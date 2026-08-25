<?php
/**
 * Deactivation handler.
 *
 * Runs when an administrator clicks "Deactivate". Per the project rules, this
 * NEVER deletes trailers, leads, or settings. It only clears temporary rewrite
 * rules so WordPress rebuilds them cleanly. Your data is untouched and will
 * still be there if you re-activate.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 */
final class Deactivator {

	/**
	 * Run on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Clear the scheduled lead-cleanup cron event (Sprint 5.0). This does NOT
		// delete any leads — it only removes the recurring task.
		LeadRetention::unschedule();

		// Flush rewrite rules so any custom URL structures this plugin added are
		// removed cleanly from WordPress's cache. This does NOT delete content.
		flush_rewrite_rules();
	}
}
