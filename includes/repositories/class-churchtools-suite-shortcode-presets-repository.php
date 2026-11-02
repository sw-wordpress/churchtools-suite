<?php
/**
 * Shortcode Presets Repository
 *
 * Verwaltet gespeicherte Shortcode-Konfigurationen
 *
 * @package ChurchTools_Suite
 * @since   0.5.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChurchTools_Suite_Shortcode_Presets_Repository extends ChurchTools_Suite_Repository_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		parent::__construct( $wpdb->prefix . CHURCHTOOLS_SUITE_DB_PREFIX . 'shortcode_presets' );
	}

	/**
	 * Get all presets
	 *
	 * @return array
	 */
	public function get_all_presets(): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$results = $wpdb->get_results(
			"SELECT * FROM {$this->table} ORDER BY is_system DESC, name ASC",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( ! $results ) {
			return array();
		}

		// Decode JSON configuration.
		foreach ( $results as &$preset ) {
			$preset['configuration'] = json_decode( $preset['configuration'], true );
		}

		return $results;
	}

	/**
	 * Get preset by ID
	 *
	 * @param int $id Preset ID
	 * @return array|null
	 */
	public function get_preset_by_id( int $id ): ?array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( ! $result ) {
			return null;
		}

		$result['configuration'] = json_decode( $result['configuration'], true );

		return $result;
	}

	/**
	 * Get presets by shortcode tag
	 *
	 * @param string $shortcode_tag Shortcode tag (z.B. 'cts_list')
	 * @return array
	 */
	public function get_presets_by_tag( string $shortcode_tag ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE shortcode_tag = %s ORDER BY is_system DESC, name ASC",
				$shortcode_tag
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( ! $results ) {
			return array();
		}

		foreach ( $results as &$preset ) {
			$preset['configuration'] = json_decode( $preset['configuration'], true );
		}

		return $results;
	}

	/**
	 * Create new preset
	 *
	 * @param array $data Preset data
	 * @return int|false Preset ID or false on failure
	 */
	public function create_preset( array $data ) {
		global $wpdb;

		$insert_data = array(
			'name'          => $data['name'] ?? '',
			'description'   => $data['description'] ?? '',
			'shortcode_tag' => $data['shortcode_tag'] ?? '',
			'configuration' => wp_json_encode( $data['configuration'] ?? array() ),
			'custom_css'    => $data['custom_css'] ?? null,
			'is_system'     => $data['is_system'] ?? 0,
			'created_at'    => current_time( 'mysql' ),
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		$result = $wpdb->insert(
			$this->table,
			$insert_data,
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update preset
	 *
	 * @param int   $id Preset ID
	 * @param array $data Updated data
	 * @return bool
	 */
	public function update_preset( int $id, array $data ): bool {
		global $wpdb;

		$update_data = array();

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = $data['name'];
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = $data['description'];
		}

		if ( isset( $data['configuration'] ) ) {
			$update_data['configuration'] = wp_json_encode( $data['configuration'] );
		}

		if ( array_key_exists( 'custom_css', $data ) ) {
			$update_data['custom_css'] = $data['custom_css'];
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		$result = $wpdb->update(
			$this->table,
			$update_data,
			array( 'id' => $id ),
			array_fill( 0, count( $update_data ), '%s' ),
			array( '%d' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		return false !== $result;
	}

	/**
	 * Delete preset
	 *
	 * @param int $id Preset ID
	 * @return bool
	 */
	public function delete_preset( int $id ): bool {
		global $wpdb;

		// Don't delete system presets.
		$preset = $this->get_preset_by_id( $id );
		if ( $preset && $preset['is_system'] ) {
			return false;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		$result = $wpdb->delete(
			$this->table,
			array( 'id' => $id ),
			array( '%d' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		return false !== $result;
	}

	/**
	 * Create system presets
	 *
	 * Erstellt vordefinierte System-Presets für häufige Use Cases
	 */
	public function create_system_presets(): void {
		$system_presets = array(
			array(
				'name'          => 'Standard Liste',
				'description'   => 'Klassische Listen-Ansicht mit allen Details',
				'shortcode_tag' => 'cts_list',
				'configuration' => array(
					'view'          => 'classic',
					'limit'         => '20',
					'show_services' => 'true',
				),
				'is_system'     => 1,
			),
			array(
				'name'          => 'Kompakte Liste',
				'description'   => 'Kompakte Listen-Ansicht für Sidebar',
				'shortcode_tag' => 'cts_list',
				'configuration' => array(
					'view'          => 'medium',
					'limit'         => '5',
					'show_services' => 'false',
				),
				'is_system'     => 1,
			),
			array(
				'name'          => 'Event-Grid',
				'description'   => '3-spaltige Raster-Ansicht',
				'shortcode_tag' => 'cts_grid',
				'configuration' => array(
					'view'    => 'simple',
					'columns' => '3',
					'limit'   => '12',
				),
				'is_system'     => 1,
			),
			array(
				'name'          => 'Monatskalender',
				'description'   => 'Moderne Monatsübersicht',
				'shortcode_tag' => 'cts_calendar',
				'configuration' => array(
					'view' => 'monthly-modern',
				),
				'is_system'     => 1,
			),
		);

		foreach ( $system_presets as $preset ) {
			// Check if already exists.
			global $wpdb;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE name = %s AND is_system = 1",
					$preset['name']
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( ! $exists ) {
				$this->create_preset( $preset );
			}
		}
	}
}
