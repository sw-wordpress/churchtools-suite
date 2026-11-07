<?php
/**
 * Next Event View - Professional
 *
 * Zeigt das nächste anstehende Event (aus einer Liste von Kalender-IDs oder
 * allen ausgewählten Kalendern) als statische Karte im Stil der
 * Professional-Modal-UI: Hauptbereich links (Titel, Beschreibungen, Dienste),
 * Sidebar rechts (Bild, Datum, Uhrzeit, Schlagwörter, Ort).
 *
 * @package ChurchTools_Suite
 * @since   1.2.2.0
 * @version 1.2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Display options.
$show_event_description       = isset( $args['show_event_description'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['show_event_description'] ) : true;
$show_appointment_description = isset( $args['show_appointment_description'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['show_appointment_description'] ) : true;
$show_location                = isset( $args['show_location'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['show_location'] ) : true;
$show_calendar_name           = isset( $args['show_calendar_name'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['show_calendar_name'] ) : true;
$show_time                    = isset( $args['show_time'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['show_time'] ) : true;
$show_tags                    = isset( $args['show_tags'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['show_tags'] ) : true;
$show_services                = isset( $args['show_services'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['show_services'] ) : false;
$show_images                  = isset( $args['show_images'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['show_images'] ) : true;
$image_fit                    = isset( $args['image_fit'] ) ? ChurchTools_Suite_Shortcodes::sanitize_image_fit( $args['image_fit'] ) : 'cover';
$use_calendar_colors          = isset( $args['use_calendar_colors'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['use_calendar_colors'] ) : true;

// Header label.
$heading = __( 'Nächster Termin', 'churchtools-suite' );

// Next upcoming event (events are returned ordered ascending, future-only).
$next_event = ! empty( $events ) ? $events[0] : null;

if ( ! $next_event ) {
	$empty_message = ! empty( $args['empty_message'] )
		? $args['empty_message']
		: __( 'Aktuell sind keine anstehenden Termine verfügbar.', 'churchtools-suite' );
	?>
	<div class="cts-next-event cts-next-event-professional cts-next-event-empty">
		<div class="cts-next-event-header">
			<span class="dashicons dashicons-calendar-alt"></span>
			<span class="cts-next-event-heading"><?php echo esc_html( $heading ); ?></span>
		</div>
		<div class="cts-next-event-empty-body">
			<span class="dashicons dashicons-info-outline"></span>
			<p><?php echo esc_html( $empty_message ); ?></p>
		</div>
	</div>
	<?php
	return;
}

// Event-Action (default: none — alle Details sind bereits sichtbar).
$event_action          = $args['event_action'] ?? 'none';
$single_event_base     = apply_filters( 'churchtools_suite_single_event_base_url', home_url( '/events/' ) );
$single_event_template = get_option( 'churchtools_suite_single_template', 'professional' );

// Event data.
$event_id                = $next_event['id'] ?? $next_event['event_id'] ?? $next_event['appointment_id'] ?? 0;
$event_title             = $next_event['title'] ?? '';
$event_description       = $next_event['event_description'] ?? '';
$appointment_description = $next_event['appointment_description'] ?? '';
$start_datetime          = $next_event['start_datetime'] ?? '';
$end_datetime            = $next_event['end_datetime'] ?? '';
$calendar_name           = $next_event['calendar_name'] ?? '';
$calendar_color          = $next_event['calendar_color'] ?? '#2563eb';
$location                = $next_event['location_name'] ?? '';
$tags                    = $next_event['tags_array'] ?? array();
$services                = $next_event['services'] ?? array();

// Image (Event → Calendar fallback via Image Helper).
$hero_image = null;
if ( class_exists( 'ChurchTools_Suite_Image_Helper' ) ) {
	$event_arr          = is_array( $next_event ) ? $next_event : (array) $next_event;
	$calendar_for_image = ! empty( $event_arr['calendar_image_id'] ) ? array( 'calendar_image_id' => $event_arr['calendar_image_id'] ) : null;
	$hero_image         = ChurchTools_Suite_Image_Helper::get_image_url( $event_arr, $calendar_for_image );
}
if ( empty( $hero_image ) ) {
	if ( ! empty( $next_event['image_url'] ) ) {
		$hero_image = $next_event['image_url'];
	} elseif ( ! empty( $next_event['calendar_image_url'] ) ) {
		$hero_image = $next_event['calendar_image_url'];
	}
}

// Date / time (DB is UTC → convert to site timezone).
$wp_timezone = wp_timezone();
$date_obj    = null;
if ( ! empty( $start_datetime ) ) {
	try {
		$date_obj = new DateTime( $start_datetime, new DateTimeZone( 'UTC' ) );
		$date_obj->setTimezone( $wp_timezone );
	} catch ( Exception $e ) {
		$date_obj = null;
	}
}

$date_format = get_option( 'date_format' );
$time_format = get_option( 'time_format' );
$has_ampm    = ( strpos( $time_format, 'a' ) !== false || strpos( $time_format, 'A' ) !== false );

$date_display = $date_obj ? wp_date( $date_format, $date_obj->getTimestamp(), $wp_timezone ) : '';
$weekday      = $date_obj ? wp_date( 'l', $date_obj->getTimestamp(), $wp_timezone ) : '';

$start_time_display = $date_obj ? wp_date( $time_format, $date_obj->getTimestamp(), $wp_timezone ) : '';
if ( ! $has_ampm && $start_time_display ) {
	$start_time_display .= ' Uhr';
}

$end_time_display = '';
if ( ! empty( $end_datetime ) ) {
	try {
		$end_obj = new DateTime( $end_datetime, new DateTimeZone( 'UTC' ) );
		$end_obj->setTimezone( $wp_timezone );
		$end_time_display = wp_date( $time_format, $end_obj->getTimestamp(), $wp_timezone );
		if ( ! $has_ampm ) {
			$end_time_display .= ' Uhr';
		}
	} catch ( Exception $e ) {
		$end_time_display = '';
	}
}

// Click behaviour.
$click_class = '';
$click_attrs = '';
if ( 'modal' === $event_action ) {
	$click_class = ' cts-event-clickable';
	$click_attrs = sprintf(
		' data-event-id="%s" data-template="%s" role="button" tabindex="0" aria-label="%s"',
		esc_attr( $event_id ),
		esc_attr( $single_event_template ),
		esc_attr( sprintf( /* translators: %s: Event title. */ __( 'Details für %s anzeigen', 'churchtools-suite' ), $event_title ) )
	);
} elseif ( 'page' === $event_action ) {
	$click_class = ' cts-event-page-link';
	$page_url    = add_query_arg(
		array(
			'event_id' => $event_id,
			'template' => $single_event_template,
		),
		$single_event_base
	);
	$click_attrs = sprintf(
		' data-event-id="%s" data-event-url="%s" role="link" tabindex="0" aria-label="%s"',
		esc_attr( $event_id ),
		esc_url( $page_url ),
		esc_attr( sprintf( /* translators: %s: Event title. */ __( 'Zu %s navigieren', 'churchtools-suite' ), $event_title ) )
	);
}

