<?php
/**
 * Comprehensive tests for Settings class
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\Settings;

/**
 * Settings test case
 *
 * Tests all public methods and edge cases for the Settings class
 *
 * @since 1.0.0
 */
class SettingsTest extends TestCase {

    /**
     * Settings instance
     *
     * @var Settings
     */
    private $settings;

    /**
     * Set up test
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();

        // Reset static options before each test
        global $wp_options;
        $wp_options = array();

        // Create Settings instance (no dependencies needed!)
        $this->settings = new Settings();
    }

    /**
     * Tear down test
     *
     * @return void
     */
    public function tearDown(): void {
        parent::tearDown();

        // Clean up
        global $wp_options;
        $wp_options = array();
    }

    /**
     * Test that Settings can be instantiated
     *
     * @return void
     */
    public function test_can_be_instantiated() {
        $this->assertInstanceOf(Settings::class, $this->settings);
    }

    /**
     * Test that Settings constants are defined correctly
     *
     * @return void
     */
    public function test_constants_are_defined() {
        $this->assertEquals('b2brouter_api_key', Settings::OPTION_API_KEY);
        $this->assertEquals('b2brouter_account_id', Settings::OPTION_ACCOUNT_ID);
        $this->assertEquals('b2brouter_environment', Settings::OPTION_ENVIRONMENT);
        $this->assertEquals('b2brouter_invoice_mode', Settings::OPTION_INVOICE_MODE);
        $this->assertEquals('b2brouter_transaction_count', Settings::OPTION_TRANSACTION_COUNT);
        $this->assertEquals('b2brouter_show_welcome', Settings::OPTION_SHOW_WELCOME);
        $this->assertEquals('b2brouter_activated', Settings::OPTION_ACTIVATED);
    }

    // ========== API Key Tests ==========

    /**
     * Test get_api_key returns empty string by default
     *
     * @return void
     */
    public function test_get_api_key_returns_empty_by_default() {
        $api_key = $this->settings->get_api_key();
        $this->assertEquals('', $api_key);
    }

    /**
     * Test set_api_key stores the API key
     *
     * @return void
     */
    public function test_set_api_key_stores_value() {
        $result = $this->settings->set_api_key('test-api-key-123');
        $this->assertTrue($result);

        $stored = $this->settings->get_api_key();
        $this->assertEquals('test-api-key-123', $stored);
    }

    /**
     * Test set_api_key sanitizes input
     *
     * @return void
     */
    public function test_set_api_key_sanitizes_input() {
        $this->settings->set_api_key('  key-with-spaces  ');
        $stored = $this->settings->get_api_key();
        $this->assertEquals('key-with-spaces', $stored);
    }

    /**
     * Test set_api_key handles empty string
     *
     * @return void
     */
    public function test_set_api_key_accepts_empty_string() {
        $result = $this->settings->set_api_key('');
        $this->assertTrue($result);
        $this->assertEquals('', $this->settings->get_api_key());
    }

    /**
     * Test is_api_key_configured returns false when empty
     *
     * @return void
     */
    public function test_is_api_key_configured_returns_false_when_empty() {
        $this->assertFalse($this->settings->is_api_key_configured());
    }

    /**
     * Test is_api_key_configured returns true when set
     *
     * @return void
     */
    public function test_is_api_key_configured_returns_true_when_set() {
        $this->settings->set_api_key('some-key');
        $this->assertTrue($this->settings->is_api_key_configured());
    }

    // ========== Account ID Tests ==========

    /**
     * Test get_account_id returns empty string by default
     *
     * @return void
     */
    public function test_get_account_id_returns_empty_by_default() {
        $account_id = $this->settings->get_account_id();
        $this->assertEquals('', $account_id);
    }

    /**
     * Test set_account_id stores the account ID
     *
     * @return void
     */
    public function test_set_account_id_stores_value() {
        $result = $this->settings->set_account_id('211162');
        $this->assertTrue($result);

        $stored = $this->settings->get_account_id();
        $this->assertEquals('211162', $stored);
    }

    /**
     * Test set_account_id sanitizes input
     *
     * @return void
     */
    public function test_set_account_id_sanitizes_input() {
        $this->settings->set_account_id('  211162  ');
        $stored = $this->settings->get_account_id();
        $this->assertEquals('211162', $stored);
    }

