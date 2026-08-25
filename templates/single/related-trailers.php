<?php
/**
 * Related trailers partial. Reuses the archive card template. Renders nothing
 * when there are no related trailers.
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lrti_id      = get_the_ID();
$lrti_related = lrti_get_related_query( $lrti_id );

if ( ! $lrti_related->have_posts() ) {
	wp_reset_postdata();
	return;
}

do_action( 'lrti_before_related_trailers', $lrti_id );
?>
<section class="lrti-related" id="lrti-related" aria-label="<?php esc_attr_e( 'Related trailers', 'little-river-trailer-inventory' ); ?>">
	<h2 class="lrti-section-title"><?php esc_html_e( 'Similar Trailers', 'little-river-trailer-inventory' ); ?></h2>
	<div class="lrti-grid lrti-grid--related">
		<?php
		while ( $lrti_related->have_posts() ) :
			$lrti_related->the_post();
			lrti_get_template_part( 'content-trailer' );
		endwhile;
		wp_reset_postdata();
		?>
	</div>
</section>
<?php
do_action( 'lrti_after_related_trailers', $lrti_id );
