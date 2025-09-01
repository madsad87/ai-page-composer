<?php
/**
 * Outline Controller Class - REST API Endpoint for Content Outline Generation
 * 
 * This file contains the Outline_Controller class that handles the /ai-composer/v1/outline
 * REST API endpoint. It accepts blueprint configurations and user inputs to generate
 * structured content outlines with block preferences for the AI Page Composer plugin.
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\API;

use AIPageComposer\API\Outline_Generator;
use AIPageComposer\Blueprints\Blueprint_Manager;
use AIPageComposer\Admin\Block_Preferences;
use AIPageComposer\Utils\Security_Helper;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Outline Controller class for REST API outline generation
 */
class Outline_Controller extends \WP_REST_Controller {

    /**
     * REST API namespace
     *
     * @var string
     */
    protected $namespace = 'ai-composer/v1';

    /**
     * REST API base
     *
     * @var string
     */
    protected $rest_base = 'outline';

    /**
     * Outline generator instance
     *
     * @var Outline_Generator
     */
    private $outline_generator;

    /**
     * Blueprint manager instance
     *
     * @var Blueprint_Manager
     */
    private $blueprint_manager;

    /**
     * Block preferences instance
     *
     * @var Block_Preferences
     */
    private $block_preferences;

    /**
     * Constructor
     *
     * @param Blueprint_Manager $blueprint_manager Blueprint manager instance.
     * @param Block_Preferences $block_preferences Block preferences instance.
     */
    public function __construct( $blueprint_manager, $block_preferences ) {
        $this->blueprint_manager = $blueprint_manager;
        $this->block_preferences = $block_preferences;
        $this->outline_generator = new Outline_Generator( $this->blueprint_manager, $this->block_preferences );
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'generate_outline' ),
                'permission_callback' => array( $this, 'generate_outline_permissions_check' ),
                'args' => $this->get_outline_args(),
            )
        );
    }

    /**
     * Generate content outline
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function generate_outline( WP_REST_Request $request ) {
        try {
            // Extract and validate parameters
            $params = $this->extract_parameters( $request );

            // Validate blueprint exists and is accessible
            $blueprint = $this->validate_blueprint( $params['blueprint_id'] );

            // Generate outline using the outline generator
            $outline_data = $this->outline_generator->generate( $params, $blueprint );

            // Apply block preferences to sections
            $enhanced_outline = $this->apply_block_preferences( $outline_data );

            // Log successful generation
            $this->log_outline_generation( $params, $enhanced_outline );

            return rest_ensure_response( $enhanced_outline );

        } catch ( Exception $e ) {
            error_log( '[AI Composer] Outline generation failed: ' . $e->getMessage() );

            return new \WP_Error(
                'outline_generation_failed',
                $e->getMessage(),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Check permissions for outline generation
     *
     * @return bool True if user has permissions.
     */
    public function generate_outline_permissions_check() {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Get outline generation arguments schema
     *
     * @return array Arguments schema.
     */
    public function get_outline_args() {
        return array(
            'blueprint_id' => array(
                'description' => __( 'Blueprint post ID', 'ai-page-composer' ),
                'type' => 'integer',
                'required' => true,
                'minimum' => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => array( $this, 'validate_blueprint_id' ),
            ),
            'brief' => array(
                'description' => __( 'Content brief (10-2000 characters)', 'ai-page-composer' ),
                'type' => 'string',
                'required' => true,
                'minLength' => 10,
                'maxLength' => 2000,
                'sanitize_callback' => 'sanitize_textarea_field',
                'validate_callback' => array( $this, 'validate_brief' ),
            ),
            'audience' => array(
                'description' => __( 'Target audience description', 'ai-page-composer' ),
                'type' => 'string',
                'required' => false,
                'maxLength' => 500,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'tone' => array(
                'description' => __( 'Content tone', 'ai-page-composer' ),
                'type' => 'string',
                'required' => false,
                'enum' => array( 'professional', 'casual', 'technical', 'friendly', 'authoritative' ),
                'default' => 'professional',
                'sanitize_callback' => 'sanitize_key',
            ),
            'mvdb_params' => array(
                'description' => __( 'MVDB retrieval parameters', 'ai-page-composer' ),
                'type' => 'object',
                'required' => false,
                'properties' => array(
                    'namespaces' => array(
                        'type' => 'array',
                        'items' => array( 'type' => 'string' ),
                        'default' => array(),
                    ),
                    'k' => array(
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 50,
                        'default' => 10,
                    ),
                    'min_score' => array(
                        'type' => 'number',
                        'minimum' => 0.0,
                        'maximum' => 1.0,
                        'default' => 0.5,
                    ),
                    'filters' => array(
                        'type' => 'object',
                        'default' => array(),
                    ),
                ),
            ),
            'alpha' => array(
                'description' => __( 'Hybrid mode alpha value (0.0-1.0)', 'ai-page-composer' ),
                'type' => 'number',
                'required' => false,
                'minimum' => 0.0,
                'maximum' => 1.0,
                'default' => 0.7,
                'validate_callback' => array( $this, 'validate_alpha_value' ),
            ),
        );
    }

    /**
     * Extract and validate parameters from request
     *
     * @param WP_REST_Request $request Request object.
     * @return array Validated parameters.
     * @throws Exception If validation fails.
     */
    private function extract_parameters( WP_REST_Request $request ) {
        $params = array(
            'blueprint_id' => absint( $request->get_param( 'blueprint_id' ) ),
            'brief' => sanitize_textarea_field( $request->get_param( 'brief' ) ),
            'audience' => sanitize_text_field( $request->get_param( 'audience' ) ),
            'tone' => sanitize_key( $request->get_param( 'tone' ) ),
            'mvdb_params' => $request->get_param( 'mvdb_params' ),
            'alpha' => $this->validate_alpha_value( $request->get_param( 'alpha' ) ),
        );

        // Set defaults
        if ( empty( $params['tone'] ) ) {
            $params['tone'] = 'professional';
        }

        if ( empty( $params['alpha'] ) ) {
            $params['alpha'] = 0.7;
        }

        if ( empty( $params['mvdb_params'] ) || ! is_array( $params['mvdb_params'] ) ) {
            $params['mvdb_params'] = array(
                'namespaces' => array(),
                'k' => 10,
                'min_score' => 0.5,
                'filters' => array(),
            );
        }

        // Validate required parameters
        if ( empty( $params['blueprint_id'] ) ) {
            throw new Exception( __( 'Blueprint ID is required', 'ai-page-composer' ) );
        }

        if ( empty( $params['brief'] ) ) {
            throw new Exception( __( 'Brief is required', 'ai-page-composer' ) );
        }

        return $params;
    }

    /**
     * Validate blueprint exists and is accessible
     *
     * @param int $blueprint_id Blueprint post ID.
     * @return array Blueprint data.
     * @throws Exception If blueprint is invalid.
     */
    private function validate_blueprint( $blueprint_id ) {
        $blueprint_post = get_post( $blueprint_id );

        if ( ! $blueprint_post || 'ai_blueprint' !== $blueprint_post->post_type ) {
            throw new Exception( __( 'Blueprint not found', 'ai-page-composer' ) );
        }

        if ( 'publish' !== $blueprint_post->post_status ) {
            throw new Exception( __( 'Blueprint is not published', 'ai-page-composer' ) );
        }

        // Get blueprint schema data
        $blueprint_data = get_post_meta( $blueprint_id, '_ai_blueprint_schema', true );

        if ( empty( $blueprint_data ) ) {
            throw new Exception( __( 'Blueprint schema data not found', 'ai-page-composer' ) );
        }

        return array(
            'post' => $blueprint_post,
            'schema' => $blueprint_data,
        );
    }

    /**
     * Apply block preferences to outline sections
     *
     * @param array $outline_data Generated outline data.
     * @return array Enhanced outline with block preferences.
     */
    private function apply_block_preferences( $outline_data ) {
        $enhanced_sections = array();

        foreach ( $outline_data['sections'] as $section ) {
            $enhanced_section = $section;

            // Get block preferences for this section type
            $block_preference = $this->block_preferences->get_section_preference( $section['type'] );

            if ( $block_preference ) {
                $enhanced_section['block_preference'] = array(
                    'preferred_plugin' => $block_preference['preferred_plugin'],
                    'primary_block' => $block_preference['primary_block'],
                    'fallback_blocks' => $block_preference['fallback_blocks'],
                    'pattern_preference' => $block_preference['pattern_preference'] ?? '',
                );
            } else {
                // Provide default block preference
                $enhanced_section['block_preference'] = array(
                    'preferred_plugin' => 'core',
                    'primary_block' => $this->get_default_core_block( $section['type'] ),
                    'fallback_blocks' => array( 'core/paragraph', 'core/heading' ),
                    'pattern_preference' => '',
                );
            }

            $enhanced_sections[] = $enhanced_section;
        }

        return array_merge( $outline_data, array( 'sections' => $enhanced_sections ) );
    }

    /**
     * Get default core block for section type
     *
     * @param string $section_type Section type.
     * @return string Core block name.
     */
    private function get_default_core_block( $section_type ) {
        $defaults = array(
            'hero' => 'core/cover',
            'content' => 'core/paragraph',
            'testimonial' => 'core/quote',
            'pricing' => 'core/table',
            'team' => 'core/media-text',
            'faq' => 'core/details',
            'cta' => 'core/buttons',
        );

        return $defaults[ $section_type ] ?? 'core/paragraph';
    }

    /**
     * Validate blueprint ID
     *
     * @param int $value Blueprint ID.
     * @return bool True if valid.
     */
    public function validate_blueprint_id( $value ) {
        return is_numeric( $value ) && $value > 0;
    }

    /**
     * Validate brief content
     *
     * @param string $value Brief content.
     * @return bool True if valid.
     */
    public function validate_brief( $value ) {
        $length = strlen( $value );
        return $length >= 10 && $length <= 2000;
    }

    /**
     * Validate alpha value
     *
     * @param mixed $value Alpha value.
     * @return float Validated alpha value.
     */
    public function validate_alpha_value( $value ) {
        if ( is_null( $value ) ) {
            return 0.7;
        }

        $alpha = floatval( $value );
        return max( 0.0, min( 1.0, $alpha ) );
    }

    /**
     * Log outline generation for audit purposes
     *
     * @param array $params Generation parameters.
     * @param array $outline Generated outline.
     */
    private function log_outline_generation( $params, $outline ) {
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( sprintf(
                '[AI Composer] Outline generated - Blueprint: %d, Mode: %s, Sections: %d, Cost: %s',
                $params['blueprint_id'],
                $outline['mode'] ?? 'unknown',
                count( $outline['sections'] ?? array() ),
                $outline['estimated_cost'] ?? '0.00'
            ) );
        }
    }
}