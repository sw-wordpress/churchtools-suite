<?php
/**
 * Shared base for the WordPress integration tests.
 *
 * Every test runs inside a transaction so plugin tables and options are rolled back afterwards.
 * Only the private MariaDB of `bin/test-db.sh` is used.
 *
 * @package ChurchTools_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CHURCHTOOLS_SUITE_PATH . 'admin/ui/class-churchtools-suite-admin-screen.php';
require_once CHURCHTOOLS_SUITE_PATH . 'includes/functions/connection-state.php';
require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-roles.php';

/**
 * Base test case.
 */
abstract class CTS_TestCase extends WP_UnitTestCase {

	/** Administrator of the test blog. @var int */
	protected int $admin_id = 0;

	/**
	 * Reset per-test state.
	 *
	 * The registry is static and the plugin registers it once on `init`; tests add and remove
	 * screens, so every test starts from the defaults. The plugin hooks live in the real hook
	 * globals and are not re-registered per test, which is exactly how the production request
	 * behaves - therefore the plugin is never re-instantiated here.
	 */
	public function set_up(): void {
		ChurchTools_Suite_Admin_Screen::reset();
		ChurchTools_Suite_Admin_Screen::register_defaults();

		parent::set_up();
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();
		add_filter( 'pre_http_request', static function () {
			return new WP_Error( 'test_network_disabled', 'Tests must provide an explicit HTTP fixture.' );
		} );

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );

		// The plugin tables are created by the migrations during plugin init; the custom
		// capabilities are granted to administrators on activation, which is not part of a test run.
		ChurchTools_Suite_Migrations::run_migrations();
		ChurchTools_Suite_Roles::register_role();

		// The admin menu is not part of the wp-phpunit hook snapshot, and the menu globals ($menu,
		// $submenu) must exist like in a real admin request. Rebuild them per test - after the user
		// is known, because add_menu_page()/add_submenu_page() record them per capability.
		delete_option( 'churchtools_suite_advanced_mode' );
		$this->build_admin_menu();
		$_GET  = array();
		$_POST = array();
		$_REQUEST = array();
	}

	/**
	 * Tear down: undo option changes made by a test.
	 */
	public function tear_down(): void {
		$_GET  = array();
		$_POST = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * Rebuild the wp-admin menu of the plugin from scratch.
	 *
	 * @since 1.4.0.0
	 */
	protected function build_admin_menu(): void {
		global $menu, $submenu;
		$menu    = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- fresh menu per test.
		$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- fresh menu per test.
		unset( $GLOBALS['admin_page_hooks'], $GLOBALS['adminmenu'] );

		$_GET['page'] = ChurchTools_Suite_Admin_Screen::TOP_LEVEL;
		do_action( 'admin_menu' );
		unset( $_GET['page'] );

	}

	/**
	 * Render a string of PHP (a view) and return its output.
	 *
	 * @param string $file Absolute PHP file path.
	 *
	 * @return string
	 */
	protected function render_file( string $file ): string {
		ob_start();
		include $file;
		return (string) ob_get_clean();
	}

	/**
	 * Render the admin page shell for a given ?page/?tab/?subtab combination.
	 *
	 * @param string $section Section slug (?tab).
	 * @param string $sub     Sub slug (?subtab).
	 *
	 * @return string
	 */
	protected function render_admin( string $section, string $sub = '' ): string {
		$_GET = array( 'page' => ChurchTools_Suite_Admin_Screen::TOP_LEVEL, 'tab' => $section );
		if ( '' !== $sub ) {
			$_GET['subtab'] = $sub;
		}

		$_REQUEST = array_merge( $_GET, $_POST );

		// Same isolation as the shell itself: a view must never leak an output buffer, because the
		// caller would lose its own buffer (and the tests its captured markup).
		$base = ob_get_level();
		ob_start();
		try {
			ChurchTools_Suite_Admin_Screen::render_page();
			while ( ob_get_level() > $base + 1 ) {
				ob_end_clean();
			}
			return (string) ob_get_clean();
		} finally {
			while ( ob_get_level() > $base ) {
				ob_end_clean();
			}
		}
	}

	/**
	 * Collapse consecutive whitespace so markup assertions do not depend on indentation.
	 *
	 * @param string $html Markup.
	 *
	 * @return string
	 */
	protected function normalize( string $html ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', $html ) );
	}
}
