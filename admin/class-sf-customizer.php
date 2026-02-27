<?php
/**
 * Feed Customizer page for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Customizer
 */
class SF_Customizer {

	/**
	 * Render the feed customizer page.
	 *
	 * @param int $feed_id Feed ID to edit (0 for new).
	 */
	public static function render( $feed_id = 0 ) {
		$feed     = $feed_id ? SF_Database::get_feed( $feed_id ) : null;
		$meta     = $feed_id ? SF_Database::get_all_feed_meta( $feed_id ) : array();
		$accounts = SF_Database::get_all_accounts( array( 'is_connected' => 1 ) );
		$is_pro   = self::is_pro();

		$defaults = self::get_defaults();
		$settings = wp_parse_args( $meta, $defaults );

		if ( $feed ) {
			$settings['name']       = $feed['name'];
			$settings['platform']   = $feed['platform'];
			$settings['account_id'] = $feed['account_id'];
			$settings['feed_type']  = $feed['feed_type'];
			$settings['post_count'] = $feed['post_count'];
		}
		$feed_name_display = ! empty( $settings['name'] ) ? $settings['name'] : ( $feed_id ? __( 'Edit Feed', 'social-feed' ) : __( 'Create Feed', 'social-feed' ) );
		$shortcode          = $feed_id ? '[social_feed id="' . (int) $feed_id . '"]' : '';
		?>
		<div class="sf-customizer-wrap" data-feed-id="<?php echo esc_attr( $feed_id ); ?>">
				<!-- Single top bar: upgrade banner (free) or actions-only bar (pro) -->
			<div class="<?php echo $is_pro ? 'sf-customizer-topbar' : 'sf-customizer-upgrade-banner'; ?>">
				<?php if ( ! $is_pro ) : ?>
					<div class="sf-upgrade-banner-left">
						<span class="sf-upgrade-text"><?php esc_html_e( 'Unlock more features with Social Feed Pro.', 'social-feed' ); ?></span>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=social-feed-license' ) ); ?>" class="sf-upgrade-link"><?php esc_html_e( 'Upgrade', 'social-feed' ); ?></a>
					</div>
				<?php endif; ?>
				<div class="sf-topbar-right">
					<button type="button" class="sf-topbar-btn sf-help-btn" title="<?php esc_attr_e( 'Help', 'social-feed' ); ?>">
						<span class="dashicons dashicons-editor-help"></span>
						<?php esc_html_e( 'Help', 'social-feed' ); ?>
					</button>
					<button type="button" class="sf-topbar-btn sf-embed-btn" title="<?php esc_attr_e( 'Embed', 'social-feed' ); ?>" <?php echo ! $feed_id ? 'style="display:none;"' : ''; ?>>
						<span class="dashicons dashicons-code"></span>
						<?php esc_html_e( 'Embed', 'social-feed' ); ?>
					</button>
					<span class="sf-unsaved-indicator" style="display:none;">
						<span class="sf-unsaved-dot"></span>
						<?php esc_html_e( 'Unsaved changes', 'social-feed' ); ?>
					</span>
					<button type="button" class="button button-primary sf-save-feed">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'Save', 'social-feed' ); ?>
					</button>
				</div>
			</div>

			<!-- Body: Sidebar + Preview -->
			<div class="sf-customizer-body">
				<!-- Left Sidebar (Settings) -->
				<div class="sf-customizer-sidebar sf-customizer-settings">
					<!-- Main navigation list -->
					<nav class="sf-sidebar-nav sf-sidebar-view-active">
						<!-- Top-level: Custom / Settings -->
						<div class="sf-sidebar-toplevel">
							<button type="button" class="sf-sidebar-toplevel-btn active" data-mode="custom"><?php esc_html_e( 'Custom', 'social-feed' ); ?></button>
							<button type="button" class="sf-sidebar-toplevel-btn" data-mode="settings"><?php esc_html_e( 'Settings', 'social-feed' ); ?></button>
						</div>

