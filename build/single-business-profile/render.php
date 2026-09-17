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
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = ! empty( $block->context['postId'] ) ? absint( $block->context['postId'] ) : get_the_ID();

if ( ! $post_id ) {
	return;
}

$business = get_post( $post_id );

if ( ! $business || RBN_Post_Type_Business::POST_TYPE !== $business->post_type ) {
	return;
}

// Same rule the directory and REST endpoint follow: don't show a business
// to anyone who isn't allowed to read it (pending, owner/admin only).
if ( ! current_user_can( 'read_post', $business->ID ) ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core function; already returns safe, pre-escaped attribute markup. ?>>
	<?php echo RBN_Templates::business_profile( $business ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>
</div>
