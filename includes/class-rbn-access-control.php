<?php
/**
 * Restricts specific front-end pages built around this plugin's blocks to
 * logged-in visitors - the business directory and the notice board, neither
 * of which is meant to be publicly browsable.
 *
 * A template_redirect check here (rather than a general-purpose content
 * restriction plugin) keeps the rule versioned with the rest of the
 * plugin's code and lets it send visitors to this plugin's own front-end
 * login form instead of wp-login.php, matching the "frontend-first" design
 * used everywhere else (see RBN_Auth_Forms).
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Access_Control {

	/**
	 * Slugs of the pages hosting this plugin's members-only blocks - the
	 * Business Directory and the Community Notice Board. Update this if
	 * either page is ever renamed/re-slugged.
	 */
	const RESTRICTED_PAGE_SLUGS = array( 'business-networking', 'notice-board' );

	const LOGIN_PAGE_CACHE_KEY = 'rbn_login_page_id';

	/**
	 * Redirects a logged-out visitor away from any of RESTRICTED_PAGE_SLUGS
	 * to this plugin's own front-end login form, sending them back to the
	 * page they wanted once they log in.
	 */
	public static function restrict_members_only_pages() {
		if ( is_user_logged_in() ) {
			return;
		}

		if ( ! is_page( self::RESTRICTED_PAGE_SLUGS ) ) {
			return;
		}

		$requested_url = esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed and escaped via esc_url_raw().

		wp_safe_redirect( self::login_url( $requested_url ) );
		exit;
	}

	/**
	 * Finds the page hosting this plugin's own front-end login form (see
	 * RBN_Templates::auth_forms()), or null if no such page exists yet.
	 * Used both to build a "come back here after logging in" redirect (see
	 * login_url() below) and, by RBN_Member_Approval/RBN_Emails, as the
	 * destination for activation-related emails.
	 *
	 * The lookup result is cached for a few hours (transient, since this
	 * runs on every logged-out visit to the restricted page and object
	 * caching alone won't survive between requests on most hosts) rather
	 * than left to query on every hit.
	 */
	public static function login_page_id() {
		$page_id = get_transient( self::LOGIN_PAGE_CACHE_KEY );

		if ( false === $page_id ) {
			global $wpdb;

			// $wpdb->prepare() + esc_like() below keep this injection-safe;
			// a direct query is used (rather than WP_Query) because no core
			// query arg can search for a specific block's markup inside
			// post_content.
			$page_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prepared below; result is cached in a transient just above.
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s LIMIT 1",
					'%' . $wpdb->esc_like( '<!-- wp:roomworks-business-networking/user-profile' ) . '%'
				)
			);

			set_transient( self::LOGIN_PAGE_CACHE_KEY, $page_id, 6 * HOUR_IN_SECONDS );
		}

		return $page_id ? $page_id : 0;
	}

	/**
	 * This plugin's own front-end login page URL, falling back to
	 * wp-login.php if no such page exists yet. Public so anything that
	 * needs to point a logged-out visitor at "log in here" - not just the
	 * members-only page redirect below - can reuse the same lookup.
	 */
	public static function login_url( $redirect_to = '' ) {
		$page_id = self::login_page_id();

		if ( $page_id ) {
			return $redirect_to ? add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), get_permalink( $page_id ) ) : get_permalink( $page_id );
		}

		return wp_login_url( $redirect_to );
	}

	/**
	 * Clears the cached login page lookup whenever any page is saved, so a
	 * newly published/edited login page is picked up within one save rather
	 * than staying stale for the rest of the transient's lifetime.
	 */
	public static function flush_login_page_cache() {
		delete_transient( self::LOGIN_PAGE_CACHE_KEY );
	}
}
