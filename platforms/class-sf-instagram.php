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
 * Class SF_Instagram_Auth
 *
 * Handles Instagram OAuth authentication flow.
 */
class SF_Instagram_Auth {

	/**
	 * Instagram OAuth base URL.
	 */
	const OAUTH_URL = 'https://api.instagram.com/oauth/authorize';

	/**
	 * Instagram token URL.
	 */
	const TOKEN_URL = 'https://api.instagram.com/oauth/access_token';

	/**
	 * Graph API base URL.
	 */
	const GRAPH_URL = 'https://graph.instagram.com';

	/**
	 * API version.
	 */
	const API_VERSION = 'v18.0';

	/**
	 * Get Instagram App ID from settings.
	 *
	 * @return string
	 */
	private static function get_app_id() {
		$settings = get_option( 'sf_settings', array() );
		return isset( $settings['instagram_app_id'] ) ? $settings['instagram_app_id'] : '';
	}

	/**
	 * Get Instagram App Secret from settings.
	 *
	 * @return string
	 */
	private static function get_app_secret() {
		$settings = get_option( 'sf_settings', array() );
		return isset( $settings['instagram_app_secret'] ) ? $settings['instagram_app_secret'] : '';
	}

	/**
	 * Get OAuth redirect URI.
	 *
	 * @return string
	 */
	public static function get_redirect_uri() {
		return admin_url( 'admin-ajax.php?action=sf_instagram_callback' );
	}

	/**
	 * Get Instagram OAuth authorization URL.
	 *
	 * @return string|WP_Error OAuth URL or error if credentials missing.
	 */
	public static function get_auth_url() {
		$app_id = self::get_app_id();

		if ( empty( $app_id ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Instagram App ID is not configured. Please add it in Settings.', 'social-feed' )
			);
		}

		$state = wp_create_nonce( 'sf_instagram_oauth' );

		$params = array(
			'client_id'     => $app_id,
			'redirect_uri'  => self::get_redirect_uri(),
			'scope'         => 'user_profile,user_media',
			'response_type' => 'code',
			'state'         => $state,
		);

		return add_query_arg( $params, self::OAUTH_URL );
	}

