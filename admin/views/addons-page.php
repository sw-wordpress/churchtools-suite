<?php
/**
 * Addons Overview Page
 *
 * Displays all installed addon plugins.
 *
 * @package ChurchTools_Suite
 * @since   1.0.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure plugin.php functions are available.
if ( ! function_exists( 'get_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

require_once CHURCHTOOLS_SUITE_PATH . 'includes/functions/addon-admin.php';

$addons        = cts_ensure_known_addons_in_table( cts_get_addon_plugins() );
$has_addons    = ! empty( $addons );
$active_addons = array_filter( $addons, fn( $addon ) => $addon['is_active'] );

// Check if Elementor is active but sub-plugin is not.
$elementor_active              = false;
$elementor_subplugin_active    = false;
$elementor_subplugin_installed = false;

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if ( ! function_exists( 'get_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$elementor_active           = is_plugin_active( 'elementor/elementor.php' ) || did_action( 'elementor/loaded' );
$elementor_subplugin_active = is_plugin_active( 'churchtools-suite-elementor/churchtools-suite-elementor.php' );

// Check if Elementor sub-plugin is installed (but possibly inactive).
$all_plugins                   = get_plugins();
$elementor_subplugin_installed = isset( $all_plugins['churchtools-suite-elementor/churchtools-suite-elementor.php'] );

$posts_sync_plugin_file = 'churchtools-suite-posts-sync/churchtools-suite-posts-sync.php';
$posts_sync_installed   = isset( $all_plugins[ $posts_sync_plugin_file ] );

?>
<div class="cts-addons">

	<?php if ( $elementor_active && ! $elementor_subplugin_active ) : ?>
<div class="notice notice-info" style="margin: 20px 0;">
<p>
<strong><?php esc_html_e( 'Elementor Integration', 'churchtools-suite' ); ?></strong><br>
		<?php
		if ( $elementor_subplugin_installed ) {
			esc_html_e( 'Das Elementor-Integrations-Plugin ist installiert, aber nicht aktiv.', 'churchtools-suite' );
		} else {
			esc_html_e( 'Elementor wurde erkannt! Installiere das Elementor-Integrations-Plugin für erweiterte Funktionen.', 'churchtools-suite' );
		}
		?>
</p>
<p>
		<?php if ( $elementor_subplugin_installed ) : ?>
<a href="<?php echo esc_url( wp_nonce_url( 'plugins.php?action=activate&plugin=' . rawurlencode( 'churchtools-suite-elementor/churchtools-suite-elementor.php' ), 'activate-plugin_churchtools-suite-elementor/churchtools-suite-elementor.php' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'Aktivieren', 'churchtools-suite' ); ?>
</a>
<?php else : ?>
<button type="button" class="button button-primary cts-install-addon" data-addon-slug="churchtools-suite-elementor">
	<?php esc_html_e( 'Jetzt installieren', 'churchtools-suite' ); ?>
</button>
<?php endif; ?>
<a href="https://github.com/feg-aschaffenburg/churchtools-suite/releases" class="button" target="_blank" rel="noopener noreferrer">
		<?php esc_html_e( 'Auf GitHub ansehen', 'churchtools-suite' ); ?>
</a>
</p>
		<?php if ( ! $elementor_subplugin_installed ) : ?>
<div class="cts-install-result" style="display:none; margin-top:10px;"></div>
<?php endif; ?>
</div>
	<?php endif; ?>

	<?php if ( ! $posts_sync_installed ) : ?>
<div class="notice notice-info" style="margin: 20px 0;">
<p>
<strong><?php esc_html_e( 'Posts Sync Addon', 'churchtools-suite' ); ?></strong><br>
		<?php esc_html_e( 'Synchronisiere Posts automatisch nach WordPress. Das Addon muss separat installiert werden.', 'churchtools-suite' ); ?>
</p>
<p>
<button type="button" class="button button-primary cts-install-addon" data-addon-slug="churchtools-suite-posts-sync">
		<?php esc_html_e( 'Jetzt installieren', 'churchtools-suite' ); ?>
</button>
</p>
<div class="cts-install-result" style="display:none; margin-top:10px;"></div>
</div>
	<?php endif; ?>

	<?php if ( ! $has_addons ) : ?>
<div class="notice notice-info inline">
<p>
<strong><?php esc_html_e( 'Keine Addons installiert', 'churchtools-suite' ); ?></strong><br>
		<?php esc_html_e( 'Es sind derzeit keine Erweiterungen für die Integration Suite installiert.', 'churchtools-suite' ); ?>
</p>
</div>

	<?php else : ?>

<div class="cts-section">
<div class="cts-section-header">
	<p class="cts-section-description">
		<?php
					printf(
						/* translators: 1: Number of installed addons, 2: Number of active addons. */                        esc_html__( '%1$d Addon(s) installiert, davon %2$d aktiv', 'churchtools-suite' ),
						count( $addons ),
						count( $active_addons )
					);
		?>
