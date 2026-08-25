<?php
/**
 * Description partial: short description (excerpt) and/or full content.
 *
 * Avoids duplicate text: the excerpt is only shown when it was manually
 * entered. Internal dealership notes are never output here.
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lrti_id       = get_the_ID();
$lrti_has_exc  = has_excerpt( $lrti_id );
$lrti_content  = trim( (string) get_the_content( null, false, $lrti_id ) );

if ( ! $lrti_has_exc && '' === $lrti_content ) {
	return;
}
?>
<section class="lrti-description" aria-label="<?php esc_attr_e( 'Description', 'little-river-trailer-inventory' ); ?>">
	<h2 class="lrti-section-title"><?php esc_html_e( 'Description', 'little-river-trailer-inventory' ); ?></h2>

	<?php if ( $lrti_has_exc ) : ?>
		<div class="lrti-description-lead">
			<?php
			$lrti_exc   = trim( (string) get_the_excerpt( $lrti_id ) );
			$lrti_lines = preg_split( '/\r\n|\r|\n/', $lrti_exc );
			$lrti_lines = is_array( $lrti_lines ) ? array_values( array_filter( array_map( 'trim', $lrti_lines ) ) ) : array();

			// If the short description is several plain lines with no HTML, show
			// it as a clean list; otherwise render normally.
			if ( count( $lrti_lines ) > 1 && $lrti_exc === wp_strip_all_tags( $lrti_exc ) ) {
				echo '<ul class="lrti-description-list">';
				foreach ( $lrti_lines as $lrti_line ) {
					echo '<li>' . esc_html( $lrti_line ) . '</li>';
				}
				echo '</ul>';
			} else {
				echo wp_kses_post( wpautop( $lrti_exc ) );
			}
			?>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $lrti_content ) : ?>
		<div class="lrti-description-body">
			<?php
			// the_content applies safe editor formatting and shortcodes.
			the_content();
			?>
		</div>
	<?php endif; ?>
</section>
