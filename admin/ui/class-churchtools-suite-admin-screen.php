<?php
/**
 * Admin screen registry (single source of truth for the backend navigation).
 *
 * Before this class the navigation existed four times: as tabs in `admin-page.php`, as sub-tabs
 * hardcoded in `tab-settings.php`/`tab-data.php`/the debug block, as submenu entries in
 * `ChurchTools_Suite_Admin::add_plugin_admin_menu()`, and again as an inline jQuery flyout. Each
 * copy drifted. Screens, groups and their parents are declared once here; menus, in-page
 * navigation, breadcrumbs and rendering all derive from it.
 *
 * A screen is a section (slug) plus an optional sub-section (sub_slug). URLs stay
 * `admin.php?page=<top>&tab=<section>[&subtab=<sub-section>]`, i.e. every existing bookmark and
 * every link inside the existing view templates keeps working.
 *
 * @package ChurchTools_Suite
 * @since   1.4.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of admin screens, navigation helpers and the page shell renderer.
 */
class ChurchTools_Suite_Admin_Screen {

	/** Menu slug of the top level page. */
	public const TOP_LEVEL = 'churchtools-suite';

	/** Nonce action used by admin AJAX calls (settings forms retain cts_settings). */
	public const NONCE = 'churchtools_suite_admin';

	/** Capability that grants access to the plugin UI. */
	public const CAPABILITY = 'manage_churchtools_suite';

	/** External documentation (menu entry points here). */
	public const DOCUMENTATION_URL = 'https://plugin.feg-aschaffenburg.de/';

	/**
	 * Screen definitions.
	 *
	 * @var array<string, array<string, mixed>> Keyed by "section" or "section/sub-section".
	 */
	private static array $screens = array();

	/**
	 * Ordered group metadata used by the in-page rail.
	 *
	 * @var array<string, array{title:string,icon:string}>
	 */
	private static array $groups = array();

	/**
	 * Whether the default screens were registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Hook suffix of the top level menu page.
	 *
	 * @var string
	 */
	private static string $menu_hook = '';

