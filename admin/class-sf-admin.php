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
			}
			if ( $page === self::PAGE_SLUG . '-create' ) {
				$classes .= ' sf-customizer-page';
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
	 * Get recent feeds.
	 *
	 * @param int $limit Number of feeds to return.
	 * @return array
	 */
	private function get_recent_feeds( $limit = 5 ) {
		return SF_Database::get_all_feeds(
			array(
				'orderby' => 'created_at',
				'order'   => 'DESC',
				'limit'   => $limit,
			)
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
		$recent_feeds  = $this->get_recent_feeds();
		$recent_errors = $this->get_recent_errors();
		$is_pro        = $this->is_pro();
		?>
		<div class="wrap sf-admin-wrap sf-dashboard-wrap">
			<?php
			sf_render_breadcrumb(
				array(
					array( 'label' => __( 'Social Feed', 'social-feed' ), 'url' => '' ),
				),
				'',
				''
			);
			?>
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
					<div class="sf-stat-card sf-stat-card--blue">
						<span class="sf-stat-icon-wrap"><span class="dashicons dashicons-rss"></span></span>
						<div class="sf-stat-content">
							<span class="sf-stat-number"><?php echo esc_html( (string) $stats['total_feeds'] ); ?></span>
							<span class="sf-stat-label"><?php esc_html_e( 'Total Feeds', 'social-feed' ); ?></span>
						</div>
					</div>
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

				<!-- Recent Feeds -->
				<div class="sf-card sf-recent-feeds">
					<h2 class="sf-card-title">
						<?php esc_html_e( 'Recent Feeds', 'social-feed' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-feeds' ) ); ?>" class="sf-view-all">
							<?php esc_html_e( 'View All', 'social-feed' ); ?>
						</a>
					</h2>
					<div class="sf-card-content">
						<?php if ( empty( $recent_feeds ) ) : ?>
							<div class="sf-empty-state">
								<p><?php esc_html_e( 'No feeds created yet.', 'social-feed' ); ?></p>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-create' ) ); ?>" class="button button-primary">
									<?php esc_html_e( 'Create Your First Feed', 'social-feed' ); ?>
								</a>
							</div>
						<?php else : ?>
							<table class="sf-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Platform', 'social-feed' ); ?></th>
										<th><?php esc_html_e( 'Feed Name', 'social-feed' ); ?></th>
										<th><?php esc_html_e( 'Status', 'social-feed' ); ?></th>
										<th><?php esc_html_e( 'Shortcode', 'social-feed' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'social-feed' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $recent_feeds as $feed ) : ?>
										<?php
										$status_class = 'active' === $feed['status'] ? 'sf-status-badge--active' : 'sf-status-badge--paused';
										$status_text  = 'active' === $feed['status'] ? __( 'Active', 'social-feed' ) : __( 'Paused', 'social-feed' );
										?>
										<tr>
											<td><?php echo $this->get_platform_icon( $feed['platform'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
											<td>
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-create&feed_id=' . $feed['id'] ) ); ?>">
													<?php echo esc_html( $feed['name'] ); ?>
												</a>
											</td>
											<td><span class="sf-status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span></td>
											<td>
												<div class="sf-shortcode-wrap">
													<code>[social_feed id="<?php echo esc_attr( (string) $feed['id'] ); ?>"]</code>
													<button type="button" class="sf-copy-btn" data-copy="[social_feed id=&quot;<?php echo esc_attr( (string) $feed['id'] ); ?>&quot;]" title="<?php esc_attr_e( 'Copy shortcode', 'social-feed' ); ?>">
														<span class="dashicons dashicons-clipboard"></span>
													</button>
												</div>
											</td>
											<td class="sf-actions-cell">
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-create&feed_id=' . $feed['id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'social-feed' ); ?></a>
												<label class="sf-toggle sf-toggle-inline">
													<input type="checkbox" class="sf-status-toggle" data-feed-id="<?php echo esc_attr( (string) $feed['id'] ); ?>" <?php checked( 'active', $feed['status'] ); ?>>
													<span class="sf-toggle-slider"></span>
												</label>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
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
		$current_filter = isset( $_GET['platform'] ) ? sanitize_key( $_GET['platform'] ) : '';
		$search_query   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$args = array(
			'orderby' => 'created_at',
			'order'   => 'DESC',
		);

		if ( ! empty( $current_filter ) ) {
			$args['platform'] = $current_filter;
		}

		$feeds = SF_Database::get_all_feeds( $args );

		if ( ! empty( $search_query ) ) {
			$feeds = array_filter(
				$feeds,
				function ( $feed ) use ( $search_query ) {
					return stripos( $feed['name'], $search_query ) !== false;
				}
			);
		}

		$all_feeds       = SF_Database::get_all_feeds();
		$platform_counts = array();
		foreach ( $all_feeds as $feed ) {
			$platform = $feed['platform'];
			if ( ! isset( $platform_counts[ $platform ] ) ) {
				$platform_counts[ $platform ] = 0;
			}
			$platform_counts[ $platform ]++;
		}
		?>
		<div class="wrap sf-admin-wrap">
			<?php
			sf_render_breadcrumb(
				array(
					array( 'label' => __( 'Social Feed', 'social-feed' ), 'url' => admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
					array( 'label' => __( 'All Feeds', 'social-feed' ), 'url' => '' ),
				),
				admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
				__( 'Back to Dashboard', 'social-feed' )
			);
			?>
			<h1 class="sf-admin-title">
				<?php esc_html_e( 'All Feeds', 'social-feed' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-create' ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Add New', 'social-feed' ); ?>
				</a>
			</h1>

			<!-- Filter Tabs -->
			<ul class="subsubsub sf-filter-tabs">
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-feeds' ) ); ?>" class="<?php echo empty( $current_filter ) ? 'current' : ''; ?>">
						<?php esc_html_e( 'All', 'social-feed' ); ?>
						<span class="count">(<?php echo esc_html( count( $all_feeds ) ); ?>)</span>
					</a> |
				</li>
				<?php foreach ( SF_Helpers::get_platforms() as $slug => $label ) : ?>
					<?php if ( isset( $platform_counts[ $slug ] ) ) : ?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-feeds&platform=' . $slug ) ); ?>" class="<?php echo $current_filter === $slug ? 'current' : ''; ?>">
								<?php echo esc_html( $label ); ?>
								<span class="count">(<?php echo esc_html( $platform_counts[ $slug ] ); ?>)</span>
							</a> |
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>

			<!-- Bulk Actions & Search -->
			<form method="get" class="sf-feeds-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG . '-feeds' ); ?>">
				<?php if ( ! empty( $current_filter ) ) : ?>
					<input type="hidden" name="platform" value="<?php echo esc_attr( $current_filter ); ?>">
				<?php endif; ?>

				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'social-feed' ); ?></label>
						<select name="bulk_action" id="bulk-action-selector-top" class="sf-bulk-action">
							<option value=""><?php esc_html_e( 'Bulk Actions', 'social-feed' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete', 'social-feed' ); ?></option>
							<option value="pause"><?php esc_html_e( 'Pause', 'social-feed' ); ?></option>
						</select>
						<button type="button" class="button sf-bulk-apply-btn"><?php esc_html_e( 'Apply', 'social-feed' ); ?></button>
						<?php wp_nonce_field( 'sf_bulk_action', 'sf_bulk_nonce' ); ?>
					</div>

					<p class="search-box">
						<label class="screen-reader-text" for="feed-search-input"><?php esc_html_e( 'Search Feeds', 'social-feed' ); ?></label>
						<input type="search" id="feed-search-input" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search feeds...', 'social-feed' ); ?>">
						<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search', 'social-feed' ); ?>">
					</p>
				</div>
			</form>

			<?php if ( empty( $feeds ) ) : ?>
				<!-- Empty State -->
				<div class="sf-empty-state-large">
					<div class="sf-empty-illustration">
						<svg width="120" height="120" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
							<circle cx="60" cy="60" r="50" fill="#f0f0f1"/>
							<rect x="30" y="35" width="60" height="50" rx="4" fill="#c3c4c7"/>
							<rect x="35" y="42" width="20" height="15" rx="2" fill="#fff"/>
							<rect x="35" y="62" width="50" height="4" rx="2" fill="#fff"/>
							<rect x="35" y="70" width="35" height="4" rx="2" fill="#fff"/>
							<circle cx="90" cy="85" r="18" fill="#2271b1"/>
							<path d="M90 77v16M82 85h16" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
						</svg>
					</div>
					<h2><?php esc_html_e( 'No feeds found', 'social-feed' ); ?></h2>
					<p><?php esc_html_e( 'Create your first social media feed to display on your website.', 'social-feed' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-create' ) ); ?>" class="button button-primary button-hero">
						<?php esc_html_e( 'Create Your First Feed', 'social-feed' ); ?>
					</a>
				</div>
			<?php else : ?>
				<!-- Feeds Table -->
				<table class="wp-list-table widefat fixed striped sf-feeds-table">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column">
								<input type="checkbox" id="cb-select-all-1" class="sf-select-all">
							</td>
							<th class="manage-column column-name column-primary"><?php esc_html_e( 'Feed Name', 'social-feed' ); ?></th>
							<th class="manage-column column-platform"><?php esc_html_e( 'Platform', 'social-feed' ); ?></th>
							<th class="manage-column column-type"><?php esc_html_e( 'Feed Type', 'social-feed' ); ?></th>
							<th class="manage-column column-status"><?php esc_html_e( 'Status', 'social-feed' ); ?></th>
							<th class="manage-column column-shortcode"><?php esc_html_e( 'Shortcode', 'social-feed' ); ?></th>
							<th class="manage-column column-updated"><?php esc_html_e( 'Last Updated', 'social-feed' ); ?></th>
							<th class="manage-column column-actions"><?php esc_html_e( 'Actions', 'social-feed' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $feeds as $feed ) : ?>
							<tr data-feed-id="<?php echo esc_attr( $feed['id'] ); ?>">
								<th class="check-column">
									<input type="checkbox" name="feed_ids[]" value="<?php echo esc_attr( $feed['id'] ); ?>" class="sf-feed-checkbox">
								</th>
								<td class="column-name column-primary">
									<strong>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-create&feed_id=' . $feed['id'] ) ); ?>">
											<?php echo esc_html( $feed['name'] ); ?>
										</a>
									</strong>
									<div class="row-actions">
										<span class="edit">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-create&feed_id=' . $feed['id'] ) ); ?>">
												<?php esc_html_e( 'Edit', 'social-feed' ); ?>
											</a> |
										</span>
										<span class="duplicate">
											<a href="#" class="sf-duplicate-feed" data-feed-id="<?php echo esc_attr( $feed['id'] ); ?>">
												<?php esc_html_e( 'Duplicate', 'social-feed' ); ?>
											</a> |
										</span>
										<span class="trash">
											<a href="#" class="sf-delete-feed" data-feed-id="<?php echo esc_attr( $feed['id'] ); ?>">
												<?php esc_html_e( 'Delete', 'social-feed' ); ?>
											</a>
										</span>
									</div>
								</td>
								<td class="column-platform">
									<?php echo $this->get_platform_icon( $feed['platform'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<?php echo esc_html( ucfirst( $feed['platform'] ) ); ?>
								</td>
								<td class="column-type"><?php echo esc_html( ucfirst( $feed['feed_type'] ) ); ?></td>
								<td class="column-status">
									<label class="sf-toggle">
										<input type="checkbox" class="sf-status-toggle" data-feed-id="<?php echo esc_attr( $feed['id'] ); ?>" <?php checked( 'active', $feed['status'] ); ?>>
										<span class="sf-toggle-slider"></span>
									</label>
								</td>
								<td class="column-shortcode">
									<code class="sf-shortcode">[social_feed id="<?php echo esc_attr( $feed['id'] ); ?>"]</code>
									<button type="button" class="sf-copy-btn" data-copy="[social_feed id=&quot;<?php echo esc_attr( $feed['id'] ); ?>&quot;]" title="<?php esc_attr_e( 'Copy', 'social-feed' ); ?>">
										<span class="dashicons dashicons-clipboard"></span>
									</button>
								</td>
								<td class="column-updated">
									<?php echo esc_html( SF_Helpers::sf_time_ago( $feed['updated_at'] ) ); ?>
								</td>
								<td class="column-actions">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-create&feed_id=' . $feed['id'] ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Edit', 'social-feed' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<td class="manage-column column-cb check-column">
								<input type="checkbox" id="cb-select-all-2" class="sf-select-all">
							</td>
							<th class="manage-column column-name column-primary"><?php esc_html_e( 'Feed Name', 'social-feed' ); ?></th>
							<th class="manage-column column-platform"><?php esc_html_e( 'Platform', 'social-feed' ); ?></th>
							<th class="manage-column column-type"><?php esc_html_e( 'Feed Type', 'social-feed' ); ?></th>
							<th class="manage-column column-status"><?php esc_html_e( 'Status', 'social-feed' ); ?></th>
							<th class="manage-column column-shortcode"><?php esc_html_e( 'Shortcode', 'social-feed' ); ?></th>
							<th class="manage-column column-updated"><?php esc_html_e( 'Last Updated', 'social-feed' ); ?></th>
							<th class="manage-column column-actions"><?php esc_html_e( 'Actions', 'social-feed' ); ?></th>
						</tr>
					</tfoot>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render create/edit feed page.
	 */
	public function render_create_feed() {
		$feed_id = isset( $_GET['feed_id'] ) ? absint( $_GET['feed_id'] ) : 0;
		$feed    = $feed_id ? SF_Database::get_feed( $feed_id ) : null;
		$current_label = $feed_id
			? sprintf( /* translators: %s: Feed name */ __( 'Edit: %s', 'social-feed' ), $feed ? $feed['name'] : '' )
			: __( 'Create New Feed', 'social-feed' );
		?>
		<div class="wrap sf-admin-wrap sf-create-feed-wrap">
			<?php
			sf_render_breadcrumb(
				array(
					array( 'label' => __( 'Social Feed', 'social-feed' ), 'url' => admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
					array( 'label' => __( 'All Feeds', 'social-feed' ), 'url' => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-feeds' ) ),
					array( 'label' => $current_label, 'url' => '' ),
				),
				admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-feeds' ),
				__( 'Back to All Feeds', 'social-feed' )
			);
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
		sf_render_breadcrumb(
			array(
				array( 'label' => __( 'Social Feed', 'social-feed' ), 'url' => admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
				array( 'label' => __( 'Instagram Accounts', 'social-feed' ), 'url' => '' ),
			),
			admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
			__( 'Back to Dashboard', 'social-feed' )
		);
		SF_Accounts::render();
	}

	/**
	 * Render settings page.
	 */
	public function render_settings() {
		sf_render_breadcrumb(
			array(
				array( 'label' => __( 'Social Feed', 'social-feed' ), 'url' => admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
				array( 'label' => __( 'Settings', 'social-feed' ), 'url' => '' ),
			),
			admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
			__( 'Back to Dashboard', 'social-feed' )
		);
		require_once SF_PLUGIN_PATH . 'admin/class-sf-settings.php';
		SF_Settings::render();
	}

	/**
	 * Render license page.
	 */
	public function render_license() {
		sf_render_breadcrumb(
			array(
				array( 'label' => __( 'Social Feed', 'social-feed' ), 'url' => admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
				array( 'label' => __( 'License', 'social-feed' ), 'url' => '' ),
			),
			admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
			__( 'Back to Dashboard', 'social-feed' )
		);
		require_once SF_PLUGIN_PATH . 'admin/class-sf-license-page.php';
		SF_License_Page::render();
	}

	/**
	 * Render help & support page.
	 */
	public function render_help() {
		?>
		<div class="wrap sf-admin-wrap">
			<?php
			sf_render_breadcrumb(
				array(
					array( 'label' => __( 'Social Feed', 'social-feed' ), 'url' => admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
					array( 'label' => __( 'Help & Support', 'social-feed' ), 'url' => '' ),
				),
				admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
				__( 'Back to Dashboard', 'social-feed' )
			);
			?>
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
