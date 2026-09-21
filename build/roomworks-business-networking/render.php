<?php
/**
 * Business directory: server-renders the first page of results using the
 * same RBN_Business_Query the REST endpoint uses, so filtering/pagination
 * works even without JS (plain GET request reloads this page). view.js
 * progressively enhances the same filter form and pagination links to
 * fetch from the REST endpoint instead, without a full page reload.
 *
 * The following variables are exposed to the file:
 *     $attributes (array): The block attributes.
 *     $content (string): The block default content.
 *     $block (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Read-only GET filters that re-render the same page/results - a
// nonce would break bookmarking/sharing a filtered directory URL, and
// there's no state change here for a nonce to protect.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$filters = array(
	'category' => isset( $_GET['rbn_category'] ) ? sanitize_title( wp_unslash( $_GET['rbn_category'] ) ) : '',
	'service'  => isset( $_GET['rbn_service'] ) ? sanitize_title( wp_unslash( $_GET['rbn_service'] ) ) : '',
	'location' => isset( $_GET['rbn_location'] ) ? sanitize_text_field( wp_unslash( $_GET['rbn_location'] ) ) : '',
	'search'   => isset( $_GET['rbn_search'] ) ? sanitize_text_field( wp_unslash( $_GET['rbn_search'] ) ) : '',
	'page'     => isset( $_GET['rbn_page'] ) ? absint( $_GET['rbn_page'] ) : 1,
);
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$results        = RBN_Business_Query::results( $filters );
$filter_options = RBN_Business_Query::filter_options();

$current_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed above; esc_url_raw()'d below via home_url().
$current_url  = esc_url_raw( home_url( $current_path ? $current_path : '/' ) );
$rest_url     = esc_url_raw( rest_url( 'roomworks-business-networking/v1/businesses' ) );

// The REST endpoint itself stays open to logged-out requests (unchanged -
// see class docblock on RBN_REST_Directory), but results are now scoped to
// the current viewer's own country (RBN_Business_Query::community_scope_clause()),
// which needs to know who's asking. Cookie-based REST auth only applies
// when a matching nonce is sent - without one, a logged-in member's async
// filter/pagination fetches would be treated as a logged-out visitor and
// silently see empty results. Always embedded, same as the nonce already
// used for the Business Type/Services fields' REST calls - harmless for a
// logged-out visitor, since wp_verify_nonce() just fails for them the same
// way a missing header does.
$rest_nonce = wp_create_nonce( 'wp_rest' );

$i18n = array(
	'loading'      => __( 'Loading businesses…', 'roomworks-business-networking' ),
	'error'        => __( 'Something went wrong loading businesses. Please try again.', 'roomworks-business-networking' ),
	'noResults'    => __( 'No businesses found.', 'roomworks-business-networking' ),
	'viewProfile'  => __( 'View Profile', 'roomworks-business-networking' ),
	'clearFilters' => __( 'Clear filters', 'roomworks-business-networking' ),
);
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'rbn-directory' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core function; already returns safe, pre-escaped attribute markup. ?>
	data-rbn-directory
	data-rest-url="<?php echo esc_attr( $rest_url ); ?>"
	data-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
	data-i18n="<?php echo esc_attr( wp_json_encode( $i18n ) ); ?>"
>
	<?php echo RBN_Templates::directory_filters( $filter_options, $filters, $current_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>

	<p class="rbn-directory__status" role="status" aria-live="polite" data-rbn-status></p>

	<div class="rbn-directory__results" data-rbn-results>
		<?php echo RBN_Templates::directory_results( $results ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>

	<div class="rbn-directory__pagination" data-rbn-pagination>
		<?php echo RBN_Templates::directory_pagination( $results, $filters, $current_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</div>
