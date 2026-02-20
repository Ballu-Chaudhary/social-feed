<?php
/**
 * Facebook platform integration for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Facebook
 */
class SF_Facebook {

	/**
	 * Fetch posts from Facebook.
	 *
	 * @param int    $limit   Number of posts to fetch.
	 * @param string $feed_id Optional page ID.
	 * @return array|WP_Error Array of posts or error.
	 */
	public function fetch_posts( $limit = 10, $feed_id = '' ) {
		$cache_key = SF_Helpers::get_cache_key( 'facebook', $feed_id, $limit );
		$cached    = SF_Cache::get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$posts = SF_Database::get_posts( 'facebook', $feed_id, $limit );
		if ( ! empty( $posts ) ) {
			SF_Cache::set( $cache_key, $posts );
			return $posts;
		}

		$access_token = $this->get_access_token();
		$page_id      = ! empty( $feed_id ) ? $feed_id : $this->get_page_id();
		if ( empty( $access_token ) || empty( $page_id ) ) {
			return array();
		}

		$posts = $this->fetch_from_api( $access_token, $page_id, $limit );
		if ( is_wp_error( $posts ) ) {
			return $posts;
		}

		foreach ( $posts as $post ) {
			SF_Database::upsert_post( $post );
		}

		SF_Cache::set( $cache_key, $posts );
		return $posts;
	}

	/**
	 * Fetch from Facebook Graph API.
	 *
	 * @param string $access_token API access token.
	 * @param string $page_id     Page ID.
	 * @param int    $limit       Number of posts.
	 * @return array|WP_Error
	 */
	private function fetch_from_api( $access_token, $page_id, $limit = 10 ) {
		$url = add_query_arg(
			array(
				'fields'       => 'id,message,permalink_url,full_picture,picture,created_time,from',
				'access_token' => $access_token,
				'limit'        => $limit,
			),
			'https://graph.facebook.com/v18.0/' . $page_id . '/posts'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'api_error', __( 'Facebook API request failed.', 'social-feed' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['data'] ) ) {
			return array();
		}

		$posts = array();
		foreach ( $body['data'] as $item ) {
			$from     = isset( $item['from']['name'] ) ? $item['from']['name'] : '';
			$picture  = isset( $item['full_picture'] ) ? $item['full_picture'] : ( isset( $item['picture'] ) ? $item['picture'] : '' );

			$posts[] = array(
				'platform'      => 'facebook',
				'post_id'       => isset( $item['id'] ) ? $item['id'] : '',
				'feed_id'       => $page_id,
				'content'       => isset( $item['message'] ) ? $item['message'] : '',
				'permalink'     => isset( $item['permalink_url'] ) ? $item['permalink_url'] : '',
				'thumbnail_url' => $picture,
				'author_name'   => $from,
				'author_avatar' => '',
				'created_at'    => isset( $item['created_time'] ) ? date( 'Y-m-d H:i:s', strtotime( $item['created_time'] ) ) : '',
				'raw_data'      => maybe_serialize( $item ),
			);
		}

		return $posts;
	}

	/**
	 * Get stored access token.
	 *
	 * @return string
	 */
	private function get_access_token() {
		$settings = get_option( 'sf_settings', array() );
		return isset( $settings['facebook_access_token'] ) ? $settings['facebook_access_token'] : '';
	}

	/**
	 * Get stored page ID.
	 *
	 * @return string
	 */
	private function get_page_id() {
		$settings = get_option( 'sf_settings', array() );
		return isset( $settings['facebook_page_id'] ) ? $settings['facebook_page_id'] : '';
	}
}
