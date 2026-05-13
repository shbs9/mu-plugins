<?php
/**
 * Plugin Name: Cookie Exclude Homepage MU
 * Plugin URI: https://example.com
 * Description: Excludes all cookies from being set on the home page for better privacy and performance
 * Version: 1.0.0
 * Author: Shoaib khan
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * MU Plugin - Place in /wp-content/mu-plugins/ folder
 *
 * @package CookieExcludeHomepageMU
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if current request is for the home page
 *
 * @return bool
 */
function is_homepage_cookie_exclude() {
	return is_home() || is_front_page();
}

/**
 * Disable cookies on homepage - early init hook
 */
function disable_cookies_on_homepage() {
	if ( ! is_homepage_cookie_exclude() ) {
		return;
	}

	// Define constant to track that we're excluding cookies
	if ( ! defined( 'COOKIE_EXCLUDE_HOMEPAGE_ACTIVE' ) ) {
		define( 'COOKIE_EXCLUDE_HOMEPAGE_ACTIVE', true );
	}

	// Disable cookie domain and path
	if ( ! defined( 'COOKIEPATH' ) ) {
		define( 'COOKIEPATH', '' );
	}
	if ( ! defined( 'COOKIE_DOMAIN' ) ) {
		define( 'COOKIE_DOMAIN', false );
	}
}
add_action( 'init', 'disable_cookies_on_homepage', 1 );

/**
 * Remove Set-Cookie headers from homepage response
 */
function remove_set_cookie_headers_homepage() {
	if ( ! is_homepage_cookie_exclude() ) {
		return;
	}

	// Remove all Set-Cookie headers
	if ( function_exists( 'header_remove' ) ) {
		header_remove( 'Set-Cookie' );
	}
}
add_action( 'send_headers', 'remove_set_cookie_headers_homepage', 1 );

/**
 * Filter HTTP response to remove cookies from homepage
 *
 * @param WP_HTTP_Response $response Response object.
 * @param WP_REST_Request  $request Request object.
 * @return WP_HTTP_Response
 */
function filter_cookies_from_homepage_response( $response, $request ) {
	if ( ! is_homepage_cookie_exclude() ) {
		return $response;
	}

	// Get headers from response
	$headers = $response->get_headers();
	
	// Remove Set-Cookie headers
	if ( isset( $headers['Set-Cookie'] ) ) {
		unset( $headers['Set-Cookie'] );
		$response->set_headers( $headers );
	}

	return $response;
}
add_filter( 'wp_http_response', 'filter_cookies_from_homepage_response', 10, 2 );

/**
 * Disable comment cookies on homepage
 *
 * @param int $lifetime Cookie lifetime.
 * @return int
 */
function disable_comment_cookies_on_homepage( $lifetime ) {
	if ( is_homepage_cookie_exclude() ) {
		return 0;
	}
	return $lifetime;
}
add_filter( 'comment_cookie_lifetime', 'disable_comment_cookies_on_homepage' );

/**
 * Prevent WordPress from setting user cookies on homepage
 */
function prevent_wp_cookies_on_homepage() {
	if ( ! is_homepage_cookie_exclude() ) {
		return;
	}

	// Remove WordPress default cookie setting
	remove_action( 'wp_footer', 'wp_shortlink_wp_head' );
	
	// Prevent login/auth cookies
	if ( function_exists( 'wp_set_auth_cookie' ) ) {
		remove_action( 'wp_set_auth_cookie', 'wp_set_auth_cookie' );
	}
}
add_action( 'wp_loaded', 'prevent_wp_cookies_on_homepage' );

/**
 * Disable WP REST API cookies on homepage
 */
function disable_rest_cookies_on_homepage() {
	if ( ! is_homepage_cookie_exclude() ) {
		return;
	}

	// Prevent REST from setting cookies
	remove_action( 'rest_api_init', 'rest_api_default_filters' );
}
add_action( 'rest_api_init', 'disable_rest_cookies_on_homepage', 0 );

/**
 * Remove Vary: Cookie header to improve caching
 */
function remove_vary_cookie_header() {
	if ( ! is_homepage_cookie_exclude() ) {
		return;
	}

	if ( function_exists( 'header_remove' ) ) {
		// Remove the Vary header that includes Cookie
		header_remove( 'Vary' );
		// Set a new Vary header without Cookie for better caching
		header( 'Vary: Accept-Encoding' );
	}
}
add_action( 'send_headers', 'remove_vary_cookie_header', 2 );

/**
 * Output debug notice in admin if needed
 */
function cookie_exclude_homepage_mu_debug() {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['cookie_debug'] ) ) {
		return;
	}

	if ( is_homepage_cookie_exclude() ) {
		add_action( 'wp_footer', function() {
			echo '<!-- Cookie Exclude Homepage MU Plugin: Active on this page -->';
		} );
	}
}
add_action( 'wp_loaded', 'cookie_exclude_homepage_mu_debug' );
