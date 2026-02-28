<?php
/**
 * Admin menu and pages for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Admin
 */
class SF_Admin {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'social-feed';

	/**
	 * Current admin page hook.
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );

		require_once SF_PLUGIN_PATH . 'admin/class-sf-settings.php';
	}

	/**
	 * Get SVG icon for admin menu.
	 *
	 * @return string Base64 encoded SVG.
	 */
	private function get_menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#a7aaad"><path d="M10 0C4.477 0 0 4.477 0 10s4.477 10 10 10 10-4.477 10-10S15.523 0 10 0zm3.8 7.2l-4.5 5.5c-.15.18-.37.29-.6.3h-.05c-.22 0-.43-.09-.58-.25l-2.5-2.5c-.32-.32-.32-.83 0-1.15.32-.32.83-.32 1.15 0l1.87 1.87 3.93-4.82c.29-.35.81-.4 1.16-.12.35.29.41.81.12 1.17z"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Register admin menu pages.
	 */
	public function register_menu() {
		$capability = 'manage_options';

		$this->page_hook = add_menu_page(
			__( 'Social Feed', 'social-feed' ),
			__( 'Social Feed', 'social-feed' ),
			$capability,
			self::PAGE_SLUG,
			array( $this, 'render_dashboard' ),
			$this->get_menu_icon(),
			30
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Dashboard', 'social-feed' ),
			__( 'Dashboard', 'social-feed' ),
			$capability,
			self::PAGE_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'All Feeds', 'social-feed' ),
			__( 'All Feeds', 'social-feed' ),
			$capability,
			self::PAGE_SLUG . '-feeds',
			array( $this, 'render_all_feeds' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Create Feed', 'social-feed' ),
			__( 'Create Feed', 'social-feed' ),
			$capability,
			self::PAGE_SLUG . '-create',
			array( $this, 'render_create_feed' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Instagram Accounts', 'social-feed' ),
			__( 'Instagram Accounts', 'social-feed' ),
			$capability,
			self::PAGE_SLUG . '-accounts',
			array( $this, 'render_accounts' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Settings', 'social-feed' ),
			__( 'Settings', 'social-feed' ),
			$capability,
			self::PAGE_SLUG . '-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'License', 'social-feed' ),
			__( 'License', 'social-feed' ),
			$capability,
			self::PAGE_SLUG . '-license',
			array( $this, 'render_license' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Help & Support', 'social-feed' ),
			__( 'Help & Support', 'social-feed' ),
			$capability,
			self::PAGE_SLUG . '-help',
			array( $this, 'render_help' )
		);
	}

	/**
	 * Check if current page is a plugin admin page.
	 *
	 * @return bool
	 */
	private function is_plugin_page() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		return strpos( $screen->id, self::PAGE_SLUG ) !== false;
	}

	/**
	 * Add body class to admin pages.
	 *
	 * @param string $classes Existing body classes.
	 * @return string Modified body classes.
	 */
	public function add_body_class( $classes ) {
		if ( $this->is_plugin_page() ) {
			$classes .= ' sf-admin-page';

			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			if ( $page === self::PAGE_SLUG ) {
				$classes .= ' sf-dashboard-page';
			} elseif ( $page === self::PAGE_SLUG . '-feeds' ) {
				$classes .= ' sf-feeds-page';
			} elseif ( $page === self::PAGE_SLUG . '-create' ) {
				$classes .= ' sf-customizer-page';
			} elseif ( $page === self::PAGE_SLUG . '-accounts' ) {
				$classes .= ' sf-accounts-page';
			} elseif ( $page === self::PAGE_SLUG . '-settings' ) {
				$classes .= ' sf-settings-page';
			} elseif ( $page === self::PAGE_SLUG . '-license' ) {
				$classes .= ' sf-license-page';
			} elseif ( $page === self::PAGE_SLUG . '-help' ) {
				$classes .= ' sf-help-page';
			}
		}

		return $classes;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! $this->is_plugin_page() ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_style(
			'sf-admin',
			SF_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SF_VERSION
		);

		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_script(
			'sf-admin',
			SF_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			SF_VERSION,
			true
		);

		wp_localize_script(
			'sf-admin',
			'sfAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'sf_admin_nonce' ),
				'pluginUrl' => SF_PLUGIN_URL,
				'isPro'     => $this->is_pro(),
				'i18n'      => array(
					'confirm_delete'      => __( 'Are you sure you want to delete this feed?', 'social-feed' ),
					'confirm_bulk_delete' => __( 'Are you sure you want to delete the selected feeds?', 'social-feed' ),
					'copied'              => __( 'Copied to clipboard!', 'social-feed' ),
					'error'               => __( 'An error occurred. Please try again.', 'social-feed' ),
					'saving'              => __( 'Saving...', 'social-feed' ),
					'saved'                   => __( 'Saved!', 'social-feed' ),
					'loading'                 => __( 'Loading...', 'social-feed' ),
					'no_feeds'                => __( 'No feeds found.', 'social-feed' ),
					'cache_cleared'           => __( 'Cache cleared successfully!', 'social-feed' ),
					'select_action'           => __( 'Please select an action.', 'social-feed' ),
					'select_feed'             => __( 'Please select at least one feed.', 'social-feed' ),
					'popup_blocked'           => __( 'Please allow popups for this site.', 'social-feed' ),
					'confirm_delete_account'  => __( 'Are you sure you want to delete this account?', 'social-feed' ),
					'feeds_using_account'     => __( 'This account is used by %d feed(s). They will be disconnected.', 'social-feed' ),
					'no_accounts'             => __( 'No accounts connected', 'social-feed' ),
					'connect_first'           => __( 'Connect your social media accounts to start displaying feeds on your website.', 'social-feed' ),
					'connect_first_account'   => __( 'Connect Your First Account', 'social-feed' ),
					'saving'                  => __( 'Saving...', 'social-feed' ),
					'configured'              => __( 'Configured', 'social-feed' ),
					'not_configured'          => __( 'Not Configured', 'social-feed' ),
					'confirm_clear_cache'     => __( 'Are you sure you want to clear all cached data?', 'social-feed' ),
					'confirm_clear_logs'      => __( 'Are you sure you want to clear all logs?', 'social-feed' ),
					'enter_license'           => __( 'Please enter a license key.', 'social-feed' ),
					'activating'              => __( 'Activating...', 'social-feed' ),
					'activate'                => __( 'Activate', 'social-feed' ),
					'deactivating'            => __( 'Deactivating...', 'social-feed' ),
					'deactivate'              => __( 'Deactivate', 'social-feed' ),
					'checking'                => __( 'Checking...', 'social-feed' ),
					'check_license'           => __( 'Check License', 'social-feed' ),
					'confirm_deactivate'      => __( 'Are you sure you want to deactivate your license?', 'social-feed' ),
					'save_feed'               => __( 'Save Feed', 'social-feed' ),
					'unsaved_leave'           => __( 'You have unsaved changes. Are you sure you want to leave?', 'social-feed' ),
				),
			)
		);
	}

	/**
	 * Check if Pro version is active.
	 *
	 * @return bool
	 */
	private function is_pro() {
		return function_exists( 'sf_is_pro' ) && sf_is_pro();
	}

	/**
	 * Get dashboard statistics.
	 *
	 * @return array
	 */
	private function get_stats() {
		$feeds    = SF_Database::get_all_feeds();
		$accounts = SF_Database::get_all_accounts( array( 'is_connected' => 1 ) );

		$platforms = array();
		foreach ( $accounts as $account ) {
			$platforms[ $account['platform'] ] = true;
		}

		$license = SF_Database::get_active_license();
		$plan    = $license ? ucfirst( $license['plan'] ) : 'Free';

		return array(
			'total_feeds'        => count( $feeds ),
			'connected_accounts' => count( $accounts ),
			'active_platforms'   => count( $platforms ),
			'current_plan'       => $plan,
		);
	}

	/**
	 * Get recent error logs.
	 *
	 * @param int $limit Number of logs to return.
	 * @return array
	 */
	private function get_recent_errors( $limit = 5 ) {
		return SF_Database::get_all_logs(
			array(
				'log_type' => 'error',
				'orderby'  => 'created_at',
				'order'    => 'DESC',
				'limit'    => $limit,
			)
		);
	}

	/**
	 * Get platform icon HTML.
	 *
	 * @param string $platform Platform slug.
	 * @return string
	 */
	private function get_platform_icon( $platform ) {
		if ( 'instagram' !== $platform ) {
			return '';
		}
		return '<span class="sf-platform-icon sf-platform-instagram" title="' . esc_attr__( 'Instagram', 'social-feed' ) . '"><svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></span>';
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard() {
		$stats         = $this->get_stats();
		$recent_errors = $this->get_recent_errors();
		$is_pro        = $this->is_pro();
		?>
		<div class="wrap sf-admin-wrap sf-dashboard-wrap">
			<div class="sf-dashboard-header">
				<h1 class="sf-admin-title"><?php esc_html_e( 'Social Feed Dashboard', 'social-feed' ); ?></h1>
				<?php if ( ! $is_pro ) : ?>
				<a href="<?php echo esc_url( 'https://socialfeedplugin.com/pricing/' ); ?>" class="button button-primary sf-get-pro-btn" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Get Pro Now', 'social-feed' ); ?>
				</a>
				<?php endif; ?>
			</div>

			<div class="sf-dashboard-grid">
				<!-- Stats Section - 3 cards -->
				<div class="sf-stats-grid">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-feeds' ) ); ?>" class="sf-stat-card sf-stat-card--blue sf-stat-card--clickable">
						<span class="sf-stat-icon-wrap"><span class="dashicons dashicons-rss"></span></span>
						<div class="sf-stat-content">
							<span class="sf-stat-number"><?php echo esc_html( (string) $stats['total_feeds'] ); ?></span>
							<span class="sf-stat-label"><?php esc_html_e( 'Total Feeds', 'social-feed' ); ?></span>
						</div>
					</a>
					<div class="sf-stat-card sf-stat-card--green">
						<span class="sf-stat-icon-wrap"><span class="dashicons dashicons-admin-users"></span></span>
						<div class="sf-stat-content">
							<span class="sf-stat-number"><?php echo esc_html( (string) $stats['connected_accounts'] ); ?></span>
							<span class="sf-stat-label"><?php esc_html_e( 'Connected Accounts', 'social-feed' ); ?></span>
						</div>
					</div>
					<div class="sf-stat-card sf-stat-card--orange">
						<span class="sf-stat-icon-wrap"><span class="dashicons dashicons-awards"></span></span>
						<div class="sf-stat-content">
							<span class="sf-stat-number"><?php echo esc_html( $stats['current_plan'] ); ?></span>
							<span class="sf-stat-label"><?php esc_html_e( 'Current Plan', 'social-feed' ); ?></span>
						</div>
					</div>
				</div>

				<!-- Quick Actions -->
				<div class="sf-card sf-quick-actions">
					<h2 class="sf-card-title"><?php esc_html_e( 'Quick Actions', 'social-feed' ); ?></h2>
					<div class="sf-card-content">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-create' ) ); ?>" class="sf-action-btn button button-primary">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Create Feed', 'social-feed' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-accounts' ) ); ?>" class="sf-action-btn button button-secondary">
							<span class="dashicons dashicons-admin-links"></span>
							<?php esc_html_e( 'Connect Account', 'social-feed' ); ?>
						</a>
						<button type="button" class="sf-action-btn button button-secondary sf-clear-cache-btn">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Clear Cache', 'social-feed' ); ?>
						</button>
					</div>
				</div>

				<!-- Error Log -->
				<div class="sf-card sf-error-log">
					<h2 class="sf-card-title"><?php esc_html_e( 'Recent Errors', 'social-feed' ); ?></h2>
					<div class="sf-card-content">
						<?php if ( empty( $recent_errors ) ) : ?>
							<div class="sf-empty-state sf-success-state">
								<span class="dashicons dashicons-yes-alt"></span>
								<p><?php esc_html_e( 'No errors logged. Everything is running smoothly!', 'social-feed' ); ?></p>
							</div>
						<?php else : ?>
							<ul class="sf-error-list">
								<?php foreach ( $recent_errors as $error ) : ?>
									<li class="sf-error-item">
										<span class="sf-error-time"><?php echo esc_html( SF_Helpers::sf_time_ago( $error['created_at'] ) ); ?></span>
										<span class="sf-error-platform"><?php echo $this->get_platform_icon( $error['platform'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<span class="sf-error-message"><?php echo esc_html( $error['message'] ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render all feeds page.
	 */
	public function render_all_feeds() {
		$search_query = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$all_feeds = SF_Database::get_all_feeds(
			array(
				'orderby' => 'created_at',
				'order'   => 'DESC',
			)
		);

		$feeds = $all_feeds;
		if ( ! empty( $search_query ) ) {
			$feeds = array_filter(
				$feeds,
				function ( $feed ) use ( $search_query ) {
					return stripos( $feed['name'], $search_query ) !== false;
				}
			);
		}

		$total_feeds      = count( $all_feeds );
		$active_feeds     = count(
			array_filter(
				$all_feeds,
				function ( $f ) {
					return 'active' === $f['status'];
				}
			)
		);
		$connected_accounts = count( SF_Database::get_all_accounts( array( 'is_connected' => 1 ) ) );
		?>
		<div class="wrap sf-admin-wrap sf-feeds-wrap">
			<!-- Modern Header -->
			<div class="sf-feeds-header">
				<div class="sf-feeds-header-left">
					<h1 class="sf-feeds-title"><?php esc_html_e( 'All Feeds', 'social-feed' ); ?></h1>
					<p class="sf-feeds-subtitle"><?php esc_html_e( 'Manage and create your social media feeds', 'social-feed' ); ?></p>
				</div>
				<div class="sf-feeds-header-right">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-create' ) ); ?>" class="sf-btn-create-feed">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Create Feed', 'social-feed' ); ?>
					</a>
				</div>
			</div>

			<!-- Stats Row with Search -->
			<div class="sf-feeds-stats-row">
				<div class="sf-feeds-stats">
					<div class="sf-feeds-stat sf-feeds-stat--blue">
						<div class="sf-feeds-stat-icon">
							<span class="dashicons dashicons-rss"></span>
						</div>
						<div class="sf-feeds-stat-content">
							<span class="sf-feeds-stat-number"><?php echo esc_html( $total_feeds ); ?></span>
							<span class="sf-feeds-stat-label"><?php esc_html_e( 'Total Feeds', 'social-feed' ); ?></span>
						</div>
					</div>
					<div class="sf-feeds-stat sf-feeds-stat--green">
						<div class="sf-feeds-stat-icon">
							<span class="dashicons dashicons-yes-alt"></span>
						</div>
						<div class="sf-feeds-stat-content">
							<span class="sf-feeds-stat-number"><?php echo esc_html( $active_feeds ); ?></span>
							<span class="sf-feeds-stat-label"><?php esc_html_e( 'Active Feeds', 'social-feed' ); ?></span>
						</div>
					</div>
					<div class="sf-feeds-stat sf-feeds-stat--purple">
						<div class="sf-feeds-stat-icon">
							<span class="dashicons dashicons-admin-users"></span>
						</div>
						<div class="sf-feeds-stat-content">
							<span class="sf-feeds-stat-number"><?php echo esc_html( $connected_accounts ); ?></span>
							<span class="sf-feeds-stat-label"><?php esc_html_e( 'Accounts', 'social-feed' ); ?></span>
						</div>
					</div>
				</div>
				<?php if ( $total_feeds > 0 ) : ?>
				<form method="get" class="sf-feeds-search-form">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG . '-feeds' ); ?>">
					<div class="sf-search-input-wrap">
						<span class="dashicons dashicons-search"></span>
						<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search feeds...', 'social-feed' ); ?>" class="sf-search-input">
					</div>
				</form>
				<?php endif; ?>
			</div>

			<?php if ( empty( $feeds ) && empty( $search_query ) ) : ?>
				<!-- Empty State -->
				<div class="sf-feeds-empty-state">
					<div class="sf-feeds-empty-icon">
						<svg width="140" height="140" viewBox="0 0 140 140" fill="none" xmlns="http://www.w3.org/2000/svg">
							<circle cx="70" cy="70" r="65" fill="url(#emptyGrad)" fill-opacity="0.1"/>
							<rect x="25" y="30" width="35" height="35" rx="6" fill="#e0e0e0"/>
							<rect x="65" y="30" width="35" height="35" rx="6" fill="#e0e0e0"/>
							<rect x="25" y="70" width="35" height="35" rx="6" fill="#e0e0e0"/>
							<rect x="65" y="70" width="35" height="35" rx="6" fill="#e0e0e0"/>
							<rect x="105" y="30" width="8" height="75" rx="4" fill="#e0e0e0"/>
							<circle cx="105" cy="105" r="25" fill="url(#plusGrad)"/>
							<path d="M105 95v20M95 105h20" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
							<defs>
								<linearGradient id="emptyGrad" x1="0" y1="0" x2="140" y2="140" gradientUnits="userSpaceOnUse">
									<stop stop-color="#6366f1"/>
									<stop offset="1" stop-color="#8b5cf6"/>
								</linearGradient>
								<linearGradient id="plusGrad" x1="80" y1="80" x2="130" y2="130" gradientUnits="userSpaceOnUse">
									<stop stop-color="#6366f1"/>
									<stop offset="1" stop-color="#8b5cf6"/>
								</linearGradient>
							</defs>
						</svg>
					</div>
					<h2 class="sf-feeds-empty-title"><?php esc_html_e( 'No Feeds Yet', 'social-feed' ); ?></h2>
					<p class="sf-feeds-empty-desc"><?php esc_html_e( 'Start by creating your first social feed to display content from Instagram, YouTube, or Facebook.', 'social-feed' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-create' ) ); ?>" class="sf-btn-create-first">
						<?php esc_html_e( 'Create Your First Feed', 'social-feed' ); ?>
					</a>
					<a href="https://developer.wordpress.org/plugins/" target="_blank" rel="noopener noreferrer" class="sf-feeds-learn-link">
						<?php esc_html_e( 'Learn how it works', 'social-feed' ); ?> &rarr;
					</a>
				</div>
			<?php elseif ( empty( $feeds ) && ! empty( $search_query ) ) : ?>
				<!-- No Search Results -->
				<div class="sf-feeds-empty-state sf-feeds-no-results">
					<div class="sf-feeds-empty-icon">
						<span class="dashicons dashicons-search" style="font-size: 64px; width: 64px; height: 64px; color: #94a3b8;"></span>
					</div>
					<h2 class="sf-feeds-empty-title"><?php esc_html_e( 'No feeds found', 'social-feed' ); ?></h2>
					<p class="sf-feeds-empty-desc">
						<?php
						printf(
							/* translators: %s: search query */
							esc_html__( 'No feeds match "%s". Try a different search term.', 'social-feed' ),
							esc_html( $search_query )
						);
						?>
					</p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-feeds' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Clear Search', 'social-feed' ); ?>
					</a>
				</div>
			<?php else : ?>
				<!-- Feed Cards Grid -->
				<div class="sf-feeds-grid">
					<?php foreach ( $feeds as $feed ) : ?>
						<?php
						$is_active    = 'active' === $feed['status'];
						$status_class = $is_active ? 'sf-feed-status--active' : 'sf-feed-status--paused';
						$status_text  = $is_active ? __( 'Active', 'social-feed' ) : __( 'Paused', 'social-feed' );
						$created_date = date_i18n( get_option( 'date_format' ), strtotime( $feed['created_at'] ) );
						?>
						<div class="sf-feed-card" data-feed-id="<?php echo esc_attr( $feed['id'] ); ?>">
							<div class="sf-feed-card-header">
								<div class="sf-feed-card-platform">
									<?php echo $this->get_platform_icon( $feed['platform'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
								<span class="sf-feed-card-status <?php echo esc_attr( $status_class ); ?>">
									<?php echo esc_html( $status_text ); ?>
								</span>
							</div>
							<div class="sf-feed-card-body">
								<h3 class="sf-feed-card-name">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-create&feed_id=' . $feed['id'] ) ); ?>">
										<?php echo esc_html( $feed['name'] ); ?>
									</a>
								</h3>
								<p class="sf-feed-card-meta">
									<span class="sf-feed-card-type"><?php echo esc_html( ucfirst( $feed['feed_type'] ) ); ?></span>
									<span class="sf-feed-card-sep">&bull;</span>
									<span class="sf-feed-card-date"><?php echo esc_html( $created_date ); ?></span>
								</p>
								<div class="sf-feed-card-shortcode">
									<code>[social_feed id="<?php echo esc_attr( $feed['id'] ); ?>"]</code>
									<button type="button" class="sf-copy-btn" data-copy="[social_feed id=&quot;<?php echo esc_attr( $feed['id'] ); ?>&quot;]" title="<?php esc_attr_e( 'Copy shortcode', 'social-feed' ); ?>">
										<span class="dashicons dashicons-admin-page"></span>
									</button>
								</div>
							</div>
							<div class="sf-feed-card-actions">
								<div class="sf-feed-card-actions-left">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-create&feed_id=' . $feed['id'] ) ); ?>" class="sf-feed-action sf-feed-action--edit" title="<?php esc_attr_e( 'Edit', 'social-feed' ); ?>">
										<span class="dashicons dashicons-edit"></span>
									</a>
									<button type="button" class="sf-feed-action sf-feed-action--duplicate sf-duplicate-feed" data-feed-id="<?php echo esc_attr( $feed['id'] ); ?>" title="<?php esc_attr_e( 'Duplicate', 'social-feed' ); ?>">
										<span class="dashicons dashicons-admin-page"></span>
									</button>
									<button type="button" class="sf-feed-action sf-feed-action--delete sf-delete-feed" data-feed-id="<?php echo esc_attr( $feed['id'] ); ?>" title="<?php esc_attr_e( 'Delete', 'social-feed' ); ?>">
										<span class="dashicons dashicons-trash"></span>
									</button>
								</div>
								<div class="sf-feed-card-actions-right">
									<label class="sf-feed-toggle-wrap">
										<input type="checkbox" class="sf-status-toggle" data-feed-id="<?php echo esc_attr( $feed['id'] ); ?>" <?php checked( $is_active ); ?>>
										<span class="sf-feed-toggle-switch"></span>
										<span class="sf-feed-toggle-label"><?php echo $is_active ? esc_html__( 'On', 'social-feed' ) : esc_html__( 'Off', 'social-feed' ); ?></span>
									</label>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render create/edit feed page.
	 */
	public function render_create_feed() {
		$feed_id = isset( $_GET['feed_id'] ) ? absint( $_GET['feed_id'] ) : 0;
		?>
		<div class="wrap sf-admin-wrap sf-create-feed-wrap">
			<?php
			require_once SF_PLUGIN_PATH . 'admin/class-sf-customizer.php';
			SF_Customizer::render( $feed_id );
			?>
		</div>
		<?php
	}

	/**
	 * Render connected accounts page.
	 */
	public function render_accounts() {
		require_once SF_PLUGIN_PATH . 'admin/class-sf-accounts.php';
		SF_Accounts::render();
	}

	/**
	 * Render settings page.
	 */
	public function render_settings() {
		require_once SF_PLUGIN_PATH . 'admin/class-sf-settings.php';
		SF_Settings::render();
	}

	/**
	 * Render license page.
	 */
	public function render_license() {
		require_once SF_PLUGIN_PATH . 'admin/class-sf-license-page.php';
		SF_License_Page::render();
	}

	/**
	 * Render help & support page.
	 */
	public function render_help() {
		?>
		<div class="wrap sf-admin-wrap">
			<h1 class="sf-admin-title"><?php esc_html_e( 'Help & Support', 'social-feed' ); ?></h1>

			<div class="sf-help-grid">
				<div class="sf-card">
					<h2 class="sf-card-title">
						<span class="dashicons dashicons-book"></span>
						<?php esc_html_e( 'Documentation', 'social-feed' ); ?>
					</h2>
					<div class="sf-card-content">
						<p><?php esc_html_e( 'Read our comprehensive documentation to learn how to use Social Feed.', 'social-feed' ); ?></p>
						<a href="<?php echo esc_url( 'https://socialfeedplugin.com/docs/' ); ?>" class="button button-secondary" target="_blank">
							<?php esc_html_e( 'View Documentation', 'social-feed' ); ?>
						</a>
					</div>
				</div>

				<div class="sf-card">
					<h2 class="sf-card-title">
						<span class="dashicons dashicons-video-alt3"></span>
						<?php esc_html_e( 'Video Tutorials', 'social-feed' ); ?>
					</h2>
					<div class="sf-card-content">
						<p><?php esc_html_e( 'Watch step-by-step video tutorials on YouTube.', 'social-feed' ); ?></p>
						<a href="<?php echo esc_url( 'https://youtube.com/@socialfeedplugin' ); ?>" class="button button-secondary" target="_blank">
							<?php esc_html_e( 'Watch Videos', 'social-feed' ); ?>
						</a>
					</div>
				</div>

				<div class="sf-card">
					<h2 class="sf-card-title">
						<span class="dashicons dashicons-sos"></span>
						<?php esc_html_e( 'Support', 'social-feed' ); ?>
					</h2>
					<div class="sf-card-content">
						<p><?php esc_html_e( 'Need help? Our support team is ready to assist you.', 'social-feed' ); ?></p>
						<a href="<?php echo esc_url( 'https://socialfeedplugin.com/support/' ); ?>" class="button button-primary" target="_blank">
							<?php esc_html_e( 'Contact Support', 'social-feed' ); ?>
						</a>
					</div>
				</div>

				<div class="sf-card">
					<h2 class="sf-card-title">
						<span class="dashicons dashicons-info"></span>
						<?php esc_html_e( 'System Info', 'social-feed' ); ?>
					</h2>
					<div class="sf-card-content">
						<table class="sf-system-info">
							<tr>
								<td><?php esc_html_e( 'Plugin Version', 'social-feed' ); ?></td>
								<td><?php echo esc_html( SF_VERSION ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'WordPress Version', 'social-feed' ); ?></td>
								<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'PHP Version', 'social-feed' ); ?></td>
								<td><?php echo esc_html( phpversion() ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Database Version', 'social-feed' ); ?></td>
								<td><?php echo esc_html( get_option( 'sf_db_version', 'N/A' ) ); ?></td>
							</tr>
						</table>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
