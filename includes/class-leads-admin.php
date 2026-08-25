<?php
/**
 * Leads admin experience (Sprint 5.0).
 *
 * Customizes the native lrti_lead list table (columns, filters, search, row and
 * bulk actions), builds the organized lead edit screen (metaboxes), records the
 * activity log, powers quick actions and resend, and adds the New-lead menu
 * bubble. All actions are nonce- and capability-checked.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LeadsAdmin
 */
final class LeadsAdmin {

	/**
	 * Leads model.
	 *
	 * @var Leads
	 */
	private Leads $leads;

	/**
	 * Notifications.
	 *
	 * @var Notifications
	 */
	private Notifications $notifications;

	/**
	 * Constructor.
	 *
	 * @param Leads         $leads         Leads model.
	 * @param Notifications $notifications Notifications.
	 */
	public function __construct( Leads $leads, Notifications $notifications ) {
		$this->leads         = $leads;
		$this->notifications = $notifications;
	}

	/**
	 * The capability required to manage leads.
	 *
	 * @return string
	 */
	private function cap(): string {
		return 'edit_lrti_leads';
	}

	/**
	 * Attach hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		$pt = Leads::POST_TYPE;

		add_filter( "manage_{$pt}_posts_columns", array( $this, 'columns' ) );
		add_action( "manage_{$pt}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		add_filter( "manage_edit-{$pt}_sortable_columns", array( $this, 'sortable_columns' ) );

		add_action( 'restrict_manage_posts', array( $this, 'render_filters' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_filters_and_search' ) );
		add_filter( 'posts_search', array( $this, 'extend_search' ), 10, 2 );

		add_filter( "bulk_actions-edit-{$pt}", array( $this, 'bulk_actions' ) );
		add_filter( "handle_bulk_actions-edit-{$pt}", array( $this, 'handle_bulk' ), 10, 3 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 9999, 2 );
		add_action( 'admin_post_lrti_lead_action', array( $this, 'handle_row_action' ) );

		add_action( 'add_meta_boxes_' . $pt, array( $this, 'add_metaboxes' ) );
		add_action( 'edit_form_after_title', array( $this, 'render_lead_header' ) );
		add_action( 'save_post_' . $pt, array( $this, 'save_metaboxes' ), 10, 2 );

		add_action( 'admin_menu', array( $this, 'menu_bubble' ), 999 );
		add_action( 'admin_notices', array( $this, 'action_notices' ) );
		add_filter( 'post_class', array( $this, 'unread_row_class' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_copy_js' ) );
	}

	/**
	 * Tiny vanilla-JS copy-to-clipboard for the Quick Actions box.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_copy_js( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || Leads::POST_TYPE !== $screen->post_type ) {
			return;
		}

		// Leads list screen: accessible "More" action menus.
		if ( 'edit.php' === $hook ) {
			wp_enqueue_script(
				'lrti-leads-list',
				LRTI_PLUGIN_URL . 'admin/js/leads-list.js',
				array(),
				LRTI_VERSION,
				true
			);
			return;
		}

		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$js = <<<'JS'
document.addEventListener( 'click', function ( e ) {
	var b = e.target.closest ? e.target.closest( '.lrti-copy' ) : null;
	if ( ! b ) { return; }
	e.preventDefault();
	var text = b.getAttribute( 'data-copy' ) || '';
	var done = function () {
		var fb = document.querySelector( '.lrti-copy-feedback' );
		if ( fb ) { fb.textContent = 'Copied'; setTimeout( function () { fb.textContent = ''; }, 1500 ); }
	};
	if ( navigator.clipboard && navigator.clipboard.writeText ) {
		navigator.clipboard.writeText( text ).then( done, done );
	} else {
		var t = document.createElement( 'textarea' );
		t.value = text; document.body.appendChild( t ); t.select();
		try { document.execCommand( 'copy' ); } catch ( err ) {}
		document.body.removeChild( t ); done();
	}
} );
JS;
		wp_register_script( 'lrti-lead-copy', '', array(), LRTI_VERSION, true );
		wp_enqueue_script( 'lrti-lead-copy' );
		wp_add_inline_script( 'lrti-lead-copy', $js );
	}

	/**
	 * Emphasize unread leads in the list table via a row class.
	 *
	 * @param string[] $classes Row classes.
	 * @param string[] $class   Extra classes (unused).
	 * @param int      $post_id Post ID.
	 * @return string[]
	 */
	public function unread_row_class( array $classes, $class, $post_id ): array {
		if ( get_post_type( $post_id ) === Leads::POST_TYPE ) {
			if ( '1' !== (string) get_post_meta( (int) $post_id, Leads::READ_META, true ) ) {
				$classes[] = 'lrti-lead-unread';
			}
			if ( $this->leads->is_overdue( (int) $post_id ) ) {
				$classes[] = 'lrti-lead-overdue';
			}
		}
		return $classes;
	}

	/* --------------------------------------------------------------------- *
	 * List table
	 * --------------------------------------------------------------------- */

