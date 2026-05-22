<?php
/**
 * Tests for REST_Meta_Guard.
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\REST_Meta_Guard;

/**
 * Defense-in-depth filter that strips any `_b2brouter_*` keys from the
 * `meta_data` array on inbound WooCommerce REST writes. This prevents a
 * Shop Manager (who has `edit_shop_orders` and can therefore PUT orders
 * via the REST API) from poisoning plugin-internal meta — most critically
 * the cached PDF path consumed by Invoice_Generator.
 */
class RESTMetaGuardTest extends TestCase {

    /**
     * Build a stub object that exposes get_param() the way WP_REST_Request
     * does, returning the provided meta_data for the 'meta_data' param.
     */
    private function fakeRequest(array $meta_data) {
        return new class($meta_data) {
            private $params;
            public function __construct($meta_data) {
                $this->params = array('meta_data' => $meta_data);
            }
            public function get_param($key) {
                return isset($this->params[$key]) ? $this->params[$key] : null;
            }
        };
    }

    public function test_strips_invoice_pdf_path_from_meta_data() {
        $order = new WC_Order(7001);
        // Simulate WC having already applied the inbound meta_data to the
        // order object before our filter runs.
        $order->update_meta_data('_b2brouter_invoice_pdf_path', '/etc/passwd');

        $request = $this->fakeRequest(array(
            array('key' => '_b2brouter_invoice_pdf_path', 'value' => '/etc/passwd'),
        ));

        $guard = new REST_Meta_Guard();
        $result = $guard->strip_internal_meta($order, $request);

        $this->assertSame('', $result->get_meta('_b2brouter_invoice_pdf_path'));
    }

    public function test_strips_every_internal_namespace_key() {
        $order = new WC_Order(7002);
        $order->update_meta_data('_b2brouter_invoice_id', 'attacker-set');
        $order->update_meta_data('_b2brouter_invoice_pdf_path', '/etc/passwd');
        $order->update_meta_data('_b2brouter_invoice_status', 'sent');

        $request = $this->fakeRequest(array(
            array('key' => '_b2brouter_invoice_id', 'value' => 'attacker-set'),
            array('key' => '_b2brouter_invoice_pdf_path', 'value' => '/etc/passwd'),
            array('key' => '_b2brouter_invoice_status', 'value' => 'sent'),
        ));

        $guard = new REST_Meta_Guard();
        $guard->strip_internal_meta($order, $request);

        $this->assertSame('', $order->get_meta('_b2brouter_invoice_id'));
        $this->assertSame('', $order->get_meta('_b2brouter_invoice_pdf_path'));
        $this->assertSame('', $order->get_meta('_b2brouter_invoice_status'));
    }

    public function test_does_not_touch_non_internal_meta() {
        $order = new WC_Order(7003);
        $order->update_meta_data('_some_other_plugin_key', 'keep me');
        $order->update_meta_data('public_meta', 'keep me too');

        $request = $this->fakeRequest(array(
            array('key' => '_some_other_plugin_key', 'value' => 'keep me'),
            array('key' => 'public_meta', 'value' => 'keep me too'),
        ));

        $guard = new REST_Meta_Guard();
        $guard->strip_internal_meta($order, $request);

        $this->assertSame('keep me', $order->get_meta('_some_other_plugin_key'));
        $this->assertSame('keep me too', $order->get_meta('public_meta'));
    }

    public function test_leaves_internal_meta_alone_when_request_did_not_touch_it() {
        // The filter only strips keys mentioned in the inbound payload. A
        // REST write that touches unrelated fields must not wipe legitimate
        // plugin meta off the order.
        $order = new WC_Order(7004);
        $order->update_meta_data('_b2brouter_invoice_id', 'inv-legit-123');

        $request = $this->fakeRequest(array(
            array('key' => 'public_meta', 'value' => 'whatever'),
        ));

        $guard = new REST_Meta_Guard();
        $guard->strip_internal_meta($order, $request);

        $this->assertSame('inv-legit-123', $order->get_meta('_b2brouter_invoice_id'));
    }

    public function test_tolerates_missing_meta_data_param() {
        $order = new WC_Order(7005);

        $request = new class {
            public function get_param($key) {
                return null;
            }
        };

        $guard = new REST_Meta_Guard();
        $result = $guard->strip_internal_meta($order, $request);

        $this->assertSame($order, $result);
    }

    public function test_tolerates_malformed_meta_entries() {
        $order = new WC_Order(7006);
        $order->update_meta_data('_b2brouter_invoice_pdf_path', '/etc/passwd');

        $request = $this->fakeRequest(array(
            'not-an-array',
            array(),
            array('value' => 'no-key-field'),
            array('key' => 12345, 'value' => 'numeric-key'),
            array('key' => '_b2brouter_invoice_pdf_path', 'value' => '/etc/passwd'),
        ));

        $guard = new REST_Meta_Guard();
        // Should not throw; valid entries should still be stripped.
        $guard->strip_internal_meta($order, $request);

        $this->assertSame('', $order->get_meta('_b2brouter_invoice_pdf_path'));
    }
}
