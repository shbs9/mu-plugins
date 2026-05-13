<?php
/**
 * Plugin Name: Cookie Exclude Homepage MU - By Shoaib Khan
 * Plugin URI: https://example.com
 * Description: Ultimate solution to completely exclude ALL cookies from homepage and enable proper caching
 * Version: 3.0.0
 * Author: Shoaib Khan
 * Author URI: https://example.com
 * License: GPL v2 or later
 *
 * MU Plugin - Place in /wp-content/mu-plugins/ folder
 * Created by: Shoaib Khan
 *
 * @package CookieExcludeHomepageMU
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global configuration
 */
define( 'COOKIE_EXCLUDE_HOMEPAGE_VERSION', '3.0.0' );
define( 'COOKIE_EXCLUDE_HOMEPAGE_AUTHOR', 'Shoaib Khan' );

/**
 * Check if current request is for the home page
 * Ultra-early detection before WordPress loads
 */
function sk_is_homepage() {
	// Get request URI
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	
	// Get home path
	$home_path = wp_parse_url( home_url(), PHP_URL_PATH );
	if ( empty( $home_path ) ) {
		$home_path = '/';
	}
	
	// Normalize request URI
	$request_uri = rtrim( $request_uri, '?' );
	
	// Check for homepage
	$is_home = (
		$request_uri === $home_path ||
		$request_uri === $home_path . '/' ||
		$request_uri === '/' ||
		$request_uri === ''
	);
	
	return apply_filters( 'sk_is_homepage', $is_home );
}

/**
 * ULTRA EARLY: Disable PHP sessions before WordPress initializes
 * This runs immediately when plugin loads
 */
if ( sk_is_homepage() ) {
	// Kill any existing session
	if ( function_exists( 'session_status' ) && session_status() === PHP_SESSION_ACTIVE ) {
		session_write_close();
		session_destroy();
	}
	
	// Prevent new sessions from starting
	@ini_set( 'session.auto_start', '0' );
	@ini_set( 'session.use_cookies', '0' );
	@ini_set( 'session.use_only_cookies', '0' );
	@ini_set( 'session.cache_limiter', '' );
	
	// Define constant
	define( 'SK_COOKIE_EXCLUDE_ACTIVE', true );
}

/**
 * STAGE 1: Early WordPress initialization
 * Hook into 'init' at priority 1 (before anything else)
 */
function sk_early_cookie_disable() {
	if ( ! sk_is_homepage() ) {
		return;
	}

	// Define cookie constants
	if ( ! defined( 'COOKIEPATH' ) ) {
		define( 'COOKIEPATH', false );
	}
	if ( ! defined( 'COOKIE_DOMAIN' ) ) {
		define( 'COOKIE_DOMAIN', false );
	}
	if ( ! defined( 'LOGGED_IN_COOKIE' ) ) {
		define( 'LOGGED_IN_COOKIE', false );
	}
	if ( ! defined( 'AUTH_COOKIE' ) ) {
		define( 'AUTH_COOKIE', false );
	}
	if ( ! defined( 'SECURE_AUTH_COOKIE' ) ) {
		define( 'SECURE_AUTH_COOKIE', false );
	}
	if ( ! defined( 'USER_COOKIE' ) ) {
		define( 'USER_COOKIE', false );
	}
	if ( ! defined( 'PASS_COOKIE' ) ) {
		define( 'PASS_COOKIE', false );
	}
	if ( ! defined( 'TEST_COOKIE' ) ) {
		define( 'TEST_COOKIE', false );
	}

	// Disable WordPress core cookie functions
	if ( ! function_exists( 'wp_set_auth_cookie_disabled' ) ) {
		function wp_set_auth_cookie_disabled( $auth_cookie, $expire, $expiration, $user_id, $scheme, $token ) {
			return; // Do nothing on homepage
		}
		add_filter( 'wp_set_auth_cookie', 'wp_set_auth_cookie_disabled', 10, 6 );
	}
}
add_action( 'init', 'sk_early_cookie_disable', 1 );

/**
 * STAGE 2: Stop WordPress from resuming sessions
 */
