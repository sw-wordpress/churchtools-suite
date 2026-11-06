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
	if ( ! is_admin() || ! current_user_can( 'update_plugins' ) ) {
		return false;
	}
	require_once CHURCHTOOLS_SUITE_PATH . 'includes/update/class-churchtools-suite-update-service.php';
	$manifest = ChurchTools_Suite_Update_Service::verified_snapshot();
	if ( is_wp_error( $manifest ) ) {
		return false;
	}
	$package = ChurchTools_Suite_Update_Manifest::package( $manifest, (string) $plugin_slug );
	if ( null === $package || ! version_compare( $package['version'], (string) $current_version, '>' ) ) {
		return false;
	}
	return array(
		'latest_version' => $package['version'],
		'zip_url'        => ChurchTools_Suite_Update_Manifest::url( $manifest, $package ),
		'html_url'       => 'https://github.com/' . ChurchTools_Suite_Update_Manifest::source()['repository'] . '/releases',
	);
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
