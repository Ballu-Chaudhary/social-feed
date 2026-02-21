<?php
/**
 * Shortcode registration for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Shortcode
 *
 * Handles [social_feed] shortcode registration and rendering.
 */
class SF_Shortcode {

	/**
	 * Whether assets have been registered.
	 *
	 * @var bool
	 */
	private static $assets_registered = false;

	/**
	 * Whether a feed has been rendered on this page.
	 *
	 * @var bool
	 */
	private static $feed_rendered = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'social_feed', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 10 );
		add_action( 'wp_footer', array( $this, 'late_enqueue_assets' ), 1 );
	}

	/**
	 * Register assets early without enqueueing.
	 */
	public function register_assets() {
		if ( self::$assets_registered ) {
			return;
		}

		$settings = get_option( 'sf_settings', array() );

		if ( ! isset( $settings['load_css'] ) || '1' === $settings['load_css'] ) {
			wp_register_style(
				'sf-frontend',
				SF_PLUGIN_URL . 'assets/css/frontend.css',
				array(),
				SF_VERSION
			);
		}

		if ( ! isset( $settings['load_js'] ) || '1' === $settings['load_js'] ) {
			wp_register_script(
				'sf-frontend',
				SF_PLUGIN_URL . 'assets/js/frontend.js',
				array( 'jquery' ),
				SF_VERSION,
				true
			);
		}

		self::$assets_registered = true;
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string Rendered HTML.
	 */
	public function render( $atts, $content = '' ) {
		$atts = shortcode_atts(
			array(
				'id'      => 0,
				'title'   => '',
				'columns' => '',
				'limit'   => '',
			),
			$atts,
			'social_feed'
		);

		$feed_id = absint( $atts['id'] );

		if ( ! $feed_id ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p class="sf-error">' . esc_html__( 'Social Feed: Please specify a feed ID. Example: [social_feed id="1"]', 'social-feed' ) . '</p>';
			}
			return '';
		}

		$overrides = array();

		if ( ! empty( $atts['title'] ) ) {
			$overrides['feed_title'] = sanitize_text_field( $atts['title'] );
		}

		if ( ! empty( $atts['columns'] ) ) {
			$overrides['columns'] = absint( $atts['columns'] );
		}

		if ( ! empty( $atts['limit'] ) ) {
			$overrides['posts_per_page'] = absint( $atts['limit'] );
		}

		self::$feed_rendered = true;

		$this->enqueue_assets();

		return SF_Renderer::render_feed( $feed_id, $overrides );
	}

	/**
	 * Maybe enqueue assets if shortcode is present in content.
	 */
	public function maybe_enqueue_assets() {
		global $post;

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		if ( has_shortcode( $post->post_content, 'social_feed' ) ) {
			$this->enqueue_assets();
		}
	}

	/**
	 * Late enqueue in footer if a feed was rendered but assets weren't loaded.
	 */
	public function late_enqueue_assets() {
		if ( ! self::$feed_rendered ) {
			return;
		}

		if ( ! wp_script_is( 'sf-frontend', 'enqueued' ) ) {
			$this->enqueue_assets();
			wp_print_styles( 'sf-frontend' );
			wp_print_scripts( 'sf-frontend' );
		}
	}

	/**
	 * Enqueue frontend assets.
	 */
	private function enqueue_assets() {
		$settings = get_option( 'sf_settings', array() );

		if ( ! isset( $settings['load_css'] ) || '1' === $settings['load_css'] ) {
			wp_enqueue_style( 'sf-frontend' );
		}

		if ( ! isset( $settings['load_js'] ) || '1' === $settings['load_js'] ) {
			wp_enqueue_script( 'sf-frontend' );

			if ( ! wp_script_is( 'sf-frontend', 'done' ) ) {
				wp_localize_script(
					'sf-frontend',
					'sfFrontend',
					array(
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'sf_frontend_nonce' ),
						'i18n'    => array(
							'loading'   => __( 'Loading...', 'social-feed' ),
							'load_more' => __( 'Load More', 'social-feed' ),
							'no_more'   => __( 'No more posts', 'social-feed' ),
							'error'     => __( 'Error loading posts', 'social-feed' ),
							'close'     => __( 'Close', 'social-feed' ),
							'prev'      => __( 'Previous', 'social-feed' ),
							'next'      => __( 'Next', 'social-feed' ),
						),
					)
				);
			}
		}
	}
}
