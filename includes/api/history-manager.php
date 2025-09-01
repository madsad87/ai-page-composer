<?php
/**
 * History Manager - Manages run retrieval and history interface
 *
 * Provides methods for retrieving run logs, filtering, and preparing
 * data for the history dashboard interface.
 *
 * @package AIPageComposer\API
 * @since 1.0.0
 */

namespace AIPageComposer\API;

use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;

/**
 * History Manager Class
 * 
 * Handles retrieval and management of AI generation run history
 * including filtering, pagination, and data preparation for admin interface.
 */
class History_Manager {

    /**
     * Get paginated list of runs with filtering
     *
     * @param array $filters Filter parameters
     * @return array Paginated runs data
     */
    public function get_runs(array $filters = []): array {
        $defaults = [
            'page' => 1,
            'per_page' => 20,
            'status' => 'all',
            'date_from' => '',
            'date_to' => '',
            'blueprint_id' => 0,
            'user_id' => 0
        ];

        $filters = wp_parse_args($filters, $defaults);

        // Build query arguments
        $query_args = [
            'post_type' => 'ai_run',
            'post_status' => ['private', 'draft'],
            'posts_per_page' => min(intval($filters['per_page']), 100),
            'paged' => max(1, intval($filters['page'])),
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => []
        ];

        // Add status filter
        if ($filters['status'] !== 'all') {
            $query_args['meta_query'][] = [
                'key' => 'run_metadata',
                'value' => '"status":"' . sanitize_text_field($filters['status']) . '"',
                'compare' => 'LIKE'
            ];
        }

        // Add blueprint filter
        if (!empty($filters['blueprint_id'])) {
            $query_args['meta_query'][] = [
                'key' => 'generation_parameters',
                'value' => '"blueprint_id":' . intval($filters['blueprint_id']),
                'compare' => 'LIKE'
            ];
        }

        // Add user filter
        if (!empty($filters['user_id'])) {
            $query_args['author'] = intval($filters['user_id']);
        }

        // Add date filters
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $date_query = [];
            
            if (!empty($filters['date_from'])) {
                $date_query['after'] = sanitize_text_field($filters['date_from']);
            }
            
            if (!empty($filters['date_to'])) {
                $date_query['before'] = sanitize_text_field($filters['date_to']);
            }
            
            if (!empty($date_query)) {
                $query_args['date_query'] = [$date_query];
            }
        }

        // Execute query
        $query = new \WP_Query($query_args);

        // Process results
        $runs = [];
        foreach ($query->posts as $post) {
            $run_data = $this->format_run_summary($post);
            if ($run_data) {
                $runs[] = $run_data;
            }
        }

