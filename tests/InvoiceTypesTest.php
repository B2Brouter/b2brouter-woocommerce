<?php
/**
 * Tests for Invoice Types and Refund Handling
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\Invoice_Generator;
use B2Brouter\WooCommerce\Customer_Fields;

/**
 * InvoiceTypesTest class
 *
 * Tests invoice type determination and refund invoice generation
 *
 * @since 1.0.0
 */
class InvoiceTypesTest extends TestCase {

    /**
     * Invoice Generator instance
     *
     * @var Invoice_Generator
     */
    private $invoice_generator;

    /**
     * Mock Settings
     *
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $mock_settings;

    /**
     * Set up test
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();

        // Clear global mock orders array
        global $wc_mock_orders;
        $wc_mock_orders = array();

        // Mock Settings
        $this->mock_settings = $this->createMock(\B2Brouter\WooCommerce\Settings::class);
        $this->mock_settings->method('get_api_key')->willReturn('test_api_key');
        $this->mock_settings->method('get_api_base_url')->willReturn('https://api.b2brouter.net');
        $this->mock_settings->method('get_account_id')->willReturn('test_account_id');

        $this->invoice_generator = new Invoice_Generator($this->mock_settings);
    }

    /**
     * Tear down test
     *
     * @return void
     */
    public function tearDown(): void {
        // Clear global mock orders array
        global $wc_mock_orders;
        $wc_mock_orders = array();

        parent::tearDown();
    }

    /**
     * Test IssuedSimplifiedInvoice type when order has no TIN
     *
     * @return void
     */
    public function test_issued_simplified_invoice_without_tin() {
        // Create mock order without TIN
        $mock_order = $this->createMock(\WC_Order::class);
        $mock_order->method('get_type')->willReturn('shop_order');
        $mock_order->method('get_billing_first_name')->willReturn('John');
        $mock_order->method('get_billing_last_name')->willReturn('Doe');
        $mock_order->method('get_billing_email')->willReturn('john@example.com');
        $mock_order->method('get_billing_country')->willReturn('US');
        $mock_order->method('get_billing_address_1')->willReturn('123 Main St');
        $mock_order->method('get_billing_city')->willReturn('New York');
        $mock_order->method('get_billing_postcode')->willReturn('10001');
        $mock_order->method('get_billing_address_2')->willReturn('');
        $mock_order->method('get_billing_company')->willReturn('');
        $mock_order->method('get_currency')->willReturn('USD');
        $mock_order->method('get_id')->willReturn(123);
        $mock_order->method('get_order_number')->willReturn('123');
        $mock_order->method('get_items')->willReturn([]);
        $mock_order->method('get_shipping_total')->willReturn(0);
        $mock_order->method('get_meta')->willReturn(''); // No TIN

        // Use reflection to call private method prepare_invoice_data
        $reflection = new \ReflectionClass($this->invoice_generator);
        $method = $reflection->getMethod('prepare_invoice_data');
        $method->setAccessible(true);

        $invoice_data = $method->invoke($this->invoice_generator, $mock_order);

        // Assert invoice type is IssuedSimplifiedInvoice
        $this->assertEquals('IssuedSimplifiedInvoice', $invoice_data['type']);

        // Assert contact does not have TIN
        $this->assertArrayNotHasKey('tin_value', $invoice_data['contact']);

        // Assert contact_email_override is set
        $this->assertArrayHasKey('contact_email_override', $invoice_data);
        $this->assertEquals('john@example.com', $invoice_data['contact_email_override']);
    }

