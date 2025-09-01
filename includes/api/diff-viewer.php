<?php
/**
 * Diff Viewer - Visualizes changes between AI generation runs
 *
 * Provides comprehensive comparison functionality including parameter changes,
 * block type changes, plugin availability changes, and cost comparisons.
 *
 * @package AIPageComposer\API
 * @since 1.0.0
 */

namespace AIPageComposer\API;

use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;

/**
 * Diff Viewer Class
 * 
 * Handles comparison and diff visualization between AI generation runs
 * including parameter changes, content changes, and plugin availability.
 */
class Diff_Viewer {

    /**
     * History Manager instance
     *
     * @var History_Manager
     */
    private History_Manager $history_manager;

    /**
     * Constructor
     *
     * @param History_Manager $history_manager History manager instance
     */
    public function __construct(History_Manager $history_manager) {
        $this->history_manager = $history_manager;
    }

    /**
     * Generate diff between two runs or a run and current configuration
     *
     * @param string $original_run_id Original run ID
     * @param string $compare_to Comparison target (run ID or 'current')
     * @param array $diff_options Diff options
     * @return array Diff result
     */
    public function generate_diff(string $original_run_id, string $compare_to, array $diff_options = []): array {
        $original_data = $this->history_manager->get_run_details($original_run_id);
        
        if (!$original_data) {
            return [
                'error' => __('Original run not found', 'ai-page-composer'),
                'error_code' => 'run_not_found'
            ];
        }

        // Get comparison data
        if ($compare_to === 'current') {
            $comparison_data = $this->get_current_configuration();
            $comparison_type = 'run_to_current';
        } else {
            $comparison_data = $this->history_manager->get_run_details($compare_to);
            $comparison_type = 'run_to_run';
            
            if (!$comparison_data) {
                return [
                    'error' => __('Comparison run not found', 'ai-page-composer'),
                    'error_code' => 'comparison_run_not_found'
                ];
            }
        }

        // Set default diff options
        $diff_options = wp_parse_args($diff_options, [
            'include_content_changes' => true,
            'include_parameter_changes' => true,
            'include_plugin_changes' => true,
            'include_cost_analysis' => true,
            'highlight_significant_changes' => true
        ]);

        // Generate comprehensive diff
        $diff_result = [
            'comparison_metadata' => [
                'original_run_id' => $original_run_id,
                'comparison_run_id' => $compare_to,
                'diff_timestamp' => current_time('c'),
                'comparison_type' => $comparison_type
            ],
            'parameter_changes' => [],
            'section_diffs' => [],
            'plugin_availability_changes' => [],
            'cost_comparison' => []
        ];

        // Compare parameters
        if ($diff_options['include_parameter_changes']) {
            $diff_result['parameter_changes'] = $this->compare_parameters(
                $original_data['generation_parameters'],
                $comparison_data['generation_parameters'] ?? []
            );
        }

        // Compare sections and content
        if ($diff_options['include_content_changes']) {
            $diff_result['section_diffs'] = $this->compare_sections(
                $original_data['sections_log'],
                $comparison_data['sections_log'] ?? []
            );
        }

        // Compare plugin usage
        if ($diff_options['include_plugin_changes']) {
            $diff_result['plugin_availability_changes'] = $this->compare_plugin_usage(
                $original_data['plugin_usage'],
                $comparison_data['plugin_usage'] ?? []
            );
        }

        // Compare costs
        if ($diff_options['include_cost_analysis']) {
            $diff_result['cost_comparison'] = $this->compare_costs(
                $original_data['cost_breakdown'],
                $comparison_data['cost_breakdown'] ?? []
            );
        }

        // Add visualization data
        $diff_result['visualization_data'] = $this->generate_visualization_data($diff_result, $diff_options);

        return $diff_result;
    }

    /**
     * Compare generation parameters
     *
     * @param array $original Original parameters
     * @param array $comparison Comparison parameters
     * @return array Parameter changes
     */
    private function compare_parameters(array $original, array $comparison): array {
        $changes = [];

        // Compare scalar parameters
        $scalar_params = ['alpha_weight', 'k_value', 'min_score', 'generation_mode', 'prompt'];
        
        foreach ($scalar_params as $param) {
            $original_value = $original[$param] ?? null;
            $comparison_value = $comparison[$param] ?? null;
            
            if ($original_value !== $comparison_value) {
                $changes[$param] = [
                    'from' => $original_value,
                    'to' => $comparison_value,
                    'type' => 'scalar'
                ];
            }
        }

        // Compare namespace versions
        if (isset($original['namespaces_versions']) || isset($comparison['namespaces_versions'])) {
            $original_namespaces = $original['namespaces_versions'] ?? [];
            $comparison_namespaces = $comparison['namespaces_versions'] ?? [];
            
            $namespace_changes = [];
            $all_namespaces = array_unique(array_merge(
                array_keys($original_namespaces),
                array_keys($comparison_namespaces)
            ));
            
            foreach ($all_namespaces as $namespace) {
                $original_enabled = $original_namespaces[$namespace] ?? false;
                $comparison_enabled = $comparison_namespaces[$namespace] ?? false;
                
                if ($original_enabled !== $comparison_enabled) {
                    $namespace_changes[$namespace] = [
                        'from' => $original_enabled,
                        'to' => $comparison_enabled
                    ];
                } else {
                    $namespace_changes[$namespace] = 'unchanged';
                }
            }
            
            if (!empty($namespace_changes)) {
                $changes['namespaces_versions'] = $namespace_changes;
            }
        }

        return $changes;
    }

