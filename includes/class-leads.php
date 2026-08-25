<?php
/**
 * Lead data model (Sprint 5.0).
 *
 * Registers the private "lrti_lead" custom post type, defines the controlled
 * lead-status list, stores/creates leads, records a lightweight activity log,
 * and provides cached New-lead counts. Leads are never public: not queryable,
 * excluded from search, no archive, no front-end URL. Lead status is a
 * dedicated meta field (not the WordPress post status) so publishing behavior
 * is never disturbed.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Leads
 */
final class Leads {

	/**
	 * Lead post type key.
	 */
	public const POST_TYPE = 'lrti_lead';

	/**
	 * Capability-schema version. Bump this whenever the lead capability list
	 * changes so the on-load recovery routine re-grants caps to roles.
	 *
	 * @var string
	 */
	public const CAP_VERSION = '2.0.1';

	/**
	 * Meta key for the lead status.
	 */
	public const STATUS_META = '_lrti_lead_status';

	/**
	 * Read/unread flag meta key ('1' read, '0'/absent unread).
	 *
	 * @var string
	 */
	public const READ_META = '_lrti_lead_read';

	/**
	 * Archived flag meta key ('1' archived).
	 *
	 * @var string
	 */
	public const ARCHIVED_META = '_lrti_lead_archived';

	/**
	 * Last-contacted timestamp meta key.
	 *
	 * @var string
	 */
	public const LAST_CONTACTED_META = '_lrti_lead_last_contacted';

	/**
	 * Transient key for the cached New-lead count.
	 */
	private const COUNT_TRANSIENT = 'lrti_new_lead_count';

	/**
	 * Transient for the cached unread-lead count.
	 *
	 * @var string
	 */
	private const UNREAD_TRANSIENT = 'lrti_unread_lead_count';

	/**
	 * Attach hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Register the private Lead post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'               => __( 'Leads', 'little-river-trailer-inventory' ),
			'singular_name'      => __( 'Lead', 'little-river-trailer-inventory' ),
			'menu_name'          => __( 'Leads', 'little-river-trailer-inventory' ),
			'name_admin_bar'     => __( 'Lead', 'little-river-trailer-inventory' ),
			'all_items'          => __( 'Leads', 'little-river-trailer-inventory' ),
			'add_new'            => __( 'Add Lead', 'little-river-trailer-inventory' ),
			'add_new_item'       => __( 'Add New Lead', 'little-river-trailer-inventory' ),
			'new_item'           => __( 'New Lead', 'little-river-trailer-inventory' ),
			'edit_item'          => __( 'Lead', 'little-river-trailer-inventory' ),
			'view_item'          => __( 'View Lead', 'little-river-trailer-inventory' ),
			'search_items'       => __( 'Search Leads', 'little-river-trailer-inventory' ),
			'not_found'          => __( 'No leads found.', 'little-river-trailer-inventory' ),
			'not_found_in_trash' => __( 'No leads found in Trash.', 'little-river-trailer-inventory' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'show_ui'             => true,
				// Menu is registered explicitly by Admin::register_menu() as a
				// single submenu under Trailer Inventory. Keep this false so
				// WordPress does not add a second (duplicate) Leads submenu.
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'menu_position'       => 27,
				'supports'            => array( 'title' ),
				'map_meta_cap'        => true,
				'capability_type'     => array( 'lrti_lead', 'lrti_leads' ),
				'capabilities'        => self::capabilities(),
			)
		);
	}

	/**
	 * The capability map for the Lead post type.
	 *
	 * @return array<string, string>
	 */
	public static function capabilities(): array {
		return array(
			'edit_post'              => 'edit_lrti_lead',
			'read_post'              => 'read_lrti_lead',
			'delete_post'            => 'delete_lrti_lead',
			'edit_posts'             => 'edit_lrti_leads',
			'edit_others_posts'      => 'edit_others_lrti_leads',
			'publish_posts'          => 'publish_lrti_leads',
			'read_private_posts'     => 'read_private_lrti_leads',
			'delete_posts'           => 'delete_lrti_leads',
			'delete_others_posts'    => 'delete_others_lrti_leads',
			'delete_private_posts'   => 'delete_private_lrti_leads',
			'delete_published_posts' => 'delete_published_lrti_leads',
			'edit_private_posts'     => 'edit_private_lrti_leads',
			'edit_published_posts'   => 'edit_published_lrti_leads',
			'create_posts'           => 'edit_lrti_leads',
		);
	}

