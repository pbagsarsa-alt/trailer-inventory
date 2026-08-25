<?php
/**
 * Shared helper functions.
 *
 * These are small, standalone functions used across the plugin. They are NOT
 * part of a class, so they are loaded directly by the main plugin file rather
 * than by the autoloader. Every function is prefixed with "lrti_" so it can
 * never clash with WordPress or another plugin.
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lrti_get_settings' ) ) {
	/**
	 * Retrieve the plugin settings array, merged with sensible defaults.
	 *
	 * @return array<string, mixed> The full settings array.
	 */
	function lrti_get_settings(): array {
		$defaults = lrti_get_default_settings();
		$stored   = get_option( 'lrti_settings', array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( $defaults, $stored );
	}
}

if ( ! function_exists( 'lrti_get_setting' ) ) {
	/**
	 * Retrieve a single plugin setting by key.
	 *
	 * @param string $key           The setting key, e.g. "dealership_phone".
	 * @param mixed  $default_value Value to return if the key is missing.
	 * @return mixed The setting value, or $default_value if not set.
	 */
	function lrti_get_setting( string $key, $default_value = '' ) {
		$settings = lrti_get_settings();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default_value;
	}
}

if ( ! function_exists( 'lrti_get_default_settings' ) ) {
	/**
	 * The default plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	function lrti_get_default_settings(): array {
		return array(
			'dealership_name'    => 'Little River Equipment Sales LLC',
			'dealership_address' => '242 Little River 157, Foreman, AR 71836',
			'dealership_phone'   => '(870) 542-4661',
			'dealership_email'   => 'littleriverequipmentsales@outlook.com',

			'lead_notification_email' => 'littleriverequipmentsales@outlook.com',

			// Front-end options (editable in a future settings sprint).
			'results_per_page'   => 12,
			'directions_url'     => '',

			// Lead generation (Sprint 5.0).
			'send_customer_confirmation'   => '0',
			'customer_confirmation_subject' => 'We received your trailer inquiry',
			'customer_confirmation_message' => 'Thank you for contacting Little River Equipment Sales LLC. We received your inquiry and will follow up as soon as possible.',
			'consent_text'                 => 'I agree that Little River Equipment Sales LLC may contact me regarding this inquiry.',
			'contact_form_heading'         => 'Contact Us',
			'contact_form_description'     => 'Complete the form below and a member of the Little River Equipment Sales team will get back to you.',
			'lead_retention_days'          => '0',
			'enable_honeypot'              => '1',
			'min_completion_time'          => '3',
			'rate_limit_window'            => '3600',
			'rate_limit_max'               => '5',
			'spam_delete_days'             => '30',
			'enable_inquiry_forms'         => '1',
			'duplicate_window_seconds'     => '600',
			'default_inquiry_status'       => 'new',
			'store_visitor_ip'             => '1',
			'inquiry_success_message'      => 'Thank you. Your inquiry has been received. Little River Equipment Sales LLC will follow up using the contact information you provided.',
			'inquiry_error_message'        => 'We could not submit your inquiry right now. Please call us for assistance.',

			// Currency symbol used when displaying prices. Editable later.
			'currency_symbol'    => '$',

			// Data-safety switch. FALSE by default: nothing is deleted on
			// uninstall unless an admin deliberately turns this on.
			'remove_all_data_on_uninstall' => false,
		);
	}
}

if ( ! function_exists( 'lrti_get_tel_href' ) ) {
	/**
	 * Build a clean "tel:" link value from a display phone number.
	 *
	 * @param string $phone The human-readable phone number.
	 * @return string A value suitable for an href="tel:..." attribute.
	 */
	function lrti_get_tel_href( string $phone ): string {
		$digits = preg_replace( '/[^0-9]/', '', $phone );

		if ( '' === (string) $digits ) {
			return '';
		}

		if ( 10 === strlen( (string) $digits ) ) {
			$digits = '1' . $digits;
		}

		return '+' . $digits;
	}
}

if ( ! function_exists( 'lrti_sanitize_decimal' ) ) {
	/**
	 * Sanitize a value that should be a positive number (allows decimals).
	 *
	 * Strips currency symbols, commas, spaces, and any other non-numeric
	 * characters except a single decimal point. Returns an empty string when the
	 * input is blank or contains no digits, so we can distinguish "not entered"
	 * from "zero".
	 *
	 * @param mixed $raw The raw user input.
	 * @return string A clean numeric string like "5490.34", or "" if empty.
	 */
	function lrti_sanitize_decimal( $raw ): string {
		$raw = is_scalar( $raw ) ? (string) $raw : '';
		$raw = trim( $raw );

		if ( '' === $raw ) {
			return '';
		}

		// Keep digits and dots only.
		$clean = preg_replace( '/[^0-9.]/', '', $raw );

		if ( '' === (string) $clean ) {
			return '';
		}

		// Collapse multiple dots down to the first one (e.g. "1.2.3" -> "1.23").
		$parts = explode( '.', $clean );
		if ( count( $parts ) > 2 ) {
			$clean = $parts[0] . '.' . implode( '', array_slice( $parts, 1 ) );
		}

		// Normalize to a float string so storage is consistent.
		return (string) ( 0 + (float) $clean );
	}
}

if ( ! function_exists( 'lrti_format_price' ) ) {
	/**
	 * Format a numeric price for display, e.g. 5490.34 -> "$5,490.34".
	 *
	 * @param string|float $amount The numeric amount.
	 * @return string The formatted price, or "" if the amount is empty.
	 */
	function lrti_format_price( $amount ): string {
		if ( '' === $amount || null === $amount ) {
			return '';
		}

		$symbol = (string) lrti_get_setting( 'currency_symbol', '$' );
		$number = number_format( (float) $amount, 2, '.', ',' );

		return $symbol . $number;
	}
}

