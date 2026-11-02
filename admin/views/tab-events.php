<?php
/**
 * Events Tab - Termine-Übersicht
 *
 * @package ChurchTools_Suite
 * @since   0.3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load repositories.
require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-events-repository.php';
require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-calendars-repository.php';
require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-event-services-repository.php';

$events_repo         = new ChurchTools_Suite_Events_Repository();
$calendars_repo      = new ChurchTools_Suite_Calendars_Repository();
$event_services_repo = new ChurchTools_Suite_Event_Services_Repository();

// Filter-Parameter.
/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, display filters, or status messages; no state is changed by these query arguments. */ $from            = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, display filters, or status messages; no state is changed by these query arguments. */ $to              = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, display filters, or status messages; no state is changed by these query arguments. */ $calendar_filter = isset( $_GET['calendar_id'] ) ? sanitize_text_field( wp_unslash( $_GET['calendar_id'] ) ) : '';
/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, display filters, or status messages; no state is changed by these query arguments. */ $cts_page        = max( 1, (int) ( absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ) );
$limit  = 200; // show more rows per page to avoid hidden events.
$offset = ( $cts_page - 1 ) * $limit;

// Kombinierte Abfrage.
global $wpdb;
$prefix       = $wpdb->prefix . CHURCHTOOLS_SUITE_DB_PREFIX;
$events_table = $prefix . 'events';

// v0.9.0.3: Check if table exists.
$wpdb->suppress_errors();
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$events_table}'" );
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->show_errors();

if ( ! $table_exists ) {
	echo '<div class="notice notice-warning"><p>';
	esc_html_e( 'Die Events-Tabelle existiert noch nicht. Bitte laden Sie die Seite neu oder bauen Sie die Datenbank im Debug-Tab neu auf.', 'churchtools-suite' );
	echo '</p></div>';
	return;
}

// v0.10.5.0: Check if new columns exist.
$wpdb->suppress_errors();
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
$sample = $wpdb->get_row( "SELECT * FROM {$events_table} LIMIT 1", ARRAY_A );
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->show_errors();

$has_image_columns = $sample && isset( $sample['image_attachment_id'] );

// Build SELECT with conditional image columns.
$select_fields = 'id, event_id, appointment_id, calendar_id, title, description, event_description, appointment_description, start_datetime, end_datetime, is_all_day, location_name, address_name, address_street, address_zip, address_city, address_latitude, address_longitude, tags, status';
if ( $has_image_columns ) {
	$select_fields .= ', image_attachment_id, image_url';
}
$select_fields .= ', raw_payload, last_modified, appointment_modified, created_at, updated_at';

$sql          = "SELECT {$select_fields} FROM {$events_table} WHERE 1=1";
$count_sql    = "SELECT COUNT(*) FROM {$events_table} WHERE 1=1";
$where_params = array();

if ( ! empty( $from ) ) {
	$sql           .= ' AND start_datetime >= %s';
	$count_sql     .= ' AND start_datetime >= %s';
	$where_params[] = $from . ' 00:00:00';
}

if ( ! empty( $to ) ) {
	$sql           .= ' AND start_datetime <= %s';
	$count_sql     .= ' AND start_datetime <= %s';
	$where_params[] = $to . ' 23:59:59';
}

if ( ! empty( $calendar_filter ) ) {
	$sql           .= ' AND calendar_id = %s';
	$count_sql     .= ' AND calendar_id = %s';
	$where_params[] = $calendar_filter;
}

$sql .= ' ORDER BY start_datetime ASC';
$sql .= ' LIMIT %d OFFSET %d';

// Prepare queries.
$prepared_count = $count_sql;
if ( ! empty( $where_params ) ) {
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled from fixed clauses and values passed through wpdb::prepare above.
	$prepared_count = $wpdb->prepare( $count_sql, ...$where_params );
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
}

$prepared_sql = $sql;
$query_params = array_merge( $where_params, array( $limit, $offset ) );
if ( ! empty( $query_params ) ) {
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled from fixed clauses and values passed through wpdb::prepare above.
	$prepared_sql = $wpdb->prepare( $sql, ...$query_params );
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
}

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

$calendars   = $calendars_repo->get_all();
$total_pages = ceil( $total / $limit );
?>

