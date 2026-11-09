<?php
/**
 * Render browser fixtures using only the disposable WordPress test installation.
 * Usage: php tests/wp/export-admin.php /tmp/<existing-output-directory>
 *
 * @package ChurchTools_Suite
 */

$output = $argv[1] ?? '';
if ( ! is_dir( $output ) || ! str_starts_with( realpath( $output ) . '/', sys_get_temp_dir() . '/' ) ) {
	fwrite( STDERR, "An existing temporary output directory is required.\n" );
	exit( 1 );
}
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';
wp_set_current_user( 1 );
set_current_screen( 'dashboard' );
ChurchTools_Suite_Roles::register_role();
update_option( 'churchtools_suite_advanced_mode', 1 );
update_option( 'churchtools_suite_calendars_last_sync', '2026-11-05 12:00:00' );
add_filter( 'pre_http_request', static fn () => new WP_Error( 'fixture_network_disabled', 'No external services in browser fixtures.' ) );
add_filter( 'admin_url', static fn ( $url ) => preg_replace( '~https?://[^/]+~', '', $url ) );

// Keep ordinary interaction fixtures independent of the deployed verification keys.
require_once CHURCHTOOLS_SUITE_PATH . 'includes/update/class-churchtools-suite-update-store.php';
add_filter( 'cts_verified_release_public_keys', static fn () => array( 'fixture' => base64_encode( str_repeat( 'a', 32 ) ) ) );
ChurchTools_Suite_Update_Store::write( array( 'last_attempt' => time() ) );

require_once CHURCHTOOLS_SUITE_PATH . 'addons/churchtools-suite-posts-sync/churchtools-suite-posts-sync.php';
require_once CTS_POSTS_SYNC_PATH . 'includes/class-churchtools-suite-posts-sync-service.php';
require_once CTS_POSTS_SYNC_PATH . 'includes/class-churchtools-suite-posts-sync-admin.php';
(new ChurchTools_Suite_Posts_Sync_Admin())->init();
ChurchTools_Suite_Admin_Screen::reset();
ChurchTools_Suite_Admin_Screen::register_defaults();

global $wpdb;
// Custom plugin tables survive the WP core reinstall; replace only this fixture's rows.
$wpdb->delete( $wpdb->prefix . 'cts_events', array( 'calendar_id' => 'review' ) );
$wpdb->delete( $wpdb->prefix . 'cts_calendars', array( 'calendar_id' => 'review' ) );
$wpdb->delete( $wpdb->prefix . 'cts_service_groups', array( 'service_group_id' => 'review-group' ) );
$wpdb->delete( $wpdb->prefix . 'cts_services', array( 'service_id' => 'review-service' ) );
$wpdb->insert( $wpdb->prefix . 'cts_calendars', array( 'calendar_id' => 'review', 'name' => 'Review Calendar', 'color' => '#4f46e5', 'is_selected' => 1 ) );
$wpdb->insert( $wpdb->prefix . 'cts_events', array( 'calendar_id' => 'review', 'appointment_id' => 'review-appointment', 'title' => 'Review Event with a longer title for a readable overview', 'event_description' => 'A useful event description that belongs beside its title and must not squeeze the other columns.', 'start_datetime' => gmdate( 'Y-m-d 12:00:00', strtotime( '+1 day' ) ), 'end_datetime' => gmdate( 'Y-m-d 13:00:00', strtotime( '+1 day' ) ), 'status' => 'active' ) );
$wpdb->insert( $wpdb->prefix . 'cts_service_groups', array( 'service_group_id' => 'review-group', 'name' => 'Review Group', 'is_selected' => 1 ) );
$wpdb->insert( $wpdb->prefix . 'cts_services', array( 'service_id' => 'review-service', 'service_group_id' => 'review-group', 'name' => 'Review Service', 'is_selected' => 1 ) );

$head = '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
foreach ( array( 'wp-admin/css/common.css', 'wp-admin/css/forms.css', 'wp-includes/css/dashicons.css' ) as $css ) {
	$head .= '<link rel="stylesheet" href="/wp-core/' . $css . '">';
}
foreach ( array( 'public', 'admin', 'admin-ui' ) as $name ) {
	$head .= '<link rel="stylesheet" href="/plugin/assets/css/churchtools-suite-' . $name . '.css">';
}
$head .= '<style>body{margin:0}#wpcontent{margin-left:160px;padding-left:20px;padding-top:20px} @media(max-width:782px){#wpcontent{margin-left:0;padding-left:10px;padding-top:10px}}</style>';
$head .= '<script>window.ajaxurl="/wp-admin/admin-ajax.php";</script>';
$head .= '</head><body class="wp-admin cts-admin-page"><div id="wpcontent"><div id="wpbody-content">';
$tail = '</div></div></body></html>';

