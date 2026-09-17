<?php
/**
 * Consolidated "things waiting on an admin" screen: pending member
 * applications, pending business listings, and pending account-deletion
 * requests. The underlying status logic already lives in
 * RBN_Member_Approval, RBN_Post_Type_Business/core post statuses, and
 * RBN_Account_Deletion - this class is the admin-page presentation on top,
 * plus the two actions that had no admin UI at all before (business
 * approve/reject, and an admin cancelling a member's pending deletion).
 *
 * Member approve/reject reuses RBN_Member_Approval's existing
 * admin_action_rbn_approve_member / admin_action_rbn_reject_member hooks
 * rather than duplicating that logic here.
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
			__( 'Approvals', 'roomworks-business-networking' ),
			__( 'Approvals', 'roomworks-business-networking' ),
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
			<h1><?php esc_html_e( 'Approvals', 'roomworks-business-networking' ); ?></h1>
			<?php
			self::render_member_section();
			self::render_business_section();
			self::render_deletion_section();
			?>
		</div>
		<?php
	}

	private static function render_member_section() {
		$users = get_users(
			array(
				'meta_key'   => RBN_Member_Approval::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => RBN_Member_Approval::STATUS_PENDING, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'    => 'registered',
				'order'      => 'DESC',
			)
		);
		?>
		<h2><?php esc_html_e( 'Pending Member Applications', 'roomworks-business-networking' ); ?></h2>
		<?php if ( empty( $users ) ) : ?>
			<p><?php esc_html_e( 'No member applications are waiting for review.', 'roomworks-business-networking' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Email', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Registered', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'roomworks-business-networking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $users as $user ) : ?>
					<tr>
						<td><?php echo esc_html( $user->display_name ); ?></td>
						<td><?php echo esc_html( $user->user_email ); ?></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $user->user_registered ) ); ?></td>
						<td>
							<a class="button button-primary" href="<?php echo esc_url( self::action_url( 'rbn_approve_member', 'user_id', $user->ID ) ); ?>">
								<?php esc_html_e( 'Approve', 'roomworks-business-networking' ); ?>
							</a>
							<a class="button" href="<?php echo esc_url( self::action_url( 'rbn_reject_member', 'user_id', $user->ID ) ); ?>">
								<?php esc_html_e( 'Reject', 'roomworks-business-networking' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_business_section() {
		$businesses = get_posts(
			array(
				'post_type'      => RBN_Post_Type_Business::POST_TYPE,
				'post_status'    => 'pending',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		?>
		<h2><?php esc_html_e( 'Pending Business Listings', 'roomworks-business-networking' ); ?></h2>
		<?php if ( empty( $businesses ) ) : ?>
			<p><?php esc_html_e( 'No business listings are waiting for review.', 'roomworks-business-networking' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Business', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Owner', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Category', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Submitted', 'roomworks-business-networking' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'roomworks-business-networking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $businesses as $business ) : ?>
					<?php $categories = get_the_terms( $business, RBN_Taxonomy_Business_Category::TAXONOMY ); ?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_post_link( $business ) ); ?>"><?php echo esc_html( wp_specialchars_decode( get_the_title( $business ), ENT_QUOTES ) ); ?></a></td>
						<td><?php echo esc_html( get_the_author_meta( 'display_name', $business->post_author ) ); ?></td>
						<td><?php echo esc_html( ( $categories && ! is_wp_error( $categories ) && ! empty( $categories ) ) ? wp_specialchars_decode( $categories[0]->name, ENT_QUOTES ) : '' ); ?></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $business->post_date ) ); ?></td>
						<td>
							<a class="button button-primary" href="<?php echo esc_url( self::action_url( 'rbn_approve_business', 'post_id', $business->ID ) ); ?>">
								<?php esc_html_e( 'Approve', 'roomworks-business-networking' ); ?>
							</a>
							<a class="button" href="<?php echo esc_url( self::action_url( 'rbn_reject_business', 'post_id', $business->ID ) ); ?>">
								<?php esc_html_e( 'Reject', 'roomworks-business-networking' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
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

	public static function handle_approve_business() {
		self::handle_business_decision( true );
	}

	public static function handle_reject_business() {
		self::handle_business_decision( false );
	}

	private static function handle_business_decision( $approve ) {
		$post_id      = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$nonce_action = ( $approve ? 'rbn_approve_business_' : 'rbn_reject_business_' ) . $post_id;
		$nonce        = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $post_id || ! $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_die( esc_html__( 'Invalid request.', 'roomworks-business-networking' ) );
		}

		if ( ! current_user_can( $approve ? 'publish_post' : 'delete_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'roomworks-business-networking' ) );
		}

		if ( $approve ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				)
			);
		} else {
			wp_trash_post( $post_id );
		}

		wp_safe_redirect( self::page_url() );
		exit;
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