// Color CSS variable (only when calendar colors enabled).
$style_attr = '';
if ( $use_calendar_colors && ! empty( $calendar_color ) ) {
	$style_attr = sprintf( ' style="--cts-calendar-color: %s;"', esc_attr( $calendar_color ) );
}

$has_sidebar = ( $hero_image && $show_images )
	|| $date_display
	|| ( $show_time && $start_time_display )
	|| ( $show_tags && ! empty( $tags ) )
	|| ( $show_location && ! empty( $location ) );
?>

<div class="cts-next-event cts-next-event-professional<?php echo esc_attr( $click_class ); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo $click_attrs; // phpcs:ignore WordPress.Security.EscapeOutput ?>>

	<!-- Header -->
	<div class="cts-next-event-header">
		<span class="dashicons dashicons-calendar-alt"></span>
		<span class="cts-next-event-heading"><?php echo esc_html( $heading ); ?></span>
	</div>

	<!-- Body: main + sidebar -->
	<div class="cts-next-event-content<?php echo $has_sidebar ? '' : ' cts-next-event-no-sidebar'; ?>">

		<!-- Main -->
		<div class="cts-next-event-main">
			<h1 class="cts-next-event-title"><?php echo esc_html( $event_title ); ?></h1>

			<?php if ( $show_calendar_name && ! empty( $calendar_name ) ) : ?>
				<div class="cts-next-event-calendar-badge"><?php echo esc_html( $calendar_name ); ?></div>
			<?php endif; ?>

			<?php if ( $show_event_description && ! empty( $event_description ) ) : ?>
				<div class="cts-next-event-section">
					<div class="cts-next-event-description"><?php echo wp_kses_post( wpautop( $event_description ) ); ?></div>
				</div>
			<?php endif; ?>

			<?php if ( $show_appointment_description && ! empty( $appointment_description ) ) : ?>
				<div class="cts-next-event-section">
					<h3><?php esc_html_e( 'Termin-Details', 'churchtools-suite' ); ?></h3>
					<div class="cts-next-event-description"><?php echo wp_kses_post( wpautop( $appointment_description ) ); ?></div>
				</div>
			<?php endif; ?>

			<?php if ( $show_services && ! empty( $services ) && is_array( $services ) ) : ?>
				<div class="cts-next-event-section">
					<h3><?php esc_html_e( 'Dienste', 'churchtools-suite' ); ?></h3>
					<ul class="cts-next-event-services-list">
						<?php foreach ( $services as $service ) : ?>
							<?php $service_name = is_array( $service ) ? ( $service['service_name'] ?? $service['name'] ?? '' ) : $service; ?>
							<?php if ( ! empty( $service_name ) ) : ?>
								<li>
									<strong><?php echo esc_html( $service_name ); ?></strong>
									<?php if ( is_array( $service ) && ! empty( $service['person_name'] ) ) : ?>
										<span><?php echo esc_html( $service['person_name'] ); ?></span>
									<?php endif; ?>
								</li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $has_sidebar ) : ?>
		<!-- Sidebar -->
		<div class="cts-next-event-sidebar">

			<?php if ( $show_images && $hero_image ) : ?>
				<div class="cts-next-event-image-container" data-image-fit="<?php echo esc_attr( $image_fit ); ?>">
					<img src="<?php echo esc_url( $hero_image ); ?>" alt="<?php echo esc_attr( $event_title ); ?>" class="cts-next-event-image" />
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $date_display ) ) : ?>
				<div class="cts-next-event-sidebar-section">
					<div class="cts-next-event-sidebar-header">
						<span class="dashicons dashicons-calendar-alt"></span>
						<span class="cts-next-event-sidebar-label"><?php esc_html_e( 'DATUM', 'churchtools-suite' ); ?></span>
					</div>
					<div class="cts-next-event-sidebar-content">
						<?php echo esc_html( $weekday ? $weekday . ', ' . $date_display : $date_display ); ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $show_time && ! empty( $start_time_display ) ) : ?>
				<div class="cts-next-event-sidebar-section">
					<div class="cts-next-event-sidebar-header">
						<span class="dashicons dashicons-clock"></span>
						<span class="cts-next-event-sidebar-label"><?php esc_html_e( 'UHRZEIT', 'churchtools-suite' ); ?></span>
					</div>
					<div class="cts-next-event-sidebar-content">
						<?php echo esc_html( $end_time_display ? $start_time_display . ' – ' . $end_time_display : $start_time_display ); ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $show_tags && ! empty( $tags ) && is_array( $tags ) ) : ?>
				<div class="cts-next-event-sidebar-section">
					<div class="cts-next-event-sidebar-header">
						<span class="dashicons dashicons-tag"></span>
						<span class="cts-next-event-sidebar-label"><?php esc_html_e( 'SCHLAGWÖRTER', 'churchtools-suite' ); ?></span>
					</div>
					<div class="cts-next-event-sidebar-content">
						<div class="cts-next-event-tags">
							<?php foreach ( $tags as $cts_tag ) : ?>
								<?php $tag_name = is_array( $cts_tag ) ? ( $cts_tag['name'] ?? '' ) : $cts_tag; ?>
								<?php if ( ! empty( $tag_name ) ) : ?>
									<span class="cts-next-event-tag"><?php echo esc_html( $tag_name ); ?></span>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $show_location && ! empty( $location ) ) : ?>
				<div class="cts-next-event-sidebar-section">
					<div class="cts-next-event-sidebar-header">
						<span class="dashicons dashicons-location-alt"></span>
						<span class="cts-next-event-sidebar-label"><?php esc_html_e( 'ORT', 'churchtools-suite' ); ?></span>
					</div>
					<div class="cts-next-event-sidebar-content"><?php echo esc_html( $location ); ?></div>
				</div>
			<?php endif; ?>

		</div>
		<?php endif; ?>

	</div>
