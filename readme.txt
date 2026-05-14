=== B2Brouter for WooCommerce ===
Contributors: b2brouter
Tags: woocommerce, e-invoicing, peppol, verifactu, ksef
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.3
License: MIT
License URI: https://opensource.org/license/mit

Electronic invoicing for WooCommerce. Compliance for Spain (Verifactu), France (DGFiP) and Poland (KSeF). EU + UK invoicing.

== Description ==

**B2Brouter for WooCommerce** connects your WooCommerce store with the B2Brouter platform to generate and send electronic invoices automatically — including the country-specific tax authority reporting required in Spain, France and Poland.

= Built-in regulatory compliance =

The plugin includes explicit support for the following e-invoicing regimes:

* **Spain — Verifactu**: automatic AEAT reporting with QR verification on every issued invoice.
* **France — DGFiP**: routing through the official PPF / Chorus Pro infrastructure.
* **Poland — KSeF**: automatic submission of invoices to the national KSeF system.

Beyond these explicit regimes, the plugin generates compliant electronic invoices in standard formats (UBL, Facturae, Peppol) for the rest of the EU, the UK, and other jurisdictions supported by B2Brouter.

**Important:** Authority-specific configuration (Verifactu certificates, KSeF tokens, Chorus Pro identifiers, etc.) is managed in the **B2Brouter dashboard**, not in the WordPress plugin UI. The plugin only needs your B2Brouter API key.

= Key features =

* **Automatic or manual invoice generation** — issue invoices on order completion or on demand from the order screen.
* **Bulk invoice generation** — process multiple completed orders at once via WooCommerce bulk actions.
* **Standard and simplified invoices** — IssuedInvoice (B2B with TIN) or IssuedSimplifiedInvoice (B2C without TIN), selected automatically.
* **Credit notes for refunds** — generated on demand when a WooCommerce refund is created against an invoiced order.
* **PDF generation, caching and email attachment** — store PDFs locally and attach them to order/customer/refund emails.
* **Real-time invoice status** via webhooks (HMAC-SHA256 signed) with optional 6-hour fallback polling.
* **TIN / VAT field at checkout** — works on both classic and block-based checkout (WooCommerce 8.6+); intra-EU reverse charge auto-detected.
* **HPOS compatibility** — native support for High-Performance Order Storage (Custom Order Tables).
* **Peppol tax categories** — automatic mapping (S, E, Z, NS, AE) from WooCommerce tax configuration.

= Requirements =

* WordPress 5.8 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* Active B2Brouter eDocExchange subscription

== Installation ==

