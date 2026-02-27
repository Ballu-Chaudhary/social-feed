<?php
/**
 * Frontend renderer for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Renderer
 *
 * Handles rendering of social feeds on the frontend.
 */
class SF_Renderer {

	/**
	 * Render a feed by ID.
	 *
	 * @param int   $feed_id Feed ID.
	 * @param array $overrides Optional setting overrides.
	 * @return string Rendered HTML.
	 */
	public static function render_feed( $feed_id, $overrides = array() ) {
		$feed = SF_Database::get_feed( $feed_id );

		if ( ! $feed ) {
			return self::render_error( __( 'Feed not found.', 'social-feed' ) );
		}

		if ( 'active' !== $feed['status'] ) {
			return '';
		}

		$settings = SF_Database::get_all_feed_meta( $feed_id );
		$settings = wp_parse_args( $overrides, $settings );

		$account = null;
		if ( ! empty( $feed['account_id'] ) ) {
			$account = SF_Database::get_account( $feed['account_id'] );
		}

		/**
		 * Action hook before rendering a feed.
		 *
		 * @param int   $feed_id  Feed ID.
		 * @param array $feed     Feed data.
		 * @param array $settings Feed settings.
		 */
		do_action( 'sf_before_render_feed', $feed_id, $feed, $settings );

		$feed_data = self::get_feed_items( $feed_id, $feed, $account, $settings );

		if ( is_wp_error( $feed_data ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return self::render_error( $feed_data->get_error_message() );
			}
			return '';
		}

		$items = $feed_data['items'] ?? array();

		/**
		 * Filter feed items before display.
		 *
		 * @param array $items    Feed items.
		 * @param int   $feed_id  Feed ID.
		 * @param array $settings Feed settings.
		 */
		$items = apply_filters( 'sf_feed_items', $items, $feed_id, $settings );

		$feed_data['items'] = $items;

		self::enqueue_feed_assets( $feed_id );

		$platform    = $feed['platform'];
		$layout_type = $settings['layout'] ?? $settings['layout_type'] ?? 'grid';
		$template    = self::get_template_path( $platform, $layout_type );

		if ( ! file_exists( $template ) ) {
			return self::render_error(
				sprintf(
					/* translators: 1: Platform, 2: Layout type */
					__( 'Template not found for %1$s %2$s layout.', 'social-feed' ),
					$platform,
					$layout_type
				)
			);
		}

		$css_vars     = self::get_feed_css_vars( $settings );
		$feed_classes = self::get_feed_classes( $feed, $settings );

		ob_start();

		$data_attrs = array(
			'data-feed-id'  => $feed_id,
			'data-platform' => $platform,
			'data-layout'   => $layout_type,
			'data-columns'  => $settings['columns'] ?? 3,
		);

		if ( ! empty( $settings['enable_lightbox'] ) ) {
			$data_attrs['data-lightbox'] = 'true';
		}

		$lm_type = $settings['loadmore_type'] ?? ( $settings['load_more_type'] ?? 'button' );
		if ( 'infinite' === $lm_type || 'scroll' === $lm_type ) {
			$data_attrs['data-infinite'] = 'true';
		}

		$data_attr_string = '';
		foreach ( $data_attrs as $attr => $value ) {
			$data_attr_string .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $value ) );
		}

		echo '<div class="' . esc_attr( $feed_classes ) . '" style="' . esc_attr( $css_vars ) . '"' . $data_attr_string . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$posts        = $feed_data['items'] ?? array();
		$profile      = $feed_data['profile'] ?? array();
		$next_cursor  = $feed_data['next_cursor'] ?? '';
		$has_more     = $feed_data['has_more'] ?? false;

		include $template;

		echo '</div>';

		$output = ob_get_clean();

		/**
		 * Action hook after rendering a feed.
		 *
		 * @param int    $feed_id  Feed ID.
		 * @param array  $feed     Feed data.
		 * @param array  $settings Feed settings.
		 * @param string $output   Rendered HTML output.
		 */
		do_action( 'sf_after_render_feed', $feed_id, $feed, $settings, $output );

		return $output;
	}

	/**
	 * Get feed items from cache or API.
	 *
	 * @param int   $feed_id  Feed ID.
	 * @param array $feed     Feed data.
	 * @param array $account  Account data.
	 * @param array $settings Feed settings.
	 * @return array|WP_Error Feed data or error.
	 */
	private static function get_feed_items( $feed_id, $feed, $account, $settings ) {
		return SF_Cache::get_or_fetch( $feed_id );
	}

	/**
	 * Get template file path.
	 *
	 * @param string $platform    Platform slug.
	 * @param string $layout_type Layout type.
	 * @return string Template path.
	 */
	private static function get_template_path( $platform, $layout_type ) {
		$theme_template = get_stylesheet_directory() . '/social-feed/' . $platform . '/' . $layout_type . '.php';

		if ( file_exists( $theme_template ) ) {
			return $theme_template;
		}

		$plugin_template = SF_PLUGIN_PATH . 'templates/' . $platform . '/' . $layout_type . '.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return SF_PLUGIN_PATH . 'templates/' . $platform . '/grid.php';
	}

	/**
	 * Generate CSS variables string from settings.
	 *
	 * @param array $settings Feed settings.
	 * @return string CSS variables string.
	 */
	public static function get_feed_css_vars( $settings ) {
		$vars = array();

		$var_map = array(
			'columns'           => '--sf-columns',
			'columns_tablet'    => '--sf-columns-tablet',
			'columns_mobile'    => '--sf-columns-mobile',
			'gap'               => '--sf-gap',
			'border_radius'     => '--sf-border-radius',
			'bg_color'          => '--sf-bg-color',
			'text_color'        => '--sf-text-color',
			'link_color'        => '--sf-link-color',
			'overlay_color'     => '--sf-overlay-color',
			'overlay_text'      => '--sf-overlay-text',
			'header_bg'         => '--sf-header-bg',
			'header_text'       => '--sf-header-text',
			'button_bg'         => '--sf-button-bg',
			'button_text'       => '--sf-button-text',
			'button_hover_bg'   => '--sf-button-hover-bg',
			'font_size'         => '--sf-font-size',
			'item_bg'           => '--sf-item-bg',
			'border_color'      => '--sf-border-color',
			'border_width'      => '--sf-border-width',
		);

		foreach ( $var_map as $setting => $var ) {
			if ( isset( $settings[ $setting ] ) && '' !== $settings[ $setting ] ) {
				$value = $settings[ $setting ];

				if ( in_array( $setting, array( 'columns', 'columns_tablet', 'columns_mobile' ), true ) ) {
					$value = absint( $value );
				} elseif ( in_array( $setting, array( 'gap', 'border_radius', 'font_size', 'border_width' ), true ) ) {
					$value = is_numeric( $value ) ? $value . 'px' : $value;
				}

				$vars[] = $var . ':' . $value;
			}
		}

		return implode( ';', $vars );
	}

	/**
	 * Build CSS class string for feed container.
	 *
	 * @param array $feed     Feed data.
	 * @param array $settings Feed settings.
	 * @return string CSS classes.
	 */
	public static function get_feed_classes( $feed, $settings ) {
		$classes = array(
			'sf-feed',
			'sf-feed--' . sanitize_html_class( $feed['platform'] ),
			'sf-feed--' . sanitize_html_class( $settings['layout'] ?? $settings['layout_type'] ?? 'grid' ),
		);

		if ( ! empty( $settings['enable_hover'] ) ) {
			$classes[] = 'sf-feed--hover-enabled';
		}

		if ( ! empty( $settings['hover_effect'] ) ) {
			$classes[] = 'sf-feed--hover-' . sanitize_html_class( $settings['hover_effect'] );
		}

		if ( ! empty( $settings['enable_lightbox'] ) ) {
			$classes[] = 'sf-feed--lightbox';
		}

		if ( ! empty( $settings['show_header'] ) ) {
			$classes[] = 'sf-feed--has-header';
		}

		if ( ! empty( $settings['enable_border'] ) ) {
			$classes[] = 'sf-feed--bordered';
		}

		if ( ! empty( $settings['custom_class'] ) ) {
			$classes[] = sanitize_html_class( $settings['custom_class'] );
		}

		return implode( ' ', array_filter( $classes ) );
	}

	/**
	 * Register frontend assets.
	 */
	public static function register_assets() {
		static $registered = false;

		if ( $registered ) {
			return;
		}

		wp_register_style(
			'sf-frontend',
			SF_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			SF_VERSION
		);

		wp_register_script(
			'sf-frontend',
			SF_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			SF_VERSION,
			true
		);

		$registered = true;
	}

	/**
	 * Enqueue frontend assets for a feed.
	 *
	 * @param int $feed_id Feed ID.
	 */
	public static function enqueue_feed_assets( $feed_id ) {
		self::register_assets();

		$settings = get_option( 'sf_settings', array() );

		if ( empty( $settings['load_css'] ) || '1' === $settings['load_css'] ) {
			wp_enqueue_style( 'sf-frontend' );
		}

		if ( empty( $settings['load_js'] ) || '1' === $settings['load_js'] ) {
			wp_enqueue_script( 'sf-frontend' );

			static $localized = false;

			if ( ! $localized ) {
				wp_localize_script(
					'sf-frontend',
					'sfFrontend',
					array(
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'sf_frontend_nonce' ),
						'i18n'    => array(
							'loading'     => __( 'Loading...', 'social-feed' ),
							'load_more'   => __( 'Load More', 'social-feed' ),
							'no_more'     => __( 'No more posts', 'social-feed' ),
							'error'       => __( 'Error loading posts', 'social-feed' ),
							'close'       => __( 'Close', 'social-feed' ),
							'prev'        => __( 'Previous', 'social-feed' ),
							'next'        => __( 'Next', 'social-feed' ),
						),
					)
				);

				$localized = true;
			}
		}
	}

	/**
	 * Render error message.
	 *
	 * @param string $message Error message.
	 * @return string HTML.
	 */
	private static function render_error( $message ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		return sprintf(
			'<div class="sf-feed-error"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Render a single feed item.
	 *
	 * @param array $item     Item data.
	 * @param array $settings Feed settings.
	 * @return string HTML.
	 */
	public static function render_item( $item, $settings ) {
		$type      = $item['type'] ?? 'image';
		$is_video  = in_array( $type, array( 'video', 'reel', 'carousel' ), true );
		$permalink = $item['permalink'] ?? '#';
		$thumbnail = $item['thumbnail'] ?? $item['media_url'] ?? '';

		$link_target = ! empty( $settings['open_links_new_tab'] ) ? '_blank' : '_self';
		$link_rel    = ! empty( $settings['open_links_new_tab'] ) ? 'noopener noreferrer' : '';

		ob_start();
		?>
		<div class="sf-feed__item sf-feed__item--<?php echo esc_attr( $type ); ?>" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
			<a href="<?php echo esc_url( $permalink ); ?>" class="sf-feed__item-link" target="<?php echo esc_attr( $link_target ); ?>" rel="<?php echo esc_attr( $link_rel ); ?>">
				<div class="sf-feed__item-media">
					<?php if ( $thumbnail ) : ?>
						<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( wp_strip_all_tags( $item['caption'] ?? '' ) ); ?>" loading="lazy" class="sf-feed__item-image">
					<?php endif; ?>

					<?php if ( $is_video ) : ?>
						<div class="sf-feed__item-play">
							<svg viewBox="0 0 24 24" width="48" height="48"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $settings['show_hover_overlay'] ) ) : ?>
					<div class="sf-feed__item-overlay">
						<div class="sf-feed__item-stats">
							<?php if ( isset( $item['likes'] ) ) : ?>
								<span class="sf-feed__stat sf-feed__stat--likes">
									<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
									<?php echo esc_html( SF_Helpers::sf_format_number( $item['likes'] ) ); ?>
								</span>
							<?php endif; ?>

							<?php if ( isset( $item['comments'] ) ) : ?>
								<span class="sf-feed__stat sf-feed__stat--comments">
									<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M21.99 4c0-1.1-.89-2-1.99-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4-.01-18z"/></svg>
									<?php echo esc_html( SF_Helpers::sf_format_number( $item['comments'] ) ); ?>
								</span>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</a>

			<?php if ( ! empty( $settings['show_caption'] ) && ! empty( $item['caption'] ) ) : ?>
				<div class="sf-feed__item-caption">
					<?php
					$max_length = absint( $settings['caption_length'] ?? 100 );
					echo esc_html( SF_Helpers::sf_truncate_text( $item['caption'], $max_length ) );
					?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $settings['show_date'] ) && ! empty( $item['timestamp'] ) ) : ?>
				<div class="sf-feed__item-date">
					<?php echo esc_html( SF_Helpers::sf_time_ago( $item['timestamp'] ) ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render feed header.
	 *
	 * @param array $account  Account data.
	 * @param array $profile  Profile data.
	 * @param array $settings Feed settings.
	 * @return string HTML.
	 */
	public static function render_header( $account, $profile, $settings ) {
		if ( empty( $settings['show_header'] ) ) {
			return '';
		}

		$profile_pic = $profile['profile_picture_url'] ?? $account['profile_pic'] ?? '';
		$username    = $profile['username'] ?? $account['account_name'] ?? '';
		$followers   = $profile['followers_count'] ?? 0;
		$bio         = $profile['biography'] ?? '';
		$platform    = $account['platform'] ?? 'instagram';

		$profile_url = self::get_profile_url( $platform, $username );

		ob_start();
		?>
		<div class="sf-feed__header">
			<?php if ( ! empty( $settings['show_avatar'] ) && $profile_pic ) : ?>
				<a href="<?php echo esc_url( $profile_url ); ?>" class="sf-feed__avatar" target="_blank" rel="noopener noreferrer">
					<img src="<?php echo esc_url( $profile_pic ); ?>" alt="<?php echo esc_attr( $username ); ?>" loading="lazy">
				</a>
			<?php endif; ?>

			<div class="sf-feed__header-info">
				<?php if ( ! empty( $settings['show_username'] ) && $username ) : ?>
					<a href="<?php echo esc_url( $profile_url ); ?>" class="sf-feed__username" target="_blank" rel="noopener noreferrer">
						@<?php echo esc_html( $username ); ?>
					</a>
				<?php endif; ?>

				<?php if ( ! empty( $settings['show_followers'] ) && $followers ) : ?>
					<span class="sf-feed__followers">
						<?php
						printf(
							/* translators: %s: Formatted follower count */
							esc_html__( '%s followers', 'social-feed' ),
							esc_html( SF_Helpers::sf_format_number( $followers ) )
						);
						?>
					</span>
				<?php endif; ?>

				<?php if ( ! empty( $settings['show_bio'] ) && $bio ) : ?>
					<p class="sf-feed__bio"><?php echo esc_html( SF_Helpers::sf_truncate_text( $bio, 150 ) ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $settings['show_follow_button'] ) ) : ?>
				<a href="<?php echo esc_url( $profile_url ); ?>" class="sf-feed__follow-btn" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( $settings['follow_button_text'] ?? __( 'Follow', 'social-feed' ) ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get profile URL for a platform.
	 *
	 * @param string $platform Platform slug.
	 * @param string $username Username.
	 * @return string Profile URL.
	 */
	private static function get_profile_url( $platform, $username ) {
		$username = ltrim( $username, '@' );
		if ( 'instagram' === $platform ) {
			return 'https://www.instagram.com/' . $username . '/';
		}
		return '#';
	}

	/**
	 * Render load more button or infinite scroll trigger.
	 *
	 * @param array  $settings    Feed settings.
	 * @param string $next_cursor Next page cursor.
	 * @return string HTML.
	 */
	public static function render_load_more( $settings, $next_cursor = '' ) {
		$load_more_type = $settings['loadmore_type'] ?? ( $settings['load_more_type'] ?? 'button' );
		$load_more_text = $settings['loadmore_text'] ?? ( $settings['load_more_text'] ?? __( 'Load More', 'social-feed' ) );

		if ( 'none' === $load_more_type || empty( $next_cursor ) ) {
			return '';
		}

		ob_start();

		if ( 'button' === $load_more_type ) :
			?>
			<div class="sf-feed__load-more">
				<button type="button" class="sf-feed__load-more-btn" data-cursor="<?php echo esc_attr( $next_cursor ); ?>">
					<?php echo esc_html( $load_more_text ); ?>
				</button>
			</div>
			<?php
		else :
			?>
			<div class="sf-feed__infinite-trigger" data-cursor="<?php echo esc_attr( $next_cursor ); ?>">
				<span class="sf-feed__loader"></span>
			</div>
			<?php
		endif;

		return ob_get_clean();
	}

	/**
	 * Render empty state.
	 *
	 * @param array $settings Feed settings.
	 * @return string HTML.
	 */
	public static function render_empty_state( $settings ) {
		ob_start();
		?>
		<div class="sf-feed__empty">
			<div class="sf-feed__empty-icon">
				<svg viewBox="0 0 24 24" width="48" height="48"><path fill="currentColor" d="M4 4h7V2H4c-1.1 0-2 .9-2 2v7h2V4zm6 9l-4 5h12l-3-4-2.03 2.71L10 13zm7-4.5c0-.83-.67-1.5-1.5-1.5S14 7.67 14 8.5s.67 1.5 1.5 1.5S17 9.33 17 8.5zM20 2h-7v2h7v7h2V4c0-1.1-.9-2-2-2zm0 18h-7v2h7c1.1 0 2-.9 2-2v-7h-2v7zM4 13H2v7c0 1.1.9 2 2 2h7v-2H4v-7z"/></svg>
			</div>
			<p class="sf-feed__empty-text">
				<?php echo esc_html( $settings['empty_message'] ?? __( 'No posts to display.', 'social-feed' ) ); ?>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}
}
