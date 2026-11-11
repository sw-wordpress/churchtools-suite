<?php
/**
 * Thin WordPress policy adapter. No state reads or network activity on frontend requests.
 *
 * @package ChurchTools_Suite
 */

declare( strict_types=1 );

final class ChurchTools_Suite_Update_Provider {
	public static function init(): void {
		add_filter( 'auto_update_plugin', array( self::class, 'automatic' ), PHP_INT_MAX, 2 );
		add_filter( 'plugin_auto_update_setting_html', array( self::class, 'automatic_html' ), 10, 2 );
		add_filter( 'upgrader_pre_download', array( self::class, 'download_guard' ), PHP_INT_MAX, 4 );
		add_filter( 'site_transient_update_plugins', array( self::class, 'offers' ) );
		add_filter( 'pre_set_site_transient_update_plugins', array( self::class, 'offers' ) );
		add_filter( 'update_plugins_github.com', array( self::class, 'update_uri' ), 10, 3 );
		add_filter( 'plugins_api', array( self::class, 'details' ), 10, 3 );
		add_filter( 'plugin_row_meta', array( self::class, 'row_meta' ), 10, 2 );
		add_action( 'admin_init', array( self::class, 'migrate' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_ajax_cts_check_github_updates', array( self::class, 'ajax_check' ) );
	}

	/**
	 * @return array<string,array{slug:string,version:string}>
	 */
	public static function descriptors(): array {
		return (array) apply_filters( 'cts_verified_update_descriptors', require __DIR__ . '/descriptors.php' );
	}

	private static function uri(): string {
		$source = require __DIR__ . '/source.php';
		return 'https://github.com/' . $source['repository'];
	}

	private static function load(): void {
		require_once __DIR__ . '/class-churchtools-suite-update-service.php';
	}

	/**
	 * @param mixed  $allowed
	 * @param object $item
	 * @return mixed
	 */
	public static function automatic( mixed $allowed, object $item ): mixed {
		return isset( self::descriptors()[ $item->plugin ?? '' ] ) ? false : $allowed;
	}

	public static function automatic_html( string $html, string $file ): string {
		return isset( self::descriptors()[ $file ] ) ? esc_html__( 'Nur manuelle Aktualisierung', 'churchtools-suite' ) : $html;
	}

	/**
	 * @param mixed               $reply
	 * @param string              $url Expected package URL.
	 * @param mixed               $upgrader
	 * @param array<string,mixed> $extra
	 * @return mixed
	 */
	public static function download_guard( mixed $reply, string $url, mixed $upgrader, array $extra = array() ): mixed {
		$descriptor = self::descriptors()[ $extra['plugin'] ?? '' ] ?? null;
		// Also catch a known provider URL when hook_extra is missing or was tampered with.
		$provider_url = str_starts_with( $url, self::uri() . '/releases/download/' );
		if ( null === $descriptor && ! $provider_url ) {
			return $reply;
		}
		if ( null === $descriptor || ! is_admin() || wp_doing_cron() || ! current_user_can( 'update_plugins' ) || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return new WP_Error( 'manual_update_required', 'GitHub-Installation nur manuell im WordPress-Backend erlaubt.' );
		}
		self::load();
		if ( ! ChurchTools_Suite_Update_Service::interactive() || ( isset( $upgrader->skin ) && is_a( $upgrader->skin, 'Automatic_Upgrader_Skin' ) && ! is_a( $upgrader->skin, 'WP_Ajax_Upgrader_Skin' ) ) ) {
			return new WP_Error( 'manual_update_required', 'Automatische GitHub-Installation ist gesperrt.' );
		}
		// Core authenticated the native single/bulk/updates-AJAX action before invoking its upgrader.
		$action        = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';
		$nonce_actions = array(
			'upgrade-plugin'    => 'upgrade-plugin_' . $extra['plugin'],
			'update-plugin'     => 'updates',
			'update-selected'   => 'bulk-update-plugins',
			'do-plugin-upgrade' => 'upgrade-core',
		);
		$nonce         = '';
		if ( isset( $_REQUEST['_ajax_nonce'] ) && is_string( $_REQUEST['_ajax_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) );
		} elseif ( isset( $_REQUEST['_wpnonce'] ) && is_string( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
		}
		if ( ! isset( $nonce_actions[ $action ] ) || ! wp_verify_nonce( $nonce, $nonce_actions[ $action ] ) ) {
			return new WP_Error( 'manual_update_required', 'Kein gültiger manueller WordPress-Updateauftrag.' );
		}
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed = WP_PLUGIN_DIR . '/' . $extra['plugin'];
		if ( is_file( $installed ) ) {
			$descriptor['version'] = get_plugin_data( $installed, false, false )['Version'];
		}
		return ChurchTools_Suite_Update_Service::download( $url, $descriptor );
	}

	/**
	 * @param mixed $transient
	 * @return mixed
	 */
	public static function offers( mixed $transient ): mixed {
		if ( ! is_admin() || wp_doing_cron() || ! current_user_can( 'update_plugins' ) || ! $transient instanceof stdClass ) {
			return $transient;
		}
		self::load();
		$state = ChurchTools_Suite_Update_Store::read();
		foreach ( self::descriptors() as $file => $descriptor ) {
			if ( ! isset( $transient->checked[ $file ] ) ) {
				continue;
			}
			unset( $transient->response[ $file ], $transient->no_update[ $file ] );
			if ( empty( $state['last_success'] ) || time() - $state['last_success'] > 7 * DAY_IN_SECONDS || empty( $state['manifest'] ) || ! ChurchTools_Suite_Update_Service::keys() ) {
				continue;
			}
			$package = ChurchTools_Suite_Update_Manifest::package( $state['manifest'], $descriptor['slug'] );
			if ( null === $package || ! ChurchTools_Suite_Update_Service::compatible( $package ) ) {
				continue;
			}
			$offer                         = (object) array(
				'id'           => self::uri(),
				'slug'         => $descriptor['slug'],
				'plugin'       => $file,
				'new_version'  => $package['version'],
				'version'      => $package['version'],
				'url'          => self::uri() . '/releases',
				'package'      => ChurchTools_Suite_Update_Manifest::url( $state['manifest'], $package ),
				'requires'     => $package['requires_wp'],
				'requires_php' => $package['requires_php'],
				'autoupdate'   => false,
			);
			$bucket                        = version_compare( $package['version'], $transient->checked[ $file ], '>' ) ? 'response' : 'no_update';
			$transient->{$bucket}[ $file ] = $offer;
		}
		return $transient;
	}

	/**
	 * @param mixed               $reply
	 * @param array<string,mixed> $data
	 * @return mixed
	 */
	public static function update_uri( mixed $reply, array $data, string $file ): mixed {
		if ( ! isset( self::descriptors()[ $file ] ) || ( $data['UpdateURI'] ?? '' ) !== self::uri() ) {
			return $reply;
		}
		$transient = self::offers(
			(object) array(
				'checked'   => array( $file => $data['Version'] ),
				'response'  => array(),
				'no_update' => array(),
			)
		);
		return $transient->response[ $file ] ?? $transient->no_update[ $file ] ?? false;
	}

	/**
	 * @param mixed  $reply
	 * @param string $action API action.
	 * @param mixed  $args
	 * @return mixed
	 */
	public static function details( mixed $reply, string $action, mixed $args ): mixed {
		if ( 'plugin_information' !== $action || ! is_admin() || ! current_user_can( 'update_plugins' ) ) {
			return $reply;
		}
		foreach ( self::descriptors() as $file => $descriptor ) {
			if ( ( $args->slug ?? '' ) !== $descriptor['slug'] ) {
				continue;
			}
			$offers = self::offers( (object) array( 'checked' => array( $file => $descriptor['version'] ) ) );
			$offer  = $offers->response[ $file ] ?? $offers->no_update[ $file ] ?? null;
			if ( null !== $offer ) {
				return (object) array(
					'name'          => $descriptor['slug'],
					'slug'          => $descriptor['slug'],
					'version'       => $offer->new_version,
					'requires'      => $offer->requires,
					'requires_php'  => $offer->requires_php,
					'download_link' => $offer->package,
					'sections'      => array( 'description' => esc_html__( 'Signiertes GitHub-Release. Installation nur manuell über WordPress.', 'churchtools-suite' ) ),
				);
			}
		}
		return $reply;
	}

	public static function ajax_check(): void {
		if ( false === check_ajax_referer( 'cts_verified_updates', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Die Sicherheitsprüfung ist abgelaufen oder ungültig. Bitte die Plugin-Liste neu laden und erneut prüfen.', 'churchtools-suite' ) ), 403 );
		}
		self::load();
		$manual = isset( $_POST['manual'] ) && '1' === $_POST['manual'];
		$result = ChurchTools_Suite_Update_Service::check( $manual );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( $result );
		}
	}

	/**
	 * Render update controls in each managed plugin's native metadata row.
	 *
	 * @param array<int|string,string> $meta Existing row links.
	 * @param string                   $file Plugin basename.
	 * @return array<int|string,string>
	 */
	public static function row_meta( array $meta, string $file ): array {
		if ( ! is_admin() || ! isset( self::descriptors()[ $file ] ) ) {
			return $meta;
		}
		self::load();
		if ( ! ChurchTools_Suite_Update_Service::interactive() ) {
			return $meta;
		}
		$ready  = (bool) ChurchTools_Suite_Update_Service::keys() && function_exists( 'sodium_crypto_sign_verify_detached' );
		$meta[] = '<span class="cts-verified-updates">'
			. '<button type="button" class="button-link" data-cts-update-check' . disabled( ! $ready, true, false ) . '>' . esc_html__( 'Jetzt auf Updates prüfen', 'churchtools-suite' ) . '</button>'
			. ' <span data-cts-update-result role="status" aria-live="polite" hidden></span>'
			. ' <a data-cts-update-reload href="' . esc_url( is_multisite() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' ) ) . '" hidden>' . esc_html__( 'Plugin-Liste neu laden, um verfügbare Updates anzuzeigen', 'churchtools-suite' ) . '</a>'
			. ' <span data-cts-update-status>' . esc_html( self::status_text() ) . '</span></span>';
		return $meta;
	}

	/** Display only locally cached status; never start a request while rendering. */
	public static function status_text(): string {
		self::load();
		if ( ! ChurchTools_Suite_Update_Service::keys() || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return __( 'GitHub-Updates sind gesperrt: Öffentlicher Release-Schlüssel oder Sodium fehlt.', 'churchtools-suite' );
		}
		$state = ChurchTools_Suite_Update_Store::read();
		if ( empty( $state['last_success'] ) ) {
			$text = __( 'Noch keine erfolgreiche Update-Prüfung.', 'churchtools-suite' );
		} else {
			/* translators: %s: time span, e.g. "2 Stunden". */
			$text = sprintf( __( 'Letzte erfolgreiche Prüfung: vor %s.', 'churchtools-suite' ), human_time_diff( (int) $state['last_success'] ) );
		}
		if ( ! empty( $state['error'] ) && is_string( $state['error'] ) ) {
			/* translators: %s: cached update error. */
			$text .= ' ' . sprintf( __( 'Letzter Fehler: %s', 'churchtools-suite' ), $state['error'] );
		}
		return $text;
	}

	public static function enqueue(): void {
		self::load();
		if ( ! ChurchTools_Suite_Update_Service::interactive() ) {
			return;
		}
		$state = ChurchTools_Suite_Update_Store::read();
		$ready = (bool) ChurchTools_Suite_Update_Service::keys() && function_exists( 'sodium_crypto_sign_verify_detached' );
		wp_enqueue_script( 'cts-verified-updates', CHURCHTOOLS_SUITE_URL . 'assets/js/churchtools-suite-updates.js', array(), CHURCHTOOLS_SUITE_VERSION, true );
		wp_add_inline_script(
			'cts-verified-updates',
			'window.ctsVerifiedUpdates=' . wp_json_encode(
				array(
					'url'       => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( 'cts_verified_updates' ),
					'due'       => $ready && time() - ( $state['last_attempt'] ?? 0 ) >= DAY_IN_SECONDS,
					'ready'     => $ready,
					'checking'  => __( 'Signierte Releases werden geprüft …', 'churchtools-suite' ),
					'completed' => __( 'Prüfung abgeschlossen.', 'churchtools-suite' ),
					'failed'    => __( 'Update-Prüfung fehlgeschlagen. Bitte später erneut prüfen.', 'churchtools-suite' ),
					'forbidden' => __( 'Update-Prüfung verweigert (HTTP 403). Bitte die Plugin-Liste neu laden und die Anmeldung prüfen.', 'churchtools-suite' ),
				)
			) . ';',
			'before'
		);
	}

	public static function migrate(): void {
		if ( ! current_user_can( 'update_plugins' ) || get_option( 'cts_verified_updates_migrated', false ) ) {
			return;
		}
		wp_clear_scheduled_hook( 'churchtools_suite_check_updates' );
		self::clear_automatic_selection();
		foreach ( array( 'churchtools_suite_auto_update_enabled', 'churchtools_suite_auto_update_level', 'churchtools_suite_update_interval', 'churchtools_suite_github_token' ) as $option ) {
			delete_option( $option );
		}
		foreach ( array( 'churchtools_suite_github_release', 'cts_elementor_latest_release', 'cts_posts_sync_latest_release' ) as $key ) {
			delete_transient( $key );
		}
		$transient = get_site_transient( 'update_plugins' );
		if ( $transient instanceof stdClass ) {
			foreach ( array_keys( self::descriptors() ) as $file ) {
				unset( $transient->response[ $file ], $transient->no_update[ $file ] );
			}
			set_site_transient( 'update_plugins', $transient );
		}
		update_option( 'cts_verified_updates_migrated', 1, false );
	}

	private static function clear_automatic_selection(): void {
		$selected = get_site_option( 'auto_update_plugins', array() );
		if ( is_array( $selected ) ) {
			update_site_option( 'auto_update_plugins', array_values( array_diff( $selected, array_keys( self::descriptors() ) ) ) );
		}
	}

	/** Remove executable cached offers before this plugin's guards stop loading. */
	public static function deactivate(): void {
		self::clear_automatic_selection();
		remove_filter( 'site_transient_update_plugins', array( self::class, 'offers' ) );
		remove_filter( 'pre_set_site_transient_update_plugins', array( self::class, 'offers' ) );
		$transient = get_site_transient( 'update_plugins' );
		if ( $transient instanceof stdClass ) {
			foreach ( array_keys( self::descriptors() ) as $file ) {
				unset( $transient->response[ $file ], $transient->no_update[ $file ] );
			}
			set_site_transient( 'update_plugins', $transient );
		}
	}
}
ChurchTools_Suite_Update_Provider::init();
