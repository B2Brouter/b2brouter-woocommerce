<?php
/**
 * Plugin Name: B2Brouter for WooCommerce
 * Plugin URI: https://b2brouter.net
 * Description: Generate and send electronic invoices from WooCommerce orders using B2Brouter
 * Version: 0.9.2
 * Author: B2Brouter
 * Author URI: https://b2brouter.net
 * License: MIT
 * License URI: https://opensource.org/license/mit
 * Text Domain: b2brouter-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 *
 * @package B2Brouter\WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('B2BROUTER_WC_VERSION', '0.9.2');
define('B2BROUTER_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('B2BROUTER_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('B2BROUTER_WC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load Composer autoloader
if (file_exists(B2BROUTER_WC_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once B2BROUTER_WC_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Main Plugin Class
 *
 * @since 1.0.0
 */
class B2Brouter_WooCommerce {

    /**
     * Instance of this class
     *
     * @since 1.0.0
     * @var B2Brouter_WooCommerce
     */
    private static $instance = null;

    /**
     * Dependency container
     *
     * @since 1.0.0
     * @var array
     */
    private $container = array();

    /**
     * Get instance
     *
     * @since 1.0.0
     * @return B2Brouter_WooCommerce
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->init();

        // Register TIN field for block checkout EARLY (before woocommerce_blocks_loaded fires)
        add_action('woocommerce_blocks_loaded', array($this, 'register_block_checkout_fields'), 10);
    }

    /**
     * Initialize plugin
     *
     * @since 1.0.0
     * @return void
     */
    private function init() {
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Declare WooCommerce HPOS compatibility
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));

        // Load translations on init (WordPress 6.7+ recommendation)
        add_action('init', array($this, 'load_textdomain'), 5);

        // Check WooCommerce dependency and initialize plugin
        // Use plugins_loaded with priority 20 (after WooCommerce loads at priority 10)
        add_action('plugins_loaded', array($this, 'check_dependencies_and_init'), 20);
    }

    /**
     * Declare compatibility with WooCommerce HPOS
     *
     * @since 1.0.0
     * @return void
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );
        }
    }

    /**
     * Check dependencies and initialize plugin
     *
     * @since 1.0.0
     * @return void
     */
    public function check_dependencies_and_init() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Initialize plugin
        $this->init_plugin();
    }

    /**
     * Check if WooCommerce is active
     *
     * @since 1.0.0
     * @return bool True if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce') || in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }

    /**
     * Display admin notice when WooCommerce is missing
     *
     * @since 1.0.0
     * @return void
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('B2Brouter for WooCommerce requires WooCommerce to be installed and active.', 'b2brouter-woocommerce');
        echo '</p></div>';
    }

    /**
     * Initialize plugin dependencies and classes
     *
     * @since 1.0.0
     * @return void
     */
    private function init_plugin() {
        // Initialize dependency container
        $this->init_container();

        // Initialize classes (instantiate them)
        $this->get('settings');
        $this->get('admin');
        $this->get('order_handler');
        $this->get('customer');
        $this->get('customer_fields');
        $this->get('status_sync');
    }

    /**
     * Register additional checkout fields for WooCommerce Blocks
     *
     * @since 1.0.0
     * @return void
     */
    public function register_block_checkout_fields() {
        if (!function_exists('woocommerce_register_additional_checkout_field')) {
            return;
        }

        try {
            // Note: Using plain English strings here instead of __() to avoid translation loading
            // before 'init' action. WooCommerce Blocks will handle translation on the frontend.
            woocommerce_register_additional_checkout_field(
                array(
                    'id' => 'b2brouter/tin',
                    'label' => 'Tax ID / VAT Number',
                    'optionalLabel' => 'Tax ID / VAT Number (optional)',
                    'location' => 'contact',
                    'type' => 'text',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                    // Hide from WooCommerce Blocks admin display (we show it via woocommerce_admin_billing_fields instead)
                    'show_in_order_confirmation' => false,
                )
            );
        } catch (\Exception $e) {
            // Silent fail - field registration errors are non-critical
        }
    }

    /**
     * Initialize dependency injection container
     *
     * @since 1.0.0
     * @return void
     */
    private function init_container() {
        // Register Settings (no dependencies)
        $this->container['settings'] = function() {
            return new \B2Brouter\WooCommerce\Settings();
        };

        // Register Invoice_Generator (depends on Settings)
        $this->container['invoice_generator'] = function() {
            return new \B2Brouter\WooCommerce\Invoice_Generator(
                $this->get('settings')
            );
        };

        // Register Admin (depends on Settings and Invoice_Generator)
        $this->container['admin'] = function() {
            return new \B2Brouter\WooCommerce\Admin(
                $this->get('settings'),
                $this->get('invoice_generator')
            );
        };

        // Register Order_Handler (depends on Settings and Invoice_Generator)
        $this->container['order_handler'] = function() {
            return new \B2Brouter\WooCommerce\Order_Handler(
                $this->get('settings'),
                $this->get('invoice_generator')
            );
        };

        // Register Customer (depends on Settings and Invoice_Generator)
        $this->container['customer'] = function() {
            return new \B2Brouter\WooCommerce\Customer(
                $this->get('settings'),
                $this->get('invoice_generator')
            );
        };

        // Register Customer_Fields (no dependencies)
        $this->container['customer_fields'] = function() {
            return new \B2Brouter\WooCommerce\Customer_Fields();
        };

        // Register Status_Sync (depends on Settings and Invoice_Generator)
        $this->container['status_sync'] = function() {
            return new \B2Brouter\WooCommerce\Status_Sync(
                $this->get('settings'),
                $this->get('invoice_generator')
            );
        };
    }

    /**
     * Get service from container
     *
     * @since 1.0.0
     * @param string $key Service key
     * @return mixed Service instance
     */
    private function get($key) {
        if (!isset($this->container[$key])) {
            throw new \Exception("Service '{$key}' not found in container.");
        }

        // If it's a callable, execute it once and cache the result
        if (is_callable($this->container[$key])) {
            $this->container[$key] = call_user_func($this->container[$key]);
        }

        return $this->container[$key];
    }

    /**
     * Load plugin textdomain
     *
     * @since 1.0.0
     * @return void
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'b2brouter-woocommerce',
            false,
            dirname(B2BROUTER_WC_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Plugin activation
     *
     * @since 1.0.0
     * @return void
     */
    public function activate() {
        // Set default options
        if (!get_option('b2brouter_activated')) {
            add_option('b2brouter_activated', '1');
            add_option('b2brouter_show_welcome', '1');
            add_option('b2brouter_invoice_mode', 'manual');
            add_option('b2brouter_transaction_count', 0);
        }

        // Initialize container to access Status_Sync
        $this->init_container();

        // Activate status sync cron
        $status_sync = $this->get('status_sync');
        $status_sync->activate();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     *
     * @since 1.0.0
     * @return void
     */
    public function deactivate() {
        // Initialize container to access Status_Sync
        $this->init_container();

        // Deactivate status sync cron
        $status_sync = $this->get('status_sync');
        $status_sync->deactivate();

        // Clean up if needed
        flush_rewrite_rules();
    }
}

// Initialize the plugin
function b2brouter_woocommerce_init() {
    return B2Brouter_WooCommerce::get_instance();
}

// Start the plugin
b2brouter_woocommerce_init();
