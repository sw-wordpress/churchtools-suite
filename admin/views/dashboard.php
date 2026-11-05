<?php
/**
 * Dashboard Tab
 *
 * @package ChurchTools_Suite
 * @since   0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CHURCHTOOLS_SUITE_PATH . 'includes/functions/connection-state.php';

$connection    = churchtools_suite_connection_state();
$ct_url        = (string) get_option( 'churchtools_suite_ct_url', '' );
$ct_last_login = $connection['last_login'];
$is_configured = $connection['configured'];
$is_connected  = $connection['connected'];

// Statistiken (v1.0.8.0: Use Repository Factory for user_id isolation).
global $wpdb;
$prefix = $wpdb->prefix . CHURCHTOOLS_SUITE_DB_PREFIX;

// Use the repository factory for user-isolated statistics.
$use_factory = class_exists( 'ChurchTools_Suite_Repository_Factory' );
$user_id     = get_current_user_id();

// v0.9.0.3: Suppress DB errors if tables don't exist yet (first activation).
$wpdb->suppress_errors();

if ( $use_factory ) {
	// Use Repository Factory for isolated counts.
	require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-factory.php';
	$events_repo    = ChurchTools_Suite_Repository_Factory::get_events_repo( $user_id );
	$calendars_repo = ChurchTools_Suite_Repository_Factory::get_calendars_repo( $user_id );

	$events_count    = $events_repo->count();
	$calendars_count = count(
		array_filter(
			$calendars_repo->get_all(),
			function ( $cal ) {
				return ! empty( $cal->is_selected );
			}
		)
	);
} else {
	// Fallback: Direct database queries (backwards compatibility).
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
	$events_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}events" );
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
	$calendars_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}calendars WHERE is_selected = 1" );
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
}

$wpdb->show_errors();

// Check if tables exist (if queries returned NULL, tables might not exist).
$tables_missing = ( '' !== $wpdb->last_error );
?>

<div class="cts-dashboard">

	<?php if ( $tables_missing ) : ?>
<div class="notice notice-warning" style="padding: 15px; margin-bottom: 20px;">
<h3 style="margin-top: 0;"><span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php esc_html_e( 'Datenbank-Tabellen fehlen', 'churchtools-suite' ); ?></h3>
<p><?php esc_html_e( 'Die Plugin-Tabellen existieren noch nicht. Bitte laden Sie die Seite neu, damit die Datenbank korrekt initialisiert wird.', 'churchtools-suite' ); ?></p>
<p>
<button type="button" class="button button-primary" onclick="location.reload();">
		<?php esc_html_e( 'Seite neu laden', 'churchtools-suite' ); ?>
</button>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
<a href="?page=churchtools-suite&tab=advanced&subtab=reset-cleanup" class="button button-secondary">
			<?php esc_html_e( 'Datenbank neu aufbauen (Debug)', 'churchtools-suite' ); ?>
</a>
<?php endif; ?>
</p>
</div>
	<?php endif; ?>

	<?php
	// Show update notice if an update is available (cached by transient if desired).
	$update_info = null;
	if ( class_exists( 'ChurchTools_Suite_Auto_Updater' ) ) {
		$info = ChurchTools_Suite_Auto_Updater::get_latest_release_info();
		if ( ! is_wp_error( $info ) ) {
			$update_info = $info;
		}
	}

	if ( is_array( $update_info ) && ! empty( $update_info['is_update'] ) ) :
		// Auto-Update Konfiguration prüfen.
		$auto_update_enabled = get_option( 'churchtools_suite_auto_update_enabled', 0 );
		$auto_update_level   = get_option( 'churchtools_suite_auto_update_level', 'major_minor_patch' );

		// Versions-Typ ermitteln (Major.Minor.Patch.Build).
		$current_version = CHURCHTOOLS_SUITE_VERSION;
		$new_version     = ltrim( $update_info['tag_name'] ?? $update_info['latest_version'], 'v' );

		$current_parts = explode( '.', $current_version );
		$new_parts     = explode( '.', $new_version );

		// Fehlende Teile mit 0 auffüllen.
		$current_parts = array_pad( $current_parts, 4, '0' );
		$new_parts     = array_pad( $new_parts, 4, '0' );

		// Update-Typ bestimmen.
		$update_type  = 'build'; // Default.
		$update_icon  = '';
		$update_color = '#0ea5e9'; // Cyan.
		$update_label = __( 'Build Update', 'churchtools-suite' );
		$update_desc  = __( 'Kleinste Änderungen, Bugfixes oder technische Verbesserungen', 'churchtools-suite' );

		if ( (int) $new_parts[0] > (int) $current_parts[0] ) {
			$update_type  = 'major';
			$update_icon  = '';
			$update_color = '#dc2626'; // Red.
			$update_label = __( 'Major Update', 'churchtools-suite' );
			$update_desc  = __( 'Große neue Features, möglicherweise Breaking Changes', 'churchtools-suite' );
		} elseif ( (int) $new_parts[1] > (int) $current_parts[1] ) {
			$update_type  = 'minor';
			$update_icon  = '';
			$update_color = '#f59e0b'; // Orange.
			$update_label = __( 'Minor Update', 'churchtools-suite' );
			$update_desc  = __( 'Neue Features, keine Breaking Changes', 'churchtools-suite' );
		} elseif ( (int) $new_parts[2] > (int) $current_parts[2] ) {
			$update_type  = 'patch';
			$update_icon  = '';
			$update_color = '#10b981'; // Green.
			$update_label = __( 'Patch Update', 'churchtools-suite' );
			$update_desc  = __( 'Bugfixes und kleinere Verbesserungen', 'churchtools-suite' );
		}

		// Prüfen ob Update-Typ gemäß Konfiguration erlaubt ist (v0.10.3.3).
		$update_allowed = false;
		switch ( $auto_update_level ) {
			case 'major':
				$update_allowed = ( 'major' === $update_type );
				break;
			case 'major_minor':
				$update_allowed = in_array( $update_type, array( 'major', 'minor' ), true );
				break;
			case 'major_minor_patch':
				$update_allowed = in_array( $update_type, array( 'major', 'minor', 'patch' ), true );
				break;
			case 'all':
				$update_allowed = true; // Alle Updates erlaubt.
				break;
		}
		?>
	<div class="cts-card" style="border-left:4px solid <?php echo esc_attr( $update_color ); ?>; margin-top:16px;">
<div class="cts-card-header">
<button id="cts-sync-now" class="cts-button cts-button-primary" style="font-size: 16px; padding: 12px 24px;">
<h3><?php echo esc_html( $update_label ); ?> <?php esc_html_e( 'verfügbar', 'churchtools-suite' ); ?></h3>
</div>
<div class="cts-card-body">
<p style="margin:0 0 8px;">
<strong><?php echo esc_html( $current_version ); ?></strong>
<span style="color:var(--cts-muted);"></span>
<strong style="color:<?php echo esc_attr( $update_color ); ?>;"><?php echo esc_html( $new_version ); ?></strong>
</p>
<p style="margin:0 0 8px; font-size:13px; color:var(--cts-muted);">
		<?php echo esc_html( $update_desc ); ?>
</p>
		<?php if ( ! empty( $update_info['html_url'] ) ) : ?>
<p style="margin:0 0 8px; font-size:13px;"><a href="<?php echo esc_url( $update_info['html_url'] ); ?>" target="_blank"><span class="dashicons dashicons-list-view" aria-hidden="true"></span> <?php esc_html_e( 'Release Notes anzeigen', 'churchtools-suite' ); ?></a></p>
<?php endif; ?>
		<?php if ( ! $auto_update_enabled ) : ?>
<p style="margin:8px 0 0; padding:8px 12px; background:#fff3cd; border-radius:4px; font-size:13px; color:#856404;">
<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<?php
					printf(
						/* translators: 1: Opening settings link, 2: Closing link tag. */                        esc_html__( 'Auto-Update ist deaktiviert. Manuelle Installation bleibt möglich. %1$sJetzt aktivieren%2$s', 'churchtools-suite' ),
						'<a href="?page=churchtools-suite&tab=settings&subtab=advanced">',
						'</a>'
					);
			?>
