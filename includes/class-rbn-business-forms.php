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

	const DELETE_ACTION = 'rbn_delete_business';

	const STICKY_TRANSIENT_PREFIX = 'rbn_business_sticky_';

	const LOGO_STASH_TRANSIENT_PREFIX = 'rbn_business_logo_stash_';

	/**
	 * Saves a rejected submission's field values for exactly one page load,
	 * so RBN_Templates::business_form() can redisplay what the member typed
	 * instead of the blank/stale form it would otherwise fall back to -
	 * every other error path here works by redirecting to a fresh GET (see
	 * redirect_with_notice()), which loses $_POST entirely by design. A
	 * transient (rather than, say, a session) because this plugin has no
	 * session handling anywhere else and doesn't want to start for one
	 * form; a short TTL because it's only ever meant to survive the
	 * redirect that follows it immediately, not to linger.
	 *
	 * The Business Logo file itself is never part of $submitted - there is
	 * no way to repopulate a <input type="file"> from the server, so a
	 * member whose logo upload was rejected (or who simply tripped a
	 * different field's validation after choosing one) will need to
	 * reselect it.
	 *
	 * $field_errors is which specific field(s) failed - e.g. array( 'name',
	 * 'contact_email' ) - so RBN_Templates::business_form() can mark just
	 * those with an error state instead of leaving the member to guess which
	 * of a dozen required fields the generic notice text is about.
	 */
	private static function remember_submission( $user_id, array $submitted, array $field_errors ) {
		$submitted['field_errors'] = $field_errors;
		set_transient( self::STICKY_TRANSIENT_PREFIX . $user_id, $submitted, MINUTE_IN_SECONDS );
	}

	/**
	 * Reads back a submission saved by remember_submission(), if any -
	 * consumed immediately (delete on read) so it only ever affects the one
	 * page load right after the failed submit, never a later, unrelated
	 * visit to the same form. $business_id must match the business (or 0,
	 * for "Add a Business") the sticky data was captured for, so a stray
	 * transient never bleeds its values into the wrong form.
	 */
	public static function consume_sticky_submission( $user_id, $business_id ) {
		$key       = self::STICKY_TRANSIENT_PREFIX . $user_id;
		$submitted = get_transient( $key );

		if ( ! $submitted ) {
			return null;
		}

		delete_transient( $key );

		if ( (int) $submitted['business_id'] !== (int) $business_id ) {
			return null;
		}

		return $submitted;
	}

	/**
	 * Keeps a just-validated logo upload past this request when some other
	 * field's validation fails - PHP deletes the original $_FILES tmp_name
	 * the moment the request ends, so without this a member who correctly
	 * picked a logo but, say, left Phone blank would have to reselect it too
	 * (browsers won't let a server prefill a file input either way, but this
	 * at least means resubmitting doesn't require a *file picker* round trip
	 * on top of fixing the one field that actually failed).
	 *
	 * Deliberately a separate, longer-lived transient from
	 * remember_submission()'s: that one is consumed (deleted) the moment the
	 * retry form is rendered, but the logo needs to still be there for the
	 * *next* form submission after that, not just the render in between.
	 * Only ever holds one pending file per user - a second stash (a new
	 * upload, or the same one retried again) replaces it via
	 * discard_stashed_logo() rather than accumulating temp files.
	 */
	private static function stash_logo_upload( $user_id, $business_id ) {
		// wp_tempnam() lives in wp-admin/includes/file.php, which (unlike
		// wp-admin/includes/{image,media}.php's functions used elsewhere in
		// this class) isn't loaded on the front end by default - this method
		// runs from handle_request(), reached via a plain front-end POST.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$file     = $_FILES['rbn_business_logo']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- nonce verified by handle_request() before this is ever called; only passed to wp_tempnam()/wp_check_filetype_and_ext(), which sanitize internally.
		$tmp_path = wp_tempnam( $file['name'] );

		if ( ! $tmp_path || ! move_uploaded_file( $file['tmp_name'], $tmp_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_move_uploaded_file -- moving a validated upload out of PHP's own tmp storage, before the media library (which handle_logo_upload() defers to) is ever involved.
			return;
		}

		self::discard_stashed_logo( $user_id );

		set_transient(
			self::LOGO_STASH_TRANSIENT_PREFIX . $user_id,
			array(
				'business_id' => $business_id,
				'tmp_path'    => $tmp_path,
				'name'        => sanitize_file_name( $file['name'] ),
				'type'        => wp_check_filetype_and_ext( $tmp_path, $file['name'] )['type'],
			),
			10 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * A read-only look at a stashed logo (if any) for this user's given
	 * business - $business_id must match the same way
	 * consume_sticky_submission()'s does, so a stash from adding one
	 * business never bleeds into a different one. Used by
	 * RBN_Templates::business_form() to tell the member their previously
	 * selected file is still there, without consuming it - it's still
	 * needed for the *next* submission, not just this render.
	 */
	public static function peek_stashed_logo( $user_id, $business_id ) {
		$stash = get_transient( self::LOGO_STASH_TRANSIENT_PREFIX . $user_id );

		if ( ! $stash || (int) $stash['business_id'] !== (int) $business_id ) {
			return null;
		}

		return $stash;
	}

	/**
	 * Deletes a stashed logo's transient and temp file together, so the two
	 * can never end up out of sync - called once a stash is no longer
	 * needed: superseded by a new upload (stash_logo_upload() calls this on
	 * itself first), successfully attached to a business
	 * (handle_logo_upload()), or the member explicitly removed it instead of
	 * replacing it (also handle_logo_upload(), via rbn_remove_logo).
	 */
	private static function discard_stashed_logo( $user_id ) {
		$stash = get_transient( self::LOGO_STASH_TRANSIENT_PREFIX . $user_id );

		if ( $stash && ! empty( $stash['tmp_path'] ) && file_exists( $stash['tmp_path'] ) ) {
			wp_delete_file( $stash['tmp_path'] );
		}

		delete_transient( self::LOGO_STASH_TRANSIENT_PREFIX . $user_id );
	}

	/**
	 * A member deleting one of their own businesses - same "never trust a
	 * client-supplied ID" rule as handle_request(): the submitted ID must
	 * resolve via get_by_id_for_user() before anything happens.
	 *
	 * Trashed rather than force-deleted, so an admin can still restore a
	 * listing removed by mistake from wp-admin. To the member it's gone
	 * either way - RBN_Business_Repository treats trash as "not owned",
	 * and it drops out of the directory and everyone's Favourites (which
	 * only ever list published businesses). Follow rows pointing at it are
	 * cleaned up by RBN_Business_Follows::cleanup_on_business_deleted() once
	 * the trash is emptied and the post is permanently deleted.
	 */
	public static function handle_delete_request() {
		if ( empty( $_POST['rbn_form_action'] ) || self::DELETE_ACTION !== $_POST['rbn_form_action'] || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- strict comparisons against literals; the actual nonce is verified just below before anything happens.
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$nonce = isset( $_POST['rbn_business_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_business_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::DELETE_ACTION ) ) {
			self::redirect_with_notice( 'business_invalid_request' );
		}

		$business_id = isset( $_POST['rbn_business_id'] ) ? absint( $_POST['rbn_business_id'] ) : 0;
		$business    = RBN_Business_Repository::get_by_id_for_user( $business_id, get_current_user_id() );

		if ( ! $business || ! current_user_can( 'delete_post', $business->ID ) ) {
			self::redirect_with_notice( 'business_not_permitted' );
		}

		if ( ! wp_trash_post( $business->ID ) ) {
			self::redirect_with_notice( 'business_not_permitted' );
		}

		self::redirect_with_notice( 'business_deleted' );
	}

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

		// Remembered for the length of one redirect round-trip (see
		// remember_submission()'s docblock) so a validation failure below
		// can redisplay what the member actually typed instead of the blank/
		// stale form RBN_Templates::business_form() would otherwise fall
		// back to - it only ever reads from $business (or nothing, for a new
		// listing), never from a failed $_POST.
		$submitted = compact(
			'business_id',
			'community_id',
			'name',
			'description',
			'category_id',
			'service_ids',
			'town_city',
			'county_region',
			'postcode',
			'service_area',
			'website',
			'phone',
			'contact_email'
		);

		// A new business always needs a community. An existing one only
		// needs to validate a submitted value - the form omits the field
		// entirely (see RBN_Templates::business_community_field()) when the
		// owner has no communities to choose from, in which case the
		// existing value (however it got there) is left untouched.
		$community_id_is_valid = $community_submitted
			? ( $community_id && ( RBN_Community_Memberships::is_member( $user_id, $community_id ) || ( $business && $community_id === absint( get_post_meta( $business->ID, 'rbn_community_id', true ) ) ) ) )
			: (bool) $business;

		// A logo is stashed here (rather than only once, after every check)
		// because it's only worth keeping when it actually validated - see
		// stash_logo_upload()'s docblock - and every one of these three
		// recoverable failures needs the same "was a good logo also
		// submitted this time?" check.
		$logo_submitted_now = self::logo_was_submitted();
		$logo_is_valid_now  = self::logo_upload_is_valid();

		if ( ! $community_id_is_valid ) {
			if ( $logo_submitted_now && $logo_is_valid_now ) {
				self::stash_logo_upload( $user_id, $business_id );
			}
			self::remember_submission( $user_id, $submitted, array( 'community' ) );
			self::redirect_with_notice( 'business_invalid_community' );
		}

		$valid_service_ids = array_values(
			array_filter(
				array_unique( $service_ids ),
				function ( $service_id ) {
					return $service_id && term_exists( $service_id, RBN_Taxonomy_Service::TAXONOMY );
				}
			)
		);

		// Checked and collected individually (rather than one combined
		// condition) so a failure can point at exactly the field(s)
		// responsible - see remember_submission()'s docblock - instead of
		// leaving every field looking equally suspect. Every field is
		// mandatory except Website - see RBN_Templates::business_form().
		$field_errors = array();

		if ( '' === $name ) {
			$field_errors[] = 'name';
		}

		if ( '' === $description ) {
			$field_errors[] = 'description';
		}

		if ( ! $category_id || ! term_exists( $category_id, RBN_Taxonomy_Business_Category::TAXONOMY ) ) {
			$field_errors[] = 'category';
		}

		if ( empty( $valid_service_ids ) ) {
			$field_errors[] = 'services';
		}

		if ( '' === $town_city ) {
			$field_errors[] = 'town_city';
		}

		if ( '' === $county_region ) {
			$field_errors[] = 'county_region';
		}

		if ( '' === $postcode ) {
			$field_errors[] = 'postcode';
		}

		if ( '' === $service_area ) {
			$field_errors[] = 'service_area';
		}

		if ( '' === $phone ) {
			$field_errors[] = 'phone';
		}

		if ( '' === $contact_email || ! is_email( $contact_email ) ) {
			$field_errors[] = 'contact_email';
		}

		if ( ! empty( $field_errors ) ) {
			if ( $logo_submitted_now && $logo_is_valid_now ) {
				self::stash_logo_upload( $user_id, $business_id );
			}
			self::remember_submission( $user_id, $submitted, $field_errors );
			self::redirect_with_notice( 'business_missing_fields' );
		}

		if ( ! $logo_is_valid_now ) {
			self::remember_submission( $user_id, $submitted, array( 'logo' ) );
			self::redirect_with_notice( 'business_logo_invalid' );
		}

		$is_new_business = ! $business;

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

			// Admins no longer review every application by hand (see
			// RBN_Member_Approval) - only an activated account can reach
			// this form at all (login is blocked otherwise), so a listing
			// from one goes straight to 'publish' instead of sitting in a
			// 'pending' review queue nobody has time to work through. The
			// status check is still explicit here (rather than assuming
			// "logged in" already means "activated") so this stays correct
			// even if that login gate is ever loosened.
			$new_business_status = RBN_Member_Approval::STATUS_APPROVED === RBN_Member_Approval::get_status( $user_id )
				? 'publish'
				: 'pending';

			$post_id = wp_insert_post(
				array(
					'post_type'    => RBN_Post_Type_Business::POST_TYPE,
					'post_title'   => $name,
					'post_content' => $description,
					'post_status'  => $new_business_status,
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

		self::handle_logo_upload( $post_id, $user_id, $business_id );

		if ( ! $is_new_business ) {
			self::redirect_with_notice( 'business_updated' );
		}

		self::redirect_with_notice( 'publish' === $new_business_status ? 'business_published' : 'business_saved' );
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
	 * saved) or, failing that, one stashed by stash_logo_upload() from an
	 * earlier attempt this same business rejected for an unrelated reason;
	 * otherwise removes the current logo if requested. Runs after the
	 * business post is saved, since the attachment needs a real post ID as
	 * its parent.
	 *
	 * Checked in this order deliberately: a file chosen just now always wins
	 * over an older stash (stash_logo_upload() already discards the old one
	 * whenever a newer upload replaces it, but this covers the same request
	 * seeing both); and an explicit "remove logo" checkbox wins over a stash
	 * too, so a member who deliberately unchecks/removes it on a retry isn't
	 * overridden by a file they picked on an earlier, failed attempt.
	 */
	private static function handle_logo_upload( $post_id, $user_id, $business_id ) {
		if ( self::logo_was_submitted() ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$attachment_id = media_handle_upload( 'rbn_business_logo', $post_id );

			if ( ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}

			self::discard_stashed_logo( $user_id );
			return;
		}

		if ( ! empty( $_POST['rbn_remove_logo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified by handle_request() before this is ever called; value is only ever used as a boolean flag.
			delete_post_thumbnail( $post_id );
			self::discard_stashed_logo( $user_id );
			return;
		}

		$stash = self::peek_stashed_logo( $user_id, $business_id );

		if ( $stash ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$attachment_id = media_handle_sideload(
				array(
					'name'     => $stash['name'],
					'type'     => $stash['type'],
					'tmp_name' => $stash['tmp_path'],
				),
				$post_id
			);

			if ( ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}

			self::discard_stashed_logo( $user_id );
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
