<?php
/**
 * Feed Manager for Social Feed plugin.
 *
 * Coordinates API fetching and data normalization.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Feed_Manager
 *
 * Handles feed data fetching from various platform APIs.
 */
class SF_Feed_Manager {

	/**
	 * Fetch feed data from the appropriate API.
	 *
	 * @param int $feed_id Feed ID.
	 * @return array|WP_Error Normalized items array or error.
	 */
	public static function fetch_from_api( $feed_id ) {
		$feed = SF_Database::get_feed( $feed_id );

		if ( ! $feed ) {
			return new WP_Error( 'feed_not_found', __( 'Feed not found.', 'social-feed' ) );
		}

		if ( 'active' !== $feed['status'] ) {
			return new WP_Error( 'feed_inactive', __( 'Feed is not active.', 'social-feed' ) );
		}

		$account = null;
		if ( ! empty( $feed['account_id'] ) ) {
			$account = SF_Database::get_account( $feed['account_id'] );
		}

		if ( ! $account ) {
			return new WP_Error( 'no_account', __( 'No account connected to this feed.', 'social-feed' ) );
		}

		if ( empty( $account['is_connected'] ) ) {
			return new WP_Error( 'account_disconnected', __( 'Account is disconnected.', 'social-feed' ) );
		}

		$access_token = self::decrypt_token( $account['access_token'] );

		if ( empty( $access_token ) ) {
			return new WP_Error( 'no_token', __( 'Access token is missing or invalid.', 'social-feed' ) );
		}

		$settings = SF_Database::get_all_feed_meta( $feed_id );
		$limit    = absint( $settings['posts_per_page'] ?? 20 );

		$result = null;

		switch ( $feed['platform'] ) {
			case 'instagram':
				$result = self::fetch_instagram( $account, $access_token, $settings, $limit );
				break;

			case 'youtube':
				$result = self::fetch_youtube( $account, $settings, $limit );
				break;

			case 'facebook':
				$result = self::fetch_facebook( $account, $access_token, $settings, $limit );
				break;

			default:
				$result = new WP_Error( 'unknown_platform', __( 'Unknown platform.', 'social-feed' ) );
		}

		if ( is_wp_error( $result ) ) {
			self::log_fetch_error( $feed_id, $account, $result );
			return $result;
		}

		self::save_items_to_db( $feed_id, $result['items'] ?? array() );

		SF_Cache::set( $feed_id, $result );

		self::log_fetch_success( $feed_id, count( $result['items'] ?? array() ) );

		return $result;
	}

	/**
	 * Decrypt access token.
	 *
	 * @param string $encrypted_token Encrypted token.
	 * @return string Decrypted token.
	 */
	private static function decrypt_token( $encrypted_token ) {
		if ( empty( $encrypted_token ) ) {
			return '';
		}

		$decrypted = SF_Helpers::sf_decrypt( $encrypted_token );

		if ( false === $decrypted || empty( $decrypted ) ) {
			return $encrypted_token;
		}

		return $decrypted;
	}

	/**
	 * Fetch Instagram feed data.
	 *
	 * @param array  $account      Account data.
	 * @param string $access_token Access token.
	 * @param array  $settings     Feed settings.
	 * @param int    $limit        Number of items.
	 * @return array|WP_Error
	 */
	private static function fetch_instagram( $account, $access_token, $settings, $limit ) {
		if ( ! class_exists( 'SF_Instagram_API' ) ) {
			return new WP_Error( 'class_missing', __( 'Instagram API class not found.', 'social-feed' ) );
		}

		$api = new SF_Instagram_API( $access_token );

		$profile = $api->get_profile();
		if ( is_wp_error( $profile ) ) {
			$profile = array();
		}

		$feed_type = $settings['feed_type'] ?? 'user_media';

		if ( 'hashtag' === $feed_type && ! empty( $settings['hashtag'] ) ) {
			$hashtag = sanitize_text_field( $settings['hashtag'] );
			$user_id = $account['account_id_ext'];
			$media   = $api->get_hashtag_media( $hashtag, $user_id, $limit );
		} else {
			$media = $api->get_media( $limit );
		}

		if ( is_wp_error( $media ) ) {
			return $media;
		}

		$items = array();
		if ( ! empty( $media['items'] ) ) {
			foreach ( $media['items'] as $item ) {
				$items[] = $api->normalize_item( $item );
			}
		}

		return array(
			'profile'     => $profile,
			'items'       => $items,
			'next_cursor' => $media['next_cursor'] ?? '',
			'has_more'    => ! empty( $media['next_cursor'] ),
		);
	}

