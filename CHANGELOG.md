# Changelog

All notable changes to B2Brouter for WooCommerce will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
- PEPPOL tax category support (S, E, Z, NS, AE)
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

[0.9.3]: https://github.com/B2Brouter/b2brouter-woocommerce/releases/tag/v0.9.3
[0.9.2]: https://github.com/B2Brouter/b2brouter-woocommerce/releases/tag/v0.9.2
[0.9.1]: https://github.com/B2Brouter/b2brouter-woocommerce/releases/tag/v0.9.1
[0.9.0]: https://github.com/B2Brouter/b2brouter-woocommerce/releases/tag/v0.9.0
