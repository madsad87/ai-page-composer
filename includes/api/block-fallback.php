<?php
/**
 * Block Fallback Class - Provide graceful fallbacks when preferred blocks are unavailable
 * 
 * This class handles fallback logic when preferred plugin blocks are not available,
 * ensuring content can always be assembled using alternative blocks or core WordPress blocks.
 *
 * @package AIPageComposer\API
 */

namespace AIPageComposer\API;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Block Fallback class for graceful block substitution
 */
class Block_Fallback {

    /**
     * Fallback priority order for plugins
     *
     * @var array
     */
    private $plugin_priority = [
        'kadence_blocks',
        'genesis_blocks',
        'stackable',
        'ultimate_addons',
        'generateblocks',
        'core'
    ];

    /**
     * Block type mappings for fallbacks
     *
     * @var array
     */
    private $fallback_mappings = [
        'hero' => [
            'kadence_blocks' => ['kadence/rowlayout', 'kadence/advancedheading'],
            'genesis_blocks' => ['genesis-blocks/gb-container', 'genesis-blocks/gb-cta'],
            'stackable' => ['ugb/hero', 'ugb/container'],
            'ultimate_addons' => ['uagb/container', 'uagb/call-to-action'],
            'generateblocks' => ['generateblocks/container', 'generateblocks/headline'],
            'core' => ['core/cover', 'core/group', 'core/heading']
        ],
        'content' => [
            'kadence_blocks' => ['kadence/advancedheading', 'kadence/column'],
            'genesis_blocks' => ['genesis-blocks/gb-container'],
            'stackable' => ['ugb/heading', 'ugb/container'],
            'ultimate_addons' => ['uagb/advanced-heading', 'uagb/container'],
            'generateblocks' => ['generateblocks/headline', 'generateblocks/container'],
            'core' => ['core/heading', 'core/paragraph', 'core/group']
        ],
        'image' => [
            'kadence_blocks' => ['kadence/image', 'kadence/gallery'],
            'genesis_blocks' => ['genesis-blocks/gb-container'],
            'stackable' => ['ugb/image'],
            'ultimate_addons' => ['uagb/image'],
            'generateblocks' => ['generateblocks/image'],
            'core' => ['core/image', 'core/gallery', 'core/media-text']
        ],
        'testimonial' => [
            'kadence_blocks' => ['kadence/testimonials'],
            'genesis_blocks' => ['genesis-blocks/gb-testimonial'],
            'stackable' => ['ugb/testimonial'],
            'ultimate_addons' => ['uagb/testimonial'],
            'generateblocks' => ['generateblocks/container'],
            'core' => ['core/quote', 'core/group']
        ],
        'list' => [
            'kadence_blocks' => ['kadence/iconlist'],
            'genesis_blocks' => ['genesis-blocks/gb-container'],
            'stackable' => ['ugb/container'],
            'ultimate_addons' => ['uagb/icon-list'],
            'generateblocks' => ['generateblocks/container'],
            'core' => ['core/list', 'core/group']
        ],
        'button' => [
            'kadence_blocks' => ['kadence/button'],
            'genesis_blocks' => ['genesis-blocks/gb-button'],
            'stackable' => ['ugb/button'],
            'ultimate_addons' => ['uagb/buttons'],
            'generateblocks' => ['generateblocks/button'],
            'core' => ['core/button', 'core/buttons']
        ],
        'form' => [
            'kadence_blocks' => ['kadence/form'],
            'genesis_blocks' => ['genesis-blocks/gb-newsletter'],
            'stackable' => ['ugb/container'],
            'ultimate_addons' => ['uagb/forms'],
            'generateblocks' => ['generateblocks/container'],
            'core' => ['core/group', 'core/paragraph']
        ],
        'layout' => [
            'kadence_blocks' => ['kadence/rowlayout', 'kadence/column'],
            'genesis_blocks' => ['genesis-blocks/gb-container', 'genesis-blocks/gb-columns'],
            'stackable' => ['ugb/container', 'ugb/columns'],
            'ultimate_addons' => ['uagb/container'],
            'generateblocks' => ['generateblocks/container', 'generateblocks/grid'],
            'core' => ['core/group', 'core/columns']
        ]
    ];

