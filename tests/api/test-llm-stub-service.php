<?php
/**
 * Unit Tests for LLM Stub Service
 * 
 * @package AIPageComposer
 */

use AIPageComposer\API\LLM_Stub_Service;

/**
 * LLM Stub Service test case
 */
class Test_LLM_Stub_Service extends WP_UnitTestCase {

    /**
     * LLM stub service instance
     *
     * @var LLM_Stub_Service
     */
    private $stub_service;

    /**
     * Set up test fixtures
     */
    public function setUp(): void {
        parent::setUp();
        $this->stub_service = new LLM_Stub_Service();
    }

    /**
     * Test basic outline generation
     */
    public function test_basic_outline_generation() {
        $params = array(
            'brief' => 'Create a comprehensive guide about sustainable gardening practices',
            'audience' => 'Beginning gardeners',
            'tone' => 'friendly',
        );

        $blueprint = array(
            'schema' => array(
                'sections' => array(
                    array( 'type' => 'hero', 'word_target' => 150, 'media_policy' => 'required' ),
                    array( 'type' => 'content', 'word_target' => 300, 'media_policy' => 'optional' ),
                    array( 'type' => 'testimonial', 'word_target' => 200, 'media_policy' => 'required' ),
                ),
            ),
        );

        $result = $this->stub_service->generate_outline( $params, $blueprint );

        // Verify basic structure
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'sections', $result );
        $this->assertArrayHasKey( 'total_words', $result );
        $this->assertArrayHasKey( 'estimated_time', $result );

