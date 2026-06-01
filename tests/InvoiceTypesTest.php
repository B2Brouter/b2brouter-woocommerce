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
     * @param array $fees     WC_Order_Item_Fee mocks to attach to the order.
     * @return \WC_Order Mock order.
     */
    private function make_single_line_order($subtotal, $total, $tax, array $coupons = array(), array $fees = array()) {
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
        $order->method('get_fees')->willReturn($fees);

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
     * Generating a credit note for a refund that already has one must fail
     * gracefully — never fatal. The "already generated" guard throws before the
     * parent is resolved, and the error note must NOT be written to the refund
     * (WC_Order_Refund has no add_order_note()); it belongs on the parent order.
     *
     * @return void
     */
    public function test_generate_invoice_on_already_invoiced_refund_fails_gracefully() {
        global $wc_mock_orders;

        $parent = new \WC_Order(300);
        $parent->add_meta_data('_b2brouter_invoice_id', 'inv_parent', true);
        $parent->add_meta_data('_b2brouter_invoice_number', 'INV-300', true);
        $wc_mock_orders[300] = $parent;

        $refund = new \WC_Order_Refund(301);
        $refund->set_parent_id(300);
        $refund->add_meta_data('_b2brouter_invoice_id', 'cn_existing', true);
        $wc_mock_orders[301] = $refund;

        // Must not raise a fatal Error on the refund; returns a graceful result.
        $result = $this->invoice_generator->generate_invoice(301);

        $this->assertFalse($result['success']);
        $this->assertStringContainsStringIgnoringCase('already', $result['message']);
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

    /**
     * Invoke prepare_invoice_data() and return all invoice lines.
     *
     * @param \WC_Order|\WC_Order_Refund $order Order mock.
     * @return array Invoice line attributes.
     */
    private function prepare_lines($order) {
        return $this->prepare_invoice($order)['invoice_lines_attributes'];
    }

    /**
     * Invoke prepare_invoice_data() and return the whole invoice data array.
     *
     * @param \WC_Order|\WC_Order_Refund $order Order mock.
     * @return array Invoice data.
     */
    private function prepare_invoice($order) {
        $reflection = new \ReflectionClass($this->invoice_generator);
        $method = $reflection->getMethod('prepare_invoice_data');
        $method->setAccessible(true);
        return $method->invoke($this->invoice_generator, $order);
    }

    /**
     * Find the first document-level allowance/charge matching a description.
     *
     * @param array  $invoice     Prepared invoice data.
     * @param string $description Description to match.
     * @return array|null
     */
    private function find_allowance_charge($invoice, $description) {
        foreach ($invoice['allowance_charges_attributes'] ?? array() as $ac) {
            if (isset($ac['description']) && $ac['description'] === $description) {
                return $ac;
            }
        }
        return null;
    }

    /**
     * A positive order fee (surcharge) must surface as a document-level
     * `charge` allowance/charge — not a line — with a positive magnitude and a
     * tax bucket derived from the fee's own tax total. This is the
     * standards-aligned representation the B2Brouter API expects for
     * order-level adjustments (verified against staging).
     *
     * @return void
     */
    public function test_positive_fee_emitted_as_document_charge() {
        $fee = new \WC_Order_Item_Fee('Gift wrapping', 15.00, 3.15); // 21% on 15
        $order = $this->make_single_line_order(100.00, 100.00, 21.00, array(), array($fee));

        $ac = $this->find_allowance_charge($this->prepare_invoice($order), 'Gift wrapping');

        $this->assertNotNull($ac, 'A surcharge fee must surface as an allowance/charge');
        $this->assertSame('charge', $ac['allowance_charge_indicator']);
        $this->assertEquals(15.00, $ac['amount'], 'Amount is the positive magnitude');
        $this->assertTrue($ac['apply_taxes']);
        $this->assertSame('S', $ac['tax_attributes']['category']);
        $this->assertEquals(21.0, $ac['tax_attributes']['percent']);
    }

    /**
     * A negative order fee — the "order-level discount as a fee" case coupons
     * don't cover — must surface as a document-level `allowance` (positive
     * magnitude, indicator carries the sign) so it reduces the taxable base
     * instead of being dropped. The rate is recovered from the signed ratio.
     *
     * @return void
     */
    public function test_negative_fee_emitted_as_document_allowance() {
        $fee = new \WC_Order_Item_Fee('Loyalty discount', -30.00, -6.30); // 21% on -30
        $order = $this->make_single_line_order(100.00, 100.00, 21.00, array(), array($fee));

        $ac = $this->find_allowance_charge($this->prepare_invoice($order), 'Loyalty discount');

        $this->assertNotNull($ac);
        $this->assertSame('allowance', $ac['allowance_charge_indicator']);
        $this->assertEquals(30.00, $ac['amount'], 'Discount magnitude is positive; indicator carries the sign');
        $this->assertSame('S', $ac['tax_attributes']['category']);
        $this->assertEquals(21.0, $ac['tax_attributes']['percent'], 'Rate recovered from signed ratio');
    }

    /**
     * An untaxed fee is bucketed as tax-exempt (category E, 0%), mirroring the
     * shipping treatment, rather than defaulting to a standard rate.
     *
     * @return void
     */
    public function test_untaxed_fee_marked_exempt() {
        $fee = new \WC_Order_Item_Fee('Handling', 10.00, 0.0);
        $order = $this->make_single_line_order(100.00, 100.00, 21.00, array(), array($fee));

        $ac = $this->find_allowance_charge($this->prepare_invoice($order), 'Handling');

        $this->assertNotNull($ac);
        $this->assertSame('charge', $ac['allowance_charge_indicator']);
        $this->assertSame('E', $ac['tax_attributes']['category']);
        $this->assertEquals(0.0, $ac['tax_attributes']['percent']);
    }

    /**
     * The net the invoice resolves to (lines − allowances + charges) must equal
     * what the order actually charged. Regression guard for fee-driven
     * mis-reporting, mirroring the backend's taxable-base formula.
     *
     * @return void
     */
    public function test_fee_allowance_reconciles_to_order_total() {
        $fee = new \WC_Order_Item_Fee('Order discount', -20.00, 0.0);
        $order = $this->make_single_line_order(100.00, 100.00, 21.00, array(), array($fee));
        $invoice = $this->prepare_invoice($order);

        $net = 0.0;
        foreach ($invoice['invoice_lines_attributes'] as $line) {
            $net += $line['price'] * $line['quantity'];
        }
        foreach ($invoice['allowance_charges_attributes'] ?? array() as $ac) {
            $net += ($ac['allowance_charge_indicator'] === 'allowance' ? -1 : 1) * $ac['amount'];
        }

        $this->assertEquals(80.00, $net, 'Item 100 minus a 20 fee allowance must net to 80');
    }

    /**
     * Build a discounted refund and its invoiced parent, registered so
     * wc_get_order() resolves the parent. The refund carries its own line with
     * a pre-discount subtotal and a (lower) post-discount total, stored
     * negative as WooCommerce does for refunds.
     *
     * @param string $parent_country Billing country (drives credit-note vs.
     *                               rectificative treatment).
     * @return \WC_Order_Refund
     */
    private function make_discounted_refund($parent_country) {
        global $wc_mock_orders;

        $parent = new \WC_Order(700);
        $parent->set_billing_country($parent_country);
        $parent->set_coupon_codes(array('SAVE20'));
        $parent->add_meta_data('_b2brouter_invoice_id', 'inv_parent', true);
        $parent->add_meta_data('_b2brouter_invoice_number', 'INV-700', true);
        $wc_mock_orders[700] = $parent;

        // Refund line: list €100, €80 charged (€20 discount), stored negative.
        $item = new \WC_Order_Item_Product('Discounted Widget');
        $item->set_quantity(-1);
        $item->set_subtotal(-100.00);
        $item->set_total(-80.00);
        $item->set_taxes(array('total' => array(1 => -16.80)));

        $refund = new \WC_Order_Refund(701);
        $refund->set_parent_id(700);
        $refund->set_items(array($item));
        $wc_mock_orders[701] = $refund;

        return $refund;
    }

    /**
     * A discounted refund issued as a CREDIT NOTE (non-rectificative country)
     * restates amounts as positive: the allowance is positive and the line
     * nets to the positive amount being credited. Exercises the sign branch
     * the original discount PR left untested.
     *
     * @return void
     */
    public function test_discounted_refund_credit_note_carries_positive_allowance() {
        $refund = $this->make_discounted_refund('US'); // US => credit note

        $line = $this->prepare_lines($refund)[0];
        $allowance = $this->line_allowance($line);

        $this->assertNotNull($allowance, 'Discounted refund line must carry an allowance');
        $this->assertEquals(100.00, $line['price'], 'Credit note restates the pre-discount price as positive');
        $this->assertEquals(1, $line['quantity']);
        $this->assertEquals(20.00, $allowance['amount'], 'Allowance is positive in a credit note');
        $this->assertStringContainsString('SAVE20', $allowance['description']);

        $net = ($line['price'] * $line['quantity']) - $allowance['amount'];
        $this->assertEquals(80.00, $net, 'Credited net equals the discounted amount charged');
    }

    /**
     * The same discounted refund issued as a RECTIFICATIVE invoice (Spain)
     * uses negative amounts: the allowance flips negative so the line nets to
     * the negative correction. Covers the other sign branch.
     *
     * @return void
     */
    public function test_discounted_refund_rectificative_carries_negative_allowance() {
        $refund = $this->make_discounted_refund('ES'); // ES => rectificative

        $line = $this->prepare_lines($refund)[0];
        $allowance = $this->line_allowance($line);

        $this->assertNotNull($allowance);
        $this->assertEquals(100.00, $line['price']);
        $this->assertEquals(-1, $line['quantity'], 'Rectificative keeps the negative quantity');
        $this->assertEquals(-20.00, $allowance['amount'], 'Allowance is negative in a rectificative invoice');

        $net = ($line['price'] * $line['quantity']) - $allowance['amount'];
        $this->assertEquals(-80.00, $net, 'Rectificative net is the negative of the amount charged');
    }

    /**
     * A refund line must keep the original standard tax rate (S, 21%), not be
     * mis-categorised as exempt. A refund line's total/tax are negative, and a
     * `> 0` guard in the rate calc would have returned 0% — leaving the line
     * VAT unreversed and the credit note / rectificative total wrong.
     *
     * @return void
     */
    public function test_refund_line_keeps_standard_tax_rate() {
        $refund = $this->make_discounted_refund('ES');

        $tax = $this->prepare_lines($refund)[0]['taxes_attributes'][0];

        $this->assertSame('S', $tax['category'], 'Refund line stays standard-rated, not exempt');
        $this->assertEquals(21.0, $tax['percent'], 'Rate resolves from the negative total/tax');
    }
}
