<?php
/**
 * Read-only country search, backing any type-ahead country picker (mirrors
 * the shape of RBN_REST_Business_Categories/RBN_REST_Services, minus the
 * find-or-create half - countries are admin-managed reference data, not
 * something a member can add to). Public: this is non-sensitive reference
 * data needed before a visitor has even registered.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_REST_Countries {

	const NAMESPACE = 'roomworks-business-networking/v1';

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/countries',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'search_countries' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'search' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public static function search_countries( WP_REST_Request $request ) {
		$countries = RBN_Countries::search( $request->get_param( 'search' ), 20 );

		return new WP_REST_Response( array_map( array( __CLASS__, 'format_country' ), $countries ), 200 );
	}

	private static function format_country( $country ) {
		return array(
			'id'       => (int) $country->id,
			'name'     => $country->name,
			'isoCode'  => $country->iso_code,
			'flagCode' => $country->flag_code,
		);
	}
}
