<?php
/**
 * YouTube Data API v3 integration for Social Feed plugin.
 *
 * @package SocialFeed
 *
 * YOUTUBE API QUOTA INFORMATION:
 * ==============================
 * YouTube Data API v3 has a daily quota limit of 10,000 units (free tier).
 *
 * Quota costs per operation:
 * - channels.list    = 1 unit
 * - videos.list      = 1 unit
 * - playlistItems.list = 1 unit
 * - search.list      = 100 units (USE SPARINGLY!)
 *
 * Best practices:
 * - Cache API responses aggressively (use SF_Helpers::sf_set_cache)
 * - Avoid search.list when possible - use playlistItems.list instead
 * - Batch video detail requests (up to 50 per call)
 * - Monitor quota usage in Google Cloud Console
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_YouTube_API
 *
 * Handles YouTube Data API v3 requests.
 */
class SF_YouTube_API {

	/**
	 * YouTube API base URL.
	 */
	const API_BASE = 'https://www.googleapis.com/youtube/v3';

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_key YouTube Data API key.
	 */
	public function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Get channel information.
	 *
	 * @param string $channel_input Channel ID (starts with UC) or username.
	 * @return array|WP_Error Normalized channel data or error.
	 */
	public function get_channel( $channel_input ) {
		$channel_input = trim( $channel_input );

		if ( strpos( $channel_input, 'UC' ) === 0 && strlen( $channel_input ) === 24 ) {
			$params = array( 'id' => $channel_input );
		} elseif ( strpos( $channel_input, '@' ) === 0 ) {
			$params = array( 'forHandle' => $channel_input );
		} else {
			$params = array( 'forUsername' => $channel_input );
		}

		$params['part'] = 'snippet,statistics,brandingSettings,contentDetails';

		$response = $this->make_request( 'channels', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['items'][0] ) ) {
			return new WP_Error(
				'channel_not_found',
				__( 'YouTube channel not found.', 'social-feed' )
			);
		}