        return [
            'runs' => $runs,
            'pagination' => [
                'current_page' => $query_args['paged'],
                'per_page' => $query_args['posts_per_page'],
                'total_pages' => $query->max_num_pages,
                'total_items' => $query->found_posts
            ],
            'filters_applied' => $this->get_applied_filters($filters)
        ];
    }

    /**
     * Get detailed run information
     *
     * @param string $run_id Run ID
     * @return array|null Run details or null if not found
     */
    public function get_run_details(string $run_id): ?array {
        $post = $this->get_run_post_by_id($run_id);
        
        if (!$post) {
            return null;
        }

        $run_data = [
            'run_metadata' => get_post_meta($post->ID, 'run_metadata', true) ?: [],
            'generation_parameters' => get_post_meta($post->ID, 'generation_parameters', true) ?: [],
            'sections_log' => get_post_meta($post->ID, 'sections_log', true) ?: [],
            'plugin_usage' => get_post_meta($post->ID, 'plugin_usage', true) ?: [],
            'cost_breakdown' => get_post_meta($post->ID, 'cost_breakdown', true) ?: [],
            'quality_metrics' => get_post_meta($post->ID, 'quality_metrics', true) ?: [],
            'final_output' => get_post_meta($post->ID, 'final_output', true) ?: []
        ];

        // Add regeneration capability assessment
        $run_data['regeneration_capability'] = $this->assess_regeneration_capability($run_data);

        return $run_data;
    }

    /**
     * Get runs statistics
     *
     * @return array Statistics data
     */
    public function get_statistics(): array {
        global $wpdb;

        $stats = [
            'total_runs' => 0,
            'completed_runs' => 0,
            'failed_runs' => 0,
            'total_cost' => 0,
            'avg_duration' => 0,
            'most_used_plugins' => []
        ];

        // Get total runs
        $total_query = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ai_run' AND post_status IN ('private', 'draft')";
        $stats['total_runs'] = intval($wpdb->get_var($total_query));

        if ($stats['total_runs'] === 0) {
            return $stats;
        }

        // Get runs by status
        $status_query = "
            SELECT 
                CASE 
                    WHEN pm.meta_value LIKE '%\"status\":\"completed\"%' THEN 'completed'
                    WHEN pm.meta_value LIKE '%\"status\":\"failed\"%' THEN 'failed'
                    ELSE 'other'
                END as status,
                COUNT(*) as count
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'run_metadata'
            WHERE p.post_type = 'ai_run' AND p.post_status IN ('private', 'draft')
            GROUP BY status
        ";

        $status_results = $wpdb->get_results($status_query);
        foreach ($status_results as $result) {
            if ($result->status === 'completed') {
                $stats['completed_runs'] = intval($result->count);
            } elseif ($result->status === 'failed') {
                $stats['failed_runs'] = intval($result->count);
            }
        }

        // Get total cost and average duration for completed runs
        $cost_duration_query = "
            SELECT 
                SUM(CAST(JSON_EXTRACT(cb.meta_value, '$.total_cost_usd') AS DECIMAL(10,4))) as total_cost,
                AVG(CAST(JSON_EXTRACT(rm.meta_value, '$.total_duration_ms') AS UNSIGNED)) as avg_duration
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} cb ON p.ID = cb.post_id AND cb.meta_key = 'cost_breakdown'
            LEFT JOIN {$wpdb->postmeta} rm ON p.ID = rm.post_id AND rm.meta_key = 'run_metadata'
            WHERE p.post_type = 'ai_run' 
            AND p.post_status IN ('private', 'draft')
            AND rm.meta_value LIKE '%\"status\":\"completed\"%'
        ";

        $cost_duration_result = $wpdb->get_row($cost_duration_query);
        if ($cost_duration_result) {
            $stats['total_cost'] = floatval($cost_duration_result->total_cost ?? 0);
            $stats['avg_duration'] = intval($cost_duration_result->avg_duration ?? 0);
        }

        // Get most used plugins
        $stats['most_used_plugins'] = $this->get_most_used_plugins();

        return $stats;
    }

    /**
     * Search runs by keyword
     *
     * @param string $keyword Search keyword
     * @param array $additional_filters Additional filters
     * @return array Search results
     */
    public function search_runs(string $keyword, array $additional_filters = []): array {
        if (empty($keyword)) {
            return $this->get_runs($additional_filters);
        }

        $keyword = sanitize_text_field($keyword);

        $query_args = [
            'post_type' => 'ai_run',
            'post_status' => ['private', 'draft'],
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'generation_parameters',
                    'value' => $keyword,
                    'compare' => 'LIKE'
                ],
                [
                    'key' => 'run_metadata',
                    'value' => $keyword,
                    'compare' => 'LIKE'
                ]
            ]
        ];

        // Merge additional filters
        if (!empty($additional_filters)) {
            $filtered_results = $this->get_runs($additional_filters);
            // This would need more complex logic to combine search with filters
            // For now, return search-only results
        }

        $query = new \WP_Query($query_args);

        $runs = [];
        foreach ($query->posts as $post) {
            $run_data = $this->format_run_summary($post);
            if ($run_data) {
                $runs[] = $run_data;
            }
        }

        return [
            'runs' => $runs,
            'search_keyword' => $keyword,
            'total_found' => $query->found_posts
        ];
    }

    /**
     * Delete a run
     *
     * @param string $run_id Run ID
     * @return bool Success status
     */
    public function delete_run(string $run_id): bool {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $post = $this->get_run_post_by_id($run_id);
        
        if (!$post) {
            return false;
        }

        $result = wp_delete_post($post->ID, true);
        return !empty($result);
    }

    /**
     * Export runs to CSV
     *
     * @param array $filters Filter parameters
     * @return string CSV content
     */
    public function export_runs_csv(array $filters = []): string {
        $runs_data = $this->get_runs(array_merge($filters, ['per_page' => -1]));
        $runs = $runs_data['runs'];

        $csv_data = [];
        $csv_data[] = [
            'Run ID',
            'Status',
            'Blueprint',
            'User',
            'Start Time',
            'Duration (seconds)',
            'Cost (USD)',
            'Sections Count',
            'Plugins Used',
            'Quality Score'
        ];

        foreach ($runs as $run) {
            $csv_data[] = [
                $run['run_id'],
                $run['status'],
                $run['blueprint_title'],
                $run['user_name'],
                $run['start_time'],
                $run['duration_seconds'],
                $run['total_cost_usd'],
                $run['sections_count'],
                implode(', ', $run['plugins_used']),
                $run['quality_score']
            ];
        }

        // Convert to CSV format
        $output = '';
        foreach ($csv_data as $row) {
            $output .= '"' . implode('","', $row) . '"' . "\n";
        }

        return $output;
    }

    /**
     * Format run data for summary display
     *
     * @param \WP_Post $post Run post
     * @return array|null Formatted run data
     */
    private function format_run_summary(\WP_Post $post): ?array {
        $metadata = get_post_meta($post->ID, 'run_metadata', true);
        $parameters = get_post_meta($post->ID, 'generation_parameters', true);
        $costs = get_post_meta($post->ID, 'cost_breakdown', true);
        $plugins = get_post_meta($post->ID, 'plugin_usage', true);
        $sections = get_post_meta($post->ID, 'sections_log', true);
        $quality = get_post_meta($post->ID, 'quality_metrics', true);

        if (empty($metadata)) {
            return null;
        }

        // Get blueprint title
        $blueprint_title = __('No Blueprint', 'ai-page-composer');
        if (!empty($parameters['blueprint_id'])) {
            $blueprint = get_post($parameters['blueprint_id']);
            if ($blueprint) {
                $blueprint_title = $blueprint->post_title;
            }
        }

        // Get user name
        $user = get_userdata($post->post_author);
        $user_name = $user ? $user->display_name : __('Unknown User', 'ai-page-composer');

        // Calculate duration in seconds
        $duration_seconds = 0;
        if (!empty($metadata['total_duration_ms'])) {
            $duration_seconds = round($metadata['total_duration_ms'] / 1000, 1);
        }

        // Get plugins used
        $plugins_used = [];
        if (!empty($plugins) && is_array($plugins)) {
            $plugins_used = array_keys($plugins);
        }

        // Get post ID from final output
        $post_id = null;
        $final_output = get_post_meta($post->ID, 'final_output', true);
        if (!empty($final_output['post_id'])) {
            $post_id = $final_output['post_id'];
        }

        return [
            'run_id' => $metadata['run_id'] ?? '',
            'blueprint_title' => $blueprint_title,
            'user_name' => $user_name,
            'status' => $metadata['status'] ?? 'unknown',
            'start_time' => $metadata['start_timestamp'] ?? '',
            'duration_seconds' => $duration_seconds,
            'total_cost_usd' => floatval($costs['total_cost_usd'] ?? 0),
            'sections_count' => is_array($sections) ? count($sections) : 0,
            'plugins_used' => $plugins_used,
            'post_id' => $post_id,
            'quality_score' => intval($quality['overall_recall_score'] ?? 0) * 100,
            'wp_post_id' => $post->ID
        ];
    }

    /**
     * Get applied filters summary
     *
     * @param array $filters Applied filters
     * @return array Filters summary
     */
    private function get_applied_filters(array $filters): array {
        $applied = [];

        if ($filters['status'] !== 'all') {
            $applied['status'] = $filters['status'];
        }

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $applied['date_range'] = 'custom';
        } elseif (!empty($filters['date_from'])) {
            $applied['date_range'] = 'from_' . $filters['date_from'];
        } elseif (!empty($filters['date_to'])) {
            $applied['date_range'] = 'to_' . $filters['date_to'];
        }

        if (!empty($filters['blueprint_id'])) {
            $applied['blueprint_id'] = $filters['blueprint_id'];
        }

        if (!empty($filters['user_id'])) {
            $applied['user_id'] = $filters['user_id'];
        }

        return $applied;
    }

    /**
     * Assess regeneration capability for a run
     *
     * @param array $run_data Run data
     * @return array Regeneration assessment
     */
    private function assess_regeneration_capability(array $run_data): array {
        $plugins = $run_data['plugin_usage'] ?? [];
        $missing_plugins = [];
        $can_regenerate = true;

        // Check plugin availability
        foreach ($plugins as $plugin_name => $plugin_data) {
            if ($plugin_name === 'core') {
                continue;
            }

            // Simple check - in real implementation, would use Block_Detector
            if (!$this->is_plugin_active($plugin_name)) {
                $missing_plugins[] = $plugin_name;
            }
        }

        // Estimate cost based on previous run
        $estimated_cost = floatval($run_data['cost_breakdown']['total_cost_usd'] ?? 0);

        return [
            'can_regenerate' => $can_regenerate,
            'missing_plugins' => $missing_plugins,
            'parameter_adjustments_needed' => !empty($missing_plugins),
            'estimated_cost' => $estimated_cost
        ];
    }

    /**
     * Get most used plugins across all runs
     *
     * @return array Most used plugins
     */
    private function get_most_used_plugins(): array {
        $posts = get_posts([
            'post_type' => 'ai_run',
            'post_status' => ['private', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);

        $plugin_usage = [];

        foreach ($posts as $post_id) {
            $plugins = get_post_meta($post_id, 'plugin_usage', true);
            
            if (!empty($plugins) && is_array($plugins)) {
                foreach ($plugins as $plugin_name => $plugin_data) {
                    if (!isset($plugin_usage[$plugin_name])) {
                        $plugin_usage[$plugin_name] = 0;
                    }
                    $plugin_usage[$plugin_name] += intval($plugin_data['usage_count'] ?? 1);
                }
            }
        }

        // Sort by usage count
        arsort($plugin_usage);

        // Return top 10
        return array_slice($plugin_usage, 0, 10, true);
    }

    /**
     * Check if a plugin is active
     *
     * @param string $plugin_name Plugin name
     * @return bool Active status
     */
    private function is_plugin_active(string $plugin_name): bool {
        // Simple implementation - would be more sophisticated in real use
        $active_plugins = get_option('active_plugins', []);
        
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, $plugin_name) !== false) {
                return true;
            }
        }

        return false;
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