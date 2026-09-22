<?php
/**
 * wp-admin "Countries" screen: lets an admin correct or fill in the noun-form
 * demonym (e.g. "South African"/"South Africans") RBN_Countries seeds for
 * each country, and is the extension point when the seeded list comes up
 * short (a territory with a contested or unseeded demonym, or a country an
 * admin wants worded differently for this network). Used by
 * RBN_Demonym_Shortcode and anywhere else on the front end that needs a
 * country's demonym rather than its bare name.
 *
 * manage_options-gated, same as RBN_Settings and RBN_Communities_Admin.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Countries_Admin {

	const PAGE_SLUG     = 'rbn-countries';
	const SAVE_ACTION   = 'rbn_save_demonyms';
	const RESEED_ACTION = 'rbn_reseed_demonym_defaults';

	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . RBN_Post_Type_Business::POST_TYPE,
			__( 'Countries', 'roomworks-business-networking' ),
			__( 'Countries', 'roomworks-business-networking' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Handles the bulk-save POST before any output, so it can redirect
	 * afterwards - mirrors RBN_Communities_Admin's pattern.
	 */
	public static function maybe_handle_request() {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only routes to the handler; the handler verifies its own nonce before acting.
			return;
		}

		if ( isset( $_POST['rbn_form_action'] ) && self::SAVE_ACTION === $_POST['rbn_form_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified inside handle_save().
			self::handle_save();
		}

		if ( isset( $_GET['action'] ) && self::RESEED_ACTION === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified inside handle_reseed().
			self::handle_reseed();
		}
	}

	/**
	 * Re-fills any blank demonym with RBN_Countries's built-in default -
	 * never overwrites a value an admin has already set (see
	 * RBN_Countries::seed_defaults()). Exists both as a normal recovery
	 * action (an admin accidentally clears a field) and as the way an
	 * already-seeded install picks up new built-in defaults added in a
	 * later plugin update, since the automatic re-seed on schema upgrade
	 * only fires once, the moment the version bump is first detected.
	 */
	private static function handle_reseed() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'roomworks-business-networking' ) );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::RESEED_ACTION ) ) {
			wp_die( esc_html__( 'Invalid request.', 'roomworks-business-networking' ) );
		}

		RBN_Countries::seed_defaults();

		self::redirect_with_notice( __( 'Missing demonyms restored from the built-in defaults.', 'roomworks-business-networking' ) );
	}

	private static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'roomworks-business-networking' ) );
		}

		$nonce = isset( $_POST['rbn_countries_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_countries_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::SAVE_ACTION ) ) {
			wp_die( esc_html__( 'Your session expired. Please try again.', 'roomworks-business-networking' ) );
		}

		$demonyms = isset( $_POST['rbn_demonyms'] ) && is_array( $_POST['rbn_demonyms'] ) ? wp_unslash( $_POST['rbn_demonyms'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- unslashed above; each value is sanitized individually below.

		foreach ( $demonyms as $country_id => $fields ) {
			$singular = isset( $fields['singular'] ) ? sanitize_text_field( $fields['singular'] ) : '';
			$plural   = isset( $fields['plural'] ) ? sanitize_text_field( $fields['plural'] ) : '';

			RBN_Countries::update_demonyms( absint( $country_id ), $singular, $plural );
		}

		self::redirect_with_notice( __( 'Demonyms saved.', 'roomworks-business-networking' ) );
	}

	private static function redirect_with_notice( $message ) {
		wp_safe_redirect(
			add_query_arg(
				array( 'rbn_notice' => rawurlencode( $message ) ),
				self::page_url()
			)
		);
		exit;
	}

	private static function page_url() {
		return admin_url( 'edit.php?post_type=' . RBN_Post_Type_Business::POST_TYPE . '&page=' . self::PAGE_SLUG );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Countries', 'roomworks-business-networking' ); ?></h1>
			<p>
				<?php esc_html_e( 'Where the singular/plural demonym used in front-end copy comes from - e.g. "South African" / "South Africans" for South Africa. Correct an entry or fill in a blank one below; blanks fall back to the plain country name on the front end.', 'roomworks-business-networking' ); ?>
			</p>
			<?php self::render_notice(); ?>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', self::RESEED_ACTION, self::page_url() ), self::RESEED_ACTION ) ); ?>">
					<?php esc_html_e( 'Restore Missing Defaults', 'roomworks-business-networking' ); ?>
				</a>
				<span class="description"><?php esc_html_e( 'Fills in any blank demonym below from the built-in list. Never overwrites one you\'ve already set.', 'roomworks-business-networking' ); ?></span>
			</p>
			<?php self::render_form(); ?>
			<?php self::render_shortcode_reference(); ?>
		</div>
		<?php
	}

	/**
	 * Usage reference for [rbn_demonym]/[rbn_country] (RBN_Country_Shortcodes)
	 * - shown here, not just in README.md, since this is where an admin is
	 * already looking at the demonym data those shortcodes read from, and is
	 * the natural place to learn how to actually use it in a page's copy.
	 */
	private static function render_shortcode_reference() {
		?>
		<h2><?php esc_html_e( 'Shortcodes', 'roomworks-business-networking' ); ?></h2>
		<div class="card" style="max-width: 800px;">
			<p>
				<?php esc_html_e( 'Drop a country name or demonym into a Heading/Paragraph block (or any other content) so it stays correct without hand-editing every occurrence.', 'roomworks-business-networking' ); ?>
			</p>
			<p>
				<code>[rbn_demonym]</code> &mdash; <?php esc_html_e( 'the noun-form demonym for a country, e.g. "South African" / "South Africans" (not the adjective).', 'roomworks-business-networking' ); ?><br />
				<code>[rbn_country]</code> &mdash; <?php esc_html_e( 'the plain country name, e.g. "United Kingdom".', 'roomworks-business-networking' ); ?>
			</p>
			<p><?php esc_html_e( 'Both resolve which country the same way:', 'roomworks-business-networking' ); ?></p>
			<table class="widefat striped" style="margin-bottom: 1em;">
				<thead>
					<tr>
						<th style="width: 220px;"><?php esc_html_e( 'Attribute', 'roomworks-business-networking' ); ?></th>
						<th><?php esc_html_e( 'Effect', 'roomworks-business-networking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>country="ZA"</code> <?php esc_html_e( 'or', 'roomworks-business-networking' ); ?> <code>country="27"</code></td>
						<td><?php esc_html_e( 'Explicit override - an ISO 3166-1 alpha-2 code or a country ID. Takes priority over source.', 'roomworks-business-networking' ); ?></td>
					</tr>
					<tr>
						<td><code>source="network"</code> <?php esc_html_e( '(default)', 'roomworks-business-networking' ); ?></td>
						<td>
							<?php
							printf(
								/* translators: %s: URL of the Directory Settings screen. */
								wp_kses(
									__( 'The Network Origin Country setting (<a href="%s">Settings &rarr; Branding</a>) - which country this deployment represents.', 'roomworks-business-networking' ),
									array( 'a' => array( 'href' => array() ) )
								),
								esc_url( admin_url( 'edit.php?post_type=' . RBN_Post_Type_Business::POST_TYPE . '&page=' . RBN_Settings::PAGE_SLUG ) )
							);
							?>
						</td>
					</tr>
					<tr>
						<td><code>source="viewer_origin"</code></td>
						<td><?php esc_html_e( "The logged-in viewer's own Country of Origin profile field, falling back to source=\"network\" when logged out or not set.", 'roomworks-business-networking' ); ?></td>
					</tr>
					<tr>
						<td><code>source="viewer_current"</code></td>
						<td><?php esc_html_e( 'The logged-in viewer\'s own Country You Currently Live In profile field, falling back to the Default Destination Country setting (same Branding section) when logged out or not set.', 'roomworks-business-networking' ); ?></td>
					</tr>
				</tbody>
			</table>
			<p>
				<?php
				echo wp_kses(
					__( '<code>[rbn_demonym]</code> also takes <code>plural="1"</code> (default <code>"0"</code>, i.e. singular). Both take <code>case="upper"</code>, <code>"lower"</code>, or <code>"title"</code> (default: as stored below).', 'roomworks-business-networking' ),
					array( 'code' => array() )
				);
				?>
			</p>
			<p><strong><?php esc_html_e( 'Examples', 'roomworks-business-networking' ); ?></strong></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Shortcode', 'roomworks-business-networking' ); ?></th>
						<th><?php esc_html_e( 'Renders as', 'roomworks-business-networking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>Built by [rbn_demonym plural="1"], for [rbn_demonym plural="1"]</code></td>
						<td><?php esc_html_e( 'Built by South Africans, for South Africans', 'roomworks-business-networking' ); ?></td>
					</tr>
					<tr>
						<td><code>ABOUT [rbn_demonym case="upper"] NETWORK</code></td>
						<td><?php esc_html_e( 'ABOUT SOUTH AFRICAN NETWORK', 'roomworks-business-networking' ); ?></td>
					</tr>
					<tr>
						<td><code>...business owners across the [rbn_country source="viewer_current"] come together...</code></td>
						<td><?php esc_html_e( '...across the United Kingdom... (or the viewer\'s own country, once set)', 'roomworks-business-networking' ); ?></td>
					</tr>
					<tr>
						<td><code>Fellow [rbn_demonym source="viewer_origin" plural="1"]</code></td>
						<td><?php esc_html_e( 'Fellow South Africans (for a member whose Country of Origin is South Africa)', 'roomworks-business-networking' ); ?></td>
					</tr>
					<tr>
						<td><code>[rbn_demonym country="NG" plural="1"] and [rbn_country country="GB"]</code></td>
						<td><?php esc_html_e( 'Nigerians and United Kingdom', 'roomworks-business-networking' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function render_notice() {
		if ( empty( $_GET['rbn_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of a message set by our own redirect above.
			return;
		}

		$message = sanitize_text_field( wp_unslash( $_GET['rbn_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	private static function render_form() {
		$countries = RBN_Countries::get_all();
		?>
		<p>
			<input type="search" id="rbn-country-filter" class="regular-text" placeholder="<?php esc_attr_e( 'Filter by country name…', 'roomworks-business-networking' ); ?>" />
		</p>
		<form method="post" action="<?php echo esc_url( self::page_url() ); ?>">
			<?php wp_nonce_field( self::SAVE_ACTION, 'rbn_countries_nonce' ); ?>
			<input type="hidden" name="rbn_form_action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>" />
			<?php submit_button( __( 'Save Demonyms', 'roomworks-business-networking' ) ); ?>
			<table class="wp-list-table widefat fixed striped" id="rbn-countries-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Country', 'roomworks-business-networking' ); ?></th>
						<th><?php esc_html_e( 'Demonym (singular)', 'roomworks-business-networking' ); ?></th>
						<th><?php esc_html_e( 'Demonym (plural)', 'roomworks-business-networking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $countries as $country ) : ?>
						<tr data-rbn-country-name="<?php echo esc_attr( mb_strtolower( $country->name ) ); ?>">
							<td>
								<?php echo esc_html( $country->name ); ?>
								<br /><code><?php echo esc_html( $country->iso_code ); ?></code>
							</td>
							<td>
								<input
									type="text"
									class="regular-text"
									name="rbn_demonyms[<?php echo esc_attr( $country->id ); ?>][singular]"
									value="<?php echo esc_attr( $country->demonym_singular ); ?>"
									placeholder="<?php echo esc_attr( $country->name ); ?>"
								/>
							</td>
							<td>
								<input
									type="text"
									class="regular-text"
									name="rbn_demonyms[<?php echo esc_attr( $country->id ); ?>][plural]"
									value="<?php echo esc_attr( $country->demonym_plural ); ?>"
									placeholder="<?php echo esc_attr( $country->name ); ?>"
								/>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Save Demonyms', 'roomworks-business-networking' ) ); ?>
		</form>
		<script>
		( function () {
			var filter = document.getElementById( 'rbn-country-filter' );
			var rows   = document.querySelectorAll( '#rbn-countries-table tbody tr' );

			if ( ! filter ) {
				return;
			}

			filter.addEventListener( 'input', function () {
				var term = filter.value.trim().toLowerCase();

				rows.forEach( function ( row ) {
					var name = row.getAttribute( 'data-rbn-country-name' ) || '';
					row.style.display = ( '' === term || name.indexOf( term ) !== -1 ) ? '' : 'none';
				} );
			} );
		} )();
		</script>
		<?php
	}
}