		return $this->normalize_channel( $response['items'][0] );
	}

	/**
	 * Normalize channel data to standard format.
	 *
	 * @param array $raw_channel Raw channel data from API.
	 * @return array Normalized channel data.
	 */
	private function normalize_channel( $raw_channel ) {
		$snippet    = $raw_channel['snippet'] ?? array();
		$statistics = $raw_channel['statistics'] ?? array();
		$branding   = $raw_channel['brandingSettings']['image'] ?? array();
		$content    = $raw_channel['contentDetails']['relatedPlaylists'] ?? array();

		$thumbnail = '';
		if ( ! empty( $snippet['thumbnails'] ) ) {
			$thumbnail = $snippet['thumbnails']['high']['url']
				?? $snippet['thumbnails']['medium']['url']
				?? $snippet['thumbnails']['default']['url']
				?? '';
		}

		return array(
			'id'               => $raw_channel['id'],
			'name'             => $snippet['title'] ?? '',
			'description'      => $snippet['description'] ?? '',
			'custom_url'       => $snippet['customUrl'] ?? '',
			'thumbnail'        => $thumbnail,
			'subscribers'      => isset( $statistics['subscriberCount'] ) ? absint( $statistics['subscriberCount'] ) : 0,
			'video_count'      => isset( $statistics['videoCount'] ) ? absint( $statistics['videoCount'] ) : 0,
			'view_count'       => isset( $statistics['viewCount'] ) ? absint( $statistics['viewCount'] ) : 0,
			'banner_url'       => $branding['bannerExternalUrl'] ?? '',
			'uploads_playlist' => $content['uploads'] ?? '',
			'country'          => $snippet['country'] ?? '',
			'published_at'     => $snippet['publishedAt'] ?? '',
		);
	}

	/**
	 * Get videos from a channel.
	 *
	 * @param string      $channel_id  Channel ID.
	 * @param int         $limit       Number of videos to fetch (max 50).
	 * @param string|null $page_token  Pagination token for next page.
	 * @return array|WP_Error Video data with pagination or error.
	 */
	public function get_channel_videos( $channel_id, $limit = 20, $page_token = null ) {
		$channel = $this->get_channel( $channel_id );

		if ( is_wp_error( $channel ) ) {
			return $channel;
		}

		if ( empty( $channel['uploads_playlist'] ) ) {
			return new WP_Error(
				'no_uploads_playlist',
				__( 'Could not find uploads playlist for this channel.', 'social-feed' )
			);
		}

		return $this->get_playlist_videos( $channel['uploads_playlist'], $limit, $page_token );
	}

	/**
	 * Get videos from a playlist.
	 *
	 * @param string      $playlist_id Playlist ID.
	 * @param int         $limit       Number of videos to fetch (max 50).
	 * @param string|null $page_token  Pagination token for next page.
	 * @return array|WP_Error Video data with pagination or error.
	 */
	public function get_playlist_videos( $playlist_id, $limit = 20, $page_token = null ) {
		$limit = min( absint( $limit ), 50 );

		$params = array(
			'part'       => 'snippet,contentDetails',
			'playlistId' => $playlist_id,
			'maxResults' => $limit,
		);

		if ( ! empty( $page_token ) ) {
			$params['pageToken'] = $page_token;
		}

		$response = $this->make_request( 'playlistItems', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['items'] ) ) {
			return array(
				'items'           => array(),
				'next_page_token' => null,
				'total_results'   => 0,
			);
		}

		$video_ids = array();
		foreach ( $response['items'] as $item ) {
			if ( ! empty( $item['contentDetails']['videoId'] ) ) {
				$video_ids[] = $item['contentDetails']['videoId'];
			}
		}

		if ( empty( $video_ids ) ) {
			return array(
				'items'           => array(),
				'next_page_token' => $response['nextPageToken'] ?? null,
				'total_results'   => $response['pageInfo']['totalResults'] ?? 0,
			);
		}

		$video_details = $this->get_video_details( $video_ids );

		if ( is_wp_error( $video_details ) ) {
			return $video_details;
		}

		$normalized_items = array();
		foreach ( $video_ids as $video_id ) {
			if ( isset( $video_details[ $video_id ] ) ) {
				$normalized_items[] = $this->normalize_video( $video_details[ $video_id ] );
			}
		}

		return array(
			'items'           => $normalized_items,
			'next_page_token' => $response['nextPageToken'] ?? null,
			'total_results'   => $response['pageInfo']['totalResults'] ?? 0,
		);
	}

	/**
	 * Get details for multiple videos in one API call.
	 *
	 * @param array $video_ids Array of video IDs (max 50).
	 * @return array|WP_Error Video details indexed by ID or error.
	 */
	public function get_video_details( $video_ids ) {
		if ( empty( $video_ids ) ) {
			return array();
		}

		$video_ids = array_slice( (array) $video_ids, 0, 50 );

		$params = array(
			'part' => 'snippet,statistics,contentDetails,status',
			'id'   => implode( ',', $video_ids ),
		);

		$response = $this->make_request( 'videos', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$videos = array();
		if ( ! empty( $response['items'] ) ) {
			foreach ( $response['items'] as $video ) {
				$videos[ $video['id'] ] = $video;
			}
		}

		return $videos;
	}

	/**
	 * Search for videos.
	 *
	 * WARNING: search.list costs 100 quota units per call!
	 * Use sparingly and cache results aggressively.
	 *
	 * @param string      $query      Search query.
	 * @param string|null $channel_id Optional channel ID to filter results.
	 * @param int         $limit      Number of results (max 50).
	 * @return array|WP_Error Search results or error.
	 */
	public function search_videos( $query, $channel_id = null, $limit = 10 ) {
		$limit = min( absint( $limit ), 50 );

		$params = array(
			'part'       => 'snippet',
			'q'          => $query,
			'type'       => 'video',
			'maxResults' => $limit,
			'order'      => 'relevance',
		);

		if ( ! empty( $channel_id ) ) {
			$params['channelId'] = $channel_id;
		}

		$response = $this->make_request( 'search', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['items'] ) ) {
			return array(
				'items'         => array(),
				'total_results' => 0,
			);
		}

		$video_ids = array();
		foreach ( $response['items'] as $item ) {
			if ( ! empty( $item['id']['videoId'] ) ) {
				$video_ids[] = $item['id']['videoId'];
			}
		}

		if ( empty( $video_ids ) ) {
			return array(
				'items'         => array(),
				'total_results' => $response['pageInfo']['totalResults'] ?? 0,
			);
		}

		$video_details = $this->get_video_details( $video_ids );

		if ( is_wp_error( $video_details ) ) {
			return $video_details;
		}

		$normalized_items = array();
		foreach ( $video_ids as $video_id ) {
			if ( isset( $video_details[ $video_id ] ) ) {
				$normalized_items[] = $this->normalize_video( $video_details[ $video_id ] );
			}
		}

		return array(
			'items'         => $normalized_items,
			'total_results' => $response['pageInfo']['totalResults'] ?? 0,
		);
	}

	/**
	 * Normalize video data to standard format.
	 *
	 * @param array $raw_video Raw video data from API.
	 * @return array Normalized video data.
	 */
	public function normalize_video( $raw_video ) {
		$snippet    = $raw_video['snippet'] ?? array();
		$statistics = $raw_video['statistics'] ?? array();
		$content    = $raw_video['contentDetails'] ?? array();
		$status     = $raw_video['status'] ?? array();

		$video_id = $raw_video['id'];

		$thumbnail = '';
		if ( ! empty( $snippet['thumbnails'] ) ) {
			$thumbnail = $snippet['thumbnails']['maxres']['url']
				?? $snippet['thumbnails']['high']['url']
				?? $snippet['thumbnails']['medium']['url']
				?? $snippet['thumbnails']['default']['url']
				?? '';
		}

		$duration_data = $this->parse_iso_duration( $content['duration'] ?? 'PT0S' );

		return array(
			'id'                 => $video_id,
			'platform'           => 'youtube',
			'type'               => 'video',
			'title'              => $snippet['title'] ?? '',
			'description'        => $snippet['description'] ?? '',
			'thumbnail'          => $thumbnail,
			'permalink'          => 'https://www.youtube.com/watch?v=' . $video_id,
			'embed_url'          => 'https://www.youtube.com/embed/' . $video_id,
			'views'              => isset( $statistics['viewCount'] ) ? absint( $statistics['viewCount'] ) : 0,
			'likes'              => isset( $statistics['likeCount'] ) ? absint( $statistics['likeCount'] ) : 0,
			'comments'           => isset( $statistics['commentCount'] ) ? absint( $statistics['commentCount'] ) : 0,
			'duration_seconds'   => $duration_data['seconds'],
			'duration_formatted' => $duration_data['formatted'],
			'timestamp'          => $snippet['publishedAt'] ?? '',
			'channel_id'         => $snippet['channelId'] ?? '',
			'channel_name'       => $snippet['channelTitle'] ?? '',
			'tags'               => $snippet['tags'] ?? array(),
			'category_id'        => $snippet['categoryId'] ?? '',
			'privacy_status'     => $status['privacyStatus'] ?? 'public',
			'embeddable'         => $status['embeddable'] ?? true,
		);
	}

	/**
	 * Parse ISO 8601 duration to seconds and formatted string.
	 *
	 * @param string $duration ISO 8601 duration (e.g., PT1H30M15S).
	 * @return array Array with 'seconds' and 'formatted' keys.
	 */
	public function parse_iso_duration( $duration ) {
		$seconds = 0;
		$hours   = 0;
		$minutes = 0;
		$secs    = 0;

		if ( preg_match( '/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches ) ) {
			$hours   = isset( $matches[1] ) ? absint( $matches[1] ) : 0;
			$minutes = isset( $matches[2] ) ? absint( $matches[2] ) : 0;
			$secs    = isset( $matches[3] ) ? absint( $matches[3] ) : 0;

			$seconds = ( $hours * 3600 ) + ( $minutes * 60 ) + $secs;
		}

		if ( $hours > 0 ) {
			$formatted = sprintf( '%d:%02d:%02d', $hours, $minutes, $secs );
		} else {
			$formatted = sprintf( '%d:%02d', $minutes, $secs );
		}

		return array(
			'seconds'   => $seconds,
			'formatted' => $formatted,
		);
	}

	/**
	 * Make a request to the YouTube Data API.
	 *
	 * @param string $endpoint API endpoint (e.g., 'videos', 'channels').
	 * @param array  $params   Query parameters.
	 * @return array|WP_Error Decoded JSON response or error.
	 */
	public function make_request( $endpoint, $params = array() ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'missing_api_key',
				__( 'YouTube API key is not configured.', 'social-feed' )
			);
		}

		$params['key'] = $this->api_key;

		$url = add_query_arg( $params, self::API_BASE . '/' . $endpoint );

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
				'YouTube API request failed: ' . $response->get_error_message(),
				'youtube'
			);
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 403 === $status_code ) {
			$error_reason = $body['error']['errors'][0]['reason'] ?? '';

			if ( 'quotaExceeded' === $error_reason ) {
				SF_Helpers::sf_log_error(
					'YouTube API quota exceeded. Daily limit of 10,000 units reached.',
					'youtube'
				);
				return new WP_Error(
					'quota_exceeded',
					__( 'YouTube API daily quota exceeded. Please try again tomorrow or upgrade your API quota.', 'social-feed' )
				);
			}

			$error_msg = $body['error']['message'] ?? __( 'Access forbidden.', 'social-feed' );
			SF_Helpers::sf_log_error( 'YouTube API 403: ' . $error_msg, 'youtube' );
			return new WP_Error( 'forbidden', $error_msg );
		}

		if ( 400 === $status_code ) {
			$error_reason = $body['error']['errors'][0]['reason'] ?? '';

			if ( 'keyInvalid' === $error_reason || 'badRequest' === $error_reason ) {
				SF_Helpers::sf_log_error( 'YouTube API key is invalid.', 'youtube' );
				return new WP_Error(
					'invalid_api_key',
					__( 'YouTube API key is invalid. Please check your API key in Settings.', 'social-feed' )
				);
			}

			$error_msg = $body['error']['message'] ?? __( 'Bad request.', 'social-feed' );
			SF_Helpers::sf_log_error( 'YouTube API 400: ' . $error_msg, 'youtube' );
			return new WP_Error( 'bad_request', $error_msg );
		}

		if ( 401 === $status_code ) {
			SF_Helpers::sf_log_error( 'YouTube API unauthorized. API key may be invalid.', 'youtube' );
			return new WP_Error(
				'unauthorized',
				__( 'YouTube API authorization failed. Please check your API key.', 'social-feed' )
			);
		}

		if ( 404 === $status_code ) {
			$error_msg = $body['error']['message'] ?? __( 'Resource not found.', 'social-feed' );
			SF_Helpers::sf_log_warning( 'YouTube API 404: ' . $error_msg, 'youtube' );
			return new WP_Error( 'not_found', $error_msg );
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_msg = $body['error']['message'] ?? __( 'YouTube API request failed.', 'social-feed' );
			SF_Helpers::sf_log_error(
				sprintf( 'YouTube API error %d: %s', $status_code, $error_msg ),
				'youtube'
			);
			return new WP_Error( 'api_error', $error_msg );
		}

		return $body;
	}

	/**
	 * Get API key from plugin settings.
	 *
	 * @return string
	 */
	public static function get_api_key_from_settings() {
		$settings = get_option( 'sf_settings', array() );
		return isset( $settings['youtube_api_key'] ) ? $settings['youtube_api_key'] : '';
	}

	/**
	 * Create instance with API key from settings.
	 *
	 * @return SF_YouTube_API|WP_Error
	 */
	public static function create_from_settings() {
		$api_key = self::get_api_key_from_settings();

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'missing_api_key',
				__( 'YouTube API key is not configured. Please add it in Settings.', 'social-feed' )
			);
		}

		return new self( $api_key );
	}
}

