<?php
/**
 * Security Helper Class - Centralized Security Management
 * 
 * This file provides centralized security utilities for the AI Page Composer plugin.
 * It implements nonce management, capability verification, input validation, and 
 * request verification to ensure secure operations throughout the plugin.
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\Utils;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Security helper class for centralized security management
 */
class Security_Helper {

    /**
     * Nonce action for settings
     */
    const NONCE_ACTION = 'ai_composer_settings';

    /**
     * Nonce name for forms
     */
    const NONCE_NAME = 'ai_composer_nonce';

    /**
     * AJAX nonce action
     */
    const AJAX_NONCE_ACTION = 'ai_composer_ajax';

    /**
     * Required capability for admin access
     */
    const REQUIRED_CAPABILITY = 'manage_options';

    /**
     * Generate a nonce for settings operations
     *
     * @return string The generated nonce
     */
    public static function generate_nonce() {
        return wp_create_nonce( self::NONCE_ACTION );
    }

    /**
     * Generate a nonce for AJAX operations
     *
     * @return string The generated AJAX nonce
     */
    public static function generate_ajax_nonce() {
        return wp_create_nonce( self::AJAX_NONCE_ACTION );
    }

    /**
     * Verify a nonce value
     *
     * @param string $nonce_value The nonce value to verify.
     * @return bool True if nonce is valid, false otherwise
     */
    public static function verify_nonce( $nonce_value ) {
        return wp_verify_nonce( $nonce_value, self::NONCE_ACTION );
    }

    /**
     * Verify an AJAX nonce value
     *
     * @param string $nonce_value The AJAX nonce value to verify.
     * @return bool True if nonce is valid, false otherwise
     */
    public static function verify_ajax_nonce( $nonce_value ) {
        return wp_verify_nonce( $nonce_value, self::AJAX_NONCE_ACTION );
    }

    /**
     * Output nonce field for forms
     */
    public static function nonce_field() {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
    }

    /**
     * Check if current user has required capabilities
     *
     * @param string $capability Optional. Capability to check. Default is 'manage_options'.
     * @return bool True if user has capability, false otherwise
     */
    public static function current_user_can( $capability = null ) {
        $capability = $capability ?: self::REQUIRED_CAPABILITY;
        return current_user_can( $capability );
    }

    /**
     * Verify request with comprehensive security checks
     * 
     * This method performs all necessary security verifications:
     * - Capability check
     * - Nonce verification (for POST requests)
     * - Referer check
     *
     * @param bool $check_nonce Whether to check nonce (default: true for POST requests).
     * @return void Dies with error message if verification fails
     */
    public static function verify_request( $check_nonce = null ) {
        // Default nonce checking to true for POST requests
        if ( null === $check_nonce ) {
            $check_nonce = isset( $_POST ) && ! empty( $_POST );
        }

        // Check user capabilities first
        if ( ! self::current_user_can() ) {
            wp_die(
                esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-page-composer' ),
                esc_html__( 'Insufficient Permissions', 'ai-page-composer' ),
                array( 'response' => 403 )
            );
        }

        // Check nonce for POST requests
        if ( $check_nonce ) {
            if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
                wp_die(
                    esc_html__( 'Security check failed. Please try again.', 'ai-page-composer' ),
                    esc_html__( 'Security Error', 'ai-page-composer' ),
                    array( 'response' => 403 )
                );
            }

            if ( ! self::verify_nonce( $_POST[ self::NONCE_NAME ] ) ) {
                wp_die(
                    esc_html__( 'Security verification failed. Please try again.', 'ai-page-composer' ),
                    esc_html__( 'Security Error', 'ai-page-composer' ),
                    array( 'response' => 403 )
                );
            }
        }

        // Additional referer check for admin pages
        if ( is_admin() && $check_nonce ) {
            check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );
        }
    }

    /**
     * Verify AJAX request with security checks
     *
     * @return void Dies with JSON error if verification fails
     */
    public static function verify_ajax_request() {
        // Check user capabilities
        if ( ! self::current_user_can() ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Insufficient permissions for this action.', 'ai-page-composer' ),
                ),
                403
            );
        }

        // Check AJAX nonce
        check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce' );
    }

    /**
     * Sanitize and validate API key
     *
     * @param string $api_key The API key to sanitize.
     * @return string The sanitized API key
     */
    public static function sanitize_api_key( $api_key ) {
        // Remove whitespace
        $sanitized = trim( sanitize_text_field( $api_key ) );
        
        // Validate format (alphanumeric, hyphens, underscores, dots)
        if ( ! empty( $sanitized ) && ! preg_match( '/^[a-zA-Z0-9_.-]+$/', $sanitized ) ) {
            return '';
        }

        return $sanitized;
    }

    /**
     * Escape output for HTML context
     *
     * @param string $string The string to escape.
     * @return string The escaped string
     */
    public static function escape_html( $string ) {
        return esc_html( $string );
    }

    /**
     * Escape output for HTML attribute context
     *
     * @param string $string The string to escape.
     * @return string The escaped string
     */
    public static function escape_attr( $string ) {
        return esc_attr( $string );
    }

    /**
     * Escape output for URL context
     *
     * @param string $url The URL to escape.
     * @return string The escaped URL
     */
    public static function escape_url( $url ) {
        return esc_url( $url );
    }

    /**
     * Sanitize text field input
     *
     * @param string $input The input to sanitize.
     * @return string The sanitized input
     */
    public static function sanitize_text( $input ) {
        return sanitize_text_field( $input );
    }

    /**
     * Sanitize textarea input
     *
     * @param string $input The input to sanitize.
     * @return string The sanitized input
     */
    public static function sanitize_textarea( $input ) {
        return sanitize_textarea_field( $input );
    }

    /**
     * Sanitize email input
     *
     * @param string $email The email to sanitize.
     * @return string The sanitized email
     */
    public static function sanitize_email( $email ) {
        return sanitize_email( $email );
    }

    /**
     * Validate and sanitize URL
     *
     * @param string $url The URL to validate and sanitize.
     * @return string The sanitized URL or empty string if invalid
     */
    public static function sanitize_url( $url ) {
        $sanitized = esc_url_raw( $url );
        return filter_var( $sanitized, FILTER_VALIDATE_URL ) ? $sanitized : '';
    }

    /**
     * Generate a secure random token
     *
     * @param int $length Token length (default: 32).
     * @return string The generated token
     */
    public static function generate_token( $length = 32 ) {
        return wp_generate_password( $length, false );
    }

    /**
     * Hash sensitive data
     *
     * @param string $data The data to hash.
     * @return string The hashed data
     */
    public static function hash_data( $data ) {
        return wp_hash( $data );
    }

    /**
     * Verify if current request is from WordPress admin
     *
     * @return bool True if request is from admin, false otherwise
     */
    public static function is_admin_request() {
        return is_admin() && ! wp_doing_ajax();
    }

    /**
     * Verify if current request is an AJAX request
     *
     * @return bool True if AJAX request, false otherwise
     */
    public static function is_ajax_request() {
        return wp_doing_ajax();
    }

    /**
     * Get current user ID with security check
     *
     * @return int Current user ID or 0 if not logged in
     */
    public static function get_current_user_id() {
        return is_user_logged_in() ? get_current_user_id() : 0;
    }

    /**
     * Log security events (if WP_DEBUG is enabled)
     *
     * @param string $message The message to log.
     * @param string $level   Log level (info, warning, error).
     */
    public static function log_security_event( $message, $level = 'info' ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 
                sprintf( 
                    '[AI Page Composer Security] [%s] %s',
                    strtoupper( $level ),
                    $message
                )
            );
        }
    }
}