<?php
/**
 * Core loader for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Core
 */
class SF_Core {

	/**
	 * Plugin instance.
	 *
	 * @var SF_Core
	 */
	private static $instance = null;

	/**
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {}

	/**
	 * Get instance.
	 *
	 * @return SF_Core
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		$this->load_dependencies();
		$this->init_components();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks and filters.
	 */
	private function init_hooks() {
		add_filter( 'sf_feed_items', array( $this, 'filter_feed_items' ), 10, 3 );
	}

	/**
	 * Filter feed items before display.
	 *
	 * @param array $items    Feed items.
	 * @param int   $feed_id  Feed ID.
	 * @param array $settings Feed settings.
	 * @return array Filtered items.
	 */
	public function filter_feed_items( $items, $feed_id, $settings ) {
		return $items;
	}

	/**
	 * Load required files and classes.
	 *
	 * SF_ classes in includes/, admin/, frontend/, and platforms/ are auto-loaded.
	 * Only load files the autoloader cannot reach (e.g. blocks/).
	 */
	private function load_dependencies() {
		if ( file_exists( SF_PLUGIN_PATH . 'blocks/class-sf-blocks.php' ) ) {
			require_once SF_PLUGIN_PATH . 'blocks/class-sf-blocks.php';
		}
	}

	/**
	 * Initialize plugin components.
	 */
	private function init_components() {
		new SF_Cron();
		new SF_License();

		if ( is_admin() ) {
			new SF_Admin();
			new SF_Ajax();
			new SF_OAuth();
		}

		new SF_Shortcode();

		if ( class_exists( 'SF_Blocks' ) ) {
			new SF_Blocks();
		}
	}
}
