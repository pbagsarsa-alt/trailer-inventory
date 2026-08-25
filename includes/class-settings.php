<?php
/**
 * Settings registration and rendering.
 *
 * This class uses the WordPress "Settings API", the official framework for
 * saving admin options securely. The Settings API automatically handles the
 * security nonce and the save-and-redirect flow for us; we just describe the
 * fields and provide sanitizing callbacks.
 *
 * In Phase 1 we register a small set of core settings. Later phases will add
 * more sections (sold-trailer behavior, colors, structured data, etc.).
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
final class Settings {

	/**
	 * The option name that stores all settings as one array.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'lrti_settings';

	/**
	 * The settings group name used by the Settings API.
	 *
	 * @var string
	 */
	private const OPTION_GROUP = 'lrti_settings_group';

	/**
	 * Attach WordPress hooks for the settings system.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the setting, its sections, and its fields with WordPress.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// Tell WordPress about our single option and how to clean it before saving.
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => lrti_get_default_settings(),
			)
		);

		// --- Section: Dealership information -------------------------------
		add_settings_section(
			'lrti_section_business',
			__( 'Dealership Information', 'little-river-trailer-inventory' ),
			array( $this, 'render_business_section_intro' ),
			'lrti-settings'
		);

		$this->add_text_field( 'dealership_name', __( 'Dealership Name', 'little-river-trailer-inventory' ), 'lrti_section_business' );
		$this->add_text_field( 'dealership_address', __( 'Address', 'little-river-trailer-inventory' ), 'lrti_section_business' );
		$this->add_text_field( 'dealership_phone', __( 'Phone', 'little-river-trailer-inventory' ), 'lrti_section_business' );
		$this->add_text_field(
			'dealership_email',
			__( 'Business Email', 'little-river-trailer-inventory' ),
			'lrti_section_business',
			'email'
		);

		// --- Section: Leads -------------------------------------------------
		add_settings_section(
			'lrti_section_leads',
			__( 'Leads & Notifications', 'little-river-trailer-inventory' ),
			array( $this, 'render_leads_section_intro' ),
			'lrti-settings'
		);

		$this->add_text_field(
			'lead_notification_email',
			__( 'Lead Notification Email', 'little-river-trailer-inventory' ),
			'lrti_section_leads',
			'email'
		);

		$this->add_checkbox_field( 'enable_inquiry_forms', __( 'Enable inquiry forms', 'little-river-trailer-inventory' ), 'lrti_section_leads', __( 'Show the Check Availability / Request Information forms on trailer pages.', 'little-river-trailer-inventory' ) );
		$this->add_checkbox_field( 'send_customer_confirmation', __( 'Send customer confirmation', 'little-river-trailer-inventory' ), 'lrti_section_leads', __( 'Email the customer a confirmation when they provide a valid email address.', 'little-river-trailer-inventory' ) );
		$this->add_text_field( 'customer_confirmation_subject', __( 'Customer Confirmation Subject', 'little-river-trailer-inventory' ), 'lrti_section_leads' );
		$this->add_textarea_field( 'customer_confirmation_message', __( 'Customer Confirmation Message', 'little-river-trailer-inventory' ), 'lrti_section_leads' );
		$this->add_textarea_field( 'consent_text', __( 'Consent Text', 'little-river-trailer-inventory' ), 'lrti_section_leads' );
		$this->add_text_field( 'contact_form_heading', __( 'Contact Form Heading', 'little-river-trailer-inventory' ), 'lrti_section_leads' );
		$this->add_textarea_field( 'contact_form_description', __( 'Contact Form Description', 'little-river-trailer-inventory' ), 'lrti_section_leads' );
		$this->add_textarea_field( 'inquiry_success_message', __( 'Success Message', 'little-river-trailer-inventory' ), 'lrti_section_leads' );
		$this->add_textarea_field( 'inquiry_error_message', __( 'General Error Message', 'little-river-trailer-inventory' ), 'lrti_section_leads' );

		$this->add_select_field(
			'lead_retention_days',
			__( 'Lead Retention Period', 'little-river-trailer-inventory' ),
			'lrti_section_leads',
			array(
				'0'   => __( 'Keep indefinitely', 'little-river-trailer-inventory' ),
				'30'  => __( '30 days', 'little-river-trailer-inventory' ),
				'90'  => __( '90 days', 'little-river-trailer-inventory' ),
				'180' => __( '180 days', 'little-river-trailer-inventory' ),
				'365' => __( '365 days', 'little-river-trailer-inventory' ),
			)
		);

		$this->add_checkbox_field( 'enable_honeypot', __( 'Enable honeypot spam protection', 'little-river-trailer-inventory' ), 'lrti_section_leads' );
		$this->add_number_field( 'min_completion_time', __( 'Minimum Completion Time (seconds)', 'little-river-trailer-inventory' ), 'lrti_section_leads' );
		$this->add_number_field( 'duplicate_window_seconds', __( 'Duplicate Submission Window (seconds)', 'little-river-trailer-inventory' ), 'lrti_section_leads' );
		$this->add_select_field(
			'default_inquiry_status',
			__( 'Default Inquiry Status', 'little-river-trailer-inventory' ),
			'lrti_section_leads',
			array(
				'new'         => __( 'New', 'little-river-trailer-inventory' ),
				'contacted'   => __( 'Contacted', 'little-river-trailer-inventory' ),
				'qualified'   => __( 'Qualified', 'little-river-trailer-inventory' ),
				'appointment' => __( 'Appointment Scheduled', 'little-river-trailer-inventory' ),
			)
		);
		$this->add_checkbox_field( 'store_visitor_ip', __( 'Store visitor IP address', 'little-river-trailer-inventory' ), 'lrti_section_leads', __( 'Store a hashed visitor identifier (never the raw IP) for spam protection.', 'little-river-trailer-inventory' ) );
		$this->add_number_field( 'rate_limit_window', __( 'Rate Limit Window (seconds)', 'little-river-trailer-inventory' ), 'lrti_section_leads' );
		$this->add_number_field( 'rate_limit_max', __( 'Maximum Submissions Per Window', 'little-river-trailer-inventory' ), 'lrti_section_leads' );
		$this->add_number_field( 'spam_delete_days', __( 'Delete Spam Leads After (days)', 'little-river-trailer-inventory' ), 'lrti_section_leads' );

		// --- Section: Data & uninstall -------------------------------------
		add_settings_section(
			'lrti_section_data',
			__( 'Data & Uninstall', 'little-river-trailer-inventory' ),
			array( $this, 'render_data_section_intro' ),
			'lrti-settings'
		);

		add_settings_field(
			'remove_all_data_on_uninstall',
			__( 'Remove all data on uninstall', 'little-river-trailer-inventory' ),
			array( $this, 'render_remove_data_field' ),
			'lrti-settings',
			'lrti_section_data'
		);
	}

	/**
	 * Helper to register a simple text (or email) field.
	 *
	 * @param string $key     The settings key.
	 * @param string $label   The visible field label.
	 * @param string $section The section ID to place it in.
	 * @param string $type    HTML input type: "text" or "email".
	 * @return void
	 */
	private function add_text_field( string $key, string $label, string $section, string $type = 'text' ): void {
		add_settings_field(
			$key,
			$label,
			function () use ( $key, $type ): void {
				$this->render_text_field( $key, $type );
			},
			'lrti-settings',
			$section
		);
	}

	/**
	 * Register a checkbox field.
	 *
	 * @param string $key     Settings key.
	 * @param string $label   Field label.
	 * @param string $section Section ID.
	 * @param string $desc    Optional description.
	 * @return void
	 */
	private function add_checkbox_field( string $key, string $label, string $section, string $desc = '' ): void {
		add_settings_field(
			$key,
			$label,
			function () use ( $key, $desc ): void {
				$settings = lrti_get_settings();
				$checked  = ! empty( $settings[ $key ] ) && '0' !== (string) $settings[ $key ];
				printf(
					'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
					esc_attr( self::OPTION_NAME ),
					esc_attr( $key ),
					checked( $checked, true, false ),
					esc_html( $desc )
				);
			},
			'lrti-settings',
			$section
		);
	}

	/**
	 * Register a textarea field.
	 *
	 * @param string $key     Settings key.
	 * @param string $label   Field label.
	 * @param string $section Section ID.
	 * @return void
	 */
	private function add_textarea_field( string $key, string $label, string $section ): void {
		add_settings_field(
			$key,
			$label,
			function () use ( $key ): void {
				$settings = lrti_get_settings();
				$value    = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
				printf(
					'<textarea name="%1$s[%2$s]" rows="3" class="large-text">%3$s</textarea>',
					esc_attr( self::OPTION_NAME ),
					esc_attr( $key ),
					esc_textarea( $value )
				);
			},
			'lrti-settings',
			$section
		);
	}

	/**
	 * Register a number field.
	 *
	 * @param string $key     Settings key.
	 * @param string $label   Field label.
	 * @param string $section Section ID.
	 * @return void
	 */
	private function add_number_field( string $key, string $label, string $section ): void {
		add_settings_field(
			$key,
			$label,
			function () use ( $key ): void {
				$settings = lrti_get_settings();
				$value    = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
				printf(
					'<input type="number" min="0" step="1" name="%1$s[%2$s]" value="%3$s" class="small-text" />',
					esc_attr( self::OPTION_NAME ),
					esc_attr( $key ),
					esc_attr( $value )
				);
			},
			'lrti-settings',
			$section
		);
	}

	/**
	 * Register a select field.
	 *
	 * @param string                $key     Settings key.
	 * @param string                $label   Field label.
	 * @param string                $section Section ID.
	 * @param array<string, string> $choices Option value => label.
	 * @return void
	 */
	private function add_select_field( string $key, string $label, string $section, array $choices ): void {
		add_settings_field(
			$key,
			$label,
			function () use ( $key, $choices ): void {
				$settings = lrti_get_settings();
				$value    = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
				echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[' . esc_attr( $key ) . ']">';
				foreach ( $choices as $ov => $ol ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( (string) $ov ),
						selected( $value, (string) $ov, false ),
						esc_html( (string) $ol )
					);
				}
				echo '</select>';
			},
			'lrti-settings',
			$section
		);
	}

	/**
	 * Render a text/email input bound to a settings key.
	 *
	 * @param string $key  The settings key.
	 * @param string $type "text" or "email".
	 * @return void
	 */
	public function render_text_field( string $key, string $type = 'text' ): void {
		$settings = lrti_get_settings();
		$value    = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';

		printf(
			'<input type="%1$s" id="%2$s" name="%3$s[%2$s]" value="%4$s" class="regular-text" />',
			esc_attr( 'email' === $type ? 'email' : 'text' ),
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $value )
		);
	}

	/**
	 * Render the "remove all data" checkbox with a clear warning.
	 *
	 * @return void
	 */
	public function render_remove_data_field(): void {
		$settings = lrti_get_settings();
		$checked  = ! empty( $settings['remove_all_data_on_uninstall'] );

		printf(
			'<label for="remove_all_data_on_uninstall">
				<input type="checkbox" id="remove_all_data_on_uninstall" name="%1$s[remove_all_data_on_uninstall]" value="1" %2$s />
				%3$s
			</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $checked, true, false ),
			esc_html__( 'Permanently delete ALL plugin data (trailers, leads, and settings) if this plugin is deleted from the Plugins screen.', 'little-river-trailer-inventory' )
		);

		echo '<p class="description lrti-warning">';
		esc_html_e(
			'Leave this UNCHECKED to keep your data safe. Deactivating the plugin never deletes anything. Data is only removed on full deletion, and only if this box is checked.',
			'little-river-trailer-inventory'
		);
		echo '</p>';
	}

	/**
	 * Intro text for the business section.
	 *
	 * @return void
	 */
	public function render_business_section_intro(): void {
		echo '<p>' . esc_html__( 'These details are pre-filled for Little River Equipment Sales LLC. Edit them if anything changes.', 'little-river-trailer-inventory' ) . '</p>';
	}

	/**
	 * Intro text for the leads section.
	 *
	 * @return void
	 */
	public function render_leads_section_intro(): void {
		echo '<p>' . esc_html__( 'New inquiry notifications will be sent to this address. It can differ from your business email.', 'little-river-trailer-inventory' ) . '</p>';
	}

	/**
	 * Intro text for the data section.
	 *
	 * @return void
	 */
	public function render_data_section_intro(): void {
		echo '<p>' . esc_html__( 'Controls what happens to your data if the plugin is ever deleted.', 'little-river-trailer-inventory' ) . '</p>';
	}

	/**
	 * Sanitize and validate all settings before they are saved.
	 *
	 * WordPress calls this automatically when the settings form is submitted.
	 * We never trust raw input: every value is cleaned to a safe form here.
	 *
	 * @param mixed $input The raw submitted values.
	 * @return array<string, mixed> The cleaned settings array.
	 */
	public function sanitize( $input ): array {
		// Start from existing settings so any field not present in this
		// submission keeps its current value.
		$existing = lrti_get_settings();
		$clean    = $existing;

		if ( ! is_array( $input ) ) {
			return $clean;
		}

		if ( isset( $input['dealership_name'] ) ) {
			$clean['dealership_name'] = sanitize_text_field( wp_unslash( $input['dealership_name'] ) );
		}

		if ( isset( $input['dealership_address'] ) ) {
			$clean['dealership_address'] = sanitize_text_field( wp_unslash( $input['dealership_address'] ) );
		}

		if ( isset( $input['dealership_phone'] ) ) {
			$clean['dealership_phone'] = sanitize_text_field( wp_unslash( $input['dealership_phone'] ) );
		}

		if ( isset( $input['dealership_email'] ) ) {
			$email = sanitize_email( wp_unslash( $input['dealership_email'] ) );
			// Only accept a valid email; otherwise keep the previous value and
			// warn the admin.
			if ( '' === $email || is_email( $email ) ) {
				$clean['dealership_email'] = $email;
			} else {
				add_settings_error(
					self::OPTION_NAME,
					'invalid_dealership_email',
					__( 'The business email you entered was not valid and was not saved.', 'little-river-trailer-inventory' ),
					'error'
				);
			}
		}

		if ( isset( $input['lead_notification_email'] ) ) {
			$email = sanitize_email( wp_unslash( $input['lead_notification_email'] ) );
			if ( '' === $email || is_email( $email ) ) {
				$clean['lead_notification_email'] = $email;
			} else {
				add_settings_error(
					self::OPTION_NAME,
					'invalid_lead_email',
					__( 'The lead notification email you entered was not valid and was not saved.', 'little-river-trailer-inventory' ),
					'error'
				);
			}
		}

		// --- Lead generation (Sprint 5.0) ----------------------------------

		// Checkboxes stored as '1' / '0' strings.
		foreach ( array( 'enable_inquiry_forms', 'send_customer_confirmation', 'enable_honeypot', 'store_visitor_ip' ) as $cb ) {
			$clean[ $cb ] = empty( $input[ $cb ] ) ? '0' : '1';
		}

		// Default inquiry status (must be a known lead status).
		if ( isset( $input['default_inquiry_status'] ) ) {
			$clean['default_inquiry_status'] = \LRTI\Leads::valid_status( sanitize_key( wp_unslash( $input['default_inquiry_status'] ) ) );
		}

		// Plain text.
		if ( isset( $input['customer_confirmation_subject'] ) ) {
			$clean['customer_confirmation_subject'] = sanitize_text_field( wp_unslash( $input['customer_confirmation_subject'] ) );
		}
		if ( isset( $input['contact_form_heading'] ) ) {
			$clean['contact_form_heading'] = sanitize_text_field( wp_unslash( $input['contact_form_heading'] ) );
		}

		// Multi-line text.
		foreach ( array( 'customer_confirmation_message', 'consent_text', 'contact_form_description', 'inquiry_success_message', 'inquiry_error_message' ) as $ta ) {
			if ( isset( $input[ $ta ] ) ) {
				$clean[ $ta ] = sanitize_textarea_field( wp_unslash( $input[ $ta ] ) );
			}
		}

		// Retention period: only accept the allowed choices.
		if ( isset( $input['lead_retention_days'] ) ) {
			$allowed = array( '0', '30', '90', '180', '365' );
			$val     = (string) absint( $input['lead_retention_days'] );
			$clean['lead_retention_days'] = in_array( $val, $allowed, true ) ? $val : '0';
		}

		// Non-negative integers.
		foreach ( array( 'min_completion_time', 'rate_limit_window', 'rate_limit_max', 'spam_delete_days', 'duplicate_window_seconds' ) as $num ) {
			if ( isset( $input[ $num ] ) ) {
				$clean[ $num ] = (string) absint( $input[ $num ] );
			}
		}

		// Checkbox: present and truthy means enabled, otherwise disabled.
		$clean['remove_all_data_on_uninstall'] = ! empty( $input['remove_all_data_on_uninstall'] );

		return $clean;
	}

	/**
	 * Render the full settings page.
	 *
	 * Called by the Admin class when the Settings submenu is displayed. The
	 * capability check and nonce are handled by the Settings API and by the
	 * Admin menu registration.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'little-river-trailer-inventory' ) );
		}
		?>
		<div class="wrap lrti-settings-wrap">
			<h1><?php echo esc_html__( 'Trailer Inventory Settings', 'little-river-trailer-inventory' ); ?></h1>

			<?php settings_errors(); ?>

			<form action="options.php" method="post">
				<?php
				// Outputs the hidden nonce, action, and option_page fields.
				settings_fields( self::OPTION_GROUP );

				// Renders all sections and fields we registered above.
				do_settings_sections( 'lrti-settings' );

				submit_button( __( 'Save Settings', 'little-river-trailer-inventory' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Expose the option group name (used by the Admin class if needed).
	 *
	 * @return string
	 */
	public function get_option_group(): string {
		return self::OPTION_GROUP;
	}
}