</p>
<p class="cts-card-subtitle"><?php esc_html_e( 'Integration Suite Status:', 'churchtools-suite' ); ?></p>
<p style="margin:8px 0 0; padding:8px 12px; background:#e0f2fe; border-radius:4px; font-size:13px; color:#0c4a6e;">
					<span class="dashicons dashicons-info" aria-hidden="true"></span>
			<?php
					printf(
						/* translators: 1: Update level, 2: Opening settings link, 3: Closing link tag. */                        esc_html__( 'Dieses Update wird nicht automatisch installiert (Stufe: %1$s). Manuelle Installation ist weiterhin möglich. %2$sEinstellungen ändern%3$s', 'churchtools-suite' ),
						'<strong>' . esc_html( $auto_update_level ) . '</strong>',
						'<a href="?page=churchtools-suite&tab=settings&subtab=advanced">',
						'</a>'
					);
			?>
</p>
<?php endif; ?>
</div>
<div class="cts-card-footer">
<button id="cts_install_update_btn" class="cts-button cts-button-danger">
		<?php echo esc_html( $update_icon ); ?> <?php esc_html_e( 'Jetzt installieren', 'churchtools-suite' ); ?>
</button>
<a href="?page=churchtools-suite&tab=settings" class="cts-button" style="margin-left:8px;"><?php esc_html_e( 'Einstellungen', 'churchtools-suite' ); ?></a>
</div>
	</div>
	<?php endif; ?>
	<!-- Status Cards -->
	<div class="cts-grid cts-grid-3">
