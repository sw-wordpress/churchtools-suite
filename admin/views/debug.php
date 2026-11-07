<?php
/**
 * Advanced section (Diagnose): renders the sub screen resolved by the screen registry.
 *
 * The section navigation lives in the rail of the page shell; this view only wraps the selected
 * sub screen.
 *
 * @package ChurchTools_Suite
 * @since   0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-logger.php';

$cts_current = ChurchTools_Suite_Admin_Screen::current();
$cts_screen  = $cts_current['screen'];
$cts_sub     = ChurchTools_Suite_Admin_Screen::view_path( 'advanced' === $cts_screen['slug'] ? 'debug/uebersicht.php' : (string) $cts_screen['view'] );
$cts_active  = '' !== $cts_current['sub_slug'] ? $cts_current['sub_slug'] : 'uebersicht';
?>
<div class="cts-debug cts-subtab-content cts-subtab-<?php echo esc_attr( $cts_active ); ?>">
	<?php
	if ( null !== $cts_sub ) {
		include $cts_sub;
	}
	?>
</div>
