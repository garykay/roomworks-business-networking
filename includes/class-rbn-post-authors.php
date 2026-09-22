<?php
/**
 * Widens who can appear in the Author picker (Publish box / block editor
 * sidebar) beyond WordPress's built-in who=authors set (anyone with
 * edit_posts on some public post type) to also include members
 * (subscribers), since Admin needs to credit a post to the business owner
 * it's actually about, not just users who can edit posts themselves. This
 * only widens the picker's own user list via capability__in (an OR match -
 * a user only needs one of ELIGIBLE_CAPS) - it never grants members
 * edit_posts itself, so no other part of their access changes.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Post_Authors {

	const ELIGIBLE_CAPS = array( 'edit_posts', 'edit_rbn_business' );

	/**
	 * Classic meta box path: post_author_meta_box() calls wp_dropdown_users()
	 * with who=authors to build the Author <select>.
	 */
	public static function filter_dropdown_users_args( $query_args ) {
		if ( isset( $query_args['who'] ) && 'authors' === $query_args['who'] ) {
			unset( $query_args['who'] );
			$query_args['capability__in'] = self::ELIGIBLE_CAPS;
		}

		return $query_args;
	}

	/**
	 * Block editor path: the Post Author sidebar panel fetches
	 * /wp/v2/users?who=authors, which WP_REST_Users_Controller turns into
	 * the same plain who=authors WP_User_Query arg.
	 */
	public static function filter_rest_user_query( $prepared_args, $request ) {
		if ( isset( $prepared_args['who'] ) && 'authors' === $prepared_args['who'] ) {
			unset( $prepared_args['who'] );
			$prepared_args['capability__in'] = self::ELIGIBLE_CAPS;
		}

		return $prepared_args;
	}
}
