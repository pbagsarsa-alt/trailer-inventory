<?php
/**
 * Trailer editor: a native, tabbed admin interface.
 *
 * Sprint 3.1 introduced the tabbed editor. Sprint 3.2 improves workflow, data
 * consistency, validation, layout, and adds working SEO fields, all while
 * preserving existing meta keys, taxonomies, URLs, and saved trailers.
 *
 * Highlights:
 *   - General tab reordered to a dealership sales workflow.
 *   - Suggested trailer titles auto-generate from Year/Manufacturer/Model/Type,
 *     but never overwrite a manually edited title.
 *   - More specification fields are controlled dropdowns (legacy free-text
 *     values are preserved so nothing breaks).
 *   - Specifications grouped into collapsible sections.
 *   - SEO tab now has real fields (Meta Title/Description function; others save
 *     for future use).
 *   - Extra validation: unique/required Stock Number, four-digit Year, numeric
 *     prices, Sale <= Regular, MSRP >= Sale, and a friendly VIN check.
 *
 * All trailer meta keys begin with "_lrti_"; the leading underscore keeps them
 * private (out of the public REST API and the generic Custom Fields panel).
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MetaFields
 */
final class MetaFields {

	private const NONCE_ACTION     = 'lrti_save_trailer_meta';
	private const NONCE_NAME       = 'lrti_trailer_meta_nonce';
	private const NOTICE_TRANSIENT = 'lrti_admin_notice_';

	/**
	 * Attach WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes_' . PostTypes::POST_TYPE, array( $this, 'setup_meta_boxes' ) );
		add_action( 'save_post_' . PostTypes::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'show_notices' ) );
		add_action( 'edit_form_after_editor', array( $this, 'description_helper_text' ) );

		// Use the classic editor for trailers so the title field and form are
		// available for auto-title generation and submit validation. This does
		// not change stored data, the post type, taxonomies, URLs, or images.
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_block_editor' ), 10, 2 );
	}

	/**
	 * Force the classic editor for the trailer post type only.
	 *
	 * @param bool   $use_block Whether to use the block editor.
	 * @param string $post_type The post type being edited.
	 * @return bool
	 */
	public function disable_block_editor( bool $use_block, string $post_type ): bool {
		if ( PostTypes::POST_TYPE === $post_type ) {
			return false;
		}
		return $use_block;
	}

	/* --------------------------------------------------------------------- *
	 * Configuration
	 * --------------------------------------------------------------------- */

	/**
	 * Tab definitions, in display order.
	 *
	 * @return array<string, string>
	 */
	private function tabs(): array {
		return array(
			'general' => __( 'General', 'little-river-trailer-inventory' ),
			'pricing' => __( 'Pricing', 'little-river-trailer-inventory' ),
			'specs'   => __( 'Specifications', 'little-river-trailer-inventory' ),
			'photos'  => __( 'Photos', 'little-river-trailer-inventory' ),
			'seo'     => __( 'SEO', 'little-river-trailer-inventory' ),
			'notes'   => __( 'Internal Notes', 'little-river-trailer-inventory' ),
		);
	}

	/**
	 * Single-select taxonomy dropdowns shown on the General tab.
	 *
	 * @return array<string, string>
	 */
	private function taxonomy_dropdowns(): array {
		return array(
			'trailer_manufacturer' => __( 'Manufacturer', 'little-river-trailer-inventory' ),
			'trailer_type'         => __( 'Trailer Type', 'little-river-trailer-inventory' ),
			'trailer_condition'    => __( 'Condition', 'little-river-trailer-inventory' ),
			'trailer_availability' => __( 'Availability', 'little-river-trailer-inventory' ),
		);
	}

	/**
	 * Specification section headings, in display order.
	 *
	 * The 'open' flag controls whether the collapsible section starts expanded.
	 *
	 * @return array<string, array{label:string, open:bool}>
	 */
	private function spec_groups(): array {
		return array(
			'dimensions' => array( 'label' => __( 'Dimensions', 'little-river-trailer-inventory' ), 'open' => true ),
			'axles'      => array( 'label' => __( 'Axles', 'little-river-trailer-inventory' ), 'open' => true ),
			'suspension' => array( 'label' => __( 'Suspension', 'little-river-trailer-inventory' ), 'open' => false ),
			'brakes'     => array( 'label' => __( 'Brakes', 'little-river-trailer-inventory' ), 'open' => false ),
			'hitch'      => array( 'label' => __( 'Hitch and Coupler', 'little-river-trailer-inventory' ), 'open' => false ),
			'frame'      => array( 'label' => __( 'Frame', 'little-river-trailer-inventory' ), 'open' => false ),
			'wheels'     => array( 'label' => __( 'Wheels and Tires', 'little-river-trailer-inventory' ), 'open' => false ),
			'lighting'   => array( 'label' => __( 'Lighting and Electrical', 'little-river-trailer-inventory' ), 'open' => false ),
			'toolbox'    => array( 'label' => __( 'Toolbox', 'little-river-trailer-inventory' ), 'open' => false ),
			'tiedowns'   => array( 'label' => __( 'Stake Pockets and Tie-Downs', 'little-river-trailer-inventory' ), 'open' => false ),
			'body'       => array( 'label' => __( 'Body & Flooring', 'little-river-trailer-inventory' ), 'open' => false ),
			'ramps'      => array( 'label' => __( 'Ramps', 'little-river-trailer-inventory' ), 'open' => false ),
			'appearance' => array( 'label' => __( 'Appearance', 'little-river-trailer-inventory' ), 'open' => false ),
			'hardware'   => array( 'label' => __( 'Hardware', 'little-river-trailer-inventory' ), 'open' => false ),
			'features'   => array( 'label' => __( 'Additional Features', 'little-river-trailer-inventory' ), 'open' => false ),
		);
	}

