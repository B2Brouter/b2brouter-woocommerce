# Changelog

All notable changes to B2Brouter for WooCommerce will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.9.0]: https://github.com/B2Brouter/b2brouter-woocommerce/releases/tag/v0.9.0