</p>
<button type="button" class="button cts-check-addon-updates" style="margin-left: 10px;">
<span class="dashicons dashicons-update"></span>
		<?php esc_html_e( 'Nach Updates suchen', 'churchtools-suite' ); ?>
</button>
</div>

<table class="wp-list-table widefat fixed striped">
<thead>
<tr>
<th style="width: 3%;"><?php esc_html_e( 'Status', 'churchtools-suite' ); ?></th>
<th style="width: 25%;"><?php esc_html_e( 'Name', 'churchtools-suite' ); ?></th>
<th style="width: 35%;"><?php esc_html_e( 'Beschreibung', 'churchtools-suite' ); ?></th>
<th style="width: 10%;"><?php esc_html_e( 'Version', 'churchtools-suite' ); ?></th>
<th style="width: 12%;"><?php esc_html_e( 'Update', 'churchtools-suite' ); ?></th>
<th style="width: 15%;"><?php esc_html_e( 'Aktionen', 'churchtools-suite' ); ?></th>
</tr>
</thead>
<tbody>
		<?php foreach ( $addons as $plugin_file => $addon ) : ?>
			<?php
						$status_icon    = '';
						$is_coming_soon = in_array( (string) $addon['plugin_slug'], array( 'churchtools-suite-presentations' ), true );
						$is_virtual     = ! empty( $addon['is_virtual'] );
			if ( $is_coming_soon ) {
				$status_icon = '';
				$status_text = esc_html__( 'Kommt bald', 'churchtools-suite' );
			} else {
				$status_text = $addon['is_active']
					? esc_html__( 'Aktiv', 'churchtools-suite' )
					: esc_html__( 'Inaktiv', 'churchtools-suite' );
			}
						$row_class = $addon['is_active'] ? 'active' : 'inactive';

						// Check for updates.
						$update_info = false;
			if ( ! $is_virtual && ! $is_coming_soon && ! empty( $addon['github_repo'] ) && ! empty( $addon['Version'] ) && '—' !== $addon['Version'] ) {
				$update_info = cts_check_addon_update( $addon['github_repo'], $addon['Version'], $addon['plugin_slug'] );
			}

						// Generate action links.
						$actions = array();
			if ( $is_coming_soon ) {
				$actions[] = esc_html__( 'Kommt bald', 'churchtools-suite' );
			} elseif ( $is_virtual ) {
				// Not installed but available – install directly via AJAX.
				$actions[] = sprintf(
					'<button type="button" class="button-link cts-install-addon" data-addon-slug="%s">%s</button>',
					esc_attr( (string) $addon['plugin_slug'] ),
					esc_html__( 'Installieren', 'churchtools-suite' )
				);
			} elseif ( $addon['is_active'] ) {
				$deactivate_url = wp_nonce_url(
					admin_url( 'plugins.php?action=deactivate&plugin=' . rawurlencode( $plugin_file ) . '&plugin_status=all&paged=1' ),
					'deactivate-plugin_' . $plugin_file
				);
				$actions[]      = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $deactivate_url ),
					esc_html__( 'Deaktivieren', 'churchtools-suite' )
				);
			} else {
				$activate_url = wp_nonce_url(
					admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $plugin_file ) . '&plugin_status=all&paged=1' ),
					'activate-plugin_' . $plugin_file
				);
				$actions[]    = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $activate_url ),
					esc_html__( 'Aktivieren', 'churchtools-suite' )
				);
			}

						// Details link.
			if ( ! empty( $addon['details_url'] ) ) {
				$actions[] = sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $addon['details_url'] ),
					esc_html__( 'Details', 'churchtools-suite' )
				);
			}
			?>
