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
}
