<?php
/**
 * Plugin Name: Social Feed
 * Plugin URI: https://example.com/social-feed
 * Description: Display social media feeds from Instagram, YouTube, and Facebook.
 * Version: 1.0.0
 * Author: Baldev Chaudhary
 * Author URI: 
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: social-feed
 * Domain Path: /languages
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'SF_VERSION', '1.0.0' );
define( 'SF_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader for Social Feed classes.
 *
 * @param string $class_name The class name to load.
 */
function sf_autoloader( $class_name ) {
	if ( strpos( $class_name, 'SF_' ) !== 0 ) {
		return;
	}

	$class_file = 'class-' . strtolower( str_replace( '_', '-', substr( $class_name, 3 ) ) ) . '.php';

	$paths = array(
		SF_PLUGIN_PATH . 'includes/' . $class_file,
		SF_PLUGIN_PATH . 'admin/' . $class_file,
	);

	foreach ( $paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}
}

spl_autoload_register( 'sf_autoloader' );

/**
 * Plugin activation.
 */
function sf_activate() {
	require_once SF_PLUGIN_PATH . 'includes/class-sf-database.php';
	SF_Database::create_tables();
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'sf_activate' );

/**
 * Plugin deactivation.
 */
function sf_deactivate() {
	$cron = get_option( 'cron', array() );
	if ( is_array( $cron ) ) {
		$cleared = array();
		foreach ( $cron as $timestamp => $hooks ) {
			if ( is_array( $hooks ) ) {
				foreach ( $hooks as $hook => $events ) {
					if ( strpos( $hook, 'sf_' ) === 0 && ! isset( $cleared[ $hook ] ) ) {
						wp_clear_scheduled_hook( $hook );
						$cleared[ $hook ] = true;
					}
				}
			}
		}
	}
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'sf_deactivate' );

/**
 * Initialize the plugin.
 */
function sf_init() {
	$core = new SF_Core();
	$core->init();
}

add_action( 'plugins_loaded', 'sf_init' );
