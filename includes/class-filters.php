<?php
/**
 * Inventory filtering engine (Sprint 4.3).
 *
 * Single source of truth for parsing filter input, building the inventory query
 * (tax_query, meta_query, sorting, keyword search), and rendering the filter
 * sidebar, active-filter chips, results header, grid, and pagination. The
 * archive, AJAX handler, and shortcodes all use this class so business logic
 * and card markup are never duplicated (cards come from content-trailer.php).
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Filters
 */
final class Filters {

	/**
	 * Attach global query filters (gated by per-query flags so only our
	 * inventory queries are affected).
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'posts_clauses', array( $this, 'keyword_clauses' ), 10, 2 );
		add_filter( 'posts_clauses', array( $this, 'sort_clauses' ), 11, 2 );

		// Clear cached filter-option lists when inventory or terms change.
		add_action( 'save_post_' . PostTypes::POST_TYPE, array( $this, 'clear_option_cache' ) );
		add_action( 'deleted_post', array( $this, 'clear_option_cache' ) );
		foreach ( array( 'trailer_manufacturer', 'trailer_type', 'trailer_condition', 'trailer_availability' ) as $tax ) {
			add_action( "created_{$tax}", array( $this, 'clear_option_cache' ) );
			add_action( "edited_{$tax}", array( $this, 'clear_option_cache' ) );
			add_action( "delete_{$tax}", array( $this, 'clear_option_cache' ) );
		}
	}

	/* --------------------------------------------------------------------- *
	 * Parsing & validation
	 * --------------------------------------------------------------------- */

	/**
	 * Canonical filter field definitions. Filterable.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fields(): array {
		$fields = array(
			'keyword'      => array( 'type' => 'text' ),
			'manufacturer' => array( 'type' => 'slug', 'taxonomy' => 'trailer_manufacturer' ),
			'type'         => array( 'type' => 'slug', 'taxonomy' => 'trailer_type' ),
			'condition'    => array( 'type' => 'slug', 'taxonomy' => 'trailer_condition' ),
			'availability' => array( 'type' => 'slug', 'taxonomy' => 'trailer_availability' ),
			'min_price'    => array( 'type' => 'number' ),
			'max_price'    => array( 'type' => 'number' ),
			'min_year'     => array( 'type' => 'int' ),
			'max_year'     => array( 'type' => 'int' ),
			'min_gvwr'     => array( 'type' => 'number' ),
			'max_gvwr'     => array( 'type' => 'number' ),
			'axles'        => array( 'type' => 'int' ),
			'pull_type'    => array( 'type' => 'text' ),
			'featured'     => array( 'type' => 'bool' ),
			'sale'         => array( 'type' => 'bool' ),
		);

		// Register Specifications accordion fields (skip keys already defined).
		foreach ( self::spec_flat() as $key => $def ) {
			if ( 'range' === $def['kind'] ) {
				foreach ( array( 'min_' . $key, 'max_' . $key ) as $rk ) {
					if ( ! isset( $fields[ $rk ] ) ) {
						$fields[ $rk ] = array( 'type' => 'number' );
					}
				}
			} elseif ( ! isset( $fields[ $key ] ) ) {
				$fields[ $key ] = array( 'type' => 'text' );
			}
		}

		return (array) apply_filters( 'lrti_inventory_filter_fields', $fields );
	}

	/**
	 * Declarative Specifications accordion groups. Single source of truth for
	 * registration, query building, sidebar rendering, and active chips.
	 *
	 * Each field: key, meta, kind (range|select|yesno), label, unit (optional).
	 *
	 * @return array<string, array{label:string, fields:array<int, array<string, string>>}>
	 */
	public static function spec_groups(): array {
		$lbs = __( 'lbs', 'little-river-trailer-inventory' );
		$ft  = __( 'ft', 'little-river-trailer-inventory' );
		$in  = __( 'in', 'little-river-trailer-inventory' );

		$groups = array(
			'weight'     => array(
				'label'  => __( 'Weight & Capacity', 'little-river-trailer-inventory' ),
				'fields' => array(
					array( 'key' => 'gvwr', 'meta' => '_lrti_gvwr', 'kind' => 'range', 'label' => __( 'GVWR', 'little-river-trailer-inventory' ), 'unit' => $lbs ),
					array( 'key' => 'empty_weight', 'meta' => '_lrti_empty_weight', 'kind' => 'range', 'label' => __( 'Empty Weight', 'little-river-trailer-inventory' ), 'unit' => $lbs ),
					array( 'key' => 'payload_capacity', 'meta' => '_lrti_payload_capacity', 'kind' => 'range', 'label' => __( 'Payload Capacity', 'little-river-trailer-inventory' ), 'unit' => $lbs ),
				),
			),
			'dimensions' => array(
				'label'  => __( 'Dimensions', 'little-river-trailer-inventory' ),
				'fields' => array(
					array( 'key' => 'floor_length', 'meta' => '_lrti_floor_length', 'kind' => 'range', 'label' => __( 'Floor Length', 'little-river-trailer-inventory' ), 'unit' => $ft ),
					array( 'key' => 'overall_length', 'meta' => '_lrti_overall_length', 'kind' => 'range', 'label' => __( 'Overall Length', 'little-river-trailer-inventory' ), 'unit' => $ft ),
					array( 'key' => 'width', 'meta' => '_lrti_width', 'kind' => 'range', 'label' => __( 'Width', 'little-river-trailer-inventory' ), 'unit' => $in ),
					array( 'key' => 'height', 'meta' => '_lrti_height', 'kind' => 'range', 'label' => __( 'Height', 'little-river-trailer-inventory' ), 'unit' => $in ),
				),
			),
			'axles'      => array(
				'label'  => __( 'Axles & Suspension', 'little-river-trailer-inventory' ),
				'fields' => array(
					array( 'key' => 'axles', 'meta' => '_lrti_axle_count', 'kind' => 'select', 'label' => __( 'Axle Count', 'little-river-trailer-inventory' ) ),
					array( 'key' => 'axle_rating', 'meta' => '_lrti_axle_rating', 'kind' => 'range', 'label' => __( 'Axle Rating (each)', 'little-river-trailer-inventory' ), 'unit' => $lbs ),
					array( 'key' => 'suspension_type', 'meta' => '_lrti_suspension_type', 'kind' => 'select', 'label' => __( 'Suspension Type', 'little-river-trailer-inventory' ) ),
				),
			),
			'brakes'     => array(
				'label'  => __( 'Brakes & Running Gear', 'little-river-trailer-inventory' ),
				'fields' => array(
					array( 'key' => 'brake_type', 'meta' => '_lrti_brake_type', 'kind' => 'select', 'label' => __( 'Brake Type', 'little-river-trailer-inventory' ) ),
				),
			),
			'hitch'      => array(
				'label'  => __( 'Hitch & Hardware', 'little-river-trailer-inventory' ),
				'fields' => array(
					array( 'key' => 'pull_type', 'meta' => '_lrti_pull_type', 'kind' => 'select', 'label' => __( 'Pull Type', 'little-river-trailer-inventory' ) ),
					array( 'key' => 'coupler_type', 'meta' => '_lrti_coupler_type', 'kind' => 'select', 'label' => __( 'Coupler Type', 'little-river-trailer-inventory' ) ),
					array( 'key' => 'adjustable_coupler', 'meta' => '_lrti_adjustable_coupler', 'kind' => 'yesno', 'label' => __( 'Adjustable Coupler', 'little-river-trailer-inventory' ) ),
					array( 'key' => 'jack_type', 'meta' => '_lrti_jack_type', 'kind' => 'select', 'label' => __( 'Jack Type', 'little-river-trailer-inventory' ) ),
				),
			),
			'frame'      => array(
				'label'  => __( 'Frame', 'little-river-trailer-inventory' ),
				'fields' => array(
					array( 'key' => 'side_height', 'meta' => '_lrti_side_height', 'kind' => 'select', 'label' => __( 'Side Height', 'little-river-trailer-inventory' ) ),
				),
			),
			'wheels'     => array(
				'label'  => __( 'Wheels & Tires', 'little-river-trailer-inventory' ),
				'fields' => array(
					array( 'key' => 'tire_size', 'meta' => '_lrti_tire_size', 'kind' => 'select', 'label' => __( 'Tire Size', 'little-river-trailer-inventory' ) ),
					array( 'key' => 'tire_load_range', 'meta' => '_lrti_tire_load_range', 'kind' => 'select', 'label' => __( 'Tire Load Range', 'little-river-trailer-inventory' ) ),
					array( 'key' => 'wheel_material', 'meta' => '_lrti_wheel_material', 'kind' => 'select', 'label' => __( 'Wheel Material', 'little-river-trailer-inventory' ) ),
				),
			),
			'lighting'   => array(
				'label'  => __( 'Lighting & Electrical', 'little-river-trailer-inventory' ),
				'fields' => array(
					array( 'key' => 'led_lights', 'meta' => '_lrti_led_lights', 'kind' => 'yesno', 'label' => __( 'LED Lights', 'little-river-trailer-inventory' ) ),
					array( 'key' => 'electrical_connector', 'meta' => '_lrti_electrical_connector', 'kind' => 'select', 'label' => __( 'Electrical Connector', 'little-river-trailer-inventory' ) ),
				),
			),
			'toolbox'    => array(
				'label'  => __( 'Toolbox', 'little-river-trailer-inventory' ),
				'fields' => array(
					array( 'key' => 'toolbox_included', 'meta' => '_lrti_toolbox_included', 'kind' => 'yesno', 'label' => __( 'Toolbox Included', 'little-river-trailer-inventory' ) ),
					array( 'key' => 'toolbox_type', 'meta' => '_lrti_toolbox_type', 'kind' => 'select', 'label' => __( 'Toolbox Type', 'little-river-trailer-inventory' ) ),
				),
			),
			'tiedowns'   => array(
				'label'  => __( 'Stake Pockets & Tie-Downs', 'little-river-trailer-inventory' ),
				'fields' => array(
					array( 'key' => 'stake_pockets', 'meta' => '_lrti_stake_pockets', 'kind' => 'yesno', 'label' => __( 'Stake Pockets', 'little-river-trailer-inventory' ) ),
				),
			),
			'body'       => array(
				'label'  => __( 'Body & Flooring', 'little-river-trailer-inventory' ),
				'fields' => array(
					array( 'key' => 'flooring', 'meta' => '_lrti_flooring', 'kind' => 'select', 'label' => __( 'Flooring', 'little-river-trailer-inventory' ) ),
				),
			),
			'ramps'      => array(
				'label'  => __( 'Ramps', 'little-river-trailer-inventory' ),
				'fields' => array(
					array( 'key' => 'ramps_included', 'meta' => '_lrti_ramps_included', 'kind' => 'yesno', 'label' => __( 'Ramps Included', 'little-river-trailer-inventory' ) ),
					array( 'key' => 'ramp_type', 'meta' => '_lrti_ramp_type', 'kind' => 'select', 'label' => __( 'Ramp Type', 'little-river-trailer-inventory' ) ),
				),
			),
			'appearance' => array(
				'label'  => __( 'Appearance', 'little-river-trailer-inventory' ),
				'fields' => array(
					array( 'key' => 'exterior_color', 'meta' => '_lrti_exterior_color', 'kind' => 'select', 'label' => __( 'Exterior Color', 'little-river-trailer-inventory' ) ),
					array( 'key' => 'interior_color', 'meta' => '_lrti_interior_color', 'kind' => 'select', 'label' => __( 'Interior Color', 'little-river-trailer-inventory' ) ),
				),
			),
		);

		return (array) apply_filters( 'lrti_inventory_spec_groups', $groups );
	}

