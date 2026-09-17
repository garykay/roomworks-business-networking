<?php
/**
 * Business category ("Business Type") taxonomy, seeded with a starter
 * vocabulary on activation (see seed_defaults()).
 *
 * The wp-admin term list and the default REST terms controller stay
 * administrator-only (manage_terms below), but members can still grow this
 * vocabulary from the business form: RBN_REST_Business_Categories exposes a
 * narrow find-or-create endpoint gated on edit_rbn_businesses instead, so
 * picking a business type that doesn't exist yet doesn't require full
 * taxonomy-management access.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Taxonomy_Business_Category {

	const TAXONOMY = 'rbn_business_category';

	/**
	 * Starter vocabulary so the "Business Type" field isn't empty the first
	 * time the plugin is activated. Administrators can still add, rename or
	 * remove categories afterwards - this only ever adds terms that don't
	 * already exist (see seed_defaults()), so it's safe to run repeatedly.
	 */
	const DEFAULT_TERMS = array(
		'Accounting & Bookkeeping',
		'Architecture & Design',
		'Automotive Services',
		'Beauty & Hair',
		'Building & Construction',
		'Catering & Events',
		'Childcare & Education',
		'Cleaning Services',
		'Consulting',
		'Electrical Services',
		'Estate Agents & Property',
		'Fitness & Wellness',
		'Florists & Gifts',
		'Gardening & Landscaping',
		'Health Services',
		'IT & Computer Services',
		'Legal Services',
		'Marketing & Advertising',
		'Painting & Decorating',
		'Photography & Video',
		'Plumbing & Heating',
		'Printing & Signage',
		'Removals & Storage',
		'Restaurants & Cafes',
		'Retail & Shops',
		'Roofing',
		'Security Services',
		'Tradespeople & Handyman',
		'Transport & Logistics',
		'Web Design & Development',
	);

	public static function register() {
		$labels = array(
			'name'          => _x( 'Business Types', 'taxonomy general name', 'roomworks-business-networking' ),
			'singular_name' => _x( 'Business Type', 'taxonomy singular name', 'roomworks-business-networking' ),
			'search_items'  => __( 'Search Business Types', 'roomworks-business-networking' ),
			'all_items'     => __( 'All Business Types', 'roomworks-business-networking' ),
			'edit_item'     => __( 'Edit Business Type', 'roomworks-business-networking' ),
			'update_item'   => __( 'Update Business Type', 'roomworks-business-networking' ),
			'add_new_item'  => __( 'Add New Business Type', 'roomworks-business-networking' ),
			'new_item_name' => __( 'New Business Type Name', 'roomworks-business-networking' ),
			'menu_name'     => __( 'Business Types', 'roomworks-business-networking' ),
		);

		register_taxonomy(
			self::TAXONOMY,
			array( RBN_Post_Type_Business::POST_TYPE ),
			array(
				'labels'            => $labels,
				'hierarchical'      => true,
				'public'            => true,
				// REST-exposed so it can appear in the block editor/Site
				// Editor (e.g. a Post Terms block on the business template)
				// - term names are already shown publicly on the business
				// profile page, so this isn't a new exposure.
				'show_in_rest'      => true,
				'rest_base'         => 'business-categories',
				'show_admin_column' => true,
				// Members can only assign categories via wp-admin/the
				// default REST terms controller, not create/edit/delete
				// them there - RBN_REST_Business_Categories is the one
				// member-facing exception, see the class docblock above.
				'capabilities'      => array(
					'manage_terms' => 'manage_rbn_business_categories',
					'edit_terms'   => 'manage_rbn_business_categories',
					'delete_terms' => 'manage_rbn_business_categories',
					'assign_terms' => 'edit_rbn_businesses',
				),
			)
		);
	}

	/**
	 * Inserts the default categories, skipping any name that already exists
	 * (by slug or exact name - see term_exists()) so this can be called on
	 * every activation without creating duplicates.
	 */
	public static function seed_defaults() {
		foreach ( self::DEFAULT_TERMS as $term_name ) {
			if ( ! term_exists( $term_name, self::TAXONOMY ) ) {
				wp_insert_term( $term_name, self::TAXONOMY );
			}
		}
	}
}