	/**
	 * Define list columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		return array(
			'cb'            => $columns['cb'] ?? '<input type="checkbox" />',
			'title'         => __( 'Lead', 'little-river-trailer-inventory' ),
			'lrti_customer' => __( 'Customer', 'little-river-trailer-inventory' ),
			'lrti_trailer'  => __( 'Trailer', 'little-river-trailer-inventory' ),
			'lrti_stock'    => __( 'Stock #', 'little-river-trailer-inventory' ),
			'lrti_formtype' => __( 'Form Type', 'little-river-trailer-inventory' ),
			'lrti_status'   => __( 'Status', 'little-river-trailer-inventory' ),
			'lrti_read'     => __( 'Read', 'little-river-trailer-inventory' ),
			'lrti_assigned' => __( 'Assigned To', 'little-river-trailer-inventory' ),
			'lrti_followup' => __( 'Follow-Up', 'little-river-trailer-inventory' ),
			'lrti_age'      => __( 'Age', 'little-river-trailer-inventory' ),
			'lrti_email'    => __( 'Email', 'little-river-trailer-inventory' ),
			'lrti_phone'    => __( 'Phone', 'little-river-trailer-inventory' ),
			'lrti_notify'   => __( 'Notification', 'little-river-trailer-inventory' ),
			'date'          => __( 'Submitted', 'little-river-trailer-inventory' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public function sortable_columns( array $columns ): array {
		$columns['lrti_status'] = 'lrti_status';
		return $columns;
	}

	/**
	 * Render a custom column cell.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Lead ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		$k = Leads::meta_keys();
		switch ( $column ) {
			case 'lrti_customer':
				$cname  = (string) get_post_meta( $post_id, $k['name'], true );
				$cemail = (string) get_post_meta( $post_id, $k['email'], true );
				echo '<span class="lrti-cell-primary"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span>' . esc_html( $cname ) . '</span>';
				if ( '' !== $cemail ) {
					echo '<a class="lrti-cell-secondary" href="mailto:' . esc_attr( $cemail ) . '" title="' . esc_attr( $cemail ) . '"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span>' . esc_html( $cemail ) . '</a>';
				}
				break;
			case 'lrti_trailer':
				$tid   = (int) get_post_meta( $post_id, $k['trailer_id'], true );
				$title = (string) get_post_meta( $post_id, $k['trailer_title'], true );
				if ( $tid && get_post_status( $tid ) ) {
					echo '<a class="lrti-cell-primary" href="' . esc_url( (string) get_edit_post_link( $tid ) ) . '">' . esc_html( $title ) . '</a>';
					$mfr  = lrti_first_term_name( $tid, 'trailer_manufacturer' );
					$type = lrti_first_term_name( $tid, 'trailer_type' );
					$bits = array_filter( array( $mfr, $type ) );
					if ( ! empty( $bits ) ) {
						echo '<span class="lrti-cell-secondary">' . esc_html( implode( ' · ', $bits ) ) . '</span>';
					}
				} else {
					echo '<span class="lrti-cell-primary">' . esc_html( $title ) . '</span>';
				}
				break;
			case 'lrti_stock':
				echo esc_html( (string) get_post_meta( $post_id, $k['stock_number'], true ) );
				break;
			case 'lrti_formtype':
				echo esc_html( $this->form_type_label( (string) get_post_meta( $post_id, $k['form_type'], true ) ) );
				break;
			case 'lrti_status':
				$status = $this->leads->get_status( $post_id );
				$labels = Leads::statuses();
				echo '<span class="lrti-status-badge lrti-status-' . esc_attr( $status ) . '">' . esc_html( $labels[ $status ] ?? $status ) . '</span>';
				break;
			case 'lrti_read':
				if ( '1' === (string) get_post_meta( $post_id, Leads::READ_META, true ) ) {
					echo '<span class="dashicons dashicons-yes" style="color:#8c8f94;" title="' . esc_attr__( 'Read', 'little-river-trailer-inventory' ) . '"></span><span class="screen-reader-text">' . esc_html__( 'Read', 'little-river-trailer-inventory' ) . '</span>';
				} else {
					echo '<span class="dashicons dashicons-marker" style="color:#a8321d;" title="' . esc_attr__( 'Unread', 'little-river-trailer-inventory' ) . '"></span><span class="screen-reader-text">' . esc_html__( 'Unread', 'little-river-trailer-inventory' ) . '</span>';
				}
				break;
			case 'lrti_assigned':
				$uid  = (int) get_post_meta( $post_id, $k['assigned_user'], true );
				$user = $uid ? get_userdata( $uid ) : false;
				echo $user
					? '<span class="lrti-assignee-badge">' . esc_html( $user->display_name ) . '</span>'
					: '<span class="description">' . esc_html__( 'Unassigned', 'little-river-trailer-inventory' ) . '</span>';
				break;
			case 'lrti_followup':
				$next = (string) get_post_meta( $post_id, $k['next_followup'], true );
				if ( '' === $next ) {
					echo '&mdash;';
				} else {
					$ts    = strtotime( $next . ' 12:00:00' );
					$human = $ts ? wp_date( get_option( 'date_format' ), $ts ) : $next;
					$alert = $this->followup_alert( $post_id );
					echo esc_html( $human );
					if ( $alert ) {
						echo ' <span class="lrti-fu-alert lrti-fu-' . esc_attr( $alert['key'] ) . '">' . esc_html( $alert['label'] ) . '</span>';
					}
				}
				break;
			case 'lrti_age':
				echo '<span class="lrti-age-badge">' . esc_html( $this->lead_age_label( $post_id ) ) . '</span>';
				break;
			case 'lrti_email':
				$email = (string) get_post_meta( $post_id, $k['email'], true );
				echo $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '&mdash;';
				break;
			case 'lrti_phone':
				$phone = (string) get_post_meta( $post_id, $k['phone'], true );
				echo $phone ? esc_html( $phone ) : '&mdash;';
				break;
			case 'lrti_notify':
				$status = (string) get_post_meta( $post_id, $k['notify_status'], true );
				$map    = array(
					'sent'   => __( 'Sent', 'little-river-trailer-inventory' ),
					'failed' => __( 'Failed', 'little-river-trailer-inventory' ),
				);
				echo esc_html( $map[ $status ] ?? __( 'Not attempted', 'little-river-trailer-inventory' ) );
				break;
		}
	}

	/**
	 * Render list filters (status / form type / notification).
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public function render_filters( string $post_type ): void {
		if ( Leads::POST_TYPE !== $post_type ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- list filters are read-only.
		$cur_status = isset( $_GET['lrti_status'] ) ? sanitize_key( wp_unslash( $_GET['lrti_status'] ) ) : '';
		$cur_form   = isset( $_GET['lrti_formtype'] ) ? sanitize_key( wp_unslash( $_GET['lrti_formtype'] ) ) : '';
		$cur_notify = isset( $_GET['lrti_notify'] ) ? sanitize_key( wp_unslash( $_GET['lrti_notify'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		echo '<select name="lrti_status"><option value="">' . esc_html__( 'All statuses', 'little-river-trailer-inventory' ) . '</option>';
		foreach ( Leads::statuses() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $cur_status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		echo '<select name="lrti_formtype"><option value="">' . esc_html__( 'All form types', 'little-river-trailer-inventory' ) . '</option>';
		foreach ( array( 'availability', 'information', 'similar_inventory' ) as $ft ) {
			echo '<option value="' . esc_attr( $ft ) . '" ' . selected( $cur_form, $ft, false ) . '>' . esc_html( $this->form_type_label( $ft ) ) . '</option>';
		}
		echo '</select>';

		echo '<select name="lrti_notify"><option value="">' . esc_html__( 'All notifications', 'little-river-trailer-inventory' ) . '</option>';
		foreach ( array( 'sent' => __( 'Sent', 'little-river-trailer-inventory' ), 'failed' => __( 'Failed', 'little-river-trailer-inventory' ), 'none' => __( 'Not attempted', 'little-river-trailer-inventory' ) ) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $cur_notify, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$cur_read = isset( $_GET['lrti_read'] ) ? sanitize_key( wp_unslash( $_GET['lrti_read'] ) ) : '';
		$cur_arch = isset( $_GET['lrti_archived'] ) ? sanitize_key( wp_unslash( $_GET['lrti_archived'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		echo '<select name="lrti_read"><option value="">' . esc_html__( 'Read &amp; unread', 'little-river-trailer-inventory' ) . '</option>';
		foreach ( array( 'unread' => __( 'Unread only', 'little-river-trailer-inventory' ), 'read' => __( 'Read only', 'little-river-trailer-inventory' ) ) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $cur_read, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		echo '<select name="lrti_archived"><option value="">' . esc_html__( 'Active leads', 'little-river-trailer-inventory' ) . '</option>';
		echo '<option value="archived" ' . selected( $cur_arch, 'archived', false ) . '>' . esc_html__( 'Archived', 'little-river-trailer-inventory' ) . '</option>';
		echo '</select>';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$cur_assigned = isset( $_GET['lrti_assigned'] ) ? absint( wp_unslash( $_GET['lrti_assigned'] ) ) : 0;
		$cur_followup = isset( $_GET['lrti_followup'] ) ? sanitize_key( wp_unslash( $_GET['lrti_followup'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		wp_dropdown_users(
			array(
				'name'              => 'lrti_assigned',
				'selected'          => $cur_assigned,
				'show_option_all'   => __( 'All staff', 'little-river-trailer-inventory' ),
				'show_option_none'  => __( 'Unassigned', 'little-river-trailer-inventory' ),
				'option_none_value' => -1,
				'capability'        => 'edit_lrti_leads',
			)
		);

		echo '<select name="lrti_followup"><option value="">' . esc_html__( 'Any follow-up', 'little-river-trailer-inventory' ) . '</option>';
		echo '<option value="today" ' . selected( $cur_followup, 'today', false ) . '>' . esc_html__( "Today's follow-ups", 'little-river-trailer-inventory' ) . '</option>';
		echo '<option value="overdue" ' . selected( $cur_followup, 'overdue', false ) . '>' . esc_html__( 'Overdue follow-ups', 'little-river-trailer-inventory' ) . '</option>';
		echo '</select>';

		$cur_priority = isset( $_GET['lrti_priority'] ) ? sanitize_key( wp_unslash( $_GET['lrti_priority'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<select name="lrti_priority"><option value="">' . esc_html__( 'Any priority', 'little-river-trailer-inventory' ) . '</option>';
		foreach ( Leads::priorities() as $pk => $pl ) {
			echo '<option value="' . esc_attr( $pk ) . '" ' . selected( $cur_priority, $pk, false ) . '>' . esc_html( $pl ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Apply admin list filters + status sorting.
	 *
	 * @param \WP_Query $query The query.
	 * @return void
	 */
	public function apply_filters_and_search( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( Leads::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$meta = array();
		$k    = Leads::meta_keys();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		if ( ! empty( $_GET['lrti_status'] ) ) {
			$meta[] = array( 'key' => Leads::STATUS_META, 'value' => sanitize_key( wp_unslash( $_GET['lrti_status'] ) ) );
		}
		if ( ! empty( $_GET['lrti_formtype'] ) ) {
			$meta[] = array( 'key' => $k['form_type'], 'value' => sanitize_key( wp_unslash( $_GET['lrti_formtype'] ) ) );
		}
		if ( ! empty( $_GET['lrti_notify'] ) ) {
			$notify = sanitize_key( wp_unslash( $_GET['lrti_notify'] ) );
			if ( 'none' === $notify ) {
				$meta[] = array( 'key' => $k['notify_status'], 'compare' => 'NOT EXISTS' );
			} else {
				$meta[] = array( 'key' => $k['notify_status'], 'value' => $notify );
			}
		}

		// Read / unread filter.
		if ( ! empty( $_GET['lrti_read'] ) ) {
			$read = sanitize_key( wp_unslash( $_GET['lrti_read'] ) );
			if ( 'read' === $read ) {
				$meta[] = array( 'key' => Leads::READ_META, 'value' => '1' );
			} elseif ( 'unread' === $read ) {
				$meta[] = array( 'key' => Leads::READ_META, 'value' => '1', 'compare' => '!=' );
			}
		}

		// Archived leads are excluded by default; only shown when requested.
		$archived = isset( $_GET['lrti_archived'] ) ? sanitize_key( wp_unslash( $_GET['lrti_archived'] ) ) : '';
		if ( 'archived' === $archived ) {
			$meta[] = array( 'key' => Leads::ARCHIVED_META, 'value' => '1' );
		} else {
			$meta[] = array( 'key' => Leads::ARCHIVED_META, 'compare' => 'NOT EXISTS' );
		}

		// Assigned-staff filter (-1 = unassigned).
		if ( isset( $_GET['lrti_assigned'] ) && '' !== $_GET['lrti_assigned'] ) {
			$assigned = (int) $_GET['lrti_assigned'];
			if ( -1 === $assigned ) {
				$meta[] = array(
					'relation' => 'OR',
					array( 'key' => $k['assigned_user'], 'compare' => 'NOT EXISTS' ),
					array( 'key' => $k['assigned_user'], 'value' => '0', 'compare' => '=' ),
				);
			} elseif ( $assigned > 0 ) {
				$meta[] = array( 'key' => $k['assigned_user'], 'value' => (string) $assigned );
			}
		}

		// Follow-up due filter.
		$followup = isset( $_GET['lrti_followup'] ) ? sanitize_key( wp_unslash( $_GET['lrti_followup'] ) ) : '';
		if ( 'overdue' === $followup ) {
			$meta[] = array(
				'key'     => $k['next_followup'],
				'value'   => current_time( 'Y-m-d' ),
				'compare' => '<=',
				'type'    => 'DATE',
			);
			$meta[] = array(
				'key'     => Leads::STATUS_META,
				'value'   => Leads::closed_statuses(),
				'compare' => 'NOT IN',
			);
		} elseif ( 'today' === $followup ) {
			$meta[] = array(
				'key'     => $k['next_followup'],
				'value'   => current_time( 'Y-m-d' ),
				'compare' => '=',
				'type'    => 'DATE',
			);
		}

		// Reminder priority filter.
		if ( ! empty( $_GET['lrti_priority'] ) ) {
			$prio = sanitize_key( wp_unslash( $_GET['lrti_priority'] ) );
			if ( array_key_exists( $prio, Leads::priorities() ) ) {
				$meta[] = array( 'key' => $k['followup_priority'], 'value' => $prio );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! empty( $meta ) ) {
			if ( count( $meta ) > 1 ) {
				$meta['relation'] = 'AND';
			}
			$query->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		if ( 'lrti_status' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', Leads::STATUS_META ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'orderby', 'meta_value' );
		}
	}

	/**
	 * Extend admin search to include lead meta (email/phone/stock/customer).
	 *
	 * @param string    $search The search SQL.
	 * @param \WP_Query $query  The query.
	 * @return string
	 */
	public function extend_search( string $search, \WP_Query $query ): string {
		if ( ! is_admin() || ! $query->is_main_query() || Leads::POST_TYPE !== $query->get( 'post_type' ) ) {
			return $search;
		}
		$term = (string) $query->get( 's' );
		if ( '' === $term ) {
			return $search;
		}

		global $wpdb;
		$like = '%' . $wpdb->esc_like( $term ) . '%';

		// Replace the default search with a title + meta search.
		$sql = $wpdb->prepare(
			" AND (
				{$wpdb->posts}.post_title LIKE %s
				OR EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} lrti_sm
					WHERE lrti_sm.post_id = {$wpdb->posts}.ID
					AND lrti_sm.meta_key IN ('_lrti_lead_name','_lrti_lead_email','_lrti_lead_phone','_lrti_lead_stock_number','_lrti_lead_trailer_title','_lrti_lead_internal_notes','_lrti_lead_notes_log')
					AND lrti_sm.meta_value LIKE %s
				)
			)",
			$like,
			$like
		);

		return $sql;
	}