	/**
	 * Fetch YouTube feed data.
	 *
	 * @param array $account  Account data.
	 * @param array $settings Feed settings.
	 * @param int   $limit    Number of items.
	 * @return array|WP_Error
	 */
	private static function fetch_youtube( $account, $settings, $limit ) {
		if ( ! class_exists( 'SF_YouTube_API' ) ) {
			return new WP_Error( 'class_missing', __( 'YouTube API class not found.', 'social-feed' ) );
		}

		$api_key = self::get_youtube_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'YouTube API key is not configured.', 'social-feed' ) );
		}

		$api        = new SF_YouTube_API( $api_key );
		$channel_id = $account['account_id_ext'];
		$feed_type  = $settings['youtube_source'] ?? 'channel';

		$channel = $api->get_channel( $channel_id );
		if ( is_wp_error( $channel ) ) {
			$channel = array();
		}

		if ( 'playlist' === $feed_type && ! empty( $settings['youtube_playlist'] ) ) {
			$videos = $api->get_playlist_videos( $settings['youtube_playlist'], $limit );
		} elseif ( 'search' === $feed_type && ! empty( $settings['youtube_search'] ) ) {
			$videos = $api->search_videos( $settings['youtube_search'], $channel_id, $limit );
		} else {
			$videos = $api->get_channel_videos( $channel_id, $limit );
		}

		if ( is_wp_error( $videos ) ) {
			return $videos;
		}

		$items = array();
		if ( ! empty( $videos['items'] ) ) {
			foreach ( $videos['items'] as $video ) {
				$items[] = $api->normalize_video( $video );
			}
		}

		return array(
			'profile'     => $channel,
			'items'       => $items,
			'next_cursor' => $videos['next_page_token'] ?? '',
			'has_more'    => ! empty( $videos['next_page_token'] ),
		);
	}

	/**
	 * Fetch Facebook feed data.
	 *
	 * @param array  $account      Account data.
	 * @param string $access_token Access token.
	 * @param array  $settings     Feed settings.
	 * @param int    $limit        Number of items.
	 * @return array|WP_Error
	 */
	private static function fetch_facebook( $account, $access_token, $settings, $limit ) {
		if ( ! class_exists( 'SF_Facebook_API' ) ) {
			return new WP_Error( 'class_missing', __( 'Facebook API class not found.', 'social-feed' ) );
		}

		$page_id = $account['account_id_ext'];
		$api     = new SF_Facebook_API( $page_id, $access_token );

		$page_info = $api->get_page_info();
		if ( is_wp_error( $page_info ) ) {
			$page_info = array();
		}

		$content_type = $settings['facebook_content'] ?? 'posts';

		switch ( $content_type ) {
			case 'photos':
				$content = $api->get_photos( $limit );
				break;

			case 'videos':
				$content = $api->get_videos( $limit );
				break;

			case 'events':
				$content = $api->get_events( $limit );
				break;

			case 'reviews':
				$content = $api->get_reviews( $limit );
				break;

			default:
				$content = $api->get_posts( $limit );
		}

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$items = array();
		if ( ! empty( $content['items'] ) ) {
			foreach ( $content['items'] as $item ) {
				$items[] = $api->normalize_post( $item );
			}
		}

		return array(
			'profile'     => $page_info,
			'items'       => $items,
			'next_cursor' => $content['next_cursor'] ?? '',
			'has_more'    => ! empty( $content['next_cursor'] ),
		);
	}

	/**
	 * Get YouTube API key from settings.
	 *
	 * @return string API key or empty string.
	 */
	private static function get_youtube_api_key() {
		$settings = get_option( 'sf_settings', array() );

		if ( ! empty( $settings['youtube_api_key'] ) ) {
			$key = SF_Helpers::sf_decrypt( $settings['youtube_api_key'] );
			return $key ? $key : $settings['youtube_api_key'];
		}

		return '';
	}

	/**
	 * Save fetched items to database.
	 *
	 * @param int   $feed_id Feed ID.
	 * @param array $items   Items to save.
	 */
	private static function save_items_to_db( $feed_id, $items ) {
		if ( empty( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			$data = array(
				'feed_id'      => $feed_id,
				'item_id_ext'  => $item['id'] ?? '',
				'content_type' => $item['type'] ?? 'post',
				'content_data' => wp_json_encode( $item ),
				'created_at'   => $item['timestamp'] ?? current_time( 'mysql' ),
			);

			SF_Database::upsert_feed_item( $data );
		}
	}

	/**
	 * Log successful fetch.
	 *
	 * @param int $feed_id    Feed ID.
	 * @param int $item_count Number of items fetched.
	 */
	private static function log_fetch_success( $feed_id, $item_count ) {
		SF_Helpers::sf_log(
			sprintf( 'Feed #%d: Successfully fetched %d items.', $feed_id, $item_count ),
			'cache'
		);
	}

	/**
	 * Log fetch error.
	 *
	 * @param int      $feed_id Feed ID.
	 * @param array    $account Account data.
	 * @param WP_Error $error   Error object.
	 */
	private static function log_fetch_error( $feed_id, $account, $error ) {
		$error_code = $error->get_error_code();

		if ( in_array( $error_code, array( 'token_expired', 'unauthorized', '401' ), true ) ) {
			SF_Database::update_account(
				$account['id'],
				array(
					'is_connected' => 0,
					'last_error'   => $error->get_error_message(),
				)
			);
		}

		SF_Helpers::sf_log_error(
			sprintf(
				'Feed #%d (%s): %s - %s',
				$feed_id,
				$account['platform'],
				$error->get_error_code(),
				$error->get_error_message()
			),
			'cache'
		);
	}

	/**
	 * Refresh token for an account if needed.
	 *
	 * @param int $account_id Account ID.
	 * @return bool|WP_Error True on success, false if not needed, WP_Error on failure.
	 */
	public static function refresh_account_token( $account_id ) {
		$account = SF_Database::get_account( $account_id );

		if ( ! $account ) {
			return new WP_Error( 'account_not_found', __( 'Account not found.', 'social-feed' ) );
		}

		$current_token = self::decrypt_token( $account['access_token'] );

		if ( empty( $current_token ) ) {
			return new WP_Error( 'no_token', __( 'No token to refresh.', 'social-feed' ) );
		}

		$new_token_data = null;

		switch ( $account['platform'] ) {
			case 'instagram':
				if ( class_exists( 'SF_Instagram_Auth' ) ) {
					$new_token_data = SF_Instagram_Auth::refresh_token( $current_token );
				}
				break;

			case 'youtube':
				if ( class_exists( 'SF_YouTube_Auth' ) ) {
					$refresh_token = SF_Database::get_account_meta( $account_id, 'refresh_token' );
					if ( $refresh_token ) {
						$new_token_data = SF_YouTube_Auth::refresh_token( $refresh_token );
					}
				}
				break;

			case 'facebook':
				break;
		}

		if ( is_wp_error( $new_token_data ) ) {
			SF_Database::update_account(
				$account_id,
				array( 'last_error' => $new_token_data->get_error_message() )
			);
			return $new_token_data;
		}

		if ( ! empty( $new_token_data['access_token'] ) ) {
			$encrypted = SF_Helpers::sf_encrypt( $new_token_data['access_token'] );

			$update_data = array(
				'access_token' => $encrypted,
				'is_connected' => 1,
				'last_error'   => null,
			);

			if ( ! empty( $new_token_data['expires_in'] ) ) {
				$update_data['token_expires'] = gmdate( 'Y-m-d H:i:s', time() + $new_token_data['expires_in'] );
			}

			SF_Database::update_account( $account_id, $update_data );

			SF_Helpers::sf_log(
				sprintf( 'Token refreshed for account #%d (%s).', $account_id, $account['platform'] ),
				'auth'
			);

			return true;
		}

		return false;
	}

	/**
	 * Fetch paginated items.
	 *
	 * @param int    $feed_id Feed ID.
	 * @param string $cursor  Pagination cursor.
	 * @return array|WP_Error Items or error.
	 */
	public static function fetch_page( $feed_id, $cursor ) {
		$feed = SF_Database::get_feed( $feed_id );

		if ( ! $feed ) {
			return new WP_Error( 'feed_not_found', __( 'Feed not found.', 'social-feed' ) );
		}

		$account = null;
		if ( ! empty( $feed['account_id'] ) ) {
			$account = SF_Database::get_account( $feed['account_id'] );
		}

		if ( ! $account ) {
			return new WP_Error( 'no_account', __( 'No account connected.', 'social-feed' ) );
		}

		$access_token = self::decrypt_token( $account['access_token'] );
		$settings     = SF_Database::get_all_feed_meta( $feed_id );
		$limit        = absint( $settings['posts_per_page'] ?? 20 );

		switch ( $feed['platform'] ) {
			case 'instagram':
				$api   = new SF_Instagram_API( $access_token );
				$media = $api->get_media( $limit, $cursor );

				if ( is_wp_error( $media ) ) {
					return $media;
				}

				$items = array();
				foreach ( $media['items'] ?? array() as $item ) {
					$items[] = $api->normalize_item( $item );
				}

				return array(
					'items'       => $items,
					'next_cursor' => $media['next_cursor'] ?? '',
					'has_more'    => ! empty( $media['next_cursor'] ),
				);

			case 'youtube':
				$api_key = self::get_youtube_api_key();
				$api     = new SF_YouTube_API( $api_key );
				$videos  = $api->get_channel_videos( $account['account_id_ext'], $limit, $cursor );

				if ( is_wp_error( $videos ) ) {
					return $videos;
				}

				$items = array();
				foreach ( $videos['items'] ?? array() as $video ) {
					$items[] = $api->normalize_video( $video );
				}

				return array(
					'items'       => $items,
					'next_cursor' => $videos['next_page_token'] ?? '',
					'has_more'    => ! empty( $videos['next_page_token'] ),
				);

			case 'facebook':
				$api     = new SF_Facebook_API( $account['account_id_ext'], $access_token );
				$content = $api->get_posts( $limit, $cursor );

				if ( is_wp_error( $content ) ) {
					return $content;
				}

				$items = array();
				foreach ( $content['items'] ?? array() as $item ) {
					$items[] = $api->normalize_post( $item );
				}

				return array(
					'items'       => $items,
					'next_cursor' => $content['next_cursor'] ?? '',
					'has_more'    => ! empty( $content['next_cursor'] ),
				);

			default:
				return new WP_Error( 'unknown_platform', __( 'Unknown platform.', 'social-feed' ) );
		}
	}
}
