<?php
/**
 * B2Brouter SDK Client Factory
 *
 * @package B2Brouter\WooCommerce
 * @since 1.0.5
 */

namespace B2Brouter\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds B2BRouterClient instances with shared plugin defaults.
 *
 * Centralises the SDK options that every call site in the plugin needs to
 * share — notably the `app_info` descriptor that identifies the plugin in
 * outbound requests via the User-Agent header. Callers pass their own
 * per-context options (api_base, http_client, timeout, ...); those win over
 * defaults so individual call sites can still override anything.
 *
 * @since 1.0.5
 */
class Sdk_Client_Factory {

    /**
     * Build a B2BRouterClient with plugin defaults merged in.
     *
     * @param string $api_key The B2Brouter API key.
     * @param array  $options Per-caller SDK options; override any default.
     * @return \B2BRouter\B2BRouterClient
     */
    public static function build($api_key, array $options = array()) {
        $options = array_merge(self::default_options(), $options);
        return new \B2BRouter\B2BRouterClient($api_key, $options);
    }

    /**
     * Default SDK options injected on every build.
     *
     * @return array
     */
    public static function default_options() {
        return array(
            'app_info' => self::default_app_info(),
        );
    }

    /**
     * Plugin descriptor appended to the SDK User-Agent.
     *
     * Reads B2BROUTER_WC_VERSION and home_url() defensively so the factory
     * also works in environments where the plugin bootstrap or WordPress
     * itself is not loaded (most notably the unit-test harness).
     *
     * @return array
     */
    public static function default_app_info() {
        $app_info = array('name' => 'B2BRouter-WooCommerce');

        if (defined('B2BROUTER_WC_VERSION')) {
            $app_info['version'] = B2BROUTER_WC_VERSION;
        }

        if (function_exists('home_url')) {
            $url = \home_url();
            if (is_string($url) && $url !== '') {
                $app_info['url'] = $url;
            }
        }

        return $app_info;
    }
}
