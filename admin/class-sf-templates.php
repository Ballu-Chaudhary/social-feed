<?php
/**
 * Feed template presets for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Templates
 *
 * Template presets used when creating a new feed.
 */
class SF_Templates {

	/**
	 * Get all available templates.
	 *
	 * @return array
	 */
	public static function get_templates() {
		return array(
			'classic-grid'  => array(
				'id'       => 'classic-grid',
				'name'     => __( 'Classic Grid', 'social-feed' ),
				'thumbnail' => 'classic-grid',
				'pro'      => false,
				'settings' => array(
					'layout'          => 'grid',
					'columns_desktop' => 3,
					'columns_tablet'  => 2,
					'columns_mobile'  => 1,
					'bg_color'        => '#ffffff',
					'text_color'      => '#333333',
					'show_header'     => false,
					'show_profile_pic' => false,
					'show_username'   => false,
					'show_followers'  => false,
					'show_follow_btn' => false,
					'image_padding'   => 10,
					'post_radius'     => '8',
				),
			),
			'with-header'   => array(
				'id'       => 'with-header',
				'name'     => __( 'With Header', 'social-feed' ),
				'thumbnail' => 'with-header',
				'pro'      => false,
				'settings' => array(
					'layout'          => 'grid',
					'columns_desktop' => 3,
					'columns_tablet'  => 2,
					'columns_mobile'  => 1,
					'bg_color'        => '#ffffff',
					'show_header'     => true,
					'show_profile_pic' => true,
					'show_username'   => true,
					'show_followers'  => true,
					'show_follow_btn' => true,
					'image_padding'   => 10,
				),
			),
			'compact'       => array(
				'id'       => 'compact',
				'name'     => __( 'Compact', 'social-feed' ),
				'thumbnail' => 'compact',
				'pro'      => false,
				'settings' => array(
					'layout'          => 'grid',
					'columns_desktop' => 4,
					'columns_tablet'  => 3,
					'columns_mobile'  => 2,
					'image_padding'   => 4,
					'show_header'     => false,
					'show_caption'    => false,
					'show_likes'      => false,
					'show_comments'   => false,
					'show_date'       => false,
				),
			),
			'dark-mode'     => array(
				'id'       => 'dark-mode',
				'name'     => __( 'Dark Mode', 'social-feed' ),
				'thumbnail' => 'dark-mode',
				'pro'      => true,
				'settings' => array(
					'layout'          => 'grid',
					'columns_desktop' => 3,
					'dark_mode'       => true,
					'bg_color'        => '#1a1a1a',
					'text_color'      => '#ffffff',
				),
			),
			'minimal'       => array(
				'id'       => 'minimal',
				'name'     => __( 'Minimal', 'social-feed' ),
				'thumbnail' => 'minimal',
				'pro'      => true,
				'settings' => array(
					'layout'          => 'grid',
					'columns_desktop' => 1,
					'columns_tablet'  => 1,
					'columns_mobile'  => 1,
					'image_padding'   => 20,
					'show_header'     => false,
					'post_radius'     => '0',
					'border_style'    => 'none',
				),
			),
			'magazine'      => array(
				'id'       => 'magazine',
				'name'     => __( 'Magazine', 'social-feed' ),
				'thumbnail' => 'magazine',
				'pro'      => true,
				'settings' => array(
					'layout'          => 'masonry',
					'columns_desktop' => 3,
					'columns_tablet'  => 2,
					'columns_mobile'  => 1,
					'image_padding'   => 8,
					'show_caption'    => true,
					'caption_length'  => 150,
				),
			),
		);
	}

	/**
	 * Get a single template preset.
	 *
	 * @param string $template_id Template ID (classic-grid, with-header, compact, dark-mode, minimal, magazine).
	 * @return array|null Settings array or null if not found.
	 */
	public static function get_preset( $template_id ) {
		$templates = self::get_templates();

		if ( 'scratch' === $template_id ) {
			return array();
		}

		if ( isset( $templates[ $template_id ]['settings'] ) ) {
			return $templates[ $template_id ]['settings'];
		}

		return null;
	}

	/**
	 * Get full settings merged with defaults for a template.
	 *
	 * @param string $template_id Template ID.
	 * @param array  $defaults    Default settings from customizer.
	 * @return array Merged settings.
	 */
	public static function get_merged_settings( $template_id, $defaults = array() ) {
		if ( empty( $defaults ) ) {
			require_once SF_PLUGIN_PATH . 'admin/class-sf-customizer.php';
			$defaults = SF_Customizer::get_defaults();
		}

		$preset = self::get_preset( $template_id );

		if ( empty( $preset ) ) {
			return $defaults;
		}

		return wp_parse_args( $preset, $defaults );
	}

	/**
	 * Check if template is Pro and user has Pro.
	 *
	 * @param string $template_id Template ID.
	 * @return bool
	 */
	public static function is_template_locked( $template_id ) {
		$templates = self::get_templates();

		if ( ! isset( $templates[ $template_id ] ) ) {
			return false;
		}

		if ( empty( $templates[ $template_id ]['pro'] ) ) {
			return false;
		}

		return ! self::is_pro();
	}

	/**
	 * Check if Pro version is active.
	 *
	 * @return bool
	 */
	private static function is_pro() {
		return function_exists( 'sf_is_pro' ) && sf_is_pro();
	}

	/**
	 * Get template thumbnail URL (placeholder SVG or image).
	 *
	 * @param string $template_id Template ID.
	 * @return string URL or data URI.
	 */
	public static function get_thumbnail_url( $template_id ) {
		$base = SF_PLUGIN_URL . 'assets/images/templates/';
		$path = SF_PLUGIN_PATH . 'assets/images/templates/' . sanitize_file_name( $template_id ) . '.png';

		if ( file_exists( $path ) ) {
			return $base . sanitize_file_name( $template_id ) . '.png';
		}

		return self::get_placeholder_svg( $template_id );
	}

	/**
	 * Get inline SVG placeholder for template.
	 *
	 * @param string $template_id Template ID.
	 * @return string Data URI.
	 */
	private static function get_placeholder_svg( $template_id ) {
		$colors = array(
			'classic-grid' => '#e5e7eb',
			'with-header'  => '#f3f4f6',
			'compact'      => '#e0e0e0',
			'dark-mode'    => '#374151',
			'minimal'      => '#f9fafb',
			'magazine'     => '#e5e7eb',
		);
		$fill = isset( $colors[ $template_id ] ) ? $colors[ $template_id ] : '#e5e7eb';

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 140" width="200" height="140">';
		$svg .= '<rect width="200" height="140" fill="' . esc_attr( $fill ) . '"/>';
		$svg .= '<rect x="10" y="10" width="55" height="55" rx="4" fill="#d1d5db"/>';
		$svg .= '<rect x="72" y="10" width="55" height="55" rx="4" fill="#d1d5db"/>';
		$svg .= '<rect x="135" y="10" width="55" height="55" rx="4" fill="#d1d5db"/>';
		$svg .= '<rect x="10" y="72" width="55" height="55" rx="4" fill="#d1d5db"/>';
		$svg .= '<rect x="72" y="72" width="55" height="55" rx="4" fill="#d1d5db"/>';
		$svg .= '<rect x="135" y="72" width="55" height="55" rx="4" fill="#d1d5db"/>';
		$svg .= '</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
