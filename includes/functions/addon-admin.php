<?php
/**
 * Addon metadata and update helpers used by the admin views.
 *
 * @package ChurchTools_Suite
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve canonical addon metadata for monorepo setup.
 *
 * @param string $plugin_slug Addon slug
 * @param string $plugin_uri  Plugin URI from header
 * @param string $plugin_file Plugin file path key from get_plugins()
 * @return array{github_repo:string,details_url:string}
 */
function cts_resolve_addon_meta( $plugin_slug, $plugin_uri = '', $plugin_file = '' ) {
	$plugin_slug = (string) $plugin_slug;
	$plugin_file = (string) $plugin_file;

	if (
		strpos( $plugin_slug, 'churchtools-suite-elementor' ) === 0
		|| strpos( $plugin_file, '/churchtools-suite-elementor.php' ) !== false
	) {
		return array(
			'github_repo' => 'feg-aschaffenburg/churchtools-suite-elementor',
			'details_url' => 'https://github.com/feg-aschaffenburg/churchtools-suite-elementor',
		);
	}

	if (
		strpos( $plugin_slug, 'churchtools-suite-posts-sync' ) === 0
		|| strpos( $plugin_file, '/churchtools-suite-posts-sync.php' ) !== false
	) {
		return array(
			'github_repo' => 'feg-aschaffenburg/churchtools-suite-posts-sync',
			'details_url' => 'https://github.com/feg-aschaffenburg/churchtools-suite-posts-sync',
		);
	}

	if (
		strpos( $plugin_slug, 'churchtools-suite-presentations' ) === 0
		|| strpos( $plugin_file, '/churchtools-suite-presentations.php' ) !== false
	) {
		return array(
			'github_repo' => 'feg-aschaffenburg/churchtools-suite-presentations',
			'details_url' => 'https://github.com/feg-aschaffenburg/churchtools-suite-presentations',
		);
	}

	$known_addons = array(
		'churchtools-suite-elementor'     => array(
			'github_repo' => 'feg-aschaffenburg/churchtools-suite-elementor',
			'details_url' => 'https://github.com/feg-aschaffenburg/churchtools-suite-elementor',
		),
		'churchtools-suite-posts-sync'    => array(
			'github_repo' => 'feg-aschaffenburg/churchtools-suite-posts-sync',
			'details_url' => 'https://github.com/feg-aschaffenburg/churchtools-suite-posts-sync',
		),
		'churchtools-suite-presentations' => array(
			'github_repo' => 'feg-aschaffenburg/churchtools-suite-presentations',
			'details_url' => 'https://github.com/feg-aschaffenburg/churchtools-suite-presentations',
		),
	);

	if ( isset( $known_addons[ $plugin_slug ] ) ) {
		return $known_addons[ $plugin_slug ];
	}

	$github_repo = '';
	if ( ! empty( $plugin_uri ) && preg_match( '#github\.com/([^/]+/[^/]+)#', $plugin_uri, $matches ) ) {
		$github_repo = $matches[1];
	}

	return array(
		'github_repo' => $github_repo,
		'details_url' => (string) $plugin_uri,
	);
}

/**
 * Check for addon updates via GitHub API
 *
 * @param string $github_repo Repository in format 'owner/repo'
 * @param string $current_version Current plugin version
 * @param string $plugin_slug Addon plugin slug for asset prefix matching
 * @return array<string, mixed>|false Update info or false if no update
 */
