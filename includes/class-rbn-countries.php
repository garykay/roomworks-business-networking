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
	 * Currency symbol for a country's currency, keyed by ISO 3166-1 alpha-2
	 * code - not a complete ISO 4217 mapping for all ~195 seeded countries,
	 * just the ones most likely to come up here (major economies plus the
	 * origin/destination countries a diaspora network like this one
	 * realistically spans). A country missing from this list simply gets no
	 * symbol prefixed - see currency_symbol() - rather than guessing wrong.
	 */
	const CURRENCY_SYMBOLS = array(
		'GB' => '£',
		'US' => '$',
		'ZA' => 'R',
		'NG' => '₦',
		'IN' => '₹',
		'PK' => '₨',
		'KE' => 'KSh',
		'GH' => 'GH₵',
		'ZW' => 'Z$',
		'UG' => 'USh',
		'TZ' => 'TSh',
		'ZM' => 'ZK',
		'MW' => 'MK',
		'MZ' => 'MT',
		'NA' => 'N$',
		'BW' => 'P',
		'LS' => 'L',
		'SZ' => 'L',
		'RW' => 'FRw',
		'ET' => 'Br',
		'EG' => 'E£',
		'MA' => 'DH',
		'TN' => 'DT',
		'DZ' => 'DA',
		'AU' => 'A$',
		'NZ' => 'NZ$',
		'CA' => 'C$',
		'IE' => '€',
		'DE' => '€',
		'FR' => '€',
		'ES' => '€',
		'IT' => '€',
		'NL' => '€',
		'BE' => '€',
		'PT' => '€',
		'AT' => '€',
		'GR' => '€',
		'FI' => '€',
		'PL' => 'zł',
		'SE' => 'kr',
		'NO' => 'kr',
		'DK' => 'kr',
		'CH' => 'CHF',
		'CZ' => 'Kč',
		'HU' => 'Ft',
		'RO' => 'lei',
		'RU' => '₽',
		'TR' => '₺',
		'AE' => 'AED',
		'SA' => 'SR',
		'QA' => 'QR',
		'KW' => 'KD',
		'BH' => 'BD',
		'OM' => 'OR',
		'IL' => '₪',
		'BR' => 'R$',
		'MX' => 'Mex$',
		'AR' => 'AR$',
		'CL' => 'CL$',
		'CO' => 'COL$',
		'PE' => 'S/',
		'JP' => '¥',
		'CN' => '¥',
		'KR' => '₩',
		'HK' => 'HK$',
		'SG' => 'S$',
		'MY' => 'RM',
		'TH' => '฿',
		'VN' => '₫',
		'PH' => '₱',
		'ID' => 'Rp',
		'BD' => '৳',
		'LK' => 'Rs',
		'NP' => 'Rs',
		'JM' => 'J$',
		'TT' => 'TT$',
	);

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
	 * Default demonyms (noun form, e.g. "South African"/"South Africans", not
	 * the adjective) for DEFAULT_COUNTRIES, keyed by ISO 3166-1 alpha-2 code
	 * rather than positionally - see seed_defaults(). Format:
	 * iso_code => array( singular, plural ).
	 *
	 * A handful of these are genuinely ambiguous or contested (e.g. Botswana:
	 * "Motswana"/"Batswana" is the traditional form, "Botswanan" is common in
	 * English-language media) - admins can correct any entry from the
	 * Countries admin screen (RBN_Countries_Admin) without a code change, per
	 * the scalability spec's "do not hard-code countries" rule.
	 */
	const DEFAULT_DEMONYMS = array(
		'AF' => array( 'Afghan', 'Afghans' ),
		'AL' => array( 'Albanian', 'Albanians' ),
		'DZ' => array( 'Algerian', 'Algerians' ),
		'AD' => array( 'Andorran', 'Andorrans' ),
		'AO' => array( 'Angolan', 'Angolans' ),
		'AG' => array( 'Antiguan', 'Antiguans' ),
		'AR' => array( 'Argentinian', 'Argentinians' ),
		'AM' => array( 'Armenian', 'Armenians' ),
		'AU' => array( 'Australian', 'Australians' ),
		'AT' => array( 'Austrian', 'Austrians' ),
		'AZ' => array( 'Azerbaijani', 'Azerbaijanis' ),
		'BS' => array( 'Bahamian', 'Bahamians' ),
		'BH' => array( 'Bahraini', 'Bahrainis' ),
		'BD' => array( 'Bangladeshi', 'Bangladeshis' ),
		'BB' => array( 'Barbadian', 'Barbadians' ),
		'BY' => array( 'Belarusian', 'Belarusians' ),
		'BE' => array( 'Belgian', 'Belgians' ),
		'BZ' => array( 'Belizean', 'Belizeans' ),
		'BJ' => array( 'Beninese', 'Beninese' ),
		'BT' => array( 'Bhutanese', 'Bhutanese' ),
		'BO' => array( 'Bolivian', 'Bolivians' ),
		'BA' => array( 'Bosnian', 'Bosnians' ),
		'BW' => array( 'Motswana', 'Batswana' ),
		'BR' => array( 'Brazilian', 'Brazilians' ),
		'BN' => array( 'Bruneian', 'Bruneians' ),
		'BG' => array( 'Bulgarian', 'Bulgarians' ),
		'BF' => array( 'Burkinabe', 'Burkinabe' ),
		'BI' => array( 'Burundian', 'Burundians' ),
		'CV' => array( 'Cabo Verdean', 'Cabo Verdeans' ),
		'KH' => array( 'Cambodian', 'Cambodians' ),
		'CM' => array( 'Cameroonian', 'Cameroonians' ),
		'CA' => array( 'Canadian', 'Canadians' ),
		'CF' => array( 'Central African', 'Central Africans' ),
		'TD' => array( 'Chadian', 'Chadians' ),
		'CL' => array( 'Chilean', 'Chileans' ),
		'CN' => array( 'Chinese', 'Chinese' ),
		'CO' => array( 'Colombian', 'Colombians' ),
		'KM' => array( 'Comoran', 'Comorans' ),
		'CG' => array( 'Congolese', 'Congolese' ),
		'CD' => array( 'Congolese', 'Congolese' ),
		'CR' => array( 'Costa Rican', 'Costa Ricans' ),
		'HR' => array( 'Croatian', 'Croatians' ),
		'CU' => array( 'Cuban', 'Cubans' ),
		'CY' => array( 'Cypriot', 'Cypriots' ),
		'CZ' => array( 'Czech', 'Czechs' ),
		'DK' => array( 'Dane', 'Danes' ),
		'DJ' => array( 'Djiboutian', 'Djiboutians' ),
		'DM' => array( 'Dominican', 'Dominicans' ),
		'DO' => array( 'Dominican', 'Dominicans' ),
		'EC' => array( 'Ecuadorian', 'Ecuadorians' ),
		'EG' => array( 'Egyptian', 'Egyptians' ),
		'SV' => array( 'Salvadoran', 'Salvadorans' ),
		'GQ' => array( 'Equatorial Guinean', 'Equatorial Guineans' ),
		'ER' => array( 'Eritrean', 'Eritreans' ),
		'EE' => array( 'Estonian', 'Estonians' ),
		'SZ' => array( 'Swazi', 'Swazis' ),
		'ET' => array( 'Ethiopian', 'Ethiopians' ),
		'FJ' => array( 'Fijian', 'Fijians' ),
		'FI' => array( 'Finn', 'Finns' ),
		'FR' => array( 'French', 'French' ),
		'GA' => array( 'Gabonese', 'Gabonese' ),
		'GM' => array( 'Gambian', 'Gambians' ),
		'GE' => array( 'Georgian', 'Georgians' ),
		'DE' => array( 'German', 'Germans' ),
		'GH' => array( 'Ghanaian', 'Ghanaians' ),
		'GR' => array( 'Greek', 'Greeks' ),
		'GD' => array( 'Grenadian', 'Grenadians' ),
		'GT' => array( 'Guatemalan', 'Guatemalans' ),
		'GN' => array( 'Guinean', 'Guineans' ),
		'GW' => array( 'Bissau-Guinean', 'Bissau-Guineans' ),
		'GY' => array( 'Guyanese', 'Guyanese' ),
		'HT' => array( 'Haitian', 'Haitians' ),
		'HN' => array( 'Honduran', 'Hondurans' ),
		'HK' => array( 'Hong Konger', 'Hong Kongers' ),
		'HU' => array( 'Hungarian', 'Hungarians' ),
		'IS' => array( 'Icelander', 'Icelanders' ),
		'IN' => array( 'Indian', 'Indians' ),
		'ID' => array( 'Indonesian', 'Indonesians' ),
		'IR' => array( 'Iranian', 'Iranians' ),
		'IQ' => array( 'Iraqi', 'Iraqis' ),
		'IE' => array( 'Irish', 'Irish' ),
		'IL' => array( 'Israeli', 'Israelis' ),
		'IT' => array( 'Italian', 'Italians' ),
		'JM' => array( 'Jamaican', 'Jamaicans' ),
		'JP' => array( 'Japanese', 'Japanese' ),
		'JO' => array( 'Jordanian', 'Jordanians' ),
		'KZ' => array( 'Kazakh', 'Kazakhs' ),
		'KE' => array( 'Kenyan', 'Kenyans' ),
		'KI' => array( 'I-Kiribati', 'I-Kiribati' ),
		'KW' => array( 'Kuwaiti', 'Kuwaitis' ),
		'KG' => array( 'Kyrgyz', 'Kyrgyz' ),
		'LA' => array( 'Lao', 'Lao' ),
		'LV' => array( 'Latvian', 'Latvians' ),
		'LB' => array( 'Lebanese', 'Lebanese' ),
		'LS' => array( 'Mosotho', 'Basotho' ),
		'LR' => array( 'Liberian', 'Liberians' ),
		'LY' => array( 'Libyan', 'Libyans' ),
		'LI' => array( 'Liechtensteiner', 'Liechtensteiners' ),
		'LT' => array( 'Lithuanian', 'Lithuanians' ),
		'LU' => array( 'Luxembourger', 'Luxembourgers' ),
		'MO' => array( 'Macanese', 'Macanese' ),
		'MG' => array( 'Malagasy', 'Malagasy' ),
		'MW' => array( 'Malawian', 'Malawians' ),
		'MY' => array( 'Malaysian', 'Malaysians' ),
		'MV' => array( 'Maldivian', 'Maldivians' ),
		'ML' => array( 'Malian', 'Malians' ),
		'MT' => array( 'Maltese', 'Maltese' ),
		'MH' => array( 'Marshallese', 'Marshallese' ),
		'MR' => array( 'Mauritanian', 'Mauritanians' ),
		'MU' => array( 'Mauritian', 'Mauritians' ),
		'MX' => array( 'Mexican', 'Mexicans' ),
		'FM' => array( 'Micronesian', 'Micronesians' ),
		'MD' => array( 'Moldovan', 'Moldovans' ),
		'MC' => array( 'Monegasque', 'Monegasques' ),
		'MN' => array( 'Mongolian', 'Mongolians' ),
		'ME' => array( 'Montenegrin', 'Montenegrins' ),
		'MA' => array( 'Moroccan', 'Moroccans' ),
		'MZ' => array( 'Mozambican', 'Mozambicans' ),
		'MM' => array( 'Burmese', 'Burmese' ),
		'NA' => array( 'Namibian', 'Namibians' ),
		'NR' => array( 'Nauruan', 'Nauruans' ),
		'NP' => array( 'Nepali', 'Nepalis' ),
		'NL' => array( 'Dutch', 'Dutch' ),
		'NZ' => array( 'New Zealander', 'New Zealanders' ),
		'NI' => array( 'Nicaraguan', 'Nicaraguans' ),
		'NE' => array( 'Nigerien', 'Nigeriens' ),
		'NG' => array( 'Nigerian', 'Nigerians' ),
		'KP' => array( 'North Korean', 'North Koreans' ),
		'MK' => array( 'Macedonian', 'Macedonians' ),
		'NO' => array( 'Norwegian', 'Norwegians' ),
		'OM' => array( 'Omani', 'Omanis' ),
		'PK' => array( 'Pakistani', 'Pakistanis' ),
		'PW' => array( 'Palauan', 'Palauans' ),
		'PS' => array( 'Palestinian', 'Palestinians' ),
		'PA' => array( 'Panamanian', 'Panamanians' ),
		'PG' => array( 'Papua New Guinean', 'Papua New Guineans' ),
		'PY' => array( 'Paraguayan', 'Paraguayans' ),
		'PE' => array( 'Peruvian', 'Peruvians' ),
		'PH' => array( 'Filipino', 'Filipinos' ),
		'PL' => array( 'Pole', 'Poles' ),
		'PT' => array( 'Portuguese', 'Portuguese' ),
		'QA' => array( 'Qatari', 'Qataris' ),
		'RO' => array( 'Romanian', 'Romanians' ),
		'RU' => array( 'Russian', 'Russians' ),
		'RW' => array( 'Rwandan', 'Rwandans' ),
		'KN' => array( 'Kittitian', 'Kittitians' ),
		'LC' => array( 'Saint Lucian', 'Saint Lucians' ),
		'VC' => array( 'Vincentian', 'Vincentians' ),
		'WS' => array( 'Samoan', 'Samoans' ),
		'SM' => array( 'Sammarinese', 'Sammarinese' ),
		'ST' => array( 'Sao Tomean', 'Sao Tomeans' ),
		'SA' => array( 'Saudi', 'Saudis' ),
		'SN' => array( 'Senegalese', 'Senegalese' ),
		'RS' => array( 'Serbian', 'Serbians' ),
		'SC' => array( 'Seychellois', 'Seychellois' ),
		'SL' => array( 'Sierra Leonean', 'Sierra Leoneans' ),
		'SG' => array( 'Singaporean', 'Singaporeans' ),
		'SK' => array( 'Slovak', 'Slovaks' ),
		'SI' => array( 'Slovenian', 'Slovenians' ),
		'SB' => array( 'Solomon Islander', 'Solomon Islanders' ),
		'SO' => array( 'Somali', 'Somalis' ),
		'ZA' => array( 'South African', 'South Africans' ),
		'KR' => array( 'South Korean', 'South Koreans' ),
		'SS' => array( 'South Sudanese', 'South Sudanese' ),
		'ES' => array( 'Spaniard', 'Spaniards' ),
		'LK' => array( 'Sri Lankan', 'Sri Lankans' ),
		'SD' => array( 'Sudanese', 'Sudanese' ),
		'SR' => array( 'Surinamese', 'Surinamese' ),
		'SE' => array( 'Swede', 'Swedes' ),
		'CH' => array( 'Swiss', 'Swiss' ),
		'SY' => array( 'Syrian', 'Syrians' ),
		'TW' => array( 'Taiwanese', 'Taiwanese' ),
		'TJ' => array( 'Tajik', 'Tajiks' ),
		'TZ' => array( 'Tanzanian', 'Tanzanians' ),
		'TH' => array( 'Thai', 'Thais' ),
		'TL' => array( 'Timorese', 'Timorese' ),
		'TG' => array( 'Togolese', 'Togolese' ),
		'TO' => array( 'Tongan', 'Tongans' ),
		'TT' => array( 'Trinidadian', 'Trinidadians' ),
		'TN' => array( 'Tunisian', 'Tunisians' ),
		'TR' => array( 'Turk', 'Turks' ),
		'TM' => array( 'Turkmen', 'Turkmens' ),
		'TV' => array( 'Tuvaluan', 'Tuvaluans' ),
		'UG' => array( 'Ugandan', 'Ugandans' ),
		'UA' => array( 'Ukrainian', 'Ukrainians' ),
		'AE' => array( 'Emirati', 'Emiratis' ),
		'GB' => array( 'Briton', 'Britons' ),
		'US' => array( 'American', 'Americans' ),
		'UY' => array( 'Uruguayan', 'Uruguayans' ),
		'UZ' => array( 'Uzbek', 'Uzbeks' ),
		'VU' => array( 'Ni-Vanuatu', 'Ni-Vanuatu' ),
		'VA' => array( 'Vatican', 'Vatican' ),
		'VE' => array( 'Venezuelan', 'Venezuelans' ),
		'VN' => array( 'Vietnamese', 'Vietnamese' ),
		'YE' => array( 'Yemeni', 'Yemenis' ),
		'ZM' => array( 'Zambian', 'Zambians' ),
		'ZW' => array( 'Zimbabwean', 'Zimbabweans' ),
	);

	/**
	 * Inserts the default country list, skipping any ISO code that already
	 * exists, so this can be called on every activation without creating
	 * duplicates or clobbering an admin's edits (e.g. a renamed or
	 * deactivated entry). Also backfills demonyms for rows that already exist
	 * but have none set yet (e.g. a country seeded before DEFAULT_DEMONYMS
	 * was introduced) - only ever fills a blank, never overwrites an admin's
	 * own edit.
	 */
	public static function seed_defaults() {
		global $wpdb;

		$table = RBN_Schema::countries_table();
		$now   = current_time( 'mysql' );

		foreach ( self::DEFAULT_COUNTRIES as $country ) {
			list( $name, $iso_code, $iso3_code ) = $country;

			$demonym          = isset( self::DEFAULT_DEMONYMS[ $iso_code ] ) ? self::DEFAULT_DEMONYMS[ $iso_code ] : array( '', '' );
			$demonym_singular = $demonym[0];
			$demonym_plural   = $demonym[1];

			$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE iso_code = %s", $iso_code ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off seed on activation, not a request-path query.

			if ( $existing_id ) {
				$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"UPDATE {$table} SET demonym_singular = %s, demonym_plural = %s WHERE id = %d AND demonym_singular = '' AND demonym_plural = ''",
						$demonym_singular,
						$demonym_plural,
						$existing_id
					)
				);
				continue;
			}

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				array(
					'name'              => $name,
					'iso_code'          => $iso_code,
					'iso3_code'         => $iso3_code,
					'flag_code'         => $iso_code,
					'demonym_singular'  => $demonym_singular,
					'demonym_plural'    => $demonym_plural,
					'status'            => 'active',
					'created_at'        => $now,
					'updated_at'        => $now,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
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
		$results = $wpdb->get_results( "SELECT id, name, iso_code, iso3_code, flag_code, demonym_singular, demonym_plural FROM {$table} WHERE status = 'active' ORDER BY name ASC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached just below.

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
	 * The currency symbol for a country, or '' if the country doesn't exist
	 * or isn't in CURRENCY_SYMBOLS - callers show the bare, unprefixed value
	 * in that case rather than guessing at a symbol.
	 */
	public static function currency_symbol( $country_id ) {
		$country = self::get_by_id( $country_id );

		if ( ! $country || ! isset( self::CURRENCY_SYMBOLS[ $country->iso_code ] ) ) {
			return '';
		}

		return self::CURRENCY_SYMBOLS[ $country->iso_code ];
	}

	/**
	 * The address-form label for the postcode field, matching the
	 * terminology a member from this country would expect. Falls back to
	 * the UK default ("Postcode") for a country with no distinct term of
	 * its own, or if the country doesn't exist - same fallback pattern as
	 * currency_symbol(). Not a complete per-country list, just the terms
	 * distinct enough from the UK default to be worth calling out.
	 */
	public static function postcode_label( $country_id ) {
		$country = self::get_by_id( $country_id );
		$iso     = $country ? $country->iso_code : '';

		switch ( $iso ) {
			case 'US':
				return __( 'ZIP Code', 'roomworks-business-networking' );
			case 'IE':
				return __( 'Eircode', 'roomworks-business-networking' );
			default:
				return __( 'Postcode', 'roomworks-business-networking' );
		}
	}

	/**
	 * The address-form label for the county/region field - see
	 * postcode_label() above, same fallback to the UK default
	 * ("County / Region").
	 */
	public static function county_region_label( $country_id ) {
		$country = self::get_by_id( $country_id );
		$iso     = $country ? $country->iso_code : '';

		switch ( $iso ) {
			case 'US':
				return __( 'State', 'roomworks-business-networking' );
			case 'AU':
				return __( 'State / Territory', 'roomworks-business-networking' );
			default:
				return __( 'County / Region', 'roomworks-business-networking' );
		}
	}

	/**
	 * The demonym (noun form - "South African"/"South Africans", not the
	 * adjective) for a country, e.g. for RBN_Demonym_Shortcode. Falls back to
	 * the plain country name when nothing is configured, rather than
	 * outputting an empty string - a missing demonym should degrade to
	 * something still readable, not disappear from the page.
	 *
	 * @param int  $country_id
	 * @param bool $plural
	 * @return string
	 */
	public static function demonym( $country_id, $plural = false ) {
		$country = self::get_by_id( $country_id );

		if ( ! $country ) {
			return '';
		}

		$demonym = $plural ? $country->demonym_plural : $country->demonym_singular;

		return $demonym ? $demonym : $country->name;
	}

	/**
	 * Updates a country's demonyms from the admin screen (RBN_Countries_Admin)
	 * - the only place these are ever edited; seed_defaults() only fills a
	 * blank, never overwrites what's saved here.
	 *
	 * @return bool True if the country exists and was updated.
	 */
	public static function update_demonyms( $country_id, $demonym_singular, $demonym_plural ) {
		if ( ! self::get_by_id( $country_id ) ) {
			return false;
		}

		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			RBN_Schema::countries_table(),
			array(
				'demonym_singular' => $demonym_singular,
				'demonym_plural'   => $demonym_plural,
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'id' => absint( $country_id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		self::flush_cache();

		return true;
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
