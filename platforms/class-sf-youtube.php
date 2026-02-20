<?php
/**
 * YouTube platform integration for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_YouTube
 */
class SF_YouTube {

	/**
	 * Fetch posts from YouTube.
	 *
	 * @param int    $limit   Number of posts to fetch.
	 * @param string $feed_id Optional channel or playlist ID.
	 * @return array|WP_Error Array of posts or error.
	 */
	public function fetch_posts( $limit = 10, $feed_id = '' ) {
		$cache_key = SF_Helpers::get_cache_key( 'youtube', $feed_id, $limit );
		$cached    = SF_Cache::get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$posts = SF_Database::get_posts( 'youtube', $feed_id, $limit );
		if ( ! empty( $posts ) ) {
			SF_Cache::set( $cache_key, $posts );
			return $posts;
		}

		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return array();
		}

		$posts = $this->fetch_from_api( $api_key, $limit, $feed_id );
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
	 * Fetch from YouTube API.
	 *
	 * @param string $api_key  API key.
	 * @param int    $limit    Number of posts.
	 * @param string $channel_id Channel ID (optional).
	 * @return array|WP_Error
	 */
	private function fetch_from_api( $api_key, $limit = 10, $channel_id = '' ) {
		$params = array(
			'part'       => 'snippet',
			'type'       => 'video',
			'maxResults' => min( $limit, 50 ),
			'key'        => $api_key,
			'order'      => 'date',
		);

		if ( ! empty( $channel_id ) ) {
			$params['channelId'] = $channel_id;
		}

		$url = add_query_arg( $params, 'https://www.googleapis.com/youtube/v3/search' );

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'api_error', __( 'YouTube API request failed.', 'social-feed' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['items'] ) ) {
			return array();
		}

		$posts = array();
		foreach ( $body['items'] as $item ) {
			$snippet = isset( $item['snippet'] ) ? $item['snippet'] : array();
			$video_id = isset( $item['id']['videoId'] ) ? $item['id']['videoId'] : '';
			$thumbnails = isset( $snippet['thumbnails'] ) ? $snippet['thumbnails'] : array();
			$thumb = isset( $thumbnails['high'] ) ? $thumbnails['high'] : ( isset( $thumbnails['default'] ) ? $thumbnails['default'] : array() );

			$posts[] = array(
				'platform'      => 'youtube',
				'post_id'       => $video_id,
				'feed_id'       => $channel_id,
				'content'       => isset( $snippet['description'] ) ? $snippet['description'] : '',
				'permalink'     => $video_id ? 'https://www.youtube.com/watch?v=' . $video_id : '',
				'thumbnail_url' => isset( $thumb['url'] ) ? $thumb['url'] : '',
				'author_name'   => isset( $snippet['channelTitle'] ) ? $snippet['channelTitle'] : '',
				'author_avatar' => '',
				'created_at'    => isset( $snippet['publishedAt'] ) ? date( 'Y-m-d H:i:s', strtotime( $snippet['publishedAt'] ) ) : '',
				'raw_data'      => maybe_serialize( $item ),
			);
		}

		return $posts;
	}

	/**
	 * Get stored API key.
	 *
	 * @return string
	 */
	private function get_api_key() {
		$settings = get_option( 'sf_settings', array() );
		return isset( $settings['youtube_api_key'] ) ? $settings['youtube_api_key'] : '';
	}
}
