<?php
/**
 * Handles a logged-in member requesting or cancelling deletion of their own
 * account. Always acts on get_current_user_id() - never a client-supplied
 * user ID - and never deletes anything itself: it only schedules/cancels
 * the request via RBN_Account_Deletion, which is what actually performs the
 * deletion 24 hours later via WP-Cron. A member can never delete their
 * account outright through this plugin, by design.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Account_Deletion_Forms {

	const REQUEST_ACTION = 'rbn_request_account_deletion';
	const CANCEL_ACTION  = 'rbn_cancel_account_deletion';

	public static function handle_request() {
		if ( empty( $_POST['rbn_form_action'] ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- only an emptiness/method check; the actual nonce is verified per-action below before anything happens.
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$action = isset( $_POST['rbn_form_action'] ) ? sanitize_key( wp_unslash( $_POST['rbn_form_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- only used to pick a handler; that handler verifies its own nonce before doing anything.

		if ( self::REQUEST_ACTION === $action ) {
			self::handle_deletion_request();
		} elseif ( self::CANCEL_ACTION === $action ) {
			self::handle_deletion_cancel();
		}
	}

	private static function handle_deletion_request() {
		$nonce = isset( $_POST['rbn_account_deletion_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_account_deletion_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::REQUEST_ACTION ) ) {
			self::redirect_with_notice( 'account_deletion_invalid_request' );
		}

		// Self-service deletion is a member feature - RBN_Templates already
		// doesn't offer the button to administrators, this is the
		// server-side backstop in case a request is ever POSTed directly.
		if ( current_user_can( 'administrator' ) ) {
			self::redirect_with_notice( 'account_deletion_not_permitted' );
		}

		RBN_Account_Deletion::request( get_current_user_id() );

		self::redirect_with_notice( 'account_deletion_requested' );
	}

	private static function handle_deletion_cancel() {
		$nonce = isset( $_POST['rbn_account_deletion_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_account_deletion_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::CANCEL_ACTION ) ) {
			self::redirect_with_notice( 'account_deletion_invalid_request' );
		}

		RBN_Account_Deletion::cancel( get_current_user_id() );

		self::redirect_with_notice( 'account_deletion_cancelled' );
	}

	/**
	 * wp_validate_redirect() below is the sanitization step - it rejects
	 * anything that isn't a safe local/allowed-host URL, falling back to
	 * home_url( '/' ) - so this is intentionally not sanitized any further.
	 */
	private static function redirect_target() {
		$target = isset( $_POST['rbn_redirect_to'] ) ? wp_unslash( $_POST['rbn_redirect_to'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- see docblock; nonce is verified by the caller before this ever runs.
		return wp_validate_redirect( $target, home_url( '/' ) );
	}

	private static function redirect_with_notice( $code ) {
		wp_safe_redirect( add_query_arg( 'rbn_notice', $code, self::redirect_target() ) );
		exit;
	}
}
