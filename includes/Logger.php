<?php
/**
 * Logger
 *
 * Thin wrapper around wc_get_logger() that pins the source to
 * 'b2brouter-woocommerce' so plugin entries are filterable in
 * WooCommerce > Status > Logs.
 *
 * @package B2Brouter\WooCommerce
 * @since 0.9.4
 */

namespace B2Brouter\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger class
 *
 * @since 0.9.4
 */
class Logger {

    const SOURCE = 'b2brouter-woocommerce';

    /**
     * Log an error-level message.
     *
     * @since 0.9.4
     * @param string $message Message
     * @param array  $context Optional context (merged with source)
     * @return void
     */
    public static function error($message, array $context = array()) {
        self::log('error', $message, $context);
    }

    /**
     * Log a warning-level message.
     *
     * @since 0.9.4
     * @param string $message Message
     * @param array  $context Optional context (merged with source)
     * @return void
     */
    public static function warning($message, array $context = array()) {
        self::log('warning', $message, $context);
    }

    /**
     * Log an info-level message.
     *
     * @since 0.9.4
     * @param string $message Message
     * @param array  $context Optional context (merged with source)
     * @return void
     */
    public static function info($message, array $context = array()) {
        self::log('info', $message, $context);
    }

    /**
     * Dispatch to wc_get_logger(). No-ops if WooCommerce hasn't loaded.
     *
     * @since 0.9.4
     * @param string $level   PSR-3 level ('error', 'warning', 'info', ...)
     * @param string $message Message
     * @param array  $context Context
     * @return void
     */
    private static function log($level, $message, array $context) {
        if (!function_exists('wc_get_logger')) {
            return;
        }

        $logger = wc_get_logger();

        if (!$logger) {
            return;
        }

        $context['source'] = self::SOURCE;
        $logger->log($level, $message, $context);
    }
}
