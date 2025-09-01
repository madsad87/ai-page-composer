<?php
/**
 * Blueprint Meta Boxes - Admin Interface Meta Box Management
 * 
 * This file contains the Blueprint_Meta_Boxes class that handles the creation
 * and rendering of meta boxes for the AI Blueprint custom post type. It provides
 * a comprehensive interface for configuring blueprint schemas, sections, and
 * global settings through WordPress admin meta boxes.
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\Blueprints;

use AIPageComposer\Blueprints\Schema_Processor;
use AIPageComposer\Admin\Block_Preferences;

/**
 * Blueprint Meta Boxes class for admin interface
 */
class Blueprint_Meta_Boxes {

    /**
     * Schema processor instance
     *
     * @var Schema_Processor
     */
    private $schema_processor;

    /**
     * Block preferences instance
     *
     * @var Block_Preferences
     */
    private $block_preferences;

    /**
     * Constructor
     *
     * @param Schema_Processor  $schema_processor  Schema processor instance.
     * @param Block_Preferences $block_preferences Block preferences instance.
     */
    public function __construct( $schema_processor, $block_preferences = null ) {
        $this->schema_processor = $schema_processor;
        $this->block_preferences = $block_preferences;

        add_action( 'add_meta_boxes_ai_blueprint', array( $this, 'add_meta_boxes' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_meta_box_scripts' ) );
    }

    /**
     * Add meta boxes for ai_blueprint post type
     */
    public function add_meta_boxes() {
        add_meta_box(
            'ai_blueprint_schema',
            __( 'Blueprint Configuration', 'ai-page-composer' ),
            array( $this, 'render_schema_meta_box' ),
            'ai_blueprint',
            'normal',
            'high'
        );

        add_meta_box(
            'ai_blueprint_sections',
            __( 'Content Sections', 'ai-page-composer' ),
            array( $this, 'render_sections_meta_box' ),
            'ai_blueprint',
            'normal',
            'high'
        );

        add_meta_box(
            'ai_blueprint_global_settings',
            __( 'Global Settings', 'ai-page-composer' ),
            array( $this, 'render_global_settings_meta_box' ),
            'ai_blueprint',
            'side',
            'default'
        );

        add_meta_box(
            'ai_blueprint_preview',
            __( 'Blueprint Preview', 'ai-page-composer' ),
            array( $this, 'render_preview_meta_box' ),
            'ai_blueprint',
            'side',
            'default'
        );

        add_meta_box(
            'ai_blueprint_validation',
            __( 'Validation Status', 'ai-page-composer' ),
            array( $this, 'render_validation_meta_box' ),
            'ai_blueprint',
            'side',
            'low'
        );
    }

    /**
     * Enqueue scripts and styles for meta boxes
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_meta_box_scripts( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        global $post_type;
        if ( $post_type !== 'ai_blueprint' ) {
            return;
        }

        // Enqueue blueprint admin scripts
        wp_enqueue_script(
            'ai-blueprint-admin',
            AI_PAGE_COMPOSER_PLUGIN_URL . 'assets/js/blueprint-admin.js',
            array( 'jquery', 'wp-util', 'wp-api' ),
            AI_PAGE_COMPOSER_VERSION,
            true
        );

        // Enqueue blueprint admin styles
        wp_enqueue_style(
            'ai-blueprint-admin',
            AI_PAGE_COMPOSER_PLUGIN_URL . 'assets/css/blueprint-admin.css',
            array(),
            AI_PAGE_COMPOSER_VERSION
        );

        // Localize script data
        wp_localize_script(
            'ai-blueprint-admin',
            'aiBlueprintAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'restUrl' => rest_url( 'ai-composer/v1/' ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'postId' => get_the_ID(),
                'sectionTypes' => $this->schema_processor->get_section_types(),
                'toneOptions' => $this->schema_processor->get_tone_options(),
                'generationModes' => $this->schema_processor->get_generation_modes(),
                'detectedPlugins' => $this->get_detected_block_plugins(),
                'i18n' => array(
                    'addSection' => __( 'Add Section', 'ai-page-composer' ),
                    'removeSection' => __( 'Remove Section', 'ai-page-composer' ),
                    'moveUp' => __( 'Move Up', 'ai-page-composer' ),
                    'moveDown' => __( 'Move Down', 'ai-page-composer' ),
                    'validationSuccess' => __( 'Blueprint validation successful', 'ai-page-composer' ),
                    'validationError' => __( 'Blueprint validation failed', 'ai-page-composer' ),
                    'previewError' => __( 'Error generating preview', 'ai-page-composer' ),
                    'testError' => __( 'Error running test generation', 'ai-page-composer' )
                )
            )
        );
    }

    /**
     * Render the schema configuration meta box
     *
     * @param WP_Post $post Current post object.
     */
    public function render_schema_meta_box( $post ) {
        wp_nonce_field( 'ai_blueprint_meta_nonce', 'ai_blueprint_meta_nonce_field' );

        $schema_data = get_post_meta( $post->ID, '_ai_blueprint_schema', true );
        $schema_json = $schema_data ? wp_json_encode( $schema_data, JSON_PRETTY_PRINT ) : '';

        include AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/admin/blueprint/schema-meta-box.php';
    }

    /**
     * Render the sections configuration meta box
     *
     * @param WP_Post $post Current post object.
     */
    public function render_sections_meta_box( $post ) {
        $sections_data = get_post_meta( $post->ID, '_ai_blueprint_sections', true ) ?: array();
        $section_types = $this->schema_processor->get_section_types();
        $tone_options = $this->schema_processor->get_tone_options();
        $detected_plugins = $this->get_detected_block_plugins();

        include AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/admin/blueprint/sections-meta-box.php';
    }

    /**
     * Render the global settings meta box
     *
     * @param WP_Post $post Current post object.
     */
    public function render_global_settings_meta_box( $post ) {
        $global_settings = get_post_meta( $post->ID, '_ai_blueprint_global_settings', true ) ?: array();
        $generation_modes = $this->schema_processor->get_generation_modes();

        include AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/admin/blueprint/global-settings-meta-box.php';
    }

    /**
     * Render the preview meta box
     *
     * @param WP_Post $post Current post object.
     */
    public function render_preview_meta_box( $post ) {
        include AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/admin/blueprint/preview-meta-box.php';
    }

    /**
     * Render the validation status meta box
     *
     * @param WP_Post $post Current post object.
     */
    public function render_validation_meta_box( $post ) {
        $validation_errors = get_post_meta( $post->ID, '_ai_blueprint_validation_errors', true );
        $schema_data = get_post_meta( $post->ID, '_ai_blueprint_schema', true );

        include AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/admin/blueprint/validation-meta-box.php';
    }

    /**
     * Get detected block plugins
     *
     * @return array Detected block plugins with their status.
     */
    private function get_detected_block_plugins() {
        if ( $this->block_preferences ) {
            return $this->block_preferences->get_detected_plugins();
        }

        // Fallback detection if Block_Preferences is not available
        $plugins = array(
            'genesis_blocks' => array(
                'name' => __( 'Genesis Blocks', 'ai-page-composer' ),
                'active' => is_plugin_active( 'genesis-blocks/genesis-blocks.php' ),
                'slug' => 'genesis-blocks'
            ),
            'kadence_blocks' => array(
                'name' => __( 'Kadence Blocks', 'ai-page-composer' ),
                'active' => is_plugin_active( 'kadence-blocks/kadence-blocks.php' ),
                'slug' => 'kadence-blocks'
            ),
            'stackable' => array(
                'name' => __( 'Stackable', 'ai-page-composer' ),
                'active' => is_plugin_active( 'stackable-ultimate-gutenberg-blocks/plugin.php' ),
                'slug' => 'stackable-ultimate-gutenberg-blocks'
            ),
            'ultimate_addons' => array(
                'name' => __( 'Ultimate Addons for Gutenberg', 'ai-page-composer' ),
                'active' => is_plugin_active( 'ultimate-addons-for-gutenberg/ultimate-addons-for-gutenberg.php' ),
                'slug' => 'ultimate-addons-for-gutenberg'
            ),
            'blocksy' => array(
                'name' => __( 'Blocksy Companion', 'ai-page-composer' ),
                'active' => is_plugin_active( 'blocksy-companion/blocksy-companion.php' ),
                'slug' => 'blocksy-companion'
            )
        );

        return $plugins;
    }

    /**
     * Render a single section row for the sections meta box
     *
     * @param array $section Section data.
     * @param int   $index   Section index.
     */
    public function render_section_row( $section, $index ) {
        $section_types = $this->schema_processor->get_section_types();
        $tone_options = $this->schema_processor->get_tone_options();
        $detected_plugins = $this->get_detected_block_plugins();

        include AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/admin/blueprint/section-row.php';
    }

    /**
     * Get media policy options
     *
     * @return array Media policy options.
     */
    public function get_media_policy_options() {
        return array(
            'none' => __( 'No Images', 'ai-page-composer' ),
            'optional' => __( 'Optional', 'ai-page-composer' ),
            'required' => __( 'Required', 'ai-page-composer' )
        );
    }

    /**
     * Get available MVDB namespaces
     *
     * @return array Available namespaces.
     */
    public function get_mvdb_namespaces() {
        return array(
            'content' => __( 'Content', 'ai-page-composer' ),
            'products' => __( 'Products', 'ai-page-composer' ),
            'docs' => __( 'Documentation', 'ai-page-composer' ),
            'knowledge' => __( 'Knowledge Base', 'ai-page-composer' )
        );
    }

    /**
     * Get blueprint categories
     *
     * @return array Blueprint categories.
     */
    public function get_blueprint_categories() {
        return array(
            'landing-page' => __( 'Landing Page', 'ai-page-composer' ),
            'blog-post' => __( 'Blog Post', 'ai-page-composer' ),
            'product-page' => __( 'Product Page', 'ai-page-composer' ),
            'about-page' => __( 'About Page', 'ai-page-composer' ),
            'contact-page' => __( 'Contact Page', 'ai-page-composer' ),
            'custom' => __( 'Custom', 'ai-page-composer' )
        );
    }

    /**
     * Get difficulty levels
     *
     * @return array Difficulty levels.
     */
    public function get_difficulty_levels() {
        return array(
            'beginner' => __( 'Beginner', 'ai-page-composer' ),
            'intermediate' => __( 'Intermediate', 'ai-page-composer' ),
            'advanced' => __( 'Advanced', 'ai-page-composer' )
        );
    }

    /**
     * Validate blueprint data via AJAX
     */
    public function ajax_validate_blueprint() {
        check_ajax_referer( 'ai_blueprint_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'ai-page-composer' ) );
        }

        $blueprint_data = json_decode( stripslashes( $_POST['blueprint_data'] ?? '' ), true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid JSON data provided.', 'ai-page-composer' )
            ) );
        }

        $validation_result = $this->schema_processor->validate_schema( $blueprint_data );

        if ( $validation_result['valid'] ) {
            wp_send_json_success( array(
                'message' => __( 'Blueprint validation successful.', 'ai-page-composer' ),
                'errors' => array()
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Blueprint validation failed.', 'ai-page-composer' ),
                'errors' => $validation_result['errors']
            ) );
        }
    }