    /**
     * Test IssuedInvoice type when order has TIN
     *
     * @return void
     */
    public function test_issued_invoice_with_tin() {
        // Create mock order with TIN
        $mock_order = $this->createMock(\WC_Order::class);
        $mock_order->method('get_type')->willReturn('shop_order');
        $mock_order->method('get_billing_first_name')->willReturn('Jane');
        $mock_order->method('get_billing_last_name')->willReturn('Smith');
        $mock_order->method('get_billing_email')->willReturn('jane@company.com');
        $mock_order->method('get_billing_country')->willReturn('ES');
        $mock_order->method('get_billing_address_1')->willReturn('Calle Mayor 1');
        $mock_order->method('get_billing_city')->willReturn('Madrid');
        $mock_order->method('get_billing_postcode')->willReturn('28001');
        $mock_order->method('get_billing_address_2')->willReturn('');
        $mock_order->method('get_billing_company')->willReturn('ACME Corp');
        $mock_order->method('get_currency')->willReturn('EUR');
        $mock_order->method('get_id')->willReturn(456);
        $mock_order->method('get_order_number')->willReturn('456');
        $mock_order->method('get_items')->willReturn([]);
        $mock_order->method('get_shipping_total')->willReturn(0);
        $mock_order->method('get_meta')->willReturnCallback(function($key) {
            if ($key === '_billing_tin') {
                return 'ESB12345678';
            }
            return '';
        });

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->invoice_generator);
        $method = $reflection->getMethod('prepare_invoice_data');
        $method->setAccessible(true);

        $invoice_data = $method->invoke($this->invoice_generator, $mock_order);

        // Assert invoice type is IssuedInvoice
        $this->assertEquals('IssuedInvoice', $invoice_data['type']);

        // Assert contact has TIN
        $this->assertArrayHasKey('tin_value', $invoice_data['contact']);
        $this->assertEquals('ESB12345678', $invoice_data['contact']['tin_value']);
        $this->assertEquals(9999, $invoice_data['contact']['tin_scheme']);

