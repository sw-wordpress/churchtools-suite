<?php
/**
 * Next Event View - Main (Edition A)
 *
 * Horizontale "Feature"-Karte: Bild links, Inhalt rechts (Titel, Badge,
 * Beschreibung, Datum/Uhrzeit, Ort + Call-to-Action).
 * Vorlage stammt aus Claude Design ("Naechster Termin" – Edition A · Hauptinhalt).
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
$show_images                  = isset( $args['show_images'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['show_images'] ) : true;
$image_fit                    = isset( $args['image_fit'] ) ? ChurchTools_Suite_Shortcodes::sanitize_image_fit( $args['image_fit'] ) : 'cover';
$use_calendar_colors          = isset( $args['use_calendar_colors'] ) ? ChurchTools_Suite_Shortcodes::parse_boolean( $args['use_calendar_colors'] ) : true;

$heading = __( 'Nächster Termin', 'churchtools-suite' );

$next_event = ! empty( $events ) ? $events[0] : null;

if ( ! $next_event ) {
	$empty_message = ! empty( $args['empty_message'] ) ? $args['empty_message'] : __( 'Aktuell sind keine anstehenden Termine verfügbar.', 'churchtools-suite' );
	echo '<div class="cts-next-event cts-next-main cts-next-empty"><div class="cts-next-empty-body"><p>' . esc_html( $empty_message ) . '</p></div></div>';
	return;
}

$event_action          = $args['event_action'] ?? 'none';
$single_event_base     = apply_filters( 'churchtools_suite_single_event_base_url', home_url( '/events/' ) );
$single_event_template = get_option( 'churchtools_suite_single_template', 'professional' );

// Event data.
$event_id          = $next_event['id'] ?? $next_event['event_id'] ?? $next_event['appointment_id'] ?? 0;
$event_title       = $next_event['title'] ?? '';
$event_description = $next_event['event_description'] ?? '';
$appt_description  = $next_event['appointment_description'] ?? '';
$start_datetime    = $next_event['start_datetime'] ?? '';
$end_datetime      = $next_event['end_datetime'] ?? '';
$calendar_name     = $next_event['calendar_name'] ?? '';
$calendar_color    = $next_event['calendar_color'] ?? '#2e9d5b';
$location          = $next_event['location_name'] ?? '';

// Description (prefer event description, fall back to appointment).
$description = '';
if ( $show_event_description && ! empty( $event_description ) ) {
	$description = $event_description;
} elseif ( $show_appointment_description && ! empty( $appt_description ) ) {
	$description = $appt_description;
}

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

$date_display = $date_obj ? wp_date( 'D, j. F Y', $date_obj->getTimestamp(), $wp_timezone ) : '';

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
$icon_clock    = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>';
$icon_pin      = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.5-7-11a7 7 0 0 1 14 0c0 4.5-7 11-7 11Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>';
$icon_arrow    = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>';
?>

<div class="cts-next-event cts-next-main<?php echo $has_image ? '' : ' cts-next-main-noimage'; ?>" style="--cts-accent: <?php echo esc_attr( $accent ); ?>; --cts-accent-soft: <?php echo esc_attr( $accent_soft ); ?>;">

	<?php if ( $has_image ) : ?>
		<div class="cts-next-main-image" data-image-fit="<?php echo esc_attr( $image_fit ); ?>">
			<img src="<?php echo esc_url( $hero_image ); ?>" alt="<?php echo esc_attr( $event_title ); ?>" />
		</div>
	<?php endif; ?>

	<div class="cts-next-main-body">

		<div class="cts-next-eyebrow">
			<?php echo $icon_calendar; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span><?php echo esc_html( $heading ); ?></span>
		</div>

		<div class="cts-next-titlerow">
			<h3 class="cts-next-title"><?php echo esc_html( $event_title ); ?></h3>
			<?php if ( $show_calendar_name && ! empty( $calendar_name ) ) : ?>
				<span class="cts-next-badge"><?php echo esc_html( $calendar_name ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $description ) ) : ?>
			<p class="cts-next-desc"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $description ), 40 ) ); ?></p>
		<?php endif; ?>

		<?php if ( ( $date_display ) || ( $show_time && $time_display ) ) : ?>
			<div class="cts-next-meta">
				<?php if ( $date_display ) : ?>
					<div class="cts-next-meta-item">
						<span class="cts-next-chip"><?php echo $icon_calendar; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<div>
							<div class="cts-next-meta-label"><?php esc_html_e( 'Datum', 'churchtools-suite' ); ?></div>
							<div class="cts-next-meta-value"><?php echo esc_html( $date_display ); ?></div>
						</div>
					</div>
				<?php endif; ?>
				<?php if ( $show_time && $time_display ) : ?>
					<div class="cts-next-meta-item">
						<span class="cts-next-chip"><?php echo $icon_clock; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<div>
							<div class="cts-next-meta-label"><?php esc_html_e( 'Uhrzeit', 'churchtools-suite' ); ?></div>
							<div class="cts-next-meta-value"><?php echo esc_html( $time_display ); ?></div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( ( $show_location && ! empty( $location ) ) || $show_cta ) : ?>
			<div class="cts-next-footer">
				<?php if ( $show_location && ! empty( $location ) ) : ?>
					<div class="cts-next-location">
						<?php echo $icon_pin; // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<span><?php echo esc_html( $location ); ?></span>
					</div>
				<?php else : ?>
					<span></span>
				<?php endif; ?>
				<?php if ( $show_cta ) : ?>
					<a class="cts-next-cta<?php echo esc_attr( $cta_class ); ?>" href="<?php echo esc_url( $cta_href ); ?>"<?php echo $cta_attrs; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
						<?php echo esc_html( $cta_label ); ?>
						<?php echo $icon_arrow; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

	</div>
</div>

<style>
	.cts-next-main {
		display: flex;
		background: #ffffff;
		border: 1px solid #e8ecef;
		border-radius: 16px;
		overflow: hidden;
		box-shadow: 0 6px 24px -12px rgba(20, 40, 30, 0.18);
		font-family: inherit;
	}
	.cts-next-main-image {
		width: 210px;
		flex: none;
		align-self: stretch;
		min-height: 240px;
		background: #e2e8f0;
	}
	.cts-next-main-image img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		display: block;
	}
	.cts-next-main-image[data-image-fit="contain"] img {
		object-fit: contain;
		background: #f4f6f8;
	}
	.cts-next-main-body {
		flex: 1;
		padding: 24px 26px;
		display: flex;
		flex-direction: column;
		min-width: 0;
	}
	.cts-next-eyebrow {
		display: flex;
		align-items: center;
		gap: 7px;
		color: var(--cts-accent, #2e9d5b);
		margin-bottom: 14px;
	}
	.cts-next-eyebrow span {
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.1em;
		text-transform: uppercase;
	}
	.cts-next-titlerow {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 14px;
		flex-wrap: wrap;
	}
	.cts-next-title {
		margin: 0;
		font-size: 23px;
		font-weight: 800;
		color: #1c2b22;
		letter-spacing: -0.01em;
		line-height: 1.2;
	}
	.cts-next-badge {
		display: inline-block;
		background: var(--cts-accent, #2e9d5b);
		color: #fff;
		font-size: 11px;
		font-weight: 600;
		padding: 5px 11px;
		border-radius: 6px;
	}
	.cts-next-desc {
		margin: 0 0 20px;
		font-size: 14px;
		line-height: 1.55;
		color: #5c6670;
		max-width: 46ch;
	}
	.cts-next-meta {
		display: flex;
		gap: 24px;
		margin-top: auto;
		padding-top: 18px;
		border-top: 1px solid #eef1f3;
		flex-wrap: wrap;
	}
	.cts-next-meta-item {
		display: flex;
		align-items: center;
		gap: 9px;
	}
	.cts-next-chip {
		display: flex;
		width: 30px;
		height: 30px;
		border-radius: 8px;
		background: var(--cts-accent-soft, #eaf6ef);
		color: var(--cts-accent, #2e9d5b);
		align-items: center;
		justify-content: center;
		flex: none;
	}
	.cts-next-meta-label {
		font-size: 10px;
		font-weight: 700;
		letter-spacing: 0.07em;
		text-transform: uppercase;
		color: #9aa0a6;
	}
	.cts-next-meta-value {
		font-size: 13.5px;
		font-weight: 600;
		color: #2a3a30;
	}
	.cts-next-footer {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		margin-top: 18px;
		flex-wrap: wrap;
	}
	.cts-next-location {
		display: flex;
		align-items: center;
		gap: 7px;
		font-size: 12.5px;
		color: #7a838c;
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
		padding: 9px 16px;
		border-radius: 8px;
		cursor: pointer;
		transition: filter 0.15s ease;
	}
	.cts-next-cta:hover {
		filter: brightness(0.92);
		color: #fff;
	}
	/* No-image variant */
	.cts-next-main-noimage .cts-next-main-body {
		padding: 28px;
	}
	/* Empty state */
	.cts-next-empty {
		background: #fff;
		border: 1px solid #e8ecef;
		border-radius: 16px;
	}
	.cts-next-empty-body {
		padding: 36px 24px;
		text-align: center;
		color: #7a838c;
		font-size: 14px;
	}
	.cts-next-empty-body p {
		margin: 0;
	}
	/* Responsive */
	@media (max-width: 600px) {
		.cts-next-main {
			flex-direction: column;
		}
		.cts-next-main-image {
			width: 100%;
			min-height: 0;
			height: 180px;
		}
	}
</style>
