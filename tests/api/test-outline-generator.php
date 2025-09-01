<?php
/**
 * Unit Tests for Outline Generator
 * 
 * @package AIPageComposer
 */

use AIPageComposer\API\Outline_Generator;
use AIPageComposer\Blueprints\Blueprint_Manager;
use AIPageComposer\Admin\Block_Preferences;

/**
 * Outline Generator test case
 */
class Test_Outline_Generator extends WP_UnitTestCase {

    /**
     * Outline generator instance
     *
     * @var Outline_Generator
     */
    private $outline_generator;

    /**
     * Blueprint manager instance
     *
     * @var Blueprint_Manager
     */
    private $blueprint_manager;

    /**
     * Block preferences instance
     *
     * @var Block_Preferences
     */
    private $block_preferences;

    /**
     * Test blueprint data
     *
     * @var array
     */
    private $test_blueprint;

    /**
     * Set up test fixtures
     */
    public function setUp(): void {
        parent::setUp();

        // Initialize components
        $this->block_preferences = new Block_Preferences();
        $this->blueprint_manager = new Blueprint_Manager( $this->block_preferences );
        $this->outline_generator = new Outline_Generator( $this->blueprint_manager, $this->block_preferences );

        // Create test blueprint data
        $this->test_blueprint = $this->create_test_blueprint_data();

        // Force stub mode for testing
        if ( ! defined( 'AI_COMPOSER_DEV_MODE' ) ) {
            define( 'AI_COMPOSER_DEV_MODE', true );
        }
    }

    /**
     * Test stub mode outline generation
     */
    public function test_stub_mode_generation() {
        $params = array(
            'blueprint_id' => 123,
            'brief' => 'Create a comprehensive guide about sustainable gardening practices for urban environments',
            'audience' => 'Urban gardeners',
            'tone' => 'friendly',
            'mvdb_params' => array(),
            'alpha' => 0.7,
        );

        $result = $this->outline_generator->generate( $params, $this->test_blueprint );

        // Verify response structure
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'sections', $result );
        $this->assertArrayHasKey( 'total_words', $result );
        $this->assertArrayHasKey( 'estimated_time', $result );
        $this->assertArrayHasKey( 'mode', $result );
        $this->assertArrayHasKey( 'estimated_cost', $result );
        $this->assertArrayHasKey( 'generated_at', $result );
        $this->assertArrayHasKey( 'blueprint_id', $result );

        // Verify mode is stub
        $this->assertEquals( 'stub', $result['mode'] );
        $this->assertEquals( 0.0, $result['estimated_cost'] );
        $this->assertEquals( 123, $result['blueprint_id'] );

        // Verify sections structure
        $this->assertIsArray( $result['sections'] );
        $this->assertNotEmpty( $result['sections'] );

