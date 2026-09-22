<?php
/**
 * Renders every published business owned by the current post's author (e.g.
 * the member a blog post was written about/by), so a reader can jump
 * straight from the post to that member's business. Nothing to show
 * (author has no published business) means nothing renders - same silent
 * "return" pattern as single-business-profile/render.php. All card markup
 * lives in RBN_Templates::business_profile() so it isn't duplicated here.
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

$post_id = ! empty( $block->context['postId'] ) ? absint( $block->context['postId'] ) : get_the_ID();

if ( ! $post_id ) {
	return;
}

$post = get_post( $post_id );

if ( ! $post || ! $post->post_author ) {
	return;
}

// The toggle (rbn_show_author_business_profile) is only ever registered
// for the 'post' post type - see RBN_Author_Business_Profile_Toggle - so
// it only applies when that's what this is. It's opt-in (registered
// default is false), so a 'post' has to explicitly turn it on.
if ( 'post' === $post->post_type && ! get_post_meta( $post_id, RBN_Author_Business_Profile_Toggle::META_KEY, true ) ) {
	return;
}

$businesses = RBN_Business_Repository::get_published_for_user( $post->post_author );

if ( empty( $businesses ) ) {
	return;
}

// Read-only GET notice from a just-submitted follow/unfollow form - same
// pattern as single-business-profile/render.php.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$notice_code = isset( $_GET['rbn_notice'] ) ? sanitize_key( wp_unslash( $_GET['rbn_notice'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$current_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed above; esc_url_raw()'d below via home_url().
$current_url  = esc_url_raw( home_url( $current_path ? $current_path : '/' ) );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'rbn-author-business-profile' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core function; already returns safe, pre-escaped attribute markup. ?>>
	<h2 class="rbn-author-business-profile__heading">
		<?php
		echo count( $businesses ) > 1
			? esc_html__( "About the Author's Businesses", 'roomworks-business-networking' )
			: esc_html__( "About the Author's Business", 'roomworks-business-networking' );
		?>
	</h2>
	<?php foreach ( $businesses as $business ) : ?>
		<?php echo RBN_Templates::business_profile( $business, $current_url, $notice_code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within. ?>
	<?php endforeach; ?>
</div>
