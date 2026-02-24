<?php
/**
 * AJAX handlers for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Ajax
 */
class SF_Ajax {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_handlers();
	}

	/**
	 * Register all AJAX handlers.
	 */
	private function register_handlers() {
		$actions = array(
			'sf_preview_feed',
			'sf_save_feed',
			'sf_get_feed_data',
			'sf_delete_feed',
			'sf_duplicate_feed',
			'sf_update_feed_status',
			'sf_bulk_action',
			'sf_clear_cache',
			'sf_refresh_feed',
			'sf_save_settings',
			'sf_get_oauth_url',
			'sf_save_account',
			'sf_delete_account',
			'sf_reconnect_account',
			'sf_get_accounts_list',
			'sf_test_api_connection',
			'sf_clear_all_cache',
			'sf_clear_all_logs',
			'sf_load_more',
			'sf_activate_license',
			'sf_deactivate_license',
			'sf_check_license',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, str_replace( 'sf_', 'handle_', $action ) ) );
		}

		add_action( 'wp_ajax_nopriv_sf_load_more', array( $this, 'handle_load_more' ) );
	}

	/**
	 * Verify AJAX request.
	 *
	 * @return bool
	 */
	private function verify_request() {
		if ( ! check_ajax_referer( 'sf_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-feed' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'social-feed' ) ), 403 );
		}

		return true;
	}

	/**
	 * Handle preview feed request.
	 */
	public function handle_preview_feed() {
		$this->verify_request();

		$settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = SF_Helpers::sf_sanitize_array( $settings );
		$device   = isset( $_POST['device'] ) ? sanitize_key( $_POST['device'] ) : 'desktop';

		require_once SF_PLUGIN_PATH . 'admin/class-sf-customizer.php';
		$defaults = SF_Customizer::get_defaults();
		$settings = wp_parse_args( $settings, $defaults );

		$html = $this->render_preview_html( $settings, $device );

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Render preview HTML.
	 *
	 * @param array  $settings Feed settings.
	 * @param string $device   Device type.
	 * @return string
	 */
	private function render_preview_html( $settings, $device = 'desktop' ) {
		$layout  = isset( $settings['layout'] ) ? sanitize_key( $settings['layout'] ) : 'grid';
		$columns = $settings['columns_desktop'];
		if ( 'tablet' === $device ) {
			$columns = $settings['columns_tablet'];
		} elseif ( 'mobile' === $device ) {
			$columns = $settings['columns_mobile'];
		}

		$items = $this->get_preview_items( $settings );

		ob_start();
		?>
		<style>
			.sf-preview-feed {
				background: <?php echo esc_attr( $settings['bg_color'] ); ?>;
				color: <?php echo esc_attr( $settings['text_color'] ); ?>;
				padding: 16px;
				border-radius: 12px;
				box-shadow: 0 1px 3px rgba(0,0,0,0.08);
				overflow: hidden;
			}
			.sf-preview-header {
				display: flex;
				align-items: center;
				gap: 16px;
				margin-bottom: 16px;
				padding-bottom: 16px;
				border-bottom: 1px solid rgba(0,0,0,0.08);
			}
			.sf-preview-avatar {
				width: 48px;
				height: 48px;
				min-width: 48px;
				border-radius: 50%;
				background: #e5e7eb;
				object-fit: cover;
			}
			.sf-preview-header-info {
				flex: 1;
				min-width: 0;
			}
			.sf-preview-username { font-weight: 700; font-size: 14px; margin: 0 0 2px; }
			.sf-preview-followers { font-size: 12px; color: rgba(0,0,0,0.5); margin: 0; }
			.sf-preview-follow-btn {
				background: <?php echo esc_attr( $settings['follow_btn_color'] ); ?>;
				color: #fff;
				border: none;
				padding: 8px 18px;
				border-radius: 6px;
				font-size: 13px;
				font-weight: 600;
				cursor: pointer;
				flex-shrink: 0;
			}
			.sf-preview-grid {
				display: grid;
				grid-template-columns: repeat(<?php echo intval( $columns ); ?>, 1fr);
				gap: <?php echo intval( $settings['image_padding'] ); ?>px;
			}
			.sf-preview-masonry {
				column-count: <?php echo intval( $columns ); ?>;
				column-gap: <?php echo intval( $settings['image_padding'] ); ?>px;
			}
			.sf-preview-masonry .sf-preview-item {
				break-inside: avoid;
				margin-bottom: <?php echo intval( $settings['image_padding'] ); ?>px;
			}
			.sf-preview-masonry .sf-preview-item-inner { padding-bottom: 80%; }
			.sf-preview-list {
				display: flex;
				flex-direction: column;
				gap: <?php echo intval( $settings['image_padding'] ); ?>px;
			}
			.sf-preview-list .sf-preview-item {
				display: flex;
				gap: 16px;
				align-items: flex-start;
			}
			.sf-preview-list .sf-preview-item-inner {
				flex-shrink: 0;
				width: 120px;
				padding-bottom: 120px;
			}
			.sf-preview-list .sf-preview-content { flex: 1; padding-top: 0; }
			.sf-preview-carousel {
				display: flex;
				gap: <?php echo intval( $settings['image_padding'] ); ?>px;
				overflow-x: auto;
				scroll-snap-type: x mandatory;
				-webkit-overflow-scrolling: touch;
			}
			.sf-preview-carousel .sf-preview-item {
				flex: 0 0 calc((100% - <?php echo intval( $settings['image_padding'] ) * ( intval( $columns ) - 1 ); ?>px) / <?php echo intval( $columns ); ?>);
				min-width: 140px;
				scroll-snap-align: start;
			}
			.sf-preview-item {
				border-radius: <?php echo intval( $settings['border_radius'] ); ?>px;
				overflow: hidden;
				<?php if ( 'none' !== $settings['border_style'] ) : ?>
				border: 1px <?php echo esc_attr( $settings['border_style'] ); ?> <?php echo esc_attr( $settings['border_color'] ); ?>;
				<?php endif; ?>
			}
			.sf-preview-item-inner { position: relative; padding-bottom: 100%; background: #f3f4f6; }
			.sf-preview-item img {
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				object-fit: cover;
				display: block;
				transition: transform 0.3s, opacity 0.3s;
			}
			.sf-preview-item:hover .sf-preview-overlay { opacity: 1; }
			<?php if ( 'zoom' === $settings['hover_effect'] ) : ?>
			.sf-preview-item:hover img { transform: scale(1.05); }
			<?php elseif ( 'fade' === $settings['hover_effect'] ) : ?>
			.sf-preview-item:hover img { opacity: 0.85; }
			<?php endif; ?>
			.sf-preview-overlay {
				position: absolute;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background: linear-gradient(transparent 40%, rgba(0,0,0,0.6) 100%);
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 16px;
				color: #fff;
				font-size: 13px;
				opacity: 0;
				transition: opacity 0.3s;
			}
			.sf-preview-overlay span { display: flex; align-items: center; gap: 4px; }
			.sf-preview-content {
				padding: 10px 0 0;
				min-width: 0;
			}
			.sf-preview-caption {
				font-size: 12px;
				line-height: 1.4;
				display: -webkit-box;
				-webkit-line-clamp: 2;
				-webkit-box-orient: vertical;
				overflow: hidden;
				text-overflow: ellipsis;
			}
			.sf-preview-meta {
				display: flex;
				align-items: center;
				gap: 12px;
				font-size: 11px;
				color: rgba(0,0,0,0.5);
				margin-top: 6px;
			}
			.sf-preview-meta span { display: flex; align-items: center; gap: 4px; }
			.sf-preview-loadmore {
				text-align: center;
				margin-top: 20px;
			}
			.sf-preview-loadmore-btn {
				background: <?php echo esc_attr( $settings['loadmore_bg_color'] ); ?>;
				color: #fff;
				border: none;
				padding: 12px 24px;
				border-radius: 6px;
				cursor: pointer;
				font-size: 14px;
				font-weight: 500;
			}
			<?php if ( $settings['dark_mode'] ) : ?>
			.sf-preview-feed { background: #1a1a1a; color: #ffffff; }
			.sf-preview-followers, .sf-preview-meta { color: rgba(255,255,255,0.6); }
			<?php endif; ?>
			<?php echo wp_strip_all_tags( $settings['custom_css'] ); ?>
		</style>

		<div class="sf-preview-frame">
		<div class="sf-preview-feed">
			<?php if ( $settings['show_header'] ) : ?>
			<div class="sf-preview-header">
				<?php if ( $settings['show_profile_pic'] ) : ?>
				<div class="sf-preview-avatar"></div>
				<?php endif; ?>
				<div class="sf-preview-header-info">
					<?php if ( $settings['show_username'] ) : ?>
					<div class="sf-preview-username">@username</div>
					<?php endif; ?>
					<?php if ( $settings['show_followers'] ) : ?>
					<div class="sf-preview-followers">12.5K followers</div>
					<?php endif; ?>
				</div>
				<?php if ( $settings['show_follow_btn'] ) : ?>
				<button type="button" class="sf-preview-follow-btn"><?php echo esc_html( $settings['follow_btn_text'] ); ?></button>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<div class="sf-preview-<?php echo esc_attr( $layout ); ?>">
				<?php foreach ( $items as $item ) : ?>
				<div class="sf-preview-item">
					<div class="sf-preview-item-inner">
						<img src="<?php echo esc_url( $item['image'] ); ?>" alt="">
						<?php if ( $settings['show_likes'] || $settings['show_comments'] ) : ?>
						<div class="sf-preview-overlay">
							<?php if ( $settings['show_likes'] ) : ?>
							<span>♥ <?php echo esc_html( SF_Helpers::sf_format_number( $item['likes'] ) ); ?></span>
							<?php endif; ?>
							<?php if ( $settings['show_comments'] ) : ?>
							<span>💬 <?php echo esc_html( $item['comments'] ); ?></span>
							<?php endif; ?>
						</div>
						<?php endif; ?>
					</div>
					<?php if ( $settings['show_caption'] || $settings['show_likes'] || $settings['show_comments'] || $settings['show_date'] ) : ?>
					<div class="sf-preview-content">
						<?php if ( $settings['show_caption'] ) : ?>
						<div class="sf-preview-caption"><?php echo esc_html( SF_Helpers::sf_truncate_text( $item['caption'], intval( $settings['caption_length'] ) ) ); ?></div>
						<?php endif; ?>
						<?php if ( $settings['show_likes'] || $settings['show_comments'] || $settings['show_date'] ) : ?>
						<div class="sf-preview-meta">
							<?php if ( $settings['show_likes'] ) : ?>
							<span>♥ <?php echo esc_html( SF_Helpers::sf_format_number( $item['likes'] ) ); ?></span>
							<?php endif; ?>
							<?php if ( $settings['show_comments'] ) : ?>
							<span>💬 <?php echo esc_html( $item['comments'] ); ?></span>
							<?php endif; ?>
							<?php if ( $settings['show_date'] ) : ?>
							<span><?php echo esc_html( $item['date'] ); ?></span>
							<?php endif; ?>
						</div>
						<?php endif; ?>
					</div>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>

			<?php if ( 'none' !== $settings['loadmore_type'] ) : ?>
			<div class="sf-preview-loadmore">
				<?php if ( 'button' === $settings['loadmore_type'] ) : ?>
				<button type="button" class="sf-preview-loadmore-btn"><?php echo esc_html( $settings['loadmore_text'] ); ?></button>
				<?php elseif ( 'pagination' === $settings['loadmore_type'] ) : ?>
				<span style="opacity:0.7;">1 2 3 ...</span>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get preview items.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	private function get_preview_items( $settings ) {
		$count = min( intval( $settings['post_count'] ), 12 );
		$items = array();

		$captions = array(
			'Beautiful sunset at the beach today! 🌅',
			'Coffee time ☕️ #morningvibes',
			'Exploring new places and making memories',
			'Just living my best life 🎉',
			'Nature photography is my passion',
			'City lights and late nights ✨',
		);

		for ( $i = 0; $i < $count; $i++ ) {
			$items[] = array(
				'image'    => 'https://picsum.photos/seed/' . ( $i + 1 ) . '/400/400',
				'caption'  => $captions[ $i % count( $captions ) ],
				'likes'    => rand( 100, 50000 ),
				'comments' => rand( 5, 500 ),
				'date'     => rand( 1, 7 ) . 'd ago',
			);
		}

		return $items;
	}

	/**
	 * Handle save feed request.
	 */
	public function handle_save_feed() {
		$this->verify_request();

		$feed_id  = isset( $_POST['feed_id'] ) ? absint( $_POST['feed_id'] ) : 0;
		$settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

		if ( ! is_array( $settings ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'social-feed' ) ) );
		}

		$settings = SF_Helpers::sf_sanitize_array( $settings );

		require_once SF_PLUGIN_PATH . 'admin/class-sf-customizer.php';
		$defaults = SF_Customizer::get_defaults();
		$settings = wp_parse_args( $settings, $defaults );

		$feed_data = array(
			'name'       => ! empty( $settings['name'] ) ? $settings['name'] : __( 'Untitled Feed', 'social-feed' ),
			'platform'   => $settings['platform'],
			'account_id' => ! empty( $settings['account_id'] ) ? absint( $settings['account_id'] ) : null,
			'feed_type'  => $settings['feed_type'],
			'post_count' => absint( $settings['post_count'] ),
			'status'     => 'active',
		);

		if ( $feed_id ) {
			$result = SF_Database::update_feed( $feed_id, $feed_data );
			if ( false === $result ) {
				wp_send_json_error( array( 'message' => __( 'Failed to update feed.', 'social-feed' ) ) );
			}
		} else {
			$feed_id = SF_Database::create_feed( $feed_data );
			if ( ! $feed_id ) {
				wp_send_json_error( array( 'message' => __( 'Failed to create feed.', 'social-feed' ) ) );
			}
		}

		$meta_fields = array(
			'layout', 'columns_desktop', 'columns_tablet', 'columns_mobile', 'image_padding',
			'bg_color', 'text_color', 'border_style', 'border_color', 'border_radius',
			'hover_effect', 'dark_mode', 'show_header', 'show_profile_pic', 'show_username',
			'show_followers', 'show_bio', 'show_follow_btn', 'follow_btn_color', 'follow_btn_text',
			'show_caption', 'caption_length', 'show_date', 'show_likes', 'show_comments',
			'click_action', 'popup_style', 'loadmore_type', 'loadmore_text', 'loadmore_bg_color',
			'posts_per_load', 'custom_css', 'lazy_load', 'gdpr_mode', 'show_credit',
		);

		foreach ( $meta_fields as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				SF_Database::set_feed_meta_value( $feed_id, $key, $settings[ $key ] );
			}
		}

		SF_Helpers::sf_log_success(
			sprintf( 'Feed "%s" saved successfully.', $feed_data['name'] ),
			$feed_data['platform'],
			$feed_id
		);

		wp_send_json_success(
			array(
				'message'   => __( 'Feed saved successfully!', 'social-feed' ),
				'feed_id'   => $feed_id,
				'shortcode' => '[social_feed id="' . $feed_id . '"]',
				'redirect'  => admin_url( 'admin.php?page=social-feed-create&feed_id=' . $feed_id ),
			)
		);
	}

	/**
	 * Handle get feed data request.
	 */
	public function handle_get_feed_data() {
		$this->verify_request();

		$feed_id = isset( $_POST['feed_id'] ) ? absint( $_POST['feed_id'] ) : 0;

		if ( ! $feed_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid feed ID.', 'social-feed' ) ) );
		}

		$feed = SF_Database::get_feed( $feed_id );
		if ( ! $feed ) {
			wp_send_json_error( array( 'message' => __( 'Feed not found.', 'social-feed' ) ) );
		}

		$meta = SF_Database::get_all_feed_meta( $feed_id );

		$data = array_merge(
			array(
				'id'         => $feed['id'],
				'name'       => $feed['name'],
				'platform'   => $feed['platform'],
				'account_id' => $feed['account_id'],
				'feed_type'  => $feed['feed_type'],
				'post_count' => $feed['post_count'],
			),
			$meta
		);

		wp_send_json_success( array( 'feed' => $data ) );
	}

	/**
	 * Handle delete feed request.
	 */
	public function handle_delete_feed() {
		$this->verify_request();

		$feed_id = isset( $_POST['feed_id'] ) ? absint( $_POST['feed_id'] ) : 0;

		if ( ! $feed_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid feed ID.', 'social-feed' ) ) );
		}

		$feed = SF_Database::get_feed( $feed_id );
		if ( ! $feed ) {
			wp_send_json_error( array( 'message' => __( 'Feed not found.', 'social-feed' ) ) );
		}

		$result = SF_Database::delete_feed( $feed_id );

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete feed.', 'social-feed' ) ) );
		}

		SF_Helpers::sf_log_info(
			sprintf( 'Feed "%s" deleted.', $feed['name'] ),
			$feed['platform'],
			$feed_id
		);

		wp_send_json_success( array( 'message' => __( 'Feed deleted.', 'social-feed' ) ) );
	}

	/**
	 * Handle duplicate feed request.
	 */
	public function handle_duplicate_feed() {
		$this->verify_request();

		$feed_id = isset( $_POST['feed_id'] ) ? absint( $_POST['feed_id'] ) : 0;

		if ( ! $feed_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid feed ID.', 'social-feed' ) ) );
		}

		$feed = SF_Database::get_feed( $feed_id );
		if ( ! $feed ) {
			wp_send_json_error( array( 'message' => __( 'Feed not found.', 'social-feed' ) ) );
		}

		$meta = SF_Database::get_all_feed_meta( $feed_id );

		$new_feed_data = array(
			'name'       => $feed['name'] . ' (Copy)',
			'platform'   => $feed['platform'],
			'account_id' => $feed['account_id'],
			'feed_type'  => $feed['feed_type'],
			'post_count' => $feed['post_count'],
			'status'     => 'active',
		);

		$new_feed_id = SF_Database::create_feed( $new_feed_data );

		if ( ! $new_feed_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to duplicate feed.', 'social-feed' ) ) );
		}

		foreach ( $meta as $key => $value ) {
			SF_Database::set_feed_meta_value( $new_feed_id, $key, $value );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Feed duplicated.', 'social-feed' ),
				'feed_id'  => $new_feed_id,
				'redirect' => admin_url( 'admin.php?page=social-feed-create&feed_id=' . $new_feed_id ),
			)
		);
	}

	/**
	 * Handle update feed status request.
	 */
	public function handle_update_feed_status() {
		$this->verify_request();

		$feed_id = isset( $_POST['feed_id'] ) ? absint( $_POST['feed_id'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';

		if ( ! $feed_id || ! in_array( $status, array( 'active', 'paused' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'social-feed' ) ) );
		}

		$result = SF_Database::update_feed( $feed_id, array( 'status' => $status ) );

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update status.', 'social-feed' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Status updated.', 'social-feed' ) ) );
	}

	/**
	 * Handle bulk action request.
	 */
	public function handle_bulk_action() {
		$this->verify_request();

		$action   = isset( $_POST['bulk_action'] ) ? sanitize_key( $_POST['bulk_action'] ) : '';
		$feed_ids = isset( $_POST['feed_ids'] ) ? array_map( 'absint', $_POST['feed_ids'] ) : array();

		if ( empty( $action ) || empty( $feed_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'social-feed' ) ) );
		}

		$count = 0;

		foreach ( $feed_ids as $feed_id ) {
			if ( 'delete' === $action ) {
				if ( SF_Database::delete_feed( $feed_id ) !== false ) {
					$count++;
				}
			} elseif ( 'pause' === $action ) {
				if ( SF_Database::update_feed( $feed_id, array( 'status' => 'paused' ) ) !== false ) {
					$count++;
				}
			}
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					_n( '%d feed updated.', '%d feeds updated.', $count, 'social-feed' ),
					$count
				),
			)
		);
	}

	/**
	 * Handle clear cache request.
	 */
	public function handle_clear_cache() {
		$this->verify_request();

		$count = SF_Helpers::sf_clear_all_cache();

		SF_Helpers::sf_log_info( sprintf( 'Cache cleared. %d transients deleted.', $count ) );

		wp_send_json_success(
			array(
				'message' => sprintf(
					__( 'Cache cleared successfully! %d items removed.', 'social-feed' ),
					$count
				),
			)
		);
	}

	/**
	 * Handle refresh feed request.
	 */
	public function handle_refresh_feed() {
		$this->verify_request();

		$feed_id = isset( $_POST['feed_id'] ) ? absint( $_POST['feed_id'] ) : 0;

		if ( ! $feed_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid feed ID.', 'social-feed' ) ) );
		}

		$feed = SF_Database::get_feed( $feed_id );

		if ( ! $feed ) {
			wp_send_json_error( array( 'message' => __( 'Feed not found.', 'social-feed' ) ) );
		}

		SF_Cache::delete( $feed_id );

		$result = SF_Feed_Manager::fetch_from_api( $feed_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$count = isset( $result['items'] ) ? count( $result['items'] ) : 0;

		SF_Helpers::sf_log_info(
			sprintf( 'Feed "%s" manually refreshed. %d items fetched.', $feed['name'], $count ),
			$feed['platform'],
			$feed_id
		);

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: Number of items */
					__( 'Feed refreshed successfully! %d items fetched.', 'social-feed' ),
					$count
				),
				'count'   => $count,
			)
		);
	}

	/**
	 * Handle save settings request.
	 */
	public function handle_save_settings() {
		$this->verify_request();

		$settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = SF_Helpers::sf_sanitize_array( $settings );

		update_option( 'sf_settings', $settings );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'social-feed' ) ) );
	}

	/**
	 * Handle get OAuth URL request.
	 */
	public function handle_get_oauth_url() {
		$this->verify_request();

		$platform = isset( $_POST['platform'] ) ? sanitize_key( $_POST['platform'] ) : '';

		if ( ! in_array( $platform, array( 'instagram', 'youtube', 'facebook' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid platform.', 'social-feed' ) ) );
		}

		require_once SF_PLUGIN_PATH . 'admin/class-sf-accounts.php';
		$url = SF_Accounts::get_oauth_url( $platform );

		if ( is_wp_error( $url ) ) {
			wp_send_json_error( array( 'message' => $url->get_error_message() ) );
		}

		if ( empty( $url ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'API credentials not configured. Please add your API keys in Settings.', 'social-feed' ),
				)
			);
		}

		wp_send_json_success( array( 'url' => $url ) );
	}

	/**
	 * Handle save account request (after OAuth success).
	 */
	public function handle_save_account() {
		$this->verify_request();

		$platform       = isset( $_POST['platform'] ) ? sanitize_key( $_POST['platform'] ) : '';
		$account_name   = isset( $_POST['account_name'] ) ? sanitize_text_field( $_POST['account_name'] ) : '';
		$account_id_ext = isset( $_POST['account_id_ext'] ) ? sanitize_text_field( $_POST['account_id_ext'] ) : '';
		$access_token   = isset( $_POST['access_token'] ) ? sanitize_text_field( $_POST['access_token'] ) : '';
		$refresh_token  = isset( $_POST['refresh_token'] ) ? sanitize_text_field( $_POST['refresh_token'] ) : '';
		$expires_in     = isset( $_POST['expires_in'] ) ? absint( $_POST['expires_in'] ) : 0;
		$profile_pic    = isset( $_POST['profile_pic'] ) ? esc_url_raw( $_POST['profile_pic'] ) : '';
		$already_saved  = isset( $_POST['already_saved'] ) && $_POST['already_saved'];

		if ( $already_saved ) {
			wp_send_json_success(
				array(
					'message'      => __( 'Account connected successfully!', 'social-feed' ),
					'account_name' => $account_name,
				)
			);
		}

		if ( empty( $platform ) || empty( $account_name ) || empty( $account_id_ext ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing required data.', 'social-feed' ) ) );
		}

		$existing = SF_Database::get_account_by_external_id( $platform, $account_id_ext );

		$encrypted_access  = ! empty( $access_token ) ? SF_Helpers::sf_encrypt( $access_token ) : null;
		$encrypted_refresh = ! empty( $refresh_token ) ? SF_Helpers::sf_encrypt( $refresh_token ) : null;
		$token_expires     = $expires_in > 0 ? gmdate( 'Y-m-d H:i:s', time() + $expires_in ) : null;

		$account_data = array(
			'platform'       => $platform,
			'account_name'   => $account_name,
			'account_id_ext' => $account_id_ext,
			'access_token'   => $encrypted_access,
			'refresh_token'  => $encrypted_refresh,
			'token_expires'  => $token_expires,
			'profile_pic'    => $profile_pic,
			'is_connected'   => 1,
			'last_error'     => null,
		);

		if ( $existing ) {
			$result     = SF_Database::update_account( $existing['id'], $account_data );
			$account_id = $existing['id'];
		} else {
			$account_id = SF_Database::create_account( $account_data );
			$result     = $account_id ? true : false;
		}

		if ( ! $result && ! $account_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save account.', 'social-feed' ) ) );
		}

		SF_Helpers::sf_log_success(
			sprintf( 'Account @%s connected successfully.', $account_name ),
			$platform
		);

		wp_send_json_success(
			array(
				'message'      => __( 'Account connected successfully!', 'social-feed' ),
				'account_id'   => $account_id,
				'account_name' => $account_name,
			)
		);
	}

	/**
	 * Handle delete account request.
	 */
	public function handle_delete_account() {
		$this->verify_request();

		$account_id = isset( $_POST['account_id'] ) ? absint( $_POST['account_id'] ) : 0;

		if ( ! $account_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid account ID.', 'social-feed' ) ) );
		}

		$account = SF_Database::get_account( $account_id );
		if ( ! $account ) {
			wp_send_json_error( array( 'message' => __( 'Account not found.', 'social-feed' ) ) );
		}

		$feeds = SF_Database::get_all_feeds( array( 'limit' => 1000 ) );
		$feeds_using = 0;
		foreach ( $feeds as $feed ) {
			if ( (int) $feed['account_id'] === $account_id ) {
				$feeds_using++;
			}
		}

		if ( $feeds_using > 0 ) {
			foreach ( $feeds as $feed ) {
				if ( (int) $feed['account_id'] === $account_id ) {
					SF_Database::update_feed( $feed['id'], array( 'account_id' => null ) );
				}
			}
		}

		$result = SF_Database::delete_account( $account_id );

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete account.', 'social-feed' ) ) );
		}

		SF_Helpers::sf_log_info(
			sprintf( 'Account @%s deleted.', $account['account_name'] ),
			$account['platform']
		);

		wp_send_json_success(
			array(
				'message' => __( 'Account deleted.', 'social-feed' ),
			)
		);
	}

	/**
	 * Handle reconnect account request.
	 */
	public function handle_reconnect_account() {
		$this->verify_request();

		$account_id = isset( $_POST['account_id'] ) ? absint( $_POST['account_id'] ) : 0;
		$platform   = isset( $_POST['platform'] ) ? sanitize_key( $_POST['platform'] ) : '';

		if ( ! $account_id || empty( $platform ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'social-feed' ) ) );
		}

		require_once SF_PLUGIN_PATH . 'admin/class-sf-accounts.php';
		$url = SF_Accounts::get_oauth_url( $platform );

		if ( empty( $url ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'API credentials not configured.', 'social-feed' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'url'        => $url,
				'account_id' => $account_id,
			)
		);
	}

	/**
	 * Handle get accounts list request.
	 */
	public function handle_get_accounts_list() {
		$this->verify_request();

		$accounts = SF_Database::get_all_accounts();

		$accounts_data = array();
		foreach ( $accounts as $account ) {
			$feeds = SF_Database::get_all_feeds( array( 'limit' => 1000 ) );
			$feeds_count = 0;
			foreach ( $feeds as $feed ) {
				if ( (int) $feed['account_id'] === (int) $account['id'] ) {
					$feeds_count++;
				}
			}

			$accounts_data[] = array(
				'id'            => $account['id'],
				'platform'      => $account['platform'],
				'account_name'  => $account['account_name'],
				'profile_pic'   => $account['profile_pic'],
				'is_connected'  => (bool) $account['is_connected'],
				'token_expires' => $account['token_expires'],
				'last_error'    => $account['last_error'],
				'feeds_count'   => $feeds_count,
			);
		}

		wp_send_json_success( array( 'accounts' => $accounts_data ) );
	}

	/**
	 * Handle test API connection request.
	 */
	public function handle_test_api_connection() {
		$this->verify_request();

		$platform = isset( $_POST['platform'] ) ? sanitize_key( $_POST['platform'] ) : '';
		$settings = isset( $_POST['settings'] ) ? SF_Helpers::sf_sanitize_array( wp_unslash( $_POST['settings'] ) ) : array();

		if ( empty( $platform ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid platform.', 'social-feed' ) ) );
		}

		if ( 'instagram' !== $platform ) {
			wp_send_json_error( array( 'message' => __( 'Only Instagram is supported.', 'social-feed' ) ) );
		}
		$result = $this->test_instagram_connection( $settings );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Test Instagram API connection.
	 *
	 * @param array $settings API settings.
	 * @return array|WP_Error
	 */
	private function test_instagram_connection( $settings ) {
		$app_id     = $settings['instagram_app_id'] ?? '';
		$app_secret = $settings['instagram_app_secret'] ?? '';

		if ( empty( $app_id ) || empty( $app_secret ) ) {
			return new WP_Error( 'missing_credentials', __( 'App ID and App Secret are required.', 'social-feed' ) );
		}

		$url = add_query_arg(
			array(
				'client_id'     => $app_id,
				'client_secret' => $app_secret,
				'grant_type'    => 'client_credentials',
			),
			'https://graph.facebook.com/oauth/access_token'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'api_error', $body['error']['message'] ?? __( 'Invalid credentials.', 'social-feed' ) );
		}

		if ( ! empty( $body['access_token'] ) ) {
			return array(
				'message' => __( 'Connection successful! Your Instagram credentials are valid.', 'social-feed' ),
			);
		}

		return new WP_Error( 'unknown_error', __( 'Could not verify credentials.', 'social-feed' ) );
	}

	/**
	 * Test YouTube API connection.
	 *
	 * @param array $settings API settings.
	 * @return array|WP_Error
	 */
	private function test_youtube_connection( $settings ) {
		$api_key = $settings['youtube_api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_credentials', __( 'API Key is required.', 'social-feed' ) );
		}

		$url = add_query_arg(
			array(
				'part' => 'snippet',
				'id'   => 'UC_x5XG1OV2P6uZZ5FSM9Ttw',
				'key'  => $api_key,
			),
			'https://www.googleapis.com/youtube/v3/channels'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 403 === $status_code && isset( $body['error']['errors'][0]['reason'] ) ) {
			$reason = $body['error']['errors'][0]['reason'];
			if ( 'accessNotConfigured' === $reason ) {
				return new WP_Error( 'api_disabled', __( 'YouTube Data API is not enabled. Please enable it in Google Cloud Console.', 'social-feed' ) );
			}
			if ( 'quotaExceeded' === $reason ) {
				return new WP_Error( 'quota_exceeded', __( 'API quota exceeded. Try again tomorrow.', 'social-feed' ) );
			}
		}

		if ( 400 === $status_code || 401 === $status_code ) {
			return new WP_Error( 'invalid_key', __( 'Invalid API key.', 'social-feed' ) );
		}

		if ( ! empty( $body['items'] ) ) {
			return array(
				'message' => __( 'Connection successful! Your YouTube API key is valid.', 'social-feed' ),
			);
		}

		return new WP_Error( 'unknown_error', __( 'Could not verify API key.', 'social-feed' ) );
	}

	/**
	 * Test Facebook API connection.
	 *
	 * @param array $settings API settings.
	 * @return array|WP_Error
	 */
	private function test_facebook_connection( $settings ) {
		$app_id     = $settings['facebook_app_id'] ?? '';
		$app_secret = $settings['facebook_app_secret'] ?? '';

		if ( empty( $app_id ) || empty( $app_secret ) ) {
			return new WP_Error( 'missing_credentials', __( 'App ID and App Secret are required.', 'social-feed' ) );
		}

		$url = add_query_arg(
			array(
				'client_id'     => $app_id,
				'client_secret' => $app_secret,
				'grant_type'    => 'client_credentials',
			),
			'https://graph.facebook.com/oauth/access_token'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'api_error', $body['error']['message'] ?? __( 'Invalid credentials.', 'social-feed' ) );
		}

		if ( ! empty( $body['access_token'] ) ) {
			return array(
				'message' => __( 'Connection successful! Your Facebook credentials are valid.', 'social-feed' ),
			);
		}

		return new WP_Error( 'unknown_error', __( 'Could not verify credentials.', 'social-feed' ) );
	}

	/**
	 * Handle clear all cache request.
	 */
	public function handle_clear_all_cache() {
		$this->verify_request();

		$deleted = SF_Cache::clear_all();

		SF_Helpers::sf_log_info(
			sprintf( 'All cache cleared manually. %d entries deleted.', $deleted / 2 )
		);

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: Number of cache entries */
					__( 'All cache cleared successfully! %d feed caches removed.', 'social-feed' ),
					$deleted / 2
				),
			)
		);
	}

	/**
	 * Handle clear all logs request.
	 */
	public function handle_clear_all_logs() {
		$this->verify_request();

		global $wpdb;
		$table = SF_Database::get_table( 'logs' );
		$wpdb->query( "TRUNCATE TABLE {$table}" );

		wp_send_json_success(
			array(
				'message' => __( 'All logs cleared successfully.', 'social-feed' ),
			)
		);
	}

	/**
	 * Handle load more request (frontend).
	 */
	public function handle_load_more() {
		check_ajax_referer( 'sf_frontend_nonce', 'nonce' );

		$feed_id = isset( $_POST['feed_id'] ) ? absint( $_POST['feed_id'] ) : 0;
		$cursor  = isset( $_POST['cursor'] ) ? sanitize_text_field( $_POST['cursor'] ) : '';

		if ( ! $feed_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid feed ID.', 'social-feed' ) ) );
		}

		$feed = SF_Database::get_feed( $feed_id );
		if ( ! $feed || 'active' !== $feed['status'] ) {
			wp_send_json_error( array( 'message' => __( 'Feed not found.', 'social-feed' ) ) );
		}

		$settings = SF_Database::get_all_feed_meta( $feed_id );

		$items = SF_Feed_Manager::fetch_page( $feed_id, $cursor );

		if ( is_wp_error( $items ) ) {
			wp_send_json_error( array( 'message' => $items->get_error_message() ) );
		}

		$posts = isset( $items['items'] ) ? $items['items'] : array();

		$posts = apply_filters( 'sf_feed_items', $posts, $feed_id, $settings );

		$html = '';

		foreach ( $posts as $item ) {
			$html .= SF_Renderer::render_item( $item, $settings );
		}

		wp_send_json_success(
			array(
				'html'        => $html,
				'next_cursor' => $items['next_cursor'] ?? '',
				'has_more'    => $items['has_more'] ?? false,
			)
		);
	}

	/**
	 * Handle license activation request.
	 */
	public function handle_activate_license() {
		$this->verify_request();

		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';

		if ( empty( $license_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a license key.', 'social-feed' ) ) );
		}

		$result = SF_License::activate( $license_key );

		if ( $result['success'] ) {
			SF_Helpers::sf_log_success( 'License activated successfully.', 'license' );
			wp_send_json_success( $result );
		} else {
			SF_Helpers::sf_log_error( 'License activation failed: ' . $result['message'], 'license' );
			wp_send_json_error( $result );
		}
	}

	/**
	 * Handle license deactivation request.
	 */
	public function handle_deactivate_license() {
		$this->verify_request();

		$result = SF_License::deactivate();

		if ( $result['success'] ) {
			SF_Helpers::sf_log_info( 'License deactivated.', 'license' );
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Handle license check request.
	 */
	public function handle_check_license() {
		$this->verify_request();

		$result = SF_License::check_license();

		wp_send_json_success( $result );
	}
}
