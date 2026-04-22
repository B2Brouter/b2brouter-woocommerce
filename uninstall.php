<?php
/**
 * Uninstall handler for B2Brouter for WooCommerce.
 *
 * Runs when the user deletes the plugin via the WordPress admin. Delegates all
 * work to \B2Brouter\WooCommerce\Uninstaller so the logic stays testable.
 *
 * @package B2Brouter\WooCommerce
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

if (class_exists('\\B2Brouter\\WooCommerce\\Uninstaller')) {
    \B2Brouter\WooCommerce\Uninstaller::run();
}
