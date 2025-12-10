<?php
/**
 * Comprehensive tests for Webhook_Handler class
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\Webhook_Handler;
use B2Brouter\WooCommerce\Settings;
use B2Brouter\WooCommerce\Status_Sync;
use B2Brouter\WooCommerce\Invoice_Generator;

/**
 * Webhook_Handler test case
 *
 * Tests webhook signature verification, event processing, and error handling
 *
 * @since 1.0.0
 */
class WebhookHandlerTest extends TestCase {

    /**
     * Webhook Handler instance
     *
     * @var Webhook_Handler
     */
    private $webhook_handler;

    /**
     * Settings instance
     *
     * @var Settings
     */
    private $settings;

    /**
     * Status Sync instance
     *
     * @var Status_Sync
     */
    private $status_sync;

    /**
     * Invoice Generator instance
     *
     * @var Invoice_Generator
     */
    private $invoice_generator;

    /**
     * Test webhook secret
     *
     * @var string
     */
    private $webhook_secret = 'test_webhook_secret_12345678901234567890';

    /**
     * Set up test
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();

        // Reset globals
        global $wp_options, $wp_rest_routes, $wc_mock_orders, $wp_actions;
        $wp_options = array();
        $wp_rest_routes = array();
        $wc_mock_orders = array();
        $wp_actions = array();

        // Create Settings instance
        $this->settings = new Settings();
        $this->settings->set_webhook_enabled(true);
        $this->settings->set_webhook_secret($this->webhook_secret);

        // Create Invoice_Generator instance (required by Status_Sync)
        $this->invoice_generator = new Invoice_Generator($this->settings);

        // Create Status_Sync instance
        $this->status_sync = new Status_Sync($this->settings, $this->invoice_generator);

        // Create Webhook_Handler instance
        $this->webhook_handler = new Webhook_Handler($this->settings, $this->status_sync);
    }

    /**
     * Tear down test
     *
     * @return void
     */
    public function tearDown(): void {
        parent::tearDown();

        // Clean up globals
        global $wp_options, $wp_rest_routes, $wc_mock_orders, $wp_actions;
        $wp_options = array();
        $wp_rest_routes = array();
        $wc_mock_orders = array();
        $wp_actions = array();
    }

    // ========== Instantiation Tests ==========

    /**
     * Test that Webhook_Handler can be instantiated
     *
     * @return void
     */
    public function test_can_be_instantiated() {
        $this->assertInstanceOf(Webhook_Handler::class, $this->webhook_handler);
    }

    /**
     * Test that REST endpoint is registered
     *
     * @return void
     */
    public function test_rest_endpoint_registered() {
        global $wp_rest_routes;

        // Trigger rest_api_init action
        $this->webhook_handler->register_webhook_endpoint();

        $this->assertArrayHasKey('/b2brouter/v1/webhook', $wp_rest_routes);
        $this->assertEquals('POST', $wp_rest_routes['/b2brouter/v1/webhook']['methods']);
    }

    // ========== Signature Verification Tests ==========

    /**
     * Test webhook with valid signature
     *
     * @return void
     */
    public function test_webhook_with_valid_signature() {
        $timestamp = time();
        $payload = array(
            'code' => 'issued_invoice.state_change',
            'data' => array(
                'invoice_id' => 123,
                'state' => 'sent'
            )
        );
        $body = json_encode($payload);

        // Create valid signature
        $signed_payload = $timestamp . '.' . $body;
        $signature = hash_hmac('sha256', $signed_payload, $this->webhook_secret);

        // Create request
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);
        $request->set_header('X-B2Brouter-Signature', 't=' . $timestamp . ',s=' . $signature);

        // Create mock order with invoice
        $order = new WC_Order(123);
        $order->update_meta_data('_b2brouter_invoice_id', 123);
        $order->update_meta_data('_b2brouter_invoice_status', 'pending');
        global $wc_mock_orders;
        $wc_mock_orders[123] = $order;

