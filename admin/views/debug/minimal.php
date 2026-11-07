<?php
/**
 * Debug Tab (Minimal)
 *
 * @package ChurchTools_Suite
 * @since   0.5.11.28
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$sync_history_table = $wpdb->prefix . 'cts_sync_history';
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifiers and generated placeholder lists; query values are prepared separately for WordPress 6.0 compatibility.
$sync_history = $wpdb->get_results( "SELECT * FROM {$sync_history_table} ORDER BY started_at DESC LIMIT 10" );
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
?>

<div class="cts-settings">


	<div class="cts-tab-content cts-debug">

<?php require __DIR__ . '/system.php'; ?>
<!-- Cron Jobs Übersicht (v0.10.1.9) -->
<div class="cts-card" style="margin-top: 20px;">
<div class="cts-card-header">
<span class="dashicons dashicons-clock cts-card-icon" aria-hidden="true"></span>
<h3><?php esc_html_e( 'Geplante Aufgaben (Cron Jobs)', 'churchtools-suite' ); ?></h3>
</div>
<div class="cts-card-body">
<?php
				// Load Cron Display Helper.
				require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-cron-display.php';

				$our_hooks = array(
					'churchtools_suite_auto_sync',
					'churchtools_suite_session_keepalive',
				);

				echo '<table class="cts-debug-table">';
				echo '<tr>';
				echo '<th scope="col">' . esc_html__( 'Aufgabe', 'churchtools-suite' ) . '</th>';
				echo '<th scope="col">' . esc_html__( 'Nächste Ausführung', 'churchtools-suite' ) . '</th>';
				echo '<th scope="col">' . esc_html__( 'Intervall', 'churchtools-suite' ) . '</th>';
				echo '</tr>';

				foreach ( $our_hooks as $hook ) {
					$next_run     = wp_next_scheduled( $hook );
					$expected     = 'churchtools_suite_auto_sync' === $hook ? $diagnostics['cron']['auto_sync'] : ( $diagnostics['connection']['configured'] && 'password' === $diagnostics['connection']['auth_method'] );
					$display_name = ChurchTools_Suite_Cron_Display::get_cron_display_name( $hook );
					$description  = ChurchTools_Suite_Cron_Display::get_cron_description( $hook );

					// Get cron schedule.
					$crons    = get_option( 'cron', array() );
					$schedule = '';
					foreach ( $crons as $timestamp => $cron ) {
						if ( isset( $cron[ $hook ] ) ) {
							$event    = reset( $cron[ $hook ] );
							$schedule = $event['schedule'] ?? 'einmalig';
							break;
						}
					}

					echo '<tr>';
					echo '<td>';
					echo '<strong>' . esc_html( $display_name ) . '</strong><br>';
					echo '<span style="color:var(--cts-muted); font-size:0.9em;">' . esc_html( $description ) . '</span><br>';
					echo '<code style="font-size:0.85em; color:var(--cts-muted);">' . esc_html( $hook ) . '</code>';
					echo '</td>';
					echo '<td>';
					if ( $next_run ) {
						// v0.10.2.2: WordPress-Zeitzone verwenden (nicht UTC!).
						$local_time = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next_run ), 'Y-m-d H:i:s' );
						echo esc_html( date_i18n( 'D, d. M Y H:i', strtotime( $local_time ) ) );
						echo '<br><span style="color:var(--cts-muted); font-size:0.85em;">in ' . esc_html( human_time_diff( $next_run ) ) . '</span>';
					} elseif ( ! $expected ) {
						echo '<span class="cts-chip">' . esc_html__( 'Nicht benötigt', 'churchtools-suite' ) . '</span>';
					} else {
						echo '<span class="cts-chip is-warn">' . esc_html__( 'Nicht geplant', 'churchtools-suite' ) . '</span>';
					}
					echo '</td>';
					echo '<td>' . esc_html( $schedule ) . '</td>';
					echo '</tr>';
				}

				echo '</table>';
				?>
</div>
</div>
<div class="cts-card" style="margin-top: 20px;">
<div class="cts-card-header">
<span class="dashicons dashicons-chart-bar cts-card-icon" aria-hidden="true"></span>
<h3><?php esc_html_e( 'Sync-Historie (letzte 10)', 'churchtools-suite' ); ?></h3>
</div>
<div class="cts-card-body">
<?php if ( empty( $sync_history ) ) : ?>
<p><?php esc_html_e( 'Keine Sync-Historie vorhanden', 'churchtools-suite' ); ?></p>
<?php else : ?>
<table class="cts-debug-table">
<tr>
<th scope="col"><?php esc_html_e( 'Zeitpunkt', 'churchtools-suite' ); ?></th>
<th scope="col"><?php esc_html_e( 'Status', 'churchtools-suite' ); ?></th>
<th scope="col"><?php esc_html_e( 'Kalender', 'churchtools-suite' ); ?></th>
<th scope="col"><?php esc_html_e( 'Events', 'churchtools-suite' ); ?></th>
<th scope="col"><?php esc_html_e( 'Dienste', 'churchtools-suite' ); ?></th>
<th scope="col"><?php esc_html_e( 'Dauer', 'churchtools-suite' ); ?></th>
</tr>
	<?php foreach ( $sync_history as $entry ) : ?>
<tr>
<td><?php echo esc_html( $entry->started_at ); ?></td>
<td><?php echo esc_html( $entry->status ); ?></td>
<td><?php echo esc_html( $entry->calendars_processed ); ?></td>
<td><?php echo esc_html( $entry->events_found ); ?></td>
<td><?php echo esc_html( $entry->services_imported ); ?></td>
<td><?php echo esc_html( $entry->duration_seconds ); ?>s</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
</div>

	</div>

</div>
	<!-- Rate Limiting (v0.7.0.2) -->
