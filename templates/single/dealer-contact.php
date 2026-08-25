<?php
/**
 * Dealership contact partial + inquiry placeholder anchor.
 *
 * All values come from plugin settings. The inquiry section is a placeholder
 * for this sprint (no working form). "Check Availability" and "Request
 * Information" buttons on the page link to the #lrti-inquiry anchor here.
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lrti_id      = get_the_ID();
$lrti_name    = (string) lrti_get_setting( 'dealership_name', '' );
$lrti_address = (string) lrti_get_setting( 'dealership_address', '' );
$lrti_phone   = (string) lrti_get_setting( 'dealership_phone', '' );
$lrti_email   = (string) lrti_get_setting( 'dealership_email', '' );
$lrti_tel     = lrti_get_tel_href( $lrti_phone );
$lrti_dir     = lrti_directions_url();
$lrti_labels  = lrti_contact_cta_labels();
?>
<?php
// Inquiry section — real forms (Sprint 5.0). Sold trailers use the
// "Request Similar Trailers" mode instead of Check Availability.
$lrti_price_data = lrti_get_price_data( $lrti_id );
$lrti_is_sold    = ! empty( $lrti_price_data['is_sold'] );
$lrti_inq_type   = $lrti_is_sold ? 'similar_inventory' : 'availability';
$lrti_inquiry  = \LRTI\Plugin::instance()->inquiry();
$lrti_forms_on = $lrti_inquiry && $lrti_inquiry->enabled();
?>
<section class="lrti-inquiry" id="lrti-inquiry" aria-label="<?php esc_attr_e( 'Contact and inquiries', 'little-river-trailer-inventory' ); ?>">
	<?php
	if ( $lrti_forms_on ) {
		echo $lrti_inquiry->render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- form output is escaped in the template.
			array(
				'trailer_id' => $lrti_id,
				'form_type'  => $lrti_inq_type,
			)
		);
	} else {
		?>
		<div class="lrti-inquiry-note">
			<h2 class="lrti-section-title"><?php esc_html_e( 'Interested in this trailer?', 'little-river-trailer-inventory' ); ?></h2>
			<p><?php esc_html_e( 'Please call or email us and we will be glad to help.', 'little-river-trailer-inventory' ); ?></p>
		</div>
		<?php
	}
	?>
</section>

<?php do_action( 'lrti_before_dealer_contact', $lrti_id ); ?>
<section class="lrti-dealer" aria-label="<?php esc_attr_e( 'Dealership contact', 'little-river-trailer-inventory' ); ?>">
	<div class="lrti-dealer-info">
		<?php if ( '' !== $lrti_name ) : ?>
			<h2 class="lrti-section-title"><?php echo esc_html( $lrti_name ); ?></h2>
		<?php endif; ?>
		<address class="lrti-dealer-address">
			<?php if ( '' !== $lrti_address ) : ?>
				<span class="lrti-dealer-line"><?php echo esc_html( $lrti_address ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $lrti_phone ) : ?>
				<span class="lrti-dealer-line">
					<a href="tel:<?php echo esc_attr( $lrti_tel ); ?>"><?php echo esc_html( $lrti_phone ); ?></a>
				</span>
			<?php endif; ?>
			<?php if ( '' !== $lrti_email ) : ?>
				<span class="lrti-dealer-line">
					<a href="mailto:<?php echo esc_attr( sanitize_email( $lrti_email ) ); ?>"><?php echo esc_html( $lrti_email ); ?></a>
				</span>
			<?php endif; ?>
		</address>

		<p class="lrti-dealer-message">
			<?php
			printf(
				/* translators: %s: dealership name */
				esc_html__( 'Contact %s for current availability, verified specifications, and purchasing information.', 'little-river-trailer-inventory' ),
				esc_html( '' !== $lrti_name ? $lrti_name : __( 'us', 'little-river-trailer-inventory' ) )
			);
			?>
		</p>
	</div>

	<div class="lrti-dealer-cta">
		<?php if ( '' !== $lrti_tel ) : ?>
			<a class="lrti-btn lrti-btn--primary" href="tel:<?php echo esc_attr( $lrti_tel ); ?>"><?php echo esc_html( $lrti_labels['call'] . ' ' . $lrti_phone ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $lrti_email ) : ?>
			<a class="lrti-btn lrti-btn--outline" href="mailto:<?php echo esc_attr( sanitize_email( $lrti_email ) ); ?>"><?php echo esc_html( $lrti_labels['email'] ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $lrti_dir ) : ?>
			<a class="lrti-btn lrti-btn--outline" href="<?php echo esc_url( $lrti_dir ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $lrti_labels['directions'] ); ?></a>
		<?php endif; ?>
	</div>
</section>
<?php do_action( 'lrti_after_dealer_contact', $lrti_id ); ?>