	/**
	 * Exchange authorization code for short-lived access token.
	 *
	 * @param string $code Authorization code from OAuth callback.
	 * @return array|WP_Error Token data or error.
	 */
	public static function exchange_code_for_token( $code ) {
		$app_id     = self::get_app_id();
		$app_secret = self::get_app_secret();

		if ( empty( $app_id ) || empty( $app_secret ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Instagram API credentials are not configured.', 'social-feed' )
			);
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 15,
				'body'    => array(
					'client_id'     => $app_id,
					'client_secret' => $app_secret,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => self::get_redirect_uri(),
					'code'          => $code,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			SF_Helpers::sf_log_error(
				'Instagram token exchange failed: ' . $response->get_error_message(),
				'instagram'
			);
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code !== 200 ) {
			$error_msg = isset( $body['error_message'] ) ? $body['error_message'] : __( 'Failed to exchange code for token.', 'social-feed' );
			SF_Helpers::sf_log_error( 'Instagram token exchange error: ' . $error_msg, 'instagram' );
			return new WP_Error( 'token_exchange_failed', $error_msg );
		}

		if ( empty( $body['access_token'] ) || empty( $body['user_id'] ) ) {
			return new WP_Error(
				'invalid_response',
				__( 'Invalid response from Instagram. Missing access_token or user_id.', 'social-feed' )
			);
		}

		return array(
			'access_token' => $body['access_token'],
			'user_id'      => $body['user_id'],
		);
	}

	/**
	 * Exchange short-lived token for long-lived token (60 days).
	 *
	 * @param string $short_token Short-lived access token.
	 * @return array|WP_Error Long-lived token data or error.
	 */
	public static function get_long_lived_token( $short_token ) {
		$app_secret = self::get_app_secret();

		if ( empty( $app_secret ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Instagram App Secret is not configured.', 'social-feed' )
			);
		}

		$url = add_query_arg(
			array(
				'grant_type'    => 'ig_exchange_token',
				'client_secret' => $app_secret,
				'access_token'  => $short_token,
			),
			self::GRAPH_URL . '/access_token'
		);

		$response = wp_remote_get(
			$url,
			array( 'timeout' => 15 )
		);

		if ( is_wp_error( $response ) ) {
			SF_Helpers::sf_log_error(
				'Instagram long-lived token exchange failed: ' . $response->get_error_message(),
				'instagram'
			);
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code !== 200 ) {
			$error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Failed to get long-lived token.', 'social-feed' );
			SF_Helpers::sf_log_error( 'Instagram long-lived token error: ' . $error_msg, 'instagram' );
			return new WP_Error( 'long_lived_token_failed', $error_msg );
		}

		if ( empty( $body['access_token'] ) ) {
			return new WP_Error(
				'invalid_response',
				__( 'Invalid response from Instagram. Missing access_token.', 'social-feed' )
			);
		}

		return array(
			'access_token' => $body['access_token'],
			'token_type'   => $body['token_type'] ?? 'bearer',
			'expires_in'   => $body['expires_in'] ?? 5184000,
		);
	}

	/**
	 * Refresh a long-lived token before it expires.
	 *
	 * @param string $token Current long-lived access token.
	 * @return array|WP_Error New token data or error.
	 */
	public static function refresh_token( $token ) {
		$url = add_query_arg(
			array(
				'grant_type'   => 'ig_refresh_token',
				'access_token' => $token,
			),
			self::GRAPH_URL . '/refresh_access_token'
		);

		$response = wp_remote_get(
			$url,
			array( 'timeout' => 15 )
		);

		if ( is_wp_error( $response ) ) {
			SF_Helpers::sf_log_error(
				'Instagram token refresh failed: ' . $response->get_error_message(),
				'instagram'
			);
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code !== 200 ) {
			$error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Failed to refresh token.', 'social-feed' );
			SF_Helpers::sf_log_error( 'Instagram token refresh error: ' . $error_msg, 'instagram' );
			return new WP_Error( 'token_refresh_failed', $error_msg );
		}

		if ( empty( $body['access_token'] ) ) {
			return new WP_Error(
				'invalid_response',
				__( 'Invalid response from Instagram. Missing access_token.', 'social-feed' )
			);
		}

		SF_Helpers::sf_log_success( 'Instagram token refreshed successfully.', 'instagram' );

		return array(
			'access_token' => $body['access_token'],
			'token_type'   => $body['token_type'] ?? 'bearer',
			'expires_in'   => $body['expires_in'] ?? 5184000,
		);
	}

	/**
	 * Handle OAuth callback from Instagram.
	 *
	 * Processes the authorization code, exchanges for tokens,
	 * and saves the account to the database.
	 *
	 * @return array|WP_Error Account data or error.
	 */
	public static function handle_oauth_callback() {
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( $_GET['code'] ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( $_GET['state'] ) : '';
		$error = isset( $_GET['error'] ) ? sanitize_text_field( $_GET['error'] ) : '';

		if ( ! empty( $error ) ) {
			$error_desc = isset( $_GET['error_description'] ) ? sanitize_text_field( $_GET['error_description'] ) : $error;
			return new WP_Error( 'oauth_error', $error_desc );
		}

		if ( empty( $code ) ) {
			return new WP_Error( 'missing_code', __( 'Authorization code is missing.', 'social-feed' ) );
		}

		if ( empty( $state ) || ! wp_verify_nonce( $state, 'sf_instagram_oauth' ) ) {
			return new WP_Error( 'invalid_state', __( 'Invalid state parameter. Please try again.', 'social-feed' ) );
		}

		$short_token_data = self::exchange_code_for_token( $code );
		if ( is_wp_error( $short_token_data ) ) {
			return $short_token_data;
		}

		$long_token_data = self::get_long_lived_token( $short_token_data['access_token'] );
		if ( is_wp_error( $long_token_data ) ) {
			return $long_token_data;
		}

		$api     = new SF_Instagram_API( $long_token_data['access_token'] );
		$profile = $api->get_profile();

		if ( is_wp_error( $profile ) ) {
			return $profile;
		}

		$encrypted_token = SF_Helpers::sf_encrypt( $long_token_data['access_token'] );
		$expires_at      = gmdate( 'Y-m-d H:i:s', time() + $long_token_data['expires_in'] );

		$existing = SF_Database::get_account_by_external_id( 'instagram', $profile['id'] );

		$account_data = array(
			'platform'       => 'instagram',
			'account_name'   => $profile['username'],
			'account_id_ext' => $profile['id'],
			'access_token'   => $encrypted_token,
			'refresh_token'  => null,
			'token_expires'  => $expires_at,
			'profile_pic'    => $profile['profile_picture_url'] ?? '',
			'is_connected'   => 1,
			'last_error'     => null,
		);

		if ( $existing ) {
			SF_Database::update_account( $existing['id'], $account_data );
			$account_id = $existing['id'];
		} else {
			$account_id = SF_Database::create_account( $account_data );
		}

		if ( ! $account_id ) {
			return new WP_Error( 'db_error', __( 'Failed to save account to database.', 'social-feed' ) );
		}

		SF_Helpers::sf_log_success(
			sprintf( 'Instagram account @%s connected successfully.', $profile['username'] ),
			'instagram'
		);

		return array(
			'account_id'   => $account_id,
			'account_name' => $profile['username'],
			'profile'      => $profile,
			'expires_in'   => $long_token_data['expires_in'],
		);
	}
}

/**
 * Class SF_Instagram_API
 *
 * Handles Instagram Graph API requests.
 */
class SF_Instagram_API {

	/**
	 * Graph API base URL.
	 */
	const GRAPH_URL = 'https://graph.instagram.com';

	/**
	 * API version.
	 */
	const API_VERSION = 'v18.0';

	/**
	 * Access token.
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Constructor.
	 *
	 * @param string $access_token Instagram access token.
	 */
	public function __construct( $access_token ) {
		$this->access_token = $access_token;
	}

	/**
	 * Get user profile information.
	 *
	 * @return array|WP_Error Profile data or error.
	 */
	public function get_profile() {
		$fields = array(
			'id',
			'username',
			'account_type',
			'media_count',
		);

		$url = add_query_arg(
			array(
				'fields'       => implode( ',', $fields ),
				'access_token' => $this->access_token,
			),
			self::GRAPH_URL . '/me'
		);

		$response = $this->make_api_request( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Get user media with pagination.
	 *
	 * @param int         $limit        Number of items to fetch (max 100).
	 * @param string|null $after_cursor Pagination cursor for next page.
	 * @return array|WP_Error Media data with pagination info or error.
	 */
	public function get_media( $limit = 20, $after_cursor = null ) {
		$limit = min( absint( $limit ), 100 );

		$fields = array(
			'id',
			'caption',
			'media_type',
			'media_url',
			'thumbnail_url',
			'permalink',
			'timestamp',
			'like_count',
			'comments_count',
		);

		$params = array(
			'fields'       => implode( ',', $fields ),
			'limit'        => $limit,
			'access_token' => $this->access_token,
		);

		if ( ! empty( $after_cursor ) ) {
			$params['after'] = $after_cursor;
		}

		$url = add_query_arg( $params, self::GRAPH_URL . '/me/media' );

		$response = $this->make_api_request( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$items       = isset( $response['data'] ) ? $response['data'] : array();
		$next_cursor = null;

		if ( isset( $response['paging']['cursors']['after'] ) ) {
			$next_cursor = $response['paging']['cursors']['after'];
		}

		$normalized_items = array();
		foreach ( $items as $item ) {
			$normalized_items[] = $this->normalize_item( $item );
		}

		return array(
			'items'       => $normalized_items,
			'next_cursor' => $next_cursor,
			'has_more'    => ! empty( $next_cursor ),
		);
	}

	/**
	 * Get media by hashtag.
	 *
	 * Note: Hashtag search requires a Business/Creator account
	 * and the instagram_basic permission.
	 *
	 * @param string $hashtag Hashtag to search (without #).
	 * @param string $user_id Instagram user ID.
	 * @param int    $limit   Number of items to fetch.
	 * @return array|WP_Error Media data or error.
	 */
	public function get_hashtag_media( $hashtag, $user_id, $limit = 20 ) {
		$hashtag = sanitize_text_field( ltrim( $hashtag, '#' ) );
		$limit   = min( absint( $limit ), 50 );

		$search_url = add_query_arg(
			array(
				'user_id'      => $user_id,
				'q'            => $hashtag,
				'access_token' => $this->access_token,
			),
			self::GRAPH_URL . '/ig_hashtag_search'
		);

		$search_response = $this->make_api_request( $search_url );

		if ( is_wp_error( $search_response ) ) {
			return $search_response;
		}

		if ( empty( $search_response['data'][0]['id'] ) ) {
			return new WP_Error(
				'hashtag_not_found',
				sprintf(
					/* translators: %s: Hashtag */
					__( 'Hashtag #%s not found.', 'social-feed' ),
					$hashtag
				)
			);
		}

		$hashtag_id = $search_response['data'][0]['id'];

		$fields = array(
			'id',
			'caption',
			'media_type',
			'media_url',
			'permalink',
			'timestamp',
			'like_count',
			'comments_count',
		);

		$media_url = add_query_arg(
			array(
				'user_id'      => $user_id,
				'fields'       => implode( ',', $fields ),
				'limit'        => $limit,
				'access_token' => $this->access_token,
			),
			self::GRAPH_URL . '/' . $hashtag_id . '/top_media'
		);

		$media_response = $this->make_api_request( $media_url );

		if ( is_wp_error( $media_response ) ) {
			return $media_response;
		}

		$items            = isset( $media_response['data'] ) ? $media_response['data'] : array();
		$normalized_items = array();

		foreach ( $items as $item ) {
			$normalized_items[] = $this->normalize_item( $item );
		}

		return array(
			'items'   => $normalized_items,
			'hashtag' => $hashtag,
		);
	}

	/**
	 * Normalize a raw API item to standard format.
	 *
	 * @param array $raw_item Raw item from Instagram API.
	 * @return array Normalized item.
	 */
	public function normalize_item( $raw_item ) {
		$media_type = isset( $raw_item['media_type'] ) ? strtolower( $raw_item['media_type'] ) : 'image';

		$type_map = array(
			'image'          => 'image',
			'video'          => 'video',
			'carousel_album' => 'carousel',
		);

		$type = isset( $type_map[ $media_type ] ) ? $type_map[ $media_type ] : 'image';

		$thumbnail = '';
		if ( 'video' === $type && ! empty( $raw_item['thumbnail_url'] ) ) {
			$thumbnail = $raw_item['thumbnail_url'];
		} elseif ( ! empty( $raw_item['media_url'] ) ) {
			$thumbnail = $raw_item['media_url'];
		}

		return array(
			'id'        => $raw_item['id'] ?? '',
			'platform'  => 'instagram',
			'type'      => $type,
			'caption'   => $raw_item['caption'] ?? '',
			'media_url' => $raw_item['media_url'] ?? '',
			'thumbnail' => $thumbnail,
			'permalink' => $raw_item['permalink'] ?? '',
			'likes'     => isset( $raw_item['like_count'] ) ? absint( $raw_item['like_count'] ) : 0,
			'comments'  => isset( $raw_item['comments_count'] ) ? absint( $raw_item['comments_count'] ) : 0,
			'timestamp' => $raw_item['timestamp'] ?? '',
		);
	}

	/**
	 * Make an API request to Instagram Graph API.
	 *
	 * Handles error responses including:
	 * - 401: Token expired
	 * - 429: Rate limit exceeded
	 *
	 * @param string $url Full API URL with parameters.
	 * @return array|WP_Error Response data or error.
	 */
	public function make_api_request( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			SF_Helpers::sf_log_error(
				'Instagram API request failed: ' . $response->get_error_message(),
				'instagram'
			);
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $status_code ) {
			$error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Access token has expired.', 'social-feed' );
			SF_Helpers::sf_log_error( 'Instagram 401 Unauthorized: ' . $error_msg, 'instagram' );

			return new WP_Error(
				'token_expired',
				__( 'Instagram access token has expired. Please reconnect your account.', 'social-feed' )
			);
		}

		if ( 429 === $status_code ) {
			SF_Helpers::sf_log_warning( 'Instagram rate limit exceeded.', 'instagram' );

			return new WP_Error(
				'rate_limit',
				__( 'Instagram API rate limit exceeded. Please try again later.', 'social-feed' )
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Instagram API request failed.', 'social-feed' );
			$error_code = isset( $body['error']['code'] ) ? $body['error']['code'] : $status_code;

			SF_Helpers::sf_log_error(
				sprintf( 'Instagram API error %d: %s', $error_code, $error_msg ),
				'instagram'
			);

			return new WP_Error( 'api_error', $error_msg );
		}

		return $body;
	}
}

/**
 * Register Instagram OAuth callback AJAX handler.
 */
add_action( 'wp_ajax_sf_instagram_callback', 'sf_instagram_oauth_callback' );
add_action( 'wp_ajax_nopriv_sf_instagram_callback', 'sf_instagram_oauth_callback' );

/**
 * Handle Instagram OAuth callback.
 *
 * This is the endpoint that Instagram redirects to after authorization.
 * It processes the code, saves the account, and sends a message to the parent window.
 */
function sf_instagram_oauth_callback() {
	$result = SF_Instagram_Auth::handle_oauth_callback();

	if ( is_wp_error( $result ) ) {
		sf_instagram_oauth_error_response( $result->get_error_message() );
	}

	sf_instagram_oauth_success_response( $result );
}

/**
 * Output success response for OAuth popup.
 *
 * @param array $data Account data.
 */
function sf_instagram_oauth_success_response( $data ) {
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<title><?php esc_html_e( 'Instagram Connected', 'social-feed' ); ?></title>
		<style>
			body {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
				background: linear-gradient(135deg, #833ab4, #fd1d1d, #fcb045);
				min-height: 100vh;
				display: flex;
				align-items: center;
				justify-content: center;
				margin: 0;
				padding: 20px;
				box-sizing: border-box;
			}
			.success-card {
				background: #fff;
				border-radius: 16px;
				padding: 40px;
				text-align: center;
				box-shadow: 0 10px 40px rgba(0,0,0,0.2);
				max-width: 400px;
			}
			.success-icon {
				width: 80px;
				height: 80px;
				background: linear-gradient(135deg, #833ab4, #fd1d1d, #fcb045);
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				margin: 0 auto 20px;
			}
			.success-icon svg {
				width: 40px;
				height: 40px;
				fill: #fff;
			}
			h1 {
				color: #1d2327;
				font-size: 24px;
				margin: 0 0 10px;
			}
			.account-name {
				color: #833ab4;
				font-size: 18px;
				margin: 0 0 20px;
			}
			p {
				color: #646970;
				margin: 0;
				font-size: 14px;
			}
		</style>
	</head>
	<body>
		<div class="success-card">
			<div class="success-icon">
				<svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
			</div>
			<h1><?php esc_html_e( 'Successfully Connected!', 'social-feed' ); ?></h1>
			<p class="account-name">@<?php echo esc_html( $data['account_name'] ); ?></p>
			<p><?php esc_html_e( 'This window will close automatically...', 'social-feed' ); ?></p>
		</div>
		<script>
			if (window.opener) {
				window.opener.postMessage({
					type: 'sf_oauth_success',
					platform: 'instagram',
					account_id: <?php echo wp_json_encode( $data['profile']['id'] ?? '' ); ?>,
					account_name: <?php echo wp_json_encode( $data['account_name'] ); ?>,
					access_token: '',
					refresh_token: '',
					expires_in: <?php echo wp_json_encode( $data['expires_in'] ?? 0 ); ?>,
					profile_pic: <?php echo wp_json_encode( $data['profile']['profile_picture_url'] ?? '' ); ?>,
					already_saved: true
				}, '*');
				setTimeout(function() { window.close(); }, 2000);
			}
		</script>
	</body>
	</html>
	<?php
	exit;
}

/**
 * Output error response for OAuth popup.
 *
 * @param string $message Error message.
 */
function sf_instagram_oauth_error_response( $message ) {
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<title><?php esc_html_e( 'Connection Error', 'social-feed' ); ?></title>
		<style>
			body {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
				background: #f0f0f1;
				min-height: 100vh;
				display: flex;
				align-items: center;
				justify-content: center;
				margin: 0;
				padding: 20px;
				box-sizing: border-box;
			}
			.error-card {
				background: #fff;
				border-radius: 16px;
				padding: 40px;
				text-align: center;
				box-shadow: 0 4px 20px rgba(0,0,0,0.1);
				max-width: 400px;
				border-top: 4px solid #d63638;
			}
			.error-icon {
				width: 60px;
				height: 60px;
				background: #fce4e4;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				margin: 0 auto 20px;
			}
			.error-icon svg {
				width: 30px;
				height: 30px;
				fill: #d63638;
			}
			h1 {
				color: #1d2327;
				font-size: 20px;
				margin: 0 0 15px;
			}
			.error-message {
				color: #d63638;
				font-size: 14px;
				margin: 0 0 20px;
				padding: 15px;
				background: #fce4e4;
				border-radius: 8px;
			}
			button {
				background: #2271b1;
				color: #fff;
				border: none;
				padding: 12px 24px;
				border-radius: 6px;
				font-size: 14px;
				cursor: pointer;
				transition: background 0.2s;
			}
			button:hover {
				background: #135e96;
			}
		</style>
	</head>
	<body>
		<div class="error-card">
			<div class="error-icon">
				<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
			</div>
			<h1><?php esc_html_e( 'Connection Failed', 'social-feed' ); ?></h1>
			<p class="error-message"><?php echo esc_html( $message ); ?></p>
			<button onclick="window.close()"><?php esc_html_e( 'Close Window', 'social-feed' ); ?></button>
		</div>
		<script>
			if (window.opener) {
				window.opener.postMessage({
					type: 'sf_oauth_error',
					platform: 'instagram',
					message: <?php echo wp_json_encode( $message ); ?>
				}, '*');
			}
		</script>
	</body>
	</html>
	<?php
	exit;
}
