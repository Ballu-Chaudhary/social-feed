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

		if ( 'instagram' !== $feed['platform'] ) {
			$result = new WP_Error( 'unknown_platform', __( 'This plugin supports Instagram feeds only.', 'social-feed' ) );
		} else {
			$result = self::fetch_instagram( $account, $access_token, $settings, $limit );
		}

		if ( is_wp_error( $result ) ) {
			self::log_fetch_error( $feed_id, $account, $result );
			// Return WP_Error so it bubbles up to cache, renderer, and admin preview with full API message.
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

		$user_id = isset( $account['account_id_ext'] ) ? $account['account_id_ext'] : '';
		$api     = new SF_Instagram_API( $access_token, $user_id );

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
	 * Refresh an Instagram long-lived token.
	 *
	 * @param string $token Current long-lived token.
	 * @return array|WP_Error
	 */
	private static function refresh_instagram_token( $token ) {
		$url = add_query_arg(
			array(
				'grant_type'   => 'ig_refresh_token',
				'access_token' => $token,
			),
			'https://graph.instagram.com/refresh_access_token'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status_code || empty( $body['access_token'] ) ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Failed to refresh Instagram token.', 'social-feed' );
			return new WP_Error( 'token_refresh_failed', $message );
		}

		return array(
			'access_token' => $body['access_token'],
			'expires_in'   => isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 5184000,
		);
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

		if ( 'instagram' !== $account['platform'] ) {
			return new WP_Error( 'unsupported_platform', __( 'Only Instagram tokens can be refreshed.', 'social-feed' ) );
		}

		$new_token_data = self::refresh_instagram_token( $current_token );

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

		if ( 'instagram' !== $feed['platform'] ) {
			return new WP_Error( 'unknown_platform', __( 'This plugin supports Instagram feeds only.', 'social-feed' ) );
		}

		$user_id = isset( $account['account_id_ext'] ) ? $account['account_id_ext'] : '';
		$api     = new SF_Instagram_API( $access_token, $user_id );
		$media   = $api->get_media( $limit, $cursor );

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
	}
}
