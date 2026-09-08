<?php

define( 'ABSPATH', __DIR__ );

function add_filter() {}
function add_action() {}
function wp_unslash( $value ) { return $value; }
function esc_url_raw( $value ) { return $value; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function __( $value ) { return $value; }
function wp_get_current_user() {
	return (object) array(
		'display_name' => 'Jordan <Admin>',
		'user_email'   => 'jordan&test@example.com',
	);
}
function wp_logout_url( $redirect_to ) {
	return 'https://example.test/wp-login.php?action=logout&_wpnonce=nonce&redirect_to=' . rawurlencode( $redirect_to );
}

$_SERVER['REQUEST_URI'] = '/private/page?tab=one&view=two';

require dirname( __DIR__ ) . '/sb118-private-sites.php';

$message = sb118_private_site_denial_message();

assert( false !== strpos( $message, 'Jordan &lt;Admin&gt;' ) );
assert( false !== strpos( $message, 'jordan&amp;test@example.com' ) );
assert( false !== strpos( $message, 'action=logout' ) );
assert( false !== strpos( $message, '_wpnonce=nonce' ) );
assert( false !== strpos( $message, 'redirect_to=%2Fprivate%2Fpage%3Ftab%3Done%26view%3Dtwo' ) );

echo "denial-message: ok\n";