/**
 * Class SF_YouTube_Auth
 *
 * Handles YouTube OAuth authentication for user channels.
 * Note: For public data, API key is sufficient. OAuth is only needed
 * for accessing private data or user's own channel management.
 */
class SF_YouTube_Auth {

	/**
	 * Google OAuth URL.
	 */
	const OAUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

	/**
	 * Google token URL.
	 */
	const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	/**
	 * Get OAuth redirect URI.
	 *
	 * @return string
	 */
	public static function get_redirect_uri() {
		return admin_url( 'admin-ajax.php?action=sf_youtube_callback' );
	}

	/**
	 * Get YouTube OAuth authorization URL.
	 *
	 * @return string|WP_Error OAuth URL or error.
	 */
	public static function get_auth_url() {
		$settings  = get_option( 'sf_settings', array() );
		$client_id = isset( $settings['youtube_client_id'] ) ? $settings['youtube_client_id'] : '';

		if ( empty( $client_id ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'YouTube OAuth Client ID is not configured. Please add it in Settings.', 'social-feed' )
			);
		}

		$state = wp_create_nonce( 'sf_youtube_oauth' );

		$params = array(
			'client_id'     => $client_id,
			'redirect_uri'  => self::get_redirect_uri(),
			'scope'         => 'https://www.googleapis.com/auth/youtube.readonly',
			'response_type' => 'code',
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		);