        // Mock wc_get_orders to return our order
        $GLOBALS['test_wc_get_orders_return'] = array(123);

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    /**
     * Test webhook with invalid signature
     *
     * @return void
     */
    public function test_webhook_with_invalid_signature() {
        $timestamp = time();
        $payload = array(
            'code' => 'issued_invoice.state_change',
            'data' => array(
                'invoice_id' => 123,
                'state' => 'sent'
            )
        );
        $body = json_encode($payload);

        // Create invalid signature
        $signature = 'invalid_signature_123456789';

        // Create request
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);
        $request->set_header('X-B2Brouter-Signature', 't=' . $timestamp . ',s=' . $signature);

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(401, $response->get_status());
        $this->assertEquals('Invalid signature', $response->get_data()['error']);
    }

    /**
     * Test webhook with expired timestamp (replay attack prevention)
     *
     * @return void
     */
    public function test_webhook_with_expired_timestamp() {
        $timestamp = time() - 600; // 10 minutes ago (outside 5-minute window)
        $payload = array(
            'code' => 'issued_invoice.state_change',
            'data' => array(
                'invoice_id' => 123,
                'state' => 'sent'
            )
        );
        $body = json_encode($payload);

        // Create signature with expired timestamp
        $signed_payload = $timestamp . '.' . $body;
        $signature = hash_hmac('sha256', $signed_payload, $this->webhook_secret);

        // Create request
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);
        $request->set_header('X-B2Brouter-Signature', 't=' . $timestamp . ',s=' . $signature);

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(401, $response->get_status());
        $this->assertEquals('Invalid signature', $response->get_data()['error']);
    }

    /**
     * Test webhook with missing signature header
     *
     * @return void
     */
    public function test_webhook_with_missing_signature_header() {
        $payload = array(
            'code' => 'issued_invoice.state_change',
            'data' => array(
                'invoice_id' => 123,
                'state' => 'sent'
            )
        );
        $body = json_encode($payload);

        // Create request without signature header
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(401, $response->get_status());
        $this->assertEquals('Invalid signature', $response->get_data()['error']);
    }

    /**
     * Test webhook with malformed signature header
     *
     * @return void
     */
    public function test_webhook_with_malformed_signature_header() {
        $payload = array(
            'code' => 'issued_invoice.state_change',
            'data' => array(
                'invoice_id' => 123,
                'state' => 'sent'
            )
        );
        $body = json_encode($payload);

        // Create request with malformed signature header
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);
        $request->set_header('X-B2Brouter-Signature', 'malformed_header');

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(401, $response->get_status());
        $this->assertEquals('Invalid signature', $response->get_data()['error']);
    }

    /**
     * Test webhook when webhooks are disabled
     *
     * @return void
     */
    public function test_webhook_when_disabled() {
        $this->settings->set_webhook_enabled(false);

        $timestamp = time();
        $payload = array(
            'code' => 'issued_invoice.state_change',
            'data' => array(
                'invoice_id' => 123,
                'state' => 'sent'
            )
        );
        $body = json_encode($payload);

        // Create request
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(403, $response->get_status());
        $this->assertEquals('Webhooks are disabled', $response->get_data()['error']);
    }

    /**
     * Test webhook when webhook secret is not configured
     *
     * @return void
     */
    public function test_webhook_without_secret() {
        $this->settings->set_webhook_secret('');

        $timestamp = time();
        $payload = array(
            'code' => 'issued_invoice.state_change',
            'data' => array(
                'invoice_id' => 123,
                'state' => 'sent'
            )
        );
        $body = json_encode($payload);

        // Create request
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);
        $request->set_header('X-B2Brouter-Signature', 't=' . $timestamp . ',s=test');

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(401, $response->get_status());
        $this->assertEquals('Invalid signature', $response->get_data()['error']);
    }

    // ========== Payload Validation Tests ==========

    /**
     * Test webhook with invalid JSON payload
     *
     * @return void
     */
    public function test_webhook_with_invalid_json() {
        $timestamp = time();
        $body = 'invalid json {{{';

        // Create valid signature for invalid body
        $signed_payload = $timestamp . '.' . $body;
        $signature = hash_hmac('sha256', $signed_payload, $this->webhook_secret);

        // Create request
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);
        $request->set_header('X-B2Brouter-Signature', 't=' . $timestamp . ',s=' . $signature);

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertEquals('Invalid JSON payload', $response->get_data()['error']);
    }

    /**
     * Test webhook with missing required fields
     *
     * @return void
     */
    public function test_webhook_with_missing_required_fields() {
        $timestamp = time();
        $payload = array(
            // Missing 'code' field
            'data' => array(
                'invoice_id' => 123,
                'state' => 'sent'
            )
        );
        $body = json_encode($payload);

        // Create valid signature
        $signed_payload = $timestamp . '.' . $body;
        $signature = hash_hmac('sha256', $signed_payload, $this->webhook_secret);

        // Create request
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);
        $request->set_header('X-B2Brouter-Signature', 't=' . $timestamp . ',s=' . $signature);

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertEquals('Missing required fields', $response->get_data()['error']);
    }

    /**
     * Test webhook with unsupported event type
     *
     * @return void
     */
    public function test_webhook_with_unsupported_event_type() {
        $timestamp = time();
        $payload = array(
            'code' => 'unsupported.event',
            'data' => array(
                'test' => 'data'
            )
        );
        $body = json_encode($payload);

        // Create valid signature
        $signed_payload = $timestamp . '.' . $body;
        $signature = hash_hmac('sha256', $signed_payload, $this->webhook_secret);

        // Create request
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);
        $request->set_header('X-B2Brouter-Signature', 't=' . $timestamp . ',s=' . $signature);

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertEquals('Unsupported event type', $response->get_data()['error']);
    }

    // ========== Invoice Status Change Event Tests ==========

    /**
     * Test invoice status change event with valid invoice
     *
     * @return void
     */
    public function test_invoice_status_change_success() {
        $timestamp = time();
        $payload = array(
            'code' => 'issued_invoice.state_change',
            'data' => array(
                'invoice_id' => 456,
                'state' => 'sent'
            )
        );
        $body = json_encode($payload);

        // Create valid signature
        $signed_payload = $timestamp . '.' . $body;
        $signature = hash_hmac('sha256', $signed_payload, $this->webhook_secret);

        // Create mock order with invoice
        $order = new WC_Order(789);
        $order->update_meta_data('_b2brouter_invoice_id', 456);
        $order->update_meta_data('_b2brouter_invoice_status', 'pending');
        global $wc_mock_orders;
        $wc_mock_orders[789] = $order;

        // Mock wc_get_orders to return our order
        $GLOBALS['test_wc_get_orders_return'] = array(789);

        // Create request
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);
        $request->set_header('X-B2Brouter-Signature', 't=' . $timestamp . ',s=' . $signature);

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        $this->assertEquals('Status updated to: sent', $response->get_data()['message']);

        // Verify order meta was updated
        $this->assertEquals('sent', $order->get_meta('_b2brouter_invoice_status'));
        $this->assertNotEmpty($order->get_meta('_b2brouter_invoice_status_updated'));
        $this->assertNotEmpty($order->get_meta('_b2brouter_last_webhook_received'));
    }

    /**
     * Test invoice status change with error status and notes
     *
     * @return void
     */
    public function test_invoice_status_change_with_error() {
        $timestamp = time();
        $payload = array(
            'code' => 'issued_invoice.state_change',
            'data' => array(
                'invoice_id' => 456,
                'state' => 'error',
                'notes' => 'Invalid recipient VAT number'
            )
        );
        $body = json_encode($payload);

        // Create valid signature
        $signed_payload = $timestamp . '.' . $body;
        $signature = hash_hmac('sha256', $signed_payload, $this->webhook_secret);

        // Create mock order with invoice
        $order = new WC_Order(789);
        $order->update_meta_data('_b2brouter_invoice_id', 456);
        $order->update_meta_data('_b2brouter_invoice_status', 'pending');
        global $wc_mock_orders;
        $wc_mock_orders[789] = $order;

        // Mock wc_get_orders to return our order
        $GLOBALS['test_wc_get_orders_return'] = array(789);

        // Create request
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);
        $request->set_header('X-B2Brouter-Signature', 't=' . $timestamp . ',s=' . $signature);

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);

        // Verify error meta was stored
        $this->assertEquals('error', $order->get_meta('_b2brouter_invoice_status'));
        $this->assertEquals('Invalid recipient VAT number', $order->get_meta('_b2brouter_invoice_status_error'));
    }

    /**
     * Test invoice status change for unknown invoice
     *
     * @return void
     */
    public function test_invoice_status_change_unknown_invoice() {
        $timestamp = time();
        $payload = array(
            'code' => 'issued_invoice.state_change',
            'data' => array(
                'invoice_id' => 999999, // Non-existent invoice
                'state' => 'sent'
            )
        );
        $body = json_encode($payload);

        // Create valid signature
        $signed_payload = $timestamp . '.' . $body;
        $signature = hash_hmac('sha256', $signed_payload, $this->webhook_secret);

        // Mock wc_get_orders to return empty array (no orders found)
        $GLOBALS['test_wc_get_orders_return'] = array();

        // Create request
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);
        $request->set_header('X-B2Brouter-Signature', 't=' . $timestamp . ',s=' . $signature);

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(404, $response->get_status());
        $this->assertEquals('Invoice not found', $response->get_data()['error']);
    }

    /**
     * Test invoice status change with missing invoice_id
     *
     * @return void
     */
    public function test_invoice_status_change_missing_invoice_id() {
        $timestamp = time();
        $payload = array(
            'code' => 'issued_invoice.state_change',
            'data' => array(
                // Missing 'invoice_id'
                'state' => 'sent'
            )
        );
        $body = json_encode($payload);

        // Create valid signature
        $signed_payload = $timestamp . '.' . $body;
        $signature = hash_hmac('sha256', $signed_payload, $this->webhook_secret);

        // Create request
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);
        $request->set_header('X-B2Brouter-Signature', 't=' . $timestamp . ',s=' . $signature);

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertEquals('Missing invoice_id or state', $response->get_data()['error']);
    }

    /**
     * Test invoice status change with missing state
     *
     * @return void
     */
    public function test_invoice_status_change_missing_state() {
        $timestamp = time();
        $payload = array(
            'code' => 'issued_invoice.state_change',
            'data' => array(
                'invoice_id' => 123
                // Missing 'state'
            )
        );
        $body = json_encode($payload);

        // Create valid signature
        $signed_payload = $timestamp . '.' . $body;
        $signature = hash_hmac('sha256', $signed_payload, $this->webhook_secret);

        // Create request
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);
        $request->set_header('X-B2Brouter-Signature', 't=' . $timestamp . ',s=' . $signature);

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(400, $response->get_status());
        $this->assertEquals('Missing invoice_id or state', $response->get_data()['error']);
    }

    // ========== Edge Cases ==========

    /**
     * Test webhook with timestamp at boundary of 5-minute window
     *
     * @return void
     */
    public function test_webhook_with_timestamp_at_boundary() {
        $timestamp = time() - 299; // 4 minutes 59 seconds ago (within 5-minute window)
        $payload = array(
            'code' => 'issued_invoice.state_change',
            'data' => array(
                'invoice_id' => 123,
                'state' => 'sent'
            )
        );
        $body = json_encode($payload);

        // Create valid signature
        $signed_payload = $timestamp . '.' . $body;
        $signature = hash_hmac('sha256', $signed_payload, $this->webhook_secret);

        // Create mock order with invoice
        $order = new WC_Order(123);
        $order->update_meta_data('_b2brouter_invoice_id', 123);
        $order->update_meta_data('_b2brouter_invoice_status', 'pending');
        global $wc_mock_orders;
        $wc_mock_orders[123] = $order;

        // Mock wc_get_orders to return our order
        $GLOBALS['test_wc_get_orders_return'] = array(123);

        // Create request
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);
        $request->set_header('X-B2Brouter-Signature', 't=' . $timestamp . ',s=' . $signature);

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        // Should succeed - within 5-minute window
        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    /**
     * Test webhook clears error meta when status changes from error to success
     *
     * @return void
     */
    public function test_webhook_clears_error_meta_on_success() {
        $timestamp = time();
        $payload = array(
            'code' => 'issued_invoice.state_change',
            'data' => array(
                'invoice_id' => 456,
                'state' => 'sent'
            )
        );
        $body = json_encode($payload);

        // Create valid signature
        $signed_payload = $timestamp . '.' . $body;
        $signature = hash_hmac('sha256', $signed_payload, $this->webhook_secret);

        // Create mock order with invoice and existing error
        $order = new WC_Order(789);
        $order->update_meta_data('_b2brouter_invoice_id', 456);
        $order->update_meta_data('_b2brouter_invoice_status', 'error');
        $order->update_meta_data('_b2brouter_invoice_status_error', 'Previous error message');
        global $wc_mock_orders;
        $wc_mock_orders[789] = $order;

        // Mock wc_get_orders to return our order
        $GLOBALS['test_wc_get_orders_return'] = array(789);

        // Create request
        $request = new WP_REST_Request('POST', '/b2brouter/v1/webhook');
        $request->set_body($body);
        $request->set_header('X-B2Brouter-Signature', 't=' . $timestamp . ',s=' . $signature);

        // Handle request
        $response = $this->webhook_handler->handle_webhook_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);

        // Verify error meta was cleared
        $this->assertEquals('sent', $order->get_meta('_b2brouter_invoice_status'));
        $this->assertEmpty($order->get_meta('_b2brouter_invoice_status_error'));
    }
}
