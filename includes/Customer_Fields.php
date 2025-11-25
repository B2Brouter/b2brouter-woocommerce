<?php
/**
 * Customer Fields Class
 *
 * Handles custom customer fields including TIN/VAT number
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
 * Customer Fields class
 *
 * Manages TIN/VAT number field in checkout, user profile, and admin
 *
 * @since 1.0.0
 */
class Customer_Fields {

    /**
     * TIN field key
     *
     * @since 1.0.0
     * @var string
     */
    const TIN_FIELD_KEY = 'billing_tin';

    /**
     * TIN meta key for orders
     *
     * @since 1.0.0
     * @var string
     */
    const TIN_META_KEY = '_billing_tin';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function init_hooks() {
        // === BLOCK CHECKOUT SUPPORT (WooCommerce 8.6+) ===
        // Block checkout field registration is handled in b2brouter-woocommerce.php
        // due to timing requirements (must register during woocommerce_blocks_loaded)

        // === CLASSIC CHECKOUT SUPPORT ===
        // Add TIN field to all checkout fields (works for both separate and merged billing/shipping)
        add_filter('woocommerce_checkout_fields', array($this, 'add_tin_to_checkout_fields'), 10, 1);

        // Also add to billing fields filter (for compatibility)
        add_filter('woocommerce_billing_fields', array($this, 'add_tin_to_billing_fields'), 10, 1);

        // === ADMIN SUPPORT ===
        // Add TIN field to admin order billing fields (manual order creation/editing)
        add_filter('woocommerce_admin_billing_fields', array($this, 'add_tin_to_admin_billing_fields'), 10, 1);

        // Save TIN to order meta (classic checkout)
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_tin_to_order_meta'), 10, 1);

        // Save TIN from block checkout to standard billing meta
        add_action('woocommerce_set_additional_field_value', array($this, 'save_tin_from_blocks'), 10, 4);

        // Save TIN when editing order in admin
        add_action('woocommerce_process_shop_order_meta', array($this, 'save_tin_from_admin_order'), 10, 2);

        // Add TIN to user profile fields
        add_filter('woocommerce_customer_meta_fields', array($this, 'add_tin_to_customer_meta'), 10, 1);

        // Save TIN field from user profile
        add_action('woocommerce_checkout_update_user_meta', array($this, 'save_tin_to_user_meta'), 10, 2);

        // Add TIN to My Account billing address fields
        add_filter('woocommerce_my_account_my_address_formatted_address', array($this, 'add_tin_to_formatted_address'), 10, 3);

        // Make TIN field available in customer object
        add_filter('woocommerce_customer_get_billing_tin', array($this, 'get_customer_tin'), 10, 2);
    }

    /**
     * Add TIN field to checkout fields (works for both merged and separate billing/shipping)
     *
     * @since 1.0.0
     * @param array $fields All checkout fields (billing, shipping, account, order)
     * @return array Modified checkout fields
     */
    public function add_tin_to_checkout_fields($fields) {
        // Define TIN field
        $tin_field = array(
            'label'       => __('Tax ID / VAT Number', 'b2brouter-woocommerce'),
            'placeholder' => __('Tax Identification Number', 'b2brouter-woocommerce'),
            'required'    => false,
            'class'       => array('form-row-wide'),
            'clear'       => true,
            'priority'    => 35,
            'description' => __('Enter your business Tax ID or VAT number for invoicing (optional)', 'b2brouter-woocommerce'),
        );

        // Add to billing fields group
        if (isset($fields['billing'])) {
            $fields['billing'][self::TIN_FIELD_KEY] = $tin_field;
        }

        return $fields;
    }

    /**
     * Add TIN field to billing fields at checkout (legacy method for compatibility)
     *
     * @since 1.0.0
     * @param array $fields Billing fields
     * @return array Modified billing fields
     */
    public function add_tin_to_billing_fields($fields) {
        // Define TIN field
        $tin_field = array(
            'label'       => __('Tax ID / VAT Number', 'b2brouter-woocommerce'),
            'placeholder' => __('Tax Identification Number', 'b2brouter-woocommerce'),
            'required'    => false,
            'class'       => array('form-row-wide'),
            'clear'       => true,
            'priority'    => 35,
            'description' => __('Enter your business Tax ID or VAT number for invoicing (optional)', 'b2brouter-woocommerce'),
        );

        // If company field exists, insert TIN after it
        if (isset($fields['billing_company'])) {
            $new_fields = array();
            foreach ($fields as $key => $field) {
                $new_fields[$key] = $field;
                // Insert TIN after company field
                if ($key === 'billing_company') {
                    $new_fields[self::TIN_FIELD_KEY] = $tin_field;
                }
            }
            return $new_fields;
        }

        // Otherwise, just add it to the fields array with priority
        $fields[self::TIN_FIELD_KEY] = $tin_field;
        return $fields;
    }

