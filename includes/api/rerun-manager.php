<?php
/**
 * ReRun Manager - Reproduces previous AI generation runs
 *
 * Handles re-running previous generations with the same parameters while
 * gracefully adapting to plugin availability changes and current configuration.
 *
 * @package AIPageComposer\API
 * @since 1.0.0
 */

namespace AIPageComposer\API;

use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;
use AIPageComposer\API\Block_Detector;
use AIPageComposer\API\Block_Resolver;

/**
 * ReRun Manager Class
 * 
 * Handles reproduction of previous AI generation runs with intelligent
 * adaptation to current plugin availability and configuration changes.
 */
class ReRun_Manager {

    /**
     * History Manager instance
     *
     * @var History_Manager
     */
    private History_Manager $history_manager;

    /**
     * Diff Viewer instance
     *
     * @var Diff_Viewer
     */
    private Diff_Viewer $diff_viewer;

    /**
     * Run Logger instance
     *
     * @var Run_Logger
     */
    private Run_Logger $run_logger;

    /**
     * Block Detector instance
     *
     * @var Block_Detector|null
     */
    private ?Block_Detector $block_detector = null;

    /**
     * Block Resolver instance
     *
     * @var Block_Resolver|null
     */
    private ?Block_Resolver $block_resolver = null;

    /**
     * Constructor
     *
     * @param History_Manager $history_manager History manager instance
     * @param Diff_Viewer $diff_viewer Diff viewer instance
     * @param Run_Logger $run_logger Run logger instance
     */
    public function __construct(
        History_Manager $history_manager,
        Diff_Viewer $diff_viewer,
        Run_Logger $run_logger
    ) {
        $this->history_manager = $history_manager;
        $this->diff_viewer = $diff_viewer;
        $this->run_logger = $run_logger;
    }

    /**
     * Set block detector instance
     *
     * @param Block_Detector $block_detector Block detector instance
     */
    public function set_block_detector(Block_Detector $block_detector): void {
        $this->block_detector = $block_detector;
    }

    /**
     * Set block resolver instance
     *
     * @param Block_Resolver $block_resolver Block resolver instance
     */
    public function set_block_resolver(Block_Resolver $block_resolver): void {
        $this->block_resolver = $block_resolver;
    }

