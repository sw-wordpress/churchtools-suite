<?php
/**
 * Settings section: renders the sub screen resolved by the screen registry.
 *
 * The navigation of this section lives in the rail of the page shell, so this view only provides
 * the shared wrapper around the selected sub screen.
 *
 * @package ChurchTools_Suite
 * @since   0.7.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cts_current = ChurchTools_Suite_Admin_Screen::current();
$cts_screen  = $cts_current['screen'];

// Safety net: a request that addresses the section itself renders its first sub screen.
if ( 'settings' === $cts_screen['slug'] ) {
	$cts_sub    = ChurchTools_Suite_Admin_Screen::view_path( 'settings/api.php' );
	$cts_active = 'api';
} else {
	$cts_sub    = ChurchTools_Suite_Admin_Screen::view_path( (string) $cts_screen['view'] );
	$cts_active = $cts_current['sub_slug'];
}
?>
<div class="cts-settings cts-subtab-content cts-subtab-<?php echo esc_attr( $cts_active ); ?>">
	<?php
	if ( null !== $cts_sub ) {
		include $cts_sub;
	}
	?>
</div>
