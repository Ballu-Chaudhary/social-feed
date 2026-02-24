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
	 * Handle OAuth callback.
	 */
	public function handle_callback() {
		if ( ! isset( $_GET['page'] ) || 'social-feed-accounts' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['sf_oauth_callback'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->send_error( __( 'Permission denied.', 'social-feed' ) );
			return;
		}

		$code     = isset( $_GET['code'] ) ? sanitize_text_field( $_GET['code'] ) : '';
		$state    = isset( $_GET['state'] ) ? sanitize_text_field( $_GET['state'] ) : '';
		$error    = isset( $_GET['error'] ) ? sanitize_text_field( $_GET['error'] ) : '';
		$error_msg = isset( $_GET['error_description'] ) ? sanitize_text_field( $_GET['error_description'] ) : '';

		if ( ! empty( $error ) ) {
			$this->send_error( $error_msg ?: $error );
			return;
		}

		if ( empty( $code ) || empty( $state ) ) {
			$this->send_error( __( 'Invalid callback parameters.', 'social-feed' ) );
			return;
		}

		$platform = $this->get_platform_from_state( $state );
		if ( ! $platform ) {
			$this->send_error( __( 'Invalid state parameter.', 'social-feed' ) );
			return;
		}

		$result = $this->exchange_code( $platform, $code );

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}

		$this->send_success( $platform, $result );
	}

	/**
	 * Get platform from state nonce.
	 *
	 * @param string $state State parameter.
	 * @return string|false Platform slug or false.
	 */
	private function get_platform_from_state( $state ) {
		if ( wp_verify_nonce( $state, 'sf_oauth_instagram' ) ) {
			return 'instagram';
		}
		return false;
	}

	/**
	 * Exchange authorization code for access token.
	 *
	 * @param string $platform Platform slug.
	 * @param string $code     Authorization code.
	 * @return array|WP_Error Token data or error.
	 */
	private function exchange_code( $platform, $code ) {
		if ( 'instagram' !== $platform ) {
			return new WP_Error( 'invalid_platform', __( 'Only Instagram is supported.', 'social-feed' ) );
		}
		$settings     = get_option( 'sf_settings', array() );
		$redirect_uri = admin_url( 'admin.php?page=social-feed-accounts&sf_oauth_callback=1' );
		return $this->exchange_instagram_code( $code, $settings, $redirect_uri );
	}

	/**
	 * Exchange Instagram authorization code.
	 *
	 * @param string $code        Authorization code.
	 * @param array  $settings    Plugin settings.
	 * @param string $redirect_uri Redirect URI.
	 * @return array|WP_Error
	 */
	private function exchange_instagram_code( $code, $settings, $redirect_uri ) {
		$app_id     = isset( $settings['instagram_app_id'] ) ? $settings['instagram_app_id'] : '';
		$app_secret = isset( $settings['instagram_app_secret'] ) ? $settings['instagram_app_secret'] : '';

		if ( empty( $app_id ) || empty( $app_secret ) ) {
			return new WP_Error( 'missing_credentials', __( 'Instagram API credentials not configured.', 'social-feed' ) );
		}

		$response = wp_remote_post(
			'https://api.instagram.com/oauth/access_token',
			array(
				'body' => array(
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

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error_message'] ) ) {
			return new WP_Error( 'api_error', $body['error_message'] );
		}

		if ( empty( $body['access_token'] ) || empty( $body['user_id'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from Instagram.', 'social-feed' ) );
		}

		$long_lived = $this->get_instagram_long_lived_token( $body['access_token'], $app_secret );

		$user_info = $this->get_instagram_user_info( $long_lived['access_token'] ?? $body['access_token'] );

		return array(
			'account_id'    => $body['user_id'],
			'account_name'  => $user_info['username'] ?? $body['user_id'],
			'access_token'  => $long_lived['access_token'] ?? $body['access_token'],
			'expires_in'    => $long_lived['expires_in'] ?? 0,
			'profile_pic'   => $user_info['profile_picture_url'] ?? '',
		);
	}

	/**
	 * Get Instagram long-lived access token.
	 *
	 * @param string $short_token Short-lived token.
	 * @param string $app_secret  App secret.
	 * @return array
	 */
	private function get_instagram_long_lived_token( $short_token, $app_secret ) {
		$response = wp_remote_get(
			add_query_arg(
				array(
					'grant_type'    => 'ig_exchange_token',
					'client_secret' => $app_secret,
					'access_token'  => $short_token,
				),
				'https://graph.instagram.com/access_token'
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'access_token' => $short_token, 'expires_in' => 3600 );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return array(
			'access_token' => $body['access_token'] ?? $short_token,
			'expires_in'   => $body['expires_in'] ?? 3600,
		);
	}

	/**
	 * Get Instagram user info.
	 *
	 * @param string $access_token Access token.
	 * @return array
	 */
	private function get_instagram_user_info( $access_token ) {
		$response = wp_remote_get(
			add_query_arg(
				array(
					'fields'       => 'id,username,account_type,media_count',
					'access_token' => $access_token,
				),
				'https://graph.instagram.com/me'
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		return json_decode( wp_remote_retrieve_body( $response ), true ) ?: array();
	}

	/**
	 * Exchange YouTube authorization code.
	 *
	 * @param string $code         Authorization code.
	 * @param array  $settings     Plugin settings.
	 * @param string $redirect_uri Redirect URI.
	 * @return array|WP_Error
	 */
	private function exchange_youtube_code( $code, $settings, $redirect_uri ) {
		$client_id     = isset( $settings['youtube_client_id'] ) ? $settings['youtube_client_id'] : '';
		$client_secret = isset( $settings['youtube_client_secret'] ) ? $settings['youtube_client_secret'] : '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error( 'missing_credentials', __( 'YouTube API credentials not configured.', 'social-feed' ) );
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body' => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'code'          => $code,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => $redirect_uri,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'api_error', $body['error_description'] ?? $body['error'] );
		}

		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from YouTube.', 'social-feed' ) );
		}

		$channel_info = $this->get_youtube_channel_info( $body['access_token'] );

		return array(
			'account_id'    => $channel_info['id'] ?? '',
			'account_name'  => $channel_info['title'] ?? '',
			'access_token'  => $body['access_token'],
			'refresh_token' => $body['refresh_token'] ?? '',
			'expires_in'    => $body['expires_in'] ?? 3600,
			'profile_pic'   => $channel_info['thumbnail'] ?? '',
		);
	}

	/**
	 * Get YouTube channel info.
	 *
	 * @param string $access_token Access token.
	 * @return array
	 */
	private function get_youtube_channel_info( $access_token ) {
		$response = wp_remote_get(
			add_query_arg(
				array(
					'part' => 'snippet',
					'mine' => 'true',
				),
				'https://www.googleapis.com/youtube/v3/channels'
			),
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['items'][0] ) ) {
			return array();
		}

		$channel = $body['items'][0];

		return array(
			'id'        => $channel['id'],
			'title'     => $channel['snippet']['title'],
			'thumbnail' => $channel['snippet']['thumbnails']['default']['url'] ?? '',
		);
	}

	/**
	 * Exchange Facebook authorization code.
	 *
	 * @param string $code         Authorization code.
	 * @param array  $settings     Plugin settings.
	 * @param string $redirect_uri Redirect URI.
	 * @return array|WP_Error
	 */
	private function exchange_facebook_code( $code, $settings, $redirect_uri ) {
		$app_id     = isset( $settings['facebook_app_id'] ) ? $settings['facebook_app_id'] : '';
		$app_secret = isset( $settings['facebook_app_secret'] ) ? $settings['facebook_app_secret'] : '';

		if ( empty( $app_id ) || empty( $app_secret ) ) {
			return new WP_Error( 'missing_credentials', __( 'Facebook API credentials not configured.', 'social-feed' ) );
		}

		$response = wp_remote_get(
			add_query_arg(
				array(
					'client_id'     => $app_id,
					'client_secret' => $app_secret,
					'redirect_uri'  => $redirect_uri,
					'code'          => $code,
				),
				'https://graph.facebook.com/v18.0/oauth/access_token'
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'api_error', $body['error']['message'] ?? 'Unknown error' );
		}

		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from Facebook.', 'social-feed' ) );
		}

		$page_info = $this->get_facebook_page_info( $body['access_token'] );

		if ( empty( $page_info ) ) {
			return new WP_Error( 'no_page', __( 'No Facebook Page found. Please ensure you have admin access to at least one Page.', 'social-feed' ) );
		}

		return array(
			'account_id'    => $page_info['id'],
			'account_name'  => $page_info['name'],
			'access_token'  => $page_info['access_token'],
			'expires_in'    => 0,
			'profile_pic'   => $page_info['picture'] ?? '',
		);
	}

	/**
	 * Get Facebook Page info.
	 *
	 * @param string $user_token User access token.
	 * @return array
	 */
	private function get_facebook_page_info( $user_token ) {
		$response = wp_remote_get(
			add_query_arg(
				array(
					'access_token' => $user_token,
					'fields'       => 'id,name,access_token,picture',
				),
				'https://graph.facebook.com/v18.0/me/accounts'
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['data'][0] ) ) {
			return array();
		}

		$page = $body['data'][0];

		return array(
			'id'           => $page['id'],
			'name'         => $page['name'],
			'access_token' => $page['access_token'],
			'picture'      => $page['picture']['data']['url'] ?? '',
		);
	}

	/**
	 * Send success response to parent window.
	 *
	 * @param string $platform Platform slug.
	 * @param array  $data     Token data.
	 */
	private function send_success( $platform, $data ) {
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<title><?php esc_html_e( 'Authorization Successful', 'social-feed' ); ?></title>
		</head>
		<body>
			<script>
				if (window.opener) {
					window.opener.postMessage({
						type: 'sf_oauth_success',
						platform: <?php echo wp_json_encode( $platform ); ?>,
						account_id: <?php echo wp_json_encode( $data['account_id'] ); ?>,
						account_name: <?php echo wp_json_encode( $data['account_name'] ); ?>,
						access_token: <?php echo wp_json_encode( $data['access_token'] ); ?>,
						refresh_token: <?php echo wp_json_encode( $data['refresh_token'] ?? '' ); ?>,
						expires_in: <?php echo wp_json_encode( $data['expires_in'] ?? 0 ); ?>,
						profile_pic: <?php echo wp_json_encode( $data['profile_pic'] ?? '' ); ?>
					}, '*');
					window.close();
				} else {
					document.body.innerHTML = '<p><?php esc_html_e( 'Authorization successful! You can close this window.', 'social-feed' ); ?></p>';
				}
			</script>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Send error response to parent window.
	 *
	 * @param string $message Error message.
	 */
	private function send_error( $message ) {
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<title><?php esc_html_e( 'Authorization Error', 'social-feed' ); ?></title>
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; padding: 40px; text-align: center; }
				.error { color: #d63638; max-width: 400px; margin: 0 auto; }
				h2 { color: #1d2327; }
				button { background: #2271b1; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-top: 20px; }
				button:hover { background: #135e96; }
			</style>
		</head>
		<body>
			<h2><?php esc_html_e( 'Authorization Error', 'social-feed' ); ?></h2>
			<p class="error"><?php echo esc_html( $message ); ?></p>
			<button onclick="window.close()"><?php esc_html_e( 'Close Window', 'social-feed' ); ?></button>
			<script>
				if (window.opener) {
					window.opener.postMessage({
						type: 'sf_oauth_error',
						message: <?php echo wp_json_encode( $message ); ?>
					}, '*');
				}
			</script>
		</body>
		</html>
		<?php
		exit;
	}
}
