<?php
/**
 * AJAX handlers for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Ajax
 */
class SF_Ajax {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_sf_refresh_feed', array( $this, 'refresh_feed' ) );
		add_action( 'wp_ajax_sf_save_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Verify AJAX nonce.
	 *
	 * @return bool
	 */
	private function verify_nonce() {
		return check_ajax_referer( 'sf_admin_nonce', 'nonce', false );
	}

	/**
	 * Refresh feed via AJAX.
	 */
	public function refresh_feed() {
		if ( ! $this->verify_nonce() || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'social-feed' ) ), 403 );
		}

		$platform = isset( $_POST['platform'] ) ? sanitize_key( $_POST['platform'] ) : '';
		if ( empty( $platform ) || ! in_array( $platform, array( 'instagram', 'youtube', 'facebook' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid platform', 'social-feed' ) ), 400 );
		}

		$class_name = 'SF_' . ucfirst( $platform );
		if ( ! class_exists( $class_name ) ) {
			require_once SF_PLUGIN_PATH . 'platforms/class-sf-' . $platform . '.php';
		}

		$platform_instance = new $class_name();
		$posts            = $platform_instance->fetch_posts( 10 );

		SF_Cache::delete_by_prefix( 'sf_feed_' . $platform );

		if ( is_wp_error( $posts ) ) {
			wp_send_json_error( array( 'message' => $posts->get_error_message() ), 500 );
		}

		wp_send_json_success( array( 'posts' => $posts ) );
	}

	/**
	 * Save settings via AJAX.
	 */
	public function save_settings() {
		if ( ! $this->verify_nonce() || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'social-feed' ) ), 403 );
		}

		$settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$sanitized = array();
		foreach ( $settings as $key => $value ) {
			$sanitized[ sanitize_key( $key ) ] = sanitize_text_field( $value );
		}

		update_option( 'sf_settings', $sanitized );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'social-feed' ) ) );
	}
}
