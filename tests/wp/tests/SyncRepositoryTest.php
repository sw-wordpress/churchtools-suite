<?php
/** Database regressions for sync identity and user-maintained calendar settings. */
final class SyncRepositoryTest extends CTS_TestCase {
	public function test_recurring_appointments_keep_each_occurrence_and_update_only_the_matching_date(): void {
		$repo = churchtools_suite_get_repository( 'events' );
		$first = array( 'calendar_id' => 'series-calendar', 'appointment_id' => 'recurring-series', 'start_datetime' => '2031-01-05 10:00:00', 'title' => 'First occurrence' );
		$second = array_replace( $first, array( 'start_datetime' => '2031-01-12 10:00:00', 'title' => 'Second occurrence' ) );
		$first_id = $repo->upsert_by_appointment_id( $first );
		$second_id = $repo->upsert_by_appointment_id( $second );
		$this->assertIsInt( $first_id );
		$this->assertIsInt( $second_id );
		$this->assertNotSame( $first_id, $second_id );
		$updated_id = $repo->upsert_by_appointment_id( array_replace( $first, array( 'title' => 'Changed first occurrence', 'end_datetime' => '2031-01-05 12:00:00', 'event_description' => 'Updated description', 'status' => 'active' ) ) );
		$this->assertSame( $first_id, $updated_id );
		$updated = $repo->get_by_id( $first_id );
		$this->assertSame( 'Changed first occurrence', $updated->title );
		$this->assertSame( '2031-01-05 12:00:00', $updated->end_datetime );
		$this->assertSame( 'Updated description', $updated->event_description );
		$this->assertSame( 'Second occurrence', $repo->get_by_id( $second_id )->title );
	}

	public function test_incomplete_appointment_identity_does_not_write_rows(): void {
		$repo = churchtools_suite_get_repository( 'events' );
		$before = $repo->count();
		$this->assertFalse( $repo->upsert_by_appointment_id( array( 'appointment_id' => 'missing-date' ) ) );
		$this->assertFalse( $repo->upsert_by_appointment_id( array( 'start_datetime' => '2031-01-05 10:00:00' ) ) );
		$this->assertSame( $before, $repo->count() );
	}

	public function test_refresh_preserves_selected_calendar_and_its_fallback_image(): void {
		$repo = churchtools_suite_get_repository( 'calendars' );
		$id = $repo->upsert_by_calendar_id( array( 'calendar_id' => 'selection-regression', 'name' => 'Original', 'is_selected' => 1 ) );
		$this->assertTrue( $repo->update_calendar_image_by_calendar_id( 'selection-regression', 123 ) );
		$refreshed_id = $repo->upsert_by_calendar_id( array( 'calendar_id' => 'selection-regression', 'name' => 'Renamed', 'color' => '#123456', 'is_selected' => 0 ) );
		$this->assertSame( $id, $refreshed_id );
		$calendar = $repo->get_by_calendar_id( 'selection-regression' );
		$this->assertSame( 'Renamed', $calendar->name );
		$this->assertSame( '#123456', $calendar->color );
		$this->assertSame( 1, (int) $calendar->is_selected );
		$this->assertSame( 123, (int) $calendar->calendar_image_id );
	}

	public function test_refresh_does_not_select_a_calendar_the_user_excluded(): void {
		$repo = churchtools_suite_get_repository( 'calendars' );
		$repo->upsert_by_calendar_id( array( 'calendar_id' => 'excluded-regression', 'name' => 'Excluded', 'is_selected' => 0 ) );
		$repo->upsert_by_calendar_id( array( 'calendar_id' => 'excluded-regression', 'name' => 'Updated', 'is_selected' => 1 ) );
		$this->assertSame( 0, (int) $repo->get_by_calendar_id( 'excluded-regression' )->is_selected );
		$this->assertNotContains( 'excluded-regression', $repo->get_selected_calendar_ids() );
	}
}
