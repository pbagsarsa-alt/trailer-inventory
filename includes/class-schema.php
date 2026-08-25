<?php
/**
 * JSON-LD structured data for single trailer pages (Sprint 4.4).
 *
 * Emits a Product (+ Offer), Organization, and BreadcrumbList graph using ONLY
 * verified, saved data. Nothing is fabricated: SKU comes from Stock Number,
 * brand from the Manufacturer term, price from saved price (respecting Call For
 * Price / Hide Price / Sold), availability/condition from the saved terms. No
 * ratings or reviews. Output is gated to avoid duplicating another SEO plugin's
 * schema, and the whole graph is filterable.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schema
 */
final class Schema {

	/**
	 * Attach hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_head', array( $this, 'output' ), 20 );
	}

	/**
	 * Should we output schema? Off when a supported SEO plugin is active
	 * (to avoid duplicates) or when disabled via setting/filter.
	 *
	 * @return bool
	 */
	private function enabled(): bool {
		$enabled = ! Seo::seo_plugin_active();

		// Honor an explicit setting if present (defaults to enabled).
		$setting = lrti_get_setting( 'enable_schema', '1' );
		if ( '0' === (string) $setting ) {
			$enabled = false;
		}

		/**
		 * Filter whether the plugin outputs JSON-LD on single trailers.
		 *
		 * @param bool $enabled Whether schema output is enabled.
		 */
		return (bool) apply_filters( 'lrti_output_schema', $enabled );
	}

	/**
	 * Map an availability term slug to a schema.org availability URL.
	 *
	 * @param string|null $slug Availability term slug.
	 * @return string
	 */
	private function availability_url( ?string $slug ): string {
		$map = array(
			'in-stock'      => 'https://schema.org/InStock',
			'sale-pending'  => 'https://schema.org/LimitedAvailability',
			'coming-soon'   => 'https://schema.org/PreOrder',
			'sold'          => 'https://schema.org/SoldOut',
		);
		return ( $slug && isset( $map[ $slug ] ) ) ? $map[ $slug ] : '';
	}

	/**
	 * Map a condition term slug to a schema.org condition URL.
	 *
	 * @param string $slug Condition term slug.
	 * @return string
	 */
	private function condition_url( string $slug ): string {
		$map = array(
			'new'  => 'https://schema.org/NewCondition',
			'used' => 'https://schema.org/UsedCondition',
		);
		return isset( $map[ $slug ] ) ? $map[ $slug ] : '';
	}

	/**
	 * Build the Organization node from dealership settings.
	 *
	 * @return array<string, mixed>
	 */
	private function organization_node(): array {
		$name  = (string) lrti_get_setting( 'dealership_name', '' );
		$phone = (string) lrti_get_setting( 'dealership_phone', '' );
		$email = (string) lrti_get_setting( 'dealership_email', '' );
		$addr  = (string) lrti_get_setting( 'dealership_address', '' );

		$node = array(
			'@type' => 'Organization',
			'@id'   => home_url( '/#organization' ),
			'name'  => $name,
			'url'   => home_url( '/' ),
		);
		if ( '' !== $phone ) {
			$node['telephone'] = $phone;
		}
		if ( '' !== $email ) {
			$node['email'] = $email;
		}
		if ( '' !== $addr ) {
			$node['address'] = array(
				'@type'          => 'PostalAddress',
				'streetAddress'  => $addr,
			);
		}
		return $node;
	}

