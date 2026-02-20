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

$table_name = $wpdb->prefix . 'social_feed_cache';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

$table_name = $wpdb->prefix . 'social_feed_posts';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

delete_metadata( 'user', 0, 'sf_', '', true );
