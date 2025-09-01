<?php
/**
 * PHPUnit Bootstrap File for AI Page Composer Tests
 *
 * @package AIPageComposer
 */

// Define testing constants
define( 'AI_PAGE_COMPOSER_TESTING', true );
define( 'WP_TESTS_CONFIG_FILE_PATH', dirname( __DIR__ ) . '/wp-tests-config.php' );

// Include WordPress test suite
if ( ! file_exists( WP_TESTS_CONFIG_FILE_PATH ) ) {
    echo "wp-tests-config.php not found. Please create it based on wp-tests-config-sample.php\n";
    exit( 1 );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// WordPress test environment
$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';
require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the plugin
 */
function _manually_load_plugin() {
    require dirname( __DIR__ ) . '/ai-page-composer.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

// Include test utilities
require_once __DIR__ . '/utilities/class-test-blueprint-factory.php';