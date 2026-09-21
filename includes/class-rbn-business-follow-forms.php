<?php
/**
 * Front-end follow/unfollow-a-business actions - a plain POST form, same
 * no-JS-first pattern as RBN_Community_Forms's join/leave (see that class's
 * docblock). Used both on the single business profile page and on each
 * directory card (RBN_Templates::business_follow_form()), so this only
 * ever needs a business ID and redirects back to wherever the form was
 * submitted from.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Business_Follow_Forms {

	const FOLLOW_ACTION   = 'rbn_follow_business';
	const UNFOLLOW_ACTION = 'rbn_unfollow_business';

	public static function handle_request() {
		if ( empty( $_POST['rbn_form_action'] ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- only an emptiness check; nonce is verified per-action below before anything happens.
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['rbn_form_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- only used to pick a handler; that handler verifies its own nonce.

		if ( self::FOLLOW_ACTION === $action ) {
			self::handle_follow();
		} elseif ( self::UNFOLLOW_ACTION === $action ) {
			self::handle_unfollow();
		}
	}

	private static function handle_follow() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$nonce = isset( $_POST['rbn_business_follow_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_business_follow_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::FOLLOW_ACTION ) ) {
			self::redirect_with_notice( 'business_follow_invalid_request' );
		}

		$user_id     = get_current_user_id();
		$business_id = isset( $_POST['rbn_business_id'] ) ? absint( $_POST['rbn_business_id'] ) : 0;

		// Only a currently-published business can be followed - never a
		// client-supplied assumption. get_published() also rejects a
		// non-existent ID or the wrong post type outright.
		$business = RBN_Business_Repository::get_published( $business_id );

		if ( ! $business ) {
			self::redirect_with_notice( 'business_follow_not_found' );
		}

		if ( $user_id === (int) $business->post_author ) {
			self::redirect_with_notice( 'business_follow_own_business' );
		}

		RBN_Business_Follows::follow( $user_id, $business_id );

		self::redirect_with_notice( 'business_followed' );
	}

	private static function handle_unfollow() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$nonce = isset( $_POST['rbn_business_follow_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_business_follow_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::UNFOLLOW_ACTION ) ) {
			self::redirect_with_notice( 'business_follow_invalid_request' );
		}

		$business_id = isset( $_POST['rbn_business_id'] ) ? absint( $_POST['rbn_business_id'] ) : 0;

		// Always the current user's own follow row - a business_id alone
		// can never target someone else's, since unfollow() is scoped to
		// get_current_user_id().
		RBN_Business_Follows::unfollow( get_current_user_id(), $business_id );

		self::redirect_with_notice( 'business_unfollowed' );
	}

	/**
	 * wp_validate_redirect() below is the sanitization step, matching every
	 * other *_Forms class in this plugin.
	 */
	private static function redirect_target() {
		$target = isset( $_POST['rbn_redirect_to'] ) ? wp_unslash( $_POST['rbn_redirect_to'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- nonce verified by the caller before this ever runs.
		return wp_validate_redirect( $target, home_url( '/' ) );
	}

	private static function redirect_with_notice( $code ) {
		wp_safe_redirect( add_query_arg( 'rbn_notice', $code, self::redirect_target() ) );
		exit;
	}
}
