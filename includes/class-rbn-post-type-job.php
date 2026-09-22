<?php
/**
 * The notice board request post type ('rbn_job' internally - kept as-is in
 * code/meta-key naming for continuity with the rest of this file, but
 * user-facing copy throughout calls it a "request": a member posts a task
 * they need done (e.g. "need a new boiler installed"), and any member -
 * typically a business/tradesperson in the matching Business Type category,
 * see RBN_Taxonomy_Business_Category - gets in touch via the contact
 * details on it. Not an employment/job-vacancy board.
 *
 * Unlike rbn_business, a member can self-publish (see RBN_Capabilities -
 * publish_rbn_jobs is granted to members) - requests don't go through admin
 * review, so ownership enforcement via map_meta_cap is the only gate, no
 * separate approval workflow.
 *
 * A request can belong to more than one community (a member may post the
 * same request into several communities they belong to in their current
 * country) - see the class docblock on RBN_Job_Query for how the
 * rbn_community_id meta below models that many-to-many without a new table.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Post_Type_Job {

	const POST_TYPE = 'rbn_job';

	const URGENCY_OPTIONS = array(
		'asap'        => 'ASAP',
		'within_week' => 'Within a week',
		'flexible'    => 'Flexible',
	);

	public static function register() {
		$labels = array(
			'name'               => _x( 'Requests', 'post type general name', 'roomworks-business-networking' ),
			'singular_name'      => _x( 'Request', 'post type singular name', 'roomworks-business-networking' ),
			'add_new_item'       => __( 'Add New Request', 'roomworks-business-networking' ),
			'edit_item'          => __( 'Edit Request', 'roomworks-business-networking' ),
			'new_item'           => __( 'New Request', 'roomworks-business-networking' ),
			'view_item'          => __( 'View Request', 'roomworks-business-networking' ),
			'search_items'       => __( 'Search Requests', 'roomworks-business-networking' ),
			'not_found'          => __( 'No requests found.', 'roomworks-business-networking' ),
			'not_found_in_trash' => __( 'No requests found in Trash.', 'roomworks-business-networking' ),
			'all_items'          => __( 'All Requests', 'roomworks-business-networking' ),
			'menu_name'          => __( 'Notice Board', 'roomworks-business-networking' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => $labels,
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				// Deliberately NOT REST-exposed, unlike rbn_business - the
				// default REST posts controller that show_in_rest would add
				// (/wp/v2/jobs) allows public, unauthenticated reads of
				// published items regardless of RBN_REST_Jobs's own
				// permission_callback, which would directly contradict the
				// "job listings stay signed-in-only, no public view" gate
				// RBN_Access_Control::restrict_members_only_pages() and
				// RBN_REST_Jobs enforce everywhere else. Businesses accept
				// that public-REST trade-off deliberately (see
				// RBN_Post_Type_Business::register_meta()'s docblock and the
				// block-bindings/Site-Editor-template use case it names) -
				// jobs have no equivalent need for it, so there's no reason
				// to take on the same exposure.
				'show_in_rest'       => false,
				'has_archive'        => false,
				'rewrite'            => array(
					'slug'       => 'job',
					'with_front' => false,
				),
				'query_var'          => true,
				'menu_icon'          => 'dashicons-megaphone',
				'supports'           => array( 'title', 'editor', 'author' ),
				'capability_type'    => array( 'rbn_job', 'rbn_jobs' ),
				'map_meta_cap'       => true,
			)
		);

		self::register_meta();
	}

	/**
	 * Structured request fields, stored as individual meta keys - same
	 * reasoning as RBN_Post_Type_Business::register_meta(): stays queryable
	 * if location/budget-based search is added later, rather than one
	 * serialized blob.
	 */
	private static function register_meta() {
		$fields = array(
			'rbn_urgency'       => 'sanitize_text_field',
			'rbn_town_city'     => 'sanitize_text_field',
			'rbn_county_region' => 'sanitize_text_field',
			'rbn_budget'        => 'sanitize_text_field',
			'rbn_closing_date'  => 'sanitize_text_field',
		);

		foreach ( $fields as $meta_key => $sanitize_callback ) {
			register_post_meta(
				self::POST_TYPE,
				$meta_key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $sanitize_callback,
					'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}

		// Whether the poster's phone number (see the new profile field on
		// RBN_Profile_Forms) is withheld from this specific listing - opt-out
		// stays with the poster, not shown in the public REST shape below
		// since it's only ever read via RBN_Templates::job_profile().
		register_post_meta(
			self::POST_TYPE,
			'rbn_hide_phone',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);

		// Which communities (see RBN_Communities) this job is posted to - a
		// job may belong to more than one, unlike a business's single
		// rbn_community_id (see RBN_Post_Type_Business::register_meta()), so
		// this is deliberately NOT single-valued: one meta row per selected
		// community, matched with a meta_query IN clause in RBN_Job_Query.
		// Not shown in the public REST shape for the same reason as the
		// business one - not private, just no current reader for it outside
		// this plugin's own PHP.
		register_post_meta(
			self::POST_TYPE,
			'rbn_community_id',
			array(
				'type'              => 'integer',
				'single'            => false,
				'show_in_rest'      => false,
				'sanitize_callback' => 'absint',
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}

	/**
	 * Appends the structured fields (urgency, category, location, budget,
	 * closing date, contact, poster) after the description on a single
	 * request page - no dedicated Site-Editor template/block needed (unlike
	 * Single Business Profile), since the default single-CPT template
	 * already renders title/content and this just extends that content.
	 */
	public static function append_details_to_content( $content ) {
		if ( ! is_singular( self::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		return $content . RBN_Templates::job_profile( get_post() );
	}

	/**
	 * The single job page has no dedicated block/Site-Editor template to
	 * carry its own auto-enqueued block.json "style" (see
	 * append_details_to_content()'s docblock) - so job_profile()'s markup
	 * would otherwise render completely unstyled. Reuses the notice board
	 * block's already-compiled stylesheet instead of shipping a second,
	 * near-duplicate one: RBN_Templates::job_profile() and
	 * RBN_Templates::job_card() share the same rbn-card design language on
	 * purpose (see src/roomworks-community-notice-board/style.scss, which
	 * has an unscoped .rbn-job-profile ruleset alongside the block-scoped
	 * grid/card rules specifically so this reuse works).
	 */
	public static function enqueue_profile_style() {
		if ( ! is_singular( self::POST_TYPE ) ) {
			return;
		}

		$style_path = RBN_PLUGIN_DIR . 'build/roomworks-community-notice-board/style-index.css';

		if ( ! file_exists( $style_path ) ) {
			return;
		}

		wp_enqueue_style(
			'rbn-job-profile',
			plugins_url( 'build/roomworks-community-notice-board/style-index.css', RBN_PLUGIN_DIR . 'roomworks-business-networking.php' ),
			array(),
			(string) filemtime( $style_path )
		);
	}
}
