<?php
/**
 * Admin screen for pending account-deletion requests - the only thing left
 * here that still needs a human. Member activation now happens automatically
 * (RBN_Member_Approval - an admin who needs to intervene on a stuck or
 * abusive signup does so from the row actions on the Users screen instead),
 * and a new business listing from an already-active member publishes
 * immediately (RBN_Business_Forms), so neither has a dedicated review queue
 * here any more.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Approvals {

	const PAGE_SLUG = 'rbn-approvals';

	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . RBN_Post_Type_Business::POST_TYPE,
			__( 'Account Deletions', 'roomworks-business-networking' ),
			__( 'Account Deletions', 'roomworks-business-networking' ),
			'edit_users',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Account Deletions', 'roomworks-business-networking' ); ?></h1>
			<?php self::render_deletion_section(); ?>
		</div>
		<?php
	}

	private static function render_deletion_section() {
		$users = get_users(
			array(
				'meta_key'     => RBN_Account_Deletion::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare' => 'EXISTS',
				'orderby'      => 'registered',
				'order'        => 'DESC',
			)
		);
		?>
		<h2><?php esc_html_e( 'Pending Account Deletions', 'roomworks-business-networking' ); ?></h2>
		<?php if ( empty( $users ) ) : ?>
			<p><?php esc_html_e( 'No account deletions are pending.', 'roomworks-business-networking' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Email', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Requested', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Scheduled for', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'roomworks-business-networking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $users as $user ) : ?>
					<tr>
						<td><?php echo esc_html( $user->display_name ); ?></td>
						<td><?php echo esc_html( $user->user_email ); ?></td>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), RBN_Account_Deletion::requested_at( $user->ID ) ) ); ?></td>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), RBN_Account_Deletion::scheduled_for( $user->ID ) ) ); ?></td>
						<td>
							<a class="button" href="<?php echo esc_url( self::action_url( 'rbn_cancel_deletion', 'user_id', $user->ID ) ); ?>">
								<?php esc_html_e( 'Cancel deletion', 'roomworks-business-networking' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * A nonced admin.php?action=... link - the standard WP pattern an
	 * admin_action_{$action} hook listens for, regardless of which admin
	 * screen the link is rendered on.
	 */
	private static function action_url( $action, $id_param, $id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => $action,
					$id_param => $id,
				),
				admin_url( 'admin.php' )
			),
			$action . '_' . $id
		);
	}

	public static function handle_cancel_deletion() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'roomworks-business-networking' ) );
		}

		$user_id      = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$nonce_action = 'rbn_cancel_deletion_' . $user_id;
		$nonce        = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $user_id || ! $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_die( esc_html__( 'Invalid request.', 'roomworks-business-networking' ) );
		}

		RBN_Account_Deletion::cancel( $user_id );

		wp_safe_redirect( self::page_url() );
		exit;
	}

	private static function page_url() {
		return admin_url( 'edit.php?post_type=' . RBN_Post_Type_Business::POST_TYPE . '&page=' . self::PAGE_SLUG );
	}
}
