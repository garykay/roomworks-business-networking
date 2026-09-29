<?php
/**
 * The single write endpoint backing the dashboard's "Followers" tab badge -
 * clicking that tab fires this from view.js's initTabs() (data-rbn-mark-
 * read-url/nonce on the tab link itself) so the badge clears without a full
 * page reload, same fetch()+X-WP-Nonce pattern initTagField()/
 * initCategoryField() already use for their own REST writes.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_REST_Notifications {

	const NAMESPACE = 'roomworks-business-networking/v1';

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/notifications/mark-read',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'mark_read' ),
				'permission_callback' => array( __CLASS__, 'check_logged_in' ),
			)
		);
	}

	/**
	 * Same reasoning as RBN_REST_Jobs::check_logged_in() - a cookie-
	 * authenticated REST request also requires the wp_rest nonce sent above,
	 * so this is "the currently logged-in viewer", not just "has a cookie".
	 */
	public static function check_logged_in() {
		return is_user_logged_in();
	}

	public static function mark_read() {
		RBN_Notifications::mark_all_read_for_user( get_current_user_id() );

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}
