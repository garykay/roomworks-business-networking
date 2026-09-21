<?php
/**
 * wp-admin "Communities" screen: create a community (name, origin country,
 * destination country, description, status) and activate/deactivate
 * existing ones. Per the scalability spec's Section 35, creating a new
 * community - e.g. "South Africans in Australia" alongside the existing
 * "South Africans in the United Kingdom" - must never require a code
 * change, only this admin screen.
 *
 * manage_options-gated for now, same as RBN_Settings - a dedicated
 * community-admin/country-admin role (spec Section 17) isn't built yet;
 * this isn't the place to add one speculatively.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Communities_Admin {

	const PAGE_SLUG     = 'rbn-communities';
	const CREATE_ACTION = 'rbn_create_community';
	const UPDATE_ACTION = 'rbn_update_community';
	const TOGGLE_ACTION = 'rbn_toggle_community_status';

	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . RBN_Post_Type_Business::POST_TYPE,
			__( 'Communities', 'roomworks-business-networking' ),
			__( 'Communities', 'roomworks-business-networking' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Handles the create/toggle POSTs and GET actions before any output, so
	 * it can redirect afterwards - mirrors RBN_Approvals's admin_action_*
	 * pattern for the toggle, and follows the same "verify nonce, act,
	 * redirect with a notice" shape for create.
	 */
	public static function maybe_handle_request() {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only routes to a handler; each handler verifies its own nonce before acting.
			return;
		}

		if ( isset( $_POST['rbn_form_action'] ) && self::CREATE_ACTION === $_POST['rbn_form_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified inside handle_create().
			self::handle_create();
		}

		if ( isset( $_POST['rbn_form_action'] ) && self::UPDATE_ACTION === $_POST['rbn_form_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified inside handle_update().
			self::handle_update();
		}

		if ( isset( $_GET['action'] ) && self::TOGGLE_ACTION === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified inside handle_toggle().
			self::handle_toggle();
		}
	}

	private static function handle_create() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'roomworks-business-networking' ) );
		}

		$nonce = isset( $_POST['rbn_community_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_community_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::CREATE_ACTION ) ) {
			wp_die( esc_html__( 'Your session expired. Please try again.', 'roomworks-business-networking' ) );
		}

		$result = RBN_Communities::create(
			array(
				'name'                   => isset( $_POST['rbn_community_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_community_name'] ) ) : '',
				'origin_country_id'      => isset( $_POST['rbn_origin_country_id'] ) ? absint( $_POST['rbn_origin_country_id'] ) : 0,
				'destination_country_id' => isset( $_POST['rbn_destination_country_id'] ) ? absint( $_POST['rbn_destination_country_id'] ) : 0,
				'description'            => isset( $_POST['rbn_community_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rbn_community_description'] ) ) : '',
				'status'                 => isset( $_POST['rbn_community_status'] ) ? sanitize_key( wp_unslash( $_POST['rbn_community_status'] ) ) : RBN_Communities::STATUS_ACTIVE,
			)
		);

		if ( is_wp_error( $result ) ) {
			self::redirect_with_notice( 'error', $result->get_error_message() );
		}

		self::redirect_with_notice( 'success', __( 'Community created.', 'roomworks-business-networking' ) );
	}

	/**
	 * Updates a community's name/description/status - origin/destination
	 * country are intentionally not editable here, see RBN_Communities::update()'s
	 * docblock for why.
	 */
	private static function handle_update() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'roomworks-business-networking' ) );
		}

		$nonce = isset( $_POST['rbn_community_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_community_nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::UPDATE_ACTION ) ) {
			wp_die( esc_html__( 'Your session expired. Please try again.', 'roomworks-business-networking' ) );
		}

		$community_id = isset( $_POST['rbn_community_id'] ) ? absint( $_POST['rbn_community_id'] ) : 0;

		$result = RBN_Communities::update(
			$community_id,
			array(
				'name'        => isset( $_POST['rbn_community_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rbn_community_name'] ) ) : '',
				'description' => isset( $_POST['rbn_community_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rbn_community_description'] ) ) : '',
				'status'      => isset( $_POST['rbn_community_status'] ) ? sanitize_key( wp_unslash( $_POST['rbn_community_status'] ) ) : RBN_Communities::STATUS_ACTIVE,
			)
		);

		if ( is_wp_error( $result ) ) {
			self::redirect_with_notice( 'error', $result->get_error_message() );
		}

		self::redirect_with_notice( 'success', __( 'Community updated.', 'roomworks-business-networking' ) );
	}

	private static function handle_toggle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'roomworks-business-networking' ) );
		}

		$community_id = isset( $_GET['community_id'] ) ? absint( $_GET['community_id'] ) : 0;
		$nonce        = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $community_id || ! $nonce || ! wp_verify_nonce( $nonce, self::TOGGLE_ACTION . '_' . $community_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'roomworks-business-networking' ) );
		}

		$community = RBN_Communities::get_by_id( $community_id );

		if ( ! $community ) {
			wp_die( esc_html__( 'Community not found.', 'roomworks-business-networking' ) );
		}

		$new_status = RBN_Communities::STATUS_ACTIVE === $community->status
			? RBN_Communities::STATUS_INACTIVE
			: RBN_Communities::STATUS_ACTIVE;

		RBN_Communities::set_status( $community_id, $new_status );

		self::redirect_with_notice( 'success', __( 'Community status updated.', 'roomworks-business-networking' ) );
	}

	private static function redirect_with_notice( $type, $message ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'rbn_notice_type' => $type,
					'rbn_notice'      => rawurlencode( $message ),
				),
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

		// Read-only: only chooses whether the form below is in "add" or
		// "edit" mode, never a state change, so no nonce applies here -
		// handle_update() re-verifies everything before actually writing.
		$editing_community_id = isset( $_GET['rbn_edit_community'] ) ? absint( $_GET['rbn_edit_community'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$editing_community    = $editing_community_id ? RBN_Communities::get_by_id( $editing_community_id ) : null;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Communities', 'roomworks-business-networking' ); ?></h1>
			<?php self::render_notice(); ?>
			<?php self::render_community_form( $editing_community ); ?>
			<?php self::render_list(); ?>
		</div>
		<?php
	}

	private static function render_notice() {
		if ( empty( $_GET['rbn_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of a message set by our own redirect above.
			return;
		}

		$type    = isset( $_GET['rbn_notice_type'] ) && 'success' === $_GET['rbn_notice_type'] ? 'success' : 'error'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = sanitize_text_field( wp_unslash( $_GET['rbn_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders the "Add Community" form, or an "Edit Community" form
	 * pre-filled for $editing_community when one is given (see
	 * render_page()'s rbn_edit_community handling). Origin/destination
	 * country are only shown - and only submitted at all - when creating a
	 * new community; editing one never touches them (RBN_Communities::update()
	 * doesn't accept them either, so this isn't just a UI-level omission).
	 */
	private static function render_community_form( $editing_community ) {
		$is_editing = (bool) $editing_community;
		$countries  = $is_editing ? array() : RBN_Countries::get_all();
		?>
		<h2>
			<?php
			echo $is_editing
				/* translators: %s: community name. */
				? esc_html( sprintf( __( 'Edit Community: %s', 'roomworks-business-networking' ), $editing_community->name ) )
				: esc_html__( 'Add Community', 'roomworks-business-networking' );
			?>
		</h2>
		<form method="post" action="<?php echo esc_url( self::page_url() ); ?>">
			<?php if ( $is_editing ) : ?>
				<?php wp_nonce_field( self::UPDATE_ACTION, 'rbn_community_nonce' ); ?>
				<input type="hidden" name="rbn_form_action" value="<?php echo esc_attr( self::UPDATE_ACTION ); ?>" />
				<input type="hidden" name="rbn_community_id" value="<?php echo esc_attr( $editing_community->id ); ?>" />
			<?php else : ?>
				<?php wp_nonce_field( self::CREATE_ACTION, 'rbn_community_nonce' ); ?>
				<input type="hidden" name="rbn_form_action" value="<?php echo esc_attr( self::CREATE_ACTION ); ?>" />
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="rbn-community-name"><?php esc_html_e( 'Community Name', 'roomworks-business-networking' ); ?></label></th>
						<td><input type="text" id="rbn-community-name" name="rbn_community_name" class="regular-text" value="<?php echo esc_attr( $is_editing ? $editing_community->name : '' ); ?>" required /></td>
					</tr>
					<?php if ( $is_editing ) : ?>
						<?php
						$editing_origin      = RBN_Countries::get_by_id( $editing_community->origin_country_id );
						$editing_destination = RBN_Countries::get_by_id( $editing_community->destination_country_id );
						?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Origin Country', 'roomworks-business-networking' ); ?></th>
							<td>
								<?php echo esc_html( $editing_origin ? $editing_origin->name : '—' ); ?>
								<p class="description"><?php esc_html_e( "Can't be changed after a community is created - members and businesses are already tied to it.", 'roomworks-business-networking' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Destination Country', 'roomworks-business-networking' ); ?></th>
							<td><?php echo esc_html( $editing_destination ? $editing_destination->name : '—' ); ?></td>
						</tr>
					<?php else : ?>
						<tr>
							<th scope="row"><label for="rbn-community-origin"><?php esc_html_e( 'Origin Country', 'roomworks-business-networking' ); ?></label></th>
							<td><?php self::country_select( 'rbn-community-origin', 'rbn_origin_country_id', $countries ); ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="rbn-community-destination"><?php esc_html_e( 'Destination Country', 'roomworks-business-networking' ); ?></label></th>
							<td><?php self::country_select( 'rbn-community-destination', 'rbn_destination_country_id', $countries ); ?></td>
						</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><label for="rbn-community-description"><?php esc_html_e( 'Description', 'roomworks-business-networking' ); ?></label></th>
						<td><textarea id="rbn-community-description" name="rbn_community_description" class="large-text" rows="3"><?php echo esc_textarea( $is_editing ? $editing_community->description : '' ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="rbn-community-status"><?php esc_html_e( 'Status', 'roomworks-business-networking' ); ?></label></th>
						<td>
							<select id="rbn-community-status" name="rbn_community_status">
								<option value="<?php echo esc_attr( RBN_Communities::STATUS_ACTIVE ); ?>" <?php selected( $is_editing ? $editing_community->status : RBN_Communities::STATUS_ACTIVE, RBN_Communities::STATUS_ACTIVE ); ?>><?php esc_html_e( 'Active', 'roomworks-business-networking' ); ?></option>
								<option value="<?php echo esc_attr( RBN_Communities::STATUS_INACTIVE ); ?>" <?php selected( $is_editing ? $editing_community->status : '', RBN_Communities::STATUS_INACTIVE ); ?>><?php esc_html_e( 'Inactive', 'roomworks-business-networking' ); ?></option>
							</select>
						</td>
					</tr>
				</tbody>
			</table>
			<?php submit_button( $is_editing ? __( 'Update Community', 'roomworks-business-networking' ) : __( 'Add Community', 'roomworks-business-networking' ) ); ?>
			<?php if ( $is_editing ) : ?>
				<a class="button" href="<?php echo esc_url( self::page_url() ); ?>"><?php esc_html_e( 'Cancel', 'roomworks-business-networking' ); ?></a>
			<?php endif; ?>
		</form>
		<?php
	}

	private static function country_select( $id, $name, $countries ) {
		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" required>
			<option value=""><?php esc_html_e( '— Select —', 'roomworks-business-networking' ); ?></option>
			<?php foreach ( $countries as $country ) : ?>
				<option value="<?php echo esc_attr( $country->id ); ?>"><?php echo esc_html( $country->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	private static function render_list() {
		$communities = RBN_Communities::get_all();
		?>
		<h2><?php esc_html_e( 'Existing Communities', 'roomworks-business-networking' ); ?></h2>
		<?php if ( empty( $communities ) ) : ?>
			<p><?php esc_html_e( 'No communities yet.', 'roomworks-business-networking' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Origin', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Destination', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Members', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Status', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'roomworks-business-networking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $communities as $community ) : ?>
					<?php
					$origin      = RBN_Countries::get_by_id( $community->origin_country_id );
					$destination = RBN_Countries::get_by_id( $community->destination_country_id );
					$is_active   = RBN_Communities::STATUS_ACTIVE === $community->status;
					$toggle_url  = wp_nonce_url(
						add_query_arg(
							array(
								'action'       => RBN_Communities_Admin::TOGGLE_ACTION,
								'community_id' => $community->id,
							),
							self::page_url()
						),
						RBN_Communities_Admin::TOGGLE_ACTION . '_' . $community->id
					);
					?>
					<tr>
						<td><?php echo esc_html( $community->name ); ?><br /><code><?php echo esc_html( $community->slug ); ?></code></td>
						<td><?php echo esc_html( $origin ? $origin->name : '—' ); ?></td>
						<td><?php echo esc_html( $destination ? $destination->name : '—' ); ?></td>
						<td><?php echo esc_html( RBN_Community_Memberships::member_count( $community->id ) ); ?></td>
						<td><?php echo esc_html( $is_active ? __( 'Active', 'roomworks-business-networking' ) : __( 'Inactive', 'roomworks-business-networking' ) ); ?></td>
						<td>
							<a class="button" href="<?php echo esc_url( add_query_arg( 'rbn_edit_community', $community->id, self::page_url() ) ); ?>">
								<?php esc_html_e( 'Edit', 'roomworks-business-networking' ); ?>
							</a>
							<a class="button" href="<?php echo esc_url( $toggle_url ); ?>">
								<?php echo $is_active ? esc_html__( 'Deactivate', 'roomworks-business-networking' ) : esc_html__( 'Activate', 'roomworks-business-networking' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
