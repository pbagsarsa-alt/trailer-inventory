<?php
/**
 * Admin menu handler.
 *
 * Builds the "Trailer Inventory" menu in the WordPress dashboard. In Phase 2 we
 * add submenu links for managing trailers and their taxonomies, and we keep the
 * "Trailer Inventory" menu highlighted while those core screens are open.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 */
final class Admin {

	/**
	 * Shared Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Taxonomy keys that belong to our menu (for highlighting).
	 *
	 * @var string[]
	 */
	private array $our_taxonomies = array(
		'trailer_type',
		'trailer_manufacturer',
		'trailer_condition',
		'trailer_availability',
		'trailer_feature',
	);

	/**
	 * Constructor.
	 *
	 * @param Settings $settings The shared settings object.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Attach admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Keep our top-level menu highlighted on trailer/taxonomy screens.
		add_filter( 'parent_file', array( $this, 'highlight_parent' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_submenu' ) );
		add_filter( 'update_footer', array( $this, 'footer_version' ), 15 );
	}

	/**
	 * Show the plugin version in the admin footer on plugin screens.
	 *
	 * @param string $text Existing right-side footer text.
	 * @return string
	 */
	public function footer_version( $text ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return $text;
		}
		$is_plugin = ( isset( $screen->post_type ) && in_array( $screen->post_type, array( PostTypes::POST_TYPE, Leads::POST_TYPE ), true ) )
			|| ( isset( $screen->id ) && false !== strpos( (string) $screen->id, 'lrti-' ) );
		if ( ! $is_plugin ) {
			return $text;
		}
		return 'TWC Trailer Inventory ' . esc_html( LRTI_VERSION );
	}

	/**
	 * Register the top-level menu and its submenu items.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$cap       = 'manage_options';
		$edit_cap  = 'edit_posts';
		$post_type = PostTypes::POST_TYPE;

		add_menu_page(
			__( 'Trailer Inventory', 'little-river-trailer-inventory' ),
			__( 'Trailer Inventory', 'little-river-trailer-inventory' ),
			$cap,
			'lrti-overview',
			array( $this, 'render_overview_page' ),
			'dashicons-car',
			26
		);

		add_submenu_page(
			'lrti-overview',
			__( 'Overview', 'little-river-trailer-inventory' ),
			__( 'Overview', 'little-river-trailer-inventory' ),
			$cap,
			'lrti-overview',
			array( $this, 'render_overview_page' )
		);

		// Trailer management links (point to the core post/taxonomy screens).
		add_submenu_page(
			'lrti-overview',
			__( 'All Trailers', 'little-river-trailer-inventory' ),
			__( 'All Trailers', 'little-river-trailer-inventory' ),
			$edit_cap,
			'edit.php?post_type=' . $post_type
		);

		add_submenu_page(
			'lrti-overview',
			__( 'Add New Trailer', 'little-river-trailer-inventory' ),
			__( 'Add New Trailer', 'little-river-trailer-inventory' ),
			$edit_cap,
			'post-new.php?post_type=' . $post_type
		);

		add_submenu_page(
			'lrti-overview',
			__( 'Trailer Types', 'little-river-trailer-inventory' ),
			__( 'Trailer Types', 'little-river-trailer-inventory' ),
			'manage_categories',
			'edit-tags.php?taxonomy=trailer_type&post_type=' . $post_type
		);

		add_submenu_page(
			'lrti-overview',
			__( 'Manufacturers', 'little-river-trailer-inventory' ),
			__( 'Manufacturers', 'little-river-trailer-inventory' ),
			'manage_categories',
			'edit-tags.php?taxonomy=trailer_manufacturer&post_type=' . $post_type
		);

		add_submenu_page(
			'lrti-overview',
			__( 'Features', 'little-river-trailer-inventory' ),
			__( 'Features', 'little-river-trailer-inventory' ),
			'manage_categories',
			'edit-tags.php?taxonomy=trailer_feature&post_type=' . $post_type
		);

		// Leads: the lead post type uses show_in_menu => false, so this is the
		// single, intentional Leads submenu under Trailer Inventory.
		$leads_cap = 'edit_lrti_leads';
		if ( ! current_user_can( $leads_cap ) ) {
			$leads_cap = $cap; // Fallback for installs before lead caps were granted.
		}
		add_submenu_page(
			'lrti-overview',
			__( 'Leads', 'little-river-trailer-inventory' ),
			__( 'Leads', 'little-river-trailer-inventory' ),
			$leads_cap,
			'edit.php?post_type=' . Leads::POST_TYPE
		);

		// Settings stays last for now (Import/Export arrives later).
		add_submenu_page(
			'lrti-overview',
			__( 'Settings', 'little-river-trailer-inventory' ),
			__( 'Settings', 'little-river-trailer-inventory' ),
			$cap,
			'lrti-settings',
			array( $this->settings, 'render_settings_page' )
		);
	}

	/**
	 * Force the "Trailer Inventory" menu to be treated as the current parent on
	 * trailer and trailer-taxonomy screens.
	 *
	 * @param string $parent_file The current parent file.
	 * @return string
	 */
	public function highlight_parent( string $parent_file ): string {
		$screen = get_current_screen();
		if ( $screen && PostTypes::POST_TYPE === $screen->post_type ) {
			return 'lrti-overview';
		}

		return $parent_file;
	}

	/**
	 * Highlight the correct submenu item on trailer and taxonomy screens.
	 *
	 * @param string|null $submenu_file The current submenu file.
	 * @return string|null
	 */
	public function highlight_submenu( $submenu_file ) {
		$screen = get_current_screen();
		if ( ! $screen || PostTypes::POST_TYPE !== $screen->post_type ) {
			return $submenu_file;
		}

		// Taxonomy term screens.
		if ( ! empty( $screen->taxonomy ) && in_array( $screen->taxonomy, $this->our_taxonomies, true ) ) {
			return 'edit-tags.php?taxonomy=' . $screen->taxonomy . '&post_type=' . PostTypes::POST_TYPE;
		}

		// The trailers list screen.
		if ( 'edit' === $screen->base ) {
			return 'edit.php?post_type=' . PostTypes::POST_TYPE;
		}

		return $submenu_file;
	}

	/**
	 * Load admin CSS on this plugin's own admin pages.
	 *
	 * @param string $hook_suffix The current admin page's hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$on_plugin_page = ( false !== strpos( $hook_suffix, 'lrti-' ) );

		$on_cpt_screen = false;
		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && isset( $screen->post_type ) && in_array( $screen->post_type, array( PostTypes::POST_TYPE, Leads::POST_TYPE ), true ) ) {
				$on_cpt_screen = true;
			}
		}

		if ( ! $on_plugin_page && ! $on_cpt_screen ) {
			return;
		}

		wp_enqueue_style(
			'lrti-admin',
			LRTI_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			LRTI_VERSION
		);
	}

	/**
	 * Gather lightweight inventory counts for the Overview dashboard.
	 *
	 * Uses efficient core counting where possible. Availability counts come from
	 * maintained term counts; the featured count is a single ID-only query.
	 *
	 * @return array<string, int>
	 */
	private function get_dashboard_stats(): array {
		$counts = wp_count_posts( PostTypes::POST_TYPE );

		$published = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$drafts    = isset( $counts->draft ) ? (int) $counts->draft : 0;
		$pending   = isset( $counts->pending ) ? (int) $counts->pending : 0;
		$private   = isset( $counts->private ) ? (int) $counts->private : 0;
		$future    = isset( $counts->future ) ? (int) $counts->future : 0;

		$total = $published + $drafts + $pending + $private + $future;

		$avail = static function ( string $slug ): int {
			$term = get_term_by( 'slug', $slug, 'trailer_availability' );
			return ( $term && ! is_wp_error( $term ) ) ? (int) $term->count : 0;
		};

		$featured_query = new \WP_Query(
			array(
				'post_type'      => PostTypes::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_lrti_featured',
						'value' => '1',
					),
				),
			)
		);

		return array(
			'total'        => $total,
			'published'    => $published,
			'drafts'       => $drafts,
			'in_stock'     => $avail( 'in-stock' ),
			'sale_pending' => $avail( 'sale-pending' ),
			'sold'         => $avail( 'sold' ),
			'featured'     => (int) $featured_query->found_posts,
		);
	}

	/**
	 * Render the "Recent Activity" panel: latest CRM actions across leads.
	 *
	 * @param Leads $leads The leads model.
	 * @return void
	 */
	private function render_recent_activity( Leads $leads ): void {
		if ( ! current_user_can( 'edit_lrti_leads' ) ) {
			return;
		}
		$entries = $leads->recent_activity( 8 );
		if ( empty( $entries ) ) {
			return;
		}
		?>
		<div class="lrti-card lrti-recent-activity">
			<h2><?php esc_html_e( 'Recent Activity', 'little-river-trailer-inventory' ); ?></h2>
			<ul class="lrti-activity-feed">
				<?php
				foreach ( $entries as $e ) :
					$when = $e['time'] > 0 ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $e['time'] ) : '';
					$user = $e['user'] > 0 ? get_userdata( $e['user'] ) : false;
					$edit = (string) get_edit_post_link( $e['lead_id'] );
					?>
					<li>
						<span class="lrti-activity-when"><?php echo esc_html( $when ); ?></span>
						<span class="lrti-activity-action"><?php echo esc_html( $e['action'] ); ?></span>
						<?php if ( $user ) : ?>
							<span class="lrti-activity-user"><?php echo esc_html( sprintf( /* translators: %s: user name */ __( 'by %s', 'little-river-trailer-inventory' ), $user->display_name ) ); ?></span>
						<?php endif; ?>
						<?php if ( $edit && '' !== $e['lead_title'] ) : ?>
							— <a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $e['lead_title'] ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render the "Recent Inquiries" panel: the five newest leads.
	 *
	 * @param Leads  $leads     The leads model.
	 * @param string $leads_url URL of the Leads list screen.
	 * @return void
	 */
	private function render_recent_inquiries( Leads $leads, string $leads_url ): void {
		if ( ! current_user_can( 'edit_lrti_leads' ) ) {
			return;
		}

		$recent = get_posts(
			array(
				'post_type'              => Leads::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => 5,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$k        = Leads::meta_keys();
		$statuses = Leads::statuses();
		$ftypes   = array(
			'availability'      => __( 'Check Availability', 'little-river-trailer-inventory' ),
			'information'       => __( 'Request Information', 'little-river-trailer-inventory' ),
			'similar_inventory' => __( 'Request Similar Trailers', 'little-river-trailer-inventory' ),
		);
		$date_fmt = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		?>
		<div class="lrti-card lrti-recent-inquiries">
			<div class="lrti-recent-head">
				<h2><?php esc_html_e( 'Recent Inquiries', 'little-river-trailer-inventory' ); ?></h2>
				<a class="button" href="<?php echo esc_url( $leads_url ); ?>"><?php esc_html_e( 'View All Leads', 'little-river-trailer-inventory' ); ?></a>
			</div>

			<?php if ( empty( $recent ) ) : ?>
				<div class="lrti-recent-empty">
					<p><strong><?php esc_html_e( 'No inquiries have been received yet.', 'little-river-trailer-inventory' ); ?></strong></p>
					<p class="description"><?php esc_html_e( 'New trailer inquiry submissions will appear here and in the Leads section.', 'little-river-trailer-inventory' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat striped lrti-recent-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Customer', 'little-river-trailer-inventory' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Trailer', 'little-river-trailer-inventory' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Stock #', 'little-river-trailer-inventory' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Form Type', 'little-river-trailer-inventory' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'little-river-trailer-inventory' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Assigned To', 'little-river-trailer-inventory' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Follow-Up', 'little-river-trailer-inventory' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Submitted', 'little-river-trailer-inventory' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $recent as $lead ) :
							$id        = (int) $lead->ID;
							$edit_url  = (string) get_edit_post_link( $id );
							$is_unread = ( '1' !== (string) get_post_meta( $id, Leads::READ_META, true ) );

							$name  = (string) get_post_meta( $id, $k['name'], true );
							$email = (string) get_post_meta( $id, $k['email'], true );
							$who   = '' !== $name ? $name : ( '' !== $email ? $email : __( 'Unknown Customer', 'little-river-trailer-inventory' ) );

							$tid     = (int) get_post_meta( $id, $k['trailer_id'], true );
							$ttitle  = (string) get_post_meta( $id, $k['trailer_title'], true );
							$stock   = (string) get_post_meta( $id, $k['stock_number'], true );
							$ftype   = (string) get_post_meta( $id, $k['form_type'], true );
							$status  = $leads->get_status( $id );
							$uid     = (int) get_post_meta( $id, $k['assigned_user'], true );
							$user    = $uid ? get_userdata( $uid ) : false;
							$next    = (string) get_post_meta( $id, $k['next_followup'], true );
							$overdue = $leads->is_overdue( $id );

							$row_class = $is_unread ? ' class="lrti-recent-unread"' : '';
							?>
							<tr<?php echo $row_class; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>>
								<td class="lrti-recent-customer">
									<?php if ( $is_unread ) : ?>
										<span class="lrti-recent-dot" aria-hidden="true"></span>
										<span class="screen-reader-text"><?php esc_html_e( 'Unread', 'little-river-trailer-inventory' ); ?></span>
									<?php endif; ?>
									<?php if ( $edit_url ) : ?>
										<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $who ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $who ); ?>
									<?php endif; ?>
									<?php if ( '' !== $email ) : ?>
										<br /><span class="description"><?php echo esc_html( $email ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php
									if ( $tid && get_post_status( $tid ) && current_user_can( 'edit_post', $tid ) ) {
										echo '<a href="' . esc_url( (string) get_edit_post_link( $tid ) ) . '">' . esc_html( '' !== $ttitle ? $ttitle : get_the_title( $tid ) ) . '</a>';
									} elseif ( $tid && get_post_status( $tid ) ) {
										echo esc_html( '' !== $ttitle ? $ttitle : get_the_title( $tid ) );
									} elseif ( '' !== $ttitle ) {
										echo esc_html( $ttitle ) . ' <span class="description">(' . esc_html__( 'Deleted Trailer', 'little-river-trailer-inventory' ) . ')</span>';
									} else {
										echo '<span class="description">' . esc_html__( 'Deleted Trailer', 'little-river-trailer-inventory' ) . '</span>';
									}
									?>
								</td>
								<td><?php echo '' !== $stock ? esc_html( $stock ) : '&mdash;'; ?></td>
								<td><?php echo esc_html( $ftypes[ $ftype ] ?? ( '' !== $ftype ? $ftype : '—' ) ); ?></td>
								<td><span class="lrti-status-badge lrti-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $statuses[ $status ] ?? $status ); ?></span></td>
								<td><?php echo $user ? esc_html( $user->display_name ) : '<span class="description">' . esc_html__( 'Unassigned', 'little-river-trailer-inventory' ) . '</span>'; ?></td>
								<td>
									<?php
									if ( '' === $next ) {
										echo '&mdash;';
									} else {
										$ts    = strtotime( $next . ' 12:00:00' );
										$human = $ts ? wp_date( get_option( 'date_format' ), $ts ) : $next;
										if ( $overdue ) {
											echo '<span class="lrti-recent-overdue">' . esc_html( $human ) . ' (' . esc_html__( 'Overdue', 'little-river-trailer-inventory' ) . ')</span>';
										} else {
											echo esc_html( $human );
										}
									}
									?>
								</td>
								<td><?php echo esc_html( get_post_time( $date_fmt, false, $id, true ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="lrti-recent-foot">
					<a class="button" href="<?php echo esc_url( $leads_url ); ?>"><?php esc_html_e( 'View All Leads', 'little-river-trailer-inventory' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Overview landing page.
	 *
	 * @return void
	 */
	public function render_overview_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'little-river-trailer-inventory' ) );
		}

		$add_url  = admin_url( 'post-new.php?post_type=' . PostTypes::POST_TYPE );
		$list_url = admin_url( 'edit.php?post_type=' . PostTypes::POST_TYPE );
		?>
		<div class="wrap lrti-admin-wrap">
			<h1><?php echo esc_html__( 'TWC Trailer Inventory for Little River Equipment Sales LLC', 'little-river-trailer-inventory' ); ?></h1>

			<?php $lrti_stats = $this->get_dashboard_stats(); ?>
			<div class="lrti-stats">
				<?php
				$lrti_cards = array(
					'total'        => __( 'Total Trailers', 'little-river-trailer-inventory' ),
					'published'    => __( 'Published', 'little-river-trailer-inventory' ),
					'drafts'       => __( 'Drafts', 'little-river-trailer-inventory' ),
					'in_stock'     => __( 'In Stock', 'little-river-trailer-inventory' ),
					'sale_pending' => __( 'Sale Pending', 'little-river-trailer-inventory' ),
					'sold'         => __( 'Sold', 'little-river-trailer-inventory' ),
					'featured'     => __( 'Featured', 'little-river-trailer-inventory' ),
				);
				foreach ( $lrti_cards as $lrti_key => $lrti_label ) :
					?>
					<div class="lrti-stat-card">
						<span class="lrti-stat-num"><?php echo esc_html( number_format_i18n( (int) $lrti_stats[ $lrti_key ] ) ); ?></span>
						<span class="lrti-stat-label"><?php echo esc_html( $lrti_label ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<?php
			// Lead statistics (Sprint 5.0). Only shown to users who can manage leads.
			$lrti_leads = Plugin::instance()->leads();
			if ( $lrti_leads && current_user_can( 'edit_lrti_leads' ) ) :
				$lrti_lead_counts = $lrti_leads->counts_by_status();
				$lrti_this_month  = $lrti_leads->count_this_month();
				$lrti_unread      = $lrti_leads->unread_count();
				$lrti_fu_due      = $lrti_leads->followups_due_count();
				$lrti_today       = $lrti_leads->count_today();
				$lrti_sold_month  = $lrti_leads->count_status_changed_this_month( 'won' );
				$lrti_lost_month  = $lrti_leads->count_status_changed_this_month( 'lost' );
				$lrti_leads_url   = admin_url( 'edit.php?post_type=' . Leads::POST_TYPE );
				?>
				<h2 class="lrti-subheading"><?php esc_html_e( 'Leads', 'little-river-trailer-inventory' ); ?></h2>
				<div class="lrti-stats">
					<div class="lrti-stat-card">
						<span class="lrti-stat-num"><?php echo esc_html( number_format_i18n( (int) $lrti_today ) ); ?></span>
						<span class="lrti-stat-label"><?php esc_html_e( "Today's Leads", 'little-river-trailer-inventory' ); ?></span>
					</div>
					<div class="lrti-stat-card">
						<span class="lrti-stat-num"><?php echo esc_html( number_format_i18n( (int) $lrti_unread ) ); ?></span>
						<span class="lrti-stat-label"><?php esc_html_e( 'Unread Leads', 'little-river-trailer-inventory' ); ?></span>
					</div>
					<?php
					$lrti_lead_cards = array(
						'new'       => __( 'New Leads', 'little-river-trailer-inventory' ),
						'contacted' => __( 'Contacted', 'little-river-trailer-inventory' ),
						'qualified' => __( 'Qualified', 'little-river-trailer-inventory' ),
						'won'       => __( 'Sold', 'little-river-trailer-inventory' ),
						'lost'      => __( 'Lost', 'little-river-trailer-inventory' ),
					);
					foreach ( $lrti_lead_cards as $lrti_lk => $lrti_ll ) :
						?>
						<div class="lrti-stat-card">
							<span class="lrti-stat-num"><?php echo esc_html( number_format_i18n( (int) ( $lrti_lead_counts[ $lrti_lk ] ?? 0 ) ) ); ?></span>
							<span class="lrti-stat-label"><?php echo esc_html( $lrti_ll ); ?></span>
						</div>
					<?php endforeach; ?>
					<div class="lrti-stat-card">
						<span class="lrti-stat-num"><?php echo esc_html( number_format_i18n( (int) $lrti_fu_due ) ); ?></span>
						<span class="lrti-stat-label"><?php esc_html_e( 'Follow-Ups Due', 'little-river-trailer-inventory' ); ?></span>
					</div>
					<div class="lrti-stat-card">
						<span class="lrti-stat-num"><?php echo esc_html( number_format_i18n( (int) $lrti_sold_month ) ); ?></span>
						<span class="lrti-stat-label"><?php esc_html_e( 'Sold This Month', 'little-river-trailer-inventory' ); ?></span>
					</div>
					<div class="lrti-stat-card">
						<span class="lrti-stat-num"><?php echo esc_html( number_format_i18n( (int) $lrti_lost_month ) ); ?></span>
						<span class="lrti-stat-label"><?php esc_html_e( 'Lost This Month', 'little-river-trailer-inventory' ); ?></span>
					</div>
					<div class="lrti-stat-card">
						<span class="lrti-stat-num"><?php echo esc_html( number_format_i18n( (int) $lrti_this_month ) ); ?></span>
						<span class="lrti-stat-label"><?php esc_html_e( 'Leads This Month', 'little-river-trailer-inventory' ); ?></span>
					</div>
				</div>
				<?php
				$lrti_top = $lrti_leads->top_assignee();
				if ( $lrti_top ) :
					$lrti_top_user = get_userdata( $lrti_top['user_id'] );
					if ( $lrti_top_user ) :
						?>
						<p class="lrti-top-salesperson">
							<?php
							printf(
								/* translators: 1: salesperson name, 2: lead count */
								esc_html__( 'Top assigned salesperson: %1$s (%2$s leads)', 'little-river-trailer-inventory' ),
								'<strong>' . esc_html( $lrti_top_user->display_name ) . '</strong>',
								esc_html( number_format_i18n( (int) $lrti_top['total'] ) )
							);
							?>
						</p>
						<?php
					endif;
				endif;
				?>
				<p><a class="button" href="<?php echo esc_url( $lrti_leads_url ); ?>"><?php esc_html_e( 'View All Leads', 'little-river-trailer-inventory' ); ?></a></p>

				<?php $this->render_recent_inquiries( $lrti_leads, $lrti_leads_url ); ?>
				<?php $this->render_recent_activity( $lrti_leads ); ?>
			<?php endif; ?>

			<div class="lrti-card">
				<h2><?php echo esc_html__( 'Manage Trailers', 'little-river-trailer-inventory' ); ?></h2>
				<p>
					<?php
					echo esc_html__(
						'You can now add trailers to your inventory. Each trailer has organized fields for pricing, dimensions, and specifications, plus a main image and an extra photo gallery.',
						'little-river-trailer-inventory'
					);
					?>
				</p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $add_url ); ?>"><?php echo esc_html__( 'Add New Trailer', 'little-river-trailer-inventory' ); ?></a>
					<a class="button" href="<?php echo esc_url( $list_url ); ?>"><?php echo esc_html__( 'View All Trailers', 'little-river-trailer-inventory' ); ?></a>
				</p>
			</div>

			<div class="lrti-card">
				<h2><?php echo esc_html__( 'Settings', 'little-river-trailer-inventory' ); ?></h2>
				<p>
					<?php
					echo esc_html__(
						'Review your dealership details and notification email on the Settings page.',
						'little-river-trailer-inventory'
					);
					?>
				</p>
				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=lrti-settings' ) ); ?>"><?php echo esc_html__( 'Open Settings', 'little-river-trailer-inventory' ); ?></a>
				</p>
			</div>

			<div class="lrti-card">
				<p class="description">
					<?php
					printf(
						/* translators: %s: plugin version number */
						esc_html__( 'Installed version: %s', 'little-river-trailer-inventory' ),
						esc_html( LRTI_VERSION )
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}
}
