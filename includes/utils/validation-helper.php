<?php
/**
 * Validation Helper Class - Input Validation and Sanitization
 * 
 * This file provides comprehensive input validation and sanitization utilities
 * for the AI Page Composer plugin. It includes specialized validators for
 * API keys, numeric ranges, arrays, and plugin-specific data types.
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\Utils;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Validation helper class for input validation and sanitization
 */
class Validation_Helper {

    /**
     * Validate and sanitize API key
     *
     * @param string $value The API key value to validate.
     * @return string The sanitized API key or empty string if invalid
     */
    public static function sanitize_api_key( $value ) {
        // Remove whitespace and sanitize
        $sanitized = trim( sanitize_text_field( $value ) );

        // Basic API key format validation (alphanumeric, hyphens, underscores, dots)
        if ( ! empty( $sanitized ) && ! preg_match( '/^[a-zA-Z0-9_.-]+$/', $sanitized ) ) {
            add_settings_error(
                'ai_composer_settings',
                'invalid_api_key',
                __( 'API key contains invalid characters. Only letters, numbers, hyphens, underscores, and dots are allowed.', 'ai-page-composer' )
            );
            return '';
        }

        return $sanitized;
    }

    /**
     * Validate alpha weight range (0.0 to 1.0)
     *
     * @param mixed $value The alpha value to validate.
     * @return float The validated alpha value
     */
    public static function validate_alpha_range( $value ) {
        $alpha = floatval( $value );

        if ( $alpha < 0.0 || $alpha > 1.0 ) {
            add_settings_error(
                'ai_composer_settings',
                'invalid_alpha',
                __( 'Alpha value must be between 0.0 and 1.0.', 'ai-page-composer' )
            );
            return 0.7; // Default fallback
        }

        return $alpha;
    }

    /**
     * Validate K value range (1 to 50)
     *
     * @param mixed $value The K value to validate.
     * @return int The validated K value
     */
    public static function validate_k_range( $value ) {
        $k = absint( $value );

        if ( $k < 1 || $k > 50 ) {
            add_settings_error(
                'ai_composer_settings',
                'invalid_k_value',
                __( 'K value must be between 1 and 50.', 'ai-page-composer' )
            );
            return 10; // Default fallback
        }

        return $k;
    }

    /**
     * Validate min score range (0.0 to 1.0)
     *
     * @param mixed $value The score value to validate.
     * @return float The validated score value
     */
    public static function validate_score_range( $value ) {
        $score = floatval( $value );

        if ( $score < 0.0 || $score > 1.0 ) {
            add_settings_error(
                'ai_composer_settings',
                'invalid_min_score',
                __( 'Min score value must be between 0.0 and 1.0.', 'ai-page-composer' )
            );
            return 0.5; // Default fallback
        }

        return $score;
    }

    /**
     * Validate generation mode
     *
     * @param string $value The generation mode to validate.
     * @return string The validated generation mode
     */
    public static function validate_generation_mode( $value ) {
        $allowed_modes = array( 'grounded', 'hybrid', 'generative' );
        $mode = sanitize_key( $value );

        if ( ! in_array( $mode, $allowed_modes, true ) ) {
            add_settings_error(
                'ai_composer_settings',
                'invalid_generation_mode',
                __( 'Invalid generation mode. Must be grounded, hybrid, or generative.', 'ai-page-composer' )
            );
            return 'hybrid'; // Default fallback
        }

        return $mode;
    }

    /**
     * Sanitize and validate namespace array
     *
     * @param mixed $value The namespaces value to validate.
     * @return array The validated namespaces array
     */
    public static function sanitize_namespace_array( $value ) {
        if ( ! is_array( $value ) ) {
            return array( 'content' ); // Default fallback
        }

        $allowed_namespaces = array( 'content', 'products', 'docs', 'knowledge' );
        $sanitized = array();

        foreach ( $value as $namespace ) {
            $clean_namespace = sanitize_key( $namespace );
            if ( in_array( $clean_namespace, $allowed_namespaces, true ) ) {
                $sanitized[] = $clean_namespace;
            }
        }

        return empty( $sanitized ) ? array( 'content' ) : $sanitized;
    }