	/**
	 * Flatten spec groups into key => field definition.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function spec_flat(): array {
		$flat = array();
		foreach ( self::spec_groups() as $group ) {
			foreach ( $group['fields'] as $field ) {
				$flat[ $field['key'] ] = $field;
			}
		}
		return $flat;
	}

	/**
	 * All request parameter names used by spec filters (for the JS field list).
	 *
	 * @return string[]
	 */
	public static function spec_param_names(): array {
		$names = array();
		foreach ( self::spec_flat() as $key => $def ) {
			if ( 'range' === $def['kind'] ) {
				$names[] = 'min_' . $key;
				$names[] = 'max_' . $key;
			} else {
				$names[] = $key;
			}
		}
		return $names;
	}

	/**
	 * Distinct, non-empty meta values currently used by PUBLISHED trailers, for
	 * every spec meta key, gathered in ONE query and cached. Powers dynamic
	 * option lists and "only show filters that can return data".
	 *
	 * @return array<string, string[]> meta_key => sorted unique values.
	 */
	private function spec_distinct(): array {
		$cached = get_transient( 'lrti_spec_options' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$metas = array();
		foreach ( self::spec_flat() as $def ) {
			$metas[ $def['meta'] ] = true;
		}
		$keys = array_keys( $metas );
		if ( empty( $keys ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$sql          = $wpdb->prepare(
			"SELECT DISTINCT pm.meta_key, pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON ( p.ID = pm.post_id )
			 WHERE p.post_type = %s AND p.post_status = 'publish'
			 AND pm.meta_key IN ( {$placeholders} )
			 AND pm.meta_value <> ''",
			array_merge( array( PostTypes::POST_TYPE ), $keys )
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built safely above.

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row['meta_key'] ][] = (string) $row['meta_value'];
		}
		foreach ( $out as $mk => $vals ) {
			$vals = array_values( array_unique( $vals ) );
			natcasesort( $vals );
			$out[ $mk ] = array_values( $vals );
		}

		set_transient( 'lrti_spec_options', $out, HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * Render the Specifications accordion. Groups default closed unless they
	 * contain an active filter. Only controls with real data are shown.
	 *
	 * @param string               $instance Instance ID.
	 * @param array<string, mixed> $filters  Current filter values.
	 * @return void
	 */
	private function render_spec_accordion( string $instance, array $filters ): void {
		$distinct = $this->spec_distinct();

		$val = static function ( string $key ) use ( $filters ): string {
			return isset( $filters[ $key ] ) ? (string) $filters[ $key ] : '';
		};

		// Precompute which fields are renderable (have data) + active state.
		$groups_out = array();
		foreach ( self::spec_groups() as $gkey => $group ) {
			$rendered   = array();
			$active     = false;
			$active_cnt = 0;
			foreach ( $group['fields'] as $field ) {
				$key    = $field['key'];
				$kind   = $field['kind'];
				$values = $distinct[ $field['meta'] ] ?? array();

				if ( 'range' === $kind ) {
					if ( empty( $values ) ) {
						continue; // No data to filter on.
					}
					$is_active  = ( '' !== $val( 'min_' . $key ) || '' !== $val( 'max_' . $key ) );
					$rendered[] = array( 'field' => $field, 'active' => $is_active );
				} elseif ( 'yesno' === $kind ) {
					if ( ! in_array( 'Yes', $values, true ) ) {
						continue; // Nothing answers "Yes".
					}
					$is_active  = ( '' !== $val( $key ) );
					$rendered[] = array( 'field' => $field, 'active' => $is_active, 'options' => array( 'Yes', 'No' ) );
				} else { // select
					if ( empty( $values ) ) {
						continue;
					}
					$is_active  = ( '' !== $val( $key ) );
					$rendered[] = array( 'field' => $field, 'active' => $is_active, 'options' => $values );
				}
				if ( $is_active ) {
					$active = true;
					++$active_cnt;
				}
			}
			if ( ! empty( $rendered ) ) {
				$groups_out[ $gkey ] = array( 'label' => $group['label'], 'items' => $rendered, 'active' => $active, 'count' => $active_cnt );
			}
		}

		if ( empty( $groups_out ) ) {
			return;
		}
		?>
		<fieldset class="lrti-filter-section lrti-spec-accordion">
			<legend class="lrti-filter-legend"><?php esc_html_e( 'Specifications', 'little-river-trailer-inventory' ); ?></legend>
			<div class="lrti-spec-filter-groups">
			<?php foreach ( $groups_out as $gkey => $group ) : ?>
				<?php
				$panel_id = 'lrti-acc-' . $gkey . '-' . $instance;
				$open     = (bool) $group['active'];
				?>
				<div class="lrti-spec-filter-group<?php echo $open ? ' is-open' : ''; ?>">
					<h4 class="lrti-spec-filter-heading">
						<button type="button" class="lrti-spec-filter-toggle" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
							<span class="lrti-spec-filter-chevron" aria-hidden="true"></span>
							<span class="lrti-spec-filter-title"><?php echo esc_html( $group['label'] ); ?></span>
							<?php if ( $group['count'] > 0 ) : ?>
								<span class="lrti-spec-filter-count"><?php echo esc_html( number_format_i18n( (int) $group['count'] ) ); ?></span>
								<span class="screen-reader-text"><?php esc_html_e( 'active filters in this group', 'little-river-trailer-inventory' ); ?></span>
							<?php endif; ?>
						</button>
					</h4>
					<div class="lrti-spec-filter-panel" id="<?php echo esc_attr( $panel_id ); ?>" <?php echo $open ? '' : 'hidden'; ?>>
						<?php
						foreach ( $group['items'] as $item ) :
							$field = $item['field'];
							$key   = $field['key'];
							if ( 'range' === $field['kind'] ) :
								$unit    = isset( $field['unit'] ) ? ' (' . $field['unit'] . ')' : '';
								$min_id  = 'lrti-f-min_' . $key . '-' . $instance;
								$max_id  = 'lrti-f-max_' . $key . '-' . $instance;
								?>
								<div class="lrti-filter-group lrti-filter-range">
									<span class="lrti-filter-label"><?php echo esc_html( $field['label'] . $unit ); ?></span>
									<label class="screen-reader-text" for="<?php echo esc_attr( $min_id ); ?>"><?php echo esc_html( sprintf( /* translators: %s: field label */ __( 'Minimum %s', 'little-river-trailer-inventory' ), $field['label'] ) ); ?></label>
									<input type="number" step="any" class="lrti-input lrti-input--sm" id="<?php echo esc_attr( $min_id ); ?>" name="min_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $val( 'min_' . $key ) ); ?>" placeholder="<?php esc_attr_e( 'Min', 'little-river-trailer-inventory' ); ?>" />
									<span class="lrti-range-sep" aria-hidden="true">&ndash;</span>
									<label class="screen-reader-text" for="<?php echo esc_attr( $max_id ); ?>"><?php echo esc_html( sprintf( /* translators: %s: field label */ __( 'Maximum %s', 'little-river-trailer-inventory' ), $field['label'] ) ); ?></label>
									<input type="number" step="any" class="lrti-input lrti-input--sm" id="<?php echo esc_attr( $max_id ); ?>" name="max_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $val( 'max_' . $key ) ); ?>" placeholder="<?php esc_attr_e( 'Max', 'little-river-trailer-inventory' ); ?>" />
								</div>
								<?php
							else :
								$opts = array();
								foreach ( (array) ( $item['options'] ?? array() ) as $o ) {
									$opts[ (string) $o ] = (string) $o;
								}
								$this->render_select( $instance, $key, $field['label'], $opts, $val( $key ) );
							endif;
						endforeach;
						?>
					</div>
				</div>
			<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Parse and sanitize a request array into canonical filter values. Only
	 * non-empty values are returned.
	 *
	 * @param array<string, mixed> $src Raw request ($_GET or $_POST).
	 * @return array<string, mixed>
	 */
	public function parse_request( array $src ): array {
		$out = array();

		foreach ( $this->fields() as $key => $def ) {
			if ( ! isset( $src[ $key ] ) ) {
				continue;
			}
			$raw  = wp_unslash( $src[ $key ] );
			$type = (string) $def['type'];

			switch ( $type ) {
				case 'slug':
					$val = sanitize_key( is_scalar( $raw ) ? (string) $raw : '' );
					if ( '' !== $val ) {
						$out[ $key ] = $val;
					}
					break;

				case 'number':
					$val = lrti_sanitize_decimal( $raw );
					if ( '' !== $val ) {
						$out[ $key ] = $val;
					}
					break;

				case 'int':
					$val = is_scalar( $raw ) ? (string) absint( $raw ) : '';
					if ( '' !== $val && '0' !== $val ) {
						$out[ $key ] = $val;
					}
					break;

				case 'bool':
					$val = is_scalar( $raw ) ? strtolower( (string) $raw ) : '';
					if ( in_array( $val, array( '1', 'true', 'on', 'yes' ), true ) ) {
						$out[ $key ] = '1';
					}
					break;

				case 'text':
				default:
					$val = sanitize_text_field( is_scalar( $raw ) ? (string) $raw : '' );
					if ( '' !== $val ) {
						$out[ $key ] = $val;
					}
					break;
			}
		}

		return $out;
	}

	/**
	 * Allowed sort keys => labels (shared with the archive dropdown).
	 *
	 * @return array<string, string>
	 */
	public function allowed_sorts(): array {
		return (array) apply_filters( 'lrti_inventory_allowed_sort_options', lrti_get_sort_options() );
	}

	/**
	 * Validate a sort key against the allowed list.
	 *
	 * @param string $sort Candidate sort key.
	 * @return string
	 */
	public function validate_sort( string $sort ): string {
		$sort = sanitize_key( $sort );
		return array_key_exists( $sort, $this->allowed_sorts() ) ? $sort : 'newest';
	}

	/**
	 * Results per page. Filterable.
	 *
	 * @param int $default_value Fallback value.
	 * @return int
	 */
	public function per_page( int $default_value = 0 ): int {
		$value = $default_value > 0 ? $default_value : lrti_results_per_page();
		return max( 1, (int) apply_filters( 'lrti_inventory_results_per_page', $value ) );
	}

	/* --------------------------------------------------------------------- *
	 * Query building
	 * --------------------------------------------------------------------- */

	/**
	 * Build WP_Query args from filters + base constraints.
	 *
	 * Base constraints (e.g. a shortcode's locked manufacturer) override the
	 * corresponding user filter. Sorting and keyword are passed as query vars so
	 * a single pair of posts_clauses filters can handle any inventory query.
	 *
	 * @param array<string, mixed> $filters Parsed filter values.
	 * @param array<string, mixed> $base    Base constraints/overrides.
	 * @return array<string, mixed>
	 */
	public function build_query_args( array $filters, array $base = array() ): array {
		// Base overrides win.
		foreach ( array( 'manufacturer', 'type', 'condition', 'availability', 'pull_type', 'axles' ) as $k ) {
			if ( ! empty( $base[ $k ] ) ) {
				$filters[ $k ] = ( 'axles' === $k ) ? (string) absint( $base[ $k ] ) : sanitize_text_field( (string) $base[ $k ] );
			}
		}
		if ( ! empty( $base['featured'] ) ) {
			$filters['featured'] = '1';
		}
		if ( ! empty( $base['sale'] ) ) {
			$filters['sale'] = '1';
		}

		$per_page = isset( $base['per_page'] ) ? max( 1, (int) $base['per_page'] ) : $this->per_page();
		$paged    = isset( $base['paged'] ) ? max( 1, (int) $base['paged'] ) : 1;
		$sort     = $this->validate_sort( isset( $base['sort'] ) ? (string) $base['sort'] : ( isset( $filters['sort'] ) ? (string) $filters['sort'] : 'newest' ) );

		$args = array(
			'post_type'           => PostTypes::POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => $paged,
			'ignore_sticky_posts' => true,
			'lrti_sort'           => $sort,
		);

		// Taxonomy filters.
		$tax_map = array(
			'manufacturer' => 'trailer_manufacturer',
			'type'         => 'trailer_type',
			'condition'    => 'trailer_condition',
			'availability' => 'trailer_availability',
		);
		$tax_query = array();
		foreach ( $tax_map as $fkey => $taxonomy ) {
			if ( ! empty( $filters[ $fkey ] ) ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => array( sanitize_key( $filters[ $fkey ] ) ),
				);
			}
		}
		// Optionally exclude sold trailers (used by featured/card shortcodes).
		if ( ! empty( $base['exclude_sold'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'trailer_availability',
				'field'    => 'slug',
				'terms'    => array( 'sold' ),
				'operator' => 'NOT IN',
			);
		}
		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		// Meta filters.
		$meta_query = array();
		$this->range_clause( $meta_query, '_lrti_regular_price', $filters['min_price'] ?? '', $filters['max_price'] ?? '' );
		$this->range_clause( $meta_query, '_lrti_year', $filters['min_year'] ?? '', $filters['max_year'] ?? '' );
		$this->range_clause( $meta_query, '_lrti_gvwr', $filters['min_gvwr'] ?? '', $filters['max_gvwr'] ?? '' );

		if ( ! empty( $filters['axles'] ) ) {
			$meta_query[] = array(
				'key'     => '_lrti_axle_count',
				'value'   => (string) absint( $filters['axles'] ),
				'compare' => '=',
			);
		}
		if ( ! empty( $filters['pull_type'] ) ) {
			$meta_query[] = array(
				'key'     => '_lrti_pull_type',
				'value'   => sanitize_text_field( $filters['pull_type'] ),
				'compare' => '=',
			);
		}

		// Generic Specifications accordion filters (skip the three handled above).
		foreach ( self::spec_flat() as $key => $def ) {
			if ( in_array( $key, array( 'gvwr', 'axles', 'pull_type' ), true ) ) {
				continue;
			}
			if ( 'range' === $def['kind'] ) {
				$this->range_clause( $meta_query, $def['meta'], $filters[ 'min_' . $key ] ?? '', $filters[ 'max_' . $key ] ?? '' );
			} else {
				$v = isset( $filters[ $key ] ) ? (string) $filters[ $key ] : '';
				if ( '' !== $v ) {
					$meta_query[] = array(
						'key'     => $def['meta'],
						'value'   => sanitize_text_field( $v ),
						'compare' => '=',
					);
				}
			}
		}
		if ( ! empty( $filters['featured'] ) ) {
			$meta_query[] = array(
				'key'     => '_lrti_featured',
				'value'   => '1',
				'compare' => '=',
			);
		}
		if ( ! empty( $filters['sale'] ) ) {
			$meta_query[] = array(
				'key'     => '_lrti_sale_badge',
				'value'   => '1',
				'compare' => '=',
			);
		}
		if ( ! empty( $meta_query ) ) {
			if ( count( $meta_query ) > 1 ) {
				$meta_query['relation'] = 'AND';
			}
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		// Keyword: passed as a query var; applied via keyword_clauses().
		if ( ! empty( $filters['keyword'] ) ) {
			$args['lrti_keyword'] = sanitize_text_field( $filters['keyword'] );
		}

		return (array) apply_filters( 'lrti_inventory_query_args', $args, $filters, $base );
	}

	/**
	 * Add a numeric BETWEEN/>=/<= meta clause to a meta_query.
	 *
	 * @param array<int|string, mixed> $meta_query The meta query (by reference).
	 * @param string                   $key        The meta key.
	 * @param string                   $min        Minimum (or '').
	 * @param string                   $max        Maximum (or '').
	 * @return void
	 */
	private function range_clause( array &$meta_query, string $key, string $min, string $max ): void {
		$min = ( '' !== $min ) ? (float) $min : null;
		$max = ( '' !== $max ) ? (float) $max : null;

		if ( null !== $min && null !== $max ) {
			if ( $min > $max ) {
				$tmp = $min;
				$min = $max;
				$max = $tmp;
			}
			$meta_query[] = array(
				'key'     => $key,
				'value'   => array( $min, $max ),
				'type'    => 'NUMERIC',
				'compare' => 'BETWEEN',
			);
		} elseif ( null !== $min ) {
			$meta_query[] = array(
				'key'     => $key,
				'value'   => $min,
				'type'    => 'NUMERIC',
				'compare' => '>=',
			);
		} elseif ( null !== $max ) {
			$meta_query[] = array(
				'key'     => $key,
				'value'   => $max,
				'type'    => 'NUMERIC',
				'compare' => '<=',
			);
		}
	}

	/**
	 * Run an inventory query for the given filters/base.
	 *
	 * @param array<string, mixed> $filters Parsed filters.
	 * @param array<string, mixed> $base    Base constraints.
	 * @return \WP_Query
	 */
	public function run_query( array $filters, array $base = array() ): \WP_Query {
		return new \WP_Query( $this->build_query_args( $filters, $base ) );
	}

	/* --------------------------------------------------------------------- *
	 * posts_clauses: keyword + sorting (gated by query vars)
	 * --------------------------------------------------------------------- */

	/**
	 * Does this query target our public inventory (carrying our flags)?
	 *
	 * @param \WP_Query $query The query.
	 * @return bool
	 */
	private function is_ours( \WP_Query $query ): bool {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}
		$pt = $query->get( 'post_type' );
		return ( PostTypes::POST_TYPE === $pt || ( is_array( $pt ) && in_array( PostTypes::POST_TYPE, $pt, true ) ) );
	}

	/**
	 * Keyword search across title, model, stock number, manufacturer, and type.
	 * VIN and internal notes are intentionally NOT searched.
	 *
	 * @param array<string, string> $clauses The query clauses.
	 * @param \WP_Query              $query   The query.
	 * @return array<string, string>
	 */
	public function keyword_clauses( array $clauses, \WP_Query $query ): array {
		$keyword = (string) $query->get( 'lrti_keyword' );
		if ( '' === $keyword || ! $this->is_ours( $query ) ) {
			return $clauses;
		}

		global $wpdb;
		$like = '%' . $wpdb->esc_like( $keyword ) . '%';

		$clauses['where'] .= $wpdb->prepare(
			" AND (
				{$wpdb->posts}.post_title LIKE %s
				OR {$wpdb->posts}.post_content LIKE %s
				OR {$wpdb->posts}.post_excerpt LIKE %s
				OR EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} lrti_kwm
					WHERE lrti_kwm.post_id = {$wpdb->posts}.ID
					AND lrti_kwm.meta_key IN (
						'_lrti_model','_lrti_stock_number','_lrti_additional_features',
						'_lrti_flooring','_lrti_exterior_color','_lrti_ramp_type',
						'_lrti_pull_type','_lrti_suspension_type','_lrti_brake_type',
						'_lrti_tire_size','_lrti_gvwr','_lrti_floor_length',
						'_lrti_overall_length','_lrti_width'
					)
					AND lrti_kwm.meta_value LIKE %s
				)
				OR EXISTS (
					SELECT 1 FROM {$wpdb->term_relationships} lrti_kwtr
					INNER JOIN {$wpdb->term_taxonomy} lrti_kwtt ON lrti_kwtr.term_taxonomy_id = lrti_kwtt.term_taxonomy_id
					INNER JOIN {$wpdb->terms} lrti_kwt ON lrti_kwtt.term_id = lrti_kwt.term_id
					WHERE lrti_kwtr.object_id = {$wpdb->posts}.ID
					AND lrti_kwtt.taxonomy IN ('trailer_manufacturer','trailer_type','trailer_feature')
					AND lrti_kwt.name LIKE %s
				)
			)",
			$like,
			$like,
			$like,
			$like,
			$like
		);

		return $clauses;
	}

	/**
	 * Apply sorting via joins/orderby so it never conflicts with filter
	 * meta_query and never excludes trailers missing the sort value.
	 *
	 * @param array<string, string> $clauses The query clauses.
	 * @param \WP_Query              $query   The query.
	 * @return array<string, string>
	 */
	public function sort_clauses( array $clauses, \WP_Query $query ): array {
		$sort = (string) $query->get( 'lrti_sort' );
		if ( '' === $sort || ! $this->is_ours( $query ) ) {
			return $clauses;
		}

		global $wpdb;
		$p = $wpdb->posts;

		switch ( $sort ) {
			case 'oldest':
				$clauses['orderby'] = "{$p}.post_date ASC";
				break;

			case 'price_low':
				$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} lrti_sp ON ({$p}.ID = lrti_sp.post_id AND lrti_sp.meta_key = '_lrti_regular_price')";
				$clauses['orderby'] = "(lrti_sp.meta_value IS NULL) ASC, CAST(lrti_sp.meta_value AS DECIMAL(12,2)) ASC, {$p}.post_date DESC";
				break;

			case 'price_high':
				$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} lrti_sp ON ({$p}.ID = lrti_sp.post_id AND lrti_sp.meta_key = '_lrti_regular_price')";
				$clauses['orderby'] = "(lrti_sp.meta_value IS NULL) ASC, CAST(lrti_sp.meta_value AS DECIMAL(12,2)) DESC, {$p}.post_date DESC";
				break;

			case 'year_new':
				$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} lrti_sy ON ({$p}.ID = lrti_sy.post_id AND lrti_sy.meta_key = '_lrti_year')";
				$clauses['orderby'] = "(lrti_sy.meta_value IS NULL) ASC, CAST(lrti_sy.meta_value AS UNSIGNED) DESC, {$p}.post_date DESC";
				break;

			case 'year_old':
				$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} lrti_sy ON ({$p}.ID = lrti_sy.post_id AND lrti_sy.meta_key = '_lrti_year')";
				$clauses['orderby'] = "(lrti_sy.meta_value IS NULL) ASC, CAST(lrti_sy.meta_value AS UNSIGNED) ASC, {$p}.post_date DESC";
				break;

