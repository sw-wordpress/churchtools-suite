<?php
/**
 * Advanced plugin settings.
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
