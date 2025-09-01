<?php
/**
 * Unit Tests for Outline Controller
 * 
 * @package AIPageComposer
 */

use AIPageComposer\API\Outline_Controller;
use AIPageComposer\Blueprints\Blueprint_Manager;
use AIPageComposer\Admin\Block_Preferences;

/**
 * Outline Controller test case
 */
class Test_Outline_Controller extends WP_UnitTestCase {

    /**
     * Outline controller instance
     *
     * @var Outline_Controller
     */
    private $outline_controller;

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
     * Test blueprint ID
     *
     * @var int
     */
    private $test_blueprint_id;

    /**
     * Admin user ID
     *
     * @var int
     */
    private $admin_user_id;

    /**
     * Set up test fixtures
     */
    public function setUp(): void {
        parent::setUp();

        // Create admin user
        $this->admin_user_id = $this->factory->user->create( array(
            'role' => 'administrator',
        ) );

        // Initialize components
        $this->block_preferences = new Block_Preferences();
        $this->blueprint_manager = new Blueprint_Manager( $this->block_preferences );
        $this->outline_controller = new Outline_Controller( $this->blueprint_manager, $this->block_preferences );

        // Create test blueprint
        $this->test_blueprint_id = $this->create_test_blueprint();

        // Set up REST server
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        $this->outline_controller->register_routes();
        do_action( 'rest_api_init' );
    }

    /**
     * Test outline generation endpoint registration
     */
    public function test_outline_endpoint_registration() {
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey( '/ai-composer/v1/outline', $routes );
        
        $route = $routes['/ai-composer/v1/outline'];
        $this->assertNotEmpty( $route );
        $this->assertEquals( 'POST', $route[0]['methods']['POST'] );
    }

    /**
     * Test successful outline generation
     */
    public function test_outline_generation_success() {
        wp_set_current_user( $this->admin_user_id );

        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/outline' );
        $request->set_param( 'blueprint_id', $this->test_blueprint_id );
        $request->set_param( 'brief', 'Create content about sustainable gardening practices for beginners' );
        $request->set_param( 'audience', 'Beginning gardeners' );
        $request->set_param( 'tone', 'friendly' );

        $response = rest_get_server()->dispatch( $request );
        
        $this->assertEquals( 200, $response->get_status() );
        
        $data = $response->get_data();
        $this->assertArrayHasKey( 'sections', $data );
        $this->assertArrayHasKey( 'total_words', $data );
        $this->assertArrayHasKey( 'estimated_cost', $data );
        $this->assertArrayHasKey( 'mode', $data );
        $this->assertArrayHasKey( 'blueprint_id', $data );
        
        // Verify sections have block preferences
        foreach ( $data['sections'] as $section ) {
            $this->assertArrayHasKey( 'block_preference', $section );
            $this->assertArrayHasKey( 'preferred_plugin', $section['block_preference'] );
            $this->assertArrayHasKey( 'primary_block', $section['block_preference'] );
            $this->assertArrayHasKey( 'fallback_blocks', $section['block_preference'] );
        }
    }

    /**
     * Test outline generation with missing blueprint ID
     */
    public function test_outline_generation_missing_blueprint_id() {
        wp_set_current_user( $this->admin_user_id );

        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/outline' );
        $request->set_param( 'brief', 'Test brief content for outline generation' );

        $response = rest_get_server()->dispatch( $request );
        
        $this->assertEquals( 500, $response->get_status() );
        $this->assertInstanceOf( 'WP_Error', $response->as_error() );
    }

    /**
     * Test outline generation with missing brief
     */
    public function test_outline_generation_missing_brief() {
        wp_set_current_user( $this->admin_user_id );

        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/outline' );
        $request->set_param( 'blueprint_id', $this->test_blueprint_id );

        $response = rest_get_server()->dispatch( $request );
        
        $this->assertEquals( 500, $response->get_status() );
        $this->assertInstanceOf( 'WP_Error', $response->as_error() );
    }

    /**
     * Test outline generation with invalid blueprint ID
     */
    public function test_outline_generation_invalid_blueprint() {
        wp_set_current_user( $this->admin_user_id );

        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/outline' );
        $request->set_param( 'blueprint_id', 99999 );
        $request->set_param( 'brief', 'Test brief content for outline generation' );

        $response = rest_get_server()->dispatch( $request );
        
        $this->assertEquals( 500, $response->get_status() );
        $this->assertInstanceOf( 'WP_Error', $response->as_error() );
    }

    /**
     * Test permission check for non-authenticated user
     */
    public function test_outline_generation_unauthorized() {
        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/outline' );
        $request->set_param( 'blueprint_id', $this->test_blueprint_id );
        $request->set_param( 'brief', 'Test brief content for outline generation' );

        $response = rest_get_server()->dispatch( $request );
        
        $this->assertEquals( 401, $response->get_status() );
    }

