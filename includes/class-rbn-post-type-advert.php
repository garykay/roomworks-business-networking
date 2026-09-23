<?php
/**
 * The advert post type. An admin builds each advert here (title/content/
 * featured image, edited with the block editor like a normal post). The
 * roomworks-advertising-block (src/roomworks-advertising-block) placed
 * inside a Site Editor template renders whichever advert is assigned to the
 * current page/post - see RBN_Advert_Picker for that assignment.
 *
 * Unlike RBN_Post_Type_Business/Job, adverts aren't member-owned content, so
 * this uses the default 'post' capabilities rather than a custom
 * capability_type - only roles that can already edit_posts (Editor/Admin)
 * can manage adverts.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Post_Type_Advert {

	const POST_TYPE = 'rbn_advert';

	public static function register() {
		$labels = array(
			'name'               => _x( 'Adverts', 'post type general name', 'roomworks-business-networking' ),
			'singular_name'      => _x( 'Advert', 'post type singular name', 'roomworks-business-networking' ),
			'add_new_item'       => __( 'Add New Advert', 'roomworks-business-networking' ),
			'edit_item'          => __( 'Edit Advert', 'roomworks-business-networking' ),
			'new_item'           => __( 'New Advert', 'roomworks-business-networking' ),
			'view_item'          => __( 'View Advert', 'roomworks-business-networking' ),
			'search_items'       => __( 'Search Adverts', 'roomworks-business-networking' ),
			'not_found'          => __( 'No adverts found.', 'roomworks-business-networking' ),
			'not_found_in_trash' => __( 'No adverts found in Trash.', 'roomworks-business-networking' ),
			'all_items'          => __( 'All Adverts', 'roomworks-business-networking' ),
			'menu_name'          => __( 'Adverts', 'roomworks-business-networking' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => $labels,
				// Adverts are only ever read by the advertising block via
				// get_post() (see render.php) - there's no standalone single
				// advert page to visit, so this stays out of public queries
				// and permalinks entirely.
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				// REST-exposed so the block editor can be used to build
				// adverts, and so the post/page sidebar picker (see
				// RBN_Advert_Picker) can list published adverts via
				// core-data.
				'show_in_rest'       => true,
				'rest_base'          => 'adverts',
				'has_archive'        => false,
				'rewrite'            => false,
				'query_var'          => false,
				'menu_icon'          => 'dashicons-megaphone',
				'supports'           => array( 'title', 'editor', 'thumbnail' ),
			)
		);
	}
}
