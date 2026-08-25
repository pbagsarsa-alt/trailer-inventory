<?php
/**
 * Custom post type registration and admin list-table columns.
 *
 * This class creates the "trailer" post type. A "post type" is simply a kind of
 * content in WordPress. Posts and Pages are built-in post types; "trailer" is a
 * custom one we invent so each trailer is its own editable item with its own
 * public URL under /inventory/.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostTypes
 */
final class PostTypes {

	/**
	 * The post type key. Keep it short and unique.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'trailer';

	/**
	 * The URL base for trailers, giving us /inventory/ and /inventory/{name}/.
	 *
	 * @var string
	 */
	public const REWRITE_SLUG = 'inventory';

	/**
	 * Attach WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );

		// Admin list-table columns for the "All Trailers" screen.
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'set_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * Register the "trailer" post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'                  => _x( 'Trailers', 'Post type general name', 'little-river-trailer-inventory' ),
			'singular_name'         => _x( 'Trailer', 'Post type singular name', 'little-river-trailer-inventory' ),
			'menu_name'             => _x( 'Trailers', 'Admin Menu text', 'little-river-trailer-inventory' ),
			'add_new'               => __( 'Add New Trailer', 'little-river-trailer-inventory' ),
			'add_new_item'          => __( 'Add New Trailer', 'little-river-trailer-inventory' ),
			'edit_item'             => __( 'Edit Trailer', 'little-river-trailer-inventory' ),
			'new_item'              => __( 'New Trailer', 'little-river-trailer-inventory' ),
			'view_item'             => __( 'View Trailer', 'little-river-trailer-inventory' ),
			'view_items'            => __( 'View Trailers', 'little-river-trailer-inventory' ),
			'search_items'          => __( 'Search Trailers', 'little-river-trailer-inventory' ),
			'not_found'             => __( 'No trailers found.', 'little-river-trailer-inventory' ),
			'not_found_in_trash'    => __( 'No trailers found in Trash.', 'little-river-trailer-inventory' ),
			'all_items'             => __( 'All Trailers', 'little-river-trailer-inventory' ),
			'featured_image'        => __( 'Main Trailer Image', 'little-river-trailer-inventory' ),
			'set_featured_image'    => __( 'Set main trailer image', 'little-river-trailer-inventory' ),
			'remove_featured_image' => __( 'Remove main trailer image', 'little-river-trailer-inventory' ),
			'use_featured_image'    => __( 'Use as main trailer image', 'little-river-trailer-inventory' ),
			'item_published'        => __( 'Trailer published.', 'little-river-trailer-inventory' ),
			'item_updated'          => __( 'Trailer updated.', 'little-river-trailer-inventory' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'show_ui'            => true,
			// We add our own submenu links under "Trailer Inventory", so we do
			// NOT let WordPress auto-place this post type in its own top-level
			// menu.
			'show_in_menu'       => false,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => true, // Enables the block editor.
			'menu_icon'          => 'dashicons-car',
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'hierarchical'       => false,
			'has_archive'        => self::REWRITE_SLUG, // Archive at /inventory/.
			'rewrite'            => array(
				'slug'       => self::REWRITE_SLUG,     // Singles at /inventory/{name}/.
				'with_front' => false,
			),
			'supports'           => array(
				'title',
				'editor',
				'excerpt',
				'thumbnail', // Featured (main) image support.
				'revisions',
			),
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Define the columns shown on the "All Trailers" admin screen.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Modified columns.
	 */
	public function set_columns( array $columns ): array {
		$new = array();

		// Keep the checkbox column first if present.
		if ( isset( $columns['cb'] ) ) {
			$new['cb'] = $columns['cb'];
		}

		$new['lrti_thumb']        = __( 'Image', 'little-river-trailer-inventory' );
		$new['title']             = __( 'Trailer', 'little-river-trailer-inventory' );
		$new['lrti_stock']        = __( 'Stock #', 'little-river-trailer-inventory' );
		$new['lrti_manufacturer'] = __( 'Manufacturer', 'little-river-trailer-inventory' );
		$new['lrti_type']         = __( 'Type', 'little-river-trailer-inventory' );
		$new['lrti_price']        = __( 'Price', 'little-river-trailer-inventory' );
		$new['lrti_availability'] = __( 'Availability', 'little-river-trailer-inventory' );
		$new['date']              = isset( $columns['date'] ) ? $columns['date'] : __( 'Date', 'little-river-trailer-inventory' );

		return $new;
	}

	/**
	 * Render the content of each custom column for a given trailer.
	 *
	 * @param string $column  The column key.
	 * @param int    $post_id The trailer post ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'lrti_thumb':
				if ( has_post_thumbnail( $post_id ) ) {
					echo get_the_post_thumbnail(
						$post_id,
						array( 60, 60 ),
						array( 'style' => 'width:60px;height:60px;object-fit:cover;border-radius:3px;' )
					);
				} else {
					echo '<span class="lrti-no-thumb" aria-hidden="true">—</span>';
					echo '<span class="screen-reader-text">' . esc_html__( 'No image', 'little-river-trailer-inventory' ) . '</span>';
				}
				break;

			case 'lrti_stock':
				$stock = lrti_get_trailer_meta( $post_id, 'stock_number', '' );
				echo '' !== $stock ? esc_html( (string) $stock ) : '—';
				break;

			case 'lrti_manufacturer':
				echo esc_html( $this->term_list( $post_id, 'trailer_manufacturer' ) );
				break;

			case 'lrti_type':
				echo esc_html( $this->term_list( $post_id, 'trailer_type' ) );
				break;

			case 'lrti_price':
				echo esc_html( lrti_get_price_label( $post_id ) );
				break;

			case 'lrti_availability':
				echo esc_html( $this->term_list( $post_id, 'trailer_availability' ) );
				break;
		}
	}

	/**
	 * Return a comma-separated list of term names for a taxonomy, or a dash.
	 *
	 * @param int    $post_id  The trailer post ID.
	 * @param string $taxonomy The taxonomy key.
	 * @return string
	 */
	private function term_list( int $post_id, string $taxonomy ): string {
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '—';
		}

		$names = wp_list_pluck( $terms, 'name' );

		return implode( ', ', $names );
	}
}