<div class="cts-events">
	
	<div class="cts-card">
		<div class="cts-card-header">
			<h3>📋 <?php esc_html_e( 'Termine-Übersicht', 'churchtools-suite' ); ?></h3>
			<p><?php esc_html_e( 'Alle synchronisierten Termine aus ChurchTools', 'churchtools-suite' ); ?></p>
		</div>
		
		<!-- Filter -->
			<form method="get" class="cts-filter-section">
				<input type="hidden" name="page" value="churchtools-suite-data" />
				<input type="hidden" name="subtab" value="events" />
			
			<div class="cts-filter-grid">
				<div class="cts-form-group">
					<label>📅 <?php esc_html_e( 'Von', 'churchtools-suite' ); ?></label>
					<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" class="cts-form-input" />
				</div>
				
				<div class="cts-form-group">
					<label>📅 <?php esc_html_e( 'Bis', 'churchtools-suite' ); ?></label>
					<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" class="cts-form-input" />
				</div>
				
				<div class="cts-form-group" style="grid-column: span 2;">
					<label>🗂️ <?php esc_html_e( 'Kalender', 'churchtools-suite' ); ?></label>
					<select name="calendar_id" class="cts-form-input">
						<option value=""><?php esc_html_e( 'Alle Kalender', 'churchtools-suite' ); ?></option>
						<?php foreach ( $calendars as $cal ) : ?>
							<option value="<?php echo esc_attr( $cal->calendar_id ); ?>" <?php selected( $calendar_filter, $cal->calendar_id ); ?>>
								<?php echo esc_html( $cal->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			
			<div class="cts-filter-actions">
				<button type="submit" class="cts-btn cts-btn-primary">
					<span>🔍</span>
					<?php esc_html_e( 'Filtern', 'churchtools-suite' ); ?>
				</button>
				
				<?php if ( ! empty( $from ) || ! empty( $to ) || ! empty( $calendar_filter ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=churchtools-suite-data&subtab=events' ) ); ?>" class="cts-btn cts-btn-secondary">
						<span>✖</span>
						<?php esc_html_e( 'Filter zurücksetzen', 'churchtools-suite' ); ?>
					</a>
				<?php endif; ?>
				
				<div class="cts-filter-count">
					📊 <?php printf( /* translators: %d: Number of events. */ esc_html__( '%d Termine', 'churchtools-suite' ), (int) $total ); ?>
				</div>
			</div>
			
			<?php if ( ! empty( $from ) || ! empty( $to ) || ! empty( $calendar_filter ) ) : ?>
				<div class="cts-active-filters">
					<strong>✓ <?php esc_html_e( 'Aktive Filter:', 'churchtools-suite' ); ?></strong>
					<?php if ( ! empty( $from ) ) : ?>
						<span><?php esc_html_e( 'Von:', 'churchtools-suite' ); ?> <strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $from ) ) ); ?></strong></span>
					<?php endif; ?>
					<?php if ( ! empty( $to ) ) : ?>
						<span><?php esc_html_e( 'Bis:', 'churchtools-suite' ); ?> <strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $to ) ) ); ?></strong></span>
					<?php endif; ?>
					<?php
					if ( ! empty( $calendar_filter ) ) :
						foreach ( $calendars as $cal ) {
							if ( $cal->calendar_id === $calendar_filter ) :
								?>
								<span><?php esc_html_e( 'Kalender:', 'churchtools-suite' ); ?> <strong><?php echo esc_html( $cal->name ); ?></strong></span>
								<?php
							endif;
						}
					endif;
					?>
				</div>
			<?php endif; ?>
		</form>
		
		<!-- Tabelle -->
		<?php if ( empty( $events ) ) : ?>
			<div class="cts-empty-state">
				<span class="cts-empty-icon">📅</span>
				<h3><?php esc_html_e( 'Keine Termine gefunden', 'churchtools-suite' ); ?></h3>
				<p><?php esc_html_e( 'Versuchen Sie andere Filterwerte oder synchronisieren Sie die Termine im Tab "Sync".', 'churchtools-suite' ); ?></p>
			</div>
		<?php else : ?>
			<div id="cts-events-ajax-container">
				<div class="cts-table-wrapper">
				<table class="cts-events-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Datum & Zeit', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Titel', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Beschreibung', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Kalender', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Ort / Adresse', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Tags', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Typ', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Ganztägig', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Status', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Services', 'churchtools-suite' ); ?></th>
							<th><?php esc_html_e( 'Details', 'churchtools-suite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $events as $event ) :
							$calendar = null;
							foreach ( $calendars as $cal ) {
								if ( $cal->calendar_id === $event->calendar_id ) {
									$calendar = $cal;
									break;
								}
							}

							// Lade Services für dieses Event.
							$services = $event_services_repo->get_for_event( $event->id );

							$raw         = ! empty( $event->raw_payload ) ? json_decode( $event->raw_payload, true ) : array();
							$base        = $raw['appointment']['base'] ?? $raw['base'] ?? $raw;
							$cts_link    = $base['link'] ?? '';
							$image_url   = $base['image'] ?? '';
							$is_canceled = (bool) ( $base['isCanceled'] ?? $raw['isCanceled'] ?? false );

							// Konvertiere UTC zu lokaler WordPress-Zeitzone.
							$start_local = get_date_from_gmt( $event->start_datetime );
							$end_local   = $event->end_datetime ? get_date_from_gmt( $event->end_datetime ) : null;
							$is_all_day  = (bool) $event->is_all_day;

							// Typ bestimmen (Event oder Appointment).
							$type_label           = ! empty( $event->appointment_id )
								? __( 'Termin', 'churchtools-suite' )
								: __( 'Event', 'churchtools-suite' );
							$type_icon            = ! empty( $event->appointment_id ) ? '📅' : '🎯';
							$last_modified        = ! empty( $event->last_modified ) ? get_date_from_gmt( $event->last_modified ) : '';
							$appointment_modified = ! empty( $event->appointment_modified ) ? get_date_from_gmt( $event->appointment_modified ) : '';
							$raw                  = ! empty( $event->raw_payload ) ? json_decode( $event->raw_payload, true ) : array();
							$base                 = $raw['appointment']['base'] ?? $raw['base'] ?? $raw;
							$cts_link             = $base['link'] ?? '';
							$image_url            = $base['image'] ?? '';
							$is_canceled          = (bool) ( $base['isCanceled'] ?? $raw['isCanceled'] ?? false );
							?>
							<tr>
								<td class="cts-event-date">
									<div class="cts-event-date-primary">
									<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_local ) ) ); ?>
								</div>
								<?php if ( ! $is_all_day ) : ?>
									<div class="cts-event-date-time">
										<?php
										echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $start_local ) ) );
										if ( $end_local ) {
											echo ' - ' . esc_html( date_i18n( get_option( 'time_format' ), strtotime( $end_local ) ) );
										}
										?>
										</div>
									<?php else : ?>
										<div class="cts-event-date-time">
											<?php esc_html_e( 'Ganztägig', 'churchtools-suite' ); ?>
										</div>
									<?php endif; ?>
								</td>
								
								<td class="cts-event-title">
									<div class="cts-event-title-main">
										<?php echo esc_html( $event->title ); ?>
									</div>
								</td>

								<td class="cts-event-description">
									<?php if ( ! empty( $event->event_description ) || ! empty( $event->appointment_description ) ) : ?>
										<?php
										$desc = ! empty( $event->event_description ) ? $event->event_description : $event->appointment_description;
										echo esc_html( wp_trim_words( $desc, 20 ) );
										?>
									<?php else : ?>
										<span class="cts-muted">—</span>
									<?php endif; ?>
								</td>
								
								<td class="cts-event-calendar">
									<?php if ( $calendar ) : ?>
										<span class="cts-calendar-badge" style="background-color: <?php echo esc_attr( $calendar->color ?? '#3498db' ); ?>;">
											<?php echo esc_html( $calendar->name ); ?>
										</span>
									<?php else : ?>
										<span class="cts-calendar-badge">
											<?php echo esc_html( $event->calendar_id ); ?>
										</span>
									<?php endif; ?>
								</td>
								
								<td class="cts-event-location">
									<?php
									// Priorität: 1. Strukturierte Adresse, 2. location_name, 3. Leer.
									if ( ! empty( $event->address_name ) || ! empty( $event->address_street ) ) :
										// Strukturierte Adresse.
										?>
										<div class="cts-address-structured">
											<?php if ( ! empty( $event->address_name ) ) : ?>
												<div class="cts-address-name"><strong>🏠 <?php echo esc_html( $event->address_name ); ?></strong></div>
											<?php endif; ?>
											<?php if ( ! empty( $event->address_street ) ) : ?>
												<div class="cts-address-street"><?php echo esc_html( $event->address_street ); ?></div>
											<?php endif; ?>
											<?php if ( ! empty( $event->address_zip ) || ! empty( $event->address_city ) ) : ?>
												<div class="cts-address-city">
													<?php echo esc_html( trim( $event->address_zip . ' ' . $event->address_city ) ); ?>
												</div>
											<?php endif; ?>
											<?php if ( ! empty( $event->address_latitude ) && ! empty( $event->address_longitude ) ) : ?>
												<a href="https://maps.google.com/?q=<?php echo esc_attr( $event->address_latitude ); ?>,<?php echo esc_attr( $event->address_longitude ); ?>" 
													target="_blank"
													class="cts-address-map-link"
													title="<?php esc_attr_e( 'In Google Maps öffnen', 'churchtools-suite' ); ?>">
													🗺️ <?php esc_html_e( 'Karte', 'churchtools-suite' ); ?>
												</a>
											<?php endif; ?>
										</div>
									<?php elseif ( ! empty( $event->location_name ) ) : ?>
										<span>📍 <?php echo esc_html( $event->location_name ); ?></span>
									<?php else : ?>
										<span class="cts-muted">—</span>
									<?php endif; ?>
								</td>
								
								<td class="cts-event-tags">
									<?php
									if ( ! empty( $event->tags ) ) :
										$tags = json_decode( $event->tags, true );
										if ( is_array( $tags ) && ! empty( $tags ) ) :
											foreach ( $tags as $cts_tag ) :
												$tag_color = $cts_tag['color'] ?? 'basic';
												?>
												<span class="cts-tag cts-tag-<?php echo esc_attr( $tag_color ); ?>" title="<?php echo esc_attr( $cts_tag['description'] ?? '' ); ?>">
													🏷️ <?php echo esc_html( $cts_tag['name'] ?? '' ); ?>
												</span>
												<?php
											endforeach;
										else :
											?>
											<span class="cts-muted">—</span>
											<?php
										endif;
									else :
										?>
										<span class="cts-muted">—</span>
										<?php
									endif;
									?>
								</td>
								
								<td class="cts-event-type">
									<span class="cts-type-badge">
										<?php echo esc_html( $type_icon . ' ' . $type_label ); ?>
									</span>
								</td>

								<td class="cts-event-allday">
									<?php echo $is_all_day ? esc_html__( 'Ja', 'churchtools-suite' ) : esc_html__( 'Nein', 'churchtools-suite' ); ?>
								</td>

								<td class="cts-event-status">
									<?php if ( $is_canceled ) : ?>
										<span class="cts-status-badge cts-status-canceled">⛔ <?php esc_html_e( 'Abgesagt', 'churchtools-suite' ); ?></span>
									<?php else : ?>
										<span class="cts-status-badge cts-status-active">✅ <?php esc_html_e( 'Aktiv', 'churchtools-suite' ); ?></span>
									<?php endif; ?>
								</td>

								<td class="cts-event-services">
									<?php
									if ( ! empty( $services ) ) :
										$service_labels = array();
										foreach ( $services as $service ) {
											$label = esc_html( $service->service_name );
											if ( ! empty( $service->person_name ) ) {
												$label .= ' <span class="cts-service-person">(' . esc_html( $service->person_name ) . ')</span>';
											}
											$service_labels[] = $label;
										}
										echo wp_kses_post( implode( ', ', $service_labels ) );
									else :
										echo '<span class="cts-muted">—</span>';
									endif;
									?>
								</td>
								
								<td class="cts-event-details">
									<?php if ( ! empty( $event->event_description ) || ! empty( $event->appointment_description ) || $cts_link || $image_url || $event->raw_payload ) : ?>
										<button type="button" 
												class="cts-btn cts-btn-small cts-details-toggle"
												data-event-id="<?php echo esc_attr( $event->id ); ?>"
											onclick="ctsSuite.openEventModal(<?php echo intval( $event->id ); ?>);">
											📝 <?php esc_html_e( 'Infos', 'churchtools-suite' ); ?>
										</button>
										<div id="event-details-<?php echo esc_attr( $event->id ); ?>" class="cts-event-details-modal-data" style="display:none;">
											<div class="cts-description-section cts-meta-grid" data-event-title="<?php echo esc_attr( $event->title ); ?>">
												<div><strong>ID:</strong> <?php echo esc_html( $event->id ); ?></div>
												<div><strong><?php esc_html_e( 'Event ID', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $event->event_id ?: '—' ); ?></div>
												<div><strong><?php esc_html_e( 'Appointment ID', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $event->appointment_id ?: '—' ); ?></div>
												<div><strong><?php esc_html_e( 'Kalender', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $event->calendar_id ); ?></div>
												<div><strong><?php esc_html_e( 'Status', 'churchtools-suite' ); ?>:</strong> <?php echo $is_canceled ? esc_html__( 'Abgesagt', 'churchtools-suite' ) : esc_html__( 'Aktiv', 'churchtools-suite' ); ?></div>
												<div><strong><?php esc_html_e( 'Start', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $event->start_datetime ); ?></div>
												<div><strong><?php esc_html_e( 'Ende', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $event->end_datetime ?: '—' ); ?></div>
												<div><strong><?php esc_html_e( 'All Day', 'churchtools-suite' ); ?>:</strong> <?php echo $event->is_all_day ? 'Yes' : 'No'; ?></div>
												<div><strong><?php esc_html_e( 'Last Modified', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $last_modified ?: '—' ); ?></div>
												<div><strong><?php esc_html_e( 'Appointment Modified', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $appointment_modified ?: '—' ); ?></div>
												<div><strong><?php esc_html_e( 'Created', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $event->created_at ?: '—' ); ?></div>
												<div><strong><?php esc_html_e( 'Updated', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $event->updated_at ?: '—' ); ?></div>
											</div>

											<div class="cts-description-section cts-meta-grid">
												<div><strong><?php esc_html_e( 'Adresse Name', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $event->address_name ?: '—' ); ?></div>
												<div><strong><?php esc_html_e( 'Straße', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $event->address_street ?: '—' ); ?></div>
												<div><strong><?php esc_html_e( 'PLZ/Ort', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( trim( $event->address_zip . ' ' . $event->address_city ) ?: '—' ); ?></div>
												<div><strong><?php esc_html_e( 'Lat', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $event->address_latitude ?: '—' ); ?></div>
												<div><strong><?php esc_html_e( 'Lng', 'churchtools-suite' ); ?>:</strong> <?php echo esc_html( $event->address_longitude ?: '—' ); ?></div>
											</div>

											<?php if ( $has_image_columns ) : ?>
												<div class="cts-description-section cts-meta-grid">
													<div><strong><?php esc_html_e( 'Bild (Attachment ID)', 'churchtools-suite' ); ?>:</strong> 
														<?php
														if ( isset( $event->image_attachment_id ) && ! empty( $event->image_attachment_id ) ) {
															echo esc_html( $event->image_attachment_id );
															echo ' <a href="' . esc_url( admin_url( 'post.php?post=' . $event->image_attachment_id . '&action=edit' ) ) . '" target="_blank" style="font-size: 11px;">↗ Bearbeiten</a>';
														} else {
															echo '—';
														}
														?>
													</div>
													<div><strong><?php esc_html_e( 'Bild-URL (Fallback)', 'churchtools-suite' ); ?>:</strong> 
														<?php
														if ( isset( $event->image_url ) && ! empty( $event->image_url ) ) {
															echo '<a href="' . esc_url( $event->image_url ) . '" target="_blank" style="font-size: 11px; word-break: break-all;">' . esc_html( $event->image_url ) . '</a>';
														} else {
															echo '—';
														}
														?>
													</div>
												</div>
											<?php endif; ?>

											<?php if ( $cts_link ) : ?>
												<div class="cts-description-section">
													<strong>🔗 <?php esc_html_e( 'Link', 'churchtools-suite' ); ?>:</strong>
													<p><a href="<?php echo esc_url( $cts_link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $cts_link ); ?></a></p>
												</div>
											<?php endif; ?>

											<?php if ( $image_url && filter_var( $image_url, FILTER_VALIDATE_URL ) ) : ?>
												<div class="cts-description-section">
													<strong>🖼️ <?php esc_html_e( 'Bild', 'churchtools-suite' ); ?>:</strong>
													<img src="<?php echo esc_url( $image_url ); ?>" alt="" class="cts-modal-image" onerror="this.style.display='none';" />
												</div>
											<?php endif; ?>

											<?php if ( ! empty( $event->event_description ) ) : ?>
												<div class="cts-description-section">
													<strong>🎯 <?php esc_html_e( 'Serie / Event:', 'churchtools-suite' ); ?></strong>
													<p><?php echo nl2br( esc_html( $event->event_description ) ); ?></p>
												</div>
											<?php endif; ?>
											<?php if ( ! empty( $event->appointment_description ) ) : ?>
												<div class="cts-description-section">
													<strong>📅 <?php esc_html_e( 'Termin-Details:', 'churchtools-suite' ); ?></strong>
													<p><?php echo nl2br( esc_html( $event->appointment_description ) ); ?></p>
												</div>
											<?php endif; ?>

											<?php
											// Zeige Bild (priorität: lokale attachment > external URL)
											// v0.10.5.0: Only if columns exist.
											$display_image_url = null;
											if ( $has_image_columns && isset( $event->image_attachment_id ) && ! empty( $event->image_attachment_id ) ) {
												$display_image_url = wp_get_attachment_url( $event->image_attachment_id );
											} elseif ( $has_image_columns && isset( $event->image_url ) && ! empty( $event->image_url ) && filter_var( $event->image_url, FILTER_VALIDATE_URL ) ) {
												$display_image_url = $event->image_url;
											}

											if ( $display_image_url ) :
												?>
												<div class="cts-description-section">
													<strong>🖼️ <?php esc_html_e( 'Bild', 'churchtools-suite' ); ?>:</strong>
													<img src="<?php echo esc_url( $display_image_url ); ?>" alt="" class="cts-modal-image" onerror="this.style.display='none';" />
													<?php if ( ! empty( $event->image_attachment_id ) ) : ?>
														<p style="color: #6b7280; font-size: 12px; margin-top: 8px;">
															<?php esc_html_e( 'Lokales Bild (importiert)', 'churchtools-suite' ); ?>
														</p>
													<?php endif; ?>
												</div>
											<?php endif; ?>

											<?php if ( $event->raw_payload ) : ?>
												<div class="cts-description-section">
													<strong>🧾 <?php esc_html_e( 'Raw Payload', 'churchtools-suite' ); ?>:</strong>
													<p><?php printf( /* translators: %d: Number of characters in the JSON payload. */ esc_html__( '%d Zeichen JSON', 'churchtools-suite' ), (int) strlen( $event->raw_payload ) ); ?></p>
												</div>
											<?php endif; ?>
										</div>
									<?php else : ?>
										<span class="cts-muted">—</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<!-- Pagination -->

		<script>
		jQuery(function($){
			var $form = $('.cts-filter-section');
			function fetchEvents(paged){
				var data = $form.serializeArray();
				if (paged) data.push({name:'paged', value: paged});
				data.push({name:'action', value:'cts_fetch_events_list'});
				data.push({name:'nonce', value: churchtoolsSuite.nonce});
				$.post(churchtoolsSuite.ajaxUrl, data, function(resp){
					if (resp.success){
						$('#cts-events-ajax-container').html(resp.data.html);
					} else {
						alert(resp.data && resp.data.message ? resp.data.message : 'Fehler');
					}
				}, 'json');
			}

			// Intercept filter submit
			$form.on('submit', function(e){
				e.preventDefault();
				fetchEvents(1);
			});

			// Bind pagination buttons (delegated)
			$(document).on('click', '.cts-ajax-page', function(e){
				e.preventDefault();
				var p = $(this).data('paged');
				fetchEvents(p);
			});
		});
		</script>
			<?php if ( $total_pages > 1 ) : ?>
				<div class="cts-pagination">
					<?php
					$base_url = add_query_arg(
						array(
							'page'        => 'churchtools-suite-data',
							'subtab'      => 'events',
							'from'        => $from,
							'to'          => $to,
							'calendar_id' => $calendar_filter,
						),
						admin_url( 'admin.php' )
					);

					if ( $cts_page > 1 ) :
						$prev_url = add_query_arg( 'paged', $cts_page - 1, $base_url );
						?>
						<a href="<?php echo esc_url( $prev_url ); ?>" class="cts-btn cts-btn-secondary">
							← <?php esc_html_e( 'Zurück', 'churchtools-suite' ); ?>
						</a>
					<?php endif; ?>
					
					<span class="cts-pagination-info">
						<?php printf( /* translators: 1: Current page, 2: Total pages. */ esc_html__( 'Seite %1$d von %2$d', 'churchtools-suite' ), (int) $cts_page, (int) $total_pages ); ?>
					</span>
					
					<?php
					if ( $cts_page < $total_pages ) :
						$next_url = add_query_arg( 'paged', $cts_page + 1, $base_url );
						?>
						<a href="<?php echo esc_url( $next_url ); ?>" class="cts-btn cts-btn-secondary">
							<?php esc_html_e( 'Weiter', 'churchtools-suite' ); ?> →
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	
</div>

<style>
.cts-status-badge {
	display: inline-block;
	padding: 4px 8px;
	border-radius: 4px;
	font-weight: 600;
	font-size: 12px;
}
.cts-status-active {
	background: #e6ffed;
	color: #0f5132;
}
.cts-status-canceled {
	background: #ffecec;
	color: #842029;
}
.cts-meta-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
	gap: 8px 12px;
}
.cts-image-preview img {
	max-width: 220px;
	height: auto;
	border: 1px solid #e5e7eb;
	border-radius: 4px;
}

