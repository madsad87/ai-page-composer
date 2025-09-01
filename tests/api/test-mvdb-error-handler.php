<?php
/**
 * MVDB Error Handler Test
 * 
 * This file contains unit tests for the MVDB Error Handler functionality.
 * It verifies error classification, logging, statistics, and recovery mechanisms.
 *
 * @package AIPageComposer\Tests
 */

namespace AIPageComposer\Tests\API;

use PHPUnit\Framework\TestCase;
use AIPageComposer\API\MVDB_Error_Handler;
use AIPageComposer\Admin\Settings_Manager;

class Test_MVDB_Error_Handler extends TestCase {

    /**
     * Error handler instance
     *
     * @var MVDB_Error_Handler
     */
    private $error_handler;

    /**
     * Mock settings manager
     *
     * @var Settings_Manager
     */
    private $settings_manager;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Mock settings manager
        $this->settings_manager = $this->createMock( Settings_Manager::class );
        
        // Mock MVDB settings
        $mock_settings = [
            'mvdb_settings' => [
                'enable_debug_logging' => true,
                'timeout_seconds' => 30,
                'retry_attempts' => 2,
            ]
        ];
        
        $this->settings_manager->method( 'get_all_settings' )
                              ->willReturn( $mock_settings );
        
        $this->error_handler = new MVDB_Error_Handler( $this->settings_manager );
    }

    /**
     * Test error handler initialization
     */
    public function test_error_handler_initialization() {
        $this->assertInstanceOf( MVDB_Error_Handler::class, $this->error_handler );
    }

    /**
     * Test basic error handling
     */
    public function test_basic_error_handling() {
        $error_id = $this->error_handler->handle_error(
            'Test error message',
            'ERROR',
            'VALIDATION',
            null,
            [ 'test_context' => 'test_value' ]
        );
        
        $this->assertIsString( $error_id );
        $this->assertStringStartsWith( 'mvdb_', $error_id );
    }

    /**
     * Test API error handling
     */
    public function test_api_error_handling() {
        $exception = new \Exception( 'API authentication failed', 401 );
        $request_data = [
            'query' => 'test query',
            'variables' => [ 'test' => 'data' ]
        ];
        $response_data = [
            'error' => 'Unauthorized',
            'status' => 401
        ];
        
        $error_id = $this->error_handler->handle_api_error(
            $exception,
            $request_data,
            $response_data
        );
        
        $this->assertIsString( $error_id );
        $this->assertStringStartsWith( 'mvdb_', $error_id );
    }

    /**
     * Test error classification
     */
    public function test_error_classification() {
        // Test authentication error (401)
        $auth_exception = new \Exception( 'Invalid authentication token', 401 );
        $error_id = $this->error_handler->handle_api_error( $auth_exception );
        
        $error_logs = $this->error_handler->get_error_logs( [ 'limit' => 1 ] );
        $this->assertNotEmpty( $error_logs );
        $this->assertEquals( 'AUTHENTICATION', $error_logs[0]['category'] );
        $this->assertEquals( 'CRITICAL', $error_logs[0]['severity'] );
        
        // Test rate limit error (429)
        $rate_limit_exception = new \Exception( 'Rate limit exceeded', 429 );
        $this->error_handler->handle_api_error( $rate_limit_exception );
        
        $error_logs = $this->error_handler->get_error_logs( [ 'limit' => 1 ] );
        $this->assertEquals( 'RATE_LIMIT', $error_logs[0]['category'] );
        $this->assertEquals( 'ERROR', $error_logs[0]['severity'] );
        
        // Test timeout error
        $timeout_exception = new \Exception( 'Request timed out after 30 seconds', 408 );
        $this->error_handler->handle_api_error( $timeout_exception );
        
        $error_logs = $this->error_handler->get_error_logs( [ 'limit' => 1 ] );
        $this->assertEquals( 'TIMEOUT', $error_logs[0]['category'] );
    }

    /**
     * Test error severity determination
     */
    public function test_error_severity_determination() {
        // Critical error - configuration issue
        $config_exception = new \Exception( 'MVDB configuration is missing', 500 );
        $this->error_handler->handle_error(
            $config_exception->getMessage(),
            'CRITICAL',
            'CONFIGURATION',
            $config_exception
        );
        
        $error_logs = $this->error_handler->get_error_logs( [ 'limit' => 1 ] );
        $this->assertEquals( 'CRITICAL', $error_logs[0]['severity'] );
        
        // Warning - network issue
        $network_exception = new \Exception( 'Network connection unstable', 0 );
        $this->error_handler->handle_error(
            $network_exception->getMessage(),
            'WARNING',
            'NETWORK',
            $network_exception
        );
        
        $error_logs = $this->error_handler->get_error_logs( [ 'limit' => 1 ] );
        $this->assertEquals( 'WARNING', $error_logs[0]['severity'] );
    }

    /**
     * Test error log filtering
     */
    public function test_error_log_filtering() {
        // Add errors with different severities and categories
        $this->error_handler->handle_error( 'Critical error 1', 'CRITICAL', 'AUTHENTICATION' );
        $this->error_handler->handle_error( 'Error 1', 'ERROR', 'NETWORK' );
        $this->error_handler->handle_error( 'Warning 1', 'WARNING', 'VALIDATION' );
        $this->error_handler->handle_error( 'Critical error 2', 'CRITICAL', 'CONFIGURATION' );
        
        // Filter by severity
        $critical_errors = $this->error_handler->get_error_logs( [ 'severity' => 'CRITICAL' ] );
        $this->assertCount( 2, $critical_errors );
        
        foreach ( $critical_errors as $error ) {
            $this->assertEquals( 'CRITICAL', $error['severity'] );
        }
        
        // Filter by category
        $auth_errors = $this->error_handler->get_error_logs( [ 'category' => 'AUTHENTICATION' ] );
        $this->assertCount( 1, $auth_errors );
        $this->assertEquals( 'AUTHENTICATION', $auth_errors[0]['category'] );
        
        // Filter by limit
        $limited_errors = $this->error_handler->get_error_logs( [ 'limit' => 2 ] );
        $this->assertLessThanOrEqual( 2, count( $limited_errors ) );
    }

    /**
     * Test error statistics calculation
     */
    public function test_error_statistics() {
        // Add various errors
        $this->error_handler->handle_error( 'Critical error', 'CRITICAL', 'AUTHENTICATION' );
        $this->error_handler->handle_error( 'Regular error', 'ERROR', 'NETWORK' );
        $this->error_handler->handle_error( 'Warning', 'WARNING', 'VALIDATION' );
        $this->error_handler->handle_error( 'Another error', 'ERROR', 'TIMEOUT' );
        $this->error_handler->handle_error( 'Info message', 'INFO', 'CACHE' );
        
        $stats = $this->error_handler->get_error_statistics( 'hour' );
        
        $this->assertIsArray( $stats );
        $this->assertArrayHasKey( 'total_errors', $stats );
        $this->assertArrayHasKey( 'by_severity', $stats );
        $this->assertArrayHasKey( 'by_category', $stats );
        $this->assertArrayHasKey( 'error_rate', $stats );
        $this->assertArrayHasKey( 'most_common_errors', $stats );
        
        $this->assertEquals( 5, $stats['total_errors'] );
        $this->assertEquals( 1, $stats['by_severity']['CRITICAL'] );
        $this->assertEquals( 2, $stats['by_severity']['ERROR'] );
        $this->assertEquals( 1, $stats['by_severity']['WARNING'] );
        $this->assertEquals( 1, $stats['by_severity']['INFO'] );
        
        $this->assertGreaterThan( 0, $stats['error_rate'] );
    }

    /**
     * Test critical alerts
     */
    public function test_critical_alerts() {
        // Add critical error
        $this->error_handler->handle_error(
            'Critical system failure',
            'CRITICAL',
            'API_RESPONSE',
            new \Exception( 'System down', 500 )
        );
        
        $critical_alerts = $this->error_handler->get_critical_alerts();
        
        $this->assertNotEmpty( $critical_alerts );
        $this->assertEquals( 'MVDB_CRITICAL_ERROR', $critical_alerts[0]['type'] );
        $this->assertEquals( 'Critical system failure', $critical_alerts[0]['message'] );
    }

    /**
     * Test error log clearing
     */
    public function test_error_log_clearing() {
        // Add some errors
        $this->error_handler->handle_error( 'Error 1', 'ERROR', 'NETWORK' );
        $this->error_handler->handle_error( 'Error 2', 'WARNING', 'VALIDATION' );
        $this->error_handler->handle_error( 'Error 3', 'CRITICAL', 'AUTHENTICATION' );
        
        // Verify errors exist
        $all_errors = $this->error_handler->get_error_logs();
        $this->assertCount( 3, $all_errors );
        
        // Clear all errors
        $result = $this->error_handler->clear_error_logs();
        $this->assertTrue( $result );
        
        // Verify errors cleared
        $cleared_errors = $this->error_handler->get_error_logs();
        $this->assertEmpty( $cleared_errors );
    }

    /**
     * Test selective error clearing
     */
    public function test_selective_error_clearing() {
        // Add errors with different severities
        $this->error_handler->handle_error( 'Critical error', 'CRITICAL', 'AUTHENTICATION' );
        $this->error_handler->handle_error( 'Regular error', 'ERROR', 'NETWORK' );
        $this->error_handler->handle_error( 'Warning', 'WARNING', 'VALIDATION' );
        
        // Clear only WARNING errors
        $result = $this->error_handler->clear_error_logs( [ 'severity' => 'WARNING' ] );
        $this->assertTrue( $result );
        
        // Verify only WARNING errors were cleared
        $remaining_errors = $this->error_handler->get_error_logs();
        $this->assertCount( 2, $remaining_errors );
        
        foreach ( $remaining_errors as $error ) {
            $this->assertNotEquals( 'WARNING', $error['severity'] );
        }
    }

    /**
     * Test context sanitization
     */
    public function test_context_sanitization() {
        $sensitive_context = [
            'query' => 'test query',
            'api_key' => 'secret_key_12345',
            'password' => 'user_password',
            'token' => 'auth_token_xyz',
            'normal_data' => 'regular_value'
        ];
        
        $this->error_handler->handle_error(
            'Test error with sensitive context',
            'ERROR',
            'VALIDATION',
            null,
            $sensitive_context
        );
        
        $error_logs = $this->error_handler->get_error_logs( [ 'limit' => 1 ] );
        $context = $error_logs[0]['context'];
        
        $this->assertEquals( 'test query', $context['query'] );
        $this->assertEquals( '[REDACTED]', $context['api_key'] );
        $this->assertEquals( '[REDACTED]', $context['password'] );
        $this->assertEquals( '[REDACTED]', $context['token'] );
        $this->assertEquals( 'regular_value', $context['normal_data'] );
    }

    /**
     * Test error rate calculation over time
     */
    public function test_error_rate_calculation() {
        // Add multiple errors to test rate calculation
        for ( $i = 0; $i < 10; $i++ ) {
            $this->error_handler->handle_error( "Error $i", 'ERROR', 'NETWORK' );
        }
        
        $stats_hour = $this->error_handler->get_error_statistics( 'hour' );
        $stats_day = $this->error_handler->get_error_statistics( 'day' );
        
        $this->assertGreaterThan( 0, $stats_hour['error_rate'] );
        $this->assertGreaterThan( 0, $stats_day['error_rate'] );
        
        // Hour rate should be higher than day rate for the same number of errors
        $this->assertGreaterThan( $stats_day['error_rate'], $stats_hour['error_rate'] );
    }

    /**
     * Test stack trace formatting
     */
    public function test_stack_trace_formatting() {
        $exception = new \Exception( 'Test exception with stack trace' );
        
        $this->error_handler->handle_error(
            'Error with exception',
            'ERROR',
            'UNKNOWN',
            $exception
        );
        
        $error_logs = $this->error_handler->get_error_logs( [ 'limit' => 1 ] );
        $this->assertNotNull( $error_logs[0]['stack_trace'] );
        $this->assertIsArray( $error_logs[0]['stack_trace'] );
        $this->assertNotEmpty( $error_logs[0]['stack_trace'] );
    }

    /**
     * Test memory usage tracking
     */
    public function test_memory_usage_tracking() {
        $this->error_handler->handle_error( 'Memory test error', 'ERROR', 'UNKNOWN' );
        
        $error_logs = $this->error_handler->get_error_logs( [ 'limit' => 1 ] );
        
        $this->assertArrayHasKey( 'memory_usage', $error_logs[0] );
        $this->assertArrayHasKey( 'peak_memory', $error_logs[0] );
        $this->assertIsInt( $error_logs[0]['memory_usage'] );
        $this->assertIsInt( $error_logs[0]['peak_memory'] );
        $this->assertGreaterThan( 0, $error_logs[0]['memory_usage'] );
        $this->assertGreaterThan( 0, $error_logs[0]['peak_memory'] );
    }
}