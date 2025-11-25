# B2Brouter for WooCommerce

**Automated invoice generation and tax compliance for WooCommerce using B2Brouter's eDocExchange service.**

B2Brouter for WooCommerce integrates your WooCommerce store with B2Brouter's electronic invoicing platform, providing structured data exchange, multi-country tax compliance, and API-driven invoice delivery for B2B and B2C eCommerce.

[![Version](https://img.shields.io/badge/version-0.9.0-blue.svg)](https://github.com/B2Brouter/b2brouter-woocommerce/releases)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0%2B-purple.svg)](https://woocommerce.com)

---

## Features

### Invoice Generation

- **Automatic or Manual Generation**: Configure the plugin to generate invoices automatically when orders are completed, or generate them manually from the order admin panel
- **Bulk Invoice Generation**: Process multiple orders simultaneously using WooCommerce bulk actions
- **Multiple Invoice Types**:
  - **Standard Invoice (IssuedInvoice)**: For B2B transactions with customer TIN provided
  - **Simplified Invoice (IssuedSimplifiedInvoice)**: For B2C transactions without TIN
  - **Credit Notes**: Automatically generated for WooCommerce refunds, with support for country-specific formats (e.g., Spanish Rectificative Invoices)
- **PDF Export**: Automatic generation and download of PDF invoices from B2Brouter
- **Email Integration**: Attach PDF invoices to WooCommerce order completion and customer invoice emails
- **Customer Downloads**: Customers can view and download invoices from their order pages

### Tax Compliance

- **Automatic Tax Category Detection**: Analyzes WooCommerce order data to determine appropriate tax categories for each line item
- **PEPPOL Tax Categories**: Supports standard PEPPOL categories:
  - **S** (Standard rate): Applied when taxes exist
  - **E** (Exempt from tax): Taxable items with 0% rate
  - **Z** (Zero-rated goods): Items with explicit zero-rate tax class
  - **NS** (Not subject to tax): Non-taxable items (B2Brouter maps to PEPPOL G or O based on context)
  - **AE** (VAT Reverse Charge): Automatically detected for intra-EU B2B transactions
- **Intra-EU Reverse Charge**: Automatic detection based on customer TIN and country comparison
- **Dynamic Tax Names**: Tax names automatically localized by supplier country (IVA, TVA, VAT, MwSt, GST, etc.)
- **Merchant Country Detection**: Automatically extracts merchant country from WooCommerce settings
- **EU Country Detection**: Built-in detection of 27 EU member states for compliance logic

### Custom Invoice Numbering

- **Series Codes**: Configure different prefixes for invoices and credit notes (e.g., INV, CN)
- **Multiple Numbering Patterns**:
  - **Automatic**: B2Brouter generates sequential numbers
  - **WooCommerce Order Number**: Use WooCommerce's native order numbering
  - **Sequential**: Independent sequential counter per series code
  - **Custom Pattern**: Define patterns using placeholders:
    - `{order_id}`: WooCommerce order ID
    - `{order_number}`: WooCommerce order number
    - `{year}`: Current year (YYYY)
    - `{month}`: Current month (MM)
    - `{day}`: Current day (DD)

### TIN/VAT Number Collection

- **Checkout Integration**: Automatic TIN/VAT field added to WooCommerce checkout
- **Block Checkout Support**: Compatible with WooCommerce 8.6+ block-based checkout
- **Classic Checkout Support**: Works with traditional shortcode-based checkout
- **Customer Profile Storage**: TIN saved to customer profile for reuse on subsequent orders
- **Order Meta Storage**: TIN stored as `_billing_tin` order metadata
- **Admin Visibility**: TIN displayed in order billing information in admin

### PDF Management

- **Local Caching**: Store generated PDFs in WordPress upload directory for fast access
- **Automatic Cleanup**: Scheduled cleanup of old PDF files using WordPress cron
- **Configurable Retention**: Set retention period (default: 90 days)
- **On-Demand Download**: Manual download trigger with automatic caching
- **Force Regeneration**: Option to force PDF regeneration and update cache

### Admin Interface

- **Settings Panel**: Dedicated settings page under B2Brouter menu with API key validation
- **Order Meta Box**: Invoice status and generation controls in WooCommerce order edit page
- **Invoice Column**: Visual invoice status indicator in WooCommerce orders list
- **Bulk Actions**: Invoice generation available in WooCommerce orders bulk actions menu
- **Admin Bar Counter**: Transaction count displayed in WordPress admin bar
- **Order Notes**: Automatic order notes added on invoice generation success/failure
- **Error Handling**: Clear error messages with detailed logging for troubleshooting

### WooCommerce Integration

- **HPOS Compatibility**: Native support for WooCommerce High-Performance Order Storage (Custom Order Tables)
- **Order Status Hooks**: Automatic invoice generation triggered on order completion
- **Meta Data Storage**: Invoice ID, number, and date stored as order metadata
- **Refund Integration**: WooCommerce refunds automatically generate credit notes
- **Currency Support**: Respects WooCommerce order currency for multi-currency stores
- **Tax Calculation Integration**: Reads WooCommerce tax data to calculate and categorize taxes

### API Integration

- **B2Brouter PHP SDK**: Built on official B2Brouter PHP SDK (v0.9.1+)
- **Environment Support**:
  - Staging: `https://api-staging.b2brouter.net`
  - Production: `https://api.b2brouter.net`
- **API Key Validation**: Real-time validation with account information retrieval
- **Structured Data Exchange**: Sends structured invoice data (not just PDFs)
- **Electronic Invoice Formats**: Supports UBL, Facturae, and other formats via B2Brouter
- **Multi-Country Compliance**: B2Brouter adapts invoice requirements for PEPPOL and non-PEPPOL countries

---

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Composer (for development only)
- Active B2Brouter eDocExchange subscription

---

## Installation

### End Users

1. Download the release ZIP file from the [releases page](https://github.com/B2Brouter/b2brouter-woocommerce/releases)
2. In WordPress Admin, navigate to **Plugins → Add New → Upload Plugin**
3. Select the downloaded ZIP file
4. Click **Install Now**, then **Activate Plugin**
5. Configure API key in **B2Brouter → Settings**

The release ZIP includes all dependencies. Composer is not required.

### Developers

```bash
# Clone repository
git clone https://github.com/B2Brouter/b2brouter-woocommerce.git
cd b2brouter-woocommerce

# Install dependencies
composer install

# Link to WordPress plugins directory
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/b2brouter-woocommerce
```

Build distribution package:

```bash
./build-release.sh
# Output: build/b2brouter-woocommerce-X.X.X.zip
```

See [DISTRIBUTION.md](docs/DISTRIBUTION.md) for release procedures.

---

## Configuration

### API Setup

1. Navigate to **B2Brouter → Settings** in WordPress admin
2. Enter your B2Brouter API key
3. Click **Validate Key** to verify connectivity and retrieve account information
4. Select environment (Staging or Production)
5. Save settings

### Invoice Generation

**Invoice Mode**:
- **Automatic**: Invoices generated when orders transition to "Completed" status
- **Manual**: Invoices generated on-demand from order admin page

### PDF Settings

**Auto-Save PDFs**:
- Enable to automatically download and cache PDFs locally in WordPress
- Improves response time for customer downloads
- Reduces API calls to B2Brouter

**Email Attachments**:
- **Attach to Order Completed Email**: Include PDF in WooCommerce order completion emails
- **Attach to Customer Invoice Email**: Include PDF in WooCommerce customer invoice emails

**Automatic Cleanup**:
- Enable scheduled cleanup of cached PDF files
- Configure retention period (days)
- Cleanup runs daily via WordPress cron

### Invoice Numbering

**Series Codes**:
- **Invoice Series Code**: Prefix for regular invoices (e.g., "INV")
- **Credit Note Series Code**: Prefix for credit notes (e.g., "CN")

**Numbering Pattern**:
- **Automatic**: B2Brouter generates sequential numbers
- **WooCommerce Order Number**: Use WooCommerce order number as invoice number
- **Sequential**: Plugin maintains independent sequential counter per series
- **Custom Pattern**: Define custom format using placeholders

Example patterns:
- `INV-{year}-{order_id}` → `INV-2025-123`
- `{order_number}` → `1234`
- Sequential → `00001`, `00002`, `00003`

---

## Usage

### Automatic Invoice Generation

With automatic mode enabled:

1. Customer completes purchase
2. Order status changes to "Completed"
3. Plugin generates invoice via B2Brouter API
4. Invoice metadata saved to order
5. Order note added with invoice details
6. PDF generated and cached (if auto-save enabled)
7. Email sent with PDF attachment (if configured)

### Manual Invoice Generation

Single order:

1. Navigate to **WooCommerce → Orders**
2. Open order detail page
3. Locate **B2Brouter Invoice** meta box
4. Click **Generate Invoice**
5. View invoice details and download PDF

Bulk generation:

1. Navigate to **WooCommerce → Orders**
2. Select multiple completed orders
3. Choose **Generate B2Brouter Invoices** from bulk actions dropdown
4. Click **Apply**
5. View results in admin notice

### Tax Configuration

The plugin reads tax configuration from WooCommerce:

1. Navigate to **WooCommerce → Settings → Tax**
2. Enable "Enable tax rates and calculations"
3. Configure standard rates:
   - Country code
   - Tax rate percentage
   - Tax name (e.g., IVA, VAT)
   - Shipping tax setting

**Zero-Rate Products**:
- Create tax class named "Zero Rate"
- Set tax rate to 0%
- Assign to products
- Plugin uses PEPPOL category Z

**Non-Taxable Products**:
- Set product **Tax Status** to "None"
- Plugin uses category NS (Not Subject)

### Customer TIN Collection

TIN field automatically appears in checkout. Customer can optionally provide:

- Tax ID / VAT Number in checkout form
- Value saved to `_billing_tin` order meta
- Value saved to customer profile for reuse
- Included automatically in invoice data

For intra-EU B2B transactions with TIN, reverse charge (AE category) is automatically applied.

---

## Architecture

For detailed technical architecture documentation including class structure, data flow, and API integration, see [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

---

## Testing

### Running Tests

```bash
# Install dev dependencies
composer install

# Run test suite
composer test
# or
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/InvoiceGeneratorTest.php

# Generate coverage report
composer test:coverage
# Opens tests/coverage/index.html
```

### Test Coverage

Current coverage: 61.38% (1038/1691 lines)

Coverage by class:
- Settings: 72.57%
- Customer_Fields: 74.07%
- Admin: 69.80%
- Order_Handler: 64.61%
- Invoice_Generator: 61.29%

### Test Environment

Tests use mocked WordPress and WooCommerce functions (see `tests/bootstrap.php`). No WordPress installation required for testing.

Mocked components:
- WordPress core functions (`get_option`, `update_option`, etc.)
- WooCommerce classes (`WC_Order`, `WC_Product`, etc.)
- B2Brouter API client (returns simulated responses)

---

## Development

For comprehensive development documentation including IDE setup, release process, and contribution guidelines, see [docs/DEVELOPER_GUIDE.md](docs/DEVELOPER_GUIDE.md).

---

## Support

- **Documentation**: [B2Brouter API Documentation](https://developer.b2brouter.net)
- **B2Brouter Platform**: [app.b2brouter.net](https://app.b2brouter.net)
- **GitHub Issues**: [github.com/B2Brouter/b2brouter-woocommerce/issues](https://github.com/B2Brouter/b2brouter-woocommerce/issues)

---

## Contributing

Contributions are welcome. Please:

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure tests pass: `composer test`
5. Follow WordPress Coding Standards
6. Submit pull request with clear description

---

## License

MIT License. See [LICENSE](LICENSE) file for details.

---

## Credits

- **Developed by**: B2Brouter
- **B2Brouter PHP SDK**: [github.com/B2Brouter/b2brouter-php](https://github.com/B2Brouter/b2brouter-php)
- **Built for**: [WooCommerce](https://woocommerce.com) and [WordPress](https://wordpress.org)
