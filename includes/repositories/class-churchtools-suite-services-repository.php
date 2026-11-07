<?php
/**
 * Dienste Repository
 *
 * Manages ChurchTools service master data (e.g., Predigt, Moderation, Musik).
 * Users can select which services to import during event sync.
 *
 * @package ChurchTools_Suite
 * @since   0.3.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChurchTools_Suite_Services_Repository extends ChurchTools_Suite_Repository_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( CHURCHTOOLS_SUITE_DB_PREFIX . 'services' );
	}

	/**
	 * Insert or update a service
	 *
	 * Uses service_id as unique key for upsert.
	 *
	 * @param array $data {
	 *     Service data
	 *
	 *     @type string $service_id        ChurchTools service ID (required)
	 *     @type string $service_group_id  ChurchTools service group ID
	 *     @type string $name              Service name (required)
	 *     @type string $name_translated   Translated service name
	 *     @type bool   $is_selected       Whether service is selected for sync
	 *     @type int    $sort_order        Sort order
	 *     @type string $raw_payload       Raw API response (JSON)
	 * }
	 * @return int|false Service ID on success, false on failure
	 */
	public function upsert( array $data ) {
		global $wpdb;

		// Validate required fields.
		if ( empty( $data['service_id'] ) || empty( $data['name'] ) ) {
			return false;
		}

		$table = $wpdb->prefix . $this->table;

		// Check if service already exists.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE service_id = %s",
				$data['service_id']
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		$defaults = array(
			'service_group_id' => null,
			'name_translated'  => null,
			'is_selected'      => 0,
			'sort_order'       => 0,
			'raw_payload'      => null,
		);
		$data     = wp_parse_args( $data, $defaults );

		// Prepare data.
		$db_data = array(
			'service_id'       => $data['service_id'],
			'service_group_id' => $data['service_group_id'],
			'name'             => $data['name'],
			'name_translated'  => $data['name_translated'],
			'is_selected'      => (int) $data['is_selected'],
			'sort_order'       => (int) $data['sort_order'],
			'raw_payload'      => $data['raw_payload'],
			'updated_at'       => current_time( 'mysql' ),
		);

		$format = array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' );

		if ( $existing ) {
			// Update existing service (preserve is_selected!).
			unset( $db_data['is_selected'] ); // Don't overwrite user selection.
			$format = array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
			$result = $wpdb->update(
				$table,
				$db_data,
				array( 'id' => $existing->id ),
				$format,
				array( '%d' )
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

			return false !== $result ? $existing->id : false;
		} else {
			// Insert new service.
			$db_data['created_at'] = current_time( 'mysql' );
			$format[]              = '%s';

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
			$result = $wpdb->insert( $table, $db_data, $format );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

			return $result ? $wpdb->insert_id : false;
		}
	}

	/**
	 * Get all selected services
	 *
	 * @return array Array of service objects
	 */
	public function get_selected(): array {
		global $wpdb;

		$table = $wpdb->prefix . $this->table;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$services = $wpdb->get_results(
			"SELECT * FROM {$table} 
			WHERE is_selected = 1 
			ORDER BY sort_order ASC, name ASC"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		return $services ?: array();
	}

	/**
	 * Get selected service IDs
	 *
	 * Returns array of service_id strings for easy checking.
	 *
	 * @return array Array of service_id strings
	 */
	public function get_selected_service_ids(): array {
		global $wpdb;

		$table = $wpdb->prefix . $this->table;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$ids = $wpdb->get_col(
			"SELECT service_id FROM {$table} 
			WHERE is_selected = 1"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		return $ids ?: array();
	}

	/**
	 * Update service selection
	 *
	 * Sets is_selected flag for multiple services at once.
	 *
	 * @param array $selected_ids Array of service_id strings to select
	 * @return bool True on success
	 */
	public function update_selection( array $selected_ids ): bool {
		global $wpdb;

		$table = $wpdb->prefix . $this->table;

		// First, deselect all.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$wpdb->query( "UPDATE {$table} SET is_selected = 0" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		// Then select specified ones.
		if ( ! empty( $selected_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $selected_ids ), '%s' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
			// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The IN clause contains a generated list of placeholders matched to the prepared values.
			$query = $wpdb->prepare(
				"UPDATE {$table} SET is_selected = 1 WHERE service_id IN ({$placeholders})",
				...$selected_ids
			);
			// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled from fixed clauses and values passed through wpdb::prepare above.
			$wpdb->query( $query );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		return true;
	}

	/**
	 * Get services by service group
	 *
	 * @param string $service_group_id Service group ID
	 * @return array Array of service objects
	 */
	public function get_by_service_group( string $service_group_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . $this->table;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$services = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
			WHERE service_group_id = %s 
			ORDER BY sort_order ASC, name ASC",
				$service_group_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		return $services ?: array();
	}

	/**
	 * Get service by ChurchTools service_id
	 *
	 * @param string $service_id ChurchTools service ID
	 * @return object|null Service object or null
	 */
	public function get_by_service_id( string $service_id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . $this->table;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE service_id = %s",
				$service_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		return $service ?: null;
	}

	/**
	 * Get total service count
	 *
	 * @return int Total number of services
	 */
	public function get_total_count(): int {
		global $wpdb;

		$table = $wpdb->prefix . $this->table;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		return (int) $count;
	}

	/**
	 * Get selected service count
	 *
	 * @return int Number of selected services
	 */
	public function get_selected_count(): int {
		global $wpdb;

		$table = $wpdb->prefix . $this->table;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_selected = 1" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		return (int) $count;
	}

	/**
	 * Delete all services
	 *
	 * Used for cleanup or resync. Dangerous operation!
	 *
	 * @return int|false Number of rows deleted or false on failure
	 */
	public function delete_all() {
		global $wpdb;

		$table = $wpdb->prefix . $this->table;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		return $wpdb->query( "DELETE FROM {$table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
	}
}
