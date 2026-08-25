<?php
/**
 * Shortcodes (Sprint 4.3).
 *
 * [trailer_inventory]  Full inventory grid, optional built-in filters.
 * [featured_trailers]  A simple grid of featured trailers.
 * [trailer_search]     A compact keyword search box that links to /inventory/.
 * [trailer_filters]    A standalone filter sidebar (advanced; pairs with an
 *                      inventory instance via matching id/target).
 *
 * All rendering reuses the shared Filters engine and the card template.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shortcodes
 */
final class Shortcodes {

	/**
	 * Shared filters engine.
	 *
	 * @var Filters
	 */
	private Filters $filters;

	/**
	 * Instance counter for auto IDs.
	 *
	 * @var int
	 */
	private int $counter = 0;

	/**
	 * Constructor.
	 *
	 * @param Filters $filters The filters engine.
	 */
	public function __construct( Filters $filters ) {
		$this->filters = $filters;
	}

	/**
	 * Attach hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_shortcode( 'trailer_inventory', array( $this, 'inventory' ) );
		add_shortcode( 'featured_trailers', array( $this, 'featured' ) );
		add_shortcode( 'trailer_search', array( $this, 'search' ) );
		add_shortcode( 'trailer_filters', array( $this, 'filters_only' ) );

		// Back-compat aliases matching the original project brief.
		add_shortcode( 'little_river_inventory', array( $this, 'inventory' ) );
		add_shortcode( 'little_river_featured_trailers', array( $this, 'featured' ) );
		add_shortcode( 'little_river_inventory_search', array( $this, 'search' ) );

		// Sprint 1.10.0: featured-inventory section + reusable card grid.
		add_shortcode( 'lrti_featured_inventory', array( $this, 'featured_inventory' ) );
		add_shortcode( 'lrti_inventory_cards', array( $this, 'inventory_cards' ) );
	}

	/**
	 * Map a public orderby/order pair to the engine's sort token.
	 *
	 * @param string $orderby One of date|title|price|year|random|menu_order.
	 * @param string $order   ASC or DESC.
	 * @return string Engine sort token.
	 */
	private function map_sort( string $orderby, string $order ): string {
		$order   = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';
		$orderby = strtolower( $orderby );
		switch ( $orderby ) {
			case 'price':
				return 'ASC' === $order ? 'price_low' : 'price_high';
			case 'year':
				return 'ASC' === $order ? 'year_old' : 'year_new';
			case 'title':
				return 'ASC' === $order ? 'manufacturer_az' : 'manufacturer_za';
			case 'date':
			default:
				return 'ASC' === $order ? 'oldest' : 'newest';
		}
	}

	/**
	 * [lrti_featured_inventory] — a branded featured-inventory section with an
	 * eyebrow, heading, and a responsive card grid. Reuses the archive card.
	 *
	 * @param array<string, mixed>|string $atts Attributes.
	 * @return string
	 */
	public function featured_inventory( $atts ): string {
		$atts = shortcode_atts(
			array(
				'limit'        => '12',
				'columns'      => '4',
				'orderby'      => 'date',
				'order'        => 'DESC',
				'carousel'     => 'yes',
				'randomize'    => 'yes',
				'interval'     => '4500',
				'show_heading' => 'yes',
				'eyebrow'      => __( 'Featured Inventory', 'little-river-trailer-inventory' ),
				'heading'      => __( 'Explore Our Latest Trailers', 'little-river-trailer-inventory' ),
				'show_price'   => 'yes',
				'show_stock'   => 'no',
				'show_badges'  => 'yes',
				'image_size'   => 'large',
				'button_text'  => __( 'View Details', 'little-river-trailer-inventory' ),
				'new_tab'      => 'yes',
				'class'        => '',
				'type'         => '',
				'manufacturer' => '',
				'id'           => '',
			),
			$atts,
			'lrti_featured_inventory'
		);

		$base = array( 'featured' => '1' );
		return $this->render_card_section( $atts, $base, true );
	}

