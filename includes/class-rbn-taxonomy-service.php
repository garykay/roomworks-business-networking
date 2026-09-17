<?php
/**
 * Service vocabulary. The same taxonomy is used for services a business
 * offers (assigned to rbn_business posts) and, via
 * RBN_Business_Repository's member-needs table, services a member needs -
 * one shared vocabulary so the two can be matched against each other later.
 *
 * The wp-admin term list and the default REST terms controller stay
 * administrator-only (manage_terms below), but members can still grow this
 * vocabulary from the business form: RBN_REST_Services exposes a narrow
 * find-or-create endpoint gated on edit_rbn_businesses instead, so adding a
 * service they offer doesn't require full taxonomy-management access.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Taxonomy_Service {

	const TAXONOMY = 'rbn_service';

	public static function register() {
		$labels = array(
			'name'          => _x( 'Services', 'taxonomy general name', 'roomworks-business-networking' ),
			'singular_name' => _x( 'Service', 'taxonomy singular name', 'roomworks-business-networking' ),
			'search_items'  => __( 'Search Services', 'roomworks-business-networking' ),
			'all_items'     => __( 'All Services', 'roomworks-business-networking' ),
			'edit_item'     => __( 'Edit Service', 'roomworks-business-networking' ),
			'update_item'   => __( 'Update Service', 'roomworks-business-networking' ),
			'add_new_item'  => __( 'Add New Service', 'roomworks-business-networking' ),
			'new_item_name' => __( 'New Service Name', 'roomworks-business-networking' ),
			'menu_name'     => __( 'Services', 'roomworks-business-networking' ),
		);

		register_taxonomy(
			self::TAXONOMY,
			array( RBN_Post_Type_Business::POST_TYPE ),
			array(
				'labels'            => $labels,
				'hierarchical'      => false,
				'public'            => true,
				// REST-exposed for the same reason as the category taxonomy
				// - block editor/Site Editor support - and for the same
				// reason it's not a new exposure: already public on the
				// business profile page.
				'show_in_rest'      => true,
				'rest_base'         => 'services',
				'show_admin_column' => true,
				// Members may only assign existing services; creating/editing/
				// deleting the vocabulary stays administrator-only.
				'capabilities'      => array(
					'manage_terms' => 'manage_rbn_services',
					'edit_terms'   => 'manage_rbn_services',
					'delete_terms' => 'manage_rbn_services',
					'assign_terms' => 'edit_rbn_businesses',
				),
			)
		);
	}
}
