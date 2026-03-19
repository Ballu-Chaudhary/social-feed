<?php
/**
 * Instagram Graph API client.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
	const API_VERSION = 'v25.0';

	/**
	 * Access token.
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Instagram user ID (account_id_ext).
	 *
	 * @var string
	 */
	private $user_id;

	/**
	 * Constructor.
	 *
	 * @param string $access_token Instagram access token.
	 * @param string $user_id      Optional. Instagram user ID (account_id_ext). get_media() uses /me.
	 */
	public function __construct( $access_token, $user_id = '' ) {
		$this->access_token = $access_token;
		$this->user_id      = $user_id ? (string) $user_id : '';
	}

	/**
	 * Get user profile information.
	 *
	 * @return array|WP_Error Profile data or error.
	 */
	public function get_profile() {
		$fields = array(
			'id',
			'user_id',
			'username',
			'profile_picture_url',
			'followers_count',
		);

		$url = add_query_arg(
			array(
				'fields'       => implode( ',', $fields ),
				'access_token' => $this->access_token,
			),
			self::GRAPH_URL . '/' . self::API_VERSION . '/me'
		);

		$response = $this->make_api_request( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = $response;

		$actual_user_id = '';
		if ( ! empty( $body['data'][0]['user_id'] ) ) {
			$actual_user_id = $body['data'][0]['user_id'];
		} elseif ( ! empty( $body['user_id'] ) ) {
			$actual_user_id = $body['user_id'];
		} elseif ( ! empty( $body['id'] ) ) {
			$actual_user_id = $body['id'];
		}

		$username = ! empty( $body['data'][0]['username'] ) ? $body['data'][0]['username'] : ( ! empty( $body['username'] ) ? $body['username'] : '' );

		if ( empty( $actual_user_id ) || empty( $username ) ) {
			return new WP_Error( 'invalid_profile', __( 'Invalid profile response from Instagram.', 'social-feed' ) );
		}

		return array(
			'id'                  => $actual_user_id,
			'username'            => $username,
			'profile_picture_url' => isset( $body['profile_picture_url'] ) ? $body['profile_picture_url'] : '',
			'followers_count'     => isset( $body['followers_count'] ) ? (int) $body['followers_count'] : 0,
		);
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
			'media_type',
			'media_url',
			'thumbnail_url',
			'permalink',
			'timestamp',
		);

		$params = array(
			'fields'       => implode( ',', $fields ),
			'limit'        => $limit,
			'access_token' => $this->access_token,
		);

		if ( ! empty( $after_cursor ) ) {
			$params['after'] = $after_cursor;
		}

		$url = add_query_arg( $params, self::GRAPH_URL . '/' . self::API_VERSION . '/' . $this->user_id . '/media' );

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
			self::GRAPH_URL . '/' . self::API_VERSION . '/ig_hashtag_search'
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
			'media_type',
			'media_url',
			'permalink',
			'timestamp',
		);

		$media_url = add_query_arg(
			array(
				'user_id'      => $user_id,
				'fields'       => implode( ',', $fields ),
				'limit'        => $limit,
				'access_token' => $this->access_token,
			),
			self::GRAPH_URL . '/' . self::API_VERSION . '/' . $hashtag_id . '/top_media'
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
			return new WP_Error( 'token_expired', $error_msg );
		}

		if ( 429 === $status_code ) {
			$error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Instagram API rate limit exceeded. Please try again later.', 'social-feed' );
			SF_Helpers::sf_log_warning( 'Instagram rate limit: ' . $error_msg, 'instagram' );
			return new WP_Error( 'rate_limit', $error_msg );
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_msg  = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Instagram API request failed.', 'social-feed' );
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
