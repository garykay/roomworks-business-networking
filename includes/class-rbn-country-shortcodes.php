<?php
/**
 * [rbn_demonym] and [rbn_country] shortcodes - drop a country's noun-form
 * demonym (e.g. "South African"/"South Africans") or plain name (e.g.
 * "United Kingdom") into existing copy, so branding text that mentions a
 * country stays correct without hand-editing every Heading/Paragraph it
 * appears in.
 *
 * Shortcodes rather than a block: the country reference is usually a word or
 * two embedded inside a larger sentence that's otherwise ordinary rich text
 * ("Built by South Africans, for South Africans", "...entrepreneurs across
 * the United Kingdom..."), not a standalone piece of content - a custom
 * block would force replacing the whole Heading/Paragraph block just to
 * substitute a few words. WordPress already runs do_shortcode() on rendered
 * block content via the_content, so these work inside core Heading/
 * Paragraph blocks with no extra wiring.
 *
 * Both shortcodes resolve "which country" the same way, via `country` or
 * `source`:
 * - `country="ZA"` (ISO 3166-1 alpha-2) or `country="27"` (a country ID) -
 *   an explicit override, takes priority over `source`.
 * - `source="network"` (default) - RBN_Settings::network_origin_country_id(),
 *   i.e. which country this deployment of the plugin represents (South
 *   Africa for a SAFFA-style network).
 * - `source="viewer_origin"` - the logged-in viewer's own "Country of
 *   Origin" profile field, falling back to the network origin country when
 *   logged out or not set.
 * - `source="viewer_current"` - the logged-in viewer's own "Country You
 *   Currently Live In" profile field (e.g. rendering "...entrepreneurs
 *   across the United Kingdom..." for a UK-based member), falling back to
 *   RBN_Settings::default_destination_country_id() when logged out or not
 *   set - unlike origin, there's no single sensible network-wide default
 *   destination, since a diaspora network spans many by design.
 *
 * [rbn_demonym] takes an additional `plural` attribute ("1"/"0", default
 * "0"). Both take `case` ("upper", "lower", "title", or the default "").
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Country_Shortcodes {

	const TAG_DEMONYM = 'rbn_demonym';
	const TAG_COUNTRY = 'rbn_country';

	public static function register() {
		add_shortcode( self::TAG_DEMONYM, array( __CLASS__, 'render_demonym' ) );
		add_shortcode( self::TAG_COUNTRY, array( __CLASS__, 'render_country' ) );

		// Priority 8: before core's do_blocks() (priority 9), while post_content
		// still has raw wp: block comments to splice.
		add_filter( 'the_content', array( __CLASS__, 'inline_standalone_shortcode_blocks' ), 8 );
	}

	public static function render_demonym( $atts ) {
		$atts = shortcode_atts(
			array(
				'country' => '',
				'source'  => 'network',
				'plural'  => '0',
				'case'    => '',
			),
			$atts,
			self::TAG_DEMONYM
		);

		$country_id = self::resolve_country_id( $atts['country'], $atts['source'] );

		if ( ! $country_id ) {
			return '';
		}

		$plural  = self::is_truthy( $atts['plural'] );
		$demonym = RBN_Countries::demonym( $country_id, $plural );

		if ( ! $demonym ) {
			return '';
		}

		return esc_html( self::apply_case( $demonym, $atts['case'] ) );
	}

	public static function render_country( $atts ) {
		$atts = shortcode_atts(
			array(
				'country' => '',
				'source'  => 'network',
				'case'    => '',
			),
			$atts,
			self::TAG_COUNTRY
		);

		$country_id = self::resolve_country_id( $atts['country'], $atts['source'] );
		$country    = $country_id ? RBN_Countries::get_by_id( $country_id ) : null;

		if ( ! $country ) {
			return '';
		}

		return esc_html( self::apply_case( $country->name, $atts['case'] ) );
	}

	/**
	 * An explicit `country` attribute (ISO code or numeric ID) if given,
	 * otherwise the country for the named `source` context. Empty/invalid
	 * input resolves to 0 rather than guessing, so an unconfigured or
	 * mistyped shortcode fails silently (empty output) instead of showing
	 * the wrong country.
	 */
	private static function resolve_country_id( $country_attr, $source ) {
		$country_attr = trim( (string) $country_attr );

		if ( '' !== $country_attr ) {
			if ( ctype_digit( $country_attr ) ) {
				$country = RBN_Countries::get_by_id( absint( $country_attr ) );
				return $country ? (int) $country->id : 0;
			}

			foreach ( RBN_Countries::get_all() as $country ) {
				if ( 0 === strcasecmp( $country->iso_code, $country_attr ) ) {
					return (int) $country->id;
				}
			}

			return 0;
		}

		$user_id = get_current_user_id();

		switch ( $source ) {
			case 'viewer_origin':
				$id = $user_id ? RBN_Countries::get_origin_country_id( $user_id ) : 0;
				return $id ? $id : RBN_Settings::network_origin_country_id();

			case 'viewer_current':
				$id = $user_id ? RBN_Countries::get_current_country_id( $user_id ) : 0;
				return $id ? $id : RBN_Settings::default_destination_country_id();

			default:
				return RBN_Settings::network_origin_country_id();
		}
	}

	private static function is_truthy( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return '' !== $value && '0' !== $value && 'false' !== $value;
	}

	private static function apply_case( $text, $case ) {
		switch ( strtolower( (string) $case ) ) {
			case 'upper':
				return mb_strtoupper( $text );
			case 'lower':
				return mb_strtolower( $text );
			case 'title':
				return mb_convert_case( $text, MB_CASE_TITLE );
			default:
				return $text;
		}
	}

	/**
	 * The core "Shortcode" block renders its content completely unwrapped - no
	 * surrounding tag at all - but it's still a sibling of the Paragraph/
	 * Heading blocks either side of it inside a block-level container, so the
	 * browser puts it on its own line regardless (e.g. an editor typing
	 * "Browse [rbn_demonym]-owned businesses..." across a couple of blocks, or
	 * the block editor splitting a shortcode into its own block after a paste,
	 * ends up with "Browse" / "South Africans" / "-owned businesses..." each
	 * on their own line instead of one sentence). The plugin's shortcodes are
	 * documented to work when typed directly inside a Paragraph/Heading
	 * block's own text - this makes a standalone [rbn_demonym]/[rbn_country]
	 * Shortcode block behave the same way, by splicing it into the text of
	 * whichever Paragraph/Heading block is next to it before do_blocks() (a
	 * later 'the_content' filter) ever turns the block comments into markup.
	 * A shortcode block with no adjacent Paragraph/Heading is left alone -
	 * still renders via do_shortcode() as before, just without inline merging.
	 */
	public static function inline_standalone_shortcode_blocks( $content ) {
		if ( ! has_block( 'shortcode', $content ) ) {
			return $content;
		}

		$blocks = parse_blocks( $content );

		if ( ! self::merge_inline_shortcode_blocks( $blocks ) ) {
			return $content;
		}

		return serialize_blocks( $blocks );
	}

	/**
	 * Walks the block tree (recursing into container blocks like Group/
	 * Columns) looking for a standalone rbn_demonym/rbn_country Shortcode
	 * block, and merges each one it finds into an adjacent Paragraph/Heading
	 * sibling - preferring the previous one, matching how the surrounding
	 * words trailed off (as in "Browse [shortcode]"), and falling back to the
	 * next one otherwise. When both a previous and next text sibling exist,
	 * all three collapse into a single block so the whole sentence ends up in
	 * one <p>/<hX> element rather than two.
	 *
	 * @return bool True if anything was merged (i.e. the tree needs re-serializing).
	 */
	private static function merge_inline_shortcode_blocks( array &$blocks ) {
		$changed = false;

		for ( $i = count( $blocks ) - 1; $i >= 0; $i-- ) {
			if ( ! empty( $blocks[ $i ]['innerBlocks'] ) ) {
				$inner_changed = self::merge_inline_shortcode_blocks( $blocks[ $i ]['innerBlocks'] );

				if ( $inner_changed ) {
					// innerContent interleaves literal HTML chunks with a null
					// placeholder per innerBlocks entry, matched by position -
					// resync it now that innerBlocks has shrunk, rather than
					// leaving stale placeholders serialize_block() would read
					// past the end of the (now shorter) innerBlocks array.
					$blocks[ $i ]['innerContent'] = array_fill( 0, count( $blocks[ $i ]['innerBlocks'] ), null );
					$changed                      = true;
				}
			}

			if ( 'core/shortcode' !== $blocks[ $i ]['blockName'] ) {
				continue;
			}

			$shortcode_text = trim( $blocks[ $i ]['innerHTML'] );

			if ( ! preg_match( '/^\[(rbn_demonym|rbn_country)\b/', $shortcode_text ) ) {
				continue;
			}

			$has_prev = $i > 0 && self::is_text_block( $blocks[ $i - 1 ] );
			$has_next = isset( $blocks[ $i + 1 ] ) && self::is_text_block( $blocks[ $i + 1 ] );

			if ( ! $has_prev && ! $has_next ) {
				continue;
			}

			if ( $has_prev ) {
				$addition = $shortcode_text . ( $has_next ? self::text_block_inner_html( $blocks[ $i + 1 ] ) : '' );
				self::append_to_text_block( $blocks[ $i - 1 ], $addition );
				array_splice( $blocks, $i, $has_next ? 2 : 1 );
			} else {
				self::prepend_to_text_block( $blocks[ $i + 1 ], $shortcode_text );
				array_splice( $blocks, $i, 1 );
			}

			$changed = true;
		}

		return $changed;
	}

	private static function is_text_block( $block ) {
		return isset( $block['blockName'] ) && in_array( $block['blockName'], array( 'core/paragraph', 'core/heading' ), true );
	}

	private static function text_block_inner_html( $block ) {
		if ( preg_match( '/<(p|h[1-6])\b[^>]*>(.*)<\/\1>/is', $block['innerHTML'], $matches ) ) {
			return $matches[2];
		}

		return '';
	}

	private static function append_to_text_block( array &$block, $addition ) {
		if ( '' === $addition ) {
			return;
		}

		if ( preg_match( '/<\/(p|h[1-6])>/i', $block['innerHTML'], $matches, PREG_OFFSET_CAPTURE ) ) {
			$offset                = $matches[0][1];
			$block['innerHTML']    = substr_replace( $block['innerHTML'], $addition, $offset, 0 );
			$block['innerContent'] = array( $block['innerHTML'] );
		}
	}

	private static function prepend_to_text_block( array &$block, $addition ) {
		if ( preg_match( '/<(p|h[1-6])\b[^>]*>/i', $block['innerHTML'], $matches, PREG_OFFSET_CAPTURE ) ) {
			$offset                = $matches[0][1] + strlen( $matches[0][0] );
			$block['innerHTML']    = substr_replace( $block['innerHTML'], $addition, $offset, 0 );
			$block['innerContent'] = array( $block['innerHTML'] );
		}
	}
}
