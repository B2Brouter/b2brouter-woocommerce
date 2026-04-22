<?php
/**
 * Tests for Customer class
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\Customer;
use B2Brouter\WooCommerce\Settings;
use B2Brouter\WooCommerce\Invoice_Generator;

/**
 * Customer test case
 *
 * Covers the only public-facing attack surface in the plugin: the
 * wp_ajax_nopriv PDF download endpoint, customer-triggered invoice
 * generation, and access control for logged-in and guest users.
 *
 * @since 1.0.0
 */
class CustomerTest extends TestCase {

    /**
     * @var Customer
     */
    private $customer;

    /**
     * @var Settings
     */
    private $mock_settings;

    /**
     * @var Invoice_Generator
     */
    private $mock_invoice_generator;

    public function setUp(): void {
        parent::setUp();

        global $wp_actions, $wp_filters, $wc_mock_orders, $wp_current_user_id,
               $wp_is_account_page, $wp_is_order_received_page, $wp_wc_endpoint_url,
               $wp_enqueued_styles, $wp_enqueued_scripts, $wp_localized_scripts;
        $wp_actions = array();
        $wp_filters = array();
        $wc_mock_orders = array();
        $wp_current_user_id = 1;
        $wp_is_account_page = false;
        $wp_is_order_received_page = false;
        $wp_wc_endpoint_url = null;
        $wp_enqueued_styles = array();
        $wp_enqueued_scripts = array();
        $wp_localized_scripts = array();

        // Clean superglobals between tests
        $_POST = array();
        $_GET = array();

        $this->mock_settings = $this->createMock(Settings::class);
        $this->mock_invoice_generator = $this->createMock(Invoice_Generator::class);

        $this->customer = new Customer($this->mock_settings, $this->mock_invoice_generator);
    }

    public function tearDown(): void {
        parent::tearDown();
        $_POST = array();
        $_GET = array();
    }

    /**
     * Helper: invoke an AJAX handler and capture the JSON response as an array.
     * Converts wp_send_json_*'s exit into a catchable exception.
     */
    private function callAjaxHandler(callable $callback): array {
        global $wp_send_json_throw;
        $wp_send_json_throw = true;
        try {
            $callback();
        } catch (\WpJsonResponseException $e) {
            $wp_send_json_throw = false;
            return json_decode($e->response, true);
        }
        $wp_send_json_throw = false;
        $this->fail('AJAX handler did not call wp_send_json');
    }

    // ========== Instantiation & hook registration ==========

    public function test_can_be_instantiated() {
        $this->assertInstanceOf(Customer::class, $this->customer);
    }

    public function test_registers_wordpress_hooks() {
        global $wp_actions, $wp_filters;

        $this->assertArrayHasKey('wp_ajax_b2brouter_customer_download_pdf', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_nopriv_b2brouter_customer_download_pdf', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_b2brouter_customer_generate_invoice', $wp_actions);
        $this->assertArrayHasKey('woocommerce_thankyou', $wp_actions);
        $this->assertArrayHasKey('wp_enqueue_scripts', $wp_actions);
        $this->assertArrayHasKey('woocommerce_my_account_my_orders_actions', $wp_filters);
    }

    // ========== can_customer_access_order (via ajax_customer_download_pdf) ==========
    //
    // The method is private, so we drive it through the public AJAX handler.
    // Returning "no invoice found" means access was granted (and the method
    // reached the invoice-id check); "no permission" means access was denied.

    public function test_access_granted_to_logged_in_owner() {
        global $wc_mock_orders, $wp_current_user_id;

        $order = new WC_Order(42);
        $order->set_customer_id(7);
        // No invoice meta — so if access passes, we get "No invoice found for this order"
        $wc_mock_orders[42] = $order;
        $wp_current_user_id = 7;



        $_POST['order_id'] = 42;

        $response = $this->callAjaxHandler(array($this->customer, 'ajax_customer_download_pdf'));

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('No invoice found', $response['data']['message']);
    }

