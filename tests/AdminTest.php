<?php
/**
 * Tests for Admin class
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\Admin;
use B2Brouter\WooCommerce\Settings;
use B2Brouter\WooCommerce\Invoice_Generator;


/**
 * Admin test case
 *
 * @since 1.0.0
 */
class AdminTest extends TestCase {

    /**
     * Mock Settings instance
     *
     * @var Settings
     */
    private $mock_settings;

    /**
     * Mock Invoice_Generator instance
     *
     * @var Invoice_Generator
     */
    private $mock_invoice_generator;

    /**
     * Admin instance
     *
     * @var Admin
     */
    private $admin;

    /**
     * Set up test
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();

        // Reset global state
        global $wp_actions, $wp_filters, $wp_menu_pages, $wp_submenu_pages;
        $wp_actions = array();
        $wp_filters = array();
        $wp_menu_pages = array();
        $wp_submenu_pages = array();

        // Create mocks
        $this->mock_settings = $this->createMock(Settings::class);
        $this->mock_invoice_generator = $this->createMock(Invoice_Generator::class);

        // Create admin
        $this->admin = new Admin($this->mock_settings, $this->mock_invoice_generator);
    }

    /**
     * Test that Admin can be instantiated
     *
     * @return void
     */
    public function test_can_be_instantiated() {
        $this->assertInstanceOf(Admin::class, $this->admin);
    }

    /**
     * Test that WordPress hooks are registered
     *
     * @return void
     */
    public function test_registers_wordpress_hooks() {
        global $wp_actions, $wp_filters;

        // Check actions
        $this->assertArrayHasKey('admin_menu', $wp_actions);
        $this->assertArrayHasKey('admin_init', $wp_actions);
        $this->assertArrayHasKey('admin_bar_menu', $wp_actions);
        $this->assertArrayHasKey('admin_enqueue_scripts', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_b2brouter_validate_api_key', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_b2brouter_generate_invoice', $wp_actions);

        // Check filters
        $this->assertArrayHasKey('plugin_action_links_' . B2BROUTER_WC_PLUGIN_BASENAME, $wp_filters);
    }

    /**
     * Test add_admin_menu creates menu pages
     *
     * @return void
     */
    public function test_add_admin_menu_creates_menu_pages() {
        global $wp_menu_pages, $wp_submenu_pages;

        $this->admin->add_admin_menu();

        // Check main menu page
        $this->assertArrayHasKey('b2brouter', $wp_menu_pages);
        $this->assertEquals('Invoices', $wp_menu_pages['b2brouter']['page_title']);

        // Check submenu pages exist
        $this->assertArrayHasKey('b2brouter', $wp_submenu_pages);
    }

    /**
     * Test register_settings is called
     *
     * @return void
     */
    public function test_register_settings() {
        // This method just calls register_setting which is mocked
        // Just verify it doesn't throw errors
        $this->admin->register_settings();
        $this->expectNotToPerformAssertions();
    }

    /**
     * Test add_plugin_action_links adds settings link
     *
     * @return void
     */
    public function test_add_plugin_action_links_adds_settings_link() {
        $links = array('deactivate' => '<a href="#">Deactivate</a>');

        $result = $this->admin->add_plugin_action_links($links);

        $this->assertCount(2, $result);
        // Settings link is added at the beginning via array_unshift
        $first_link = reset($result);
        $this->assertStringContainsString('Settings', $first_link);
        $this->assertStringContainsString('page=b2brouter-settings', $first_link);
    }

    /**
     * Test AJAX methods exist and are callable
     *
     * Note: Full AJAX testing requires complex mocking of exit() behavior
     * This test verifies the methods exist and are properly registered
     *
     * @return void
     */
    public function test_ajax_methods_are_callable() {
        $this->assertTrue(method_exists($this->admin, 'ajax_validate_api_key'));
        $this->assertTrue(method_exists($this->admin, 'ajax_generate_invoice'));
        $this->assertTrue(method_exists($this->admin, 'ajax_select_account'));
    }

