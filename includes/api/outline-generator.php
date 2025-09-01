<?php
/**
 * Outline Generator Class - Core Generation Logic with Mode Switching
 * 
 * This file contains the Outline_Generator class that handles the core outline generation
 * logic with mode switching between development stub mode and production AI mode.
 * It coordinates between different services based on configuration settings.
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\API;

use AIPageComposer\API\LLM_Stub_Service;
use AIPageComposer\API\AI_Service_Client;
use AIPageComposer\Blueprints\Blueprint_Manager;
use AIPageComposer\Admin\Block_Preferences;
use AIPageComposer\Admin\Settings_Manager;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Outline Generator class for content outline generation
 */
class Outline_Generator {

    /**
     * Blueprint manager instance
     *
     * @var Blueprint_Manager
     */
    private $blueprint_manager;

    /**
     * Block preferences instance
     *
     * @var Block_Preferences
     */
    private $block_preferences;

    /**
     * Settings manager instance
     *
     * @var Settings_Manager
     */
    private $settings_manager;

    /**
     * LLM stub service instance
     *
     * @var LLM_Stub_Service
     */
    private $stub_service;

    /**
     * AI service client instance
     *
     * @var AI_Service_Client
     */
    private $ai_service;

    /**
     * Constructor
     *
     * @param Blueprint_Manager $blueprint_manager Blueprint manager instance.
     * @param Block_Preferences $block_preferences Block preferences instance.
     */
    public function __construct( $blueprint_manager, $block_preferences ) {
        $this->blueprint_manager = $blueprint_manager;
        $this->block_preferences = $block_preferences;
        $this->settings_manager = new Settings_Manager();
        $this->stub_service = new LLM_Stub_Service();
        $this->ai_service = new AI_Service_Client( $this->settings_manager );
    }

    /**
     * Generate content outline
     *
     * @param array $params Generation parameters.
     * @param array $blueprint Blueprint data.
     * @return array Generated outline data.
     * @throws Exception If generation fails.
     */
    public function generate( $params, $blueprint ) {
        $use_stub = $this->should_use_stub_mode();

        if ( $use_stub ) {
            return $this->generate_stub_outline( $params, $blueprint );
        } else {
            return $this->generate_ai_outline( $params, $blueprint );
        }
    }

    /**
     * Determine if stub mode should be used
     *
     * @return bool True if stub mode should be used.
     */
    private function should_use_stub_mode() {
        // Check for development environment flag
        if ( defined( 'AI_COMPOSER_DEV_MODE' ) && AI_COMPOSER_DEV_MODE ) {
            return true;
        }

        // Check for stub mode setting
        $settings = $this->settings_manager->get_all_settings();
        return $settings['development_settings']['use_llm_stub'] ?? false;
    }

    /**
     * Generate outline using stub service
     *
     * @param array $params Generation parameters.
     * @param array $blueprint Blueprint data.
     * @return array Generated outline data.
     */
    private function generate_stub_outline( $params, $blueprint ) {
        try {
            $outline_data = $this->stub_service->generate_outline( $params, $blueprint );

            // Add generation metadata
            $outline_data['mode'] = 'stub';
            $outline_data['estimated_cost'] = 0.0;
            $outline_data['generated_at'] = current_time( 'c' );
            $outline_data['blueprint_id'] = $blueprint['post']->ID;

            return $outline_data;

        } catch ( Exception $e ) {
            error_log( '[AI Composer] Stub outline generation failed: ' . $e->getMessage() );
            throw new Exception( __( 'Failed to generate outline using stub service', 'ai-page-composer' ) );
        }
    }