    public function test_access_denied_to_non_owner_logged_in_user() {
        global $wc_mock_orders, $wp_current_user_id;

        $order = new WC_Order(42);
        $order->set_customer_id(7);
        $order->set_order_key('wc_order_realkey');
        $order->update_meta_data('_b2brouter_invoice_id', 'inv_123');
        $wc_mock_orders[42] = $order;
        $wp_current_user_id = 999; // different user

        $_POST['order_id'] = 42;
        // No order_key in POST — guest fallback must fail too

        $response = $this->callAjaxHandler(array($this->customer, 'ajax_customer_download_pdf'));

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('permission', $response['data']['message']);
    }

    public function test_guest_access_granted_with_matching_order_key() {
        global $wc_mock_orders, $wp_current_user_id;

        $order = new WC_Order(42);
        $order->set_customer_id(0);
        $order->set_order_key('wc_order_abc123');
        $wc_mock_orders[42] = $order;
        $wp_current_user_id = 0; // guest

        $_POST['order_id'] = 42;
        $_POST['order_key'] = 'wc_order_abc123';

        $response = $this->callAjaxHandler(array($this->customer, 'ajax_customer_download_pdf'));

        // Access granted → flow reaches invoice-id check, which fails since we didn't set one
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('No invoice found', $response['data']['message']);
    }

    public function test_guest_access_denied_with_wrong_order_key() {
        global $wc_mock_orders, $wp_current_user_id;

        $order = new WC_Order(42);
        $order->set_customer_id(0);
        $order->set_order_key('wc_order_abc123');
        $order->update_meta_data('_b2brouter_invoice_id', 'inv_123');
        $wc_mock_orders[42] = $order;
        $wp_current_user_id = 0;

        $_POST['order_id'] = 42;
        $_POST['order_key'] = 'attacker_guess';

        $response = $this->callAjaxHandler(array($this->customer, 'ajax_customer_download_pdf'));

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('permission', $response['data']['message']);
    }

    public function test_guest_access_denied_without_order_key() {
        global $wc_mock_orders, $wp_current_user_id;

        $order = new WC_Order(42);
        $order->set_customer_id(0);
        $order->set_order_key('wc_order_abc123');
        $order->update_meta_data('_b2brouter_invoice_id', 'inv_123');
        $wc_mock_orders[42] = $order;
        $wp_current_user_id = 0;

        $_POST['order_id'] = 42;
        // no order_key at all

        $response = $this->callAjaxHandler(array($this->customer, 'ajax_customer_download_pdf'));

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('permission', $response['data']['message']);
    }

    public function test_refund_access_delegates_to_parent_order_owner() {
        global $wc_mock_orders, $wp_current_user_id;

        $parent = new WC_Order(42);
        $parent->set_customer_id(7);

        $refund = new WC_Order_Refund(43);
        $refund->set_parent_id(42);
        $refund->update_meta_data('_b2brouter_invoice_id', 'inv_credit_1');

        $wc_mock_orders[42] = $parent;
        $wc_mock_orders[43] = $refund;
        $wp_current_user_id = 7;

        $_POST['order_id'] = 43;

        $this->mock_invoice_generator->expects($this->once())
            ->method('stream_invoice_pdf')
            ->with(43, true);

        // stream_invoice_pdf is mocked — it returns null instead of exiting,
        // so the handler returns normally (no JSON sent). That's a valid
        // success path for this test.
        $this->customer->ajax_customer_download_pdf();
    }

    public function test_refund_access_denied_when_parent_missing() {
        global $wc_mock_orders, $wp_current_user_id;

        $refund = new WC_Order_Refund(43);
        $refund->set_parent_id(0); // orphan refund
        $refund->update_meta_data('_b2brouter_invoice_id', 'inv_credit_1');

        $wc_mock_orders[43] = $refund;
        $wp_current_user_id = 7;

        $_POST['order_id'] = 43;

        $response = $this->callAjaxHandler(array($this->customer, 'ajax_customer_download_pdf'));

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('permission', $response['data']['message']);
    }

    // ========== ajax_customer_download_pdf ==========

