<?php
/**
 * Admin Area Handler
 *
 * @package ChurchTools_Suite
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/ui/class-churchtools-suite-admin-screen.php';

class ChurchTools_Suite_Admin {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Initialize admin area
	 */
	public function __construct( string $version ) {
		$this->version = $version;
	}

	/** Retired custom installer: WordPress owns all manual installation paths. */
	public function ajax_run_update() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );
		wp_send_json_error( array( 'message' => __( 'Bitte Updates ausschließlich über die WordPress-Plugin-Verwaltung installieren.', 'churchtools-suite' ) ) );
	}

	/** Legacy check button remains a check, with the stronger WordPress update capability. */
	public function ajax_manual_update() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/update/class-churchtools-suite-update-service.php';
		$result = ChurchTools_Suite_Update_Service::check( true );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	/**
	 * Enqueue admin styles and scripts.
	 *
	 * @since 0.6.1.0 Always load (no conditional check).
	 * @since 0.6.1.5 Load public CSS first, then admin CSS (correct order).
	 * @since 1.4.0.0 Design system CSS (admin-ui.css) and the shell script; the jQuery based
	 *                admin-menu flyout is gone because the in-page rail replaces it.
	 */
	public function enqueue_styles() {
		if ( ! ChurchTools_Suite_Admin_Screen::is_plugin_screen() ) {
			return;
		}

		// The page shell colours the whole screen area, so the body class must be known before
		// the markup is sent.
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );

		wp_enqueue_style( 'dashicons' );

		// Load public CSS first (for demos in the admin area).
		wp_enqueue_style(
			'churchtools-suite-public',
			CHURCHTOOLS_SUITE_URL . 'assets/css/churchtools-suite-public.css',
			array(),
			$this->version
		);

		// Legacy admin styles (kept for backwards compatible view markup).
		wp_enqueue_style(
			'churchtools-suite-admin',
			CHURCHTOOLS_SUITE_URL . 'assets/css/churchtools-suite-admin.css',
			array( 'churchtools-suite-public' ),
			$this->version
		);

		// Design system: loads last so it wins over the legacy rules.
		wp_enqueue_style(
			'churchtools-suite-admin-ui',
			CHURCHTOOLS_SUITE_URL . 'assets/css/churchtools-suite-admin-ui.css',
			array( 'churchtools-suite-admin' ),
			$this->version
		);

		// Main admin script (jQuery based, drives the sync/selection/log interactions).
		wp_enqueue_script(
			'churchtools-suite-admin',
			CHURCHTOOLS_SUITE_URL . 'assets/js/churchtools-suite-admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		// Page shell: rail, colour scheme, global sync action.
		wp_enqueue_script(
			'churchtools-suite-admin-ui',
			CHURCHTOOLS_SUITE_URL . 'assets/js/churchtools-suite-admin-ui.js',
			array( 'churchtools-suite-admin' ),
			$this->version,
			true
		);

		$this->localize_admin_config();
	}

	/**
	 * Pass the admin config to the scripts (ajax endpoint, nonce, deep links).
	 *
	 * Registering the object through wp_add_inline_script() once keeps the inline data free of
	 * duplicates when the assets are enqueued more than once in a request.
	 *
	 * @since 1.4.0.0
	 */
	private function localize_admin_config(): void {
		$config = array(
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( ChurchTools_Suite_Admin_Screen::NONCE ),
			'version'          => $this->version,
			'documentationUrl' => ChurchTools_Suite_Admin_Screen::DOCUMENTATION_URL,
			'urls'             => array(
				'events' => admin_url( 'admin.php?page=' . ChurchTools_Suite_Admin_Screen::TOP_LEVEL . '&tab=data&subtab=events' ),
				'posts'  => admin_url( 'admin.php?page=' . ChurchTools_Suite_Admin_Screen::TOP_LEVEL . '&tab=data&subtab=posts' ),
			),
			'postsSyncActive'  => class_exists( 'ChurchTools_Suite_Posts_Sync' ) ? 1 : 0,
		);

		$inline = 'window.churchtoolsSuite = ' . wp_json_encode( $config ) . ';';

		// enqueue_styles() may run more than once per request; keep a single config statement.
		$existing = (array) wp_scripts()->get_data( 'churchtools-suite-admin', 'before' );
		foreach ( $existing as $cts_index => $cts_existing ) {
			if ( false !== strpos( (string) $cts_existing, 'window.churchtoolsSuite =' ) ) {
				$existing[ $cts_index ] = $inline;
				wp_scripts()->add_data( 'churchtools-suite-admin', 'before', $existing );
				return;
			}
		}

		wp_add_inline_script( 'churchtools-suite-admin', $inline, 'before' );
	}

	/**
	 * Body class of the plugin screens (used for the full-bleed shell and the dark scheme).
	 *
	 * @param string $classes Existing body classes.
	 *
	 * @return string
	 * @since 1.4.0.0
	 */
	public function admin_body_class( $classes ) {
		return trim( (string) $classes . ' cts-admin-page' );
	}

	/**
	 * Add the plugin menu and its submenu entries.
	 *
	 * The entries are derived from the screen registry; the jQuery based flyout of earlier
	 * versions is gone.
	 *
	 * @since 1.4.0.0 Registry based menu.
	 */
	public function add_plugin_admin_menu() {
		ChurchTools_Suite_Admin_Screen::register_defaults();

		ChurchTools_Suite_Admin_Screen::set_menu_hook(
			(string) add_menu_page(
				__( 'ChurchTools Integration', 'churchtools-suite' ),
				__( 'ChurchTools Integration', 'churchtools-suite' ),
				ChurchTools_Suite_Admin_Screen::CAPABILITY,
				ChurchTools_Suite_Admin_Screen::TOP_LEVEL,
				array( $this, 'display_admin_page' ),
				'dashicons-calendar-alt',
				30
			)
		);

		// `add_submenu_page()` with the parent slug as its own slug only appends to an existing
		// entry, so the first entry is added explicitly (position 1, shown as "Dashboard").
		$sections = ChurchTools_Suite_Admin_Screen::sections();
		if ( isset( $sections['dashboard'] ) ) {
			$dashboard_label = (string) $sections['dashboard']['title'];
			add_submenu_page(
				ChurchTools_Suite_Admin_Screen::TOP_LEVEL,
				$dashboard_label,
				$dashboard_label,
				ChurchTools_Suite_Admin_Screen::CAPABILITY,
				ChurchTools_Suite_Admin_Screen::TOP_LEVEL,
				array( $this, 'display_admin_page' ),
				1
			);
		}

		// Everything else that deserves its own wp-admin entry is marked with `sub_menu`.
		foreach ( $sections as $slug => $screen ) {
			if ( empty( $screen['sub_menu'] ) || 'dashboard' === $slug || ! ChurchTools_Suite_Admin_Screen::is_visible( $screen ) ) {
				continue;
			}
			$label = (string) $screen['title'];
			add_submenu_page(
				ChurchTools_Suite_Admin_Screen::TOP_LEVEL,
				$label,
				$label,
				ChurchTools_Suite_Admin_Screen::CAPABILITY,
				ChurchTools_Suite_Admin_Screen::TOP_LEVEL . '-' . $slug,
				array( $this, 'display_admin_page' )
			);
		}

		// Keep existing bookmarks routable even when no wp-admin submenu is shown.
		foreach ( array( 'data', 'overviews', 'addons', 'disclaimer' ) as $alias ) {
			add_submenu_page(
				'',
				__( 'ChurchTools Integration', 'churchtools-suite' ),
				__( 'ChurchTools Integration', 'churchtools-suite' ),
				ChurchTools_Suite_Admin_Screen::CAPABILITY,
				ChurchTools_Suite_Admin_Screen::TOP_LEVEL . '-' . $alias,
				array( $this, 'display_admin_page' )
			);
		}

		// Redirect before admin-header.php sends output.
		$docs_hook = add_submenu_page(
			ChurchTools_Suite_Admin_Screen::TOP_LEVEL,
			__( 'Dokumentation', 'churchtools-suite' ),
			__( 'Dokumentation', 'churchtools-suite' ),
			ChurchTools_Suite_Admin_Screen::CAPABILITY,
			ChurchTools_Suite_Admin_Screen::TOP_LEVEL . '-docs',
			array( $this, 'redirect_to_documentation' )
		);
		if ( is_string( $docs_hook ) && '' !== $docs_hook ) {
			add_action( 'load-' . $docs_hook, array( $this, 'redirect_to_documentation' ) );
		}
	}

	/**
	 * Render any plugin screen through the page shell.
	 *
	 * @since 1.4.0.0 Replaces the tab switch of admin-page.php.
	 */
	public function display_admin_page() {
		if ( ! current_user_can( ChurchTools_Suite_Admin_Screen::CAPABILITY ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'churchtools-suite' ) );
		}
		ChurchTools_Suite_Admin_Screen::render_page();
	}

	/**
	 * Show one-time notice after view-ID migration.
	 *
	 * @since 1.1.4.6
	 */
	public function display_view_migration_notice(): void {
		if ( ! current_user_can( 'manage_churchtools_suite' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && strpos( (string) $screen->id, 'churchtools-suite' ) === false ) {
			return;
		}

		$payload = get_option( 'churchtools_suite_view_migration_notice', null );
		if ( ! is_array( $payload ) ) {
			return;
		}

		$gutenberg = isset( $payload['gutenberg'] ) ? (int) $payload['gutenberg'] : 0;
		$elementor = isset( $payload['elementor'] ) ? (int) $payload['elementor'] : 0;

		if ( $gutenberg <= 0 && $elementor <= 0 ) {
			delete_option( 'churchtools_suite_view_migration_notice' );
			return;
		}

		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>' . esc_html__( 'ChurchTools Integration Suite: Views migriert.', 'churchtools-suite' ) . '</strong><br>';
		echo esc_html__( 'Gutenberg-Blöcke aktualisiert:', 'churchtools-suite' ) . ' ' . esc_html( (string) $gutenberg ) . ' · ';
		echo esc_html__( 'Elementor-Widgets aktualisiert:', 'churchtools-suite' ) . ' ' . esc_html( (string) $elementor ) . '</p>';
		echo '</div>';

		delete_option( 'churchtools_suite_view_migration_notice' );
	}

	/**
	 * Handle feedback consent form and optional automatic mail send.
	 *
	 * @since 1.1.4.12
	 */
	public function handle_feedback_submit(): void {
		if ( ! current_user_can( 'manage_churchtools_suite' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'churchtools-suite' ) );
		}

		check_admin_referer( 'cts_feedback_submit', 'cts_feedback_nonce' );

		$choice          = isset( $_POST['cts_feedback_choice'] ) ? sanitize_key( (string) $_POST['cts_feedback_choice'] ) : '';
		$selected_stages = array();
		if ( isset( $_POST['cts_feedback_stage'] ) && is_array( $_POST['cts_feedback_stage'] ) ) {
			$selected_stages = array_map( 'sanitize_text_field', wp_unslash( $_POST['cts_feedback_stage'] ) );
		}
		$allowed_stages  = array( 'installiert', 'getestet', 'genutzt' );
		$selected_stages = array_values( array_intersect( $selected_stages, $allowed_stages ) );

		$redirect_url = admin_url( 'admin.php?page=churchtools-suite' );
		if ( isset( $_POST['cts_feedback_redirect'] ) ) {
			$raw_redirect = esc_url_raw( wp_unslash( (string) $_POST['cts_feedback_redirect'] ) );
			if ( ! empty( $raw_redirect ) ) {
				$redirect_url = $raw_redirect;
			}
		}

		if ( 'skip' === $choice ) {
			update_option(
				'churchtools_suite_feedback_status',
				array(
					'status'     => 'declined',
					'updated_at' => current_time( 'mysql' ),
				),
				false
			);
			set_transient( 'churchtools_suite_feedback_flash', 'declined', 120 );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( 'send' !== $choice ) {
			set_transient( 'churchtools_suite_feedback_flash', 'invalid', 120 );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$to      = 'plugin@feg-aschaffenburg.de';
		$subject = sprintf( 'ChurchTools Integration Suite Feedback (%s)', wp_parse_url( home_url(), PHP_URL_HOST ) );

		$stage_text = ! empty( $selected_stages ) ? implode( ', ', $selected_stages ) : 'nicht angegeben';
		$body_lines = array(
			'Neue Rückmeldung zur Plugin-Nutzung',
			'',
			'Site URL: ' . site_url(),
			'Home URL: ' . home_url(),
			'Blogname: ' . get_bloginfo( 'name' ),
			'WP-Version: ' . get_bloginfo( 'version' ),
			'Plugin-Version: ' . CHURCHTOOLS_SUITE_VERSION,
			'Status: ' . $stage_text,
			'Zeitpunkt: ' . current_time( 'mysql' ),
		);
		$body       = implode( "\n", $body_lines );

		$sent = wp_mail( $to, $subject, $body );

		if ( $sent ) {
			update_option(
				'churchtools_suite_feedback_status',
				array(
					'status'  => 'sent',
					'stages'  => $selected_stages,
					'sent_at' => current_time( 'mysql' ),
				),
				false
			);
			set_transient( 'churchtools_suite_feedback_flash', 'sent', 120 );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		// User has answered "send"; do not ask again even if sending fails.
		update_option(
			'churchtools_suite_feedback_status',
			array(
				'status'       => 'error',
				'stages'       => $selected_stages,
				'attempted_at' => current_time( 'mysql' ),
			),
			false
		);

		set_transient( 'churchtools_suite_feedback_flash', 'error', 120 );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * AJAX Handler: Cleanup Legacy Cronjobs (v1.0.6.0)
	 */
	public function ajax_cleanup_cronjobs() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_churchtools_suite' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ) ) );
			return;
		}

		// Legacy cronjob patterns to remove.
		$legacy_hooks = array(
			'puc_cron_check_updates-KN-Churchtoolplugin',
			'puc_cron_check_updates-churchtools-suite',
		);

		$removed   = array();
		$not_found = array();

		foreach ( $legacy_hooks as $hook ) {
			if ( wp_next_scheduled( $hook ) ) {
				wp_clear_scheduled_hook( $hook );
				$removed[] = $hook;
			} else {
				$not_found[] = $hook;
			}
		}

		// Log cleanup.
		if ( ! empty( $removed ) && class_exists( 'ChurchTools_Suite_Logger' ) ) {
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-logger.php';
			ChurchTools_Suite_Logger::info(
				'cronjob_cleanup',
				'Verwaiste Cronjobs entfernt',
				array(
					'removed' => $removed,
					'count'   => count( $removed ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message'   => sprintf(
					/* translators: %d: Number of removed orphaned cron jobs. */                    __( '%d verwaiste Cronjobs entfernt.', 'churchtools-suite' ),
					count( $removed )
				),
				'removed'   => $removed,
				'not_found' => $not_found,
			)
		);
	}

	/**
	 * Check if current page is a plugin page
	 */
	private function is_plugin_page(): bool {
		$screen = get_current_screen();
		return $screen && strpos( $screen->id, 'churchtools-suite' ) !== false;
	}

	/**
	 * Register AJAX handlers
	 */
	public function register_ajax_handlers() {
		// Main AJAX handlers for Settings, Calendars, Events, Dienste.
		add_action( 'wp_ajax_cts_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_cts_sync_calendars', array( $this, 'ajax_sync_calendars' ) );
		add_action( 'wp_ajax_cts_save_calendar_selection', array( $this, 'ajax_save_calendar_selection' ) );
		add_action( 'wp_ajax_cts_sync_service_groups', array( $this, 'ajax_sync_service_groups' ) );
		add_action( 'wp_ajax_cts_save_service_group_selection', array( $this, 'ajax_save_service_group_selection' ) );
		add_action( 'wp_ajax_cts_sync_services', array( $this, 'ajax_sync_services' ) );
		add_action( 'wp_ajax_cts_save_service_selection', array( $this, 'ajax_save_service_selection' ) );
		add_action( 'wp_ajax_cts_sync_events', array( $this, 'ajax_sync_events' ) );
		add_action( 'wp_ajax_cts_trigger_manual_sync', array( $this, 'ajax_trigger_manual_sync' ) );
		add_action( 'wp_ajax_cts_manual_update', array( $this, 'ajax_manual_update' ) );
		add_action( 'wp_ajax_cts_run_update', array( $this, 'ajax_run_update' ) );
		add_action( 'wp_ajax_cts_trigger_keepalive', array( $this, 'ajax_trigger_keepalive' ) );
		// Simple ping endpoint to verify AJAX/JSON pipeline.
		add_action( 'wp_ajax_cts_keepalive_ping', array( $this, 'ajax_keepalive_ping' ) );
		add_action( 'wp_ajax_cts_reload_logs', array( $this, 'ajax_reload_logs' ) );
		add_action( 'wp_ajax_cts_export_logs', array( $this, 'ajax_export_logs' ) );
		add_action( 'wp_ajax_cts_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_cts_clear_block_logs', array( $this, 'ajax_clear_block_logs' ) );
		add_action( 'wp_ajax_cts_save_preset', array( $this, 'ajax_save_preset' ) );
		add_action( 'wp_ajax_cts_update_preset', array( $this, 'ajax_update_preset' ) );
		add_action( 'wp_ajax_cts_delete_preset', array( $this, 'ajax_delete_preset' ) );
		add_action( 'wp_ajax_cts_get_calendars', array( $this, 'ajax_get_calendars' ) );
		// AJAX data lists (server-side filtering/pagination).
		add_action( 'wp_ajax_cts_fetch_events_list', array( $this, 'ajax_fetch_events_list' ) );
		add_action( 'wp_ajax_cts_delete_event', array( $this, 'ajax_delete_event' ) );
		add_action( 'wp_ajax_cts_fetch_imported_services_list', array( $this, 'ajax_fetch_imported_services_list' ) );

		// Reset & Cleanup (v0.7.2.4).
		add_action( 'wp_ajax_cts_clear_events', array( $this, 'ajax_clear_events' ) );
		add_action( 'wp_ajax_cts_clear_calendars', array( $this, 'ajax_clear_calendars' ) );
		add_action( 'wp_ajax_cts_clear_services', array( $this, 'ajax_clear_services' ) );
		add_action( 'wp_ajax_cts_cleanup_cronjobs', array( $this, 'ajax_cleanup_cronjobs' ) ); // v1.0.6.0.

		// Frontend AJAX (für alle Nutzer, auch nicht-eingeloggte) (v0.10.2.7).
		add_action( 'wp_ajax_cts_load_calendar_month', array( $this, 'ajax_load_calendar_month' ) );
		add_action( 'wp_ajax_nopriv_cts_load_calendar_month', array( $this, 'ajax_load_calendar_month' ) );
		add_action( 'wp_ajax_cts_clear_sync_history', array( $this, 'ajax_clear_sync_history' ) );
		add_action( 'wp_ajax_cts_full_reset', array( $this, 'ajax_full_reset' ) );
		add_action( 'wp_ajax_cts_complete_reset', array( $this, 'ajax_complete_reset' ) ); // v0.10.1.4.
		add_action( 'wp_ajax_cts_rebuild_database', array( $this, 'ajax_rebuild_database' ) ); // v0.9.0.1.

		// Public AJAX (for frontend modal).
		add_action( 'wp_ajax_cts_get_modal_template', array( $this, 'ajax_get_modal_template' ) );
		add_action( 'wp_ajax_nopriv_cts_get_modal_template', array( $this, 'ajax_get_modal_template' ) );
		add_action( 'wp_ajax_cts_get_event_details', array( $this, 'ajax_get_event_details' ) );
		add_action( 'wp_ajax_nopriv_cts_get_event_details', array( $this, 'ajax_get_event_details' ) );

		// Addon Management (v1.0.9.1).
		add_action( 'wp_ajax_cts_install_addon', array( $this, 'ajax_install_addon' ) );
		add_action( 'wp_ajax_cts_update_addon', array( $this, 'ajax_update_addon' ) ); // v1.1.0.1.
		add_action( 'wp_ajax_cts_clear_addon_update_cache', array( $this, 'ajax_clear_addon_update_cache' ) ); // v1.1.0.1.
	}

	/**
	 * AJAX Handler: Test ChurchTools Connection
	 */
	public function ajax_test_connection() {
		// Check nonce.
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Permission: configure_churchtools_suite (Settings).
		if ( ! current_user_can( 'configure_churchtools_suite' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ) ) );
			return;
		}

		try {
			// Load CT Client.
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-ct-client.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-logger.php';

			ChurchTools_Suite_Logger::info( 'test_connection', 'Verbindungstest gestartet' );

			$credentials = null;
			if ( isset( $_POST['ct_tenant'] ) ) {
				require_once CHURCHTOOLS_SUITE_PATH . 'includes/functions/connection-settings.php';
				$credentials = churchtools_suite_connection_form_values( $_POST );
				if ( is_wp_error( $credentials ) ) {
					wp_send_json_error( array( 'message' => $credentials->get_error_message() ) );
					return;
				}
			}
			$client = new ChurchTools_Suite_CT_Client( $credentials );
			$result = $client->test_connection();

			if ( $result['success'] ) {
				ChurchTools_Suite_Logger::info(
					'test_connection',
					'Verbindungstest erfolgreich',
					array(
						'user' => $result['user_info'] ?? 'unknown',
					)
				);

				wp_send_json_success(
					array(
						'message'   => $result['message'],
						'user_info' => $result['user_info'] ?? null,
					)
				);
			} else {
				$error_msg = $result['message'] ?? 'Unbekannter Fehler';
				ChurchTools_Suite_Logger::error(
					'test_connection',
					'Verbindungstest fehlgeschlagen',
					array(
						'message'       => $error_msg,
						'error_code'    => $result['error_code'] ?? null,
						'error_details' => $result['error_details'] ?? null,
					)
				);

				wp_send_json_error(
					array(
						'message'       => $error_msg,
						'error_code'    => $result['error_code'] ?? null,
						'error_details' => $result['error_details'] ?? null,
					)
				);
			}
		} catch ( Exception $e ) {
			ChurchTools_Suite_Logger::error(
				'test_connection',
				'Exception beim Verbindungstest',
				array(
					'exception' => $e->getMessage(),
					'trace'     => $e->getTraceAsString(),
				)
			);

			wp_send_json_error(
				array(
					'message'       => __( 'Fehler beim Testen der Verbindung.', 'churchtools-suite' ),
					'error_details' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX Handler: Keepalive ping (test endpoint)
	 * Returns simple JSON to validate that admin-ajax.php returns JSON correctly.
	 */
	public function ajax_keepalive_ping() {
		// Use non-fatal nonce check and return JSON.
		$ok = check_ajax_referer( 'churchtools_suite_admin', 'nonce', false );
		if ( false === $ok ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
			return;
		}
		// Permission: configure_churchtools_suite (Settings).
		if ( ! current_user_can( 'configure_churchtools_suite' ) ) {
			wp_send_json_error( array( 'message' => 'No permission' ) );
			return;
		}
		wp_send_json_success( array( 'message' => 'pong' ) );
	}

	/**
	 * AJAX Handler: Sync Calendars from ChurchTools
	 */
	public function ajax_sync_calendars() {
		// Check nonce.
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Check permissions: manage_churchtools_calendars (Calendars).
		if ( ! current_user_can( 'manage_churchtools_calendars' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ),
				)
			);
			return;
		}

		// Rate Limiting (v0.7.0.2).
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-rate-limiter.php';

		$user_id    = get_current_user_id();
		$identifier = 'user_' . $user_id;

		if ( ! ChurchTools_Suite_Rate_Limiter::is_allowed( $identifier, 'ajax' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Zu viele Anfragen. Bitte warten Sie einen Moment.', 'churchtools-suite' ),
				)
			);
		}

		try {
			// Load dependencies.
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-ct-client.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-calendars-repository.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/services/class-churchtools-suite-calendar-sync-service.php';

			$client         = new ChurchTools_Suite_CT_Client();
			$calendars_repo = new ChurchTools_Suite_Calendars_Repository();
			$sync_service   = new ChurchTools_Suite_Calendar_Sync_Service( $client, $calendars_repo );

			$result = $sync_service->sync_calendars();

			if ( is_wp_error( $result ) ) {
				wp_send_json_error(
					array(
						'message' => $result->get_error_message(),
					)
				);
				return;
			}

			$source            = isset( $result['source'] ) && is_array( $result['source'] ) ? $result['source'] : array();
			$api_count         = isset( $source['api_calendars'] ) ? (int) $source['api_calendars'] : (int) $result['total'];
			$events_discovered = isset( $source['events_discovered'] ) ? (int) $source['events_discovered'] : 0;
			$merged_total      = isset( $source['merged_total'] ) ? (int) $source['merged_total'] : (int) $result['total'];

			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: 1: Calendars found, 2: New calendars, 3: Updated calendars, 4: Errors, 5: Calendars from the calendars endpoint, 6: Calendars from events, 7: Total calendars. */                        __( 'Synchronisation erfolgreich! %1$d Kalender gefunden, %2$d neu, %3$d aktualisiert, %4$d Fehler. Quelle: /calendars=%5$d, aus /events ergänzt=%6$d, gesamt=%7$d.', 'churchtools-suite' ),
						$merged_total,
						$result['inserted'],
						$result['updated'],
						$result['errors'],
						$api_count,
						$events_discovered,
						$merged_total
					),
					'stats'   => $result,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Fehler: ', 'churchtools-suite' ) . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX Handler: Save Calendar Selection
	 */
	public function ajax_save_calendar_selection() {
		// Check nonce.
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Check permissions: manage_churchtools_calendars (Calendars).
		if ( ! current_user_can( 'manage_churchtools_calendars' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ),
				)
			);
			return;
		}

		try {
			$calendar_action = isset( $_POST['calendar_action'] ) ? sanitize_key( wp_unslash( $_POST['calendar_action'] ) ) : 'save_selection';
			$allowed_actions = array( 'save_selection', 'activate_selected', 'deactivate_selected', 'delete_deactivated_selected' );
			if ( ! in_array( $calendar_action, $allowed_actions, true ) ) {
				$calendar_action = 'save_selection';
			}

			// Get selected calendar IDs.
			$selected_ids = isset( $_POST['selected_ids'] ) ? array_map( 'intval', $_POST['selected_ids'] ) : array();

			// Capture calendar fallback images (calendar_id => attachment_id).
			$calendar_images = array();
			if ( isset( $_POST['calendar_images'] ) && is_array( $_POST['calendar_images'] ) ) {
				foreach ( array_map( 'absint', wp_unslash( $_POST['calendar_images'] ) ) as $calendar_id => $attachment_id ) {
					$calendar_id_sanitized = sanitize_text_field( wp_unslash( $calendar_id ) );
					$attachment_id_int     = absint( $attachment_id );
					if ( $calendar_id_sanitized && $attachment_id_int > 0 ) {
						$calendar_images[ $calendar_id_sanitized ] = $attachment_id_int;
					}
				}
			}

			// Load repository.
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-calendars-repository.php';

			$calendars_repo = new ChurchTools_Suite_Calendars_Repository();

			if ( 'activate_selected' === $calendar_action ) {
				if ( empty( $selected_ids ) ) {
					wp_send_json_error(
						array(
							'message' => __( 'Bitte mindestens einen Kalender markieren.', 'churchtools-suite' ),
						)
					);
					return;
				}

				$activated_count = $calendars_repo->select_by_ids( $selected_ids );
				wp_send_json_success(
					array(
						'message'         => sprintf(
							/* translators: %d: Number of activated calendars. */                            __( '%d Kalender aktiviert.', 'churchtools-suite' ),
							$activated_count
						),
						'activated_count' => $activated_count,
					)
				);
				return;
			}

			if ( 'deactivate_selected' === $calendar_action ) {
				if ( empty( $selected_ids ) ) {
					wp_send_json_error(
						array(
							'message' => __( 'Bitte mindestens einen Kalender markieren.', 'churchtools-suite' ),
						)
					);
					return;
				}

				$deactivated_count = $calendars_repo->deselect_by_ids( $selected_ids );
				wp_send_json_success(
					array(
						'message'           => sprintf(
							/* translators: %d: Number of deactivated calendars. */                            __( '%d Kalender deaktiviert.', 'churchtools-suite' ),
							$deactivated_count
						),
						'deactivated_count' => $deactivated_count,
					)
				);
				return;
			}

			if ( 'delete_deactivated_selected' === $calendar_action ) {
				if ( empty( $selected_ids ) ) {
					wp_send_json_error(
						array(
							'message' => __( 'Bitte mindestens einen Kalender markieren.', 'churchtools-suite' ),
						)
					);
					return;
				}

				$delete_result = $calendars_repo->delete_deactivated_by_ids( $selected_ids );
				wp_send_json_success(
					array(
						'message'              => sprintf(
							/* translators: 1: Deleted calendars, 2: Active calendars retained. */                            __( '%1$d Kalender gelöscht. %2$d aktive Kalender wurden aus Sicherheitsgründen nicht gelöscht.', 'churchtools-suite' ),
							(int) ( $delete_result['deleted'] ?? 0 ),
							(int) ( $delete_result['blocked_active'] ?? 0 )
						),
						'deleted_count'        => (int) ( $delete_result['deleted'] ?? 0 ),
						'blocked_active_count' => (int) ( $delete_result['blocked_active'] ?? 0 ),
					)
				);
				return;
			}

			$result = $calendars_repo->update_selected( $selected_ids );

			if ( ! $result ) {
				wp_send_json_error(
					array(
						'message' => __( 'Fehler beim Speichern der Auswahl.', 'churchtools-suite' ),
					)
				);
				return;
			}

			// Update calendar_image_id in table (v0.9.9.58).
			foreach ( $calendar_images as $calendar_id => $attachment_id ) {
				$calendars_repo->update_calendar_image_by_calendar_id( $calendar_id, $attachment_id );
			}

			// Persist fallback images option (keep for backward compatibility).
			update_option( 'churchtools_suite_calendar_images', $calendar_images, false );

			$selected_count = count( $selected_ids );
			$total_count    = $calendars_repo->count();

			wp_send_json_success(
				array(
					'message'        => sprintf(
						/* translators: 1: Number of selected calendars, 2: Total calendars. */                        __( 'Auswahl gespeichert: %1$d von %2$d Kalendern ausgewählt.', 'churchtools-suite' ),
						$selected_count,
						$total_count
					),
					'selected_count' => $selected_count,
					'total_count'    => $total_count,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Fehler: ', 'churchtools-suite' ) . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX Handler: Sync Service Groups from ChurchTools
	 */
	public function ajax_sync_service_groups() {
		// Check nonce.
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Check permissions: manage_churchtools_services (Dienste).
		if ( ! current_user_can( 'manage_churchtools_services' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ),
				)
			);
			return;
		}

		try {
			// Load dependencies.
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-ct-client.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-services-repository.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-service-groups-repository.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/services/class-churchtools-suite-service-sync-service.php';

			// Initialize services.
			$ct_client           = new ChurchTools_Suite_CT_Client();
			$services_repo       = new ChurchTools_Suite_Services_Repository();
			$service_groups_repo = new ChurchTools_Suite_Service_Groups_Repository();
			$sync_service        = new ChurchTools_Suite_Service_Sync_Service( $ct_client, $services_repo, $service_groups_repo );

			// Run sync.
			$result = $sync_service->sync_service_groups();

			if ( is_wp_error( $result ) ) {
				$error_data    = $result->get_error_data();
				$error_message = $result->get_error_message();

				// Add URL to error message if available.
				if ( isset( $error_data['url'] ) ) {
					$error_message .= ' (URL: ' . $error_data['url'] . ')';
				}

				wp_send_json_error(
					array(
						'message'    => $error_message,
						'error_data' => $error_data,
					)
				);
				return;
			}

			$message = sprintf(
				/* translators: 1: Service groups found, 2: New service groups, 3: Updated service groups. */                __( 'Synchronisation erfolgreich! %1$d Dienstgruppen gefunden. %2$d neu, %3$d aktualisiert.', 'churchtools-suite' ),
				$result['groups_found'],
				$result['groups_inserted'],
				$result['groups_updated']
			);

			wp_send_json_success(
				array(
					'message' => $message,
					'stats'   => $result,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Fehler: ', 'churchtools-suite' ) . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX Handler: Save Service Group Selection
	 */
	public function ajax_save_service_group_selection() {
		// Check nonce.
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Check permissions: manage_churchtools_services (Dienste).
		if ( ! current_user_can( 'manage_churchtools_services' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ),
				)
			);
			return;
		}

		try {
			// Get selected service group IDs.
			$selected_ids = isset( $_POST['selected_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_ids'] ) ) : array();

			// Load repository.
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-service-groups-repository.php';

			$service_groups_repo = new ChurchTools_Suite_Service_Groups_Repository();
			$result              = $service_groups_repo->update_selection( $selected_ids );

			if ( ! $result ) {
				wp_send_json_error(
					array(
						'message' => __( 'Fehler beim Speichern.', 'churchtools-suite' ),
					)
				);
				return;
			}

			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %d: Number of selected service groups. */                        __( 'Auswahl gespeichert! %d Dienstgruppen ausgewählt.', 'churchtools-suite' ),
						count( $selected_ids )
					),
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Fehler: ', 'churchtools-suite' ) . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX Handler: Sync Dienste from ChurchTools
	 */
	public function ajax_sync_services() {
		// Check nonce.
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Check permissions: manage_churchtools_services (Dienste).
		if ( ! current_user_can( 'manage_churchtools_services' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ),
				)
			);
			return;
		}

		try {
			// Load dependencies.
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-ct-client.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-services-repository.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-service-groups-repository.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/services/class-churchtools-suite-service-sync-service.php';

			// Initialize services.
			$ct_client           = new ChurchTools_Suite_CT_Client();
			$services_repo       = new ChurchTools_Suite_Services_Repository();
			$service_groups_repo = new ChurchTools_Suite_Service_Groups_Repository();
			$sync_service        = new ChurchTools_Suite_Service_Sync_Service( $ct_client, $services_repo, $service_groups_repo );

			// Run sync.
			$result = $sync_service->sync_services();

			if ( is_wp_error( $result ) ) {
				wp_send_json_error(
					array(
						'message' => $result->get_error_message(),
					)
				);
				return;
			}

			$message = sprintf(
				/* translators: 1: Dienste found, 2: New services, 3: Updated services. */                __( 'Synchronisation erfolgreich! %1$d Dienste gefunden. %2$d neu, %3$d aktualisiert.', 'churchtools-suite' ),
				$result['services_found'],
				$result['services_inserted'],
				$result['services_updated']
			);

			wp_send_json_success(
				array(
					'message' => $message,
					'stats'   => $result,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Fehler: ', 'churchtools-suite' ) . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX Handler: Save Service Selection
	 */
	public function ajax_save_service_selection() {
		// Check nonce.
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Check permissions: manage_churchtools_services (Dienste).
		if ( ! current_user_can( 'manage_churchtools_services' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ),
				)
			);
			return;
		}

		try {
			// Get selected service IDs.
			$selected_ids = isset( $_POST['selected_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_ids'] ) ) : array();

			// Load repository.
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-services-repository.php';

			$services_repo = new ChurchTools_Suite_Services_Repository();
			$result        = $services_repo->update_selection( $selected_ids );

			if ( ! $result ) {
				wp_send_json_error(
					array(
						'message' => __( 'Fehler beim Speichern der Auswahl.', 'churchtools-suite' ),
					)
				);
				return;
			}

			$selected_count = count( $selected_ids );
			$total_count    = $services_repo->get_total_count();

			wp_send_json_success(
				array(
					'message'        => sprintf(
						/* translators: 1: Number of selected services, 2: Total services. */                        __( 'Auswahl gespeichert: %1$d von %2$d Dienste ausgewählt.', 'churchtools-suite' ),
						$selected_count,
						$total_count
					),
					'selected_count' => $selected_count,
					'total_count'    => $total_count,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Fehler: ', 'churchtools-suite' ) . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX Handler: Sync Events from ChurchTools
	 */
	public function ajax_sync_events() {
		// v0.10.4.23: Clean any output before JSON (fixes "keine gültige JSON-Antwort").
		if ( ob_get_level() === 0 ) {
			ob_start();
		}

		// v0.7.2.6: Register shutdown handler to catch fatal errors.
		register_shutdown_function(
			function () {
				$error = error_get_last();
				if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
						// Log to plugin log file (v0.7.2.6).
						require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-logger.php';
						ChurchTools_Suite_Logger::critical(
							'fatal_error',
							sprintf(
								'PHP Fatal Error während Event-Sync: %s',
								$error['message']
							),
							array(
								'file' => $error['file'],
								'line' => $error['line'],
								'type' => $error['type'],
							)
						);

					if ( ! headers_sent() ) {
						header( 'Content-Type: application/json; charset=utf-8' );
						http_response_code( 200 );
						echo wp_json_encode(
							array(
								'success' => false,
								'data'    => array(
									'message' => sprintf(
										'PHP Fatal Error: %s in %s (Zeile %d). Details im Debug-Tab unter "Logs".',
										$error['message'],
										basename( $error['file'] ),
										$error['line']
									),
								),
							)
						);
					}
						exit;
				}
			}
		);

		// Check nonce.
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Check permissions: sync_churchtools_events (Event Sync).
		if ( ! current_user_can( 'sync_churchtools_events' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ),
				)
			);
			return;
		}

		// Cleanup stuck syncs (older than 5 minutes).
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-sync-history-repository.php';
		$history_repo = new ChurchTools_Suite_Sync_History_Repository();
		$history_repo->cleanup_stuck_syncs( 5 );

		// Create sync history entry.
		$sync_id = $history_repo->create_sync_entry( 'manual', current_time( 'mysql' ) );

		try {
			// Load dependencies.
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-ct-client.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-calendars-repository.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-events-repository.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-event-services-repository.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-services-repository.php';
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/services/class-churchtools-suite-event-sync-service.php';

			$client              = new ChurchTools_Suite_CT_Client();
			$calendars_repo      = new ChurchTools_Suite_Calendars_Repository();
			$events_repo         = new ChurchTools_Suite_Events_Repository();
			$event_services_repo = new ChurchTools_Suite_Event_Services_Repository();
			$services_repo       = new ChurchTools_Suite_Services_Repository();
			$sync_service        = new ChurchTools_Suite_Event_Sync_Service( $client, $events_repo, $calendars_repo, $event_services_repo, $services_repo );

			// Optional: Custom date range from POST.
			$args = array();
			if ( isset( $_POST['from'] ) ) {
				$args['from'] = sanitize_text_field( wp_unslash( $_POST['from'] ) );
			}
			if ( isset( $_POST['to'] ) ) {
				$args['to'] = sanitize_text_field( wp_unslash( $_POST['to'] ) );
			}

			// v0.7.1.0: Force full sync option.
			if ( isset( $_POST['force_full'] ) && '1' === $_POST['force_full'] ) {
				$args['force_full'] = true;
			}

			$result = $sync_service->sync_events( $args );

			if ( is_wp_error( $result ) ) {
				// Mark sync as failed.
				if ( $sync_id ) {
					$history_repo->complete_sync( $sync_id, array(), $result->get_error_message() );
				}

				ob_end_clean(); // v0.10.4.23.
				wp_send_json_error(
					array(
						'message' => $result->get_error_message(),
					)
				);
				return;
			}

			// Posts sync triggered via Hook 'cts_do_sync_posts' (if addon is active).
			try {
				do_action( 'cts_do_sync_posts', $client, $result );
			} catch ( \Throwable $posts_sync_error ) {
				ChurchTools_Suite_Logger::warning(
					'manual_sync',
					'Posts-Sync hook failed after successful event sync',
					array( 'error' => $posts_sync_error->getMessage() )
				);
				$result['ct_posts_error']  = $posts_sync_error->getMessage();
				$result['ct_posts_errors'] = (int) ( $result['ct_posts_errors'] ?? 0 ) + 1;
			}

			// Mark sync as successful.
			if ( $sync_id ) {
				$history_repo->complete_sync( $sync_id, $result, null );
			}

			ob_end_clean(); // v0.10.4.23.
			$message = sprintf(
				/* translators: 1: Calendars processed, 2: Events found, 3: Appointments found, 4: New records, 5: Updated records, 6: Skipped records, 7: Errors. */                __( 'Synchronisation erfolgreich! %1$d Kalender verarbeitet, %2$d Events gefunden, %3$d Appointments gefunden, %4$d neu, %5$d aktualisiert, %6$d übersprungen, %7$d Fehler.', 'churchtools-suite' ),
				$result['calendars_processed'],
				$result['events_found'],
				$result['appointments_found'],
				$result['events_inserted'],
				$result['events_updated'],
				$result['events_skipped'],
				$result['errors']
			);

			if ( isset( $result['ct_posts_error'] ) ) {
				$message .= ' ' . sprintf( /* translators: %s: Post synchronization error message. */ __( 'Posts-Sync Fehler: %s', 'churchtools-suite' ), $result['ct_posts_error'] );
			} elseif ( isset( $result['ct_posts_found'] ) ) {
				$message .= ' ' . sprintf(
					/* translators: 1: Posts found, 2: New posts, 3: Updated posts, 4: Skipped posts, 5: Errors. */                    __( 'ChurchTools-Posts: %1$d gefunden, %2$d neu, %3$d aktualisiert, %4$d übersprungen, %5$d Fehler.', 'churchtools-suite' ),
					$result['ct_posts_found'] ?? 0,
					$result['ct_posts_created'] ?? 0,
					$result['ct_posts_updated'] ?? 0,
					$result['ct_posts_skipped'] ?? 0,
					$result['ct_posts_errors'] ?? 0
				);
			}

			wp_send_json_success(
				array(
					'message'   => $message,
					'stats'     => $result,
					'sync_type' => $result['sync_type'] ?? 'full', // v0.7.1.0: Pass sync type to frontend.
				)
			);
		} catch ( Exception $e ) {
			// Mark sync as failed.
			if ( $sync_id ) {
				$history_repo->complete_sync( $sync_id, array(), $e->getMessage() );
			}

			ob_end_clean(); // v0.10.4.23.
			wp_send_json_error(
				array(
					'message' => __( 'Fehler: ', 'churchtools-suite' ) . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX: Fetch events list (server-side pagination & filtering)
	 */
	public function ajax_fetch_events_list() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Permission: manage_churchtools_suite (General Admin).
		if ( ! current_user_can( 'manage_churchtools_suite' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ) ) );
			return;
		}

		global $wpdb;
		$from            = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to              = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		$calendar_filter = isset( $_POST['calendar_id'] ) ? sanitize_text_field( wp_unslash( $_POST['calendar_id'] ) ) : '';
		$page            = max( 1, (int) ( absint( wp_unslash( $_POST['paged'] ?? 1 ) ) ) );
		$limit           = 200;
		$offset          = ( $page - 1 ) * $limit;

		$prefix = $wpdb->prefix . CHURCHTOOLS_SUITE_DB_PREFIX;
		$table  = $prefix . 'events';

		$sql       = "SELECT id, event_id, appointment_id, calendar_id, title, description, event_description, appointment_description, start_datetime, end_datetime, is_all_day, location_name, address_name, address_street, address_zip, address_city, address_latitude, address_longitude, tags, status, raw_payload, last_modified, appointment_modified, created_at, updated_at FROM {$table} WHERE 1=1";
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE 1=1";
		$where     = array();
		$params    = array();

		if ( ! empty( $from ) ) {
			$where[]  = 'start_datetime >= %s';
			$params[] = $from . ' 00:00:00';
		}
		if ( ! empty( $to ) ) {
			$where[]  = 'start_datetime <= %s';
			$params[] = $to . ' 23:59:59';
		}
		if ( ! empty( $calendar_filter ) ) {
			$where[]  = 'calendar_id = %s';
			$params[] = $calendar_filter;
		}

		if ( ! empty( $where ) ) {
			$cond       = ' AND ' . implode( ' AND ', $where );
			$sql       .= $cond;
			$count_sql .= $cond;
		}

		$sql              .= ' ORDER BY start_datetime ASC LIMIT %d OFFSET %d';
		$params_with_limit = array_merge( $params, array( $limit, $offset ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled from fixed clauses and values passed through wpdb::prepare above.
		$prepared_sql = empty( $params_with_limit ) ? $sql : $wpdb->prepare( $sql, ...$params_with_limit );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled from fixed clauses and values passed through wpdb::prepare above.
		$prepared_count = empty( $params ) ? $count_sql : $wpdb->prepare( $count_sql, ...$params );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled from fixed clauses and values passed through wpdb::prepare above.
		$events = $wpdb->get_results( $prepared_sql );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled from fixed clauses and values passed through wpdb::prepare above.
		$total = (int) $wpdb->get_var( $prepared_count );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$total_pages = max( 1, ceil( $total / $limit ) );

		// Build HTML fragment (table rows + pagination).
		ob_start();
		if ( empty( $events ) ) {
			?>
			<div class="cts-empty-state"><span class="cts-empty-icon">📅</span><h3><?php esc_html_e( 'Keine Termine gefunden', 'churchtools-suite' ); ?></h3></div>
			<?php
		} else {
			?>
			<div class="cts-table-wrapper">
				<table class="cts-events-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Datum & Zeit', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Titel', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Kalender', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Ort / Adresse', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Tags', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Typ', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Dienste', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Details', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Aktionen', 'churchtools-suite' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					foreach ( $events as $event ) :
						$start_local          = get_date_from_gmt( $event->start_datetime );
						$end_local            = $event->end_datetime ? get_date_from_gmt( $event->end_datetime ) : null;
						$is_all_day           = (bool) $event->is_all_day;
						$type_label           = ! empty( $event->appointment_id ) ? __( 'Termin', 'churchtools-suite' ) : __( 'Event', 'churchtools-suite' );
						$type_icon            = ! empty( $event->appointment_id ) ? '📅' : '🎯';
						$raw                  = ! empty( $event->raw_payload ) ? json_decode( $event->raw_payload, true ) : array();
						$base                 = $raw['appointment']['base'] ?? $raw['base'] ?? $raw;
						$link                 = $base['link'] ?? '';
						$image_url            = $base['image'] ?? '';
						$is_canceled          = (bool) ( $base['isCanceled'] ?? $raw['isCanceled'] ?? false );
						$last_modified        = ! empty( $event->last_modified ) ? get_date_from_gmt( $event->last_modified ) : '';
						$appointment_modified = ! empty( $event->appointment_modified ) ? get_date_from_gmt( $event->appointment_modified ) : '';
						?>
						<tr>
							<td class="cts-event-date">
								<div class="cts-event-date-primary"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_local ) ) ); ?></div>
								<div class="cts-event-date-time">
								<?php
								if ( ! $is_all_day ) {
									echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $start_local ) ) );
									if ( $end_local ) {
										echo ' - ' . esc_html( date_i18n( get_option( 'time_format' ), strtotime( $end_local ) ) );
									}
								} else {
									esc_html_e( 'Ganztägig', 'churchtools-suite' ); }
								?>
								</div>
							</td>
							<td class="cts-event-title"><div class="cts-event-title-main"><?php echo esc_html( $event->title ); ?></div></td>
							<td class="cts-event-calendar"><span class="cts-calendar-badge"><?php echo esc_html( $event->calendar_id ); ?></span></td>
							<td class="cts-event-location">
								<?php if ( ! empty( $event->address_name ) || ! empty( $event->address_street ) ) : ?>
									<div class="cts-address-structured">
										<?php
										if ( ! empty( $event->address_name ) ) :
											?>
											<div class="cts-address-name"><strong>🏠 <?php echo esc_html( $event->address_name ); ?></strong></div><?php endif; ?>
										<?php
										if ( ! empty( $event->address_street ) ) :
											?>
											<div class="cts-address-street"><?php echo esc_html( $event->address_street ); ?></div><?php endif; ?>
									</div>
								<?php elseif ( ! empty( $event->location_name ) ) : ?>
									<span>📍 <?php echo esc_html( $event->location_name ); ?></span>
									<?php
								else :
									?>
									<span class="cts-muted">—</span><?php endif; ?>
							</td>
							<td class="cts-event-tags">
								<?php
								if ( ! empty( $event->tags ) ) {
									$tags = json_decode( $event->tags, true ); if ( is_array( $tags ) && ! empty( $tags ) ) {
										foreach ( $tags as $tag ) {
											?>
		<span class="cts-tag">🏷️ <?php echo esc_html( $tag['name'] ?? '' ); ?></span>
											<?php
										}
									} else {
										echo '<span class="cts-muted">—</span>';
									}
								} else {
									echo '<span class="cts-muted">—</span>'; }
								?>
							</td>
							<td class="cts-event-type"><span class="cts-type-badge"><?php echo esc_html( $type_icon . ' ' . $type_label ); ?></span></td>
							<td class="cts-event-status">
								<?php if ( $is_canceled ) : ?>
									<span class="cts-status-badge cts-status-canceled">⛔ <?php esc_html_e( 'Abgesagt', 'churchtools-suite' ); ?></span>
								<?php else : ?>
									<span class="cts-status-badge cts-status-active">✅ <?php esc_html_e( 'Aktiv', 'churchtools-suite' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="cts-event-services"><span class="cts-muted">—</span></td>
							<td class="cts-event-details">
								<?php if ( $link || $image_url || $event->raw_payload ) : ?>
									<div class="cts-description-section cts-meta-grid" style="margin-bottom:8px;">
										<?php
										if ( ! empty( $event->event_id ) ) :
											?>
											<div><strong>Event ID:</strong> <?php echo esc_html( $event->event_id ); ?></div><?php endif; ?>
										<?php
										if ( ! empty( $event->appointment_id ) ) :
											?>
											<div><strong>Appointment ID:</strong> <?php echo esc_html( $event->appointment_id ); ?></div><?php endif; ?>
										<div><strong>Status:</strong> <?php echo $is_canceled ? esc_html__( 'Abgesagt', 'churchtools-suite' ) : esc_html__( 'Aktiv', 'churchtools-suite' ); ?></div>
										<div><strong><?php esc_html_e( 'Start', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $event->start_datetime ); ?></div>
										<div><strong><?php esc_html_e( 'Ende', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $event->end_datetime ?: '—' ); ?></div>
										<div><strong><?php esc_html_e( 'Last Modified', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $last_modified ?: '—' ); ?></div>
										<div><strong><?php esc_html_e( 'Appointment Modified', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $appointment_modified ?: '—' ); ?></div>
									</div>
									<?php if ( $link ) : ?>
										<div class="cts-description-section" style="margin-bottom:6px;">
											<strong>🔗 <?php esc_html_e( 'Link', 'churchtools-suite' ); ?>:</strong> <a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link ); ?></a>
										</div>
									<?php endif; ?>
									<?php if ( $image_url ) : ?>
										<div class="cts-description-section cts-image-preview">
											<img src="<?php echo esc_url( $image_url ); ?>" alt="" />
										</div>
									<?php endif; ?>
									<?php if ( $event->raw_payload ) : ?>
										<div class="cts-description-section">
											<strong>🧾 <?php esc_html_e( 'Raw Payload', 'churchtools-suite' ); ?>:</strong> <?php printf( /* translators: %d: Number of characters in the JSON payload. */ esc_html__( '%d Zeichen JSON', 'churchtools-suite' ), (int) strlen( $event->raw_payload ) ); ?>
										</div>
									<?php endif; ?>
								<?php else : ?>
									<span class="cts-muted">—</span>
								<?php endif; ?>
							</td>
							<td class="cts-event-actions">
								<?php
								if ( $link ) :
									?>
									<a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Bearbeiten', 'churchtools-suite' ); ?></a><?php endif; ?>
								<button type="button" class="button-link-delete cts-delete-event" data-event-id="<?php echo esc_attr( $event->id ); ?>"><?php esc_html_e( 'Löschen', 'churchtools-suite' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
		}

		// Pagination HTML.
		if ( $total_pages > 1 ) {
				$pagination = '<div class="cts-pagination">';
			if ( $page > 1 ) {
				$pagination .= '<button data-paged="' . ( $page - 1 ) . '" class="cts-ajax-page cts-btn cts-btn-secondary">← ' . __( 'Zurück', 'churchtools-suite' ) . '</button>';
			}
			$pagination .= '<span class="cts-pagination-info">' . sprintf( /* translators: 1: Current page, 2: Total pages. */ __( 'Seite %1$d von %2$d', 'churchtools-suite' ), $page, $total_pages ) . '</span>';
			if ( $page < $total_pages ) {
				$pagination .= '<button data-paged="' . ( $page + 1 ) . '" class="cts-ajax-page cts-btn cts-btn-secondary">' . __( 'Weiter', 'churchtools-suite' ) . ' →</button>';
			}
			$pagination .= '</div>';
			echo wp_kses_post( $pagination );
		}

		$html = ob_get_clean();
		wp_send_json_success(
			array(
				'html'        => $html,
				'total'       => $total,
				'page'        => $page,
				'total_pages' => $total_pages,
			)
		);
	}

	/**
	 * AJAX: Delete one locally stored event.
	 */
	public function ajax_delete_event() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_churchtools_suite' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ) ), 403 );
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		if ( $event_id < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Event-ID.', 'churchtools-suite' ) ), 400 );
		}

		global $wpdb;
		$events_table   = $wpdb->prefix . 'cts_events';
		$services_table = $wpdb->prefix . 'cts_event_services';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		$deleted_services = $wpdb->delete( $services_table, array( 'event_id' => $event_id ), array( '%d' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( false === $deleted_services ) {
			wp_send_json_error( array( 'message' => __( 'Die Service-Zuordnungen konnten nicht gelöscht werden.', 'churchtools-suite' ) ), 500 );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		$deleted_event = $wpdb->delete( $events_table, array( 'id' => $event_id ), array( '%d' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( false === $deleted_event ) {
			wp_send_json_error( array( 'message' => __( 'Der Termin konnte nicht gelöscht werden.', 'churchtools-suite' ) ), 500 );
		}

		if ( 0 === $deleted_event ) {
			wp_send_json_error( array( 'message' => __( 'Der Termin wurde nicht gefunden.', 'churchtools-suite' ) ), 404 );
		}

		wp_send_json_success( array( 'message' => __( 'Termin gelöscht.', 'churchtools-suite' ) ) );
	}

	/**
	 * AJAX: Fetch imported services list (server-side pagination)
	 */
	public function ajax_fetch_imported_services_list() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Permission: manage_churchtools_suite (General Admin).
		if ( ! current_user_can( 'manage_churchtools_suite' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ) ) );
			return;
		}

		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-event-services-repository.php';
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-events-repository.php';

		$event_services_repo = new ChurchTools_Suite_Event_Services_Repository();
		$events_repo         = new ChurchTools_Suite_Events_Repository();

		$page   = max( 1, (int) ( absint( wp_unslash( $_POST['paged'] ?? 1 ) ) ) );
		$limit  = 50;
		$offset = ( $page - 1 ) * $limit;

		$all_services = $event_services_repo->get_all();
		$total        = count( $all_services );
		$services     = array_slice( $all_services, $offset, $limit );
		$total_pages  = max( 1, ceil( $total / $limit ) );

		ob_start();
		if ( empty( $services ) ) {
			?>
			<div class="cts-empty-state"><span class="cts-empty-icon">👥</span><h3><?php esc_html_e( 'Keine Dienste gefunden', 'churchtools-suite' ); ?></h3></div>
			<?php
		} else {
			?>
			<div class="cts-card">
				<div class="cts-table-wrapper">
					<table class="cts-events-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Service', 'churchtools-suite' ); ?></th>
								<th><?php esc_html_e( 'Person', 'churchtools-suite' ); ?></th>
								<th><?php esc_html_e( 'Event', 'churchtools-suite' ); ?></th>
								<th><?php esc_html_e( 'Service ID', 'churchtools-suite' ); ?></th>
								<th><?php esc_html_e( 'Importiert', 'churchtools-suite' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php
						foreach ( $services as $service ) :
							$event = $events_repo->get_by_id( $service->event_id );
							?>
							<tr>
								<td><strong><?php echo esc_html( $service->service_name ); ?></strong></td>
								<td><?php echo ! empty( $service->person_name ) ? esc_html( $service->person_name ) : '<span class="cts-muted">—</span>'; ?></td>
								<td>
								<?php
								if ( $event ) {
									echo '<div class="cts-event-title-main">' . esc_html( $event->title ) . '</div><div class="cts-event-date-time">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $event->start_datetime ) ) ) . '</div>';
								} else {
									echo '<span class="cts-muted">Event gelöscht</span>'; }
								?>
								</td>
								<td><?php echo ! empty( $service->service_id ) ? '<code>' . esc_html( $service->service_id ) . '</code>' : '<span class="cts-muted">—</span>'; ?></td>
								<td><?php echo ! empty( $service->created_at ) ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $service->created_at ) ) ) : '<span class="cts-muted">—</span>'; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php
		}

		if ( $total_pages > 1 ) {
			$pagination = '<div class="cts-pagination">';
			if ( $page > 1 ) {
				$pagination .= '<button data-paged="' . ( $page - 1 ) . '" class="cts-ajax-page cts-btn cts-btn-secondary">← ' . __( 'Zurück', 'churchtools-suite' ) . '</button>'; }
			$pagination .= '<span class="cts-pagination-info">' . sprintf( /* translators: 1: Current page, 2: Total pages. */ __( 'Seite %1$d von %2$d', 'churchtools-suite' ), $page, $total_pages ) . '</span>';
			if ( $page < $total_pages ) {
				$pagination .= '<button data-paged="' . ( $page + 1 ) . '" class="cts-ajax-page cts-btn cts-btn-secondary">' . __( 'Weiter', 'churchtools-suite' ) . ' →</button>'; }
			$pagination .= '</div>';
			echo wp_kses_post( $pagination );
		}

		$html = ob_get_clean();
		wp_send_json_success(
			array(
				'html'        => $html,
				'total'       => $total,
				'page'        => $page,
				'total_pages' => $total_pages,
			)
		);
	}

	/**
	 * AJAX Handler: Trigger Manual Sync
	 * Führt sofortigen Cron-Sync aus
	 */
	public function ajax_trigger_manual_sync() {
		// Reset output buffers and start a fresh buffer to avoid polluted JSON responses.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		ob_start();

		$send_json = static function ( $success, $payload ) {
			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}

			if ( $success ) {
				wp_send_json_success( $payload );
			}

			wp_send_json_error( $payload );
		};

		$nonce_ok = check_ajax_referer( 'churchtools_suite_admin', 'nonce', false );
		if ( false === $nonce_ok ) {
			$send_json(
				false,
				array(
					'message' => __( 'Ungültiger oder fehlender Nonce.', 'churchtools-suite' ),
				)
			);
			return;
		}

		// Permission: sync_churchtools_events (Event Sync).
		if ( ! current_user_can( 'sync_churchtools_events' ) ) {
			$send_json(
				false,
				array(
					'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ),
				)
			);
			return;
		}

		// Load Logger with error suppression.
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-logger.php';

		try {
			ChurchTools_Suite_Logger::log( '=== MANUAL SYNC START ===', 'info' );
			$start_time = current_time( 'mysql' );

			// Sync-Historie Repository laden.
			ChurchTools_Suite_Logger::log( 'Loading Sync History Repository', 'info' );
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-sync-history-repository.php';

			ChurchTools_Suite_Logger::log( 'Instantiating Sync History Repository', 'info' );
			$history_repo = new ChurchTools_Suite_Sync_History_Repository();

			// Historie-Eintrag erstellen.
			ChurchTools_Suite_Logger::log( 'Creating sync history entry', 'info' );
			$sync_id = $history_repo->create_sync_entry( 'manual', $start_time );
			ChurchTools_Suite_Logger::log( sprintf( 'Sync ID: %d', $sync_id ), 'info' );

			// Event Sync Service laden.
			ChurchTools_Suite_Logger::log( 'Loading dependencies', 'info' );

			ChurchTools_Suite_Logger::log( 'Loading: class-churchtools-suite-ct-client.php', 'info' );
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-ct-client.php';

			ChurchTools_Suite_Logger::log( 'Loading: class-churchtools-suite-repository-base.php', 'info' );
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';

			ChurchTools_Suite_Logger::log( 'Loading: class-churchtools-suite-events-repository.php', 'info' );
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-events-repository.php';

			ChurchTools_Suite_Logger::log( 'Loading: class-churchtools-suite-calendars-repository.php', 'info' );
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-calendars-repository.php';

			ChurchTools_Suite_Logger::log( 'Loading: class-churchtools-suite-event-services-repository.php', 'info' );
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-event-services-repository.php';

			ChurchTools_Suite_Logger::log( 'Loading: class-churchtools-suite-services-repository.php', 'info' );
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-services-repository.php';

			ChurchTools_Suite_Logger::log( 'Loading: class-churchtools-suite-event-sync-service.php', 'info' );
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/services/class-churchtools-suite-event-sync-service.php';

			ChurchTools_Suite_Logger::log( 'All dependencies loaded successfully', 'info' );

			// Service initialisieren.
			ChurchTools_Suite_Logger::log( 'Initializing services', 'info' );
			$ct_client           = new ChurchTools_Suite_CT_Client();
			$events_repo         = new ChurchTools_Suite_Events_Repository();
			$calendars_repo      = new ChurchTools_Suite_Calendars_Repository();
			$event_services_repo = new ChurchTools_Suite_Event_Services_Repository();
			$services_repo       = new ChurchTools_Suite_Services_Repository();
			$sync_service        = new ChurchTools_Suite_Event_Sync_Service( $ct_client, $events_repo, $calendars_repo, $event_services_repo, $services_repo );

			// Sync ausführen.
			ChurchTools_Suite_Logger::log( 'Starting sync_events()', 'info' );
			$result = $sync_service->sync_events(
				array(
					'force_full' => false, // Manual trigger uses incremental by default (v0.7.1.0).
				)
			);
			ChurchTools_Suite_Logger::log( sprintf( 'sync_events() completed - Result type: %s', gettype( $result ) ), 'info' );

			if ( is_wp_error( $result ) ) {
				ChurchTools_Suite_Logger::log( 'Sync returned WP_Error: ' . $result->get_error_message(), 'error' );

				// Fehler-Eintrag.
				if ( $sync_id ) {
					$history_repo->complete_sync( $sync_id, array(), $result->get_error_message() );
				}

				$send_json(
					false,
					array(
						'message' => __( 'Sync fehlgeschlagen: ', 'churchtools-suite' ) . $result->get_error_message(),
					)
				);
				return;
			}

			// Posts sync triggered via Hook 'cts_do_sync_posts' (if addon is active).
			ChurchTools_Suite_Logger::log( 'Triggering posts sync hook', 'info' );
			do_action( 'cts_do_sync_posts', $ct_client, $result );

			// Erfolg - Stats zusammenstellen.
			ChurchTools_Suite_Logger::log( sprintf( 'Processing results - Keys: %s', implode( ', ', array_keys( $result ) ) ), 'info' );

			$stats = array(
				'calendars_processed' => $result['calendars_processed'] ?? 0,
				'events_found'        => $result['events_found'] ?? 0,
				'events_inserted'     => $result['events_inserted'] ?? 0,
				'events_updated'      => $result['events_updated'] ?? 0,
				'events_skipped'      => $result['events_skipped'] ?? 0,
				'services_imported'   => $result['services_imported'] ?? 0,
				'ct_posts_found'      => $result['ct_posts_found'] ?? 0,
				'ct_posts_created'    => $result['ct_posts_created'] ?? 0,
				'ct_posts_updated'    => $result['ct_posts_updated'] ?? 0,
				'ct_posts_skipped'    => $result['ct_posts_skipped'] ?? 0,
				'ct_posts_errors'     => $result['ct_posts_errors'] ?? 0,
				'ct_posts_error'      => $result['ct_posts_error'] ?? '',
				'started_at'          => $start_time,
				'completed_at'        => current_time( 'mysql' ),
			);

			ChurchTools_Suite_Logger::log(
				sprintf(
					'Stats: %d calendars, %d events, %d services',
					$stats['calendars_processed'],
					$stats['events_found'],
					$stats['services_imported']
				),
				'info'
			);

			// Historie-Eintrag abschließen.
			if ( $sync_id ) {
				ChurchTools_Suite_Logger::log( 'Completing sync history entry', 'info' );
				$history_repo->complete_sync( $sync_id, $stats, null );
			}

			ChurchTools_Suite_Logger::log( '=== MANUAL SYNC SUCCESS ===', 'info' );

			$message = sprintf(
				/* translators: 1: Calendars processed, 2: Events found, 3: New events, 4: Updated events, 5: Skipped events, 6: Imported services. */                __( '✅ Manueller Sync erfolgreich! %1$d Kalender, %2$d Events gefunden, %3$d neu, %4$d aktualisiert, %5$d übersprungen, %6$d Dienste importiert', 'churchtools-suite' ),
				$stats['calendars_processed'],
				$stats['events_found'],
				$stats['events_inserted'],
				$stats['events_updated'],
				$stats['events_skipped'],
				$stats['services_imported']
			);

			if ( ! empty( $stats['ct_posts_error'] ) ) {
				$message .= ' ' . sprintf( /* translators: %s: Post synchronization error message. */ __( 'Posts-Sync Fehler: %s', 'churchtools-suite' ), $stats['ct_posts_error'] );
			} elseif ( $stats['ct_posts_found'] > 0 || $stats['ct_posts_created'] > 0 || $stats['ct_posts_updated'] > 0 ) {
				$message .= ' ' . sprintf(
					/* translators: 1: Posts found, 2: New posts, 3: Updated posts, 4: Skipped posts, 5: Errors. */                    __( 'ChurchTools-Posts: %1$d gefunden, %2$d neu, %3$d aktualisiert, %4$d übersprungen, %5$d Fehler.', 'churchtools-suite' ),
					$stats['ct_posts_found'],
					$stats['ct_posts_created'],
					$stats['ct_posts_updated'],
					$stats['ct_posts_skipped'],
					$stats['ct_posts_errors']
				);
			}

			$send_json(
				true,
				array(
					'message' => $message,
					'stats'   => $stats,
				)
			);

		} catch ( Exception $e ) {
			ChurchTools_Suite_Logger::log( '=== MANUAL SYNC ERROR ===', 'error' );
			ChurchTools_Suite_Logger::log( 'Exception: ' . $e->getMessage(), 'error' );
			ChurchTools_Suite_Logger::log( 'Stack trace: ' . $e->getTraceAsString(), 'error' );

			// Fehler-Eintrag.
			if ( isset( $sync_id ) && $sync_id && isset( $history_repo ) ) {
				try {
					$history_repo->complete_sync( $sync_id, array(), $e->getMessage() );
					/* phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Do not replace the original sync failure with a secondary history-recording failure. */ } catch ( Exception $inner_e ) {
					// Ignore history errors during error handling.
					}
			}

			$send_json(
				false,
				array(
					'message' => __( 'Fehler: ', 'churchtools-suite' ) . $e->getMessage(),
				)
			);
		} catch ( Error $e ) {
			// Catch PHP 7+ Fatal Errors.
			ChurchTools_Suite_Logger::log( '=== MANUAL SYNC FATAL ERROR ===', 'error' );
			ChurchTools_Suite_Logger::log( 'Fatal Error: ' . $e->getMessage(), 'error' );
			ChurchTools_Suite_Logger::log( 'File: ' . $e->getFile() . ':' . $e->getLine(), 'error' );

			$send_json(
				false,
				array(
					'message' => __( 'Fataler Fehler: ', 'churchtools-suite' ) . $e->getMessage() . ' in ' . basename( $e->getFile() ) . ':' . $e->getLine(),
				)
			);
		}
	}

	/**
	 * AJAX Handler: Trigger Keepalive
	 * Führt sofortigen Session Keepalive aus
	 */
	public function ajax_trigger_keepalive() {
		// Clear any previous output to avoid HTML before JSON.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Use plugin logger to verify handler invocation.
		if ( ! class_exists( 'ChurchTools_Suite_Logger' ) ) {
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-logger.php';
		}
		if ( class_exists( 'ChurchTools_Suite_Logger' ) ) {
			ChurchTools_Suite_Logger::info( 'ajax_keepalive', 'ajax_trigger_keepalive called', array( 'user_id' => get_current_user_id() ) );
		}

		// Start a fresh output buffer to capture any unexpected output during handler.
		ob_start();

		if ( class_exists( 'ChurchTools_Suite_Logger' ) ) {
			ChurchTools_Suite_Logger::debug( 'ajax_keepalive', 'Output buffer started for ajax_trigger_keepalive', array( 'ob_level' => ob_get_level() ) );
		}

		// Register shutdown handler to catch fatal errors and return JSON instead of raw HTML.
		register_shutdown_function(
			function () {
				$error = error_get_last();
				if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
						require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-logger.php';
						$ob = '';
					if ( ob_get_length() ) {
						$ob = substr( ob_get_contents(), 0, 2000 );
					}
					$shutdown_payload = array(
						'error'        => $error,
						'headers_sent' => headers_sent(),
						'ob_preview'   => $ob,
						/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, display filters, or status messages; no state is changed by these query arguments. */ 'request_keys' => array_keys( $_REQUEST ?? array() ),
						'server'       => array(
							'REQUEST_URI' => sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? null ) ),
							'REMOTE_ADDR' => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? null ) ),
						),
					);
						ChurchTools_Suite_Logger::critical( 'ajax_keepalive', 'Fatal error in ajax_trigger_keepalive', $shutdown_payload );
						// If headers not sent, send JSON response.
					if ( ! headers_sent() ) {
						header( 'Content-Type: application/json; charset=utf-8' );
						http_response_code( 500 );
						echo wp_json_encode(
							array(
								'success' => false,
								'data'    => array(
									'message' => 'PHP Fatal Error: ' . $error['message'],
									'file'    => $error['file'],
									'line'    => $error['line'],
								),
							)
						);
					}
						exit;
				}
			}
		);

		// Check nonce without dying (avoid wp_die HTML) and handle failure with JSON.
		$nonce_ok = check_ajax_referer( 'churchtools_suite_admin', 'nonce', false );
		if ( class_exists( 'ChurchTools_Suite_Logger' ) ) {
			ChurchTools_Suite_Logger::debug(
				'ajax_keepalive',
				'Nonce check result',
				array(
					'nonce_ok'     => false === $nonce_ok ? false : true,
					'request_keys' => array_keys( $_REQUEST ?? array() ),
				)
			);
		}
		if ( false === $nonce_ok ) {
			if ( class_exists( 'ChurchTools_Suite_Logger' ) ) {
				ChurchTools_Suite_Logger::warning( 'ajax_keepalive', 'Invalid nonce in ajax_trigger_keepalive', array( 'user_id' => get_current_user_id() ) );
			}
			// capture any output and discard.
			ob_end_clean();
			wp_send_json_error( array( 'message' => __( 'Ungültiger oder fehlender Nonce.', 'churchtools-suite' ) ) );
			return;
		}

		// Permission: configure_churchtools_suite (Settings).
		if ( ! current_user_can( 'configure_churchtools_suite' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ),
				)
			);
		}

		try {
			// CT-Client laden.
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-ct-client.php';
			if ( class_exists( 'ChurchTools_Suite_Logger' ) ) {
				ChurchTools_Suite_Logger::debug( 'ajax_keepalive', 'Instantiating CT client' );
			}
			$ct_client = new ChurchTools_Suite_CT_Client();
			if ( class_exists( 'ChurchTools_Suite_Logger' ) ) {
				ChurchTools_Suite_Logger::info(
					'ajax_keepalive',
					'CT client instantiated',
					array(
						'is_authenticated' => $ct_client->is_authenticated(),
						'cookies_count'    => is_array( $ct_client->get_cookies() ) ? count( $ct_client->get_cookies() ) : 0,
					)
				);
			}

			// Keepalive ausführen.
			if ( class_exists( 'ChurchTools_Suite_Logger' ) ) {
				ChurchTools_Suite_Logger::debug( 'ajax_keepalive', 'Calling keepalive on CT client' );
			}
			$result = $ct_client->keepalive();
			if ( class_exists( 'ChurchTools_Suite_Logger' ) ) {
				// Log summarized result (avoid dumping sensitive data).
				if ( is_wp_error( $result ) ) {
					ChurchTools_Suite_Logger::error( 'ajax_keepalive', 'Keepalive returned WP_Error', array( 'error' => $result->get_error_message() ) );
				} else {
					ChurchTools_Suite_Logger::info( 'ajax_keepalive', 'Keepalive succeeded', array( 'message' => is_array( $result ) && isset( $result['message'] ) ? $result['message'] : null ) );
				}
			}

			if ( is_wp_error( $result ) ) {
				// Log result via plugin logger.
				if ( class_exists( 'ChurchTools_Suite_Logger' ) ) {
					ChurchTools_Suite_Logger::error( 'ajax_keepalive', 'Keepalive failed: ' . $result->get_error_message(), array( 'user_id' => get_current_user_id() ) );
				}
				// Capture any extra output.
				$extra = trim( ob_get_clean() );
				if ( class_exists( 'ChurchTools_Suite_Logger' ) && ! empty( $extra ) ) {
					ChurchTools_Suite_Logger::error( 'ajax_keepalive', 'Unexpected output before JSON (keepalive failed)', array( 'extra' => substr( $extra, 0, 2000 ) ) );
				}
				wp_send_json_error( array( 'message' => __( 'Keepalive fehlgeschlagen: ', 'churchtools-suite' ) . $result->get_error_message() ) );
				return;
			}

			// Capture any extra output and log via plugin logger.
			$extra = trim( ob_get_clean() );
			if ( class_exists( 'ChurchTools_Suite_Logger' ) && ! empty( $extra ) ) {
				ChurchTools_Suite_Logger::warning(
					'ajax_keepalive',
					'Unexpected output before JSON (keepalive success)',
					array(
						'extra'   => substr( $extra, 0, 2000 ),
						'user_id' => get_current_user_id(),
					)
				);
			}

			wp_send_json_success(
				array(
					'message' => __( '✅ Session Keepalive erfolgreich!', 'churchtools-suite' ),
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Fehler: ', 'churchtools-suite' ) . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX Handler: Reload Logs
	 */
	public function ajax_reload_logs() {
		// Clean any previous output.
		ob_clean();

		// Check nonce.
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Check permissions: view_churchtools_debug (Debug/Logs).
		if ( ! current_user_can( 'view_churchtools_debug' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ),
				)
			);
			return;
		}

		try {
			// Load Logger class.
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-logger.php';

			$log_content = ChurchTools_Suite_Logger::get_log_content( 100 ); // Last 100 lines.

			if ( empty( $log_content ) ) {
				$html = '<span style="color: #8c8f94;">Keine Logs verfügbar. Führen Sie einen Sync aus, um Logs zu generieren.</span>';
			} else {
				// Colorize log levels.
				$log_content = htmlspecialchars( $log_content );
				$log_content = preg_replace( '/\[ERROR\]/', '<span style="color: #f48771; font-weight: 600;">[ERROR]</span>', $log_content );
				$log_content = preg_replace( '/\[WARNING\]/', '<span style="color: #dcdcaa; font-weight: 600;">[WARNING]</span>', $log_content );
				$log_content = preg_replace( '/\[INFO\]/', '<span style="color: #4ec9b0; font-weight: 600;">[INFO]</span>', $log_content );
				$html        = $log_content;
			}

			wp_send_json_success(
				array(
					'html'    => $html,
					'message' => __( 'Logs neu geladen.', 'churchtools-suite' ),
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Fehler beim Laden der Logs: ', 'churchtools-suite' ) . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX Handler: Export Logs as CSV download
	 */
	public function ajax_export_logs() {
		// Check nonce.
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Check permissions: view_churchtools_debug (Debug/Logs).
		if ( ! current_user_can( 'view_churchtools_debug' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'churchtools-suite' ), 403 );
		}

		require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-logger.php';

		$lines = isset( $_REQUEST['lines'] ) ? absint( $_REQUEST['lines'] ) : 2000;
		$lines = max( 1, min( 20000, $lines ) );

		$csv      = ChurchTools_Suite_Logger::export_csv( $lines );
		$filename = 'churchtools-suite-logs-' . gmdate( 'Y-m-d-His' ) . '.csv';

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		if ( ob_get_length() ) {
			ob_clean();
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Content-Length: ' . strlen( $csv ) );

		/* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download with a text/csv content type; HTML escaping would corrupt the exported data. */ echo $csv;
		exit;
	}

	/**
	 * AJAX Handler: Clear Logs
	 */
	public function ajax_clear_logs() {
		// Clean any previous output.
		ob_clean();

		// Check nonce.
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Check permissions: view_churchtools_debug (Debug/Logs).
		if ( ! current_user_can( 'view_churchtools_debug' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ),
				)
			);
			return;
		}

		try {
			// Load Logger class.
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-logger.php';

			ChurchTools_Suite_Logger::clear_log();

			wp_send_json_success(
				array(
					'message' => __( 'Logs gelöscht.', 'churchtools-suite' ),
					'html'    => '<span style="color: #8c8f94;">Logs wurden gelöscht. Führen Sie einen Sync aus, um neue Logs zu generieren.</span>',
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Fehler beim Löschen der Logs: ', 'churchtools-suite' ) . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX Handler: Clear Block Debug Logs
	 */
	public function ajax_clear_block_logs() {
		// Check nonce.
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Check permissions: view_churchtools_debug (Debug/Logs).
		if ( ! current_user_can( 'view_churchtools_debug' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Keine Berechtigung.', 'churchtools-suite' ),
				)
			);
			return;
		}

		try {
			// Clear block logs.
			delete_option( 'churchtools_suite_block_debug_logs' );
			delete_option( 'churchtools_suite_block_status' );

			wp_send_json_success(
				array(
					'message' => __( 'Block-Logs gelöscht.', 'churchtools-suite' ),
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Fehler beim Löschen: ', 'churchtools-suite' ) . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX Handler: Get Modal Template
	 */
	public function ajax_get_modal_template() {
		// Check nonce.
		check_ajax_referer( 'churchtools_suite_public', 'nonce' );

		// Load logger.
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-logger.php';

		// Load template loader.
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-template-loader.php';

		// v0.9.9.84: Click action from Block, not Dashboard setting.
		$click_action = isset( $_POST['click_action'] ) ? sanitize_text_field( wp_unslash( $_POST['click_action'] ) ) : 'modal';
		$event_id     = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : null;

		ChurchTools_Suite_Logger::debug(
			'ajax_modal',
			'Modal request received',
			array(
				'click_action' => $click_action,
				'event_id'     => $event_id,
			)
		);

		// v0.9.9.84: If click action is "page", return page URL instead of modal HTML.
		if ( 'page' === $click_action ) {
			// v0.9.9.85: Always use Dashboard setting (no Block override).
			$single_template        = sanitize_key( (string) get_option( 'churchtools_suite_single_template', 'professional' ) );
			$valid_single_templates = array( 'professional', 'minimal' );
			if ( ! in_array( $single_template, $valid_single_templates, true ) ) {
				$single_template = 'professional';
			}

			// Build robust base URL for single-event navigation.
			$single_page_url = trim( (string) get_option( 'churchtools_suite_single_page_url', '' ) );
			$event_base_url  = '';

			if ( ! empty( $single_page_url ) ) {
				$event_base_url = esc_url_raw( $single_page_url );
			}

			if ( empty( $event_base_url ) && isset( $_POST['current_url'] ) ) {
				$current_url           = esc_url_raw( wp_unslash( $_POST['current_url'] ) );
				$validated_current_url = wp_validate_redirect( $current_url, '' );
				if ( ! empty( $validated_current_url ) ) {
					$event_base_url = $validated_current_url;
				}
			}

			if ( empty( $event_base_url ) ) {
				$event_base_url = wp_get_referer();
			}

			if ( empty( $event_base_url ) ) {
				$event_base_url = home_url( '/' );
			}

			$event_base_url = apply_filters( 'churchtools_suite_single_event_base_url', $event_base_url );

			// Build event page URL - clean (only event_id + template).
			$event_page_url = add_query_arg(
				array(
					'event_id'     => $event_id,
					'template'     => $single_template,
					'ctse_context' => 'elementor',
				),
				$event_base_url
			);

			ChurchTools_Suite_Logger::debug(
				'ajax_modal',
				'Returning page redirect',
				array(
					'action'   => 'page',
					'template' => $single_template,
					'base_url' => $event_base_url,
					'url'      => $event_page_url,
				)
			);

			wp_send_json_success(
				array(
					'action' => 'page',
					'url'    => $event_page_url,
				)
			);
			return;
		}

		// v0.9.9.84: Click action is "modal" - continue with modal loading
		// v0.9.9.66: Get current view from client (if available).
		$current_view = isset( $_POST['current_view'] ) ? sanitize_text_field( wp_unslash( $_POST['current_view'] ) ) : null;

		// Load modal template settings.
		$global_modal_setting = sanitize_key( (string) get_option( 'churchtools_suite_modal_template', 'professional' ) );

		ChurchTools_Suite_Logger::debug(
			'ajax_modal',
			'Dashboard settings loaded',
			array(
				'churchtools_suite_modal_template' => $global_modal_setting,
				'current_view'                     => $current_view,
			)
		);

		// v0.9.9.83: SIMPLIFIED LOGIC - Use single global template for ALL views
		// Scan filesystem for available templates.
		$valid_modal_templates = self::get_available_modal_templates();

		// Fallback if no templates found.
		if ( empty( $valid_modal_templates ) ) {
			$valid_modal_templates = array( 'professional' );
		}

		// Check if dashboard setting references valid template.
		if ( ! in_array( $global_modal_setting, $valid_modal_templates, true ) ) {
			ChurchTools_Suite_Logger::warning(
				'ajax_modal',
				'Dashboard template does not exist - using fallback',
				array(
					'requested_template'      => $global_modal_setting,
					'fallback_template'       => $valid_modal_templates[0],
					'valid_templates'         => $valid_modal_templates,
					'available_in_filesystem' => $valid_modal_templates,
				)
			);
			$global_modal_setting = $valid_modal_templates[0];
		}

		// v0.9.9.83: UNIFIED - Same template for ALL views (list, grid, calendar, single)
		// v0.9.9.85: Always use Dashboard setting (no Block override needed).
		$modal_template = $global_modal_setting;

		ChurchTools_Suite_Logger::debug(
			'ajax_modal',
			'Template selected',
			array(
				'selected_template' => $modal_template,
				'source'            => 'dashboard_global',
				'current_view'      => $current_view,
			)
		);

		// v1.4.0: Neue Template-Struktur (views/event-modal/).
		$template_path = 'views/event-modal/' . sanitize_file_name( $modal_template ) . '.php';

		// v0.9.9.74: FIX - render_template with $echo=false to capture output
		// (using ob_start() with $echo=true doesn't work because output is sent directly).
		$html = ChurchTools_Suite_Template_Loader::render_template( $template_path, array(), false );

		// v0.9.9.75: Enhanced logging using plugin logger.
		$is_error_comment = strpos( $html, '<!--' ) === 0;
		ChurchTools_Suite_Logger::debug(
			'ajax_modal',
			'Template rendering result',
			array(
				'template_path' => $template_path,
				'html_length'   => strlen( $html ),
				'is_error'      => $is_error_comment,
				'first_chars'   => substr( $html, 0, 50 ),
			)
		);

		// If error, log details.
		if ( $is_error_comment ) {
			$full_path = CHURCHTOOLS_SUITE_PATH . 'templates/' . $template_path;
			ChurchTools_Suite_Logger::warning(
				'ajax_modal',
				'Template not found or render error',
				array(
					'template_path'           => $template_path,
					'full_path'               => $full_path,
					'file_exists'             => file_exists( $full_path ),
					'churchtools_suite_path'  => CHURCHTOOLS_SUITE_PATH,
					'error_message'           => substr( $html, 4, 300 ), // Remove <!-- and get message.
					'dashboard_modal_setting' => $global_modal_setting,
					'current_view'            => $current_view,
				)
			);
		}

		// v0.10.3.6: Debug - Prüfe ob HTML vorhanden ist.
		if ( empty( $html ) || $is_error_comment ) {
			// Fehler beim Laden des Templates.
			wp_send_json_error(
				array(
					'message' => 'Modal-Template konnte nicht geladen werden',
					'html'    => $html, // Sende trotzdem für Debugging.
					'debug'   => array(
						'template_path'           => CHURCHTOOLS_SUITE_PATH . 'templates/views/event-modal/' . $modal_template . '.php',
						'exists'                  => file_exists( CHURCHTOOLS_SUITE_PATH . 'templates/views/event-modal/' . $modal_template . '.php' ),
						'current_view'            => $current_view,
						'selected_modal'          => $modal_template,
						'churchtools_suite_path'  => CHURCHTOOLS_SUITE_PATH,
						'dashboard_modal_setting' => $global_modal_setting,
					),
				)
			);
			return;
		}

		// Success - log it.
		ChurchTools_Suite_Logger::info(
			'ajax_modal',
			'Modal template loaded successfully',
			array(
				'template_path'     => $template_path,
				'selected_template' => $modal_template,
				'html_length'       => strlen( $html ),
				'action'            => 'modal',
			)
		);

		wp_send_json_success(
			array(
				'action' => 'modal',
				'html'   => $html,
			)
		);
	}

	/**
	 * AJAX Handler: Get Event Details
	 */
	public function ajax_get_event_details() {
		// Check nonce.
		check_ajax_referer( 'churchtools_suite_public', 'nonce' );

		$event_id = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;

		if ( ! $event_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Keine Event-ID angegeben.', 'churchtools-suite' ),
				)
			);
		}

		// Load repositories.
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-events-repository.php';
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-calendars-repository.php';
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-event-services-repository.php';

		global $wpdb;

		$events_repo    = new ChurchTools_Suite_Events_Repository( $wpdb );
		$calendars_repo = new ChurchTools_Suite_Calendars_Repository( $wpdb );
		$services_repo  = new ChurchTools_Suite_Event_Services_Repository( $wpdb );

		// Get event from database.
		$event = $events_repo->get_by_id( $event_id );

		if ( ! $event ) {
			wp_send_json_error(
				array(
					'message' => __( 'Event nicht gefunden.', 'churchtools-suite' ),
				)
			);
		}

		// Get calendar.
		$calendar = null;
		if ( $event->calendar_id ) {
			$calendar = $calendars_repo->get_by_calendar_id( $event->calendar_id );
		}

		// Get services.
		$services = $services_repo->get_for_event( $event_id );

		// Format dates with WordPress timezone.
		$date_format = get_option( 'date_format', 'd.m.Y' );
		$time_format = get_option( 'time_format', 'H:i' );

		// Check if 24h format (no 'a' or 'A' in format string).
		$is_24h      = ( strpos( $time_format, 'a' ) === false && strpos( $time_format, 'A' ) === false );
		$time_suffix = $is_24h ? ' Uhr' : '';

		// Convert to WordPress timezone.
		$start_datetime = $event->start_datetime;
		$end_datetime   = $event->end_datetime;

		// Real events from ChurchTools are stored in GMT.
		$start_timestamp = strtotime( get_date_from_gmt( $start_datetime ) );
		$end_timestamp   = $end_datetime ? strtotime( get_date_from_gmt( $end_datetime ) ) : null;

		// Format times with suffix.
		$start_time_formatted = date_i18n( $time_format, $start_timestamp ) . $time_suffix;
		$end_time_formatted   = '';

		if ( $end_timestamp ) {
			$end_time_formatted = date_i18n( $time_format, $end_timestamp ) . $time_suffix;
		}

		// Build time display string.
		$time_display = $start_time_formatted;
		if ( $end_time_formatted ) {
			$time_display .= ' - ' . $end_time_formatted;
		}

		// Extract common fields (v1.0.3.1: Use isset() for optional fields).
		$event_id_val        = $event->id;
		$title               = $event->title;
		$event_desc          = isset( $event->event_description ) ? $event->event_description : null;
		$apt_desc            = isset( $event->appointment_description ) ? $event->appointment_description : null;
		$location_name       = isset( $event->location_name ) ? $event->location_name : '';
		$address_name        = isset( $event->address_name ) ? $event->address_name : '';
		$address_street      = isset( $event->address_street ) ? $event->address_street : '';
		$address_zip         = isset( $event->address_zip ) ? $event->address_zip : '';
		$address_city        = isset( $event->address_city ) ? $event->address_city : '';
		$tags_json           = isset( $event->tags ) ? $event->tags : null;
		$image_attachment_id = isset( $event->image_attachment_id ) ? $event->image_attachment_id : null;
		$image_url           = isset( $event->image_url ) ? $event->image_url : null;

		// Build response.
		$response = array(
			'id'                      => $event_id_val,
			'title'                   => $title,
			'event_description'       => ! empty( $event_desc ) ? wpautop( $event_desc ) : '',
			'appointment_description' => ! empty( $apt_desc ) ? wpautop( $apt_desc ) : '',
			'start_date'              => date_i18n( $date_format, $start_timestamp ),
			'start_time'              => $start_time_formatted,
			'end_time'                => $end_time_formatted,
			'time_display'            => $time_display,
			'location_name'           => $location_name,
			'address_name'            => $address_name,
			'address_street'          => $address_street,
			'address_zip'             => $address_zip,
			'address_city'            => $address_city,
			'calendar_name'           => $calendar ? $calendar->name : '',
			'calendar_color'          => $calendar ? $calendar->color : '#3498db',
			'image_attachment_id'     => $image_attachment_id,
			'image_url'               => $image_url,
			'tags'                    => array(),
			'services'                => array(),
		);

		// Parse tags.
		if ( ! empty( $tags_json ) ) {
			$tags_data = is_string( $tags_json ) ? json_decode( $tags_json, true ) : $tags_json;
			if ( is_array( $tags_data ) ) {
				$response['tags'] = $tags_data;
			}
		}

		// Format services.
		if ( $services ) {
			foreach ( $services as $service ) {
				$response['services'][] = array(
					'service_name' => $service->service_name,
					'person_name'  => $service->person_name,
				);
			}
		}

		wp_send_json_success( $response );
	}

	/**
	 * AJAX: Save Shortcode Preset
	 */
	public function ajax_save_preset() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Permission: manage_churchtools_suite (General Admin).
		if ( ! current_user_can( 'manage_churchtools_suite' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'churchtools-suite' ) ) );
		}

		$name          = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$description   = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$shortcode_tag = sanitize_text_field( wp_unslash( $_POST['shortcode_tag'] ?? '' ) );
		/* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw JSON is decoded and validated below; text sanitizers would alter its values. */ $configuration_json = wp_unslash( $_POST['configuration'] ?? '{}' );
		$custom_css = wp_strip_all_tags( wp_unslash( $_POST['custom_css'] ?? '' ) );

		if ( empty( $name ) || empty( $shortcode_tag ) ) {
			wp_send_json_error( array( 'message' => __( 'Name und Shortcode-Typ sind Pflichtfelder', 'churchtools-suite' ) ) );
		}

		// Decode and validate configuration.
		$configuration = is_string( $configuration_json ) ? json_decode( $configuration_json, true ) : null;
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $configuration ) ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Konfiguration', 'churchtools-suite' ) ) );
		}

		// Store original base view before replacing with slug.
		if ( isset( $configuration['view'] ) && ! empty( $configuration['view'] ) ) {
			$configuration['_base_view'] = $configuration['view'];
		}

		// Generate slug from name.
		$slug = sanitize_title( $name );

		// Replace view parameter with preset slug.
		$configuration['view'] = $slug;

		// Load repository.
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-shortcode-presets-repository.php';
		$presets_repo = new ChurchTools_Suite_Shortcode_Presets_Repository();

		// Save preset.
		$preset_id = $presets_repo->create_preset(
			array(
				'name'          => $name,
				'description'   => $description,
				'shortcode_tag' => $shortcode_tag,
				'configuration' => $configuration,
				'custom_css'    => $custom_css ?: null,
				'is_system'     => 0,
			)
		);

		if ( $preset_id ) {
			wp_send_json_success(
				array(
					'message'   => __( 'Preset erfolgreich gespeichert', 'churchtools-suite' ),
					'preset_id' => $preset_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Fehler beim Speichern', 'churchtools-suite' ) ) );
		}
	}

	/**
	 * AJAX: Update Shortcode Preset
	 */
	public function ajax_update_preset() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Permission: manage_churchtools_suite (General Admin).
		if ( ! current_user_can( 'manage_churchtools_suite' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'churchtools-suite' ) ) );
		}

		$preset_id     = absint( $_POST['preset_id'] ?? 0 );
		$name          = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$description   = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$shortcode_tag = sanitize_text_field( wp_unslash( $_POST['shortcode_tag'] ?? '' ) );
		/* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw JSON is decoded and validated below; text sanitizers would alter its values. */ $configuration_json = wp_unslash( $_POST['configuration'] ?? '{}' );
		$custom_css = wp_strip_all_tags( wp_unslash( $_POST['custom_css'] ?? '' ) );

		if ( ! $preset_id ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Preset-ID', 'churchtools-suite' ) ) );
		}

		if ( empty( $name ) || empty( $shortcode_tag ) ) {
			wp_send_json_error( array( 'message' => __( 'Name und Shortcode-Typ sind Pflichtfelder', 'churchtools-suite' ) ) );
		}

		// Decode and validate configuration.
		$configuration = is_string( $configuration_json ) ? json_decode( $configuration_json, true ) : null;
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $configuration ) ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Konfiguration', 'churchtools-suite' ) ) );
		}

		// Store original base view before replacing with slug.
		if ( isset( $configuration['view'] ) && ! empty( $configuration['view'] ) ) {
			$configuration['_base_view'] = $configuration['view'];
		}

		// Generate slug from name.
		$slug = sanitize_title( $name );

		// Replace view parameter with preset slug.
		$configuration['view'] = $slug;

		// Load repository.
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-shortcode-presets-repository.php';
		$presets_repo = new ChurchTools_Suite_Shortcode_Presets_Repository();

		// Update preset.
		$success = $presets_repo->update_preset(
			$preset_id,
			array(
				'name'          => $name,
				'description'   => $description,
				'shortcode_tag' => $shortcode_tag,
				'configuration' => $configuration,
				'custom_css'    => $custom_css ?: null,
			)
		);

		if ( $success ) {
			wp_send_json_success(
				array(
					'message'   => __( 'Preset erfolgreich aktualisiert', 'churchtools-suite' ),
					'preset_id' => $preset_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Fehler beim Aktualisieren (System-Presets können nicht bearbeitet werden)', 'churchtools-suite' ) ) );
		}
	}

	/**
	 * AJAX: Delete Shortcode Preset
	 */
	public function ajax_delete_preset() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Permission: manage_churchtools_suite (General Admin).
		if ( ! current_user_can( 'manage_churchtools_suite' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'churchtools-suite' ) ) );
		}

		$preset_id = absint( $_POST['preset_id'] ?? 0 );

		if ( ! $preset_id ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Preset-ID', 'churchtools-suite' ) ) );
		}

		// Load repository.
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-shortcode-presets-repository.php';
		$presets_repo = new ChurchTools_Suite_Shortcode_Presets_Repository();

		// Delete preset (checks for system presets internally).
		$success = $presets_repo->delete_preset( $preset_id );

		if ( $success ) {
			wp_send_json_success(
				array(
					'message' => __( 'Preset erfolgreich gelöscht', 'churchtools-suite' ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Fehler beim Löschen (System-Presets können nicht gelöscht werden)', 'churchtools-suite' ) ) );
		}
	}

	/**
	 * AJAX Handler: Get Calendars für Checkbox-Auswahl
	 *
	 * @since 0.6.5.18
	 */
	public function ajax_get_calendars() {
		// Check nonce.
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Check permissions: manage_churchtools_calendars (Calendars).
		if ( ! current_user_can( 'manage_churchtools_calendars' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'churchtools-suite' ) ) );
		}

		// Load repository.
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-calendars-repository.php';
		$calendars_repo = new ChurchTools_Suite_Calendars_Repository();

		// Get all calendars.
		$calendars = $calendars_repo->get_all();

		if ( empty( $calendars ) ) {
			wp_send_json_success(
				array(
					'calendars' => array(),
					'message'   => __( 'Keine Kalender verfügbar. Bitte zuerst Kalender synchronisieren.', 'churchtools-suite' ),
				)
			);
			return;
		}

		// Format for frontend.
		$formatted_calendars = array_map(
			function ( $calendar ) {
				return array(
					'id'    => $calendar->calendar_id,
					'name'  => $calendar->name,
					'color' => $calendar->color ?? '#667eea',
				);
			},
			$calendars
		);

		wp_send_json_success(
			array(
				'calendars' => $formatted_calendars,
			)
		);
	}

	/**
	 * AJAX Handler: Clear Events
	 * Löscht alle Events aus der Datenbank
	 */
	public function ajax_clear_events() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Permission: sync_churchtools_events (Event Sync).
		if ( ! current_user_can( 'sync_churchtools_events' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'churchtools-suite' ) ) );
		}

		global $wpdb;
		$events_table         = $wpdb->prefix . 'cts_events';
		$event_services_table = $wpdb->prefix . 'cts_event_services';

		// Delete event services first (foreign key).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$services_deleted = $wpdb->query( "DELETE FROM {$event_services_table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( false === $services_deleted ) {
			wp_send_json_error( array( 'message' => __( 'Fehler beim Löschen der Event-Dienste', 'churchtools-suite' ) ) );
		}

		// Delete events.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$events_deleted = $wpdb->query( "DELETE FROM {$events_table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( false === $events_deleted ) {
			wp_send_json_error( array( 'message' => __( 'Fehler beim Löschen der Events', 'churchtools-suite' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: Deleted events, 2: Deleted service assignments. */                    __( '%1$d Events und %2$d Service-Zuordnungen gelöscht', 'churchtools-suite' ),
					$events_deleted,
					$services_deleted
				),
			)
		);
	}

	/**
	 * AJAX Handler: Clear Calendars
	 * Löscht alle Kalender aus der Datenbank
	 */
	public function ajax_clear_calendars() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		// Permission: manage_churchtools_calendars (Calendars).
		if ( ! current_user_can( 'manage_churchtools_calendars' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'churchtools-suite' ) ) );
		}

		global $wpdb;
		$calendars_table = $wpdb->prefix . 'cts_calendars';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$deleted = $wpdb->query( "DELETE FROM {$calendars_table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( false === $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Fehler beim Löschen der Kalender', 'churchtools-suite' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: Number of deleted calendars. */                    __( '%d Kalender gelöscht', 'churchtools-suite' ),
					$deleted
				),
			)
		);
	}

	/**
	 * AJAX Handler: Clear Dienste
	 * Löscht alle Dienste und Dienstgruppen
	 */
	public function ajax_clear_services() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'churchtools-suite' ) ) );
		}

		global $wpdb;
		$services_table       = $wpdb->prefix . 'cts_services';
		$service_groups_table = $wpdb->prefix . 'cts_service_groups';
		$event_services_table = $wpdb->prefix . 'cts_event_services';

		// Delete event services first (foreign key).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$event_services_deleted = $wpdb->query( "DELETE FROM {$event_services_table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( false === $event_services_deleted ) {
			wp_send_json_error( array( 'message' => __( 'Fehler beim Löschen der Termin-Dienst-Zuordnungen', 'churchtools-suite' ) ) );
		}

		// Delete services.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$services_deleted = $wpdb->query( "DELETE FROM {$services_table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( false === $services_deleted ) {
			wp_send_json_error( array( 'message' => __( 'Fehler beim Löschen der Dienste', 'churchtools-suite' ) ) );
		}

		// Delete service groups.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$groups_deleted = $wpdb->query( "DELETE FROM {$service_groups_table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( false === $groups_deleted ) {
			wp_send_json_error( array( 'message' => __( 'Fehler beim Löschen der Dienstgruppen', 'churchtools-suite' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: Deleted services, 2: Deleted service groups, 3: Deleted event-service assignments. */                    __( '%1$d Dienste, %2$d Dienstgruppen und %3$d Termin-Dienst-Zuordnungen gelöscht', 'churchtools-suite' ),
					$services_deleted,
					$groups_deleted,
					$event_services_deleted
				),
			)
		);
	}

	/**
	 * AJAX Handler: Clear Sync History
	 * Löscht die gesamte Sync-Historie
	 */
	public function ajax_clear_sync_history() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'churchtools-suite' ) ) );
		}

		global $wpdb;
		$sync_history_table = $wpdb->prefix . 'cts_sync_history';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$deleted = $wpdb->query( "DELETE FROM {$sync_history_table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( false === $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Fehler beim Löschen der Sync-Historie', 'churchtools-suite' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: Number of deleted synchronization history entries. */                    __( '%d Sync-Historie-Einträge gelöscht', 'churchtools-suite' ),
					$deleted
				),
			)
		);
	}

	/**
	 * AJAX Handler: Full Reset
	 * Löscht ALLE Daten (Events, Kalender, Dienste, Sync-Historie)
	 */
	public function ajax_full_reset() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'churchtools-suite' ) ) );
		}

		global $wpdb;

		// Delete all data from all tables.
		$tables = array(
			$wpdb->prefix . 'cts_event_services',
			$wpdb->prefix . 'cts_events',
			$wpdb->prefix . 'cts_calendars',
			$wpdb->prefix . 'cts_services',
			$wpdb->prefix . 'cts_service_groups',
			$wpdb->prefix . 'cts_sync_history',
		);

		$total_deleted = 0;
		foreach ( $tables as $table ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
			$deleted = $wpdb->query( "DELETE FROM {$table}" );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			if ( false === $deleted ) {
				wp_send_json_error( array( 'message' => sprintf( /* translators: %s: Database table name. */ __( 'Fehler beim Löschen der Tabelle %s', 'churchtools-suite' ), esc_html( $table ) ) ) );
			}
			$total_deleted += $deleted;
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: Number of deleted records, 2: Number of tables. */                    __( 'Daten erfolgreich zurückgesetzt! %1$d Einträge aus %2$d Tabellen gelöscht.', 'churchtools-suite' ),
					$total_deleted,
					count( $tables )
				),
			)
		);
	}

	/**
	 * AJAX Handler: Complete Reset (v0.10.1.4)
	 * Löscht ALLES: Daten + Einstellungen + Cookies
	 */
	public function ajax_complete_reset() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'churchtools-suite' ) ) );
		}

		global $wpdb;

		// 1. Delete all data from all tables (same as full_reset)
		$tables = array(
			$wpdb->prefix . 'cts_event_services',
			$wpdb->prefix . 'cts_events',
			$wpdb->prefix . 'cts_calendars',
			$wpdb->prefix . 'cts_services',
			$wpdb->prefix . 'cts_service_groups',
			$wpdb->prefix . 'cts_sync_history',
		);

		$total_deleted = 0;
		foreach ( $tables as $table ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
			$deleted = $wpdb->query( "DELETE FROM {$table}" );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			if ( false === $deleted ) {
				wp_send_json_error( array( 'message' => sprintf( /* translators: %s: Database table name. */ __( 'Fehler beim Löschen der Tabelle %s', 'churchtools-suite' ), esc_html( $table ) ) ) );
			}
			$total_deleted += $deleted;
		}

		// 2. Delete all plugin settings from wp_options
		$settings_deleted = 0;
		$settings_keys    = array(
			'churchtools_suite_ct_url',
			'churchtools_suite_ct_auth_method',
			'churchtools_suite_ct_username',
			'churchtools_suite_ct_password',
			'churchtools_suite_ct_token',
			'churchtools_suite_ct_cookies',
			'churchtools_suite_sync_days_past',
			'churchtools_suite_sync_days_future',
			'churchtools_suite_delete_expired_events',
			'churchtools_suite_expired_events_after_days',
			'churchtools_suite_auto_sync_enabled',
			'churchtools_suite_auto_sync_interval',
			'churchtools_suite_advanced_mode',
			'churchtools_suite_events_last_sync',
			'churchtools_suite_last_sync_timestamp',
			'churchtools_suite_db_version',
			'churchtools_suite_session_keepalive',
		);

		foreach ( $settings_keys as $key ) {
			if ( delete_option( $key ) ) {
				++$settings_deleted;
			}
		}

		// 3. Clear all transients
		delete_transient( 'churchtools_suite_update_info' );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: Deleted database records, 2: Deleted options. */                    __( 'Plugin komplett zurückgesetzt!\n\n- %1$d Datenbank-Einträge gelöscht\n- %2$d Einstellungen gelöscht\n- Cookies gelöscht\n\nBitte Plugin neu konfigurieren.', 'churchtools-suite' ),
					$total_deleted,
					$settings_deleted
				),
			)
		);
	}

	/**
	 * AJAX Handler: Rebuild Database (v0.9.0.1)
	 * Löscht ALLE Tabellen und erstellt sie komplett neu
	 * Nützlich bei DB-Strukturproblemen nach Updates
	 */
	public function ajax_rebuild_database() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung', 'churchtools-suite' ) ) );
		}

		global $wpdb;

		// 1. Drop ALL plugin tables
		$tables = array(
			$wpdb->prefix . 'cts_event_services',
			$wpdb->prefix . 'cts_events',
			$wpdb->prefix . 'cts_calendars',
			$wpdb->prefix . 'cts_services',
			$wpdb->prefix . 'cts_service_groups',
			$wpdb->prefix . 'cts_sync_history',
			$wpdb->prefix . 'cts_schedule',
			$wpdb->prefix . 'cts_shortcode_presets',
		);

		$tables_dropped = 0;
		foreach ( $tables as $table ) {
			// Check if table exists before dropping.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			if ( $table_exists ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- Explicit plugin schema migration or administrator-requested database rebuild.
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
				$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
				++$tables_dropped;
			}
		}

		// 2. Reset DB version to force migration
		delete_option( 'churchtools_suite_db_version' );

		// 3. Run migrations to recreate tables
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-migrations.php';
		ChurchTools_Suite_Migrations::run_migrations();

		// 4. Get new DB version
		$new_version = get_option( 'churchtools_suite_db_version', '0.0' );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: Number of deleted tables, 2: Database schema version. */                    __( 'Datenbank erfolgreich neu aufgebaut!\n\n- %1$d Tabellen gelöscht\n- Alle Tabellen neu erstellt (DB Version %2$s)\n- Alle Daten verloren\n\nBitte Daten neu synchronisieren.', 'churchtools-suite' ),
					$tables_dropped,
					$new_version
				),
			)
		);
	}

	/**
	 * AJAX: Load calendar month (Frontend)
	 *
	 * @since 0.10.2.7
	 */
	public function ajax_load_calendar_month() {
		// Log AJAX call start.
		ChurchTools_Suite_Logger::debug(
			'ajax_calendar',
			'AJAX call started',
			array(
				'nonce_isset' => isset( $_POST['nonce'] ),
			)
		);

		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'churchtools_suite_public' ) ) {
			ChurchTools_Suite_Logger::error( 'ajax_calendar', 'Nonce verification failed' );
			wp_send_json_error( array( 'message' => __( 'Sicherheitsprüfung fehlgeschlagen', 'churchtools-suite' ) ) );
			return;
		}

		ChurchTools_Suite_Logger::debug( 'ajax_calendar', 'Nonce verified' );

		try {
			$year  = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : current_datetime()->format( 'Y' );
			$month = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : current_datetime()->format( 'n' );

			ChurchTools_Suite_Logger::debug(
				'ajax_calendar',
				'Parameters extracted',
				array(
					'year'  => $year,
					'month' => $month,
				)
			);

			// Validierung.
			if ( $year < 2000 || $year > 2100 || $month < 1 || $month > 12 ) {
				ChurchTools_Suite_Logger::error(
					'ajax_calendar',
					'Invalid date',
					array(
						'year'  => $year,
						'month' => $month,
					)
				);
				wp_send_json_error( array( 'message' => __( 'Ungültiges Datum', 'churchtools-suite' ) ) );
				return;
			}

			// Shortcode-Attribute aus Request.
			$calendar_ids_raw = isset( $_POST['calendar_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['calendar_ids'] ) ) : '';
			$limit            = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 100;
			// Clamp Limit auf sinnvolle Grenzen (1..500).
			if ( $limit < 1 || $limit > 500 ) {
				ChurchTools_Suite_Logger::debug( 'ajax_calendar', 'Limit clamped', array( 'original' => $limit ) );
				$limit = max( 1, min( 500, $limit ) );
			}
			$enable_modal = isset( $_POST['enable_modal'] ) ? filter_var( wp_unslash( $_POST['enable_modal'] ), FILTER_VALIDATE_BOOLEAN ) : true;

			// Validierung calendar_ids: max. 20 IDs, nur A-Z, a-z, 0-9, _ und - (max. 100 Zeichen).
			$calendar_ids_list = array();
			if ( ! empty( $calendar_ids_raw ) ) {
				$parts = array_slice( array_map( 'trim', explode( ',', $calendar_ids_raw ) ), 0, 20 );
				foreach ( $parts as $p ) {
					if ( '' === $p ) {
						continue; }
					if ( preg_match( '/^[A-Za-z0-9_-]{1,100}$/', $p ) ) {
						$calendar_ids_list[] = $p;
					}
				}
				if ( empty( $calendar_ids_list ) ) {
					ChurchTools_Suite_Logger::warning( 'ajax_calendar', 'All provided calendar_ids invalid', array( 'raw' => $calendar_ids_raw ) );
					wp_send_json_error( array( 'message' => __( 'Ungültige Kalender-IDs', 'churchtools-suite' ) ) );
					return;
				}
			}

			// Anzeige-Optionen (für Tooltip-Infos).
			$show_time          = isset( $_POST['show_time'] ) ? filter_var( wp_unslash( $_POST['show_time'] ), FILTER_VALIDATE_BOOLEAN ) : true;
			$show_description   = isset( $_POST['show_description'] ) ? filter_var( wp_unslash( $_POST['show_description'] ), FILTER_VALIDATE_BOOLEAN ) : false;
			$show_location      = isset( $_POST['show_location'] ) ? filter_var( wp_unslash( $_POST['show_location'] ), FILTER_VALIDATE_BOOLEAN ) : false;
			$show_services      = isset( $_POST['show_services'] ) ? filter_var( wp_unslash( $_POST['show_services'] ), FILTER_VALIDATE_BOOLEAN ) : false;
			$show_calendar_name = isset( $_POST['show_calendar_name'] ) ? filter_var( wp_unslash( $_POST['show_calendar_name'] ), FILTER_VALIDATE_BOOLEAN ) : false;

			// Berechne Datumsbereich für den Monat.
			$from_date = sprintf( '%04d-%02d-01', $year, $month );
			$to_date   = gmdate( 'Y-m-t', strtotime( $from_date ) );

			ChurchTools_Suite_Logger::debug(
				'ajax_calendar',
				'Date range calculated',
				array(
					'from'         => $from_date,
					'to'           => $to_date,
					'calendar_ids' => $calendar_ids,
					'limit'        => $limit,
				)
			);

			// Baue Shortcode-Attribute.
			$atts = array(
				'view'               => 'monthly-modern',
				'from'               => $from_date,
				'to'                 => $to_date,
				'limit'              => $limit,
				'enable_modal'       => $enable_modal,
				'year'               => $year,   // AJAX-spezifisch: Jahr für Titel.
				'month'              => $month, // AJAX-spezifisch: Monat für Titel.
				'show_time'          => $show_time,
				'show_description'   => $show_description,
				'show_location'      => $show_location,
				'show_services'      => $show_services,
				'show_calendar_name' => $show_calendar_name,
			);

			if ( ! empty( $calendar_ids_list ) ) {
				$atts['calendar'] = implode( ',', $calendar_ids_list );
			}

			// Lade Template Loader.
			if ( ! class_exists( 'ChurchTools_Suite_Template_Loader' ) ) {
				ChurchTools_Suite_Logger::debug( 'ajax_calendar', 'Loading Template_Loader class' );
				require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-template-loader.php';
			}

			// Lade Events für diesen Monat.
			if ( ! class_exists( 'ChurchTools_Suite_Events_Repository' ) ) {
				ChurchTools_Suite_Logger::debug( 'ajax_calendar', 'Loading Events_Repository class' );
				require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-events-repository.php';
			}
			$events_repo = new ChurchTools_Suite_Events_Repository();

			ChurchTools_Suite_Logger::debug( 'ajax_calendar', 'Fetching events from database' );

			$raw_events = $events_repo->get_events_in_range(
				$from_date . ' 00:00:00',
				$to_date . ' 23:59:59',
				! empty( $calendar_ids_list ) ? $calendar_ids_list : array(),
				$limit
			);

			ChurchTools_Suite_Logger::debug(
				'ajax_calendar',
				'Events fetched',
				array(
					'count' => count( $raw_events ),
				)
			);

			// Formatiere Events für Template mit Template_Data Service.
			ChurchTools_Suite_Logger::debug( 'ajax_calendar', 'Formatting events with Template_Data service' );

			// Lade Template_Data Service.
			if ( ! class_exists( 'ChurchTools_Suite_Template_Data' ) ) {
				require_once CHURCHTOOLS_SUITE_PATH . 'includes/services/class-churchtools-suite-template-data.php';
			}
			$template_data = new ChurchTools_Suite_Template_Data();

			$events = array();
			foreach ( $raw_events as $event ) {
				$event_array = (array) $event;
				// Formatiere Event (fügt start_time, start_day, calendar_color, etc. hinzu).
				$formatted_event = $template_data->format_event( $event_array );
				$events[]        = $formatted_event;
			}

			ChurchTools_Suite_Logger::debug(
				'ajax_calendar',
				'Events formatted',
				array(
					'count' => count( $events ),
				)
			);

			// Group events by date.
			$events_by_date = array();
			foreach ( $events as $event ) {
				$date = gmdate( 'Y-m-d', strtotime( $event['start_datetime'] ) );
				if ( ! isset( $events_by_date[ $date ] ) ) {
					$events_by_date[ $date ] = array();
				}
				$events_by_date[ $date ][] = $event;
			}

			// Generate GRID HTML only (not full template!).
			ChurchTools_Suite_Logger::debug( 'ajax_calendar', 'Generating grid HTML' );

			ob_start();

			// Weekdays.
			echo '<div class="cts-weekday">' . esc_html__( 'Mo', 'churchtools-suite' ) . '</div>';
			echo '<div class="cts-weekday">' . esc_html__( 'Di', 'churchtools-suite' ) . '</div>';
			echo '<div class="cts-weekday">' . esc_html__( 'Mi', 'churchtools-suite' ) . '</div>';
			echo '<div class="cts-weekday">' . esc_html__( 'Do', 'churchtools-suite' ) . '</div>';
			echo '<div class="cts-weekday">' . esc_html__( 'Fr', 'churchtools-suite' ) . '</div>';
			echo '<div class="cts-weekday">' . esc_html__( 'Sa', 'churchtools-suite' ) . '</div>';
			echo '<div class="cts-weekday">' . esc_html__( 'So', 'churchtools-suite' ) . '</div>';

			// Calculate calendar grid.
			$start_weekday = gmdate( 'N', strtotime( $from_date ) );
			$days_in_month = gmdate( 't', strtotime( $from_date ) );

			// Empty cells before first day.
			for ( $i = 1; $i < $start_weekday; $i++ ) {
				echo '<div class="cts-day cts-day-empty"></div>';
			}

			// Days of month.
			for ( $day = 1; $day <= $days_in_month; $day++ ) {
				$date       = sprintf( '%04d-%02d-%02d', $year, $month, $day );
				$has_events = isset( $events_by_date[ $date ] );
				$is_today   = current_datetime()->format( 'Y-m-d' ) === $date;

				$classes = array( 'cts-day' );
				if ( $is_today ) {
					$classes[] = 'cts-day-today';
				}
				if ( $has_events ) {
					$classes[] = 'cts-day-has-events';
				}

				echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-date="' . esc_attr( $date ) . '">';
				echo '<div class="cts-day-number">' . (int) $day . '</div>';

				if ( $has_events ) {
					echo '<div class="cts-day-events">';
					foreach ( $events_by_date[ $date ] as $event_index => $event ) {
						$color = $event['calendar_color'] ?? '#667eea';
						$title = $event['start_day'] . '. ' . $event['start_month'] . ' ' . $event['start_year'] . ' - ' . $event['title'];

						// Event needs data-event-id for modal.
						$event_id = $event['id'] ?? '';

						$hidden_class = $event_index >= 3 ? ' cts-event-dot-hidden' : '';
						echo '<div class="cts-event-dot' . esc_attr( $hidden_class ) . '" style="background-color: ' . esc_attr( $color ) . '" title="' . esc_attr( $title ) . '" data-event-id="' . esc_attr( $event_id ) . '">';
						echo '<span class="cts-event-time">' . esc_html( $event['start_time'] ) . '</span>';
						echo '<span class="cts-event-title-small">' . esc_html( wp_trim_words( $event['title'], 3 ) ) . '</span>';
						echo '</div>';
					}
					if ( count( $events_by_date[ $date ] ) > 3 ) {
						$remaining = count( $events_by_date[ $date ] ) - 3;
						echo '<button type="button" class="cts-more-events" aria-expanded="false"><span class="cts-more-events-show">+' . esc_html( (string) ( $remaining ) ) . ' mehr</span><span class="cts-more-events-hide" hidden>Weniger anzeigen</span></button>';
					}
					echo '</div>';
				}

				echo '</div>';
			}

			$html = ob_get_clean();

			ChurchTools_Suite_Logger::debug(
				'ajax_calendar',
				'Grid HTML generated',
				array(
					'html_length' => strlen( $html ),
				)
			);

			// Generate month name for JavaScript.
			$timestamp  = mktime( 0, 0, 0, $month, 1, $year );
			$month_name = date_i18n( 'F Y', $timestamp );

			wp_send_json_success(
				array(
					'html'       => $html,
					'month'      => $month,
					'year'       => $year,
					'month_name' => $month_name,
				)
			);

		} catch ( Exception $e ) {
			// Cleanup output buffer.
			if ( ob_get_level() > 0 ) {
				ob_end_clean();
			}

			// Log error.
			ChurchTools_Suite_Logger::error(
				'ajax_calendar',
				'AJAX Calendar Error',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);

			wp_send_json_error(
				array(
					'message' => __( 'Fehler beim Laden des Kalenders', 'churchtools-suite' ),
					'error'   => WP_DEBUG ? $e->getMessage() : '',
					'trace'   => WP_DEBUG ? $e->getTraceAsString() : '',
				)
			);
		}
	}


	/**
	 * Get available modal templates by scanning filesystem
	 *
	 * @since 0.9.9.82
	 * @return array List of available template names (e.g., ['professional'])
	 */
	private static function get_available_modal_templates(): array {
		$template_dir = CHURCHTOOLS_SUITE_PATH . 'templates/views/event-modal/';

		if ( ! is_dir( $template_dir ) ) {
			return array();
		}

		$templates        = array();
		$canonical_prefix = 'modal-';
		$files            = scandir( $template_dir );

		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				// Only PHP files, exclude dotfiles.
				if ( substr( $file, -4 ) === '.php' && '.' !== $file[0] ) {
					// Remove .php extension to get template name.
					$template_name = substr( $file, 0, -4 );

					if ( strpos( $template_name, $canonical_prefix ) === 0 ) {
						// Canonical file name: modal-professional.php -> professional.
						$template_name = substr( $template_name, strlen( $canonical_prefix ) );
					} else {
						// Skip wrapper if canonical file exists.
						$prefixed_file = $template_dir . $canonical_prefix . $template_name . '.php';
						if ( file_exists( $prefixed_file ) ) {
							continue;
						}
					}

					$templates[ $template_name ] = true;
				}
			}
		}

		$templates = array_keys( $templates );
		sort( $templates ); // Alphabetical order.
		return $templates;
	}

	/**
	 * Show disclaimer notice in admin dashboard
	 *
	 * Displays an informational notice about software liability and usage terms
	 * Only shown on ChurchTools Suite admin pages
	 * Can be dismissed by user
	 *
	 * @since 1.0.4.0
	 */
	/**
	 * Redirect to documentation
	 *
	 * @since 1.0.3.16
	 */
	public function redirect_to_documentation() {
		if ( ! current_user_can( 'manage_churchtools_suite' ) ) {
			wp_die( esc_html__( 'Sie haben keine Berechtigung, auf diese Seite zuzugreifen.', 'churchtools-suite' ) );
		}

		/* phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Intentional redirect to the fixed plugin documentation site on an external host. */ wp_redirect( 'https://plugin.feg-aschaffenburg.de/' );
		exit;
	}

	/**
	 * Display Disclaimer admin page
	 *
	 * @since 1.0.3.16
	 */
	public function display_disclaimer_page() {
		if ( ! current_user_can( ChurchTools_Suite_Admin_Screen::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sie haben keine Berechtigung, auf diese Seite zuzugreifen.', 'churchtools-suite' ) );
		}

		self::render_disclaimer_content();
	}

	/**
	 * Disclaimer content, rendered inside the page shell.
	 *
	 * @since 1.4.0.0
	 */
	public static function render_disclaimer_content() {
		?>
		<div class="cts-stack cts-disclaimer">
			<div class="cts-callout is-warning">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<p><?php esc_html_e( 'Dieses Plugin wird ohne Gewährleistung bereitgestellt. ChurchTools ist eine Marke der ChurchTools GmbH; dieses Projekt steht in keiner Verbindung dazu.', 'churchtools-suite' ); ?></p>
			</div>

			<div class="cts-card">
				<div style="background: #fff; padding: 2rem; border-radius: 4px; border-left: 4px solid #ff6b6b;">
					<h2><?php esc_html_e( 'Nutzung ohne Gewährleistung', 'churchtools-suite' ); ?></h2>
					<p><?php esc_html_e( 'Dieses Plugin (ChurchTools Suite) wird ohne jegliche Gewährleistung bereitgestellt ("as is"). Die Nutzung erfolgt auf eigenes Risiko.', 'churchtools-suite' ); ?></p>
				</div>

				<div style="background: #fff; padding: 2rem; border-radius: 4px; margin-top: 2rem; border-left: 4px solid #ff6b6b;">
					<h2><?php esc_html_e( 'Keine Haftung für:', 'churchtools-suite' ); ?></h2>
					<ul style="list-style-type: disc; margin-left: 2rem; line-height: 1.8;">
						<li><?php esc_html_e( 'Datenverlust oder Datenschäden', 'churchtools-suite' ); ?></li>
						<li><?php esc_html_e( 'Systemausfälle oder Fehlfunktionen', 'churchtools-suite' ); ?></li>
						<li><?php esc_html_e( 'Fehlerhafte oder unvollständige Darstellung von Inhalten', 'churchtools-suite' ); ?></li>
						<li><?php esc_html_e( 'Sicherheitsprobleme oder unbefugter Zugriff', 'churchtools-suite' ); ?></li>
						<li><?php esc_html_e( 'Inkompatibilität mit anderen Plugins oder Themes', 'churchtools-suite' ); ?></li>
						<li><?php esc_html_e( 'Unterbrechung oder Verzögerung des Service', 'churchtools-suite' ); ?></li>
					</ul>
				</div>

				<div style="background: #fff; padding: 2rem; border-radius: 4px; margin-top: 2rem; border-left: 4px solid #ffa500;">
					<h2><?php esc_html_e( 'Unabhängiges Projekt', 'churchtools-suite' ); ?></h2>
					<p>
						<strong><?php esc_html_e( 'ChurchTools ist eine registrierte Marke der ChurchTools GmbH.', 'churchtools-suite' ); ?></strong><br>
						<?php esc_html_e( 'Dieses Projekt steht in keiner Verbindung zu oder Unterstützung durch die ChurchTools GmbH. Die ChurchTools GmbH übernimmt keine Verantwortung für dieses Projekt oder dessen Verwendung.', 'churchtools-suite' ); ?>
					</p>
				</div>

				<div style="background: #fff; padding: 2rem; border-radius: 4px; margin-top: 2rem; border-left: 4px solid #4CAF50;">
					<h2><?php esc_html_e( 'Ihre Verantwortung', 'churchtools-suite' ); ?></h2>
					<ul style="list-style-type: disc; margin-left: 2rem; line-height: 1.8;">
						<li><?php esc_html_e( 'Führen Sie regelmäßig Backups Ihrer WordPress-Installation durch', 'churchtools-suite' ); ?></li>
						<li><?php esc_html_e( 'Testen Sie Updates in einer Test-Umgebung, bevor Sie diese auf der Live-Site einspielen', 'churchtools-suite' ); ?></li>
						<li><?php esc_html_e( 'Überprüfen Sie die Kompatibilität mit anderen aktiven Plugins', 'churchtools-suite' ); ?></li>
						<li><?php esc_html_e( 'Halten Sie WordPress, PHP und alle Plugins aktuell', 'churchtools-suite' ); ?></li>
						<li><?php esc_html_e( 'Lesen Sie die Dokumentation und Anleitung gründlich', 'churchtools-suite' ); ?></li>
						<li><?php esc_html_e( 'Melden Sie Bugs und Probleme auf GitHub, um zur Verbesserung beizutragen', 'churchtools-suite' ); ?></li>
					</ul>
				</div>

				<div style="background: #f0f0f0; padding: 2rem; border-radius: 4px; margin-top: 2rem;">
					<h3><?php esc_html_e( 'Weitere Informationen', 'churchtools-suite' ); ?></h3>
					<p>
						<strong><?php esc_html_e( 'Lizenz:', 'churchtools-suite' ); ?></strong> 
						<a href="https://www.gnu.org/licenses/gpl-2.0.html" target="_blank">GPL v2 or later</a>
					</p>
					<p>
						<strong><?php esc_html_e( 'GitHub Repository:', 'churchtools-suite' ); ?></strong> 
						<a href="https://github.com/feg-aschaffenburg/churchtools-suite" target="_blank">feg-aschaffenburg/churchtools-suite</a>
					</p>
					<p>
						<strong><?php esc_html_e( 'Dokumentation:', 'churchtools-suite' ); ?></strong> 
						<a href="https://github.com/feg-aschaffenburg/churchtools-suite/blob/main/README.md" target="_blank">README.md auf GitHub</a>
					</p>
					<p>
						<strong><?php esc_html_e( 'Feedback & Bug-Reports:', 'churchtools-suite' ); ?></strong> 
						<a href="https://github.com/feg-aschaffenburg/churchtools-suite/issues" target="_blank">GitHub Issues</a>
					</p>
				</div>

				<div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #ddd; color: #666; font-size: 0.9em;">
					<p><?php esc_html_e( 'Stand:', 'churchtools-suite' ); ?> <?php echo esc_html( gmdate( 'd. F Y', strtotime( '2026-01-14' ) ) ); ?> |
						<?php esc_html_e( 'Version:', 'churchtools-suite' ); ?> <?php echo esc_html( CHURCHTOOLS_SUITE_VERSION ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX Handler: Install Addon from GitHub
	 *
	 * Downloads and installs a sub-plugin from GitHub releases
	 *
	 * @since 1.0.9.1
	 * @throws Exception When the operation fails.
	 */
	public function ajax_install_addon() {
		check_ajax_referer( 'churchtools_suite_admin', 'nonce' );
		wp_send_json_error( array( 'message' => __( 'Bitte die Erstinstallation mit einem geprüften Release-ZIP über die WordPress-Plugin-Verwaltung durchführen.', 'churchtools-suite' ) ) );
	}

	/** Legacy cache button is now an explicitly authorized, throttled check. */
	public function ajax_clear_addon_update_cache() {
		$this->ajax_manual_update();
	}

	/** Retired custom addon installer. */
	public function ajax_update_addon() {
		$this->ajax_run_update();
	}
}
