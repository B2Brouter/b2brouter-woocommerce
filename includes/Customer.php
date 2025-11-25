<?php
/**
 * Customer Frontend Class
 *
 * Handles customer-facing functionality including PDF downloads
 *
 * @package B2Brouter\WooCommerce
 * @since 1.0.0
 */

namespace B2Brouter\WooCommerce;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customer class
 *
 * @since 1.0.0
 */
class Customer {

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

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function init_hooks() {
        // Add PDF download link to My Account orders
        add_filter('woocommerce_my_account_my_orders_actions', array($this, 'add_pdf_download_to_my_account'), 10, 2);

        // Add PDF download section to Order Received (Thank You) page
        add_action('woocommerce_thankyou', array($this, 'add_pdf_to_thankyou_page'), 20, 1);

        // Handle customer PDF download requests
        add_action('wp_ajax_b2brouter_customer_download_pdf', array($this, 'ajax_customer_download_pdf'));
        add_action('wp_ajax_nopriv_b2brouter_customer_download_pdf', array($this, 'ajax_customer_download_pdf'));

        // Enqueue frontend scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue frontend scripts and styles
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_scripts() {
        // Load on My Account pages (including orders), Order Received page, and View Order page
        if (!is_account_page() && !is_order_received_page() && !is_wc_endpoint_url('view-order')) {
            return;
        }

        wp_enqueue_style(
            'b2brouter-customer',
            B2BROUTER_WC_PLUGIN_URL . 'assets/css/customer.css',
            array(),
            B2BROUTER_WC_VERSION
        );

        wp_enqueue_script(
            'b2brouter-customer',
            B2BROUTER_WC_PLUGIN_URL . 'assets/js/customer.js',
            array('jquery'),
            B2BROUTER_WC_VERSION,
            true
        );

        wp_localize_script('b2brouter-customer', 'b2brouterCustomer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('b2brouter_customer_nonce'),
            'strings' => array(
                'downloading' => __('Downloading...', 'b2brouter-woocommerce'),
                'error' => __('Error downloading PDF', 'b2brouter-woocommerce'),
            ),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
        ));
    }

    /**
     * Add PDF download action to My Account orders
     *
     * @since 1.0.0
     * @param array $actions Existing actions
     * @param \WC_Order $order The order object
     * @return array Modified actions
     */
    public function add_pdf_download_to_my_account($actions, $order) {
        // Check if order has an invoice
        $invoice_id = $order->get_meta('_b2brouter_invoice_id');

        if (empty($invoice_id)) {
            return $actions;
        }

        // Add PDF download action
        // Note: We use javascript:void(0) to prevent page jump, and add data attributes
        $actions['download_invoice_pdf'] = array(
            'url' => 'javascript:void(0);',
            'name' => __('Download Invoice PDF', 'b2brouter-woocommerce'),
            'class' => 'b2brouter-customer-download-pdf',
        );

        return $actions;
    }

    /**
     * Add PDF download section to Thank You page
     *
     * @since 1.0.0
     * @param int $order_id The order ID
     * @return void
     */
    public function add_pdf_to_thankyou_page($order_id) {
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        // Check if order has an invoice
        $invoice_id = $order->get_meta('_b2brouter_invoice_id');
        $invoice_number = Invoice_Generator::get_formatted_invoice_number($order);

        if (empty($invoice_id)) {
            return;
        }

        ?>
        <section class="b2brouter-invoice-section woocommerce-order-details">
            <h2 class="woocommerce-order-details__title"><?php esc_html_e('Invoice', 'b2brouter-woocommerce'); ?></h2>

            <div class="b2brouter-invoice-details">
                <?php if (!empty($invoice_number)): ?>
                    <p>
                        <strong><?php esc_html_e('Invoice Number:', 'b2brouter-woocommerce'); ?></strong>
                        <?php echo esc_html($invoice_number); ?>
                    </p>
                <?php endif; ?>

                <p>
                    <button type="button"
                            class="button b2brouter-customer-download-pdf"
                            data-order-id="<?php echo esc_attr($order_id); ?>"
                            data-order-key="<?php echo esc_attr($order->get_order_key()); ?>">
                        <span class="dashicons dashicons-pdf"></span>
                        <?php esc_html_e('Download Invoice PDF', 'b2brouter-woocommerce'); ?>
                    </button>
                </p>

                <?php
                // Show cache status if PDF is cached
                $pdf_path = $order->get_meta('_b2brouter_invoice_pdf_path');
                if (!empty($pdf_path) && file_exists($pdf_path)):
                    $pdf_size = $order->get_meta('_b2brouter_invoice_pdf_size');
                ?>
                    <p class="b2brouter-pdf-info">
                        <small>
                            <?php
                            printf(
                                esc_html__('PDF Size: %s', 'b2brouter-woocommerce'),
                                esc_html(size_format($pdf_size, 2))
                            );
                            ?>
                        </small>
                    </p>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }

    /**
     * AJAX: Customer download PDF
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_customer_download_pdf() {
        // Verify nonce
        check_ajax_referer('b2brouter_customer_nonce', 'nonce');

        // Get order ID
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(array(
                'message' => __('Invalid order ID', 'b2brouter-woocommerce')
            ));
        }

        // Get order
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(array(
                'message' => __('Order not found', 'b2brouter-woocommerce')
            ));
        }

        // Check customer permissions
        if (!$this->can_customer_access_order($order)) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to access this order', 'b2brouter-woocommerce')
            ));
        }

        // Check if order has an invoice
        $invoice_id = $order->get_meta('_b2brouter_invoice_id');

        if (empty($invoice_id)) {
            wp_send_json_error(array(
                'message' => __('No invoice found for this order', 'b2brouter-woocommerce')
            ));
        }

        // Stream PDF directly (this will exit)
        $this->invoice_generator->stream_invoice_pdf($order_id, true);
    }

    /**
     * Check if customer can access order
     *
     * @since 1.0.0
     * @param \WC_Order $order The order object
     * @return bool True if customer has access
     */
    private function can_customer_access_order($order) {
        // Check if current user is the order customer
        $user_id = get_current_user_id();
        if ($user_id > 0 && (int) $order->get_customer_id() === $user_id) {
            return true;
        }

        // Check for guest access with order key
        if (isset($_POST['order_key']) && $order->get_order_key() === $_POST['order_key']) {
            return true;
        }

        return false;
    }
}
