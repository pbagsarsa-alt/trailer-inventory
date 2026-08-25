<?php
/**
 * Full specifications, grouped. Uses accessible definition lists. Only
 * completed fields and non-empty groups are shown.
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lrti_id     = get_the_ID();
$lrti_groups = lrti_full_specs( $lrti_id );

if ( empty( $lrti_groups ) ) {
	return;
}

do_action( 'lrti_before_trailer_specs', $lrti_id );
?>
<section class="lrti-specs" aria-label="<?php esc_attr_e( 'Specifications', 'little-river-trailer-inventory' ); ?>">
	<h2 class="lrti-section-title"><?php esc_html_e( 'Specifications', 'little-river-trailer-inventory' ); ?></h2>

	<div class="lrti-specs-groups">
		<?php foreach ( $lrti_groups as $lrti_group => $lrti_rows ) : ?>
			<div class="lrti-specs-group">
				<h3 class="lrti-specs-group-title"><?php echo esc_html( $lrti_group ); ?></h3>
				<dl class="lrti-specs-list">
					<?php foreach ( $lrti_rows as $lrti_label => $lrti_value ) : ?>
						<div class="lrti-specs-row">
							<dt><?php echo esc_html( $lrti_label ); ?></dt>
							<dd><?php echo esc_html( $lrti_value ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</div>
		<?php endforeach; ?>
	</div>
</section>
<?php
do_action( 'lrti_after_trailer_specs', $lrti_id );