        // Verify sections
        $this->assertCount( 3, $result['sections'] );
        $this->assertEquals( 650, $result['total_words'] );
    }

    /**
     * Test contextual heading generation
     */
    public function test_contextual_heading_generation() {
        $params = array(
            'brief' => 'Learn about machine learning algorithms for data analysis',
        );

        $blueprint = array(
            'schema' => array(
                'sections' => array(
                    array( 'type' => 'hero', 'word_target' => 100 ),
                    array( 'type' => 'content', 'word_target' => 200 ),
                ),
            ),
        );

        $result = $this->stub_service->generate_outline( $params, $blueprint );

        // Verify headings contain context from brief
        foreach ( $result['sections'] as $section ) {
            $this->assertNotEmpty( $section['heading'] );
            $this->assertIsString( $section['heading'] );
            $this->assertNotEquals( 'Section', $section['heading'] );
        }

        // At least one heading should contain a word from the brief
        $brief_words = array( 'machine', 'learning', 'algorithms', 'data', 'analysis' );
        $found_context = false;

        foreach ( $result['sections'] as $section ) {
            foreach ( $brief_words as $word ) {
                if ( stripos( $section['heading'], $word ) !== false ) {
                    $found_context = true;
                    break 2;
                }
            }
        }

        $this->assertTrue( $found_context, 'Headings should contain context from brief' );
    }

    /**
     * Test subheading generation
     */
    public function test_subheading_generation() {
        $params = array(
            'brief' => 'Complete guide to digital photography techniques',
        );

        $blueprint = array(
            'schema' => array(
                'sections' => array(
                    array( 'type' => 'hero', 'word_target' => 150 ),
                    array( 'type' => 'content', 'word_target' => 300 ),
                    array( 'type' => 'testimonial', 'word_target' => 200 ),
                ),
            ),
        );

        $result = $this->stub_service->generate_outline( $params, $blueprint );

        foreach ( $result['sections'] as $section ) {
            $this->assertArrayHasKey( 'subheadings', $section );
            $this->assertIsArray( $section['subheadings'] );
            
            // Should have 2-3 subheadings
            $this->assertGreaterThanOrEqual( 2, count( $section['subheadings'] ) );
            $this->assertLessThanOrEqual( 3, count( $section['subheadings'] ) );

            foreach ( $section['subheadings'] as $subheading ) {
                $this->assertIsString( $subheading );
                $this->assertNotEmpty( $subheading );
            }
        }
    }

    /**
     * Test image requirement determination
     */
    public function test_image_requirement_determination() {
        $blueprint = array(
            'schema' => array(
                'sections' => array(
                    array( 'type' => 'hero', 'media_policy' => 'required' ),
                    array( 'type' => 'content', 'media_policy' => 'optional' ),
                    array( 'type' => 'testimonial', 'media_policy' => 'required' ),
                    array( 'type' => 'faq', 'media_policy' => 'none' ),
                ),
            ),
        );

        $params = array( 'brief' => 'Test image requirements' );
        $result = $this->stub_service->generate_outline( $params, $blueprint );

        $this->assertTrue( $result['sections'][0]['needsImage'] ); // hero with required policy
        $this->assertFalse( $result['sections'][1]['needsImage'] ); // content with optional policy
        $this->assertTrue( $result['sections'][2]['needsImage'] ); // testimonial with required policy
        $this->assertFalse( $result['sections'][3]['needsImage'] ); // faq with none policy
    }

    /**
     * Test default section type image requirements
     */
    public function test_default_image_requirements() {
        $image_sections = array( 'hero', 'testimonial', 'team', 'pricing' );
        $non_image_sections = array( 'content', 'faq' );

        foreach ( $image_sections as $section_type ) {
            $blueprint = array(
                'schema' => array(
                    'sections' => array(
                        array( 'type' => $section_type, 'word_target' => 150 ),
                    ),
                ),
            );

            $params = array( 'brief' => 'Test ' . $section_type . ' section' );
            $result = $this->stub_service->generate_outline( $params, $blueprint );

            $this->assertTrue( $result['sections'][0]['needsImage'], $section_type . ' sections should typically need images' );
        }

        foreach ( $non_image_sections as $section_type ) {
            $blueprint = array(
                'schema' => array(
                    'sections' => array(
                        array( 'type' => $section_type, 'word_target' => 150 ),
                    ),
                ),
            );

            $params = array( 'brief' => 'Test ' . $section_type . ' section' );
            $result = $this->stub_service->generate_outline( $params, $blueprint );

            $this->assertFalse( $result['sections'][0]['needsImage'], $section_type . ' sections typically don\'t need images' );
        }
    }

    /**
     * Test default outline generation (no blueprint sections)
     */
    public function test_default_outline_generation() {
        $params = array(
            'brief' => 'Create content about renewable energy solutions',
            'audience' => 'Homeowners',
            'tone' => 'professional',
        );

        $empty_blueprint = array( 'schema' => array( 'sections' => array() ) );
        $result = $this->stub_service->generate_outline( $params, $empty_blueprint );

        // Should generate default sections
        $this->assertNotEmpty( $result['sections'] );
        $this->assertGreaterThan( 0, count( $result['sections'] ) );
        $this->assertGreaterThan( 0, $result['total_words'] );

        // Should include hero and content sections at minimum
        $section_types = array_column( $result['sections'], 'type' );
        $this->assertContains( 'hero', $section_types );
        $this->assertContains( 'content', $section_types );
    }

    /**
     * Test tone-based section variation
     */
    public function test_tone_based_variation() {
        $professional_params = array(
            'brief' => 'Business strategy consulting services',
            'tone' => 'professional',
        );

        $casual_params = array(
            'brief' => 'Fun weekend activities for families',
            'tone' => 'casual',
        );

        $empty_blueprint = array( 'schema' => array( 'sections' => array() ) );

        $professional_result = $this->stub_service->generate_outline( $professional_params, $empty_blueprint );
        $casual_result = $this->stub_service->generate_outline( $casual_params, $empty_blueprint );

        // Professional tone might include testimonials
        $prof_types = array_column( $professional_result['sections'], 'type' );
        
        // Both should have basic sections
        $this->assertContains( 'hero', $prof_types );
        $this->assertContains( 'content', $prof_types );
        
        $casual_types = array_column( $casual_result['sections'], 'type' );
        $this->assertContains( 'hero', $casual_types );
        $this->assertContains( 'content', $casual_types );
    }

    /**
     * Test audience-based variation
     */
    public function test_audience_based_variation() {
        $with_audience = array(
            'brief' => 'Software development best practices',
            'audience' => 'Junior developers',
        );

        $without_audience = array(
            'brief' => 'Software development best practices',
        );

        $empty_blueprint = array( 'schema' => array( 'sections' => array() ) );

        $with_result = $this->stub_service->generate_outline( $with_audience, $empty_blueprint );
        $without_result = $this->stub_service->generate_outline( $without_audience, $empty_blueprint );

        // With audience might include CTA
        $with_types = array_column( $with_result['sections'], 'type' );
        
        // Both should have basic structure
        $this->assertNotEmpty( $with_result['sections'] );
        $this->assertNotEmpty( $without_result['sections'] );
    }

    /**
     * Test section ID generation
     */
    public function test_section_id_generation() {
        $blueprint = array(
            'schema' => array(
                'sections' => array(
                    array( 'type' => 'hero', 'word_target' => 100 ),
                    array( 'type' => 'content', 'word_target' => 200 ),
                    array( 'type' => 'cta', 'word_target' => 80 ),
                ),
            ),
        );

        $params = array( 'brief' => 'Test section ID generation' );
        $result = $this->stub_service->generate_outline( $params, $blueprint );

        $expected_ids = array( 'section-1', 'section-2', 'section-3' );
        $actual_ids = array_column( $result['sections'], 'id' );

        $this->assertEquals( $expected_ids, $actual_ids );
    }

    /**
     * Test mode setting
     */
    public function test_mode_setting() {
        $blueprint = array(
            'schema' => array(
                'sections' => array(
                    array( 'type' => 'content', 'word_target' => 150 ),
                ),
            ),
        );

        $params = array( 'brief' => 'Test mode setting' );
        $result = $this->stub_service->generate_outline( $params, $blueprint );

        // All sections should have mode set to 'stub'
        foreach ( $result['sections'] as $section ) {
            $this->assertEquals( 'stub', $section['mode'] );
        }
    }

    /**
     * Test word count preservation
     */
    public function test_word_count_preservation() {
        $blueprint = array(
            'schema' => array(
                'sections' => array(
                    array( 'type' => 'hero', 'word_target' => 125 ),
                    array( 'type' => 'content', 'word_target' => 275 ),
                    array( 'type' => 'testimonial', 'word_target' => 175 ),
                ),
            ),
        );

        $params = array( 'brief' => 'Test word count preservation' );
        $result = $this->stub_service->generate_outline( $params, $blueprint );

        // Word targets should be preserved from blueprint
        $this->assertEquals( 125, $result['sections'][0]['targetWords'] );
        $this->assertEquals( 275, $result['sections'][1]['targetWords'] );
        $this->assertEquals( 175, $result['sections'][2]['targetWords'] );

        // Total should be sum of all sections
        $this->assertEquals( 575, $result['total_words'] );
    }

    /**
     * Test estimated time calculation
     */
    public function test_estimated_time_calculation() {
        $blueprint = array(
            'schema' => array(
                'sections' => array(
                    array( 'type' => 'content', 'word_target' => 500 ),
                ),
            ),
        );

        $params = array( 'brief' => 'Test time estimation' );
        $result = $this->stub_service->generate_outline( $params, $blueprint );

        // 500 words / 50 words per minute = 10 minutes
        $this->assertEquals( 10, $result['estimated_time'] );

        // Test minimum time
        $small_blueprint = array(
            'schema' => array(
                'sections' => array(
                    array( 'type' => 'content', 'word_target' => 50 ),
                ),
            ),
        );

        $small_result = $this->stub_service->generate_outline( $params, $small_blueprint );
        $this->assertEquals( 5, $small_result['estimated_time'] ); // Minimum 5 minutes
    }
}