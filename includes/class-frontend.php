<?php
/**
 * Front-end asset loader.
 *
 * Loads inventory styles/scripts only where needed: the trailer archive, single
 * trailer views, and any page whose content contains one of our shortcodes. The
 * filter CSS/JS and AJAX data are added on the archive and on shortcode pages.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Frontend
 */
final class Frontend {

	/**
	 * Attach hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Does the current singular post contain any of our shortcodes?
	 *
	 * @return bool
	 */
	private function has_inventory_shortcode(): bool {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		foreach ( array( 'trailer_inventory', 'featured_trailers', 'trailer_search', 'trailer_filters', 'little_river_inventory', 'little_river_featured_trailers', 'little_river_inventory_search', 'lrti_featured_inventory', 'lrti_inventory_cards' ) as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Does the current singular post contain the inquiry shortcode?
	 *
	 * @return bool
	 */
	private function has_inquiry_shortcode(): bool {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'trailer_inquiry' ) || has_shortcode( $post->post_content, 'lrti_contact_form' );
	}

	/**
	 * Enqueue front-end assets conditionally.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		$is_archive   = is_post_type_archive( PostTypes::POST_TYPE );
		$is_single    = is_singular( PostTypes::POST_TYPE );
		$is_type_tax  = is_tax( 'trailer_type' );
		$is_shortcode = $this->has_inventory_shortcode();
		$is_inquiry   = $this->has_inquiry_shortcode();

		// Inquiry forms may appear on a single trailer page or anywhere the
		// [trailer_inquiry] shortcode is used — load those assets accordingly.
		$forms_enabled = ( '1' === (string) lrti_get_setting( 'enable_inquiry_forms', '1' ) );
		if ( $forms_enabled && ( $is_single || $is_inquiry ) ) {
			wp_enqueue_style(
				'lrti-inquiry',
				LRTI_PLUGIN_URL . 'public/css/inquiry.css',
				array( 'lrti-inventory' ),
				LRTI_VERSION
			);
			wp_enqueue_script(
				'lrti-inquiry',
				LRTI_PLUGIN_URL . 'public/js/inquiry.js',
				array(),
				LRTI_VERSION,
				true
			);
			wp_localize_script(
				'lrti-inquiry',
				'lrtiInquiry',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'lrti_inquiry' ),
					'action'  => 'lrti_submit_inquiry',
					'i18n'    => array(
						'loading' => __( 'Sending…', 'little-river-trailer-inventory' ),
						'success' => __( 'Thank you. Your inquiry has been received.', 'little-river-trailer-inventory' ),
						'error'   => __( 'Please review the highlighted fields and try again.', 'little-river-trailer-inventory' ),
					),
				)
			);
		}

		if ( ! $is_archive && ! $is_single && ! $is_shortcode && ! $is_inquiry && ! $is_type_tax ) {
			return;
		}

		// Shared card/grid styles.
		wp_enqueue_style(
			'lrti-inventory',
			LRTI_PLUGIN_URL . 'public/css/inventory.css',
			array(),
			LRTI_VERSION
		);

		// Filtering UI (archive + shortcode pages). The trailer-type taxonomy
		// archive reuses the results region styling but sorts via GET (no AJAX
		// sidebar), so it needs the CSS but not the filter script.
		if ( $is_archive || $is_shortcode || $is_type_tax ) {
			wp_enqueue_style(
				'lrti-filters',
				LRTI_PLUGIN_URL . 'public/css/filters.css',
				array( 'lrti-inventory' ),
				LRTI_VERSION
			);
		}

		if ( $is_archive || $is_shortcode ) {
			wp_enqueue_script(
				'lrti-inventory-filter',
				LRTI_PLUGIN_URL . 'public/js/inventory-filter.js',
				array(),
				LRTI_VERSION,
				true
			);

			wp_localize_script(
				'lrti-inventory-filter',
				'lrtiFilter',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( Ajax::nonce_action() ),
					'action'  => 'lrti_filter_inventory',
					'specFields' => \LRTI\Filters::spec_param_names(),
					'i18n'    => array(
						'loading'     => __( 'Loading…', 'little-river-trailer-inventory' ),
						'updated'     => __( 'Results updated.', 'little-river-trailer-inventory' ),
						'error'       => __( 'Something went wrong. Please try again.', 'little-river-trailer-inventory' ),
						'showFilters' => __( 'Show Filters', 'little-river-trailer-inventory' ),
						'hideFilters' => __( 'Hide Filters', 'little-river-trailer-inventory' ),
					),
				)
			);
		}

		// The legacy sort-only script is superseded by the filter script on the
		// archive; keep loading nothing extra there.

		if ( $is_single ) {
			wp_enqueue_style(
				'lrti-single',
				LRTI_PLUGIN_URL . 'public/css/single.css',
				array( 'lrti-inventory' ),
				LRTI_VERSION
			);
			wp_enqueue_script(
				'lrti-single',
				LRTI_PLUGIN_URL . 'public/js/single.js',
				array(),
				LRTI_VERSION,
				true
			);
		}
	}
}