						<!-- Custom sub-list (visible when Custom active) -->
						<div class="sf-sidebar-sublist sf-sidebar-sublist-custom">
							<div class="sf-sidebar-group">
								<button type="button" class="sf-sidebar-item" data-section="layout">
									<span class="dashicons dashicons-grid-view"></span>
									<span class="sf-sidebar-label"><?php esc_html_e( 'Feed Layout', 'social-feed' ); ?></span>
									<span class="dashicons dashicons-arrow-right-alt2 sf-sidebar-chevron"></span>
								</button>
								<button type="button" class="sf-sidebar-item" data-section="design">
									<span class="dashicons dashicons-art"></span>
									<span class="sf-sidebar-label"><?php esc_html_e( 'Color Scheme', 'social-feed' ); ?></span>
									<span class="dashicons dashicons-arrow-right-alt2 sf-sidebar-chevron"></span>
								</button>
							</div>
							<div class="sf-sidebar-group">
								<div class="sf-sidebar-group-title"><?php esc_html_e( 'SECTIONS', 'social-feed' ); ?></div>
							<button type="button" class="sf-sidebar-item" data-section="header">
								<span class="dashicons dashicons-admin-users"></span>
								<span class="sf-sidebar-label"><?php esc_html_e( 'Header', 'social-feed' ); ?></span>
								<span class="dashicons dashicons-arrow-right-alt2 sf-sidebar-chevron"></span>
							</button>
							<button type="button" class="sf-sidebar-item" data-section="post_settings">
								<span class="dashicons dashicons-format-image"></span>
								<span class="sf-sidebar-label"><?php esc_html_e( 'Post Settings', 'social-feed' ); ?></span>
								<span class="dashicons dashicons-arrow-right-alt2 sf-sidebar-chevron"></span>
							</button>
							<button type="button" class="sf-sidebar-item" data-section="ballu">
									<span class="dashicons dashicons-admin-generic"></span>
									<span class="sf-sidebar-label"><?php esc_html_e( 'Ballu', 'social-feed' ); ?></span>
									<span class="dashicons dashicons-arrow-right-alt2 sf-sidebar-chevron"></span>
								</button>
								<button type="button" class="sf-sidebar-item" data-section="loadmore">
									<span class="dashicons dashicons-download"></span>
									<span class="sf-sidebar-label"><?php esc_html_e( 'Load More Button', 'social-feed' ); ?></span>
									<span class="dashicons dashicons-arrow-right-alt2 sf-sidebar-chevron"></span>
								</button>
								<button type="button" class="sf-sidebar-item" data-section="header">
									<span class="dashicons dashicons-admin-users"></span>
									<span class="sf-sidebar-label"><?php esc_html_e( 'Follow Button', 'social-feed' ); ?></span>
									<span class="dashicons dashicons-arrow-right-alt2 sf-sidebar-chevron"></span>
								</button>
								<button type="button" class="sf-sidebar-item" data-section="advanced">
									<span class="dashicons dashicons-admin-tools"></span>
									<span class="sf-sidebar-label"><?php esc_html_e( 'Advanced', 'social-feed' ); ?></span>
									<span class="dashicons dashicons-arrow-right-alt2 sf-sidebar-chevron"></span>
								</button>
							</div>
						</div>

						<!-- Settings sub-list (visible when Settings active) -->
						<div class="sf-sidebar-sublist sf-sidebar-sublist-settings" style="display:none;">
							<div class="sf-sidebar-group">
								<button type="button" class="sf-sidebar-item" data-section="feed">
									<span class="dashicons dashicons-rss"></span>
									<span class="sf-sidebar-label"><?php esc_html_e( 'Feed Source', 'social-feed' ); ?></span>
									<span class="dashicons dashicons-arrow-right-alt2 sf-sidebar-chevron"></span>
								</button>
							</div>
						</div>
					</nav>

					<!-- Panel view: back + section content -->
					<div class="sf-sidebar-panels">
						<?php
						$panel_titles = array(
							'feed'     => __( 'Feed Source', 'social-feed' ),
							'layout'   => __( 'Feed Layout', 'social-feed' ),
							'design'   => __( 'Color Scheme', 'social-feed' ),
							'header'   => __( 'Header', 'social-feed' ),
							'post_settings' => __( 'Post Settings', 'social-feed' ),
							'ballu'    => __( 'Ballu', 'social-feed' ),
							'loadmore' => __( 'Load More Button', 'social-feed' ),
							'advanced' => __( 'Advanced', 'social-feed' ),
						);
						foreach ( $panel_titles as $section => $title ) :
							$panel_id = 'sf-panel-' . sanitize_html_class( $section );
							?>
							<div id="<?php echo esc_attr( $panel_id ); ?>" class="sf-sidebar-panel" data-section="<?php echo esc_attr( $section ); ?>">
								<div class="sf-sidebar-panel-header">
									<button type="button" class="sf-sidebar-back">
										<span class="dashicons dashicons-arrow-left-alt2"></span>
									</button>
									<h3 class="sf-sidebar-panel-title"><?php echo esc_html( $title ); ?></h3>
								</div>
								<div class="sf-sidebar-panel-body">
									<div class="sf-customizer-content">
										<?php
										switch ( $section ) {
											case 'feed':
												self::render_tab_feed( $settings, $accounts );
												break;
											case 'layout':
												self::render_tab_layout( $settings );
												break;
											case 'design':
												self::render_tab_design( $settings );
												break;
											case 'header':
												self::render_tab_header( $settings );
												break;
										case 'post_settings':
											self::render_tab_post_settings( $settings );
											break;
											case 'ballu':
												self::render_tab_ballu( $settings );
												break;
											case 'loadmore':
												self::render_tab_loadmore( $settings );
												break;
											case 'advanced':
												self::render_tab_advanced( $settings, $is_pro );
												break;
										}
										?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Right Panel - Preview -->
				<div class="sf-customizer-panel sf-customizer-preview">
					<div class="sf-preview-header">
						<span class="sf-preview-label"><?php esc_html_e( 'PREVIEW', 'social-feed' ); ?></span>
						<div class="sf-preview-header-center">
							<div class="sf-feed-name-wrap">
								<span class="sf-feed-name-display"><?php echo esc_html( $feed_name_display ); ?></span>
								<button type="button" class="sf-feed-name-edit" aria-label="<?php esc_attr_e( 'Edit feed name', 'social-feed' ); ?>">
									<span class="dashicons dashicons-edit"></span>
								</button>
								<input type="text" class="sf-feed-name-input" id="sf_name" name="name" value="<?php echo esc_attr( $settings['name'] ); ?>" placeholder="<?php esc_attr_e( 'My Instagram Feed', 'social-feed' ); ?>" style="display:none;">
							</div>
						</div>
						<div class="sf-preview-header-right">
							<div class="sf-device-switcher">
								<button type="button" class="sf-device-btn active" data-device="desktop" title="<?php esc_attr_e( 'Desktop', 'social-feed' ); ?>">
									<span class="dashicons dashicons-desktop"></span>
								</button>
								<button type="button" class="sf-device-btn" data-device="tablet" title="<?php esc_attr_e( 'Tablet', 'social-feed' ); ?>">
									<span class="dashicons dashicons-tablet"></span>
								</button>
								<button type="button" class="sf-device-btn" data-device="mobile" title="<?php esc_attr_e( 'Mobile', 'social-feed' ); ?>">
									<span class="dashicons dashicons-smartphone"></span>
								</button>
							</div>
							<button type="button" class="sf-refresh-preview" title="<?php esc_attr_e( 'Refresh Preview', 'social-feed' ); ?>">
								<span class="dashicons dashicons-update"></span>
							</button>
						</div>
					</div>
					<div class="sf-preview-container" data-device="desktop">
						<div class="sf-preview-loading">
							<div class="sf-skeleton-grid">
								<?php for ( $i = 0; $i < 9; $i++ ) : ?>
									<div class="sf-skeleton-item"></div>
								<?php endfor; ?>
							</div>
						</div>
						<div class="sf-preview-content"></div>
					</div>
				</div>
			</div>
		</div>

