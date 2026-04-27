<?php
/**
 * Uninstall handler
 *
 * @package B2Brouter\WooCommerce
 * @since 0.9.4
 */

namespace B2Brouter\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Uninstaller class
 *
 * Removes plugin data when the user deletes the plugin. The logic is kept in a
 * class (rather than directly in uninstall.php) so it can be exercised by the
 * test suite.
 *
 * @since 0.9.4
 */
class Uninstaller {

    /**
     * Order meta keys that are always removed.
     *
     * These keys are either live state that becomes stale once the plugin is
     * gone, or pointers to PDF files that are being deleted anyway.
     */
    const EPHEMERAL_META_KEYS = array(
        '_b2brouter_invoice_status',
        '_b2brouter_invoice_status_updated',
        '_b2brouter_invoice_status_error',
        '_b2brouter_last_webhook_received',
        '_b2brouter_invoice_pdf_path',
        '_b2brouter_invoice_pdf_filename',
        '_b2brouter_invoice_pdf_size',
        '_b2brouter_invoice_pdf_date',
    );

    /**
     * Order meta keys that hold tax-audit references and are preserved by
     * default. Only removed when the "delete archival data" option is on.
     */
    const ARCHIVAL_META_KEYS = array(
        '_b2brouter_invoice_id',
        '_b2brouter_invoice_number',
        '_b2brouter_invoice_series_code',
        '_b2brouter_invoice_date',
    );

    /**
     * Cron hooks registered by the plugin.
     */
    const CRON_HOOKS = array(
        'b2brouter_sync_invoice_status',
        'b2brouter_cleanup_old_pdfs',
        'b2brouter_sync_single_invoice',
    );

    /**
     * All b2brouter_* wp_options keys written by the plugin.
     *
     * Kept as an explicit list rather than a LIKE query so we never match
     * unrelated options that happen to share the prefix.
     */
    const OPTION_KEYS = array(
        'b2brouter_api_key',
        'b2brouter_account_id',
        'b2brouter_account_name',
        'b2brouter_environment',
        'b2brouter_invoice_mode',
        'b2brouter_transaction_count',
        'b2brouter_show_welcome',
        'b2brouter_activated',
        'b2brouter_auto_save_pdf',
        'b2brouter_pdf_storage_path',
        'b2brouter_attach_to_order_completed',
        'b2brouter_attach_to_customer_invoice',
        'b2brouter_attach_to_refunded_order',
        'b2brouter_auto_cleanup_enabled',
        'b2brouter_auto_cleanup_days',
        'b2brouter_invoice_series_code',
        'b2brouter_credit_note_series_code',
        'b2brouter_invoice_numbering_pattern',
        'b2brouter_custom_numbering_pattern',
        'b2brouter_webhook_secret',
        'b2brouter_webhook_enabled',
        'b2brouter_webhook_fallback_polling',
        'b2brouter_status_sync_last_run',
        'b2brouter_delete_archival_data',
    );

    /**
     * Transient keys written by the plugin.
     */
    const TRANSIENT_KEYS = array(
        'b2brouter_validated_accounts',
    );

    /**
     * Orders processed per batch when deleting meta.
     */
    const BATCH_SIZE = 200;

    /**
     * Entry point. Invoked by uninstall.php.
     *
     * @return void
     */
    public static function run() {
        $uninstaller = new self();
        $uninstaller->uninstall($uninstaller->should_delete_archival_data());
    }

    /**
     * Whether the user opted into archival data deletion.
     *
     * Must be called before options are deleted.
     *
     * @return bool
     */
    public function should_delete_archival_data() {
        return get_option('b2brouter_delete_archival_data', '0') === '1';
    }

    /**
     * Perform the uninstall.
     *
     * @param bool $delete_archival Whether archival order meta should also be removed.
     * @return void
     */
    public function uninstall($delete_archival) {
        $this->unschedule_cron();
        $this->delete_order_meta($delete_archival);
        $this->delete_pdf_directory();
        $this->delete_options_and_transients();
    }

    /**
     * Clear all scheduled cron events registered by the plugin.
     *
     * @return void
     */
    public function unschedule_cron() {
        foreach (self::CRON_HOOKS as $hook) {
            if (function_exists('wp_clear_scheduled_hook')) {
                wp_clear_scheduled_hook($hook);
            }
        }
    }

