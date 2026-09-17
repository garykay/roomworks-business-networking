<?php
/**
 * Reusable markup for the member/business account experience and the
 * business directory. Kept out of any single block's render.php so more
 * than one block (e.g. a future dedicated "My Business" or "Business
 * Search" block) can reuse the same forms/results without duplicating
 * logic.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Templates {

	public static function notice( $code ) {
		if ( ! $code ) {
			return '';
		}

		$text = RBN_Notices::text( $code );

		if ( ! $text ) {
			return '';
		}

		$status = RBN_Notices::status( $code );

		return sprintf(
			'<p class="rbn-notice rbn-notice--%1$s" role="%2$s">%3$s</p>',
			esc_attr( $status ),
			'error' === $status ? 'alert' : 'status',
			esc_html( $text )
		);
	}

	private static function notice_for_section( $notice_code, $section ) {
		return $notice_code && $section === RBN_Notices::section( $notice_code ) ? self::notice( $notice_code ) : '';
	}

	/**
	 * Login + registration forms for a logged-out visitor.
	 */
	public static function auth_forms( $current_url, $notice_code ) {
		// Honours ?redirect_to= (set by RBN_Access_Control when it bounces a
		// logged-out visitor off a restricted page) so a successful login
		// lands them back where they were headed, not just on this page.
		// wp_validate_redirect() is the sanitization step - it rejects
		// anything that isn't a safe local/allowed-host URL.
		$login_redirect = $current_url;

		if ( isset( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, only chooses the login form's own redirect target; see comment above.
			$login_redirect = wp_validate_redirect( wp_unslash( $_GET['redirect_to'] ), $current_url ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
		}

		ob_start();
		?>
		<div class="rbn-auth">
			<section class="rbn-auth__login rbn-card">
				<h2><?php esc_html_e( 'Log In', 'roomworks-business-networking' ); ?></h2>

				<?php echo self::notice_for_section( $notice_code, 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped in notice(). ?>

				<form class="rbn-form" method="post" action="<?php echo esc_url( $current_url ); ?>">
					<?php wp_nonce_field( 'rbn_login', 'rbn_login_nonce' ); ?>
					<input type="hidden" name="rbn_form_action" value="rbn_login" />
					<input type="hidden" name="rbn_redirect_to" value="<?php echo esc_attr( $login_redirect ); ?>" />

					<p>
						<label for="rbn-login-username"><?php esc_html_e( 'Username or Email', 'roomworks-business-networking' ); ?></label>
						<input type="text" id="rbn-login-username" name="rbn_username" autocomplete="username" required />
					</p>
					<p>
						<label for="rbn-login-password"><?php esc_html_e( 'Password', 'roomworks-business-networking' ); ?></label>
						<input type="password" id="rbn-login-password" name="rbn_password" autocomplete="current-password" required />
					</p>
					<p>
						<button type="submit" class="rbn-button"><?php esc_html_e( 'Log In', 'roomworks-business-networking' ); ?></button>
					</p>
					<p>
						<a class="rbn-link" href="<?php echo esc_url( wp_lostpassword_url( $current_url ) ); ?>"><?php esc_html_e( 'Forgot your password?', 'roomworks-business-networking' ); ?></a>
					</p>
				</form>
			</section>

			<section class="rbn-auth__register rbn-card">
				<h2><?php esc_html_e( 'Create an Account', 'roomworks-business-networking' ); ?></h2>

				<?php echo self::notice_for_section( $notice_code, 'register' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<form class="rbn-form" method="post" action="<?php echo esc_url( $current_url ); ?>">
					<?php wp_nonce_field( 'rbn_register', 'rbn_register_nonce' ); ?>
					<input type="hidden" name="rbn_form_action" value="rbn_register" />
					<input type="hidden" name="rbn_redirect_to" value="<?php echo esc_attr( $current_url ); ?>" />
					<?php echo RBN_Auth_Forms::honeypot_fields(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>

					<p>
						<label for="rbn-register-first-name"><?php esc_html_e( 'First Name', 'roomworks-business-networking' ); ?></label>
						<input type="text" id="rbn-register-first-name" name="rbn_first_name" autocomplete="given-name" required />
					</p>
					<p>
						<label for="rbn-register-last-name"><?php esc_html_e( 'Last Name', 'roomworks-business-networking' ); ?></label>
						<input type="text" id="rbn-register-last-name" name="rbn_last_name" autocomplete="family-name" required />
					</p>
					<p>
						<label for="rbn-register-display-name"><?php esc_html_e( 'Display Name', 'roomworks-business-networking' ); ?></label>
						<input type="text" id="rbn-register-display-name" name="rbn_display_name" autocomplete="nickname" />
					</p>
					<p>
						<label for="rbn-register-email"><?php esc_html_e( 'Email Address', 'roomworks-business-networking' ); ?></label>
						<input type="email" id="rbn-register-email" name="rbn_email" autocomplete="email" required />
					</p>
					<p>
						<label for="rbn-register-password"><?php esc_html_e( 'Password', 'roomworks-business-networking' ); ?></label>
						<input type="password" id="rbn-register-password" name="rbn_password" autocomplete="new-password" required />
					</p>
					<p>
						<label for="rbn-register-password-confirm"><?php esc_html_e( 'Confirm Password', 'roomworks-business-networking' ); ?></label>
						<input type="password" id="rbn-register-password-confirm" name="rbn_password_confirm" autocomplete="new-password" required />
					</p>
					<p class="rbn-form__checkbox">
						<label>
							<input type="checkbox" name="rbn_terms" value="1" required />
							<?php esc_html_e( 'I agree to the Terms and Privacy Policy.', 'roomworks-business-networking' ); ?>
						</label>
					</p>
					<p>
						<button type="submit" class="rbn-button"><?php esc_html_e( 'Create Account', 'roomworks-business-networking' ); ?></button>
					</p>
				</form>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * The full logged-in experience: profile summary, profile edit form,
	 * and the business create/edit form.
	 */
	public static function member_dashboard( $current_user, $current_url, $notice_code ) {
		$business = RBN_Business_Repository::get_for_user( $current_user->ID );

		ob_start();
		?>
		<div class="rbn-dashboard">
			<?php echo self::profile_summary( $current_user, $business ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>
			<?php echo self::profile_edit_form( $current_user, $current_url, $notice_code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo self::business_form( $business, $current_url, $notice_code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo self::account_deletion_section( $current_user, $current_url, $notice_code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<p class="rbn-profile__logout">
				<a class="rbn-link" href="<?php echo esc_url( wp_logout_url( $current_url ) ); ?>"><?php esc_html_e( 'Log out', 'roomworks-business-networking' ); ?></a>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function profile_summary( $current_user, $business ) {
		$bio = get_user_meta( $current_user->ID, 'description', true );

		ob_start();
		?>
		<div class="rbn-profile rbn-card">
			<div class="rbn-profile__header">
				<?php echo get_avatar( $current_user->ID, 96 ); ?>

				<div>
					<h2 class="rbn-profile__name"><?php echo esc_html( $current_user->display_name ); ?></h2>
					<?php if ( $bio ) : ?>
						<p class="rbn-profile__bio"><?php echo esc_html( $bio ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="rbn-profile__business">
				<h3><?php esc_html_e( 'Business', 'roomworks-business-networking' ); ?></h3>
				<?php if ( $business ) : ?>
					<p>
						<?php echo esc_html( wp_specialchars_decode( get_the_title( $business ), ENT_QUOTES ) ); ?>
						<?php if ( 'pending' === $business->post_status ) : ?>
							<span class="rbn-status rbn-status--pending"><?php esc_html_e( 'Pending review', 'roomworks-business-networking' ); ?></span>
						<?php endif; ?>
					</p>
				<?php else : ?>
					<p class="rbn-field-note"><?php esc_html_e( "You haven't added a business yet.", 'roomworks-business-networking' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * A member can only ever request deletion (or cancel that request) -
	 * never delete their account outright, see RBN_Account_Deletion_Forms.
	 * The confirmation step is a plain GET link toggling ?rbn_confirm_deletion=1
	 * rather than a JS confirm() dialog, so it still works without JS: the
	 * link takes them to a "are you sure" screen with a real submit button,
	 * not straight to the destructive action.
	 *
	 * Not offered to administrators at all - self-service deletion is a
	 * member feature, and losing the site's only/last admin to a 24-hour
	 * timer would be a bad time. RBN_Account_Deletion_Forms enforces this
	 * server-side too, in case an admin's request is ever POSTed directly.
	 */
	private static function account_deletion_section( $current_user, $current_url, $notice_code ) {
		if ( in_array( 'administrator', (array) $current_user->roles, true ) ) {
			return '';
		}

		$user_id   = $current_user->ID;
		$requested = RBN_Account_Deletion::is_requested( $user_id );
		$confirming = ! $requested && isset( $_GET['rbn_confirm_deletion'] ) && '1' === $_GET['rbn_confirm_deletion']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only toggles which confirmation copy/button is shown, not a state change.

		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		ob_start();
		?>
		<section class="rbn-delete-account rbn-card">
			<h3><?php esc_html_e( 'Delete Account', 'roomworks-business-networking' ); ?></h3>

			<?php echo self::notice_for_section( $notice_code, 'account_deletion' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<?php if ( $requested ) : ?>
				<p class="rbn-field-note">
					<?php
					printf(
						/* translators: %s: scheduled deletion date/time. */
						esc_html__( 'Your account is scheduled for deletion on %s. You can cancel this request at any time before then.', 'roomworks-business-networking' ),
						esc_html( wp_date( $date_format, RBN_Account_Deletion::scheduled_for( $user_id ) ) )
					);
					?>
				</p>
				<form class="rbn-form" method="post" action="<?php echo esc_url( $current_url ); ?>">
					<?php wp_nonce_field( RBN_Account_Deletion_Forms::CANCEL_ACTION, 'rbn_account_deletion_nonce' ); ?>
					<input type="hidden" name="rbn_form_action" value="<?php echo esc_attr( RBN_Account_Deletion_Forms::CANCEL_ACTION ); ?>" />
					<input type="hidden" name="rbn_redirect_to" value="<?php echo esc_attr( $current_url ); ?>" />
					<button type="submit" class="rbn-button rbn-button--danger"><?php esc_html_e( 'Cancel Deletion Request', 'roomworks-business-networking' ); ?></button>
				</form>
			<?php elseif ( $confirming ) : ?>
				<p class="rbn-field-note"><?php esc_html_e( 'Are you sure? Your account and business listing will be permanently deleted in 24 hours, unless you cancel before then.', 'roomworks-business-networking' ); ?></p>
				<div class="rbn-delete-account__actions">
					<form class="rbn-form" method="post" action="<?php echo esc_url( $current_url ); ?>">
						<?php wp_nonce_field( RBN_Account_Deletion_Forms::REQUEST_ACTION, 'rbn_account_deletion_nonce' ); ?>
						<input type="hidden" name="rbn_form_action" value="<?php echo esc_attr( RBN_Account_Deletion_Forms::REQUEST_ACTION ); ?>" />
						<input type="hidden" name="rbn_redirect_to" value="<?php echo esc_attr( $current_url ); ?>" />
						<button type="submit" class="rbn-button rbn-button--danger"><?php esc_html_e( 'Yes, Delete My Account', 'roomworks-business-networking' ); ?></button>
					</form>
					<a class="rbn-link" href="<?php echo esc_url( $current_url ); ?>"><?php esc_html_e( 'Cancel', 'roomworks-business-networking' ); ?></a>
				</div>
			<?php else : ?>
				<p class="rbn-field-note"><?php esc_html_e( 'Deleting your account is permanent and also removes your business listing. This cannot be undone once it takes effect.', 'roomworks-business-networking' ); ?></p>
				<a class="rbn-button rbn-button--danger" href="<?php echo esc_url( add_query_arg( 'rbn_confirm_deletion', '1', $current_url ) ); ?>"><?php esc_html_e( 'Request Account Deletion', 'roomworks-business-networking' ); ?></a>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function profile_edit_form( $current_user, $current_url, $notice_code ) {
		$bio = get_user_meta( $current_user->ID, 'description', true );

		ob_start();
		?>
		<section class="rbn-edit-profile rbn-card">
			<h3><?php esc_html_e( 'Edit Profile', 'roomworks-business-networking' ); ?></h3>

			<?php echo self::notice_for_section( $notice_code, 'profile' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<form class="rbn-form" method="post" action="<?php echo esc_url( $current_url ); ?>">
				<?php wp_nonce_field( 'rbn_update_profile', 'rbn_profile_nonce' ); ?>
				<input type="hidden" name="rbn_form_action" value="rbn_update_profile" />
				<input type="hidden" name="rbn_redirect_to" value="<?php echo esc_attr( $current_url ); ?>" />

				<p>
					<label for="rbn-profile-first-name"><?php esc_html_e( 'First Name', 'roomworks-business-networking' ); ?></label>
					<input type="text" id="rbn-profile-first-name" name="rbn_first_name" autocomplete="given-name" value="<?php echo esc_attr( $current_user->first_name ); ?>" required />
				</p>
				<p>
					<label for="rbn-profile-last-name"><?php esc_html_e( 'Last Name', 'roomworks-business-networking' ); ?></label>
					<input type="text" id="rbn-profile-last-name" name="rbn_last_name" autocomplete="family-name" value="<?php echo esc_attr( $current_user->last_name ); ?>" required />
				</p>
				<p>
					<label for="rbn-profile-display-name"><?php esc_html_e( 'Display Name', 'roomworks-business-networking' ); ?></label>
					<input type="text" id="rbn-profile-display-name" name="rbn_display_name" autocomplete="nickname" value="<?php echo esc_attr( $current_user->display_name ); ?>" />
				</p>
				<p>
					<label for="rbn-profile-bio"><?php esc_html_e( 'About You', 'roomworks-business-networking' ); ?></label>
					<textarea id="rbn-profile-bio" name="rbn_bio" rows="4"><?php echo esc_textarea( $bio ); ?></textarea>
				</p>
				<p>
					<button type="submit" class="rbn-button"><?php esc_html_e( 'Save Profile', 'roomworks-business-networking' ); ?></button>
				</p>
			</form>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function business_form( $business, $current_url, $notice_code ) {
		$name          = $business ? wp_specialchars_decode( get_the_title( $business ), ENT_QUOTES ) : '';
		$description   = $business ? $business->post_content : '';
		$selected_categories = $business ? wp_get_post_terms( $business->ID, RBN_Taxonomy_Business_Category::TAXONOMY ) : array();
		if ( is_wp_error( $selected_categories ) ) {
			$selected_categories = array();
		}
		$selected_services = $business ? wp_get_post_terms( $business->ID, RBN_Taxonomy_Service::TAXONOMY ) : array();
		if ( is_wp_error( $selected_services ) ) {
			$selected_services = array();
		}
		$town_city     = $business ? get_post_meta( $business->ID, 'rbn_town_city', true ) : '';
		$county_region = $business ? get_post_meta( $business->ID, 'rbn_county_region', true ) : '';
		$postcode      = $business ? get_post_meta( $business->ID, 'rbn_postcode', true ) : '';
		$service_area  = $business ? get_post_meta( $business->ID, 'rbn_service_area', true ) : '';
		$website       = $business ? get_post_meta( $business->ID, 'rbn_website', true ) : '';
		$phone         = $business ? get_post_meta( $business->ID, 'rbn_phone', true ) : '';
		$contact_email = $business ? get_post_meta( $business->ID, 'rbn_contact_email', true ) : '';

		$categories = get_terms(
			array(
				'taxonomy'   => RBN_Taxonomy_Business_Category::TAXONOMY,
				'hide_empty' => false,
			)
		);

		$services = get_terms(
			array(
				'taxonomy'   => RBN_Taxonomy_Service::TAXONOMY,
				'hide_empty' => false,
			)
		);

		ob_start();
		?>
		<section class="rbn-edit-business rbn-card">
			<h3>
				<?php
				echo $business
					? esc_html__( 'Edit Your Business', 'roomworks-business-networking' )
					: esc_html__( 'Add Your Business', 'roomworks-business-networking' );
				?>
			</h3>

			<?php echo self::notice_for_section( $notice_code, 'business' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<p class="rbn-field-note"><?php esc_html_e( 'All fields are required except Website.', 'roomworks-business-networking' ); ?></p>

			<form class="rbn-form" method="post" action="<?php echo esc_url( $current_url ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'rbn_save_business', 'rbn_business_nonce' ); ?>
				<input type="hidden" name="rbn_form_action" value="rbn_save_business" />
				<input type="hidden" name="rbn_redirect_to" value="<?php echo esc_attr( $current_url ); ?>" />

				<p>
					<label for="rbn-business-name"><?php esc_html_e( 'Business Name', 'roomworks-business-networking' ); ?></label>
					<input type="text" id="rbn-business-name" name="rbn_business_name" value="<?php echo esc_attr( $name ); ?>" required />
				</p>

				<p>
					<label for="rbn-business-logo"><?php esc_html_e( 'Business Logo', 'roomworks-business-networking' ); ?></label>
					<?php if ( $business && has_post_thumbnail( $business ) ) : ?>
						<span class="rbn-logo-preview">
							<img src="<?php echo esc_url( get_the_post_thumbnail_url( $business, 'thumbnail' ) ); ?>" alt="<?php esc_attr_e( 'Current logo', 'roomworks-business-networking' ); ?>" />
						</span>
					<?php endif; ?>
					<input type="file" id="rbn-business-logo" name="rbn_business_logo" accept="image/png,image/jpeg,image/gif,image/webp" />
					<span class="rbn-field-note"><?php esc_html_e( 'Optional. JPG, PNG, GIF or WEBP, up to 5MB.', 'roomworks-business-networking' ); ?></span>
				</p>

				<?php if ( $business && has_post_thumbnail( $business ) ) : ?>
					<p class="rbn-form__checkbox">
						<label>
							<input type="checkbox" name="rbn_remove_logo" value="1" />
							<?php esc_html_e( 'Remove current logo', 'roomworks-business-networking' ); ?>
						</label>
					</p>
				<?php endif; ?>

				<?php
				echo self::business_type_field( $selected_categories, $categories ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within business_type_field().
				?>

				<p>
					<label for="rbn-business-description"><?php esc_html_e( 'Description', 'roomworks-business-networking' ); ?></label>
					<textarea id="rbn-business-description" name="rbn_business_description" rows="4" required><?php echo esc_textarea( $description ); ?></textarea>
				</p>

				<?php
				$services_field_args = array(
					'legend'         => __( 'Services', 'roomworks-business-networking' ),
					'input_name'     => 'rbn_services[]',
					'rest_route'     => 'services',
					'selected_terms' => $selected_services,
					'all_terms'      => $services,
					'search_id'      => 'rbn-service-search',
					'placeholder'    => __( 'Start typing a service…', 'roomworks-business-networking' ),
					'empty_note'     => __( 'No services are available yet.', 'roomworks-business-networking' ),
				);
				echo self::tag_field( $services_field_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within tag_field().
				?>

				<div class="rbn-form__grid">
					<p>
						<label for="rbn-business-town-city"><?php esc_html_e( 'Town / City', 'roomworks-business-networking' ); ?></label>
						<input type="text" id="rbn-business-town-city" name="rbn_town_city" value="<?php echo esc_attr( $town_city ); ?>" required />
					</p>
					<p>
						<label for="rbn-business-county-region"><?php esc_html_e( 'County / Region', 'roomworks-business-networking' ); ?></label>
						<input type="text" id="rbn-business-county-region" name="rbn_county_region" value="<?php echo esc_attr( $county_region ); ?>" required />
					</p>
					<p>
						<label for="rbn-business-postcode"><?php esc_html_e( 'Postcode', 'roomworks-business-networking' ); ?></label>
						<input type="text" id="rbn-business-postcode" name="rbn_postcode" value="<?php echo esc_attr( $postcode ); ?>" required />
					</p>
					<p>
						<label for="rbn-business-service-area"><?php esc_html_e( 'Service Area', 'roomworks-business-networking' ); ?></label>
						<input type="text" id="rbn-business-service-area" name="rbn_service_area" value="<?php echo esc_attr( $service_area ); ?>" required />
					</p>
					<p>
						<label for="rbn-business-website"><?php esc_html_e( 'Website', 'roomworks-business-networking' ); ?></label>
						<input type="url" id="rbn-business-website" name="rbn_website" value="<?php echo esc_attr( $website ); ?>" />
					</p>
					<p>
						<label for="rbn-business-phone"><?php esc_html_e( 'Phone', 'roomworks-business-networking' ); ?></label>
						<input type="text" id="rbn-business-phone" name="rbn_phone" value="<?php echo esc_attr( $phone ); ?>" required />
					</p>
					<p>
						<label for="rbn-business-contact-email"><?php esc_html_e( 'Contact Email', 'roomworks-business-networking' ); ?></label>
						<input type="email" id="rbn-business-contact-email" name="rbn_contact_email" value="<?php echo esc_attr( $contact_email ); ?>" required />
					</p>
				</div>

				<p>
					<button type="submit" class="rbn-button">
						<?php
						echo $business
							? esc_html__( 'Save Changes', 'roomworks-business-networking' )
							: esc_html__( 'Submit Business', 'roomworks-business-networking' );
						?>
					</button>
				</p>
			</form>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * The Business Type field: a plain dropdown of existing categories plus
	 * an "Other" option that reveals a text input for adding one that isn't
	 * listed. Deliberately not a free-typing field like Services - members
	 * were typing services (or anything else) into it since it invited
	 * open-ended text; choosing from a list first, with "Other" as a
	 * conscious extra step, is meant to make miscategorising less likely.
	 * New types go through RBN_REST_Business_Categories's find-or-create
	 * endpoint, so a name matching an existing type is reused rather than
	 * duplicated. Degrades to a plain select without JS - "Other" still
	 * submits, just as no category, since adding one needs a request.
	 */
	private static function business_type_field( array $selected_categories, $categories ) {
		$category_id = ! empty( $selected_categories ) ? (int) $selected_categories[0]->term_id : 0;

		if ( is_wp_error( $categories ) ) {
			$categories = array();
		}

		$i18n = array(
			'adding' => __( 'Adding…', 'roomworks-business-networking' ),
			'error'  => __( 'Something went wrong adding that business type.', 'roomworks-business-networking' ),
		);

		ob_start();
		?>
		<p
			class="rbn-business-type-field"
			data-rbn-category-field
			data-rest-url="<?php echo esc_url( rest_url( 'roomworks-business-networking/v1/business-categories' ) ); ?>"
			data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
			data-i18n="<?php echo esc_attr( wp_json_encode( $i18n ) ); ?>"
		>
			<label for="rbn-business-category"><?php esc_html_e( 'Business Type', 'roomworks-business-networking' ); ?></label>
			<select id="rbn-business-category" name="rbn_business_category" data-rbn-category-select required>
				<option value=""><?php esc_html_e( '— Select —', 'roomworks-business-networking' ); ?></option>
				<?php foreach ( $categories as $category ) : ?>
					<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $category_id, $category->term_id ); ?>>
						<?php echo esc_html( wp_specialchars_decode( $category->name, ENT_QUOTES ) ); ?>
					</option>
				<?php endforeach; ?>
				<option value="__other__" data-rbn-other-option><?php esc_html_e( 'Other — add a new business type', 'roomworks-business-networking' ); ?></option>
			</select>

			<span class="rbn-category-add" data-rbn-category-add hidden>
				<label for="rbn-business-category-new" class="rbn-visually-hidden"><?php esc_html_e( 'New business type', 'roomworks-business-networking' ); ?></label>
				<input
					type="text"
					id="rbn-business-category-new"
					data-rbn-category-input
					placeholder="<?php esc_attr_e( 'e.g. Painting & Decorating', 'roomworks-business-networking' ); ?>"
				/>
				<button type="button" class="rbn-button rbn-button--secondary" data-rbn-category-submit><?php esc_html_e( 'Add', 'roomworks-business-networking' ); ?></button>
			</span>

			<span class="rbn-field-note" data-rbn-category-status role="status" aria-live="polite"></span>
		</p>
		<?php
		return ob_get_clean();
	}

	/**
	 * A search-as-you-type field for picking existing taxonomy terms or
	 * creating a new one on the fly, backed by RBN_REST_Services. Used for
	 * Services (multi-select) on the business form. Business Type used to
	 * share this, but its free-typing UI made it too easy to add the wrong
	 * kind of term (see business_type_field() above) - kept here since
	 * Services still benefits from typing ahead rather than scrolling a
	 * long dropdown. Falls back to a plain checkbox list inside <noscript>
	 * when JS doesn't run - that fallback can only choose among
	 * $args['all_terms'], since creating a new term needs a request.
	 *
	 * @param array $args {
	 *     @type string    $legend         Field legend/label text.
	 *     @type string    $input_name     Form field name (with trailing `[]`, since this is always multi-select).
	 *     @type string    $rest_route     REST route under roomworks-business-networking/v1/.
	 *     @type WP_Term[] $selected_terms Currently assigned terms.
	 *     @type WP_Term[] $all_terms      Every term in the taxonomy, for the no-JS fallback.
	 *     @type string    $search_id      Element ID for the text input.
	 *     @type string    $placeholder    Text input placeholder.
	 *     @type string    $empty_note     Message shown when the taxonomy has no terms at all.
	 * }
	 */
	private static function tag_field( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'legend'         => '',
				'input_name'     => '',
				'rest_route'     => '',
				'selected_terms' => array(),
				'all_terms'      => array(),
				'search_id'      => '',
				'placeholder'    => '',
				'empty_note'     => '',
			)
		);

		$i18n = array(
			'adding'      => __( 'Adding…', 'roomworks-business-networking' ),
			/* translators: %s: term name. */
			'removeLabel' => __( 'Remove %s', 'roomworks-business-networking' ),
			'error'       => __( 'Something went wrong adding that.', 'roomworks-business-networking' ),
			'required'    => __( 'Please add at least one.', 'roomworks-business-networking' ),
		);

		$selected_ids = wp_list_pluck( $args['selected_terms'], 'term_id' );

		ob_start();
		?>
		<fieldset
			class="rbn-form__field rbn-tag-field"
			data-rbn-tag-field
			data-required="true"
			data-input-name="<?php echo esc_attr( $args['input_name'] ); ?>"
			data-rest-url="<?php echo esc_url( rest_url( 'roomworks-business-networking/v1/' . $args['rest_route'] ) ); ?>"
			data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
			data-i18n="<?php echo esc_attr( wp_json_encode( $i18n ) ); ?>"
		>
			<legend>
				<?php echo esc_html( $args['legend'] ); ?>
				<span class="rbn-required-marker" aria-hidden="true">*</span>
			</legend>

			<div class="rbn-chip-list" data-rbn-tag-chips>
				<?php foreach ( $args['selected_terms'] as $term ) : ?>
					<?php $term_name = wp_specialchars_decode( $term->name, ENT_QUOTES ); ?>
					<span class="rbn-chip rbn-chip--tag" data-term-id="<?php echo esc_attr( $term->term_id ); ?>">
						<input type="hidden" name="<?php echo esc_attr( $args['input_name'] ); ?>" value="<?php echo esc_attr( $term->term_id ); ?>" />
						<span class="rbn-chip__label"><?php echo esc_html( $term_name ); ?></span>
						<button
							type="button"
							class="rbn-chip__remove"
							data-rbn-tag-remove
							aria-label="<?php echo esc_attr( sprintf( /* translators: %s: term name. */ __( 'Remove %s', 'roomworks-business-networking' ), $term_name ) ); ?>"
						>&times;</button>
					</span>
				<?php endforeach; ?>
			</div>

			<div class="rbn-tag-add">
				<label for="<?php echo esc_attr( $args['search_id'] ); ?>" class="rbn-visually-hidden"><?php echo esc_html( $args['legend'] ); ?></label>
				<input
					type="text"
					id="<?php echo esc_attr( $args['search_id'] ); ?>"
					data-rbn-tag-search
					autocomplete="off"
					placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
				/>
				<button type="button" class="rbn-button rbn-button--secondary" data-rbn-tag-add><?php esc_html_e( 'Add', 'roomworks-business-networking' ); ?></button>
				<ul class="rbn-tag-suggestions" data-rbn-tag-suggestions role="listbox" hidden></ul>
			</div>

			<p class="rbn-field-note" data-rbn-tag-status role="status" aria-live="polite"></p>

			<noscript>
				<?php if ( empty( $args['all_terms'] ) || is_wp_error( $args['all_terms'] ) ) : ?>
					<p class="rbn-field-note"><?php echo esc_html( $args['empty_note'] ); ?></p>
				<?php else : ?>
					<div class="rbn-chip-list">
						<?php foreach ( $args['all_terms'] as $term ) : ?>
							<label class="rbn-chip">
								<input
									type="checkbox"
									name="<?php echo esc_attr( $args['input_name'] ); ?>"
									value="<?php echo esc_attr( $term->term_id ); ?>"
									<?php checked( in_array( $term->term_id, $selected_ids, true ) ); ?>
								/>
								<span><?php echo esc_html( wp_specialchars_decode( $term->name, ENT_QUOTES ) ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</noscript>
		</fieldset>
		<?php
		return ob_get_clean();
	}

	/**
	 * The filter form. Submits via a normal GET request so filtering works
	 * without JS (the block re-renders server-side from $_GET); view.js
	 * intercepts the same form to fetch results asynchronously instead.
	 */
	public static function directory_filters( $filter_options, $filters, $current_url ) {
		ob_start();
		?>
		<form class="rbn-form rbn-directory__filters rbn-card" method="get" action="<?php echo esc_url( $current_url ); ?>" data-rbn-filter-form>
			<p>
				<label for="rbn-filter-search"><?php esc_html_e( 'Search', 'roomworks-business-networking' ); ?></label>
				<input type="search" id="rbn-filter-search" name="rbn_search" value="<?php echo esc_attr( $filters['search'] ); ?>" />
			</p>

			<p>
				<label for="rbn-filter-category"><?php esc_html_e( 'Business Type', 'roomworks-business-networking' ); ?></label>
				<select id="rbn-filter-category" name="rbn_category">
					<option value=""><?php esc_html_e( 'All Business Types', 'roomworks-business-networking' ); ?></option>
					<?php foreach ( $filter_options['categories'] as $category ) : ?>
						<option value="<?php echo esc_attr( $category['slug'] ); ?>" <?php selected( $filters['category'], $category['slug'] ); ?>>
							<?php echo esc_html( $category['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<label for="rbn-filter-service"><?php esc_html_e( 'Service', 'roomworks-business-networking' ); ?></label>
				<select id="rbn-filter-service" name="rbn_service">
					<option value=""><?php esc_html_e( 'All Services', 'roomworks-business-networking' ); ?></option>
					<?php foreach ( $filter_options['services'] as $service ) : ?>
						<option value="<?php echo esc_attr( $service['slug'] ); ?>" <?php selected( $filters['service'], $service['slug'] ); ?>>
							<?php echo esc_html( $service['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<label for="rbn-filter-location"><?php esc_html_e( 'Location', 'roomworks-business-networking' ); ?></label>
				<input type="text" id="rbn-filter-location" name="rbn_location" value="<?php echo esc_attr( $filters['location'] ); ?>" placeholder="<?php esc_attr_e( 'Town, county or postcode', 'roomworks-business-networking' ); ?>" />
			</p>

			<p class="rbn-directory__filter-actions" data-rbn-filter-actions>
				<button type="submit" class="rbn-button"><?php esc_html_e( 'Filter', 'roomworks-business-networking' ); ?></button>
				<?php if ( $filters['category'] || $filters['service'] || $filters['location'] || $filters['search'] ) : ?>
					<a class="rbn-directory__clear rbn-link" href="<?php echo esc_url( $current_url ); ?>" data-rbn-filter-clear><?php esc_html_e( 'Clear filters', 'roomworks-business-networking' ); ?></a>
				<?php endif; ?>
			</p>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function directory_results( $results ) {
		ob_start();

		if ( empty( $results['items'] ) ) {
			?>
			<p class="rbn-directory__empty"><?php esc_html_e( 'No businesses found.', 'roomworks-business-networking' ); ?></p>
			<?php
		} else {
			?>
			<div class="rbn-directory__grid">
				<?php foreach ( $results['items'] as $item ) : ?>
					<?php echo self::business_card( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>
				<?php endforeach; ?>
			</div>
			<?php
		}

		return ob_get_clean();
	}

	private static function business_card( $item ) {
		ob_start();
		?>
		<article class="rbn-business-card rbn-card">
			<?php if ( $item['logo'] ) : ?>
				<img class="rbn-business-card__logo" src="<?php echo esc_url( $item['logo'] ); ?>" alt="" loading="lazy" />
			<?php else : ?>
				<span class="rbn-business-card__logo rbn-business-card__logo--placeholder" aria-hidden="true"><?php echo self::icon( 'image' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted markup, see icon(). ?></span>
			<?php endif; ?>

			<div class="rbn-business-card__body">
				<?php if ( $item['category'] ) : ?>
					<p class="rbn-business-card__category"><?php echo esc_html( $item['category'] ); ?></p>
				<?php endif; ?>

				<h3 class="rbn-business-card__name">
					<a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
				</h3>

				<?php if ( $item['excerpt'] ) : ?>
					<p class="rbn-business-card__excerpt"><?php echo esc_html( $item['excerpt'] ); ?></p>
				<?php endif; ?>

				<?php $location = trim( implode( ', ', array_filter( array( $item['town_city'], $item['county_region'] ) ) ) ); ?>
				<?php if ( $location ) : ?>
					<p class="rbn-business-card__location"><?php echo self::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted markup, see icon(). ?><?php echo esc_html( $location ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $item['services'] ) ) : ?>
					<p class="rbn-business-card__services"><?php echo esc_html( implode( ', ', $item['services'] ) ); ?></p>
				<?php endif; ?>

				<p class="rbn-business-card__link">
					<a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php esc_html_e( 'View Profile', 'roomworks-business-networking' ); ?></a>
				</p>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	public static function directory_pagination( $results, $filters, $current_url ) {
		if ( $results['total_pages'] <= 1 ) {
			return '';
		}

		ob_start();
		?>
		<nav class="rbn-pagination" aria-label="<?php esc_attr_e( 'Directory pagination', 'roomworks-business-networking' ); ?>">
			<?php foreach ( self::pagination_range( $results['page'], $results['total_pages'] ) as $page ) : ?>
				<?php if ( '...' === $page ) : ?>
					<span class="rbn-pagination__ellipsis" aria-hidden="true">&hellip;</span>
					<?php continue; ?>
				<?php endif; ?>
				<?php
				$page_args = array_filter(
					array(
						'rbn_category' => $filters['category'],
						'rbn_service'  => $filters['service'],
						'rbn_location' => $filters['location'],
						'rbn_search'   => $filters['search'],
						'rbn_page'     => $page > 1 ? $page : null,
					)
				);
				$page_url = $page_args ? add_query_arg( $page_args, $current_url ) : $current_url;
				?>
				<?php if ( $page === $results['page'] ) : ?>
					<span class="rbn-pagination__current" aria-current="page"><?php echo esc_html( $page ); ?></span>
				<?php else : ?>
					<a href="<?php echo esc_url( $page_url ); ?>" data-rbn-page="<?php echo esc_attr( $page ); ?>"><?php echo esc_html( $page ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</nav>
		<?php
		return ob_get_clean();
	}

	/**
	 * Which page numbers to render, collapsing a long run into "..." -
	 * always the first/last SIBLING_COUNT pages, plus a window of
	 * BOUNDARY_COUNT pages either side of the current page, e.g. for page 21
	 * of 42: [1, 2, '...', 20, 21, 22, '...', 41, 42]. Below the threshold
	 * where that window would already cover every page, every page number
	 * is returned with no "..." at all. Mirrored in
	 * src/roomworks-business-networking/view.js's paginationRange() for the
	 * JS-driven re-render after an async filter/page fetch - keep the two
	 * in sync if this changes.
	 *
	 * @return array<int|string> Page numbers, with '...' standing in for a
	 *                            collapsed run.
	 */
	private static function pagination_range( $current, $total ) {
		$sibling_count  = 1;
		$boundary_count = 2;

		$total_page_numbers = $boundary_count * 2 + $sibling_count * 2 + 3;

		if ( $total <= $total_page_numbers ) {
			return range( 1, $total );
		}

		$left_sibling  = max( $current - $sibling_count, $boundary_count + 1 );
		$right_sibling = min( $current + $sibling_count, $total - $boundary_count );

		$show_left_dots  = $left_sibling > $boundary_count + 2;
		$show_right_dots = $right_sibling < $total - $boundary_count - 1;

		$first_pages = range( 1, $boundary_count );
		$last_pages  = range( $total - $boundary_count + 1, $total );

		if ( ! $show_left_dots && $show_right_dots ) {
			return array_merge( range( 1, $boundary_count + $sibling_count * 2 + 2 ), array( '...' ), $last_pages );
		}

		if ( $show_left_dots && ! $show_right_dots ) {
			return array_merge( $first_pages, array( '...' ), range( $total - ( $boundary_count + $sibling_count * 2 + 2 ) + 1, $total ) );
		}

		return array_merge( $first_pages, array( '...' ), range( $left_sibling, $right_sibling ), array( '...' ), $last_pages );
	}

	/**
	 * Small trusted inline icon set for the profile cards. Never fed user
	 * input, so it's safe to echo raw - kept out of wp_kses_post() because
	 * that strips the SVG tags icons need.
	 */
	private static function icon( $name ) {
		$icons = array(
			'tag'    => '<path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3.24L4 3a1 1 0 0 0-1 1l.24 5.59a2 2 0 0 0 .59 1.41l9.58 9.58a2 2 0 0 0 2.83 0l4.35-4.35a2 2 0 0 0 0-2.82Z"></path><circle cx="7.5" cy="7.5" r="1.5"></circle>',
			'pin'    => '<circle cx="12" cy="10" r="3"></circle><path d="M12 21s7-7.5 7-12a7 7 0 0 0-14 0c0 4.5 7 12 7 12Z"></path>',
			'globe'  => '<circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10Z"></path>',
			'phone'  => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"></path>',
			'mail'   => '<rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 6-10 7L2 6"></path>',
			'radius' => '<circle cx="12" cy="12" r="9"></circle><circle cx="12" cy="12" r="4"></circle>',
			'image'  => '<rect x="2.5" y="4.5" width="19" height="15" rx="1.5"></rect><circle cx="8" cy="9.7" r="1.6" fill="currentColor"></circle><path d="M3.6 17.5 8 11.8l2.6 2.7L15.2 9l5.2 8.5H3.6Z" fill="currentColor"></path>',
		);

		if ( ! isset( $icons[ $name ] ) ) {
			return '';
		}

		return '<svg class="rbn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icons[ $name ] . '</svg>';
	}

	/**
	 * Everything the Single Business template doesn't already render via
	 * core blocks (Post Title, Post Featured Image, Post Content): business
	 * type, all services, full location, contact details, owning member.
	 */
	public static function business_profile( WP_Post $business ) {
		$categories = get_the_terms( $business, RBN_Taxonomy_Business_Category::TAXONOMY );
		$services   = get_the_terms( $business, RBN_Taxonomy_Service::TAXONOMY );

		$town_city     = get_post_meta( $business->ID, 'rbn_town_city', true );
		$county_region = get_post_meta( $business->ID, 'rbn_county_region', true );
		$postcode      = get_post_meta( $business->ID, 'rbn_postcode', true );
		$service_area  = get_post_meta( $business->ID, 'rbn_service_area', true );
		$website       = get_post_meta( $business->ID, 'rbn_website', true );
		$phone         = get_post_meta( $business->ID, 'rbn_phone', true );
		$contact_email = get_post_meta( $business->ID, 'rbn_contact_email', true );

		$owner    = get_userdata( $business->post_author );
		$location = trim( implode( ', ', array_filter( array( $town_city, $county_region, $postcode ) ) ) );

		ob_start();
		?>
		<article <?php post_class( 'rbn-business-profile' ); ?>>

			<?php if ( 'publish' !== $business->post_status && current_user_can( 'edit_post', $business->ID ) ) : ?>
				<p class="rbn-notice rbn-notice--error" role="status">
					<?php esc_html_e( "This business is pending review and isn't visible to the public yet.", 'roomworks-business-networking' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( has_post_thumbnail( $business ) ) : ?>
				<img class="rbn-business-profile__logo" src="<?php echo esc_url( get_the_post_thumbnail_url( $business, 'medium' ) ); ?>" alt="<?php echo esc_attr( wp_specialchars_decode( get_the_title( $business ), ENT_QUOTES ) ); ?>" />
			<?php endif; ?>

			<?php if ( $categories && ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
				<p class="rbn-business-profile__badge"><?php echo esc_html( wp_specialchars_decode( $categories[0]->name, ENT_QUOTES ) ); ?></p>
			<?php endif; ?>

			<?php if ( ( $services && ! is_wp_error( $services ) && ! empty( $services ) ) || $location || $service_area || $website || $phone || $contact_email ) : ?>
				<div class="rbn-business-profile__grid">

					<?php if ( $services && ! is_wp_error( $services ) && ! empty( $services ) ) : ?>
						<section class="rbn-business-profile__card rbn-business-profile__services">
							<h2 class="rbn-business-profile__card-title"><?php esc_html_e( 'Services', 'roomworks-business-networking' ); ?></h2>
							<ul class="rbn-business-profile__tags">
								<?php foreach ( $services as $service ) : ?>
									<li class="rbn-business-profile__tag"><?php echo self::icon( 'tag' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted markup, see icon(). ?><?php echo esc_html( wp_specialchars_decode( $service->name, ENT_QUOTES ) ); ?></li>
								<?php endforeach; ?>
							</ul>
						</section>
					<?php endif; ?>

					<?php if ( $location || $service_area ) : ?>
						<section class="rbn-business-profile__card rbn-business-profile__location">
							<h2 class="rbn-business-profile__card-title"><?php esc_html_e( 'Location', 'roomworks-business-networking' ); ?></h2>
							<?php if ( $location ) : ?>
								<p class="rbn-business-profile__row"><?php echo self::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $location ); ?></p>
							<?php endif; ?>
							<?php if ( $service_area ) : ?>
								<p class="rbn-business-profile__row rbn-business-profile__row--muted">
									<?php echo self::icon( 'radius' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<?php
									printf(
										/* translators: %s: service area description. */
										esc_html__( 'Service area: %s', 'roomworks-business-networking' ),
										esc_html( $service_area )
									);
									?>
								</p>
							<?php endif; ?>
						</section>
					<?php endif; ?>

					<?php if ( $website || $phone || $contact_email ) : ?>
						<section class="rbn-business-profile__card rbn-business-profile__contact">
							<h2 class="rbn-business-profile__card-title"><?php esc_html_e( 'Contact', 'roomworks-business-networking' ); ?></h2>
							<ul class="rbn-business-profile__contact-list">
								<?php if ( $website ) : ?>
									<li><a class="rbn-business-profile__row" href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener noreferrer"><?php echo self::icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $website ) ) ); ?></a></li>
								<?php endif; ?>
								<?php if ( $phone ) : ?>
									<li><a class="rbn-business-profile__row" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo self::icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $phone ); ?></a></li>
								<?php endif; ?>
								<?php if ( $contact_email ) : ?>
									<li><a class="rbn-business-profile__row" href="mailto:<?php echo esc_attr( $contact_email ); ?>"><?php echo self::icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $contact_email ); ?></a></li>
								<?php endif; ?>
							</ul>
						</section>
					<?php endif; ?>

				</div>
			<?php endif; ?>

			<?php if ( $owner ) : ?>
				<div class="rbn-business-profile__owner">
					<?php echo get_avatar( $owner->ID, 48 ); ?>
					<span>
						<span class="rbn-business-profile__owner-label"><?php esc_html_e( 'Listed by', 'roomworks-business-networking' ); ?></span>
						<span class="rbn-business-profile__owner-name"><?php echo esc_html( $owner->display_name ); ?></span>
					</span>
				</div>
			<?php endif; ?>

		</article>
		<?php
		return ob_get_clean();
	}
}
