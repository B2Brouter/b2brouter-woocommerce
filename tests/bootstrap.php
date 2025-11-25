<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up the testing environment by loading Composer autoloader
 * and defining WordPress functions that are used by the plugin.
 *
 * @package B2Brouter\WooCommerce\Tests
 */

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define ABSPATH constant for WordPress compatibility
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

// Mock WordPress functions used by the plugin
// Global options storage for tests
global $wp_options;
$wp_options = array();

if (!function_exists('get_option')) {
    /**
     * Mock get_option function
     *
     * @param string $option Option name
     * @param mixed $default Default value
     * @return mixed Option value
     */
    function get_option($option, $default = false) {
        global $wp_options;
        return isset($wp_options[$option]) ? $wp_options[$option] : $default;
    }
}

if (!function_exists('update_option')) {
    /**
     * Mock update_option function
     *
     * @param string $option Option name
     * @param mixed $value Option value
     * @return bool Success
     */
    function update_option($option, $value) {
        global $wp_options;
        $wp_options[$option] = $value;
        return true;
    }
}

if (!function_exists('add_option')) {
    /**
     * Mock add_option function
     *
     * @param string $option Option name
     * @param mixed $value Option value
     * @return bool Success
     */
    function add_option($option, $value) {
        return update_option($option, $value);
    }
}

if (!function_exists('delete_option')) {
    /**
     * Mock delete_option function
     *
     * @param string $option Option name
     * @return bool Success
     */
    function delete_option($option) {
        global $wp_options;
        if (isset($wp_options[$option])) {
            unset($wp_options[$option]);
            return true;
        }
        return false;
    }
}

if (!function_exists('sanitize_text_field')) {
    /**
     * Mock sanitize_text_field function
     *
     * @param string $str String to sanitize
     * @return string Sanitized string
     */
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('__')) {
    /**
     * Mock translation function
     *
     * @param string $text Text to translate
     * @param string $domain Text domain
     * @return string Translated text
     */
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    /**
     * Mock esc_html function
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    /**
     * Mock esc_attr function
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    /**
     * Mock esc_url function
     *
     * @param string $url URL to escape
     * @return string Escaped URL
     */
    function esc_url($url) {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('current_time')) {
    /**
     * Mock current_time function
     *
     * @param string $type Type of time ('mysql' or 'timestamp')
     * @return string|int Current time
     */
    function current_time($type) {
        if ($type === 'mysql') {
            return date('Y-m-d H:i:s');
        }
        return time();
    }
}

if (!function_exists('get_locale')) {
    /**
     * Mock get_locale function
     *
     * @return string Locale
     */
    function get_locale() {
        return 'en_US';
    }
}

if (!function_exists('wc_get_order')) {
    /**
     * Mock wc_get_order function
     *
     * @param int $order_id Order ID
     * @return WC_Order|false Returns mock order or false
     */
    function wc_get_order($order_id) {
        global $wc_mock_orders;
        if (isset($wc_mock_orders[$order_id])) {
            return $wc_mock_orders[$order_id];
        }
        return null;
    }
}

if (!function_exists('error_log')) {
    /**
     * Mock error_log function
     *
     * @param string $message Message to log
     * @return bool Success
     */
    function error_log($message) {
        // Silence errors in tests
        return true;
    }
}

if (!function_exists('wp_die')) {
    /**
     * Mock wp_die function
     *
     * @param string $message Message
     * @param string $title Title
     * @param array $args Arguments
     * @return void
     */
    function wp_die($message = '', $title = '', $args = array()) {
        // In tests, just exit silently
        throw new Exception('wp_die called: ' . $message);
    }
}

// Global storage for WordPress actions and filters
global $wp_actions, $wp_filters, $wp_meta_boxes;
$wp_actions = array();
$wp_filters = array();
$wp_meta_boxes = array();

if (!function_exists('add_action')) {
    /**
     * Mock add_action function
     *
     * @param string $hook Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority
     * @param int $accepted_args Accepted args
     * @return bool Success
     */
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        global $wp_actions;
        if (!isset($wp_actions[$hook])) {
            $wp_actions[$hook] = array();
        }
        $wp_actions[$hook][] = array('callback' => $callback, 'priority' => $priority);
        return true;
    }
}