	/**
	 * Grant every lead capability to the given roles. Idempotent — add_cap()
	 * is safe to call repeatedly and never removes unrelated capabilities.
	 *
	 * @param string[] $roles Role names to receive lead capabilities.
	 * @return void
	 */
	public static function grant_capabilities( array $roles = array( 'administrator', 'editor' ) ): void {
		$caps = self::all_capabilities();
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				if ( ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * All lead capabilities as a flat list (for granting to roles).
	 *
	 * @return string[]
	 */
	public static function all_capabilities(): array {
		return array_values( array_unique( array_values( self::capabilities() ) ) );
	}

	/**
	 * The controlled lead-status list (key => label). Filterable.
	 *
	 * @return array<string, string>
	 */
	public static function statuses(): array {
		return (array) apply_filters(
			'lrti_lead_statuses',
			array(
				'new'            => __( 'New', 'little-river-trailer-inventory' ),
				'contacted'      => __( 'Contacted', 'little-river-trailer-inventory' ),
				'left-voicemail' => __( 'Left Voicemail', 'little-river-trailer-inventory' ),
				'follow-up'      => __( 'Follow-Up Needed', 'little-river-trailer-inventory' ),
				'qualified'      => __( 'Qualified', 'little-river-trailer-inventory' ),
				'quote-sent'     => __( 'Quote Sent', 'little-river-trailer-inventory' ),
				'appointment'    => __( 'Appointment Scheduled', 'little-river-trailer-inventory' ),
				'negotiating'    => __( 'Negotiating', 'little-river-trailer-inventory' ),
				'deposit'        => __( 'Deposit Received', 'little-river-trailer-inventory' ),
				'won'            => __( 'Sold', 'little-river-trailer-inventory' ),
				'lost'           => __( 'Lost', 'little-river-trailer-inventory' ),
				'spam'           => __( 'Spam', 'little-river-trailer-inventory' ),
			)
		);
	}

	/**
	 * Follow-up reminder priorities.
	 *
	 * @return array<string, string> slug => label.
	 */
	public static function priorities(): array {
		return array(
			'low'    => __( 'Low', 'little-river-trailer-inventory' ),
			'normal' => __( 'Normal', 'little-river-trailer-inventory' ),
			'high'   => __( 'High', 'little-river-trailer-inventory' ),
			'urgent' => __( 'Urgent', 'little-river-trailer-inventory' ),
		);
	}

	/**
	 * Validate a status key.
	 *
	 * @param string $status Candidate status.
	 * @return string A valid status ('new' if invalid).
	 */
	public static function valid_status( string $status ): string {
		$status = sanitize_key( $status );
		return array_key_exists( $status, self::statuses() ) ? $status : 'new';
	}

	/**
	 * The lead meta keys we persist (used for storage, export, and erase).
	 *
	 * @return array<string, string>
	 */
	public static function meta_keys(): array {
		return array(
			'form_type'         => '_lrti_lead_form_type',
			'name'              => '_lrti_lead_name',
			'email'             => '_lrti_lead_email',
			'phone'             => '_lrti_lead_phone',
			'preferred_contact' => '_lrti_lead_preferred_contact',
			'message'           => '_lrti_lead_message',
			'consent'           => '_lrti_lead_consent',
			'consent_text'      => '_lrti_lead_consent_text',
			'consent_time'      => '_lrti_lead_consent_time',
			'trailer_id'        => '_lrti_lead_trailer_id',
			'trailer_title'     => '_lrti_lead_trailer_title',
			'stock_number'      => '_lrti_lead_stock_number',
			'trailer_url'       => '_lrti_lead_trailer_url',
			'source_url'        => '_lrti_lead_source_url',
			'referrer'          => '_lrti_lead_referrer',
			'utm_source'        => '_lrti_lead_utm_source',
			'utm_medium'        => '_lrti_lead_utm_medium',
			'utm_campaign'      => '_lrti_lead_utm_campaign',
			'utm_term'          => '_lrti_lead_utm_term',
			'utm_content'       => '_lrti_lead_utm_content',
			'assigned_user'     => '_lrti_lead_assigned_user',
			'internal_notes'    => '_lrti_lead_internal_notes',
			'next_followup'     => '_lrti_lead_next_followup',
			'followup_notes'    => '_lrti_lead_followup_notes',
			'followup_time'     => '_lrti_lead_followup_time',
			'followup_priority' => '_lrti_lead_followup_priority',
			'assigned_by'       => '_lrti_lead_assigned_by',
			'assigned_date'     => '_lrti_lead_assigned_date',
			'notes_log'         => '_lrti_lead_notes_log',
			'notify_recipient'  => '_lrti_lead_notify_recipient',
			'notify_status'     => '_lrti_lead_notify_status',
			'notify_time'       => '_lrti_lead_notify_time',
			'ip_hash'           => '_lrti_lead_ip_hash',
			'user_agent'        => '_lrti_lead_user_agent',
			'submitted'         => '_lrti_lead_submitted',
		);
	}

	/**
	 * Create a lead record from validated, sanitized data.
	 *
	 * @param array<string, mixed> $data Validated lead data.
	 * @return int|\WP_Error Lead post ID on success.
	 */
	public function create_lead( array $data ) {
		$name    = isset( $data['name'] ) ? (string) $data['name'] : '';
		$trailer = isset( $data['trailer_title'] ) ? (string) $data['trailer_title'] : '';

		$title = trim( sprintf( '%1$s — %2$s', $name !== '' ? $name : __( 'Inquiry', 'little-river-trailer-inventory' ), $trailer ) );

		/**
		 * Fires before a lead is created.
		 *
		 * @param array<string, mixed> $data Lead data.
		 */
		do_action( 'lrti_before_lead_created', $data );

		$lead_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title !== '' ? $title : __( 'Trailer Inquiry', 'little-river-trailer-inventory' ),
			),
			true
		);

		if ( is_wp_error( $lead_id ) ) {
			return $lead_id;
		}

		$lead_id = (int) $lead_id;
		$keys    = self::meta_keys();

		foreach ( $keys as $field => $meta_key ) {
			if ( array_key_exists( $field, $data ) ) {
				update_post_meta( $lead_id, $meta_key, $data[ $field ] );
			}
		}

		update_post_meta( $lead_id, self::STATUS_META, self::valid_status( (string) lrti_get_setting( 'default_inquiry_status', 'new' ) ) );
		update_post_meta( $lead_id, self::READ_META, '0' );
		update_post_meta( $lead_id, self::meta_keys()['submitted'], time() );

		$this->add_activity( $lead_id, __( 'Lead created', 'little-river-trailer-inventory' ), 0 );
		$this->clear_count_cache();

		/**
		 * Fires after a lead is created.
		 *
		 * @param int                  $lead_id Lead ID.
		 * @param array<string, mixed> $data    Lead data.
		 */
		do_action( 'lrti_after_lead_created', $lead_id, $data );

		return $lead_id;
	}

