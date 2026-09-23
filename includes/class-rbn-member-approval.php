<?php
/**
 * Member account activation. A newly registered member is "pending" until
 * they click the activation link emailed to them (RBN_Emails::activation_email())
 * - admins are no longer expected to review every signup by hand. An admin
 * can still force-activate, reject, or resend the activation email for a
 * pending account from the row actions this class adds to the wp-admin
 * Users screen, for the rare case that needs a human (a bounced activation
 * email, an obvious spam signup).
 *
 * Accounts with no status meta at all (every user that existed before this
 * feature, including the site's own administrator) are treated as approved
 * by default - the gate is opt-in per user, never retroactive.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Member_Approval {

	const META_KEY = 'rbn_account_status';

	const STATUS_PENDING  = 'pending';
	const STATUS_APPROVED = 'approved';
	const STATUS_REJECTED = 'rejected';

	const ACTIVATION_TOKEN_META = 'rbn_activation_token';
	const ACTIVATION_SENT_META  = 'rbn_activation_sent_at';

	/**
	 * How long an activation link stays valid after being (re)sent. Chosen
	 * to comfortably outlast a slow inbox delivery or a "I'll do this
	 * later" delay, without leaving an old link usable indefinitely.
	 */
	const ACTIVATION_TTL = 48 * HOUR_IN_SECONDS;

	public static function get_status( $user_id ) {
		$status = get_user_meta( $user_id, self::META_KEY, true );
		return $status ? $status : self::STATUS_APPROVED;
	}

	public static function mark_pending( $user_id ) {
		update_user_meta( $user_id, self::META_KEY, self::STATUS_PENDING );
	}

	/**
	 * Generates a fresh activation token for a pending account, emails the
	 * activation link to the member, and notifies the configured
	 * notification address that a new member has registered (informational
	 * only now - see RBN_Emails::admin_new_registration_email()). Called on
	 * registration (RBN_Auth_Forms::handle_register()) and again whenever
	 * an admin resends the activation email from the Approvals screen.
	 */
	public static function send_activation_email( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		$token = wp_generate_password( 32, false );

		update_user_meta( $user_id, self::ACTIVATION_TOKEN_META, wp_hash( $token ) );
		update_user_meta( $user_id, self::ACTIVATION_SENT_META, time() );

		$sent = RBN_Emails::activation_email( $user, self::activation_url( $user_id, $token ) );

		RBN_Emails::admin_new_registration_email( $user, admin_url( 'users.php' ) );

		return $sent;
	}

	private static function activation_url( $user_id, $token ) {
		return add_query_arg(
			array(
				'rbn_activate' => absint( $user_id ),
				'rbn_key'      => $token,
			),
			RBN_Access_Control::login_url()
		);
	}

	/**
	 * True if $token matches the activation token on file for $user_id and
	 * hasn't expired. hash_equals() (rather than ===) avoids a timing
	 * side-channel, the same reasoning RBN_Auth_Forms::looks_like_bot()
	 * already applies to its own signed timestamp.
	 */
	private static function activation_token_is_valid( $user_id, $token ) {
		if ( '' === (string) $token ) {
			return false;
		}

		$stored = get_user_meta( $user_id, self::ACTIVATION_TOKEN_META, true );

		if ( ! $stored || ! hash_equals( $stored, wp_hash( $token ) ) ) {
			return false;
		}

		$sent_at = (int) get_user_meta( $user_id, self::ACTIVATION_SENT_META, true );

		return $sent_at && ( time() - $sent_at ) < self::ACTIVATION_TTL;
	}

	/**
	 * Approves the account, clears any activation token on file (a used or
	 * superseded link must never work again), joins the member to the
	 * community matching the origin/current country pair they registered
	 * with (RBN_Auth_Forms::handle_register() already guarantees that
	 * community exists via get_or_create_for_pair()), and sends the welcome
	 * email - the account's one "you're in" moment, whether it got here via
	 * the member clicking their activation link or an admin force-activating
	 * from wp-admin. RBN_Community_Memberships::join() is idempotent, so
	 * this is safe to run again on an already-joined member (e.g. an admin
	 * re-activating an already-approved account).
	 */
	public static function activate_account( $user_id ) {
		update_user_meta( $user_id, self::META_KEY, self::STATUS_APPROVED );
		delete_user_meta( $user_id, self::ACTIVATION_TOKEN_META );
		delete_user_meta( $user_id, self::ACTIVATION_SENT_META );

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$origin_id  = RBN_Countries::get_origin_country_id( $user_id );
		$current_id = RBN_Countries::get_current_country_id( $user_id );
		$community  = ( $origin_id && $current_id ) ? RBN_Communities::get_for_pair( $origin_id, $current_id ) : null;

		if ( $community ) {
			RBN_Community_Memberships::join( $user_id, $community->id );
		}

		RBN_Emails::welcome_email( $user, $community, RBN_Access_Control::login_url() );
	}

	/**
	 * Handles a visitor following an activation link from
	 * send_activation_email() above - runs on every front-end request, so it
	 * bails immediately unless both expected query args are present.
	 */
	public static function maybe_handle_activation() {
		if ( ! isset( $_GET['rbn_activate'], $_GET['rbn_key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the activation token itself (verified below) is the credential here, the same way a password-reset key is; there is no separate nonce to check for a link a user follows from their inbox, not a form they submit.
			return;
		}

		$user_id = absint( $_GET['rbn_activate'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
		$token   = sanitize_text_field( wp_unslash( $_GET['rbn_key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.

		$redirect = RBN_Access_Control::login_url();

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			self::redirect_with_notice( $redirect, 'login_activation_invalid' );
		}

		if ( self::STATUS_REJECTED === self::get_status( $user_id ) ) {
			self::redirect_with_notice( $redirect, 'login_activation_invalid' );
		}

		if ( self::STATUS_APPROVED === self::get_status( $user_id ) ) {
			self::redirect_with_notice( $redirect, 'login_activation_already_done' );
		}

		if ( ! self::activation_token_is_valid( $user_id, $token ) ) {
			self::redirect_with_notice( $redirect, 'login_activation_invalid' );
		}

		self::activate_account( $user_id );

		self::redirect_with_notice( $redirect, 'login_activation_success' );
	}

	private static function redirect_with_notice( $url, $code ) {
		wp_safe_redirect( add_query_arg( 'rbn_notice', $code, $url ) );
		exit;
	}

	public static function notify_member_of_rejection( $user_id ) {
		$user = get_userdata( $user_id );

		if ( $user ) {
			RBN_Emails::member_rejected_email( $user );
		}
	}

	/**
	 * Blocks wp_signon() from succeeding for a pending/rejected account,
	 * before any auth cookie is ever set.
	 */
	public static function block_pending_login( $user, $password ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$status = self::get_status( $user->ID );

		if ( self::STATUS_PENDING === $status ) {
			return new WP_Error( 'rbn_account_pending', __( 'Please check your email and click the activation link to activate your account.', 'roomworks-business-networking' ) );
		}

		if ( self::STATUS_REJECTED === $status ) {
			return new WP_Error( 'rbn_account_rejected', __( 'Your registration was not approved.', 'roomworks-business-networking' ) );
		}

		return $user;
	}

	public static function add_column( $columns ) {
		$columns['rbn_account_status'] = __( 'Account Status', 'roomworks-business-networking' );
		return $columns;
	}

	public static function render_column( $value, $column_name, $user_id ) {
		if ( 'rbn_account_status' !== $column_name ) {
			return $value;
		}

		$labels = array(
			self::STATUS_PENDING  => __( 'Pending activation', 'roomworks-business-networking' ),
			self::STATUS_APPROVED => __( 'Active', 'roomworks-business-networking' ),
			self::STATUS_REJECTED => __( 'Rejected', 'roomworks-business-networking' ),
		);

		$status = self::get_status( $user_id );

		return esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $status );
	}

	public static function add_row_actions( $actions, $user ) {
		if ( ! current_user_can( 'edit_users' ) ) {
			return $actions;
		}

		$status = self::get_status( $user->ID );

		if ( self::STATUS_PENDING === $status || self::STATUS_REJECTED === $status ) {
			$approve_url = wp_nonce_url(
				add_query_arg(
					array(
						'action'  => 'rbn_approve_member',
						'user_id' => $user->ID,
					),
					admin_url( 'users.php' )
				),
				'rbn_approve_member_' . $user->ID
			);

			$actions['rbn_approve'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $approve_url ),
				esc_html__( 'Activate now', 'roomworks-business-networking' )
			);
		}

		if ( self::STATUS_PENDING === $status ) {
			$resend_url = wp_nonce_url(
				add_query_arg(
					array(
						'action'  => 'rbn_resend_activation',
						'user_id' => $user->ID,
					),
					admin_url( 'users.php' )
				),
				'rbn_resend_activation_' . $user->ID
			);

			$actions['rbn_resend_activation'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $resend_url ),
				esc_html__( 'Resend activation email', 'roomworks-business-networking' )
			);

			$reject_url = wp_nonce_url(
				add_query_arg(
					array(
						'action'  => 'rbn_reject_member',
						'user_id' => $user->ID,
					),
					admin_url( 'users.php' )
				),
				'rbn_reject_member_' . $user->ID
			);

			$actions['rbn_reject'] = sprintf(
				'<a href="%s" class="submitdelete">%s</a>',
				esc_url( $reject_url ),
				esc_html__( 'Reject', 'roomworks-business-networking' )
			);
		}

		return $actions;
	}

	public static function handle_approve() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'roomworks-business-networking' ) );
		}

		$user_id = self::verified_user_id_from_request( 'rbn_approve_member_' );

		self::activate_account( $user_id );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'users.php' ) );
		exit;
	}

	public static function handle_reject() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'roomworks-business-networking' ) );
		}

		$user_id = self::verified_user_id_from_request( 'rbn_reject_member_' );

		update_user_meta( $user_id, self::META_KEY, self::STATUS_REJECTED );
		delete_user_meta( $user_id, self::ACTIVATION_TOKEN_META );
		delete_user_meta( $user_id, self::ACTIVATION_SENT_META );
		self::notify_member_of_rejection( $user_id );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'users.php' ) );
		exit;
	}

	public static function handle_resend_activation() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'roomworks-business-networking' ) );
		}

		$user_id = self::verified_user_id_from_request( 'rbn_resend_activation_' );

		if ( self::STATUS_PENDING === self::get_status( $user_id ) ) {
			self::send_activation_email( $user_id );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'users.php' ) );
		exit;
	}

	/**
	 * Shared nonce/user_id validation for the three admin_action_* handlers
	 * above - the nonce action prefix is the only thing that differs
	 * between them.
	 */
	private static function verified_user_id_from_request( $nonce_action_prefix ) {
		$user_id      = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$nonce_action = $nonce_action_prefix . $user_id;
		$nonce        = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $user_id || ! $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_die( esc_html__( 'Invalid request.', 'roomworks-business-networking' ) );
		}

		return $user_id;
	}
}