	/**
	 * Build the BreadcrumbList node.
	 *
	 * @param int $post_id Trailer post ID.
	 * @return array<string, mixed>
	 */
	private function breadcrumb_node( int $post_id ): array {
		$items    = lrti_breadcrumb_items( $post_id );
		$elements = array();
		$position = 1;

		foreach ( $items as $crumb ) {
			$entry = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => (string) ( $crumb['label'] ?? '' ),
			);
			if ( ! empty( $crumb['url'] ) ) {
				$entry['item'] = (string) $crumb['url'];
			}
			$elements[] = $entry;
			$position++;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $elements,
		);
	}

	/**
	 * Build the Product (+ Offer) node using only verified data.
	 *
	 * @param int $post_id Trailer post ID.
	 * @return array<string, mixed>
	 */
	private function product_node( int $post_id ): array {
		$node = array(
			'@type' => 'Product',
			'@id'   => get_permalink( $post_id ) . '#product',
			'name'  => get_the_title( $post_id ),
			'url'   => get_permalink( $post_id ),
		);

		$description = wp_strip_all_tags( (string) get_the_excerpt( $post_id ) );
		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		// Image (only when one exists).
		$thumb = get_post_thumbnail_id( $post_id );
		if ( $thumb ) {
			$img = wp_get_attachment_image_url( (int) $thumb, 'large' );
			if ( $img ) {
				$node['image'] = array( (string) $img );
			}
		}

		// SKU from Stock Number (only when entered).
		$stock = (string) lrti_get_trailer_meta( $post_id, 'stock_number', '' );
		if ( '' !== $stock ) {
			$node['sku'] = $stock;
		}

		// Brand from Manufacturer (only when assigned).
		$manufacturer = lrti_first_term_name( $post_id, 'trailer_manufacturer' );
		if ( '' !== $manufacturer ) {
			$node['brand'] = array(
				'@type' => 'Brand',
				'name'  => $manufacturer,
			);
		}

		// MPN from Model (only when entered).
		$model = (string) lrti_get_trailer_meta( $post_id, 'model', '' );
		if ( '' !== $model ) {
			$node['mpn'] = $model;
		}

		// Condition (only when a New/Used term is assigned).
		$cond_terms = get_the_terms( $post_id, 'trailer_condition' );
		if ( ! empty( $cond_terms ) && ! is_wp_error( $cond_terms ) ) {
			$cond_url = $this->condition_url( (string) $cond_terms[0]->slug );
			if ( '' !== $cond_url ) {
				$node['itemCondition'] = $cond_url;
			}
		}

		// Offer — respect Call For Price / Hide Price / Sold.
		$price = lrti_get_price_data( $post_id );
		$avail = lrti_availability( $post_id );
		$offer = array(
			'@type'     => 'Offer',
			'url'       => get_permalink( $post_id ),
			'seller'    => array( '@id' => home_url( '/#organization' ) ),
		);

		$currency = (string) lrti_get_setting( 'currency_code', 'USD' );
		$offer['priceCurrency'] = $currency;

		$show_price = ( 'hide' !== $price['state'] && 'call' !== $price['state'] );
		if ( $show_price ) {
			$amount = ( '' !== $price['sale'] ) ? $price['sale'] : $price['regular'];
			if ( '' !== $amount ) {
				$offer['price'] = (string) ( 0 + (float) $amount );
			}
		}

		$avail_url = $this->availability_url( $avail ? $avail['slug'] : null );
		if ( '' !== $avail_url ) {
			$offer['availability'] = $avail_url;
		}

		// Only attach the Offer if it carries real, useful information.
		if ( isset( $offer['price'] ) || isset( $offer['availability'] ) ) {
			$node['offers'] = $offer;
		}

		return $node;
	}

	/**
	 * Output the JSON-LD graph.
	 *
	 * @return void
	 */
	public function output(): void {
		if ( ! $this->enabled() ) {
			return;
		}

		if ( is_singular( PostTypes::POST_TYPE ) ) {
			$this->output_single();
			return;
		}

		$tax = array( 'trailer_manufacturer', 'trailer_type', 'trailer_condition', 'trailer_availability', 'trailer_feature' );
		if ( is_post_type_archive( PostTypes::POST_TYPE ) || is_tax( $tax ) ) {
			$this->output_archive();
		}
	}

	/**
	 * Output the single-trailer JSON-LD graph.
	 *
	 * @return void
	 */
	private function output_single(): void {
		$post_id = (int) get_queried_object_id();

		do_action( 'lrti_before_single_schema', $post_id );

		$graph = array(
			$this->organization_node(),
			$this->product_node( $post_id ),
			$this->breadcrumb_node( $post_id ),
		);

		/**
		 * Filter the full JSON-LD @graph before output.
		 *
		 * @param array<int, array<string, mixed>> $graph   The graph nodes.
		 * @param int                              $post_id Trailer post ID.
		 */
		$graph = (array) apply_filters( 'lrti_schema_graph', $graph, $post_id );

		if ( ! empty( $graph ) ) {
			$payload = array(
				'@context' => 'https://schema.org',
				'@graph'   => array_values( $graph ),
			);
			echo '<script type="application/ld+json">' . wp_json_encode( $payload ) . '</script>' . "\n";
		}

		do_action( 'lrti_after_single_schema', $post_id );
	}

	/**
	 * Output CollectionPage + ItemList (+ Product/Offer per card) + Breadcrumb
	 * for the inventory archive and trailer taxonomy archives.
	 *
	 * @return void
	 */
	private function output_archive(): void {
		global $wp_query;

		$posts = ( $wp_query instanceof \WP_Query ) ? (array) $wp_query->posts : array();
		if ( empty( $posts ) ) {
			return;
		}

		$obj   = get_queried_object();
		$title = is_post_type_archive( PostTypes::POST_TYPE )
			? post_type_archive_title( '', false )
			: ( ( $obj instanceof \WP_Term ) ? $obj->name : get_the_title() );

		$current_url = home_url( add_query_arg( array() ) );

		$items = array();
		$pos   = 1;
		foreach ( $posts as $p ) {
			if ( ! ( $p instanceof \WP_Post ) ) {
				continue;
			}
			$product = $this->product_node( (int) $p->ID );
			// product_node returns a Product array; embed it as the list item.
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'url'      => (string) get_permalink( $p ),
				'item'     => $product,
			);
			++$pos;
		}

		// Breadcrumb: Home › Inventory [› Term].
		$crumbs   = array();
		$crumbs[] = array(
			'@type'    => 'ListItem',
			'position' => 1,
			'name'     => __( 'Home', 'little-river-trailer-inventory' ),
			'item'     => home_url( '/' ),
		);
		$archive_link = (string) get_post_type_archive_link( PostTypes::POST_TYPE );
		$crumbs[]     = array(
			'@type'    => 'ListItem',
			'position' => 2,
			'name'     => post_type_archive_title( '', false ),
			'item'     => $archive_link,
		);
		if ( $obj instanceof \WP_Term ) {
			$crumbs[] = array(
				'@type'    => 'ListItem',
				'position' => 3,
				'name'     => $obj->name,
				'item'     => (string) get_term_link( $obj ),
			);
		}

		$graph = array(
			$this->organization_node(),
			array(
				'@type' => 'CollectionPage',
				'name'  => (string) $title,
				'url'   => $current_url,
			),
			array(
				'@type'           => 'ItemList',
				'numberOfItems'   => count( $items ),
				'itemListElement' => $items,
			),
			array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $crumbs,
			),
		);

		/**
		 * Filter the archive JSON-LD @graph before output.
		 *
		 * @param array<int, array<string, mixed>> $graph The graph nodes.
		 */
		$graph = (array) apply_filters( 'lrti_schema_archive_graph', $graph );

		$payload = array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $graph ),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $payload ) . '</script>' . "\n";
	}
}
