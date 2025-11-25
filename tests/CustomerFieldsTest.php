<?php
/**
 * Tests for Customer_Fields class
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\Customer_Fields;

/**
 * Customer_Fields test case
 *
 * Tests TIN/VAT field functionality for both classic and block checkout
 *
 * @since 1.0.0
 */
class CustomerFieldsTest extends TestCase {

    /**
     * Customer_Fields instance
     *
     * @var Customer_Fields
     */
    private $customer_fields;

    /**
     * Set up test
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();

        // Reset global state
        global $wp_actions, $wp_filters;
        $wp_actions = array();
        $wp_filters = array();

        // Create instance
        $this->customer_fields = new Customer_Fields();
    }

    /**
     * Test TIN field constants
     *
     * @return void
     */
    public function test_tin_field_constants() {
        $this->assertEquals('billing_tin', Customer_Fields::get_tin_field_key());
        $this->assertEquals('_billing_tin', Customer_Fields::get_tin_meta_key());
    }

    /**
     * Test TIN field is added to checkout fields
     *
     * @return void
     */
    public function test_add_tin_to_checkout_fields() {
        $fields = array(
            'billing' => array(
                'billing_first_name' => array('label' => 'First Name'),
                'billing_last_name' => array('label' => 'Last Name'),
            ),
        );

        $result = $this->customer_fields->add_tin_to_checkout_fields($fields);

        // Check TIN field was added to billing fields
        $this->assertArrayHasKey('billing_tin', $result['billing']);

        // Check TIN field properties
        $tin_field = $result['billing']['billing_tin'];
        $this->assertEquals('Tax ID / VAT Number', $tin_field['label']);
        $this->assertEquals('Tax Identification Number', $tin_field['placeholder']);
        $this->assertFalse($tin_field['required']);
        $this->assertContains('form-row-wide', $tin_field['class']);
        $this->assertEquals(35, $tin_field['priority']);
    }

    /**
     * Test TIN field is added to billing fields
     *
     * @return void
     */
    public function test_add_tin_to_billing_fields() {
        $fields = array(
            'billing_company' => array('label' => 'Company'),
            'billing_address_1' => array('label' => 'Address'),
        );

        $result = $this->customer_fields->add_tin_to_billing_fields($fields);

        // Check TIN field was added
        $this->assertArrayHasKey('billing_tin', $result);

        // Check TIN field is after company field
        $keys = array_keys($result);
        $company_index = array_search('billing_company', $keys);
        $tin_index = array_search('billing_tin', $keys);
        $this->assertEquals($company_index + 1, $tin_index);
    }

    /**
     * Test TIN field is added to billing fields when no company field exists
     *
     * @return void
     */
    public function test_add_tin_to_billing_fields_without_company() {
        $fields = array(
            'billing_first_name' => array('label' => 'First Name'),
            'billing_address_1' => array('label' => 'Address'),
        );

        $result = $this->customer_fields->add_tin_to_billing_fields($fields);

        // Check TIN field was added
        $this->assertArrayHasKey('billing_tin', $result);
        $this->assertEquals('Tax ID / VAT Number', $result['billing_tin']['label']);
    }

    /**
     * Test TIN field is added to admin billing fields
     *
     * @return void
     */
    public function test_add_tin_to_admin_billing_fields() {
        $fields = array(
            'first_name' => array('label' => 'First Name'),
            'last_name' => array('label' => 'Last Name'),
        );

        $result = $this->customer_fields->add_tin_to_admin_billing_fields($fields);

        // Check TIN field was added
        $this->assertArrayHasKey('tin', $result);
        $this->assertEquals('Tax ID / VAT Number', $result['tin']['label']);
        $this->assertTrue($result['tin']['show']);
    }

