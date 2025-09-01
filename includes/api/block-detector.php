<?php
/**
 * Block Detector Class - Detect available plugin blocks and determine user preferences
 * 
 * This class handles the detection of available WordPress blocks from various plugins,
 * analyzing installed plugins and their registered blocks to provide intelligent
 * block selection capabilities.
 *
 * @package AIPageComposer\API
 */

namespace AIPageComposer\API;

use AIPageComposer\Admin\Block_Preferences;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Block Detector class for plugin and block discovery
 */
class Block_Detector {

    /**
     * Cache key for detected blocks
     */
    const CACHE_KEY = 'ai_composer_detected_blocks';

    /**
     * Cache duration in seconds (1 hour)
     */
    const CACHE_DURATION = HOUR_IN_SECONDS;

    /**
     * Known plugin block mappings
     *
     * @var array
     */
    private $plugin_mappings = [
        'kadence_blocks' => [
            'plugin_file' => 'kadence-blocks/kadence-blocks.php',
            'block_prefix' => 'kadence/',
            'known_blocks' => [
                'kadence/rowlayout',
                'kadence/column',
                'kadence/advancedheading',
                'kadence/spacer',
                'kadence/image',
                'kadence/testimonials',
                'kadence/gallery',
                'kadence/button',
                'kadence/icon',
                'kadence/iconlist',
                'kadence/accordion',
                'kadence/tabs',
                'kadence/form'
            ]
        ],
        'genesis_blocks' => [
            'plugin_file' => 'genesis-blocks/genesis-blocks.php',
            'block_prefix' => 'genesis-blocks/',
            'known_blocks' => [
                'genesis-blocks/gb-container',
                'genesis-blocks/gb-columns',
                'genesis-blocks/gb-column',
                'genesis-blocks/gb-button',
                'genesis-blocks/gb-spacer',
                'genesis-blocks/gb-testimonial',
                'genesis-blocks/gb-accordion',
                'genesis-blocks/gb-newsletter',
                'genesis-blocks/gb-sharing',
                'genesis-blocks/gb-cta',
                'genesis-blocks/gb-pricing',
                'genesis-blocks/gb-post-grid'
            ]
        ],
        'stackable' => [
            'plugin_file' => 'stackable-ultimate-gutenberg-blocks/plugin.php',
            'block_prefix' => 'ugb/',
            'known_blocks' => [
                'ugb/container',
                'ugb/columns',
                'ugb/heading',
                'ugb/button',
                'ugb/image',
                'ugb/testimonial',
                'ugb/accordion',
                'ugb/card',
                'ugb/feature',
                'ugb/hero',
                'ugb/cta',
                'ugb/spacer'
            ]
        ],
        'ultimate_addons' => [
            'plugin_file' => 'ultimate-addons-for-gutenberg/ultimate-addons-for-gutenberg.php',
            'block_prefix' => 'uagb/',
            'known_blocks' => [
                'uagb/container',
                'uagb/advanced-heading',
                'uagb/image',
                'uagb/testimonial',
                'uagb/team',
                'uagb/call-to-action',
                'uagb/info-box',
                'uagb/social-share',
                'uagb/google-map',
                'uagb/icon-list',
                'uagb/buttons',
                'uagb/forms'
            ]
        ],
        'generateblocks' => [
            'plugin_file' => 'generateblocks/plugin.php',
            'block_prefix' => 'generateblocks/',
            'known_blocks' => [
                'generateblocks/container',
                'generateblocks/grid',
                'generateblocks/button',
                'generateblocks/headline',
                'generateblocks/image'
            ]
        ]
    ];

    /**
     * WordPress core blocks
     *
     * @var array
     */
    private $core_blocks = [
        'core/paragraph',
        'core/heading',
        'core/image',
        'core/gallery',
        'core/list',
        'core/quote',
        'core/table',
        'core/button',
        'core/buttons',
        'core/columns',
        'core/column',
        'core/group',
        'core/cover',
        'core/spacer',
        'core/separator',
        'core/media-text'
    ];

    /**
     * Block preferences instance
     *
     * @var Block_Preferences
     */
    private $block_preferences;

    /**
     * Constructor
     */
    public function __construct() {
        $this->block_preferences = new Block_Preferences();
    }

