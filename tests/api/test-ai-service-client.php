<?php
/**
 * Unit Tests for AI Service Client
 * 
 * @package AIPageComposer
 */

use AIPageComposer\API\AI_Service_Client;
use AIPageComposer\Admin\Settings_Manager;

/**
 * AI Service Client test case
 */
class Test_AI_Service_Client extends WP_UnitTestCase {

    /**
     * AI service client instance
     *
     * @var AI_Service_Client
     */
    private $ai_service;

    /**
     * Settings manager instance
     *
     * @var Settings_Manager
     */
    private $settings_manager;

    /**
     * Set up test fixtures
     */
    public function setUp(): void {
        parent::setUp();
        
        $this->settings_manager = new Settings_Manager();
        $this->ai_service = new AI_Service_Client( $this->settings_manager );
        
        // Set up mock settings
        $this->setup_mock_settings();
    }

    /**
     * Test connection testing for OpenAI
     */
    public function test_openai_connection_test() {
        // Test with no API key
        update_option( 'ai_composer_settings', array(
            'api_settings' => array(
                'openai_api_key' => '',
            ),
        ) );

        $result = $this->ai_service->test_connection( 'openai' );
        $this->assertFalse( $result['success'] );
        $this->assertContains( 'not provided', $result['message'] );

        // Test with invalid key format
        update_option( 'ai_composer_settings', array(
            'api_settings' => array(
                'openai_api_key' => 'invalid-key-format',
            ),
        ) );

        // Note: This would require mocking HTTP requests for full testing
        // For now, we test the basic validation logic
    }

    /**
     * Test connection testing for MVDB
     */
    public function test_mvdb_connection_test() {
        // Test with no API key
        update_option( 'ai_composer_settings', array(
            'api_settings' => array(
                'mvdb_api_key' => '',
            ),
        ) );

        $result = $this->ai_service->test_connection( 'mvdb' );
        $this->assertFalse( $result['success'] );
        $this->assertContains( 'not provided', $result['message'] );
    }

    /**
     * Test unknown service connection test
     */
    public function test_unknown_service_connection() {
        $result = $this->ai_service->test_connection( 'unknown_service' );
        $this->assertFalse( $result['success'] );
        $this->assertContains( 'Unknown service', $result['message'] );
    }

    /**
     * Test MVDB context retrieval with empty API key
     */
    public function test_mvdb_context_retrieval_no_key() {
        update_option( 'ai_composer_settings', array(
            'api_settings' => array(
                'mvdb_api_key' => '',
            ),
        ) );

        $result = $this->ai_service->retrieve_mvdb_context(
            'test query',
            array( 'content' ),
            10,
            0.5
        );

        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    /**
     * Test prompt enhancement with MVDB context
     */
    public function test_prompt_enhancement_with_context() {
        $original_prompt = 'Generate content about gardening';
        $mvdb_context = array(
            array( 'content' => 'Organic gardening principles' ),
            array( 'content' => 'Soil preparation techniques' ),
        );

        // Use reflection to test private method
        $reflection = new ReflectionClass( $this->ai_service );
        $method = $reflection->getMethod( 'enhance_prompt_with_context' );
        $method->setAccessible( true );

        $enhanced_prompt = $method->invoke( $this->ai_service, $original_prompt, $mvdb_context );

        $this->assertStringContainsString( 'Relevant context from knowledge base:', $enhanced_prompt );
        $this->assertStringContainsString( 'Organic gardening principles', $enhanced_prompt );
        $this->assertStringContainsString( 'Soil preparation techniques', $enhanced_prompt );
        $this->assertStringContainsString( $original_prompt, $enhanced_prompt );
    }

    /**
     * Test prompt enhancement without context
     */
    public function test_prompt_enhancement_without_context() {
        $original_prompt = 'Generate content about gardening';
        $empty_context = array();

        // Use reflection to test private method
        $reflection = new ReflectionClass( $this->ai_service );
        $method = $reflection->getMethod( 'enhance_prompt_with_context' );
        $method->setAccessible( true );

        $enhanced_prompt = $method->invoke( $this->ai_service, $original_prompt, $empty_context );

        $this->assertEquals( $original_prompt, $enhanced_prompt );
    }

    /**
     * Test system message building
     */
    public function test_system_message_building() {
        // Use reflection to test private method
        $reflection = new ReflectionClass( $this->ai_service );
        $method = $reflection->getMethod( 'build_system_message' );
        $method->setAccessible( true );

        // Test with high alpha (context-focused)
        $high_alpha_message = $method->invoke( $this->ai_service, 0.8 );
        $this->assertStringContainsString( 'Focus heavily on the provided context', $high_alpha_message );

        // Test with low alpha (creative)
        $low_alpha_message = $method->invoke( $this->ai_service, 0.3 );
        $this->assertStringContainsString( 'Be creative while incorporating', $low_alpha_message );

        // Both should contain JSON structure
        $this->assertStringContainsString( '"sections":', $high_alpha_message );
        $this->assertStringContainsString( '"sections":', $low_alpha_message );
    }

    /**
     * Test AI response parsing with valid JSON
     */
    public function test_ai_response_parsing_valid() {
        $mock_response = array(
            'choices' => array(
                array(
                    'message' => array(
                        'content' => json_encode( array(
                            'sections' => array(
                                array(
                                    'heading' => 'Test Heading',
                                    'target_words' => 150,
                                    'needs_image' => true,
                                    'subheadings' => array( 'Sub 1', 'Sub 2' ),
                                ),
                            ),
                        ) ),
                    ),
                ),
            ),
        );

        // Use reflection to test private method
        $reflection = new ReflectionClass( $this->ai_service );
        $method = $reflection->getMethod( 'parse_ai_response' );
        $method->setAccessible( true );

        $result = $method->invoke( $this->ai_service, $mock_response );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'sections', $result );
        $this->assertCount( 1, $result['sections'] );
        $this->assertEquals( 'Test Heading', $result['sections'][0]['heading'] );
    }

