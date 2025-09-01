<?php
/**
 * Settings Manager Class - WordPress Settings API Management
 * 
 * This file manages all plugin settings using WordPress Settings API.
 * It handles registration, sanitization, validation, and retrieval of
 * all AI Page Composer settings including API keys, generation defaults,
 * content policies, block preferences, and cost management.
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\Admin;

use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings Manager class for WordPress Settings API
 */
class Settings_Manager {

    /**
     * Settings option name
     */
    const OPTION_NAME = 'ai_composer_settings';

    /**
     * Settings group name
     */
    const SETTINGS_GROUP = 'ai_composer_settings_group';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Register all plugin settings
     */
    public function register_settings() {
        // Register main settings option
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_NAME,
            array(
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default' => $this->get_default_settings(),
            )
        );

        // Register individual setting sections
        $this->register_api_settings();
        $this->register_mvdb_settings();
        $this->register_generation_settings();
        $this->register_content_policy_settings();
        $this->register_block_preference_settings();
        $this->register_cost_management_settings();
    }

    /**
     * Register API configuration settings
     */
    private function register_api_settings() {
        add_settings_section(
            'ai_composer_api_section',
            __( 'API Configuration', 'ai-page-composer' ),
            array( $this, 'api_section_callback' ),
            self::OPTION_NAME
        );

        // OpenAI API Key
        add_settings_field(
            'openai_api_key',
            __( 'OpenAI API Key', 'ai-page-composer' ),
            array( $this, 'api_key_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_api_section',
            array( 'key' => 'openai_api_key', 'required' => true )
        );

        // MVDB API Key
        add_settings_field(
            'mvdb_api_key',
            __( 'Vector Database API Key', 'ai-page-composer' ),
            array( $this, 'api_key_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_api_section',
            array( 'key' => 'mvdb_api_key', 'required' => true )
        );

        // Image API Key
        add_settings_field(
            'image_api_key',
            __( 'Image Generation API Key', 'ai-page-composer' ),
            array( $this, 'api_key_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_api_section',
            array( 'key' => 'image_api_key', 'required' => false )
        );
    }

    /**
     * Register MVDB configuration settings
     */
    private function register_mvdb_settings() {
        add_settings_section(
            'ai_composer_mvdb_section',
            __( 'Vector Database Configuration', 'ai-page-composer' ),
            array( $this, 'mvdb_section_callback' ),
            self::OPTION_NAME
        );

        // MVDB API URL
        add_settings_field(
            'mvdb_api_url',
            __( 'MVDB API URL', 'ai-page-composer' ),
            array( $this, 'url_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_mvdb_section',
            array( 'key' => 'mvdb_api_url', 'section' => 'mvdb_settings', 'required' => true )
        );

        // MVDB Access Token
        add_settings_field(
            'mvdb_access_token',
            __( 'MVDB Access Token', 'ai-page-composer' ),
            array( $this, 'api_key_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_mvdb_section',
            array( 'key' => 'mvdb_access_token', 'section' => 'mvdb_settings', 'required' => true )
        );

        // Cache TTL
        add_settings_field(
            'mvdb_cache_ttl',
            __( 'Cache TTL (seconds)', 'ai-page-composer' ),
            array( $this, 'number_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_mvdb_section',
            array(
                'key' => 'mvdb_cache_ttl',
                'section' => 'mvdb_settings',
                'min' => 300,
                'max' => 86400,
                'step' => 300,
            )
        );

        // Timeout
        add_settings_field(
            'mvdb_timeout_seconds',
            __( 'Request Timeout (seconds)', 'ai-page-composer' ),
            array( $this, 'number_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_mvdb_section',
            array(
                'key' => 'mvdb_timeout_seconds',
                'section' => 'mvdb_settings',
                'min' => 5,
                'max' => 120,
                'step' => 5,
            )
        );

        // Debug Logging
        add_settings_field(
            'mvdb_enable_debug_logging',
            __( 'Enable Debug Logging', 'ai-page-composer' ),
            array( $this, 'checkbox_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_mvdb_section',
            array( 'key' => 'mvdb_enable_debug_logging', 'section' => 'mvdb_settings' )
        );
    }

    /**
     * Register generation default settings
     */
    private function register_generation_settings() {
        add_settings_section(
            'ai_composer_generation_section',
            __( 'Generation Defaults', 'ai-page-composer' ),
            array( $this, 'generation_section_callback' ),
            self::OPTION_NAME
        );

        // Default Mode
        add_settings_field(
            'default_mode',
            __( 'Default Generation Mode', 'ai-page-composer' ),
            array( $this, 'select_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_generation_section',
            array(
                'key' => 'default_mode',
                'options' => array(
                    'grounded' => __( 'Grounded (Data-based)', 'ai-page-composer' ),
                    'hybrid' => __( 'Hybrid (Mixed)', 'ai-page-composer' ),
                    'generative' => __( 'Generative (Creative)', 'ai-page-composer' ),
                ),
            )
        );

        // Alpha Weight
        add_settings_field(
            'alpha_weight',
            __( 'Alpha Weight', 'ai-page-composer' ),
            array( $this, 'range_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_generation_section',
            array(
                'key' => 'alpha_weight',
                'min' => 0.0,
                'max' => 1.0,
                'step' => 0.1,
            )
        );

        // K Value
        add_settings_field(
            'k_value',
            __( 'K Value (Retrieval Count)', 'ai-page-composer' ),
            array( $this, 'number_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_generation_section',
            array(
                'key' => 'k_value',
                'min' => 1,
                'max' => 50,
            )
        );

        // Min Score
        add_settings_field(
            'min_score',
            __( 'Minimum Score', 'ai-page-composer' ),
            array( $this, 'range_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_generation_section',
            array(
                'key' => 'min_score',
                'min' => 0.0,
                'max' => 1.0,
                'step' => 0.1,
            )
        );
    }

    /**
     * Register content policy settings
     */
    private function register_content_policy_settings() {
        add_settings_section(
            'ai_composer_content_policy_section',
            __( 'Content Policies', 'ai-page-composer' ),
            array( $this, 'content_policy_section_callback' ),
            self::OPTION_NAME
        );

        // Image Generation Policy
        add_settings_field(
            'image_generation_policy',
            __( 'Image Generation Policy', 'ai-page-composer' ),
            array( $this, 'select_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_content_policy_section',
            array(
                'key' => 'image_generation_policy',
                'options' => array(
                    'always' => __( 'Always Generate', 'ai-page-composer' ),
                    'auto' => __( 'Auto (when appropriate)', 'ai-page-composer' ),
                    'manual' => __( 'Manual Selection', 'ai-page-composer' ),
                    'never' => __( 'Never Generate', 'ai-page-composer' ),
                ),
            )
        );

        // Internal Linking
        add_settings_field(
            'internal_linking_enabled',
            __( 'Enable Internal Linking', 'ai-page-composer' ),
            array( $this, 'checkbox_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_content_policy_section',
            array( 'key' => 'internal_linking_enabled' )
        );
    }

    /**
     * Register block preference settings
     */
    private function register_block_preference_settings() {
        add_settings_section(
            'ai_composer_block_preferences_section',
            __( 'Block Preferences', 'ai-page-composer' ),
            array( $this, 'block_preferences_section_callback' ),
            self::OPTION_NAME
        );

        // Detection Enabled
        add_settings_field(
            'detection_enabled',
            __( 'Enable Plugin Detection', 'ai-page-composer' ),
            array( $this, 'checkbox_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_block_preferences_section',
            array( 'key' => 'detection_enabled' )
        );
    }

    /**
     * Register cost management settings
     */
    private function register_cost_management_settings() {
        add_settings_section(
            'ai_composer_cost_management_section',
            __( 'Cost Management', 'ai-page-composer' ),
            array( $this, 'cost_management_section_callback' ),
            self::OPTION_NAME
        );

        // Daily Budget
        add_settings_field(
            'daily_budget_usd',
            __( 'Daily Budget (USD)', 'ai-page-composer' ),
            array( $this, 'number_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_cost_management_section',
            array(
                'key' => 'daily_budget_usd',
                'min' => 0.01,
                'max' => 1000.0,
                'step' => 0.01,
            )
        );

        // Per-run Limit
        add_settings_field(
            'per_run_limit_usd',
            __( 'Per-run Limit (USD)', 'ai-page-composer' ),
            array( $this, 'number_field_callback' ),
            self::OPTION_NAME,
            'ai_composer_cost_management_section',
            array(
                'key' => 'per_run_limit_usd',
                'min' => 0.01,
                'max' => 100.0,
                'step' => 0.01,
            )
        );
    }

    /**
     * Sanitize all settings
     *
     * @param array $input Raw input from form.
     * @return array Sanitized settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        $defaults = $this->get_default_settings();

        // Sanitize API settings
        if ( isset( $input['api_settings'] ) ) {
            $sanitized['api_settings'] = array(
                'openai_api_key' => Validation_Helper::sanitize_api_key( $input['api_settings']['openai_api_key'] ?? '' ),
                'mvdb_api_key' => Validation_Helper::sanitize_api_key( $input['api_settings']['mvdb_api_key'] ?? '' ),
                'image_api_key' => Validation_Helper::sanitize_api_key( $input['api_settings']['image_api_key'] ?? '' ),
            );
        } else {
            $sanitized['api_settings'] = $defaults['api_settings'];
        }

        // Sanitize MVDB settings
        if ( isset( $input['mvdb_settings'] ) ) {
            $sanitized['mvdb_settings'] = array(
                'api_url' => Validation_Helper::validate_url( $input['mvdb_settings']['api_url'] ?? $defaults['mvdb_settings']['api_url'] ),
                'access_token' => Validation_Helper::sanitize_api_key( $input['mvdb_settings']['access_token'] ?? '' ),
                'cache_ttl' => Validation_Helper::validate_int_range( $input['mvdb_settings']['cache_ttl'] ?? 3600, 300, 86400, 3600 ),
                'default_namespaces' => Validation_Helper::sanitize_namespace_array( $input['mvdb_settings']['default_namespaces'] ?? array( 'content' ) ),
                'max_results_per_query' => Validation_Helper::validate_int_range( $input['mvdb_settings']['max_results_per_query'] ?? 50, 1, 100, 50 ),
                'min_similarity_score' => Validation_Helper::validate_score_range( $input['mvdb_settings']['min_similarity_score'] ?? 0.5 ),
                'timeout_seconds' => Validation_Helper::validate_int_range( $input['mvdb_settings']['timeout_seconds'] ?? 30, 5, 120, 30 ),
                'retry_attempts' => Validation_Helper::validate_int_range( $input['mvdb_settings']['retry_attempts'] ?? 2, 0, 5, 2 ),
                'enable_debug_logging' => Validation_Helper::validate_checkbox( $input['mvdb_settings']['enable_debug_logging'] ?? false ),
            );
        } else {
            $sanitized['mvdb_settings'] = $defaults['mvdb_settings'];
        }

        // Sanitize generation defaults
        if ( isset( $input['generation_defaults'] ) ) {
            $sanitized['generation_defaults'] = array(
                'default_mode' => Validation_Helper::validate_generation_mode( $input['generation_defaults']['default_mode'] ?? 'hybrid' ),
                'alpha_weight' => Validation_Helper::validate_alpha_range( $input['generation_defaults']['alpha_weight'] ?? 0.7 ),
                'k_value' => Validation_Helper::validate_k_range( $input['generation_defaults']['k_value'] ?? 10 ),
                'min_score' => Validation_Helper::validate_score_range( $input['generation_defaults']['min_score'] ?? 0.5 ),
                'default_namespaces' => Validation_Helper::sanitize_namespace_array( $input['generation_defaults']['default_namespaces'] ?? array( 'content' ) ),
            );
        } else {
            $sanitized['generation_defaults'] = $defaults['generation_defaults'];
        }

        // Sanitize content policies
        if ( isset( $input['content_policies'] ) ) {
            $sanitized['content_policies'] = array(
                'image_generation_policy' => Validation_Helper::validate_image_policy( $input['content_policies']['image_generation_policy'] ?? 'auto' ),
                'internal_linking_enabled' => Validation_Helper::validate_checkbox( $input['content_policies']['internal_linking_enabled'] ?? true ),
                'max_internal_links' => Validation_Helper::validate_int_range( $input['content_policies']['max_internal_links'] ?? 3, 0, 10, 3 ),
                'citation_required' => Validation_Helper::validate_checkbox( $input['content_policies']['citation_required'] ?? true ),
                'license_filters' => Validation_Helper::sanitize_license_filters( $input['content_policies']['license_filters'] ?? array() ),
            );
        } else {
            $sanitized['content_policies'] = $defaults['content_policies'];
        }

        // Sanitize block preferences
        if ( isset( $input['block_preferences'] ) ) {
            $sanitized['block_preferences'] = array(
                'detection_enabled' => Validation_Helper::validate_checkbox( $input['block_preferences']['detection_enabled'] ?? true ),
                'plugin_priorities' => Validation_Helper::sanitize_plugin_priorities( $input['block_preferences']['plugin_priorities'] ?? array() ),
                'section_mappings' => Validation_Helper::sanitize_section_mappings( $input['block_preferences']['section_mappings'] ?? array() ),
                'custom_block_types' => Validation_Helper::sanitize_custom_block_types( $input['block_preferences']['custom_block_types'] ?? array() ),
            );
        } else {
            $sanitized['block_preferences'] = $defaults['block_preferences'];
        }

        // Sanitize cost management
        if ( isset( $input['cost_management'] ) ) {
            $sanitized['cost_management'] = array(
                'daily_budget_usd' => Validation_Helper::validate_budget_amount( $input['cost_management']['daily_budget_usd'] ?? 10.0 ),
                'per_run_limit_usd' => Validation_Helper::validate_per_run_limit( $input['cost_management']['per_run_limit_usd'] ?? 2.0 ),
                'token_limit_per_section' => Validation_Helper::validate_token_limit( $input['cost_management']['token_limit_per_section'] ?? 1000 ),
                'cost_alerts_enabled' => Validation_Helper::validate_checkbox( $input['cost_management']['cost_alerts_enabled'] ?? true ),
                'budget_reset_schedule' => Validation_Helper::validate_budget_schedule( $input['cost_management']['budget_reset_schedule'] ?? 'daily' ),
            );
        } else {
            $sanitized['cost_management'] = $defaults['cost_management'];
        }

        return $sanitized;
    }

    /**
     * Get default settings
     *
     * @return array Default settings
     */
    public function get_default_settings() {
        return array(
            'api_settings' => array(
                'openai_api_key' => '',
                'mvdb_api_key' => '',
                'image_api_key' => '',
            ),
            'mvdb_settings' => array(
                'api_url' => 'https://api.wpengine.com/smart-search/v1',
                'access_token' => '',
                'cache_ttl' => 3600,
                'default_namespaces' => array( 'content' ),
                'max_results_per_query' => 50,
                'min_similarity_score' => 0.5,
                'timeout_seconds' => 30,
                'retry_attempts' => 2,
                'enable_debug_logging' => false,
            ),
            'generation_defaults' => array(
                'default_mode' => 'hybrid',
                'alpha_weight' => 0.7,
                'k_value' => 10,
                'min_score' => 0.5,
                'default_namespaces' => array( 'content' ),
            ),
            'content_policies' => array(
                'image_generation_policy' => 'auto',
                'internal_linking_enabled' => true,
                'max_internal_links' => 3,
                'citation_required' => true,
                'license_filters' => array( 'CC-BY', 'CC-BY-SA', 'public-domain' ),
            ),
            'block_preferences' => array(
                'detection_enabled' => true,
                'plugin_priorities' => array(
                    'genesis_blocks' => 8,
                    'kadence_blocks' => 8,
                    'stackable' => 7,
                    'ultimate_addons' => 7,
                    'blocksy' => 6,
                    'core' => 5,
                ),
                'section_mappings' => array(
                    'hero' => 'auto',
                    'content' => 'auto',
                    'testimonial' => 'auto',
                    'pricing' => 'auto',
                    'team' => 'auto',
                    'faq' => 'auto',
                    'cta' => 'auto',
                ),
                'custom_block_types' => array(),
            ),
            'cost_management' => array(
                'daily_budget_usd' => 10.0,
                'per_run_limit_usd' => 2.0,
                'token_limit_per_section' => 1000,
                'cost_alerts_enabled' => true,
                'budget_reset_schedule' => 'daily',
            ),
        );
    }

    /**
     * Get all settings
     *
     * @return array All plugin settings
     */
    public function get_all_settings() {
        return get_option( self::OPTION_NAME, $this->get_default_settings() );
    }

    /**
     * Get specific setting value
     *
     * @param string $section Setting section.
     * @param string $key     Setting key.
     * @param mixed  $default Default value.
     * @return mixed Setting value
     */
    public function get_setting( $section, $key, $default = null ) {
        $settings = $this->get_all_settings();
        return $settings[ $section ][ $key ] ?? $default;
    }

    /**
     * Update specific setting
     *
     * @param string $section Setting section.
     * @param string $key     Setting key.
     * @param mixed  $value   Setting value.
     * @return bool Whether the update was successful
     */
    public function update_setting( $section, $key, $value ) {
        $settings = $this->get_all_settings();
        $settings[ $section ][ $key ] = $value;
        return update_option( self::OPTION_NAME, $settings );
    }

    // Field Callbacks
    public function api_section_callback() {
        echo '<p>' . esc_html__( 'Configure your API keys for AI services.', 'ai-page-composer' ) . '</p>';
    }

    public function mvdb_section_callback() {
        echo '<p>' . esc_html__( 'Configure vector database settings for content retrieval.', 'ai-page-composer' ) . '</p>';
    }

    public function generation_section_callback() {
        echo '<p>' . esc_html__( 'Set default parameters for AI content generation.', 'ai-page-composer' ) . '</p>';
    }

    public function content_policy_section_callback() {
        echo '<p>' . esc_html__( 'Define content generation policies and restrictions.', 'ai-page-composer' ) . '</p>';
    }

    public function block_preferences_section_callback() {
        echo '<p>' . esc_html__( 'Configure block plugin preferences and detection.', 'ai-page-composer' ) . '</p>';
    }

    public function cost_management_section_callback() {
        echo '<p>' . esc_html__( 'Manage API usage costs and budget limits.', 'ai-page-composer' ) . '</p>';
    }

    public function api_key_field_callback( $args ) {
        $settings = $this->get_all_settings();
        $section = $args['section'] ?? 'api_settings';
        $value = $settings[ $section ][ $args['key'] ] ?? '';
        $required = $args['required'] ? 'required' : '';
        
        printf(
            '<input type="password" id="%s" name="%s[%s][%s]" value="%s" class="regular-text" %s />',
            esc_attr( $args['key'] ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $section ),
            esc_attr( $args['key'] ),
            esc_attr( $value ),
            esc_attr( $required )
        );
    }

    public function url_field_callback( $args ) {
        $settings = $this->get_all_settings();
        $section = $args['section'] ?? 'mvdb_settings';
        $value = $settings[ $section ][ $args['key'] ] ?? '';
        $required = $args['required'] ? 'required' : '';
        
        printf(
            '<input type="url" id="%s" name="%s[%s][%s]" value="%s" class="regular-text" %s />',
            esc_attr( $args['key'] ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $section ),
            esc_attr( $args['key'] ),
            esc_attr( $value ),
            esc_attr( $required )
        );
    }

    public function select_field_callback( $args ) {
        $settings = $this->get_all_settings();
        $section = $args['section'] ?? 'generation_defaults';
        $value = $settings[ $section ][ $args['key'] ] ?? '';
        
        printf( '<select id="%s" name="%s[%s][%s]">', esc_attr( $args['key'] ), esc_attr( self::OPTION_NAME ), esc_attr( $section ), esc_attr( $args['key'] ) );
        foreach ( $args['options'] as $option_value => $option_label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $option_value ),
                selected( $value, $option_value, false ),
                esc_html( $option_label )
            );
        }
        echo '</select>';
    }

    public function range_field_callback( $args ) {
        $settings = $this->get_all_settings();
        $value = $settings['generation_defaults'][ $args['key'] ] ?? $args['min'];
        
        printf(
            '<input type="range" id="%s" name="%s[generation_defaults][%s]" value="%s" min="%s" max="%s" step="%s" class="range-input" />',
            esc_attr( $args['key'] ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $args['key'] ),
            esc_attr( $value ),
            esc_attr( $args['min'] ),
            esc_attr( $args['max'] ),
            esc_attr( $args['step'] )
        );
        printf( '<span class="range-value">%s</span>', esc_html( $value ) );
    }

    public function number_field_callback( $args ) {
        $settings = $this->get_all_settings();
        $section = $args['section'] ?? 'generation_defaults';
        $value = $settings[ $section ][ $args['key'] ] ?? $args['min'];
        
        printf(
            '<input type="number" id="%s" name="%s[%s][%s]" value="%s" min="%s" max="%s" step="%s" class="small-text" />',
            esc_attr( $args['key'] ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $section ),
            esc_attr( $args['key'] ),
            esc_attr( $value ),
            esc_attr( $args['min'] ?? '' ),
            esc_attr( $args['max'] ?? '' ),
            esc_attr( $args['step'] ?? '1' )
        );
    }

    public function checkbox_field_callback( $args ) {
        $settings = $this->get_all_settings();
        $section = $args['section'] ?? 'content_policies';
        $value = $settings[ $section ][ $args['key'] ] ?? false;
        
        printf(
            '<input type="checkbox" id="%s" name="%s[%s][%s]" value="1" %s />',
            esc_attr( $args['key'] ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $section ),
            esc_attr( $args['key'] ),
            checked( $value, true, false )
        );
    }
}