if ( ! function_exists( 'lrti_get_trailer_meta' ) ) {
	/**
	 * Convenience wrapper to read a single trailer meta value.
	 *
	 * All trailer meta keys are prefixed with "_lrti_". This helper adds that
	 * prefix for you, so you call lrti_get_trailer_meta( $id, 'stock_number' ).
	 *
	 * @param int    $post_id       The trailer post ID.
	 * @param string $key           The short meta key (without the _lrti_ prefix).
	 * @param mixed  $default_value Value to return when the meta is empty.
	 * @return mixed The stored value or the default.
	 */
	function lrti_get_trailer_meta( int $post_id, string $key, $default_value = '' ) {
		$value = get_post_meta( $post_id, '_lrti_' . $key, true );

		return ( '' === $value || null === $value ) ? $default_value : $value;
	}
}

if ( ! function_exists( 'lrti_get_price_label' ) ) {
	/**
	 * Produce a plain-text price label for a trailer, honoring the hide/call
	 * flags. Used in the admin list table (and reusable later on the front end).
	 *
	 * Logic order:
	 *   1. Hide price      -> "Hidden"
	 *   2. Call for price  -> "Call for Price"
	 *   3. Sale price set  -> the sale price
	 *   4. Regular price   -> the regular price
	 *   5. Nothing set     -> "—"
	 *
	 * @param int $post_id The trailer post ID.
	 * @return string A human-readable, plain-text label (no HTML).
	 */
	function lrti_get_price_label( int $post_id ): string {
		$hide = lrti_get_trailer_meta( $post_id, 'hide_price', '' );
		if ( '1' === (string) $hide ) {
			return __( 'Hidden', 'little-river-trailer-inventory' );
		}

		$call = lrti_get_trailer_meta( $post_id, 'call_for_price', '' );
		if ( '1' === (string) $call ) {
			return __( 'Call for Price', 'little-river-trailer-inventory' );
		}

		$sale = lrti_get_trailer_meta( $post_id, 'sale_price', '' );
		if ( '' !== $sale ) {
			return lrti_format_price( $sale );
		}

		$regular = lrti_get_trailer_meta( $post_id, 'regular_price', '' );
		if ( '' !== $regular ) {
			return lrti_format_price( $regular );
		}

		return '—';
	}
}

