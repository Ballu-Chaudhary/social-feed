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
			'classic-grid'   => array(
				'id'        => 'classic-grid',
				'name'      => __( 'Classic Grid', 'social-feed' ),
				'thumbnail' => 'classic-grid',
				'pro'       => false,
				'mockup'    => 'grid-3',
				'settings'  => array(
					'name'             => __( 'Classic Grid', 'social-feed' ),
					'layout'           => 'grid',
					'columns_desktop'  => 3,
					'columns_tablet'   => 2,
					'columns_mobile'   => 1,
					'show_header'      => false,
					'show_caption'     => false,
					'show_likes'       => false,
					'show_comments'    => false,
					'bg_color'         => '#ffffff',
					'image_padding'    => 4,
					'hover_effect'     => 'zoom',
				),
			),
			'with-header'    => array(
				'id'        => 'with-header',
				'name'      => __( 'With Header', 'social-feed' ),
				'thumbnail' => 'with-header',
				'pro'       => false,
				'mockup'    => 'grid-3-header',
				'settings'  => array(
					'name'             => __( 'With Header', 'social-feed' ),
					'layout'           => 'grid',
					'columns_desktop'  => 3,
					'columns_tablet'   => 2,
					'columns_mobile'   => 1,
					'show_header'      => true,
					'show_profile_pic' => true,
					'show_username'    => true,
					'show_followers'   => true,
					'show_follow_btn'  => true,
					'follow_btn_color' => '#0095f6',
					'show_caption'     => false,
					'image_padding'    => 4,
					'hover_effect'     => 'zoom',
				),
			),
			'captions-feed'  => array(
				'id'        => 'captions-feed',
				'name'      => __( 'Captions Feed', 'social-feed' ),
				'thumbnail' => 'captions-feed',
				'pro'       => false,
				'mockup'    => 'grid-2-captions',
				'settings'  => array(
					'name'             => __( 'Captions Feed', 'social-feed' ),
					'layout'           => 'grid',
					'columns_desktop'  => 2,
					'columns_tablet'   => 2,
					'columns_mobile'   => 1,
					'show_header'      => true,
					'show_caption'     => true,
					'caption_length'   => 100,
					'show_date'        => true,
					'show_likes'       => true,
					'show_comments'    => true,
					'image_padding'    => 6,
					'hover_effect'     => 'fade',
				),
			),
			'compact-grid'   => array(
				'id'        => 'compact-grid',
				'name'      => __( 'Compact Grid', 'social-feed' ),
				'thumbnail' => 'compact-grid',
				'pro'       => false,
				'mockup'    => 'grid-4',
				'settings'  => array(
					'name'             => __( 'Compact Grid', 'social-feed' ),
					'layout'           => 'grid',
					'columns_desktop'  => 4,
					'columns_tablet'   => 3,
					'columns_mobile'   => 2,
					'show_header'      => false,
					'show_caption'     => false,
					'show_likes'       => false,
					'show_date'        => false,
					'image_padding'    => 2,
					'hover_effect'     => 'zoom',
				),
			),
			'dark-mode'      => array(
				'id'        => 'dark-mode',
				'name'      => __( 'Dark Mode', 'social-feed' ),
				'thumbnail' => 'dark-mode',
				'pro'       => true,
				'mockup'    => 'grid-3-dark',
				'settings'  => array(
					'name'             => __( 'Dark Mode', 'social-feed' ),
					'layout'           => 'grid',
					'columns_desktop'  => 3,
					'columns_tablet'   => 2,
					'columns_mobile'   => 1,
					'bg_color'         => '#0f0f0f',
					'text_color'       => '#ffffff',
					'show_header'      => true,
					'dark_mode'        => true,
					'image_padding'    => 4,
					'hover_effect'     => 'zoom',
					'follow_btn_color' => '#ffffff',
				),
			),
			'minimal-single' => array(
				'id'        => 'minimal-single',
				'name'      => __( 'Minimal Single', 'social-feed' ),
				'thumbnail' => 'minimal-single',
				'pro'       => true,
				'mockup'    => 'grid-1',
				'settings'  => array(
					'name'             => __( 'Minimal Single', 'social-feed' ),
					'layout'           => 'grid',
					'columns_desktop'  => 1,
					'columns_tablet'   => 1,
					'columns_mobile'   => 1,
					'show_header'      => true,
					'show_caption'     => true,
					'caption_length'   => 200,
					'show_date'        => true,
					'show_likes'       => true,
					'image_padding'    => 12,
					'hover_effect'     => 'fade',
				),
			),
			'magazine'       => array(
				'id'        => 'magazine',
				'name'      => __( 'Magazine Style', 'social-feed' ),
				'thumbnail' => 'magazine',
				'pro'       => true,
				'mockup'    => 'masonry',
				'settings'  => array(
					'name'             => __( 'Magazine Style', 'social-feed' ),
					'layout'           => 'masonry',
					'columns_desktop'  => 3,
					'columns_tablet'   => 2,
					'columns_mobile'   => 1,
					'show_header'      => true,
					'show_caption'     => true,
					'caption_length'   => 120,
					'show_likes'       => true,
					'show_date'        => true,
					'image_padding'    => 6,
					'hover_effect'     => 'zoom',
				),
			),
			'highlight-feed' => array(
				'id'        => 'highlight-feed',
				'name'      => __( 'Highlight Feed', 'social-feed' ),
				'thumbnail' => 'highlight-feed',
				'pro'       => true,
				'mockup'    => 'highlight',
				'settings'  => array(
					'name'             => __( 'Highlight Feed', 'social-feed' ),
					'layout'           => 'highlight',
					'columns_desktop'  => 3,
					'columns_tablet'   => 2,
					'columns_mobile'   => 1,
					'show_header'      => true,
					'show_caption'     => false,
					'image_padding'    => 3,
					'hover_effect'     => 'zoom',
				),
			),
		);
	}

	/**
	 * Get a single template preset.
	 *
	 * @param string $template_id Template ID (e.g. classic-grid, with-header).
	 * @return array|null Settings array or null if not found.
	 */
	public static function get_preset( $template_id ) {
		$templates = self::get_templates();

		if ( in_array( $template_id, array( 'custom', 'scratch' ), true ) ) {
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
	 * Check if template is Pro and user does not have Pro.
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
	 * Get template thumbnail URL (placeholder image).
	 *
	 * Returns empty string when using CSS mockup preview.
	 *
	 * @param string $template_id Template ID.
	 * @return string URL or empty for CSS mockup.
	 */
	public static function get_thumbnail_url( $template_id ) {
		$base = SF_PLUGIN_URL . 'assets/images/templates/';
		$path = SF_PLUGIN_PATH . 'assets/images/templates/' . sanitize_file_name( $template_id ) . '.png';

		if ( file_exists( $path ) ) {
			return $base . sanitize_file_name( $template_id ) . '.png';
		}

		return '';
	}

	/**
	 * Get mockup type for CSS-drawn preview.
	 *
	 * @param string $template_id Template ID.
	 * @return string Mockup type (grid-3, grid-3-header, etc.).
	 */
	public static function get_mockup_type( $template_id ) {
		$templates = self::get_templates();
		if ( isset( $templates[ $template_id ]['mockup'] ) ) {
			return $templates[ $template_id ]['mockup'];
		}
		return 'grid-3';
	}
}
