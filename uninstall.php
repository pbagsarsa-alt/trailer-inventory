<?php
/**
 * Uninstall routine.
 *
 * WordPress loads this file ONLY when an administrator deletes the plugin from
 * the Plugins screen (not on deactivation). By default we preserve everything.
 * Data is removed only if the administrator explicitly enabled the
 * "Remove all data on uninstall" setting.
 *
 * @package LittleRiverTrailerInventory
 */

// This constant is defined by WordPress only during a genuine uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$lrti_settings = get_option( 'lrti_settings', array() );

$lrti_remove_data = is_array( $lrti_settings )
	&& ! empty( $lrti_settings['remove_all_data_on_uninstall'] );

// SAFETY GATE: if the admin did not opt in, do nothing at all. All data stays.
if ( ! $lrti_remove_data ) {
	return;
}

/*
 * The admin explicitly enabled full data removal. Delete plugin options.
 *
 * Note: trailers (posts), their meta, and taxonomy terms are intentionally NOT
 * bulk-deleted here yet; that will be handled carefully in a later phase, still
 * behind this same opt-in check. This keeps the current behavior conservative.
 */
delete_option( 'lrti_settings' );
delete_option( 'lrti_version' );
delete_option( 'lrti_terms_version' );

/*
 * Leads (Sprint 5.0). Because the admin opted in to full removal, delete all
 * lead records and their meta, clear the scheduled cleanup event, and remove
 * the lead capabilities that were granted on activation.
 */
$lrti_lead_ids = get_posts(
	array(
		'post_type'      => 'lrti_lead',
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'suppress_filters' => true,
	)
);
foreach ( $lrti_lead_ids as $lrti_lead_id ) {
	wp_delete_post( (int) $lrti_lead_id, true );
}

// Clear the daily cleanup cron event.
$lrti_ts = wp_next_scheduled( 'lrti_lead_cleanup' );
if ( $lrti_ts ) {
	wp_unschedule_event( $lrti_ts, 'lrti_lead_cleanup' );
}

// Remove lead capabilities from all roles.
$lrti_lead_caps = array(
	'edit_lrti_lead',
	'read_lrti_lead',
	'delete_lrti_lead',
	'edit_lrti_leads',
	'edit_others_lrti_leads',
	'publish_lrti_leads',
	'read_private_lrti_leads',
	'delete_lrti_leads',
	'delete_others_lrti_leads',
	'delete_private_lrti_leads',
	'delete_published_lrti_leads',
	'edit_private_lrti_leads',
	'edit_published_lrti_leads',
);
$lrti_roles = wp_roles();
if ( $lrti_roles instanceof WP_Roles ) {
	foreach ( $lrti_roles->role_objects as $lrti_role ) {
		foreach ( $lrti_lead_caps as $lrti_cap ) {
			$lrti_role->remove_cap( $lrti_cap );
		}
	}
}

// Clear lead-related transients.
delete_transient( 'lrti_new_lead_count' );
