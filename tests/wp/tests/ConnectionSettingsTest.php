<?php
/** Form parsing regressions: tenant validation, WordPress slashes and secret reuse. */
final class ConnectionSettingsTest extends CTS_TestCase {
	public function set_up(): void {
		parent::set_up();
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/functions/connection-settings.php';
	}

	public function test_tenant_is_normalized_and_wordpress_slashes_are_removed_once(): void {
		$password = 'quote\'and\\backslash';
		$result = churchtools_suite_connection_form_values( wp_slash( array( 'ct_tenant' => ' Review-123 ', 'ct_auth_method' => 'password', 'ct_username' => 'review@example.org', 'ct_password' => $password ) ) );
		$this->assertIsArray( $result );
		$this->assertSame( 'https://review-123.church.tools', $result['url'] );
		$this->assertSame( $password, $result['password'] );
	}

	/** @dataProvider invalid_tenants */
	public function test_invalid_tenants_cannot_construct_arbitrary_api_destinations( $tenant ): void {
		$result = churchtools_suite_connection_form_values( array( 'ct_tenant' => $tenant, 'ct_auth_method' => 'token', 'ct_token' => 'secret' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'invalid_tenant', $result->get_error_code() );
	}

	public static function invalid_tenants(): array {
		return array(
			'empty' => array( '' ),
			'url' => array( 'https://review.church.tools' ),
			'host' => array( 'review.church.tools' ),
			'path' => array( 'review/private' ),
			'userinfo' => array( 'review@evil.example' ),
			'leading hyphen' => array( '-review' ),
			'trailing hyphen' => array( 'review-' ),
			'oversized label' => array( str_repeat( 'a', 64 ) ),
			'array injection' => array( array( 'review' ) ),
		);
	}

	public function test_changing_username_requires_a_new_password_on_the_same_host(): void {
		update_option( 'churchtools_suite_ct_url', 'https://review.church.tools' );
		update_option( 'churchtools_suite_ct_username', 'old@example.org' );
		update_option( 'churchtools_suite_ct_password', 'saved-password' );
		$result = churchtools_suite_connection_form_values( array( 'ct_tenant' => 'review', 'ct_auth_method' => 'password', 'ct_username' => 'new@example.org', 'ct_password' => '' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'missing_credentials', $result->get_error_code() );
		$this->assertSame( 'old@example.org', get_option( 'churchtools_suite_ct_username' ) );
	}

	public function test_encrypted_token_can_be_reused_only_for_the_saved_host(): void {
		update_option( 'churchtools_suite_ct_url', 'https://review.church.tools/' );
		update_option( 'churchtools_suite_ct_token', ChurchTools_Suite_Secret_Storage::encrypt( 'saved-token' ) );
		$result = churchtools_suite_connection_form_values( array( 'ct_tenant' => 'review', 'ct_auth_method' => 'token', 'ct_token' => '' ) );
		$this->assertIsArray( $result );
		$this->assertSame( 'saved-token', $result['token'] );
		$this->assertWPError( churchtools_suite_connection_form_values( array( 'ct_tenant' => 'another', 'ct_auth_method' => 'token', 'ct_token' => '' ) ) );
	}
}