    /**
     * Generate outline using AI service
     *
     * @param array $params Generation parameters.
     * @param array $blueprint Blueprint data.
     * @return array Generated outline data.
     */
    private function generate_ai_outline( $params, $blueprint ) {
        try {
            // Build prompt from blueprint and parameters
            $prompt = $this->build_generation_prompt( $params, $blueprint );

            // Retrieve MVDB context if configured
            $mvdb_context = array();
            if ( ! empty( $params['mvdb_params']['namespaces'] ) ) {
                $mvdb_context = $this->ai_service->retrieve_mvdb_context(
                    $params['brief'],
                    $params['mvdb_params']['namespaces'],
                    $params['mvdb_params']['k'],
                    $params['mvdb_params']['min_score']
                );
            }

            // Generate outline using AI service
            $ai_response = $this->ai_service->generate_outline( $prompt, $mvdb_context, $params['alpha'] );

            // Process and structure the response
            $outline_data = $this->process_ai_response( $ai_response, $blueprint );

            // Add generation metadata
            $outline_data['mode'] = 'hybrid';
            $outline_data['estimated_cost'] = $this->calculate_generation_cost( $prompt, $ai_response );
            $outline_data['generated_at'] = current_time( 'c' );
            $outline_data['blueprint_id'] = $blueprint['post']->ID;

            // Update cost tracking
            $this->update_cost_tracking( $outline_data['estimated_cost'] );

            return $outline_data;

        } catch ( Exception $e ) {
            error_log( '[AI Composer] AI outline generation failed: ' . $e->getMessage() );
            
            // Fallback to stub if AI fails
            error_log( '[AI Composer] Falling back to stub mode due to AI service failure' );
            return $this->generate_stub_outline( $params, $blueprint );
        }
    }

    /**
     * Build generation prompt from parameters and blueprint
     *
     * @param array $params Generation parameters.
     * @param array $blueprint Blueprint data.
     * @return string Generated prompt.
     */
    private function build_generation_prompt( $params, $blueprint ) {
        $prompt_parts = array();

        // Content brief
        $prompt_parts[] = "Content Brief: " . $params['brief'];

        // Target audience
        if ( ! empty( $params['audience'] ) ) {
            $prompt_parts[] = "Target Audience: " . $params['audience'];
        }

        // Content tone
        $prompt_parts[] = "Tone: " . ucfirst( $params['tone'] );

        // Blueprint structure
        $blueprint_sections = $blueprint['schema']['sections'] ?? array();
        if ( ! empty( $blueprint_sections ) ) {
            $prompt_parts[] = "Required Sections:";
            foreach ( $blueprint_sections as $section ) {
                $prompt_parts[] = sprintf(
                    "- %s (%s, %d words, %s images)",
                    $section['heading'] ?? 'Section',
                    $section['type'] ?? 'content',
                    $section['word_target'] ?? 100,
                    $section['media_policy'] === 'required' ? 'with' : 'optional'
                );
            }
        }

        // Generation instructions
        $prompt_parts[] = "\nGenerate a structured content outline with:";
        $prompt_parts[] = "- Specific headings for each section";
        $prompt_parts[] = "- Target word counts";
        $prompt_parts[] = "- Image requirements";
        $prompt_parts[] = "- 2-3 subheadings per section where appropriate";

        return implode( "\n", $prompt_parts );
    }

