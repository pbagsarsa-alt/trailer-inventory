<?php
/**
 * Features partial: assigned Features taxonomy terms as a badge grid.
 *
 * Shows only features assigned to THIS trailer, alphabetical by default. No
 * output when there are no features. Order is filterable.
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lrti_id    = get_the_ID();
$lrti_terms = get_the_terms( $lrti_id, 'trailer_feature' );

if ( empty( $lrti_terms ) || is_wp_error( $lrti_terms ) ) {
	return;
}

// Alphabetical by default.
usort(
	$lrti_terms,
	static function ( $a, $b ) {
		return strcasecmp( $a->name, $b->name );
	}
);

/**
 * Filter the ordered list of feature terms shown on the single page.
 *
 * @param \WP_Term[] $lrti_terms The feature terms.
 * @param int        $lrti_id    The trailer post ID.
 */
$lrti_terms = (array) apply_filters( 'lrti_features_order', $lrti_terms, $lrti_id );

do_action( 'lrti_before_trailer_features', $lrti_id );
?>
<section class="lrti-features" aria-label="<?php esc_attr_e( 'Features', 'little-river-trailer-inventory' ); ?>">
	<h2 class="lrti-section-title"><?php esc_html_e( 'Features', 'little-river-trailer-inventory' ); ?></h2>
	<ul class="lrti-features-grid" role="list">
		<?php foreach ( $lrti_terms as $lrti_term ) : ?>
			<li class="lrti-feature"><?php echo esc_html( $lrti_term->name ); ?></li>
		<?php endforeach; ?>
	</ul>
</section>
<?php
do_action( 'lrti_after_trailer_features', $lrti_id );
