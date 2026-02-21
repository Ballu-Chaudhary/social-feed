<?php
/**
 * Cache Manager for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Cache
 *
 * Handles feed data caching using WordPress transients.
 */
class SF_Cache {

	/**
	 * Cache key prefix.
	 *
	 * @var string
	 */
	const CACHE_PREFIX = 'sf_feed_';

	/**
	 * Default cache duration in seconds (1 hour).
	 *
	 * @var int
	 */
	const DEFAULT_DURATION = 3600;

	/**
	 * Get cached feed data.
	 *
	 * @param int $feed_id Feed ID.
	 * @return array|false Cached items array or false if not found/expired.
	 */
	public static function get( $feed_id ) {
		$cache_key = self::get_cache_key( $feed_id );
		$cached    = get_transient( $cache_key );

		if ( false === $cached ) {
			return false;
		}

		if ( ! is_array( $cached ) ) {
			return false;
		}

		return $cached;
	}

	/**
	 * Set feed cache.
	 *
	 * @param int      $feed_id  Feed ID.
	 * @param array    $items    Items to cache.
	 * @param int|null $duration Cache duration in seconds. If null, reads from feed settings.
	 * @return bool Whether the cache was set successfully.
	 */
	public static function set( $feed_id, $items, $duration = null ) {
		if ( null === $duration ) {
			$duration = self::get_feed_cache_duration( $feed_id );
		}

		$cache_key = self::get_cache_key( $feed_id );
		$result    = set_transient( $cache_key, $items, $duration );

		if ( $result ) {
			SF_Database::update_feed(
				$feed_id,
				array(
					'last_fetched' => current_time( 'mysql' ),
				)
			);
		}

		return $result;
	}

	/**
	 * Delete specific feed's cache.
	 *
	 * @param int $feed_id Feed ID.
	 * @return bool Whether the transient was deleted successfully.
	 */
	public static function delete( $feed_id ) {
		$cache_key = self::get_cache_key( $feed_id );
		return delete_transient( $cache_key );
	}

	/**
	 * Clear all feed caches.
	 *
	 * @return int Number of deleted cache entries.
	 */
	public static function clear_all() {
		global $wpdb;

		$deleted = $wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_sf_feed_%' 
			OR option_name LIKE '_transient_timeout_sf_feed_%'"
		);

		return $deleted;
	}

	/**
	 * Get cached data or fetch from API.
	 *
	 * @param int  $feed_id       Feed ID.
	 * @param bool $force_refresh Whether to bypass cache.
	 * @return array|WP_Error Feed items or error.
	 */
	public static function get_or_fetch( $feed_id, $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = self::get( $feed_id );

			if ( false !== $cached ) {
				return $cached;
			}
		}

		$items = SF_Feed_Manager::fetch_from_api( $feed_id );

		if ( is_wp_error( $items ) ) {
			return $items;
		}

		self::set( $feed_id, $items );

		return $items;
	}

	/**
	 * Check if feed cache is expired.
	 *
	 * @param int $feed_id Feed ID.
	 * @return bool Whether cache is expired.
	 */
	public static function is_expired( $feed_id ) {
		$feed = SF_Database::get_feed( $feed_id );

		if ( ! $feed || empty( $feed['last_fetched'] ) ) {
			return true;
		}

		$last_fetched = strtotime( $feed['last_fetched'] );
		$duration     = self::get_feed_cache_duration( $feed_id );
		$expiry       = $last_fetched + $duration;

		return time() > $expiry;
	}

	/**
	 * Get cache duration for a specific feed.
	 *
	 * @param int $feed_id Feed ID.
	 * @return int Cache duration in seconds.
	 */
	private static function get_feed_cache_duration( $feed_id ) {
		$meta = SF_Database::get_feed_meta( $feed_id, 'cache_duration' );

		if ( $meta && is_numeric( $meta ) ) {
			return absint( $meta );
		}

		$settings = get_option( 'sf_settings', array() );

		if ( ! empty( $settings['cache_duration'] ) ) {
			return absint( $settings['cache_duration'] );
		}

		return self::DEFAULT_DURATION;
	}

	/**
	 * Generate cache key for a feed.
	 *
	 * @param int $feed_id Feed ID.
	 * @return string Cache key.
	 */
	private static function get_cache_key( $feed_id ) {
		return self::CACHE_PREFIX . absint( $feed_id );
	}

	/**
	 * Warm cache for multiple feeds in background.
	 *
	 * @param array $feed_ids Array of feed IDs.
	 */
	public static function warm_caches( $feed_ids ) {
		foreach ( $feed_ids as $feed_id ) {
			if ( self::is_expired( $feed_id ) ) {
				SF_Feed_Manager::fetch_from_api( $feed_id );
			}
		}
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array Cache stats.
	 */
	public static function get_stats() {
		global $wpdb;

		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_sf_feed_%' 
			AND option_name NOT LIKE '_transient_timeout_sf_feed_%'"
		);

		$size = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_sf_feed_%' 
			AND option_name NOT LIKE '_transient_timeout_sf_feed_%'"
		);

		return array(
			'count' => absint( $count ),
			'size'  => absint( $size ),
		);
	}
}
