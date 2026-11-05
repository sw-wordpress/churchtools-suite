<?php
/**
 * Regression guard for the admin design system.
 *
 * The views are plain PHP templates, so markup mistakes (emoji instead of dashicons, missing
 * stylesheet handles, inline colours that fight the design tokens) are cheap to check here and
 * expensive to find in the browser.
 *
 * @package ChurchTools_Suite
 */

final class AdminDesignSystemTest extends CTS_TestCase {

	/**
	 * All view files of the restructured backend.
	 *
	 * @return array<string, array{0:string}>
	 */
	public function view_provider(): array {
		$files = array_merge(
			glob( CHURCHTOOLS_SUITE_PATH . 'admin/views/*.php' ) ?: array(),
			glob( CHURCHTOOLS_SUITE_PATH . 'admin/views/*/*.php' ) ?: array()
		);
		$cases = array();
		foreach ( $files as $file ) {
			$cases[ basename( dirname( $file ) ) . '/' . basename( $file ) ] = array( $file );
		}
		$this->assertGreaterThan( 15, count( $cases ), 'expected the restructured view files' );
		return $cases;
	}

	/**
	 * @dataProvider view_provider
	 * @param string $file Absolute view path.
	 */
	public function test_views_use_dashicons_instead_of_emoji( string $file ): void {
		$contents = (string) file_get_contents( $file );

		preg_match_all( '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', $contents, $emoji );
		$this->assertSame( array(), $emoji[0], 'emoji found in ' . $file );

		preg_match_all( '/class="dashicons(?: (?!dashicons-)[a-z]|[a-z])/', $contents, $broken );
		$this->assertSame( array(), $broken[0], 'dashicon class without the dashicons- prefix in ' . $file );

		$this->assertStringNotContainsString( '<span></span>', $contents, 'empty markup left over in ' . $file );
	}

	/**
	 * @dataProvider view_provider
	 * @param string $file Absolute view path.
	 */
	public function test_views_stay_escapable_php( string $file ): void {
		$exit   = 0;
		$output = array();
		exec( 'php -l ' . escapeshellarg( $file ) . ' 2>&1', $output, $exit );
		$this->assertSame( 0, $exit, implode( "\n", $output ) );

		// merge conflict markers survive php -l when they sit in template output
		$this->assertStringNotContainsString( '<<<<<<<', (string) file_get_contents( $file ), 'conflict marker in ' . $file );
	}

	public function test_design_system_defines_tokens_and_components(): void {
		$css = (string) file_get_contents( CHURCHTOOLS_SUITE_PATH . 'assets/css/churchtools-suite-admin-ui.css' );
		foreach (
			array(
				// tokens
				'--cts-primary',
				'--cts-surface',
				'--cts-border',
				'--cts-danger',
				'--cts-radius',
				'--cts-shadow-2',
				'--cts-gradient',
				// dark scheme
				'body.cts-dark .cts-wrap',
				'color-scheme: dark',
				// shell
				'.cts-rail',
				'.cts-topbar',
				'.cts-nav-link--active',
				'.cts-rail-collapsed',
				// components
				'.cts-card',
				'.cts-chip',
				'.cts-btn--primary',
				'.cts-btn-secondary',
				'.cts-stat',
				'.cts-callout',
				'.cts-empty',
				'.cts-form-grid',
				'.cts-table',
				// accessibility
				'prefers-reduced-motion',
				':focus-visible',
				'.cts-sr-only',
			) as $needle
		) {
			$this->assertStringContainsString( $needle, $css, 'missing in the design system CSS: ' . $needle );
		}
	}

