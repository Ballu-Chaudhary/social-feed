<?php
/**
 * Database operations for Social Feed plugin.
 *
 * @package SocialFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SF_Database
 */
class SF_Database {

	/**
	 * Create plugin database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_posts    = $wpdb->prefix . 'social_feed_posts';
		$table_cache    = $wpdb->prefix . 'social_feed_cache';

		$sql_posts = "CREATE TABLE {$table_posts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			platform varchar(50) NOT NULL,
			post_id varchar(255) NOT NULL,
			feed_id varchar(255) NOT NULL DEFAULT '',
			content longtext,
			permalink varchar(500) DEFAULT NULL,
			thumbnail_url varchar(500) DEFAULT NULL,
			author_name varchar(255) DEFAULT NULL,
			author_avatar varchar(500) DEFAULT NULL,
			created_at datetime DEFAULT NULL,
			raw_data longtext,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY platform_post (platform, post_id),
			KEY platform (platform),
			KEY feed_id (feed_id)
		) {$charset_collate};";

		$sql_cache = "CREATE TABLE {$table_cache} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			cache_key varchar(255) NOT NULL,
			cache_value longtext,
			expires_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY cache_key (cache_key)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_posts );
		dbDelta( $sql_cache );

		update_option( 'sf_db_version', SF_VERSION );
	}

	/**
	 * Get posts from database.
	 *
	 * @param string $platform Platform name (instagram, youtube, facebook).
	 * @param string $feed_id  Feed identifier.
	 * @param int    $limit    Number of posts to return.
	 * @return array
	 */
	public static function get_posts( $platform, $feed_id = '', $limit = 10 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'social_feed_posts';

		$where = $wpdb->prepare( 'platform = %s', $platform );
		if ( ! empty( $feed_id ) ) {
			$where .= $wpdb->prepare( ' AND feed_id = %s', $feed_id );
		}

		$limit = absint( $limit );
		$limit = $limit > 0 ? $limit : 10;

		$results = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT {$limit}",
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Insert or update a post.
	 *
	 * @param array $data Post data.
	 * @return int|false Rows affected or false on failure.
	 */
	public static function upsert_post( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'social_feed_posts';

		$defaults = array(
			'platform'      => '',
			'post_id'       => '',
			'feed_id'       => '',
			'content'       => '',
			'permalink'     => '',
			'thumbnail_url' => '',
			'author_name'   => '',
			'author_avatar' => '',
			'created_at'    => current_time( 'mysql' ),
			'raw_data'      => '',
		);

		$data = wp_parse_args( $data, $defaults );

		return $wpdb->replace( $table, $data );
	}

	/**
	 * Delete posts by platform and optionally feed_id.
	 *
	 * @param string $platform Platform name.
	 * @param string $feed_id  Optional feed identifier.
	 * @return int|false Rows affected or false.
	 */
	public static function delete_posts( $platform, $feed_id = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'social_feed_posts';

		if ( empty( $feed_id ) ) {
			return $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE platform = %s", $platform ) );
		}

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE platform = %s AND feed_id = %s",
				$platform,
				$feed_id
			)
		);
	}
}