    /**
     * Re-run a previous generation with optional parameter overrides
     *
     * @param string $original_run_id Original run ID to reproduce
     * @param array $rerun_options Re-run configuration options
     * @param array $parameter_overrides Parameter overrides
     * @return array Re-run result
     */
    public function rerun_generation(
        string $original_run_id,
        array $rerun_options = [],
        array $parameter_overrides = []
    ): array {
        // Get original run data
        $original_data = $this->history_manager->get_run_details($original_run_id);
        
        if (!$original_data) {
            return [
                'success' => false,
                'error' => __('Original run not found', 'ai-page-composer'),
                'error_code' => 'run_not_found'
            ];
        }

        // Set default rerun options
        $rerun_options = wp_parse_args($rerun_options, [
            'preserve_plugin_preferences' => true,
            'fallback_on_missing_plugins' => true,
            'update_namespace_versions' => false,
            'maintain_cost_limits' => true,
            'notification_on_changes' => true
        ]);

        try {
            // Analyze current environment and prepare adapted parameters
            $adaptation_result = $this->analyze_and_adapt_parameters(
                $original_data,
                $rerun_options,
                $parameter_overrides
            );

            if (!$adaptation_result['can_proceed']) {
                return [
                    'success' => false,
                    'error' => $adaptation_result['error_message'],
                    'error_code' => 'adaptation_failed',
                    'adaptation_issues' => $adaptation_result['issues']
                ];
            }

            // Start new run with adapted parameters
            $new_run_id = $this->run_logger->start_run($adaptation_result['adapted_parameters']);

            // Log the adaptation process
            $this->log_adaptation_process($new_run_id, $adaptation_result);

            // Execute the generation (this would integrate with existing generation managers)
            $generation_result = $this->execute_generation($adaptation_result['adapted_parameters']);

            if ($generation_result['success']) {
                // Complete the run logging
                $this->run_logger->complete_run([
                    'status' => 'completed',
                    'post_id' => $generation_result['post_id'] ?? null,
                    'total_blocks' => $generation_result['total_blocks'] ?? 0,
                    'total_word_count' => $generation_result['total_word_count'] ?? 0
                ]);

                // Generate diff preview
                $diff_preview = $this->generate_rerun_diff_preview($original_run_id, $new_run_id);

                return [
                    'success' => true,
                    'rerun_result' => [
                        'new_run_id' => $new_run_id,
                        'status' => 'completed',
                        'parameter_adaptations' => $adaptation_result['adaptations'],
                        'plugin_fallbacks' => $adaptation_result['plugin_fallbacks'],
                        'cost_comparison' => $this->compare_costs(
                            $original_data['cost_breakdown'],
                            $generation_result['cost_breakdown'] ?? []
                        )
                    ],
                    'diff_preview' => $diff_preview
                ];
            } else {
                // Log error and return failure
                $this->run_logger->log_error(
                    $generation_result['error'] ?? 'Generation failed',
                    $generation_result['error_context'] ?? []
                );

                return [
                    'success' => false,
                    'error' => $generation_result['error'] ?? 'Generation failed',
                    'error_code' => 'generation_failed',
                    'new_run_id' => $new_run_id
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => sprintf(__('Re-run failed: %s', 'ai-page-composer'), $e->getMessage()),
                'error_code' => 'exception',
                'exception_details' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }

    /**
     * Preview a re-run without executing it
     *
     * @param string $original_run_id Original run ID
     * @param array $rerun_options Re-run options
     * @param array $parameter_overrides Parameter overrides
     * @return array Preview result
     */
    public function preview_rerun(
        string $original_run_id,
        array $rerun_options = [],
        array $parameter_overrides = []
    ): array {
        $original_data = $this->history_manager->get_run_details($original_run_id);
        
        if (!$original_data) {
            return [
                'success' => false,
                'error' => __('Original run not found', 'ai-page-composer')
            ];
        }

        $rerun_options = wp_parse_args($rerun_options, [
            'preserve_plugin_preferences' => true,
            'fallback_on_missing_plugins' => true,
            'update_namespace_versions' => false,
            'maintain_cost_limits' => true
        ]);

        $adaptation_result = $this->analyze_and_adapt_parameters(
            $original_data,
            $rerun_options,
            $parameter_overrides
        );

        return [
            'success' => true,
            'preview' => [
                'can_proceed' => $adaptation_result['can_proceed'],
                'parameter_adaptations' => $adaptation_result['adaptations'],
                'plugin_fallbacks' => $adaptation_result['plugin_fallbacks'],
                'estimated_cost' => $this->estimate_rerun_cost($original_data, $adaptation_result),
                'warnings' => $adaptation_result['warnings'],
                'recommendations' => $this->generate_rerun_recommendations($adaptation_result)
            ]
        ];
    }

    /**
     * Analyze current environment and adapt parameters
     *
     * @param array $original_data Original run data
     * @param array $rerun_options Re-run options
     * @param array $parameter_overrides Parameter overrides
     * @return array Adaptation result
     */
    private function analyze_and_adapt_parameters(
        array $original_data,
        array $rerun_options,
        array $parameter_overrides
    ): array {
        $original_params = $original_data['generation_parameters'];
        $original_plugins = $original_data['plugin_usage'] ?? [];
        
        $adapted_parameters = $original_params;
        $adaptations = [];
        $plugin_fallbacks = [];
        $warnings = [];
        $can_proceed = true;

        // Apply parameter overrides first
        if (!empty($parameter_overrides)) {
            foreach ($parameter_overrides as $key => $value) {
                if (isset($adapted_parameters[$key])) {
                    $adaptations[] = [
                        'parameter' => $key,
                        'original' => $adapted_parameters[$key],
                        'adapted' => $value,
                        'reason' => 'User override'
                    ];
                    $adapted_parameters[$key] = $value;
                }
            }
        }

        // Check plugin availability and adapt
        foreach ($original_plugins as $plugin_name => $plugin_data) {
            if ($plugin_name === 'core') {
                continue;
            }

            $current_availability = $this->check_plugin_availability($plugin_name);
            
            if (!$current_availability['available']) {
                if ($rerun_options['fallback_on_missing_plugins']) {
                    $fallback_plugin = $this->find_plugin_fallback($plugin_name, $plugin_data);
                    $plugin_fallbacks[$plugin_name] = $fallback_plugin;
                    
                    $warnings[] = sprintf(
                        __('Plugin %s is not available, falling back to %s', 'ai-page-composer'),
                        $plugin_name,
                        $fallback_plugin
                    );
                } else {
                    $can_proceed = false;
                    $warnings[] = sprintf(
                        __('Required plugin %s is not available', 'ai-page-composer'),
                        $plugin_name
                    );
                }
            } elseif ($current_availability['version_changed']) {
                $warnings[] = sprintf(
                    __('Plugin %s version changed from %s to %s', 'ai-page-composer'),
                    $plugin_name,
                    $plugin_data['version'] ?? 'unknown',
                    $current_availability['current_version']
                );
            }
        }

        // Update namespace versions if requested
        if ($rerun_options['update_namespace_versions']) {
            $current_namespaces = $this->get_current_namespace_versions();
            if (!empty($current_namespaces)) {
                $adaptations[] = [
                    'parameter' => 'namespaces_versions',
                    'original' => $adapted_parameters['namespaces_versions'] ?? [],
                    'adapted' => $current_namespaces,
                    'reason' => 'Updated to current namespace versions'
                ];
                $adapted_parameters['namespaces_versions'] = $current_namespaces;
            }
        }

        // Check cost limits if enabled
        if ($rerun_options['maintain_cost_limits']) {
            $estimated_cost = $this->estimate_adapted_cost($original_data, $adapted_parameters);
            $cost_limit = $this->get_cost_limit();
            
            if ($cost_limit > 0 && $estimated_cost > $cost_limit) {
                $warnings[] = sprintf(
                    __('Estimated cost ($%.4f) exceeds limit ($%.4f)', 'ai-page-composer'),
                    $estimated_cost,
                    $cost_limit
                );
            }
        }

        return [
            'can_proceed' => $can_proceed,
            'adapted_parameters' => $adapted_parameters,
            'adaptations' => $adaptations,
            'plugin_fallbacks' => $plugin_fallbacks,
            'warnings' => $warnings,
            'issues' => $can_proceed ? [] : $warnings,
            'error_message' => $can_proceed ? '' : implode('; ', $warnings)
        ];
    }

    /**
     * Check plugin availability
     *
     * @param string $plugin_name Plugin name
     * @return array Availability info
     */
    private function check_plugin_availability(string $plugin_name): array {
        if ($this->block_detector) {
            return $this->block_detector->get_plugin_availability($plugin_name);
        }

        // Fallback implementation
        $active_plugins = get_option('active_plugins', []);
        $available = false;
        
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, $plugin_name) !== false) {
                $available = true;
                break;
            }
        }

        return [
            'available' => $available,
            'version_changed' => false,
            'current_version' => 'unknown'
        ];
    }

