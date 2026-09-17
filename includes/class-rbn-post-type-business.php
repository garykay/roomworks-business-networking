<?php
/**
 * The business profile post type. One per member, enforced via WordPress
 * capabilities (map_meta_cap) plus RBN_Business_Repository as a safety net -
 * see that class for why a save_post-based check is still needed.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Post_Type_Business {

	const POST_TYPE = 'rbn_business';

	public static function register() {
		$labels = array(
			'name'               => _x( 'Businesses', 'post type general name', 'roomworks-business-networking' ),
			'singular_name'      => _x( 'Business', 'post type singular name', 'roomworks-business-networking' ),
			'add_new_item'       => __( 'Add New Business', 'roomworks-business-networking' ),
			'edit_item'          => __( 'Edit Business', 'roomworks-business-networking' ),
			'new_item'           => __( 'New Business', 'roomworks-business-networking' ),
			'view_item'          => __( 'View Business', 'roomworks-business-networking' ),
			'search_items'       => __( 'Search Businesses', 'roomworks-business-networking' ),
			'not_found'          => __( 'No businesses found.', 'roomworks-business-networking' ),
			'not_found_in_trash' => __( 'No businesses found in Trash.', 'roomworks-business-networking' ),
			'all_items'          => __( 'All Businesses', 'roomworks-business-networking' ),
			'menu_name'          => __( 'Businesses', 'roomworks-business-networking' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => $labels,
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				// REST-exposed so the post type is manageable in the block
				// editor and available as a Site Editor template target
				// (required for Appearance > Editor > Templates > Single
				// Business, since Kadence supports block-templates).
				// Permissions still come from our own capability_type +
				// map_meta_cap below, so the default REST controller
				// enforces the same ownership rules as everywhere else.
				'show_in_rest'       => true,
				'rest_base'          => 'businesses',
				'has_archive'        => false,
				'rewrite'            => array(
					'slug'       => 'business',
					'with_front' => false,
				),
				'query_var'          => true,
				'menu_icon'          => 'dashicons-store',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'author' ),
				'capability_type'    => array( 'rbn_business', 'rbn_businesses' ),
				'map_meta_cap'       => true,
			)
		);

		self::register_meta();
	}

	/**
	 * Structured location and contact fields, stored as individual meta
	 * keys (not one serialized blob) so they stay queryable if
	 * location-based search is added later.
	 *
	 * REST-exposed (read) so they can be used with the block editor's
	 * Block Bindings API - e.g. a Paragraph block in a Site Editor template
	 * bound to "rbn_town_city" - without which the Site Editor could only
	 * touch title/content/featured image, not these fields. This isn't a
	 * new privacy exposure: every one of these fields is already rendered
	 * in plain HTML on the public single-business page for a published
	 * business. Writing still requires edit_post, exactly as before.
	 */
	private static function register_meta() {
		$fields = array(
			'rbn_town_city'     => 'sanitize_text_field',
			'rbn_county_region' => 'sanitize_text_field',
			'rbn_postcode'      => 'sanitize_text_field',
			'rbn_service_area'  => 'sanitize_text_field',
			'rbn_website'       => 'sanitize_url',
			'rbn_phone'         => 'sanitize_text_field',
			'rbn_contact_email' => 'sanitize_email',
		);

		foreach ( $fields as $meta_key => $sanitize_callback ) {
			register_post_meta(
				self::POST_TYPE,
				$meta_key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $sanitize_callback,
					'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}
}
