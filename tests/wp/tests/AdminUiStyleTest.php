<?php
/**
 * The shell must use the registered endpoint and a valid nonce for the current request.
 *
 * @package ChurchTools_Suite
 */

final class AdminUiStyleTest extends CTS_TestCase {

	/**
	 * The shell must reuse the existing AJAX action and nonce instead of introducing new endpoints.
	 */
	public function test_sync_endpoints_still_use_the_existing_action_and_nonce(): void {
		$js = (string) file_get_contents( CHURCHTOOLS_SUITE_PATH . 'assets/js/churchtools-suite-admin-ui.js' );
		$this->assertStringContainsString( "action: 'cts_trigger_manual_sync'", $js );
		$this->assertStringContainsString( 'config.nonce', $js );

		// The AJAX action is registered and the localized config carries the same nonce name.
		$this->assertNotFalse( has_action( 'wp_ajax_cts_trigger_manual_sync' ) );

		$_GET = array( 'page' => 'churchtools-suite', 'tab' => 'dashboard' );
		$admin = new ChurchTools_Suite_Admin( CHURCHTOOLS_SUITE_VERSION );
		$admin->enqueue_styles();

		// The config is added as one inline script; repeated enqueues must not duplicate it.
		$before = (array) wp_scripts()->get_data( 'churchtools-suite-admin', 'before' );
		$inline = '';
		foreach ( $before as $cts_before ) {
			$inline .= (string) $cts_before;
		}
		$this->assertStringContainsString( 'window.churchtoolsSuite =', $inline );
		// enqueue_styles() can run more than once per request (e.g. the admin print scripts); the
		// config must stay a single statement.
		$this->assertSame( 1, substr_count( $inline, 'window.churchtoolsSuite =' ), 'the config must be registered exactly once' );

		$start   = strpos( $inline, '{' );
		$end     = strrpos( $inline, '}' );
		$this->assertIsInt( $start );
		$this->assertIsInt( $end );
		$payload = substr( (string) $inline, (int) $start, (int) ( $end - $start + 1 ) );

		$decoded = json_decode( $payload, true );
		if ( null === $decoded ) {
			$this->fail( 'invalid JSON (' . json_last_error_msg() . '): ' . $payload );
		}
		$this->assertSame( wp_create_nonce( ChurchTools_Suite_Admin_Screen::NONCE ), $decoded['nonce'], 'the config must come from this request' );

		$this->assertArrayHasKey( 'nonce', $decoded );
		$this->assertTrue(
			wp_verify_nonce( $decoded['nonce'], ChurchTools_Suite_Admin_Screen::NONCE ) !== false,
			'the localized nonce must be valid for the admin nonce action'
		);
		$this->assertSame( CHURCHTOOLS_SUITE_VERSION, $decoded['version'] );
	}
}
