<?php
/** Connection testing through the real AJAX action and a fixture ChurchTools HTTP API. */
final class CTS_Connection_Test_Exit extends Error {}

final class ConnectionPreviewTest extends CTS_TestCase {
	private function request( array $form ) {
		$_POST = $form + array( 'nonce' => wp_create_nonce( 'churchtools_suite_admin' ) );
		$_REQUEST = $_POST;
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', static fn () => static function ( $message ) { echo $message; throw new CTS_Connection_Test_Exit(); } );
		ob_start();
		try {
			do_action( 'wp_ajax_cts_test_connection' );
		} catch ( CTS_Connection_Test_Exit $exception ) {
			// WordPress terminates an AJAX response; retain only this test's buffer.
		}
		$response = ob_get_clean();
		$this->assertIsString( $response );
		return json_decode( $response, true, 512, JSON_THROW_ON_ERROR );
	}

	private function fixture( callable $callback ): void {
		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', $callback, 10, 3 );
	}

	private function ok( array $data, array $cookies = array() ): array {
		return array( 'headers' => array(), 'body' => wp_json_encode( array( 'data' => $data ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => $cookies );
	}

	public function test_token_preview_uses_unsaved_form_values_without_changing_the_saved_account(): void {
		update_option( 'churchtools_suite_ct_url', 'https://saved.church.tools' );
		update_option( 'churchtools_suite_ct_token', 'old-token' );
		update_option( 'churchtools_suite_ct_cookies', array( 'saved' ) );
		$this->fixture( function ( $unused, $args, $url ) {
			$this->assertSame( 'https://preview.church.tools/api/whoami', $url );
			$this->assertSame( 'Login new-token', $args['headers']['Authorization'] );
			return $this->ok( array( 'id' => 12, 'firstName' => 'Preview' ) );
		} );
		$result = $this->request( array( 'ct_tenant' => 'preview', 'ct_auth_method' => 'token', 'ct_token' => 'new-token' ) );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 'https://saved.church.tools', get_option( 'churchtools_suite_ct_url' ) );
		$this->assertSame( 'old-token', get_option( 'churchtools_suite_ct_token' ) );
		$this->assertSame( array( 'saved' ), get_option( 'churchtools_suite_ct_cookies' ) );
		$this->assertFalse( get_option( 'churchtools_suite_ct_user_info' ) );
	}

	public function test_password_preview_logs_in_and_checks_whoami_without_replacing_the_saved_session(): void {
		update_option( 'churchtools_suite_ct_url', 'https://review.church.tools' );
		update_option( 'churchtools_suite_ct_username', 'review@example.org' );
		update_option( 'churchtools_suite_ct_password', 'saved-password' );
		update_option( 'churchtools_suite_ct_cookies', array( 'saved' ) );
		$requests = array();
		$this->fixture( function ( $unused, $args, $url ) use ( &$requests ) {
			$requests[] = $url;
			if ( str_ends_with( $url, '/login' ) ) {
				$this->assertSame( array( 'username' => 'review@example.org', 'password' => 'saved-password' ), json_decode( $args['body'], true ) );
				return $this->ok( array( 'status' => 'success', 'personId' => 12 ), array( new WP_Http_Cookie( array( 'name' => 'session', 'value' => 'preview-session' ) ) ) );
			}
			$this->assertNotEmpty( $args['cookies'] );
			return $this->ok( array( 'personId' => 12 ) );
		} );
		$result = $this->request( array( 'ct_tenant' => 'review', 'ct_auth_method' => 'password', 'ct_username' => 'review@example.org', 'ct_password' => '' ) );
		$this->assertTrue( $result['success'] );
		$this->assertCount( 2, $requests );
		$this->assertSame( array( 'saved' ), get_option( 'churchtools_suite_ct_cookies' ) );
	}

	public function test_bad_credentials_are_reported_and_not_silently_saved(): void {
		$this->fixture( static fn () => array( 'headers' => array(), 'body' => '{}', 'response' => array( 'code' => 401 ) ) );
		$result = $this->request( array( 'ct_tenant' => 'review', 'ct_auth_method' => 'token', 'ct_token' => 'bad' ) );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 'auth_failed', $result['data']['error_code'] );
		$this->assertFalse( get_option( 'churchtools_suite_ct_token' ) );
	}

	public function test_missing_credentials_or_changed_hosts_do_not_reuse_saved_secrets(): void {
		update_option( 'churchtools_suite_ct_url', 'https://saved.church.tools' );
		update_option( 'churchtools_suite_ct_token', 'secret' );
		$this->fixture( function () { $this->fail( 'No HTTP request should be made without credentials.' ); } );
		$result = $this->request( array( 'ct_tenant' => 'other', 'ct_auth_method' => 'token', 'ct_token' => '' ) );
		$this->assertFalse( $result['success'] );
	}

	public function test_invalid_nonce_cannot_test_credentials(): void {
		$this->fixture( function () { $this->fail( 'No unauthorized HTTP request.' ); } );
		$result = $this->request( array( 'nonce' => 'invalid' ) );
		$this->assertSame( -1, $result );
	}
	public function test_missing_capability_cannot_test_credentials(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->fixture( function () { $this->fail( 'No unauthorized HTTP request.' ); } );
		$result = $this->request( array( 'ct_tenant' => 'preview', 'ct_auth_method' => 'token', 'ct_token' => 'secret' ) );
		$this->assertFalse( $result['success'] );
	}

	public function test_legacy_callers_can_still_check_the_saved_account(): void {
		update_option( 'churchtools_suite_ct_url', 'https://saved.church.tools' );
		update_option( 'churchtools_suite_ct_auth_method', 'token' );
		update_option( 'churchtools_suite_ct_token', 'saved-token' );
		$this->fixture( function ( $unused, $args, $url ) {
			$this->assertSame( 'https://saved.church.tools/api/whoami', $url );
			$this->assertSame( 'Login saved-token', $args['headers']['Authorization'] );
			return $this->ok( array( 'id' => 12 ) );
		} );
		$this->assertTrue( $this->request( array() )['success'] );
		$this->assertNotFalse( get_option( 'churchtools_suite_ct_last_login' ) );
	}

}
