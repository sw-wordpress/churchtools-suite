<?php
/**
 * Gutenberg Blocks
 *
 * CLEAN SLATE v1.0.0 - Complete Rewrite
 * Minimal, focused, maintainable
 *
 * @package ChurchTools_Suite
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChurchTools_Suite_Blocks {

	/**
	 * Register all blocks
	 */
	public static function register(): void {
		// Check if Gutenberg is available.
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Register block category.
		add_filter( 'block_categories_all', array( __CLASS__, 'register_block_category' ), 10, 2 );

		// Register block editor script FIRST (v0.9.9.5)
		// CSS wird zentral via admin_enqueue_scripts geladen (v1.0.6.0).
		$block_script_path    = CHURCHTOOLS_SUITE_PATH . 'assets/js/churchtools-suite-blocks.js';
		$block_script_version = file_exists( $block_script_path ) ? (string) filemtime( $block_script_path ) : CHURCHTOOLS_SUITE_VERSION;

		wp_register_script(
			'churchtools-suite-blocks',
			CHURCHTOOLS_SUITE_URL . 'assets/js/churchtools-suite-blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			$block_script_version,
			false // Load in header, not footer.
		);

		// Add translations.
		wp_set_script_translations( 'churchtools-suite-blocks', 'churchtools-suite' );

		// v0.9.9.94: Load calendars and tags for filter dropdowns.
		self::localize_block_data();

		// Register ChurchTools Events Block.
		register_block_type(
			'churchtools-suite/events',
			array(
				'api_version'     => 3, // v1.1.4.4: Updated for iframe editor compatibility.
				'category'        => 'churchtools-suite',
				'supports'        => array(
					'html'            => false,
					'customClassName' => true,
					'anchor'          => true,
				),
				'attributes'      => array(
					'viewType'                     => array(
						'type'    => 'string',
						'default' => 'list',
					),
					'view'                         => array(
						'type'    => 'string',
						'default' => 'classic',
					), // Auto-switches to grid-classic when viewType=grid.
					'limit'                        => array(
						'type'    => 'number',
						'default' => 5,
					),
					'columns'                      => array(
						'type'    => 'number',
						'default' => 3,
					),
					'slides_per_view'              => array(
						'type'    => 'number',
						'default' => 3,
					), // Carousel.
					'autoplay'                     => array(
						'type'    => 'boolean',
						'default' => false,
					), // Carousel.
					'autoplay_delay'               => array(
						'type'    => 'number',
						'default' => 5000,
					), // Carousel.
					'loop'                         => array(
						'type'    => 'boolean',
						'default' => true,
					), // Carousel.
					'calendars'                    => array(
						'type'    => 'string',
						'default' => '',
					),
					'tags'                         => array(
						'type'    => 'string',
						'default' => '',
					),
					'event_id'                     => array(
						'type'    => 'number',
						'default' => 0,
					), // v1.1.3.0: Countdown - spezifischer Event.
					'show_event_description'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'show_appointment_description' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'show_location'                => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'show_services'                => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'show_time'                    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'show_tags'                    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'show_calendar_name'           => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'show_filter'                  => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'show_images'                  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'image_fit'                    => array(
						'type'    => 'string',
						'default' => 'cover',
					),
					'hero_title_font_size'         => array(
						'type'    => 'number',
						'default' => 0,
					),
					'hero_layout_preset'           => array(
						'type'    => 'string',
						'default' => 'standard',
					),
					'hero_mobile_optimize'         => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'show_month_separator'         => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'show_past_events'             => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'event_action'                 => array(
						'type'    => 'string',
						'default' => 'modal',
					),
					'style_mode'                   => array(
						'type'    => 'string',
						'default' => 'theme',
					),
					'use_calendar_colors'          => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'custom_primary_color'         => array(
						'type'    => 'string',
						'default' => '#2563eb',
					),
					'custom_text_color'            => array(
						'type'    => 'string',
						'default' => '#1e293b',
					),
					'custom_background_color'      => array(
						'type'    => 'string',
						'default' => '#ffffff',
					),
					'custom_border_radius'         => array(
						'type'    => 'number',
						'default' => 6,
					),
					'custom_font_size'             => array(
						'type'    => 'number',
						'default' => 14,
					),
					'custom_padding'               => array(
						'type'    => 'number',
						'default' => 12,
					),
					'custom_spacing'               => array(
						'type'    => 'number',
						'default' => 8,
					),
					'custom_css'                   => array(
						'type'    => 'string',
						'default' => '',
					), // v1.3.0: Custom CSS per block instance.
				),
				'render_callback' => array( __CLASS__, 'render_events_block' ),
				'editor_script'   => 'churchtools-suite-blocks', // v0.9.9.5: Explizite Verknüpfung mit JS
			// editor_style nicht nötig - CSS wird zentral via admin_enqueue_scripts geladen (v1.0.6.0).
			)
		);
	}

	/**
	 * Register block category
	 */
	/* phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Parameter retained for compatibility with the public WordPress callback signature. */ public static function register_block_category( $categories, $post ) {
		return array_merge(
			$categories,
			array(
				array(
					'slug'  => 'churchtools-suite',
					'title' => __( 'ChurchTools Suite', 'churchtools-suite' ),
					'icon'  => 'calendar-alt',
				),
			)
		);
	}

	/**
	 * Render Events Block
	 *
	 * @param array $attributes Block attributes
	 * @return string Rendered HTML
	 */
	public static function render_events_block( $attributes ): string {
		// Ensure services toggle is interpreted robustly across editor/runtime variants.
		$attributes['show_services'] = self::normalize_bool_attribute( $attributes, 'show_services', 'showServices', false );
		$attributes['show_filter']   = self::normalize_bool_attribute( $attributes, 'show_filter', 'showFilter', false );

		// v1.3.0: Pass custom_css to shortcode (will be handled by _custom_css scoping).
		if ( ! empty( $attributes['custom_css'] ) ) {
			$attributes['_custom_css'] = wp_strip_all_tags( wp_unslash( $attributes['custom_css'] ) );
		}

		// Support Gutenberg core "Additional CSS" field (stored as attributes.style.css).
		// Use it only when plugin-specific custom_css is empty.
		if ( empty( $attributes['_custom_css'] ) && ! empty( $attributes['style']['css'] ) && is_string( $attributes['style']['css'] ) ) {
			$attributes['_custom_css'] = wp_strip_all_tags( wp_unslash( $attributes['style']['css'] ) );
		}

		// v1.1.2.0: Convert Block attributes (camelCase) to Shortcode params (snake_case)
		// Route to appropriate shortcode handler.
		$view_type = ! empty( $attributes['viewType'] ) ? $attributes['viewType'] : 'list';

		// Convert camelCase Block attributes to snake_case Shortcode params
		// Blocks use: viewType, showPastEvents, etc.
		// Shortcodes expect: view_type, show_past_events, etc.
		// BUT: Most display options already use snake_case in both (show_event_description).

		// Ensure all boolean/number values are properly typed
		// (Block editor already sends correct types, but ensure compatibility).

		// v1.1.2.0: All view types active (List, Grid, Calendar, Countdown).
		if ( 'list' === $view_type ) {
			return ChurchTools_Suite_Shortcodes::list_shortcode( $attributes );
		}

		if ( 'grid' === $view_type ) {
			return ChurchTools_Suite_Shortcodes::grid_shortcode( $attributes );
		}

		if ( 'calendar' === $view_type ) {
			return ChurchTools_Suite_Shortcodes::calendar_shortcode( $attributes );
		}

		if ( 'countdown' === $view_type ) {
			return ChurchTools_Suite_Shortcodes::countdown_shortcode( $attributes );
		}

		// v1.1.3.0: Carousel Views.
		if ( 'carousel' === $view_type ) {
			return ChurchTools_Suite_Shortcodes::carousel_shortcode( $attributes );
		}

		return '<p>' . __( 'Dieser Ansichtstyp ist derzeit deaktiviert.', 'churchtools-suite' ) . '</p>';
	}

	/**
	 * Normalize boolean block attributes with optional fallback key.
	 *
	 * @param array<string,mixed> $attributes
	 */
	private static function normalize_bool_attribute( array $attributes, string $primary_key, string $fallback_key = '', bool $default = false ): bool {
		$value = $default;

		if ( array_key_exists( $primary_key, $attributes ) ) {
			$value = $attributes[ $primary_key ];
		} elseif ( '' !== $fallback_key && array_key_exists( $fallback_key, $attributes ) ) {
			$value = $attributes[ $fallback_key ];
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return (int) 0 !== $value;
		}

		if ( is_string( $value ) ) {
			$normalized = strtolower( trim( $value ) );
			if ( in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true ) ) {
				return true;
			}
			if ( in_array( $normalized, array( '0', 'false', 'no', 'off', '' ), true ) ) {
				return false;
			}
		}

		return $default;
	}

	/**
	 * Localize block data - pass calendars and tags to editor
	 *
	 * @since 0.9.9.94
	 */
	private static function localize_block_data(): void {
		// Load repositories.
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-calendars-repository.php';
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-events-repository.php';
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-template-loader.php';
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/view-feature-matrix.php';

		$calendars_repo = new ChurchTools_Suite_Calendars_Repository();
		$all_calendars  = $calendars_repo->get_all();

		// Format calendars for dropdown.
		$calendar_options = array();
		if ( ! empty( $all_calendars ) ) {
			foreach ( $all_calendars as $calendar ) {
				$calendar_options[] = array(
					'label' => $calendar->name,
					'value' => $calendar->calendar_id,
				);
			}
		}

		// v1.1.3.0: Load upcoming events for Countdown selector.
		$events_repo     = new ChurchTools_Suite_Events_Repository();
		$upcoming_events = $events_repo->get_upcoming( 50 ); // Next 50 events.

		$event_options = array(
			array(
				'label'       => __( 'Nächster Event (automatisch)', 'churchtools-suite' ),
				'value'       => 0,
				'calendar_id' => '',
				'tags'        => array(),
			),
		);

		if ( ! empty( $upcoming_events ) ) {
			foreach ( $upcoming_events as $event ) {
				$date_format = get_option( 'date_format', 'd.m.Y' );
				$event_date  = '';

				if ( ! empty( $event->start_datetime ) ) {
					$event_date = ' (' . get_date_from_gmt( $event->start_datetime, $date_format ) . ')';
				}

				// Extract tag IDs from tags JSON.
				$tag_ids = array();
				if ( ! empty( $event->tags ) ) {
					$tags = json_decode( $event->tags, true );
					if ( is_array( $tags ) ) {
						foreach ( $tags as $tag ) {
							if ( isset( $tag['id'] ) ) {
								$tag_ids[] = (string) $tag['id'];
							}
						}
					}
				}

				$event_options[] = array(
					'label'       => $event->title . $event_date,
					'value'       => (int) ( $event->event_id ?: $event->appointment_id ),
					'calendar_id' => (string) $event->calendar_id,
					'tags'        => $tag_ids,
				);
			}
		}

		// Get unique tags from events.
		global $wpdb;
		$table = $wpdb->prefix . CHURCHTOOLS_SUITE_DB_PREFIX . 'events';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
		$results = $wpdb->get_results(
			"SELECT DISTINCT tags FROM {$table} WHERE tags IS NOT NULL AND tags != ''",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		// Extract and merge all tags.
		$all_tags = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$tags = json_decode( $row['tags'], true );
				if ( is_array( $tags ) ) {
					foreach ( $tags as $tag ) {
						if ( isset( $tag['id'] ) && isset( $tag['name'] ) ) {
							$all_tags[ $tag['id'] ] = $tag['name'];
						}
					}
				}
			}
		}

		// Format tags for dropdown.
		$tag_options = array();
		foreach ( $all_tags as $id => $name ) {
			$tag_options[] = array(
				'label' => $name,
				'value' => (string) $id,
			);
		}

		// Sort by name.
		usort(
			$tag_options,
			function ( $a, $b ) {
				return strcmp( $a['label'], $b['label'] );
			}
		);

		// View registry (Single Source of Truth).
		$view_types = ChurchTools_Suite_Template_Loader::get_view_types_options();
		$views_map  = array(
			'list'      => ChurchTools_Suite_Template_Loader::get_view_options( 'list' ),
			'grid'      => ChurchTools_Suite_Template_Loader::get_view_options( 'grid' ),
			'calendar'  => ChurchTools_Suite_Template_Loader::get_view_options( 'calendar' ),
			'countdown' => ChurchTools_Suite_Template_Loader::get_view_options( 'countdown' ),
		);

		// Pass to editor.
		wp_localize_script(
			'churchtools-suite-blocks',
			'churchtoolsSuiteBlocks',
			array(
				'calendars'    => $calendar_options,
				'tags'         => $tag_options,
				'events'       => $event_options, // v1.1.3.0: Event selector for Countdown.
				'viewTypes'    => $view_types,
				'views'        => $views_map,
				'viewFeatures' => churchtools_suite_get_view_features(), // Feature matrix for conditional toggles.
			)
		);
	}
}
