<?php
/**
 * Comprehensive tests for Status_Sync class
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
     * Settings instance
     *
     * @var Settings
     */
    private $settings;

    /**
     * Invoice_Generator instance
     *
     * @var Invoice_Generator
     */
    private $invoice_generator;

    /**
     * Set up test
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();

        // Reset globals
        global $wp_options, $wp_cron_events, $wc_mock_orders;
        $wp_options = array();
        $wp_cron_events = array();
        $wc_mock_orders = array();

        // Create real Settings and Invoice_Generator instances
        $this->settings = new Settings();
        $this->invoice_generator = new Invoice_Generator($this->settings);

        // Create Status_Sync instance
        $this->status_sync = new Status_Sync(
            $this->settings,
            $this->invoice_generator
        );
    }

    /**
     * Tear down test
     *
     * @return void
     */
    public function tearDown(): void {
        parent::tearDown();

        // Clean up globals
        global $wp_options, $wp_cron_events, $wc_mock_orders;
        $wp_options = array();
        $wp_cron_events = array();
        $wc_mock_orders = array();
        unset($GLOBALS['mock_wp_timezone']);
    }

    // ========== Final States Tests ==========

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

    // ========== should_sync (polling eligibility) Tests ==========

    /**
     * Regression for #8: finalized invoices are never re-polled, regardless
     * of how long ago they were last checked.
     *
     * @return void
     */
    public function test_should_sync_final_state_never_polled() {
        $now = 1_700_000_000;
        $two_hours_ago = $now - (2 * HOUR_IN_SECONDS);
        $one_day_ago = $now - DAY_IN_SECONDS;

        foreach (Status_Sync::FINAL_STATES as $state) {
            $this->assertFalse(
                $this->status_sync->should_sync($state, $two_hours_ago, $one_day_ago, $now),
                "Final state '{$state}' must not be re-polled"
            );
        }
    }

    public function test_should_sync_empty_status_always_polled() {
        $now = 1_700_000_000;
        $this->assertTrue($this->status_sync->should_sync('', 0, 0, $now));
        $this->assertTrue($this->status_sync->should_sync('', $now - 60, $now - 60, $now));
    }

    public function test_should_sync_non_final_never_synced_polls_immediately() {
        $now = 1_700_000_000;
        // status_updated = 0 means we've never polled for status
        $this->assertTrue($this->status_sync->should_sync('draft', 0, $now, $now));
    }

    public function test_should_sync_young_invoice_hourly_tier() {
        $now = 1_700_000_000;
        $created = $now - (6 * HOUR_IN_SECONDS); // 6h old → hourly tier

        // Polled 30 min ago → skip
        $this->assertFalse(
            $this->status_sync->should_sync('draft', $now - 1800, $created, $now)
        );
        // Polled 61 min ago → poll
        $this->assertTrue(
            $this->status_sync->should_sync('draft', $now - (HOUR_IN_SECONDS + 60), $created, $now)
        );
    }

    public function test_should_sync_mid_age_invoice_six_hourly_tier() {
        $now = 1_700_000_000;
        $created = $now - (3 * DAY_IN_SECONDS); // 3 days old → 6h tier

        // Polled 3h ago → skip
        $this->assertFalse(
            $this->status_sync->should_sync('draft', $now - (3 * HOUR_IN_SECONDS), $created, $now)
        );
        // Polled 7h ago → poll
        $this->assertTrue(
            $this->status_sync->should_sync('draft', $now - (7 * HOUR_IN_SECONDS), $created, $now)
        );
    }

    public function test_should_sync_old_invoice_daily_tier() {
        $now = 1_700_000_000;
        $created = $now - (30 * DAY_IN_SECONDS); // 30 days old → daily tier

        // Polled 12h ago → skip
        $this->assertFalse(
            $this->status_sync->should_sync('error', $now - (12 * HOUR_IN_SECONDS), $created, $now)
        );
        // Polled 25h ago → poll
        $this->assertTrue(
            $this->status_sync->should_sync('error', $now - (25 * HOUR_IN_SECONDS), $created, $now)
        );
    }

    /**
     * If invoice_created is unknown (0), behaviour must not silently skip
     * forever — treat as youngest tier so it still gets checked hourly.
     *
     * @return void
     */
    public function test_should_sync_missing_invoice_created_falls_back_to_hourly() {
        $now = 1_700_000_000;

        $this->assertFalse(
            $this->status_sync->should_sync('draft', $now - 1800, 0, $now)
        );
        $this->assertTrue(
            $this->status_sync->should_sync('draft', $now - (HOUR_IN_SECONDS + 60), 0, $now)
        );
    }

    public function test_should_sync_tier_boundaries() {
        $now = 1_700_000_000;

        // Exactly 24h old → crosses into 6h tier
        $at_24h = $now - DAY_IN_SECONDS;
        $this->assertFalse(
            $this->status_sync->should_sync('draft', $now - (5 * HOUR_IN_SECONDS), $at_24h, $now)
        );
        $this->assertTrue(
            $this->status_sync->should_sync('draft', $now - (6 * HOUR_IN_SECONDS + 1), $at_24h, $now)
        );

        // Exactly 7d old → crosses into daily tier
        $at_7d = $now - (7 * DAY_IN_SECONDS);
        $this->assertFalse(
            $this->status_sync->should_sync('draft', $now - (23 * HOUR_IN_SECONDS), $at_7d, $now)
        );
        $this->assertTrue(
            $this->status_sync->should_sync('draft', $now - (DAY_IN_SECONDS + 1), $at_7d, $now)
        );
    }

    // ========== parse_invoice_date_meta (timezone) Tests ==========

    public function test_parse_invoice_date_meta_empty_returns_zero() {
        $this->assertSame(0, $this->status_sync->parse_invoice_date_meta(''));
        $this->assertSame(0, $this->status_sync->parse_invoice_date_meta(null));
    }

    public function test_parse_invoice_date_meta_malformed_returns_zero() {
        $this->assertSame(0, $this->status_sync->parse_invoice_date_meta('not a date'));
        $this->assertSame(0, $this->status_sync->parse_invoice_date_meta('2025-13-40 99:99:99'));
    }

    /**
     * Regression: current_time('mysql') writes site-local time. Parsing it
     * naively with strtotime() (PHP default tz = UTC) would offset the
     * resulting timestamp by the site's UTC offset. Verify the helper parses
     * the same string in wp_timezone() and produces a UTC-comparable ts.
     *
     * @return void
     */
    public function test_parse_invoice_date_meta_honours_wp_timezone() {
        $GLOBALS['mock_wp_timezone'] = new DateTimeZone('Europe/Madrid');

        // Europe/Madrid is UTC+1 in January (no DST).
        // "2025-01-15 12:00:00" in Madrid == "2025-01-15 11:00:00" UTC.
        $expected = (new DateTime('2025-01-15 11:00:00', new DateTimeZone('UTC')))->getTimestamp();
        $actual = $this->status_sync->parse_invoice_date_meta('2025-01-15 12:00:00');

        $this->assertSame($expected, $actual);

        // And confirm it does NOT match the naive strtotime() result, which
        // would interpret the string in PHP's default (UTC) timezone.
        $naive = strtotime('2025-01-15 12:00:00');
        $this->assertNotSame($naive, $actual, 'Helper must not fall back to PHP default tz');
        $this->assertSame(3600, $naive - $actual, 'Offset should match Madrid UTC+1');
    }

    public function test_parse_invoice_date_meta_utc_roundtrip() {
        $GLOBALS['mock_wp_timezone'] = new DateTimeZone('UTC');

        $expected = (new DateTime('2025-06-01 09:30:00', new DateTimeZone('UTC')))->getTimestamp();
        $this->assertSame(
            $expected,
            $this->status_sync->parse_invoice_date_meta('2025-06-01 09:30:00')
        );
    }

    // ========== Cron Scheduling Tests ==========

    /**
     * Test activate schedules hourly cron when webhooks disabled
     *
     * @return void
     */
    public function test_activate_schedules_hourly_when_webhooks_disabled() {
        global $wp_cron_events;

        $this->settings->set_webhook_enabled(false);

        $this->status_sync->activate();

        $this->assertArrayHasKey('b2brouter_sync_invoice_status', $wp_cron_events);
        $this->assertEquals('hourly', $wp_cron_events['b2brouter_sync_invoice_status']['recurrence']);
    }

    /**
     * Test activate schedules six_hourly when webhooks enabled with fallback
     *
     * @return void
     */
    public function test_activate_schedules_six_hourly_with_fallback() {
        global $wp_cron_events;

        $this->settings->set_webhook_enabled(true);
        $this->settings->set_webhook_fallback_polling(true);

        $this->status_sync->activate();

        $this->assertArrayHasKey('b2brouter_sync_invoice_status', $wp_cron_events);
        $this->assertEquals('six_hourly', $wp_cron_events['b2brouter_sync_invoice_status']['recurrence']);
    }

    /**
     * Test activate does not schedule when webhooks enabled without fallback
     *
     * @return void
     */
    public function test_activate_no_schedule_when_webhooks_only() {
        global $wp_cron_events;

        $this->settings->set_webhook_enabled(true);
        $this->settings->set_webhook_fallback_polling(false);

        $this->status_sync->activate();

        $this->assertArrayNotHasKey('b2brouter_sync_invoice_status', $wp_cron_events);
    }

    /**
     * Test activate does not schedule if already scheduled
     *
     * @return void
     */
    public function test_activate_skips_if_already_scheduled() {
        global $wp_cron_events;

        // Pre-schedule the event
        wp_schedule_event(time() + 3600, 'hourly', 'b2brouter_sync_invoice_status');
        $original_timestamp = $wp_cron_events['b2brouter_sync_invoice_status']['timestamp'];

        $this->status_sync->activate();

        // Should not reschedule
        $this->assertEquals($original_timestamp, $wp_cron_events['b2brouter_sync_invoice_status']['timestamp']);
    }

    /**
     * Test deactivate clears scheduled cron
     *
     * @return void
     */
    public function test_deactivate_clears_scheduled_cron() {
        global $wp_cron_events;

        // Schedule the event
        wp_schedule_event(time() + 3600, 'hourly', 'b2brouter_sync_invoice_status');
        $this->assertArrayHasKey('b2brouter_sync_invoice_status', $wp_cron_events);

        $this->status_sync->deactivate();

        $this->assertArrayNotHasKey('b2brouter_sync_invoice_status', $wp_cron_events);
    }

    /**
     * Test deactivate handles no scheduled cron
     *
     * @return void
     */
    public function test_deactivate_handles_no_scheduled_cron() {
        global $wp_cron_events;

        // No cron scheduled
        $this->assertArrayNotHasKey('b2brouter_sync_invoice_status', $wp_cron_events);

        // Should not throw error
        $this->status_sync->deactivate();

        $this->assertArrayNotHasKey('b2brouter_sync_invoice_status', $wp_cron_events);
    }

    /**
     * Test reschedule_cron changes from hourly to six_hourly
     *
     * @return void
     */
    public function test_reschedule_cron_changes_schedule() {
        global $wp_cron_events;

        // Start with hourly
        $this->settings->set_webhook_enabled(false);
        $this->status_sync->activate();
        $this->assertEquals('hourly', $wp_cron_events['b2brouter_sync_invoice_status']['recurrence']);

        // Change to webhooks with fallback
        $this->settings->set_webhook_enabled(true);
        $this->settings->set_webhook_fallback_polling(true);
        $this->status_sync->reschedule_cron();

        $this->assertEquals('six_hourly', $wp_cron_events['b2brouter_sync_invoice_status']['recurrence']);
    }

    /**
     * Test reschedule_cron removes schedule when webhooks only
     *
     * @return void
     */
    public function test_reschedule_cron_removes_schedule_for_webhooks_only() {
        global $wp_cron_events;

        // Start with hourly
        $this->settings->set_webhook_enabled(false);
        $this->status_sync->activate();
        $this->assertArrayHasKey('b2brouter_sync_invoice_status', $wp_cron_events);

        // Change to webhooks only
        $this->settings->set_webhook_enabled(true);
        $this->settings->set_webhook_fallback_polling(false);
        $this->status_sync->reschedule_cron();

        $this->assertArrayNotHasKey('b2brouter_sync_invoice_status', $wp_cron_events);
    }

    /**
     * Test reschedule_cron adds schedule when switching from webhooks to polling
     *
     * @return void
     */
    public function test_reschedule_cron_adds_schedule_when_disabling_webhooks() {
        global $wp_cron_events;

        // Start with webhooks only (no schedule)
        $this->settings->set_webhook_enabled(true);
        $this->settings->set_webhook_fallback_polling(false);
        $this->status_sync->activate();
        $this->assertArrayNotHasKey('b2brouter_sync_invoice_status', $wp_cron_events);

        // Disable webhooks
        $this->settings->set_webhook_enabled(false);
        $this->status_sync->reschedule_cron();

        $this->assertArrayHasKey('b2brouter_sync_invoice_status', $wp_cron_events);
        $this->assertEquals('hourly', $wp_cron_events['b2brouter_sync_invoice_status']['recurrence']);
    }

    /**
     * Test add_custom_schedules adds six_hourly schedule
     *
     * @return void
     */
    public function test_add_custom_schedules() {
        $schedules = array();
        $result = $this->status_sync->add_custom_schedules($schedules);

        $this->assertArrayHasKey('six_hourly', $result);
        $this->assertEquals(21600, $result['six_hourly']['interval']); // 6 hours in seconds
        $this->assertEquals('Every 6 Hours', $result['six_hourly']['display']);
    }

    /**
     * Test add_custom_schedules preserves existing schedules
     *
     * @return void
     */
    public function test_add_custom_schedules_preserves_existing() {
        $schedules = array(
            'hourly' => array('interval' => 3600, 'display' => 'Once Hourly'),
            'daily' => array('interval' => 86400, 'display' => 'Once Daily')
        );
        $result = $this->status_sync->add_custom_schedules($schedules);

        $this->assertArrayHasKey('hourly', $result);
        $this->assertArrayHasKey('daily', $result);
        $this->assertArrayHasKey('six_hourly', $result);
        $this->assertCount(3, $result);
    }

    // ========== Get Invoice Status Tests ==========

    /**
     * Test get_invoice_status with no order
     *
     * @return void
     */
    public function test_get_invoice_status_no_order() {
        $result = $this->status_sync->get_invoice_status(999999);

        $this->assertNull($result);
    }

    /**
     * Test get_invoice_status returns status when set
     *
     * @return void
     */
    public function test_get_invoice_status_returns_status() {
        global $wc_mock_orders;

        $order = new WC_Order(123);
        $order->update_meta_data('_b2brouter_invoice_status', 'sent');
        $wc_mock_orders[123] = $order;

        $result = $this->status_sync->get_invoice_status(123);

        $this->assertEquals('sent', $result);
    }

    /**
     * Test get_invoice_status returns null when no status
     *
     * @return void
     */
    public function test_get_invoice_status_returns_null_when_not_set() {
        global $wc_mock_orders;

        $order = new WC_Order(123);
        $wc_mock_orders[123] = $order;

        $result = $this->status_sync->get_invoice_status(123);

        $this->assertNull($result);
    }

    // ========== Sync Single Invoice Tests ==========

    /**
     * Test sync_single_invoice fails with invalid order
     *
     * @return void
     */
    public function test_sync_single_invoice_invalid_order() {
        $result = $this->status_sync->sync_single_invoice(999999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Order not found', $result['message']);
    }

    /**
     * Test sync_single_invoice fails without invoice ID
     *
     * @return void
     */
    public function test_sync_single_invoice_no_invoice_id() {
        global $wc_mock_orders;

        $order = new WC_Order(123);
        $wc_mock_orders[123] = $order;

        $result = $this->status_sync->sync_single_invoice(123);

        $this->assertFalse($result['success']);
        $this->assertEquals('No invoice ID found', $result['message']);
    }

    /**
     * Test sync_single_invoice fails without API key
     *
     * @return void
     */
    public function test_sync_single_invoice_no_api_key() {
        global $wc_mock_orders;

        $order = new WC_Order(123);
        $order->update_meta_data('_b2brouter_invoice_id', 456);
        $wc_mock_orders[123] = $order;

        // No API key set
        $result = $this->status_sync->sync_single_invoice(123);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('API key', $result['message']);
    }

    /**
     * Test sync_single_invoice_scheduled calls sync_single_invoice
     *
     * @return void
     */
    public function test_sync_single_invoice_scheduled() {
        global $wc_mock_orders;

        $order = new WC_Order(123);
        $wc_mock_orders[123] = $order;

        // Should call sync_single_invoice internally
        $this->status_sync->sync_single_invoice_scheduled(123);

        // No exception should be thrown
        $this->assertTrue(true);
    }

    // ========== Manual Sync Tests ==========

    /**
     * Test manual_sync delegates to sync_single_invoice
     *
     * @return void
     */
    public function test_manual_sync_delegates_to_sync_single_invoice() {
        global $wc_mock_orders;

        $order = new WC_Order(123);
        $wc_mock_orders[123] = $order;

        $result = $this->status_sync->manual_sync(123);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }

    /**
     * Test manual_sync returns same result as sync_single_invoice
     *
     * @return void
     */
    public function test_manual_sync_returns_same_result() {
        $result1 = $this->status_sync->manual_sync(999999);
        $result2 = $this->status_sync->sync_single_invoice(999999);

        $this->assertEquals($result1, $result2);
    }

    // ========== Sync Batch Tests ==========

    /**
     * Test sync_batch returns zero counts when no orders
     *
     * @return void
     */
    public function test_sync_batch_no_orders() {
        $result = $this->status_sync->sync_batch();

        $this->assertEquals(0, $result['synced']);
        $this->assertEquals(0, $result['errors']);
    }

    /**
     * Test sync_batch returns proper structure
     *
     * @return void
     */
    public function test_sync_batch_returns_proper_structure() {
        $result = $this->status_sync->sync_batch();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('synced', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertIsInt($result['synced']);
        $this->assertIsInt($result['errors']);
    }

    /**
     * Test sync_batch does not update last run when no orders
     *
     * @return void
     */
    public function test_sync_batch_no_last_run_update_when_no_orders() {
        global $wp_options;

        // sync_batch returns early when no orders - doesn't update last_run
        $this->status_sync->sync_batch();

        $last_run = get_option('b2brouter_status_sync_last_run');

        $this->assertFalse($last_run);
    }

    // ========== Status Colors Tests ==========

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

    // ========== Integration Tests ==========

    /**
     * Test complete workflow: activate -> sync -> deactivate
     *
     * @return void
     */
    public function test_complete_workflow() {
        global $wp_cron_events, $wp_options;

        // Activate
        $this->settings->set_webhook_enabled(false);
        $this->status_sync->activate();
        $this->assertArrayHasKey('b2brouter_sync_invoice_status', $wp_cron_events);

        // Sync batch
        $result = $this->status_sync->sync_batch();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('synced', $result);
        $this->assertEquals(0, $result['synced']); // No orders to sync

        // Deactivate
        $this->status_sync->deactivate();
        $this->assertArrayNotHasKey('b2brouter_sync_invoice_status', $wp_cron_events);
    }

    /**
     * Test webhook fallback logic
     *
     * @return void
     */
    public function test_webhook_fallback_logic() {
        global $wp_cron_events;

        // Start with polling only
        $this->settings->set_webhook_enabled(false);
        $this->status_sync->activate();
        $this->assertEquals('hourly', $wp_cron_events['b2brouter_sync_invoice_status']['recurrence']);

        // Enable webhooks with fallback
        $this->settings->set_webhook_enabled(true);
        $this->settings->set_webhook_fallback_polling(true);
        $this->status_sync->reschedule_cron();
        $this->assertEquals('six_hourly', $wp_cron_events['b2brouter_sync_invoice_status']['recurrence']);

        // Disable fallback (webhooks only)
        $this->settings->set_webhook_fallback_polling(false);
        $this->status_sync->reschedule_cron();
        $this->assertArrayNotHasKey('b2brouter_sync_invoice_status', $wp_cron_events);

        // Back to polling
        $this->settings->set_webhook_enabled(false);
        $this->status_sync->reschedule_cron();
        $this->assertEquals('hourly', $wp_cron_events['b2brouter_sync_invoice_status']['recurrence']);
    }

    /**
     * Test that Status_Sync respects webhook settings
     *
     * @return void
     */
    public function test_respects_webhook_settings() {
        global $wp_cron_events;

        // Test all webhook configuration combinations
        $test_cases = array(
            // [webhooks_enabled, fallback_polling, expected_schedule]
            array(false, false, 'hourly'),
            array(false, true, 'hourly'),
            array(true, false, null), // No polling
            array(true, true, 'six_hourly'),
        );

        foreach ($test_cases as $case) {
            list($webhooks, $fallback, $expected) = $case;

            // Reset cron
            global $wp_cron_events;
            $wp_cron_events = array();

            // Configure settings
            $this->settings->set_webhook_enabled($webhooks);
            $this->settings->set_webhook_fallback_polling($fallback);

            // Activate
            $this->status_sync->activate();

            if ($expected === null) {
                $this->assertArrayNotHasKey('b2brouter_sync_invoice_status', $wp_cron_events,
                    "Failed for webhooks={$webhooks}, fallback={$fallback}");
            } else {
                $this->assertArrayHasKey('b2brouter_sync_invoice_status', $wp_cron_events,
                    "Failed for webhooks={$webhooks}, fallback={$fallback}");
                $this->assertEquals($expected, $wp_cron_events['b2brouter_sync_invoice_status']['recurrence'],
                    "Failed for webhooks={$webhooks}, fallback={$fallback}");
            }
        }
    }
}