			case 'featured_first':
				$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} lrti_sf ON ({$p}.ID = lrti_sf.post_id AND lrti_sf.meta_key = '_lrti_featured')";
				$clauses['orderby'] = "(lrti_sf.meta_value = '1') DESC, {$p}.post_date DESC";
				break;

			case 'length':
				$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} lrti_sl ON ({$p}.ID = lrti_sl.post_id AND lrti_sl.meta_key = '_lrti_floor_length')";
				$clauses['orderby'] = "(lrti_sl.meta_value IS NULL OR lrti_sl.meta_value = '') ASC, CAST(lrti_sl.meta_value AS DECIMAL(12,2)) ASC, {$p}.post_date DESC";
				break;

			case 'gvwr':
				$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} lrti_sg ON ({$p}.ID = lrti_sg.post_id AND lrti_sg.meta_key = '_lrti_gvwr')";
				$clauses['orderby'] = "(lrti_sg.meta_value IS NULL OR lrti_sg.meta_value = '') ASC, CAST(lrti_sg.meta_value AS DECIMAL(12,2)) DESC, {$p}.post_date DESC";
				break;

			case 'in_stock_first':
				// Trailers NOT flagged sold appear first, newest within each group.
				$clauses['orderby'] = "(EXISTS ("
					. "SELECT 1 FROM {$wpdb->term_relationships} lrti_istr"
					. " INNER JOIN {$wpdb->term_taxonomy} lrti_istt ON lrti_istr.term_taxonomy_id = lrti_istt.term_taxonomy_id"
					. " INNER JOIN {$wpdb->terms} lrti_ist ON lrti_istt.term_id = lrti_ist.term_id"
					. " WHERE lrti_istr.object_id = {$p}.ID AND lrti_istt.taxonomy = 'trailer_availability' AND lrti_ist.slug = 'sold'"
					. ")) ASC, {$p}.post_date DESC";
				break;

			case 'manufacturer_az':
			case 'manufacturer_za':
				$dir                = ( 'manufacturer_za' === $sort ) ? 'DESC' : 'ASC';
				$clauses['join']   .= " LEFT JOIN {$wpdb->term_relationships} lrti_smtr ON ({$p}.ID = lrti_smtr.object_id)"
					. " LEFT JOIN {$wpdb->term_taxonomy} lrti_smtt ON (lrti_smtr.term_taxonomy_id = lrti_smtt.term_taxonomy_id AND lrti_smtt.taxonomy = 'trailer_manufacturer')"
					. " LEFT JOIN {$wpdb->terms} lrti_smt ON (lrti_smtt.term_id = lrti_smt.term_id)";
				$clauses['orderby'] = "lrti_smt.name {$dir}, {$p}.post_date DESC";
				$clauses['groupby'] = "{$p}.ID";
				break;

			case 'newest':
			default:
				$clauses['orderby'] = "{$p}.post_date DESC";
				break;
		}

		return $clauses;
	}

	/* --------------------------------------------------------------------- *
	 * Filter option lists (cached)
	 * --------------------------------------------------------------------- */

	/**
	 * Cached lists used to populate the dropdowns. Only non-empty taxonomy terms
	 * and actually-saved meta values are returned.
	 *
	 * @return array<string, array<int|string, string>>
	 */
	public function get_filter_options(): array {
		$cached = get_transient( 'lrti_filter_options' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$terms_for = static function ( string $taxonomy ): array {
			$out   = array();
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
				)
			);
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$out[ $term->slug ] = $term->name;
				}
			}
			return $out;
		};

		$options = array(
			'manufacturer' => $terms_for( 'trailer_manufacturer' ),
			'type'         => $terms_for( 'trailer_type' ),
			'condition'    => $terms_for( 'trailer_condition' ),
			'availability' => $terms_for( 'trailer_availability' ),
			'axles'        => $this->distinct_meta( '_lrti_axle_count', true ),
			'pull_type'    => $this->distinct_meta( '_lrti_pull_type', false ),
		);

		set_transient( 'lrti_filter_options', $options, HOUR_IN_SECONDS );

		return $options;
	}

	/**
	 * Distinct saved meta values for a key (used for Axle Count / Pull Type).
	 *
	 * @param string $key     The meta key.
	 * @param bool   $numeric Whether to sort numerically.
	 * @return array<int|string, string>
	 */
	private function distinct_meta( string $key, bool $numeric ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$values = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s
				 AND pm.meta_value <> ''
				 AND p.post_type = %s
				 AND p.post_status = 'publish'",
				$key,
				PostTypes::POST_TYPE
			)
		);

		$values = array_filter( array_map( 'trim', (array) $values ) );

		if ( $numeric ) {
			$values = array_map( 'intval', $values );
			sort( $values, SORT_NUMERIC );
		} else {
			natcasesort( $values );
		}

		$out = array();
		foreach ( $values as $v ) {
			$out[ (string) $v ] = (string) $v;
		}

		return $out;
	}

	/**
	 * Clear cached filter option lists.
	 *
	 * @return void
	 */
	public function clear_option_cache(): void {
		delete_transient( 'lrti_filter_options' );
		delete_transient( 'lrti_spec_options' );
		if ( function_exists( 'lrti_clear_related_cache' ) ) {
			lrti_clear_related_cache();
		}
	}

	/* --------------------------------------------------------------------- *
	 * Rendering
	 * --------------------------------------------------------------------- */

	/**
	 * Render the whole inventory application (sidebar + results region) for an
	 * instance. Used by the archive template and the [trailer_inventory]
	 * shortcode so markup lives in one place.
	 *
	 * @param string               $instance      Unique instance ID.
	 * @param array<string, mixed> $base          Base constraints (locks).
	 * @param array<string, mixed> $filters       Parsed current filters.
	 * @param bool                 $show_filters  Whether to render the sidebar.
	 * @param int                  $columns       Grid columns (1-4).
	 * @param bool                 $show_pagination Whether to show pagination.
	 * @return void
	 */
	public function render_app( string $instance, array $base, array $filters, bool $show_filters, int $columns, bool $show_pagination ): void {
		$query = $this->run_query( $filters, $base );

		$data_atts = array(
			'instance'   => $instance,
			'base'       => $base,
			'columns'    => $columns,
			'filters'    => $show_filters,
			'pagination' => $show_pagination,
		);
		?>
		<div class="lrti-inventory lrti-archive" id="lrti-inv-<?php echo esc_attr( $instance ); ?>" data-instance="<?php echo esc_attr( $instance ); ?>" data-atts="<?php echo esc_attr( wp_json_encode( $data_atts ) ); ?>">
			<div class="lrti-container lrti-inventory-layout<?php echo $show_filters ? '' : ' lrti-inventory-layout--nofilters'; ?>">
				<?php if ( $show_filters ) : ?>
					<?php $this->render_sidebar( $instance, $filters ); ?>
				<?php endif; ?>
				<div class="lrti-inventory-main">
					<?php $this->render_results_region( $query, $filters, $instance, $columns, $show_pagination ); ?>
				</div>
			</div>
		</div>
		<?php
		wp_reset_postdata();
	}

	/**
	 * Render the filter sidebar form.
	 *
	 * @param string               $instance The instance ID.
	 * @param array<string, mixed> $filters  Current filter values.
	 * @return void
	 */
	public function render_sidebar( string $instance, array $filters ): void {
		$options = $this->get_filter_options();
		$action  = (string) get_post_type_archive_link( PostTypes::POST_TYPE );

		$val = static function ( string $key ) use ( $filters ): string {
			return isset( $filters[ $key ] ) ? (string) $filters[ $key ] : '';
		};

		do_action( 'lrti_before_inventory_filters', $instance );
		?>
		<aside class="lrti-sidebar" aria-label="<?php esc_attr_e( 'Inventory filters', 'little-river-trailer-inventory' ); ?>">
			<button type="button" class="lrti-btn lrti-btn--small lrti-filter-toggle" aria-expanded="false" aria-controls="lrti-filter-form-<?php echo esc_attr( $instance ); ?>">
				<?php esc_html_e( 'Show Filters', 'little-river-trailer-inventory' ); ?>
			</button>

			<form class="lrti-filter-form" id="lrti-filter-form-<?php echo esc_attr( $instance ); ?>" method="get" action="<?php echo esc_url( $action ); ?>">

				<fieldset class="lrti-filter-section">
					<legend class="lrti-filter-legend"><?php esc_html_e( 'Search', 'little-river-trailer-inventory' ); ?></legend>
					<div class="lrti-filter-group">
						<label class="lrti-filter-label" for="lrti-f-keyword-<?php echo esc_attr( $instance ); ?>"><?php esc_html_e( 'Keyword', 'little-river-trailer-inventory' ); ?></label>
						<input type="search" class="lrti-input" id="lrti-f-keyword-<?php echo esc_attr( $instance ); ?>" name="keyword" value="<?php echo esc_attr( $val( 'keyword' ) ); ?>" placeholder="<?php esc_attr_e( 'Search inventory…', 'little-river-trailer-inventory' ); ?>" />
					</div>
				</fieldset>

				<fieldset class="lrti-filter-section">
					<legend class="lrti-filter-legend"><?php esc_html_e( 'Trailer', 'little-river-trailer-inventory' ); ?></legend>
					<?php
					$this->render_select( $instance, 'type', __( 'Trailer Type', 'little-river-trailer-inventory' ), $options['type'], $val( 'type' ) );
					$this->render_select( $instance, 'manufacturer', __( 'Manufacturer', 'little-river-trailer-inventory' ), $options['manufacturer'], $val( 'manufacturer' ) );
					?>
				</fieldset>

				<fieldset class="lrti-filter-section">
					<legend class="lrti-filter-legend"><?php esc_html_e( 'Status', 'little-river-trailer-inventory' ); ?></legend>
					<?php
					$this->render_select( $instance, 'condition', __( 'Condition', 'little-river-trailer-inventory' ), $options['condition'], $val( 'condition' ) );
					$this->render_select( $instance, 'availability', __( 'Availability', 'little-river-trailer-inventory' ), $options['availability'], $val( 'availability' ) );
					?>
					<div class="lrti-filter-group lrti-filter-checks">
						<label class="lrti-check"><input type="checkbox" name="featured" value="1" <?php checked( '1', $val( 'featured' ) ); ?> /> <?php esc_html_e( 'Featured only', 'little-river-trailer-inventory' ); ?></label>
						<label class="lrti-check"><input type="checkbox" name="sale" value="1" <?php checked( '1', $val( 'sale' ) ); ?> /> <?php esc_html_e( 'Sale trailers only', 'little-river-trailer-inventory' ); ?></label>
					</div>
				</fieldset>

				<fieldset class="lrti-filter-section lrti-filter-range">
					<legend class="lrti-filter-legend"><?php esc_html_e( 'Price', 'little-river-trailer-inventory' ); ?></legend>
					<label class="screen-reader-text" for="lrti-f-min_price-<?php echo esc_attr( $instance ); ?>"><?php esc_html_e( 'Minimum price', 'little-river-trailer-inventory' ); ?></label>
					<input type="number" min="0" step="1" class="lrti-input lrti-input--sm" id="lrti-f-min_price-<?php echo esc_attr( $instance ); ?>" name="min_price" value="<?php echo esc_attr( $val( 'min_price' ) ); ?>" placeholder="<?php esc_attr_e( 'Min', 'little-river-trailer-inventory' ); ?>" />
					<span class="lrti-range-sep" aria-hidden="true">&ndash;</span>
					<label class="screen-reader-text" for="lrti-f-max_price-<?php echo esc_attr( $instance ); ?>"><?php esc_html_e( 'Maximum price', 'little-river-trailer-inventory' ); ?></label>
					<input type="number" min="0" step="1" class="lrti-input lrti-input--sm" id="lrti-f-max_price-<?php echo esc_attr( $instance ); ?>" name="max_price" value="<?php echo esc_attr( $val( 'max_price' ) ); ?>" placeholder="<?php esc_attr_e( 'Max', 'little-river-trailer-inventory' ); ?>" />
				</fieldset>

				<fieldset class="lrti-filter-section lrti-filter-range">
					<legend class="lrti-filter-legend"><?php esc_html_e( 'Year', 'little-river-trailer-inventory' ); ?></legend>
					<label class="screen-reader-text" for="lrti-f-min_year-<?php echo esc_attr( $instance ); ?>"><?php esc_html_e( 'Minimum year', 'little-river-trailer-inventory' ); ?></label>
					<input type="number" min="1900" step="1" class="lrti-input lrti-input--sm" id="lrti-f-min_year-<?php echo esc_attr( $instance ); ?>" name="min_year" value="<?php echo esc_attr( $val( 'min_year' ) ); ?>" placeholder="<?php esc_attr_e( 'Min', 'little-river-trailer-inventory' ); ?>" />
					<span class="lrti-range-sep" aria-hidden="true">&ndash;</span>
					<label class="screen-reader-text" for="lrti-f-max_year-<?php echo esc_attr( $instance ); ?>"><?php esc_html_e( 'Maximum year', 'little-river-trailer-inventory' ); ?></label>
					<input type="number" min="1900" step="1" class="lrti-input lrti-input--sm" id="lrti-f-max_year-<?php echo esc_attr( $instance ); ?>" name="max_year" value="<?php echo esc_attr( $val( 'max_year' ) ); ?>" placeholder="<?php esc_attr_e( 'Max', 'little-river-trailer-inventory' ); ?>" />
				</fieldset>

				<?php $this->render_spec_accordion( $instance, $filters ); ?>

				<?php
				$current_sort = $this->validate_sort( isset( $filters['sort'] ) ? (string) $filters['sort'] : lrti_current_sort() );
				?>
				<input type="hidden" name="sort" value="<?php echo esc_attr( $current_sort ); ?>" class="lrti-sort-mirror" />

				<div class="lrti-filter-actions">
					<button type="submit" class="lrti-btn lrti-btn--primary lrti-apply"><?php esc_html_e( 'Apply Filters', 'little-river-trailer-inventory' ); ?></button>
					<button type="button" class="lrti-btn lrti-btn--outline lrti-reset"><?php esc_html_e( 'Reset Filters', 'little-river-trailer-inventory' ); ?></button>
				</div>
			</form>
		</aside>
		<?php
		do_action( 'lrti_after_inventory_filters', $instance );
	}

	/**
	 * Render one labeled select control.
	 *
	 * @param string                    $instance The instance ID.
	 * @param string                    $name     The field name.
	 * @param string                    $label    The visible label.
	 * @param array<int|string, string> $options  value => label.
	 * @param string                    $selected Current value.
	 * @return void
	 */
	private function render_select( string $instance, string $name, string $label, array $options, string $selected ): void {
		if ( empty( $options ) ) {
			return; // Do not show empty filter options.
		}
		$id = 'lrti-f-' . $name . '-' . $instance;
		?>
		<div class="lrti-filter-group">
			<label class="lrti-filter-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<select class="lrti-select" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
				<option value=""><?php esc_html_e( 'All', 'little-river-trailer-inventory' ); ?></option>
				<?php foreach ( $options as $value => $opt_label ) : ?>
					<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $selected, (string) $value ); ?>><?php echo esc_html( (string) $opt_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	/**
	 * Render the results region (header, chips, grid, pagination, empty state).
	 * This is the exact fragment the AJAX handler returns.
	 *
	 * @param \WP_Query            $query           The results query.
	 * @param array<string, mixed> $filters         Current filters.
	 * @param string               $instance        The instance ID.
	 * @param int                  $columns         Grid columns.
	 * @param bool                 $show_pagination Whether to render pagination.
	 * @return void
	 */
	public function render_results_region( \WP_Query $query, array $filters, string $instance, int $columns, bool $show_pagination ): void {
		$found   = (int) $query->found_posts;
		$chips   = $this->active_filter_labels( $filters );
		$columns = max( 1, min( 4, $columns ) );

		do_action( 'lrti_before_inventory_results', $instance, $query );
		?>
		<div class="lrti-results" data-instance="<?php echo esc_attr( $instance ); ?>">

			<div class="lrti-results-header">
				<p class="lrti-results-count" role="status" aria-live="polite">
					<?php
					if ( 0 === $found ) {
						esc_html_e( 'No trailers currently available', 'little-river-trailer-inventory' );
					} else {
						printf(
							/* translators: %s: number of trailers */
							esc_html( _n( 'Showing %s trailer currently available', 'Showing %s trailers currently available', $found, 'little-river-trailer-inventory' ) ),
							esc_html( number_format_i18n( $found ) )
						);
					}
					?>
				</p>

				<form class="lrti-sort lrti-sort-controls" method="get" role="search" aria-label="<?php esc_attr_e( 'Sort inventory', 'little-river-trailer-inventory' ); ?>">
					<label class="lrti-sort-label" for="lrti-sort-<?php echo esc_attr( $instance ); ?>"><?php esc_html_e( 'Sort by', 'little-river-trailer-inventory' ); ?></label>
					<select class="lrti-sort-select" id="lrti-sort-<?php echo esc_attr( $instance ); ?>" name="sort">
						<?php
						$current_sort = $this->validate_sort( isset( $filters['sort'] ) ? (string) $filters['sort'] : lrti_current_sort() );
						foreach ( $this->allowed_sorts() as $skey => $slabel ) :
							?>
							<option value="<?php echo esc_attr( $skey ); ?>" <?php selected( $current_sort, $skey ); ?>><?php echo esc_html( $slabel ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="lrti-btn lrti-btn--small"><?php esc_html_e( 'Sort', 'little-river-trailer-inventory' ); ?></button>
				</form>
			</div>

			<?php if ( ! empty( $chips ) ) : ?>
				<?php do_action( 'lrti_before_active_filter_chips', $instance ); ?>
				<div class="lrti-chips" aria-label="<?php esc_attr_e( 'Active filters', 'little-river-trailer-inventory' ); ?>">
					<?php foreach ( $chips as $chip_key => $chip_label ) : ?>
						<span class="lrti-chip">
							<span class="lrti-chip-text"><?php echo esc_html( $chip_label ); ?></span>
							<button type="button" class="lrti-chip-remove" data-filter="<?php echo esc_attr( $chip_key ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: filter label */ __( 'Remove filter: %s', 'little-river-trailer-inventory' ), $chip_label ) ); ?>">&times;</button>
						</span>
					<?php endforeach; ?>
					<button type="button" class="lrti-chip-clear lrti-reset"><?php esc_html_e( 'Clear All', 'little-river-trailer-inventory' ); ?></button>
				</div>
				<?php do_action( 'lrti_after_active_filter_chips', $instance ); ?>
			<?php endif; ?>

			<?php if ( $query->have_posts() ) : ?>
				<div class="lrti-grid lrti-grid--cols-<?php echo esc_attr( (string) $columns ); ?>">
					<?php
					while ( $query->have_posts() ) :
						$query->the_post();
						lrti_get_template_part( 'content-trailer' );
					endwhile;
					wp_reset_postdata();
					?>
				</div>

				<?php if ( $show_pagination ) : ?>
					<?php echo $this->pagination_html( $query, $filters ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped. ?>
				<?php endif; ?>

			<?php else : ?>
				<?php $this->render_empty( $filters ); ?>
			<?php endif; ?>

		</div>
		<?php
		do_action( 'lrti_after_inventory_results', $instance, $query );
	}

	/**
	 * Build active-filter chip labels. Filterable.
	 *
	 * @param array<string, mixed> $filters Current filters.
	 * @return array<string, string>
	 */
	public function active_filter_labels( array $filters ): array {
		$options = $this->get_filter_options();
		$labels  = array();
		$sym     = (string) lrti_get_setting( 'currency_symbol', '$' );

		$term_label = static function ( array $map, string $slug ): string {
			return isset( $map[ $slug ] ) ? (string) $map[ $slug ] : $slug;
		};

		if ( ! empty( $filters['keyword'] ) ) {
			/* translators: %s: keyword */
			$labels['keyword'] = sprintf( __( 'Keyword: %s', 'little-river-trailer-inventory' ), $filters['keyword'] );
		}
		if ( ! empty( $filters['manufacturer'] ) ) {
			$labels['manufacturer'] = __( 'Manufacturer', 'little-river-trailer-inventory' ) . ': ' . $term_label( $options['manufacturer'], $filters['manufacturer'] );
		}
		if ( ! empty( $filters['type'] ) ) {
			$labels['type'] = __( 'Type', 'little-river-trailer-inventory' ) . ': ' . $term_label( $options['type'], $filters['type'] );
		}
		if ( ! empty( $filters['condition'] ) ) {
			$labels['condition'] = __( 'Condition', 'little-river-trailer-inventory' ) . ': ' . $term_label( $options['condition'], $filters['condition'] );
		}
		if ( ! empty( $filters['availability'] ) ) {
			$labels['availability'] = __( 'Availability', 'little-river-trailer-inventory' ) . ': ' . $term_label( $options['availability'], $filters['availability'] );
		}

		$min_price = $filters['min_price'] ?? '';
		$max_price = $filters['max_price'] ?? '';
		if ( '' !== $min_price || '' !== $max_price ) {
			$labels['price'] = __( 'Price', 'little-river-trailer-inventory' ) . ': ' . $this->range_label( $min_price, $max_price, $sym, true );
		}
		$min_year = $filters['min_year'] ?? '';
		$max_year = $filters['max_year'] ?? '';
		if ( '' !== $min_year || '' !== $max_year ) {
			$labels['year'] = __( 'Year', 'little-river-trailer-inventory' ) . ': ' . $this->range_label( $min_year, $max_year, '', false );
		}
		$min_gvwr = $filters['min_gvwr'] ?? '';
		$max_gvwr = $filters['max_gvwr'] ?? '';
		if ( '' !== $min_gvwr || '' !== $max_gvwr ) {
			$labels['gvwr'] = __( 'GVWR', 'little-river-trailer-inventory' ) . ': ' . $this->range_label( $min_gvwr, $max_gvwr, '', false );
		}
		if ( ! empty( $filters['axles'] ) ) {
			$labels['axles'] = __( 'Axles', 'little-river-trailer-inventory' ) . ': ' . $filters['axles'];
		}
		if ( ! empty( $filters['pull_type'] ) ) {
			$labels['pull_type'] = __( 'Pull Type', 'little-river-trailer-inventory' ) . ': ' . $filters['pull_type'];
		}
		if ( ! empty( $filters['featured'] ) ) {
			$labels['featured'] = __( 'Featured only', 'little-river-trailer-inventory' );
		}
		if ( ! empty( $filters['sale'] ) ) {
			$labels['sale'] = __( 'Sale only', 'little-river-trailer-inventory' );
		}

		// Specifications accordion chips (skip the three that already have chips).
		foreach ( self::spec_flat() as $key => $def ) {
			if ( in_array( $key, array( 'gvwr', 'axles', 'pull_type' ), true ) ) {
				continue;
			}
			if ( 'range' === $def['kind'] ) {
				$mn = $filters[ 'min_' . $key ] ?? '';
				$mx = $filters[ 'max_' . $key ] ?? '';
				if ( '' !== $mn || '' !== $mx ) {
					$labels[ $key ] = $def['label'] . ': ' . $this->range_label( $mn, $mx, '', false );
				}
			} else {
				$v = isset( $filters[ $key ] ) ? (string) $filters[ $key ] : '';
				if ( '' !== $v ) {
					$labels[ $key ] = $def['label'] . ': ' . $v;
				}
			}
		}

		return (array) apply_filters( 'lrti_inventory_active_filter_labels', $labels, $filters );
	}

	/**
	 * Format a range like "$5,000–$10,000", "Min $5,000", or "Up to $10,000".
	 *
	 * @param string $min      Minimum.
	 * @param string $max      Maximum.
	 * @param string $prefix   Currency/prefix.
	 * @param bool   $thousands Whether to thousands-format.
	 * @return string
	 */
	private function range_label( string $min, string $max, string $prefix, bool $thousands ): string {
		$fmt = static function ( string $v ) use ( $prefix, $thousands ): string {
			$n = (float) $v;
			return $prefix . ( $thousands ? number_format_i18n( $n ) : (string) (int) $n );
		};
		if ( '' !== $min && '' !== $max ) {
			return $fmt( $min ) . '–' . $fmt( $max );
		}
		if ( '' !== $min ) {
			/* translators: %s: minimum value */
			return sprintf( __( 'Min %s', 'little-river-trailer-inventory' ), $fmt( $min ) );
		}
		/* translators: %s: maximum value */
		return sprintf( __( 'Up to %s', 'little-river-trailer-inventory' ), $fmt( $max ) );
	}

	/**
	 * Pagination markup for a query, preserving active filters and sort.
	 *
	 * @param \WP_Query            $query   The query.
	 * @param array<string, mixed> $filters Current filters.
	 * @return string
	 */
	public function pagination_html( \WP_Query $query, array $filters ): string {
		$total = (int) $query->max_num_pages;
		if ( $total < 2 ) {
			return '';
		}

		$current = max( 1, (int) $query->get( 'paged' ) );
		if ( $current < 1 ) {
			$current = 1;
		}

		$add_args = array();
		foreach ( $filters as $k => $v ) {
			if ( '' !== $v && null !== $v ) {
				$add_args[ $k ] = $v;
			}
		}
		$sort = $this->validate_sort( isset( $filters['sort'] ) ? (string) $filters['sort'] : lrti_current_sort() );
		if ( 'newest' !== $sort ) {
			$add_args['sort'] = $sort;
		}

		$big   = 999999999;
		$links = paginate_links(
			array(
				'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
				'format'    => '?paged=%#%',
				'current'   => $current,
				'total'     => $total,
				'add_args'  => $add_args,
				'prev_text' => __( '&laquo; Previous', 'little-river-trailer-inventory' ),
				'next_text' => __( 'Next &raquo;', 'little-river-trailer-inventory' ),
				'type'      => 'list',
			)
		);

		if ( empty( $links ) ) {
			return '';
		}

		return '<nav class="lrti-pagination" aria-label="' . esc_attr__( 'Inventory pagination', 'little-river-trailer-inventory' ) . '">' . $links . '</nav>';
	}

	/**
	 * Render the empty-results state (filtered vs. truly empty).
	 *
	 * @param array<string, mixed> $filters Current filters.
	 * @return void
	 */
	public function render_empty( array $filters ): void {
		$phone = (string) lrti_get_setting( 'dealership_phone', '' );
		$name  = (string) lrti_get_setting( 'dealership_name', __( 'us', 'little-river-trailer-inventory' ) );
		$has   = ! empty( array_filter( $filters ) );

		$archive_url = (string) get_post_type_archive_link( PostTypes::POST_TYPE );
		$contact_url = (string) lrti_get_setting( 'contact_url', '' );
		?>
		<div class="lrti-empty">
			<span class="lrti-empty-icon" aria-hidden="true">
				<svg viewBox="0 0 64 40" xmlns="http://www.w3.org/2000/svg" focusable="false">
					<rect x="4" y="8" width="42" height="18" rx="2" fill="currentColor" opacity="0.15"/>
					<rect x="46" y="14" width="14" height="12" rx="1.5" fill="currentColor" opacity="0.15"/>
					<circle cx="18" cy="30" r="4" fill="currentColor" opacity="0.4"/>
					<circle cx="40" cy="30" r="4" fill="currentColor" opacity="0.4"/>
				</svg>
			</span>
			<?php if ( $has ) : ?>
				<h2 class="lrti-empty-title"><?php esc_html_e( 'No trailers match your search.', 'little-river-trailer-inventory' ); ?></h2>
				<p class="lrti-empty-text">
					<?php
					printf(
						/* translators: %s: dealership name */
						esc_html__( 'Try adjusting your filters or contact %s for current inventory.', 'little-river-trailer-inventory' ),
						esc_html( $name )
					);
					?>
				</p>
				<p class="lrti-empty-cta">
					<button type="button" class="lrti-btn lrti-btn--outline lrti-reset"><?php esc_html_e( 'Reset Filters', 'little-river-trailer-inventory' ); ?></button>
					<?php if ( '' !== $archive_url ) : ?>
						<a class="lrti-btn lrti-btn--outline" href="<?php echo esc_url( $archive_url ); ?>"><?php esc_html_e( 'View All Inventory', 'little-river-trailer-inventory' ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $contact_url ) : ?>
						<a class="lrti-btn lrti-btn--primary" href="<?php echo esc_url( $contact_url ); ?>"><?php esc_html_e( 'Contact Us', 'little-river-trailer-inventory' ); ?></a>
					<?php elseif ( '' !== $phone ) : ?>
						<a class="lrti-btn lrti-btn--primary" href="tel:<?php echo esc_attr( lrti_get_tel_href( $phone ) ); ?>">
							<?php
							printf(
								/* translators: %s: phone */
								esc_html__( 'Call %s', 'little-river-trailer-inventory' ),
								esc_html( $phone )
							);
							?>
						</a>
					<?php endif; ?>
				</p>
			<?php else : ?>
				<h2 class="lrti-empty-title"><?php esc_html_e( 'No Inventory Available', 'little-river-trailer-inventory' ); ?></h2>
				<p class="lrti-empty-text"><?php esc_html_e( 'Please check back soon or contact us for current inventory.', 'little-river-trailer-inventory' ); ?></p>
				<p class="lrti-empty-cta">
					<?php if ( '' !== $contact_url ) : ?>
						<a class="lrti-btn lrti-btn--primary" href="<?php echo esc_url( $contact_url ); ?>"><?php esc_html_e( 'Contact Us', 'little-river-trailer-inventory' ); ?></a>
					<?php elseif ( '' !== $phone ) : ?>
						<a class="lrti-btn lrti-btn--primary" href="tel:<?php echo esc_attr( lrti_get_tel_href( $phone ) ); ?>">
							<?php
							printf(
								/* translators: %s: phone */
								esc_html__( 'Call %s', 'little-river-trailer-inventory' ),
								esc_html( $phone )
							);
							?>
						</a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
