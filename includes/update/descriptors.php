<?php
/**
 * Local plugin identities. No requests or option reads.
 *
 * @package ChurchTools_Suite
 */

return array(
	plugin_basename( CHURCHTOOLS_SUITE_PATH . 'churchtools-suite.php' ) => array(
		'slug'    => 'churchtools-suite',
		'version' => CHURCHTOOLS_SUITE_VERSION,
	),
	defined( 'CTS_ELEMENTOR_BASENAME' ) ? CTS_ELEMENTOR_BASENAME : 'churchtools-suite-elementor/churchtools-suite-elementor.php' => array(
		'slug'    => 'churchtools-suite-elementor',
		'version' => defined( 'CTS_ELEMENTOR_VERSION' ) ? CTS_ELEMENTOR_VERSION : '0',
	),
	defined( 'CTS_POSTS_SYNC_BASENAME' ) ? CTS_POSTS_SYNC_BASENAME : 'churchtools-suite-posts-sync/churchtools-suite-posts-sync.php' => array(
		'slug'    => 'churchtools-suite-posts-sync',
		'version' => defined( 'CTS_POSTS_SYNC_VERSION' ) ? CTS_POSTS_SYNC_VERSION : '0',
	),
	'churchtools-suite-presentations/churchtools-suite-presentations.php' => array(
		'slug'    => 'churchtools-suite-presentations',
		'version' => defined( 'CTS_PRESENTATIONS_VERSION' ) ? CTS_PRESENTATIONS_VERSION : '0',
	),
);
