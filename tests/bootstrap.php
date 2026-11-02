<?php
/** Shared, disposable WordPress harness; no external ChurchTools service is contacted. */
$cloudcalendar = getenv( 'SW_CLOUDCALENDAR_DIR' ) ?: dirname( __DIR__, 2 ) . '/sw-cloudcalendar';
require_once $cloudcalendar . '/tests/wp/vendor/wp-phpunit/wp-phpunit/includes/functions.php';
tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__ ) . '/churchtools-suite.php';
	}
);
require $cloudcalendar . '/tests/wp/bootstrap.php';
