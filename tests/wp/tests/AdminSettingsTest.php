<?php
/**
 * Settings contracts preserved by the view migration.
 *
 * @package ChurchTools_Suite
 */
final class AdminSettingsTest extends CTS_TestCase {
	public function test_auto_sync_times_use_wordpress_timezone_without_changing_the_schedule(): void {
		update_option( 'timezone_string', 'Europe/Berlin' );
		update_option( 'date_format', 'd.m.Y' );
		update_option( 'time_format', 'H:i' );
		update_option( 'churchtools_suite_auto_sync_enabled', 1 );
		update_option( 'churchtools_suite_last_auto_sync', '2026-11-11 19:23:00' );
		wp_clear_scheduled_hook( 'churchtools_suite_auto_sync' );
		$next = strtotime( '2026-11-12 08:15:00 UTC' );
		wp_schedule_single_event( $next, 'churchtools_suite_auto_sync' );
		foreach ( array( array( 'settings', 'sync' ), array( 'dashboard', '' ) ) as [$section, $sub] ) {
			$html = $this->render_admin( $section, $sub );
			$this->assertStringContainsString( '12.11.2026 09:15', $html );
			$this->assertStringContainsString( '11.11.2026 19:23', $html );
			$this->assertStringContainsString( 'Letzter erfolgreicher Auto-Sync', $html );
		}
		$this->assertSame( $next, wp_next_scheduled( 'churchtools_suite_auto_sync' ) );
	}

	public function test_auto_sync_times_explain_missing_schedule_and_success(): void {
		wp_clear_scheduled_hook( 'churchtools_suite_auto_sync' );
		delete_option( 'churchtools_suite_last_auto_sync' );
		foreach ( array( array( 'settings', 'sync' ), array( 'dashboard', '' ) ) as [$section, $sub] ) {
			$html = $this->render_admin( $section, $sub );
			$this->assertStringContainsString( 'Nicht geplant', $html );
			$this->assertStringContainsString( 'Noch kein erfolgreicher Auto-Sync', $html );
		}
	}

	public function test_api_form_saves_the_existing_option_names_and_normalizes_authentication(): void {
		$_POST = array(
			'_wpnonce'       => wp_create_nonce( 'cts_settings' ),
			'cts_save_api'   => '1',
			'ct_tenant'      => 'review',
			'ct_auth_method' => 'invalid-auth-method',
			'ct_username'    => 'review@example.org',
			'ct_password'    => 'review-password',
			'ct_token'       => 'review-token',
		);
		$html = $this->render_admin( 'settings', 'api' );
		$this->assertStringContainsString( 'API-Einstellungen gespeichert.', $html );
		$this->assertSame( 'https://review.church.tools', get_option( 'churchtools_suite_ct_url' ) );
		$this->assertSame( 'password', get_option( 'churchtools_suite_ct_auth_method' ) );
		$this->assertSame( 'review@example.org', get_option( 'churchtools_suite_ct_username' ) );
		$_POST['ct_auth_method'] = 'token';
		$this->render_admin( 'settings', 'api' );
		$this->assertSame( 'token', get_option( 'churchtools_suite_ct_auth_method' ) );
	}

	public function test_invalid_nonce_cannot_change_api_settings(): void {
		update_option( 'churchtools_suite_ct_url', 'https://unchanged.church.tools' );
		$_POST = array( '_wpnonce' => 'invalid', 'cts_save_api' => '1', 'ct_tenant' => 'changed' );
		try {
			$this->render_admin( 'settings', 'api' );
			$this->fail( 'An invalid nonce must reject the submission.' );
		} catch ( WPDieException $exception ) {
			$this->assertSame( 'https://unchanged.church.tools', get_option( 'churchtools_suite_ct_url' ) );
		}
	}

	public function test_sync_form_preserves_ranges_and_bounds_expired_event_cleanup(): void {
		$_POST = array(
			'_wpnonce'                  => wp_create_nonce( 'cts_settings' ),
			'cts_save_sync'              => '1',
			'sync_days_past'             => '14',
			'sync_days_future'           => '120',
			'expired_events_after_days'  => '999999',
			'delete_expired_events'      => '1',
			'auto_sync_enabled'          => '1',
			'auto_sync_interval'         => 'hourly',
		);
		$this->render_admin( 'settings', 'sync' );
		$this->assertSame( 14, get_option( 'churchtools_suite_sync_days_past' ) );
		$this->assertSame( 120, get_option( 'churchtools_suite_sync_days_future' ) );
		$this->assertSame( 3650, get_option( 'churchtools_suite_expired_events_after_days' ) );
		$this->assertSame( 1, get_option( 'churchtools_suite_delete_expired_events' ) );
		$this->assertSame( 'hourly', wp_get_schedule( 'churchtools_suite_auto_sync' ) );
	}