	/**
	 * The complete meta-field configuration (taxonomies handled separately).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function meta_fields(): array {
		$colors = array( 'Black', 'White', 'Red', 'Blue', 'Silver', 'Gray', 'Green', 'Other' );

		return array(
			// ---- General ------------------------------------------------
			'year'                => array(
				'tab'         => 'general',
				'label'       => __( 'Year', 'little-river-trailer-inventory' ),
				'type'        => 'number',
				'sanitize'    => 'int',
				'placeholder' => '2026',
				'desc'        => __( 'Four-digit model year.', 'little-river-trailer-inventory' ),
			),
			'model'               => array(
				'tab'         => 'general',
				'label'       => __( 'Model', 'little-river-trailer-inventory' ),
				'type'        => 'text',
				'sanitize'    => 'text',
				'placeholder' => '1020SC',
			),
			'stock_number'        => array(
				'tab'      => 'general',
				'label'    => __( 'Stock Number', 'little-river-trailer-inventory' ),
				'type'     => 'text',
				'sanitize' => 'text',
				'required' => true,
				'desc'     => __( 'Required. Must be unique across your inventory.', 'little-river-trailer-inventory' ),
			),
			'vin'                 => array(
				'tab'      => 'general',
				'label'    => __( 'VIN', 'little-river-trailer-inventory' ),
				'type'     => 'text',
				'sanitize' => 'vin',
				'desc'     => __( 'Optional. If entered, should be a 17-character VIN. Kept private (not shown publicly).', 'little-river-trailer-inventory' ),
			),

			// ---- Pricing ------------------------------------------------
			'regular_price'       => array(
				'tab'      => 'pricing',
				'label'    => __( 'Regular Price', 'little-river-trailer-inventory' ),
				'type'     => 'price',
				'sanitize' => 'price',
				'desc'     => __( 'Paste any format, e.g. 6995, $6,995, or 6995.00.', 'little-river-trailer-inventory' ),
			),
			'sale_price'          => array(
				'tab'      => 'pricing',
				'label'    => __( 'Sale Price', 'little-river-trailer-inventory' ),
				'type'     => 'price',
				'sanitize' => 'price',
				'desc'     => __( 'Optional. Cannot be greater than the Regular Price.', 'little-river-trailer-inventory' ),
			),
			'msrp'                => array(
				'tab'      => 'pricing',
				'label'    => __( 'MSRP', 'little-river-trailer-inventory' ),
				'type'     => 'price',
				'sanitize' => 'price',
				'desc'     => __( 'Manufacturer suggested retail price. Cannot be less than the Sale Price.', 'little-river-trailer-inventory' ),
			),
			'call_for_price'      => array(
				'tab'      => 'pricing',
				'label'    => __( 'Call For Price', 'little-river-trailer-inventory' ),
				'type'     => 'checkbox',
				'sanitize' => 'checkbox',
				'cbtext'   => __( 'Show "Call for Price" instead of a number.', 'little-river-trailer-inventory' ),
			),
			'hide_price'          => array(
				'tab'      => 'pricing',
				'label'    => __( 'Hide Price', 'little-river-trailer-inventory' ),
				'type'     => 'checkbox',
				'sanitize' => 'checkbox',
				'cbtext'   => __( 'Hide the price entirely on the public site.', 'little-river-trailer-inventory' ),
			),
			'featured'            => array(
				'tab'      => 'pricing',
				'label'    => __( 'Featured Trailer', 'little-river-trailer-inventory' ),
				'type'     => 'checkbox',
				'sanitize' => 'checkbox',
				'cbtext'   => __( 'Mark this trailer as featured.', 'little-river-trailer-inventory' ),
			),
			'sale_badge'          => array(
				'tab'      => 'pricing',
				'label'    => __( 'Sale Badge', 'little-river-trailer-inventory' ),
				'type'     => 'checkbox',
				'sanitize' => 'checkbox',
				'cbtext'   => __( 'Display a "Sale" badge on this trailer.', 'little-river-trailer-inventory' ),
			),
			'financing_message'   => array(
				'tab'      => 'pricing',
				'label'    => __( 'Financing Message', 'little-river-trailer-inventory' ),
				'type'     => 'textarea',
				'sanitize' => 'textarea',
				'desc'     => __( 'Optional note about financing. Only shown if entered.', 'little-river-trailer-inventory' ),
			),

			// ---- Specifications: Dimensions -----------------------------
			'floor_length'        => array( 'tab' => 'specs', 'group' => 'dimensions', 'label' => __( 'Floor Length', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'ft', 'little-river-trailer-inventory' ) ),
			'overall_length'      => array( 'tab' => 'specs', 'group' => 'dimensions', 'label' => __( 'Overall Length', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'ft', 'little-river-trailer-inventory' ) ),
			'width'               => array( 'tab' => 'specs', 'group' => 'dimensions', 'label' => __( 'Width', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'in', 'little-river-trailer-inventory' ) ),
			'height'              => array( 'tab' => 'specs', 'group' => 'dimensions', 'label' => __( 'Height', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'in', 'little-river-trailer-inventory' ) ),
			'gvwr'                => array( 'tab' => 'specs', 'group' => 'dimensions', 'label' => __( 'GVWR', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'lbs', 'little-river-trailer-inventory' ), 'desc' => __( 'Gross Vehicle Weight Rating.', 'little-river-trailer-inventory' ) ),
			'empty_weight'        => array( 'tab' => 'specs', 'group' => 'dimensions', 'label' => __( 'Empty Weight', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'lbs', 'little-river-trailer-inventory' ) ),
			'payload_capacity'    => array( 'tab' => 'specs', 'group' => 'dimensions', 'label' => __( 'Payload Capacity', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'lbs', 'little-river-trailer-inventory' ), 'desc' => __( 'Leave blank to auto-calculate from GVWR minus Empty Weight.', 'little-river-trailer-inventory' ) ),

			// ---- Specifications: Axles ----------------------------------
			'axle_count'          => array( 'tab' => 'specs', 'group' => 'axles', 'label' => __( 'Axle Count', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'int', 'placeholder' => '2' ),
			'axle_rating'         => array( 'tab' => 'specs', 'group' => 'axles', 'label' => __( 'Axle Rating (each)', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'lbs', 'little-river-trailer-inventory' ), 'placeholder' => '6000' ),

			// ---- Specifications: Suspension / Brakes / Hitch (dropdowns) -
			'suspension_type'     => array(
				'tab' => 'specs', 'group' => 'suspension', 'label' => __( 'Suspension', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text',
				'options' => array( 'Leaf Spring', 'Torsion', 'Air Ride', 'Hydraulic', 'Other' ),
			),
			'brake_type'          => array(
				'tab' => 'specs', 'group' => 'brakes', 'label' => __( 'Brakes', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text',
				'options' => array( 'Electric', 'Electric Over Hydraulic', 'Hydraulic Surge', 'None' ),
			),
			'pull_type'           => array(
				'tab' => 'specs', 'group' => 'hitch', 'label' => __( 'Pull Type', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text',
				'options' => array( 'Bumper Pull', 'Gooseneck', 'Pintle', 'Fifth Wheel' ),
			),

			// ---- Specifications: Frame ----------------------------------
			'side_height'         => array( 'tab' => 'specs', 'group' => 'frame', 'label' => __( 'Side Height', 'little-river-trailer-inventory' ), 'type' => 'text', 'sanitize' => 'text', 'placeholder' => __( 'e.g. 24 in', 'little-river-trailer-inventory' ) ),

			// ---- Specifications: Ramp & Flooring (dropdowns) ------------
			'ramp_type'           => array(
				'tab' => 'specs', 'group' => 'body', 'label' => __( 'Ramp', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text',
				'options' => array( 'Slide-In', 'Fold-Up', 'Stand-Up', 'Mega Ramp', 'No Ramp' ),
			),
			'flooring'            => array(
				'tab' => 'specs', 'group' => 'body', 'label' => __( 'Flooring', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text',
				'options' => array( 'Pressure Treated Wood', 'Steel', 'Aluminum', 'Composite' ),
			),

			// ---- Specifications: Appearance (color dropdowns) -----------
			'exterior_color'      => array( 'tab' => 'specs', 'group' => 'appearance', 'label' => __( 'Exterior Color', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => $colors ),
			'interior_color'      => array( 'tab' => 'specs', 'group' => 'appearance', 'label' => __( 'Interior Color', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => $colors ),

			// ---- Specifications: Hardware (dropdowns) -------------------
			'coupler_type'        => array(
				'tab' => 'specs', 'group' => 'hardware', 'label' => __( 'Coupler', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text',
				'options' => array( '2"', '2-5/16"', 'Adjustable', 'Pintle Eye', 'Gooseneck Coupler' ),
			),
			'jack_type'           => array(
				'tab' => 'specs', 'group' => 'hardware', 'label' => __( 'Jack', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text',
				'options' => array( 'Top Wind', 'Side Wind', 'Drop Leg', 'Hydraulic', 'Electric' ),
			),

			// ---- Specifications: Additional Features --------------------
			'additional_features' => array(
				'tab'      => 'specs',
				'group'    => 'features',
				'label'    => __( 'Additional Features', 'little-river-trailer-inventory' ),
				'type'     => 'textarea',
				'sanitize' => 'textarea',
				'desc'     => __( 'Free-form notes. For standardized, filterable features use the Features box on the trailer sidebar.', 'little-river-trailer-inventory' ),
			),

			// ---- Specifications: Hitch and Coupler (additions) ----------
			'adjustable_hitch'        => array( 'tab' => 'specs', 'group' => 'hitch', 'label' => __( 'Adjustable Hitch', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),
			'adjustable_coupler'      => array( 'tab' => 'specs', 'group' => 'hitch', 'label' => __( 'Adjustable Coupler', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),
			'coupler_ball_size'       => array( 'tab' => 'specs', 'group' => 'hitch', 'label' => __( 'Coupler Ball Size', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( '2 inch', '2-5/16 inch', '3 inch', 'Pintle Ring', 'Gooseneck Ball', 'Other' ) ),
			'coupler_adjustment_range' => array( 'tab' => 'specs', 'group' => 'hitch', 'label' => __( 'Coupler Adjustment Range', 'little-river-trailer-inventory' ), 'type' => 'text', 'sanitize' => 'text', 'placeholder' => __( 'e.g. 5-position channel', 'little-river-trailer-inventory' ) ),
			'hitch_height_range'      => array( 'tab' => 'specs', 'group' => 'hitch', 'label' => __( 'Hitch Height Range', 'little-river-trailer-inventory' ), 'type' => 'text', 'sanitize' => 'text', 'placeholder' => __( 'e.g. 18–24 inches', 'little-river-trailer-inventory' ) ),
			'safety_chains_included'  => array( 'tab' => 'specs', 'group' => 'hitch', 'label' => __( 'Safety Chains Included', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),
			'breakaway_kit_included'  => array( 'tab' => 'specs', 'group' => 'hitch', 'label' => __( 'Breakaway Kit Included', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),

			// ---- Specifications: Wheels and Tires -----------------------
			'tire_size'               => array( 'tab' => 'specs', 'group' => 'wheels', 'label' => __( 'Tire Size', 'little-river-trailer-inventory' ), 'type' => 'text', 'sanitize' => 'text', 'placeholder' => __( 'e.g. 235/75R16', 'little-river-trailer-inventory' ) ),
			'tire_type'               => array( 'tab' => 'specs', 'group' => 'wheels', 'label' => __( 'Tire Type', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Radial', 'Bias Ply', 'All-Terrain', 'Highway', 'Commercial', 'Other' ) ),
			'tire_load_range'         => array( 'tab' => 'specs', 'group' => 'wheels', 'label' => __( 'Tire Load Range', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Load Range C', 'Load Range D', 'Load Range E', 'Load Range F', 'Load Range G', 'Other' ) ),
			'tire_ply_rating'         => array( 'tab' => 'specs', 'group' => 'wheels', 'label' => __( 'Tire Ply Rating', 'little-river-trailer-inventory' ), 'type' => 'text', 'sanitize' => 'text', 'placeholder' => __( 'e.g. 10-ply', 'little-river-trailer-inventory' ) ),
			'tire_brand'              => array( 'tab' => 'specs', 'group' => 'wheels', 'label' => __( 'Tire Brand', 'little-river-trailer-inventory' ), 'type' => 'text', 'sanitize' => 'text' ),
			'wheel_diameter'          => array( 'tab' => 'specs', 'group' => 'wheels', 'label' => __( 'Wheel Diameter', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'in', 'little-river-trailer-inventory' ) ),
			'wheel_width'             => array( 'tab' => 'specs', 'group' => 'wheels', 'label' => __( 'Wheel Width', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'in', 'little-river-trailer-inventory' ) ),
			'wheel_material'          => array( 'tab' => 'specs', 'group' => 'wheels', 'label' => __( 'Wheel Material', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Steel', 'Aluminum', 'Other' ) ),
			'wheel_finish'            => array( 'tab' => 'specs', 'group' => 'wheels', 'label' => __( 'Wheel Finish', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Black', 'Silver', 'White', 'Chrome', 'Machined', 'Powder-Coated', 'Other' ) ),
			'wheel_bolt_pattern'      => array( 'tab' => 'specs', 'group' => 'wheels', 'label' => __( 'Wheel Bolt Pattern', 'little-river-trailer-inventory' ), 'type' => 'text', 'sanitize' => 'text', 'placeholder' => __( 'e.g. 8 on 6.5', 'little-river-trailer-inventory' ) ),
			'spare_tire_included'     => array( 'tab' => 'specs', 'group' => 'wheels', 'label' => __( 'Spare Tire Included', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),
			'spare_tire_mount_included' => array( 'tab' => 'specs', 'group' => 'wheels', 'label' => __( 'Spare Tire Mount Included', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),
			'spare_tire_size'         => array( 'tab' => 'specs', 'group' => 'wheels', 'label' => __( 'Spare Tire Size', 'little-river-trailer-inventory' ), 'type' => 'text', 'sanitize' => 'text' ),
			'number_of_tires'         => array( 'tab' => 'specs', 'group' => 'wheels', 'label' => __( 'Number of Tires', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'int' ),
			'tire_notes'              => array( 'tab' => 'specs', 'group' => 'wheels', 'label' => __( 'Tire Notes', 'little-river-trailer-inventory' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),

			// ---- Specifications: Lighting and Electrical ----------------
			'led_lights'              => array( 'tab' => 'specs', 'group' => 'lighting', 'label' => __( 'LED Lights', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),
			'light_types'             => array(
				'tab' => 'specs', 'group' => 'lighting', 'label' => __( 'Light Types', 'little-river-trailer-inventory' ), 'type' => 'checkboxes', 'sanitize' => 'set',
				'options' => array( 'LED Tail Lights', 'LED Clearance Lights', 'LED Marker Lights', 'LED Backup Lights', 'LED Work Lights', 'Interior LED Lights', 'Underbody Lights', 'Strobe Lights', 'Other' ),
			),
			'electrical_connector'    => array( 'tab' => 'specs', 'group' => 'lighting', 'label' => __( 'Electrical Connector', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( '4-Way Flat', '5-Way Flat', '6-Way Round', '7-Way RV', '7-Way Round', 'Other' ) ),
			'junction_box'            => array( 'tab' => 'specs', 'group' => 'lighting', 'label' => __( 'Junction Box', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),
			'battery_included'        => array( 'tab' => 'specs', 'group' => 'lighting', 'label' => __( 'Battery Included', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),
			'battery_box_included'    => array( 'tab' => 'specs', 'group' => 'lighting', 'label' => __( 'Battery Box Included', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),
			'solar_charger_included'  => array( 'tab' => 'specs', 'group' => 'lighting', 'label' => __( 'Solar Charger Included', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),
			'lighting_notes'          => array( 'tab' => 'specs', 'group' => 'lighting', 'label' => __( 'Lighting Notes', 'little-river-trailer-inventory' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),

			// ---- Specifications: Toolbox --------------------------------
			'toolbox_included'        => array( 'tab' => 'specs', 'group' => 'toolbox', 'label' => __( 'Toolbox Included', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),
			'toolbox_type'            => array( 'tab' => 'specs', 'group' => 'toolbox', 'label' => __( 'Toolbox Type', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Tongue Toolbox', 'A-Frame Toolbox', 'Underbody Toolbox', 'Side-Mount Toolbox', 'Lockable Toolbox', 'Other' ) ),
			'toolbox_width'           => array( 'tab' => 'specs', 'group' => 'toolbox', 'label' => __( 'Toolbox Width', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'in', 'little-river-trailer-inventory' ) ),
			'toolbox_height'          => array( 'tab' => 'specs', 'group' => 'toolbox', 'label' => __( 'Toolbox Height', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'in', 'little-river-trailer-inventory' ) ),
			'toolbox_depth'           => array( 'tab' => 'specs', 'group' => 'toolbox', 'label' => __( 'Toolbox Depth', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'in', 'little-river-trailer-inventory' ) ),
			'toolbox_material'        => array( 'tab' => 'specs', 'group' => 'toolbox', 'label' => __( 'Toolbox Material', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Steel', 'Aluminum', 'Diamond Plate', 'Other' ) ),
			'toolbox_notes'           => array( 'tab' => 'specs', 'group' => 'toolbox', 'label' => __( 'Toolbox Notes', 'little-river-trailer-inventory' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),

			// ---- Specifications: Stake Pockets and Tie-Downs ------------
			'stake_pockets'           => array( 'tab' => 'specs', 'group' => 'tiedowns', 'label' => __( 'Stake Pockets', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),
			'stake_pocket_count'      => array( 'tab' => 'specs', 'group' => 'tiedowns', 'label' => __( 'Stake Pocket Count', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'int' ),
			'stake_pocket_size'       => array( 'tab' => 'specs', 'group' => 'tiedowns', 'label' => __( 'Stake Pocket Size', 'little-river-trailer-inventory' ), 'type' => 'text', 'sanitize' => 'text', 'placeholder' => __( 'e.g. 2 x 4 inches', 'little-river-trailer-inventory' ) ),
			'stake_pocket_spacing'    => array( 'tab' => 'specs', 'group' => 'tiedowns', 'label' => __( 'Stake Pocket Spacing', 'little-river-trailer-inventory' ), 'type' => 'text', 'sanitize' => 'text', 'placeholder' => __( 'e.g. 24 inches on center', 'little-river-trailer-inventory' ) ),
			'rub_rail_included'       => array( 'tab' => 'specs', 'group' => 'tiedowns', 'label' => __( 'Rub Rail Included', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),
			'dring_count'             => array( 'tab' => 'specs', 'group' => 'tiedowns', 'label' => __( 'D-Ring Count', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'int' ),
			'dring_rating'            => array( 'tab' => 'specs', 'group' => 'tiedowns', 'label' => __( 'D-Ring Rating', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'lbs', 'little-river-trailer-inventory' ) ),
			'bull_rings_included'     => array( 'tab' => 'specs', 'group' => 'tiedowns', 'label' => __( 'Bull Rings Included', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),
			'tiedown_notes'           => array( 'tab' => 'specs', 'group' => 'tiedowns', 'label' => __( 'Tie-Down Notes', 'little-river-trailer-inventory' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),

			// ---- Specifications: Ramps ----------------------------------
			'ramps_included'          => array( 'tab' => 'specs', 'group' => 'ramps', 'label' => __( 'Ramps Included', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Yes', 'No' ) ),
			'ramp_style'              => array( 'tab' => 'specs', 'group' => 'ramps', 'label' => __( 'Ramp Style', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Slide-In', 'Stand-Up', 'Flip-Over', 'Fold-Up', 'Pull-Out', 'Spring-Assisted', 'Hydraulic', 'No Ramp', 'Other' ) ),
			'ramp_length'             => array( 'tab' => 'specs', 'group' => 'ramps', 'label' => __( 'Ramp Length', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'ft', 'little-river-trailer-inventory' ) ),
			'ramp_width'              => array( 'tab' => 'specs', 'group' => 'ramps', 'label' => __( 'Ramp Width', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'in', 'little-river-trailer-inventory' ) ),
			'ramp_material'           => array( 'tab' => 'specs', 'group' => 'ramps', 'label' => __( 'Ramp Material', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Steel', 'Aluminum', 'Wood', 'Other' ) ),
			'ramp_storage'            => array( 'tab' => 'specs', 'group' => 'ramps', 'label' => __( 'Ramp Storage Location', 'little-river-trailer-inventory' ), 'type' => 'select', 'sanitize' => 'text', 'options' => array( 'Under Deck', 'Rear', 'Side', 'Upright', 'Other' ) ),
			'ramp_capacity'           => array( 'tab' => 'specs', 'group' => 'ramps', 'label' => __( 'Ramp Capacity', 'little-river-trailer-inventory' ), 'type' => 'number', 'sanitize' => 'decimal', 'unit' => __( 'lbs', 'little-river-trailer-inventory' ) ),
			'ramp_notes'              => array( 'tab' => 'specs', 'group' => 'ramps', 'label' => __( 'Ramp Notes', 'little-river-trailer-inventory' ), 'type' => 'textarea', 'sanitize' => 'textarea' ),

			// ---- SEO ----------------------------------------------------
			'seo_meta_title'       => array(
				'tab'      => 'seo',
				'label'    => __( 'Meta Title', 'little-river-trailer-inventory' ),
				'type'     => 'text',
				'sanitize' => 'text',
				'desc'     => __( 'Title for search engines. If blank, the trailer title is used. Aim for about 60 characters.', 'little-river-trailer-inventory' ),
			),
			'seo_meta_description' => array(
				'tab'      => 'seo',
				'label'    => __( 'Meta Description', 'little-river-trailer-inventory' ),
				'type'     => 'textarea',
				'sanitize' => 'textarea',
				'desc'     => __( 'Short summary shown in search results. Aim for about 155 characters.', 'little-river-trailer-inventory' ),
			),
			'seo_focus_keyword'    => array(
				'tab'      => 'seo',
				'label'    => __( 'Focus Keyword', 'little-river-trailer-inventory' ),
				'type'     => 'text',
				'sanitize' => 'text',
				'desc'     => __( 'Saved for future use.', 'little-river-trailer-inventory' ),
			),
			'seo_canonical_url'    => array(
				'tab'      => 'seo',
				'label'    => __( 'Canonical URL', 'little-river-trailer-inventory' ),
				'type'     => 'url',
				'sanitize' => 'url',
				'desc'     => __( 'Saved for future use. Leave blank unless you know you need it.', 'little-river-trailer-inventory' ),
			),
			'seo_og_image'         => array(
				'tab'      => 'seo',
				'label'    => __( 'Open Graph Image', 'little-river-trailer-inventory' ),
				'type'     => 'url',
				'sanitize' => 'url',
				'desc'     => __( 'Saved for future use. Image shown when the page is shared on social media.', 'little-river-trailer-inventory' ),
			),
			'seo_schema_override'  => array(
				'tab'      => 'seo',
				'label'    => __( 'Schema Override', 'little-river-trailer-inventory' ),
				'type'     => 'textarea',
				'sanitize' => 'textarea',
				'desc'     => __( 'Advanced. Saved for future use.', 'little-river-trailer-inventory' ),
			),
		);
	}

	/* --------------------------------------------------------------------- *
	 * Meta box setup
	 * --------------------------------------------------------------------- */