    /**
     * Test parameter validation
     */
    public function test_parameter_validation() {
        wp_set_current_user( $this->admin_user_id );

        // Test brief too short
        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/outline' );
        $request->set_param( 'blueprint_id', $this->test_blueprint_id );
        $request->set_param( 'brief', 'Short' );

        $response = rest_get_server()->dispatch( $request );
        $this->assertEquals( 400, $response->get_status() );

        // Test invalid tone
        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/outline' );
        $request->set_param( 'blueprint_id', $this->test_blueprint_id );
        $request->set_param( 'brief', 'Valid brief content that is long enough for testing purposes' );
        $request->set_param( 'tone', 'invalid_tone' );

        $response = rest_get_server()->dispatch( $request );
        $this->assertEquals( 400, $response->get_status() );

        // Test invalid alpha value
        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/outline' );
        $request->set_param( 'blueprint_id', $this->test_blueprint_id );
        $request->set_param( 'brief', 'Valid brief content that is long enough for testing purposes' );
        $request->set_param( 'alpha', 1.5 );

        $response = rest_get_server()->dispatch( $request );
        
        // Alpha should be clamped to valid range, so this should succeed
        $this->assertEquals( 200, $response->get_status() );
        
        $data = $response->get_data();
        $this->assertLessThanOrEqual( 1.0, $data['alpha'] ?? 0.7 );
    }

    /**
     * Test MVDB parameters handling
     */
    public function test_mvdb_parameters() {
        wp_set_current_user( $this->admin_user_id );

        $mvdb_params = array(
            'namespaces' => array( 'content', 'products' ),
            'k' => 15,
            'min_score' => 0.7,
            'filters' => array( 'category' => 'gardening' ),
        );

        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/outline' );
        $request->set_param( 'blueprint_id', $this->test_blueprint_id );
        $request->set_param( 'brief', 'Create comprehensive guide for sustainable gardening practices' );
        $request->set_param( 'mvdb_params', $mvdb_params );

        $response = rest_get_server()->dispatch( $request );
        
        $this->assertEquals( 200, $response->get_status() );
        
        $data = $response->get_data();
        $this->assertArrayHasKey( 'sections', $data );
    }

    /**
     * Test block preferences integration
     */
    public function test_block_preferences_integration() {
        wp_set_current_user( $this->admin_user_id );

        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/outline' );
        $request->set_param( 'blueprint_id', $this->test_blueprint_id );
        $request->set_param( 'brief', 'Test brief for block preferences validation' );

        $response = rest_get_server()->dispatch( $request );
        
        $this->assertEquals( 200, $response->get_status() );
        
        $data = $response->get_data();
        
        // Verify each section has block preferences
        foreach ( $data['sections'] as $section ) {
            $this->assertArrayHasKey( 'block_preference', $section );
            
            $block_pref = $section['block_preference'];
            $this->assertArrayHasKey( 'preferred_plugin', $block_pref );
            $this->assertArrayHasKey( 'primary_block', $block_pref );
            $this->assertArrayHasKey( 'fallback_blocks', $block_pref );
            $this->assertArrayHasKey( 'pattern_preference', $block_pref );
            
            // Verify data types
            $this->assertIsString( $block_pref['preferred_plugin'] );
            $this->assertIsString( $block_pref['primary_block'] );
            $this->assertIsArray( $block_pref['fallback_blocks'] );
            $this->assertIsString( $block_pref['pattern_preference'] );
        }
    }

    /**
     * Create test blueprint for testing
     *
     * @return int Blueprint post ID.
     */
    private function create_test_blueprint() {
        $blueprint_post = $this->factory->post->create( array(
            'post_type' => 'ai_blueprint',
            'post_title' => 'Test Blueprint for Outline Generation',
            'post_status' => 'publish',
            'post_author' => $this->admin_user_id,
        ) );

        // Add blueprint schema meta
        $blueprint_schema = array(
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
                    'type' => 'testimonial',
                    'heading' => 'Success Stories',
                    'word_target' => 200,
                    'media_policy' => 'required',
                ),
            ),
            'global_settings' => array(
                'total_target_words' => 650,
                'audience' => 'general',
                'tone' => 'professional',
            ),
        );

        update_post_meta( $blueprint_post, '_ai_blueprint_schema', $blueprint_schema );

        return $blueprint_post;
    }

    /**
     * Clean up test fixtures
     */
    public function tearDown(): void {
        parent::tearDown();
        
        // Clean up test blueprint
        if ( $this->test_blueprint_id ) {
            wp_delete_post( $this->test_blueprint_id, true );
        }
    }
}