<?php
/**
 * Featured toggle in the All Trailers list (Sprint 2.2.0).
 *
 * Adds a "Featured" column with a one-click star toggle (AJAX), a sortable
 * (featured-first) column, a Quick Edit checkbox, and bulk actions to feature
 * or unfeature multiple trailers. Reuses the existing `_lrti_featured` meta, so
 * the front-end Featured badge and [lrti_featured_inventory] shortcode update
 * automatically. Every write checks capability + nonce.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FeaturedAdmin
 */
final class FeaturedAdmin {

	private const META_KEY   = '_lrti_featured';
	private const NONCE      = 'lrti_toggle_featured';
	private const QE_NONCE   = 'lrti_qe_featured_nonce';
	private const QE_ACTION  = 'lrti_qe_featured';

	/**
	 * Attach hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		$pt = PostTypes::POST_TYPE;

		add_filter( "manage_{$pt}_posts_columns", array( $this, 'add_column' ) );
		add_action( "manage_{$pt}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		add_filter( "manage_edit-{$pt}_sortable_columns", array( $this, 'sortable' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_sort' ) );
		add_filter( 'posts_clauses', array( $this, 'sort_clauses' ), 10, 2 );

		add_action( 'wp_ajax_lrti_toggle_featured', array( $this, 'ajax_toggle' ) );

		add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_box' ), 10, 2 );
		add_action( "save_post_{$pt}", array( $this, 'save_quick_edit' ) );

		add_filter( "bulk_actions-edit-{$pt}", array( $this, 'bulk_actions' ) );
		add_filter( "handle_bulk_actions-edit-{$pt}", array( $this, 'handle_bulk' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_notice' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Is a trailer featured?
	 *
	 * @param int $post_id Trailer ID.
	 * @return bool
	 */
	private function is_featured( int $post_id ): bool {
		return '1' === (string) get_post_meta( $post_id, self::META_KEY, true );
	}

	/**
	 * Clear the cached filter-option lists (Featured affects filter counts).
	 *
	 * @return void
	 */
	private function clear_cache(): void {
		delete_transient( 'lrti_filter_options' );
	}

