<?php
/**
 * Dynamic homepage trailer-type category cards.
 *
 * Adds "Homepage Category Card" settings to each Trailer Type term (stored in
 * WordPress term meta), admin list columns, and the [twc_trailer_categories]
 * shortcode that builds the homepage category grid from enabled Trailer Types.
 *
 * This feature is additive: it does not alter the trailer_type taxonomy
 * registration, inventory, archives, URLs, filtering, or SEO.
 *
 * @package LRTI
 */

namespace LRTI;

defined( 'ABSPATH' ) || exit;

/**
 * Homepage category cards driven by Trailer Type term meta.
 */
class HomeCategories {

	/**
	 * The taxonomy that powers the cards.
	 */
	private const TAXONOMY = 'trailer_type';

	/**
	 * Term-meta keys.
	 */
	private const META_IMAGE       = '_twc_home_card_image';
	private const META_SHOW        = '_twc_home_show';
	private const META_ORDER       = '_twc_home_order';
	private const META_LABEL       = '_twc_home_label';
	private const META_DESTINATION = '_twc_home_destination';

	/**
	 * Archive-page term-meta keys.
	 */
	private const META_A_HEADING = '_twc_archive_heading';
	private const META_A_INTRO   = '_twc_archive_intro';
	private const META_A_HERO    = '_twc_archive_hero';
	private const META_A_CONTENT = '_twc_archive_content';
	private const META_A_SEO     = '_twc_archive_seo';
	private const META_A_EMPTY   = '_twc_archive_empty';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		$tax = self::TAXONOMY;

		// Term edit / add screens.
		add_action( $tax . '_add_form_fields', array( $this, 'render_add_fields' ) );
		add_action( $tax . '_edit_form_fields', array( $this, 'render_edit_fields' ), 10, 2 );
		add_action( 'created_' . $tax, array( $this, 'save_fields' ) );
		add_action( 'edited_' . $tax, array( $this, 'save_fields' ) );

		// Admin list columns.
		add_filter( 'manage_edit-' . $tax . '_columns', array( $this, 'columns' ) );
		add_filter( 'manage_' . $tax . '_custom_column', array( $this, 'column_content' ), 10, 3 );

		// Assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_style' ) );