    /**
     * Block feature compatibility matrix
     *
     * @var array
     */
    private $feature_compatibility = [
        'background_image' => [
            'kadence/rowlayout' => true,
            'genesis-blocks/gb-container' => true,
            'ugb/container' => true,
            'uagb/container' => true,
            'generateblocks/container' => true,
            'core/cover' => true,
            'core/group' => false
        ],
        'advanced_styling' => [
            'kadence_blocks' => true,
            'genesis_blocks' => true,
            'stackable' => true,
            'ultimate_addons' => true,
            'generateblocks' => true,
            'core' => false
        ],
        'responsive_controls' => [
            'kadence_blocks' => true,
            'genesis_blocks' => true,
            'stackable' => true,
            'ultimate_addons' => true,
            'generateblocks' => true,
            'core' => false
        ]
    ];

    /**
     * Get fallback block for content type
     *
     * @param string $content_type Content type needing fallback.
     * @param array  $available_blocks Available blocks by plugin.
     * @param array  $required_features Optional required features.
     * @return array Fallback block information.
     */
    public function get_fallback_block( $content_type, $available_blocks, $required_features = [] ) {
        $fallback_options = $this->fallback_mappings[ $content_type ] ?? $this->fallback_mappings['content'];

        // Try plugins in priority order
        foreach ( $this->plugin_priority as $plugin_key ) {
            if ( ! isset( $available_blocks[ $plugin_key ] ) ) {
                continue;
            }

            $plugin_blocks = $available_blocks[ $plugin_key ];
            $fallback_blocks = $fallback_options[ $plugin_key ] ?? [];

            // Check each fallback block for this plugin
            foreach ( $fallback_blocks as $block_name ) {
                if ( in_array( $block_name, $plugin_blocks, true ) ) {
                    // Check if block meets required features
                    if ( $this->block_meets_requirements( $block_name, $required_features ) ) {
                        return [
                            'plugin' => $plugin_key,
                            'block_name' => $block_name,
                            'is_fallback' => true,
                            'fallback_reason' => $this->get_fallback_reason( $content_type, $plugin_key )
                        ];
                    }
                }
            }
        }

        // Last resort: use core paragraph
        return [
            'plugin' => 'core',
            'block_name' => 'core/paragraph',
            'is_fallback' => true,
            'fallback_reason' => __( 'No suitable blocks available, using core paragraph', 'ai-page-composer' )
        ];
    }

