<?php
/**
 * Blueprint Manager Unit Tests
 *
 * @package AIPageComposer\Tests
 */

namespace AIPageComposer\Tests\Blueprints;

use WP_UnitTestCase;
use AIPageComposer\Blueprints\Blueprint_Manager;
use AIPageComposer\Blueprints\Schema_Processor;
use AIPageComposer\Tests\Utilities\Test_Blueprint_Factory;

/**
 * Blueprint Manager test class
 */
class Test_Blueprint_Manager extends WP_UnitTestCase {

    /**
     * Blueprint manager instance
     *
     * @var Blueprint_Manager
     */
    private $blueprint_manager;

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
        $this->blueprint_manager = new Blueprint_Manager();
        $this->blueprint_factory = new Test_Blueprint_Factory();
    }

    /**
     * Test post type registration
     */
    public function test_post_type_registration() {
        // Trigger post type registration
        do_action( 'init' );

        $this->assertTrue( post_type_exists( 'ai_blueprint' ), 'ai_blueprint post type should be registered' );

        $post_type_object = get_post_type_object( 'ai_blueprint' );
        $this->assertNotNull( $post_type_object, 'Post type object should exist' );
        $this->assertEquals( 'AI Blueprints', $post_type_object->labels->name );
        $this->assertFalse( $post_type_object->public, 'Post type should not be public' );
        $this->assertTrue( $post_type_object->show_ui, 'Post type should show UI' );
        $this->assertEquals( 'ai-composer', $post_type_object->show_in_menu );
    }

    /**
     * Test blueprint creation and retrieval
     */
    public function test_blueprint_creation_and_retrieval() {
        $blueprint_id = $this->blueprint_factory->create_blueprint( array(
            'post_title' => 'Test Blueprint Creation'
        ) );

        $this->assertIsInt( $blueprint_id, 'Blueprint creation should return integer ID' );
        $this->assertGreaterThan( 0, $blueprint_id, 'Blueprint ID should be positive' );

        // Test retrieval
        $blueprint_data = $this->blueprint_manager->get_blueprint( $blueprint_id );
        $this->assertIsArray( $blueprint_data, 'Blueprint data should be an array' );
        $this->assertArrayHasKey( 'sections', $blueprint_data, 'Blueprint should have sections' );
        $this->assertArrayHasKey( 'global_settings', $blueprint_data, 'Blueprint should have global settings' );
        $this->assertArrayHasKey( 'metadata', $blueprint_data, 'Blueprint should have metadata' );
    }

    /**
     * Test blueprint data validation during save
     */
    public function test_blueprint_validation_during_save() {
        $blueprint_id = $this->blueprint_factory->create_blueprint();

        // Simulate invalid data save
        $invalid_data = $this->blueprint_factory->get_invalid_blueprint_schema();
        update_post_meta( $blueprint_id, '_ai_blueprint_schema', $invalid_data );

        // Trigger save validation
        $_POST['ai_blueprint_meta_nonce_field'] = wp_create_nonce( 'ai_blueprint_meta_nonce' );
        $_POST['blueprint_schema_data'] = wp_json_encode( $invalid_data );

        $post = get_post( $blueprint_id );
        $this->blueprint_manager->save_blueprint_data( $blueprint_id, $post );

        // Check that validation errors were stored
        $validation_errors = get_post_meta( $blueprint_id, '_ai_blueprint_validation_errors', true );
        $this->assertNotEmpty( $validation_errors, 'Validation errors should be stored for invalid data' );
        $this->assertIsArray( $validation_errors, 'Validation errors should be an array' );
    }

    /**
     * Test blueprint duplication
     */
    public function test_blueprint_duplication() {
        $original_id = $this->blueprint_factory->create_blueprint( array(
            'post_title' => 'Original Blueprint'
        ) );

        $duplicate_id = $this->blueprint_manager->duplicate_blueprint( $original_id );

        $this->assertIsInt( $duplicate_id, 'Duplication should return integer ID' );
        $this->assertNotEquals( $original_id, $duplicate_id, 'Duplicate should have different ID' );

        // Check that data was copied
        $original_data = $this->blueprint_manager->get_blueprint( $original_id );
        $duplicate_data = $this->blueprint_manager->get_blueprint( $duplicate_id );

        $this->assertEquals( $original_data['sections'], $duplicate_data['sections'], 'Sections should be copied' );
        $this->assertEquals( $original_data['global_settings'], $duplicate_data['global_settings'], 'Global settings should be copied' );

        // Check that title was modified
        $duplicate_post = get_post( $duplicate_id );
        $this->assertStringContains( 'Copy', $duplicate_post->post_title, 'Duplicate title should contain "Copy"' );
        $this->assertEquals( 'draft', $duplicate_post->post_status, 'Duplicate should be draft status' );
    }

    /**
     * Test blueprint export
     */
    public function test_blueprint_export() {
        $blueprint_id = $this->blueprint_factory->create_blueprint( array(
            'post_title' => 'Export Test Blueprint'
        ) );

        $export_data = $this->blueprint_manager->export_blueprint( $blueprint_id );

        $this->assertIsString( $export_data, 'Export should return JSON string' );
        
        $decoded_data = json_decode( $export_data, true );
        $this->assertIsArray( $decoded_data, 'Export should be valid JSON' );
        $this->assertArrayHasKey( 'blueprint', $decoded_data, 'Export should contain blueprint data' );
        $this->assertArrayHasKey( 'title', $decoded_data, 'Export should contain title' );
        $this->assertArrayHasKey( 'exported_at', $decoded_data, 'Export should contain timestamp' );
        $this->assertArrayHasKey( 'plugin_version', $decoded_data, 'Export should contain version' );
        
        $this->assertEquals( 'Export Test Blueprint', $decoded_data['title'] );
    }

    /**
     * Test blueprint import
     */
    public function test_blueprint_import() {
        // Create export data
        $original_id = $this->blueprint_factory->create_blueprint( array(
            'post_title' => 'Import Test Blueprint'
        ) );
        $export_data = $this->blueprint_manager->export_blueprint( $original_id );

        // Import the data
        $imported_id = $this->blueprint_manager->import_blueprint( $export_data );

        $this->assertIsInt( $imported_id, 'Import should return integer ID' );
        $this->assertNotEquals( $original_id, $imported_id, 'Imported blueprint should have different ID' );

        // Check that data was imported correctly
        $original_data = $this->blueprint_manager->get_blueprint( $original_id );
        $imported_data = $this->blueprint_manager->get_blueprint( $imported_id );

        $this->assertEquals( $original_data['sections'], $imported_data['sections'], 'Imported sections should match' );
        $this->assertEquals( $original_data['global_settings'], $imported_data['global_settings'], 'Imported global settings should match' );

        // Check that imported post is draft
        $imported_post = get_post( $imported_id );
        $this->assertEquals( 'draft', $imported_post->post_status, 'Imported blueprint should be draft' );
    }

    /**
     * Test invalid blueprint import
     */
    public function test_invalid_blueprint_import() {
        // Test invalid JSON
        $result = $this->blueprint_manager->import_blueprint( 'invalid json' );
        $this->assertWPError( $result, 'Invalid JSON should return WP_Error' );
        $this->assertEquals( 'invalid_json', $result->get_error_code() );

        // Test missing blueprint data
        $invalid_export = wp_json_encode( array( 'title' => 'Test', 'exported_at' => current_time( 'mysql' ) ) );
        $result = $this->blueprint_manager->import_blueprint( $invalid_export );
        $this->assertWPError( $result, 'Missing blueprint data should return WP_Error' );
        $this->assertEquals( 'missing_blueprint', $result->get_error_code() );

        // Test invalid blueprint schema
        $invalid_blueprint_export = wp_json_encode( array(
            'title' => 'Invalid Blueprint',
            'blueprint' => $this->blueprint_factory->get_invalid_blueprint_schema()
        ) );
        $result = $this->blueprint_manager->import_blueprint( $invalid_blueprint_export );
        $this->assertWPError( $result, 'Invalid blueprint schema should return WP_Error' );
        $this->assertEquals( 'invalid_blueprint', $result->get_error_code() );
    }

    /**
     * Test getting blueprints with filters
     */
    public function test_get_blueprints_with_filters() {
        // Create test blueprints
        $landing_page_id = $this->blueprint_factory->create_blueprint( array(
            'post_title' => 'Landing Page Blueprint',
            'blueprint_schema' => array(
                'metadata' => array( 'category' => 'landing-page' )
            )
        ) );

        $blog_post_id = $this->blueprint_factory->create_blueprint( array(
            'post_title' => 'Blog Post Blueprint',
            'blueprint_schema' => array(
                'metadata' => array( 'category' => 'blog-post' )
            )
        ) );

        // Update meta cache
        update_post_meta( $landing_page_id, '_ai_blueprint_category', 'landing-page' );
        update_post_meta( $blog_post_id, '_ai_blueprint_category', 'blog-post' );

        // Test getting all blueprints
        $all_blueprints = $this->blueprint_manager->get_blueprints();
        $this->assertGreaterThanOrEqual( 2, count( $all_blueprints ), 'Should return at least 2 blueprints' );

        // Test filtering by category
        $landing_blueprints = $this->blueprint_manager->get_blueprints( array(
            'meta_query' => array(
                array(
                    'key' => '_ai_blueprint_category',
                    'value' => 'landing-page',
                    'compare' => '='
                )
            )
        ) );
        
        $this->assertEquals( 1, count( $landing_blueprints ), 'Should return 1 landing page blueprint' );
        $this->assertEquals( $landing_page_id, $landing_blueprints[0]->ID );
    }

    /**
     * Test schema processor access
     */
    public function test_schema_processor_access() {
        $schema_processor = $this->blueprint_manager->get_schema_processor();
        $this->assertInstanceOf( Schema_Processor::class, $schema_processor, 'Should return Schema_Processor instance' );
    }

    /**
     * Test custom columns functionality
     */
    public function test_custom_columns() {
        $columns = array( 'title' => 'Title', 'date' => 'Date' );
        $updated_columns = $this->blueprint_manager->add_custom_columns( $columns );

        $this->assertArrayHasKey( 'blueprint_category', $updated_columns, 'Should add category column' );
        $this->assertArrayHasKey( 'sections_count', $updated_columns, 'Should add sections count column' );
        $this->assertArrayHasKey( 'generation_mode', $updated_columns, 'Should add generation mode column' );
        $this->assertArrayHasKey( 'difficulty', $updated_columns, 'Should add difficulty column' );
        $this->assertArrayHasKey( 'status', $updated_columns, 'Should add status column' );
        $this->assertArrayHasKey( 'date', $updated_columns, 'Should preserve date column' );
    }

    /**
     * Test sortable columns
     */
    public function test_sortable_columns() {
        $columns = array();
        $sortable_columns = $this->blueprint_manager->sortable_columns( $columns );

        $this->assertArrayHasKey( 'blueprint_category', $sortable_columns, 'Category should be sortable' );
        $this->assertArrayHasKey( 'sections_count', $sortable_columns, 'Sections count should be sortable' );
        $this->assertArrayHasKey( 'generation_mode', $sortable_columns, 'Generation mode should be sortable' );
        $this->assertArrayHasKey( 'difficulty', $sortable_columns, 'Difficulty should be sortable' );
    }
}