/* Modal Styles */
.cts-modal-overlay {
	display: none;
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.6);
	z-index: 9999;
	overflow-y: auto;
}

.cts-modal-overlay.active {
	display: block;
}

.cts-modal {
	position: fixed;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	background: white;
	border-radius: 8px;
	box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
	max-width: 800px;
	width: 90%;
	max-height: 90vh;
	overflow-y: auto;
	z-index: 10000;
	display: none;
}

.cts-modal.active {
	display: block;
}

.cts-modal-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 20px;
	border-bottom: 1px solid #e5e7eb;
	background: #f9fafb;
}

.cts-modal-header h2 {
	margin: 0;
	font-size: 18px;
	color: #1f2937;
}

.cts-modal-close {
	background: none;
	border: none;
	font-size: 24px;
	cursor: pointer;
	color: #6b7280;
	padding: 0;
	width: 32px;
	height: 32px;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 4px;
	transition: background 0.2s;
}

.cts-modal-close:hover {
	background: #e5e7eb;
	color: #1f2937;
}

.cts-modal-content {
	padding: 20px;
}

.cts-description-section {
	margin-bottom: 20px;
}

.cts-description-section strong {
	display: block;
	margin-bottom: 8px;
	color: #1f2937;
	font-weight: 600;
}

.cts-description-section p {
	margin: 0;
	color: #4b5563;
	line-height: 1.5;
}

