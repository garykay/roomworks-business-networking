<?php
/**
 * Maps the "one member = one business" and "members can't publish or touch
 * each other's businesses" rules onto native WordPress capabilities, so
 * ownership is enforced by core (map_meta_cap) rather than by ad-hoc checks
 * scattered through the app.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Capabilities {

	const CAPS_VERSION_OPTION = 'rbn_caps_version';

	/**
	 * Bump this whenever MEMBER_CAPS/ADMIN_ONLY_CAPS (or the job equivalents)
	 * change, so maybe_upgrade() re-runs add_caps() on an already-active
	 * install. add_caps() itself only ever ran from RBN_Activator::activate()
	 * before the job listing caps were added - fine for a cap set that never
	 * changed after first activation, but the same self-healing problem
	 * RBN_Schema::maybe_upgrade() already solves for the DB schema (a
	 * one-shot hook has no retry on a site that's been active for months).
	 */
	const CAPS_VERSION = '1.1.0';

	/**
	 * Granted to members (subscribers). Deliberately excludes
	 * publish_rbn_businesses, edit_others_rbn_businesses,
	 * delete_others_rbn_businesses, edit_private_rbn_businesses and
	 * delete_private_rbn_businesses, so a member can only manage their own
	 * business and cannot self-publish: WordPress core sets a post's status
	 * to "pending" when the current user lacks the publish capability,
	 * which is exactly the approval workflow the spec requires.
	 */
	const MEMBER_CAPS = array(
		'edit_rbn_business',
		'read_rbn_business',
		'delete_rbn_business',
		'edit_rbn_businesses',
		'delete_rbn_businesses',
		'edit_published_rbn_businesses',
		'delete_published_rbn_businesses',
	);

	/**
	 * Granted to administrators, on top of the member caps: full CRUD across
	 * every business regardless of author, plus managing the category and
	 * service vocabularies.
	 */
	const ADMIN_ONLY_CAPS = array(
		'edit_others_rbn_businesses',
		'publish_rbn_businesses',
		'read_private_rbn_businesses',
		'delete_private_rbn_businesses',
		'delete_others_rbn_businesses',
		'edit_private_rbn_businesses',
		'manage_rbn_business_categories',
		'manage_rbn_services',
	);

	/**
	 * Granted to members, same shape as MEMBER_CAPS above but for job
	 * listings - with one deliberate difference: publish_rbn_jobs IS
	 * included. Job listings auto-publish (no admin review step, unlike
	 * businesses - see RBN_Job_Forms), so a member needs the capability
	 * that actually lets wp_insert_post()/wp_update_post() set the status
	 * to "publish" instead of core silently demoting it to "pending".
	 */
	const JOB_MEMBER_CAPS = array(
		'edit_rbn_job',
		'read_rbn_job',
		'delete_rbn_job',
		'edit_rbn_jobs',
		'delete_rbn_jobs',
		'publish_rbn_jobs',
		'edit_published_rbn_jobs',
		'delete_published_rbn_jobs',
	);

	const JOB_ADMIN_ONLY_CAPS = array(
		'edit_others_rbn_jobs',
		'read_private_rbn_jobs',
		'delete_private_rbn_jobs',
		'delete_others_rbn_jobs',
		'edit_private_rbn_jobs',
	);

	public static function add_caps() {
		$administrator = get_role( 'administrator' );

		if ( $administrator ) {
			foreach ( array_merge( self::MEMBER_CAPS, self::ADMIN_ONLY_CAPS, self::JOB_MEMBER_CAPS, self::JOB_ADMIN_ONLY_CAPS ) as $cap ) {
				$administrator->add_cap( $cap );
			}
		}

		$subscriber = get_role( 'subscriber' );

		if ( $subscriber ) {
			foreach ( array_merge( self::MEMBER_CAPS, self::JOB_MEMBER_CAPS ) as $cap ) {
				$subscriber->add_cap( $cap );
			}
		}
	}

	public static function maybe_upgrade() {
		if ( get_option( self::CAPS_VERSION_OPTION ) !== self::CAPS_VERSION ) {
			self::add_caps();
			update_option( self::CAPS_VERSION_OPTION, self::CAPS_VERSION );
		}
	}

	public static function remove_caps() {
		$administrator = get_role( 'administrator' );

		if ( $administrator ) {
			foreach ( array_merge( self::MEMBER_CAPS, self::ADMIN_ONLY_CAPS, self::JOB_MEMBER_CAPS, self::JOB_ADMIN_ONLY_CAPS ) as $cap ) {
				$administrator->remove_cap( $cap );
			}
		}

		$subscriber = get_role( 'subscriber' );

		if ( $subscriber ) {
			foreach ( array_merge( self::MEMBER_CAPS, self::JOB_MEMBER_CAPS ) as $cap ) {
				$subscriber->remove_cap( $cap );
			}
		}
	}
}
