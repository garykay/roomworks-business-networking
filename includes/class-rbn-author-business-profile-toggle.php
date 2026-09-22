<?php
/**
 * Per-post on/off switch for the single-post-author-business-profile block:
 * registers the `rbn_show_author_business_profile` post meta it reads, and
 * enqueues the sidebar toggle (src/post-author-business-profile-toggle)
 * that edits it. Defaults to false (hidden) - the block is opt-in per post
 * rather than shown automatically on every post whose author happens to
 * have a published business.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Author_Business_Profile_Toggle {

	const META_KEY = 'rbn_show_author_business_profile';

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
}
