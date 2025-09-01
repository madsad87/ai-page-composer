<?php
/**
 * Block Resolver Class - Block Type Detection and Preferences
 * 
 * This file contains the Block_Resolver class that handles block type detection,
 * preference resolution, and block specification generation for the section
 * generation system. It integrates with the existing Block_Preferences system.
 *
 * @package AIPageComposer\API
 */

namespace AIPageComposer\API;

use AIPageComposer\Admin\Block_Preferences;
use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Block Resolver class for block type detection and preferences
 */
class Block_Resolver {

    /**
     * Block preferences instance
     *
     * @var Block_Preferences
     */
    private $block_preferences;

    /**
     * Section type to block mappings
     *
     * @var array
     */
    private $section_block_mappings = [
        'hero' => [
            'kadence_blocks' => 'kadence/rowlayout',
            'genesis_blocks' => 'genesis-blocks/gb-container',
            'stackable' => 'ugb/hero',
            'ultimate_addons' => 'uagb/advanced-heading',
            'core' => 'core/cover'
        ],
        'content' => [
            'kadence_blocks' => 'kadence/rowlayout',
            'genesis_blocks' => 'genesis-blocks/gb-container',
            'stackable' => 'ugb/text',
            'ultimate_addons' => 'uagb/info-box',
            'core' => 'core/group'
        ],
        'testimonial' => [
            'kadence_blocks' => 'kadence/testimonials',
            'genesis_blocks' => 'genesis-blocks/gb-testimonial',
            'stackable' => 'ugb/testimonial',
            'ultimate_addons' => 'uagb/testimonial',
            'core' => 'core/quote'
        ],
        'pricing' => [
            'kadence_blocks' => 'kadence/pricelist',
            'genesis_blocks' => 'genesis-blocks/gb-pricing',
            'stackable' => 'ugb/pricing-box',
            'ultimate_addons' => 'uagb/restaurant-menu',
            'core' => 'core/table'
        ],
        'team' => [
            'kadence_blocks' => 'kadence/rowlayout',
            'genesis_blocks' => 'genesis-blocks/gb-profile-box',
            'stackable' => 'ugb/team-member',
            'ultimate_addons' => 'uagb/team',
            'core' => 'core/media-text'
        ],
        'faq' => [
            'kadence_blocks' => 'kadence/accordion',
            'genesis_blocks' => 'genesis-blocks/gb-accordion',
            'stackable' => 'ugb/expand',
            'ultimate_addons' => 'uagb/faq',
            'core' => 'core/details'
        ],
        'cta' => [
            'kadence_blocks' => 'kadence/advancedbtn',
            'genesis_blocks' => 'genesis-blocks/gb-button',
            'stackable' => 'ugb/cta',
            'ultimate_addons' => 'uagb/call-to-action',
            'core' => 'core/buttons'
        ],
        'feature' => [
            'kadence_blocks' => 'kadence/iconlist',
            'genesis_blocks' => 'genesis-blocks/gb-columns',
            'stackable' => 'ugb/feature',
            'ultimate_addons' => 'uagb/info-box',
            'core' => 'core/columns'
        ]
    ];

    /**
     * Block attribute specifications
     *
     * @var array
     */
    private $block_attributes = [
        'kadence/rowlayout' => [
            'uniqueID' => '',
            'columns' => 1,
            'padding' => [ '20', '20', '20', '20' ],
            'backgroundImg' => [],
            'backgroundOverlay' => []
        ],
        'kadence/testimonials' => [
            'uniqueID' => '',
            'testimonialCount' => 1,
            'layout' => 'simple',
            'displayTitle' => true
        ],
        'kadence/accordion' => [
            'uniqueID' => '',
            'paneCount' => 3,
            'startClosed' => false,
            'showIcon' => true
        ],
        'genesis-blocks/gb-container' => [
            'containerPaddingTop' => 20,
            'containerPaddingBottom' => 20,
            'containerMaxWidth' => 1200,
            'containerImgID' => 0
        ],
        'core/cover' => [
            'dimRatio' => 30,
            'minHeight' => 400,
            'contentPosition' => 'center center',
            'backgroundType' => 'image'
        ],
        'core/group' => [
            'layout' => [ 'type' => 'constrained' ],
            'style' => []
        ],
        'core/columns' => [
            'columns' => 2,
            'isStackedOnMobile' => true
        ]
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->block_preferences = new Block_Preferences();
    }