/* -------------------------------------------------------------------------
 * Front-end template helpers (Sprint 4.1).
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'lrti_locate_template' ) ) {
	/**
	 * Locate a template, allowing themes to override plugin templates.
	 *
	 * Themes can override by placing a file at:
	 *   wp-content/themes/your-theme/little-river-trailer-inventory/<name>
	 * Otherwise the plugin's own templates/<name> is used.
	 *
	 * @param string|string[] $names One or more template file names.
	 * @return string Absolute path to the template, or '' if not found.
	 */
	function lrti_locate_template( $names ): string {
		$names          = (array) $names;
		$theme_lookups  = array();

		foreach ( $names as $name ) {
			$theme_lookups[] = 'little-river-trailer-inventory/' . ltrim( $name, '/' );
		}

		$located = locate_template( $theme_lookups );
		if ( '' !== $located ) {
			return $located;
		}

		foreach ( $names as $name ) {
			$file = LRTI_PLUGIN_DIR . 'templates/' . ltrim( $name, '/' );
			if ( is_readable( $file ) ) {
				return $file;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'lrti_get_template_part' ) ) {
	/**
	 * Include a template part (with theme-override support).
	 *
	 * @param string $slug The template slug, without the .php extension.
	 * @return void
	 */
	function lrti_get_template_part( string $slug ): void {
		$file = lrti_locate_template( $slug . '.php' );
		if ( '' !== $file && is_file( $file ) ) {
			include $file;
		}
	}
}

if ( ! function_exists( 'lrti_results_per_page' ) ) {
	/**
	 * Number of trailers shown per archive page. Defaults to 12; filterable.
	 *
	 * @return int
	 */
	function lrti_results_per_page(): int {
		$value = (int) lrti_get_setting( 'results_per_page', 12 );
		if ( $value < 1 ) {
			$value = 12;
		}

		return (int) apply_filters( 'lrti_results_per_page', $value );
	}
}

if ( ! function_exists( 'lrti_get_sort_options' ) ) {
	/**
	 * The available inventory sort options (key => label).
	 *
	 * @return array<string, string>
	 */
	function lrti_get_sort_options(): array {
		return apply_filters(
			'lrti_sort_options',
			array(
				'newest'          => __( 'Newest', 'little-river-trailer-inventory' ),
				'oldest'          => __( 'Oldest', 'little-river-trailer-inventory' ),
				'price_low'       => __( 'Price: Low to High', 'little-river-trailer-inventory' ),
				'price_high'      => __( 'Price: High to Low', 'little-river-trailer-inventory' ),
				'year_new'        => __( 'Year: Newest First', 'little-river-trailer-inventory' ),
				'year_old'        => __( 'Year: Oldest First', 'little-river-trailer-inventory' ),
				'manufacturer_az' => __( 'Manufacturer: A–Z', 'little-river-trailer-inventory' ),
				'manufacturer_za' => __( 'Manufacturer: Z–A', 'little-river-trailer-inventory' ),
				'length'          => __( 'Length', 'little-river-trailer-inventory' ),
				'gvwr'            => __( 'GVWR', 'little-river-trailer-inventory' ),
				'in_stock_first'  => __( 'In Stock First', 'little-river-trailer-inventory' ),
				'featured_first'  => __( 'Featured First', 'little-river-trailer-inventory' ),
			)
		);
	}
}

if ( ! function_exists( 'lrti_current_sort' ) ) {
	/**
	 * The currently selected sort key, sanitized and validated. Reads a public
	 * navigation GET parameter, so no nonce is required.
	 *
	 * @return string
	 */
	function lrti_current_sort(): string {
		$allowed = array_keys( lrti_get_sort_options() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public sort.
		if ( isset( $_GET['sort'] ) ) {
			$value = sanitize_key( wp_unslash( $_GET['sort'] ) );
		} elseif ( isset( $_GET['lrti_sort'] ) ) { // Back-compat.
			$value = sanitize_key( wp_unslash( $_GET['lrti_sort'] ) );
		} else {
			$value = 'newest';
		}

		return in_array( $value, $allowed, true ) ? $value : 'newest';
	}
}

if ( ! function_exists( 'lrti_archive_title' ) ) {
	/**
	 * The inventory archive heading. Filterable for future taxonomy archives.
	 *
	 * @return string
	 */
	function lrti_archive_title(): string {
		return (string) apply_filters( 'lrti_archive_title', __( 'Trailer Inventory', 'little-river-trailer-inventory' ) );
	}
}

if ( ! function_exists( 'lrti_archive_description' ) ) {
	/**
	 * Optional description beneath the archive heading. Filterable.
	 *
	 * @return string
	 */
	function lrti_archive_description(): string {
		$default = __( 'Browse our current selection of quality trailers. Contact us for availability and details.', 'little-river-trailer-inventory' );

		return (string) apply_filters( 'lrti_archive_description', $default );
	}
}

if ( ! function_exists( 'lrti_first_term_name' ) ) {
	/**
	 * Return the first assigned term name for a taxonomy, or ''.
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $taxonomy The taxonomy key.
	 * @return string
	 */
	function lrti_first_term_name( int $post_id, string $taxonomy ): string {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		return (string) $terms[0]->name;
	}
}

if ( ! function_exists( 'lrti_availability' ) ) {
	/**
	 * Return the trailer's availability as ['name'=>, 'slug'=>], or null.
	 *
	 * @param int $post_id The trailer post ID.
	 * @return array{name:string, slug:string}|null
	 */
	function lrti_availability( int $post_id ): ?array {
		$terms = get_the_terms( $post_id, 'trailer_availability' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return null;
		}

		return array(
			'name' => (string) $terms[0]->name,
			'slug' => (string) $terms[0]->slug,
		);
	}
}

if ( ! function_exists( 'lrti_format_measure' ) ) {
	/**
	 * Format a numeric measurement with a unit, e.g. 9995 -> "9,995 lbs".
	 *
	 * @param string|float $value The numeric value.
	 * @param string       $unit  The unit label (already translated).
	 * @return string
	 */
	function lrti_format_measure( $value, string $unit ): string {
		if ( '' === $value || null === $value ) {
			return '';
		}

		$n   = (float) $value;
		$num = ( floor( $n ) === $n ) ? number_format_i18n( $n, 0 ) : number_format_i18n( $n, 1 );

		return trim( $num . ' ' . $unit );
	}
}

if ( ! function_exists( 'lrti_price_html' ) ) {
	/**
	 * Build the small price markup for a trailer card or detail view.
	 *
	 * Honors Hide Price and Call For Price. Returns '' when nothing should be
	 * shown. All dynamic values are escaped inside.
	 *
	 * @param int $post_id The trailer post ID.
	 * @return string Safe HTML.
	 */
	function lrti_price_html( int $post_id ): string {
		if ( '1' === (string) lrti_get_trailer_meta( $post_id, 'hide_price', '' ) ) {
			return '';
		}

		if ( '1' === (string) lrti_get_trailer_meta( $post_id, 'call_for_price', '' ) ) {
			return '<span class="lrti-price lrti-price--call">' . esc_html__( 'Call for Price', 'little-river-trailer-inventory' ) . '</span>';
		}

		$sale    = lrti_get_trailer_meta( $post_id, 'sale_price', '' );
		$regular = lrti_get_trailer_meta( $post_id, 'regular_price', '' );

		if ( '' !== $sale ) {
			$html = '<span class="lrti-price lrti-price--sale">' . esc_html( lrti_format_price( $sale ) ) . '</span>';
			if ( '' !== $regular ) {
				$html .= ' <span class="lrti-price-was">' . esc_html( lrti_format_price( $regular ) ) . '</span>';
			}
			return $html;
		}

		if ( '' !== $regular ) {
			return '<span class="lrti-price">' . esc_html( lrti_format_price( $regular ) ) . '</span>';
		}

		return '';
	}
}

if ( ! function_exists( 'lrti_placeholder_image' ) ) {
	/**
	 * A lightweight inline SVG placeholder for trailers without a photo.
	 *
	 * @return string Safe SVG markup.
	 */
	function lrti_placeholder_image(): string {
		$svg = '<span class="lrti-card-img lrti-card-img--placeholder" role="img" aria-label="' . esc_attr__( 'No photo available', 'little-river-trailer-inventory' ) . '">'
			. '<svg viewBox="0 0 64 40" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true">'
			. '<rect x="4" y="8" width="42" height="18" rx="2" fill="currentColor" opacity="0.15"/>'
			. '<rect x="46" y="14" width="14" height="12" rx="1.5" fill="currentColor" opacity="0.15"/>'
			. '<circle cx="18" cy="30" r="4" fill="currentColor" opacity="0.35"/>'
			. '<circle cx="40" cy="30" r="4" fill="currentColor" opacity="0.35"/>'
			. '<line x1="2" y1="30" x2="60" y2="30" stroke="currentColor" stroke-width="1" opacity="0.2"/>'
			. '</svg></span>';

		/**
		 * Filter the placeholder markup shown when a trailer has no image.
		 *
		 * @param string $svg Safe placeholder HTML.
		 */
		return (string) apply_filters( 'lrti_placeholder_image', $svg );
	}
}

if ( ! function_exists( 'lrti_pagination_html' ) ) {
	/**
	 * Build standard WordPress pagination for the inventory archive, preserving
	 * the current sort selection in the page links.
	 *
	 * @return string Safe HTML (paginate_links output is pre-escaped).
	 */
	function lrti_pagination_html(): string {
		global $wp_query;

		if ( empty( $wp_query ) || (int) $wp_query->max_num_pages < 2 ) {
			return '';
		}

		$big  = 999999999;
		$sort = lrti_current_sort();
		$args = array(
			'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
			'format'    => '?paged=%#%',
			'current'   => max( 1, (int) get_query_var( 'paged' ) ),
			'total'     => (int) $wp_query->max_num_pages,
			'prev_text' => __( '&laquo; Previous', 'little-river-trailer-inventory' ),
			'next_text' => __( 'Next &raquo;', 'little-river-trailer-inventory' ),
			'type'      => 'list',
			'add_args'  => ( 'newest' !== $sort ) ? array( 'lrti_sort' => $sort ) : array(),
		);

		$links = paginate_links( $args );
		if ( empty( $links ) ) {
			return '';
		}

		return '<nav class="lrti-pagination" aria-label="' . esc_attr__( 'Inventory pagination', 'little-river-trailer-inventory' ) . '">' . $links . '</nav>';
	}
}

/* -------------------------------------------------------------------------
 * Single trailer helpers (Sprint 4.2).
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'lrti_get_gallery_image_ids' ) ) {
	/**
	 * Return the ordered, de-duplicated list of image attachment IDs for a
	 * trailer: the featured image first, then gallery images. Cached per request.
	 *
	 * @param int $post_id The trailer post ID.
	 * @return int[] Attachment IDs.
	 */
	function lrti_get_gallery_image_ids( int $post_id ): array {
		static $cache = array();
		if ( isset( $cache[ $post_id ] ) ) {
			return $cache[ $post_id ];
		}

		$ids   = array();
		$thumb = (int) get_post_thumbnail_id( $post_id );
		if ( $thumb > 0 ) {
			$ids[] = $thumb;
		}

		$gallery_raw = (string) get_post_meta( $post_id, '_lrti_gallery_ids', true );
		foreach ( array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) as $gid ) {
			if ( ! in_array( $gid, $ids, true ) ) {
				$ids[] = $gid;
			}
		}

		$cache[ $post_id ] = $ids;

		return $ids;
	}
}

