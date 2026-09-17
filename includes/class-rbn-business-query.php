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

		$meta_query = self::meta_query( $args );

		if ( $meta_query ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

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

	private static function meta_query( array $args ) {
		if ( empty( $args['location'] ) ) {
			return array();
		}

		$location = sanitize_text_field( $args['location'] );

		return array(
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
		return array(
			'categories' => self::terms_for( RBN_Taxonomy_Business_Category::TAXONOMY ),
			'services'   => self::terms_for( RBN_Taxonomy_Service::TAXONOMY ),
		);
	}

	private static function terms_for( $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
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
