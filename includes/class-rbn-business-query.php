<?php
/**
 * Reusable business search/filter query - the single source of truth for
 * "which published businesses match these filters", used by both the
 * directory block's server render (first page, works without JS) and the
 * REST endpoint (subsequent async filtering/pagination). Keeping this in
 * one place is what the spec means by exposing search through a reusable
 * data layer rather than building it into one block.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Business_Query {

	const DEFAULT_PER_PAGE = 12;
	const MAX_PER_PAGE     = 48;

	/**
	 * @param array $args {
	 *     @type string $category Category term slug.
	 *     @type string $service  Service term slug.
	 *     @type string $location Free-text match against town/city, county/region, postcode.
	 *     @type string $search   Free-text match against business name/description.
	 *     @type int    $page     1-indexed page number.
	 *     @type int    $per_page Results per page.
	 * }
	 */
	public static function results( array $args ) {
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$per_page = isset( $args['per_page'] ) && absint( $args['per_page'] )
			? min( self::MAX_PER_PAGE, absint( $args['per_page'] ) )
			: RBN_Settings::per_page();

		$query_args = array_merge(
			array(
				'post_type'      => RBN_Post_Type_Business::POST_TYPE,
				'post_status'    => 'publish',
				'paged'          => $page,
				'posts_per_page' => $per_page,
			),
			RBN_Settings::sort_query_args()
		);

		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}

		$tax_query = self::tax_query( $args );

		if ( $tax_query ) {
			$query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		// Always applied, regardless of what the caller passed - see
		// scoped_meta_query()'s docblock for why this can't be optional or
		// caller-supplied.
		$query_args['meta_query'] = self::scoped_meta_query( $args ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		$query = new WP_Query( $query_args );

		return array(
			'items'       => array_map( array( __CLASS__, 'normalize' ), $query->posts ),
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		);
	}

	private static function tax_query( array $args ) {
		$tax_query = array();

		if ( ! empty( $args['category'] ) ) {
			$tax_query[] = array(
				'taxonomy' => RBN_Taxonomy_Business_Category::TAXONOMY,
				'field'    => 'slug',
				'terms'    => sanitize_title( $args['category'] ),
			);
		}

		if ( ! empty( $args['service'] ) ) {
			$tax_query[] = array(
				'taxonomy' => RBN_Taxonomy_Service::TAXONOMY,
				'field'    => 'slug',
				'terms'    => sanitize_title( $args['service'] ),
			);
		}

		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		return $tax_query;
	}

	/**
	 * Combines the optional location filter with the mandatory community
	 * scope below into one meta_query, so a caller can never end up with a
	 * query that only applies one of the two.
	 */
	private static function scoped_meta_query( array $args ) {
		$clauses = array( self::community_scope_clause() );

		if ( ! empty( $args['location'] ) ) {
			$location  = sanitize_text_field( $args['location'] );
			$clauses[] = array(
				'relation' => 'OR',
				array(
					'key'     => 'rbn_town_city',
					'value'   => $location,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'rbn_county_region',
					'value'   => $location,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'rbn_postcode',
					'value'   => $location,
					'compare' => 'LIKE',
				),
			);
		}

		return array_merge( array( 'relation' => 'AND' ), $clauses );
	}

	/**
	 * The one access-control rule that exists so far (scalability spec
	 * Section 9): a member only sees businesses belonging to a community
	 * that BOTH (a) they've actually joined and (b) is currently in the
	 * country they currently live in. Two separate, deliberate factors:
	 *
	 * - Membership (RBN_Community_Memberships) is what a member builds up
	 *   over time and never loses just by editing their profile - moving
	 *   from the UK to the US doesn't remove their "South Africans in the
	 *   UK" membership, and "My Communities" on the dashboard always lists
	 *   every community they've ever joined, full stop.
	 * - Current country (RBN_Countries::get_current_country_id()) is what
	 *   makes a joined community's businesses *actually visible* right
	 *   now. This is a local-services directory - a UK plumber is useless
	 *   to someone who has since moved to the US - so a membership in a
	 *   country a member no longer lives in goes dormant rather than
	 *   staying permanently visible. Move back to the UK and it's visible
	 *   again immediately, with no need to rejoin.
	 *
	 * A member who has only joined "Indians in the UK" does not see "South
	 * Africans in the UK" businesses just because both communities happen
	 * to be in the UK - joining that second community is what would grant
	 * that (membership), same as before this country factor was added.
	 *
	 * Enforced here, in the shared query layer every caller (the block's
	 * server render and the REST endpoint) already goes through, rather
	 * than left to each caller to remember - see the class docblock.
	 *
	 * A visitor who isn't logged in, has no current country set, or has no
	 * joined community matching their current country, matches a
	 * meta_query for an impossible rbn_community_id (0) - i.e. no results
	 * - rather than falling back to showing everything. Spec Section 27:
	 * never accidentally create global visibility.
	 */
	private static function community_scope_clause() {
		$allowed_community_ids = array();

		if ( is_user_logged_in() ) {
			$user_id            = get_current_user_id();
			$current_country_id = RBN_Countries::get_current_country_id( $user_id );

			if ( $current_country_id ) {
				$joined_ids     = array_map( 'absint', wp_list_pluck( RBN_Community_Memberships::get_communities_for_user( $user_id ), 'id' ) );
				$in_country_ids = array_map( 'absint', wp_list_pluck( RBN_Communities::get_for_destination_country( $current_country_id ), 'id' ) );

				$allowed_community_ids = array_values( array_intersect( $joined_ids, $in_country_ids ) );
			}
		}

		if ( empty( $allowed_community_ids ) ) {
			$allowed_community_ids = array( 0 );
		}

		return array(
			'key'     => 'rbn_community_id',
			'value'   => $allowed_community_ids,
			'compare' => 'IN',
		);
	}

	/**
	 * Only fields safe to expose publicly - no owner email, phone, or any
	 * other private contact data.
	 */
	private static function normalize( WP_Post $business ) {
		$categories = get_the_terms( $business, RBN_Taxonomy_Business_Category::TAXONOMY );
		$services   = get_the_terms( $business, RBN_Taxonomy_Service::TAXONOMY );
		$logo       = get_the_post_thumbnail_url( $business, 'medium' );

		return array(
			'id'            => $business->ID,
			// get_the_title() runs the 'the_title' filter chain, which
			// entity-encodes special characters (e.g. "&" -> "&#038;") -
			// decoded here for the same reason as decoded_name() below:
			// callers still need to esc_html()/esc_attr() this themselves.
			'name'          => wp_specialchars_decode( get_the_title( $business ), ENT_QUOTES ),
			'excerpt'       => wp_trim_words( wp_strip_all_tags( $business->post_content ), 25 ),
			'permalink'     => get_permalink( $business ),
			'logo'          => $logo ? $logo : '',
			'category'      => ( $categories && ! is_wp_error( $categories ) && ! empty( $categories ) ) ? self::decoded_name( $categories[0] ) : '',
			'services'      => ( $services && ! is_wp_error( $services ) ) ? array_map( array( __CLASS__, 'decoded_name' ), $services ) : array(),
			'town_city'     => get_post_meta( $business->ID, 'rbn_town_city', true ),
			'county_region' => get_post_meta( $business->ID, 'rbn_county_region', true ),
		);
	}

	/**
	 * The category/service options available to filter by - only terms
	 * currently used by at least one published business, so the directory
	 * never offers a filter that returns zero results.
	 */
	public static function filter_options() {
		$business_ids = self::scoped_business_ids();

		return array(
			'categories' => self::terms_for( RBN_Taxonomy_Business_Category::TAXONOMY, $business_ids ),
			'services'   => self::terms_for( RBN_Taxonomy_Service::TAXONOMY, $business_ids ),
		);
	}

	/**
	 * IDs of every published business the current viewer is allowed to see
	 * (same community scope as results() above), for scoping the filter
	 * dropdowns - otherwise a UK-based member could be offered a "Beauty &
	 * Hair" filter that only matches an Australian business they'll never
	 * actually be shown, which both misleads them and offers a filter that
	 * always returns zero results (the thing filter_options() otherwise
	 * takes care to avoid).
	 */
	private static function scoped_business_ids() {
		$query = new WP_Query(
			array(
				'post_type'      => RBN_Post_Type_Business::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( self::community_scope_clause() ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		return $query->posts;
	}

	private static function terms_for( $taxonomy, array $object_ids ) {
		if ( empty( $object_ids ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'object_ids' => $object_ids,
				'hide_empty' => true,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_map(
			static function ( $term ) {
				return array(
					'slug' => $term->slug,
					'name' => self::decoded_name( $term ),
				);
			},
			$terms
		);
	}

	/**
	 * Term names are stored with special characters HTML-entity-encoded
	 * (wp_insert_term() runs them through kses on save), so a name like
	 * "Painting & Decorating" is saved as "Painting &amp; Decorating".
	 * Decoding here gives back the plain text these API responses should
	 * carry - callers still need to esc_html() it themselves before
	 * outputting as HTML.
	 */
	private static function decoded_name( WP_Term $term ) {
		return wp_specialchars_decode( $term->name, ENT_QUOTES );
	}
}
