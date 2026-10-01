<?php
/**
 * Renders whichever advert applies (see
 * RBN_Advert_Picker::resolve_advert_id() - the one picked in the post's
 * "Advert" sidebar panel, else the settings-screen default, unless adverts
 * are switched off for that post) to the post supplied by block context
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

$advert_id = RBN_Advert_Picker::resolve_advert_id( $current_post_id );

if ( ! $advert_id ) {
	if ( $can_edit_context ) {
		?>
		<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core function; already returns safe, pre-escaped attribute markup. ?>>
			<p class="rbn-advert__editor-notice">
				<?php
				if ( RBN_Advert_Picker::adverts_hidden( $current_post_id ) ) {
					esc_html_e( 'Adverts are turned off for this page/post. Turn them back on from the "Advert" panel in the editor sidebar.', 'roomworks-business-networking' );
				} else {
					esc_html_e( 'No advert selected for this page/post. Choose one from the "Advert" panel in the editor sidebar, or set a default advert under Directory Settings.', 'roomworks-business-networking' );
				}
				?>
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
				<?php esc_html_e( 'The advert selected for this page/post (or the default advert) is missing or unpublished.', 'roomworks-business-networking' ); ?>
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
		<?php
		// Every advert is paid for, so every off-site link in it is a paid
		// link and needs rel="sponsored" - see RBN_Sponsored_Content.
		echo RBN_Sponsored_Content::mark_links_sponsored( apply_filters( 'the_content', $advert->post_content ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content filters already escape/sanitize as core does for any other post content.
		?>
	</div>
</div>
