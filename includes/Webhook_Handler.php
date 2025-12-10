<?php
/**
 * Webhook Handler
 *
 * Handles incoming webhooks from B2Brouter API for real-time invoice status updates
 *
 * @package B2Brouter\WooCommerce
 * @since 1.0.0
 */

namespace B2Brouter\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhook_Handler class
 *
 * Receives and processes webhook events from B2Brouter
 *
 * @since 1.0.0
 */
class Webhook_Handler {

    /**
     * Settings instance
     *
     * @since 1.0.0
     * @var Settings
     */
    private $settings;

    /**
     * Status Sync instance
     *
     * @since 1.0.0
     * @var Status_Sync
     */
    private $status_sync;

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param Settings $settings Settings instance
     * @param Status_Sync $status_sync Status Sync instance
     */
    public function __construct(Settings $settings, Status_Sync $status_sync) {
        $this->settings = $settings;
        $this->status_sync = $status_sync;

        // Register REST API endpoint
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
    }

    /**
     * Register webhook REST API endpoint
     *
     * @since 1.0.0
     * @return void
     */
    public function register_webhook_endpoint() {
        register_rest_route('b2brouter/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook_request'),
            'permission_callback' => '__return_true', // Signature verification handles auth
        ));
    }

    /**
     * Handle incoming webhook request
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request REST request object
     * @return \WP_REST_Response REST response
     */
    public function handle_webhook_request($request) {
        // Check if webhooks are enabled
        if (!$this->settings->get_webhook_enabled()) {
            return new \WP_REST_Response(array(
                'error' => 'Webhooks are disabled'
            ), 403);
        }

        // Get raw body for signature verification
        $raw_body = $request->get_body();

        // Verify webhook signature
        if (!$this->verify_webhook_signature($request, $raw_body)) {
            error_log('B2Brouter webhook signature verification failed');
            return new \WP_REST_Response(array(
                'error' => 'Invalid signature'
            ), 401);
        }

        // Parse JSON payload
        $payload = json_decode($raw_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('B2Brouter webhook invalid JSON payload');
            return new \WP_REST_Response(array(
                'error' => 'Invalid JSON payload'
            ), 400);
        }

        // Validate payload structure
        if (!isset($payload['code']) || !isset($payload['data'])) {
            error_log('B2Brouter webhook missing required fields');
            return new \WP_REST_Response(array(
                'error' => 'Missing required fields'
            ), 400);
        }

        // Process based on event type
        $result = $this->process_webhook_event($payload);

        if ($result['success']) {
            return new \WP_REST_Response(array(
                'success' => true,
                'message' => $result['message']
            ), 200);
        } else {
            return new \WP_REST_Response(array(
                'error' => $result['message']
            ), $result['status_code']);
        }
    }

    /**
     * Verify webhook signature using HMAC-SHA256
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request REST request
     * @param string $raw_body Raw request body
     * @return bool True if signature is valid
     */
    private function verify_webhook_signature($request, $raw_body) {
        $webhook_secret = $this->settings->get_webhook_secret();

        if (empty($webhook_secret)) {
            error_log('B2Brouter webhook secret not configured');
            return false;
        }

        // Get signature header
        $signature_header = $request->get_header('X-B2Brouter-Signature');

        if (empty($signature_header)) {
            error_log('B2Brouter webhook missing X-B2Brouter-Signature header');
            return false;
        }

        // Parse signature header: t={timestamp},s={signature}
        $parts = array();
        foreach (explode(',', $signature_header) as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                $parts[$kv[0]] = $kv[1];
            }
        }

        if (!isset($parts['t']) || !isset($parts['s'])) {
            error_log('B2Brouter webhook invalid signature header format');
            return false;
        }

        $timestamp = intval($parts['t']);
        $signature = trim($parts['s']);

        // Verify timestamp is within 5 minutes (prevent replay attacks)
        if (abs(time() - $timestamp) > 300) {
            error_log('B2Brouter webhook timestamp outside valid window');
            return false;
        }

        // Reconstruct signed payload: {timestamp}.{raw_body}
        $signed_payload = $timestamp . '.' . $raw_body;

        // Calculate expected signature
        $expected_signature = hash_hmac('sha256', $signed_payload, $webhook_secret);

        // Timing-safe comparison
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Process webhook event based on type
     *
     * @since 1.0.0
     * @param array $payload Webhook payload
     * @return array Result with success, message, and optional status_code
     */
    private function process_webhook_event($payload) {
        $event_code = $payload['code'];

        switch ($event_code) {
            case 'issued_invoice.state_change':
                return $this->process_invoice_status_change($payload['data']);

            default:
                error_log(sprintf('B2Brouter webhook unsupported event type: %s', $event_code));
                return array(
                    'success' => false,
                    'message' => 'Unsupported event type',
                    'status_code' => 400
                );
        }
    }

    /**
     * Process invoice status change webhook
     *
     * @since 1.0.0
     * @param array $data Webhook event data
     * @return array Result with success and message
     */
    private function process_invoice_status_change($data) {
        // Validate required fields
        if (!isset($data['invoice_id']) || !isset($data['state'])) {
            return array(
                'success' => false,
                'message' => 'Missing invoice_id or state',
                'status_code' => 400
            );
        }

        $invoice_id = intval($data['invoice_id']);
        $new_status = strtolower(sanitize_text_field($data['state']));
        $notes = isset($data['notes']) ? sanitize_text_field($data['notes']) : null;

        // Find order by invoice ID
        $order_id = $this->find_order_by_invoice_id($invoice_id);

        if (!$order_id) {
            error_log(sprintf(
                'B2Brouter webhook received for unknown invoice ID: %d',
                $invoice_id
            ));
            return array(
                'success' => false,
                'message' => 'Invoice not found',
                'status_code' => 404
            );
        }

        // Get order
        $order = wc_get_order($order_id);

        if (!$order) {
            return array(
                'success' => false,
                'message' => 'Order not found',
                'status_code' => 404
            );
        }

        // Get old status for logging
        $old_status = $order->get_meta('_b2brouter_invoice_status');

        // Update order status
        $order->update_meta_data('_b2brouter_invoice_status', $new_status);
        $order->update_meta_data('_b2brouter_invoice_status_updated', time());

        // Mark webhook receipt for fallback polling
        $order->update_meta_data('_b2brouter_last_webhook_received', time());

        // Handle error status
        if ($new_status === 'error' && !empty($notes)) {
            $order->update_meta_data('_b2brouter_invoice_status_error', $notes);
        } else {
            $order->delete_meta_data('_b2brouter_invoice_status_error');
        }

        $order->save();

        // Add order note
        $order->add_order_note(sprintf(
            __('Status updated via webhook: %s → %s', 'b2brouter-woocommerce'),
            $old_status ?: 'pending',
            $new_status
        ));

        // Fire action hook for extensibility
        do_action('b2brouter_invoice_status_updated', $order_id, $new_status, $old_status);

        return array(
            'success' => true,
            'message' => sprintf('Status updated to: %s', $new_status)
        );
    }

    /**
     * Find order by B2Brouter invoice ID
     *
     * @since 1.0.0
     * @param int $invoice_id B2Brouter invoice ID
     * @return int|false Order ID or false if not found
     */
    private function find_order_by_invoice_id($invoice_id) {
        // Query orders with matching invoice ID (HPOS-compatible)
        $orders = wc_get_orders(array(
            'limit' => 1,
            'meta_key' => '_b2brouter_invoice_id',
            'meta_value' => $invoice_id,
            'return' => 'ids',
            'type' => array('shop_order', 'shop_order_refund'),
        ));

        return !empty($orders) ? $orders[0] : false;
    }
}
