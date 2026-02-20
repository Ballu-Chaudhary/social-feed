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

	/**
	 * Get available platforms.
	 *
	 * @return array
	 */
	public static function get_platforms() {
		return array(
			'instagram' => __( 'Instagram', 'social-feed' ),
			'youtube'   => __( 'YouTube', 'social-feed' ),
			'facebook'  => __( 'Facebook', 'social-feed' ),
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
			'platform' => 'instagram',
			'layout'   => 'grid',
			'limit'    => 9,
			'columns'  => 3,
		);

		$atts = shortcode_atts( $defaults, $atts, 'social_feed' );

		$atts['platform'] = sanitize_key( $atts['platform'] );
		$atts['layout']   = sanitize_key( $atts['layout'] );
		$atts['limit']    = absint( $atts['limit'] );
		$atts['columns']  = absint( $atts['columns'] );

		if ( ! in_array( $atts['platform'], array( 'instagram', 'youtube', 'facebook' ), true ) ) {
			$atts['platform'] = 'instagram';
		}

		$atts['limit']   = min( max( 1, $atts['limit'] ), 50 );
		$atts['columns'] = min( max( 1, $atts['columns'] ), 6 );

		return $atts;
	}

	/**
	 * Format timestamp for display.
	 *
	 * @param string $datetime MySQL datetime string.
	 * @return string
	 */
	public static function format_date( $datetime ) {
		if ( empty( $datetime ) ) {
			return '';
		}

		$timestamp = strtotime( $datetime );
		if ( ! $timestamp ) {
			return '';
		}

		return human_time_diff( $timestamp, time() ) . ' ' . __( 'ago', 'social-feed' );
	}

	/**
	 * Truncate text.
	 *
	 * @param string $text   Text to truncate.
	 * @param int    $length Max length.
	 * @param string $suffix Suffix for truncated text.
	 * @return string
	 */
	public static function truncate( $text, $length = 150, $suffix = '...' ) {
		$text = wp_strip_all_tags( $text );
		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}
		return mb_substr( $text, 0, $length ) . $suffix;
	}

	/**
	 * Generate a cache key from parameters.
	 *
	 * @param string $platform Platform name.
	 * @param string $feed_id  Feed ID.
	 * @param int    $limit    Limit.
	 * @return string
	 */
	public static function get_cache_key( $platform, $feed_id = '', $limit = 10 ) {
		return 'sf_feed_' . $platform . '_' . md5( $feed_id . '_' . $limit );
	}
}
