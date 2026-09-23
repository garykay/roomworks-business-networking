<?php
/**
 * Renders whichever advert is assigned (via the `rbn_advert_id` meta set by
 * RBN_Advert_Picker's sidebar panel) to the post supplied by block context
 * (postId) - i.e. whichever page/post the surrounding singular template is
 * currently displaying. See RBN_Post_Type_Advert for the advert post type
 * itself.
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

$current_post_id = ! empty( $block->context['postId'] ) ? absint( $block->context['postId'] ) : get_the_ID();

if ( ! $current_post_id ) {
	return;
}

$can_edit_context = current_user_can( 'edit_post', $current_post_id );

$advert_id = absint( get_post_meta( $current_post_id, 'rbn_advert_id', true ) );

if ( ! $advert_id ) {
	if ( $can_edit_context ) {
		?>
		<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core function; already returns safe, pre-escaped attribute markup. ?>>
			<p class="rbn-advert__editor-notice">
				<?php esc_html_e( 'No advert selected for this page/post. Choose one from the "Advert" panel in the editor sidebar.', 'roomworks-business-networking' ); ?>
			</p>
		</div>
		<?php
	}
	return;
}

$advert = get_post( $advert_id );

if ( ! $advert || RBN_Post_Type_Advert::POST_TYPE !== $advert->post_type || 'publish' !== $advert->post_status ) {
	if ( $can_edit_context ) {
		?>
		<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<p class="rbn-advert__editor-notice">
				<?php esc_html_e( 'The advert selected for this page/post is missing or unpublished.', 'roomworks-business-networking' ); ?>
			</p>
		</div>
		<?php
	}
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'rbn-advert rbn-advert--custom' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<span class="rbn-advert__label"><?php esc_html_e( 'Advertisement', 'roomworks-business-networking' ); ?></span>
	<div class="rbn-advert__content">
		<?php echo apply_filters( 'the_content', $advert->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content filters already escape/sanitize as core does for any other post content. ?>
	</div>
</div>
