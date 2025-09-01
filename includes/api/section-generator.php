<?php
/**
 * Section Generator Class - Content Generation with Block Awareness
 * 
 * This file contains the Section_Generator class that handles content generation
 * with three modes (Grounded/Hybrid/Generative), block-aware output formatting,
 * and integrated citation management. It coordinates between MVDB retrieval,
 * AI generation, and block resolution.
 *
 * @package AIPageComposer\API
 */

namespace AIPageComposer\API;

use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;
use Exception;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Section Generator class for content generation
 */
class Section_Generator {

    /**
     * MVDB manager instance
     *
     * @var MVDB_Manager
     */
    private $mvdb_manager;

    /**
     * Block resolver instance
     *
     * @var Block_Resolver
     */
    private $block_resolver;

    /**
     * Citation manager instance
     *
     * @var Citation_Manager
     */
    private $citation_manager;

    /**
     * AI service client instance
     *
     * @var AI_Service_Client
     */
    private $ai_client;

    /**
     * Constructor
     */
    public function __construct() {
        $this->mvdb_manager = new MVDB_Manager();
        $this->block_resolver = new Block_Resolver();
        $this->citation_manager = new Citation_Manager();
        $this->ai_client = new AI_Service_Client( new \AIPageComposer\Admin\Settings_Manager() );
    }

    /**
     * Generate section content
     *
     * @param array $params Generation parameters.
     * @return array Generated section data.
     * @throws Exception If generation fails.
     */
    public function generate( $params ) {
        $start_time = microtime( true );
        
        try {
            // Resolve block type first
            $block_specification = $this->block_resolver->resolve_block_type( $params['block_preferences'] );
            
            // Get context for grounded/hybrid modes
            $context_chunks = [];
            if ( in_array( $params['mode'], [ 'grounded', 'hybrid' ] ) ) {
                $context_chunks = $this->retrieve_context( $params );
            }
            
            // Generate content
            $content_result = $this->generate_content( $params, $context_chunks, $block_specification );
            
            // Process citations
            $citations = $this->citation_manager->extract_and_format_citations(
                $content_result['content'],
                $context_chunks
            );
            
            // Handle image requirements
            $media_data = null;
            if ( $this->should_include_image( $params['image_requirements'], $block_specification ) ) {
                $media_data = $this->process_image_for_section( $params, $block_specification );
            }
            
            // Build response
            $processing_time = ( microtime( true ) - $start_time ) * 1000;
            
            return [
                'sectionId' => $params['sectionId'],
                'content' => [
                    'html' => $content_result['html'],
                    'json' => $content_result['block_json']
                ],
                'blockType' => [
                    'name' => $block_specification['block_name'],
                    'plugin' => $block_specification['plugin'],
                    'namespace' => $block_specification['namespace'],
                    'fallback_used' => $block_specification['fallback_used']
                ],
                'citations' => $citations,
                'mediaId' => $media_data['id'] ?? null,
                'media' => $media_data,
                'generation_metadata' => [
                    'mode' => $params['mode'],
                    'alpha' => $params['alpha'],
                    'word_count' => str_word_count( strip_tags( $content_result['html'] ) ),
                    'token_count' => $content_result['token_count'],
                    'cost_usd' => $content_result['cost_usd'],
                    'processing_time_ms' => round( $processing_time ),
                    'cache_hit' => false
                ]
            ];
            
        } catch ( Exception $e ) {
            error_log( '[AI Composer] Section generation failed: ' . $e->getMessage() );
            throw new Exception( __( 'Section generation failed: ', 'ai-page-composer' ) . $e->getMessage() );
        }
    }

    /**
     * Retrieve context from MVDB
     *
     * @param array $params Generation parameters.
     * @return array Context chunks.
     */
    private function retrieve_context( $params ) {
        try {
            return $this->mvdb_manager->retrieve_context( [
                'sectionId' => $params['sectionId'],
                'query' => $params['content_brief'],
                'k' => 10,
                'min_score' => 0.5
            ] );
        } catch ( Exception $e ) {
            error_log( '[AI Composer] Context retrieval failed: ' . $e->getMessage() );
            return [];
        }
    }

