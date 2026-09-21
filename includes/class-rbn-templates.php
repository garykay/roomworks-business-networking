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
					<?php echo self::country_field( 'rbn-register-origin-country', 'rbn_origin_country_id', __( 'Country of Origin', 'roomworks-business-networking' ), 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within country_field(). ?>
					<?php echo self::country_field( 'rbn-register-current-country', 'rbn_current_country_id', __( 'Country You Currently Live In', 'roomworks-business-networking' ), 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
		$businesses = RBN_Business_Repository::get_all_for_user( $current_user->ID );
		$jobs       = RBN_Job_Repository::get_all_for_user( $current_user->ID );

		// Which business/job (if any) the form below is editing - read
		// directly here rather than passed in, same as
		// account_deletion_section()'s rbn_confirm_deletion below. Read-only:
		// only chooses which record's data pre-fills the form, never a state
		// change, so no nonce applies - get_by_id_for_user() re-verifies
		// ownership regardless of what this value claims.
		$editing_business_id = isset( $_GET['rbn_edit_business'] ) ? absint( $_GET['rbn_edit_business'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$editing_business     = $editing_business_id ? RBN_Business_Repository::get_by_id_for_user( $editing_business_id, $current_user->ID ) : null;

		$editing_job_id = isset( $_GET['rbn_edit_job'] ) ? absint( $_GET['rbn_edit_job'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$editing_job    = $editing_job_id ? RBN_Job_Repository::get_by_id_for_user( $editing_job_id, $current_user->ID ) : null;

		$tabs = array(
			'profile'     => array(
				'label'   => __( 'Profile', 'roomworks-business-networking' ),
				'content' => self::profile_edit_form( $current_user, $current_url, $notice_code ),
			),
			'communities' => array(
				'label'   => __( 'Communities', 'roomworks-business-networking' ),
				'content' => self::communities_section( $current_user, $current_url, $notice_code ),
			),
			'businesses'  => array(
				'label'   => __( 'Businesses', 'roomworks-business-networking' ),
				'content' => self::businesses_section( $businesses, $editing_business, $current_user, $current_url, $notice_code ),
			),
			'requests'    => array(
				'label'   => __( 'Requests', 'roomworks-business-networking' ),
				'content' => self::jobs_section( $jobs, $editing_job, $current_user, $current_url, $notice_code ),
			),
		);

		// Not offered to administrators at all - see
		// account_deletion_section()'s docblock - so the tab itself is
		// dropped rather than shown with nothing in it.
		$account_content = self::account_deletion_section( $current_user, $current_url, $notice_code );
		if ( '' !== $account_content ) {
			$tabs['account'] = array(
				'label'   => __( 'Account', 'roomworks-business-networking' ),
				'content' => $account_content,
			);
		}

		// The FALLBACK tab to land on once JS turns this into an actual
		// tabbed interface (see initTabs() in view.js) - used only when
		// initTabs()'s own first choice, the panel containing the current
		// URL's #hash target (e.g. "Edit" on a business/request links to
		// #rbn-business-form/#rbn-job-form, each inside its own panel),
		// doesn't apply - there's no hash at all right after a plain form
		// POST redirect, only a ?rbn_notice= query arg. Checked in priority
		// order:
		//
		// 1. Whichever record is currently being edited/confirmed - covers
		//    a GET navigation that has neither a notice nor a matching
		//    in-panel anchor (there isn't one for "Request Account
		//    Deletion", only for the Cancel link that undoes it).
		// 2. The section a just-submitted form's notice belongs to (e.g.
		//    posting a brand new request, which has no ID in the URL for
		//    rule 1 to key off, still lands on "Requests" via its
		//    job_saved notice).
		// 3. The first tab, if neither of the above matched anything.
		//
		// Irrelevant without JS, since every panel is already visible there
		// - nothing to "land on".
		$confirming_deletion = ! empty( $_GET['rbn_confirm_deletion'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, same as account_deletion_section()'s own identical check.

		if ( $editing_business_id ) {
			$active_tab = 'businesses';
		} elseif ( $editing_job_id ) {
			$active_tab = 'requests';
		} elseif ( $confirming_deletion ) {
			$active_tab = 'account';
		} else {
			$section_to_tab = array(
				'profile'          => 'profile',
				'community'        => 'communities',
				'business'         => 'businesses',
				'job'              => 'requests',
				'account_deletion' => 'account',
			);
			$active_tab = isset( $section_to_tab[ RBN_Notices::section( $notice_code ) ] ) ? $section_to_tab[ RBN_Notices::section( $notice_code ) ] : '';
		}

		if ( ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = array_key_first( $tabs );
		}

		ob_start();
		?>
		<div class="rbn-dashboard">
			<?php echo self::profile_header( $current_user, $current_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>

			<div class="rbn-tabs" data-rbn-tabs data-rbn-active-tab="<?php echo esc_attr( $active_tab ); ?>">
				<nav class="rbn-tabs__nav" aria-label="<?php esc_attr_e( 'Dashboard sections', 'roomworks-business-networking' ); ?>">
					<?php foreach ( $tabs as $key => $tab ) : ?>
						<a class="rbn-tabs__tab" href="#rbn-tab-panel-<?php echo esc_attr( $key ); ?>" data-rbn-tab-link="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $tab['label'] ); ?></a>
					<?php endforeach; ?>
				</nav>

				<div class="rbn-tabs__panels">
					<?php foreach ( $tabs as $key => $tab ) : ?>
						<div class="rbn-tabs__panel" id="rbn-tab-panel-<?php echo esc_attr( $key ); ?>" data-rbn-tab-panel="<?php echo esc_attr( $key ); ?>">
							<?php echo $tab['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within each section's own render method. ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * The cover-banner/avatar header above the tabs - a purely decorative
	 * CSS gradient banner (no upload feature), the member's name, their
	 * origin/current-country pairing (the same one their communities are
	 * built from) and bio if set, and the one action that isn't tied to any
	 * particular tab (logging out).
	 */
	private static function profile_header( $current_user, $current_url ) {
		$bio                 = get_user_meta( $current_user->ID, 'description', true );
		$origin_country_id   = RBN_Countries::get_origin_country_id( $current_user->ID );
		$current_country_id  = RBN_Countries::get_current_country_id( $current_user->ID );
		$origin_country      = $origin_country_id ? RBN_Countries::get_by_id( $origin_country_id ) : null;
		$current_country     = $current_country_id ? RBN_Countries::get_by_id( $current_country_id ) : null;

		$location = '';
		if ( $origin_country && $current_country ) {
			/* translators: 1: origin country name, 2: current country name. */
			$location = sprintf( __( '%1$s → %2$s', 'roomworks-business-networking' ), $origin_country->name, $current_country->name );
		} elseif ( $current_country ) {
			$location = $current_country->name;
		} elseif ( $origin_country ) {
			$location = $origin_country->name;
		}

		ob_start();
		?>
		<div class="rbn-profile-header">
			<div class="rbn-profile-header__cover" aria-hidden="true"></div>
			<div class="rbn-profile-header__content">
				<span class="rbn-profile-header__avatar"><?php echo get_avatar( $current_user->ID, 112 ); ?></span>

				<div class="rbn-profile-header__info">
					<h2 class="rbn-profile-header__name"><?php echo esc_html( $current_user->display_name ); ?></h2>
					<?php if ( $location ) : ?>
						<p class="rbn-profile-header__location"><?php echo self::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted markup, see icon(). ?><?php echo esc_html( $location ); ?></p>
					<?php endif; ?>
					<?php if ( $bio ) : ?>
						<p class="rbn-profile-header__bio"><?php echo esc_html( $bio ); ?></p>
					<?php endif; ?>
				</div>

				<p class="rbn-profile-header__actions">
					<a class="rbn-link" href="<?php echo esc_url( wp_logout_url( $current_url ) ); ?>"><?php esc_html_e( 'Log out', 'roomworks-business-networking' ); ?></a>
				</p>
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
		<section class="rbn-delete-account rbn-card" id="rbn-delete-account">
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
					<a class="rbn-link" href="<?php echo esc_url( $current_url . '#rbn-delete-account' ); ?>"><?php esc_html_e( 'Cancel', 'roomworks-business-networking' ); ?></a>
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
		$bio                = get_user_meta( $current_user->ID, 'description', true );
		$phone_number       = get_user_meta( $current_user->ID, 'rbn_phone_number', true );
		$origin_country_id  = RBN_Countries::get_origin_country_id( $current_user->ID );
		$current_country_id = RBN_Countries::get_current_country_id( $current_user->ID );

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
					<label for="rbn-profile-phone-number"><?php esc_html_e( 'Phone Number', 'roomworks-business-networking' ); ?></label>
					<input type="tel" id="rbn-profile-phone-number" name="rbn_phone_number" autocomplete="tel" value="<?php echo esc_attr( $phone_number ); ?>" />
					<span class="rbn-field-note"><?php esc_html_e( 'Optional. Offered as a contact option on your notice board requests - you can hide it on any individual one.', 'roomworks-business-networking' ); ?></span>
				</p>
				<?php echo self::country_field( 'rbn-profile-origin-country', 'rbn_origin_country_id', __( 'Country of Origin', 'roomworks-business-networking' ), $origin_country_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within country_field(). ?>
				<?php echo self::country_field( 'rbn-profile-current-country', 'rbn_current_country_id', __( 'Country You Currently Live In', 'roomworks-business-networking' ), $current_country_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p>
					<button type="submit" class="rbn-button"><?php esc_html_e( 'Save Profile', 'roomworks-business-networking' ); ?></button>
				</p>
			</form>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * "My Communities" (joined, with a Leave button) plus "Available
	 * Communities" (active communities whose destination country matches
	 * the member's current country, not yet joined, with a Join button).
	 * Nothing shown for "available" if the member hasn't set a current
	 * country yet on the form above - there's nothing valid to offer.
	 */
	private static function communities_section( $current_user, $current_url, $notice_code ) {
		$user_id             = $current_user->ID;
		$current_country_id  = RBN_Countries::get_current_country_id( $user_id );
		$joined              = RBN_Community_Memberships::get_communities_for_user( $user_id );
		// wp_list_pluck() returns raw values, which for a $wpdb->get_results()
		// row are strings - cast to int so the strict in_array() comparison
		// below (correctly) matches against $community->id, which is also
		// cast to int.
		$joined_ids          = array_map( 'intval', wp_list_pluck( $joined, 'id' ) );

		$available = $current_country_id ? RBN_Communities::get_for_destination_country( $current_country_id ) : array();
		$available = array_values(
			array_filter(
				$available,
				static function ( $community ) use ( $joined_ids ) {
					return ! in_array( (int) $community->id, $joined_ids, true );
				}
			)
		);

		ob_start();
		?>
		<section class="rbn-communities rbn-card">
			<h3><?php esc_html_e( 'Communities', 'roomworks-business-networking' ); ?></h3>

			<?php echo self::notice_for_section( $notice_code, 'community' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<h4><?php esc_html_e( 'My Communities', 'roomworks-business-networking' ); ?></h4>
			<?php if ( empty( $joined ) ) : ?>
				<p class="rbn-field-note"><?php esc_html_e( "You haven't joined any communities yet.", 'roomworks-business-networking' ); ?></p>
			<?php else : ?>
				<ul class="rbn-community-list">
					<?php foreach ( $joined as $community ) : ?>
						<li>
							<?php echo esc_html( $community->name ); ?>
							<?php echo self::community_action_form( $community->id, RBN_Community_Forms::LEAVE_ACTION, __( 'Leave', 'roomworks-business-networking' ), $current_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<h4><?php esc_html_e( 'Available Communities', 'roomworks-business-networking' ); ?></h4>
			<?php if ( ! $current_country_id ) : ?>
				<p class="rbn-field-note"><?php esc_html_e( 'Set the country you currently live in above to see communities you can join.', 'roomworks-business-networking' ); ?></p>
			<?php elseif ( empty( $available ) ) : ?>
				<p class="rbn-field-note"><?php esc_html_e( 'No communities are available in your current country yet.', 'roomworks-business-networking' ); ?></p>
			<?php else : ?>
				<ul class="rbn-community-list">
					<?php foreach ( $available as $community ) : ?>
						<li>
							<?php echo esc_html( $community->name ); ?>
							<?php echo self::community_action_form( $community->id, RBN_Community_Forms::JOIN_ACTION, __( 'Join', 'roomworks-business-networking' ), $current_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function community_action_form( $community_id, $action, $label, $current_url ) {
		ob_start();
		?>
		<form class="rbn-community-list__form" method="post" action="<?php echo esc_url( $current_url ); ?>">
			<?php wp_nonce_field( $action, 'rbn_community_action_nonce' ); ?>
			<input type="hidden" name="rbn_form_action" value="<?php echo esc_attr( $action ); ?>" />
			<input type="hidden" name="rbn_community_id" value="<?php echo esc_attr( $community_id ); ?>" />
			<input type="hidden" name="rbn_redirect_to" value="<?php echo esc_attr( $current_url ); ?>" />
			<button type="submit" class="rbn-button rbn-button--secondary"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * A country <select>, populated from RBN_Countries::get_all(). A plain
	 * native select rather than a type-ahead (unlike Services/Business
	 * Type): with ~195 options a native select is still fast to use (type
	 * to jump, works without JS) and needed nowhere near as urgently as an
	 * async picker would for the much larger business-category vocabulary.
	 * RBN_REST_Countries already exists for a future JS-enhanced version of
	 * this field without needing new backend work.
	 */
	private static function country_field( $id, $name, $label, $selected_id ) {
		ob_start();
		?>
		<p>
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" required>
				<option value=""><?php esc_html_e( '— Select —', 'roomworks-business-networking' ); ?></option>
				<?php foreach ( RBN_Countries::get_all() as $country ) : ?>
					<option value="<?php echo esc_attr( $country->id ); ?>" <?php selected( $selected_id, $country->id ); ?>>
						<?php echo esc_html( $country->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
		return ob_get_clean();
	}

	/**
	 * The business's Community field. Options are always the member's own
	 * joined communities - never every community - so a member can't
	 * assign their business to one they don't belong to; the save handler
	 * (RBN_Business_Forms) re-checks this server-side regardless, since a
	 * required attribute never stops a crafted request. If the business
	 * already has a community that the member has since left, it's still
	 * included as an option (selected) so saving the form can't silently
	 * change it to something else.
	 */
	private static function business_community_field( array $joined_communities, $current_community_id ) {
		$options = $joined_communities;

		if ( $current_community_id && ! in_array( $current_community_id, wp_list_pluck( $options, 'id' ), true ) ) {
			$current = RBN_Communities::get_by_id( $current_community_id );

			if ( $current ) {
				$options[] = $current;
			}
		}

		// Only reachable when editing an existing business whose owner has
		// no joined communities at all (including one it was never
		// assigned to) - e.g. legacy data from before communities existed.
		// No select is rendered - nothing valid to submit - so the save
		// handler leaves rbn_community_id exactly as it was.
		if ( empty( $options ) ) {
			ob_start();
			?>
			<p class="rbn-field-note"><?php esc_html_e( "This business isn't assigned to a community yet - join one above, then edit this business again to assign it.", 'roomworks-business-networking' ); ?></p>
			<?php
			return ob_get_clean();
		}

		ob_start();
		?>
		<p>
			<label for="rbn-business-community"><?php esc_html_e( 'Community', 'roomworks-business-networking' ); ?></label>
			<select id="rbn-business-community" name="rbn_community_id" required>
				<?php foreach ( $options as $community ) : ?>
					<option value="<?php echo esc_attr( $community->id ); ?>" <?php selected( $current_community_id, $community->id ); ?>>
						<?php echo esc_html( $community->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="rbn-field-note"><?php esc_html_e( 'Which community this business belongs to.', 'roomworks-business-networking' ); ?></span>
		</p>
		<?php
		return ob_get_clean();
	}

	/**
	 * "My Businesses" list (each with an Edit link jumping to the form
	 * below, pre-filled for that one) plus the add/edit form itself. A
	 * member may own several businesses - see RBN_Business_Repository -
	 * so unlike the profile/community sections there's a list here rather
	 * than a single implicit record.
	 */
	private static function businesses_section( array $businesses, $editing_business, $current_user, $current_url, $notice_code ) {
		ob_start();
		?>
		<section class="rbn-my-businesses rbn-card">
			<h3><?php esc_html_e( 'My Businesses', 'roomworks-business-networking' ); ?></h3>
			<?php if ( empty( $businesses ) ) : ?>
				<p class="rbn-field-note"><?php esc_html_e( "You haven't added a business yet - use the form below to add one.", 'roomworks-business-networking' ); ?></p>
			<?php else : ?>
				<ul class="rbn-my-businesses__list">
					<?php foreach ( $businesses as $business ) : ?>
						<?php $community = RBN_Communities::get_by_id( get_post_meta( $business->ID, 'rbn_community_id', true ) ); ?>
						<li>
							<span class="rbn-my-businesses__name"><?php echo esc_html( wp_specialchars_decode( get_the_title( $business ), ENT_QUOTES ) ); ?></span>
							<?php if ( 'pending' === $business->post_status ) : ?>
								<span class="rbn-status rbn-status--pending"><?php esc_html_e( 'Pending review', 'roomworks-business-networking' ); ?></span>
							<?php endif; ?>
							<?php if ( $community ) : ?>
								<span class="rbn-field-note"><?php echo esc_html( $community->name ); ?></span>
							<?php endif; ?>
							<a class="rbn-link" href="<?php echo esc_url( add_query_arg( 'rbn_edit_business', $business->ID, $current_url ) . '#rbn-business-form' ); ?>"><?php esc_html_e( 'Edit', 'roomworks-business-networking' ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php echo self::business_form( $editing_business, $current_user, $current_url, $notice_code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>
		<?php
		return ob_get_clean();
	}

	private static function business_form( $business, $current_user, $current_url, $notice_code ) {
		$joined_communities   = RBN_Community_Memberships::get_communities_for_user( $current_user->ID );
		$current_community_id = $business ? absint( get_post_meta( $business->ID, 'rbn_community_id', true ) ) : 0;

		// A new business can't be created without a community to belong to
		// (spec Section 12) - an existing one can still be edited even if
		// the member has since left every community, so they're never
		// locked out of their own already-saved data.
		if ( ! $business && empty( $joined_communities ) ) {
			ob_start();
			?>
			<section class="rbn-edit-business rbn-card" id="rbn-business-form">
				<h3><?php esc_html_e( 'Add a Business', 'roomworks-business-networking' ); ?></h3>
				<p class="rbn-field-note"><?php esc_html_e( 'Join a community above before adding a business - every business belongs to one.', 'roomworks-business-networking' ); ?></p>
			</section>
			<?php
			return ob_get_clean();
		}

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
		<section class="rbn-edit-business rbn-card" id="rbn-business-form">
			<h3>
				<?php
				echo $business
					/* translators: %s: business name. */
					? esc_html( sprintf( __( 'Edit %s', 'roomworks-business-networking' ), $name ) )
					: esc_html__( 'Add a Business', 'roomworks-business-networking' );
				?>
			</h3>

			<?php echo self::notice_for_section( $notice_code, 'business' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<p class="rbn-field-note"><?php esc_html_e( 'All fields are required except Website.', 'roomworks-business-networking' ); ?></p>

			<form class="rbn-form" method="post" action="<?php echo esc_url( $current_url ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'rbn_save_business', 'rbn_business_nonce' ); ?>
				<input type="hidden" name="rbn_form_action" value="rbn_save_business" />
				<input type="hidden" name="rbn_business_id" value="<?php echo esc_attr( $business ? $business->ID : 0 ); ?>" />
				<input type="hidden" name="rbn_redirect_to" value="<?php echo esc_attr( $current_url ); ?>" />

				<?php echo self::business_community_field( $joined_communities, $current_community_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>

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
	 *
	 * Also reused by job_form() for the request form's Category field (see
	 * RBN_Taxonomy_Business_Category's class docblock) - $id_prefix/
	 * $field_name let that second instance render with its own unique
	 * element IDs and a different form field name, since both forms can be
	 * on the page at once (the member dashboard) and duplicate IDs would
	 * break the <label for> association on whichever instance rendered
	 * second. The JS behind data-rbn-category-field itself already scopes
	 * everything relative to each field's own wrapper (see
	 * user-profile/view.js's initCategoryField()), so it needs no changes to
	 * support more than one instance - only the id/name attributes did.
	 * $label lets that second instance say "Category" instead of "Business
	 * Type" - same taxonomy/options either way, just a less confusing label
	 * for someone posting a request rather than a business.
	 */
	private static function business_type_field( array $selected_categories, $categories, $id_prefix = 'business', $field_name = 'rbn_business_category', $label = null ) {
		$category_id = ! empty( $selected_categories ) ? (int) $selected_categories[0]->term_id : 0;

		if ( is_wp_error( $categories ) ) {
			$categories = array();
		}

		if ( null === $label ) {
			$label = __( 'Business Type', 'roomworks-business-networking' );
		}

		$select_id = 'rbn-' . $id_prefix . '-category';
		$new_id    = 'rbn-' . $id_prefix . '-category-new';

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
			<label for="<?php echo esc_attr( $select_id ); ?>"><?php echo esc_html( $label ); ?></label>
			<select id="<?php echo esc_attr( $select_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" data-rbn-category-select required>
				<option value=""><?php esc_html_e( '— Select —', 'roomworks-business-networking' ); ?></option>
				<?php foreach ( $categories as $category ) : ?>
					<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $category_id, $category->term_id ); ?>>
						<?php echo esc_html( wp_specialchars_decode( $category->name, ENT_QUOTES ) ); ?>
					</option>
				<?php endforeach; ?>
				<option value="__other__" data-rbn-other-option><?php esc_html_e( 'Other — add a new business type', 'roomworks-business-networking' ); ?></option>
			</select>

			<span class="rbn-category-add" data-rbn-category-add hidden>
				<label for="<?php echo esc_attr( $new_id ); ?>" class="rbn-visually-hidden"><?php esc_html_e( 'New business type', 'roomworks-business-networking' ); ?></label>
				<input
					type="text"
					id="<?php echo esc_attr( $new_id ); ?>"
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
	 * "My Requests" (each with an Edit link jumping to the form below,
	 * pre-filled for that one) plus the add/edit form itself - same shape as
	 * businesses_section() above, but with no pending-review badge:
	 * requests auto-publish (see RBN_Job_Forms).
	 */
	private static function jobs_section( array $jobs, $editing_job, $current_user, $current_url, $notice_code ) {
		ob_start();
		?>
		<section class="rbn-my-jobs rbn-card">
			<h3><?php esc_html_e( 'My Requests', 'roomworks-business-networking' ); ?></h3>
			<?php if ( empty( $jobs ) ) : ?>
				<p class="rbn-field-note"><?php esc_html_e( "You haven't posted a request yet - use the form below to add one.", 'roomworks-business-networking' ); ?></p>
			<?php else : ?>
				<ul class="rbn-my-jobs__list">
					<?php foreach ( $jobs as $job ) : ?>
						<li>
							<span class="rbn-my-jobs__title"><?php echo esc_html( wp_specialchars_decode( get_the_title( $job ), ENT_QUOTES ) ); ?></span>
							<a class="rbn-link" href="<?php echo esc_url( add_query_arg( 'rbn_edit_job', $job->ID, $current_url ) . '#rbn-job-form' ); ?>"><?php esc_html_e( 'Edit', 'roomworks-business-networking' ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php echo self::job_form( $editing_job, $current_user, $current_url, $notice_code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>
		<?php
		return ob_get_clean();
	}

	private static function job_form( $job, $current_user, $current_url, $notice_code ) {
		$current_country_id   = RBN_Countries::get_current_country_id( $current_user->ID );
		$eligible_communities = RBN_Community_Memberships::get_communities_for_user_in_country( $current_user->ID, $current_country_id );

		// A new request can't be posted without an eligible community to
		// belong to - an existing one can still be edited even if the
		// member has since moved country and has none left, same reasoning
		// as business_form()'s equivalent gate.
		if ( ! $job && empty( $eligible_communities ) ) {
			ob_start();
			?>
			<section class="rbn-edit-job rbn-card" id="rbn-job-form">
				<h3><?php esc_html_e( 'Post a Request', 'roomworks-business-networking' ); ?></h3>
				<p class="rbn-field-note"><?php esc_html_e( 'Join a community in your current country above before posting a request.', 'roomworks-business-networking' ); ?></p>
			</section>
			<?php
			return ob_get_clean();
		}

		$title       = $job ? wp_specialchars_decode( get_the_title( $job ), ENT_QUOTES ) : '';
		$description = $job ? $job->post_content : '';
		// Category reuses the same Business Type taxonomy businesses use -
		// see RBN_Taxonomy_Business_Category's class docblock for why - so
		// this shares business_type_field() (including its member-facing
		// "Other, add a new type" flow) rather than a separate field.
		$selected_categories = $job ? wp_get_post_terms( $job->ID, RBN_Taxonomy_Business_Category::TAXONOMY ) : array();
		if ( is_wp_error( $selected_categories ) ) {
			$selected_categories = array();
		}
		$urgency             = $job ? get_post_meta( $job->ID, 'rbn_urgency', true ) : '';
		$town_city           = $job ? get_post_meta( $job->ID, 'rbn_town_city', true ) : '';
		$county_region       = $job ? get_post_meta( $job->ID, 'rbn_county_region', true ) : '';
		$budget              = $job ? get_post_meta( $job->ID, 'rbn_budget', true ) : '';
		$closing_date        = $job ? get_post_meta( $job->ID, 'rbn_closing_date', true ) : '';
		$hide_phone          = $job ? (bool) get_post_meta( $job->ID, 'rbn_hide_phone', true ) : false;
		// Multi-valued meta (single => false) - every rbn_community_id row
		// this request is currently posted to, see RBN_Post_Type_Job.
		$selected_community_ids = $job ? array_map( 'intval', (array) get_post_meta( $job->ID, 'rbn_community_id', false ) ) : array();

		$categories = get_terms(
			array(
				'taxonomy'   => RBN_Taxonomy_Business_Category::TAXONOMY,
				'hide_empty' => false,
			)
		);

		$phone_number = get_user_meta( $current_user->ID, 'rbn_phone_number', true );

		ob_start();
		?>
		<section class="rbn-edit-job rbn-card" id="rbn-job-form">
			<h3>
				<?php
				echo $job
					/* translators: %s: request title. */
					? esc_html( sprintf( __( 'Edit %s', 'roomworks-business-networking' ), $title ) )
					: esc_html__( 'Post a Request', 'roomworks-business-networking' );
				?>
			</h3>

			<?php echo self::notice_for_section( $notice_code, 'job' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<p class="rbn-field-note"><?php esc_html_e( 'All fields are required except Budget and Closing Date.', 'roomworks-business-networking' ); ?></p>

			<form class="rbn-form" method="post" action="<?php echo esc_url( $current_url ); ?>">
				<?php wp_nonce_field( RBN_Job_Forms::ACTION, 'rbn_job_nonce' ); ?>
				<input type="hidden" name="rbn_form_action" value="<?php echo esc_attr( RBN_Job_Forms::ACTION ); ?>" />
				<input type="hidden" name="rbn_job_id" value="<?php echo esc_attr( $job ? $job->ID : 0 ); ?>" />
				<input type="hidden" name="rbn_redirect_to" value="<?php echo esc_attr( $current_url ); ?>" />

				<?php echo self::job_community_field( $eligible_communities, $selected_community_ids ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>

				<p>
					<label for="rbn-job-title"><?php esc_html_e( 'Title', 'roomworks-business-networking' ); ?></label>
					<input type="text" id="rbn-job-title" name="rbn_job_title" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'e.g. Need a new boiler installed', 'roomworks-business-networking' ); ?>" required />
				</p>

				<?php
				echo self::business_type_field( $selected_categories, $categories, 'job', 'rbn_job_category', __( 'Category', 'roomworks-business-networking' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within business_type_field().
				?>
				<?php echo self::urgency_field( $urgency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>

				<p>
					<label for="rbn-job-description"><?php esc_html_e( 'Description', 'roomworks-business-networking' ); ?></label>
					<textarea id="rbn-job-description" name="rbn_job_description" rows="4" required><?php echo esc_textarea( $description ); ?></textarea>
				</p>

				<div class="rbn-form__grid">
					<p>
						<label for="rbn-job-town-city"><?php esc_html_e( 'Town / City', 'roomworks-business-networking' ); ?></label>
						<input type="text" id="rbn-job-town-city" name="rbn_town_city" value="<?php echo esc_attr( $town_city ); ?>" required />
					</p>
					<p>
						<label for="rbn-job-county-region"><?php esc_html_e( 'County / Region', 'roomworks-business-networking' ); ?></label>
						<input type="text" id="rbn-job-county-region" name="rbn_county_region" value="<?php echo esc_attr( $county_region ); ?>" required />
					</p>
					<p>
						<label for="rbn-job-budget"><?php esc_html_e( 'Budget', 'roomworks-business-networking' ); ?></label>
						<input type="text" id="rbn-job-budget" name="rbn_budget" value="<?php echo esc_attr( $budget ); ?>" placeholder="<?php esc_attr_e( 'e.g. 200 - 400', 'roomworks-business-networking' ); ?>" />
						<span class="rbn-field-note"><?php esc_html_e( 'Numbers only - the currency symbol for the country you currently live in is added automatically.', 'roomworks-business-networking' ); ?></span>
					</p>
					<p>
						<label for="rbn-job-closing-date"><?php esc_html_e( 'Closing Date', 'roomworks-business-networking' ); ?></label>
						<input type="date" id="rbn-job-closing-date" name="rbn_closing_date" value="<?php echo esc_attr( $closing_date ); ?>" />
					</p>
				</div>

				<?php if ( $phone_number ) : ?>
					<p class="rbn-form__checkbox">
						<label>
							<input type="checkbox" name="rbn_hide_phone" value="1" <?php checked( $hide_phone ); ?> />
							<?php esc_html_e( 'Hide my phone number on this request', 'roomworks-business-networking' ); ?>
						</label>
						<span class="rbn-field-note"><?php esc_html_e( 'Your account email is always shown as contact; your profile phone number is shown too unless you tick this.', 'roomworks-business-networking' ); ?></span>
					</p>
				<?php else : ?>
					<p class="rbn-field-note"><?php esc_html_e( 'Only your account email will be shown as contact - add a phone number on your profile above to also offer that.', 'roomworks-business-networking' ); ?></p>
				<?php endif; ?>

				<p>
					<button type="submit" class="rbn-button">
						<?php
						echo $job
							? esc_html__( 'Save Changes', 'roomworks-business-networking' )
							: esc_html__( 'Post Request', 'roomworks-business-networking' );
						?>
					</button>
				</p>
			</form>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * The job's Communities field - a checkbox group, not a required single
	 * select like business_community_field(): a job may be posted to more
	 * than one eligible community at once, and none-checked is a valid
	 * submission (RBN_Job_Forms defaults it to the oldest-joined one), so
	 * nothing here is marked required. Options are the member's eligible
	 * communities (joined AND in their current country - see
	 * RBN_Community_Memberships::get_communities_for_user_in_country()),
	 * plus - same reasoning as business_community_field() - any community
	 * this job is already assigned to even if it's no longer eligible, so
	 * saving the form can't silently drop it.
	 */
	private static function job_community_field( array $eligible_communities, array $selected_community_ids ) {
		$options = $eligible_communities;
		// int-cast - wp_list_pluck() here returns whatever type each
		// community row's ->id property already is (a string, straight out
		// of a $wpdb->get_results() row), while $selected_community_ids is
		// always already int (see job_form()'s call site) - without this,
		// the strict in_array() below never matches, and every eligible
		// community the job is already assigned to gets appended a second
		// time as if it weren't already an option.
		$option_ids = array_map( 'intval', wp_list_pluck( $options, 'id' ) );

		foreach ( $selected_community_ids as $community_id ) {
			if ( in_array( $community_id, $option_ids, true ) ) {
				continue;
			}

			$community = RBN_Communities::get_by_id( $community_id );

			if ( $community ) {
				$options[] = $community;
			}
		}

		// Only reachable when editing an existing job whose owner has no
		// eligible community left at all (e.g. they've since moved
		// country) - no checkboxes are rendered, so the save handler
		// leaves this job's community assignment exactly as it was.
		if ( empty( $options ) ) {
			ob_start();
			?>
			<p class="rbn-field-note"><?php esc_html_e( "This request isn't assigned to a community you're currently eligible for - join one in your current country above, then edit this listing again to reassign it.", 'roomworks-business-networking' ); ?></p>
			<?php
			return ob_get_clean();
		}

		ob_start();
		?>
		<fieldset class="rbn-job-community-field rbn-form__field">
			<legend><?php esc_html_e( 'Communities', 'roomworks-business-networking' ); ?></legend>
			<?php foreach ( $options as $community ) : ?>
				<p class="rbn-form__checkbox">
					<label>
						<input type="checkbox" name="rbn_communities[]" value="<?php echo esc_attr( $community->id ); ?>" <?php checked( in_array( (int) $community->id, $selected_community_ids, true ) ); ?> />
						<?php echo esc_html( $community->name ); ?>
					</label>
				</p>
			<?php endforeach; ?>
			<span class="rbn-field-note"><?php esc_html_e( 'Which of your communities this request is posted to. Leave everything unchecked to post it to your main (oldest-joined) community automatically.', 'roomworks-business-networking' ); ?></span>
		</fieldset>
		<?php
		return ob_get_clean();
	}

	private static function urgency_field( $selected ) {
		ob_start();
		?>
		<p>
			<label for="rbn-job-urgency"><?php esc_html_e( 'Urgency', 'roomworks-business-networking' ); ?></label>
			<select id="rbn-job-urgency" name="rbn_urgency" required>
				<option value=""><?php esc_html_e( '— Select —', 'roomworks-business-networking' ); ?></option>
				<?php foreach ( RBN_Post_Type_Job::URGENCY_OPTIONS as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
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
	 * The notice board's filter form - same shape as directory_filters(),
	 * with Urgency in place of Service and Category meaning
	 * RBN_Taxonomy_Business_Category (same taxonomy businesses use - see
	 * that class's docblock) rather than a separate vocabulary.
	 */
	public static function notice_board_filters( $filter_options, $filters, $current_url ) {
		ob_start();
		?>
		<form class="rbn-form rbn-directory__filters rbn-card" method="get" action="<?php echo esc_url( $current_url ); ?>" data-rbn-filter-form>
			<p>
				<label for="rbn-job-filter-search"><?php esc_html_e( 'Search', 'roomworks-business-networking' ); ?></label>
				<input type="search" id="rbn-job-filter-search" name="rbn_search" value="<?php echo esc_attr( $filters['search'] ); ?>" />
			</p>

			<p>
				<label for="rbn-job-filter-category"><?php esc_html_e( 'Category', 'roomworks-business-networking' ); ?></label>
				<select id="rbn-job-filter-category" name="rbn_category">
					<option value=""><?php esc_html_e( 'All Categories', 'roomworks-business-networking' ); ?></option>
					<?php foreach ( $filter_options['categories'] as $category ) : ?>
						<option value="<?php echo esc_attr( $category['slug'] ); ?>" <?php selected( $filters['category'], $category['slug'] ); ?>>
							<?php echo esc_html( $category['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<label for="rbn-job-filter-urgency"><?php esc_html_e( 'Urgency', 'roomworks-business-networking' ); ?></label>
				<select id="rbn-job-filter-urgency" name="rbn_urgency">
					<option value=""><?php esc_html_e( 'Any Urgency', 'roomworks-business-networking' ); ?></option>
					<?php foreach ( $filter_options['urgency_options'] as $urgency ) : ?>
						<option value="<?php echo esc_attr( $urgency['value'] ); ?>" <?php selected( $filters['urgency'], $urgency['value'] ); ?>>
							<?php echo esc_html( $urgency['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<label for="rbn-job-filter-location"><?php esc_html_e( 'Location', 'roomworks-business-networking' ); ?></label>
				<input type="text" id="rbn-job-filter-location" name="rbn_location" value="<?php echo esc_attr( $filters['location'] ); ?>" placeholder="<?php esc_attr_e( 'Town or county', 'roomworks-business-networking' ); ?>" />
			</p>

			<p class="rbn-directory__filter-actions" data-rbn-filter-actions>
				<button type="submit" class="rbn-button"><?php esc_html_e( 'Filter', 'roomworks-business-networking' ); ?></button>
				<?php if ( $filters['category'] || $filters['urgency'] || $filters['location'] || $filters['search'] ) : ?>
					<a class="rbn-directory__clear rbn-link" href="<?php echo esc_url( $current_url ); ?>" data-rbn-filter-clear><?php esc_html_e( 'Clear filters', 'roomworks-business-networking' ); ?></a>
				<?php endif; ?>
			</p>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function notice_board_results( $results ) {
		ob_start();

		if ( empty( $results['items'] ) ) {
			?>
			<p class="rbn-directory__empty"><?php esc_html_e( 'No requests found.', 'roomworks-business-networking' ); ?></p>
			<?php
		} else {
			?>
			<div class="rbn-directory__grid">
				<?php foreach ( $results['items'] as $item ) : ?>
					<?php echo self::job_card( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>
				<?php endforeach; ?>
			</div>
			<?php
		}

		return ob_get_clean();
	}

	private static function job_card( $item ) {
		ob_start();
		?>
		<article class="rbn-job-card rbn-card">
			<div class="rbn-job-card__body">
				<?php if ( $item['category'] ) : ?>
					<p class="rbn-job-card__category"><?php echo esc_html( $item['category'] ); ?></p>
				<?php endif; ?>

				<h3 class="rbn-job-card__title">
					<a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
				</h3>

				<?php if ( $item['urgency'] ) : ?>
					<p class="rbn-job-card__urgency"><?php echo esc_html( $item['urgency'] ); ?></p>
				<?php endif; ?>

				<?php if ( $item['excerpt'] ) : ?>
					<p class="rbn-job-card__excerpt"><?php echo esc_html( $item['excerpt'] ); ?></p>
				<?php endif; ?>

				<?php $location = trim( implode( ', ', array_filter( array( $item['town_city'], $item['county_region'] ) ) ) ); ?>
				<?php if ( $location ) : ?>
					<p class="rbn-job-card__location"><?php echo self::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted markup, see icon(). ?><?php echo esc_html( $location ); ?></p>
				<?php endif; ?>

				<?php if ( $item['budget'] ) : ?>
					<p class="rbn-job-card__budget"><?php echo esc_html( $item['budget'] ); ?></p>
				<?php endif; ?>

				<?php if ( $item['closing_date'] ) : ?>
					<p class="rbn-job-card__closing">
						<?php
						printf(
							/* translators: %s: closing date. */
							esc_html__( 'Closes %s', 'roomworks-business-networking' ),
							esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item['closing_date'] ) ) )
						);
						?>
					</p>
				<?php endif; ?>

				<p class="rbn-job-card__link">
					<a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php esc_html_e( 'View Details', 'roomworks-business-networking' ); ?></a>
				</p>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Same shape as directory_pagination(), reusing pagination_range() -
	 * only the filter query args carried through page links differ (urgency
	 * instead of service).
	 */
	public static function notice_board_pagination( $results, $filters, $current_url ) {
		if ( $results['total_pages'] <= 1 ) {
			return '';
		}

		ob_start();
		?>
		<nav class="rbn-pagination" aria-label="<?php esc_attr_e( 'Notice board pagination', 'roomworks-business-networking' ); ?>">
			<?php foreach ( self::pagination_range( $results['page'], $results['total_pages'] ) as $page ) : ?>
				<?php if ( '...' === $page ) : ?>
					<span class="rbn-pagination__ellipsis" aria-hidden="true">&hellip;</span>
					<?php continue; ?>
				<?php endif; ?>
				<?php
				$page_args = array_filter(
					array(
						'rbn_category' => $filters['category'],
						'rbn_urgency'  => $filters['urgency'],
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

	/**
	 * Everything the default single-request template doesn't already render
	 * (title/content) - urgency, category, location, budget, closing date,
	 * contact, poster. Appended via RBN_Post_Type_Job::append_details_to_content()
	 * rather than a dedicated Site-Editor block/template like
	 * business_profile() above needs - see that method's docblock for why.
	 *
	 * Contact always includes the poster's account email; phone is only
	 * included if they've set one on their profile AND haven't hidden it on
	 * this specific request (rbn_hide_phone) - see RBN_Job_Forms.
	 */
	public static function job_profile( WP_Post $job ) {
		$categories  = get_the_terms( $job, RBN_Taxonomy_Business_Category::TAXONOMY );
		$urgency_key = get_post_meta( $job->ID, 'rbn_urgency', true );
		$urgency     = isset( RBN_Post_Type_Job::URGENCY_OPTIONS[ $urgency_key ] ) ? RBN_Post_Type_Job::URGENCY_OPTIONS[ $urgency_key ] : '';

		$town_city     = get_post_meta( $job->ID, 'rbn_town_city', true );
		$county_region = get_post_meta( $job->ID, 'rbn_county_region', true );
		$budget        = RBN_Job_Query::format_budget( get_post_meta( $job->ID, 'rbn_budget', true ), $job->post_author );
		$closing_date  = get_post_meta( $job->ID, 'rbn_closing_date', true );
		$hide_phone    = (bool) get_post_meta( $job->ID, 'rbn_hide_phone', true );

		$owner       = get_userdata( $job->post_author );
		$owner_email = $owner ? $owner->user_email : '';
		$owner_phone = ( $owner && ! $hide_phone ) ? get_user_meta( $owner->ID, 'rbn_phone_number', true ) : '';

		$location = trim( implode( ', ', array_filter( array( $town_city, $county_region ) ) ) );

		ob_start();
		?>
		<div class="rbn-job-profile">

			<?php if ( 'publish' !== $job->post_status && current_user_can( 'edit_post', $job->ID ) ) : ?>
				<p class="rbn-notice rbn-notice--error" role="status">
					<?php esc_html_e( "This request isn't visible to other members yet.", 'roomworks-business-networking' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $urgency || ( $categories && ! is_wp_error( $categories ) && ! empty( $categories ) ) ) : ?>
				<p class="rbn-job-profile__badges">
					<?php if ( $categories && ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
						<span class="rbn-job-profile__badge"><?php echo esc_html( wp_specialchars_decode( $categories[0]->name, ENT_QUOTES ) ); ?></span>
					<?php endif; ?>
					<?php if ( $urgency ) : ?>
						<span class="rbn-job-profile__badge"><?php echo esc_html( $urgency ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php if ( $location || $budget || $closing_date || $owner_email || $owner_phone ) : ?>
				<div class="rbn-job-profile__grid">

					<?php if ( $location || $budget || $closing_date ) : ?>
						<section class="rbn-job-profile__card">
							<h2 class="rbn-job-profile__card-title"><?php esc_html_e( 'Details', 'roomworks-business-networking' ); ?></h2>
							<?php if ( $location ) : ?>
								<p class="rbn-job-profile__row"><?php echo self::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $location ); ?></p>
							<?php endif; ?>
							<?php if ( $budget ) : ?>
								<p class="rbn-job-profile__row-label"><?php esc_html_e( 'Budget', 'roomworks-business-networking' ); ?></p>
								<p class="rbn-job-profile__row"><?php echo esc_html( $budget ); ?></p>
							<?php endif; ?>
							<?php if ( $closing_date ) : ?>
								<p class="rbn-job-profile__row rbn-job-profile__row--muted">
									<?php
									printf(
										/* translators: %s: closing date. */
										esc_html__( 'Closes %s', 'roomworks-business-networking' ),
										esc_html( date_i18n( get_option( 'date_format' ), strtotime( $closing_date ) ) )
									);
									?>
								</p>
							<?php endif; ?>
						</section>
					<?php endif; ?>

					<?php if ( $owner_email || $owner_phone ) : ?>
						<section class="rbn-job-profile__card">
							<h2 class="rbn-job-profile__card-title"><?php esc_html_e( 'Contact', 'roomworks-business-networking' ); ?></h2>
							<ul class="rbn-job-profile__contact-list">
								<?php if ( $owner_email ) : ?>
									<li><a class="rbn-job-profile__row" href="mailto:<?php echo esc_attr( $owner_email ); ?>"><?php echo self::icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $owner_email ); ?></a></li>
								<?php endif; ?>
								<?php if ( $owner_phone ) : ?>
									<li><a class="rbn-job-profile__row" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $owner_phone ) ); ?>"><?php echo self::icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $owner_phone ); ?></a></li>
								<?php endif; ?>
							</ul>
						</section>
					<?php endif; ?>

				</div>
			<?php endif; ?>

			<?php if ( $owner ) : ?>
				<div class="rbn-job-profile__owner">
					<?php echo get_avatar( $owner->ID, 48 ); ?>
					<span>
						<span class="rbn-job-profile__owner-label"><?php esc_html_e( 'Posted by', 'roomworks-business-networking' ); ?></span>
						<span class="rbn-job-profile__owner-name"><?php echo esc_html( $owner->display_name ); ?></span>
					</span>
				</div>
			<?php endif; ?>

		</div>
		<?php
		return ob_get_clean();
	}
}
