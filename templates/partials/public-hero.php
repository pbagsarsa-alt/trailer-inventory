<?php
/**
 * Shared public hero band (Sprint 2.9.8).
 *
 * Rendered via twc_render_public_hero(); receives $twc_hero. Do not call
 * directly. Theme override:
 *   wp-content/themes/your-theme/little-river-trailer-inventory/partials/public-hero.php
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $twc_hero ) || ! is_array( $twc_hero ) ) {
	return;
}

$twc_variant     = (string) ( $twc_hero['variant'] ?? '' );
$twc_breadcrumbs = is_array( $twc_hero['breadcrumbs'] ?? null ) ? $twc_hero['breadcrumbs'] : array();
$twc_title       = (string) ( $twc_hero['title'] ?? '' );
$twc_description  = (string) ( $twc_hero['description'] ?? '' );
$twc_meta        = is_array( $twc_hero['meta'] ?? null ) ? $twc_hero['meta'] : array();
$twc_badges      = is_array( $twc_hero['badges'] ?? null ) ? $twc_hero['badges'] : array();
?>
<section class="lrti-hero<?php echo $twc_variant ? ' lrti-hero--' . esc_attr( $twc_variant ) : ''; ?>">
	<div class="lrti-container lrti-hero-inner">
		<div class="lrti-hero-body">
			<?php if ( ! empty( $twc_breadcrumbs ) ) : ?>
				<nav class="lrti-hero-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'little-river-trailer-inventory' ); ?>">
					<ol class="lrti-hero-crumbs">
						<?php
						$twc_last = count( $twc_breadcrumbs ) - 1;
						foreach ( $twc_breadcrumbs as $twc_i => $twc_crumb ) :
							$twc_c_label = (string) ( $twc_crumb['label'] ?? '' );
							$twc_c_url   = (string) ( $twc_crumb['url'] ?? '' );
							if ( '' === $twc_c_label ) {
								continue;
							}
							?>
							<li class="lrti-hero-crumb">
								<?php if ( '' !== $twc_c_url && $twc_i !== $twc_last ) : ?>
									<a href="<?php echo esc_url( $twc_c_url ); ?>"><?php echo esc_html( $twc_c_label ); ?></a>
									<span class="lrti-hero-crumb-sep" aria-hidden="true">&rsaquo;</span>
								<?php else : ?>
									<span aria-current="page"><?php echo esc_html( $twc_c_label ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				</nav>
			<?php endif; ?>

			<h1 class="lrti-hero-title"><?php echo esc_html( $twc_title ); ?></h1>

			<?php if ( '' !== trim( $twc_description ) ) : ?>
				<p class="lrti-hero-desc"><?php echo esc_html( $twc_description ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $twc_meta ) ) : ?>
				<ul class="lrti-hero-meta">
					<?php
					foreach ( $twc_meta as $twc_m ) :
						$twc_m_label = (string) ( $twc_m['label'] ?? '' );
						$twc_m_value = (string) ( $twc_m['value'] ?? '' );
						if ( '' === trim( $twc_m_value ) ) {
							continue; // Never show empty labels.
						}
						?>
						<li class="lrti-hero-meta-item">
							<?php if ( '' !== $twc_m_label ) : ?>
								<span class="lrti-hero-meta-label"><?php echo esc_html( $twc_m_label ); ?></span>
							<?php endif; ?>
							<span class="lrti-hero-meta-value"><?php echo esc_html( $twc_m_value ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $twc_badges ) ) : ?>
			<div class="lrti-hero-badges">
				<?php
				foreach ( $twc_badges as $twc_b ) :
					$twc_b_label = (string) ( $twc_b['label'] ?? '' );
					if ( '' === $twc_b_label ) {
						continue;
					}
					$twc_b_class = (string) ( $twc_b['class'] ?? '' );
					?>
					<span class="lrti-badge <?php echo esc_attr( $twc_b_class ); ?>"><?php echo esc_html( $twc_b_label ); ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
