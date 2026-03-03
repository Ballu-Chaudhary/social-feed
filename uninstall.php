<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package SocialFeed
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom database tables (matches SF_Database::create_tables).
$tables = array(
	$wpdb->prefix . 'sf_feeds',
	$wpdb->prefix . 'sf_accounts',
	$wpdb->prefix . 'sf_feed_items',
	$wpdb->prefix . 'sf_feed_meta',
	$wpdb->prefix . 'sf_logs',
	$wpdb->prefix . 'sf_licenses',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete plugin options.
$option_names = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'sf_' ) . '%'
	)
);

if ( ! empty( $option_names ) ) {
	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}
}

// Clear plugin transients (e.g. sf_feed_cache_*, sf_license_status_changed).
$transient_names = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_sf_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_sf_' ) . '%'
	)
);

if ( ! empty( $transient_names ) ) {
	foreach ( $transient_names as $transient_name ) {
		if ( strpos( $transient_name, '_transient_timeout_' ) === 0 ) {
			$transient_name = str_replace( '_transient_timeout_', '', $transient_name );
		} else {
			$transient_name = str_replace( '_transient_', '', $transient_name );
		}
		delete_transient( $transient_name );
	}
}

// Delete user meta (e.g. sf_upgrade_banner_dismissed).
delete_metadata( 'user', 0, 'sf_upgrade_banner_dismissed', '', true );

// Delete any other sf_ prefixed user meta.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( 'sf_' ) . '%'
	)
); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
