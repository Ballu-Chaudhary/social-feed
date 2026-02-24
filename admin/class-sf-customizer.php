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
		?>
		<div class="sf-customizer-wrap" data-feed-id="<?php echo esc_attr( $feed_id ); ?>">
			<!-- Left Panel - Settings -->
			<div class="sf-customizer-panel sf-customizer-settings">
				<div class="sf-customizer-header">
					<h2><?php echo $feed_id ? esc_html__( 'Edit Feed', 'social-feed' ) : esc_html__( 'Create Feed', 'social-feed' ); ?></h2>
				</div>

				<!-- Tabs Navigation -->
				<div class="sf-customizer-tabs">
					<button type="button" class="sf-tab-btn active" data-tab="feed">
						<span class="dashicons dashicons-rss"></span>
						<?php esc_html_e( 'Feed', 'social-feed' ); ?>
					</button>
					<button type="button" class="sf-tab-btn" data-tab="layout">
						<span class="dashicons dashicons-grid-view"></span>
						<?php esc_html_e( 'Layout', 'social-feed' ); ?>
					</button>
					<button type="button" class="sf-tab-btn" data-tab="design">
						<span class="dashicons dashicons-art"></span>
						<?php esc_html_e( 'Design', 'social-feed' ); ?>
					</button>
					<button type="button" class="sf-tab-btn" data-tab="header">
						<span class="dashicons dashicons-admin-users"></span>
						<?php esc_html_e( 'Header', 'social-feed' ); ?>
					</button>
					<button type="button" class="sf-tab-btn" data-tab="posts">
						<span class="dashicons dashicons-format-image"></span>
						<?php esc_html_e( 'Posts', 'social-feed' ); ?>
					</button>
					<button type="button" class="sf-tab-btn" data-tab="loadmore">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Load More', 'social-feed' ); ?>
					</button>
					<button type="button" class="sf-tab-btn" data-tab="advanced">
						<span class="dashicons dashicons-admin-tools"></span>
						<?php esc_html_e( 'Advanced', 'social-feed' ); ?>
					</button>
				</div>

				<!-- Tab Content -->
				<div class="sf-customizer-content">
					<?php
					self::render_tab_feed( $settings, $accounts );
					self::render_tab_layout( $settings );
					self::render_tab_design( $settings );
					self::render_tab_header( $settings );
					self::render_tab_posts( $settings );
					self::render_tab_loadmore( $settings );
					self::render_tab_advanced( $settings, $is_pro );
					?>
				</div>

				<!-- Bottom Save Bar -->
				<div class="sf-customizer-footer">
					<div class="sf-footer-left">
						<div class="sf-shortcode-display" <?php echo ! $feed_id ? 'style="display:none;"' : ''; ?>>
							<label><?php esc_html_e( 'Shortcode:', 'social-feed' ); ?></label>
							<code class="sf-generated-shortcode">[social_feed id="<?php echo esc_attr( $feed_id ); ?>"]</code>
							<button type="button" class="sf-copy-btn" data-copy="[social_feed id=&quot;<?php echo esc_attr( $feed_id ); ?>&quot;]">
								<span class="dashicons dashicons-clipboard"></span>
							</button>
						</div>
					</div>
					<div class="sf-footer-right">
						<span class="sf-unsaved-indicator" style="display:none;">
							<span class="sf-unsaved-dot"></span>
							<?php esc_html_e( 'Unsaved changes', 'social-feed' ); ?>
						</span>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=social-feed-feeds' ) ); ?>" class="button sf-cancel-btn sf-back-nav" data-back-url="<?php echo esc_url( admin_url( 'admin.php?page=social-feed-feeds' ) ); ?>">
							<?php esc_html_e( 'Cancel', 'social-feed' ); ?>
						</a>
						<button type="button" class="button button-primary sf-save-feed">
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e( 'Save Feed', 'social-feed' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Right Panel - Preview -->
			<div class="sf-customizer-panel sf-customizer-preview">
				<div class="sf-preview-header">
					<span class="sf-preview-label"><?php esc_html_e( 'Live Preview', 'social-feed' ); ?></span>
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
			'layout'              => 'grid',
			'columns_desktop'     => 3,
			'columns_tablet'      => 2,
			'columns_mobile'      => 1,
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
					<label for="sf_name"><?php esc_html_e( 'Feed Name', 'social-feed' ); ?></label>
					<input type="text" id="sf_name" name="name" value="<?php echo esc_attr( $settings['name'] ); ?>" placeholder="<?php esc_attr_e( 'My Instagram Feed', 'social-feed' ); ?>">
				</div>
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
	 * Render Tab 2 - Layout.
	 *
	 * @param array $settings Current settings.
	 */
	private static function render_tab_layout( $settings ) {
		?>
		<div class="sf-tab-content" data-tab="layout">
			<div class="sf-section">
				<div class="sf-section-title"><?php esc_html_e( 'Layout Type', 'social-feed' ); ?></div>
				<div class="sf-field">
					<label><?php esc_html_e( 'Layout Type', 'social-feed' ); ?></label>
					<div class="sf-layout-options">
					<?php
					$layouts = array(
						'grid'     => array( 'icon' => 'dashicons-grid-view', 'label' => __( 'Grid', 'social-feed' ) ),
						'list'     => array( 'icon' => 'dashicons-list-view', 'label' => __( 'List', 'social-feed' ) ),
						'masonry'  => array( 'icon' => 'dashicons-layout', 'label' => __( 'Masonry', 'social-feed' ) ),
						'carousel' => array( 'icon' => 'dashicons-slides', 'label' => __( 'Carousel', 'social-feed' ) ),
					);
					foreach ( $layouts as $value => $layout ) :
						?>
						<label class="sf-layout-option <?php echo $settings['layout'] === $value ? 'active' : ''; ?>">
							<input type="radio" name="layout" value="<?php echo esc_attr( $value ); ?>" <?php checked( $settings['layout'], $value ); ?>>
							<span class="dashicons <?php echo esc_attr( $layout['icon'] ); ?>"></span>
							<span class="sf-layout-label"><?php echo esc_html( $layout['label'] ); ?></span>
						</label>
					<?php endforeach; ?>
					</div>
				</div>
			</div>
			<div class="sf-section">
				<div class="sf-section-title"><?php esc_html_e( 'Columns &amp; Spacing', 'social-feed' ); ?></div>
				<div class="sf-field">
					<label for="sf_columns_desktop"><?php esc_html_e( 'Columns (Desktop)', 'social-feed' ); ?></label>
					<div class="sf-range-wrapper">
						<input type="range" id="sf_columns_desktop" name="columns_desktop" value="<?php echo esc_attr( $settings['columns_desktop'] ); ?>" min="1" max="6">
						<span class="sf-range-value"><?php echo esc_html( $settings['columns_desktop'] ); ?></span>
					</div>
				</div>
				<div class="sf-field">
					<label for="sf_columns_tablet"><?php esc_html_e( 'Columns (Tablet)', 'social-feed' ); ?></label>
					<div class="sf-range-wrapper">
						<input type="range" id="sf_columns_tablet" name="columns_tablet" value="<?php echo esc_attr( $settings['columns_tablet'] ); ?>" min="1" max="4">
						<span class="sf-range-value"><?php echo esc_html( $settings['columns_tablet'] ); ?></span>
					</div>
				</div>
				<div class="sf-field">
					<label for="sf_columns_mobile"><?php esc_html_e( 'Columns (Mobile)', 'social-feed' ); ?></label>
					<div class="sf-range-wrapper">
						<input type="range" id="sf_columns_mobile" name="columns_mobile" value="<?php echo esc_attr( $settings['columns_mobile'] ); ?>" min="1" max="2">
						<span class="sf-range-value"><?php echo esc_html( $settings['columns_mobile'] ); ?></span>
					</div>
				</div>
				<div class="sf-field">
					<label for="sf_image_padding"><?php esc_html_e( 'Image Gap', 'social-feed' ); ?></label>
					<div class="sf-range-wrapper">
						<input type="range" id="sf_image_padding" name="image_padding" value="<?php echo esc_attr( $settings['image_padding'] ); ?>" min="0" max="20">
						<span class="sf-range-value"><?php echo esc_html( $settings['image_padding'] ); ?>px</span>
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
		<?php
	}

	/**
	 * Render Tab 5 - Posts.
	 *
	 * @param array $settings Current settings.
	 */
	private static function render_tab_posts( $settings ) {
		?>
		<div class="sf-tab-content" data-tab="posts">
			<div class="sf-section">
				<div class="sf-section-title"><?php esc_html_e( 'Post Content', 'social-feed' ); ?></div>
			<div class="sf-field sf-toggle-field">
				<label for="sf_show_caption"><?php esc_html_e( 'Show Caption', 'social-feed' ); ?></label>
				<label class="sf-toggle">
					<input type="checkbox" id="sf_show_caption" name="show_caption" value="1" <?php checked( $settings['show_caption'] ); ?>>
					<span class="sf-toggle-slider"></span>
				</label>
			</div>

			<div class="sf-caption-options" <?php echo ! $settings['show_caption'] ? 'style="display:none;"' : ''; ?>>
				<div class="sf-field">
					<label for="sf_caption_length"><?php esc_html_e( 'Caption Length', 'social-feed' ); ?></label>
					<div class="sf-range-wrapper">
						<input type="range" id="sf_caption_length" name="caption_length" value="<?php echo esc_attr( $settings['caption_length'] ); ?>" min="50" max="300">
						<span class="sf-range-value"><?php echo esc_html( $settings['caption_length'] ); ?></span>
					</div>
				</div>
			</div>

			<div class="sf-field sf-toggle-field">
				<label for="sf_show_date"><?php esc_html_e( 'Show Date', 'social-feed' ); ?></label>
				<label class="sf-toggle">
					<input type="checkbox" id="sf_show_date" name="show_date" value="1" <?php checked( $settings['show_date'] ); ?>>
					<span class="sf-toggle-slider"></span>
				</label>
			</div>

			<div class="sf-field sf-toggle-field">
				<label for="sf_show_likes"><?php esc_html_e( 'Show Likes Count', 'social-feed' ); ?></label>
				<label class="sf-toggle">
					<input type="checkbox" id="sf_show_likes" name="show_likes" value="1" <?php checked( $settings['show_likes'] ); ?>>
					<span class="sf-toggle-slider"></span>
				</label>
			</div>

			<div class="sf-field sf-toggle-field">
				<label for="sf_show_comments"><?php esc_html_e( 'Show Comments Count', 'social-feed' ); ?></label>
				<label class="sf-toggle">
					<input type="checkbox" id="sf_show_comments" name="show_comments" value="1" <?php checked( $settings['show_comments'] ); ?>>
					<span class="sf-toggle-slider"></span>
				</label>
			</div>

			</div>
			<div class="sf-section">
				<div class="sf-section-title"><?php esc_html_e( 'Interaction', 'social-feed' ); ?></div>
			<div class="sf-field">
				<label><?php esc_html_e( 'Click Action', 'social-feed' ); ?></label>
				<div class="sf-radio-group">
					<label class="sf-radio-option">
						<input type="radio" name="click_action" value="link" <?php checked( $settings['click_action'], 'link' ); ?>>
						<span><?php esc_html_e( 'Open Original Link', 'social-feed' ); ?></span>
					</label>
					<label class="sf-radio-option">
						<input type="radio" name="click_action" value="popup" <?php checked( $settings['click_action'], 'popup' ); ?>>
						<span><?php esc_html_e( 'Open Popup', 'social-feed' ); ?></span>
					</label>
					<label class="sf-radio-option">
						<input type="radio" name="click_action" value="none" <?php checked( $settings['click_action'], 'none' ); ?>>
						<span><?php esc_html_e( 'Nothing', 'social-feed' ); ?></span>
					</label>
				</div>
			</div>

			<div class="sf-popup-options" <?php echo 'popup' !== $settings['click_action'] ? 'style="display:none;"' : ''; ?>>
				<div class="sf-field">
					<label for="sf_popup_style"><?php esc_html_e( 'Popup Style', 'social-feed' ); ?></label>
					<select id="sf_popup_style" name="popup_style">
						<option value="minimal" <?php selected( $settings['popup_style'], 'minimal' ); ?>><?php esc_html_e( 'Minimal', 'social-feed' ); ?></option>
						<option value="card" <?php selected( $settings['popup_style'], 'card' ); ?>><?php esc_html_e( 'Card', 'social-feed' ); ?></option>
						<option value="full" <?php selected( $settings['popup_style'], 'full' ); ?>><?php esc_html_e( 'Full', 'social-feed' ); ?></option>
					</select>
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