	public function test_disabling_auto_sync_preserves_the_selected_interval(): void {
		update_option( 'churchtools_suite_auto_sync_enabled', 1 );
		update_option( 'churchtools_suite_auto_sync_interval', 'cts_weekly' );
		$_POST = array(
			'_wpnonce'      => wp_create_nonce( 'cts_settings' ),
			'cts_save_sync' => '1',
		);
		$this->render_admin( 'settings', 'sync' );
		$this->assertSame( 0, get_option( 'churchtools_suite_auto_sync_enabled' ) );
		$this->assertSame( 'cts_weekly', get_option( 'churchtools_suite_auto_sync_interval' ) );
		$this->assertFalse( wp_next_scheduled( 'churchtools_suite_auto_sync' ) );
	}

	public function test_advanced_mode_can_be_enabled_and_disabled_from_its_existing_form(): void {
		$_POST = array( '_wpnonce' => wp_create_nonce( 'cts_settings' ), 'cts_save_advanced' => '1', 'advanced_mode' => '1' );
		$this->render_admin( 'settings', 'advanced' );
		$this->assertTrue( ChurchTools_Suite_Admin_Screen::advanced_mode() );
		unset( $_POST['advanced_mode'] );
		$this->render_admin( 'settings', 'advanced' );
		$this->assertFalse( ChurchTools_Suite_Admin_Screen::advanced_mode() );
	}
	public function test_saved_secrets_are_not_rendered_or_erased_by_blank_fields(): void {
		update_option( 'churchtools_suite_ct_url', 'https://review.church.tools' );
		update_option( 'churchtools_suite_ct_username', 'review@example.org' );
		update_option( 'churchtools_suite_ct_password', 'private-password' );
		update_option( 'churchtools_suite_ct_token', 'private-token' );
		$html = $this->render_admin( 'settings', 'api' );
		$this->assertStringNotContainsString( 'private-password', $html );
		$this->assertStringNotContainsString( 'private-token', $html );
		$_POST = array( '_wpnonce' => wp_create_nonce( 'cts_settings' ), 'cts_save_api' => '1', 'ct_tenant' => 'review', 'ct_auth_method' => 'password', 'ct_username' => 'review@example.org', 'ct_password' => '' );
		$this->render_admin( 'settings', 'api' );
		$this->assertSame( 'private-password', get_option( 'churchtools_suite_ct_password' ) );
		$this->assertSame( 'private-token', ChurchTools_Suite_Secret_Storage::decrypt( get_option( 'churchtools_suite_ct_token' ) ) );
	}

	public function test_token_edit_keeps_the_saved_password_account_on_the_same_host(): void {
		update_option( 'churchtools_suite_ct_url', 'https://review.church.tools' );
		update_option( 'churchtools_suite_ct_username', 'review@example.org' );
		update_option( 'churchtools_suite_ct_password', 'saved-password' );
		$_POST = array( '_wpnonce' => wp_create_nonce( 'cts_settings' ), 'cts_save_api' => '1', 'ct_tenant' => 'review', 'ct_auth_method' => 'token', 'ct_token' => 'new-token' );
		$this->render_admin( 'settings', 'api' );
		$this->assertSame( 'review@example.org', get_option( 'churchtools_suite_ct_username' ) );
		$this->assertSame( 'saved-password', get_option( 'churchtools_suite_ct_password' ) );
	}

	public function test_changed_host_drops_inactive_credentials_instead_of_reusing_them_later(): void {
		update_option( 'churchtools_suite_ct_url', 'https://old.church.tools' );
		update_option( 'churchtools_suite_ct_username', 'old@example.org' );
		update_option( 'churchtools_suite_ct_password', 'old-password' );
		$_POST = array( '_wpnonce' => wp_create_nonce( 'cts_settings' ), 'cts_save_api' => '1', 'ct_tenant' => 'new', 'ct_auth_method' => 'token', 'ct_token' => 'new-token' );
		$this->render_admin( 'settings', 'api' );
		$this->assertSame( 'https://new.church.tools', get_option( 'churchtools_suite_ct_url' ) );
		$this->assertFalse( get_option( 'churchtools_suite_ct_password' ) );
	}

	public function test_inactive_authentication_rows_are_hidden_before_javascript_runs(): void {
		update_option( 'churchtools_suite_ct_auth_method', 'password' );
		$html = $this->render_admin( 'settings', 'api' );
		$this->assertStringContainsString( '<tr class="cts-auth-token" hidden>', $html );
		$this->assertStringNotContainsString( '<tr class="cts-auth-password" hidden>', $html );
		update_option( 'churchtools_suite_ct_auth_method', 'token' );
		$html = $this->render_admin( 'settings', 'api' );
		$this->assertStringContainsString( '<tr class="cts-auth-password" hidden>', $html );
		$this->assertStringNotContainsString( '<tr class="cts-auth-token" hidden>', $html );
	}

}
