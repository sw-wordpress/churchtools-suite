<?php
/**
 * Shared sync module status table. Expects $registered_modules and $module_statuses.
 *
 * @package ChurchTools_Suite
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( empty( $registered_modules ) ) : ?>
<div class="notice notice-info inline" style="margin: 0;">
<p><?php esc_html_e( 'Aktuell sind keine Sync-Module registriert. Aktiviere ein Addon (z. B. Posts Sync), damit hier Modulstatus angezeigt wird.', 'churchtools-suite' ); ?></p>
</div>
<?php else : ?>
<table class="widefat striped">
<thead>
<tr>
<th><?php esc_html_e( 'Modul', 'churchtools-suite' ); ?></th>
<th><?php esc_html_e( 'Status', 'churchtools-suite' ); ?></th>
<th><?php esc_html_e( 'Letzter Source-Sync', 'churchtools-suite' ); ?></th>
<th><?php esc_html_e( 'Letzter Daten-Sync', 'churchtools-suite' ); ?></th>
<th><?php esc_html_e( 'Letztes Ergebnis', 'churchtools-suite' ); ?></th>
</tr>
</thead>
<tbody>
	<?php foreach ( $registered_modules as $module_id => $module_config ) : ?>
			<?php
						$module_status       = isset( $module_statuses[ $module_id ] ) && is_array( $module_statuses[ $module_id ] ) ? $module_statuses[ $module_id ] : array();
						$module_label        = isset( $module_config['label'] ) ? (string) $module_config['label'] : (string) $module_id;
						$state               = isset( $module_status['state'] ) ? (string) $module_status['state'] : 'idle';
						$state_label_map     = array(
							'idle'     => __( 'Bereit', 'churchtools-suite' ),
							'running'  => __( 'Läuft', 'churchtools-suite' ),
							'ok'       => __( 'OK', 'churchtools-suite' ),
							'error'    => __( 'Fehler', 'churchtools-suite' ),
							'disabled' => __( 'Deaktiviert', 'churchtools-suite' ),
						);
						$state_label         = isset( $state_label_map[ $state ] ) ? (string) $state_label_map[ $state ] : $state;
						$last_source_sync_at = isset( $module_status['last_source_sync_at'] ) ? (string) $module_status['last_source_sync_at'] : '';
						$last_data_sync_at   = isset( $module_status['last_data_sync_at'] ) ? (string) $module_status['last_data_sync_at'] : '';
						$last_result         = isset( $module_status['last_result'] ) && is_array( $module_status['last_result'] ) ? $module_status['last_result'] : array();
						$last_message        = isset( $last_result['message'] ) ? (string) $last_result['message'] : '';

						if ( '' !== $last_source_sync_at ) {
							$last_source_sync_at = get_date_from_gmt( $last_source_sync_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
						}
						if ( '' !== $last_data_sync_at ) {
							$last_data_sync_at = get_date_from_gmt( $last_data_sync_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
						}
						?>
<tr>
<td><strong><?php echo esc_html( $module_label ); ?></strong> <code><?php echo esc_html( (string) $module_id ); ?></code></td>
<td><?php echo esc_html( $state_label ); ?></td>
<td><?php echo esc_html( '' !== $last_source_sync_at ? $last_source_sync_at : '—' ); ?></td>
<td><?php echo esc_html( '' !== $last_data_sync_at ? $last_data_sync_at : '—' ); ?></td>
<td><?php echo esc_html( '' !== $last_message ? $last_message : '—' ); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
