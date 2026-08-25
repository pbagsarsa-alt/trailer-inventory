<?php
/**
 * Taxonomy registration and default-term seeding.
 *
 * A "taxonomy" is a way to group content. WordPress ships with Categories and
 * Tags for posts; here we create dealership-specific groupings for trailers:
 * Trailer Type, Manufacturer, Condition, Availability Status, and Features.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Taxonomies
 */
final class Taxonomies {

	/**
	 * Version of the default-term set.
	 *
	 * Bump this whenever the default terms below change. The plugin compares it
	 * to the stored "lrti_terms_version" option and re-runs seeding when they
	 * differ, so existing installations pick up new default terms WITHOUT any
	 * reinstall.
	 *
	 * @var string
	 */
	public const TERMS_VERSION = '3';

	/**
	 * Attach WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_taxonomies' ) );
	}

	/**
	 * Register all five trailer taxonomies.
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		$post_type = PostTypes::POST_TYPE;

		// Trailer Type — category-like (choose one), e.g. Utility, Dump.
		$this->register_taxonomy(
			'trailer_type',
			$post_type,
			__( 'Trailer Types', 'little-river-trailer-inventory' ),
			__( 'Trailer Type', 'little-river-trailer-inventory' ),
			'trailer-type',
			true
		);

		// Manufacturer — category-like.
		$this->register_taxonomy(
			'trailer_manufacturer',
			$post_type,
			__( 'Manufacturers', 'little-river-trailer-inventory' ),
			__( 'Manufacturer', 'little-river-trailer-inventory' ),
			'manufacturer',
			true
		);

		// Condition — category-like (New / Used).
		$this->register_taxonomy(
			'trailer_condition',
			$post_type,
			__( 'Conditions', 'little-river-trailer-inventory' ),
			__( 'Condition', 'little-river-trailer-inventory' ),
			'condition',
			true
		);

		// Availability Status — category-like.
		$this->register_taxonomy(
			'trailer_availability',
			$post_type,
			__( 'Availability Statuses', 'little-river-trailer-inventory' ),
			__( 'Availability Status', 'little-river-trailer-inventory' ),
			'availability',
			true
		);

		// Features — tag-like (free-form list), e.g. "Spare Tire Mount".
		$this->register_taxonomy(
			'trailer_feature',
			$post_type,
			__( 'Features', 'little-river-trailer-inventory' ),
			__( 'Feature', 'little-river-trailer-inventory' ),
			'feature',
			false
		);
	}

	/**
	 * Helper that registers a single taxonomy with sensible defaults.
	 *
	 * @param string $taxonomy     The taxonomy key (max 32 chars).
	 * @param string $post_type    The post type it applies to.
	 * @param string $plural       Plural display label.
	 * @param string $singular     Singular display label.
	 * @param string $slug         The URL slug for term archives.
	 * @param bool   $hierarchical True = category-like, false = tag-like.
	 * @return void
	 */
	private function register_taxonomy( string $taxonomy, string $post_type, string $plural, string $singular, string $slug, bool $hierarchical ): void {
		$labels = array(
			'name'          => $plural,
			'singular_name' => $singular,
			'search_items'  => sprintf(
				/* translators: %s: taxonomy plural name */
				__( 'Search %s', 'little-river-trailer-inventory' ),
				$plural
			),
			'all_items'     => sprintf(
				/* translators: %s: taxonomy plural name */
				__( 'All %s', 'little-river-trailer-inventory' ),
				$plural
			),
			'edit_item'     => sprintf(
				/* translators: %s: taxonomy singular name */
				__( 'Edit %s', 'little-river-trailer-inventory' ),
				$singular
			),
			'update_item'   => sprintf(
				/* translators: %s: taxonomy singular name */
				__( 'Update %s', 'little-river-trailer-inventory' ),
				$singular
			),
			'add_new_item'  => sprintf(
				/* translators: %s: taxonomy singular name */
				__( 'Add New %s', 'little-river-trailer-inventory' ),
				$singular
			),
			'new_item_name' => sprintf(
				/* translators: %s: taxonomy singular name */
				__( 'New %s Name', 'little-river-trailer-inventory' ),
				$singular
			),
			'menu_name'     => $plural,
		);

		$args = array(
			'labels'            => $labels,
			'public'            => true,
			'hierarchical'      => $hierarchical,
			'show_ui'           => true,
			// We provide our own submenu links, so keep these out of auto menus.
			'show_in_menu'      => false,
			'show_admin_column' => false, // We render our own admin columns.
			'show_in_rest'      => true,  // Block editor + future REST use.
			'query_var'         => true,
			'rewrite'           => array(
				'slug'         => $slug,
				'with_front'   => false,
				'hierarchical' => $hierarchical,
			),
		);

		register_taxonomy( $taxonomy, $post_type, $args );
	}

