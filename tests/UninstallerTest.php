<?php
/**
 * Tests for Uninstaller class
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\Uninstaller;

class UninstallerTest extends TestCase {

    /**
     * @var Uninstaller
     */
    private $uninstaller;

    /**
     * @var string Temporary PDF directory used by tests.
     */
    private $tmp_pdf_dir;

    public function setUp(): void {
        parent::setUp();

        global $wp_options, $wp_cron_events, $wp_transients, $wc_mock_orders;
        $wp_options = array();
        $wp_cron_events = array();
        $wp_transients = array();
        $wc_mock_orders = array();

        unset($GLOBALS['test_wc_get_orders_return']);

        $this->tmp_pdf_dir = sys_get_temp_dir() . '/b2brouter-uninstall-test-' . uniqid();

        $this->uninstaller = new Uninstaller();
    }

    public function tearDown(): void {
        parent::tearDown();

        if (is_dir($this->tmp_pdf_dir)) {
            $this->recursive_rmdir($this->tmp_pdf_dir);
        }

        unset($GLOBALS['test_wc_get_orders_return']);

        global $wp_options, $wp_cron_events, $wp_transients, $wc_mock_orders;
        $wp_options = array();
        $wp_cron_events = array();
        $wp_transients = array();
        $wc_mock_orders = array();
    }

    public function test_should_delete_archival_data_defaults_to_false(): void {
        $this->assertFalse($this->uninstaller->should_delete_archival_data());
    }

    public function test_should_delete_archival_data_respects_option(): void {
        update_option('b2brouter_delete_archival_data', '1');
        $this->assertTrue($this->uninstaller->should_delete_archival_data());

        update_option('b2brouter_delete_archival_data', '0');
        $this->assertFalse($this->uninstaller->should_delete_archival_data());
    }

    public function test_unschedule_cron_clears_all_plugin_hooks(): void {
        global $wp_cron_events;
        $wp_cron_events = array(
            'b2brouter_sync_invoice_status' => array('timestamp' => 1000, 'recurrence' => 'hourly'),
            'b2brouter_cleanup_old_pdfs'    => array('timestamp' => 2000, 'recurrence' => 'daily'),
            'b2brouter_sync_single_invoice' => array('timestamp' => 3000),
            'unrelated_third_party_hook'    => array('timestamp' => 4000),
        );

        $this->uninstaller->unschedule_cron();

        $this->assertArrayNotHasKey('b2brouter_sync_invoice_status', $wp_cron_events);
        $this->assertArrayNotHasKey('b2brouter_cleanup_old_pdfs', $wp_cron_events);
        $this->assertArrayNotHasKey('b2brouter_sync_single_invoice', $wp_cron_events);
        $this->assertArrayHasKey('unrelated_third_party_hook', $wp_cron_events, 'third-party cron hooks must not be touched');
    }

    public function test_delete_order_meta_default_preserves_archival_and_tin(): void {
        $order = $this->make_order_with_invoice_meta(101);

        $this->uninstaller->delete_order_meta(false);

        $this->assertSame('', $order->get_meta('_b2brouter_invoice_status'));
        $this->assertSame('', $order->get_meta('_b2brouter_invoice_status_updated'));
        $this->assertSame('', $order->get_meta('_b2brouter_invoice_status_error'));
        $this->assertSame('', $order->get_meta('_b2brouter_last_webhook_received'));
        $this->assertSame('', $order->get_meta('_b2brouter_invoice_pdf_path'));
        $this->assertSame('', $order->get_meta('_b2brouter_invoice_pdf_filename'));
        $this->assertSame('', $order->get_meta('_b2brouter_invoice_pdf_size'));
        $this->assertSame('', $order->get_meta('_b2brouter_invoice_pdf_date'));

        $this->assertSame('inv-uuid-1', $order->get_meta('_b2brouter_invoice_id'));
        $this->assertSame('FAC-2026-001', $order->get_meta('_b2brouter_invoice_number'));
        $this->assertSame('FAC', $order->get_meta('_b2brouter_invoice_series_code'));
        $this->assertSame('2026-03-01 10:00:00', $order->get_meta('_b2brouter_invoice_date'));

        $this->assertSame('ES12345678Z', $order->get_meta('_billing_tin'));
    }

    public function test_delete_order_meta_opt_in_removes_archival_but_preserves_tin(): void {
        $order = $this->make_order_with_invoice_meta(202);

        $this->uninstaller->delete_order_meta(true);

        // Ephemeral removed
        $this->assertSame('', $order->get_meta('_b2brouter_invoice_pdf_path'));
        // Archival removed
        $this->assertSame('', $order->get_meta('_b2brouter_invoice_id'));
        $this->assertSame('', $order->get_meta('_b2brouter_invoice_number'));
        $this->assertSame('', $order->get_meta('_b2brouter_invoice_series_code'));
        $this->assertSame('', $order->get_meta('_b2brouter_invoice_date'));

        // TIN preserved on the "clean wipe" branch too
        $this->assertSame('ES12345678Z', $order->get_meta('_billing_tin'));
    }

    public function test_delete_order_meta_noop_when_woocommerce_unavailable(): void {
        // Without wc_get_orders, the method should simply return. We can't
        // unload the already-defined mock, but we can prove the method handles
        // an empty result gracefully.
        $GLOBALS['test_wc_get_orders_return'] = array();

        $this->uninstaller->delete_order_meta(true);

        $this->assertTrue(true); // no exception thrown
    }

    public function test_delete_pdf_directory_removes_files_and_dir(): void {
        mkdir($this->tmp_pdf_dir, 0777, true);
        file_put_contents($this->tmp_pdf_dir . '/invoice-1.pdf', 'fake pdf');
        file_put_contents($this->tmp_pdf_dir . '/.htaccess', '# deny all');
        file_put_contents($this->tmp_pdf_dir . '/index.php', '<?php // silence');
        mkdir($this->tmp_pdf_dir . '/sub', 0777, true);
        file_put_contents($this->tmp_pdf_dir . '/sub/nested.pdf', 'nested');

        update_option('b2brouter_pdf_storage_path', $this->tmp_pdf_dir);

        $this->uninstaller->delete_pdf_directory();

        $this->assertFalse(is_dir($this->tmp_pdf_dir));
    }

    public function test_delete_pdf_directory_is_safe_when_dir_missing(): void {
        update_option('b2brouter_pdf_storage_path', $this->tmp_pdf_dir . '/does-not-exist');

        $this->uninstaller->delete_pdf_directory();

        $this->assertTrue(true); // no exception
    }

    public function test_remove_directory_logs_warning_when_unlink_fails(): void {
        if (DIRECTORY_SEPARATOR === '\\' || (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
            $this->markTestSkipped('Filesystem permission test requires non-root POSIX');
        }

        mkdir($this->tmp_pdf_dir, 0777, true);
        $blocked_file = $this->tmp_pdf_dir . '/blocked.pdf';
        file_put_contents($blocked_file, 'cannot delete me');
        chmod($this->tmp_pdf_dir, 0555); // r-x: prevents unlink/rmdir inside

        update_option('b2brouter_pdf_storage_path', $this->tmp_pdf_dir);
        $GLOBALS['wc_logger_calls'] = array();

        try {
            $this->uninstaller->delete_pdf_directory();
        } finally {
            chmod($this->tmp_pdf_dir, 0777);
            @unlink($blocked_file);
            @rmdir($this->tmp_pdf_dir);
        }

        $this->assertArrayHasKey('warning', $GLOBALS['wc_logger_calls']);
        $messages = array_column($GLOBALS['wc_logger_calls']['warning'], 'message');
        $matched = array_filter($messages, function ($m) use ($blocked_file) {
            return strpos($m, $blocked_file) !== false;
        });
        $this->assertNotEmpty($matched, 'expected a warning mentioning the blocked file');
    }

    public function test_delete_options_and_transients_clears_plugin_options(): void {
        global $wp_options, $wp_transients;

        $wp_options = array(
            'b2brouter_api_key'                 => 'secret',
            'b2brouter_account_id'              => 'acct-1',
            'b2brouter_environment'             => 'production',
            'b2brouter_invoice_mode'            => 'automatic',
            'b2brouter_webhook_secret'          => 'wh-secret',
            'b2brouter_status_sync_last_run'    => 1700000000,
            'b2brouter_delete_archival_data'    => '1',
            'some_other_plugin_option'          => 'keep-me',
        );

        $wp_transients = array(
            'b2brouter_validated_accounts' => array('fake'),
            'unrelated_transient'          => 'keep',
        );

        $this->uninstaller->delete_options_and_transients();

        foreach (Uninstaller::OPTION_KEYS as $option) {
            $this->assertArrayNotHasKey($option, $wp_options, "option {$option} should be deleted");
        }
        $this->assertArrayHasKey('some_other_plugin_option', $wp_options, 'unrelated options must be preserved');

        $this->assertArrayNotHasKey('b2brouter_validated_accounts', $wp_transients);
        $this->assertArrayHasKey('unrelated_transient', $wp_transients);
    }

    public function test_ephemeral_keys_constant_matches_documented_keys(): void {
        $expected = array(
            '_b2brouter_invoice_status',
            '_b2brouter_invoice_status_updated',
            '_b2brouter_invoice_status_error',
            '_b2brouter_last_webhook_received',
            '_b2brouter_invoice_pdf_path',
            '_b2brouter_invoice_pdf_filename',
            '_b2brouter_invoice_pdf_size',
            '_b2brouter_invoice_pdf_date',
        );
        $this->assertSame($expected, Uninstaller::EPHEMERAL_META_KEYS);
    }

    public function test_archival_keys_constant_matches_documented_keys(): void {
        $expected = array(
            '_b2brouter_invoice_id',
            '_b2brouter_invoice_number',
            '_b2brouter_invoice_series_code',
            '_b2brouter_invoice_date',
        );
        $this->assertSame($expected, Uninstaller::ARCHIVAL_META_KEYS);
    }

    /**
     * Create a mock WC_Order populated with the full B2Brouter meta set plus
     * a _billing_tin, and register it so wc_get_orders returns its ID.
     */
    private function make_order_with_invoice_meta($order_id): WC_Order {
        global $wc_mock_orders;

        $order = new WC_Order($order_id);
        $order->add_meta_data('_b2brouter_invoice_id', 'inv-uuid-1');
        $order->add_meta_data('_b2brouter_invoice_number', 'FAC-2026-001');
        $order->add_meta_data('_b2brouter_invoice_series_code', 'FAC');
        $order->add_meta_data('_b2brouter_invoice_date', '2026-03-01 10:00:00');
        $order->add_meta_data('_b2brouter_invoice_status', 'sent');
        $order->add_meta_data('_b2brouter_invoice_status_updated', time());
        $order->add_meta_data('_b2brouter_invoice_status_error', 'stale error');
        $order->add_meta_data('_b2brouter_last_webhook_received', time());
        $order->add_meta_data('_b2brouter_invoice_pdf_path', '/tmp/invoice.pdf');
        $order->add_meta_data('_b2brouter_invoice_pdf_filename', 'invoice.pdf');
        $order->add_meta_data('_b2brouter_invoice_pdf_size', 1234);
        $order->add_meta_data('_b2brouter_invoice_pdf_date', '2026-03-01 10:00:00');
        $order->add_meta_data('_billing_tin', 'ES12345678Z');

        $wc_mock_orders[$order_id] = $order;
        $GLOBALS['test_wc_get_orders_return'] = array($order_id);

        return $order;
    }

    private function recursive_rmdir(string $dir): void {
        $entries = @scandir($dir);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $dir . '/' . $entry;
            if (is_dir($full) && !is_link($full)) {
                $this->recursive_rmdir($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($dir);
    }
}
