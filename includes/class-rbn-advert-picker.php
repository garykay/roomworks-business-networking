<?php
/**
 * Post-editor sidebar panel for 'post' and 'page': lets an admin choose
 * which rbn_advert is assigned to that page/post, stored as the
 * `rbn_advert_id` meta. The roomworks-advertising-block reads this meta (via
 * block context) when placed inside a Site Editor template to decide which
 * advert to render there - see RBN_Post_Type_Advert for the advert content
 * itself and src/roomworks-advertising-block/render.php for where this meta
 * is read.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Advert_Picker {

	const META_KEY = 'rbn_advert_id';

	const POST_TYPES = array( 'post', 'page' );

	public static function register_meta() {
		foreach ( self::POST_TYPES as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'              => 'integer',
					'single'            => true,
					'default'           => 0,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	/**
	 * The picker only matters on 'post'/'page' edit screens (register_meta()
	 * above is likewise scoped to those), so there's no reason to load its
	 * script into every other post type's editor.
	 */
	public static function enqueue_editor_script() {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, self::POST_TYPES, true ) ) {
			return;
		}

		$asset_file = RBN_PLUGIN_DIR . 'build/advert-picker.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'rbn-advert-picker',
			plugins_url( 'build/advert-picker.js', RBN_PLUGIN_DIR . 'roomworks-business-networking.php' ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'rbn-advert-picker', 'roomworks-business-networking' );
	}
}
