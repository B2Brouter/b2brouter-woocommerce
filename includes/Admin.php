<?php
/**
 * Admin Interface Handler
 *
 * @package B2Brouter\WooCommerce
 * @since 1.0.0
 */

namespace B2Brouter\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class
 *
 * Handles all admin interface, settings pages, and AJAX operations
 *
 * @since 1.0.0
 */
class Admin {

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

        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Show welcome page on activation
        add_action('admin_init', array($this, 'maybe_show_welcome'));

        // Add settings link on plugins page
        add_filter('plugin_action_links_' . B2BROUTER_WC_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));

        // Admin bar counter
        add_action('admin_bar_menu', array($this, 'add_admin_bar_counter'), 100);

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Handle AJAX requests
        add_action('wp_ajax_b2brouter_validate_api_key', array($this, 'ajax_validate_api_key'));
        add_action('wp_ajax_b2brouter_generate_invoice', array($this, 'ajax_generate_invoice'));
        add_action('wp_ajax_b2brouter_download_pdf', array($this, 'ajax_download_pdf'));
    }

    /**
     * Add admin menu
     *
     * @since 1.0.0
     * @return void
     */
    public function add_admin_menu() {
        add_menu_page(
            __('B2Brouter', 'b2brouter-woocommerce'),
            __('B2Brouter', 'b2brouter-woocommerce'),
            'manage_options',
            'b2brouter',
            array($this, 'render_settings_page'),
            'dashicons-media-document',
            56
        );

        // Register Welcome page first
        add_submenu_page(
            'b2brouter',
            __('Welcome', 'b2brouter-woocommerce'),
            __('Welcome', 'b2brouter-woocommerce'),
            'manage_options',
            'b2brouter-welcome',
            array($this, 'render_welcome_page')
        );

        // Register Settings page second (renames the default first submenu item)
        add_submenu_page(
            'b2brouter',
            __('Settings', 'b2brouter-woocommerce'),
            __('Settings', 'b2brouter-woocommerce'),
            'manage_options',
            'b2brouter',
            array($this, 'render_settings_page')
        );

        // Register List of Invoices page third
        add_submenu_page(
            'b2brouter',
            __('List of Invoices', 'b2brouter-woocommerce'),
            __('List of Invoices', 'b2brouter-woocommerce'),
            'manage_woocommerce',
            'b2brouter-invoices',
            array($this, 'render_invoices_page')
        );
    }

    /**
     * Register settings
     *
     * @since 1.0.0
     * @return void
     */
    public function register_settings() {
        register_setting('b2brouter_settings', 'b2brouter_api_key');
        register_setting('b2brouter_settings', 'b2brouter_environment');
        register_setting('b2brouter_settings', 'b2brouter_invoice_mode');
    }

    /**
     * Maybe show welcome page on activation
     *
     * @since 1.0.0
     * @return void
     */
    public function maybe_show_welcome() {
        if ($this->settings->should_show_welcome()) {
            $this->settings->mark_welcome_shown();
            wp_safe_redirect(admin_url('admin.php?page=b2brouter-welcome'));
            exit;
        }
    }

    /**
     * Add plugin action links
     *
     * @since 1.0.0
     * @param array $links Existing plugin action links
     * @return array Modified plugin action links
     */
    public function add_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=b2brouter'),
            __('Settings', 'b2brouter-woocommerce')
        );

        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Add admin bar counter
     *
     * @since 1.0.0
     * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance
     * @return void
     */
    public function add_admin_bar_counter($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $count = $this->settings->get_transaction_count();

        $wp_admin_bar->add_node(array(
            'id'    => 'b2brouter-counter',
            'title' => sprintf(
                '<span class="ab-icon dashicons dashicons-media-document"></span> <span class="ab-label">%s</span>',
                sprintf(__('Invoices: %d', 'b2brouter-woocommerce'), $count)
            ),
            'href'  => admin_url('admin.php?page=b2brouter'),
            'meta'  => array(
                'title' => __('B2Brouter Invoices', 'b2brouter-woocommerce'),
            ),
        ));
    }

    /**
     * Enqueue admin scripts
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_scripts($hook) {
        // Load on B2Brouter pages, order edit pages, and WooCommerce HPOS order pages
        $allowed_hooks = array('post.php', 'edit.php', 'woocommerce_page_wc-orders');

        if (strpos($hook, 'b2brouter') === false && !in_array($hook, $allowed_hooks)) {
            return;
        }

        wp_enqueue_style(
            'b2brouter-admin',
            B2BROUTER_WC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            B2BROUTER_WC_VERSION
        );

        wp_enqueue_script(
            'b2brouter-admin',
            B2BROUTER_WC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            B2BROUTER_WC_VERSION,
            true
        );

        // Check for bulk download transient
        $bulk_download_ids = get_transient('b2brouter_bulk_download_' . get_current_user_id());
        if ($bulk_download_ids) {
            delete_transient('b2brouter_bulk_download_' . get_current_user_id());
        }

        wp_localize_script('b2brouter-admin', 'b2brouterAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('b2brouter_nonce'),
            'bulk_download_ids' => $bulk_download_ids ? $bulk_download_ids : false,
            'strings' => array(
                'validating' => __('Validating...', 'b2brouter-woocommerce'),
                'generating' => __('Generating invoice...', 'b2brouter-woocommerce'),
                'success' => __('Success!', 'b2brouter-woocommerce'),
                'error' => __('Error', 'b2brouter-woocommerce'),
            ),
        ));
    }

    /**
     * AJAX: Validate API key
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_validate_api_key() {
        check_ajax_referer('b2brouter_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'b2brouter-woocommerce')));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        $result = $this->settings->validate_api_key($api_key);

        if ($result['valid']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Generate invoice
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_generate_invoice() {
        check_ajax_referer('b2brouter_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'b2brouter-woocommerce')));
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(array('message' => __('Invalid order ID', 'b2brouter-woocommerce')));
        }

        $result = $this->invoice_generator->generate_invoice($order_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Render welcome page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_welcome_page() {
        ?>
        <div class="wrap b2brouter-welcome">
            <h1><?php esc_html_e('Welcome to B2Brouter for WooCommerce', 'b2brouter-woocommerce'); ?></h1>

            <div class="b2brouter-welcome-content">
                <div class="b2brouter-card">
                    <h2><?php esc_html_e('Get Started with Electronic Invoicing', 'b2brouter-woocommerce'); ?></h2>

                    <p><?php esc_html_e('Thank you for installing B2Brouter for WooCommerce! This plugin allows you to automatically generate and send electronic invoices for your WooCommerce orders.', 'b2brouter-woocommerce'); ?></p>

                    <h3><?php esc_html_e('Requirements', 'b2brouter-woocommerce'); ?></h3>
                    <ul class="b2brouter-checklist">
                        <li><?php esc_html_e('An active eDocExchange subscription is required', 'b2brouter-woocommerce'); ?></li>
                        <li><?php esc_html_e('The subscription provides an API key to activate the plugin', 'b2brouter-woocommerce'); ?></li>
                        <li><?php esc_html_e('Invoices are generated and sent through B2Brouter', 'b2brouter-woocommerce'); ?></li>
                        <li><?php esc_html_e('Advanced configuration (transports, formats, taxes) is done in your B2Brouter account', 'b2brouter-woocommerce'); ?></li>
                    </ul>

                    <h3><?php esc_html_e('Next Steps', 'b2brouter-woocommerce'); ?></h3>
                    <ol class="b2brouter-steps">
                        <li><?php esc_html_e('Activate your eDocExchange subscription on B2Brouter', 'b2brouter-woocommerce'); ?></li>
                        <li><?php esc_html_e('Copy your API key from B2Brouter', 'b2brouter-woocommerce'); ?></li>
                        <li><?php esc_html_e('Return to WordPress and configure the plugin with your API key', 'b2brouter-woocommerce'); ?></li>
                    </ol>

                    <div class="b2brouter-actions">
                        <a href="https://app.b2brouter.net" class="button button-primary button-hero" target="_blank">
                            <?php esc_html_e('Go to B2Brouter - Activate Subscription', 'b2brouter-woocommerce'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=b2brouter')); ?>" class="button button-secondary button-hero">
                            <?php esc_html_e('Configure Plugin', 'b2brouter-woocommerce'); ?>
                        </a>
                    </div>
                </div>

                <div class="b2brouter-card b2brouter-info">
                    <h3><?php esc_html_e('Need Help?', 'b2brouter-woocommerce'); ?></h3>
                    <p><?php esc_html_e('Visit our documentation or contact support if you need assistance.', 'b2brouter-woocommerce'); ?></p>
                    <p>
                        <a href="https://app.b2brouter.net" target="_blank"><?php esc_html_e('Documentation', 'b2brouter-woocommerce'); ?></a> |
                        <a href="https://app.b2brouter.net" target="_blank"><?php esc_html_e('Support', 'b2brouter-woocommerce'); ?></a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_settings_page() {
        $api_key = $this->settings->get_api_key();
        $environment = $this->settings->get_environment();
        $invoice_mode = $this->settings->get_invoice_mode();
        $auto_save_pdf = $this->settings->get_auto_save_pdf();
        $attach_to_completed = $this->settings->get_attach_to_order_completed();
        $attach_to_invoice = $this->settings->get_attach_to_customer_invoice();
        $attach_to_refunded = $this->settings->get_attach_to_refunded_order();
        $auto_cleanup_enabled = $this->settings->get_auto_cleanup_enabled();
        $auto_cleanup_days = $this->settings->get_auto_cleanup_days();
        $transaction_count = $this->settings->get_transaction_count();
        $api_configured = $this->settings->is_api_key_configured();
        $invoice_series_code = $this->settings->get_invoice_series_code();
        $credit_note_series_code = $this->settings->get_credit_note_series_code();
        $numbering_pattern = $this->settings->get_invoice_numbering_pattern();
        $custom_pattern = $this->settings->get_custom_numbering_pattern();

        if (isset($_POST['b2brouter_save_settings']) && check_admin_referer('b2brouter_settings')) {
            // Save API key
            if (isset($_POST['b2brouter_api_key'])) {
                $new_api_key = sanitize_text_field($_POST['b2brouter_api_key']);
                $this->settings->set_api_key($new_api_key);
                $api_key = $new_api_key;
            }

            // Save environment
            if (isset($_POST['b2brouter_environment'])) {
                $this->settings->set_environment(sanitize_text_field($_POST['b2brouter_environment']));
                $environment = $this->settings->get_environment();
            }

            // Save invoice mode
            if (isset($_POST['b2brouter_invoice_mode'])) {
                $this->settings->set_invoice_mode(sanitize_text_field($_POST['b2brouter_invoice_mode']));
                $invoice_mode = $this->settings->get_invoice_mode();
            }

            // Save PDF auto-save setting
            $auto_save_enabled = isset($_POST['b2brouter_auto_save_pdf']) && $_POST['b2brouter_auto_save_pdf'] === '1';
            $this->settings->set_auto_save_pdf($auto_save_enabled);
            $auto_save_pdf = $auto_save_enabled;

            // Save email attachment settings
            $this->settings->set_attach_to_order_completed(
                isset($_POST['b2brouter_attach_to_order_completed']) && $_POST['b2brouter_attach_to_order_completed'] === '1'
            );
            $attach_to_completed = $this->settings->get_attach_to_order_completed();

            $this->settings->set_attach_to_customer_invoice(
                isset($_POST['b2brouter_attach_to_customer_invoice']) && $_POST['b2brouter_attach_to_customer_invoice'] === '1'
            );
            $attach_to_invoice = $this->settings->get_attach_to_customer_invoice();

            $this->settings->set_attach_to_refunded_order(
                isset($_POST['b2brouter_attach_to_refunded_order']) && $_POST['b2brouter_attach_to_refunded_order'] === '1'
            );
            $attach_to_refunded = $this->settings->get_attach_to_refunded_order();

            // Save cleanup settings
            $this->settings->set_auto_cleanup_enabled(
                isset($_POST['b2brouter_auto_cleanup_enabled']) && $_POST['b2brouter_auto_cleanup_enabled'] === '1'
            );
            $auto_cleanup_enabled = $this->settings->get_auto_cleanup_enabled();

            if (isset($_POST['b2brouter_auto_cleanup_days'])) {
                $this->settings->set_auto_cleanup_days(intval($_POST['b2brouter_auto_cleanup_days']));
                $auto_cleanup_days = $this->settings->get_auto_cleanup_days();
            }

            // Save invoice series code
            if (isset($_POST['b2brouter_invoice_series_code'])) {
                $this->settings->set_invoice_series_code(sanitize_text_field($_POST['b2brouter_invoice_series_code']));
                $invoice_series_code = $this->settings->get_invoice_series_code();
            }

            // Save credit note series code
            if (isset($_POST['b2brouter_credit_note_series_code'])) {
                $this->settings->set_credit_note_series_code(sanitize_text_field($_POST['b2brouter_credit_note_series_code']));
                $credit_note_series_code = $this->settings->get_credit_note_series_code();
            }

            // Save numbering pattern
            if (isset($_POST['b2brouter_invoice_numbering_pattern'])) {
                $this->settings->set_invoice_numbering_pattern(sanitize_text_field($_POST['b2brouter_invoice_numbering_pattern']));
                $numbering_pattern = $this->settings->get_invoice_numbering_pattern();
            }

            // Save custom pattern
            if (isset($_POST['b2brouter_custom_numbering_pattern'])) {
                $this->settings->set_custom_numbering_pattern(sanitize_text_field($_POST['b2brouter_custom_numbering_pattern']));
                $custom_pattern = $this->settings->get_custom_numbering_pattern();
            }

            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully.', 'b2brouter-woocommerce') . '</p></div>';
        }

        ?>
        <div class="wrap b2brouter-settings">
            <h1><?php esc_html_e('B2Brouter Settings', 'b2brouter-woocommerce'); ?></h1>

            <?php if (!$api_configured): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e('API key is not configured. Please enter your B2Brouter API key below.', 'b2brouter-woocommerce'); ?>
                        <a href="https://app.b2brouter.net" target="_blank"><?php esc_html_e('Get your API key', 'b2brouter-woocommerce'); ?></a>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="" class="b2brouter-form">
                <?php wp_nonce_field('b2brouter_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="b2brouter_api_key"><?php esc_html_e('API Key', 'b2brouter-woocommerce'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="b2brouter_api_key"
                                   name="b2brouter_api_key"
                                   value="<?php echo esc_attr($api_key); ?>"
                                   class="regular-text"
                                   placeholder="<?php esc_attr_e('Enter your B2Brouter API key', 'b2brouter-woocommerce'); ?>">
                            <button type="button" id="b2brouter_validate_key" class="button button-secondary">
                                <?php esc_html_e('Validate Key', 'b2brouter-woocommerce'); ?>
                            </button>
                            <span id="b2brouter_validation_result"></span>
                            <p class="description">
                                <?php esc_html_e('Enter your B2Brouter API key to enable invoice generation.', 'b2brouter-woocommerce'); ?>
                                <a href="https://app.b2brouter.net" target="_blank"><?php esc_html_e('Get your API key', 'b2brouter-woocommerce'); ?></a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Environment', 'b2brouter-woocommerce'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio"
                                           name="b2brouter_environment"
                                           value="staging"
                                           <?php checked($environment, 'staging'); ?>>
                                    <?php esc_html_e('Staging', 'b2brouter-woocommerce'); ?>
                                    <code>api-staging.b2brouter.net</code>
                                </label>
                                <p class="description"><?php esc_html_e('Use staging environment for testing', 'b2brouter-woocommerce'); ?></p>

                                <label>
                                    <input type="radio"
                                           name="b2brouter_environment"
                                           value="production"
                                           <?php checked($environment, 'production'); ?>>
                                    <?php esc_html_e('Production', 'b2brouter-woocommerce'); ?>
                                    <code>api.b2brouter.net</code>
                                </label>
                                <p class="description"><?php esc_html_e('Use production environment for live invoices', 'b2brouter-woocommerce'); ?></p>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Invoice Generation Mode', 'b2brouter-woocommerce'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio"
                                           name="b2brouter_invoice_mode"
                                           value="automatic"
                                           <?php checked($invoice_mode, 'automatic'); ?>>
                                    <?php esc_html_e('Automatic', 'b2brouter-woocommerce'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Generate invoice automatically when order is completed', 'b2brouter-woocommerce'); ?></p>

                                <label>
                                    <input type="radio"
                                           name="b2brouter_invoice_mode"
                                           value="manual"
                                           <?php checked($invoice_mode, 'manual'); ?>>
                                    <?php esc_html_e('Manual', 'b2brouter-woocommerce'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Generate invoice manually using a button in the order admin', 'b2brouter-woocommerce'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Invoice Numbering & Series', 'b2brouter-woocommerce'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="b2brouter_invoice_series_code"><?php esc_html_e('Invoice Series Code', 'b2brouter-woocommerce'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="b2brouter_invoice_series_code"
                                   name="b2brouter_invoice_series_code"
                                   value="<?php echo esc_attr($invoice_series_code); ?>"
                                   class="regular-text"
                                   placeholder="<?php esc_attr_e('e.g., INV, S01', 'b2brouter-woocommerce'); ?>">
                            <p class="description">
                                <?php esc_html_e('Define one invoice series code for all invoices. B2Brouter allows one series per invoice type. Leave empty to use B2Brouter default.', 'b2brouter-woocommerce'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="b2brouter_credit_note_series_code"><?php esc_html_e('Credit Note Series Code', 'b2brouter-woocommerce'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="b2brouter_credit_note_series_code"
                                   name="b2brouter_credit_note_series_code"
                                   value="<?php echo esc_attr($credit_note_series_code); ?>"
                                   class="regular-text"
                                   placeholder="<?php esc_attr_e('e.g., CN, R01', 'b2brouter-woocommerce'); ?>">
                            <p class="description">
                                <?php esc_html_e('Define one credit note series code for all credit notes. B2Brouter allows one series per type. Leave empty to use the same series as invoices.', 'b2brouter-woocommerce'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Invoice Numbering Pattern', 'b2brouter-woocommerce'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio"
                                           name="b2brouter_invoice_numbering_pattern"
                                           value="automatic"
                                           <?php checked($numbering_pattern, 'automatic'); ?>>
                                    <?php esc_html_e('Automatic (B2Brouter manages numbering)', 'b2brouter-woocommerce'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Let B2Brouter automatically assign invoice numbers', 'b2brouter-woocommerce'); ?></p>

                                <label>
                                    <input type="radio"
                                           name="b2brouter_invoice_numbering_pattern"
                                           value="woocommerce"
                                           <?php checked($numbering_pattern, 'woocommerce'); ?>>
                                    <?php esc_html_e('WooCommerce Order Number', 'b2brouter-woocommerce'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Use the WooCommerce order number as the invoice number', 'b2brouter-woocommerce'); ?></p>

                                <label>
                                    <input type="radio"
                                           name="b2brouter_invoice_numbering_pattern"
                                           value="sequential"
                                           <?php checked($numbering_pattern, 'sequential'); ?>>
                                    <?php esc_html_e('Sequential (plugin-managed)', 'b2brouter-woocommerce'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Plugin maintains sequential numbering per series (starts at 1)', 'b2brouter-woocommerce'); ?></p>

                                <label>
                                    <input type="radio"
                                           name="b2brouter_invoice_numbering_pattern"
                                           value="custom"
                                           <?php checked($numbering_pattern, 'custom'); ?>>
                                    <?php esc_html_e('Custom Pattern', 'b2brouter-woocommerce'); ?>
                                </label>
                                <br>
                                <input type="text"
                                       name="b2brouter_custom_numbering_pattern"
                                       value="<?php echo esc_attr($custom_pattern); ?>"
                                       class="regular-text"
                                       placeholder="{order_id}"
                                       style="margin-left: 25px; margin-top: 5px;">
                                <p class="description" style="margin-left: 25px;">
                                    <?php esc_html_e('Available placeholders: {order_id}, {order_number}, {year}, {month}, {day}. Note: Series code is defined separately above and will be prefixed automatically.', 'b2brouter-woocommerce'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('PDF Options', 'b2brouter-woocommerce'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Automatic PDF Caching', 'b2brouter-woocommerce'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox"
                                           name="b2brouter_auto_save_pdf"
                                           value="1"
                                           <?php checked($auto_save_pdf, true); ?>>
                                    <?php esc_html_e('Automatically download and cache PDF when invoice is generated', 'b2brouter-woocommerce'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When enabled, PDFs will be automatically downloaded from B2Brouter and stored locally when an invoice is generated. This improves performance and reduces API calls.', 'b2brouter-woocommerce'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('PDF Storage Location', 'b2brouter-woocommerce'); ?>
                        </th>
                        <td>
                            <code><?php echo esc_html($this->settings->get_pdf_storage_path()); ?></code>
                            <p class="description">
                                <?php esc_html_e('Invoice PDFs are stored in this directory. Files are protected from direct access via .htaccess rules.', 'b2brouter-woocommerce'); ?>
                            </p>
                            <?php
                            $storage_path = $this->settings->get_pdf_storage_path();
                            if (file_exists($storage_path)) {
                                $pdf_files = glob($storage_path . '/*.pdf');
                                $pdf_count = $pdf_files ? count($pdf_files) : 0;
                                $total_size = 0;
                                if ($pdf_files) {
                                    foreach ($pdf_files as $file) {
                                        $total_size += filesize($file);
                                    }
                                }
                                ?>
                                <p class="description">
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php
                                    printf(
                                        esc_html__('Currently storing %d PDF(s) using %s of disk space', 'b2brouter-woocommerce'),
                                        $pdf_count,
                                        size_format($total_size, 2)
                                    );
                                    ?>
                                </p>
                            <?php } else { ?>
                                <p class="description">
                                    <span class="dashicons dashicons-info" style="color: #72aee6;"></span>
                                    <?php esc_html_e('Directory will be created automatically when first PDF is downloaded', 'b2brouter-woocommerce'); ?>
                                </p>
                            <?php } ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Email PDF Attachments', 'b2brouter-woocommerce'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox"
                                           name="b2brouter_attach_to_order_completed"
                                           value="1"
                                           <?php checked($attach_to_completed, true); ?>>
                                    <?php esc_html_e('Attach PDF to Order Completed email (sent to customer)', 'b2brouter-woocommerce'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox"
                                           name="b2brouter_attach_to_customer_invoice"
                                           value="1"
                                           <?php checked($attach_to_invoice, true); ?>>
                                    <?php esc_html_e('Attach PDF to Customer Invoice email', 'b2brouter-woocommerce'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox"
                                           name="b2brouter_attach_to_refunded_order"
                                           value="1"
                                           <?php checked($attach_to_refunded, true); ?>>
                                    <?php esc_html_e('Attach PDF to Refunded Order email (credit note/rectificative)', 'b2brouter-woocommerce'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Automatically attach invoice PDFs to customer emails. "Order Completed" is sent when orders are fulfilled. "Customer Invoice" is sent for pending/unpaid orders or when manually sent from admin panel. "Refunded Order" is sent when orders are refunded.', 'b2brouter-woocommerce'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Automatic Cleanup', 'b2brouter-woocommerce'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox"
                                           name="b2brouter_auto_cleanup_enabled"
                                           value="1"
                                           <?php checked($auto_cleanup_enabled, true); ?>>
                                    <?php esc_html_e('Automatically delete old cached PDFs', 'b2brouter-woocommerce'); ?>
                                </label>
                                <br><br>
                                <label>
                                    <?php esc_html_e('Delete PDFs older than', 'b2brouter-woocommerce'); ?>
                                    <input type="number"
                                           name="b2brouter_auto_cleanup_days"
                                           value="<?php echo esc_attr($auto_cleanup_days); ?>"
                                           min="1"
                                           max="365"
                                           style="width: 80px;">
                                    <?php esc_html_e('days', 'b2brouter-woocommerce'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When enabled, PDFs older than the specified number of days will be automatically deleted daily via cron job. This helps manage disk space usage.', 'b2brouter-woocommerce'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Plugin Information', 'b2brouter-woocommerce'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Transaction Counter', 'b2brouter-woocommerce'); ?>
                        </th>
                        <td>
                            <strong><?php echo esc_html($transaction_count); ?></strong>
                            <p class="description"><?php esc_html_e('Total number of invoices generated from this plugin', 'b2brouter-woocommerce'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('B2Brouter Account', 'b2brouter-woocommerce'); ?>
                        </th>
                        <td>
                            <a href="https://app.b2brouter.net" class="button button-secondary" target="_blank">
                                <?php esc_html_e('Access B2Brouter Account Settings', 'b2brouter-woocommerce'); ?>
                            </a>
                            <p class="description"><?php esc_html_e('Configure advanced settings like transports, formats, and taxes in your B2Brouter account.', 'b2brouter-woocommerce'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="b2brouter_save_settings" class="button button-primary">
                        <?php esc_html_e('Save Settings', 'b2brouter-woocommerce'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render list of invoices page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_invoices_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'b2brouter-woocommerce'));
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('List of Invoices', 'b2brouter-woocommerce'); ?></h1>

            <?php
            // Create an instance of the list table
            $list_table = new Invoice_List_Table($this->settings, $this->invoice_generator);

            // Display bulk download notice if applicable
            $list_table->bulk_download_notice();

            // Prepare items
            $list_table->prepare_items();

            // Display the table
            ?>
            <form method="post">
                <?php
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * AJAX: Download invoice PDF
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_download_pdf() {
        // Verify nonce
        check_ajax_referer('b2brouter_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options') && !current_user_can('edit_shop_orders')) {
            wp_send_json_error(array(
                'message' => __('Permission denied', 'b2brouter-woocommerce')
            ));
        }

        // Get order ID
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(array(
                'message' => __('Invalid order ID', 'b2brouter-woocommerce')
            ));
        }

        // Get download mode
        $download_mode = isset($_POST['download']) && $_POST['download'] === 'download';

        // Stream PDF directly (this will exit)
        $this->invoice_generator->stream_invoice_pdf($order_id, $download_mode);
    }
}