<tr class="<?php echo esc_attr( $row_class ); ?>" data-plugin-file="<?php echo esc_attr( $plugin_file ); ?>" data-github-repo="<?php echo esc_attr( $addon['github_repo'] ?? '' ); ?>">
<td style="text-align: center; font-size: 1.2em;">
<span title="<?php echo esc_attr( $status_text ); ?>"><?php echo esc_html( $status_icon ); ?></span>
</td>
<td>
<strong><?php echo esc_html( $addon['Name'] ); ?></strong>
			<?php if ( $addon['is_network_active'] ) : ?>
<span class="cts-badge cts-badge-info">
				<?php esc_html_e( 'Netzwerk', 'churchtools-suite' ); ?>
</span>
<?php endif; ?>
</td>
<td>
			<?php echo wp_kses_post( $addon['Description'] ); ?>
</td>
<td>
<code><?php echo esc_html( $addon['Version'] ); ?></code>
</td>
<td class="cts-update-cell">
			<?php if ( $is_coming_soon ) : ?>
<span style="color: #996800;"><span class="dashicons dashicons-hourglass" aria-hidden="true"></span> <?php esc_html_e( 'kommt bald', 'churchtools-suite' ); ?></span>
<?php elseif ( $is_virtual ) : ?>
<span style="color: #6b7280;">—</span>
<?php elseif ( $update_info ) : ?>
<div class="cts-update-available">
<span class="dashicons dashicons-update" style="color: #d63638;"></span>
<strong style="color: #d63638;"><?php echo esc_html( $update_info['latest_version'] ); ?></strong>
<button type="button"
												class="button button-primary button-small cts-update-addon"
												data-plugin-file="<?php echo esc_attr( $plugin_file ); ?>"
												data-github-repo="<?php echo esc_attr( $addon['github_repo'] ); ?>"
												data-zip-url="<?php echo esc_attr( $update_info['zip_url'] ); ?>"
												data-version="<?php echo esc_attr( $update_info['latest_version'] ); ?>">
	<?php esc_html_e( 'Jetzt aktualisieren', 'churchtools-suite' ); ?>
</button>
</div>
<?php else : ?>
<span style="color: #2271b1;"><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e( 'Aktuell', 'churchtools-suite' ); ?></span>
<?php endif; ?>
</td>
<td>
			<?php echo wp_kses_post( implode( ' | ', $actions ) ); ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

	<?php endif; ?>

</div>

