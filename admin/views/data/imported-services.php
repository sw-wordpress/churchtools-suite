<?php
/**
 * Imported Dienste Tab - Übersicht importierter Event Dienste
 *
 * @package ChurchTools_Suite
 * @since   0.3.13.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load repositories.
require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-repository-base.php';
require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-event-services-repository.php';
require_once CHURCHTOOLS_SUITE_PATH . 'includes/repositories/class-churchtools-suite-events-repository.php';

// The overview belongs to the data section; other sections must not include it.
if ( 'data' !== ChurchTools_Suite_Admin_Screen::requested_section() ) {
	return;
}

$cts_self_url = ChurchTools_Suite_Admin_Screen::url( 'data/imported-services' );


$event_services_repo = new ChurchTools_Suite_Event_Services_Repository();
$events_repo         = new ChurchTools_Suite_Events_Repository();

// Pagination.
/* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, display filters, or status messages; no state is changed by these query arguments. */ $cts_page = max( 1, (int) ( absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ) );
$limit  = 50;
$offset = ( $cts_page - 1 ) * $limit;

// Get all event services with pagination.
$all_services = $event_services_repo->get_all();
$total_count  = count( $all_services );
$services     = array_slice( $all_services, $offset, $limit );

// Get unique service names.
$unique_services = $event_services_repo->get_unique_service_names();

// Calculate pagination.
$total_pages = ceil( $total_count / $limit );
?>

<div class="cts-imported-services">

	<div class="cts-section-description">
<p class="cts-section-description">
<?php
			printf(
				/* translators: 1: Number of services, 2: Number of events. */                esc_html__( 'Übersicht aller importierten Dienste aus ChurchTools Events. Insgesamt %1$d Dienste in %2$d Events.', 'churchtools-suite' ),
				(int) $total_count,
				count( array_unique( array_column( $all_services, 'event_id' ) ) )
			);
			?>
</p>
	</div>

	<!-- Statistics -->
	<div class="cts-grid cts-grid-3">
<div class="cts-card">
<div class="cts-card-body">
<div class="cts-stat-number"><?php echo esc_html( (string) ( $total_count ) ); ?></div>
<p class="cts-card-detail"><?php esc_html_e( 'Dienste gesamt', 'churchtools-suite' ); ?></p>
</div>
</div>

<div class="cts-card">
<div class="cts-card-body">
<div class="cts-stat-number"><?php echo esc_html( (string) ( count( $unique_services ) ) ); ?></div>
<p class="cts-card-detail"><?php esc_html_e( 'Verschiedene Dienste', 'churchtools-suite' ); ?></p>
</div>
</div>

<div class="cts-card">
<div class="cts-card-body">
<div class="cts-stat-number"><?php echo esc_html( (string) ( count( array_unique( array_column( $all_services, 'event_id' ) ) ) ) ); ?></div>
<p class="cts-card-detail"><?php esc_html_e( 'Events mit Dienste', 'churchtools-suite' ); ?></p>
</div>
</div>
	</div>

	<!-- Dienste List -->
	<?php if ( empty( $services ) ) : ?>
<div class="cts-empty-state">
<span class="cts-empty-icon"></span>
<h3><?php esc_html_e( 'Keine Dienste gefunden', 'churchtools-suite' ); ?></h3>
<p><?php esc_html_e( 'Führen Sie eine Event-Synchronisation durch, um Dienste zu importieren.', 'churchtools-suite' ); ?></p>
</div>
	<?php else : ?>
<div id="cts-imported-services-ajax-container">
<div class="cts-card">
<div class="cts-table-wrapper">
<table class="cts-events-table">
<thead>
<tr>
<th><?php esc_html_e( 'Dienst', 'churchtools-suite' ); ?></th>
<th><?php esc_html_e( 'Person', 'churchtools-suite' ); ?></th>
<th><?php esc_html_e( 'Event', 'churchtools-suite' ); ?></th>
<th><?php esc_html_e( 'Dienst-ID', 'churchtools-suite' ); ?></th>
<th><?php esc_html_e( 'Importiert', 'churchtools-suite' ); ?></th>
</tr>
</thead>
<tbody>
		<?php
		foreach ( $services as $service ) :
			// Get event details.
			$event = $events_repo->get_by_id( $service->event_id );
			?>
<tr>
<td>
<strong><?php echo esc_html( $service->service_name ); ?></strong>
</td>

<td>
			<?php if ( ! empty( $service->person_name ) ) : ?>
				<?php echo esc_html( $service->person_name ); ?>
<?php else : ?>
<span class="cts-muted">—</span>
<?php endif; ?>
</td>

<td>
			<?php if ( $event ) : ?>
<div class="cts-event-title-main">
				<?php echo esc_html( $event->title ); ?>
</div>
<div class="cts-event-date-time">
				<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $event->start_datetime ) ) ); ?>
</div>
<?php else : ?>
<span class="cts-muted"><?php esc_html_e( 'Event gelöscht', 'churchtools-suite' ); ?></span>
<?php endif; ?>
</td>

<td>
			<?php if ( ! empty( $service->service_id ) ) : ?>
<code><?php echo esc_html( $service->service_id ); ?></code>
<?php else : ?>
<span class="cts-muted">—</span>
<?php endif; ?>
</td>

<td>
			<?php if ( ! empty( $service->created_at ) ) : ?>
				<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $service->created_at ) ) ); ?>
<?php else : ?>
<span class="cts-muted">—</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>

<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
<div class="cts-pagination">
<span class="cts-pagination-info">
			<?php
					printf(
						/* translators: 1: Current page, 2: Total pages. */                        esc_html__( 'Seite %1$d von %2$d', 'churchtools-suite' ),
						(int) $cts_page,
						(int) $total_pages
					);
			?>
</span>
<div style="display: flex; gap: 8px;">
			<?php if ( $cts_page > 1 ) : ?>
<a href="<?php echo esc_url( add_query_arg( 'paged', ( $cts_page - 1 ), $cts_self_url ) ); ?>"
							class="cts-button cts-button-secondary">
				<?php esc_html_e( 'Zurück', 'churchtools-suite' ); ?>
</a>
<?php endif; ?>

			<?php if ( $cts_page < $total_pages ) : ?>
<a href="<?php echo esc_url( add_query_arg( 'paged', ( $cts_page + 1 ), $cts_self_url ) ); ?>"
							class="cts-button cts-button-primary">
				<?php esc_html_e( 'Weiter', 'churchtools-suite' ); ?>
</a>
<?php endif; ?>
</div>
</div>
<?php endif; ?>

<script>
		jQuery(function($){
			function fetchServices(paged){
				var data = { action: 'cts_fetch_imported_services_list', paged: paged || 1, nonce: churchtoolsSuite.nonce };
				$.post(churchtoolsSuite.ajaxUrl, data, function(resp){
					if (resp.success){
						$('#cts-imported-services-ajax-container').html(resp.data.html);
					} else {
						alert(resp.data && resp.data.message ? resp.data.message : 'Fehler');
					}
				}, 'json');
			}

			// Intercept pagination links inside this card
			$(document).on('click', '.cts-imported-services .cts-pagination a', function(e){
				e.preventDefault();
				var href = $(this).attr('href');
				var match = href && href.match(/paged=(\d+)/);
				if ( match ) { fetchServices( match[1] ); }
			});
		});
</script>
	<?php endif; ?>

</div>