	/**
	 * Register the default screens. Idempotent; addons may add screens on
	 * `churchtools_suite_admin_screens` (see `add_screen()`).
	 *
	 * @since 1.4.0.0
	 */
	public static function register_defaults(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;

		self::$groups = array(
			'main'       => array(
				'title' => __( 'Integration', 'churchtools-suite' ),
				'icon'  => 'dashicons-dashboard',
			),
			'data'       => array(
				'title' => __( 'Übersichten', 'churchtools-suite' ),
				'icon'  => 'dashicons-list-view',
			),
			'extensions' => array(
				'title' => __( 'Erweiterungen', 'churchtools-suite' ),
				'icon'  => 'dashicons-admin-plugins',
			),
		);

		// Main section: the three steps of the integration.
		self::add_screen(
			'dashboard',
			array(
				'title' => __( 'Dashboard', 'churchtools-suite' ),
				'icon'  => 'dashicons-dashboard',
				'view'  => 'dashboard.php',
				'group' => 'main',
			)
		);
		self::add_screen(
			'settings',
			array(
				'title'    => __( 'Einstellungen', 'churchtools-suite' ),
				'icon'     => 'dashicons-admin-settings',
				'view'     => 'settings.php',
				'group'    => 'main',
				'sub_menu' => true,
			)
		);
		self::add_screen(
			'sync',
			array(
				'title' => __( 'Synchronisation', 'churchtools-suite' ),
				'icon'  => 'dashicons-update',
				'view'  => 'sync.php',
				'group' => 'main',
			)
		);

		// Settings sub-screens.
		self::add_screen(
			'settings/api',
			array(
				'title'  => __( 'API & Verbindung', 'churchtools-suite' ),
				'icon'   => 'dashicons-admin-network',
				'view'   => 'settings/api.php',
				'parent' => 'settings',
				'group'  => 'main',
			)
		);
		self::add_screen(
			'settings/sync',
			array(
				'title'  => __( 'Sync-Einstellungen', 'churchtools-suite' ),
				'icon'   => 'dashicons-backup',
				'view'   => 'settings/sync.php',
				'parent' => 'settings',
				'group'  => 'main',
			)
		);
		self::add_screen(
			'settings/calendars',
			array(
				'title'  => __( 'Kalender', 'churchtools-suite' ),
				'icon'   => 'dashicons-calendar-alt',
				'view'   => 'settings/calendars.php',
				'parent' => 'settings',
				'group'  => 'main',
			)
		);
		self::add_screen(
			'settings/services',
			array(
				'title'  => __( 'Dienste', 'churchtools-suite' ),
				'icon'   => 'dashicons-groups',
				'view'   => 'settings/services.php',
				'parent' => 'settings',
				'group'  => 'main',
			)
		);
		self::add_screen(
			'settings/posts',
			array(
				'title'  => __( 'Posts', 'churchtools-suite' ),
				'icon'   => 'dashicons-post-status',
				'view'   => 'settings/posts.php',
				'parent' => 'settings',
				'group'  => 'main',
			)
		);
		self::add_screen(
			'settings/templates',
			array(
				'title'  => __( 'Darstellung', 'churchtools-suite' ),
				'icon'   => 'dashicons-layout',
				'view'   => 'settings/templates.php',
				'parent' => 'settings',
				'group'  => 'main',
			)
		);
		self::add_screen(
			'settings/advanced',
			array(
				'title'  => __( 'Erweitert', 'churchtools-suite' ),
				'icon'   => 'dashicons-admin-generic',
				'view'   => 'settings/advanced.php',
				'parent' => 'settings',
				'group'  => 'main',
			)
		);

		// Data overviews (previously the "Übersichten" flyout).
		// Overview section: the parent is a pure group, its children are the screens.
		self::add_screen(
			'data',
			array(
				'title' => __( 'Übersichten', 'churchtools-suite' ),
				'icon'  => 'dashicons-list-view',
				'group' => 'data',
			)
		);
		self::add_screen(
			'data/events',
			array(
				'title'  => __( 'Termine', 'churchtools-suite' ),
				'icon'   => 'dashicons-calendar-alt',
				'view'   => 'data/events.php',
				'parent' => 'data',
				'group'  => 'data',
			)
		);
		self::add_screen(
			'data/imported-services',
			array(
				'title'  => __( 'Importierte Dienste', 'churchtools-suite' ),
				'icon'   => 'dashicons-admin-generic',
				'view'   => 'data/imported-services.php',
				'parent' => 'data',
				'group'  => 'data',
			)
		);
		self::add_screen(
			'data/presets',
			array(
				'title'  => __( 'Shortcode-Vorlagen', 'churchtools-suite' ),
				'icon'   => 'dashicons-editor-code',
				'view'   => 'shortcode-manager.php',
				'parent' => 'data',
				'group'  => 'data',
			)
		);
		self::add_screen(
			'data/posts',
			array(
				'title'  => __( 'Übersicht Posts', 'churchtools-suite' ),
				'icon'   => 'dashicons-post-status',
				'parent' => 'data',
				'group'  => 'data',
				'render' => array( self::class, 'render_posts_overview' ),
				'hidden' => ! has_action( 'churchtools_suite_render_posts_overview' ),
			)
		);

		// Advanced mode (previously the hidden Debug tab).
		self::add_screen(
			'advanced',
			array(
				'title'    => __( 'Erweiterte Diagnose', 'churchtools-suite' ),
				'icon'     => 'dashicons-warning',
				'view'     => 'debug.php',
				'group'    => 'extensions',
				'advanced' => true,
				'sub_menu' => true,
			)
		);
		self::add_screen(
			'advanced/uebersicht',
			array(
				'title'    => __( 'Übersicht', 'churchtools-suite' ),
				'icon'     => 'dashicons-dashboard',
				'view'     => 'debug/uebersicht.php',
				'parent'   => 'advanced',
				'group'    => 'extensions',
				'advanced' => true,
			)
		);
		self::add_screen(
			'advanced/manuelle-trigger',
			array(
				'title'    => __( 'Manuelle Trigger', 'churchtools-suite' ),
				'icon'     => 'dashicons-controls-forward',
				'view'     => 'debug/manuelle-trigger.php',
				'parent'   => 'advanced',
				'group'    => 'extensions',
				'advanced' => true,
			)
		);
		self::add_screen(
			'advanced/logs',
			array(
				'title'    => __( 'Logs', 'churchtools-suite' ),
				'icon'     => 'dashicons-media-document',
				'view'     => 'debug/logs.php',
				'parent'   => 'advanced',
				'group'    => 'extensions',
				'advanced' => true,
			)
		);
		self::add_screen(
			'advanced/reset-cleanup',
			array(
				'title'    => __( 'Reset & Aufräumen', 'churchtools-suite' ),
				'icon'     => 'dashicons-delete',
				'view'     => 'debug/reset-cleanup.php',
				'parent'   => 'advanced',
				'group'    => 'extensions',
				'advanced' => true,
			)
		);

		// Addons and legal/info pages.
		self::add_screen(
			'addons',
			array(
				'title' => __( 'Addons', 'churchtools-suite' ),
				'icon'  => 'dashicons-admin-plugins',
				'view'  => 'addons-page.php',
				'group' => 'extensions',
			)
		);
		self::add_screen(
			'disclaimer',
			array(
				'title'  => __( 'Haftungsausschluss', 'churchtools-suite' ),
				'icon'   => 'dashicons-shield',
				'group'  => 'extensions',
				'hidden' => true,
				'render' => static function (): void {
					\ChurchTools_Suite_Admin::render_disclaimer_content();
				},
			)
		);
		self::add_screen(
			'docs',
			array(
				'title'    => __( 'Dokumentation', 'churchtools-suite' ),
				'icon'     => 'dashicons-media-text',
				'group'    => 'extensions',
				'external' => self::DOCUMENTATION_URL,
			)
		);

		/**
		 * Allow addons to register their own admin screens.
		 *
		 * @since 1.4.0.0
		 */
		if ( function_exists( 'apply_filters' ) ) {
			foreach ( (array) apply_filters( 'churchtools_suite_admin_screens', array() ) as $addon_screen ) {
				if ( is_callable( $addon_screen ) ) {
					$addon_screen( array( self::class, 'add_screen' ) );
				}
			}
		}
	}

