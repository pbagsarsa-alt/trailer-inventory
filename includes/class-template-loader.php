<?php
/**
 * Front-end template loader.
 *
 * Routes the trailer archive and single trailer views to the plugin's own
 * template files, while letting a theme override them by placing matching files
 * in a "little-river-trailer-inventory" folder inside the theme.
 *
 * Theme override example:
 *   wp-content/themes/your-theme/little-river-trailer-inventory/single-trailer.php
 *   wp-content/themes/your-theme/little-river-trailer-inventory/archive-trailer.php
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TemplateLoader
 */
final class TemplateLoader {

	/**
	 * Attach hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'template_include', array( $this, 'load_template' ), 99 );
	}

	/**
	 * Swap in our templates for trailer archive and single views.
	 *
	 * @param string $template The template WordPress resolved.
	 * @return string
	 */
	public function load_template( string $template ): string {
		if ( is_singular( PostTypes::POST_TYPE ) ) {
			$located = lrti_locate_template( 'single-trailer.php' );

			/**
			 * Filter the resolved single trailer template path.
			 *
			 * @param string $located  Absolute path to the template ('' if missing).
			 * @param string $template The original template path.
			 */
			$located = (string) apply_filters( 'lrti_single_template', $located, $template );

			if ( $this->is_usable_template( $located ) ) {
				return $located;
			}

			// Required plugin template missing: log for developers and fall back
			// to WordPress's resolved template instead of causing a fatal error.
			$this->log_missing_template( 'single-trailer.php' );
			return $template;
		}

		if ( is_post_type_archive( PostTypes::POST_TYPE ) ) {
			$located = lrti_locate_template( 'archive-trailer.php' );
			if ( $this->is_usable_template( $located ) ) {
				return $located;
			}

			$this->log_missing_template( 'archive-trailer.php' );
			return $template;
		}

		if ( is_tax( 'trailer_type' ) ) {
			$located = lrti_locate_template( 'archive-trailer-type.php' );

			/**
			 * Filter the resolved trailer-type archive template path.
			 *
			 * @param string $located  Absolute path to the template ('' if missing).
			 * @param string $template The original template path.
			 */
			$located = (string) apply_filters( 'lrti_trailer_type_template', $located, $template );

			if ( $this->is_usable_template( $located ) ) {
				return $located;
			}

			// Fall back to the theme's archive rather than erroring.
			$this->log_missing_template( 'archive-trailer-type.php' );
			return $template;
		}

		return $template;
	}

	/**
	 * Is the given path a real, readable template FILE (not empty and not a dir)?
	 *
	 * @param string $path Candidate template path.
	 * @return bool
	 */
	private function is_usable_template( string $path ): bool {
		return '' !== $path && is_file( $path ) && is_readable( $path );
	}

	/**
	 * Log a helpful development message when a required template is missing.
	 *
	 * @param string $name The expected template file name.
	 * @return void
	 */
	private function log_missing_template( string $name ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Little River Trailer Inventory: expected template "%s" was not found; falling back to the theme template.', $name ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
