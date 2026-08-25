<?php
/**
 * Built-in User Guide / Training Center.
 *
 * Adds a read-only "User Guide" page under the Trailer Inventory admin menu with
 * plain-English lessons, a searchable table of contents, accordions, HTML/CSS
 * diagrams, an accurate shortcode reference (with copy buttons), troubleshooting,
 * FAQs, and support details. This feature is additive and changes no public,
 * lead, form, shortcode, template, or database behavior.
 *
 * @package LRTI
 */

namespace LRTI;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the in-admin User Guide.
 */
class UserGuide {

	/**
	 * The documentation version (independent of the plugin version).
	 */
	private const DOC_VERSION = '2.9.16';

	/**
	 * Menu page hook suffix (set on registration; used to scope assets).
	 *
	 * @var string
	 */
	private string $page_hook = '';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Priority 20 so this lands after the core plugin submenus (last item).
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'audit_notice' ) );
	}

	/**
	 * Add the "User Guide" submenu under Trailer Inventory.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$this->page_hook = (string) add_submenu_page(
			'lrti-overview',
			__( 'TWC Trailer Inventory User Guide', 'little-river-trailer-inventory' ),
			__( 'User Guide', 'little-river-trailer-inventory' ),
			'manage_options',
			'lrti-user-guide',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Load guide assets on the guide page only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( $hook !== $this->page_hook || '' === $this->page_hook ) {
			return;
		}
		wp_enqueue_style(
			'lrti-user-guide',
			LRTI_PLUGIN_URL . 'admin/css/user-guide.css',
			array(),
			LRTI_VERSION
		);
		wp_enqueue_script(
			'lrti-user-guide',
			LRTI_PLUGIN_URL . 'admin/js/user-guide.js',
			array(),
			LRTI_VERSION,
			true
		);
		wp_localize_script(
			'lrti-user-guide',
			'lrtiGuide',
			array(
				'copied'   => __( 'Copied', 'little-river-trailer-inventory' ),
				'copy'     => __( 'Copy', 'little-river-trailer-inventory' ),
				'copyAll'  => __( 'Copy All Shortcodes', 'little-river-trailer-inventory' ),
				'noResult' => __( 'No help topics found. Try a different word.', 'little-river-trailer-inventory' ),
			)
		);
	}

	/* ===================================================================
	 * Shortcode registry (staff-friendly, accurate to the current code)
	 * =================================================================== */

	/**
	 * The documented shortcodes, grouped by category.
	 *
	 * Only shortcodes actually registered by this plugin are listed. Aliases are
	 * noted on the primary card rather than duplicated.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private function shortcode_registry(): array {
		return array(
			__( 'Inventory Displays', 'little-river-trailer-inventory' ) => array(
				array(
					'tag'     => 'little_river_inventory',
					'aliases' => array( 'trailer_inventory' ),
					'what'    => __( 'Shows the full inventory list with filters, sorting, and pages.', 'little-river-trailer-inventory' ),
					'where'   => __( 'Use this on your main Inventory page.', 'little-river-trailer-inventory' ),
					'basic'   => '[little_river_inventory]',
					'attrs'   => array(
						array( 'type', __( 'Show only one trailer type (use the type name, like dump-trailers).', 'little-river-trailer-inventory' ), '' ),
						array( 'manufacturer', __( 'Show only one brand.', 'little-river-trailer-inventory' ), '' ),
						array( 'condition', __( 'Show only New or Used.', 'little-river-trailer-inventory' ), '' ),
						array( 'availability', __( 'Show only one status (like in-stock).', 'little-river-trailer-inventory' ), '' ),
						array( 'featured', __( 'Set to yes to show only Featured trailers.', 'little-river-trailer-inventory' ), '' ),
						array( 'sale', __( 'Set to yes to show only Sale trailers.', 'little-river-trailer-inventory' ), '' ),
						array( 'filters', __( 'Show the filter sidebar. yes or no.', 'little-river-trailer-inventory' ), 'yes' ),
						array( 'columns', __( 'How many across (1 to 4).', 'little-river-trailer-inventory' ), '3' ),
						array( 'per_page', __( 'How many trailers per page.', 'little-river-trailer-inventory' ), '' ),
					),
					'example' => '[little_river_inventory type="dump-trailers" columns="3"]',
					'notes'   => __( 'Also works as [trailer_inventory]. Leave options off to show everything.', 'little-river-trailer-inventory' ),
				),
				array(
					'tag'     => 'lrti_inventory_cards',
					'aliases' => array(),
					'what'    => __( 'Shows a simple grid of trailer cards with no filter sidebar.', 'little-river-trailer-inventory' ),
					'where'   => __( 'Use this anywhere you want a small block of trailers.', 'little-river-trailer-inventory' ),
					'basic'   => '[lrti_inventory_cards]',
					'attrs'   => array(
						array( 'featured', __( 'Set to yes to show only Featured trailers.', 'little-river-trailer-inventory' ), 'no' ),
						array( 'type', __( 'Show only one trailer type.', 'little-river-trailer-inventory' ), '' ),
						array( 'manufacturer', __( 'Show only one brand.', 'little-river-trailer-inventory' ), '' ),
						array( 'condition', __( 'New or Used.', 'little-river-trailer-inventory' ), '' ),
						array( 'availability', __( 'One status (like in-stock).', 'little-river-trailer-inventory' ), '' ),
						array( 'limit', __( 'How many to show.', 'little-river-trailer-inventory' ), '4' ),
						array( 'columns', __( 'How many across (1 to 4).', 'little-river-trailer-inventory' ), '4' ),
						array( 'orderby', __( 'date, price, year, or title.', 'little-river-trailer-inventory' ), 'date' ),
						array( 'order', __( 'ASC (up) or DESC (down).', 'little-river-trailer-inventory' ), 'DESC' ),
					),
					'example' => '[lrti_inventory_cards type="utility-trailers" limit="6" columns="3"]',
					'notes'   => __( 'This grid does not slide; it is a plain set of cards.', 'little-river-trailer-inventory' ),
				),
			),
			__( 'Featured Trailers', 'little-river-trailer-inventory' ) => array(
				array(
					'tag'     => 'lrti_featured_inventory',
					'aliases' => array(),
					'what'    => __( 'Shows Featured trailers in a sliding carousel with a heading.', 'little-river-trailer-inventory' ),
					'where'   => __( 'Use this on your homepage to highlight trailers.', 'little-river-trailer-inventory' ),
					'basic'   => '[lrti_featured_inventory]',
					'attrs'   => array(
						array( 'limit', __( 'How many trailers can be in the carousel.', 'little-river-trailer-inventory' ), '12' ),
						array( 'columns', __( 'How many cards show at once (1 to 4).', 'little-river-trailer-inventory' ), '4' ),
						array( 'carousel', __( 'Slide the cards. yes or no.', 'little-river-trailer-inventory' ), 'yes' ),
						array( 'randomize', __( 'Mix the order each time the page loads. yes or no.', 'little-river-trailer-inventory' ), 'yes' ),
						array( 'interval', __( 'Time between slides, in milliseconds (4500 = 4.5 seconds).', 'little-river-trailer-inventory' ), '4500' ),
						array( 'heading', __( 'The big title above the cards.', 'little-river-trailer-inventory' ), __( 'Explore Our Latest Trailers', 'little-river-trailer-inventory' ) ),
						array( 'eyebrow', __( 'The small label above the title.', 'little-river-trailer-inventory' ), __( 'Featured Inventory', 'little-river-trailer-inventory' ) ),
						array( 'type', __( 'Limit to one trailer type.', 'little-river-trailer-inventory' ), '' ),
						array( 'manufacturer', __( 'Limit to one brand.', 'little-river-trailer-inventory' ), '' ),
					),
					'example' => '[lrti_featured_inventory columns="4" interval="4000"]',
					'notes'   => __( 'Only trailers marked Featured appear. It pauses when a visitor points at it, and shows fewer cards on small screens. If a change does not appear, clear the full SpeedyCache cache.', 'little-river-trailer-inventory' ),
				),
				array(
					'tag'     => 'little_river_featured_trailers',
					'aliases' => array( 'featured_trailers' ),
					'what'    => __( 'A simple grid of Featured trailers (no carousel).', 'little-river-trailer-inventory' ),
					'where'   => __( 'Use this if you want Featured trailers without sliding.', 'little-river-trailer-inventory' ),
					'basic'   => '[little_river_featured_trailers]',
					'attrs'   => array(
						array( 'limit', __( 'How many to show.', 'little-river-trailer-inventory' ), '4' ),
						array( 'columns', __( 'How many across (1 to 4).', 'little-river-trailer-inventory' ), '4' ),
						array( 'manufacturer', __( 'Limit to one brand.', 'little-river-trailer-inventory' ), '' ),
						array( 'type', __( 'Limit to one trailer type.', 'little-river-trailer-inventory' ), '' ),
					),
					'example' => '[little_river_featured_trailers limit="4" columns="4"]',
					'notes'   => __( 'Also works as [featured_trailers].', 'little-river-trailer-inventory' ),
				),
			),
			__( 'Trailer Categories', 'little-river-trailer-inventory' ) => array(
				array(
					'tag'     => 'twc_trailer_categories',
					'aliases' => array(),
					'what'    => __( 'Shows the homepage category cards, built from your Trailer Types.', 'little-river-trailer-inventory' ),
					'where'   => __( 'Use this on your homepage "Browse Trailers by Type" area.', 'little-river-trailer-inventory' ),
					'basic'   => '[twc_trailer_categories]',
					'attrs'   => array(
						array( 'columns', __( 'How many cards across (1 to 6).', 'little-river-trailer-inventory' ), '4' ),
						array( 'limit', __( 'How many category cards to show (0 = all).', 'little-river-trailer-inventory' ), '8' ),
						array( 'heading', __( 'Show the title and small label. true or false.', 'little-river-trailer-inventory' ), 'true' ),
					),
					'example' => '[twc_trailer_categories columns="4" limit="8"]',
					'notes'   => __( 'A category only appears if its Trailer Type has "Show on Homepage" turned on. Set a Display Order to control the order.', 'little-river-trailer-inventory' ),
				),
			),
			__( 'Forms', 'little-river-trailer-inventory' ) => array(
				array(
					'tag'     => 'lrti_contact_form',
					'aliases' => array(),
					'what'    => __( 'Shows a general contact form. Messages are saved as leads.', 'little-river-trailer-inventory' ),
					'where'   => __( 'Use this on your Home or Contact page.', 'little-river-trailer-inventory' ),
					'basic'   => '[lrti_contact_form]',
					'attrs'   => array(
						array( 'heading', __( 'The form title. Leave blank to use the Settings value.', 'little-river-trailer-inventory' ), '' ),
						array( 'button_text', __( 'The submit button text.', 'little-river-trailer-inventory' ), __( 'Send Inquiry', 'little-river-trailer-inventory' ) ),
						array( 'show_message', __( 'Show the message box. yes or no.', 'little-river-trailer-inventory' ), 'yes' ),
						array( 'show_preferred_contact', __( 'Show the preferred contact choice. yes or no.', 'little-river-trailer-inventory' ), 'yes' ),
					),
					'example' => '[lrti_contact_form heading="Send Us a Message"]',
					'notes'   => __( 'You can change the heading, description, and consent wording under Settings. Submissions appear in Leads.', 'little-river-trailer-inventory' ),
				),
				array(
					'tag'     => 'trailer_inquiry',
					'aliases' => array(),
					'what'    => __( 'Shows the inquiry form for a specific trailer.', 'little-river-trailer-inventory' ),
					'where'   => __( 'It already appears on each trailer page. You usually do not need to paste it yourself.', 'little-river-trailer-inventory' ),
					'basic'   => '[trailer_inquiry]',
					'attrs'   => array(
						array( 'trailer_id', __( 'The trailer to ask about (a number).', 'little-river-trailer-inventory' ), '' ),
						array( 'form_type', __( 'availability, information, or general.', 'little-river-trailer-inventory' ), 'availability' ),
						array( 'general', __( 'Set to yes to use it as a plain contact form.', 'little-river-trailer-inventory' ), 'no' ),
						array( 'heading', __( 'The form title.', 'little-river-trailer-inventory' ), '' ),
					),
					'example' => '[trailer_inquiry general="yes"]',
					'notes'   => __( 'On a trailer page it uses that trailer automatically. For a general form, [lrti_contact_form] is easier. Submissions appear in Leads.', 'little-river-trailer-inventory' ),
				),
			),
			__( 'Search and Filtering', 'little-river-trailer-inventory' ) => array(
				array(
					'tag'     => 'trailer_search',
					'aliases' => array( 'little_river_inventory_search' ),
					'what'    => __( 'Shows a search box that sends visitors to the inventory page.', 'little-river-trailer-inventory' ),
					'where'   => __( 'Use this in a header, sidebar, or hero area.', 'little-river-trailer-inventory' ),
					'basic'   => '[trailer_search]',
					'attrs'   => array(
						array( 'placeholder', __( 'The grey hint text inside the box.', 'little-river-trailer-inventory' ), __( 'Search trailers…', 'little-river-trailer-inventory' ) ),
						array( 'button', __( 'The button text.', 'little-river-trailer-inventory' ), __( 'Search', 'little-river-trailer-inventory' ) ),
					),
					'example' => '[trailer_search placeholder="Find a trailer..."]',
					'notes'   => __( 'Also works as [little_river_inventory_search].', 'little-river-trailer-inventory' ),
				),
				array(
					'tag'     => 'trailer_filters',
					'aliases' => array(),
					'what'    => __( 'Shows just the filter controls on their own.', 'little-river-trailer-inventory' ),
					'where'   => __( 'Advanced use only, when you build a custom layout.', 'little-river-trailer-inventory' ),
					'basic'   => '[trailer_filters]',
					'attrs'   => array(
						array( 'target', __( 'Where the filters send the visitor.', 'little-river-trailer-inventory' ), 'archive' ),
					),
					'example' => '[trailer_filters]',
					'notes'   => __( 'Most dealerships do not need this; the inventory shortcode already includes filters.', 'little-river-trailer-inventory' ),
				),
			),
		);
	}

	/**
	 * Flat list of every shortcode tag documented in the registry (incl aliases).
	 *
	 * @return array<int, string>
	 */
	private function documented_tags(): array {
		$tags = array();
		foreach ( $this->shortcode_registry() as $cards ) {
			foreach ( $cards as $card ) {
				$tags[] = (string) $card['tag'];
				foreach ( (array) ( $card['aliases'] ?? array() ) as $alias ) {
					$tags[] = (string) $alias;
				}
			}
		}
		return array_values( array_unique( $tags ) );
	}

	/**
	 * Every shortcode tag this plugin actually registers.
	 *
	 * @return array<int, string>
	 */
	private function registered_tags(): array {
		return array(
			'trailer_inventory',
			'featured_trailers',
			'trailer_search',
			'trailer_filters',
			'little_river_inventory',
			'little_river_featured_trailers',
			'little_river_inventory_search',
			'lrti_featured_inventory',
			'lrti_inventory_cards',
			'twc_trailer_categories',
			'trailer_inquiry',
			'lrti_contact_form',
		);
	}

	/**
	 * Developer-only notice if a registered shortcode is missing from the guide.
	 *
	 * Shown only to administrators and only when debugging, so normal staff never
	 * see it. Helps future shortcodes from being forgotten in the docs.
	 *
	 * @return void
	 */
	public function audit_notice(): void {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'lrti-user-guide' ) ) {
			return;
		}
		$missing = array_diff( $this->registered_tags(), $this->documented_tags() );
		if ( empty( $missing ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'User Guide (developer notice):', 'little-river-trailer-inventory' ) . '</strong> ';
		echo esc_html__( 'These registered shortcodes are not documented yet:', 'little-river-trailer-inventory' ) . ' ';
		echo esc_html( implode( ', ', $missing ) ) . '</p></div>';
	}

	/* ===================================================================
	 * Rendering
	 * =================================================================== */

	/**
	 * Render the User Guide page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$view = LRTI_PLUGIN_DIR . 'admin/views/user-guide.php';
		if ( is_file( $view ) ) {
			$guide = $this; // Exposed to the view.
			include $view;
		}
	}

	/**
	 * Public accessors used by the view.
	 */

	/**
	 * @return string Documentation version.
	 */
	public function doc_version(): string {
		return self::DOC_VERSION;
	}

	/**
	 * @return array<string, array<int, array<string, mixed>>> Registry for the view.
	 */
	public function registry(): array {
		return $this->shortcode_registry();
	}

	/**
	 * Render one accordion block.
	 *
	 * @param string $id    Unique id.
	 * @param string $title Section title.
	 * @param string $body  Inner HTML (already escaped by the caller).
	 * @param bool   $open  Whether it starts open.
	 * @return string
	 */
	public function accordion( string $id, string $title, string $body, bool $open = false ): string {
		$panel = 'lrti-acc-' . sanitize_html_class( $id );
		ob_start();
		?>
		<div class="lrti-guide-acc<?php echo $open ? ' is-open' : ''; ?>" data-guide-section data-keywords="<?php echo esc_attr( wp_strip_all_tags( $title ) ); ?>">
			<h3 class="lrti-guide-acc-head">
				<button type="button" class="lrti-guide-acc-btn" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $panel ); ?>">
					<span class="lrti-guide-acc-title"><?php echo esc_html( $title ); ?></span>
					<span class="lrti-guide-acc-icon dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				</button>
			</h3>
			<div class="lrti-guide-acc-panel" id="<?php echo esc_attr( $panel ); ?>" role="region" aria-label="<?php echo esc_attr( $title ); ?>"<?php echo $open ? '' : ' hidden'; ?>>
				<div class="lrti-guide-acc-inner"><?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller escapes. ?></div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render a small HTML/CSS flow diagram from a list of steps.
	 *
	 * @param array<int, string> $steps Ordered step labels.
	 * @return string
	 */
	public function flow( array $steps ): string {
		$out = '<div class="lrti-guide-flow">';
		$last = count( $steps ) - 1;
		foreach ( array_values( $steps ) as $i => $step ) {
			$out .= '<span class="lrti-guide-flow-step">' . esc_html( $step ) . '</span>';
			if ( $i !== $last ) {
				$out .= '<span class="lrti-guide-flow-arrow dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>';
			}
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Render a shortcode reference card.
	 *
	 * @param array<string, mixed> $card Card data from the registry.
	 * @return string
	 */
	public function shortcode_card( array $card ): string {
		$tag     = (string) $card['tag'];
		$aliases = (array) ( $card['aliases'] ?? array() );
		$attrs   = (array) ( $card['attrs'] ?? array() );

		ob_start();
		?>
		<div class="lrti-guide-sc" data-guide-section data-keywords="<?php echo esc_attr( $tag . ' ' . implode( ' ', $aliases ) . ' ' . wp_strip_all_tags( (string) $card['what'] ) ); ?>">
			<div class="lrti-guide-sc-head">
				<code class="lrti-guide-sc-tag">[<?php echo esc_html( $tag ); ?>]</code>
				<button type="button" class="lrti-guide-copy" data-copy="[<?php echo esc_attr( $tag ); ?>]">
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					<span class="lrti-guide-copy-label"><?php esc_html_e( 'Copy', 'little-river-trailer-inventory' ); ?></span>
				</button>
			</div>
			<p class="lrti-guide-sc-what"><?php echo esc_html( (string) $card['what'] ); ?></p>
			<p class="lrti-guide-sc-where"><strong><?php esc_html_e( 'Where to use it:', 'little-river-trailer-inventory' ); ?></strong> <?php echo esc_html( (string) $card['where'] ); ?></p>

			<div class="lrti-guide-sc-example">
				<code><?php echo esc_html( (string) $card['basic'] ); ?></code>
				<button type="button" class="lrti-guide-copy lrti-guide-copy--sm" data-copy="<?php echo esc_attr( (string) $card['basic'] ); ?>">
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					<span class="lrti-guide-copy-label"><?php esc_html_e( 'Copy', 'little-river-trailer-inventory' ); ?></span>
				</button>
			</div>

			<?php if ( ! empty( $attrs ) ) : ?>
				<p class="lrti-guide-sc-subhead"><?php esc_html_e( 'Options you can add', 'little-river-trailer-inventory' ); ?></p>
				<table class="lrti-guide-sc-attrs">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Option', 'little-river-trailer-inventory' ); ?></th>
							<th><?php esc_html_e( 'What it does', 'little-river-trailer-inventory' ); ?></th>
							<th><?php esc_html_e( 'Default', 'little-river-trailer-inventory' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $attrs as $a ) : ?>
							<tr>
								<td><code><?php echo esc_html( (string) $a[0] ); ?></code></td>
								<td><?php echo esc_html( (string) $a[1] ); ?></td>
								<td><?php echo '' === (string) $a[2] ? '<span class="lrti-guide-muted">—</span>' : '<code>' . esc_html( (string) $a[2] ) . '</code>'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="lrti-guide-sc-example">
					<code><?php echo esc_html( (string) $card['example'] ); ?></code>
					<button type="button" class="lrti-guide-copy lrti-guide-copy--sm" data-copy="<?php echo esc_attr( (string) $card['example'] ); ?>">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
						<span class="lrti-guide-copy-label"><?php esc_html_e( 'Copy', 'little-river-trailer-inventory' ); ?></span>
					</button>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $card['notes'] ) ) : ?>
				<p class="lrti-guide-sc-note"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span> <?php echo esc_html( (string) $card['notes'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