if ( ! function_exists( 'lrti_get_price_data' ) ) {
	/**
	 * Build a normalized pricing data array for a trailer. Cached per request.
	 *
	 * Keys: state (hide|sold|call|sale|regular|none), regular, sale, msrp,
	 * savings, percent, is_sold.
	 *
	 * @param int $post_id The trailer post ID.
	 * @return array<string, mixed>
	 */
	function lrti_get_price_data( int $post_id ): array {
		static $cache = array();
		if ( isset( $cache[ $post_id ] ) ) {
			return $cache[ $post_id ];
		}

		$avail   = lrti_availability( $post_id );
		$is_sold = ( null !== $avail && 'sold' === $avail['slug'] );

		$hide    = ( '1' === (string) lrti_get_trailer_meta( $post_id, 'hide_price', '' ) );
		$call    = ( '1' === (string) lrti_get_trailer_meta( $post_id, 'call_for_price', '' ) );
		$regular = lrti_get_trailer_meta( $post_id, 'regular_price', '' );
		$sale    = lrti_get_trailer_meta( $post_id, 'sale_price', '' );
		$msrp    = lrti_get_trailer_meta( $post_id, 'msrp', '' );

		$savings = '';
		$percent = '';
		if ( '' !== $sale && '' !== $regular && (float) $regular > (float) $sale ) {
			$diff    = (float) $regular - (float) $sale;
			$savings = (string) $diff;
			if ( (float) $regular > 0 ) {
				$percent = (string) (int) round( $diff / (float) $regular * 100 );
			}
		}

		if ( $hide ) {
			$state = 'hide';
		} elseif ( $is_sold ) {
			$state = 'sold';
		} elseif ( $call ) {
			$state = 'call';
		} elseif ( '' !== $sale ) {
			$state = 'sale';
		} elseif ( '' !== $regular ) {
			$state = 'regular';
		} else {
			$state = 'none';
		}

		$data = array(
			'state'   => $state,
			'is_sold' => $is_sold,
			'regular' => $regular,
			'sale'    => $sale,
			'msrp'    => $msrp,
			'savings' => $savings,
			'percent' => $percent,
		);

		$cache[ $post_id ] = $data;

		return $data;
	}
}

if ( ! function_exists( 'lrti_single_price_html' ) ) {
	/**
	 * Build the single-page price markup (filterable). All values escaped inside.
	 *
	 * @param int $post_id The trailer post ID.
	 * @return string Safe HTML.
	 */
	function lrti_single_price_html( int $post_id ): string {
		$d    = lrti_get_price_data( $post_id );
		$html = '';

		switch ( $d['state'] ) {
			case 'hide':
				$html = '';
				break;

			case 'sold':
				$html = '<span class="lrti-price lrti-price--sold">' . esc_html__( 'Sold', 'little-river-trailer-inventory' ) . '</span>';
				break;

			case 'call':
				$html = '<span class="lrti-price lrti-price--call">' . esc_html__( 'Call for Price', 'little-river-trailer-inventory' ) . '</span>';
				break;

			case 'sale':
				$html  = '<span class="lrti-price lrti-price--sale">' . esc_html( lrti_format_price( $d['sale'] ) ) . '</span>';
				if ( '' !== $d['regular'] ) {
					$html .= ' <span class="lrti-price-was">' . esc_html( lrti_format_price( $d['regular'] ) ) . '</span>';
				}
				break;

			case 'regular':
				$html = '<span class="lrti-price">' . esc_html( lrti_format_price( $d['regular'] ) ) . '</span>';
				break;

			default:
				$html = '';
				break;
		}

		// MSRP shown only when entered and price is visible.
		if ( '' !== $d['msrp'] && 'hide' !== $d['state'] ) {
			$html .= '<span class="lrti-price-msrp">' . sprintf(
				/* translators: %s: formatted MSRP */
				esc_html__( 'MSRP: %s', 'little-river-trailer-inventory' ),
				esc_html( lrti_format_price( $d['msrp'] ) )
			) . '</span>';
		}

		return (string) apply_filters( 'lrti_trailer_price_html', $html, $post_id );
	}
}