<script>
jQuery(document).ready(function($) {
	// Install addon handler
	$('.cts-install-addon').on('click', function(e) {
		e.preventDefault();

		const $btn = $(this);
		const addonSlug = $btn.data('addon-slug');
		let $resultDiv = $btn.closest('.cts-addon-card, .notice').find('.cts-install-result');
		if (!$resultDiv.length) {
			const $row = $btn.closest('tr');
			if ($row.length) {
				$resultDiv = $('<div class="cts-install-result notice" style="margin:8px 0;"></div>');
				$row.find('td').eq(2).append($resultDiv);
			}
		}

		// Disable button and show loading
		$btn.prop('disabled', true);
		const originalText = $btn.html();
		$btn.html('<span class="dashicons dashicons-update spin"></span> <?php esc_html_e( 'Installiere...', 'churchtools-suite' ); ?>');

		// Hide previous result
		$resultDiv.hide().removeClass('notice-success notice-error').empty();

		// AJAX request
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'cts_install_addon',
				nonce: '<?php echo esc_js( wp_create_nonce( 'churchtools_suite_admin' ) ); ?>',
				addon_slug: addonSlug
			},
			success: function(response) {
				if (response.success) {
					$resultDiv
						.addClass('notice notice-success')
						.html('<p>' + response.data.message + '</p>')
						.show();

					// Reload page after 2 seconds
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					$resultDiv
						.addClass('notice notice-error')
						.html('<p>' + (response.data.message || '<?php esc_html_e( 'Unbekannter Fehler', 'churchtools-suite' ); ?>') + '</p>')
						.show();

					// Re-enable button
					$btn.prop('disabled', false).html(originalText);
				}
			},
			error: function(xhr, status, error) {
				$resultDiv
					.addClass('notice notice-error')
					.html('<p><?php esc_html_e( 'Netzwerkfehler bei der Installation', 'churchtools-suite' ); ?>: ' + error + '</p>')
					.show();

				// Re-enable button
				$btn.prop('disabled', false).html(originalText);
			}
		});
	});

	// Check for updates handler
	$('.cts-check-addon-updates').on('click', function(e) {
		e.preventDefault();

		const $btn = $(this);
		const originalHtml = $btn.html();

		// Show loading
		$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php esc_html_e( 'Prüfe...', 'churchtools-suite' ); ?>');

		// Clear transient cache and reload
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'cts_clear_addon_update_cache',
				nonce: '<?php echo esc_js( wp_create_nonce( 'churchtools_suite_admin' ) ); ?>'
			},
			success: function(response) {
				if (response && response.success) {
					location.reload();
					return;
				}

				const msg = (response && response.data && response.data.message)
					? response.data.message
					: '<?php esc_html_e( 'Fehler beim Prüfen der Updates', 'churchtools-suite' ); ?>';
				$btn.prop('disabled', false).html(originalHtml);
				alert(msg);
			},
			error: function(xhr) {
				let msg = '<?php esc_html_e( 'Fehler beim Prüfen der Updates', 'churchtools-suite' ); ?>';
				if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					msg = xhr.responseJSON.data.message;
				}
				$btn.prop('disabled', false).html(originalHtml);
				alert(msg);
			}
		});
	});

	// Update addon handler
	$('.cts-update-addon').on('click', function(e) {
		e.preventDefault();

		const $btn = $(this);
		const pluginFile = $btn.data('plugin-file');
		const zipUrl = $btn.data('zip-url');
		const version = $btn.data('version');
		const $row = $btn.closest('tr');
		const $updateCell = $row.find('.cts-update-cell');

		// Confirm update
		if (!confirm('<?php esc_html_e( 'Möchten Sie dieses Addon wirklich aktualisieren?', 'churchtools-suite' ); ?>')) {
			return;
		}

		// Show loading in update cell
		const originalHtml = $updateCell.html();
		$updateCell.html('<span class="dashicons dashicons-update spin"></span> <?php esc_html_e( 'Aktualisiere...', 'churchtools-suite' ); ?>');
		$btn.prop('disabled', true);

		// AJAX request
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'cts_update_addon',
				nonce: '<?php echo esc_js( wp_create_nonce( 'churchtools_suite_admin' ) ); ?>',
				plugin_file: pluginFile,
				zip_url: zipUrl,
				version: version
			},
			success: function(response) {
				if (response.success) {
					$updateCell.html('<span style="color: #00a32a;"><span class="dashicons dashicons-yes" aria-hidden="true"></span> ' + response.data.message + '</span>');

					// Reload page after 2 seconds
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					$updateCell.html('<span style="color: #d63638;">' + (response.data.message || '<?php esc_html_e( 'Update fehlgeschlagen', 'churchtools-suite' ); ?>') + '</span>');
					$btn.prop('disabled', false);

					// Restore after 3 seconds
					setTimeout(function() {
						$updateCell.html(originalHtml);
					}, 3000);
				}
			},
			error: function(xhr, status, error) {
				$updateCell.html('<span style="color: #d63638;"><?php esc_html_e( 'Netzwerkfehler', 'churchtools-suite' ); ?></span>');
				$btn.prop('disabled', false);

				// Restore after 3 seconds
				setTimeout(function() {
					$updateCell.html(originalHtml);
				}, 3000);
			}
		});
	});
});
</script>

<style>
.cts-install-addon .dashicons.spin,
.cts-check-addon-updates .dashicons.spin,
.cts-update-cell .dashicons.spin {
	animation: rotation 1s infinite linear;
}

@keyframes rotation {
	from { transform: rotate(0deg); }
	to { transform: rotate(359deg); }
}

.cts-install-result {
	margin-top: 10px;
}

.cts-install-result.notice {
	padding: 10px;
	border-left: 4px solid #00a32a;
}

.cts-install-result.notice-error {
	border-left-color: #d63638;
}

.cts-install-result p {
	margin: 0;
}

.cts-update-available {
	display: flex;
	align-items: center;
	gap: 8px;
}

.cts-update-available .dashicons {
	font-size: 18px;
	width: 18px;
	height: 18px;
}

.cts-update-addon.button-small {
	padding: 2px 8px;
	font-size: 12px;
	height: auto;
	line-height: 1.5;
}

.cts-check-addon-updates {
	vertical-align: middle;
}

.cts-check-addon-updates .dashicons {
	font-size: 16px;
	width: 16px;
	height: 16px;
	vertical-align: text-top;
}
</style>
