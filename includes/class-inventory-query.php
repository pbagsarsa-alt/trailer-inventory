<?php
/**
 * Main-query integration for the public archive (Sprint 4.3 refactor).
 *
 * On the trailer archive, this applies the shared Filters engine to the MAIN
 * query so that filtering, sorting, keyword search, and pagination all work
 * without JavaScript (standard GET parameters). The heavy lifting (tax_query,
 * meta_query, keyword and sort clauses) lives in the Filters class, which is
 * the single source of truth also used by AJAX and shortcodes.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class InventoryQuery
 */
final class InventoryQuery {

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
	 * Attach hooks. (Keyword and sort clauses are registered by the Filters
	 * class itself, so they apply to the main query as well.)
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'pre_get_posts', array( $this, 'adjust_query' ) );
	}

	/**
	 * Is this the main front-end trailer archive query?
	 *
	 * @param \WP_Query $query The query.
	 * @return bool
	 */
	private function is_inventory_archive( \WP_Query $query ): bool {
		if ( is_admin() || ! $query->is_main_query() ) {
			return false;
		}
		return (bool) $query->is_post_type_archive( PostTypes::POST_TYPE );
	}

	/**
	 * Apply filters, sorting, keyword, and per-page to the main archive query.
	 *
	 * @param \WP_Query $query The query.
	 * @return void
	 */
	public function adjust_query( \WP_Query $query ): void {
		if ( ! $this->is_inventory_archive( $query ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only navigation.
		$request = $this->filters->parse_request( $_GET );
		$request['sort'] = lrti_current_sort();

		$args = $this->filters->build_query_args( $request );

		$query->set( 'posts_per_page', (int) $args['posts_per_page'] );
		$query->set( 'lrti_sort', (string) $args['lrti_sort'] );

		if ( isset( $args['tax_query'] ) ) {
			$query->set( 'tax_query', $args['tax_query'] );
		}
		if ( isset( $args['meta_query'] ) ) {
			$query->set( 'meta_query', $args['meta_query'] );
		}
		if ( ! empty( $args['lrti_keyword'] ) ) {
			$query->set( 'lrti_keyword', (string) $args['lrti_keyword'] );
		}
	}
}