<!-- ChurchTools Verbindung -->
<div class="cts-card">
<div class="cts-card-header">
<span class="dashicons dashicons-cloud cts-card-icon" aria-hidden="true"></span>
<h3><?php esc_html_e( 'ChurchTools', 'churchtools-suite' ); ?></h3>
</div>
<div class="cts-card-body">
<?php if ( $is_connected ) : ?>
<p class="cts-status cts-status-success">
<span class="cts-status-indicator"></span>
		<?php esc_html_e( 'Verbunden', 'churchtools-suite' ); ?>
</p>
<p class="cts-card-detail"><?php echo esc_html( wp_parse_url( $ct_url, PHP_URL_HOST ) ?: $ct_url ); ?></p>
		<?php if ( $ct_last_login ) : ?>
<p class="cts-card-meta"><?php echo esc_html( sprintf( /* translators: %s: Date and time of the last login. */ __( 'Letzter Login: %s', 'churchtools-suite' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $ct_last_login ) ) ) ); ?></p>
<?php endif; ?>
<?php elseif ( $is_configured ) : ?>
<p class="cts-status cts-status-inactive">
<span class="cts-status-indicator"></span>
	<?php esc_html_e( 'Konfiguriert', 'churchtools-suite' ); ?>
</p>
<p class="cts-card-detail"><?php echo esc_html( wp_parse_url( $ct_url, PHP_URL_HOST ) ?: $ct_url ); ?></p>
<p class="cts-card-meta"><?php esc_html_e( 'Verbindung noch nicht getestet', 'churchtools-suite' ); ?></p>
<?php else : ?>
<p class="cts-status cts-status-error">
<span class="cts-status-indicator"></span>
	<?php esc_html_e( 'Nicht konfiguriert', 'churchtools-suite' ); ?>
</p>
<p class="cts-card-detail"><?php esc_html_e( 'Bitte ChurchTools-Zugangsdaten eingeben', 'churchtools-suite' ); ?></p>
<?php endif; ?>
</div>
<div class="cts-card-footer">
<a href="?page=churchtools-suite&tab=settings" class="cts-button cts-button-secondary">
<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> <?php esc_html_e( 'Einstellungen', 'churchtools-suite' ); ?>
</a>
</div>
</div>

