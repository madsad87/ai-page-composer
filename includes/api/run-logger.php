<?php
/**
 * Run Logger - Comprehensive audit logging for AI generation runs
 *
 * Logs all generation parameters, plugin choices, costs, and outcomes
 * for complete governance and auditability of AI Page Composer runs.
 *
 * @package AIPageComposer\API
 * @since 1.0.0
 */

namespace AIPageComposer\API;

use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;

/**
 * Run Logger Class
 * 
 * Handles comprehensive logging of AI generation runs including:
 * - Generation parameters and settings
 * - Plugin usage and availability
 * - Section-by-section generation details
 * - Cost tracking and token usage
 * - Quality metrics and warnings
 */
class Run_Logger {

    /**
     * Current run ID being tracked
     *
     * @var string
     */
    private string $current_run_id;

    /**
     * Run start timestamp
     *
     * @var float
     */
    private float $run_start_time;

    /**
     * Accumulated run data
     *
     * @var array
     */
    private array $run_data = [];

    /**
     * WordPress post ID for the run log
     *
     * @var int|null
     */
    private ?int $run_post_id = null;

    /**
     * Initialize new run logging session
     *
     * @param array $parameters Generation parameters
     * @return string Run ID
     */
    public function start_run(array $parameters): string {
        $this->current_run_id = $this->generate_run_id();
        $this->run_start_time = microtime(true);
        
        $this->run_data = [
            'run_metadata' => [
                'run_id' => $this->current_run_id,
                'user_id' => get_current_user_id(),
                'blueprint_id' => $parameters['blueprint_id'] ?? null,
                'start_timestamp' => current_time('c'),
                'status' => 'in_progress',
                'wordpress_version' => get_bloginfo('version'),
                'plugin_version' => defined('AI_COMPOSER_VERSION') ? AI_COMPOSER_VERSION : '1.0.0'
            ],
            'generation_parameters' => $this->sanitize_parameters($parameters),
            'sections_log' => [],
            'plugin_usage' => [],
            'cost_breakdown' => [
                'total_cost_usd' => 0,
                'openai_api_cost' => 0,
                'mvdb_query_cost' => 0,
                'token_breakdown' => [
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'total_tokens' => 0
                ]
            ],
            'quality_metrics' => [
                'overall_recall_score' => 0,
                'citation_coverage' => 0,
                'accessibility_score' => 0,
                'seo_optimization_score' => 0,
                'performance_impact' => 'unknown'
            ]
        ];

        // Create run post immediately for tracking
        $this->create_run_post();
        
        return $this->current_run_id;
    }

    /**
     * Log section generation details
     *
     * @param array $section_data Section generation data
     * @return void
     */
    public function log_section_generation(array $section_data): void {
        if (empty($this->current_run_id)) {
            return;
        }

        $section_log = [
            'section_id' => $section_data['section_id'] ?? uniqid('section_'),
            'section_type' => $section_data['section_type'] ?? 'unknown',
            'timestamp' => current_time('c'),
            'chunk_ids_used' => $section_data['chunk_ids'] ?? [],
            'prompt_hash' => hash('sha256', $section_data['prompt'] ?? ''),
            'tokens_consumed' => intval($section_data['tokens_consumed'] ?? 0),
            'cost_usd' => floatval($section_data['cost_usd'] ?? 0),
            'processing_time_ms' => intval($section_data['processing_time_ms'] ?? 0),
            'block_type_used' => $section_data['block_type_used'] ?? 'core/paragraph',
            'fallback_applied' => $section_data['fallback_applied'] ?? false,
            'plugin_required' => $section_data['plugin_required'] ?? 'core',
            'warnings' => $section_data['warnings'] ?? [],
            'citations' => $section_data['citations'] ?? []
        ];

        $this->run_data['sections_log'][] = $section_log;

        // Update plugin usage tracking
        $this->track_plugin_usage($section_data);

        // Update cost breakdown
        $this->update_cost_breakdown($section_data);

        // Save progress to database
        $this->save_run_progress();
    }

    /**
     * Log plugin usage for a section
     *
     * @param array $plugin_data Plugin usage data
     * @return void
     */
    public function log_plugin_usage(array $plugin_data): void {
        if (empty($this->current_run_id)) {
            return;
        }

        $plugin_name = $plugin_data['plugin_name'] ?? 'core';
        
        if (!isset($this->run_data['plugin_usage'][$plugin_name])) {
            $this->run_data['plugin_usage'][$plugin_name] = [
                'version' => $plugin_data['version'] ?? 'unknown',
                'blocks_used' => [],
                'availability_status' => $plugin_data['availability_status'] ?? 'unknown',
                'usage_count' => 0
            ];
        }

        $block_type = $plugin_data['block_type'] ?? '';
        if ($block_type && !in_array($block_type, $this->run_data['plugin_usage'][$plugin_name]['blocks_used'])) {
            $this->run_data['plugin_usage'][$plugin_name]['blocks_used'][] = $block_type;
        }

        $this->run_data['plugin_usage'][$plugin_name]['usage_count']++;
    }

