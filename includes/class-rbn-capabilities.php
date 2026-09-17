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

	public static function add_caps() {
		$administrator = get_role( 'administrator' );

		if ( $administrator ) {
			foreach ( array_merge( self::MEMBER_CAPS, self::ADMIN_ONLY_CAPS ) as $cap ) {
				$administrator->add_cap( $cap );
			}
		}

		$subscriber = get_role( 'subscriber' );

		if ( $subscriber ) {
			foreach ( self::MEMBER_CAPS as $cap ) {
				$subscriber->add_cap( $cap );
			}
		}
	}

	public static function remove_caps() {
		$administrator = get_role( 'administrator' );

		if ( $administrator ) {
			foreach ( array_merge( self::MEMBER_CAPS, self::ADMIN_ONLY_CAPS ) as $cap ) {
				$administrator->remove_cap( $cap );
			}
		}

		$subscriber = get_role( 'subscriber' );

		if ( $subscriber ) {
			foreach ( self::MEMBER_CAPS as $cap ) {
				$subscriber->remove_cap( $cap );
			}
		}
	}
}