    /**
     * Generate content using AI service
     *
     * @param array $params Generation parameters.
     * @param array $context_chunks Context from MVDB.
     * @param array $block_specification Block specification.
     * @return array Content generation result.
     * @throws Exception If content generation fails.
     */
    private function generate_content( $params, $context_chunks, $block_specification ) {
        // Build generation prompt
        $prompt = $this->build_generation_prompt( $params, $context_chunks, $block_specification );
        
        // Call AI service
        $ai_response = $this->ai_client->generate_content( [
            'prompt' => $prompt,
            'mode' => $params['mode'],
            'alpha' => $params['alpha'],
            'block_spec' => $block_specification
        ] );
        
        // Convert to block format
        $block_json = $this->convert_to_block_format( $ai_response['content'], $block_specification );
        $html = $this->render_block_html( $block_json );
        
        return [
            'content' => $ai_response['content'],
            'html' => $html,
            'block_json' => $block_json,
            'token_count' => $ai_response['token_count'] ?? 0,
            'cost_usd' => $ai_response['cost_usd'] ?? 0.0
        ];
    }

    /**
     * Build generation prompt
     *
     * @param array $params Generation parameters.
     * @param array $context_chunks Context chunks.
     * @param array $block_specification Block specification.
     * @return string Generated prompt.
     */
    private function build_generation_prompt( $params, $context_chunks, $block_specification ) {
        $prompt = "Generate content for a {$block_specification['section_type']} section using {$block_specification['block_name']} block.\n\n";
        $prompt .= "Content Brief: {$params['content_brief']}\n\n";
        
        if ( ! empty( $context_chunks ) ) {
            $prompt .= "Relevant Context:\n";
            foreach ( array_slice( $context_chunks, 0, 5 ) as $chunk ) {
                $prompt .= "- " . wp_trim_words( $chunk['text'], 50 ) . "\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "Block Requirements:\n";
        $prompt .= "- Block Type: {$block_specification['block_name']}\n";
        $prompt .= "- Plugin: {$block_specification['plugin']}\n";
        $prompt .= "- Section Type: {$block_specification['section_type']}\n";
        
        if ( ! empty( $block_specification['attributes'] ) ) {
            $prompt .= "- Required Attributes: " . implode( ', ', array_keys( $block_specification['attributes'] ) ) . "\n";
        }
        
        $prompt .= "\nGeneration Mode: {$params['mode']}\n";
        
        if ( $params['mode'] === 'hybrid' ) {
            $prompt .= "Alpha Weight: {$params['alpha']} (context relevance)\n";
        }
        
        $prompt .= "\nPlease generate appropriate content that fits the block structure and section requirements.";
        
        return $prompt;
    }

    /**
     * Convert content to block format
     *
     * @param string $content Generated content.
     * @param array  $block_specification Block specification.
     * @return array Block JSON structure.
     */
    private function convert_to_block_format( $content, $block_specification ) {
        $block_name = $block_specification['block_name'];
        $attributes = $block_specification['attributes'] ?? [];
        
        // Generate unique ID for the block
        $unique_id = $this->generate_unique_block_id( $block_specification );
        
        // Basic block structure
        $block_json = [
            'blockName' => $block_name,
            'attrs' => array_merge( $attributes, [
                'uniqueID' => $unique_id,
                'content' => $content
            ] ),
            'innerBlocks' => []
        ];
        
        // Handle specific block types
        switch ( $block_specification['plugin'] ) {
            case 'kadence_blocks':
                $block_json = $this->format_kadence_block( $block_json, $content, $block_specification );
                break;
                
            case 'genesis_blocks':
                $block_json = $this->format_genesis_block( $block_json, $content, $block_specification );
                break;
                
            case 'core':
                $block_json = $this->format_core_block( $block_json, $content, $block_specification );
                break;
                
            default:
                // Generic block formatting
                $block_json['attrs']['content'] = $content;
                break;
        }
        
        return $block_json;
    }

    /**
     * Format Kadence block
     *
     * @param array  $block_json Base block structure.
     * @param string $content Generated content.
     * @param array  $block_specification Block specification.
     * @return array Formatted block.
     */
    private function format_kadence_block( $block_json, $content, $block_specification ) {
        switch ( $block_specification['section_type'] ) {
            case 'hero':
                $block_json['attrs'] = array_merge( $block_json['attrs'], [
                    'backgroundImg' => [],
                    'backgroundOverlay' => [ 'color' => 'rgba(0,0,0,0.3)' ],
                    'padding' => [ '100', '20', '100', '20' ],
                    'textAlign' => 'center'
                ] );
                break;
                
            case 'content':
                $block_json['attrs'] = array_merge( $block_json['attrs'], [
                    'padding' => [ '40', '20', '40', '20' ],
                    'textAlign' => 'left'
                ] );
                break;
        }
        
        return $block_json;
    }

    /**
     * Format Genesis block
     *
     * @param array  $block_json Base block structure.
     * @param string $content Generated content.
     * @param array  $block_specification Block specification.
     * @return array Formatted block.
     */
    private function format_genesis_block( $block_json, $content, $block_specification ) {
        // Genesis-specific formatting
        $block_json['attrs']['className'] = 'gb-block-' . $block_specification['section_type'];
        
        return $block_json;
    }

    /**
     * Format core WordPress block
     *
     * @param array  $block_json Base block structure.
     * @param string $content Generated content.
     * @param array  $block_specification Block specification.
     * @return array Formatted block.
     */
    private function format_core_block( $block_json, $content, $block_specification ) {
        switch ( $block_specification['section_type'] ) {
            case 'hero':
                // Use core/cover block for hero sections
                $block_json['blockName'] = 'core/cover';
                $block_json['attrs'] = array_merge( $block_json['attrs'], [
                    'dimRatio' => 30,
                    'minHeight' => 400,
                    'contentPosition' => 'center center'
                ] );
                break;
                
            case 'content':
                // Use core/group for content sections
                $block_json['blockName'] = 'core/group';
                break;
        }
        
        return $block_json;
    }

    /**
     * Render block HTML
     *
     * @param array $block_json Block JSON structure.
     * @return string Rendered HTML.
     */
    private function render_block_html( $block_json ) {
        // Use WordPress block parser to render HTML
        $block = parse_blocks( serialize_blocks( [ $block_json ] ) )[0] ?? null;
        
        if ( $block ) {
            return render_block( $block );
        }
        
        // Fallback HTML generation
        return $this->generate_fallback_html( $block_json );
    }

    /**
     * Generate fallback HTML
     *
     * @param array $block_json Block JSON structure.
     * @return string Fallback HTML.
     */
    private function generate_fallback_html( $block_json ) {
        $content = $block_json['attrs']['content'] ?? '';
        $class_name = 'wp-block-' . str_replace( '/', '-', $block_json['blockName'] );
        
        return sprintf( '<div class="%s">%s</div>', esc_attr( $class_name ), wp_kses_post( $content ) );
    }

    /**
     * Generate unique block ID
     *
     * @param array $block_specification Block specification.
     * @return string Unique ID.
     */
    private function generate_unique_block_id( $block_specification ) {
        $prefix = $block_specification['section_type'] ?? 'section';
        return $prefix . '-' . wp_generate_uuid4();
    }

    /**
     * Check if image should be included
     *
     * @param array $image_requirements Image requirements.
     * @param array $block_specification Block specification.
     * @return bool True if image should be included.
     */
    private function should_include_image( $image_requirements, $block_specification ) {
        $policy = $image_requirements['policy'] ?? 'optional';
        
        if ( $policy === 'none' ) {
            return false;
        }
        
        if ( $policy === 'required' ) {
            return true;
        }
        
        // Optional - check if block type typically needs images
        $image_sections = [ 'hero', 'feature', 'testimonial' ];
        return in_array( $block_specification['section_type'], $image_sections );
    }

    /**
     * Process image for section
     *
     * @param array $params Generation parameters.
     * @param array $block_specification Block specification.
     * @return array|null Media data or null.
     */
    private function process_image_for_section( $params, $block_specification ) {
        try {
            $image_service = new Image_Service();
            
            $image_params = [
                'prompt' => $this->generate_image_prompt( $params, $block_specification ),
                'style' => $params['image_requirements']['style'] ?? 'photographic',
                'source' => 'generate',
                'alt_text' => $this->generate_alt_text( $params, $block_specification ),
                'license_filter' => $params['image_requirements']['license_compliance'] ?? []
            ];
            
            return $image_service->process_image_request( $image_params );
            
        } catch ( Exception $e ) {
            error_log( '[AI Composer] Image processing failed: ' . $e->getMessage() );
            return null;
        }
    }

    /**
     * Generate image prompt
     *
     * @param array $params Generation parameters.
     * @param array $block_specification Block specification.
     * @return string Image prompt.
     */
    private function generate_image_prompt( $params, $block_specification ) {
        $base_prompt = $params['content_brief'];
        $section_type = $block_specification['section_type'];
        
        $style_hints = [
            'hero' => 'professional, high-quality background image',
            'feature' => 'clean, modern illustration',
            'testimonial' => 'professional headshot or team photo',
            'content' => 'relevant supporting image'
        ];
        
        $style_hint = $style_hints[ $section_type ] ?? 'professional image';
        
        return $base_prompt . ', ' . $style_hint;
    }

    /**
     * Generate alt text
     *
     * @param array $params Generation parameters.
     * @param array $block_specification Block specification.
     * @return string Alt text.
     */
    private function generate_alt_text( $params, $block_specification ) {
        $section_type = $block_specification['section_type'];
        $brief = wp_trim_words( $params['content_brief'], 10 );
        
        return ucfirst( $section_type ) . ' section image: ' . $brief;
    }
}