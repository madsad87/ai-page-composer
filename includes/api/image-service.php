<?php
/**
 * Image Service Class - Image Generation and Media Library Integration
 * 
 * This file contains the Image_Service class that handles image generation,
 * library search, and WordPress Media Library integration with alt text
 * and license metadata management.
 *
 * @package AIPageComposer\API
 */

namespace AIPageComposer\API;

use AIPageComposer\Admin\Settings_Manager;
use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;
use Exception;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Image Service class for image generation and management
 */
class Image_Service {

    /**
     * Settings manager instance
     *
     * @var Settings_Manager
     */
    private $settings_manager;

    /**
     * Supported image styles
     *
     * @var array
     */
    private $supported_styles = [
        'photographic',
        'illustration',
        'abstract',
        'minimalist',
        'artistic',
        'technical'
    ];

    /**
     * Supported license types
     *
     * @var array
     */
    private $supported_licenses = [
        'CC-BY',
        'CC-BY-SA',
        'CC-BY-NC',
        'CC0',
        'public-domain',
        'royalty-free',
        'generated-content'
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings_manager = new Settings_Manager();
    }

    /**
     * Process image request
     *
     * @param array $params Image request parameters.
     * @return array Image result with media data.
     * @throws Exception If image processing fails.
     */
    public function process_image_request( $params ) {
        try {
            $start_time = microtime( true );
            
            // Validate parameters
            $validated_params = $this->validate_image_params( $params );
            
            // Process based on source type
            $image_data = null;
            switch ( $validated_params['source'] ) {
                case 'generate':
                    $image_data = $this->generate_image( $validated_params );
                    break;
                    
                case 'search':
                    $image_data = $this->search_image( $validated_params );
                    break;
                    
                case 'upload':
                    $image_data = $this->handle_upload( $validated_params );
                    break;
                    
                default:
                    throw new Exception( __( 'Invalid image source specified', 'ai-page-composer' ) );
            }
            
            if ( ! $image_data ) {
                throw new Exception( __( 'Failed to process image request', 'ai-page-composer' ) );
            }
            
            // Upload to Media Library
            $media_id = $this->upload_to_media_library( $image_data, $validated_params );
            
            // Calculate processing time
            $processing_time = ( microtime( true ) - $start_time ) * 1000;
            
            // Build result
            return [
                'mediaId' => $media_id,
                'url' => wp_get_attachment_url( $media_id ),
                'alt' => $validated_params['alt_text'],
                'license' => $image_data['license'] ?? 'unknown',
                'attribution' => $image_data['attribution'] ?? '',
                'dimensions' => [
                    'width' => $image_data['width'] ?? 0,
                    'height' => $image_data['height'] ?? 0
                ],
                'file_size' => $image_data['file_size'] ?? 0,
                'metadata' => [
                    'source' => $validated_params['source'],
                    'prompt' => $validated_params['prompt'],
                    'style' => $validated_params['style'],
                    'cost_usd' => $image_data['cost_usd'] ?? 0.0,
                    'generation_time_ms' => round( $processing_time )
                ]
            ];
            
        } catch ( Exception $e ) {
            error_log( '[AI Composer] Image processing failed: ' . $e->getMessage() );
            throw new Exception( __( 'Image processing failed: ', 'ai-page-composer' ) . $e->getMessage() );
        }
    }

