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
        $this->assertEquals('B2Brouter', $wp_menu_pages['b2brouter']['page_title']);

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
        $this->assertStringContainsString('page=b2brouter', $first_link);
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

        $this->assertStringContainsString('B2Brouter Settings', $output);
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

        $this->assertStringContainsString('Welcome to B2Brouter', $output);
        $this->assertStringContainsString('Electronic Invoicing', $output);
    }
}
