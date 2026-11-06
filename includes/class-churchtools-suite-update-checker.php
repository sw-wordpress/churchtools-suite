<?php
/**
 * Retired compatibility name. GitHub updates use the verified WordPress provider.
 *
 * @package ChurchTools_Suite
 */

declare( strict_types=1 );

class ChurchTools_Suite_Update_Checker {
	public const GITHUB_UPDATES_ENABLED = false;
	public static function init(): void {
		// Compatibility only: never register the retired updater hooks.
	}
}
