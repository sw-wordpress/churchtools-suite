<?php
/**
 * Behavioural regressions found during the admin overhaul review.
 *
 * @package ChurchTools_Suite
 */

final class AdminRegressionTest extends CTS_TestCase {

	public function test_diagnostics_landing_and_legacy_bookmarks_render_without_recursion(): void {
		update_option( 'churchtools_suite_advanced_mode', 1 );
		foreach ( array( '', 'uebersicht', 'unknown' ) as $sub ) {
			$level = ob_get_level();
			$html  = $this->render_admin( 'advanced', $sub );
			$this->assertSame( $level, ob_get_level() );
			$this->assertStringContainsString( 'Health-Übersicht', $html );
			$this->assertSame( 1, substr_count( $html, '<aside class="cts-rail"' ) );
		}
		$html = $this->render_admin( 'debug', 'logs' );
		$this->assertStringContainsString( 'id="cts-log-content"', $html );
	}

	public function test_disabled_diagnostics_falls_back_without_rendering_maintenance_controls(): void {
		$html = $this->render_admin( 'advanced', 'reset-cleanup' );
		$this->assertStringContainsString( 'cts-dashboard', $html );
		$this->assertStringNotContainsString( 'id="cts-full-reset"', $html );
	}

	public function test_unknown_data_subsection_falls_back_to_events(): void {
		$html = $this->render_admin( 'data', 'old-bookmark' );
		$this->assertStringContainsString( 'cts-events-overview', $html );
		$this->assertStringNotContainsString( 'Für diese Ansicht ist keine Datei hinterlegt', $html );
	}

	public function test_admin_submenu_targets_are_unique_and_registered(): void {
		update_option( 'churchtools_suite_advanced_mode', 1 );
		$this->build_admin_menu();
		$entries = $GLOBALS['submenu']['churchtools-suite'];
		$slugs   = wp_list_pluck( $entries, 2 );
		$this->assertSame( count( $slugs ), count( array_unique( $slugs ) ) );
		foreach ( array( 'settings', 'advanced' ) as $section ) {
			$slug = 'churchtools-suite-' . $section;
			$this->assertContains( $slug, $slugs );
			$this->assertNotFalse( has_action( get_plugin_page_hookname( $slug, 'churchtools-suite' ) ) );
			$_GET = array( 'page' => $slug );
			$this->assertSame( $section, ChurchTools_Suite_Admin_Screen::current()['section'] );
		}
	}

	public function test_documentation_redirect_is_registered_before_the_admin_header(): void {
		$hook = get_plugin_page_hookname( 'churchtools-suite-docs', 'churchtools-suite' );
		$this->assertNotFalse( has_action( 'load-' . $hook ) );
	}

	public function test_legacy_pages_have_real_wordpress_callbacks(): void {
		foreach ( array( 'data', 'overviews', 'addons', 'disclaimer' ) as $suffix ) {
			$slug = 'churchtools-suite-' . $suffix;
			$this->assertNotFalse( has_action( get_plugin_page_hookname( $slug, '' ) ), $slug );
		}
	}

	public function test_repeated_asset_enqueue_keeps_inline_data_as_an_array_and_preserves_other_scripts(): void {
		$_GET = array( 'page' => 'churchtools-suite' );
		$admin = new ChurchTools_Suite_Admin( CHURCHTOOLS_SUITE_VERSION );
		$admin->enqueue_styles();
		wp_add_inline_script( 'churchtools-suite-admin', 'window.ctsOther = true;', 'before' );
		$admin->enqueue_styles();
		$before = wp_scripts()->get_data( 'churchtools-suite-admin', 'before' );
		$this->assertIsArray( $before );
		$this->assertContains( 'window.ctsOther = true;', $before );
		$this->assertSame( 1, substr_count( implode( "\n", $before ), 'window.churchtoolsSuite =' ) );
	}

	public function test_rendering_addons_twice_preserves_the_caller_buffer(): void {
		$level = ob_get_level();
		foreach ( array( 1, 2 ) as $iteration ) {
			$html = $this->render_admin( 'addons' );
			$this->assertStringContainsString( 'Elementor', $html );
			$this->assertSame( $level, ob_get_level() );
		}
	}

	public function test_a_user_without_the_plugin_capability_cannot_render_the_backend(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->expectException( WPDieException::class );
		(new ChurchTools_Suite_Admin( CHURCHTOOLS_SUITE_VERSION ))->display_admin_page();
	}

