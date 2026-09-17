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
	 * including legacy accounts with no status meta at all.
	 */
	public static function member_count() {
		$query = new WP_User_Query(
			array(
				'fields'     => 'ID',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => RBN_Member_Approval::META_KEY,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => RBN_Member_Approval::META_KEY,
						'value' => RBN_Member_Approval::STATUS_APPROVED,
					),
				),
			)
		);

		return (int) $query->get_total();
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