<!-- Automatischer Sync -->
<?php
		$auto_sync_enabled    = get_option( 'churchtools_suite_auto_sync_enabled', 0 );
		$last_sync_status     = get_option( 'churchtools_suite_last_sync_status', '' );
		$last_sync_error      = get_option( 'churchtools_suite_last_sync_error', '' );
		$last_sync_error_time = get_option( 'churchtools_suite_last_sync_error_time', '' );
		$last_sync_stats      = get_option( 'churchtools_suite_last_sync_stats', array() );
		$auto_sync_interval   = get_option( 'churchtools_suite_auto_sync_interval', 'daily' );

		// Intervall-Namen (für Referenz, aktuell nicht verwendet da Cron-Display verwendet wird).
		$interval_names = array(
			'hourly'      => __( 'Stündlich', 'churchtools-suite' ),
			'twicedaily'  => __( 'Zweimal täglich', 'churchtools-suite' ),
			'daily'       => __( 'Täglich', 'churchtools-suite' ),
			'cts_2days'   => __( 'Alle 2 Tage', 'churchtools-suite' ),
			'cts_3days'   => __( 'Alle 3 Tage', 'churchtools-suite' ),
			'cts_weekly'  => __( 'Wöchentlich', 'churchtools-suite' ),
			'cts_2weeks'  => __( 'Alle 2 Wochen', 'churchtools-suite' ),
			'cts_monthly' => __( 'Monatlich', 'churchtools-suite' ),
		);
		?>
<div class="cts-card">
<div class="cts-card-header">
<span class="dashicons dashicons-clock cts-card-icon" aria-hidden="true"></span>
<h3><?php esc_html_e( 'Cronjobs', 'churchtools-suite' ); ?></h3>
</div>
<div class="cts-card-body">
<?php
					// Cronjobs visuell darstellen.
					$cron           = _get_cron_array();
					$relevant_hooks = array();
if ( is_array( $cron ) ) {
	foreach ( $cron as $ts => $hooks ) {
		foreach ( $hooks as $hook => $events ) {
			if ( preg_match( '/churchtools|cts_|puc_/i', $hook ) ) {
				if ( ! isset( $relevant_hooks[ $hook ] ) ) {
					$relevant_hooks[ $hook ] = array();
				}
				$relevant_hooks[ $hook ][] = (int) $ts;
			}
		}
	}
}

					$known_hooks = array(
						'churchtools_suite_auto_sync',
						'churchtools_suite_session_keepalive',
						'churchtools_suite_calendar_reconcile',
						'churchtools_suite_check_updates',
					);

					foreach ( $known_hooks as $known_hook ) {
						if ( ! isset( $relevant_hooks[ $known_hook ] ) ) {
							$relevant_hooks[ $known_hook ] = array();
						}
					}

					if ( empty( $relevant_hooks ) ) :
						?>
<p class="cts-card-meta"><?php esc_html_e( 'Keine automatischen Cron-Jobs für dieses Plugin gefunden.', 'churchtools-suite' ); ?></p>
<?php else : ?>
<div class="cts-cronjob-list">
	<?php
	foreach ( $relevant_hooks as $hook_name => $timestamps ) :
		if ( ! empty( $timestamps ) ) {
			sort( $timestamps );
		}
		$next    = ! empty( $timestamps ) ? (int) $timestamps[0] : 0;
		$count   = count( $timestamps );
		$overdue = $next > 0 && $next < time();

		// Use ChurchTools_Suite_Cron_Display helper for consistent labels.
		$label = class_exists( 'ChurchTools_Suite_Cron_Display' )
		? ChurchTools_Suite_Cron_Display::get_cron_display_name( $hook_name )
		: $hook_name;
		$desc  = class_exists( 'ChurchTools_Suite_Cron_Display' )
		? ChurchTools_Suite_Cron_Display::get_cron_description( $hook_name )
		: '';
		?>
<div class="cts-cronjob-card">
<div class="cts-cronjob-title">
<span class="dashicons dashicons-clock" aria-hidden="true"></span> <?php echo esc_html( $label ); ?>
</div>
<div class="cts-cronjob-description">
			<?php echo esc_html( $desc ); ?>
