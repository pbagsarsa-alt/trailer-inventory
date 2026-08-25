<?php
/**
 * SEO output for single trailer pages (Sprint 4.4).
 *
 * Applies the saved Meta Title / Meta Description — or branded fallbacks — on
 * single trailer views, plus Open Graph and Twitter Card tags and an optional
 * custom canonical. Everything is gated so it only runs when NO supported SEO
 * plugin is active, preventing duplicate tags. All formats are filterable.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seo
 */
final class Seo {

	/**
	 * Attach hooks (only when no supported SEO plugin is handling meta).
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( self::seo_plugin_active() ) {
			return;
		}

		add_filter( 'document_title_parts', array( $this, 'filter_title' ) );
		add_action( 'wp_head', array( $this, 'output_head' ), 1 );
	}

	/**
	 * Detect a supported SEO plugin. Static so the Schema class can reuse it.
	 *
	 * @return bool
	 */
	public static function seo_plugin_active(): bool {
		$active = defined( 'WPSEO_VERSION' )
			|| function_exists( 'YoastSEO' )
			|| class_exists( 'RankMath' )
			|| function_exists( 'rank_math' )
			|| function_exists( 'aioseo' )
			|| defined( 'AIOSEO_VERSION' )
			|| defined( 'SEOPRESS_VERSION' )
			|| function_exists( 'seopress_get_service' );

		/**
		 * Filter whether an external SEO plugin is considered active.
		 *
		 * @param bool $active True if a supported SEO plugin is handling meta.
		 */
		return (bool) apply_filters( 'lrti_seo_plugin_active', $active );
	}

	/**
	 * The effective meta title: saved value, else branded fallback.
	 *
	 * @param int $post_id Trailer post ID.
	 * @return string
	 */
	public static function meta_title( int $post_id ): string {
		$saved = (string) get_post_meta( $post_id, '_lrti_seo_meta_title', true );
		if ( '' !== $saved ) {
			return $saved;
		}

		$title = get_the_title( $post_id );
		$name  = (string) lrti_get_setting( 'dealership_name', 'Little River Equipment Sales LLC' );

		/* translators: 1: trailer title, 2: dealership name */
		$fallback = sprintf( __( '%1$s for Sale in Foreman, AR | %2$s', 'little-river-trailer-inventory' ), $title, $name );

		return (string) apply_filters( 'lrti_fallback_meta_title', $fallback, $post_id );
	}

	/**
	 * The effective meta description: saved value, else excerpt, else fallback.
	 *
	 * @param int $post_id Trailer post ID.
	 * @return string
	 */
	public static function meta_description( int $post_id ): string {
		$saved = (string) get_post_meta( $post_id, '_lrti_seo_meta_description', true );
		if ( '' !== $saved ) {
			return $saved;
		}

		$excerpt = wp_strip_all_tags( (string) get_the_excerpt( $post_id ) );
		if ( '' !== $excerpt ) {
			return $excerpt;
		}

		$title = get_the_title( $post_id );
		$name  = (string) lrti_get_setting( 'dealership_name', 'Little River Equipment Sales LLC' );

		/* translators: 1: trailer title, 2: dealership name */
		$fallback = sprintf( __( 'Shop the %1$s at %2$s in Foreman, Arkansas. View pricing, specifications, photos, and availability.', 'little-river-trailer-inventory' ), $title, $name );

		return (string) apply_filters( 'lrti_fallback_meta_description', $fallback, $post_id );
	}

	/**
	 * Override the document title on single trailers.
	 *
	 * @param array<string, string> $parts The title parts.
	 * @return array<string, string>
	 */
	public function filter_title( array $parts ): array {
		if ( ! is_singular( PostTypes::POST_TYPE ) ) {
			return $parts;
		}
		$parts['title'] = self::meta_title( (int) get_queried_object_id() );
		// Our fallback already includes the site/dealer name; drop the tagline.
		unset( $parts['tagline'], $parts['site'] );
		return $parts;
	}

	/**
	 * The best available social image URL: saved OG image, else featured image,
	 * else the filterable plugin fallback.
	 *
	 * @param int $post_id Trailer post ID.
	 * @return string
	 */
	public static function social_image( int $post_id ): string {
		$og = (string) get_post_meta( $post_id, '_lrti_seo_og_image', true );
		if ( '' !== $og ) {
			return $og;
		}
		$thumb = get_post_thumbnail_id( $post_id );
		if ( $thumb ) {
			$url = wp_get_attachment_image_url( (int) $thumb, 'large' );
			if ( $url ) {
				return (string) $url;
			}
		}
		/**
		 * Filter the fallback social/OG image URL used when a trailer has none.
		 *
		 * @param string $url     Fallback image URL (empty by default).
		 * @param int    $post_id Trailer post ID.
		 */
		return (string) apply_filters( 'lrti_fallback_social_image', '', $post_id );
	}

	/**
	 * Output meta description, canonical (optional), Open Graph, and Twitter.
	 *
	 * @return void
	 */
	public function output_head(): void {
		if ( ! is_singular( PostTypes::POST_TYPE ) ) {
			return;
		}

		$post_id     = (int) get_queried_object_id();
		$title       = self::meta_title( $post_id );
		$description  = self::meta_description( $post_id );
		$url         = (string) get_permalink( $post_id );
		$image       = self::social_image( $post_id );

		// Meta description.
		if ( '' !== $description ) {
			echo '<meta name="description" content="' . esc_attr( wp_trim_words( $description, 40, '' ) ) . '" />' . "\n";
		}

		// Custom canonical replaces core's rel_canonical to avoid duplicates.
		$canonical = (string) get_post_meta( $post_id, '_lrti_seo_canonical_url', true );
		if ( '' !== $canonical ) {
			remove_action( 'wp_head', 'rel_canonical' );
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
		}

		/**
		 * Filter the Open Graph / Twitter data before output.
		 *
		 * @param array<string, string> $data    OG/Twitter values.
		 * @param int                   $post_id Trailer post ID.
		 */
		$og = (array) apply_filters(
			'lrti_open_graph_data',
			array(
				'og:title'            => $title,
				'og:description'      => wp_trim_words( $description, 40, '' ),
				'og:url'              => $url,
				'og:type'             => 'product',
				'og:image'            => $image,
				'og:site_name'        => (string) lrti_get_setting( 'dealership_name', '' ),
				'twitter:card'        => '' !== $image ? 'summary_large_image' : 'summary',
				'twitter:title'       => $title,
				'twitter:description' => wp_trim_words( $description, 40, '' ),
				'twitter:image'       => $image,
			),
			$post_id
		);

		foreach ( $og as $property => $content ) {
			if ( '' === (string) $content ) {
				continue;
			}
			$attr = ( strpos( (string) $property, 'twitter:' ) === 0 ) ? 'name' : 'property';
			if ( 'og:image' === $property || 'twitter:image' === $property || 'og:url' === $property ) {
				$value = esc_url( (string) $content );
			} else {
				$value = esc_attr( (string) $content );
			}
			printf( '<meta %1$s="%2$s" content="%3$s" />' . "\n", esc_attr( $attr ), esc_attr( (string) $property ), $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- values escaped above.
		}
	}
}
