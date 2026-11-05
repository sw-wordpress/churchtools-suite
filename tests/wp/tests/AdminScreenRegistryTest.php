<?php
/**
 * Tests for the admin screen registry (single source of truth of the backend navigation).
 *
 * @package ChurchTools_Suite
 */

final class AdminScreenRegistryTest extends CTS_TestCase {

	public function test_default_screens_are_registered(): void {
		$screens = ChurchTools_Suite_Admin_Screen::screens();
		foreach ( array( 'dashboard', 'settings', 'sync', 'settings/api', 'settings/calendars', 'data/events', 'addons', 'docs' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $screens, 'missing screen ' . $slug );
			$this->assertNotEmpty( $screens[ $slug ]['title'] );
		}
	}

	public function test_every_view_file_exists(): void {
		foreach ( ChurchTools_Suite_Admin_Screen::screens() as $slug => $screen ) {
			if ( '' === $screen['view'] ) {
				// Group screens (e.g. "data") only bundle sub screens.
				$this->assertTrue(
					is_callable( $screen['render'] ) || '' !== $screen['external'] || (array) ChurchTools_Suite_Admin_Screen::children( $slug ),
					$slug . ' has neither view, renderer, external URL nor children'
				);
				continue;
			}
			$this->assertNotNull(
				ChurchTools_Suite_Admin_Screen::view_path( (string) $screen['view'] ),
				'view file of ' . $slug . ' (' . $screen['view'] . ') is missing'
			);
		}
	}

	public function test_view_path_rejects_traversal_and_non_php(): void {
		$this->assertNull( ChurchTools_Suite_Admin_Screen::view_path( '../includes/class-churchtools-suite.php' ) );
		$this->assertNull( ChurchTools_Suite_Admin_Screen::view_path( 'dashboard.txt' ) );
		$this->assertNull( ChurchTools_Suite_Admin_Screen::view_path( '' ) );
		$this->assertNull( ChurchTools_Suite_Admin_Screen::view_path( 'does-not-exist.php' ) );
	}

	public function test_registry_is_idempotent(): void {
		$before = ChurchTools_Suite_Admin_Screen::screens();
		ChurchTools_Suite_Admin_Screen::register_defaults();
		$this->assertSame( array_keys( $before ), array_keys( ChurchTools_Suite_Admin_Screen::screens() ) );
	}

	public function test_advanced_screens_hidden_without_advanced_mode(): void {
		$screens = ChurchTools_Suite_Admin_Screen::screens();
		$this->assertFalse( ChurchTools_Suite_Admin_Screen::is_visible( $screens['advanced/logs'] ) );

		update_option( 'churchtools_suite_advanced_mode', 1 );
		$this->assertTrue( ChurchTools_Suite_Admin_Screen::advanced_mode() );
		$this->assertTrue( ChurchTools_Suite_Admin_Screen::is_visible( ChurchTools_Suite_Admin_Screen::screens()['advanced/logs'] ) );
	}

	public function test_navigation_contains_advanced_only_in_advanced_mode(): void {
		$this->assertContains( 'settings/calendars', $this->nav_slugs() );
		$this->assertContains( 'settings/api', $this->nav_slugs() );
		$this->assertContains( 'data', $this->nav_slugs() );
		$this->assertContains( 'data/events', $this->nav_slugs() );
		$this->assertNotContains( 'advanced', $this->nav_slugs() );
		$this->assertNotContains( 'advanced/logs', $this->nav_slugs() );

		update_option( 'churchtools_suite_advanced_mode', 1 );
		$this->assertContains( 'advanced', $this->nav_slugs() );
		$this->assertContains( 'advanced/logs', $this->nav_slugs() );
	}

	/**
	 * All slugs reachable through the rail.
	 *
	 * @return string[]
	 */
	private function nav_slugs(): array {
		$slugs = array();
		foreach ( ChurchTools_Suite_Admin_Screen::navigation() as $group ) {
			foreach ( $group['entries'] as $entry ) {
				$slugs[] = $entry['slug'];
				foreach ( $entry['children'] as $child ) {
					$slugs[] = $child['slug'];
				}
			}
		}
		return $slugs;
	}

