<?php
/**
 * Front-end join/leave-a-community actions from the member dashboard. Joining
 * is immediate (see RBN_Community_Memberships's docblock for why there's no
 * approval step yet) - this class only enforces that a member can only join
 * a community whose destination country matches their own current country
 * (spec Section 9), and can only act on their own membership, never
 * another user's.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Community_Forms {

	const JOIN_ACTION  = 'rbn_join_community';
	const LEAVE_ACTION = 'rbn_leave_community';

	public static function handle_request() {
		if ( empty( $_POST['rbn_form_action'] ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- only an emptiness check; nonce is verified per-action below before anything happens.
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['rbn_form_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- only used to pick a handler; that handler verifies its own nonce.

		if ( self::JOIN_ACTION === $action ) {
			self::handle_join();
		} elseif ( self::LEAVE_ACTION === $action ) {
			self::handle_leave();
		}
	}

	private static function handle_join() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$nonce = isset( $_POST['rbn_community_action_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_community_action_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::JOIN_ACTION ) ) {
			self::redirect_with_notice( 'community_invalid_request' );
		}

		$user_id      = get_current_user_id();
		$community_id = isset( $_POST['rbn_community_id'] ) ? absint( $_POST['rbn_community_id'] ) : 0;
		$community    = RBN_Communities::get_by_id( $community_id );

		if ( ! $community || RBN_Communities::STATUS_ACTIVE !== $community->status ) {
			self::redirect_with_notice( 'community_not_found' );
		}

		// The one access rule that already exists (spec Section 9): a
		// member may only join a community whose destination country
		// matches their own current country - never a client-supplied
		// assumption, always re-checked server-side against their own
		// stored current_country_id.
		if ( (int) $community->destination_country_id !== RBN_Countries::get_current_country_id( $user_id ) ) {
			self::redirect_with_notice( 'community_wrong_country' );
		}

		RBN_Community_Memberships::join( $user_id, $community_id );

		self::redirect_with_notice( 'community_joined' );
	}

	private static function handle_leave() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$nonce = isset( $_POST['rbn_community_action_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_community_action_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::LEAVE_ACTION ) ) {
			self::redirect_with_notice( 'community_invalid_request' );
		}

		$community_id = isset( $_POST['rbn_community_id'] ) ? absint( $_POST['rbn_community_id'] ) : 0;

		// Always the current user's own membership - a community_id alone
		// can never target someone else's row, since leave() is scoped to
		// get_current_user_id().
		RBN_Community_Memberships::leave( get_current_user_id(), $community_id );

		self::redirect_with_notice( 'community_left' );
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