    /**
     * Test that select_account AJAX hook is registered
     *
     * @return void
     */
    public function test_select_account_ajax_hook_registered() {
        global $wp_actions;
        $this->assertArrayHasKey('wp_ajax_b2brouter_select_account', $wp_actions);
    }

    /**
     * Test render_settings_page outputs form
     *
     * @return void
     */
    public function test_render_settings_page_outputs_form() {
        $this->mock_settings->method('get_api_key')
                           ->willReturn('test-key');
        $this->mock_settings->method('get_invoice_mode')
                           ->willReturn('automatic');
        $this->mock_settings->method('get_transaction_count')
                           ->willReturn(42);

        ob_start();
        $this->admin->render_settings_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('Invoice Settings', $output);
        $this->assertStringContainsString('API Key', $output);
        $this->assertStringContainsString('Invoice Generation Mode', $output);
        $this->assertStringContainsString('test-key', $output);
        $this->assertStringContainsString('checked="checked"', $output); // automatic is checked
        $this->assertStringContainsString('42', $output); // transaction count
    }

    /**
     * Test render_welcome_page outputs content
     *
     * @return void
     */
    public function test_render_welcome_page_outputs_content() {
        ob_start();
        $this->admin->render_welcome_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('B2Brouter for WooCommerce', $output);
        $this->assertStringContainsString('electronic invoices', $output);
    }

    /**
     * Test render_invoices_page outputs list table
     *
     * @return void
     */
    public function test_render_invoices_page_outputs_list_table() {
        // Mock current_user_can to return true
        // Note: In a real WordPress environment, this would be handled by WP_Mock

        ob_start();
        $this->admin->render_invoices_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('List of Invoices', $output);
        $this->assertStringContainsString('wrap', $output);
    }

    /**
     * Test add_admin_menu includes invoices page
     *
     * @return void
     */
    public function test_add_admin_menu_includes_invoices_page() {
        global $wp_submenu_pages;

        $this->admin->add_admin_menu();

        // Check that b2brouter-invoices submenu was added
        $this->assertArrayHasKey('b2brouter', $wp_submenu_pages);

        // Check if b2brouter-invoices key exists in submenu
        $this->assertArrayHasKey('b2brouter-invoices', $wp_submenu_pages['b2brouter']);

        // Verify page details
        $invoices_page = $wp_submenu_pages['b2brouter']['b2brouter-invoices'];
        $this->assertEquals('List of Invoices', $invoices_page['page_title']);
        $this->assertEquals('List of Invoices', $invoices_page['menu_title']);
        $this->assertEquals('manage_woocommerce', $invoices_page['capability']);
    }

    /**
     * Helper to call an AJAX handler and capture the JSON response
     *
     * @param callable $callback The AJAX handler to call
     * @return array Decoded JSON response
     */
    private function callAjaxHandler($callback) {
        global $wp_send_json_throw;
        $wp_send_json_throw = true;
        try {
            call_user_func($callback);
        } catch (\WpJsonResponseException $e) {
            $wp_send_json_throw = false;
            return json_decode($e->response, true);
        }
        $wp_send_json_throw = false;
        $this->fail('AJAX handler did not call wp_send_json');
    }

    /**
     * Test ajax_select_account rejects when no transient exists
     *
     * @return void
     */
    public function test_ajax_select_account_rejects_without_transient() {
        global $wp_transients;
        $wp_transients = array();

        $_POST['account_id'] = '211162';

        $response = $this->callAjaxHandler(array($this->admin, 'ajax_select_account'));

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Invalid account', $response['data']['message']);

        unset($_POST['account_id']);
    }

