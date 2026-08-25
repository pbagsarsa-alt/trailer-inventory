<?php
/**
 * Single trailer card, used inside the archive/shortcode/related loops.
 *
 * Sprint 4.4 layout order: image, badges, title, manufacturer + type, quick
 * specs, pricing (with savings), stock number, View Details. Only values that
 * exist are shown. Theme override:
 *   wp-content/themes/<theme>/little-river-trailer-inventory/content-trailer.php
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lrti_id    = get_the_ID();
$lrti_year  = lrti_get_trailer_meta( $lrti_id, 'year', '' );
$lrti_model = lrti_get_trailer_meta( $lrti_id, 'model', '' );
$lrti_stock = lrti_get_trailer_meta( $lrti_id, 'stock_number', '' );
$lrti_manu  = lrti_first_term_name( $lrti_id, 'trailer_manufacturer' );
$lrti_type  = lrti_first_term_name( $lrti_id, 'trailer_type' );
$lrti_avail = lrti_availability( $lrti_id );

$lrti_floor = lrti_get_trailer_meta( $lrti_id, 'floor_length', '' );
$lrti_gvwr  = lrti_get_trailer_meta( $lrti_id, 'gvwr', '' );
$lrti_axles = lrti_get_trailer_meta( $lrti_id, 'axle_count', '' );

$lrti_featured   = ( '1' === (string) lrti_get_trailer_meta( $lrti_id, 'featured', '' ) );
$lrti_sale_badge = ( '1' === (string) lrti_get_trailer_meta( $lrti_id, 'sale_badge', '' ) );

$lrti_price   = lrti_price_html( $lrti_id );
$lrti_pdata   = lrti_get_price_data( $lrti_id );
$lrti_subline = trim( implode( ' · ', array_filter( array( $lrti_manu, $lrti_type ) ) ) );

/**
 * Filter extra attributes added to the card's links (image, title, button).
 * Default empty. The featured-inventory shortcode uses this to open links in a
 * new tab. The returned string must be pre-escaped attribute markup.
 *
 * @param string $attrs   Attribute string (e.g. 'target="_blank" rel="noopener"').
 * @param int    $lrti_id Trailer post ID.
 */
$lrti_link_atts = (string) apply_filters( 'lrti_card_link_attributes', '', $lrti_id );

/**
 * Filter which card fields are shown. Keys default to true; set false to hide.
 *
 * @param array<string, bool> $fields  Field visibility map.
 * @param int                 $lrti_id Trailer post ID.
 */
$lrti_show = (array) apply_filters(
	'lrti_inventory_card_fields',
	array(
		'subline'    => true,
		'quick_specs' => true,
		'price'      => true,
		'savings'    => true,
		'stock'      => true,
		'excerpt'    => true,
	),
	$lrti_id
);

