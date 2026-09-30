<?php
/**
 * The blog listing's country/community filter bar. Place this block
 * directly above the Query Loop on the "Blog Home" template - it doesn't
 * render any posts itself; RBN_Blog_Filters::filter_main_query() (hooked to
 * pre_get_posts) narrows the Query Loop's own inherited main query when a
 * filter is submitted. See that class's docblock for why this is
 * deliberately opt-in narrowing, not access control.
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

$filter_options = RBN_Blog_Filters::filter_options();
$filters        = RBN_Blog_Filters::current_filters();

$current_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed above; esc_url_raw()'d below via home_url().
$current_url  = esc_url_raw( home_url( $current_path ? $current_path : '/' ) );
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core function; already returns safe, pre-escaped attribute markup. ?>>
	<?php echo RBN_Templates::blog_filters( $filter_options, $filters, $current_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>
</div>
