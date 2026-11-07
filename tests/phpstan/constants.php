<?php
/** Plugin constants supplied to PHPStan without booting WordPress or the plugin. */
declare( strict_types=1 );

$cts_analysis_root = dirname( __DIR__, 2 ) . '/';
$cts_analysis_plugins = array(
	'CHURCHTOOLS_SUITE' => array( '', 'churchtools-suite.php' ),
	'CTS_ELEMENTOR' => array( 'addons/churchtools-suite-elementor/', 'churchtools-suite-elementor.php' ),
	'CTS_POSTS_SYNC' => array( 'addons/churchtools-suite-posts-sync/', 'churchtools-suite-posts-sync.php' ),
	'CTS_PRESENTATIONS' => array( 'addons/churchtools-suite-presentations/', 'churchtools-suite-presentations.php' ),
);
foreach ( $cts_analysis_plugins as $cts_analysis_prefix => [ $cts_analysis_dir, $cts_analysis_entry ] ) {
	$cts_analysis_path = $cts_analysis_root . $cts_analysis_dir;
	preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', (string) file_get_contents( $cts_analysis_path . $cts_analysis_entry ), $cts_analysis_version );
	foreach ( array(
		'_PATH' => $cts_analysis_path,
		'_URL' => 'https://example.invalid/plugins/' . basename( rtrim( $cts_analysis_path, '/' ) ) . '/',
		'_BASENAME' => basename( rtrim( $cts_analysis_path, '/' ) ) . '/' . $cts_analysis_entry,
		'_VERSION' => $cts_analysis_version[1] ?? '',
	) as $cts_analysis_suffix => $cts_analysis_value ) {
		if ( ! defined( $cts_analysis_prefix . $cts_analysis_suffix ) ) {
			define( $cts_analysis_prefix . $cts_analysis_suffix, $cts_analysis_value );
		}
	}
}
define( 'CHURCHTOOLS_SUITE_DB_PREFIX', 'cts_' );
define( 'CTS_POSTS_SYNC_CPT', 'ct_post' );
