<?php
/**
 * Quick specifications (compact) shown near pricing.
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lrti_id    = get_the_ID();
$lrti_specs = lrti_quick_specs( $lrti_id );

if ( empty( $lrti_specs ) ) {
	return;
}
?>
<section class="lrti-quickspecs" aria-label="<?php esc_attr_e( 'Quick specifications', 'little-river-trailer-inventory' ); ?>">
	<h2 class="lrti-section-title lrti-section-title--sm"><?php esc_html_e( 'Quick Specs', 'little-river-trailer-inventory' ); ?></h2>
	<dl class="lrti-quickspecs-list">
		<?php foreach ( $lrti_specs as $lrti_label => $lrti_value ) : ?>
			<div class="lrti-quickspecs-row">
				<dt><?php echo esc_html( $lrti_label ); ?></dt>
				<dd><?php echo esc_html( $lrti_value ); ?></dd>
			</div>
		<?php endforeach; ?>
	</dl>
</section>
