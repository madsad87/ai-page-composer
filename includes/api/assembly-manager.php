<?php
/**
 * Assembly Manager Class - Core logic for assembling generated sections into WordPress blocks
 * 
 * This class handles the intelligent assembly of generated content sections into WordPress
 * blocks with plugin detection, user preference respect, and fallback handling.
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
 * Assembly Manager class for intelligent block assembly
 */
class Assembly_Manager {

    /**
     * Block detector instance
     *
     * @var Block_Detector
     */
    private $block_detector;

    /**
     * Block fallback handler instance
     *
     * @var Block_Fallback
     */
    private $block_fallback;

    /**
     * Block preferences instance
     *
     * @var Block_Preferences
     */
    private $block_preferences;

    /**
     * Assembly metadata
     *
     * @var array
     */
    private $assembly_metadata = [
        'blocks_used' => [],
        'fallbacks_applied' => 0,
        'validation_warnings' => [],
        'processing_time' => 0,
        'accessibility_score' => 0
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->block_detector = new Block_Detector();
        $this->block_fallback = new Block_Fallback();
        $this->block_preferences = new Block_Preferences();
    }

    /**
     * Assemble sections into WordPress blocks
     *
     * @param array $sections Generated sections to assemble.
     * @param array $options Assembly options and configuration.
     * @return array Assembly result with blocks and metadata.
     */
    public function assemble_sections( $sections, $options = [] ) {
        $start_time = microtime( true );
        
        try {
            // Validate input sections
            if ( empty( $sections ) || ! is_array( $sections ) ) {
                throw new \InvalidArgumentException( __( 'Valid sections array is required', 'ai-page-composer' ) );
            }

            // Get assembly options with defaults
            $assembly_options = wp_parse_args( $options['options'] ?? [], [
                'respect_user_preferences' => true,
                'enable_fallbacks' => true,
                'validate_html' => true,
                'optimize_images' => true,
                'seo_optimization' => true
            ]);

            $blueprint_id = $options['blueprint_id'] ?? null;

            // Detect available blocks
            $available_blocks = $this->block_detector->detect_available_blocks();
            
            // Get user preferences for block selection
            $user_preferences = $this->get_user_preferences( $blueprint_id );

            // Initialize assembly result
            $assembled_blocks = [];
            $plugin_indicators = [];

            // Process each section
            foreach ( $sections as $index => $section ) {
                $section_result = $this->assemble_section( 
                    $section, 
                    $available_blocks, 
                    $user_preferences, 
                    $assembly_options,
                    $index 
                );

                $assembled_blocks = array_merge( $assembled_blocks, $section_result['blocks'] );
                $plugin_indicators = array_merge( $plugin_indicators, $section_result['indicators'] );
            }

            // Generate final HTML and JSON
            $html_content = $this->blocks_to_html( $assembled_blocks );
            $json_content = $this->blocks_to_json( $assembled_blocks );

            // Calculate processing time
            $this->assembly_metadata['processing_time'] = microtime( true ) - $start_time;

            // Calculate accessibility score
            $this->assembly_metadata['accessibility_score'] = $this->calculate_accessibility_score( $assembled_blocks );

            return [
                'assembled_content' => [
                    'blocks' => $assembled_blocks,
                    'html' => $html_content,
                    'json' => $json_content
                ],
                'assembly_metadata' => $this->assembly_metadata,
                'plugin_indicators' => $plugin_indicators
            ];

        } catch ( \Exception $e ) {
            error_log( '[AI Composer] Assembly failed: ' . $e->getMessage() );
            throw new \Exception( 
                sprintf( __( 'Assembly failed: %s', 'ai-page-composer' ), $e->getMessage() )
            );
        }
    }

