<?php
/**
 * Plugin Name:       Business Networking
 * Description:       Frontend-first business networking platform for community members.
 * Version:           1.0.0
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       roomworks-business-networking
 * Domain Path:       /languages
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'RBN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * This plugin isn't hosted on wp.org, so translations aren't auto-loaded the
 * way core does for wp.org plugins since WP 4.6 - they need loading
 * explicitly. Region-specific terminology (e.g. "Postcode" vs "ZIP Code")
 * is handled the same way as any other translation: each server sets its
 * own Site Language (Settings > General), and WordPress picks the matching
 * .mo file from /languages automatically. See /languages/README.md.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'roomworks-business-networking', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	1
);

require_once RBN_PLUGIN_DIR . 'includes/class-rbn-schema.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-countries.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-rest-countries.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-communities.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-community-memberships.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-community-forms.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-capabilities.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-taxonomy-business-category.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-taxonomy-service.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-post-type-business.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-business-repository.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-business-follows.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-business-follow-forms.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-notices.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-access-control.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-emails.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-member-approval.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-auth-forms.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-profile-forms.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-business-forms.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-account-deletion.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-account-deletion-forms.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-business-query.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-settings.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-approvals.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-communities-admin.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-countries-admin.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-country-shortcodes.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-rest-directory.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-rest-services.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-rest-business-categories.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-post-type-job.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-job-repository.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-job-forms.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-job-query.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-rest-jobs.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-post-authors.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-author-business-profile-toggle.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-post-type-advert.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-advert-picker.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-templates.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-stats.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-activator.php';
require_once RBN_PLUGIN_DIR . 'includes/class-rbn-deactivator.php';

register_activation_hook( __FILE__, array( 'RBN_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RBN_Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'RBN_Schema', 'maybe_upgrade' ) );
add_action( 'plugins_loaded', array( 'RBN_Capabilities', 'maybe_upgrade' ) );

add_action( 'init', array( 'RBN_Taxonomy_Business_Category', 'register' ) );
add_action( 'init', array( 'RBN_Taxonomy_Service', 'register' ) );
add_action( 'init', array( 'RBN_Post_Type_Business', 'register' ) );
add_action( 'init', array( 'RBN_Post_Type_Job', 'register' ) );
add_filter( 'the_content', array( 'RBN_Post_Type_Job', 'append_details_to_content' ) );
add_action( 'wp_enqueue_scripts', array( 'RBN_Post_Type_Job', 'enqueue_profile_style' ) );

add_action( 'init', array( 'RBN_Auth_Forms', 'handle_request' ) );
add_action( 'init', array( 'RBN_Profile_Forms', 'handle_request' ) );
add_action( 'init', array( 'RBN_Business_Forms', 'handle_request' ) );
add_action( 'init', array( 'RBN_Business_Forms', 'handle_delete_request' ) );
add_action( 'init', array( 'RBN_Account_Deletion_Forms', 'handle_request' ) );
add_action( 'init', array( 'RBN_Community_Forms', 'handle_request' ) );
add_action( 'init', array( 'RBN_Job_Forms', 'handle_request' ) );
add_action( 'init', array( 'RBN_Business_Follow_Forms', 'handle_request' ) );

add_action( 'before_delete_post', array( 'RBN_Business_Follows', 'cleanup_on_business_deleted' ) );

add_filter( 'wp_dropdown_users_args', array( 'RBN_Post_Authors', 'filter_dropdown_users_args' ) );
add_filter( 'rest_user_query', array( 'RBN_Post_Authors', 'filter_rest_user_query' ), 10, 2 );

add_action( 'init', array( 'RBN_Author_Business_Profile_Toggle', 'register_meta' ) );
add_action( 'enqueue_block_editor_assets', array( 'RBN_Author_Business_Profile_Toggle', 'enqueue_editor_script' ) );

add_action( 'init', array( 'RBN_Post_Type_Advert', 'register' ) );

add_action( 'init', array( 'RBN_Advert_Picker', 'register_meta' ) );
add_action( 'enqueue_block_editor_assets', array( 'RBN_Advert_Picker', 'enqueue_editor_script' ) );

add_action( RBN_Account_Deletion::CRON_HOOK, array( 'RBN_Account_Deletion', 'process_deletion' ) );

add_action( 'init', array( 'RBN_Member_Approval', 'maybe_handle_activation' ) );
add_filter( 'wp_authenticate_user', array( 'RBN_Member_Approval', 'block_pending_login' ), 10, 2 );
add_filter( 'manage_users_columns', array( 'RBN_Member_Approval', 'add_column' ) );
add_filter( 'manage_users_custom_column', array( 'RBN_Member_Approval', 'render_column' ), 10, 3 );
add_filter( 'user_row_actions', array( 'RBN_Member_Approval', 'add_row_actions' ), 10, 2 );
add_action( 'admin_action_rbn_approve_member', array( 'RBN_Member_Approval', 'handle_approve' ) );
add_action( 'admin_action_rbn_reject_member', array( 'RBN_Member_Approval', 'handle_reject' ) );
add_action( 'admin_action_rbn_resend_activation', array( 'RBN_Member_Approval', 'handle_resend_activation' ) );

add_action( 'admin_menu', array( 'RBN_Approvals', 'register_menu' ) );
add_action( 'admin_action_rbn_cancel_deletion', array( 'RBN_Approvals', 'handle_cancel_deletion' ) );

add_action( 'template_redirect', array( 'RBN_Access_Control', 'restrict_members_only_pages' ) );
add_action( 'save_post_page', array( 'RBN_Access_Control', 'flush_login_page_cache' ) );

add_action( 'admin_menu', array( 'RBN_Settings', 'register_menu' ) );
add_action( 'admin_init', array( 'RBN_Settings', 'register_settings' ) );

add_action( 'admin_menu', array( 'RBN_Communities_Admin', 'register_menu' ) );
add_action( 'admin_init', array( 'RBN_Communities_Admin', 'maybe_handle_request' ) );

add_action( 'admin_menu', array( 'RBN_Countries_Admin', 'register_menu' ) );
add_action( 'admin_init', array( 'RBN_Countries_Admin', 'maybe_handle_request' ) );

add_action( 'init', array( 'RBN_Country_Shortcodes', 'register' ) );

add_action( 'rest_api_init', array( 'RBN_REST_Directory', 'register_routes' ) );
add_action( 'rest_api_init', array( 'RBN_REST_Services', 'register_routes' ) );
add_action( 'rest_api_init', array( 'RBN_REST_Business_Categories', 'register_routes' ) );
add_action( 'rest_api_init', array( 'RBN_REST_Countries', 'register_routes' ) );
add_action( 'rest_api_init', array( 'RBN_REST_Jobs', 'register_routes' ) );

/**
 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
 * based on the registered block metadata. Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
 */
function roomworks_business_networking_roomworks_business_networking_block_init() {
	wp_register_block_types_from_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
}
add_action( 'init', 'roomworks_business_networking_roomworks_business_networking_block_init' );
