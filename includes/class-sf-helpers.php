<?php
/**
 * Utility functions for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Helpers
 */
class SF_Helpers {

	// =========================================================================
	// TOKEN ENCRYPTION/DECRYPTION
	// =========================================================================

	/**
	 * Encrypt data using OpenSSL AES-256-CBC.
	 *
	 * Uses WordPress SECURE_AUTH_KEY and SECURE_AUTH_SALT for encryption.
	 *
	 * @param string $data The data to encrypt.
	 * @return string|false Base64 encoded encrypted string or false on failure.
	 */
	public static function sf_encrypt( $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $data );
		}

		$key    = self::get_encryption_key();
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = 'AES-256-CBC';

		$encrypted = openssl_encrypt( $data, $cipher, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return false;
		}

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt data encrypted with sf_encrypt.
	 *
	 * @param string $data The base64 encoded encrypted data.
	 * @return string|false Decrypted string or false on failure.
	 */
	public static function sf_decrypt( $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			$decoded = base64_decode( $data, true );
			return false !== $decoded ? $decoded : false;
		}

		$decoded = base64_decode( $data, true );
		if ( false === $decoded || strlen( $decoded ) < 17 ) {
			return false;
		}

		$key    = self::get_encryption_key();
		$iv     = substr( $decoded, 0, 16 );
		$cipher = 'AES-256-CBC';
		$data   = substr( $decoded, 16 );

		$decrypted = openssl_decrypt( $data, $cipher, $key, OPENSSL_RAW_DATA, $iv );

		return false !== $decrypted ? $decrypted : false;
	}

	/**
	 * Get encryption key from WordPress constants.
	 *
	 * @return string 32-byte encryption key.
	 */
	private static function get_encryption_key() {
		$key = '';

		if ( defined( 'SECURE_AUTH_KEY' ) ) {
			$key .= SECURE_AUTH_KEY;
		}

		if ( defined( 'SECURE_AUTH_SALT' ) ) {
			$key .= SECURE_AUTH_SALT;
		}

		if ( empty( $key ) ) {
			$key = 'sf_default_key_please_set_wordpress_salts';
		}

		return hash( 'sha256', $key, true );
	}

	// =========================================================================
	// SECURITY HELPERS
	// =========================================================================

	/**
	 * Verify nonce with optional JSON error response.
	 *
	 * @param string $nonce        The nonce to verify.
	 * @param string $action       The nonce action.
	 * @param bool   $send_error   Whether to send JSON error on failure.
	 * @return bool True if valid, false otherwise.
	 */
	public static function sf_verify_nonce( $nonce, $action, $send_error = false ) {
		$valid = wp_verify_nonce( $nonce, $action );

		if ( ! $valid && $send_error ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security check failed. Please refresh the page and try again.', 'social-feed' ),
					'code'    => 'invalid_nonce',
				),
				403
			);
		}

		return (bool) $valid;
	}

	/**
	 * Check if current user has the required capability.
	 *
	 * @param string $capability The capability to check.
	 * @param bool   $send_error Whether to send JSON error on failure.
	 * @return bool True if user has capability.
	 */
	public static function sf_current_user_can( $capability = 'manage_options', $send_error = false ) {
		$can = current_user_can( $capability );

		if ( ! $can && $send_error ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'social-feed' ),
					'code'    => 'insufficient_permissions',
				),
				403
			);
		}

		return $can;
	}

	/**
	 * Recursively sanitize array values.
	 *
	 * @param array $array The array to sanitize.
	 * @return array Sanitized array.
	 */
	public static function sf_sanitize_array( $array ) {
		if ( ! is_array( $array ) ) {
			return sanitize_text_field( $array );
		}

		$sanitized = array();

		foreach ( $array as $key => $value ) {
			$clean_key = sanitize_key( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $clean_key ] = self::sf_sanitize_array( $value );
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $clean_key ] = $value;
			} elseif ( is_int( $value ) ) {
				$sanitized[ $clean_key ] = intval( $value );
			} elseif ( is_float( $value ) ) {
				$sanitized[ $clean_key ] = floatval( $value );
			} else {
				$sanitized[ $clean_key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	// =========================================================================
	// FORMATTING HELPERS
	// =========================================================================

	/**
	 * Format a number with K/M/B suffix.
	 *
	 * @param int|float $number    The number to format.
	 * @param int       $precision Decimal precision.
	 * @return string Formatted number string.
	 */
	public static function sf_format_number( $number, $precision = 1 ) {
		$number = floatval( $number );

		if ( $number < 0 ) {
			return '-' . self::sf_format_number( abs( $number ), $precision );
		}

		if ( $number < 1000 ) {
			return number_format( $number, 0 );
		}

		$suffixes = array(
			array( 1000000000000, 'T' ),
			array( 1000000000, 'B' ),
			array( 1000000, 'M' ),
			array( 1000, 'K' ),
		);

		foreach ( $suffixes as $suffix ) {
			if ( $number >= $suffix[0] ) {
				$formatted = $number / $suffix[0];
				$formatted = round( $formatted, $precision );

				if ( floor( $formatted ) == $formatted ) {
					return number_format( $formatted, 0 ) . $suffix[1];
				}

				return number_format( $formatted, $precision ) . $suffix[1];
			}
		}

		return number_format( $number, 0 );
	}

	/**
	 * Get human-readable time difference.
	 *
	 * @param int|string $timestamp Unix timestamp or datetime string.
	 * @return string Human-readable time difference.
	 */
	public static function sf_time_ago( $timestamp ) {
		if ( empty( $timestamp ) ) {
			return '';
		}

		if ( ! is_numeric( $timestamp ) ) {
			$timestamp = strtotime( $timestamp );
		}

		if ( ! $timestamp || $timestamp <= 0 ) {
			return '';
		}

		$now  = current_time( 'timestamp' );
		$diff = $now - $timestamp;

		if ( $diff < 0 ) {
			return __( 'just now', 'social-feed' );
		}

		$intervals = array(
			array( 'year', 31536000 ),
			array( 'month', 2592000 ),
			array( 'week', 604800 ),
			array( 'day', 86400 ),
			array( 'hour', 3600 ),
			array( 'minute', 60 ),
			array( 'second', 1 ),
		);

		foreach ( $intervals as $interval ) {
			$unit    = $interval[0];
			$seconds = $interval[1];

			if ( $diff >= $seconds ) {
				$count = floor( $diff / $seconds );

				switch ( $unit ) {
					case 'year':
						$text = sprintf(
							_n( '%d year ago', '%d years ago', $count, 'social-feed' ),
							$count
						);
						break;
					case 'month':
						$text = sprintf(
							_n( '%d month ago', '%d months ago', $count, 'social-feed' ),
							$count
						);
						break;
					case 'week':
						$text = sprintf(
							_n( '%d week ago', '%d weeks ago', $count, 'social-feed' ),
							$count
						);
						break;
					case 'day':
						$text = sprintf(
							_n( '%d day ago', '%d days ago', $count, 'social-feed' ),
							$count
						);
						break;
					case 'hour':
						$text = sprintf(
							_n( '%d hour ago', '%d hours ago', $count, 'social-feed' ),
							$count
						);
						break;
					case 'minute':
						$text = sprintf(
							_n( '%d minute ago', '%d minutes ago', $count, 'social-feed' ),
							$count
						);
						break;
					default:
						$text = sprintf(
							_n( '%d second ago', '%d seconds ago', $count, 'social-feed' ),
							$count
						);
				}

				return $text;
			}
		}

		return __( 'just now', 'social-feed' );
	}

	/**
	 * Truncate text to a specified length.
	 *
	 * @param string $text   The text to truncate.
	 * @param int    $length Maximum length.
	 * @param string $suffix Suffix to append if truncated.
	 * @return string Truncated text.
	 */
	public static function sf_truncate_text( $text, $length = 150, $suffix = '...' ) {
		$text = wp_strip_all_tags( $text );
		$text = trim( $text );

		if ( empty( $text ) ) {
			return '';
		}

		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		$truncated = mb_substr( $text, 0, $length );
		$last_space = mb_strrpos( $truncated, ' ' );

		if ( false !== $last_space && $last_space > ( $length * 0.8 ) ) {
			$truncated = mb_substr( $truncated, 0, $last_space );
		}

		return rtrim( $truncated ) . $suffix;
	}

	// =========================================================================
	// CACHE HELPERS
	// =========================================================================

	/**
	 * Get cached data.
	 *
	 * @param string $key Cache key (without sf_ prefix).
	 * @return mixed|false Cached data or false if not found.
	 */
	public static function sf_get_cache( $key ) {
		$key   = 'sf_' . sanitize_key( $key );
		$value = get_transient( $key );

		return $value;
	}

	/**
	 * Set cached data.
	 *
	 * @param string $key    Cache key (without sf_ prefix).
	 * @param mixed  $data   Data to cache.
	 * @param int    $expiry Expiration time in seconds.
	 * @return bool True on success, false on failure.
	 */
	public static function sf_set_cache( $key, $data, $expiry = 3600 ) {
		$key    = 'sf_' . sanitize_key( $key );
		$expiry = absint( $expiry );

		return set_transient( $key, $data, $expiry );
	}

	/**
	 * Delete specific cached data.
	 *
	 * @param string $key Cache key (without sf_ prefix).
	 * @return bool True on success, false on failure.
	 */
	public static function sf_delete_cache( $key ) {
		$key = 'sf_' . sanitize_key( $key );

		return delete_transient( $key );
	}

	/**
	 * Clear all Social Feed transients from database.
	 *
	 * @return int Number of deleted transients.
	 */
	public static function sf_clear_all_cache() {
		global $wpdb;

		$count = 0;

		$transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_sf_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_sf_' ) . '%'
			)
		);

		if ( ! empty( $transients ) ) {
			foreach ( $transients as $transient ) {
				if ( strpos( $transient, '_transient_timeout_' ) === 0 ) {
					continue;
				}

				$key = str_replace( '_transient_', '', $transient );
				if ( delete_transient( $key ) ) {
					$count++;
				}
			}
		}

		return $count;
	}

	// =========================================================================
	// LOGGING
	// =========================================================================

	/**
	 * Log a message to the database.
	 *
	 * @param string   $type     Log type: error, warning, info, success.
	 * @param string   $message  Log message.
	 * @param string   $platform Platform name (optional).
	 * @param int|null $feed_id  Feed ID (optional).
	 * @param array    $context  Additional context data (optional).
	 * @return int|false Insert ID or false on failure.
	 */
	public static function sf_log( $type, $message, $platform = null, $feed_id = null, $context = array() ) {
		if ( ! self::is_logging_enabled() ) {
			return false;
		}

		$allowed_types = array( 'error', 'warning', 'info', 'success' );
		$type          = in_array( $type, $allowed_types, true ) ? $type : 'info';

		if ( ! class_exists( 'SF_Database' ) ) {
			return false;
		}

		return SF_Database::create_log(
			array(
				'feed_id'    => $feed_id ? absint( $feed_id ) : null,
				'account_id' => null,
				'platform'   => $platform ? sanitize_key( $platform ) : null,
				'log_type'   => $type,
				'message'    => sanitize_text_field( $message ),
				'context'    => $context,
			)
		);
	}

	/**
	 * Check if logging is enabled in settings.
	 *
	 * @return bool True if logging is enabled.
	 */
	private static function is_logging_enabled() {
		$settings = get_option( 'sf_settings', array() );

		if ( ! isset( $settings['enable_logging'] ) ) {
			return true;
		}

		return ! empty( $settings['enable_logging'] );
	}

	/**
	 * Log an error message.
	 *
	 * @param string   $message  Error message.
	 * @param string   $platform Platform name (optional).
	 * @param int|null $feed_id  Feed ID (optional).
	 * @param array    $context  Additional context (optional).
	 * @return int|false
	 */
	public static function sf_log_error( $message, $platform = null, $feed_id = null, $context = array() ) {
		return self::sf_log( 'error', $message, $platform, $feed_id, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string   $message  Warning message.
	 * @param string   $platform Platform name (optional).
	 * @param int|null $feed_id  Feed ID (optional).
	 * @param array    $context  Additional context (optional).
	 * @return int|false
	 */
	public static function sf_log_warning( $message, $platform = null, $feed_id = null, $context = array() ) {
		return self::sf_log( 'warning', $message, $platform, $feed_id, $context );
	}

	/**
	 * Log an info message.
	 *
	 * @param string   $message  Info message.
	 * @param string   $platform Platform name (optional).
	 * @param int|null $feed_id  Feed ID (optional).
	 * @param array    $context  Additional context (optional).
	 * @return int|false
	 */
	public static function sf_log_info( $message, $platform = null, $feed_id = null, $context = array() ) {
		return self::sf_log( 'info', $message, $platform, $feed_id, $context );
	}

	/**
	 * Log a success message.
	 *
	 * @param string   $message  Success message.
	 * @param string   $platform Platform name (optional).
	 * @param int|null $feed_id  Feed ID (optional).
	 * @param array    $context  Additional context (optional).
	 * @return int|false
	 */
	public static function sf_log_success( $message, $platform = null, $feed_id = null, $context = array() ) {
		return self::sf_log( 'success', $message, $platform, $feed_id, $context );
	}

	// =========================================================================
	// ADDITIONAL UTILITY METHODS
	// =========================================================================

	/**
	 * Get available platforms.
	 *
	 * @return array Associative array of platform slugs to labels.
	 */
	public static function get_platforms() {
		return array(
			'instagram' => __( 'Instagram', 'social-feed' ),
			'youtube'   => __( 'YouTube', 'social-feed' ),
			'facebook'  => __( 'Facebook', 'social-feed' ),
			'tiktok'    => __( 'TikTok', 'social-feed' ),
			'twitter'   => __( 'Twitter/X', 'social-feed' ),
		);
	}

	/**
	 * Get feed layout options.
	 *
	 * @return array Associative array of layout slugs to labels.
	 */
	public static function get_layouts() {
		return array(
			'grid'     => __( 'Grid', 'social-feed' ),
			'masonry'  => __( 'Masonry', 'social-feed' ),
			'carousel' => __( 'Carousel', 'social-feed' ),
			'list'     => __( 'List', 'social-feed' ),
			'slider'   => __( 'Slider', 'social-feed' ),
		);
	}

	/**
	 * Sanitize shortcode attributes.
	 *
	 * @param array $atts Raw attributes.
	 * @return array Sanitized attributes.
	 */
	public static function sanitize_shortcode_atts( $atts ) {
		$defaults = array(
			'id'       => 0,
			'platform' => 'instagram',
			'layout'   => 'grid',
			'limit'    => 9,
			'columns'  => 3,
			'class'    => '',
		);

		$atts = shortcode_atts( $defaults, $atts, 'social_feed' );

		$atts['id']       = absint( $atts['id'] );
		$atts['platform'] = sanitize_key( $atts['platform'] );
		$atts['layout']   = sanitize_key( $atts['layout'] );
		$atts['limit']    = absint( $atts['limit'] );
		$atts['columns']  = absint( $atts['columns'] );
		$atts['class']    = sanitize_html_class( $atts['class'] );

		$platforms = array_keys( self::get_platforms() );
		if ( ! in_array( $atts['platform'], $platforms, true ) ) {
			$atts['platform'] = 'instagram';
		}

		$layouts = array_keys( self::get_layouts() );
		if ( ! in_array( $atts['layout'], $layouts, true ) ) {
			$atts['layout'] = 'grid';
		}

		$atts['limit']   = min( max( 1, $atts['limit'] ), 100 );
		$atts['columns'] = min( max( 1, $atts['columns'] ), 8 );

		return $atts;
	}

	/**
	 * Get plugin settings with defaults.
	 *
	 * @param string|null $key Specific setting key to retrieve.
	 * @return mixed All settings array or specific setting value.
	 */
	public static function get_settings( $key = null ) {
		$defaults = array(
			'enable_logging'     => true,
			'cache_duration'     => 3600,
			'cleanup_logs_days'  => 30,
			'default_layout'     => 'grid',
			'default_columns'    => 3,
			'default_post_count' => 9,
			'lazy_load'          => true,
			'popup_enabled'      => true,
		);

		$settings = get_option( 'sf_settings', array() );
		$settings = wp_parse_args( $settings, $defaults );

		if ( null !== $key ) {
			return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
		}

		return $settings;
	}

	/**
	 * Check if the current request is an AJAX request.
	 *
	 * @return bool True if AJAX request.
	 */
	public static function is_ajax() {
		return wp_doing_ajax();
	}

	/**
	 * Check if the current request is a REST API request.
	 *
	 * @return bool True if REST request.
	 */
	public static function is_rest() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Generate a unique cache key.
	 *
	 * @param string $prefix Key prefix.
	 * @param array  $params Parameters to include in key.
	 * @return string Cache key.
	 */
	public static function generate_cache_key( $prefix, $params = array() ) {
		$key = $prefix;

		if ( ! empty( $params ) ) {
			$key .= '_' . md5( wp_json_encode( $params ) );
		}

		return sanitize_key( $key );
	}

	/**
	 * Validate URL.
	 *
	 * @param string $url URL to validate.
	 * @return bool True if valid URL.
	 */
	public static function is_valid_url( $url ) {
		return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * Get file extension from URL.
	 *
	 * @param string $url URL to parse.
	 * @return string File extension or empty string.
	 */
	public static function get_file_extension( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );

		if ( ! $path ) {
			return '';
		}

		$extension = pathinfo( $path, PATHINFO_EXTENSION );

		return strtolower( $extension );
	}

	/**
	 * Determine media type from URL.
	 *
	 * @param string $url Media URL.
	 * @return string Media type: image, video, or unknown.
	 */
	public static function get_media_type( $url ) {
		$extension = self::get_file_extension( $url );

		$image_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp' );
		$video_extensions = array( 'mp4', 'webm', 'mov', 'avi', 'wmv', 'm4v', 'ogv' );

		if ( in_array( $extension, $image_extensions, true ) ) {
			return 'image';
		}

		if ( in_array( $extension, $video_extensions, true ) ) {
			return 'video';
		}

		return 'unknown';
	}
}
