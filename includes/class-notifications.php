<?php
/**
 * Lead email notifications (Sprint 5.0).
 *
 * Sends the dealership notification and the optional customer confirmation via
 * wp_mail(). Subjects, bodies, headers, and the recipient are filterable. The
 * result (sent/failed) is recorded on the lead. No SMTP library is bundled — an
 * SMTP plugin is recommended for reliable delivery. Raw IP addresses and VIN are
 * never included.
 *
 * @package LittleRiverTrailerInventory
 */

namespace LRTI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Notifications
 */
final class Notifications {

	/**
	 * Leads model.
	 *
	 * @var Leads
	 */
	private Leads $leads;

	/**
	 * Constructor.
	 *
	 * @param Leads $leads Leads model.
	 */
	public function __construct( Leads $leads ) {
		$this->leads = $leads;
	}

	/**
	 * Resolve the dealership notification recipient with fallbacks.
	 *
	 * @return string
	 */
	public function recipient(): string {
		$recipient = (string) lrti_get_setting( 'lead_notification_email', '' );
		if ( '' === $recipient || ! is_email( $recipient ) ) {
			$recipient = (string) lrti_get_setting( 'dealership_email', '' );
		}
		if ( '' === $recipient || ! is_email( $recipient ) ) {
			$recipient = (string) get_option( 'admin_email' );
		}
		$recipient = (string) apply_filters( 'lrti_lead_notification_recipient', $recipient );

		/**
		 * Filter the notification recipient(s) as an array (plural alias).
		 *
		 * @param string[] $recipients Recipient email addresses.
		 */
		$recipients = (array) apply_filters( 'lrti_lead_notification_recipients', array_filter( array( $recipient ) ) );

		return implode( ',', array_map( 'sanitize_email', $recipients ) );
	}

	/**
	 * Read a lead field via its meta key.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param string $field   Field name.
	 * @return string
	 */
	private function field( int $lead_id, string $field ): string {
		$keys = Leads::meta_keys();
		if ( ! isset( $keys[ $field ] ) ) {
			return '';
		}
		return (string) get_post_meta( $lead_id, $keys[ $field ], true );
	}

