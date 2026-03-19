<?php
// Load WordPress core directly
require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

echo '<div style="font-family: monospace; background: #111; color: #0f0; padding: 20px; font-size: 16px;">';
echo '<h2>🚀 Instagram API Direct Connection Test</h2>';

global $wpdb;
// Find the exact table name for the plugin's accounts
$table_name = $wpdb->prefix . 'sf_accounts'; 

// Fetch the account
$account = $wpdb->get_row( "SELECT * FROM $table_name WHERE platform = 'instagram' LIMIT 1" );

if ( ! $account ) {
    die( '<span style="color:red;">ERROR: No connected Instagram account found in database table ' . $table_name . '</span></div>' );
}

$access_token = $account->access_token;
$user_id      = $account->account_id;

echo "<strong>Registered Account ID:</strong> " . esc_html( $user_id ) . "<br>";
echo "<strong>Access Token Snippet:</strong> " . substr( $access_token, 0, 15 ) . "...<br><hr>";

// TEST 1: The /me Endpoint
echo "<h3>Test 1: Fetching Basic Profile (/me)</h3>";
$url1 = "https://graph.instagram.com/v25.0/me?fields=id,user_id,username&access_token=" . $access_token;
$res1 = wp_remote_get( $url1 );

if ( is_wp_error( $res1 ) ) {
    echo '<span style="color:red;">WP HTTP Error: ' . $res1->get_error_message() . '</span><br>';
} else {
    echo "Raw Response:<br>";
    echo "<pre style='color: #0ff;'>" . print_r( json_decode( wp_remote_retrieve_body( $res1 ), true ), true ) . "</pre>";
}

echo "<hr>";

// TEST 2: The /media Endpoint (Where the posts fail)
echo "<h3>Test 2: Fetching Posts (/{user_id}/media)</h3>";
$url2 = "https://graph.instagram.com/v25.0/" . $user_id . "/media?fields=id,media_type,media_url,thumbnail_url,permalink,timestamp&access_token=" . $access_token;
$res2 = wp_remote_get( $url2 );

if ( is_wp_error( $res2 ) ) {
    echo '<span style="color:red;">WP HTTP Error: ' . $res2->get_error_message() . '</span><br>';
} else {
    echo "Raw Response:<br>";
    echo "<pre style='color: #ff0;'>" . print_r( json_decode( wp_remote_retrieve_body( $res2 ), true ), true ) . "</pre>";
}

echo '</div>';