    /**
     * Assemble a single section into blocks
     *
     * @param array $section Section data to assemble.
     * @param array $available_blocks Available plugin blocks.
     * @param array $user_preferences User block preferences.
     * @param array $options Assembly options.
     * @param int   $section_index Section index for unique IDs.
     * @return array Section assembly result.
     */
    private function assemble_section( $section, $available_blocks, $user_preferences, $options, $section_index ) {
        $section_blocks = [];
        $section_indicators = [];

        // Determine section type and content
        $section_type = $this->determine_section_type( $section );
        $section_content = $section['content'] ?? '';
        $section_id = "section-{$section_index}";

        // Select appropriate block based on section type and preferences
        $selected_block = $this->select_block_for_section( 
            $section_type, 
            $available_blocks, 
            $user_preferences 
        );

        // Check if fallback was used
        $fallback_used = $selected_block['is_fallback'] ?? false;
        if ( $fallback_used ) {
            $this->assembly_metadata['fallbacks_applied']++;
        }

        // Track block usage
        $plugin_key = $selected_block['plugin'] ?? 'core';
        if ( ! isset( $this->assembly_metadata['blocks_used'][ $plugin_key ] ) ) {
            $this->assembly_metadata['blocks_used'][ $plugin_key ] = 0;
        }
        $this->assembly_metadata['blocks_used'][ $plugin_key ]++;

        // Generate block structure
        $block_data = $this->generate_block_structure( 
            $selected_block, 
            $section_content, 
            $section_id, 
            $options 
        );

        $section_blocks[] = $block_data;

        // Create plugin indicator
        $section_indicators[] = [
            'section_id' => $section_id,
            'plugin_used' => $plugin_key,
            'block_name' => $selected_block['block_name'],
            'fallback_used' => $fallback_used,
            'section_type' => $section_type
        ];

        return [
            'blocks' => $section_blocks,
            'indicators' => $section_indicators
        ];
    }

    /**
     * Determine section type from content analysis
     *
     * @param array $section Section data.
     * @return string Section type (hero, content, gallery, etc.).
     */
    private function determine_section_type( $section ) {
        $content = strtolower( $section['content'] ?? '' );
        $title = strtolower( $section['title'] ?? '' );
        $type = strtolower( $section['type'] ?? '' );

        // Check explicit type first
        if ( ! empty( $type ) ) {
            return $type;
        }

        // Analyze content for patterns
        if ( strpos( $content, '<img' ) !== false || strpos( $title, 'image' ) !== false ) {
            return 'image';
        }

        if ( strpos( $title, 'hero' ) !== false || strpos( $content, 'banner' ) !== false ) {
            return 'hero';
        }

        if ( strpos( $content, 'testimonial' ) !== false || strpos( $title, 'review' ) !== false ) {
            return 'testimonial';
        }

        if ( strpos( $content, '<ul>' ) !== false || strpos( $content, '<ol>' ) !== false ) {
            return 'list';
        }

        // Default to content block
        return 'content';
    }

    /**
     * Select appropriate block for section type
     *
     * @param string $section_type Section type.
     * @param array  $available_blocks Available plugin blocks.
     * @param array  $user_preferences User preferences.
     * @return array Selected block information.
     */
    private function select_block_for_section( $section_type, $available_blocks, $user_preferences ) {
        // Get preferred plugin for this section type
        $preferred_plugin = $user_preferences[ $section_type ] ?? 'core';

        // Check if preferred plugin is available
        if ( isset( $available_blocks[ $preferred_plugin ] ) ) {
            $plugin_blocks = $available_blocks[ $preferred_plugin ];
            
            // Find appropriate block for section type
            $block_name = $this->find_block_for_type( $section_type, $plugin_blocks );
            
            if ( $block_name ) {
                return [
                    'plugin' => $preferred_plugin,
                    'block_name' => $block_name,
                    'is_fallback' => false
                ];
            }
        }

        // Use fallback selection
        return $this->block_fallback->get_fallback_block( $section_type, $available_blocks );
    }

    /**
     * Find specific block name for section type within plugin blocks
     *
     * @param string $section_type Section type.
     * @param array  $plugin_blocks Available blocks for plugin.
     * @return string|null Block name or null if not found.
     */
    private function find_block_for_type( $section_type, $plugin_blocks ) {
        $type_mappings = [
            'hero' => ['rowlayout', 'hero', 'banner', 'header'],
            'content' => ['advancedheading', 'paragraph', 'text', 'content'],
            'image' => ['image', 'gallery', 'media'],
            'testimonial' => ['testimonial', 'review', 'quote'],
            'list' => ['list', 'bullet', 'numbered']
        ];

        $possible_names = $type_mappings[ $section_type ] ?? ['paragraph'];

        foreach ( $possible_names as $name ) {
            foreach ( $plugin_blocks as $block ) {
                if ( strpos( strtolower( $block ), $name ) !== false ) {
                    return $block;
                }
            }
        }

        // Return first available block as fallback
        return $plugin_blocks[0] ?? null;
    }

