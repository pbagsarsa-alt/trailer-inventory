<?php
/**
 * Pricing and primary calls to action.
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lrti_id     = get_the_ID();
$lrti_data   = lrti_get_price_data( $lrti_id );
$lrti_price  = lrti_single_price_html( $lrti_id );
$lrti_labels = lrti_contact_cta_labels();
$lrti_phone  = (string) lrti_get_setting( 'dealership_phone', '' );
$lrti_tel    = lrti_get_tel_href( $lrti_phone );
$lrti_sold   = ! empty( $lrti_data['is_sold'] );

do_action( 'lrti_before_trailer_price', $lrti_id );
?>
<section class="lrti-pricing" aria-label="<?php esc_attr_e( 'Pricing', 'little-river-trailer-inventory' ); ?>">

	<?php if ( '' !== $lrti_price ) : ?>
		<div class="lrti-pricing-amount"><?php echo wp_kses_post( $lrti_price ); ?></div>
	<?php endif; ?>

	<?php
	do_action( 'lrti_before_savings', $lrti_id );
	if ( '' !== $lrti_data['savings'] ) :
		?>
		<p class="lrti-savings">
			<?php
			if ( '' !== $lrti_data['percent'] ) {
				printf(
					/* translators: 1: dollar savings, 2: percent savings */
					esc_html__( 'You save %1$s (%2$s%%)', 'little-river-trailer-inventory' ),
					esc_html( lrti_format_price( $lrti_data['savings'] ) ),
					esc_html( $lrti_data['percent'] )
				);
			} else {
				printf(
					/* translators: %s: dollar savings */
					esc_html__( 'You save %s', 'little-river-trailer-inventory' ),
					esc_html( lrti_format_price( $lrti_data['savings'] ) )
				);
			}
			?>
		</p>
	<?php endif; ?>
	<?php do_action( 'lrti_after_savings', $lrti_id ); ?>

	<div class="lrti-cta-group">
		<?php if ( '' !== $lrti_tel ) : ?>
			<a class="lrti-btn lrti-btn--primary lrti-cta" href="tel:<?php echo esc_attr( $lrti_tel ); ?>">
				<?php echo esc_html( $lrti_labels['call'] . ' ' . $lrti_phone ); ?>
			</a>
		<?php endif; ?>

		<?php if ( $lrti_sold ) : ?>
			<a class="lrti-btn lrti-btn--outline lrti-cta" href="#lrti-trailer-inquiry"
				data-lrti-inquiry-mode="similar_inventory"
				data-lrti-target="#lrti-trailer-inquiry"
				data-lrti-focus="name"
				data-lrti-heading="<?php esc_attr_e( 'Looking for a Similar Trailer?', 'little-river-trailer-inventory' ); ?>">
				<?php echo esc_html( $lrti_labels['similar'] ); ?>
			</a>
		<?php else : ?>
			<a class="lrti-btn lrti-btn--outline lrti-cta" href="#lrti-trailer-inquiry"
				data-lrti-inquiry-mode="availability"
				data-lrti-target="#lrti-trailer-inquiry"
				data-lrti-focus="name"
				data-lrti-heading="<?php esc_attr_e( 'Check Availability', 'little-river-trailer-inventory' ); ?>">
				<?php echo esc_html( $lrti_labels['availability'] ); ?>
			</a>
		<?php endif; ?>

		<?php if ( ! $lrti_sold ) : ?>
			<a class="lrti-btn lrti-btn--outline lrti-cta" href="#lrti-trailer-inquiry"
				data-lrti-inquiry-mode="information"
				data-lrti-target="#lrti-trailer-inquiry"
				data-lrti-focus="message"
				data-lrti-heading="<?php esc_attr_e( 'Request Information', 'little-river-trailer-inventory' ); ?>">
				<?php echo esc_html( $lrti_labels['request'] ); ?>
			</a>
		<?php endif; ?>
	</div>
</section>
<?php
do_action( 'lrti_after_trailer_price', $lrti_id );
