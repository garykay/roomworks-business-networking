<?php
/**
 * Admin settings page for directory and notification behaviour. Read via
 * the per-setting accessors below (per_page(), sort_query_args(),
 * notification_email(), deletion_grace_hours()) by whichever part of the
 * plugin previously had the equivalent value hardcoded, so there is exactly
 * one source of truth for each configured value.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Settings {

	const OPTION_NAME = 'rbn_settings';
	const PAGE_SLUG    = 'rbn-directory-settings';

	const DEFAULT_SORT                  = 'title_asc';
	const DEFAULT_DELETION_GRACE_HOURS  = 24;
	const MAX_DELETION_GRACE_HOURS      = 720; // 30 days - a sanity ceiling, not an expected value.

	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . RBN_Post_Type_Business::POST_TYPE,
			__( 'Directory Settings', 'roomworks-business-networking' ),
			__( 'Settings', 'roomworks-business-networking' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			self::OPTION_NAME,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'rbn_settings_directory',
			__( 'Directory', 'roomworks-business-networking' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'per_page',
			__( 'Businesses per page', 'roomworks-business-networking' ),
			array( __CLASS__, 'render_per_page_field' ),
			self::PAGE_SLUG,
			'rbn_settings_directory'
		);

		add_settings_field(
			'sort',
			__( 'Sort businesses by', 'roomworks-business-networking' ),
			array( __CLASS__, 'render_sort_field' ),
			self::PAGE_SLUG,
			'rbn_settings_directory'
		);

		add_settings_section(
			'rbn_settings_notifications',
			__( 'Notifications', 'roomworks-business-networking' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'notification_email',
			__( 'Notification email', 'roomworks-business-networking' ),
			array( __CLASS__, 'render_notification_email_field' ),
			self::PAGE_SLUG,
			'rbn_settings_notifications'
		);

		add_settings_field(
			'deletion_grace_hours',
			__( 'Account deletion grace period', 'roomworks-business-networking' ),
			array( __CLASS__, 'render_deletion_grace_field' ),
			self::PAGE_SLUG,
			'rbn_settings_notifications'
		);

		add_settings_section(
			'rbn_settings_communities',
			__( 'Communities', 'roomworks-business-networking' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'require_unique_community_pair',
			__( 'Origin/destination pairs', 'roomworks-business-networking' ),
			array( __CLASS__, 'render_require_unique_community_pair_field' ),
			self::PAGE_SLUG,
			'rbn_settings_communities'
		);
	}

	public static function sanitize( $input ) {
		$per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 0;

		if ( $per_page < 1 ) {
			$per_page = RBN_Business_Query::DEFAULT_PER_PAGE;
		}

		$per_page = min( RBN_Business_Query::MAX_PER_PAGE, $per_page );

		$sort = isset( $input['sort'] ) && array_key_exists( $input['sort'], self::sort_options() )
			? $input['sort']
			: self::DEFAULT_SORT;

		$notification_email = isset( $input['notification_email'] ) ? sanitize_email( wp_unslash( $input['notification_email'] ) ) : '';

		if ( '' !== $notification_email && ! is_email( $notification_email ) ) {
			$notification_email = '';
		}

		$grace_hours = isset( $input['deletion_grace_hours'] ) ? absint( $input['deletion_grace_hours'] ) : 0;

		if ( $grace_hours < 1 ) {
			$grace_hours = self::DEFAULT_DELETION_GRACE_HOURS;
		}

		$grace_hours = min( self::MAX_DELETION_GRACE_HOURS, $grace_hours );

		$require_unique_community_pair = ! empty( $input['require_unique_community_pair'] );

		return array(
			'per_page'                      => $per_page,
			'sort'                          => $sort,
			'notification_email'            => $notification_email,
			'deletion_grace_hours'          => $grace_hours,
			'require_unique_community_pair' => $require_unique_community_pair,
		);
	}

	private static function options() {
		$defaults = array(
			'per_page'                      => RBN_Business_Query::DEFAULT_PER_PAGE,
			'sort'                          => self::DEFAULT_SORT,
			'notification_email'            => '',
			'deletion_grace_hours'          => self::DEFAULT_DELETION_GRACE_HOURS,
			// On by default: one community per origin/destination pair,
			// matching the spec's default assumption (Section 6) unless an
			// admin explicitly opts into multiple (e.g. regional
			// sub-communities) via this setting.
			'require_unique_community_pair' => true,
		);
		$saved    = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	public static function per_page() {
		return self::options()['per_page'];
	}

	public static function sort_options() {
		return array(
			'title_asc' => __( 'Name (A–Z)', 'roomworks-business-networking' ),
			'date_desc' => __( 'Newest first', 'roomworks-business-networking' ),
			'date_asc'  => __( 'Oldest first', 'roomworks-business-networking' ),
		);
	}

	/**
	 * WP_Query orderby/order args for the configured sort - the shape
	 * RBN_Business_Query::results() previously hardcoded directly.
	 */
	public static function sort_query_args() {
		$map = array(
			'title_asc' => array(
				'orderby' => 'title',
				'order'   => 'ASC',
			),
			'date_desc' => array(
				'orderby' => 'date',
				'order'   => 'DESC',
			),
			'date_asc'  => array(
				'orderby' => 'date',
				'order'   => 'ASC',
			),
		);

		$sort = self::options()['sort'];

		return isset( $map[ $sort ] ) ? $map[ $sort ] : $map[ self::DEFAULT_SORT ];
	}

	/**
	 * Where member-registration and account-deletion admin alerts are sent.
	 * Falls back to the site's general admin email (Settings > General) when
	 * no override is configured, matching the get_option( 'admin_email' )
	 * this replaces at each call site.
	 */
	public static function notification_email() {
		$email = self::options()['notification_email'];
		return $email ? $email : get_option( 'admin_email' );
	}

	public static function deletion_grace_hours() {
		return self::options()['deletion_grace_hours'];
	}

	public static function require_unique_community_pair() {
		return (bool) self::options()['require_unique_community_pair'];
	}

	public static function render_per_page_field() {
		$options = self::options();
		?>
		<input
			type="number"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[per_page]"
			value="<?php echo esc_attr( $options['per_page'] ); ?>"
			min="1"
			max="<?php echo esc_attr( RBN_Business_Query::MAX_PER_PAGE ); ?>"
			step="1"
			class="small-text"
		/>
		<p class="description">
			<?php
			printf(
				/* translators: %d: maximum allowed value. */
				esc_html__( 'How many businesses to show per page in the directory. Maximum %d.', 'roomworks-business-networking' ),
				(int) RBN_Business_Query::MAX_PER_PAGE
			);
			?>
		</p>
		<?php
	}

	public static function render_sort_field() {
		$options = self::options();
		?>
		<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[sort]">
			<?php foreach ( self::sort_options() as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $options['sort'], $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Default order for the directory listing.', 'roomworks-business-networking' ); ?>
		</p>
		<?php
	}

	public static function render_notification_email_field() {
		$options = self::options();
		?>
		<input
			type="email"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[notification_email]"
			value="<?php echo esc_attr( $options['notification_email'] ); ?>"
			placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
			class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Where new member and account-deletion alerts are sent. Leave blank to use the site admin email above.', 'roomworks-business-networking' ); ?>
		</p>
		<?php
	}

	public static function render_deletion_grace_field() {
		$options = self::options();
		?>
		<input
			type="number"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[deletion_grace_hours]"
			value="<?php echo esc_attr( $options['deletion_grace_hours'] ); ?>"
			min="1"
			max="<?php echo esc_attr( self::MAX_DELETION_GRACE_HOURS ); ?>"
			step="1"
			class="small-text"
		/>
		<?php esc_html_e( 'hours', 'roomworks-business-networking' ); ?>
		<p class="description">
			<?php esc_html_e( 'How long a member has to cancel an account deletion request before it happens automatically. Only applies to requests made after this is changed.', 'roomworks-business-networking' ); ?>
		</p>
		<?php
	}

	public static function render_require_unique_community_pair_field() {
		$options = self::options();
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[require_unique_community_pair]"
				value="1"
				<?php checked( $options['require_unique_community_pair'] ); ?>
			/>
			<?php esc_html_e( 'Require a unique origin/destination pair (e.g. only one "South Africans in the UK" community).', 'roomworks-business-networking' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Turn this off to allow more than one community for the same pair - for example regional sub-communities such as "South Africans in the UK - London".', 'roomworks-business-networking' ); ?>
		</p>
		<?php
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Directory Settings', 'roomworks-business-networking' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_NAME );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
