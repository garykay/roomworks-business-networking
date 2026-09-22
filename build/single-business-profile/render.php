<?php
/**
 * Renders the business supplied by block context (postId/postType) - i.e.
 * whichever business post the surrounding singular template is currently
 * displaying. All markup lives in RBN_Templates::business_profile() so it
 * isn't duplicated between this and anywhere else that might reuse it.
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

$business_id = ! empty( $block->context['postId'] ) ? absint( $block->context['postId'] ) : get_the_ID();

if ( ! $business_id ) {
	return;
}

$business = get_post( $business_id );

if ( ! $business || RBN_Post_Type_Business::POST_TYPE !== $business->post_type ) {
	return;
}

// Same rule the directory and REST endpoint follow: don't show a business
// to anyone who isn't allowed to read it (pending, owner/admin only).
if ( ! current_user_can( 'read_post', $business->ID ) ) {
	return;
}

// Read-only GET notice from a just-submitted follow/unfollow form
// (RBN_Business_Follow_Forms redirects back here with ?rbn_notice=) - same
// pattern as the dashboard's own $notice_code, just read directly here
// since this page isn't part of that dashboard.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$notice_code = isset( $_GET['rbn_notice'] ) ? sanitize_key( wp_unslash( $_GET['rbn_notice'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$current_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed above; esc_url_raw()'d below via home_url().
$current_url  = esc_url_raw( home_url( $current_path ? $current_path : '/' ) );
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core function; already returns safe, pre-escaped attribute markup. ?>>
	<?php echo RBN_Templates::business_profile( $business, $current_url, $notice_code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>
</div>
