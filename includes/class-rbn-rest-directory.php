<?php
/**
 * Public, read-only REST endpoints backing the business directory block's
 * async filtering/pagination. Reuses RBN_Business_Query so the query logic
 * exists in exactly one place. No authentication is required - these only
 * ever return published businesses and public fields.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_REST_Directory {

	const NAMESPACE = 'roomworks-business-networking/v1';

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/businesses',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_businesses' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'category' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
					'service'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
					'location' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'search'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => RBN_Settings::per_page(),
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/directory-filters',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_filters' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function get_businesses( WP_REST_Request $request ) {
		$results = RBN_Business_Query::results(
			array(
				'category' => $request->get_param( 'category' ),
				'service'  => $request->get_param( 'service' ),
				'location' => $request->get_param( 'location' ),
				'search'   => $request->get_param( 'search' ),
				'page'     => $request->get_param( 'page' ),
				'per_page' => $request->get_param( 'per_page' ),
			)
		);

		return new WP_REST_Response( $results, 200 );
	}

	public static function get_filters() {
		return new WP_REST_Response( RBN_Business_Query::filter_options(), 200 );
	}
}