	/**
	 * Insert the Featured column after the Type column.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'lrti_type' === $key ) {
				$new['lrti_featured'] = __( 'Featured', 'little-river-trailer-inventory' );
			}
		}
		if ( ! isset( $new['lrti_featured'] ) ) {
			$new['lrti_featured'] = __( 'Featured', 'little-river-trailer-inventory' );
		}
		return $new;
	}

	/**
	 * Render the star toggle button for the Featured column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( 'lrti_featured' !== $column ) {
			return;
		}
		$featured = $this->is_featured( $post_id );
		$can_edit = current_user_can( 'edit_post', $post_id );

		// Hidden value for Quick Edit prefill.
		printf( '<span class="lrti-featured-value" style="display:none;">%s</span>', $featured ? '1' : '0' );

		if ( ! $can_edit ) {
			echo $featured
				? '<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>'
				: '<span class="dashicons dashicons-star-empty" aria-hidden="true"></span>';
			return;
		}

		printf(
			'<button type="button" class="button-link lrti-feature-toggle%1$s" data-id="%2$d" data-nonce="%3$s" aria-pressed="%4$s" title="%5$s"><span class="dashicons %6$s" aria-hidden="true"></span><span class="screen-reader-text">%7$s</span></button>',
			$featured ? ' is-featured' : '',
			(int) $post_id,
			esc_attr( wp_create_nonce( self::NONCE ) ),
			$featured ? 'true' : 'false',
			esc_attr__( 'Toggle featured', 'little-river-trailer-inventory' ),
			$featured ? 'dashicons-star-filled' : 'dashicons-star-empty',
			$featured
				? esc_html__( 'Featured (click to remove)', 'little-river-trailer-inventory' )
				: esc_html__( 'Not featured (click to feature)', 'little-river-trailer-inventory' )
		);
	}

	/**
	 * Make the Featured column sortable.
	 *
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public function sortable( array $columns ): array {
		$columns['lrti_featured'] = 'lrti_featured';
		return $columns;
	}

	/**
	 * Flag the query when sorting by Featured (handled in sort_clauses).
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function apply_sort( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( PostTypes::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		if ( 'lrti_featured' === $query->get( 'orderby' ) ) {
			$query->set( 'lrti_order_featured', $query->get( 'order' ) ?: 'DESC' );
		}
	}

	/**
	 * LEFT JOIN so featured-first sorting keeps non-featured trailers visible.
	 *
	 * @param array<string, string> $clauses SQL clauses.
	 * @param \WP_Query              $query   Query.
	 * @return array<string, string>
	 */
	public function sort_clauses( array $clauses, \WP_Query $query ): array {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return $clauses;
		}
		$order = $query->get( 'lrti_order_featured' );
		if ( ! $order ) {
			return $clauses;
		}
		global $wpdb;
		$order         = 'ASC' === strtoupper( (string) $order ) ? 'ASC' : 'DESC';
		$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} lrti_feat ON ( {$wpdb->posts}.ID = lrti_feat.post_id AND lrti_feat.meta_key = '_lrti_featured' ) ";
		$clauses['orderby'] = " COALESCE( lrti_feat.meta_value, '0' ) {$order}, {$wpdb->posts}.post_date DESC ";
		$clauses['groupby'] = "{$wpdb->posts}.ID";
		return $clauses;
	}

	/**
	 * AJAX: toggle a trailer's featured state.
	 *
	 * @return void
	 */
	public function ajax_toggle(): void {
		check_ajax_referer( self::NONCE, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! $post_id || PostTypes::POST_TYPE !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid trailer.', 'little-river-trailer-inventory' ) ), 400 );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this trailer.', 'little-river-trailer-inventory' ) ), 403 );
		}

		$featured = ! $this->is_featured( $post_id );
		if ( $featured ) {
			update_post_meta( $post_id, self::META_KEY, '1' );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
		$this->clear_cache();

		wp_send_json_success(
			array(
				'featured' => $featured,
				'label'    => $featured
					? __( 'Featured (click to remove)', 'little-river-trailer-inventory' )
					: __( 'Not featured (click to feature)', 'little-river-trailer-inventory' ),
			)
		);
	}

	/**
	 * Quick Edit checkbox.
	 *
	 * @param string $column_name Column being edited.
	 * @param string $post_type   Post type.
	 * @return void
	 */
	public function quick_edit_box( string $column_name, string $post_type ): void {
		if ( 'lrti_featured' !== $column_name || PostTypes::POST_TYPE !== $post_type ) {
			return;
		}
		wp_nonce_field( self::QE_ACTION, self::QE_NONCE );
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label class="alignleft">
					<input type="checkbox" name="lrti_featured_qe" value="1" class="lrti-qe-featured" />
					<span class="checkbox-title"><?php esc_html_e( 'Featured trailer', 'little-river-trailer-inventory' ); ?></span>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Save the Quick Edit featured checkbox (only when the QE nonce is present).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_quick_edit( int $post_id ): void {
		if ( ! isset( $_POST[ self::QE_NONCE ] ) ) {
			return; // Not a Quick Edit save (full-editor saves are handled elsewhere).
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::QE_NONCE ] ) ), self::QE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( PostTypes::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		if ( ! empty( $_POST['lrti_featured_qe'] ) ) {
			update_post_meta( $post_id, self::META_KEY, '1' );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
		$this->clear_cache();
	}

	/**
	 * Add feature/unfeature bulk actions.
	 *
	 * @param array<string, string> $actions Bulk actions.
	 * @return array<string, string>
	 */
	public function bulk_actions( array $actions ): array {
		$actions['lrti_feature']   = __( 'Mark as Featured', 'little-river-trailer-inventory' );
		$actions['lrti_unfeature'] = __( 'Remove Featured Status', 'little-river-trailer-inventory' );
		return $actions;
	}

	/**
	 * Handle feature/unfeature bulk actions.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Bulk action.
	 * @param int[]  $ids      Post IDs.
	 * @return string
	 */
	public function handle_bulk( string $redirect, string $action, array $ids ): string {
		if ( ! in_array( $action, array( 'lrti_feature', 'lrti_unfeature' ), true ) ) {
			return $redirect;
		}
		$feature = 'lrti_feature' === $action;
		$count   = 0;
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( PostTypes::POST_TYPE !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			if ( $feature ) {
				update_post_meta( $id, self::META_KEY, '1' );
			} else {
				delete_post_meta( $id, self::META_KEY );
			}
			++$count;
		}
		$this->clear_cache();
		return add_query_arg( array( 'lrti_featured_bulk' => $feature ? 'on' : 'off', 'lrti_featured_n' => $count ), $redirect );
	}

	/**
	 * Bulk-action result notice.
	 *
	 * @return void
	 */
	public function bulk_notice(): void {
		if ( empty( $_GET['lrti_featured_bulk'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.
			return;
		}
		$on = 'on' === sanitize_key( wp_unslash( $_GET['lrti_featured_bulk'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$n  = isset( $_GET['lrti_featured_n'] ) ? absint( wp_unslash( $_GET['lrti_featured_n'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = $on
			/* translators: %d: number of trailers. */
			? sprintf( _n( '%d trailer marked as featured.', '%d trailers marked as featured.', $n, 'little-river-trailer-inventory' ), $n )
			/* translators: %d: number of trailers. */
			: sprintf( _n( 'Featured status removed from %d trailer.', 'Featured status removed from %d trailers.', $n, 'little-river-trailer-inventory' ), $n );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	/**
	 * Enqueue the toggle script/styles on the All Trailers list screen only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( 'edit.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'edit-' . PostTypes::POST_TYPE !== $screen->id ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		$css = '.column-lrti_featured{width:80px;text-align:center;}'
			. '.lrti-feature-toggle{cursor:pointer;padding:0;border:0;background:none;color:#b8b8b8;}'
			. '.lrti-feature-toggle .dashicons{font-size:22px;width:22px;height:22px;line-height:22px;}'
			. '.lrti-feature-toggle.is-featured{color:#a8321d;}'
			. '.lrti-feature-toggle[aria-busy="true"]{opacity:.5;}'
			. '.column-lrti_featured .dashicons-star-filled{color:#a8321d;}';
		wp_register_style( 'lrti-featured-admin', false, array(), LRTI_VERSION );
		wp_enqueue_style( 'lrti-featured-admin' );
		wp_add_inline_style( 'lrti-featured-admin', $css );

		$js = <<<'JS'
( function () {
	var A = window.lrtiFeatured || {};
	function label( on ) { return on ? A.on : A.off; }
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '.lrti-feature-toggle' ) : null;
		if ( ! btn ) { return; }
		e.preventDefault();
		if ( btn.getAttribute( 'aria-busy' ) === 'true' ) { return; }
		btn.setAttribute( 'aria-busy', 'true' );
		var body = new URLSearchParams();
		body.set( 'action', 'lrti_toggle_featured' );
		body.set( 'post_id', btn.getAttribute( 'data-id' ) );
		body.set( 'nonce', btn.getAttribute( 'data-nonce' ) );
		fetch( A.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				btn.removeAttribute( 'aria-busy' );
				if ( ! json || ! json.success ) { window.alert( ( json && json.data && json.data.message ) || A.error ); return; }
				var on = !! json.data.featured;
				btn.classList.toggle( 'is-featured', on );
				btn.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
				btn.setAttribute( 'title', label( on ) );
				var icon = btn.querySelector( '.dashicons' );
				if ( icon ) { icon.className = 'dashicons ' + ( on ? 'dashicons-star-filled' : 'dashicons-star-empty' ); }
				var row = btn.closest( 'tr' );
				if ( row ) { var v = row.querySelector( '.lrti-featured-value' ); if ( v ) { v.textContent = on ? '1' : '0'; } }
			} )
			.catch( function () { btn.removeAttribute( 'aria-busy' ); window.alert( A.error ); } );
	} );

	// Prefill the Quick Edit checkbox from the row's hidden value.
	if ( window.jQuery ) {
		jQuery( function ( $ ) {
			$( '#the-list' ).on( 'click', '.editinline', function () {
				var id = $( this ).closest( 'tr' ).attr( 'id' );
				if ( ! id ) { return; }
				var val = $( '#' + id ).find( '.lrti-featured-value' ).text().trim();
				setTimeout( function () {
					$( 'input.lrti-qe-featured' ).prop( 'checked', '1' === val );
				}, 0 );
			} );
		} );
	}
} )();
JS;
		wp_register_script( 'lrti-featured-admin', '', array(), LRTI_VERSION, true );
		wp_enqueue_script( 'lrti-featured-admin' );
		wp_localize_script(
			'lrti-featured-admin',
			'lrtiFeatured',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'on'      => __( 'Featured (click to remove)', 'little-river-trailer-inventory' ),
				'off'     => __( 'Not featured (click to feature)', 'little-river-trailer-inventory' ),
				'error'   => __( 'Sorry, that could not be saved. Please try again.', 'little-river-trailer-inventory' ),
			)
		);
		wp_add_inline_script( 'lrti-featured-admin', $js );
	}
}
