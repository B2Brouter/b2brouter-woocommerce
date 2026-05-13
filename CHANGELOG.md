# Changelog

All notable changes to B2Brouter for WooCommerce will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-05-13

First stable release. WordPress.org plugin directory submission-ready: Plugin Check reports zero errors and zero warnings on the shipped ZIP. HPOS compatibility audited end-to-end; no direct postmeta access remains in the plugin.

### Added

- **External Services Disclosure**: New `== External Services ==` section in `readme.txt` documenting the B2Brouter API endpoint (`https://api.b2brouter.net`, overridable via `B2BROUTER_API_BASE`), the data sent (order, billing, refund) and received (status, PDFs), when transmission happens, and links to Terms and Privacy Policy. Required by WordPress.org guideline #6 for SaaS-integration plugins (closes #58)
- **Release Build Validation**: `build-release.sh` now uses an explicit allow-list and fails the build if any required file or directory is missing from either the staging directory or the produced ZIP. The release workflow delegates to the same script so local and CI builds run identical pipelines. Eliminates a regression that produced ZIPs without `readme.txt` (readme-parser errors at install) or `uninstall.php` (cleanup hook never fired) (closes #70)

### Changed

- **Plugin Slug**: Renamed from `b2brouter-woocommerce` to `b2brouter-for-woocommerce` to comply with WordPress.org trademark rules for the `woocommerce` term (allowed only with the `-for-woocommerce` / `-with-woocommerce` / `-using-woocommerce` / `-and-woocommerce` suffix patterns). The plugin entry file, text domain, translation file names, and the distributed ZIP's inner folder all use the new slug. The user-facing plugin name ("B2Brouter for WooCommerce") is unchanged. PHP constants (`B2BROUTER_WC_*`), option keys (`b2brouter_*`), and extension hooks (`b2brouter_invoice_*`) are unchanged to preserve compatibility with existing installs and third-party integrations (closes #55)
- **Logger Source**: The `wc_get_logger()` source identifier shown under **WooCommerce → Status → Logs** changed from `b2brouter-woocommerce` to `b2brouter-for-woocommerce` for consistency with the new slug. Existing log entries written under the previous source remain visible under the old source name in the Logs UI
- **Environment Selector**: Removed the staging/production environment radio from the settings page. The plugin now always defaults to production; developers can point at staging or a local B2Brouter instance via the `B2BROUTER_API_BASE` (and parallel `B2BROUTER_WEB_BASE`) constants in `wp-config.php` — replaces the previous `B2BROUTER_DEV_API_BASE` constant (closes #10)
- **Order Meta Box**: The "View in B2Brouter" link now respects `Settings::get_web_app_base_url()` instead of hardcoding the production app URL
- **Bulk Invoice Generation**: The "Generate B2Brouter Invoices" bulk action now enqueues background jobs via Action Scheduler instead of generating invoices synchronously. Eliminates 504 timeouts on large selections (worst case was ~12 minutes for 50 orders with auto-save PDF). Orders that already have an invoice are pre-filtered and counted as skipped. Progress and per-action logs are visible under **WooCommerce → Status → Scheduled Actions** (group `b2brouter`) (closes #37)
- **B2Brouter PHP SDK**: Upgraded from `^1.0.0` to `^1.2.0`. The SDK now defaults to API version `2026-03-02`. Verified the only plugin-touching change in the new API version is scheme code formatting: `tin_scheme` is now sent as the zero-padded string `'9999'` instead of integer `9999` (`Invoice_Generator.php`). Removed discount/charge fields, the `taxcode` query parameter, and the `type_document` rename — none are used by the plugin (closes #35)
- **API Key Validation**: `Settings::validate_api_key()` now uses the SDK's new `AccountService` (`$client->accounts->all()`) instead of a hand-rolled HTTP call against `/accounts`, removing duplicated header construction and response parsing
- **Uninstaller Filesystem Operations**: `Uninstaller::delete_pdf_directory()` now delegates to `$wp_filesystem->delete($path, true, 'd')` instead of a manual `scandir` / `unlink` / `rmdir` recursion. Required for hosts that restrict direct PHP filesystem calls; failures are logged via `Logger::warning` and uninstall remains best-effort if WP_Filesystem initialisation fails (closes #67)
- **WooCommerce Active Check**: Simplified `is_woocommerce_active()` to `class_exists('WooCommerce')` alone. The previous `apply_filters('active_plugins', …)` fallback was unreachable at the actual call site (`plugins_loaded` priority 20, after WC has loaded) and would return a false positive when WC was listed but its class failed to load — causing the plugin to proceed and crash on subsequent `wc_*` calls
- **Translator Comments and Placeholders**: All translatable strings with placeholders now carry `// translators:` comments explaining each substitution. Strings with multiple placeholders use positional `%1$s` / `%2$s` form so translators can reorder them per language. Removed the explicit `load_plugin_textdomain()` call — WordPress 4.6+ auto-loads translations for plugins hosted on WordPress.org (closes #66)
- **Invoice Due Date**: `date()` replaced with `wp_date()` for the invoice due-date field so the value is no longer affected by other plugins changing PHP's default timezone at runtime (closes #69)

### Security

- **Output Escaping**: All output flagged by Plugin Check is now passed through an appropriate escape function — `esc_html__()` for translated text in `wp_die()`, `(int)` casts for numeric IDs, `wp_kses_post()` for `wc_price()` output and translated strings that contain markup, `esc_html()` for plain translated text. The single legitimate raw binary output (the PDF body in `stream_invoice_pdf`) is annotated with a `phpcs:ignore` comment explaining that the `Content-Type` header is set above the echo (closes #56)
- **Exception Message Escaping**: All `throw new \Exception(__(…))` calls now wrap the message with `esc_html__()` (or `esc_html()` for interpolated values), so a consumer that echoes `$e->getMessage()` into an admin notice or order note remains safe (closes #68)
- **Request Handling Hardening**: All reads of `$_POST`, `$_GET`, and `$_REQUEST` now route through `wp_unslash()` before sanitization, addressing the `ValidatedSanitizedInput.MissingUnslash` rule. Nonce verification on the invoice-download bulk action moved from a manual `wp_verify_nonce + wp_die` pair to the canonical `check_admin_referer()`. Sites where nonce verification happens upstream (WooCommerce checkout, admin order save, AJAX entry points) carry explicit `phpcs:ignore` annotations naming the upstream nonce (closes #65)
- **Settings API Sanitisation**: Both `register_setting()` calls in `Admin::register_settings()` now declare a `sanitize_callback` — `sanitize_text_field` for `b2brouter_api_key`, and a strict whitelist of `'automatic'` / `'manual'` (with `'manual'` as fallback) for `b2brouter_invoice_mode` (closes #57)

### Fixed

- **Orphan PDF Metadata Cleanup on HPOS**: `Invoice_Generator::cleanup_order_metadata_for_file()` previously ran a direct SQL `SELECT … FROM wp_postmeta`, which returned no results on HPOS-only stores (where order meta lives in `wp_wc_orders_meta`) — orphan PDF references stuck around in the order metadata. The lookup now uses `wc_get_orders()`, which works in both legacy and HPOS modes

### Technical

- **Translation Regeneration**: `.pot`, `.po`, and `.mo` files regenerated after placeholder reordering and translator-comment additions. Existing translations preserved where the `msgid` is unchanged; fuzzy entries flagged for translator review where placeholders were reordered

## [0.9.4] - 2026-04-23

Final pre-release before 1.0. Focused on stability, operational polish, and preparing the plugin for distribution via the WordPress.org plugin directory and the WooCommerce Marketplace.

### Compliance scope

Documented explicit support for three national e-invoicing regimes:

- **Spain — Verifactu**: automatic AEAT reporting with QR verification.
- **France — DGFiP**: routing via PPF / Chorus Pro.
- **Poland — KSeF**: automatic submission to the national system.

General electronic invoicing (UBL / Facturae / Peppol) continues to work for the rest of the EU, the UK, and other countries supported by B2Brouter. Authority-specific credentials and identifiers (Verifactu certificates, KSeF tokens, Chorus Pro IDs, etc.) are managed in the B2Brouter dashboard, not in the WordPress plugin UI.

### Added

- **Internationalization**: Initial translation files for Catalan (ca), German (de_DE), Spanish (es_ES), French (fr_FR), English (en_US), plus `.pot` template
- **Bulk "Generate Invoice" Action**: Added to the HPOS orders screen; non-completed orders are skipped with a scoped admin notice
- **Organizational Unit Selector**: When the connected B2Brouter account has multiple organizational units, admins can pick which one to use
- **`uninstall.php`**: Cleans up `b2brouter_*` options, sync timestamps, and cached PDFs when the plugin is deleted

### Changed

- **Invoice Numbering**: Removed sequential and custom numbering modes. Remaining modes are WooCommerce order number and automatic B2Brouter numbering. Resolves a race condition in the sequential counter (closes #7)
- **Status Sync**: Finalized invoices (sent, paid, closed) are no longer re-polled. Stale non-final invoices use exponential backoff instead of hourly re-polling (closes #8)
- **Service Loading**: Admin services are now instantiated only in admin context and Customer services only on the frontend, reducing overhead on every page load (closes #14)
- **Logging**: Replaced all `error_log()` calls with `wc_get_logger()`. Plugin messages now appear under **WooCommerce → Status → Logs** with source `b2brouter-woocommerce` (closes #12)
- **Filesystem Operations**: PDF reads, writes, deletions, and directory operations now use the WP Filesystem API instead of direct `file_*` calls — prerequisite for wp.org submission (closes #15)
- **Welcome Page**: Redesigned landing page with updated copy, branding, and regenerated translations
- **Admin Menu**: Removed duplicate "Invoices" submenu; Welcome is now the default landing page (closes #4)

### Fixed

- **Customer Invoice Download**: Improved reliability of invoice downloads from the My Account → Orders page (closes #6)
- **Invoice Date Timezone**: `_b2brouter_invoice_date` is now parsed in the site timezone rather than the PHP default
- **Duplicate Save Notice**: Eliminated the duplicate save-success notice on the settings page

### Technical

- **Customer Class Test Coverage**: Previously 0%, now covered — exercises PDF download, manual invoice generation, access control, and AJAX handlers (closes #9)
- **Translations**: Fuzzy entries cleared and `.po`/`.mo` files regenerated after UI changes

---

## [0.9.3] - 2025-12-11

### Added

#### Real-Time Webhook Integration
- **Webhook Handler**: New REST API endpoint for receiving real-time invoice status updates from B2Brouter
  - Endpoint: `/wp-json/b2brouter/v1/webhook`
  - HMAC-SHA256 signature verification for security
  - 5-minute timestamp validation window to prevent replay attacks
  - Processes `issued_invoice.state_change` events
  - Real-time status updates (< 1 second vs hourly polling)
  - 95.97% test coverage with 19 comprehensive tests
- **Webhook Configuration Settings**: New admin settings section
  - Enable/disable webhooks toggle
  - Auto-generated webhook URL display
  - Webhook secret input field with validation
  - Optional fallback polling (6-hourly, enabled by default)
  - Settings validation prevents misconfiguration
- **Smart Polling Strategy**: Dynamic cron scheduling based on webhook configuration
  - Webhooks disabled: Hourly polling (existing behavior, unchanged)
  - Webhooks + fallback: 6-hourly polling (skips orders with recent webhook receipt)
  - Webhooks only: No polling (optional for advanced users)
  - Maintains existing 10-second immediate check after invoice generation
  - Custom "six_hourly" cron schedule (21600 seconds)
- **Webhook Receipt Tracking**: Stores webhook receipt timestamps to optimize fallback polling
  - New order meta: `_b2brouter_webhook_received_at`
  - Fallback polling skips orders with webhook receipt < 6 hours ago
  - Order notes indicate "Status updated via webhook" with timestamp

#### UI Improvements
- **Admin Menu Rename**: Changed admin menu from "B2Brouter" to "Invoices"
  - Better UX - users can easily find invoices in WordPress admin
  - More intuitive navigation for invoice-related functions
- **Custom Admin Menu Icon**: New custom SVG icon for admin menu
  - Professional branding with B2B icon logo
  - Consistent with plugin identity

### Changed

#### Invoice Status Synchronization
- **Enhanced Status Sync**: Updated `Status_Sync` class with webhook awareness
  - Respects webhook configuration when scheduling polling
  - Automatically reschedules cron on settings change
  - Improved test coverage: 56.86% (up from 5.88%)
  - 29 comprehensive tests with 94 assertions

### Fixed

#### HPOS Compatibility
- **TIN Field Saving**: Fixed TIN/VAT number field saving for HPOS-enabled stores
  - Affects classic checkout and admin order editing
  - Block checkout (WooCommerce 8.2+) was unaffected and already worked correctly
  - Ensures `_billing_tin` saves correctly on both HPOS and legacy storage modes
  - Maintains full backward compatibility with WooCommerce 5.0+
  - Added comprehensive tests: 13 new test cases, 82.76% coverage

### Technical

- **Test Suite Improvements**:
  - Total coverage: 62.53% (1572/2514 lines) across 293 tests
  - Webhook_Handler: 95.97% coverage
  - Customer_Fields: 82.76% coverage (up from 74.07%)
  - Status_Sync: 56.86% coverage (up from 5.88%)
- **Documentation Updates**:
  - README.md: Added comprehensive webhook setup guide, updated all menu references from "B2Brouter" to "Invoices"
  - LOCAL_DEVELOPMENT_SETUP.md: Extended with webhook testing instructions, updated menu references
  - ARCHITECTURE.md: Added webhook architecture and data flows
  - DEVELOPER_GUIDE.md: Updated testing checklist and project structure
  - release.yml: Updated installation instructions with new menu name
- **Code Quality**:
  - New `Webhook_Handler` class with full security validation
  - Enhanced `Settings` class with webhook configuration methods
  - Updated `Admin` class with webhook settings UI
  - Improved `Status_Sync` with dynamic scheduling logic

---

## [0.9.2] - 2025-12-05

### Changed
- **SDK Update**: Upgraded to B2Brouter PHP SDK v1.0.0
  - Updated from v0.9.1 to stable v1.0.0 release
  - All 247 tests pass with new SDK version
  - No breaking changes detected

### Fixed
- **Credit Note Number Collision**: Fixed "Number has already been taken" error for credit notes
  - Credit notes (refunds) now use refund ID instead of parent order number for uniqueness
  - Applies to 'woocommerce' numbering pattern (default)
  - Sequential and automatic numbering patterns already handled uniqueness correctly
- **WordPress 6.7.0 Compatibility**: Fixed translation loading timing
  - Resolved incorrect timing of translation loading to comply with WordPress 6.7.0 requirements
  - WordPress 6.7.0 introduced stricter checks requiring translations to be loaded at the 'init' action or later
- **Refund Invoice Generation**: Fixed "Call to undefined method OrderRefund::get_order_number()" error
  - Fixed error that occurred when generating credit notes for refunds in WooCommerce HPOS mode
- **Invoice Status Sync UX**: Enhanced status synchronization display
  - Display actual invoice status immediately upon creation instead of generic "draft" placeholder
  - Store initial invoice status from API response on creation
  - Schedule single status check 10 seconds after invoice generation
  - Replace 'draft' default with 'pending' for unfetched statuses
- **Invoice List Display**: Fixed refund display issues
  - Handle OrderRefund objects that lack get_order_number() and billing methods
  - Retrieve customer information from parent order for refunds
  - Link refund rows to parent order edit page instead of empty href
  - Use environment-aware B2Brouter web app URLs (staging vs production)

---

## [0.9.1] - 2025-12-03

### Added

#### Invoice Management
- **List of Invoices Page**: New admin page showing all generated invoices with pagination, sorting, and bulk download functionality
- **Invoice Status Sync System**: Automatic synchronization of invoice status from B2Brouter API with hourly cron job
  - Status badges in orders list showing current invoice state (draft, sent, accepted, paid, error, etc.)
  - Final states detection to avoid unnecessary API calls
  - Batch processing (50 invoices per run) for performance
  - Status display in order meta box with last checked timestamp
- **Menu Reordering**: Improved admin menu structure (Welcome → Settings → List of Invoices)

#### Customer Features
- **Customer Invoice Generation**: Customers can generate invoices from My Account page in manual mode
  - "Generate Invoice" button for completed/processing orders without invoices
  - Full security validation (nonce, ownership, order status checks)
  - AJAX-based generation with loading states
- **Credit Note Downloads**: Customers can download credit notes directly from My Account
  - "Download Credit Note" buttons for orders with refunds
  - Support for multiple credit notes per order
  - Automatic refund ID handling

#### Settings Improvements
- **Series Code Clarity**: Enhanced help text explaining one series code per invoice type
- **Custom Pattern Guidance**: Improved documentation that series code is separate from custom pattern
- **Status Display in Meta Box**: Invoice status shown in order admin with color-coded badges
  - "Manage from B2Brouter" link for invoices with errors
  - Last status update timestamp display

### Changed

#### Credit Note Generation
- **Consolidated On-Demand Generation**: Simplified credit note generation to single pattern
  - Removed automatic generation hook on refund creation
  - Credit notes now generate on-demand when accessed (email, download, view)
  - Only generates if parent invoice exists (accounting compliance)
  - Better maintainability with single code path

### Fixed
- **Refund Access Control**: Fixed customer access validation for refund invoices (credit notes)
  - Properly checks parent order customer for refund access
  - Applied in both Customer.php and Invoice_Generator.php

### Technical
- **API Retry Strategy**: Implemented exponential backoff for PDF downloads
  - Replaced fixed 2-second sleep with adaptive retry logic (up to 5 attempts: 1s, 2s, 4s, 8s)
  - New `API_Retry` helper class for robust error handling
  - Retries on `ResourceNotFoundException` (PDF not ready yet)
  - Non-retryable errors (auth, permission) fail immediately
  - PDF download failures don't fail invoice creation
  - 13 comprehensive unit tests with 91% code coverage
- **Cron Job Optimization**: Randomized invoice status sync timing
  - Each installation runs at a different minute (0-59) of the hour
  - Distributes B2Brouter API load across all installations
  - Prevents simultaneous API hits from multiple sites
- **JavaScript Improvements**:
  - Support for WooCommerce action key classes (underscores vs hyphens)
  - Order ID extraction from table structure and aria-labels
  - Refund ID extraction from URL fragments
  - Proper event delegation for dynamic content
- **Code Quality**: Maintained 247 passing PHPUnit tests across all changes (31x faster test suite: 24s → 0.8s)
- **Documentation**: Updated inline code comments for clarity on credit note behavior and retry logic

---

## [0.9.0] - 2025-11-25 (Beta Release)

### Added

#### Invoice Generation
- Automatic invoice generation on order completion (configurable)
- Manual invoice generation from order admin panel
- Bulk invoice generation using WooCommerce bulk actions
- Support for multiple invoice types:
  - Standard Invoice (IssuedInvoice) for B2B transactions
  - Simplified Invoice (IssuedSimplifiedInvoice) for B2C transactions
  - Credit Notes with automatic generation for WooCommerce refunds
- PDF invoice generation and automatic download from B2Brouter
- Email integration with PDF attachments for order completion emails
- Customer invoice downloads from order pages

#### Tax Compliance
- Automatic tax category detection for order line items
- Peppol tax category support (S, E, Z, NS, AE)
- Intra-EU reverse charge detection for B2B transactions
- Dynamic tax name localization (IVA, TVA, VAT, MwSt, GST)
- Automatic merchant country detection from WooCommerce settings
- EU country detection for 27 member states

#### Invoice Numbering
- Configurable series codes for invoices and credit notes
- Multiple numbering patterns:
  - Automatic sequential numbering via B2Brouter
  - WooCommerce order number integration
  - Independent sequential counters per series
  - Custom patterns with placeholders (`{order_id}`, `{order_number}`, `{year}`, `{month}`, `{day}`)

#### TIN/VAT Collection
- Automatic TIN/VAT field added to checkout
- WooCommerce 8.6+ block-based checkout support
- Classic shortcode-based checkout support
- Customer profile storage for TIN reuse
- TIN stored as `_billing_tin` order metadata
- Admin visibility in order billing information

#### PDF Management
- Local PDF caching in WordPress upload directory
- Automatic cleanup of old PDFs using WordPress cron
- Configurable retention period (default: 90 days)
- On-demand download with automatic caching
- Force regeneration option for PDFs

#### Admin Interface
- Dedicated settings page under B2Brouter menu
- API key validation with account information retrieval
- Order meta box showing invoice status and generation controls
- Invoice status column in WooCommerce orders list
- Bulk actions in orders list
- Admin bar transaction counter
- Automatic order notes for invoice operations
- Detailed error logging and user-friendly error messages

#### WooCommerce Integration
- High-Performance Order Storage (HPOS/Custom Order Tables) compatibility
- Order status hooks for automatic invoice generation
- Invoice metadata storage (ID, number, date)
- Refund integration with automatic credit note generation
- Multi-currency support
- Tax calculation integration

#### API Integration
- Built on B2Brouter PHP SDK v0.9.1+
- Staging and production environment support
- Real-time API key validation
- Structured data exchange (UBL, Facturae, and other formats)
- Electronic invoice format support via B2Brouter

#### Developer Features
- PHPUnit test suite with 100% coverage
- Composer scripts for testing and building
- Distribution build script with vendor dependencies
- PSR-4 autoloading
- WordPress and WooCommerce stubs for development

### Technical Requirements
- WordPress 5.8 or higher
- WooCommerce 5.0 or higher (tested up to 8.5)
- PHP 7.4 or higher
- B2Brouter account and API key

### Known Limitations (Beta)
- This is a beta release for testing purposes
- Please report any issues to https://github.com/B2Brouter/b2brouter-woocommerce/issues
- Recommended for testing environments before production deployment

---

## Release Notes

### Version 0.9.0 (Beta)
This is the initial beta release of B2Brouter for WooCommerce. It includes core functionality for automatic electronic invoice generation, tax compliance, and PDF management. The plugin integrates seamlessly with WooCommerce and supports both B2B and B2C scenarios.

**What's Working:**
- Complete invoice generation workflow (automatic and manual)
- Tax compliance including intra-EU reverse charge
- PDF generation and caching
- TIN/VAT field collection at checkout
- WooCommerce HPOS compatibility
- Refund handling with credit notes

**Testing Focus:**
- Invoice generation accuracy across different tax scenarios
- PDF generation and caching reliability
- Checkout field integration (both classic and block-based)
- Multi-currency and international transactions
- Error handling and recovery

**Feedback Welcome:**
We welcome feedback on all aspects of the plugin. Please test in a staging environment and report any issues or suggestions.

---

[1.0.0]: https://github.com/B2Brouter/b2brouter-woocommerce/releases/tag/v1.0.0
[0.9.4]: https://github.com/B2Brouter/b2brouter-woocommerce/releases/tag/v0.9.4
[0.9.3]: https://github.com/B2Brouter/b2brouter-woocommerce/releases/tag/v0.9.3
[0.9.2]: https://github.com/B2Brouter/b2brouter-woocommerce/releases/tag/v0.9.2
[0.9.1]: https://github.com/B2Brouter/b2brouter-woocommerce/releases/tag/v0.9.1
[0.9.0]: https://github.com/B2Brouter/b2brouter-woocommerce/releases/tag/v0.9.0
