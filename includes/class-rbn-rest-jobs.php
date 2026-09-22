<?php
/**
 * REST endpoints backing the notice board block's async filtering/
 * pagination. Reuses RBN_Job_Query so the query logic exists in exactly one
 * place, same as RBN_REST_Directory - but unlike that endpoint, this one
 * requires a logged-in request: the notice board is a protected,
 * members-only area (per the session requirement it stays signed-in-only),
 * not the business directory's "public endpoint, scoped results" trade-off.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_REST_Jobs {

	const NAMESPACE = 'roomworks-business-networking/v1';

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/jobs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_jobs' ),
				'permission_callback' => array( __CLASS__, 'check_logged_in' ),
				'args'                => array(
					'category' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
					'urgency'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
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
						'default'           => RBN_Job_Query::DEFAULT_PER_PAGE,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/job-filters',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_filters' ),
				'permission_callback' => array( __CLASS__, 'check_logged_in' ),
			)
		);
	}

	/**
	 * is_user_logged_in() alone would still accept a logged-out browser's
	 * ambient cookies for someone else's session on a cached page, but a
	 * cookie-authenticated REST request also requires a valid wp_rest nonce
	 * (WordPress core's own rest_cookie_check_errors()) - so this, in
	 * practice, is "must present the nonce embedded for the currently
	 * logged-in viewer", not just "must have a cookie".
	 */
	public static function check_logged_in() {
		return is_user_logged_in();
	}

	public static function get_jobs( WP_REST_Request $request ) {
		$results = RBN_Job_Query::results(
			array(
				'category' => $request->get_param( 'category' ),
				'urgency'  => $request->get_param( 'urgency' ),
				'location' => $request->get_param( 'location' ),
				'search'   => $request->get_param( 'search' ),
				'page'     => $request->get_param( 'page' ),
				'per_page' => $request->get_param( 'per_page' ),
			)
		);

		return new WP_REST_Response( $results, 200 );
	}

	public static function get_filters() {
		return new WP_REST_Response( RBN_Job_Query::filter_options(), 200 );
	}
}
