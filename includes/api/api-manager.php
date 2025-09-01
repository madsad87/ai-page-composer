<?php
/**
 * API Manager Class - REST API and AJAX Endpoints
 * 
 * This file handles REST API endpoints and AJAX handlers for the AI Page Composer plugin.
 * It provides endpoints for plugin detection refresh, API status checks, and other
 * interactive functionality.
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\API;

use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Admin\Settings_Manager;
use AIPageComposer\API\Outline_Controller;
use AIPageComposer\API\MVDB_Manager;
use AIPageComposer\API\Section_Controller;
use AIPageComposer\API\Assembly_Manager;
use AIPageComposer\API\Preview_Manager;
use AIPageComposer\API\Draft_Creator;
use AIPageComposer\API\Governance_Controller;
use AIPageComposer\API\Governance_REST_Controller;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * API Manager class for REST and AJAX endpoints
 */
class API_Manager {

    /**
     * Settings manager instance
     *
     * @var Settings_Manager
     */
    private $settings_manager;

    /**
     * Outline controller instance
     *
     * @var Outline_Controller
     */
    private $outline_controller;

    /**
     * MVDB manager instance
     *
     * @var MVDB_Manager
     */
    private $mvdb_manager;

    /**
     * Section controller instance
     *
     * @var Section_Controller
     */
    private $section_controller;

    /**
     * Assembly manager instance
     *
     * @var Assembly_Manager
     */
    private $assembly_manager;

    /**
     * Preview manager instance
     *
     * @var Preview_Manager
     */
    private $preview_manager;

    /**
     * Draft creator instance
     *
     * @var Draft_Creator
     */
    private $draft_creator;

    /**
     * Governance controller instance
     *
     * @var Governance_Controller
     */
    private $governance_controller;

    /**
     * Governance REST controller instance
     *
     * @var Governance_REST_Controller
     */
    private $governance_rest_controller;

    /**
     * Constructor
     *
     * @param Settings_Manager $settings_manager Settings manager instance.
     */
    public function __construct( $settings_manager ) {
        $this->settings_manager = $settings_manager;
        
        try {
            // Initialize MVDB Manager with proper error handling
            $this->mvdb_manager = new MVDB_Manager( $this->settings_manager );
        } catch ( \Exception $e ) {
            // MVDB manager failed to initialize - log error but continue
            if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                error_log( '[AI Page Composer] MVDB Manager initialization failed: ' . $e->getMessage() );
            }
            $this->mvdb_manager = null;
        }
        
