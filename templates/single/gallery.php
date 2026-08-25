<?php
/**
 * Image gallery partial. Native, lightweight (no third-party library).
 *
 * The active image fills the entire stage (object-fit: cover, centered) while
 * the lightbox shows the full uncropped image (object-fit: contain). Aspect
 * ratio, focal position, and image sizes are filterable. If no images exist, a
 * professional placeholder is shown. Theme override:
 *   wp-content/themes/<theme>/little-river-trailer-inventory/single/gallery.php
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lrti_id  = get_the_ID();
$lrti_ids = lrti_get_gallery_image_ids( $lrti_id );

/**
 * Filter the gallery stage aspect ratio (CSS aspect-ratio value).
 *
 * @param string $ratio   Default '4 / 3'.
 * @param int    $lrti_id Trailer ID.
 */
$lrti_aspect = (string) apply_filters( 'lrti_gallery_aspect_ratio', '4 / 3', $lrti_id );

/**
 * Filter the gallery image focal position (CSS object-position value).
 *
 * @param string $position Default 'center center'.
 * @param int    $lrti_id  Trailer ID.
 */
$lrti_obj_pos = (string) apply_filters( 'lrti_gallery_image_position', 'center center', $lrti_id );

/**
 * Filter the main (stage/thumb) image size.
 *
 * @param string $size Default 'large'.
 */
$lrti_main_size = (string) apply_filters( 'lrti_gallery_main_image_size', 'large' );

/**
 * Filter the lightbox (full-size) image size.
 *
 * @param string $size Default 'full'.
 */
$lrti_light_size = (string) apply_filters( 'lrti_gallery_lightbox_image_size', 'full' );

$lrti_stage_style = sprintf(
	'--lrti-gallery-aspect:%1$s;--lrti-gallery-pos:%2$s;',
	esc_attr( $lrti_aspect ),
	esc_attr( $lrti_obj_pos )
);

do_action( 'lrti_before_single_gallery', $lrti_id );
do_action( 'lrti_before_trailer_gallery', $lrti_id );

if ( empty( $lrti_ids ) ) :
	?>
	<div class="lrti-gallery-single lrti-gallery-empty" style="<?php echo esc_attr( $lrti_stage_style ); ?>">
		<?php echo lrti_placeholder_image(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped SVG. ?>
	</div>
	<?php
	do_action( 'lrti_after_trailer_gallery', $lrti_id );
	do_action( 'lrti_after_single_gallery', $lrti_id );
	return;
endif;

$lrti_first     = (int) $lrti_ids[0];
$lrti_stage_alt = trim( (string) get_post_meta( $lrti_first, '_wp_attachment_image_alt', true ) );
if ( '' === $lrti_stage_alt ) {
	$lrti_stage_alt = get_the_title( $lrti_id );
}
$lrti_first_full  = (string) wp_get_attachment_image_url( $lrti_first, $lrti_light_size );
$lrti_first_stage = (string) wp_get_attachment_image_url( $lrti_first, $lrti_main_size );
$lrti_multi       = count( $lrti_ids ) > 1;
?>
<div class="lrti-gallery-single" id="lrti-gallery" data-count="<?php echo esc_attr( (string) count( $lrti_ids ) ); ?>">

	<div class="lrti-gallery-stage" style="<?php echo esc_attr( $lrti_stage_style ); ?>">
		<button type="button" class="lrti-gallery-open" id="lrti-gallery-open" aria-label="<?php esc_attr_e( 'View full-size image', 'little-river-trailer-inventory' ); ?>">
			<?php
			echo wp_get_attachment_image(
				$lrti_first,
				$lrti_main_size,
				false,
				array(
					'id'            => 'lrti-stage-img',
					'class'         => 'lrti-stage-img',
					'alt'           => $lrti_stage_alt,
					'loading'       => 'eager',
					'fetchpriority' => 'high',
					'decoding'      => 'async',
					'data-full'     => $lrti_first_full,
					'data-stage'    => $lrti_first_stage,
				)
			);
			?>
		</button>

		<?php if ( $lrti_multi ) : ?>
			<button type="button" class="lrti-gallery-nav lrti-gallery-prev" id="lrti-gallery-prev" aria-label="<?php esc_attr_e( 'Previous trailer image', 'little-river-trailer-inventory' ); ?>">&lsaquo;</button>
			<button type="button" class="lrti-gallery-nav lrti-gallery-next" id="lrti-gallery-next" aria-label="<?php esc_attr_e( 'Next trailer image', 'little-river-trailer-inventory' ); ?>">&rsaquo;</button>
		<?php endif; ?>
	</div>

	<?php if ( $lrti_multi ) : ?>
		<ul class="lrti-gallery-thumbs" role="list">
			<?php
			foreach ( $lrti_ids as $lrti_index => $lrti_att_id ) :
				$lrti_att_id = (int) $lrti_att_id;
				$lrti_full   = wp_get_attachment_image_url( $lrti_att_id, $lrti_light_size );
				$lrti_stage  = wp_get_attachment_image_url( $lrti_att_id, $lrti_main_size );
				$lrti_alt    = trim( (string) get_post_meta( $lrti_att_id, '_wp_attachment_image_alt', true ) );
				if ( '' === $lrti_alt ) {
					$lrti_alt = get_the_title( $lrti_id );
				}
				?>
				<li class="lrti-gallery-thumb-item">
					<button
						type="button"
						class="lrti-gallery-thumb<?php echo 0 === $lrti_index ? ' is-active' : ''; ?>"
						<?php echo 0 === $lrti_index ? 'aria-current="true"' : ''; ?>
						data-full="<?php echo esc_url( (string) $lrti_full ); ?>"
						data-stage="<?php echo esc_url( (string) $lrti_stage ); ?>"
						data-index="<?php echo esc_attr( (string) $lrti_index ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %d: image number */ __( 'Show image %d', 'little-river-trailer-inventory' ), $lrti_index + 1 ) ); ?>"
					>
						<?php
						echo wp_get_attachment_image(
							$lrti_att_id,
							'thumbnail',
							false,
							array(
								'class'   => 'lrti-thumb-img',
								'alt'     => $lrti_alt,
								'loading' => 'lazy',
							)
						);
						?>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>

<div class="lrti-lightbox" id="lrti-lightbox" hidden>
	<div class="lrti-lightbox-inner" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Image viewer', 'little-river-trailer-inventory' ); ?>">
		<button type="button" class="lrti-lightbox-close" id="lrti-lightbox-close" aria-label="<?php esc_attr_e( 'Close', 'little-river-trailer-inventory' ); ?>">&times;</button>
		<?php if ( $lrti_multi ) : ?>
			<button type="button" class="lrti-lightbox-nav lrti-lightbox-prev" id="lrti-lightbox-prev" aria-label="<?php esc_attr_e( 'Previous trailer image', 'little-river-trailer-inventory' ); ?>">&lsaquo;</button>
		<?php endif; ?>
		<img src="" alt="" class="lrti-lightbox-img" id="lrti-lightbox-img" />
		<?php if ( $lrti_multi ) : ?>
			<button type="button" class="lrti-lightbox-nav lrti-lightbox-next" id="lrti-lightbox-next" aria-label="<?php esc_attr_e( 'Next trailer image', 'little-river-trailer-inventory' ); ?>">&rsaquo;</button>
		<?php endif; ?>
	</div>
</div>
<?php
do_action( 'lrti_after_trailer_gallery', $lrti_id );
do_action( 'lrti_after_single_gallery', $lrti_id );
