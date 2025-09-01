<?php
/**
 * Governance Controller - Central coordinator for governance system
 *
 * Coordinates all governance components including run logging, history management,
 * diff viewing, and re-run functionality for the AI Page Composer.
 *
 * @package AIPageComposer\API
 * @since 1.0.0
 */

namespace AIPageComposer\API;

use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;

/**
 * Governance Controller Class
 * 
 * Central controller that orchestrates all governance functionality
 * and provides a unified interface for governance operations.
 */
class Governance_Controller {

    /**
     * Run Logger instance
     *
     * @var Run_Logger
     */
    public Run_Logger $run_logger;

    /**
     * History Manager instance
     *
     * @var History_Manager
     */
    public History_Manager $history_manager;

    /**
     * Diff Viewer instance
     *
     * @var Diff_Viewer
     */
    public Diff_Viewer $diff_viewer;

    /**
     * ReRun Manager instance
     *
     * @var ReRun_Manager
     */
    public ReRun_Manager $rerun_manager;

    /**
     * AI Run Post Type instance
     *
     * @var AI_Run_Post_Type
     */
    public AI_Run_Post_Type $ai_run_post_type;

    /**
     * Current active run ID
     *
     * @var string|null
     */
    private ?string $active_run_id = null;

    /**
     * Initialize the governance system
     */
    public function init(): void {
        // Initialize components
        $this->init_components();

        // Hook into WordPress
        $this->setup_hooks();

        // Initialize post type
        $this->ai_run_post_type->init();
    }

    /**
     * Initialize all governance components
     */
    private function init_components(): void {
        // Initialize core components
        $this->run_logger = new Run_Logger();
        $this->history_manager = new History_Manager();
        $this->diff_viewer = new Diff_Viewer($this->history_manager);
        $this->rerun_manager = new ReRun_Manager(
            $this->history_manager,
            $this->diff_viewer,
            $this->run_logger
        );
        $this->ai_run_post_type = new AI_Run_Post_Type();

        // Set up dependencies if block classes are available
        $this->setup_block_dependencies();
    }

    /**
     * Setup WordPress hooks
     */
    private function setup_hooks(): void {
        add_action('init', [$this, 'register_governance_hooks']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('wp_ajax_ai_composer_governance_action', [$this, 'handle_ajax_request']);
    }

    /**
     * Register governance-specific hooks
     */
    public function register_governance_hooks(): void {
        // Hook into generation process
        add_action('ai_composer_generation_start', [$this, 'on_generation_start'], 10, 2);
        add_action('ai_composer_section_generated', [$this, 'on_section_generated'], 10, 2);
        add_action('ai_composer_generation_complete', [$this, 'on_generation_complete'], 10, 2);
        add_action('ai_composer_generation_failed', [$this, 'on_generation_failed'], 10, 2);

        // Hook into plugin settings
        add_filter('ai_composer_admin_menu_pages', [$this, 'add_governance_menu_pages']);
    }

    /**
     * Admin initialization
     */
    public function admin_init(): void {
        // Add governance capabilities
        $this->setup_governance_capabilities();

        // Enqueue admin assets for governance pages
        if ($this->is_governance_page()) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_governance_assets']);
        }
    }

    /**
     * Start a new governance run
     *
     * @param array $parameters Generation parameters
     * @return string Run ID
     */
    public function start_run(array $parameters): string {
        // Validate parameters
        $validated_params = $this->validate_generation_parameters($parameters);
        
        if (is_wp_error($validated_params)) {
            throw new \InvalidArgumentException($validated_params->get_error_message());
        }

        // Start run logging
        $this->active_run_id = $this->run_logger->start_run($validated_params);

        // Trigger action for other components
        do_action('ai_composer_governance_run_started', $this->active_run_id, $validated_params);

        return $this->active_run_id;
    }

    /**
     * Log section generation
     *
     * @param array $section_data Section generation data
     */
    public function log_section(array $section_data): void {
        if ($this->active_run_id) {
            $this->run_logger->log_section_generation($section_data);
        }
    }

