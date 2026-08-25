<?php
/**
 * Scheduled lead cleanup (Sprint 5.0).
 *
 * Runs a daily cron event that (a) deletes leads older than the configured
 * retention period — unless retention is "keep indefinitely" — while never
 * auto-deleting Won leads unless explicitly configured, and (b) deletes Spam
 * leads older than the configured spam window. Cleanup only runs when a finite
 * period is selected.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LeadRetention
 */
final class LeadRetention {

	/**
	 * The cron hook name.
	 */
	public const CRON_HOOK = 'lrti_lead_cleanup';

	/**
	 * Attach hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_cleanup' ) );
	}

	/**
	 * Ensure the daily cleanup event is scheduled.
	 *
	 * @return void
	 */
	public function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Clear the scheduled event (called on deactivation).
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * The configured retention period in days (0 = keep indefinitely). Filterable.
	 *
	 * @return int
	 */
	private function retention_days(): int {
		$days = (int) lrti_get_setting( 'lead_retention_days', 0 );
		return max( 0, (int) apply_filters( 'lrti_lead_retention_days', $days ) );
	}

	/**
	 * Run the cleanup.
	 *
	 * @return void
	 */
	public function run_cleanup(): void {
		$this->cleanup_by_retention();
		$this->cleanup_spam();
	}

	/**
	 * Delete leads older than the retention window (skipping Won by default).
	 *
	 * @return void
	 */
	private function cleanup_by_retention(): void {
		$days = $this->retention_days();
		if ( $days < 1 ) {
			return; // Keep indefinitely.
		}

		/**
		 * Whether to also auto-delete Won leads during retention cleanup.
		 *
		 * @param bool $delete Default false.
		 */
		$delete_won = (bool) apply_filters( 'lrti_retention_delete_won', false );

		$query = new \WP_Query(
			array(
				'post_type'      => Leads::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'date_query'     => array(
					array( 'before' => $days . ' days ago' ),
				),
			)
		);

		foreach ( $query->posts as $lead_id ) {
			$lead_id = (int) $lead_id;
			$status  = (string) get_post_meta( $lead_id, Leads::STATUS_META, true );
			if ( 'won' === $status && ! $delete_won ) {
				continue;
			}
			wp_delete_post( $lead_id, true );
		}
	}

	/**
	 * Delete Spam leads older than the configured spam window.
	 *
	 * @return void
	 */
	private function cleanup_spam(): void {
		$days = (int) lrti_get_setting( 'spam_delete_days', 30 );
		if ( $days < 1 ) {
			return;
		}

		$query = new \WP_Query(
			array(
				'post_type'      => Leads::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => Leads::STATUS_META,
						'value' => 'spam',
					),
				),
				'date_query'     => array(
					array( 'before' => $days . ' days ago' ),
				),
			)
		);

		foreach ( $query->posts as $lead_id ) {
			wp_delete_post( (int) $lead_id, true );
		}
	}
}