</div>
<div style="font-size:13px; margin-bottom:6px;">
<strong><?php esc_html_e( 'Nächste Ausführung:', 'churchtools-suite' ); ?></strong> <span class="cts-cronjob-next<?php echo $overdue ? ' is-overdue' : ''; ?>">
			<?php if ( $next > 0 ) : ?>
				<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) ); ?>
				<?php
				if ( $overdue ) :
					?>
										(<?php printf( /* translators: %s: Human-readable time since the scheduled run. */ esc_html__( 'überfällig seit %s', 'churchtools-suite' ), esc_html( human_time_diff( $next, time() ) ) ); ?>)<?php endif; ?>
<?php else : ?>
	<?php esc_html_e( 'Nicht geplant', 'churchtools-suite' ); ?>
<?php endif; ?>
</span>
</div>
<div class="cts-cronjob-count">
			<?php echo esc_html( sprintf( /* translators: %d: Number of scheduled runs. */ _n( '%d Termin geplant', '%d Termine geplant', $count, 'churchtools-suite' ), $count ) ); ?>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<p class="cts-card-meta" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #f0f0f1;">
<small>
<?php
						// Prüfen ob manueller Trigger im Debug-Tab verfügbar ist.
						$debug_tab_has_trigger = true; // Immer vorhanden ab v0.9.2.x.
if ( $debug_tab_has_trigger ) {
	printf(
		/* translators: 1: Opening debug link, 2: Closing link tag. */        esc_html__( 'Manueller Trigger im %1$sDebug-Tab%2$s verfügbar', 'churchtools-suite' ),
		'<a href="?page=churchtools-suite&tab=advanced">',
		'</a>'
	);
} else {
	esc_html_e( 'Kein manueller Trigger verfügbar.', 'churchtools-suite' );
}
?>
</small>
</p>
</div>
<div class="cts-card-footer">
<a href="?page=churchtools-suite&tab=settings#auto-sync" class="cts-button cts-button-secondary">
<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> <?php esc_html_e( 'Konfigurieren', 'churchtools-suite' ); ?>
</a>
</div>
</div>

<!-- Synchronisation -->
<div class="cts-card">
<div class="cts-card-header">
<span class="dashicons dashicons-calendar-alt cts-card-icon" aria-hidden="true"></span>
<h3><?php esc_html_e( 'Synchronisation', 'churchtools-suite' ); ?></h3>
</div>
<div class="cts-card-body">
<p class="cts-stat-number"><?php echo esc_html( $events_count ); ?></p>
<p class="cts-card-detail">
<?php
					printf(
						/* translators: %s: Number of selected calendars. */                        esc_html__( 'Termine gesamt, %s Kalender ausgewählt', 'churchtools-suite' ),
						esc_html( (string) ( $calendars_count ) )
					);
					?>
</p>
</div>
<div class="cts-card-footer">
<a href="<?php echo esc_url( admin_url( 'admin.php?page=churchtools-suite-data&subtab=events' ) ); ?>" class="cts-button cts-button-secondary">
<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span> <?php esc_html_e( 'Termine anzeigen', 'churchtools-suite' ); ?>
</a>
</div>
</div>

	</div>

	<!-- System Info -->
	<div class="cts-card cts-system-card">
<div class="cts-card-header">
<span class="dashicons dashicons-info cts-card-icon" aria-hidden="true"></span>
<h3><?php esc_html_e( 'System', 'churchtools-suite' ); ?></h3>
</div>
<div class="cts-card-body">
<table class="cts-system-table">
<tr>
<th scope="row"><?php esc_html_e( 'Plugin-Version', 'churchtools-suite' ); ?></th>
<td><strong><?php echo esc_html( CHURCHTOOLS_SUITE_VERSION ); ?></strong></td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'WordPress-Version', 'churchtools-suite' ); ?></th>
<td><strong><?php echo esc_html( get_bloginfo( 'version' ) ); ?></strong></td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'PHP-Version', 'churchtools-suite' ); ?></th>
<td><strong><?php echo esc_html( PHP_VERSION ); ?></strong></td>
</tr>
</table>
</div>
<div class="cts-card-footer">
<a href="<?php echo esc_url( admin_url( 'admin.php?page=churchtools-suite&tab=advanced' ) ); ?>" class="cts-button cts-button-secondary">
<span class="dashicons dashicons-tools" aria-hidden="true"></span> <?php esc_html_e( 'Diagnose', 'churchtools-suite' ); ?>
</a>
</div>
	</div>

	<!-- WP-Cron Warnung -->
	<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
	<div class="cts-callout is-warning" style="display:block; margin-top:20px;">
