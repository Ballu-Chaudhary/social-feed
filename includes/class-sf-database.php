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
	 * Database version.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Table names cache.
	 *
	 * @var array
	 */
	private static $tables = array();

	/**
	 * Get table name with prefix.
	 *
	 * @param string $table Table name without prefix.
	 * @return string
	 */
	public static function get_table( $table ) {
		global $wpdb;

		if ( ! isset( self::$tables[ $table ] ) ) {
			self::$tables[ $table ] = $wpdb->prefix . 'sf_' . $table;
		}

		return self::$tables[ $table ];
	}

	/**
	 * Create all plugin database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = array();

		$sql[] = "CREATE TABLE " . self::get_table( 'feeds' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			platform varchar(50) NOT NULL,
			account_id bigint(20) unsigned DEFAULT NULL,
			feed_type varchar(50) NOT NULL DEFAULT 'user',
			status varchar(20) NOT NULL DEFAULT 'active',
			shortcode varchar(100) DEFAULT NULL,
			post_count int(11) NOT NULL DEFAULT 10,
			cache_duration int(11) NOT NULL DEFAULT 3600,
			last_fetched datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY platform (platform),
			KEY account_id (account_id),
			KEY status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE " . self::get_table( 'accounts' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			platform varchar(50) NOT NULL,
			account_name varchar(255) NOT NULL,
			account_id_ext varchar(255) NOT NULL,
			account_type varchar(50) NOT NULL DEFAULT 'personal',
			access_token text,
			refresh_token text,
			token_expires datetime DEFAULT NULL,
			profile_pic varchar(500) DEFAULT NULL,
			followers bigint(20) unsigned DEFAULT 0,
			is_connected tinyint(1) NOT NULL DEFAULT 1,
			last_error text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY platform_account (platform, account_id_ext),
			KEY platform (platform),
			KEY is_connected (is_connected)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE " . self::get_table( 'feed_items' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			feed_id bigint(20) unsigned NOT NULL,
			platform varchar(50) NOT NULL,
			item_id_ext varchar(255) NOT NULL,
			item_type varchar(50) NOT NULL DEFAULT 'image',
			caption longtext,
			media_url varchar(1000) DEFAULT NULL,
			thumbnail_url varchar(1000) DEFAULT NULL,
			permalink varchar(1000) DEFAULT NULL,
			author_name varchar(255) DEFAULT NULL,
			likes_count bigint(20) unsigned DEFAULT 0,
			comments_count bigint(20) unsigned DEFAULT 0,
			views_count bigint(20) unsigned DEFAULT 0,
			video_url varchar(1000) DEFAULT NULL,
			duration int(11) DEFAULT NULL,
			is_hidden tinyint(1) NOT NULL DEFAULT 0,
			is_pinned tinyint(1) NOT NULL DEFAULT 0,
			sort_order int(11) NOT NULL DEFAULT 0,
			posted_at datetime DEFAULT NULL,
			fetched_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY feed_item (feed_id, item_id_ext),
			KEY feed_id (feed_id),
			KEY platform (platform),
			KEY is_hidden (is_hidden),
			KEY is_pinned (is_pinned),
			KEY posted_at (posted_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE " . self::get_table( 'feed_meta' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			feed_id bigint(20) unsigned NOT NULL,
			meta_key varchar(255) NOT NULL,
			meta_value longtext,
			PRIMARY KEY (id),
			UNIQUE KEY feed_meta_key (feed_id, meta_key),
			KEY feed_id (feed_id),
			KEY meta_key (meta_key)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE " . self::get_table( 'logs' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			feed_id bigint(20) unsigned DEFAULT NULL,
			account_id bigint(20) unsigned DEFAULT NULL,
			platform varchar(50) DEFAULT NULL,
			log_type varchar(20) NOT NULL DEFAULT 'info',
			message text NOT NULL,
			context longtext,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY feed_id (feed_id),
			KEY account_id (account_id),
			KEY log_type (log_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE " . self::get_table( 'licenses' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			license_key varchar(255) NOT NULL,
			plan varchar(50) NOT NULL DEFAULT 'free',
			status varchar(50) NOT NULL DEFAULT 'inactive',
			sites_allowed int(11) NOT NULL DEFAULT 1,
			sites_used int(11) NOT NULL DEFAULT 0,
			activated_on datetime DEFAULT NULL,
			expires_on datetime DEFAULT NULL,
			customer_email varchar(255) DEFAULT NULL,
			last_checked datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY license_key (license_key),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}

		update_option( 'sf_db_version', self::DB_VERSION );
	}

	// =========================================================================
	// FEEDS CRUD
	// =========================================================================

	/**
	 * Create a feed.
	 *
	 * @param array $data Feed data.
	 * @return int|false Insert ID or false on failure.
	 */
	public static function create_feed( $data ) {
		global $wpdb;

		$defaults = array(
			'name'           => '',
			'platform'       => '',
			'account_id'     => null,
			'feed_type'      => 'user',
			'status'         => 'active',
			'shortcode'      => null,
			'post_count'     => 10,
			'cache_duration' => 3600,
			'last_fetched'   => null,
		);

		$data   = wp_parse_args( $data, $defaults );
		$result = $wpdb->insert( self::get_table( 'feeds' ), $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get a feed by ID.
	 *
	 * @param int $id Feed ID.
	 * @return array|null
	 */
	public static function get_feed( $id ) {
		global $wpdb;
		$table = self::get_table( 'feeds' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);
	}

	/**
	 * Update a feed.
	 *
	 * @param int   $id   Feed ID.
	 * @param array $data Data to update.
	 * @return int|false Rows affected or false.
	 */
	public static function update_feed( $id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		return $wpdb->update(
			self::get_table( 'feeds' ),
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Delete a feed.
	 *
	 * @param int $id Feed ID.
	 * @return int|false Rows affected or false.
	 */
	public static function delete_feed( $id ) {
		global $wpdb;

		self::delete_feed_items_by_feed( $id );
		self::delete_feed_meta_by_feed( $id );

		return $wpdb->delete(
			self::get_table( 'feeds' ),
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get all feeds.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function get_all_feeds( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'platform' => '',
			'status'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'limit'    => 100,
			'offset'   => 0,
		);

		$args  = wp_parse_args( $args, $defaults );
		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['platform'] ) ) {
			$where[]  = 'platform = %s';
			$values[] = $args['platform'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		$allowed_orderby = array( 'id', 'name', 'platform', 'created_at', 'updated_at', 'last_fetched' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit           = absint( $args['limit'] );
		$offset          = absint( $args['offset'] );

		$where_clause = implode( ' AND ', $where );
		$table        = self::get_table( 'feeds' );

		$sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	// =========================================================================
	// ACCOUNTS CRUD
	// =========================================================================

	/**
	 * Create an account.
	 *
	 * @param array $data Account data.
	 * @return int|false Insert ID or false.
	 */
	public static function create_account( $data ) {
		global $wpdb;

		$defaults = array(
			'platform'       => '',
			'account_name'   => '',
			'account_id_ext' => '',
			'account_type'   => 'personal',
			'access_token'   => null,
			'refresh_token'  => null,
			'token_expires'  => null,
			'profile_pic'    => null,
			'followers'      => 0,
			'is_connected'   => 1,
			'last_error'     => null,
		);

		$data   = wp_parse_args( $data, $defaults );
		$result = $wpdb->insert( self::get_table( 'accounts' ), $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get an account by ID.
	 *
	 * @param int $id Account ID.
	 * @return array|null
	 */
	public static function get_account( $id ) {
		global $wpdb;
		$table = self::get_table( 'accounts' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);
	}

	/**
	 * Update an account.
	 *
	 * @param int   $id   Account ID.
	 * @param array $data Data to update.
	 * @return int|false Rows affected or false.
	 */
	public static function update_account( $id, $data ) {
		global $wpdb;

		return $wpdb->update(
			self::get_table( 'accounts' ),
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Delete an account.
	 *
	 * @param int $id Account ID.
	 * @return int|false Rows affected or false.
	 */
	public static function delete_account( $id ) {
		global $wpdb;

		return $wpdb->delete(
			self::get_table( 'accounts' ),
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get all accounts.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function get_all_accounts( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'platform'     => '',
			'is_connected' => null,
			'orderby'      => 'created_at',
			'order'        => 'DESC',
			'limit'        => 100,
			'offset'       => 0,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['platform'] ) ) {
			$where[]  = 'platform = %s';
			$values[] = $args['platform'];
		}

		if ( null !== $args['is_connected'] ) {
			$where[]  = 'is_connected = %d';
			$values[] = (int) $args['is_connected'];
		}

		$allowed_orderby = array( 'id', 'platform', 'account_name', 'created_at', 'followers' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit           = absint( $args['limit'] );
		$offset          = absint( $args['offset'] );

		$where_clause = implode( ' AND ', $where );
		$table        = self::get_table( 'accounts' );

		$sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get account by platform and external ID.
	 *
	 * @param string $platform       Platform name.
	 * @param string $account_id_ext External account ID.
	 * @return array|null
	 */
	public static function get_account_by_external_id( $platform, $account_id_ext ) {
		global $wpdb;
		$table = self::get_table( 'accounts' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE platform = %s AND account_id_ext = %s",
				$platform,
				$account_id_ext
			),
			ARRAY_A
		);
	}

	// =========================================================================
	// FEED ITEMS CRUD
	// =========================================================================

	/**
	 * Create a feed item.
	 *
	 * @param array $data Item data.
	 * @return int|false Insert ID or false.
	 */
	public static function create_feed_item( $data ) {
		global $wpdb;

		$defaults = array(
			'feed_id'        => 0,
			'platform'       => '',
			'item_id_ext'    => '',
			'item_type'      => 'image',
			'caption'        => null,
			'media_url'      => null,
			'thumbnail_url'  => null,
			'permalink'      => null,
			'author_name'    => null,
			'likes_count'    => 0,
			'comments_count' => 0,
			'views_count'    => 0,
			'video_url'      => null,
			'duration'       => null,
			'is_hidden'      => 0,
			'is_pinned'      => 0,
			'sort_order'     => 0,
			'posted_at'      => null,
		);

		$data   = wp_parse_args( $data, $defaults );
		$result = $wpdb->insert( self::get_table( 'feed_items' ), $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get a feed item by ID.
	 *
	 * @param int $id Item ID.
	 * @return array|null
	 */
	public static function get_feed_item( $id ) {
		global $wpdb;
		$table = self::get_table( 'feed_items' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);
	}

	/**
	 * Update a feed item.
	 *
	 * @param int   $id   Item ID.
	 * @param array $data Data to update.
	 * @return int|false Rows affected or false.
	 */
	public static function update_feed_item( $id, $data ) {
		global $wpdb;

		return $wpdb->update(
			self::get_table( 'feed_items' ),
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Delete a feed item.
	 *
	 * @param int $id Item ID.
	 * @return int|false Rows affected or false.
	 */
	public static function delete_feed_item( $id ) {
		global $wpdb;

		return $wpdb->delete(
			self::get_table( 'feed_items' ),
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get all feed items for a feed.
	 *
	 * @param int   $feed_id Feed ID.
	 * @param array $args    Query arguments.
	 * @return array
	 */
	public static function get_all_feed_items( $feed_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'include_hidden' => false,
			'orderby'        => 'posted_at',
			'order'          => 'DESC',
			'limit'          => 50,
			'offset'         => 0,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array( 'feed_id = %d' );
		$values = array( $feed_id );

		if ( ! $args['include_hidden'] ) {
			$where[] = 'is_hidden = 0';
		}

		$allowed_orderby = array( 'id', 'posted_at', 'fetched_at', 'likes_count', 'comments_count', 'sort_order' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'posted_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit           = absint( $args['limit'] );
		$offset          = absint( $args['offset'] );

		$where_clause = implode( ' AND ', $where );
		$table        = self::get_table( 'feed_items' );

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY is_pinned DESC, {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}",
			$values
		);

		$results = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Delete all feed items for a feed.
	 *
	 * @param int $feed_id Feed ID.
	 * @return int|false Rows affected or false.
	 */
	public static function delete_feed_items_by_feed( $feed_id ) {
		global $wpdb;

		return $wpdb->delete(
			self::get_table( 'feed_items' ),
			array( 'feed_id' => $feed_id ),
			array( '%d' )
		);
	}

	/**
	 * Upsert feed items (INSERT ... ON DUPLICATE KEY UPDATE).
	 *
	 * @param array $items Array of item data arrays.
	 * @return int Number of items processed.
	 */
	public static function upsert_feed_items( $items ) {
		global $wpdb;

		if ( empty( $items ) ) {
			return 0;
		}

		$table = self::get_table( 'feed_items' );
		$count = 0;

		foreach ( $items as $item ) {
			$defaults = array(
				'feed_id'        => 0,
				'platform'       => '',
				'item_id_ext'    => '',
				'item_type'      => 'image',
				'caption'        => null,
				'media_url'      => null,
				'thumbnail_url'  => null,
				'permalink'      => null,
				'author_name'    => null,
				'likes_count'    => 0,
				'comments_count' => 0,
				'views_count'    => 0,
				'video_url'      => null,
				'duration'       => null,
				'is_hidden'      => 0,
				'is_pinned'      => 0,
				'sort_order'     => 0,
				'posted_at'      => null,
				'fetched_at'     => current_time( 'mysql' ),
			);

			$item = wp_parse_args( $item, $defaults );

			$sql = $wpdb->prepare(
				"INSERT INTO {$table}
				(feed_id, platform, item_id_ext, item_type, caption, media_url, thumbnail_url, permalink,
				 author_name, likes_count, comments_count, views_count, video_url, duration,
				 is_hidden, is_pinned, sort_order, posted_at, fetched_at)
				VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %s, %d, %d, %d, %s, %d, %d, %d, %d, %s, %s)
				ON DUPLICATE KEY UPDATE
				item_type = VALUES(item_type),
				caption = VALUES(caption),
				media_url = VALUES(media_url),
				thumbnail_url = VALUES(thumbnail_url),
				permalink = VALUES(permalink),
				author_name = VALUES(author_name),
				likes_count = VALUES(likes_count),
				comments_count = VALUES(comments_count),
				views_count = VALUES(views_count),
				video_url = VALUES(video_url),
				duration = VALUES(duration),
				fetched_at = VALUES(fetched_at)",
				$item['feed_id'],
				$item['platform'],
				$item['item_id_ext'],
				$item['item_type'],
				$item['caption'],
				$item['media_url'],
				$item['thumbnail_url'],
				$item['permalink'],
				$item['author_name'],
				$item['likes_count'],
				$item['comments_count'],
				$item['views_count'],
				$item['video_url'],
				$item['duration'],
				$item['is_hidden'],
				$item['is_pinned'],
				$item['sort_order'],
				$item['posted_at'],
				$item['fetched_at']
			);

			if ( false !== $wpdb->query( $sql ) ) {
				$count++;
			}
		}

		return $count;
	}

	// =========================================================================
	// FEED META CRUD
	// =========================================================================

	/**
	 * Create feed meta.
	 *
	 * @param array $data Meta data.
	 * @return int|false Insert ID or false.
	 */
	public static function create_feed_meta( $data ) {
		global $wpdb;

		$defaults = array(
			'feed_id'    => 0,
			'meta_key'   => '',
			'meta_value' => null,
		);

		$data   = wp_parse_args( $data, $defaults );
		$result = $wpdb->insert( self::get_table( 'feed_meta' ), $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get feed meta by ID.
	 *
	 * @param int $id Meta ID.
	 * @return array|null
	 */
	public static function get_feed_meta( $id ) {
		global $wpdb;
		$table = self::get_table( 'feed_meta' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);
	}

	/**
	 * Get a single meta value for a feed.
	 *
	 * @param int    $feed_id  Feed ID.
	 * @param string $meta_key Meta key.
	 * @return mixed|null
	 */
	public static function get_feed_meta_value( $feed_id, $meta_key ) {
		global $wpdb;
		$table = self::get_table( 'feed_meta' );

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$table} WHERE feed_id = %d AND meta_key = %s",
				$feed_id,
				$meta_key
			)
		);

		return maybe_unserialize( $value );
	}

	/**
	 * Update feed meta.
	 *
	 * @param int   $id   Meta ID.
	 * @param array $data Data to update.
	 * @return int|false Rows affected or false.
	 */
	public static function update_feed_meta( $id, $data ) {
		global $wpdb;

		return $wpdb->update(
			self::get_table( 'feed_meta' ),
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Set feed meta (insert or update).
	 *
	 * @param int    $feed_id    Feed ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return bool
	 */
	public static function set_feed_meta_value( $feed_id, $meta_key, $meta_value ) {
		global $wpdb;

		$table      = self::get_table( 'feed_meta' );
		$meta_value = maybe_serialize( $meta_value );

		$sql = $wpdb->prepare(
			"INSERT INTO {$table} (feed_id, meta_key, meta_value)
			VALUES (%d, %s, %s)
			ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)",
			$feed_id,
			$meta_key,
			$meta_value
		);

		return false !== $wpdb->query( $sql );
	}

	/**
	 * Delete feed meta.
	 *
	 * @param int $id Meta ID.
	 * @return int|false Rows affected or false.
	 */
	public static function delete_feed_meta( $id ) {
		global $wpdb;

		return $wpdb->delete(
			self::get_table( 'feed_meta' ),
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Delete all meta for a feed.
	 *
	 * @param int $feed_id Feed ID.
	 * @return int|false Rows affected or false.
	 */
	public static function delete_feed_meta_by_feed( $feed_id ) {
		global $wpdb;

		return $wpdb->delete(
			self::get_table( 'feed_meta' ),
			array( 'feed_id' => $feed_id ),
			array( '%d' )
		);
	}

	/**
	 * Get all meta for a feed.
	 *
	 * @param int $feed_id Feed ID.
	 * @return array Associative array of meta_key => meta_value.
	 */
	public static function get_all_feed_meta( $feed_id ) {
		global $wpdb;
		$table = self::get_table( 'feed_meta' );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$table} WHERE feed_id = %d",
				$feed_id
			),
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		$meta = array();
		foreach ( $results as $row ) {
			$meta[ $row['meta_key'] ] = maybe_unserialize( $row['meta_value'] );
		}

		return $meta;
	}

	// =========================================================================
	// LOGS CRUD
	// =========================================================================

	/**
	 * Create a log entry.
	 *
	 * @param array $data Log data.
	 * @return int|false Insert ID or false.
	 */
	public static function create_log( $data ) {
		global $wpdb;

		$defaults = array(
			'feed_id'    => null,
			'account_id' => null,
			'platform'   => null,
			'log_type'   => 'info',
			'message'    => '',
			'context'    => null,
		);

		$data = wp_parse_args( $data, $defaults );

		if ( is_array( $data['context'] ) || is_object( $data['context'] ) ) {
			$data['context'] = wp_json_encode( $data['context'] );
		}

		$result = $wpdb->insert( self::get_table( 'logs' ), $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get a log entry by ID.
	 *
	 * @param int $id Log ID.
	 * @return array|null
	 */
	public static function get_log( $id ) {
		global $wpdb;
		$table = self::get_table( 'logs' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);
	}

	/**
	 * Update a log entry.
	 *
	 * @param int   $id   Log ID.
	 * @param array $data Data to update.
	 * @return int|false Rows affected or false.
	 */
	public static function update_log( $id, $data ) {
		global $wpdb;

		if ( isset( $data['context'] ) && ( is_array( $data['context'] ) || is_object( $data['context'] ) ) ) {
			$data['context'] = wp_json_encode( $data['context'] );
		}

		return $wpdb->update(
			self::get_table( 'logs' ),
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Delete a log entry.
	 *
	 * @param int $id Log ID.
	 * @return int|false Rows affected or false.
	 */
	public static function delete_log( $id ) {
		global $wpdb;

		return $wpdb->delete(
			self::get_table( 'logs' ),
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get all logs.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function get_all_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'feed_id'    => null,
			'account_id' => null,
			'platform'   => '',
			'log_type'   => '',
			'orderby'    => 'created_at',
			'order'      => 'DESC',
			'limit'      => 100,
			'offset'     => 0,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array( '1=1' );
		$values = array();

		if ( null !== $args['feed_id'] ) {
			$where[]  = 'feed_id = %d';
			$values[] = $args['feed_id'];
		}

		if ( null !== $args['account_id'] ) {
			$where[]  = 'account_id = %d';
			$values[] = $args['account_id'];
		}

		if ( ! empty( $args['platform'] ) ) {
			$where[]  = 'platform = %s';
			$values[] = $args['platform'];
		}

		if ( ! empty( $args['log_type'] ) ) {
			$where[]  = 'log_type = %s';
			$values[] = $args['log_type'];
		}

		$allowed_orderby = array( 'id', 'created_at', 'log_type' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit           = absint( $args['limit'] );
		$offset          = absint( $args['offset'] );

		$where_clause = implode( ' AND ', $where );
		$table        = self::get_table( 'logs' );

		$sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Delete logs older than specified days.
	 *
	 * @param int $days Number of days to keep logs (default 30).
	 * @return int Number of deleted rows.
	 */
	public static function cleanup_old_logs( $days = 30 ) {
		global $wpdb;

		$days = absint( $days );
		if ( $days < 1 ) {
			$days = 30;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		$table  = self::get_table( 'logs' );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				$cutoff
			)
		);

		return is_numeric( $deleted ) ? $deleted : 0;
	}

	// =========================================================================
	// LICENSES CRUD
	// =========================================================================

	/**
	 * Create a license.
	 *
	 * @param array $data License data.
	 * @return int|false Insert ID or false.
	 */
	public static function create_license( $data ) {
		global $wpdb;

		$defaults = array(
			'license_key'    => '',
			'plan'           => 'free',
			'status'         => 'inactive',
			'sites_allowed'  => 1,
			'sites_used'     => 0,
			'activated_on'   => null,
			'expires_on'     => null,
			'customer_email' => null,
			'last_checked'   => null,
		);

		$data   = wp_parse_args( $data, $defaults );
		$result = $wpdb->insert( self::get_table( 'licenses' ), $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get a license by ID.
	 *
	 * @param int $id License ID.
	 * @return array|null
	 */
	public static function get_license( $id ) {
		global $wpdb;
		$table = self::get_table( 'licenses' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);
	}

	/**
	 * Get license by key.
	 *
	 * @param string $license_key License key.
	 * @return array|null
	 */
	public static function get_license_by_key( $license_key ) {
		global $wpdb;
		$table = self::get_table( 'licenses' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE license_key = %s",
				$license_key
			),
			ARRAY_A
		);
	}

	/**
	 * Update a license.
	 *
	 * @param int   $id   License ID.
	 * @param array $data Data to update.
	 * @return int|false Rows affected or false.
	 */
	public static function update_license( $id, $data ) {
		global $wpdb;

		return $wpdb->update(
			self::get_table( 'licenses' ),
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Delete a license.
	 *
	 * @param int $id License ID.
	 * @return int|false Rows affected or false.
	 */
	public static function delete_license( $id ) {
		global $wpdb;

		return $wpdb->delete(
			self::get_table( 'licenses' ),
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get all licenses.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function get_all_licenses( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'  => '',
			'plan'    => '',
			'orderby' => 'activated_on',
			'order'   => 'DESC',
			'limit'   => 100,
			'offset'  => 0,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['plan'] ) ) {
			$where[]  = 'plan = %s';
			$values[] = $args['plan'];
		}

		$allowed_orderby = array( 'id', 'plan', 'status', 'activated_on', 'expires_on' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'activated_on';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit           = absint( $args['limit'] );
		$offset          = absint( $args['offset'] );

		$where_clause = implode( ' AND ', $where );
		$table        = self::get_table( 'licenses' );

		$sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get active license.
	 *
	 * @return array|null
	 */
	public static function get_active_license() {
		global $wpdb;
		$table = self::get_table( 'licenses' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY activated_on DESC LIMIT 1",
				'active'
			),
			ARRAY_A
		);
	}

	// =========================================================================
	// HELPER LOG METHODS
	// =========================================================================

	/**
	 * Log an error.
	 *
	 * @param string     $message    Error message.
	 * @param array|null $context    Additional context.
	 * @param int|null   $feed_id    Feed ID.
	 * @param int|null   $account_id Account ID.
	 * @param string     $platform   Platform name.
	 * @return int|false
	 */
	public static function log_error( $message, $context = null, $feed_id = null, $account_id = null, $platform = '' ) {
		return self::create_log(
			array(
				'feed_id'    => $feed_id,
				'account_id' => $account_id,
				'platform'   => $platform,
				'log_type'   => 'error',
				'message'    => $message,
				'context'    => $context,
			)
		);
	}

	/**
	 * Log a warning.
	 *
	 * @param string     $message    Warning message.
	 * @param array|null $context    Additional context.
	 * @param int|null   $feed_id    Feed ID.
	 * @param int|null   $account_id Account ID.
	 * @param string     $platform   Platform name.
	 * @return int|false
	 */
	public static function log_warning( $message, $context = null, $feed_id = null, $account_id = null, $platform = '' ) {
		return self::create_log(
			array(
				'feed_id'    => $feed_id,
				'account_id' => $account_id,
				'platform'   => $platform,
				'log_type'   => 'warning',
				'message'    => $message,
				'context'    => $context,
			)
		);
	}

	/**
	 * Log an info message.
	 *
	 * @param string     $message    Info message.
	 * @param array|null $context    Additional context.
	 * @param int|null   $feed_id    Feed ID.
	 * @param int|null   $account_id Account ID.
	 * @param string     $platform   Platform name.
	 * @return int|false
	 */
	public static function log_info( $message, $context = null, $feed_id = null, $account_id = null, $platform = '' ) {
		return self::create_log(
			array(
				'feed_id'    => $feed_id,
				'account_id' => $account_id,
				'platform'   => $platform,
				'log_type'   => 'info',
				'message'    => $message,
				'context'    => $context,
			)
		);
	}

	/**
	 * Log a success message.
	 *
	 * @param string     $message    Success message.
	 * @param array|null $context    Additional context.
	 * @param int|null   $feed_id    Feed ID.
	 * @param int|null   $account_id Account ID.
	 * @param string     $platform   Platform name.
	 * @return int|false
	 */
	public static function log_success( $message, $context = null, $feed_id = null, $account_id = null, $platform = '' ) {
		return self::create_log(
			array(
				'feed_id'    => $feed_id,
				'account_id' => $account_id,
				'platform'   => $platform,
				'log_type'   => 'success',
				'message'    => $message,
				'context'    => $context,
			)
		);
	}
}
