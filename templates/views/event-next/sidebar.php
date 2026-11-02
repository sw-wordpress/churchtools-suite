<?php
/**
 * Next Event View - Sidebar (Edition B)
 *
 * Kompakte vertikale Karte für Seitenleisten: Bild oben mit Overlay-Badge,
 * darunter Titel, Datum/Uhrzeit/Ort und Call-to-Action.
 * Vorlage stammt aus Claude Design ("Naechster Termin" – Edition B · Sidebar).
 *
 * @package ChurchTools_Suite
 * @since   1.2.2.0
 * @version 1.2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Display options.
$show_location       = isset( $args['show_location'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['show_location'] ) : true;
$show_calendar_name  = isset( $args['show_calendar_name'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['show_calendar_name'] ) : true;
$show_time           = isset( $args['show_time'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['show_time'] ) : true;
$show_images         = isset( $args['show_images'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['show_images'] ) : true;
$image_fit           = isset( $args['image_fit'] ) ? ChurchTools_Suite_Shortcodes::sanitize_image_fit( $args['image_fit'] ) : 'cover';
$use_calendar_colors = isset( $args['use_calendar_colors'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['use_calendar_colors'] ) : true;

$heading = __( 'Nächster Termin', 'churchtools-suite' );

$next_event = ! empty( $events ) ? $events[0] : null;

if ( ! $next_event ) {
	$empty_message = ! empty( $args['empty_message'] ) ? $args['empty_message'] : __( 'Aktuell sind keine anstehenden Termine verfügbar.', 'churchtools-suite' );
	echo '<div class="cts-next-event cts-next-side cts-next-empty"><div class="cts-next-empty-body"><p>' . esc_html( $empty_message ) . '</p></div></div>';
	return;
}

$event_action          = $args['event_action'] ?? 'none';
$single_event_base     = apply_filters( 'churchtools_suite_single_event_base_url', home_url( '/events/' ) );
$single_event_template = get_option( 'churchtools_suite_single_template', 'professional' );

// Event data.
$event_id       = $next_event['id'] ?? $next_event['event_id'] ?? $next_event['appointment_id'] ?? 0;
$event_title    = $next_event['title'] ?? '';
$start_datetime = $next_event['start_datetime'] ?? '';
$end_datetime   = $next_event['end_datetime'] ?? '';
$calendar_name  = $next_event['calendar_name'] ?? '';
$calendar_color = $next_event['calendar_color'] ?? '#2e9d5b';
$location       = $next_event['location_name'] ?? '';

// Image (Event → Calendar fallback).
$hero_image = null;
if ( class_exists( 'ChurchTools_Suite_Image_Helper' ) ) {
	$event_arr          = (array) $next_event;
	$calendar_for_image = ! empty( $event_arr['calendar_image_id'] ) ? array( 'calendar_image_id' => $event_arr['calendar_image_id'] ) : null;
	$hero_image         = ChurchTools_Suite_Image_Helper::get_image_url( $event_arr, $calendar_for_image );
}
if ( empty( $hero_image ) ) {
	$hero_image = $next_event['image_url'] ?? $next_event['calendar_image_url'] ?? null;
}
$has_image = $show_images && ! empty( $hero_image );

// Date / time (UTC → site timezone).
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
$time_format = get_option( 'time_format' );
$has_ampm    = ( strpos( $time_format, 'a' ) !== false || strpos( $time_format, 'A' ) !== false );

$date_display = $date_obj ? wp_date( 'l, j. F Y', $date_obj->getTimestamp(), $wp_timezone ) : '';

$start_time = $date_obj ? wp_date( $time_format, $date_obj->getTimestamp(), $wp_timezone ) : '';
$end_time   = '';
if ( ! empty( $end_datetime ) ) {
	try {
		$end_obj = new DateTime( $end_datetime, new DateTimeZone( 'UTC' ) );
		$end_obj->setTimezone( $wp_timezone );
		$end_time = wp_date( $time_format, $end_obj->getTimestamp(), $wp_timezone );
	} catch ( Exception $e ) {
		$end_time = '';
	}
}
$time_display = '';
if ( $start_time ) {
	$time_display = $end_time ? $start_time . ' – ' . $end_time : $start_time;
	if ( ! $has_ampm ) {
		$time_display .= ' Uhr';
	}
}

// Accent colors.
$accent      = ( $use_calendar_colors && ! empty( $calendar_color ) ) ? $calendar_color : '#2e9d5b';
$accent_soft = ChurchTools_Suite_Shortcodes::hex_to_rgba( $accent, 0.12 );

// Call-to-action.
$cta_label = __( 'Mehr erfahren', 'churchtools-suite' );
$cta_attrs = '';
$cta_class = '';
$cta_href  = '#';
$show_cta  = false;
if ( 'page' === $event_action ) {
	$show_cta  = true;
	$cta_class = ' cts-event-page-link';
	$cta_href  = add_query_arg(
		array(
			'event_id' => $event_id,
			'template' => $single_event_template,
		),
		$single_event_base
	);
	$cta_attrs = sprintf( ' data-event-id="%s" data-event-url="%s" data-template="%s"', esc_attr( $event_id ), esc_url( $cta_href ), esc_attr( $single_event_template ) );
} elseif ( 'modal' === $event_action ) {
	$show_cta  = true;
	$cta_class = ' cts-event-clickable';
	$cta_attrs = sprintf( ' data-event-id="%s" data-template="%s" role="button"', esc_attr( $event_id ), esc_attr( $single_event_template ) );
}

$icon_calendar = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>';
$icon_clock    = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>';
$icon_pin      = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.5-7-11a7 7 0 0 1 14 0c0 4.5-7 11-7 11Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>';
$icon_arrow    = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>';
?>

<div class="cts-next-event cts-next-side" style="--cts-accent: <?php echo esc_attr( $accent ); ?>; --cts-accent-soft: <?php echo esc_attr( $accent_soft ); ?>;">

	<div class="cts-next-side-top<?php echo $has_image ? '' : ' cts-next-side-top-plain'; ?>">
		<?php if ( $has_image ) : ?>
			<div class="cts-next-side-image" data-image-fit="<?php echo esc_attr( $image_fit ); ?>">
				<img src="<?php echo esc_url( $hero_image ); ?>" alt="<?php echo esc_attr( $event_title ); ?>" />
			</div>
		<?php endif; ?>
		<span class="cts-next-side-eyebrow">
			<?php echo $icon_calendar; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo esc_html( $heading ); ?>
		</span>
	</div>

	<div class="cts-next-side-body">
		<div class="cts-next-titlerow">
			<h3 class="cts-next-title"><?php echo esc_html( $event_title ); ?></h3>
			<?php if ( $show_calendar_name && ! empty( $calendar_name ) ) : ?>
				<span class="cts-next-badge cts-next-badge-soft"><?php echo esc_html( $calendar_name ); ?></span>
			<?php endif; ?>
		</div>

		<div class="cts-next-side-list">
			<?php if ( $date_display ) : ?>
				<div class="cts-next-side-row">
					<span class="cts-next-chip"><?php echo $icon_calendar; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="cts-next-side-text cts-next-side-strong"><?php echo esc_html( $date_display ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( $show_time && $time_display ) : ?>
				<div class="cts-next-side-row">
					<span class="cts-next-chip"><?php echo $icon_clock; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="cts-next-side-text cts-next-side-strong"><?php echo esc_html( $time_display ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( $show_location && ! empty( $location ) ) : ?>
				<div class="cts-next-side-row">
					<span class="cts-next-chip"><?php echo $icon_pin; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="cts-next-side-text"><?php echo esc_html( $location ); ?></span>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $show_cta ) : ?>
			<a class="cts-next-cta cts-next-cta-block<?php echo esc_attr( $cta_class ); ?>" href="<?php echo esc_url( $cta_href ); ?>"<?php echo $cta_attrs; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php echo esc_html( $cta_label ); ?>
				<?php echo $icon_arrow; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
		<?php endif; ?>
	</div>
</div>

<style>
	.cts-next-side {
		width: 100%;
		max-width: 320px;
		background: #ffffff;
		border: 1px solid #e8ecef;
		border-radius: 14px;
		overflow: hidden;
		box-shadow: 0 6px 24px -14px rgba(20, 40, 30, 0.2);
		font-family: inherit;
	}
	.cts-next-side-top {
		position: relative;
	}
	.cts-next-side-image {
		width: 100%;
		height: 124px;
		display: block;
		background: #e2e8f0;
	}
	.cts-next-side-image img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		display: block;
	}
	.cts-next-side-image[data-image-fit="contain"] img {
		object-fit: contain;
		background: #f4f6f8;
	}
	/* When no image: a slim accent band carries the badge */
	.cts-next-side-top-plain {
		background: var(--cts-accent-soft, #eaf6ef);
		padding: 14px 18px;
	}
	.cts-next-side-eyebrow {
		position: absolute;
		top: 11px;
		left: 11px;
		display: flex;
		align-items: center;
		gap: 6px;
		background: rgba(255, 255, 255, 0.94);
		color: var(--cts-accent, #2e9d5b);
		font-size: 10px;
		font-weight: 700;
		letter-spacing: 0.09em;
		text-transform: uppercase;
		padding: 5px 9px;
		border-radius: 7px;
		box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
	}
	.cts-next-side-top-plain .cts-next-side-eyebrow {
		position: static;
		box-shadow: none;
		background: transparent;
		padding: 0;
	}
	.cts-next-side-body {
		padding: 17px 18px 18px;
	}
	.cts-next-side .cts-next-titlerow {
		display: flex;
		align-items: center;
		gap: 9px;
		margin-bottom: 14px;
		flex-wrap: wrap;
	}
	.cts-next-side .cts-next-title {
		margin: 0;
		font-size: 19px;
		font-weight: 800;
		color: #1c2b22;
		letter-spacing: -0.01em;
		line-height: 1.2;
	}
	.cts-next-badge {
		display: inline-block;
		background: var(--cts-accent, #2e9d5b);
		color: #fff;
		font-size: 10.5px;
		font-weight: 700;
		padding: 4px 9px;
		border-radius: 6px;
	}
	.cts-next-badge-soft {
		background: var(--cts-accent-soft, #eaf6ef);
		color: var(--cts-accent, #268050);
	}
	.cts-next-side-list {
		display: flex;
		flex-direction: column;
		gap: 11px;
	}
	.cts-next-side-row {
		display: flex;
		align-items: center;
		gap: 10px;
	}
	.cts-next-chip {
		display: flex;
		width: 28px;
		height: 28px;
		border-radius: 8px;
		background: var(--cts-accent-soft, #eaf6ef);
		color: var(--cts-accent, #2e9d5b);
		align-items: center;
		justify-content: center;
		flex: none;
	}
	.cts-next-side-text {
		font-size: 13px;
		color: #5c6670;
		line-height: 1.35;
	}
	.cts-next-side-strong {
		font-size: 13.5px;
		font-weight: 600;
		color: #2a3a30;
	}
	.cts-next-cta {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		background: var(--cts-accent, #2e9d5b);
		color: #fff;
		font-size: 13px;
		font-weight: 600;
		text-decoration: none;
		border-radius: 8px;
		cursor: pointer;
		transition: filter 0.15s ease;
	}
	.cts-next-cta:hover {
		filter: brightness(0.92);
		color: #fff;
	}
	.cts-next-cta-block {
		display: flex;
		justify-content: center;
		margin-top: 16px;
		padding: 10px;
	}
	/* Empty state */
	.cts-next-side.cts-next-empty .cts-next-empty-body {
		padding: 28px 18px;
		text-align: center;
		color: #7a838c;
		font-size: 13.5px;
	}
	.cts-next-side.cts-next-empty .cts-next-empty-body p {
		margin: 0;
	}
</style>