    /**
     * Log plugin usage
     *
     * @param array $plugin_data Plugin usage data
     */
    public function log_plugin_usage(array $plugin_data): void {
        if ($this->active_run_id) {
            $this->run_logger->log_plugin_usage($plugin_data);
        }
    }

    /**
     * Complete the current run
     *
     * @param array $final_data Final run data
     */
    public function complete_run(array $final_data = []): void {
        if ($this->active_run_id) {
            $this->run_logger->complete_run($final_data);
            $this->active_run_id = null;

            // Trigger action
            do_action('ai_composer_governance_run_completed', $final_data);
        }
    }

    /**
     * Log run error
     *
     * @param string $error_message Error message
     * @param array $error_context Error context
     */
    public function log_error(string $error_message, array $error_context = []): void {
        if ($this->active_run_id) {
            $this->run_logger->log_error($error_message, $error_context);
            $this->active_run_id = null;

            // Trigger action
            do_action('ai_composer_governance_run_failed', $error_message, $error_context);
        }
    }

    /**
     * Get run history with filters
     *
     * @param array $filters Filter parameters
     * @return array History data
     */
    public function get_history(array $filters = []): array {
        return $this->history_manager->get_runs($filters);
    }

    /**
     * Get detailed run information
     *
     * @param string $run_id Run ID
     * @return array|null Run details
     */
    public function get_run_details(string $run_id): ?array {
        return $this->history_manager->get_run_details($run_id);
    }

    /**
     * Generate diff between runs
     *
     * @param string $original_run_id Original run ID
     * @param string $compare_to Comparison target
     * @param array $diff_options Diff options
     * @return array Diff result
     */
    public function generate_diff(string $original_run_id, string $compare_to, array $diff_options = []): array {
        return $this->diff_viewer->generate_diff($original_run_id, $compare_to, $diff_options);
    }

    /**
     * Re-run a previous generation
     *
     * @param string $run_id Original run ID
     * @param array $rerun_options Re-run options
     * @param array $parameter_overrides Parameter overrides
     * @return array Re-run result
     */
    public function rerun_generation(string $run_id, array $rerun_options = [], array $parameter_overrides = []): array {
        return $this->rerun_manager->rerun_generation($run_id, $rerun_options, $parameter_overrides);
    }

    /**
     * Preview re-run without executing
     *
     * @param string $run_id Original run ID
     * @param array $rerun_options Re-run options
     * @param array $parameter_overrides Parameter overrides
     * @return array Preview result
     */
    public function preview_rerun(string $run_id, array $rerun_options = [], array $parameter_overrides = []): array {
        return $this->rerun_manager->preview_rerun($run_id, $rerun_options, $parameter_overrides);
    }

    /**
     * Get governance statistics
     *
     * @return array Statistics
     */
    public function get_statistics(): array {
        return $this->history_manager->get_statistics();
    }

    /**
     * Export runs to CSV
     *
     * @param array $filters Export filters
     * @return string CSV content
     */
    public function export_runs(array $filters = []): string {
        return $this->history_manager->export_runs_csv($filters);
    }

    /**
     * Delete a run
     *
     * @param string $run_id Run ID to delete
     * @return bool Success status
     */
    public function delete_run(string $run_id): bool {
        if (!current_user_can('manage_options')) {
            return false;
        }

        return $this->history_manager->delete_run($run_id);
    }

    /**
     * Handle generation start event
     *
     * @param array $parameters Generation parameters
     * @param string $context Generation context
     */
    public function on_generation_start(array $parameters, string $context = ''): void {
        $this->start_run($parameters);
    }

    /**
     * Handle section generated event
     *
     * @param array $section_data Section data
     * @param string $context Generation context
     */
    public function on_section_generated(array $section_data, string $context = ''): void {
        $this->log_section($section_data);
    }

    /**
     * Handle generation complete event
     *
     * @param array $final_data Final generation data
     * @param string $context Generation context
     */
    public function on_generation_complete(array $final_data, string $context = ''): void {
        $this->complete_run($final_data);
    }

