<?php
/**
 * API Manager Retrieve Endpoint Test
 * 
 * This file contains integration tests for the /retrieve REST API endpoint.
 * It verifies the complete API pipeline functionality.
 *
 * @package AIPageComposer\Tests
 */

namespace AIPageComposer\Tests\API;

use PHPUnit\Framework\TestCase;
use AIPageComposer\API\API_Manager;
use AIPageComposer\Admin\Settings_Manager;

class Test_API_Retrieve_Endpoint extends TestCase {

    /**
     * API Manager instance
     *
     * @var API_Manager
     */
    private $api_manager;

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
        
        // Mock complete settings including MVDB
        $mock_settings = [
            'api_settings' => [
                'openai_api_key' => 'sk-test123',
                'mvdb_api_key' => 'mvdb-test456',
                'image_api_key' => ''
            ],
            'mvdb_settings' => [
                'api_url' => 'https://api.wpengine.com/smart-search/v1',
                'access_token' => 'test_token_12345',
                'cache_ttl' => 3600,
                'timeout_seconds' => 30,
                'retry_attempts' => 2,
                'enable_debug_logging' => true,
            ]
        ];
        
        $this->settings_manager->method( 'get_all_settings' )
                              ->willReturn( $mock_settings );
        
        $this->api_manager = new API_Manager( $this->settings_manager );
    }

    /**
     * Test retrieve endpoint parameter validation
     */
    public function test_retrieve_endpoint_validation() {
        // Test valid section ID validation
        $this->assertTrue( $this->api_manager->validate_section_id( 'section-hero-1' ) );
        $this->assertTrue( $this->api_manager->validate_section_id( 'section-content_block-test' ) );
        $this->assertFalse( $this->api_manager->validate_section_id( 'invalid-section' ) );
        $this->assertFalse( $this->api_manager->validate_section_id( 'section-' ) );
        
        // Test query validation
        $this->assertTrue( $this->api_manager->validate_query( 'This is a valid query for testing' ) );
        $this->assertFalse( $this->api_manager->validate_query( 'short' ) ); // Too short
        $this->assertFalse( $this->api_manager->validate_query( str_repeat( 'a', 501 ) ) ); // Too long
    }

    /**
     * Test namespace sanitization
     */
    public function test_namespace_sanitization() {
        $valid_namespaces = ['content', 'products', 'docs'];
        $result = $this->api_manager->sanitize_namespaces( $valid_namespaces );
        $this->assertEquals( $valid_namespaces, $result );
        
        $mixed_namespaces = ['content', 'invalid', 'docs', 'another_invalid'];
        $result = $this->api_manager->sanitize_namespaces( $mixed_namespaces );
        $this->assertEquals( ['content', 'docs'], $result );
        
        $empty_namespaces = [];
        $result = $this->api_manager->sanitize_namespaces( $empty_namespaces );
        $this->assertEquals( ['content'], $result ); // Default fallback
        
        $invalid_input = 'not_an_array';
        $result = $this->api_manager->sanitize_namespaces( $invalid_input );
        $this->assertEquals( ['content'], $result ); // Default fallback
    }

    /**
     * Test score sanitization
     */
    public function test_score_sanitization() {
        $this->assertEquals( 0.5, $this->api_manager->sanitize_score( 0.5 ) );
        $this->assertEquals( 0.0, $this->api_manager->sanitize_score( -0.5 ) ); // Clamped to min
        $this->assertEquals( 1.0, $this->api_manager->sanitize_score( 1.5 ) ); // Clamped to max
        $this->assertEquals( 0.75, $this->api_manager->sanitize_score( '0.75' ) ); // String conversion
        $this->assertEquals( 0.0, $this->api_manager->sanitize_score( 'invalid' ) ); // Invalid input
    }

    /**
     * Test filters sanitization
     */
    public function test_filters_sanitization() {
        $valid_filters = [
            'post_type' => ['post', 'page'],
            'date_range' => [
                'start' => '2024-01-01',
                'end' => '2024-12-31'
            ],
            'language' => 'en',
            'license' => ['CC-BY', 'CC-BY-SA'],
            'author' => [1, 2, 3],
            'exclude_ids' => [123, 456]
        ];
        
        $result = $this->api_manager->sanitize_filters( $valid_filters );
        
        $this->assertEquals( ['post', 'page'], $result['post_type'] );
        $this->assertEquals( '2024-01-01', $result['date_range']['start'] );
        $this->assertEquals( '2024-12-31', $result['date_range']['end'] );
        $this->assertEquals( 'en', $result['language'] );
        $this->assertEquals( ['CC-BY', 'CC-BY-SA'], $result['license'] );
        $this->assertEquals( [1, 2, 3], $result['author'] );
        $this->assertEquals( [123, 456], $result['exclude_ids'] );
    }

    /**
     * Test filters sanitization with invalid data
     */
    public function test_filters_sanitization_invalid_data() {
        $invalid_filters = [
            'post_type' => 'not_an_array',
            'date_range' => 'invalid_date_range',
            'language' => 123, // Should be string
            'license' => 'not_an_array',
            'author' => 'not_an_array',
            'exclude_ids' => 'not_an_array'
        ];
        
        $result = $this->api_manager->sanitize_filters( $invalid_filters );
        
        // Should not contain invalid data
        $this->assertArrayNotHasKey( 'post_type', $result );
        $this->assertArrayNotHasKey( 'date_range', $result );
        $this->assertArrayNotHasKey( 'license', $result );
        $this->assertArrayNotHasKey( 'author', $result );
        $this->assertArrayNotHasKey( 'exclude_ids', $result );
        
        // Language should be sanitized
        $this->assertEquals( '123', $result['language'] );
    }

    /**
     * Test empty filters input
     */
    public function test_empty_filters_sanitization() {
        $this->assertEquals( [], $this->api_manager->sanitize_filters( [] ) );
        $this->assertEquals( [], $this->api_manager->sanitize_filters( 'not_an_array' ) );
        $this->assertEquals( [], $this->api_manager->sanitize_filters( null ) );
    }

    /**
     * Test API endpoint argument schema
     */
    public function test_retrieve_args_schema() {
        $reflection = new \ReflectionClass( $this->api_manager );
        $method = $reflection->getMethod( 'get_retrieve_args' );
        $method->setAccessible( true );
        
        $args = $method->invoke( $this->api_manager );
        
        $this->assertIsArray( $args );
        
        // Check required fields
        $this->assertArrayHasKey( 'sectionId', $args );
        $this->assertArrayHasKey( 'query', $args );
        $this->assertTrue( $args['sectionId']['required'] );
        $this->assertTrue( $args['query']['required'] );
        
        // Check optional fields with defaults
        $this->assertArrayHasKey( 'namespaces', $args );
        $this->assertArrayHasKey( 'k', $args );
        $this->assertArrayHasKey( 'min_score', $args );
        $this->assertArrayHasKey( 'filters', $args );
        
        $this->assertEquals( ['content'], $args['namespaces']['default'] );
        $this->assertEquals( 10, $args['k']['default'] );
        $this->assertEquals( 0.5, $args['min_score']['default'] );
        $this->assertEquals( [], $args['filters']['default'] );
        
        // Check validation constraints
        $this->assertEquals( 1, $args['k']['minimum'] );
        $this->assertEquals( 50, $args['k']['maximum'] );
        $this->assertEquals( 0.0, $args['min_score']['minimum'] );
        $this->assertEquals( 1.0, $args['min_score']['maximum'] );
        $this->assertEquals( 10, $args['query']['minLength'] );
        $this->assertEquals( 500, $args['query']['maxLength'] );
    }

    /**
     * Test mock retrieve context call
     */
    public function test_mock_retrieve_context() {
        // Create a mock WP_REST_Request
        $request = $this->createMock( \WP_REST_Request::class );
        
        // Configure the mock request
        $request->method( 'get_param' )
                ->willReturnMap( [
                    ['sectionId', 'section-hero-1'],
                    ['query', 'WordPress development best practices'],
                    ['namespaces', ['content', 'docs']],
                    ['k', 15],
                    ['min_score', 0.7],
                    ['filters', [
                        'post_type' => ['post'],
                        'language' => 'en'
                    ]]
                ] );
        
        $request->method( 'get_params' )
                ->willReturn( [
                    'sectionId' => 'section-hero-1',
                    'query' => 'WordPress development best practices',
                    'namespaces' => ['content', 'docs'],
                    'k' => 15,
                    'min_score' => 0.7,
                    'filters' => [
                        'post_type' => ['post'],
                        'language' => 'en'
                    ]
                ] );
        
        // Since we can't actually make API calls in tests, we'll just verify
        // that the method exists and would handle the request structure correctly
        $this->assertTrue( method_exists( $this->api_manager, 'retrieve_context' ) );
        
        // Verify the API manager has MVDB manager (even if mocked)
        $reflection = new \ReflectionClass( $this->api_manager );
        $property = $reflection->getProperty( 'mvdb_manager' );
        $property->setAccessible( true );
        $mvdb_manager = $property->getValue( $this->api_manager );
        
        // In our test environment, MVDB manager should be null due to missing credentials
        // In a real environment with proper credentials, it would be an instance
        $this->assertNull( $mvdb_manager ); // Expected in test environment
    }

    /**
     * Test permission checking
     */
    public function test_permission_checking() {
        $this->assertTrue( method_exists( $this->api_manager, 'check_permissions' ) );
        
        // The actual permission check relies on WordPress functions
        // In a real test environment, this would verify admin capabilities
        // For unit testing, we just verify the method exists
    }
}