<?php
/**
 * First external implementation of CloudCalendar's local-source API v1.
 * Loaded only when CloudCalendar requests adapter registration.
 *
 * @package ChurchTools_Suite
 */

declare(strict_types=1);

use SW\Calendar\Application\Local\LocalEvent;
use SW\Calendar\Application\Local\LocalEventPage;
use SW\Calendar\Application\Local\LocalEventQuery;
use SW\Calendar\Application\Local\LocalEventSourceInterface;
use SW\Calendar\Domain\Event\OccurrenceStatus;
use SW\Calendar\Domain\Provider\CalendarDefinition;

final class ChurchTools_Suite_CloudCalendar_Source implements LocalEventSourceInterface {

	public function __construct( private readonly ChurchTools_Suite_Calendar_Reader $reader = new ChurchTools_Suite_Calendar_Reader() ) {
	}

	public function name(): string {
		return 'ChurchTools Integration Suite';
	}

	public function calendars(): array {
		return array_map(
			static fn ( stdClass $calendar ): CalendarDefinition => new CalendarDefinition(
				(string) $calendar->calendar_id,
				(string) $calendar->name,
				wp_timezone_string(),
				preg_match( '/^#[0-9a-f]{6}$/iD', (string) $calendar->color ) === 1 ? (string) $calendar->color : '#3366cc',
				false
			),
			$this->reader->calendars()
		);
	}

	public function canReadCalendar( string $calendar_id, ?int $user_id ): bool {
		// Selection is required; CloudCalendar additionally requires explicit publication/role approval.
		return in_array( $calendar_id, array_map( static fn ( stdClass $calendar ): string => (string) $calendar->calendar_id, $this->reader->calendars() ), true );
	}

	public function query( LocalEventQuery $query ): LocalEventPage {
		$result = $this->reader->query(
			$query->calendarIds, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- External CloudCalendar DTO contract.
			array(
				'start'                  => $query->range->startUtc->format( 'Y-m-d H:i:s' ),
				'end'                    => $query->range->endUtc->format( 'Y-m-d H:i:s' ),
				'floating_start'         => $query->range->floatingStart->format( 'Y-m-d H:i:s' ),
				'floating_end'           => $query->range->floatingEnd->format( 'Y-m-d H:i:s' ),
				'search'                 => $query->search,
				'location'               => $query->location,
				'exclude'                => $query->excludeIds, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- External CloudCalendar DTO contract.
				'descending'             => $query->descending,
				'limit'                  => $query->limit,
				'offset'                 => $query->offset,
				'sort_offset'            => $query->sortOffsetSeconds, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- External CloudCalendar DTO contract.
				'ended_before_range_end' => $query->endedBeforeRangeEnd, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- External CloudCalendar DTO contract.
			)
		);
		// Prime attachment posts and metadata in bulk, avoiding per-event database queries.
		$images = array_values( array_unique( array_filter( array_map( static fn ( stdClass $row ): int => (int) $row->image_attachment_id, $result['items'] ) ) ) );
		if ( array() !== $images ) {
			get_posts(
				array(
					'post_type'              => 'attachment',
					'post_status'            => 'inherit',
					'post__in'               => $images,
					'posts_per_page'         => count( $images ),
					'update_post_term_cache' => false,
				)
			);
		}
		return new LocalEventPage( array_map( array( $this, 'map' ), $result['items'] ), $result['total'] );
	}

	public function event( string $calendar_id, string $id ): ?LocalEvent {
		if ( ! ctype_digit( $id ) || ! $this->canReadCalendar( $calendar_id, null ) ) {
			return null;
		}
		$result = $this->reader->query(
			array( $calendar_id ),
			array(
				'id'    => $id,
				'limit' => 1,
			)
		);
		return isset( $result['items'][0] ) ? $this->map( $result['items'][0] ) : null;
	}

	private function map( stdClass $row ): LocalEvent {
		$description = trim( implode( "\n\n", array_filter( array( (string) $row->appointment_description, (string) $row->event_description, (string) $row->description ) ) ) );
		$image_id    = (int) $row->image_attachment_id;
		$image       = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'medium_large' ) : false;
		$utc         = new DateTimeZone( 'UTC' );
		return new LocalEvent(
			(string) $row->id,
			(string) $row->calendar_id,
			(string) $row->title,
			new DateTimeImmutable( (string) $row->start_datetime, $utc ),
			new DateTimeImmutable( (string) $row->end_datetime, $utc ),
			wp_timezone_string(),
			(bool) $row->is_all_day,
			OccurrenceStatus::fromProvider( (string) $row->status ),
			wp_strip_all_tags( $description ),
			$description,
			(string) $row->location_name,
			imageUrl: false === $image ? '' : $image,
			imageAlt: $image_id > 0 ? (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : ''
		);
	}
}