	/**
	 * Show helper text under the Description editor on the trailer screen.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function description_helper_text( $post ): void {
		if ( ! ( $post instanceof \WP_Post ) || PostTypes::POST_TYPE !== $post->post_type ) {
			return;
		}
		echo '<p class="description" style="margin-top:8px;">'
			. esc_html__( 'Use the Description for customer-facing details and selling points. Enter structured technical information in the Specifications tab.', 'little-river-trailer-inventory' )
			. '</p>';
	}

	/**
	 * Register our single tabbed meta box and remove the default taxonomy and
	 * featured-image boxes (we render those ourselves inside the tabs).
	 *
	 * @return void
	 */
	public function setup_meta_boxes(): void {
		foreach ( array_keys( $this->taxonomy_dropdowns() ) as $tax ) {
			remove_meta_box( $tax . 'div', PostTypes::POST_TYPE, 'side' );
		}

		remove_meta_box( 'postimagediv', PostTypes::POST_TYPE, 'side' );

		// Rename the core Excerpt box to "Short Description" (storage unchanged).
		remove_meta_box( 'postexcerpt', PostTypes::POST_TYPE, 'normal' );
		add_meta_box(
			'postexcerpt',
			__( 'Short Description', 'little-river-trailer-inventory' ),
			'post_excerpt_meta_box',
			PostTypes::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'lrti_trailer_details',
			__( 'Trailer Details', 'little-river-trailer-inventory' ),
			array( $this, 'render_editor' ),
			PostTypes::POST_TYPE,
			'normal',
			'high'
		);
	}

