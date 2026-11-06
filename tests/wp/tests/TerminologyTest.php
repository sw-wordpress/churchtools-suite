<?php
/**
 * Keep the user-facing German term consistent across the plugin and bundled addons.
 * Technical services keys, database names and API paths remain unchanged.
 *
 * @package ChurchTools_Suite
 */

final class TerminologyTest extends CTS_TestCase {
	public function test_runtime_sources_and_catalogs_do_not_reintroduce_the_old_plural(): void {
		$checked = 0;
		foreach ( array( 'admin', 'includes', 'addons', 'templates', 'assets/js', 'languages' ) as $directory ) {
			$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( CHURCHTOOLS_SUITE_PATH . $directory, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $files as $file ) {
				if ( ! $file->isFile() || ! in_array( $file->getExtension(), array( 'php', 'js', 'po', 'pot', 'mo' ), true ) || preg_match( '~/(vendor|node_modules)/~', $file->getPathname() ) ) {
					continue;
				}
				$contents = file_get_contents( $file->getPathname() );
				$this->assertDoesNotMatchRegularExpression( '/\bServices\b/', $contents, $file->getPathname() );
				++$checked;
			}
		}
		$this->assertGreaterThan( 100, $checked );
	}

	public function test_dienste_settings_use_the_same_term_in_every_step(): void {
		$html = $this->render_admin( 'settings', 'services' );
		$this->assertStringContainsString( 'Schritt 3: Dienste synchronisieren', $html );
		$this->assertStringContainsString( 'Schritt 4: Dienste auswählen', $html );
		$this->assertStringContainsString( 'Dienstgruppen', $html );
		$this->assertStringNotContainsString( 'Services', $html );
	}

	public function test_technical_data_contracts_keep_their_existing_keys(): void {
		$this->assertInstanceOf( ChurchTools_Suite_Services_Repository::class, churchtools_suite_get_repository( 'services' ) );
		$this->assertInstanceOf( ChurchTools_Suite_Event_Services_Repository::class, churchtools_suite_get_repository( 'event_services' ) );
		$this->assertStringContainsString( "'show_services'", file_get_contents( CHURCHTOOLS_SUITE_PATH . 'includes/view-feature-matrix.php' ) );
		$this->assertNotFalse( has_action( 'wp_ajax_cts_sync_services' ) );
		$this->assertNotFalse( has_action( 'wp_ajax_cts_save_service_selection' ) );
	}
}