    /**
     * Find fallback plugin for unavailable plugin
     *
     * @param string $plugin_name Unavailable plugin name
     * @param array $plugin_data Original plugin data
     * @return string Fallback plugin name
     */
    private function find_plugin_fallback(string $plugin_name, array $plugin_data): string {
        if ($this->block_resolver) {
            return $this->block_resolver->find_fallback_plugin($plugin_name, $plugin_data);
        }

        // Simple fallback logic
        $fallback_map = [
            'kadence_blocks' => 'core',
            'genesis_blocks' => 'core',
            'gutenberg' => 'core'
        ];

        return $fallback_map[$plugin_name] ?? 'core';
    }

    /**
     * Execute generation with adapted parameters
     *
     * @param array $parameters Adapted generation parameters
     * @return array Generation result
     */
    private function execute_generation(array $parameters): array {
        // This would integrate with existing generation managers
        // For now, return a mock successful result
        
        return [
            'success' => true,
            'post_id' => wp_insert_post([
                'post_type' => 'page',
                'post_title' => 'Re-run Generated Content',
                'post_status' => 'draft',
                'post_content' => '<!-- This would contain the generated blocks -->'
            ]),
            'total_blocks' => 8,
            'total_word_count' => 1200,
            'cost_breakdown' => [
                'total_cost_usd' => 0.145,
                'openai_api_cost' => 0.130,
                'mvdb_query_cost' => 0.015,
                'token_breakdown' => [
                    'input_tokens' => 2100,
                    'output_tokens' => 1700,
                    'total_tokens' => 3800
                ]
            ]
        ];
    }

    /**
     * Log the adaptation process
     *
     * @param string $run_id New run ID
     * @param array $adaptation_result Adaptation result
     */
    private function log_adaptation_process(string $run_id, array $adaptation_result): void {
        foreach ($adaptation_result['adaptations'] as $adaptation) {
            $this->run_logger->log_section_generation([
                'section_id' => 'adaptation_log',
                'section_type' => 'system',
                'warnings' => [
                    sprintf(
                        'Parameter %s adapted: %s → %s (%s)',
                        $adaptation['parameter'],
                        $this->format_value_for_log($adaptation['original']),
                        $this->format_value_for_log($adaptation['adapted']),
                        $adaptation['reason']
                    )
                ]
            ]);
        }

        foreach ($adaptation_result['plugin_fallbacks'] as $original => $fallback) {
            $this->run_logger->log_plugin_usage([
                'plugin_name' => $fallback,
                'block_type' => 'fallback',
                'availability_status' => 'fallback_from_' . $original
            ]);
        }
    }

