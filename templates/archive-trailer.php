<?php
/**
 * Trailer archive template (Sprint 4.3).
 *
 * Renders the header, then delegates the sidebar + results (filters, chips,
 * sorting, grid, pagination, empty states) to the shared Filters engine so the
 * archive, AJAX, and shortcodes stay perfectly in sync.
 *
 * Theme override:
 *   wp-content/themes/your-theme/little-river-trailer-inventory/archive-trailer.php
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$lrti_desc = lrti_archive_description();

// Reflect the current request into the engine (no-JS state).
$lrti_filters         = array();
$lrti_filters_engine  = function_exists( 'lrti_filters_engine' ) ? lrti_filters_engine() : null;
if ( $lrti_filters_engine instanceof \LRTI\Filters ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only.
	$lrti_filters         = $lrti_filters_engine->parse_request( $_GET );
	$lrti_filters['sort'] = lrti_current_sort();
}
?>
<div class="lrti-archive-wrap">

	<?php
	twc_render_public_hero(
		array(
			'variant'     => 'inventory',
			'breadcrumbs' => array(
				array(
					'label' => __( 'Home', 'little-river-trailer-inventory' ),
					'url'   => home_url( '/' ),
				),
				array(
					'label' => __( 'Inventory', 'little-river-trailer-inventory' ),
					'url'   => '',
				),
			),
			'title'       => lrti_archive_title(),
			'description' => $lrti_desc,
		)
	);
	?>

	<?php
	if ( $lrti_filters_engine instanceof \LRTI\Filters ) {
		$lrti_paged = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 1;
		$lrti_filters_engine->render_app( 'archive', array( 'paged' => max( 1, $lrti_paged ) ), $lrti_filters, true, 3, true );
	}
	?>

</div>
<?php
get_footer();
