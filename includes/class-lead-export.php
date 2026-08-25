<?php
/**
 * Lead CSV export (Sprint 1.10.0).
 *
 * Streams a UTF-8 (BOM) CSV of leads that respects the current Leads-list
 * filters. Protected by capability + nonce and served through admin-post so it
 * is never publicly accessible.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LeadExport
 */
final class LeadExport {

	/**
	 * Leads model.
	 *
	 * @var Leads
	 */
	private Leads $leads;

	/**
	 * Constructor.
	 *
	 * @param Leads $leads Leads model.
	 */
	public function __construct( Leads $leads ) {
		$this->leads = $leads;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_post_lrti_export_leads', array( $this, 'handle_export' ) );
		add_action( 'manage_posts_extra_tablenav', array( $this, 'render_export_button' ) );
	}

	/**
	 * The capability required to export leads.
	 *
	 * @return string
	 */
	private function cap(): string {
		return current_user_can( 'edit_lrti_leads' ) ? 'edit_lrti_leads' : 'manage_options';
	}

	/**
	 * Render an "Export CSV" button above the leads table, carrying the current
	 * filter selections so the export matches what the admin is viewing.
	 *
	 * @param string $which Tablenav position ('top' or 'bottom').
	 * @return void
	 */
	public function render_export_button( string $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'edit-' . Leads::POST_TYPE !== $screen->id ) {
			return;
		}
		if ( ! current_user_can( $this->cap() ) ) {
			return;
		}