    // ========== Environment Tests ==========

    /**
     * Test get_environment returns 'staging' by default
     *
     * @return void
     */
    public function test_get_environment_returns_staging_by_default() {
        $environment = $this->settings->get_environment();
        $this->assertEquals('staging', $environment);
    }

    /**
     * Test set_environment accepts 'staging'
     *
     * @return void
     */
    public function test_set_environment_accepts_staging() {
        $result = $this->settings->set_environment('staging');
        $this->assertTrue($result);
        $this->assertEquals('staging', $this->settings->get_environment());
    }

    /**
     * Test set_environment accepts 'production'
     *
     * @return void
     */
    public function test_set_environment_accepts_production() {
        $result = $this->settings->set_environment('production');
        $this->assertTrue($result);
        $this->assertEquals('production', $this->settings->get_environment());
    }

    /**
     * Test set_environment rejects invalid values
     *
     * @return void
     */
    public function test_set_environment_rejects_invalid_values() {
        $result = $this->settings->set_environment('invalid');
        $this->assertFalse($result);

        $result = $this->settings->set_environment('');
        $this->assertFalse($result);

        $result = $this->settings->set_environment('PRODUCTION');
        $this->assertFalse($result);
    }

    /**
     * Test get_api_base_url returns staging URL by default
     *
     * @return void
     */
    public function test_get_api_base_url_returns_staging_by_default() {
        $url = $this->settings->get_api_base_url();
        $this->assertEquals('https://api-staging.b2brouter.net', $url);
    }

    /**
     * Test get_api_base_url returns production URL when set
     *
     * @return void
     */
    public function test_get_api_base_url_returns_production_when_set() {
        $this->settings->set_environment('production');
        $url = $this->settings->get_api_base_url();
        $this->assertEquals('https://api.b2brouter.net', $url);
    }

    /**
     * Test get_api_base_url returns staging URL when explicitly set
     *
     * @return void
     */
    public function test_get_api_base_url_returns_staging_when_set() {
        $this->settings->set_environment('production');
        $this->settings->set_environment('staging');
        $url = $this->settings->get_api_base_url();
        $this->assertEquals('https://api-staging.b2brouter.net', $url);
    }

    // ========== Invoice Mode Tests ==========

    /**
     * Test get_invoice_mode returns 'manual' by default
     *
     * @return void
     */
    public function test_get_invoice_mode_returns_manual_by_default() {
        $mode = $this->settings->get_invoice_mode();
        $this->assertEquals('manual', $mode);
    }

    /**
     * Test set_invoice_mode accepts 'automatic'
     *
     * @return void
     */
    public function test_set_invoice_mode_accepts_automatic() {
        $result = $this->settings->set_invoice_mode('automatic');
        $this->assertTrue($result);
        $this->assertEquals('automatic', $this->settings->get_invoice_mode());
    }

    /**
     * Test set_invoice_mode accepts 'manual'
     *
     * @return void
     */
    public function test_set_invoice_mode_accepts_manual() {
        $result = $this->settings->set_invoice_mode('manual');
        $this->assertTrue($result);
        $this->assertEquals('manual', $this->settings->get_invoice_mode());
    }

    /**
     * Test set_invoice_mode rejects invalid values
     *
     * @return void
     */
    public function test_set_invoice_mode_rejects_invalid_values() {
        $result = $this->settings->set_invoice_mode('invalid');
        $this->assertFalse($result);

        $result = $this->settings->set_invoice_mode('');
        $this->assertFalse($result);

        $result = $this->settings->set_invoice_mode('AUTO');
        $this->assertFalse($result);

        $result = $this->settings->set_invoice_mode('something_random');
        $this->assertFalse($result);
    }

    /**
     * Test invoice mode is case sensitive
     *
     * @return void
     */
    public function test_invoice_mode_is_case_sensitive() {
        $result = $this->settings->set_invoice_mode('Automatic');
        $this->assertFalse($result);

        $result = $this->settings->set_invoice_mode('MANUAL');
        $this->assertFalse($result);
    }

