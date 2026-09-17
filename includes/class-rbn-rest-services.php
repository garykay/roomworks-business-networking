<?php
/**
 * REST endpoints backing the Services tag-input on the business form:
 * a public search so a member can find existing terms as they type, and a
 * find-or-create endpoint so they can add a service that doesn't exist yet
 * without needing manage_rbn_services (full taxonomy management stays
 * administrator-only - see class-rbn-taxonomy-service.php).
 *
 * Duplicate terms are prevented by wp_insert_term() itself: it matches an
 * existing term by slug or exact name (case-insensitively, per the terms
 * table's collation) and returns that term's ID instead of creating a new
 * row, which find_or_create_service() relies on below.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_REST_Services {

	const NAMESPACE = 'roomworks-business-networking/v1';

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/services',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'search_services' ),
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
					'callback'            => array( __CLASS__, 'find_or_create_service' ),
					'permission_callback' => array( __CLASS__, 'can_add_service' ),
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

	public static function can_add_service() {
		// Same capability the business form itself requires to add a
		// business in the first place - not manage_rbn_services, which is
		// reserved for administering the vocabulary via wp-admin.
		return is_user_logged_in() && current_user_can( 'edit_rbn_businesses' );
	}

	public static function search_services( WP_REST_Request $request ) {
		$terms = get_terms(
			array(
				'taxonomy'   => RBN_Taxonomy_Service::TAXONOMY,
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

	public static function find_or_create_service( WP_REST_Request $request ) {
		$name = trim( sanitize_text_field( $request->get_param( 'name' ) ) );

		if ( '' === $name ) {
			return new WP_Error(
				'rbn_service_name_required',
				__( 'Please enter a service name.', 'roomworks-business-networking' ),
				array( 'status' => 400 )
			);
		}

		$result = wp_insert_term( $name, RBN_Taxonomy_Service::TAXONOMY );

		if ( is_wp_error( $result ) ) {
			if ( 'term_exists' !== $result->get_error_code() ) {
				return new WP_Error(
					'rbn_service_create_failed',
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

		$term = get_term( $term_id, RBN_Taxonomy_Service::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error(
				'rbn_service_not_found',
				__( 'Something went wrong adding that service.', 'roomworks-business-networking' ),
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
