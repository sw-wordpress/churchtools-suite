<?php
/**
 * Tests for the page shell (rail, top bar, view resolution) and the admin menu.
 *
 * @package ChurchTools_Suite
 */

final class AdminPageShellTest extends CTS_TestCase {

	public function test_shell_renders_rail_topbar_and_current_screen(): void {
		$_GET = array( 'tab' => 'dashboard' );
		$html = $this->normalize( $this->render_admin( 'dashboard' ) );

		$this->assertStringContainsString( '<div class="wrap cts-wrap">', $html );
		$this->assertStringContainsString( '<aside class="cts-rail"', $html );
		$this->assertStringContainsString( '<header class="cts-topbar">', $html );
		$this->assertStringContainsString( 'cts-page-title', $html );
		// the dashboard view itself
		$this->assertStringContainsString( 'cts-dashboard', $html );
	}

	public function test_rail_lists_groups_and_hides_advanced_entries_by_default(): void {
		$html = $this->normalize( $this->render_admin( 'dashboard' ) );
		$this->assertStringContainsString( 'cts-rail__group-title', $html );
		$this->assertStringContainsString( 'Einstellungen', $html );
		$this->assertStringContainsString( 'Synchronisation', $html );
		$this->assertStringNotContainsString( 'Reset &amp; Aufräumen', $html );

		update_option( 'churchtools_suite_advanced_mode', 1 );
		$html = $this->normalize( $this->render_admin( 'dashboard' ) );
		$this->assertStringContainsString( 'Reset &amp; Aufräumen', $html );
	}

	public function test_topbar_shows_connection_state_and_sync_action(): void {
		delete_option( 'churchtools_suite_ct_url' );
		$html = $this->normalize( $this->render_admin( 'dashboard' ) );
		$this->assertStringContainsString( 'Nicht konfiguriert', $html );
		$this->assertStringContainsString( 'data-cts-action="sync-now"', $html );
		$this->assertStringContainsString( 'id="cts-sync-now"', $html );

		update_option( 'churchtools_suite_ct_url', 'https://example.church.tools' );
		update_option( 'churchtools_suite_ct_auth_method', 'token' );
		update_option( 'churchtools_suite_ct_token', 't0ken' );
		$html = $this->normalize( $this->render_admin( 'dashboard' ) );
		$this->assertStringContainsString( 'Verbunden', $html );
		$this->assertStringNotContainsString( 't0ken', $html );
	}

	public function test_wrapper_screen_renders_one_child_and_nav_entries(): void {
		$html = $this->normalize( $this->render_admin( 'settings', 'templates' ) );

		// The rail is the only navigation of the new shell.
		$this->assertSame( 0, substr_count( $html, 'cts-sub-tabs' ), 'the legacy sub tab bar must be gone' );
		$this->assertStringContainsString( 'cts-nav-sub--active', $html );

		// exactly the requested child view
		$this->assertStringContainsString( 'single_template', $html );
		$this->assertStringNotContainsString( 'cts_save_api', $html );
	}

	public function test_advanced_wrapper_renders_requested_subtab(): void {
		update_option( 'churchtools_suite_advanced_mode', 1 );
		$html = $this->normalize( $this->render_admin( 'advanced', 'logs' ) );
		$this->assertSame( 0, substr_count( $html, 'cts-sub-tabs' ) );
		$this->assertStringContainsString( 'id="cts-log-content"', $html );
		$this->assertStringNotContainsString( 'id="cts-full-reset"', $html );

		$html = $this->normalize( $this->render_admin( 'advanced', 'reset-cleanup' ) );
		$this->assertStringContainsString( 'id="cts-full-reset"', $html );
	}

	public function test_data_page_renders_requested_child(): void {
		$html = $this->normalize( $this->render_admin( 'data', 'imported-services' ) );
		$this->assertStringContainsString( 'cts-imported-services', $html );
		$this->assertStringNotContainsString( 'cts-events-overview', $html );

		$html = $this->normalize( $this->render_admin( 'data', 'events' ) );
		$this->assertStringContainsString( 'cts-events-overview', $html );
		$this->assertStringNotContainsString( 'cts-imported-services', $html );
	}

	public function test_posts_overview_falls_back_when_addon_inactive(): void {
		$html = $this->normalize( $this->render_admin( 'data', 'posts' ) );
		$this->assertStringContainsString( 'Posts Sync ist nicht aktiv', $html );
		$this->assertStringContainsString( 'Zu den Addons', $html );
	}

	public function test_missing_view_renders_notice_instead_of_fatal(): void {
		ChurchTools_Suite_Admin_Screen::add_screen(
			'settings/broken',
			array(
				'title'  => 'Broken',
				'parent' => 'settings',
				'group'  => 'main',
				'view'   => 'settings/definitely-not-here.php',
			)
		);
		$html = $this->normalize( $this->render_admin( 'settings', 'broken' ) );
		$this->assertStringContainsString( 'Die Ansichtsdatei fehlt.', $html );
	}