    /**
     * Validate image parameters
     *
     * @param array $params Input parameters.
     * @return array Validated parameters.
     * @throws Exception If validation fails.
     */
    private function validate_image_params( $params ) {
        $validated = [];
        
        // Validate prompt
        if ( empty( $params['prompt'] ) || strlen( $params['prompt'] ) < 3 ) {
            throw new Exception( __( 'Image prompt must be at least 3 characters long', 'ai-page-composer' ) );
        }
        $validated['prompt'] = sanitize_textarea_field( $params['prompt'] );
        
        // Validate style
        $style = sanitize_key( $params['style'] ?? 'photographic' );
        if ( ! in_array( $style, $this->supported_styles ) ) {
            $style = 'photographic';
        }
        $validated['style'] = $style;
        
        // Validate source
        $source = sanitize_key( $params['source'] ?? 'generate' );
        if ( ! in_array( $source, [ 'generate', 'search', 'upload' ] ) ) {
            $source = 'generate';
        }
        $validated['source'] = $source;
        
        // Validate alt text
        $validated['alt_text'] = ! empty( $params['alt_text'] ) 
            ? sanitize_text_field( $params['alt_text'] )
            : $this->generate_default_alt_text( $validated['prompt'] );
        
        // Validate license filter
        $license_filter = (array) ( $params['license_filter'] ?? [] );
        $validated['license_filter'] = array_intersect( 
            array_map( 'sanitize_key', $license_filter ), 
            $this->supported_licenses 
        );
        
        // Validate dimensions
        $validated['dimensions'] = $this->validate_dimensions( $params['dimensions'] ?? [] );
        
        return $validated;
    }

    /**
     * Validate dimensions
     *
     * @param array $dimensions Dimensions array.
     * @return array Validated dimensions.
     */
    private function validate_dimensions( $dimensions ) {
        $validated = [
            'width' => 0,
            'height' => 0,
            'aspect_ratio' => ''
        ];
        
        if ( is_array( $dimensions ) ) {
            $validated['width'] = absint( $dimensions['width'] ?? 0 );
            $validated['height'] = absint( $dimensions['height'] ?? 0 );
            $validated['aspect_ratio'] = sanitize_text_field( $dimensions['aspect_ratio'] ?? '' );
            
            // Apply reasonable limits
            $validated['width'] = min( $validated['width'], 2048 );
            $validated['height'] = min( $validated['height'], 2048 );
        }
        
        return $validated;
    }

    /**
     * Generate default alt text
     *
     * @param string $prompt Image prompt.
     * @return string Default alt text.
     */
    private function generate_default_alt_text( $prompt ) {
        $trimmed = wp_trim_words( $prompt, 8 );
        return ucfirst( $trimmed );
    }

    /**
     * Generate image using AI service
     *
     * @param array $params Validated parameters.
     * @return array Image data.
     * @throws Exception If generation fails.
     */
    private function generate_image( $params ) {
        $settings = $this->settings_manager->get_all_settings();
        $api_settings = $settings['api_settings'] ?? [];
        
        $api_key = $api_settings['image_api_key'] ?? '';
        $api_provider = $api_settings['image_api_provider'] ?? 'dalle';
        
        if ( empty( $api_key ) ) {
            throw new Exception( __( 'Image API key not configured', 'ai-page-composer' ) );
        }
        
        switch ( $api_provider ) {
            case 'dalle':
                return $this->generate_with_dalle( $params, $api_key );
                
            case 'midjourney':
                return $this->generate_with_midjourney( $params, $api_key );
                
            case 'stable_diffusion':
                return $this->generate_with_stable_diffusion( $params, $api_key );
                
            default:
                throw new Exception( __( 'Unsupported image API provider', 'ai-page-composer' ) );
        }
    }

    /**
     * Generate image with DALL-E
     *
     * @param array  $params Parameters.
     * @param string $api_key API key.
     * @return array Image data.
     * @throws Exception If generation fails.
     */
    private function generate_with_dalle( $params, $api_key ) {
        $prompt = $this->enhance_prompt_for_style( $params['prompt'], $params['style'] );
        
        // Determine size based on dimensions
        $size = $this->determine_dalle_size( $params['dimensions'] );
        
        $request_body = [
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'quality' => 'standard',
            'response_format' => 'url'
        ];
        
        $response = wp_remote_post( 'https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode( $request_body ),
            'timeout' => 60
        ] );
        