	/**
	 * Register or replace one screen.
	 *
	 * Supported keys: `title` (string, required), `icon` (dashicon name), `view` (PHP file below
	 * `admin/views/`), `render` (callable, alternative to `view`), `parent` (section slug for
	 * sub-screens), `group` (rail group slug), `advanced` (bool, only in advanced mode),
	 * `external` (URL, the entry links out instead of rendering), `hidden` (bool, no rail entry),
	 * `sub_menu` (bool, gets its own wp-admin submenu entry), `fallback` (bool, sub-screens used
	 * when the parent has no child selected).
	 *
	 * @param string               $slug  "section" or "section/sub-section".
	 * @param array<string, mixed> $args Screen definition.
	 *
	 * @since 1.4.0.0
	 */
	public static function add_screen( string $slug, array $args ): void {
		self::register_defaults();
		$slug = strtolower( trim( $slug, "/ \t" ) );
		if ( '' === $slug || empty( $args['title'] ) ) {
			return;
		}
		$defaults               = array(
			'slug'     => $slug,
			'title'    => '',
			'icon'     => 'dashicons-admin-generic',
			'view'     => '',
			'render'   => null,
			'parent'   => '',
			'group'    => 'main',
			'advanced' => false,
			'external' => '',
			'hidden'   => false,
			'sub_menu' => false,
			'fallback' => false,
		);
		self::$screens[ $slug ] = array_merge( $defaults, wp_parse_args( $args, $defaults ), array( 'slug' => $slug ) );
	}

	/**
	 * Register a rail group (a titled block of navigation entries).
	 *
	 * @param string $slug  Group slug.
	 * @param string $title Group label.
	 * @param string $icon  Dashicon name.
	 *
	 * @since 1.4.0.0
	 */
	public static function add_group( string $slug, string $title, string $icon = 'dashicons-admin-generic' ): void {
		self::register_defaults();
		self::$groups[ $slug ] = array(
			'title' => $title,
			'icon'  => $icon,
		);
	}

	/**
	 * Remove a screen (addons use this for entries that do not apply to their install).
	 *
	 * @param string $slug Screen slug.
	 *
	 * @since 1.4.0.0
	 */
	public static function remove_screen( string $slug ): void {
		self::register_defaults();
		unset( self::$screens[ strtolower( trim( $slug, "/ \t" ) ) ] );
	}

