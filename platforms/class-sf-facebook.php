<?php
/**
 * Facebook Graph API integration for Social Feed plugin.
 *
 * @package SocialFeed
 *
 * FACEBOOK API INFORMATION:
 * =========================
 * Graph API Version: v18.0
 * Base URL: https://graph.facebook.com/v18.0/
 *
 * Required Permissions:
 * - pages_read_engagement: Read page engagement metrics
 * - pages_read_user_content: Read user-generated content on pages
 * - pages_show_list: Show list of pages user manages
 * - public_profile: Basic profile information
 *
 * Note: Facebook Page tokens obtained via OAuth do not expire.
 * User tokens expire in ~60 days but can be exchanged for long-lived tokens.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Facebook_Auth
 *
 * Handles Facebook OAuth authentication flow.
 */
class SF_Facebook_Auth {

	/**
	 * Facebook OAuth dialog URL.
	 */
	const OAUTH_URL = 'https://www.facebook.com/v18.0/dialog/oauth';

	/**
	 * Facebook Graph API base URL.
	 */
	const GRAPH_URL = 'https://graph.facebook.com/v18.0';

	/**
	 * Required OAuth scopes.
	 */
	const SCOPES = 'pages_read_engagement,pages_read_user_content,pages_show_list,public_profile';

	/**
	 * App ID.
	 *
	 * @var string
	 */
	private $app_id;

	/**
	 * App Secret.
	 *
	 * @var string
	 */
	private $app_secret;

	/**
	 * OAuth redirect URI.
	 *
	 * @var string
	 */
	private $redirect_uri;

	/**
	 * Constructor.
	 *
	 * @param string|null $app_id     Facebook App ID.
	 * @param string|null $app_secret Facebook App Secret.
	 */
	public function __construct( $app_id = null, $app_secret = null ) {
		$settings = get_option( 'sf_settings', array() );

		$this->app_id       = $app_id ?? ( $settings['facebook_app_id'] ?? '' );
		$this->app_secret   = $app_secret ?? ( $settings['facebook_app_secret'] ?? '' );
		$this->redirect_uri = admin_url( 'admin-ajax.php?action=sf_facebook_callback' );
	}

	/**
	 * Get OAuth redirect URI.
	 *
	 * @return string
	 */
	public static function get_redirect_uri() {
		return admin_url( 'admin-ajax.php?action=sf_facebook_callback' );
	}

	/**
	 * Get Facebook OAuth authorization URL.
	 *
	 * @return string|WP_Error OAuth URL or error.
	 */
	public static function get_auth_url() {
		$settings = get_option( 'sf_settings', array() );
		$app_id   = isset( $settings['facebook_app_id'] ) ? $settings['facebook_app_id'] : '';

		if ( empty( $app_id ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Facebook App ID is not configured. Please add it in Settings.', 'social-feed' )
			);
		}

		$state = wp_create_nonce( 'sf_facebook_oauth' );

		$params = array(
			'client_id'     => $app_id,
			'redirect_uri'  => self::get_redirect_uri(),
			'scope'         => self::SCOPES,
			'response_type' => 'code',
			'state'         => $state,
		);

		return add_query_arg( $params, self::OAUTH_URL );
	}

	/**
	 * Exchange authorization code for short-lived user token.
	 *
	 * @param string $code Authorization code from OAuth callback.
	 * @return array|WP_Error Token data or error.
	 */
	public function exchange_code( $code ) {
		if ( empty( $this->app_id ) || empty( $this->app_secret ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Facebook API credentials are not configured.', 'social-feed' )
			);
		}

		$url = add_query_arg(
			array(
				'client_id'     => $this->app_id,
				'client_secret' => $this->app_secret,
				'redirect_uri'  => $this->redirect_uri,
				'code'          => $code,
			),
			self::GRAPH_URL . '/oauth/access_token'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			SF_Helpers::sf_log_error(
				'Facebook token exchange failed: ' . $response->get_error_message(),
				'facebook'
			);
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			$error_msg = $body['error']['message'] ?? __( 'Failed to exchange code.', 'social-feed' );
			SF_Helpers::sf_log_error( 'Facebook token error: ' . $error_msg, 'facebook' );
			return new WP_Error( 'token_error', $error_msg );
		}

		if ( empty( $body['access_token'] ) ) {
			return new WP_Error(
				'invalid_response',
				__( 'Invalid response from Facebook.', 'social-feed' )
			);
		}

		return array(
			'access_token' => $body['access_token'],
			'token_type'   => $body['token_type'] ?? 'bearer',
			'expires_in'   => $body['expires_in'] ?? 3600,
		);
	}

