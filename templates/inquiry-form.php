<?php
/**
 * Inquiry form template (Sprint 5.0, redesigned in 5.1).
 *
 * Rendered by the Inquiry class; expects a $lrti_form array in scope. Compact,
 * professional two-column dealership form on desktop that stacks to one column
 * on tablet/mobile. Works with AJAX and as a standard POST fallback. Theme
 * override:
 *   wp-content/themes/<theme>/little-river-trailer-inventory/inquiry-form.php
 *
 * @package LittleRiverTrailerInventory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $lrti_form ) || ! is_array( $lrti_form ) ) {
	return;
}

$f  = $lrti_form;
$id = $f['instance'];

// Show a success/error message from a non-JS (redirected) submission.
$lrti_notice      = '';
$lrti_notice_type = '';
$lrti_old_fields  = array();
$lrti_old_values  = array();
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only, read from redirect.
if ( isset( $_GET['lrti_inquiry'] ) ) {
	$state = sanitize_key( wp_unslash( $_GET['lrti_inquiry'] ) );
	if ( 'success' === $state ) {
		$lrti_notice      = '' !== $f['success_message'] ? $f['success_message'] : __( 'Thank you. Your inquiry has been received.', 'little-river-trailer-inventory' );
		$lrti_notice_type = 'success';
		if ( isset( $_GET['lrti_t'] ) ) {
			$ok_tok = sanitize_text_field( wp_unslash( $_GET['lrti_t'] ) );
			$ok_msg = get_transient( 'lrti_inq_ok_' . $ok_tok );
			if ( is_string( $ok_msg ) && '' !== $ok_msg ) {
				$lrti_notice = $ok_msg;
				delete_transient( 'lrti_inq_ok_' . $ok_tok );
			}
		}
	} elseif ( 'error' === $state && isset( $_GET['lrti_t'] ) ) {
		$tok  = sanitize_text_field( wp_unslash( $_GET['lrti_t'] ) );
		$data = get_transient( 'lrti_inq_err_' . $tok );
		if ( is_array( $data ) ) {
			$lrti_notice      = (string) ( $data['message'] ?? __( 'Please review the highlighted fields and try again.', 'little-river-trailer-inventory' ) );
			$lrti_notice_type = 'error';
			$lrti_old_fields  = (array) ( $data['fields'] ?? array() );
			$lrti_old_values  = (array) ( $data['values'] ?? array() );
			delete_transient( 'lrti_inq_err_' . $tok );
		}
	}
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$field_err = static function ( string $key ) use ( $lrti_old_fields ): string {
	return isset( $lrti_old_fields[ $key ] ) ? (string) $lrti_old_fields[ $key ] : '';
};
$old_val = static function ( string $key, string $fallback = '' ) use ( $lrti_old_values ): string {
	return isset( $lrti_old_values[ $key ] ) ? (string) $lrti_old_values[ $key ] : $fallback;
};

// Optional privacy-policy link (only when the site has one configured).
$lrti_privacy_url = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';

do_action( 'lrti_inquiry_form_before', $f );
?>
<div class="lrti-inquiry-form-wrap lrti-inquiry-form-card" id="lrti-trailer-inquiry" data-instance="<?php echo esc_attr( $id ); ?>">
	<div class="lrti-inquiry-head">
		<h2 class="lrti-inquiry-heading" id="lrti-inquiry-heading-<?php echo esc_attr( $id ); ?>" tabindex="-1"><?php echo esc_html( $f['heading'] ); ?></h2>
		<p class="lrti-inquiry-help"><?php
		if ( ! empty( $f['is_general'] ) && ! empty( $f['help_text'] ) ) {
			echo esc_html( (string) $f['help_text'] );
		} else {
			echo esc_html( ! empty( $f['is_general'] ) ? __( 'Complete the form below and a member of the Little River Equipment Sales team will get back to you.', 'little-river-trailer-inventory' ) : __( 'Complete the form below and a member of the Little River Equipment Sales team will follow up about this trailer.', 'little-river-trailer-inventory' ) );
		}
		?></p>
	</div>

	<div class="lrti-inquiry-status" role="status" aria-live="polite">
		<?php if ( '' !== $lrti_notice && 'success' === $lrti_notice_type ) : ?>
			<div class="lrti-inquiry-success"><?php echo esc_html( $lrti_notice ); ?></div>
		<?php endif; ?>
	</div>

	<?php if ( '' !== $lrti_notice && 'error' === $lrti_notice_type ) : ?>
		<div class="lrti-inquiry-errorsummary" role="alert"><?php echo esc_html( $lrti_notice ); ?></div>
	<?php endif; ?>

	<form class="lrti-inquiry-form" method="post" action="<?php echo esc_url( $f['action_url'] ); ?>" novalidate>
		<input type="hidden" name="action" value="lrti_submit_inquiry" />
		<input type="hidden" name="nonce" value="<?php echo esc_attr( $f['nonce'] ); ?>" />
		<input type="hidden" name="token" value="<?php echo esc_attr( $f['token'] ); ?>" />
		<input type="hidden" name="trailer_id" value="<?php echo esc_attr( (string) $f['trailer_id'] ); ?>" />
		<input type="hidden" name="trailer_title" value="<?php echo esc_attr( $f['trailer_title'] ); ?>" />
		<input type="hidden" name="stock_number" value="<?php echo esc_attr( $f['stock_number'] ); ?>" />
		<input type="hidden" name="form_type" value="<?php echo esc_attr( $f['form_type'] ); ?>" class="lrti-form-type" />
		<input type="hidden" name="instance" value="<?php echo esc_attr( $id ); ?>" />
		<input type="hidden" name="lrti_ts" value="<?php echo esc_attr( (string) time() ); ?>" />
		<input type="hidden" name="source_url" value="<?php echo esc_url( (int) $f['trailer_id'] ? (string) get_permalink( (int) $f['trailer_id'] ) : ( function_exists( 'lrti_current_url' ) ? lrti_current_url() : home_url( add_query_arg( array() ) ) ) ); ?>" />
		<input type="hidden" name="referrer" value="<?php echo esc_attr( isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '' ); // phpcs:ignore ?>" />
		<input type="hidden" name="success_message" value="<?php echo esc_attr( $f['success_message'] ); ?>" />

		<?php // Honeypot: visually hidden, must stay empty. ?>
		<div class="lrti-hp" aria-hidden="true">
			<label for="lrti-website-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Website', 'little-river-trailer-inventory' ); ?></label>
			<input type="text" id="lrti-website-<?php echo esc_attr( $id ); ?>" name="lrti_website" tabindex="-1" autocomplete="off" value="" />
		</div>

		<div class="lrti-form-grid lrti-inquiry-fields">
			<div class="lrti-field">
				<label for="lrti-name-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Name', 'little-river-trailer-inventory' ); ?> <span class="lrti-req" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( '(required)', 'little-river-trailer-inventory' ); ?></span></label>
				<input type="text" id="lrti-name-<?php echo esc_attr( $id ); ?>" name="name" required autocomplete="name" maxlength="120" value="<?php echo esc_attr( $old_val( 'name' ) ); ?>"
					<?php if ( '' !== $field_err( 'name' ) ) : ?>aria-invalid="true" aria-describedby="lrti-name-err-<?php echo esc_attr( $id ); ?>"<?php endif; ?> />
				<span class="lrti-field-error" id="lrti-name-err-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field_err( 'name' ) ); ?></span>
			</div>

			<div class="lrti-field">
				<label for="lrti-email-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Email', 'little-river-trailer-inventory' ); ?></label>
				<input type="email" id="lrti-email-<?php echo esc_attr( $id ); ?>" name="email" autocomplete="email" maxlength="180" value="<?php echo esc_attr( $old_val( 'email' ) ); ?>"
					<?php if ( '' !== $field_err( 'email' ) ) : ?>aria-invalid="true" aria-describedby="lrti-email-err-<?php echo esc_attr( $id ); ?>"<?php endif; ?> />
				<span class="lrti-field-error" id="lrti-email-err-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field_err( 'email' ) ); ?></span>
			</div>

			<div class="lrti-field">
				<label for="lrti-phone-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Phone', 'little-river-trailer-inventory' ); ?></label>
				<input type="tel" id="lrti-phone-<?php echo esc_attr( $id ); ?>" name="phone" autocomplete="tel" maxlength="40" value="<?php echo esc_attr( $old_val( 'phone' ) ); ?>"
					<?php if ( '' !== $field_err( 'phone' ) ) : ?>aria-invalid="true" aria-describedby="lrti-phone-err-<?php echo esc_attr( $id ); ?>"<?php endif; ?> />
				<span class="lrti-field-error" id="lrti-phone-err-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field_err( 'phone' ) ); ?></span>
			</div>

			<?php if ( ! empty( $f['show_preferred_contact'] ) ) : ?>
				<div class="lrti-field">
					<label for="lrti-pref-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Preferred Contact Method', 'little-river-trailer-inventory' ); ?></label>
					<?php $lrti_pref = $old_val( 'preferred_contact', 'either' ); ?>
					<select id="lrti-pref-<?php echo esc_attr( $id ); ?>" name="preferred_contact">
						<option value="either" <?php selected( $lrti_pref, 'either' ); ?>><?php esc_html_e( 'Either', 'little-river-trailer-inventory' ); ?></option>
						<option value="phone" <?php selected( $lrti_pref, 'phone' ); ?>><?php esc_html_e( 'Phone', 'little-river-trailer-inventory' ); ?></option>
						<option value="email" <?php selected( $lrti_pref, 'email' ); ?>><?php esc_html_e( 'Email', 'little-river-trailer-inventory' ); ?></option>
					</select>
				</div>
			<?php endif; ?>

			<div class="lrti-field lrti-field--full">
				<label for="lrti-message-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Message', 'little-river-trailer-inventory' ); ?> <span class="lrti-req" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( '(required)', 'little-river-trailer-inventory' ); ?></span></label>
				<textarea id="lrti-message-<?php echo esc_attr( $id ); ?>" name="message" rows="4" maxlength="2000" required data-default="<?php echo esc_attr( $f['default_message'] ); ?>"
					<?php if ( '' !== $field_err( 'message' ) ) : ?>aria-invalid="true" aria-describedby="lrti-message-err-<?php echo esc_attr( $id ); ?>"<?php endif; ?>><?php echo esc_textarea( $old_val( 'message', $f['default_message'] ) ); ?></textarea>
				<span class="lrti-field-error" id="lrti-message-err-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field_err( 'message' ) ); ?></span>
			</div>

			<div class="lrti-field lrti-field--full lrti-field--consent">
				<label class="lrti-consent">
					<input type="checkbox" name="consent" value="1" required
						<?php if ( '' !== $field_err( 'consent' ) ) : ?>aria-invalid="true" aria-describedby="lrti-consent-err-<?php echo esc_attr( $id ); ?>"<?php endif; ?> />
					<span><?php echo esc_html( $f['consent_text'] ); ?> <span class="lrti-req" aria-hidden="true">*</span></span>
				</label>
				<span class="lrti-field-error" id="lrti-consent-err-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field_err( 'consent' ) ); ?></span>
			</div>
		</div>

		<div class="lrti-field lrti-field--submit lrti-inquiry-actions">
			<button type="submit" class="lrti-btn lrti-btn--primary lrti-inquiry-submit" name="lrti_submit_inquiry" value="1"><?php echo esc_html( $f['button_text'] ); ?></button>
			<span class="lrti-inquiry-spinner" aria-hidden="true"></span>
			<p class="lrti-inquiry-privacy">
				<?php esc_html_e( 'Your information will only be used to respond to your trailer inquiry.', 'little-river-trailer-inventory' ); ?>
				<?php if ( '' !== $lrti_privacy_url ) : ?>
					<a href="<?php echo esc_url( $lrti_privacy_url ); ?>"><?php esc_html_e( 'Privacy Policy', 'little-river-trailer-inventory' ); ?></a>
				<?php endif; ?>
			</p>
		</div>

		<noscript>
			<p class="lrti-field-hint"><?php esc_html_e( 'Your inquiry will be submitted normally without JavaScript.', 'little-river-trailer-inventory' ); ?></p>
		</noscript>
	</form>
</div>
<?php
do_action( 'lrti_inquiry_form_after', $f );
