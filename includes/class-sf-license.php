<?php
/**
 * License Management for Social Feed Pro.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_License
 *
 * Handles license activation, validation, and automatic updates.
 */
class SF_License {

	/**
	 * License store URL.
	 *
	 * @var string
	 */
	const STORE_URL = 'https://yourpluginsite.com';

	/**
	 * Product item name.
	 *
	 * @var string
	 */
	const ITEM_NAME = 'Social Feed Pro';

	/**
	 * License option key.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'sf_license_data';

	/**
	 * Weekly check cron hook.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'sf_weekly_license_check';

	/**
	 * Plan features mapping.
	 *
	 * @var array
	 */
	private static $plan_features = array(
		'free'     => array( 'instagram_basic', 'grid_layout' ),
		'personal' => array( 'instagram_basic', 'instagram_hashtag', 'grid_layout', 'list_layout', 'popup', 'custom_css' ),
		'plus'     => array( 'instagram_basic', 'instagram_hashtag', 'grid_layout', 'list_layout', 'masonry_layout', 'carousel_layout', 'popup', 'lightbox', 'custom_css', 'moderation', 'analytics' ),
		'agency'   => array( 'instagram_basic', 'instagram_hashtag', 'grid_layout', 'list_layout', 'masonry_layout', 'carousel_layout', 'popup', 'lightbox', 'custom_css', 'moderation', 'analytics', 'white_label', 'priority_support' ),
		'bundle'   => array( 'all' ),
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'weekly_license_check' ) );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'license_notices' ) );
		add_action( 'wp_ajax_sf_dismiss_license_notice', array( $this, 'dismiss_notice' ) );
	}

	/**
	 * Activate a license key.
	 *
	 * @param string $license_key License key to activate.
	 * @return array Response with success status and message.
	 */
	public static function activate( $license_key ) {
		$license_key = sanitize_text_field( trim( $license_key ) );

		if ( empty( $license_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please enter a license key.', 'social-feed' ),
			);
		}

		$response = self::api_request( 'activate_license', $license_key );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		if ( 'valid' === $response->license ) {
			$license_data = array(
				'key'            => SF_Helpers::sf_encrypt( $license_key ),
				'status'         => 'valid',
				'expires'        => $response->expires ?? '',
				'plan'           => $response->price_id ?? 'personal',
				'site_count'     => $response->site_count ?? 1,
				'license_limit'  => $response->license_limit ?? 1,
				'activations'    => $response->activations_left ?? 0,
				'customer_name'  => $response->customer_name ?? '',
				'customer_email' => $response->customer_email ?? '',
				'last_checked'   => time(),
				'activated_at'   => time(),
			);

			update_option( self::OPTION_KEY, $license_data );
			self::schedule_weekly_check();

			return array(
				'success' => true,
				'message' => __( 'License activated successfully!', 'social-feed' ),
				'data'    => $license_data,
			);
		}

		$error_message = self::get_error_message( $response );

		return array(
			'success' => false,
			'message' => $error_message,
		);
	}

	/**
	 * Deactivate the current license.
	 *
	 * @param string $license_key License key to deactivate.
	 * @return array Response with success status and message.
	 */
	public static function deactivate( $license_key = '' ) {
		if ( empty( $license_key ) ) {
			$license_data = self::get_license_data();
			$license_key  = ! empty( $license_data['key'] ) ? SF_Helpers::sf_decrypt( $license_data['key'] ) : '';
		}

		if ( empty( $license_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'No license key found.', 'social-feed' ),
			);
		}

		$response = self::api_request( 'deactivate_license', $license_key );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		delete_option( self::OPTION_KEY );
		wp_clear_scheduled_hook( self::CRON_HOOK );

		return array(
			'success' => true,
			'message' => __( 'License deactivated successfully.', 'social-feed' ),
		);
	}

	/**
	 * Check license status with the API.
	 *
	 * @return array License status data.
	 */
	public static function check_license() {
		$license_data = self::get_license_data();

		if ( empty( $license_data['key'] ) ) {
			return array(
				'status'  => 'inactive',
				'message' => __( 'No license activated.', 'social-feed' ),
			);
		}

		$license_key = SF_Helpers::sf_decrypt( $license_data['key'] );
		$response    = self::api_request( 'check_license', $license_key );

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 'error',
				'message' => $response->get_error_message(),
			);
		}

		$previous_status = $license_data['status'] ?? '';

		$license_data['status']       = $response->license ?? 'invalid';
		$license_data['expires']      = $response->expires ?? '';
		$license_data['site_count']   = $response->site_count ?? 1;
		$license_data['license_limit'] = $response->license_limit ?? 1;
		$license_data['last_checked'] = time();

		update_option( self::OPTION_KEY, $license_data );

		if ( $previous_status === 'valid' && $license_data['status'] !== 'valid' ) {
			self::trigger_status_change_notice( $license_data['status'] );
		}

		return array(
			'status'  => $license_data['status'],
			'data'    => $license_data,
			'message' => self::get_status_message( $license_data['status'] ),
		);
	}

	/**
	 * Check if license is valid (no API call).
	 *
	 * @return bool Whether license is valid.
	 */
	public static function is_valid() {
		$license_data = self::get_license_data();

		if ( empty( $license_data['status'] ) || 'valid' !== $license_data['status'] ) {
			return false;
		}

		if ( ! empty( $license_data['expires'] ) && 'lifetime' !== $license_data['expires'] ) {
			$expires = strtotime( $license_data['expires'] );
			if ( $expires && $expires < time() ) {
				return false;
			}
		}

		$last_checked = $license_data['last_checked'] ?? 0;
		$week_ago     = time() - ( 7 * DAY_IN_SECONDS );

		if ( $last_checked < $week_ago ) {
			wp_schedule_single_event( time() + 60, 'sf_background_license_check' );
			add_action( 'sf_background_license_check', array( __CLASS__, 'check_license' ) );
		}

		return true;
	}

	/**
	 * Get saved license data.
	 *
	 * @return array License data.
	 */
	public static function get_license_data() {
		return get_option( self::OPTION_KEY, array() );
	}

	/**
	 * Schedule weekly license check.
	 */
	public static function schedule_weekly_check() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + WEEK_IN_SECONDS, 'weekly', self::CRON_HOOK );
		}
	}

	/**
	 * Weekly license check callback.
	 */
	public function weekly_license_check() {
		self::check_license();
	}

	/**
	 * Make API request to license server.
	 *
	 * @param string $action      API action.
	 * @param string $license_key License key.
	 * @return object|WP_Error Response object or error.
	 */
	private static function api_request( $action, $license_key ) {
		$api_params = array(
			'edd_action'  => $action,
			'license'     => $license_key,
			'item_name'   => rawurlencode( self::ITEM_NAME ),
			'url'         => home_url(),
			'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
		);

		$response = wp_remote_post(
			self::STORE_URL,
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'body'      => $api_params,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $response_code ) {
			return new WP_Error(
				'api_error',
				sprintf(
					/* translators: %d: HTTP response code */
					__( 'License server returned error code: %d', 'social-feed' ),
					$response_code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( empty( $data ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from license server.', 'social-feed' ) );
		}

		return $data;
	}

	/**
	 * Get human-readable error message.
	 *
	 * @param object $response API response.
	 * @return string Error message.
	 */
	private static function get_error_message( $response ) {
		$error = $response->error ?? '';

		switch ( $error ) {
			case 'expired':
				return __( 'Your license key has expired.', 'social-feed' );

			case 'disabled':
			case 'revoked':
				return __( 'Your license key has been revoked.', 'social-feed' );

			case 'missing':
				return __( 'Invalid license key.', 'social-feed' );

			case 'invalid':
			case 'site_inactive':
				return __( 'Your license key is not active for this URL.', 'social-feed' );

			case 'item_name_mismatch':
				return __( 'This license key is for a different product.', 'social-feed' );

			case 'no_activations_left':
				return __( 'Your license key has reached its activation limit.', 'social-feed' );

			default:
				return __( 'An error occurred. Please try again.', 'social-feed' );
		}
	}

	/**
	 * Get status message.
	 *
	 * @param string $status License status.
	 * @return string Status message.
	 */
	private static function get_status_message( $status ) {
		switch ( $status ) {
			case 'valid':
				return __( 'License is active and valid.', 'social-feed' );

			case 'expired':
				return __( 'Your license has expired. Please renew to continue receiving updates.', 'social-feed' );

			case 'disabled':
			case 'revoked':
				return __( 'Your license has been revoked.', 'social-feed' );

			case 'invalid':
				return __( 'Your license key is invalid.', 'social-feed' );

			case 'inactive':
			case 'site_inactive':
				return __( 'Your license is not active for this site.', 'social-feed' );

			default:
				return __( 'Unknown license status.', 'social-feed' );
		}
	}

	/**
	 * Trigger admin notice on status change.
	 *
	 * @param string $new_status New license status.
	 */
	private static function trigger_status_change_notice( $new_status ) {
		set_transient( 'sf_license_status_changed', $new_status, DAY_IN_SECONDS );
	}

	/**
	 * Check for plugin updates.
	 *
	 * @param object $transient Update transient.
	 * @return object Modified transient.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		if ( ! self::is_valid() ) {
			return $transient;
		}

		$license_data = self::get_license_data();
		$license_key  = ! empty( $license_data['key'] ) ? SF_Helpers::sf_decrypt( $license_data['key'] ) : '';

		$response = wp_remote_post(
			self::STORE_URL,
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'body'      => array(
					'edd_action' => 'get_version',
					'license'    => $license_key,
					'item_name'  => rawurlencode( self::ITEM_NAME ),
					'url'        => home_url(),
					'version'    => SF_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $transient;
		}

		$version_info = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $version_info ) || empty( $version_info->new_version ) ) {
			return $transient;
		}

		if ( version_compare( SF_VERSION, $version_info->new_version, '<' ) ) {
			$plugin_slug = plugin_basename( SF_PLUGIN_PATH . 'social-feed.php' );

			$transient->response[ $plugin_slug ] = (object) array(
				'slug'         => 'social-feed',
				'plugin'       => $plugin_slug,
				'new_version'  => $version_info->new_version,
				'url'          => $version_info->url ?? self::STORE_URL,
				'package'      => $version_info->package ?? '',
				'icons'        => array(
					'1x' => $version_info->icons->{'1x'} ?? '',
					'2x' => $version_info->icons->{'2x'} ?? '',
				),
				'banners'      => array(
					'low'  => $version_info->banners->low ?? '',
					'high' => $version_info->banners->high ?? '',
				),
				'tested'       => $version_info->tested ?? '',
				'requires_php' => $version_info->requires_php ?? '7.4',
				'requires'     => $version_info->requires ?? '5.8',
			);
		}

		return $transient;
	}

	/**
	 * Plugin info for updates screen.
	 *
	 * @param false|object|array $result Result.
	 * @param string             $action Action.
	 * @param object             $args   Arguments.
	 * @return false|object Modified result.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || 'social-feed' !== $args->slug ) {
			return $result;
		}

		$license_data = self::get_license_data();
		$license_key  = ! empty( $license_data['key'] ) ? SF_Helpers::sf_decrypt( $license_data['key'] ) : '';

		$response = wp_remote_post(
			self::STORE_URL,
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'body'      => array(
					'edd_action' => 'get_version',
					'license'    => $license_key,
					'item_name'  => rawurlencode( self::ITEM_NAME ),
					'url'        => home_url(),
					'version'    => SF_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $result;
		}

		$version_info = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $version_info ) ) {
			return $result;
		}

		return (object) array(
			'name'          => $version_info->name ?? self::ITEM_NAME,
			'slug'          => 'social-feed',
			'version'       => $version_info->new_version ?? SF_VERSION,
			'author'        => $version_info->author ?? '',
			'author_profile' => $version_info->author_profile ?? '',
			'requires'      => $version_info->requires ?? '5.8',
			'tested'        => $version_info->tested ?? '',
			'requires_php'  => $version_info->requires_php ?? '7.4',
			'sections'      => array(
				'description'  => $version_info->sections->description ?? '',
				'installation' => $version_info->sections->installation ?? '',
				'changelog'    => $version_info->sections->changelog ?? '',
			),
			'download_link' => $version_info->package ?? '',
			'banners'       => array(
				'low'  => $version_info->banners->low ?? '',
				'high' => $version_info->banners->high ?? '',
			),
		);
	}

	/**
	 * Display license admin notices.
	 */
	public function license_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'social-feed' ) === false ) {
			return;
		}

		$license_data = self::get_license_data();

		if ( empty( $license_data['status'] ) || 'valid' !== $license_data['status'] ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( ! empty( $license_data['expires'] ) && 'lifetime' !== $license_data['expires'] ) {
			$expires      = strtotime( $license_data['expires'] );
			$now          = time();
			$days_left    = ceil( ( $expires - $now ) / DAY_IN_SECONDS );
			$days_expired = ceil( ( $now - $expires ) / DAY_IN_SECONDS );

			if ( $days_left <= 7 && $days_left > 0 ) {
				$dismissed = get_user_meta( $user_id, 'sf_notice_expiring_dismissed', true );
				if ( empty( $dismissed ) || $dismissed < ( time() - DAY_IN_SECONDS ) ) {
					$this->render_notice(
						'warning',
						'expiring',
						sprintf(
							/* translators: %d: Days until expiry */
							_n(
								'Your Social Feed Pro license expires in %d day. <a href="%s" target="_blank">Renew now</a> to continue receiving updates and support.',
								'Your Social Feed Pro license expires in %d days. <a href="%s" target="_blank">Renew now</a> to continue receiving updates and support.',
								$days_left,
								'social-feed'
							),
							$days_left,
							esc_url( self::STORE_URL . '/checkout/?edd_license_key=' . urlencode( SF_Helpers::sf_decrypt( $license_data['key'] ) ) )
						)
					);
				}
			} elseif ( $days_left <= 0 && $days_expired < 30 ) {
				$dismissed = get_user_meta( $user_id, 'sf_notice_expired_dismissed', true );
				if ( empty( $dismissed ) || $dismissed < ( time() - WEEK_IN_SECONDS ) ) {
					$this->render_notice(
						'error',
						'expired',
						sprintf(
							/* translators: %s: Renew URL */
							__( 'Your Social Feed Pro license has expired. <a href="%s" target="_blank">Renew now</a> to continue receiving updates and support.', 'social-feed' ),
							esc_url( self::STORE_URL . '/checkout/?edd_license_key=' . urlencode( SF_Helpers::sf_decrypt( $license_data['key'] ) ) )
						)
					);
				}
			} elseif ( $days_expired >= 30 ) {
				$dismissed = get_user_meta( $user_id, 'sf_notice_disabled_dismissed', true );
				if ( empty( $dismissed ) || $dismissed < ( time() - WEEK_IN_SECONDS ) ) {
					$this->render_notice(
						'error',
						'disabled',
						sprintf(
							/* translators: %s: Renew URL */
							__( 'Your Social Feed Pro license expired over 30 days ago. Pro features have been disabled. <a href="%s" target="_blank">Renew your license</a> to re-enable them.', 'social-feed' ),
							esc_url( self::STORE_URL . '/checkout/?edd_license_key=' . urlencode( SF_Helpers::sf_decrypt( $license_data['key'] ) ) )
						)
					);
				}
			}
		}
	}

	/**
	 * Render admin notice.
	 *
	 * @param string $type    Notice type (warning, error).
	 * @param string $notice  Notice identifier.
	 * @param string $message Notice message.
	 */
	private function render_notice( $type, $notice, $message ) {
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible sf-license-notice" data-notice="<?php echo esc_attr( $notice ); ?>">
			<p><strong>Social Feed Pro:</strong> <?php echo wp_kses_post( $message ); ?></p>
		</div>
		<script>
		jQuery(function($) {
			$('.sf-license-notice').on('click', '.notice-dismiss', function() {
				var notice = $(this).closest('.sf-license-notice').data('notice');
				$.post(ajaxurl, {
					action: 'sf_dismiss_license_notice',
					notice: notice,
					nonce: '<?php echo esc_js( wp_create_nonce( 'sf_dismiss_notice' ) ); ?>'
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle dismiss notice AJAX.
	 */
	public function dismiss_notice() {
		check_ajax_referer( 'sf_dismiss_notice', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}

		$notice  = isset( $_POST['notice'] ) ? sanitize_key( $_POST['notice'] ) : '';
		$user_id = get_current_user_id();

		if ( $notice ) {
			update_user_meta( $user_id, 'sf_notice_' . $notice . '_dismissed', time() );
		}

		wp_die();
	}

	/**
	 * Get days until license expiry.
	 *
	 * @return int|null Days until expiry or null if lifetime/no license.
	 */
	public static function get_days_until_expiry() {
		$license_data = self::get_license_data();

		if ( empty( $license_data['expires'] ) || 'lifetime' === $license_data['expires'] ) {
			return null;
		}

		$expires = strtotime( $license_data['expires'] );
		$now     = time();

		return ceil( ( $expires - $now ) / DAY_IN_SECONDS );
	}

	/**
	 * Clear all license data on plugin deactivation.
	 */
	public static function clear_data() {
		delete_option( self::OPTION_KEY );
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}

/**
 * Check if Pro version is active.
 *
 * @return bool Whether Pro license is valid.
 */
function sf_is_pro() {
	return SF_License::is_valid();
}

/**
 * Get current license plan.
 *
 * @return string Plan slug (free, personal, plus, agency, bundle).
 */
function sf_get_plan() {
	if ( ! sf_is_pro() ) {
		return 'free';
	}

	$license_data = SF_License::get_license_data();

	$plan_map = array(
		'1' => 'personal',
		'2' => 'plus',
		'3' => 'agency',
		'4' => 'bundle',
	);

	$price_id = $license_data['plan'] ?? '1';

	return $plan_map[ $price_id ] ?? 'personal';
}

/**
 * Check if a specific Pro feature is available.
 *
 * @param string $feature     Feature name to check.
 * @param bool   $show_notice Whether to show upgrade notice if not available.
 * @return bool Whether feature is available.
 */
function sf_pro_feature( $feature, $show_notice = false ) {
	$plan = sf_get_plan();

	if ( 'free' === $plan ) {
		if ( $show_notice ) {
			sf_show_upgrade_notice( $feature );
		}
		return false;
	}

	$plan_features = array(
		'free'     => array( 'instagram_basic', 'grid_layout' ),
		'personal' => array( 'instagram_basic', 'instagram_hashtag', 'grid_layout', 'list_layout', 'popup', 'custom_css' ),
		'plus'     => array( 'instagram_basic', 'instagram_hashtag', 'grid_layout', 'list_layout', 'masonry_layout', 'carousel_layout', 'popup', 'lightbox', 'custom_css', 'moderation', 'analytics' ),
		'agency'   => array( 'instagram_basic', 'instagram_hashtag', 'grid_layout', 'list_layout', 'masonry_layout', 'carousel_layout', 'popup', 'lightbox', 'custom_css', 'moderation', 'analytics', 'white_label', 'priority_support' ),
		'bundle'   => array( 'all' ),
	);

	$features = $plan_features[ $plan ] ?? array();

	if ( in_array( 'all', $features, true ) || in_array( $feature, $features, true ) ) {
		return true;
	}

	if ( $show_notice ) {
		sf_show_upgrade_notice( $feature );
	}

	return false;
}

/**
 * Show upgrade notice for a feature.
 *
 * @param string $feature Feature name.
 */
function sf_show_upgrade_notice( $feature ) {
	$feature_names = array(
		'instagram_hashtag' => __( 'Instagram Hashtag Feeds', 'social-feed' ),
		'masonry_layout'    => __( 'Masonry Layout', 'social-feed' ),
		'carousel_layout'   => __( 'Carousel Layout', 'social-feed' ),
		'lightbox'          => __( 'Lightbox Popup', 'social-feed' ),
		'moderation'        => __( 'Content Moderation', 'social-feed' ),
		'analytics'         => __( 'Feed Analytics', 'social-feed' ),
		'white_label'       => __( 'White Label', 'social-feed' ),
		'priority_support'  => __( 'Priority Support', 'social-feed' ),
	);

	$feature_name = $feature_names[ $feature ] ?? $feature;

	printf(
		'<span class="sf-pro-badge" title="%s">%s</span>',
		esc_attr(
			sprintf(
				/* translators: %s: Feature name */
				__( '%s requires a Pro license. Click to upgrade.', 'social-feed' ),
				$feature_name
			)
		),
		esc_html__( 'PRO', 'social-feed' )
	);
}
