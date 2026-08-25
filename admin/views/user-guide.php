<?php
/**
 * User Guide page view.
 *
 * Rendered by UserGuide::render_page(). Receives $guide (the UserGuide instance).
 *
 * @package LRTI
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $guide ) || ! ( $guide instanceof \LRTI\UserGuide ) ) {
	return;
}

$phone = (string) lrti_get_setting( 'dealership_phone', '(870) 542-4661' );
$email = (string) lrti_get_setting( 'lead_notification_email', 'littleriverequipmentsales@outlook.com' );
if ( '' === $email ) {
	$email = 'littleriverequipmentsales@outlook.com';
}
?>
<div class="wrap lrti-guide">

	<div class="lrti-guide-hero">
		<h1 class="lrti-guide-title"><?php esc_html_e( 'TWC Trailer Inventory — User Guide', 'little-river-trailer-inventory' ); ?></h1>
		<p class="lrti-guide-sub"><?php esc_html_e( 'A plain-English guide to running your trailer website. No coding needed.', 'little-river-trailer-inventory' ); ?></p>
		<p class="lrti-guide-meta">
			<?php
			printf(
				/* translators: 1: plugin version, 2: guide version */
				esc_html__( 'Plugin version %1$s · Guide version %2$s', 'little-river-trailer-inventory' ),
				esc_html( LRTI_VERSION ),
				esc_html( $guide->doc_version() )
			);
			?>
		</p>
	</div>

	<div class="lrti-guide-searchbar">
		<label for="lrti-guide-search" class="screen-reader-text"><?php esc_html_e( 'Search the guide', 'little-river-trailer-inventory' ); ?></label>
		<span class="dashicons dashicons-search" aria-hidden="true"></span>
		<input type="search" id="lrti-guide-search" class="lrti-guide-search" placeholder="<?php esc_attr_e( 'Search the guide (for example: add a trailer, sold, shortcode)…', 'little-river-trailer-inventory' ); ?>" autocomplete="off" />
	</div>
	<p class="lrti-guide-noresult" hidden><?php esc_html_e( 'No help topics found. Try a different word.', 'little-river-trailer-inventory' ); ?></p>

	<div class="lrti-guide-body">

		<!-- Start here -->
		<section class="lrti-guide-card lrti-guide-start" data-guide-section data-keywords="start here quick first setup begin">
			<h2><?php esc_html_e( 'Start Here', 'little-river-trailer-inventory' ); ?></h2>
			<p><?php esc_html_e( 'New to the plugin? Do these four things first.', 'little-river-trailer-inventory' ); ?></p>
			<ol class="lrti-guide-quick">
				<li><strong><?php esc_html_e( 'Add a trailer', 'little-river-trailer-inventory' ); ?></strong> — <?php esc_html_e( 'Trailer Inventory → Add New Trailer.', 'little-river-trailer-inventory' ); ?></li>
				<li><strong><?php esc_html_e( 'Add photos', 'little-river-trailer-inventory' ); ?></strong> — <?php esc_html_e( 'Set a main image and a gallery on the trailer screen.', 'little-river-trailer-inventory' ); ?></li>
				<li><strong><?php esc_html_e( 'Set up the pages', 'little-river-trailer-inventory' ); ?></strong> — <?php esc_html_e( 'Place the shortcodes on your Inventory and Home pages.', 'little-river-trailer-inventory' ); ?></li>
				<li><strong><?php esc_html_e( 'Check your leads', 'little-river-trailer-inventory' ); ?></strong> — <?php esc_html_e( 'Trailer Inventory → Leads shows every inquiry.', 'little-river-trailer-inventory' ); ?></li>
			</ol>
		</section>

		<!-- Lessons -->
		<section class="lrti-guide-card">
			<h2><?php esc_html_e( 'Step-by-Step Lessons', 'little-river-trailer-inventory' ); ?></h2>

			<?php
			// --- Adding a trailer ---
			$body  = '<p>' . esc_html__( 'To list a trailer for sale:', 'little-river-trailer-inventory' ) . '</p>';
			$body .= '<ol>';
			$body .= '<li>' . esc_html__( 'Go to Trailer Inventory → Add New Trailer.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '<li>' . esc_html__( 'Type a clear title, like "20 ft Dump Trailer".', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '<li>' . esc_html__( 'Fill in the Basic Information (year, brand, price, stock number).', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '<li>' . esc_html__( 'Add sizes and specs where you know them. Blank fields are hidden on the website.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '<li>' . esc_html__( 'Set the main image and add gallery photos.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '<li>' . esc_html__( 'Pick the Trailer Type and Manufacturer on the right.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '<li>' . esc_html__( 'Click Publish.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '</ol>';
			$body .= '<p class="lrti-guide-tip">' . esc_html__( 'Tip: only fill in what you have verified. Never guess a spec, price, or warranty.', 'little-river-trailer-inventory' ) . '</p>';
			echo $guide->accordion( 'add-trailer', __( 'How to add a trailer', 'little-river-trailer-inventory' ), $body, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			// --- Photos ---
			$body  = '<p>' . esc_html__( 'Good photos sell trailers. On the trailer edit screen:', 'little-river-trailer-inventory' ) . '</p>';
			$body .= '<ul>';
			$body .= '<li>' . esc_html__( 'Set the Featured Image — this is the main photo shown in lists.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '<li>' . esc_html__( 'Add more photos to the trailer gallery for the detail page.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '<li>' . esc_html__( 'Use clear, well-lit pictures. Landscape (wide) photos look best.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '</ul>';
			echo $guide->accordion( 'photos', __( 'How to add trailer photos', 'little-river-trailer-inventory' ), $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			// --- Categories / manufacturers ---
			$body  = '<p>' . esc_html__( 'Trailer Types and Manufacturers are how visitors browse.', 'little-river-trailer-inventory' ) . '</p>';
			$body .= '<ul>';
			$body .= '<li>' . esc_html__( 'Trailer Inventory → Trailer Types: add or edit categories like Dump or Utility.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '<li>' . esc_html__( 'On each Trailer Type you can add a homepage card image, tick "Show on Homepage", and set a Display Order.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '<li>' . esc_html__( 'Trailer Inventory → Manufacturers: add or edit brands.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '</ul>';
			$body .= '<p class="lrti-guide-tip">' . esc_html__( 'Homepage cards come from Trailer Types, not from Pages. You do not need to create pages for them.', 'little-river-trailer-inventory' ) . '</p>';
			echo $guide->accordion( 'categories', __( 'How to manage categories and brands', 'little-river-trailer-inventory' ), $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			// --- Leads ---
			$flow  = $guide->flow( array( __( 'Visitor sends form', 'little-river-trailer-inventory' ), __( 'Lead saved', 'little-river-trailer-inventory' ), __( 'You get an email', 'little-river-trailer-inventory' ), __( 'You follow up', 'little-river-trailer-inventory' ), __( 'Mark Sold', 'little-river-trailer-inventory' ) ) );
			$body  = '<p>' . esc_html__( 'Every inquiry from your site becomes a lead you can manage.', 'little-river-trailer-inventory' ) . '</p>';
			$body .= $flow;
			$body .= '<ul>';
			$body .= '<li>' . esc_html__( 'Open Trailer Inventory → Leads to see them all.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '<li>' . esc_html__( 'Click a lead to see the customer, their message, and the trailer.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '<li>' . esc_html__( 'Update the status (New, Contacted, Sold, and so on), add private notes, and set a follow-up date.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '<li>' . esc_html__( 'Use Export CSV to download your leads.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '</ul>';
			echo $guide->accordion( 'leads', __( 'How to manage leads (inquiries)', 'little-river-trailer-inventory' ), $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			// --- Mark sold ---
			$body  = '<p>' . esc_html__( 'When a trailer sells:', 'little-river-trailer-inventory' ) . '</p>';
			$body .= '<ol>';
			$body .= '<li>' . esc_html__( 'Open the trailer and set its Availability to Sold.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '<li>' . esc_html__( 'Update (save) the trailer.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '</ol>';
			$body .= '<p>' . esc_html__( 'Sold trailers drop out of the active list by default. You choose how sold pages behave in Settings.', 'little-river-trailer-inventory' ) . '</p>';
			echo $guide->accordion( 'sold', __( 'How to mark a trailer sold', 'little-river-trailer-inventory' ), $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			// --- Contact form / compliance ---
			$body  = '<p>' . esc_html__( 'You can reuse the same form as a general contact form.', 'little-river-trailer-inventory' ) . '</p>';
			$body .= '<ul>';
			$body .= '<li>' . esc_html__( 'Put [lrti_contact_form] on your Home or Contact page.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '<li>' . esc_html__( 'Change the Heading, Description, and Consent wording under Trailer Inventory → Settings → Leads.', 'little-river-trailer-inventory' ) . '</li>';
			$body .= '</ul>';
			echo $guide->accordion( 'contact-form', __( 'How to add a contact form', 'little-river-trailer-inventory' ), $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</section>

		<!-- Shortcodes -->
		<section class="lrti-guide-card lrti-guide-shortcodes" data-guide-section data-keywords="shortcode shortcodes embed paste code">
			<div class="lrti-guide-sc-top">
				<h2><?php esc_html_e( 'Shortcode Reference', 'little-river-trailer-inventory' ); ?></h2>
				<button type="button" class="button lrti-guide-copyall" data-copyall><?php esc_html_e( 'Copy All Shortcodes', 'little-river-trailer-inventory' ); ?></button>
			</div>
			<p><?php esc_html_e( 'A shortcode is a small tag in square brackets you paste onto a page. WordPress turns it into content. Copy one, paste it into a page or an Elementor "Shortcode" block, and save.', 'little-river-trailer-inventory' ); ?></p>

			<?php foreach ( $guide->registry() as $group => $cards ) : ?>
				<h3 class="lrti-guide-sc-group"><?php echo esc_html( $group ); ?></h3>
				<?php foreach ( $cards as $card ) : ?>
					<?php echo $guide->shortcode_card( $card ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</section>

		<!-- Troubleshooting -->
		<section class="lrti-guide-card">
			<h2><?php esc_html_e( 'Troubleshooting', 'little-river-trailer-inventory' ); ?></h2>
			<?php
			$body  = '<p><strong>' . esc_html__( 'A change I made is not showing.', 'little-river-trailer-inventory' ) . '</strong></p>';
			$body .= '<p>' . esc_html__( 'Your site uses SpeedyCache, which saves a copy of each page. After a change, do a full SpeedyCache purge (clear combined/minified CSS and JS), then refresh with Ctrl+Shift+R (Cmd+Shift+R on Mac).', 'little-river-trailer-inventory' ) . '</p>';
			echo $guide->accordion( 'ts-cache', __( 'My change is not showing on the website', 'little-river-trailer-inventory' ), $body, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			$body  = '<p>' . esc_html__( 'Open Settings → Permalinks and click Save Changes once (you do not need to change anything). This refreshes the web addresses.', 'little-river-trailer-inventory' ) . '</p>';
			echo $guide->accordion( 'ts-404', __( 'A trailer or category page shows "Not Found"', 'little-river-trailer-inventory' ), $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			$body  = '<p>' . esc_html__( 'A homepage category card only appears when its Trailer Type has "Show on Homepage" turned on. Set a Display Order (1, 2, 3…) to control the order. Order 0 sorts last.', 'little-river-trailer-inventory' ) . '</p>';
			echo $guide->accordion( 'ts-cards', __( 'My homepage category cards are missing or out of order', 'little-river-trailer-inventory' ), $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			$body  = '<p>' . esc_html__( 'The featured carousel does not auto-slide if your computer has "Reduce Motion" turned on — that is on purpose for accessibility. You can still use the arrows. If nothing changed at all, clear the full SpeedyCache cache.', 'little-river-trailer-inventory' ) . '</p>';
			echo $guide->accordion( 'ts-carousel', __( 'The featured carousel is not moving', 'little-river-trailer-inventory' ), $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			$body  = '<p>' . esc_html__( 'Check that inquiry forms are enabled in Settings, that a notification email is set, and look in your spam folder. Every inquiry is also saved under Leads, so nothing is lost even if an email is delayed.', 'little-river-trailer-inventory' ) . '</p>';
			echo $guide->accordion( 'ts-email', __( 'I did not get an email for an inquiry', 'little-river-trailer-inventory' ), $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</section>

		<!-- FAQ -->
		<section class="lrti-guide-card">
			<h2><?php esc_html_e( 'Frequently Asked Questions', 'little-river-trailer-inventory' ); ?></h2>
			<?php
			$body = '<p>' . esc_html__( 'No. Homepage category cards are built from Trailer Types. Each card links to that type automatically — no pages or slugs to create.', 'little-river-trailer-inventory' ) . '</p>';
			echo $guide->accordion( 'faq-pages', __( 'Do I need to make pages for each category?', 'little-river-trailer-inventory' ), $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			$body = '<p>' . esc_html__( 'No. This plugin handles inventory, inquiries, and a contact form. Use [lrti_contact_form] instead of a separate form plugin.', 'little-river-trailer-inventory' ) . '</p>';
			echo $guide->accordion( 'faq-forms', __( 'Do I need WPForms or another contact-form plugin?', 'little-river-trailer-inventory' ), $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			$body = '<p>' . esc_html__( 'Leave the field blank. Blank fields are hidden on the website, so there are no empty rows or "N/A" values.', 'little-river-trailer-inventory' ) . '</p>';
			echo $guide->accordion( 'faq-blank', __( 'What if I do not know a spec?', 'little-river-trailer-inventory' ), $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			$body = '<p>' . esc_html__( 'Deactivating or updating the plugin never deletes your trailers, leads, or settings. Data is only removed on uninstall if you have turned that option on.', 'little-river-trailer-inventory' ) . '</p>';
			echo $guide->accordion( 'faq-data', __( 'Will I lose my data if I update the plugin?', 'little-river-trailer-inventory' ), $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</section>

		<!-- Support -->
		<section class="lrti-guide-card lrti-guide-support" data-guide-section data-keywords="support help contact developer">
			<h2><?php esc_html_e( 'Need More Help?', 'little-river-trailer-inventory' ); ?></h2>
			<p><?php esc_html_e( 'Your dealership contact details (edit these under Settings):', 'little-river-trailer-inventory' ); ?></p>
			<ul class="lrti-guide-support-list">
				<li><span class="dashicons dashicons-phone" aria-hidden="true"></span> <?php echo esc_html( $phone ); ?></li>
				<li><span class="dashicons dashicons-email" aria-hidden="true"></span> <?php echo esc_html( $email ); ?></li>
			</ul>
			<p class="lrti-guide-credit"><?php esc_html_e( 'Plugin built and maintained by Trendwise Co.', 'little-river-trailer-inventory' ); ?></p>
		</section>

	</div>
</div>
