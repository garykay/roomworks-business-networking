<?php
/**
 * Reusable country reference data (see the scalability spec's Section 4)
 * plus the user origin/current-country fields that key the rest of the
 * country/community model. Countries are looked up by stable numeric ID or
 * ISO 3166-1 code everywhere else in the plugin - never by name.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Countries {

	const USER_META_ORIGIN_COUNTRY  = 'rbn_origin_country_id';
	const USER_META_CURRENT_COUNTRY = 'rbn_current_country_id';

	const CACHE_GROUP   = 'rbn_countries';
	const CACHE_KEY_ALL = 'all';

	/**
	 * Starter vocabulary of ISO 3166-1 countries/territories, seeded on
	 * activation - administrators can add, rename or deactivate entries
	 * afterwards via wp-admin (e.g. Kosovo and other territories without a
	 * settled official code aren't included here, but can be added
	 * manually). Format: [ name, iso_code (alpha-2), iso3_code (alpha-3) ].
	 * flag_code is derived from iso_code at seed time.
	 */
	const DEFAULT_COUNTRIES = array(
		array( 'Afghanistan', 'AF', 'AFG' ),
		array( 'Albania', 'AL', 'ALB' ),
		array( 'Algeria', 'DZ', 'DZA' ),
		array( 'Andorra', 'AD', 'AND' ),
		array( 'Angola', 'AO', 'AGO' ),
		array( 'Antigua and Barbuda', 'AG', 'ATG' ),
		array( 'Argentina', 'AR', 'ARG' ),
		array( 'Armenia', 'AM', 'ARM' ),
		array( 'Australia', 'AU', 'AUS' ),
		array( 'Austria', 'AT', 'AUT' ),
		array( 'Azerbaijan', 'AZ', 'AZE' ),
		array( 'Bahamas', 'BS', 'BHS' ),
		array( 'Bahrain', 'BH', 'BHR' ),
		array( 'Bangladesh', 'BD', 'BGD' ),
		array( 'Barbados', 'BB', 'BRB' ),
		array( 'Belarus', 'BY', 'BLR' ),
		array( 'Belgium', 'BE', 'BEL' ),
		array( 'Belize', 'BZ', 'BLZ' ),
		array( 'Benin', 'BJ', 'BEN' ),
		array( 'Bhutan', 'BT', 'BTN' ),
		array( 'Bolivia', 'BO', 'BOL' ),
		array( 'Bosnia and Herzegovina', 'BA', 'BIH' ),
		array( 'Botswana', 'BW', 'BWA' ),
		array( 'Brazil', 'BR', 'BRA' ),
		array( 'Brunei', 'BN', 'BRN' ),
		array( 'Bulgaria', 'BG', 'BGR' ),
		array( 'Burkina Faso', 'BF', 'BFA' ),
		array( 'Burundi', 'BI', 'BDI' ),
		array( 'Cabo Verde', 'CV', 'CPV' ),
		array( 'Cambodia', 'KH', 'KHM' ),
		array( 'Cameroon', 'CM', 'CMR' ),
		array( 'Canada', 'CA', 'CAN' ),
		array( 'Central African Republic', 'CF', 'CAF' ),
		array( 'Chad', 'TD', 'TCD' ),
		array( 'Chile', 'CL', 'CHL' ),
		array( 'China', 'CN', 'CHN' ),
		array( 'Colombia', 'CO', 'COL' ),
		array( 'Comoros', 'KM', 'COM' ),
		array( 'Congo (Republic of the)', 'CG', 'COG' ),
		array( 'Congo (Democratic Republic of the)', 'CD', 'COD' ),
		array( 'Costa Rica', 'CR', 'CRI' ),
		array( 'Croatia', 'HR', 'HRV' ),
		array( 'Cuba', 'CU', 'CUB' ),
		array( 'Cyprus', 'CY', 'CYP' ),
		array( 'Czechia', 'CZ', 'CZE' ),
		array( 'Denmark', 'DK', 'DNK' ),
		array( 'Djibouti', 'DJ', 'DJI' ),
		array( 'Dominica', 'DM', 'DMA' ),
		array( 'Dominican Republic', 'DO', 'DOM' ),
		array( 'Ecuador', 'EC', 'ECU' ),
		array( 'Egypt', 'EG', 'EGY' ),
		array( 'El Salvador', 'SV', 'SLV' ),
		array( 'Equatorial Guinea', 'GQ', 'GNQ' ),
		array( 'Eritrea', 'ER', 'ERI' ),
		array( 'Estonia', 'EE', 'EST' ),
		array( 'Eswatini', 'SZ', 'SWZ' ),
		array( 'Ethiopia', 'ET', 'ETH' ),
		array( 'Fiji', 'FJ', 'FJI' ),
		array( 'Finland', 'FI', 'FIN' ),
		array( 'France', 'FR', 'FRA' ),
		array( 'Gabon', 'GA', 'GAB' ),
		array( 'Gambia', 'GM', 'GMB' ),
		array( 'Georgia', 'GE', 'GEO' ),
		array( 'Germany', 'DE', 'DEU' ),
		array( 'Ghana', 'GH', 'GHA' ),
		array( 'Greece', 'GR', 'GRC' ),
		array( 'Grenada', 'GD', 'GRD' ),
		array( 'Guatemala', 'GT', 'GTM' ),
		array( 'Guinea', 'GN', 'GIN' ),
		array( 'Guinea-Bissau', 'GW', 'GNB' ),
		array( 'Guyana', 'GY', 'GUY' ),
		array( 'Haiti', 'HT', 'HTI' ),
		array( 'Honduras', 'HN', 'HND' ),
		array( 'Hong Kong', 'HK', 'HKG' ),
		array( 'Hungary', 'HU', 'HUN' ),
		array( 'Iceland', 'IS', 'ISL' ),
		array( 'India', 'IN', 'IND' ),
		array( 'Indonesia', 'ID', 'IDN' ),
		array( 'Iran', 'IR', 'IRN' ),
		array( 'Iraq', 'IQ', 'IRQ' ),
		array( 'Ireland', 'IE', 'IRL' ),
		array( 'Israel', 'IL', 'ISR' ),
		array( 'Italy', 'IT', 'ITA' ),
		array( 'Jamaica', 'JM', 'JAM' ),
		array( 'Japan', 'JP', 'JPN' ),
		array( 'Jordan', 'JO', 'JOR' ),
		array( 'Kazakhstan', 'KZ', 'KAZ' ),
		array( 'Kenya', 'KE', 'KEN' ),
		array( 'Kiribati', 'KI', 'KIR' ),
		array( 'Kuwait', 'KW', 'KWT' ),
		array( 'Kyrgyzstan', 'KG', 'KGZ' ),
		array( 'Laos', 'LA', 'LAO' ),
		array( 'Latvia', 'LV', 'LVA' ),
		array( 'Lebanon', 'LB', 'LBN' ),
		array( 'Lesotho', 'LS', 'LSO' ),
		array( 'Liberia', 'LR', 'LBR' ),
		array( 'Libya', 'LY', 'LBY' ),
		array( 'Liechtenstein', 'LI', 'LIE' ),
		array( 'Lithuania', 'LT', 'LTU' ),
		array( 'Luxembourg', 'LU', 'LUX' ),
		array( 'Macao', 'MO', 'MAC' ),
		array( 'Madagascar', 'MG', 'MDG' ),
		array( 'Malawi', 'MW', 'MWI' ),
		array( 'Malaysia', 'MY', 'MYS' ),
		array( 'Maldives', 'MV', 'MDV' ),
		array( 'Mali', 'ML', 'MLI' ),
		array( 'Malta', 'MT', 'MLT' ),
		array( 'Marshall Islands', 'MH', 'MHL' ),
		array( 'Mauritania', 'MR', 'MRT' ),
		array( 'Mauritius', 'MU', 'MUS' ),
		array( 'Mexico', 'MX', 'MEX' ),
		array( 'Micronesia', 'FM', 'FSM' ),
		array( 'Moldova', 'MD', 'MDA' ),
		array( 'Monaco', 'MC', 'MCO' ),
		array( 'Mongolia', 'MN', 'MNG' ),
		array( 'Montenegro', 'ME', 'MNE' ),
		array( 'Morocco', 'MA', 'MAR' ),
		array( 'Mozambique', 'MZ', 'MOZ' ),
		array( 'Myanmar', 'MM', 'MMR' ),
		array( 'Namibia', 'NA', 'NAM' ),
		array( 'Nauru', 'NR', 'NRU' ),
		array( 'Nepal', 'NP', 'NPL' ),
		array( 'Netherlands', 'NL', 'NLD' ),
		array( 'New Zealand', 'NZ', 'NZL' ),
		array( 'Nicaragua', 'NI', 'NIC' ),
		array( 'Niger', 'NE', 'NER' ),
		array( 'Nigeria', 'NG', 'NGA' ),
		array( 'North Korea', 'KP', 'PRK' ),
		array( 'North Macedonia', 'MK', 'MKD' ),
		array( 'Norway', 'NO', 'NOR' ),
		array( 'Oman', 'OM', 'OMN' ),
		array( 'Pakistan', 'PK', 'PAK' ),
		array( 'Palau', 'PW', 'PLW' ),
		array( 'Palestine', 'PS', 'PSE' ),
		array( 'Panama', 'PA', 'PAN' ),
		array( 'Papua New Guinea', 'PG', 'PNG' ),
		array( 'Paraguay', 'PY', 'PRY' ),
		array( 'Peru', 'PE', 'PER' ),
		array( 'Philippines', 'PH', 'PHL' ),
		array( 'Poland', 'PL', 'POL' ),
		array( 'Portugal', 'PT', 'PRT' ),
		array( 'Qatar', 'QA', 'QAT' ),
		array( 'Romania', 'RO', 'ROU' ),
		array( 'Russia', 'RU', 'RUS' ),
		array( 'Rwanda', 'RW', 'RWA' ),
		array( 'Saint Kitts and Nevis', 'KN', 'KNA' ),
		array( 'Saint Lucia', 'LC', 'LCA' ),
		array( 'Saint Vincent and the Grenadines', 'VC', 'VCT' ),
		array( 'Samoa', 'WS', 'WSM' ),
		array( 'San Marino', 'SM', 'SMR' ),
		array( 'Sao Tome and Principe', 'ST', 'STP' ),
		array( 'Saudi Arabia', 'SA', 'SAU' ),
		array( 'Senegal', 'SN', 'SEN' ),
		array( 'Serbia', 'RS', 'SRB' ),
		array( 'Seychelles', 'SC', 'SYC' ),
		array( 'Sierra Leone', 'SL', 'SLE' ),
		array( 'Singapore', 'SG', 'SGP' ),
		array( 'Slovakia', 'SK', 'SVK' ),
		array( 'Slovenia', 'SI', 'SVN' ),
		array( 'Solomon Islands', 'SB', 'SLB' ),
		array( 'Somalia', 'SO', 'SOM' ),
		array( 'South Africa', 'ZA', 'ZAF' ),
		array( 'South Korea', 'KR', 'KOR' ),
		array( 'South Sudan', 'SS', 'SSD' ),
		array( 'Spain', 'ES', 'ESP' ),
		array( 'Sri Lanka', 'LK', 'LKA' ),
		array( 'Sudan', 'SD', 'SDN' ),
		array( 'Suriname', 'SR', 'SUR' ),
		array( 'Sweden', 'SE', 'SWE' ),
		array( 'Switzerland', 'CH', 'CHE' ),
		array( 'Syria', 'SY', 'SYR' ),
		array( 'Taiwan', 'TW', 'TWN' ),
		array( 'Tajikistan', 'TJ', 'TJK' ),
		array( 'Tanzania', 'TZ', 'TZA' ),
		array( 'Thailand', 'TH', 'THA' ),
		array( 'Timor-Leste', 'TL', 'TLS' ),
		array( 'Togo', 'TG', 'TGO' ),
		array( 'Tonga', 'TO', 'TON' ),
		array( 'Trinidad and Tobago', 'TT', 'TTO' ),
		array( 'Tunisia', 'TN', 'TUN' ),
		array( 'Turkey', 'TR', 'TUR' ),
		array( 'Turkmenistan', 'TM', 'TKM' ),
		array( 'Tuvalu', 'TV', 'TUV' ),
		array( 'Uganda', 'UG', 'UGA' ),
		array( 'Ukraine', 'UA', 'UKR' ),
		array( 'United Arab Emirates', 'AE', 'ARE' ),
		array( 'United Kingdom', 'GB', 'GBR' ),
		array( 'United States', 'US', 'USA' ),
		array( 'Uruguay', 'UY', 'URY' ),
		array( 'Uzbekistan', 'UZ', 'UZB' ),
		array( 'Vanuatu', 'VU', 'VUT' ),
		array( 'Vatican City', 'VA', 'VAT' ),
		array( 'Venezuela', 'VE', 'VEN' ),
		array( 'Vietnam', 'VN', 'VNM' ),
		array( 'Yemen', 'YE', 'YEM' ),
		array( 'Zambia', 'ZM', 'ZMB' ),
		array( 'Zimbabwe', 'ZW', 'ZWE' ),
	);

	/**
	 * Inserts the default country list, skipping any ISO code that already
	 * exists, so this can be called on every activation without creating
	 * duplicates or clobbering an admin's edits (e.g. a renamed or
	 * deactivated entry).
	 */
	public static function seed_defaults() {
		global $wpdb;

		$table = RBN_Schema::countries_table();
		$now   = current_time( 'mysql' );

		foreach ( self::DEFAULT_COUNTRIES as $country ) {
			list( $name, $iso_code, $iso3_code ) = $country;

			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE iso_code = %s", $iso_code ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off seed on activation, not a request-path query.

			if ( $exists ) {
				continue;
			}

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array(
					'name'       => $name,
					'iso_code'   => $iso_code,
					'iso3_code'  => $iso3_code,
					'flag_code'  => $iso_code,
					'status'     => 'active',
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		self::flush_cache();
	}

	/**
	 * Every active country, ordered by name, for populating dropdowns.
	 * Cached (object cache; falls back to a transient on hosts without a
	 * persistent one) since this is read on every profile/business form
	 * render and rarely changes.
	 */
	public static function get_all() {
		$cached = wp_cache_get( self::CACHE_KEY_ALL, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$table   = RBN_Schema::countries_table();
		$results = $wpdb->get_results( "SELECT id, name, iso_code, iso3_code, flag_code FROM {$table} WHERE status = 'active' ORDER BY name ASC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached just below.

		$countries = $results ? $results : array();

		wp_cache_set( self::CACHE_KEY_ALL, $countries, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $countries;
	}

	/**
	 * A single country by its stable ID, or null if it doesn't exist / isn't
	 * active. Used to validate a country ID before it's ever stored against
	 * a user, community or business.
	 */
	public static function get_by_id( $country_id ) {
		$country_id = absint( $country_id );

		if ( ! $country_id ) {
			return null;
		}

		foreach ( self::get_all() as $country ) {
			if ( (int) $country->id === $country_id ) {
				return $country;
			}
		}

		return null;
	}

	/**
	 * Free-text match against country name, for a future type-ahead picker
	 * (mirrors RBN_REST_Business_Categories's search shape). Not currently
	 * used by the plain-select profile fields, but kept small and ready so
	 * community/business country pickers can reuse it without another round
	 * of design.
	 */
	public static function search( $term, $limit = 20 ) {
		$term = trim( (string) $term );

		if ( '' === $term ) {
			return array_slice( self::get_all(), 0, $limit );
		}

		$term    = mb_strtolower( $term );
		$matches = array_values(
			array_filter(
				self::get_all(),
				static function ( $country ) use ( $term ) {
					return false !== mb_strpos( mb_strtolower( $country->name ), $term );
				}
			)
		);

		return array_slice( $matches, 0, $limit );
	}

	public static function flush_cache() {
		wp_cache_delete( self::CACHE_KEY_ALL, self::CACHE_GROUP );
	}

	/**
	 * Self-healing check run on every request (see RBN_Schema::maybe_upgrade())
	 * so an empty table always gets re-seeded on the next page load,
	 * regardless of why it ended up empty - e.g. the one-shot seed call tied
	 * to a schema-version bump not completing for some reason. Once
	 * DB_VERSION_OPTION is bumped, maybe_upgrade() never calls install()
	 * again, so without this check a failed first seed would leave the
	 * table empty forever rather than self-correcting. seed_defaults() is
	 * idempotent (skips any iso_code that already exists), so this is safe
	 * to call as often as needed. Cheap: one indexed COUNT(*) per request
	 * when the table already has rows, which is the common case.
	 */
	public static function maybe_seed() {
		global $wpdb;

		$table = RBN_Schema::countries_table();
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- single indexed count, not user-facing.

		if ( 0 === $count ) {
			self::seed_defaults();
		}
	}

	/**
	 * Origin/current-country accessors. Always store and compare by ID -
	 * never by name - per the scalability spec's "do not hard-code
	 * countries" rule.
	 */
	public static function get_origin_country_id( $user_id ) {
		return absint( get_user_meta( $user_id, self::USER_META_ORIGIN_COUNTRY, true ) );
	}

	public static function get_current_country_id( $user_id ) {
		return absint( get_user_meta( $user_id, self::USER_META_CURRENT_COUNTRY, true ) );
	}

	/**
	 * @return bool True if the country ID was valid and saved.
	 */
	public static function set_origin_country( $user_id, $country_id ) {
		if ( ! self::get_by_id( $country_id ) ) {
			return false;
		}

		update_user_meta( $user_id, self::USER_META_ORIGIN_COUNTRY, absint( $country_id ) );
		return true;
	}

	/**
	 * @return bool True if the country ID was valid and saved.
	 */
	public static function set_current_country( $user_id, $country_id ) {
		if ( ! self::get_by_id( $country_id ) ) {
			return false;
		}

		update_user_meta( $user_id, self::USER_META_CURRENT_COUNTRY, absint( $country_id ) );
		return true;
	}
}
