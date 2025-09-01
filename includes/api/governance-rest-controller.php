<?php
/**
 * Governance REST Controller - REST API endpoints for governance operations
 *
 * Provides comprehensive REST API endpoints for AI generation run governance
 * including history retrieval, diff generation, and re-run functionality.
 *
 * @package AIPageComposer\API
 * @since 1.0.0
 */

namespace AIPageComposer\API;

use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Governance REST Controller Class
 * 
 * Handles REST API endpoints for governance functionality including
 * run history, diff generation, and re-run operations.
 */
class Governance_REST_Controller extends \WP_REST_Controller {

    /**
     * Governance Controller instance
     *
     * @var Governance_Controller
     */
    private Governance_Controller $governance_controller;

    /**
     * Namespace for the REST API
     *
     * @var string
     */
    protected $namespace = 'ai-composer/v1';

    /**
     * Rest base for the controller
     *
     * @var string
     */
    protected $rest_base = 'governance';

    /**
     * Constructor
     *
     * @param Governance_Controller $governance_controller Governance controller instance
     */
    public function __construct(Governance_Controller $governance_controller) {
        $this->governance_controller = $governance_controller;
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void {
        // GET /ai-composer/v1/governance/runs - Get paginated list of runs
        register_rest_route($this->namespace, '/' . $this->rest_base . '/runs', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_runs'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => $this->get_runs_args()
            ]
        ]);

