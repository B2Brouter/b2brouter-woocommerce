<?php
/**
 * Strip plugin-internal meta keys from inbound WooCommerce REST writes.
 *
 * @package B2Brouter\WooCommerce
 * @since 1.0.4
 */

namespace B2Brouter\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST_Meta_Guard
 *
 * The WooCommerce REST orders endpoint lets any user with the
 * `edit_shop_orders` capability (Shop Manager and above) write arbitrary
 * `meta_data` entries — including underscore-prefixed keys — on PUT
 * /wc/v3/orders/{id}. This guard hooks WC's pre-insert filters and
 * strips any incoming key in the `_b2brouter_*` namespace before the
 * order is saved, so plugin-internal state (cached PDF path, invoice
 * id, status, …) can never be poisoned through the REST surface.
 *
 * This is defense-in-depth alongside the path-containment check in
 * Invoice_Generator::resolve_safe_pdf_path(); either alone would close
 * the reported LFI, but the pair makes future regressions safer.
 *
 * @since 1.0.4
 */
class REST_Meta_Guard {

    /**
     * Namespace prefix used by every plugin-managed order meta key.
     *
     * @var string
     */
    const META_PREFIX = '_b2brouter_';

    /**
     * Register the filters with WooCommerce.
     *
     * @return void
     */
    public function register() {
        add_filter('woocommerce_rest_pre_insert_shop_order_object', array($this, 'strip_internal_meta'), 10, 2);
        add_filter('woocommerce_rest_pre_insert_shop_order_refund_object', array($this, 'strip_internal_meta'), 10, 2);
    }

    /**
     * Remove any internal-namespace meta keys that the REST request tried
     * to set, before WC persists the order.
     *
     * Only keys mentioned in the incoming `meta_data` payload are stripped;
     * legitimate plugin meta already on the order is left untouched when
     * the request does not reference it.
     *
     * @param mixed $order   The WC_Order / WC_Order_Refund being prepared.
     * @param mixed $request The WP_REST_Request driving the operation.
     * @return mixed The (possibly cleaned) order object, returned for filter chaining.
     */
    public function strip_internal_meta($order, $request) {
        if (!is_object($order) || !is_object($request) || !method_exists($order, 'delete_meta_data')) {
            return $order;
        }

        $incoming = $request->get_param('meta_data');
        if (!is_array($incoming)) {
            return $order;
        }

        $stripped = array();
        foreach ($incoming as $entry) {
            if (!is_array($entry) || !isset($entry['key']) || !is_string($entry['key'])) {
                continue;
            }
            if (strncmp($entry['key'], self::META_PREFIX, strlen(self::META_PREFIX)) !== 0) {
                continue;
            }
            $order->delete_meta_data($entry['key']);
            $stripped[] = $entry['key'];
        }

        if (!empty($stripped) && class_exists(__NAMESPACE__ . '\\Logger')) {
            Logger::warning(
                'B2Brouter REST guard: dropped internal meta keys from inbound REST write: '
                . implode(', ', $stripped)
            );
        }

        return $order;
    }
}