	/**
	 * The default terms for each taxonomy.
	 *
	 * These are starting points only; an administrator can rename, add, or
	 * remove any of them from the WordPress dashboard.
	 *
	 * @return array<string, string[]>
	 */
	public function get_default_terms(): array {
		return array(
			'trailer_type'         => array(
				'Utility Trailers',
				'Dump Trailers',
				'Equipment Trailers',
				'Flatbed Trailers',
				'Gooseneck Trailers',
			),
			'trailer_manufacturer' => array(
				'VAR Trailers',
				'A&V Trailers',
				'AMW Trailers',
				'True Texas Trailers',
				'Texas Trailer Ranch',
			),
			'trailer_condition'    => array(
				'New',
				'Used',
			),
			'trailer_availability' => array(
				'In Stock',
				'Coming Soon',
				'Sale Pending',
				'Sold',
			),
			'trailer_feature'      => array(
				'LED Lights',
				'LED Marker Lights',
				'LED Tail Lights',
				'Loading Lights',
				'Interior Dome Light',
				'Spare Tire',
				'Spare Tire Mount',
				'Spare Tire Carrier',
				'Mounted Spare',
				'Stake Pockets',
				'Rub Rail',
				'Pipe Top Rail',
				'Side Rails',
				'Removable Side Rails',
				'D-Rings',
				'Recessed D-Rings',
				'Weld-On D-Rings',
				'Bull Rings',
				'E-Track',
				'Tie Downs',
				'Toolbox',
				'Lockable Toolbox',
				'Underbody Toolbox',
				'Chain Tray',
				'Chain Box',
				'Drive Over Fenders',
				'Diamond Plate Fenders',
				'Removable Fenders',
				'Teardrop Fenders',
				'Slide-In Ramps',
				'Fold-Up Ramps',
				'Stand-Up Ramps',
				'Mega Ramps',
				'Rear Loading Ramp',
				'Spring Assist Ramps',
				'Ramp Gate',
				'Fold Down Gate',
				'Mesh Gate',
				'Combo Gate',
				'Bi-Fold Gate',
				'Pressure Treated Floor',
				'Steel Floor',
				'Aluminum Floor',
				'Composite Floor',
				'Rumber Floor',
				'Apitong Floor',
				'EZ Lube Hubs',
				'Oil Bath Hubs',
				'Nev-R-Lube Hubs',
				'Electric Brakes',
				'Self-Adjusting Brakes',
				'Breakaway Kit',
				'Brakes on All Axles',
				'Winch Plate',
				'Winch Mount',
				'Front Toolbox',
				'Adjustable Coupler',
				'Bulldog Coupler',
				'Gooseneck Coupler',
				'Pintle Eye',
				'Safety Chains',
				'Torsion Axles',
				'Leaf Spring Axles',
				'Drop Axles',
				'Spread Axle',
				'Dexter Axles',
				'Single Axle',
				'Tandem Axle',
				'Triple Axle',
				'Radial Tires',
				'Aluminum Wheels',
				'Steel Wheels',
				'Silver Wheels',
				'Black Wheels',
				'Sealed Wiring',
				'7-Way Plug',
				'Battery Box',
				'Solar Charger',
				'Breakaway Battery',
				'Powder Coat Finish',
				'Powder Coated Frame',
				'Two Tone Paint',
				'Undercoating',
				'Primed and Painted',
				'Rear Stabilizer Jacks',
				'Drop Leg Jack',
				'Dual Jacks',
				'Side Wind Jack',
				'Hydraulic Jack',
				'Tarp Kit',
				'Roll Tarp',
				'Hydraulic Lift',
				'Scissor Hoist',
				'Telescopic Hoist',
				'Expanded Metal Sides',
				'Solid Metal Sides',
				'Mesh Sides',
				'Beavertail',
				'Dovetail',
				'Knife Edge',
				'Headache Rack',
				'Cab Guard',
				'Stone Guard',
				'DOT Reflective Tape',
				'Grab Handles',
				'Ladder Rack',
				'Heavy Duty Frame',
				'Reinforced Frame',
				'Landscape Package',
				'Equipment Package',
				'GVWR Upgrade',
			),
		);
	}

	/**
	 * Insert the default terms.
	 *
	 * Runs on activation and again through the version-upgrade routine so that
	 * already-installed sites receive new default terms without reinstalling.
	 *
	 * Safe to run repeatedly: term_exists() is checked before each insert, so no
	 * duplicates are ever created, and existing terms are left untouched.
	 *
	 * @return void
	 */
	public function seed_default_terms(): void {
		// Make sure the taxonomies exist before inserting terms into them (this
		// method may run before the normal init registration on some requests).
		$this->register_taxonomies();

		foreach ( $this->get_default_terms() as $taxonomy => $terms ) {
			// Skip if the taxonomy somehow is not registered, to avoid errors.
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				// Only create the term if it does not already exist.
				if ( ! term_exists( $term, $taxonomy ) ) {
					wp_insert_term( $term, $taxonomy );
				}
			}
		}
	}
}
