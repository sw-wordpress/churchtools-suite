<?php

use SW\Calendar\Application\Local\EventReference;
use SW\Calendar\Application\Local\LocalEventQuery;
use SW\Calendar\Application\Render\QueryRange;
use SW\Calendar\Domain\Connection\AuthMode;
use SW\Calendar\Domain\Connection\ConnectionStatus;
use SW\Calendar\Domain\Connection\Provider;
use SW\Calendar\Domain\Event\OccurrenceStatus;
use SW\Calendar\Domain\Source\SourceSettings;
use SW\Calendar\Domain\Source\SourceStatus;
use SW\Calendar\Domain\Source\Visibility;
use SW\Calendar\Infrastructure\Database\Schema;
use SW\Calendar\Presentation\Admin\ConnectionManager;
use SW\Calendar\Presentation\Admin\SourceManager;

final class CloudCalendarTest extends SWC_TestCase {
	private int $connection_id;
	private int $blue;
	private int $green;
	private int $first;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		foreach ( array( array( 'blue', '#4477dd' ), array( 'green', '#88ddbb' ) ) as $calendar ) {
			$wpdb->insert( $wpdb->prefix . 'cts_calendars', array( 'calendar_id' => $calendar[0], 'name' => ucfirst( $calendar[0] ), 'color' => $calendar[1], 'is_selected' => 1 ) );
		}
		$this->connection_id = $this->services->connections()->create( Provider::Local, 'Local', AuthMode::None, ConnectionStatus::Connected, array( 'adapter' => 'churchtools-suite' ) );
		$this->blue = $this->local_source( 'blue', '#4477dd' );
		$this->green = $this->local_source( 'green', '#88ddbb' );
		$this->first = $this->event( 'blue', 'First', '2026-11-01 10:00:00', '2026-11-01 11:00:00' );
		$this->event( 'green', 'Second', '2026-11-01 11:00:00', '2026-11-01 12:00:00' );
	}

	private function local_source( string $calendar, string $color ): int {
		$settings = new SourceSettings( syncEnabled: false );
		$id = $this->services->sources()->create( $this->connection_id, $calendar, ucfirst( $calendar ), $color, 'Europe/Berlin', Visibility::Public, array(), $settings );
		$this->services->sources()->setStatus( $id, SourceStatus::Active );
		return $id;
	}

	private function event( string $calendar, string $title, string $start, ?string $end, array $extra = array() ): int {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'cts_events', array_merge( array( 'calendar_id' => $calendar, 'appointment_id' => 'appointment-' . wp_rand(), 'title' => $title, 'start_datetime' => $start, 'end_datetime' => $end, 'status' => 'active', 'location_name' => 'Ludlstraße', 'appointment_description' => 'Description', 'event_description' => null, 'description' => null ), $extra ) );
		return (int) $wpdb->insert_id;
	}

	private function adapter(): ChurchTools_Suite_CloudCalendar_Source {
		return $this->services->local_sources()->get( 'churchtools-suite' );
	}

	private function query( array $ids = array( 'blue', 'green' ), ?string $search = null, ?string $location = null, array $exclude = array(), bool $descending = false, int $limit = 100, int $offset = 0, bool $ended = false ): LocalEventQuery {
		$range = QueryRange::fromLocal( new DateTimeImmutable( '2026-11-01', new DateTimeZone( 'Europe/Berlin' ) ), new DateTimeImmutable( '2026-12-01', new DateTimeZone( 'Europe/Berlin' ) ) );
		return new LocalEventQuery( $ids, $range, $search, $location, $exclude, $descending, $limit, $offset, 3600, $ended );
	}

	public function test_adapter_registration_calendars_and_no_second_copy(): void {
		$this->assertSame( 'ChurchTools Integration Suite', $this->adapter()->name() );
		$calendars = $this->adapter()->calendars();
		$this->assertSame( array( 'blue', 'green' ), array_map( static fn ( $c ) => $c->externalId, $calendars ) );
		$this->assertSame( '#4477dd', $calendars[0]->color );
		$this->assertFalse( $calendars[0]->publicAtProvider );
		$this->assertFalse( $this->adapter()->canReadCalendar( 'missing', null ) );
		$this->assertSame( 2, $this->adapter()->query( $this->query() )->total );
		global $wpdb;
		$this->assertSame( '0', $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'occurrences' ) ) );
	}

	public function test_overlap_exclusive_end_all_day_and_missing_end(): void {
		$this->event( 'blue', 'Ongoing', '2026-10-30 10:00:00', '2026-11-02 10:00:00' );
		$this->event( 'blue', 'Ends at boundary', '2026-10-30 10:00:00', '2026-10-31 23:00:00' );
		$this->event( 'blue', 'Starts at boundary', '2026-11-30 23:00:00', '2026-12-01 10:00:00' );
		$this->event( 'blue', 'All day', '2026-11-03 00:00:00', null, array( 'is_all_day' => 1 ) );
		$this->event( 'blue', 'Instant', '2026-11-04 12:00:00', null );
		$this->event( 'blue', 'Cancelled', '2026-11-01 10:00:00', '2026-11-01 11:00:00', array( 'status' => 'cancelled' ) );
		$result = $this->adapter()->query( $this->query() );
		$titles = array_map( static fn ( $e ) => $e->title, $result->items );
		$this->assertContains( 'Ongoing', $titles );
		$this->assertNotContains( 'Ends at boundary', $titles );
		$this->assertNotContains( 'Starts at boundary', $titles );
		$this->assertNotContains( 'Cancelled', $titles );
		$all_day = $this->adapter()->query( $this->query( search: 'All day' ) )->items[0];
		$this->assertTrue( $all_day->allDay );
		$this->assertSame( '2026-11-04', $all_day->end->format( 'Y-m-d' ) );
		$this->assertSame( 1, $this->adapter()->query( $this->query( search: 'Instant' ) )->total );
	}

	public function test_filters_sorting_pagination_and_detail_scope(): void {
		$this->assertSame( 1, $this->adapter()->query( $this->query( array( 'blue' ) ) )->total );
		$this->assertSame( 1, $this->adapter()->query( $this->query( search: 'First' ) )->total );
		$this->assertSame( 0, $this->adapter()->query( $this->query( location: 'elsewhere' ) )->total );
		$this->assertSame( 2, $this->adapter()->query( $this->query( location: 'Ludl' ) )->total );
		$this->assertSame( 1, $this->adapter()->query( $this->query( exclude: array( (string) $this->first ) ) )->total );
		$this->assertSame( 'Second', $this->adapter()->query( $this->query( limit: 1, offset: 1 ) )->items[0]->title );
		$this->assertSame( 'Second', $this->adapter()->query( $this->query( descending: true, limit: 1 ) )->items[0]->title );
		$this->assertSame( 2, $this->adapter()->query( $this->query( ended: true ) )->total );
		$this->assertSame( 0, $this->adapter()->query( $this->query( array() ) )->total );
		$this->assertSame( 'First', $this->adapter()->event( 'blue', (string) $this->first )->title );
		$this->assertNull( $this->adapter()->event( 'green', (string) $this->first ) );
		$this->assertNull( $this->adapter()->event( 'blue', 'invalid' ) );
	}

	public function test_renderer_details_colours_legends_and_live_updates(): void {
		$view = $this->view( array( $this->blue, $this->green ) );
		$result = $this->services->renderer()->render( $view, array( 'month' => '2026-11', 'color_titles' => true, 'legend' => true, 'columns' => 2, 'navigation_group' => 'mgwh' ), null );
		$this->assertStringContainsString( 'First', $result->html );
		$this->assertStringContainsString( 'Second', $result->html );
		$this->assertStringContainsString( 'swc-color-titles', $result->html );
		$this->assertStringContainsString( 'swc-columns-2', $result->html );
		$this->assertStringContainsString( 'swc-legend', $result->html );
		$this->assertStringContainsString( '#4477dd', $result->html );
		$this->assertStringContainsString( '#88ddbb', $result->html );
		$this->assertStringContainsString( 'data-swc-group="mgwh"', $result->html );
		$this->assertFalse( $result->public );
		$ref = EventReference::local( $this->blue, (string) $this->first );
		$detail = $this->rest( 'GET', '/events/' . $ref, array( 'view' => $view ) );
		$this->assertSame( 200, $detail->get_status() );
		$this->assertStringContainsString( 'First', $detail->get_data()['html'] );
		$this->assertStringContainsString( 'no-store', $detail->get_headers()['Cache-Control'] );
		$shortcode = do_shortcode( '[sw_calendar id="' . $view . '"]' );
		$this->assertStringContainsString( 'swc-dynamic', $shortcode );
		$this->assertStringNotContainsString( 'First', $shortcode, 'No private/local data leaks into page caches' );
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'cts_events', array( 'title' => 'Changed' ), array( 'id' => $this->first ) );
		$this->assertStringContainsString( 'Changed', $this->services->renderer()->render( $view, array( 'month' => '2026-11' ), null )->html );
		$wpdb->delete( $wpdb->prefix . 'cts_events', array( 'id' => $this->first ) );
		$this->assertSame( 404, $this->rest( 'GET', '/events/' . $ref, array( 'view' => $view ) )->get_status() );
	}

	public function test_calendar_deselection_and_view_scope_revoke_details(): void {
		$blue_view = $this->view( array( $this->blue ) );
		$green_view = $this->view( array( $this->green ) );
		$ref = EventReference::local( $this->blue, (string) $this->first );
		$this->assertSame( 404, $this->rest( 'GET', '/events/' . $ref, array( 'view' => $green_view ) )->get_status() );
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'cts_calendars', array( 'is_selected' => 0 ), array( 'calendar_id' => 'blue' ) );
		$this->assertSame( 404, $this->rest( 'GET', '/events/' . $ref, array( 'view' => $blue_view ) )->get_status() );
		$this->assertSame( 1, $this->adapter()->query( $this->query() )->total );
		$this->assertNull( $this->adapter()->event( 'blue', (string) $this->first ) );
	}

	public function test_source_administration_requires_explicit_publication_and_disables_sync(): void {
		$this->admin();
		$manager = new SourceManager( $this->services );
		$body = array( 'connection_id' => $this->connection_id, 'external_id' => 'green', 'name' => 'Public', 'visibility' => 'public' );
		try {
			$manager->save( null, $body );
			$this->fail( 'Publication confirmation required' );
		} catch ( \SW\Calendar\Presentation\Admin\ValidationException $e ) {
			$this->assertSame( 'required', $e->errors['confirm_publication'] );
		}
		// Use an existing source to avoid a duplicate external-calendar constraint.
		$source = $manager->save( $this->green, array( 'confirm_publication' => true, 'settings' => array( 'sync_enabled' => true ) ) );
		$this->assertFalse( $source->settings->syncEnabled );
		$this->assertFalse( $source->settings->attachments->syncAttachments );
		$this->assertTrue( $manager->to_array( $source )['local'] );
		$connection = ( new ConnectionManager( $this->services ) )->to_array( $this->services->connections()->find( $this->connection_id ) );
		$this->assertTrue( $connection['capabilities']['list_calendars'] );
		$this->expectException( \SW\Calendar\Presentation\Admin\ValidationException::class );
		$manager->request_sync( $this->blue );
	}

	public function test_month_overflow_is_accessible_without_javascript(): void {
		for ( $i = 0; $i < 5; ++$i ) {
			$this->event( 'blue', 'Extra ' . $i, '2026-11-01 12:00:00', '2026-11-01 13:00:00' );
		}
		$view = $this->view( array( $this->blue, $this->green ), 'month' );
		$html = $this->services->renderer()->render( $view, array( 'month' => '2026-11', 'day_limit' => 3 ), null )->html;
		$this->assertStringContainsString( '<details><summary>4 more</summary>', $html );
		$this->assertStringContainsString( '--swc-event-color:#88ddbb;--swc-event-text:#000000', $html );
		$this->assertStringContainsString( 'Extra 4', $html );
	}
	public function test_public_media_images_are_batched_and_never_copied(): void {
		$image = self::factory()->attachment->create_upload_object( ABSPATH . 'wp-includes/images/blank.gif' );
		update_post_meta( $image, '_wp_attachment_image_alt', 'Cafe preview' );
		for ( $i = 0; $i < 40; ++$i ) {
			$this->event( 'blue', 'Image ' . $i, '2026-11-05 12:00:00', '2026-11-05 13:00:00', array( 'image_attachment_id' => $image ) );
		}
		wp_cache_delete( $image, 'posts' );
		wp_cache_delete( $image, 'post_meta' );
		global $wpdb;
		$before = $wpdb->num_queries;
		$result = $this->adapter()->query( $this->query( search: 'Image' ) );
		$queries = $wpdb->num_queries - $before;
		$this->assertSame( 40, $result->total );
		$this->assertLessThanOrEqual( 8, $queries, 'Query count must not grow with the number of event images' );
		$this->assertSame( wp_get_attachment_image_url( $image, 'medium_large' ), $result->items[0]->imageUrl );
		$this->assertSame( 'Cafe preview', $result->items[0]->imageAlt );
		$view = $this->view( array( $this->blue ) );
		$events = $this->services->renderer()->events( $view, array( 'month' => '2026-11', 'search' => 'Image' ), null );
		$this->assertSame( 'Cafe preview', $events['events'][0]['image']['alt'] );
		$hidden = $this->services->renderer()->events( $view, array( 'month' => '2026-11', 'search' => 'Image', 'show_images' => false ), null );
		$this->assertNull( $hidden['events'][0]['image'] );
		$this->assertSame( '0', $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::table( 'occurrences' ) ) );
	}

	public function test_mixed_stored_and_live_pages_have_correct_global_counts_and_order(): void {
		$ics = $this->source( $this->ics_connection() );
		$this->sync( $ics );
		$range = $this->query()->range;
		$stored = $this->services->occurrences()->find( array( $ics ), $range, null, null, array(), false, 100, 0, 3600 );
		$all = $this->services->event_queries()->find( array( $this->blue, $this->green, $ics ), $range, null, null, array(), false, 100, 0, 3600 );
		$this->assertSame( $stored['total'] + 2, $all['total'] );
		$page = $this->services->event_queries()->find( array( $this->blue, $this->green, $ics ), $range, null, null, array(), false, 2, 1, 3600 );
		$this->assertSame( array_map( static fn ( $e ) => $e->id, array_slice( $all['items'], 1, 2 ) ), array_map( static fn ( $e ) => $e->id, $page['items'] ) );
		$this->assertSame( $all['total'], $page['total'] );
		$this->assertNotEmpty( array_filter( $all['items'], static fn ( $e ) => is_int( $e->id ) ) );
		$this->assertNotEmpty( array_filter( $all['items'], static fn ( $e ) => is_string( $e->id ) ) );
	}

	public function test_missing_adapter_and_connection_disconnect_are_closed(): void {
		$view = $this->view( array( $this->blue ) );
		$ref = EventReference::local( $this->blue, (string) $this->first );
		$this->services->connections()->setStatus( $this->connection_id, ConnectionStatus::Disconnected );
		$this->assertSame( 404, $this->rest( 'GET', '/events/' . $ref, array( 'view' => $view ) )->get_status() );
		$this->assertStringNotContainsString( 'First', $this->services->renderer()->render( $view, array( 'month' => '2026-11' ), null )->html );
		$this->services->connections()->setStatus( $this->connection_id, ConnectionStatus::Connected );
		$this->services->set( 'local_sources', new \SW\Calendar\Application\Local\LocalSourceRegistry() );
		$this->services->set( 'event_queries', new \SW\Calendar\Application\Local\LocalOccurrenceQuery( $this->services->occurrences(), $this->services->sources(), $this->services->connections(), $this->services->local_sources() ) );
		$this->admin();
		$disabled = ( new SourceManager( $this->services ) )->save( $this->blue, array( 'status' => 'disabled', 'confirm_publication' => true ) );
		$this->assertSame( SourceStatus::Disabled, $disabled->status );
		$renamed = ( new ConnectionManager( $this->services ) )->save( $this->connection_id, array( 'name' => 'Offline plugin' ) );
		$this->assertSame( 'Offline plugin', $renamed->name );
		wp_set_current_user( 0 );
		$this->assertSame( 404, $this->rest( 'GET', '/events/' . $ref, array( 'view' => $view ) )->get_status() );
	}

	public function test_equal_start_times_use_source_order_before_event_identity(): void {
		$green = $this->event( 'green', 'Tie green', '2026-11-02 08:00:00', '2026-11-02 09:00:00' );
		$blue = $this->event( 'blue', 'Tie blue', '2026-11-02 08:00:00', '2026-11-02 09:00:00' );
		$direct = $this->adapter()->query( $this->query( array( 'green', 'blue' ), search: 'Tie', limit: 1 ) );
		$this->assertSame( (string) $green, $direct->items[0]->id );
		$merged = $this->services->event_queries()->find( array( $this->green, $this->blue ), $this->query()->range, 'Tie', null, array(), false, 1, 0, 3600 );
		$this->assertSame( EventReference::local( $this->blue, (string) $blue ), $merged['items'][0]->id );
	}

	public function test_datetime_mapping_is_utc_across_the_daylight_saving_transition(): void {
		$id = $this->event( 'blue', 'DST', '2026-10-25 00:30:00', '2026-10-25 02:30:00' );
		$event = $this->adapter()->event( 'blue', (string) $id );
		$zone = new DateTimeZone( 'Europe/Berlin' );
		$this->assertSame( 'UTC', $event->start->getTimezone()->getName() );
		$this->assertSame( '02:30 +02:00', $event->start->setTimezone( $zone )->format( 'H:i P' ) );
		$this->assertSame( '03:30 +01:00', $event->end->setTimezone( $zone )->format( 'H:i P' ) );
	}

	public function test_fresh_install_creates_system_presets_after_their_complete_schema(): void {
		global $wpdb;
		$this->assertSame( '4', $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'cts_shortcode_presets WHERE is_system = 1' ) );
	}

}
