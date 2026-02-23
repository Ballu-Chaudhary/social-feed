<?php
/**
 * Plugin Name: Social Feed
 * Plugin URI: https://example.com/social-feed
 * Description: Display social media feeds from Instagram, YouTube, and Facebook.
 * Version: 1.0.0
 * Author: Baldev Chaudhary
 * Author URI: 
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: social-feed
 * Domain Path: /languages
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'SF_VERSION', '1.0.0' );
define( 'SF_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader for Social Feed classes.
 *
 * @param string $class_name The class name to load.
 */
function sf_autoloader( $class_name ) {
	if ( strpos( $class_name, 'SF_' ) !== 0 ) {
		return;
	}

	$class_file = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

	$paths = array(
		SF_PLUGIN_PATH . 'includes/' . $class_file,
		SF_PLUGIN_PATH . 'admin/' . $class_file,
		SF_PLUGIN_PATH . 'frontend/' . $class_file,
		SF_PLUGIN_PATH . 'platforms/' . $class_file,
	);

	foreach ( $paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}
}

spl_autoload_register( 'sf_autoloader' );

/**
 * Plugin activation.
 */
function sf_activate() {
	require_once SF_PLUGIN_PATH . 'includes/class-sf-database.php';
	require_once SF_PLUGIN_PATH . 'includes/class-sf-cron.php';

	SF_Database::create_tables();
	SF_Cron::schedule_events();

	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'sf_activate' );

/**
 * Plugin deactivation.
 */
function sf_deactivate() {
	require_once SF_PLUGIN_PATH . 'includes/class-sf-cron.php';

	SF_Cron::clear_events();

	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'sf_deactivate' );

/**
 * Initialize the plugin.
 */
function sf_init() {
	$core = new SF_Core();
	$core->init();
}

add_action( 'plugins_loaded', 'sf_init' );

/**
 * Render breadcrumb and back navigation for admin pages.
 *
 * @param array  $items     Breadcrumb items. Each: ['label' => string, 'url' => string]. Empty url = current.
 * @param string $back_url  URL for back button. Empty = no back button.
 * @param string $back_label Label for back button (e.g. "Back to Dashboard").
 */
function sf_render_breadcrumb( $items = array(), $back_url = '', $back_label = '' ) {
	if ( ! is_array( $items ) || empty( $items ) ) {
		return;
	}
	?>
	<div class="sf-breadcrumb-bar">
		<?php if ( $back_url && $back_label ) : ?>
			<a href="<?php echo esc_url( $back_url ); ?>" class="sf-back-link" title="<?php esc_attr_e( 'Alt + ←', 'social-feed' ); ?>">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<span class="sf-back-link-text"><?php echo esc_html( $back_label ); ?></span>
			</a>
		<?php endif; ?>
		<nav class="sf-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'social-feed' ); ?>">
			<?php
			$last_index  = count( $items ) - 1;
			$total_items = count( $items );
			foreach ( $items as $i => $item ) :
				$label = isset( $item['label'] ) ? $item['label'] : '';
				$url   = isset( $item['url'] ) ? $item['url'] : '';
				if ( empty( $label ) ) {
					continue;
				}
				$hide_mobile = ( $total_items > 2 && $i < $total_items - 2 ) ? ' sf-breadcrumb__item--hide-mobile' : '';
				$sep_hide    = ( $i === 1 && $total_items > 2 ) ? ' sf-breadcrumb__item--hide-mobile' : '';
				if ( $i > 0 ) :
					?>
					<span class="sf-breadcrumb__sep<?php echo esc_attr( $sep_hide ); ?>" aria-hidden="true">›</span>
					<?php
				endif;
				if ( ! empty( $url ) && $i < $last_index ) :
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="sf-breadcrumb__item<?php echo esc_attr( $hide_mobile ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php
				else :
					?>
					<span class="sf-breadcrumb__current<?php echo esc_attr( $hide_mobile ); ?>"><?php echo esc_html( $label ); ?></span>
					<?php
				endif;
			endforeach;
			?>
		</nav>
	</div>
	<?php
}
