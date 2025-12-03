<?php
/**
 * Invoice Status Synchronization
 *
 * @package B2Brouter\WooCommerce
 * @since 1.0.0
 */

namespace B2Brouter\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Status Sync class
 *
 * Handles synchronization of invoice status from B2Brouter API
 *
 * @since 1.0.0
 */
class Status_Sync {

    /**
     * Final states that don't need periodic checking
     *
     * @since 1.0.0
     * @var array
     */
    const FINAL_STATES = array('sent', 'accepted', 'registered', 'paid', 'cancelled', 'closed');

    /**
     * Settings instance
     *
     * @since 1.0.0
     * @var Settings
     */
    private $settings;

    /**
     * Invoice Generator instance
     *
     * @since 1.0.0
     * @var Invoice_Generator
     */
    private $invoice_generator;

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param Settings $settings Settings instance
     * @param Invoice_Generator $invoice_generator Invoice Generator instance
     */
    public function __construct($settings, $invoice_generator) {
        $this->settings = $settings;
        $this->invoice_generator = $invoice_generator;

        // Register cron hooks
        add_action('b2brouter_sync_invoice_status', array($this, 'sync_batch'));
        add_filter('cron_schedules', array($this, 'add_custom_schedules'));
    }

    /**
     * Activate status sync (called on plugin activation)
     *
     * @since 1.0.0
     * @return void
     */
    public function activate() {
        // Schedule cron if not already scheduled
        if (!wp_next_scheduled('b2brouter_sync_invoice_status')) {
            // Randomize the minute within the next hour to distribute API load
            $random_minutes = wp_rand(0, 59);
            $first_run = strtotime('+' . $random_minutes . ' minutes', time());
            wp_schedule_event($first_run, 'hourly', 'b2brouter_sync_invoice_status');
        }
    }

