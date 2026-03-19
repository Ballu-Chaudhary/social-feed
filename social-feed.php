<?php
/**
 * Plugin Name: Social Feed
 * Plugin URI: https://example.com/social-feed
 * Description: Display Instagram feed on your website. Connect your Instagram account and show posts in grid, list, masonry or carousel layout.
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

// Register REST API Route for Instagram OAuth Callback
add_action( 'rest_api_init', function () {
	if ( ! class_exists( 'SF_Ajax' ) ) {
		require_once plugin_dir_path( __FILE__ ) . 'admin/class-sf-ajax.php';
	}
	$sf_ajax = new SF_Ajax();

	register_rest_route( 'social-feed/v1', '/instagram-callback', array(
		'methods'             => 'GET',
		'callback'            => array( $sf_ajax, 'handle_instagram_oauth_callback' ),
		'permission_callback' => '__return_true',
	) );
} );

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

	$class_file = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

	$paths = array(
		SF_PLUGIN_PATH . 'includes/' . $class_file,
		SF_PLUGIN_PATH . 'admin/' . $class_file,
		SF_PLUGIN_PATH . 'frontend/' . $class_file,
		SF_PLUGIN_PATH . 'platforms/' . $class_file,
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
	SF_Database::create_tables();
	SF_Cron::schedule_events();

	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'sf_activate' );

/**
 * Plugin deactivation.
 */
function sf_deactivate() {
	SF_Cron::clear_events();

	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'sf_deactivate' );

/**
 * Initialize the plugin.
 */
function sf_init() {
	load_plugin_textdomain( 'social-feed', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	SF_Database::maybe_upgrade();

	$core = SF_Core::instance();
	$core->init();
}

add_action( 'plugins_loaded', 'sf_init' );

/**
 * Add Settings link to plugin action links.
 *
 * @param array $links Plugin action links.
 * @return array Modified links.
 */
function sf_plugin_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=social-feed-settings' ) ) . '">' . esc_html__( 'Settings', 'social-feed' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'sf_plugin_action_links' );

// --- TEMPORARY INSTAGRAM API DEBUGGER ---
add_action(
	'admin_notices',
	function () {
		// Only run if we pass ?sf_debug_api=1 in the URL.
		if ( ! isset( $_GET['sf_debug_api'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		// Guessing the accounts table name based on standard prefixing.
		$table_name     = $wpdb->prefix . 'sf_accounts';
		$table_name_sql = esc_sql( $table_name );

		// Check if table exists.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $table_exists !== $table_name ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Debug: Accounts table not found.', 'social-feed' ) . '</p></div>';
			return;
		}

		// Get the first connected Instagram account.
		$account = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name_sql} WHERE platform = %s LIMIT 1",
				'instagram'
			)
		);

		echo '<div class="notice notice-warning" style="background: #fff; padding: 20px; border-left: 4px solid #ffb900;">';
		echo '<h3 style="margin-top:0;">Instagram API Debug Result</h3>';

		if ( ! $account ) {
			echo '<p>No connected Instagram account found in the database.</p>';
		} else {
			if ( ! class_exists( 'SF_Instagram_API' ) ) {
				require_once plugin_dir_path( __FILE__ ) . 'platforms/class-sf-instagram-api.php';
			}

			$access_token_encrypted = isset( $account->access_token ) ? (string) $account->access_token : '';
			$access_token           = ! empty( $access_token_encrypted ) ? SF_Helpers::sf_decrypt( $access_token_encrypted ) : '';
			$access_token           = false !== $access_token ? (string) $access_token : '';

			$account_id = isset( $account->account_id_ext ) ? (string) $account->account_id_ext : '';

			$api   = new SF_Instagram_API( $access_token, $account_id );
			$media = $api->get_media( 3 ); // Try to fetch 3 posts.

			if ( is_wp_error( $media ) ) {
				echo '<p style="color:red;"><strong>API ERROR:</strong><br>';
				echo esc_html( $media->get_error_message() ) . '</p>';
				echo '<p><strong>Error Code:</strong> ' . esc_html( (string) $media->get_error_code() ) . '</p>';
			} else {
				echo '<p style="color:green;"><strong>SUCCESS: API returned posts.</strong></p>';
				echo '<pre style="background:#f0f0f0; padding:10px; overflow:auto; max-height:300px;">' . esc_html( print_r( $media, true ) ) . '</pre>';
			}
		}

		echo '</div>';
	}
);
// ----------------------------------------
