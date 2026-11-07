<?php
/**
 * Renders every screen of the backend once (including the large shortcode manager).
 *
 * @package ChurchTools_Suite
 */

final class SmokeTest extends CTS_TestCase {
	public function test_smoke_all_screens(): void {
		update_option( 'churchtools_suite_advanced_mode', 1 );
		update_option( 'churchtools_suite_ct_url', 'https://example.church.tools' );
		update_option( 'churchtools_suite_ct_auth_method', 'token' );
		update_option( 'churchtools_suite_ct_token', 'tok' );
		$cases = array(
			array( 'dashboard', '' ), array( 'settings', 'api' ), array( 'settings', 'sync' ), array( 'settings', 'calendars' ),
			array( 'settings', 'services' ), array( 'settings', 'posts' ), array( 'settings', 'templates' ), array( 'settings', 'advanced' ),
			array( 'sync', '' ), array( 'data', 'events' ), array( 'data', 'imported-services' ), array( 'data', 'presets' ), array( 'data', 'posts' ),
			array( 'addons', '' ), array( 'advanced', 'uebersicht' ), array( 'advanced', 'manuelle-trigger' ), array( 'advanced', 'logs' ), array( 'advanced', 'reset-cleanup' ),
			array( 'disclaimer', '' ),
		);
		foreach ( $cases as $case ) {
			$html = $this->render_admin( $case[0], $case[1] );
			$this->assertSame( 1, substr_count( $html, '<div class="wrap cts-wrap">' ), 'nested shell: ' . $case[0] . '/' . $case[1] );
			$this->assertSame( 1, substr_count( $html, 'id="cts-sync-result"' ) );
			$this->assertStringContainsString( 'cts-rail', $html, $case[0] . '/' . $case[1] . ' has no rail' );
			$this->assertStringContainsString( 'cts-topbar', $html, $case[0] . '/' . $case[1] . ' has no top bar' );
			$this->assertStringNotContainsString( 'Die Ansichtsdatei fehlt', $html, $case[0] . '/' . $case[1] . ' has no view' );
			$this->assertGreaterThan( 3000, strlen( $html ), $case[0] . '/' . $case[1] . ' renders almost nothing' );
		}
	}
}