		return add_query_arg( $params, self::OAUTH_URL );
	}

	/**
	 * Exchange authorization code for access token.
	 *
	 * @param string $code Authorization code.
	 * @return array|WP_Error Token data or error.
	 */
	public static function exchange_code_for_token( $code ) {
		$settings      = get_option( 'sf_settings', array() );
		$client_id     = isset( $settings['youtube_client_id'] ) ? $settings['youtube_client_id'] : '';
		$client_secret = isset( $settings['youtube_client_secret'] ) ? $settings['youtube_client_secret'] : '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'YouTube OAuth credentials are not configured.', 'social-feed' )
			);
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 15,
				'body'    => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'code'          => $code,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => self::get_redirect_uri(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			SF_Helpers::sf_log_error(
				'YouTube token exchange failed: ' . $response->get_error_message(),
				'youtube'
			);
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			$error_msg = $body['error_description'] ?? $body['error'];
			SF_Helpers::sf_log_error( 'YouTube token exchange error: ' . $error_msg, 'youtube' );
			return new WP_Error( 'token_error', $error_msg );
		}

		if ( empty( $body['access_token'] ) ) {
			return new WP_Error(
				'invalid_response',
				__( 'Invalid response from YouTube.', 'social-feed' )
			);
		}

		return array(
			'access_token'  => $body['access_token'],
			'refresh_token' => $body['refresh_token'] ?? '',
			'expires_in'    => $body['expires_in'] ?? 3600,
			'token_type'    => $body['token_type'] ?? 'Bearer',
		);
	}

	/**
	 * Refresh an access token.
	 *
	 * @param string $refresh_token Refresh token.
	 * @return array|WP_Error New token data or error.
	 */
	public static function refresh_token( $refresh_token ) {
		$settings      = get_option( 'sf_settings', array() );
		$client_id     = isset( $settings['youtube_client_id'] ) ? $settings['youtube_client_id'] : '';
		$client_secret = isset( $settings['youtube_client_secret'] ) ? $settings['youtube_client_secret'] : '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'YouTube OAuth credentials are not configured.', 'social-feed' )
			);
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 15,
				'body'    => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			SF_Helpers::sf_log_error(
				'YouTube token refresh failed: ' . $response->get_error_message(),
				'youtube'
			);
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			$error_msg = $body['error_description'] ?? $body['error'];
			SF_Helpers::sf_log_error( 'YouTube token refresh error: ' . $error_msg, 'youtube' );
			return new WP_Error( 'token_error', $error_msg );
		}

		SF_Helpers::sf_log_success( 'YouTube token refreshed successfully.', 'youtube' );

		return array(
			'access_token' => $body['access_token'],
			'expires_in'   => $body['expires_in'] ?? 3600,
			'token_type'   => $body['token_type'] ?? 'Bearer',
		);
	}

	/**
	 * Get channel info using OAuth token.
	 *
	 * @param string $access_token OAuth access token.
	 * @return array|WP_Error Channel data or error.
	 */
	public static function get_authenticated_channel( $access_token ) {
		$url = add_query_arg(
			array(
				'part' => 'snippet,statistics,contentDetails',
				'mine' => 'true',
			),
			'https://www.googleapis.com/youtube/v3/channels'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['items'][0] ) ) {
			return new WP_Error(
				'no_channel',
				__( 'No YouTube channel found for this account.', 'social-feed' )
			);
		}

		$channel  = $body['items'][0];
		$snippet  = $channel['snippet'] ?? array();

		$thumbnail = '';
		if ( ! empty( $snippet['thumbnails'] ) ) {
			$thumbnail = $snippet['thumbnails']['high']['url']
				?? $snippet['thumbnails']['default']['url']
				?? '';
		}

		return array(
			'id'        => $channel['id'],
			'name'      => $snippet['title'] ?? '',
			'thumbnail' => $thumbnail,
		);
	}

	/**
	 * Handle OAuth callback.
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

		if ( empty( $state ) || ! wp_verify_nonce( $state, 'sf_youtube_oauth' ) ) {
			return new WP_Error( 'invalid_state', __( 'Invalid state parameter.', 'social-feed' ) );
		}

		$token_data = self::exchange_code_for_token( $code );
		if ( is_wp_error( $token_data ) ) {
			return $token_data;
		}

		$channel = self::get_authenticated_channel( $token_data['access_token'] );
		if ( is_wp_error( $channel ) ) {
			return $channel;
		}

		$encrypted_access  = SF_Helpers::sf_encrypt( $token_data['access_token'] );
		$encrypted_refresh = ! empty( $token_data['refresh_token'] ) ? SF_Helpers::sf_encrypt( $token_data['refresh_token'] ) : null;
		$expires_at        = gmdate( 'Y-m-d H:i:s', time() + $token_data['expires_in'] );

		$existing = SF_Database::get_account_by_external_id( 'youtube', $channel['id'] );

		$account_data = array(
			'platform'       => 'youtube',
			'account_name'   => $channel['name'],
			'account_id_ext' => $channel['id'],
			'access_token'   => $encrypted_access,
			'refresh_token'  => $encrypted_refresh,
			'token_expires'  => $expires_at,
			'profile_pic'    => $channel['thumbnail'],
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
			return new WP_Error( 'db_error', __( 'Failed to save account.', 'social-feed' ) );
		}

		SF_Helpers::sf_log_success(
			sprintf( 'YouTube channel %s connected.', $channel['name'] ),
			'youtube'
		);

		return array(
			'account_id'   => $account_id,
			'account_name' => $channel['name'],
			'channel'      => $channel,
			'expires_in'   => $token_data['expires_in'],
		);
	}
}

/**
 * Register YouTube OAuth callback AJAX handler.
 */