</div>

<style>
	/* Next Event - Professional (orientated at the professional modal UI) */
	.cts-next-event-professional {
		width: 100%;
		background: #fff;
		border-radius: 12px;
		box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
		border: 1px solid #e5e7eb;
		overflow: hidden;
	}

	.cts-next-event-professional.cts-event-clickable,
	.cts-next-event-professional.cts-event-page-link {
		cursor: pointer;
		transition: box-shadow 0.18s ease, transform 0.18s ease;
	}

	.cts-next-event-professional.cts-event-clickable:hover,
	.cts-next-event-professional.cts-event-page-link:hover {
		box-shadow: 0 16px 40px rgba(0, 0, 0, 0.18);
		transform: translateY(-2px);
	}

	/* Header */
	.cts-next-event-header {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 16px 24px;
		border-bottom: 1px solid #e5e7eb;
		background: #f8fafc;
		font-weight: 700;
		color: #1e293b;
	}

	.cts-next-event-header .dashicons {
		color: var(--cts-calendar-color, #2563eb);
	}

	.cts-next-event-heading {
		font-size: 14px;
		text-transform: uppercase;
		letter-spacing: 0.6px;
	}

	/* Body grid */
	.cts-next-event-content {
		display: grid;
		grid-template-columns: 2fr 1fr;
		gap: 0;
	}

	.cts-next-event-content.cts-next-event-no-sidebar {
		grid-template-columns: 1fr;
	}

	/* Main */
	.cts-next-event-main {
		padding: 28px;
		border-right: 1px solid #e5e7eb;
	}

	.cts-next-event-no-sidebar .cts-next-event-main {
		border-right: none;
	}

	.cts-next-event-title {
		font-size: 26px;
		font-weight: 700;
		color: #1e293b;
		margin: 0 0 16px 0;
		line-height: 1.3;
		word-break: break-word;
		border-left: 4px solid var(--cts-calendar-color, #2563eb);
		padding-left: 16px;
	}

	.cts-next-event-calendar-badge {
		display: inline-block;
		padding: 6px 12px;
		background: var(--cts-calendar-color, #e0e7ff);
		border-radius: 4px;
		font-size: 12px;
		font-weight: 600;
		color: #fff;
		margin-bottom: 16px;
	}

	.cts-next-event-section {
		margin-bottom: 24px;
	}

	.cts-next-event-section:last-child {
		margin-bottom: 0;
	}

	.cts-next-event-section h3 {
		font-size: 15px;
		font-weight: 700;
		color: #1e293b;
		margin: 0 0 12px 0;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}

	.cts-next-event-description {
		font-size: 14px;
		line-height: 1.7;
		color: #64748b;
	}

	.cts-next-event-description p {
		margin: 0 0 12px 0;
	}

	.cts-next-event-description p:last-child {
		margin-bottom: 0;
	}

	/* Dienste */
	.cts-next-event-services-list {
		list-style: none;
		margin: 0;
		padding: 0;
	}

	.cts-next-event-services-list li {
		padding: 8px 0;
		border-bottom: 1px solid #e5e7eb;
		font-size: 13px;
		color: #475569;
	}

	.cts-next-event-services-list li:last-child {
		border-bottom: none;
	}

	.cts-next-event-services-list strong {
		color: #1e293b;
		display: block;
		margin-bottom: 2px;
	}

	/* Sidebar */
	.cts-next-event-sidebar {
		padding: 28px;
		background: #f8fafc;
		display: flex;
		flex-direction: column;
		gap: 16px;
	}

	.cts-next-event-image-container {
		width: 100%;
		height: 200px;
		border-radius: 8px;
		overflow: hidden;
		background: #e2e8f0;
	}

	.cts-next-event-image {
		width: 100%;
		height: 100%;
		object-fit: cover;
		display: block;
	}

	.cts-next-event-image-container[data-image-fit="contain"] .cts-next-event-image {
		object-fit: contain;
		background: #f8fafc;
	}

	.cts-next-event-sidebar-section {
		background: #fff;
		border: 1px solid #e5e7eb;
		border-radius: 6px;
		padding: 14px;
	}

	.cts-next-event-sidebar-header {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 10px;
		font-weight: 700;
		color: #1e293b;
	}

	.cts-next-event-sidebar-header .dashicons {
		width: 18px;
		height: 18px;
		font-size: 18px;
		color: var(--cts-calendar-color, #2563eb);
	}

	.cts-next-event-sidebar-label {
		font-size: 11px;
		text-transform: uppercase;
		letter-spacing: 0.8px;
		flex: 1;
	}

	.cts-next-event-sidebar-content {
		font-size: 13px;
		color: #475569;
		line-height: 1.6;
	}

	.cts-next-event-tags {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
	}

	.cts-next-event-tag {
		display: inline-block;
		padding: 4px 10px;
		border-radius: 4px;
		font-size: 11px;
		font-weight: 600;
		color: #fff;
		background: var(--cts-calendar-color, #2563eb);
		white-space: nowrap;
	}

	/* Empty state */
	.cts-next-event-empty-body {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 8px;
		padding: 40px 20px;
		text-align: center;
		color: #64748b;
	}

	.cts-next-event-empty-body .dashicons {
		font-size: 32px;
		width: 32px;
		height: 32px;
		color: #94a3b8;
	}

	.cts-next-event-empty-body p {
		margin: 0;
		font-size: 14px;
	}

	/* Responsive */
	@media (max-width: 768px) {
		.cts-next-event-content {
			grid-template-columns: 1fr;
		}

		.cts-next-event-main {
			border-right: none;
			border-bottom: 1px solid #e5e7eb;
			padding: 20px;
		}

		.cts-next-event-sidebar {
			padding: 20px;
		}

		.cts-next-event-title {
			font-size: 22px;
		}
	}
</style>
