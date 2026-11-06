<?php
/**
 * Tab: Dienste
 *
 * @package ChurchTools_Suite
 * @since   0.3.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load repositories.
global $wpdb;
require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-service-groups-repository.php';
require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-services-repository.php';

$service_groups_repo = new ChurchTools_Suite_Service_Groups_Repository();
$services_repo       = new ChurchTools_Suite_Services_Repository();

$service_groups        = $service_groups_repo->get_all();
$selected_groups_count = $service_groups_repo->get_selected_count();
$last_groups_sync      = get_option( 'churchtools_suite_service_groups_last_sync', null );

$services                = $services_repo->get_all();
$selected_services_count = $services_repo->get_selected_count();
$last_services_sync      = get_option( 'churchtools_suite_services_last_sync', null );
?>

<!-- SCHRITT 1: Service Groups synchronisieren -->
<div class="cts-card">
	<div class="cts-card-header">
<span class="dashicons dashicons-update cts-card-icon" aria-hidden="true"></span>
<h3><?php esc_html_e( 'Schritt 1: Dienstgruppen synchronisieren', 'churchtools-suite' ); ?></h3>
	</div>
	<div class="cts-card-body">
<p>
<?php esc_html_e( 'Synchronisieren Sie zuerst die verfügbaren Dienstgruppen aus ChurchTools.', 'churchtools-suite' ); ?>
<br><em style="color:var(--cts-muted); font-size: 0.95em;"><?php esc_html_e( '3-Schritt-Prozess: 1) Dienstgruppen synchronisieren  2) Dienstgruppen auswählen  3) Dienste synchronisieren & auswählen', 'churchtools-suite' ); ?></em>
</p>

<?php if ( $last_groups_sync ) : ?>
<p class="cts-card-meta">
		<?php
				printf(
					/* translators: %s: Formatted date and time of the last synchronization. */                    esc_html__( 'Letzte Synchronisation: %s', 'churchtools-suite' ),
					esc_html( get_date_from_gmt( $last_groups_sync, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) )
				);
		?>
</p>
<?php endif; ?>

<div class="cts-button-group">
<button type="button" id="cts-sync-service-groups-btn" class="cts-button cts-button-primary">
<span class="dashicons dashicons-update"></span>
<?php esc_html_e( 'Dienstgruppen synchronisieren', 'churchtools-suite' ); ?>
</button>
</div>
	</div>
</div>

<div id="cts-sync-service-groups-result" class="cts-mt-15" style="display: none;"></div>

<!-- SCHRITT 2: Service Group Auswahl -->
<div class="cts-card cts-mt-20">
<div class="cts-card-header">
<span class="cts-card-icon"></span>
<h3><?php esc_html_e( 'Schritt 2: Dienstgruppen auswählen', 'churchtools-suite' ); ?></h3>
</div>
<div class="cts-card-body">

<?php if ( empty( $service_groups ) ) : ?>
<div class="notice notice-info inline">
<p>
		<?php esc_html_e( 'Keine Dienstgruppen vorhanden. Bitte synchronisieren Sie zuerst die Dienstgruppen von ChurchTools.', 'churchtools-suite' ); ?>
</p>
</div>
<?php else : ?>

<p class="description">
	<?php
					printf(
						/* translators: 1: Number of selected service groups, 2: Total service groups. */                        esc_html__( 'Wählen Sie die Dienstgruppen aus, deren Dienste Sie synchronisieren möchten. Aktuell ausgewählt: %1$d von %2$d', 'churchtools-suite' ),
						(int) $selected_groups_count,
						count( $service_groups )
					);
	?>
</p>

<!-- Bulk-Actions für Dienstgruppen -->
<div style="margin: 15px 0; display: flex; gap: 10px;">
<button type="button" id="cts-select-all-groups-btn" class="button">
<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Alle auswählen', 'churchtools-suite' ); ?>
</button>
<button type="button" id="cts-deselect-all-groups-btn" class="button">
<span class="dashicons dashicons-dismiss" aria-hidden="true"></span> <?php esc_html_e( 'Alle abwählen', 'churchtools-suite' ); ?>
</button>
</div>

<form method="post" id="cts-service-group-selection-form">
	<?php wp_nonce_field( 'cts_service_group_selection', 'cts_service_group_selection_nonce' ); ?>

<table class="widefat" style="margin-top: 15px;">
<thead>
<tr>
<th style="width: 40px;">
<input type="checkbox" id="cts-select-all-service-groups" aria-label="<?php esc_attr_e( 'Alle Dienstgruppen auswählen', 'churchtools-suite' ); ?>">
</th>
<th><?php esc_html_e( 'Dienstgruppe', 'churchtools-suite' ); ?></th>
<th><?php esc_html_e( 'ChurchTools-ID', 'churchtools-suite' ); ?></th>
<th><?php esc_html_e( 'Sortierung', 'churchtools-suite' ); ?></th>
</tr>
</thead>
<tbody>
	<?php foreach ( $service_groups as $group ) : ?>
<tr>
<td>
<input
											type="checkbox"
											name="selected_groups[]"
											value="<?php echo esc_attr( $group->service_group_id ); ?>"
											class="cts-service-group-checkbox" aria-label="<?php echo esc_attr( $group->name ); ?>"
			<?php checked( $group->is_selected, 1 ); ?>
										>
</td>
<td>
<strong><?php echo esc_html( $group->name ); ?></strong>
</td>
<td>
<code><?php echo esc_html( $group->service_group_id ); ?></code>
</td>
<td>
			<?php echo esc_html( $group->sort_order ); ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div style="margin-top: 20px;">
