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
	}

	/**
	 * Load required files and classes.
	 */
	private function load_dependencies() {
		require_once SF_PLUGIN_PATH . 'includes/class-sf-database.php';
		require_once SF_PLUGIN_PATH . 'includes/class-sf-cache.php';
		require_once SF_PLUGIN_PATH . 'includes/class-sf-helpers.php';

		if ( is_admin() ) {
			require_once SF_PLUGIN_PATH . 'admin/class-sf-admin.php';
			require_once SF_PLUGIN_PATH . 'admin/class-sf-ajax.php';
		}

		require_once SF_PLUGIN_PATH . 'frontend/class-sf-renderer.php';
		require_once SF_PLUGIN_PATH . 'frontend/class-sf-shortcode.php';
	}

	/**
	 * Initialize plugin components.
	 */
	private function init_components() {
		if ( is_admin() ) {
			new SF_Admin();
			new SF_Ajax();
		}

		new SF_Shortcode();
	}
}
