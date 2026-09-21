<?php
/**
 * Community notice board: a member posts a request for work to be done
 * (e.g. "need a new boiler installed") and any member - typically a
 * business/tradesperson in a matching category - gets in touch via its
 * contact details. Server-renders the first page of requests using the
 * same RBN_Job_Query the REST endpoint uses, so filtering/
 * pagination works even without JS. view.js progressively enhances the same
 * filter form and pagination links to fetch from the REST endpoint instead,
 * without a full page reload - mirrors roomworks-business-networking's
 * render.php exactly, see that file's docblock for the general approach.
 *
 * Unlike the business directory, this page is members-only end to end: the
 * page itself is gated by RBN_Access_Control::restrict_members_only_pages() and
 * the REST endpoint below requires a logged-in request
 * (RBN_REST_Jobs::check_logged_in()) - there's no logged-out/public view of
 * this block at all.
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

// Read-only GET filters that re-render the same page/results - a nonce
// would break bookmarking/sharing a filtered board URL, and there's no
// state change here for a nonce to protect. Same reasoning as the business
// directory's render.php.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$filters = array(
	'category' => isset( $_GET['rbn_category'] ) ? sanitize_title( wp_unslash( $_GET['rbn_category'] ) ) : '',
	'urgency'  => isset( $_GET['rbn_urgency'] ) ? sanitize_text_field( wp_unslash( $_GET['rbn_urgency'] ) ) : '',
	'location' => isset( $_GET['rbn_location'] ) ? sanitize_text_field( wp_unslash( $_GET['rbn_location'] ) ) : '',
	'search'   => isset( $_GET['rbn_search'] ) ? sanitize_text_field( wp_unslash( $_GET['rbn_search'] ) ) : '',
	'page'     => isset( $_GET['rbn_page'] ) ? absint( $_GET['rbn_page'] ) : 1,
);
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$results        = RBN_Job_Query::results( $filters );
$filter_options = RBN_Job_Query::filter_options();

$current_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed above; esc_url_raw()'d below via home_url().
$current_url  = esc_url_raw( home_url( $current_path ? $current_path : '/' ) );
$rest_url     = esc_url_raw( rest_url( 'roomworks-business-networking/v1/jobs' ) );

// Cookie-based REST auth only applies when a matching nonce is sent -
// without one, a logged-in member's async filter/pagination fetches would
// be rejected by RBN_REST_Jobs::check_logged_in() the same as a logged-out
// visitor. Same approach as the business directory's render.php.
$rest_nonce = wp_create_nonce( 'wp_rest' );

$i18n = array(
	'loading'      => __( 'Loading requests…', 'roomworks-business-networking' ),
	'error'        => __( 'Something went wrong loading requests. Please try again.', 'roomworks-business-networking' ),
	'noResults'    => __( 'No requests found.', 'roomworks-business-networking' ),
	'viewDetails'  => __( 'View Details', 'roomworks-business-networking' ),
	'closes'       => __( 'Closes %s', 'roomworks-business-networking' ),
	'clearFilters' => __( 'Clear filters', 'roomworks-business-networking' ),
);
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'rbn-notice-board' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core function; already returns safe, pre-escaped attribute markup. ?>
	data-rbn-notice-board
	data-rest-url="<?php echo esc_attr( $rest_url ); ?>"
	data-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
	data-i18n="<?php echo esc_attr( wp_json_encode( $i18n ) ); ?>"
>
	<?php echo RBN_Templates::notice_board_filters( $filter_options, $filters, $current_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>

	<p class="rbn-directory__status" role="status" aria-live="polite" data-rbn-status></p>

	<div class="rbn-directory__results" data-rbn-results>
		<?php echo RBN_Templates::notice_board_results( $results ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>

	<div class="rbn-directory__pagination" data-rbn-pagination>
		<?php echo RBN_Templates::notice_board_pagination( $results, $filters, $current_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</div>
