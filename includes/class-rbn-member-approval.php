<?php
/**
 * Member account approval: a newly registered member is "pending" until an
 * administrator approves them, either via the row actions this class adds
 * to the wp-admin Users screen, or from the consolidated RBN_Approvals
 * screen (Businesses > Approvals) - both call the same handle_approve() /
 * handle_reject() below.
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

	public static function get_status( $user_id ) {
		$status = get_user_meta( $user_id, self::META_KEY, true );
		return $status ? $status : self::STATUS_APPROVED;
	}

	public static function mark_pending( $user_id ) {
		update_user_meta( $user_id, self::META_KEY, self::STATUS_PENDING );
	}

	public static function notify_admin_of_registration( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: new member's display name. */
			__( 'New member registration awaiting approval: %s', 'roomworks-business-networking' ),
			$user->display_name
		);

		$message = sprintf(
			/* translators: 1: display name, 2: email address, 3: admin Approvals screen URL. */
			__( "A new member has registered and is awaiting approval.\n\nName: %1\$s\nEmail: %2\$s\n\nReview and approve this member here:\n%3\$s", 'roomworks-business-networking' ),
			$user->display_name,
			$user->user_email,
			admin_url( 'edit.php?post_type=' . RBN_Post_Type_Business::POST_TYPE . '&page=' . RBN_Approvals::PAGE_SLUG )
		);

		wp_mail( RBN_Settings::notification_email(), $subject, $message );
	}

	public static function notify_member_of_decision( $user_id, $approved ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		if ( $approved ) {
			$subject = __( 'Your account has been approved', 'roomworks-business-networking' );
			$message = __( 'Good news - your account has been approved and you can now log in.', 'roomworks-business-networking' );
		} else {
			$subject = __( 'Your account registration', 'roomworks-business-networking' );
			$message = __( "We're sorry, your account registration was not approved.", 'roomworks-business-networking' );
		}

		wp_mail( $user->user_email, $subject, $message );
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
			return new WP_Error( 'rbn_account_pending', __( 'Your account is awaiting admin approval.', 'roomworks-business-networking' ) );
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
			self::STATUS_PENDING  => __( 'Pending', 'roomworks-business-networking' ),
			self::STATUS_APPROVED => __( 'Approved', 'roomworks-business-networking' ),
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
				esc_html__( 'Approve', 'roomworks-business-networking' )
			);
		}

		if ( self::STATUS_PENDING === $status ) {
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
		self::handle_decision( true );
	}

	public static function handle_reject() {
		self::handle_decision( false );
	}

	private static function handle_decision( $approve ) {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'roomworks-business-networking' ) );
		}

		$user_id      = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$nonce_action = ( $approve ? 'rbn_approve_member_' : 'rbn_reject_member_' ) . $user_id;
		$nonce        = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $user_id || ! $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_die( esc_html__( 'Invalid request.', 'roomworks-business-networking' ) );
		}

		update_user_meta( $user_id, self::META_KEY, $approve ? self::STATUS_APPROVED : self::STATUS_REJECTED );
		self::notify_member_of_decision( $user_id, $approve );

		// Row actions for this exist on both the Users screen and the
		// Approvals screen (RBN_Approvals) - send the admin back to
		// whichever one they came from rather than assuming Users.
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'users.php' ) );
		exit;
	}
}
