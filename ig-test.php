<?php
require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Unauthorized' );
}

echo '<div style="font-family: monospace; background: #111; color: #0f0; padding: 20px; font-size: 14px; max-width: 900px;">';

// === OAUTH DEBUG ===
echo '<h2 style="color:#ff0;">OAuth Debug Data</h2>';

$auth_debug  = get_option( 'sf_oauth_debug_auth', array() );
$token_debug = get_option( 'sf_oauth_debug_token', array() );

echo '<h3>Step 1: Auth URL (get_login_url)</h3>';
if ( ! empty( $auth_debug ) ) {
	echo '<pre style="color:#0ff; white-space:pre-wrap; word-break:break-all;">' . esc_html( print_r( $auth_debug, true ) ) . '</pre>';
} else {
	echo '<span style="color:red;">No auth debug data yet. Click Connect Instagram first.</span><br>';
}

echo '<h3>Step 2: Token Exchange (get_access_token)</h3>';
if ( ! empty( $token_debug ) ) {
	echo '<pre style="color:#ff0; white-space:pre-wrap; word-break:break-all;">' . esc_html( print_r( $token_debug, true ) ) . '</pre>';
} else {
	echo '<span style="color:red;">No token debug data yet. Complete the Instagram OAuth flow first.</span><br>';
}

// === Redirect URI comparison ===
if ( ! empty( $auth_debug['redirect_uri'] ) && ! empty( $token_debug['redirect_uri'] ) ) {
	echo '<h3>Redirect URI Match Check</h3>';
	$auth_uri  = $auth_debug['redirect_uri'];
	$token_uri = $token_debug['redirect_uri'];
	if ( $auth_uri === $token_uri ) {
		echo '<span style="color:#0f0; font-size:18px;">MATCH - Both URIs are identical</span><br>';
	} else {
		echo '<span style="color:red; font-size:18px;">MISMATCH!</span><br>';
		echo 'Auth:  <code>' . esc_html( $auth_uri ) . '</code><br>';
		echo 'Token: <code>' . esc_html( $token_uri ) . '</code><br>';
	}
	echo 'Auth URI length: ' . strlen( $auth_uri ) . '<br>';
	echo 'Token URI length: ' . strlen( $token_uri ) . '<br>';
}

echo '<hr>';

// === EXISTING API TEST ===
echo '<h2 style="color:#0f0;">Instagram API Test</h2>';

global $wpdb;
$table_name = $wpdb->prefix . 'sf_accounts';
$account = $wpdb->get_row( "SELECT * FROM $table_name WHERE platform = 'instagram' LIMIT 1" );

if ( ! $account ) {
	echo '<span style="color:red;">No connected Instagram account found.</span>';
} else {
	$access_token = $account->access_token;
	$user_id      = $account->account_id;

	echo "<strong>Account ID:</strong> " . esc_html( $user_id ) . "<br>";
	echo "<strong>Account ID Ext:</strong> " . esc_html( $account->account_id_ext ?? 'N/A' ) . "<br>";
	echo "<strong>Token Snippet:</strong> " . substr( $access_token, 0, 15 ) . "...<br><hr>";

	echo "<h3>Test: /me endpoint</h3>";
	$url1 = "https://graph.instagram.com/v25.0/me?fields=id,user_id,username&access_token=" . $access_token;
	$res1 = wp_remote_get( $url1 );
	if ( is_wp_error( $res1 ) ) {
		echo '<span style="color:red;">Error: ' . $res1->get_error_message() . '</span><br>';
	} else {
		echo "<pre style='color:#0ff;'>" . esc_html( print_r( json_decode( wp_remote_retrieve_body( $res1 ), true ), true ) ) . "</pre>";
	}
}

echo '</div>';
