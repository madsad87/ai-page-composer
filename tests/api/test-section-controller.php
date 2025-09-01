<?php
/**
 * Test Section Controller Class
 *
 * @package AIPageComposer\Tests
 */

namespace AIPageComposer\Tests;

use PHPUnit\Framework\TestCase;
use AIPageComposer\API\Section_Controller;
use WP_REST_Request;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Test Section Controller
 */
class Test_Section_Controller extends TestCase {

    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Mock WordPress functions
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('sanitize_textarea_field')->returnArg();
        Functions\when('sanitize_key')->returnArg();
        Functions\when('rest_ensure_response')->returnArg();
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('current_time')->justReturn('2024-01-01 12:00:00');
        Functions\when('__')->returnArg();
    }

    /**
     * Tear down test
     */
    public function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test constructor
     */
    public function test_constructor() {
        $controller = new Section_Controller();
        $this->assertInstanceOf(Section_Controller::class, $controller);
    }

    /**
     * Test check permissions
     */
    public function test_check_permissions() {
        $controller = new Section_Controller();
        $this->assertTrue($controller->check_permissions());
    }

    /**
     * Test extract section parameters
     */
    public function test_extract_section_parameters() {
        $controller = new Section_Controller();
        
        // Mock WP_REST_Request
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_param')->willReturnMap([
            ['sectionId', 'test-section-1'],
            ['content_brief', 'Test content brief for section'],
            ['mode', 'hybrid'],
            ['alpha', 0.7],
            ['block_preferences', []],
            ['image_requirements', []],
            ['citation_settings', []]
        ]);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('extract_section_parameters');
        $method->setAccessible(true);
        
        $result = $method->invoke($controller, $request);
        
        $this->assertEquals('test-section-1', $result['sectionId']);
        $this->assertEquals('Test content brief for section', $result['content_brief']);
        $this->assertEquals('hybrid', $result['mode']);
        $this->assertEquals(0.7, $result['alpha']);
    }

    /**
     * Test generate cache key
     */
    public function test_generate_cache_key() {
        $controller = new Section_Controller();
        
        $params = [
            'sectionId' => 'test-section-1',
            'content_brief' => 'Test content',
            'mode' => 'hybrid',
            'alpha' => 0.7,
            'block_preferences' => []
        ];

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('generate_cache_key');
        $method->setAccessible(true);
        
        $result = $method->invoke($controller, $params);
        
        $this->assertStringStartsWith('ai_section_', $result);
        $this->assertEquals(75, strlen($result)); // ai_section_ + 64 char hash
    }

    /**
     * Test section endpoint arguments
     */
    public function test_get_section_args() {
        $controller = new Section_Controller();
        
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('get_section_args');
        $method->setAccessible(true);
        
        $args = $method->invoke($controller);
        
        $this->assertArrayHasKey('sectionId', $args);
        $this->assertArrayHasKey('content_brief', $args);
        $this->assertArrayHasKey('mode', $args);
        $this->assertArrayHasKey('alpha', $args);
        
        $this->assertTrue($args['sectionId']['required']);
        $this->assertTrue($args['content_brief']['required']);
        $this->assertFalse($args['mode']['required']);
        $this->assertEquals('hybrid', $args['mode']['default']);
    }

    /**
     * Test image endpoint arguments
     */
    public function test_get_image_args() {
        $controller = new Section_Controller();
        
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('get_image_args');
        $method->setAccessible(true);
        
        $args = $method->invoke($controller);
        
        $this->assertArrayHasKey('prompt', $args);
        $this->assertArrayHasKey('style', $args);
        $this->assertArrayHasKey('source', $args);
        
        $this->assertTrue($args['prompt']['required']);
        $this->assertFalse($args['style']['required']);
        $this->assertEquals('photographic', $args['style']['default']);
        $this->assertEquals('generate', $args['source']['default']);
    }

    /**
     * Test sanitize block preferences
     */
    public function test_sanitize_block_preferences() {
        $controller = new Section_Controller();
        
        $preferences = [
            'preferred_plugin' => 'kadence_blocks',
            'section_type' => 'hero',
            'fallback_blocks' => ['core/group', 'core/columns'],
            'custom_attributes' => ['color' => 'blue']
        ];

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitize_block_preferences');
        $method->setAccessible(true);
        
        $result = $method->invoke($controller, $preferences);
        
        $this->assertEquals('kadence_blocks', $result['preferred_plugin']);
        $this->assertEquals('hero', $result['section_type']);
        $this->assertIsArray($result['fallback_blocks']);
        $this->assertIsArray($result['custom_attributes']);
    }

    /**
     * Test sanitize image requirements
     */
    public function test_sanitize_image_requirements() {
        $controller = new Section_Controller();
        
        $requirements = [
            'policy' => 'required',
            'style' => 'photographic',
            'alt_text_required' => true,
            'license_compliance' => ['CC-BY', 'public-domain']
        ];

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitize_image_requirements');
        $method->setAccessible(true);
        
        $result = $method->invoke($controller, $requirements);
        
        $this->assertEquals('required', $result['policy']);
        $this->assertEquals('photographic', $result['style']);
        $this->assertTrue($result['alt_text_required']);
        $this->assertIsArray($result['license_compliance']);
    }
}