    /**
     * Add TIN field to admin order billing fields
     *
     * @since 1.0.0
     * @param array $fields Admin billing fields
     * @return array Modified fields
     */
    public function add_tin_to_admin_billing_fields($fields) {
        // Add TIN field to admin order form
        $fields['tin'] = array(
            'label' => __('Tax ID / VAT Number', 'b2brouter-woocommerce'),
            'show'  => true,
        );

        return $fields;
    }

    /**
     * Save TIN to order meta (for classic checkout)
     *
     * Note: Block checkout automatically saves registered fields to order meta,
     * so this method is only needed for classic checkout compatibility.
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @return void
     */
    public function save_tin_to_order_meta($order_id) {
        if (isset($_POST[self::TIN_FIELD_KEY]) && !empty($_POST[self::TIN_FIELD_KEY])) {
            $tin = sanitize_text_field($_POST[self::TIN_FIELD_KEY]);
            update_post_meta($order_id, self::TIN_META_KEY, $tin);
        }
    }

    /**
     * Save TIN from block checkout to standard billing meta
     *
     * Maps the b2brouter/tin field from block checkout to _billing_tin
     * so it's consistent with classic checkout.
     *
     * @since 1.0.0
     * @param string $key Field key
     * @param mixed $value Field value
     * @param string $group Field group
     * @param \WC_Order $wc_object Order object
     * @return void
     */
    public function save_tin_from_blocks($key, $value, $group, $wc_object) {
        // Check if this is our TIN field from block checkout
        if ($key === 'b2brouter/tin') {
            // Save to standard billing TIN meta key
            if (!empty($value)) {
                $wc_object->update_meta_data(self::TIN_META_KEY, sanitize_text_field($value));
            }

            // Delete the WooCommerce Blocks meta to avoid duplication in admin
            $wc_object->delete_meta_data('_wc_other/b2brouter/tin');
        }
    }

    /**
     * Save TIN when editing order in admin
     *
     * @since 1.0.0
     * @param int $order_id Order ID
     * @param \WP_Post $post Post object
     * @return void
     */
    public function save_tin_from_admin_order($order_id, $post) {
        if (isset($_POST['_billing_tin'])) {
            $tin = sanitize_text_field($_POST['_billing_tin']);
            update_post_meta($order_id, self::TIN_META_KEY, $tin);
        }
    }

    /**
     * Add TIN to customer meta fields in admin
     *
     * @since 1.0.0
     * @param array $fields Customer meta fields
     * @return array Modified fields
     */
    public function add_tin_to_customer_meta($fields) {
        // Add TIN to billing fields section
        $fields['billing']['fields'][self::TIN_FIELD_KEY] = array(
            'label'       => __('Tax ID / VAT Number', 'b2brouter-woocommerce'),
            'description' => __('Customer Tax Identification Number or VAT number', 'b2brouter-woocommerce'),
        );

        return $fields;
    }

    /**
     * Save TIN to user meta during checkout
     *
     * @since 1.0.0
     * @param int $user_id User ID
     * @param array $data Posted data
     * @return void
     */
    public function save_tin_to_user_meta($user_id, $data) {
        if (isset($data[self::TIN_FIELD_KEY]) && !empty($data[self::TIN_FIELD_KEY])) {
            $tin = sanitize_text_field($data[self::TIN_FIELD_KEY]);
            update_user_meta($user_id, self::TIN_FIELD_KEY, $tin);
        }
    }

    /**
     * Add TIN to formatted address in My Account
     *
     * @since 1.0.0
     * @param array $address Formatted address
     * @param int $customer_id Customer ID
     * @param string $address_type Address type (billing or shipping)
     * @return array Modified address
     */
    public function add_tin_to_formatted_address($address, $customer_id, $address_type) {
        // Only add TIN to billing address
        if ($address_type !== 'billing') {
            return $address;
        }

        $tin = get_user_meta($customer_id, self::TIN_FIELD_KEY, true);

        if (!empty($tin)) {
            $address['tin'] = $tin;
        }

        return $address;
    }

    /**
     * Get customer TIN from order
     *
     * @since 1.0.0
     * @param \WC_Order $order Order object
     * @return string TIN value or empty string
     */
    public static function get_order_tin($order) {
        if (!$order) {
            return '';
        }

        return $order->get_meta(self::TIN_META_KEY);
    }

    /**
     * Get customer TIN from customer object
     *
     * @since 1.0.0
     * @param string $value Current value
     * @param \WC_Customer $customer Customer object
     * @return string TIN value
     */
    public function get_customer_tin($value, $customer) {
        if (!$customer) {
            return '';
        }

        $user_id = $customer->get_id();
        if ($user_id) {
            return get_user_meta($user_id, self::TIN_FIELD_KEY, true);
        }

        return '';
    }

    /**
     * Get TIN field key
     *
     * @since 1.0.0
     * @return string Field key
     */
    public static function get_tin_field_key() {
        return self::TIN_FIELD_KEY;
    }

    /**
     * Get TIN meta key
     *
     * @since 1.0.0
     * @return string Meta key
     */
    public static function get_tin_meta_key() {
        return self::TIN_META_KEY;
    }
}
