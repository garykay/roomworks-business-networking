<?php
/**
 * Server-rendered: shows the current visitor's own profile (with edit
 * forms for their profile and business) if logged in, or login/
 * registration forms if logged out. The actual markup lives in
 * RBN_Templates so it can be reused by other blocks later.
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

$current_url = esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed and escaped via esc_url_raw().
// rbn_confirm_deletion/rbn_edit_business only toggle which copy/button or
// which business the account-deletion/business sections show on THIS load;
// stripped here so neither gets carried into the hidden redirect field of
// every other form on the page.
$current_url = remove_query_arg( array( 'rbn_confirm_deletion', 'rbn_edit_business' ), $current_url );
// Read-only: only chooses which (already-sanitized, centrally-defined)
// notice message to display - never a state change, so no nonce applies.
$notice_code = isset( $_GET['rbn_notice'] ) ? sanitize_key( wp_unslash( $_GET['rbn_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'rbn-user-profile' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core function; already returns safe, pre-escaped attribute markup. ?>>
	<?php
	if ( is_user_logged_in() ) {
		echo RBN_Templates::member_dashboard( wp_get_current_user(), $current_url, $notice_code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within RBN_Templates.
	} else {
		echo RBN_Templates::auth_forms( $current_url, $notice_code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	?>
</div>
