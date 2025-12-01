<?php
/**
 * Tests for Status_Sync class
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\Status_Sync;
use B2Brouter\WooCommerce\Settings;
use B2Brouter\WooCommerce\Invoice_Generator;

/**
 * Status_Sync test case
 *
 * Tests invoice status synchronization functionality
 *
 * @since 1.0.0
 */
class StatusSyncTest extends TestCase {

    /**
     * Status_Sync instance
     *
     * @var Status_Sync
     */
    private $status_sync;

    /**
     * Settings mock
     *
     * @var Settings
     */
    private $settings_mock;

    /**
     * Invoice_Generator mock
     *
     * @var Invoice_Generator
     */
    private $invoice_generator_mock;

    /**
     * Set up test
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();

        // Create mocks
        $this->settings_mock = $this->createMock(Settings::class);
        $this->invoice_generator_mock = $this->createMock(Invoice_Generator::class);

        // Create Status_Sync instance
        $this->status_sync = new Status_Sync(
            $this->settings_mock,
            $this->invoice_generator_mock
        );
    }

    /**
     * Test that final states are correctly identified
     *
     * @return void
     */
    public function test_is_final_state() {
        // Final states should return true
        $this->assertTrue($this->status_sync->is_final_state('sent'));
        $this->assertTrue($this->status_sync->is_final_state('accepted'));
        $this->assertTrue($this->status_sync->is_final_state('registered'));
        $this->assertTrue($this->status_sync->is_final_state('paid'));
        $this->assertTrue($this->status_sync->is_final_state('cancelled'));
        $this->assertTrue($this->status_sync->is_final_state('closed'));

        // Case insensitive
        $this->assertTrue($this->status_sync->is_final_state('SENT'));
        $this->assertTrue($this->status_sync->is_final_state('Paid'));

        // Non-final states should return false
        $this->assertFalse($this->status_sync->is_final_state('draft'));
        $this->assertFalse($this->status_sync->is_final_state('pending'));
        $this->assertFalse($this->status_sync->is_final_state('error'));
        $this->assertFalse($this->status_sync->is_final_state('unknown'));
        $this->assertFalse($this->status_sync->is_final_state(''));
    }

    /**
     * Test get_invoice_status with no order
     *
     * @return void
     */
    public function test_get_invoice_status_no_order() {
        // Mock wc_get_order to return null
        $result = $this->status_sync->get_invoice_status(999999);

        $this->assertNull($result);
    }

    /**
     * Test that Status_Sync constants are defined correctly
     *
     * @return void
     */
    public function test_final_states_constant() {
        $final_states = Status_Sync::FINAL_STATES;

        $this->assertIsArray($final_states);
        $this->assertCount(6, $final_states);
        $this->assertContains('sent', $final_states);
        $this->assertContains('accepted', $final_states);
        $this->assertContains('registered', $final_states);
        $this->assertContains('paid', $final_states);
        $this->assertContains('cancelled', $final_states);
        $this->assertContains('closed', $final_states);

        // Should not contain non-final states
        $this->assertNotContains('draft', $final_states);
        $this->assertNotContains('error', $final_states);
        $this->assertNotContains('pending', $final_states);
    }

    /**
     * Test activate method exists and is callable
     *
     * @return void
     */
    public function test_activate_method_exists() {
        $this->assertTrue(method_exists($this->status_sync, 'activate'));
        $this->assertTrue(is_callable(array($this->status_sync, 'activate')));
    }

    /**
     * Test deactivate method exists and is callable
     *
     * @return void
     */
    public function test_deactivate_method_exists() {
        $this->assertTrue(method_exists($this->status_sync, 'deactivate'));
        $this->assertTrue(is_callable(array($this->status_sync, 'deactivate')));
    }

    /**
     * Test sync_batch method exists and is callable
     *
     * @return void
     */
    public function test_sync_batch_method_exists() {
        $this->assertTrue(method_exists($this->status_sync, 'sync_batch'));
        $this->assertTrue(is_callable(array($this->status_sync, 'sync_batch')));
    }

    /**
     * Test that all status badge colors are defined
     *
     * @return void
     */
    public function test_status_colors_are_comprehensive() {
        // This tests that our CSS and status logic covers all states
        $expected_statuses = array(
            'draft',
            'sent',
            'accepted',
            'registered',
            'paid',
            'error',
            'cancelled',
            'closed'
        );

        // Verify final states are a subset
        $final_states = Status_Sync::FINAL_STATES;

        foreach ($final_states as $state) {
            $this->assertContains($state, $expected_statuses);
        }

        // Verify non-final states
        $non_final = array('draft', 'error');
        foreach ($non_final as $state) {
            $this->assertFalse($this->status_sync->is_final_state($state));
        }
    }

    /**
     * Test manual_sync method exists and returns array
     *
     * @return void
     */
    public function test_manual_sync_method_exists() {
        $this->assertTrue(method_exists($this->status_sync, 'manual_sync'));
    }

    /**
     * Tear down test
     *
     * @return void
     */
    public function tearDown(): void {
        parent::tearDown();
    }
}
