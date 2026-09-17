<?php
/**
 * Site-wide counts used by the stats counter block: approved members,
 * published businesses, and the distinct UK towns/cities they cover (as
 * recorded in the Town/City field of the Edit Your Business form).
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Stats {

	/**
	 * Members whose account isn't pending or rejected - i.e. the same
	 * "approved" definition RBN_Member_Approval uses everywhere else,
	 * including legacy accounts with no status meta at all - restricted to
	 * the `subscriber` role that RBN_Auth_Forms assigns on registration, so
	 * administrators/editors/other staff accounts (which also default to
	 * "approved" since they never got a status meta) don't inflate this
	 * public-facing count.
	 *
	 * A direct COUNT(DISTINCT ...) query is used rather than
	 * WP_User_Query::get_total(), which combines a `role` LIKE match with an
	 * OR'd meta_query across two separate usermeta JOINs - a combination that
	 * can over-count a user whose row satisfies more than one JOIN branch.
	 * DISTINCT on the user ID guarantees each member is only ever counted once.
	 */
	public static function member_count() {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( DISTINCT u.ID )
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} cap ON cap.user_id = u.ID
					AND cap.meta_key = %s
					AND cap.meta_value LIKE %s
				LEFT JOIN {$wpdb->usermeta} status ON status.user_id = u.ID
					AND status.meta_key = %s
				WHERE ( status.user_id IS NULL OR status.meta_value = %s )",
				$wpdb->get_blog_prefix() . 'capabilities',
				'%"subscriber"%',
				RBN_Member_Approval::META_KEY,
				RBN_Member_Approval::STATUS_APPROVED
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off aggregate count, no equivalent core API that avoids the JOIN over-count above.

		return (int) $count;
	}

	/**
	 * Published businesses only - pending/draft/private listings aren't
	 * visible to the public, so they shouldn't inflate a public-facing count.
	 */
	public static function business_count() {
		$counts = wp_count_posts( RBN_Post_Type_Business::POST_TYPE );

		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * Distinct towns/cities across published businesses, matched
	 * case/whitespace-insensitively so "London" and "london " aren't
	 * counted twice - the field is free text, not a fixed list.
	 */
	public static function city_count() {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( DISTINCT LOWER( TRIM( pm.meta_value ) ) )
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				AND TRIM( pm.meta_value ) != ''
				AND p.post_type = %s
				AND p.post_status = 'publish'",
				'rbn_town_city',
				RBN_Post_Type_Business::POST_TYPE
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off aggregate count, no equivalent core API.

		return (int) $count;
	}
}
