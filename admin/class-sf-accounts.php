+<?php
/**
 * Connected Accounts page for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Accounts
 */
class SF_Accounts {

	/**
	 * Render the connected accounts page.
	 */
	public static function render() {
		?>
		<div class="wrap sf-admin-wrap sf-accounts-wrap">
			<?php self::render_content(); ?>
		</div>
		<?php
	}

	/**
	 * Render the accounts page content (without wrapper).
	 */
	public static function render_content() {
		$args = array();
		if ( ! current_user_can( 'manage_options' ) ) {
			$args['wp_user_id'] = get_current_user_id();
		}
		$accounts = SF_Database::get_all_accounts( $args );

		// OAuth callback notices.
		if ( isset( $_GET['sf_connected'] ) && '1' === $_GET['sf_connected'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Instagram account connected successfully!', 'social-feed' ) . '</p></div>';
		}
		if ( isset( $_GET['sf_error'] ) && '1' === $_GET['sf_error'] ) {
			$msg = isset( $_GET['sf_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['sf_msg'] ) ) : __( 'An error occurred during connection.', 'social-feed' );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}

		// Prompt to configure API credentials if missing.
		$settings   = get_option( 'sf_settings', array() );
		$settings   = is_array( $settings ) ? wp_parse_args( $settings, array( 'instagram_app_id' => '', 'instagram_app_secret' => '' ) ) : array();
		$app_id     = ! empty( $settings['instagram_app_id'] ) ? trim( (string) $settings['instagram_app_id'] ) : '';
		$app_secret = ! empty( $settings['instagram_app_secret'] ) ? trim( (string) $settings['instagram_app_secret'] ) : '';
		if ( empty( $app_id ) || empty( $app_secret ) ) {
			$settings_url = admin_url( 'admin.php?page=social-feed-settings&tab=general' );
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'To connect Instagram, add your App ID and App Secret first.', 'social-feed' ),
				esc_url( $settings_url ),
				esc_html__( 'Go to Settings', 'social-feed' )
			);
		} else {
			$redirect_uri = admin_url( 'admin.php' );
			echo '<div class="notice notice-info is-dismissible" style="margin-bottom:12px"><p><strong>' . esc_html__( 'Meta redirect URI (add exactly to your app):', 'social-feed' ) . '</strong><br><code style="word-break:break-all">' . esc_html( $redirect_uri ) . '</code></p></div>';
		}

		$oauth_init_url = ( ! empty( $app_id ) && ! empty( $app_secret ) )
			? add_query_arg(
				array(
					'page'         => 'social-feed-create',
					'sf_oauth_init' => '1',
					'_wpnonce'  => wp_create_nonce( 'sf_oauth_init' ),
				),
				admin_url( 'admin.php' )
			)
			: admin_url( 'admin.php?page=social-feed-settings&tab=general' );
		?>
		<div class="sf-accounts-wrap">
		<!-- Back Button -->
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=social-feed' ) ); ?>" class="sf-back-btn">
			<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
			<span><?php esc_html_e( 'Back to Dashboard', 'social-feed' ); ?></span>
		</a>

		<div class="sf-accounts-header">
			<?php if ( ! empty( $accounts ) ) : ?>
				<a href="<?php echo esc_url( $oauth_init_url ); ?>" class="button button-primary sf-connect-account-btn">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Connect New Account', 'social-feed' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<?php if ( empty( $accounts ) ) : ?>
			<!-- Empty State - Instagram branded -->
			<div class="sf-empty-state-large sf-accounts-empty sf-instagram-empty">
				<div class="sf-empty-instagram-logo">
					<?php echo self::get_instagram_logo_svg( 80 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<h2><?php esc_html_e( 'Connect your Instagram account', 'social-feed' ); ?></h2>
				<p><?php esc_html_e( 'Link your Instagram Business or Creator account to display your posts, reels, and profile on your website. You\'ll authorize once through Facebook — then your feed updates automatically.', 'social-feed' ); ?></p>
				<a href="<?php echo esc_url( $oauth_init_url ); ?>" class="button button-primary button-hero sf-connect-account-btn">
					<?php echo self::get_instagram_logo_svg( 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php esc_html_e( 'Connect Instagram Account', 'social-feed' ); ?></span>
				</a>
			</div>
		<?php else : ?>
			<!-- Accounts Grid -->
			<div class="sf-accounts-grid">
				<?php foreach ( $accounts as $account ) : ?>
					<?php self::render_account_card( $account ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		</div><!-- .sf-accounts-wrap -->
		<?php
	}

	/**
	 * Render a single account card.
	 *
	 * @param array $account Account data.
	 */
	private static function render_account_card( $account ) {
		$status       = self::get_account_status( $account );
		$feeds_count  = self::get_feeds_using_account( $account['id'] );
		$platform     = $account['platform'];
		$expiry_date  = $account['token_expires'] ? date_i18n( get_option( 'date_format' ), strtotime( $account['token_expires'] ) ) : __( 'Never', 'social-feed' );
		$wp_user_id   = isset( $account['wp_user_id'] ) ? (int) $account['wp_user_id'] : 0;
		$connected_by = $wp_user_id ? get_userdata( $wp_user_id ) : null;
		?>
		<div class="sf-account-card sf-account-<?php echo esc_attr( $status['type'] ); ?>" data-account-id="<?php echo esc_attr( $account['id'] ); ?>">
			<div class="sf-account-card-header">
				<?php echo self::get_platform_icon( $platform ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="sf-account-status sf-status-<?php echo esc_attr( $status['type'] ); ?>">
					<?php echo esc_html( $status['label'] ); ?>
				</span>
			</div>

			<div class="sf-account-card-body">
				<div class="sf-account-avatar">
					<?php if ( ! empty( $account['profile_pic'] ) ) : ?>
						<img src="<?php echo esc_url( $account['profile_pic'] ); ?>" alt="">
					<?php else : ?>
						<span class="sf-avatar-placeholder"><?php echo esc_html( strtoupper( substr( $account['account_name'], 0, 1 ) ) ); ?></span>
					<?php endif; ?>
				</div>

				<h3 class="sf-account-name">@<?php echo esc_html( $account['account_name'] ); ?></h3>

				<span class="sf-platform-badge sf-platform-<?php echo esc_attr( $platform ); ?>">
					<?php echo esc_html( ucfirst( $platform ) ); ?>
				</span>

				<?php if ( ! empty( $account['last_error'] ) && 'error' === $status['type'] ) : ?>
					<p class="sf-account-error"><?php echo esc_html( $account['last_error'] ); ?></p>
				<?php endif; ?>
			</div>

			<div class="sf-account-card-meta">
				<div class="sf-meta-item">
					<span class="sf-meta-label"><?php esc_html_e( 'Token Expires', 'social-feed' ); ?></span>
					<span class="sf-meta-value"><?php echo esc_html( $expiry_date ); ?></span>
				</div>
				<div class="sf-meta-item">
					<span class="sf-meta-label"><?php esc_html_e( 'Feeds Using', 'social-feed' ); ?></span>
					<span class="sf-meta-value"><?php echo esc_html( $feeds_count ); ?></span>
				</div>
				<?php if ( $connected_by && current_user_can( 'manage_options' ) ) : ?>
				<div class="sf-meta-item">
					<span class="sf-meta-label"><?php esc_html_e( 'Connected By', 'social-feed' ); ?></span>
					<span class="sf-meta-value"><?php echo esc_html( $connected_by->display_name ); ?></span>
				</div>
				<?php endif; ?>
			</div>

			<div class="sf-account-card-actions">
				<button type="button" class="button sf-reconnect-account" data-account-id="<?php echo esc_attr( $account['id'] ); ?>" data-platform="<?php echo esc_attr( $platform ); ?>">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Reconnect', 'social-feed' ); ?>
				</button>
				<button type="button" class="button sf-delete-account" data-account-id="<?php echo esc_attr( $account['id'] ); ?>" data-feeds="<?php echo esc_attr( $feeds_count ); ?>">
					<span class="dashicons dashicons-trash"></span>
					<?php esc_html_e( 'Delete', 'social-feed' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Get account connection status.
	 *
	 * @param array $account Account data.
	 * @return array
	 */
	private static function get_account_status( $account ) {
		if ( ! $account['is_connected'] || ! empty( $account['last_error'] ) ) {
			return array(
				'type'  => 'error',
				'label' => __( 'Error', 'social-feed' ),
			);
		}

		if ( ! empty( $account['token_expires'] ) ) {
			$expires = strtotime( $account['token_expires'] );
			$now     = time();

			if ( $expires < $now ) {
				return array(
					'type'  => 'error',
					'label' => __( 'Expired', 'social-feed' ),
				);
			}

			$days_until = ( $expires - $now ) / DAY_IN_SECONDS;
			if ( $days_until <= 7 ) {
				return array(
					'type'  => 'warning',
					'label' => __( 'Expiring Soon', 'social-feed' ),
				);
			}
		}

		return array(
			'type'  => 'success',
			'label' => __( 'Connected', 'social-feed' ),
		);
	}

	/**
	 * Get number of feeds using an account.
	 *
	 * @param int $account_id Account ID.
	 * @return int
	 */
	private static function get_feeds_using_account( $account_id ) {
		$feeds = SF_Database::get_all_feeds( array( 'limit' => 1000 ) );
		$count = 0;

		foreach ( $feeds as $feed ) {
			if ( (int) $feed['account_id'] === (int) $account_id ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Get Instagram logo SVG (official-style icon).
	 *
	 * @param int $size Width and height in pixels.
	 * @return string SVG HTML.
	 */
	private static function get_instagram_logo_svg( $size = 24 ) {
		$size = max( 16, min( 120, (int) $size ) );
		return '<svg class="sf-icon-instagram" viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" aria-hidden="true"><path fill="currentColor" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>';
	}

	/**
	 * Get platform icon HTML.
	 *
	 * @param string $platform Platform slug.
	 * @return string
	 */
	private static function get_platform_icon( $platform ) {
		if ( 'instagram' !== $platform ) {
			return '';
		}
		return '<div class="sf-platform-icon-large sf-icon-instagram">' . self::get_instagram_logo_svg( 32 ) . '</div>';
	}

	/**
	 * Get OAuth login URL for Instagram.
	 *
	 * @param string $platform Platform slug (only 'instagram' supported).
	 * @return string|WP_Error OAuth URL or error.
	 */
	public static function get_oauth_url( $platform ) {
		if ( 'instagram' !== $platform ) {
			return new WP_Error( 'unsupported', __( 'Only Instagram is supported.', 'social-feed' ) );
		}
		if ( class_exists( 'SF_Instagram' ) ) {
			return SF_Instagram::get_login_url();
		}
		return new WP_Error( 'missing_class', __( 'Instagram OAuth is not available.', 'social-feed' ) );
	}
}