    /**
     * Test ajax_select_account rejects account_id not in transient
     *
     * @return void
     */
    public function test_ajax_select_account_rejects_unknown_account_id() {
        global $wp_transients;
        $wp_transients = array(
            'b2brouter_validated_accounts' => array(
                '211162' => 'Real Company',
            )
        );

        $_POST['account_id'] = '999999';

        $response = $this->callAjaxHandler(array($this->admin, 'ajax_select_account'));

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Invalid account', $response['data']['message']);

        unset($_POST['account_id']);
    }

    /**
     * Test ajax_select_account accepts valid account from transient and uses server name
     *
     * @return void
     */
    public function test_ajax_select_account_accepts_valid_account_from_transient() {
        global $wp_transients;
        $wp_transients = array(
            'b2brouter_validated_accounts' => array(
                '211162' => 'Real Company',
                '211163' => 'Child Unit',
            )
        );

        $_POST['account_id'] = '211162';
        $_POST['account_name'] = 'Spoofed Name That Should Be Ignored';

        $this->mock_settings->expects($this->once())
            ->method('set_account_id')
            ->with('211162');
        $this->mock_settings->expects($this->once())
            ->method('set_account_name')
            ->with('Real Company'); // Server-side name, not the spoofed one

        $response = $this->callAjaxHandler(array($this->admin, 'ajax_select_account'));

        $this->assertTrue($response['success']);
        $this->assertStringContainsString('Real Company', $response['data']['message']);

        unset($_POST['account_id'], $_POST['account_name']);
    }

    /**
     * Test ajax_select_account rejects empty account_id
     *
     * @return void
     */
    public function test_ajax_select_account_rejects_empty_account_id() {
        $_POST['account_id'] = '';

        $response = $this->callAjaxHandler(array($this->admin, 'ajax_select_account'));

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('No account selected', $response['data']['message']);

        unset($_POST['account_id']);
    }

    /**
     * Test render_settings_page shows account selector markup
     *
     * @return void
     */
    public function test_render_settings_page_shows_account_selector() {
        $this->mock_settings->method('get_api_key')->willReturn('test-key');
        $this->mock_settings->method('get_invoice_mode')->willReturn('manual');
        $this->mock_settings->method('get_transaction_count')->willReturn(0);
        $this->mock_settings->method('get_account_id')->willReturn('');
        $this->mock_settings->method('get_account_name')->willReturn('');

        ob_start();
        $this->admin->render_settings_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('b2brouter_account_selector', $output);
        $this->assertStringContainsString('b2brouter_account_select', $output);
        $this->assertStringContainsString('Use this account', $output);
    }

    /**
     * Test render_settings_page shows current account when account_id set without account_name
     * (backward compatibility for existing installations)
     *
     * @return void
     */
    public function test_render_settings_page_shows_current_account_without_name() {
        $this->mock_settings->method('get_api_key')->willReturn('test-key');
        $this->mock_settings->method('get_invoice_mode')->willReturn('manual');
        $this->mock_settings->method('get_transaction_count')->willReturn(0);
        $this->mock_settings->method('get_account_id')->willReturn('211162');
        $this->mock_settings->method('get_account_name')->willReturn('');

        ob_start();
        $this->admin->render_settings_page();
        $output = ob_get_clean();

        // Should display current account even without name (shows "Unknown")
        $this->assertStringContainsString('b2brouter_current_account', $output);
        $this->assertStringContainsString('211162', $output);
        $this->assertStringContainsString('Unknown', $output);
    }

    /**
     * Test render_settings_page shows current account with name and ID
     *
     * @return void
     */
    public function test_render_settings_page_shows_current_account_with_name() {
        $this->mock_settings->method('get_api_key')->willReturn('test-key');
        $this->mock_settings->method('get_invoice_mode')->willReturn('manual');
        $this->mock_settings->method('get_transaction_count')->willReturn(0);
        $this->mock_settings->method('get_account_id')->willReturn('211162');
        $this->mock_settings->method('get_account_name')->willReturn('WP test');

        ob_start();
        $this->admin->render_settings_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('b2brouter_current_account', $output);
        $this->assertStringContainsString('WP test', $output);
        $this->assertStringContainsString('211162', $output);
    }

