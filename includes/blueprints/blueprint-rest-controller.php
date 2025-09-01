<?php
/**
 * Blueprint REST Controller - REST API Endpoints for AI Blueprints
 * 
 * This file contains the Blueprint_REST_Controller class that handles REST API
 * endpoints for AI Blueprint operations including validation, preview generation,
 * and blueprint management via the WordPress REST API.
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\Blueprints;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use AIPageComposer\Blueprints\Schema_Processor;
use AIPageComposer\Blueprints\Blueprint_Manager;

/**
 * Blueprint REST Controller class
 */
class Blueprint_REST_Controller extends \WP_REST_Controller {

    /**
     * REST API namespace
     *
     * @var string
     */
    protected $namespace = 'ai-composer/v1';

    /**
     * REST API resource base
     *
     * @var string
     */
    protected $rest_base = 'blueprints';

    /**
     * Schema processor instance
     *
     * @var Schema_Processor
     */
    private $schema_processor;

    /**
     * Blueprint manager instance
     *
     * @var Blueprint_Manager
     */
    private $blueprint_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->schema_processor = new Schema_Processor();
    }

    /**
     * Set blueprint manager instance
     *
     * @param Blueprint_Manager $blueprint_manager Blueprint manager instance.
     */
    public function set_blueprint_manager( $blueprint_manager ) {
        $this->blueprint_manager = $blueprint_manager;
    }

    /**
     * Register the REST API routes
     */
    public function register_routes() {
        // Main blueprints endpoint
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'get_items_permissions_check' ),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array( $this, 'create_item' ),
                    'permission_callback' => array( $this, 'create_item_permissions_check' ),
                ),
            )
        );

        // Individual blueprint endpoint
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array( $this, 'get_item' ),
                    'permission_callback' => array( $this, 'get_item_permissions_check' ),
                ),
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array( $this, 'update_item' ),
                    'permission_callback' => array( $this, 'update_item_permissions_check' ),
                ),
                array(
                    'methods' => WP_REST_Server::DELETABLE,
                    'callback' => array( $this, 'delete_item' ),
                    'permission_callback' => array( $this, 'delete_item_permissions_check' ),
                ),
            )
        );

        // Schema validation endpoint
        register_rest_route(
            $this->namespace,
            '/validate-schema',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'validate_schema' ),
                'permission_callback' => array( $this, 'validate_schema_permissions_check' ),
            )
        );

        // Blueprint preview endpoint
        register_rest_route(
            $this->namespace,
            '/blueprint-preview',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'generate_preview' ),
                'permission_callback' => array( $this, 'generate_preview_permissions_check' ),
            )
        );

        // Detected plugins endpoint
        register_rest_route(
            $this->namespace,
            '/detected-plugins',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_detected_plugins' ),
                'permission_callback' => array( $this, 'get_detected_plugins_permissions_check' ),
            )
        );
    }

    /**
     * Get a collection of blueprints
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_items( $request ) {
        $args = array(
            'post_type' => 'ai_blueprint',
            'post_status' => 'publish',
            'posts_per_page' => $request->get_param( 'per_page' ) ?: 10,
            'paged' => $request->get_param( 'page' ) ?: 1,
        );

        $query = new \WP_Query( $args );
        $blueprints = array();

        foreach ( $query->posts as $post ) {
            $data = $this->prepare_item_for_response( $post, $request );
            $blueprints[] = $this->prepare_response_for_collection( $data );
        }

        return rest_ensure_response( $blueprints );
    }

    /**
     * Get a single blueprint
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_item( $request ) {
        $post = get_post( $request->get_param( 'id' ) );

        if ( empty( $post ) || $post->post_type !== 'ai_blueprint' ) {
            return new \WP_Error(
                'rest_blueprint_invalid_id',
                __( 'Invalid blueprint ID.', 'ai-page-composer' ),
                array( 'status' => 404 )
            );
        }

        $data = $this->prepare_item_for_response( $post, $request );
        return rest_ensure_response( $data );
    }

    /**
     * Validate blueprint schema
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function validate_schema( $request ) {
        $schema_data = json_decode( $request->get_body(), true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error(
                'rest_invalid_json',
                __( 'Invalid JSON data provided.', 'ai-page-composer' ),
                array( 'status' => 400 )
            );
        }

        $validation_result = $this->schema_processor->validate_schema( $schema_data );

        return rest_ensure_response( array(
            'valid' => $validation_result['valid'],
            'errors' => $validation_result['errors'],
            'schema_version' => '1.0.0',
            'validated_at' => current_time( 'mysql' ),
        ) );
    }

    /**
     * Generate blueprint preview
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function generate_preview( $request ) {
        $blueprint_data = json_decode( $request->get_body(), true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error(
                'rest_invalid_json',
                __( 'Invalid JSON data provided.', 'ai-page-composer' ),
                array( 'status' => 400 )
            );
        }

        $preview = $this->generate_blueprint_preview( $blueprint_data );

        return rest_ensure_response( array(
            'preview' => $preview,
            'generated_at' => current_time( 'mysql' ),
        ) );
    }

    /**
     * Get detected block plugins
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response Response object.
     */
    public function get_detected_plugins( $request ) {
        $plugins = array(
            'core' => array(
                'name' => __( 'WordPress Core', 'ai-page-composer' ),
                'active' => true,
                'slug' => 'wordpress-core',
            ),
            'kadence_blocks' => array(
                'name' => __( 'Kadence Blocks', 'ai-page-composer' ),
                'active' => is_plugin_active( 'kadence-blocks/kadence-blocks.php' ),
                'slug' => 'kadence-blocks',
            ),
            'genesis_blocks' => array(
                'name' => __( 'Genesis Blocks', 'ai-page-composer' ),
                'active' => is_plugin_active( 'genesis-blocks/genesis-blocks.php' ),
                'slug' => 'genesis-blocks',
            ),
        );

        return rest_ensure_response( $plugins );
    }

    /**
     * Prepare a single blueprint for response
     *
     * @param WP_Post         $post    Post object.
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function prepare_item_for_response( $post, $request ) {
        $schema_data = get_post_meta( $post->ID, '_ai_blueprint_schema', true );
        $validation_errors = get_post_meta( $post->ID, '_ai_blueprint_validation_errors', true );

        $data = array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'status' => $post->post_status,
            'date' => mysql_to_rfc3339( $post->post_date ),
            'blueprint_data' => $schema_data ?: new \stdClass(),
            'sections_count' => count( $schema_data['sections'] ?? array() ),
            'category' => $schema_data['metadata']['category'] ?? 'custom',
            'valid' => empty( $validation_errors ),
            'validation_errors' => $validation_errors ?: array(),
        );

        return rest_ensure_response( $data );
    }

    /**
     * Generate blueprint preview
     *
     * @param array $blueprint_data Blueprint data.
     * @return array Preview data.
     */
    private function generate_blueprint_preview( $blueprint_data ) {
        $preview = array(
            'sections' => array(),
            'estimated_tokens' => 0,
            'estimated_cost' => 0.0,
        );

        if ( ! isset( $blueprint_data['sections'] ) || ! is_array( $blueprint_data['sections'] ) ) {
            return $preview;
        }

        foreach ( $blueprint_data['sections'] as $section ) {
            $section_tokens = $this->estimate_section_tokens( $section );
            $preview['sections'][] = array(
                'id' => $section['id'] ?? '',
                'type' => $section['type'] ?? 'content',
                'heading' => $section['heading'] ?? '',
                'word_target' => $section['word_target'] ?? 150,
                'estimated_tokens' => $section_tokens,
            );
            $preview['estimated_tokens'] += $section_tokens;
        }

        $preview['estimated_cost'] = ( $preview['estimated_tokens'] / 1000 ) * 0.045;

        return $preview;
    }

    /**
     * Estimate tokens for a section
     *
     * @param array $section Section data.
     * @return int Estimated tokens.
     */
    private function estimate_section_tokens( $section ) {
        $word_target = $section['word_target'] ?? 150;
        return ceil( $word_target / 0.75 ) + 50; // 1 token ≈ 0.75 words + overhead
    }

    /**
     * Permission checks
     */
    public function get_items_permissions_check( $request ) {
        return current_user_can( 'manage_options' );
    }

    public function get_item_permissions_check( $request ) {
        return current_user_can( 'manage_options' );
    }

    public function create_item_permissions_check( $request ) {
        return current_user_can( 'manage_options' );
    }

    public function update_item_permissions_check( $request ) {
        return current_user_can( 'manage_options' );
    }

    public function delete_item_permissions_check( $request ) {
        return current_user_can( 'manage_options' );
    }

    public function validate_schema_permissions_check( $request ) {
        return current_user_can( 'manage_options' );
    }

    public function generate_preview_permissions_check( $request ) {
        return current_user_can( 'manage_options' );
    }

    public function get_detected_plugins_permissions_check( $request ) {
        return current_user_can( 'manage_options' );
    }
}