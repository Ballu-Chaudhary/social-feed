<?php
/**
 * Gutenberg block registration for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Blocks
 *
 * Handles Gutenberg block registration.
 */
class SF_Blocks {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Register Gutenberg blocks.
	 */
	public function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			'social-feed/feed',
			array(
				'attributes'      => array(
					'feedId' => array(
						'type'    => 'number',
						'default' => 0,
					),
					'title'  => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render_callback' => array( $this, 'render_feed_block' ),
				'editor_script'   => 'sf-block-editor',
				'editor_style'    => 'sf-block-editor-style',
			)
		);
	}

	/**
	 * Enqueue block editor assets.
	 */
	public function enqueue_editor_assets() {
		$feeds = SF_Database::get_all_feeds( array( 'status' => 'active', 'limit' => 100 ) );

		$feed_options = array(
			array(
				'value' => 0,
				'label' => __( 'Select a feed...', 'social-feed' ),
			),
		);

		foreach ( $feeds as $feed ) {
			$feed_options[] = array(
				'value' => absint( $feed['id'] ),
				'label' => sprintf(
					'%s (#%d) - %s',
					esc_html( $feed['name'] ),
					absint( $feed['id'] ),
					ucfirst( $feed['platform'] )
				),
			);
		}

		wp_enqueue_script(
			'sf-block-editor',
			SF_PLUGIN_URL . 'assets/js/block-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			SF_VERSION,
			true
		);

		wp_localize_script(
			'sf-block-editor',
			'sfBlockData',
			array(
				'feeds'      => $feed_options,
				'pluginUrl'  => SF_PLUGIN_URL,
				'adminUrl'   => admin_url( 'admin.php?page=social-feed' ),
				'createUrl'  => admin_url( 'admin.php?page=social-feed-create' ),
				'i18n'       => array(
					'title'           => __( 'Social Feed', 'social-feed' ),
					'description'     => __( 'Display a social media feed on your site.', 'social-feed' ),
					'select_feed'     => __( 'Select a Feed', 'social-feed' ),
					'no_feed'         => __( 'Please select a feed to display.', 'social-feed' ),
					'create_feed'     => __( 'Create a Feed', 'social-feed' ),
					'no_feeds'        => __( 'No feeds created yet.', 'social-feed' ),
					'manage_feeds'    => __( 'Manage Feeds', 'social-feed' ),
					'feed_id'         => __( 'Feed ID', 'social-feed' ),
					'custom_title'    => __( 'Custom Title (optional)', 'social-feed' ),
				),
			)
		);

		wp_enqueue_style(
			'sf-block-editor-style',
			SF_PLUGIN_URL . 'assets/css/block-editor.css',
			array(),
			SF_VERSION
		);
	}

	/**
	 * Render feed block on the frontend.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered HTML.
	 */
	public function render_feed_block( $attributes ) {
		$feed_id = isset( $attributes['feedId'] ) ? absint( $attributes['feedId'] ) : 0;

		if ( ! $feed_id ) {
			if ( is_admin() ) {
				return '<div class="sf-block-placeholder">' . 
					'<p>' . esc_html__( 'Please select a feed from the block settings.', 'social-feed' ) . '</p>' . 
					'</div>';
			}
			return '';
		}

		$overrides = array();

		if ( ! empty( $attributes['title'] ) ) {
			$overrides['feed_title'] = sanitize_text_field( $attributes['title'] );
		}

		SF_Renderer::enqueue_feed_assets( $feed_id );

		return SF_Renderer::render_feed( $feed_id, $overrides );
	}
}