    // ========== Transaction Count Tests ==========

    /**
     * Test get_transaction_count returns 0 by default
     *
     * @return void
     */
    public function test_get_transaction_count_returns_zero_by_default() {
        $count = $this->settings->get_transaction_count();
        $this->assertEquals(0, $count);
        $this->assertIsInt($count);
    }

    /**
     * Test increment_transaction_count increases count
     *
     * @return void
     */
    public function test_increment_transaction_count_increases_value() {
        $initial = $this->settings->get_transaction_count();
        $this->assertEquals(0, $initial);

        $result = $this->settings->increment_transaction_count();
        $this->assertTrue($result);

        $after_first = $this->settings->get_transaction_count();
        $this->assertEquals(1, $after_first);

        $this->settings->increment_transaction_count();
        $after_second = $this->settings->get_transaction_count();
        $this->assertEquals(2, $after_second);
    }

    /**
     * Test transaction count returns integer
     *
     * @return void
     */
    public function test_transaction_count_always_returns_integer() {
        $count = $this->settings->get_transaction_count();
        $this->assertIsInt($count);

        $this->settings->increment_transaction_count();
        $count = $this->settings->get_transaction_count();
        $this->assertIsInt($count);
    }

    // ========== Welcome Screen Tests ==========

    /**
     * Test should_show_welcome returns false by default
     *
     * @return void
     */
    public function test_should_show_welcome_returns_false_by_default() {
        $should_show = $this->settings->should_show_welcome();
        $this->assertFalse($should_show);
    }

    /**
     * Test mark_welcome_shown sets flag to not show
     *
     * @return void
     */
    public function test_mark_welcome_shown_prevents_showing() {
        $result = $this->settings->mark_welcome_shown();
        $this->assertTrue($result);

        $should_show = $this->settings->should_show_welcome();
        $this->assertFalse($should_show);
    }

    // ========== API Key Validation Tests ==========

    /**
     * Test validate_api_key rejects empty key
     *
     * @return void
     */
    public function test_validate_api_key_rejects_empty_string() {
        $result = $this->settings->validate_api_key('');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('cannot be empty', $result['message']);
    }

    /**
     * Test validate_api_key checks for SDK presence
     *
     * @return void
     */
    public function test_validate_api_key_requires_sdk() {
        // The bootstrap file creates a mock B2BRouterClient class
        // Verify the namespaced class exists
        $this->assertTrue(class_exists('B2BRouter\B2BRouterClient'));
    }

    /**
     * Test validate_api_key returns proper structure
     *
     * @return void
     */
    public function test_validate_api_key_returns_proper_structure() {
        $result = $this->settings->validate_api_key('test-key');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertIsBool($result['valid']);
        $this->assertIsString($result['message']);
    }

    /**
     * Test validate_api_key with invalid key returns error
     *
     * Note: With real SDK installed, this makes actual API calls
     *
     * @return void
     */
    public function test_validate_api_key_with_invalid_key() {
        $result = $this->settings->validate_api_key('invalid-key');

        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertIsString($result['message']);
    }

    // ========== Integration Tests ==========

    /**
     * Test complete workflow of setting up plugin
     *
     * @return void
     */
    public function test_complete_setup_workflow() {
        // Step 1: Check initial state
        $this->assertFalse($this->settings->is_api_key_configured());
        $this->assertEquals('manual', $this->settings->get_invoice_mode());
        $this->assertEquals(0, $this->settings->get_transaction_count());

        // Step 2: Configure API key
        $this->settings->set_api_key('my-api-key');
        $this->assertTrue($this->settings->is_api_key_configured());

        // Step 3: Set to automatic mode
        $this->assertTrue($this->settings->set_invoice_mode('automatic'));
        $this->assertEquals('automatic', $this->settings->get_invoice_mode());

        // Step 4: Generate invoices (increment counter)
        for ($i = 0; $i < 5; $i++) {
            $this->settings->increment_transaction_count();
        }
        $this->assertEquals(5, $this->settings->get_transaction_count());

        // Step 5: Verify configuration persists
        $this->assertEquals('my-api-key', $this->settings->get_api_key());
        $this->assertEquals('automatic', $this->settings->get_invoice_mode());
    }