	/* --------------------------------------------------------------------- *
	 * Rendering
	 * --------------------------------------------------------------------- */

	/**
	 * Render the whole tabbed editor.
	 *
	 * @param \WP_Post $post The trailer being edited.
	 * @return void
	 */
	public function render_editor( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$tabs    = $this->tabs();
		$post_id = (int) $post->ID;

		// Determine whether the title should keep auto-generating.
		$stored_auto = get_post_meta( $post_id, '_lrti_title_is_auto', true );
		if ( '' === $stored_auto ) {
			$is_auto = ( '' === trim( (string) $post->post_title ) ) ? '1' : '0';
		} else {
			$is_auto = ( '1' === $stored_auto ) ? '1' : '0';
		}

		echo '<div class="lrti-editor" data-post="' . esc_attr( (string) $post_id ) . '">';
		printf( '<input type="hidden" id="lrti_title_auto" name="lrti_title_auto" value="%s" />', esc_attr( $is_auto ) );

		echo '<h2 class="nav-tab-wrapper lrti-nav" role="tablist">';
		$first = true;
		foreach ( $tabs as $key => $label ) {
			printf(
				'<a href="#" class="nav-tab lrti-nav-tab%1$s" data-tab="%2$s" role="tab" aria-selected="%3$s">%4$s</a>',
				$first ? ' nav-tab-active' : '',
				esc_attr( $key ),
				$first ? 'true' : 'false',
				esc_html( $label )
			);
			$first = false;
		}
		echo '</h2>';

		$first = true;
		foreach ( $tabs as $key => $label ) {
			printf(
				'<div class="lrti-tab-panel%1$s" data-tab="%2$s" role="tabpanel">',
				$first ? ' is-active' : '',
				esc_attr( $key )
			);
			$this->render_panel( $key, $post_id );
			echo '</div>';
			$first = false;
		}

		echo '</div>';
	}