	/**
	 * All registered screens keyed by slug.
	 *
	 * @return array<string, array<string, mixed>>
	 * @since 1.4.0.0
	 */
	public static function screens(): array {
		self::register_defaults();
		return self::$screens;
	}

	/**
	 * All registered rail groups keyed by slug.
	 *
	 * @return array<string, array{title:string, icon:string}>
	 * @since 1.4.0.0
	 */
	public static function groups(): array {
		self::register_defaults();
		return self::$groups;
	}

	/**
	 * Whether advanced mode (Diagnose-Bereich) is switched on.
	 *
	 * @since 1.4.0.0
	 */
	public static function advanced_mode(): bool {
		return (bool) get_option( 'churchtools_suite_advanced_mode', 0 );
	}

	/**
	 * Whether a screen may be shown to the current user right now.
	 *
	 * @param array<string, mixed> $screen Screen definition.
	 *
	 * @since 1.4.0.0
	 */
	public static function is_visible( array $screen ): bool {
		if ( ! empty( $screen['hidden'] ) ) {
			return false;
		}
		if ( ! empty( $screen['advanced'] ) && ! self::advanced_mode() ) {
			return false;
		}
		return true;
	}

	/**
	 * Sections (screens without parent).
	 *
	 * @return array<string, array<string, mixed>>
	 * @since 1.4.0.0
	 */
	public static function sections(): array {
		$sections = array();
		foreach ( self::screens() as $slug => $screen ) {
			if ( '' === $screen['parent'] ) {
				$sections[ $slug ] = $screen;
			}
		}
		return $sections;
	}

	/**
	 * Direct children of a section, ordered like the parent.
	 *
	 * @param string $section Section slug.
	 *
	 * @return array<string, array<string, mixed>>
	 * @since 1.4.0.0
	 */
	public static function children( string $section ): array {
		$children = array();
		foreach ( self::screens() as $slug => $screen ) {
			if ( $section === $screen['parent'] ) {
				$children[ $slug ] = $screen;
			}
		}
		return $children;
	}

	/**
	 * Resolve the requested screen from the current request.
	 *
	 * Unknown sections fall back to the dashboard, unknown sub-screens to the first visible child
	 * of the section, so a stale bookmark never renders an empty page.
	 *
	 * @return array{section:string, sub_slug:string, slug:string, screen:array<string, mixed>, parent:array<string, mixed>}
	 * @since 1.4.0.0
	 */
	public static function current(): array {
		$section  = self::requested_section();
		$sections = self::sections();
		if ( ! isset( $sections[ $section ] ) || ( ! empty( $sections[ $section ]['advanced'] ) && ! self::advanced_mode() ) ) {
			$section = 'dashboard';
		}
		$parent     = $sections[ $section ];
		$sub_wanted = self::requested_sub_slug();
		$children   = self::children( $section );

		// A section that only exists as a group of sub screens (e.g. "data") resolves to one of
		// its children: the requested sub-section, the child marked as landing screen, or simply
		// the first visible one.
		$group_only = '' === (string) $parent['view'] && ! is_callable( $parent['render'] ) && '' === (string) $parent['external'];
		$chosen     = $parent;

		if ( $group_only && $children ) {
			$chosen = null;
			if ( '' !== $sub_wanted && isset( self::$screens[ $section . '/' . $sub_wanted ] ) ) {
				$candidate = self::$screens[ $section . '/' . $sub_wanted ];
				if ( empty( $candidate['advanced'] ) || self::advanced_mode() ) {
					$chosen = $candidate;
				}
			}
			if ( null === $chosen ) {
				foreach ( $children as $child ) {
					if ( ! self::is_visible( $child ) ) {
						continue;
					}
					$chosen = $child;
					break;
				}
			}
			if ( null === $chosen ) {
				$chosen = $parent;
			}
		} elseif ( '' !== $sub_wanted ) {
			$candidate = isset( self::$screens[ $section . '/' . $sub_wanted ] ) ? self::$screens[ $section . '/' . $sub_wanted ] : null;

			// Unknown or hidden sub-sections fall back to the first visible child, so a stale
			// bookmark never renders an empty page.
			if ( null === $candidate || ( ! empty( $candidate['advanced'] ) && ! self::advanced_mode() ) ) {
				$candidate = null;
				foreach ( $children as $child ) {
					if ( self::is_visible( $child ) ) {
						$candidate = $child;
						break;
					}
				}
			}
			if ( null !== $candidate ) {
				$chosen = $candidate;
			}
		}

		return array(
			'section'  => $section,
			'sub_slug' => '' === $chosen['parent'] ? '' : substr( (string) $chosen['slug'], strlen( $section ) + 1 ),
			'slug'     => $chosen['slug'],
			'screen'   => $chosen,
			'parent'   => $parent,
		);
	}

