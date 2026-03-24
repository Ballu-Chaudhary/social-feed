<?php
/**
 * OAuth callback handler for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_OAuth
 */
class SF_OAuth {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_callback' ) );
	}

	/**
	 * Handle OAuth init (redirect to Instagram) and callback.
	 */
	public function handle_callback() {
		$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		// Init: redirect to Instagram OAuth when user clicks Connect.
		$oauth_init = isset( $_GET['sf_oauth_init'] ) && '1' === $_GET['sf_oauth_init'];
		if ( $oauth_init && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'sf_oauth_init' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Unauthorized access.', 'social-feed' ) );
			}
			if ( class_exists( 'SF_Instagram' ) ) {
				$redirect_uri = SF_Instagram::get_redirect_uri();
				$this->store_redirect_uri_for_exchange( $redirect_uri );
				$url = SF_Instagram::get_login_url( $redirect_uri );
				if ( is_wp_error( $url ) ) {
					$this->redirect_with_error( $url->get_error_message() );
				}
				if ( ! empty( $url ) ) {
					wp_redirect( $url );
					exit;
				}
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'social-feed-create', 'sf_error' => '1', 'sf_msg' => rawurlencode( __( 'Could not build OAuth URL. Check App ID and App Secret in Settings.', 'social-feed' ) ) ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Callback: handle return from Instagram with code.
		$has_oauth_response = isset( $_GET['code'] ) || isset( $_GET['error'] );
		if ( empty( $page ) && $has_oauth_response ) {
			$page = 'social-feed-create';
		}

		if ( $has_oauth_response ) {
			$saved_state = get_transient( 'sf_instagram_oauth_state' );
			delete_transient( 'sf_instagram_oauth_state' );

			if ( empty( $state ) || $state !== $saved_state ) {
				$this->redirect_with_error( __( 'Invalid or expired OAuth state. Please retry the connection from the WordPress admin.', 'social-feed' ) );
				return;
			}
		}

		$is_oauth_callback = 'social-feed-create' === $page && $has_oauth_response;
		if ( ! $is_oauth_callback ) {
			return;
		}

		// Reuse the existing callback logic from SF_Ajax on admin page callback.
		if ( ! class_exists( 'SF_Ajax' ) ) {
			require_once SF_PLUGIN_PATH . 'admin/class-sf-ajax.php';
		}
		$ajax = new SF_Ajax();
		$ajax->handle_instagram_oauth_callback();
		exit;
	}

	/**
	 * Redirect back to Accounts page with error message.
	 *
	 * @param string $message Error message.
	 */
	private function redirect_with_error( $message ) {
		$redirect_url = add_query_arg(
			array(
				'page'        => 'social-feed-create',
				'sf_error'    => '1',
				'sf_msg'      => rawurlencode( $message ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Store the exact redirect URI used in OAuth URL.
	 *
	 * @param string $redirect_uri Redirect URI.
	 */
	private function store_redirect_uri_for_exchange( $redirect_uri ) {
		if ( empty( $redirect_uri ) ) {
			return;
		}

		set_transient( $this->get_redirect_uri_transient_key(), (string) $redirect_uri, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Get and clear stored redirect URI, ensuring token exchange uses exact same value.
	 *
	 * @return string
	 */
	private function consume_redirect_uri_for_exchange() {
		$key          = $this->get_redirect_uri_transient_key();
		$redirect_uri = get_transient( $key );
		delete_transient( $key );

		if ( ! empty( $redirect_uri ) ) {
			return (string) $redirect_uri;
		}

		return class_exists( 'SF_Instagram' ) ? SF_Instagram::get_redirect_uri() : admin_url( 'admin.php' );
	}

	/**
	 * Get per-user transient key for redirect URI.
	 *
	 * @return string
	 */
	private function get_redirect_uri_transient_key() {
		return 'sf_oauth_redirect_uri_' . get_current_user_id();
	}
}
