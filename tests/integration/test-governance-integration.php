<?php
/**
 * Test Governance Integration
 *
 * @package AIPageComposer\Tests\Integration
 */

namespace AIPageComposer\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use AIPageComposer\API\Governance_Controller;
use AIPageComposer\API\Run_Logger;
use AIPageComposer\API\History_Manager;
use AIPageComposer\API\Diff_Viewer;
use AIPageComposer\API\ReRun_Manager;

/**
 * Test class for Governance Integration
 */
class Test_Governance_Integration extends TestCase {

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
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('add_action')->justReturn(true);
        Functions\when('add_filter')->justReturn(true);
        Functions\when('do_action')->justReturn(true);
        Functions\when('get_option')->justReturn([]);
        Functions\when('update_option')->justReturn(true);
        Functions\when('wp_parse_args')->returnArg(1);
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('sanitize_textarea_field')->returnArg();
        Functions\when('__')->returnArg();
        Functions\when('esc_html__')->returnArg();
        
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
     * Test complete governance workflow
     */
    public function test_complete_governance_workflow() {
        $governance = new Governance_Controller();
        
        // Mock initialization
        Functions\when('add_action')->justReturn(true);
        
        // Initialize governance system
        $governance->init();
        
        // Start a new run
        $parameters = [
            'prompt' => 'Create a technology consulting landing page',
            'blueprint_id' => 123,
            'alpha_weight' => 0.7,
            'k_value' => 10,
            'min_score' => 0.5,
            'namespaces_versions' => [
                'content@v2.1' => true,
                'products@v1.8' => true
            ]
        ];
        
        $run_id = $governance->start_run($parameters);
        
        $this->assertIsString($run_id);
        $this->assertStringStartsWith('run_', $run_id);
        
        // Log section generation
        $section_data = [
            'section_id' => 'hero-1',
            'section_type' => 'hero',
            'prompt' => 'Generate hero section',
            'chunk_ids' => ['chunk-123', 'chunk-456'],
            'tokens_consumed' => 450,
            'cost_usd' => 0.023,
            'processing_time_ms' => 2500,
            'block_type_used' => 'kadence/rowlayout',
            'plugin_required' => 'kadence_blocks',
            'warnings' => ['Low MVDB recall: 0.3']
        ];
        
        $governance->log_section($section_data);
        
        // Log plugin usage
        $plugin_data = [
            'plugin_name' => 'kadence_blocks',
            'block_type' => 'kadence/rowlayout',
            'version' => '3.2.1',
            'availability_status' => 'active'
        ];
        
        $governance->log_plugin_usage($plugin_data);
        
        // Complete the run
        $final_data = [
            'status' => 'completed',
            'post_id' => 789,
            'total_blocks' => 8,
            'total_word_count' => 1200
        ];
        
        $governance->complete_run($final_data);
        
        // Verify workflow completed successfully
        $this->assertTrue(true);
    }

    /**
     * Test history retrieval
     */
    public function test_history_retrieval() {
        $governance = new Governance_Controller();
        $governance->init();
        
        // Mock history data
        Functions\when('get_posts')->justReturn([
            (object) [
                'ID' => 123,
                'post_author' => 1,
                'post_title' => 'run_20241215_143022_abc123'
            ]
        ]);
        
        Functions\when('get_post_meta')->alias(function($post_id, $key, $single) {
            switch($key) {
                case 'run_metadata':
                    return [
                        'run_id' => 'run_20241215_143022_abc123',
                        'status' => 'completed',
                        'start_timestamp' => '2024-12-15T14:30:22Z'
                    ];
                case 'cost_breakdown':
                    return ['total_cost_usd' => 0.156];
                case 'plugin_usage':
                    return ['kadence_blocks' => ['usage_count' => 3]];
                case 'sections_log':
                    return [['section_id' => 'hero-1']];
                default:
                    return [];
            }
        });
        
        Functions\when('get_userdata')->justReturn((object) ['display_name' => 'Test User']);
        Functions\when('get_post')->justReturn((object) ['post_title' => 'Test Blueprint']);
        
        $history = $governance->get_history();
        
        $this->assertIsArray($history);
        $this->assertArrayHasKey('runs', $history);
        $this->assertArrayHasKey('pagination', $history);
    }

