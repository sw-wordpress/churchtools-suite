<?php
/**
 * Single Event Handler
 *
 * Handles rendering of single event view when event_id query parameter is present.
 * Intercepts page content and replaces it with single event display.
 *
 * @package ChurchTools_Suite
 * @since   0.9.3.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChurchTools_Suite_Single_Event_Handler {
	/**
	 * Resolve requested single template from explicit value, URL or settings.
	 */
	public static function get_requested_template( ?string $template = null ): string {
		if ( is_string( $template ) && '' !== $template ) {
			return sanitize_key( $template );
		}

		/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, display filters, or status messages; no state is changed by these query arguments. */ if ( isset( $_GET['template'] ) && ! empty( $_GET['template'] ) ) {
			/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, display filters, or status messages; no state is changed by these query arguments. */ return sanitize_key( wp_unslash( $_GET['template'] ) );
		}

		return sanitize_key( (string) get_option( 'churchtools_suite_single_template', 'professional' ) );
	}

	/**
	 * Render a single event with consistent markup across Gutenberg and Elementor.
	 */
	public static function render_single_event_page( int $event_id, ?string $template = null, bool $include_back_button = true ): string {
		if ( $event_id <= 0 ) {
			return '';
		}

		$template = self::get_requested_template( $template );

		if ( ! class_exists( 'ChurchTools_Suite_Logger' ) ) {
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-logger.php';
		}

		ChurchTools_Suite_Logger::debug(
			'single_event_handler',
			'Template selected for single event',
			array(
				'event_id'          => $event_id,
				'template_name'     => $template,
				/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, display filters, or status messages; no state is changed by these query arguments. */ 'source' => isset( $_GET['template'] ) ? 'URL parameter' : 'Dashboard setting',
				/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, display filters, or status messages; no state is changed by these query arguments. */ 'url_param' => sanitize_text_field( wp_unslash( $_GET['template'] ?? 'not set' ) ),
				'dashboard_setting' => get_option( 'churchtools_suite_single_template', 'professional' ),
				'expected_path'     => 'templates/views/event-single/single-' . $template . '.php',
			)
		);

		if ( $include_back_button ) {
			$GLOBALS['churchtools_suite_single_back_link'] = remove_query_arg( array( 'event_id', 'template', 'ctse_context' ) );
		} else {
			unset( $GLOBALS['churchtools_suite_single_back_link'] );
		}

		require_once CHURCHTOOLS_SUITE_PATH . 'includes/shortcodes/class-churchtools-suite-single-event-shortcode.php';
		$single_event = ChurchTools_Suite_Single_Event_Shortcode::render(
			array(
				'id'       => $event_id,
				'template' => $template,
			)
		);
		unset( $GLOBALS['churchtools_suite_single_back_link'] );

		return $single_event;
	}

	/**
	 * Initialize handler
	 */
	public static function init(): void {
		// Intercept content when event_id is present.
		// Run late so wpautop and similar content filters cannot wrap the rendered template markup.
		add_filter( 'the_content', array( __CLASS__, 'maybe_show_single_event' ), 9999 );
	}

	/**
	 * Maybe replace page content with single event view
	 *
	 * @param string $content Original content
	 * @return string Modified content
	 */
	public static function maybe_show_single_event( string $content ): string {
		// Only on singular pages (not archives, home, etc.).
		if ( ! is_singular() ) {
			return $content;
		}

		// Check if event_id is present.
		/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, display filters, or status messages; no state is changed by these query arguments. */ $event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;

		if ( ! $event_id ) {
			return $content;
		}

		return self::render_single_event_page( $event_id );
	}
}
