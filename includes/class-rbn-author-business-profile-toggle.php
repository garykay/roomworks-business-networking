<?php
/**
 * Per-post on/off switch for the single-post-author-business-profile block:
 * registers the `rbn_show_author_business_profile` post meta it reads, and
 * enqueues the sidebar toggle (src/post-author-business-profile-toggle)
 * that edits it. Defaults to false (hidden) - the block is opt-in per post
 * rather than shown automatically on every post whose author happens to
 * have a published business.
 *
 * Also registers `rbn_author_business_id`: which one of the author's
 * published businesses to show, for an author with more than one - without
 * it, render.php would have no way to know which one the editor picked in
 * the sidebar's radio control, and would have to show every one of them
 * (the old behaviour, which read strangely on a post that's really only
 * about one of an author's several businesses).
 *
 * And a small REST route the sidebar script uses to list a given author's
 * published businesses for that radio control - deliberately keyed by a URL
 * path segment (/author-businesses/{user_id}) rather than the more obvious
 * `?author={id}` query param the default `wp/v2/businesses` collection
 * endpoint already supports: this site's Wordfence firewall blocks any REST
 * request whose query string contains `author=` outright (its stock
 * anti-username-enumeration rule, which doesn't distinguish "someone
 * probing `?author=1` on the front end" from "the block editor filtering
 * its own custom post type"), returning a 404 that looks identical to the
 * route not existing. A path segment sidesteps that filter entirely without
 * needing to touch Wordfence's own configuration.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Author_Business_Profile_Toggle {

	const META_KEY = 'rbn_show_author_business_profile';

	const BUSINESS_META_KEY = 'rbn_author_business_id';

	public static function register_meta() {
		register_post_meta(
			'post',
			self::META_KEY,
			array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => true,
				'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);

		register_post_meta(
			'post',
			self::BUSINESS_META_KEY,
			array(
				'type'          => 'integer',
				'single'        => true,
				'default'       => 0,
				'show_in_rest'  => true,
				'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}

	/**
	 * The toggle only matters on the 'post' edit screen (register_meta()
	 * above is likewise scoped to 'post'), so there's no reason to load its
	 * script into every other post type's editor.
	 */
	public static function enqueue_editor_script() {
		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->post_type ) {
			return;
		}

		$asset_file = RBN_PLUGIN_DIR . 'build/post-author-business-profile-toggle.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'rbn-post-author-business-profile-toggle',
			plugins_url( 'build/post-author-business-profile-toggle.js', RBN_PLUGIN_DIR . 'roomworks-business-networking.php' ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'rbn-post-author-business-profile-toggle', 'roomworks-business-networking' );
	}

	/**
	 * GET roomworks-business-networking/v1/author-businesses/{user_id} - see
	 * this class's docblock for why {user_id} is a path segment rather than
	 * an `?author=` query param. gated on edit_posts (rather than, say,
	 * being fully public) since it only ever needs to run from the post
	 * editor - the data itself isn't sensitive (every published business
	 * returned is already public in the directory), this just isn't a route
	 * anything outside wp-admin has a reason to call.
	 */
	public static function register_rest_route() {
		register_rest_route(
			'roomworks-business-networking/v1',
			'/author-businesses/(?P<user_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_author_businesses' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'user_id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public static function get_author_businesses( WP_REST_Request $request ) {
		$businesses = RBN_Business_Repository::get_published_for_user( $request->get_param( 'user_id' ) );

		return new WP_REST_Response(
			array_map(
				function ( $business ) {
					return array(
						'id'    => $business->ID,
						'title' => wp_specialchars_decode( get_the_title( $business ), ENT_QUOTES ),
					);
				},
				$businesses
			),
			200
		);
	}
}
