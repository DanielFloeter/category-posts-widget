<?php
/**
 * Bootstrap for running the plugin's tests against a plain WordPress install.
 *
 * tests/bootstrap.php expects the plugin to sit inside a wordpress-develop
 * checkout. This one takes the test suite from the cache that
 * tests/local/setup.sh fills, so the plugin can live in a normal install.
 */

global $wpdb, $current_site, $current_blog, $wp_rewrite, $shortcode_tags, $wp, $phpmailer;

$cpw_cache = getenv( 'CPW_TESTS_CACHE' );
if ( ! $cpw_cache ) {
	$cpw_cache = getenv( 'HOME' ) . '/.cache/category-posts-widget-tests';
}

$cpw_polyfills = getenv( 'CPW_POLYFILLS_PATH' );
if ( ! $cpw_polyfills ) {
	$cpw_polyfills = $cpw_cache . '/PHPUnit-Polyfills-1.1.0';
}

define( 'WP_TESTS_DIR', $cpw_cache . '/wp-tests/tests/phpunit/' );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $cpw_polyfills );
define( 'TEST_PLUGIN_FILE', dirname( __DIR__, 2 ) . '/cat-posts.php' );

if ( ! file_exists( WP_TESTS_DIR . 'includes/functions.php' ) ) {
	echo "The WordPress test suite is missing. Run tests/local/setup.sh first.\n";
	exit( 1 );
}

require_once WP_TESTS_DIR . 'includes/functions.php';

/**
 * Load the plugin manually, it is not activated in the test install.
 */
function _manually_load_plugin() {
	require TEST_PLUGIN_FILE;
}
tests_add_filter( 'plugins_loaded', '_manually_load_plugin' );

require WP_TESTS_DIR . 'includes/bootstrap.php';