1. Download the plugin ZIP from the [GitHub releases page](https://github.com/B2Brouter/b2brouter-woocommerce/releases) (or install from the WordPress.org plugin directory once available).
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin** and select the ZIP file.
3. Click **Install Now**, then **Activate Plugin**.
4. Navigate to **Invoices → Settings**, paste your B2Brouter API key and click **Validate Key**.
5. Choose **Automatic** or **Manual** invoice generation mode and configure series codes for invoices and credit notes.
6. (Recommended) Configure webhooks under **Invoices → Settings → Webhook Configuration** for real-time status updates.

== Frequently Asked Questions ==

= Do I need a B2Brouter account? =

Yes. The plugin requires an active B2Brouter eDocExchange subscription. Sign up at [app.b2brouter.net](https://app.b2brouter.net) and obtain an API key under **Developers → API Keys**.

= Where do I configure Verifactu, KSeF or Chorus Pro? =

Authority-specific configuration (certificates, tokens, identifiers) is managed in your B2Brouter dashboard, not in the WordPress plugin UI. Once your dashboard is configured, the plugin transparently sends invoices through the appropriate regime.

= Does the plugin work for countries outside Spain, France and Poland? =

Yes. Beyond the explicitly supported compliance regimes, the plugin generates standard electronic invoices (UBL, Facturae, Peppol) for the rest of the EU, the UK and other jurisdictions supported by B2Brouter. Need explicit compliance for another country? [Open an issue](https://github.com/B2Brouter/b2brouter-woocommerce/issues/new).

= Is HPOS (High-Performance Order Storage) supported? =

Yes. The plugin declares full compatibility with WooCommerce HPOS / Custom Order Tables.

= How do I get real-time invoice status updates? =

Enable webhooks under **Invoices → Settings → Webhook Configuration**. Copy the auto-generated webhook URL into your B2Brouter dashboard (**Developers → Webhooks**), then paste the generated webhook secret back into WordPress. Updates arrive in under one second; a 6-hour fallback poll keeps things reliable.

= Is the TIN/VAT field shown at checkout? =

Yes, automatically. It works with both classic shortcode-based checkout and the new block-based checkout (WooCommerce 8.6+). The value is stored in `_billing_tin` on the order and on the customer profile for reuse.

= Are credit notes generated automatically? =

Credit notes are generated on demand for WooCommerce refunds when the parent order has an invoice. They follow the country-specific format (e.g. Spanish rectifying invoices) and are accessible from the order admin and from the customer's My Account page.

= Are tests included? =

Yes. The plugin ships with a PHPUnit test suite. See `docs/DEVELOPER_GUIDE.md` in the repository for instructions.

== Screenshots ==

1. Settings page with API key validation and account information.
2. Order edit screen showing the B2Brouter Invoice meta box with status and PDF download.
3. WooCommerce orders list with the invoice status column.
4. List of Invoices admin page with bulk PDF download.
5. Webhook configuration section.
6. Customer "My Account" view with Download Invoice / Generate Invoice buttons.

== External Services ==

This plugin connects your WooCommerce store to **B2Brouter**, a third-party e-invoicing SaaS operated by B2Brouter SL. Using the plugin requires an active B2Brouter account and API key.

**Service endpoint:** `https://api.b2brouter.net` (overridable via the `B2BROUTER_API_BASE` constant for staging or self-hosted instances).

**Data sent to B2Brouter when an invoice or credit note is created:**

* Order data — order number, date, currency, totals, and line items (product name, SKU, quantity, unit price, tax rate, Peppol tax category).
* Customer billing data — name, company name, billing address, country, email, and TIN/VAT number when provided at checkout.
* Refund data when a credit note is generated against a previously invoiced order.

**Data received from B2Brouter:** invoice status updates and PDF documents, either via webhook callbacks to `/wp-json/b2brouter/v1/webhook` or via SDK polling.

**When data is transmitted:** whenever an invoice or credit note is created (automatically on order completion, or on demand from the order screen, the WooCommerce bulk action, or the customer's My Account page) and whenever invoice status is polled or pushed back via webhook.

**Provider and legal documents:**

* Provider: [B2Brouter](https://www.b2brouter.net)
* [Terms and Conditions](https://www.b2brouter.net/global/terms-and-conditions/)
* [Privacy Policy](https://www.b2brouter.net/global/privacy-policy/)

== Changelog ==

= 1.0.3 =

**Fixed:**

* Release ZIP no longer ships development-only files from the bundled B2Brouter PHP SDK (`.env.example`, the package's own `tests/`, `docs/`, `examples/`, `.github/`, `phpunit.xml.dist`, `CHANGELOG.md`, `README.md`). Surfaced by the WordPress.org Plugin Review Team.

= 1.0.2 =

**Added:**

* Declare WooCommerce as a plugin dependency via the `Requires Plugins: woocommerce` header (WordPress 6.5+). Older WordPress versions ignore the header and fall back to the existing PHP-side dependency check.

= 1.0.1 =

**Fixed:**

* Plugin header `Plugin URI` and `Author URI` were both set to the same value; `Plugin URI` now points at the WooCommerce integration documentation (`https://www.b2brouter.net/docs/#/en/integration/woocommerce`) so the two URIs are distinct, as required by the WordPress.org submission validator.

= 1.0.0 =

First stable release. Cleared for distribution via the WordPress.org plugin directory and the WooCommerce Marketplace. Plugin Check reports zero errors and zero warnings on the shipped ZIP, and HPOS compatibility was audited end to end (no direct `wp_postmeta` access remains).

**Added:**

* `== External Services ==` disclosure section listing the B2Brouter API endpoint, data sent/received, transmission triggers, and links to Terms and Privacy Policy.
* Build-time validation in `build-release.sh`: the release ZIP is rejected if `readme.txt`, `uninstall.php`, or the bundled SDK is missing.

**Changed:**

* Plugin slug renamed from `b2brouter-woocommerce` to `b2brouter-for-woocommerce` for WordPress.org trademark compliance. The user-facing plugin name is unchanged; PHP constants, option keys, and extension hooks are preserved for compatibility.
* Bulk "Generate B2Brouter Invoices" now runs through Action Scheduler instead of a synchronous loop — no more 504 timeouts on large selections. Progress visible under **WooCommerce → Status → Scheduled Actions**.
* Staging/production environment selector removed from the settings page. The plugin defaults to production; staging can be reached via the `B2BROUTER_API_BASE` constant.
* B2Brouter PHP SDK upgraded to v1.2 (API version `2026-03-02`). API-key validation now uses the SDK's new `AccountService`.
* Uninstaller routes all filesystem operations through the WordPress `WP_Filesystem` API.
* Invoice due date now uses `wp_date()` for timezone-stable formatting.

**Security:**

* All flagged output paths now run through appropriate escape functions (`esc_html__`, `esc_html`, `wp_kses_post`, `(int)` casts).
* Exception messages are escaped at throw time so any consumer that echoes them remains safe.
* All superglobal reads (`$_POST` / `$_GET` / `$_REQUEST`) go through `wp_unslash()` before sanitization; nonce verification uses the canonical `check_admin_referer()` pattern.
* Settings API options declare `sanitize_callback` (strict whitelist for invoice mode).

**Fixed:**

* Orphan PDF metadata cleanup is now HPOS-aware. The previous implementation queried `wp_postmeta` directly and silently missed orders on HPOS-only stores.

= 0.9.4 =

Final pre-release before 1.0. Focused on stability, operational polish, and preparing the plugin for distribution via the WordPress.org plugin directory and the WooCommerce Marketplace.

**Compliance scope:**

* Documented explicit support for three national e-invoicing regimes: Spain Verifactu, France DGFiP (PPF / Chorus Pro) and Poland KSeF. General electronic invoicing (UBL / Facturae / Peppol) continues to work for the rest of the EU, the UK, and other countries supported by B2Brouter. Authority-specific credentials and identifiers are managed in the B2Brouter dashboard, not in the WordPress plugin UI.

**Added:**

* Initial translation files for Catalan, German, Spanish, French and English plus a `.pot` template.
* Bulk "Generate Invoice" action on the HPOS orders screen; non-completed orders are skipped with a scoped admin notice.
* Organizational unit selector when the connected B2Brouter account has multiple organizational units.
* `uninstall.php` cleans up `b2brouter_*` options, sync timestamps and cached PDFs when the plugin is deleted.

**Changed:**

* Invoice numbering: removed sequential and custom numbering modes. Remaining modes are WooCommerce order number and automatic B2Brouter numbering.
* Status sync: finalized invoices are no longer re-polled; stale non-final invoices use exponential backoff.
* Service loading: admin services instantiated only in admin context and customer services only on the frontend.
* Logging: replaced `error_log()` with `wc_get_logger()`. Plugin messages now appear under WooCommerce → Status → Logs (source `b2brouter-for-woocommerce`).
* Filesystem operations: PDF reads, writes, deletions and directory operations now use the WP Filesystem API.
* Welcome page redesigned; admin menu cleaned up (Welcome is the default landing page).

**Fixed:**

* Customer invoice download reliability from My Account → Orders.
* `_b2brouter_invoice_date` now parsed in the site timezone.
* Eliminated the duplicate save-success notice on the settings page.

= 0.9.3 =

* Real-time webhook integration (HMAC-SHA256 signed `issued_invoice.state_change` events; 5-minute timestamp window).
* Smart polling strategy with optional 6-hour fallback when webhooks are enabled.
* Admin menu renamed to "Invoices" with a new custom SVG icon.
* Fixed TIN field saving for HPOS-enabled stores (classic checkout and admin order editing).

= 0.9.2 =

* Upgraded to B2Brouter PHP SDK v1.0.0.
* Fixed credit note number collision for refunds.
* Fixed WordPress 6.7.0 translation loading timing.
* Fixed refund invoice generation under HPOS.
* Improved invoice status sync UX (real status on creation instead of generic "draft").

= 0.9.1 =

* New "List of Invoices" admin page with pagination, sorting and bulk PDF download.
* Invoice status sync system with hourly cron and color-coded badges.
* Customer invoice generation from My Account in manual mode.
* Customer credit note downloads from My Account.
* Exponential backoff retry for PDF downloads.

= 0.9.0 =

* First public beta. Automatic / manual / bulk invoice generation, credit notes for refunds, PDF caching and email attachment, TIN/VAT collection, HPOS compatibility.

For the complete history, see `CHANGELOG.md` in the repository.

== Upgrade Notice ==

= 1.0.3 =

Strips development-only files from the bundled SDK in the release ZIP (no behaviour change for installed plugins). Resolves the WordPress.org Plugin Review Team's flag on `.env.example` and similar package internals.

= 1.0.2 =

Declares WooCommerce as a plugin dependency via the `Requires Plugins` header (WordPress 6.5+). No change in behaviour for users on older WordPress versions.

= 1.0.1 =

Fixes the plugin header so the WordPress.org submission validator accepts it: `Plugin URI` and `Author URI` are now distinct.

= 1.0.0 =

First stable release. Plugin slug renamed to `b2brouter-for-woocommerce` for WordPress.org compliance — existing installs upgrading in place will see the plugin as a new entry under the new directory and must be reactivated. Bulk invoice generation moved to Action Scheduler. SDK upgraded to v1.2.

= 0.9.4 =

Final pre-release before 1.0. Filesystem API migration (required for wp.org), translations, and explicit compliance scope docs (Verifactu, DGFiP, KSeF). Sequential and custom numbering modes removed — switch to Automatic or WooCommerce order number before upgrading.