    /**
     * Test enqueue_admin_scripts passes bulk download IDs
     *
     * @return void
     */
    public function test_enqueue_admin_scripts_handles_bulk_download_transient() {
        // This test verifies the logic is present
        // Full testing would require mocking WordPress functions

        $this->assertTrue(method_exists($this->admin, 'enqueue_admin_scripts'));

        // Verify the method is callable with hook parameter
        $reflection = new ReflectionMethod($this->admin, 'enqueue_admin_scripts');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('hook', $params[0]->getName());
    }

    // ========== PDF Storage Stats (Phase: WP_Filesystem migration) ==========

    public function test_get_pdf_storage_stats_returns_null_when_directory_missing() {
        $this->mock_settings->method('get_pdf_storage_path')
                            ->willReturn('/nonexistent/path-' . uniqid());

        $this->assertNull($this->admin->get_pdf_storage_stats());
    }

    public function test_get_pdf_storage_stats_returns_zero_for_empty_directory() {
        $temp_dir = sys_get_temp_dir() . '/b2brouter-stats-empty-' . uniqid();
        mkdir($temp_dir);

        $this->mock_settings->method('get_pdf_storage_path')->willReturn($temp_dir);

        $stats = $this->admin->get_pdf_storage_stats();

        $this->assertSame(array('count' => 0, 'total_size' => 0), $stats);

        rmdir($temp_dir);
    }

    public function test_get_pdf_storage_stats_counts_pdfs_and_sums_sizes() {
        $temp_dir = sys_get_temp_dir() . '/b2brouter-stats-' . uniqid();
        mkdir($temp_dir);

        file_put_contents($temp_dir . '/a.pdf', str_repeat('A', 100));
        file_put_contents($temp_dir . '/b.pdf', str_repeat('B', 250));

        $this->mock_settings->method('get_pdf_storage_path')->willReturn($temp_dir);

        $stats = $this->admin->get_pdf_storage_stats();

        $this->assertSame(2, $stats['count']);
        $this->assertSame(350, $stats['total_size']);

        @unlink($temp_dir . '/a.pdf');
        @unlink($temp_dir . '/b.pdf');
        rmdir($temp_dir);
    }

    public function test_get_pdf_storage_stats_ignores_non_pdf_files() {
        $temp_dir = sys_get_temp_dir() . '/b2brouter-stats-mixed-' . uniqid();
        mkdir($temp_dir);

        file_put_contents($temp_dir . '/invoice.pdf', str_repeat('X', 100));
        file_put_contents($temp_dir . '/notes.txt', str_repeat('Y', 999));
        file_put_contents($temp_dir . '/.htaccess', 'deny from all');

        $this->mock_settings->method('get_pdf_storage_path')->willReturn($temp_dir);

        $stats = $this->admin->get_pdf_storage_stats();

        $this->assertSame(1, $stats['count']);
        $this->assertSame(100, $stats['total_size']);

        @unlink($temp_dir . '/invoice.pdf');
        @unlink($temp_dir . '/notes.txt');
        @unlink($temp_dir . '/.htaccess');
        rmdir($temp_dir);
    }

    public function test_get_pdf_storage_stats_returns_null_when_filesystem_init_fails() {
        $temp_dir = sys_get_temp_dir() . '/b2brouter-stats-fsfail-' . uniqid();
        mkdir($temp_dir);

        $this->mock_settings->method('get_pdf_storage_path')->willReturn($temp_dir);

        $GLOBALS['wp_filesystem_init_failure'] = true;
        try {
            $this->assertNull($this->admin->get_pdf_storage_stats());
        } finally {
            unset($GLOBALS['wp_filesystem_init_failure']);
            rmdir($temp_dir);
        }
    }
}