	/* --------------------------------------------------------------------- *
	 * Row + bulk actions
	 * --------------------------------------------------------------------- */

	/**
	 * Bulk status actions.
	 *
	 * @param array<string, string> $actions Existing bulk actions.
	 * @return array<string, string>
	 */
	public function bulk_actions( array $actions ): array {
		unset( $actions['edit'] );
		$actions['lrti_mark_contacted'] = __( 'Mark Contacted', 'little-river-trailer-inventory' );
		$actions['lrti_mark_qualified'] = __( 'Mark Qualified', 'little-river-trailer-inventory' );
		$actions['lrti_mark_won']       = __( 'Mark Won', 'little-river-trailer-inventory' );
		$actions['lrti_mark_lost']      = __( 'Mark Lost', 'little-river-trailer-inventory' );
		$actions['lrti_mark_followup']  = __( 'Mark Follow-Up', 'little-river-trailer-inventory' );
		$actions['lrti_mark_spam']      = __( 'Mark Spam', 'little-river-trailer-inventory' );
		$actions['lrti_mark_read']      = __( 'Mark Read', 'little-river-trailer-inventory' );
		$actions['lrti_mark_unread']    = __( 'Mark Unread', 'little-river-trailer-inventory' );
		$actions['lrti_archive']        = __( 'Archive', 'little-river-trailer-inventory' );
		return $actions;
	}

