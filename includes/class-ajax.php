<?php
/**
 * Secure AJAX endpoint for inventory filtering (Sprint 4.3).
 *
 * Returns the results-region HTML fragment (header, chips, grid, pagination,
 * empty state) for a given set of filters/base constraints. Uses the shared
 * Filters engine so behavior matches the non-AJAX archive exactly.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ajax
 */
final class Ajax {

	/**
	 * Shared filters engine.
	 *
	 * @var Filters
	 */
	private Filters $filters;

	/**
	 * Constructor.
	 *
	 * @param Filters $filters The filters engine.
	 */
	public function __construct( Filters $filters ) {
		$this->filters = $filters;
	}

	/**
	 * The nonce action used for filter requests.
	 *
	 * @return string
	 */
	public static function nonce_action(): string {
		return 'lrti_filter';
	}

	/**
	 * Attach hooks. The nopriv variant lets logged-out visitors filter too.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_lrti_filter_inventory', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_lrti_filter_inventory', array( $this, 'handle' ) );
	}

	/**
	 * Handle the AJAX filter request.
	 *
	 * @return void
	 */
	public function handle(): void {
		// Verify the nonce (read-only endpoint, but we still require it).
		if ( ! check_ajax_referer( self::nonce_action(), 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Please refresh the page.', 'little-river-trailer-inventory' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$filters = $this->filters->parse_request( $_POST );

		if ( isset( $_POST['sort'] ) ) {
			$filters['sort'] = sanitize_key( wp_unslash( $_POST['sort'] ) );
		}
		$paged = isset( $_POST['paged'] ) ? max( 1, absint( wp_unslash( $_POST['paged'] ) ) ) : 1;

		$instance = isset( $_POST['instance'] ) ? sanitize_key( wp_unslash( $_POST['instance'] ) ) : 'archive';

		// Base constraints from the instance (locked shortcode attributes).
		$base = array( 'paged' => $paged );
		if ( isset( $_POST['base'] ) ) {
			$raw_base = json_decode( wp_unslash( (string) $_POST['base'] ), true );
			if ( is_array( $raw_base ) ) {
				$base = array_merge( $base, $this->sanitize_base( $raw_base ) );
			}
		}
		$columns         = isset( $_POST['columns'] ) ? max( 1, min( 4, absint( wp_unslash( $_POST['columns'] ) ) ) ) : 3;
		$show_pagination = isset( $_POST['pagination'] ) ? ( '0' !== (string) wp_unslash( $_POST['pagination'] ) ) : true;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$query = $this->filters->run_query( $filters, $base );

		ob_start();
		$this->filters->render_results_region( $query, $filters, $instance, $columns, $show_pagination );
		$html = ob_get_clean();

		wp_reset_postdata();

		wp_send_json_success(
			array(
				'html'  => $html,
				'count' => (int) $query->found_posts,
				'pages' => (int) $query->max_num_pages,
			)
		);
	}

	/**
	 * Sanitize base constraints received from the client.
	 *
	 * @param array<string, mixed> $raw Raw base data.
	 * @return array<string, mixed>
	 */
	private function sanitize_base( array $raw ): array {
		$clean = array();
		foreach ( array( 'manufacturer', 'type', 'condition', 'availability', 'pull_type', 'sort' ) as $k ) {
			if ( isset( $raw[ $k ] ) && is_scalar( $raw[ $k ] ) ) {
				$clean[ $k ] = ( 'pull_type' === $k ) ? sanitize_text_field( (string) $raw[ $k ] ) : sanitize_key( (string) $raw[ $k ] );
			}
		}
		foreach ( array( 'featured', 'sale' ) as $k ) {
			if ( ! empty( $raw[ $k ] ) ) {
				$clean[ $k ] = '1';
			}
		}
		if ( isset( $raw['axles'] ) ) {
			$clean['axles'] = (string) absint( $raw['axles'] );
		}
		if ( isset( $raw['per_page'] ) ) {
			$clean['per_page'] = max( 1, absint( $raw['per_page'] ) );
		}
		return $clean;
	}
}
