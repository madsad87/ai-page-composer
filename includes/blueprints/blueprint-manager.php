<?php
/**
 * Blueprint Manager - AI Blueprint Custom Post Type Management
 * 
 * This file contains the Blueprint_Manager class that handles the registration
 * and management of the ai_blueprint custom post type. It coordinates with
 * other blueprint components to provide a complete CRUD interface for AI
 * content generation templates.
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\Blueprints;

use AIPageComposer\Blueprints\Schema_Processor;
use AIPageComposer\Blueprints\Blueprint_Meta_Boxes;
use AIPageComposer\Admin\Block_Preferences;

/**
 * Blueprint Manager class for handling AI Blueprint CPT
 */
class Blueprint_Manager {

    /**
     * Schema processor instance
     *
     * @var Schema_Processor
     */
    private $schema_processor;

    /**
     * Meta boxes instance
     *
     * @var Blueprint_Meta_Boxes
     */
    private $meta_boxes;

    /**
     * Block preferences instance
     *
     * @var Block_Preferences
     */
    private $block_preferences;

    /**
     * Constructor
     *
     * @param Block_Preferences $block_preferences Block preferences instance.
     */
    public function __construct( $block_preferences = null ) {
        $this->schema_processor = new Schema_Processor();
        $this->block_preferences = $block_preferences;

        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'init', array( $this, 'init_meta_boxes' ) );
        add_action( 'save_post_ai_blueprint', array( $this, 'save_blueprint_data' ), 10, 2 );
        add_filter( 'manage_ai_blueprint_posts_columns', array( $this, 'add_custom_columns' ) );
        add_action( 'manage_ai_blueprint_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
        add_filter( 'manage_edit-ai_blueprint_sortable_columns', array( $this, 'sortable_columns' ) );
        add_action( 'restrict_manage_posts', array( $this, 'add_admin_filters' ) );
        add_filter( 'parse_query', array( $this, 'filter_blueprints_by_type' ) );
    }

    /**
     * Register the ai_blueprint custom post type
     */
    public function register_post_type() {
        $labels = array(
            'name' => __( 'AI Blueprints', 'ai-page-composer' ),
            'singular_name' => __( 'AI Blueprint', 'ai-page-composer' ),
            'menu_name' => __( 'AI Blueprints', 'ai-page-composer' ),
            'add_new' => __( 'Add New Blueprint', 'ai-page-composer' ),
            'add_new_item' => __( 'Add New AI Blueprint', 'ai-page-composer' ),
            'edit_item' => __( 'Edit AI Blueprint', 'ai-page-composer' ),
            'new_item' => __( 'New AI Blueprint', 'ai-page-composer' ),
            'view_item' => __( 'View AI Blueprint', 'ai-page-composer' ),
            'search_items' => __( 'Search AI Blueprints', 'ai-page-composer' ),
            'not_found' => __( 'No AI blueprints found', 'ai-page-composer' ),
            'not_found_in_trash' => __( 'No AI blueprints found in trash', 'ai-page-composer' ),
            'all_items' => __( 'All AI Blueprints', 'ai-page-composer' ),
            'archives' => __( 'AI Blueprint Archives', 'ai-page-composer' ),
            'insert_into_item' => __( 'Insert into blueprint', 'ai-page-composer' ),
            'uploaded_to_this_item' => __( 'Uploaded to this blueprint', 'ai-page-composer' ),
            'filter_items_list' => __( 'Filter blueprints list', 'ai-page-composer' ),
            'items_list_navigation' => __( 'Blueprints list navigation', 'ai-page-composer' ),
            'items_list' => __( 'Blueprints list', 'ai-page-composer' )
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => 'ai-composer',
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'manage_options',
                'edit_posts' => 'manage_options',
                'edit_others_posts' => 'manage_options',
                'delete_posts' => 'manage_options',
                'delete_others_posts' => 'manage_options',
                'read_private_posts' => 'manage_options',
                'edit_post' => 'manage_options',
                'delete_post' => 'manage_options',
                'read_post' => 'manage_options'
            ),
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
            'supports' => array( 'title', 'author', 'revisions' ),
            'menu_icon' => 'dashicons-layout',
            'show_in_rest' => true,
            'rest_base' => 'ai-blueprints',
            'rest_controller_class' => 'AIPageComposer\\Blueprints\\Blueprint_REST_Controller',
            'menu_position' => 25
        );