	public function test_shell_script_exposes_the_documented_api(): void {
		$js = (string) file_get_contents( CHURCHTOOLS_SUITE_PATH . 'assets/js/churchtools-suite-admin-ui.js' );
		foreach (
			array(
				'window.ctsAdmin',
				'toggleTheme',
				'setRailCollapsed',
				'syncNow',
				'notify',
				'cts_trigger_manual_sync',
				'cts:sync-finished',
				"data-cts-action=\"toggle-rail\"",
			) as $needle
		) {
			$this->assertStringContainsString( $needle, $js, 'missing in the admin UI JS: ' . $needle );
		}
	}

	/**
	 * The legacy stylesheet stays enqueued (old view markup) but must not fight the new tokens.
	 */
	public function test_legacy_stylesheets_are_not_broken(): void {
		foreach (
			array(
				'assets/css/churchtools-suite-admin.css',
				'assets/css/churchtools-suite-public.css',
			) as $file
		) {
			$css = (string) file_get_contents( CHURCHTOOLS_SUITE_PATH . $file );
			$this->assertStringContainsString( '.cts-', $css, $file . ' lost its namespace' );
			$this->assertStringNotContainsString( '<<<<<<<', $css, 'conflict marker in ' . $file );
		}
	}

	/**
	 * Every screen renders a shell with rail, top bar and a page title.
	 *
	 * @dataProvider screen_provider
	 * @param string $section Section slug.
	 * @param string $sub     Sub slug.
	 */
	public function test_each_screen_renders_the_shell( string $section, string $sub ): void {
		update_option( 'churchtools_suite_advanced_mode', 1 );
		$html = $this->render_admin( $section, $sub );

		$this->assertStringContainsString( '<div class="wrap cts-wrap">', $html );
		$this->assertStringContainsString( '<aside class="cts-rail"', $html );
		$this->assertStringContainsString( '<header class="cts-topbar">', $html );
		$this->assertStringContainsString( 'cts-page-title', $html );
		$this->assertStringNotContainsString( 'Die Ansichtsdatei fehlt', $html, $section . '/' . $sub . ' has no view' );
		$this->assertStringNotContainsString( '<h1>⚠', $html );
	}

	/**
	 * Screens that render a view (the shortcode manager renders ~100 KB of markup and is covered
	 * by SmokeTest instead).
	 *
	 * @return array<string, array{0:string, 1:string}>
	 */
	public function screen_provider(): array {
		ChurchTools_Suite_Admin_Screen::register_defaults();
		$skip  = array( 'data/presets' );
		$cases = array();
		foreach ( ChurchTools_Suite_Admin_Screen::screens() as $slug => $screen ) {
			if ( '' !== (string) $screen['external'] || in_array( (string) $slug, $skip, true ) ) {
				continue;
			}
			$parts          = explode( '/', (string) $slug, 2 );
			$cases[ $slug ] = array( $parts[0], isset( $parts[1] ) ? $parts[1] : '' );
		}
		return $cases;
	}

	/**
	 * The page title appears once: in the top bar. Views must not repeat it as a big heading.
	 *
	 * @dataProvider screen_provider
	 * @param string $section Section slug.
	 * @param string $sub     Sub slug.
	 */
	public function test_views_do_not_repeat_the_page_title( string $section, string $sub ): void {
		update_option( 'churchtools_suite_advanced_mode', 1 );
		$started = microtime( true );
		$html    = $this->normalize( $this->render_admin( $section, $sub ) );
		$elapsed = microtime( true ) - $started;
		if ( $elapsed > 0.5 ) {
			fwrite( STDOUT, "\n[SLOW " . $section . "/" . $sub . " " . number_format( $elapsed, 2 ) . "s]\n" );
		}

		$topbar_end = strpos( $html, '</header>' );
		$this->assertIsInt( $topbar_end );
		$body = substr( (string) $html, $topbar_end );

		$this->assertSame( 0, preg_match( '/<h1[ >]/', $body ), $section . '/' . $sub . ' renders a second h1' );
		$this->assertSame( 0, preg_match( '/<div class="cts-section-header">\s*<h[23][ >]/', $body ), $section . '/' . $sub . ' repeats its title' );
	}
}
