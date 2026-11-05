<?php
/**
 * Connection settings. Saved secrets are never rendered into form values.
 *
 * @package ChurchTools_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once CHURCHTOOLS_SUITE_PATH . 'includes/functions/connection-settings.php';

if ( isset( $_POST['cts_save_api'] ) && check_admin_referer( 'cts_settings' ) ) {
	if ( ! current_user_can( 'configure_churchtools_suite' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'churchtools-suite' ) );
	}
	$values = churchtools_suite_connection_form_values( $_POST );
	if ( is_wp_error( $values ) ) {
		echo '<div class="cts-callout is-danger" role="alert">' . esc_html( $values->get_error_message() ) . '</div>';
	} else {
		if ( untrailingslashit( (string) get_option( 'churchtools_suite_ct_url', '' ) ) !== $values['url'] ) {
			delete_option( 'churchtools_suite_ct_password' );
			delete_option( 'churchtools_suite_ct_token' );
		}
		update_option( 'churchtools_suite_ct_url', $values['url'] );
		update_option( 'churchtools_suite_ct_auth_method', $values['auth_method'] );
		update_option( 'churchtools_suite_ct_username', $values['username'] );
		if ( '' !== $values['password'] ) {
			update_option( 'churchtools_suite_ct_password', $values['password'] );
		}
		if ( '' !== $values['token'] ) {
			update_option( 'churchtools_suite_ct_token', ChurchTools_Suite_Secret_Storage::encrypt( $values['token'] ) );
		}
		delete_option( 'churchtools_suite_ct_cookies' );
		delete_option( 'churchtools_suite_ct_user_info' );
		echo '<div class="cts-callout is-success" role="status">' . esc_html__( 'API-Einstellungen gespeichert.', 'churchtools-suite' ) . '</div>';
	}
}
$ct_url         = (string) get_option( 'churchtools_suite_ct_url', '' );
$ct_host        = (string) wp_parse_url( $ct_url, PHP_URL_HOST );
$ct_tenant      = preg_replace( '/\.church\.tools$/', '', $ct_host );
$ct_auth_method = 'token' === get_option( 'churchtools_suite_ct_auth_method', 'password' ) ? 'token' : 'password';
$ct_username    = (string) get_option( 'churchtools_suite_ct_username', '' );
$has_password   = '' !== (string) get_option( 'churchtools_suite_ct_password', '' );
$has_token      = '' !== (string) get_option( 'churchtools_suite_ct_token', '' );
?>
<form method="post" class="cts-form cts-connection-form" id="cts-connection-form"
	data-saved-tenant="<?php echo esc_attr( $ct_tenant ); ?>" data-saved-username="<?php echo esc_attr( $ct_username ); ?>">
	<?php wp_nonce_field( 'cts_settings' ); ?>
	<section class="cts-card" aria-labelledby="cts-connection-heading">
		<div class="cts-card-header">
			<span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
			<h2 id="cts-connection-heading"><?php esc_html_e( 'ChurchTools API-Verbindung', 'churchtools-suite' ); ?></h2>
		</div>
		<div class="cts-card-body">
			<table class="cts-form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Anmeldemethode', 'churchtools-suite' ); ?></th>
					<td>
						<fieldset class="cts-auth-methods">
							<legend class="cts-sr-only"><?php esc_html_e( 'Anmeldemethode', 'churchtools-suite' ); ?></legend>
							<label><input type="radio" name="ct_auth_method" id="ct_auth_method_password" value="password" <?php checked( $ct_auth_method, 'password' ); ?>> <?php esc_html_e( 'Benutzername & Passwort', 'churchtools-suite' ); ?></label>
							<label><input type="radio" name="ct_auth_method" id="ct_auth_method_token" value="token" <?php checked( $ct_auth_method, 'token' ); ?>> <?php esc_html_e( 'API-Token', 'churchtools-suite' ); ?></label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ct_tenant"><?php esc_html_e( 'ChurchTools-Instanz', 'churchtools-suite' ); ?></label></th>
					<td>
						<div class="cts-tenant-input"><span aria-hidden="true">https://</span><input type="text" id="ct_tenant" name="ct_tenant" value="<?php echo esc_attr( $ct_tenant ); ?>" required pattern="[A-Za-z0-9][A-Za-z0-9\-]*" autocomplete="off" aria-describedby="cts-tenant-hint"><span aria-hidden="true">.church.tools</span></div>
						<p id="cts-tenant-hint" class="cts-form-description"><?php esc_html_e( 'Nur den Instanznamen eingeben, ohne https:// und .church.tools.', 'churchtools-suite' ); ?></p>
					</td>
				</tr>
				<tr class="cts-auth-password"<?php echo 'password' !== $ct_auth_method ? ' hidden' : ''; ?>>
					<th scope="row"><label for="ct_username"><?php esc_html_e( 'Benutzername', 'churchtools-suite' ); ?></label></th>
					<td><input type="email" id="ct_username" name="ct_username" value="<?php echo esc_attr( $ct_username ); ?>" autocomplete="username" aria-describedby="cts-username-hint"><p id="cts-username-hint" class="cts-form-description"><?php esc_html_e( 'E-Mail-Adresse des ChurchTools-Benutzers.', 'churchtools-suite' ); ?></p></td>
				</tr>
				<tr class="cts-auth-password"<?php echo 'password' !== $ct_auth_method ? ' hidden' : ''; ?>>
					<th scope="row"><label for="ct_password"><?php esc_html_e( 'Passwort', 'churchtools-suite' ); ?></label></th>
					<td><input type="password" id="ct_password" name="ct_password" value="" autocomplete="new-password" data-saved-secret="<?php echo $has_password ? '1' : '0'; ?>" aria-describedby="cts-password-hint"><p id="cts-password-hint" class="cts-form-description"><?php echo $has_password ? esc_html__( 'Passwort gespeichert. Leer lassen, um es für denselben Benutzer beizubehalten.', 'churchtools-suite' ) : esc_html__( 'Passwort des ChurchTools-Benutzers eingeben.', 'churchtools-suite' ); ?></p></td>
				</tr>
				<tr class="cts-auth-token"<?php echo 'token' !== $ct_auth_method ? ' hidden' : ''; ?>>
					<th scope="row"><label for="ct_token"><?php esc_html_e( 'API-Token', 'churchtools-suite' ); ?></label></th>
					<td>
						<input type="password" id="ct_token" name="ct_token" value="" autocomplete="new-password" data-saved-secret="<?php echo $has_token ? '1' : '0'; ?>" aria-describedby="cts-token-hint">
						<p id="cts-token-hint" class="cts-form-description"><?php echo $has_token ? esc_html__( 'Token gespeichert. Leer lassen, um es für dieselbe Instanz beizubehalten.', 'churchtools-suite' ) : esc_html__( 'Persönliches API-Token aus ChurchTools eingeben.', 'churchtools-suite' ); ?></p>
						<details class="cts-field-help"><summary><?php esc_html_e( 'Wo finde ich mein API-Token?', 'churchtools-suite' ); ?></summary><p><?php esc_html_e( 'In ChurchTools über das Profilbild „API-Token“ öffnen, ein Token mit Kalender-/Event-Rechten erstellen und hier einfügen.', 'churchtools-suite' ); ?></p></details>
					</td>
				</tr>
			</table>
		</div>
		<div class="cts-card-footer cts-form-actions">
			<button type="submit" name="cts_save_api" value="1" class="cts-button cts-button-primary"><?php esc_html_e( 'Speichern', 'churchtools-suite' ); ?></button>
			<button type="button" id="cts-test-connection" class="cts-button cts-button-secondary"><?php esc_html_e( 'Verbindung testen', 'churchtools-suite' ); ?></button>
		</div>
		<div id="cts-connection-result" class="cts-connection-result" role="status" aria-live="polite" hidden></div>
	</section>
</form>