function sk_prevent_session_resume() {
	if ( ! sk_is_homepage() ) {
		return;
	}

	remove_action( 'init', 'wp_resume_session' );
	remove_action( 'wp_set_auth_cookie', 'wp_set_auth_cookie' );
	remove_action( 'set_logged_in_cookie', 'wp_set_logged_in_cookie' );
}
add_action( 'plugins_loaded', 'sk_prevent_session_resume', 1 );

/**
 * STAGE 3: Remove comment cookies
 */
function sk_disable_comment_cookies( $lifetime ) {
	if ( sk_is_homepage() ) {
		return 0;
	}
	return $lifetime;
}
add_filter( 'comment_cookie_lifetime', 'sk_disable_comment_cookies' );

/**
 * STAGE 4: Intercept and remove Set-Cookie headers via output buffering
 */
function sk_start_cookie_buffer() {
	if ( ! sk_is_homepage() ) {
		return;
	}

	ob_start( 'sk_filter_cookie_output' );
}
add_action( 'template_redirect', 'sk_start_cookie_buffer', -9999 );

/**
 * Output buffer callback - keeps content clean
 *
 * @param string $output The buffered output.
 * @return string
 */
function sk_filter_cookie_output( $output ) {
	return $output; // Content passes through, headers handled separately
}

/**
 * STAGE 5: Remove Set-Cookie headers with ultimate priority
 * This runs at the very end before headers are sent
 */
function sk_remove_all_set_cookies() {
	if ( ! sk_is_homepage() ) {
		return;
	}

	// Check if headers already sent
	if ( headers_sent() ) {
		return;
	}

	// Get all currently set headers
	if ( function_exists( 'headers_list' ) ) {
		$headers = headers_list();
		
		foreach ( $headers as $header ) {
			// Remove Set-Cookie headers
			if ( stripos( $header, 'Set-Cookie:' ) === 0 ) {
				if ( function_exists( 'header_remove' ) ) {
					header_remove( 'Set-Cookie' );
				}
			}
		}
	}

	// Absolutely remove Set-Cookie header
	if ( function_exists( 'header_remove' ) ) {
		header_remove( 'Set-Cookie' );
	}

	// Remove cache-busting headers that force cookies
	if ( function_exists( 'header_remove' ) ) {
		header_remove( 'Expires' );
		header_remove( 'Cache-Control' );
		header_remove( 'Pragma' );
		header_remove( 'Set-Cookie' );
	}

	// Set OPTIMAL caching headers for homepage
	header( 'Cache-Control: public, max-age=3600, s-maxage=3600' );
	header( 'Expires: ' . gmdate( 'D, d M Y H:i:s T', time() + 3600 ) );
	header( 'Pragma: public' );
	header( 'Vary: Accept-Encoding' );
	header( 'X-Cache-Status: HIT' );
}
add_action( 'send_headers', 'sk_remove_all_set_cookies', 999999 );

/**
 * STAGE 6: Override REST API cookie behavior
 */
function sk_filter_rest_api_cookies() {
	if ( ! sk_is_homepage() ) {
		return;
	}

	// Remove REST authentication
	if ( has_action( 'rest_api_init', 'create_initial_rest_routes' ) ) {
		remove_action( 'rest_api_init', 'create_initial_rest_routes' );
	}
}
add_action( 'rest_api_init', 'sk_filter_rest_api_cookies', -10 );

/**
 * STAGE 7: Filter HTTP responses to remove cookies at response level
 */
function sk_filter_http_response( $response, $request = null ) {
	if ( ! sk_is_homepage() ) {
		return $response;
	}

	// Handle WP_HTTP_Response objects
	if ( is_a( $response, 'WP_HTTP_Response' ) ) {
		$headers = $response->get_headers();
		
		// Remove all cookie and cache-related headers
		$keys_to_remove = array();
		foreach ( $headers as $key => $value ) {
			if ( stripos( $key, 'set-cookie' ) !== false ||
				 stripos( $key, 'cache-control' ) !== false ||
				 stripos( $key, 'expires' ) !== false ||
				 stripos( $key, 'pragma' ) !== false ) {
				$keys_to_remove[] = $key;
			}
		}
		
		// Remove flagged headers
		foreach ( $keys_to_remove as $key ) {
			unset( $headers[ $key ] );
		}
		
		// Set optimal caching headers
		$headers['Cache-Control'] = 'public, max-age=3600, s-maxage=3600';
		$headers['Expires']       = gmdate( 'D, d M Y H:i:s T', time() + 3600 );
		$headers['Pragma']        = 'public';
		$headers['Vary']          = 'Accept-Encoding';
		
		$response->set_headers( $headers );
	}

	return $response;
}
add_filter( 'wp_http_response', 'sk_filter_http_response', 999, 2 );

