<?php
/**
 * Block Manager - Gutenberg Block Registration and Management
 * 
 * This file manages all Gutenberg block functionality, including ACF block registration, native block creation,
 * and block editor asset enqueuing. It follows Genesis Custom Blocks patterns to provide a flexible and
 * extensible system for creating custom blocks with both ACF integration and native JavaScript approaches.
 *
 * Block Manager class following Genesis Custom Blocks patterns
 *
 * @package ModernWPPlugin
 */

namespace ModernWPPlugin\Blocks;

/**
 * Block Manager class
 */
class Block_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_blocks' ) );
        add_action( 'acf/init', array( $this, 'register_acf_blocks' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
    }

    /**
     * Register custom blocks
     */
    public function register_blocks() {
        // Register custom Gutenberg blocks
        $this->register_sample_block();
    }

    /**
     * Register ACF blocks
     */
    public function register_acf_blocks() {
        if ( ! function_exists( 'acf_register_block_type' ) ) {
            return;
        }

        // Sample ACF Block
        acf_register_block_type( array(
            'name'            => 'sample-block',
            'title'           => __( 'Sample Block', 'modern-wp-plugin' ),
            'description'     => __( 'A sample custom block with ACF fields.', 'modern-wp-plugin' ),
            'render_template' => MODERN_WP_PLUGIN_PLUGIN_DIR . 'templates/blocks/sample-block.php',
            'category'        => 'modern-wp-plugin',
            'icon'            => 'admin-comments',
            'keywords'        => array( 'sample', 'custom' ),
            'supports'        => array(
                'align' => true,
                'mode'  => false,
                'jsx'   => true,
            ),
            'example'         => array(
                'attributes' => array(
                    'mode' => 'preview',
                    'data' => array(
                        'sample_text' => 'Sample content for preview',
                    ),
                ),
            ),
        ) );

        // Testimonial Block
        acf_register_block_type( array(
            'name'            => 'testimonial',
            'title'           => __( 'Testimonial', 'modern-wp-plugin' ),
            'description'     => __( 'Display a customer testimonial.', 'modern-wp-plugin' ),
            'render_template' => MODERN_WP_PLUGIN_PLUGIN_DIR . 'templates/blocks/testimonial.php',
            'category'        => 'modern-wp-plugin',
            'icon'            => 'format-quote',
            'keywords'        => array( 'testimonial', 'quote', 'review' ),
            'supports'        => array(
                'align' => array( 'left', 'center', 'right' ),
                'mode'  => false,
            ),
        ) );
    }

    /**
     * Register sample block (native Gutenberg)
     */
    private function register_sample_block() {
        wp_register_script(
            'modern-wp-plugin-sample-block',
            MODERN_WP_PLUGIN_PLUGIN_URL . 'assets/js/blocks/sample-block.js',
            array( 'wp-blocks', 'wp-element', 'wp-editor' ),
            MODERN_WP_PLUGIN_VERSION
        );

        register_block_type( 'modern-wp-plugin/sample-block', array(
            'editor_script' => 'modern-wp-plugin-sample-block',
        ) );
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'modern-wp-plugin-blocks',
            MODERN_WP_PLUGIN_PLUGIN_URL . 'assets/js/blocks.js',
            array( 'wp-blocks', 'wp-element', 'wp-editor' ),
            MODERN_WP_PLUGIN_VERSION
        );

        wp_enqueue_style(
            'modern-wp-plugin-blocks-editor',
            MODERN_WP_PLUGIN_PLUGIN_URL . 'assets/css/blocks-editor.css',
            array( 'wp-edit-blocks' ),
            MODERN_WP_PLUGIN_VERSION
        );
    }

    /**
     * Register custom block category
     */
    public function register_block_category( $categories ) {
        return array_merge(
            $categories,
            array(
                array(
                    'slug'  => 'modern-wp-plugin',
                    'title' => __( 'Modern WP Plugin', 'modern-wp-plugin' ),
                ),
            )
        );
    }
}