	/**
	 * [lrti_inventory_cards] — the same card grid with broader query control.
	 *
	 * @param array<string, mixed>|string $atts Attributes.
	 * @return string
	 */
	public function inventory_cards( $atts ): string {
		$atts = shortcode_atts(
			array(
				'featured'     => 'no',
				'type'         => '',
				'manufacturer' => '',
				'condition'    => '',
				'availability' => '',
				'limit'        => '4',
				'columns'      => '4',
				'orderby'      => 'date',
				'order'        => 'DESC',
				'show_heading' => 'no',
				'eyebrow'      => '',
				'heading'      => '',
				'show_price'   => 'yes',
				'show_stock'   => 'no',
				'show_badges'  => 'yes',
				'image_size'   => 'large',
				'button_text'  => __( 'View Details', 'little-river-trailer-inventory' ),
				'new_tab'      => 'no',
				'class'        => '',
				'id'           => '',
			),
			$atts,
			'lrti_inventory_cards'
		);

		$base = array();
		if ( $this->truthy( $atts['featured'] ) ) {
			$base['featured'] = '1';
		}
		if ( '' !== $atts['condition'] ) {
			$base['condition'] = sanitize_key( (string) $atts['condition'] );
		}
		if ( '' !== $atts['availability'] ) {
			$base['availability'] = sanitize_key( (string) $atts['availability'] );
		}

		return $this->render_card_section( $atts, $base, false );
	}

	/**
	 * Shared renderer for the featured section / inventory-card grid.
	 *
	 * @param array<string, mixed> $atts        Normalized attributes.
	 * @param array<string, mixed> $base         Base query parameters.
	 * @param bool                 $default_head Whether the heading shows by default.
	 * @return string
	 */
	private function render_card_section( array $atts, array $base, bool $default_head ): string {
		$instance = $this->instance_id( $atts );
		$columns  = max( 1, min( 4, (int) $atts['columns'] ) );
		$limit    = max( 1, (int) $atts['limit'] );

		$base['per_page'] = $limit;
		$base['sort']     = $this->map_sort( (string) $atts['orderby'], (string) $atts['order'] );

		if ( '' !== $atts['type'] ) {
			$base['type'] = sanitize_key( (string) $atts['type'] );
		}
		if ( '' !== $atts['manufacturer'] ) {
			$base['manufacturer'] = sanitize_key( (string) $atts['manufacturer'] );
		}
		// Default: exclude sold trailers from these public grids.
		if ( ! isset( $base['availability'] ) ) {
			$base['exclude_sold'] = '1';
		}

		$query = $this->filters->run_query( array(), $base );

		$lrti_carousel  = isset( $atts['carousel'] ) && $this->truthy( $atts['carousel'] );
		$lrti_randomize = isset( $atts['randomize'] ) && $this->truthy( $atts['randomize'] );
		$lrti_interval  = isset( $atts['interval'] ) ? max( 0, (int) $atts['interval'] ) : 4500;

		// Collect posts so we can optionally shuffle for a fresh order each load.
		$lrti_posts = $query->posts;

		if ( empty( $lrti_posts ) ) {
			wp_reset_postdata();
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="lrti-featured-empty">' . esc_html__( 'No featured trailers are currently available.', 'little-river-trailer-inventory' ) . '</p>';
			}
			return '';
		}

		if ( $lrti_randomize && count( $lrti_posts ) > 1 ) {
			shuffle( $lrti_posts );
		}

		// A carousel is only meaningful with more than one card.
		if ( $lrti_carousel && count( $lrti_posts ) < 2 ) {
			$lrti_carousel = false;
		}

		if ( $lrti_carousel ) {
			wp_enqueue_script(
				'lrti-featured-carousel',
				LRTI_PLUGIN_URL . 'public/js/featured-carousel.js',
				array(),
				LRTI_VERSION,
				true
			);
		}

		$show_heading = $default_head ? ! $this->falsy( $atts['show_heading'] ) : $this->truthy( $atts['show_heading'] );
		$wrap_class   = 'lrti-featured-section';
		if ( '' !== trim( (string) $atts['class'] ) ) {
			$wrap_class .= ' ' . sanitize_html_class( (string) $atts['class'] );
		}

		$lrti_new_tab   = isset( $atts['new_tab'] ) && $this->truthy( $atts['new_tab'] );
		$lrti_target_cb = null;
		if ( $lrti_new_tab ) {
			$lrti_target_cb = static function (): string {
				return 'target="_blank" rel="noopener noreferrer"';
			};
			add_filter( 'lrti_card_link_attributes', $lrti_target_cb );
		}

		// Render all cards (as carousel slides or grid items) from $lrti_posts.
		$lrti_render_cards = static function () use ( $lrti_posts, $lrti_carousel ): void {
			global $post;
			foreach ( $lrti_posts as $lrti_p ) {
				$post = $lrti_p; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
				setup_postdata( $post );
				if ( $lrti_carousel ) {
					echo '<div class="lrti-carousel-slide">';
				}
				lrti_get_template_part( 'content-trailer' );
				if ( $lrti_carousel ) {
					echo '</div>';
				}
			}
			wp_reset_postdata();
		};

