<?php
/**
 * Custom database tables for relationships that structured post/taxonomy
 * data cannot model cleanly (self-referential follows, and services linked
 * to a user rather than a post).
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Schema {

	const DB_VERSION_OPTION = 'rbn_db_version';
	const DB_VERSION        = '1.0.0';

	/**
	 * Creates (or updates) the plugin's custom tables.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$follows_table = self::follows_table();
		$sql_follows   = "CREATE TABLE {$follows_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			follower_id BIGINT UNSIGNED NOT NULL,
			followed_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY follower_followed (follower_id, followed_id),
			KEY followed_id (followed_id)
		) {$charset_collate};";

		$needs_table = self::member_needs_table();
		$sql_needs   = "CREATE TABLE {$needs_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			term_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_term (user_id, term_id),
			KEY term_id (term_id)
		) {$charset_collate};";

		dbDelta( $sql_follows );
		dbDelta( $sql_needs );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Runs install() again if the stored schema version is behind, so a
	 * future table change takes effect without requiring deactivate/reactivate.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function follows_table() {
		global $wpdb;
		return $wpdb->prefix . 'rbn_follows';
	}

	public static function member_needs_table() {
		global $wpdb;
		return $wpdb->prefix . 'rbn_member_needs';
	}
}