if (!function_exists('add_filter')) {
    /**
     * Mock add_filter function
     *
     * @param string $hook Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority
     * @param int $accepted_args Accepted args
     * @return bool Success
     */
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        global $wp_filters;
        if (!isset($wp_filters[$hook])) {
            $wp_filters[$hook] = array();
        }
        $wp_filters[$hook][] = array('callback' => $callback, 'priority' => $priority);
        return true;
    }
}

if (!function_exists('add_meta_box')) {
    /**
     * Mock add_meta_box function
     *
     * @param string $id ID
     * @param string $title Title
     * @param callable $callback Callback
     * @param string $screen Screen
     * @param string $context Context
     * @param string $priority Priority
     * @return void
     */
    function add_meta_box($id, $title, $callback, $screen, $context = 'advanced', $priority = 'default') {
        global $wp_meta_boxes;
        if (!isset($wp_meta_boxes[$screen])) {
            $wp_meta_boxes[$screen] = array();
        }
        $wp_meta_boxes[$screen][$id] = array(
            'id' => $id,
            'title' => $title,
            'callback' => $callback,
            'context' => $context,
            'priority' => $priority
        );
    }
}

if (!function_exists('add_query_arg')) {
    /**
     * Mock add_query_arg function
     *
     * @param array $args Arguments
     * @param string $url URL
     * @return string Modified URL
     */
    function add_query_arg($args, $url) {
        $query = http_build_query($args);
        $separator = (strpos($url, '?') === false) ? '?' : '&';
        return $url . $separator . $query;
    }
}

