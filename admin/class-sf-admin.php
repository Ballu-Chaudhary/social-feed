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
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add admin menu page.
	 */
	public function add_menu_page() {
		add_menu_page(
			__( 'Social Feed', 'social-feed' ),
			__( 'Social Feed', 'social-feed' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-share',
			30
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Settings', 'social-feed' ),
			__( 'Settings', 'social-feed' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap sf-admin-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div class="sf-admin-content">
				<p><?php esc_html_e( 'Configure your social media feeds below.', 'social-feed' ); ?></p>
				<div id="sf-settings-app"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'sf-admin',
			SF_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SF_VERSION
		);

		wp_enqueue_script(
			'sf-admin',
			SF_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			SF_VERSION,
			true
		);

		wp_localize_script(
			'sf-admin',
			'sfAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sf_admin_nonce' ),
			)
		);
	}
}
