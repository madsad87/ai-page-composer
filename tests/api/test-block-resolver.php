<?php
/**
 * Test Block Resolver Class
 *
 * @package AIPageComposer\Tests
 */

namespace AIPageComposer\Tests;

use PHPUnit\Framework\TestCase;
use AIPageComposer\API\Block_Resolver;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Test Block Resolver
 */
class Test_Block_Resolver extends TestCase {

    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Mock WordPress functions
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('get_option')->justReturn([]);
        Functions\when('get_plugins')->justReturn([]);
        Functions\when('get_bloginfo')->justReturn('6.0');
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
        $resolver = new Block_Resolver();
        $this->assertInstanceOf(Block_Resolver::class, $resolver);
    }

    /**
     * Test resolve block type with preferences
     */
    public function test_resolve_block_type() {
        $resolver = new Block_Resolver();
        
        $preferences = [
            'preferred_plugin' => 'kadence_blocks',
            'section_type' => 'hero',
            'fallback_blocks' => ['core/cover'],
            'custom_attributes' => []
        ];

        $result = $resolver->resolve_block_type($preferences);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('block_name', $result);
        $this->assertArrayHasKey('plugin', $result);
        $this->assertArrayHasKey('section_type', $result);
        $this->assertArrayHasKey('fallback_used', $result);
        
        $this->assertEquals('hero', $result['section_type']);
        $this->assertIsBool($result['fallback_used']);
    }

    /**
     * Test calculate priority score
     */
    public function test_calculate_priority_score() {
        $resolver = new Block_Resolver();
        
        $plugin_data = [
            'active' => true,
            'supported_sections' => ['hero', 'content'],
            'priority' => 8
        ];

        $reflection = new \ReflectionClass($resolver);
        $method = $reflection->getMethod('calculate_priority_score');
        $method->setAccessible(true);
        
        // Test preferred plugin bonus
        $score = $method->invoke($resolver, 'kadence_blocks', 'kadence_blocks', $plugin_data, 'hero');
        $this->assertGreaterThan(100, $score); // Should get user preference bonus
        
        // Test non-preferred plugin
        $score = $method->invoke($resolver, 'kadence_blocks', 'genesis_blocks', $plugin_data, 'hero');
        $this->assertLessThan(100, $score); // Should not get user preference bonus
    }

    /**
     * Test guess plugin from block name
     */
    public function test_guess_plugin_from_block_name() {
        $resolver = new Block_Resolver();
        
        $reflection = new \ReflectionClass($resolver);
        $method = $reflection->getMethod('guess_plugin_from_block_name');
        $method->setAccessible(true);
        
        $this->assertEquals('kadence_blocks', $method->invoke($resolver, 'kadence/rowlayout'));
        $this->assertEquals('genesis_blocks', $method->invoke($resolver, 'genesis-blocks/gb-container'));
        $this->assertEquals('core', $method->invoke($resolver, 'core/group'));
        $this->assertEquals('unknown', $method->invoke($resolver, 'unknown/block'));
    }

    /**
     * Test block supports inner blocks
     */
    public function test_block_supports_inner_blocks() {
        $resolver = new Block_Resolver();
        
        $reflection = new \ReflectionClass($resolver);
        $method = $reflection->getMethod('block_supports_inner_blocks');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($resolver, 'kadence/rowlayout'));
        $this->assertTrue($method->invoke($resolver, 'core/group'));
        $this->assertTrue($method->invoke($resolver, 'core/columns'));
        $this->assertFalse($method->invoke($resolver, 'core/paragraph'));
        $this->assertFalse($method->invoke($resolver, 'kadence/testimonials'));
    }

    /**
     * Test is container block
     */
    public function test_is_container_block() {
        $resolver = new Block_Resolver();
        
        $reflection = new \ReflectionClass($resolver);
        $method = $reflection->getMethod('is_container_block');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($resolver, 'kadence/rowlayout'));
        $this->assertTrue($method->invoke($resolver, 'core/group'));
        $this->assertTrue($method->invoke($resolver, 'core/cover'));
        $this->assertFalse($method->invoke($resolver, 'core/paragraph'));
        $this->assertFalse($method->invoke($resolver, 'core/image'));
    }

    /**
     * Test get available blocks for section
     */
    public function test_get_available_blocks_for_section() {
        $resolver = new Block_Resolver();
        
        // Mock block preferences to return test data
        $reflection = new \ReflectionClass($resolver);
        $property = $reflection->getProperty('block_preferences');
        $property->setAccessible(true);
        
        $mock_block_preferences = $this->createMock(\AIPageComposer\Admin\Block_Preferences::class);
        $mock_block_preferences->method('get_detected_plugins')->willReturn([
            'kadence_blocks' => [
                'active' => true,
                'name' => 'Kadence Blocks',
                'priority' => 8
            ],
            'core' => [
                'active' => true,
                'name' => 'WordPress Core Blocks',
                'priority' => 5
            ]
        ]);
        
        $property->setValue($resolver, $mock_block_preferences);
        
        // Mock WordPress registry
        Functions\when('\\WP_Block_Type_Registry::get_instance')->justReturn(
            (object) ['get_all_registered' => function() {
                return [
                    'kadence/rowlayout' => true,
                    'core/cover' => true
                ];
            }]
        );
        
        $blocks = $resolver->get_available_blocks_for_section('hero');
        
        $this->assertIsArray($blocks);
        // This would contain available blocks for hero section in a real scenario
    }

    /**
     * Test build block specification
     */
    public function test_build_block_specification() {
        $resolver = new Block_Resolver();
        
        $resolved_block = [
            'plugin_key' => 'kadence_blocks',
            'block_name' => 'kadence/rowlayout',
            'plugin_data' => [
                'name' => 'Kadence Blocks',
                'namespace' => 'kadence'
            ],
            'fallback_used' => false
        ];

        $reflection = new \ReflectionClass($resolver);
        $method = $reflection->getMethod('build_block_specification');
        $method->setAccessible(true);
        
        $result = $method->invoke($resolver, $resolved_block, 'hero', []);
        
        $this->assertIsArray($result);
        $this->assertEquals('kadence/rowlayout', $result['block_name']);
        $this->assertEquals('Kadence Blocks', $result['plugin']);
        $this->assertEquals('kadence_blocks', $result['plugin_key']);
        $this->assertEquals('kadence', $result['namespace']);
        $this->assertEquals('hero', $result['section_type']);
        $this->assertFalse($result['fallback_used']);
        $this->assertIsArray($result['attributes']);
    }
}