if ( ! function_exists( 'lrti_directions_url' ) ) {
	/**
	 * A safe Get Directions URL: a configured URL if set, otherwise a Google
	 * Maps search built from the saved dealership address.
	 *
	 * @return string
	 */
	function lrti_directions_url(): string {
		$custom = trim( (string) lrti_get_setting( 'directions_url', '' ) );
		if ( '' !== $custom ) {
			return esc_url_raw( $custom );
		}

		$address = (string) lrti_get_setting( 'dealership_address', '' );
		if ( '' === $address ) {
			return '';
		}

		return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address );
	}
}

if ( ! function_exists( 'lrti_breadcrumb_items' ) ) {
	/**
	 * Build breadcrumb items for a trailer: Home > Inventory > Type > Title.
	 * Filterable via 'lrti_breadcrumb_items'.
	 *
	 * @param int $post_id The trailer post ID.
	 * @return array<int, array{label:string, url:string}>
	 */
	function lrti_breadcrumb_items( int $post_id ): array {
		$items = array(
			array(
				'label' => __( 'Home', 'little-river-trailer-inventory' ),
				'url'   => home_url( '/' ),
			),
			array(
				'label' => __( 'Inventory', 'little-river-trailer-inventory' ),
				'url'   => (string) get_post_type_archive_link( \LRTI\PostTypes::POST_TYPE ),
			),
		);

		$type_terms = get_the_terms( $post_id, 'trailer_type' );
		if ( ! empty( $type_terms ) && ! is_wp_error( $type_terms ) ) {
			$link = get_term_link( $type_terms[0] );
			if ( ! is_wp_error( $link ) ) {
				$items[] = array(
					'label' => (string) $type_terms[0]->name,
					'url'   => (string) $link,
				);
			}
		}

		$items[] = array(
			'label' => get_the_title( $post_id ),
			'url'   => '',
		);

		return (array) apply_filters( 'lrti_breadcrumb_items', $items, $post_id );
	}
}

if ( ! function_exists( 'twc_render_public_hero' ) ) {
	/**
	 * Render the shared public hero band used by every plugin-generated page.
	 *
	 * One reusable component: dark branded band with breadcrumbs, H1,
	 * description, optional meta, and optional badges. Theme-overridable via
	 * little-river-trailer-inventory/partials/public-hero.php.
	 *
	 * @param array<string, mixed> $args {
	 *   @type string                                  $variant     Context slug (e.g. 'inventory', 'trailer-type', 'single').
	 *   @type array<int, array{label:string,url:string}> $breadcrumbs Ordered crumbs; last is current (url ignored).
	 *   @type string                                  $title       H1 text (required for output).
	 *   @type string                                  $description Optional supporting paragraph.
	 *   @type array<int, array{label:string,value:string}> $meta   Optional label/value pairs (non-empty only).
	 *   @type array<int, array{label:string,class:string}> $badges Optional status badges.
	 * }
	 * @return void
	 */
	function twc_render_public_hero( array $args ): void {
		$twc_hero = array_merge(
			array(
				'variant'     => '',
				'breadcrumbs' => array(),
				'title'       => '',
				'description' => '',
				'meta'        => array(),
				'badges'      => array(),
			),
			$args
		);

		if ( '' === (string) $twc_hero['title'] ) {
			return;
		}

		$located = lrti_locate_template( 'partials/public-hero.php' );
		if ( '' === $located ) {
			return;
		}

		include $located;
	}
}

if ( ! function_exists( 'lrti_quick_specs' ) ) {
	/**
	 * Build the quick-specifications list (label => value), non-empty only.
	 * Filterable via 'lrti_quick_specs'.
	 *
	 * @param int $post_id The trailer post ID.
	 * @return array<string, string>
	 */
	function lrti_quick_specs( int $post_id ): array {
		$ft  = __( 'ft', 'little-river-trailer-inventory' );
		$lbs = __( 'lbs', 'little-river-trailer-inventory' );

		// Up to seven high-value specs (Sprint 2.0.0 adds Tire Size).
		$raw = array(
			__( 'Year', 'little-river-trailer-inventory' )         => (string) lrti_get_trailer_meta( $post_id, 'year', '' ),
			__( 'Manufacturer', 'little-river-trailer-inventory' ) => lrti_first_term_name( $post_id, 'trailer_manufacturer' ),
			__( 'Trailer Type', 'little-river-trailer-inventory' ) => lrti_first_term_name( $post_id, 'trailer_type' ),
			__( 'Floor Length', 'little-river-trailer-inventory' ) => lrti_format_measure( lrti_get_trailer_meta( $post_id, 'floor_length', '' ), $ft ),
			__( 'GVWR', 'little-river-trailer-inventory' )         => lrti_format_measure( lrti_get_trailer_meta( $post_id, 'gvwr', '' ), $lbs ),
			__( 'Axle Count', 'little-river-trailer-inventory' )   => (string) lrti_get_trailer_meta( $post_id, 'axle_count', '' ),
			__( 'Tire Size', 'little-river-trailer-inventory' )    => (string) lrti_get_trailer_meta( $post_id, 'tire_size', '' ),
		);

		$specs = array();
		foreach ( $raw as $label => $value ) {
			if ( '' !== $value ) {
				$specs[ $label ] = $value;
			}
		}

		// Never exceed seven rows.
		$specs = array_slice( $specs, 0, 7, true );

		return (array) apply_filters( 'lrti_quick_specs', $specs, $post_id );
	}
}