    /**
     * Detect all available blocks from active plugins
     *
     * @param bool $force_refresh Force refresh of cached data.
     * @return array Available blocks organized by plugin.
     */
    public function detect_available_blocks( $force_refresh = false ) {
        // Check cache first
        if ( ! $force_refresh ) {
            $cached_blocks = get_transient( self::CACHE_KEY );
            if ( $cached_blocks !== false ) {
                return $cached_blocks;
            }
        }

        $available_blocks = [
            'core' => $this->core_blocks
        ];

        // Detect plugin blocks
        foreach ( $this->plugin_mappings as $plugin_key => $plugin_data ) {
            if ( $this->is_plugin_active( $plugin_data['plugin_file'] ) ) {
                $plugin_blocks = $this->detect_plugin_blocks( $plugin_key, $plugin_data );
                if ( ! empty( $plugin_blocks ) ) {
                    $available_blocks[ $plugin_key ] = $plugin_blocks;
                }
            }
        }

        // Detect additional registered blocks
        $additional_blocks = $this->detect_registered_blocks();
        if ( ! empty( $additional_blocks ) ) {
            $available_blocks = array_merge_recursive( $available_blocks, $additional_blocks );
        }

        // Cache the results
        set_transient( self::CACHE_KEY, $available_blocks, self::CACHE_DURATION );

        return $available_blocks;
    }

    /**
     * Detect blocks for a specific plugin
     *
     * @param string $plugin_key Plugin identifier.
     * @param array  $plugin_data Plugin configuration data.
     * @return array Detected blocks for the plugin.
     */
    private function detect_plugin_blocks( $plugin_key, $plugin_data ) {
        $detected_blocks = [];

        // Start with known blocks
        $known_blocks = $plugin_data['known_blocks'] ?? [];
        
        // Verify which known blocks are actually registered
        foreach ( $known_blocks as $block_name ) {
            if ( $this->is_block_registered( $block_name ) ) {
                $detected_blocks[] = $block_name;
            }
        }

        // Search for additional blocks with the plugin's prefix
        $prefix = $plugin_data['block_prefix'] ?? '';
        if ( ! empty( $prefix ) ) {
            $additional_blocks = $this->find_blocks_by_prefix( $prefix );
            $detected_blocks = array_merge( $detected_blocks, $additional_blocks );
        }

        // Remove duplicates and sort
        $detected_blocks = array_unique( $detected_blocks );
        sort( $detected_blocks );

        return $detected_blocks;
    }

    /**
     * Detect additional registered blocks not in known mappings
     *
     * @return array Additional blocks organized by prefix.
     */
    private function detect_registered_blocks() {
        $registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();
        $additional_blocks = [];

        foreach ( $registered_blocks as $block_name => $block_type ) {
            // Skip core blocks and known plugin blocks
            if ( $this->is_core_block( $block_name ) || $this->is_known_plugin_block( $block_name ) ) {
                continue;
            }

            // Try to categorize by prefix
            $prefix = $this->extract_block_prefix( $block_name );
            if ( $prefix ) {
                $plugin_key = $this->guess_plugin_key( $prefix );
                if ( ! isset( $additional_blocks[ $plugin_key ] ) ) {
                    $additional_blocks[ $plugin_key ] = [];
                }
                $additional_blocks[ $plugin_key ][] = $block_name;
            }
        }

        return $additional_blocks;
    }

    /**
     * Check if a block is registered in WordPress
     *
     * @param string $block_name Block name to check.
     * @return bool True if block is registered.
     */
    private function is_block_registered( $block_name ) {
        return \WP_Block_Type_Registry::get_instance()->is_registered( $block_name );
    }

    /**
     * Check if a plugin is active
     *
     * @param string $plugin_file Plugin file path.
     * @return bool True if plugin is active.
     */
    private function is_plugin_active( $plugin_file ) {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active( $plugin_file );
    }

    /**
     * Find blocks by prefix in registered blocks
     *
     * @param string $prefix Block prefix to search for.
     * @return array Blocks matching the prefix.
     */
    private function find_blocks_by_prefix( $prefix ) {
        $registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();
        $matching_blocks = [];

        foreach ( array_keys( $registered_blocks ) as $block_name ) {
            if ( strpos( $block_name, $prefix ) === 0 ) {
                $matching_blocks[] = $block_name;
            }
        }

        return $matching_blocks;
    }

    /**
     * Check if a block is a core WordPress block
     *
     * @param string $block_name Block name.
     * @return bool True if it's a core block.
     */
    private function is_core_block( $block_name ) {
        return in_array( $block_name, $this->core_blocks, true ) || strpos( $block_name, 'core/' ) === 0;
    }