        // GET /ai-composer/v1/governance/runs/{run_id} - Get specific run details
        register_rest_route($this->namespace, '/' . $this->rest_base . '/runs/(?P<run_id>[a-zA-Z0-9_]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_run_details'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'run_id' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => __('Run ID to retrieve', 'ai-page-composer'),
                        'sanitize_callback' => 'sanitize_text_field'
                    ]
                ]
            ]
        ]);

        // POST /ai-composer/v1/governance/runs/{run_id}/diff - Generate diff
        register_rest_route($this->namespace, '/' . $this->rest_base . '/runs/(?P<run_id>[a-zA-Z0-9_]+)/diff', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generate_diff'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => $this->get_diff_args()
            ]
        ]);

        // POST /ai-composer/v1/governance/runs/{run_id}/rerun - Execute re-run
        register_rest_route($this->namespace, '/' . $this->rest_base . '/runs/(?P<run_id>[a-zA-Z0-9_]+)/rerun', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'execute_rerun'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => $this->get_rerun_args()
            ]
        ]);

        // POST /ai-composer/v1/governance/runs/{run_id}/preview-rerun - Preview re-run
        register_rest_route($this->namespace, '/' . $this->rest_base . '/runs/(?P<run_id>[a-zA-Z0-9_]+)/preview-rerun', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'preview_rerun'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => $this->get_rerun_args()
            ]
        ]);

        // DELETE /ai-composer/v1/governance/runs/{run_id} - Delete run
        register_rest_route($this->namespace, '/' . $this->rest_base . '/runs/(?P<run_id>[a-zA-Z0-9_]+)', [
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_run'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'run_id' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => __('Run ID to delete', 'ai-page-composer'),
                        'sanitize_callback' => 'sanitize_text_field'
                    ]
                ]
            ]
        ]);

        // GET /ai-composer/v1/governance/statistics - Get governance statistics
        register_rest_route($this->namespace, '/' . $this->rest_base . '/statistics', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_statistics'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        // GET /ai-composer/v1/governance/export - Export runs to CSV
        register_rest_route($this->namespace, '/' . $this->rest_base . '/export', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'export_runs'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => $this->get_export_args()
            ]
        ]);
    }

    /**
     * Get runs with filtering and pagination
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_runs(WP_REST_Request $request): WP_REST_Response {
        try {
            $filters = [
                'page' => $request->get_param('page') ?? 1,
                'per_page' => $request->get_param('per_page') ?? 20,
                'status' => $request->get_param('status') ?? 'all',
                'date_from' => $request->get_param('date_from') ?? '',
                'date_to' => $request->get_param('date_to') ?? '',
                'blueprint_id' => $request->get_param('blueprint_id') ?? 0,
                'user_id' => $request->get_param('user_id') ?? 0
            ];

            $result = $this->governance_controller->get_history($filters);

            return new \WP_REST_Response($result, 200);

        } catch (\Exception $e) {
            return new \WP_Error(
                'governance_error',
                sprintf(__('Failed to retrieve runs: %s', 'ai-page-composer'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Get detailed run information
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_run_details(WP_REST_Request $request): WP_REST_Response {
        $run_id = $request->get_param('run_id');

        try {
            $run_details = $this->governance_controller->get_run_details($run_id);

            if (!$run_details) {
                return new \WP_Error(
                    'run_not_found',
                    __('Run not found', 'ai-page-composer'),
                    ['status' => 404]
                );
            }

            return new \WP_REST_Response([
                'run_details' => $run_details,
                'regeneration_capability' => $run_details['regeneration_capability'] ?? []
            ], 200);

        } catch (\Exception $e) {
            return new \WP_Error(
                'governance_error',
                sprintf(__('Failed to retrieve run details: %s', 'ai-page-composer'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Generate diff between runs
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function generate_diff(WP_REST_Request $request): WP_REST_Response {
        $run_id = $request->get_param('run_id');
        $compare_to = $request->get_param('compare_to') ?? 'current';
        $diff_options = $request->get_param('diff_options') ?? [];

        try {
            $diff_result = $this->governance_controller->generate_diff($run_id, $compare_to, $diff_options);

            if (isset($diff_result['error'])) {
                return new \WP_Error(
                    $diff_result['error_code'] ?? 'diff_error',
                    $diff_result['error'],
                    ['status' => 400]
                );
            }

            return new \WP_REST_Response([
                'diff_result' => $diff_result,
                'visualization_data' => $diff_result['visualization_data'] ?? []
            ], 200);

        } catch (\Exception $e) {
            return new \WP_Error(
                'governance_error',
                sprintf(__('Failed to generate diff: %s', 'ai-page-composer'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Execute re-run
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function execute_rerun(WP_REST_Request $request): WP_REST_Response {
        $run_id = $request->get_param('run_id');
        $rerun_options = $request->get_param('rerun_options') ?? [];
        $parameter_overrides = $request->get_param('parameter_overrides') ?? [];

        try {
            $result = $this->governance_controller->rerun_generation($run_id, $rerun_options, $parameter_overrides);

            if (!$result['success']) {
                return new \WP_Error(
                    $result['error_code'] ?? 'rerun_error',
                    $result['error'],
                    ['status' => 400]
                );
            }

            return new \WP_REST_Response([
                'rerun_result' => $result['rerun_result'],
                'diff_preview' => $result['diff_preview'] ?? []
            ], 200);

        } catch (\Exception $e) {
            return new \WP_Error(
                'governance_error',
                sprintf(__('Failed to execute re-run: %s', 'ai-page-composer'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Preview re-run without executing
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function preview_rerun(WP_REST_Request $request): WP_REST_Response {
        $run_id = $request->get_param('run_id');
        $rerun_options = $request->get_param('rerun_options') ?? [];
        $parameter_overrides = $request->get_param('parameter_overrides') ?? [];

        try {
            $result = $this->governance_controller->preview_rerun($run_id, $rerun_options, $parameter_overrides);

            if (!$result['success']) {
                return new \WP_Error(
                    'preview_error',
                    $result['error'],
                    ['status' => 400]
                );
            }

            return new WP_REST_Response($result['preview'], 200);

        } catch (\Exception $e) {
            return new \WP_Error(
                'governance_error',
                sprintf(__('Failed to preview re-run: %s', 'ai-page-composer'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Delete a run
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function delete_run(WP_REST_Request $request): WP_REST_Response {
        $run_id = $request->get_param('run_id');

        try {
            $success = $this->governance_controller->delete_run($run_id);

            if (!$success) {
                return new \WP_Error(
                    'delete_failed',
                    __('Failed to delete run', 'ai-page-composer'),
                    ['status' => 400]
                );
            }

            return new \WP_REST_Response([
                'message' => __('Run deleted successfully', 'ai-page-composer'),
                'deleted_run_id' => $run_id
            ], 200);

        } catch (\Exception $e) {
            return new \WP_Error(
                'governance_error',
                sprintf(__('Failed to delete run: %s', 'ai-page-composer'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Get governance statistics
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_statistics(WP_REST_Request $request): WP_REST_Response {
        try {
            $statistics = $this->governance_controller->get_statistics();

            return new \WP_REST_Response([
                'statistics' => $statistics,
                'generated_at' => current_time('c')
            ], 200);

        } catch (\Exception $e) {
            return new \WP_Error(
                'governance_error',
                sprintf(__('Failed to retrieve statistics: %s', 'ai-page-composer'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Export runs to CSV
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function export_runs(WP_REST_Request $request): WP_REST_Response {
        try {
            $filters = [
                'status' => $request->get_param('status') ?? 'all',
                'date_from' => $request->get_param('date_from') ?? '',
                'date_to' => $request->get_param('date_to') ?? '',
                'blueprint_id' => $request->get_param('blueprint_id') ?? 0,
                'user_id' => $request->get_param('user_id') ?? 0
            ];

            $csv_content = $this->governance_controller->export_runs($filters);

            return new \WP_REST_Response([
                'csv_content' => $csv_content,
                'filename' => 'ai-runs-' . date('Y-m-d') . '.csv',
                'export_timestamp' => current_time('c')
            ], 200);

        } catch (\Exception $e) {
            return new \WP_Error(
                'governance_error',
                sprintf(__('Failed to export runs: %s', 'ai-page-composer'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Check permissions for governance operations
     *
     * @param WP_REST_Request $request REST request object
     * @return bool Permission status
     */
    public function check_permission(WP_REST_Request $request): bool {
        return current_user_can('manage_options');
    }

    /**
     * Get arguments for runs endpoint
     *
     * @return array Arguments schema
     */
    private function get_runs_args(): array {
        return [
            'page' => [
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
                'description' => __('Page number for pagination', 'ai-page-composer'),
                'sanitize_callback' => 'absint'
            ],
            'per_page' => [
                'type' => 'integer',
                'default' => 20,
                'minimum' => 1,
                'maximum' => 100,
                'description' => __('Number of results per page', 'ai-page-composer'),
                'sanitize_callback' => 'absint'
            ],
            'status' => [
                'type' => 'string',
                'default' => 'all',
                'enum' => ['all', 'completed', 'failed', 'in_progress'],
                'description' => __('Filter by run status', 'ai-page-composer'),
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'date_from' => [
                'type' => 'string',
                'description' => __('Filter runs from date (ISO 8601)', 'ai-page-composer'),
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'date_to' => [
                'type' => 'string',
                'description' => __('Filter runs to date (ISO 8601)', 'ai-page-composer'),
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'blueprint_id' => [
                'type' => 'integer',
                'description' => __('Filter by blueprint ID', 'ai-page-composer'),
                'sanitize_callback' => 'absint'
            ],
            'user_id' => [
                'type' => 'integer',
                'description' => __('Filter by user ID', 'ai-page-composer'),
                'sanitize_callback' => 'absint'
            ]
        ];
    }

    /**
     * Get arguments for diff endpoint
     *
     * @return array Arguments schema
     */
    private function get_diff_args(): array {
        return [
            'run_id' => [
                'required' => true,
                'type' => 'string',
                'description' => __('Original run ID', 'ai-page-composer'),
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'compare_to' => [
                'required' => true,
                'type' => 'string',
                'description' => __('Target to compare to (run ID or "current")', 'ai-page-composer'),
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'diff_options' => [
                'type' => 'object',
                'description' => __('Diff visualization options', 'ai-page-composer'),
                'properties' => [
                    'include_content_changes' => [
                        'type' => 'boolean',
                        'default' => true
                    ],
                    'include_parameter_changes' => [
                        'type' => 'boolean',
                        'default' => true
                    ],
                    'include_plugin_changes' => [
                        'type' => 'boolean',
                        'default' => true
                    ],
                    'include_cost_analysis' => [
                        'type' => 'boolean',
                        'default' => true
                    ],
                    'highlight_significant_changes' => [
                        'type' => 'boolean',
                        'default' => true
                    ]
                ]
            ]
        ];
    }

    /**
     * Get arguments for rerun endpoints
     *
     * @return array Arguments schema
     */
    private function get_rerun_args(): array {
        return [
            'run_id' => [
                'required' => true,
                'type' => 'string',
                'description' => __('Run ID to re-run', 'ai-page-composer'),
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'rerun_options' => [
                'type' => 'object',
                'description' => __('Re-run configuration options', 'ai-page-composer'),
                'properties' => [
                    'preserve_plugin_preferences' => [
                        'type' => 'boolean',
                        'default' => true
                    ],
                    'fallback_on_missing_plugins' => [
                        'type' => 'boolean',
                        'default' => true
                    ],
                    'update_namespace_versions' => [
                        'type' => 'boolean',
                        'default' => false
                    ],
                    'maintain_cost_limits' => [
                        'type' => 'boolean',
                        'default' => true
                    ],
                    'notification_on_changes' => [
                        'type' => 'boolean',
                        'default' => true
                    ]
                ]
            ],
            'parameter_overrides' => [
                'type' => 'object',
                'description' => __('Parameter overrides for re-run', 'ai-page-composer'),
                'properties' => [
                    'prompt' => ['type' => 'string'],
                    'alpha_weight' => ['type' => 'number'],
                    'k_value' => ['type' => 'integer'],
                    'min_score' => ['type' => 'number'],
                    'generation_mode' => ['type' => 'string'],
                    'namespaces_versions' => ['type' => 'object']
                ]
            ]
        ];
    }

    /**
     * Get arguments for export endpoint
     *
     * @return array Arguments schema
     */
    private function get_export_args(): array {
        return [
            'status' => [
                'type' => 'string',
                'default' => 'all',
                'enum' => ['all', 'completed', 'failed', 'in_progress'],
                'description' => __('Filter by run status', 'ai-page-composer'),
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'date_from' => [
                'type' => 'string',
                'description' => __('Filter runs from date (ISO 8601)', 'ai-page-composer'),
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'date_to' => [
                'type' => 'string',
                'description' => __('Filter runs to date (ISO 8601)', 'ai-page-composer'),
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'blueprint_id' => [
                'type' => 'integer',
                'description' => __('Filter by blueprint ID', 'ai-page-composer'),
                'sanitize_callback' => 'absint'
            ],
            'user_id' => [
                'type' => 'integer',
                'description' => __('Filter by user ID', 'ai-page-composer'),
                'sanitize_callback' => 'absint'
            ]
        ];
    }
}