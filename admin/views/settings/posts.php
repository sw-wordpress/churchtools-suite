<?php
/**
 * Settings Subtab: Posts
 *
 * @package ChurchTools_Suite
 * @since   1.1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The settings renderer belongs to the separately activated Posts Sync addon.
if ( ! has_action( 'cts_posts_settings_render' ) ) {
	?>
	<section class="cts-card cts-posts-unavailable">
		<div class="cts-card-header"><span class="dashicons dashicons-admin-post" aria-hidden="true"></span><h2><?php esc_html_e( 'Posts Sync', 'churchtools-suite' ); ?></h2></div>
		<div class="cts-card-body">
			<p><?php esc_html_e( 'Das Posts-Sync-Addon ist nicht aktiv. Nach der Installation und Aktivierung erscheinen hier die Einstellungen für Zieltyp, Filter und Synchronisation.', 'churchtools-suite' ); ?></p>
			<?php if ( current_user_can( 'activate_plugins' ) ) : ?>
				<a class="cts-btn" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Plugin-Verwaltung öffnen', 'churchtools-suite' ); ?></a>
			<?php endif; ?>
		</div>
	</section>
	<?php
	return;
}

if ( ( isset( $_POST['cts_save_posts'] ) || isset( $_POST['ct_posts_sync_groups_now'] ) ) && check_admin_referer( 'cts_settings' ) ) {
	do_action( 'cts_posts_settings_save', $_POST );

	if ( isset( $_POST['ct_posts_sync_groups_now'] ) ) {
		echo '<div class="cts-notice cts-notice-info"><p>' . esc_html__( 'Gruppen-Sync wurde ausgeführt.', 'churchtools-suite' ) . '</p></div>';
	} else {
		echo '<div class="cts-notice cts-notice-success"><p>' . esc_html__( 'Posts-Einstellungen gespeichert.', 'churchtools-suite' ) . '</p></div>';
	}
}
?>

<form method="post" action="">
	<?php wp_nonce_field( 'cts_settings' ); ?>

	<?php do_action( 'cts_posts_settings_render' ); ?>

	<div class="cts-form-actions">
<button type="submit" name="cts_save_posts" class="cts-button cts-button-primary">
<?php esc_html_e( 'Posts-Einstellungen speichern', 'churchtools-suite' ); ?>
</button>
	</div>
</form>
