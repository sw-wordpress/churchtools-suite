<?php
/**
 * Advanced settings and manual, verified GitHub update status.
 *
 * @package ChurchTools_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( isset( $_POST['cts_save_advanced'] ) && check_admin_referer( 'cts_settings' ) ) {
	if ( ! current_user_can( 'configure_churchtools_suite' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'churchtools-suite' ) );
	}
	update_option( 'churchtools_suite_advanced_mode', isset( $_POST['advanced_mode'] ) ? 1 : 0 );
	echo '<div class="cts-notice cts-notice-success"><p>' . esc_html__( 'Erweiterte Einstellungen gespeichert.', 'churchtools-suite' ) . '</p></div>';
}
$advanced_mode = get_option( 'churchtools_suite_advanced_mode', 0 );
?>
<form method="post" class="cts-form">
	<?php wp_nonce_field( 'cts_settings' ); ?>
	<section class="cts-card">
		<div class="cts-card-header"><h2><?php esc_html_e( 'Erweiterte Optionen', 'churchtools-suite' ); ?></h2></div>
		<div class="cts-card-body">
			<table class="cts-form-table"><tr><th scope="row"><label for="advanced_mode"><?php esc_html_e( 'Erweiterter Modus', 'churchtools-suite' ); ?></label></th><td>
				<span class="cts-toggle"><input type="checkbox" id="advanced_mode" name="advanced_mode" value="1" <?php checked( $advanced_mode, 1 ); ?>><span class="cts-toggle-slider"></span></span>
				<p class="cts-form-description"><?php esc_html_e( 'Zeigt Diagnose, Logs und Wartungsaktionen an.', 'churchtools-suite' ); ?></p>
			</td></tr></table>
		</div>
		<div class="cts-card-footer"><button type="submit" name="cts_save_advanced" value="1" class="cts-button cts-button-primary"><?php esc_html_e( 'Speichern', 'churchtools-suite' ); ?></button></div>
	</section>
</form>
<?php if ( current_user_can( 'update_plugins' ) ) : ?>
	<?php
	require_once CHURCHTOOLS_SUITE_PATH . 'includes/update/class-churchtools-suite-update-service.php';
	$update_state = ChurchTools_Suite_Update_Store::read();
	$update_ready = (bool) ChurchTools_Suite_Update_Service::keys() && function_exists( 'sodium_crypto_sign_verify_detached' );
	?>
	<section class="cts-card">
		<div class="cts-card-header"><h2><?php esc_html_e( 'GitHub-Updates', 'churchtools-suite' ); ?></h2></div>
		<div class="cts-card-body">
			<p><?php esc_html_e( 'Prüfung nur im Backend, höchstens einmal je 24 Stunden oder ausdrücklich manuell. Installation ausschließlich über die WordPress-Plugin-Verwaltung. Signatur und SHA-256 werden vor dem Entpacken geprüft.', 'churchtools-suite' ); ?></p>
			<?php if ( ! $update_ready ) : ?>
				<div class="cts-callout is-warning"><p><?php esc_html_e( 'GitHub-Updates sind gesperrt: Ein vertrauenswürdiger öffentlicher Release-Schlüssel und Sodium müssen eingerichtet sein.', 'churchtools-suite' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Letzte erfolgreiche Prüfung:', 'churchtools-suite' ); ?> <?php echo ! empty( $update_state['last_success'] ) ? esc_html( wp_date( 'd.m.Y H:i', $update_state['last_success'] ) ) : esc_html__( 'Noch keine', 'churchtools-suite' ); ?></p>
			<?php
			if ( ! empty( $update_state['error'] ) ) :
				?>
				<p role="status"><?php echo esc_html( $update_state['error'] ); ?></p><?php endif; ?>
			<div id="cts-verified-updates-result" class="cts-info" role="status" aria-live="polite" hidden></div>
		</div>
		<div class="cts-card-footer cts-form-actions">
			<button type="button" id="cts-verified-updates-check" class="cts-btn" <?php disabled( ! $update_ready ); ?>><?php esc_html_e( 'Jetzt auf Updates prüfen', 'churchtools-suite' ); ?></button>
			<a class="cts-btn" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Plugin-Verwaltung öffnen', 'churchtools-suite' ); ?></a>
		</div>
	</section>
<?php endif; ?>
