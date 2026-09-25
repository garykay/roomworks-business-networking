<?php
/**
 * Handles a logged-in member creating a new notice board request, or
 * updating one of their own. Modeled closely on RBN_Business_Forms (same
 * "never trust a client-supplied ID" re-verification via
 * RBN_Job_Repository), with two deliberate differences:
 *
 * - Requests auto-publish (RBN_Capabilities grants members
 *   publish_rbn_jobs, unlike businesses) - there's no admin review step.
 * - The community field is a checkbox group, not a required single select:
 *   a request can be posted to several eligible communities at once, and
 *   leaving every box unchecked silently defaults to the member's
 *   oldest-joined eligible community rather than being rejected as invalid
 *   - see RBN_Community_Memberships::get_communities_for_user_in_country().
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Job_Forms {

	const ACTION = 'rbn_save_job';

	public static function handle_request() {
		if ( empty( $_POST['rbn_form_action'] ) || self::ACTION !== $_POST['rbn_form_action'] || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- strict comparisons against literals; the actual nonce is verified just below before anything happens.
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$nonce = isset( $_POST['rbn_job_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_job_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			self::redirect_with_notice( 'job_invalid_request' );
		}

		$user_id = get_current_user_id();
		$job_id  = isset( $_POST['rbn_job_id'] ) ? absint( $_POST['rbn_job_id'] ) : 0;
		$job     = $job_id ? RBN_Job_Repository::get_by_id_for_user( $job_id, $user_id ) : null;

		// A submitted ID that doesn't resolve to one of the user's own job
		// listings is always rejected outright - see RBN_Business_Forms's
		// identical check for why this can't silently fall back to "create
		// a new one" instead.
		if ( $job_id && ! $job ) {
			self::redirect_with_notice( 'job_not_permitted' );
		}

		$current_country_id = RBN_Countries::get_current_country_id( $user_id );
		$eligible_communities = RBN_Community_Memberships::get_communities_for_user_in_country( $user_id, $current_country_id );

		// A new listing always needs at least one eligible community to post
		// into. An existing listing can still be edited even if the member
		// has since moved country and has no eligible community left - its
		// community assignment is simply left untouched (community_ids stays
		// null below), mirroring how RBN_Business_Forms leaves an existing
		// business's community alone when the field isn't rendered.
		if ( empty( $eligible_communities ) && ! $job ) {
			self::redirect_with_notice( 'job_invalid_community' );
		}

		$community_ids = null;

		if ( ! empty( $eligible_communities ) ) {
			$eligible_ids   = wp_list_pluck( $eligible_communities, 'id' );
			$submitted_ids  = isset( $_POST['rbn_communities'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['rbn_communities'] ) ) : array();
			$community_ids  = array_values( array_intersect( $eligible_ids, $submitted_ids ) );

			// Nothing valid was checked - default to the oldest-joined
			// eligible community rather than rejecting the submission (the
			// agreed "leave it empty and we'll pick your main community"
			// behaviour), not just for a first-time listing but any time the
			// member leaves every box unchecked.
			if ( empty( $community_ids ) ) {
				$community_ids = array( (int) $eligible_communities[0]->id );
			}
		}

		$title         = isset( $_POST['rbn_job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_job_title'] ) ) : '';
		$description   = isset( $_POST['rbn_job_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rbn_job_description'] ) ) : '';
		$category_id   = isset( $_POST['rbn_job_category'] ) ? absint( $_POST['rbn_job_category'] ) : 0;
		$urgency       = isset( $_POST['rbn_urgency'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_urgency'] ) ) : '';
		$town_city     = isset( $_POST['rbn_town_city'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_town_city'] ) ) : '';
		$county_region = isset( $_POST['rbn_county_region'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_county_region'] ) ) : '';
		$budget        = isset( $_POST['rbn_budget'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_budget'] ) ) : '';
		$closing_date  = isset( $_POST['rbn_closing_date'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_closing_date'] ) ) : '';
		$hide_phone    = ! empty( $_POST['rbn_hide_phone'] );

		// Budget and closing date are the only optional fields - see
		// RBN_Templates::job_form(). Category reuses the same Business Type
		// taxonomy businesses use - see RBN_Taxonomy_Business_Category's
		// class docblock for why.
		if (
			'' === $title
			|| '' === $description
			|| ! $category_id || ! term_exists( $category_id, RBN_Taxonomy_Business_Category::TAXONOMY )
			|| ! array_key_exists( $urgency, RBN_Post_Type_Job::URGENCY_OPTIONS )
			|| '' === $town_city
			|| '' === $county_region
			|| ( '' !== $closing_date && ! self::is_valid_date( $closing_date ) )
		) {
			self::redirect_with_notice( 'job_missing_fields' );
		}

		if ( $job ) {
			if ( ! current_user_can( 'edit_post', $job->ID ) ) {
				self::redirect_with_notice( 'job_not_permitted' );
			}

			$post_id = wp_update_post(
				array(
					'ID'           => $job->ID,
					'post_title'   => $title,
					'post_content' => $description,
				),
				true
			);
		} else {
			if ( ! current_user_can( 'edit_rbn_jobs' ) ) {
				self::redirect_with_notice( 'job_not_permitted' );
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => RBN_Post_Type_Job::POST_TYPE,
					'post_title'   => $title,
					'post_content' => $description,
					// Members hold publish_rbn_jobs (RBN_Capabilities) -
					// unlike a new business, a new job listing goes live
					// immediately, no admin review step.
					'post_status'  => 'publish',
					'post_author'  => $user_id,
				),
				true
			);
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			self::redirect_with_notice( 'job_not_permitted' );
		}

		wp_set_object_terms( $post_id, array( $category_id ), RBN_Taxonomy_Business_Category::TAXONOMY, false );

		update_post_meta( $post_id, 'rbn_urgency', $urgency );
		update_post_meta( $post_id, 'rbn_town_city', $town_city );
		update_post_meta( $post_id, 'rbn_county_region', $county_region );
		update_post_meta( $post_id, 'rbn_budget', $budget );
		update_post_meta( $post_id, 'rbn_closing_date', $closing_date );
		update_post_meta( $post_id, 'rbn_hide_phone', $hide_phone );

		if ( null !== $community_ids ) {
			delete_post_meta( $post_id, 'rbn_community_id' );

			foreach ( $community_ids as $community_id ) {
				add_post_meta( $post_id, 'rbn_community_id', $community_id );
			}
		}

		// New listing only - members of the target community/communities are
		// notified once, on first publish; editing an existing request never
		// re-notifies them. See RBN_Job_Notifications's class docblock for
		// why this is offloaded to WP-Cron rather than sent here inline.
		if ( ! $job ) {
			RBN_Job_Notifications::schedule_new_request_notification( $post_id );
		}

		// Every save (create or edit) re-evaluates the "closing soon"
		// reminder to the poster, since editing the closing date must move
		// it too - see RBN_Job_Notifications::reschedule_expiring_reminder().
		RBN_Job_Notifications::reschedule_expiring_reminder( $post_id );

		self::redirect_with_notice( $job ? 'job_updated' : 'job_saved' );
	}

	private static function is_valid_date( $date ) {
		$parsed = DateTime::createFromFormat( 'Y-m-d', $date );
		return $parsed && $parsed->format( 'Y-m-d' ) === $date;
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
