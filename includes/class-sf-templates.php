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
 * Template presets for creating feeds. All templates are free.
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
				'id'          => 'classic-grid',
				'name'        => __( 'Classic Grid', 'social-feed' ),
				'description' => __( '3 col, clean, no header, no captions', 'social-feed' ),
				'category'    => 'grid',
				'mockup'      => 'grid-3',
				'settings'    => array(
					'layout'          => 'grid',
					'columns_desktop' => 3,
					'columns_tablet'  => 2,
					'columns_mobile'  => 1,
					'show_header'     => false,
					'show_caption'    => false,
					'show_likes'      => false,
					'show_comments'   => false,
					'bg_color'        => '#ffffff',
					'image_padding'   => 4,
					'hover_effect'    => 'zoom',
				),
			),
			'with-header'    => array(
				'id'          => 'with-header',
				'name'        => __( 'With Header', 'social-feed' ),
				'description' => __( '3 col, profile header on top, follow button', 'social-feed' ),
				'category'    => 'with-header',
				'mockup'      => 'grid-3-header',
				'settings'    => array(
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
				'id'          => 'captions-feed',
				'name'        => __( 'Captions Feed', 'social-feed' ),
				'description' => __( '2 col, captions and date below each post', 'social-feed' ),
				'category'    => 'with-header',
				'mockup'      => 'grid-2-captions',
				'settings'    => array(
					'layout'          => 'grid',
					'columns_desktop' => 2,
					'columns_tablet'  => 2,
					'columns_mobile'  => 1,
					'show_header'     => true,
					'show_caption'    => true,
					'caption_length'  => 100,
					'show_date'       => true,
					'show_likes'      => true,
					'show_comments'   => true,
					'image_padding'   => 6,
					'hover_effect'    => 'fade',
				),
			),
			'compact-grid'   => array(
				'id'          => 'compact-grid',
				'name'        => __( 'Compact Grid', 'social-feed' ),
				'description' => __( '4 col, tight spacing, no text at all', 'social-feed' ),
				'category'    => 'grid',
				'mockup'      => 'grid-4',
				'settings'    => array(
					'layout'          => 'grid',
					'columns_desktop' => 4,
					'columns_tablet'  => 3,
					'columns_mobile'  => 2,
					'show_header'     => false,
					'show_caption'    => false,
					'show_likes'      => false,
					'show_date'       => false,
					'image_padding'   => 2,
					'hover_effect'    => 'zoom',
				),
			),
			'dark-theme'     => array(
				'id'          => 'dark-theme',
				'name'        => __( 'Dark Theme', 'social-feed' ),
				'description' => __( '3 col, dark background, white text', 'social-feed' ),
				'category'    => 'grid',
				'mockup'      => 'grid-3-dark',
				'settings'    => array(
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
				'id'          => 'minimal-single',
				'name'        => __( 'Minimal Single', 'social-feed' ),
				'description' => __( '1 col, large images, full captions', 'social-feed' ),
				'category'    => 'single',
				'mockup'      => 'grid-1',
				'settings'    => array(
					'layout'          => 'grid',
					'columns_desktop' => 1,
					'columns_tablet'  => 1,
					'columns_mobile'  => 1,
					'show_header'     => true,
					'show_caption'    => true,
					'caption_length'  => 200,
					'show_date'       => true,
					'show_likes'      => true,
					'image_padding'   => 12,
					'hover_effect'    => 'fade',
				),
			),
			'square-tight'   => array(
				'id'          => 'square-tight',
				'name'        => __( 'Square Tight', 'social-feed' ),
				'description' => __( '3 col, zero gap, no text', 'social-feed' ),
				'category'    => 'grid',
				'mockup'      => 'grid-3-tight',
				'settings'    => array(
					'layout'          => 'grid',
					'columns_desktop' => 3,
					'columns_tablet'  => 3,
					'columns_mobile'  => 2,
					'show_header'     => false,
					'show_caption'    => false,
					'show_likes'      => false,
					'show_comments'   => false,
					'image_padding'   => 0,
					'post_radius'     => '0',
					'hover_effect'    => 'zoom',
				),
			),
			'soft-card'      => array(
				'id'          => 'soft-card',
				'name'        => __( 'Soft Card', 'social-feed' ),
				'description' => __( '3 col, rounded corners, soft shadow on each post', 'social-feed' ),
				'category'    => 'grid',
				'mockup'      => 'grid-3-card',
				'settings'    => array(
					'layout'          => 'grid',
					'columns_desktop' => 3,
					'columns_tablet'  => 2,
					'columns_mobile'  => 1,
					'show_header'     => true,
					'show_caption'    => false,
					'image_padding'   => 8,
					'post_radius'     => '16',
					'border_style'    => 'solid',
					'border_color'    => '#e5e7eb',
					'border_radius'   => 12,
					'hover_effect'    => 'zoom',
				),
			),
			'bold-magazine'  => array(
				'id'          => 'bold-magazine',
				'name'        => __( 'Bold Magazine', 'social-feed' ),
				'description' => __( '2 col, large thumbnails, captions with likes', 'social-feed' ),
				'category'    => 'with-header',
				'mockup'      => 'grid-2-large',
				'settings'    => array(
					'layout'          => 'grid',
					'columns_desktop' => 2,
					'columns_tablet'  => 2,
					'columns_mobile'  => 1,
					'show_header'     => true,
					'show_caption'    => true,
					'caption_length'  => 120,
					'show_likes'      => true,
					'show_date'       => true,
					'image_padding'   => 6,
					'hover_effect'    => 'zoom',
				),
			),
			'story-style'    => array(
				'id'          => 'story-style',
				'name'        => __( 'Story Style', 'social-feed' ),
				'description' => __( '1 col, tall portrait images, minimal design', 'social-feed' ),
				'category'    => 'single',
				'mockup'      => 'grid-1-tall',
				'settings'    => array(
					'layout'          => 'grid',
					'columns_desktop' => 1,
					'columns_tablet'  => 1,
					'columns_mobile'  => 1,
					'show_header'     => true,
					'show_caption'    => false,
					'show_likes'      => false,
					'image_padding'   => 4,
					'post_radius'     => '8',
					'hover_effect'    => 'fade',
				),
			),
			'gallery-wall'   => array(
				'id'          => 'gallery-wall',
				'name'        => __( 'Gallery Wall', 'social-feed' ),
				'description' => __( '4 col, very tight grid, hover shows likes', 'social-feed' ),
				'category'    => 'grid',
				'mockup'      => 'grid-4-tight',
				'settings'    => array(
					'layout'          => 'grid',
					'columns_desktop' => 4,
					'columns_tablet'  => 3,
					'columns_mobile'  => 2,
					'show_header'     => false,
					'show_caption'    => false,
					'show_likes'      => true,
					'image_padding'   => 1,
					'hover_effect'    => 'zoom',
				),
			),
			'profile-card'   => array(
				'id'          => 'profile-card',
				'name'        => __( 'Profile Card', 'social-feed' ),
				'description' => __( '3 col, full header with bio, styled follow button', 'social-feed' ),
				'category'    => 'with-header',
				'mockup'      => 'grid-3-header',
				'settings'    => array(
					'layout'           => 'grid',
					'columns_desktop'  => 3,
					'columns_tablet'   => 2,
					'columns_mobile'   => 1,
					'show_header'      => true,
					'show_profile_pic' => true,
					'show_username'    => true,
					'show_followers'   => true,
					'show_bio'         => true,
					'show_follow_btn'  => true,
					'follow_btn_color' => '#0095f6',
					'follow_btn_text'  => 'Follow on Instagram',
					'show_caption'     => false,
					'image_padding'    => 4,
					'hover_effect'     => 'zoom',
				),
			),
		);
	}

	/**
	 * Get a single template.
	 *
	 * @param string $template_id Template ID.
	 * @return array|null Template array or null.
	 */
	public static function get_template( $template_id ) {
		$templates = self::get_templates();
		return isset( $templates[ $template_id ] ) ? $templates[ $template_id ] : null;
	}

	/**
	 * Get template preset (settings only).
	 *
	 * @param string $template_id Template ID.
	 * @return array|null Settings or null.
	 */
	public static function get_preset( $template_id ) {
		$t = self::get_template( $template_id );
		return $t && isset( $t['settings'] ) ? $t['settings'] : null;
	}

	/**
	 * Get merged settings with defaults.
	 *
	 * @param string $template_id Template ID.
	 * @param array  $defaults    Defaults.
	 * @return array
	 */
	public static function get_merged_settings( $template_id, $defaults = array() ) {
		if ( empty( $defaults ) ) {
			require_once SF_PLUGIN_PATH . 'admin/class-sf-customizer.php';
			$defaults = SF_Customizer::get_defaults();
		}
		$preset = self::get_preset( $template_id );
		return empty( $preset ) ? $defaults : wp_parse_args( $preset, $defaults );
	}

	/**
	 * Get thumbnail URL.
	 *
	 * @param string $template_id Template ID.
	 * @return string URL or empty.
	 */
	public static function get_thumbnail_url( $template_id ) {
		$path = SF_PLUGIN_PATH . 'assets/images/templates/' . sanitize_file_name( $template_id ) . '.png';
		if ( file_exists( $path ) ) {
			return SF_PLUGIN_URL . 'assets/images/templates/' . sanitize_file_name( $template_id ) . '.png';
		}
		return '';
	}
}
