<?php
/**
 * Instagram platform integration for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Instagram
 */
class SF_Instagram {

	/**
	 * Fetch posts from Instagram.
	 *
	 * @param int    $limit   Number of posts to fetch.
	 * @param string $feed_id Optional feed/account identifier.
	 * @return array|WP_Error Array of posts or error.
	 */
	public function fetch_posts( $limit = 10, $feed_id = '' ) {
		$cache_key = SF_Helpers::get_cache_key( 'instagram', $feed_id, $limit );
		$cached    = SF_Cache::get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$posts = SF_Database::get_posts( 'instagram', $feed_id, $limit );
		if ( ! empty( $posts ) ) {
			SF_Cache::set( $cache_key, $posts );
			return $posts;
		}

		$access_token = $this->get_access_token();
		if ( empty( $access_token ) ) {
			return array();
		}

		$posts = $this->fetch_from_api( $access_token, $limit );
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
	 * Fetch from Instagram API.
	 *
	 * @param string $access_token API access token.
	 * @param int    $limit       Number of posts.
	 * @return array|WP_Error
	 */
	private function fetch_from_api( $access_token, $limit = 10 ) {
		$url = add_query_arg(
			array(
				'fields'       => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp',
				'access_token' => $access_token,
				'limit'        => $limit,
			),
			'https://graph.instagram.com/me/media'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'api_error', __( 'Instagram API request failed.', 'social-feed' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['data'] ) ) {
			return array();
		}

		$posts = array();
		foreach ( $body['data'] as $item ) {
			$posts[] = array(
				'platform'      => 'instagram',
				'post_id'       => isset( $item['id'] ) ? $item['id'] : '',
				'feed_id'       => '',
				'content'       => isset( $item['caption'] ) ? $item['caption'] : '',
				'permalink'     => isset( $item['permalink'] ) ? $item['permalink'] : '',
				'thumbnail_url' => isset( $item['media_url'] ) ? $item['media_url'] : ( isset( $item['thumbnail_url'] ) ? $item['thumbnail_url'] : '' ),
				'author_name'   => '',
				'author_avatar' => '',
				'created_at'    => isset( $item['timestamp'] ) ? date( 'Y-m-d H:i:s', strtotime( $item['timestamp'] ) ) : '',
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
		return isset( $settings['instagram_access_token'] ) ? $settings['instagram_access_token'] : '';
	}
}