    /**
     * Complete the run logging
     *
     * @param array $final_data Final run data
     * @return void
     */
    public function complete_run(array $final_data = []): void {
        if (empty($this->current_run_id)) {
            return;
        }

        $end_time = microtime(true);
        $duration = ($end_time - $this->run_start_time) * 1000; // Convert to milliseconds

        $this->run_data['run_metadata']['end_timestamp'] = current_time('c');
        $this->run_data['run_metadata']['total_duration_ms'] = intval($duration);
        $this->run_data['run_metadata']['status'] = $final_data['status'] ?? 'completed';

        // Add final output data
        if (!empty($final_data)) {
            $this->run_data['final_output'] = [
                'post_id' => $final_data['post_id'] ?? null,
                'block_structure_hash' => $final_data['block_structure_hash'] ?? '',
                'total_blocks' => intval($final_data['total_blocks'] ?? 0),
                'total_word_count' => intval($final_data['total_word_count'] ?? 0),
                'featured_image_id' => $final_data['featured_image_id'] ?? null,
                'seo_meta' => $final_data['seo_meta'] ?? []
            ];
        }

        // Calculate quality metrics
        $this->calculate_quality_metrics();

        // Final save to database
        $this->save_run_complete();
    }

    /**
     * Log an error or failure
     *
     * @param string $error_message Error message
     * @param array $error_context Additional error context
     * @return void
     */
    public function log_error(string $error_message, array $error_context = []): void {
        if (empty($this->current_run_id)) {
            return;
        }

        $this->run_data['run_metadata']['status'] = 'failed';
        $this->run_data['run_metadata']['end_timestamp'] = current_time('c');
        $this->run_data['run_metadata']['error'] = [
            'message' => sanitize_text_field($error_message),
            'context' => $error_context,
            'timestamp' => current_time('c')
        ];

        $this->save_run_complete();
    }

    /**
     * Get current run ID
     *
     * @return string|null
     */
    public function get_current_run_id(): ?string {
        return $this->current_run_id ?? null;
    }

    /**
     * Get run data by run ID
     *
     * @param string $run_id Run ID
     * @return array|null
     */
    public function get_run_data(string $run_id): ?array {
        $post = $this->get_run_post_by_id($run_id);
        
        if (!$post) {
            return null;
        }

        return [
            'run_metadata' => get_post_meta($post->ID, 'run_metadata', true) ?: [],
            'generation_parameters' => get_post_meta($post->ID, 'generation_parameters', true) ?: [],
            'sections_log' => get_post_meta($post->ID, 'sections_log', true) ?: [],
            'plugin_usage' => get_post_meta($post->ID, 'plugin_usage', true) ?: [],
            'cost_breakdown' => get_post_meta($post->ID, 'cost_breakdown', true) ?: [],
            'quality_metrics' => get_post_meta($post->ID, 'quality_metrics', true) ?: [],
            'final_output' => get_post_meta($post->ID, 'final_output', true) ?: []
        ];
    }

