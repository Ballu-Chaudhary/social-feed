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

		if ( ! empty( $state ) && 'social-feed-create' !== $state && $has_oauth_response ) {
			return;
		}

		$is_oauth_callback = 'social-feed-create' === $page && $has_oauth_response;
		if ( ! $is_oauth_callback ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_with_error( __( 'Unauthorized access.', 'social-feed' ) );
			return;
		}

		$code      = isset( $_GET['code'] ) ? trim( (string) wp_unslash( $_GET['code'] ) ) : '';
		$error     = isset( $_GET['error'] ) ? trim( (string) wp_unslash( $_GET['error'] ) ) : '';
		$error_msg = isset( $_GET['error_description'] ) ? trim( (string) wp_unslash( $_GET['error_description'] ) ) : '';

		if ( ! empty( $error ) ) {
			$this->redirect_with_error( ! empty( $error_msg ) ? $error_msg : $error );
			return;
		}

		if ( empty( $code ) ) {
			$this->redirect_with_error( __( 'Invalid callback parameters.', 'social-feed' ) );
			return;
		}

		if ( ! class_exists( 'SF_Instagram' ) ) {
			$this->redirect_with_error( __( 'Instagram OAuth is not available.', 'social-feed' ) );
			return;
		}

		$redirect_uri = $this->consume_redirect_uri_for_exchange();
		$token_data   = SF_Instagram::get_access_token( $code, $redirect_uri );
		if ( is_wp_error( $token_data ) ) {
			SF_Helpers::sf_log_error( 'Instagram OAuth: ' . $token_data->get_error_message(), 'instagram' );
			$this->redirect_with_error( $token_data->get_error_message() );
			return;
		}

		$profile = SF_Instagram::get_user_profile( $token_data['access_token'] );
		if ( is_wp_error( $profile ) ) {
			SF_Helpers::sf_log_error( 'Instagram OAuth profile: ' . $profile->get_error_message(), 'instagram' );
			$this->redirect_with_error( $profile->get_error_message() );
			return;
		}

		$account_id_ext = $token_data['user_id'] ?? $profile['id'];
		$account_name   = $profile['username'] ?? $account_id_ext;
		$access_token   = $token_data['access_token'];
		$expires_in     = isset( $token_data['expires_in'] ) ? (int) $token_data['expires_in'] : 0;
		$profile_pic    = ! empty( $profile['profile_picture_url'] ) ? esc_url_raw( $profile['profile_picture_url'] ) : '';

		$encrypted_token = SF_Helpers::sf_encrypt( $access_token );
		$token_expires   = $expires_in > 0 ? gmdate( 'Y-m-d H:i:s', time() + $expires_in ) : null;

		$existing = SF_Database::get_account_by_external_id( 'instagram', $account_id_ext );

		$account_data = array(
			'platform'       => 'instagram',
			'account_name'   => $account_name,
			'account_id_ext' => $account_id_ext,
			'access_token'   => $encrypted_token,
			'refresh_token'  => null,
			'token_expires'  => $token_expires,
			'profile_pic'    => $profile_pic,
			'is_connected'   => 1,
			'last_error'     => null,
		);

		if ( $existing ) {
			$result     = SF_Database::update_account( $existing['id'], $account_data );
			$account_id = $existing['id'];
		} else {
			global $wpdb;

			$accounts_table = SF_Database::get_table( 'accounts' );
			$table_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $accounts_table ) );
			if ( $accounts_table !== $table_exists ) {
				SF_Database::create_tables();
			}

			$account_data['wp_user_id'] = get_current_user_id();
			$account_id = SF_Database::create_account( $account_data );
			$result     = $account_id ? true : false;
		}

		if ( ! $result && ! $account_id ) {
			global $wpdb;
			$db_error = ( isset( $wpdb->last_error ) && '' !== $wpdb->last_error ) ? $wpdb->last_error : __( 'Unknown database error.', 'social-feed' );
			$this->redirect_with_error( sprintf( __( 'Failed to save account to database: %s', 'social-feed' ), $db_error ) );
			return;
		}

		SF_Helpers::sf_log_success(
			sprintf( 'Instagram account @%s connected successfully.', $account_name ),
			'instagram'
		);

		$redirect_url = add_query_arg(
			array(
				'page'         => 'social-feed-create',
				'sf_connected' => '1',
				'account_id'   => $account_id,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
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
