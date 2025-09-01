<?php
/**
 * AI Service Client Class - Production Mode API Integration
 * 
 * This file contains the AI_Service_Client class that handles production mode
 * integration with OpenAI and MVDB services for real AI-powered outline generation.
 * It manages API calls, response processing, and error handling.
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\API;

use AIPageComposer\Admin\Settings_Manager;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI Service Client class for production mode integration
 */
class AI_Service_Client {

    /**
     * Settings manager instance
     *
     * @var Settings_Manager
     */
    private $settings_manager;

    /**
     * OpenAI API endpoint
     *
     * @var string
     */
    private $openai_endpoint = 'https://api.openai.com/v1/chat/completions';

    /**
     * MVDB API endpoint base
     *
     * @var string
     */
    private $mvdb_endpoint;

    /**
     * Constructor
     *
     * @param Settings_Manager $settings_manager Settings manager instance.
     */
    public function __construct( $settings_manager ) {
        $this->settings_manager = $settings_manager;
        
        $settings = $this->settings_manager->get_all_settings();
        $this->mvdb_endpoint = $settings['api_settings']['mvdb_endpoint'] ?? 'https://api.mvdb.example.com';
    }

    /**
     * Generate outline using AI services
     *
     * @param string $prompt Generation prompt.
     * @param array  $mvdb_context MVDB context data.
     * @param float  $alpha Hybrid mode alpha value.
     * @return array AI response data.
     * @throws Exception If API call fails.
     */
    public function generate_outline( $prompt, $mvdb_context, $alpha ) {
        // Build complete prompt with MVDB context
        $enhanced_prompt = $this->enhance_prompt_with_context( $prompt, $mvdb_context );

        // Call OpenAI API
        $response = $this->call_openai_api( $enhanced_prompt, $alpha );

        return $this->parse_ai_response( $response );
    }

    /**
     * Retrieve context from MVDB service
     *
     * @param string $query Search query.
     * @param array  $namespaces Search namespaces.
     * @param int    $k Number of results.
     * @param float  $min_score Minimum similarity score.
     * @return array MVDB results.
     */
    public function retrieve_mvdb_context( $query, $namespaces, $k, $min_score ) {
        $settings = $this->settings_manager->get_all_settings();
        $api_key = $settings['api_settings']['mvdb_api_key'] ?? '';

        if ( empty( $api_key ) ) {
            return array();
        }

        try {
            $response = wp_remote_post( $this->mvdb_endpoint . '/vector-search', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode( array(
                    'query' => $query,
                    'namespaces' => $namespaces,
                    'k' => $k,
                    'min_score' => $min_score,
                ) ),
                'timeout' => 30,
            ) );

            if ( is_wp_error( $response ) ) {
                error_log( '[AI Composer] MVDB API error: ' . $response->get_error_message() );
                return array();
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            return $data['results'] ?? array();

        } catch ( Exception $e ) {
            error_log( '[AI Composer] MVDB retrieval failed: ' . $e->getMessage() );
            return array();
        }
    }

    /**
     * Enhance prompt with MVDB context
     *
     * @param string $prompt Original prompt.
     * @param array  $mvdb_context MVDB context results.
     * @return string Enhanced prompt.
     */
    private function enhance_prompt_with_context( $prompt, $mvdb_context ) {
        if ( empty( $mvdb_context ) ) {
            return $prompt;
        }

        $context_text = "Relevant context from knowledge base:\n";
        foreach ( $mvdb_context as $chunk ) {
            $context_text .= "- " . $chunk['content'] . "\n";
        }

        return $context_text . "\n" . $prompt;
    }