    /**
     * Test settings can be updated multiple times
     *
     * @return void
     */
    public function test_settings_can_be_updated_multiple_times() {
        // Update API key multiple times
        $this->settings->set_api_key('key1');
        $this->assertEquals('key1', $this->settings->get_api_key());

        $this->settings->set_api_key('key2');
        $this->assertEquals('key2', $this->settings->get_api_key());

        // Update mode multiple times
        $this->settings->set_invoice_mode('automatic');
        $this->assertEquals('automatic', $this->settings->get_invoice_mode());

        $this->settings->set_invoice_mode('manual');
        $this->assertEquals('manual', $this->settings->get_invoice_mode());
    }

    // ========== PDF Settings Tests (Phase 3) ==========

    /**
     * Test get_auto_save_pdf returns false by default
     *
     * @return void
     */
    public function test_get_auto_save_pdf_returns_false_by_default() {
        $auto_save = $this->settings->get_auto_save_pdf();
        $this->assertFalse($auto_save);
    }

    /**
     * Test set_auto_save_pdf enables auto-save
     *
     * @return void
     */
    public function test_set_auto_save_pdf_enables() {
        $result = $this->settings->set_auto_save_pdf(true);
        $this->assertTrue($result);
        $this->assertTrue($this->settings->get_auto_save_pdf());
    }

    /**
     * Test set_auto_save_pdf disables auto-save
     *
     * @return void
     */
    public function test_set_auto_save_pdf_disables() {
        $this->settings->set_auto_save_pdf(true);
        $this->assertTrue($this->settings->get_auto_save_pdf());

        $this->settings->set_auto_save_pdf(false);
        $this->assertFalse($this->settings->get_auto_save_pdf());
    }

    /**
     * Test get_pdf_storage_path returns default path
     *
     * @return void
     */
    public function test_get_pdf_storage_path_returns_default() {
        $path = $this->settings->get_pdf_storage_path();
        $this->assertIsString($path);
        $this->assertStringContainsString('b2brouter-invoices', $path);
    }

    // ========== Email Attachment Settings Tests (Phase 5.1) ==========

    /**
     * Test get_attach_to_order_completed returns false by default
     *
     * @return void
     */
    public function test_get_attach_to_order_completed_returns_false_by_default() {
        $attach = $this->settings->get_attach_to_order_completed();
        $this->assertFalse($attach);
    }

    /**
     * Test set_attach_to_order_completed enables attachment
     *
     * @return void
     */
    public function test_set_attach_to_order_completed_enables() {
        $result = $this->settings->set_attach_to_order_completed(true);
        $this->assertTrue($result);
        $this->assertTrue($this->settings->get_attach_to_order_completed());
    }

    /**
     * Test set_attach_to_order_completed disables attachment
     *
     * @return void
     */
    public function test_set_attach_to_order_completed_disables() {
        $this->settings->set_attach_to_order_completed(true);
        $this->settings->set_attach_to_order_completed(false);
        $this->assertFalse($this->settings->get_attach_to_order_completed());
    }

    /**
     * Test get_attach_to_customer_invoice returns false by default
     *
     * @return void
     */
    public function test_get_attach_to_customer_invoice_returns_false_by_default() {
        $attach = $this->settings->get_attach_to_customer_invoice();
        $this->assertFalse($attach);
    }

    /**
     * Test set_attach_to_customer_invoice enables attachment
     *
     * @return void
     */
    public function test_set_attach_to_customer_invoice_enables() {
        $result = $this->settings->set_attach_to_customer_invoice(true);
        $this->assertTrue($result);
        $this->assertTrue($this->settings->get_attach_to_customer_invoice());
    }

    /**
     * Test set_attach_to_customer_invoice disables attachment
     *
     * @return void
     */
    public function test_set_attach_to_customer_invoice_disables() {
        $this->settings->set_attach_to_customer_invoice(true);
        $this->settings->set_attach_to_customer_invoice(false);
        $this->assertFalse($this->settings->get_attach_to_customer_invoice());
    }