	/**
	 * Handle bulk status actions.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   The bulk action.
	 * @param int[]  $ids      Selected lead IDs.
	 * @return string
	 */
	public function handle_bulk( string $redirect, string $action, array $ids ): string {
		if ( ! current_user_can( $this->cap() ) ) {
			return $redirect;
		}

		// Read / unread / archive bulk actions.
		if ( in_array( $action, array( 'lrti_mark_read', 'lrti_mark_unread', 'lrti_archive' ), true ) ) {
			$count = 0;
			foreach ( $ids as $id ) {
				$id = (int) $id;
				if ( ! current_user_can( 'edit_lrti_lead', $id ) ) {
					continue;
				}
				if ( 'lrti_mark_read' === $action ) {
					$this->leads->set_read( $id, true );
				} elseif ( 'lrti_mark_unread' === $action ) {
					$this->leads->set_read( $id, false );
				} else {
					$this->leads->set_archived( $id, true );
				}
				++$count;
			}
			return add_query_arg( 'lrti_bulk', $count, $redirect );
		}

		$map = array(
			'lrti_mark_contacted' => 'contacted',
			'lrti_mark_qualified' => 'qualified',
			'lrti_mark_followup'  => 'follow-up',
			'lrti_mark_won'       => 'won',
			'lrti_mark_lost'      => 'lost',
			'lrti_mark_spam'      => 'spam',
		);
		if ( ! isset( $map[ $action ] ) ) {
			return $redirect;
		}
		if ( ! current_user_can( $this->cap() ) ) {
			return $redirect;
		}

		$count = 0;
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( current_user_can( 'edit_lrti_lead', $id ) ) {
				$this->leads->set_status( $id, $map[ $action ], get_current_user_id() );
				$count++;
			}
		}
		return add_query_arg( 'lrti_bulk', $count, $redirect );
	}

	/**
	 * Add quick row actions.
	 *
	 * @param array<string, string> $actions Row actions.
	 * @param \WP_Post               $post    The lead.
	 * @return array<string, string>
	 */
	public function row_actions( array $actions, \WP_Post $post ): array {
		if ( Leads::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_lrti_lead', $post->ID ) ) {
			return $actions;
		}

		$id  = $post->ID;
		$eid = 'lrti-more-' . $id;

		$do_url = static function ( string $do ) use ( $id ): string {
			return wp_nonce_url(
				admin_url( 'admin-post.php?action=lrti_lead_action&do=' . $do . '&lead=' . $id ),
				'lrti_lead_action_' . $id
			);
		};

		$status   = $this->leads->get_status( $id );
		$archived = '1' === (string) get_post_meta( $id, Leads::ARCHIVED_META, true );
		$is_read  = '1' === (string) get_post_meta( $id, Leads::READ_META, true );
		$tid      = (int) get_post_meta( $id, Leads::meta_keys()['trailer_id'], true );
		$edit_url = (string) get_edit_post_link( $id );

		// Turn a raw <a ...> string into an accessible menu item.
		$item = static function ( string $html, bool $danger = false ): string {
			return (string) preg_replace(
				'/^<a\s/',
				'<a role="menuitem" tabindex="-1" class="lrti-menu-item' . ( $danger ? ' lrti-menu-item--danger' : '' ) . '" ',
				trim( $html ),
				1
			);
		};

		// --- Build the More menu, grouped, reusing native/third-party URLs. ---
		$groups = array(
			'lead'   => array( 'label' => __( 'Lead', 'little-river-trailer-inventory' ), 'items' => array() ),
			'status' => array( 'label' => __( 'Status', 'little-river-trailer-inventory' ), 'items' => array() ),
			'comm'   => array( 'label' => __( 'Communication', 'little-river-trailer-inventory' ), 'items' => array() ),
			'record' => array( 'label' => __( 'Record', 'little-river-trailer-inventory' ), 'items' => array() ),
		);

		// Lead group: Quick Edit (native), Schedule Follow-Up, Mark Read/Unread.
		if ( isset( $actions['inline hide-if-no-js'] ) ) {
			$groups['lead']['items'][] = $item( $actions['inline hide-if-no-js'] );
		}
		$groups['lead']['items'][] = $item( '<a href="' . esc_url( $edit_url . '#lrti-lead-management' ) . '">' . esc_html__( 'Schedule Follow-Up', 'little-river-trailer-inventory' ) . '</a>' );
		$groups['lead']['items'][] = $item( '<a href="' . esc_url( $do_url( $is_read ? 'unread' : 'read' ) ) . '">' . esc_html( $is_read ? __( 'Mark Unread', 'little-river-trailer-inventory' ) : __( 'Mark Read', 'little-river-trailer-inventory' ) ) . '</a>' );

		// Status group.
		$groups['status']['items'][] = $item( '<a href="' . esc_url( $do_url( 'won' ) ) . '">' . esc_html__( 'Mark Won', 'little-river-trailer-inventory' ) . '</a>' );
		$groups['status']['items'][] = $item( '<a href="' . esc_url( $do_url( 'lost' ) ) . '">' . esc_html__( 'Mark Lost', 'little-river-trailer-inventory' ) . '</a>' );
		$groups['status']['items'][] = $item( '<a href="' . esc_url( $do_url( 'spam' ) ) . '">' . esc_html__( 'Mark Spam', 'little-river-trailer-inventory' ) . '</a>' );

		// Communication group.
		$groups['comm']['items'][] = $item( '<a href="' . esc_url( $do_url( 'resend' ) ) . '">' . esc_html__( 'Resend Notification', 'little-river-trailer-inventory' ) . '</a>' );
		if ( $tid && 'publish' === get_post_status( $tid ) ) {
			$groups['comm']['items'][] = $item( '<a href="' . esc_url( (string) get_permalink( $tid ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View Trailer', 'little-river-trailer-inventory' ) . '</a>' );
		}

		// Record group: Archive, plus any leftover third-party actions (Duplicate, Delete Cache, etc.).
		$groups['record']['items'][] = $item( '<a href="' . esc_url( $do_url( 'archive' ) ) . '">' . esc_html__( 'Archive', 'little-river-trailer-inventory' ) . '</a>' );
		$handled = array( 'edit', 'inline hide-if-no-js', 'trash' );
		foreach ( $actions as $key => $html ) {
			if ( in_array( $key, $handled, true ) ) {
				continue;
			}
			$groups['record']['items'][] = $item( (string) $html );
		}

		// Destructive: Trash (kept separate, danger styling).
		$trash = isset( $actions['trash'] ) ? $item( $actions['trash'], true ) : '';

		// --- Assemble menu HTML. ---
		$menu = '<div class="lrti-more-menu" id="' . esc_attr( $eid ) . '" role="menu" aria-label="' . esc_attr__( 'More lead actions', 'little-river-trailer-inventory' ) . '">';
		foreach ( $groups as $g ) {
			if ( empty( $g['items'] ) ) {
				continue;
			}
			$menu .= '<div class="lrti-menu-group" role="none"><span class="lrti-menu-heading" role="presentation">' . esc_html( $g['label'] ) . '</span>';
			$menu .= implode( '', $g['items'] );
			$menu .= '</div>';
		}
		if ( '' !== $trash ) {
			$menu .= '<div class="lrti-menu-sep" role="separator"></div>' . $trash;
		}
		$menu .= '</div>';

		// --- Primary visible actions. ---
		$view = '<a class="lrti-btn lrti-btn--primary lrti-btn--sm" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'View Lead', 'little-river-trailer-inventory' ) . '</a>';

		$can_contact = ! in_array( $status, array( 'contacted', 'qualified', 'won', 'lost', 'spam' ), true ) && ! $archived;
		$contact     = $can_contact
			? '<a class="lrti-btn lrti-btn--secondary lrti-btn--sm" href="' . esc_url( $do_url( 'contacted' ) ) . '">' . esc_html__( 'Mark Contacted', 'little-river-trailer-inventory' ) . '</a>'
			: '';

		$more = '<span class="lrti-row-more">'
			. '<button type="button" class="lrti-btn lrti-btn--neutral lrti-btn--sm lrti-more-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="' . esc_attr( $eid ) . '">'
			. esc_html__( 'More', 'little-river-trailer-inventory' ) . ' <span class="lrti-chevron" aria-hidden="true"></span></button>'
			. $menu
			. '</span>';

		$bar = '<div class="lrti-row-actions">' . $view . $contact . $more . '</div>';

		$ftype    = $this->form_type_label( (string) get_post_meta( $id, Leads::meta_keys()['form_type'], true ) );
		$subtitle = '' !== $ftype
			? '<div class="lrti-lead-subtitle">' . esc_html( sprintf( /* translators: %s: inquiry form type */ __( '%s inquiry', 'little-river-trailer-inventory' ), $ftype ) ) . '</div>'
			: '';

		// Return a single entry so WordPress renders only the compact bar.
		return array( 'lrti_bar' => $subtitle . $bar );
	}

	/**
	 * Handle a single-lead quick action.
	 *
	 * @return void
	 */
	public function handle_row_action(): void {
		$lead = isset( $_GET['lead'] ) ? absint( $_GET['lead'] ) : 0;
		$do   = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';

		if ( ! $lead || ! check_admin_referer( 'lrti_lead_action_' . $lead ) ) {
			wp_die( esc_html__( 'Security check failed.', 'little-river-trailer-inventory' ) );
		}
		if ( ! current_user_can( 'edit_lrti_lead', $lead ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'little-river-trailer-inventory' ) );
		}

		$notice = 'updated';
		if ( 'resend' === $do ) {
			$this->notifications->send_dealer_notification( $lead );
			$this->leads->add_activity( $lead, __( 'Notification resent', 'little-river-trailer-inventory' ), get_current_user_id() );
			$notice = 'resent';
		} elseif ( 'read' === $do ) {
			$this->leads->set_read( $lead, true );
		} elseif ( 'unread' === $do ) {
			$this->leads->set_read( $lead, false );
		} elseif ( 'archive' === $do ) {
			$this->leads->set_archived( $lead, true );
			$notice = 'archived';
		} elseif ( 'unarchive' === $do ) {
			$this->leads->set_archived( $lead, false );
		} elseif ( in_array( $do, array( 'contacted', 'qualified', 'follow-up', 'won', 'lost', 'spam', 'appointment' ), true ) ) {
			$this->leads->set_status( $lead, $do, get_current_user_id() );
			if ( 'contacted' === $do ) {
				update_post_meta( $lead, Leads::LAST_CONTACTED_META, time() );
			}
		}

		$redirect = add_query_arg(
			array( 'post_type' => Leads::POST_TYPE, 'lrti_notice' => $notice ),
			admin_url( 'edit.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * Edit screen
	 * --------------------------------------------------------------------- */

	/**
	 * Register lead metaboxes and remove the editor.
	 *
	 * @return void
	 */
	public function add_metaboxes(): void {
		remove_post_type_support( Leads::POST_TYPE, 'editor' );

		// Opening the detail screen marks the lead read.
		global $post;
		if ( $post instanceof \WP_Post && Leads::POST_TYPE === $post->post_type
			&& '1' !== (string) get_post_meta( $post->ID, Leads::READ_META, true )
			&& current_user_can( 'edit_lrti_lead', $post->ID )
		) {
			$this->leads->set_read( $post->ID, true );
			$this->leads->add_activity( $post->ID, __( 'Lead opened', 'little-river-trailer-inventory' ), get_current_user_id() );
		}

		add_meta_box( 'lrti-lead-customer', __( 'Customer', 'little-river-trailer-inventory' ), array( $this, 'box_customer' ), Leads::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'lrti-lead-inquiry', __( 'Inquiry', 'little-river-trailer-inventory' ), array( $this, 'box_inquiry' ), Leads::POST_TYPE, 'normal' );
		add_meta_box( 'lrti-lead-trailer', __( 'Trailer', 'little-river-trailer-inventory' ), array( $this, 'box_trailer' ), Leads::POST_TYPE, 'side' );
		add_meta_box( 'lrti-lead-management', __( 'Management', 'little-river-trailer-inventory' ), array( $this, 'box_management' ), Leads::POST_TYPE, 'side', 'high' );
		add_meta_box( 'lrti-lead-actions', __( 'Quick Actions', 'little-river-trailer-inventory' ), array( $this, 'box_quick_actions' ), Leads::POST_TYPE, 'side', 'high' );
		add_meta_box( 'lrti-lead-notes', __( 'Internal Notes', 'little-river-trailer-inventory' ), array( $this, 'box_notes' ), Leads::POST_TYPE, 'normal' );
		add_meta_box( 'lrti-lead-notification', __( 'Notification', 'little-river-trailer-inventory' ), array( $this, 'box_notification' ), Leads::POST_TYPE, 'side' );
		add_meta_box( 'lrti-lead-activity', __( 'Activity Log', 'little-river-trailer-inventory' ), array( $this, 'box_activity' ), Leads::POST_TYPE, 'normal' );
	}

	/**
	 * Read a lead meta field.
	 *
	 * @param int    $id    Lead ID.
	 * @param string $field Field key.
	 * @return string
	 */
	private function m( int $id, string $field ): string {
		$k = Leads::meta_keys();
		return isset( $k[ $field ] ) ? (string) get_post_meta( $id, $k[ $field ], true ) : '';
	}

	/**
	 * Render a modern CRM header above the lead metaboxes.
	 *
	 * @param mixed $post The current post (only acts on lead CPT).
	 * @return void
	 */
	public function render_lead_header( $post ): void {
		if ( ! ( $post instanceof \WP_Post ) || Leads::POST_TYPE !== $post->post_type ) {
			return;
		}

		$name   = $this->m( $post->ID, 'name' );
		$email  = $this->m( $post->ID, 'email' );
		$phone  = $this->m( $post->ID, 'phone' );
		$tid    = (int) $this->m( $post->ID, 'trailer_id' );
		$ttitle = $this->m( $post->ID, 'trailer_title' );
		$turl   = $this->m( $post->ID, 'trailer_url' );
		$tlink  = $tid && get_post_status( $tid ) ? (string) get_permalink( $tid ) : $turl;

		$status  = $this->leads->get_status( $post->ID );
		$labels  = Leads::statuses();
		$age     = $this->lead_age_label( $post->ID );
		$sub     = (int) $this->m( $post->ID, 'submitted' );
		$sub_fmt = $sub ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $sub ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '';
		$uid     = (int) $this->m( $post->ID, 'assigned_user' );
		$user    = $uid ? get_userdata( $uid ) : false;
		$alert   = $this->followup_alert( $post->ID );
		$next    = $this->m( $post->ID, 'next_followup' );

		$display_name = '' !== $name ? $name : ( '' !== $email ? $email : __( 'Unknown Customer', 'little-river-trailer-inventory' ) );
		?>
		<div class="lrti-app lrti-lead-header">
			<div class="lrti-lead-header-top">
				<div class="lrti-lead-header-id">
					<h1 class="lrti-lead-name"><?php echo esc_html( $display_name ); ?></h1>
					<?php if ( '' !== $ttitle ) : ?>
						<p class="lrti-lead-sub">
							<?php echo esc_html__( 'Inquiry for', 'little-river-trailer-inventory' ) . ' '; ?>
							<?php if ( '' !== $tlink ) : ?>
								<a href="<?php echo esc_url( $tlink ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $ttitle ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $ttitle ); ?>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</div>
				<div class="lrti-lead-header-actions">
					<?php if ( '' !== $phone ) : ?>
						<a class="lrti-btn lrti-btn--primary" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><span class="dashicons dashicons-phone" aria-hidden="true"></span> <?php esc_html_e( 'Call Customer', 'little-river-trailer-inventory' ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $email ) : ?>
						<a class="lrti-btn lrti-btn--secondary" href="mailto:<?php echo esc_attr( $email ); ?>"><span class="dashicons dashicons-email" aria-hidden="true"></span> <?php esc_html_e( 'Email Customer', 'little-river-trailer-inventory' ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $tlink ) : ?>
						<a class="lrti-btn lrti-btn--secondary" href="<?php echo esc_url( $tlink ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external" aria-hidden="true"></span> <?php esc_html_e( 'View Trailer', 'little-river-trailer-inventory' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<div class="lrti-lead-header-meta">
				<span class="lrti-meta-item">
					<span class="lrti-status-badge lrti-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $labels[ $status ] ?? $status ); ?></span>
				</span>
				<?php if ( '' !== $age ) : ?>
					<span class="lrti-meta-item"><span class="lrti-meta-label"><?php esc_html_e( 'Age', 'little-river-trailer-inventory' ); ?></span> <?php echo esc_html( $age ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $sub_fmt ) : ?>
					<span class="lrti-meta-item"><span class="lrti-meta-label"><?php esc_html_e( 'Submitted', 'little-river-trailer-inventory' ); ?></span> <?php echo esc_html( $sub_fmt ); ?></span>
				<?php endif; ?>
				<span class="lrti-meta-item">
					<span class="lrti-meta-label"><?php esc_html_e( 'Assigned', 'little-river-trailer-inventory' ); ?></span>
					<?php echo $user ? esc_html( $user->display_name ) : esc_html__( 'Unassigned', 'little-river-trailer-inventory' ); ?>
				</span>
				<span class="lrti-meta-item">
					<span class="lrti-meta-label"><?php esc_html_e( 'Follow-up', 'little-river-trailer-inventory' ); ?></span>
					<?php
					if ( '' === $next ) {
						esc_html_e( 'Not scheduled', 'little-river-trailer-inventory' );
					} else {
						$ts = strtotime( $next . ' 12:00:00' );
						echo esc_html( $ts ? wp_date( get_option( 'date_format' ), $ts ) : $next );
						if ( $alert ) {
							echo ' <span class="lrti-fu-alert lrti-fu-' . esc_attr( $alert['key'] ) . '">' . esc_html( $alert['label'] ) . '</span>';
						}
					}
					?>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Customer metabox (read-only submission).
	 *
	 * @param \WP_Post $post Lead.
	 * @return void
	 */
	public function box_customer( \WP_Post $post ): void {
		$name    = $this->m( $post->ID, 'name' );
		$email   = $this->m( $post->ID, 'email' );
		$phone   = $this->m( $post->ID, 'phone' );
		$pref    = $this->m( $post->ID, 'preferred_contact' );
		$consent = '1' === $this->m( $post->ID, 'consent' );
		$age     = $this->lead_age_label( $post->ID );
		$sub     = (int) $this->m( $post->ID, 'submitted' );
		$sub_fmt = $sub ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $sub ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '';

		echo '<div class="lrti-crm-card">';
		echo '<div class="lrti-crm-head"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span>';
		echo '<span class="lrti-crm-name">' . esc_html( '' !== $name ? $name : __( 'Unknown Customer', 'little-river-trailer-inventory' ) ) . '</span></div>';
		if ( '' !== $email ) {
			$this->crm_row( 'email-alt', __( 'Email', 'little-river-trailer-inventory' ), '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' );
		}
		if ( '' !== $phone ) {
			$this->crm_row( 'phone', __( 'Phone', 'little-river-trailer-inventory' ), '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>' );
		}
		$this->crm_row( 'format-chat', __( 'Preferred contact', 'little-river-trailer-inventory' ), esc_html( $pref ) );
		$this->crm_row(
			$consent ? 'yes-alt' : 'no-alt',
			__( 'Consent', 'little-river-trailer-inventory' ),
			esc_html( $consent ? __( 'Granted', 'little-river-trailer-inventory' ) : __( 'Not granted', 'little-river-trailer-inventory' ) )
		);
		$this->crm_row( 'clock', __( 'Lead age', 'little-river-trailer-inventory' ), esc_html( $age ) );
		$this->crm_row( 'calendar-alt', __( 'Submitted', 'little-river-trailer-inventory' ), esc_html( $sub_fmt ) );
		echo '</div>';
	}

	/**
	 * Render one icon row inside a CRM card. $value is pre-escaped HTML.
	 *
	 * @param string $icon  Dashicon suffix.
	 * @param string $label Row label.
	 * @param string $value Pre-escaped value HTML.
	 * @return void
	 */
	private function crm_row( string $icon, string $label, string $value ): void {
		if ( '' === trim( wp_strip_all_tags( $value ) ) ) {
			return;
		}
		echo '<div class="lrti-crm-row">';
		echo '<span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
		echo '<span class="lrti-crm-label">' . esc_html( $label ) . '</span>';
		echo '<span class="lrti-crm-value">' . $value . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller pre-escapes.
		echo '</div>';
	}

	/**
	 * Map an activity action to a timeline marker color class.
	 *
	 * @param string $action Action text.
	 * @return string
	 */
	private function activity_marker_class( string $action ): string {
		$a = strtolower( $action );
		if ( false !== strpos( $a, 'fail' ) ) {
			return 'lrti-log--danger';
		}
		if ( false !== strpos( $a, 'notification' ) || false !== strpos( $a, 'sent to' ) || false !== strpos( $a, 'email' ) ) {
			return 'lrti-log--success';
		}
		if ( false !== strpos( $a, 'created' ) ) {
			return 'lrti-log--brand';
		}
		if ( false !== strpos( $a, 'opened' ) || false !== strpos( $a, 'viewed' ) ) {
			return 'lrti-log--info';
		}
		if ( false !== strpos( $a, 'status' ) ) {
			return 'lrti-log--warning';
		}
		return '';
	}

	/**
	 * Map an activity action string to a Dashicon suffix.
	 *
	 * @param string $action Action text.
	 * @return string
	 */
	private function activity_icon( string $action ): string {		$a = strtolower( $action );
		if ( false !== strpos( $a, 'created' ) ) {
			return 'plus-alt';
		}
		if ( false !== strpos( $a, 'notification' ) || false !== strpos( $a, 'email' ) ) {
			return 'email-alt';
		}
		if ( false !== strpos( $a, 'opened' ) || false !== strpos( $a, 'viewed' ) ) {
			return 'visibility';
		}
		if ( false !== strpos( $a, 'status' ) ) {
			return 'update';
		}
		if ( false !== strpos( $a, 'assign' ) ) {
			return 'admin-users';
		}
		if ( false !== strpos( $a, 'follow-up' ) || false !== strpos( $a, 'follow up' ) ) {
			return 'calendar-alt';
		}
		if ( false !== strpos( $a, 'note' ) ) {
			return 'edit';
		}
		if ( false !== strpos( $a, 'archiv' ) ) {
			return 'archive';
		}
		return 'marker';
	}

	/**
	 * Inquiry metabox (read-only).
	 *
	 * @param \WP_Post $post Lead.
	 * @return void
	 */
	public function box_inquiry( \WP_Post $post ): void {
		echo '<p><strong>' . esc_html__( 'Form type:', 'little-river-trailer-inventory' ) . '</strong> ' . esc_html( $this->form_type_label( $this->m( $post->ID, 'form_type' ) ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Message:', 'little-river-trailer-inventory' ) . '</strong></p>';
		echo '<div class="lrti-lead-message">' . nl2br( esc_html( $this->m( $post->ID, 'message' ) ) ) . '</div>';

		$submitted = (int) $this->m( $post->ID, 'submitted' );
		$rows      = array(
			__( 'Submitted', 'little-river-trailer-inventory' )  => $submitted ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $submitted ), 'Y-m-d H:i' ) : '',
			__( 'Source URL', 'little-river-trailer-inventory' ) => $this->m( $post->ID, 'source_url' ),
			__( 'Referrer', 'little-river-trailer-inventory' )   => $this->m( $post->ID, 'referrer' ),
			'UTM source'   => $this->m( $post->ID, 'utm_source' ),
			'UTM medium'   => $this->m( $post->ID, 'utm_medium' ),
			'UTM campaign' => $this->m( $post->ID, 'utm_campaign' ),
			'UTM term'     => $this->m( $post->ID, 'utm_term' ),
			'UTM content'  => $this->m( $post->ID, 'utm_content' ),
		);
		$this->render_readonly_table( $rows );
	}

	/**
	 * Trailer metabox (read-only).
	 *
	 * @param \WP_Post $post Lead.
	 * @return void
	 */
	public function box_trailer( \WP_Post $post ): void {
		$tid   = (int) $this->m( $post->ID, 'trailer_id' );
		$title = $this->m( $post->ID, 'trailer_title' );
		$url   = $this->m( $post->ID, 'trailer_url' );
		$stock = $this->m( $post->ID, 'stock_number' );
		$exists = $tid && get_post_status( $tid );

		if ( ! $exists && '' === $title ) {
			echo '<div class="lrti-empty"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span>';
			echo '<p>' . esc_html__( 'Associated trailer is no longer available.', 'little-river-trailer-inventory' ) . '</p></div>';
			return;
		}

		$link = $exists ? (string) get_permalink( $tid ) : $url;
		echo '<div class="lrti-trailer-card">';

		if ( $exists && has_post_thumbnail( $tid ) ) {
			echo '<div class="lrti-trailer-thumb">' . get_the_post_thumbnail( $tid, 'thumbnail', array( 'loading' => 'lazy' ) ) . '</div>';
		} else {
			echo '<div class="lrti-trailer-thumb lrti-trailer-thumb--empty"><span class="dashicons dashicons-format-image" aria-hidden="true"></span></div>';
		}

		echo '<div class="lrti-trailer-info">';
		echo '<p class="lrti-trailer-title">' . esc_html( '' !== $title ? $title : get_the_title( $tid ) ) . '</p>';
		if ( '' !== $stock ) {
			echo '<p class="lrti-trailer-meta">' . esc_html__( 'Stock #', 'little-river-trailer-inventory' ) . ' ' . esc_html( $stock ) . '</p>';
		}
		if ( $exists ) {
			$mfr  = lrti_first_term_name( $tid, 'trailer_manufacturer' );
			$type = lrti_first_term_name( $tid, 'trailer_type' );
			if ( '' !== $mfr ) {
				echo '<p class="lrti-trailer-meta">' . esc_html( $mfr ) . '</p>';
			}
			if ( '' !== $type ) {
				echo '<p class="lrti-trailer-meta">' . esc_html( $type ) . '</p>';
			}
		} else {
			echo '<p class="lrti-trailer-meta lrti-trailer-gone">' . esc_html__( 'Associated trailer is no longer available.', 'little-river-trailer-inventory' ) . '</p>';
		}
		echo '</div>';
		echo '</div>';

		if ( '' !== $link ) {
			echo '<p><a class="lrti-btn lrti-btn--secondary lrti-btn--block" href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external" aria-hidden="true"></span> ' . esc_html__( 'View Trailer', 'little-river-trailer-inventory' ) . '</a></p>';
		}
	}

	/**
	 * Management metabox (editable: status / assigned user / notes).
	 *
	 * @param \WP_Post $post Lead.
	 * @return void
	 */
	public function box_management( \WP_Post $post ): void {
		wp_nonce_field( 'lrti_lead_manage_' . $post->ID, 'lrti_lead_manage_nonce' );

		echo '<p class="lrti-lead-age-line"><strong>' . esc_html__( 'Lead age:', 'little-river-trailer-inventory' ) . '</strong> <span class="lrti-age-badge">' . esc_html( $this->lead_age_label( $post->ID ) ) . '</span></p>';

		$status   = $this->leads->get_status( $post->ID );
		$assigned = (int) $this->m( $post->ID, 'assigned_user' );
		$notes    = $this->m( $post->ID, 'internal_notes' );
		$last_con = (int) get_post_meta( $post->ID, Leads::LAST_CONTACTED_META, true );
		$next_fu  = (string) $this->m( $post->ID, 'next_followup' );
		$fu_time  = (string) $this->m( $post->ID, 'followup_time' );
		$fu_prio  = (string) $this->m( $post->ID, 'followup_priority' );
		$fu_notes = (string) $this->m( $post->ID, 'followup_notes' );
		$priorities = Leads::priorities();
		if ( '' === $fu_prio ) {
			$fu_prio = 'normal';
		}
		?>
		<p class="lrti-section-label"><?php esc_html_e( 'Lead', 'little-river-trailer-inventory' ); ?></p>
		<p>
			<label for="lrti-lead-status"><strong><?php esc_html_e( 'Status', 'little-river-trailer-inventory' ); ?></strong></label><br />
			<select id="lrti-lead-status" name="lrti_lead_status" class="widefat">
				<?php foreach ( Leads::statuses() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="lrti-lead-assigned"><strong><?php esc_html_e( 'Assigned staff', 'little-river-trailer-inventory' ); ?></strong></label><br />
			<?php
			wp_dropdown_users(
				array(
					'name'              => 'lrti_lead_assigned',
					'id'                => 'lrti-lead-assigned',
					'selected'          => $assigned,
					'show_option_none'  => __( '— Unassigned —', 'little-river-trailer-inventory' ),
					'option_none_value' => 0,
					'capability'        => 'edit_lrti_leads',
					'class'             => 'widefat',
				)
			);
			?>
		</p>
		<p class="lrti-section-label"><?php esc_html_e( 'Contact Activity', 'little-river-trailer-inventory' ); ?></p>
		<p>
			<label for="lrti-lead-last-contacted"><strong><?php esc_html_e( 'Last contacted', 'little-river-trailer-inventory' ); ?></strong></label><br />
			<input type="date" id="lrti-lead-last-contacted" name="lrti_lead_last_contacted" class="widefat" value="<?php echo esc_attr( $last_con > 0 ? gmdate( 'Y-m-d', $last_con ) : '' ); ?>" />
		</p>
		<p class="lrti-section-label"><?php esc_html_e( 'Follow-Up', 'little-river-trailer-inventory' ); ?></p>
		<p>
			<label for="lrti-lead-next-followup"><strong><?php esc_html_e( 'Next follow-up', 'little-river-trailer-inventory' ); ?></strong></label><br />
			<input type="date" id="lrti-lead-next-followup" name="lrti_lead_next_followup" class="widefat" value="<?php echo esc_attr( $next_fu ); ?>" />
			<?php
			$fu_alert = $this->followup_alert( $post->ID );
			if ( $fu_alert ) :
				?>
				<span class="lrti-fu-alert lrti-fu-<?php echo esc_attr( $fu_alert['key'] ); ?>"><?php echo esc_html( $fu_alert['label'] ); ?></span>
			<?php elseif ( '' === $next_fu ) : ?>
				<span class="lrti-empty-hint"><?php esc_html_e( 'No follow-up scheduled. Pick a date to schedule one.', 'little-river-trailer-inventory' ); ?></span>
			<?php endif; ?>
		</p>
		<p>
			<label for="lrti-lead-followup-time"><strong><?php esc_html_e( 'Follow-up time', 'little-river-trailer-inventory' ); ?></strong></label><br />
			<input type="time" id="lrti-lead-followup-time" name="lrti_lead_followup_time" class="widefat" value="<?php echo esc_attr( $fu_time ); ?>" />
		</p>
		<p>
			<label for="lrti-lead-followup-priority"><strong><?php esc_html_e( 'Reminder priority', 'little-river-trailer-inventory' ); ?></strong></label><br />
			<select id="lrti-lead-followup-priority" name="lrti_lead_followup_priority" class="widefat">
				<?php foreach ( $priorities as $pk => $pl ) : ?>
					<option value="<?php echo esc_attr( $pk ); ?>" <?php selected( $fu_prio, $pk ); ?>><?php echo esc_html( $pl ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="lrti-lead-followup-notes"><strong><?php esc_html_e( 'Follow-up notes', 'little-river-trailer-inventory' ); ?></strong></label>
			<textarea id="lrti-lead-followup-notes" name="lrti_lead_followup_notes" rows="3" class="widefat"><?php echo esc_textarea( $fu_notes ); ?></textarea>
		</p>
		<p>
			<label for="lrti-lead-notes"><strong><?php esc_html_e( 'Legacy internal note', 'little-river-trailer-inventory' ); ?></strong>
			<span class="description"><?php esc_html_e( '(Use the Internal Notes panel below for new notes.)', 'little-river-trailer-inventory' ); ?></span></label>
			<textarea id="lrti-lead-notes" name="lrti_lead_notes" rows="3" class="widefat"><?php echo esc_textarea( $notes ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Quick Actions metabox: call / email / view trailer / copy.
	 *
	 * @param \WP_Post $post Lead.
	 * @return void
	 */
	public function box_quick_actions( \WP_Post $post ): void {
		$email = (string) $this->m( $post->ID, 'email' );
		$phone = (string) $this->m( $post->ID, 'phone' );
		$is_read = '1' === (string) get_post_meta( $post->ID, Leads::READ_META, true );

		echo '<p class="description">' . esc_html__( 'Call, Email, and View Trailer are in the header above.', 'little-river-trailer-inventory' ) . '</p>';

		echo '<p class="lrti-copy-actions">';
		if ( '' !== $email ) {
			printf(
				'<button type="button" class="lrti-btn lrti-btn--secondary lrti-btn--sm lrti-copy" data-copy="%1$s">%2$s</button> ',
				esc_attr( $email ),
				esc_html__( 'Copy Email', 'little-river-trailer-inventory' )
			);
		}
		if ( '' !== $phone ) {
			printf(
				'<button type="button" class="lrti-btn lrti-btn--secondary lrti-btn--sm lrti-copy" data-copy="%1$s">%2$s</button>',
				esc_attr( $phone ),
				esc_html__( 'Copy Phone', 'little-river-trailer-inventory' )
			);
		}
		echo '<span class="lrti-copy-feedback" role="status" aria-live="polite" style="margin-left:8px;color:#2e7d32;"></span>';
		echo '</p>';

		$resend_url  = wp_nonce_url( admin_url( 'admin-post.php?action=lrti_lead_action&do=resend&lead=' . $post->ID ), 'lrti_lead_action_' . $post->ID );
		$toggle_do   = $is_read ? 'unread' : 'read';
		$toggle_lbl  = $is_read ? __( 'Mark Unread', 'little-river-trailer-inventory' ) : __( 'Mark Read', 'little-river-trailer-inventory' );
		$toggle_url  = wp_nonce_url( admin_url( 'admin-post.php?action=lrti_lead_action&do=' . $toggle_do . '&lead=' . $post->ID ), 'lrti_lead_action_' . $post->ID );
		$archive_url = wp_nonce_url( admin_url( 'admin-post.php?action=lrti_lead_action&do=archive&lead=' . $post->ID ), 'lrti_lead_action_' . $post->ID );

		echo '<p class="lrti-utility-actions">';
		echo '<a class="lrti-btn lrti-btn--neutral lrti-btn--sm lrti-btn--block" href="' . esc_url( $resend_url ) . '"><span class="dashicons dashicons-update" aria-hidden="true"></span> ' . esc_html__( 'Resend Notification', 'little-river-trailer-inventory' ) . '</a>';
		echo '<a class="lrti-btn lrti-btn--neutral lrti-btn--sm lrti-btn--block" href="' . esc_url( $toggle_url ) . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span> ' . esc_html( $toggle_lbl ) . '</a>';
		echo '<a class="lrti-btn lrti-btn--neutral lrti-btn--sm lrti-btn--block" href="' . esc_url( $archive_url ) . '"><span class="dashicons dashicons-archive" aria-hidden="true"></span> ' . esc_html__( 'Archive Lead', 'little-river-trailer-inventory' ) . '</a>';
		echo '</p>';
	}

	/**
	 * Internal Notes metabox: structured, newest-first history + add form.
	 *
	 * @param \WP_Post $post Lead.
	 * @return void
	 */
	public function box_notes( \WP_Post $post ): void {
		?>
		<div class="lrti-note-composer">
			<label for="lrti-lead-new-note" class="lrti-composer-head"><span class="dashicons dashicons-edit" aria-hidden="true"></span> <?php esc_html_e( 'Add Internal Note', 'little-river-trailer-inventory' ); ?></label>
			<textarea id="lrti-lead-new-note" name="lrti_lead_new_note" rows="4" class="widefat" placeholder="<?php esc_attr_e( 'Add a call summary, customer update, or follow-up note…', 'little-river-trailer-inventory' ); ?>"></textarea>
			<span class="description"><?php esc_html_e( 'Private notes are visible only to dealership staff. Saved when you update the lead; previous notes are never overwritten.', 'little-river-trailer-inventory' ); ?></span>
		</div>
		<?php
		$notes = $this->leads->get_notes( $post->ID );
		if ( empty( $notes ) ) {
			echo '<div class="lrti-empty"><span class="dashicons dashicons-format-status" aria-hidden="true"></span>';
			echo '<p><strong>' . esc_html__( 'No internal notes yet.', 'little-river-trailer-inventory' ) . '</strong><br />';
			echo esc_html__( 'Add a private note to track calls, updates, and follow-up activity.', 'little-river-trailer-inventory' ) . '</p></div>';
			return;
		}
		echo '<ul class="lrti-notes-log">';
		foreach ( $notes as $note ) {
			$author = isset( $note['user'] ) ? get_userdata( (int) $note['user'] ) : false;
			$name   = $author ? $author->display_name : __( 'System', 'little-river-trailer-inventory' );
			$when   = isset( $note['time'] ) ? (int) $note['time'] : 0;
			$meta   = sprintf(
				/* translators: 1: author name, 2: date, 3: time. */
				esc_html__( '%1$s · %2$s at %3$s', 'little-river-trailer-inventory' ),
				esc_html( $name ),
				esc_html( $when ? wp_date( get_option( 'date_format' ), $when ) : '' ),
				esc_html( $when ? wp_date( get_option( 'time_format' ), $when ) : '' )
			);
			echo '<li class="lrti-note">';
			echo '<div class="lrti-note-avatar">' . get_avatar( $author ? (int) $note['user'] : 0, 32 ) . '</div>';
			echo '<div class="lrti-note-body">';
			echo '<div class="lrti-note-meta description">' . $meta . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above.
			echo '<div class="lrti-note-text">' . nl2br( esc_html( (string) ( $note['text'] ?? '' ) ) ) . '</div>';
			echo '</div>';
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Notification metabox.
	 *
	 * @param \WP_Post $post Lead.
	 * @return void
	 */
	public function box_notification( \WP_Post $post ): void {
		$status = $this->m( $post->ID, 'notify_status' );
		$badges = array(
			'sent'   => array( 'success', __( 'Sent', 'little-river-trailer-inventory' ) ),
			'failed' => array( 'danger', __( 'Failed', 'little-river-trailer-inventory' ) ),
			'pending' => array( 'warning', __( 'Pending', 'little-river-trailer-inventory' ) ),
		);
		$badge = $badges[ $status ] ?? array( 'muted', __( 'Not attempted', 'little-river-trailer-inventory' ) );
		$time  = (int) $this->m( $post->ID, 'notify_time' );

		echo '<div class="lrti-crm-card">';
		$this->crm_row( 'email-alt', __( 'Recipient', 'little-river-trailer-inventory' ), esc_html( $this->m( $post->ID, 'notify_recipient' ) ) );
		$this->crm_row(
			'yes-alt',
			__( 'Result', 'little-river-trailer-inventory' ),
			'<span class="lrti-badge lrti-badge--' . esc_attr( $badge[0] ) . '">' . esc_html( $badge[1] ) . '</span>'
		);
		if ( $time ) {
			$this->crm_row( 'clock', __( 'Sent', 'little-river-trailer-inventory' ), esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $time ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) );
		}
		echo '</div>';

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=lrti_lead_action&do=resend&lead=' . $post->ID ),
			'lrti_lead_action_' . $post->ID
		);
		echo '<p><a class="lrti-btn lrti-btn--secondary lrti-btn--block" href="' . esc_url( $url ) . '"><span class="dashicons dashicons-update" aria-hidden="true"></span> ' . esc_html__( 'Resend Notification', 'little-river-trailer-inventory' ) . '</a></p>';
	}

	/**
	 * Activity log metabox.
	 *
	 * @param \WP_Post $post Lead.
	 * @return void
	 */
	public function box_activity( \WP_Post $post ): void {
		$log = $this->leads->get_activity( $post->ID );
		if ( empty( $log ) ) {
			echo '<div class="lrti-empty"><span class="dashicons dashicons-backup" aria-hidden="true"></span>';
			echo '<p><strong>' . esc_html__( 'No activity recorded yet.', 'little-river-trailer-inventory' ) . '</strong><br />';
			echo esc_html__( 'Activity will appear here when this lead is updated.', 'little-river-trailer-inventory' ) . '</p></div>';
			return;
		}
		echo '<ul class="lrti-activity-log">';
		foreach ( array_reverse( $log ) as $entry ) {
			$when = isset( $entry['time'] ) ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $entry['time'] ), 'Y-m-d H:i' ) : '';
			$who  = ! empty( $entry['user'] ) ? get_the_author_meta( 'display_name', (int) $entry['user'] ) : __( 'System', 'little-river-trailer-inventory' );
			$act  = (string) ( $entry['action'] ?? '' );
			$cls  = $this->activity_marker_class( $act );
			echo '<li class="' . esc_attr( $cls ) . '">';
			echo '<span class="lrti-log-icon dashicons dashicons-' . esc_attr( $this->activity_icon( $act ) ) . '" aria-hidden="true"></span>';
			echo '<span class="lrti-log-when">' . esc_html( $when ) . '</span> — ' . esc_html( $act ) . ' <em>(' . esc_html( $who ) . ')</em>';
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Render a simple read-only key/value table.
	 *
	 * @param array<string, string> $rows Rows.
	 * @return void
	 */
	private function render_readonly_table( array $rows ): void {
		echo '<table class="lrti-lead-table">';
		foreach ( $rows as $label => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			echo '<tr><th scope="row">' . esc_html( (string) $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
		}
		echo '</table>';
	}

	/**
	 * Save the editable management fields.
	 *
	 * @param int      $post_id Lead ID.
	 * @param \WP_Post $post    Lead.
	 * @return void
	 */
	public function save_metaboxes( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['lrti_lead_manage_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lrti_lead_manage_nonce'] ) ), 'lrti_lead_manage_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_lrti_lead', $post_id ) ) {
			return;
		}

		$k = Leads::meta_keys();

		if ( isset( $_POST['lrti_lead_status'] ) ) {
			$this->leads->set_status( $post_id, sanitize_key( wp_unslash( $_POST['lrti_lead_status'] ) ), get_current_user_id() );
		}

		if ( isset( $_POST['lrti_lead_assigned'] ) ) {
			$old = (int) get_post_meta( $post_id, $k['assigned_user'], true );
			$new = absint( $_POST['lrti_lead_assigned'] );
			update_post_meta( $post_id, $k['assigned_user'], $new );
			if ( $new !== $old ) {
				update_post_meta( $post_id, $k['assigned_by'], get_current_user_id() );
				update_post_meta( $post_id, $k['assigned_date'], time() );
				$who = $new ? get_userdata( $new ) : false;
				$this->leads->add_activity(
					$post_id,
					$who
						/* translators: %s: assigned user display name */
						? sprintf( __( 'Assigned to %s', 'little-river-trailer-inventory' ), $who->display_name )
						: __( 'Assignment cleared', 'little-river-trailer-inventory' ),
					get_current_user_id()
				);
			}
		}

		if ( isset( $_POST['lrti_lead_notes'] ) ) {
			$old = (string) get_post_meta( $post_id, $k['internal_notes'], true );
			$new = sanitize_textarea_field( wp_unslash( $_POST['lrti_lead_notes'] ) );
			if ( $new !== $old ) {
				update_post_meta( $post_id, $k['internal_notes'], $new );
				$this->leads->add_activity( $post_id, __( 'Internal note updated', 'little-river-trailer-inventory' ), get_current_user_id() );
			}
		}

		// Follow-up tracking.
		if ( isset( $_POST['lrti_lead_last_contacted'] ) ) {
			$val = sanitize_text_field( wp_unslash( $_POST['lrti_lead_last_contacted'] ) );
			if ( '' !== $val && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $val ) ) {
				update_post_meta( $post_id, Leads::LAST_CONTACTED_META, (int) strtotime( $val . ' 12:00:00' ) );
			} elseif ( '' === $val ) {
				delete_post_meta( $post_id, Leads::LAST_CONTACTED_META );
			}
		}
		if ( isset( $_POST['lrti_lead_next_followup'] ) ) {
			$old = (string) get_post_meta( $post_id, $k['next_followup'], true );
			$val = sanitize_text_field( wp_unslash( $_POST['lrti_lead_next_followup'] ) );
			if ( '' !== $val && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $val ) ) {
				update_post_meta( $post_id, $k['next_followup'], $val );
			} elseif ( '' === $val ) {
				delete_post_meta( $post_id, $k['next_followup'] );
			}
			if ( $val !== $old ) {
				$this->leads->add_activity(
					$post_id,
					'' !== $val
						/* translators: %s: follow-up date */
						? sprintf( __( 'Follow-up set for %s', 'little-river-trailer-inventory' ), $val )
						: __( 'Follow-up cleared', 'little-river-trailer-inventory' ),
					get_current_user_id()
				);
			}
		}
		if ( isset( $_POST['lrti_lead_followup_time'] ) ) {
			$val = sanitize_text_field( wp_unslash( $_POST['lrti_lead_followup_time'] ) );
			if ( '' !== $val && preg_match( '/^\d{2}:\d{2}$/', $val ) ) {
				update_post_meta( $post_id, $k['followup_time'], $val );
			} elseif ( '' === $val ) {
				delete_post_meta( $post_id, $k['followup_time'] );
			}
		}
		if ( isset( $_POST['lrti_lead_followup_priority'] ) ) {
			$val = sanitize_key( wp_unslash( $_POST['lrti_lead_followup_priority'] ) );
			$val = array_key_exists( $val, Leads::priorities() ) ? $val : 'normal';
			update_post_meta( $post_id, $k['followup_priority'], $val );
		}
		if ( isset( $_POST['lrti_lead_followup_notes'] ) ) {
			update_post_meta( $post_id, $k['followup_notes'], sanitize_textarea_field( wp_unslash( $_POST['lrti_lead_followup_notes'] ) ) );
		}

		// Append a structured internal note (never overwrites earlier notes).
		if ( isset( $_POST['lrti_lead_new_note'] ) ) {
			$note = sanitize_textarea_field( wp_unslash( $_POST['lrti_lead_new_note'] ) );
			if ( '' !== trim( $note ) ) {
				$this->leads->add_note( $post_id, $note, get_current_user_id() );
				$this->leads->add_activity( $post_id, __( 'Note added', 'little-river-trailer-inventory' ), get_current_user_id() );
			}
		}
	}

	/* --------------------------------------------------------------------- *
	 * Menu bubble + notices
	 * --------------------------------------------------------------------- */

	/**
	 * Append a New-lead count bubble to the Leads submenu.
	 *
	 * @return void
	 */
	public function menu_bubble(): void {
		if ( ! current_user_can( $this->cap() ) ) {
			return;
		}
		global $submenu;
		if ( empty( $submenu['lrti-overview'] ) ) {
			return;
		}
		$new = $this->leads->unread_count();
		if ( $new < 1 ) {
			return;
		}
		$leads_slug = 'edit.php?post_type=' . Leads::POST_TYPE;
		foreach ( $submenu['lrti-overview'] as $i => $item ) {
			if ( isset( $item[2] ) && $item[2] === $leads_slug ) {
				$submenu['lrti-overview'][ $i ][0] .= ' <span class="update-plugins count-' . (int) $new . '"><span class="plugin-count">' . number_format_i18n( $new ) . '</span></span>';
				break;
			}
		}
	}

	/**
	 * Simple admin notices after quick/bulk actions.
	 *
	 * @return void
	 */
	public function action_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || Leads::POST_TYPE !== $screen->post_type ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only notices.
		if ( ! empty( $_GET['lrti_notice'] ) ) {
			$notice = sanitize_key( wp_unslash( $_GET['lrti_notice'] ) );
			$msg    = 'resent' === $notice ? __( 'Notification resent.', 'little-river-trailer-inventory' ) : __( 'Lead updated.', 'little-river-trailer-inventory' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
		if ( isset( $_GET['lrti_bulk'] ) ) {
			$count = absint( $_GET['lrti_bulk'] );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( /* translators: %d: count */ _n( '%d lead updated.', '%d leads updated.', $count, 'little-river-trailer-inventory' ), $count ) ) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Human "lead age" label (Today, Yesterday, N days).
	 *
	 * @param int $post_id Lead ID.
	 * @return string
	 */
	private function lead_age_label( int $post_id ): string {
		$created = (int) get_post_time( 'U', true, $post_id );
		if ( $created <= 0 ) {
			return '';
		}
		$days = (int) floor( ( time() - $created ) / DAY_IN_SECONDS );
		if ( $days <= 0 ) {
			return __( 'Today', 'little-river-trailer-inventory' );
		}
		if ( 1 === $days ) {
			return __( 'Yesterday', 'little-river-trailer-inventory' );
		}
		/* translators: %s: number of days */
		return sprintf( _n( '%s day', '%s days', $days, 'little-river-trailer-inventory' ), number_format_i18n( $days ) );
	}

	/**
	 * Follow-up alert state for a lead: overdue / today / upcoming (or null).
	 *
	 * @param int $post_id Lead ID.
	 * @return array{key:string, label:string}|null
	 */
	private function followup_alert( int $post_id ): ?array {
		$next = (string) get_post_meta( $post_id, Leads::meta_keys()['next_followup'], true );
		if ( '' === $next ) {
			return null;
		}
		// Closed leads do not raise follow-up alerts.
		if ( in_array( $this->leads->get_status( $post_id ), $this->leads->closed_statuses(), true ) ) {
			return null;
		}
		$today = current_time( 'Y-m-d' );
		if ( $next < $today ) {
			return array( 'key' => 'overdue', 'label' => __( 'Overdue', 'little-river-trailer-inventory' ) );
		}
		if ( $next === $today ) {
			return array( 'key' => 'today', 'label' => __( 'Due Today', 'little-river-trailer-inventory' ) );
		}
		return array( 'key' => 'upcoming', 'label' => __( 'Upcoming', 'little-river-trailer-inventory' ) );
	}

	/**
	 * Human label for a form type key.
	 *
	 * @param string $type Form type.
	 * @return string
	 */
	private function form_type_label( string $type ): string {
		$map = array(
			'availability'      => __( 'Check Availability', 'little-river-trailer-inventory' ),
			'information'       => __( 'Request Information', 'little-river-trailer-inventory' ),
			'similar_inventory' => __( 'Request Similar Trailers', 'little-river-trailer-inventory' ),
		);
		return $map[ $type ] ?? $type;
	}
}
