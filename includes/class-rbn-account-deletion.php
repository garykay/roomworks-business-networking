<?php
/**
 * Self-service account deletion. A member can only ever request deletion of
 * their own account - never delete it outright (see
 * RBN_Account_Deletion_Forms) - which schedules the actual deletion via
 * WP-Cron after RBN_Settings::deletion_grace_hours(), giving them a window
 * to change their mind by cancelling; an admin can also cancel a pending
 * request from the Approvals screen (see RBN_Approvals). Administrators are
 * emailed when a request is made or cancelled; the member is emailed once
 * deletion actually completes, since by then there's no account left to
 * show them a notice on.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Account_Deletion {

	const META_KEY        = 'rbn_deletion_requested_at';
	const META_KEY_GRACE   = 'rbn_deletion_grace_period';
	const CRON_HOOK        = 'rbn_process_account_deletion';

	public static function is_requested( $user_id ) {
		return (bool) get_user_meta( $user_id, self::META_KEY, true );
	}

	public static function requested_at( $user_id ) {
		return (int) get_user_meta( $user_id, self::META_KEY, true );
	}

	/**
	 * The grace period actually used for this request, in seconds - read
	 * from a snapshot taken when the request was made, not from the live
	 * setting. Otherwise, changing RBN_Settings::deletion_grace_hours()
	 * while a request is already pending would desync this from the
	 * timestamp already passed to wp_schedule_single_event(), and could
	 * make process_deletion()'s elapsed-time guard skip a real deletion.
	 */
	private static function grace_period( $user_id ) {
		$stored = (int) get_user_meta( $user_id, self::META_KEY_GRACE, true );
		return $stored ? $stored : RBN_Settings::deletion_grace_hours() * HOUR_IN_SECONDS;
	}

	public static function scheduled_for( $user_id ) {
		$requested_at = self::requested_at( $user_id );
		return $requested_at ? $requested_at + self::grace_period( $user_id ) : 0;
	}

	/**
	 * Records the request and schedules the actual deletion after the
	 * currently configured grace period. A no-op if a request is already
	 * pending, so a duplicate submit can't schedule a second cron event for
	 * the same account.
	 */
	public static function request( $user_id ) {
		if ( self::is_requested( $user_id ) ) {
			return;
		}

		$requested_at = time();
		$grace_period = RBN_Settings::deletion_grace_hours() * HOUR_IN_SECONDS;

		update_user_meta( $user_id, self::META_KEY, $requested_at );
		update_user_meta( $user_id, self::META_KEY_GRACE, $grace_period );
		wp_schedule_single_event( $requested_at + $grace_period, self::CRON_HOOK, array( $user_id ) );

		self::notify_admin( $user_id, 'requested' );
	}

	/**
	 * Cancels a pending request. Called both when a member cancels their own
	 * request (RBN_Account_Deletion_Forms) and when an admin cancels one on
	 * a member's behalf (RBN_Approvals) - the two share this one code path
	 * so a cancellation always clears the cron event and notifies admin the
	 * same way regardless of who triggered it.
	 */
	public static function cancel( $user_id ) {
		if ( ! self::is_requested( $user_id ) ) {
			return;
		}

		delete_user_meta( $user_id, self::META_KEY );
		delete_user_meta( $user_id, self::META_KEY_GRACE );
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $user_id ) );

		self::notify_admin( $user_id, 'cancelled' );
	}

	/**
	 * The scheduled WP-Cron callback. Re-checks the request is still
	 * pending and the grace period has actually elapsed before deleting -
	 * belt-and-braces against a stray cron event outliving a cancellation
	 * (e.g. one that had already started running when cancel() fired).
	 */
	public static function process_deletion( $user_id ) {
		if ( ! self::is_requested( $user_id ) ) {
			return;
		}

		if ( ( time() - self::requested_at( $user_id ) ) < self::grace_period( $user_id ) - MINUTE_IN_SECONDS ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$email        = $user->user_email;
		$display_name = $user->display_name;

		if ( is_multisite() ) {
			require_once ABSPATH . 'wp-admin/includes/ms.php';
			$deleted = wpmu_delete_user( $user_id );
		} else {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			// No reassign target: this also removes their business listing,
			// which is the expected outcome of deleting the whole account.
			$deleted = wp_delete_user( $user_id );
		}

		if ( ! $deleted ) {
			// Leave the request meta and pending state as-is rather than
			// silently telling the member it's done - an admin needs to
			// investigate why core's own deletion call refused, and the
			// member should still see "Cancel Deletion Request" rather than
			// a false sense that nothing is pending.
			self::notify_admin_of_failure( $user_id, $email, $display_name );
			return;
		}

		// Only clean up our own tracking-table rows once the account itself
		// is confirmed gone, so a failed deletion doesn't lose data that a
		// retry would still need.
		self::delete_related_data( $user_id );

		self::notify_member_of_completion( $email, $display_name );
	}

	/**
	 * Cleans up rows in the plugin's own custom tables - wp_delete_user()
	 * only knows about core user data, posts and comments, not these.
	 * $wpdb->delete() already parameterises its WHERE clause internally, so
	 * this is injection-safe despite being a "direct" query; there's
	 * nothing worth caching about a one-off delete-on-account-deletion.
	 */
	private static function delete_related_data( $user_id ) {
		global $wpdb;

		$wpdb->delete( RBN_Schema::follows_table(), array( 'follower_id' => $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( RBN_Schema::follows_table(), array( 'followed_id' => $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( RBN_Schema::member_needs_table(), array( 'user_id' => $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// The other side - other members' follows of THIS user's businesses
		// - is cleaned up separately, by RBN_Business_Follows::
		// cleanup_on_business_deleted() hooked to before_delete_post, which
		// fires for each business wp_delete_user() removes below.
		RBN_Business_Follows::delete_all_for_user( $user_id );
	}

	private static function notify_admin( $user_id, $event ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		if ( 'requested' === $event ) {
			$subject = sprintf(
				/* translators: %s: member's display name. */
				__( 'Account deletion requested: %s', 'roomworks-business-networking' ),
				$user->display_name
			);
			$message = sprintf(
				/* translators: 1: display name, 2: email address, 3: scheduled deletion date/time, 4: admin Users screen URL. */
				__( "%1\$s (%2\$s) has requested their account be deleted.\n\nUnless they cancel the request, this will happen automatically on %3\$s.\n\n%4\$s", 'roomworks-business-networking' ),
				$user->display_name,
				$user->user_email,
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), self::scheduled_for( $user_id ) ),
				admin_url( 'edit.php?post_type=' . RBN_Post_Type_Business::POST_TYPE . '&page=' . RBN_Approvals::PAGE_SLUG )
			);
		} else {
			$subject = sprintf(
				/* translators: %s: member's display name. */
				__( 'Account deletion request cancelled: %s', 'roomworks-business-networking' ),
				$user->display_name
			);
			$message = sprintf(
				/* translators: 1: display name, 2: email address. */
				__( '%1$s (%2$s) has cancelled their account deletion request. No action is needed.', 'roomworks-business-networking' ),
				$user->display_name,
				$user->user_email
			);
		}

		wp_mail( RBN_Settings::notification_email(), $subject, $message );
	}

	/**
	 * Tells the admin that core's own wp_delete_user()/wpmu_delete_user()
	 * call returned false instead of assuming - and telling the member -
	 * that deletion completed. The request is deliberately left pending
	 * (see process_deletion()) so this can be investigated and retried
	 * rather than silently losing the account.
	 */
	private static function notify_admin_of_failure( $user_id, $email, $display_name ) {
		$subject = sprintf(
			/* translators: %s: member's display name. */
			__( 'Account deletion FAILED: %s', 'roomworks-business-networking' ),
			$display_name
		);
		$message = sprintf(
			/* translators: 1: display name, 2: email address, 3: user ID. */
			__( "The scheduled account deletion for %1\$s (%2\$s, user ID %3\$d) did not complete - WordPress refused the delete request.\n\nThe account and business listing are still active and the deletion request is still marked as pending. Please investigate (e.g. another plugin blocking user deletion) and delete the account manually if needed.", 'roomworks-business-networking' ),
			$display_name,
			$email,
			$user_id
		);

		wp_mail( RBN_Settings::notification_email(), $subject, $message );
	}

	private static function notify_member_of_completion( $email, $display_name ) {
		$subject = __( 'Your account has been deleted', 'roomworks-business-networking' );
		$message = sprintf(
			/* translators: %s: member's display name. */
			__( "Hi %s,\n\nAs requested, your account and business listing have been permanently deleted.\n\nIf you didn't request this, please contact us straight away.", 'roomworks-business-networking' ),
			$display_name
		);

		wp_mail( $email, $subject, $message );
	}
}