    /**
     * Test both email attachment settings work independently
     *
     * @return void
     */
    public function test_email_attachment_settings_are_independent() {
        // Enable only order completed
        $this->settings->set_attach_to_order_completed(true);
        $this->settings->set_attach_to_customer_invoice(false);

        $this->assertTrue($this->settings->get_attach_to_order_completed());
        $this->assertFalse($this->settings->get_attach_to_customer_invoice());

        // Enable only customer invoice
        $this->settings->set_attach_to_order_completed(false);
        $this->settings->set_attach_to_customer_invoice(true);

        $this->assertFalse($this->settings->get_attach_to_order_completed());
        $this->assertTrue($this->settings->get_attach_to_customer_invoice());

        // Enable both
        $this->settings->set_attach_to_order_completed(true);
        $this->settings->set_attach_to_customer_invoice(true);

        $this->assertTrue($this->settings->get_attach_to_order_completed());
        $this->assertTrue($this->settings->get_attach_to_customer_invoice());
    }

    // ========== Cleanup Settings Tests (Phase 5.3) ==========

    /**
     * Test get_auto_cleanup_enabled returns false by default
     *
     * @return void
     */
    public function test_get_auto_cleanup_enabled_returns_false_by_default() {
        $enabled = $this->settings->get_auto_cleanup_enabled();
        $this->assertFalse($enabled);
    }

    /**
     * Test set_auto_cleanup_enabled enables cleanup
     *
     * @return void
     */
    public function test_set_auto_cleanup_enabled_enables() {
        $result = $this->settings->set_auto_cleanup_enabled(true);
        $this->assertTrue($result);
        $this->assertTrue($this->settings->get_auto_cleanup_enabled());
    }

    /**
     * Test set_auto_cleanup_enabled disables cleanup
     *
     * @return void
     */
    public function test_set_auto_cleanup_enabled_disables() {
        $this->settings->set_auto_cleanup_enabled(true);
        $this->settings->set_auto_cleanup_enabled(false);
        $this->assertFalse($this->settings->get_auto_cleanup_enabled());
    }

    /**
     * Test get_auto_cleanup_days returns 90 by default
     *
     * @return void
     */
    public function test_get_auto_cleanup_days_returns_90_by_default() {
        $days = $this->settings->get_auto_cleanup_days();
        $this->assertEquals(90, $days);
        $this->assertIsInt($days);
    }

    /**
     * Test set_auto_cleanup_days stores value
     *
     * @return void
     */
    public function test_set_auto_cleanup_days_stores_value() {
        $result = $this->settings->set_auto_cleanup_days(60);
        $this->assertTrue($result);
        $this->assertEquals(60, $this->settings->get_auto_cleanup_days());
    }

    /**
     * Test set_auto_cleanup_days enforces minimum of 1 day
     *
     * @return void
     */
    public function test_set_auto_cleanup_days_enforces_minimum() {
        $this->settings->set_auto_cleanup_days(0);
        $this->assertEquals(1, $this->settings->get_auto_cleanup_days());

        $this->settings->set_auto_cleanup_days(-10);
        $this->assertEquals(1, $this->settings->get_auto_cleanup_days());
    }

    /**
     * Test set_auto_cleanup_days accepts large values
     *
     * @return void
     */
    public function test_set_auto_cleanup_days_accepts_large_values() {
        $this->settings->set_auto_cleanup_days(365);
        $this->assertEquals(365, $this->settings->get_auto_cleanup_days());

        $this->settings->set_auto_cleanup_days(1000);
        $this->assertEquals(1000, $this->settings->get_auto_cleanup_days());
    }

    /**
     * Test set_auto_cleanup_days converts to integer
     *
     * @return void
     */
    public function test_set_auto_cleanup_days_converts_to_integer() {
        $this->settings->set_auto_cleanup_days('45');
        $this->assertIsInt($this->settings->get_auto_cleanup_days());
        $this->assertEquals(45, $this->settings->get_auto_cleanup_days());

        $this->settings->set_auto_cleanup_days(30.7);
        $this->assertEquals(30, $this->settings->get_auto_cleanup_days());
    }

