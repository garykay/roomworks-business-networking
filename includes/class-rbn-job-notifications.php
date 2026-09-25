<?php
/**
 * Email notifications around a notice board request's lifecycle: alerting a
 * request's target community/communities when it's first posted, and
 * reminding the request's own poster shortly before it stops showing on the
 * board (see RBN_Job_Query::not_closed_clause()). Both are offloaded to
 * WP-Cron (same pattern as RBN_Account_Deletion) rather than sent inline
 * from RBN_Job_Forms, so a community with many members - or a slow mail
 * transport - can never delay the poster's own "request posted" redirect.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Job_Notifications {

	const NEW_REQUEST_HOOK   = 'rbn_notify_new_request';
	const EXPIRING_SOON_HOOK = 'rbn_notify_request_expiring_soon';

	/**
	 * Schedules the "new request" notification for a brand new request only
	 * - never for an edit to an existing one. Fired a moment later via
	 * WP-Cron rather than inline; see the class docblock for why.
	 */
	public static function schedule_new_request_notification( $job_id ) {
		wp_schedule_single_event( time(), self::NEW_REQUEST_HOOK, array( absint( $job_id ) ) );
	}

	/**
	 * (Re)schedules the "closing soon" reminder to the request's own poster,
	 * based on its rbn_closing_date meta - called on every save (create or
	 * edit), not just creation, since editing the closing date must move the
	 * reminder too. Any previously scheduled reminder for this request is
	 * always cleared first, so editing (or clearing) the closing date can
	 * never leave a stale event behind pointing at the old date.
	 */
	public static function reschedule_expiring_reminder( $job_id ) {
		$job_id = absint( $job_id );

		self::cancel_expiring_reminder( $job_id );

		$closing_date = get_post_meta( $job_id, 'rbn_closing_date', true );

		if ( ! $closing_date ) {
			return;
		}

		$closing = DateTime::createFromFormat( 'Y-m-d', $closing_date, wp_timezone() );

		if ( ! $closing ) {
			return;
		}

		$closing->setTime( 0, 0, 0 );

		// Reminder fires at the start of the day *before* the closing date -
		// a full 48h before RBN_Job_Query::not_closed_clause() actually hides
		// the request from the board (which happens the day after closing).
		$reminder_at = $closing->getTimestamp() - DAY_IN_SECONDS;

		// A closing date that's already today/in the past (or under a day
		// away) has no meaningful "24 hours before" moment left - skip
		// rather than firing an immediate/backdated reminder.
		if ( $reminder_at <= time() ) {
			return;
		}

		wp_schedule_single_event( $reminder_at, self::EXPIRING_SOON_HOOK, array( $job_id ) );
	}

	public static function cancel_expiring_reminder( $job_id ) {
		wp_clear_scheduled_hook( self::EXPIRING_SOON_HOOK, array( absint( $job_id ) ) );
	}

	/**
	 * Cancels any pending notification/reminder for a request that's about
	 * to be permanently deleted, so a stray cron event can't fire against a
	 * post ID that no longer exists. Hooked to before_delete_post for every
	 * post type - harmless no-op for anything that isn't an rbn_job, since
	 * no event would ever have been scheduled against that ID on these hooks.
	 */
	public static function cancel_all( $job_id ) {
		$job_id = absint( $job_id );

		wp_clear_scheduled_hook( self::NEW_REQUEST_HOOK, array( $job_id ) );
		self::cancel_expiring_reminder( $job_id );
	}

	/**
	 * WP-Cron callback: emails every active member of the request's target
	 * community/communities, except the poster themselves. One email per
	 * recipient (never a single email addressed to everyone), so no
	 * member's address is ever exposed to another.
	 */
	public static function send_new_request_notification( $job_id ) {
		$job = get_post( $job_id );

		if ( ! $job || RBN_Post_Type_Job::POST_TYPE !== $job->post_type || 'publish' !== $job->post_status ) {
			return;
		}

		$community_ids = array_unique( array_map( 'absint', (array) get_post_meta( $job->ID, 'rbn_community_id' ) ) );
		$member_ids    = array();

		foreach ( $community_ids as $community_id ) {
			$member_ids = array_merge( $member_ids, RBN_Community_Memberships::get_member_ids_for_community( $community_id ) );
		}

		$member_ids = array_diff( array_unique( $member_ids ), array( (int) $job->post_author ) );

		foreach ( $member_ids as $member_id ) {
			$member = get_userdata( $member_id );

			if ( $member ) {
				RBN_Emails::new_request_email( $member, $job );
			}
		}
	}

	/**
	 * WP-Cron callback: emails the request's own poster that it's about to
	 * stop showing on the board.
	 */
	public static function send_expiring_soon_reminder( $job_id ) {
		$job = get_post( $job_id );

		if ( ! $job || RBN_Post_Type_Job::POST_TYPE !== $job->post_type || 'publish' !== $job->post_status ) {
			return;
		}

		$poster = get_userdata( $job->post_author );

		if ( ! $poster ) {
			return;
		}

		RBN_Emails::request_expiring_soon_email( $poster, $job );
	}
}
