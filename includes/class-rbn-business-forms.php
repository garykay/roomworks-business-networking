<?php
/**
 * Handles a logged-in member creating a new business, or updating one of
 * their own. A member may own more than one business, so which business is
 * being edited (if any) comes from a submitted rbn_business_id - but that
 * ID is never trusted on its own: RBN_Business_Repository::get_by_id_for_user()
 * re-verifies it actually belongs to the logged-in user before anything is
 * read from or written to it, exactly the same "never trust a client-
 * supplied ID" rule this class always followed when there was only ever
 * one business to look up.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Business_Forms {

	const ACTION = 'rbn_save_business';

	public static function handle_request() {
		if ( empty( $_POST['rbn_form_action'] ) || self::ACTION !== $_POST['rbn_form_action'] || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- strict comparisons against literals; the actual nonce is verified just below before anything happens.
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$nonce = isset( $_POST['rbn_business_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_business_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			self::redirect_with_notice( 'business_invalid_request' );
		}

		$user_id     = get_current_user_id();
		$business_id = isset( $_POST['rbn_business_id'] ) ? absint( $_POST['rbn_business_id'] ) : 0;
		$business    = $business_id ? RBN_Business_Repository::get_by_id_for_user( $business_id, $user_id ) : null;

		// A submitted ID that doesn't resolve to one of the user's own
		// businesses is always rejected outright, rather than silently
		// falling back to "create a new one" - that would let a stale or
		// tampered ID (e.g. another member's business) quietly succeed as
		// something the submitter never intended.
		if ( $business_id && ! $business ) {
			self::redirect_with_notice( 'business_not_permitted' );
		}

		$community_submitted = isset( $_POST['rbn_community_id'] );
		$community_id        = $community_submitted ? absint( $_POST['rbn_community_id'] ) : 0;

		// A new business always needs a community. An existing one only
		// needs to validate a submitted value - the form omits the field
		// entirely (see RBN_Templates::business_community_field()) when the
		// owner has no communities to choose from, in which case the
		// existing value (however it got there) is left untouched.
		$community_id_is_valid = $community_submitted
			? ( $community_id && ( RBN_Community_Memberships::is_member( $user_id, $community_id ) || ( $business && $community_id === absint( get_post_meta( $business->ID, 'rbn_community_id', true ) ) ) ) )
			: (bool) $business;

		if ( ! $community_id_is_valid ) {
			self::redirect_with_notice( 'business_invalid_community' );
		}

		$name          = isset( $_POST['rbn_business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_business_name'] ) ) : '';
		$description   = isset( $_POST['rbn_business_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rbn_business_description'] ) ) : '';
		$category_id   = isset( $_POST['rbn_business_category'] ) ? absint( $_POST['rbn_business_category'] ) : 0;
		$service_ids   = isset( $_POST['rbn_services'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['rbn_services'] ) ) : array();
		$town_city     = isset( $_POST['rbn_town_city'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_town_city'] ) ) : '';
		$county_region = isset( $_POST['rbn_county_region'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_county_region'] ) ) : '';
		$postcode      = isset( $_POST['rbn_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_postcode'] ) ) : '';
		$service_area  = isset( $_POST['rbn_service_area'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_service_area'] ) ) : '';
		$website       = isset( $_POST['rbn_website'] ) ? sanitize_url( wp_unslash( $_POST['rbn_website'] ) ) : '';
		$phone         = isset( $_POST['rbn_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_phone'] ) ) : '';
		$contact_email = isset( $_POST['rbn_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['rbn_contact_email'] ) ) : '';

		$valid_service_ids = array_values(
			array_filter(
				array_unique( $service_ids ),
				function ( $service_id ) {
					return $service_id && term_exists( $service_id, RBN_Taxonomy_Service::TAXONOMY );
				}
			)
		);

		// Every field is mandatory except Website - see RBN_Templates::business_form().
		if (
			'' === $name
			|| '' === $description
			|| ! $category_id || ! term_exists( $category_id, RBN_Taxonomy_Business_Category::TAXONOMY )
			|| empty( $valid_service_ids )
			|| '' === $town_city
			|| '' === $county_region
			|| '' === $postcode
			|| '' === $service_area
			|| '' === $phone
			|| '' === $contact_email || ! is_email( $contact_email )
		) {
			self::redirect_with_notice( 'business_missing_fields' );
		}

		if ( ! self::logo_upload_is_valid() ) {
			self::redirect_with_notice( 'business_logo_invalid' );
		}

		if ( $business ) {
			if ( ! current_user_can( 'edit_post', $business->ID ) ) {
				self::redirect_with_notice( 'business_not_permitted' );
			}

			$post_id = wp_update_post(
				array(
					'ID'           => $business->ID,
					'post_title'   => $name,
					'post_content' => $description,
				),
				true
			);
		} else {
			if ( ! current_user_can( 'edit_rbn_businesses' ) ) {
				self::redirect_with_notice( 'business_not_permitted' );
			}

			// Always created as pending: this is the member self-service
			// path, which must always go through admin review.
			$post_id = wp_insert_post(
				array(
					'post_type'    => RBN_Post_Type_Business::POST_TYPE,
					'post_title'   => $name,
					'post_content' => $description,
					'post_status'  => 'pending',
					'post_author'  => $user_id,
				),
				true
			);
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			self::redirect_with_notice( 'business_not_permitted' );
		}

		// $category_id and $valid_service_ids were already validated above.
		wp_set_object_terms( $post_id, array( $category_id ), RBN_Taxonomy_Business_Category::TAXONOMY, false );
		wp_set_object_terms( $post_id, $valid_service_ids, RBN_Taxonomy_Service::TAXONOMY, false );

		if ( $community_submitted ) {
			update_post_meta( $post_id, 'rbn_community_id', $community_id );
		}

		update_post_meta( $post_id, 'rbn_town_city', $town_city );
		update_post_meta( $post_id, 'rbn_county_region', $county_region );
		update_post_meta( $post_id, 'rbn_postcode', $postcode );
		update_post_meta( $post_id, 'rbn_service_area', $service_area );
		update_post_meta( $post_id, 'rbn_website', $website );
		update_post_meta( $post_id, 'rbn_phone', $phone );
		update_post_meta( $post_id, 'rbn_contact_email', $contact_email );

		self::handle_logo_upload( $post_id );

		self::redirect_with_notice( $business ? 'business_updated' : 'business_saved' );
	}

	/**
	 * Whether a file was actually chosen in the logo field - distinct from
	 * an upload error, since an empty file input is not one.
	 */
	private static function logo_was_submitted() {
		return isset( $_FILES['rbn_business_logo']['error'] ) && UPLOAD_ERR_NO_FILE !== $_FILES['rbn_business_logo']['error']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified by handle_request() before this is ever called.
	}

	/**
	 * Pre-flight check run before the business post is touched at all, so
	 * an invalid upload is rejected the same way a missing required field
	 * is - nothing partially saved. The logo itself is optional: no file
	 * chosen is valid, not an error.
	 */
	private static function logo_upload_is_valid() {
		if ( ! self::logo_was_submitted() ) {
			return true;
		}

		$file = isset( $_FILES['rbn_business_logo'] ) ? $_FILES['rbn_business_logo'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- nonce verified by handle_request() before this is ever called; 'error'/'size' are ints PHP sets itself, 'tmp_name'/'name' are only ever passed to wp_check_filetype_and_ext()/media_handle_upload() below, which sanitize internally - never output or queried raw.

		if ( UPLOAD_ERR_OK !== $file['error'] || $file['size'] > 5 * MB_IN_BYTES ) {
			return false;
		}

		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		$filetype      = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );

		return ! empty( $filetype['type'] ) && in_array( $filetype['type'], $allowed_types, true );
	}

	/**
	 * Uploads and attaches a new logo if one was submitted (already
	 * confirmed valid by logo_upload_is_valid() before the post was ever
	 * saved), otherwise removes the current one if requested. Runs after
	 * the business post is saved, since the attachment needs a real post
	 * ID as its parent.
	 */
	private static function handle_logo_upload( $post_id ) {
		if ( self::logo_was_submitted() ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$attachment_id = media_handle_upload( 'rbn_business_logo', $post_id );

			if ( ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}

			return;
		}

		if ( ! empty( $_POST['rbn_remove_logo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified by handle_request() before this is ever called; value is only ever used as a boolean flag.
			delete_post_thumbnail( $post_id );
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
}