    /**
     * Check if block meets feature requirements
     *
     * @param string $block_name Block name to check.
     * @param array  $required_features Required features.
     * @return bool True if block meets requirements.
     */
    private function block_meets_requirements( $block_name, $required_features ) {
        if ( empty( $required_features ) ) {
            return true;
        }

        foreach ( $required_features as $feature ) {
            if ( ! $this->block_supports_feature( $block_name, $feature ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if block supports specific feature
     *
     * @param string $block_name Block name.
     * @param string $feature Feature to check.
     * @return bool True if feature is supported.
     */
    private function block_supports_feature( $block_name, $feature ) {
        // Check direct block mapping
        if ( isset( $this->feature_compatibility[ $feature ][ $block_name ] ) ) {
            return $this->feature_compatibility[ $feature ][ $block_name ];
        }

        // Check plugin-level compatibility
        $plugin_key = $this->extract_plugin_from_block( $block_name );
        if ( isset( $this->feature_compatibility[ $feature ][ $plugin_key ] ) ) {
            return $this->feature_compatibility[ $feature ][ $plugin_key ];
        }

        // Default based on block type
        return $this->guess_feature_support( $block_name, $feature );
    }

    /**
     * Extract plugin key from block name
     *
     * @param string $block_name Block name.
     * @return string Plugin key.
     */
    private function extract_plugin_from_block( $block_name ) {
        $prefix_mappings = [
            'kadence/' => 'kadence_blocks',
            'genesis-blocks/' => 'genesis_blocks',
            'ugb/' => 'stackable',
            'uagb/' => 'ultimate_addons',
            'generateblocks/' => 'generateblocks',
            'core/' => 'core'
        ];

        foreach ( $prefix_mappings as $prefix => $plugin ) {
            if ( strpos( $block_name, $prefix ) === 0 ) {
                return $plugin;
            }
        }

        return 'unknown';
    }

    /**
     * Guess feature support based on block name and type
     *
     * @param string $block_name Block name.
     * @param string $feature Feature to check.
     * @return bool Estimated feature support.
     */
    private function guess_feature_support( $block_name, $feature ) {
        $block_lower = strtolower( $block_name );

        switch ( $feature ) {
            case 'background_image':
                return strpos( $block_lower, 'container' ) !== false ||
                       strpos( $block_lower, 'row' ) !== false ||
                       strpos( $block_lower, 'hero' ) !== false ||
                       strpos( $block_lower, 'cover' ) !== false;

            case 'advanced_styling':
                return strpos( $block_name, 'core/' ) !== 0; // Non-core blocks usually have advanced styling

            case 'responsive_controls':
                return strpos( $block_name, 'core/' ) !== 0; // Non-core blocks usually have responsive controls

            default:
                return false;
        }
    }

    /**
     * Get fallback reason message
     *
     * @param string $content_type Content type.
     * @param string $fallback_plugin Plugin used for fallback.
     * @return string Fallback reason message.
     */
    private function get_fallback_reason( $content_type, $fallback_plugin ) {
        $messages = [
            'kadence_blocks' => __( 'Using Kadence Blocks as fallback', 'ai-page-composer' ),
            'genesis_blocks' => __( 'Using Genesis Blocks as fallback', 'ai-page-composer' ),
            'stackable' => __( 'Using Stackable as fallback', 'ai-page-composer' ),
            'ultimate_addons' => __( 'Using Ultimate Addons as fallback', 'ai-page-composer' ),
            'generateblocks' => __( 'Using GenerateBlocks as fallback', 'ai-page-composer' ),
            'core' => __( 'Using WordPress core blocks as fallback', 'ai-page-composer' )
        ];

        return $messages[ $fallback_plugin ] ?? __( 'Using alternative block', 'ai-page-composer' );
    }

    /**
     * Get alternative blocks for specific block
     *
     * @param string $original_block Original block that's unavailable.
     * @param array  $available_blocks Available blocks by plugin.
     * @return array Alternative block suggestions.
     */
    public function get_alternative_blocks( $original_block, $available_blocks ) {
        $alternatives = [];
        $content_type = $this->determine_content_type_from_block( $original_block );
        
        $fallback_options = $this->fallback_mappings[ $content_type ] ?? [];

        foreach ( $this->plugin_priority as $plugin_key ) {
            if ( ! isset( $available_blocks[ $plugin_key ] ) ) {
                continue;
            }

            $plugin_blocks = $available_blocks[ $plugin_key ];
            $plugin_fallbacks = $fallback_options[ $plugin_key ] ?? [];

            foreach ( $plugin_fallbacks as $block_name ) {
                if ( in_array( $block_name, $plugin_blocks, true ) && $block_name !== $original_block ) {
                    $alternatives[] = [
                        'block_name' => $block_name,
                        'plugin' => $plugin_key,
                        'similarity_score' => $this->calculate_similarity_score( $original_block, $block_name ),
                        'features_preserved' => $this->compare_block_features( $original_block, $block_name )
                    ];
                }
            }
        }

        // Sort by similarity score
        usort( $alternatives, function( $a, $b ) {
            return $b['similarity_score'] - $a['similarity_score'];
        });

        return $alternatives;
    }

    /**
     * Determine content type from block name
     *
     * @param string $block_name Block name.
     * @return string Determined content type.
     */
    private function determine_content_type_from_block( $block_name ) {
        $block_lower = strtolower( $block_name );

        if ( strpos( $block_lower, 'hero' ) !== false || strpos( $block_lower, 'banner' ) !== false ) {
            return 'hero';
        }

        if ( strpos( $block_lower, 'testimonial' ) !== false ) {
            return 'testimonial';
        }

        if ( strpos( $block_lower, 'image' ) !== false || strpos( $block_lower, 'gallery' ) !== false ) {
            return 'image';
        }

        if ( strpos( $block_lower, 'button' ) !== false ) {
            return 'button';
        }

        if ( strpos( $block_lower, 'form' ) !== false ) {
            return 'form';
        }

        if ( strpos( $block_lower, 'row' ) !== false || 
             strpos( $block_lower, 'column' ) !== false || 
             strpos( $block_lower, 'container' ) !== false ) {
            return 'layout';
        }

        return 'content';
    }

    /**
     * Calculate similarity score between two blocks
     *
     * @param string $block1 First block name.
     * @param string $block2 Second block name.
     * @return int Similarity score (0-100).
     */
    private function calculate_similarity_score( $block1, $block2 ) {
        $score = 0;

        // Same plugin bonus
        $plugin1 = $this->extract_plugin_from_block( $block1 );
        $plugin2 = $this->extract_plugin_from_block( $block2 );
        if ( $plugin1 === $plugin2 ) {
            $score += 20;
        }

        // Block name similarity
        $name1 = $this->extract_block_name( $block1 );
        $name2 = $this->extract_block_name( $block2 );
        
        $name_similarity = similar_text( $name1, $name2, $percentage );
        $score += intval( $percentage * 0.8 );

        // Feature compatibility
        $common_features = $this->count_common_features( $block1, $block2 );
        $score += $common_features * 10;

        return min( 100, $score );
    }

    /**
     * Extract block name without prefix
     *
     * @param string $block_name Full block name.
     * @return string Block name without prefix.
     */
    private function extract_block_name( $block_name ) {
        $parts = explode( '/', $block_name );
        return end( $parts );
    }

    /**
     * Count common features between two blocks
     *
     * @param string $block1 First block name.
     * @param string $block2 Second block name.
     * @return int Number of common features.
     */
    private function count_common_features( $block1, $block2 ) {
        $common = 0;
        $features = array_keys( $this->feature_compatibility );

        foreach ( $features as $feature ) {
            $supports1 = $this->block_supports_feature( $block1, $feature );
            $supports2 = $this->block_supports_feature( $block2, $feature );
            
            if ( $supports1 === $supports2 ) {
                $common++;
            }
        }

        return $common;
    }

    /**
     * Compare features between two blocks
     *
     * @param string $original_block Original block.
     * @param string $alternative_block Alternative block.
     * @return array Features comparison.
     */
    private function compare_block_features( $original_block, $alternative_block ) {
        $comparison = [
            'preserved' => [],
            'lost' => [],
            'gained' => []
        ];

        $features = array_keys( $this->feature_compatibility );

        foreach ( $features as $feature ) {
            $original_support = $this->block_supports_feature( $original_block, $feature );
            $alternative_support = $this->block_supports_feature( $alternative_block, $feature );

            if ( $original_support === $alternative_support ) {
                $comparison['preserved'][] = $feature;
            } elseif ( $original_support && ! $alternative_support ) {
                $comparison['lost'][] = $feature;
            } elseif ( ! $original_support && $alternative_support ) {
                $comparison['gained'][] = $feature;
            }
        }

        return $comparison;
    }

    /**
     * Generate fallback recommendations
     *
     * @param array $content_sections Content sections to analyze.
     * @param array $available_blocks Available blocks.
     * @return array Fallback recommendations.
     */
    public function generate_fallback_recommendations( $content_sections, $available_blocks ) {
        $recommendations = [];

        foreach ( $content_sections as $index => $section ) {
            $content_type = $section['type'] ?? 'content';
            $required_features = $section['required_features'] ?? [];

            $fallback = $this->get_fallback_block( $content_type, $available_blocks, $required_features );
            
            $recommendations[ $index ] = [
                'section_type' => $content_type,
                'recommended_block' => $fallback,
                'confidence_score' => $this->calculate_confidence_score( $fallback, $content_type ),
                'alternatives' => $this->get_alternative_blocks( $fallback['block_name'], $available_blocks )
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate confidence score for fallback recommendation
     *
     * @param array  $fallback Fallback block information.
     * @param string $content_type Original content type.
     * @return int Confidence score (0-100).
     */
    private function calculate_confidence_score( $fallback, $content_type ) {
        $score = 50; // Base score

        // Plugin quality bonus
        $plugin_scores = [
            'kadence_blocks' => 25,
            'genesis_blocks' => 20,
            'stackable' => 20,
            'ultimate_addons' => 20,
            'generateblocks' => 15,
            'core' => 10
        ];

        $score += $plugin_scores[ $fallback['plugin'] ] ?? 0;

        // Content type match bonus
        $block_name = strtolower( $fallback['block_name'] );
        $type_keywords = [
            'hero' => ['hero', 'banner', 'cover'],
            'testimonial' => ['testimonial', 'review'],
            'image' => ['image', 'gallery'],
            'button' => ['button', 'cta'],
            'form' => ['form', 'contact']
        ];

        $keywords = $type_keywords[ $content_type ] ?? [];
        foreach ( $keywords as $keyword ) {
            if ( strpos( $block_name, $keyword ) !== false ) {
                $score += 15;
                break;
            }
        }

        return min( 100, $score );
    }
}