<h4 style="margin: 0 0 10px; color: inherit; font-size: 15px;">
<span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php esc_html_e( 'WP-Cron ist deaktiviert', 'churchtools-suite' ); ?>
</h4>
<p style="margin: 0 0 10px; color: inherit; font-size: 13px; line-height: 1.6;">
		<?php esc_html_e( 'Die automatische Synchronisation ist nicht aktiv, da WP-Cron in Ihrer Konfiguration deaktiviert wurde. Bitte richten Sie einen System-Cron ein oder aktivieren Sie WP-Cron.', 'churchtools-suite' ); ?>
</p>
<a href="?page=churchtools-suite&tab=settings#auto-sync" class="cts-button cts-button-secondary" style="margin-top: 8px;">
		<?php esc_html_e( 'System-Cron Anleitung anzeigen', 'churchtools-suite' ); ?>
</a>
	</div>
	<?php endif; ?>

	<!-- Quick Start -->
	<?php if ( ! $is_configured ) : ?>
	<div class="cts-card cts-quick-start">
<h3><?php esc_html_e( 'Quick Start', 'churchtools-suite' ); ?></h3>
<ol>
<li><?php printf( /* translators: 1: Opening settings link, 2: Closing link tag. */ esc_html__( 'ChurchTools-URL und Zugangsdaten (API-Token oder Benutzername/Passwort) in den %1$sEinstellungen%2$s hinterlegen', 'churchtools-suite' ), '<a href="?page=churchtools-suite&tab=settings">', '</a>' ); ?></li>
<li><?php esc_html_e( 'Kalender auswählen und synchronisieren', 'churchtools-suite' ); ?></li>
<li><?php esc_html_e( 'Events per Shortcode im Frontend anzeigen', 'churchtools-suite' ); ?></li>
</ol>
	</div>
	<?php endif; ?>

</div>



<script>
(function(){
	'use strict';
	var installBtn = document.getElementById('cts_install_update_btn');
	if (!installBtn) return;
	installBtn.addEventListener('click', function(){
		if (!confirm('<?php echo esc_js( __( 'Update jetzt installieren? Dies überschreibt Plugin-Dateien.', 'churchtools-suite' ) ); ?>')) return;
		installBtn.disabled = true;
		var orig = installBtn.innerHTML;
		installBtn.innerHTML = '<?php echo esc_js( __( 'Installiere...', 'churchtools-suite' ) ); ?>';

		fetch( churchtoolsSuite.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({ action: 'cts_run_update', nonce: churchtoolsSuite.nonce })
	}).then(function(r) {
		if (!r.ok) throw new Error('Server-Fehler: ' + r.status);
		const contentType = r.headers.get('content-type');
		if (!contentType || !contentType.includes('application/json')) {
			return r.text().then(text => {
				console.error('Non-JSON Response:', text.substring(0, 500));
				throw new Error('Server hat keine gültige JSON-Antwort gesendet');
			});
		}
		return r.json();
	}).then(function(data){
		if (data.success) {
			// Dashboard neu laden nach erfolgreichem Update (v0.10.3.2)
			window.location.reload();
		} else {
			alert( data.data && data.data.message ? data.data.message : (data.message || '<?php echo esc_js( __( 'Fehler beim Update', 'churchtools-suite' ) ); ?>') );
			installBtn.disabled = false;
			installBtn.innerHTML = orig;
		}
	}).catch(function(err){
		alert('Netzwerkfehler:' + err.message);
		installBtn.disabled = false;
		installBtn.innerHTML = orig;
	});
	});
})();
</script>
