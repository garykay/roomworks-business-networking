<?php
/**
 * Reusable notice board request search/filter query - the single source of
 * truth for "which published requests match these filters", used by both
 * the notice board block's server render and its REST endpoint. Mirrors
 * RBN_Business_Query's structure closely; see that class for the general
 * design (one query layer shared by both callers).
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Job_Query {

	const DEFAULT_PER_PAGE = 12;
	const MAX_PER_PAGE     = 48;

	/**
	 * @param array $args {
	 *     @type string $category Business Type term slug (see
	 *                             RBN_Taxonomy_Business_Category) - what
	 *                             trade/service the request is asking for.
	 *     @type string $urgency  One of RBN_Post_Type_Job::URGENCY_OPTIONS keys.
	 *     @type string $location Free-text match against town/city, county/region.
	 *     @type string $search   Free-text match against request title/description.
	 *     @type int    $page     1-indexed page number.
	 *     @type int    $per_page Results per page.
	 * }
	 */
	public static function results( array $args ) {
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$per_page = isset( $args['per_page'] ) && absint( $args['per_page'] )
			? min( self::MAX_PER_PAGE, absint( $args['per_page'] ) )
			: self::DEFAULT_PER_PAGE;

		$query_args = array(
			'post_type'      => RBN_Post_Type_Job::POST_TYPE,
			'post_status'    => 'publish',
			'paged'          => $page,
			'posts_per_page' => $per_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}

		if ( ! empty( $args['category'] ) ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => RBN_Taxonomy_Business_Category::TAXONOMY,
					'field'    => 'slug',
					'terms'    => sanitize_title( $args['category'] ),
				),
			);
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

	/**
	 * Combines the optional urgency/location filters with the mandatory
	 * community scope and "not closed yet" rule below into one meta_query,
	 * so a caller can never end up with a query missing any of them.
	 */
	private static function scoped_meta_query( array $args ) {
		$clauses = array( self::community_scope_clause(), self::not_closed_clause() );

		if ( ! empty( $args['urgency'] ) && array_key_exists( $args['urgency'], RBN_Post_Type_Job::URGENCY_OPTIONS ) ) {
			$clauses[] = array(
				'key'   => 'rbn_urgency',
				'value' => $args['urgency'],
			);
		}

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
			);
		}

		return array_merge( array( 'relation' => 'AND' ), $clauses );
	}

	/**
	 * Excludes a request whose closing date has passed - the board shouldn't
	 * keep showing a request that's no longer open, and nothing else ever
	 * removes an old one from view. A request with no closing date at all is
	 * always open.
	 */
	private static function not_closed_clause() {
		return array(
			'relation' => 'OR',
			array(
				'key'     => 'rbn_closing_date',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'rbn_closing_date',
				'value'   => '',
				'compare' => '=',
			),
			array(
				'key'     => 'rbn_closing_date',
				'value'   => current_time( 'Y-m-d' ),
				'compare' => '>=',
				'type'    => 'DATE',
			),
		);
	}

	/**
	 * The same membership-and-current-country eligibility rule the
	 * directory uses (see RBN_Business_Query::community_scope_clause()'s
	 * docblock for the full reasoning), applied here against the request's
	 * multi-valued rbn_community_id meta instead of the business's
	 * single-valued one - compare => IN matches a post if ANY of its
	 * community-id rows falls in the viewer's allowed set, which is exactly
	 * what "posted to one of my eligible communities" needs, with no schema
	 * changes required to support the many-to-many.
	 *
	 * The whole notice board additionally requires being logged in at the
	 * page (RBN_Access_Control::restrict_members_only_pages()) and REST
	 * (RBN_REST_Jobs) level, stricter than the business directory - but this
	 * clause is still what's needed to be safe on its own, the same
	 * "identify who's asking, or show nothing" rule as the business one.
	 */
	private static function community_scope_clause() {
		$allowed_community_ids = array();

		if ( is_user_logged_in() ) {
			$user_id            = get_current_user_id();
			$current_country_id = RBN_Countries::get_current_country_id( $user_id );

			if ( $current_country_id ) {
				$allowed_community_ids = wp_list_pluck(
					RBN_Community_Memberships::get_communities_for_user_in_country( $user_id, $current_country_id ),
					'id'
				);
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
	 * Only fields safe to expose to a logged-in board viewer - no owner
	 * email/phone (those are only ever read via RBN_Templates::job_profile()
	 * on the single request page, which applies the poster's own hide-phone
	 * choice - see RBN_Post_Type_Job).
	 */
	private static function normalize( WP_Post $job ) {
		$categories = get_the_terms( $job, RBN_Taxonomy_Business_Category::TAXONOMY );
		$urgency    = get_post_meta( $job->ID, 'rbn_urgency', true );

		return array(
			'id'            => $job->ID,
			'title'         => wp_specialchars_decode( get_the_title( $job ), ENT_QUOTES ),
			'excerpt'       => wp_trim_words( wp_strip_all_tags( $job->post_content ), 25 ),
			'permalink'     => get_permalink( $job ),
			'category'      => ( $categories && ! is_wp_error( $categories ) && ! empty( $categories ) ) ? self::decoded_name( $categories[0] ) : '',
			'urgency'       => isset( RBN_Post_Type_Job::URGENCY_OPTIONS[ $urgency ] ) ? RBN_Post_Type_Job::URGENCY_OPTIONS[ $urgency ] : '',
			'town_city'     => get_post_meta( $job->ID, 'rbn_town_city', true ),
			'county_region' => get_post_meta( $job->ID, 'rbn_county_region', true ),
			'budget'        => self::format_budget( get_post_meta( $job->ID, 'rbn_budget', true ), $job->post_author ),
			'closing_date'  => get_post_meta( $job->ID, 'rbn_closing_date', true ),
		);
	}

	/**
	 * Prefixes a raw rbn_budget value with the currency symbol for the
	 * poster's current country ("the country you live in currently" - where
	 * the work would actually be paid for, as opposed to their origin
	 * country). Budget stays free text (a request may reasonably be a range
	 * like "200-400" rather than one number), so the symbol is only ever
	 * prefixed here at display time, never typed by the member themselves -
	 * see RBN_Templates::job_form()'s Budget field, whose placeholder
	 * deliberately doesn't include one either, to avoid ending up with two.
	 *
	 * Shared with RBN_Templates::job_profile() (the single request page,
	 * which doesn't go through normalize() at all) so the two only ever
	 * agree by construction, not by coincidence.
	 *
	 * @return string The budget with its currency symbol prefixed, or
	 *                 exactly as stored if it's empty or the poster's
	 *                 current country isn't in RBN_Countries::CURRENCY_SYMBOLS.
	 */
	public static function format_budget( $budget, $author_id ) {
		if ( '' === $budget ) {
			return '';
		}

		$symbol = RBN_Countries::currency_symbol( RBN_Countries::get_current_country_id( $author_id ) );

		return $symbol ? $symbol . $budget : $budget;
	}

	/**
	 * The category/urgency options available to filter by - only values
	 * currently used by at least one request the viewer can see, same
	 * "never offer a filter that returns zero results" reasoning as
	 * RBN_Business_Query::filter_options().
	 */
	public static function filter_options() {
		$job_ids = self::scoped_job_ids();

		return array(
			'categories'      => self::category_options( $job_ids ),
			'urgency_options' => self::urgency_options( $job_ids ),
		);
	}

	private static function scoped_job_ids() {
		$query = new WP_Query(
			array(
				'post_type'      => RBN_Post_Type_Job::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( self::community_scope_clause(), self::not_closed_clause() ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		return $query->posts;
	}

	private static function category_options( array $job_ids ) {
		if ( empty( $job_ids ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => RBN_Taxonomy_Business_Category::TAXONOMY,
				'object_ids' => $job_ids,
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
	 * Distinct rbn_urgency values actually in use among $job_ids, mapped to
	 * their label and kept in RBN_Post_Type_Job::URGENCY_OPTIONS's own order
	 * (rather than the arbitrary order the DISTINCT query returns) so the
	 * filter dropdown lists them the same way the request form's own
	 * <select> does.
	 */
	private static function urgency_options( array $job_ids ) {
		if ( empty( $job_ids ) ) {
			return array();
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $job_ids ), '%d' ) );
		$used         = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prepared below; small, admin/filter-dropdown-only lookup, not worth a cache entry.
			$wpdb->prepare(
				"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'rbn_urgency' AND post_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a fixed string of %d tokens, not user input.
				$job_ids
			)
		);

		$options = array();

		foreach ( RBN_Post_Type_Job::URGENCY_OPTIONS as $key => $label ) {
			if ( in_array( $key, $used, true ) ) {
				$options[] = array(
					'value' => $key,
					'name'  => $label,
				);
			}
		}

		return $options;
	}

	/**
	 * Term names are stored with special characters HTML-entity-encoded -
	 * see RBN_Business_Query::decoded_name()'s docblock for why.
	 */
	private static function decoded_name( WP_Term $term ) {
		return wp_specialchars_decode( $term->name, ENT_QUOTES );
	}
}