    /**
     * Generate unique run ID
     *
     * @return string
     */
    private function generate_run_id(): string {
        $timestamp = current_time('Ymd_His');
        $random = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 6);
        return "run_{$timestamp}_{$random}";
    }

    /**
     * Sanitize generation parameters
     *
     * @param array $parameters Raw parameters
     * @return array Sanitized parameters
     */
    private function sanitize_parameters(array $parameters): array {
        $sanitized = [];
        
        $sanitized['prompt'] = sanitize_textarea_field($parameters['prompt'] ?? '');
        $sanitized['namespaces_versions'] = $parameters['namespaces_versions'] ?? [];
        $sanitized['alpha_weight'] = floatval($parameters['alpha_weight'] ?? 0.7);
        $sanitized['k_value'] = intval($parameters['k_value'] ?? 10);
        $sanitized['min_score'] = floatval($parameters['min_score'] ?? 0.5);
        $sanitized['generation_mode'] = sanitize_text_field($parameters['generation_mode'] ?? 'hybrid');

        return $sanitized;
    }

    /**
     * Track plugin usage from section data
     *
     * @param array $section_data Section data
     * @return void
     */
    private function track_plugin_usage(array $section_data): void {
        $plugin_name = $section_data['plugin_required'] ?? 'core';
        $block_type = $section_data['block_type_used'] ?? '';

        $this->log_plugin_usage([
            'plugin_name' => $plugin_name,
            'block_type' => $block_type,
            'availability_status' => $section_data['plugin_available'] ?? 'unknown'
        ]);
    }

    /**
     * Update cost breakdown with section costs
     *
     * @param array $section_data Section data
     * @return void
     */
    private function update_cost_breakdown(array $section_data): void {
        $cost = floatval($section_data['cost_usd'] ?? 0);
        $tokens = intval($section_data['tokens_consumed'] ?? 0);

        $this->run_data['cost_breakdown']['total_cost_usd'] += $cost;
        $this->run_data['cost_breakdown']['openai_api_cost'] += $cost;
        $this->run_data['cost_breakdown']['token_breakdown']['total_tokens'] += $tokens;
    }

    /**
     * Calculate quality metrics from run data
     *
     * @return void
     */
    private function calculate_quality_metrics(): void {
        $sections = $this->run_data['sections_log'];
        
        if (empty($sections)) {
            return;
        }

        $total_citations = 0;
        $sections_with_citations = 0;
        $warning_count = 0;

        foreach ($sections as $section) {
            if (!empty($section['citations'])) {
                $total_citations += count($section['citations']);
                $sections_with_citations++;
            }
            
            if (!empty($section['warnings'])) {
                $warning_count += count($section['warnings']);
            }
        }

        $citation_coverage = count($sections) > 0 ? ($sections_with_citations / count($sections)) : 0;
        
        $this->run_data['quality_metrics'] = [
            'overall_recall_score' => $this->calculate_recall_score($sections),
            'citation_coverage' => round($citation_coverage, 2),
            'accessibility_score' => 85, // Placeholder - would be calculated from actual content
            'seo_optimization_score' => 80, // Placeholder - would be calculated from actual content
            'performance_impact' => $warning_count > 3 ? 'high' : ($warning_count > 1 ? 'medium' : 'low')
        ];
    }

    /**
     * Calculate overall recall score from sections
     *
     * @param array $sections Sections data
     * @return float
     */
    private function calculate_recall_score(array $sections): float {
        if (empty($sections)) {
            return 0;
        }

        $total_score = 0;
        $count = 0;

        foreach ($sections as $section) {
            // Extract recall score from warnings if available
            foreach ($section['warnings'] as $warning) {
                if (strpos($warning, 'recall:') !== false) {
                    $score = floatval(str_replace(['Low MVDB recall: ', 'recall: '], '', $warning));
                    $total_score += $score;
                    $count++;
                    break;
                }
            }
        }

        return $count > 0 ? round($total_score / $count, 2) : 0.75; // Default reasonable score
    }

    /**
     * Create WordPress post for run logging
     *
     * @return void
     */
    private function create_run_post(): void {
        $post_data = [
            'post_type' => 'ai_run',
            'post_title' => $this->current_run_id,
            'post_status' => 'private',
            'post_author' => get_current_user_id(),
            'meta_input' => [
                'run_id' => $this->current_run_id,
                'status' => 'in_progress'
            ]
        ];

        $this->run_post_id = wp_insert_post($post_data);
        
        if (is_wp_error($this->run_post_id)) {
            error_log('AI Composer: Failed to create run post - ' . $this->run_post_id->get_error_message());
            $this->run_post_id = null;
        }
    }

    /**
     * Save run progress to database
     *
     * @return void
     */
    private function save_run_progress(): void {
        if (!$this->run_post_id) {
            return;
        }

        foreach ($this->run_data as $key => $value) {
            update_post_meta($this->run_post_id, $key, $value);
        }
    }

    /**
     * Save complete run to database
     *
     * @return void
     */
    private function save_run_complete(): void {
        if (!$this->run_post_id) {
            return;
        }

        // Update all meta fields
        foreach ($this->run_data as $key => $value) {
            update_post_meta($this->run_post_id, $key, $value);
        }

        // Update post status
        wp_update_post([
            'ID' => $this->run_post_id,
            'post_status' => $this->run_data['run_metadata']['status'] === 'failed' ? 'draft' : 'private'
        ]);
    }

    /**
     * Get run post by run ID
     *
     * @param string $run_id Run ID
     * @return \WP_Post|null
     */
    private function get_run_post_by_id(string $run_id): ?\WP_Post {
        $posts = get_posts([
            'post_type' => 'ai_run',
            'meta_key' => 'run_id',
            'meta_value' => $run_id,
            'posts_per_page' => 1,
            'post_status' => ['private', 'draft']
        ]);

        return !empty($posts) ? $posts[0] : null;
    }
}