.cts-modal-image {
	max-width: 100%;
	height: auto;
	border: 1px solid #e5e7eb;
	border-radius: 4px;
	margin: 10px 0;
}
</style>

<!-- Modal für Event-Details -->
<div id="cts-event-modal-overlay" class="cts-modal-overlay">
	<div id="cts-event-modal" class="cts-modal">
		<div class="cts-modal-header">
			<h2 id="cts-modal-title">Event Details</h2>
			<button class="cts-modal-close" onclick="ctsSuite.closeEventModal();">✕</button>
		</div>
		<div id="cts-modal-content" class="cts-modal-content">
			<!-- Content wird via JavaScript eingefügt -->
		</div>
	</div>
</div>

<script>
// Globales Modal-Management Objekt
var ctsSuite = ctsSuite || {};

ctsSuite.openEventModal = function(eventId) {
	var dataContainer = document.getElementById('event-details-' + eventId);
	var contentDiv = document.getElementById('cts-modal-content');
	var modalTitle = document.getElementById('cts-modal-title');
	
	if (!dataContainer) return;
	
	// Kopiere den Inhalt in das Modal
	contentDiv.innerHTML = dataContainer.innerHTML;
	
	// Setze den Titel
	var titleEl = dataContainer.querySelector('[data-event-title]');
	if (titleEl) {
		modalTitle.textContent = titleEl.getAttribute('data-event-title');
	}
	
	// Zeige das Modal
	document.getElementById('cts-event-modal-overlay').classList.add('active');
	document.getElementById('cts-event-modal').classList.add('active');
	document.body.style.overflow = 'hidden';
};

ctsSuite.closeEventModal = function() {
	document.getElementById('cts-event-modal-overlay').classList.remove('active');
	document.getElementById('cts-event-modal').classList.remove('active');
	document.body.style.overflow = 'auto';
};

// Modal schließen bei Click auf Overlay
document.addEventListener('DOMContentLoaded', function() {
	var overlay = document.getElementById('cts-event-modal-overlay');
	if (overlay) {
		overlay.addEventListener('click', function(e) {
			if (e.target === overlay) {
				ctsSuite.closeEventModal();
			}
		});
	}
	
	// Escape-Taste zum Schließen
	document.addEventListener('keydown', function(e) {
		if (e.key === 'Escape') {
			ctsSuite.closeEventModal();
		}
	});
});
</script>
