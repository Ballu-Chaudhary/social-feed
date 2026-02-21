<?php
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
		$accounts = SF_Database::get_all_accounts();
		?>
		<div class="wrap sf-admin-wrap sf-accounts-wrap">
			<div class="sf-accounts-header">
				<h1 class="sf-admin-title"><?php esc_html_e( 'Connected Accounts', 'social-feed' ); ?></h1>
				<button type="button" class="button button-primary sf-connect-account-btn">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Connect New Account', 'social-feed' ); ?>
				</button>
			</div>

			<?php if ( empty( $accounts ) ) : ?>
				<!-- Empty State -->
				<div class="sf-empty-state-large sf-accounts-empty">
					<div class="sf-empty-illustration">
						<svg width="120" height="120" viewBox="0 0 120 120" fill="none">
							<circle cx="60" cy="60" r="50" fill="#f0f0f1"/>
							<circle cx="60" cy="45" r="18" fill="#c3c4c7"/>
							<path d="M35 85c0-13.8 11.2-25 25-25s25 11.2 25 25" stroke="#c3c4c7" stroke-width="8" fill="none"/>
							<circle cx="90" cy="85" r="18" fill="#2271b1"/>
							<path d="M90 77v16M82 85h16" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
						</svg>
					</div>
					<h2><?php esc_html_e( 'No accounts connected', 'social-feed' ); ?></h2>
					<p><?php esc_html_e( 'Connect your social media accounts to start displaying feeds on your website.', 'social-feed' ); ?></p>
					<button type="button" class="button button-primary button-hero sf-connect-account-btn">
						<?php esc_html_e( 'Connect Your First Account', 'social-feed' ); ?>
					</button>
				</div>
			<?php else : ?>
				<!-- Accounts Grid -->
				<div class="sf-accounts-grid">
					<?php foreach ( $accounts as $account ) : ?>
						<?php self::render_account_card( $account ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Connect Account Modal -->
			<?php self::render_connect_modal(); ?>
		</div>
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
	 * Get platform icon HTML.
	 *
	 * @param string $platform Platform slug.
	 * @return string
	 */
	private static function get_platform_icon( $platform ) {
		$icons = array(
			'instagram' => '<div class="sf-platform-icon-large sf-icon-instagram"><svg viewBox="0 0 24 24" width="32" height="32"><path fill="currentColor" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></div>',
			'youtube'   => '<div class="sf-platform-icon-large sf-icon-youtube"><svg viewBox="0 0 24 24" width="32" height="32"><path fill="currentColor" d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></div>',
			'facebook'  => '<div class="sf-platform-icon-large sf-icon-facebook"><svg viewBox="0 0 24 24" width="32" height="32"><path fill="currentColor" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></div>',
			'tiktok'    => '<div class="sf-platform-icon-large sf-icon-tiktok"><svg viewBox="0 0 24 24" width="32" height="32"><path fill="currentColor" d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></div>',
			'twitter'   => '<div class="sf-platform-icon-large sf-icon-twitter"><svg viewBox="0 0 24 24" width="32" height="32"><path fill="currentColor" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></div>',
		);

		return isset( $icons[ $platform ] ) ? $icons[ $platform ] : '';
	}

	/**
	 * Render the connect account modal.
	 */
	private static function render_connect_modal() {
		?>
		<div class="sf-modal sf-connect-modal" id="sf-connect-modal">
			<div class="sf-modal-overlay"></div>
			<div class="sf-modal-container">
				<div class="sf-modal-header">
					<h2><?php esc_html_e( 'Connect New Account', 'social-feed' ); ?></h2>
					<button type="button" class="sf-modal-close">&times;</button>
				</div>

				<div class="sf-modal-body">
					<!-- Step 1: Platform Selection -->
					<div class="sf-connect-step sf-step-platforms active" data-step="1">
						<p class="sf-step-desc"><?php esc_html_e( 'Select a platform to connect:', 'social-feed' ); ?></p>

						<div class="sf-platform-grid">
							<button type="button" class="sf-platform-card" data-platform="instagram">
								<div class="sf-platform-icon-large sf-icon-instagram">
									<svg viewBox="0 0 24 24" width="40" height="40"><path fill="currentColor" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
								</div>
								<span class="sf-platform-name"><?php esc_html_e( 'Instagram', 'social-feed' ); ?></span>
							</button>

							<button type="button" class="sf-platform-card" data-platform="youtube">
								<div class="sf-platform-icon-large sf-icon-youtube">
									<svg viewBox="0 0 24 24" width="40" height="40"><path fill="currentColor" d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
								</div>
								<span class="sf-platform-name"><?php esc_html_e( 'YouTube', 'social-feed' ); ?></span>
							</button>

							<button type="button" class="sf-platform-card" data-platform="facebook">
								<div class="sf-platform-icon-large sf-icon-facebook">
									<svg viewBox="0 0 24 24" width="40" height="40"><path fill="currentColor" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
								</div>
								<span class="sf-platform-name"><?php esc_html_e( 'Facebook', 'social-feed' ); ?></span>
							</button>
						</div>
					</div>

					<!-- Step 2: Connection Instructions -->
					<div class="sf-connect-step sf-step-connect" data-step="2">
						<button type="button" class="sf-back-btn">&larr; <?php esc_html_e( 'Back', 'social-feed' ); ?></button>

						<div class="sf-connect-platform-header">
							<div class="sf-selected-platform-icon"></div>
							<h3 class="sf-selected-platform-name"></h3>
						</div>

						<div class="sf-connect-instructions">
							<div class="sf-instructions-instagram">
								<p><?php esc_html_e( 'Connect your Instagram account to display your feed. You\'ll need to authorize access through Facebook (Instagram\'s parent company).', 'social-feed' ); ?></p>
								<ul>
									<li><?php esc_html_e( 'Works with Business and Creator accounts', 'social-feed' ); ?></li>
									<li><?php esc_html_e( 'Displays posts, reels, and stories', 'social-feed' ); ?></li>
									<li><?php esc_html_e( 'Auto-refreshes every hour', 'social-feed' ); ?></li>
								</ul>
							</div>

							<div class="sf-instructions-youtube">
								<p><?php esc_html_e( 'Connect your YouTube channel to display videos on your website.', 'social-feed' ); ?></p>
								<ul>
									<li><?php esc_html_e( 'Display channel videos or playlists', 'social-feed' ); ?></li>
									<li><?php esc_html_e( 'Shows video thumbnails and stats', 'social-feed' ); ?></li>
									<li><?php esc_html_e( 'Embed videos in popup or inline', 'social-feed' ); ?></li>
								</ul>
							</div>

							<div class="sf-instructions-facebook">
								<p><?php esc_html_e( 'Connect your Facebook Page to display posts on your website.', 'social-feed' ); ?></p>
								<ul>
									<li><?php esc_html_e( 'Works with Facebook Pages only', 'social-feed' ); ?></li>
									<li><?php esc_html_e( 'Display posts, photos, and events', 'social-feed' ); ?></li>
									<li><?php esc_html_e( 'Requires Page admin access', 'social-feed' ); ?></li>
								</ul>
							</div>
						</div>

						<button type="button" class="button button-primary button-hero sf-oauth-connect-btn">
							<span class="sf-btn-icon"></span>
							<span class="sf-btn-text"><?php esc_html_e( 'Connect with', 'social-feed' ); ?> <span class="sf-platform-text"></span></span>
						</button>

						<p class="sf-connect-note">
							<?php esc_html_e( 'A popup window will open for authentication. Please allow popups for this site.', 'social-feed' ); ?>
						</p>
					</div>

					<!-- Step 3: Connecting... -->
					<div class="sf-connect-step sf-step-loading" data-step="3">
						<div class="sf-connecting-animation">
							<span class="spinner is-active"></span>
						</div>
						<h3><?php esc_html_e( 'Connecting...', 'social-feed' ); ?></h3>
						<p><?php esc_html_e( 'Please complete the authorization in the popup window.', 'social-feed' ); ?></p>
					</div>

					<!-- Step 4: Success -->
					<div class="sf-connect-step sf-step-success" data-step="4">
						<div class="sf-success-icon">
							<span class="dashicons dashicons-yes-alt"></span>
						</div>
						<h3><?php esc_html_e( 'Account Connected!', 'social-feed' ); ?></h3>
						<p class="sf-connected-account-name"></p>
						<button type="button" class="button button-primary sf-modal-done-btn"><?php esc_html_e( 'Done', 'social-feed' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get OAuth URL for a platform.
	 *
	 * Uses platform-specific auth classes when available.
	 *
	 * @param string $platform Platform slug.
	 * @return string|WP_Error OAuth URL or error.
	 */
	public static function get_oauth_url( $platform ) {
		switch ( $platform ) {
			case 'instagram':
				if ( class_exists( 'SF_Instagram_Auth' ) ) {
					return SF_Instagram_Auth::get_auth_url();
				}
				break;

			case 'youtube':
				if ( class_exists( 'SF_YouTube_Auth' ) ) {
					return SF_YouTube_Auth::get_auth_url();
				}
				break;

			case 'facebook':
				if ( class_exists( 'SF_Facebook_Auth' ) ) {
					return SF_Facebook_Auth::get_auth_url();
				}
				break;
		}

		return self::get_generic_oauth_url( $platform );
	}

	/**
	 * Get generic OAuth URL for a platform.
	 *
	 * Fallback when platform-specific class is not available.
	 *
	 * @param string $platform Platform slug.
	 * @return string
	 */
	private static function get_generic_oauth_url( $platform ) {
		$redirect_uri = admin_url( 'admin.php?page=social-feed-accounts&sf_oauth_callback=1' );
		$state        = wp_create_nonce( 'sf_oauth_' . $platform );

		$settings = get_option( 'sf_settings', array() );

		switch ( $platform ) {
			case 'instagram':
				$app_id = isset( $settings['instagram_app_id'] ) ? $settings['instagram_app_id'] : '';
				return add_query_arg(
					array(
						'client_id'     => $app_id,
						'redirect_uri'  => urlencode( $redirect_uri ),
						'scope'         => 'user_profile,user_media',
						'response_type' => 'code',
						'state'         => $state,
					),
					'https://api.instagram.com/oauth/authorize'
				);

			case 'youtube':
				$client_id = isset( $settings['youtube_client_id'] ) ? $settings['youtube_client_id'] : '';
				return add_query_arg(
					array(
						'client_id'     => $client_id,
						'redirect_uri'  => urlencode( $redirect_uri ),
						'scope'         => urlencode( 'https://www.googleapis.com/auth/youtube.readonly' ),
						'response_type' => 'code',
						'access_type'   => 'offline',
						'prompt'        => 'consent',
						'state'         => $state,
					),
					'https://accounts.google.com/o/oauth2/v2/auth'
				);

			case 'facebook':
				$app_id = isset( $settings['facebook_app_id'] ) ? $settings['facebook_app_id'] : '';
				return add_query_arg(
					array(
						'client_id'     => $app_id,
						'redirect_uri'  => urlencode( $redirect_uri ),
						'scope'         => 'pages_show_list,pages_read_engagement',
						'response_type' => 'code',
						'state'         => $state,
					),
					'https://www.facebook.com/v18.0/dialog/oauth'
				);

			default:
				return '';
		}
	}
}