	/**
	 * Exchange short-lived token for long-lived user token (~60 days).
	 *
	 * @param string $short_token Short-lived access token.
	 * @return array|WP_Error Long-lived token data or error.
	 */
	public function get_long_lived_user_token( $short_token ) {
		if ( empty( $this->app_id ) || empty( $this->app_secret ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Facebook API credentials are not configured.', 'social-feed' )
			);
		}

		$url = add_query_arg(
			array(
				'grant_type'        => 'fb_exchange_token',
				'client_id'         => $this->app_id,
				'client_secret'     => $this->app_secret,
				'fb_exchange_token' => $short_token,
			),
			self::GRAPH_URL . '/oauth/access_token'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			SF_Helpers::sf_log_error(
				'Facebook long-lived token exchange failed: ' . $response->get_error_message(),
				'facebook'
			);
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			$error_msg = $body['error']['message'] ?? __( 'Failed to get long-lived token.', 'social-feed' );
			SF_Helpers::sf_log_error( 'Facebook long-lived token error: ' . $error_msg, 'facebook' );
			return new WP_Error( 'token_error', $error_msg );
		}

		if ( empty( $body['access_token'] ) ) {
			return new WP_Error(
				'invalid_response',
				__( 'Invalid response from Facebook.', 'social-feed' )
			);
		}

		return array(
			'access_token' => $body['access_token'],
			'token_type'   => $body['token_type'] ?? 'bearer',
			'expires_in'   => $body['expires_in'] ?? 5184000,
		);
	}

	/**
	 * Get page tokens for all pages the user manages.
	 *
	 * Note: Page tokens obtained this way do not expire.
	 *
	 * @param string $user_token Long-lived user access token.
	 * @return array|WP_Error Array of pages with tokens or error.
	 */
	public function get_page_tokens( $user_token ) {
		$url = add_query_arg(
			array(
				'fields'       => 'id,name,access_token,picture,fan_count,category',
				'access_token' => $user_token,
			),
			self::GRAPH_URL . '/me/accounts'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			SF_Helpers::sf_log_error(
				'Facebook get pages failed: ' . $response->get_error_message(),
				'facebook'
			);
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			$error_msg = $body['error']['message'] ?? __( 'Failed to get pages.', 'social-feed' );
			SF_Helpers::sf_log_error( 'Facebook pages error: ' . $error_msg, 'facebook' );
			return new WP_Error( 'pages_error', $error_msg );
		}

		if ( empty( $body['data'] ) ) {
			return new WP_Error(
				'no_pages',
				__( 'No Facebook Pages found. You must be an admin of at least one Facebook Page.', 'social-feed' )
			);
		}

		$pages = array();
		foreach ( $body['data'] as $page ) {
			$picture_url = '';
			if ( ! empty( $page['picture']['data']['url'] ) ) {
				$picture_url = $page['picture']['data']['url'];
			}

			$pages[] = array(
				'id'           => $page['id'],
				'name'         => $page['name'],
				'access_token' => $page['access_token'],
				'picture'      => $picture_url,
				'fan_count'    => $page['fan_count'] ?? 0,
				'category'     => $page['category'] ?? '',
			);
		}

		return $pages;
	}