	/**
	 * Get a lead's status.
	 *
	 * @param int $lead_id Lead ID.
	 * @return string
	 */
	public function get_status( int $lead_id ): string {
		return self::valid_status( (string) get_post_meta( $lead_id, self::STATUS_META, true ) );
	}

	/**
	 * Change a lead's status (records activity + fires a hook).
	 *
	 * @param int    $lead_id Lead ID.
	 * @param string $status  New status.
	 * @param int    $user_id Acting user ID (0 = system).
	 * @return bool
	 */
	public function set_status( int $lead_id, string $status, int $user_id = 0 ): bool {
		$status = self::valid_status( $status );
		$old    = $this->get_status( $lead_id );
		if ( $status === $old ) {
			return true;
		}

		update_post_meta( $lead_id, self::STATUS_META, $status );
		update_post_meta( $lead_id, '_lrti_lead_status_time', time() );

		$labels = self::statuses();
		$this->add_activity(
			$lead_id,
			sprintf(
				/* translators: 1: old status, 2: new status */
				__( 'Status changed from %1$s to %2$s', 'little-river-trailer-inventory' ),
				$labels[ $old ] ?? $old,
				$labels[ $status ] ?? $status
			),
			$user_id
		);
		$this->clear_count_cache();

		/**
		 * Fires when a lead status changes.
		 *
		 * @param int    $lead_id Lead ID.
		 * @param string $status  New status.
		 * @param string $old     Previous status.
		 */
		do_action( 'lrti_lead_status_changed', $lead_id, $status, $old );

		return true;
	}

	/**
	 * Append an entry to the lead activity log (kept lightweight; last 50).
	 *
	 * @param int    $lead_id Lead ID.
	 * @param string $action  Human-readable action.
	 * @param int    $user_id Acting user ID.
	 * @return void
	 */
	public function add_activity( int $lead_id, string $action, int $user_id = 0 ): void {
		$log   = get_post_meta( $lead_id, '_lrti_lead_log', true );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array(
			'action' => $action,
			'time'   => time(),
			'user'   => $user_id > 0 ? $user_id : get_current_user_id(),
		);
		if ( count( $log ) > 50 ) {
			$log = array_slice( $log, -50 );
		}
		update_post_meta( $lead_id, '_lrti_lead_log', $log );
	}