    /**
     * Generate block structure for WordPress
     *
     * @param array  $selected_block Selected block information.
     * @param string $content Section content.
     * @param string $section_id Unique section ID.
     * @param array  $options Assembly options.
     * @return array WordPress block structure.
     */
    private function generate_block_structure( $selected_block, $content, $section_id, $options ) {
        $block_name = $selected_block['block_name'];
        $plugin = $selected_block['plugin'];

        // Generate unique ID for the block
        $unique_id = $section_id . '-' . uniqid();

        // Base block structure
        $block_attrs = [
            'uniqueID' => $unique_id
        ];

        // Add plugin-specific attributes
        if ( $plugin === 'kadence_blocks' ) {
            $block_attrs = $this->add_kadence_attributes( $block_attrs, $block_name, $content );
        } elseif ( $plugin === 'genesis_blocks' ) {
            $block_attrs = $this->add_genesis_attributes( $block_attrs, $block_name, $content );
        }

        // Process content based on options
        if ( $options['validate_html'] ?? true ) {
            $content = $this->validate_and_clean_html( $content );
        }

        if ( $options['optimize_images'] ?? true ) {
            $content = $this->optimize_image_references( $content );
        }

        // Generate innerHTML
        $inner_html = $this->generate_inner_html( $block_name, $content, $block_attrs );

        return [
            'blockName' => $block_name,
            'attrs' => $block_attrs,
            'innerBlocks' => [],
            'innerHTML' => $inner_html
        ];
    }

    /**
     * Add Kadence-specific block attributes
     *
     * @param array  $attrs Current attributes.
     * @param string $block_name Block name.
     * @param string $content Block content.
     * @return array Updated attributes.
     */
    private function add_kadence_attributes( $attrs, $block_name, $content ) {
        if ( strpos( $block_name, 'rowlayout' ) !== false ) {
            $attrs['colLayout'] = 'equal';
            $attrs['tabletLayout'] = 'equal';
            $attrs['mobileLayout'] = 'row';
            $attrs['columnGutter'] = 'default';
        }

        if ( strpos( $block_name, 'advancedheading' ) !== false ) {
            $attrs['level'] = 2;
            $attrs['sizeType'] = 'px';
            $attrs['size'] = [24, 20, 18];
        }

        return $attrs;
    }

    /**
     * Add Genesis-specific block attributes
     *
     * @param array  $attrs Current attributes.
     * @param string $block_name Block name.
     * @param string $content Block content.
     * @return array Updated attributes.
     */
    private function add_genesis_attributes( $attrs, $block_name, $content ) {
        if ( strpos( $block_name, 'container' ) !== false ) {
            $attrs['containerMaxWidth'] = 1200;
            $attrs['containerPaddingTop'] = 0;
            $attrs['containerPaddingBottom'] = 0;
        }

        return $attrs;
    }

    /**
     * Generate inner HTML for block
     *
     * @param string $block_name Block name.
     * @param string $content Block content.
     * @param array  $attrs Block attributes.
     * @return string Generated HTML.
     */
    private function generate_inner_html( $block_name, $content, $attrs ) {
        $css_class = 'wp-block-' . str_replace( '/', '-', $block_name );
        $unique_id = $attrs['uniqueID'] ?? '';

        return sprintf(
            '<div class="%s" id="%s">%s</div>',
            esc_attr( $css_class ),
            esc_attr( $unique_id ),
            $content
        );
    }

    /**
     * Convert blocks array to HTML string
     *
     * @param array $blocks WordPress blocks array.
     * @return string HTML content.
     */
    private function blocks_to_html( $blocks ) {
        $html = '';
        
        foreach ( $blocks as $block ) {
            $html .= sprintf(
                "<!-- wp:%s %s -->\n%s\n<!-- /wp:%s -->\n\n",
                $block['blockName'],
                wp_json_encode( $block['attrs'] ),
                $block['innerHTML'],
                $block['blockName']
            );
        }

        return trim( $html );
    }