	/**
	 * Handle OAuth callback.
	 *
	 * Processes OAuth, gets pages list, saves each page as separate account.
	 *
	 * @return array|WP_Error Result data or error.
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

		if ( empty( $state ) || ! wp_verify_nonce( $state, 'sf_facebook_oauth' ) ) {
			return new WP_Error( 'invalid_state', __( 'Invalid state parameter.', 'social-feed' ) );
		}

		$auth = new self();

		$short_token = $auth->exchange_code( $code );
		if ( is_wp_error( $short_token ) ) {
			return $short_token;
		}

		$long_token = $auth->get_long_lived_user_token( $short_token['access_token'] );
		if ( is_wp_error( $long_token ) ) {
			return $long_token;
		}

		$pages = $auth->get_page_tokens( $long_token['access_token'] );
		if ( is_wp_error( $pages ) ) {
			return $pages;
		}

		$saved_pages = array();
		foreach ( $pages as $page ) {
			$encrypted_token = SF_Helpers::sf_encrypt( $page['access_token'] );

			$existing = SF_Database::get_account_by_external_id( 'facebook', $page['id'] );

			$account_data = array(
				'platform'       => 'facebook',
				'account_name'   => $page['name'],
				'account_id_ext' => $page['id'],
				'access_token'   => $encrypted_token,
				'refresh_token'  => null,
				'token_expires'  => null,
				'profile_pic'    => $page['picture'],
				'is_connected'   => 1,
				'last_error'     => null,
			);

			if ( $existing ) {
				SF_Database::update_account( $existing['id'], $account_data );
				$account_id = $existing['id'];
			} else {
				$account_id = SF_Database::create_account( $account_data );
			}

			if ( $account_id ) {
				$saved_pages[] = array(
					'account_id' => $account_id,
					'page_id'    => $page['id'],
					'name'       => $page['name'],
					'picture'    => $page['picture'],
				);
			}
		}

		if ( empty( $saved_pages ) ) {
			return new WP_Error( 'save_failed', __( 'Failed to save any pages.', 'social-feed' ) );
		}

		SF_Helpers::sf_log_success(
			sprintf( '%d Facebook Page(s) connected successfully.', count( $saved_pages ) ),
			'facebook'
		);

		return array(
			'pages'        => $saved_pages,
			'total_pages'  => count( $saved_pages ),
			'account_name' => $saved_pages[0]['name'],
		);
	}
}

/**
 * Class SF_Facebook_API
 *
 * Handles Facebook Graph API requests for a specific page.
 */
class SF_Facebook_API {

	/**
	 * Facebook Graph API base URL.
	 */
	const GRAPH_URL = 'https://graph.facebook.com/v18.0';

	/**
	 * Page ID.
	 *
	 * @var string
	 */
	private $page_id;

	/**
	 * Page access token.
	 *
	 * @var string
	 */
	private $page_token;

	/**
	 * Constructor.
	 *
	 * @param string $page_id    Facebook Page ID.
	 * @param string $page_token Page access token.
	 */
	public function __construct( $page_id, $page_token ) {
		$this->page_id    = $page_id;
		$this->page_token = $page_token;
	}