	public function test_urls_keep_the_legacy_query_shape(): void {
		$this->assertStringContainsString( 'page=churchtools-suite', ChurchTools_Suite_Admin_Screen::url( 'settings' ) );
		$this->assertStringContainsString( 'tab=settings', ChurchTools_Suite_Admin_Screen::url( 'settings' ) );
		$this->assertStringContainsString( 'subtab=api', ChurchTools_Suite_Admin_Screen::url( 'settings/api' ) );
		$this->assertSame( ChurchTools_Suite_Admin_Screen::DOCUMENTATION_URL, ChurchTools_Suite_Admin_Screen::url( 'docs' ) );
	}

	public function test_requested_section_maps_legacy_pages(): void {
		$map = array(
			'churchtools-suite'           => 'dashboard',
			'churchtools-suite-data'      => 'data',
			'churchtools-suite-overviews' => 'data',
			'churchtools-suite-addons'    => 'addons',
			'churchtools-suite-docs'      => 'docs',
		);
		foreach ( $map as $page => $section ) {
			$_GET = array( 'page' => $page );
			$this->assertSame( $section, ChurchTools_Suite_Admin_Screen::requested_section(), $page );
		}
		$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'sync' );
		$this->assertSame( 'sync', ChurchTools_Suite_Admin_Screen::requested_section() );
		$_GET = array();
		$this->assertSame( 'dashboard', ChurchTools_Suite_Admin_Screen::requested_section() );
	}

	public function test_current_falls_back_for_unknown_sections_and_subs(): void {
		$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'nope' );
		$this->assertSame( 'dashboard', ChurchTools_Suite_Admin_Screen::current()['slug'] );

		$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'settings', 'subtab' => 'nope' );
		$this->assertSame( 'settings/api', ChurchTools_Suite_Admin_Screen::current()['slug'] );

		// Advanced screens are not reachable unless advanced mode is on.
		$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'advanced', 'subtab' => 'logs' );
		$this->assertSame( 'dashboard', ChurchTools_Suite_Admin_Screen::current()['slug'] );
		update_option( 'churchtools_suite_advanced_mode', 1 );
		$this->assertSame( 'advanced/logs', ChurchTools_Suite_Admin_Screen::current()['slug'] );
	}

	public function test_current_data_page_defaults_to_events(): void {
		$_GET = array( 'page' => 'churchtools-suite-data' );
		$this->assertSame( 'data/events', ChurchTools_Suite_Admin_Screen::current()['slug'] );
		$_GET = array( 'page' => 'churchtools-suite-data', 'subtab' => 'imported-services' );
		$this->assertSame( 'data/imported-services', ChurchTools_Suite_Admin_Screen::current()['slug'] );
	}

	public function test_addons_can_register_screens(): void {
		ChurchTools_Suite_Admin_Screen::add_screen(
			'data/thing',
			array(
				'title'  => 'Thing',
				'parent' => 'data',
				'group'  => 'data',
				'render' => static function (): void {
					echo '<p id="addon-screen">hello</p>';
				},
			)
		);
		$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'data', 'subtab' => 'thing' );
		$this->assertSame( 'data/thing', ChurchTools_Suite_Admin_Screen::current()['slug'] );
		$this->assertStringContainsString( 'hello', $this->render_admin( 'data', 'thing' ) );

		ChurchTools_Suite_Admin_Screen::remove_screen( 'data/thing' );
		$this->assertArrayNotHasKey( 'data/thing', ChurchTools_Suite_Admin_Screen::screens() );
	}

	public function test_breadcrumb_for_sub_screen(): void {
		$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'settings', 'subtab' => 'templates' );
		$trail = ChurchTools_Suite_Admin_Screen::breadcrumb();
		$this->assertCount( 3, $trail );
		$this->assertSame( 'Einstellungen', $trail[1]['title'] );
	}
}
