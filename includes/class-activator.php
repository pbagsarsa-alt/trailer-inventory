<?php
/**
 * Activation handler.
 *
 * Runs once when an administrator activates the plugin. In Phase 2 it also
 * registers the trailer post type and taxonomies and seeds the default terms
 * BEFORE flushing rewrite rules, so the new /inventory/ URLs work immediately.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 */
final class Activator {

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::grant_lead_capabilities();
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		self::seed_default_settings();

		// Register the post type and taxonomies now, during activation, because
		// the normal "init" hook for this request has already run. Registering
		// here lets flush_rewrite_rules() below build the correct /inventory/
		// URL rules.
		( new PostTypes() )->register_post_type();

		$taxonomies = new Taxonomies();
		$taxonomies->register_taxonomies();
		$taxonomies->seed_default_terms();

		// Record the default-terms version so the admin_init upgrade routine
		// does not needlessly re-run seeding right after a fresh activation.
		update_option( 'lrti_terms_version', Taxonomies::TERMS_VERSION );

		// Rebuild WordPress's URL rules so /inventory/ works right away.
		flush_rewrite_rules();

		update_option( 'lrti_version', LRTI_VERSION );
	}

	/**
	 * Add default settings WITHOUT overwriting any that already exist.
	 *
	 * @return void
	 */
	private static function seed_default_settings(): void {
		add_option( 'lrti_settings', lrti_get_default_settings() );
	}

	/**
	 * Grant lead management capabilities to appropriate roles (Sprint 5.0).
	 *
	 * @return void
	 */
	public static function grant_lead_capabilities(): void {
		Leads::grant_capabilities( array( 'administrator', 'editor' ) );
		update_option( 'lrti_lead_caps_version', Leads::CAP_VERSION );
	}
}
