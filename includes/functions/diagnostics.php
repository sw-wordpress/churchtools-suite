<?php
/**
 * Read-only, exportable diagnostics. Never includes credentials or event contents.
 *
 * @package ChurchTools_Suite
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collect local operational state without API calls or filesystem probes.
 *
 * @return array<string, mixed>
 */
function churchtools_suite_diagnostics(): array {
	require_once __DIR__ . '/connection-state.php';
	$connection = churchtools_suite_connection_state();
	$cron       = array();
	foreach ( array( 'churchtools_suite_auto_sync', 'churchtools_suite_session_keepalive' ) as $hook ) {
		$next          = wp_next_scheduled( $hook );
		$cron[ $hook ] = false === $next ? null : gmdate( 'c', $next );
	}
	$counts = array();
	foreach ( array( 'calendars', 'events', 'services' ) as $repository ) {
		/** @var ChurchTools_Suite_Repository_Base $instance */
		$instance              = churchtools_suite_get_repository( $repository );
		$counts[ $repository ] = $instance->count();
	}
	return array(
		'generated_at' => gmdate( 'c' ),
		'versions'     => array(
			'plugin'    => CHURCHTOOLS_SUITE_VERSION,
			'wordpress' => get_bloginfo( 'version' ),
			'php'       => PHP_VERSION,
			'db_schema' => ChurchTools_Suite_Migrations::get_current_version(),
		),
		'environment'  => array(
			'timezone'                => wp_timezone_string(),
			'memory_limit'            => ini_get( 'memory_limit' ),
			'openssl'                 => function_exists( 'openssl_encrypt' ),
			'curl'                    => function_exists( 'curl_init' ),
			'fileinfo'                => function_exists( 'finfo_open' ),
			'gd'                      => extension_loaded( 'gd' ),
			'imagick'                 => extension_loaded( 'imagick' ),
			'persistent_object_cache' => (bool) wp_using_ext_object_cache(),
		),
		'connection'   => array(
			'configured'      => $connection['configured'],
			'auth_method'     => $connection['auth_method'],
			'session_present' => $connection['connected'],
		),
		'cron'         => array(
			'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'auto_sync'        => (bool) get_option( 'churchtools_suite_auto_sync_enabled', 0 ),
			'next_runs'        => $cron,
		),
		'counts'       => $counts,
	);
}
