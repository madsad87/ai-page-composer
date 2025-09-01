<?php
/**
 * Schema Processor Unit Tests
 *
 * @package AIPageComposer\Tests
 */

namespace AIPageComposer\Tests\Blueprints;

use WP_UnitTestCase;
use AIPageComposer\Blueprints\Schema_Processor;
use AIPageComposer\Tests\Utilities\Test_Blueprint_Factory;

/**
 * Schema Processor test class
 */
class Test_Schema_Processor extends WP_UnitTestCase {

    /**
     * Schema processor instance
     *
     * @var Schema_Processor
     */
    private $schema_processor;

    /**
     * Blueprint factory instance
     *
     * @var Test_Blueprint_Factory
     */
    private $blueprint_factory;

    /**
     * Set up test case
     */
    public function setUp(): void {
        parent::setUp();
        $this->schema_processor = new Schema_Processor();
        $this->blueprint_factory = new Test_Blueprint_Factory();
    }

    /**
     * Test schema validation with valid data
     */
    public function test_validate_schema_with_valid_data() {
        $valid_schema = $this->blueprint_factory->get_default_blueprint_schema();
        $result = $this->schema_processor->validate_schema( $valid_schema );

        $this->assertTrue( $result['valid'], 'Valid schema should pass validation' );
        $this->assertEmpty( $result['errors'], 'Valid schema should have no errors' );
        $this->assertIsArray( $result['errors'], 'Errors should be an array' );
    }

    /**
     * Test schema validation with invalid data
     */
    public function test_validate_schema_with_invalid_data() {
        $invalid_schema = $this->blueprint_factory->get_invalid_blueprint_schema();
        $result = $this->schema_processor->validate_schema( $invalid_schema );

        $this->assertFalse( $result['valid'], 'Invalid schema should fail validation' );
        $this->assertNotEmpty( $result['errors'], 'Invalid schema should have errors' );
        $this->assertIsArray( $result['errors'], 'Errors should be an array' );
        
        // Check that errors contain expected fields
        $error_properties = array_column( $result['errors'], 'property' );
        $this->assertContains( 'sections', $error_properties, 'Should have section-related errors' );
    }

    /**
     * Test applying defaults to blueprint data
     */
    public function test_apply_defaults() {
        $minimal_data = array(
            'sections' => array(
                array(
                    'id' => 'test-section',
                    'type' => 'content',
                    'heading' => 'Test Heading'
                )
            ),
            'global_settings' => array()
        );

        $result = $this->schema_processor->apply_defaults( $minimal_data );

        // Check global settings defaults
        $this->assertEquals( 'hybrid', $result['global_settings']['generation_mode'] );
        $this->assertEquals( 0.7, $result['global_settings']['hybrid_alpha'] );
        $this->assertEquals( array( 'content' ), $result['global_settings']['mvdb_namespaces'] );
        $this->assertEquals( 1000, $result['global_settings']['max_tokens_per_section'] );
        $this->assertTrue( $result['global_settings']['image_generation_enabled'] );
        $this->assertTrue( $result['global_settings']['seo_optimization'] );
        $this->assertTrue( $result['global_settings']['accessibility_checks'] );
        $this->assertEquals( 5.0, $result['global_settings']['cost_limit_usd'] );

        // Check section defaults
        $section = $result['sections'][0];
        $this->assertEquals( 2, $section['heading_level'] );
        $this->assertEquals( 150, $section['word_target'] );
        $this->assertEquals( 'optional', $section['media_policy'] );
        $this->assertEquals( 2, $section['internal_links'] );
        $this->assertTrue( $section['citations_required'] );
        $this->assertEquals( 'professional', $section['tone'] );
        $this->assertIsArray( $section['allowed_blocks'] );
        $this->assertIsArray( $section['block_preferences'] );

        // Check metadata defaults
        $this->assertEquals( '1.0.0', $result['metadata']['version'] );
        $this->assertEquals( 'custom', $result['metadata']['category'] );
        $this->assertEquals( 30, $result['metadata']['estimated_time_minutes'] );
        $this->assertEquals( 'intermediate', $result['metadata']['difficulty_level'] );
    }

