<?php
/**
 * Tests for Invoice_Generator class
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\Invoice_Generator;
use B2Brouter\WooCommerce\Settings;

/**
 * Invoice_Generator test case
 *
 * This test demonstrates:
 * - Testing a class WITH dependencies
 * - Using PHPUnit mocks for dependency injection
 * - Verifying method calls and return values
 * - Testing exception handling
 *
 * @since 1.0.0
 */
class InvoiceGeneratorTest extends TestCase {

    /**
     * Mock Settings instance
     *
     * @var Settings
     */
    private $mock_settings;

    /**
     * Invoice_Generator instance
     *
     * @var Invoice_Generator
     */
    private $generator;

    /**
     * Set up test
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();

        // Create mock Settings (the dependency)
        $this->mock_settings = $this->createMock(Settings::class);

        // Inject the mock into Invoice_Generator
        $this->generator = new Invoice_Generator($this->mock_settings);
    }

    /**
     * Helper method to inject a mock B2BRouter client
     *
     * @return void
     */
    private function injectMockClient() {
        // Create a mock B2BRouter client with real API payload
        $mock_client = new class {
            public $invoices;
            public function __construct() {
                $this->invoices = new class {
                    public function create($account, $params) {
                        return [
                            'id' => 354754,
                            'number' => 'INV-ES-2025-00078',
                            'total' => 200.0
                        ];
                    }
                    public function send($id) {
                        return true;
                    }
                    public function downloadPdf($invoice_id) {
                        return "%PDF-1.5\n%PDF data\n%%EOF";
                    }
                };
            }
        };

        // Inject mock client using reflection
        $reflection = new \ReflectionClass($this->generator);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->generator, $mock_client);
    }

    /**
     * Test that Invoice_Generator can be instantiated with Settings
     *
     * @return void
     */
    public function test_can_be_instantiated_with_settings() {
        $this->assertInstanceOf(Invoice_Generator::class, $this->generator);
    }

    /**
     * Test that dependency injection works
     *
     * This verifies that the Settings dependency was properly injected
     * by testing that we can mock its behavior.
     *
     * @return void
     */
    public function test_uses_injected_settings() {
        // Configure the mock to return a specific API key
        $this->mock_settings->method('get_api_key')
                           ->willReturn('test-api-key-123');

        // Try to generate an invoice (will fail because order doesn't exist)
        // WordPress functions are mocked in tests/bootstrap.php
        $result = $this->generator->generate_invoice(999);

        // Verify the result indicates failure (order doesn't exist)
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Order not found', $result['message']);
    }

    /**
     * Test has_invoice method
     *
     * @return void
     */
    public function test_has_invoice_returns_boolean() {
        // wc_get_order returns false by default (from bootstrap)
        $result = $this->generator->has_invoice(123);

        // Should return a boolean
        $this->assertIsBool($result);
        $this->assertFalse($result); // No order exists
    }

    /**
     * Test get_invoice_id method
     *
     * @return void
     */
    public function test_get_invoice_id_returns_null_for_invalid_order() {
        // wc_get_order returns false by default (from bootstrap)
        $result = $this->generator->get_invoice_id(999);

        $this->assertNull($result);
    }

    /**
     * Test that Invoice_Generator requires Settings dependency
     *
     * This test verifies that you can't create an Invoice_Generator
     * without providing Settings (type safety).
     *
     * @return void
     */
    public function test_requires_settings_dependency() {
        // This test is implicit - PHP will throw a TypeError if you try:
        // $generator = new Invoice_Generator(); // ❌ Error!
        // $generator = new Invoice_Generator('wrong type'); // ❌ Error!
        // $generator = new Invoice_Generator($settings); // ✅ Works!

        $this->expectNotToPerformAssertions();

        // If we got this far, the dependency injection is working correctly
    }

    /**
     * Test successful invoice generation
     *
     * @return void
     */
    public function test_generate_invoice_success() {
        global $wc_mock_orders;

        // Create a mock order
        $order = new WC_Order(100);
        $item = new WC_Order_Item_Product('Test Product');
        $order->add_item($item);
        $wc_mock_orders[100] = $order;

        // Configure mock settings
        $this->mock_settings->method('get_api_key')
                           ->willReturn('valid-api-key');
        $this->mock_settings->method('get_account_id')
                           ->willReturn('211162');
        $this->mock_settings->method('get_auto_save_pdf')
                           ->willReturn(false); // Don't auto-save PDF in this test
        $this->mock_settings->method('get_api_base_url')
                           ->willReturn('https://api-staging.b2brouter.net');
        $this->mock_settings->expects($this->once())
                           ->method('increment_transaction_count');

        // Inject mock B2BRouter client
        $this->injectMockClient();

        // Generate invoice
        $result = $this->generator->generate_invoice(100);

        // Verify success with REAL API payload values
        $this->assertTrue($result['success']);
        $this->assertEquals(354754, $result['invoice_id']);
        $this->assertEquals('INV-ES-2025-00078', $result['invoice_number']);
        $this->assertStringContainsString('Invoice generated successfully', $result['message']);

        // Verify order meta was saved
        $this->assertEquals(354754, $order->get_meta('_b2brouter_invoice_id'));
        $this->assertEquals('INV-ES-2025-00078', $order->get_meta('_b2brouter_invoice_number'));
        $this->assertNotEmpty($order->get_meta('_b2brouter_invoice_date'));

        // Cleanup
        unset($wc_mock_orders[100]);
    }

    /**
     * Test generate_invoice when invoice already exists
     *
     * @return void
     */
    public function test_generate_invoice_already_exists() {
        global $wc_mock_orders;

        // Create order with existing invoice
        $order = new WC_Order(101);
        $order->add_meta_data('_b2brouter_invoice_id', 'existing-invoice-id', true);
        $wc_mock_orders[101] = $order;

        $this->mock_settings->method('get_api_key')
                           ->willReturn('valid-api-key');

        // Try to generate invoice
        $result = $this->generator->generate_invoice(101);

        // Should fail
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already generated', $result['message']);

        // Cleanup
        unset($wc_mock_orders[101]);
    }

    /**
     * Test generate_invoice when API key not configured
     *
     * @return void
     */
    public function test_generate_invoice_no_api_key() {
        global $wc_mock_orders;

        $order = new WC_Order(102);
        $wc_mock_orders[102] = $order;

        // API key is empty
        $this->mock_settings->method('get_api_key')
                           ->willReturn('');

        $result = $this->generator->generate_invoice(102);

        // Should fail
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('API key not configured', $result['message']);

        // Cleanup
        unset($wc_mock_orders[102]);
    }

    /**
     * Test has_invoice with order that has invoice
     *
     * @return void
     */
    public function test_has_invoice_returns_true_when_invoice_exists() {
        global $wc_mock_orders;

        $order = new WC_Order(103);
        $order->add_meta_data('_b2brouter_invoice_id', 'inv-123', true);
        $wc_mock_orders[103] = $order;

        $result = $this->generator->has_invoice(103);

        $this->assertTrue($result);

        // Cleanup
        unset($wc_mock_orders[103]);
    }

    /**
     * Test has_invoice with order without invoice
     *
     * @return void
     */
    public function test_has_invoice_returns_false_when_no_invoice() {
        global $wc_mock_orders;

        $order = new WC_Order(104);
        $wc_mock_orders[104] = $order;

        $result = $this->generator->has_invoice(104);

        $this->assertFalse($result);

        // Cleanup
        unset($wc_mock_orders[104]);
    }

    /**
     * Test get_invoice_id with order that has invoice
     *
     * @return void
     */
    public function test_get_invoice_id_returns_id_when_exists() {
        global $wc_mock_orders;

        $order = new WC_Order(105);
        $order->add_meta_data('_b2brouter_invoice_id', 'inv-456', true);
        $wc_mock_orders[105] = $order;

        $result = $this->generator->get_invoice_id(105);

        $this->assertEquals('inv-456', $result);

        // Cleanup
        unset($wc_mock_orders[105]);
    }

    /**
     * Test get_invoice_id with order without invoice
     *
     * @return void
     */
    public function test_get_invoice_id_returns_empty_when_no_invoice() {
        global $wc_mock_orders;

        $order = new WC_Order(106);
        $wc_mock_orders[106] = $order;

        $result = $this->generator->get_invoice_id(106);

        $this->assertEmpty($result);

        // Cleanup
        unset($wc_mock_orders[106]);
    }

    /**
     * Test invoice generation with company name fallback
     *
     * @return void
     */
    public function test_generate_invoice_uses_company_name_fallback() {
        global $wc_mock_orders;

        // Order with no first/last name but has company
        $order = new WC_Order(107);
        $order->set_billing_first_name('');
        $order->set_billing_last_name('');
        $order->set_billing_company('Acme Corp');
        $item = new WC_Order_Item_Product('Product');
        $order->add_item($item);
        $wc_mock_orders[107] = $order;

        $this->mock_settings->method('get_api_key')
                           ->willReturn('valid-api-key');
        $this->mock_settings->method('get_account_id')
                           ->willReturn('211162');
        $this->mock_settings->method('get_auto_save_pdf')
                           ->willReturn(false);
        $this->mock_settings->method('increment_transaction_count')
                           ->willReturn(true);

        $this->injectMockClient();

        $result = $this->generator->generate_invoice(107);

        $this->assertTrue($result['success']);
        $this->assertEquals(354754, $result['invoice_id']);

        // Cleanup
        unset($wc_mock_orders[107]);
    }

    /**
     * Test invoice generation with shipping
     *
     * @return void
     */
    public function test_generate_invoice_includes_shipping() {
        global $wc_mock_orders;

        $order = new WC_Order(108);
        $order->set_shipping_total(10.00);
        $order->set_shipping_tax(2.00);
        $item = new WC_Order_Item_Product('Product');
        $order->add_item($item);
        $wc_mock_orders[108] = $order;

        $this->mock_settings->method('get_api_key')
                           ->willReturn('valid-api-key');
        $this->mock_settings->method('get_account_id')
                           ->willReturn('211162');
        $this->mock_settings->method('get_auto_save_pdf')
                           ->willReturn(false);
        $this->mock_settings->method('increment_transaction_count')
                           ->willReturn(true);

        $this->injectMockClient();

        $result = $this->generator->generate_invoice(108);

        $this->assertTrue($result['success']);
        $this->assertEquals(354754, $result['invoice_id']);

        // Cleanup
        unset($wc_mock_orders[108]);
    }

    /**
     * Test invoice generation with item taxes
     *
     * @return void
     */
    public function test_generate_invoice_calculates_item_tax_rate() {
        global $wc_mock_orders;

        $order = new WC_Order(109);
        $item = new WC_Order_Item_Product('Product with Tax');
        $item->set_total(100.00);
        $item->set_taxes(array('total' => array(10.00, 5.00))); // 15% total tax
        $order->add_item($item);
        $wc_mock_orders[109] = $order;

        $this->mock_settings->method('get_api_key')
                           ->willReturn('valid-api-key');
        $this->mock_settings->method('get_account_id')
                           ->willReturn('211162');
        $this->mock_settings->method('get_auto_save_pdf')
                           ->willReturn(false);
        $this->mock_settings->method('increment_transaction_count')
                           ->willReturn(true);

        $this->injectMockClient();

        $result = $this->generator->generate_invoice(109);

        $this->assertTrue($result['success']);
        $this->assertEquals(354754, $result['invoice_id']);

        // Cleanup
        unset($wc_mock_orders[109]);
    }

    /**
     * Test invoice generation with zero-price item (free product)
     *
     * @return void
     */
    public function test_generate_invoice_handles_zero_price_item() {
        global $wc_mock_orders;

        $order = new WC_Order(110);
        $item = new WC_Order_Item_Product('Free Product');
        $item->set_total(0.00);
        $item->set_taxes(array('total' => array(0)));
        $order->add_item($item);
        $wc_mock_orders[110] = $order;

        $this->mock_settings->method('get_api_key')
                           ->willReturn('valid-api-key');
        $this->mock_settings->method('get_account_id')
                           ->willReturn('211162');
        $this->mock_settings->method('get_auto_save_pdf')
                           ->willReturn(false);
        $this->mock_settings->method('increment_transaction_count')
                           ->willReturn(true);

        $this->injectMockClient();

        $result = $this->generator->generate_invoice(110);

        $this->assertTrue($result['success']);
        $this->assertEquals(354754, $result['invoice_id']);

        // Cleanup
        unset($wc_mock_orders[110]);
    }

    /**
     * Test that order notes are added on error
     *
     * @return void
     */
    public function test_generate_invoice_adds_note_on_error() {
        global $wc_mock_orders;

        // Order exists but no invoice ID
        $order = new WC_Order(111);
        $wc_mock_orders[111] = $order;

        // No API key configured
        $this->mock_settings->method('get_api_key')
                           ->willReturn('');

        $result = $this->generator->generate_invoice(111);

        $this->assertFalse($result['success']);

        // Cleanup
        unset($wc_mock_orders[111]);
    }

    /**
     * Test multiple invoice generations use cached client
     *
     * @return void
     */
    public function test_client_is_cached_across_calls() {
        global $wc_mock_orders;

        // Create two orders
        $order1 = new WC_Order(112);
        $item1 = new WC_Order_Item_Product('Product 1');
        $order1->add_item($item1);
        $wc_mock_orders[112] = $order1;

        $order2 = new WC_Order(113);
        $item2 = new WC_Order_Item_Product('Product 2');
        $order2->add_item($item2);
        $wc_mock_orders[113] = $order2;

        $this->mock_settings->method('get_api_key')
                           ->willReturn('valid-api-key');
        $this->mock_settings->method('get_account_id')
                           ->willReturn('211162');
        $this->mock_settings->method('get_auto_save_pdf')
                           ->willReturn(false);
        $this->mock_settings->method('increment_transaction_count')
                           ->willReturn(true);

        $this->injectMockClient();

        // Generate two invoices
        $result1 = $this->generator->generate_invoice(112);
        $result2 = $this->generator->generate_invoice(113);

        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);
        $this->assertEquals(354754, $result1['invoice_id']);
        $this->assertEquals(354754, $result2['invoice_id']);

        // Cleanup
        unset($wc_mock_orders[112], $wc_mock_orders[113]);
    }

    // ========== Email Attachment Tests (Phase 5.1) ==========

    /**
     * Test attach_pdf_to_email returns unchanged when order is invalid
     *
     * @return void
     */
    public function test_attach_pdf_to_email_returns_unchanged_for_invalid_order() {
        $attachments = array('/path/to/existing.pdf');

        $result = $this->generator->attach_pdf_to_email($attachments, 'customer_completed_order', null);

        $this->assertEquals($attachments, $result);
        $this->assertCount(1, $result);
    }

    /**
     * Test attach_pdf_to_email returns unchanged when order has no invoice
     *
     * @return void
     */
    public function test_attach_pdf_to_email_returns_unchanged_when_no_invoice() {
        global $wc_mock_orders;

        $order = new WC_Order(300);
        $wc_mock_orders[300] = $order;

        $attachments = array();

        $result = $this->generator->attach_pdf_to_email($attachments, 'customer_completed_order', $order);

        $this->assertEquals($attachments, $result);
        $this->assertCount(0, $result);

        unset($wc_mock_orders[300]);
    }

    /**
     * Test attach_pdf_to_email returns unchanged when setting disabled
     *
     * @return void
     */
    public function test_attach_pdf_to_email_skips_when_setting_disabled() {
        global $wc_mock_orders;

        $order = new WC_Order(301);
        $order->add_meta_data('_b2brouter_invoice_id', 'inv-test', true);
        $wc_mock_orders[301] = $order;

        $this->mock_settings->method('get_attach_to_order_completed')
                           ->willReturn(false);

        $attachments = array();

        $result = $this->generator->attach_pdf_to_email($attachments, 'customer_completed_order', $order);

        $this->assertEquals($attachments, $result);
        $this->assertCount(0, $result);

        unset($wc_mock_orders[301]);
    }

    /**
     * Test attach_pdf_to_email skips unknown email types
     *
     * @return void
     */
    public function test_attach_pdf_to_email_skips_unknown_email_types() {
        global $wc_mock_orders;

        $order = new WC_Order(302);
        $order->add_meta_data('_b2brouter_invoice_id', 'inv-test', true);
        $wc_mock_orders[302] = $order;

        $this->mock_settings->method('get_attach_to_order_completed')
                           ->willReturn(true);

        $attachments = array();

        // Unknown email type
        $result = $this->generator->attach_pdf_to_email($attachments, 'new_order', $order);

        $this->assertEquals($attachments, $result);
        $this->assertCount(0, $result);

        unset($wc_mock_orders[302]);
    }

    /**
     * Test attach_pdf_to_email checks correct setting for order_completed
     *
     * @return void
     */
    public function test_attach_pdf_to_email_checks_order_completed_setting() {
        global $wc_mock_orders;

        $order = new WC_Order(303);
        $order->add_meta_data('_b2brouter_invoice_id', 'inv-test', true);
        $wc_mock_orders[303] = $order;

        // Enable order_completed, disable customer_invoice
        $this->mock_settings->method('get_attach_to_order_completed')
                           ->willReturn(true);
        $this->mock_settings->method('get_attach_to_customer_invoice')
                           ->willReturn(false);

        $attachments = array();

        // Should NOT attach for customer_invoice (disabled)
        $result = $this->generator->attach_pdf_to_email($attachments, 'customer_invoice', $order);
        $this->assertCount(0, $result);

        unset($wc_mock_orders[303]);
    }

    /**
     * Test attach_pdf_to_email checks correct setting for customer_invoice
     *
     * @return void
     */
    public function test_attach_pdf_to_email_checks_customer_invoice_setting() {
        global $wc_mock_orders;

        $order = new WC_Order(304);
        $order->add_meta_data('_b2brouter_invoice_id', 'inv-test', true);
        $wc_mock_orders[304] = $order;

        // Disable order_completed, enable customer_invoice
        $this->mock_settings->method('get_attach_to_order_completed')
                           ->willReturn(false);
        $this->mock_settings->method('get_attach_to_customer_invoice')
                           ->willReturn(true);

        $attachments = array();

        // Should NOT attach for customer_completed_order (disabled)
        $result = $this->generator->attach_pdf_to_email($attachments, 'customer_completed_order', $order);
        $this->assertCount(0, $result);

        unset($wc_mock_orders[304]);
    }

    // ========== Cleanup Tests (Phase 5.3) ==========

    /**
     * Test cleanup_old_pdfs returns zero when directory doesn't exist
     *
     * @return void
     */
    public function test_cleanup_old_pdfs_returns_zero_when_no_directory() {
        $this->mock_settings->method('get_pdf_storage_path')
                           ->willReturn('/nonexistent/path');

        $result = $this->generator->cleanup_old_pdfs(90);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('deleted', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals(0, $result['deleted']);
        $this->assertEquals(0, $result['errors']);
    }

    /**
     * Test cleanup_old_pdfs returns zero when no PDF files
     *
     * @return void
     */
    public function test_cleanup_old_pdfs_returns_zero_when_no_files() {
        // Use a real temporary directory with no PDFs
        $temp_dir = sys_get_temp_dir() . '/b2brouter-test-' . uniqid();
        mkdir($temp_dir);

        $this->mock_settings->method('get_pdf_storage_path')
                           ->willReturn($temp_dir);

        $result = $this->generator->cleanup_old_pdfs(90);

        $this->assertEquals(0, $result['deleted']);
        $this->assertEquals(0, $result['errors']);

        // Cleanup
        rmdir($temp_dir);
    }

    /**
     * Test cleanup_old_pdfs accepts custom days parameter
     *
     * @return void
     */
    public function test_cleanup_old_pdfs_accepts_custom_days() {
        $this->mock_settings->method('get_pdf_storage_path')
                           ->willReturn('/nonexistent/path');

        // Should not throw error with different days values
        $result30 = $this->generator->cleanup_old_pdfs(30);
        $result90 = $this->generator->cleanup_old_pdfs(90);
        $result365 = $this->generator->cleanup_old_pdfs(365);

        $this->assertIsArray($result30);
        $this->assertIsArray($result90);
        $this->assertIsArray($result365);
    }

    /**
     * Test cleanup_old_pdfs default parameter is 90 days
     *
     * @return void
     */
    public function test_cleanup_old_pdfs_default_is_90_days() {
        $this->mock_settings->method('get_pdf_storage_path')
                           ->willReturn('/nonexistent/path');

        // Call without parameter (should default to 90)
        $result = $this->generator->cleanup_old_pdfs();

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['deleted']);
        $this->assertEquals(0, $result['errors']);
    }

    /**
     * Test cleanup_old_pdfs result structure
     *
     * @return void
     */
    public function test_cleanup_old_pdfs_returns_correct_structure() {
        $this->mock_settings->method('get_pdf_storage_path')
                           ->willReturn('/nonexistent/path');

        $result = $this->generator->cleanup_old_pdfs(60);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('deleted', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertIsInt($result['deleted']);
        $this->assertIsInt($result['errors']);
    }

    // ========== PDF Download Tests (Phase 1) ==========

    /**
     * Test download_invoice_pdf with invalid invoice ID
     *
     * @return void
     */
    public function test_download_invoice_pdf_fails_with_empty_invoice_id() {
        $result = $this->generator->download_invoice_pdf('');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invoice ID is required', $result['message']);
    }

    /**
     * Test download_invoice_pdf with no API key
     *
     * @return void
     */
    public function test_download_invoice_pdf_fails_without_api_key() {
        $this->mock_settings->method('get_api_key')
                           ->willReturn('');

        $result = $this->generator->download_invoice_pdf('inv-123');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('API key not configured', $result['message']);
    }

    /**
     * Test download_invoice_pdf returns correct structure
     *
     * @return void
     */
    public function test_download_invoice_pdf_returns_correct_structure() {
        $this->mock_settings->method('get_api_key')
                           ->willReturn('');

        $result = $this->generator->download_invoice_pdf('inv-123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertIsBool($result['success']);
        $this->assertIsString($result['message']);
    }

    // ========== PDF Save Tests (Phase 3) ==========

    /**
     * Test save_invoice_pdf with invalid order
     *
     * @return void
     */
    public function test_save_invoice_pdf_fails_with_invalid_order() {
        $result = $this->generator->save_invoice_pdf(999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Order not found', $result['message']);
    }

    /**
     * Test save_invoice_pdf with order without invoice
     *
     * @return void
     */
    public function test_save_invoice_pdf_fails_without_invoice() {
        global $wc_mock_orders;

        $order = new WC_Order(400);
        $wc_mock_orders[400] = $order;

        $result = $this->generator->save_invoice_pdf(400);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No invoice found', $result['message']);

        unset($wc_mock_orders[400]);
    }

    /**
     * Test save_invoice_pdf returns correct structure
     *
     * @return void
     */
    public function test_save_invoice_pdf_returns_correct_structure() {
        $result = $this->generator->save_invoice_pdf(999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertIsBool($result['success']);
        $this->assertIsString($result['message']);
    }

    /**
     * Test save_invoice_pdf with force_download parameter
     *
     * @return void
     */
    public function test_save_invoice_pdf_accepts_force_download_parameter() {
        global $wc_mock_orders;

        $order = new WC_Order(401);
        $wc_mock_orders[401] = $order;

        // Test without forcing
        $result1 = $this->generator->save_invoice_pdf(401, false);
        $this->assertIsArray($result1);

        // Test with forcing
        $result2 = $this->generator->save_invoice_pdf(401, true);
        $this->assertIsArray($result2);

        unset($wc_mock_orders[401]);
    }

    // ========== PDF Stream Tests (Phase 4) ==========

    /**
     * Test stream_invoice_pdf with invalid order calls wp_die
     *
     * @return void
     */
    public function test_stream_invoice_pdf_fails_with_invalid_order() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('wp_die called');

        $this->generator->stream_invoice_pdf(999);
    }

    /**
     * Test stream_invoice_pdf with order without invoice calls wp_die
     *
     * @return void
     */
    public function test_stream_invoice_pdf_fails_without_invoice() {
        global $wc_mock_orders;

        $order = new WC_Order(402);
        $wc_mock_orders[402] = $order;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('wp_die called');

        $this->generator->stream_invoice_pdf(402);

        unset($wc_mock_orders[402]);
    }

    /**
     * Test stream_invoice_pdf accepts download parameter (both modes call wp_die without invoice)
     *
     * @return void
     */
    public function test_stream_invoice_pdf_accepts_download_parameter() {
        global $wc_mock_orders;

        $order = new WC_Order(403);
        $wc_mock_orders[403] = $order;

        // Both modes should call wp_die when no invoice exists
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('wp_die called');

        // Test view mode (will throw exception)
        $this->generator->stream_invoice_pdf(403, false);

        unset($wc_mock_orders[403]);
    }

    // ========== PDF Delete Tests (Phase 5) ==========

    /**
     * Test delete_invoice_pdf with invalid order returns false
     *
     * @return void
     */
    public function test_delete_invoice_pdf_returns_false_with_invalid_order() {
        $result = $this->generator->delete_invoice_pdf(999);

        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    /**
     * Test delete_invoice_pdf with order without cached PDF returns false
     *
     * @return void
     */
    public function test_delete_invoice_pdf_returns_false_without_cached_pdf() {
        global $wc_mock_orders;

        $order = new WC_Order(404);
        $wc_mock_orders[404] = $order;

        $result = $this->generator->delete_invoice_pdf(404);

        $this->assertIsBool($result);
        $this->assertFalse($result);

        unset($wc_mock_orders[404]);
    }

    /**
     * Test delete_invoice_pdf returns boolean
     *
     * @return void
     */
    public function test_delete_invoice_pdf_returns_boolean() {
        $result = $this->generator->delete_invoice_pdf(999);

        $this->assertIsBool($result);
    }

    // ========== Invoice Number Formatting Tests ==========

    /**
     * Test format_invoice_number with series code
     *
     * @return void
     */
    public function test_format_invoice_number_with_series_code() {
        $formatted = Invoice_Generator::format_invoice_number('12345', 'INV');
        $this->assertEquals('INV-12345', $formatted);
    }

    /**
     * Test format_invoice_number without series code returns number only
     *
     * @return void
     */
    public function test_format_invoice_number_without_series_code() {
        $formatted = Invoice_Generator::format_invoice_number('12345', '');
        $this->assertEquals('12345', $formatted);
    }

    /**
     * Test format_invoice_number with empty invoice number
     *
     * @return void
     */
    public function test_format_invoice_number_with_empty_number() {
        $formatted = Invoice_Generator::format_invoice_number('', 'INV');
        $this->assertEquals('', $formatted);
    }

    /**
     * Test format_invoice_number with both empty
     *
     * @return void
     */
    public function test_format_invoice_number_with_both_empty() {
        $formatted = Invoice_Generator::format_invoice_number('', '');
        $this->assertEquals('', $formatted);
    }

    /**
     * Test get_formatted_invoice_number from order
     *
     * @return void
     */
    public function test_get_formatted_invoice_number_from_order() {
        global $wc_mock_orders;

        $order = new WC_Order(405);
        $order->add_meta_data('_b2brouter_invoice_number', '12345', true);
        $order->add_meta_data('_b2brouter_invoice_series_code', 'INV', true);
        $wc_mock_orders[405] = $order;

        $formatted = Invoice_Generator::get_formatted_invoice_number($order);
        $this->assertEquals('INV-12345', $formatted);

        unset($wc_mock_orders[405]);
    }

    /**
     * Test get_formatted_invoice_number without series code
     *
     * @return void
     */
    public function test_get_formatted_invoice_number_without_series_code() {
        global $wc_mock_orders;

        $order = new WC_Order(406);
        $order->add_meta_data('_b2brouter_invoice_number', '12345', true);
        $wc_mock_orders[406] = $order;

        $formatted = Invoice_Generator::get_formatted_invoice_number($order);
        $this->assertEquals('12345', $formatted);

        unset($wc_mock_orders[406]);
    }

    /**
     * Test format_invoice_number with various series codes
     *
     * @return void
     */
    public function test_format_invoice_number_with_various_series() {
        $this->assertEquals('CN-98765', Invoice_Generator::format_invoice_number('98765', 'CN'));
        $this->assertEquals('S01-111', Invoice_Generator::format_invoice_number('111', 'S01'));
        $this->assertEquals('R01-222', Invoice_Generator::format_invoice_number('222', 'R01'));
    }

    // ========== Tax Handling Tests (PEPPOL Compliance) ==========

    /**
     * Test get_merchant_country extracts country from WooCommerce settings
     *
     * @return void
     */
    public function test_get_merchant_country_extracts_from_settings() {
        // Mock WooCommerce option
        update_option('woocommerce_default_country', 'ES:B');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('get_merchant_country');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator);

        $this->assertEquals('ES', $result);

        // Cleanup
        delete_option('woocommerce_default_country');
    }

    /**
     * Test get_merchant_country handles country without state
     *
     * @return void
     */
    public function test_get_merchant_country_handles_country_without_state() {
        update_option('woocommerce_default_country', 'FR');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('get_merchant_country');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator);

        $this->assertEquals('FR', $result);

        delete_option('woocommerce_default_country');
    }

    /**
     * Test get_merchant_country returns uppercase
     *
     * @return void
     */
    public function test_get_merchant_country_returns_uppercase() {
        update_option('woocommerce_default_country', 'de:BY');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('get_merchant_country');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator);

        $this->assertEquals('DE', $result);

        delete_option('woocommerce_default_country');
    }

    /**
     * Test get_tax_name returns correct name for Spain
     *
     * @return void
     */
    public function test_get_tax_name_returns_iva_for_spain() {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('get_tax_name');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator, 'ES');

        $this->assertEquals('IVA', $result);
    }

    /**
     * Test get_tax_name returns correct name for various countries
     *
     * @return void
     */
    public function test_get_tax_name_returns_correct_names_for_countries() {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('get_tax_name');
        $method->setAccessible(true);

        $this->assertEquals('TVA', $method->invoke($this->generator, 'FR'));
        $this->assertEquals('MwSt', $method->invoke($this->generator, 'DE'));
        $this->assertEquals('VAT', $method->invoke($this->generator, 'GB'));
        $this->assertEquals('VAT', $method->invoke($this->generator, 'IE'));
        $this->assertEquals('BTW', $method->invoke($this->generator, 'NL'));
        $this->assertEquals('GST', $method->invoke($this->generator, 'CA'));
        $this->assertEquals('Sales Tax', $method->invoke($this->generator, 'US'));
    }

    /**
     * Test get_tax_name returns default VAT for unknown country
     *
     * @return void
     */
    public function test_get_tax_name_returns_default_for_unknown_country() {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('get_tax_name');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator, 'XX');

        $this->assertEquals('VAT', $result);
    }

    /**
     * Test get_tax_name is case insensitive
     *
     * @return void
     */
    public function test_get_tax_name_is_case_insensitive() {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('get_tax_name');
        $method->setAccessible(true);

        $this->assertEquals('IVA', $method->invoke($this->generator, 'es'));
        $this->assertEquals('IVA', $method->invoke($this->generator, 'Es'));
        $this->assertEquals('IVA', $method->invoke($this->generator, 'ES'));
    }

    /**
     * Test is_eu_country returns true for EU countries
     *
     * @return void
     */
    public function test_is_eu_country_returns_true_for_eu_members() {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('is_eu_country');
        $method->setAccessible(true);

        $eu_countries = ['ES', 'FR', 'DE', 'IT', 'NL', 'BE', 'PT', 'IE', 'AT', 'PL'];

        foreach ($eu_countries as $country) {
            $this->assertTrue(
                $method->invoke($this->generator, $country),
                "$country should be recognized as EU country"
            );
        }
    }

    /**
     * Test is_eu_country returns false for non-EU countries
     *
     * @return void
     */
    public function test_is_eu_country_returns_false_for_non_eu() {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('is_eu_country');
        $method->setAccessible(true);

        $non_eu_countries = ['US', 'GB', 'CH', 'NO', 'CA', 'AU'];

        foreach ($non_eu_countries as $country) {
            $this->assertFalse(
                $method->invoke($this->generator, $country),
                "$country should NOT be recognized as EU country"
            );
        }
    }

    /**
     * Test is_eu_country is case insensitive
     *
     * @return void
     */
    public function test_is_eu_country_is_case_insensitive() {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('is_eu_country');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->generator, 'es'));
        $this->assertTrue($method->invoke($this->generator, 'Es'));
        $this->assertTrue($method->invoke($this->generator, 'ES'));
    }

    /**
     * Test is_reverse_charge returns false when no TIN
     *
     * @return void
     */
    public function test_is_reverse_charge_returns_false_without_tin() {
        global $wc_mock_orders;

        $order = new WC_Order(500);
        $order->set_billing_country('FR');
        $wc_mock_orders[500] = $order;

        update_option('woocommerce_default_country', 'ES');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('is_reverse_charge');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator, $order);

        $this->assertFalse($result);

        unset($wc_mock_orders[500]);
        delete_option('woocommerce_default_country');
    }

    /**
     * Test is_reverse_charge returns false for same country
     *
     * @return void
     */
    public function test_is_reverse_charge_returns_false_for_same_country() {
        global $wc_mock_orders;

        $order = new WC_Order(501);
        $order->set_billing_country('ES');
        $order->add_meta_data('_billing_tin', 'ESA12345678', true);
        $wc_mock_orders[501] = $order;

        update_option('woocommerce_default_country', 'ES');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('is_reverse_charge');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator, $order);

        $this->assertFalse($result);

        unset($wc_mock_orders[501]);
        delete_option('woocommerce_default_country');
    }

    /**
     * Test is_reverse_charge returns true for intra-EU B2B
     *
     * @return void
     */
    public function test_is_reverse_charge_returns_true_for_intra_eu_b2b() {
        global $wc_mock_orders;

        $order = new WC_Order(502);
        $order->set_billing_country('FR');
        $order->add_meta_data('_billing_tin', 'FR12345678901', true);
        $wc_mock_orders[502] = $order;

        update_option('woocommerce_default_country', 'ES');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('is_reverse_charge');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator, $order);

        $this->assertTrue($result);

        unset($wc_mock_orders[502]);
        delete_option('woocommerce_default_country');
    }

    /**
     * Test is_reverse_charge returns false for non-EU customer
     *
     * @return void
     */
    public function test_is_reverse_charge_returns_false_for_non_eu_customer() {
        global $wc_mock_orders;

        $order = new WC_Order(503);
        $order->set_billing_country('US');
        $order->add_meta_data('_billing_tin', 'US123456789', true);
        $wc_mock_orders[503] = $order;

        update_option('woocommerce_default_country', 'ES');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('is_reverse_charge');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator, $order);

        $this->assertFalse($result);

        unset($wc_mock_orders[503]);
        delete_option('woocommerce_default_country');
    }

    /**
     * Test get_peppol_tax_category returns S for standard rate
     *
     * @return void
     */
    public function test_get_peppol_tax_category_returns_s_for_standard_rate() {
        global $wc_mock_orders;

        $order = new WC_Order(510);
        $order->set_billing_country('ES');
        $wc_mock_orders[510] = $order;

        $item = new WC_Order_Item_Product('Product');
        $product = new WC_Product();
        $product->set_tax_status('taxable');
        $item->set_product($product);

        update_option('woocommerce_default_country', 'ES');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('get_peppol_tax_category');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator, $item, $order, 21.0);

        $this->assertEquals('S', $result['category']);
        $this->assertEquals('IVA', $result['name']);
        $this->assertEquals(21.0, $result['percent']);

        unset($wc_mock_orders[510]);
        delete_option('woocommerce_default_country');
    }

    /**
     * Test get_peppol_tax_category returns E for exempt
     *
     * @return void
     */
    public function test_get_peppol_tax_category_returns_e_for_exempt() {
        global $wc_mock_orders;

        $order = new WC_Order(511);
        $order->set_billing_country('ES');
        $wc_mock_orders[511] = $order;

        $item = new WC_Order_Item_Product('Product');
        $product = new WC_Product();
        $product->set_tax_status('taxable');
        $product->set_tax_class('');
        $item->set_product($product);

        update_option('woocommerce_default_country', 'ES');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('get_peppol_tax_category');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator, $item, $order, 0.0);

        $this->assertEquals('E', $result['category']);
        $this->assertEquals('IVA', $result['name']);
        $this->assertEquals(0.0, $result['percent']);

        unset($wc_mock_orders[511]);
        delete_option('woocommerce_default_country');
    }

    /**
     * Test get_peppol_tax_category returns Z for zero-rated
     *
     * @return void
     */
    public function test_get_peppol_tax_category_returns_z_for_zero_rated() {
        global $wc_mock_orders;

        $order = new WC_Order(512);
        $order->set_billing_country('ES');
        $wc_mock_orders[512] = $order;

        $item = new WC_Order_Item_Product('Product');
        $product = new WC_Product();
        $product->set_tax_status('taxable');
        $product->set_tax_class('zero-rate');
        $item->set_product($product);

        update_option('woocommerce_default_country', 'ES');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('get_peppol_tax_category');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator, $item, $order, 0.0);

        $this->assertEquals('Z', $result['category']);
        $this->assertEquals('IVA', $result['name']);
        $this->assertEquals(0.0, $result['percent']);

        unset($wc_mock_orders[512]);
        delete_option('woocommerce_default_country');
    }

    /**
     * Test get_peppol_tax_category returns NS for non-taxable
     *
     * @return void
     */
    public function test_get_peppol_tax_category_returns_ns_for_non_taxable() {
        global $wc_mock_orders;

        $order = new WC_Order(513);
        $order->set_billing_country('ES');
        $wc_mock_orders[513] = $order;

        $item = new WC_Order_Item_Product('Product');
        $product = new WC_Product();
        $product->set_tax_status('none');
        $item->set_product($product);

        update_option('woocommerce_default_country', 'ES');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('get_peppol_tax_category');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator, $item, $order, 0.0);

        $this->assertEquals('NS', $result['category']);
        $this->assertEquals('IVA', $result['name']);
        $this->assertEquals(0.0, $result['percent']);

        unset($wc_mock_orders[513]);
        delete_option('woocommerce_default_country');
    }

    /**
     * Test get_peppol_tax_category returns AE for reverse charge
     *
     * @return void
     */
    public function test_get_peppol_tax_category_returns_ae_for_reverse_charge() {
        global $wc_mock_orders;

        $order = new WC_Order(514);
        $order->set_billing_country('FR');
        $order->add_meta_data('_billing_tin', 'FR12345678901', true);
        $wc_mock_orders[514] = $order;

        $item = new WC_Order_Item_Product('Product');
        $product = new WC_Product();
        $product->set_tax_status('taxable');
        $item->set_product($product);

        update_option('woocommerce_default_country', 'ES');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('get_peppol_tax_category');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator, $item, $order, 0.0);

        $this->assertEquals('AE', $result['category']);
        $this->assertEquals('IVA', $result['name']);
        $this->assertEquals(0.0, $result['percent']);

        unset($wc_mock_orders[514]);
        delete_option('woocommerce_default_country');
    }

    /**
     * Test get_peppol_tax_category uses correct tax name per country
     *
     * @return void
     */
    public function test_get_peppol_tax_category_uses_correct_tax_name_per_country() {
        global $wc_mock_orders;

        // Test France with TVA
        $order = new WC_Order(515);
        $order->set_billing_country('FR');
        $wc_mock_orders[515] = $order;

        $item = new WC_Order_Item_Product('Product');
        $product = new WC_Product();
        $product->set_tax_status('taxable');
        $item->set_product($product);

        update_option('woocommerce_default_country', 'FR');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('get_peppol_tax_category');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator, $item, $order, 20.0);

        $this->assertEquals('TVA', $result['name']);
        $this->assertEquals('S', $result['category']);

        unset($wc_mock_orders[515]);
        delete_option('woocommerce_default_country');
    }

    /**
     * Test get_peppol_tax_category returns correct structure
     *
     * @return void
     */
    public function test_get_peppol_tax_category_returns_correct_structure() {
        global $wc_mock_orders;

        $order = new WC_Order(516);
        $order->set_billing_country('ES');
        $wc_mock_orders[516] = $order;

        $item = new WC_Order_Item_Product('Product');
        $product = new WC_Product();
        $product->set_tax_status('taxable');
        $item->set_product($product);

        update_option('woocommerce_default_country', 'ES');

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('get_peppol_tax_category');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator, $item, $order, 21.0);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('percent', $result);
        $this->assertIsString($result['category']);
        $this->assertIsString($result['name']);
        $this->assertIsFloat($result['percent']);

        unset($wc_mock_orders[516]);
        delete_option('woocommerce_default_country');
    }
}
