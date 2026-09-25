<?php
/**
 * Community membership - which communities a member has joined. See the
 * scalability spec's Section 8. A separate table rather than a field on the
 * user, since one member can belong to more than one community (e.g. a
 * national community and a regional one later).
 *
 * Joining is currently immediate/self-service (no community-admin approval
 * step exists yet - see the spec's Section 17: "do not implement every
 * role immediately unless required"), unlike account registration, which
 * does require admin approval. status/role are still modelled now so an
 * approval step can be added later without a schema change.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Community_Memberships {

	const ROLE_MEMBER = 'member';

	const STATUS_ACTIVE    = 'active';
	const STATUS_PENDING   = 'pending';
	const STATUS_SUSPENDED = 'suspended';

	/**
	 * Joins a user to a community. Idempotent - joining a community the
	 * user already belongs to is a no-op, not an error, so a form re-submit
	 * or a stale "Join" button can't create a duplicate row (also enforced
	 * at the DB level by the user_community UNIQUE key).
	 *
	 * @return bool True if the user is (now) an active member.
	 */
	public static function join( $user_id, $community_id ) {
		$user_id      = absint( $user_id );
		$community_id = absint( $community_id );

		if ( ! $user_id || ! RBN_Communities::get_by_id( $community_id ) ) {
			return false;
		}

		if ( self::is_member( $user_id, $community_id ) ) {
			return true;
		}

		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			RBN_Schema::community_memberships_table(),
			array(
				'user_id'      => $user_id,
				'community_id' => $community_id,
				'role'         => self::ROLE_MEMBER,
				'status'       => self::STATUS_ACTIVE,
				'joined_at'    => $now,
				'approved_at'  => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		self::flush_cache( $user_id );

		return (bool) $inserted;
	}

	/**
	 * Leaves a community - a hard delete, not a status change: there's
	 * nothing yet that needs a record of a past membership (unlike account
	 * deletion, which has a grace period for a different reason).
	 */
	public static function leave( $user_id, $community_id ) {
		global $wpdb;

		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			RBN_Schema::community_memberships_table(),
			array(
				'user_id'      => absint( $user_id ),
				'community_id' => absint( $community_id ),
			),
			array( '%d', '%d' )
		);

		self::flush_cache( $user_id );

		return (bool) $deleted;
	}

	public static function is_member( $user_id, $community_id ) {
		$community_id = absint( $community_id );

		foreach ( self::get_for_user( $user_id ) as $membership ) {
			if ( (int) $membership->community_id === $community_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Every membership row for a user (any status), cached per-user - read
	 * on most logged-in page loads (dashboard, access-control checks once
	 * those exist).
	 */
	public static function get_for_user( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return array();
		}

		$cache_key = 'user_' . $user_id;
		$cached    = wp_cache_get( $cache_key, RBN_Communities::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$table       = RBN_Schema::community_memberships_table();
		$memberships = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached just below.

		$memberships = $memberships ? $memberships : array();

		wp_cache_set( $cache_key, $memberships, RBN_Communities::CACHE_GROUP, HOUR_IN_SECONDS );

		return $memberships;
	}

	/**
	 * The communities a user is an active member of, resolved to full
	 * community rows (not just IDs) for display.
	 */
	public static function get_communities_for_user( $user_id ) {
		$communities = array();

		foreach ( self::get_for_user( $user_id ) as $membership ) {
			if ( self::STATUS_ACTIVE !== $membership->status ) {
				continue;
			}

			$community = RBN_Communities::get_by_id( $membership->community_id );

			if ( $community ) {
				$communities[] = $community;
			}
		}

		return $communities;
	}

	/**
	 * The communities a user is both (a) an active member of and (b) whose
	 * destination country matches the given country - the eligibility rule
	 * RBN_Business_Query::community_scope_clause() already applies for
	 * directory visibility, factored out here so the job form's community
	 * picker (which must offer the same restricted set - see
	 * RBN_Job_Forms) doesn't duplicate the intersection logic a third time.
	 *
	 * Ordered by joined_at ascending, oldest first - RBN_Job_Forms relies on
	 * this order to pick a default community when a member submits the job
	 * form with none checked.
	 *
	 * @return object[] Community rows (RBN_Communities objects), oldest joined first.
	 */
	public static function get_communities_for_user_in_country( $user_id, $country_id ) {
		$country_id = absint( $country_id );

		if ( ! $country_id ) {
			return array();
		}

		$in_country_ids = array_map( 'absint', wp_list_pluck( RBN_Communities::get_for_destination_country( $country_id ), 'id' ) );

		$eligible = array();

		foreach ( self::get_for_user( $user_id ) as $membership ) {
			if ( self::STATUS_ACTIVE !== $membership->status || ! in_array( (int) $membership->community_id, $in_country_ids, true ) ) {
				continue;
			}

			$community = RBN_Communities::get_by_id( $membership->community_id );

			// Cloned rather than mutated in place - get_by_id() returns
			// objects straight out of RBN_Communities' own request-lifetime
			// cache (see its CACHE_GROUP), so writing directly to $community
			// would leak this membership's joined_at onto that shared,
			// user-independent cached object for every other caller.
			if ( $community ) {
				$community              = clone $community;
				$community->joined_at   = $membership->joined_at;
				$eligible[ $membership->joined_at . '_' . $community->id ] = $community;
			}
		}

		ksort( $eligible );

		return array_values( $eligible );
	}

	/**
	 * Active member user IDs for a community - the read side of "who should
	 * be notified about this community's activity" (see
	 * RBN_Job_Notifications::send_new_request_notification()). Not cached
	 * like get_for_user() above: only ever read from a background WP-Cron
	 * callback, not a page-load path.
	 */
	public static function get_member_ids_for_community( $community_id ) {
		global $wpdb;

		$table = RBN_Schema::community_memberships_table();

		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE community_id = %d AND status = %s", absint( $community_id ), self::STATUS_ACTIVE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cron-only, not a page-load path.

		return array_map( 'absint', $ids );
	}

	public static function member_count( $community_id ) {
		global $wpdb;

		$table = RBN_Schema::community_memberships_table();

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE community_id = %d AND status = %s", absint( $community_id ), self::STATUS_ACTIVE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin-page-only, not worth a cache entry.
	}

	private static function flush_cache( $user_id ) {
		wp_cache_delete( 'user_' . absint( $user_id ), RBN_Communities::CACHE_GROUP );
	}
}