		<!-- Embed modal (shortcode) -->
		<div class="sf-embed-modal" id="sf-embed-modal" style="display:none;">
			<div class="sf-embed-modal-overlay"></div>
			<div class="sf-embed-modal-content">
				<div class="sf-embed-modal-header">
					<h3><?php esc_html_e( 'Embed Feed', 'social-feed' ); ?></h3>
					<button type="button" class="sf-embed-modal-close">&times;</button>
				</div>
				<div class="sf-embed-modal-body">
					<label><?php esc_html_e( 'Shortcode:', 'social-feed' ); ?></label>
					<div class="sf-embed-shortcode-row">
						<code class="sf-generated-shortcode"><?php echo esc_html( $shortcode ); ?></code>
						<button type="button" class="sf-copy-btn" data-copy="<?php echo esc_attr( str_replace( '"', '&quot;', $shortcode ) ); ?>">
							<span class="dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Copy', 'social-feed' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'name'                => '',
			'platform'            => 'instagram',
			'account_id'          => 0,
			'feed_type'           => 'user',
			'post_count'          => 9,
			'post_count_desktop'   => 9,
			'post_count_tablet'    => 9,
			'post_count_mobile'    => 9,
			'layout'              => 'grid',
			'columns_desktop'     => 3,
			'columns_tablet'      => 2,
			'columns_mobile'      => 1,
			'feed_height'         => '',
			'image_padding'       => 10,
			'bg_color'            => '#ffffff',
			'text_color'          => '#333333',
			'border_style'        => 'none',
			'border_color'        => '#e0e0e0',
			'border_radius'       => 8,
			'hover_effect'        => 'zoom',
			'dark_mode'           => false,
			'show_header'         => true,
			'show_profile_pic'    => true,
			'show_username'       => true,
			'show_followers'      => true,
			'show_bio'            => false,
			'show_follow_btn'     => true,
			'follow_btn_color'    => '#0095f6',
			'follow_btn_text'     => 'Follow',
			'show_caption'        => true,
			'caption_length'      => 100,
			'show_date'           => true,
			'show_likes'          => true,
			'show_comments'       => true,
			'click_action'        => 'popup',
			'popup_style'         => 'minimal',
			'loadmore_type'       => 'button',
			'loadmore_text'       => 'Load More',
			'loadmore_bg_color'   => '#2271b1',
			'posts_per_load'      => 9,
			'custom_css'          => '',
			'lazy_load'           => true,
			'gdpr_mode'           => false,
			'show_credit'         => true,
		);
	}

	/**
	 * Check if Pro version.
	 *
	 * @return bool
	 */
	private static function is_pro() {
		$license = SF_Database::get_active_license();
		return $license && 'active' === $license['status'] && 'free' !== $license['plan'];
	}

	/**
	 * Render Tab 1 - Feed Settings.
	 *
	 * @param array $settings Current settings.
	 * @param array $accounts Connected accounts.
	 */
	private static function render_tab_feed( $settings, $accounts ) {
		?>
		<div class="sf-tab-content active" data-tab="feed">
			<input type="hidden" id="sf_platform" name="platform" value="instagram">
			<div class="sf-section sf-section-feed-source">
				<div class="sf-section-title"><?php esc_html_e( 'Feed Source', 'social-feed' ); ?></div>
				<div class="sf-field">
					<label for="sf_account_id"><?php esc_html_e( 'Instagram Account', 'social-feed' ); ?></label>
					<div class="sf-field-control sf-field-control-stack">
						<select id="sf_account_id" name="account_id">
							<option value=""><?php esc_html_e( 'Select an account...', 'social-feed' ); ?></option>
							<?php foreach ( $accounts as $account ) : ?>
								<option value="<?php echo esc_attr( $account['id'] ); ?>" data-platform="<?php echo esc_attr( $account['platform'] ); ?>" <?php selected( $settings['account_id'], $account['id'] ); ?>>
									<?php echo esc_html( $account['account_name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=social-feed-accounts' ) ); ?>" class="sf-link-small">
							<?php esc_html_e( '+ Connect new account', 'social-feed' ); ?>
						</a>
					</div>
				</div>
				<div class="sf-field">
					<label for="sf_feed_type"><?php esc_html_e( 'Feed Type', 'social-feed' ); ?></label>
					<select id="sf_feed_type" name="feed_type">
						<option value="user" <?php selected( $settings['feed_type'], 'user' ); ?>><?php esc_html_e( 'User Timeline', 'social-feed' ); ?></option>
						<option value="hashtag" <?php selected( $settings['feed_type'], 'hashtag' ); ?>><?php esc_html_e( 'Hashtag', 'social-feed' ); ?></option>
						<option value="tagged" <?php selected( $settings['feed_type'], 'tagged' ); ?>><?php esc_html_e( 'Tagged Posts', 'social-feed' ); ?></option>
					</select>
				</div>
				<div class="sf-field">
					<label for="sf_post_count"><?php esc_html_e( 'Number of Posts', 'social-feed' ); ?></label>
					<input type="number" id="sf_post_count" name="post_count" value="<?php echo esc_attr( $settings['post_count'] ); ?>" min="1" max="50">
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Tab 2 - Layout (sub-panel with back arrow).
	 *
	 * @param array $settings Current settings.
	 */
	private static function render_tab_layout( $settings ) {
		$layout = isset( $settings['layout'] ) ? $settings['layout'] : 'grid';
		if ( ! in_array( $layout, array( 'grid', 'carousel', 'masonry', 'highlight' ), true ) ) {
			$layout = 'grid';
		}
		$layouts = array(
			'grid'     => array( 'icon' => 'dashicons-grid-view', 'label' => __( 'Grid', 'social-feed' ), 'pro' => false ),
			'carousel' => array( 'icon' => 'dashicons-slides', 'label' => __( 'Carousel', 'social-feed' ), 'pro' => true ),
			'masonry'  => array( 'icon' => 'dashicons-layout', 'label' => __( 'Masonry', 'social-feed' ), 'pro' => true ),
			'highlight'=> array( 'icon' => 'dashicons-star-filled', 'label' => __( 'Highlight', 'social-feed' ), 'pro' => true ),
		);
		$post_count_desktop = isset( $settings['post_count_desktop'] ) ? $settings['post_count_desktop'] : ( isset( $settings['post_count'] ) ? $settings['post_count'] : 9 );
		$post_count_tablet  = isset( $settings['post_count_tablet'] ) ? $settings['post_count_tablet'] : $post_count_desktop;
		$post_count_mobile  = isset( $settings['post_count_mobile'] ) ? $settings['post_count_mobile'] : $post_count_desktop;
		?>
		<div class="sf-tab-content sf-tab-content-layout" data-tab="layout">
			<!-- 1. Layout -->
			<div class="sf-layout-panel-section">
				<div class="sf-layout-panel-section-title"><?php esc_html_e( 'Layout', 'social-feed' ); ?></div>
				<div class="sf-radio-cards">
					<?php foreach ( $layouts as $value => $layout_data ) : ?>
						<label class="sf-radio-card sf-layout-option <?php echo ( $layout === $value ) ? 'active' : ''; ?>">
							<input type="radio" name="layout" value="<?php echo esc_attr( $value ); ?>" <?php checked( $layout, $value ); ?>>
							<span class="sf-radio-card-radio"></span>
							<span class="dashicons <?php echo esc_attr( $layout_data['icon'] ); ?>"></span>
							<span class="sf-radio-card-label"><?php echo esc_html( $layout_data['label'] ); ?></span>
							<?php if ( ! empty( $layout_data['pro'] ) ) : ?>
								<span class="sf-pro-badge" title="<?php esc_attr_e( 'Pro', 'social-feed' ); ?>"><?php esc_html_e( 'PRO', 'social-feed' ); ?></span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- 2. Feed Height -->
			<div class="sf-layout-panel-section">
				<div class="sf-layout-panel-section-title"><?php esc_html_e( 'Feed Height', 'social-feed' ); ?></div>
				<div class="sf-field sf-field-number-px">
					<label for="sf_feed_height"><?php esc_html_e( 'Height', 'social-feed' ); ?></label>
					<div class="sf-number-px-wrap">
						<input type="number" id="sf_feed_height" name="feed_height" value="<?php echo esc_attr( $settings['feed_height'] ); ?>" min="0" step="1" placeholder="">
						<span class="sf-number-px-suffix">px</span>
					</div>
				</div>
			</div>

			<!-- 3. Padding -->
			<div class="sf-layout-panel-section">
				<div class="sf-layout-panel-section-title"><?php esc_html_e( 'Padding', 'social-feed' ); ?></div>
				<div class="sf-field sf-field-number-px">
					<label for="sf_image_padding"><?php esc_html_e( 'Padding', 'social-feed' ); ?></label>
					<div class="sf-number-px-wrap">
						<input type="number" id="sf_image_padding" name="image_padding" value="<?php echo esc_attr( $settings['image_padding'] ); ?>" min="0" step="1">
						<span class="sf-number-px-suffix">px</span>
					</div>
				</div>
			</div>

			<!-- 4. Number of Posts -->
			<div class="sf-layout-panel-section">
				<div class="sf-layout-panel-section-title"><?php esc_html_e( 'Number of Posts', 'social-feed' ); ?></div>
				<div class="sf-device-rows">
					<div class="sf-device-row">
						<span class="dashicons dashicons-desktop"></span>
						<label for="sf_post_count_desktop"><?php esc_html_e( 'Desktop', 'social-feed' ); ?></label>
						<input type="number" id="sf_post_count_desktop" name="post_count_desktop" value="<?php echo esc_attr( $post_count_desktop ); ?>" min="1" max="50" step="1">
					</div>
					<div class="sf-device-row">
						<span class="dashicons dashicons-tablet"></span>
						<label for="sf_post_count_tablet"><?php esc_html_e( 'Tablet', 'social-feed' ); ?></label>
						<input type="number" id="sf_post_count_tablet" name="post_count_tablet" value="<?php echo esc_attr( $post_count_tablet ); ?>" min="1" max="50" step="1">
					</div>
					<div class="sf-device-row">
						<span class="dashicons dashicons-smartphone"></span>
						<label for="sf_post_count_mobile"><?php esc_html_e( 'Mobile', 'social-feed' ); ?></label>
						<input type="number" id="sf_post_count_mobile" name="post_count_mobile" value="<?php echo esc_attr( $post_count_mobile ); ?>" min="1" max="50" step="1">
					</div>
				</div>
			</div>

			<!-- 5. Columns -->
			<div class="sf-layout-panel-section">
				<div class="sf-layout-panel-section-title"><?php esc_html_e( 'Columns', 'social-feed' ); ?></div>
				<div class="sf-device-rows">
					<div class="sf-device-row">
						<span class="dashicons dashicons-desktop"></span>
						<label for="sf_columns_desktop"><?php esc_html_e( 'Desktop', 'social-feed' ); ?></label>
						<input type="number" id="sf_columns_desktop" name="columns_desktop" value="<?php echo esc_attr( $settings['columns_desktop'] ); ?>" min="1" max="6" step="1">
					</div>
					<div class="sf-device-row">
						<span class="dashicons dashicons-tablet"></span>
						<label for="sf_columns_tablet"><?php esc_html_e( 'Tablet', 'social-feed' ); ?></label>
						<input type="number" id="sf_columns_tablet" name="columns_tablet" value="<?php echo esc_attr( $settings['columns_tablet'] ); ?>" min="1" max="6" step="1">
					</div>
					<div class="sf-device-row">
						<span class="dashicons dashicons-smartphone"></span>
						<label for="sf_columns_mobile"><?php esc_html_e( 'Mobile', 'social-feed' ); ?></label>
						<input type="number" id="sf_columns_mobile" name="columns_mobile" value="<?php echo esc_attr( $settings['columns_mobile'] ); ?>" min="1" max="6" step="1">
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Tab 3 - Design.
	 *
	 * @param array $settings Current settings.
	 */
	private static function render_tab_design( $settings ) {
		?>
		<div class="sf-tab-content" data-tab="design">
			<div class="sf-section">
				<div class="sf-section-title"><?php esc_html_e( 'Colors', 'social-feed' ); ?></div>
				<div class="sf-field">
					<label for="sf_bg_color"><?php esc_html_e( 'Background Color', 'social-feed' ); ?></label>
					<div class="sf-color-picker-wrap"><input type="text" id="sf_bg_color" name="bg_color" value="<?php echo esc_attr( $settings['bg_color'] ); ?>" class="sf-color-picker"></div>
				</div>
				<div class="sf-field">
					<label for="sf_text_color"><?php esc_html_e( 'Text Color', 'social-feed' ); ?></label>
					<div class="sf-color-picker-wrap"><input type="text" id="sf_text_color" name="text_color" value="<?php echo esc_attr( $settings['text_color'] ); ?>" class="sf-color-picker"></div>
				</div>
			</div>
			<div class="sf-section">
				<div class="sf-section-title"><?php esc_html_e( 'Borders', 'social-feed' ); ?></div>
				<div class="sf-field">
					<label for="sf_border_style"><?php esc_html_e( 'Border Style', 'social-feed' ); ?></label>
					<select id="sf_border_style" name="border_style">
						<option value="none" <?php selected( $settings['border_style'], 'none' ); ?>><?php esc_html_e( 'None', 'social-feed' ); ?></option>
						<option value="solid" <?php selected( $settings['border_style'], 'solid' ); ?>><?php esc_html_e( 'Solid', 'social-feed' ); ?></option>
						<option value="dashed" <?php selected( $settings['border_style'], 'dashed' ); ?>><?php esc_html_e( 'Dashed', 'social-feed' ); ?></option>
					</select>
				</div>
				<div class="sf-field sf-border-options" <?php echo 'none' === $settings['border_style'] ? 'style="display:none;"' : ''; ?>>
					<label for="sf_border_color"><?php esc_html_e( 'Border Color', 'social-feed' ); ?></label>
					<div class="sf-color-picker-wrap"><input type="text" id="sf_border_color" name="border_color" value="<?php echo esc_attr( $settings['border_color'] ); ?>" class="sf-color-picker"></div>
				</div>
				<div class="sf-field">
					<label for="sf_border_radius"><?php esc_html_e( 'Border Radius', 'social-feed' ); ?></label>
					<div class="sf-range-wrapper">
						<input type="range" id="sf_border_radius" name="border_radius" value="<?php echo esc_attr( $settings['border_radius'] ); ?>" min="0" max="20">
						<span class="sf-range-value"><?php echo esc_html( $settings['border_radius'] ); ?>px</span>
					</div>
				</div>
			</div>
			<div class="sf-section">
				<div class="sf-section-title"><?php esc_html_e( 'Display Options', 'social-feed' ); ?></div>
				<div class="sf-field">
					<label for="sf_hover_effect"><?php esc_html_e( 'Hover Effect', 'social-feed' ); ?></label>
					<select id="sf_hover_effect" name="hover_effect">
						<option value="none" <?php selected( $settings['hover_effect'], 'none' ); ?>><?php esc_html_e( 'None', 'social-feed' ); ?></option>
						<option value="zoom" <?php selected( $settings['hover_effect'], 'zoom' ); ?>><?php esc_html_e( 'Zoom', 'social-feed' ); ?></option>
						<option value="fade" <?php selected( $settings['hover_effect'], 'fade' ); ?>><?php esc_html_e( 'Fade', 'social-feed' ); ?></option>
					</select>
				</div>
				<div class="sf-field sf-toggle-field">
					<label for="sf_dark_mode"><?php esc_html_e( 'Dark Mode', 'social-feed' ); ?></label>
					<label class="sf-toggle">
						<input type="checkbox" id="sf_dark_mode" name="dark_mode" value="1" <?php checked( $settings['dark_mode'] ); ?>>
						<span class="sf-toggle-slider"></span>
					</label>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Tab 4 - Header.
	 *
	 * @param array $settings Current settings.
	 */
	private static function render_tab_header( $settings ) {
		?>
		<div class="sf-tab-content" data-tab="header">
			<div class="sf-section">
				<div class="sf-section-title"><?php esc_html_e( 'Header Visibility', 'social-feed' ); ?></div>
				<div class="sf-field sf-toggle-field">
					<label for="sf_show_header"><?php esc_html_e( 'Show Header', 'social-feed' ); ?></label>
					<label class="sf-toggle">
						<input type="checkbox" id="sf_show_header" name="show_header" value="1" <?php checked( $settings['show_header'] ); ?>>
						<span class="sf-toggle-slider"></span>
					</label>
				</div>
				<div class="sf-header-options" <?php echo ! $settings['show_header'] ? 'style="display:none;"' : ''; ?>>
					<div class="sf-field sf-toggle-field">
						<label for="sf_show_profile_pic"><?php esc_html_e( 'Show Profile Picture', 'social-feed' ); ?></label>
						<label class="sf-toggle">
							<input type="checkbox" id="sf_show_profile_pic" name="show_profile_pic" value="1" <?php checked( $settings['show_profile_pic'] ); ?>>
							<span class="sf-toggle-slider"></span>
						</label>
					</div>
					<div class="sf-field sf-toggle-field">
						<label for="sf_show_username"><?php esc_html_e( 'Show Username', 'social-feed' ); ?></label>
						<label class="sf-toggle">
							<input type="checkbox" id="sf_show_username" name="show_username" value="1" <?php checked( $settings['show_username'] ); ?>>
							<span class="sf-toggle-slider"></span>
						</label>
					</div>
					<div class="sf-field sf-toggle-field">
						<label for="sf_show_followers"><?php esc_html_e( 'Show Follower Count', 'social-feed' ); ?></label>
						<label class="sf-toggle">
							<input type="checkbox" id="sf_show_followers" name="show_followers" value="1" <?php checked( $settings['show_followers'] ); ?>>
							<span class="sf-toggle-slider"></span>
						</label>
					</div>
					<div class="sf-field sf-toggle-field">
						<label for="sf_show_bio"><?php esc_html_e( 'Show Bio', 'social-feed' ); ?></label>
						<label class="sf-toggle">
							<input type="checkbox" id="sf_show_bio" name="show_bio" value="1" <?php checked( $settings['show_bio'] ); ?>>
							<span class="sf-toggle-slider"></span>
						</label>
					</div>

				<div class="sf-field sf-toggle-field">
					<label for="sf_show_follow_btn"><?php esc_html_e( 'Show Follow Button', 'social-feed' ); ?></label>
					<label class="sf-toggle">
						<input type="checkbox" id="sf_show_follow_btn" name="show_follow_btn" value="1" <?php checked( $settings['show_follow_btn'] ); ?>>
						<span class="sf-toggle-slider"></span>
					</label>
				</div>
			</div>
			<div class="sf-section sf-follow-btn-section" <?php echo ! $settings['show_follow_btn'] ? 'style="display:none;"' : ''; ?>>
				<div class="sf-section-title"><?php esc_html_e( 'Follow Button', 'social-feed' ); ?></div>
				<div class="sf-follow-btn-options">
					<div class="sf-field">
						<label for="sf_follow_btn_color"><?php esc_html_e( 'Button Color', 'social-feed' ); ?></label>
						<div class="sf-color-picker-wrap"><input type="text" id="sf_follow_btn_color" name="follow_btn_color" value="<?php echo esc_attr( $settings['follow_btn_color'] ); ?>" class="sf-color-picker"></div>
					</div>

					<div class="sf-field">
						<label for="sf_follow_btn_text"><?php esc_html_e( 'Button Text', 'social-feed' ); ?></label>
						<input type="text" id="sf_follow_btn_text" name="follow_btn_text" value="<?php echo esc_attr( $settings['follow_btn_text'] ); ?>">
					</div>
			</div>
		</div>
	</div>
	</div>
	<?php
	}

	/**
	 * Render Tab - Post Settings.
	 *
	 * @param array $settings Current settings.
	 */
	private static function render_tab_post_settings( $settings ) {
		?>
		<div class="sf-tab-content sf-tab-content-layout" data-tab="post_settings">
			<div class="sf-layout-panel-section">
				<div class="sf-layout-panel-section-title"><?php esc_html_e( 'Post Elements', 'social-feed' ); ?></div>
				<div class="sf-field sf-toggle-field">
					<label for="sf_show_caption"><?php esc_html_e( 'Show Caption', 'social-feed' ); ?></label>
					<label class="sf-toggle">
						<input type="checkbox" id="sf_show_caption" name="show_caption" value="1" <?php checked( $settings['show_caption'] ); ?>>
						<span class="sf-toggle-slider"></span>
					</label>
				</div>
				<div class="sf-caption-options sf-field sf-field-number-px" <?php echo ! $settings['show_caption'] ? 'style="display:none;"' : ''; ?>>
					<label for="sf_caption_length"><?php esc_html_e( 'Caption Length', 'social-feed' ); ?></label>
					<div class="sf-number-px-wrap">
						<input type="number" id="sf_caption_length" name="caption_length" value="<?php echo esc_attr( $settings['caption_length'] ); ?>" min="1" max="500" step="1">
						<span class="sf-number-px-suffix">chars</span>
					</div>
				</div>
				<div class="sf-field sf-toggle-field">
					<label for="sf_show_likes"><?php esc_html_e( 'Show Likes', 'social-feed' ); ?></label>
					<label class="sf-toggle">
						<input type="checkbox" id="sf_show_likes" name="show_likes" value="1" <?php checked( $settings['show_likes'] ); ?>>
						<span class="sf-toggle-slider"></span>
					</label>
				</div>
				<div class="sf-field sf-toggle-field">
					<label for="sf_show_comments"><?php esc_html_e( 'Show Comments', 'social-feed' ); ?></label>
					<label class="sf-toggle">
						<input type="checkbox" id="sf_show_comments" name="show_comments" value="1" <?php checked( $settings['show_comments'] ); ?>>
						<span class="sf-toggle-slider"></span>
					</label>
				</div>
				<div class="sf-field sf-toggle-field">
					<label for="sf_show_date"><?php esc_html_e( 'Show Time', 'social-feed' ); ?></label>
					<label class="sf-toggle">
						<input type="checkbox" id="sf_show_date" name="show_date" value="1" <?php checked( $settings['show_date'] ); ?>>
						<span class="sf-toggle-slider"></span>
					</label>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Tab - Ballu.
	 *
	 * @param array $settings Current settings.
	 */
	private static function render_tab_ballu( $settings ) {
		?>
		<div class="sf-tab-content sf-tab-content-layout" data-tab="ballu">
			<div class="sf-layout-panel-section">
				<div class="sf-layout-panel-section-title"><?php esc_html_e( 'Post Style', 'social-feed' ); ?></div>
				<div class="sf-field sf-toggle-field">
					<label for="sf_ballu_show_caption"><?php esc_html_e( 'Show Caption', 'social-feed' ); ?></label>
					<label class="sf-toggle">
						<input type="checkbox" id="sf_ballu_show_caption" name="show_caption" value="1" <?php checked( $settings['show_caption'] ); ?>>
						<span class="sf-toggle-slider"></span>
					</label>
				</div>
				<div class="sf-ballu-caption-options sf-field sf-field-number-px" <?php echo ! $settings['show_caption'] ? 'style="display:none;"' : ''; ?>>
					<label for="sf_ballu_caption_length"><?php esc_html_e( 'Caption Length', 'social-feed' ); ?></label>
					<div class="sf-number-px-wrap">
						<input type="number" id="sf_ballu_caption_length" name="caption_length" value="<?php echo esc_attr( $settings['caption_length'] ); ?>" min="1" max="500" step="1">
						<span class="sf-number-px-suffix">px</span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Tab 6 - Load More.
	 *
	 * @param array $settings Current settings.
	 */
	private static function render_tab_loadmore( $settings ) {
		?>
		<div class="sf-tab-content" data-tab="loadmore">
			<div class="sf-section">
				<div class="sf-section-title"><?php esc_html_e( 'Load More Type', 'social-feed' ); ?></div>
				<div class="sf-field">
					<label><?php esc_html_e( 'Load More Type', 'social-feed' ); ?></label>
					<div class="sf-radio-group">
					<label class="sf-radio-option">
						<input type="radio" name="loadmore_type" value="button" <?php checked( $settings['loadmore_type'], 'button' ); ?>>
						<span><?php esc_html_e( 'Button', 'social-feed' ); ?></span>
					</label>
					<label class="sf-radio-option">
						<input type="radio" name="loadmore_type" value="scroll" <?php checked( $settings['loadmore_type'], 'scroll' ); ?>>
						<span><?php esc_html_e( 'Infinite Scroll', 'social-feed' ); ?></span>
					</label>
					<label class="sf-radio-option">
						<input type="radio" name="loadmore_type" value="pagination" <?php checked( $settings['loadmore_type'], 'pagination' ); ?>>
						<span><?php esc_html_e( 'Pagination', 'social-feed' ); ?></span>
					</label>
					<label class="sf-radio-option">
						<input type="radio" name="loadmore_type" value="none" <?php checked( $settings['loadmore_type'], 'none' ); ?>>
						<span><?php esc_html_e( 'None', 'social-feed' ); ?></span>
					</label>
					</div>
					</div>
				</div>
			</div>
			<div class="sf-section sf-loadmore-options sf-loadmore-button-section" <?php echo 'none' === $settings['loadmore_type'] || 'button' !== $settings['loadmore_type'] ? 'style="display:none;"' : ''; ?>>
				<div class="sf-section-title"><?php esc_html_e( 'Button Appearance', 'social-feed' ); ?></div>
				<div class="sf-field">
					<label for="sf_loadmore_text"><?php esc_html_e( 'Button Text', 'social-feed' ); ?></label>
					<input type="text" id="sf_loadmore_text" name="loadmore_text" value="<?php echo esc_attr( $settings['loadmore_text'] ); ?>">
				</div>
				<div class="sf-field">
					<label for="sf_loadmore_bg_color"><?php esc_html_e( 'Button Background', 'social-feed' ); ?></label>
					<div class="sf-color-picker-wrap"><input type="text" id="sf_loadmore_bg_color" name="loadmore_bg_color" value="<?php echo esc_attr( $settings['loadmore_bg_color'] ); ?>" class="sf-color-picker"></div>
				</div>
			</div>
			<div class="sf-section sf-loadmore-options" <?php echo 'none' === $settings['loadmore_type'] ? 'style="display:none;"' : ''; ?>>
				<div class="sf-section-title"><?php esc_html_e( 'Loading', 'social-feed' ); ?></div>
				<div class="sf-field">
					<label for="sf_posts_per_load"><?php esc_html_e( 'Posts to Load', 'social-feed' ); ?></label>
					<input type="number" id="sf_posts_per_load" name="posts_per_load" value="<?php echo esc_attr( $settings['posts_per_load'] ); ?>" min="1" max="50">
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Tab 7 - Advanced.
	 *
	 * @param array $settings Current settings.
	 * @param bool  $is_pro   Whether Pro version.
	 */
	private static function render_tab_advanced( $settings, $is_pro ) {
		?>
		<div class="sf-tab-content" data-tab="advanced">
			<div class="sf-section">
				<div class="sf-section-title"><?php esc_html_e( 'Customization', 'social-feed' ); ?></div>
			<div class="sf-field sf-field-textarea">
				<label for="sf_custom_css"><?php esc_html_e( 'Custom CSS', 'social-feed' ); ?></label>
				<textarea id="sf_custom_css" name="custom_css" rows="8" class="sf-code-textarea" placeholder="<?php esc_attr_e( '.sf-feed { /* your styles */ }', 'social-feed' ); ?>"><?php echo esc_textarea( $settings['custom_css'] ); ?></textarea>
			</div>
			</div>
			<div class="sf-section">
				<div class="sf-section-title"><?php esc_html_e( 'Performance &amp; Privacy', 'social-feed' ); ?></div>
			<div class="sf-field sf-toggle-field">
				<label for="sf_lazy_load"><?php esc_html_e( 'Enable Lazy Loading', 'social-feed' ); ?></label>
				<label class="sf-toggle">
					<input type="checkbox" id="sf_lazy_load" name="lazy_load" value="1" <?php checked( $settings['lazy_load'] ); ?>>
					<span class="sf-toggle-slider"></span>
				</label>
			</div>

			<div class="sf-field sf-toggle-field">
				<label for="sf_gdpr_mode"><?php esc_html_e( 'GDPR Mode', 'social-feed' ); ?></label>
				<label class="sf-toggle">
					<input type="checkbox" id="sf_gdpr_mode" name="gdpr_mode" value="1" <?php checked( $settings['gdpr_mode'] ); ?>>
					<span class="sf-toggle-slider"></span>
				</label>
				<p class="sf-field-desc"><?php esc_html_e( 'Loads images only after user consent.', 'social-feed' ); ?></p>
			</div>

			<div class="sf-field sf-toggle-field <?php echo ! $is_pro ? 'sf-pro-locked' : ''; ?>">
				<label for="sf_show_credit"><?php esc_html_e( 'Show Credit Link', 'social-feed' ); ?></label>
				<label class="sf-toggle">
					<input type="checkbox" id="sf_show_credit" name="show_credit" value="1" <?php checked( $settings['show_credit'] ); ?> <?php echo ! $is_pro ? 'disabled' : ''; ?>>
					<span class="sf-toggle-slider"></span>
				</label>
				<?php if ( ! $is_pro ) : ?>
					<span class="sf-pro-badge"><?php esc_html_e( 'Pro', 'social-feed' ); ?></span>
				<?php endif; ?>
			</div>
			</div>
		</div>
		<?php
	}
}