    /**
     * Test cleanup settings work independently
     *
     * @return void
     */
    public function test_cleanup_settings_work_independently() {
        // Disabled with custom days
        $this->settings->set_auto_cleanup_enabled(false);
        $this->settings->set_auto_cleanup_days(30);

        $this->assertFalse($this->settings->get_auto_cleanup_enabled());
        $this->assertEquals(30, $this->settings->get_auto_cleanup_days());

        // Enabled with different days
        $this->settings->set_auto_cleanup_enabled(true);
        $this->settings->set_auto_cleanup_days(120);

        $this->assertTrue($this->settings->get_auto_cleanup_enabled());
        $this->assertEquals(120, $this->settings->get_auto_cleanup_days());
    }

    // ========== Phase 5 Integration Tests ==========

    /**
     * Test complete Phase 5 workflow
     *
     * @return void
     */
    public function test_phase_5_complete_workflow() {
        // Step 1: Enable PDF auto-save
        $this->settings->set_auto_save_pdf(true);
        $this->assertTrue($this->settings->get_auto_save_pdf());

        // Step 2: Enable email attachments
        $this->settings->set_attach_to_order_completed(true);
        $this->settings->set_attach_to_customer_invoice(true);

        $this->assertTrue($this->settings->get_attach_to_order_completed());
        $this->assertTrue($this->settings->get_attach_to_customer_invoice());

        // Step 3: Configure cleanup
        $this->settings->set_auto_cleanup_enabled(true);
        $this->settings->set_auto_cleanup_days(60);

        $this->assertTrue($this->settings->get_auto_cleanup_enabled());
        $this->assertEquals(60, $this->settings->get_auto_cleanup_days());

        // Verify all settings persist
        $this->assertTrue($this->settings->get_auto_save_pdf());
        $this->assertTrue($this->settings->get_attach_to_order_completed());
        $this->assertTrue($this->settings->get_attach_to_customer_invoice());
        $this->assertTrue($this->settings->get_auto_cleanup_enabled());
        $this->assertEquals(60, $this->settings->get_auto_cleanup_days());
    }

