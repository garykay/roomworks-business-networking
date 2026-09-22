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
	const DB_VERSION        = '1.4.0';

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

		// Reusable country reference data - see RBN_Countries. Used by user
		// origin/current-country fields now, and by communities/businesses
		// once those land (see the scalability spec).
		$countries_table = self::countries_table();
		$sql_countries   = "CREATE TABLE {$countries_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			iso_code CHAR(2) NOT NULL,
			iso3_code CHAR(3) NOT NULL,
			flag_code CHAR(2) NOT NULL,
			demonym_singular VARCHAR(191) NOT NULL DEFAULT '',
			demonym_plural VARCHAR(191) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY iso_code (iso_code),
			KEY status (status),
			KEY name (name)
		) {$charset_collate};";

		// A community is the origin-country/destination-country pairing
		// members join (e.g. "South Africans in the United Kingdom") - see
		// RBN_Communities and the scalability spec's Section 5. Both country
		// columns reference countries.id, never a name/ISO code, per the
		// spec's "do not hard-code countries" rule.
		$communities_table = self::communities_table();
		$sql_communities   = "CREATE TABLE {$communities_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(200) NOT NULL,
			origin_country_id BIGINT UNSIGNED NOT NULL,
			destination_country_id BIGINT UNSIGNED NOT NULL,
			description TEXT NULL,
			logo_attachment_id BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY origin_country_id (origin_country_id),
			KEY destination_country_id (destination_country_id),
			KEY status (status)
		) {$charset_collate};";

		// A member's membership in a community - see RBN_Community_Memberships
		// and the spec's Section 8. A user may hold more than one membership
		// (e.g. joining both a national and a regional community later), so
		// this is its own table rather than a single field on the user.
		$memberships_table = self::community_memberships_table();
		$sql_memberships   = "CREATE TABLE {$memberships_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			community_id BIGINT UNSIGNED NOT NULL,
			role VARCHAR(30) NOT NULL DEFAULT 'member',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			joined_at DATETIME NOT NULL,
			approved_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_community (user_id, community_id),
			KEY community_id (community_id),
			KEY status (status)
		) {$charset_collate};";

		// A member following a business - see RBN_Business_Follows. Kept as
		// its own table rather than reusing $sql_follows above: that table's
		// follower_id/followed_id are both WordPress user IDs (a future
		// member-to-member follow feature - see its own docblock), and
		// pointing followed_id at a business post ID instead would silently
		// break RBN_Account_Deletion's existing followed_id = user_id
		// cleanup as well as conflating two different entity types under
		// one ambiguous column.
		$business_follows_table = self::business_follows_table();
		$sql_business_follows   = "CREATE TABLE {$business_follows_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			business_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_business (user_id, business_id),
			KEY business_id (business_id)
		) {$charset_collate};";

		dbDelta( $sql_follows );
		dbDelta( $sql_needs );
		dbDelta( $sql_countries );
		dbDelta( $sql_communities );
		dbDelta( $sql_memberships );
		dbDelta( $sql_business_follows );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Runs install() again if the stored schema version is behind, so a
	 * future table change takes effect without requiring deactivate/reactivate.
	 * Also re-seeds reference data introduced by that change (e.g. countries
	 * in 1.1.0) - RBN_Countries::seed_defaults() only ever inserts rows
	 * whose iso_code doesn't already exist, so this is safe to run on every
	 * version bump without duplicating or clobbering existing rows.
	 *
	 * RBN_Countries::maybe_seed() runs unconditionally, not just inside the
	 * version-bump branch above: DB_VERSION_OPTION is updated as the last
	 * step of install(), so if seed_defaults() were ever interrupted right
	 * after a version bump, this branch would never run again to retry it.
	 * maybe_seed()'s own empty-table check is what makes that self-healing.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
			RBN_Countries::seed_defaults();
		}

		RBN_Countries::maybe_seed();
		RBN_Communities::maybe_seed();
	}

	public static function follows_table() {
		global $wpdb;
		return $wpdb->prefix . 'rbn_follows';
	}

	public static function member_needs_table() {
		global $wpdb;
		return $wpdb->prefix . 'rbn_member_needs';
	}

	public static function countries_table() {
		global $wpdb;
		return $wpdb->prefix . 'rbn_countries';
	}

	public static function communities_table() {
		global $wpdb;
		return $wpdb->prefix . 'rbn_communities';
	}

	public static function community_memberships_table() {
		global $wpdb;
		return $wpdb->prefix . 'rbn_community_memberships';
	}

	public static function business_follows_table() {
		global $wpdb;
		return $wpdb->prefix . 'rbn_business_follows';
	}
}
