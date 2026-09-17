<?php
/**
 * REST endpoints backing the Business Type tag-input on the business form:
 * a public search so a member can find existing categories as they type,
 * and a find-or-create endpoint so they can add a business type that
 * doesn't exist yet without needing manage_rbn_business_categories - full
 * taxonomy management stays administrator-only (see
 * class-rbn-taxonomy-business-category.php). Mirrors RBN_REST_Services;
 * kept separate since it targets a different taxonomy.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_REST_Business_Categories {

	const NAMESPACE = 'roomworks-business-networking/v1';

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/business-categories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'search_categories' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'search' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'find_or_create_category' ),
					'permission_callback' => array( __CLASS__, 'can_add_category' ),
					'args'                => array(
						'name' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	public static function can_add_category() {
		// Same capability the business form itself requires to add a
		// business in the first place - not manage_rbn_business_categories,
		// which is reserved for administering the vocabulary via wp-admin.
		return is_user_logged_in() && current_user_can( 'edit_rbn_businesses' );
	}

	public static function search_categories( WP_REST_Request $request ) {
		$terms = get_terms(
			array(
				'taxonomy'   => RBN_Taxonomy_Business_Category::TAXONOMY,
				'hide_empty' => false,
				'search'     => $request->get_param( 'search' ),
				'number'     => 20,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		return new WP_REST_Response( array_map( array( __CLASS__, 'format_term' ), $terms ), 200 );
	}

	public static function find_or_create_category( WP_REST_Request $request ) {
		$name = trim( sanitize_text_field( $request->get_param( 'name' ) ) );

		if ( '' === $name ) {
			return new WP_Error(
				'rbn_category_name_required',
				__( 'Please enter a business type.', 'roomworks-business-networking' ),
				array( 'status' => 400 )
			);
		}

		$result = wp_insert_term( $name, RBN_Taxonomy_Business_Category::TAXONOMY );

		if ( is_wp_error( $result ) ) {
			if ( 'term_exists' !== $result->get_error_code() ) {
				return new WP_Error(
					'rbn_category_create_failed',
					$result->get_error_message(),
					array( 'status' => 400 )
				);
			}

			// wp_insert_term() found a matching term (by slug or exact
			// name) instead of creating a duplicate; use that one.
			$term_id = (int) $result->get_error_data();
		} else {
			$term_id = (int) $result['term_id'];
		}

		$term = get_term( $term_id, RBN_Taxonomy_Business_Category::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error(
				'rbn_category_not_found',
				__( 'Something went wrong adding that business type.', 'roomworks-business-networking' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( self::format_term( $term ), 200 );
	}

	/**
	 * Term names are stored with special characters HTML-entity-encoded
	 * (wp_insert_term() runs them through kses on save), so decode before
	 * sending JSON - the JS renders this with textContent, which doesn't
	 * interpret entities, so it needs the plain text as-is.
	 */
	private static function format_term( $term ) {
		return array(
			'id'   => $term->term_id,
			'name' => wp_specialchars_decode( $term->name, ENT_QUOTES ),
			'slug' => $term->slug,
		);
	}
}
