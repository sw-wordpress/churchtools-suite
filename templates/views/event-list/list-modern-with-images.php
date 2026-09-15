<?php

/**
 * List View - Modern with Images
 *
 * Clean row layout: date badge on the left, content in the middle,
 * optional event image floated to the right.
 *
 * @package ChurchTools_Suite
 * @since   1.2.0.0
 * @version 1.0.0
 *
 * Available variables:
 * @var array $events Events data
 * @var array $args   Shortcode arguments
 */

if (! defined('ABSPATH')) {
	exit;
}

$show_event_description       = isset($args['show_event_description'])       ? ChurchTools_Suite_Shortcodes::parse_boolean($args['show_event_description'])       : true;
$show_appointment_description = isset($args['show_appointment_description']) ? ChurchTools_Suite_Shortcodes::parse_boolean($args['show_appointment_description']) : true;
$show_location                = isset($args['show_location'])                ? ChurchTools_Suite_Shortcodes::parse_boolean($args['show_location'])                : true;
$show_time                    = isset($args['show_time'])                    ? ChurchTools_Suite_Shortcodes::parse_boolean($args['show_time'])                    : true;
$show_month_separator         = isset($args['show_month_separator'])         ? ChurchTools_Suite_Shortcodes::parse_boolean($args['show_month_separator'])         : true;
$show_images                  = isset($args['show_images'])                  ? ChurchTools_Suite_Shortcodes::parse_boolean($args['show_images'])                  : true;
$use_calendar_colors          = isset($args['use_calendar_colors'])          ? ChurchTools_Suite_Shortcodes::parse_boolean($args['use_calendar_colors'])          : false;

$max_event_height = $args['max_event_height'] ?? "180px";
$style_mode    = $args['style_mode'] ?? 'theme';
$custom_styles = '';

if ($style_mode === 'plugin') {
	$custom_styles = '--cts-primary-color: #16a34a; --cts-text-color: #1e293b; --cts-bg-color: #ffffff;';
} elseif ($style_mode === 'custom') {
	$primary       = $args['custom_primary_color']    ?? '#16a34a';
	$text          = $args['custom_text_color']        ?? '#1e293b';
	$bg            = $args['custom_background_color']  ?? '#ffffff';
	$custom_styles = sprintf(
		'--cts-primary-color: %s; --cts-text-color: %s; --cts-bg-color: %s;',
		esc_attr($primary),
		esc_attr($text),
		esc_attr($bg)
	);
}

$current_month = null;