    public function test_download_pdf_rejects_missing_order_id() {
        $_POST = array();

        $response = $this->callAjaxHandler(array($this->customer, 'ajax_customer_download_pdf'));

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Invalid order ID', $response['data']['message']);
    }

    public function test_download_pdf_rejects_nonexistent_order() {
        $_POST['order_id'] = 99999; // not in $wc_mock_orders

        $response = $this->callAjaxHandler(array($this->customer, 'ajax_customer_download_pdf'));

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Order not found', $response['data']['message']);
    }

    public function test_download_pdf_rejects_when_no_invoice_on_order() {
        global $wc_mock_orders, $wp_current_user_id;

        $order = new WC_Order(42);
        $order->set_customer_id(7);
        // No _b2brouter_invoice_id meta
        $wc_mock_orders[42] = $order;
        $wp_current_user_id = 7;

        $_POST['order_id'] = 42;

        $response = $this->callAjaxHandler(array($this->customer, 'ajax_customer_download_pdf'));

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('No invoice found', $response['data']['message']);
    }

    public function test_download_pdf_streams_invoice_on_success() {
        global $wc_mock_orders, $wp_current_user_id;

        $order = new WC_Order(42);
        $order->set_customer_id(7);
        $order->update_meta_data('_b2brouter_invoice_id', 'inv_123');
        $wc_mock_orders[42] = $order;
        $wp_current_user_id = 7;

        $_POST['order_id'] = 42;

        $this->mock_invoice_generator->expects($this->once())
            ->method('stream_invoice_pdf')
            ->with(42, true);

        $this->customer->ajax_customer_download_pdf();
    }

    // ========== ajax_customer_generate_invoice ==========

    public function test_generate_invoice_rejects_when_not_manual_mode() {
        global $wc_mock_orders, $wp_current_user_id;

        $order = new WC_Order(42);
        $order->set_customer_id(7);
        $order->set_status('completed');
        $wc_mock_orders[42] = $order;
        $wp_current_user_id = 7;

        $this->mock_settings->method('get_invoice_mode')->willReturn('automatic');

        $_POST['order_id'] = 42;

        $response = $this->callAjaxHandler(array($this->customer, 'ajax_customer_generate_invoice'));

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('not available in automatic mode', $response['data']['message']);
    }

    public function test_generate_invoice_rejects_when_order_status_not_completed_or_processing() {
        global $wc_mock_orders, $wp_current_user_id;

        $order = new WC_Order(42);
        $order->set_customer_id(7);
        $order->set_status('pending'); // not completed/processing
        $wc_mock_orders[42] = $order;
        $wp_current_user_id = 7;

        $this->mock_settings->method('get_invoice_mode')->willReturn('manual');

        $_POST['order_id'] = 42;

        $response = $this->callAjaxHandler(array($this->customer, 'ajax_customer_generate_invoice'));

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('completed or processing', $response['data']['message']);
    }

    public function test_generate_invoice_rejects_duplicate_when_invoice_already_exists() {
        global $wc_mock_orders, $wp_current_user_id;

        $order = new WC_Order(42);
        $order->set_customer_id(7);
        $order->set_status('completed');
        $order->update_meta_data('_b2brouter_invoice_id', 'inv_existing');
        $wc_mock_orders[42] = $order;
        $wp_current_user_id = 7;

        $this->mock_settings->method('get_invoice_mode')->willReturn('manual');

        $_POST['order_id'] = 42;

        $this->mock_invoice_generator->expects($this->never())->method('generate_invoice');

        $response = $this->callAjaxHandler(array($this->customer, 'ajax_customer_generate_invoice'));

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('already exists', $response['data']['message']);
    }

