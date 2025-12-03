<?php
/**
 * Tests for API_Retry class
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\API_Retry;

/**
 * API_Retry test case
 *
 * Tests exponential backoff retry logic
 *
 * @since 1.0.0
 */
class APIRetryTest extends TestCase {

    /**
     * Test successful execution on first attempt
     *
     * @return void
     */
    public function test_execute_success_first_attempt() {
        $callback = function() {
            return 'success';
        };

        $result = API_Retry::execute($callback);

        $this->assertEquals('success', $result);
    }

    /**
     * Test successful execution after retries
     *
     * @return void
     */
    public function test_execute_success_after_retries() {
        $attempt_count = 0;

        $callback = function() use (&$attempt_count) {
            $attempt_count++;
            if ($attempt_count < 3) {
                throw new \B2BRouter\Exception\ResourceNotFoundException('Not ready yet');
            }
            return 'success';
        };

        $result = API_Retry::execute($callback, array(
            'max_attempts' => 5,
            'initial_delay' => 0, // Use 0 delay for testing
        ));

        $this->assertEquals('success', $result);
        $this->assertEquals(3, $attempt_count);
    }

    /**
     * Test non-retryable exception is thrown immediately
     *
     * @return void
     */
    public function test_execute_non_retryable_exception() {
        $attempt_count = 0;

        $callback = function() use (&$attempt_count) {
            $attempt_count++;
            throw new \B2BRouter\Exception\AuthenticationException('Invalid API key');
        };

        $this->expectException(\B2BRouter\Exception\AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key');

        API_Retry::execute($callback, array(
            'max_attempts' => 5,
            'initial_delay' => 0,
        ));

        // Should only attempt once
        $this->assertEquals(1, $attempt_count);
    }

    /**
     * Test max attempts limit is respected
     *
     * @return void
     */
    public function test_execute_max_attempts_limit() {
        $attempt_count = 0;

        $callback = function() use (&$attempt_count) {
            $attempt_count++;
            throw new \B2BRouter\Exception\ResourceNotFoundException('Still not ready');
        };

        $this->expectException(\B2BRouter\Exception\ResourceNotFoundException::class);

        API_Retry::execute($callback, array(
            'max_attempts' => 3,
            'initial_delay' => 0,
        ));

        // Should attempt exactly 3 times
        $this->assertEquals(3, $attempt_count);
    }

    /**
     * Test custom retryable exceptions
     *
     * @return void
     */
    public function test_execute_custom_retryable_exceptions() {
        $attempt_count = 0;

        $callback = function() use (&$attempt_count) {
            $attempt_count++;
            if ($attempt_count < 2) {
                throw new \B2BRouter\Exception\ApiConnectionException('Network error');
            }
            return 'success';
        };

        $result = API_Retry::execute($callback, array(
            'max_attempts' => 5,
            'initial_delay' => 0,
            'retryable_exceptions' => array(
                'B2BRouter\Exception\ApiConnectionException',
            ),
        ));

        $this->assertEquals('success', $result);
        $this->assertEquals(2, $attempt_count);
    }

    /**
     * Test exception not in retryable list is thrown immediately
     *
     * @return void
     */
    public function test_execute_exception_not_in_retryable_list() {
        $attempt_count = 0;

        $callback = function() use (&$attempt_count) {
            $attempt_count++;
            throw new \B2BRouter\Exception\PermissionException('Forbidden');
        };

        $this->expectException(\B2BRouter\Exception\PermissionException::class);

        API_Retry::execute($callback, array(
            'max_attempts' => 5,
            'initial_delay' => 0,
            'retryable_exceptions' => array(
                'B2BRouter\Exception\ResourceNotFoundException',
            ),
        ));

        // Should only attempt once
        $this->assertEquals(1, $attempt_count);
    }

    /**
     * Test default options are applied
     *
     * @return void
     */
    public function test_execute_default_options() {
        $callback = function() {
            return 'success';
        };

        // Should use defaults: max_attempts=5, initial_delay=1
        $result = API_Retry::execute($callback);

        $this->assertEquals('success', $result);
    }

    /**
     * Test callback receives return value correctly
     *
     * @return void
     */
    public function test_execute_returns_callback_value() {
        $callback = function() {
            return array('key' => 'value', 'number' => 42);
        };

        $result = API_Retry::execute($callback);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('key', $result);
        $this->assertEquals('value', $result['key']);
        $this->assertEquals(42, $result['number']);
    }

    /**
     * Test ResourceNotFoundException is retryable by default
     *
     * @return void
     */
    public function test_resource_not_found_is_retryable_by_default() {
        $attempt_count = 0;

        $callback = function() use (&$attempt_count) {
            $attempt_count++;
            if ($attempt_count < 2) {
                throw new \B2BRouter\Exception\ResourceNotFoundException('PDF not ready');
            }
            return 'success';
        };

        $result = API_Retry::execute($callback, array('initial_delay' => 0));

        $this->assertEquals('success', $result);
        $this->assertEquals(2, $attempt_count);
    }

    /**
     * Test ApiConnectionException is retryable by default
     *
     * @return void
     */
    public function test_api_connection_exception_is_retryable_by_default() {
        $attempt_count = 0;

        $callback = function() use (&$attempt_count) {
            $attempt_count++;
            if ($attempt_count < 2) {
                throw new \B2BRouter\Exception\ApiConnectionException('Network timeout');
            }
            return 'success';
        };

        $result = API_Retry::execute($callback, array('initial_delay' => 0));

        $this->assertEquals('success', $result);
        $this->assertEquals(2, $attempt_count);
    }

    /**
     * Test multiple retries with zero delay
     *
     * @return void
     */
    public function test_multiple_retries_succeed() {
        $attempt_count = 0;

        $callback = function() use (&$attempt_count) {
            $attempt_count++;
            if ($attempt_count < 4) {
                throw new \B2BRouter\Exception\ResourceNotFoundException('Not ready');
            }
            return 'success';
        };

        $result = API_Retry::execute($callback, array(
            'max_attempts' => 5,
            'initial_delay' => 0,
        ));

        $this->assertEquals('success', $result);
        $this->assertEquals(4, $attempt_count);
    }

    /**
     * Test retries work with custom initial delay
     *
     * @return void
     */
    public function test_custom_initial_delay_option() {
        $attempt_count = 0;

        $callback = function() use (&$attempt_count) {
            $attempt_count++;
            if ($attempt_count < 3) {
                throw new \B2BRouter\Exception\ResourceNotFoundException('Not ready');
            }
            return 'success';
        };

        // Test that custom initial_delay option is accepted (use 0 for fast test)
        $result = API_Retry::execute($callback, array(
            'max_attempts' => 5,
            'initial_delay' => 0,
            'max_delay' => 10,
        ));

        $this->assertEquals('success', $result);
        $this->assertEquals(3, $attempt_count);
    }

    /**
     * Test constants are defined correctly
     *
     * @return void
     */
    public function test_constants_defined() {
        $this->assertEquals(5, API_Retry::DEFAULT_MAX_ATTEMPTS);
        $this->assertEquals(1, API_Retry::DEFAULT_INITIAL_DELAY);
        $this->assertEquals(10, API_Retry::DEFAULT_MAX_DELAY);
    }
}
