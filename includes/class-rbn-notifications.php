<?php
/**
 * In-app (never emailed) notifications for a business owner - currently
 * just "someone followed your business", raised from
 * RBN_Business_Follows::follow()'s rbn_business_followed action rather than
 * called directly, so any future source of a business-follow (an import, a
 * REST endpoint) gets a notification for free. Deliberately separate from
 * RBN_Emails/RBN_Job_Notifications: those exist to get a message off-site
 * into someone's inbox, this exists to survive until the recipient next
 * visits their own dashboard, which a queued email has no reason to know
 * about.
 *
 * A row here always mirrors a currently-active RBN_Business_Follows
 * relationship, not a historical log of every follow that's ever happened -
 * delete_business_followed(), hooked to unfollow()'s rbn_business_unfollowed
 * action, removes the row the moment the follow itself does, and the
 * notifications table's follower_business UNIQUE key is what stops
 * unfollow+refollow from ever piling up duplicates for the same pair.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Notifications {

	const CACHE_GROUP = 'rbn_notifications';

	const TYPE_BUSINESS_FOLLOWED = 'business_followed';

	/**
	 * Handler for the rbn_business_followed action - resolves the business's
	 * owner (its post_author; RBN_Business_Repository has no "team" concept
	 * yet, so this is the only recipient there is) and records a row for
	 * them. Silently no-ops for a business with no author or one somehow
	 * following their own listing (business_follow_form() already prevents
	 * the latter via the business_follow_own_business notice, but this stays
	 * defensive rather than trusting every future caller of the action to
	 * repeat that check).
	 */
	public static function create_business_followed( $follower_id, $business_id ) {
		$follower_id = absint( $follower_id );
		$business_id = absint( $business_id );

		if ( ! $follower_id || ! $business_id ) {
			return;
		}

		$recipient_id = absint( get_post_field( 'post_author', $business_id ) );

		if ( ! $recipient_id || $recipient_id === $follower_id ) {
			return;
		}

		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			RBN_Schema::notifications_table(),
			array(
				'recipient_id' => $recipient_id,
				'type'         => self::TYPE_BUSINESS_FOLLOWED,
				'follower_id'  => $follower_id,
				'business_id'  => $business_id,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%d', '%s' )
		);

		self::flush_cache( $recipient_id );
	}

	/**
	 * Handler for the rbn_business_unfollowed action - removes the
	 * notification row for this follower/business pair, if one exists,
	 * mirroring the business_follows row that unfollow() just deleted.
	 * Looks up the affected recipient(s) from the row itself rather than
	 * re-resolving the business's current post_author, since a business
	 * could in principle have changed owner since the row was created; the
	 * cache to flush belongs to whoever the row actually says it notified.
	 */
	public static function delete_business_followed( $follower_id, $business_id ) {
		$follower_id = absint( $follower_id );
		$business_id = absint( $business_id );

		if ( ! $follower_id || ! $business_id ) {
			return;
		}

		global $wpdb;

		$table = RBN_Schema::notifications_table();

		$recipient_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT recipient_id FROM {$table} WHERE follower_id = %d AND business_id = %d", $follower_id, $business_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array(
				'follower_id' => $follower_id,
				'business_id' => $business_id,
			),
			array( '%d', '%d' )
		);

		foreach ( $recipient_ids as $recipient_id ) {
			self::flush_cache( $recipient_id );
		}
	}

	/**
	 * Unread count for the "Followers" dashboard tab's badge - cached
	 * per-user the same way RBN_Business_Follows caches followed-business
	 * IDs, since it's read on every dashboard load regardless of which tab
	 * ends up active. Joined against business_follows for the same reason
	 * get_notifications_for_user() is below - see that method's docblock.
	 */
	public static function get_unread_count_for_user( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return 0;
		}

		$cache_key = 'unread_' . $user_id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;

		$notifications_table = RBN_Schema::notifications_table();
		$follows_table       = RBN_Schema::business_follows_table();
		$count               = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$notifications_table} n INNER JOIN {$follows_table} bf ON bf.user_id = n.follower_id AND bf.business_id = n.business_id WHERE n.recipient_id = %d AND n.read_at IS NULL", $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached just below.

		wp_cache_set( $cache_key, $count, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $count;
	}

	/**
	 * The "Followers" tab's list, most recent first - every business-follow
	 * currently pointed at one of this member's businesses (see this
	 * class's own docblock re: a row here always mirroring a live
	 * RBN_Business_Follows relationship, not a log of past ones), resolved
	 * to the follower's WP_User and the business they follow. Dropped if
	 * either has since been deleted (a user who deleted their own account,
	 * or a business removed some other way than unfollow/cleanup_on_
	 * business_deleted() below having already run - e.g. mid-request
	 * ordering). Not cached: read once per dashboard load, same as
	 * RBN_Business_Follows::get_followed_businesses_for_user()'s own
	 * resolved-object list.
	 *
	 * Joined against business_follows itself, not just trusted to already
	 * be in sync - delete_business_followed()/cleanup_on_business_deleted()
	 * keep this table's rows matching that one going forward, but a row
	 * created before those existed (or by any future path that raises
	 * rbn_business_followed without a matching unfollow cleanup ever
	 * running) would otherwise sit here as a ghost "follower" forever. The
	 * join makes "still actually following" the source of truth for what's
	 * shown, same way get_followed_businesses_for_user() already treats
	 * "still published" as the source of truth for Favourites.
	 *
	 * @return object[] Each with ->id, ->follower (WP_User), ->business
	 *                  (WP_Post), ->created_at, ->is_unread.
	 */
	public static function get_notifications_for_user( $user_id, $limit = 20 ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return array();
		}

		global $wpdb;

		$notifications_table = RBN_Schema::notifications_table();
		$follows_table       = RBN_Schema::business_follows_table();
		$rows                = $wpdb->get_results( $wpdb->prepare( "SELECT n.id, n.follower_id, n.business_id, n.created_at, n.read_at FROM {$notifications_table} n INNER JOIN {$follows_table} bf ON bf.user_id = n.follower_id AND bf.business_id = n.business_id WHERE n.recipient_id = %d ORDER BY n.created_at DESC LIMIT %d", $user_id, absint( $limit ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$notifications = array();

		foreach ( $rows as $row ) {
			$follower = get_userdata( $row->follower_id );
			$business = get_post( $row->business_id );

			if ( ! $follower || ! $business ) {
				continue;
			}

			$notifications[] = (object) array(
				'id'         => (int) $row->id,
				'follower'   => $follower,
				'business'   => $business,
				'created_at' => $row->created_at,
				'is_unread'  => null === $row->read_at,
			);
		}

		return $notifications;
	}

	/**
	 * Clears the badge - called once the member has actually seen the
	 * Followers tab (see the mark-read REST route registered by
	 * RBN_REST_Notifications), not on every dashboard load: the tab's
	 * content is server-rendered regardless of which tab ends up active
	 * (see RBN_Templates::member_dashboard()), so marking read at render
	 * time would clear a badge the member never looked at.
	 */
	public static function mark_all_read_for_user( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return;
		}

		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			RBN_Schema::notifications_table(),
			array( 'read_at' => current_time( 'mysql' ) ),
			array(
				'recipient_id' => $user_id,
				'read_at'      => null,
			),
			array( '%s' ),
			array( '%d', '%s' )
		);

		self::flush_cache( $user_id );
	}

	/**
	 * Cleans up a member's notification rows on account deletion - called
	 * from RBN_Account_Deletion::delete_related_data(), same spot as the
	 * equivalent RBN_Business_Follows cleanup. Covers both directions: rows
	 * where this user was the recipient (their own business's notifications)
	 * and rows where they were the follower (someone else's notification
	 * about them) - unlike RBN_Business_Follows::cleanup_on_business_deleted(),
	 * this doesn't need a separate before_delete_post hook for the "someone
	 * followed my business" side, since wp_delete_user() deleting this
	 * user's businesses already fires cleanup_on_business_deleted() below,
	 * which removes those rows by business_id.
	 */
	public static function delete_all_for_user( $user_id ) {
		$user_id = absint( $user_id );

		global $wpdb;

		$table = RBN_Schema::notifications_table();

		$wpdb->delete( $table, array( 'recipient_id' => $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'follower_id' => $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		self::flush_cache( $user_id );
	}

	/**
	 * Removes every notification pointing at a business that's about to be
	 * permanently deleted - hooked to before_delete_post in the main plugin
	 * file, same event RBN_Business_Follows::cleanup_on_business_deleted()
	 * already hooks for the follow rows themselves.
	 */
	public static function cleanup_on_business_deleted( $post_id ) {
		if ( RBN_Post_Type_Business::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		global $wpdb;

		$table = RBN_Schema::notifications_table();

		// The recipients are the ones with a per-user unread-count cache
		// entry to invalidate - fetched before the delete below removes the
		// rows that would otherwise identify them.
		$recipient_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT recipient_id FROM {$table} WHERE business_id = %d", absint( $post_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$wpdb->delete( $table, array( 'business_id' => absint( $post_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $recipient_ids as $recipient_id ) {
			self::flush_cache( $recipient_id );
		}
	}

	private static function flush_cache( $user_id ) {
		wp_cache_delete( 'unread_' . absint( $user_id ), self::CACHE_GROUP );
	}
}