    public function test_generate_invoice_delegates_to_invoice_generator_on_success() {
        global $wc_mock_orders, $wp_current_user_id;

        $order = new WC_Order(42);
        $order->set_customer_id(7);
        $order->set_status('processing');
        $wc_mock_orders[42] = $order;
        $wp_current_user_id = 7;

        $this->mock_settings->method('get_invoice_mode')->willReturn('manual');

        $_POST['order_id'] = 42;

        $this->mock_invoice_generator->expects($this->once())
            ->method('generate_invoice')
            ->with(42)
            ->willReturnCallback(function ($order_id) use ($order) {
                // Simulate the generator writing the invoice id back to the order
                $order->update_meta_data('_b2brouter_invoice_id', 'inv_new_1');
                return array('success' => true, 'message' => 'Invoice created');
            });

        $response = $this->callAjaxHandler(array($this->customer, 'ajax_customer_generate_invoice'));

        $this->assertTrue($response['success']);
        $this->assertEquals('Invoice created', $response['data']['message']);
        $this->assertEquals('inv_new_1', $response['data']['invoice_id']);
    }

    public function test_generate_invoice_reports_generator_failure_message() {
        global $wc_mock_orders, $wp_current_user_id;

        $order = new WC_Order(42);
        $order->set_customer_id(7);
        $order->set_status('completed');
        $wc_mock_orders[42] = $order;
        $wp_current_user_id = 7;

        $this->mock_settings->method('get_invoice_mode')->willReturn('manual');

        $_POST['order_id'] = 42;

        $this->mock_invoice_generator->method('generate_invoice')
            ->willReturn(array('success' => false, 'message' => 'API returned 500'));

        $response = $this->callAjaxHandler(array($this->customer, 'ajax_customer_generate_invoice'));

        $this->assertFalse($response['success']);
        $this->assertEquals('API returned 500', $response['data']['message']);
    }

    // ========== add_pdf_download_to_my_account ==========

    public function test_my_account_action_uses_new_fragment_url_format() {
        $order = new WC_Order(42);
        $order->set_order_key('wc_order_abc123');
        $order->update_meta_data('_b2brouter_invoice_id', 'inv_123');

        $actions = $this->customer->add_pdf_download_to_my_account(array(), $order);

        $this->assertArrayHasKey('b2brouter_download_invoice', $actions);
        $this->assertEquals(
            '#b2brouter-invoice-42-wc_order_abc123',
            $actions['b2brouter_download_invoice']['url']
        );
    }

    public function test_my_account_action_shows_generate_button_in_manual_mode_with_no_invoice() {
        $this->mock_settings->method('get_invoice_mode')->willReturn('manual');

        $order = new WC_Order(42);
        $order->set_status('completed');
        // No invoice meta

        $actions = $this->customer->add_pdf_download_to_my_account(array(), $order);

        $this->assertArrayHasKey('b2brouter_generate_invoice', $actions);
        $this->assertArrayNotHasKey('b2brouter_download_invoice', $actions);
    }

    public function test_my_account_action_hides_generate_button_in_automatic_mode() {
        $this->mock_settings->method('get_invoice_mode')->willReturn('automatic');

        $order = new WC_Order(42);
        $order->set_status('completed');

        $actions = $this->customer->add_pdf_download_to_my_account(array(), $order);

        $this->assertArrayNotHasKey('b2brouter_generate_invoice', $actions);
        $this->assertArrayNotHasKey('b2brouter_download_invoice', $actions);
    }

    public function test_my_account_action_hides_generate_button_for_pending_orders() {
        $this->mock_settings->method('get_invoice_mode')->willReturn('manual');

        $order = new WC_Order(42);
        $order->set_status('pending'); // not completed/processing

        $actions = $this->customer->add_pdf_download_to_my_account(array(), $order);

        $this->assertArrayNotHasKey('b2brouter_generate_invoice', $actions);
    }

