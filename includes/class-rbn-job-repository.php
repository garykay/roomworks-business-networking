<?php
/**
 * Data access for the member/job-listing relationship. Mirrors
 * RBN_Business_Repository - a member may own any number of job listings,
 * and a submitted job ID is never trusted on its own (get_by_id_for_user()
 * always re-verifies ownership server-side).
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Job_Repository {

	/**
	 * The set of statuses that count as "this user owns this job listing" -
	 * everything except trash.
	 */
	const OWNERSHIP_STATUSES = array( 'publish', 'pending', 'draft', 'future', 'private' );

	/**
	 * Every job listing owned by a user, any ownership status, ordered by
	 * date - used for the "My Job Listings" list on the dashboard.
	 */
	public static function get_all_for_user( $user_id ) {
		return get_posts(
			array(
				'post_type'      => RBN_Post_Type_Job::POST_TYPE,
				'post_status'    => self::OWNERSHIP_STATUSES,
				'author'         => $user_id,
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * A specific job listing, but only if it's actually owned by $user_id -
	 * see RBN_Business_Repository::get_by_id_for_user()'s docblock for why
	 * this re-verification is mandatory rather than trusting a submitted ID.
	 */
	public static function get_by_id_for_user( $job_id, $user_id ) {
		$job_id = absint( $job_id );

		if ( ! $job_id ) {
			return null;
		}

		$job = get_post( $job_id );

		if ( ! $job || RBN_Post_Type_Job::POST_TYPE !== $job->post_type ) {
			return null;
		}

		if ( absint( $user_id ) !== (int) $job->post_author ) {
			return null;
		}

		if ( ! in_array( $job->post_status, self::OWNERSHIP_STATUSES, true ) ) {
			return null;
		}

		return $job;
	}
}
