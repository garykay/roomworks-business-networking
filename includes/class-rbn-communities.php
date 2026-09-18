<?php
/**
 * Communities - the origin-country/destination-country pairing members
 * join (e.g. "South Africans in the United Kingdom"). See the scalability
 * spec's Section 5. Deliberately a custom table rather than a taxonomy or
 * CPT: a community needs two country foreign keys and an admin-configurable
 * uniqueness rule that don't map cleanly onto either.
 *
 * @package RoomworksBusinessNetworking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RBN_Communities {

	const CACHE_GROUP   = 'rbn_communities';
	const CACHE_KEY_ALL = 'all';

	const STATUS_ACTIVE   = 'active';
	const STATUS_INACTIVE = 'inactive';

	/**
	 * Creates a community. Validates the origin/destination country IDs are
	 * real countries, and - if RBN_Settings::require_unique_community_pair()
	 * is on - that no other community already covers the same
	 * origin/destination pair (spec Section 6: this is a product decision,
	 * not something to enforce unconditionally).
	 *
	 * @return int|WP_Error New community ID, or a WP_Error describing what
	 *                       failed validation.
	 */
	public static function create( array $args ) {
		$name                   = trim( sanitize_text_field( $args['name'] ?? '' ) );
		$origin_country_id      = absint( $args['origin_country_id'] ?? 0 );
		$destination_country_id = absint( $args['destination_country_id'] ?? 0 );
		$description            = isset( $args['description'] ) ? sanitize_textarea_field( $args['description'] ) : '';
		$status                 = in_array( $args['status'] ?? '', array( self::STATUS_ACTIVE, self::STATUS_INACTIVE ), true )
			? $args['status']
			: self::STATUS_ACTIVE;

		if ( '' === $name ) {
			return new WP_Error( 'rbn_community_name_required', __( 'Please enter a community name.', 'roomworks-business-networking' ) );
		}

		if ( ! RBN_Countries::get_by_id( $origin_country_id ) || ! RBN_Countries::get_by_id( $destination_country_id ) ) {
			return new WP_Error( 'rbn_community_invalid_country', __( 'Please select a valid origin and destination country.', 'roomworks-business-networking' ) );
		}

		if ( RBN_Settings::require_unique_community_pair() && self::exists_for_pair( $origin_country_id, $destination_country_id ) ) {
			return new WP_Error( 'rbn_community_pair_exists', __( 'A community already exists for that origin/destination pair. Turn off "Require unique origin/destination pair" in Settings to allow more than one.', 'roomworks-business-networking' ) );
		}

		global $wpdb;

		$table = RBN_Schema::communities_table();
		$now   = current_time( 'mysql' );
		$slug  = self::unique_slug( sanitize_title( $name ) );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array(
				'name'                   => $name,
				'slug'                   => $slug,
				'origin_country_id'      => $origin_country_id,
				'destination_country_id' => $destination_country_id,
				'description'            => $description,
				'status'                 => $status,
				'created_at'             => $now,
				'updated_at'             => $now,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'rbn_community_create_failed', __( 'Something went wrong creating that community.', 'roomworks-business-networking' ) );
		}

		self::flush_cache();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates a community's name, description and status - deliberately
	 * not origin/destination country: those are fixed once a community
	 * exists, since members and businesses are already tied to it by ID,
	 * and silently changing which country pair it represents would move
	 * all of them to a different country without anyone choosing that.
	 * Renaming is the main reason this exists - see the auto-created
	 * "{Origin} in {Destination}" fallback names in get_or_create_for_pair(),
	 * which an admin can now clean up here.
	 *
	 * The slug is deliberately left untouched by a rename - it was already
	 * used to route to this community (spec Section 19), and changing it
	 * to match a new name would break any existing link to it.
	 *
	 * @return true|WP_Error
	 */
	public static function update( $community_id, array $args ) {
		$community = self::get_by_id( $community_id );

		if ( ! $community ) {
			return new WP_Error( 'rbn_community_not_found', __( 'Community not found.', 'roomworks-business-networking' ) );
		}

		$name = array_key_exists( 'name', $args ) ? trim( sanitize_text_field( $args['name'] ) ) : $community->name;

		if ( '' === $name ) {
			return new WP_Error( 'rbn_community_name_required', __( 'Please enter a community name.', 'roomworks-business-networking' ) );
		}

		$description = array_key_exists( 'description', $args ) ? sanitize_textarea_field( $args['description'] ) : $community->description;
		$status      = in_array( $args['status'] ?? '', array( self::STATUS_ACTIVE, self::STATUS_INACTIVE ), true )
			? $args['status']
			: $community->status;

		global $wpdb;

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			RBN_Schema::communities_table(),
			array(
				'name'        => $name,
				'description' => $description,
				'status'      => $status,
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => absint( $community_id ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		self::flush_cache();

		return false !== $updated ? true : new WP_Error( 'rbn_community_update_failed', __( 'Something went wrong updating that community.', 'roomworks-business-networking' ) );
	}

	public static function set_status( $community_id, $status ) {
		if ( ! in_array( $status, array( self::STATUS_ACTIVE, self::STATUS_INACTIVE ), true ) ) {
			return false;
		}

		global $wpdb;

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			RBN_Schema::communities_table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $community_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		self::flush_cache();

		return false !== $updated;
	}

	/**
	 * Every community regardless of status, ordered by name. Cached the same
	 * way as RBN_Countries::get_all() - small, rarely-changing reference
	 * data read on most page loads.
	 */
	public static function get_all() {
		$cached = wp_cache_get( self::CACHE_KEY_ALL, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$table   = RBN_Schema::communities_table();
		$results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached just below.

		$communities = $results ? $results : array();

		wp_cache_set( self::CACHE_KEY_ALL, $communities, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $communities;
	}

	public static function get_by_id( $community_id ) {
		$community_id = absint( $community_id );

		if ( ! $community_id ) {
			return null;
		}

		foreach ( self::get_all() as $community ) {
			if ( (int) $community->id === $community_id ) {
				return $community;
			}
		}

		return null;
	}

	/**
	 * Active communities whose destination country matches the given
	 * country - "which communities can a member currently living in this
	 * country join", the core lookup behind the onboarding/available-
	 * communities UI (spec Section 25).
	 */
	public static function get_for_destination_country( $country_id ) {
		$country_id = absint( $country_id );

		return array_values(
			array_filter(
				self::get_all(),
				static function ( $community ) use ( $country_id ) {
					return self::STATUS_ACTIVE === $community->status && (int) $community->destination_country_id === $country_id;
				}
			)
		);
	}

	public static function exists_for_pair( $origin_country_id, $destination_country_id, $exclude_id = 0 ) {
		$origin_country_id      = absint( $origin_country_id );
		$destination_country_id = absint( $destination_country_id );
		$exclude_id             = absint( $exclude_id );

		foreach ( self::get_all() as $community ) {
			if ( $exclude_id && (int) $community->id === $exclude_id ) {
				continue;
			}

			if ( (int) $community->origin_country_id === $origin_country_id && (int) $community->destination_country_id === $destination_country_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The existing community for an origin/destination pair, any status,
	 * or null if none exists yet. Unlike exists_for_pair()/create()'s own
	 * uniqueness check (which only applies when
	 * RBN_Settings::require_unique_community_pair() is on, since an admin
	 * may deliberately want several communities for the same pair), this
	 * always looks for just one match - used by get_or_create_for_pair()
	 * below, which must never create a second automatic community for a
	 * pair that already has one, regardless of that setting.
	 */
	public static function get_for_pair( $origin_country_id, $destination_country_id ) {
		$origin_country_id      = absint( $origin_country_id );
		$destination_country_id = absint( $destination_country_id );

		foreach ( self::get_all() as $community ) {
			if ( (int) $community->origin_country_id === $origin_country_id && (int) $community->destination_country_id === $destination_country_id ) {
				return $community;
			}
		}

		return null;
	}

	/**
	 * Ensures a community exists for an origin/destination pair, creating
	 * one automatically if it doesn't - so a real member (registering, or
	 * updating their origin/current country) is never blocked just because
	 * no admin has set up their specific combination yet. Never creates a
	 * duplicate: get_for_pair() above always checks for an existing match
	 * first, independent of the admin-configurable uniqueness setting on
	 * create() (see that method's docblock) - auto-creation must never
	 * multiply itself no matter how that setting is configured.
	 *
	 * The generated name ("{Origin} in {Destination}", e.g. "South Africa
	 * in Denmark") is a deliberately safe fallback, not a demonym - "South
	 * Africans"/"Indians"/"Poles" all pluralise differently and there's no
	 * reliable way to generate that automatically. An admin can rename it
	 * later once the wp-admin screen supports editing (it doesn't yet -
	 * only create and activate/deactivate).
	 *
	 * @return object|null The existing or newly created community, or null
	 *                      if either country ID is invalid.
	 */
	public static function get_or_create_for_pair( $origin_country_id, $destination_country_id ) {
		$origin_country_id      = absint( $origin_country_id );
		$destination_country_id = absint( $destination_country_id );

		if ( ! $origin_country_id || ! $destination_country_id ) {
			return null;
		}

		$existing = self::get_for_pair( $origin_country_id, $destination_country_id );

		if ( $existing ) {
			return $existing;
		}

		$origin      = RBN_Countries::get_by_id( $origin_country_id );
		$destination = RBN_Countries::get_by_id( $destination_country_id );

		if ( ! $origin || ! $destination ) {
			return null;
		}

		$result = self::create(
			array(
				'name'                   => sprintf(
					/* translators: 1: origin country name, 2: destination country name. */
					__( '%1$s in %2$s', 'roomworks-business-networking' ),
					$origin->name,
					$destination->name
				),
				'origin_country_id'      => $origin_country_id,
				'destination_country_id' => $destination_country_id,
				'status'                 => self::STATUS_ACTIVE,
			)
		);

		return is_wp_error( $result ) ? null : self::get_by_id( $result );
	}

	/**
	 * Appends -2, -3, etc. until the slug is free, the same approach core
	 * uses for post slugs - communities are looked up/routed by slug later
	 * (spec Section 19), so it must be unique.
	 */
	private static function unique_slug( $base_slug, $exclude_id = 0 ) {
		$slug   = $base_slug;
		$suffix = 2;

		while ( self::slug_exists( $slug, $exclude_id ) ) {
			$slug = $base_slug . '-' . $suffix;
			++$suffix;
		}

		return $slug;
	}

	private static function slug_exists( $slug, $exclude_id = 0 ) {
		foreach ( self::get_all() as $community ) {
			if ( $exclude_id && (int) $community->id === absint( $exclude_id ) ) {
				continue;
			}

			if ( $community->slug === $slug ) {
				return true;
			}
		}

		return false;
	}

	public static function flush_cache() {
		wp_cache_delete( self::CACHE_KEY_ALL, self::CACHE_GROUP );
	}

	/**
	 * Seeds "South Africa -> United Kingdom" as the first community, per the
	 * spec's Section 33 migration guidance (this plugin's original,
	 * implicit community). A no-op if any community already exists, so this
	 * never overwrites an admin's own setup.
	 */
	public static function seed_default() {
		if ( ! empty( self::get_all() ) ) {
			return;
		}

		$countries_by_iso = array();
		foreach ( RBN_Countries::get_all() as $country ) {
			$countries_by_iso[ $country->iso_code ] = (int) $country->id;
		}

		if ( empty( $countries_by_iso['ZA'] ) || empty( $countries_by_iso['GB'] ) ) {
			return;
		}

		self::create(
			array(
				'name'                   => __( 'South Africans in the United Kingdom', 'roomworks-business-networking' ),
				'origin_country_id'      => $countries_by_iso['ZA'],
				'destination_country_id' => $countries_by_iso['GB'],
				'description'            => __( 'The original South Africans in the UK business network.', 'roomworks-business-networking' ),
				'status'                 => self::STATUS_ACTIVE,
			)
		);
	}

	/**
	 * Self-healing check run on every request (see RBN_Schema::maybe_upgrade())
	 * - mirrors RBN_Countries::maybe_seed() and exists for the same reason:
	 * a one-shot seed tied to a schema-version bump has no automatic retry
	 * if it doesn't complete. seed_default() is itself a no-op once any
	 * community exists, so this is safe to call on every request.
	 */
	public static function maybe_seed() {
		global $wpdb;

		$table = RBN_Schema::communities_table();
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- single indexed count, not user-facing.

		if ( 0 === $count ) {
			self::seed_default();
		}
	}
}
