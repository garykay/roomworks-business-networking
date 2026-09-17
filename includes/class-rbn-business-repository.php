<?php
/**
 * Data access for the member/business relationship, and enforcement of the
 * "one member = one business" rule.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Business_Repository {

	/**
	 * The set of statuses that count as "this user already has a business" -
	 * everything except trash, so a trashed business frees the user up to
	 * create a new one.
	 */
	const OWNERSHIP_STATUSES = array( 'publish', 'pending', 'draft', 'future', 'private' );

	public static function get_for_user( $user_id ) {
		$business_ids = get_posts(
			array(
				'post_type'      => RBN_Post_Type_Business::POST_TYPE,
				'post_status'    => self::OWNERSHIP_STATUSES,
				'author'         => $user_id,
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		return $business_ids ? get_post( $business_ids[0] ) : null;
	}

	public static function user_has_business( $user_id, $exclude_post_id = 0 ) {
		$business_ids = get_posts(
			array(
				'post_type'      => RBN_Post_Type_Business::POST_TYPE,
				'post_status'    => self::OWNERSHIP_STATUSES,
				'author'         => $user_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'exclude'        => $exclude_post_id ? array( $exclude_post_id ) : array(),
			)
		);

		return ! empty( $business_ids );
	}

	/**
	 * WordPress has no native way to enforce a unique-per-author constraint
	 * on a post type. The application's own creation path (built in a later
	 * phase) will check user_has_business() before ever calling
	 * wp_insert_post(), but this hook is a server-side safety net that
	 * catches a second business however it was created - e.g. directly in
	 * wp-admin - by trashing it immediately rather than leaving two live
	 * businesses for the same member.
	 */
	public static function enforce_single_business( $post_id, $post, $update ) {
		if ( $update || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! self::user_has_business( $post->post_author, $post_id ) ) {
			return;
		}

		remove_action( 'save_post_' . RBN_Post_Type_Business::POST_TYPE, array( __CLASS__, 'enforce_single_business' ), 10 );
		wp_trash_post( $post_id );
		add_action( 'save_post_' . RBN_Post_Type_Business::POST_TYPE, array( __CLASS__, 'enforce_single_business' ), 10, 3 );
	}
}