if ( ! function_exists( 'lrti_full_specs' ) ) {
	/**
	 * Build the grouped full-specifications structure. Only non-empty values are
	 * included, and empty groups are dropped. Filterable via 'lrti_full_specs'.
	 *
	 * @param int $post_id The trailer post ID.
	 * @return array<string, array<string, string>>
	 */
	function lrti_full_specs( int $post_id ): array {
		$ft  = __( 'ft', 'little-river-trailer-inventory' );
		$in  = __( 'in', 'little-river-trailer-inventory' );
		$lbs = __( 'lbs', 'little-river-trailer-inventory' );

		$m = static function ( $key ) use ( $post_id ) {
			return (string) lrti_get_trailer_meta( $post_id, $key, '' );
		};

		// Read a multi-value (checkbox group) meta and join for display.
		$mset = static function ( $key ) use ( $post_id ) {
			$raw = get_post_meta( $post_id, '_lrti_' . $key, true );
			if ( is_array( $raw ) && ! empty( $raw ) ) {
				return implode( ', ', array_map( 'strval', $raw ) );
			}
			return '';
		};

		$payload      = $m( 'payload_capacity' );
		$payload_note = '';
		if ( '' !== $payload && '1' === (string) get_post_meta( $post_id, '_lrti_payload_derived', true ) ) {
			$payload_note = ' ' . __( '(calculated)', 'little-river-trailer-inventory' );
		}

		$groups = array(
			__( 'General', 'little-river-trailer-inventory' )              => array(
				__( 'Year', 'little-river-trailer-inventory' )         => $m( 'year' ),
				__( 'Manufacturer', 'little-river-trailer-inventory' ) => lrti_first_term_name( $post_id, 'trailer_manufacturer' ),
				__( 'Model', 'little-river-trailer-inventory' )        => $m( 'model' ),
				__( 'Trailer Type', 'little-river-trailer-inventory' ) => lrti_first_term_name( $post_id, 'trailer_type' ),
				__( 'Condition', 'little-river-trailer-inventory' )    => lrti_first_term_name( $post_id, 'trailer_condition' ),
				__( 'Availability', 'little-river-trailer-inventory' ) => lrti_first_term_name( $post_id, 'trailer_availability' ),
				__( 'Stock Number', 'little-river-trailer-inventory' ) => $m( 'stock_number' ),
			),
			__( 'Dimensions', 'little-river-trailer-inventory' )           => array(
				__( 'Floor Length', 'little-river-trailer-inventory' )   => lrti_format_measure( $m( 'floor_length' ), $ft ),
				__( 'Overall Length', 'little-river-trailer-inventory' ) => lrti_format_measure( $m( 'overall_length' ), $ft ),
				__( 'Width', 'little-river-trailer-inventory' )          => lrti_format_measure( $m( 'width' ), $in ),
				__( 'Height', 'little-river-trailer-inventory' )         => lrti_format_measure( $m( 'height' ), $in ),
				__( 'Side Height', 'little-river-trailer-inventory' )    => $m( 'side_height' ),
			),
			__( 'Weight and Capacity', 'little-river-trailer-inventory' )  => array(
				__( 'GVWR', 'little-river-trailer-inventory' )             => lrti_format_measure( $m( 'gvwr' ), $lbs ),
				__( 'Empty Weight', 'little-river-trailer-inventory' )     => lrti_format_measure( $m( 'empty_weight' ), $lbs ),
				__( 'Payload Capacity', 'little-river-trailer-inventory' ) => ( '' !== $payload ) ? lrti_format_measure( $payload, $lbs ) . $payload_note : '',
			),
			__( 'Axles and Suspension', 'little-river-trailer-inventory' ) => array(
				__( 'Axle Count', 'little-river-trailer-inventory' )  => $m( 'axle_count' ),
				__( 'Axle Rating', 'little-river-trailer-inventory' ) => lrti_format_measure( $m( 'axle_rating' ), $lbs ),
				__( 'Suspension', 'little-river-trailer-inventory' )  => $m( 'suspension_type' ),
			),
			__( 'Brakes and Running Gear', 'little-river-trailer-inventory' ) => array(
				__( 'Brake Type', 'little-river-trailer-inventory' ) => $m( 'brake_type' ),
				__( 'Tires', 'little-river-trailer-inventory' )      => $m( 'tires' ),
				__( 'Wheels', 'little-river-trailer-inventory' )     => $m( 'wheels' ),
			),
			__( 'Hitch and Hardware', 'little-river-trailer-inventory' )   => array(
				__( 'Pull Type', 'little-river-trailer-inventory' )               => $m( 'pull_type' ),
				__( 'Coupler', 'little-river-trailer-inventory' )                 => $m( 'coupler_type' ),
				__( 'Coupler Ball Size', 'little-river-trailer-inventory' )       => $m( 'coupler_ball_size' ),
				__( 'Adjustable Hitch', 'little-river-trailer-inventory' )        => $m( 'adjustable_hitch' ),
				__( 'Adjustable Coupler', 'little-river-trailer-inventory' )      => $m( 'adjustable_coupler' ),
				__( 'Coupler Adjustment Range', 'little-river-trailer-inventory' ) => $m( 'coupler_adjustment_range' ),
				__( 'Hitch Height Range', 'little-river-trailer-inventory' )      => $m( 'hitch_height_range' ),
				__( 'Safety Chains', 'little-river-trailer-inventory' )           => $m( 'safety_chains_included' ),
				__( 'Breakaway Kit', 'little-river-trailer-inventory' )           => $m( 'breakaway_kit_included' ),
				__( 'Jack', 'little-river-trailer-inventory' )                    => $m( 'jack_type' ),
			),
			__( 'Wheels and Tires', 'little-river-trailer-inventory' )     => array(
				__( 'Tire Size', 'little-river-trailer-inventory' )        => $m( 'tire_size' ),
				__( 'Tire Type', 'little-river-trailer-inventory' )        => $m( 'tire_type' ),
				__( 'Load Range', 'little-river-trailer-inventory' )       => $m( 'tire_load_range' ),
				__( 'Ply Rating', 'little-river-trailer-inventory' )       => $m( 'tire_ply_rating' ),
				__( 'Tire Brand', 'little-river-trailer-inventory' )       => $m( 'tire_brand' ),
				__( 'Wheel Diameter', 'little-river-trailer-inventory' )   => lrti_format_measure( $m( 'wheel_diameter' ), $in ),
				__( 'Wheel Width', 'little-river-trailer-inventory' )      => lrti_format_measure( $m( 'wheel_width' ), $in ),
				__( 'Wheel Material', 'little-river-trailer-inventory' )   => $m( 'wheel_material' ),
				__( 'Wheel Finish', 'little-river-trailer-inventory' )     => $m( 'wheel_finish' ),
				__( 'Bolt Pattern', 'little-river-trailer-inventory' )     => $m( 'wheel_bolt_pattern' ),
				__( 'Spare Tire', 'little-river-trailer-inventory' )       => $m( 'spare_tire_included' ),
				__( 'Spare Tire Mount', 'little-river-trailer-inventory' ) => $m( 'spare_tire_mount_included' ),
				__( 'Spare Tire Size', 'little-river-trailer-inventory' )  => $m( 'spare_tire_size' ),
				__( 'Number of Tires', 'little-river-trailer-inventory' )  => $m( 'number_of_tires' ),
				__( 'Tire Notes', 'little-river-trailer-inventory' )       => $m( 'tire_notes' ),
			),
			__( 'Lighting and Electrical', 'little-river-trailer-inventory' ) => array(
				__( 'LED Lights', 'little-river-trailer-inventory' )     => $m( 'led_lights' ),
				__( 'Light Types', 'little-river-trailer-inventory' )    => $mset( 'light_types' ),
				__( 'Electrical Connector', 'little-river-trailer-inventory' ) => $m( 'electrical_connector' ),
				__( 'Junction Box', 'little-river-trailer-inventory' )   => $m( 'junction_box' ),
				__( 'Battery', 'little-river-trailer-inventory' )        => $m( 'battery_included' ),
				__( 'Battery Box', 'little-river-trailer-inventory' )    => $m( 'battery_box_included' ),
				__( 'Solar Charger', 'little-river-trailer-inventory' )  => $m( 'solar_charger_included' ),
				__( 'Lighting Notes', 'little-river-trailer-inventory' ) => $m( 'lighting_notes' ),
			),
			__( 'Toolbox', 'little-river-trailer-inventory' )             => array(
				__( 'Toolbox Included', 'little-river-trailer-inventory' ) => $m( 'toolbox_included' ),
				__( 'Toolbox Type', 'little-river-trailer-inventory' )     => $m( 'toolbox_type' ),
				__( 'Toolbox Width', 'little-river-trailer-inventory' )    => lrti_format_measure( $m( 'toolbox_width' ), $in ),
				__( 'Toolbox Height', 'little-river-trailer-inventory' )   => lrti_format_measure( $m( 'toolbox_height' ), $in ),
				__( 'Toolbox Depth', 'little-river-trailer-inventory' )    => lrti_format_measure( $m( 'toolbox_depth' ), $in ),
				__( 'Toolbox Material', 'little-river-trailer-inventory' ) => $m( 'toolbox_material' ),
				__( 'Toolbox Notes', 'little-river-trailer-inventory' )    => $m( 'toolbox_notes' ),
			),
			__( 'Stake Pockets and Tie-Downs', 'little-river-trailer-inventory' ) => array(
				__( 'Stake Pockets', 'little-river-trailer-inventory' )       => $m( 'stake_pockets' ),
				__( 'Stake Pocket Count', 'little-river-trailer-inventory' )  => $m( 'stake_pocket_count' ),
				__( 'Stake Pocket Size', 'little-river-trailer-inventory' )   => $m( 'stake_pocket_size' ),
				__( 'Stake Pocket Spacing', 'little-river-trailer-inventory' ) => $m( 'stake_pocket_spacing' ),
				__( 'Rub Rail', 'little-river-trailer-inventory' )            => $m( 'rub_rail_included' ),
				__( 'D-Ring Count', 'little-river-trailer-inventory' )        => $m( 'dring_count' ),
				__( 'D-Ring Rating', 'little-river-trailer-inventory' )       => lrti_format_measure( $m( 'dring_rating' ), $lbs ),
				__( 'Bull Rings', 'little-river-trailer-inventory' )          => $m( 'bull_rings_included' ),
				__( 'Tie-Down Notes', 'little-river-trailer-inventory' )      => $m( 'tiedown_notes' ),
			),
			__( 'Body and Loading', 'little-river-trailer-inventory' )     => array(
				__( 'Flooring', 'little-river-trailer-inventory' )       => $m( 'flooring' ),
				__( 'Exterior Color', 'little-river-trailer-inventory' ) => $m( 'exterior_color' ),
				__( 'Interior Color', 'little-river-trailer-inventory' ) => $m( 'interior_color' ),
			),
			__( 'Ramps', 'little-river-trailer-inventory' )               => array(
				__( 'Ramps Included', 'little-river-trailer-inventory' ) => $m( 'ramps_included' ),
				__( 'Ramp Type', 'little-river-trailer-inventory' )      => $m( 'ramp_type' ),
				__( 'Ramp Style', 'little-river-trailer-inventory' )     => $m( 'ramp_style' ),
				__( 'Ramp Length', 'little-river-trailer-inventory' )    => lrti_format_measure( $m( 'ramp_length' ), $ft ),
				__( 'Ramp Width', 'little-river-trailer-inventory' )     => lrti_format_measure( $m( 'ramp_width' ), $in ),
				__( 'Ramp Material', 'little-river-trailer-inventory' )  => $m( 'ramp_material' ),
				__( 'Ramp Storage', 'little-river-trailer-inventory' )   => $m( 'ramp_storage' ),
				__( 'Ramp Capacity', 'little-river-trailer-inventory' )  => lrti_format_measure( $m( 'ramp_capacity' ), $lbs ),
				__( 'Ramp Notes', 'little-river-trailer-inventory' )     => $m( 'ramp_notes' ),
			),
			__( 'Additional Features', 'little-river-trailer-inventory' )  => array(
				__( 'Features', 'little-river-trailer-inventory' ) => $m( 'additional_features' ),
			),
		);

		// Drop empty values and empty groups.
		$clean = array();
		foreach ( $groups as $group => $rows ) {
			$kept = array();
			foreach ( $rows as $label => $value ) {
				if ( '' !== $value ) {
					$kept[ $label ] = $value;
				}
			}
			if ( ! empty( $kept ) ) {
				$clean[ $group ] = $kept;
			}
		}

		return (array) apply_filters( 'lrti_full_specs', $clean, $post_id );
	}
}

