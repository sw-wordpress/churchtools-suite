<?php
/**
 * Local diagnostics inspired by the operational overview in sw-cloudcalendar.
 *
 * @package ChurchTools_Suite
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once CHURCHTOOLS_SUITE_PATH . 'includes/functions/diagnostics.php';
$diagnostics = churchtools_suite_diagnostics();
$sections    = array(
	__( 'Versionen', 'churchtools-suite' )       => array(
		__( 'Plugin', 'churchtools-suite' )          => $diagnostics['versions']['plugin'],
		'WordPress'                                  => $diagnostics['versions']['wordpress'],
		'PHP'                                        => $diagnostics['versions']['php'],
		__( 'Datenbankschema', 'churchtools-suite' ) => $diagnostics['versions']['db_schema'],
	),
	__( 'Umgebung', 'churchtools-suite' )        => array(
		__( 'Zeitzone', 'churchtools-suite' )          => $diagnostics['environment']['timezone'],
		__( 'PHP-Speicherlimit', 'churchtools-suite' ) => '-1' === $diagnostics['environment']['memory_limit'] ? __( 'Unbegrenzt', 'churchtools-suite' ) : $diagnostics['environment']['memory_limit'],
		__( 'Persistenter Objektcache', 'churchtools-suite' ) => $diagnostics['environment']['persistent_object_cache'] ? __( 'Ja', 'churchtools-suite' ) : __( 'Nein', 'churchtools-suite' ),
	),
	__( 'Voraussetzungen', 'churchtools-suite' ) => array(
		'OpenSSL'  => $diagnostics['environment']['openssl'],
		'cURL'     => $diagnostics['environment']['curl'],
		'Fileinfo' => $diagnostics['environment']['fileinfo'],
		'GD'       => $diagnostics['environment']['gd'],
		'Imagick'  => $diagnostics['environment']['imagick'],
	),
	__( 'Verbindung', 'churchtools-suite' )      => array(
		__( 'Zugangsdaten konfiguriert', 'churchtools-suite' ) => $diagnostics['connection']['configured'] ? __( 'Ja', 'churchtools-suite' ) : __( 'Nein', 'churchtools-suite' ),
		__( 'Anmeldemethode', 'churchtools-suite' ) => 'token' === $diagnostics['connection']['auth_method'] ? __( 'API-Token', 'churchtools-suite' ) : __( 'Benutzername & Passwort', 'churchtools-suite' ),
		__( 'Sitzung / Token vorhanden', 'churchtools-suite' ) => $diagnostics['connection']['session_present'] ? __( 'Ja', 'churchtools-suite' ) : __( 'Nein', 'churchtools-suite' ),
	),
	__( 'Datenbestand', 'churchtools-suite' )    => array(
		__( 'Kalender', 'churchtools-suite' ) => $diagnostics['counts']['calendars'],
		__( 'Termine', 'churchtools-suite' )  => $diagnostics['counts']['events'],
		__( 'Dienste', 'churchtools-suite' )  => $diagnostics['counts']['services'],
	),
);
?>
<div class="cts-diagnostics-actions">
	<p><?php esc_html_e( 'Lokale Zustandsübersicht ohne API-Anfrage. Der Export enthält keine Zugangsdaten oder Termin-Inhalte.', 'churchtools-suite' ); ?></p>
	<button type="button" class="cts-btn" id="cts-diagnostics-export"><span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e( 'Diagnose exportieren', 'churchtools-suite' ); ?></button>
</div>
<script type="application/json" id="cts-diagnostics-data"><?php echo wp_json_encode( $diagnostics, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with HTML-safe flags. ?></script>
<div class="cts-diagnostics-grid">
	<?php foreach ( $sections as $heading => $rows ) : ?>
	<section class="cts-card">
		<div class="cts-card-header"><h3><?php echo esc_html( $heading ); ?></h3></div>
		<div class="cts-card-body"><dl class="cts-diagnostics-list">
			<?php foreach ( $rows as $label => $value ) : ?>
			<div><dt><?php echo esc_html( $label ); ?></dt><dd>
				<?php if ( is_bool( $value ) ) : ?>
					<span class="cts-chip <?php echo $value ? 'is-ok' : ''; ?>"><?php echo $value ? esc_html__( 'Verfügbar', 'churchtools-suite' ) : esc_html__( 'Nicht verfügbar', 'churchtools-suite' ); ?></span>
				<?php else : ?>
					<?php echo esc_html( (string) $value ); ?>
				<?php endif; ?>
			</dd></div>
			<?php endforeach; ?>
		</dl></div>
	</section>
	<?php endforeach; ?>
</div>
<p class="cts-field-hint"><?php esc_html_e( 'GD oder Imagick werden für die Bildverarbeitung benötigt. cURL ist optional, wenn WordPress einen anderen HTTP-Transport nutzen kann.', 'churchtools-suite' ); ?></p>
<?php if ( $diagnostics['cron']['wp_cron_disabled'] ) : ?>
	<div class="cts-callout is-warning"><p><?php esc_html_e( 'WP-Cron ist deaktiviert. Stelle sicher, dass ein externer Cronjob die WordPress-Aufgaben regelmäßig ausführt.', 'churchtools-suite' ); ?></p></div>
<?php endif; ?>
<?php if ( $diagnostics['cron']['auto_sync'] && null === $diagnostics['cron']['next_runs']['churchtools_suite_auto_sync'] ) : ?>
	<div class="cts-callout is-warning"><p><?php esc_html_e( 'Automatischer Sync ist aktiviert, aber es ist kein nächster Lauf geplant. Prüfe die Sync-Einstellungen.', 'churchtools-suite' ); ?></p></div>
<?php endif; ?>