        try {
            // Initialize Section Controller
            $this->section_controller = new Section_Controller();
        } catch ( \Exception $e ) {
            // Section controller failed to initialize - log error but continue
            if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                error_log( '[AI Page Composer] Section Controller initialization failed: ' . $e->getMessage() );
            }
            $this->section_controller = null;
        }
        
        try {
            // Initialize Assembly components
            $this->assembly_manager = new Assembly_Manager();
            $this->preview_manager = new Preview_Manager();
            $this->draft_creator = new Draft_Creator();
        } catch ( \Exception $e ) {
            // Assembly components failed to initialize - log error but continue
            if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                error_log( '[AI Page Composer] Assembly components initialization failed: ' . $e->getMessage() );
            }
            $this->assembly_manager = null;
            $this->preview_manager = null;
            $this->draft_creator = null;
        }
        
        try {
            // Initialize Governance System
            $this->governance_controller = new Governance_Controller();
            $this->governance_controller->init();
            $this->governance_rest_controller = new Governance_REST_Controller($this->governance_controller);
        } catch ( \Exception $e ) {
            // Governance system failed to initialize - log error but continue
            if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                error_log( '[AI Page Composer] Governance system initialization failed: ' . $e->getMessage() );
            }
            $this->governance_controller = null;
            $this->governance_rest_controller = null;
        }
        
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'wp_ajax_ai_composer_refresh_plugins', array( $this, 'ajax_refresh_plugins' ) );
        add_action( 'wp_ajax_ai_composer_check_api_status', array( $this, 'ajax_check_api_status' ) );
        add_action( 'wp_ajax_ai_composer_get_plugin_status', array( $this, 'ajax_get_plugin_status' ) );
        add_action( 'wp_ajax_ai_composer_refresh_cost_stats', array( $this, 'ajax_refresh_cost_stats' ) );
        
        // Section generation AJAX handlers
        add_action( 'wp_ajax_ai_composer_test_section_generation', array( $this, 'ajax_test_section_generation' ) );
        add_action( 'wp_ajax_ai_composer_clear_section_cache', array( $this, 'ajax_clear_section_cache' ) );
        add_action( 'wp_ajax_ai_composer_get_cache_stats', array( $this, 'ajax_get_cache_stats' ) );
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route( 'ai-composer/v1', '/status', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_plugin_status' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'ai-composer/v1', '/settings', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_settings' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'ai-composer/v1', '/settings', array(
            'methods' => 'POST',
            'callback' => array( $this, 'update_settings' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        // Register MVDB retrieve endpoint
        register_rest_route( 'ai-composer/v1', '/retrieve', array(
            'methods' => 'POST',
            'callback' => array( $this, 'retrieve_context' ),
            'permission_callback' => array( $this, 'check_permissions' ),
            'args' => $this->get_retrieve_args(),
        ) );

        // Register MVDB admin endpoints for monitoring
        register_rest_route( 'ai-composer/v1', '/mvdb/stats', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_mvdb_error_stats' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'ai-composer/v1', '/mvdb/logs', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_mvdb_error_logs' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        // Register outline controller routes
        $this->register_outline_routes();
        
        // Register section controller routes
        $this->register_section_routes();
        
        // Register assembly controller routes
        $this->register_assembly_routes();
        
        // Register governance routes
        $this->register_governance_routes();
    }

    /**
     * Register assembly controller routes
     */
    private function register_assembly_routes() {
        // Assemble endpoint
        register_rest_route( 'ai-composer/v1', '/assemble', array(
            'methods' => 'POST',
            'callback' => array( $this, 'assemble_content' ),
            'permission_callback' => array( $this, 'check_permissions' ),
            'args' => $this->get_assemble_args(),
        ) );

        // Preview endpoint
        register_rest_route( 'ai-composer/v1', '/preview', array(
            'methods' => 'POST',
            'callback' => array( $this, 'generate_preview' ),
            'permission_callback' => array( $this, 'check_permissions' ),
            'args' => $this->get_preview_args(),
        ) );

        // Create draft endpoint
        register_rest_route( 'ai-composer/v1', '/create-draft', array(
            'methods' => 'POST',
            'callback' => array( $this, 'create_draft' ),
            'permission_callback' => array( $this, 'check_permissions' ),
            'args' => $this->get_draft_args(),
        ) );

        // Detected plugins endpoint
        register_rest_route( 'ai-composer/v1', '/detected-plugins', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_detected_plugins' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );
    }

    /**
     * Register governance controller routes
     */
    private function register_governance_routes() {
        if ($this->governance_rest_controller) {
            $this->governance_rest_controller->register_routes();
        }
    }

    /**
     * AJAX handler for refreshing plugin detection
     */
    public function ajax_refresh_plugins() {
        Security_Helper::verify_ajax_request();

        // Clear plugin detection cache
        delete_transient( 'ai_composer_detected_plugins' );
        delete_transient( 'ai_composer_plugins_scanned' );

        // Get fresh plugin data
        $block_preferences = new \AIPageComposer\Admin\Block_Preferences();
        $detected_plugins = $block_preferences->scan_active_plugins();

        // Cache the results
        set_transient( 'ai_composer_detected_plugins', $detected_plugins, HOUR_IN_SECONDS );

        wp_send_json_success( array(
            'message' => __( 'Plugin detection refreshed successfully.', 'ai-page-composer' ),
            'plugins_count' => count( $detected_plugins ),
            'plugins' => $detected_plugins,
        ) );
    }

    /**
     * AJAX handler for checking API status
     */
    public function ajax_check_api_status() {
        Security_Helper::verify_ajax_request();

        $settings = $this->settings_manager->get_all_settings();
        $api_settings = $settings['api_settings'] ?? array();

        $status_results = array(
            'OpenAI' => $this->check_openai_status( $api_settings['openai_api_key'] ?? '' ),
            'MVDB' => $this->check_mvdb_status( $api_settings['mvdb_api_key'] ?? '' ),
            'Image API' => $this->check_image_api_status( $api_settings['image_api_key'] ?? '' ),
        );

        wp_send_json_success( $status_results );
    }

    /**
     * AJAX handler for getting plugin status
     */
    public function ajax_get_plugin_status() {
        Security_Helper::verify_ajax_request();

        $status = $this->get_plugin_status_data();

        wp_send_json_success( $status );
    }

    /**
     * AJAX handler for refreshing cost statistics
     */
    public function ajax_refresh_cost_stats() {
        Security_Helper::verify_ajax_request();

        $daily_costs = get_option( 'ai_composer_daily_costs', 0.0 );
        $monthly_costs = get_option( 'ai_composer_monthly_costs', 0.0 );

        // In a real implementation, you would fetch updated costs from API providers
        // For now, we'll just return the current values
        
        wp_send_json_success( array(
            'daily_costs' => number_format( $daily_costs, 2 ),
            'monthly_costs' => number_format( $monthly_costs, 2 ),
            'last_updated' => current_time( 'mysql' ),
        ) );
    }

    /**
     * Get plugin status for REST API
     *
     * @return array Plugin status data
     */
    public function get_plugin_status() {
        return rest_ensure_response( $this->get_plugin_status_data() );
    }

    /**
     * Get settings for REST API
     *
     * @return array Plugin settings
     */
    public function get_settings() {
        $settings = $this->settings_manager->get_all_settings();
        
        // Remove sensitive data
        if ( isset( $settings['api_settings'] ) ) {
            foreach ( $settings['api_settings'] as $key => $value ) {
                if ( strpos( $key, 'api_key' ) !== false ) {
                    $settings['api_settings'][ $key ] = ! empty( $value ) ? '***masked***' : '';
                }
            }
        }

        return rest_ensure_response( $settings );
    }

    /**
     * Update settings via REST API
     *
     * @param WP_REST_Request $request Request object.
     * @return array Update result
     */
    public function update_settings( $request ) {
        $new_settings = $request->get_param( 'settings' );
        
        if ( empty( $new_settings ) || ! is_array( $new_settings ) ) {
            return new \WP_Error( 'invalid_settings', __( 'Invalid settings data', 'ai-page-composer' ), array( 'status' => 400 ) );
        }

        // Sanitize settings using the Settings Manager
        $sanitized_settings = $this->settings_manager->sanitize_settings( $new_settings );

        // Update the settings
        $updated = update_option( Settings_Manager::OPTION_NAME, $sanitized_settings );

        if ( $updated ) {
            return rest_ensure_response( array(
                'success' => true,
                'message' => __( 'Settings updated successfully', 'ai-page-composer' ),
            ) );
        } else {
            return new \WP_Error( 'update_failed', __( 'Failed to update settings', 'ai-page-composer' ), array( 'status' => 500 ) );
        }
    }

    /**
     * Get plugin status data
     *
     * @return array Status data
     */
    private function get_plugin_status_data() {
        $settings = $this->settings_manager->get_all_settings();
        $api_settings = $settings['api_settings'] ?? array();
        
        $block_preferences = new \AIPageComposer\Admin\Block_Preferences();
        $detected_plugins = $block_preferences->get_detected_plugins();

        return array(
            'api_configured' => ! empty( $api_settings['openai_api_key'] ) && ! empty( $api_settings['mvdb_api_key'] ),
            'daily_costs' => get_option( 'ai_composer_daily_costs', 0.0 ),
            'monthly_costs' => get_option( 'ai_composer_monthly_costs', 0.0 ),
            'plugin_version' => AI_PAGE_COMPOSER_VERSION,
            'detected_plugins_count' => count( $detected_plugins ),
            'active_plugins_count' => count( array_filter( $detected_plugins, function( $plugin ) {
                return $plugin['active'] ?? false;
            } ) ),
            'wordpress_version' => get_bloginfo( 'version' ),
            'php_version' => PHP_VERSION,
        );
    }

    /**
     * Check OpenAI API status
     *
     * @param string $api_key OpenAI API key.
     * @return bool API status
     */
    private function check_openai_status( $api_key ) {
        if ( empty( $api_key ) ) {
            return false;
        }

        // In a real implementation, you would make an actual API call
        // For now, we'll just check if the key format looks valid
        return strlen( $api_key ) > 10 && strpos( $api_key, 'sk-' ) === 0;
    }

    /**
     * Check MVDB API status
     *
     * @param string $api_key MVDB API key.
     * @return bool API status
     */
    private function check_mvdb_status( $api_key ) {
        if ( empty( $api_key ) ) {
            return false;
        }

        // In a real implementation, you would make an actual API call
        // For now, we'll just check if the key is provided
        return strlen( $api_key ) > 5;
    }

    /**
     * Check Image API status
     *
     * @param string $api_key Image API key.
     * @return bool API status
     */
    private function check_image_api_status( $api_key ) {
        if ( empty( $api_key ) ) {
            return false; // Optional API, so false when empty is normal
        }

        // In a real implementation, you would make an actual API call
        // For now, we'll just check if the key is provided
        return strlen( $api_key ) > 5;
    }

    /**
     * Log API request for debugging
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Request data.
     * @param string $response API response.
     */
    private function log_api_request( $endpoint, $data, $response ) {
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( sprintf(
                '[AI Page Composer API] %s - Request: %s, Response: %s',
                $endpoint,
                wp_json_encode( $data ),
                $response
            ) );
        }
    }

    /**
     * Set outline controller instance
     *
     * @param Outline_Controller $outline_controller Outline controller instance.
     */
    public function set_outline_controller( $outline_controller ) {
        $this->outline_controller = $outline_controller;
    }

    /**
     * Register outline REST API routes
     */
    private function register_outline_routes() {
        if ( $this->outline_controller ) {
            $this->outline_controller->register_routes();
        }
    }

    /**
     * Retrieve context from MVDB
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function retrieve_context( $request ) {
        try {
            // Check if MVDB manager is available
            if ( ! $this->mvdb_manager ) {
                return new \WP_Error(
                    'mvdb_unavailable',
                    __( 'MVDB service is not available. Please check your configuration.', 'ai-page-composer' ),
                    array( 'status' => 503 )
                );
            }

            // Extract parameters from request
            $params = array(
                'sectionId' => $request->get_param( 'sectionId' ),
                'query' => $request->get_param( 'query' ),
                'namespaces' => $request->get_param( 'namespaces' ) ?? array( 'content' ),
                'k' => $request->get_param( 'k' ) ?? 10,
                'min_score' => $request->get_param( 'min_score' ) ?? 0.5,
                'filters' => $request->get_param( 'filters' ) ?? array()
            );

            // Retrieve context using MVDB manager
            $result = $this->mvdb_manager->retrieve_context( $params );

            // Log successful retrieval
            $this->log_api_request( 'retrieve', $params, 'success' );

            return rest_ensure_response( $result );

        } catch ( \Exception $e ) {
            // Log error and return appropriate error response
            $this->log_api_request( 'retrieve', $request->get_params(), $e->getMessage() );
            
            // Determine appropriate error code and message
            $error_code = 'retrieval_failed';
            $error_message = $e->getMessage();
            $status_code = 500;
            
            if ( strpos( $error_message, 'authentication' ) !== false ) {
                $error_code = 'authentication_failed';
                $status_code = 401;
            } elseif ( strpos( $error_message, 'rate limit' ) !== false ) {
                $error_code = 'rate_limit_exceeded';
                $status_code = 429;
            } elseif ( strpos( $error_message, 'timeout' ) !== false ) {
                $error_code = 'request_timeout';
                $status_code = 408;
            }
            
            return new \WP_Error(
                $error_code,
                $error_message,
                array( 'status' => $status_code )
            );
        }
    }

    /**
     * Get retrieve endpoint arguments schema
     *
     * @return array Arguments schema.
     */
    private function get_retrieve_args() {
        return array(
            'sectionId' => array(
                'description' => __( 'Section identifier from outline', 'ai-page-composer' ),
                'type' => 'string',
                'required' => true,
                'pattern' => '^section-[a-zA-Z0-9_-]+$',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => array( $this, 'validate_section_id' ),
            ),
            'query' => array(
                'description' => __( 'Search query text (10-500 characters)', 'ai-page-composer' ),
                'type' => 'string',
                'required' => true,
                'minLength' => 10,
                'maxLength' => 500,
                'sanitize_callback' => 'sanitize_textarea_field',
                'validate_callback' => array( $this, 'validate_query' ),
            ),
            'namespaces' => array(
                'description' => __( 'Target namespaces', 'ai-page-composer' ),
                'type' => 'array',
                'items' => array(
                    'type' => 'string',
                    'enum' => array( 'content', 'products', 'docs', 'knowledge' )
                ),
                'default' => array( 'content' ),
                'sanitize_callback' => array( $this, 'sanitize_namespaces' ),
            ),
            'k' => array(
                'description' => __( 'Number of results to retrieve (1-50)', 'ai-page-composer' ),
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 50,
                'default' => 10,
                'sanitize_callback' => 'absint',
            ),
            'min_score' => array(
                'description' => __( 'Minimum relevance score (0.0-1.0)', 'ai-page-composer' ),
                'type' => 'number',
                'minimum' => 0.0,
                'maximum' => 1.0,
                'default' => 0.5,
                'sanitize_callback' => array( $this, 'sanitize_score' ),
            ),
            'filters' => array(
                'description' => __( 'Additional filtering criteria', 'ai-page-composer' ),
                'type' => 'object',
                'properties' => array(
                    'post_type' => array(
                        'type' => 'array',
                        'items' => array( 'type' => 'string' )
                    ),
                    'date_range' => array(
                        'type' => 'object',
                        'properties' => array(
                            'start' => array( 'type' => 'string', 'format' => 'date' ),
                            'end' => array( 'type' => 'string', 'format' => 'date' )
                        )
                    ),
                    'language' => array( 'type' => 'string', 'minLength' => 2, 'maxLength' => 2 ),
                    'license' => array(
                        'type' => 'array',
                        'items' => array(
                            'type' => 'string',
                            'enum' => array( 'CC-BY', 'CC-BY-SA', 'CC-BY-NC', 'public-domain', 'fair-use', 'commercial' )
                        )
                    ),
                    'author' => array(
                        'type' => 'array',
                        'items' => array( 'type' => 'integer' )
                    ),
                    'exclude_ids' => array(
                        'type' => 'array',
                        'items' => array( 'type' => 'integer' )
                    )
                ),
                'default' => array(),
                'sanitize_callback' => array( $this, 'sanitize_filters' ),
            )
        );
    }

    /**
     * Validate section ID
     *
     * @param string $value Section ID value.
     * @return bool True if valid.
     */
    public function validate_section_id( $value ) {
        return preg_match( '/^section-[a-zA-Z0-9_-]+$/', $value ) === 1;
    }

    /**
     * Validate query length
     *
     * @param string $value Query value.
     * @return bool True if valid.
     */
    public function validate_query( $value ) {
        $length = strlen( $value );
        return $length >= 10 && $length <= 500;
    }

    /**
     * Sanitize namespaces array
     *
     * @param array $value Namespaces array.
     * @return array Sanitized namespaces.
     */
    public function sanitize_namespaces( $value ) {
        if ( ! is_array( $value ) ) {
            return array( 'content' );
        }

        $allowed = array( 'content', 'products', 'docs', 'knowledge' );
        $sanitized = array();

        foreach ( $value as $namespace ) {
            $clean = sanitize_key( $namespace );
            if ( in_array( $clean, $allowed, true ) ) {
                $sanitized[] = $clean;
            }
        }

        return ! empty( $sanitized ) ? $sanitized : array( 'content' );
    }

    /**
     * Sanitize score value
     *
     * @param mixed $value Score value.
     * @return float Sanitized score.
     */
    public function sanitize_score( $value ) {
        $score = floatval( $value );
        return max( 0.0, min( 1.0, $score ) );
    }

    /**
     * Sanitize filters object
     *
     * @param mixed $value Filters value.
     * @return array Sanitized filters.
     */
    public function sanitize_filters( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $sanitized = array();

        // Sanitize post_type filter
        if ( ! empty( $value['post_type'] ) && is_array( $value['post_type'] ) ) {
            $sanitized['post_type'] = array_map( 'sanitize_key', $value['post_type'] );
        }

        // Sanitize date_range filter
        if ( ! empty( $value['date_range'] ) && is_array( $value['date_range'] ) ) {
            $date_range = array();
            if ( ! empty( $value['date_range']['start'] ) ) {
                $date_range['start'] = sanitize_text_field( $value['date_range']['start'] );
            }
            if ( ! empty( $value['date_range']['end'] ) ) {
                $date_range['end'] = sanitize_text_field( $value['date_range']['end'] );
            }
            if ( ! empty( $date_range ) ) {
                $sanitized['date_range'] = $date_range;
            }
        }

        // Sanitize other filters
        if ( ! empty( $value['language'] ) ) {
            $sanitized['language'] = sanitize_key( $value['language'] );
        }

        if ( ! empty( $value['license'] ) && is_array( $value['license'] ) ) {
            $sanitized['license'] = array_map( 'sanitize_key', $value['license'] );
        }

        if ( ! empty( $value['author'] ) && is_array( $value['author'] ) ) {
            $sanitized['author'] = array_map( 'absint', $value['author'] );
        }

        if ( ! empty( $value['exclude_ids'] ) && is_array( $value['exclude_ids'] ) ) {
            $sanitized['exclude_ids'] = array_map( 'absint', $value['exclude_ids'] );
        }

        return $sanitized;
    }

    /**
     * Get MVDB error statistics (admin endpoint)
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error Response object.
     */
    public function get_mvdb_error_stats( $request ) {
        if ( ! $this->mvdb_manager ) {
            return new \WP_Error(
                'mvdb_unavailable',
                __( 'MVDB service is not available.', 'ai-page-composer' ),
                array( 'status' => 503 )
            );
        }

        $timeframe = $request->get_param( 'timeframe' ) ?: 'day';
        $error_stats = $this->mvdb_manager->get_error_statistics( $timeframe );
        $cache_stats = $this->mvdb_manager->get_cache_stats();

        return rest_ensure_response( array(
            'error_statistics' => $error_stats,
            'cache_statistics' => $cache_stats,
            'timestamp' => current_time( 'mysql' )
        ) );
    }

    /**
     * Get MVDB error logs (admin endpoint)
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error Response object.
     */
    public function get_mvdb_error_logs( $request ) {
        if ( ! $this->mvdb_manager ) {
            return new \WP_Error(
                'mvdb_unavailable',
                __( 'MVDB service is not available.', 'ai-page-composer' ),
                array( 'status' => 503 )
            );
        }

        $filters = array(
            'severity' => $request->get_param( 'severity' ),
            'category' => $request->get_param( 'category' ),
            'since' => $request->get_param( 'since' ),
            'limit' => absint( $request->get_param( 'limit' ) ) ?: 50
        );

        $error_logs = $this->mvdb_manager->get_error_logs( array_filter( $filters ) );

        return rest_ensure_response( $error_logs );
    }

    /**
     * Register section controller routes
     */
    private function register_section_routes() {
        if ( $this->section_controller ) {
            $this->section_controller->register_routes();
        }
    }
    
    /**
     * AJAX handler for testing section generation
     */
    public function ajax_test_section_generation() {
        Security_Helper::verify_ajax_request();
        
        try {
            $test_data = $_POST['test_data'] ?? [];
            
            if ( empty( $test_data ) ) {
                wp_send_json_error( __( 'No test data provided', 'ai-page-composer' ) );
                return;
            }
            
            if ( ! $this->section_controller ) {
                wp_send_json_error( __( 'Section controller not available', 'ai-page-composer' ) );
                return;
            }
            
            // Create mock request object
            $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/section' );
            foreach ( $test_data as $key => $value ) {
                $request->set_param( $key, $value );
            }
            
            // Generate section
            $response = $this->section_controller->generate_section( $request );
            
            if ( is_wp_error( $response ) ) {
                wp_send_json_error( $response->get_error_message() );
            } else {
                wp_send_json_success( $response->get_data() );
            }
            
        } catch ( \Exception $e ) {
            wp_send_json_error( 'Test generation failed: ' . $e->getMessage() );
        }
    }
    
    /**
     * AJAX handler for clearing section cache
     */
    public function ajax_clear_section_cache() {
        Security_Helper::verify_ajax_request();
        
        try {
            $cache_manager = new \AIPageComposer\API\Cache_Manager();
            $result = $cache_manager->clear_all_cache();
            
            if ( $result ) {
                wp_send_json_success( __( 'Section cache cleared successfully', 'ai-page-composer' ) );
            } else {
                wp_send_json_error( __( 'Failed to clear section cache', 'ai-page-composer' ) );
            }
            
        } catch ( \Exception $e ) {
            wp_send_json_error( 'Cache clear failed: ' . $e->getMessage() );
        }
    }
    
    /**
     * AJAX handler for getting cache statistics
     */
    public function ajax_get_cache_stats() {
        Security_Helper::verify_ajax_request();
        
        try {
            $cache_manager = new \AIPageComposer\API\Cache_Manager();
            $stats = $cache_manager->get_cache_health();
            
            wp_send_json_success( $stats );
            
        } catch ( \Exception $e ) {
            wp_send_json_error( 'Failed to get cache stats: ' . $e->getMessage() );
        }
    }

    /**
     * Assembly content endpoint
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Assembly result.
     */
    public function assemble_content( $request ) {
        try {
            if ( ! $this->assembly_manager ) {
                return new \WP_Error(
                    'assembly_unavailable',
                    __( 'Assembly manager not available', 'ai-page-composer' ),
                    array( 'status' => 503 )
                );
            }

            $sections = $request->get_param( 'sections' );
            $blueprint_id = $request->get_param( 'blueprint_id' );
            $assembly_options = $request->get_param( 'assembly_options' ) ?: array();

            $assembly_result = $this->assembly_manager->assemble_sections( $sections, array(
                'blueprint_id' => $blueprint_id,
                'options' => $assembly_options
            ) );

            return rest_ensure_response( $assembly_result );

        } catch ( \Exception $e ) {
            return new \WP_Error(
                'assembly_failed',
                $e->getMessage(),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Generate preview endpoint
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Preview result.
     */
    public function generate_preview( $request ) {
        try {
            if ( ! $this->preview_manager ) {
                return new \WP_Error(
                    'preview_unavailable',
                    __( 'Preview manager not available', 'ai-page-composer' ),
                    array( 'status' => 503 )
                );
            }

            $assembled_content = $request->get_param( 'assembled_content' );
            $preview_options = $request->get_param( 'preview_options' ) ?: array();

            $preview_result = $this->preview_manager->generate_preview( $assembled_content, $preview_options );

            return rest_ensure_response( $preview_result );

        } catch ( \Exception $e ) {
            return new \WP_Error(
                'preview_failed',
                $e->getMessage(),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Create draft endpoint
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Draft creation result.
     */
    public function create_draft( $request ) {
        try {
            if ( ! $this->draft_creator ) {
                return new \WP_Error(
                    'draft_creator_unavailable',
                    __( 'Draft creator not available', 'ai-page-composer' ),
                    array( 'status' => 503 )
                );
            }

            $assembled_content = $request->get_param( 'assembled_content' );
            $post_meta = $request->get_param( 'post_meta' );
            $seo_data = $request->get_param( 'seo_data' ) ?: array();
            $taxonomies = $request->get_param( 'taxonomies' ) ?: array();

            $draft_result = $this->draft_creator->create_draft( array(
                'content' => $assembled_content,
                'meta' => $post_meta,
                'seo' => $seo_data,
                'taxonomies' => $taxonomies
            ) );

            return rest_ensure_response( $draft_result );

        } catch ( \Exception $e ) {
            return new \WP_Error(
                'draft_creation_failed',
                $e->getMessage(),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Get detected plugins endpoint
     *
     * @return WP_REST_Response Detected plugins data.
     */
    public function get_detected_plugins() {
        try {
            if ( ! $this->assembly_manager ) {
                return new \WP_Error(
                    'assembly_unavailable',
                    __( 'Assembly manager not available', 'ai-page-composer' ),
                    array( 'status' => 503 )
                );
            }

            $block_detector = new \AIPageComposer\API\Block_Detector();
            $plugin_info = $block_detector->get_plugin_information();

            return rest_ensure_response( $plugin_info );

        } catch ( \Exception $e ) {
            return new \WP_Error(
                'detection_failed',
                $e->getMessage(),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Check permissions for REST API
     *
     * @return bool True if user has permissions
     */
    public function check_permissions() {
        return Security_Helper::current_user_can();
    }

    /**
     * Get assemble endpoint args
     *
     * @return array Endpoint arguments.
     */
    private function get_assemble_args() {
        return array(
            'sections' => array(
                'required' => true,
                'type' => 'array',
                'description' => __( 'Generated sections to assemble', 'ai-page-composer' )
            ),
            'blueprint_id' => array(
                'type' => 'integer',
                'description' => __( 'Blueprint ID for preferences', 'ai-page-composer' )
            ),
            'assembly_options' => array(
                'type' => 'object',
                'description' => __( 'Assembly configuration options', 'ai-page-composer' )
            )
        );
    }

    /**
     * Get preview endpoint args
     *
     * @return array Endpoint arguments.
     */
    private function get_preview_args() {
        return array(
            'assembled_content' => array(
                'required' => true,
                'type' => 'object',
                'description' => __( 'Assembled content from /assemble', 'ai-page-composer' )
            ),
            'preview_options' => array(
                'type' => 'object',
                'description' => __( 'Preview configuration', 'ai-page-composer' )
            )
        );
    }

    /**
     * Get draft endpoint args
     *
     * @return array Endpoint arguments.
     */
    private function get_draft_args() {
        return array(
            'assembled_content' => array(
                'required' => true,
                'type' => 'object',
                'description' => __( 'Assembled content with blocks', 'ai-page-composer' )
            ),
            'post_meta' => array(
                'required' => true,
                'type' => 'object',
                'description' => __( 'Post metadata and configuration', 'ai-page-composer' )
            ),
            'seo_data' => array(
                'type' => 'object',
                'description' => __( 'SEO optimization data', 'ai-page-composer' )
            ),
            'taxonomies' => array(
                'type' => 'object',
                'description' => __( 'Category and tag assignments', 'ai-page-composer' )
            )
        );
    }
}