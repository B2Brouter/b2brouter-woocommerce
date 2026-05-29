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
        $this->assertSame('9999', $invoice_data['contact']['tin_scheme']);

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

        // Assert legacy top-level amend fields are absent (API 2026-04-20)
        $this->assertArrayNotHasKey('amended_number', $invoice_data);
        $this->assertArrayNotHasKey('amended_date', $invoice_data);
        $this->assertArrayNotHasKey('amended_reason', $invoice_data);
        $this->assertArrayNotHasKey('amend_reason', $invoice_data);

        // Assert structured invoice_references[] entry
        $this->assertArrayHasKey('invoice_references', $invoice_data);
        $this->assertCount(1, $invoice_data['invoice_references']);
        $reference = $invoice_data['invoice_references'][0];
        $this->assertEquals('amend', $reference['reference_type']);
        $this->assertEquals('INV-ES-2025-00100', $reference['number']);
        $this->assertEquals('2025-11-20', $reference['date']);
        $this->assertEquals('Customer requested refund', $reference['reason']);

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

        // Assert legacy top-level amend fields are absent (API 2026-04-20)
        $this->assertArrayNotHasKey('amended_number', $invoice_data);
        $this->assertArrayNotHasKey('amended_date', $invoice_data);
        $this->assertArrayNotHasKey('amended_reason', $invoice_data);
        $this->assertArrayNotHasKey('amend_reason', $invoice_data);

        // Assert structured invoice_references[] entry
        $this->assertArrayHasKey('invoice_references', $invoice_data);
        $this->assertCount(1, $invoice_data['invoice_references']);
        $reference = $invoice_data['invoice_references'][0];
        $this->assertEquals('amend', $reference['reference_type']);
        $this->assertEquals('INV-US-2025-00200', $reference['number']);
        $this->assertArrayHasKey('date', $reference);
        $this->assertEquals('Defective product', $reference['reason']);

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

    /**
     * Build a single-line order mock for discount tests.
     *
     * The line carries a pre-discount subtotal and a (lower) post-discount
     * total, mirroring how WooCommerce exposes a coupon-discounted item.
     *
     * @param float $subtotal Pre-discount line net (e.g. list price * qty).
     * @param float $total    Post-discount line net (what the customer paid).
     * @param float $tax      Total tax charged on the line (post-discount base).
     * @param array $coupons  Coupon codes applied to the order.
     * @return \WC_Order Mock order.
     */
    private function make_single_line_order($subtotal, $total, $tax, array $coupons = array()) {
        $item = $this->createMock(\WC_Order_Item_Product::class);
        $item->method('get_name')->willReturn('Discounted Widget');
        $item->method('get_quantity')->willReturn(1);
        $item->method('get_subtotal')->willReturn($subtotal);
        $item->method('get_total')->willReturn($total);
        $item->method('get_taxes')->willReturn(array('total' => array(1 => $tax)));
        $item->method('get_product')->willReturn(null);

        $order = $this->createMock(\WC_Order::class);
        $order->method('get_type')->willReturn('shop_order');
        $order->method('get_billing_first_name')->willReturn('Pat');
        $order->method('get_billing_last_name')->willReturn('Buyer');
        $order->method('get_billing_email')->willReturn('pat@example.com');
        $order->method('get_billing_country')->willReturn('FR');
        $order->method('get_billing_address_1')->willReturn('1 rue de Test');
        $order->method('get_billing_address_2')->willReturn('');
        $order->method('get_billing_city')->willReturn('Paris');
        $order->method('get_billing_postcode')->willReturn('75001');
        $order->method('get_billing_company')->willReturn('');
        $order->method('get_currency')->willReturn('EUR');
        $order->method('get_id')->willReturn(900);
        $order->method('get_order_number')->willReturn('900');
        $order->method('get_items')->willReturn(array($item));
        $order->method('get_shipping_total')->willReturn(0);
        $order->method('get_meta')->willReturn(''); // no TIN
        // Pre-discount per-unit net price (qty 1).
        $order->method('get_item_subtotal')->willReturn($subtotal);
        $order->method('get_coupon_codes')->willReturn($coupons);

        return $order;
    }

    /**
     * Invoke prepare_invoice_data() and return the first invoice line.
     *
     * @param \WC_Order $order Order mock.
     * @return array First invoice line attributes.
     */
    private function prepare_first_line($order) {
        $reflection = new \ReflectionClass($this->invoice_generator);
        $method = $reflection->getMethod('prepare_invoice_data');
        $method->setAccessible(true);
        $invoice_data = $method->invoke($this->invoice_generator, $order);
        return $invoice_data['invoice_lines_attributes'][0];
    }

    /**
     * Read the single allowance off a prepared line, or null if none.
     *
     * @param array $line Invoice line attributes.
     * @return array|null
     */
    private function line_allowance($line) {
        if (empty($line['allowance_charges_attributes'][0])) {
            return null;
        }
        return $line['allowance_charges_attributes'][0];
    }

    /**
     * A coupon-discounted line must carry the discount as a line-level
     * AllowanceCharge (indicator=allowance, apply_taxes=true), with the line
     * price left at the pre-discount value. This is the representation verified
     * against B2Brouter staging to both render the discount and tax the net.
     *
     * @return void
     */
    public function test_discounted_line_carries_allowance_charge() {
        // List €100, customer paid €80 net (€20 coupon), 21% tax on the net.
        $order = $this->make_single_line_order(100.00, 80.00, 16.80, array('SAVE20'));

        $line = $this->prepare_first_line($order);

        $this->assertEquals(100.00, $line['price'], 'Line price stays at the pre-discount unit price');
        $this->assertEquals(1, $line['quantity']);

        $allowance = $this->line_allowance($line);
        $this->assertNotNull($allowance, 'Discounted line must carry an allowance charge');
        $this->assertSame('allowance', $allowance['allowance_charge_indicator']);
        $this->assertEquals(20.00, $allowance['amount'], 'Allowance amount is subtotal - total');
        $this->assertTrue($allowance['apply_taxes'], 'Allowance must reduce the taxable base');
        $this->assertNotEmpty($allowance['description']);
    }

    /**
     * The post-discount net the line resolves to (price*qty - allowance) must
     * equal what WooCommerce actually charged. Regression guard for the
     * over-reporting bug.
     *
     * @return void
     */
    public function test_discounted_line_net_matches_amount_charged() {
        $order = $this->make_single_line_order(100.00, 80.00, 16.80, array('SAVE20'));

        $line = $this->prepare_first_line($order);
        $allowance = $this->line_allowance($line);

        $net = ($line['price'] * $line['quantity']) - $allowance['amount'];
        $this->assertEquals(80.00, $net, 'Invoiced net must equal the amount the customer paid');
    }

    /**
     * An un-discounted line (subtotal == total) must NOT carry an allowance,
     * so we don't emit zero-amount allowances on every invoice.
     *
     * @return void
     */
    public function test_undiscounted_line_omits_allowance_charge() {
        $order = $this->make_single_line_order(100.00, 100.00, 21.00, array());

        $line = $this->prepare_first_line($order);

        $this->assertEquals(100.00, $line['price']);
        $this->assertArrayNotHasKey('allowance_charges_attributes', $line);
    }

    /**
     * The allowance description should name the applied coupon(s) so the
     * customer recognises it on the invoice; it falls back to a generic label.
     *
     * @return void
     */
    public function test_allowance_description_names_coupons_with_generic_fallback() {
        $with_coupon = $this->make_single_line_order(100.00, 80.00, 16.80, array('SAVE20', 'VIP'));
        $allowance = $this->line_allowance($this->prepare_first_line($with_coupon));
        $this->assertStringContainsString('SAVE20', $allowance['description']);
        $this->assertStringContainsString('VIP', $allowance['description']);

        $no_coupon = $this->make_single_line_order(100.00, 80.00, 16.80, array());
        $allowance2 = $this->line_allowance($this->prepare_first_line($no_coupon));
        $this->assertNotEmpty($allowance2['description']);
    }
}
