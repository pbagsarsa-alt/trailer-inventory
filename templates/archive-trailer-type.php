<?php
/**
 * Trailer Type taxonomy archive (Sprint 2.9.7).
 *
 * A polished, dealership-style archive that reuses the shared inventory engine
 * (cards, grid, sort, pagination, empty state) constrained to the current
 * Trailer Type. Hero/intro/content come from Trailer Type term meta.
 *
 * Theme override:
 *   wp-content/themes/your-theme/little-river-trailer-inventory/archive-trailer-type.php
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$lrti_term = get_queried_object();

if ( ! ( $lrti_term instanceof \WP_Term ) ) {
	// Should not happen (loader gates on is_tax), but never fatal.
	get_footer();
	return;
}

$lrti_engine = function_exists( 'lrti_filters_engine' ) ? lrti_filters_engine() : null;
$lrti_paged  = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 1;
$lrti_sort   = function_exists( 'lrti_current_sort' ) ? lrti_current_sort() : 'newest';

// Term meta (all optional).
$lrti_heading = trim( (string) get_term_meta( $lrti_term->term_id, '_twc_archive_heading', true ) );
$lrti_intro   = trim( (string) get_term_meta( $lrti_term->term_id, '_twc_archive_intro', true ) );
$lrti_hero    = (int) get_term_meta( $lrti_term->term_id, '_twc_archive_hero', true );
$lrti_content = (string) get_term_meta( $lrti_term->term_id, '_twc_archive_content', true );
$lrti_empty   = trim( (string) get_term_meta( $lrti_term->term_id, '_twc_archive_empty', true ) );

if ( '' === $lrti_heading ) {
	$lrti_heading = $lrti_term->name;
}
// Archive Intro term meta only (no generated filler; hero falls back to the
// taxonomy description, then to nothing).

// Count available trailers of this type (mirrors the engine's query rules).
$lrti_count = 0;
if ( $lrti_engine instanceof \LRTI\Filters ) {
	$lrti_count_q = $lrti_engine->run_query(
		array( 'sort' => $lrti_sort ),
		array(
			'type'     => $lrti_term->slug,
			'paged'    => 1,
			'per_page' => 1,
		)
	);
	$lrti_count = (int) $lrti_count_q->found_posts;
	wp_reset_postdata();
}

$lrti_inventory_url = (string) get_post_type_archive_link( \LRTI\PostTypes::POST_TYPE );

// Hero description: taxonomy description, else Archive Intro meta, else none.
$lrti_hero_desc = '';
if ( '' !== trim( (string) $lrti_term->description ) ) {
	$lrti_hero_desc = wp_strip_all_tags( $lrti_term->description );
} elseif ( '' !== $lrti_intro ) {
	$lrti_hero_desc = $lrti_intro;
}

twc_render_public_hero(
	array(
		'variant'     => 'trailer-type',
		'breadcrumbs' => array(
			array(
				'label' => __( 'Home', 'little-river-trailer-inventory' ),
				'url'   => home_url( '/' ),
			),
			array(
				'label' => __( 'Inventory', 'little-river-trailer-inventory' ),
				'url'   => $lrti_inventory_url,
			),
			array(
				'label' => $lrti_term->name,
				'url'   => '',
			),
		),
		'title'       => $lrti_heading,
		'description' => $lrti_hero_desc,
	)
);
?>
<div class="lrti-inventory lrti-archive lrti-taxarchive">

	<div class="lrti-container lrti-taxarchive-toolbar">
		<p class="lrti-taxarchive-count">
			<?php
			printf(
				/* translators: 1: count, 2: trailer type name. */
				esc_html( _n( '%1$s %2$s Available', '%1$s %2$s Available', $lrti_count, 'little-river-trailer-inventory' ) ),
				esc_html( number_format_i18n( $lrti_count ) ),
				esc_html( $lrti_term->name )
			);
			?>
		</p>
		<?php if ( '' !== $lrti_inventory_url ) : ?>
			<a class="lrti-btn lrti-btn--outline lrti-btn--small" href="<?php echo esc_url( $lrti_inventory_url ); ?>">
				&larr; <?php esc_html_e( 'Back to All Inventory', 'little-river-trailer-inventory' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php
	if ( 0 === $lrti_count ) :
		if ( '' === $lrti_empty ) {
			/* translators: %s: trailer type name (lowercased). */
			$lrti_empty = sprintf( __( 'We currently do not have any %s in stock. Please contact us or browse our full inventory.', 'little-river-trailer-inventory' ), strtolower( $lrti_term->name ) );
		}
		$lrti_contact_url = (string) lrti_get_setting( 'directions_url', '' );
		$lrti_phone       = (string) lrti_get_setting( 'phone', '(870) 542-4661' );
		?>
		<div class="lrti-container">
			<div class="lrti-taxarchive-empty">
				<h2 class="lrti-taxarchive-empty-title">
					<?php
					printf(
						/* translators: %s: trailer type name. */
						esc_html__( 'No %s Available Right Now', 'little-river-trailer-inventory' ),
						esc_html( $lrti_term->name )
					);
					?>
				</h2>
				<p class="lrti-taxarchive-empty-text"><?php echo esc_html( $lrti_empty ); ?></p>
				<p class="lrti-taxarchive-empty-actions">
					<?php if ( '' !== $lrti_inventory_url ) : ?>
						<a class="lrti-btn lrti-btn--primary" href="<?php echo esc_url( $lrti_inventory_url ); ?>"><?php esc_html_e( 'Browse Inventory', 'little-river-trailer-inventory' ); ?></a>
					<?php endif; ?>
					<a class="lrti-btn lrti-btn--outline" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $lrti_phone ) ); ?>"><?php esc_html_e( 'Contact Us', 'little-river-trailer-inventory' ); ?></a>
				</p>
			</div>
		</div>
		<?php
	elseif ( $lrti_engine instanceof \LRTI\Filters ) :
		// Reuse the inventory engine: results-only (no sidebar), constrained to
		// this Trailer Type via the base override so no "Type" chip appears.
		$lrti_engine->render_app(
			'trailer-type',
			array(
				'type'  => $lrti_term->slug,
				'paged' => max( 1, $lrti_paged ),
			),
			array( 'sort' => $lrti_sort ),
			false,
			3,
			true
		);
	endif;
	?>

	<?php if ( '' !== trim( $lrti_content ) ) : ?>
		<section class="lrti-taxarchive-content">
			<div class="lrti-container">
				<h2 class="lrti-taxarchive-content-title">
					<?php
					printf(
						/* translators: %s: trailer type name. */
						esc_html__( 'About %s', 'little-river-trailer-inventory' ),
						esc_html( $lrti_term->name )
					);
					?>
				</h2>
				<div class="lrti-taxarchive-content-body">
					<?php echo wp_kses_post( wpautop( $lrti_content ) ); ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

</div>
<?php
get_footer();