    /**
     * Compare sections between runs
     *
     * @param array $original Original sections
     * @param array $comparison Comparison sections
     * @return array Section differences
     */
    private function compare_sections(array $original, array $comparison): array {
        $section_diffs = [];

        $max_sections = max(count($original), count($comparison));

        for ($i = 0; $i < $max_sections; $i++) {
            $original_section = $original[$i] ?? null;
            $comparison_section = $comparison[$i] ?? null;

            $section_diff = [
                'section_index' => $i,
                'section_id' => $original_section['section_id'] ?? $comparison_section['section_id'] ?? "section_{$i}",
                'changes' => []
            ];

            // Check if section exists in both
            if (!$original_section) {
                $section_diff['changes']['existence'] = 'added_in_comparison';
            } elseif (!$comparison_section) {
                $section_diff['changes']['existence'] = 'removed_in_comparison';
            } else {
                // Compare section properties
                $this->compare_section_properties($original_section, $comparison_section, $section_diff);
            }

            if (!empty($section_diff['changes'])) {
                $section_diffs[] = $section_diff;
            }
        }

        return $section_diffs;
    }

    /**
     * Compare individual section properties
     *
     * @param array $original Original section
     * @param array $comparison Comparison section
     * @param array &$section_diff Section diff array (by reference)
     */
    private function compare_section_properties(array $original, array $comparison, array &$section_diff): void {
        // Compare block type
        $original_block = $original['block_type_used'] ?? '';
        $comparison_block = $comparison['block_type_used'] ?? '';
        
        if ($original_block !== $comparison_block) {
            $section_diff['changes']['block_type_change'] = [
                'from' => $original_block,
                'to' => $comparison_block,
                'reason' => $this->determine_block_change_reason($original, $comparison)
            ];
        }

        // Compare plugin requirements
        $original_plugin = $original['plugin_required'] ?? 'core';
        $comparison_plugin = $comparison['plugin_required'] ?? 'core';
        
        if ($original_plugin !== $comparison_plugin) {
            $section_diff['changes']['plugin_change'] = [
                'from' => $original_plugin,
                'to' => $comparison_plugin
            ];
        }

        // Compare fallback status
        $original_fallback = $original['fallback_applied'] ?? false;
        $comparison_fallback = $comparison['fallback_applied'] ?? false;
        
        if ($original_fallback !== $comparison_fallback) {
            $section_diff['changes']['fallback_change'] = [
                'from' => $original_fallback,
                'to' => $comparison_fallback
            ];
        }

        // Compare performance metrics
        $original_tokens = $original['tokens_consumed'] ?? 0;
        $comparison_tokens = $comparison['tokens_consumed'] ?? 0;
        $token_delta = $comparison_tokens - $original_tokens;
        
        if (abs($token_delta) > 50) { // Only report significant token changes
            $section_diff['changes']['token_usage'] = [
                'from' => $original_tokens,
                'to' => $comparison_tokens,
                'delta' => $token_delta
            ];
        }

        // Compare costs
        $original_cost = $original['cost_usd'] ?? 0;
        $comparison_cost = $comparison['cost_usd'] ?? 0;
        $cost_delta = $comparison_cost - $original_cost;
        
        if (abs($cost_delta) > 0.001) { // Only report significant cost changes
            $section_diff['changes']['cost'] = [
                'from' => $original_cost,
                'to' => $comparison_cost,
                'delta' => $cost_delta
            ];
        }

        // Compare citations
        $original_citations = count($original['citations'] ?? []);
        $comparison_citations = count($comparison['citations'] ?? []);
        
        if ($original_citations !== $comparison_citations) {
            $section_diff['changes']['citations'] = [
                'from' => $original_citations,
                'to' => $comparison_citations,
                'delta' => $comparison_citations - $original_citations
            ];
        }

        // Compare warnings
        $original_warnings = count($original['warnings'] ?? []);
        $comparison_warnings = count($comparison['warnings'] ?? []);
        
        if ($original_warnings !== $comparison_warnings) {
            $section_diff['changes']['warnings'] = [
                'from' => $original_warnings,
                'to' => $comparison_warnings,
                'delta' => $comparison_warnings - $original_warnings
            ];
        }
    }