    /**
     * Test all public methods are covered
     *
     * This test serves as documentation of the Settings class public API
     *
     * @return void
     */
    public function test_all_public_methods_exist() {
        $methods = [
            'get_api_key',
            'set_api_key',
            'get_account_id',
            'set_account_id',
            'get_environment',
            'set_environment',
            'get_api_base_url',
            'get_invoice_mode',
            'set_invoice_mode',
            'get_transaction_count',
            'increment_transaction_count',
            'is_api_key_configured',
            'should_show_welcome',
            'mark_welcome_shown',
            'validate_api_key',
            // Phase 3
            'get_auto_save_pdf',
            'set_auto_save_pdf',
            'get_pdf_storage_path',
            // Phase 5.1
            'get_attach_to_order_completed',
            'set_attach_to_order_completed',
            'get_attach_to_customer_invoice',
            'set_attach_to_customer_invoice',
            // Phase 5.3
            'get_auto_cleanup_enabled',
            'set_auto_cleanup_enabled',
            'get_auto_cleanup_days',
            'set_auto_cleanup_days',
            // Series codes and numbering
            'get_invoice_series_code',
            'set_invoice_series_code',
            'get_credit_note_series_code',
            'set_credit_note_series_code',
            'get_invoice_numbering_pattern',
            'set_invoice_numbering_pattern',
            'get_custom_numbering_pattern',
            'set_custom_numbering_pattern',
            'get_next_sequential_number',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->settings, $method),
                "Method {$method} should exist in Settings class"
            );
        }
    }

    // ========== Invoice Series Code Tests ==========

    /**
     * Test get_invoice_series_code returns empty string by default
     *
     * @return void
     */
    public function test_get_invoice_series_code_returns_empty_by_default() {
        $series_code = $this->settings->get_invoice_series_code();
        $this->assertEquals('', $series_code);
    }

    /**
     * Test set_invoice_series_code stores value
     *
     * @return void
     */
    public function test_set_invoice_series_code_stores_value() {
        $result = $this->settings->set_invoice_series_code('INV');
        $this->assertTrue($result);
        $this->assertEquals('INV', $this->settings->get_invoice_series_code());
    }

    /**
     * Test set_invoice_series_code sanitizes input
     *
     * @return void
     */
    public function test_set_invoice_series_code_sanitizes_input() {
        $this->settings->set_invoice_series_code('  S01  ');
        $this->assertEquals('S01', $this->settings->get_invoice_series_code());
    }

    /**
     * Test set_invoice_series_code accepts empty string
     *
     * @return void
     */
    public function test_set_invoice_series_code_accepts_empty() {
        $this->settings->set_invoice_series_code('INV');
        $this->settings->set_invoice_series_code('');
        $this->assertEquals('', $this->settings->get_invoice_series_code());
    }

    // ========== Credit Note Series Code Tests ==========

    /**
     * Test get_credit_note_series_code returns empty string by default
     *
     * @return void
     */
    public function test_get_credit_note_series_code_returns_empty_by_default() {
        $series_code = $this->settings->get_credit_note_series_code();
        $this->assertEquals('', $series_code);
    }

    /**
     * Test set_credit_note_series_code stores value
     *
     * @return void
     */
    public function test_set_credit_note_series_code_stores_value() {
        $result = $this->settings->set_credit_note_series_code('CN');
        $this->assertTrue($result);
        $this->assertEquals('CN', $this->settings->get_credit_note_series_code());
    }

    /**
     * Test set_credit_note_series_code sanitizes input
     *
     * @return void
     */
    public function test_set_credit_note_series_code_sanitizes_input() {
        $this->settings->set_credit_note_series_code('  R01  ');
        $this->assertEquals('R01', $this->settings->get_credit_note_series_code());
    }

    /**
     * Test series codes are independent
     *
     * @return void
     */
    public function test_series_codes_are_independent() {
        $this->settings->set_invoice_series_code('INV');
        $this->settings->set_credit_note_series_code('CN');

        $this->assertEquals('INV', $this->settings->get_invoice_series_code());
        $this->assertEquals('CN', $this->settings->get_credit_note_series_code());
    }

    // ========== Invoice Numbering Pattern Tests ==========

    /**
     * Test get_invoice_numbering_pattern returns 'woocommerce' by default
     *
     * @return void
     */
    public function test_get_invoice_numbering_pattern_returns_woocommerce_by_default() {
        $pattern = $this->settings->get_invoice_numbering_pattern();
        $this->assertEquals('woocommerce', $pattern);
    }

    /**
     * Test set_invoice_numbering_pattern accepts 'automatic'
     *
     * @return void
     */
    public function test_set_invoice_numbering_pattern_accepts_automatic() {
        $result = $this->settings->set_invoice_numbering_pattern('automatic');
        $this->assertTrue($result);
        $this->assertEquals('automatic', $this->settings->get_invoice_numbering_pattern());
    }

    /**
     * Test set_invoice_numbering_pattern accepts 'woocommerce'
     *
     * @return void
     */
    public function test_set_invoice_numbering_pattern_accepts_woocommerce() {
        $result = $this->settings->set_invoice_numbering_pattern('woocommerce');
        $this->assertTrue($result);
        $this->assertEquals('woocommerce', $this->settings->get_invoice_numbering_pattern());
    }

    /**
     * Test set_invoice_numbering_pattern accepts 'sequential'
     *
     * @return void
     */
    public function test_set_invoice_numbering_pattern_accepts_sequential() {
        $result = $this->settings->set_invoice_numbering_pattern('sequential');
        $this->assertTrue($result);
        $this->assertEquals('sequential', $this->settings->get_invoice_numbering_pattern());
    }

    /**
     * Test set_invoice_numbering_pattern accepts 'custom'
     *
     * @return void
     */
    public function test_set_invoice_numbering_pattern_accepts_custom() {
        $result = $this->settings->set_invoice_numbering_pattern('custom');
        $this->assertTrue($result);
        $this->assertEquals('custom', $this->settings->get_invoice_numbering_pattern());
    }

    /**
     * Test set_invoice_numbering_pattern rejects invalid values
     *
     * @return void
     */
    public function test_set_invoice_numbering_pattern_rejects_invalid_values() {
        $result = $this->settings->set_invoice_numbering_pattern('invalid');
        $this->assertFalse($result);

        $result = $this->settings->set_invoice_numbering_pattern('');
        $this->assertFalse($result);

        $result = $this->settings->set_invoice_numbering_pattern('AUTOMATIC');
        $this->assertFalse($result);
    }

    // ========== Custom Numbering Pattern Tests ==========

    /**
     * Test get_custom_numbering_pattern returns default value
     *
     * @return void
     */
    public function test_get_custom_numbering_pattern_returns_default() {
        $pattern = $this->settings->get_custom_numbering_pattern();
        $this->assertEquals('INV-{order_id}', $pattern);
    }

    /**
     * Test set_custom_numbering_pattern stores value
     *
     * @return void
     */
    public function test_set_custom_numbering_pattern_stores_value() {
        $result = $this->settings->set_custom_numbering_pattern('INV-{year}-{order_id}');
        $this->assertTrue($result);
        $this->assertEquals('INV-{year}-{order_id}', $this->settings->get_custom_numbering_pattern());
    }

    /**
     * Test set_custom_numbering_pattern sanitizes input
     *
     * @return void
     */
    public function test_set_custom_numbering_pattern_sanitizes_input() {
        $this->settings->set_custom_numbering_pattern('  {order_id}  ');
        $this->assertEquals('{order_id}', $this->settings->get_custom_numbering_pattern());
    }

    // ========== Sequential Numbering Tests ==========

    /**
     * Test get_next_sequential_number starts at 1
     *
     * @return void
     */
    public function test_get_next_sequential_number_starts_at_1() {
        $number = $this->settings->get_next_sequential_number('INV');
        $this->assertEquals(1, $number);
    }

    /**
     * Test get_next_sequential_number increments
     *
     * @return void
     */
    public function test_get_next_sequential_number_increments() {
        $first = $this->settings->get_next_sequential_number('INV');
        $this->assertEquals(1, $first);

        $second = $this->settings->get_next_sequential_number('INV');
        $this->assertEquals(2, $second);

        $third = $this->settings->get_next_sequential_number('INV');
        $this->assertEquals(3, $third);
    }

    /**
     * Test get_next_sequential_number is series-specific
     *
     * @return void
     */
    public function test_get_next_sequential_number_is_series_specific() {
        $inv1 = $this->settings->get_next_sequential_number('INV');
        $this->assertEquals(1, $inv1);

        $cn1 = $this->settings->get_next_sequential_number('CN');
        $this->assertEquals(1, $cn1);

        $inv2 = $this->settings->get_next_sequential_number('INV');
        $this->assertEquals(2, $inv2);

        $cn2 = $this->settings->get_next_sequential_number('CN');
        $this->assertEquals(2, $cn2);
    }

    /**
     * Test get_next_sequential_number returns integer
     *
     * @return void
     */
    public function test_get_next_sequential_number_returns_integer() {
        $number = $this->settings->get_next_sequential_number('INV');
        $this->assertIsInt($number);
    }

    // ========== Integration Tests: Series Codes & Numbering ==========

    /**
     * Test complete series code and numbering workflow
     *
     * @return void
     */
    public function test_series_code_and_numbering_workflow() {
        // Step 1: Configure series codes
        $this->settings->set_invoice_series_code('INV');
        $this->settings->set_credit_note_series_code('CN');

        $this->assertEquals('INV', $this->settings->get_invoice_series_code());
        $this->assertEquals('CN', $this->settings->get_credit_note_series_code());

        // Step 2: Configure numbering pattern
        $this->settings->set_invoice_numbering_pattern('sequential');
        $this->assertEquals('sequential', $this->settings->get_invoice_numbering_pattern());

        // Step 3: Generate sequential numbers
        $this->assertEquals(1, $this->settings->get_next_sequential_number('INV'));
        $this->assertEquals(2, $this->settings->get_next_sequential_number('INV'));
        $this->assertEquals(1, $this->settings->get_next_sequential_number('CN'));

        // Step 4: Switch to custom pattern
        $this->settings->set_invoice_numbering_pattern('custom');
        $this->settings->set_custom_numbering_pattern('INV-{year}-{order_id}');

        $this->assertEquals('custom', $this->settings->get_invoice_numbering_pattern());
        $this->assertEquals('INV-{year}-{order_id}', $this->settings->get_custom_numbering_pattern());
    }
}
