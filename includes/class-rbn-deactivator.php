<?php
/**
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Deactivator {

	/**
	 * Deliberately does not drop the custom tables or touch existing
	 * business/service/category data - only an explicit uninstall should
	 * ever do that, and this plugin doesn't implement one yet.
	 */
	public static function deactivate() {
		RBN_Capabilities::remove_caps();
		flush_rewrite_rules();
	}
}
