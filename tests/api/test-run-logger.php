<?php
/**
 * Test Run Logger
 *
 * @package AIPageComposer\Tests\Governance
 */

namespace AIPageComposer\Tests\Governance;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use AIPageComposer\API\Run_Logger;

/**
 * Test class for Run_Logger
 */
class Test_Run_Logger extends TestCase {

    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Mock WordPress functions
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('current_time')->justReturn('2024-12-15T14:30:22+00:00');
        Functions\when('get_bloginfo')->justReturn('6.4.2');
        Functions\when('wp_insert_post')->justReturn(123);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('update_post_meta')->justReturn(true);
        Functions\when('wp_update_post')->justReturn(123);
        Functions\when('get_posts')->justReturn([]);
        Functions\when('get_post_meta')->justReturn([]);
        
        // Mock constants
        if (!defined('AI_COMPOSER_VERSION')) {
            define('AI_COMPOSER_VERSION', '1.0.0');
        }
    }

    /**
     * Tear down test environment
     */
    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test start_run method
     */
    public function test_start_run() {
        $logger = new Run_Logger();
        
        $parameters = [
            'prompt' => 'Test prompt',
            'blueprint_id' => 456,
            'alpha_weight' => 0.7,
            'k_value' => 10,
            'min_score' => 0.5,
            'generation_mode' => 'hybrid'
        ];
        
        $run_id = $logger->start_run($parameters);
        
        // Assert run ID is generated
        $this->assertIsString($run_id);
        $this->assertStringStartsWith('run_', $run_id);
        $this->assertGreaterThan(20, strlen($run_id));
        
        // Assert current run ID is set
        $this->assertEquals($run_id, $logger->get_current_run_id());
    }

    /**
     * Test log_section_generation method
     */
    public function test_log_section_generation() {
        $logger = new Run_Logger();
        
        // Start a run first
        $parameters = ['prompt' => 'Test'];
        $run_id = $logger->start_run($parameters);
        
        $section_data = [
            'section_id' => 'hero-1',
            'section_type' => 'hero',
            'prompt' => 'Generate hero section',
            'chunk_ids' => ['chunk-1', 'chunk-2'],
            'tokens_consumed' => 450,
            'cost_usd' => 0.023,
            'processing_time_ms' => 2500,
            'block_type_used' => 'kadence/rowlayout',
            'fallback_applied' => false,
            'plugin_required' => 'kadence_blocks',
            'warnings' => ['Low MVDB recall: 0.3'],
            'citations' => [
                ['source_url' => 'https://site.com/page', 'confidence' => 0.89]
            ]
        ];
        
        // Should not throw any exceptions
        $logger->log_section_generation($section_data);
        
        // Verify the method completes successfully
        $this->assertTrue(true);
    }

    /**
     * Test log_plugin_usage method
     */
    public function test_log_plugin_usage() {
        $logger = new Run_Logger();
        
        // Start a run first
        $parameters = ['prompt' => 'Test'];
        $run_id = $logger->start_run($parameters);
        
        $plugin_data = [
            'plugin_name' => 'kadence_blocks',
            'block_type' => 'kadence/rowlayout',
            'version' => '3.2.1',
            'availability_status' => 'active'
        ];
        
        // Should not throw any exceptions
        $logger->log_plugin_usage($plugin_data);
        
        // Verify the method completes successfully
        $this->assertTrue(true);
    }

    /**
     * Test complete_run method
     */
    public function test_complete_run() {
        $logger = new Run_Logger();
        
        // Start a run first
        $parameters = ['prompt' => 'Test'];
        $run_id = $logger->start_run($parameters);
        
        $final_data = [
            'status' => 'completed',
            'post_id' => 789,
            'total_blocks' => 12,
            'total_word_count' => 1450,
            'featured_image_id' => 234,
            'seo_meta' => [
                'title' => 'Generated SEO Title',
                'description' => 'Generated meta description'
            ]
        ];
        
        // Should not throw any exceptions
        $logger->complete_run($final_data);
        
        // Verify current run ID is cleared
        $this->assertNull($logger->get_current_run_id());
    }

    /**
     * Test log_error method
     */
    public function test_log_error() {
        $logger = new Run_Logger();
        
        // Start a run first
        $parameters = ['prompt' => 'Test'];
        $run_id = $logger->start_run($parameters);
        
        $error_message = 'Test error occurred';
        $error_context = [
            'error_code' => 'test_error',
            'additional_info' => 'Test context'
        ];
        
        // Should not throw any exceptions
        $logger->log_error($error_message, $error_context);
        
        // Verify current run ID is cleared
        $this->assertNull($logger->get_current_run_id());
    }

    /**
     * Test get_run_data method with no data
     */
    public function test_get_run_data_no_data() {
        Functions\when('get_posts')->justReturn([]);
        
        $logger = new Run_Logger();
        $result = $logger->get_run_data('nonexistent_run_id');
        
        $this->assertNull($result);
    }

    /**
     * Test generate_run_id method creates unique IDs
     */
    public function test_generate_unique_run_ids() {
        $logger = new Run_Logger();
        
        $parameters = ['prompt' => 'Test'];
        
        $run_id_1 = $logger->start_run($parameters);
        $logger->complete_run();
        
        $run_id_2 = $logger->start_run($parameters);
        $logger->complete_run();
        
        // IDs should be different
        $this->assertNotEquals($run_id_1, $run_id_2);
        
        // Both should follow the expected pattern
        $this->assertStringStartsWith('run_', $run_id_1);
        $this->assertStringStartsWith('run_', $run_id_2);
    }

    /**
     * Test parameter sanitization
     */
    public function test_parameter_sanitization() {
        $logger = new Run_Logger();
        
        $parameters = [
            'prompt' => '<script>alert("xss")</script>Test prompt',
            'alpha_weight' => '0.7',
            'k_value' => '10',
            'min_score' => '0.5',
            'generation_mode' => 'hybrid<script>',
            'namespaces_versions' => [
                'content@v2.1' => true,
                'products@v1.8' => false
            ]
        ];
        
        // Should sanitize parameters and not throw exceptions
        $run_id = $logger->start_run($parameters);
        
        $this->assertIsString($run_id);
        $this->assertStringStartsWith('run_', $run_id);
    }

    /**
     * Test logging without starting a run
     */
    public function test_log_without_run() {
        $logger = new Run_Logger();
        
        // Should handle gracefully when no run is started
        $logger->log_section_generation(['section_id' => 'test']);
        $logger->log_plugin_usage(['plugin_name' => 'test']);
        $logger->complete_run();
        $logger->log_error('test error');
        
        // Should not throw exceptions
        $this->assertTrue(true);
    }
}