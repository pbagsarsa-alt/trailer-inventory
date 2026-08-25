<?php
/**
 * Single trailer detail page (Sprint 4.2).
 *
 * Theme override: copy this file (and/or the templates/single/ partials) to
 *   wp-content/themes/your-theme/little-river-trailer-inventory/single-trailer.php
 * Plugin updates never overwrite theme overrides.
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$lrti_id = get_the_ID();

	/**
	 * Fires before the single trailer output.
	 *
	 * @param int $lrti_id Trailer post ID.
	 */
	do_action( 'lrti_before_single_trailer', $lrti_id );

	/**
	 * Filter whether the price/CTA card sticks on desktop. Default false.
	 *
	 * @param bool $sticky  Whether the card is sticky.
	 * @param int  $lrti_id Trailer ID.
	 */
	$lrti_sticky_class = apply_filters( 'lrti_single_price_card_sticky', false, $lrti_id ) ? ' lrti-price-sticky' : '';

	// Build the shared hero for this trailer.
	$lrti_h_manu  = lrti_first_term_name( $lrti_id, 'trailer_manufacturer' );
	$lrti_h_cond  = lrti_first_term_name( $lrti_id, 'trailer_condition' );
	$lrti_h_stock = (string) lrti_get_trailer_meta( $lrti_id, 'stock_number', '' );
	$lrti_h_year  = (string) lrti_get_trailer_meta( $lrti_id, 'year', '' );
	$lrti_h_avail = lrti_availability( $lrti_id );
	$lrti_h_feat  = ( '1' === (string) lrti_get_trailer_meta( $lrti_id, 'featured', '' ) );
	$lrti_h_sale  = ( '1' === (string) lrti_get_trailer_meta( $lrti_id, 'sale_badge', '' ) );
	$lrti_h_excpt = has_excerpt( $lrti_id ) ? wp_strip_all_tags( get_the_excerpt( $lrti_id ) ) : '';

	$lrti_h_meta = array();
	if ( '' !== $lrti_h_manu ) {
		$lrti_h_meta[] = array( 'label' => __( 'Manufacturer', 'little-river-trailer-inventory' ), 'value' => $lrti_h_manu );
	}
	if ( '' !== $lrti_h_stock ) {
		$lrti_h_meta[] = array( 'label' => __( 'Stock #', 'little-river-trailer-inventory' ), 'value' => $lrti_h_stock );
	}
	if ( '' !== $lrti_h_year ) {
		$lrti_h_meta[] = array( 'label' => __( 'Year', 'little-river-trailer-inventory' ), 'value' => $lrti_h_year );
	}

	$lrti_h_badges = array();
	if ( '' !== $lrti_h_cond ) {
		$lrti_h_cond_terms = get_the_terms( $lrti_id, 'trailer_condition' );
		$lrti_h_cond_slug  = ( is_array( $lrti_h_cond_terms ) && ! empty( $lrti_h_cond_terms ) ) ? $lrti_h_cond_terms[0]->slug : sanitize_title( $lrti_h_cond );
		$lrti_h_badges[]   = array( 'label' => $lrti_h_cond, 'class' => 'lrti-badge--cond lrti-cond-' . $lrti_h_cond_slug );
	}
	if ( null !== $lrti_h_avail ) {
		$lrti_h_badges[] = array( 'label' => $lrti_h_avail['name'], 'class' => 'lrti-badge--avail lrti-avail-' . $lrti_h_avail['slug'] );
	}
	if ( $lrti_h_feat ) {
		$lrti_h_badges[] = array( 'label' => __( 'Featured', 'little-river-trailer-inventory' ), 'class' => 'lrti-badge--featured' );
	}
	if ( $lrti_h_sale ) {
		$lrti_h_badges[] = array( 'label' => __( 'Sale', 'little-river-trailer-inventory' ), 'class' => 'lrti-badge--sale' );
	}

	twc_render_public_hero(
		array(
			'variant'     => 'single',
			'breadcrumbs' => lrti_breadcrumb_items( $lrti_id ),
			'title'       => get_the_title( $lrti_id ),
			'description' => $lrti_h_excpt,
			'meta'        => $lrti_h_meta,
			'badges'      => $lrti_h_badges,
		)
	);
	?>
	<div class="lrti-single<?php echo esc_attr( $lrti_sticky_class ); ?>" id="trailer-<?php echo esc_attr( (string) $lrti_id ); ?>">
		<div class="lrti-container">

			<div class="lrti-single-cols">
				<div class="lrti-col-gallery">
					<?php lrti_get_template_part( 'single/gallery' ); ?>
				</div>
				<div class="lrti-col-summary">
					<?php lrti_get_template_part( 'single/pricing' ); ?>
					<?php lrti_get_template_part( 'single/quick-specs' ); ?>
				</div>
			</div>

			<?php lrti_get_template_part( 'single/description' ); ?>

			<?php lrti_get_template_part( 'single/specifications' ); ?>

			<?php lrti_get_template_part( 'single/features' ); ?>

			<?php
			// Financing message. Terms shown verbatim as entered by the dealer;
			// the dealership must confirm all financing terms with the customer.
			$lrti_financing = (string) lrti_get_trailer_meta( $lrti_id, 'financing_message', '' );
			if ( '' !== $lrti_financing ) :
				?>
				<section class="lrti-financing" aria-label="<?php esc_attr_e( 'Financing', 'little-river-trailer-inventory' ); ?>">
					<h2 class="lrti-section-title"><?php esc_html_e( 'Financing', 'little-river-trailer-inventory' ); ?></h2>
					<div class="lrti-financing-box">
						<?php echo wp_kses_post( wpautop( $lrti_financing ) ); ?>
					</div>
				</section>
			<?php endif; ?>

			<?php lrti_get_template_part( 'single/dealer-contact' ); ?>

			<?php lrti_get_template_part( 'single/related-trailers' ); ?>

		</div>
	</div>
	<?php
	/**
	 * Fires after the single trailer output.
	 *
	 * @param int $lrti_id Trailer post ID.
	 */
	do_action( 'lrti_after_single_trailer', $lrti_id );

endwhile;

get_footer();