        // Assert contact_email_override is set
        $this->assertArrayHasKey('contact_email_override', $invoice_data);
        $this->assertEquals('jane@company.com', $invoice_data['contact_email_override']);
    }

    /**
     * Test rectificative invoice for Spain (negative amounts)
     *
     * @return void
     */
    public function test_rectificative_invoice_spain_negative_amounts() {
        // Create real WC_Order instance for parent (using our mock class)
        $mock_parent = new \WC_Order(100);
        $mock_parent->set_billing_first_name('Carlos');
        $mock_parent->set_billing_last_name('García');
        $mock_parent->add_meta_data('_b2brouter_invoice_id', 'inv_123', true);
        $mock_parent->add_meta_data('_b2brouter_invoice_number', 'INV-ES-2025-00100', true);
        $mock_parent->add_meta_data('_b2brouter_invoice_date', '2025-11-20 10:00:00', true);
        $mock_parent->add_meta_data('_billing_tin', '', true);

        // Set country to Spain (ES) - need to use reflection since there's no setter
        $reflection_parent = new \ReflectionClass($mock_parent);
        $data_property = $reflection_parent->getProperty('data');
        $data_property->setAccessible(true);
        $data = $data_property->getValue($mock_parent);
        $data['billing_country'] = 'ES';
        $data_property->setValue($mock_parent, $data);

        // Create real WC_Order_Refund instance (using our mock class)
        $mock_refund = new \WC_Order_Refund(101);
        $mock_refund->set_parent_id(100);
        $mock_refund->set_reason('Customer requested refund');
        $mock_refund->set_shipping_total(-5.00);

        // Register parent in global mock orders
        global $wc_mock_orders;
        $wc_mock_orders[100] = $mock_parent;

        // Mock line item with negative values
        $mock_item = $this->createMock(\WC_Order_Item_Product::class);
        $mock_item->method('get_name')->willReturn('Test Product');
        $mock_item->method('get_quantity')->willReturn(-1);
        $mock_item->method('get_total')->willReturn(-10.00);
        $mock_item->method('get_taxes')->willReturn([
            'total' => [1 => -2.10]
        ]);

        // Set items using setter method
        $mock_refund->set_items([$mock_item]);

        // Use reflection to call private methods
        $reflection = new \ReflectionClass($this->invoice_generator);

        // Test get_parent_invoice_info
        $parent_info_method = $reflection->getMethod('get_parent_invoice_info');
        $parent_info_method->setAccessible(true);
        $parent_info = $parent_info_method->invoke($this->invoice_generator, $mock_refund);

        $this->assertNotNull($parent_info, 'Parent invoice info should not be null');
        $this->assertEquals('inv_123', $parent_info['invoice_id']);
        $this->assertEquals('INV-ES-2025-00100', $parent_info['invoice_number']);

        // Test prepare_invoice_data for rectificative
        $prepare_method = $reflection->getMethod('prepare_invoice_data');
        $prepare_method->setAccessible(true);
        $invoice_data = $prepare_method->invoke($this->invoice_generator, $mock_refund);

        // Assert amended fields are set
        $this->assertArrayHasKey('amended_number', $invoice_data);
        $this->assertEquals('INV-ES-2025-00100', $invoice_data['amended_number']);
        $this->assertArrayHasKey('amended_date', $invoice_data);
        $this->assertEquals('2025-11-20', $invoice_data['amended_date']);
        $this->assertArrayHasKey('amended_reason', $invoice_data);
        $this->assertEquals('Customer requested refund', $invoice_data['amended_reason']);

        // Assert is_credit_note is NOT set (Spain uses rectificative)
        $this->assertArrayNotHasKey('is_credit_note', $invoice_data);

        // Assert amounts are negative (rectificative)
        $this->assertEquals(-1, $invoice_data['invoice_lines_attributes'][0]['quantity']);
        $this->assertEquals(10.00, $invoice_data['invoice_lines_attributes'][0]['price']); // Price stays positive
        $this->assertEquals(-5.00, $invoice_data['invoice_lines_attributes'][1]['price']); // Shipping negative

        // Assert type is IssuedSimplifiedInvoice (no TIN)
        $this->assertEquals('IssuedSimplifiedInvoice', $invoice_data['type']);
    }

    /**
     * Test credit note for non-Spain country (positive amounts)
     *
     * @return void
     */
    public function test_credit_note_positive_amounts() {
        // Create real WC_Order instance for parent (using our mock class)
        $mock_parent = new \WC_Order(200);
        $mock_parent->set_billing_first_name('Bob');
        $mock_parent->set_billing_last_name('Johnson');
        $mock_parent->add_meta_data('_b2brouter_invoice_id', 'inv_456', true);
        $mock_parent->add_meta_data('_b2brouter_invoice_number', 'INV-US-2025-00200', true);
        $mock_parent->add_meta_data('_b2brouter_invoice_date', '2025-11-21 15:00:00', true);
        $mock_parent->add_meta_data('_billing_tin', 'US123456789', true);

        // Country is already US by default, no need to set it

        // Create real WC_Order_Refund instance (using our mock class)
        $mock_refund = new \WC_Order_Refund(201);
        $mock_refund->set_parent_id(200);
        $mock_refund->set_reason('Defective product');
        $mock_refund->set_shipping_total(-10.00);

        // Register parent in global mock orders
        global $wc_mock_orders;
        $wc_mock_orders[200] = $mock_parent;

        // Mock line item with negative values
        $mock_item = $this->createMock(\WC_Order_Item_Product::class);
        $mock_item->method('get_name')->willReturn('Widget');
        $mock_item->method('get_quantity')->willReturn(-2);
        $mock_item->method('get_total')->willReturn(-40.00);
        $mock_item->method('get_taxes')->willReturn([
            'total' => [1 => -4.00]
        ]);

        // Set items using setter method
        $mock_refund->set_items([$mock_item]);

        // Use reflection to test
        $reflection = new \ReflectionClass($this->invoice_generator);
        $prepare_method = $reflection->getMethod('prepare_invoice_data');
        $prepare_method->setAccessible(true);
        $invoice_data = $prepare_method->invoke($this->invoice_generator, $mock_refund);

        // Assert amended fields are set
        $this->assertArrayHasKey('amended_number', $invoice_data);
        $this->assertEquals('INV-US-2025-00200', $invoice_data['amended_number']);
        $this->assertArrayHasKey('amended_date', $invoice_data);
        $this->assertArrayHasKey('amended_reason', $invoice_data);

        // Assert is_credit_note IS set (non-Spain)
        $this->assertArrayHasKey('is_credit_note', $invoice_data);
        $this->assertTrue($invoice_data['is_credit_note']);

        // Assert amounts are positive (credit note)
        $this->assertEquals(2, $invoice_data['invoice_lines_attributes'][0]['quantity']);
        $this->assertEquals(20.00, $invoice_data['invoice_lines_attributes'][0]['price']);
        $this->assertEquals(10.00, $invoice_data['invoice_lines_attributes'][1]['price']); // Shipping positive

        // Assert type is IssuedInvoice (has TIN from parent)
        $this->assertEquals('IssuedInvoice', $invoice_data['type']);

        // Assert TIN inherited from parent
        $this->assertArrayHasKey('tin_value', $invoice_data['contact']);
        $this->assertEquals('US123456789', $invoice_data['contact']['tin_value']);
    }

    /**
     * Test rectificative countries constant
     *
     * @return void
     */
    public function test_rectificative_countries_constant() {
        $this->assertContains('ES', Invoice_Generator::RECTIFICATIVE_COUNTRIES);
    }

    /**
     * Test uses_rectificative_invoices method
     *
     * @return void
     */
    public function test_uses_rectificative_invoices_method() {
        $reflection = new \ReflectionClass($this->invoice_generator);
        $method = $reflection->getMethod('uses_rectificative_invoices');
        $method->setAccessible(true);

        // Spain should use rectificative
        $this->assertTrue($method->invoke($this->invoice_generator, 'ES'));
        $this->assertTrue($method->invoke($this->invoice_generator, 'es')); // Case insensitive

        // Other countries should NOT use rectificative
        $this->assertFalse($method->invoke($this->invoice_generator, 'US'));
        $this->assertFalse($method->invoke($this->invoice_generator, 'FR'));
        $this->assertFalse($method->invoke($this->invoice_generator, 'DE'));
    }

    /**
     * Test is_refund method
     *
     * @return void
     */
    public function test_is_refund_method() {
        $reflection = new \ReflectionClass($this->invoice_generator);
        $method = $reflection->getMethod('is_refund');
        $method->setAccessible(true);

        // Test with regular order
        $mock_order = $this->createMock(\WC_Order::class);
        $mock_order->method('get_type')->willReturn('shop_order');
        $this->assertFalse($method->invoke($this->invoice_generator, $mock_order));

        // Test with refund
        $mock_refund = $this->createMock(\WC_Order_Refund::class);
        $mock_refund->method('get_type')->willReturn('shop_order_refund');
        $this->assertTrue($method->invoke($this->invoice_generator, $mock_refund));
    }

    /**
     * Test get_invoice_type method
     *
     * @return void
     */
    public function test_get_invoice_type_method() {
        $reflection = new \ReflectionClass($this->invoice_generator);
        $method = $reflection->getMethod('get_invoice_type');
        $method->setAccessible(true);

        // Test order without TIN
        $mock_order_no_tin = $this->createMock(\WC_Order::class);
        $mock_order_no_tin->method('get_type')->willReturn('shop_order');
        $mock_order_no_tin->method('get_meta')->willReturn('');
        $this->assertEquals('IssuedSimplifiedInvoice', $method->invoke($this->invoice_generator, $mock_order_no_tin));

        // Test order with TIN
        $mock_order_with_tin = $this->createMock(\WC_Order::class);
        $mock_order_with_tin->method('get_type')->willReturn('shop_order');
        $mock_order_with_tin->method('get_meta')->willReturn('ES12345678');
        $this->assertEquals('IssuedInvoice', $method->invoke($this->invoice_generator, $mock_order_with_tin));
    }
}