		// Shortcode.
		add_shortcode( 'twc_trailer_categories', array( $this, 'shortcode' ) );
	}

	/* ===================================================================
	 * Admin: term fields
	 * =================================================================== */

	/**
	 * Fields shown on the "Add New Trailer Type" screen.
	 *
	 * @return void
	 */
	public function render_add_fields(): void {
		wp_nonce_field( 'twc_home_card_save', 'twc_home_card_nonce' );
		?>
		<div class="form-field">
			<label><?php esc_html_e( 'Category Card Image', 'little-river-trailer-inventory' ); ?></label>
			<?php $this->image_control( 0 ); ?>
		</div>
		<div class="form-field">
			<label for="twc_home_show"><input type="checkbox" id="twc_home_show" name="twc_home_show" value="1" /> <?php esc_html_e( 'Show this trailer type on the homepage', 'little-river-trailer-inventory' ); ?></label>
		</div>
		<div class="form-field">
			<label for="twc_home_order"><?php esc_html_e( 'Display Order', 'little-river-trailer-inventory' ); ?></label>
			<input type="number" id="twc_home_order" name="twc_home_order" value="0" step="1" min="0" />
		</div>
		<div class="form-field">
			<label for="twc_home_label"><?php esc_html_e( 'Homepage Card Label', 'little-river-trailer-inventory' ); ?></label>
			<input type="text" id="twc_home_label" name="twc_home_label" value="" />
			<p class="description"><?php esc_html_e( 'Optional. If blank, the Trailer Type name is used.', 'little-river-trailer-inventory' ); ?></p>
		</div>
		<div class="form-field">
			<label for="twc_home_destination"><?php esc_html_e( 'Destination Override', 'little-river-trailer-inventory' ); ?></label>
			<input type="url" id="twc_home_destination" name="twc_home_destination" value="" placeholder="https://" />
			<p class="description"><?php esc_html_e( 'Optional. A custom URL to link the card to. If blank, the card links to the Trailer Type archive.', 'little-river-trailer-inventory' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Fields shown on the "Edit Trailer Type" screen.
	 *
	 * @param \WP_Term $term The term being edited.
	 * @return void
	 */
	public function render_edit_fields( $term ): void {
		$image = (int) get_term_meta( $term->term_id, self::META_IMAGE, true );
		$show  = '1' === (string) get_term_meta( $term->term_id, self::META_SHOW, true );
		$order = (int) get_term_meta( $term->term_id, self::META_ORDER, true );
		$label = (string) get_term_meta( $term->term_id, self::META_LABEL, true );
		$dest  = (string) get_term_meta( $term->term_id, self::META_DESTINATION, true );
		wp_nonce_field( 'twc_home_card_save', 'twc_home_card_nonce' );
		?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Homepage Category Card', 'little-river-trailer-inventory' ); ?></th>
			<td><p class="description"><?php esc_html_e( 'Control how this trailer type appears in the homepage category grid.', 'little-river-trailer-inventory' ); ?></p></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label><?php esc_html_e( 'Category Card Image', 'little-river-trailer-inventory' ); ?></label></th>
			<td><?php $this->image_control( $image ); ?></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Show on Homepage', 'little-river-trailer-inventory' ); ?></th>
			<td><label for="twc_home_show"><input type="checkbox" id="twc_home_show" name="twc_home_show" value="1" <?php checked( $show ); ?> /> <?php esc_html_e( 'Show this trailer type on the homepage', 'little-river-trailer-inventory' ); ?></label></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="twc_home_order"><?php esc_html_e( 'Display Order', 'little-river-trailer-inventory' ); ?></label></th>
			<td><input type="number" id="twc_home_order" name="twc_home_order" value="<?php echo esc_attr( (string) $order ); ?>" step="1" min="0" /><p class="description"><?php esc_html_e( 'Cards are sorted ascending by this number.', 'little-river-trailer-inventory' ); ?></p></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="twc_home_label"><?php esc_html_e( 'Homepage Card Label', 'little-river-trailer-inventory' ); ?></label></th>
			<td><input type="text" id="twc_home_label" name="twc_home_label" value="<?php echo esc_attr( $label ); ?>" class="regular-text" /><p class="description"><?php esc_html_e( 'Optional. If blank, the Trailer Type name is used.', 'little-river-trailer-inventory' ); ?></p></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="twc_home_destination"><?php esc_html_e( 'Destination Override', 'little-river-trailer-inventory' ); ?></label></th>
			<td><input type="url" id="twc_home_destination" name="twc_home_destination" value="<?php echo esc_attr( $dest ); ?>" class="regular-text" placeholder="https://" /><p class="description"><?php esc_html_e( 'Optional. A custom URL to link the card to. If blank, the card links to the Trailer Type archive.', 'little-river-trailer-inventory' ); ?></p></td>
		</tr>

		<?php
		$a_heading = (string) get_term_meta( $term->term_id, self::META_A_HEADING, true );
		$a_intro   = (string) get_term_meta( $term->term_id, self::META_A_INTRO, true );
		$a_hero    = (int) get_term_meta( $term->term_id, self::META_A_HERO, true );
		$a_content = (string) get_term_meta( $term->term_id, self::META_A_CONTENT, true );
		$a_seo     = (string) get_term_meta( $term->term_id, self::META_A_SEO, true );
		$a_empty   = (string) get_term_meta( $term->term_id, self::META_A_EMPTY, true );
		?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Archive Page', 'little-river-trailer-inventory' ); ?></th>
			<td><p class="description"><?php esc_html_e( 'Control the heading, intro, and content shown on this trailer type\'s inventory archive page.', 'little-river-trailer-inventory' ); ?></p></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="twc_archive_heading"><?php esc_html_e( 'Archive Heading', 'little-river-trailer-inventory' ); ?></label></th>
			<td><input type="text" id="twc_archive_heading" name="twc_archive_heading" value="<?php echo esc_attr( $a_heading ); ?>" class="regular-text" /><p class="description"><?php esc_html_e( 'Optional. Defaults to the Trailer Type name.', 'little-river-trailer-inventory' ); ?></p></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="twc_archive_intro"><?php esc_html_e( 'Archive Intro', 'little-river-trailer-inventory' ); ?></label></th>
			<td><textarea id="twc_archive_intro" name="twc_archive_intro" rows="3" class="large-text"><?php echo esc_textarea( $a_intro ); ?></textarea><p class="description"><?php esc_html_e( 'Optional. A short paragraph shown under the heading.', 'little-river-trailer-inventory' ); ?></p></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label><?php esc_html_e( 'Archive Hero Image', 'little-river-trailer-inventory' ); ?></label></th>
			<td><?php $this->image_control( $a_hero, 'twc_archive_hero' ); ?><p class="description"><?php esc_html_e( 'Optional. Shown above the heading. Left blank shows nothing.', 'little-river-trailer-inventory' ); ?></p></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="twc_archive_content"><?php esc_html_e( 'Archive Content', 'little-river-trailer-inventory' ); ?></label></th>
			<td>
				<?php
				wp_editor(
					$a_content,
					'twc_archive_content',
					array(
						'textarea_name' => 'twc_archive_content',
						'textarea_rows' => 8,
						'media_buttons' => true,
					)
				);
				?>
				<p class="description"><?php esc_html_e( 'Optional. Shown in an "About" section below the listings. Left blank shows nothing.', 'little-river-trailer-inventory' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="twc_archive_empty"><?php esc_html_e( 'Empty State Message', 'little-river-trailer-inventory' ); ?></label></th>
			<td><textarea id="twc_archive_empty" name="twc_archive_empty" rows="2" class="large-text"><?php echo esc_textarea( $a_empty ); ?></textarea><p class="description"><?php esc_html_e( 'Optional. Shown when no trailers of this type are in stock.', 'little-river-trailer-inventory' ); ?></p></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="twc_archive_seo"><?php esc_html_e( 'SEO Summary', 'little-river-trailer-inventory' ); ?></label></th>
			<td><textarea id="twc_archive_seo" name="twc_archive_seo" rows="2" class="large-text"><?php echo esc_textarea( $a_seo ); ?></textarea><p class="description"><?php esc_html_e( 'Optional. Reserved for future SEO use.', 'little-river-trailer-inventory' ); ?></p></td>
		</tr>
		<?php
	}

	/**
	 * Render the media-library image control (preview + buttons + hidden input).
	 *
	 * @param int $attachment_id Current attachment ID (0 for none).
	 * @return void
	 */
	private function image_control( int $attachment_id, string $field_name = 'twc_home_image' ): void {
		$src = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
		?>
		<div class="twc-home-image" data-twc-home-image>
			<div class="twc-home-image-preview">
				<?php if ( $src ) : ?>
					<img src="<?php echo esc_url( $src ); ?>" alt="" />
				<?php else : ?>
					<span class="twc-home-image-placeholder dashicons dashicons-format-image" aria-hidden="true"></span>
				<?php endif; ?>
			</div>
			<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( (string) $attachment_id ); ?>" class="twc-home-image-id" />
			<p>
				<button type="button" class="button twc-home-image-select"><?php esc_html_e( 'Select Image', 'little-river-trailer-inventory' ); ?></button>
				<button type="button" class="button twc-home-image-remove" <?php disabled( ! $attachment_id ); ?>><?php esc_html_e( 'Remove Image', 'little-river-trailer-inventory' ); ?></button>
			</p>
		</div>
		<?php
	}

	/**
	 * Save the homepage card fields for a term.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_fields( int $term_id ): void {
		if ( ! isset( $_POST['twc_home_card_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['twc_home_card_nonce'] ) ), 'twc_home_card_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// Image: must be a real attachment, else clear.
		if ( isset( $_POST['twc_home_image'] ) ) {
			$image = absint( wp_unslash( $_POST['twc_home_image'] ) );
			if ( $image > 0 && 'attachment' === get_post_type( $image ) ) {
				update_term_meta( $term_id, self::META_IMAGE, $image );
			} else {
				delete_term_meta( $term_id, self::META_IMAGE );
			}
		}

		update_term_meta( $term_id, self::META_SHOW, isset( $_POST['twc_home_show'] ) ? '1' : '0' );

		$order = isset( $_POST['twc_home_order'] ) ? absint( wp_unslash( $_POST['twc_home_order'] ) ) : 0;
		update_term_meta( $term_id, self::META_ORDER, $order );

		$label = isset( $_POST['twc_home_label'] ) ? sanitize_text_field( wp_unslash( $_POST['twc_home_label'] ) ) : '';
		if ( '' !== $label ) {
			update_term_meta( $term_id, self::META_LABEL, $label );
		} else {
			delete_term_meta( $term_id, self::META_LABEL );
		}

		$dest = isset( $_POST['twc_home_destination'] ) ? esc_url_raw( wp_unslash( $_POST['twc_home_destination'] ) ) : '';
		if ( '' !== $dest ) {
			update_term_meta( $term_id, self::META_DESTINATION, $dest );
		} else {
			delete_term_meta( $term_id, self::META_DESTINATION );
		}

		// --- Archive-page fields ---
		$this->save_text_meta( $term_id, self::META_A_HEADING, 'twc_archive_heading' );
		$this->save_textarea_meta( $term_id, self::META_A_INTRO, 'twc_archive_intro' );
		$this->save_textarea_meta( $term_id, self::META_A_EMPTY, 'twc_archive_empty' );
		$this->save_textarea_meta( $term_id, self::META_A_SEO, 'twc_archive_seo' );

		if ( isset( $_POST['twc_archive_hero'] ) ) {
			$hero = absint( wp_unslash( $_POST['twc_archive_hero'] ) );
			if ( $hero > 0 && 'attachment' === get_post_type( $hero ) ) {
				update_term_meta( $term_id, self::META_A_HERO, $hero );
			} else {
				delete_term_meta( $term_id, self::META_A_HERO );
			}
		}

		if ( isset( $_POST['twc_archive_content'] ) ) {
			$content = wp_kses_post( wp_unslash( $_POST['twc_archive_content'] ) );
			if ( '' !== trim( $content ) ) {
				update_term_meta( $term_id, self::META_A_CONTENT, $content );
			} else {
				delete_term_meta( $term_id, self::META_A_CONTENT );
			}
		}
	}

	/**
	 * Save a sanitized single-line text field to term meta (delete if empty).
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $meta_key Meta key.
	 * @param string $field    POST field name.
	 * @return void
	 */
	private function save_text_meta( int $term_id, string $meta_key, string $field ): void {
		if ( ! isset( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in save_fields.
			return;
		}
		$val = sanitize_text_field( wp_unslash( $_POST[ $field ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' !== $val ) {
			update_term_meta( $term_id, $meta_key, $val );
		} else {
			delete_term_meta( $term_id, $meta_key );
		}
	}

	/**
	 * Save a sanitized multi-line text field to term meta (delete if empty).
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $meta_key Meta key.
	 * @param string $field    POST field name.
	 * @return void
	 */
	private function save_textarea_meta( int $term_id, string $meta_key, string $field ): void {
		if ( ! isset( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in save_fields.
			return;
		}
		$val = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' !== trim( $val ) ) {
			update_term_meta( $term_id, $meta_key, $val );
		} else {
			delete_term_meta( $term_id, $meta_key );
		}
	}

	/* ===================================================================
	 * Admin: list columns
	 * =================================================================== */

	/**
	 * Add custom columns to the Trailer Types list table.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'name' === $key ) {
				$new['twc_home_image'] = __( 'Image', 'little-river-trailer-inventory' );
			}
			$new[ $key ] = $label;
			if ( 'name' === $key ) {
				$new['twc_home_show']  = __( 'Homepage', 'little-river-trailer-inventory' );
				$new['twc_home_order'] = __( 'Order', 'little-river-trailer-inventory' );
			}
		}
		return $new;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $content Existing content.
	 * @param string $column  Column key.
	 * @param int    $term_id Term ID.
	 * @return string
	 */
	public function column_content( string $content, string $column, int $term_id ): string {
		switch ( $column ) {
			case 'twc_home_image':
				$image = (int) get_term_meta( $term_id, self::META_IMAGE, true );
				if ( $image ) {
					return wp_get_attachment_image( $image, array( 44, 44 ), true, array( 'style' => 'width:44px;height:44px;object-fit:cover;border-radius:4px;' ) );
				}
				return '<span class="dashicons dashicons-format-image" aria-hidden="true" style="color:#c8ced4;"></span>';

			case 'twc_home_show':
				$show = '1' === (string) get_term_meta( $term_id, self::META_SHOW, true );
				return $show
					? '<span style="color:#2e7d32;font-weight:600;">' . esc_html__( 'Yes', 'little-river-trailer-inventory' ) . '</span>'
					: '<span style="color:#7c8792;">' . esc_html__( 'No', 'little-river-trailer-inventory' ) . '</span>';

			case 'twc_home_order':
				return esc_html( (string) (int) get_term_meta( $term_id, self::META_ORDER, true ) );
		}
		return $content;
	}

	/* ===================================================================
	 * Assets
	 * =================================================================== */

	/**
	 * Enqueue media library + term-image JS on the Trailer Types screens only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function admin_assets( string $hook ): void {
		if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || self::TAXONOMY !== $screen->taxonomy ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'twc-home-term-image',
			LRTI_PLUGIN_URL . 'admin/js/term-image.js',
			array( 'jquery' ),
			LRTI_VERSION,
			true
		);
		wp_localize_script(
			'twc-home-term-image',
			'twcHomeImage',
			array(
				'title'  => __( 'Select Category Card Image', 'little-river-trailer-inventory' ),
				'button' => __( 'Use this image', 'little-river-trailer-inventory' ),
			)
		);
	}

	/**
	 * Register (not enqueue) the frontend category grid stylesheet.
	 *
	 * @return void
	 */
	public function register_frontend_style(): void {
		wp_register_style(
			'twc-trailer-categories',
			LRTI_PLUGIN_URL . 'public/css/categories.css',
			array(),
			LRTI_VERSION
		);
	}

	/* ===================================================================
	 * Shortcode
	 * =================================================================== */

	/**
	 * [twc_trailer_categories] — build the homepage category grid.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'columns' => '4',
				'limit'   => '8',
				'heading' => 'true',
			),
			$atts,
			'twc_trailer_categories'
		);

		$columns = max( 1, min( 6, (int) $atts['columns'] ) );
		$limit   = max( 0, (int) $atts['limit'] );
		$heading = filter_var( $atts['heading'], FILTER_VALIDATE_BOOLEAN );

		$terms = $this->get_home_terms( $limit );
		if ( empty( $terms ) ) {
			return '';
		}

		wp_enqueue_style( 'twc-trailer-categories' );

		ob_start();
		?>
		<section class="twc-trailer-categories" style="--twc-cat-cols: <?php echo esc_attr( (string) $columns ); ?>;">
			<?php if ( $heading ) : ?>
				<div class="twc-cat-header">
					<span class="twc-cat-eyebrow"><?php esc_html_e( 'TRAILER CATEGORIES', 'little-river-trailer-inventory' ); ?></span>
					<h2 class="twc-cat-title"><?php esc_html_e( 'Browse Trailers by Type', 'little-river-trailer-inventory' ); ?></h2>
				</div>
			<?php endif; ?>
			<div class="twc-cat-grid">
				<?php foreach ( $terms as $term ) : ?>
					<?php echo $this->render_card( $term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render_card. ?>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Get enabled Trailer Types ordered by display order.
	 *
	 * @param int $limit Max terms (0 = no limit).
	 * @return array<int, \WP_Term>
	 */
	private function get_home_terms( int $limit ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'   => self::META_SHOW,
						'value' => '1',
					),
				),
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		usort(
			$terms,
			static function ( $a, $b ) {
				$oa = (int) get_term_meta( $a->term_id, self::META_ORDER, true );
				$ob = (int) get_term_meta( $b->term_id, self::META_ORDER, true );
				// Treat 0 (the default/unset) as "no preference" so it sorts
				// after any type given a positive Display Order. This makes
				// "set Order to 1" put a type first, which is what dealers expect.
				if ( $oa <= 0 ) {
					$oa = PHP_INT_MAX;
				}
				if ( $ob <= 0 ) {
					$ob = PHP_INT_MAX;
				}
				if ( $oa === $ob ) {
					return strcasecmp( $a->name, $b->name );
				}
				return $oa <=> $ob;
			}
		);

		if ( $limit > 0 && count( $terms ) > $limit ) {
			$terms = array_slice( $terms, 0, $limit );
		}
		return $terms;
	}

	/**
	 * Render a single category card (fully escaped).
	 *
	 * @param \WP_Term $term The trailer type term.
	 * @return string
	 */
	private function render_card( \WP_Term $term ): string {
		$label = (string) get_term_meta( $term->term_id, self::META_LABEL, true );
		if ( '' === $label ) {
			$label = $term->name;
		}

		$dest = (string) get_term_meta( $term->term_id, self::META_DESTINATION, true );
		if ( '' === $dest ) {
			$archive = get_term_link( $term );
			$dest    = is_wp_error( $archive ) ? home_url( '/' ) : $archive;
		}

		$image_id = (int) get_term_meta( $term->term_id, self::META_IMAGE, true );
		/* translators: %s: trailer type name. */
		$alt = sprintf( __( '%s available from Little River Equipment Sales', 'little-river-trailer-inventory' ), $term->name );

		if ( $image_id ) {
			$img = wp_get_attachment_image(
				$image_id,
				'large',
				false,
				array(
					'class'   => 'twc-cat-img',
					'alt'     => $alt,
					'loading' => 'lazy',
				)
			);
		} else {
			$img = '<img class="twc-cat-img" src="' . esc_url( $this->placeholder_url() ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" />';
		}

		ob_start();
		?>
		<a class="twc-cat-card" href="<?php echo esc_url( $dest ); ?>">
			<span class="twc-cat-media"><?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image/escaped above. ?></span>
			<span class="twc-cat-footer">
				<span class="twc-cat-icon dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				<span class="twc-cat-label"><?php echo esc_html( $label ); ?></span>
			</span>
		</a>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Placeholder image URL used when a term has no card image.
	 *
	 * @return string
	 */
	private function placeholder_url(): string {
		return LRTI_PLUGIN_URL . 'public/images/category-placeholder.svg';
	}
}
