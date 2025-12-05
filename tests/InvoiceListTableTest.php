<?php
/**
 * Tests for Invoice_List_Table class
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\Invoice_List_Table;
use B2Brouter\WooCommerce\Settings;
use B2Brouter\WooCommerce\Invoice_Generator;

/**
 * Invoice_List_Table test case
 *
 * @since 1.0.0
 */
class InvoiceListTableTest extends TestCase {

    /**
     * Mock Settings instance
     *
     * @var Settings
     */
    private $mock_settings;

    /**
     * Mock Invoice_Generator instance
     *
     * @var Invoice_Generator
     */
    private $mock_invoice_generator;

    /**
     * Invoice_List_Table instance
     *
     * @var Invoice_List_Table
     */
    private $list_table;

    /**
     * Set up test
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();

        // Mock dependencies
        $this->mock_settings = $this->createMock(Settings::class);
        $this->mock_invoice_generator = $this->createMock(Invoice_Generator::class);

        // Create instance
        $this->list_table = new Invoice_List_Table(
            $this->mock_settings,
            $this->mock_invoice_generator
        );
    }

    /**
     * Test constructor sets properties correctly
     *
     * @return void
     */
    public function test_constructor() {
        $this->assertInstanceOf(
            'B2Brouter\WooCommerce\Invoice_List_Table',
            $this->list_table
        );
    }

    /**
     * Test get_columns returns correct columns
     *
     * @return void
     */
    public function test_get_columns() {
        $columns = $this->list_table->get_columns();

        $this->assertIsArray($columns);
        $this->assertArrayHasKey('cb', $columns);
        $this->assertArrayHasKey('order_number', $columns);
        $this->assertArrayHasKey('customer', $columns);
        $this->assertArrayHasKey('invoice_number', $columns);
        $this->assertArrayHasKey('date', $columns);
        $this->assertArrayHasKey('status', $columns);
        $this->assertArrayHasKey('amount', $columns);
        $this->assertArrayHasKey('actions', $columns);
    }

    /**
     * Test get_sortable_columns returns correct sortable columns
     *
     * @return void
     */
    public function test_get_sortable_columns() {
        $sortable = $this->list_table->get_sortable_columns();

        $this->assertIsArray($sortable);
        $this->assertArrayHasKey('order_number', $sortable);
        $this->assertArrayHasKey('date', $sortable);

        // Verify format: array('column_key', default_sorted_desc)
        $this->assertEquals(array('ID', false), $sortable['order_number']);
        $this->assertEquals(array('date', true), $sortable['date']);
    }

    /**
     * Test get_bulk_actions returns correct actions
     *
     * @return void
     */
    public function test_get_bulk_actions() {
        $actions = $this->list_table->get_bulk_actions();

        $this->assertIsArray($actions);
        $this->assertArrayHasKey('download', $actions);
        $this->assertEquals('Download PDFs', $actions['download']);
    }

    /**
     * Test column_order_number with regular order
     *
     * @return void
     */
    public function test_column_order_number_regular_order() {
        // Create mock order
        $mock_order = $this->createMock(WC_Order::class);
        $mock_order->method('get_id')->willReturn(123);
        $mock_order->method('get_order_number')->willReturn('123');
        $mock_order->method('get_type')->willReturn('shop_order');

        // Use reflection to access protected method
        $method = new ReflectionMethod(Invoice_List_Table::class, 'column_order_number');
        $method->setAccessible(true);

        $result = $method->invoke($this->list_table, $mock_order);

        $this->assertStringContainsString('#123', $result);
        $this->assertStringContainsString('<a href=', $result);
    }

    /**
     * Test column_order_number with refund order
     *
     * @return void
     */
    public function test_column_order_number_refund() {
        global $wc_mock_orders;

        // Create parent order and add to global mock
        $parent_order = new WC_Order(100);
        $wc_mock_orders[100] = $parent_order;

        // Create refund order
        $mock_refund = new WC_Order_Refund(456);
        $mock_refund->set_parent_id(100);

        // Use reflection to access protected method
        $method = new ReflectionMethod(Invoice_List_Table::class, 'column_order_number');
        $method->setAccessible(true);

        $result = $method->invoke($this->list_table, $mock_refund);

        $this->assertStringContainsString('Refund', $result);
        $this->assertStringContainsString('<a href=', $result);
        $this->assertStringContainsString('#100', $result); // Parent order number
    }

    /**
     * Test column_customer with full name
     *
     * @return void
     */
    public function test_column_customer_with_name() {
        // Create mock order with billing info
        $mock_order = $this->createMock(WC_Order::class);
        $mock_order->method('get_billing_first_name')->willReturn('John');
        $mock_order->method('get_billing_last_name')->willReturn('Doe');
        $mock_order->method('get_billing_email')->willReturn('john@example.com');
        $mock_order->method('get_billing_company')->willReturn('');

        // Use reflection to access protected method
        $method = new ReflectionMethod(Invoice_List_Table::class, 'column_customer');
        $method->setAccessible(true);

        $result = $method->invoke($this->list_table, $mock_order);

        $this->assertStringContainsString('John Doe', $result);
        $this->assertStringContainsString('john@example.com', $result);
        $this->assertStringContainsString('mailto:', $result);
    }

    /**
     * Test column_customer with company only
     *
     * @return void
     */
    public function test_column_customer_with_company() {
        // Create mock order with company but no name
        $mock_order = $this->createMock(WC_Order::class);
        $mock_order->method('get_billing_first_name')->willReturn('');
        $mock_order->method('get_billing_last_name')->willReturn('');
        $mock_order->method('get_billing_company')->willReturn('Acme Corp');
        $mock_order->method('get_billing_email')->willReturn('contact@acme.com');

        // Use reflection to access protected method
        $method = new ReflectionMethod(Invoice_List_Table::class, 'column_customer');
        $method->setAccessible(true);

        $result = $method->invoke($this->list_table, $mock_order);

        $this->assertStringContainsString('Acme Corp', $result);
        $this->assertStringContainsString('contact@acme.com', $result);
    }