function cts_browser_asset_url( $src ) {
	if ( str_starts_with( $src, CTS_POSTS_SYNC_URL ) ) {
		return '/plugin/addons/churchtools-suite-posts-sync/' . substr( $src, strlen( CTS_POSTS_SYNC_URL ) );
	}
	if ( str_starts_with( $src, CHURCHTOOLS_SUITE_URL ) ) {
		return '/plugin/' . substr( $src, strlen( CHURCHTOOLS_SUITE_URL ) );
	}
	$includes = strpos( $src, '/wp-includes/' );
	return false === $includes ? $src : '/wp-core' . substr( $src, $includes );
}
add_filter( 'script_loader_src', 'cts_browser_asset_url' );
add_filter( 'style_loader_src', 'cts_browser_asset_url' );

/** Export a view with WordPress's real header/footer script dependency resolution. */
function cts_export_browser_page( string $output, string $name, string $head, string $tail ): void {
	$GLOBALS['wp_scripts'] = new WP_Scripts();
	$GLOBALS['wp_styles'] = new WP_Styles();
	do_action( 'admin_enqueue_scripts' );
	ob_start();
	wp_scripts()->do_items( array( 'jquery' ), 0 );
	if ( wp_style_is( 'cts-posts-sync-admin', 'enqueued' ) ) {
		wp_styles()->do_items( array( 'cts-posts-sync-admin' ) );
	}
	$header_scripts = ob_get_clean();
	ob_start();
	ChurchTools_Suite_Admin_Screen::render_page();
	$html = ob_get_clean();
	ob_start();
	// Resolve the plugin's actual dependency trees; editor queues need a full WP editor shell.
	$roots = array( 'churchtools-suite-admin', 'churchtools-suite-admin-ui', 'cts-verified-updates' );
	if ( wp_script_is( 'cts-posts-sync-admin', 'enqueued' ) ) {
		$roots[] = 'cts-posts-sync-admin';
	}
	wp_scripts()->do_items( $roots, 1 );
	$footer_scripts = ob_get_clean();
	file_put_contents( $output . '/' . $name . '.html', str_replace( '</head>', $header_scripts . '</head>', $head ) . $html . str_replace( '</body>', $footer_scripts . '</body>', $tail ) );
}

foreach ( ChurchTools_Suite_Admin_Screen::screens() as $slug => $screen ) {
	if ( $screen['external'] ) {
		continue;
	}
	$parts = explode( '/', $slug, 2 );
	$_GET  = array( 'page' => 'churchtools-suite', 'tab' => $parts[0], 'subtab' => $parts[1] ?? '' );
	cts_export_browser_page( $output, str_replace( '/', '--', $slug ), $head, $tail );
}

// Also exercise the connected dashboard: it previously emitted duplicate sync controls.
update_option( 'churchtools_suite_ct_url', 'https://review.church.tools' );
update_option( 'churchtools_suite_ct_auth_method', 'token' );
update_option( 'churchtools_suite_ct_token', 'fixture-token' );
$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'dashboard' );
cts_export_browser_page( $output, 'dashboard--connected', $head, $tail );

// Existing account: render a blank password input with a saved-secret hint, never its value.
update_option( 'churchtools_suite_ct_auth_method', 'password' );
update_option( 'churchtools_suite_ct_username', 'review@example.org' );
update_option( 'churchtools_suite_ct_password', 'private-fixture-password' );
$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'settings', 'subtab' => 'api' );
cts_export_browser_page( $output, 'settings--saved-api', $head, $tail );

// Missing addon must explain how to enable settings instead of rendering an empty form.
remove_all_actions( 'cts_posts_settings_render' );
$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'settings', 'subtab' => 'posts' );
cts_export_browser_page( $output, 'settings--posts-inactive', $head, $tail );

// A configured public key enables the manual-check control without contacting GitHub.
$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'settings', 'subtab' => 'advanced' );
cts_export_browser_page( $output, 'settings--verified-updates', $head, $tail );

// Exercise overdue background checks separately from user-triggered AJAX actions.
ChurchTools_Suite_Update_Store::write( array( 'last_attempt' => 0 ) );
cts_export_browser_page( $output, 'settings--verified-updates-due', $head, $tail );
