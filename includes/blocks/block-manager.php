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
 * @package AIPageComposer
 */

namespace AIPageComposer\Blocks;

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
            'title'           => __( 'Sample Block', 'ai-page-composer' ),
            'description'     => __( 'A sample custom block with ACF fields.', 'ai-page-composer' ),
            'render_template' => AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/blocks/sample-block.php',
            'category'        => 'ai-page-composer',
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
            'title'           => __( 'Testimonial', 'ai-page-composer' ),
            'description'     => __( 'Display a customer testimonial.', 'ai-page-composer' ),
            'render_template' => AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/blocks/testimonial.php',
            'category'        => 'ai-page-composer',
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
            'ai-page-composer-sample-block',
            AI_PAGE_COMPOSER_PLUGIN_URL . 'assets/js/blocks/sample-block.js',
            array( 'wp-blocks', 'wp-element', 'wp-editor' ),
            AI_PAGE_COMPOSER_VERSION
        );

        register_block_type( 'ai-page-composer/sample-block', array(
            'editor_script' => 'ai-page-composer-sample-block',
        ) );
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'ai-page-composer-blocks',
            AI_PAGE_COMPOSER_PLUGIN_URL . 'assets/js/blocks.js',
            array( 'wp-blocks', 'wp-element', 'wp-editor' ),
            AI_PAGE_COMPOSER_VERSION
        );

        wp_enqueue_style(
            'ai-page-composer-blocks-editor',
            AI_PAGE_COMPOSER_PLUGIN_URL . 'assets/css/blocks-editor.css',
            array( 'wp-edit-blocks' ),
            AI_PAGE_COMPOSER_VERSION
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
                    'slug'  => 'ai-page-composer',
                    'title' => __( 'AI Page Composer', 'ai-page-composer' ),
                ),
            )
        );
    }
}