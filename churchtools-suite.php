<?php
/**
 * Plugin Name:       ChurchTools Integration Suite
 * Update URI: https://github.com/sw-wordpress/churchtools-suite
 * Plugin URI:        https://github.com/feg-aschaffenburg/churchtools-suite
 * Description:       Professionelle WordPress-Integration für externe Kalender-, Event- und Dienstsysteme. Synchronisiert Events, Termine und Dienste aus dem angebundenen System. Repository Factory für erweiterbare Architektur (Multi-User, Caching, Add-Ons).
 * Version: 1.4.0.7
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            FEG Aschaffenburg
 * Author URI:        https://github.com/feg-aschaffenburg
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       churchtools-suite
 * Domain Path:       /languages
 *
 * TRADEMARK NOTICE:
 * ChurchTools ist eine registrierte Marke der ChurchTools GmbH.
 * Dieses Projekt steht in keiner Verbindung zu oder Unterst�tzung durch die ChurchTools GmbH.
 * Dieses Plugin wird ohne Gewährleistung bereitgestellt (see LICENSE).
 *
 * @package ChurchTools_Suite
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Suppress WP 6.7 JIT translation notice IMMEDIATELY (v1.0.3.5).
remove_filter( 'load_textdomain_mofile', 'wp_check_load_textdomain_just_in_time' );

// Plugin constants.
define( 'CHURCHTOOLS_SUITE_VERSION', '1.4.0.7' );
define( 'CHURCHTOOLS_SUITE_PATH', plugin_dir_path( __FILE__ ) );
define( 'CHURCHTOOLS_SUITE_URL', plugin_dir_url( __FILE__ ) );
define( 'CHURCHTOOLS_SUITE_BASENAME', plugin_basename( __FILE__ ) );

// Database table prefix.
define( 'CHURCHTOOLS_SUITE_DB_PREFIX', 'cts_' );

// Load repository factory (v1.0.8.0).
require_once CHURCHTOOLS_SUITE_PATH . 'includes/functions/repository-factory.php';

/**
 * Plugin activation
 */
function churchtools_suite_activate(): void {
	require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-activator.php';
	ChurchTools_Suite_Activator::activate();
}
register_activation_hook( __FILE__, 'churchtools_suite_activate' );

/**
 * Plugin deactivation
 */
function churchtools_suite_deactivate(): void {
	require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-deactivator.php';
	ChurchTools_Suite_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'churchtools_suite_deactivate' );

/**
 * Initialize the plugin
 */
function churchtools_suite_run(): void {
	require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite.php';
	$plugin = new ChurchTools_Suite();

	// Store instance globally for sub-plugins (v1.0.9.0).
	global $churchtools_suite_plugin_instance;
	$churchtools_suite_plugin_instance = $plugin;

	$plugin->run();
}
churchtools_suite_run();

// Integrate Posts sync per default.
// require_once CHURCHTOOLS_SUITE_PATH . 'addons/churchtools-suite-posts-sync/churchtools-suite-posts-sync.php';
// require_once CHURCHTOOLS_SUITE_PATH . "addons/churchtools-suite-presentations/churchtools-suite-presentations.php";.

/** Register the external adapter lazily; ChurchTools works without CloudCalendar installed. */
add_action(
	'sw_calendar_register_local_sources',
	static function ( $registry ): void {
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/integrations/class-churchtools-suite-calendar-reader.php';
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/integrations/class-churchtools-suite-cloudcalendar-source.php';
		$registry->register( 'churchtools-suite', new ChurchTools_Suite_CloudCalendar_Source(), 1 );
	}
);
