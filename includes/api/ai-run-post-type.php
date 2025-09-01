<?php
/**
 * AI Run Post Type Registration
 *
 * Registers the custom post type for storing AI generation run logs
 * with comprehensive metadata schema for governance and auditing.
 *
 * @package AIPageComposer\API
 * @since 1.0.0
 */

namespace AIPageComposer\API;

/**
 * AI Run Post Type Class
 * 
 * Handles registration and management of the ai_run custom post type
 * used for storing comprehensive run logs and metadata.
 */
class AI_Run_Post_Type {

    /**
     * Post type key
     */
    const POST_TYPE = 'ai_run';

    /**
     * Initialize the post type
     */
    public function init(): void {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'fill_custom_columns'], 10, 2);
    }

    /**
     * Register the AI Run post type
     */
    public function register_post_type(): void {
        $labels = [
            'name' => __('AI Runs', 'ai-page-composer'),
            'singular_name' => __('AI Run', 'ai-page-composer'),
            'menu_name' => __('AI Runs', 'ai-page-composer'),
            'name_admin_bar' => __('AI Run', 'ai-page-composer'),
            'add_new' => __('Add New', 'ai-page-composer'),
            'add_new_item' => __('Add New AI Run', 'ai-page-composer'),
            'new_item' => __('New AI Run', 'ai-page-composer'),
            'edit_item' => __('Edit AI Run', 'ai-page-composer'),
            'view_item' => __('View AI Run', 'ai-page-composer'),
            'all_items' => __('All AI Runs', 'ai-page-composer'),
            'search_items' => __('Search AI Runs', 'ai-page-composer'),
            'not_found' => __('No AI runs found.', 'ai-page-composer'),
            'not_found_in_trash' => __('No AI runs found in Trash.', 'ai-page-composer')
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => 'ai-page-composer',
            'query_var' => false,
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'manage_options',
                'edit_posts' => 'manage_options',
                'edit_others_posts' => 'manage_options',
                'publish_posts' => 'manage_options',
                'read_private_posts' => 'manage_options',
                'delete_posts' => 'manage_options',
                'delete_private_posts' => 'manage_options',
                'delete_published_posts' => 'manage_options',
                'delete_others_posts' => 'manage_options',
                'edit_private_posts' => 'manage_options',
                'edit_published_posts' => 'manage_options'
            ],
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => ['title'],
            'show_in_rest' => false
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Add meta boxes for the AI Run post type
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'ai_run_metadata',
            __('Run Details', 'ai-page-composer'),
            [$this, 'render_metadata_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'ai_run_parameters',
            __('Generation Parameters', 'ai-page-composer'),
            [$this, 'render_parameters_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'ai_run_sections',
            __('Sections Log', 'ai-page-composer'),
            [$this, 'render_sections_meta_box'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'ai_run_plugins',
            __('Plugin Usage', 'ai-page-composer'),
            [$this, 'render_plugins_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'ai_run_costs',
            __('Cost Breakdown', 'ai-page-composer'),
            [$this, 'render_costs_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Add custom columns to the AI Runs list table
     */
    public function add_custom_columns(array $columns): array {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'title') {
                $new_columns['run_status'] = __('Status', 'ai-page-composer');
                $new_columns['blueprint'] = __('Blueprint', 'ai-page-composer');
                $new_columns['duration'] = __('Duration', 'ai-page-composer');
                $new_columns['cost'] = __('Cost', 'ai-page-composer');
                $new_columns['plugins_used'] = __('Plugins Used', 'ai-page-composer');
            }
        }
        
        return $new_columns;
    }

    /**
     * Fill custom columns with data
     */
    public function fill_custom_columns(string $column, int $post_id): void {
        switch ($column) {
            case 'run_status':
                $metadata = get_post_meta($post_id, 'run_metadata', true);
                $status = $metadata['status'] ?? 'unknown';
                $status_colors = [
                    'completed' => '#00a32a',
                    'failed' => '#d63638',
                    'in_progress' => '#dba617'
                ];
                $color = $status_colors[$status] ?? '#50575e';
                echo '<span style="color: ' . esc_attr($color) . '; font-weight: bold;">' . esc_html(ucfirst($status)) . '</span>';
                break;

            case 'blueprint':
                $params = get_post_meta($post_id, 'generation_parameters', true);
                $blueprint_id = $params['blueprint_id'] ?? null;
                if ($blueprint_id) {
                    $blueprint = get_post($blueprint_id);
                    if ($blueprint) {
                        echo '<a href="' . esc_url(get_edit_post_link($blueprint_id)) . '">' . esc_html($blueprint->post_title) . '</a>';
                    } else {
                        echo '<em>' . __('Blueprint not found', 'ai-page-composer') . '</em>';
                    }
                } else {
                    echo '<em>' . __('No blueprint', 'ai-page-composer') . '</em>';
                }
                break;

            case 'duration':
                $metadata = get_post_meta($post_id, 'run_metadata', true);
                $duration_ms = $metadata['total_duration_ms'] ?? 0;
                if ($duration_ms > 0) {
                    $seconds = round($duration_ms / 1000, 1);
                    echo esc_html($seconds . 's');
                } else {
                    echo '<em>' . __('In progress', 'ai-page-composer') . '</em>';
                }
                break;

            case 'cost':
                $costs = get_post_meta($post_id, 'cost_breakdown', true);
                $total_cost = $costs['total_cost_usd'] ?? 0;
                if ($total_cost > 0) {
                    echo '$' . esc_html(number_format($total_cost, 4));
                } else {
                    echo '<em>' . __('No cost', 'ai-page-composer') . '</em>';
                }
                break;

            case 'plugins_used':
                $plugins = get_post_meta($post_id, 'plugin_usage', true);
                if (!empty($plugins) && is_array($plugins)) {
                    $plugin_names = array_keys($plugins);
                    $display_plugins = array_slice($plugin_names, 0, 3);
                    echo esc_html(implode(', ', $display_plugins));
                    if (count($plugin_names) > 3) {
                        echo ' <em>+' . (count($plugin_names) - 3) . ' more</em>';
                    }
                } else {
                    echo '<em>' . __('Core blocks only', 'ai-page-composer') . '</em>';
                }
                break;
        }
    }

    /**
     * Render metadata meta box
     */
    public function render_metadata_meta_box(\WP_Post $post): void {
        $metadata = get_post_meta($post->ID, 'run_metadata', true);
        
        if (empty($metadata)) {
            echo '<p>' . __('No metadata available for this run.', 'ai-page-composer') . '</p>';
            return;
        }

        echo '<table class="form-table">';
        
        $fields = [
            'run_id' => __('Run ID', 'ai-page-composer'),
            'status' => __('Status', 'ai-page-composer'),
            'start_timestamp' => __('Start Time', 'ai-page-composer'),
            'end_timestamp' => __('End Time', 'ai-page-composer'),
            'total_duration_ms' => __('Duration (ms)', 'ai-page-composer'),
            'wordpress_version' => __('WordPress Version', 'ai-page-composer'),
            'plugin_version' => __('Plugin Version', 'ai-page-composer')
        ];

        foreach ($fields as $key => $label) {
            $value = $metadata[$key] ?? __('Not set', 'ai-page-composer');
            
            if ($key === 'total_duration_ms' && is_numeric($value)) {
                $value = $value . ' ms (' . round($value / 1000, 1) . 's)';
            }
            
            echo '<tr>';
            echo '<th scope="row">' . esc_html($label) . '</th>';
            echo '<td>' . esc_html($value) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';

        // Show error if present
        if (isset($metadata['error'])) {
            echo '<h4>' . __('Error Details', 'ai-page-composer') . '</h4>';
            echo '<div class="notice notice-error inline">';
            echo '<p><strong>' . __('Error:', 'ai-page-composer') . '</strong> ' . esc_html($metadata['error']['message']) . '</p>';
            if (!empty($metadata['error']['context'])) {
                echo '<pre>' . esc_html(print_r($metadata['error']['context'], true)) . '</pre>';
            }
            echo '</div>';
        }
    }

    /**
     * Render parameters meta box
     */
    public function render_parameters_meta_box(\WP_Post $post): void {
        $parameters = get_post_meta($post->ID, 'generation_parameters', true);
        
        if (empty($parameters)) {
            echo '<p>' . __('No parameters available for this run.', 'ai-page-composer') . '</p>';
            return;
        }

        echo '<table class="form-table">';
        
        // Prompt
        if (!empty($parameters['prompt'])) {
            echo '<tr>';
            echo '<th scope="row">' . __('Prompt', 'ai-page-composer') . '</th>';
            echo '<td><textarea readonly rows="4" cols="50">' . esc_textarea($parameters['prompt']) . '</textarea></td>';
            echo '</tr>';
        }

        // Other parameters
        $fields = [
            'alpha_weight' => __('Alpha Weight', 'ai-page-composer'),
            'k_value' => __('K Value', 'ai-page-composer'),
            'min_score' => __('Min Score', 'ai-page-composer'),
            'generation_mode' => __('Generation Mode', 'ai-page-composer')
        ];

        foreach ($fields as $key => $label) {
            if (isset($parameters[$key])) {
                echo '<tr>';
                echo '<th scope="row">' . esc_html($label) . '</th>';
                echo '<td>' . esc_html($parameters[$key]) . '</td>';
                echo '</tr>';
            }
        }

        // Namespaces
        if (!empty($parameters['namespaces_versions'])) {
            echo '<tr>';
            echo '<th scope="row">' . __('Namespaces', 'ai-page-composer') . '</th>';
            echo '<td>';
            foreach ($parameters['namespaces_versions'] as $namespace => $enabled) {
                $status = $enabled ? __('Enabled', 'ai-page-composer') : __('Disabled', 'ai-page-composer');
                echo '<div>' . esc_html($namespace) . ': <strong>' . esc_html($status) . '</strong></div>';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }

    /**
     * Render sections meta box
     */
    public function render_sections_meta_box(\WP_Post $post): void {
        $sections = get_post_meta($post->ID, 'sections_log', true);
        
        if (empty($sections) || !is_array($sections)) {
            echo '<p>' . __('No sections logged for this run.', 'ai-page-composer') . '</p>';
            return;
        }

        echo '<div class="ai-run-sections">';
        
        foreach ($sections as $index => $section) {
            echo '<div class="section-log" style="border: 1px solid #ddd; margin: 10px 0; padding: 15px;">';
            echo '<h4>' . sprintf(__('Section %d: %s', 'ai-page-composer'), $index + 1, esc_html($section['section_type'] ?? 'Unknown')) . '</h4>';
            
            echo '<table class="form-table">';
            echo '<tr><th>Block Type:</th><td>' . esc_html($section['block_type_used'] ?? 'Not set') . '</td></tr>';
            echo '<tr><th>Plugin Required:</th><td>' . esc_html($section['plugin_required'] ?? 'Core') . '</td></tr>';
            echo '<tr><th>Processing Time:</th><td>' . esc_html($section['processing_time_ms'] ?? 0) . ' ms</td></tr>';
            echo '<tr><th>Tokens Consumed:</th><td>' . esc_html($section['tokens_consumed'] ?? 0) . '</td></tr>';
            echo '<tr><th>Cost:</th><td>$' . esc_html(number_format($section['cost_usd'] ?? 0, 4)) . '</td></tr>';
            echo '<tr><th>Fallback Applied:</th><td>' . ($section['fallback_applied'] ? __('Yes', 'ai-page-composer') : __('No', 'ai-page-composer')) . '</td></tr>';
            
            if (!empty($section['chunk_ids_used'])) {
                echo '<tr><th>Chunks Used:</th><td>' . esc_html(count($section['chunk_ids_used'])) . ' chunks</td></tr>';
            }
            
            if (!empty($section['warnings'])) {
                echo '<tr><th>Warnings:</th><td>';
                foreach ($section['warnings'] as $warning) {
                    echo '<div style="color: #d63638;">' . esc_html($warning) . '</div>';
                }
                echo '</td></tr>';
            }
            
            if (!empty($section['citations'])) {
                echo '<tr><th>Citations:</th><td>' . esc_html(count($section['citations'])) . ' citations</td></tr>';
            }
            
            echo '</table>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Render plugins meta box
     */
    public function render_plugins_meta_box(\WP_Post $post): void {
        $plugins = get_post_meta($post->ID, 'plugin_usage', true);
        
        if (empty($plugins) || !is_array($plugins)) {
            echo '<p>' . __('No plugin usage data for this run.', 'ai-page-composer') . '</p>';
            return;
        }

        foreach ($plugins as $plugin_name => $plugin_data) {
            echo '<div style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd;">';
            echo '<h4>' . esc_html($plugin_name) . '</h4>';
            echo '<p><strong>' . __('Version:', 'ai-page-composer') . '</strong> ' . esc_html($plugin_data['version'] ?? 'Unknown') . '</p>';
            echo '<p><strong>' . __('Status:', 'ai-page-composer') . '</strong> ' . esc_html($plugin_data['availability_status'] ?? 'Unknown') . '</p>';
            echo '<p><strong>' . __('Usage Count:', 'ai-page-composer') . '</strong> ' . esc_html($plugin_data['usage_count'] ?? 0) . '</p>';
            
            if (!empty($plugin_data['blocks_used'])) {
                echo '<p><strong>' . __('Blocks Used:', 'ai-page-composer') . '</strong></p>';
                echo '<ul>';
                foreach ($plugin_data['blocks_used'] as $block) {
                    echo '<li>' . esc_html($block) . '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        }
    }

    /**
     * Render costs meta box
     */
    public function render_costs_meta_box(\WP_Post $post): void {
        $costs = get_post_meta($post->ID, 'cost_breakdown', true);
        
        if (empty($costs)) {
            echo '<p>' . __('No cost data for this run.', 'ai-page-composer') . '</p>';
            return;
        }

        echo '<table class="form-table">';
        echo '<tr><th>' . __('Total Cost:', 'ai-page-composer') . '</th><td>$' . esc_html(number_format($costs['total_cost_usd'] ?? 0, 4)) . '</td></tr>';
        echo '<tr><th>' . __('OpenAI API:', 'ai-page-composer') . '</th><td>$' . esc_html(number_format($costs['openai_api_cost'] ?? 0, 4)) . '</td></tr>';
        echo '<tr><th>' . __('MVDB Query:', 'ai-page-composer') . '</th><td>$' . esc_html(number_format($costs['mvdb_query_cost'] ?? 0, 4)) . '</td></tr>';
        echo '</table>';

        if (!empty($costs['token_breakdown'])) {
            echo '<h4>' . __('Token Usage', 'ai-page-composer') . '</h4>';
            echo '<table class="form-table">';
            echo '<tr><th>' . __('Input Tokens:', 'ai-page-composer') . '</th><td>' . esc_html(number_format($costs['token_breakdown']['input_tokens'] ?? 0)) . '</td></tr>';
            echo '<tr><th>' . __('Output Tokens:', 'ai-page-composer') . '</th><td>' . esc_html(number_format($costs['token_breakdown']['output_tokens'] ?? 0)) . '</td></tr>';
            echo '<tr><th>' . __('Total Tokens:', 'ai-page-composer') . '</th><td>' . esc_html(number_format($costs['token_breakdown']['total_tokens'] ?? 0)) . '</td></tr>';
            echo '</table>';
        }
    }
}