		ob_start();
		?>
		<section class="lrti-inventory lrti-archive <?php echo esc_attr( $wrap_class ); ?>" id="lrti-inv-<?php echo esc_attr( $instance ); ?>">
			<?php if ( $show_heading ) : ?>
				<div class="lrti-featured-head">
					<?php if ( '' !== trim( (string) $atts['eyebrow'] ) ) : ?>
						<p class="lrti-featured-eyebrow"><?php echo esc_html( (string) $atts['eyebrow'] ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $atts['heading'] ) ) : ?>
						<h2 class="lrti-featured-heading"><?php echo esc_html( (string) $atts['heading'] ); ?></h2>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $lrti_carousel ) : ?>
				<div class="lrti-carousel" data-columns="<?php echo esc_attr( (string) $columns ); ?>" data-interval="<?php echo esc_attr( (string) $lrti_interval ); ?>">
					<div class="lrti-carousel-viewport">
						<div class="lrti-carousel-track">
							<?php $lrti_render_cards(); ?>
						</div>
					</div>
					<button type="button" class="lrti-carousel-nav lrti-carousel-prev" aria-label="<?php esc_attr_e( 'Previous trailers', 'little-river-trailer-inventory' ); ?>">
						<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</button>
					<button type="button" class="lrti-carousel-nav lrti-carousel-next" aria-label="<?php esc_attr_e( 'Next trailers', 'little-river-trailer-inventory' ); ?>">
						<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</button>
				</div>
			<?php else : ?>
				<div class="lrti-grid lrti-grid--cols-<?php echo esc_attr( (string) $columns ); ?>">
					<?php $lrti_render_cards(); ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
		if ( null !== $lrti_target_cb ) {
			remove_filter( 'lrti_card_link_attributes', $lrti_target_cb );
		}
		return (string) ob_get_clean();
	}

	/**
	 * Loose "no" test for yes/no attributes.
	 *
	 * @param mixed $value Attribute value.
	 * @return bool
	 */
	private function falsy( $value ): bool {
		return in_array( strtolower( (string) $value ), array( 'no', 'false', '0', 'off', '' ), true );
	}

	/**
	 * A unique instance id for a shortcode render.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	private function instance_id( array $atts ): string {
		if ( ! empty( $atts['id'] ) ) {
			return sanitize_key( (string) $atts['id'] );
		}
		$this->counter++;
		return 'sc' . $this->counter;
	}

	/**
	 * [trailer_inventory] — full grid with optional filters.
	 *
	 * Attributes: category|type, manufacturer, condition, availability, featured,
	 * sale, filters (yes/no), columns, per_page|limit, orderby|sort, id.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function inventory( $atts ): string {
		$atts = shortcode_atts(
			array(
				'category'     => '',
				'type'         => '',
				'manufacturer' => '',
				'condition'    => '',
				'availability' => '',
				'featured'     => '',
				'sale'         => '',
				'filters'      => 'yes',
				'columns'      => '3',
				'per_page'     => '',
				'limit'        => '',
				'orderby'      => '',
				'sort'         => '',
				'id'           => '',
			),
			$atts,
			'trailer_inventory'
		);

		$instance = $this->instance_id( $atts );
		$columns  = max( 1, min( 4, (int) $atts['columns'] ) );
		$per_page = (int) ( '' !== $atts['per_page'] ? $atts['per_page'] : ( '' !== $atts['limit'] ? $atts['limit'] : 0 ) );

		$base = array();
		$type = '' !== $atts['type'] ? $atts['type'] : $atts['category'];
		if ( '' !== $type ) {
			$base['type'] = sanitize_key( $type );
		}
		foreach ( array( 'manufacturer', 'condition', 'availability' ) as $k ) {
			if ( '' !== $atts[ $k ] ) {
				$base[ $k ] = sanitize_key( (string) $atts[ $k ] );
			}
		}
		if ( $this->truthy( $atts['featured'] ) ) {
			$base['featured'] = '1';
		}
		if ( $this->truthy( $atts['sale'] ) ) {
			$base['sale'] = '1';
		}
		if ( $per_page > 0 ) {
			$base['per_page'] = $per_page;
		}
		$sort = '' !== $atts['sort'] ? $atts['sort'] : $atts['orderby'];
		if ( '' !== $sort ) {
			$base['sort'] = sanitize_key( $sort );
		}

		$show_filters = $this->truthy( $atts['filters'] ) || 'yes' === strtolower( (string) $atts['filters'] );

		// Current filters come from the request (shared GET state).
		$request = $this->filters->parse_request( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
		$paged   = ( get_query_var( 'paged' ) ) ? (int) get_query_var( 'paged' ) : ( ( get_query_var( 'page' ) ) ? (int) get_query_var( 'page' ) : 1 );
		$base['paged'] = max( 1, $paged );

		ob_start();
		$this->filters->render_app( $instance, $base, $request, $show_filters, $columns, true );
		return (string) ob_get_clean();
	}

	/**
	 * [featured_trailers] — a simple grid of featured trailers.
	 *
	 * Attributes: limit, columns, manufacturer, type, id.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function featured( $atts ): string {
		$atts = shortcode_atts(
			array(
				'limit'        => '4',
				'columns'      => '4',
				'manufacturer' => '',
				'type'         => '',
				'id'           => '',
			),
			$atts,
			'featured_trailers'
		);

		$instance = $this->instance_id( $atts );
		$columns  = max( 1, min( 4, (int) $atts['columns'] ) );

		$base = array(
			'featured' => '1',
			'per_page' => max( 1, (int) $atts['limit'] ),
			'sort'     => 'newest',
		);
		if ( '' !== $atts['manufacturer'] ) {
			$base['manufacturer'] = sanitize_key( (string) $atts['manufacturer'] );
		}
		if ( '' !== $atts['type'] ) {
			$base['type'] = sanitize_key( (string) $atts['type'] );
		}

		$query = $this->filters->run_query( array(), $base );

		ob_start();
		?>
		<div class="lrti-inventory lrti-archive lrti-featured-shortcode" id="lrti-inv-<?php echo esc_attr( $instance ); ?>">
			<div class="lrti-inventory-main">
				<?php if ( $query->have_posts() ) : ?>
					<div class="lrti-grid lrti-grid--cols-<?php echo esc_attr( (string) $columns ); ?>">
						<?php
						while ( $query->have_posts() ) :
							$query->the_post();
							lrti_get_template_part( 'content-trailer' );
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				<?php else : ?>
					<p class="lrti-featured-empty"><?php esc_html_e( 'No featured trailers at this time.', 'little-river-trailer-inventory' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * [trailer_search] — a compact keyword search that submits to /inventory/.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function search( $atts ): string {
		$atts = shortcode_atts(
			array(
				'placeholder' => __( 'Search trailers…', 'little-river-trailer-inventory' ),
				'button'      => __( 'Search', 'little-river-trailer-inventory' ),
			),
			$atts,
			'trailer_search'
		);

		$action  = (string) get_post_type_archive_link( PostTypes::POST_TYPE );
		$keyword = isset( $_GET['keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['keyword'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		ob_start();
		?>
		<form class="lrti-search-shortcode" role="search" method="get" action="<?php echo esc_url( $action ); ?>">
			<label class="screen-reader-text" for="lrti-search-input"><?php esc_html_e( 'Search trailers', 'little-river-trailer-inventory' ); ?></label>
			<input type="search" id="lrti-search-input" class="lrti-input" name="keyword" value="<?php echo esc_attr( $keyword ); ?>" placeholder="<?php echo esc_attr( (string) $atts['placeholder'] ); ?>" />
			<button type="submit" class="lrti-btn lrti-btn--primary"><?php echo esc_html( (string) $atts['button'] ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * [trailer_filters] — a standalone filter sidebar. Pairs with an inventory
	 * instance whose id matches the "target" attribute (JS wires them together).
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function filters_only( $atts ): string {
		$atts = shortcode_atts(
			array(
				'target' => 'archive',
				'id'     => '',
			),
			$atts,
			'trailer_filters'
		);

		$instance = '' !== $atts['id'] ? sanitize_key( (string) $atts['id'] ) : sanitize_key( (string) $atts['target'] );
		$request  = $this->filters->parse_request( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		ob_start();
		?>
		<div class="lrti-inventory lrti-filters-standalone" data-instance="<?php echo esc_attr( $instance ); ?>" data-target="<?php echo esc_attr( sanitize_key( (string) $atts['target'] ) ); ?>">
			<?php $this->filters->render_sidebar( $instance, $request ); ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Loosely interpret a truthy attribute value.
	 *
	 * @param mixed $value The value.
	 * @return bool
	 */
	private function truthy( $value ): bool {
		return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
	}
}