	/**
	 * Send the dealership notification and record the result on the lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @return bool True if wp_mail reported success.
	 */
	public function send_dealer_notification( int $lead_id ): bool {
		$recipient = $this->recipient();
		update_post_meta( $lead_id, Leads::meta_keys()['notify_recipient'], $recipient );

		$form_type = $this->field( $lead_id, 'form_type' );
		$name      = $this->field( $lead_id, 'name' );
		$email     = $this->field( $lead_id, 'email' );
		$phone     = $this->field( $lead_id, 'phone' );
		$preferred = $this->field( $lead_id, 'preferred_contact' );
		$message   = $this->field( $lead_id, 'message' );
		$title     = $this->field( $lead_id, 'trailer_title' );
		$stock     = $this->field( $lead_id, 'stock_number' );
		$url       = $this->field( $lead_id, 'trailer_url' );
		$edit_link = admin_url( 'post.php?post=' . $lead_id . '&action=edit' );

		if ( '' === $title ) {
			// General contact submission (no trailer attached).
			$subject = sprintf(
				/* translators: %s: customer name */
				__( 'New Contact Inquiry: %s', 'little-river-trailer-inventory' ),
				'' !== $name ? $name : __( 'Website visitor', 'little-river-trailer-inventory' )
			);
		} else {
			$subject = sprintf(
				/* translators: 1: trailer title, 2: stock number */
				__( 'New Trailer Inquiry: %1$s – Stock #%2$s', 'little-river-trailer-inventory' ),
				$title,
				$stock !== '' ? $stock : __( 'N/A', 'little-river-trailer-inventory' )
			);
		}
		$subject = (string) apply_filters( 'lrti_lead_notification_subject', $subject, $lead_id );

		$lines = array(
			__( 'A new inquiry was submitted.', 'little-river-trailer-inventory' ),
			'',
			sprintf( '%s: %s', __( 'Form type', 'little-river-trailer-inventory' ), $this->form_type_label( $form_type ) ),
			sprintf( '%s: %s', __( 'Name', 'little-river-trailer-inventory' ), $name ),
			sprintf( '%s: %s', __( 'Email', 'little-river-trailer-inventory' ), $email ),
			sprintf( '%s: %s', __( 'Phone', 'little-river-trailer-inventory' ), $phone ),
			sprintf( '%s: %s', __( 'Preferred contact', 'little-river-trailer-inventory' ), $preferred ),
			'',
			sprintf( '%s:', __( 'Message', 'little-river-trailer-inventory' ) ),
			$message,
			'',
			sprintf( '%s: %s', __( 'Trailer', 'little-river-trailer-inventory' ), $title ),
			sprintf( '%s: %s', __( 'Stock #', 'little-river-trailer-inventory' ), $stock ),
			sprintf( '%s: %s', __( 'Trailer URL', 'little-river-trailer-inventory' ), $url ),
			sprintf( '%s: %s', __( 'Submitted', 'little-river-trailer-inventory' ), get_date_from_gmt( gmdate( 'Y-m-d H:i:s' ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
			'',
			sprintf( '%s: %s', __( 'Manage lead', 'little-river-trailer-inventory' ), $edit_link ),
		);
		$body = implode( "\n", $lines );
		$body = (string) apply_filters( 'lrti_lead_notification_body', $body, $lead_id );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( '' !== $email && is_email( $email ) ) {
			$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
		}
		$headers = (array) apply_filters( 'lrti_lead_notification_headers', $headers, $lead_id );

		/**
		 * Fires before the dealership notification is sent.
		 *
		 * @param int $lead_id Lead ID.
		 */
		do_action( 'lrti_before_lead_notification', $lead_id );

		$sent = false;
		if ( '' !== $recipient && is_email( $recipient ) ) {
			$sent = (bool) wp_mail( $recipient, $subject, $body, $headers );
		}

		$status = $sent ? 'sent' : 'failed';
		update_post_meta( $lead_id, Leads::meta_keys()['notify_status'], $status );
		update_post_meta( $lead_id, Leads::meta_keys()['notify_time'], time() );

		if ( ! $sent && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Little River Trailer Inventory: lead notification email failed for lead ' . $lead_id ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		$this->leads->add_activity(
			$lead_id,
			$sent ? __( 'Notification sent', 'little-river-trailer-inventory' ) : __( 'Notification failed', 'little-river-trailer-inventory' ),
			0
		);

		if ( $sent ) {
			/**
			 * Fires after a lead notification email is sent.
			 *
			 * @param int    $lead_id   Lead ID.
			 * @param string $recipient Recipient address(es).
			 */
			do_action( 'lrti_lead_email_sent', $lead_id, $recipient );
		} else {
			/**
			 * Fires after a lead notification email fails.
			 *
			 * @param int    $lead_id   Lead ID.
			 * @param string $recipient Recipient address(es).
			 */
			do_action( 'lrti_lead_email_failed', $lead_id, $recipient );
		}

		/**
		 * Fires after the dealership notification attempt.
		 *
		 * @param int  $lead_id Lead ID.
		 * @param bool $sent    Whether wp_mail reported success.
		 */
		do_action( 'lrti_after_lead_notification', $lead_id, $sent );

		return $sent;
	}

	/**
	 * Send the optional customer confirmation email.
	 *
	 * @param int $lead_id Lead ID.
	 * @return bool
	 */
	public function send_customer_confirmation( int $lead_id ): bool {
		if ( '1' !== (string) lrti_get_setting( 'send_customer_confirmation', '0' ) ) {
			return false;
		}

		$email = $this->field( $lead_id, 'email' );
		if ( '' === $email || ! is_email( $email ) ) {
			return false;
		}

		$title = $this->field( $lead_id, 'trailer_title' );
		$stock = $this->field( $lead_id, 'stock_number' );
		$phone = (string) lrti_get_setting( 'dealership_phone', '' );
		$demail = (string) lrti_get_setting( 'dealership_email', '' );

		$subject = (string) lrti_get_setting( 'customer_confirmation_subject', __( 'We received your trailer inquiry', 'little-river-trailer-inventory' ) );
		$subject = (string) apply_filters( 'lrti_customer_confirmation_subject', $subject, $lead_id );

		$message = (string) lrti_get_setting( 'customer_confirmation_message', '' );
		$lines   = array(
			$message,
			'',
			sprintf( '%s: %s', __( 'Trailer', 'little-river-trailer-inventory' ), $title ),
			sprintf( '%s: %s', __( 'Stock #', 'little-river-trailer-inventory' ), $stock ),
			'',
			'' !== $phone ? sprintf( '%s: %s', __( 'Phone', 'little-river-trailer-inventory' ), $phone ) : '',
			'' !== $demail ? sprintf( '%s: %s', __( 'Email', 'little-river-trailer-inventory' ), $demail ) : '',
		);
		$body = trim( implode( "\n", array_filter( $lines, static function ( $l ) { return '' !== $l || true; } ) ) );
		$body = (string) apply_filters( 'lrti_customer_confirmation_body', $body, $lead_id );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return (bool) wp_mail( $email, $subject, $body, $headers );
	}

	/**
	 * Human label for a form type key.
	 *
	 * @param string $type Form type.
	 * @return string
	 */
	private function form_type_label( string $type ): string {
		$map = array(
			'availability'     => __( 'Check Availability', 'little-river-trailer-inventory' ),
			'information'      => __( 'Request Information', 'little-river-trailer-inventory' ),
			'similar_inventory' => __( 'Request Similar Trailers', 'little-river-trailer-inventory' ),
		);
		return $map[ $type ] ?? $type;
	}
}