	/**
	 * Requested section slug (`?tab=`, falling back to `?page=` for the standalone pages).
	 *
	 * @since 1.4.0.0
	 */
	public static function requested_section(): string {
		/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation argument; no state changes. */
		$tab = isset( $_GET['tab'] ) ? strtolower( sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) ) : '';
		if ( '' !== $tab ) {
			return 'debug' === $tab ? 'advanced' : $tab;
		}
		/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation argument; no state changes. */
		$page = isset( $_GET['page'] ) ? strtolower( sanitize_key( (string) wp_unslash( $_GET['page'] ) ) ) : '';
		if ( '' === $page ) {
			return 'dashboard';
		}
		$aliases = array(
			'churchtools-suite'            => 'dashboard',
			'churchtools-suite-data'       => 'data',
			'churchtools-suite-overviews'  => 'data',
			'churchtools-suite-addons'     => 'addons',
			'churchtools-suite-disclaimer' => 'disclaimer',
			'churchtools-suite-docs'       => 'docs',
		);
		if ( isset( $aliases[ $page ] ) ) {
			return $aliases[ $page ];
		}
		if ( strpos( $page, 'churchtools-suite-' ) === 0 ) {
			return substr( $page, strlen( 'churchtools-suite-' ) );
		}
		return $page;
	}

	/**
	 * Requested sub-section slug (`?subtab=`).
	 *
	 * @since 1.4.0.0
	 */
	public static function requested_sub_slug(): string {
		/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation argument; no state changes. */
		return isset( $_GET['subtab'] ) ? strtolower( sanitize_key( (string) wp_unslash( $_GET['subtab'] ) ) ) : '';
	}

	/**
	 * URL of a screen inside the plugin UI.
	 *
	 * Navigation links always carry `page`, `tab` and `subtab` so they stay independent of the
	 * current request (add_query_arg() falls back to the current query string).
	 *
	 * @param string $slug Screen slug ("section" or "section/sub").
	 *
	 * @since 1.4.0.0
	 */
	public static function url( string $slug ): string {
		$slug = strtolower( trim( $slug, "/ \t" ) );
		if ( isset( self::$screens[ $slug ] ) && '' !== (string) self::$screens[ $slug ]['external'] ) {
			return (string) self::$screens[ $slug ]['external'];
		}
		$parts = explode( '/', $slug, 2 );
		$args  = array(
			'page'   => self::TOP_LEVEL,
			'tab'    => $parts[0],
			'subtab' => isset( $parts[1] ) && '' !== $parts[1] ? $parts[1] : '',
		);
		return (string) add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Navigation tree for the rail: groups with their entries and children.
	 *
	 * @return array<int, array{slug:string, title:string, icon:string, entries:array<int, array<string, mixed>>}>
	 * @since 1.4.0.0
	 */
	public static function navigation(): array {
		$tree = array();
		foreach ( self::groups() as $group_slug => $group ) {
			$entries = array();
			foreach ( self::sections() as $slug => $screen ) {
				if ( $group_slug !== $screen['group'] || ! self::is_visible( $screen ) ) {
					continue;
				}
				$children = array();
				foreach ( self::children( $slug ) as $child_slug => $child ) {
					if ( ! self::is_visible( $child ) ) {
						continue;
					}
					$children[] = array(
						'slug'  => $child_slug,
						'title' => $child['title'],
						'icon'  => $child['icon'],
						'url'   => self::url( $child_slug ),
					);
				}
				$entries[] = array(
					'slug'     => $slug,
					'title'    => $screen['title'],
					'icon'     => $screen['icon'],
					'url'      => self::url( $slug ),
					'external' => (string) $screen['external'],
					'children' => $children,
				);
			}
			if ( $entries ) {
				$tree[] = array(
					'slug'    => $group_slug,
					'title'   => $group['title'],
					'icon'    => $group['icon'],
					'entries' => $entries,
				);
			}
		}
		return $tree;
	}

	/**
	 * Breadcrumb trail for the current screen.
	 *
	 * @return array<int, array{title:string, url:string}>
	 * @since 1.4.0.0
	 */
	public static function breadcrumb(): array {
		$current = self::current();
		$trail   = array(
			array(
				'title' => __( 'ChurchTools Integration', 'churchtools-suite' ),
				'url'   => self::url( 'dashboard' ),
			),
		);
		if ( 'dashboard' !== $current['section'] ) {
			$trail[] = array(
				'title' => $current['parent']['title'],
				'url'   => self::url( $current['section'] ),
			);
		}
		if ( $current['screen']['slug'] !== $current['section'] ) {
			$trail[] = array(
				'title' => $current['screen']['title'],
				'url'   => self::url( (string) $current['screen']['slug'] ),
			);
		}
		return $trail;
	}

	/**
	 * Render the full admin page: rail, top bar and the view of the current screen.
	 *
	 * @since 1.4.0.0
	 */
	public static function render_page(): void {
		self::register_defaults();
		$current = self::current();
		$screen  = $current['screen'];

		if ( '' !== (string) $screen['external'] ) {
			wp_redirect( (string) $screen['external'] ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Fixed external documentation host.
			exit;
		}

		echo '<div class="wrap cts-wrap">';
		self::render_rail();
		echo '<div class="cts-main">';
		self::render_topbar( $current );

		/**
		 * Fires inside the content area, before the screen view.
		 *
		 * @since 1.4.0.0
		 */
		do_action( 'churchtools_suite_admin_before_view', $current );

		self::render_view( $screen );

		/**
		 * Fires inside the content area, after the screen view.
		 *
		 * @since 1.4.0.0
		 */
		do_action( 'churchtools_suite_admin_after_view', $current );

		echo '</div></div>';
	}

	/**
	 * Include the view file of a screen (or call its renderer).
	 *
	 * @param array<string, mixed> $screen Screen definition.
	 *
	 * @since 1.4.0.0
	 */
	public static function render_view( array $screen ): void {
		if ( is_callable( $screen['render'] ) ) {
			call_user_func( $screen['render'] );
			return;
		}
		if ( '' === (string) $screen['view'] ) {
			echo '<div class="cts-card"><p>' . esc_html__( 'Für diese Ansicht ist keine Datei hinterlegt.', 'churchtools-suite' ) . '</p></div>';
			return;
		}
		$file = self::view_path( (string) $screen['view'] );
		if ( null === $file ) {
			echo '<div class="cts-card"><p>' . esc_html__( 'Die Ansichtsdatei fehlt.', 'churchtools-suite' ) . '</p></div>';
			return;
		}

		// Navigation is rendered once in the rail, including wrapper sections.
		$base = ob_get_level();
		include $file;

		// A view must never leave buffers open; that would swallow the rest of the page.
		while ( ob_get_level() > $base ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted PHP views escape dynamic values before writing to this buffer.
			echo (string) ob_get_clean();
		}
	}

	/**
	 * Absolute path of a view file below `admin/views/`, or null when it does not exist.
	 *
	 * @param string $view Relative view path.
	 *
	 * @since 1.4.0.0
	 */
	public static function view_path( string $view ): ?string {
		$view = str_replace( '\\', '/', $view );
		if ( '' === $view || substr( $view, -4 ) !== '.php' || strpos( $view, '..' ) !== false || strpos( $view, '//' ) === 0 ) {
			return null;
		}
		$file = trailingslashit( CHURCHTOOLS_SUITE_PATH . 'admin/views/' ) . ltrim( $view, '/' );
		return is_readable( $file ) ? $file : null;
	}

	/**
	 * Left rail: brand, grouped navigation, theme and rail toggle.
	 *
	 * @since 1.4.0.0
	 */
	public static function render_rail(): void {
		$current = self::current();
		$version = defined( 'CHURCHTOOLS_SUITE_VERSION' ) ? CHURCHTOOLS_SUITE_VERSION : '';
		?>
		<aside class="cts-rail" aria-label="<?php esc_attr_e( 'ChurchTools Navigation', 'churchtools-suite' ); ?>">
			<div class="cts-rail__brand">
				<span class="cts-rail__logo" aria-hidden="true"><span class="dashicons dashicons-calendar-alt"></span></span>
				<span class="cts-rail__name">
					<strong>ChurchTools</strong>
					<?php if ( '' !== $version ) : ?>
						<small>v<?php echo esc_html( $version ); ?></small>
					<?php endif; ?>
				</span>
				<button type="button" class="cts-icon-btn cts-rail__menu" data-cts-action="toggle-navigation"
					aria-expanded="false" aria-controls="cts-navigation" aria-label="<?php esc_attr_e( 'Navigation öffnen oder schließen', 'churchtools-suite' ); ?>">
					<span class="dashicons dashicons-menu" aria-hidden="true"></span>
				</button>
			</div>

			<nav class="cts-rail__nav" id="cts-navigation">
				<?php foreach ( self::navigation() as $group ) : ?>
					<div class="cts-rail__group">
						<h2 class="cts-rail__group-title"><?php echo esc_html( $group['title'] ); ?></h2>
						<ul>
							<?php foreach ( $group['entries'] as $entry ) : ?>
								<li>
									<a class="cts-nav-link<?php echo $entry['slug'] === $current['section'] && '' === $current['screen']['parent'] ? ' cts-nav-link--active' : ''; ?>"
										href="<?php echo esc_url( $entry['url'] ); ?>"
										<?php echo '' !== $entry['external'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
										<span class="dashicons <?php echo esc_attr( $entry['icon'] ); ?>" aria-hidden="true"></span>
										<span class="cts-nav-link__label"><?php echo esc_html( $entry['title'] ); ?></span>
									</a>
									<?php if ( $entry['children'] ) : ?>
										<ul class="cts-rail__sub">
											<?php foreach ( $entry['children'] as $child ) : ?>
												<li>
													<a class="cts-nav-sub<?php echo $child['slug'] === $current['slug'] ? ' cts-nav-sub--active' : ''; ?>"
														href="<?php echo esc_url( $child['url'] ); ?>">
														<span class="dashicons <?php echo esc_attr( $child['icon'] ); ?>" aria-hidden="true"></span>
														<span class="cts-nav-link__label"><?php echo esc_html( $child['title'] ); ?></span>
													</a>
												</li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</nav>

			<div class="cts-rail__tools">
				<button type="button" class="cts-icon-btn cts-rail__theme" data-cts-action="toggle-theme"
					aria-pressed="false" title="<?php esc_attr_e( 'Farbschema wechseln', 'churchtools-suite' ); ?>">
					<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
					<span class="cts-sr-only"><?php esc_html_e( 'Farbschema wechseln', 'churchtools-suite' ); ?></span>
				</button>
				<button type="button" class="cts-icon-btn cts-rail__collapse" data-cts-action="toggle-rail"
					aria-pressed="false" title="<?php esc_attr_e( 'Navigation einklappen', 'churchtools-suite' ); ?>">
					<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
					<span class="cts-sr-only"><?php esc_html_e( 'Navigation einklappen', 'churchtools-suite' ); ?></span>
				</button>
			</div>
		</aside>
		<?php
	}

	/**
	 * Top bar: breadcrumb, page title, connection state and the global sync action.
	 *
	 * @param array<string, mixed> $current Current screen (see `current()`).
	 *
	 * @since 1.4.0.0
	 */
	public static function render_topbar( array $current ): void {
		$state = self::connection_state();
		?>
		<header class="cts-topbar">
			<div class="cts-topbar__left">
				<nav class="cts-crumbs" aria-label="<?php esc_attr_e( 'Brotkrumen', 'churchtools-suite' ); ?>">
					<?php foreach ( self::breadcrumb() as $index => $crumb ) : ?>
						<?php if ( $index > 0 ) : ?>
							<span class="cts-crumbs__sep" aria-hidden="true">/</span>
						<?php endif; ?>
						<a href="<?php echo esc_url( $crumb['url'] ); ?>"<?php echo count( self::breadcrumb() ) - 1 === $index ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $crumb['title'] ); ?></a>
					<?php endforeach; ?>
				</nav>
				<h1 class="cts-page-title cts-sr-only"><?php echo esc_html( $current['screen']['title'] ); ?></h1>
			</div>
			<div class="cts-topbar__right">
				<span class="cts-chip <?php echo $state['connected'] ? 'is-ok' : ( $state['configured'] ? 'is-warn' : 'is-danger' ); ?>">
					<span class="cts-chip__dot" aria-hidden="true"></span>
					<?php
					if ( $state['connected'] ) {
						esc_html_e( 'Verbunden', 'churchtools-suite' );
					} elseif ( $state['configured'] ) {
						esc_html_e( 'Konfiguriert, nicht verbunden', 'churchtools-suite' );
					} else {
						esc_html_e( 'Nicht konfiguriert', 'churchtools-suite' );
					}
					?>
				</span>
				<button type="button" id="cts-sync-now" class="cts-btn cts-btn--primary" data-cts-action="sync-now">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e( 'Jetzt synchronisieren', 'churchtools-suite' ); ?>
				</button>
				<span id="cts-sync-result" class="cts-topbar__result" role="status" aria-live="polite" hidden></span>
			</div>
		</header>
		<?php
	}

	/**
	 * Connection state shown in the top bar.
	 *
	 * @return array{configured:bool, connected:bool, auth_method:string}
	 * @since 1.4.0.0
	 */
	public static function connection_state(): array {
		if ( ! function_exists( 'churchtools_suite_connection_state' ) ) {
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/functions/connection-state.php';
		}
		return churchtools_suite_connection_state();
	}

	/**
	 * Bridge for the Posts Sync addon overview (its own plugin renders its own page).
	 *
	 * @since 1.4.0.0
	 */
	public static function render_posts_overview(): void {
		if ( has_action( 'churchtools_suite_render_posts_overview' ) ) {
			do_action( 'churchtools_suite_render_posts_overview' );
			return;
		}
		echo '<div class="cts-card"><h2>' . esc_html__( 'Posts Sync ist nicht aktiv', 'churchtools-suite' ) . '</h2><p>'
			. esc_html__( 'Dieser Bereich gehört zum Addon „Posts Sync“. Aktiviere das Addon, um importierte Beiträge zu verwalten.', 'churchtools-suite' )
			. '</p><p><a class="cts-btn cts-btn--secondary" href="' . esc_url( self::url( 'addons' ) ) . '">'
			. esc_html__( 'Zu den Addons', 'churchtools-suite' ) . '</a></p></div>';
	}

	/**
	 * Remember the hook suffix of the top level menu page.
	 *
	 * @param string $hook Hook suffix returned by add_menu_page().
	 *
	 * @since 1.4.0.0
	 */
	public static function set_menu_hook( string $hook ): void {
		self::$menu_hook = $hook;
	}

	/**
	 * Hook suffix of the top level menu page.
	 *
	 * @since 1.4.0.0
	 */
	public static function menu_hook(): string {
		return self::$menu_hook;
	}

	/**
	 * Whether the current request belongs to a screen of this plugin.
	 *
	 * Used to enqueue the admin assets only where they are needed and to add the shell body class.
	 *
	 * @since 1.4.0.0
	 */
	public static function is_plugin_screen(): bool {
		/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation argument. */
		$page = isset( $_GET['page'] ) ? strtolower( sanitize_key( (string) wp_unslash( $_GET['page'] ) ) ) : '';
		if ( strpos( $page, self::TOP_LEVEL ) === 0 ) {
			return true;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && strpos( (string) $screen->id, self::TOP_LEVEL ) !== false ) {
			return true;
		}
		return false;
	}

	/**
	 * Reset the registry (tests only).
	 *
	 * @since 1.4.0.0
	 */
	public static function reset(): void {
		self::$screens    = array();
		self::$groups     = array();
		self::$registered = false;
		self::$menu_hook  = '';
	}
}