	/**
	 * Render one tab panel.
	 *
	 * @param string $tab     The tab key.
	 * @param int    $post_id The trailer post ID.
	 * @return void
	 */
	private function render_panel( string $tab, int $post_id ): void {
		switch ( $tab ) {
			case 'general':
				$this->render_general( $post_id );
				break;
			case 'pricing':
				$this->render_pricing( $post_id );
				break;
			case 'specs':
				$this->render_specs( $post_id );
				break;
			case 'photos':
				$this->render_photos( $post_id );
				break;
			case 'seo':
				$this->render_seo( $post_id );
				break;
			case 'notes':
				$this->render_notes( $post_id );
				break;
		}
	}

	/**
	 * General tab: fields ordered to the dealership sales workflow.
	 *
	 * Order: (Title above) Year, Manufacturer, Model, Trailer Type, Condition,
	 * Availability, Stock Number, VIN.
	 *
	 * @param int $post_id The trailer post ID.
	 * @return void
	 */
	private function render_general( int $post_id ): void {
		$fields = $this->meta_fields();

		echo '<p class="description lrti-title-hint">' . esc_html__( 'Tip: the Trailer Title above will auto-fill from Year, Manufacturer, Model, and Trailer Type until you edit it yourself.', 'little-river-trailer-inventory' ) . '</p>';

		echo '<table class="form-table lrti-form-table" role="presentation"><tbody>';

		// Sequence mixes meta fields and taxonomy dropdowns in workflow order.
		$sequence = array(
			array( 'meta', 'year' ),
			array( 'tax', 'trailer_manufacturer' ),
			array( 'meta', 'model' ),
			array( 'tax', 'trailer_type' ),
			array( 'tax', 'trailer_condition' ),
			array( 'tax', 'trailer_availability' ),
			array( 'meta', 'stock_number' ),
			array( 'meta', 'vin' ),
		);

		$tax_labels = $this->taxonomy_dropdowns();

		foreach ( $sequence as $item ) {
			list( $kind, $key ) = $item;
			if ( 'meta' === $kind ) {
				$this->render_field_row( $key, $fields[ $key ], $post_id );
			} else {
				$label = $tax_labels[ $key ] ?? $key;
				$this->render_taxonomy_row( $key, $label, $post_id );
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Pricing tab.
	 *
	 * @param int $post_id The trailer post ID.
	 * @return void
	 */
	private function render_pricing( int $post_id ): void {
		$fields = $this->meta_fields();

		echo '<table class="form-table lrti-form-table" role="presentation"><tbody>';

		$this->render_field_row( 'regular_price', $fields['regular_price'], $post_id );
		$this->render_field_row( 'sale_price', $fields['sale_price'], $post_id );
		$this->render_field_row( 'msrp', $fields['msrp'], $post_id );

		$savings = (string) get_post_meta( $post_id, '_lrti_savings', true );
		echo '<tr class="lrti-savings-row">';
		echo '<th scope="row">' . esc_html__( 'Savings', 'little-river-trailer-inventory' ) . '</th>';
		echo '<td>';
		printf(
			'<input type="text" id="lrti_savings_display" class="regular-text" value="%s" readonly disabled />',
			esc_attr( '' !== $savings ? lrti_format_price( $savings ) : '' )
		);
		echo '<p class="description">' . esc_html__( 'Calculated automatically from MSRP minus the selling price. Not editable.', 'little-river-trailer-inventory' ) . '</p>';
		echo '</td></tr>';

		foreach ( array( 'call_for_price', 'hide_price', 'featured', 'sale_badge', 'financing_message' ) as $key ) {
			$this->render_field_row( $key, $fields[ $key ], $post_id );
		}

		echo '</tbody></table>';
	}

	/**
	 * Specifications tab: grouped, collapsible sections.
	 *
	 * @param int $post_id The trailer post ID.
	 * @return void
	 */
	private function render_specs( int $post_id ): void {
		$fields = $this->meta_fields();
		$groups = $this->spec_groups();

		foreach ( $groups as $group_key => $group ) {
			$group_fields = array();
			foreach ( $fields as $key => $cfg ) {
				if ( 'specs' === ( $cfg['tab'] ?? '' ) && $group_key === ( $cfg['group'] ?? '' ) ) {
					$group_fields[ $key ] = $cfg;
				}
			}

			if ( empty( $group_fields ) ) {
				continue;
			}

			printf(
				'<details class="lrti-spec-group"%s>',
				! empty( $group['open'] ) ? ' open' : ''
			);
			echo '<summary class="lrti-spec-heading">' . esc_html( $group['label'] ) . '</summary>';
			echo '<table class="form-table lrti-form-table" role="presentation"><tbody>';
			foreach ( $group_fields as $key => $cfg ) {
				$this->render_field_row( $key, $cfg, $post_id );
			}
			echo '</tbody></table>';
			echo '</details>';
		}
	}

	/**
	 * Photos tab: custom featured image control + existing gallery.
	 *
	 * @param int $post_id The trailer post ID.
	 * @return void
	 */
	private function render_photos( int $post_id ): void {
		$thumb_id = (int) get_post_thumbnail_id( $post_id );

		echo '<h2 class="title lrti-spec-heading">' . esc_html__( 'Main Image', 'little-river-trailer-inventory' ) . '</h2>';
		echo '<div class="lrti-featured" id="lrti-featured">';
		echo '<div class="lrti-featured-preview" id="lrti-featured-preview">';
		if ( $thumb_id > 0 ) {
			echo wp_get_attachment_image( $thumb_id, 'medium' );
		}
		echo '</div>';
		printf(
			'<input type="hidden" id="lrti_featured_image_id" name="lrti_featured_image_id" value="%s" />',
			esc_attr( $thumb_id > 0 ? (string) $thumb_id : '' )
		);
		echo '<p>';
		$lrti_set_label = ( $thumb_id > 0 ) ? __( 'Replace Image', 'little-river-trailer-inventory' ) : __( 'Set Main Image', 'little-river-trailer-inventory' );
		echo '<button type="button" class="button" id="lrti-featured-set" data-label-set="' . esc_attr__( 'Set Main Image', 'little-river-trailer-inventory' ) . '" data-label-replace="' . esc_attr__( 'Replace Image', 'little-river-trailer-inventory' ) . '">' . esc_html( $lrti_set_label ) . '</button> ';
		echo '<button type="button" class="button lrti-featured-remove-btn" id="lrti-featured-remove"' . ( $thumb_id > 0 ? '' : ' style="display:none;"' ) . '>' . esc_html__( 'Remove Main Image', 'little-river-trailer-inventory' ) . '</button>';
		echo '</p>';
		echo '</div>';

		$ids_raw = (string) get_post_meta( $post_id, '_lrti_gallery_ids', true );
		$ids     = array_filter( array_map( 'absint', explode( ',', $ids_raw ) ) );

		echo '<h2 class="title lrti-spec-heading">' . esc_html__( 'Photo Gallery', 'little-river-trailer-inventory' ) . '</h2>';
		echo '<div class="lrti-gallery" id="lrti-gallery">';
		echo '<p class="description">' . esc_html__( 'Extra photos beyond the main image. Order follows the order added.', 'little-river-trailer-inventory' ) . '</p>';
		echo '<ul class="lrti-gallery-list" id="lrti-gallery-list">';
		foreach ( $ids as $id ) {
			$thumb = wp_get_attachment_image( $id, 'thumbnail' );
			if ( '' === $thumb ) {
				continue;
			}
			echo '<li class="lrti-gallery-item" data-id="' . esc_attr( (string) $id ) . '">';
			echo $thumb;
			echo '<button type="button" class="lrti-gallery-remove" aria-label="' . esc_attr__( 'Remove image', 'little-river-trailer-inventory' ) . '">&times;</button>';
			echo '</li>';
		}
		echo '</ul>';
		printf(
			'<input type="hidden" id="lrti_gallery_ids" name="lrti_gallery_ids" value="%s" />',
			esc_attr( implode( ',', $ids ) )
		);
		echo '<p><button type="button" class="button" id="lrti-gallery-add">' . esc_html__( 'Add Images', 'little-river-trailer-inventory' ) . '</button></p>';
		echo '</div>';
	}

	/**
	 * SEO tab: real fields. Meta Title/Description are ready to use; the rest
	 * save values for future use.
	 *
	 * @param int $post_id The trailer post ID.
	 * @return void
	 */
	private function render_seo( int $post_id ): void {
		$fields = $this->meta_fields();

		echo '<table class="form-table lrti-form-table" role="presentation"><tbody>';

		foreach ( array( 'seo_meta_title', 'seo_meta_description', 'seo_focus_keyword', 'seo_canonical_url' ) as $key ) {
			$this->render_field_row( $key, $fields[ $key ], $post_id );
		}

		// Open Graph image: URL field plus a media-picker button and preview.
		$og_value = (string) get_post_meta( $post_id, '_lrti_seo_og_image', true );
		echo '<tr>';
		echo '<th scope="row"><label for="lrti_field_seo_og_image">' . esc_html__( 'Open Graph Image', 'little-river-trailer-inventory' ) . '</label></th>';
		echo '<td>';
		printf(
			'<input type="text" id="lrti_field_seo_og_image" name="lrti_meta[seo_og_image]" value="%s" class="large-text" placeholder="https://…" />',
			esc_attr( $og_value )
		);
		echo '<p><button type="button" class="button" id="lrti-og-set">' . esc_html__( 'Select Image', 'little-river-trailer-inventory' ) . '</button> ';
		echo '<button type="button" class="button" id="lrti-og-clear"' . ( '' !== $og_value ? '' : ' style="display:none;"' ) . '>' . esc_html__( 'Clear', 'little-river-trailer-inventory' ) . '</button></p>';
		echo '<div class="lrti-og-preview" id="lrti-og-preview">';
		if ( '' !== $og_value ) {
			printf( '<img src="%s" alt="" />', esc_url( $og_value ) );
		}
		echo '</div>';
		echo '<p class="description">' . esc_html( (string) $fields['seo_og_image']['desc'] ) . '</p>';
		echo '</td></tr>';

		$this->render_field_row( 'seo_schema_override', $fields['seo_schema_override'], $post_id );

		echo '</tbody></table>';
	}

	/**
	 * Internal Notes tab.
	 *
	 * @param int $post_id The trailer post ID.
	 * @return void
	 */
	private function render_notes( int $post_id ): void {
		$value = (string) get_post_meta( $post_id, '_lrti_internal_notes', true );

		echo '<p class="description lrti-warning">' . esc_html__( 'These notes are for staff only and are never shown on the public website.', 'little-river-trailer-inventory' ) . '</p>';
		printf(
			'<textarea id="lrti_internal_notes" name="lrti_internal_notes" rows="6" class="large-text">%s</textarea>',
			esc_textarea( $value )
		);
	}

	/**
	 * Render a single form-table row for a meta field.
	 *
	 * @param string               $key     The field key.
	 * @param array<string, mixed> $cfg     The field config.
	 * @param int                  $post_id The trailer post ID.
	 * @return void
	 */
	private function render_field_row( string $key, array $cfg, int $post_id ): void {
		$type        = (string) ( $cfg['type'] ?? 'text' );
		$label       = (string) ( $cfg['label'] ?? $key );
		$desc        = (string) ( $cfg['desc'] ?? '' );
		$unit        = (string) ( $cfg['unit'] ?? '' );
		$placeholder = (string) ( $cfg['placeholder'] ?? '' );
		$required    = ! empty( $cfg['required'] );
		$raw_value   = get_post_meta( $post_id, '_lrti_' . $key, true );
		$value       = is_scalar( $raw_value ) ? (string) $raw_value : '';
		$field_id    = 'lrti_field_' . $key;
		$field_name  = 'lrti_meta[' . $key . ']';

		echo '<tr>';

		if ( 'checkbox' === $type ) {
			echo '<th scope="row">' . esc_html( $label ) . '</th>';
		} else {
			echo '<th scope="row"><label for="' . esc_attr( $field_id ) . '">' . esc_html( $label );
			if ( $required ) {
				echo ' <span class="lrti-required" aria-hidden="true">*</span>';
			}
			echo '</label></th>';
		}

		echo '<td>';

		switch ( $type ) {
			case 'checkbox':
				printf(
					'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $field_id ),
					esc_attr( $field_name ),
					checked( '1', $value, false ),
					esc_html( (string) ( $cfg['cbtext'] ?? '' ) )
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="3" class="large-text" placeholder="%3$s">%4$s</textarea>',
					esc_attr( $field_id ),
					esc_attr( $field_name ),
					esc_attr( $placeholder ),
					esc_textarea( $value )
				);
				break;

			case 'select':
				$options = isset( $cfg['options'] ) && is_array( $cfg['options'] ) ? $cfg['options'] : array();
				echo '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '">';
				echo '<option value="">' . esc_html__( '— Not Specified —', 'little-river-trailer-inventory' ) . '</option>';
				$found = false;
				foreach ( $options as $option ) {
					$option = (string) $option;
					$sel    = selected( $value, $option, false );
					if ( '' !== $sel ) {
						$found = true;
					}
					echo '<option value="' . esc_attr( $option ) . '" ' . $sel . '>' . esc_html( $option ) . '</option>';
				}
				// Preserve any legacy value not in the current option list.
				if ( ! $found && '' !== $value ) {
					echo '<option value="' . esc_attr( $value ) . '" selected>' . esc_html( $value ) . '</option>';
				}
				echo '</select>';
				break;

			case 'checkboxes':
				$options  = isset( $cfg['options'] ) && is_array( $cfg['options'] ) ? $cfg['options'] : array();
				$selected = (array) get_post_meta( $post_id, '_lrti_' . $key, true );
				echo '<fieldset class="lrti-checkbox-group"><legend class="screen-reader-text">' . esc_html( $label ) . '</legend>';
				foreach ( $options as $i => $option ) {
					$option = (string) $option;
					$cb_id  = $field_id . '_' . $i;
					printf(
						'<label class="lrti-checkbox-option" for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[]" value="%3$s" %4$s /> %5$s</label>',
						esc_attr( $cb_id ),
						esc_attr( $field_name ),
						esc_attr( $option ),
						checked( in_array( $option, $selected, true ), true, false ),
						esc_html( $option )
					);
				}
				echo '</fieldset>';
				break;

			case 'price':
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text lrti-price-input" inputmode="decimal" placeholder="%4$s" />',
					esc_attr( $field_id ),
					esc_attr( $field_name ),
					esc_attr( '' !== $value ? lrti_format_price( $value ) : '' ),
					esc_attr( $placeholder )
				);
				break;

			case 'url':
				printf(
					'<input type="url" id="%1$s" name="%2$s" value="%3$s" class="large-text" placeholder="%4$s" />',
					esc_attr( $field_id ),
					esc_attr( $field_name ),
					esc_attr( $value ),
					esc_attr( '' !== $placeholder ? $placeholder : 'https://…' )
				);
				break;

			case 'number':
			case 'text':
			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" %4$s placeholder="%5$s" />',
					esc_attr( $field_id ),
					esc_attr( $field_name ),
					esc_attr( $value ),
					'number' === $type ? 'inputmode="decimal"' : '',
					esc_attr( $placeholder )
				);
				if ( '' !== $unit ) {
					echo ' <span class="lrti-unit">' . esc_html( $unit ) . '</span>';
				}
				break;
		}

		if ( '' !== $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}

		echo '</td></tr>';
	}

	/**
	 * Render a single-select taxonomy dropdown row.
	 *
	 * @param string $taxonomy The taxonomy key.
	 * @param string $label    The visible label.
	 * @param int    $post_id  The trailer post ID.
	 * @return void
	 */
	private function render_taxonomy_row( string $taxonomy, string $label, int $post_id ): void {
		$field_id = 'lrti_tax_' . $taxonomy;
		$name     = 'lrti_tax[' . $taxonomy . ']';

		$current = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		$sel     = ( ! is_wp_error( $current ) && ! empty( $current ) ) ? (int) $current[0] : 0;

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $field_id ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td>';
		echo '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '">';
		echo '<option value="0">' . esc_html__( '— Select —', 'little-river-trailer-inventory' ) . '</option>';

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				printf(
					'<option value="%1$d" %2$s>%3$s</option>',
					(int) $term->term_id,
					selected( $sel, (int) $term->term_id, false ),
					esc_html( $term->name )
				);
			}
		}

