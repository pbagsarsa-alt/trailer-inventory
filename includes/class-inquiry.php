<?php
/**
 * Inquiry forms and submission handling (Sprint 5.0).
 *
 * Renders the Check Availability / Request Information / Request Similar
 * Trailers forms, validates and sanitizes submissions server-side, applies
 * spam protection (nonce, honeypot, minimum completion time, rate limiting,
 * idempotency), creates a lead, and triggers notifications. Works via AJAX and
 * via a standard POST fallback (Post/Redirect/Get). Also provides the
 * [trailer_inquiry] shortcode.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Inquiry
 */
final class Inquiry {

	private const NONCE_ACTION = 'lrti_inquiry';

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
	 * Per-request instance counter.
	 *
	 * @var int
	 */
	private int $counter = 0;

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
	 * Attach hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_shortcode( 'trailer_inquiry', array( $this, 'shortcode' ) );
		add_shortcode( 'lrti_contact_form', array( $this, 'contact_shortcode' ) );

		add_action( 'wp_ajax_lrti_submit_inquiry', array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_lrti_submit_inquiry', array( $this, 'handle_ajax' ) );

		add_action( 'admin_post_lrti_submit_inquiry', array( $this, 'handle_post' ) );
		add_action( 'admin_post_nopriv_lrti_submit_inquiry', array( $this, 'handle_post' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Rendering
	 * --------------------------------------------------------------------- */

	/**
	 * Are inquiry forms enabled?
	 *
	 * @return bool
	 */
	public function enabled(): bool {
		return '1' === (string) lrti_get_setting( 'enable_inquiry_forms', '1' );
	}

	/**
	 * Default heading/message per form type.
	 *
	 * @param string $form_type Form type.
	 * @return array{heading:string, message:string}
	 */
	private function defaults_for( string $form_type ): array {
		switch ( $form_type ) {
			case 'general':
				return array(
					'heading' => __( 'Contact Us', 'little-river-trailer-inventory' ),
					'message' => '',
				);
			case 'similar_inventory':
				return array(
					'heading' => __( 'Looking for a Similar Trailer?', 'little-river-trailer-inventory' ),
					'message' => __( 'This trailer is sold, but we may have similar inventory available.', 'little-river-trailer-inventory' ),
				);
			case 'information':
				return array(
					'heading' => __( 'Request Information', 'little-river-trailer-inventory' ),
					'message' => __( 'I would like more information about this trailer.', 'little-river-trailer-inventory' ),
				);
			case 'availability':
			default:
				return array(
					'heading' => __( 'Check Availability', 'little-river-trailer-inventory' ),
					'message' => __( "I'm interested in confirming availability for this trailer.", 'little-river-trailer-inventory' ),
				);
		}
	}

	/**
	 * Render an inquiry form for a trailer.
	 *
	 * @param array<string, mixed> $args Render arguments.
	 * @return string
	 */
	public function render( array $args = array() ): string {
		if ( ! $this->enabled() ) {
			return '';
		}

		$trailer_id = isset( $args['trailer_id'] ) ? absint( $args['trailer_id'] ) : 0;

		$form_type = isset( $args['form_type'] ) ? sanitize_key( (string) $args['form_type'] ) : 'availability';
		if ( ! in_array( $form_type, array( 'availability', 'information', 'similar_inventory', 'general' ), true ) ) {
			$form_type = 'availability';
		}

		// A "general" form is a standalone contact form with no attached trailer
		// (for the Home / Contact pages). Every other type is trailer-specific.
		$is_general = ( 'general' === $form_type );

		if ( $is_general ) {
			$trailer_id = 0;
			$post       = null;
		} else {
			$post = $trailer_id ? get_post( $trailer_id ) : null;
			if ( ! $post || PostTypes::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
				return '<p class="lrti-inquiry-error">' . esc_html__( 'This inquiry form is unavailable because the trailer could not be found.', 'little-river-trailer-inventory' ) . '</p>';
			}
		}

		$this->counter++;
		$instance = isset( $args['instance'] ) && '' !== $args['instance'] ? sanitize_key( (string) $args['instance'] ) : 'inq' . $this->counter . '-' . $trailer_id;

		$defaults = $this->defaults_for( $form_type );

		// The general contact form's heading/description are admin-editable
		// (Settings → Leads) so the dealership can meet any wording or
		// compliance requirements without code changes.
		$help_text = '';
		if ( $is_general ) {
			$setting_heading = trim( (string) lrti_get_setting( 'contact_form_heading', '' ) );
			if ( '' !== $setting_heading ) {
				$defaults['heading'] = $setting_heading;
			}
			$help_text = (string) lrti_get_setting( 'contact_form_description', '' );
		}

		$heading = isset( $args['heading'] ) && '' !== $args['heading'] ? (string) $args['heading'] : $defaults['heading'];

		/**
		 * Filter the pre-filled inquiry message.
		 *
		 * @param string $message   Default message.
		 * @param string $form_type Form type.
		 * @param int    $trailer_id Trailer ID.
		 */
		$def_msg = (string) apply_filters( 'lrti_inquiry_form_default_message', $defaults['message'], $form_type, $trailer_id );

		/**
		 * Filter the consent checkbox text.
		 *
		 * @param string $text Consent text.
		 */
		$consent_text = (string) apply_filters( 'lrti_inquiry_form_consent_text', (string) lrti_get_setting( 'consent_text', '' ) );

		$render_args = array(
			'trailer_id'             => $trailer_id,
			'form_type'              => $form_type,
			'is_general'             => $is_general,
			'help_text'              => $help_text,
			'instance'               => $instance,
			'heading'                => $heading,
			'default_message'        => $def_msg,
			'button_text'            => isset( $args['button_text'] ) && '' !== $args['button_text'] ? (string) $args['button_text'] : __( 'Send Inquiry', 'little-river-trailer-inventory' ),
			'show_message'           => ! isset( $args['show_message'] ) || $this->truthy( $args['show_message'], true ),
			'show_preferred_contact' => ! isset( $args['show_preferred_contact'] ) || $this->truthy( $args['show_preferred_contact'], true ),
			'success_message'        => isset( $args['success_message'] ) && '' !== $args['success_message'] ? (string) $args['success_message'] : (string) lrti_get_setting( 'inquiry_success_message', '' ),
			'consent_text'           => $consent_text,
			'nonce'                  => wp_create_nonce( self::NONCE_ACTION ),
			'token'                  => wp_generate_password( 20, false ),
			'action_url'             => esc_url( admin_url( 'admin-post.php' ) ),
			'stock_number'           => (string) lrti_get_trailer_meta( $trailer_id, 'stock_number', '' ),
			'trailer_title'          => get_the_title( $trailer_id ),
			'trailer_url'            => (string) get_permalink( $trailer_id ),
		);

		/**
		 * Filter the full inquiry form render arguments (field visibility, text).
		 *
		 * @param array<string, mixed> $render_args Render arguments.
		 * @param int                  $trailer_id  Trailer ID.
		 */
		$render_args = (array) apply_filters( 'lrti_inquiry_form_fields', $render_args, $trailer_id );

		ob_start();
		do_action( 'lrti_before_inquiry_form', $render_args );
		$file = lrti_locate_template( 'inquiry-form.php' );
		if ( '' !== $file && is_file( $file ) ) {
			$lrti_form = $render_args; // Exposed to the template.
			include $file;
		}
		do_action( 'lrti_after_inquiry_form', $render_args );
		return (string) ob_get_clean();
	}

	/**
	 * [lrti_contact_form] — a standalone general contact form (no trailer).
	 *
	 * A convenience alias for [trailer_inquiry general="yes"], intended for the
	 * Home and Contact pages so the dealership can reuse the same form and lead
	 * pipeline instead of installing a separate contact-form plugin.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function contact_shortcode( $atts ): string {
		$atts             = is_array( $atts ) ? $atts : array();
		$atts['general']  = 'yes';
		return $this->shortcode( $atts );
	}

	/**
	 * [trailer_inquiry] shortcode.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'trailer_id'             => '',
				'form_type'              => 'availability',
				'general'                => 'no',
				'heading'                => '',
				'button_text'            => '',
				'show_message'           => 'yes',
				'show_preferred_contact' => 'yes',
				'success_message'        => '',
			),
			$atts,
			'trailer_inquiry'
		);

		// General/contact mode: a standalone form with no attached trailer.
		$is_general = $this->truthy( $atts['general'] ) || 'general' === sanitize_key( (string) $atts['form_type'] );
		if ( $is_general ) {
			return $this->render(
				array(
					'trailer_id'             => 0,
					'form_type'              => 'general',
					'heading'                => $atts['heading'],
					'button_text'            => $atts['button_text'],
					'show_message'           => $atts['show_message'],
					'show_preferred_contact' => $atts['show_preferred_contact'],
					'success_message'        => $atts['success_message'],
				)
			);
		}

		$trailer_id = absint( $atts['trailer_id'] );
		if ( ! $trailer_id && is_singular( PostTypes::POST_TYPE ) ) {
			$trailer_id = get_queried_object_id();
		}
		if ( ! $trailer_id ) {
			return '<p class="lrti-inquiry-error">' . esc_html__( 'No trailer was specified for this inquiry form.', 'little-river-trailer-inventory' ) . '</p>';
		}

		return $this->render(
			array(
				'trailer_id'             => $trailer_id,
				'form_type'              => $atts['form_type'],
				'heading'                => $atts['heading'],
				'button_text'            => $atts['button_text'],
				'show_message'           => $atts['show_message'],
				'show_preferred_contact' => $atts['show_preferred_contact'],
				'success_message'        => $atts['success_message'],
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * Submission handling
	 * --------------------------------------------------------------------- */

	/**
	 * AJAX handler.
	 *
	 * @return void
	 */
	public function handle_ajax(): void {
		$result = $this->process( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in process().

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'fields'  => is_array( $data ) && isset( $data['fields'] ) ? $data['fields'] : array(),
				),
				200
			);
		}

		wp_send_json_success(
			array(
				'message' => (string) $result,
			)
		);
	}

	/**
	 * Non-AJAX POST handler (Post/Redirect/Get).
	 *
	 * @return void
	 */
	public function handle_post(): void {
		$result   = $this->process( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in process().
		$redirect = isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : home_url( '/' );
		$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );

		if ( is_wp_error( $result ) ) {
			$token = wp_generate_password( 12, false );
			$data  = $result->get_error_data();
			set_transient(
				'lrti_inq_err_' . $token,
				array(
					'message' => $result->get_error_message(),
					'fields'  => (array) ( $data['fields'] ?? array() ),
					'values'  => (array) ( $data['values'] ?? array() ),
				),
				10 * MINUTE_IN_SECONDS
			);
			wp_safe_redirect( add_query_arg( array( 'lrti_inquiry' => 'error', 'lrti_t' => $token ), $redirect ) . '#lrti-trailer-inquiry' );
			exit;
		}

		// Store the composed (title-aware) success message for the redirect.
		$ok_token = wp_generate_password( 12, false );
		set_transient( 'lrti_inq_ok_' . $ok_token, (string) $result, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( array( 'lrti_inquiry' => 'success', 'lrti_t' => $ok_token ), $redirect ) . '#lrti-trailer-inquiry' );
		exit;
	}

	/**
	 * Validate, guard, and process a submission.
	 *
	 * @param array<string, mixed> $src Raw request.
	 * @return string|\WP_Error Success message or error (with field data).
	 */
	private function process( array $src ) {
		// Nonce / CSRF.
		$nonce = isset( $src['nonce'] ) ? sanitize_text_field( wp_unslash( $src['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new \WP_Error( 'lrti_nonce', $this->error_message(), array( 'fields' => array() ) );
		}

		/**
		 * Fires after the nonce check, before spam/validation. Extension point
		 * for adding a CAPTCHA provider.
		 *
		 * @param array<string, mixed> $src Raw request.
		 */
		do_action( 'lrti_inquiry_form_before_submit', $src );

		// Honeypot: a filled decoy field means a bot.
		if ( '1' === (string) lrti_get_setting( 'enable_honeypot', '1' ) ) {
			$hp = isset( $src['lrti_website'] ) ? trim( (string) wp_unslash( $src['lrti_website'] ) ) : '';
			if ( '' !== $hp ) {
				return new \WP_Error( 'lrti_spam', $this->error_message(), array( 'fields' => array() ) );
			}
		}

		// Minimum completion time.
		$min_time = (int) lrti_get_setting( 'min_completion_time', 3 );
		$rendered = isset( $src['lrti_ts'] ) ? absint( $src['lrti_ts'] ) : 0;
		if ( $min_time > 0 && ( 0 === $rendered || ( time() - $rendered ) < $min_time ) ) {
			return new \WP_Error( 'lrti_fast', $this->error_message(), array( 'fields' => array() ) );
		}
		// Maximum age (stale form): 24 hours.
		if ( $rendered > 0 && ( time() - $rendered ) > DAY_IN_SECONDS ) {
			return new \WP_Error( 'lrti_stale', $this->error_message(), array( 'fields' => array() ) );
		}

		// Trailer verification (never trust submitted stock/title/url). A general
		// contact submission has no trailer (trailer_id 0) and is allowed.
		$trailer_id = isset( $src['trailer_id'] ) ? absint( $src['trailer_id'] ) : 0;
		if ( $trailer_id ) {
			$post = get_post( $trailer_id );
			if ( ! $post || PostTypes::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
				return new \WP_Error( 'lrti_trailer', __( 'This trailer is no longer available. Please contact us for current inventory.', 'little-river-trailer-inventory' ), array( 'fields' => array() ) );
			}
		}

		// Idempotency: ignore a repeated token (double-click / retry).
		$token = isset( $src['token'] ) ? sanitize_text_field( wp_unslash( $src['token'] ) ) : '';
		if ( '' !== $token ) {
			$seen_key = 'lrti_inq_tok_' . md5( $token );
			if ( false !== get_transient( $seen_key ) ) {
				return $this->success_message( $src, $trailer_id );
			}
			set_transient( $seen_key, 1, HOUR_IN_SECONDS );
		}

		// Rate limiting (privacy-safe hashed identifier).
		$rate_error = $this->check_rate_limit( $trailer_id, $src );
		if ( is_wp_error( $rate_error ) ) {
			return $rate_error;
		}

		do_action( 'lrti_before_lead_validation', $src );

		// Validate + sanitize.
		$fields = array();
		$name   = isset( $src['name'] ) ? sanitize_text_field( wp_unslash( $src['name'] ) ) : '';
		$email  = isset( $src['email'] ) ? sanitize_email( wp_unslash( $src['email'] ) ) : '';
		$phone  = isset( $src['phone'] ) ? sanitize_text_field( wp_unslash( $src['phone'] ) ) : '';
		$msg    = isset( $src['message'] ) ? sanitize_textarea_field( wp_unslash( $src['message'] ) ) : '';
		$pref   = isset( $src['preferred_contact'] ) ? sanitize_key( wp_unslash( $src['preferred_contact'] ) ) : '';
		$consent = ! empty( $src['consent'] );

		if ( '' === $name ) {
			$fields['name'] = __( 'Please enter your name.', 'little-river-trailer-inventory' );
		}
		if ( '' !== $email && ! is_email( $email ) ) {
			$fields['email'] = __( 'Please enter a valid email address.', 'little-river-trailer-inventory' );
		}
		if ( '' !== $phone && ! lrti_is_valid_phone( $phone ) ) {
			$fields['phone'] = __( 'Please enter a valid phone number.', 'little-river-trailer-inventory' );
		}
		$has_email = ( '' !== $email && is_email( $email ) );
		$has_phone = ( '' !== $phone && lrti_is_valid_phone( $phone ) );
		if ( ! $has_email && ! $has_phone ) {
			$fields['email'] = __( 'Please provide an email address or phone number.', 'little-river-trailer-inventory' );
		}
		if ( '' === $msg ) {
			$fields['message'] = __( 'Please enter a message.', 'little-river-trailer-inventory' );
		}
		if ( ! $consent ) {
			$fields['consent'] = __( 'Please provide your consent to be contacted.', 'little-river-trailer-inventory' );
		}
		if ( ! in_array( $pref, array( 'email', 'phone', 'either' ), true ) ) {
			$pref = 'either';
		}

		if ( ! empty( $fields ) ) {
			return new \WP_Error(
				'lrti_validation',
				__( 'Please review the highlighted fields and try again.', 'little-river-trailer-inventory' ),
				array(
					'fields' => $fields,
					'values' => array(
						'name'              => $name,
						'email'             => $email,
						'phone'             => $phone,
						'message'           => $msg,
						'preferred_contact' => $pref,
					),
				)
			);
		}

		do_action( 'lrti_after_lead_validation', $src );

		$form_type = isset( $src['form_type'] ) ? sanitize_key( wp_unslash( $src['form_type'] ) ) : 'availability';
		if ( ! in_array( $form_type, array( 'availability', 'information', 'similar_inventory' ), true ) ) {
			$form_type = 'availability';
		}

		// Genuine duplicate check: only a previously SAVED inquiry from the same
		// person (trailer + normalized email/phone) within the window counts.
		$dupe_key = $this->duplicate_key( $trailer_id, $has_email ? $email : '', $has_phone ? $phone : '' );
		if ( '' !== $dupe_key && false !== get_transient( $dupe_key ) ) {
			return new \WP_Error(
				'lrti_dupe',
				__( 'We already received this inquiry. Please allow our team a little time to respond.', 'little-river-trailer-inventory' ),
				array( 'fields' => array() )
			);
		}

		// Build lead data (official trailer data pulled server-side).
		$data = array(
			'form_type'         => $form_type,
			'name'              => $name,
			'email'             => $has_email ? $email : '',
			'phone'             => $has_phone ? $phone : '',
			'preferred_contact' => $pref,
			'message'           => $msg,
			'consent'           => '1',
			'consent_text'      => (string) lrti_get_setting( 'consent_text', '' ),
			'consent_time'      => time(),
			'trailer_id'        => $trailer_id,
			'trailer_title'     => get_the_title( $trailer_id ),
			'stock_number'      => (string) lrti_get_trailer_meta( $trailer_id, 'stock_number', '' ),
			'trailer_url'       => (string) get_permalink( $trailer_id ),
			'source_url'        => isset( $src['source_url'] ) ? esc_url_raw( wp_unslash( $src['source_url'] ) ) : '',
			'referrer'          => isset( $src['referrer'] ) ? esc_url_raw( wp_unslash( $src['referrer'] ) ) : '',
			'utm_source'        => $this->clean_utm( $src, 'utm_source' ),
			'utm_medium'        => $this->clean_utm( $src, 'utm_medium' ),
			'utm_campaign'      => $this->clean_utm( $src, 'utm_campaign' ),
			'utm_term'          => $this->clean_utm( $src, 'utm_term' ),
			'utm_content'       => $this->clean_utm( $src, 'utm_content' ),
			'ip_hash'           => ( '1' === (string) lrti_get_setting( 'store_visitor_ip', '1' ) ) ? $this->ip_hash() : '',
			'user_agent'        => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 190 ) : '',
		);

		$lead_id = $this->leads->create_lead( $data );
		if ( is_wp_error( $lead_id ) ) {
			return new \WP_Error( 'lrti_store', $this->error_message(), array( 'fields' => array() ) );
		}

		/**
		 * Fires after a lead is created from an inquiry (alias of
		 * lrti_after_lead_created for form-centric integrations).
		 *
		 * @param int                  $lead_id Lead ID.
		 * @param array<string, mixed> $data    Lead data.
		 */
		do_action( 'lrti_lead_created', (int) $lead_id, $data );

		// Notifications (a delivery failure never loses the stored lead).
		$this->notifications->send_dealer_notification( (int) $lead_id );
		$this->notifications->send_customer_confirmation( (int) $lead_id );

		// Create the duplicate lock ONLY now — after the record exists and the
		// email has been attempted. This prevents false "already sent" warnings
		// on a first legitimate submission.
		if ( '' !== $dupe_key ) {
			set_transient( $dupe_key, (int) $lead_id, $this->duplicate_window() );
		}

		/**
		 * Fires after an inquiry submission is fully processed.
		 *
		 * @param int                  $lead_id Lead ID.
		 * @param array<string, mixed> $src     Raw request.
		 */
		do_action( 'lrti_inquiry_form_after_submit', (int) $lead_id, $src );

		return $this->success_message( $src, $trailer_id );
	}

	/**
	 * Rate-limit check using a privacy-safe hashed identifier. Filterable rules.
	 *
	 * @param int                  $trailer_id Trailer ID.
	 * @param array<string, mixed> $src        Raw request.
	 * @return true|\WP_Error
	 */
	private function check_rate_limit( int $trailer_id, array $src ) {
		$rules = (array) apply_filters(
			'lrti_rate_limit_rules',
			array(
				'window' => (int) lrti_get_setting( 'rate_limit_window', 3600 ),
				'max'    => (int) lrti_get_setting( 'rate_limit_max', 5 ),
			)
		);
		$window = max( 60, (int) ( $rules['window'] ?? 3600 ) );
		$max    = max( 1, (int) ( $rules['max'] ?? 5 ) );

		$id  = $this->ip_hash();
		$key = 'lrti_rl_' . $id;

		$count = (int) get_transient( $key );
		if ( $count >= $max ) {
			return new \WP_Error(
				'lrti_rate',
				__( 'You have submitted several inquiries recently. Please try again later or call us directly.', 'little-river-trailer-inventory' ),
				array( 'fields' => array() )
			);
		}
		set_transient( $key, $count + 1, $window );

		return true;
	}

	/**
	 * Build the duplicate fingerprint from trailer + normalized email/phone.
	 * IP is never used as the identifier.
	 *
	 * @param int    $trailer_id Trailer ID.
	 * @param string $email      Customer email.
	 * @param string $phone      Customer phone.
	 * @return string Transient key, or '' when there is nothing to fingerprint.
	 */
	private function duplicate_key( int $trailer_id, string $email, string $phone ): string {
		$email = strtolower( trim( $email ) );
		$phone = preg_replace( '/[^0-9]/', '', $phone );
		$phone = is_string( $phone ) ? $phone : '';

		// Without an email or phone there is no reliable person-level fingerprint.
		if ( '' === $email && '' === $phone ) {
			return '';
		}

		return 'lrti_inq_dupe_' . md5( $trailer_id . '|' . $email . '|' . $phone );
	}

	/**
	 * The duplicate-submission window in seconds (default 10 minutes). Filterable.
	 *
	 * @return int
	 */
	private function duplicate_window(): int {
		$default = (int) lrti_get_setting( 'duplicate_window_seconds', 600 );
		if ( $default < 1 ) {
			$default = 600;
		}
		/**
		 * Filter the duplicate-submission window, in seconds.
		 *
		 * @param int $seconds Window length (default 600).
		 */
		$window = max( 1, (int) apply_filters( 'lrti_duplicate_window_seconds', $default ) );

		/**
		 * Filter the duplicate-lead window, in seconds (alias of
		 * lrti_duplicate_window_seconds for lead-centric integrations).
		 *
		 * @param int $seconds Window length.
		 */
		return max( 1, (int) apply_filters( 'lrti_duplicate_lead_window', $window ) );
	}

	/**
	 * A privacy-safe hashed visitor identifier (never stores the raw IP).
	 *
	 * @return string
	 */
	private function ip_hash(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		return substr( hash_hmac( 'sha256', $ip, (string) wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Sanitize a UTM parameter.
	 *
	 * @param array<string, mixed> $src Raw request.
	 * @param string               $key UTM key.
	 * @return string
	 */
	private function clean_utm( array $src, string $key ): string {
		return isset( $src[ $key ] ) ? sanitize_text_field( wp_unslash( $src[ $key ] ) ) : '';
	}

	/**
	 * The success message (from the form args or settings). Filterable.
	 *
	 * @param array<string, mixed> $src Raw request.
	 * @return string
	 */
	private function success_message( array $src, int $trailer_id = 0 ): string {
		$title = $trailer_id ? get_the_title( $trailer_id ) : '';
		if ( '' !== $title ) {
			$message = sprintf(
				/* translators: %s: trailer title */
				__( 'Thank you. Your inquiry about %s has been received. Little River Equipment Sales will follow up using the contact information you provided.', 'little-river-trailer-inventory' ),
				$title
			);
		} else {
			$custom  = isset( $src['success_message'] ) ? sanitize_text_field( wp_unslash( $src['success_message'] ) ) : '';
			$message = '' !== $custom ? $custom : (string) lrti_get_setting( 'inquiry_success_message', '' );
		}
		return (string) apply_filters( 'lrti_inquiry_success_message', $message, $trailer_id );
	}

	/**
	 * The generic error message, with the dealership phone appended.
	 *
	 * @return string
	 */
	private function error_message(): string {
		$msg   = (string) lrti_get_setting( 'inquiry_error_message', '' );
		$phone = (string) lrti_get_setting( 'dealership_phone', '' );
		if ( '' !== $phone ) {
			$msg = trim( $msg . ' ' . sprintf( /* translators: %s: phone */ __( 'Call %s for assistance.', 'little-river-trailer-inventory' ), $phone ) );
		}
		return $msg;
	}

	/**
	 * Loosely interpret a truthy attribute value.
	 *
	 * @param mixed $value    The value.
	 * @param bool  $fallback Default when unrecognized.
	 * @return bool
	 */
	private function truthy( $value, bool $fallback = false ): bool {
		$v = strtolower( (string) $value );
		if ( in_array( $v, array( '1', 'true', 'yes', 'on' ), true ) ) {
			return true;
		}
		if ( in_array( $v, array( '0', 'false', 'no', 'off' ), true ) ) {
			return false;
		}
		return $fallback;
	}
}
