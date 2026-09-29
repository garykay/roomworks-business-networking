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
	const DB_VERSION        = '1.7.1';

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
		// spec's "do not hard-code countries" rule. notify_new_requests is a
		// community-wide kill switch for RBN_Job_Notifications' "new request
		// posted" broadcast - off entirely stops every member being emailed
		// for this community, regardless of their own membership-level flag
		// below.
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
			notify_new_requests TINYINT(1) NOT NULL DEFAULT 1,
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
		// notify_new_requests is this member's own opt-out of the "new
		// request posted" broadcast for this specific community - see
		// RBN_Job_Notifications.
		$memberships_table = self::community_memberships_table();
		$sql_memberships   = "CREATE TABLE {$memberships_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			community_id BIGINT UNSIGNED NOT NULL,
			role VARCHAR(30) NOT NULL DEFAULT 'member',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			notify_new_requests TINYINT(1) NOT NULL DEFAULT 1,
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

		// A notification for a business owner - currently only raised by
		// RBN_Notifications::create_business_followed() (hooked to
		// RBN_Business_Follows::follow()'s rbn_business_followed action), so
		// recipient_id is always a business's post_author today, but it's
		// named generically rather than "owner_id" since other notification
		// types are the obvious next step here, not a hypothetical. read_at
		// is NULL until RBN_Notifications::mark_all_read_for_user() runs -
		// used both to render the "new" state and to answer the unread-count
		// badge query without scanning already-seen rows.
		//
		// The follower_business UNIQUE key ties a row 1:1 to the underlying
		// business_follows relationship (same pairing that table's own
		// user_business key enforces) rather than logging every follow
		// event: RBN_Notifications::delete_business_followed(), hooked to
		// unfollow()'s rbn_business_unfollowed action, deletes this row when
		// the follow itself goes away, and create_business_followed() only
		// ever runs after a fresh insert into business_follows - so at most
		// one row can exist per follower/business pair, and re-following
		// after an unfollow inserts a clean new one instead of piling up
		// duplicates.
		$notifications_table = self::notifications_table();
		$sql_notifications    = "CREATE TABLE {$notifications_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			recipient_id BIGINT UNSIGNED NOT NULL,
			type VARCHAR(30) NOT NULL,
			follower_id BIGINT UNSIGNED NOT NULL,
			business_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			read_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY follower_business (follower_id, business_id),
			KEY recipient_unread (recipient_id, read_at)
		) {$charset_collate};";

		// 1.7.0 added the follower_business UNIQUE key above to stop
		// unfollow+refollow piling up duplicate rows (see the notifications
		// table's own docblock) - a site that already has duplicate rows
		// from before that fix would otherwise make dbDelta's ALTER TABLE
		// silently fail to add it, leaving the table permanently unfixed.
		// Runs every time install() does (i.e. on every version bump, not
		// just this one) and is a no-op once no duplicates remain, so it
		// never needs its own removal later.
		self::dedupe_notifications();

		// 1.7.1: RBN_Notifications::get_notifications_for_user()/
		// get_unread_count_for_user() now join against business_follows
		// directly rather than trusting notification rows to already be in
		// sync with it, so this is belt-and-braces hygiene rather than
		// something the "Followers" tab's correctness actually depends on -
		// but a notification row from before delete_business_followed()
		// existed (raised on a follow that's since been unfollowed) would
		// otherwise sit here indefinitely, so it's still worth clearing out.
		self::prune_orphaned_notifications();

		dbDelta( $sql_follows );
		dbDelta( $sql_needs );
		dbDelta( $sql_countries );
		dbDelta( $sql_communities );
		dbDelta( $sql_memberships );
		dbDelta( $sql_business_follows );
		dbDelta( $sql_notifications );

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

	/**
	 * Keeps only the most recent notification row per follower/business
	 * pair - the cleanup that makes the notifications table's
	 * follower_business UNIQUE key addable on a site that already has
	 * duplicates from before that key existed. Guarded by a table-exists
	 * check since it also runs on a brand new install, before install()'s
	 * own dbDelta() call below has created the table yet.
	 */
	private static function dedupe_notifications() {
		global $wpdb;

		$table = self::notifications_table();

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return;
		}

		$wpdb->query( "DELETE n1 FROM {$table} n1 INNER JOIN {$table} n2 ON n1.follower_id = n2.follower_id AND n1.business_id = n2.business_id AND n1.id < n2.id" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Removes a notification row that no longer has a matching
	 * business_follows row - i.e. one raised by a follow that's since been
	 * unfollowed, from before delete_business_followed() existed to catch
	 * that itself. See RBN_Notifications::get_notifications_for_user()'s
	 * docblock for why this is hygiene rather than a correctness
	 * dependency. Guarded the same way dedupe_notifications() is, for the
	 * same brand-new-install reason.
	 */
	private static function prune_orphaned_notifications() {
		global $wpdb;

		$notifications_table = self::notifications_table();
		$follows_table       = self::business_follows_table();

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $notifications_table ) ) !== $notifications_table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return;
		}

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $follows_table ) ) !== $follows_table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return;
		}

		$wpdb->query( "DELETE n FROM {$notifications_table} n LEFT JOIN {$follows_table} bf ON bf.user_id = n.follower_id AND bf.business_id = n.business_id WHERE bf.id IS NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

	public static function notifications_table() {
		global $wpdb;
		return $wpdb->prefix . 'rbn_notifications';
	}
}
