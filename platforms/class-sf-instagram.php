<?php
/**
 * Instagram API integration for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Instagram
 *
 * Instagram Basic Display API OAuth and profile methods.
 */
class SF_Instagram {

	/**
	 * Canonical OAuth redirect URI (no trailing slash).
	 *
	 * Uses plugin settings if set, otherwise rtrim(get_rest_url(...), '/') so both
	 * get_login_url and get_access_token use an identical string for Meta API.
	 *
	 * @return string
	 */
	public static function get_redirect_uri() {
		$settings     = get_option( 'sf_settings', array() );
		$redirect_uri = isset( $settings['instagram_redirect_uri'] ) ? trim( (string) $settings['instagram_redirect_uri'] ) : '';
		if ( ! empty( $redirect_uri ) ) {
			return rtrim( esc_url_raw( $redirect_uri ), '/' );
		}

		return rtrim( get_rest_url( null, 'social-feed/v1/instagram-callback' ), '/' );
	}

	/**
	 * Get Instagram API (Business Login) authorization URL.
	 *
	 * Uses Instagram API with Instagram Login (2025+) - requires Business/Creator accounts.
	 *
	 * @return string|WP_Error Login URL or error if credentials missing.
	 */
	public static function get_login_url( $redirect_uri = '' ) {
		$redirect_uri = 'https://one.mahihub.in/wp-json/social-feed/v1/instagram-callback';

		$settings = get_option( 'sf_settings', array() );
		$app_id   = isset( $settings['instagram_app_id'] ) ? trim( $settings['instagram_app_id'] ) : '';

		if ( empty( $app_id ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Instagram App ID is not configured. Please add it in Settings.', 'social-feed' )
			);
		}

		$scope = 'instagram_business_basic';
		$state = wp_generate_password( 24, false );

		// One-time OAuth CSRF token (validated in the AJAX callback).
		set_transient( 'sf_instagram_oauth_state', $state, HOUR_IN_SECONDS );

		// Store the exact redirect_uri and user_id so the callback uses the identical URI for token exchange.
		set_transient(
			'sf_ig_oauth_state_' . $state,
			array(
				'issued_at'    => time(),
				'user_id'      => get_current_user_id(),
				'redirect_uri' => $redirect_uri,
			),
			15 * MINUTE_IN_SECONDS
		);

		$url = add_query_arg(
			array(
				'force_reauth'   => 'true',
				'client_id'      => $app_id,
				'redirect_uri'   => $redirect_uri,
				'response_type'  => 'code',
				'scope'          => $scope,
				'state'          => $state,
			),
			'https://www.instagram.com/oauth/authorize'
		);

		return $url;
	}

	/**
	 * Get OAuth login URL for Instagram.
	 *
	 * Wrapper around get_login_url() so callers can use a semantically clear method name.
	 * This also ensures a one-time transient state token is issued (handled inside get_login_url()).
	 *
	 * @param string $redirect_uri Optional redirect URI override.
	 * @return string|WP_Error
	 */
	public static function get_oauth_url( $redirect_uri = '' ) {
		return self::get_login_url( $redirect_uri );
	}

	/**
	 * Issue a one-time OAuth state token and store it temporarily.
	 *
	 * @return string
	 */
	private static function issue_oauth_state() {
		$token = wp_generate_password( 24, false, false );

		set_transient(
			'sf_ig_oauth_state_' . $token,
			array(
				'issued_at' => time(),
				'user_id'   => get_current_user_id(),
			),
			15 * MINUTE_IN_SECONDS
		);

		return $token;
	}

