<?php
/**
 * Tests for the shared connection state helper.
 *
 * @package ChurchTools_Suite
 */

final class ConnectionStateTest extends CTS_TestCase {

	public function test_empty_install_is_neither_configured_nor_connected(): void {
		delete_option( 'churchtools_suite_ct_url' );
		delete_option( 'churchtools_suite_ct_auth_method' );
		delete_option( 'churchtools_suite_ct_username' );
		delete_option( 'churchtools_suite_ct_password' );
		delete_option( 'churchtools_suite_ct_token' );
		delete_option( 'churchtools_suite_ct_cookies' );

		$state = churchtools_suite_connection_state();
		$this->assertFalse( $state['configured'] );
		$this->assertFalse( $state['connected'] );
		$this->assertSame( 'password', $state['auth_method'] );
	}

	public function test_password_auth_requires_url_user_and_password(): void {
		update_option( 'churchtools_suite_ct_url', 'https://example.church.tools' );
		update_option( 'churchtools_suite_ct_username', 'user@example.org' );
		$this->assertFalse( churchtools_suite_connection_state()['configured'] );

		update_option( 'churchtools_suite_ct_password', 'secret' );
		$state = churchtools_suite_connection_state();
		$this->assertTrue( $state['configured'] );
		$this->assertFalse( $state['connected'], 'no session cookies yet' );

		update_option( 'churchtools_suite_ct_cookies', array( 'ct_session' => 'x' ) );
		$this->assertTrue( churchtools_suite_connection_state()['connected'] );
	}

	public function test_token_auth_reads_encrypted_token(): void {
		update_option( 'churchtools_suite_ct_url', 'https://example.church.tools' );
		update_option( 'churchtools_suite_ct_auth_method', 'token' );
		$this->assertFalse( churchtools_suite_connection_state()['configured'] );

		require_once CHURCHTOOLS_SUITE_PATH . 'includes/functions/secret-storage.php';
		update_option( 'churchtools_suite_ct_token', ChurchTools_Suite_Secret_Storage::encrypt( 'my-token' ) );

		$state = churchtools_suite_connection_state();
		$this->assertTrue( $state['configured'] );
		$this->assertTrue( $state['connected'] );
		$this->assertSame( 'token', $state['auth_method'] );
	}

	public function test_state_never_exposes_secrets(): void {
		update_option( 'churchtools_suite_ct_url', 'https://example.church.tools' );
		update_option( 'churchtools_suite_ct_username', 'user@example.org' );
		update_option( 'churchtools_suite_ct_password', 'super-secret' );
		update_option( 'churchtools_suite_ct_token', 'token-secret' );

		$serialized = wp_json_encode( churchtools_suite_connection_state() );
		$this->assertStringNotContainsString( 'super-secret', $serialized );
		$this->assertStringNotContainsString( 'token-secret', $serialized );
	}
}