if (!function_exists('admin_url')) {
    /**
     * Mock admin_url function
     *
     * @param string $path Path
     * @return string URL
     */
    function admin_url($path = '') {
        return 'http://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('_n')) {
    /**
     * Mock _n (plural translation) function
     *
     * @param string $single Singular text
     * @param string $plural Plural text
     * @param int $number Number
     * @param string $domain Domain
     * @return string Text
     */
    function _n($single, $plural, $number, $domain = 'default') {
        return $number == 1 ? $single : $plural;
    }
}

if (!function_exists('esc_html_e')) {
    /**
     * Mock esc_html_e function
     *
     * @param string $text Text to escape and echo
     * @param string $domain Text domain
     * @return void
     */
    function esc_html_e($text, $domain = 'default') {
        echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr__')) {
    /**
     * Mock esc_attr__ function
     *
     * @param string $text Text to escape
     * @param string $domain Text domain
     * @return string Escaped text
     */
    function esc_attr__($text, $domain = 'default') {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    /**
     * Mock esc_html__ function
     *
     * @param string $text Text to translate and escape
     * @param string $domain Text domain
     * @return string Escaped text
     */
    function esc_html__($text, $domain = 'default') {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('add_menu_page')) {
    /**
     * Mock add_menu_page function
     *
     * @return void
     */
    function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null) {
        global $wp_menu_pages;
        if (!isset($wp_menu_pages)) {
            $wp_menu_pages = array();
        }
        $wp_menu_pages[$menu_slug] = compact('page_title', 'menu_title', 'capability', 'function');
    }
}

if (!function_exists('add_submenu_page')) {
    /**
     * Mock add_submenu_page function
     *
     * @return void
     */
    function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '') {
        global $wp_submenu_pages;
        if (!isset($wp_submenu_pages)) {
            $wp_submenu_pages = array();
        }
        if (!isset($wp_submenu_pages[$parent_slug])) {
            $wp_submenu_pages[$parent_slug] = array();
        }
        $wp_submenu_pages[$parent_slug][$menu_slug] = compact('page_title', 'menu_title', 'capability', 'function');
    }
}

if (!function_exists('register_setting')) {
    /**
     * Mock register_setting function
     *
     * @return void
     */
    function register_setting($option_group, $option_name, $args = array()) {
        // Do nothing in tests
    }
}

if (!function_exists('wp_send_json_success')) {
    /**
     * Mock wp_send_json_success function
     *
     * @param mixed $data Data to send
     * @return void
     */
    function wp_send_json_success($data = null) {
        echo json_encode(array('success' => true, 'data' => $data));
        exit;
    }
}

if (!function_exists('wp_send_json_error')) {
    /**
     * Mock wp_send_json_error function
     *
     * @param mixed $data Data to send
     * @return void
     */
    function wp_send_json_error($data = null) {
        echo json_encode(array('success' => false, 'data' => $data));
        exit;
    }
}

if (!function_exists('check_ajax_referer')) {
    /**
     * Mock check_ajax_referer function
     *
     * @return bool Success
     */
    function check_ajax_referer($action = -1, $query_arg = false, $die = true) {
        return true;
    }
}

if (!function_exists('wp_create_nonce')) {
    /**
     * Mock wp_create_nonce function
     *
     * @param string $action Action
     * @return string Nonce
     */
    function wp_create_nonce($action = -1) {
        return 'test-nonce-' . md5($action);
    }
}

if (!function_exists('wp_nonce_field')) {
    /**
     * Mock wp_nonce_field function
     *
     * @return void
     */
    function wp_nonce_field($action = -1, $name = "_wpnonce", $referer = true, $echo = true) {
        $nonce = wp_create_nonce($action);
        $output = '<input type="hidden" name="' . $name . '" value="' . $nonce . '" />';
        if ($echo) {
            echo $output;
        }
        return $output;
    }
}

if (!function_exists('settings_fields')) {
    /**
     * Mock settings_fields function
     *
     * @param string $option_group Option group
     * @return void
     */
    function settings_fields($option_group) {
        echo '<input type="hidden" name="option_page" value="' . esc_attr($option_group) . '" />';
    }
}

if (!function_exists('submit_button')) {
    /**
     * Mock submit_button function
     *
     * @param string $text Button text
     * @param string $type Button type
     * @param string $name Button name
     * @param bool $wrap Wrap in paragraph
     * @param array $other_attributes Other attributes
     * @return void
     */
    function submit_button($text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null) {
        if (is_null($text)) {
            $text = 'Save Changes';
        }
        $button = '<button type="submit" name="' . esc_attr($name) . '" class="button button-' . esc_attr($type) . '">' . esc_html($text) . '</button>';
        if ($wrap) {
            $button = '<p>' . $button . '</p>';
        }
        echo $button;
    }
}

if (!function_exists('current_user_can')) {
    /**
     * Mock current_user_can function
     *
     * @param string $capability Capability
     * @return bool Always true in tests
     */
    function current_user_can($capability) {
        return true;
    }
}

if (!function_exists('esc_attr_e')) {
    /**
     * Mock esc_attr_e function
     *
     * @param string $text Text to escape and echo
     * @param string $domain Text domain
     * @return void
     */
    function esc_attr_e($text, $domain = 'default') {
        echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('selected')) {
    /**
     * Mock selected function
     *
     * @param mixed $selected Selected value
     * @param mixed $current Current value
     * @param bool $echo Echo or return
     * @return string Selected attribute
     */
    function selected($selected, $current, $echo = true) {
        $result = ($selected == $current) ? ' selected="selected"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists('checked')) {
    /**
     * Mock checked function
     *
     * @param mixed $checked Checked value
     * @param mixed $current Current value
     * @param bool $echo Echo or return
     * @return string Checked attribute
     */
    function checked($checked, $current, $echo = true) {
        $result = ($checked == $current) ? ' checked="checked"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

// Global storage for cron events (Phase 5)
global $wp_cron_events;
$wp_cron_events = array();

if (!function_exists('wp_next_scheduled')) {
    /**
     * Mock wp_next_scheduled function
     *
     * @param string $hook Hook name
     * @param array $args Arguments
     * @return false|int Timestamp or false
     */
    function wp_next_scheduled($hook, $args = array()) {
        global $wp_cron_events;
        if (isset($wp_cron_events[$hook])) {
            return $wp_cron_events[$hook]['timestamp'];
        }
        return false;
    }
}

if (!function_exists('wp_schedule_event')) {
    /**
     * Mock wp_schedule_event function
     *
     * @param int $timestamp Timestamp
     * @param string $recurrence Recurrence
     * @param string $hook Hook name
     * @param array $args Arguments
     * @return bool Success
     */
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = array()) {
        global $wp_cron_events;
        $wp_cron_events[$hook] = array(
            'timestamp' => $timestamp,
            'recurrence' => $recurrence,
            'args' => $args
        );
        return true;
    }
}

if (!function_exists('wp_upload_dir')) {
    /**
     * Mock wp_upload_dir function
     *
     * @param string $time Time
     * @return array Upload directory info
     */
    function wp_upload_dir($time = null) {
        $upload_path = sys_get_temp_dir() . '/wp-content/uploads';
        return array(
            'path' => $upload_path . '/2025/11',
            'url' => 'http://example.com/wp-content/uploads/2025/11',
            'subdir' => '/2025/11',
            'basedir' => $upload_path,
            'baseurl' => 'http://example.com/wp-content/uploads',
            'error' => false,
        );
    }
}

// Constants for Phase 5
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

// Define B2BROUTER_WC_PLUGIN_BASENAME constant for plugin action links
if (!defined('B2BROUTER_WC_PLUGIN_BASENAME')) {
    define('B2BROUTER_WC_PLUGIN_BASENAME', 'b2brouter-woocommerce/b2brouter-woocommerce.php');
}

// Global storage for mock orders
global $mock_orders;
$mock_orders = array();

// Mock WooCommerce Order class
if (!class_exists('WC_Order')) {
    /**
     * Mock WC_Order class
     */
    class WC_Order {
        private $id;
        private $data = array();
        private $meta_data = array();
        private $items = array();
        private $notes = array();

        public function __construct($order_id = 0) {
            $this->id = $order_id;
            // Default data
            $this->data = array(
                'billing_first_name' => 'John',
                'billing_last_name' => 'Doe',
                'billing_company' => '',
                'billing_email' => 'john@example.com',
                'billing_address_1' => '123 Main St',
                'billing_city' => 'New York',
                'billing_postcode' => '10001',
                'billing_country' => 'US',
                'currency' => 'USD',
                'order_number' => $order_id,
                'shipping_total' => 0,
                'shipping_tax' => 0,
            );
        }

        public function get_id() { return $this->id; }
        public function get_type() { return 'shop_order'; }
        public function get_billing_first_name() { return $this->data['billing_first_name']; }
        public function get_billing_last_name() { return $this->data['billing_last_name']; }
        public function get_billing_company() { return $this->data['billing_company']; }
        public function get_billing_email() { return $this->data['billing_email']; }
        public function get_billing_address_1() { return $this->data['billing_address_1']; }
        public function get_billing_address_2() { return isset($this->data['billing_address_2']) ? $this->data['billing_address_2'] : ''; }
        public function get_billing_city() { return $this->data['billing_city']; }
        public function get_billing_postcode() { return $this->data['billing_postcode']; }
        public function get_billing_country() { return $this->data['billing_country']; }
        public function get_currency() { return $this->data['currency']; }
        public function get_order_number() { return $this->data['order_number']; }
        public function get_shipping_total() { return $this->data['shipping_total']; }
        public function get_shipping_tax() { return $this->data['shipping_tax']; }

        public function set_billing_first_name($value) { $this->data['billing_first_name'] = $value; }
        public function set_billing_last_name($value) { $this->data['billing_last_name'] = $value; }
        public function set_billing_company($value) { $this->data['billing_company'] = $value; }
        public function set_billing_country($value) { $this->data['billing_country'] = $value; }
        public function set_shipping_total($value) { $this->data['shipping_total'] = $value; }
        public function set_shipping_tax($value) { $this->data['shipping_tax'] = $value; }

        public function get_meta($key, $single = true) {
            return isset($this->meta_data[$key]) ? $this->meta_data[$key] : '';
        }

        public function add_meta_data($key, $value, $unique = false) {
            $this->meta_data[$key] = $value;
        }

        public function update_meta_data($key, $value, $meta_id = '') {
            $this->meta_data[$key] = $value;
        }

        public function delete_meta_data($key) {
            unset($this->meta_data[$key]);
        }

        public function get_meta_data() {
            $meta_objects = array();
            foreach ($this->meta_data as $key => $value) {
                $meta_objects[] = (object) array('key' => $key, 'value' => $value);
            }
            return $meta_objects;
        }

        public function add_order_note($note) {
            $this->notes[] = $note;
        }

        public function save() {
            return true;
        }

        public function get_items($type = 'line_item') {
            return $this->items;
        }

        public function add_item($item) {
            $this->items[] = $item;
        }

        public function get_item_subtotal($item, $inc_tax = false, $round = true) {
            // Calculate from item total and quantity
            if (method_exists($item, 'get_total') && method_exists($item, 'get_quantity')) {
                $total = $item->get_total();
                $quantity = $item->get_quantity();
                if ($quantity != 0) {
                    return abs($total / $quantity);
                }
            }
            return 10.00; // Fallback mock price
        }

        public function get_refunds() {
            return array(); // Return empty array by default
        }

        public function get_edit_order_url() {
            return admin_url('post.php?post=' . $this->get_id() . '&action=edit');
        }
    }
}

// Mock WC_Order_Item_Product class
if (!class_exists('WC_Order_Item_Product')) {
    class WC_Order_Item_Product {
        private $data = array();
        private $product = null;

        public function __construct($name = 'Test Product') {
            $this->data = array(
                'name' => $name,
                'quantity' => 1,
                'total' => 10.00,
                'taxes' => array('total' => array()),
            );
        }

        public function get_name() { return $this->data['name']; }
        public function get_quantity() { return $this->data['quantity']; }
        public function get_total() { return $this->data['total']; }
        public function get_taxes() { return $this->data['taxes']; }
        public function get_product() { return $this->product; }

        public function set_quantity($qty) { $this->data['quantity'] = $qty; }
        public function set_total($total) { $this->data['total'] = $total; }
        public function set_taxes($taxes) { $this->data['taxes'] = $taxes; }
        public function set_product($product) { $this->product = $product; }
    }
}

// Mock WC_Product class
if (!class_exists('WC_Product')) {
    class WC_Product {
        private $data = array();

        public function __construct() {
            $this->data = array(
                'tax_status' => 'taxable',
                'tax_class' => '',
            );
        }

        public function get_tax_status() { return $this->data['tax_status']; }
        public function get_tax_class() { return $this->data['tax_class']; }

        public function set_tax_status($status) { $this->data['tax_status'] = $status; }
        public function set_tax_class($class) { $this->data['tax_class'] = $class; }
    }
}

// Mock B2Brouter SDK classes for testing with REAL API payloads
// Must use eval to create namespaced class at runtime
if (!class_exists('B2BRouter\B2BRouterClient')) {
    eval('
    namespace B2BRouter {
        namespace HttpClient {
            class MockHttpClient {
                public function request($method, $url, $headers, $body, $timeout) {
                    // Mock GET /accounts endpoint (API key validation)
                    if ($method === "GET" && strpos($url, "/accounts") !== false) {
                        return [
                            "status" => 200,
                            "body" => json_encode([
                                "accounts" => [
                                    [
                                        "id" => 211162,
                                        "tin_value" => "ES01738726H",
                                        "tin_scheme" => 9920,
                                        "cin_value" => null,
                                        "cin_scheme" => null,
                                        "name" => "WP test",
                                        "address" => "casa meva",
                                        "address2" => null,
                                        "city" => "Barcelona",
                                        "postalcode" => "08080",
                                        "province" => "Barcelona",
                                        "country" => "es",
                                        "currency" => "EUR",
                                        "contact_person" => null,
                                        "phone" => null,
                                        "email" => "jtorrents@b2brouter.net",
                                        "rounding_method" => "half_up",
                                        "round_before_sum" => false,
                                        "apply_taxes_per_line" => false,
                                        "registered_for_empl_tax" => false,
                                        "transport_type_code" => null,
                                        "document_type_code" => null,
                                        "has_logo" => false,
                                        "archived" => false,
                                        "created_at" => "2025-11-13T09:45:30.000Z",
                                        "updated_at" => "2025-11-13T09:47:26.000Z",
                                        "transactions_count" => 11,
                                        "transactions_count_previous_period" => 0,
                                        "transactions_limit" => 100
                                    ]
                                ],
                                "total_count" => 1,
                                "offset" => 0,
                                "limit" => 1
                            ]),
                            "headers" => []
                        ];
                    }

                    // Mock other endpoints
                    return [
                        "status" => 200,
                        "body" => json_encode([]),
                        "headers" => []
                    ];
                }
            }
        }

        namespace Exception {
            class ResourceNotFoundException extends \Exception {}
            class AuthenticationException extends \Exception {}
        }

        class B2BRouterClient {
            public $invoices;
            private $apiKey;
            private $apiBase = "https://api-staging.b2brouter.net";
            private $apiVersion = "2025-10-13";
            private $httpClient;
            private $timeout = 80;

            public function __construct($api_key, array $options = []) {
                $this->apiKey = $api_key;
                if (isset($options["api_base"])) {
                    $this->apiBase = $options["api_base"];
                }
                $this->httpClient = new HttpClient\MockHttpClient();

                $this->invoices = new class {
                    public function create($account, $params) {
                        // Return REAL API payload structure
                        return [
                            "id" => 354754,
                            "type" => "IssuedInvoice",
                            "number" => "INV-ES-2025-00078",
                            "series_code" => null,
                            "state" => "new",
                            "account" => [
                                "id" => 211162,
                                "name" => "WP test"
                            ],
                            "company" => [
                                "id" => 27176,
                                "name" => "WP test",
                                "tin_value" => "ES01738726H",
                                "country" => "es"
                            ],
                            "contact" => [
                                "id" => 1313321399,
                                "name" => "test customer",
                                "email" => "jtorrents@b2brouter.net"
                            ],
                            "date" => "2025-11-20",
                            "due_date" => "2025-12-20",
                            "subtotal" => 200.0,
                            "total" => 200.0,
                            "currency" => "EUR",
                            "payable_amount" => 200.0,
                            "extra_info" => "WooCommerce Order #78",
                            "created_at" => "2025-11-20T10:14:24.000Z"
                        ];
                    }

                    public function send($id) {
                        return true;
                    }

                    public function downloadPdf($invoice_id) {
                        // Return REAL PDF binary data (simplified for testing)
                        return "%PDF-1.5\n%¿÷¢þ\n1 0 obj\n<< /Type /Catalog >>\nendobj\nstartxref\n%%EOF";
                    }

                    public function all($account, $params) {
                        return [];
                    }
                };
            }

            public function getApiKey() {
                return $this->apiKey;
            }

            public function getApiBase() {
                return $this->apiBase;
            }

            public function getApiVersion() {
                return $this->apiVersion;
            }

            public function getHttpClient() {
                return $this->httpClient;
            }

            public function getTimeout() {
                return $this->timeout;
            }
        }
    }
    ');
}

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    /**
     * Mock WP_Error class
     */
    class WP_Error {
        private $errors = array();

        public function add($code, $message, $data = '') {
            $this->errors[$code] = array(
                'message' => $message,
                'data' => $data
            );
        }

        public function get_error_messages($code = '') {
            if (empty($code)) {
                $messages = array();
                foreach ($this->errors as $error) {
                    $messages[] = $error['message'];
                }
                return $messages;
            }
            return isset($this->errors[$code]) ? array($this->errors[$code]['message']) : array();
        }

        public function has_errors() {
            return !empty($this->errors);
        }
    }
}

// Mock WC_Customer class
if (!class_exists('WC_Customer')) {
    /**
     * Mock WC_Customer class
     */
    class WC_Customer {
        private $id;
        private $data = array();

        public function __construct($customer_id = 0) {
            $this->id = $customer_id;
        }

        public function get_id() {
            return $this->id;
        }

        public function set_id($id) {
            $this->id = $id;
        }
    }
}

// Mock WC_Order_Refund class
if (!class_exists('WC_Order_Refund')) {
    /**
     * Mock WC_Order_Refund class
     */
    class WC_Order_Refund extends WC_Order {
        private $parent_id = 0;
        private $reason = '';
        private $items = array();

        public function get_type() {
            return 'shop_order_refund';
        }

        public function get_parent_id() {
            return $this->parent_id;
        }

        public function set_parent_id($parent_id) {
            $this->parent_id = $parent_id;
        }

        public function get_reason() {
            return $this->reason;
        }

        public function set_reason($reason) {
            $this->reason = $reason;
        }

        public function get_items($type = 'line_item') {
            return $this->items;
        }

        public function set_items($items) {
            $this->items = $items;
        }
    }
}

// Global user meta storage
global $wp_user_meta;
$wp_user_meta = array();

if (!function_exists('get_user_meta')) {
    /**
     * Mock get_user_meta function
     *
     * @param int $user_id User ID
     * @param string $key Meta key
     * @param bool $single Return single value
     * @return mixed Meta value
     */
    function get_user_meta($user_id, $key = '', $single = false) {
        global $wp_user_meta;

        if (empty($key)) {
            return isset($wp_user_meta[$user_id]) ? $wp_user_meta[$user_id] : array();
        }

        if (!isset($wp_user_meta[$user_id][$key])) {
            return $single ? '' : array();
        }

        return $single ? $wp_user_meta[$user_id][$key] : array($wp_user_meta[$user_id][$key]);
    }
}

if (!function_exists('update_user_meta')) {
    /**
     * Mock update_user_meta function
     *
     * @param int $user_id User ID
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return bool Success
     */
    function update_user_meta($user_id, $key, $value) {
        global $wp_user_meta;

        if (!isset($wp_user_meta[$user_id])) {
            $wp_user_meta[$user_id] = array();
        }

        $wp_user_meta[$user_id][$key] = $value;
        return true;
    }
}

// Global $wp_filter for hook testing
global $wp_filter;
$wp_filter = array();

// Global mock orders storage for tests
global $wc_mock_orders;
$wc_mock_orders = array();