    /**
     * Compare plugin usage between runs
     *
     * @param array $original Original plugin usage
     * @param array $comparison Comparison plugin usage
     * @return array Plugin availability changes
     */
    private function compare_plugin_usage(array $original, array $comparison): array {
        $plugin_changes = [];

        $all_plugins = array_unique(array_merge(
            array_keys($original),
            array_keys($comparison)
        ));

        foreach ($all_plugins as $plugin_name) {
            $original_plugin = $original[$plugin_name] ?? null;
            $comparison_plugin = $comparison[$plugin_name] ?? null;

            $plugin_change = [
                'plugin_name' => $plugin_name,
                'changes' => []
            ];

            if (!$original_plugin && $comparison_plugin) {
                $plugin_change['changes']['availability'] = 'newly_available';
                $plugin_change['changes']['usage_count'] = [
                    'from' => 0,
                    'to' => $comparison_plugin['usage_count'] ?? 0
                ];
            } elseif ($original_plugin && !$comparison_plugin) {
                $plugin_change['changes']['availability'] = 'no_longer_available';
                $plugin_change['changes']['usage_count'] = [
                    'from' => $original_plugin['usage_count'] ?? 0,
                    'to' => 0
                ];
            } elseif ($original_plugin && $comparison_plugin) {
                // Compare versions
                $original_version = $original_plugin['version'] ?? '';
                $comparison_version = $comparison_plugin['version'] ?? '';
                
                if ($original_version !== $comparison_version) {
                    $plugin_change['changes']['version'] = [
                        'from' => $original_version,
                        'to' => $comparison_version
                    ];
                }

                // Compare availability status
                $original_status = $original_plugin['availability_status'] ?? '';
                $comparison_status = $comparison_plugin['availability_status'] ?? '';
                
                if ($original_status !== $comparison_status) {
                    $plugin_change['changes']['status'] = [
                        'from' => $original_status,
                        'to' => $comparison_status
                    ];
                }

                // Compare usage count
                $original_usage = $original_plugin['usage_count'] ?? 0;
                $comparison_usage = $comparison_plugin['usage_count'] ?? 0;
                
                if ($original_usage !== $comparison_usage) {
                    $plugin_change['changes']['usage_count'] = [
                        'from' => $original_usage,
                        'to' => $comparison_usage,
                        'delta' => $comparison_usage - $original_usage
                    ];
                }

                // Compare blocks used
                $original_blocks = $original_plugin['blocks_used'] ?? [];
                $comparison_blocks = $comparison_plugin['blocks_used'] ?? [];
                
                $blocks_diff = $this->compare_arrays($original_blocks, $comparison_blocks);
                if (!empty($blocks_diff)) {
                    $plugin_change['changes']['blocks_used'] = $blocks_diff;
                }
            }

            if (!empty($plugin_change['changes'])) {
                $plugin_changes[$plugin_name] = $plugin_change;
            }
        }

        return $plugin_changes;
    }

    /**
     * Compare costs between runs
     *
     * @param array $original Original costs
     * @param array $comparison Comparison costs
     * @return array Cost comparison
     */
    private function compare_costs(array $original, array $comparison): array {
        $cost_comparison = [];

        // Compare total costs
        $original_total = $original['total_cost_usd'] ?? 0;
        $comparison_total = $comparison['total_cost_usd'] ?? 0;
        $cost_delta = $comparison_total - $original_total;

        $cost_comparison['total_cost'] = [
            'from' => $original_total,
            'to' => $comparison_total,
            'delta' => $cost_delta,
            'percentage_change' => $original_total > 0 ? ($cost_delta / $original_total) * 100 : 0
        ];

        // Compare API costs
        $original_api = $original['openai_api_cost'] ?? 0;
        $comparison_api = $comparison['openai_api_cost'] ?? 0;
        
        if ($original_api !== $comparison_api) {
            $cost_comparison['api_cost'] = [
                'from' => $original_api,
                'to' => $comparison_api,
                'delta' => $comparison_api - $original_api
            ];
        }

        // Compare MVDB costs
        $original_mvdb = $original['mvdb_query_cost'] ?? 0;
        $comparison_mvdb = $comparison['mvdb_query_cost'] ?? 0;
        
        if ($original_mvdb !== $comparison_mvdb) {
            $cost_comparison['mvdb_cost'] = [
                'from' => $original_mvdb,
                'to' => $comparison_mvdb,
                'delta' => $comparison_mvdb - $original_mvdb
            ];
        }

        // Compare token usage
        $original_tokens = $original['token_breakdown']['total_tokens'] ?? 0;
        $comparison_tokens = $comparison['token_breakdown']['total_tokens'] ?? 0;
        
        if ($original_tokens !== $comparison_tokens) {
            $cost_comparison['token_usage'] = [
                'from' => $original_tokens,
                'to' => $comparison_tokens,
                'delta' => $comparison_tokens - $original_tokens
            ];
        }

        return $cost_comparison;
    }