    /**
     * Handle generation failed event
     *
     * @param string $error_message Error message
     * @param array $error_context Error context
     */
    public function on_generation_failed(string $error_message, array $error_context = []): void {
        $this->log_error($error_message, $error_context);
    }

    /**
     * Add governance menu pages
     *
     * @param array $pages Existing menu pages
     * @return array Updated menu pages
     */
    public function add_governance_menu_pages(array $pages): array {
        $pages['governance'] = [
            'page_title' => __('AI Run History', 'ai-page-composer'),
            'menu_title' => __('Run History', 'ai-page-composer'),
            'capability' => 'manage_options',
            'menu_slug' => 'ai-composer-governance',
            'callback' => [$this, 'render_governance_page'],
            'position' => 30
        ];

        return $pages;
    }

    /**
     * Render governance page
     */
    public function render_governance_page(): void {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ai-page-composer'));
        }

        // Get current action
        $action = $_GET['action'] ?? 'history';
        $run_id = $_GET['run_id'] ?? '';

        switch ($action) {
            case 'view':
                $this->render_run_details_page($run_id);
                break;
            case 'diff':
                $this->render_diff_page($run_id);
                break;
            case 'export':
                $this->handle_export_request();
                break;
            default:
                $this->render_history_page();
                break;
        }
    }

    /**
     * Handle AJAX requests
     */
    public function handle_ajax_request(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_composer_governance')) {
            wp_die(__('Security check failed', 'ai-page-composer'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-page-composer'));
        }

        $action = $_POST['governance_action'] ?? '';

        switch ($action) {
            case 'generate_diff':
                $this->handle_ajax_generate_diff();
                break;
            case 'preview_rerun':
                $this->handle_ajax_preview_rerun();
                break;
            case 'execute_rerun':
                $this->handle_ajax_execute_rerun();
                break;
            case 'delete_run':
                $this->handle_ajax_delete_run();
                break;
            default:
                wp_send_json_error(['message' => __('Unknown action', 'ai-page-composer')]);
                break;
        }
    }

    /**
     * Setup block dependencies if available
     */
    private function setup_block_dependencies(): void {
        // Try to get block detector and resolver from global plugin instance
        global $ai_page_composer_plugin;
        
        if (isset($ai_page_composer_plugin->api)) {
            // Set block detector if available
            if (property_exists($ai_page_composer_plugin->api, 'block_detector')) {
                $this->rerun_manager->set_block_detector($ai_page_composer_plugin->api->block_detector);
            }
            
            // Set block resolver if available
            if (property_exists($ai_page_composer_plugin->api, 'block_resolver')) {
                $this->rerun_manager->set_block_resolver($ai_page_composer_plugin->api->block_resolver);
            }
        }
    }

    /**
     * Validate generation parameters
     *
     * @param array $parameters Parameters to validate
     * @return array|\WP_Error Validated parameters or error
     */
    private function validate_generation_parameters(array $parameters) {
        $errors = new \WP_Error();

        // Required parameters
        if (empty($parameters['prompt'])) {
            $errors->add('missing_prompt', __('Prompt is required', 'ai-page-composer'));
        }

        // Validate numeric parameters
        if (isset($parameters['alpha_weight'])) {
            $alpha = floatval($parameters['alpha_weight']);
            if ($alpha < 0 || $alpha > 1) {
                $errors->add('invalid_alpha', __('Alpha weight must be between 0 and 1', 'ai-page-composer'));
            }
        }

        if (isset($parameters['k_value'])) {
            $k = intval($parameters['k_value']);
            if ($k < 1 || $k > 100) {
                $errors->add('invalid_k', __('K value must be between 1 and 100', 'ai-page-composer'));
            }
        }

        if ($errors->has_errors()) {
            return $errors;
        }

        return $parameters;
    }

    /**
     * Setup governance capabilities
     */
    private function setup_governance_capabilities(): void {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_ai_composer_governance');
        }
    }

    /**
     * Check if current page is a governance page
     *
     * @return bool
     */
    private function is_governance_page(): bool {
        $screen = get_current_screen();
        return $screen && strpos($screen->id, 'ai-composer-governance') !== false;
    }

    /**
     * Enqueue governance assets
     */
    public function enqueue_governance_assets(): void {
        wp_enqueue_script(
            'ai-composer-governance',
            plugin_dir_url(__FILE__) . '../../assets/js/governance.js',
            ['jquery', 'wp-api'],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'ai-composer-governance',
            plugin_dir_url(__FILE__) . '../../assets/css/governance.css',
            [],
            '1.0.0'
        );

        wp_localize_script('ai-composer-governance', 'aiComposerGovernance', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_composer_governance'),
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this run?', 'ai-page-composer'),
                'processing' => __('Processing...', 'ai-page-composer'),
                'error' => __('An error occurred', 'ai-page-composer')
            ]
        ]);
    }

    /**
     * Render history page
     */
    private function render_history_page(): void {
        $filters = [
            'page' => $_GET['paged'] ?? 1,
            'per_page' => $_GET['per_page'] ?? 20,
            'status' => $_GET['status'] ?? 'all'
        ];

        $history_data = $this->get_history($filters);
        $statistics = $this->get_statistics();

        include plugin_dir_path(__FILE__) . '../../templates/admin/governance/history-page.php';
    }

    /**
     * Render run details page
     *
     * @param string $run_id Run ID
     */
    private function render_run_details_page(string $run_id): void {
        $run_details = $this->get_run_details($run_id);
        
        if (!$run_details) {
            wp_die(__('Run not found', 'ai-page-composer'));
        }

        include plugin_dir_path(__FILE__) . '../../templates/admin/governance/run-details.php';
    }

    /**
     * Render diff page
     *
     * @param string $run_id Run ID
     */
    private function render_diff_page(string $run_id): void {
        $compare_to = $_GET['compare_to'] ?? 'current';
        $diff_result = $this->generate_diff($run_id, $compare_to);

        include plugin_dir_path(__FILE__) . '../../templates/admin/governance/diff-page.php';
    }

    /**
     * Handle export request
     */
    private function handle_export_request(): void {
        $filters = $_GET;
        $csv_content = $this->export_runs($filters);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="ai-runs-' . date('Y-m-d') . '.csv"');
        echo $csv_content;
        exit;
    }

    /**
     * Handle AJAX diff generation
     */
    private function handle_ajax_generate_diff(): void {
        $run_id = $_POST['run_id'] ?? '';
        $compare_to = $_POST['compare_to'] ?? 'current';
        $diff_options = $_POST['diff_options'] ?? [];

        $result = $this->generate_diff($run_id, $compare_to, $diff_options);
        wp_send_json_success($result);
    }

    /**
     * Handle AJAX rerun preview
     */
    private function handle_ajax_preview_rerun(): void {
        $run_id = $_POST['run_id'] ?? '';
        $rerun_options = $_POST['rerun_options'] ?? [];
        $parameter_overrides = $_POST['parameter_overrides'] ?? [];

        $result = $this->preview_rerun($run_id, $rerun_options, $parameter_overrides);
        wp_send_json_success($result);
    }

    /**
     * Handle AJAX rerun execution
     */
    private function handle_ajax_execute_rerun(): void {
        $run_id = $_POST['run_id'] ?? '';
        $rerun_options = $_POST['rerun_options'] ?? [];
        $parameter_overrides = $_POST['parameter_overrides'] ?? [];

        $result = $this->rerun_generation($run_id, $rerun_options, $parameter_overrides);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Handle AJAX run deletion
     */
    private function handle_ajax_delete_run(): void {
        $run_id = $_POST['run_id'] ?? '';
        $success = $this->delete_run($run_id);

        if ($success) {
            wp_send_json_success(['message' => __('Run deleted successfully', 'ai-page-composer')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete run', 'ai-page-composer')]);
        }
    }
}