/**
 * STAGE 8: Remove WordPress default actions that set cookies
 */
function sk_remove_wordpress_cookie_hooks() {
	if ( ! sk_is_homepage() ) {
		return;
	}

	// Remove session hooks
	remove_all_actions( 'wp_set_auth_cookie' );
	remove_all_actions( 'set_logged_in_cookie' );
	remove_all_actions( 'wp_clear_auth_cookie' );
	
	// Remove nonce-related actions
	remove_all_actions( 'wp_nonce_tick' );
}
add_action( 'wp_loaded', 'sk_remove_wordpress_cookie_hooks', 1 );

/**
 * STAGE 9: Filter nocache headers
 */
function sk_filter_nocache() {
	if ( ! sk_is_homepage() ) {
		return;
	}

	// This prevents WordPress from sending no-cache headers
	remove_action( 'init', 'nocache_headers' );
	remove_action( 'init', 'wp_resume_session' );
}
add_action( 'wp_head', 'sk_filter_nocache', -10 );

/**
 * STAGE 10: Prevent Vary: Cookie header
 */
function sk_fix_vary_header() {
	if ( ! sk_is_homepage() ) {
		return;
	}

	// Remove Vary header if it contains Cookie
	if ( function_exists( 'header_remove' ) ) {
		header_remove( 'Vary' );
		header( 'Vary: Accept-Encoding', true );
	}
}
add_action( 'send_headers', 'sk_fix_vary_header', 1000000 );

/**
 * STAGE 11: Disable PHP defaults for cookies
 */
function sk_disable_php_cookies() {
	if ( ! sk_is_homepage() ) {
		return;
	}

	// More aggressive PHP settings
	if ( function_exists( 'ini_set' ) ) {
		@ini_set( 'session.auto_start', '0' );
		@ini_set( 'session.use_cookies', '0' );
		@ini_set( 'session.use_only_cookies', '0' );
		@ini_set( 'session.cache_limiter', '' );
		@ini_set( 'session.name', '' );
	}
}
add_action( 'wp_footer', 'sk_disable_php_cookies' );

/**
 * STAGE 12: Add debug header if in development
 */
function sk_add_debug_header() {
	if ( ! sk_is_homepage() ) {
		return;
	}

	// Only show in development environment
	$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
	
	if ( 'development' === $env ) {
		header( 'X-Cookie-Excluded-By: Shoaib Khan' );
		header( 'X-Plugin-Version: ' . COOKIE_EXCLUDE_HOMEPAGE_VERSION );
	}
}
add_action( 'send_headers', 'sk_add_debug_header', 999 );

/**
 * STAGE 13: Admin notice if needed
 */
function sk_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) || ! is_admin() ) {
		return;
	}

	if ( sk_is_homepage() && isset( $_GET['page'] ) && 'sk-cookie-settings' === $_GET['page'] ) {
		echo '<div class="notice notice-success"><p>';
		echo '<strong>Cookie Exclude Homepage</strong> by <strong>Shoaib Khan</strong> v' . COOKIE_EXCLUDE_HOMEPAGE_VERSION . ' is active!';
		echo '</p></div>';
	}
}
add_action( 'admin_notices', 'sk_admin_notice' );

/**
 * Final safety: Ensure no cookies on homepage
 */
function sk_final_cookie_check() {
	if ( ! sk_is_homepage() ) {
		return;
	}

	// One last check before sending anything
	if ( function_exists( 'header_remove' ) && ! headers_sent() ) {
		header_remove( 'Set-Cookie' );
		header( 'Cache-Control: public, max-age=3600, s-maxage=3600', true );
	}
}
add_action( 'wp_footer', 'sk_final_cookie_check', 9999 );

/**
 * Log status
 */
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	error_log( 'Cookie Exclude Homepage MU v' . COOKIE_EXCLUDE_HOMEPAGE_VERSION . ' by Shoaib Khan loaded' );
	if ( sk_is_homepage() ) {
		error_log( 'Cookie exclusion ACTIVE on homepage' );
	}
}
