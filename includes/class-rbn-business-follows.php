<?php
/**
 * A member following a business - lets them build a personal shortlist
 * ("Favourites") independent of the community+country scoping that governs
 * the directory listing itself (RBN_Business_Query::community_scope_clause()).
 * A follow, once made, isn't removed just because the followed business
 * later falls outside the follower's current community/country match -
 * same "persists until explicitly undone" precedent as community
 * membership (RBN_Community_Memberships).
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Business_Follows {

	const CACHE_GROUP = 'rbn_business_follows';

	/**
	 * Follows a business. Idempotent - following a business the user
	 * already follows is a no-op, not an error, matching
	 * RBN_Community_Memberships::join()'s same reasoning (also enforced at
	 * the DB level by the user_business UNIQUE key).
	 *
	 * @return bool True if the user (now) follows this business.
	 */
	public static function follow( $user_id, $business_id ) {
		$user_id     = absint( $user_id );
		$business_id = absint( $business_id );

		if ( ! $user_id || ! $business_id ) {
			return false;
		}

		if ( self::is_following( $user_id, $business_id ) ) {
			return true;
		}

		global $wpdb;

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			RBN_Schema::business_follows_table(),
			array(
				'user_id'     => $user_id,
				'business_id' => $business_id,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);

		self::flush_cache( $user_id );

		return (bool) $inserted;
	}

	/**
	 * Unfollows a business - a hard delete, same as
	 * RBN_Community_Memberships::leave(): there's nothing that needs a
	 * record of a past follow.
	 */
	public static function unfollow( $user_id, $business_id ) {
		global $wpdb;

		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			RBN_Schema::business_follows_table(),
			array(
				'user_id'     => absint( $user_id ),
				'business_id' => absint( $business_id ),
			),
			array( '%d', '%d' )
		);

		self::flush_cache( $user_id );

		return (bool) $deleted;
	}

	public static function is_following( $user_id, $business_id ) {
		$business_id = absint( $business_id );

		return in_array( $business_id, self::get_followed_business_ids_for_user( $user_id ), true );
	}

	/**
	 * Every business ID a user follows, cached per-user - read on every
	 * directory/notice-board... no, directory page load (once per request,
	 * not once per card - see RBN_Business_Query::results(), which reads
	 * this once and threads the result through normalize() rather than
	 * querying per business, per the scalability spec's "avoid N+1
	 * queries" rule, Section 23).
	 *
	 * @return int[]
	 */
	public static function get_followed_business_ids_for_user( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return array();
		}

		$cache_key = 'user_' . $user_id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$table = RBN_Schema::business_follows_table();
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT business_id FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached just below.
		$ids   = $ids ? array_map( 'absint', $ids ) : array();

		wp_cache_set( $cache_key, $ids, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $ids;
	}

	/**
	 * The businesses a user follows, resolved to full posts - only
	 * currently-published ones, most-recently-followed first (matches
	 * get_followed_business_ids_for_user()'s DB order, since that column
	 * has no other sort applied). A business that's since been
	 * unpublished/deleted simply drops out of this list without the
	 * underlying follow row being touched - if it's republished later, it
	 * reappears with no need to re-follow it.
	 *
	 * @return WP_Post[]
	 */
	public static function get_followed_businesses_for_user( $user_id ) {
		$business_ids = self::get_followed_business_ids_for_user( $user_id );

		if ( ! $business_ids ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => RBN_Post_Type_Business::POST_TYPE,
				'post_status'    => 'publish',
				'post__in'       => $business_ids,
				'orderby'        => 'post__in',
				'posts_per_page' => -1,
			)
		);
	}

	/**
	 * Cleans up a member's own follow rows on account deletion - called
	 * from RBN_Account_Deletion::delete_related_data(), same as the
	 * equivalent rbn_follows cleanup. Rows on the OTHER side (someone else
	 * following one of this user's now-deleted businesses) are cleaned up
	 * separately by cleanup_on_business_deleted() below, hooked to
	 * before_delete_post - core's wp_delete_user() deletes the user's posts
	 * itself, which fires that hook for each one.
	 */
	public static function delete_all_for_user( $user_id ) {
		global $wpdb;

		$wpdb->delete( RBN_Schema::business_follows_table(), array( 'user_id' => absint( $user_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		self::flush_cache( $user_id );
	}

	/**
	 * Removes every follow row pointing at a business that's about to be
	 * permanently deleted - hooked to before_delete_post in the main plugin
	 * file, not scoped to any one deletion path, so it also covers an
	 * admin manually deleting a business from wp-admin, not just the
	 * account-deletion flow.
	 */
	public static function cleanup_on_business_deleted( $post_id ) {
		if ( RBN_Post_Type_Business::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		global $wpdb;

		$table = RBN_Schema::business_follows_table();

		// The followers themselves are the ones with a per-user cache entry
		// to invalidate - fetched before the delete below removes the rows
		// that would otherwise identify them.
		$follower_ids = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE business_id = %d", absint( $post_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$wpdb->delete( $table, array( 'business_id' => absint( $post_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $follower_ids as $follower_id ) {
			self::flush_cache( $follower_id );
		}
	}

	private static function flush_cache( $user_id ) {
		wp_cache_delete( 'user_' . absint( $user_id ), self::CACHE_GROUP );
	}
}