function cts_check_addon_update( $github_repo, $current_version, $plugin_slug = '' ) {
	if ( empty( $github_repo ) || empty( $current_version ) || empty( $plugin_slug ) ) {
		return false;
	}

	$current_clean = ltrim( (string) $current_version, 'vV' );

	// Check transient cache first (1 hour).
	$cache_key = 'cts_addon_update_' . sanitize_key( $github_repo . '_' . $plugin_slug . '_' . $current_clean );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		if ( is_array( $cached ) && ! empty( $cached['latest_version'] ) ) {
			$cached_latest = ltrim( (string) $cached['latest_version'], 'vV' );
			if ( ! version_compare( $cached_latest, $current_clean, '>' ) ) {
				return false;
			}
		}
		return $cached;
	}

	$api_url  = sprintf( 'https://api.github.com/repos/%s/releases/latest', $github_repo );
	$response = wp_remote_get(
		$api_url,
		array(
			'timeout' => 10,
			'headers' => array( 'User-Agent' => 'ChurchTools-Suite-Addon-Updater' ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( empty( $data['tag_name'] ) ) {
		return false;
	}

	$release_version = ltrim( $data['tag_name'], 'v' );
	$current_clean   = ltrim( $current_version, 'vV' );

	$asset_prefix = $plugin_slug . '-';

	// Find ZIP asset and derive addon version from asset name in monorepo releases.
	$zip_url       = '';
	$asset_version = '';
	if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
		foreach ( $data['assets'] as $asset ) {
			if ( isset( $asset['browser_download_url'], $asset['name'] )
				&& strpos( $asset['name'], $asset_prefix ) === 0
				&& str_ends_with( $asset['name'], '.zip' ) ) {
				$zip_url = $asset['browser_download_url'];

				if ( preg_match( '/^' . preg_quote( $plugin_slug, '/' ) . '-(.+)\\.zip$/', (string) $asset['name'], $matches ) ) {
					$asset_version = ltrim( (string) $matches[1], 'v' );
				}

				break;
			}
		}
	}

	if ( empty( $zip_url ) ) {
		set_transient( $cache_key, false, HOUR_IN_SECONDS );
		return false;
	}

	$latest_version = '' !== $asset_version ? $asset_version : $release_version;

	if ( ! version_compare( $latest_version, $current_clean, '>' ) ) {
		set_transient( $cache_key, false, HOUR_IN_SECONDS );
		return false;
	}

	$update_info = array(
		'current_version' => $current_clean,
		'latest_version'  => $latest_version,
		'tag_name'        => $data['tag_name'],
		'zip_url'         => $zip_url,
		'html_url'        => $data['html_url'] ?? '',
		'body'            => $data['body'] ?? '',
	);

	// Cache for 1 hour.
	set_transient( $cache_key, $update_info, HOUR_IN_SECONDS );

	return $update_info;
}

/**
 * Get all addon plugins
 *
 * Detects addons by:
 * - Plugin slug starts with 'churchtools-suite-'
 * - OR Plugin header contains 'Requires Plugins: churchtools-suite'
 *
 * Excludes:
 * - Main plugin (churchtools-suite)
 *
 * @return array<string, array<string, mixed>> Array of addon plugin data
 */
function cts_get_addon_plugins() {
	$all_plugins = get_plugins();
	$addons      = array();

	foreach ( $all_plugins as $plugin_file => $plugin_data ) {
		$plugin_slug = dirname( $plugin_file );

		// Skip main plugin itself.
		if ( 'churchtools-suite' === $plugin_slug || 'churchtools-suite/churchtools-suite.php' === $plugin_file ) {
			continue;
		}

		// Rollback copies are not addons, even when they were accidentally left in wp-content/plugins.
		if ( preg_match( '/^churchtools-suite(?:-before-v|-backup-|\.backup-)/', $plugin_slug ) ) {
			continue;
		}

		// Check if plugin is an addon for the integration suite.
		$is_addon = false;

		// Method 1: Plugin slug starts with 'churchtools-suite-'.
		if ( strpos( $plugin_slug, 'churchtools-suite-' ) === 0 ) {
			$is_addon = true;
		}

		// Method 2: Plugin requires churchtools-suite (WordPress 6.5+ header).
		if ( ! empty( $plugin_data['RequiresPlugins'] ) &&
			strpos( $plugin_data['RequiresPlugins'], 'churchtools-suite' ) !== false ) {
			$is_addon = true;
		}

		// Method 3: Plugin description mentions the integration suite.
		if ( ! empty( $plugin_data['Description'] ) &&
			( stripos( $plugin_data['Description'], 'Integration Suite' ) !== false ||
				stripos( $plugin_data['Description'], 'churchtools-suite' ) !== false ) ) {
			$is_addon = true;
		}

		if ( $is_addon ) {
			$addon_meta = cts_resolve_addon_meta( $plugin_slug, $plugin_data['PluginURI'] ?? '', $plugin_file );

			$addons[ $plugin_file ] = array_merge(
				$plugin_data,
				array(
					'plugin_file'       => $plugin_file,
					'plugin_slug'       => $plugin_slug,
					'is_active'         => is_plugin_active( $plugin_file ),
					'is_network_active' => is_plugin_active_for_network( $plugin_file ),
					'github_repo'       => $addon_meta['github_repo'],
					'details_url'       => $addon_meta['details_url'],
				)
			);
		}
	}

	return $addons;
}

/**
 * Ensure top table contains all known addons, even if not installed.
 *
 * @param array<string, array<string, mixed>> $addons Detected addons from installed plugins
 * @return array<string, array<string, mixed>>
 */
function cts_ensure_known_addons_in_table( array $addons ): array {
	$known = array(
		'churchtools-suite-elementor'     => array(
			'name'        => 'Elementor Integration',
			'description' => 'Elementor Page Builder Widget für Events aus dem integrierten System.',
			'details_url' => 'https://github.com/feg-aschaffenburg/churchtools-suite-elementor',
		),
		'churchtools-suite-posts-sync'    => array(
			'name'        => 'Posts Sync Addon',
			'description' => 'Synchronisiert Posts in WordPress-Posts/Seiten.',
			'details_url' => 'https://github.com/feg-aschaffenburg/churchtools-suite-posts-sync',
		),
		'churchtools-suite-presentations' => array(
			'name'        => 'Präsentation Addon',
			'description' => 'Präsentationsseiten für Termine und Informationen.',
			'details_url' => 'https://github.com/feg-aschaffenburg/churchtools-suite-presentations',
		),
	);

	$existing_slugs = array_map(
		static function ( $addon ) {
			return (string) ( $addon['plugin_slug'] ?? '' );
		},
		$addons
	);

	foreach ( $known as $slug => $meta ) {
		if ( in_array( $slug, $existing_slugs, true ) ) {
			continue;
		}

		$addons[ '__virtual__' . $slug ] = array(
			'Name'              => $meta['name'],
			'Description'       => $meta['description'],
			'Version'           => '—',
			'plugin_file'       => '',
			'plugin_slug'       => $slug,
			'is_active'         => false,
			'is_network_active' => false,
			'github_repo'       => 'feg-aschaffenburg/churchtools-suite-' . $slug,
			'details_url'       => $meta['details_url'],
			'is_virtual'        => true,
		);
	}

	return $addons;
}