    /**
     * Generate visualization data for the diff
     *
     * @param array $diff_result Diff result
     * @param array $diff_options Diff options
     * @return array Visualization data
     */
    private function generate_visualization_data(array $diff_result, array $diff_options): array {
        $change_summary = [
            'total_changes' => 0,
            'significant_changes' => 0,
            'plugin_changes' => 0,
            'content_changes' => 0,
            'parameter_changes' => 0
        ];

        // Count parameter changes
        $parameter_changes = count($diff_result['parameter_changes']);
        $change_summary['parameter_changes'] = $parameter_changes;
        $change_summary['total_changes'] += $parameter_changes;

        // Count section changes
        $content_changes = 0;
        foreach ($diff_result['section_diffs'] as $section_diff) {
            $content_changes += count($section_diff['changes']);
        }
        $change_summary['content_changes'] = $content_changes;
        $change_summary['total_changes'] += $content_changes;

        // Count plugin changes
        $plugin_changes = count($diff_result['plugin_availability_changes']);
        $change_summary['plugin_changes'] = $plugin_changes;
        $change_summary['total_changes'] += $plugin_changes;

        // Determine significant changes
        foreach ($diff_result['section_diffs'] as $section_diff) {
            if (isset($section_diff['changes']['block_type_change']) || 
                isset($section_diff['changes']['plugin_change'])) {
                $change_summary['significant_changes']++;
            }
        }

        // Determine overall impact
        $change_impact = 'low';
        if ($change_summary['significant_changes'] > 2 || $plugin_changes > 1) {
            $change_impact = 'high';
        } elseif ($change_summary['significant_changes'] > 0 || $plugin_changes > 0) {
            $change_impact = 'medium';
        }

        // Generate recommendation
        $recommendation = $this->generate_recommendation($diff_result, $change_summary);

        return [
            'change_summary' => $change_summary,
            'change_impact' => $change_impact,
            'recommendation' => $recommendation
        ];
    }

    /**
     * Get current configuration for comparison
     *
     * @return array Current configuration
     */
    private function get_current_configuration(): array {
        // This would get current plugin settings, available plugins, etc.
        // For now, return a basic structure
        return [
            'generation_parameters' => get_option('ai_composer_settings', []),
            'plugin_usage' => [], // Would be populated with current plugin availability
            'cost_breakdown' => [],
            'sections_log' => []
        ];
    }

    /**
     * Determine reason for block type change
     *
     * @param array $original Original section
     * @param array $comparison Comparison section
     * @return string Change reason
     */
    private function determine_block_change_reason(array $original, array $comparison): string {
        $original_plugin = $original['plugin_required'] ?? 'core';
        $comparison_plugin = $comparison['plugin_required'] ?? 'core';
        
        if ($original_plugin !== 'core' && $comparison_plugin === 'core') {
            return sprintf(__('%s plugin not available', 'ai-page-composer'), $original_plugin);
        }
        
        if ($original['fallback_applied'] ?? false) {
            return __('Fallback was applied', 'ai-page-composer');
        }
        
        return __('Block preference changed', 'ai-page-composer');
    }

    /**
     * Compare two arrays and return differences
     *
     * @param array $original Original array
     * @param array $comparison Comparison array
     * @return array Differences
     */
    private function compare_arrays(array $original, array $comparison): array {
        $added = array_diff($comparison, $original);
        $removed = array_diff($original, $comparison);
        
        $diff = [];
        if (!empty($added)) {
            $diff['added'] = array_values($added);
        }
        if (!empty($removed)) {
            $diff['removed'] = array_values($removed);
        }
        
        return $diff;
    }

    /**
     * Generate recommendation based on diff analysis
     *
     * @param array $diff_result Diff result
     * @param array $change_summary Change summary
     * @return string Recommendation
     */
    private function generate_recommendation(array $diff_result, array $change_summary): string {
        if ($change_summary['plugin_changes'] > 0) {
            return __('Review plugin availability before re-run', 'ai-page-composer');
        }
        
        if ($change_summary['significant_changes'] > 2) {
            return __('Major changes detected - careful review recommended', 'ai-page-composer');
        }
        
        if ($change_summary['total_changes'] === 0) {
            return __('No changes detected - safe to re-run', 'ai-page-composer');
        }
        
        return __('Minor changes detected - re-run with caution', 'ai-page-composer');
    }
}