    /**
     * Validate budget amount
     *
     * @param mixed $value The budget amount to validate.
     * @return float The validated budget amount
     */
    public static function validate_budget_amount( $value ) {
        $amount = floatval( $value );

        if ( $amount < 0.01 || $amount > 1000.0 ) {
            add_settings_error(
                'ai_composer_settings',
                'invalid_budget',
                __( 'Budget amount must be between $0.01 and $1000.00.', 'ai-page-composer' )
            );
            return 10.0; // Default fallback
        }

        return $amount;
    }

    /**
     * Validate per-run limit amount
     *
     * @param mixed $value The per-run limit to validate.
     * @return float The validated per-run limit
     */
    public static function validate_per_run_limit( $value ) {
        $amount = floatval( $value );

        if ( $amount < 0.01 || $amount > 100.0 ) {
            add_settings_error(
                'ai_composer_settings',
                'invalid_per_run_limit',
                __( 'Per-run limit must be between $0.01 and $100.00.', 'ai-page-composer' )
            );
            return 2.0; // Default fallback
        }

        return $amount;
    }

    /**
     * Validate token limit
     *
     * @param mixed $value The token limit to validate.
     * @return int The validated token limit
     */
    public static function validate_token_limit( $value ) {
        $limit = absint( $value );

        if ( $limit < 100 || $limit > 5000 ) {
            add_settings_error(
                'ai_composer_settings',
                'invalid_token_limit',
                __( 'Token limit must be between 100 and 5000.', 'ai-page-composer' )
            );
            return 1000; // Default fallback
        }

        return $limit;
    }

    /**
     * Sanitize plugin priorities
     *
     * @param mixed $value The plugin priorities to sanitize.
     * @return array The sanitized plugin priorities
     */
    public static function sanitize_plugin_priorities( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $value as $plugin => $priority ) {
            $clean_plugin = sanitize_key( $plugin );
            $clean_priority = absint( $priority );

            // Ensure priority is within valid range (1-10)
            $clean_priority = max( 1, min( 10, $clean_priority ) );
            $sanitized[ $clean_plugin ] = $clean_priority;
        }