	public function test_menu_is_built_from_the_registry(): void {
		$entries = $this->menu_entries();
		$this->assertNotEmpty( $entries, 'top level menu missing' );

		$slugs = wp_list_pluck( $entries, 2 );
		$this->assertContains( 'churchtools-suite', $slugs );
		$this->assertContains( 'churchtools-suite-docs', $slugs );

		// No emoji in menu labels (the old flyout labels used them).
		foreach ( $entries as $item ) {
			$this->assertSame( $item[0], preg_replace( '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $item[0] ), 'emoji in menu label: ' . $item[0] );
		}
	}

	public function test_submenu_entries_hide_advanced_entries(): void {
		$labels = wp_list_pluck( $this->menu_entries(), 0 );
		$this->assertNotContains( 'Erweiterte Diagnose', $labels );

		update_option( 'churchtools_suite_advanced_mode', 1 );
		$this->build_admin_menu();
		$labels = wp_list_pluck( $this->menu_entries(), 0 );
		$this->assertContains( 'Erweiterte Diagnose', $labels );
	}

	/**
	 * Submenu entries of the plugin menu.
	 *
	 * @return array<int, array<int, string>>
	 */
	private function menu_entries(): array {
		global $submenu;
		return isset( $submenu['churchtools-suite'] ) ? $submenu['churchtools-suite'] : array();
	}

	public function test_is_plugin_screen(): void {
		$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'settings' );
		$this->assertTrue( ChurchTools_Suite_Admin_Screen::is_plugin_screen() );
		$_GET = array( 'page' => 'some-other-plugin' );
		$this->assertFalse( ChurchTools_Suite_Admin_Screen::is_plugin_screen() );
	}

	public function test_assets_are_enqueued_only_for_plugin_screens(): void {
		$_GET = array( 'page' => 'other-plugin' );
		$admin = new ChurchTools_Suite_Admin( CHURCHTOOLS_SUITE_VERSION );
		$admin->enqueue_styles();
		$this->assertFalse( wp_style_is( 'churchtools-suite-admin-ui', 'enqueued' ) );

		$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'dashboard' );
		$admin->enqueue_styles();
		$this->assertTrue( wp_style_is( 'churchtools-suite-admin-ui', 'enqueued' ) );
	}

	public function test_admin_assets_are_registered_with_the_plugin_version(): void {
		// Start from a clean asset registry: other tests may already have enqueued the handles.
		wp_scripts()->registered = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test isolation.
		wp_scripts()->queue      = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test isolation.
		wp_scripts()->done       = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test isolation.
		wp_styles()->registered  = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test isolation.
		wp_styles()->queue       = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test isolation.
		wp_styles()->done        = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test isolation.

		$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'dashboard' );
		$admin = new ChurchTools_Suite_Admin( CHURCHTOOLS_SUITE_VERSION );
		$admin->enqueue_styles();

		foreach ( array( 'churchtools-suite-public', 'churchtools-suite-admin', 'churchtools-suite-admin-ui' ) as $handle ) {
			$this->assertTrue( wp_style_is( $handle, 'enqueued' ), $handle . ' not enqueued' );
		}
		foreach ( array( 'churchtools-suite-admin', 'churchtools-suite-admin-ui' ) as $handle ) {
			$this->assertTrue( wp_script_is( $handle, 'enqueued' ), $handle . ' not enqueued' );
		}
		$this->assertStringContainsString(
			'assets/css/churchtools-suite-admin-ui.css',
			(string) wp_styles()->query( 'churchtools-suite-admin-ui' )->src
		);
		// cache busting uses the plugin version, not a timestamp
		$this->assertSame( CHURCHTOOLS_SUITE_VERSION, wp_styles()->query( 'churchtools-suite-admin-ui' )->ver );
		$this->assertSame( CHURCHTOOLS_SUITE_VERSION, wp_scripts()->query( 'churchtools-suite-admin-ui' )->ver );

		// the design system loads after the legacy stylesheet so it can override it
		$this->assertContains( 'churchtools-suite-admin', (array) wp_styles()->query( 'churchtools-suite-admin-ui' )->deps );
	}

	public function test_legacy_admin_page_view_is_gone(): void {
		$this->assertFileDoesNotExist( CHURCHTOOLS_SUITE_PATH . 'admin/views/admin-page.php' );
		$this->assertFileDoesNotExist( CHURCHTOOLS_SUITE_PATH . 'admin/views/tab-settings.php' );
		$this->assertFileDoesNotExist( CHURCHTOOLS_SUITE_PATH . 'admin/views/tab-data.php' );
	}

	public function test_body_class_added_on_plugin_screens(): void {
		$admin = new ChurchTools_Suite_Admin( CHURCHTOOLS_SUITE_VERSION );
		$this->assertStringContainsString( 'cts-admin-page', $admin->admin_body_class( 'existing-class' ) );
		$this->assertStringContainsString( 'cts-admin-page', $admin->admin_body_class( '' ) );
	}
	public function test_admin_script_is_actually_printed_without_frontend_script_registration(): void {
		$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'settings' );
		$this->assertFalse( wp_script_is( 'churchtools-suite-public', 'registered' ) );
		(new ChurchTools_Suite_Admin( CHURCHTOOLS_SUITE_VERSION ))->enqueue_styles();
		ob_start();
		wp_scripts()->do_items( array( 'churchtools-suite-admin', 'churchtools-suite-admin-ui' ), 1 );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'assets/js/churchtools-suite-admin.js', $html );
		$this->assertStringContainsString( 'window.churchtoolsSuite =', $html );
		$this->assertStringContainsString( 'assets/js/churchtools-suite-admin-ui.js', $html );
	}

}