	/**
	 * Get the activity log for a lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_activity( int $lead_id ): array {
		$log = get_post_meta( $lead_id, '_lrti_lead_log', true );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Count leads by status (single grouped query).
	 *
	 * @return array<string, int>
	 */
	public function counts_by_status(): array {
		global $wpdb;

		$counts = array_fill_keys( array_keys( self::statuses() ), 0 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS status, COUNT(*) AS total
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish'
				 GROUP BY pm.meta_value",
				self::STATUS_META,
				self::POST_TYPE
			)
		);

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$key = self::valid_status( (string) $row->status );
				$counts[ $key ] = (int) $row->total;
			}
		}

		return $counts;
	}

	/**
	 * Count leads created today (site timezone).
	 *
	 * @return int
	 */
	public function count_today(): int {
		$q = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'year'  => (int) current_time( 'Y' ),
						'month' => (int) current_time( 'n' ),
						'day'   => (int) current_time( 'j' ),
					),
				),
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * Count leads whose status changed to $status during the current month.
	 * Accurate for changes made from 2.7.0 onward (uses the recorded change time).
	 *
	 * @param string $status Status slug.
	 * @return int
	 */
	public function count_status_changed_this_month( string $status ): int {
		global $wpdb;
		$start = (int) strtotime( current_time( 'Y-m-01 00:00:00' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$wpdb->postmeta} s
				 INNER JOIN {$wpdb->postmeta} t ON t.post_id = s.post_id AND t.meta_key = '_lrti_lead_status_time'
				 INNER JOIN {$wpdb->posts} p ON p.ID = s.post_id
				 WHERE s.meta_key = %s AND s.meta_value = %s
				 AND p.post_type = %s AND p.post_status = 'publish'
				 AND CAST( t.meta_value AS UNSIGNED ) >= %d",
				self::STATUS_META,
				$status,
				self::POST_TYPE,
				$start
			)
		);
		return (int) $count;
	}

	/**
	 * Top assigned salesperson by open (non-closed) lead count.
	 *
	 * @return array{user_id:int, total:int}|null
	 */
	public function top_assignee(): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT pm.meta_value AS uid, COUNT(*) AS total
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND pm.meta_value <> '' AND pm.meta_value <> '0'
				 AND p.post_type = %s AND p.post_status = 'publish'
				 GROUP BY pm.meta_value
				 ORDER BY total DESC
				 LIMIT 1",
				self::meta_keys()['assigned_user'],
				self::POST_TYPE
			)
		);
		if ( ! $row ) {
			return null;
		}
		return array( 'user_id' => (int) $row->uid, 'total' => (int) $row->total );
	}

	/**
	 * Recent activity across leads (bounded: newest 20 leads, newest 8 entries).
	 *
	 * @param int $limit Max entries to return.
	 * @return array<int, array{time:int, user:int, action:string, lead_id:int, lead_title:string}>
	 */
	public function recent_activity( int $limit = 8 ): array {
		$leads = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => 20,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);
		$entries = array();
		foreach ( $leads as $lead ) {
			$log = get_post_meta( $lead->ID, '_lrti_lead_log', true );
			if ( ! is_array( $log ) ) {
				continue;
			}
			foreach ( $log as $e ) {
				$entries[] = array(
					'time'       => (int) ( $e['time'] ?? 0 ),
					'user'       => (int) ( $e['user'] ?? 0 ),
					'action'     => (string) ( $e['action'] ?? '' ),
					'lead_id'    => (int) $lead->ID,
					'lead_title' => get_the_title( $lead->ID ),
				);
			}
		}
		usort(
			$entries,
			static function ( $a, $b ) {
				return $b['time'] <=> $a['time'];
			}
		);
		return array_slice( $entries, 0, max( 1, $limit ) );
	}

	/**
	 * Count leads created in the current calendar month.
	 *
	 * @return int
	 */
	public function count_this_month(): int {
		$q = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'date_query'     => array(
					array(
						'year'  => (int) gmdate( 'Y' ),
						'month' => (int) gmdate( 'n' ),
					),
				),
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * Cached count of New leads (for the menu bubble).
	 *
	 * @return int
	 */
	public function new_count(): int {
		$cached = get_transient( self::COUNT_TRANSIENT );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$counts = $this->counts_by_status();
		$new    = isset( $counts['new'] ) ? (int) $counts['new'] : 0;
		set_transient( self::COUNT_TRANSIENT, $new, 5 * MINUTE_IN_SECONDS );
		return $new;
	}

	/**
	 * Count unread, non-archived leads (used for the menu badge).
	 *
	 * @return int
	 */
	public function unread_count(): int {
		$cached = get_transient( self::UNREAD_TRANSIENT );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$q = new \WP_Query(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					'relation' => 'AND',
					array(
						'key'     => self::READ_META,
						'value'   => '1',
						'compare' => '!=',
					),
					array(
						'key'     => self::ARCHIVED_META,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		// Leads created before this feature have no READ_META; treat as read to
		// avoid a misleading spike, so only compare against an explicit '0'.
		$count = 0;
		foreach ( $q->posts as $pid ) {
			if ( '0' === (string) get_post_meta( (int) $pid, self::READ_META, true ) ) {
				++$count;
			}
		}
		set_transient( self::UNREAD_TRANSIENT, $count, 5 * MINUTE_IN_SECONDS );
		return $count;
	}

	/**
	 * Mark a lead read or unread.
	 *
	 * @param int  $lead_id Lead ID.
	 * @param bool $read    True to mark read.
	 * @return void
	 */
	public function set_read( int $lead_id, bool $read ): void {
		update_post_meta( $lead_id, self::READ_META, $read ? '1' : '0' );
		delete_transient( self::UNREAD_TRANSIENT );
	}

	/**
	 * Archive or un-archive a lead (never deletes the record).
	 *
	 * @param int  $lead_id  Lead ID.
	 * @param bool $archived True to archive.
	 * @return void
	 */
	public function set_archived( int $lead_id, bool $archived ): void {
		if ( $archived ) {
			update_post_meta( $lead_id, self::ARCHIVED_META, '1' );
			$this->add_activity( $lead_id, __( 'Archived', 'little-river-trailer-inventory' ) );
		} else {
			delete_post_meta( $lead_id, self::ARCHIVED_META );
			$this->add_activity( $lead_id, __( 'Unarchived', 'little-river-trailer-inventory' ) );
		}
		$this->clear_count_cache();
		delete_transient( self::UNREAD_TRANSIENT );
	}

	/**
	 * Statuses that stop a lead from being "overdue" (closed states).
	 *
	 * @return string[]
	 */
	public static function closed_statuses(): array {
		return array( 'won', 'lost', 'spam' );
	}

	/**
	 * Is this lead an overdue follow-up? Overdue = a Next Follow-Up date before
	 * today AND the lead is not in a closed state (Sold/Lost/Spam).
	 *
	 * @param int $lead_id Lead ID.
	 * @return bool
	 */
	public function is_overdue( int $lead_id ): bool {
		$date = (string) get_post_meta( $lead_id, self::meta_keys()['next_followup'], true );
		if ( '' === $date ) {
			return false;
		}
		if ( in_array( $this->get_status( $lead_id ), self::closed_statuses(), true ) ) {
			return false;
		}
		$today = current_time( 'Y-m-d' );
		return $date < $today;
	}

	/**
	 * Count leads whose follow-up is due/overdue (today or earlier, not closed).
	 *
	 * @return int
	 */
	public function followups_due_count(): int {
		$today = current_time( 'Y-m-d' );
		$q     = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => self::meta_keys()['next_followup'],
						'value'   => $today,
						'compare' => '<=',
						'type'    => 'DATE',
					),
					array(
						'key'     => self::STATUS_META,
						'value'   => self::closed_statuses(),
						'compare' => 'NOT IN',
					),
					array(
						'key'     => self::ARCHIVED_META,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		return (int) $q->post_count;
	}

	/**
	 * Append a structured internal note (never overwrites earlier notes).
	 *
	 * @param int    $lead_id Lead ID.
	 * @param string $text    Note text (already trimmed).
	 * @param int    $user_id Author user ID.
	 * @return void
	 */
	public function add_note( int $lead_id, string $text, int $user_id ): void {
		$text = trim( $text );
		if ( '' === $text ) {
			return;
		}
		$log = get_post_meta( $lead_id, self::meta_keys()['notes_log'], true );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = array(
			'text' => $text,
			'user' => $user_id,
			'time' => time(),
		);
		update_post_meta( $lead_id, self::meta_keys()['notes_log'], $log );
	}

	/**
	 * Get structured internal notes, newest first.
	 *
	 * @param int $lead_id Lead ID.
	 * @return array<int, array{text:string,user:int,time:int}>
	 */
	public function get_notes( int $lead_id ): array {
		$log = get_post_meta( $lead_id, self::meta_keys()['notes_log'], true );
		if ( ! is_array( $log ) ) {
			return array();
		}
		return array_reverse( $log );
	}

	/**
	 * Invalidate the cached New-lead count.
	 *
	 * @return void
	 */
	public function clear_count_cache(): void {
		delete_transient( self::COUNT_TRANSIENT );
		delete_transient( self::UNREAD_TRANSIENT );
	}
}
