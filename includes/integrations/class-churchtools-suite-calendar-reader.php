<?php
/**
 * Public local calendar reader. Reads existing events, including events imported from other systems.
 *
 * @package ChurchTools_Suite
 */

declare(strict_types=1);

final class ChurchTools_Suite_Calendar_Reader {

	/** @return list<stdClass> */
	public function calendars(): array {
		/** @var ChurchTools_Suite_Calendars_Repository $repository */
		$repository = churchtools_suite_get_repository( 'calendars' );
		/** @var list<stdClass> $calendars wpdb OBJECT rows. */
		$calendars = $repository->get_selected();
		return $calendars;
	}

	/**
	 * Half-open overlap query. Bounds are UTC for timed events and floating dates for all-day events.
	 * No provider API calls, sync writes, raw payloads or service/person data are exported.
	 *
	 * @param array $calendar_ids Authorized external calendar IDs.
	 * @param array $query Normalized query options (see docs/CLOUDCALENDAR.md).
	 * @phpstan-param list<string> $calendar_ids
	 * @phpstan-param array<string,mixed> $query
	 * @throws RuntimeException When the database query fails.
	 * @return array{items: list<stdClass>, total: int}
	 */
	public function query( array $calendar_ids, array $query ): array {
		global $wpdb;
		if ( array() === $calendar_ids ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}
		$events    = $wpdb->prefix . CHURCHTOOLS_SUITE_DB_PREFIX . 'events';
		$calendars = $wpdb->prefix . CHURCHTOOLS_SUITE_DB_PREFIX . 'calendars';
		$holders   = implode( ',', array_fill( 0, count( $calendar_ids ), '%s' ) );
		$end       = 'CASE WHEN e.end_datetime IS NULL OR e.end_datetime <= e.start_datetime THEN DATE_ADD(e.start_datetime, INTERVAL CASE WHEN e.is_all_day = 1 THEN 86400 ELSE 1 END SECOND) ELSE e.end_datetime END';
		$where     = "c.is_selected = 1 AND e.calendar_id IN ({$holders}) AND (e.status IS NULL OR e.status NOT IN ('cancelled','canceled','deleted','inactive'))";
		$args      = $calendar_ids;
		if ( isset( $query['id'] ) ) {
			$where .= ' AND e.id = %d';
			$args[] = (int) $query['id'];
		} else {
			$where .= " AND ((e.is_all_day = 0 AND e.start_datetime < %s AND ({$end}) > %s) OR (e.is_all_day = 1 AND e.start_datetime < %s AND ({$end}) > %s))";
			array_push( $args, $query['end'], $query['start'], $query['floating_end'], $query['floating_start'] );
			if ( ! empty( $query['ended_before_range_end'] ) ) {
				$where .= " AND ((e.is_all_day = 0 AND ({$end}) <= %s) OR (e.is_all_day = 1 AND ({$end}) <= %s))";
				array_push( $args, $query['end'], $query['floating_end'] );
			}
		}
		foreach ( array( 'search', 'location' ) as $field ) {
			if ( ! empty( $query[ $field ] ) ) {
				$like = '%' . $wpdb->esc_like( (string) $query[ $field ] ) . '%';
				if ( 'search' === $field ) {
					$where .= ' AND (e.title LIKE %s OR e.description LIKE %s OR e.event_description LIKE %s OR e.appointment_description LIKE %s OR e.location_name LIKE %s)';
					array_push( $args, $like, $like, $like, $like, $like );
				} else {
					$where .= ' AND e.location_name LIKE %s';
					$args[] = $like;
				}
			}
		}
		$exclude = array_values( array_filter( array_map( 'strval', (array) ( $query['exclude'] ?? array() ) ), 'ctype_digit' ) );
		if ( array() !== $exclude ) {
			$where .= ' AND e.id NOT IN (' . implode( ',', array_fill( 0, count( $exclude ), '%d' ) ) . ')';
			$args   = array_merge( $args, $exclude );
		}
		$direction = ! empty( $query['descending'] ) ? 'DESC' : 'ASC';
		$order     = "CASE WHEN e.is_all_day = 1 THEN DATE_SUB(e.start_datetime, INTERVAL %d SECOND) ELSE e.start_datetime END {$direction}, FIELD(e.calendar_id, {$holders}) ASC, CAST(e.id AS CHAR) ASC";
		$columns   = 'e.id, e.calendar_id, e.title, e.description, e.event_description, e.appointment_description, e.start_datetime, e.is_all_day, e.status, e.location_name, e.image_attachment_id';
		// All interpolated SQL fragments are fixed expressions or placeholder lists; data is prepared below.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$from  = "FROM {$events} e INNER JOIN {$calendars} c ON c.calendar_id = e.calendar_id WHERE {$where}";
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$from}", $args ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$columns}, ({$end}) AS end_datetime {$from} ORDER BY {$order} LIMIT %d OFFSET %d",
				array_merge( $args, array( (int) ( $query['sort_offset'] ?? 0 ) ), $calendar_ids, array( max( 1, (int) ( $query['limit'] ?? 100 ) ), max( 0, (int) ( $query['offset'] ?? 0 ) ) ) )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		if ( '' !== $wpdb->last_error ) {
			throw new RuntimeException( 'Local calendar query failed.' );
		}
		/** @var list<stdClass> $items wpdb OBJECT rows. */
		$items = is_array( $rows ) ? array_values( $rows ) : array();
		return array(
			'items' => $items,
			'total' => $total,
		);
	}
}
