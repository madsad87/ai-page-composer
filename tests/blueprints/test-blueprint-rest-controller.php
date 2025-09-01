<?php
/**
 * Blueprint REST Controller Unit Tests
 *
 * @package AIPageComposer\Tests
 */

namespace AIPageComposer\Tests\Blueprints;

use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;
use AIPageComposer\Blueprints\Blueprint_REST_Controller;
use AIPageComposer\Blueprints\Blueprint_Manager;
use AIPageComposer\Tests\Utilities\Test_Blueprint_Factory;

/**
 * Blueprint REST Controller test class
 */
class Test_Blueprint_REST_Controller extends WP_UnitTestCase {

    /**
     * REST controller instance
     *
     * @var Blueprint_REST_Controller
     */
    private $controller;

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
     * Admin user ID
     *
     * @var int
     */
    private $admin_user_id;

    /**
     * Set up test case
     */
    public function setUp(): void {
        parent::setUp();
        
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        $this->server = $wp_rest_server;
        
        $this->controller = new Blueprint_REST_Controller();
        $this->blueprint_manager = new Blueprint_Manager();
        $this->controller->set_blueprint_manager( $this->blueprint_manager );
        $this->blueprint_factory = new Test_Blueprint_Factory();
        
        // Create admin user for permission tests
        $this->admin_user_id = $this->factory->user->create( array(
            'role' => 'administrator'
        ) );
        
        // Register routes
        $this->controller->register_routes();
        
        do_action( 'rest_api_init' );
    }

    /**
     * Test route registration
     */
    public function test_route_registration() {
        $routes = $this->server->get_routes();
        
        $this->assertArrayHasKey( '/ai-composer/v1/blueprints', $routes, 'Blueprints collection route should be registered' );
        $this->assertArrayHasKey( '/ai-composer/v1/blueprints/(?P<id>[\d]+)', $routes, 'Individual blueprint route should be registered' );
        $this->assertArrayHasKey( '/ai-composer/v1/validate-schema', $routes, 'Schema validation route should be registered' );
        $this->assertArrayHasKey( '/ai-composer/v1/blueprint-preview', $routes, 'Blueprint preview route should be registered' );
        $this->assertArrayHasKey( '/ai-composer/v1/detected-plugins', $routes, 'Detected plugins route should be registered' );
    }

    /**
     * Test getting blueprints collection
     */
    public function test_get_blueprints_collection() {
        wp_set_current_user( $this->admin_user_id );
        
        // Create test blueprints
        $blueprint1_id = $this->blueprint_factory->create_blueprint( array(
            'post_title' => 'Test Blueprint 1'
        ) );
        $blueprint2_id = $this->blueprint_factory->create_blueprint( array(
            'post_title' => 'Test Blueprint 2'
        ) );

        $request = new \WP_REST_Request( 'GET', '/ai-composer/v1/blueprints' );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status(), 'Should return 200 status' );
        
        $data = $response->get_data();
        $this->assertIsArray( $data, 'Response should be an array' );
        $this->assertGreaterThanOrEqual( 2, count( $data ), 'Should return at least 2 blueprints' );
        
