<?php
/**
 * Central lookup for the redirect-driven notices shown after a form
 * submission (login, registration, profile update, business save). Keeping
 * this in one place avoids each form handler duplicating message/status/
 * section logic.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Notices {

	private static function messages() {
		return array(
			'login_missing_fields'       => array( 'error', __( 'Please enter your username/email and password.', 'roomworks-business-networking' ) ),
			'login_failed'               => array( 'error', __( "We couldn't log you in with those details. Please try again.", 'roomworks-business-networking' ) ),
			'login_invalid_request'      => array( 'error', __( 'Your session expired. Please try again.', 'roomworks-business-networking' ) ),
			'login_pending'              => array( 'error', __( 'Your account is awaiting admin approval. We will email you once it has been approved.', 'roomworks-business-networking' ) ),
			'login_rejected'             => array( 'error', __( 'Your registration was not approved. Please contact us for more information.', 'roomworks-business-networking' ) ),
			'register_pending_approval'  => array( 'success', __( "Thanks for registering! Your account is awaiting admin approval - we'll email you once it's approved.", 'roomworks-business-networking' ) ),
			'register_missing_fields'    => array( 'error', __( 'Please fill in all required fields.', 'roomworks-business-networking' ) ),
			'register_invalid_email'     => array( 'error', __( 'Please enter a valid email address.', 'roomworks-business-networking' ) ),
			'register_weak_password'     => array( 'error', __( 'Please choose a password with at least 8 characters.', 'roomworks-business-networking' ) ),
			'register_password_mismatch' => array( 'error', __( 'Passwords do not match.', 'roomworks-business-networking' ) ),
			'register_terms_required'    => array( 'error', __( 'Please agree to the Terms and Privacy Policy to continue.', 'roomworks-business-networking' ) ),
			'register_invalid_country'   => array( 'error', __( 'Please select a valid country of origin and current country.', 'roomworks-business-networking' ) ),
			'register_email_exists'      => array( 'error', __( 'An account already exists with that email address.', 'roomworks-business-networking' ) ),
			'register_failed'            => array( 'error', __( "We couldn't create your account. Please try again.", 'roomworks-business-networking' ) ),
			'register_invalid_request'   => array( 'error', __( 'Your session expired. Please try again.', 'roomworks-business-networking' ) ),
			'register_already_logged_in' => array( 'error', __( 'You are already logged in.', 'roomworks-business-networking' ) ),
			'profile_invalid_request'    => array( 'error', __( 'Your session expired. Please try again.', 'roomworks-business-networking' ) ),
			'profile_missing_fields'     => array( 'error', __( 'Please enter your first and last name.', 'roomworks-business-networking' ) ),
			'profile_invalid_country'    => array( 'error', __( 'Please select a valid country of origin and current country.', 'roomworks-business-networking' ) ),
			'profile_updated'            => array( 'success', __( 'Your profile has been updated.', 'roomworks-business-networking' ) ),
			'business_invalid_request'   => array( 'error', __( 'Your session expired. Please try again.', 'roomworks-business-networking' ) ),
			'business_invalid_community' => array( 'error', __( 'Please select a community you belong to.', 'roomworks-business-networking' ) ),
			'business_missing_fields'    => array( 'error', __( 'Please fill in all required fields (Website is the only optional field).', 'roomworks-business-networking' ) ),
			'business_not_permitted'     => array( 'error', __( "You don't have permission to do that.", 'roomworks-business-networking' ) ),
			'business_logo_invalid'      => array( 'error', __( 'Please upload a valid logo image (JPG, PNG, GIF or WEBP) under 5MB, or leave that field empty.', 'roomworks-business-networking' ) ),
			'business_saved'             => array( 'success', __( 'Your business has been submitted for review.', 'roomworks-business-networking' ) ),
			'business_updated'           => array( 'success', __( 'Your business has been updated.', 'roomworks-business-networking' ) ),
			'account_deletion_invalid_request' => array( 'error', __( 'Your session expired. Please try again.', 'roomworks-business-networking' ) ),
			'account_deletion_not_permitted'   => array( 'error', __( 'Administrator accounts cannot be deleted this way.', 'roomworks-business-networking' ) ),
			'account_deletion_requested'       => array( 'success', __( 'Your account deletion request has been received. Unless you cancel it, your account will be permanently deleted in 24 hours.', 'roomworks-business-networking' ) ),
			'account_deletion_cancelled'       => array( 'success', __( 'Your account deletion request has been cancelled.', 'roomworks-business-networking' ) ),
			'community_invalid_request'        => array( 'error', __( 'Your session expired. Please try again.', 'roomworks-business-networking' ) ),
			'community_not_found'              => array( 'error', __( "That community isn't available.", 'roomworks-business-networking' ) ),
			'community_wrong_country'          => array( 'error', __( 'You can only join communities in the country you currently live in.', 'roomworks-business-networking' ) ),
			'community_joined'                 => array( 'success', __( "You've joined this community.", 'roomworks-business-networking' ) ),
			'community_left'                   => array( 'success', __( 'You have left this community.', 'roomworks-business-networking' ) ),
		);
	}

	public static function text( $code ) {
		$messages = self::messages();
		return isset( $messages[ $code ] ) ? $messages[ $code ][1] : '';
	}

	public static function status( $code ) {
		$messages = self::messages();
		return isset( $messages[ $code ] ) ? $messages[ $code ][0] : 'error';
	}

	/**
	 * Which form section a notice code belongs to, derived from its prefix
	 * (login_, register_, profile_, business_, account_deletion_).
	 */
	public static function section( $code ) {
		foreach ( array( 'login', 'register', 'profile', 'business', 'account_deletion', 'community' ) as $section ) {
			if ( 0 === strpos( (string) $code, $section . '_' ) ) {
				return $section;
			}
		}

		return '';
	}
}