		echo '</select>';
		$edit_url = admin_url( 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=' . PostTypes::POST_TYPE );
		echo '<p class="description"><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Manage options', 'little-river-trailer-inventory' ) . '</a></p>';
		echo '</td></tr>';
	}

	/* --------------------------------------------------------------------- *
	 * Saving
	 * --------------------------------------------------------------------- */

	/**
	 * Save all trailer data from the tabbed editor.
	 *
	 * @param int      $post_id The trailer post ID.
	 * @param \WP_Post $post    The trailer post object.
	 * @return void
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$notices = array();
		$fields  = $this->meta_fields();

		// 1. Sanitize each defined meta field.
		$raw   = isset( $_POST['lrti_meta'] ) && is_array( $_POST['lrti_meta'] ) ? wp_unslash( $_POST['lrti_meta'] ) : array();
		$clean = array();
		$sets  = array();
		foreach ( $fields as $key => $cfg ) {
			// Multi-value checkbox groups store a whitelisted array, not a string.
			if ( 'set' === ( $cfg['sanitize'] ?? '' ) || 'checkboxes' === ( $cfg['type'] ?? '' ) ) {
				$submitted = isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ? $raw[ $key ] : array();
				$allowed   = isset( $cfg['options'] ) && is_array( $cfg['options'] ) ? array_map( 'strval', $cfg['options'] ) : array();
				$picked    = array();
				foreach ( $submitted as $candidate ) {
					$candidate = sanitize_text_field( is_scalar( $candidate ) ? (string) $candidate : '' );
					if ( '' !== $candidate && in_array( $candidate, $allowed, true ) && ! in_array( $candidate, $picked, true ) ) {
						$picked[] = $candidate;
					}
				}
				$sets[ $key ] = $picked;
				continue;
			}
			$clean[ $key ] = $this->sanitize_value( (string) ( $cfg['sanitize'] ?? 'text' ), $raw[ $key ] ?? '' );
		}

		// 2. Year validation.
		if ( '' !== $clean['year'] ) {
			$year = (int) $clean['year'];
			$max  = (int) gmdate( 'Y' ) + 2;
			if ( $year < 1900 || $year > $max ) {
				$notices[]     = array( 'type' => 'error', 'text' => __( 'Year must be a valid four-digit year and was not saved.', 'little-river-trailer-inventory' ) );
				$clean['year'] = '';
			}
		}

		// 3. Stock number: required + unique.
		if ( '' === $clean['stock_number'] ) {
			$notices[] = array( 'type' => 'error', 'text' => __( 'Stock Number is required. Please add one on the General tab.', 'little-river-trailer-inventory' ) );
		} else {
			$dup = $this->find_duplicate_stock( $clean['stock_number'], $post_id );
			if ( $dup > 0 ) {
				$notices[] = array(
					'type' => 'error',
					'text' => sprintf(
						/* translators: %s: stock number */
						__( 'Stock Number "%s" is already used by another trailer. Please use a unique stock number.', 'little-river-trailer-inventory' ),
						$clean['stock_number']
					),
				);
			}
		}

		// 4. Sale must not exceed Regular.
		if ( '' !== $clean['sale_price'] && '' !== $clean['regular_price'] && (float) $clean['sale_price'] > (float) $clean['regular_price'] ) {
			$notices[]           = array( 'type' => 'error', 'text' => __( 'Sale Price was greater than the Regular Price and was not saved.', 'little-river-trailer-inventory' ) );
			$clean['sale_price'] = '';
		}

		// 5. MSRP must not be less than Sale.
		if ( '' !== $clean['msrp'] && '' !== $clean['sale_price'] && (float) $clean['msrp'] < (float) $clean['sale_price'] ) {
			$notices[]     = array( 'type' => 'error', 'text' => __( 'MSRP cannot be less than the Sale Price and was not saved.', 'little-river-trailer-inventory' ) );
			$clean['msrp'] = '';
		}

		// 6. VIN friendly validation (non-blocking; value already normalized).
		if ( '' !== $clean['vin'] ) {
			if ( ! preg_match( '/^[A-HJ-NPR-Z0-9]{17}$/', $clean['vin'] ) ) {
				$notices[] = array(
					'type' => 'warning',
					'text' => __( 'The VIN does not look like a standard 17-character VIN (letters I, O, and Q are not used). It was saved as entered — please double-check.', 'little-river-trailer-inventory' ),
				);
			}
		}

		// 7. Empty weight vs GVWR warning.
		if ( '' !== $clean['empty_weight'] && '' !== $clean['gvwr'] && (float) $clean['empty_weight'] > (float) $clean['gvwr'] ) {
			$notices[] = array( 'type' => 'warning', 'text' => __( 'Warning: Empty Weight is greater than GVWR. Please double-check these values.', 'little-river-trailer-inventory' ) );
		}

		// 8. Payload auto-calculation when left blank.
		$payload_derived = false;
		if ( '' === $clean['payload_capacity'] && '' !== $clean['gvwr'] && '' !== $clean['empty_weight'] ) {
			$diff = (float) $clean['gvwr'] - (float) $clean['empty_weight'];
			if ( $diff > 0 ) {
				$clean['payload_capacity'] = (string) $diff;
				$payload_derived           = true;
			}
		}

		// 9. Persist meta values.
		foreach ( $clean as $key => $value ) {
			$meta_key = '_lrti_' . $key;
			if ( '' === $value ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $value );
			}
		}

		// 9b. Persist multi-value checkbox groups (stored as arrays).
		foreach ( $sets as $key => $values ) {
			$meta_key = '_lrti_' . $key;
			if ( empty( $values ) ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, array_values( $values ) );
			}
		}

		if ( $payload_derived ) {
			update_post_meta( $post_id, '_lrti_payload_derived', '1' );
		} else {
			delete_post_meta( $post_id, '_lrti_payload_derived' );
		}

		// 10. Savings = MSRP - selling price (sale if set, else regular).
		$selling = '' !== $clean['sale_price'] ? (float) $clean['sale_price'] : ( '' !== $clean['regular_price'] ? (float) $clean['regular_price'] : 0.0 );
		if ( '' !== $clean['msrp'] && $selling > 0 && (float) $clean['msrp'] > $selling ) {
			update_post_meta( $post_id, '_lrti_savings', (string) ( (float) $clean['msrp'] - $selling ) );
		} else {
			delete_post_meta( $post_id, '_lrti_savings' );
		}

		// 11. Taxonomy single-select dropdowns.
		$tax_raw = isset( $_POST['lrti_tax'] ) && is_array( $_POST['lrti_tax'] ) ? wp_unslash( $_POST['lrti_tax'] ) : array();
		foreach ( array_keys( $this->taxonomy_dropdowns() ) as $tax ) {
			$term_id = isset( $tax_raw[ $tax ] ) ? absint( $tax_raw[ $tax ] ) : 0;
			if ( $term_id > 0 && term_exists( $term_id, $tax ) ) {
				wp_set_object_terms( $post_id, array( $term_id ), $tax, false );
			} else {
				wp_set_object_terms( $post_id, array(), $tax, false );
			}
		}

		// 12. Featured (main) image.
		if ( isset( $_POST['lrti_featured_image_id'] ) ) {
			$thumb_id = absint( wp_unslash( $_POST['lrti_featured_image_id'] ) );
			if ( $thumb_id > 0 ) {
				set_post_thumbnail( $post_id, $thumb_id );
			} else {
				delete_post_thumbnail( $post_id );
			}
		}

		// 13. Gallery IDs.
		if ( isset( $_POST['lrti_gallery_ids'] ) ) {
			$gallery_raw = sanitize_text_field( wp_unslash( $_POST['lrti_gallery_ids'] ) );
			$gallery_ids = array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) );
			if ( ! empty( $gallery_ids ) ) {
				update_post_meta( $post_id, '_lrti_gallery_ids', implode( ',', $gallery_ids ) );
			} else {
				delete_post_meta( $post_id, '_lrti_gallery_ids' );
			}
		}

		// 14. Internal notes (private).
		if ( isset( $_POST['lrti_internal_notes'] ) ) {
			$notes = sanitize_textarea_field( wp_unslash( $_POST['lrti_internal_notes'] ) );
			if ( '' !== $notes ) {
				update_post_meta( $post_id, '_lrti_internal_notes', $notes );
			} else {
				delete_post_meta( $post_id, '_lrti_internal_notes' );
			}
		}

		// 15. Title auto-generation flag (convenience feature only).
		$auto_flag = isset( $_POST['lrti_title_auto'] ) && '1' === (string) wp_unslash( $_POST['lrti_title_auto'] ) ? '1' : '0';
		update_post_meta( $post_id, '_lrti_title_is_auto', $auto_flag );

		if ( ! empty( $notices ) ) {
			set_transient( self::NOTICE_TRANSIENT . get_current_user_id(), $notices, 60 );
		}
	}

	/**
	 * Sanitize a single value according to its declared type.
	 *
	 * @param string $type The sanitize type.
	 * @param mixed  $raw  The raw submitted value.
	 * @return string
	 */
	private function sanitize_value( string $type, $raw ): string {
		switch ( $type ) {
			case 'checkbox':
				return ( '1' === (string) $raw || 1 === $raw || true === $raw ) ? '1' : '';

			case 'int':
				$raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';
				return '' === $raw ? '' : (string) absint( $raw );

			case 'decimal':
			case 'price':
				return lrti_sanitize_decimal( $raw );

			case 'url':
				$raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';
				return '' === $raw ? '' : esc_url_raw( $raw );

			case 'vin':
				// Normalize to uppercase alphanumerics (strip spaces/dashes).
				$raw = is_scalar( $raw ) ? strtoupper( (string) $raw ) : '';
				$raw = preg_replace( '/[^A-Z0-9]/', '', $raw );
				return (string) $raw;

			case 'textarea':
				return sanitize_textarea_field( is_scalar( $raw ) ? (string) $raw : '' );

			case 'text':
			default:
				return sanitize_text_field( is_scalar( $raw ) ? (string) $raw : '' );
		}
	}

	/**
	 * Find another trailer already using a given stock number.
	 *
	 * @param string $stock_number The stock number.
	 * @param int    $exclude_id   The current post ID to exclude.
	 * @return int Duplicate post ID, or 0.
	 */
	private function find_duplicate_stock( string $stock_number, int $exclude_id ): int {
		$query = new \WP_Query(
			array(
				'post_type'      => PostTypes::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'post__not_in'   => array( $exclude_id ),
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_lrti_stock_number',
						'value' => $stock_number,
					),
				),
			)
		);

		return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
	}

	/* --------------------------------------------------------------------- *
	 * Assets & notices
	 * --------------------------------------------------------------------- */

	/**
	 * Load media library, our admin JS, and CSS on the trailer editor only.
	 *
	 * @param string $hook_suffix The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || PostTypes::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'lrti-admin', LRTI_PLUGIN_URL . 'admin/css/admin.css', array(), LRTI_VERSION );
		wp_enqueue_script( 'lrti-admin', LRTI_PLUGIN_URL . 'admin/js/admin.js', array( 'jquery' ), LRTI_VERSION, true );

		wp_localize_script(
			'lrti-admin',
			'lrtiAdmin',
			array(
				'currency'      => (string) lrti_get_setting( 'currency_symbol', '$' ),
				'chooseImages'  => __( 'Select Trailer Images', 'little-river-trailer-inventory' ),
				'useImages'     => __( 'Add to Gallery', 'little-river-trailer-inventory' ),
				'chooseMain'    => __( 'Select Main Image', 'little-river-trailer-inventory' ),
				'useMain'       => __( 'Use as Main Image', 'little-river-trailer-inventory' ),
				'chooseOg'      => __( 'Select Open Graph Image', 'little-river-trailer-inventory' ),
				'useOg'         => __( 'Use this Image', 'little-river-trailer-inventory' ),
				'stockRequired' => __( 'Stock Number is required. Please enter one on the General tab.', 'little-river-trailer-inventory' ),
				'saleTooHigh'   => __( 'Sale Price cannot be greater than Regular Price.', 'little-river-trailer-inventory' ),
				'msrpTooLow'    => __( 'MSRP cannot be less than the Sale Price.', 'little-river-trailer-inventory' ),
				'emptyOverGvwr' => __( 'Empty Weight is greater than GVWR — please double-check.', 'little-river-trailer-inventory' ),
				'vinHint'       => __( 'A VIN is usually 17 characters and does not use I, O, or Q.', 'little-river-trailer-inventory' ),
			)
		);
	}

	/**
	 * Display one-time notices stored during save.
	 *
	 * @return void
	 */
	public function show_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || PostTypes::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$key     = self::NOTICE_TRANSIENT . get_current_user_id();
		$notices = get_transient( $key );
		if ( empty( $notices ) || ! is_array( $notices ) ) {
			return;
		}
		delete_transient( $key );

		foreach ( $notices as $notice ) {
			$type = isset( $notice['type'] ) && in_array( $notice['type'], array( 'error', 'warning', 'success' ), true ) ? $notice['type'] : 'info';
			$text = isset( $notice['text'] ) ? (string) $notice['text'] : '';
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $text ) );
		}
	}
}
