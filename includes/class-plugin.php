<?php
/**
 * Main plugin class.
 *
 * This is the "conductor" of the plugin. It is a singleton (only one instance
 * ever exists) and its job is to load and wire up every feature area at the
 * right time using WordPress hooks.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * The single shared instance of this class.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Admin handler (menus and settings glue).
	 *
	 * @var Admin|null
	 */
	private ?Admin $admin = null;

	/**
	 * Settings handler.
	 *
	 * @var Settings|null
	 */
	private ?Settings $settings = null;

	/**
	 * Post type handler.
	 *
	 * @var PostTypes|null
	 */
	private ?PostTypes $post_types = null;

	/**
	 * Taxonomies handler.
	 *
	 * @var Taxonomies|null
	 */
	private ?Taxonomies $taxonomies = null;

	/**
	 * Meta fields handler.
	 *
	 * @var MetaFields|null
	 */
	private ?MetaFields $meta_fields = null;

	/**
	 * Featured toggle admin component.
	 *
	 * @var FeaturedAdmin|null
	 */
	private ?FeaturedAdmin $featured_admin = null;

	/**
	 * Template loader (front end).
	 *
	 * @var TemplateLoader|null
	 */
	private ?TemplateLoader $template_loader = null;

	/**
	 * Inventory query adjustments (front end).
	 *
	 * @var InventoryQuery|null
	 */
	private ?InventoryQuery $inventory_query = null;

	/**
	 * Front-end asset loader.
	 *
	 * @var Frontend|null
	 */
	private ?Frontend $frontend = null;

	/**
	 * Homepage trailer-type category cards.
	 *
	 * @var HomeCategories|null
	 */
	private ?HomeCategories $home_categories = null;

	/**
	 * In-admin User Guide.
	 *
	 * @var UserGuide|null
	 */
	private ?UserGuide $user_guide = null;

	/**
	 * SEO output handler.
	 *
	 * @var Seo|null
	 */
	private ?Seo $seo = null;

	/**
	 * Structured data (JSON-LD) handler.
	 *
	 * @var Schema|null
	 */
	private ?Schema $schema = null;

	/**
	 * Leads model.
	 *
	 * @var Leads|null
	 */
	private ?Leads $leads = null;

	/**
	 * Notifications.
	 *
	 * @var Notifications|null
	 */
	private ?Notifications $notifications = null;

	/**
	 * Inquiry forms.
	 *
	 * @var Inquiry|null
	 */
	private ?Inquiry $inquiry = null;

	/**
	 * Leads admin.
	 *
	 * @var LeadsAdmin|null
	 */
	private ?LeadsAdmin $leads_admin = null;

	/**
	 * Lead CSV export handler.
	 *
	 * @var LeadExport|null
	 */
	private ?LeadExport $lead_export = null;

	/**
	 * Privacy integration.
	 *
	 * @var Privacy|null
	 */
	private ?Privacy $privacy = null;

	/**
	 * Lead retention cleanup.
	 *
	 * @var LeadRetention|null
	 */
	private ?LeadRetention $retention = null;

	/**
	 * Shared inventory filters engine.
	 *
	 * @var Filters|null
	 */
	private ?Filters $filters = null;

	/**
	 * AJAX handler.
	 *
	 * @var Ajax|null
	 */
	private ?Ajax $ajax = null;

	/**
	 * Shortcodes handler.
	 *
	 * @var Shortcodes|null
	 */
	private ?Shortcodes $shortcodes = null;

	/**
	 * Private constructor so nobody can create this class with "new".
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of the singleton.
	 */
	private function __clone() {}

	/**
	 * Prevent un-serializing of the singleton.
	 */
	public function __wakeup(): void {
		throw new \RuntimeException( 'Cannot unserialize a singleton.' );
	}

	/**
	 * Get the one shared instance, creating it on first call.
	 *
	 * @return Plugin
	 */
	/**
	 * Access the shared inventory filters engine.
	 *
	 * @return Filters|null
	 */
	public function filters(): ?Filters {
		return $this->filters;
	}

	/**
	 * Access the inquiry forms handler.
	 *
	 * @return Inquiry|null
	 */
	public function inquiry(): ?Inquiry {
		return $this->inquiry;
	}

	/**
	 * Access the leads model.
	 *
	 * @return Leads|null
	 */
	public function leads(): ?Leads {
		return $this->leads;
	}

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Start the plugin: load features and attach hooks.
	 *
	 * @return void
	 */
	public function run(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Content types and taxonomies must exist on both front end and admin,
		// so they are registered unconditionally.
		$this->post_types = new PostTypes();
		$this->post_types->register_hooks();

		$this->taxonomies = new Taxonomies();
		$this->taxonomies->register_hooks();

		// Front-end components (their hooks self-guard to front-end views).
		$this->template_loader = new TemplateLoader();
		$this->template_loader->register_hooks();

		// Shared filters engine (used by archive query, AJAX, and shortcodes).
		$this->filters = new Filters();
		$this->filters->register_hooks();

		$this->inventory_query = new InventoryQuery( $this->filters );
		$this->inventory_query->register_hooks();

		$this->ajax = new Ajax( $this->filters );
		$this->ajax->register_hooks();

		$this->shortcodes = new Shortcodes( $this->filters );
		$this->shortcodes->register_hooks();

		$this->frontend = new Frontend();
		$this->frontend->register_hooks();

		$this->seo = new Seo();
		$this->seo->register_hooks();

		$this->schema = new Schema();
		$this->schema->register_hooks();

		// Dynamic homepage trailer-type category cards (Sprint 2.9.6).
		$this->home_categories = new HomeCategories();
		$this->home_categories->register_hooks();

		// Lead generation (Sprint 5.0).
		$this->leads = new Leads();
		$this->leads->register_hooks();

		$this->notifications = new Notifications( $this->leads );

		$this->inquiry = new Inquiry( $this->leads, $this->notifications );
		$this->inquiry->register_hooks();

		$this->retention = new LeadRetention();
		$this->retention->register_hooks();

		$this->privacy = new Privacy();
		$this->privacy->register_hooks();

		if ( is_admin() ) {
			$this->leads_admin = new LeadsAdmin( $this->leads, $this->notifications );
			$this->leads_admin->register_hooks();

			$this->lead_export = new LeadExport( $this->leads );
			$this->lead_export->register_hooks();
		}

		// Settings (registers on admin_init internally).
		$this->settings = new Settings();
		$this->settings->register_hooks();

		// Admin-only features.
		if ( is_admin() ) {
			$this->admin = new Admin( $this->settings );
			$this->admin->register_hooks();

			$this->meta_fields = new MetaFields();
			$this->meta_fields->register_hooks();

			$this->featured_admin = new FeaturedAdmin();
			$this->featured_admin->register_hooks();

			// Built-in User Guide / training center (Sprint 2.9.16).
			$this->user_guide = new UserGuide();
			$this->user_guide->register_hooks();

			// Safe upgrade routine: seeds new default terms on existing
			// installs and recovers lead capabilities without requiring reinstall.
			add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
			add_action( 'admin_notices', array( $this, 'lead_caps_recovered_notice' ) );
		}
	}

	/**
	 * Version-upgrade routine.
	 *
	 * Compares the stored default-terms version with the current one. When they
	 * differ (including the first time, when nothing is stored), it re-runs the
	 * safe term seeding and records the new version. Because seeding checks
	 * term_exists() before every insert, this never creates duplicates and
	 * never touches terms an administrator has already customized.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		// Lead-capability recovery. Roles are stored in the database and are only
		// set on activation, so an install updated without reactivation can be
		// missing the lead caps (locking admins out of the Leads screen). This
		// re-grants them idempotently on a normal admin request — no reactivation.
		$caps_stored = (string) get_option( 'lrti_lead_caps_version', '0' );
		if ( version_compare( $caps_stored, Leads::CAP_VERSION, '<' ) ) {
			Leads::grant_capabilities( array( 'administrator', 'editor' ) );
			update_option( 'lrti_lead_caps_version', Leads::CAP_VERSION );
			set_transient( 'lrti_lead_caps_recovered', 1, 5 * MINUTE_IN_SECONDS );

			// Roles are cached on the already-loaded current user; rebuild their
			// capabilities so the fix takes effect on THIS request (no reload).
			$current = wp_get_current_user();
			if ( $current && $current->exists() ) {
				$current->get_role_caps();
			}
		}

		if ( null === $this->taxonomies ) {
			return;
		}

		$stored = (string) get_option( 'lrti_terms_version', '0' );

		if ( version_compare( $stored, Taxonomies::TERMS_VERSION, '<' ) ) {
			$this->taxonomies->seed_default_terms();
			update_option( 'lrti_terms_version', Taxonomies::TERMS_VERSION );
		}
	}

	/**
	 * One-time admin notice shown right after lead-permission recovery.
	 *
	 * @return void
	 */
	public function lead_caps_recovered_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! get_transient( 'lrti_lead_caps_recovered' ) ) {
			return;
		}
		delete_transient( 'lrti_lead_caps_recovered' );
		echo '<div class="notice notice-success is-dismissible"><p>'
			. esc_html__( 'Trailer Inventory lead permissions were updated successfully.', 'little-river-trailer-inventory' )
			. '</p></div>';
	}

	/**
	 * Load the plugin's translation files from the /languages folder.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			LRTI_TEXT_DOMAIN,
			false,
			dirname( LRTI_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Convenience accessor for the Settings object.
	 *
	 * @return Settings|null
	 */
	public function settings(): ?Settings {
		return $this->settings;
	}
}