    /**
     * Convert blocks array to JSON string
     *
     * @param array $blocks WordPress blocks array.
     * @return string JSON content.
     */
    private function blocks_to_json( $blocks ) {
        return wp_json_encode([
            'version' => 2,
            'blocks' => $blocks
        ], JSON_PRETTY_PRINT );
    }

    /**
     * Get user preferences for block selection
     *
     * @param int|null $blueprint_id Blueprint ID for preferences.
     * @return array User preferences array.
     */
    private function get_user_preferences( $blueprint_id = null ) {
        if ( $blueprint_id ) {
            // Get blueprint-specific preferences
            $blueprint_prefs = get_post_meta( $blueprint_id, '_block_preferences', true );
            if ( ! empty( $blueprint_prefs ) ) {
                return $blueprint_prefs;
            }
        }

        // Get global preferences
        return $this->block_preferences->get_user_preferences();
    }

    /**
     * Validate and clean HTML content
     *
     * @param string $content HTML content to validate.
     * @return string Cleaned HTML content.
     */
    private function validate_and_clean_html( $content ) {
        // Use WordPress sanitization
        $content = wp_kses_post( $content );
        
        // Additional validation
        if ( class_exists( 'DOMDocument' ) ) {
            libxml_use_internal_errors( true );
            $dom = new \DOMDocument();
            $dom->loadHTML( '<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
            
            $errors = libxml_get_errors();
            if ( ! empty( $errors ) ) {
                foreach ( $errors as $error ) {
                    $this->assembly_metadata['validation_warnings'][] = $error->message;
                }
            }
            libxml_clear_errors();
        }

        return $content;
    }

    /**
     * Optimize image references in content
     *
     * @param string $content Content with potential image references.
     * @return string Content with optimized images.
     */
    private function optimize_image_references( $content ) {
        // Find image tags and optimize
        $content = preg_replace_callback(
            '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i',
            function( $matches ) {
                $img_tag = $matches[0];
                $src = $matches[1];
                
                // Add loading="lazy" if not present
                if ( strpos( $img_tag, 'loading=' ) === false ) {
                    $img_tag = str_replace( '<img', '<img loading="lazy"', $img_tag );
                }
                
                // Add alt text if missing
                if ( strpos( $img_tag, 'alt=' ) === false ) {
                    $img_tag = str_replace( '<img', '<img alt=""', $img_tag );
                }
                
                return $img_tag;
            },
            $content
        );

        return $content;
    }

    /**
     * Calculate accessibility score for assembled content
     *
     * @param array $blocks Assembled blocks.
     * @return int Accessibility score (0-100).
     */
    private function calculate_accessibility_score( $blocks ) {
        $score = 100;
        $deductions = 0;

        foreach ( $blocks as $block ) {
            $content = $block['innerHTML'] ?? '';
            
            // Check for missing alt text on images
            if ( preg_match_all( '/<img[^>]*>/i', $content, $matches ) ) {
                foreach ( $matches[0] as $img ) {
                    if ( strpos( $img, 'alt=' ) === false || strpos( $img, 'alt=""' ) !== false ) {
                        $deductions += 5;
                    }
                }
            }
            
            // Check for heading structure
            if ( preg_match_all( '/<h([1-6])[^>]*>/i', $content, $matches ) ) {
                // Basic heading structure validation
                $heading_levels = array_map( 'intval', $matches[1] );
                if ( ! empty( $heading_levels ) && min( $heading_levels ) > 1 ) {
                    $deductions += 3; // Should start with h1 or h2
                }
            }
        }

        return max( 0, $score - $deductions );
    }

    /**
     * Get plugin blocks for specific plugin
     *
     * @param string $plugin_key Plugin identifier.
     * @return array Available blocks for plugin.
     */
    public function get_plugin_blocks( $plugin_key ) {
        return $this->block_detector->get_plugin_blocks( $plugin_key );
    }
}