    /**
     * Test data sanitization
     */
    public function test_sanitize_data() {
        $dirty_data = array(
            'sections' => array(
                array(
                    'id' => 'Test Section!@#',
                    'type' => '<script>alert("xss")</script>content',
                    'heading' => '<h1>Test Heading</h1>',
                    'heading_level' => '3.5',
                    'word_target' => '200.7',
                    'media_policy' => 'required<script>',
                    'internal_links' => '2.5',
                    'citations_required' => 'true',
                    'tone' => 'professional<script>'
                )
            ),
            'global_settings' => array(
                'generation_mode' => 'hybrid<script>',
                'hybrid_alpha' => '0.7',
                'max_tokens_per_section' => '1000.5',
                'image_generation_enabled' => 'true',
                'seo_optimization' => '1',
                'accessibility_checks' => 'false',
                'cost_limit_usd' => '5.99'
            ),
            'metadata' => array(
                'version' => '1.0.0<script>',
                'description' => '<p>Test description with <strong>HTML</strong></p>',
                'category' => 'custom<script>',
                'estimated_time_minutes' => '30.5',
                'difficulty_level' => 'intermediate<script>'
            )
        );

        $result = $this->schema_processor->sanitize_data( $dirty_data );

        // Check section sanitization
        $section = $result['sections'][0];
        $this->assertEquals( 'testsection', $section['id'] ); // sanitized as key
        $this->assertEquals( 'content', $section['type'] ); // XSS removed
        $this->assertEquals( 'Test Heading', $section['heading'] ); // HTML stripped
        $this->assertEquals( 3, $section['heading_level'] ); // converted to int
        $this->assertEquals( 200, $section['word_target'] ); // converted to int
        $this->assertEquals( 'required', $section['media_policy'] ); // XSS removed
        $this->assertEquals( 2, $section['internal_links'] ); // converted to int
        $this->assertTrue( $section['citations_required'] ); // converted to bool
        $this->assertEquals( 'professional', $section['tone'] ); // XSS removed

        // Check global settings sanitization
        $globals = $result['global_settings'];
        $this->assertEquals( 'hybrid', $globals['generation_mode'] );
        $this->assertEquals( 0.7, $globals['hybrid_alpha'] );
        $this->assertEquals( 1000, $globals['max_tokens_per_section'] );
        $this->assertTrue( $globals['image_generation_enabled'] );
        $this->assertTrue( $globals['seo_optimization'] );
        $this->assertFalse( $globals['accessibility_checks'] );
        $this->assertEquals( 5.99, $globals['cost_limit_usd'] );

        // Check metadata sanitization
        $metadata = $result['metadata'];
        $this->assertEquals( '1.0.0', $metadata['version'] );
        $this->assertEquals( 'Test description with HTML', $metadata['description'] ); // HTML stripped but content preserved
        $this->assertEquals( 'custom', $metadata['category'] );
        $this->assertEquals( 30, $metadata['estimated_time_minutes'] );
        $this->assertEquals( 'intermediate', $metadata['difficulty_level'] );
    }

    /**
     * Test individual section validation
     */
    public function test_validate_section() {
        // Valid section
        $valid_section = array(
            'id' => 'test-section',
            'type' => 'content',
            'heading' => 'Test Heading',
            'heading_level' => 2,
            'word_target' => 150,
            'media_policy' => 'optional',
            'internal_links' => 2,
            'citations_required' => true,
            'tone' => 'professional'
        );

        $result = $this->schema_processor->validate_section( $valid_section );
        $this->assertTrue( $result['valid'], 'Valid section should pass validation' );
        $this->assertEmpty( $result['errors'], 'Valid section should have no errors' );

        // Invalid section
        $invalid_section = array(
            'id' => '',
            'type' => 'invalid_type',
            'heading' => '',
            'word_target' => -1
        );

        $result = $this->schema_processor->validate_section( $invalid_section );
        $this->assertFalse( $result['valid'], 'Invalid section should fail validation' );
        $this->assertNotEmpty( $result['errors'], 'Invalid section should have errors' );
    }

    /**
     * Test getting schema definition
     */
    public function test_get_schema() {
        $schema = $this->schema_processor->get_schema();

        $this->assertIsArray( $schema, 'Schema should be an array' );
        $this->assertArrayHasKey( 'properties', $schema, 'Schema should have properties' );
        $this->assertArrayHasKey( 'definitions', $schema, 'Schema should have definitions' );
        $this->assertArrayHasKey( 'sections', $schema['properties'], 'Schema should define sections' );
        $this->assertArrayHasKey( 'global_settings', $schema['properties'], 'Schema should define global_settings' );
    }

    /**
     * Test getting section types
     */
    public function test_get_section_types() {
        $section_types = $this->schema_processor->get_section_types();

        $this->assertIsArray( $section_types, 'Section types should be an array' );
        $this->assertArrayHasKey( 'hero', $section_types, 'Should include hero section type' );
        $this->assertArrayHasKey( 'content', $section_types, 'Should include content section type' );
        $this->assertArrayHasKey( 'cta', $section_types, 'Should include CTA section type' );
        
        foreach ( $section_types as $key => $label ) {
            $this->assertIsString( $key, 'Section type key should be string' );
            $this->assertIsString( $label, 'Section type label should be string' );
            $this->assertNotEmpty( $label, 'Section type label should not be empty' );
        }
    }

    /**
     * Test getting tone options
     */
    public function test_get_tone_options() {
        $tone_options = $this->schema_processor->get_tone_options();

        $this->assertIsArray( $tone_options, 'Tone options should be an array' );
        $this->assertArrayHasKey( 'professional', $tone_options, 'Should include professional tone' );
        $this->assertArrayHasKey( 'casual', $tone_options, 'Should include casual tone' );
        $this->assertArrayHasKey( 'friendly', $tone_options, 'Should include friendly tone' );
        
        foreach ( $tone_options as $key => $label ) {
            $this->assertIsString( $key, 'Tone option key should be string' );
            $this->assertIsString( $label, 'Tone option label should be string' );
            $this->assertNotEmpty( $label, 'Tone option label should not be empty' );
        }
    }

    /**
     * Test getting generation modes
     */
    public function test_get_generation_modes() {
        $generation_modes = $this->schema_processor->get_generation_modes();

        $this->assertIsArray( $generation_modes, 'Generation modes should be an array' );
        $this->assertArrayHasKey( 'grounded', $generation_modes, 'Should include grounded mode' );
        $this->assertArrayHasKey( 'hybrid', $generation_modes, 'Should include hybrid mode' );
        $this->assertArrayHasKey( 'generative', $generation_modes, 'Should include generative mode' );
        
        foreach ( $generation_modes as $key => $label ) {
            $this->assertIsString( $key, 'Generation mode key should be string' );
            $this->assertIsString( $label, 'Generation mode label should be string' );
            $this->assertNotEmpty( $label, 'Generation mode label should not be empty' );
        }
    }
}