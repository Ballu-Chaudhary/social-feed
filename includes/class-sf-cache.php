<?php
/**
 * Caching system for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Cache
 */
class SF_Cache {

	/**
	 * Cache group name.
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'social_feed';

	/**
	 * Default TTL in seconds (1 hour).
	 *
	 * @var int
	 */
	const DEFAULT_TTL = 3600;

	/**
	 * Get cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed|false Cached value or false if not found/expired.
	 */
	public static function get( $key ) {
		$key = self::sanitize_key( $key );
		if ( empty( $key ) ) {
			return false;
		}

		$value = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $value ) {
			return $value;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'social_feed_cache';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT cache_value, expires_at FROM {$table} WHERE cache_key = %s",
				$key
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return false;
		}

		if ( $row['expires_at'] && strtotime( $row['expires_at'] ) < time() ) {
			self::delete( $key );
			return false;
		}

		$value = maybe_unserialize( $row['cache_value'] );
		wp_cache_set( $key, $value, self::CACHE_GROUP, self::DEFAULT_TTL );

		return $value;
	}

	/**
	 * Set cache value.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $ttl   Time to live in seconds.
	 * @return bool
	 */
	public static function set( $key, $value, $ttl = self::DEFAULT_TTL ) {
		$key = self::sanitize_key( $key );
		if ( empty( $key ) ) {
			return false;
		}

		$expires_at = $ttl > 0 ? gmdate( 'Y-m-d H:i:s', time() + $ttl ) : null;

		wp_cache_set( $key, $value, self::CACHE_GROUP, $ttl );

		global $wpdb;
		$table = $wpdb->prefix . 'social_feed_cache';

		$data = array(
			'cache_key'   => $key,
			'cache_value' => maybe_serialize( $value ),
			'expires_at'  => $expires_at,
		);

		return false !== $wpdb->replace( $table, $data );
	}

	/**
	 * Delete cached value.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public static function delete( $key ) {
		$key = self::sanitize_key( $key );
		if ( empty( $key ) ) {
			return false;
		}

		wp_cache_delete( $key, self::CACHE_GROUP );

		global $wpdb;
		$table = $wpdb->prefix . 'social_feed_cache';

		return false !== $wpdb->delete( $table, array( 'cache_key' => $key ) );
	}

	/**
	 * Delete all cache entries matching a prefix.
	 *
	 * @param string $prefix Key prefix.
	 * @return int Number of deleted entries.
	 */
	public static function delete_by_prefix( $prefix ) {
		global $wpdb;
		$table = $wpdb->prefix . 'social_feed_cache';

		$prefix = sanitize_key( $prefix );
		if ( empty( $prefix ) ) {
			return 0;
		}

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE cache_key LIKE %s",
				$prefix . '%'
			)
		);

		return is_numeric( $deleted ) ? $deleted : 0;
	}

	/**
	 * Sanitize cache key.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	private static function sanitize_key( $key ) {
		return sanitize_key( $key );
	}
}