do_action( 'lrti_before_inventory_card', $lrti_id );
do_action( 'lrti_before_trailer_card', $lrti_id ); // Back-compat.
?>
<article id="trailer-<?php echo esc_attr( (string) $lrti_id ); ?>" class="lrti-card">

	<div class="lrti-card-media">
		<?php do_action( 'lrti_before_trailer_image', $lrti_id ); ?>

		<a class="lrti-card-imglink" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true" <?php echo $lrti_link_atts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped attributes from filter. ?>>
			<?php
			if ( has_post_thumbnail() ) {
				the_post_thumbnail(
					'large',
					array(
						'class'   => 'lrti-card-img',
						'loading' => 'lazy',
						'alt'     => the_title_attribute( array( 'echo' => false ) ),
					)
				);
			} else {
				echo lrti_placeholder_image(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped SVG.
			}
			?>
		</a>

		<div class="lrti-badges">
			<?php if ( $lrti_featured ) : ?>
				<span class="lrti-badge lrti-badge--featured"><?php esc_html_e( 'Featured', 'little-river-trailer-inventory' ); ?></span>
			<?php endif; ?>
			<?php if ( $lrti_sale_badge ) : ?>
				<span class="lrti-badge lrti-badge--sale"><?php esc_html_e( 'Sale', 'little-river-trailer-inventory' ); ?></span>
			<?php endif; ?>
			<?php if ( null !== $lrti_avail ) : ?>
				<span class="lrti-badge lrti-badge--avail lrti-avail-<?php echo esc_attr( $lrti_avail['slug'] ); ?>"><?php echo esc_html( $lrti_avail['name'] ); ?></span>
			<?php endif; ?>
		</div>

		<?php do_action( 'lrti_after_trailer_image', $lrti_id ); ?>
	</div>

	<div class="lrti-card-body">

		<div class="lrti-card-main">

			<h2 class="lrti-card-title">
				<a href="<?php the_permalink(); ?>" <?php echo $lrti_link_atts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped attributes from filter. ?>><?php the_title(); ?></a>
			</h2>

			<?php if ( ! empty( $lrti_show['subline'] ) && '' !== $lrti_subline ) : ?>
				<p class="lrti-card-sub"><?php echo esc_html( $lrti_subline ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $lrti_show['quick_specs'] ) && ( '' !== $lrti_floor || '' !== $lrti_gvwr || '' !== $lrti_axles ) ) : ?>
				<ul class="lrti-card-specs">
					<?php if ( '' !== $lrti_floor ) : ?>
						<li><span class="lrti-spec-label"><?php esc_html_e( 'Floor', 'little-river-trailer-inventory' ); ?></span><span class="lrti-spec-value"><?php echo esc_html( lrti_format_measure( $lrti_floor, __( 'ft', 'little-river-trailer-inventory' ) ) ); ?></span></li>
					<?php endif; ?>
					<?php if ( '' !== $lrti_gvwr ) : ?>
						<li><span class="lrti-spec-label"><?php esc_html_e( 'GVWR', 'little-river-trailer-inventory' ); ?></span><span class="lrti-spec-value"><?php echo esc_html( lrti_format_measure( $lrti_gvwr, __( 'lbs', 'little-river-trailer-inventory' ) ) ); ?></span></li>
					<?php endif; ?>
					<?php if ( '' !== $lrti_axles ) : ?>
						<li><span class="lrti-spec-label"><?php esc_html_e( 'Axles', 'little-river-trailer-inventory' ); ?></span><span class="lrti-spec-value"><?php echo esc_html( $lrti_axles ); ?></span></li>
					<?php endif; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $lrti_show['excerpt'] ) && has_excerpt() ) : ?>
				<p class="lrti-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 16 ) ); ?></p>
			<?php endif; ?>

		</div>

		<div class="lrti-card-bottom">

			<?php if ( ! empty( $lrti_show['stock'] ) && '' !== $lrti_stock ) : ?>
				<span class="lrti-card-stock">
					<?php
					printf(
						/* translators: %s: stock number. */
						esc_html__( 'Stock #%s', 'little-river-trailer-inventory' ),
						esc_html( $lrti_stock )
					);
					?>
				</span>
			<?php endif; ?>

			<div class="lrti-card-footer">
				<div class="lrti-card-price-wrap">
					<?php do_action( 'lrti_before_trailer_price', $lrti_id ); ?>
					<?php if ( ! empty( $lrti_show['price'] ) && '' !== $lrti_price ) : ?>
						<div class="lrti-card-price"><?php echo wp_kses_post( $lrti_price ); ?></div>
						<?php if ( ! empty( $lrti_show['savings'] ) && '' !== $lrti_pdata['savings'] ) : ?>
							<p class="lrti-card-savings">
								<?php
								printf(
									/* translators: %s: dollar savings */
									esc_html__( 'You save %s', 'little-river-trailer-inventory' ),
									esc_html( lrti_format_price( $lrti_pdata['savings'] ) )
								);
								?>
							</p>
						<?php endif; ?>
					<?php endif; ?>
					<?php do_action( 'lrti_after_trailer_price', $lrti_id ); ?>
				</div>

				<a class="lrti-btn lrti-btn--primary lrti-card-btn" href="<?php the_permalink(); ?>" <?php echo $lrti_link_atts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped attributes from filter. ?>>
					<?php esc_html_e( 'View Details', 'little-river-trailer-inventory' ); ?>
					<span class="screen-reader-text">
						<?php
						printf(
							/* translators: %s: trailer title. */
							esc_html__( 'for %s', 'little-river-trailer-inventory' ),
							esc_html( get_the_title() )
						);
						?>
					</span>
				</a>
			</div>

		</div>

	</div>
</article>
<?php
do_action( 'lrti_after_trailer_card', $lrti_id ); // Back-compat.
do_action( 'lrti_after_inventory_card', $lrti_id );