    /**
     * Call OpenAI API for outline generation
     *
     * @param string $prompt Enhanced prompt.
     * @param float  $alpha Hybrid mode alpha value.
     * @return array OpenAI response.
     * @throws Exception If API call fails.
     */
    private function call_openai_api( $prompt, $alpha ) {
        $settings = $this->settings_manager->get_all_settings();
        $api_key = $settings['api_settings']['openai_api_key'] ?? '';

        if ( empty( $api_key ) ) {
            throw new Exception( __( 'OpenAI API key not configured', 'ai-page-composer' ) );
        }

        // Build system message for outline generation
        $system_message = $this->build_system_message( $alpha );

        $request_body = array(
            'model' => 'gpt-4',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_message,
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt,
                ),
            ),
            'temperature' => 0.7,
            'max_tokens' => 2000,
        );

        $response = wp_remote_post( $this->openai_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( $request_body ),
            'timeout' => 60,
        ) );

        if ( is_wp_error( $response ) ) {
            throw new Exception( 'OpenAI API request failed: ' . $response->get_error_message() );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            $body = wp_remote_retrieve_body( $response );
            $error_data = json_decode( $body, true );
            $error_message = $error_data['error']['message'] ?? 'Unknown API error';
            
            throw new Exception( 'OpenAI API error (' . $response_code . '): ' . $error_message );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new Exception( 'Invalid JSON response from OpenAI API' );
        }

        return $data;
    }

    /**
     * Build system message for OpenAI
     *
     * @param float $alpha Hybrid mode alpha value.
     * @return string System message.
     */
    private function build_system_message( $alpha ) {
        $base_message = "You are an expert content strategist creating structured outlines for web content. ";
        $base_message .= "Your task is to generate a detailed content outline based on the provided brief and requirements. ";

        if ( $alpha > 0.5 ) {
            $base_message .= "Focus heavily on the provided context and knowledge base information. ";
        } else {
            $base_message .= "Be creative while incorporating relevant context when available. ";
        }

        $base_message .= "Return a JSON response with this exact structure:\n";
        $base_message .= "{\n";
        $base_message .= '  "sections": [\n';
        $base_message .= "    {\n";
        $base_message .= '      "heading": "Section Title",\n';
        $base_message .= '      "target_words": 150,\n';
        $base_message .= '      "needs_image": true,\n';
        $base_message .= '      "subheadings": ["Subheading 1", "Subheading 2"]\n';
        $base_message .= "    }\n";
        $base_message .= "  ]\n";
        $base_message .= "}\n\n";
        $base_message .= "Make headings specific and engaging. Include 2-3 relevant subheadings per section. ";
        $base_message .= "Set needs_image to true for visual sections like hero, testimonials, team, pricing.";

        return $base_message;
    }

    /**
     * Parse AI response into structured format
     *
     * @param array $openai_response OpenAI API response.
     * @return array Parsed response data.
     * @throws Exception If response parsing fails.
     */
    private function parse_ai_response( $openai_response ) {
        if ( ! isset( $openai_response['choices'][0]['message']['content'] ) ) {
            throw new Exception( 'Invalid OpenAI response structure' );
        }

        $content = $openai_response['choices'][0]['message']['content'];
        
        // Extract JSON from response (in case there's extra text)
        $json_start = strpos( $content, '{' );
        $json_end = strrpos( $content, '}' );
        
        if ( $json_start === false || $json_end === false ) {
            throw new Exception( 'No valid JSON found in OpenAI response' );
        }

        $json_content = substr( $content, $json_start, $json_end - $json_start + 1 );
        $parsed_data = json_decode( $json_content, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new Exception( 'Failed to parse JSON from OpenAI response: ' . json_last_error_msg() );
        }

        if ( ! isset( $parsed_data['sections'] ) || ! is_array( $parsed_data['sections'] ) ) {
            throw new Exception( 'Invalid section structure in OpenAI response' );
        }

        return $parsed_data;
    }

    /**
     * Test API connection
     *
     * @param string $service Service to test ('openai' or 'mvdb').
     * @return array Test result.
     */
    public function test_connection( $service ) {
        $settings = $this->settings_manager->get_all_settings();
        
        switch ( $service ) {
            case 'openai':
                return $this->test_openai_connection( $settings['api_settings']['openai_api_key'] ?? '' );
                
            case 'mvdb':
                return $this->test_mvdb_connection( $settings['api_settings']['mvdb_api_key'] ?? '' );
                
            default:
                return array(
                    'success' => false,
                    'message' => 'Unknown service: ' . $service,
                );
        }
    }

    /**
     * Test OpenAI API connection
     *
     * @param string $api_key OpenAI API key.
     * @return array Test result.
     */
    private function test_openai_connection( $api_key ) {
        if ( empty( $api_key ) ) {
            return array(
                'success' => false,
                'message' => 'OpenAI API key not provided',
            );
        }

        try {
            $response = wp_remote_get( 'https://api.openai.com/v1/models', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                ),
                'timeout' => 10,
            ) );

            if ( is_wp_error( $response ) ) {
                return array(
                    'success' => false,
                    'message' => $response->get_error_message(),
                );
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            
            if ( $response_code === 200 ) {
                return array(
                    'success' => true,
                    'message' => 'OpenAI API connection successful',
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'OpenAI API returned status code: ' . $response_code,
                );
            }

        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            );
        }
    }

    /**
     * Test MVDB API connection
     *
     * @param string $api_key MVDB API key.
     * @return array Test result.
     */
    private function test_mvdb_connection( $api_key ) {
        if ( empty( $api_key ) ) {
            return array(
                'success' => false,
                'message' => 'MVDB API key not provided',
            );
        }

        try {
            $response = wp_remote_get( $this->mvdb_endpoint . '/health', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                ),
                'timeout' => 10,
            ) );

            if ( is_wp_error( $response ) ) {
                return array(
                    'success' => false,
                    'message' => $response->get_error_message(),
                );
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            
            if ( $response_code === 200 ) {
                return array(
                    'success' => true,
                    'message' => 'MVDB API connection successful',
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'MVDB API returned status code: ' . $response_code,
                );
            }

        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            );
        }
    }

    /**
     * Generate content for section generation
     *
     * @param array $params Generation parameters.
     * @return array Generation result.
     * @throws Exception If generation fails.
     */
    public function generate_content( $params ) {
        $prompt = $params['prompt'] ?? '';
        $mode = $params['mode'] ?? 'hybrid';
        $alpha = $params['alpha'] ?? 0.7;
        $block_spec = $params['block_spec'] ?? [];

        if ( empty( $prompt ) ) {
            throw new Exception( __( 'No prompt provided for content generation', 'ai-page-composer' ) );
        }

        // Build system message for section generation
        $system_message = $this->build_section_system_message( $mode, $alpha, $block_spec );

        $request_body = array(
            'model' => 'gpt-4',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_message,
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt,
                ),
            ),
            'temperature' => 0.7,
            'max_tokens' => 2000,
        );

        $response = $this->call_openai_api( $prompt, $alpha );

        return array(
            'content' => $response['content'] ?? '',
            'token_count' => $response['usage']['total_tokens'] ?? 0,
            'cost_usd' => $this->calculate_openai_cost( $response['usage'] ?? [] )
        );
    }

    /**
     * Build system message for section generation
     *
     * @param string $mode Generation mode.
     * @param float  $alpha Alpha value for hybrid mode.
     * @param array  $block_spec Block specification.
     * @return string System message.
     */
    private function build_section_system_message( $mode, $alpha, $block_spec ) {
        $message = "You are an expert content generator for WordPress websites. Your task is to create high-quality, engaging content for specific website sections.\n\n";
        
        $message .= "Generation Mode: {$mode}\n";
        
        if ( $mode === 'hybrid' ) {
            $message .= "Alpha Weight: {$alpha} (balance between provided context and creative generation)\n";
        }
        
        if ( ! empty( $block_spec ) ) {
            $message .= "\nBlock Requirements:\n";
            $message .= "- Block Type: {$block_spec['block_name']}\n";
            $message .= "- Plugin: {$block_spec['plugin']}\n";
            $message .= "- Section Type: {$block_spec['section_type']}\n";
        }
        
        $message .= "\nInstructions:\n";
        $message .= "1. Generate content that is appropriate for the specified block type and section\n";
        $message .= "2. Ensure content is engaging, professional, and well-structured\n";
        $message .= "3. Include natural citations and references when relevant context is provided\n";
        $message .= "4. Format content appropriately for the target block structure\n";
        $message .= "5. Keep content focused and relevant to the provided brief\n";
        
        return $message;
    }

    /**
     * Calculate OpenAI API cost
     *
     * @param array $usage Usage statistics from API response.
     * @return float Cost in USD.
     */
    private function calculate_openai_cost( $usage ) {
        // GPT-4 pricing (as of 2024)
        $input_cost_per_token = 0.00003;  // $0.03 per 1K tokens
        $output_cost_per_token = 0.00006; // $0.06 per 1K tokens
        
        $prompt_tokens = $usage['prompt_tokens'] ?? 0;
        $completion_tokens = $usage['completion_tokens'] ?? 0;
        
        $cost = ( $prompt_tokens * $input_cost_per_token ) + ( $completion_tokens * $output_cost_per_token );
        
        return round( $cost, 4 );
    }
}