	/**
	 * Exchange authorization code for short-lived token, then long-lived token.
	 *
	 * @param string $code Authorization code from OAuth callback.
	 * @return array|WP_Error Token data (access_token, expires_in) or error.
	 */
	public static function get_access_token( $code, $redirect_uri = '' ) {
		$settings     = get_option( 'sf_settings', array() );
		$app_id       = isset( $settings['instagram_app_id'] ) ? trim( $settings['instagram_app_id'] ) : '';
		$app_secret   = isset( $settings['instagram_app_secret'] ) ? trim( $settings['instagram_app_secret'] ) : '';
		$redirect_uri = 'https://one.mahihub.in/wp-json/social-feed/v1/instagram-callback';

		if ( empty( $app_id ) || empty( $app_secret ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Instagram API credentials not configured. Please add App ID and App Secret in Settings.', 'social-feed' )
			);
		}

		$response = wp_remote_post(
			'https://api.instagram.com/oauth/access_token',
			array(
				'timeout' => 15,
				'body'    => array(
					'client_id'     => $app_id,
					'client_secret' => $app_secret,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => $redirect_uri,
					'code'          => $code,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status ) {
			$message = self::extract_instagram_error_message( $body, __( 'Failed to exchange code for token.', 'social-feed' ) );
			return new WP_Error( 'token_exchange_failed', $message );
		}

		// New API returns data array; legacy returns flat access_token, user_id.
		$token_data = isset( $body['data'][0] ) ? $body['data'][0] : $body;
		$short_token = isset( $token_data['access_token'] ) ? $token_data['access_token'] : '';
		$user_id     = isset( $token_data['user_id'] ) ? $token_data['user_id'] : '';

		if ( empty( $short_token ) || empty( $user_id ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from Instagram. Missing access_token or user_id.', 'social-feed' ) );
		}

		// Exchange for long-lived token (60 days).
		$long_url = add_query_arg(
			array(
				'grant_type'    => 'ig_exchange_token',
				'client_secret' => $app_secret,
				'access_token'  => $short_token,
			),
			'https://graph.instagram.com/access_token'
		);

		$long_response = wp_remote_get( $long_url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $long_response ) ) {
			return new WP_Error( 'long_lived_token_exchange_failed', $long_response->get_error_message() );
		}

		$long_status = wp_remote_retrieve_response_code( $long_response );
		$long_body   = json_decode( wp_remote_retrieve_body( $long_response ), true );

		if ( 200 !== $long_status || empty( $long_body['access_token'] ) ) {
			$message = self::extract_instagram_error_message( $long_body, __( 'Failed to exchange for long-lived token.', 'social-feed' ) );
			return new WP_Error( 'long_lived_token_exchange_failed', $message );
		}

		return array(
			'access_token' => $long_body['access_token'],
			'expires_in'   => isset( $long_body['expires_in'] ) ? (int) $long_body['expires_in'] : 5184000,
			'user_id'      => $user_id,
		);
	}

	/**
	 * Fetch user profile (id, username, account_type).
	 *
	 * @param string $access_token Access token.
	 * @return array|WP_Error Profile data or error.
	 */
	public static function get_user_profile( $access_token ) {
		$url = add_query_arg(
			array(
				'fields'       => 'id,username,profile_picture_url,followers_count',
				'access_token' => $access_token,
			),
			'https://graph.instagram.com/v25.0/me'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 ) {
			$message = self::extract_instagram_error_message( $body, __( 'Failed to fetch user profile.', 'social-feed' ) );
			return new WP_Error( 'profile_fetch_failed', $message );
		}

		if ( empty( $body['id'] ) || empty( $body['username'] ) ) {
			return new WP_Error( 'invalid_profile', __( 'Invalid profile response from Instagram.', 'social-feed' ) );
		}

		return array(
			'id'                   => $body['id'],
			'username'             => $body['username'],
			'account_type'         => isset( $body['account_type'] ) ? $body['account_type'] : '',
			'profile_picture_url'  => isset( $body['profile_picture_url'] ) ? $body['profile_picture_url'] : '',
			'followers_count'      => isset( $body['followers_count'] ) ? (int) $body['followers_count'] : 0,
		);
	}

	/**
	 * Extract the most specific Instagram error message from an API payload.
	 *
	 * @param mixed  $body    Decoded response body.
	 * @param string $default Fallback message.
	 * @return string
	 */
	private static function extract_instagram_error_message( $body, $default ) {
		if ( is_array( $body ) ) {
			if ( ! empty( $body['error']['message'] ) ) {
				return (string) $body['error']['message'];
			}
			if ( ! empty( $body['error_message'] ) ) {
				return (string) $body['error_message'];
			}
			if ( ! empty( $body['message'] ) ) {
				return (string) $body['message'];
			}
		}

		return (string) $default;
	}
}
