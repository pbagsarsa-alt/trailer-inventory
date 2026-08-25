=== TWC Trailer Inventory for Little River Equipment Sales LLC ===
Contributors: trendwiseco
Tags: trailers, inventory, dealership, car hauler, dump trailer
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 2.9.17
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Trailer dealership inventory management for Little River Equipment Sales LLC. Manage trailers in the dashboard; let visitors browse, search, filter, and inquire.

== Description ==

TWC Trailer Inventory for Little River Equipment Sales LLC is a self-contained inventory system for a trailer
dealership. It is built specifically for Little River Equipment Sales LLC in
Foreman, Arkansas, but every business detail can be edited from the settings page.

This is version 1.0.0, delivered in phases. The current release provides the
plugin foundation: the admin menu, a settings page pre-filled with the dealership's
details, and safe activation, deactivation, and uninstall behavior.

Planned features (added in later phases):

* Trailer management with a custom post type and organized admin fields
* Taxonomies for trailer type, manufacturer, condition, availability, and features
* Front-end inventory listings with search, advanced filtering, and sorting
* Individual trailer detail pages with image galleries
* Inquiry forms with spam protection and lead storage in WordPress
* Email notifications for new leads
* SEO-friendly URLs, metadata, and JSON-LD structured data
* Shortcodes and, later, Gutenberg blocks
* CSV import and export

This plugin does NOT require WooCommerce, Advanced Custom Fields, Elementor, or
any paid plugin.

== Installation ==

1. Copy the "little-river-trailer-inventory" folder into wp-content/plugins/,
   or upload a ZIP of that folder via Plugins > Add New > Upload Plugin.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to Settings > Permalinks and click "Save Changes" to refresh URL rules.
4. Open the "Trailer Inventory" menu and review the Settings page.

== Data & Privacy ==

Deactivating the plugin never deletes any data.

When the plugin is deleted from the Plugins screen, data is preserved by default.
Data is removed only if an administrator has explicitly enabled
"Remove all data on uninstall" on the plugin's Settings page.

== Third-Party Code ==

This plugin includes no third-party code libraries. All code is original and
licensed under GPLv2 or later.

== Changelog ==

= 2.9.17 =
* Standardized the trailer card bottom section so every card lines up. The stock
  number now sits directly above the price, and the price sits on the lower-left
  across from the View Details button on the lower-right, in one aligned footer
  row. Cards in a row are equal height and their bottoms line up regardless of
  title length, number of specs, or missing fields.
* Fixed in the single shared card (templates/content-trailer.php), so the change
  applies everywhere the card is used: main inventory, Trailer Type archives, the
  featured carousel and static featured grid, related trailers, and any inventory
  shortcode. No separate per-page card was patched.