    /**
     * Resolve block type based on preferences and availability
     *
     * @param array $preferences Block preferences from request.
     * @return array Block specification.
     */
    public function resolve_block_type( $preferences ) {
        // Extract preferences
        $preferred_plugin = $preferences['preferred_plugin'] ?? '';
        $section_type = $preferences['section_type'] ?? 'content';
        $fallback_blocks = $preferences['fallback_blocks'] ?? [];
        $custom_attributes = $preferences['custom_attributes'] ?? [];

        // Get detected plugins
        $detected_plugins = $this->block_preferences->get_detected_plugins();
        
        // Build priority list
        $priority_list = $this->build_priority_list( $section_type, $preferred_plugin, $detected_plugins );
        
        // Resolve best available block
        $resolved_block = $this->resolve_best_block( $priority_list, $fallback_blocks );
        
        // Build block specification
        return $this->build_block_specification( $resolved_block, $section_type, $custom_attributes );
    }

    /**
     * Build priority list for block selection
     *
     * @param string $section_type Section type.
     * @param string $preferred_plugin Preferred plugin.
     * @param array  $detected_plugins Detected plugins.
     * @return array Priority list.
     */
    private function build_priority_list( $section_type, $preferred_plugin, $detected_plugins ) {
        $priority_list = [];
        
        // Get section mappings
        $section_mappings = $this->section_block_mappings[ $section_type ] ?? $this->section_block_mappings['content'];
        
        foreach ( $section_mappings as $plugin_key => $block_name ) {
            $plugin_data = $detected_plugins[ $plugin_key ] ?? null;
            
            if ( ! $plugin_data || ! $plugin_data['active'] ) {
                continue;
            }
            
            $priority_score = $this->calculate_priority_score( $plugin_key, $preferred_plugin, $plugin_data, $section_type );
            
            $priority_list[] = [
                'plugin_key' => $plugin_key,
                'block_name' => $block_name,
                'priority_score' => $priority_score,
                'plugin_data' => $plugin_data
            ];
        }
        
        // Sort by priority score (highest first)
        usort( $priority_list, function( $a, $b ) {
            return $b['priority_score'] <=> $a['priority_score'];
        } );
        
        return $priority_list;
    }

    /**
     * Calculate priority score for a plugin/block combination
     *
     * @param string $plugin_key Plugin key.
     * @param string $preferred_plugin Preferred plugin.
     * @param array  $plugin_data Plugin data.
     * @param string $section_type Section type.
     * @return int Priority score.
     */
    private function calculate_priority_score( $plugin_key, $preferred_plugin, $plugin_data, $section_type ) {
        $score = 0;
        
        // User preference bonus
        if ( $plugin_key === $preferred_plugin ) {
            $score += 100;
        }
        
        // Plugin active bonus
        if ( $plugin_data['active'] ) {
            $score += 50;
        }
        
        // Section support bonus
        if ( in_array( $section_type, $plugin_data['supported_sections'] ?? [] ) ) {
            $score += 30;
        }
        
        // Plugin priority score
        $score += $plugin_data['priority'] ?? 0;
        
        // Block availability bonus
        if ( $this->is_block_registered( $this->section_block_mappings[ $section_type ][ $plugin_key ] ?? '' ) ) {
            $score += 20;
        }
        
        // Core blocks get lower priority unless specifically preferred
        if ( $plugin_key === 'core' && $preferred_plugin !== 'core' ) {
            $score -= 10;
        }
        
        return $score;
    }

    /**
     * Check if a block is registered
     *
     * @param string $block_name Block name.
     * @return bool True if block is registered.
     */
    private function is_block_registered( $block_name ) {
        if ( empty( $block_name ) ) {
            return false;
        }
        
        $registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();
        return isset( $registered_blocks[ $block_name ] );
    }

    /**
     * Resolve best available block from priority list
     *
     * @param array $priority_list Priority list.
     * @param array $fallback_blocks Fallback blocks.
     * @return array Resolved block data.
     */
    private function resolve_best_block( $priority_list, $fallback_blocks ) {
        // Try priority list first
        foreach ( $priority_list as $block_option ) {
            if ( $this->is_block_registered( $block_option['block_name'] ) ) {
                return [
                    'plugin_key' => $block_option['plugin_key'],
                    'block_name' => $block_option['block_name'],
                    'plugin_data' => $block_option['plugin_data'],
                    'fallback_used' => false
                ];
            }
        }
        
        // Try fallback blocks
        foreach ( $fallback_blocks as $fallback_block ) {
            if ( $this->is_block_registered( $fallback_block ) ) {
                return [
                    'plugin_key' => $this->guess_plugin_from_block_name( $fallback_block ),
                    'block_name' => $fallback_block,
                    'plugin_data' => null,
                    'fallback_used' => true
                ];
            }
        }
        
        // Final fallback to core blocks
        $core_fallbacks = [
            'core/group',
            'core/columns',
            'core/paragraph'
        ];
        
        foreach ( $core_fallbacks as $core_block ) {
            if ( $this->is_block_registered( $core_block ) ) {
                return [
                    'plugin_key' => 'core',
                    'block_name' => $core_block,
                    'plugin_data' => [ 'name' => 'WordPress Core Blocks', 'namespace' => 'core' ],
                    'fallback_used' => true
                ];
            }
        }
        
        // Absolute fallback
        return [
            'plugin_key' => 'core',
            'block_name' => 'core/paragraph',
            'plugin_data' => [ 'name' => 'WordPress Core Blocks', 'namespace' => 'core' ],
            'fallback_used' => true
        ];
    }