    /**
     * Check if a block is in our known plugin mappings
     *
     * @param string $block_name Block name.
     * @return bool True if it's a known plugin block.
     */
    private function is_known_plugin_block( $block_name ) {
        foreach ( $this->plugin_mappings as $plugin_data ) {
            $known_blocks = $plugin_data['known_blocks'] ?? [];
            if ( in_array( $block_name, $known_blocks, true ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract block prefix from block name
     *
     * @param string $block_name Block name.
     * @return string|null Block prefix or null.
     */
    private function extract_block_prefix( $block_name ) {
        $parts = explode( '/', $block_name );
        return count( $parts ) >= 2 ? $parts[0] . '/' : null;
    }

    /**
     * Guess plugin key from block prefix
     *
     * @param string $prefix Block prefix.
     * @return string Guessed plugin key.
     */
    private function guess_plugin_key( $prefix ) {
        // Remove trailing slash
        $prefix = rtrim( $prefix, '/' );

        // Map common prefixes to plugin keys
        $prefix_mappings = [
            'kadence' => 'kadence_blocks',
            'genesis-blocks' => 'genesis_blocks',
            'ugb' => 'stackable',
            'uagb' => 'ultimate_addons',
            'generateblocks' => 'generateblocks',
            'coblocks' => 'coblocks',
            'atomic-blocks' => 'atomic_blocks'
        ];

        return $prefix_mappings[ $prefix ] ?? $prefix;
    }

    /**
     * Get blocks for a specific plugin
     *
     * @param string $plugin_key Plugin identifier.
     * @return array Blocks for the specified plugin.
     */
    public function get_plugin_blocks( $plugin_key ) {
        $available_blocks = $this->detect_available_blocks();
        return $available_blocks[ $plugin_key ] ?? [];
    }

    /**
     * Get plugin information for detected plugins
     *
     * @return array Plugin information with block counts and capabilities.
     */
    public function get_plugin_information() {
        $available_blocks = $this->detect_available_blocks();
        $plugin_info = [];

        foreach ( $available_blocks as $plugin_key => $blocks ) {
            $plugin_data = $this->plugin_mappings[ $plugin_key ] ?? [];
            
            $plugin_info[ $plugin_key ] = [
                'name' => $this->get_plugin_display_name( $plugin_key ),
                'block_count' => count( $blocks ),
                'blocks' => $blocks,
                'is_active' => $plugin_key === 'core' ? true : $this->is_plugin_active( $plugin_data['plugin_file'] ?? '' ),
                'capabilities' => $this->analyze_plugin_capabilities( $plugin_key, $blocks ),
                'version' => $this->get_plugin_version( $plugin_key )
            ];
        }

        return $plugin_info;
    }

    /**
     * Get display name for plugin
     *
     * @param string $plugin_key Plugin identifier.
     * @return string Human-readable plugin name.
     */
    private function get_plugin_display_name( $plugin_key ) {
        $display_names = [
            'core' => __( 'WordPress Core', 'ai-page-composer' ),
            'kadence_blocks' => __( 'Kadence Blocks', 'ai-page-composer' ),
            'genesis_blocks' => __( 'Genesis Blocks', 'ai-page-composer' ),
            'stackable' => __( 'Stackable', 'ai-page-composer' ),
            'ultimate_addons' => __( 'Ultimate Addons for Gutenberg', 'ai-page-composer' ),
            'generateblocks' => __( 'GenerateBlocks', 'ai-page-composer' )
        ];

        return $display_names[ $plugin_key ] ?? ucwords( str_replace( '_', ' ', $plugin_key ) );
    }

    /**
     * Analyze plugin capabilities based on available blocks
     *
     * @param string $plugin_key Plugin identifier.
     * @param array  $blocks Available blocks.
     * @return array Plugin capabilities.
     */
    private function analyze_plugin_capabilities( $plugin_key, $blocks ) {
        $capabilities = [
            'layout' => false,
            'content' => false,
            'media' => false,
            'forms' => false,
            'advanced' => false
        ];

        foreach ( $blocks as $block_name ) {
            $block_lower = strtolower( $block_name );

            // Layout capabilities
            if ( strpos( $block_lower, 'row' ) !== false || 
                 strpos( $block_lower, 'column' ) !== false || 
                 strpos( $block_lower, 'container' ) !== false ||
                 strpos( $block_lower, 'grid' ) !== false ) {
                $capabilities['layout'] = true;
            }

            // Content capabilities
            if ( strpos( $block_lower, 'heading' ) !== false || 
                 strpos( $block_lower, 'paragraph' ) !== false || 
                 strpos( $block_lower, 'text' ) !== false ||
                 strpos( $block_lower, 'button' ) !== false ) {
                $capabilities['content'] = true;
            }

            // Media capabilities
            if ( strpos( $block_lower, 'image' ) !== false || 
                 strpos( $block_lower, 'gallery' ) !== false || 
                 strpos( $block_lower, 'video' ) !== false ) {
                $capabilities['media'] = true;
            }

            // Form capabilities
            if ( strpos( $block_lower, 'form' ) !== false || 
                 strpos( $block_lower, 'newsletter' ) !== false || 
                 strpos( $block_lower, 'contact' ) !== false ) {
                $capabilities['forms'] = true;
            }

            // Advanced capabilities
            if ( strpos( $block_lower, 'accordion' ) !== false || 
                 strpos( $block_lower, 'tabs' ) !== false || 
                 strpos( $block_lower, 'testimonial' ) !== false ||
                 strpos( $block_lower, 'pricing' ) !== false ) {
                $capabilities['advanced'] = true;
            }
        }

        return $capabilities;
    }

    /**
     * Get plugin version information
     *
     * @param string $plugin_key Plugin identifier.
     * @return string Plugin version or empty string.
     */
    private function get_plugin_version( $plugin_key ) {
        if ( $plugin_key === 'core' ) {
            return get_bloginfo( 'version' );
        }

        $plugin_data = $this->plugin_mappings[ $plugin_key ] ?? [];
        $plugin_file = $plugin_data['plugin_file'] ?? '';

        if ( ! empty( $plugin_file ) && function_exists( 'get_plugin_data' ) ) {
            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
            if ( file_exists( $plugin_path ) ) {
                $plugin_info = get_plugin_data( $plugin_path );
                return $plugin_info['Version'] ?? '';
            }
        }

        return '';
    }

    /**
     * Clear detection cache
     */
    public function clear_cache() {
        delete_transient( self::CACHE_KEY );
    }

    /**
     * Get block type information
     *
     * @param string $block_name Block name.
     * @return array|null Block type information.
     */
    public function get_block_type_info( $block_name ) {
        $block_registry = \WP_Block_Type_Registry::get_instance();
        $block_type = $block_registry->get_registered( $block_name );

        if ( ! $block_type ) {
            return null;
        }

        return [
            'name' => $block_type->name,
            'title' => $block_type->title ?? '',
            'description' => $block_type->description ?? '',
            'category' => $block_type->category ?? '',
            'icon' => $block_type->icon ?? '',
            'keywords' => $block_type->keywords ?? [],
            'supports' => $block_type->supports ?? [],
            'attributes' => $block_type->attributes ?? []
        ];
    }

    /**
     * Suggest blocks for content type
     *
     * @param string $content_type Content type (hero, content, image, etc.).
     * @param array  $user_preferences User block preferences.
     * @return array Suggested blocks ranked by suitability.
     */
    public function suggest_blocks_for_content( $content_type, $user_preferences = [] ) {
        $available_blocks = $this->detect_available_blocks();
        $suggestions = [];

        // Define content type mappings
        $type_keywords = [
            'hero' => ['hero', 'banner', 'cover', 'jumbotron', 'header'],
            'content' => ['paragraph', 'text', 'content', 'heading'],
            'image' => ['image', 'gallery', 'media', 'photo'],
            'testimonial' => ['testimonial', 'review', 'quote'],
            'list' => ['list', 'bullet', 'numbered'],
            'button' => ['button', 'cta', 'call-to-action'],
            'form' => ['form', 'contact', 'newsletter'],
            'layout' => ['row', 'column', 'container', 'grid']
        ];

        $keywords = $type_keywords[ $content_type ] ?? [];

        foreach ( $available_blocks as $plugin_key => $blocks ) {
            foreach ( $blocks as $block_name ) {
                $score = $this->calculate_block_suitability( $block_name, $keywords, $user_preferences );
                if ( $score > 0 ) {
                    $suggestions[] = [
                        'block_name' => $block_name,
                        'plugin' => $plugin_key,
                        'score' => $score,
                        'user_preferred' => isset( $user_preferences[ $content_type ] ) && 
                                         $user_preferences[ $content_type ] === $plugin_key
                    ];
                }
            }
        }

        // Sort by score and user preference
        usort( $suggestions, function( $a, $b ) {
            if ( $a['user_preferred'] !== $b['user_preferred'] ) {
                return $a['user_preferred'] ? -1 : 1;
            }
            return $b['score'] - $a['score'];
        });

        return $suggestions;
    }

    /**
     * Calculate block suitability score
     *
     * @param string $block_name Block name.
     * @param array  $keywords Content type keywords.
     * @param array  $user_preferences User preferences.
     * @return int Suitability score.
     */
    private function calculate_block_suitability( $block_name, $keywords, $user_preferences ) {
        $score = 0;
        $block_lower = strtolower( $block_name );

        // Check for keyword matches
        foreach ( $keywords as $keyword ) {
            if ( strpos( $block_lower, $keyword ) !== false ) {
                $score += 10;
            }
        }

        // Bonus for exact matches
        foreach ( $keywords as $keyword ) {
            if ( strpos( $block_lower, $keyword ) !== false && 
                 strpos( $block_lower, $keyword ) === strrpos( $block_lower, $keyword ) ) {
                $score += 5;
            }
        }

        return $score;
    }
}