<?php
/**
 * API Manager - REST API Endpoints and External Integration
 * 
 * This file manages custom REST API endpoints, handles API authentication and permissions, and extends
 * WordPress REST API responses with custom field data. It provides a secure and standardized way to
 * expose plugin functionality to external applications and JavaScript frontends.
 *
 * API Manager class
 *
 * @package ModernWPPlugin
 */

namespace ModernWPPlugin\API;

/**
 * API Manager class
 */
class API_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_filter( 'rest_prepare_post', array( $this, 'add_custom_fields_to_rest' ), 10, 3 );
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route(
            'modern-wp-plugin/v1',
            '/data',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_data' ),
                'permission_callback' => array( $this, 'get_data_permissions_check' ),
            )
        );

        register_rest_route(
            'modern-wp-plugin/v1',
            '/data',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'create_data' ),
                'permission_callback' => array( $this, 'create_data_permissions_check' ),
                'args'                => array(
                    'title' => array(
                        'required' => true,
                        'validate_callback' => function( $param, $request, $key ) {
                            return is_string( $param );
                        },
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'content' => array(
                        'required' => false,
                        'validate_callback' => function( $param, $request, $key ) {
                            return is_string( $param );
                        },
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ),
                ),
            )
        );
    }

    /**
     * Get data endpoint
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_data( $request ) {
        $data = array(
            'message' => __( 'Hello from Modern WP Plugin API!', 'modern-wp-plugin' ),
            'version' => MODERN_WP_PLUGIN_VERSION,
            'timestamp' => current_time( 'mysql' ),
        );

        return rest_ensure_response( $data );
    }

    /**
     * Create data endpoint
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function create_data( $request ) {
        $title = $request->get_param( 'title' );
        $content = $request->get_param( 'content' );

        // Create a new post with the provided data
        $post_data = array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'draft',
            'post_type'    => 'post',
        );

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            return new WP_Error(
                'create_failed',
                __( 'Failed to create post.', 'modern-wp-plugin' ),
                array( 'status' => 500 )
            );
        }

        $response_data = array(
            'id' => $post_id,
            'title' => $title,
            'content' => $content,
            'status' => 'created',
        );

        return rest_ensure_response( $response_data );
    }

    /**
     * Check permissions for get data endpoint
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if allowed, error otherwise.
     */
    public function get_data_permissions_check( $request ) {
        return true; // Public endpoint
    }

    /**
     * Check permissions for create data endpoint
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if allowed, error otherwise.
     */
    public function create_data_permissions_check( $request ) {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Add custom fields to REST API response
     *
     * @param WP_REST_Response $response The response object.
     * @param WP_Post          $post     Post object.
     * @param WP_REST_Request  $request  Request object.
     * @return WP_REST_Response Modified response.
     */
    public function add_custom_fields_to_rest( $response, $post, $request ) {
        $custom_fields = array();

        // Add ACF fields if available
        if ( function_exists( 'get_fields' ) ) {
            $fields = get_fields( $post->ID );
            if ( $fields ) {
                $custom_fields['acf'] = $fields;
            }
        }

        // Add other custom fields
        $sample_text = get_post_meta( $post->ID, 'sample_text', true );
        if ( $sample_text ) {
            $custom_fields['sample_text'] = $sample_text;
        }

        if ( ! empty( $custom_fields ) ) {
            $response->data['custom_fields'] = $custom_fields;
        }

        return $response;
    }
}