    /**
     * Generate blueprint preview via AJAX
     */
    public function ajax_generate_preview() {
        check_ajax_referer( 'ai_blueprint_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'ai-page-composer' ) );
        }

        $blueprint_data = json_decode( stripslashes( $_POST['blueprint_data'] ?? '' ), true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid JSON data provided.', 'ai-page-composer' )
            ) );
        }

        // Generate a preview structure based on the blueprint
        $preview = $this->generate_blueprint_preview( $blueprint_data );

        wp_send_json_success( array(
            'preview' => $preview,
            'message' => __( 'Preview generated successfully.', 'ai-page-composer' )
        ) );
    }

    /**
     * Generate a preview of the blueprint structure
     *
     * @param array $blueprint_data Blueprint configuration data.
     * @return array Preview structure.
     */
    private function generate_blueprint_preview( $blueprint_data ) {
        $preview = array(
            'sections' => array(),
            'estimated_tokens' => 0,
            'estimated_cost' => 0.0,
            'estimated_time_minutes' => 0
        );

        if ( ! isset( $blueprint_data['sections'] ) || ! is_array( $blueprint_data['sections'] ) ) {
            return $preview;
        }

        foreach ( $blueprint_data['sections'] as $section ) {
            $section_preview = array(
                'id' => $section['id'] ?? '',
                'type' => $section['type'] ?? 'content',
                'heading' => $section['heading'] ?? '',
                'heading_level' => $section['heading_level'] ?? 2,
                'word_target' => $section['word_target'] ?? 150,
                'media_policy' => $section['media_policy'] ?? 'optional',
                'estimated_tokens' => $this->estimate_section_tokens( $section ),
                'block_info' => $this->get_section_block_info( $section )
            );

            $preview['sections'][] = $section_preview;
            $preview['estimated_tokens'] += $section_preview['estimated_tokens'];
        }

        // Estimate cost and time
        $preview['estimated_cost'] = $this->estimate_generation_cost( $preview['estimated_tokens'] );
        $preview['estimated_time_minutes'] = $this->estimate_generation_time( $blueprint_data );

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
        
        // Rough estimation: 1 token ≈ 0.75 words
        $content_tokens = ceil( $word_target / 0.75 );
        
        // Add overhead for structure and metadata
        $overhead_tokens = 50;
        
        // Add tokens for media generation if required
        if ( ( $section['media_policy'] ?? 'optional' ) === 'required' ) {
            $overhead_tokens += 100; // Image generation prompt tokens
        }
        
        return $content_tokens + $overhead_tokens;
    }

    /**
     * Estimate generation cost in USD
     *
     * @param int $total_tokens Total estimated tokens.
     * @return float Estimated cost in USD.
     */
    private function estimate_generation_cost( $total_tokens ) {
        // Rough estimation based on OpenAI GPT-4 pricing
        // $0.03 per 1K tokens (input) + $0.06 per 1K tokens (output)
        // Assuming roughly equal input/output
        $cost_per_1k_tokens = 0.045; // Average of input/output
        
        return ( $total_tokens / 1000 ) * $cost_per_1k_tokens;
    }

    /**
     * Estimate generation time in minutes
     *
     * @param array $blueprint_data Blueprint data.
     * @return int Estimated time in minutes.
     */
    private function estimate_generation_time( $blueprint_data ) {
        $section_count = count( $blueprint_data['sections'] ?? array() );
        
        // Base time: 2 minutes per section
        $base_time = $section_count * 2;
        
        // Add time for complex sections
        $complex_types = array( 'hero', 'gallery', 'pricing', 'faq' );
        $complex_sections = 0;
        
        foreach ( $blueprint_data['sections'] ?? array() as $section ) {
            if ( in_array( $section['type'] ?? '', $complex_types, true ) ) {
                $complex_sections++;
            }
        }
        
        $complexity_time = $complex_sections * 1; // 1 extra minute per complex section
        
        // Add time for image generation
        $image_time = 0;
        foreach ( $blueprint_data['sections'] ?? array() as $section ) {
            if ( ( $section['media_policy'] ?? 'optional' ) === 'required' ) {
                $image_time += 1; // 1 minute per required image
            }
        }
        
        return max( 5, $base_time + $complexity_time + $image_time ); // Minimum 5 minutes
    }

    /**
     * Get block information for a section
     *
     * @param array $section Section data.
     * @return array Block information.
     */
    private function get_section_block_info( $section ) {
        $block_preferences = $section['block_preferences'] ?? array();
        $preferred_plugin = $block_preferences['preferred_plugin'] ?? 'auto';
        $primary_block = $block_preferences['primary_block'] ?? '';
        
        $block_info = array(
            'preferred_plugin' => $preferred_plugin,
            'primary_block' => $primary_block,
            'suggested_blocks' => array()
        );
        
        // Suggest blocks based on section type
        $section_type = $section['type'] ?? 'content';
        $suggested_blocks = $this->get_suggested_blocks_for_type( $section_type, $preferred_plugin );
        $block_info['suggested_blocks'] = $suggested_blocks;
        
        return $block_info;
    }

    /**
     * Get suggested blocks for a section type
     *
     * @param string $section_type    Section type.
     * @param string $preferred_plugin Preferred plugin.
     * @return array Suggested blocks.
     */
    private function get_suggested_blocks_for_type( $section_type, $preferred_plugin ) {
        $suggestions = array(
            'hero' => array(
                'core' => array( 'core/cover', 'core/group', 'core/media-text' ),
                'kadence_blocks' => array( 'kadence/rowlayout', 'kadence/advancedheading' ),
                'genesis_blocks' => array( 'genesis-blocks/gb-hero' ),
                'stackable' => array( 'stackable/hero' )
            ),
            'content' => array(
                'core' => array( 'core/paragraph', 'core/heading', 'core/group' ),
                'kadence_blocks' => array( 'kadence/advancedheading', 'kadence/infobox' ),
                'genesis_blocks' => array( 'genesis-blocks/gb-post-grid' ),
                'stackable' => array( 'stackable/text', 'stackable/heading' )
            ),
            'media_text' => array(
                'core' => array( 'core/media-text', 'core/image', 'core/gallery' ),
                'kadence_blocks' => array( 'kadence/rowlayout', 'kadence/image' ),
                'genesis_blocks' => array( 'genesis-blocks/gb-container' ),
                'stackable' => array( 'stackable/image-box' )
            ),
            'columns' => array(
                'core' => array( 'core/columns', 'core/group' ),
                'kadence_blocks' => array( 'kadence/rowlayout', 'kadence/column' ),
                'genesis_blocks' => array( 'genesis-blocks/gb-columns' ),
                'stackable' => array( 'stackable/columns' )
            ),
            'cta' => array(
                'core' => array( 'core/buttons', 'core/group', 'core/cover' ),
                'kadence_blocks' => array( 'kadence/advancedbtn', 'kadence/rowlayout' ),
                'genesis_blocks' => array( 'genesis-blocks/gb-button' ),
                'stackable' => array( 'stackable/button', 'stackable/cta' )
            )
        );

        return $suggestions[ $section_type ][ $preferred_plugin ] ?? $suggestions[ $section_type ]['core'] ?? array();
    }
}