	public function test_stale_credentials_without_a_url_do_not_report_connected(): void {
		delete_option( 'churchtools_suite_ct_url' );
		update_option( 'churchtools_suite_ct_cookies', array( 'session' => 'stale' ) );
		$this->assertFalse( churchtools_suite_connection_state()['connected'] );
		update_option( 'churchtools_suite_ct_auth_method', 'token' );
		update_option( 'churchtools_suite_ct_token', 'stale' );
		$this->assertFalse( churchtools_suite_connection_state()['connected'] );
		$this->assertStringNotContainsString( 'class="cts-status cts-status-success"', $this->render_admin( 'dashboard' ) );
	}

	public function test_active_posts_addon_renders_real_posts_in_one_shell_and_loads_its_assets(): void {
		$addon = $this->load_posts_addon();
		update_option( 'churchtools_suite_ct_posts_target_type', 'post' );
		$post_id = self::factory()->post->create( array( 'post_title' => 'Imported review post', 'post_status' => 'publish' ) );
		update_post_meta( $post_id, ChurchTools_Suite_Posts_Sync_Service::META_CT_POST_ID, 'review-123' );

		$this->assertTrue( ChurchTools_Suite_Admin_Screen::is_visible( ChurchTools_Suite_Admin_Screen::screens()['data/posts'] ) );
		$html = $this->render_admin( 'data', 'posts' );
		$this->assertStringContainsString( 'Imported review post', $html );
		$this->assertStringNotContainsString( 'Posts Sync ist nicht aktiv', $html );
		$this->assertSame( 1, substr_count( $html, '<div class="wrap cts-wrap">' ) );
		$this->assertSame( 1, preg_match_all( '/<h1\b/', $html ) );

		$addon->enqueue_admin_assets();
		$this->assertTrue( wp_script_is( 'cts-posts-sync-admin', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'cts-posts-sync-admin', 'enqueued' ) );

		$_GET = array( 'page' => 'churchtools-suite-posts-overview' );
		ob_start();
		$addon->render_overview_page();
		$legacy = ob_get_clean();
		$this->assertStringContainsString( 'Imported review post', $legacy );
		$this->assertSame( 1, substr_count( $legacy, '<aside class="cts-rail"' ) );
	}

	public function test_posts_settings_explain_the_missing_addon_without_a_save_form(): void {
		remove_all_actions( 'cts_posts_settings_render' );
		$html = $this->render_admin( 'settings', 'posts' );
		$this->assertStringContainsString( 'Das Posts-Sync-Addon ist nicht aktiv', $html );
		$this->assertStringContainsString( 'plugins.php', $html );
		$this->assertStringNotContainsString( 'name="cts_save_posts"', $html );
		$this->assertStringNotContainsString( 'Berichte', $html );
	}

	public function test_posts_settings_with_addon_render_controls_and_save(): void {
		$this->load_posts_addon();
		add_filter( 'cts_posts_sync_is_allowed_environment', '__return_true' );
		$html = $this->render_admin( 'settings', 'posts' );
		$this->assertStringContainsString( 'name="ct_posts_sync_enabled"', $html );
		$this->assertStringContainsString( 'Posts-Einstellungen speichern', $html );
		$this->assertStringNotContainsString( 'Berichte', $html );
		set_current_screen( 'dashboard' );
		$_POST = array( '_wpnonce' => wp_create_nonce( 'cts_settings' ), 'cts_save_posts' => '1', 'ct_posts_sync_enabled' => '1', 'ct_posts_target_type' => 'post', 'ct_posts_sync_limit' => '25' );
		$this->render_admin( 'settings', 'posts' );
		set_current_screen( 'front' );
		$this->assertSame( 1, get_option( 'churchtools_suite_ct_posts_sync_enabled' ) );
		$this->assertSame( 'post', get_option( 'churchtools_suite_ct_posts_target_type' ) );
		$this->assertSame( 25, get_option( 'churchtools_suite_ct_posts_sync_limit' ) );
	}

	public function test_calendar_settings_do_not_import_bundled_images(): void {
		$before = get_posts( array( 'post_type' => 'attachment', 'numberposts' => -1, 'fields' => 'ids' ) );
		$html = $this->render_admin( 'settings', 'calendars' );
		$this->assertStringNotContainsString( 'Vorlagenbilder', $html );
		$this->assertStringNotContainsString( 'cts-calendar-template', $html );
		$this->assertSame( $before, get_posts( array( 'post_type' => 'attachment', 'numberposts' => -1, 'fields' => 'ids' ) ) );
		$this->assertSame( array(), glob( CHURCHTOOLS_SUITE_PATH . 'assets/images/calendar-templates/*' ) );
	}