        if ( is_wp_error( $response ) ) {
            throw new Exception( 'DALL-E API request failed: ' . $response->get_error_message() );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            $body = wp_remote_retrieve_body( $response );
            $error_data = json_decode( $body, true );
            $error_message = $error_data['error']['message'] ?? 'Unknown API error';
            throw new Exception( 'DALL-E API error (' . $response_code . '): ' . $error_message );
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( empty( $data['data'][0]['url'] ) ) {
            throw new Exception( __( 'No image URL returned from DALL-E', 'ai-page-composer' ) );
        }
        
        $image_url = $data['data'][0]['url'];
        
        // Download image data
        $image_content = $this->download_image( $image_url );
        
        return [
            'content' => $image_content,
            'url' => $image_url,
            'license' => 'generated-content',
            'attribution' => 'AI Generated via DALL-E',
            'cost_usd' => $this->calculate_dalle_cost( $size ),
            'width' => $this->get_size_dimensions( $size )['width'],
            'height' => $this->get_size_dimensions( $size )['height']
        ];
    }

    /**
     * Generate image with Midjourney (placeholder implementation)
     *
     * @param array  $params Parameters.
     * @param string $api_key API key.
     * @return array Image data.
     * @throws Exception If generation fails.
     */
    private function generate_with_midjourney( $params, $api_key ) {
        // This would require integration with Midjourney API
        // For now, throw an exception as it's not implemented
        throw new Exception( __( 'Midjourney integration not yet implemented', 'ai-page-composer' ) );
    }

    /**
     * Generate image with Stable Diffusion (placeholder implementation)
     *
     * @param array  $params Parameters.
     * @param string $api_key API key.
     * @return array Image data.
     * @throws Exception If generation fails.
     */
    private function generate_with_stable_diffusion( $params, $api_key ) {
        // This would require integration with Stable Diffusion API
        // For now, throw an exception as it's not implemented
        throw new Exception( __( 'Stable Diffusion integration not yet implemented', 'ai-page-composer' ) );
    }

    /**
     * Search for existing images
     *
     * @param array $params Parameters.
     * @return array Image data.
     * @throws Exception If search fails.
     */
    private function search_image( $params ) {
        $settings = $this->settings_manager->get_all_settings();
        $api_settings = $settings['api_settings'] ?? [];
        
        $search_provider = $api_settings['image_search_provider'] ?? 'unsplash';
        $api_key = $api_settings['image_search_api_key'] ?? '';
        
        switch ( $search_provider ) {
            case 'unsplash':
                return $this->search_unsplash( $params, $api_key );
                
            case 'pexels':
                return $this->search_pexels( $params, $api_key );
                
            case 'pixabay':
                return $this->search_pixabay( $params, $api_key );
                
            default:
                throw new Exception( __( 'Unsupported image search provider', 'ai-page-composer' ) );
        }
    }

    /**
     * Search Unsplash for images
     *
     * @param array  $params Parameters.
     * @param string $api_key API key.
     * @return array Image data.
     * @throws Exception If search fails.
     */
    private function search_unsplash( $params, $api_key ) {
        if ( empty( $api_key ) ) {
            throw new Exception( __( 'Unsplash API key not configured', 'ai-page-composer' ) );
        }
        
        $query = urlencode( $params['prompt'] );
        $per_page = 10;
        
        $response = wp_remote_get( 
            "https://api.unsplash.com/search/photos?query={$query}&per_page={$per_page}",
            [
                'headers' => [
                    'Authorization' => 'Client-ID ' . $api_key
                ],
                'timeout' => 30
            ]
        );
        
        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Unsplash API request failed: ' . $response->get_error_message() );
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( empty( $data['results'] ) ) {
            throw new Exception( __( 'No images found on Unsplash', 'ai-page-composer' ) );
        }
        
        // Filter by license if specified
        $filtered_results = $this->filter_by_license( $data['results'], $params['license_filter'] );
        
        if ( empty( $filtered_results ) ) {
            throw new Exception( __( 'No images found matching license requirements', 'ai-page-composer' ) );
        }
        
        // Select best match
        $selected_image = $filtered_results[0];
        
        // Download image
        $image_url = $selected_image['urls']['regular'] ?? $selected_image['urls']['small'];
        $image_content = $this->download_image( $image_url );
        
        return [
            'content' => $image_content,
            'url' => $image_url,
            'license' => 'CC0',
            'attribution' => sprintf( 
                'Photo by %s on Unsplash', 
                $selected_image['user']['name'] ?? 'Unknown'
            ),
            'cost_usd' => 0.0,
            'width' => $selected_image['width'] ?? 0,
            'height' => $selected_image['height'] ?? 0
        ];
    }

