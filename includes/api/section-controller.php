<?php
/**
 * Section Controller Class - REST API for Section Generation
 * 
 * This file contains the Section_Controller class that handles REST API endpoints
 * for section generation including POST /section and POST /image endpoints.
 * It supports three generation modes (Grounded/Hybrid/Generative), block-aware
 * content generation, and integrated media management.
 *
 * @package AIPageComposer\API
 */

namespace AIPageComposer\API;

use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;
use Exception;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Section Controller class for section generation endpoints
 */
class Section_Controller extends \WP_REST_Controller {

    /**
     * REST API namespace
     *
     * @var string
     */
    protected $namespace = 'ai-composer/v1';

    /**
     * REST API base for section endpoint
     *
     * @var string
     */
    protected $rest_base = 'section';

    /**
     * Section generator instance
     *
     * @var Section_Generator
     */
    private $section_generator;

    /**
     * Image service instance
     *
     * @var Image_Service
     */
    private $image_service;

    /**
     * Cache manager instance
     *
     * @var Cache_Manager
     */
    private $cache_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->section_generator = new Section_Generator();
        $this->image_service = new Image_Service();
        $this->cache_manager = new Cache_Manager();
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // POST /ai-composer/v1/section
        register_rest_route( $this->namespace, '/' . $this->rest_base, [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'generate_section' ],
            'permission_callback' => [ $this, 'check_permissions' ],
            'args' => $this->get_section_args()
        ] );

        // POST /ai-composer/v1/image
        register_rest_route( $this->namespace, '/image', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'process_image' ],
            'permission_callback' => [ $this, 'check_permissions' ],
            'args' => $this->get_image_args()
        ] );
    }

    /**
     * Generate section content
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function generate_section( WP_REST_Request $request ) {
        try {
            $params = $this->extract_section_parameters( $request );
            
            // Check cache first
            $cache_key = $this->generate_cache_key( $params );
            $cached_result = $this->cache_manager->get( $cache_key );
            
            if ( $cached_result ) {
                $cached_result['generation_metadata']['cache_hit'] = true;
                return rest_ensure_response( $cached_result );
            }
            
            // Generate section content
            $result = $this->section_generator->generate( $params );
            
            // Cache the result
            $this->cache_manager->set( $cache_key, $result, 3600 ); // 1 hour
            
            // Track costs
            $this->track_generation_cost( $result['generation_metadata']['cost_usd'] );
            
            $result['generation_metadata']['cache_hit'] = false;
            return rest_ensure_response( $result );
            
        } catch ( Exception $e ) {
            return new \WP_Error(
                'section_generation_failed',
                $e->getMessage(),
                [ 'status' => 500 ]
            );
        }
    }

    /**
     * Process image request
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function process_image( WP_REST_Request $request ) {
        try {
            $params = $this->extract_image_parameters( $request );
            
            $result = $this->image_service->process_image_request( $params );
            
            // Track image generation costs
            if ( isset( $result['metadata']['cost_usd'] ) ) {
                $this->track_generation_cost( $result['metadata']['cost_usd'] );
            }
            
            return rest_ensure_response( $result );
            
        } catch ( Exception $e ) {
            return new \WP_Error(
                'image_processing_failed',
                $e->getMessage(),
                [ 'status' => 500 ]
            );
        }
    }

    /**
     * Extract and sanitize section parameters from request
     *
     * @param WP_REST_Request $request Request object.
     * @return array Sanitized parameters.
     */
    private function extract_section_parameters( WP_REST_Request $request ) {
        return [
            'sectionId' => sanitize_text_field( $request->get_param( 'sectionId' ) ),
            'content_brief' => sanitize_textarea_field( $request->get_param( 'content_brief' ) ),
            'mode' => sanitize_key( $request->get_param( 'mode' ) ?: 'hybrid' ),
            'alpha' => floatval( $request->get_param( 'alpha' ) ?: 0.7 ),
            'block_preferences' => $this->sanitize_block_preferences( $request->get_param( 'block_preferences' ) ),
            'image_requirements' => $this->sanitize_image_requirements( $request->get_param( 'image_requirements' ) ),
            'citation_settings' => $this->sanitize_citation_settings( $request->get_param( 'citation_settings' ) )
        ];
    }

    /**
     * Extract and sanitize image parameters from request
     *
     * @param WP_REST_Request $request Request object.
     * @return array Sanitized parameters.
     */
    private function extract_image_parameters( WP_REST_Request $request ) {
        return [
            'prompt' => sanitize_textarea_field( $request->get_param( 'prompt' ) ),
            'style' => sanitize_key( $request->get_param( 'style' ) ?: 'photographic' ),
            'source' => sanitize_key( $request->get_param( 'source' ) ?: 'generate' ),
            'alt_text' => sanitize_text_field( $request->get_param( 'alt_text' ) ),
            'license_filter' => array_map( 'sanitize_key', (array) $request->get_param( 'license_filter' ) ),
            'dimensions' => $this->sanitize_dimensions( $request->get_param( 'dimensions' ) )
        ];
    }

    /**
     * Sanitize block preferences
     *
     * @param array $preferences Block preferences array.
     * @return array Sanitized preferences.
     */
    private function sanitize_block_preferences( $preferences ) {
        if ( ! is_array( $preferences ) ) {
            return [];
        }

        return [
            'preferred_plugin' => sanitize_key( $preferences['preferred_plugin'] ?? '' ),
            'section_type' => sanitize_key( $preferences['section_type'] ?? '' ),
            'fallback_blocks' => array_map( 'sanitize_text_field', (array) ( $preferences['fallback_blocks'] ?? [] ) ),
            'custom_attributes' => is_array( $preferences['custom_attributes'] ?? null ) 
                ? array_map( 'sanitize_text_field', $preferences['custom_attributes'] ) 
                : []
        ];
    }

    /**
     * Sanitize image requirements
     *
     * @param array $requirements Image requirements array.
     * @return array Sanitized requirements.
     */
    private function sanitize_image_requirements( $requirements ) {
        if ( ! is_array( $requirements ) ) {
            return [];
        }

        return [
            'policy' => sanitize_key( $requirements['policy'] ?? 'optional' ),
            'style' => sanitize_key( $requirements['style'] ?? 'photographic' ),
            'alt_text_required' => filter_var( $requirements['alt_text_required'] ?? true, FILTER_VALIDATE_BOOLEAN ),
            'license_compliance' => array_map( 'sanitize_key', (array) ( $requirements['license_compliance'] ?? [] ) )
        ];
    }

    /**
     * Sanitize citation settings
     *
     * @param array $settings Citation settings array.
     * @return array Sanitized settings.
     */
    private function sanitize_citation_settings( $settings ) {
        if ( ! is_array( $settings ) ) {
            return [];
        }

        return [
            'enabled' => filter_var( $settings['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN ),
            'style' => sanitize_key( $settings['style'] ?? 'inline' ),
            'include_mvdb_refs' => filter_var( $settings['include_mvdb_refs'] ?? true, FILTER_VALIDATE_BOOLEAN ),
            'format' => sanitize_key( $settings['format'] ?? 'text' )
        ];
    }

    /**
     * Sanitize dimensions
     *
     * @param array $dimensions Dimensions array.
     * @return array Sanitized dimensions.
     */
    private function sanitize_dimensions( $dimensions ) {
        if ( ! is_array( $dimensions ) ) {
            return [];
        }

        return [
            'width' => absint( $dimensions['width'] ?? 0 ),
            'height' => absint( $dimensions['height'] ?? 0 ),
            'aspect_ratio' => sanitize_text_field( $dimensions['aspect_ratio'] ?? '' )
        ];
    }

    /**
     * Generate cache key for section parameters
     *
     * @param array $params Section parameters.
     * @return string Cache key.
     */
    private function generate_cache_key( $params ) {
        $cache_data = [
            'sectionId' => $params['sectionId'],
            'content_brief' => $params['content_brief'],
            'mode' => $params['mode'],
            'alpha' => $params['alpha'],
            'block_preferences' => $params['block_preferences']
        ];
        
        return 'ai_section_' . hash( 'sha256', serialize( $cache_data ) );
    }

    /**
     * Track generation cost
     *
     * @param float $cost Cost in USD.
     */
    private function track_generation_cost( $cost ) {
        $daily_costs = get_option( 'ai_composer_daily_costs', 0.0 );
        $monthly_costs = get_option( 'ai_composer_monthly_costs', 0.0 );
        
        update_option( 'ai_composer_daily_costs', $daily_costs + $cost );
        update_option( 'ai_composer_monthly_costs', $monthly_costs + $cost );
        
        // Log cost tracking to database
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_composer_cost_log';
        
        $wpdb->insert(
            $table_name,
            [
                'user_id' => get_current_user_id(),
                'cost_usd' => $cost,
                'operation_type' => 'section_generation',
                'timestamp' => current_time( 'mysql' )
            ],
            [ '%d', '%f', '%s', '%s' ]
        );
    }

    /**
     * Get section endpoint arguments
     *
     * @return array Endpoint arguments.
     */
    private function get_section_args() {
        return [
            'sectionId' => [
                'required' => true,
                'type' => 'string',
                'description' => __( 'Section identifier from outline', 'ai-page-composer' ),
                'validate_callback' => function( $param ) {
                    return is_string( $param ) && ! empty( $param );
                }
            ],
            'content_brief' => [
                'required' => true,
                'type' => 'string',
                'description' => __( 'Content generation brief', 'ai-page-composer' ),
                'validate_callback' => function( $param ) {
                    return is_string( $param ) && strlen( $param ) >= 10;
                }
            ],
            'mode' => [
                'required' => false,
                'type' => 'string',
                'default' => 'hybrid',
                'enum' => [ 'grounded', 'hybrid', 'generative' ],
                'description' => __( 'Generation mode', 'ai-page-composer' )
            ],
            'alpha' => [
                'required' => false,
                'type' => 'number',
                'default' => 0.7,
                'minimum' => 0.0,
                'maximum' => 1.0,
                'description' => __( 'Hybrid mode alpha weight', 'ai-page-composer' )
            ],
            'block_preferences' => [
                'required' => false,
                'type' => 'object',
                'description' => __( 'Block type preferences', 'ai-page-composer' )
            ],
            'image_requirements' => [
                'required' => false,
                'type' => 'object',
                'description' => __( 'Image generation settings', 'ai-page-composer' )
            ],
            'citation_settings' => [
                'required' => false,
                'type' => 'object',
                'description' => __( 'Citation configuration', 'ai-page-composer' )
            ]
        ];
    }

    /**
     * Get image endpoint arguments
     *
     * @return array Endpoint arguments.
     */
    private function get_image_args() {
        return [
            'prompt' => [
                'required' => true,
                'type' => 'string',
                'description' => __( 'Image description/search query', 'ai-page-composer' ),
                'validate_callback' => function( $param ) {
                    return is_string( $param ) && ! empty( $param );
                }
            ],
            'style' => [
                'required' => false,
                'type' => 'string',
                'default' => 'photographic',
                'enum' => [ 'photographic', 'illustration', 'abstract', 'minimalist' ],
                'description' => __( 'Image style preference', 'ai-page-composer' )
            ],
            'source' => [
                'required' => false,
                'type' => 'string',
                'default' => 'generate',
                'enum' => [ 'generate', 'search', 'upload' ],
                'description' => __( 'Generation source', 'ai-page-composer' )
            ],
            'alt_text' => [
                'required' => false,
                'type' => 'string',
                'description' => __( 'Custom alt text', 'ai-page-composer' )
            ],
            'license_filter' => [
                'required' => false,
                'type' => 'array',
                'description' => __( 'Acceptable license types', 'ai-page-composer' )
            ],
            'dimensions' => [
                'required' => false,
                'type' => 'object',
                'description' => __( 'Image size requirements', 'ai-page-composer' )
            ]
        ];
    }

    /**
     * Check permissions for REST API access
     *
     * @return bool True if user has permissions.
     */
    public function check_permissions() {
        return current_user_can( 'edit_posts' );
    }
}