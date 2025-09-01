<?php
/**
 * Schema Processor - JSON Schema Validation for AI Blueprints
 * 
 * This file contains the Schema_Processor class that handles JSON schema validation
 * for AI Blueprint configurations. It provides comprehensive validation against a
 * predefined schema and returns detailed error reporting for malformed blueprints.
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\Blueprints;

use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

/**
 * Schema Processor class for validating AI Blueprint configurations
 */
class Schema_Processor {

    /**
     * Base JSON schema for AI Blueprints
     *
     * @var array
     */
    private $base_schema = [
        '$schema' => 'https://json-schema.org/draft/2020-12/schema',
        'title' => 'AI Blueprint Schema',
        'type' => 'object',
        'required' => ['sections', 'global_settings'],
        'properties' => [
            'sections' => [
                'type' => 'array',
                'minItems' => 1,
                'items' => [
                    '$ref' => '#/definitions/section'
                ]
            ],
            'global_settings' => [
                '$ref' => '#/definitions/global_settings'
            ],
            'metadata' => [
                '$ref' => '#/definitions/metadata'
            ]
        ],
        'definitions' => [
            'section' => [
                'type' => 'object',
                'required' => ['id', 'type', 'heading'],
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'pattern' => '^[a-z0-9_-]+$',
                        'minLength' => 1,
                        'maxLength' => 50
                    ],
                    'type' => [
                        'type' => 'string',
                        'enum' => [
                            'hero', 'content', 'media_text', 'columns', 
                            'list', 'quote', 'gallery', 'faq', 'cta',
                            'testimonial', 'pricing', 'team', 'custom'
                        ]
                    ],
                    'heading' => [
                        'type' => 'string',
                        'minLength' => 1,
                        'maxLength' => 200
                    ],
                    'heading_level' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 6,
                        'default' => 2
                    ],
                    'word_target' => [
                        'type' => 'integer',
                        'minimum' => 10,
                        'maximum' => 2000,
                        'default' => 150
                    ],
                    'media_policy' => [
                        'type' => 'string',
                        'enum' => ['required', 'optional', 'none'],
                        'default' => 'optional'
                    ],
                    'internal_links' => [
                        'type' => 'integer',
                        'minimum' => 0,
                        'maximum' => 10,
                        'default' => 2
                    ],
                    'citations_required' => [
                        'type' => 'boolean',
                        'default' => true
                    ],
                    'tone' => [
                        'type' => 'string',
                        'enum' => ['professional', 'casual', 'technical', 'friendly', 'authoritative'],
                        'default' => 'professional'
                    ],
                    'allowed_blocks' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string'
                        ],
                        'default' => []
                    ],
                    'block_preferences' => [
                        '$ref' => '#/definitions/block_preferences'
                    ],
                    'custom_prompts' => [
                        'type' => 'object',
                        'properties' => [
                            'system_prompt' => ['type' => 'string'],
                            'user_prompt_template' => ['type' => 'string']
                        ]
                    ]
                ]
            ],
            'block_preferences' => [
                'type' => 'object',
                'properties' => [
                    'preferred_plugin' => [
                        'type' => 'string',
                        'enum' => ['auto', 'core', 'genesis_blocks', 'kadence_blocks', 'stackable', 'ultimate_addons', 'blocksy']
                    ],
                    'primary_block' => [
                        'type' => 'string'
                    ],
                    'fallback_blocks' => [
                        'type' => 'array',
                        'items' => ['type' => 'string']
                    ],
                    'pattern_preference' => [
                        'type' => 'string'
                    ],
                    'custom_attributes' => [
                        'type' => 'object'
                    ]
                ]
            ],
            'global_settings' => [
                'type' => 'object',
                'required' => ['generation_mode'],
                'properties' => [
                    'generation_mode' => [
                        'type' => 'string',
                        'enum' => ['grounded', 'hybrid', 'generative'],
                        'default' => 'hybrid'
                    ],
                    'hybrid_alpha' => [
                        'type' => 'number',
                        'minimum' => 0.0,
                        'maximum' => 1.0,
                        'default' => 0.7
                    ],
                    'mvdb_namespaces' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'enum' => ['content', 'products', 'docs', 'knowledge']
                        ],
                        'default' => ['content']
                    ],
                    'max_tokens_per_section' => [
                        'type' => 'integer',
                        'minimum' => 100,
                        'maximum' => 5000,
                        'default' => 1000
                    ],
                    'image_generation_enabled' => [
                        'type' => 'boolean',
                        'default' => true
                    ],
                    'seo_optimization' => [
                        'type' => 'boolean',
                        'default' => true
                    ],
                    'accessibility_checks' => [
                        'type' => 'boolean',
                        'default' => true
                    ],
                    'cost_limit_usd' => [
                        'type' => 'number',
                        'minimum' => 0.01,
                        'maximum' => 100.0,
                        'default' => 5.0
                    ]
                ]
            ],
            'metadata' => [
                'type' => 'object',
                'properties' => [
                    'version' => [
                        'type' => 'string',
                        'pattern' => '^\d+\.\d+\.\d+$',
                        'default' => '1.0.0'
                    ],
                    'description' => [
                        'type' => 'string',
                        'maxLength' => 500
                    ],
                    'tags' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'maxItems' => 10
                    ],
                    'category' => [
                        'type' => 'string',
                        'enum' => ['landing-page', 'blog-post', 'product-page', 'about-page', 'contact-page', 'custom']
                    ],
                    'estimated_time_minutes' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 120
                    ],
                    'difficulty_level' => [
                        'type' => 'string',
                        'enum' => ['beginner', 'intermediate', 'advanced']
                    ]
                ]
            ]
        ]
    ];

    /**
     * Validate schema against blueprint data
     *
     * @param array $data Blueprint data to validate.
     * @return array Validation result with status and errors.
     */
    public function validate_schema( $data ) {
        if ( ! class_exists( 'JsonSchema\Validator' ) ) {
            return [
                'valid' => false,
                'errors' => [
                    [
                        'property' => 'system',
                        'message' => 'JSON Schema validator not available. Please install justinrainbow/json-schema.',
                        'constraint' => 'dependency'
                    ]
                ]
            ];
        }

        $validator = new Validator();
        $data_object = json_decode( wp_json_encode( $data ) );
        $schema_object = json_decode( wp_json_encode( $this->base_schema ) );

        $validator->validate( $data_object, $schema_object );

        if ( $validator->isValid() ) {
            return [ 'valid' => true, 'errors' => [] ];
        }

        $errors = [];
        foreach ( $validator->getErrors() as $error ) {
            $errors[] = [
                'property' => $error['property'],
                'message' => $error['message'],
                'constraint' => $error['constraint']
            ];
        }

        return [ 'valid' => false, 'errors' => $errors ];
    }

    /**
     * Get the base schema definition
     *
     * @return array The complete schema definition.
     */
    public function get_schema() {
        return $this->base_schema;
    }

    /**
     * Get available section types
     *
     * @return array Available section types with labels.
     */
    public function get_section_types() {
        return [
            'hero' => __( 'Hero Section', 'ai-page-composer' ),
            'content' => __( 'Content Section', 'ai-page-composer' ),
            'media_text' => __( 'Media & Text', 'ai-page-composer' ),
            'columns' => __( 'Columns', 'ai-page-composer' ),
            'list' => __( 'List', 'ai-page-composer' ),
            'quote' => __( 'Quote/Testimonial', 'ai-page-composer' ),
            'gallery' => __( 'Gallery', 'ai-page-composer' ),
            'faq' => __( 'FAQ', 'ai-page-composer' ),
            'cta' => __( 'Call to Action', 'ai-page-composer' ),
            'testimonial' => __( 'Testimonial', 'ai-page-composer' ),
            'pricing' => __( 'Pricing', 'ai-page-composer' ),
            'team' => __( 'Team', 'ai-page-composer' ),
            'custom' => __( 'Custom', 'ai-page-composer' )
        ];
    }

    /**
     * Get available tone options
     *
     * @return array Available tone options with labels.
     */
    public function get_tone_options() {
        return [
            'professional' => __( 'Professional', 'ai-page-composer' ),
            'casual' => __( 'Casual', 'ai-page-composer' ),
            'technical' => __( 'Technical', 'ai-page-composer' ),
            'friendly' => __( 'Friendly', 'ai-page-composer' ),
            'authoritative' => __( 'Authoritative', 'ai-page-composer' )
        ];
    }

    /**
     * Get available generation modes
     *
     * @return array Available generation modes with labels.
     */
    public function get_generation_modes() {
        return [
            'grounded' => __( 'Grounded (MVDB Only)', 'ai-page-composer' ),
            'hybrid' => __( 'Hybrid (Balanced)', 'ai-page-composer' ),
            'generative' => __( 'Generative (AI Only)', 'ai-page-composer' )
        ];
    }

    /**
     * Validate individual section data
     *
     * @param array $section Section data to validate.
     * @return array Validation result.
     */
    public function validate_section( $section ) {
        $section_schema = $this->base_schema['definitions']['section'];
        
        $validator = new Validator();
        $section_object = json_decode( wp_json_encode( $section ) );
        $schema_object = json_decode( wp_json_encode( $section_schema ) );

        $validator->validate( $section_object, $schema_object );

        if ( $validator->isValid() ) {
            return [ 'valid' => true, 'errors' => [] ];
        }

        $errors = [];
        foreach ( $validator->getErrors() as $error ) {
            $errors[] = [
                'property' => $error['property'],
                'message' => $error['message'],
                'constraint' => $error['constraint']
            ];
        }

        return [ 'valid' => false, 'errors' => $errors ];
    }

    /**
     * Apply default values to blueprint data
     *
     * @param array $data Blueprint data.
     * @return array Blueprint data with defaults applied.
     */
    public function apply_defaults( $data ) {
        // Apply global settings defaults
        if ( ! isset( $data['global_settings'] ) ) {
            $data['global_settings'] = [];
        }

        $global_defaults = [
            'generation_mode' => 'hybrid',
            'hybrid_alpha' => 0.7,
            'mvdb_namespaces' => [ 'content' ],
            'max_tokens_per_section' => 1000,
            'image_generation_enabled' => true,
            'seo_optimization' => true,
            'accessibility_checks' => true,
            'cost_limit_usd' => 5.0
        ];

        $data['global_settings'] = wp_parse_args( $data['global_settings'], $global_defaults );

        // Apply section defaults
        if ( isset( $data['sections'] ) && is_array( $data['sections'] ) ) {
            foreach ( $data['sections'] as $index => $section ) {
                $section_defaults = [
                    'heading_level' => 2,
                    'word_target' => 150,
                    'media_policy' => 'optional',
                    'internal_links' => 2,
                    'citations_required' => true,
                    'tone' => 'professional',
                    'allowed_blocks' => [],
                    'block_preferences' => [
                        'preferred_plugin' => 'auto',
                        'primary_block' => '',
                        'fallback_blocks' => [],
                        'pattern_preference' => '',
                        'custom_attributes' => []
                    ]
                ];

                $data['sections'][ $index ] = wp_parse_args( $section, $section_defaults );
            }
        }

        // Apply metadata defaults
        if ( ! isset( $data['metadata'] ) ) {
            $data['metadata'] = [];
        }

        $metadata_defaults = [
            'version' => '1.0.0',
            'description' => '',
            'tags' => [],
            'category' => 'custom',
            'estimated_time_minutes' => 30,
            'difficulty_level' => 'intermediate'
        ];

        $data['metadata'] = wp_parse_args( $data['metadata'], $metadata_defaults );

        return $data;
    }

    /**
     * Sanitize blueprint data
     *
     * @param array $data Blueprint data to sanitize.
     * @return array Sanitized blueprint data.
     */
    public function sanitize_data( $data ) {
        // Sanitize sections
        if ( isset( $data['sections'] ) && is_array( $data['sections'] ) ) {
            foreach ( $data['sections'] as $index => $section ) {
                $data['sections'][ $index ]['id'] = sanitize_key( $section['id'] ?? '' );
                $data['sections'][ $index ]['type'] = sanitize_text_field( $section['type'] ?? 'content' );
                $data['sections'][ $index ]['heading'] = sanitize_text_field( $section['heading'] ?? '' );
                $data['sections'][ $index ]['heading_level'] = absint( $section['heading_level'] ?? 2 );
                $data['sections'][ $index ]['word_target'] = absint( $section['word_target'] ?? 150 );
                $data['sections'][ $index ]['media_policy'] = sanitize_text_field( $section['media_policy'] ?? 'optional' );
                $data['sections'][ $index ]['internal_links'] = absint( $section['internal_links'] ?? 2 );
                $data['sections'][ $index ]['citations_required'] = (bool) ( $section['citations_required'] ?? true );
                $data['sections'][ $index ]['tone'] = sanitize_text_field( $section['tone'] ?? 'professional' );

                // Sanitize allowed_blocks array
                if ( isset( $section['allowed_blocks'] ) && is_array( $section['allowed_blocks'] ) ) {
                    $data['sections'][ $index ]['allowed_blocks'] = array_map( 'sanitize_text_field', $section['allowed_blocks'] );
                }

                // Sanitize block preferences
                if ( isset( $section['block_preferences'] ) && is_array( $section['block_preferences'] ) ) {
                    $prefs = &$data['sections'][ $index ]['block_preferences'];
                    $prefs['preferred_plugin'] = sanitize_text_field( $prefs['preferred_plugin'] ?? 'auto' );
                    $prefs['primary_block'] = sanitize_text_field( $prefs['primary_block'] ?? '' );
                    $prefs['pattern_preference'] = sanitize_text_field( $prefs['pattern_preference'] ?? '' );
                    
                    if ( isset( $prefs['fallback_blocks'] ) && is_array( $prefs['fallback_blocks'] ) ) {
                        $prefs['fallback_blocks'] = array_map( 'sanitize_text_field', $prefs['fallback_blocks'] );
                    }
                }
            }
        }

        // Sanitize global settings
        if ( isset( $data['global_settings'] ) && is_array( $data['global_settings'] ) ) {
            $globals = &$data['global_settings'];
            $globals['generation_mode'] = sanitize_text_field( $globals['generation_mode'] ?? 'hybrid' );
            $globals['hybrid_alpha'] = floatval( $globals['hybrid_alpha'] ?? 0.7 );
            $globals['max_tokens_per_section'] = absint( $globals['max_tokens_per_section'] ?? 1000 );
            $globals['image_generation_enabled'] = (bool) ( $globals['image_generation_enabled'] ?? true );
            $globals['seo_optimization'] = (bool) ( $globals['seo_optimization'] ?? true );
            $globals['accessibility_checks'] = (bool) ( $globals['accessibility_checks'] ?? true );
            $globals['cost_limit_usd'] = floatval( $globals['cost_limit_usd'] ?? 5.0 );

            if ( isset( $globals['mvdb_namespaces'] ) && is_array( $globals['mvdb_namespaces'] ) ) {
                $globals['mvdb_namespaces'] = array_map( 'sanitize_text_field', $globals['mvdb_namespaces'] );
            }
        }

        // Sanitize metadata
        if ( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
            $metadata = &$data['metadata'];
            $metadata['version'] = sanitize_text_field( $metadata['version'] ?? '1.0.0' );
            $metadata['description'] = sanitize_textarea_field( $metadata['description'] ?? '' );
            $metadata['category'] = sanitize_text_field( $metadata['category'] ?? 'custom' );
            $metadata['estimated_time_minutes'] = absint( $metadata['estimated_time_minutes'] ?? 30 );
            $metadata['difficulty_level'] = sanitize_text_field( $metadata['difficulty_level'] ?? 'intermediate' );

            if ( isset( $metadata['tags'] ) && is_array( $metadata['tags'] ) ) {
                $metadata['tags'] = array_map( 'sanitize_text_field', $metadata['tags'] );
            }
        }

        return $data;
    }
}