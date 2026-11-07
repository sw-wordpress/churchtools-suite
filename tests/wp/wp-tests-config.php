<?php
/**
 * WordPress test suite configuration. Uses ONLY the private throw-away MariaDB instance
 * (bin/test-db.sh), never the development site's database.
 */

define( 'ABSPATH', __DIR__ . '/vendor/johnpbloch/wordpress-core/' );
define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_DEBUG', true );

define( 'DB_NAME', getenv( 'CTS_WP_TEST_DB_NAME' ) ?: 'cts_wp_tests' );
define( 'DB_USER', getenv( 'CTS_TEST_DB_USER' ) ?: 'cts' );
define( 'DB_PASSWORD', getenv( 'CTS_TEST_DB_PASSWORD' ) ?: 'cts' );
define( 'DB_HOST', ( getenv( 'CTS_TEST_DB_HOST' ) ?: '127.0.0.1' ) . ':' . ( getenv( 'CTS_TEST_DB_PORT' ) ?: '33080' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'ChurchTools Suite Tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );

// Salts. wp-phpunit does not define them, but the plugin encrypts secrets with them.
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $cts_test_salt ) {
	if ( ! defined( $cts_test_salt ) ) {
		define( $cts_test_salt, 'cts-test-' . strtolower( $cts_test_salt ) );
	}
}

$table_prefix = 'wptests_';