// Output CSS once per page load
static $cts_mwi_styles_printed = false;
if (! $cts_mwi_styles_printed) :
	$cts_mwi_styles_printed = true;
	?>
	<style id="cts-list-modern-images-css">
	.cts-list--modern-images {
		font-family: inherit;
		color: var(--cts-text-color, #1e293b);
		box-shadow: none;

		--cts-date-color: #64748b;
	}

	.cts-list--modern-images__month-separator {
		display: flex;
		align-items: center;
		gap: 14px;
		margin: 32px 0 4px;
		color: #64748b;
		font-size: 0.875rem;
	}
	.cts-list--modern-images__month-separator::after {
		content: '';
		flex: 1;
		height: 1px;
		background: #e2e8f0;
	}

	.cts-list--modern-images__item {
		display: flex;
		align-items: flex-start;
		gap: 24px;
		padding: 22px 0;
	}

	.cts-list--modern-images__item--clickable,
	.cts-list--modern-images__item--page-link {
		cursor: pointer;

		<?php if ( !empty($max_event_height)): ?>max-height:<?php echo $max_event_height; ?>;<?php endif; ?>
		overflow: hidden;
	}
	.cts-list--modern-images__item--clickable:hover .cts-list--modern-images__title,
	.cts-list--modern-images__item--page-link:hover .cts-list--modern-images__title {
		text-decoration: underline;
	}

	.cts-list--modern-images__date {
		color: var(--cts-date-color);
		flex: 0 0 52px;
		text-align: center;
		line-height: 1;
		padding-top: 2px;
	}
	.cts-list--modern-images__date-weekday {
		color: var(--cts-date-color);
		display: block;
		font-size: 0.7rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		/* color: #64748b; */
		margin-bottom: 2px;
	}
	.cts-list--modern-images__date-day {
		color: var(--cts-date-color);		
		display: block;
		font-size: 2rem;
		font-weight: 700;
		line-height: 1;
	}

	.cts-list--modern-images__body {
		flex: 1;
		min-width: 0;
	}

	.cts-list--modern-images__meta {
		font-size: 0.85rem;
		color: #64748b;
		margin-bottom: 4px;
	}

	.cts-list--modern-images__title {
		font-size: 1.1rem;
		font-weight: 600;
		color: var(--cts-primary-color, #16a34a);
		margin: 0 0 6px;
		line-height: 1.35;
	}

	.cts-list--modern-images__location {
		display: flex;
		align-items: center;
		gap: 4px;
		font-size: 0.85rem;
		color: #374151;
		margin-bottom: 8px;
	}
	.cts-list--modern-images__location .dashicons {
		font-size: 1rem;
		width: 1rem;
		height: 1rem;
		flex-shrink: 0;
		color: #6b7280;
	}

	.cts-list--modern-images__description {
		display: -webkit-box;
    	-webkit-line-clamp: 4;
    	-webkit-box-orient: vertical;
    	overflow: hidden;
	}
	.cts-list--modern-images__description {
		font-size: 0.9rem;
		color: #4b5563;
		line-height: 1.55;
		margin: 0;
	}

	.cts-list--modern-images__image {
		flex: 0 0 270px;
		width: 270px;
		border-radius: 6px;
		overflow: hidden;
		align-self: stretch;
		min-height: 120px;
	}
	.cts-list--modern-images__image img {
		display: block;
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	
	.cts-list--modern-images-items {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 40px 30px;
	}

	@media (max-width: 768px) {
		.cts-list--modern-images__image {
			flex: 0 0 160px;
			width: 160px;
			min-height: 100px;
		}


	@media (max-width: 600px) {
		.cts-list--modern-images__item {
			flex-wrap: wrap;
		}
		.cts-list--modern-images__image {
			display: none;
		}
		.cts-list--modern-images__date {
			order: 0;
		}
		.cts-list--modern-images__body {
			order: 1;
		}
	}
	</style>
<?php endif; ?>

<div class="churchtools-suite-wrapper" data-style-mode="<?php echo esc_attr($style_mode); ?>"<?php echo $custom_styles ? ' style="' . esc_attr($custom_styles) . '"' : ''; ?>>
	<div class="cts-list cts-list--modern-images" data-view="list-modern-images">
		<?php $opened_month_container = false; ?>
		<?php foreach ($events as $event) : ?>

			<?php
			$event_month = get_date_from_gmt($event['start_datetime'], 'Y-m');
			if ($show_month_separator && ($current_month === null || $current_month !== $event_month)) :
				$current_month = $event_month;

			?>
				<?php if ( $opened_month_container ): ?></div><?php endif; ?>
				<div class="cts-list--modern-images__month-separator">
					<time datetime="<?php echo esc_attr($event_month); ?>">
						<?php echo esc_html(date_i18n('F Y', strtotime(get_date_from_gmt($event['start_datetime'])))); ?>
					</time>
				</div>
				<div class="cts-list--modern-images-items">
			<?php 
				$opened_month_container = true; 
			endif; ?>

			<?php
			// Click / navigation behaviour
			$event_action = $args['event_action'] ?? 'modal';
			$click_class  = '';
			$click_attrs  = '';

			if ($event_action === 'modal') {
				$click_class = ' cts-list--modern-images__item--clickable cts-event-clickable';
				$click_attrs = sprintf(
					' data-event-id="%s" role="button" tabindex="0" aria-label="%s"',
					esc_attr($event['id']),
					esc_attr(sprintf(__('Details für %s anzeigen', 'churchtools-suite'), $event['title']))
				);
			} elseif ($event_action === 'page') {
				$click_class = ' cts-list--modern-images__item--page-link';
				$single_base = apply_filters('churchtools_suite_single_event_base_url', home_url('/events/'));
				$single_tmpl = get_option('churchtools_suite_single_template', 'professional');
				$page_url    = add_query_arg(
					[
						'event_id'      => $event['id'],
						'template'      => $single_tmpl,
						'ctse_context'  => 'elementor',
					],
					$single_base
				);
				$click_attrs = sprintf(
					' data-event-id="%s" data-event-url="%s" role="link" tabindex="0" aria-label="%s"',
					esc_attr($event['id']),
					esc_url($page_url),
					esc_attr(sprintf(__('Zu %s navigieren', 'churchtools-suite'), $event['title']))
				);
			}

			// Calendar color override for title
			$calendar_color = $event['calendar_color'] ?? '';
			$title_style    = '';
			if ($use_calendar_colors && ! empty($calendar_color)) {
				$title_style = sprintf(' style="color: %s;"', esc_attr($calendar_color));
			}

			// Image HTML (empty string = no image)
			$image_html = '';
			if ($show_images) {
				$event_arr   = (array) $event;
				$cal_img     = ! empty($event_arr['calendar_image_id']) ? ['calendar_image_id' => $event_arr['calendar_image_id']] : null;
				$image_html  = ChurchTools_Suite_Image_Helper::get_image(
					$event_arr,
					$cal_img,
					false,
					[
						'class'   => 'cts-list--modern-images__img',
						'alt'     => esc_attr($event['title'] ?? ''),
						'loading' => 'lazy',
						'width'   => 270,
						'height'  => 190,
					]
				);

				if ( strpos($image_html, 'fallback-event-image.jpg') !== false) {
					# no fallback image
					$image_html = '';
				}
			}

			// Single description: prefer event_description, fall back to appointment_description
			$description = '';
			if ($show_event_description && ! empty($event['event_description'])) {
				$description = $event['event_description'];
			} elseif ($show_appointment_description && ! empty($event['appointment_description'])) {
				$description = $event['appointment_description'];
			}
			?>

			<article class="cts-list--modern-images__item<?php echo esc_attr($click_class); ?>"<?php echo $click_attrs; ?>>

				<div class="cts-list--modern-images__date">
					<span class="cts-list--modern-images__date-weekday"><?php echo esc_html(strtoupper($event['start_weekday'])); ?></span>
					<span class="cts-list--modern-images__date-day"><?php echo esc_html($event['start_day']); ?></span>
				</div>

				<div class="cts-list--modern-images__body">

					<?php if ($show_time) : ?>
						<div class="cts-list--modern-images__meta">
							<?php
							$date_label = date_i18n('j. F', strtotime(get_date_from_gmt($event['start_datetime'])));
							echo esc_html($date_label);
							if (! empty($event['start_time'])) {
								echo ' ' . esc_html__('von', 'churchtools-suite') . ' ' . esc_html($event['start_time']);
								if (! empty($event['end_time'])) {
									echo ' - ' . esc_html($event['end_time']);
								}
							}
							?>
						</div>
					<?php endif; ?>

					<h3 class="cts-list--modern-images__title"<?php echo $title_style; ?>>
						<?php echo esc_html($event['title']); ?>
					</h3>

					<?php if ($show_location && (! empty($event['address_street']) || ! empty($event['address_name']) || ! empty($event['location_name']))) : ?>
						<div class="cts-list--modern-images__location">
							<span class="dashicons dashicons-location" aria-hidden="true"></span>
							<span>
								<?php
								if (! empty($event['address_street'])) {
									$loc_parts = array_filter([
										$event['address_street'],
										trim(($event['address_zip'] ?? '') . ' ' . ($event['address_city'] ?? '')),
									]);
									echo esc_html(implode(', ', $loc_parts));
								} elseif (! empty($event['address_name'])) {
									echo esc_html($event['address_name']);
								} else {
									echo esc_html($event['location_name']);
								}
								?>
							</span>
						</div>
					<?php endif; ?>

					<?php if ($description) : ?>
						<p class="cts-list--modern-images__description">
							<?php echo esc_html(wp_trim_words($description, 35)); ?>
						</p>
					<?php endif; ?>

				</div>

				<?php if ($image_html) : ?>
					<div class="cts-list--modern-images__image">
						<?php echo $image_html; ?>
					</div>
				<?php endif; ?>

			</article>

		<?php endforeach; ?>
		<?php if ( $opened_month_container ): ?></div><?php endif; ?>
	</div>
</div>