        foreach ( $result['sections'] as $section ) {
            $this->assertArrayHasKey( 'id', $section );
            $this->assertArrayHasKey( 'heading', $section );
            $this->assertArrayHasKey( 'type', $section );
            $this->assertArrayHasKey( 'targetWords', $section );
            $this->assertArrayHasKey( 'needsImage', $section );
            $this->assertArrayHasKey( 'mode', $section );
            
            $this->assertIsString( $section['id'] );
            $this->assertIsString( $section['heading'] );
            $this->assertIsString( $section['type'] );
            $this->assertIsInt( $section['targetWords'] );
            $this->assertIsBool( $section['needsImage'] );
            $this->assertEquals( 'stub', $section['mode'] );
        }
    }

    /**
     * Test outline generation with different section types
     */
    public function test_different_section_types() {
        $blueprint = array(
            'post' => (object) array( 'ID' => 123 ),
            'schema' => array(
                'sections' => array(
                    array( 'type' => 'hero', 'word_target' => 100, 'media_policy' => 'required' ),
                    array( 'type' => 'content', 'word_target' => 250, 'media_policy' => 'optional' ),
                    array( 'type' => 'testimonial', 'word_target' => 150, 'media_policy' => 'required' ),
                    array( 'type' => 'pricing', 'word_target' => 200, 'media_policy' => 'required' ),
                    array( 'type' => 'team', 'word_target' => 180, 'media_policy' => 'required' ),
                    array( 'type' => 'faq', 'word_target' => 120, 'media_policy' => 'none' ),
                    array( 'type' => 'cta', 'word_target' => 80, 'media_policy' => 'optional' ),
                ),
            ),
        );

        $params = array(
            'brief' => 'Create content about digital marketing strategies',
            'tone' => 'professional',
        );

        $result = $this->outline_generator->generate( $params, $blueprint );

        $this->assertEquals( 7, count( $result['sections'] ) );

        $section_types = array_column( $result['sections'], 'type' );
        $this->assertContains( 'hero', $section_types );
        $this->assertContains( 'content', $section_types );
        $this->assertContains( 'testimonial', $section_types );
        $this->assertContains( 'pricing', $section_types );
        $this->assertContains( 'team', $section_types );
        $this->assertContains( 'faq', $section_types );
        $this->assertContains( 'cta', $section_types );

        // Verify image requirements based on media policy
        $hero_section = $result['sections'][0];
        $this->assertTrue( $hero_section['needsImage'] );

        $faq_section = $result['sections'][5];
        $this->assertFalse( $faq_section['needsImage'] );
    }

    /**
     * Test outline generation with contextual headings
     */
    public function test_contextual_headings() {
        $params = array(
            'brief' => 'Create content about organic vegetable gardening techniques for small spaces',
            'tone' => 'friendly',
        );

        $result = $this->outline_generator->generate( $params, $this->test_blueprint );

        foreach ( $result['sections'] as $section ) {
            $heading = $section['heading'];
            
            // Verify heading is not empty and contains some context
            $this->assertNotEmpty( $heading );
            $this->assertIsString( $heading );
            
            // Headings should be more than just generic placeholders
            $this->assertNotEquals( 'Section', $heading );
            $this->assertNotEquals( 'Heading', $heading );
        }
    }

    /**
     * Test total word count calculation
     */
    public function test_total_word_count() {
        $params = array( 'brief' => 'Test brief content' );
        
        $result = $this->outline_generator->generate( $params, $this->test_blueprint );
        
        $calculated_total = array_sum( array_column( $result['sections'], 'targetWords' ) );
        $this->assertEquals( $calculated_total, $result['total_words'] );
    }

    /**
     * Test estimated time calculation
     */
    public function test_estimated_time() {
        $params = array( 'brief' => 'Test brief content' );
        
        $result = $this->outline_generator->generate( $params, $this->test_blueprint );
        
        // Time should be based on word count (approx 50 words per minute)
        $expected_time = max( 5, intval( $result['total_words'] / 50 ) );
        $this->assertEquals( $expected_time, $result['estimated_time'] );
    }

    /**
     * Test generation with empty blueprint
     */
    public function test_empty_blueprint_generation() {
        $empty_blueprint = array(
            'post' => (object) array( 'ID' => 123 ),
            'schema' => array( 'sections' => array() ),
        );

        $params = array(
            'brief' => 'Create content about sustainable business practices',
            'tone' => 'professional',
        );

        $result = $this->outline_generator->generate( $params, $empty_blueprint );

        // Should generate default structure
        $this->assertNotEmpty( $result['sections'] );
        $this->assertGreaterThan( 0, $result['total_words'] );
    }

    /**
     * Test generation with different tones
     */
    public function test_different_tones() {
        $tones = array( 'professional', 'casual', 'friendly', 'technical', 'authoritative' );

        foreach ( $tones as $tone ) {
            $params = array(
                'brief' => 'Create content about renewable energy solutions',
                'tone' => $tone,
            );

            $result = $this->outline_generator->generate( $params, $this->test_blueprint );

            $this->assertIsArray( $result );
            $this->assertNotEmpty( $result['sections'] );
            
            // Professional and authoritative tones might include testimonials
            if ( in_array( $tone, array( 'professional', 'authoritative' ), true ) ) {
                $section_types = array_column( $result['sections'], 'type' );
                // This would be more relevant for default outline generation
            }
        }
    }

    /**
     * Test subheadings generation
     */
    public function test_subheadings_generation() {
        $params = array(
            'brief' => 'Create comprehensive guide for home solar installation',
            'tone' => 'technical',
        );

        $result = $this->outline_generator->generate( $params, $this->test_blueprint );

        foreach ( $result['sections'] as $section ) {
            if ( isset( $section['subheadings'] ) ) {
                $this->assertIsArray( $section['subheadings'] );
                
                // Should have 0-3 subheadings
                $this->assertLessThanOrEqual( 3, count( $section['subheadings'] ) );
                
                foreach ( $section['subheadings'] as $subheading ) {
                    $this->assertIsString( $subheading );
                    $this->assertNotEmpty( $subheading );
                }
            }
        }
    }

    /**
     * Test mode setting in response
     */
    public function test_mode_setting() {
        $params = array( 'brief' => 'Test mode setting' );
        
        $result = $this->outline_generator->generate( $params, $this->test_blueprint );
        
        // Should be in stub mode for tests
        $this->assertEquals( 'stub', $result['mode'] );
        
        // Each section should also have mode set
        foreach ( $result['sections'] as $section ) {
            $this->assertEquals( 'stub', $section['mode'] );
        }
    }

    /**
     * Test response timestamps
     */
    public function test_response_timestamps() {
        $before_time = current_time( 'c' );
        
        $params = array( 'brief' => 'Test timestamp generation' );
        $result = $this->outline_generator->generate( $params, $this->test_blueprint );
        
        $after_time = current_time( 'c' );
        
        $this->assertArrayHasKey( 'generated_at', $result );
        $this->assertNotEmpty( $result['generated_at'] );
        
        // Verify timestamp is in valid ISO format
        $timestamp = DateTime::createFromFormat( DateTime::ATOM, $result['generated_at'] );
        $this->assertInstanceOf( DateTime::class, $timestamp );
        
        // Timestamp should be between before and after
        $generated_time = $timestamp->format( 'c' );
        $this->assertGreaterThanOrEqual( $before_time, $generated_time );
        $this->assertLessThanOrEqual( $after_time, $generated_time );
    }

    /**
     * Create test blueprint data
     *
     * @return array Test blueprint data.
     */
    private function create_test_blueprint_data() {
        return array(
            'post' => (object) array( 'ID' => 123 ),
            'schema' => array(
                'sections' => array(
                    array(
                        'type' => 'hero',
                        'heading' => 'Introduction',
                        'word_target' => 150,
                        'media_policy' => 'required',
                    ),
                    array(
                        'type' => 'content',
                        'heading' => 'Main Content',
                        'word_target' => 300,
                        'media_policy' => 'optional',
                    ),
                    array(
                        'type' => 'cta',
                        'heading' => 'Call to Action',
                        'word_target' => 100,
                        'media_policy' => 'optional',
                    ),
                ),
                'global_settings' => array(
                    'total_target_words' => 550,
                    'audience' => 'general',
                    'tone' => 'professional',
                ),
            ),
        );
    }
}