	public function test_diagnostics_export_is_local_and_does_not_expose_private_options(): void {
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/functions/diagnostics.php';
		update_option( 'churchtools_suite_ct_url', 'https://private-tenant.church.tools' );
		update_option( 'churchtools_suite_ct_username', 'private-person@example.org' );
		update_option( 'churchtools_suite_ct_password', 'private-password' );
		update_option( 'churchtools_suite_ct_token', 'private-token' );
		update_option( 'churchtools_suite_ct_cookies', array( 'session' => 'private-cookie' ) );
		update_option( 'churchtools_suite_auto_sync_enabled', 1 );
		wp_clear_scheduled_hook( 'churchtools_suite_auto_sync' );
		$requests = 0;
		add_filter( 'pre_http_request', static function ( $response ) use ( &$requests ) { ++$requests; return $response; } );
		$report = churchtools_suite_diagnostics();
		$this->assertSame( 0, $requests );
		$this->assertTrue( $report['connection']['configured'] );
		$this->assertNull( $report['cron']['next_runs']['churchtools_suite_auto_sync'] );
		$this->assertSame( CHURCHTOOLS_SUITE_VERSION, $report['versions']['plugin'] );
		$this->assertIsBool( $report['environment']['persistent_object_cache'] );
		foreach ( array( 'private-tenant', 'private-person', 'private-password', 'private-token', 'private-cookie' ) as $private ) {
			$this->assertStringNotContainsString( $private, wp_json_encode( $report ) );
		}
		update_option( 'churchtools_suite_advanced_mode', 1 );
		$html = $this->render_admin( 'advanced' );
		$this->assertStringContainsString( 'kein nächster Lauf geplant', $html );
		$this->assertStringContainsString( 'id="cts-diagnostics-export"', $html );
		$this->assertStringNotContainsString( 'churchtools_suite_check_updates', $html );
		$this->assertSame( 0, $requests );
		wp_schedule_single_event( time() + 3600, 'churchtools_suite_auto_sync' );
		$this->assertNotNull( churchtools_suite_diagnostics()['cron']['next_runs']['churchtools_suite_auto_sync'] );
	}

	public function test_event_filters_keep_the_same_readable_table_and_matching_count(): void {
		global $wpdb;
		foreach ( array( '2030-06-01 12:00:00' => 'Included event', '2030-07-01 12:00:00' => 'Excluded event' ) as $start => $title ) {
			$wpdb->insert( $wpdb->prefix . 'cts_events', array( 'calendar_id' => 'filter-regression', 'title' => $title, 'start_datetime' => $start ) );
		}
		$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'data', 'subtab' => 'events', 'from' => '2030-06-01', 'to' => '2030-06-30', 'calendar_id' => 'filter-regression' );
		$html = $this->render_file( CHURCHTOOLS_SUITE_PATH . 'admin/views/data/events.php' );
		$this->assertStringContainsString( 'Included event', $html );
		$this->assertStringNotContainsString( 'Excluded event', $html );
		$this->assertStringContainsString( '1 Termine', $html );
		$this->assertStringContainsString( 'Aktive Filter:', $html );
		$this->assertStringNotContainsString( "action', value:'cts_fetch_events_list'", $html );
	}

	public function test_both_sync_pages_render_and_escape_the_same_module_status(): void {
		add_filter( 'cts_register_sync_modules', static function ( $modules ) {
			$modules['quality-test'] = array(
				'label' => '<em>Review module</em>',
				'callbacks' => array( 'get_status' => static fn () => array( 'state' => 'error', 'last_result' => array( 'message' => '<script>alert(1)</script>' ) ) ),
			);
			return $modules;
		} );
		foreach ( array( array( 'sync', '' ), array( 'settings', 'sync' ) ) as $page ) {
			$html = $this->render_admin( $page[0], $page[1] );
			$this->assertStringContainsString( '&lt;em&gt;Review module&lt;/em&gt;', $html );
			$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
			$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		}
	}

	private function load_posts_addon(): ChurchTools_Suite_Posts_Sync_Admin {
		require_once CHURCHTOOLS_SUITE_PATH . 'addons/churchtools-suite-posts-sync/churchtools-suite-posts-sync.php';
		require_once CTS_POSTS_SYNC_PATH . 'includes/class-churchtools-suite-posts-sync-service.php';
		require_once CTS_POSTS_SYNC_PATH . 'includes/class-churchtools-suite-posts-sync-admin.php';
		$addon = new ChurchTools_Suite_Posts_Sync_Admin();
		$addon->init();
		ChurchTools_Suite_Admin_Screen::reset();
		ChurchTools_Suite_Admin_Screen::register_defaults();
		return $addon;
	}
}