    /**
     * Generate diff preview for rerun
     *
     * @param string $original_run_id Original run ID
     * @param string $new_run_id New run ID
     * @return array Diff preview
     */
    private function generate_rerun_diff_preview(string $original_run_id, string $new_run_id): array {
        $diff_result = $this->diff_viewer->generate_diff($original_run_id, $new_run_id);
        
        return [
            'major_changes' => $diff_result['visualization_data']['change_summary']['significant_changes'] ?? 0,
            'block_type_changes' => $this->count_block_type_changes($diff_result),
            'content_similarity' => $this->calculate_content_similarity($diff_result),
            'cost_difference' => $diff_result['cost_comparison']['total_cost']['delta'] ?? 0
        ];
    }

    /**
     * Compare costs between original and new run
     *
     * @param array $original_costs Original costs
     * @param array $new_costs New costs
     * @return array Cost comparison
     */
    private function compare_costs(array $original_costs, array $new_costs): array {
        $original_total = $original_costs['total_cost_usd'] ?? 0;
        $new_total = $new_costs['total_cost_usd'] ?? 0;
        
        return [
            'original_cost' => $original_total,
            'new_cost' => $new_total,
            'savings' => $original_total - $new_total,
            'percentage_change' => $original_total > 0 ? (($new_total - $original_total) / $original_total) * 100 : 0
        ];
    }

    /**
     * Estimate rerun cost
     *
     * @param array $original_data Original run data
     * @param array $adaptation_result Adaptation result
     * @return float Estimated cost
     */
    private function estimate_rerun_cost(array $original_data, array $adaptation_result): float {
        $base_cost = $original_data['cost_breakdown']['total_cost_usd'] ?? 0;
        
        // Adjust cost based on adaptations
        $adaptation_factor = 1.0;
        
        if (!empty($adaptation_result['plugin_fallbacks'])) {
            // Fallbacks might require more tokens
            $adaptation_factor += 0.1;
        }
        
        if (!empty($adaptation_result['adaptations'])) {
            // Parameter changes might affect cost
            $adaptation_factor += 0.05;
        }
        
        return $base_cost * $adaptation_factor;
    }

    /**
     * Get current namespace versions
     *
     * @return array Current namespace versions
     */
    private function get_current_namespace_versions(): array {
        // This would get current namespace versions from MVDB or settings
        return get_option('ai_composer_namespaces', []);
    }

    /**
     * Get cost limit from settings
     *
     * @return float Cost limit
     */
    private function get_cost_limit(): float {
        $settings = get_option('ai_composer_settings', []);
        return floatval($settings['cost_limit'] ?? 0);
    }

    /**
     * Estimate adapted cost
     *
     * @param array $original_data Original run data
     * @param array $adapted_parameters Adapted parameters
     * @return float Estimated cost
     */
    private function estimate_adapted_cost(array $original_data, array $adapted_parameters): float {
        // Simple estimation based on original cost
        return $original_data['cost_breakdown']['total_cost_usd'] ?? 0;
    }

    /**
     * Generate rerun recommendations
     *
     * @param array $adaptation_result Adaptation result
     * @return array Recommendations
     */
    private function generate_rerun_recommendations(array $adaptation_result): array {
        $recommendations = [];
        
        if (!empty($adaptation_result['plugin_fallbacks'])) {
            $recommendations[] = __('Consider installing missing plugins for better results', 'ai-page-composer');
        }
        
        if (!empty($adaptation_result['warnings'])) {
            $recommendations[] = __('Review warnings before proceeding', 'ai-page-composer');
        }
        
        if (empty($adaptation_result['adaptations'])) {
            $recommendations[] = __('No adaptations needed - should produce similar results', 'ai-page-composer');
        }
        
        return $recommendations;
    }

    /**
     * Count block type changes in diff
     *
     * @param array $diff_result Diff result
     * @return int Count of block type changes
     */
    private function count_block_type_changes(array $diff_result): int {
        $count = 0;
        
        foreach ($diff_result['section_diffs'] ?? [] as $section_diff) {
            if (isset($section_diff['changes']['block_type_change'])) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Calculate content similarity between runs
     *
     * @param array $diff_result Diff result
     * @return float Similarity score (0-1)
     */
    private function calculate_content_similarity(array $diff_result): float {
        $total_sections = count($diff_result['section_diffs'] ?? []);
        
        if ($total_sections === 0) {
            return 1.0;
        }
        
        $similar_sections = 0;
        
        foreach ($diff_result['section_diffs'] as $section_diff) {
            if (empty($section_diff['changes']['block_type_change'])) {
                $similar_sections++;
            }
        }
        
        return $similar_sections / $total_sections;
    }

    /**
     * Format value for logging
     *
     * @param mixed $value Value to format
     * @return string Formatted value
     */
    private function format_value_for_log($value): string {
        if (is_array($value)) {
            return json_encode($value);
        }
        
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        return (string) $value;
    }
}