    /**
     * Process AI response into structured outline format
     *
     * @param array $ai_response AI service response.
     * @param array $blueprint Blueprint data.
     * @return array Structured outline data.
     */
    private function process_ai_response( $ai_response, $blueprint ) {
        $sections = array();
        $total_words = 0;

        // Parse AI response sections
        $ai_sections = $ai_response['sections'] ?? array();
        $blueprint_sections = $blueprint['schema']['sections'] ?? array();

        foreach ( $ai_sections as $index => $ai_section ) {
            $blueprint_section = $blueprint_sections[ $index ] ?? array();
            
            $section = array(
                'id' => 'section-' . ( $index + 1 ),
                'heading' => $ai_section['heading'] ?? $blueprint_section['heading'] ?? 'Section ' . ( $index + 1 ),
                'type' => $blueprint_section['type'] ?? 'content',
                'targetWords' => intval( $ai_section['target_words'] ?? $blueprint_section['word_target'] ?? 150 ),
                'needsImage' => $this->determine_image_requirement( $ai_section, $blueprint_section ),
                'mode' => 'hybrid',
                'subheadings' => $ai_section['subheadings'] ?? array(),
            );

            $sections[] = $section;
            $total_words += $section['targetWords'];
        }

        // If no AI sections, fall back to blueprint structure
        if ( empty( $sections ) && ! empty( $blueprint_sections ) ) {
            foreach ( $blueprint_sections as $index => $blueprint_section ) {
                $section = array(
                    'id' => 'section-' . ( $index + 1 ),
                    'heading' => $blueprint_section['heading'] ?? 'Section ' . ( $index + 1 ),
                    'type' => $blueprint_section['type'] ?? 'content',
                    'targetWords' => intval( $blueprint_section['word_target'] ?? 150 ),
                    'needsImage' => $blueprint_section['media_policy'] === 'required',
                    'mode' => 'hybrid',
                    'subheadings' => array(),
                );

                $sections[] = $section;
                $total_words += $section['targetWords'];
            }
        }

        return array(
            'sections' => $sections,
            'total_words' => $total_words,
            'estimated_time' => $this->estimate_writing_time( $total_words ),
        );
    }

    /**
     * Determine image requirement for section
     *
     * @param array $ai_section AI generated section data.
     * @param array $blueprint_section Blueprint section template.
     * @return bool True if image is needed.
     */
    private function determine_image_requirement( $ai_section, $blueprint_section ) {
        // Check AI recommendation first
        if ( isset( $ai_section['needs_image'] ) ) {
            return (bool) $ai_section['needs_image'];
        }

        // Fall back to blueprint policy
        if ( isset( $blueprint_section['media_policy'] ) ) {
            return $blueprint_section['media_policy'] === 'required';
        }

        // Default based on section type
        $image_sections = array( 'hero', 'testimonial', 'team', 'pricing' );
        $section_type = $blueprint_section['type'] ?? 'content';
        
        return in_array( $section_type, $image_sections, true );
    }

    /**
     * Calculate generation cost estimate
     *
     * @param string $prompt Generation prompt.
     * @param array  $ai_response AI service response.
     * @return float Estimated cost in USD.
     */
    private function calculate_generation_cost( $prompt, $ai_response ) {
        // Simple token-based cost estimation
        $prompt_tokens = $this->estimate_tokens( $prompt );
        $response_tokens = $this->estimate_tokens( wp_json_encode( $ai_response ) );
        
        // OpenAI GPT-4 pricing (approximate)
        $input_cost_per_1k = 0.01;  // $0.01 per 1K input tokens
        $output_cost_per_1k = 0.03; // $0.03 per 1K output tokens
        
        $input_cost = ( $prompt_tokens / 1000 ) * $input_cost_per_1k;
        $output_cost = ( $response_tokens / 1000 ) * $output_cost_per_1k;
        
        return round( $input_cost + $output_cost, 4 );
    }

    /**
     * Estimate token count for text
     *
     * @param string $text Text to estimate.
     * @return int Estimated token count.
     */
    private function estimate_tokens( $text ) {
        // Rough estimation: 1 token = ~4 characters
        return intval( strlen( $text ) / 4 );
    }

    /**
     * Estimate writing time based on word count
     *
     * @param int $word_count Total word count.
     * @return int Estimated time in minutes.
     */
    private function estimate_writing_time( $word_count ) {
        // Assumption: ~50 words per minute for quality content writing
        return max( 5, intval( $word_count / 50 ) );
    }

    /**
     * Update cost tracking
     *
     * @param float $cost Generation cost.
     */
    private function update_cost_tracking( $cost ) {
        if ( $cost > 0 ) {
            $daily_costs = get_option( 'ai_composer_daily_costs', 0.0 );
            $monthly_costs = get_option( 'ai_composer_monthly_costs', 0.0 );
            
            update_option( 'ai_composer_daily_costs', $daily_costs + $cost );
            update_option( 'ai_composer_monthly_costs', $monthly_costs + $cost );
        }
    }
}