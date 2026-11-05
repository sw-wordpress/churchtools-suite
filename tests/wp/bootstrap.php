<?php
/**
 * Bootstrap for the WordPress integration tests of ChurchTools Suite.
 *
 */

$tests_dir = __DIR__ . '/vendor/wp-phpunit/wp-phpunit';

// The config path must be visible to the install subprocess *and* to this process.
putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
putenv( 'WP_TESTS_CONFIG_FILE_PATH=' . __DIR__ . '/wp-tests-config.php' );
define( 'WP_PHPUNIT__TESTS_CONFIG', __DIR__ . '/wp-tests-config.php' );
define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );

require_once $tests_dir . '/includes/functions.php';

// Boot the plugin once. WP_UnitTestCase snapshots and restores these hooks between tests.
tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__, 2 ) . '/churchtools-suite.php';
	}
);

require $tests_dir . '/includes/bootstrap.php';
require_once __DIR__ . '/tests/CTS_TestCase.php';
