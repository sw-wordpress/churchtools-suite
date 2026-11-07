<?php
/**
 * Elementor Events Widget for ChurchTools Suite
 *
 * Displays ChurchTools events in Elementor using the built-in shortcodes
 * Provides UI controls for all shortcode parameters
 *
 * @package ChurchTools_Suite_Elementor
 * @since   0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only define class if not already defined.
if ( ! class_exists( 'CTS_Elementor_Events_Widget' ) ) {

	class CTS_Elementor_Events_Widget extends \Elementor\Widget_Base {

		/**
		 * Get widget name
		 */
		public function get_name() {
			return 'churchtools_suite_events';
		}

		/**
		 * Get widget title
		 */
		public function get_title() {
			return __( 'ChurchTools Events', 'churchtools-suite' );
		}

		/**
		 * Get widget icon
		 */
		public function get_icon() {
			return 'eicon-calendar';
		}

		/**
		 * Get widget categories
		 */
		public function get_categories() {
			return array( 'basic', 'churchtools-suite' );
		}

		/**
		 * Register widget controls
		 */
		protected function register_controls() {
			// ========================================
			// CONTENT SECTION
			// ========================================
			$this->start_controls_section(
				'content_section',
				array(
					'label' => __( 'Inhalt', 'churchtools-suite' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);

			// Event Action (Modal, Page, None).
			$this->add_control(
				'event_action',
				array(
					'label'       => __( 'Bei Event-Klick', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => array(
						'modal' => __( 'Modal öffnen', 'churchtools-suite' ),
						'page'  => __( 'Event-Seite öffnen', 'churchtools-suite' ),
						'none'  => __( 'Nicht anklickbar', 'churchtools-suite' ),
					),
					'default'     => 'modal',
					'description' => __( 'Modal = Popup-Fenster, Event-Seite = Eigene Seite mit URL-Parameter', 'churchtools-suite' ),
				)
			);

			// View Type (List/Grid/Calendar/Countdown/Carousel).
			$this->add_control(
				'view_type',
				array(
					'label'       => __( 'Ansichtstyp', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => array(
						'list'      => __( 'Liste', 'churchtools-suite' ),
						'grid'      => __( 'Gitter', 'churchtools-suite' ),
						'calendar'  => __( 'Kalender', 'churchtools-suite' ),
						'countdown' => __( 'Countdown', 'churchtools-suite' ),
						'carousel'  => __( 'Karussell', 'churchtools-suite' ),
					),
					'default'     => 'list',
					'description' => __( 'Wähle zwischen Listenansicht, Gitteransicht und Kalender', 'churchtools-suite' ),
				)
			);

			// View Template - List.
			$this->add_control(
				'view_list',
				array(
					'label'     => __( 'Template', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'options'   => array(
						'simple'              => __( 'Einfach', 'churchtools-suite' ),
						'classic'             => __( 'Klassisch', 'churchtools-suite' ),
						'classic-with-images' => __( 'Klassisch mit Bildern', 'churchtools-suite' ),
						'minimal'             => __( 'Minimal', 'churchtools-suite' ),
						'modern'              => __( 'Modern', 'churchtools-suite' ),
					),
					'default'   => 'classic',
					'condition' => array( 'view_type' => 'list' ),
				)
			);

			// View Template - Grid.
			$this->add_control(
				'view_grid',
				array(
					'label'     => __( 'Template', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'options'   => array(
						'simple' => __( 'Einfach', 'churchtools-suite' ),
						'modern' => __( 'Modern', 'churchtools-suite' ),
					),
					'default'   => 'simple',
					'condition' => array( 'view_type' => 'grid' ),
				)
			);

			// View Template - Calendar.
			$this->add_control(
				'view_calendar',
				array(
					'label'     => __( 'Template', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'options'   => array(
						'monthly-simple' => __( 'Monat (Simple)', 'churchtools-suite' ),
					),
					'default'   => 'monthly-simple',
					'condition' => array( 'view_type' => 'calendar' ),
				)
			);

			// View Template - Countdown.
			$this->add_control(
				'view_countdown',
				array(
					'label'       => __( 'Template', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => array(
						'countdown-klassisch' => __( 'Split-Layout', 'churchtools-suite' ),
					),
					'default'     => 'countdown-klassisch',
					'condition'   => array( 'view_type' => 'countdown' ),
					'description' => __( 'Zeigt den nächsten Event mit Live-Countdown', 'churchtools-suite' ),
				)
			);

			// View Template - Carousel.
			$this->add_control(
				'view_carousel',
				array(
					'label'       => __( 'Template', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => array(
						'carousel-klassisch'    => __( 'Klassisch (Swipe)', 'churchtools-suite' ),
						'carousel-einzel-event' => __( 'Einzel Event (Hero Slider)', 'churchtools-suite' ),
					),
					'default'     => 'carousel-klassisch',
					'condition'   => array( 'view_type' => 'carousel' ),
					'description' => __( 'Horizontales Karussell mit Touch/Swipe-Navigation', 'churchtools-suite' ),
				)
			);

			// Limit.
			$this->add_control(
				'limit',
				array(
					'label'     => __( 'Anzahl Events', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'default'   => 5,
					'min'       => 1,
					'max'       => 100,
					'condition' => array(
						'view_type!' => array( 'calendar', 'countdown' ),
					),
				)
			);

			// Columns (nur für Grid).
			$this->add_control(
				'columns',
				array(
					'label'     => __( 'Spalten', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'default'   => 3,
					'min'       => 1,
					'max'       => 6,
					'condition' => array(
						'view_type' => 'grid',
					),
				)
			);

			// Slides per View (nur für Carousel).
			$this->add_control(
				'slides_per_view',
				array(
					'label'       => __( 'Slides pro Ansicht', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'default'     => 3,
					'min'         => 1,
					'max'         => 6,
					'condition'   => array(
						'view_type' => 'carousel',
					),
					'description' => __( 'Anzahl der gleichzeitig sichtbaren Event-Karten (responsive)', 'churchtools-suite' ),
				)
			);

			// Autoplay.
			$this->add_control(
				'autoplay',
				array(
					'label'        => __( 'Auto-Play', 'churchtools-suite' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'An', 'churchtools-suite' ),
					'label_off'    => __( 'Aus', 'churchtools-suite' ),
					'return_value' => 'true',
					'default'      => '',
					'condition'    => array(
						'view_type' => 'carousel',
					),
					'description'  => __( 'Automatisches Weiterschalten der Slides', 'churchtools-suite' ),
				)
			);

			// Autoplay Delay.
			$this->add_control(
				'autoplay_delay',
				array(
					'label'       => __( 'Auto-Play Verzögerung (ms)', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'default'     => 5000,
					'min'         => 1000,
					'max'         => 10000,
					'step'        => 500,
					'condition'   => array(
						'view_type' => 'carousel',
						'autoplay'  => 'true',
					),
					'description' => __( 'Zeit zwischen automatischen Slide-Wechseln', 'churchtools-suite' ),
				)
			);

			// Loop.
			$this->add_control(
				'loop',
				array(
					'label'        => __( 'Endlos-Modus', 'churchtools-suite' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'An', 'churchtools-suite' ),
					'label_off'    => __( 'Aus', 'churchtools-suite' ),
					'return_value' => 'true',
					'default'      => 'true',
					'condition'    => array(
						'view_type' => 'carousel',
					),
					'description'  => __( 'Carousel springt vom letzten zum ersten Slide', 'churchtools-suite' ),
				)
			);

			$this->end_controls_section();

			// ========================================
			// FILTER SECTION
			// ========================================
			$this->start_controls_section(
				'filter_section',
				array(
					'label' => __( 'Filter', 'churchtools-suite' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);

			// Calendars.
			$this->add_control(
				'calendars',
				array(
					'label'       => __( 'Kalender', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::SELECT2,
					'options'     => $this->get_calendars_options(),
					'multiple'    => true,
					'label_block' => true,
					'description' => __( 'Leer = alle ausgewählten Kalender', 'churchtools-suite' ),
				)
			);

			// Tags.
			$this->add_control(
				'tags',
				array(
					'label'       => __( 'Tags', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::SELECT2,
					'options'     => $this->get_tags_options(),
					'multiple'    => true,
					'label_block' => true,
					'description' => __( 'Leer = alle Tags', 'churchtools-suite' ),
				)
			);

			// Event-Auswahl (nur für Countdown).
			$this->add_control(
				'event_id',
				array(
					'label'       => __( 'Event-Auswahl', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::SELECT2,
					'options'     => $this->get_events_options(),
					'default'     => '0',
					'condition'   => array(
						'view_type' => 'countdown',
					),
					'description' => __( 'Wähle ein spezifisches Event oder lass es automatisch', 'churchtools-suite' ),
				)
			);

			// Show past events.
			$this->add_control(
				'show_past_events',
				array(
					'label'     => __( 'Vergangene Events anzeigen', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'label_on'  => __( 'Ja', 'churchtools-suite' ),
					'label_off' => __( 'Nein', 'churchtools-suite' ),
					'default'   => 'no',
				)
			);

			$this->end_controls_section();

			// ========================================
			// DISPLAY OPTIONS SECTION
			// ========================================
			$this->start_controls_section(
				'display_section',
				array(
					'label'       => __( 'Anzeigeoptionen', 'churchtools-suite' ),
					'tab'         => \Elementor\Controls_Manager::TAB_CONTENT,
					'description' => __( '💡 Die verfügbaren Optionen passen sich automatisch an die gewählte View an. Nicht unterstützte Optionen werden ausgeblendet.', 'churchtools-suite' ),
				)
			);
			$this->add_control(
				'show_event_description',
				array(
					'label'      => __( 'Event-Beschreibung', 'churchtools-suite' ),
					'type'       => \Elementor\Controls_Manager::SWITCHER,
					'label_on'   => __( 'Ja', 'churchtools-suite' ),
					'label_off'  => __( 'Nein', 'churchtools-suite' ),
					'default'    => 'yes',
					'conditions' => array(
						'relation' => 'or',
						'terms'    => array(
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'list',
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'grid',
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'carousel',
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'countdown',
							),
						),
					),
				)
			);

			$this->add_control(
				'show_appointment_description',
				array(
					'label'      => __( 'Termin-Beschreibung', 'churchtools-suite' ),
					'type'       => \Elementor\Controls_Manager::SWITCHER,
					'label_on'   => __( 'Ja', 'churchtools-suite' ),
					'label_off'  => __( 'Nein', 'churchtools-suite' ),
					'default'    => 'yes',
					'conditions' => array(
						'relation' => 'or',
						'terms'    => array(
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'list',
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'grid',
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'carousel',
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'countdown',
							),
						),
					),
				)
			);

			$this->add_control(
				'show_location',
				array(
					'label'      => __( 'Ort', 'churchtools-suite' ),
					'type'       => \Elementor\Controls_Manager::SWITCHER,
					'label_on'   => __( 'Ja', 'churchtools-suite' ),
					'label_off'  => __( 'Nein', 'churchtools-suite' ),
					'default'    => 'yes',
					'conditions' => array(
						'relation' => 'or',
						'terms'    => array(
							array(
								'relation' => 'and',
								'terms'    => array(
									array(
										'name'     => 'view_type',
										'operator' => '===',
										'value'    => 'list',
									),
									array(
										'name'     => 'view_list',
										'operator' => '!==',
										'value'    => 'minimal',
									),
								),
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'grid',
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'carousel',
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'countdown',
							),
						),
					),
				)
			);

			$this->add_control(
				'show_time',
				array(
					'label'       => __( 'Uhrzeit', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'label_on'    => __( 'Ja', 'churchtools-suite' ),
					'label_off'   => __( 'Nein', 'churchtools-suite' ),
					'default'     => 'yes',
					'description' => __( '✅ Wird von allen Views unterstützt', 'churchtools-suite' ),
				)
			);

			$this->add_control(
				'show_tags',
				array(
					'label'      => __( 'Tags', 'churchtools-suite' ),
					'type'       => \Elementor\Controls_Manager::SWITCHER,
					'label_on'   => __( 'Ja', 'churchtools-suite' ),
					'label_off'  => __( 'Nein', 'churchtools-suite' ),
					'default'    => 'yes',
					'conditions' => array(
						'relation' => 'or',
						'terms'    => array(
							array(
								'relation' => 'and',
								'terms'    => array(
									array(
										'name'     => 'view_type',
										'operator' => '===',
										'value'    => 'list',
									),
									array(
										'name'     => 'view_list',
										'operator' => '!==',
										'value'    => 'minimal',
									),
								),
							),
							array(
								'relation' => 'and',
								'terms'    => array(
									array(
										'name'     => 'view_type',
										'operator' => '===',
										'value'    => 'grid',
									),
									array(
										'name'     => 'view_grid',
										'operator' => '===',
										'value'    => 'modern',
									),
								),
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'carousel',
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'countdown',
							),
						),
					),
				)
			);

			$this->add_control(
				'show_title',
				array(
					'label'     => __( 'Titel anzeigen (Hero)', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'label_on'  => __( 'Ja', 'churchtools-suite' ),
					'label_off' => __( 'Nein', 'churchtools-suite' ),
					'default'   => 'yes',
					'condition' => array(
						'view_type'     => 'carousel',
						'view_carousel' => 'carousel-einzel-event',
					),
				)
			);

			$this->add_control(
				'show_images',
				array(
					'label'      => __( 'Bilder', 'churchtools-suite' ),
					'type'       => \Elementor\Controls_Manager::SWITCHER,
					'label_on'   => __( 'Ja', 'churchtools-suite' ),
					'label_off'  => __( 'Nein', 'churchtools-suite' ),
					'default'    => 'yes',
					'conditions' => array(
						'relation' => 'or',
						'terms'    => array(
							array(
								'relation' => 'and',
								'terms'    => array(
									array(
										'name'     => 'view_type',
										'operator' => '===',
										'value'    => 'list',
									),
									array(
										'name'     => 'view_list',
										'operator' => '===',
										'value'    => 'classic-with-images',
									),
								),
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'grid',
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'carousel',
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'countdown',
							),
						),
					),
				)
			);

			$this->add_control(
				'show_calendar_name',
				array(
					'label'      => __( 'Kalendername', 'churchtools-suite' ),
					'type'       => \Elementor\Controls_Manager::SWITCHER,
					'label_on'   => __( 'Ja', 'churchtools-suite' ),
					'label_off'  => __( 'Nein', 'churchtools-suite' ),
					'default'    => 'yes',
					'conditions' => array(
						'relation' => 'or',
						'terms'    => array(
							array(
								'relation' => 'and',
								'terms'    => array(
									array(
										'name'     => 'view_type',
										'operator' => '===',
										'value'    => 'list',
									),
									array(
										'name'     => 'view_list',
										'operator' => '!==',
										'value'    => 'minimal',
									),
								),
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'grid',
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'carousel',
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'countdown',
							),
						),
					),
				)
			);

			$this->add_control(
				'show_month_separator',
				array(
					'label'     => __( 'Monatstrenner', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'label_on'  => __( 'Ja', 'churchtools-suite' ),
					'label_off' => __( 'Nein', 'churchtools-suite' ),
					'default'   => 'yes',
					'condition' => array(
						'view_type' => 'list',
					),
				)
			);

			$this->add_control(
				'show_filter',
				array(
					'label'       => __( 'Filter im Frontend anzeigen', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'label_on'    => __( 'Ja', 'churchtools-suite' ),
					'label_off'   => __( 'Nein', 'churchtools-suite' ),
					'default'     => 'no',
					'condition'   => array(
						'view_type' => 'list',
					),
					'description' => __( 'Blendet oberhalb der Event-Liste Kalender- und Tag-Filter ein.', 'churchtools-suite' ),
				)
			);

			$this->add_control(
				'show_services',
				array(
					'label'      => __( 'Dienste', 'churchtools-suite' ),
					'type'       => \Elementor\Controls_Manager::SWITCHER,
					'label_on'   => __( 'Ja', 'churchtools-suite' ),
					'label_off'  => __( 'Nein', 'churchtools-suite' ),
					'default'    => 'no',
					'conditions' => array(
						'relation' => 'or',
						'terms'    => array(
							array(
								'relation' => 'and',
								'terms'    => array(
									array(
										'name'     => 'view_type',
										'operator' => '===',
										'value'    => 'list',
									),
									array(
										'name'     => 'view_list',
										'operator' => '!==',
										'value'    => 'minimal',
									),
								),
							),
							array(
								'name'     => 'view_type',
								'operator' => '===',
								'value'    => 'grid',
							),
						),
					),
				)
			);

			$this->end_controls_section();

			// =========================
			// Style Section
			// =========================
			$this->start_controls_section(
				'style_section',
				array(
					'label' => __( 'Stil', 'churchtools-suite' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);

			$this->add_control(
				'style_mode',
				array(
					'label'       => __( 'Style-Modus', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => array(
						'plugin' => __( 'Plugin-Styles', 'churchtools-suite' ),
						'theme'  => __( 'Theme-Styles', 'churchtools-suite' ),
						'custom' => __( 'Individuelle Styles', 'churchtools-suite' ),
					),
					'default'     => 'plugin',
					'description' => __( 'Plugin-Styles = eigene Farbpalette, Theme-Styles = Theme-Farben (inherit), Individuelle Styles = eigene Farben definieren', 'churchtools-suite' ),
				)
			);

			$this->add_control(
				'style_media_heading',
				array(
					'label'     => __( 'Bild & Hero', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);

			$this->add_control(
				'image_fit',
				array(
					'label'       => __( 'Bilddarstellung', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => array(
						'cover'   => __( 'Zuschneiden (Cover)', 'churchtools-suite' ),
						'contain' => __( 'Ganzes Bild (Contain)', 'churchtools-suite' ),
					),
					'default'     => 'cover',
					'condition'   => array(
						'show_images' => 'yes',
					),
					'description' => __( 'Contain zeigt das komplette Bild mit ggf. freien Flächen.', 'churchtools-suite' ),
				)
			);

			$this->add_control(
				'hero_title_font_size',
				array(
					'label'       => __( 'Titel-Schriftgröße (Hero)', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'default'     => 0,
					'min'         => 0,
					'max'         => 120,
					'condition'   => array(
						'view_type'     => 'carousel',
						'view_carousel' => 'carousel-einzel-event',
					),
					'description' => __( '0 = automatisch (responsive). Sonst feste Pixelgröße.', 'churchtools-suite' ),
				)
			);

			$this->add_control(
				'hero_layout_preset',
				array(
					'label'     => __( 'Bild/Höhen-Preset (Hero)', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'options'   => array(
						'compact'  => __( 'Kompakt', 'churchtools-suite' ),
						'standard' => __( 'Standard', 'churchtools-suite' ),
						'hero'     => __( 'Hero', 'churchtools-suite' ),
					),
					'default'   => 'standard',
					'condition' => array(
						'view_type'     => 'carousel',
						'view_carousel' => 'carousel-einzel-event',
					),
				)
			);

			$this->add_control(
				'hero_mobile_optimize',
				array(
					'label'     => __( 'Mobile-Optimierung (Hero)', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'label_on'  => __( 'Ja', 'churchtools-suite' ),
					'label_off' => __( 'Nein', 'churchtools-suite' ),
					'default'   => 'yes',
					'condition' => array(
						'view_type'     => 'carousel',
						'view_carousel' => 'carousel-einzel-event',
					),
				)
			);

			$this->add_control(
				'style_theme_heading',
				array(
					'label'     => __( 'Farben & Layout', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);

			$this->add_control(
				'use_calendar_colors',
				array(
					'label'       => __( 'Kalenderfarben verwenden', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => array(
						'no'  => __( 'Nein', 'churchtools-suite' ),
						'yes' => __( 'Ja', 'churchtools-suite' ),
					),
					'default'     => 'no',
					'description' => __( 'Nutzt die Farbe des jeweiligen Kalenders als Akzentfarbe (unabhängig vom Style-Modus).', 'churchtools-suite' ),
				)
			);

			$this->add_control(
				'custom_primary_color',
				array(
					'label'     => __( 'Primärfarbe', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'default'   => '#2563eb',
					'condition' => array(
						'style_mode' => 'custom',
					),
				)
			);

			$this->add_control(
				'custom_text_color',
				array(
					'label'     => __( 'Textfarbe', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'default'   => '#1e293b',
					'condition' => array(
						'style_mode' => 'custom',
					),
				)
			);

			$this->add_control(
				'custom_background_color',
				array(
					'label'     => __( 'Hintergrundfarbe', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'default'   => '#ffffff',
					'condition' => array(
						'style_mode' => 'custom',
					),
				)
			);

			$this->add_control(
				'custom_border_radius',
				array(
					'label'     => __( 'Border Radius', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'default'   => 6,
					'min'       => 0,
					'max'       => 50,
					'unit'      => 'px',
					'condition' => array(
						'style_mode' => 'custom',
					),
				)
			);

			$this->add_control(
				'custom_font_size',
				array(
					'label'     => __( 'Schriftgröße', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'default'   => 14,
					'min'       => 10,
					'max'       => 32,
					'unit'      => 'px',
					'condition' => array(
						'style_mode' => 'custom',
					),
				)
			);

			$this->add_control(
				'custom_padding',
				array(
					'label'     => __( 'Padding', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'default'   => 12,
					'min'       => 0,
					'max'       => 50,
					'unit'      => 'px',
					'condition' => array(
						'style_mode' => 'custom',
					),
				)
			);

			$this->add_control(
				'custom_spacing',
				array(
					'label'     => __( 'Abstände', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'default'   => 8,
					'min'       => 0,
					'max'       => 50,
					'unit'      => 'px',
					'condition' => array(
						'style_mode' => 'custom',
					),
				)
			);

			// v1.3.0: Custom CSS Section.
			$this->add_control(
				'custom_css_heading',
				array(
					'label'     => __( 'Benutzerdefiniertes CSS', 'churchtools-suite' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);

			$this->add_control(
				'custom_css',
				array(
					'label'       => __( 'CSS', 'churchtools-suite' ),
					'type'        => \Elementor\Controls_Manager::CODE,
					'language'    => 'css',
					'rows'        => 12,
					'default'     => '',
					'description' => __( 'Geben Sie beliebiges CSS ein. Es wird auf diese Widget-Instanz beschränkt.', 'churchtools-suite' ),
					'separator'   => 'before',
				)
			);

			$this->end_controls_section();
		}

		/**
		 * Render widget
		 */
		protected function render() {
			$settings = $this->get_settings_for_display();

			// If a single event is requested via URL, always render single view.
			// This keeps deep-links working on Elementor pages independent of widget click mode.
			/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, display filters, or status messages; no state is changed by these query arguments. */ $event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
			if ( $event_id > 0 ) {
				/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, display filters, or status messages; no state is changed by these query arguments. */ $template = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : '';
				require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-single-event-handler.php';
				/* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Templates escape their values and intentionally return complete HTML, styles, and scripts. */ echo ChurchTools_Suite_Single_Event_Handler::render_single_event_page( $event_id, $template );
				return;
			}

			// Determine selected view based on type.
			$view_type     = $settings['view_type'] ?? 'list';
			$selected_view = null;

			if ( 'list' === $view_type && ! empty( $settings['view_list'] ) ) {
				$selected_view = $settings['view_list'];
			} elseif ( 'grid' === $view_type && ! empty( $settings['view_grid'] ) ) {
				$selected_view = $settings['view_grid'];
			} elseif ( 'calendar' === $view_type && ! empty( $settings['view_calendar'] ) ) {
				$selected_view = $settings['view_calendar'];
			} elseif ( 'countdown' === $view_type && ! empty( $settings['view_countdown'] ) ) {
				$selected_view = $settings['view_countdown'];
			} elseif ( 'carousel' === $view_type && ! empty( $settings['view_carousel'] ) ) {
				$selected_view = $settings['view_carousel'];
			} else {
				// Fallback für alte Widgets.
				$selected_view = $settings['view'] ?? 'classic';
			}

			// Build shortcode attributes.
			$atts = array(
				'view'                         => $selected_view,
				'show_event_description'       => $this->is_switcher_enabled( $settings, 'show_event_description', true ) ? '1' : '0',
				'show_appointment_description' => $this->is_switcher_enabled( $settings, 'show_appointment_description', true ) ? '1' : '0',
				'show_location'                => $this->is_switcher_enabled( $settings, 'show_location', true ) ? '1' : '0',
				'show_time'                    => $this->is_switcher_enabled( $settings, 'show_time', true ) ? '1' : '0',
				'show_tags'                    => $this->is_switcher_enabled( $settings, 'show_tags', false ) ? '1' : '0',
				'show_title'                   => $this->is_switcher_enabled( $settings, 'show_title', true ) ? '1' : '0',
				'show_images'                  => $this->is_switcher_enabled( $settings, 'show_images', true ) ? '1' : '0',
				'image_fit'                    => in_array( $settings['image_fit'] ?? 'cover', array( 'cover', 'contain' ), true ) ? $settings['image_fit'] : 'cover',
				'hero_title_font_size'         => max( 0, min( 120, intval( $settings['hero_title_font_size'] ?? 0 ) ) ),
				'hero_layout_preset'           => in_array( $settings['hero_layout_preset'] ?? 'standard', array( 'compact', 'standard', 'hero' ), true ) ? $settings['hero_layout_preset'] : 'standard',
				'hero_mobile_optimize'         => $this->is_switcher_enabled( $settings, 'hero_mobile_optimize', true ) ? '1' : '0',
				'show_calendar_name'           => $this->is_switcher_enabled( $settings, 'show_calendar_name', true ) ? '1' : '0',
				'show_services'                => $this->is_switcher_enabled( $settings, 'show_services', false ) ? '1' : '0',
				'show_past_events'             => $this->is_switcher_enabled( $settings, 'show_past_events', false ) ? '1' : '0',
				'show_month_separator'         => $this->is_switcher_enabled( $settings, 'show_month_separator', true ) ? '1' : '0',
				'show_filter'                  => $this->is_switcher_enabled( $settings, 'show_filter', false ) ? '1' : '0',
				'event_action'                 => isset( $settings['event_action'] ) ? $settings['event_action'] : 'modal',
				'style_mode'                   => $settings['style_mode'] ?? 'theme',
				'use_calendar_colors'          => $this->is_switcher_enabled( $settings, 'use_calendar_colors', false ) ? '1' : '0',
				'custom_primary_color'         => $settings['custom_primary_color'] ?? '#2563eb',
				'custom_text_color'            => $settings['custom_text_color'] ?? '#1e293b',
				'custom_background_color'      => $settings['custom_background_color'] ?? '#ffffff',
				'custom_border_radius'         => $settings['custom_border_radius'] ?? 6,
				'custom_font_size'             => $settings['custom_font_size'] ?? 14,
				'custom_padding'               => $settings['custom_padding'] ?? 12,
				'custom_spacing'               => $settings['custom_spacing'] ?? 8,
			);

			// Add limit for non-calendar and non-countdown views.
			if ( 'calendar' !== $view_type && 'countdown' !== $view_type ) {
				$atts['limit'] = $settings['limit'] ?? 5;
			}

			// Add countdown event_id (0 = automatic).
			if ( 'countdown' === $view_type ) {
				$atts['event_id'] = absint( $settings['event_id'] ?? 0 );
			}

			// Add calendars filter if specified.
			if ( ! empty( $settings['calendars'] ) ) {
				$atts['calendars'] = implode( ',', $settings['calendars'] );
			}

			// Add tags filter if specified.
			if ( ! empty( $settings['tags'] ) ) {
				$atts['tags'] = implode( ',', $settings['tags'] );
			}

			// v1.3.0: Add custom CSS (scoped to this widget instance).
			if ( ! empty( $settings['custom_css'] ) ) {
				$atts['_custom_css'] = wp_strip_all_tags( wp_unslash( $settings['custom_css'] ) );
			}

			// Determine shortcode tag based on view type.
			$shortcode_tag = 'cts_list'; // Default.

			if ( 'grid' === $view_type ) {
				$shortcode_tag   = 'cts_grid';
				$atts['columns'] = $settings['columns'] ?? 3;
			} elseif ( 'calendar' === $view_type ) {
				$shortcode_tag = 'cts_calendar';
			} elseif ( 'countdown' === $view_type ) {
				$shortcode_tag = 'cts_countdown';
			} elseif ( 'carousel' === $view_type ) {
				$shortcode_tag           = 'cts_carousel';
				$atts['slides_per_view'] = $settings['slides_per_view'] ?? 3;
				$atts['autoplay']        = $settings['autoplay'] ?? 'false';
				$atts['autoplay_delay']  = $settings['autoplay_delay'] ?? 5000;
				$atts['loop']            = $settings['loop'] ?? 'true';
			}

			// Execute shortcode.
			echo do_shortcode( '[' . $shortcode_tag . ' ' . $this->build_shortcode_atts( $atts ) . ']' );
		}

		/**
		 * Build shortcode attributes string
		 *
		 * @param array $atts Attributes array
		 * @return string Attributes string
		 */
		private function build_shortcode_atts( $atts ) {
			$output = '';
			foreach ( $atts as $key => $value ) {
				$output .= ' ' . $key . '="' . esc_attr( $value ) . '"';
			}
			return $output;
		}

		/**
		 * Normalize Elementor switcher values across versions (yes/true/1/on).
		 *
		 * @param array<string,mixed> $settings
		 */
		private function is_switcher_enabled( array $settings, string $key, bool $default = false ): bool {
			if ( ! array_key_exists( $key, $settings ) ) {
				return $default;
			}

			$value = $settings[ $key ];

			if ( is_bool( $value ) ) {
				return $value;
			}

			if ( is_numeric( $value ) ) {
				return (int) 0 !== $value;
			}

			if ( is_string( $value ) ) {
				$normalized = strtolower( trim( $value ) );
				if ( in_array( $normalized, array( 'yes', 'true', '1', 'on' ), true ) ) {
					return true;
				}
				if ( in_array( $normalized, array( 'no', 'false', '0', 'off', '' ), true ) ) {
					return false;
				}
			}

			return $default;
		}

		/**
		 * Get calendars options
		 *
		 * @return array Calendar options
		 */
		private function get_calendars_options() {
			// v1.0.8.0: Use factory.
			$repo      = churchtools_suite_get_repository( 'calendars' );
			$calendars = $repo->get_all();

			$options = array();
			foreach ( $calendars as $calendar ) {
				$options[ $calendar->calendar_id ] = $calendar->name;
			}

			return $options;
		}

		/**
		 * Get tags options
		 *
		 * @return array Tags options
		 */
		private function get_tags_options() {
			// v1.0.8.0: Use factory.
			$repo = churchtools_suite_get_repository( 'events' );

			// Get all unique tags from database.
			$all_events = $repo->get_all();
			$tags_set   = array();

			foreach ( $all_events as $event ) {
				if ( ! empty( $event->tags ) ) {
					$tags_data = json_decode( $event->tags, true );
					if ( is_array( $tags_data ) ) {
						foreach ( $tags_data as $tag ) {
							if ( isset( $tag['name'] ) ) {
								$tags_set[ $tag['name'] ] = $tag['name'];
							}
						}
					}
				}
			}

			return $tags_set;
		}

		/**
		 * Get events options for countdown selection
		 *
		 * @return array Event options
		 */
		private function get_events_options() {
			// Check cache first (5 minute transient).
			$cache_key = 'cts_elementor_events_options';
			$cached    = get_transient( $cache_key );

			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}

			$options = array(
				'0' => __( 'Nächstes Event (automatisch)', 'churchtools-suite' ),
			);

			// Try to load events from database.
			if ( ! class_exists( 'ChurchTools_Suite_Events_Repository' ) ) {
				require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
				require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-events-repository.php';
			}

			if ( ! class_exists( 'ChurchTools_Suite_Events_Repository' ) ) {
				return $options;
			}

			try {
				$events_repo     = new ChurchTools_Suite_Events_Repository();
				$upcoming_events = $events_repo->get_upcoming( 50 ); // Next 50 events.

				if ( ! empty( $upcoming_events ) ) {
					foreach ( $upcoming_events as $event ) {
						$date_format = get_option( 'date_format', 'd.m.Y' );
						$event_date  = '';

						if ( ! empty( $event->start_datetime ) ) {
							// Convert to timestamp for wp_date (which handles locale).
							$timestamp  = strtotime( get_date_from_gmt( $event->start_datetime, 'Y-m-d H:i:s' ) );
							$event_date = wp_date( $date_format, $timestamp ) . ' - ';
						}

						$event_id                      = (int) ( $event->event_id ?: $event->appointment_id );
						$options[ (string) $event_id ] = $event_date . $event->title;
					}
				}

				// Cache for 5 minutes.
				set_transient( $cache_key, $options, 5 * MINUTE_IN_SECONDS );

			} catch ( Exception $e ) {
				// On error, return only auto option (don't cache errors).
				return $options;
			}

			return $options;
		}
	}
}
