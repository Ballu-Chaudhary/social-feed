<?php
/**
 * Shortcode handler for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Shortcode
 */
class SF_Shortcode {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'social_feed', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Render shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$this->enqueue();

		$atts = SF_Helpers::sanitize_shortcode_atts( $atts );

		$platform = $atts['platform'];
		$layout   = $atts['layout'];
		$limit    = $atts['limit'];
		$columns  = $atts['columns'];

		$class_name = 'SF_' . ucfirst( $platform );
		if ( ! class_exists( $class_name ) ) {
			$platform_file = SF_PLUGIN_PATH . 'platforms/class-sf-' . $platform . '.php';
			if ( file_exists( $platform_file ) ) {
				require_once $platform_file;
			}
		}

		$posts = array();
		if ( class_exists( $class_name ) ) {
			$platform_instance = new $class_name();
			$posts            = $platform_instance->fetch_posts( $limit );
			if ( is_wp_error( $posts ) ) {
				$posts = array();
			}
		}

		$renderer = new SF_Renderer();
		$output   = $renderer->render( $platform, $layout, $posts, $atts );

		$wrapper_class = sprintf(
			'sf-feed sf-feed--%s sf-feed--%s sf-feed--cols-%d',
			esc_attr( $platform ),
			esc_attr( $layout ),
			$columns
		);

		return '<div class="' . esc_attr( $wrapper_class ) . '">' . $output . '</div>';
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_assets() {
		global $post;
		if ( ! is_singular() || ! $post || ! has_shortcode( $post->post_content, 'social_feed' ) ) {
			return;
		}

		$this->enqueue();
	}

	/**
	 * Enqueue CSS and JS assets.
	 */
	private function enqueue() {
		wp_enqueue_style(
			'sf-frontend',
			SF_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			SF_VERSION
		);

		wp_enqueue_script(
			'sf-frontend',
			SF_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			SF_VERSION,
			true
		);
	}
}