	/**
	 * Get page information.
	 *
	 * @return array|WP_Error Page data or error.
	 */
	public function get_page_info() {
		$fields = array(
			'id',
			'name',
			'about',
			'picture.type(large)',
			'fan_count',
			'website',
			'category',
			'cover',
			'link',
		);

		$response = $this->make_request(
			$this->page_id,
			array( 'fields' => implode( ',', $fields ) )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$picture_url = '';
		if ( ! empty( $response['picture']['data']['url'] ) ) {
			$picture_url = $response['picture']['data']['url'];
		}

		$cover_url = '';
		if ( ! empty( $response['cover']['source'] ) ) {
			$cover_url = $response['cover']['source'];
		}

		return array(
			'id'        => $response['id'] ?? $this->page_id,
			'name'      => $response['name'] ?? '',
			'about'     => $response['about'] ?? '',
			'picture'   => $picture_url,
			'fan_count' => $response['fan_count'] ?? 0,
			'website'   => $response['website'] ?? '',
			'category'  => $response['category'] ?? '',
			'cover'     => $cover_url,
			'link'      => $response['link'] ?? '',
		);
	}

	/**
	 * Get page posts.
	 *
	 * @param int         $limit        Number of posts to fetch.
	 * @param string|null $after_cursor Pagination cursor.
	 * @return array|WP_Error Posts data or error.
	 */
	public function get_posts( $limit = 20, $after_cursor = null ) {
		$limit = min( absint( $limit ), 100 );

		$fields = array(
			'id',
			'message',
			'story',
			'full_picture',
			'permalink_url',
			'created_time',
			'type',
			'likes.summary(true)',
			'comments.summary(true)',
			'shares',
			'attachments{media_type,media,url,title,description}',
		);

		$params = array(
			'fields' => implode( ',', $fields ),
			'limit'  => $limit,
		);

		if ( ! empty( $after_cursor ) ) {
			$params['after'] = $after_cursor;
		}

		$response = $this->make_request( $this->page_id . '/posts', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$items       = isset( $response['data'] ) ? $response['data'] : array();
		$next_cursor = null;

		if ( ! empty( $response['paging']['cursors']['after'] ) ) {
			$next_cursor = $response['paging']['cursors']['after'];
		}

		$normalized_items = array();
		foreach ( $items as $item ) {
			$normalized_items[] = $this->normalize_post( $item );
		}

		return array(
			'items'       => $normalized_items,
			'next_cursor' => $next_cursor,
			'has_more'    => ! empty( $next_cursor ),
		);
	}

	/**
	 * Get page photos.
	 *
	 * @param int $limit Number of photos to fetch.
	 * @return array|WP_Error Photos data or error.
	 */
	public function get_photos( $limit = 20 ) {
		$limit = min( absint( $limit ), 100 );

		$params = array(
			'type'   => 'uploaded',
			'fields' => 'id,picture,source,name,created_time,link,likes.summary(true),comments.summary(true)',
			'limit'  => $limit,
		);

		$response = $this->make_request( $this->page_id . '/photos', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$items = isset( $response['data'] ) ? $response['data'] : array();

		$normalized_items = array();
		foreach ( $items as $item ) {
			$normalized_items[] = array(
				'id'        => $item['id'] ?? '',
				'platform'  => 'facebook',
				'type'      => 'photo',
				'text'      => $item['name'] ?? '',
				'thumbnail' => $item['picture'] ?? '',
				'media_url' => $item['source'] ?? '',
				'permalink' => $item['link'] ?? '',
				'likes'     => $item['likes']['summary']['total_count'] ?? 0,
				'comments'  => $item['comments']['summary']['total_count'] ?? 0,
				'timestamp' => $item['created_time'] ?? '',
			);
		}

		return array(
			'items'    => $normalized_items,
			'has_more' => ! empty( $response['paging']['next'] ),
		);
	}

	/**
	 * Get page videos.
	 *
	 * @param int $limit Number of videos to fetch.
	 * @return array|WP_Error Videos data or error.
	 */
	public function get_videos( $limit = 20 ) {
		$limit = min( absint( $limit ), 100 );

		$params = array(
			'fields' => 'id,source,picture,title,description,length,views,created_time,permalink_url,likes.summary(true),comments.summary(true)',
			'limit'  => $limit,
		);

		$response = $this->make_request( $this->page_id . '/videos', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$items = isset( $response['data'] ) ? $response['data'] : array();

		$normalized_items = array();
		foreach ( $items as $item ) {
			$normalized_items[] = array(
				'id'                 => $item['id'] ?? '',
				'platform'           => 'facebook',
				'type'               => 'video',
				'title'              => $item['title'] ?? '',
				'text'               => $item['description'] ?? '',
				'thumbnail'          => $item['picture'] ?? '',
				'media_url'          => $item['source'] ?? '',
				'permalink'          => $item['permalink_url'] ?? '',
				'views'              => $item['views'] ?? 0,
				'likes'              => $item['likes']['summary']['total_count'] ?? 0,
				'comments'           => $item['comments']['summary']['total_count'] ?? 0,
				'duration_seconds'   => isset( $item['length'] ) ? round( $item['length'] ) : 0,
				'duration_formatted' => isset( $item['length'] ) ? gmdate( 'i:s', round( $item['length'] ) ) : '0:00',
				'timestamp'          => $item['created_time'] ?? '',
			);
		}

		return array(
			'items'    => $normalized_items,
			'has_more' => ! empty( $response['paging']['next'] ),
		);
	}

	/**
	 * Get page events.
	 *
	 * @param int $limit Number of events to fetch.
	 * @return array|WP_Error Events data or error.
	 */
	public function get_events( $limit = 10 ) {
		$limit = min( absint( $limit ), 50 );

		$params = array(
			'fields'      => 'id,name,description,start_time,end_time,place,cover,attending_count,interested_count',
			'limit'       => $limit,
			'time_filter' => 'upcoming',
		);

		$response = $this->make_request( $this->page_id . '/events', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$items = isset( $response['data'] ) ? $response['data'] : array();

		$normalized_items = array();
		foreach ( $items as $item ) {
			$place_name = '';
			if ( ! empty( $item['place']['name'] ) ) {
				$place_name = $item['place']['name'];
			}

			$cover_url = '';
			if ( ! empty( $item['cover']['source'] ) ) {
				$cover_url = $item['cover']['source'];
			}

			$normalized_items[] = array(
				'id'               => $item['id'] ?? '',
				'platform'         => 'facebook',
				'type'             => 'event',
				'name'             => $item['name'] ?? '',
				'description'      => $item['description'] ?? '',
				'cover'            => $cover_url,
				'place'            => $place_name,
				'start_time'       => $item['start_time'] ?? '',
				'end_time'         => $item['end_time'] ?? '',
				'attending_count'  => $item['attending_count'] ?? 0,
				'interested_count' => $item['interested_count'] ?? 0,
			);
		}

		return array(
			'items'    => $normalized_items,
			'has_more' => ! empty( $response['paging']['next'] ),
		);
	}

	/**
	 * Get page reviews/ratings.
	 *
	 * @param int $limit Number of reviews to fetch.
	 * @return array|WP_Error Reviews data or error.
	 */
	public function get_reviews( $limit = 20 ) {
		$limit = min( absint( $limit ), 100 );

		$params = array(
			'fields' => 'reviewer{id,name,picture},review_text,rating,created_time',
			'limit'  => $limit,
		);

		$response = $this->make_request( $this->page_id . '/ratings', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$items = isset( $response['data'] ) ? $response['data'] : array();

		$normalized_items = array();
		foreach ( $items as $item ) {
			$reviewer_name    = '';
			$reviewer_picture = '';

			if ( ! empty( $item['reviewer'] ) ) {
				$reviewer_name    = $item['reviewer']['name'] ?? '';
				$reviewer_picture = $item['reviewer']['picture']['data']['url'] ?? '';
			}

			$normalized_items[] = array(
				'id'               => $item['id'] ?? uniqid(),
				'platform'         => 'facebook',
				'type'             => 'review',
				'reviewer_name'    => $reviewer_name,
				'reviewer_picture' => $reviewer_picture,
				'review_text'      => $item['review_text'] ?? '',
				'rating'           => $item['rating'] ?? 0,
				'timestamp'        => $item['created_time'] ?? '',
			);
		}

		return array(
			'items'    => $normalized_items,
			'has_more' => ! empty( $response['paging']['next'] ),
		);
	}

	/**
	 * Normalize a post to standard format.
	 *
	 * @param array $raw Raw post data from API.
	 * @return array Normalized post data.
	 */
	public function normalize_post( $raw ) {
		$type = 'status';
		if ( ! empty( $raw['type'] ) ) {
			$type = $raw['type'];
		} elseif ( ! empty( $raw['attachments']['data'][0]['media_type'] ) ) {
			$type = strtolower( $raw['attachments']['data'][0]['media_type'] );
		}

		$type_map = array(
			'status'      => 'text',
			'photo'       => 'image',
			'video'       => 'video',
			'link'        => 'link',
			'share'       => 'link',
			'album'       => 'album',
			'event'       => 'event',
			'offer'       => 'offer',
		);

		$normalized_type = isset( $type_map[ $type ] ) ? $type_map[ $type ] : 'text';

		$text = '';
		if ( ! empty( $raw['message'] ) ) {
			$text = $raw['message'];
		} elseif ( ! empty( $raw['story'] ) ) {
			$text = $raw['story'];
		}

		$media_url = '';
		if ( ! empty( $raw['full_picture'] ) ) {
			$media_url = $raw['full_picture'];
		} elseif ( ! empty( $raw['attachments']['data'][0]['media']['image']['src'] ) ) {
			$media_url = $raw['attachments']['data'][0]['media']['image']['src'];
		}

		$likes    = 0;
		$comments = 0;
		$shares   = 0;

		if ( isset( $raw['likes']['summary']['total_count'] ) ) {
			$likes = absint( $raw['likes']['summary']['total_count'] );
		}

		if ( isset( $raw['comments']['summary']['total_count'] ) ) {
			$comments = absint( $raw['comments']['summary']['total_count'] );
		}

		if ( isset( $raw['shares']['count'] ) ) {
			$shares = absint( $raw['shares']['count'] );
		}

		return array(
			'id'        => $raw['id'] ?? '',
			'platform'  => 'facebook',
			'type'      => $normalized_type,
			'text'      => $text,
			'media_url' => $media_url,
			'permalink' => $raw['permalink_url'] ?? '',
			'likes'     => $likes,
			'comments'  => $comments,
			'shares'    => $shares,
			'timestamp' => $raw['created_time'] ?? '',
		);
	}

	/**
	 * Make a request to the Facebook Graph API.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $params   Query parameters.
	 * @return array|WP_Error Response data or error.
	 */
	private function make_request( $endpoint, $params = array() ) {
		if ( empty( $this->page_token ) ) {
			return new WP_Error(
				'missing_token',
				__( 'Facebook page access token is missing.', 'social-feed' )
			);
		}

		$params['access_token'] = $this->page_token;

		$url = add_query_arg( $params, self::GRAPH_URL . '/' . $endpoint );

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
				'Facebook API request failed: ' . $response->get_error_message(),
				'facebook'
			);
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			$error_code = $body['error']['code'] ?? 0;
			$error_msg  = $body['error']['message'] ?? __( 'Facebook API error.', 'social-feed' );

			if ( 190 === $error_code || 102 === $error_code ) {
				SF_Helpers::sf_log_error( 'Facebook token expired or invalid.', 'facebook' );
				return new WP_Error(
					'token_expired',
					__( 'Facebook access token has expired. Please reconnect your account.', 'social-feed' )
				);
			}

			if ( 4 === $error_code || 17 === $error_code ) {
				SF_Helpers::sf_log_warning( 'Facebook API rate limit reached.', 'facebook' );
				return new WP_Error(
					'rate_limit',
					__( 'Facebook API rate limit reached. Please try again later.', 'social-feed' )
				);
			}

			SF_Helpers::sf_log_error(
				sprintf( 'Facebook API error %d: %s', $error_code, $error_msg ),
				'facebook'
			);

			return new WP_Error( 'api_error', $error_msg );
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			SF_Helpers::sf_log_error(
				sprintf( 'Facebook API HTTP error %d', $status_code ),
				'facebook'
			);
			return new WP_Error( 'http_error', __( 'Facebook API request failed.', 'social-feed' ) );
		}

		return $body;
	}
}

/**
 * Register Facebook OAuth callback AJAX handler.
 */
add_action( 'wp_ajax_sf_facebook_callback', 'sf_facebook_oauth_callback' );
add_action( 'wp_ajax_nopriv_sf_facebook_callback', 'sf_facebook_oauth_callback' );

/**
 * Handle Facebook OAuth callback.
 */
function sf_facebook_oauth_callback() {
	$result = SF_Facebook_Auth::handle_oauth_callback();

	if ( is_wp_error( $result ) ) {
		sf_facebook_oauth_error_response( $result->get_error_message() );
	}

	sf_facebook_oauth_success_response( $result );
}

/**
 * Output success response for OAuth popup.
 *
 * @param array $data Result data.
 */
function sf_facebook_oauth_success_response( $data ) {
	$page_count = $data['total_pages'] ?? 1;
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<title><?php esc_html_e( 'Facebook Connected', 'social-feed' ); ?></title>
		<style>
			body {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
				background: #1877F2;
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
				background: #1877F2;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				margin: 0 auto 20px;
			}
			.success-icon svg { width: 40px; height: 40px; fill: #fff; }
			h1 { color: #1d2327; font-size: 24px; margin: 0 0 10px; }
			.page-count { color: #1877F2; font-size: 18px; margin: 0 0 20px; }
			p { color: #646970; margin: 0; font-size: 14px; }
			.pages-list {
				margin: 15px 0;
				padding: 0;
				list-style: none;
				text-align: left;
				max-height: 150px;
				overflow-y: auto;
			}
			.pages-list li {
				padding: 8px 12px;
				background: #f0f2f5;
				border-radius: 6px;
				margin-bottom: 6px;
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
			<p class="page-count">
				<?php
				printf(
					/* translators: %d: Number of pages */
					esc_html( _n( '%d Facebook Page connected', '%d Facebook Pages connected', $page_count, 'social-feed' ) ),
					$page_count
				);
				?>
			</p>
			<?php if ( ! empty( $data['pages'] ) ) : ?>
			<ul class="pages-list">
				<?php foreach ( $data['pages'] as $page ) : ?>
					<li><?php echo esc_html( $page['name'] ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
			<p><?php esc_html_e( 'This window will close automatically...', 'social-feed' ); ?></p>
		</div>
		<script>
			if (window.opener) {
				window.opener.postMessage({
					type: 'sf_oauth_success',
					platform: 'facebook',
					account_id: <?php echo wp_json_encode( $data['pages'][0]['page_id'] ?? '' ); ?>,
					account_name: <?php echo wp_json_encode( $data['account_name'] ?? '' ); ?>,
					access_token: '',
					refresh_token: '',
					expires_in: 0,
					profile_pic: <?php echo wp_json_encode( $data['pages'][0]['picture'] ?? '' ); ?>,
					already_saved: true,
					total_pages: <?php echo absint( $page_count ); ?>
				}, '*');
				setTimeout(function() { window.close(); }, 3000);
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
function sf_facebook_oauth_error_response( $message ) {
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
					platform: 'facebook',
					message: <?php echo wp_json_encode( $message ); ?>
				}, '*');
			}
		</script>
	</body>
	</html>
	<?php
	exit;
}
