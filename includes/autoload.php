<?php
/**
 * Class autoloader.
 *
 * When PHP encounters a class it has not seen yet (for example \LRTI\Settings),
 * it calls every function registered with spl_autoload_register. Our function
 * translates the class name into a file path and loads that file. This means
 * we never have to write a long list of require_once statements.
 *
 * Naming convention we follow:
 *   Class  \LRTI\Plugin        -> includes/class-plugin.php
 *   Class  \LRTI\Settings      -> includes/class-settings.php
 *   Class  \LRTI\PostTypes     -> includes/class-post-types.php
 *   Class  \LRTI\Admin\Foo     -> includes/admin/class-foo.php   (sub-namespaces
 *                                 map to sub-folders, all lowercase)
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( string $class_name ): void {

		// Only handle classes inside our own namespace prefix "LRTI\".
		// Anything else (WordPress core, other plugins) we leave alone.
		$prefix = 'LRTI\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		// Remove the "LRTI\" prefix, leaving e.g. "Settings" or "Admin\Foo".
		$relative = substr( $class_name, strlen( $prefix ) );

		// Split into namespace parts. The last part is the class; any earlier
		// parts become sub-directories.
		$parts      = explode( '\\', $relative );
		$class_part = array_pop( $parts );

		// Convert CamelCase class name to a hyphenated, lowercase file name and
		// prefix it with "class-". Example: PostTypes -> class-post-types.php
		$file_name = 'class-' . strtolower(
			(string) preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $class_part )
		) . '.php';

		// Build the sub-directory path from any remaining namespace parts.
		$sub_path = '';
		if ( ! empty( $parts ) ) {
			$sub_path = strtolower( implode( '/', $parts ) ) . '/';
		}

		$file = LRTI_PLUGIN_DIR . 'includes/' . $sub_path . $file_name;

		// Only load the file if it actually exists, so a typo does not cause a
		// fatal error.
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