    /**
     * Search Pexels for images (placeholder)
     *
     * @param array  $params Parameters.
     * @param string $api_key API key.
     * @return array Image data.
     * @throws Exception If search fails.
     */
    private function search_pexels( $params, $api_key ) {
        throw new Exception( __( 'Pexels integration not yet implemented', 'ai-page-composer' ) );
    }

    /**
     * Search Pixabay for images (placeholder)
     *
     * @param array  $params Parameters.
     * @param string $api_key API key.
     * @return array Image data.
     * @throws Exception If search fails.
     */
    private function search_pixabay( $params, $api_key ) {
        throw new Exception( __( 'Pixabay integration not yet implemented', 'ai-page-composer' ) );
    }

    /**
     * Handle file upload
     *
     * @param array $params Parameters.
     * @return array Image data.
     * @throws Exception If upload handling fails.
     */
    private function handle_upload( $params ) {
        // This would handle direct file uploads
        // For now, throw an exception as it's not implemented
        throw new Exception( __( 'Direct upload handling not yet implemented', 'ai-page-composer' ) );
    }

    /**
     * Upload image to WordPress Media Library
     *
     * @param array $image_data Image data.
     * @param array $params Parameters.
     * @return int Media attachment ID.
     * @throws Exception If upload fails.
     */
    private function upload_to_media_library( $image_data, $params ) {
        if ( ! function_exists( 'wp_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        // Generate filename
        $filename = $this->generate_filename( $params['prompt'], $params['style'] );
        
        // Create temporary file
        $tmp_file = wp_tempnam( $filename );
        file_put_contents( $tmp_file, $image_data['content'] );
        
        // Prepare file array for sideload
        $file_array = [
            'name' => $filename,
            'tmp_name' => $tmp_file
        ];
        
        // Upload to media library
        $attachment_id = media_handle_sideload( $file_array, 0 );
        
        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp_file );
            throw new Exception( 'Media upload failed: ' . $attachment_id->get_error_message() );
        }
        
        // Update attachment metadata
        $this->update_attachment_metadata( $attachment_id, $image_data, $params );
        
        return $attachment_id;
    }

    /**
     * Update attachment metadata
     *
     * @param int   $attachment_id Attachment ID.
     * @param array $image_data Image data.
     * @param array $params Parameters.
     */
    private function update_attachment_metadata( $attachment_id, $image_data, $params ) {
        // Update alt text
        update_post_meta( $attachment_id, '_wp_attachment_image_alt', $params['alt_text'] );
        
        // Update custom metadata
        update_post_meta( $attachment_id, '_ai_composer_generated', true );
        update_post_meta( $attachment_id, '_ai_composer_prompt', $params['prompt'] );
        update_post_meta( $attachment_id, '_ai_composer_style', $params['style'] );
        update_post_meta( $attachment_id, '_ai_composer_source', $params['source'] );
        update_post_meta( $attachment_id, '_ai_composer_license', $image_data['license'] ?? '' );
        update_post_meta( $attachment_id, '_ai_composer_attribution', $image_data['attribution'] ?? '' );
        
        if ( isset( $image_data['cost_usd'] ) ) {
            update_post_meta( $attachment_id, '_ai_composer_cost_usd', $image_data['cost_usd'] );
        }
    }

