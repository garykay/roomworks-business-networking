<?php
/**
 * Data access for the member/business relationship. A member may own more
 * than one business (e.g. two unrelated companies) - nothing here caps
 * that. A business belongs to exactly one community (its rbn_community_id
 * meta, set on the business form), which is what gives each listing its
 * own context, not "the member's one business" the way this used to work.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Business_Repository {

	/**
	 * The set of statuses that count as "this user owns this business" -
	 * everything except trash.
	 */
	const OWNERSHIP_STATUSES = array( 'publish', 'pending', 'draft', 'future', 'private' );

	/**
	 * Every business owned by a user, any ownership status, ordered by
	 * name - used for the "My Businesses" list on the dashboard.
	 */
	public static function get_all_for_user( $user_id ) {
		return get_posts(
			array(
				'post_type'      => RBN_Post_Type_Business::POST_TYPE,
				'post_status'    => self::OWNERSHIP_STATUSES,
				'author'         => $user_id,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * A specific business, but only if it's actually owned by $user_id -
	 * the edit form now targets one business among possibly several by ID
	 * (see RBN_Business_Forms), so a request's business ID must always be
	 * re-verified server-side against the logged-in user rather than
	 * trusted - a member could otherwise submit any other member's post ID.
	 * Returns null for a non-existent business, someone else's business,
	 * the wrong post type, or a trashed one.
	 */
	public static function get_by_id_for_user( $business_id, $user_id ) {
		$business_id = absint( $business_id );

		if ( ! $business_id ) {
			return null;
		}

		$business = get_post( $business_id );

		if ( ! $business || RBN_Post_Type_Business::POST_TYPE !== $business->post_type ) {
			return null;
		}

		if ( absint( $user_id ) !== (int) $business->post_author ) {
			return null;
		}

		if ( ! in_array( $business->post_status, self::OWNERSHIP_STATUSES, true ) ) {
			return null;
		}

		return $business;
	}

	/**
	 * A specific business, regardless of who owns it, but only if it's
	 * actually published - used by RBN_Business_Follow_Forms, where "can
	 * this be followed" means "is this publicly visible", not "does the
	 * current user own it" (the opposite check from get_by_id_for_user()
	 * above). Returns null for a non-existent business, the wrong post
	 * type, or anything not published (pending/draft/trashed).
	 */
	public static function get_published( $business_id ) {
		$business_id = absint( $business_id );

		if ( ! $business_id ) {
			return null;
		}

		$business = get_post( $business_id );

		if ( ! $business || RBN_Post_Type_Business::POST_TYPE !== $business->post_type || 'publish' !== $business->post_status ) {
			return null;
		}

		return $business;
	}
}