    public function test_my_account_action_adds_credit_note_downloads_for_refunds_with_invoices() {
        $refund_a = new WC_Order_Refund(43);
        $refund_a->update_meta_data('_b2brouter_invoice_id', 'credit_1');

        $refund_b = new WC_Order_Refund(44);
        // Refund without invoice meta — should be skipped

        $order = $this->getMockBuilder(WC_Order::class)
            ->setConstructorArgs(array(42))
            ->onlyMethods(array('get_refunds'))
            ->getMock();
        $order->update_meta_data('_b2brouter_invoice_id', 'inv_123');
        $order->set_order_key('wc_order_abc123');
        $order->method('get_refunds')->willReturn(array($refund_a, $refund_b));

        $actions = $this->customer->add_pdf_download_to_my_account(array(), $order);

        $this->assertArrayHasKey('b2brouter_download_credit_note_43', $actions);
        $this->assertArrayNotHasKey('b2brouter_download_credit_note_44', $actions);
        $this->assertEquals('#refund-43', $actions['b2brouter_download_credit_note_43']['url']);
    }

    // ========== add_pdf_to_thankyou_page ==========

    public function test_thankyou_renders_nothing_without_order_id() {
        ob_start();
        $this->customer->add_pdf_to_thankyou_page(0);
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    public function test_thankyou_renders_nothing_when_order_has_no_invoice() {
        global $wc_mock_orders;

        $order = new WC_Order(42);
        $order->set_order_key('wc_order_abc123');
        $wc_mock_orders[42] = $order;

        ob_start();
        $this->customer->add_pdf_to_thankyou_page(42);
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    public function test_thankyou_renders_invoice_section_with_order_key_in_button() {
        global $wc_mock_orders;

        $order = new WC_Order(42);
        $order->set_order_key('wc_order_abc123');
        $order->update_meta_data('_b2brouter_invoice_id', 'inv_123');
        $order->update_meta_data('_b2brouter_invoice_number', '0001');
        $order->update_meta_data('_b2brouter_invoice_series_code', 'INV');
        $wc_mock_orders[42] = $order;

        ob_start();
        $this->customer->add_pdf_to_thankyou_page(42);
        $output = ob_get_clean();

        $this->assertStringContainsString('b2brouter-invoice-section', $output);
        $this->assertStringContainsString('data-order-id="42"', $output);
        $this->assertStringContainsString('data-order-key="wc_order_abc123"', $output);
        // Invoice number ("INV-0001" or similar formatted) should appear
        $this->assertStringContainsString('0001', $output);
    }

    // ========== enqueue_scripts ==========

    public function test_enqueue_scripts_skipped_on_unrelated_pages() {
        global $wp_enqueued_scripts, $wp_enqueued_styles;

        // All page-state globals are false (set in setUp)
        $this->customer->enqueue_scripts();

        $this->assertArrayNotHasKey('b2brouter-customer', $wp_enqueued_scripts);
        $this->assertArrayNotHasKey('b2brouter-customer', $wp_enqueued_styles);
    }

    public function test_enqueue_scripts_loads_assets_on_account_page() {
        global $wp_is_account_page, $wp_enqueued_scripts, $wp_enqueued_styles, $wp_localized_scripts;
        $wp_is_account_page = true;

        $this->customer->enqueue_scripts();

        $this->assertArrayHasKey('b2brouter-customer', $wp_enqueued_scripts);
        $this->assertArrayHasKey('b2brouter-customer', $wp_enqueued_styles);
        $this->assertArrayHasKey('b2brouter-customer', $wp_localized_scripts);
        $this->assertArrayHasKey('ajax_url', $wp_localized_scripts['b2brouter-customer']['data']);
        $this->assertArrayHasKey('nonce', $wp_localized_scripts['b2brouter-customer']['data']);
    }

    public function test_enqueue_scripts_loads_assets_on_order_received_page() {
        global $wp_is_order_received_page, $wp_enqueued_scripts;
        $wp_is_order_received_page = true;

        $this->customer->enqueue_scripts();

        $this->assertArrayHasKey('b2brouter-customer', $wp_enqueued_scripts);
    }

    public function test_enqueue_scripts_loads_assets_on_view_order_endpoint() {
        global $wp_wc_endpoint_url, $wp_enqueued_scripts;
        $wp_wc_endpoint_url = 'view-order';

        $this->customer->enqueue_scripts();

        $this->assertArrayHasKey('b2brouter-customer', $wp_enqueued_scripts);
    }
}
