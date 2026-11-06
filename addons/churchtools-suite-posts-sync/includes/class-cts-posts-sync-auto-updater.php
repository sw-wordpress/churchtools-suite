<?php
/**
 * Retired compatibility name. GitHub updates use the verified WordPress provider.
 *
 * @package ChurchTools_Suite
 */

declare( strict_types=1 );

class CTS_Posts_Sync_Auto_Updater {
	public const GITHUB_UPDATES_ENABLED = false;
	public static function init(): void {
		// Compatibility only: never register the retired updater hooks.
	}
}
