<?php
/**
 * Post-editor sidebar panel for 'post' and 'page': lets an admin choose
 * which rbn_advert is assigned to that page/post (the `rbn_advert_id` meta),
 * or switch adverts off there entirely (the `rbn_hide_adverts` meta). When
 * nothing is picked, single posts fall back to the default advert set on
 * the Directory Settings screen (RBN_Settings::default_advert_id()). The
 * roomworks-advertising-block asks resolve_advert_id() below which advert to
 * render - see RBN_Post_Type_Advert for the advert content itself and
 * src/roomworks-advertising-block/render.php for where it's rendered.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Advert_Picker {

	const META_KEY = 'rbn_advert_id';

	const HIDE_META_KEY = 'rbn_hide_adverts';

	const POST_TYPES = array( 'post', 'page' );

	/**
	 * Only single posts fall back to the settings-screen default - pages
	 * keep showing an advert only when one is picked for them explicitly.
	 */
	const DEFAULT_ADVERT_POST_TYPES = array( 'post' );

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

			register_post_meta(
				$post_type,
				self::HIDE_META_KEY,
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
	}

	/**
	 * Which advert (if any) should show on $post_id: nothing when adverts
	 * are switched off there, else the advert picked for it, else - on
	 * single posts only - the settings-screen default. 0 means none. Doesn't
	 * check the advert is still published; render.php does that so it can
	 * tell an editor why nothing shows.
	 */
	public static function resolve_advert_id( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id || self::adverts_hidden( $post_id ) ) {
			return 0;
		}

		$advert_id = absint( get_post_meta( $post_id, self::META_KEY, true ) );

		if ( $advert_id ) {
			return $advert_id;
		}

		if ( in_array( get_post_type( $post_id ), self::DEFAULT_ADVERT_POST_TYPES, true ) ) {
			return RBN_Settings::default_advert_id();
		}

		return 0;
	}

	public static function adverts_hidden( $post_id ) {
		return (bool) get_post_meta( absint( $post_id ), self::HIDE_META_KEY, true );
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

		// Lets the panel name the fallback advert ("Use default (Summer
		// promo)") instead of a bare "None", so editors can see what a post
		// will actually show without leaving the editor.
		$default_id = in_array( $screen->post_type, self::DEFAULT_ADVERT_POST_TYPES, true ) ? RBN_Settings::default_advert_id() : 0;

		wp_add_inline_script(
			'rbn-advert-picker',
			'window.rbnAdvertPicker = ' . wp_json_encode(
				array(
					'defaultAdvertTitle' => $default_id ? get_the_title( $default_id ) : '',
				)
			) . ';',
			'before'
		);

		wp_set_script_translations( 'rbn-advert-picker', 'roomworks-business-networking' );
	}
}