    /**
     * Test TIN is saved from block checkout
     *
     * @return void
     */
    public function test_save_tin_from_blocks() {
        // Mock WC_Order
        $mock_order = $this->createMock(\WC_Order::class);

        // Expect update_meta_data to be called with _billing_tin
        $mock_order->expects($this->once())
            ->method('update_meta_data')
            ->with(
                $this->equalTo('_billing_tin'),
                $this->equalTo('ES12345678Z')
            );

        // Expect delete_meta_data to be called to remove WooCommerce Blocks meta
        $mock_order->expects($this->once())
            ->method('delete_meta_data')
            ->with($this->equalTo('_wc_other/b2brouter/tin'));

        // Call the method
        $this->customer_fields->save_tin_from_blocks(
            'b2brouter/tin',
            'ES12345678Z',
            'other',
            $mock_order
        );
    }

    /**
     * Test TIN is not saved from block checkout when value is empty
     *
     * @return void
     */
    public function test_save_tin_from_blocks_empty_value() {
        // Mock WC_Order
        $mock_order = $this->createMock(\WC_Order::class);

        // Expect update_meta_data NOT to be called for empty value
        $mock_order->expects($this->never())
            ->method('update_meta_data');

        // Expect delete_meta_data to still be called
        $mock_order->expects($this->once())
            ->method('delete_meta_data')
            ->with($this->equalTo('_wc_other/b2brouter/tin'));

        // Call the method with empty value
        $this->customer_fields->save_tin_from_blocks(
            'b2brouter/tin',
            '',
            'other',
            $mock_order
        );
    }

    /**
     * Test TIN is not saved from block checkout for wrong field key
     *
     * @return void
     */
    public function test_save_tin_from_blocks_wrong_key() {
        // Mock WC_Order
        $mock_order = $this->createMock(\WC_Order::class);

        // Expect no methods to be called for wrong key
        $mock_order->expects($this->never())
            ->method('update_meta_data');
        $mock_order->expects($this->never())
            ->method('delete_meta_data');

        // Call the method with wrong key
        $this->customer_fields->save_tin_from_blocks(
            'some_other_field',
            'ES12345678Z',
            'other',
            $mock_order
        );
    }

    /**
     * Test get TIN from order
     *
     * @return void
     */
    public function test_get_order_tin() {
        // Mock WC_Order
        $mock_order = $this->createMock(\WC_Order::class);

        $mock_order->expects($this->once())
            ->method('get_meta')
            ->with($this->equalTo('_billing_tin'))
            ->willReturn('ES12345678Z');

        $tin = Customer_Fields::get_order_tin($mock_order);

        $this->assertEquals('ES12345678Z', $tin);
    }

    /**
     * Test get TIN from order returns empty string for null order
     *
     * @return void
     */
    public function test_get_order_tin_null_order() {
        $tin = Customer_Fields::get_order_tin(null);

        $this->assertEquals('', $tin);
    }

    /**
     * Test get TIN from customer
     *
     * @return void
     */
    public function test_get_customer_tin() {
        global $wp_user_meta;

        // Mock WC_Customer
        $mock_customer = $this->createMock(\WC_Customer::class);

        $mock_customer->expects($this->once())
            ->method('get_id')
            ->willReturn(123);

        // Set up mock user meta
        $wp_user_meta[123]['billing_tin'] = 'ES12345678Z';

        $tin = $this->customer_fields->get_customer_tin('', $mock_customer);

        $this->assertEquals('ES12345678Z', $tin);
    }

    /**
     * Test get TIN from customer returns empty string for null customer
     *
     * @return void
     */
    public function test_get_customer_tin_null_customer() {
        $tin = $this->customer_fields->get_customer_tin('', null);

        $this->assertEquals('', $tin);
    }

    /**
     * Test hooks are registered
     *
     * @return void
     */
    public function test_hooks_registered() {
        global $wp_filters, $wp_actions;

        // Check that filters are registered
        $this->assertArrayHasKey('woocommerce_checkout_fields', $wp_filters);
        $this->assertArrayHasKey('woocommerce_billing_fields', $wp_filters);
        $this->assertArrayHasKey('woocommerce_admin_billing_fields', $wp_filters);

        // Check that actions are registered
        $this->assertArrayHasKey('woocommerce_checkout_update_order_meta', $wp_actions);
        $this->assertArrayHasKey('woocommerce_set_additional_field_value', $wp_actions);
    }
}