    /**
     * Test column_customer with guest (no info)
     *
     * @return void
     */
    public function test_column_customer_guest() {
        // Create mock order with no customer info
        $mock_order = $this->createMock(WC_Order::class);
        $mock_order->method('get_billing_first_name')->willReturn('');
        $mock_order->method('get_billing_last_name')->willReturn('');
        $mock_order->method('get_billing_company')->willReturn('');
        $mock_order->method('get_billing_email')->willReturn('');

        // Use reflection to access protected method
        $method = new ReflectionMethod(Invoice_List_Table::class, 'column_customer');
        $method->setAccessible(true);

        $result = $method->invoke($this->list_table, $mock_order);

        $this->assertStringContainsString('Guest', $result);
    }

    /**
     * Test column_invoice_number with invoice
     *
     * @return void
     */
    public function test_column_invoice_number_with_invoice() {
        // Configure mock settings to return web app base URL
        $this->mock_settings->method('get_web_app_base_url')
            ->willReturn('https://app.b2brouter.net');

        // Create real WC_Order instance with invoice ID set
        $mock_order = new WC_Order(123);
        $mock_order->update_meta_data('_b2brouter_invoice_id', 'inv_123abc');
        $mock_order->update_meta_data('_b2brouter_invoice_number', 'INV-2025-001');

        // Use reflection to access protected method
        $method = new ReflectionMethod(Invoice_List_Table::class, 'column_invoice_number');
        $method->setAccessible(true);

        $result = $method->invoke($this->list_table, $mock_order);

        $this->assertStringContainsString('inv_123abc', $result);
        $this->assertStringContainsString('app.b2brouter.net', $result);
        $this->assertStringContainsString('target="_blank"', $result);
        $this->assertStringContainsString('dashicons-external', $result);
    }

    /**
     * Test column_invoice_number without invoice
     *
     * @return void
     */
    public function test_column_invoice_number_without_invoice() {
        // Create real WC_Order instance without invoice ID
        $mock_order = new WC_Order(123);

        // Use reflection to access protected method
        $method = new ReflectionMethod(Invoice_List_Table::class, 'column_invoice_number');
        $method->setAccessible(true);

        $result = $method->invoke($this->list_table, $mock_order);

        $this->assertStringContainsString('—', $result);
        $this->assertStringNotContainsString('app.b2brouter.net', $result);
    }

    /**
     * Test column_status with known status
     *
     * @return void
     */
    public function test_column_status_with_status() {
        // Create mock order with status
        $mock_order = $this->createMock(WC_Order::class);
        $mock_order->method('get_meta')
            ->with('_b2brouter_invoice_status')
            ->willReturn('sent');

        // Use reflection to access protected method
        $method = new ReflectionMethod(Invoice_List_Table::class, 'column_status');
        $method->setAccessible(true);

        $result = $method->invoke($this->list_table, $mock_order);

        $this->assertStringContainsString('status-sent', $result);
        $this->assertStringContainsString('Sent', $result);
        $this->assertStringContainsString('b2brouter-status-badge', $result);
    }

    /**
     * Test column_status defaults to draft
     *
     * @return void
     */
    public function test_column_status_defaults_to_draft() {
        // Create mock order without status
        $mock_order = $this->createMock(WC_Order::class);
        $mock_order->method('get_meta')
            ->with('_b2brouter_invoice_status')
            ->willReturn('');

        // Use reflection to access protected method
        $method = new ReflectionMethod(Invoice_List_Table::class, 'column_status');
        $method->setAccessible(true);

        $result = $method->invoke($this->list_table, $mock_order);

        $this->assertStringContainsString('status-draft', $result);
        $this->assertStringContainsString('Draft', $result);
    }

    /**
     * Test column_actions contains buttons
     *
     * @return void
     */
    public function test_column_actions() {
        // Create mock order
        $mock_order = $this->createMock(WC_Order::class);
        $mock_order->method('get_id')->willReturn(123);

        // Use reflection to access protected method
        $method = new ReflectionMethod(Invoice_List_Table::class, 'column_actions');
        $method->setAccessible(true);

        $result = $method->invoke($this->list_table, $mock_order);

        $this->assertStringContainsString('b2brouter-list-view-pdf', $result);
        $this->assertStringContainsString('b2brouter-list-download-pdf', $result);
        $this->assertStringContainsString('data-order-id="123"', $result);
        $this->assertStringContainsString('View', $result);
        $this->assertStringContainsString('Download', $result);
        $this->assertStringContainsString('dashicons-pdf', $result);
        $this->assertStringContainsString('dashicons-download', $result);
    }

    /**
     * Test column_cb generates checkbox
     *
     * @return void
     */
    public function test_column_cb() {
        // Create mock order
        $mock_order = $this->createMock(WC_Order::class);
        $mock_order->method('get_id')->willReturn(123);

        // Use reflection to access protected method
        $method = new ReflectionMethod(Invoice_List_Table::class, 'column_cb');
        $method->setAccessible(true);

        $result = $method->invoke($this->list_table, $mock_order);

        $this->assertStringContainsString('<input type="checkbox"', $result);
        $this->assertStringContainsString('name="invoice[]"', $result);
        $this->assertStringContainsString('value="123"', $result);
    }

    /**
     * Test no_items message
     *
     * @return void
     */
    public function test_no_items() {
        // Capture output
        ob_start();
        $this->list_table->no_items();
        $result = ob_get_clean();

        $this->assertStringContainsString('No invoices found', $result);
    }
}
