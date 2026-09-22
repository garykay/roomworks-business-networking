<?php
/**
 * Handles a logged-in member updating their own profile (name, display
 * name, bio, origin/current country). Always acts on
 * get_current_user_id() - never a client-supplied user ID - so a member
 * can only ever edit themselves.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Profile_Forms {

	const ACTION = 'rbn_update_profile';

	public static function handle_request() {
		if ( empty( $_POST['rbn_form_action'] ) || self::ACTION !== $_POST['rbn_form_action'] || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- strict comparisons against literals; the actual nonce is verified just below before anything happens.
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$nonce = isset( $_POST['rbn_profile_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_profile_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			self::redirect_with_notice( 'profile_invalid_request' );
		}

		$first_name         = isset( $_POST['rbn_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_first_name'] ) ) : '';
		$last_name          = isset( $_POST['rbn_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_last_name'] ) ) : '';
		$display            = isset( $_POST['rbn_display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_display_name'] ) ) : '';
		$bio                = isset( $_POST['rbn_bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rbn_bio'] ) ) : '';
		// Optional - not every member wants to share a phone number at all;
		// see RBN_Templates::job_form()'s per-listing "hide my phone number"
		// option for the other half of that opt-in/opt-out pairing.
		$phone_number       = isset( $_POST['rbn_phone_number'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_phone_number'] ) ) : '';
		$origin_country_id  = isset( $_POST['rbn_origin_country_id'] ) ? absint( $_POST['rbn_origin_country_id'] ) : 0;
		$current_country_id = isset( $_POST['rbn_current_country_id'] ) ? absint( $_POST['rbn_current_country_id'] ) : 0;

		if ( '' === $first_name || '' === $last_name ) {
			self::redirect_with_notice( 'profile_missing_fields' );
		}

		if ( ! RBN_Countries::get_by_id( $origin_country_id ) || ! RBN_Countries::get_by_id( $current_country_id ) ) {
			self::redirect_with_notice( 'profile_invalid_country' );
		}

		if ( '' === $display ) {
			$display = trim( $first_name . ' ' . $last_name );
		}

		$user_id = get_current_user_id();

		wp_update_user(
			array(
				'ID'           => $user_id,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => $display,
				'nickname'     => $display,
			)
		);

		update_user_meta( $user_id, 'description', $bio );
		update_user_meta( $user_id, 'rbn_phone_number', $phone_number );

		// Validated against RBN_Countries::get_by_id() above, so these
		// set_*_country() calls cannot fail here - still checked because
		// set_*_country() is the single place that enforces "only a real
		// country ID is ever stored", not just this one call site.
		RBN_Countries::set_origin_country( $user_id, $origin_country_id );
		RBN_Countries::set_current_country( $user_id, $current_country_id );

		// Ensures a community exists for this origin/destination pair so a
		// member changing their current country is never left with an
		// empty "Available Communities" list just because no admin has set
		// up their new combination yet - see the method's own docblock for
		// why this can never create a duplicate.
		RBN_Communities::get_or_create_for_pair( $origin_country_id, $current_country_id );

		self::redirect_with_notice( 'profile_updated' );
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
