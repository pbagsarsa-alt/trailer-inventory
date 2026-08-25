<?php
/**
 * WordPress privacy integration for leads (Sprint 5.0).
 *
 * Registers a personal-data exporter and eraser keyed by email address, and
 * adds suggested privacy-policy content describing what lead data is stored and
 * how long it is kept.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Privacy
 */
final class Privacy {

	/**
	 * Attach hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array<string, mixed> $exporters Existing exporters.
	 * @return array<string, mixed>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['lrti-leads'] = array(
			'exporter_friendly_name' => __( 'Trailer Inquiry Leads', 'little-river-trailer-inventory' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array<string, mixed> $erasers Existing erasers.
	 * @return array<string, mixed>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['lrti-leads'] = array(
			'eraser_friendly_name' => __( 'Trailer Inquiry Leads', 'little-river-trailer-inventory' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Find lead IDs matching an email address.
	 *
	 * @param string $email Email address.
	 * @return int[]
	 */
	private function lead_ids_for_email( string $email ): array {
		$q = new \WP_Query(
			array(
				'post_type'      => Leads::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => Leads::meta_keys()['email'],
						'value' => $email,
					),
				),
			)
		);
		return array_map( 'intval', $q->posts );
	}

	/**
	 * Export lead data for an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array<string, mixed>
	 */
	public function export( string $email, int $page = 1 ): array {
		$data = array();
		$k    = Leads::meta_keys();

		foreach ( $this->lead_ids_for_email( $email ) as $lead_id ) {
			$items = array(
				array( 'name' => __( 'Name', 'little-river-trailer-inventory' ), 'value' => get_post_meta( $lead_id, $k['name'], true ) ),
				array( 'name' => __( 'Email', 'little-river-trailer-inventory' ), 'value' => get_post_meta( $lead_id, $k['email'], true ) ),
				array( 'name' => __( 'Phone', 'little-river-trailer-inventory' ), 'value' => get_post_meta( $lead_id, $k['phone'], true ) ),
				array( 'name' => __( 'Message', 'little-river-trailer-inventory' ), 'value' => get_post_meta( $lead_id, $k['message'], true ) ),
				array( 'name' => __( 'Trailer', 'little-river-trailer-inventory' ), 'value' => get_post_meta( $lead_id, $k['trailer_title'], true ) ),
				array( 'name' => __( 'Submitted', 'little-river-trailer-inventory' ), 'value' => get_the_date( 'Y-m-d H:i', $lead_id ) ),
			);

			$data[] = array(
				'group_id'    => 'lrti-leads',
				'group_label' => __( 'Trailer Inquiry Leads', 'little-river-trailer-inventory' ),
				'item_id'     => 'lrti-lead-' . $lead_id,
				'data'        => $items,
			);
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase lead data for an email address (anonymize personal fields).
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array<string, mixed>
	 */
	public function erase( string $email, int $page = 1 ): array {
		$removed = false;
		$k       = Leads::meta_keys();

		foreach ( $this->lead_ids_for_email( $email ) as $lead_id ) {
			foreach ( array( 'name', 'email', 'phone', 'message', 'ip_hash', 'user_agent', 'referrer', 'source_url' ) as $field ) {
				update_post_meta( $lead_id, $k[ $field ], '' );
			}
			wp_update_post(
				array(
					'ID'         => $lead_id,
					'post_title' => __( 'Anonymized Lead', 'little-river-trailer-inventory' ),
				)
			);
			$removed = true;
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Add suggested privacy-policy content.
	 *
	 * @return void
	 */
	public function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<p>%1$s</p><p>%2$s</p>',
			esc_html__( 'When you submit a trailer inquiry, we store the name, contact details, and message you provide, along with the trailer you asked about, the page you submitted from, and a privacy-safe (hashed) identifier used only to prevent spam. We do not store your raw IP address.', 'little-river-trailer-inventory' ),
			esc_html__( 'Inquiry data is retained according to the retention period configured by the dealership and is used only to respond to your inquiry. You may request an export or erasure of your data using the tools on this site.', 'little-river-trailer-inventory' )
		);

		wp_add_privacy_policy_content( 'TWC Trailer Inventory for Little River Equipment Sales LLC', wp_kses_post( $content ) );
	}
}