		$args = array(
			'action'   => 'lrti_export_leads',
			'_wpnonce' => wp_create_nonce( 'lrti_export_leads' ),
		);
		// Carry through the read-only list filters currently in the URL.
		foreach ( array( 'lrti_status', 'lrti_formtype', 'lrti_notify', 'lrti_read', 'lrti_archived', 's', 'm' ) as $key ) {
			if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only passthrough.
				$args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}
		$url = add_query_arg( $args, admin_url( 'admin-post.php' ) );
		printf(
			'<div class="alignleft actions lrti-export-actions"><a class="lrti-btn lrti-btn--secondary lrti-btn--sm lrti-export-csv" href="%1$s"><span class="dashicons dashicons-download" aria-hidden="true"></span> %2$s</a></div>',
			esc_url( $url ),
			esc_html__( 'Export CSV', 'little-river-trailer-inventory' )
		);
	}

	/**
	 * Build WP_Query args from the current request's filter parameters.
	 *
	 * @return array<string, mixed>
	 */
	private function query_args(): array {
		$meta = array();

		$status = isset( $_GET['lrti_status'] ) ? sanitize_key( wp_unslash( $_GET['lrti_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $status ) {
			$meta[] = array(
				'key'   => Leads::STATUS_META,
				'value' => Leads::valid_status( $status ),
			);
		}

		$form_type = isset( $_GET['lrti_formtype'] ) ? sanitize_key( wp_unslash( $_GET['lrti_formtype'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $form_type ) {
			$meta[] = array(
				'key'   => Leads::meta_keys()['form_type'],
				'value' => $form_type,
			);
		}

		$notify = isset( $_GET['lrti_notify'] ) ? sanitize_key( wp_unslash( $_GET['lrti_notify'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'none' === $notify ) {
			$meta[] = array(
				'key'     => Leads::meta_keys()['notify_status'],
				'compare' => 'NOT EXISTS',
			);
		} elseif ( '' !== $notify ) {
			$meta[] = array(
				'key'   => Leads::meta_keys()['notify_status'],
				'value' => $notify,
			);
		}

		$trailer_id = isset( $_GET['trailer_id'] ) ? absint( wp_unslash( $_GET['trailer_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $trailer_id > 0 ) {
			$meta[] = array(
				'key'   => Leads::meta_keys()['trailer_id'],
				'value' => $trailer_id,
			);
		}

		$read = isset( $_GET['lrti_read'] ) ? sanitize_key( wp_unslash( $_GET['lrti_read'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'read' === $read ) {
			$meta[] = array(
				'key'   => Leads::READ_META,
				'value' => '1',
			);
		} elseif ( 'unread' === $read ) {
			$meta[] = array(
				'key'     => Leads::READ_META,
				'value'   => '1',
				'compare' => '!=',
			);
		}

		$archived = isset( $_GET['lrti_archived'] ) ? sanitize_key( wp_unslash( $_GET['lrti_archived'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'archived' === $archived ) {
			$meta[] = array(
				'key'   => Leads::ARCHIVED_META,
				'value' => '1',
			);
		} else {
			// Default and "active": exclude archived leads.
			$meta[] = array(
				'key'     => Leads::ARCHIVED_META,
				'compare' => 'NOT EXISTS',
			);
		}

		$args = array(
			'post_type'      => Leads::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);
		if ( ! empty( $meta ) ) {
			$meta['relation'] = 'AND';
			$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		/**
		 * Filter the lead-export query args.
		 *
		 * @param array<string, mixed> $args WP_Query args.
		 */
		return (array) apply_filters( 'lrti_lead_export_query_args', $args );
	}

	/**
	 * The export column map: header label => callback( int $lead_id ): string.
	 *
	 * @return array<string, callable>
	 */
	private function columns(): array {
		$k = Leads::meta_keys();
		$m = static function ( int $id, string $meta ): string {
			$v = get_post_meta( $id, $meta, true );
			return is_scalar( $v ) ? (string) $v : '';
		};

		$columns = array(
			__( 'Lead ID', 'little-river-trailer-inventory' )        => static function ( int $id ): string {
				return (string) $id;
			},
			__( 'Submitted Date', 'little-river-trailer-inventory' )  => static function ( int $id ): string {
				return get_the_date( 'Y-m-d', $id );
			},
			__( 'Submitted Time', 'little-river-trailer-inventory' )  => static function ( int $id ): string {
				return get_the_date( 'H:i', $id );
			},
			__( 'Lead Status', 'little-river-trailer-inventory' )     => static function ( int $id ): string {
				$statuses = Leads::statuses();
				$s        = (string) get_post_meta( $id, Leads::STATUS_META, true );
				return $statuses[ $s ] ?? $s;
			},
			__( 'Read Status', 'little-river-trailer-inventory' )     => static function ( int $id ): string {
				return '1' === (string) get_post_meta( $id, Leads::READ_META, true ) ? 'Read' : 'Unread';
			},
			__( 'Customer Name', 'little-river-trailer-inventory' )   => static function ( int $id ) use ( $m, $k ): string {
				return $m( $id, $k['name'] );
			},
			__( 'Email', 'little-river-trailer-inventory' )           => static function ( int $id ) use ( $m, $k ): string {
				return $m( $id, $k['email'] );
			},
			__( 'Phone', 'little-river-trailer-inventory' )           => static function ( int $id ) use ( $m, $k ): string {
				return $m( $id, $k['phone'] );
			},
			__( 'Preferred Contact', 'little-river-trailer-inventory' ) => static function ( int $id ) use ( $m, $k ): string {
				return $m( $id, $k['preferred_contact'] );
			},
			__( 'Form Type', 'little-river-trailer-inventory' )       => static function ( int $id ) use ( $m, $k ): string {
				return $m( $id, $k['form_type'] );
			},
			__( 'Trailer Title', 'little-river-trailer-inventory' )   => static function ( int $id ) use ( $m, $k ): string {
				return $m( $id, $k['trailer_title'] );
			},
			__( 'Trailer ID', 'little-river-trailer-inventory' )      => static function ( int $id ) use ( $m, $k ): string {
				return $m( $id, $k['trailer_id'] );
			},
			__( 'Stock Number', 'little-river-trailer-inventory' )    => static function ( int $id ) use ( $m, $k ): string {
				return $m( $id, $k['stock_number'] );
			},
			__( 'Trailer URL', 'little-river-trailer-inventory' )     => static function ( int $id ) use ( $m, $k ): string {
				return $m( $id, $k['trailer_url'] );
			},
			__( 'Message', 'little-river-trailer-inventory' )         => static function ( int $id ) use ( $m, $k ): string {
				return $m( $id, $k['message'] );
			},
			__( 'Consent Given', 'little-river-trailer-inventory' )   => static function ( int $id ) use ( $m, $k ): string {
				return '1' === $m( $id, $k['consent'] ) ? 'Yes' : 'No';
			},
			__( 'Notification Status', 'little-river-trailer-inventory' ) => static function ( int $id ) use ( $m, $k ): string {
				return $m( $id, $k['notify_status'] );
			},
			__( 'Notification Email', 'little-river-trailer-inventory' ) => static function ( int $id ) use ( $m, $k ): string {
				return $m( $id, $k['notify_recipient'] );
			},
			__( 'Internal Notes', 'little-river-trailer-inventory' )  => static function ( int $id ) use ( $m, $k ): string {
				return $m( $id, $k['internal_notes'] );
			},
			__( 'Last Contacted', 'little-river-trailer-inventory' )  => static function ( int $id ): string {
				$ts = (int) get_post_meta( $id, Leads::LAST_CONTACTED_META, true );
				return $ts > 0 ? gmdate( 'Y-m-d H:i', $ts ) : '';
			},
			__( 'Next Follow-Up', 'little-river-trailer-inventory' )  => static function ( int $id ) use ( $k ): string {
				return (string) get_post_meta( $id, $k['next_followup'], true );
			},
			__( 'Follow-Up Notes', 'little-river-trailer-inventory' ) => static function ( int $id ) use ( $k ): string {
				return (string) get_post_meta( $id, $k['followup_notes'], true );
			},
			__( 'Internal Note Count', 'little-river-trailer-inventory' ) => static function ( int $id ) use ( $k ): string {
				$log = get_post_meta( $id, $k['notes_log'], true );
				return (string) ( is_array( $log ) ? count( $log ) : 0 );
			},
			__( 'Assigned User', 'little-river-trailer-inventory' )   => static function ( int $id ) use ( $m, $k ): string {
				$uid = (int) $m( $id, $k['assigned_user'] );
				if ( $uid <= 0 ) {
					return '';
				}
				$user = get_userdata( $uid );
				return $user ? $user->display_name : '';
			},
			__( 'Source URL', 'little-river-trailer-inventory' )      => static function ( int $id ) use ( $m, $k ): string {
				return $m( $id, $k['source_url'] );
			},
			__( 'IP Address', 'little-river-trailer-inventory' )      => static function ( int $id ) use ( $m, $k ): string {
				// Only a hashed identifier is ever stored; export as-is.
				return $m( $id, $k['ip_hash'] );
			},
			__( 'User Agent', 'little-river-trailer-inventory' )      => static function ( int $id ) use ( $m, $k ): string {
				return $m( $id, $k['user_agent'] );
			},
		);

		/**
		 * Filter the lead-export columns (label => callback).
		 *
		 * @param array<string, callable> $columns Column map.
		 */
		return (array) apply_filters( 'lrti_lead_export_columns', $columns );
	}

	/**
	 * Handle the export request: capability + nonce, then stream the CSV.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		if ( ! current_user_can( $this->cap() ) ) {
			wp_die( esc_html__( 'You are not allowed to export leads.', 'little-river-trailer-inventory' ), 403 );
		}
		check_admin_referer( 'lrti_export_leads' );

		$query   = new \WP_Query( $this->query_args() );
		$columns = $this->columns();
		$labels  = array_keys( $columns );

		$filename = 'little-river-trailer-leads-' . gmdate( 'Y-m-d-Hi' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );

		// UTF-8 BOM for Excel.
		echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		fputcsv( $out, $labels );

		while ( $query->have_posts() ) {
			$query->the_post();
			$id  = (int) get_the_ID();
			$row = array();
			foreach ( $columns as $callback ) {
				$value = is_callable( $callback ) ? (string) call_user_func( $callback, $id ) : '';
				// Strip HTML but keep line breaks intact for message/notes.
				$value = wp_strip_all_tags( $value );
				$row[] = $value;
			}
			/**
			 * Filter a single export row before it is written.
			 *
			 * @param array<int, string> $row Row values.
			 * @param int                $id  Lead ID.
			 */
			$row = (array) apply_filters( 'lrti_lead_export_row', $row, $id );
			fputcsv( $out, $row );
		}
		wp_reset_postdata();

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