        return $sanitized;
    }

    /**
     * Sanitize section mappings
     *
     * @param mixed $value The section mappings to sanitize.
     * @return array The sanitized section mappings
     */
    public static function sanitize_section_mappings( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $allowed_sections = array( 'hero', 'content', 'testimonial', 'pricing', 'team', 'faq', 'cta' );
        $sanitized = array();

        foreach ( $value as $section => $mapping ) {
            $clean_section = sanitize_key( $section );
            $clean_mapping = sanitize_key( $mapping );

            if ( in_array( $clean_section, $allowed_sections, true ) ) {
                // Allow 'auto', 'core', or plugin names
                if ( 'auto' === $clean_mapping || 'core' === $clean_mapping || ! empty( $clean_mapping ) ) {
                    $sanitized[ $clean_section ] = $clean_mapping;
                }
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize license filters
     *
     * @param mixed $value The license filters to sanitize.
     * @return array The sanitized license filters
     */
    public static function sanitize_license_filters( $value ) {
        if ( ! is_array( $value ) ) {
            return array( 'CC-BY', 'CC-BY-SA', 'public-domain' ); // Default fallback
        }

        $allowed_licenses = array(
            'CC-BY',
            'CC-BY-SA',
            'CC-BY-NC',
            'public-domain',
            'fair-use',
            'commercial',
        );

        $sanitized = array();
        foreach ( $value as $license ) {
            $clean_license = sanitize_key( $license );
            if ( in_array( $clean_license, $allowed_licenses, true ) ) {
                $sanitized[] = $clean_license;
            }
        }

        return empty( $sanitized ) ? array( 'CC-BY', 'CC-BY-SA', 'public-domain' ) : $sanitized;
    }

    /**
     * Sanitize custom block types
     *
     * @param mixed $value The custom block types to sanitize.
     * @return array The sanitized custom block types
     */
    public static function sanitize_custom_block_types( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $value as $block_type ) {
            if ( is_array( $block_type ) && isset( $block_type['name'], $block_type['namespace'] ) ) {
                $clean_name = sanitize_key( $block_type['name'] );
                $clean_namespace = sanitize_key( $block_type['namespace'] );

                if ( ! empty( $clean_name ) && ! empty( $clean_namespace ) ) {
                    $sanitized[] = array(
                        'name'      => $clean_name,
                        'namespace' => $clean_namespace,
                    );
                }
            }
        }

        return $sanitized;
    }

    /**
     * Validate image generation policy
     *
     * @param string $value The image generation policy to validate.
     * @return string The validated image generation policy
     */
    public static function validate_image_policy( $value ) {
        $allowed_policies = array( 'always', 'auto', 'manual', 'never' );
        $policy = sanitize_key( $value );

        if ( ! in_array( $policy, $allowed_policies, true ) ) {
            add_settings_error(
                'ai_composer_settings',
                'invalid_image_policy',
                __( 'Invalid image generation policy.', 'ai-page-composer' )
            );
            return 'auto'; // Default fallback
        }

        return $policy;
    }

    /**
     * Validate budget reset schedule
     *
     * @param string $value The budget reset schedule to validate.
     * @return string The validated budget reset schedule
     */
    public static function validate_budget_schedule( $value ) {
        $allowed_schedules = array( 'daily', 'weekly', 'monthly' );
        $schedule = sanitize_key( $value );

        if ( ! in_array( $schedule, $allowed_schedules, true ) ) {
            add_settings_error(
                'ai_composer_settings',
                'invalid_budget_schedule',
                __( 'Invalid budget reset schedule.', 'ai-page-composer' )
            );
            return 'daily'; // Default fallback
        }

        return $schedule;
    }

    /**
     * Sanitize and validate URL
     *
     * @param string $url The URL to validate.
     * @return string The sanitized URL or empty string if invalid
     */
    public static function validate_url( $url ) {
        $sanitized = esc_url_raw( $url );
        return filter_var( $sanitized, FILTER_VALIDATE_URL ) ? $sanitized : '';
    }

    /**
     * Validate email address
     *
     * @param string $email The email to validate.
     * @return string The sanitized email or empty string if invalid
     */
    public static function validate_email( $email ) {
        $sanitized = sanitize_email( $email );
        return is_email( $sanitized ) ? $sanitized : '';
    }

    /**
     * Validate integer within range
     *
     * @param mixed $value The value to validate.
     * @param int   $min   Minimum allowed value.
     * @param int   $max   Maximum allowed value.
     * @param int   $default Default value to return if invalid.
     * @return int The validated integer
     */
    public static function validate_int_range( $value, $min, $max, $default ) {
        $int_value = absint( $value );

        if ( $int_value < $min || $int_value > $max ) {
            return $default;
        }

        return $int_value;
    }

    /**
     * Validate float within range
     *
     * @param mixed $value The value to validate.
     * @param float $min   Minimum allowed value.
     * @param float $max   Maximum allowed value.
     * @param float $default Default value to return if invalid.
     * @return float The validated float
     */
    public static function validate_float_range( $value, $min, $max, $default ) {
        $float_value = floatval( $value );

        if ( $float_value < $min || $float_value > $max ) {
            return $default;
        }

        return $float_value;
    }

    /**
     * Validate checkbox/boolean input
     *
     * @param mixed $value The value to validate.
     * @return bool The validated boolean value
     */
    public static function validate_checkbox( $value ) {
        return (bool) $value;
    }

    /**
     * Sanitize HTML content
     *
     * @param string $content The HTML content to sanitize.
     * @return string The sanitized HTML content
     */
    public static function sanitize_html_content( $content ) {
        return wp_kses_post( $content );
    }

    /**
     * Validate and sanitize color value
     *
     * @param string $color The color value to validate.
     * @return string The validated color value
     */
    public static function validate_color( $color ) {
        // Remove # if present and validate hex color
        $color = ltrim( $color, '#' );
        
        if ( ! preg_match( '/^[a-fA-F0-9]{6}$/', $color ) ) {
            return '#ffffff'; // Default to white
        }

        return '#' . $color;
    }

    /**
     * Validate JSON string
     *
     * @param string $json The JSON string to validate.
     * @return array|false The decoded array or false if invalid
     */
    public static function validate_json( $json ) {
        if ( empty( $json ) ) {
            return false;
        }

        $decoded = json_decode( $json, true );
        
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return false;
        }

        return $decoded;
    }

    /**
     * Get validation errors from WordPress settings API
     *
     * @return array Array of validation errors
     */
    public static function get_validation_errors() {
        return get_settings_errors( 'ai_composer_settings' );
    }

    /**
     * Clear validation errors
     */
    public static function clear_validation_errors() {
        settings_errors( 'ai_composer_settings' );
    }
}