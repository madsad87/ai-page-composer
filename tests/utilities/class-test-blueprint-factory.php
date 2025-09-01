<?php
/**
 * Test Blueprint Factory - Helper for Creating Test Blueprints
 *
 * @package AIPageComposer\Tests
 */

namespace AIPageComposer\Tests\Utilities;

use WP_UnitTest_Factory_For_Post;

/**
 * Test Blueprint Factory class
 */
class Test_Blueprint_Factory extends WP_UnitTest_Factory_For_Post {

    /**
     * Create a test blueprint
     *
     * @param array $args Blueprint arguments.
     * @return int Blueprint post ID.
     */
    public function create_blueprint( $args = array() ) {
        $default_args = array(
            'post_type' => 'ai_blueprint',
            'post_title' => 'Test Blueprint',
            'post_status' => 'publish',
        );

        $args = wp_parse_args( $args, $default_args );
        $post_id = $this->create_object( $args );

        // Add default blueprint data
        $default_schema = $this->get_default_blueprint_schema();
        
        if ( isset( $args['blueprint_schema'] ) ) {
            $schema = wp_parse_args( $args['blueprint_schema'], $default_schema );
        } else {
            $schema = $default_schema;
        }

        update_post_meta( $post_id, '_ai_blueprint_schema', $schema );
        update_post_meta( $post_id, '_ai_blueprint_sections', $schema['sections'] );
        update_post_meta( $post_id, '_ai_blueprint_global_settings', $schema['global_settings'] );
        update_post_meta( $post_id, '_ai_blueprint_metadata', $schema['metadata'] );

        return $post_id;
    }

    /**
     * Get default blueprint schema for testing
     *
     * @return array Default schema.
     */
    public function get_default_blueprint_schema() {
        return array(
            'sections' => array(
                array(
                    'id' => 'test-section-1',
                    'type' => 'content',
                    'heading' => 'Test Section 1',
                    'heading_level' => 2,
                    'word_target' => 150,
                    'media_policy' => 'optional',
                    'internal_links' => 2,
                    'citations_required' => true,
                    'tone' => 'professional',
                    'allowed_blocks' => array(),
                    'block_preferences' => array(
                        'preferred_plugin' => 'auto',
                        'primary_block' => '',
                        'fallback_blocks' => array(),
                        'pattern_preference' => '',
                        'custom_attributes' => array()
                    )
                ),
                array(
                    'id' => 'test-section-2',
                    'type' => 'cta',
                    'heading' => 'Test Call to Action',
                    'heading_level' => 2,
                    'word_target' => 75,
                    'media_policy' => 'none',
                    'internal_links' => 0,
                    'citations_required' => false,
                    'tone' => 'friendly',
                    'allowed_blocks' => array(),
                    'block_preferences' => array(
                        'preferred_plugin' => 'auto',
                        'primary_block' => '',
                        'fallback_blocks' => array(),
                        'pattern_preference' => '',
                        'custom_attributes' => array()
                    )
                )
            ),
            'global_settings' => array(
                'generation_mode' => 'hybrid',
                'hybrid_alpha' => 0.7,
                'mvdb_namespaces' => array( 'content' ),
                'max_tokens_per_section' => 1000,
                'image_generation_enabled' => true,
                'seo_optimization' => true,
                'accessibility_checks' => true,
                'cost_limit_usd' => 5.0
            ),
            'metadata' => array(
                'version' => '1.0.0',
                'description' => 'Test blueprint for unit testing',
                'tags' => array( 'test', 'unit-test' ),
                'category' => 'custom',
                'estimated_time_minutes' => 30,
                'difficulty_level' => 'intermediate'
            )
        );
    }

    /**
     * Get invalid blueprint schema for testing validation
     *
     * @return array Invalid schema.
     */
    public function get_invalid_blueprint_schema() {
        return array(
            'sections' => array(
                array(
                    'id' => '', // Invalid: empty ID
                    'type' => 'invalid_type', // Invalid: not in enum
                    'heading' => '', // Invalid: empty heading
                    'word_target' => -1, // Invalid: negative value
                )
            ),
            'global_settings' => array(
                'generation_mode' => 'invalid_mode', // Invalid: not in enum
                'hybrid_alpha' => 2.0, // Invalid: out of range
            )
        );
    }
}