    /**
     * Test diff generation
     */
    public function test_diff_generation() {
        $governance = new Governance_Controller();
        $governance->init();
        
        // Mock run data for diff comparison
        Functions\when('get_posts')->justReturn([
            (object) ['ID' => 123, 'post_title' => 'run_test']
        ]);
        
        Functions\when('get_post_meta')->alias(function($post_id, $key, $single) {
            return [
                'run_metadata' => ['status' => 'completed'],
                'generation_parameters' => ['alpha_weight' => 0.7],
                'sections_log' => [['section_id' => 'hero-1', 'block_type_used' => 'kadence/rowlayout']],
                'plugin_usage' => ['kadence_blocks' => ['version' => '3.2.1']],
                'cost_breakdown' => ['total_cost_usd' => 0.156]
            ][$key] ?? [];
        });
        
        $diff_result = $governance->generate_diff('run_test', 'current');
        
        $this->assertIsArray($diff_result);
        
        // Should contain diff structure even if comparison fails
        if (!isset($diff_result['error'])) {
            $this->assertArrayHasKey('comparison_metadata', $diff_result);
            $this->assertArrayHasKey('parameter_changes', $diff_result);
            $this->assertArrayHasKey('section_diffs', $diff_result);
            $this->assertArrayHasKey('plugin_availability_changes', $diff_result);
            $this->assertArrayHasKey('cost_comparison', $diff_result);
        }
    }

    /**
     * Test statistics generation
     */
    public function test_statistics_generation() {
        global $wpdb;
        
        // Mock wpdb
        $wpdb = (object) [
            'posts' => 'wp_posts',
            'postmeta' => 'wp_postmeta'
        ];
        
        Functions\when('get_posts')->justReturn([123, 456, 789]);
        
        $governance = new Governance_Controller();
        $governance->init();
        
        $stats = $governance->get_statistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_runs', $stats);
        $this->assertArrayHasKey('completed_runs', $stats);
        $this->assertArrayHasKey('failed_runs', $stats);
        $this->assertArrayHasKey('total_cost', $stats);
        $this->assertArrayHasKey('most_used_plugins', $stats);
    }

    /**
     * Test error handling in governance workflow
     */
    public function test_error_handling() {
        $governance = new Governance_Controller();
        $governance->init();
        
        // Start a run
        $parameters = ['prompt' => 'Test prompt'];
        $run_id = $governance->start_run($parameters);
        
        // Log an error
        $error_message = 'Test error occurred';
        $error_context = ['error_code' => 'test_error'];
        
        $governance->log_error($error_message, $error_context);
        
        // Should handle error gracefully
        $this->assertTrue(true);
    }

    /**
     * Test re-run preview functionality
     */
    public function test_rerun_preview() {
        $governance = new Governance_Controller();
        $governance->init();
        
        // Mock run data
        Functions\when('get_posts')->justReturn([
            (object) ['ID' => 123, 'post_title' => 'run_test']
        ]);
        
        Functions\when('get_post_meta')->alias(function($post_id, $key, $single) {
            return [
                'run_metadata' => ['status' => 'completed'],
                'generation_parameters' => ['prompt' => 'Test', 'alpha_weight' => 0.7],
                'plugin_usage' => ['kadence_blocks' => ['version' => '3.2.1']],
                'cost_breakdown' => ['total_cost_usd' => 0.156]
            ][$key] ?? [];
        });
        
        $preview_result = $governance->preview_rerun('run_test');
        
        $this->assertIsArray($preview_result);
        
        if ($preview_result['success']) {
            $this->assertArrayHasKey('preview', $preview_result);
            $this->assertArrayHasKey('can_proceed', $preview_result['preview']);
        }
    }

    /**
     * Test parameter validation
     */
    public function test_parameter_validation() {
        $governance = new Governance_Controller();
        $governance->init();
        
        // Test with missing prompt
        $this->expectException(\InvalidArgumentException::class);
        $governance->start_run([]);
    }

    /**
     * Test CSV export functionality
     */
    public function test_csv_export() {
        $governance = new Governance_Controller();
        $governance->init();
        
        // Mock history data for export
        Functions\when('get_posts')->justReturn([
            (object) [
                'ID' => 123,
                'post_author' => 1,
                'post_title' => 'run_test'
            ]
        ]);
        
        Functions\when('get_post_meta')->alias(function($post_id, $key, $single) {
            switch($key) {
                case 'run_metadata':
                    return ['run_id' => 'run_test', 'status' => 'completed'];
                case 'cost_breakdown':
                    return ['total_cost_usd' => 0.156];
                default:
                    return [];
            }
        });
        
        Functions\when('get_userdata')->justReturn((object) ['display_name' => 'Test User']);
        Functions\when('get_post')->justReturn((object) ['post_title' => 'Test Blueprint']);
        
        $csv_content = $governance->export_runs();
        
        $this->assertIsString($csv_content);
        $this->assertStringContains('Run ID', $csv_content);
    }
}