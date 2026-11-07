<?php
/**
 * Check the documented coverage requirement for all production PHP and JavaScript.
 * Run composer test:coverage and npm run test:ui:coverage first.
 */
declare( strict_types=1 );

$root = dirname( __DIR__ );
$php_report = $argv[1] ?? $root . '/coverage/php.xml';
$js_report  = $argv[2] ?? $root . '/coverage/ui.json';
if ( ! is_file( $php_report ) || ! is_file( $js_report ) ) {
	fwrite( STDERR, "Both coverage/php.xml and coverage/ui.json are required; missing measurements do not pass.\n" );
	exit( 1 );
}
$xml = simplexml_load_file( $php_report );
$js  = json_decode( (string) file_get_contents( $js_report ), true, 512, JSON_THROW_ON_ERROR );
if ( false === $xml || ! isset( $xml->project->metrics ) || ! is_array( $js ) ) {
	fwrite( STDERR, "Invalid coverage report.\n" );
	exit( 1 );
}
$metrics = $xml->project->metrics;
$reports = array(
	'PHP'        => array( (int) $metrics['coveredstatements'], (int) $metrics['statements'] ),
	'JavaScript' => array( 0, 0 ),
);
foreach ( $js as $file ) {
	foreach ( $file['s'] as $hits ) {
		++$reports['JavaScript'][1];
		if ( $hits > 0 ) {
			++$reports['JavaScript'][0];
		}
	}
}
$passed = true;
foreach ( $reports as $language => [ $covered, $total ] ) {
	$percentage = $total > 0 ? 100 * $covered / $total : 0;
	$ok = $total > 0 && $covered * 100 > $total * 80;
	printf( "%s: %.2f%% (%d/%d), required >80%%: %s\n", $language, $percentage, $covered, $total, $ok ? 'PASS' : 'FAIL' );
	$passed = $passed && $ok;
}
exit( $passed ? 0 : 1 );
