<?php
/**
 * Test Cache Manager Class
 *
 * @package AIPageComposer\Tests
 */

namespace AIPageComposer\Tests;

use PHPUnit\Framework\TestCase;
use AIPageComposer\API\Cache_Manager;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Test Cache Manager
 */
class Test_Cache_Manager extends TestCase {

    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Mock WordPress functions
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('wp_cache_delete')->justReturn(true);
        Functions\when('wp_cache_flush_group')->justReturn(true);
        Functions\when('get_option')->justReturn([]);
        Functions\when('absint')->returnArg();
        Functions\when('current_time')->justReturn('2024-01-01 12:00:00');
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
        $manager = new Cache_Manager();
        $this->assertInstanceOf(Cache_Manager::class, $manager);
    }

    /**
     * Test cache key sanitization
     */
    public function test_sanitize_cache_key() {
        $manager = new Cache_Manager();
        
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('sanitize_cache_key');
        $method->setAccessible(true);
        
        // Test removing invalid characters
        $result = $method->invoke($manager, 'test key with spaces!@#');
        $this->assertEquals('test_key_with_spaces___', $result);
        
        // Test length limitation
        $long_key = str_repeat('a', 200);
        $result = $method->invoke($manager, $long_key);
        $this->assertEquals(172, strlen($result));
    }

    /**
     * Test cache data compression
     */
    public function test_compress_cache_data() {
        $manager = new Cache_Manager();
        
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('compress_cache_data');
        $method->setAccessible(true);
        
        $data = ['test' => 'data', 'array' => [1, 2, 3]];
        $compressed = $method->invoke($manager, $data);
        
        $this->assertIsString($compressed);
        
        // Test decompression
        $decompress_method = $reflection->getMethod('decompress_cache_data');
        $decompress_method->setAccessible(true);
        
        $decompressed = $decompress_method->invoke($manager, $compressed);
        $this->assertEquals($data, $decompressed);
    }

    /**
     * Test get data size
     */
    public function test_get_data_size() {
        $manager = new Cache_Manager();
        
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('get_data_size');
        $method->setAccessible(true);
        
        $data = 'test string';
        $size = $method->invoke($manager, $data);
        $this->assertEquals(strlen($data), $size);
        
        $array_data = ['key' => 'value'];
        $size = $method->invoke($manager, $array_data);
        $this->assertGreaterThan(0, $size);
    }

    /**
     * Test is caching enabled
     */
    public function test_is_caching_enabled() {
        $manager = new Cache_Manager();
        
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('is_caching_enabled');
        $method->setAccessible(true);
        
        // Mock settings manager to return cache enabled
        $settings_property = $reflection->getProperty('settings_manager');
        $settings_property->setAccessible(true);
        
        $mock_settings_manager = $this->createMock(\AIPageComposer\Admin\Settings_Manager::class);
        $mock_settings_manager->method('get_all_settings')->willReturn([
            'cache_settings' => ['enable_section_cache' => true]
        ]);
        
        $settings_property->setValue($manager, $mock_settings_manager);
        
        $this->assertTrue($method->invoke($manager));
    }

    /**
     * Test generate section cache key
     */
    public function test_generate_section_cache_key() {
        $manager = new Cache_Manager();
        
        $params = [
            'sectionId' => 'test-section',
            'content_brief' => 'Test content',
            'mode' => 'hybrid',
            'alpha' => 0.7,
            'block_preferences' => [],
            'image_requirements' => []
        ];

        $cache_key = $manager->generate_section_cache_key($params);
        
        $this->assertIsString($cache_key);
        $this->assertStringStartsWith('section_', $cache_key);
        $this->assertEquals(72, strlen($cache_key)); // section_ + 64 char hash
    }

    /**
     * Test cache statistics
     */
    public function test_get_statistics() {
        $manager = new Cache_Manager();
        
        // Simulate some cache operations
        $reflection = new \ReflectionClass($manager);
        $stats_property = $reflection->getProperty('stats');
        $stats_property->setAccessible(true);
        
        $stats_property->setValue($manager, [
            'hits' => 5,
            'misses' => 3,
            'sets' => 8,
            'deletes' => 1
        ]);

        $statistics = $manager->get_statistics();
        
        $this->assertIsArray($statistics);
        $this->assertEquals(5, $statistics['hits']);
        $this->assertEquals(3, $statistics['misses']);
        $this->assertEquals(8, $statistics['total_requests']);
        $this->assertEquals(62.5, $statistics['hit_rate']); // 5/8 * 100
    }

    /**
     * Test cache health
     */
    public function test_get_cache_health() {
        $manager = new Cache_Manager();
        
        // Mock database methods
        $reflection = new \ReflectionClass($manager);
        
        $get_cache_size_method = $reflection->getMethod('get_cache_size');
        $get_cache_size_method->setAccessible(true);
        
        $get_cache_entry_count_method = $reflection->getMethod('get_cache_entry_count');
        $get_cache_entry_count_method->setAccessible(true);
        
        // Mock global $wpdb
        global $wpdb;
        $wpdb = $this->createMock('stdClass');
        $wpdb->method('get_var')->willReturn('1048576'); // 1MB
        $wpdb->prefix = 'wp_';
        
        $health = $manager->get_cache_health();
        
        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('hit_rate', $health);
        $this->assertArrayHasKey('total_entries', $health);
        $this->assertArrayHasKey('memory_usage', $health);
        $this->assertArrayHasKey('recommendations', $health);
    }

    /**
     * Test cache recommendations
     */
    public function test_get_cache_recommendations() {
        $manager = new Cache_Manager();
        
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('get_cache_recommendations');
        $method->setAccessible(true);
        
        // Test low hit rate recommendation
        $stats = [
            'hit_rate' => 30,
            'total_requests' => 20,
            'cache_entries' => 100,
            'cache_size' => 10 * 1048576 // 10MB
        ];
        
        $recommendations = $method->invoke($manager, $stats);
        
        $this->assertIsArray($recommendations);
        $this->assertContains('Consider increasing cache TTL to improve hit rate', $recommendations);
        
        // Test high cache entries recommendation
        $stats['cache_entries'] = 2000;
        $recommendations = $method->invoke($manager, $stats);
        $this->assertContains('Large number of cache entries - consider cleanup', $recommendations);
        
        // Test large cache size recommendation
        $stats['cache_size'] = 60 * 1048576; // 60MB
        $recommendations = $method->invoke($manager, $stats);
        $this->assertContains('Cache size is large - consider reducing TTL or entry limits', $recommendations);
    }
}