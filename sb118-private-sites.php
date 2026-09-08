<?php
/**
 * Plugin Name:       SB118 Private Sites
 * Plugin URI:        https://github.com/StarBase118/sb118-private-sites
 * Description:       Enforces per-site membership on private multisite sub-sites (blog_public=0).
 *                    Blocks REST API, RSS/Atom feeds, and direct page access for users not added to
 *                    the specific sub-site. Network super admins always have access.
 *                    Replaces jonradio-private-site with a single must-use plugin.
 * Version:           1.4.0
 * Author:            StarBase 118
 * Author URI:        https://www.starbase118.net
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sb118-private-sites
 * Network:           true
 * Requires at least: 5.0
 * Requires PHP:      7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if the current site is marked as private (blog_public = 0).
 */
function sb118_is_private_site() {
	return '0' === get_option( 'blog_public' );
}

/**
 * Check if the current user has access to this specific sub-site.
 * Requires both: logged in AND a member of this sub-site's user list.
 * Network super admins always have access.
 */
function sb118_user_can_access_site() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	// Super admins can access everything
	if ( is_super_admin() ) {
		return true;
	}

	// User must be explicitly added to this sub-site
	return is_user_member_of_blog( get_current_user_id(), get_current_blog_id() );
}

/**
 * Explain a private-site denial and let the user switch accounts safely.
 */
function sb118_private_site_denial_message() {
	$user        = wp_get_current_user();
	$redirect_to = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$logout_url  = wp_logout_url( $redirect_to );

	return sprintf(
		__( 'This site is private, and your account does not have access to it. If you think it should, contact the site\'s staff team.<br><br>You are signed in as <strong>%1$s</strong> (%2$s). <a href="%3$s">Sign out and use another account</a>.', 'sb118-private-sites' ),
		esc_html( $user->display_name ),
		esc_html( $user->user_email ),
		esc_url( $logout_url )
	);
}

/**
 * Block REST API requests on private sites for unauthenticated users.
 */
add_filter( 'rest_authentication_errors', function ( $result ) {
	if ( ! sb118_is_private_site() ) {
		return $result;
	}

	if ( sb118_user_can_access_site() ) {
		return $result;
	}

	return new WP_Error(
		'rest_login_required',
		__( 'This site is private. Authentication is required.', 'sb118-private-sites' ),
		array( 'status' => 401 )
	);
}, 99 );

/**
 * Disable RSS/Atom feeds on private sites for unauthenticated users.
 */
add_action( 'do_feed', 'sb118_block_private_feeds', 1 );
add_action( 'do_feed_rdf', 'sb118_block_private_feeds', 1 );
add_action( 'do_feed_rss', 'sb118_block_private_feeds', 1 );
add_action( 'do_feed_rss2', 'sb118_block_private_feeds', 1 );
add_action( 'do_feed_atom', 'sb118_block_private_feeds', 1 );

function sb118_block_private_feeds() {
	if ( ! sb118_is_private_site() ) {
		return;
	}

	if ( sb118_user_can_access_site() ) {
		return;
	}

	wp_die(
		__( 'This site is private. Please log in to access feeds.', 'sb118-private-sites' ),
		__( 'Private Site', 'sb118-private-sites' ),
		array( 'response' => 403 )
	);
}

/**
 * Gate direct page access on private sites.
 *
 * Two different failures need two different answers. Sending a logged-out visitor to the
 * login page is correct. Sending an *already authenticated* user there is not: wp-login.php
 * sees their valid auth cookie, wp_signon() succeeds, and it redirects them straight back to
 * the page they came from, which redirects them to login again. That loop runs until nginx's
 * per-IP login rate limit trips and serves a 503, so the member sees a server error rather
 * than an explanation.
 *
 * A user who is signed in but not a member of this sub-site is not going to become one by
 * logging in again, so tell them that instead of bouncing them.
 *
 * Allows wp-login.php, wp-cron.php, and admin-ajax.php through.
 */
add_action( 'template_redirect', function () {
	if ( ! sb118_is_private_site() ) {
		return;
	}

	if ( sb118_user_can_access_site() ) {
		return;
	}

	// Don't block login, cron, or AJAX
	$script  = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
	$allowed = array( 'wp-login.php', 'wp-cron.php', 'admin-ajax.php', 'wp-activate.php' );
	if ( in_array( $script, $allowed, true ) ) {
		return;
	}

	// Signed in, but not a member of this sub-site. Logging in again cannot fix that.
	if ( is_user_logged_in() ) {
		nocache_headers();
		wp_die(
			sb118_private_site_denial_message(),
			__( 'Private Site', 'sb118-private-sites' ),
			array( 'response' => 403 )
		);
	}

	$redirect_to = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	wp_safe_redirect( wp_login_url( $redirect_to ) );
	exit;
}, 0 );

/**
 * Remove feed links from <head> on private sites for unauthenticated users.
 */
add_action( 'wp', function () {
	if ( ! sb118_is_private_site() ) {
		return;
	}

	if ( sb118_user_can_access_site() ) {
		return;
	}

	remove_action( 'wp_head', 'feed_links', 2 );
	remove_action( 'wp_head', 'feed_links_extra', 3 );
} );