        register_post_type( 'ai_blueprint', $args );
    }

    /**
     * Initialize meta boxes
     */
    public function init_meta_boxes() {
        if ( ! $this->meta_boxes ) {
            $this->meta_boxes = new Blueprint_Meta_Boxes( $this->schema_processor, $this->block_preferences );
        }
    }

    /**
     * Save blueprint data when post is saved
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function save_blueprint_data( $post_id, $post ) {
        // Verify nonce
        if ( ! isset( $_POST['ai_blueprint_meta_nonce_field'] ) || 
             ! wp_verify_nonce( $_POST['ai_blueprint_meta_nonce_field'], 'ai_blueprint_meta_nonce' ) ) {
            return;
        }

        // Check if user has permission to edit
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Don't save on autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Prepare blueprint data
        $blueprint_data = array();

        // Process schema data from visual editor or JSON editor
        if ( isset( $_POST['blueprint_schema_data'] ) && ! empty( $_POST['blueprint_schema_data'] ) ) {
            $schema_data = json_decode( stripslashes( $_POST['blueprint_schema_data'] ), true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $blueprint_data = $schema_data;
            }
        } elseif ( isset( $_POST['blueprint_schema_json'] ) && ! empty( $_POST['blueprint_schema_json'] ) ) {
            $schema_data = json_decode( stripslashes( $_POST['blueprint_schema_json'] ), true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $blueprint_data = $schema_data;
            }
        }

        // Process sections data if not in schema data
        if ( empty( $blueprint_data['sections'] ) && isset( $_POST['sections'] ) && is_array( $_POST['sections'] ) ) {
            $blueprint_data['sections'] = $this->process_sections_data( $_POST['sections'] );
        }

        // Process global settings if not in schema data
        if ( empty( $blueprint_data['global_settings'] ) && isset( $_POST['global_settings'] ) && is_array( $_POST['global_settings'] ) ) {
            $blueprint_data['global_settings'] = $this->process_global_settings_data( $_POST['global_settings'] );
        }

        // Apply defaults and sanitize
        $blueprint_data = $this->schema_processor->apply_defaults( $blueprint_data );
        $blueprint_data = $this->schema_processor->sanitize_data( $blueprint_data );

        // Validate the data
        $validation_result = $this->schema_processor->validate_schema( $blueprint_data );

        if ( ! $validation_result['valid'] ) {
            // Store validation errors for display
            update_post_meta( $post_id, '_ai_blueprint_validation_errors', $validation_result['errors'] );
            
            // Log validation errors
            error_log( 'AI Blueprint validation failed for post ' . $post_id . ': ' . wp_json_encode( $validation_result['errors'] ) );
        } else {
            // Clear any previous validation errors
            delete_post_meta( $post_id, '_ai_blueprint_validation_errors' );
        }

        // Save the blueprint data
        update_post_meta( $post_id, '_ai_blueprint_schema', $blueprint_data );
        update_post_meta( $post_id, '_ai_blueprint_sections', $blueprint_data['sections'] ?? array() );
        update_post_meta( $post_id, '_ai_blueprint_global_settings', $blueprint_data['global_settings'] ?? array() );
        update_post_meta( $post_id, '_ai_blueprint_metadata', $blueprint_data['metadata'] ?? array() );

        // Update post meta for easy querying
        $this->update_blueprint_meta_cache( $post_id, $blueprint_data );

        do_action( 'ai_blueprint_saved', $post_id, $blueprint_data, $validation_result );
    }

    /**
     * Process sections data from form submission
     *
     * @param array $sections_data Raw sections data from form.
     * @return array Processed sections data.
     */
    private function process_sections_data( $sections_data ) {
        $processed_sections = array();

        foreach ( $sections_data as $index => $section ) {
            if ( empty( $section['heading'] ) ) {
                continue; // Skip sections without headings
            }

            $processed_section = array(
                'id' => sanitize_key( $section['id'] ?? 'section-' . ( $index + 1 ) ),
                'type' => sanitize_text_field( $section['type'] ?? 'content' ),
                'heading' => sanitize_text_field( $section['heading'] ),
                'heading_level' => absint( $section['heading_level'] ?? 2 ),
                'word_target' => absint( $section['word_target'] ?? 150 ),
                'media_policy' => sanitize_text_field( $section['media_policy'] ?? 'optional' ),
                'internal_links' => absint( $section['internal_links'] ?? 2 ),
                'citations_required' => isset( $section['citations_required'] ) ? (bool) $section['citations_required'] : true,
                'tone' => sanitize_text_field( $section['tone'] ?? 'professional' )
            );

            // Process allowed blocks
            if ( isset( $section['allowed_blocks'] ) ) {
                if ( is_string( $section['allowed_blocks'] ) ) {
                    $processed_section['allowed_blocks'] = array_filter( 
                        array_map( 'trim', explode( "\n", $section['allowed_blocks'] ) )
                    );
                } elseif ( is_array( $section['allowed_blocks'] ) ) {
                    $processed_section['allowed_blocks'] = array_map( 'sanitize_text_field', $section['allowed_blocks'] );
                }
            } else {
                $processed_section['allowed_blocks'] = array();
            }

            // Process block preferences
            if ( isset( $section['block_preferences'] ) && is_array( $section['block_preferences'] ) ) {
                $processed_section['block_preferences'] = array(
                    'preferred_plugin' => sanitize_text_field( $section['block_preferences']['preferred_plugin'] ?? 'auto' ),
                    'primary_block' => sanitize_text_field( $section['block_preferences']['primary_block'] ?? '' ),
                    'pattern_preference' => sanitize_text_field( $section['block_preferences']['pattern_preference'] ?? '' ),
                    'custom_attributes' => $section['block_preferences']['custom_attributes'] ?? array()
                );

                // Process fallback blocks
                if ( isset( $section['block_preferences']['fallback_blocks'] ) ) {
                    if ( is_string( $section['block_preferences']['fallback_blocks'] ) ) {
                        $processed_section['block_preferences']['fallback_blocks'] = array_filter(
                            array_map( 'trim', explode( ',', $section['block_preferences']['fallback_blocks'] ) )
                        );
                    } elseif ( is_array( $section['block_preferences']['fallback_blocks'] ) ) {
                        $processed_section['block_preferences']['fallback_blocks'] = array_map( 
                            'sanitize_text_field', 
                            $section['block_preferences']['fallback_blocks'] 
                        );
                    }
                } else {
                    $processed_section['block_preferences']['fallback_blocks'] = array();
                }
            }

            $processed_sections[] = $processed_section;
        }

        return $processed_sections;
    }

    /**
     * Process global settings data from form submission
     *
     * @param array $global_settings_data Raw global settings data from form.
     * @return array Processed global settings data.
     */
    private function process_global_settings_data( $global_settings_data ) {
        $processed_settings = array(
            'generation_mode' => sanitize_text_field( $global_settings_data['generation_mode'] ?? 'hybrid' ),
            'hybrid_alpha' => floatval( $global_settings_data['hybrid_alpha'] ?? 0.7 ),
            'max_tokens_per_section' => absint( $global_settings_data['max_tokens_per_section'] ?? 1000 ),
            'image_generation_enabled' => isset( $global_settings_data['image_generation_enabled'] ),
            'seo_optimization' => isset( $global_settings_data['seo_optimization'] ),
            'accessibility_checks' => isset( $global_settings_data['accessibility_checks'] ),
            'cost_limit_usd' => floatval( $global_settings_data['cost_limit_usd'] ?? 5.0 )
        );

        // Process MVDB namespaces
        if ( isset( $global_settings_data['mvdb_namespaces'] ) && is_array( $global_settings_data['mvdb_namespaces'] ) ) {
            $processed_settings['mvdb_namespaces'] = array_map( 'sanitize_text_field', $global_settings_data['mvdb_namespaces'] );
        } else {
            $processed_settings['mvdb_namespaces'] = array( 'content' );
        }

        return $processed_settings;
    }

    /**
     * Update blueprint meta cache for easier querying
     *
     * @param int   $post_id       Post ID.
     * @param array $blueprint_data Blueprint data.
     */
    private function update_blueprint_meta_cache( $post_id, $blueprint_data ) {
        // Cache section count
        $section_count = count( $blueprint_data['sections'] ?? array() );
        update_post_meta( $post_id, '_ai_blueprint_section_count', $section_count );

        // Cache blueprint category
        $category = $blueprint_data['metadata']['category'] ?? 'custom';
        update_post_meta( $post_id, '_ai_blueprint_category', $category );

        // Cache difficulty level
        $difficulty = $blueprint_data['metadata']['difficulty_level'] ?? 'intermediate';
        update_post_meta( $post_id, '_ai_blueprint_difficulty', $difficulty );

        // Cache generation mode
        $generation_mode = $blueprint_data['global_settings']['generation_mode'] ?? 'hybrid';
        update_post_meta( $post_id, '_ai_blueprint_generation_mode', $generation_mode );

        // Cache estimated time
        $estimated_time = $blueprint_data['metadata']['estimated_time_minutes'] ?? 30;
        update_post_meta( $post_id, '_ai_blueprint_estimated_time', $estimated_time );
    }

    /**
     * Add custom columns to the blueprints list table
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_custom_columns( $columns ) {
        // Remove date column temporarily
        $date = $columns['date'];
        unset( $columns['date'] );

        // Add custom columns
        $columns['blueprint_category'] = __( 'Category', 'ai-page-composer' );
        $columns['sections_count'] = __( 'Sections', 'ai-page-composer' );
        $columns['generation_mode'] = __( 'Mode', 'ai-page-composer' );
        $columns['difficulty'] = __( 'Difficulty', 'ai-page-composer' );
        $columns['status'] = __( 'Status', 'ai-page-composer' );

        // Add date column back
        $columns['date'] = $date;

        return $columns;
    }

    /**
     * Render custom column content
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     */
    public function render_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'blueprint_category':
                $category = get_post_meta( $post_id, '_ai_blueprint_category', true );
                echo esc_html( ucwords( str_replace( '-', ' ', $category ?: 'custom' ) ) );
                break;

            case 'sections_count':
                $count = get_post_meta( $post_id, '_ai_blueprint_section_count', true );
                echo esc_html( $count ?: '0' );
                break;

            case 'generation_mode':
                $mode = get_post_meta( $post_id, '_ai_blueprint_generation_mode', true );
                $modes = $this->schema_processor->get_generation_modes();
                echo esc_html( $modes[ $mode ] ?? ucfirst( $mode ?: 'hybrid' ) );
                break;

            case 'difficulty':
                $difficulty = get_post_meta( $post_id, '_ai_blueprint_difficulty', true );
                $badge_class = 'difficulty-' . ( $difficulty ?: 'intermediate' );
                echo '<span class="blueprint-difficulty-badge ' . esc_attr( $badge_class ) . '">' . 
                     esc_html( ucfirst( $difficulty ?: 'intermediate' ) ) . '</span>';
                break;

            case 'status':
                $validation_errors = get_post_meta( $post_id, '_ai_blueprint_validation_errors', true );
                if ( empty( $validation_errors ) ) {
                    echo '<span class="blueprint-status-badge status-valid">✓ Valid</span>';
                } else {
                    echo '<span class="blueprint-status-badge status-invalid">⚠ ' . 
                         sprintf( _n( '%d Error', '%d Errors', count( $validation_errors ), 'ai-page-composer' ), count( $validation_errors ) ) . 
                         '</span>';
                }
                break;
        }
    }

    /**
     * Make custom columns sortable
     *
     * @param array $columns Existing sortable columns.
     * @return array Modified sortable columns.
     */
    public function sortable_columns( $columns ) {
        $columns['blueprint_category'] = 'blueprint_category';
        $columns['sections_count'] = 'sections_count';
        $columns['generation_mode'] = 'generation_mode';
        $columns['difficulty'] = 'difficulty';

        return $columns;
    }

    /**
     * Add admin filters for blueprints
     */
    public function add_admin_filters() {
        global $typenow;

        if ( $typenow !== 'ai_blueprint' ) {
            return;
        }

        // Category filter
        $category_filter = $_GET['blueprint_category'] ?? '';
        echo '<select name="blueprint_category">';
        echo '<option value="">' . esc_html__( 'All Categories', 'ai-page-composer' ) . '</option>';
        
        $categories = array(
            'landing-page' => __( 'Landing Page', 'ai-page-composer' ),
            'blog-post' => __( 'Blog Post', 'ai-page-composer' ),
            'product-page' => __( 'Product Page', 'ai-page-composer' ),
            'about-page' => __( 'About Page', 'ai-page-composer' ),
            'contact-page' => __( 'Contact Page', 'ai-page-composer' ),
            'custom' => __( 'Custom', 'ai-page-composer' )
        );

        foreach ( $categories as $value => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $value ),
                selected( $category_filter, $value, false ),
                esc_html( $label )
            );
        }
        echo '</select>';

        // Difficulty filter
        $difficulty_filter = $_GET['blueprint_difficulty'] ?? '';
        echo '<select name="blueprint_difficulty">';
        echo '<option value="">' . esc_html__( 'All Difficulties', 'ai-page-composer' ) . '</option>';
        
        $difficulties = array(
            'beginner' => __( 'Beginner', 'ai-page-composer' ),
            'intermediate' => __( 'Intermediate', 'ai-page-composer' ),
            'advanced' => __( 'Advanced', 'ai-page-composer' )
        );

        foreach ( $difficulties as $value => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $value ),
                selected( $difficulty_filter, $value, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
    }

    /**
     * Filter blueprints by category and difficulty
     *
     * @param WP_Query $query Query object.
     */
    public function filter_blueprints_by_type( $query ) {
        global $pagenow, $typenow;

        if ( $pagenow !== 'edit.php' || $typenow !== 'ai_blueprint' || ! $query->is_admin ) {
            return;
        }

        $meta_query = array();

        if ( ! empty( $_GET['blueprint_category'] ) ) {
            $meta_query[] = array(
                'key' => '_ai_blueprint_category',
                'value' => sanitize_text_field( $_GET['blueprint_category'] ),
                'compare' => '='
            );
        }

        if ( ! empty( $_GET['blueprint_difficulty'] ) ) {
            $meta_query[] = array(
                'key' => '_ai_blueprint_difficulty',
                'value' => sanitize_text_field( $_GET['blueprint_difficulty'] ),
                'compare' => '='
            );
        }

        if ( ! empty( $meta_query ) ) {
            $query->set( 'meta_query', $meta_query );
        }
    }

    /**
     * Get blueprint data by post ID
     *
     * @param int $post_id Post ID.
     * @return array|false Blueprint data or false on failure.
     */
    public function get_blueprint( $post_id ) {
        if ( get_post_type( $post_id ) !== 'ai_blueprint' ) {
            return false;
        }

        $blueprint_data = get_post_meta( $post_id, '_ai_blueprint_schema', true );

        if ( empty( $blueprint_data ) ) {
            return false;
        }

        return $blueprint_data;
    }

    /**
     * Get all blueprints with optional filtering
     *
     * @param array $args Query arguments.
     * @return array Array of blueprint posts.
     */
    public function get_blueprints( $args = array() ) {
        $default_args = array(
            'post_type' => 'ai_blueprint',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array()
        );

        $args = wp_parse_args( $args, $default_args );

        $query = new \WP_Query( $args );

        return $query->posts;
    }

    /**
     * Duplicate a blueprint
     *
     * @param int $post_id Original blueprint post ID.
     * @return int|WP_Error New blueprint post ID or error.
     */
    public function duplicate_blueprint( $post_id ) {
        $original_post = get_post( $post_id );

        if ( ! $original_post || $original_post->post_type !== 'ai_blueprint' ) {
            return new \WP_Error( 'invalid_blueprint', __( 'Invalid blueprint to duplicate.', 'ai-page-composer' ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new \WP_Error( 'insufficient_permissions', __( 'Insufficient permissions to duplicate blueprint.', 'ai-page-composer' ) );
        }

        // Create new post
        $new_post_id = wp_insert_post( array(
            'post_title' => $original_post->post_title . ' (' . __( 'Copy', 'ai-page-composer' ) . ')',
            'post_content' => $original_post->post_content,
            'post_status' => 'draft',
            'post_type' => 'ai_blueprint',
            'post_author' => get_current_user_id()
        ) );

        if ( is_wp_error( $new_post_id ) ) {
            return $new_post_id;
        }

        // Copy all meta data
        $meta_keys = array(
            '_ai_blueprint_schema',
            '_ai_blueprint_sections',
            '_ai_blueprint_global_settings',
            '_ai_blueprint_metadata'
        );

        foreach ( $meta_keys as $meta_key ) {
            $meta_value = get_post_meta( $post_id, $meta_key, true );
            if ( ! empty( $meta_value ) ) {
                update_post_meta( $new_post_id, $meta_key, $meta_value );
            }
        }

        // Update cache meta
        $blueprint_data = get_post_meta( $post_id, '_ai_blueprint_schema', true );
        if ( ! empty( $blueprint_data ) ) {
            $this->update_blueprint_meta_cache( $new_post_id, $blueprint_data );
        }

        do_action( 'ai_blueprint_duplicated', $new_post_id, $post_id );

        return $new_post_id;
    }

    /**
     * Get schema processor instance
     *
     * @return Schema_Processor
     */
    public function get_schema_processor() {
        return $this->schema_processor;
    }

    /**
     * Export blueprint as JSON
     *
     * @param int $post_id Blueprint post ID.
     * @return string|false JSON string or false on failure.
     */
    public function export_blueprint( $post_id ) {
        $blueprint_data = $this->get_blueprint( $post_id );

        if ( false === $blueprint_data ) {
            return false;
        }

        $export_data = array(
            'blueprint' => $blueprint_data,
            'title' => get_the_title( $post_id ),
            'exported_at' => current_time( 'mysql' ),
            'exported_by' => get_current_user_id(),
            'plugin_version' => AI_PAGE_COMPOSER_VERSION
        );

        return wp_json_encode( $export_data, JSON_PRETTY_PRINT );
    }

    /**
     * Import blueprint from JSON
     *
     * @param string $json_data JSON data to import.
     * @return int|WP_Error New blueprint post ID or error.
     */
    public function import_blueprint( $json_data ) {
        $import_data = json_decode( $json_data, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'invalid_json', __( 'Invalid JSON data provided.', 'ai-page-composer' ) );
        }

        if ( ! isset( $import_data['blueprint'] ) ) {
            return new \WP_Error( 'missing_blueprint', __( 'Blueprint data not found in import.', 'ai-page-composer' ) );
        }

        $blueprint_data = $import_data['blueprint'];
        $title = $import_data['title'] ?? __( 'Imported Blueprint', 'ai-page-composer' );

        // Validate the blueprint data
        $validation_result = $this->schema_processor->validate_schema( $blueprint_data );

        if ( ! $validation_result['valid'] ) {
            return new \WP_Error( 'invalid_blueprint', __( 'Imported blueprint data is invalid.', 'ai-page-composer' ), $validation_result['errors'] );
        }

        // Create new post
        $post_id = wp_insert_post( array(
            'post_title' => sanitize_text_field( $title ),
            'post_status' => 'draft',
            'post_type' => 'ai_blueprint',
            'post_author' => get_current_user_id()
        ) );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Save blueprint data
        update_post_meta( $post_id, '_ai_blueprint_schema', $blueprint_data );
        update_post_meta( $post_id, '_ai_blueprint_sections', $blueprint_data['sections'] ?? array() );
        update_post_meta( $post_id, '_ai_blueprint_global_settings', $blueprint_data['global_settings'] ?? array() );
        update_post_meta( $post_id, '_ai_blueprint_metadata', $blueprint_data['metadata'] ?? array() );

        // Update cache meta
        $this->update_blueprint_meta_cache( $post_id, $blueprint_data );

        do_action( 'ai_blueprint_imported', $post_id, $blueprint_data );

        return $post_id;
    }
}