<button type="submit" class="button button-primary">
	<?php esc_html_e( 'Auswahl speichern', 'churchtools-suite' ); ?>
</button>
</div>
</form>

<?php endif; ?>

</div>
	</div>

	<div id="cts-service-group-selection-result" class="cts-mt-15" style="display: none;"></div>

	<!-- SCHRITT 3: Dienste synchronisieren & auswählen -->
	<div class="cts-card cts-mt-20">
<div class="cts-card-header">
<span class="dashicons dashicons-update cts-card-icon" aria-hidden="true"></span>
<h3><?php esc_html_e( 'Schritt 3: Dienste synchronisieren', 'churchtools-suite' ); ?></h3>
</div>
<div class="cts-card-body">
<p>
<?php esc_html_e( 'Synchronisieren Sie die Dienste aus den ausgewählten Dienstgruppen.', 'churchtools-suite' ); ?>
</p>

<?php if ( $last_services_sync ) : ?>
<p class="cts-card-meta">
		<?php
					printf(
						/* translators: %s: Formatted date and time of the last synchronization. */                        esc_html__( 'Letzte Synchronisation: %s', 'churchtools-suite' ),
						esc_html( get_date_from_gmt( $last_services_sync, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) )
					);
		?>
</p>
<?php endif; ?>

<div class="cts-button-group">
<button type="button" id="cts-sync-services-btn" class="cts-button cts-button-primary">
<span class="dashicons dashicons-update"></span>
<?php esc_html_e( 'Dienste synchronisieren', 'churchtools-suite' ); ?>
</button>
</div>
	</div>
</div>

<div id="cts-sync-services-result" class="cts-mt-15" style="display: none;"></div>

<!-- SCHRITT 4: Dienste auswählen -->
<div class="cts-card cts-mt-20">
	<div class="cts-card-header">
<span class="dashicons dashicons-yes-alt cts-card-icon" aria-hidden="true"></span>
<h3><?php esc_html_e( 'Schritt 4: Dienste auswählen', 'churchtools-suite' ); ?></h3>
	</div>
	<div class="cts-card-body">

<?php if ( empty( $services ) ) : ?>
<div class="notice notice-info inline">
<p>
		<?php esc_html_e( 'Keine Dienste vorhanden. Bitte synchronisieren Sie zuerst die Dienste.', 'churchtools-suite' ); ?>
</p>
</div>
<?php else : ?>

<p class="description">
	<?php
					printf(
						/* translators: 1: Number of selected services, 2: Total services. */                        esc_html__( 'Wählen Sie die Dienste aus, die bei der Event-Synchronisation importiert werden sollen. Aktuell ausgewählt: %1$d von %2$d', 'churchtools-suite' ),
						(int) $selected_services_count,
						count( $services )
					);
	?>
</p>

<!-- Bulk-Actions für Dienste -->
<div style="margin: 15px 0; display: flex; gap: 10px;">
<button type="button" id="cts-select-all-services-btn" class="button">
<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Alle auswählen', 'churchtools-suite' ); ?>
</button>
<button type="button" id="cts-deselect-all-services-btn" class="button">
<span class="dashicons dashicons-dismiss" aria-hidden="true"></span> <?php esc_html_e( 'Alle abwählen', 'churchtools-suite' ); ?>
</button>
</div>

<form method="post" id="cts-service-selection-form">
	<?php wp_nonce_field( 'cts_service_selection', 'cts_service_selection_nonce' ); ?>

<table class="widefat" style="margin-top: 15px;">
<thead>
<tr>
<th style="width: 40px;">
<input type="checkbox" id="cts-select-all-services" aria-label="<?php esc_attr_e( 'Alle Dienste auswählen', 'churchtools-suite' ); ?>">
</th>
<th><?php esc_html_e( 'Dienst', 'churchtools-suite' ); ?></th>
<th><?php esc_html_e( 'ChurchTools-ID', 'churchtools-suite' ); ?></th>
<th><?php esc_html_e( 'Dienstgruppe', 'churchtools-suite' ); ?></th>
<th><?php esc_html_e( 'Sortierung', 'churchtools-suite' ); ?></th>
</tr>
</thead>
<tbody>
	<?php foreach ( $services as $service ) : ?>
<tr>
<td>
<input
											type="checkbox"
											name="selected_services[]"
											value="<?php echo esc_attr( $service->service_id ); ?>"
											class="cts-service-checkbox" aria-label="<?php echo esc_attr( $service->name ); ?>"
			<?php checked( $service->is_selected, 1 ); ?>
										>
</td>
<td>
<strong><?php echo esc_html( $service->name_translated ?: $service->name ); ?></strong>
			<?php if ( $service->name !== $service->name_translated && ! empty( $service->name_translated ) ) : ?>
<br><small class="description"><?php echo esc_html( $service->name ); ?></small>
<?php endif; ?>
</td>
<td>
<code><?php echo esc_html( $service->service_id ); ?></code>
</td>
<td>
			<?php if ( ! empty( $service->service_group_id ) ) : ?>
<code><?php echo esc_html( $service->service_group_id ); ?></code>
<?php else : ?>
<span class="description"><?php esc_html_e( 'Keine', 'churchtools-suite' ); ?></span>
<?php endif; ?>
</td>
<td>
			<?php echo esc_html( $service->sort_order ); ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div style="margin-top: 20px;">
<button type="submit" class="button button-primary">
	<?php esc_html_e( 'Auswahl speichern', 'churchtools-suite' ); ?>
</button>
</div>
</form>

<?php endif; ?>

</div>
	</div>

	<div id="cts-service-selection-result" style="margin-top: 15px; display: none;"></div>
