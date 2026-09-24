<?php
/**
 * Paid ("sponsored") posts: a business pays to publish an article, usually
 * with the single-post-author-business-profile block switched on below it.
 * Advertising rules in practically every host country (UK ASA/CAP, EU
 * consumer law, US FTC, AU AANA, ZA ARB) require paid content to be clearly
 * labelled, and Google requires paid links to carry rel="sponsored" - so
 * this class:
 *
 * - registers the per-post `rbn_is_sponsored` meta and enqueues the sidebar
 *   toggle (src/sponsored-content-toggle) that edits it. Opt-in, same as
 *   RBN_Author_Business_Profile_Toggle;
 * - prints a "Sponsored" label above the post title wherever a
 *   core/post-title block renders a sponsored post (single template and
 *   query loops/archives alike), and prefixes its RSS title;
 * - adds rel="sponsored" to every external link in a sponsored post's
 *   content. The author business card and the advertising block reuse
 *   mark_links_sponsored() / is_sponsored() for their own links.
 *
 * The label text runs through the `rbn_sponsored_label` filter (with the
 * post ID) so wording can later vary per host country, e.g. "Anzeige" for a
 * German community, without touching this class.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Sponsored_Content {

	const META_KEY = 'rbn_is_sponsored';

	const STYLE_HANDLE = 'rbn-sponsored-content';

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
	 * Same scoping as RBN_Author_Business_Profile_Toggle: the meta only
	 * exists on 'post', so the toggle only loads on the 'post' edit screen.
	 */
	public static function enqueue_editor_script() {
		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->post_type ) {
			return;
		}

		$asset_file = RBN_PLUGIN_DIR . 'build/sponsored-content-toggle.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'rbn-sponsored-content-toggle',
			plugins_url( 'build/sponsored-content-toggle.js', RBN_PLUGIN_DIR . 'roomworks-business-networking.php' ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'rbn-sponsored-content-toggle', 'roomworks-business-networking' );
	}

	public static function is_sponsored( $post_id ) {
		$post_id = absint( $post_id );

		return $post_id
			&& 'post' === get_post_type( $post_id )
			&& (bool) get_post_meta( $post_id, self::META_KEY, true );
	}

	public static function get_label( $post_id ) {
		return (string) apply_filters(
			'rbn_sponsored_label',
			__( 'Sponsored', 'roomworks-business-networking' ),
			$post_id
		);
	}

	/**
	 * Adds rel="sponsored" to every link in $html that points off-site,
	 * keeping whatever rel tokens (noopener etc.) are already there.
	 * Relative links, links back to this site, and mailto:/tel: links are
	 * left alone - Google's rule only concerns paid links to other sites.
	 */
	public static function mark_links_sponsored( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, '<a' ) ) {
			return $html;
		}

		$site_host = self::normalise_host( wp_parse_url( home_url(), PHP_URL_HOST ) );
		$processor = new WP_HTML_Tag_Processor( $html );

		while ( $processor->next_tag( 'A' ) ) {
			$href = $processor->get_attribute( 'href' );

			if ( ! is_string( $href ) ) {
				continue;
			}

			$host = self::normalise_host( wp_parse_url( trim( $href ), PHP_URL_HOST ) );

			if ( ! $host || $host === $site_host ) {
				continue;
			}

			$rel    = $processor->get_attribute( 'rel' );
			$tokens = is_string( $rel ) ? preg_split( '/\s+/', strtolower( trim( $rel ) ), -1, PREG_SPLIT_NO_EMPTY ) : array();

			if ( in_array( 'sponsored', $tokens, true ) ) {
				continue;
			}

			$tokens[] = 'sponsored';
			$processor->set_attribute( 'rel', implode( ' ', $tokens ) );
		}

		return $processor->get_updated_html();
	}

	/**
	 * the_content filter. Runs late (priority 20) so it sees the final
	 * markup after blocks, wpautop and shortcodes have all rendered.
	 */
	public static function filter_content( $content ) {
		$post = get_post();

		if ( ! $post || ! self::is_sponsored( $post->ID ) ) {
			return $content;
		}

		return self::mark_links_sponsored( $content );
	}

	/**
	 * render_block_core/post-title filter. Uses the block's own postId
	 * context rather than the global post, so each sponsored post in a
	 * query loop/archive gets its label too - readers need to know an
	 * article is paid before they click through to it, not only after.
	 */
	public static function filter_post_title_block( $block_content, $parsed_block, $instance = null ) {
		$post_id = ( $instance instanceof WP_Block && ! empty( $instance->context['postId'] ) )
			? absint( $instance->context['postId'] )
			: get_the_ID();

		if ( '' === trim( (string) $block_content ) || ! self::is_sponsored( $post_id ) ) {
			return $block_content;
		}

		self::enqueue_style();

		$label = sprintf(
			'<p class="rbn-sponsored-label">%s</p>',
			esc_html( self::get_label( $post_id ) )
		);

		return $label . $block_content;
	}

	/**
	 * Feed readers never see the on-page label, so the title carries it.
	 */
	public static function filter_feed_title( $title ) {
		$post = get_post();

		if ( ! $post || ! self::is_sponsored( $post->ID ) ) {
			return $title;
		}

		/* translators: 1: sponsored label (e.g. "Sponsored"), 2: post title. */
		return sprintf( __( '%1$s: %2$s', 'roomworks-business-networking' ), self::get_label( $post->ID ), $title );
	}

	/**
	 * Only enqueued when a label is actually printed. Block themes render
	 * the whole template before wp_head, so this still lands in <head>.
	 */
	private static function enqueue_style() {
		if ( wp_style_is( self::STYLE_HANDLE, 'enqueued' ) ) {
			return;
		}

		wp_register_style( self::STYLE_HANDLE, false, array(), (string) filemtime( __FILE__ ) );
		wp_enqueue_style( self::STYLE_HANDLE );
		wp_add_inline_style(
			self::STYLE_HANDLE,
			'.rbn-sponsored-label{display:inline-block;margin:0 0 .5rem;padding:.15rem .5rem;border:1px solid #e6e8ee;border-radius:999px;color:#667085;font-size:.6875rem;font-weight:700;letter-spacing:.06em;line-height:1.4;text-transform:uppercase}'
		);
	}

	private static function normalise_host( $host ) {
		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}

		return preg_replace( '/^www\./', '', strtolower( $host ) );
	}
}