    /**
     * Guess plugin from block name
     *
     * @param string $block_name Block name.
     * @return string Plugin key.
     */
    private function guess_plugin_from_block_name( $block_name ) {
        $namespace_mappings = [
            'kadence' => 'kadence_blocks',
            'genesis-blocks' => 'genesis_blocks',
            'ugb' => 'stackable',
            'uagb' => 'ultimate_addons',
            'core' => 'core'
        ];
        
        $namespace = explode( '/', $block_name )[0] ?? '';
        
        return $namespace_mappings[ $namespace ] ?? 'unknown';
    }

    /**
     * Build block specification
     *
     * @param array  $resolved_block Resolved block data.
     * @param string $section_type Section type.
     * @param array  $custom_attributes Custom attributes.
     * @return array Block specification.
     */
    private function build_block_specification( $resolved_block, $section_type, $custom_attributes ) {
        $block_name = $resolved_block['block_name'];
        $plugin_data = $resolved_block['plugin_data'];
        
        // Get default attributes for the block
        $default_attributes = $this->block_attributes[ $block_name ] ?? [];
        
        // Merge with custom attributes
        $attributes = array_merge( $default_attributes, $custom_attributes );
        
        // Build specification
        return [
            'block_name' => $block_name,
            'plugin' => $plugin_data['name'] ?? 'Unknown Plugin',
            'plugin_key' => $resolved_block['plugin_key'],
            'namespace' => $plugin_data['namespace'] ?? explode( '/', $block_name )[0],
            'section_type' => $section_type,
            'attributes' => $attributes,
            'fallback_used' => $resolved_block['fallback_used'],
            'supports_inner_blocks' => $this->block_supports_inner_blocks( $block_name ),
            'is_container' => $this->is_container_block( $block_name )
        ];
    }

    /**
     * Check if block supports inner blocks
     *
     * @param string $block_name Block name.
     * @return bool True if block supports inner blocks.
     */
    private function block_supports_inner_blocks( $block_name ) {
        $inner_block_supported = [
            'kadence/rowlayout',
            'genesis-blocks/gb-container',
            'core/group',
            'core/cover',
            'core/columns',
            'core/column'
        ];
        
        return in_array( $block_name, $inner_block_supported );
    }

    /**
     * Check if block is a container block
     *
     * @param string $block_name Block name.
     * @return bool True if block is a container.
     */
    private function is_container_block( $block_name ) {
        $container_blocks = [
            'kadence/rowlayout',
            'genesis-blocks/gb-container',
            'core/group',
            'core/cover',
            'core/columns'
        ];
        
        return in_array( $block_name, $container_blocks );
    }

    /**
     * Get available blocks for section type
     *
     * @param string $section_type Section type.
     * @return array Available blocks.
     */
    public function get_available_blocks_for_section( $section_type ) {
        $detected_plugins = $this->block_preferences->get_detected_plugins();
        $section_mappings = $this->section_block_mappings[ $section_type ] ?? [];
        
        $available_blocks = [];
        
        foreach ( $section_mappings as $plugin_key => $block_name ) {
            $plugin_data = $detected_plugins[ $plugin_key ] ?? null;
            
            if ( $plugin_data && $plugin_data['active'] && $this->is_block_registered( $block_name ) ) {
                $available_blocks[] = [
                    'plugin_key' => $plugin_key,
                    'plugin_name' => $plugin_data['name'],
                    'block_name' => $block_name,
                    'priority' => $plugin_data['priority']
                ];
            }
        }
        
        return $available_blocks;
    }

    /**
     * Get block specification by name
     *
     * @param string $block_name Block name.
     * @param string $section_type Section type.
     * @return array Block specification.
     */
    public function get_block_specification_by_name( $block_name, $section_type = 'content' ) {
        $plugin_key = $this->guess_plugin_from_block_name( $block_name );
        $detected_plugins = $this->block_preferences->get_detected_plugins();
        
        $resolved_block = [
            'plugin_key' => $plugin_key,
            'block_name' => $block_name,
            'plugin_data' => $detected_plugins[ $plugin_key ] ?? [ 'name' => 'Unknown Plugin', 'namespace' => 'unknown' ],
            'fallback_used' => false
        ];
        
        return $this->build_block_specification( $resolved_block, $section_type, [] );
    }
}