* Missing stock number hides cleanly (no empty "Stock #"); missing price keeps the
  current fallback in the same footer spot; sale + crossed-out regular price stay
  together on the left; the footer stacks (price above a full-width button) on
  very narrow cards. Price keeps the brand red (#a8321d).
* No changes to pricing calculations, stock data, badges, image ratio, sorting,
  filtering, pagination, carousel behavior, shortcodes, schema, forms, or the CRM.

= 2.9.16 =
* New: a built-in User Guide / training center under Trailer Inventory → User
  Guide. Plain-English, non-technical help written for dealership staff.
* Includes a Start Here quick-start, step-by-step lessons (adding trailers,
  photos, categories/brands, leads, marking sold, contact form) as searchable
  accordions, a live search box, small HTML/CSS flow diagrams (no images), a
  troubleshooting section, FAQs, and support details pulled from your Settings.
* Accurate Shortcode Reference generated from the plugin's real shortcodes, each
  with a plain description, where to use it, options, an example, and one-click
  Copy buttons (plus Copy All). A developer-only audit (WP_DEBUG) flags any
  registered shortcode that is missing from the guide.
* Read-only and additive: no changes to inventory, leads, forms, shortcodes,
  templates, the database, or any front-end behavior. Assets load only on the
  guide page. Admin-only (manage_options).
* Note: this is the release the brief referred to as "v2.9.14"; it ships as
  2.9.16 because 2.9.14 and 2.9.15 (contact form + editable form text) shipped
  first. Version numbers only move forward.

= 2.9.15 =
* New: the contact form's Heading, Description, and Consent text are now editable
  in the admin (Trailer Inventory → Settings → Leads), so the dealership can meet
  any wording or compliance requirements without touching code. Consent Text was
  already customizable; added Contact Form Heading and Contact Form Description.
* These apply to the general contact form ([lrti_contact_form]); values are
  sanitized on save and fall back to sensible defaults if left blank. No changes
  to trailer-attached inquiry forms, the database schema, or existing settings.

= 2.9.14 =
* New: the trailer inquiry form can now be reused as a general contact form on
  any page (Home, Contact, etc.) — no separate contact-form plugin needed. Use
  the shortcode [lrti_contact_form], or [trailer_inquiry general="yes"].
* General submissions are stored as leads in the same CRM (with no trailer
  attached), send the same staff notification (with a "New Contact Inquiry"
  subject), the customer confirmation, and use the same spam protection
  (honeypot, rate limiting, nonce, consent) and validation as trailer inquiries.
* Trailer-attached inquiry forms are unchanged. No database, meta key, option,
  post type, taxonomy, URL, or existing-shortcode changes; the contact form is
  additive.

= 2.9.13 =
* Housekeeping & stabilization release — the final 2.9.x version before v3.0.0.
  No functional or visual changes; every page and admin screen behaves and looks
  exactly as in 2.9.12.
* Removed two obsolete template partials that were no longer loaded by any
  template, shortcode, or loader (templates/single/summary.php and
  templates/single/breadcrumbs.php). Their content was superseded by the shared
  public hero in 2.9.8.
* Removed orphaned CSS: the legacy .lrti-taxhero-* rules from the pre-hero
  Trailer Type archive, which no template referenced anymore.
* Verified the codebase is free of debug artifacts (no console.log, var_dump,
  print_r, debugger, alert, TODO/FIXME, or stray die/exit); the remaining
  error_log() calls are intentional, guarded diagnostics and were kept.
* Re-verified brand-color compliance (dealership red only; no pink/magenta or
  WordPress blue for plugin controls), PHP lint, and JS syntax across the plugin.
* No database, meta key, option, post type, taxonomy, URL, shortcode, AJAX, or
  template-loader changes. Fully backwards compatible.

= 2.9.12 =
* Featured carousel: the Previous/Next buttons were showing the theme/Elementor
  accent (pink), because some themes style all <button> elements — including
  their :focus/:active states — with higher specificity than the plugin rule.
  The nav buttons are now forced to the official brand red (#a8321d, hover
  #842615, white arrow) across every state, scoped only to the carousel button
  so no other buttons are affected. No other changes.

= 2.9.11 =
* Featured carousel: replaced the thin text arrows with crisp SVG chevrons and
  hardened the Previous/Next buttons to the official brand red (#a8321d, hover
  #842615) with an explicit hex fallback, so they render branded even if theme
  cascade or CSS variables are unavailable. Smooth 0.5s slide, autoplay, and the
  infinite loop are unchanged. Default interval remains 4500ms (configurable via
  interval="…"). No other changes.

= 2.9.10 =
* Featured inventory is now a carousel. [lrti_featured_inventory] auto-advances
  one card at a time from right to left in a seamless infinite loop, and the
  trailer order is randomized on each page load so every featured unit gets a
  turn near the front. Shows 4 cards on desktop, 2 on tablet, 1 on mobile.
* Accessible + polite: pauses on hover and keyboard focus, respects
  prefers-reduced-motion (no auto-motion for those users), has brand-red
  Previous/Next buttons, and rebuilds responsively on resize. Degrades to the
  static card grid if JavaScript is off or only one featured trailer exists.
* New attributes on the featured shortcode: carousel="yes|no",
  randomize="yes|no", interval="4500" (ms), and the pool limit was raised to 12.
  The generic [lrti_inventory_cards] grid is unchanged (carousel off by default).
* CSS/JS only — no database, query, URL, or card-template changes.

= 2.9.9 =
* Fixed: status badge colors (New, Used, Featured, Sale) were missing on the new
  public hero. The brand color variables are scoped to the inventory/single
  wrappers, but the hero renders outside them, so var()-based badge colors
  resolved to nothing (only the hard-coded green "In Stock" showed). The hero now
  carries its own brand variables and the badge colors include hex fallbacks, so
  Featured (gold), Sale (brand red), New (navy), Used (gray), plus the
  availability colors all display correctly again — on the hero and on cards.
  The "New" badge uses a lighter navy-slate on the dark hero so it stays visible.
* Fixed: homepage category ordering. Display Order now treats 0 as "no
  preference," so a type with Order 1 sorts first and untouched types (Order 0)
  fall to the end alphabetically. Previously 0 sorted before 1, which pushed a
  type you set to 1 to the back. Tip: number the types you care about 1, 2, 3…
  and leave the rest at 0.
* CSS/logic only — no database, URL, query, or template-structure changes.

= 2.9.8 =
* New: a single shared Public Hero band used by every plugin-generated public
  page (Inventory archive, Trailer Type archive, Single Trailer — and any future
  archive). One reusable renderer, twc_render_public_hero(), plus a theme-
  overridable partial templates/partials/public-hero.php. No duplicated hero
  markup or CSS across templates.
* The hero shows breadcrumbs, exactly one H1, an optional description, optional
  meta (label/value, non-empty only), and optional status badges — on the dark
  navy band already used by the Inventory page (same background variable), with
  60px top/bottom padding and 40px below.
* Inventory archive: the in-body H1 header is gone; the body now begins with the
  filters, sort, and grid. Trailer Type archive: the H1/description move into the
  hero (taxonomy description → Archive Intro meta → nothing, no filler); the
  trailer count and Back to All Inventory sit in the content area above the grid.
* Single trailer: title, breadcrumbs, manufacturer, stock #, condition/status
  badges, and excerpt now live in the hero; the body begins with the gallery,
  price card, and quick specs (no duplicated header). Meta shows only fields
  that exist; the excerpt shows only when set.
* Badges remain color-coded (In Stock green, Featured gold, Sale brand red, New
  navy, Used gray, Sold dark red, Sale Pending amber, Coming Soon blue-gray).
  Fully responsive: badges/meta/breadcrumbs wrap and stack on mobile; one CSS
  file, no duplicate assets. No changes to data, URLs, SEO permalinks, queries,
  or breadcrumb schema.

= 2.9.7 =
* New: a custom, dealership-style archive for Trailer Type pages (e.g.
  /trailer-type/dump-trailers/). It replaces the plain WordPress taxonomy archive
  with a filtered-inventory experience — breadcrumb (Home › Inventory › Type),
  a clean H1 (just the type name, no "Trailer Type:" prefix), an intro, an
  available-count line, a Back to All Inventory link, the SAME inventory cards /
  grid / sort / pagination as the main Inventory page, and an optional "About"
  section below. URLs, the taxonomy, trailer data, SEO, and permalinks are
  unchanged; if the template is missing it falls back to the theme (never fatal).
* Reuse-first: the archive drives the shared Filters engine (results-only, no
  sidebar) constrained to the current type via the query base — no duplicated
  card layout, query logic, or CSS. Sorting uses a GET form so it always keeps
  the current type in the URL.
* Trailer Types now have Archive Page fields (Trailer Inventory → Trailer Types →
  Edit): Archive Heading, Archive Intro, Archive Hero Image, Archive Content
  (WYSIWYG), Empty State Message, and a reserved SEO Summary — all stored in term
  meta (no new tables). Empty types show a polished "No [Type] Available Right
  Now" state with Browse Inventory / Contact buttons.
* Assets load only on the taxonomy archive (inventory + filters CSS; no AJAX
  filter script). Schema (ItemList + Breadcrumb) already covers these pages.
* Works automatically for every current and future Trailer Type; nothing is
  hardcoded.

= 2.9.6 =
* New: dynamic homepage trailer category cards driven by the existing Trailer
  Types taxonomy (additive — no changes to inventory, taxonomy registration,
  trailer URLs, filtering, archives, or SEO; no custom tables; term meta only).
* Each Trailer Type now has a "Homepage Category Card" section (Trailer Inventory
  → Trailer Types → Edit) with: a Media Library card image (stored as an
  attachment ID, with Select/Remove and a live preview), a "Show on homepage"
  checkbox, a numeric Display Order, an optional card Label, and an optional
  Destination URL override. Saves are nonce-verified, capability-checked, and
  sanitized; image IDs and URLs are validated.
* Trailer Types list gains Image, Homepage (Yes/No), and Order columns
  (alongside the existing Name and Count columns).
* New shortcode [twc_trailer_categories] (attributes: columns, limit, heading)
  builds the category grid from Trailer Types marked "Show on homepage," sorted
  by Display Order. Each card is a single clickable anchor linking to the
  destination override or the Trailer Type archive, with a cover-fit image,
  auto alt text ("[Type] available from Little River Equipment Sales"), keyboard
  focus, and a placeholder image when none is set. Responsive 4 / 2 / 1 columns.
* Assets load only where needed: the media picker JS on the Trailer Types screens,
  and the grid stylesheet only when the shortcode renders.

= 2.9.5 =
* Lead Detail layout: the Customer and Inquiry panels now sit side by side (50/50)
  on desktop instead of stacking full width, so staff can read the inquiry while
  seeing customer details without scrolling. Internal Notes and the Activity Log
  remain full width below; the sidebar and Lead Summary header are unchanged.
* CSS-only change: the existing Customer and Inquiry metaboxes are placed in a
  two-column CSS grid (Notes and Activity span both columns). No rendering
  functions were rewritten, no markup duplicated, and no JavaScript is used for
  layout. Scoped to 2-column screen mode; collapses to a single column on small
  screens (and respects the Screen Options 1-column setting).
* The inquiry message is now the focal point of its panel (roomy padded panel,
  1.6 line-height, 14px), with a small uppercase "Message" label.
* No database, post-meta, AJAX, save, nonce, capability, notification, workflow,
  status, activity-log, follow-up, or Quick Actions changes. Metabox collapse,
  drag-sort, and Screen Options continue to work. Brand audit clean.

= 2.9.4 =
* Final admin UI polish (presentation only — no functionality, database, CPT,
  taxonomy, meta keys, AJAX endpoints, lead/inquiry/notification logic, or public
  behavior changed; no data migration).
* Buttons are ~10–15% lighter and standardized (radius, padding, font, hover) with
  smooth 150–160ms transitions and a subtle active press; row action buttons are
  slightly more compact.
* Status badges restyled to modern SaaS pills: ~22–24px tall, rounded, 600 weight,
  subtle tinted backgrounds (brand / information / success / warning / danger /
  muted) with no gradients or shadows — applied everywhere badges appear.
* Full-row hover now fades smoothly and reveals a subtle brand left-accent; unread
  rows keep their tint. Tightened table density (Stock #, Age no-wrap; trimmed cell
  padding) for more information per screen without cramping.
* Polished empty states for the Activity Log (and existing Notes / follow-up),
  each with an icon, muted title, and helpful description.
* Respects prefers-reduced-motion (transitions/transforms disabled).
* Brand audit clean: no WordPress blue, no pink/magenta, no Elementor colors;
  custom controls use #a8321d with #842615 hover; the only blue is the approved
  Information accent.

= 2.9.3 =
* Corrective release for the Leads List (admin layout/CSS/JS only — no data,
  CPT, taxonomy, meta keys, statuses, transitions, read/follow-up/assignment/
  notification/duplicate/CSV/activity/note/archive logic, capabilities, nonces,
  sanitization, escaping, public templates, filters, or shortcodes changed).
* Fixed the root cause of the stacked row actions: the action container now uses
  flex-wrap:nowrap with width:max-content, so View Lead / Mark Contacted / More
  stay on one horizontal line and the Lead column is sized to fit them (min
  280px) instead of collapsing the buttons. Mark Contacted still hides when the
  status makes it inappropriate.
* Because the table is wide, it now sits in a horizontal scroll wrapper so it
  scrolls cleanly instead of squeezing. The More menu is positioned as fixed
  when open so it escapes the scroll wrapper and is never clipped; it keeps full
  keyboard support, aria-expanded/aria-haspopup, Escape and outside-click close,
  and closes on scroll/resize. All actions and their nonced URLs are unchanged.
* Toolbar is now one cohesive card: bulk actions, filters, the Filter button
  (brand primary), Export CSV (brand secondary), and the search box (relocated
  into the card) share consistent 36px control heights and wrap cleanly.
* Compact Customer (icon + name / icon + email, clickable, title fallback) and
  Trailer (title + Manufacturer · Type) cells, reduced row height, and
  middle-aligned data columns. Unread rows keep the subtle brand tint + accent.
* Lead Detail screen is unchanged; only shared list CSS/JS was touched.

= 2.9.2 =
* CRM polish release (admin presentation only — no data, CPT, taxonomy, meta
  keys, statuses, transitions, read/follow-up/assignment/notification/duplicate
  logic, CSV, activity/note storage, archive, capabilities, nonces, sanitization,
  escaping, public templates, filters, shortcodes, or uninstall behavior changed).
* Leads List: the View Lead / Mark Contacted / More action bar now stays on one
  horizontal row on desktop widths (the Lead column was widened and buttons kept
  compact). Added a muted form-type subtitle under the lead title, and person +
  email icons in the Customer column. Rows are shorter and middle-aligned.
* Filter toolbar is now a white card with consistent 34px control heights, a
  brand-primary Filter button, and a brand-secondary Export CSV button.
* Lead Detail: header gets a little more breathing room; the Management sidebar
  now has "Lead", "Contact Activity", and "Follow-Up" section labels; the
  Internal Notes composer is a bordered panel ("Add Internal Note" + helper +
  friendlier placeholder) with a polished empty state; the More neutral button
  hover matches the spec.
* Activity timeline markers are now color-coded by event type (created = brand,
  opened = information, notification = success, failure = danger, status =
  warning). The Publish box Update button uses the brand color; Move to Trash
  stays clearly destructive.
* Brand audit clean: no pink/magenta; custom controls use #a8321d with #842615
  hover; the only blue is the approved Information color.

= 2.9.1 =
* Leads List row actions no longer stack into a tall wall of links. Each row now
  shows a compact CRM action bar — View Lead, Mark Contacted (when applicable),
  and a More menu — so rows stay short and scannable. Every previous action
  (Quick Edit, Mark Won/Lost/Spam, Mark Read/Unread, Schedule Follow-Up, Resend
  Notification, View Trailer, Archive, Duplicate, Delete Cache, Trash, and any
  third-party action) is preserved inside the accessible More dropdown with its
  original URL, nonce, capability, and handler untouched.
* The More menu is keyboard accessible (aria-haspopup/aria-expanded, arrow keys,
  Home/End), closes on Escape (returns focus to the trigger) and on outside
  click, repositions away from the viewport edge, and degrades to a hover/focus
  disclosure when JavaScript is off. Trash is separated by a divider and uses
  danger styling.
* Leads List polish: stacked Customer (name + clickable email) and Trailer
  (title + manufacturer/type) cells, compact rows, unread rows with a subtle
  brand tint + left accent (no pink), and consistent filter-toolbar control
  heights.
* Lead Detail refinements: the native WordPress title field is hidden on the
  lead screen (value preserved) so the CRM header is the clear page title;
  "Add New" now reads "Add Lead" instead of generic post wording; the sidebar
  Quick Actions no longer duplicate the header's Call/Email/View buttons and
  instead offer Copy Email, Copy Phone, Resend Notification, Mark Read/Unread,
  and Archive; successful notification events show a green timeline marker.
* Reusable small-button variant added to the design system. New file
  admin/js/leads-list.js loads only on the Leads list screen.
* No data architecture, meta keys, statuses, actions, inquiry/CSV/search/filter/
  sort/pagination/bulk logic, notifications, capabilities, nonces, escaping, or
  public templates were changed. Brand audit: no pink/magenta; custom controls
  use #a8321d with #842615 hover; the only blue is the approved Information color.

= 2.9.0 =
* Began the Modern Dealership CRM admin experience rewrite (Part 1: Lead Detail
  + the shared design system). UI/presentation only — no data architecture, post
  types, taxonomies, meta keys, inquiry storage, notifications, duplicate
  detection, permissions, nonces, sanitization, escaping, lead/assignment/
  follow-up logic, archive queries, CSV export, public templates, forms, or
  shortcodes were changed.
* IMPORTANT FIX: the admin stylesheet now also loads on the Leads and Trailer
  edit/list screens (previously it only loaded on the plugin's own menu pages),
  so the CRM styling actually applies where you edit leads and trailers. Assets
  still load only on plugin screens — never globally across wp-admin.
* Lead Detail: a modern CRM header now leads the screen (customer name, linked
  trailer, status badge, lead age, submitted, assigned, follow-up state, and
  Call / Email / View Trailer actions). Metaboxes render as clean cards on an
  app-style page background; the Trailer card shows a photo thumbnail with a
  polished "no longer available" state; the Notification card uses Sent/Failed/
  Pending badges; the Publish box is de-emphasized (still fully functional).
* Added a reusable design system (scoped to plugin surfaces): brand tokens,
  button classes (primary/secondary/neutral/danger, focus + disabled states),
  a badge system (success/warning/danger/info/muted/brand), empty states, and
  card styling — all on the approved dealership palette with the approved
  Information accent. No pink, magenta, or default-blue for custom controls.
* Remaining screens (Leads list header/summary/filters/row-actions, Overview
  dashboard, Inventory list, Add/Edit Trailer tabs, Settings) will be modernized
  in follow-up 2.9.x sub-sprints against this same design system.

= 2.8.1 =
* Premium CRM admin polish (UI only — no logic, data, CPT, storage, emails,
  notifications, permissions, search, filters, or public templates changed).
* Customer card now leads with a large name + icon header, then clean icon rows
  (email/phone are click-to-contact).
* Quick Actions are consistent brand-filled buttons (Call, Email, View Trailer,
  Copy Email, Copy Phone) with dark-brown hover and brand focus rings.
* Follow-up alerts now use the approved semantic palette: Overdue #c62828,
  Due Today #e6a100, Upcoming #2e7d32. Added a "No follow-up scheduled" empty
  state in the Management panel.
* Inquiry card gains subtle section dividers; the Add Note field reads as the
  top of the notes conversation; leads table rows are taller and easier to scan.
* Plugin version now shows in the admin footer on plugin screens.
* Verified project-wide: no pink, magenta, purple, Elementor, or default-blue
  colors in any plugin stylesheet. All 2.8.0 CRM UI and earlier features remain.

= 2.8.0 =
* Modern admin CRM experience (UI only — no functionality, data, post types,
  meta keys, or business logic changed). Uses WordPress core Dashicons and
  avatars, so nothing heavier is loaded.
* Lead metaboxes now read as clean CRM cards (brand border, soft header, roomy
  padding) on the lead screen only.
* Customer metabox rebuilt as an icon-row card: Name, Email, Phone, Preferred
  Contact, Consent, Lead Age, and Submitted, with email/phone click-to-contact.
* Activity Log timeline now shows an icon marker per event (created, notification,
  viewed, status, assigned, follow-up, note, archived), newest first.
* Internal notes render as conversation cards with the author's avatar, name,
  date, time, and content; add-note is unchanged.
* Plugin button hierarchy: brand focus rings and outlined secondary buttons,
  scoped to plugin screens so core wp-admin styling is untouched (no WP blue).
* Overview cards, headings, and spacing refined; responsive rules keep cards and
  action buttons stacking cleanly on tablet / narrow windows.
* All 2.7.x CRM features, inventory, gallery, specs, inquiry forms, CSV export,
  shortcodes, SEO, and settings remain exactly as before.

= 2.7.1 =
* CRM UI/UX polish (brand palette only — no pink, magenta, purple, or browser
  default focus/hover colors anywhere; verified by a project-wide hue audit).
* Activity Log is now a vertical timeline with brand dots and a connector line,
  newest first (existing data unchanged).
* Internal notes render as brand-tinted conversation cards (author, date, time,
  content); inline styles moved to scoped classes.
* Status badges are larger with consistent padding and rounded corners.
* Quick Actions: "Call Customer" is now the primary brand-filled button; Email,
  View Trailer, and Copy actions are secondary.
* Added Lead Age (Today / Yesterday / N days) to the leads table and the lead
  detail; added follow-up alert badges (Overdue / Due Today / Upcoming) on the
  table and detail. Assigned staff now shows as a brand pill.
* Customer / Trailer / Inquiry read-only panels get row dividers and better
  spacing; message blocks sit on a light-gray card.
* Leads table, Recent Inquiries, and Recent Activity get brand row-hover and
  tighter spacing. Plugin-scoped primary buttons (Quick Actions, Settings) use
  the brand color instead of WP blue.
* CSS-only and light-markup changes; all 2.7.0 CRM functionality is unchanged.

= 2.7.0 =
* Dealer CRM foundation (Sprint 1). Expanded the lead pipeline to a full
  dealership workflow: New, Contacted, Left Voicemail, Follow-Up Needed,
  Qualified, Quote Sent, Appointment Scheduled, Negotiating, Deposit Received,
  Sold, Lost (Spam retained). Existing leads keep their status; "Won" already
  displays as "Sold". New inquiries still default to New. No data migration.
* Each new status has an on-palette badge (no pink/purple/blue) on the leads
  table, detail screen, dashboard, and recent inquiries.
* Follow-up management: added Follow-Up Time and Reminder Priority (Low, Normal,
  High, Urgent). Follow-up date changes, assignment changes (now recording who
  assigned and when), status changes, notes, views, and email sends are recorded
  in the read-only per-lead Activity Log (newest first) that already existed.
* Dashboard widgets added to Overview: Today's Leads, Sold This Month, Lost This
  Month, Top Assigned Salesperson, and a Recent Activity feed (bounded, no N+1).
  Existing Unread, Follow-Ups Due, per-status, and Leads This Month cards remain.
* Lead list filters added: Today's Follow-Ups, Overdue Follow-Ups, and Reminder
  Priority. Lead search now also matches internal notes and the notes log.
* Internal notes remain chronological and append-only. CSV export, inquiry forms,
  inventory, gallery, single pages, shortcodes, and settings are unchanged.

= 2.6.4 =
* Fixed the specification filter accordion showing a bright pink fill with white
  text on the focused/clicked row (e.g. "Hitch & Hardware"). The theme/Elementor
  was applying a pink background on the button's :focus and :active states, which
  the plugin had not overridden. The neutral gray background and dark text are
  now forced on :focus, :active, and :focus-visible (with the open state taking
  precedence), so the only focus decoration is the brand red-brown outline. Base
  background and text color are also forced against theme button styles.
* CSS-only change. No markup, logic, data, or other styling affected. Styles stay
  versioned by the plugin version for cache-busting — purge any page cache after
  updating.

= 2.6.3 =
* Project-wide color audit for brand consistency. Removed the only unapproved
  accent colors found: the lead-status badges used purple (#6d28d9, Appointment)
  and blue (#1d4ed8, Qualified) — both replaced with the approved brand family.
  Normalized an older brand-red variant (#8f2f1b, used by warnings, the required
  asterisk, and the New badge) to the approved #a8321d so there is a single
  brand red. Switched the admin info-hint's WordPress-blue accent to brand gold.
* Reworked the lead-status palette to stay on-palette while keeping established
  semantics: New #a8321d, Contacted #b45309, Follow-Up #8a5a12 (added — was
  previously unstyled), Qualified #842615, Appointment #6f2115, Sold #2f6f3b
  (green), Lost #6b7280 (gray), Spam #111111. All badge text stays white with
  AA contrast.
* Verified no pink, magenta, purple, fuchsia, rose, or browser-default blue
  remains in any stylesheet (programmatic hue sweep) or JavaScript. Front-end
  gallery, filter accordion, buttons, links, and form focus were already on the
  brand palette and are unchanged. Checkboxes/radios use accent-color: brand.
* No markup, PHP logic, queries, data, or features changed — CSS-only. Styles
  remain versioned by the plugin version constant for cache-busting.

= 2.6.2 =
* Front-end Specifications filter accordion: fixed the rows that were still
  showing a colored triangle and a bordered/pill look on some themes. Rules are
  now scoped under .lrti-inventory (so they outrank theme/Elementor button
  styling) and the toggle button plus its title span are hard-reset so nothing
  can draw a second border, outline, box-shadow, or pink box around the label.
* Each heading is one full-width neutral gray row (#f3f4f5) with a thin gray
  border (#cfd3d7), a small GRAY disclosure triangle (#646970) that rotates down
  when open, and a subtle brand-tinted open state (rgba(168,50,29,0.08)). Brand
  red/brown is used only for the focus ring, the open-state triangle, the active
  count, and input focus — never as the default row/label color, and never pink.
* No markup, field mapping, query logic, or JS behavior changed; this is a
  styling correction only. Apply/Reset buttons and the one-line Sort control are
  unchanged. No other plugin area touched.

= 2.6.1 =
* Added a "Recent Inquiries" panel to Trailer Inventory → Overview (below the
  lead stat cards, above Manage Trailers). Shows the five newest leads with
  Customer, Trailer, Stock #, Form Type, Status, Assigned To, Follow-Up, and
  Submitted (site timezone/format). Each row links to the lead detail screen;
  trailers link to their edit screen (or show "Deleted Trailer" safely). Unread
  leads are marked with a brand-red dot and bold name (never pink); overdue
  follow-ups are flagged. Includes an empty state and a "View All Leads" button.
* Queries only the five newest leads and is gated by the lead-viewing capability
  (edit_lrti_leads); all output is escaped. No storage change — reuses the
  existing lrti_lead custom post type. Verified a single Leads submenu.

= 2.6.0 =
* Rebuilt the front-end Specifications filter to match the "Add New Trailer"
  specification accordion: full-width neutral rows with a light-gray border, 4px
  radius, a right/down chevron (no detached plus sign), the whole row clickable.
  Removed the outlined pill-button look.
* Reordered/expanded the accordion groups to follow the admin structure: Weight &
  Capacity, Dimensions (now incl. Floor Length), Axles & Suspension, Brakes &
  Running Gear, Hitch & Hardware, Frame (Side Height), Wheels & Tires, Lighting &
  Electrical, Toolbox, Stake Pockets & Tie-Downs, Body & Flooring, Ramps,
  Appearance (Exterior + Interior Color). Empty groups are hidden.
* Groups auto-open when they contain an active filter and show a small active
  count badge; entered values are preserved when a group is collapsed.
* Centralized brand color tokens (--lrti-brand-primary/#a9301b, hover #842614,
  gold #d69a3a, plus neutral bg/border/text) on the plugin wrappers so a theme
  accent can never bleed pink into plugin controls. Checkboxes/inputs use the
  brand accent and a brand focus ring. No pink/magenta values exist in the plugin
  CSS or JS.
* No changes to filtering logic, cards, sorting, pagination, chips, single pages,
  gallery, inquiry forms, leads, CSV, shortcodes, or admin.


= 2.5.0 =
* Fixed the "Sort by" control wrapping onto two lines; label, select, and Sort
  button now stay on one line (flex, nowrap) and remain aligned on all sizes.
* Filter controls (Show Filters, Apply, Reset, Sort, Clear All, chip remove) now
  use the dealership red/brown brand color instead of the theme's pink accent,
  with primary/outline styles and visible keyboard focus.
* New Specifications accordion in the filter sidebar: collapsible groups (Weight &
  Capacity, Dimensions, Axles & Suspension, Hitch & Hardware, Wheels & Tires,
  Lighting & Electrical, Toolbox, Stake Pockets & Tie-Downs, Ramps, Body &
  Loading, Brakes). Groups default closed and auto-open when they contain an
  active filter; selected values are preserved when toggling.
* Added spec filters wired through the existing AJAX engine with AND logic:
  GVWR, Empty Weight, Payload, Overall Length, Width, Height, Axle Count, Axle
  Rating, Suspension Type, Pull Type, Coupler Type, Adjustable Coupler, Jack
  Type, Tire Size, Tire Load Range, Wheel Material, LED Lights, Electrical
  Connector, Toolbox Included, Toolbox Type, Stake Pockets, Ramps Included, Ramp
  Type, Flooring, Exterior Color, Brake Type. Select/yes-no options are populated
  only from values used by published trailers (no empty or invented options),
  gathered in a single cached query. Each active spec filter shows a removable
  chip; Clear All still clears everything.
* Tightened sidebar spacing. No changes to admin, leads, CSV, shortcodes, single
  pages, schema, gallery, or inquiry forms.


= 2.4.0 =
* Inventory archive polish: wider desktop layout (~1460px) with a ~310px sidebar
  and more room for cards; subtle card hover lift, shadow, and 4% image zoom
  (250ms). The results header now reads "Showing N trailers currently available".
* Sorting expanded with Length, GVWR, and In Stock First (existing sorts kept);
  the selected sort is preserved across filtering.
* Keyword search now also matches the description, features, and specifications
  (e.g. toolbox, LED, GVWR, length, width) in addition to title, model, stock
  number, manufacturer, and type.
* Empty state now offers Reset Filters, View All Inventory, and Contact Us.
* Mobile refinements: reduced header/toolbar whitespace, near-full-width cards
  with 16px padding, shorter card image, larger price, and a full-width View
  Details button.
* Archive SEO: added CollectionPage, ItemList (with per-card Product/Offer), and
  BreadcrumbList JSON-LD on the inventory and taxonomy archives. Single-trailer
  schema is unchanged.
* No admin, lead, CSV, shortcode, quick-edit, gallery, or inquiry functionality
  was changed.


= 2.3.3 =
* Fixed the Plugin URI (the plugin's "Visit plugin site" link) which still
  pointed at a placeholder .example domain; it now points to
  https://trendwiseco.com. Header/display change only — no functionality or data
  affected.


= 2.3.2 =
* Rebranded the plugin: renamed to "TWC Trailer Inventory for Little River
  Equipment Sales LLC" and set the author to Trendwise Co.
  (https://trendwiseco.com). These are display/header changes only — the plugin
  slug, text domain, meta keys, and all data are unchanged.
* Single trailer page: the manufacturer line now shows a "Manufacturer:" label
  (black) before the brand name (red/brown), on one line.


= 2.3.1 =
* Fixed a duplicate "Leads" item under the Trailer Inventory admin menu. The lead
  post type registered with show_in_menu set to the Trailer Inventory parent,
  which WordPress auto-links, while the plugin also added an explicit Leads
  submenu — producing two entries. The post type is now show_in_menu => false
  (show_ui stays true), leaving the single explicit Leads submenu. No lead data,
  slug, capability, badge, filter, or CSV behavior changed.
* Added the manufacturer brand on the single trailer page, shown directly below
  the title and above the condition/availability badges, in the dealership
  red/brown brand color (class .lrti-single-manufacturer). It uses the assigned
  trailer_manufacturer term and renders nothing when no manufacturer is set.


= 2.3.0 =
* Follow-up tracking: added Last Contacted, Next Follow-Up (date), and Follow-Up
  Notes to each lead, a "Follow-up due" filter, overdue highlighting in the list,
  and a "Follow-Ups Due" card on the Overview dashboard. A lead is overdue when
  its Next Follow-Up date has passed and its status is not Sold, Lost, or Spam.
* Structured Internal Notes: notes are now an append-only history (text, author,
  date/time, newest first) with an Add Note form, instead of a single editable
  box. The previous single note field is preserved read-only.
* Assigned staff: the assignment dropdown now lists only users who can manage
  leads; added an "Assigned To" column and an assigned-staff (incl. Unassigned)
  filter to the Leads table.
* Quick Actions on the lead screen: Call, Email, and View Trailer (new tab) links
  plus Copy Email / Copy Phone buttons.
* Leads table: added Read, Assigned To, and Follow-Up columns.
* CSV export now also includes Next Follow-Up, Follow-Up Notes, and Internal Note
  Count (Lead Status, Read Status, Assigned Staff, and Last Contacted were already
  included).
* Dashboard: added an Unread Leads card and relabeled "Won" as "Sold".
* No lead data was renamed or deleted; existing leads keep working. New fields are
  added only when set. Leads remain private (not publicly queryable, excluded from
  search/feeds).


= 2.2.0 =
* Added a Featured column to the All Trailers list with a one-click star toggle.
  Clicking the star features/unfeatures a trailer instantly (AJAX), with no need
  to open the editor; the change is capability- and nonce-checked and updates the
  same _lrti_featured value used by the front-end Featured badge and the
  [lrti_featured_inventory] shortcode.
* The Featured column is sortable (featured-first) without hiding non-featured
  trailers, and Featured is now available in Quick Edit and as "Mark as Featured"
  / "Remove Featured Status" bulk actions.
* [lrti_featured_inventory] now opens trailer links in a new tab by default via a
  new_tab="yes" attribute (set new_tab="no" to keep same-tab). Implemented with a
  new lrti_card_link_attributes filter, so other inventory grids and the archive
  are unaffected. [lrti_inventory_cards] defaults to same-tab (new_tab="no").
* No data, taxonomy, URL, or setting changes. The featured meta key is unchanged,
  so existing featured trailers and badges are preserved.


= 2.1.0 =
* Added filtered CSV export for leads. An "Export CSV" button above the Leads
  table exports the leads currently shown (respecting the status, form-type,
  notification, read/unread, archived, and search filters), as a UTF-8 (BOM) file
  that opens cleanly in Excel. Protected by capability + nonce; not publicly
  accessible. Extensible via lrti_lead_export_columns, lrti_lead_export_row, and
  lrti_lead_export_query_args.
* Added the [lrti_featured_inventory] shortcode: a branded section with an
  eyebrow, centered heading, and a responsive card grid (4/2/1) reusing the
  existing trailer card and pricing/image/badge logic. Excludes sold trailers by
  default; shows an admin-only message when no featured trailers exist. Added the
  companion [lrti_inventory_cards] shortcode for broader queries using the same
  renderer.
* Leads now track read/unread: new leads are unread, opening a lead marks it
  read, the menu badge counts unread leads, unread rows are emphasized, and Mark
  Read / Mark Unread are available as row and bulk actions.
* Added the Follow-Up status and an Archive workflow: archived leads are kept but
  hidden from the default view, with an Archived filter and Archive row/bulk
  actions. Added read/unread and active/archived filter dropdowns.
* Added an lrti_duplicate_lead_window filter alias for the duplicate-submission
  window.
* No trailer, lead, taxonomy, or setting data was changed; no public trailer URLs
  changed. Existing leads with no read flag are treated as read.


= 2.0.1 =
* Fixed "You need a higher level of permission" when opening Trailer Inventory →
  Leads. The lead post type uses custom capabilities (edit_lrti_leads, etc.), which
  were only granted to the administrator and editor roles during plugin
  activation. Installs that were updated across versions without deactivating and
  reactivating never received those capabilities, locking admins out of the Leads
  screen.
* Added an idempotent, self-healing recovery routine: on the next normal admin
  request the plugin compares a stored lead-capability version and, if older,
  re-grants all lead capabilities to the administrator and editor roles, refreshes
  the current user so the fix applies immediately (no reload or reactivation), and
  shows a one-time notice: "Trailer Inventory lead permissions were updated
  successfully." No unrelated capabilities are ever removed.
* Leads remain stored as private lrti_lead records (public => false,
  publicly_queryable => false, exclude_from_search => true); no public lead URLs,
  feeds, REST, or sitemap exposure. No trailer, specification, lead, or setting
  data was changed, and the public trailer pages were not touched.


= 2.0.0 =
* Added a large set of structured Specification fields so technical details no
  longer need to be typed into the Description: a Toolbox section; expanded Hitch
  and Coupler (adjustable hitch/coupler, ball size, adjustment/height ranges,
  safety chains, breakaway kit); a Lighting and Electrical section (LED lights,
  multi-select light types, connector, junction box, battery/box, solar); a Stake
  Pockets and Tie-Downs section; a full Wheels and Tires section (tire size/type/
  load range/ply/brand, wheel diameter/width/material/finish/bolt pattern, spare
  tire, count, notes); and an expanded Ramps section. All new fields are optional
  and shown publicly only when filled in.
* New collapsible admin groups on the Specifications tab: Wheels and Tires,
  Lighting and Electrical, Toolbox, Stake Pockets and Tie-Downs, Ramps.
* Public specification groups now render in a clear order (General, Dimensions,
  Weight and Capacity, Axles and Suspension, Brakes and Running Gear, Hitch and
  Hardware, Wheels and Tires, Lighting and Electrical, Toolbox, Stake Pockets and
  Tie-Downs, Body and Loading, Ramps, Additional Features). Empty rows and empty
  groups are hidden; "Not Specified" is never shown.
* Quick Specs can now include Tire Size and is capped at seven rows.
* Description editor shows guidance: use it for selling points; put technical
  details in the Specifications tab. Description content is never auto-populated.
* Fixed the missing Leads submenu. The lead post type used
  show_in_menu => 'lrti-overview' (a string), which WordPress does not auto-link,
  so the submenu is now registered explicitly under Trailer Inventory and shows
  even with zero inquiries. Inquiries are stored as records that persist across
  updates and appear immediately in Trailer Inventory → Leads.
* Removed the pink frame around the main gallery image (a theme/Elementor accent
  bleeding onto the image button). The gallery now uses a neutral #D7DCE1 frame,
  a red-brown keyboard-focus ring, and #A8321D active thumbnails.
* No existing trailer, taxonomy, lead, or setting data is changed. New fields use
  new meta keys only; no database schema change or migration is required.


= 1.9.0 =
* Fixed false "duplicate inquiry" warnings on a first legitimate submission. The
  duplicate lock is now created ONLY after the inquiry is validated, saved, and
  the email has been attempted — never on form load, validation failure, refresh,
  or before the record exists.
* The duplicate fingerprint now uses the trailer ID plus the normalized email and
  phone (never the IP address alone), with a 10-minute default window filterable
  via lrti_duplicate_window_seconds and configurable in Settings.
* Genuine duplicates now show a clearer message ("We already received this
  inquiry. Please allow our team a little time to respond.") and never create a
  second lead or resend the email; after the window passes, the same person can
  submit again.
* The submit button is disabled and shows "Sending…" during submission and is
  restored if validation or the server request fails, preventing double-click and
  slow-network duplicates.
* New inquiry settings: Duplicate Submission Window (seconds), Default Inquiry
  Status, and Store Visitor IP address (hashed identifier only; raw IP is never
  stored). Existing dealership and lead settings are unchanged.
* Gallery/brand color corrected to the exact dealership red-brown (#A8321D,
  hover #842615, soft focus rgba(168,50,29,0.18)) across nav buttons, thumbnail
  active/hover/focus borders, the main image focus, and all lightbox controls.
  No pink or magenta remains, and no fallback pink values are left.
* No trailer data or existing leads are affected. Processing order is now
  validate → save → email → lock, so an inquiry is always stored even if the
  email fails.


= 1.8.2 =
* Fixed the inquiry form losing its field borders and its submit button becoming
  invisible (white-on-white) under themes or Elementor: form field, focus, and
  submit-button styling is now applied with plugin-scoped, high-specificity rules
  and tightly targeted !important so external CSS can no longer override borders,
  backgrounds, colors, or hide the button. The Send button always renders below
  the consent checkbox as a solid brown-red CTA.
* Eliminated the remaining pink highlight on the gallery: previous/next buttons,
  active/hover thumbnails, focus rings, and lightbox controls are now forced to
  the brand brown-red (#9f321d / #7f2818) with scoped high-specificity overrides.
* Gallery image switching is now smooth: the stage keeps a stable height, the
  current image stays visible until the next one is decoded (no white flash or
  collapse), images are preloaded (adjacent images prefetched) and swapped with a
  short opacity transition, and rapid clicks can no longer cause a race or a wrong
  image. The stage uses an appropriately sized image while the lightbox shows the
  full image.
* First gallery image now loads eagerly with high fetch priority; reduced-motion
  users get no transition.
* Inquiry form sits in normal document flow with a sensible max width and no
  overlap with the price/detail card.
* CSS/JS/markup only. No database, schema, SEO, lead, or email changes. Bumped
  asset version so browsers and page caches load the corrected files.


= 1.8.1 =
* Inquiry form restyled as a polished card: white background, light gray border,
  soft shadow, comfortable padding, branded 22px heading with an accent underline,
  and a helper sentence. Fields share a consistent 44px height, 1px borders, and a
  brown-red focus ring (no pink). The submit button now matches the site's primary
  brown-red CTA, with a darker hover and a full-width state on mobile.
* Gallery rebranded to the site's brown-red (#9f321d / #7f2818): previous/next
  buttons (44px, inside the stage edges, white icons, visible focus), active
  thumbnail border + soft ring, thumbnail hover/focus, and all lightbox controls.
  No pink remains.
* Thumbnails now scroll horizontally on small screens (no page overflow), use
  box-sizing: border-box to avoid layout shift, and expose aria-current="true" on
  the active thumbnail.
* Responsive polish: single-page grid uses safe minmax() tracks with min-width: 0
  children and stacks the price card below the gallery under 850px; the gallery
  stage keeps width: 100% with a stable aspect ratio.
* Centralized brand variables (--lrti-brand-primary, --lrti-brand-primary-dark,
  --lrti-brand-primary-soft, --lrti-border-light, --lrti-field-border,
  --lrti-text-dark). CSS/JS only — no database or schema changes. Gallery,
  lightbox, form submission, validation, leads, email, SEO, and schema behavior
  are unchanged.


= 1.8.0 =
* Gallery: the active image now fills the entire stage (object-fit: cover,
  centered) with no empty side space, while the lightbox shows the full,
  uncropped image (object-fit: contain). Single-image trailers open the lightbox
  correctly and hide unnecessary controls. New filters: lrti_gallery_aspect_ratio,
  lrti_gallery_image_position, lrti_gallery_main_image_size,
  lrti_gallery_lightbox_image_size. Navigation aria-labels clarified.
* Inquiry form redesigned into a compact, professional two-column dealership
  card (Name | Email, Phone | Preferred Contact, full-width Message + Consent),
  stacking to one column on tablet/mobile, with a heading, helper sentence, and
  a privacy note that auto-links the site Privacy Policy page when set. Stable
  anchor id lrti-trailer-inquiry.
* Message is now required; entered values are preserved after a validation error
  (including the no-JavaScript path). Successful submissions show a confirmation
  that includes the trailer title, scroll to it, and move focus to it.
* Check Availability / Request Information scroll to the form and focus the Name
  or Message field respectively; the Call button remains a tel: link.
* Specifications: tighter two-column grid with accent group headings; Quick
  Specs unchanged (six fields). Plain multi-line short descriptions render as a
  clean list.
* Similar Trailers now shows up to three, in a responsive 3/2/1 row, using the
  archive card. New filter: lrti_similar_trailers_query_args.
* Optional desktop-only sticky price card via the lrti_single_price_card_sticky
  filter (default false). Reduced excessive vertical spacing throughout.
* New form hooks: lrti_inquiry_form_before, lrti_inquiry_form_after,
  lrti_inquiry_form_before_submit, lrti_inquiry_form_after_submit,
  lrti_lead_created, lrti_lead_email_sent, lrti_lead_email_failed. New filters:
  lrti_inquiry_form_fields, lrti_inquiry_form_default_message,
  lrti_inquiry_form_consent_text, lrti_lead_notification_recipients.
* No database changes. All existing data, leads, SEO, and schema behavior are
  preserved.


= 1.7.0 =
* Lead generation: working Check Availability, Request Information, and (for sold
  units) Request Similar Trailers inquiry forms on trailer pages, plus a new
  [trailer_inquiry] shortcode. Forms submit via AJAX with a standard POST
  fallback, accessible inline errors, an error summary, and an aria-live success
  message. Multiple forms per page are supported.
* Each submission is validated server-side and stored as a private Lead record
  (new lrti_lead post type) tied to the verified trailer; the official stock
  number, title, and URL are pulled server-side and never trusted from the form.
* Spam protection: nonce/CSRF, honeypot, minimum completion time, maximum form
  age, privacy-safe rate limiting (hashed identifier, no raw IP stored),
  duplicate/idempotency protection. CAPTCHA is not required and can be added via
  filters.
* Dealer notification email on every submission, with an optional customer
  confirmation email. A delivery failure is recorded on the lead without losing
  the lead. No SMTP library is bundled (an SMTP plugin is recommended).
* Leads admin: list columns, status/form-type/notification filters, search by
  name/email/phone/stock/trailer, quick + bulk status actions, resend
  notification, an organized edit screen (Customer, Inquiry, Trailer,
  Management, Notification, Activity Log), a lightweight activity log, a New-lead
  menu bubble, and lead statistics on the Overview dashboard.
* Privacy: personal-data exporter and eraser (by email) and suggested
  privacy-policy text. Configurable retention with a daily cleanup event that
  never auto-deletes Won leads unless explicitly enabled; separate spam-lead
  cleanup window.
* New settings (Leads & Notifications): enable inquiry forms, customer
  confirmation + subject/message, consent text, success/error messages,
  retention period, honeypot, minimum completion time, rate-limit window/max,
  and spam-delete window.
* New capabilities (granted to Administrator and Editor on activation) gate all
  lead access. Leads are never publicly accessible; VIN and internal trailer
  notes are never included in forms, leads, or emails.
* Data safety: deactivation clears only the scheduled cleanup event; leads and
  settings are preserved. Full removal on uninstall (leads, cron, capabilities)
  happens only when the existing "Remove all data on uninstall" setting is on.


= 1.6.0 =
* Inventory cards refined: clearer hierarchy (title, manufacturer · type, quick
  specs, price with savings, stock, View Details), subtle image zoom on hover,
  and empty values hidden. Added lrti_before/after_inventory_card hooks and an
  lrti_inventory_card_fields filter.
* Archive filter sidebar reorganized into accessible, collapsible groups
  (Search, Trailer, Status, Price, Year, Specifications) with fieldsets/legends.
* Quick Specs trimmed to six high-value fields; Get Directions moved from the
  price CTAs to the dealership contact section.
* Single gallery gains touch-swipe support and lrti_before/after_single_gallery
  hooks; features now render as an accessible checklist.
* SEO: branded fallback Meta Title/Description plus full Open Graph and Twitter
  Card tags on single trailers when no supported SEO plugin is active. New
  filters: lrti_fallback_meta_title, lrti_fallback_meta_description,
  lrti_open_graph_data, lrti_fallback_social_image, lrti_placeholder_image.
* New JSON-LD structured data (Product, Offer, Organization, BreadcrumbList)
  built only from verified saved data, with correct availability/condition
  mapping and no ratings/reviews. Gated to avoid duplicating SEO-plugin schema;
  filterable via lrti_schema_graph and toggleable via lrti_output_schema.
* Related-trailer results are cached and invalidated when inventory changes.
* No VIN or internal notes are ever exposed.


= 1.5.0 =
* Added a complete inventory search and filtering system in the archive sidebar:
  keyword, manufacturer, type, condition, availability, price/year/GVWR ranges,
  axle count, pull type, featured-only, and sale-only.
* AJAX filtering with no page reload, a loading state, an accessible aria-live
  result count, stale-request cancellation, and duplicate-request prevention.
* Full no-JavaScript fallback via readable GET parameters; filter state survives
  refresh, filtered URLs are shareable, and browser Back/Forward work.
* Removable active-filter chips with Clear All, a Reset button, and a
  keyboard-accessible mobile "Show/Hide Filters" panel.
* Expanded sorting: Newest, Oldest, Price Low/High, Year Newest/Oldest,
  Manufacturer A–Z / Z–A, and Featured First — all working with filters,
  AJAX, and pagination.
* New shortcodes: [trailer_inventory], [featured_trailers], [trailer_search],
  and [trailer_filters], reusing the same query, cards, and styling. Multiple
  inventory instances on one page do not conflict.
* Filter-option lists are cached and invalidated when inventory or terms change.
* No VIN or internal notes are ever searched or exposed publicly.


= 1.4.1 =
* Fixed a fatal error ("critical error") when opening any single trailer page.
  Two helpers in the global-namespace helpers.php referenced the plugin's
  PostTypes class without its namespace, causing a "Class not found" fatal on
  the breadcrumbs and related-trailers sections. References are now fully
  qualified (\LRTI\PostTypes).
* Hardened the template loader: it now verifies a template is a readable file
  before use, falls back to the theme template if a required file is missing,
  and logs a development message instead of failing fatally.
* No changes to data, URLs, admin, or the archive.


= 1.4.0 =
* Added a complete, responsive single trailer detail page at each trailer's own
  URL (breadcrumbs, gallery, pricing with savings, quick + full specifications,
  features, financing message, dealer contact, and related trailers).
* Native, keyboard-accessible image gallery with thumbnail navigation and a
  full-size lightbox (no third-party library).
* Sold trailers show a Sold state and a "View Similar Trailers" action instead
  of availability, and are excluded from related results.
* Single-page Meta Title/Description are applied only when no supported SEO
  plugin (Yoast, Rank Math, AIOSEO, SEOPress) is active. No JSON-LD added.
* Admin polish: featured image Replace/Remove labels, live savings with dollar
  and percent, the Excerpt box renamed to "Short Description", and Overview
  dashboard cards (Total, Published, Drafts, In Stock, Sale Pending, Sold,
  Featured).
* Numerous developer actions and filters; theme template overrides supported.


= 1.3.0 =
* Added the public inventory archive at /inventory/ with a responsive card grid
  (3 columns desktop, 2 tablet, 1 mobile).
* Trailer cards show image, badges (featured/sale/availability), title, price,
  quick specs (floor length, GVWR, axles), stock number, excerpt, and a
  View Details button.
* Sorting (Newest, Oldest, Price low/high, Year, Manufacturer) and standard
  WordPress pagination (12 per page).
* Professional archive header, left sidebar filter placeholder, and a polished
  empty-inventory state that shows the dealership phone number.
* Theme-overridable templates, a template loader, an optimized query, a
  dedicated front-end stylesheet, and developer hooks throughout.


= 1.2.0 =
* General tab reordered to a dealership sales workflow (Year, Manufacturer,
  Model, Type, Condition, Availability, Stock #, VIN).
* Suggested trailer titles auto-generate from Year/Manufacturer/Model/Type and
  never overwrite a manually edited title.
* More specification fields are now standardized dropdowns (Suspension, Brakes,
  Pull Type, Flooring, Ramp, Jack, Coupler, Colors). Legacy values are kept.
* Preloaded ~100 common trailer features into the Features taxonomy (no
  duplicates; delivered via the version-upgrade routine).
* Added validation: unique/required Stock Number, four-digit Year, numeric
  prices, Sale <= Regular, MSRP >= Sale, and a friendly VIN check.
* Specifications grouped into collapsible sections (Dimensions, Axles,
  Suspension, Brakes, Hitch, Frame, Ramp & Flooring, Appearance, Hardware,
  Additional Features).
* SEO tab now has real fields (Meta Title, Meta Description, Focus Keyword,
  Canonical URL, Open Graph Image, Schema Override).
* The trailer editor now uses the classic editor for reliable title and
  validation behavior. Data, post type, taxonomies, URLs, and images unchanged.


= 1.1.0 =
* Redesigned the trailer editor into a native tabbed interface (General,
  Pricing, Specifications, Photos, SEO, Internal Notes).
* Manufacturer, Trailer Type, Condition, and Availability are now single-select
  dropdowns.
* Suspension, Brakes, Hitch, Ramp, and Flooring are now dropdowns.
* Featured image moved into the Photos tab.
* Prices accept pasted formats and are stored as clean numbers; Savings is
  calculated automatically and is read-only.
* Added validation: required and unique Stock Number, Sale price cannot exceed
  Regular price, and a warning when Empty Weight exceeds GVWR.

= 1.0.0 =
* Initial foundation release: plugin bootstrap, class autoloader, main plugin
  class, activation/deactivation handlers, admin menu, settings page, and
  data-preserving uninstall routine.
