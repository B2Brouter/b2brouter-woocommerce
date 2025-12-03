<?php
/**
 * API Retry Helper
 *
 * Provides exponential backoff retry logic for B2Brouter API calls
 *
 * @package B2Brouter\WooCommerce
 * @since 1.0.0
 */

namespace B2Brouter\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * API_Retry class
 *
 * Handles retry logic with exponential backoff for API operations
 *
 * @since 1.0.0
 */
class API_Retry {

    /**
     * Default maximum retry attempts
     *
     * @since 1.0.0
     * @var int
     */
    const DEFAULT_MAX_ATTEMPTS = 5;

    /**
     * Default initial delay in seconds
     *
     * @since 1.0.0
     * @var int
     */
    const DEFAULT_INITIAL_DELAY = 1;

    /**
     * Default maximum delay in seconds
     *
     * @since 1.0.0
     * @var int
     */
    const DEFAULT_MAX_DELAY = 10;

    /**
     * Execute a callback with exponential backoff retry logic
     *
     * @since 1.0.0
     * @param callable $callback The function to execute
     * @param array $options Optional configuration
     *   - max_attempts (int): Maximum number of attempts (default: 5)
     *   - initial_delay (int): Initial delay in seconds (default: 1)
     *   - max_delay (int): Maximum delay in seconds (default: 10)
     *   - retryable_exceptions (array): Exception class names to retry (default: ResourceNotFoundException)
     * @return mixed Result from callback
     * @throws \Exception The last exception if all retries fail
     */
    public static function execute($callback, $options = array()) {
        // Merge options with defaults
        $options = array_merge(
            array(
                'max_attempts' => self::DEFAULT_MAX_ATTEMPTS,
                'initial_delay' => self::DEFAULT_INITIAL_DELAY,
                'max_delay' => self::DEFAULT_MAX_DELAY,
                'retryable_exceptions' => array(
                    'B2BRouter\Exception\ResourceNotFoundException',
                    'B2BRouter\Exception\ApiConnectionException',
                ),
            ),
            $options
        );

        $attempt = 0;
        $last_exception = null;

        while ($attempt < $options['max_attempts']) {
            try {
                // Execute the callback
                return call_user_func($callback);

            } catch (\Exception $e) {
                $last_exception = $e;
                $attempt++;

                // Check if this exception is retryable
                if (!self::is_retryable($e, $options['retryable_exceptions'])) {
                    throw $e;
                }

                // If we've exhausted all attempts, throw the exception
                if ($attempt >= $options['max_attempts']) {
                    throw $e;
                }

                // Calculate delay and sleep
                $delay = self::calculate_delay($attempt, $options['initial_delay'], $options['max_delay']);
                sleep($delay);
            }
        }

        // This should never be reached, but throw the last exception if it is
        if ($last_exception) {
            throw $last_exception;
        }

        throw new \Exception('Retry logic failed unexpectedly');
    }

    /**
     * Check if an exception is retryable
     *
     * @since 1.0.0
     * @param \Exception $exception The exception to check
     * @param array $retryable_exceptions Array of retryable exception class names
     * @return bool True if exception should be retried
     */
    private static function is_retryable($exception, $retryable_exceptions) {
        $exception_class = get_class($exception);

        // Check if exception class matches any retryable exceptions
        foreach ($retryable_exceptions as $retryable) {
            if ($exception instanceof $retryable || $exception_class === $retryable) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate backoff delay using exponential strategy
     *
     * @since 1.0.0
     * @param int $attempt Current attempt number (1-based)
     * @param int $initial_delay Initial delay in seconds
     * @param int $max_delay Maximum delay in seconds
     * @return int Delay in seconds
     */
    private static function calculate_delay($attempt, $initial_delay, $max_delay) {
        // Exponential backoff: initial_delay * 2^(attempt-1)
        // Attempt 1: 1 * 2^0 = 1 second
        // Attempt 2: 1 * 2^1 = 2 seconds
        // Attempt 3: 1 * 2^2 = 4 seconds
        // Attempt 4: 1 * 2^3 = 8 seconds
        $delay = $initial_delay * pow(2, $attempt - 1);

        // Cap at max_delay
        return min($delay, $max_delay);
    }
}
