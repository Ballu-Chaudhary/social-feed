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
	 * OAuth redirect URI.
	 *
	 * @return string
	 */
	public static function get_redirect_uri() {
		return admin_url( 'admin.php' );
	}

	/**
	 * Get Instagram API (Business Login) authorization URL.
	 *
	 * Uses Instagram API with Instagram Login (2025+) - requires Business/Creator accounts.
	 *
	 * @return string|WP_Error Login URL or error if credentials missing.
	 */
	public static function get_login_url() {
		$redirect_uri = self::get_redirect_uri();

		$settings = get_option( 'sf_settings', array() );
		$app_id   = isset( $settings['instagram_app_id'] ) ? trim( $settings['instagram_app_id'] ) : '';

		if ( empty( $app_id ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Instagram App ID is not configured. Please add it in Settings.', 'social-feed' )
			);
		}

		$scope = 'instagram_business_basic';
		$state = 'social-feed-create';

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
	 * Exchange authorization code for short-lived token, then long-lived token.
	 *
	 * @param string $code Authorization code from OAuth callback.
	 * @return array|WP_Error Token data (access_token, expires_in) or error.
	 */
	public static function get_access_token( $code ) {
		$settings    = get_option( 'sf_settings', array() );
		$app_id      = isset( $settings['instagram_app_id'] ) ? trim( $settings['instagram_app_id'] ) : '';
		$app_secret  = isset( $settings['instagram_app_secret'] ) ? trim( $settings['instagram_app_secret'] ) : '';
		$redirect_uri = self::get_redirect_uri();

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
			$message = isset( $body['error_message'] ) ? $body['error_message'] : __( 'Failed to exchange code for token.', 'social-feed' );
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
			return array(
				'access_token' => $short_token,
				'expires_in'   => 3600,
				'user_id'      => $user_id,
			);
		}

		$long_body = json_decode( wp_remote_retrieve_body( $long_response ), true );

		return array(
			'access_token' => isset( $long_body['access_token'] ) ? $long_body['access_token'] : $short_token,
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
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Failed to fetch user profile.', 'social-feed' );
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
}