    /**
     * Generate filename for image
     *
     * @param string $prompt Image prompt.
     * @param string $style Image style.
     * @return string Generated filename.
     */
    private function generate_filename( $prompt, $style ) {
        $base_name = sanitize_file_name( wp_trim_words( $prompt, 5, '' ) );
        $base_name = preg_replace( '/[^a-z0-9-_]/', '-', strtolower( $base_name ) );
        $base_name = preg_replace( '/-+/', '-', $base_name );
        $base_name = trim( $base_name, '-' );
        
        if ( empty( $base_name ) ) {
            $base_name = 'ai-generated-image';
        }
        
        $timestamp = time();
        $random = wp_rand( 1000, 9999 );
        
        return "{$base_name}-{$style}-{$timestamp}-{$random}.jpg";
    }

    /**
     * Download image from URL
     *
     * @param string $url Image URL.
     * @return string Image content.
     * @throws Exception If download fails.
     */
    private function download_image( $url ) {
        $response = wp_remote_get( $url, [
            'timeout' => 60,
            'user-agent' => 'AI Page Composer/1.0'
        ] );
        
        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Image download failed: ' . $response->get_error_message() );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            throw new Exception( 'Image download failed with HTTP ' . $response_code );
        }
        
        return wp_remote_retrieve_body( $response );
    }

    /**
     * Enhance prompt for specific style
     *
     * @param string $prompt Original prompt.
     * @param string $style Image style.
     * @return string Enhanced prompt.
     */
    private function enhance_prompt_for_style( $prompt, $style ) {
        $style_enhancements = [
            'photographic' => 'professional photography, high quality, realistic',
            'illustration' => 'digital illustration, clean design, vector style',
            'abstract' => 'abstract art, modern design, geometric shapes',
            'minimalist' => 'minimalist design, clean lines, simple composition',
            'artistic' => 'artistic rendering, creative interpretation, expressive',
            'technical' => 'technical diagram, clear details, informative'
        ];
        
        $enhancement = $style_enhancements[ $style ] ?? '';
        
        if ( $enhancement ) {
            return $prompt . ', ' . $enhancement;
        }
        
        return $prompt;
    }

    /**
     * Determine DALL-E size parameter
     *
     * @param array $dimensions Requested dimensions.
     * @return string DALL-E size parameter.
     */
    private function determine_dalle_size( $dimensions ) {
        $width = $dimensions['width'] ?? 0;
        $height = $dimensions['height'] ?? 0;
        
        // DALL-E 3 supported sizes
        if ( $width > $height ) {
            return '1792x1024'; // Landscape
        } elseif ( $height > $width ) {
            return '1024x1792'; // Portrait
        } else {
            return '1024x1024'; // Square
        }
    }

    /**
     * Get size dimensions
     *
     * @param string $size Size parameter.
     * @return array Width and height.
     */
    private function get_size_dimensions( $size ) {
        $dimensions = [
            '1024x1024' => [ 'width' => 1024, 'height' => 1024 ],
            '1792x1024' => [ 'width' => 1792, 'height' => 1024 ],
            '1024x1792' => [ 'width' => 1024, 'height' => 1792 ]
        ];
        
        return $dimensions[ $size ] ?? [ 'width' => 1024, 'height' => 1024 ];
    }

    /**
     * Calculate DALL-E cost
     *
     * @param string $size Size parameter.
     * @return float Cost in USD.
     */
    private function calculate_dalle_cost( $size ) {
        // DALL-E 3 pricing (as of 2024)
        $pricing = [
            '1024x1024' => 0.040,
            '1792x1024' => 0.080,
            '1024x1792' => 0.080
        ];
        
        return $pricing[ $size ] ?? 0.040;
    }

    /**
     * Filter images by license
     *
     * @param array $images Image results.
     * @param array $license_filter License filter.
     * @return array Filtered images.
     */
    private function filter_by_license( $images, $license_filter ) {
        if ( empty( $license_filter ) ) {
            return $images;
        }
        
        // For Unsplash, all images are CC0, so no filtering needed
        // For other providers, this would need to be implemented
        return $images;
    }
}