    /**
     * Delete B2Brouter order meta across all orders and refunds.
     *
     * Uses wc_get_orders() — HPOS-safe and lifecycle-correct — instead of a
     * direct SQL DELETE. Paginated to keep memory bounded on large stores.
     *
     * @param bool $delete_archival Whether to also remove archival keys.
     * @return void
     */
    public function delete_order_meta($delete_archival) {
        if (!function_exists('wc_get_orders')) {
            return;
        }

        $keys_to_delete = self::EPHEMERAL_META_KEYS;
        if ($delete_archival) {
            $keys_to_delete = array_merge($keys_to_delete, self::ARCHIVAL_META_KEYS);
        }

        foreach (array('shop_order', 'shop_order_refund') as $type) {
            $this->delete_meta_for_type($type, $keys_to_delete);
        }
    }

    /**
     * Paginate through one order type and delete the given meta keys.
     *
     * @param string $type Order type (shop_order or shop_order_refund).
     * @param array $keys Meta keys to delete.
     * @return void
     */
    protected function delete_meta_for_type($type, array $keys) {
        $page = 1;
        while (true) {
            $order_ids = wc_get_orders(array(
                'type'     => $type,
                'limit'    => self::BATCH_SIZE,
                'paged'    => $page,
                'return'   => 'ids',
                'orderby'  => 'ID',
                'order'    => 'ASC',
                'status'   => 'any',
                'meta_query' => array(
                    array(
                        'key'     => '_b2brouter_invoice_id',
                        'compare' => 'EXISTS',
                    ),
                ),
            ));

            if (empty($order_ids)) {
                break;
            }

            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order) {
                    continue;
                }

                $changed = false;
                foreach ($keys as $key) {
                    if ($order->meta_exists($key)) {
                        $order->delete_meta_data($key);
                        $changed = true;
                    }
                }
                if ($changed) {
                    $order->save();
                }
            }

            // If we are deleting _b2brouter_invoice_id itself, the next
            // query's EXISTS predicate will skip the orders we just cleared —
            // so we must stay on page 1 to avoid skipping unprocessed ones.
            // If we are only deleting ephemeral keys, _b2brouter_invoice_id
            // remains and we need to advance the page to make progress.
            if (!in_array('_b2brouter_invoice_id', $keys, true)) {
                $page++;
            }

            if (count($order_ids) < self::BATCH_SIZE) {
                break;
            }
        }
    }

    /**
     * Remove the PDF storage directory and all files it contains.
     *
     * @return void
     */
    public function delete_pdf_directory() {
        $path = $this->get_pdf_storage_path();
        if (empty($path) || !is_dir($path)) {
            return;
        }

        $this->remove_directory($path);
    }

    /**
     * Resolve the PDF storage directory path.
     *
     * Mirrors Settings::get_pdf_storage_path() so uninstall works even if the
     * main plugin classes aren't loaded.
     *
     * @return string
     */
    protected function get_pdf_storage_path() {
        $custom_path = get_option('b2brouter_pdf_storage_path', '');
        if (!empty($custom_path) && is_dir($custom_path)) {
            return $custom_path;
        }

        if (!function_exists('wp_upload_dir')) {
            return '';
        }

        $upload_dir = wp_upload_dir();
        if (empty($upload_dir['basedir'])) {
            return '';
        }

        return $upload_dir['basedir'] . '/b2brouter-invoices';
    }

    /**
     * Recursively remove a directory and all its contents.
     *
     * @param string $path Absolute directory path.
     * @return void
     */
    protected function remove_directory($path) {
        // @ suppresses PHP warnings at the syscall boundary; failures are
        // surfaced through Logger::warning so leaked PDFs are visible in
        // WooCommerce logs instead of silently dropped.
        $entries = @scandir($path);
        if ($entries === false) {
            Logger::warning('B2Brouter Uninstall: scandir() failed for ' . $path);
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . '/' . $entry;
            if (is_dir($full) && !is_link($full)) {
                $this->remove_directory($full);
            } else {
                if (!@unlink($full)) {
                    Logger::warning('B2Brouter Uninstall: failed to delete ' . $full);
                }
            }
        }

        if (!@rmdir($path)) {
            Logger::warning('B2Brouter Uninstall: failed to remove directory ' . $path);
        }
    }

    /**
     * Delete all plugin options and transients.
     *
     * @return void
     */
    public function delete_options_and_transients() {
        foreach (self::OPTION_KEYS as $option) {
            delete_option($option);
        }

        foreach (self::TRANSIENT_KEYS as $transient) {
            if (function_exists('delete_transient')) {
                delete_transient($transient);
            }
        }
    }
}
