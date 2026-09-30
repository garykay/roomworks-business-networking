<?php
/**
 * Optional, non-restrictive filtering of the standard WordPress blog
 * ('post') listing by the post author's country/community - deliberately
 * the opposite policy from RBN_Business_Query's community_scope_clause():
 * every published post is visible to every visitor by default (logged in
 * or not), and a filter only ever narrows that down, never widens it or
 * blocks access. There is no "Section 27" access-control concern here
 * because nothing is hidden until a visitor actively chooses to filter.
 *
 * The blog listing itself is the theme's own Query Loop block (the "Blog
 * Home" template, inheriting the main query) - not a plugin-owned block -
 * so filtering hooks into the main query via pre_get_posts rather than
 * running its own WP_Query, letting the theme keep rendering/pagination.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Blog_Filters {

	const PARAM_COUNTRY   = 'rbn_country';
	const PARAM_COMMUNITY = 'rbn_community';
	const PARAM_SEARCH    = 'rbn_search';

	/**
	 * Narrows the main blog query's authors to match the country/community
	 * filter, when one was chosen, and/or its keyword search. Only touches
	 * the front-end main query for the blog listing itself (is_home() - see
	 * class docblock) - never single posts, admin, REST, or any other
	 * archive.
	 */
	public static function filter_main_query( WP_Query $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_home() ) {
			return;
		}

		$filters = self::current_filters();

		if ( $filters['search'] ) {
			// Standard WP search (post_title/post_content), same as any
			// other search query - combines with author__in below via AND.
			$query->set( 's', $filters['search'] );
		}

		$author_ids = self::author_ids_for_filters( $filters );

		if ( null === $author_ids ) {
			return; // No country/community filter chosen - authors stay unrestricted.
		}

		// A filter combination that matches no author must return zero
		// results, not silently fall back to showing everything.
		$query->set( 'author__in', $author_ids ? $author_ids : array( 0 ) );
	}

	/**
	 * The filter choices as sanitized, request-scoped values - not yet
	 * resolved to authors. Used by both filter_main_query() above and the
	 * filter bar's own template (RBN_Templates::blog_filters()).
	 *
	 * @return array { 'country' => int, 'community' => int, 'search' => string }
	 *               'country'/'community' may be 0 (not chosen); 'community'
	 *               is always 0 for a logged-out visitor - see class
	 *               docblock: the community filter is a member-only concept.
	 *               'search' may be '' (not chosen).
	 */
	public static function current_filters() {
		// Read-only GET filter that re-renders the same page - a nonce would
		// break bookmarking/sharing a filtered blog URL, and there's no
		// state change here for a nonce to protect. Same reasoning as the
		// business directory's render.php.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		return array(
			'country'   => isset( $_GET[ self::PARAM_COUNTRY ] ) ? absint( $_GET[ self::PARAM_COUNTRY ] ) : 0,
			'community' => ( is_user_logged_in() && isset( $_GET[ self::PARAM_COMMUNITY ] ) ) ? absint( $_GET[ self::PARAM_COMMUNITY ] ) : 0,
			'search'    => isset( $_GET[ self::PARAM_SEARCH ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::PARAM_SEARCH ] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Resolves the filter choices to an author__in list.
	 *
	 * @return int[]|null Null if neither filter was chosen (no narrowing).
	 *                     Otherwise the intersection of every chosen
	 *                     filter's authors - possibly empty, which the
	 *                     caller must treat as "match nothing", not "match
	 *                     everything".
	 */
	private static function author_ids_for_filters( array $filters ) {
		$author_id_sets = array();

		if ( $filters['country'] ) {
			$author_id_sets[] = RBN_Countries::get_user_ids_for_current_country( $filters['country'] );
		}

		if ( $filters['community'] ) {
			$author_id_sets[] = RBN_Community_Memberships::get_all_member_ids_for_community( $filters['community'] );
		}

		if ( ! $author_id_sets ) {
			return null;
		}

		$intersected = array_shift( $author_id_sets );

		foreach ( $author_id_sets as $set ) {
			$intersected = array_intersect( $intersected, $set );
		}

		return array_values( $intersected );
	}

	/**
	 * The country/community options to offer in the filter bar.
	 *
	 * - Countries: only ones with at least one published post's author
	 *   currently living there, so the filter never offers a choice that
	 *   returns zero results (same rule RBN_Business_Query::filter_options()
	 *   already follows).
	 * - Communities: only shown to a logged-in visitor, and only the
	 *   communities *they* have joined - picking one filters the blog down
	 *   to fellow members of that same community, mirroring how community
	 *   membership already scopes what a member sees elsewhere in the
	 *   plugin, even though visibility itself isn't restricted here.
	 *
	 * @return array { 'countries' => array, 'communities' => array }
	 */
	public static function filter_options() {
		return array(
			'countries'   => self::country_options(),
			'communities' => is_user_logged_in() ? self::community_options( get_current_user_id() ) : array(),
		);
	}

	private static function country_options() {
		global $wpdb;

		$author_ids = $wpdb->get_col( "SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- filter-options lookup, same cost class as RBN_Business_Query::scoped_business_ids().

		$country_ids = array();

		foreach ( $author_ids as $author_id ) {
			$country_id = RBN_Countries::get_current_country_id( $author_id );

			if ( $country_id ) {
				$country_ids[ $country_id ] = true;
			}
		}

		$options = array();

		foreach ( array_keys( $country_ids ) as $country_id ) {
			$country = RBN_Countries::get_by_id( $country_id );

			if ( $country ) {
				$options[] = array(
					'id'   => (int) $country->id,
					'name' => $country->name,
				);
			}
		}

		usort(
			$options,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $options;
	}

	private static function community_options( $user_id ) {
		$options = array();

		foreach ( RBN_Community_Memberships::get_communities_for_user( $user_id ) as $community ) {
			$options[] = array(
				'id'   => (int) $community->id,
				'name' => $community->name,
			);
		}

		return $options;
	}
}
