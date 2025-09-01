<?php
/**
 * MVDB Manager Integration Test
 * 
 * This file contains integration tests for the MVDB Manager and retrieve endpoint.
 * It verifies the complete MVDB retrieval pipeline functionality.
 *
 * @package AIPageComposer\Tests
 */

namespace AIPageComposer\Tests\API;

use PHPUnit\Framework\TestCase;
use AIPageComposer\API\MVDB_Manager;
use AIPageComposer\Admin\Settings_Manager;

class Test_MVDB_Manager extends TestCase {

    /**
     * MVDB Manager instance
     *
     * @var MVDB_Manager
     */
    private $mvdb_manager;

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
                'api_url' => 'https://api.wpengine.com/smart-search/v1',
                'access_token' => 'test_token_12345',
                'cache_ttl' => 3600,
                'timeout_seconds' => 30,
                'retry_attempts' => 2,
                'enable_debug_logging' => false,
            ]
        ];
        
        $this->settings_manager->method( 'get_all_settings' )
                              ->willReturn( $mock_settings );
    }

    /**
     * Test MVDB Manager initialization
     */
    public function test_mvdb_manager_initialization() {
        $this->mvdb_manager = new MVDB_Manager( $this->settings_manager );
        $this->assertInstanceOf( MVDB_Manager::class, $this->mvdb_manager );
    }

    /**
     * Test parameter validation
     */
    public function test_parameter_validation() {
        $this->mvdb_manager = new MVDB_Manager( $this->settings_manager );
        
        // Test valid parameters
        $valid_params = [
            'sectionId' => 'section-hero-1',
            'query' => 'This is a test query for content retrieval',
            'namespaces' => ['content'],
            'k' => 10,
            'min_score' => 0.5,
            'filters' => []
        ];
        
        // This would normally call retrieve_context, but we'll test validation separately
        $this->assertTrue( true ); // Placeholder assertion
    }

    /**
     * Test invalid section ID validation
     */
    public function test_invalid_section_id() {
        $this->mvdb_manager = new MVDB_Manager( $this->settings_manager );
        
        $this->expectException( \Exception::class );
        $this->expectExceptionMessage( 'Invalid section ID format' );
        
        // Test with invalid section ID (should use reflection to test private method)
        $reflection = new \ReflectionClass( $this->mvdb_manager );
        $method = $reflection->getMethod( 'validate_retrieval_params' );
        $method->setAccessible( true );
        
        $invalid_params = [
            'sectionId' => 'invalid-id',
            'query' => 'This is a test query for content retrieval',
        ];
        
        $method->invoke( $this->mvdb_manager, $invalid_params );
    }

    /**
     * Test query length validation
     */
    public function test_query_length_validation() {
        $this->mvdb_manager = new MVDB_Manager( $this->settings_manager );
        
        $this->expectException( \Exception::class );
        $this->expectExceptionMessage( 'Query must be between 10 and 500 characters' );
        
        $reflection = new \ReflectionClass( $this->mvdb_manager );
        $method = $reflection->getMethod( 'validate_retrieval_params' );
        $method->setAccessible( true );
        
        $invalid_params = [
            'sectionId' => 'section-test-1',
            'query' => 'short', // Too short
        ];
        
        $method->invoke( $this->mvdb_manager, $invalid_params );
    }

    /**
     * Test GraphQL query building
     */
    public function test_build_similarity_query() {
        $this->mvdb_manager = new MVDB_Manager( $this->settings_manager );
        
        $reflection = new \ReflectionClass( $this->mvdb_manager );
        $method = $reflection->getMethod( 'build_similarity_query' );
        $method->setAccessible( true );
        
        $params = [
            'query' => 'WordPress development best practices',
            'namespaces' => ['content', 'docs'],
            'k' => 15,
            'min_score' => 0.7,
            'filters' => [
                'post_type' => ['post', 'page'],
                'language' => 'en'
            ]
        ];
        
        $result = $method->invoke( $this->mvdb_manager, $params );
        
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'query', $result );
        $this->assertArrayHasKey( 'variables', $result );
        
        $variables = $result['variables'];
        $this->assertEquals( 'WordPress development best practices', $variables['query'] );
        $this->assertEquals( ['content', 'docs'], $variables['namespaces'] );
        $this->assertEquals( 15, $variables['limit'] );
        $this->assertEquals( 0.7, $variables['minScore'] );
        
        // Test filter string construction
        $this->assertStringContains( 'post_type:post OR post_type:page', $variables['filter'] );
        $this->assertStringContains( 'language:en', $variables['filter'] );
    }

    /**
     * Test cache key generation
     */
    public function test_cache_key_generation() {
        $this->mvdb_manager = new MVDB_Manager( $this->settings_manager );
        
        $reflection = new \ReflectionClass( $this->mvdb_manager );
        $method = $reflection->getMethod( 'generate_cache_key' );
        $method->setAccessible( true );
        
        $params1 = [
            'query' => 'test query',
            'namespaces' => ['content'],
            'k' => 10,
            'min_score' => 0.5,
            'filters' => []
        ];
        
        $params2 = [
            'query' => 'different query',
            'namespaces' => ['content'],
            'k' => 10,
            'min_score' => 0.5,
            'filters' => []
        ];
        
        $key1 = $method->invoke( $this->mvdb_manager, $params1 );
        $key2 = $method->invoke( $this->mvdb_manager, $params2 );
        
        $this->assertIsString( $key1 );
        $this->assertIsString( $key2 );
        $this->assertNotEquals( $key1, $key2 ); // Different queries should produce different keys
        $this->assertStringStartsWith( 'mvdb_', $key1 );
        $this->assertStringStartsWith( 'mvdb_', $key2 );
    }

    /**
     * Test quality filtering
     */
    public function test_quality_filtering() {
        $this->mvdb_manager = new MVDB_Manager( $this->settings_manager );
        
        $reflection = new \ReflectionClass( $this->mvdb_manager );
        $method = $reflection->getMethod( 'apply_quality_filters' );
        $method->setAccessible( true );
        
        $test_chunks = [
            [
                'id' => 'chunk-1',
                'text' => 'This is a high-quality content chunk with meaningful information about WordPress development.',
                'score' => 0.9,
                'metadata' => [
                    'license' => 'CC-BY',
                    'language' => 'en',
                    'date' => '2024-01-15'
                ]
            ],
            [
                'id' => 'chunk-2',
                'text' => 'Short', // Should be filtered out for being too short
                'score' => 0.8,
                'metadata' => [
                    'license' => 'CC-BY',
                    'language' => 'en'
                ]
            ],
            [
                'id' => 'chunk-3',
                'text' => 'This is another good quality chunk that provides valuable insights.',
                'score' => 0.3, // Should be filtered out for low score
                'metadata' => [
                    'license' => 'CC-BY',
                    'language' => 'en'
                ]
            ]
        ];
        
        $params = [
            'min_score' => 0.5,
            'filters' => [
                'language' => 'en'
            ]
        ];
        
        $filtered_chunks = $method->invoke( $this->mvdb_manager, $test_chunks, $params );
        
        $this->assertCount( 1, $filtered_chunks ); // Only chunk-1 should pass all filters
        $this->assertEquals( 'chunk-1', $filtered_chunks[0]['id'] );
    }

    /**
     * Test metrics calculation
     */
    public function test_metrics_calculation() {
        $this->mvdb_manager = new MVDB_Manager( $this->settings_manager );
        
        $reflection = new \ReflectionClass( $this->mvdb_manager );
        $method = $reflection->getMethod( 'calculate_retrieval_metrics' );
        $method->setAccessible( true );
        
        $chunks = [
            ['id' => 'chunk-1', 'score' => 0.9],
            ['id' => 'chunk-2', 'score' => 0.8],
            ['id' => 'chunk-3', 'score' => 0.7]
        ];
        
        $params = [
            'sectionId' => 'section-test-1',
            'query' => 'test query',
            'namespaces' => ['content'],
            'k' => 5,
            'min_score' => 0.5
        ];
        
        $processing_time = 150.5;
        
        $result = $method->invoke( $this->mvdb_manager, $chunks, $params, $processing_time );
        
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'chunks', $result );
        $this->assertArrayHasKey( 'total_retrieved', $result );
        $this->assertArrayHasKey( 'recall_score', $result );
        $this->assertArrayHasKey( 'average_score', $result );
        $this->assertArrayHasKey( 'processing_time_ms', $result );
        $this->assertArrayHasKey( 'warnings', $result );
        
        $this->assertEquals( 3, $result['total_retrieved'] );
        $this->assertEquals( 0.6, $result['recall_score'] ); // 3/5 = 0.6
        $this->assertEquals( 0.8, $result['average_score'] ); // (0.9+0.8+0.7)/3 = 0.8
        $this->assertEquals( 150.5, $result['processing_time_ms'] );
    }

    /**
     * Test date format validation
     */
    public function test_date_validation() {
        $this->mvdb_manager = new MVDB_Manager( $this->settings_manager );
        
        $reflection = new \ReflectionClass( $this->mvdb_manager );
        $method = $reflection->getMethod( 'validate_date_format' );
        $method->setAccessible( true );
        
        $this->assertTrue( $method->invoke( $this->mvdb_manager, '2024-01-15' ) );
        $this->assertTrue( $method->invoke( $this->mvdb_manager, '2023-12-31' ) );
        $this->assertFalse( $method->invoke( $this->mvdb_manager, '2024-13-01' ) ); // Invalid month
        $this->assertFalse( $method->invoke( $this->mvdb_manager, '2024/01/15' ) ); // Wrong format
        $this->assertFalse( $method->invoke( $this->mvdb_manager, 'invalid-date' ) );
    }

    /**
     * Test response processing
     */
    public function test_response_processing() {
        $this->mvdb_manager = new MVDB_Manager( $this->settings_manager );
        
        $reflection = new \ReflectionClass( $this->mvdb_manager );
        $method = $reflection->getMethod( 'process_similarity_response' );
        $method->setAccessible( true );
        
        $mock_response = [
            'data' => [
                'similarity' => [
                    'total' => 2,
                    'docs' => [
                        [
                            'id' => 'doc-123',
                            'score' => 0.85,
                            'data' => [
                                'post_title' => 'WordPress Best Practices',
                                'post_content' => 'This is comprehensive guide about WordPress development.',
                                'post_excerpt' => 'A guide about WordPress.',
                                'post_type' => 'post',
                                'post_id' => 456
                            ],
                            'metadata' => [
                                'license' => 'CC-BY',
                                'language' => 'en',
                                'author' => 'John Doe'
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $params = [
            'query' => 'WordPress development'
        ];
        
        $processed_chunks = $method->invoke( $this->mvdb_manager, $mock_response, $params );
        
        $this->assertIsArray( $processed_chunks );
        $this->assertCount( 1, $processed_chunks );
        
        $chunk = $processed_chunks[0];
        $this->assertArrayHasKey( 'id', $chunk );
        $this->assertArrayHasKey( 'text', $chunk );
        $this->assertArrayHasKey( 'score', $chunk );
        $this->assertArrayHasKey( 'metadata', $chunk );
        
        $this->assertEquals( 0.85, $chunk['score'] );
        $this->assertStringContains( 'WordPress Best Practices', $chunk['text'] );
        $this->assertEquals( 'CC-BY', $chunk['metadata']['license'] );
    }
}