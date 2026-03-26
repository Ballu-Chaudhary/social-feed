<?php
/**
 * Settings Page for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Settings
 *
 * Handles the plugin settings page with multiple tabs.
 */
class SF_Settings {

	/**
	 * Option group name.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'sf_settings_group';

	/**
	 * Option name in database.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'sf_settings';

	/**
	 * Settings tabs.
	 *
	 * @var array
	 */
	private static $tabs = array();

	/**
	 * Initialize settings.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Register settings using Settings API.
	 */
	public static function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);

		self::register_general_settings();
		self::register_cache_settings();
		self::register_privacy_settings();
		self::register_advanced_settings();
	}

	/**
	 * Register General settings section.
	 */
	private static function register_general_settings() {
		add_settings_section(
			'sf_general_section',
			'',
			'__return_false',
			'sf_settings_general'
		);

		add_settings_field(
			'enable_plugin',
			__( 'Enable Plugin', 'social-feed' ),
			array( __CLASS__, 'render_toggle_field' ),
			'sf_settings_general',
			'sf_general_section',
			array(
				'id'          => 'enable_plugin',
				'description' => __( 'Disable to stop all feeds from loading on frontend.', 'social-feed' ),
			)
		);

		add_settings_field(
			'admin_email',
			__( 'Admin Email', 'social-feed' ),
			array( __CLASS__, 'render_text_field' ),
			'sf_settings_general',
			'sf_general_section',
			array(
				'id'          => 'admin_email',
				'type'        => 'email',
				'placeholder' => get_option( 'admin_email' ),
				'description' => __( 'Email address for plugin notifications (token expiry, errors).', 'social-feed' ),
			)
		);

		add_settings_field(
			'date_format',
			__( 'Date Format', 'social-feed' ),
			array( __CLASS__, 'render_select_field' ),
			'sf_settings_general',
			'sf_general_section',
			array(
				'id'      => 'date_format',
				'options' => array(
					'relative' => __( 'Relative (2 hours ago)', 'social-feed' ),
					'absolute' => __( 'Absolute (Jan 15, 2024)', 'social-feed' ),
				),
			)
		);

		add_settings_field(
			'credit_link',
			__( 'Credit Link Text', 'social-feed' ),
			array( __CLASS__, 'render_text_field' ),
			'sf_settings_general',
			'sf_general_section',
			array(
				'id'          => 'credit_link',
				'placeholder' => 'Powered by Social Feed',
				'description' => __( 'Shown below feeds. Leave empty to hide.', 'social-feed' ),
				'pro_only'    => true,
			)
		);

		add_settings_section(
			'sf_instagram_connect_section',
			__( 'Instagram Connection', 'social-feed' ),
			array( __CLASS__, 'render_instagram_connect_section_desc' ),
			'sf_settings_general'
		);

		add_settings_field(
			'instagram_connect_button',
			__( 'Connect', 'social-feed' ),
			array( __CLASS__, 'render_instagram_connect_button_field' ),
			'sf_settings_general',
			'sf_instagram_connect_section'
		);
	}

	/**
	 * Render OAuth Redirect URI field with current effective URL.
	 *
	 * @param array $args Field arguments.
	 */
	public static function render_redirect_uri_field( $args ) {
		$settings = get_option( self::OPTION_NAME, self::get_defaults() );
		$value    = $settings[ $args['id'] ] ?? '';
		$current  = class_exists( 'SF_Instagram' ) ? SF_Instagram::get_redirect_uri() : '';
		?>
		<input type="text"
			name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['id'] . ']' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="<?php echo esc_attr( $current ); ?>"
			class="regular-text large-text">
		<p class="description"><strong><?php esc_html_e( 'Copy this exact URL to Meta:', 'social-feed' ); ?></strong><br>
		<code style="word-break:break-all;"><?php echo esc_html( $current ); ?></code></p>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render read-only field (e.g. Redirect URI).
	 *
	 * @param array $args Field arguments.
	 */
	public static function render_readonly_field( $args ) {
		$value = $args['value'] ?? '';
		?>
		<input type="text" 
			value="<?php echo esc_attr( $value ); ?>" 
			readonly 
			class="regular-text"
			onclick="this.select();">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render Instagram connection section description.
	 */
	public static function render_instagram_connect_section_desc() {
		echo '<p class="description">';
		esc_html_e( 'Connect your Instagram account to start showing your feed.', 'social-feed' );
		echo '</p>';
	}

	/**
	 * Render Instagram connect button (OAuth authorize link).
	 */
	public static function render_instagram_connect_button_field() {
		$base_url = menu_page_url( 'social-feed-settings', false );
		$get      = isset( $_GET ) ? wp_unslash( $_GET ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_array( $get ) ) {
			unset( $get['page'] );
		} else {
			$get = array();
		}

		$page_url = add_query_arg( array_map( 'sanitize_text_field', $get ), $base_url );
		if ( empty( $page_url ) ) {
			$page_url = admin_url( 'admin.php?page=social-feed-settings' );
		}
		$state      = base64_encode( (string) $page_url );
		$oauth_url  = 'https://api.instagram.com/oauth/authorize?client_id=2067986510434194&redirect_uri=https://mahihub.in/ig-api/oauth.php&scope=user_profile,user_media&response_type=code&state=' . rawurlencode( $state );
		?>
		<a class="sf-connect-instagram-btn sf-oauth-connect-btn" href="<?php echo esc_url( $oauth_url ); ?>">
			<span class="sf-icon-instagram" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="18" height="18" focusable="false" aria-hidden="true">
					<path fill="currentColor" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
				</svg>
			</span>
			<span><?php esc_html_e( 'Connect with Instagram', 'social-feed' ); ?></span>
		</a>
		<p class="description sf-connect-note"><?php esc_html_e( 'You will be redirected back to this settings page after authorization.', 'social-feed' ); ?></p>
		<?php
	}

	/**
	 * Register Cache settings section.
	 */
	private static function register_cache_settings() {
		add_settings_section(
			'sf_cache_section',
			'',
			'__return_false',
			'sf_settings_cache'
		);

		add_settings_field(
			'enable_cache',
			__( 'Enable Caching', 'social-feed' ),
			array( __CLASS__, 'render_toggle_field' ),
			'sf_settings_cache',
			'sf_cache_section',
			array(
				'id'          => 'enable_cache',
				'description' => __( 'Cache API responses to improve performance and reduce API calls.', 'social-feed' ),
			)
		);

		add_settings_field(
			'cache_duration',
			__( 'Cache Duration', 'social-feed' ),
			array( __CLASS__, 'render_select_field' ),
			'sf_settings_cache',
			'sf_cache_section',
			array(
				'id'      => 'cache_duration',
				'options' => array(
					'3600'   => __( '1 Hour', 'social-feed' ),
					'21600'  => __( '6 Hours', 'social-feed' ),
					'43200'  => __( '12 Hours', 'social-feed' ),
					'86400'  => __( '1 Day', 'social-feed' ),
					'604800' => __( '1 Week', 'social-feed' ),
				),
			)
		);

		add_settings_field(
			'cache_actions',
			__( 'Cache Management', 'social-feed' ),
			array( __CLASS__, 'render_cache_actions' ),
			'sf_settings_cache',
			'sf_cache_section'
		);
	}

	/**
	 * Register Privacy/GDPR settings section.
	 */
	private static function register_privacy_settings() {
		add_settings_section(
			'sf_privacy_section',
			'',
			'__return_false',
			'sf_settings_privacy'
		);

		add_settings_field(
			'gdpr_mode',
			__( 'GDPR Mode', 'social-feed' ),
			array( __CLASS__, 'render_toggle_field' ),
			'sf_settings_privacy',
			'sf_privacy_section',
			array(
				'id'          => 'gdpr_mode',
				'description' => __( 'When enabled, feeds won\'t load until user gives consent.', 'social-feed' ),
			)
		);

		add_settings_field(
			'gdpr_notice',
			__( 'Consent Notice Text', 'social-feed' ),
			array( __CLASS__, 'render_textarea_field' ),
			'sf_settings_privacy',
			'sf_privacy_section',
			array(
				'id'          => 'gdpr_notice',
				'placeholder' => __( 'This content is hosted by a third party. By showing the external content you accept the terms and conditions.', 'social-feed' ),
				'rows'        => 3,
			)
		);

		add_settings_field(
			'data_retention',
			__( 'Data Retention Period', 'social-feed' ),
			array( __CLASS__, 'render_number_field' ),
			'sf_settings_privacy',
			'sf_privacy_section',
			array(
				'id'          => 'data_retention',
				'min'         => 1,
				'max'         => 365,
				'suffix'      => __( 'days', 'social-feed' ),
				'description' => __( 'Feed items older than this will be automatically deleted.', 'social-feed' ),
			)
		);

		add_settings_field(
			'delete_on_uninstall',
			__( 'Delete Data on Uninstall', 'social-feed' ),
			array( __CLASS__, 'render_checkbox_field' ),
			'sf_settings_privacy',
			'sf_privacy_section',
			array(
				'id'          => 'delete_on_uninstall',
				'label'       => __( 'Remove all plugin data when uninstalling', 'social-feed' ),
				'description' => __( 'Warning: This will permanently delete all feeds, accounts, and settings.', 'social-feed' ),
				'warning'     => true,
			)
		);
	}

	/**
	 * Register Advanced settings section.
	 */
	private static function register_advanced_settings() {
		add_settings_section(
			'sf_advanced_section',
			'',
			'__return_false',
			'sf_settings_advanced'
		);

		add_settings_field(
			'enable_logging',
			__( 'Error Logging', 'social-feed' ),
			array( __CLASS__, 'render_toggle_field' ),
			'sf_settings_advanced',
			'sf_advanced_section',
			array(
				'id'          => 'enable_logging',
				'description' => __( 'Log API errors and plugin events for debugging.', 'social-feed' ),
			)
		);

		add_settings_field(
			'log_retention',
			__( 'Log Retention', 'social-feed' ),
			array( __CLASS__, 'render_number_field' ),
			'sf_settings_advanced',
			'sf_advanced_section',
			array(
				'id'          => 'log_retention',
				'min'         => 1,
				'max'         => 90,
				'suffix'      => __( 'days', 'social-feed' ),
				'description' => __( 'Logs older than this will be automatically deleted.', 'social-feed' ),
			)
		);

		add_settings_field(
			'debug_mode',
			__( 'Debug Mode', 'social-feed' ),
			array( __CLASS__, 'render_toggle_field' ),
			'sf_settings_advanced',
			'sf_advanced_section',
			array(
				'id'          => 'debug_mode',
				'description' => __( 'Show additional debugging information in admin.', 'social-feed' ),
			)
		);

		add_settings_field(
			'proxy_images',
			__( 'Proxy Images', 'social-feed' ),
			array( __CLASS__, 'render_toggle_field' ),
			'sf_settings_advanced',
			'sf_advanced_section',
			array(
				'id'          => 'proxy_images',
				'description' => __( 'Load images through your server to avoid CORS issues.', 'social-feed' ),
			)
		);

		add_settings_field(
			'load_assets_conditionally',
			__( 'Conditional Asset Loading', 'social-feed' ),
			array( __CLASS__, 'render_toggle_field' ),
			'sf_settings_advanced',
			'sf_advanced_section',
			array(
				'id'          => 'load_assets_conditionally',
				'description' => __( 'Only load CSS/JS on pages that contain feeds.', 'social-feed' ),
			)
		);

		add_settings_field(
			'data_management',
			__( 'Data Management', 'social-feed' ),
			array( __CLASS__, 'render_data_management' ),
			'sf_settings_advanced',
			'sf_advanced_section'
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	public static function get_defaults() {
		return array(
			'enable_plugin'            => '1',
			'admin_email'              => get_option( 'admin_email' ),
			'date_format'              => 'relative',
			'credit_link'              => '',
			'enable_cache'             => '1',
			'cache_duration'           => '3600',
			'gdpr_mode'                => '0',
			'gdpr_notice'              => __( 'This content is hosted by a third party. By showing the external content you accept the terms and conditions.', 'social-feed' ),
			'data_retention'           => '30',
			'delete_on_uninstall'      => '0',
			'enable_logging'           => '1',
			'log_retention'            => '30',
			'debug_mode'               => '0',
			'proxy_images'             => '0',
			'load_assets_conditionally' => '1',
			'instagram_app_id'         => '',
			'instagram_app_secret'     => '',
			'instagram_redirect_uri'   => '',
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_settings( $input ) {
		$defaults  = self::get_defaults();
		$sanitized = array();

		$sanitized['enable_plugin']             = isset( $input['enable_plugin'] ) ? '1' : '0';
		$sanitized['admin_email']               = sanitize_email( $input['admin_email'] ?? $defaults['admin_email'] );
		$sanitized['date_format']               = in_array( $input['date_format'] ?? '', array( 'relative', 'absolute' ), true ) ? $input['date_format'] : 'relative';
		$sanitized['credit_link']               = sanitize_text_field( $input['credit_link'] ?? '' );
		$sanitized['enable_cache']              = isset( $input['enable_cache'] ) ? '1' : '0';
		$sanitized['cache_duration']            = absint( $input['cache_duration'] ?? 3600 );
		$sanitized['gdpr_mode']                 = isset( $input['gdpr_mode'] ) ? '1' : '0';
		$sanitized['gdpr_notice']               = wp_kses_post( $input['gdpr_notice'] ?? '' );
		$sanitized['data_retention']            = min( 365, max( 1, absint( $input['data_retention'] ?? 30 ) ) );
		$sanitized['delete_on_uninstall']       = isset( $input['delete_on_uninstall'] ) ? '1' : '0';
		$sanitized['enable_logging']            = isset( $input['enable_logging'] ) ? '1' : '0';
		$sanitized['log_retention']             = min( 90, max( 1, absint( $input['log_retention'] ?? 30 ) ) );
		$sanitized['debug_mode']                = isset( $input['debug_mode'] ) ? '1' : '0';
		$sanitized['proxy_images']              = isset( $input['proxy_images'] ) ? '1' : '0';
		$sanitized['load_assets_conditionally'] = isset( $input['load_assets_conditionally'] ) ? '1' : '0';
		$sanitized['instagram_app_id']          = sanitize_text_field( $input['instagram_app_id'] ?? '' );
		$sanitized['instagram_app_secret']      = sanitize_text_field( $input['instagram_app_secret'] ?? '' );
		$raw_uri                                 = trim( (string) ( $input['instagram_redirect_uri'] ?? '' ) );
		$sanitized['instagram_redirect_uri']    = ! empty( $raw_uri ) ? esc_url_raw( $raw_uri ) : '';

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 */
	public static function render() {
		?>
		<div class="wrap sf-admin-wrap sf-settings-page">
			<?php self::render_content(); ?>
		</div>
		<?php
	}

	/**
	 * Render the settings page content (without wrapper).
	 */
	public static function render_content() {
		$settings    = get_option( self::OPTION_NAME, self::get_defaults() );
		$settings    = wp_parse_args( $settings, self::get_defaults() );
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		$cache_stats = SF_Cache::get_stats();
		$last_clear  = get_option( 'sf_last_cache_clear', 0 );

		?>
		<nav class="sf-tabs">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'general' ) ); ?>" class="sf-tab <?php echo 'general' === $current_tab ? 'sf-tab--active' : ''; ?>">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'General', 'social-feed' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'cache' ) ); ?>" class="sf-tab <?php echo 'cache' === $current_tab ? 'sf-tab--active' : ''; ?>">
					<span class="dashicons dashicons-database"></span>
					<?php esc_html_e( 'Cache', 'social-feed' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'privacy' ) ); ?>" class="sf-tab <?php echo 'privacy' === $current_tab ? 'sf-tab--active' : ''; ?>">
					<span class="dashicons dashicons-shield"></span>
					<?php esc_html_e( 'Privacy / GDPR', 'social-feed' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'advanced' ) ); ?>" class="sf-tab <?php echo 'advanced' === $current_tab ? 'sf-tab--active' : ''; ?>">
					<span class="dashicons dashicons-admin-tools"></span>
					<?php esc_html_e( 'Advanced', 'social-feed' ); ?>
				</a>
			</nav>

			<form method="post" action="options.php" class="sf-settings-form">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<div class="sf-settings-content">
					<?php if ( 'general' === $current_tab ) : ?>
						<div class="sf-card">
							<h2 class="sf-card-title"><?php esc_html_e( 'General Settings', 'social-feed' ); ?></h2>
							<div class="sf-card-content">
								<?php do_settings_sections( 'sf_settings_general' ); ?>
							</div>
						</div>
					<?php elseif ( 'cache' === $current_tab ) : ?>
						<div class="sf-card">
							<h2 class="sf-card-title"><?php esc_html_e( 'Cache Settings', 'social-feed' ); ?></h2>
							<div class="sf-card-content">
								<?php do_settings_sections( 'sf_settings_cache' ); ?>
							</div>
						</div>

						<div class="sf-card sf-card--stats">
							<h2 class="sf-card-title"><?php esc_html_e( 'Cache Statistics', 'social-feed' ); ?></h2>
							<div class="sf-card-content">
								<div class="sf-stats-grid sf-stats-grid--2">
									<div class="sf-stat-item">
										<span class="sf-stat-value"><?php echo esc_html( $cache_stats['count'] ); ?></span>
										<span class="sf-stat-label"><?php esc_html_e( 'Cached Feeds', 'social-feed' ); ?></span>
									</div>
									<div class="sf-stat-item">
										<span class="sf-stat-value"><?php echo esc_html( size_format( $cache_stats['size'] ) ); ?></span>
										<span class="sf-stat-label"><?php esc_html_e( 'Cache Size', 'social-feed' ); ?></span>
									</div>
								</div>
								<?php if ( $last_clear ) : ?>
									<p class="sf-last-action">
										<?php
										printf(
											/* translators: %s: Time ago */
											esc_html__( 'Last cleared: %s', 'social-feed' ),
											esc_html( human_time_diff( $last_clear ) . ' ' . __( 'ago', 'social-feed' ) )
										);
										?>
									</p>
								<?php endif; ?>
							</div>
						</div>
					<?php elseif ( 'privacy' === $current_tab ) : ?>
						<div class="sf-card">
							<h2 class="sf-card-title"><?php esc_html_e( 'Privacy & GDPR Settings', 'social-feed' ); ?></h2>
							<div class="sf-card-content">
								<div class="sf-notice sf-notice--info">
									<span class="dashicons dashicons-info"></span>
									<p><?php esc_html_e( 'These settings help you comply with GDPR and other privacy regulations.', 'social-feed' ); ?></p>
								</div>
								<?php do_settings_sections( 'sf_settings_privacy' ); ?>
							</div>
						</div>
					<?php elseif ( 'advanced' === $current_tab ) : ?>
						<div class="sf-card">
							<h2 class="sf-card-title"><?php esc_html_e( 'Advanced Settings', 'social-feed' ); ?></h2>
							<div class="sf-card-content">
								<?php do_settings_sections( 'sf_settings_advanced' ); ?>
							</div>
						</div>
					<?php endif; ?>
				</div>

			<div class="sf-settings-footer">
				<?php submit_button( __( 'Save Settings', 'social-feed' ), 'primary', 'submit', false ); ?>
			</div>
		</form>
		<?php
	}

	/**
	 * Render toggle field.
	 *
	 * @param array $args Field arguments.
	 */
	public static function render_toggle_field( $args ) {
		$settings = get_option( self::OPTION_NAME, self::get_defaults() );
		$value    = $settings[ $args['id'] ] ?? '0';
		$disabled = ! empty( $args['pro_only'] ) && ! sf_is_pro();

		?>
		<label class="sf-toggle <?php echo $disabled ? 'sf-toggle--disabled' : ''; ?>">
			<input type="checkbox" 
				name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['id'] . ']' ); ?>" 
				value="1" 
				<?php checked( $value, '1' ); ?>
				<?php disabled( $disabled ); ?>>
			<span class="sf-toggle-slider"></span>
		</label>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php if ( $disabled ) : ?>
			<span class="sf-pro-badge"><?php esc_html_e( 'PRO', 'social-feed' ); ?></span>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render text field.
	 *
	 * @param array $args Field arguments.
	 */
	public static function render_text_field( $args ) {
		$settings    = get_option( self::OPTION_NAME, self::get_defaults() );
		$value       = $settings[ $args['id'] ] ?? '';
		$type        = $args['type'] ?? 'text';
		$placeholder = $args['placeholder'] ?? '';
		$disabled    = ! empty( $args['pro_only'] ) && ! sf_is_pro();

		?>
		<input type="<?php echo esc_attr( $type ); ?>" 
			name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['id'] . ']' ); ?>" 
			value="<?php echo esc_attr( $value ); ?>" 
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			class="regular-text"
			<?php disabled( $disabled ); ?>>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php if ( $disabled ) : ?>
			<span class="sf-pro-badge"><?php esc_html_e( 'PRO', 'social-feed' ); ?></span>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render password field with visibility toggle.
	 *
	 * @param array $args Field arguments.
	 */
	public static function render_password_field( $args ) {
		$settings    = get_option( self::OPTION_NAME, self::get_defaults() );
		$value       = $settings[ $args['id'] ] ?? '';
		$placeholder = $args['placeholder'] ?? '';

		?>
		<input type="password"
			name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['id'] . ']' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			class="regular-text"
			autocomplete="off">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render select field.
	 *
	 * @param array $args Field arguments.
	 */
	public static function render_select_field( $args ) {
		$settings = get_option( self::OPTION_NAME, self::get_defaults() );
		$value    = $settings[ $args['id'] ] ?? '';
		$options  = $args['options'] ?? array();

		?>
		<select name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['id'] . ']' ); ?>">
			<?php foreach ( $options as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render textarea field.
	 *
	 * @param array $args Field arguments.
	 */
	public static function render_textarea_field( $args ) {
		$settings    = get_option( self::OPTION_NAME, self::get_defaults() );
		$value       = $settings[ $args['id'] ] ?? '';
		$placeholder = $args['placeholder'] ?? '';
		$rows        = $args['rows'] ?? 4;

		?>
		<textarea name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['id'] . ']' ); ?>" 
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			rows="<?php echo absint( $rows ); ?>"
			class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render number field.
	 *
	 * @param array $args Field arguments.
	 */
	public static function render_number_field( $args ) {
		$settings = get_option( self::OPTION_NAME, self::get_defaults() );
		$value    = $settings[ $args['id'] ] ?? '';
		$min      = $args['min'] ?? 0;
		$max      = $args['max'] ?? 999;
		$suffix   = $args['suffix'] ?? '';

		?>
		<input type="number" 
			name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['id'] . ']' ); ?>" 
			value="<?php echo esc_attr( $value ); ?>" 
			min="<?php echo absint( $min ); ?>"
			max="<?php echo absint( $max ); ?>"
			class="small-text">
		<?php if ( $suffix ) : ?>
			<span class="sf-field-suffix"><?php echo esc_html( $suffix ); ?></span>
		<?php endif; ?>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array $args Field arguments.
	 */
	public static function render_checkbox_field( $args ) {
		$settings = get_option( self::OPTION_NAME, self::get_defaults() );
		$value    = $settings[ $args['id'] ] ?? '0';
		$label    = $args['label'] ?? '';
		$warning  = ! empty( $args['warning'] );

		?>
		<label class="sf-checkbox <?php echo $warning ? 'sf-checkbox--warning' : ''; ?>">
			<input type="checkbox" 
				name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['id'] . ']' ); ?>" 
				value="1" 
				<?php checked( $value, '1' ); ?>>
			<?php echo esc_html( $label ); ?>
		</label>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description <?php echo $warning ? 'sf-warning-text' : ''; ?>"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render cache actions.
	 */
	public static function render_cache_actions() {
		?>
		<div class="sf-action-buttons">
			<button type="button" class="button" id="sf-clear-cache">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Clear All Cache', 'social-feed' ); ?>
			</button>
			<span class="sf-action-result" id="sf-cache-result"></span>
		</div>
		<?php
	}

	/**
	 * Render data management section.
	 */
	public static function render_data_management() {
		?>
		<div class="sf-data-management">
			<div class="sf-action-group">
				<h4><?php esc_html_e( 'Reset Settings', 'social-feed' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Reset all plugin settings to their default values.', 'social-feed' ); ?></p>
				<button type="button" class="button sf-button--danger" id="sf-reset-settings">
					<span class="dashicons dashicons-image-rotate"></span>
					<?php esc_html_e( 'Reset All Settings', 'social-feed' ); ?>
				</button>
			</div>

			<div class="sf-action-group">
				<h4><?php esc_html_e( 'Export / Import', 'social-feed' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Export your settings as JSON or import from a backup.', 'social-feed' ); ?></p>
				<div class="sf-action-buttons">
					<button type="button" class="button" id="sf-export-settings">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export Settings', 'social-feed' ); ?>
					</button>
					<label class="button sf-import-btn">
						<span class="dashicons dashicons-upload"></span>
						<?php esc_html_e( 'Import Settings', 'social-feed' ); ?>
						<input type="file" id="sf-import-settings" accept=".json" style="display: none;">
					</label>
				</div>
			</div>

			<div class="sf-action-group">
				<h4><?php esc_html_e( 'Clear Logs', 'social-feed' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Delete all stored log entries.', 'social-feed' ); ?></p>
				<button type="button" class="button" id="sf-clear-logs">
					<span class="dashicons dashicons-editor-removeformatting"></span>
					<?php esc_html_e( 'Clear All Logs', 'social-feed' ); ?>
				</button>
			</div>
		</div>
		<?php
	}
}

SF_Settings::init();
