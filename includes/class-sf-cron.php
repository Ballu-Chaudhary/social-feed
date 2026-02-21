<?php
/**
 * Cron Jobs for Social Feed plugin.
 *
 * Handles scheduled background tasks.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Cron
 *
 * Manages WP Cron scheduled events for the plugin.
 */
class SF_Cron {

	/**
	 * Cron hook for feed refresh.
	 *
	 * @var string
	 */
	const REFRESH_HOOK = 'sf_refresh_feeds_cron';

	/**
	 * Cron hook for token expiry check.
	 *
	 * @var string
	 */
	const TOKEN_CHECK_HOOK = 'sf_check_token_expiry';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( self::REFRESH_HOOK, array( $this, 'refresh_all_feeds' ) );
		add_action( self::TOKEN_CHECK_HOOK, array( $this, 'check_all_tokens' ) );
	}

	/**
	 * Schedule cron events on plugin activation.
	 */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( self::REFRESH_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::REFRESH_HOOK );
		}

		if ( ! wp_next_scheduled( self::TOKEN_CHECK_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::TOKEN_CHECK_HOOK );
		}
	}

	/**
	 * Clear scheduled events on plugin deactivation.
	 */
	public static function clear_events() {
		wp_clear_scheduled_hook( self::REFRESH_HOOK );
		wp_clear_scheduled_hook( self::TOKEN_CHECK_HOOK );
	}

	/**
	 * Refresh all active feeds that have expired cache.
	 */
	public function refresh_all_feeds() {
		$feeds = SF_Database::get_all_feeds( array( 'status' => 'active' ) );

		if ( empty( $feeds ) ) {
			return;
		}

		$refreshed = 0;
		$errors    = 0;

		foreach ( $feeds as $feed ) {
			if ( ! SF_Cache::is_expired( $feed['id'] ) ) {
				continue;
			}

			$result = SF_Feed_Manager::fetch_from_api( $feed['id'] );

			if ( is_wp_error( $result ) ) {
				$errors++;
			} else {
				$refreshed++;
			}

			usleep( 500000 );
		}

		SF_Helpers::sf_log(
			sprintf(
				'Cron feed refresh completed: %d feeds refreshed, %d errors.',
				$refreshed,
				$errors
			),
			'cron'
		);
	}

	/**
	 * Check all account tokens for expiry.
	 */
	public function check_all_tokens() {
		global $wpdb;

		$table = SF_Database::get_table( 'accounts' );

		$expiring_accounts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE token_expires IS NOT NULL 
				AND token_expires < DATE_ADD(NOW(), INTERVAL %d DAY) 
				AND is_connected = 1",
				7
			),
			ARRAY_A
		);

		if ( empty( $expiring_accounts ) ) {
			SF_Helpers::sf_log( 'Token check: No tokens expiring soon.', 'cron' );
			return;
		}

		$refreshed  = 0;
		$failed     = 0;
		$notified   = array();
		$settings   = get_option( 'sf_settings', array() );
		$notify     = ! empty( $settings['email_notifications'] );

		foreach ( $expiring_accounts as $account ) {
			$expires = strtotime( $account['token_expires'] );
			$now     = time();

			if ( 'instagram' === $account['platform'] && $expires > $now ) {
				$result = SF_Feed_Manager::refresh_account_token( $account['id'] );

				if ( is_wp_error( $result ) ) {
					$failed++;
					$notified[] = $account;

					SF_Helpers::sf_log_error(
						sprintf(
							'Failed to refresh token for account #%d (%s): %s',
							$account['id'],
							$account['account_name'],
							$result->get_error_message()
						),
						'cron'
					);
				} else {
					$refreshed++;

					SF_Helpers::sf_log(
						sprintf(
							'Auto-refreshed token for account #%d (%s).',
							$account['id'],
							$account['account_name']
						),
						'cron'
					);
				}
			} else {
				$notified[] = $account;

				if ( $expires < $now ) {
					SF_Database::update_account(
						$account['id'],
						array(
							'is_connected' => 0,
							'last_error'   => __( 'Token has expired.', 'social-feed' ),
						)
					);
				}
			}

			usleep( 200000 );
		}

		if ( $notify && ! empty( $notified ) ) {
			$this->send_expiry_notification( $notified );
		}

		SF_Helpers::sf_log(
			sprintf(
				'Token check completed: %d refreshed, %d failed, %d notifications sent.',
				$refreshed,
				$failed,
				count( $notified )
			),
			'cron'
		);
	}

	/**
	 * Send email notification about expiring/expired tokens.
	 *
	 * @param array $accounts Accounts with token issues.
	 */
	private function send_expiry_notification( $accounts ) {
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Social Feed: Account tokens need attention', 'social-feed' ),
			$site_name
		);

		$message  = __( 'The following social media accounts need your attention:', 'social-feed' );
		$message .= "\n\n";

		foreach ( $accounts as $account ) {
			$expires = strtotime( $account['token_expires'] );
			$now     = time();

			if ( $expires < $now ) {
				$status = __( 'EXPIRED', 'social-feed' );
			} else {
				$days   = ceil( ( $expires - $now ) / DAY_IN_SECONDS );
				$status = sprintf(
					/* translators: %d: number of days */
					_n( 'Expires in %d day', 'Expires in %d days', $days, 'social-feed' ),
					$days
				);
			}

			$message .= sprintf(
				"- %s (%s): %s\n",
				$account['account_name'],
				ucfirst( $account['platform'] ),
				$status
			);
		}

		$message .= "\n";
		$message .= __( 'Please reconnect these accounts to continue fetching content.', 'social-feed' );
		$message .= "\n\n";
		$message .= sprintf(
			/* translators: %s: admin URL */
			__( 'Manage accounts: %s', 'social-feed' ),
			admin_url( 'admin.php?page=social-feed-accounts' )
		);

		wp_mail( $admin_email, $subject, $message );

		SF_Helpers::sf_log(
			sprintf( 'Sent token expiry notification for %d accounts.', count( $accounts ) ),
			'cron'
		);
	}

	/**
	 * Get next scheduled run time for a hook.
	 *
	 * @param string $hook Hook name.
	 * @return int|false Timestamp or false if not scheduled.
	 */
	public static function get_next_run( $hook ) {
		return wp_next_scheduled( $hook );
	}

	/**
	 * Get cron status information.
	 *
	 * @return array Cron status data.
	 */
	public static function get_status() {
		$refresh_next = wp_next_scheduled( self::REFRESH_HOOK );
		$token_next   = wp_next_scheduled( self::TOKEN_CHECK_HOOK );

		return array(
			'refresh_feeds' => array(
				'hook'      => self::REFRESH_HOOK,
				'schedule'  => 'hourly',
				'next_run'  => $refresh_next ? gmdate( 'Y-m-d H:i:s', $refresh_next ) : null,
				'scheduled' => (bool) $refresh_next,
			),
			'token_check'   => array(
				'hook'      => self::TOKEN_CHECK_HOOK,
				'schedule'  => 'daily',
				'next_run'  => $token_next ? gmdate( 'Y-m-d H:i:s', $token_next ) : null,
				'scheduled' => (bool) $token_next,
			),
		);
	}

	/**
	 * Manually trigger feed refresh for a specific feed.
	 *
	 * @param int $feed_id Feed ID.
	 * @return array|WP_Error Result or error.
	 */
	public static function manual_refresh( $feed_id ) {
		SF_Cache::delete( $feed_id );

		return SF_Feed_Manager::fetch_from_api( $feed_id );
	}

	/**
	 * Manually trigger refresh for all feeds.
	 *
	 * @return array Results summary.
	 */
	public static function manual_refresh_all() {
		$instance = new self();

		ob_start();
		$instance->refresh_all_feeds();
		ob_end_clean();

		return array(
			'success' => true,
			'message' => __( 'Feed refresh triggered.', 'social-feed' ),
		);
	}
}