        // Check blueprint structure
        $blueprint = $data[0];
        $this->assertArrayHasKey( 'id', $blueprint, 'Blueprint should have ID' );
        $this->assertArrayHasKey( 'title', $blueprint, 'Blueprint should have title' );
        $this->assertArrayHasKey( 'status', $blueprint, 'Blueprint should have status' );
        $this->assertArrayHasKey( 'blueprint_data', $blueprint, 'Blueprint should have data' );
        $this->assertArrayHasKey( 'sections_count', $blueprint, 'Blueprint should have sections count' );
        $this->assertArrayHasKey( 'category', $blueprint, 'Blueprint should have category' );
        $this->assertArrayHasKey( 'valid', $blueprint, 'Blueprint should have valid flag' );
    }

    /**
     * Test getting individual blueprint
     */
    public function test_get_individual_blueprint() {
        wp_set_current_user( $this->admin_user_id );
        
        $blueprint_id = $this->blueprint_factory->create_blueprint( array(
            'post_title' => 'Individual Test Blueprint'
        ) );

        $request = new \WP_REST_Request( 'GET', '/ai-composer/v1/blueprints/' . $blueprint_id );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status(), 'Should return 200 status' );
        
        $data = $response->get_data();
        $this->assertIsArray( $data, 'Response should be an array' );
        $this->assertEquals( $blueprint_id, $data['id'], 'Should return correct blueprint ID' );
        $this->assertEquals( 'Individual Test Blueprint', $data['title'], 'Should return correct title' );
    }

    /**
     * Test getting non-existent blueprint
     */
    public function test_get_nonexistent_blueprint() {
        wp_set_current_user( $this->admin_user_id );
        
        $request = new \WP_REST_Request( 'GET', '/ai-composer/v1/blueprints/99999' );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 404, $response->get_status(), 'Should return 404 for non-existent blueprint' );
        
        $data = $response->get_data();
        $this->assertEquals( 'rest_blueprint_invalid_id', $data['code'], 'Should return correct error code' );
    }

    /**
     * Test schema validation endpoint
     */
    public function test_schema_validation_endpoint() {
        wp_set_current_user( $this->admin_user_id );
        
        $valid_schema = $this->blueprint_factory->get_default_blueprint_schema();
        
        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/validate-schema' );
        $request->set_body( wp_json_encode( $valid_schema ) );
        $request->set_header( 'content-type', 'application/json' );
        
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status(), 'Should return 200 status' );
        
        $data = $response->get_data();
        $this->assertArrayHasKey( 'valid', $data, 'Response should have valid flag' );
        $this->assertArrayHasKey( 'errors', $data, 'Response should have errors array' );
        $this->assertArrayHasKey( 'schema_version', $data, 'Response should have schema version' );
        $this->assertArrayHasKey( 'validated_at', $data, 'Response should have validation timestamp' );
        
        $this->assertTrue( $data['valid'], 'Valid schema should pass validation' );
        $this->assertEmpty( $data['errors'], 'Valid schema should have no errors' );
    }

    /**
     * Test schema validation with invalid data
     */
    public function test_schema_validation_invalid_data() {
        wp_set_current_user( $this->admin_user_id );
        
        $invalid_schema = $this->blueprint_factory->get_invalid_blueprint_schema();
        
        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/validate-schema' );
        $request->set_body( wp_json_encode( $invalid_schema ) );
        $request->set_header( 'content-type', 'application/json' );
        
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status(), 'Should return 200 status' );
        
        $data = $response->get_data();
        $this->assertFalse( $data['valid'], 'Invalid schema should fail validation' );
        $this->assertNotEmpty( $data['errors'], 'Invalid schema should have errors' );
    }

    /**
     * Test schema validation with malformed JSON
     */
    public function test_schema_validation_malformed_json() {
        wp_set_current_user( $this->admin_user_id );
        
        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/validate-schema' );
        $request->set_body( 'invalid json {' );
        $request->set_header( 'content-type', 'application/json' );
        
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 400, $response->get_status(), 'Should return 400 for malformed JSON' );
        
        $data = $response->get_data();
        $this->assertEquals( 'rest_invalid_json', $data['code'], 'Should return correct error code' );
    }

    /**
     * Test blueprint preview generation
     */
    public function test_blueprint_preview_generation() {
        wp_set_current_user( $this->admin_user_id );
        
        $blueprint_data = $this->blueprint_factory->get_default_blueprint_schema();
        
        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/blueprint-preview' );
        $request->set_body( wp_json_encode( $blueprint_data ) );
        $request->set_header( 'content-type', 'application/json' );
        
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status(), 'Should return 200 status' );
        
        $data = $response->get_data();
        $this->assertArrayHasKey( 'preview', $data, 'Response should have preview data' );
        $this->assertArrayHasKey( 'generated_at', $data, 'Response should have generation timestamp' );
        
        $preview = $data['preview'];
        $this->assertArrayHasKey( 'sections', $preview, 'Preview should have sections' );
        $this->assertArrayHasKey( 'estimated_tokens', $preview, 'Preview should have token estimate' );
        $this->assertArrayHasKey( 'estimated_cost', $preview, 'Preview should have cost estimate' );
        
        $this->assertIsArray( $preview['sections'], 'Preview sections should be array' );
        $this->assertIsInt( $preview['estimated_tokens'], 'Token estimate should be integer' );
        $this->assertIsFloat( $preview['estimated_cost'], 'Cost estimate should be float' );
        $this->assertGreaterThan( 0, $preview['estimated_tokens'], 'Token estimate should be positive' );
        $this->assertGreaterThanOrEqual( 0, $preview['estimated_cost'], 'Cost estimate should be non-negative' );
    }

    /**
     * Test detected plugins endpoint
     */
    public function test_detected_plugins_endpoint() {
        wp_set_current_user( $this->admin_user_id );
        
        $request = new \WP_REST_Request( 'GET', '/ai-composer/v1/detected-plugins' );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status(), 'Should return 200 status' );
        
        $data = $response->get_data();
        $this->assertIsArray( $data, 'Response should be an array' );
        $this->assertArrayHasKey( 'core', $data, 'Should include WordPress core' );
        
        // Check plugin structure
        $core_plugin = $data['core'];
        $this->assertArrayHasKey( 'name', $core_plugin, 'Plugin should have name' );
        $this->assertArrayHasKey( 'active', $core_plugin, 'Plugin should have active status' );
        $this->assertArrayHasKey( 'slug', $core_plugin, 'Plugin should have slug' );
        
        $this->assertTrue( $core_plugin['active'], 'WordPress core should always be active' );
        $this->assertEquals( 'WordPress Core', $core_plugin['name'] );
    }

    /**
     * Test permission checks for non-admin users
     */
    public function test_permission_checks_non_admin() {
        $subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $subscriber_id );
        
        // Test blueprints collection
        $request = new \WP_REST_Request( 'GET', '/ai-composer/v1/blueprints' );
        $response = $this->server->dispatch( $request );
        $this->assertEquals( 403, $response->get_status(), 'Non-admin should not access blueprints' );
        
        // Test schema validation
        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/validate-schema' );
        $response = $this->server->dispatch( $request );
        $this->assertEquals( 403, $response->get_status(), 'Non-admin should not access validation' );
        
        // Test blueprint preview
        $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/blueprint-preview' );
        $response = $this->server->dispatch( $request );
        $this->assertEquals( 403, $response->get_status(), 'Non-admin should not access preview' );
    }

    /**
     * Test permission checks for unauthenticated users
     */
    public function test_permission_checks_unauthenticated() {
        wp_set_current_user( 0 ); // No user
        
        $request = new \WP_REST_Request( 'GET', '/ai-composer/v1/blueprints' );
        $response = $this->server->dispatch( $request );
        $this->assertEquals( 401, $response->get_status(), 'Unauthenticated user should get 401' );
    }
}