<?php
/**
 * Frontend login and registration handling for logged-out visitors.
 *
 * Runs on `init` (before any output) so it can safely set the auth cookie
 * and redirect. Uses wp_signon() and wp_insert_user() - WordPress's own
 * authentication mechanisms - rather than any custom password handling.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Auth_Forms {

	const LOGIN_ACTION    = 'rbn_login';
	const REGISTER_ACTION = 'rbn_register';

	/**
	 * Minimum time (in seconds) a real visitor should need between loading
	 * the registration form and submitting it. Scripted bots that POST
	 * straight to the endpoint almost always come in well under this.
	 */
	const MIN_SUBMIT_SECONDS = 3;

	/**
	 * Renders the anti-bot fields for the registration form: a honeypot
	 * text field (hidden from sighted users via CSS, so a screen reader
	 * or keyboard-only visitor never encounters it, but visible to bots
	 * that blindly fill every field) and a signed timestamp used to
	 * reject submissions that arrive implausibly fast. The timestamp is
	 * signed with wp_hash() (site-secret-backed) rather than trusted as
	 * plain text, so a bot can't just fabricate an older value.
	 */
	public static function honeypot_fields() {
		$timestamp = time();
		$signature = wp_hash( $timestamp . '|rbn_register_ts' );

		ob_start();
		?>
		<p class="rbn-form__honeypot" aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;">
			<label for="rbn-register-website"><?php esc_html_e( 'Leave this field blank', 'roomworks-business-networking' ); ?></label>
			<input type="text" id="rbn-register-website" name="rbn_website" tabindex="-1" autocomplete="off" />
		</p>
		<input type="hidden" name="rbn_register_ts" value="<?php echo esc_attr( $timestamp ); ?>" />
		<input type="hidden" name="rbn_register_ts_hmac" value="<?php echo esc_attr( $signature ); ?>" />
		<?php
		return ob_get_clean();
	}

	/**
	 * True if the submission looks like a bot: the honeypot was filled in,
	 * the timing signature was missing/tampered with, or it arrived faster
	 * than a human plausibly could. Callers that detect this should fail
	 * silently (respond as if registration succeeded) rather than surface
	 * an error, so scripted attempts don't learn what tripped them up.
	 */
	private static function looks_like_bot() {
		if ( ! empty( $_POST['rbn_website'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- honeypot check, not a state change.
			return true;
		}

		$timestamp = isset( $_POST['rbn_register_ts'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_register_ts'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$signature = isset( $_POST['rbn_register_ts_hmac'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_register_ts_hmac'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $timestamp || '' === $signature || ! ctype_digit( $timestamp ) ) {
			return true;
		}

		if ( ! hash_equals( wp_hash( $timestamp . '|rbn_register_ts' ), $signature ) ) {
			return true;
		}

		return ( time() - (int) $timestamp ) < self::MIN_SUBMIT_SECONDS;
	}

	public static function handle_request() {
		if ( empty( $_POST['rbn_form_action'] ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- only an emptiness/method check; the actual nonce is verified per-action below before anything happens.
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['rbn_form_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- only used to pick a handler; that handler verifies its own nonce before doing anything.

		if ( self::LOGIN_ACTION === $action ) {
			self::handle_login();
		} elseif ( self::REGISTER_ACTION === $action ) {
			self::handle_register();
		}
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

	private static function handle_login() {
		$nonce = isset( $_POST['rbn_login_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_login_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::LOGIN_ACTION ) ) {
			self::redirect_with_notice( 'login_invalid_request' );
		}

		$username = isset( $_POST['rbn_username'] ) ? sanitize_user( wp_unslash( $_POST['rbn_username'] ) ) : '';
		// Deliberately not run through sanitize_text_field() - a password
		// may legitimately contain characters it would strip or alter, and
		// wp_signon()/wp_insert_user() below handle it safely as-is (it's
		// never echoed, stored raw, or used in a query - only hashed).
		$password = isset( $_POST['rbn_password'] ) ? (string) wp_unslash( $_POST['rbn_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( '' === $username || '' === $password ) {
			self::redirect_with_notice( 'login_missing_fields' );
		}

		$user = wp_signon(
			array(
				'user_login'    => $username,
				'user_password' => $password,
				'remember'      => true,
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			$error_code = $user->get_error_code();

			if ( 'rbn_account_pending' === $error_code ) {
				self::redirect_with_notice( 'login_pending' );
			}

			if ( 'rbn_account_rejected' === $error_code ) {
				self::redirect_with_notice( 'login_rejected' );
			}

			self::redirect_with_notice( 'login_failed' );
		}

		wp_safe_redirect( self::redirect_target() );
		exit;
	}

	private static function handle_register() {
		$nonce = isset( $_POST['rbn_register_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_register_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::REGISTER_ACTION ) ) {
			self::redirect_with_notice( 'register_invalid_request' );
		}

		if ( is_user_logged_in() ) {
			self::redirect_with_notice( 'register_already_logged_in' );
		}

		if ( self::looks_like_bot() ) {
			// Respond exactly as a real registration would, without
			// creating a user - see looks_like_bot() docblock for why.
			self::redirect_with_notice( 'register_check_email' );
		}

		$first_name         = isset( $_POST['rbn_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_first_name'] ) ) : '';
		$last_name          = isset( $_POST['rbn_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_last_name'] ) ) : '';
		$email              = isset( $_POST['rbn_email'] ) ? sanitize_email( wp_unslash( $_POST['rbn_email'] ) ) : '';
		$display            = isset( $_POST['rbn_display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_display_name'] ) ) : '';
		$agreed             = ! empty( $_POST['rbn_terms'] );
		$origin_country_id  = isset( $_POST['rbn_origin_country_id'] ) ? absint( $_POST['rbn_origin_country_id'] ) : 0;
		$current_country_id = isset( $_POST['rbn_current_country_id'] ) ? absint( $_POST['rbn_current_country_id'] ) : 0;
		// Passwords are deliberately not run through sanitize_text_field() -
		// see the matching comment in handle_login().
		$password  = isset( $_POST['rbn_password'] ) ? (string) wp_unslash( $_POST['rbn_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$password2 = isset( $_POST['rbn_password_confirm'] ) ? (string) wp_unslash( $_POST['rbn_password_confirm'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( '' === $first_name || '' === $last_name || '' === $email || '' === $password ) {
			self::redirect_with_notice( 'register_missing_fields' );
		}

		if ( ! is_email( $email ) ) {
			self::redirect_with_notice( 'register_invalid_email' );
		}

		if ( strlen( $password ) < 8 ) {
			self::redirect_with_notice( 'register_weak_password' );
		}

		if ( $password !== $password2 ) {
			self::redirect_with_notice( 'register_password_mismatch' );
		}

		if ( ! $agreed ) {
			self::redirect_with_notice( 'register_terms_required' );
		}

		if ( ! RBN_Countries::get_by_id( $origin_country_id ) || ! RBN_Countries::get_by_id( $current_country_id ) ) {
			self::redirect_with_notice( 'register_invalid_country' );
		}

		if ( email_exists( $email ) || username_exists( $email ) ) {
			self::redirect_with_notice( 'register_email_exists' );
		}

		if ( '' === $display ) {
			$display = trim( $first_name . ' ' . $last_name );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => $display,
				'nickname'     => $display,
				'role'         => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			self::redirect_with_notice( 'register_failed' );
		}

		update_user_meta( $user_id, 'rbn_terms_accepted_at', current_time( 'mysql' ) );

		// Validated against RBN_Countries::get_by_id() above.
		RBN_Countries::set_origin_country( $user_id, $origin_country_id );
		RBN_Countries::set_current_country( $user_id, $current_country_id );

		// Ensures a community exists for this origin/destination pair so a
		// new member is never blocked just because no admin has set up
		// their specific combination yet - see the method's own docblock
		// for why this can never create a duplicate.
		RBN_Communities::get_or_create_for_pair( $origin_country_id, $current_country_id );

		// New accounts require the member to click their activation email
		// before they can log in - see RBN_Member_Approval - so we
		// deliberately do not log the member in here the way a normal
		// registration flow would.
		RBN_Member_Approval::mark_pending( $user_id );
		RBN_Member_Approval::send_activation_email( $user_id );

		self::redirect_with_notice( 'register_check_email' );
	}

}