if ( ! function_exists( 'lrti_get_related_query' ) ) {
	/**
	 * Build and run the related-trailers query. Prefers same Trailer Type or
	 * Manufacturer, excludes the current trailer and sold trailers, and returns
	 * up to four published results. Args are filterable.
	 *
	 * @param int $post_id The current trailer post ID.
	 * @return \WP_Query
	 */
	function lrti_get_related_query( int $post_id ): \WP_Query {
		$type_ids = wp_get_object_terms( $post_id, 'trailer_type', array( 'fields' => 'ids' ) );
		$manu_ids = wp_get_object_terms( $post_id, 'trailer_manufacturer', array( 'fields' => 'ids' ) );
		$type_ids = is_wp_error( $type_ids ) ? array() : $type_ids;
		$manu_ids = is_wp_error( $manu_ids ) ? array() : $manu_ids;

		$tax_query = array( 'relation' => 'AND' );

		// Prefer same type or manufacturer.
		$match = array( 'relation' => 'OR' );
		if ( ! empty( $type_ids ) ) {
			$match[] = array(
				'taxonomy' => 'trailer_type',
				'field'    => 'term_id',
				'terms'    => $type_ids,
			);
		}
		if ( ! empty( $manu_ids ) ) {
			$match[] = array(
				'taxonomy' => 'trailer_manufacturer',
				'field'    => 'term_id',
				'terms'    => $manu_ids,
			);
		}
		if ( count( $match ) > 1 ) {
			$tax_query[] = $match;
		}

		// Exclude sold trailers by default.
		$sold = get_term_by( 'slug', 'sold', 'trailer_availability' );
		if ( $sold ) {
			$tax_query[] = array(
				'taxonomy' => 'trailer_availability',
				'field'    => 'term_id',
				'terms'    => array( (int) $sold->term_id ),
				'operator' => 'NOT IN',
			);
		}

		$args = array(
			'post_type'           => \LRTI\PostTypes::POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => 3,
			'post__not_in'        => array( $post_id ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
		);

		if ( count( $tax_query ) > 1 ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$args = (array) apply_filters( 'lrti_related_trailers_args', $args, $post_id );

		/**
		 * Filter the similar-trailers query args (alias of lrti_related_trailers_args).
		 *
		 * @param array<string, mixed> $args    WP_Query args.
		 * @param int                  $post_id Current trailer ID.
		 */
		$args = (array) apply_filters( 'lrti_similar_trailers_query_args', $args, $post_id );

		// Cache the resulting IDs per trailer to avoid repeating the join query.
		$cache_key = 'lrti_related_' . $post_id;
		$cached_ids = get_transient( $cache_key );

		if ( is_array( $cached_ids ) ) {
			if ( empty( $cached_ids ) ) {
				return new \WP_Query( array( 'post__in' => array( 0 ), 'post_type' => \LRTI\PostTypes::POST_TYPE ) );
			}
			$cached_args = array(
				'post_type'           => \LRTI\PostTypes::POST_TYPE,
				'post_status'         => 'publish',
				'post__in'            => $cached_ids,
				'orderby'             => 'post__in',
				'posts_per_page'      => count( $cached_ids ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			);
			return new \WP_Query( $cached_args );
		}

		$query = new \WP_Query( $args );
		set_transient( $cache_key, wp_list_pluck( $query->posts, 'ID' ), 6 * HOUR_IN_SECONDS );

		return $query;
	}
}

if ( ! function_exists( 'lrti_clear_related_cache' ) ) {
	/**
	 * Clear all cached related-trailer results. Hooked to inventory changes.
	 *
	 * @return void
	 */
	function lrti_clear_related_cache(): void {
		global $wpdb;
		// Related caches are keyed per-trailer; clear them in bulk.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_lrti\_related\_%' OR option_name LIKE '\_transient\_timeout\_lrti\_related\_%'" );
	}
}

if ( ! function_exists( 'lrti_contact_cta_labels' ) ) {
	/**
	 * The labels used for the primary calls to action. Filterable.
	 *
	 * @return array<string, string>
	 */
	function lrti_contact_cta_labels(): array {
		return (array) apply_filters(
			'lrti_contact_cta_labels',
			array(
				'call'        => __( 'Call', 'little-river-trailer-inventory' ),
				'availability' => __( 'Check Availability', 'little-river-trailer-inventory' ),
				'request'     => __( 'Request Information', 'little-river-trailer-inventory' ),
				'directions'  => __( 'Get Directions', 'little-river-trailer-inventory' ),
				'email'       => __( 'Email Us', 'little-river-trailer-inventory' ),
				'similar'     => __( 'View Similar Trailers', 'little-river-trailer-inventory' ),
			)
		);
	}
}

if ( ! function_exists( 'lrti_filters_engine' ) ) {
	/**
	 * Return the shared Filters engine instance (or null if unavailable).
	 *
	 * @return \LRTI\Filters|null
	 */
	function lrti_filters_engine() {
		$plugin = \LRTI\Plugin::instance();
		if ( method_exists( $plugin, 'filters' ) ) {
			return $plugin->filters();
		}
		return null;
	}
}


if ( ! function_exists( 'lrti_is_valid_phone' ) ) {
	/**
	 * Loose server-side phone validation: at least 7 digits after stripping
	 * common formatting characters.
	 *
	 * @param string $phone The phone value.
	 * @return bool
	 */
	function lrti_is_valid_phone( string $phone ): bool {
		$digits = preg_replace( '/[^0-9]/', '', $phone );
		return is_string( $digits ) && strlen( $digits ) >= 7 && strlen( $digits ) <= 15;
	}
}
