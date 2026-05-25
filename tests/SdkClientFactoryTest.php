<?php
/**
 * Tests for Sdk_Client_Factory
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\Sdk_Client_Factory;

/**
 * Sdk_Client_Factory test case
 *
 * @since 1.0.5
 */
class SdkClientFactoryTest extends TestCase {

    /**
     * @return void
     */
    public function test_default_app_info_uses_plugin_name_and_version() {
        $app_info = Sdk_Client_Factory::default_app_info();

        $this->assertSame('B2BRouter-WooCommerce', $app_info['name']);
        $this->assertSame(B2BROUTER_WC_VERSION, $app_info['version']);
    }

    /**
     * @return void
     */
    public function test_default_app_info_omits_url_when_home_url_unavailable() {
        // Bootstrap does not define home_url(); the factory must not synthesise one.
        $app_info = Sdk_Client_Factory::default_app_info();

        $this->assertArrayNotHasKey('url', $app_info);
    }

    /**
     * @return void
     */
    public function test_build_injects_app_info_into_user_agent() {
        $client = Sdk_Client_Factory::build('test-key');

        $user_agent = $client->getUserAgent();

        $this->assertStringContainsString('B2BRouter-PHP/', $user_agent);
        $this->assertStringContainsString(
            'B2BRouter-WooCommerce/' . B2BROUTER_WC_VERSION,
            $user_agent
        );
    }

    /**
     * @return void
     */
    public function test_caller_app_info_overrides_defaults() {
        $client = Sdk_Client_Factory::build('test-key', array(
            'app_info' => array(
                'name'    => 'Custom-Caller',
                'version' => '9.9.9',
            ),
        ));

        $user_agent = $client->getUserAgent();

        $this->assertStringContainsString('Custom-Caller/9.9.9', $user_agent);
        $this->assertStringNotContainsString('B2BRouter-WooCommerce', $user_agent);
    }

    /**
     * @return void
     */
    public function test_caller_options_pass_through() {
        $client = Sdk_Client_Factory::build('test-key', array(
            'api_base' => 'https://example.test',
        ));

        $this->assertSame('https://example.test', $client->getApiBase());
    }
}
