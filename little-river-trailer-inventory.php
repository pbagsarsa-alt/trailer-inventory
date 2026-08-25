<?php
/**
 * Plugin Name:       TWC Trailer Inventory for Little River Equipment Sales LLC
 * Plugin URI:        https://trendwiseco.com
 * Description:        Trailer dealership inventory management for Little River Equipment Sales LLC. Lets staff manage trailers from the dashboard and lets visitors browse, search, filter, and inquire.
 * Version:           2.9.17
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            Trendwise Co.
 * Author URI:        https://trendwiseco.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       little-river-trailer-inventory
 * Domain Path:       /languages
 *
 * @package LittleRiverTrailerInventory
 */

// If this file is called directly, stop. This prevents anyone from loading the
// file by visiting its URL in a browser. ABSPATH is only defined when WordPress
// itself is loading the file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * -------------------------------------------------------------------------
 * Plugin constants.
 *
 * These are fixed, reusable values. We define them once here so the rest of
 * the plugin can refer to them by name instead of repeating file paths, URLs,
 * or version numbers (which would be easy to get wrong).
 * -------------------------------------------------------------------------
 */

// The plugin version. Bump this whenever you release an update. It is also used
// to "bust" browser caches for our CSS/JS files.
define( 'LRTI_VERSION', '2.9.17' );

// The full path to THIS file. Useful for activation/deactivation hooks and for
// building other paths.
define( 'LRTI_PLUGIN_FILE', __FILE__ );

// The absolute server path to the plugin folder, WITH a trailing slash.
// Example: /var/www/html/wp-content/plugins/little-river-trailer-inventory/
define( 'LRTI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// The public URL to the plugin folder, WITH a trailing slash. Used to load
// CSS, JS, and images in the browser.
define( 'LRTI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// The "basename" WordPress uses to identify this plugin, e.g.
// little-river-trailer-inventory/little-river-trailer-inventory.php
define( 'LRTI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// The text domain for translations. Kept as a constant so it is spelled
// consistently everywhere.
define( 'LRTI_TEXT_DOMAIN', 'little-river-trailer-inventory' );

/*
 * -------------------------------------------------------------------------
 * Load the class autoloader and shared helper functions.
 *
 * The autoloader lets us split our PHP classes into separate files without
 * manually "require"-ing each one. helpers.php holds small standalone
 * functions that are not part of a class.
 * -------------------------------------------------------------------------
 */
require_once LRTI_PLUGIN_DIR . 'includes/autoload.php';
require_once LRTI_PLUGIN_DIR . 'includes/helpers.php';

/*
 * -------------------------------------------------------------------------
 * Activation and deactivation hooks.
 *
 * register_activation_hook runs its callback ONCE, the moment an admin clicks
 * "Activate" for this plugin. register_deactivation_hook runs when they click
 * "Deactivate". We route both to dedicated classes so the logic stays organized.
 * -------------------------------------------------------------------------
 */
register_activation_hook(
	LRTI_PLUGIN_FILE,
	static function (): void {
		\LRTI\Activator::activate();
	}
);

register_deactivation_hook(
	LRTI_PLUGIN_FILE,
	static function (): void {
		\LRTI\Deactivator::deactivate();
	}
);

/*
 * -------------------------------------------------------------------------
 * Boot the plugin.
 *
 * We wait for the "plugins_loaded" action so that WordPress and any other
 * plugins are fully available before we start. Then we grab the single
 * instance of our main Plugin class and tell it to run.
 * -------------------------------------------------------------------------
 */
add_action(
	'plugins_loaded',
	static function (): void {
		\LRTI\Plugin::instance()->run();
	}
);