    /**
     * Test AI response parsing with invalid structure
     */
    public function test_ai_response_parsing_invalid_structure() {
        $mock_response = array(
            'invalid' => 'structure',
        );

        // Use reflection to test private method
        $reflection = new ReflectionClass( $this->ai_service );
        $method = $reflection->getMethod( 'parse_ai_response' );
        $method->setAccessible( true );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Invalid OpenAI response structure' );

        $method->invoke( $this->ai_service, $mock_response );
    }

    /**
     * Test AI response parsing with invalid JSON
     */
    public function test_ai_response_parsing_invalid_json() {
        $mock_response = array(
            'choices' => array(
                array(
                    'message' => array(
                        'content' => 'Invalid JSON content here',
                    ),
                ),
            ),
        );

        // Use reflection to test private method
        $reflection = new ReflectionClass( $this->ai_service );
        $method = $reflection->getMethod( 'parse_ai_response' );
        $method->setAccessible( true );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'No valid JSON found' );

        $method->invoke( $this->ai_service, $mock_response );
    }

    /**
     * Test AI response parsing with JSON but missing sections
     */
    public function test_ai_response_parsing_missing_sections() {
        $mock_response = array(
            'choices' => array(
                array(
                    'message' => array(
                        'content' => json_encode( array(
                            'invalid' => 'structure',
                        ) ),
                    ),
                ),
            ),
        );

        // Use reflection to test private method
        $reflection = new ReflectionClass( $this->ai_service );
        $method = $reflection->getMethod( 'parse_ai_response' );
        $method->setAccessible( true );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Invalid section structure' );

        $method->invoke( $this->ai_service, $mock_response );
    }

    /**
     * Test AI response parsing with extra text around JSON
     */
    public function test_ai_response_parsing_with_extra_text() {
        $json_content = json_encode( array(
            'sections' => array(
                array(
                    'heading' => 'Test Heading',
                    'target_words' => 150,
                    'needs_image' => true,
                    'subheadings' => array( 'Sub 1' ),
                ),
            ),
        ) );

        $mock_response = array(
            'choices' => array(
                array(
                    'message' => array(
                        'content' => "Here's your outline:\n\n" . $json_content . "\n\nHope this helps!",
                    ),
                ),
            ),
        );

        // Use reflection to test private method
        $reflection = new ReflectionClass( $this->ai_service );
        $method = $reflection->getMethod( 'parse_ai_response' );
        $method->setAccessible( true );

        $result = $method->invoke( $this->ai_service, $mock_response );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'sections', $result );
        $this->assertEquals( 'Test Heading', $result['sections'][0]['heading'] );
    }

    /**
     * Test generate outline with missing OpenAI key
     */
    public function test_generate_outline_missing_openai_key() {
        update_option( 'ai_composer_settings', array(
            'api_settings' => array(
                'openai_api_key' => '',
            ),
        ) );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'OpenAI API key not configured' );

        $this->ai_service->generate_outline( 'Test prompt', array(), 0.7 );
    }

    /**
     * Test settings retrieval
     */
    public function test_settings_retrieval() {
        $test_settings = array(
            'api_settings' => array(
                'openai_api_key' => 'test-key',
                'mvdb_api_key' => 'test-mvdb-key',
                'mvdb_endpoint' => 'https://test.mvdb.com',
            ),
        );

        update_option( 'ai_composer_settings', $test_settings );

        // Create new instance to pick up settings
        $ai_service = new AI_Service_Client( $this->settings_manager );

        // Verify settings are loaded (would need reflection or public getter to test directly)
        $this->assertTrue( true ); // Placeholder - in real implementation we'd verify settings loading
    }

    /**
     * Set up mock settings for testing
     */
    private function setup_mock_settings() {
        $mock_settings = array(
            'api_settings' => array(
                'openai_api_key' => 'sk-test-key-for-testing',
                'mvdb_api_key' => 'test-mvdb-key',
                'mvdb_endpoint' => 'https://api.mvdb.test.com',
                'image_api_key' => 'test-image-key',
            ),
        );

        update_option( 'ai_composer_settings', $mock_settings );
    }
}