    /**
     * Deactivate status sync (called on plugin deactivation)
     *
     * @since 1.0.0
     * @return void
     */
    public function deactivate() {
        // Clear scheduled cron
        $timestamp = wp_next_scheduled('b2brouter_sync_invoice_status');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'b2brouter_sync_invoice_status');
        }
    }

    /**
     * Add custom cron schedules
     *
     * @since 1.0.0
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_custom_schedules($schedules) {
        // Add custom schedules if needed in the future
        return $schedules;
    }

    /**
     * Sync a batch of invoices
     *
     * @since 1.0.0
     * @return array Results array with counts
     */
    public function sync_batch() {
        $start_time = time();
        $timeout = 50; // 50 seconds max
        $batch_size = 50; // Process up to 50 invoices per run

        $synced = 0;
        $errors = 0;

        try {
            // Get orders with invoices that need status checking
            $order_ids = $this->get_orders_needing_sync($batch_size);

            if (empty($order_ids)) {
                return array('synced' => 0, 'errors' => 0);
            }

            foreach ($order_ids as $order_id) {
                // Check timeout
                if ((time() - $start_time) > $timeout) {
                    break;
                }

                $result = $this->sync_single_invoice($order_id);

                if ($result['success']) {
                    $synced++;
                } else {
                    $errors++;
                }
            }

            // Update last run timestamp
            update_option('b2brouter_status_sync_last_run', time());

            return array('synced' => $synced, 'errors' => $errors);

        } catch (\Exception $e) {
            return array('synced' => $synced, 'errors' => $errors + 1);
        }
    }

    /**
     * Get orders that need status sync
     *
     * @since 1.0.0
     * @param int $limit Maximum number of orders to return
     * @return array Array of order IDs
     */
    private function get_orders_needing_sync($limit = 50) {
        // Use WooCommerce order query for HPOS compatibility
        $args = array(
            'limit' => $limit * 2, // Get more to filter later
            'type' => array('shop_order', 'shop_order_refund'),
            'return' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_b2brouter_invoice_id',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        $order_ids = wc_get_orders($args);

        if (empty($order_ids)) {
            return array();
        }

        $one_hour_ago = time() - HOUR_IN_SECONDS;
        $orders_needing_sync = array();

        foreach ($order_ids as $order_id) {
            if (count($orders_needing_sync) >= $limit) {
                break;
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            $status = $order->get_meta('_b2brouter_invoice_status');
            $status_updated = $order->get_meta('_b2brouter_invoice_status_updated');

            // Include if:
            // - No status set yet
            // - Status is not in final states
            // - Status hasn't been updated in the last hour
            if (empty($status) ||
                !$this->is_final_state($status) ||
                empty($status_updated) ||
                $status_updated < $one_hour_ago) {
                $orders_needing_sync[] = $order_id;
            }
        }

        return $orders_needing_sync;
    }

    /**
     * Sync status for a single invoice
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @return array Result array with success and message
     */
    public function sync_single_invoice($order_id) {
        try {
            $order = wc_get_order($order_id);

            if (!$order) {
                return array(
                    'success' => false,
                    'message' => __('Order not found', 'b2brouter-woocommerce')
                );
            }

            $invoice_id = $order->get_meta('_b2brouter_invoice_id');

            if (empty($invoice_id)) {
                return array(
                    'success' => false,
                    'message' => __('No invoice ID found', 'b2brouter-woocommerce')
                );
            }

            // Get B2Brouter client
            $client = $this->get_client();

            // Fetch invoice from API
            $invoice = $client->invoices->retrieve($invoice_id);

            if (empty($invoice)) {
                return array(
                    'success' => false,
                    'message' => __('Invoice not found in B2Brouter', 'b2brouter-woocommerce')
                );
            }

            // Extract status from response (B2Brouter uses 'state' field)
            $status = isset($invoice['state']) ? strtolower($invoice['state']) : 'unknown';

            // Store status in order meta
            $order->update_meta_data('_b2brouter_invoice_status', $status);
            $order->update_meta_data('_b2brouter_invoice_status_updated', time());

            // If status is error, store error message if available
            if ($status === 'error' && isset($invoice['error_message'])) {
                $order->update_meta_data('_b2brouter_invoice_status_error', $invoice['error_message']);
            } else {
                $order->delete_meta_data('_b2brouter_invoice_status_error');
            }

            $order->save();

            return array(
                'success' => true,
                'message' => sprintf(__('Status updated to: %s', 'b2brouter-woocommerce'), $status),
                'status' => $status
            );

        } catch (\B2BRouter\Exception\ResourceNotFoundException $e) {
            return array(
                'success' => false,
                'message' => __('Invoice not found in B2Brouter', 'b2brouter-woocommerce')
            );

        } catch (\B2BRouter\Exception\AuthenticationException $e) {
            return array(
                'success' => false,
                'message' => __('API authentication failed', 'b2brouter-woocommerce')
            );

        } catch (\B2BRouter\Exception\ApiErrorException $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('API error: %s', 'b2brouter-woocommerce'), $e->getMessage())
            );

        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Get cached invoice status
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @return string|null Status or null if not found
     */
    public function get_invoice_status($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return null;
        }

        $status = $order->get_meta('_b2brouter_invoice_status');

        return !empty($status) ? $status : null;
    }

    /**
     * Check if status is a final state
     *
     * @since 1.0.0
     * @param string $status Status to check
     * @return bool True if final state
     */
    public function is_final_state($status) {
        return in_array(strtolower($status), self::FINAL_STATES, true);
    }

    /**
     * Get B2Brouter client instance
     *
     * @since 1.0.0
     * @return \B2BRouter\Client\B2BRouterClient
     * @throws \Exception
     */
    private function get_client() {
        $api_key = $this->settings->get_api_key();

        if (empty($api_key)) {
            throw new \Exception(__('API key not configured', 'b2brouter-woocommerce'));
        }

        if (!class_exists('B2BRouter\B2BRouterClient')) {
            throw new \Exception(__('B2Brouter PHP SDK not found', 'b2brouter-woocommerce'));
        }

        $options = array('api_base' => $this->settings->get_api_base_url());
        return new \B2BRouter\B2BRouterClient($api_key, $options);
    }

    /**
     * Manual sync trigger for admin
     *
     * @since 1.0.0
     * @param int $order_id Order ID to sync
     * @return array Result array
     */
    public function manual_sync($order_id) {
        return $this->sync_single_invoice($order_id);
    }
}
