# Architecture

Technical architecture documentation for the B2Brouter WooCommerce plugin.

## Table of Contents

- [File Structure](#file-structure)
- [Class Architecture](#class-architecture)
- [Data Flow](#data-flow)
- [API Integration](#api-integration)

---

## File Structure

```
b2brouter-woocommerce/
├── assets/
│   ├── css/admin.css              # Admin interface styles
│   ├── js/admin.js                # Admin JavaScript (AJAX handlers)
│   └── img/b2b-icon-logo.svg      # Custom admin menu icon
├── includes/
│   ├── Admin.php                  # Admin UI and AJAX endpoints
│   ├── API_Retry.php              # Retry logic helper with exponential backoff
│   ├── Customer_Fields.php        # TIN field management (checkout)
│   ├── Customer.php               # Customer-facing features (My Account)
│   ├── Invoice_Generator.php     # Core invoice generation logic
│   ├── Invoice_List_Table.php    # Invoice list admin page
│   ├── Order_Handler.php          # WooCommerce order integration
│   ├── Settings.php               # Settings management
│   ├── Status_Sync.php            # Invoice status synchronization
│   └── Webhook_Handler.php        # Webhook endpoint for real-time updates
├── tests/
│   ├── AdminTest.php
│   ├── APIRetryTest.php
│   ├── CustomerFieldsTest.php
│   ├── InvoiceGeneratorTest.php
│   ├── InvoiceListTableTest.php
│   ├── InvoiceTypesTest.php
│   ├── OrderHandlerTest.php
│   ├── SettingsTest.php
│   ├── StatusSyncTest.php
│   ├── WebhookHandlerTest.php
│   └── bootstrap.php
├── vendor/                        # Composer dependencies (gitignored)
├── .github/workflows/
│   └── release.yml               # Automated release workflow
├── b2brouter-woocommerce.php     # Main plugin file
├── composer.json
├── phpunit.xml
└── README.md
```

---

## Class Architecture

### B2Brouter_WooCommerce (Main Plugin Class)

- Singleton pattern
- Dependency injection container
- Plugin initialization and hook registration

### Settings

- Manages WordPress options storage
- API key validation
- Environment configuration
- Invoice numbering settings

### Invoice_Generator

- Core invoice generation logic
- Data transformation (WooCommerce → B2Brouter format)
- Tax category detection
- PDF download and caching
- B2Brouter API client wrapper

### Order_Handler

- WooCommerce order integration
- Automatic invoice generation trigger
- Bulk action implementation
- Meta box rendering
- Order column customization

### Admin

- Admin interface rendering
- Settings page
- AJAX endpoints
- API key validation UI
- Admin bar counter

### Customer_Fields

- TIN field registration (checkout)
- Customer profile integration
- Order meta storage
- Block checkout compatibility
- HPOS compatibility for TIN field saving

### Customer

- Customer-facing features on My Account page
- Invoice downloads for customers
- Credit note downloads
- Customer-initiated invoice generation (manual mode)
- Security validation (ownership, order status checks)

### Status_Sync

- Invoice status synchronization from B2Brouter API
- Smart polling strategy (hourly, 6-hourly, or disabled based on webhook config)
- Immediate 10-second status check after invoice generation
- Batch processing (50 invoices per run)
- Final state detection to avoid unnecessary API calls
- Randomized cron scheduling to distribute API load

### Webhook_Handler

- REST API endpoint for B2Brouter webhooks
- HMAC-SHA256 signature verification for security
- 5-minute timestamp window to prevent replay attacks
- Processes `issued_invoice.state_change` events
- Real-time invoice status updates (< 1 second)
- HPOS-compatible order lookup
- Webhook receipt tracking for fallback polling optimization

### Invoice_List_Table

- Admin page showing all generated invoices
- Pagination and sorting capabilities
- Bulk PDF download functionality
- View/Download buttons for individual invoices
- Environment-aware B2Brouter web app URLs

### API_Retry

- Exponential backoff retry logic for API calls
- Adaptive retry strategy (up to 5 attempts: 1s, 2s, 4s, 8s)
- Retries on transient errors (PDF not ready yet)
- Immediate failure on non-retryable errors (auth, permission)
- Used primarily for PDF downloads

---

## Data Flow

### Invoice Generation Flow

```
Order Completed
    ↓
Order_Handler::maybe_generate_invoice_automatic()
    ↓
Check: mode == 'automatic' && API key configured && !invoice_exists
    ↓
Invoice_Generator::generate_invoice()
    ↓
prepare_invoice_data() - Transform WC order to B2Brouter format
    ↓
get_peppol_tax_category() - Determine tax category for each line
    ↓
B2BRouterClient::invoices->create() - API call
    ↓
B2BRouterClient::invoices->send() - Send to recipient
    ↓
Store metadata: _b2brouter_invoice_id, _b2brouter_invoice_number
    ↓
Add order note
    ↓
Increment transaction counter
```

### Tax Category Detection

```
For each order line item:
    ↓
Check: is_reverse_charge(order)? → AE (intra-EU B2B)
    ↓
Check: product tax_status == 'none'? → NS (not subject)
    ↓
Check: product tax_class == 'zero-rate' && rate == 0? → Z (zero-rated)
    ↓
Check: rate == 0? → E (exempt)
    ↓
Otherwise → S (standard rate with calculated percentage)
```

### Invoice Status Sync Flow

**Webhook Mode (Real-Time)**:
```
B2Brouter invoice state changes
    ↓
B2Brouter sends webhook to /wp-json/b2brouter/v1/webhook
    ↓
Webhook_Handler::handle_webhook_request()
    ↓
Verify HMAC-SHA256 signature
    ↓
Verify timestamp (5-minute window)
    ↓
Extract invoice_id and new status from payload
    ↓
Find WooCommerce order via _b2brouter_invoice_id meta
    ↓
Update order meta: _b2brouter_invoice_status, _b2brouter_status_checked_at
    ↓
Store webhook receipt timestamp: _b2brouter_webhook_received_at
    ↓
Add order note: "Status updated via webhook"
    ↓
Return 200 OK (< 1 second total)
```

**Polling Mode (Backup/Fallback)**:
```
WordPress Cron runs (hourly or 6-hourly based on webhook config)
    ↓
Status_Sync::sync_invoice_statuses()
    ↓
Get orders with pending invoices (batch of 50)
    ↓
Skip orders with recent webhook receipt (< 6 hours)
    ↓
For each order:
    ↓
    API call: $client->invoices->retrieve($invoice_id)
    ↓
    Extract status from response
    ↓
    Update order meta: _b2brouter_invoice_status, _b2brouter_status_checked_at
    ↓
    Add order note if status changed
    ↓
Final state detection → skip future polling for completed invoices
```

**Immediate Check (After Generation)**:
```
Invoice generated successfully
    ↓
Schedule single-run status check in 10 seconds
    ↓
Status_Sync::immediate_status_check($order_id)
    ↓
Fetch status from B2Brouter API
    ↓
Update order meta with current status
    ↓
Provides fast feedback when webhooks not configured
```

---

## API Integration

The plugin uses the B2Brouter PHP SDK for all API operations:

```php
// Client initialization
$client = new \B2BRouter\B2BRouterClient($api_key, [
    'api_base' => $this->settings->get_api_base_url()
]);

// Create invoice
$invoice = $client->invoices->create($account_id, [
    'invoice' => $invoice_data
]);

// Send invoice to recipient
$client->invoices->send($invoice['id']);

// Download PDF
$pdf_content = $client->invoices->downloadPdf($invoice['id']);

// Validate API key
$accounts = $client->accounts->all(['limit' => 1]);
```

### API Endpoints

**Staging Environment**:
- Base URL: `https://api-staging.b2brouter.net`
- Use for testing and development

**Production Environment**:
- Base URL: `https://api.b2brouter.net`
- Use for live transactions

**Webhook Endpoint**:
- REST API: `/wp-json/b2brouter/v1/webhook`
- Method: POST
- Authentication: HMAC-SHA256 signature verification
- Event: `issued_invoice.state_change`
- Response: 200 OK on success, 401 on invalid signature, 403 if disabled

### Error Handling

The plugin implements comprehensive error handling:

- API errors are logged to order notes
- User-friendly error messages displayed in admin
- Detailed errors written to WordPress debug log
- Failed invoices do not prevent order completion

### Data Mapping

WooCommerce order data is transformed to B2Brouter format:

**Order Information**:
- `order_id` → `invoice.order_number` (optional, based on settings)
- `order_date` → `invoice.issue_date`
- `order_total` → calculated from line items

**Customer Information**:
- `billing_first_name + billing_last_name` → `invoice.customer.name`
- `billing_email` → `invoice.customer.email`
- `billing_address_1` → `invoice.customer.address`
- `billing_city` → `invoice.customer.city`
- `billing_postcode` → `invoice.customer.postal_code`
- `billing_country` → `invoice.customer.country_code`
- `_billing_tin` → `invoice.customer.tin` (if provided)

**Line Items**:
- `product_name` → `line.description`
- `quantity` → `line.quantity`
- `line_total` → `line.unit_price_without_tax`
- `line_tax` → calculated tax amount
- Tax rate → `line.taxes_attributes[].percent`
- Tax category → `line.taxes_attributes[].category` (S, E, Z, NS, AE)

**Tax Handling**:
- Merchant country extracted from WooCommerce settings
- Tax name localized by country (IVA, TVA, VAT, etc.)
- PEPPOL category determined per line item
- Intra-EU reverse charge automatically detected

---

## Extension Points

### Hooks

**Actions**:

```php
// Triggered after successful invoice generation
do_action('b2brouter_invoice_generated', $order_id, $invoice_data);

// Triggered after PDF is saved
do_action('b2brouter_pdf_saved', $order_id, $pdf_path);
```

**Filters**:

```php
// Modify invoice data before API submission
apply_filters('b2brouter_invoice_data', $invoice_data, $order);

// Customize invoice number format
apply_filters('b2brouter_invoice_number', $invoice_number, $order);

// Modify PDF storage path
apply_filters('b2brouter_pdf_storage_path', $path, $order_id);
```

### Custom Implementations

Developers can extend functionality by:

1. **Custom Tax Logic**: Filter `b2brouter_invoice_data` to modify tax categories
2. **Custom Numbering**: Filter `b2brouter_invoice_number` for unique numbering schemes
3. **Additional Metadata**: Hook into `b2brouter_invoice_generated` to store custom data
4. **Custom PDF Handling**: Filter `b2brouter_pdf_storage_path` for different storage locations

---

## Performance Considerations

### Caching

- PDF files cached locally in `wp-content/uploads/b2brouter-invoices/`
- Automatic cleanup via WordPress cron
- Configurable retention period

### Database

- Minimal database queries using HPOS when available
- Invoice metadata stored as order meta (efficient retrieval)
- Transaction counter cached in WordPress options

### API Calls

- Invoice creation and sending performed in single request flow
- PDF download only on-demand or when auto-save enabled
- API key validation cached (not performed on every request)

### HPOS Compatibility

The plugin is fully compatible with WooCommerce High-Performance Order Storage:

- Uses `wc_get_order()` for order retrieval
- Uses order object methods instead of direct meta queries
- No direct database queries to legacy posts table
- Compatible with both Custom Order Tables and legacy storage

---

## Security

### Authentication

- API key stored in WordPress options (hashed where possible)
- Environment configuration stored separately
- No API credentials exposed to client-side code

### Data Validation

- User input sanitized before storage
- API responses validated before processing
- Nonces verified on AJAX requests
- Capability checks on admin actions

### Webhook Security

- HMAC-SHA256 signature verification for all webhook requests
- Webhook secret stored securely in WordPress options
- 5-minute timestamp validation window to prevent replay attacks
- Requests with invalid signatures are rejected (401)
- Webhooks can be disabled entirely (403)

### PDF Access

- PDFs stored in uploads directory with random subdirectory names
- Direct access requires knowledge of full path
- Optional cleanup prevents indefinite storage

---

## Testing Architecture

Tests use mocked WordPress and WooCommerce functions (see `tests/bootstrap.php`). No WordPress installation required for testing.

### Mocked Components

- WordPress core functions (`get_option`, `update_option`, etc.)
- WooCommerce classes (`WC_Order`, `WC_Product`, etc.)
- B2Brouter API client (returns simulated responses)

### Test Coverage

Current coverage: 62.53% (1572/2514 lines) across 293 tests

Coverage by class:
- Webhook_Handler: 95.97%
- API_Retry: 91.43%
- Customer_Fields: 82.76%
- Invoice_List_Table: 72.77%
- Admin: 71.89%
- Settings: 70.63%
- Order_Handler: 59.64%
- Invoice_Generator: 59.15%
- Status_Sync: 56.86%

See [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md) for testing instructions.