add_action( 'wp_ajax_sf_youtube_callback', 'sf_youtube_oauth_callback' );
add_action( 'wp_ajax_nopriv_sf_youtube_callback', 'sf_youtube_oauth_callback' );

/**
 * Handle YouTube OAuth callback.
 */
function sf_youtube_oauth_callback() {
	$result = SF_YouTube_Auth::handle_oauth_callback();

	if ( is_wp_error( $result ) ) {
		sf_youtube_oauth_error_response( $result->get_error_message() );
	}

	sf_youtube_oauth_success_response( $result );
}

/**
 * Output success response for OAuth popup.
 *
 * @param array $data Account data.
 */
function sf_youtube_oauth_success_response( $data ) {
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<title><?php esc_html_e( 'YouTube Connected', 'social-feed' ); ?></title>
		<style>
			body {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
				background: #FF0000;
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
				box-shadow: 0 10px 40px rgba(0,0,0,0.3);
				max-width: 400px;
			}
			.success-icon {
				width: 80px;
				height: 80px;
				background: #FF0000;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				margin: 0 auto 20px;
			}
			.success-icon svg { width: 40px; height: 40px; fill: #fff; }
			h1 { color: #1d2327; font-size: 24px; margin: 0 0 10px; }
			.account-name { color: #FF0000; font-size: 18px; margin: 0 0 20px; }
			p { color: #646970; margin: 0; font-size: 14px; }
		</style>
	</head>
	<body>
		<div class="success-card">
			<div class="success-icon">
				<svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
			</div>
			<h1><?php esc_html_e( 'Successfully Connected!', 'social-feed' ); ?></h1>
			<p class="account-name"><?php echo esc_html( $data['account_name'] ); ?></p>
			<p><?php esc_html_e( 'This window will close automatically...', 'social-feed' ); ?></p>
		</div>
		<script>
			if (window.opener) {
				window.opener.postMessage({
					type: 'sf_oauth_success',
					platform: 'youtube',
					account_id: <?php echo wp_json_encode( $data['channel']['id'] ?? '' ); ?>,
					account_name: <?php echo wp_json_encode( $data['account_name'] ); ?>,
					access_token: '',
					refresh_token: '',
					expires_in: <?php echo wp_json_encode( $data['expires_in'] ?? 0 ); ?>,
					profile_pic: <?php echo wp_json_encode( $data['channel']['thumbnail'] ?? '' ); ?>,
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
function sf_youtube_oauth_error_response( $message ) {
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<title><?php esc_html_e( 'Connection Error', 'social-feed' ); ?></title>
		<style>
			body {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
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
			.error-icon svg { width: 30px; height: 30px; fill: #d63638; }
			h1 { color: #1d2327; font-size: 20px; margin: 0 0 15px; }
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
				cursor: pointer;
			}
			button:hover { background: #135e96; }
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
					platform: 'youtube',
					message: <?php echo wp_json_encode( $message ); ?>
				}, '*');
			}
		</script>
	</body>
	</html>
	<?php
	exit;
}
