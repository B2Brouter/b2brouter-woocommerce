<?php
/**
 * Settings Handler
 *
 * @package B2Brouter\WooCommerce
 * @since 1.0.0
 */

namespace B2Brouter\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings class
 *
 * Handles all plugin settings and API key management
 *
 * @since 1.0.0
 */
class Settings {

    const OPTION_API_KEY = 'b2brouter_api_key';
    const OPTION_ACCOUNT_ID = 'b2brouter_account_id';
    const OPTION_ACCOUNT_NAME = 'b2brouter_account_name';
    const OPTION_ENVIRONMENT = 'b2brouter_environment';
    const OPTION_INVOICE_MODE = 'b2brouter_invoice_mode';
    const OPTION_TRANSACTION_COUNT = 'b2brouter_transaction_count';
    const OPTION_SHOW_WELCOME = 'b2brouter_show_welcome';
    const OPTION_ACTIVATED = 'b2brouter_activated';
    const OPTION_AUTO_SAVE_PDF = 'b2brouter_auto_save_pdf';
    const OPTION_PDF_STORAGE_PATH = 'b2brouter_pdf_storage_path';
    const OPTION_ATTACH_TO_ORDER_COMPLETED = 'b2brouter_attach_to_order_completed';
    const OPTION_ATTACH_TO_CUSTOMER_INVOICE = 'b2brouter_attach_to_customer_invoice';
    const OPTION_ATTACH_TO_REFUNDED_ORDER = 'b2brouter_attach_to_refunded_order';
    const OPTION_AUTO_CLEANUP_ENABLED = 'b2brouter_auto_cleanup_enabled';
    const OPTION_AUTO_CLEANUP_DAYS = 'b2brouter_auto_cleanup_days';
    const OPTION_INVOICE_SERIES_CODE = 'b2brouter_invoice_series_code';
    const OPTION_CREDIT_NOTE_SERIES_CODE = 'b2brouter_credit_note_series_code';
    const OPTION_INVOICE_NUMBERING_PATTERN = 'b2brouter_invoice_numbering_pattern';
    const OPTION_WEBHOOK_SECRET = 'b2brouter_webhook_secret';
    const OPTION_WEBHOOK_ENABLED = 'b2brouter_webhook_enabled';
    const OPTION_WEBHOOK_FALLBACK_POLLING = 'b2brouter_webhook_fallback_polling';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Constructor
    }

    /**
     * Get API key
     *
     * @since 1.0.0
     * @return string The API key
     */
    public function get_api_key() {
        return get_option(self::OPTION_API_KEY, '');
    }

    /**
     * Set API key
     *
     * @since 1.0.0
     * @param string $api_key The API key to save
     * @return bool True on success, false on failure
     */
    public function set_api_key($api_key) {
        return update_option(self::OPTION_API_KEY, sanitize_text_field($api_key));
    }

    /**
     * Get account ID
     *
     * @since 1.0.0
     * @return string The account ID
     */
    public function get_account_id() {
        return get_option(self::OPTION_ACCOUNT_ID, '');
    }

    /**
     * Set account ID
     *
     * @since 1.0.0
     * @param string $account_id The account ID to save
     * @return bool True on success, false on failure
     */
    public function set_account_id($account_id) {
        return update_option(self::OPTION_ACCOUNT_ID, sanitize_text_field($account_id));
    }

    /**
     * Get account name
     *
     * @since 1.0.0
     * @return string The account name
     */
    public function get_account_name() {
        return get_option(self::OPTION_ACCOUNT_NAME, '');
    }

    /**
     * Set account name
     *
     * @since 1.0.0
     * @param string $account_name The account name to save
     * @return bool True on success, false on failure
     */
    public function set_account_name($account_name) {
        return update_option(self::OPTION_ACCOUNT_NAME, sanitize_text_field($account_name));
    }

    /**
     * Get environment (staging or production)
     *
     * @since 1.0.0
     * @return string The environment ('staging' or 'production')
     */
    public function get_environment() {
        return get_option(self::OPTION_ENVIRONMENT, 'staging');
    }

    /**
     * Set environment
     *
     * @since 1.0.0
     * @param string $environment The environment ('staging' or 'production')
     * @return bool True on success, false on failure
     */
    public function set_environment($environment) {
        if (in_array($environment, array('staging', 'production'))) {
            return update_option(self::OPTION_ENVIRONMENT, $environment);
        }
        return false;
    }

    /**
     * Get API base URL for current environment
     *
     * @since 1.0.0
     * @return string The API base URL
     */
    public function get_api_base_url() {
        // Allow developers to override via wp-config.php constant
        if (defined('B2BROUTER_DEV_API_BASE') && B2BROUTER_DEV_API_BASE) {
            return B2BROUTER_DEV_API_BASE;
        }

        $environment = $this->get_environment();

        if ($environment === 'production') {
            return 'https://api.b2brouter.net';
        }

        return 'https://api-staging.b2brouter.net';
    }

    /**
     * Get web app base URL for current environment
     *
     * @since 1.0.0
     * @return string The web app base URL
     */
    public function get_web_app_base_url() {
        $environment = $this->get_environment();

        if ($environment === 'production') {
            return 'https://app.b2brouter.net';
        }

        return 'https://app-staging.b2brouter.net';
    }

    /**
     * Get invoice mode (automatic or manual)
     *
     * @since 1.0.0
     * @return string The invoice mode ('automatic' or 'manual')
     */
    public function get_invoice_mode() {
        return get_option(self::OPTION_INVOICE_MODE, 'manual');
    }

    /**
     * Set invoice mode
     *
     * @since 1.0.0
     * @param string $mode The invoice mode ('automatic' or 'manual')
     * @return bool True on success, false on failure
     */
    public function set_invoice_mode($mode) {
        if (in_array($mode, array('automatic', 'manual'))) {
            return update_option(self::OPTION_INVOICE_MODE, $mode);
        }
        return false;
    }

    /**
     * Get transaction count
     *
     * @since 1.0.0
     * @return int The transaction count
     */
    public function get_transaction_count() {
        return (int) get_option(self::OPTION_TRANSACTION_COUNT, 0);
    }

    /**
     * Increment transaction count
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public function increment_transaction_count() {
        $count = $this->get_transaction_count();
        return update_option(self::OPTION_TRANSACTION_COUNT, $count + 1);
    }

    /**
     * Check if API key is configured
     *
     * @since 1.0.0
     * @return bool True if API key is configured, false otherwise
     */
    public function is_api_key_configured() {
        $api_key = $this->get_api_key();
        return !empty($api_key);
    }

    /**
     * Should show welcome page
     *
     * @since 1.0.0
     * @return bool True if welcome page should be shown, false otherwise
     */
    public function should_show_welcome() {
        return get_option(self::OPTION_SHOW_WELCOME, '0') === '1';
    }

    /**
     * Mark welcome page as shown
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public function mark_welcome_shown() {
        return update_option(self::OPTION_SHOW_WELCOME, '0');
    }

    /**
     * Validate API key with B2Brouter
     *
     * For single-account keys, auto-selects the account and returns {valid, message}.
     * For multi-account keys, returns {valid, message, multiple_accounts, accounts}
     * where accounts is a list of {id, name, label, parent_id} and stores a
     * transient of verified accounts for server-side validation on selection.
     *
     * @since 1.0.0
     * @param string $api_key The API key to validate
     * @return array{valid: bool, message: string, multiple_accounts?: bool, accounts?: array} Validation result
     */
    public function validate_api_key($api_key) {
        try {
            if (empty($api_key)) {
                return array(
                    'valid' => false,
                    'message' => __('API key cannot be empty', 'b2brouter-woocommerce')
                );
            }

            // Try to initialize the client
            if (!class_exists('B2BRouter\B2BRouterClient')) {
                return array(
                    'valid' => false,
                    'message' => __('B2Brouter PHP SDK not found. Please install dependencies.', 'b2brouter-woocommerce')
                );
            }

            // Create client with environment setting
            $options = array('api_base' => $this->get_api_base_url());
            $client = new \B2BRouter\B2BRouterClient($api_key, $options);

            // Call GET /accounts to validate the key and retrieve all accounts
            $url = $client->getApiBase() . '/accounts?limit=100';

            $headers = array(
                'X-B2B-API-Key' => $api_key,
                'X-B2B-API-Version' => $client->getApiVersion(),
                'Accept' => 'application/json'
            );

            $response = $client->getHttpClient()->request(
                'GET',
                $url,
                $headers,
                null,
                $client->getTimeout()
            );

            // Check if request was successful
            if ($response['status'] !== 200) {
                $body = json_decode($response['body'], true);
                $error_message = isset($body['message']) ? $body['message'] : __('Invalid API key', 'b2brouter-woocommerce');

                return array(
                    'valid' => false,
                    'message' => $error_message
                );
            }

            // Parse response and extract accounts
            $body = json_decode($response['body'], true);

            if (!isset($body['accounts']) || empty($body['accounts'])) {
                return array(
                    'valid' => false,
                    'message' => __('No accounts found for this API key', 'b2brouter-woocommerce')
                );
            }

            $accounts = $body['accounts'];

            // Single account: auto-select it
            if (count($accounts) === 1) {
                $account = $accounts[0];
                $this->set_account_id((string) $account['id']);
                $this->set_account_name($account['name']);

                return array(
                    'valid' => true,
                    'message' => sprintf(
                        __('API key is valid. Using account: %s', 'b2brouter-woocommerce'),
                        $account['name']
                    )
                );
            }

            // Multiple accounts: return list for user selection
            $account_list = array();
            foreach ($accounts as $account) {
                $label = $account['name'];
                if (!empty($account['tin_value'])) {
                    $label .= ' (' . $account['tin_value'] . ')';
                }
                if (!empty($account['parent_id'])) {
                    $label = '↳ ' . $label;
                }
                $account_list[] = array(
                    'id' => (string) $account['id'],
                    'name' => $account['name'],
                    'label' => $label,
                    'parent_id' => isset($account['parent_id']) ? $account['parent_id'] : null,
                );
            }

            // Sort: parents first, then children grouped by parent_id
            usort($account_list, function($a, $b) {
                $a_is_child = !empty($a['parent_id']) ? 1 : 0;
                $b_is_child = !empty($b['parent_id']) ? 1 : 0;
                if ($a_is_child !== $b_is_child) {
                    return $a_is_child - $b_is_child;
                }
                if ($a_is_child && $b_is_child) {
                    $parent_cmp = strcmp((string) $a['parent_id'], (string) $b['parent_id']);
                    if ($parent_cmp !== 0) {
                        return $parent_cmp;
                    }
                }
                return strcmp($a['name'], $b['name']);
            });

            // Store validated accounts in transient for server-side verification
            $verified_accounts = array();
            foreach ($account_list as $acct) {
                $verified_accounts[$acct['id']] = $acct['name'];
            }
            set_transient('b2brouter_validated_accounts', $verified_accounts, HOUR_IN_SECONDS);

            return array(
                'valid' => true,
                'multiple_accounts' => true,
                'accounts' => $account_list,
                'message' => __('API key is valid. Please select an account.', 'b2brouter-woocommerce')
            );
        } catch (\Exception $e) {
            return array(
                'valid' => false,
                'message' => sprintf(
                    __('API key validation failed: %s', 'b2brouter-woocommerce'),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Get auto-save PDF setting
     *
     * @since 1.0.0
     * @return bool
     */
    public function get_auto_save_pdf() {
        return get_option(self::OPTION_AUTO_SAVE_PDF, '0') === '1';
    }

    /**
     * Set auto-save PDF setting
     *
     * @since 1.0.0
     * @param bool $enabled
     * @return bool
     */
    public function set_auto_save_pdf($enabled) {
        return update_option(self::OPTION_AUTO_SAVE_PDF, $enabled ? '1' : '0');
    }

    /**
     * Get PDF storage directory path
     *
     * @since 1.0.0
     * @return string Full path to PDF storage directory
     */
    public function get_pdf_storage_path() {
        $upload_dir = wp_upload_dir();
        $custom_path = get_option(self::OPTION_PDF_STORAGE_PATH, '');

        if (!empty($custom_path) && file_exists($custom_path)) {
            return $custom_path;
        }

        return $upload_dir['basedir'] . '/b2brouter-invoices';
    }

    /**
     * Get attach to order completed email setting
     *
     * @since 1.0.0
     * @return bool
     */
    public function get_attach_to_order_completed() {
        return get_option(self::OPTION_ATTACH_TO_ORDER_COMPLETED, '0') === '1';
    }

    /**
     * Set attach to order completed email setting
     *
     * @since 1.0.0
     * @param bool $enabled
     * @return bool
     */
    public function set_attach_to_order_completed($enabled) {
        return update_option(self::OPTION_ATTACH_TO_ORDER_COMPLETED, $enabled ? '1' : '0');
    }

    /**
     * Get attach to customer invoice email setting
     *
     * @since 1.0.0
     * @return bool
     */
    public function get_attach_to_customer_invoice() {
        return get_option(self::OPTION_ATTACH_TO_CUSTOMER_INVOICE, '0') === '1';
    }

    /**
     * Set attach to customer invoice email setting
     *
     * @since 1.0.0
     * @param bool $enabled
     * @return bool
     */
    public function set_attach_to_customer_invoice($enabled) {
        return update_option(self::OPTION_ATTACH_TO_CUSTOMER_INVOICE, $enabled ? '1' : '0');
    }

    /**
     * Get attach to refunded order email setting
     *
     * @since 1.0.0
     * @return bool
     */
    public function get_attach_to_refunded_order() {
        return get_option(self::OPTION_ATTACH_TO_REFUNDED_ORDER, '0') === '1';
    }

    /**
     * Set attach to refunded order email setting
     *
     * @since 1.0.0
     * @param bool $enabled
     * @return bool
     */
    public function set_attach_to_refunded_order($enabled) {
        return update_option(self::OPTION_ATTACH_TO_REFUNDED_ORDER, $enabled ? '1' : '0');
    }

    /**
     * Get auto cleanup enabled setting
     *
     * @since 1.0.0
     * @return bool
     */
    public function get_auto_cleanup_enabled() {
        return get_option(self::OPTION_AUTO_CLEANUP_ENABLED, '0') === '1';
    }

    /**
     * Set auto cleanup enabled setting
     *
     * @since 1.0.0
     * @param bool $enabled
     * @return bool
     */
    public function set_auto_cleanup_enabled($enabled) {
        return update_option(self::OPTION_AUTO_CLEANUP_ENABLED, $enabled ? '1' : '0');
    }

    /**
     * Get auto cleanup days setting
     *
     * @since 1.0.0
     * @return int
     */
    public function get_auto_cleanup_days() {
        return intval(get_option(self::OPTION_AUTO_CLEANUP_DAYS, 90));
    }

    /**
     * Set auto cleanup days setting
     *
     * @since 1.0.0
     * @param int $days
     * @return bool
     */
    public function set_auto_cleanup_days($days) {
        return update_option(self::OPTION_AUTO_CLEANUP_DAYS, max(1, intval($days)));
    }

    /**
     * Get invoice series code
     *
     * @since 1.0.0
     * @return string
     */
    public function get_invoice_series_code() {
        return get_option(self::OPTION_INVOICE_SERIES_CODE, '');
    }

    /**
     * Set invoice series code
     *
     * @since 1.0.0
     * @param string $code
     * @return bool
     */
    public function set_invoice_series_code($code) {
        return update_option(self::OPTION_INVOICE_SERIES_CODE, sanitize_text_field($code));
    }

    /**
     * Get credit note series code
     *
     * @since 1.0.0
     * @return string
     */
    public function get_credit_note_series_code() {
        return get_option(self::OPTION_CREDIT_NOTE_SERIES_CODE, '');
    }

    /**
     * Set credit note series code
     *
     * @since 1.0.0
     * @param string $code
     * @return bool
     */
    public function set_credit_note_series_code($code) {
        return update_option(self::OPTION_CREDIT_NOTE_SERIES_CODE, sanitize_text_field($code));
    }

    /**
     * Get invoice numbering pattern
     *
     * @since 1.0.0
     * @return string
     */
    public function get_invoice_numbering_pattern() {
        return get_option(self::OPTION_INVOICE_NUMBERING_PATTERN, 'woocommerce');
    }

    /**
     * Set invoice numbering pattern
     *
     * @since 1.0.0
     * @param string $pattern
     * @return bool
     */
    public function set_invoice_numbering_pattern($pattern) {
        $valid_patterns = array('automatic', 'woocommerce', 'sequential', 'custom');
        if (in_array($pattern, $valid_patterns)) {
            return update_option(self::OPTION_INVOICE_NUMBERING_PATTERN, $pattern);
        }
        return false;
    }

    /**
     * Get custom numbering pattern
     *
     * @since 1.0.0
     * @return string
     */
    public function get_custom_numbering_pattern() {
        return get_option('b2brouter_custom_numbering_pattern', '{order_id}');
    }

    /**
     * Set custom numbering pattern
     *
     * @since 1.0.0
     * @param string $pattern
     * @return bool
     */
    public function set_custom_numbering_pattern($pattern) {
        return update_option('b2brouter_custom_numbering_pattern', sanitize_text_field($pattern));
    }

    /**
     * Get next sequential number for a series
     *
     * @since 1.0.0
     * @param string $series_code
     * @return int
     */
    public function get_next_sequential_number($series_code) {
        $option_name = 'b2brouter_seq_counter_' . sanitize_text_field($series_code);
        $current = intval(get_option($option_name, 0));
        $next = $current + 1;
        update_option($option_name, $next);
        return $next;
    }

    /**
     * Get webhook secret
     *
     * @since 1.0.0
     * @return string Webhook secret
     */
    public function get_webhook_secret() {
        return get_option(self::OPTION_WEBHOOK_SECRET, '');
    }

    /**
     * Set webhook secret
     *
     * @since 1.0.0
     * @param string $secret Webhook secret
     * @return bool True on success, false on failure
     */
    public function set_webhook_secret($secret) {
        return update_option(self::OPTION_WEBHOOK_SECRET, sanitize_text_field($secret));
    }

    /**
     * Check if webhooks are enabled
     *
     * @since 1.0.0
     * @return bool True if enabled, false otherwise
     */
    public function get_webhook_enabled() {
        return get_option(self::OPTION_WEBHOOK_ENABLED, 'no') === 'yes';
    }

    /**
     * Set webhook enabled status
     *
     * @since 1.0.0
     * @param bool $enabled Whether webhooks should be enabled
     * @return bool True on success, false on failure
     */
    public function set_webhook_enabled($enabled) {
        return update_option(self::OPTION_WEBHOOK_ENABLED, $enabled ? 'yes' : 'no');
    }

    /**
     * Check if fallback polling is enabled
     *
     * @since 1.0.0
     * @return bool True if enabled, false otherwise
     */
    public function get_webhook_fallback_polling() {
        return get_option(self::OPTION_WEBHOOK_FALLBACK_POLLING, 'yes') === 'yes';
    }

    /**
     * Set webhook fallback polling status
     *
     * @since 1.0.0
     * @param bool $enabled Whether fallback polling should be enabled
     * @return bool True on success, false on failure
     */
    public function set_webhook_fallback_polling($enabled) {
        return update_option(self::OPTION_WEBHOOK_FALLBACK_POLLING, $enabled ? 'yes' : 'no');
    }

    /**
     * Get webhook URL
     *
     * @since 1.0.0
     * @return string Webhook endpoint URL
     */
    public function get_webhook_url() {
        return rest_url('b2brouter/v1/webhook');
    }
}
