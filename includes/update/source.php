<?php
/**
 * Local trust and storage configuration; never populated from release metadata.
 * Copies in other plugins change this file and descriptors.php, with their own class prefix.
 *
 * @package ChurchTools_Suite
 */

return array(
	'repository'    => 'sw-wordpress/churchtools-suite',
	'repository_id' => 1163606817,
	'allowed_slugs' => array( 'churchtools-suite', 'churchtools-suite-elementor', 'churchtools-suite-posts-sync', 'churchtools-suite-presentations' ),
	'main_slug'     => 'churchtools-suite',
	'state_key'     => 'cts_verified_github_updates_v1',
	'lock_key'      => 'cts_verified_github_updates_lock_v1',
);
