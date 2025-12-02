<?php
/**
 * WooCommerce Order Handler
 *
 * @package B2Brouter\WooCommerce
 * @since 1.0.0
 */

namespace B2Brouter\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order_Handler class
 *
 * Handles WooCommerce order integration and invoice generation triggers
 *
 * @since 1.0.0
 */
class Order_Handler {

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
     * @param Invoice_Generator $invoice_generator Invoice generator instance
     */
    public function __construct(Settings $settings, Invoice_Generator $invoice_generator) {
        $this->settings = $settings;
        $this->invoice_generator = $invoice_generator;

        // Automatic invoice generation on order completed (priority 1 to run before emails at priority 10)
        add_action('woocommerce_order_status_completed', array($this, 'maybe_generate_invoice_automatic'), 1);

        // Note: Credit notes are generated on-demand when accessed (email, download, etc.)
        // if parent invoice exists, for accounting compliance

        // Add meta box to order admin
        add_action('add_meta_boxes', array($this, 'add_invoice_meta_box'));

        // Add invoice column to orders list (legacy and HPOS)
        add_filter('manage_edit-shop_order_columns', array($this, 'add_invoice_column'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'render_invoice_column'), 20, 2);

        // HPOS compatibility
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_invoice_column'), 20);
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'render_invoice_column_hpos'), 20, 2);

        // Add bulk action for generating invoices
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_action'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_action'), 10, 3);
        add_action('admin_notices', array($this, 'bulk_action_notices'));

        // Email PDF attachments
        add_filter('woocommerce_email_attachments', array($this, 'attach_pdf_to_email'), 10, 3);

        // Schedule cleanup cron job
        if (!wp_next_scheduled('b2brouter_cleanup_old_pdfs')) {
            wp_schedule_event(time(), 'daily', 'b2brouter_cleanup_old_pdfs');
        }
        add_action('b2brouter_cleanup_old_pdfs', array($this, 'run_scheduled_cleanup'));
    }

    /**
     * Maybe generate invoice automatically
     *
     * @since 1.0.0
     * @param int $order_id The order ID
     * @return void
     */
    public function maybe_generate_invoice_automatic($order_id) {
        // Check if automatic mode is enabled
        if ($this->settings->get_invoice_mode() !== 'automatic') {
            return;
        }

        // Check if API key is configured
        if (!$this->settings->is_api_key_configured()) {
            return;
        }

        // Check if invoice already exists
        if ($this->invoice_generator->has_invoice($order_id)) {
            return;
        }

        // Generate invoice
        $this->invoice_generator->generate_invoice($order_id);
    }

    /**
     * Add invoice meta box to order admin
     *
     * @since 1.0.0
     * @return void
     */
    public function add_invoice_meta_box() {
        // Add for regular orders
        add_meta_box(
            'b2brouter_invoice',
            __('B2Brouter Invoice', 'b2brouter-woocommerce'),
            array($this, 'render_invoice_meta_box'),
            'shop_order',
            'side',
            'default'
        );

        // Add for refunds
        add_meta_box(
            'b2brouter_invoice',
            __('B2Brouter Credit Note', 'b2brouter-woocommerce'),
            array($this, 'render_invoice_meta_box'),
            'shop_order_refund',
            'side',
            'default'
        );

        // WooCommerce HPOS compatibility
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') &&
            \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            add_meta_box(
                'b2brouter_invoice',
                __('B2Brouter Invoice', 'b2brouter-woocommerce'),
                array($this, 'render_invoice_meta_box'),
                'woocommerce_page_wc-orders',
                'side',
                'default'
            );
        }
    }

    /**
     * Render invoice meta box
     *
     * @since 1.0.0
     * @param \WP_Post|\WC_Order|\WC_Order_Refund $post_or_order Post or order object
     * @return void
     */
    public function render_invoice_meta_box($post_or_order) {
        $order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order($post_or_order->ID);
        $order_id = $order->get_id();
        $is_refund = $order->get_type() === 'shop_order_refund';

        $has_invoice = $this->invoice_generator->has_invoice($order_id);
        $invoice_id = $this->invoice_generator->get_invoice_id($order_id);

        // For refunds, get parent order info
        $parent_order = null;
        $parent_has_invoice = false;
        if ($is_refund) {
            $parent_id = $order->get_parent_id();
            $parent_order = $parent_id ? wc_get_order($parent_id) : null;
            $parent_has_invoice = $parent_order ? $this->invoice_generator->has_invoice($parent_id) : false;
        }

        ?>
        <div class="b2brouter-invoice-meta-box">
            <?php if ($is_refund && $parent_order): ?>
                <!-- Parent Order Invoice Info -->
                <p>
                    <strong><?php esc_html_e('Parent Order:', 'b2brouter-woocommerce'); ?></strong>
                    <br>
                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $parent_order->get_id() . '&action=edit')); ?>">
                        #<?php echo esc_html($parent_order->get_order_number()); ?>
                    </a>
                </p>

                <?php if ($parent_has_invoice): ?>
                    <p>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php esc_html_e('Parent has invoice', 'b2brouter-woocommerce'); ?>
                    </p>
                    <p class="description" style="font-size: 11px;">
                        <strong><?php esc_html_e('Parent Invoice:', 'b2brouter-woocommerce'); ?></strong>
                        <?php echo esc_html(Invoice_Generator::get_formatted_invoice_number($parent_order)); ?>
                    </p>
                <?php else: ?>
                    <p>
                        <span class="dashicons dashicons-warning" style="color: #f0b849;"></span>
                        <?php esc_html_e('Parent has no invoice', 'b2brouter-woocommerce'); ?>
                    </p>
                    <p class="description">
                        <?php esc_html_e('Generate invoice for parent order first.', 'b2brouter-woocommerce'); ?>
                    </p>
                <?php endif; ?>

                <hr style="margin: 15px 0;">
            <?php endif; ?>

            <?php if ($has_invoice): ?>
                <p class="b2brouter-invoice-status">
                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                    <?php
                    if ($is_refund) {
                        esc_html_e('Credit Note Generated', 'b2brouter-woocommerce');
                    } else {
                        esc_html_e('Invoice Generated', 'b2brouter-woocommerce');
                    }
                    ?>
                </p>
                <p>
                    <strong>
                        <?php
                        if ($is_refund) {
                            esc_html_e('Credit Note ID:', 'b2brouter-woocommerce');
                        } else {
                            esc_html_e('Invoice ID:', 'b2brouter-woocommerce');
                        }
                        ?>
                    </strong>
                    <br><?php echo esc_html($invoice_id); ?>
                </p>
                <p>
                    <strong>
                        <?php
                        if ($is_refund) {
                            esc_html_e('Credit Note Number:', 'b2brouter-woocommerce');
                        } else {
                            esc_html_e('Invoice Number:', 'b2brouter-woocommerce');
                        }
                        ?>
                    </strong>
                    <br><?php echo esc_html(Invoice_Generator::get_formatted_invoice_number($order)); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('Generated Date:', 'b2brouter-woocommerce'); ?></strong>
                    <br><?php echo esc_html($order->get_meta('_b2brouter_invoice_date')); ?>
                </p>

                <?php
                // Display invoice status
                $status = $order->get_meta('_b2brouter_invoice_status');
                $status_updated = $order->get_meta('_b2brouter_invoice_status_updated');

                if (!empty($status)):
                ?>
                <p>
                    <strong><?php esc_html_e('Status:', 'b2brouter-woocommerce'); ?></strong>
                    <br>
                    <span class="b2brouter-status-badge status-<?php echo esc_attr($status); ?>">
                        <?php echo esc_html(ucfirst($status)); ?>
                    </span>
                    <?php if ($status_updated): ?>
                        <br>
                        <small style="color: #666;">
                            <?php
                            printf(
                                esc_html__('Last checked: %s', 'b2brouter-woocommerce'),
                                esc_html(human_time_diff($status_updated, current_time('timestamp'))) . ' ' . esc_html__('ago', 'b2brouter-woocommerce')
                            );
                            ?>
                        </small>
                    <?php endif; ?>
                </p>

                <?php
                // Show error message if status is error
                if ($status === 'error'):
                    $status_error = $order->get_meta('_b2brouter_invoice_status_error');
                    if (!empty($status_error)):
                ?>
                <div class="notice notice-error inline" style="margin: 10px 0; padding: 8px 12px;">
                    <p style="margin: 0;">
                        <strong><?php esc_html_e('Error:', 'b2brouter-woocommerce'); ?></strong>
                        <?php echo esc_html($status_error); ?>
                    </p>
                </div>
                <?php
                    endif;
                endif;
                ?>

                <?php endif; ?>

                <!-- PDF Download Section -->
                <hr style="margin: 15px 0;">
                <p>
                    <button type="button"
                            class="button button-secondary b2brouter-download-pdf"
                            data-order-id="<?php echo esc_attr($order_id); ?>"
                            data-download="view"
                            style="width: 100%; margin-bottom: 5px;">
                        <span class="dashicons dashicons-pdf"></span>
                        <?php esc_html_e('View PDF', 'b2brouter-woocommerce'); ?>
                    </button>
                </p>
                <p>
                    <button type="button"
                            class="button button-secondary b2brouter-download-pdf"
                            data-order-id="<?php echo esc_attr($order_id); ?>"
                            data-download="download"
                            style="width: 100%;">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Download PDF', 'b2brouter-woocommerce'); ?>
                    </button>
                </p>

                <?php
                // Show PDF cache status if available
                $pdf_path = $order->get_meta('_b2brouter_invoice_pdf_path');
                if (!empty($pdf_path) && file_exists($pdf_path)):
                    $pdf_size = $order->get_meta('_b2brouter_invoice_pdf_size');
                    $pdf_date = $order->get_meta('_b2brouter_invoice_pdf_date');
                ?>
                <p class="description" style="margin-top: 10px; font-size: 11px;">
                    <span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 14px;"></span>
                    <?php
                    printf(
                        esc_html__('PDF cached (%s, %s)', 'b2brouter-woocommerce'),
                        esc_html(size_format($pdf_size, 2)),
                        esc_html(human_time_diff(strtotime($pdf_date), current_time('timestamp'))) . ' ' . esc_html__('ago', 'b2brouter-woocommerce')
                    );
                    ?>
                </p>
                <?php endif; ?>
            <?php else: ?>
                <p class="b2brouter-invoice-status">
                    <span class="dashicons dashicons-warning" style="color: #f0b849;"></span>
                    <?php
                    if ($is_refund) {
                        esc_html_e('Credit Note Not Generated', 'b2brouter-woocommerce');
                    } else {
                        esc_html_e('Invoice Not Generated', 'b2brouter-woocommerce');
                    }
                    ?>
                </p>

                <?php if (!$this->settings->is_api_key_configured()): ?>
                    <p class="description">
                        <?php esc_html_e('API key not configured.', 'b2brouter-woocommerce'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=b2brouter')); ?>">
                            <?php esc_html_e('Configure now', 'b2brouter-woocommerce'); ?>
                        </a>
                    </p>
                <?php elseif ($is_refund && !$parent_has_invoice): ?>
                    <p class="description">
                        <?php esc_html_e('Cannot generate credit note: parent order has no invoice.', 'b2brouter-woocommerce'); ?>
                    </p>
                <?php else: ?>
                    <p>
                        <button type="button"
                                class="button button-primary b2brouter-generate-invoice"
                                data-order-id="<?php echo esc_attr($order_id); ?>">
                            <?php
                            if ($is_refund) {
                                esc_html_e('Generate Credit Note', 'b2brouter-woocommerce');
                            } else {
                                esc_html_e('Generate Invoice', 'b2brouter-woocommerce');
                            }
                            ?>
                        </button>
                    </p>
                    <p class="description">
                        <?php
                        if ($is_refund) {
                            esc_html_e('Click to manually generate a credit note for this refund.', 'b2brouter-woocommerce');
                        } else {
                            esc_html_e('Click to manually generate an invoice for this order.', 'b2brouter-woocommerce');
                        }
                        ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            // Show refund invoices for parent orders
            if (!$is_refund) {
                $refunds = $order->get_refunds();
                if (!empty($refunds)) {
                    ?>
                    <hr style="margin: 15px 0;">
                    <h4 style="margin-top: 0;"><?php esc_html_e('Refund Invoices', 'b2brouter-woocommerce'); ?></h4>
                    <?php
                    foreach ($refunds as $refund) {
                        $refund_has_invoice = $this->invoice_generator->has_invoice($refund->get_id());
                        $refund_invoice_number = Invoice_Generator::get_formatted_invoice_number($refund);
                        ?>
                        <div style="padding: 8px; background: #f9f9f9; margin-bottom: 8px; border-left: 3px solid <?php echo $refund_has_invoice ? '#46b450' : '#ddd'; ?>;">
                            <p style="margin: 0 0 5px 0;">
                                <strong>
                                    <?php printf(esc_html__('Refund #%s', 'b2brouter-woocommerce'), $refund->get_id()); ?>
                                </strong>
                                <span style="color: #666; font-size: 0.9em;">
                                    (<?php echo wc_price($refund->get_amount(), array('currency' => $order->get_currency())); ?>)
                                </span>
                            </p>
                            <?php if ($refund_has_invoice): ?>
                                <p style="margin: 0 0 5px 0; font-size: 0.9em;">
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 14px;"></span>
                                    <?php echo esc_html($refund_invoice_number); ?>
                                </p>
                                <p style="margin: 0;">
                                    <button type="button"
                                            class="button button-small b2brouter-download-pdf"
                                            data-order-id="<?php echo esc_attr($refund->get_id()); ?>"
                                            data-download="view"
                                            style="margin-right: 5px; padding: 0 8px; height: 24px; line-height: 22px; font-size: 11px;">
                                        <span class="dashicons dashicons-pdf" style="font-size: 13px; width: 13px; height: 13px;"></span>
                                        <?php esc_html_e('View', 'b2brouter-woocommerce'); ?>
                                    </button>
                                    <button type="button"
                                            class="button button-small b2brouter-download-pdf"
                                            data-order-id="<?php echo esc_attr($refund->get_id()); ?>"
                                            data-download="download"
                                            style="padding: 0 8px; height: 24px; line-height: 22px; font-size: 11px;">
                                        <span class="dashicons dashicons-download" style="font-size: 13px; width: 13px; height: 13px;"></span>
                                        <?php esc_html_e('Download', 'b2brouter-woocommerce'); ?>
                                    </button>
                                </p>
                            <?php else: ?>
                                <p style="margin: 0; font-size: 0.9em; color: #999;">
                                    <span class="dashicons dashicons-minus" style="font-size: 14px;"></span>
                                    <?php esc_html_e('No credit note', 'b2brouter-woocommerce'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                }
            }
            ?>

            <p>
                <?php
                // Build B2Brouter dashboard URL
                $dashboard_url = 'https://app.b2brouter.net';
                if ($has_invoice) {
                    $invoice_id = $order->get_meta('_b2brouter_invoice_id');
                    if (!empty($invoice_id)) {
                        $dashboard_url = 'https://app.b2brouter.net/invoices/' . urlencode($invoice_id);
                    }
                }

                // Get status to determine button text
                $invoice_status = $order->get_meta('_b2brouter_invoice_status');
                $button_text = ($invoice_status === 'error')
                    ? __('Manage from B2Brouter', 'b2brouter-woocommerce')
                    : __('View in B2Brouter', 'b2brouter-woocommerce');
                ?>
                <a href="<?php echo esc_url($dashboard_url); ?>" target="_blank" class="button button-secondary">
                    <?php echo esc_html($button_text); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Add invoice column to orders list
     *
     * @since 1.0.0
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_invoice_column($columns) {
        $new_columns = array();

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;

            // Add invoice column after order number
            if ($key === 'order_number') {
                $new_columns['b2brouter_invoice'] = __('Invoice', 'b2brouter-woocommerce');
            }
        }

        return $new_columns;
    }

    /**
     * Render invoice column
     *
     * @since 1.0.0
     * @param string $column Column name
     * @param int $post_id Post ID
     * @return void
     */
    public function render_invoice_column($column, $post_id) {
        if ($column !== 'b2brouter_invoice') {
            return;
        }

        $order = wc_get_order($post_id);
        if (!$order) {
            return;
        }

        $invoice_id = $order->get_meta('_b2brouter_invoice_id');

        if (empty($invoice_id)) {
            echo '<span style="color: #999;">—</span>';
            return;
        }

        // Get status from meta
        $status = $order->get_meta('_b2brouter_invoice_status');

        if (empty($status)) {
            $status = 'draft'; // Default if not yet synced
        }

        // Display status badge with color
        $status_colors = array(
            'draft' => '#999',
            'sent' => '#00a32a',
            'accepted' => '#00a32a',
            'registered' => '#00a32a',
            'paid' => '#00a32a',
            'error' => '#d63638',
            'cancelled' => '#dba617',
            'closed' => '#999'
        );

        $color = isset($status_colors[$status]) ? $status_colors[$status] : '#2271b1';

        printf(
            '<span style="color: %s; font-weight: 500;" title="%s">%s</span>',
            esc_attr($color),
            esc_attr(ucfirst($status)),
            esc_html(ucfirst($status))
        );
    }

    /**
     * Render invoice column for HPOS orders
     *
     * @since 1.0.0
     * @param string $column Column name
     * @param \WC_Order $order Order object
     * @return void
     */
    public function render_invoice_column_hpos($column, $order) {
        if ($column !== 'b2brouter_invoice') {
            return;
        }

        if (!is_a($order, 'WC_Order')) {
            return;
        }

        $invoice_id = $order->get_meta('_b2brouter_invoice_id');

        if (empty($invoice_id)) {
            echo '<span style="color: #999;">—</span>';
            return;
        }

        // Get status from meta
        $status = $order->get_meta('_b2brouter_invoice_status');

        if (empty($status)) {
            $status = 'draft'; // Default if not yet synced
        }

        // Display status badge with color
        $status_colors = array(
            'draft' => '#999',
            'sent' => '#00a32a',
            'accepted' => '#00a32a',
            'registered' => '#00a32a',
            'paid' => '#00a32a',
            'error' => '#d63638',
            'cancelled' => '#dba617',
            'closed' => '#999'
        );

        $color = isset($status_colors[$status]) ? $status_colors[$status] : '#2271b1';

        printf(
            '<span style="color: %s; font-weight: 500;" title="%s">%s</span>',
            esc_attr($color),
            esc_attr(ucfirst($status)),
            esc_html(ucfirst($status))
        );
    }

    /**
     * Add bulk action
     *
     * @since 1.0.0
     * @param array $actions Existing bulk actions
     * @return array Modified bulk actions
     */
    public function add_bulk_action($actions) {
        $actions['b2brouter_generate_invoices'] = __('Generate B2Brouter Invoices', 'b2brouter-woocommerce');
        return $actions;
    }

    /**
     * Handle bulk action
     *
     * @since 1.0.0
     * @param string $redirect_to Redirect URL
     * @param string $action Action name
     * @param array $post_ids Post IDs
     * @return string Modified redirect URL
     */
    public function handle_bulk_action($redirect_to, $action, $post_ids) {
        if ($action !== 'b2brouter_generate_invoices') {
            return $redirect_to;
        }

        $success_count = 0;
        $error_count = 0;

        foreach ($post_ids as $post_id) {
            $result = $this->invoice_generator->generate_invoice($post_id);
            if ($result['success']) {
                $success_count++;
            } else {
                $error_count++;
            }
        }

        $redirect_to = add_query_arg(array(
            'b2brouter_bulk_success' => $success_count,
            'b2brouter_bulk_error' => $error_count,
        ), $redirect_to);

        return $redirect_to;
    }

    /**
     * Show bulk action notices
     *
     * @since 1.0.0
     * @return void
     */
    public function bulk_action_notices() {
        if (!isset($_GET['b2brouter_bulk_success']) && !isset($_GET['b2brouter_bulk_error'])) {
            return;
        }

        $success_count = isset($_GET['b2brouter_bulk_success']) ? intval($_GET['b2brouter_bulk_success']) : 0;
        $error_count = isset($_GET['b2brouter_bulk_error']) ? intval($_GET['b2brouter_bulk_error']) : 0;

        if ($success_count > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo sprintf(
                _n(
                    '%d invoice generated successfully.',
                    '%d invoices generated successfully.',
                    $success_count,
                    'b2brouter-woocommerce'
                ),
                $success_count
            );
            echo '</p></div>';
        }

        if ($error_count > 0) {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo sprintf(
                _n(
                    '%d invoice failed to generate.',
                    '%d invoices failed to generate.',
                    $error_count,
                    'b2brouter-woocommerce'
                ),
                $error_count
            );
            echo '</p></div>';
        }
    }

    /**
     * Attach PDF to WooCommerce emails
     *
     * @since 1.0.0
     * @param array $attachments Existing attachments
     * @param string $email_id Email ID
     * @param mixed $order Order object or false
     * @return array Modified attachments
     */
    public function attach_pdf_to_email($attachments, $email_id, $order) {
        return $this->invoice_generator->attach_pdf_to_email($attachments, $email_id, $order);
    }

    /**
     * Run scheduled PDF cleanup
     *
     * @since 1.0.0
     * @return void
     */
    public function run_scheduled_cleanup() {
        // Only run if automatic cleanup is enabled
        if (!$this->settings->get_auto_cleanup_enabled()) {
            return;
        }

        $days = $this->settings->get_auto_cleanup_days();
        $result = $this->invoice_generator->cleanup_old_pdfs($days);

        // Log the cleanup
        if ($result['deleted'] > 0 || $result['errors'] > 0) {
            error_log(sprintf(
                'B2Brouter PDF Cleanup: Deleted %d files, %d errors (older than %d days)',
                $result['deleted'],
                $result['errors'],
                $days
            ));
        }
    }
}
