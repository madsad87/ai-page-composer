<?php
/**
 * MVDB Security Validator Class - Enhanced Security Layer
 * 
 * This file contains the MVDB_Security_Validator class that provides
 * comprehensive security validation for MVDB operations including
 * input sanitization, rate limiting, permission checking, and threat detection.
 *
 * @package AIPageComposer\API
 */

namespace AIPageComposer\API;

use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MVDB Security Validator class for enhanced security operations
 */
class MVDB_Security_Validator {

    /**
     * Rate limiting transient prefix
     *
     * @var string
     */
    private $rate_limit_prefix = 'mvdb_rate_limit_';

    /**
     * Security log group
     *
     * @var string
     */
    private $security_log_group = 'ai_composer_mvdb_security';

    /**
     * Maximum queries per hour per user
     *
     * @var int
     */
    private $max_queries_per_hour = 100;

    /**
     * Maximum queries per minute per IP
     *
     * @var int
     */
    private $max_queries_per_minute = 10;

    /**
     * Validate request security
     *
     * @param array $params Request parameters.
     * @param array $context Request context (user, IP, etc).
     * @return array Validation result.
     * @throws \Exception If security validation fails.
     */
    public function validate_request_security( $params, $context = [] ) {
        $validation_result = [
            'valid' => true,
            'warnings' => [],
            'security_flags' => []
        ];

        // 1. Permission validation
        $this->validate_user_permissions( $context );

        // 2. Rate limiting
        $this->enforce_rate_limits( $context );

        // 3. Input sanitization and validation
        $sanitized_params = $this->deep_sanitize_params( $params );

        // 4. Content security checks
        $content_security = $this->validate_content_security( $sanitized_params );
        $validation_result['warnings'] = array_merge( 
            $validation_result['warnings'], 
            $content_security['warnings'] 
        );

        // 5. Query pattern analysis
        $pattern_analysis = $this->analyze_query_patterns( $sanitized_params, $context );
        if ( ! empty( $pattern_analysis['suspicious'] ) ) {
            $validation_result['security_flags'][] = $pattern_analysis;
        }

        // 6. Geographic and temporal validation
        $geo_temporal = $this->validate_geo_temporal_context( $context );
        if ( ! $geo_temporal['valid'] ) {
            $validation_result['warnings'][] = $geo_temporal['warning'];
        }

        // Log security event
        $this->log_security_event( 'request_validated', $context, $validation_result );

        return [
            'params' => $sanitized_params,
            'validation' => $validation_result
        ];
    }

    /**
     * Validate user permissions for MVDB access
     *
     * @param array $context Request context.
     * @throws \Exception If permission check fails.
     */
    private function validate_user_permissions( $context ) {
        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            $this->log_security_event( 'unauthorized_access_attempt', $context );
            throw new \Exception( __( 'Authentication required for MVDB access', 'ai-page-composer' ) );
        }

        // Check required capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            $this->log_security_event( 'insufficient_permissions', $context );
            throw new \Exception( __( 'Insufficient permissions for MVDB access', 'ai-page-composer' ) );
        }

        // Check for suspended or restricted users
        $user_id = get_current_user_id();
        if ( $this->is_user_restricted( $user_id ) ) {
            $this->log_security_event( 'restricted_user_access', $context );
            throw new \Exception( __( 'User access to MVDB is restricted', 'ai-page-composer' ) );
        }

        // Additional role-based restrictions
        $user = wp_get_current_user();
        if ( in_array( 'subscriber', $user->roles ) && ! $this->allow_subscriber_access() ) {
            throw new \Exception( __( 'Subscriber role not permitted for MVDB access', 'ai-page-composer' ) );
        }
    }

    /**
     * Enforce rate limiting per user and IP
     *
     * @param array $context Request context.
     * @throws \Exception If rate limit exceeded.
     */
    private function enforce_rate_limits( $context ) {
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();

        // Check user-based rate limiting
        $user_limit_key = $this->rate_limit_prefix . 'user_' . $user_id . '_hour';
        $user_requests = get_transient( $user_limit_key ) ?: 0;

        if ( $user_requests >= $this->max_queries_per_hour ) {
            $this->log_security_event( 'user_rate_limit_exceeded', $context );
            throw new \Exception( 
                sprintf( 
                    __( 'Rate limit exceeded: %d queries per hour maximum', 'ai-page-composer' ), 
                    $this->max_queries_per_hour 
                ) 
            );
        }

        // Check IP-based rate limiting
        $ip_limit_key = $this->rate_limit_prefix . 'ip_' . md5( $ip_address ) . '_minute';
        $ip_requests = get_transient( $ip_limit_key ) ?: 0;

        if ( $ip_requests >= $this->max_queries_per_minute ) {
            $this->log_security_event( 'ip_rate_limit_exceeded', $context );
            throw new \Exception( 
                sprintf( 
                    __( 'Rate limit exceeded: %d queries per minute maximum', 'ai-page-composer' ), 
                    $this->max_queries_per_minute 
                ) 
            );
        }

        // Update counters
        set_transient( $user_limit_key, $user_requests + 1, HOUR_IN_SECONDS );
        set_transient( $ip_limit_key, $ip_requests + 1, MINUTE_IN_SECONDS );
    }

    /**
     * Deep sanitization of all parameters
     *
     * @param array $params Input parameters.
     * @return array Sanitized parameters.
     */
    private function deep_sanitize_params( $params ) {
        $sanitized = [];

        // Sanitize section ID
        if ( isset( $params['sectionId'] ) ) {
            $sanitized['sectionId'] = sanitize_key( $params['sectionId'] );
            
            // Additional validation for section ID format
            if ( ! preg_match( '/^section-[a-zA-Z0-9_-]+$/', $sanitized['sectionId'] ) ) {
                throw new \Exception( __( 'Invalid section ID format detected', 'ai-page-composer' ) );
            }
        }

        // Sanitize and validate query
        if ( isset( $params['query'] ) ) {
            $sanitized['query'] = $this->sanitize_search_query( $params['query'] );
        }

        // Sanitize namespaces
        if ( isset( $params['namespaces'] ) ) {
            $sanitized['namespaces'] = $this->sanitize_namespaces( $params['namespaces'] );
        }

        // Sanitize numeric parameters
        if ( isset( $params['k'] ) ) {
            $sanitized['k'] = $this->sanitize_k_value( $params['k'] );
        }

        if ( isset( $params['min_score'] ) ) {
            $sanitized['min_score'] = $this->sanitize_score_value( $params['min_score'] );
        }

        // Deep sanitize filters
        if ( isset( $params['filters'] ) ) {
            $sanitized['filters'] = $this->deep_sanitize_filters( $params['filters'] );
        }

        return $sanitized;
    }

    /**
     * Sanitize search query with security checks
     *
     * @param string $query Input query.
     * @return string Sanitized query.
     * @throws \Exception If query contains threats.
     */
    private function sanitize_search_query( $query ) {
        // Basic sanitization
        $sanitized = sanitize_textarea_field( $query );
        
        // Length validation
        if ( strlen( $sanitized ) < 3 || strlen( $sanitized ) > 1000 ) {
            throw new \Exception( __( 'Query length must be between 3 and 1000 characters', 'ai-page-composer' ) );
        }

        // Check for malicious patterns
        $malicious_patterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', // Script tags
            '/javascript:/i', // JavaScript URLs
            '/data:(?!image\/)/i', // Data URLs (except images)
            '/vbscript:/i', // VBScript URLs
            '/onload\s*=/i', // Event handlers
            '/onerror\s*=/i',
            '/onclick\s*=/i',
            '/<iframe/i', // iframes
            '/<object/i', // objects
            '/<embed/i', // embeds
        ];

        foreach ( $malicious_patterns as $pattern ) {
            if ( preg_match( $pattern, $sanitized ) ) {
                throw new \Exception( __( 'Query contains potentially malicious content', 'ai-page-composer' ) );
            }
        }

        // Check for SQL injection patterns
        $sql_patterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bSELECT\b.*\bFROM\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
        ];

        foreach ( $sql_patterns as $pattern ) {
            if ( preg_match( $pattern, $sanitized ) ) {
                throw new \Exception( __( 'Query contains SQL injection patterns', 'ai-page-composer' ) );
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize namespaces with whitelist validation
     *
     * @param mixed $namespaces Input namespaces.
     * @return array Sanitized namespaces.
     */
    private function sanitize_namespaces( $namespaces ) {
        $allowed_namespaces = [ 'content', 'products', 'docs', 'knowledge' ];
        
        if ( ! is_array( $namespaces ) ) {
            $namespaces = [ $namespaces ];
        }

        $sanitized = [];
        foreach ( $namespaces as $namespace ) {
            $clean = sanitize_key( $namespace );
            if ( in_array( $clean, $allowed_namespaces, true ) ) {
                $sanitized[] = $clean;
            }
        }

        return ! empty( $sanitized ) ? $sanitized : [ 'content' ];
    }

    /**
     * Sanitize K value with bounds checking
     *
     * @param mixed $k Input K value.
     * @return int Sanitized K value.
     */
    private function sanitize_k_value( $k ) {
        $k = absint( $k );
        return max( 1, min( 50, $k ) );
    }

    /**
     * Sanitize score value with bounds checking
     *
     * @param mixed $score Input score value.
     * @return float Sanitized score value.
     */
    private function sanitize_score_value( $score ) {
        $score = floatval( $score );
        return max( 0.0, min( 1.0, $score ) );
    }

    /**
     * Deep sanitize filters array
     *
     * @param mixed $filters Input filters.
     * @return array Sanitized filters.
     */
    private function deep_sanitize_filters( $filters ) {
        if ( ! is_array( $filters ) ) {
            return [];
        }

        $sanitized = [];

        // Sanitize post_type filter
        if ( isset( $filters['post_type'] ) ) {
            $post_types = is_array( $filters['post_type'] ) ? $filters['post_type'] : [ $filters['post_type'] ];
            $clean_types = [];
            
            foreach ( $post_types as $type ) {
                $clean_type = sanitize_key( $type );
                if ( post_type_exists( $clean_type ) ) {
                    $clean_types[] = $clean_type;
                }
            }
            
            if ( ! empty( $clean_types ) ) {
                $sanitized['post_type'] = $clean_types;
            }
        }

        // Sanitize date_range filter
        if ( isset( $filters['date_range'] ) && is_array( $filters['date_range'] ) ) {
            $date_range = [];
            
            if ( ! empty( $filters['date_range']['start'] ) ) {
                $start = sanitize_text_field( $filters['date_range']['start'] );
                if ( $this->validate_date_format( $start ) ) {
                    $date_range['start'] = $start;
                }
            }
            
            if ( ! empty( $filters['date_range']['end'] ) ) {
                $end = sanitize_text_field( $filters['date_range']['end'] );
                if ( $this->validate_date_format( $end ) ) {
                    $date_range['end'] = $end;
                }
            }
            
            if ( ! empty( $date_range ) ) {
                $sanitized['date_range'] = $date_range;
            }
        }

        // Sanitize other filters with validation
        if ( isset( $filters['language'] ) ) {
            $language = sanitize_key( $filters['language'] );
            if ( preg_match( '/^[a-z]{2}$/', $language ) ) {
                $sanitized['language'] = $language;
            }
        }

        if ( isset( $filters['license'] ) ) {
            $allowed_licenses = [ 'CC-BY', 'CC-BY-SA', 'CC-BY-NC', 'public-domain', 'fair-use', 'commercial' ];
            $licenses = is_array( $filters['license'] ) ? $filters['license'] : [ $filters['license'] ];
            $clean_licenses = [];
            
            foreach ( $licenses as $license ) {
                $clean_license = sanitize_key( $license );
                if ( in_array( $clean_license, $allowed_licenses, true ) ) {
                    $clean_licenses[] = $clean_license;
                }
            }
            
            if ( ! empty( $clean_licenses ) ) {
                $sanitized['license'] = $clean_licenses;
            }
        }

        return $sanitized;
    }

    /**
     * Validate content security policies
     *
     * @param array $params Sanitized parameters.
     * @return array Validation result.
     */
    private function validate_content_security( $params ) {
        $warnings = [];

        // Check for overly broad queries
        if ( isset( $params['query'] ) && strlen( $params['query'] ) < 10 ) {
            $warnings[] = [
                'type' => 'broad_query',
                'message' => 'Query may be too broad for secure content retrieval'
            ];
        }

        // Check for excessive result requests
        if ( isset( $params['k'] ) && $params['k'] > 25 ) {
            $warnings[] = [
                'type' => 'large_result_set',
                'message' => 'Large result set requested - consider pagination'
            ];
        }

        // Check for low minimum score (potential data leakage)
        if ( isset( $params['min_score'] ) && $params['min_score'] < 0.3 ) {
            $warnings[] = [
                'type' => 'low_quality_threshold',
                'message' => 'Low minimum score may return irrelevant content'
            ];
        }

        return [
            'valid' => true,
            'warnings' => $warnings
        ];
    }

    /**
     * Analyze query patterns for suspicious behavior
     *
     * @param array $params Request parameters.
     * @param array $context Request context.
     * @return array Analysis result.
     */
    private function analyze_query_patterns( $params, $context ) {
        $suspicious_indicators = [];

        // Check for rapid-fire queries
        $user_id = get_current_user_id();
        $recent_queries_key = 'mvdb_recent_queries_' . $user_id;
        $recent_queries = get_transient( $recent_queries_key ) ?: [];

        if ( count( $recent_queries ) > 5 ) {
            $time_diffs = [];
            for ( $i = 1; $i < count( $recent_queries ); $i++ ) {
                $time_diffs[] = $recent_queries[$i]['timestamp'] - $recent_queries[$i-1]['timestamp'];
            }
            
            $avg_interval = array_sum( $time_diffs ) / count( $time_diffs );
            if ( $avg_interval < 5 ) { // Less than 5 seconds between queries
                $suspicious_indicators[] = 'rapid_fire_queries';
            }
        }

        // Add current query to history
        $recent_queries[] = [
            'timestamp' => time(),
            'query_hash' => hash( 'md5', $params['query'] ?? '' ),
            'ip' => $this->get_client_ip()
        ];

        // Keep only last 10 queries
        $recent_queries = array_slice( $recent_queries, -10 );
        set_transient( $recent_queries_key, $recent_queries, 300 ); // 5 minutes

        // Check for identical repeated queries
        $query_hashes = array_column( $recent_queries, 'query_hash' );
        $hash_counts = array_count_values( $query_hashes );
        $max_count = max( $hash_counts );
        
        if ( $max_count > 3 ) {
            $suspicious_indicators[] = 'repeated_identical_queries';
        }

        return [
            'suspicious' => ! empty( $suspicious_indicators ),
            'indicators' => $suspicious_indicators,
            'risk_level' => $this->calculate_risk_level( $suspicious_indicators )
        ];
    }

    /**
     * Validate geographic and temporal context
     *
     * @param array $context Request context.
     * @return array Validation result.
     */
    private function validate_geo_temporal_context( $context ) {
        $warnings = [];

        // Check for unusual access times
        $current_hour = (int) current_time( 'H' );
        if ( $current_hour < 6 || $current_hour > 23 ) {
            $warnings[] = 'unusual_access_time';
        }

        // Check for IP address changes (basic detection)
        $user_id = get_current_user_id();
        $last_ip_key = 'mvdb_last_ip_' . $user_id;
        $last_ip = get_transient( $last_ip_key );
        $current_ip = $this->get_client_ip();

        if ( $last_ip && $last_ip !== $current_ip ) {
            $warnings[] = 'ip_address_change';
        }

        set_transient( $last_ip_key, $current_ip, DAY_IN_SECONDS );

        return [
            'valid' => empty( $warnings ),
            'warnings' => $warnings
        ];
    }

    /**
     * Check if user is restricted
     *
     * @param int $user_id User ID.
     * @return bool True if user is restricted.
     */
    private function is_user_restricted( $user_id ) {
        $restricted_users = get_option( 'mvdb_restricted_users', [] );
        return in_array( $user_id, $restricted_users, true );
    }

    /**
     * Check if subscriber access is allowed
     *
     * @return bool True if subscribers are allowed.
     */
    private function allow_subscriber_access() {
        return apply_filters( 'mvdb_allow_subscriber_access', false );
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address.
     */
    private function get_client_ip() {
        $ip_keys = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' ];
        
        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        
        return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    }

    /**
     * Validate date format
     *
     * @param string $date Date string.
     * @return bool True if valid.
     */
    private function validate_date_format( $date ) {
        $d = \DateTime::createFromFormat( 'Y-m-d', $date );
        return $d && $d->format( 'Y-m-d' ) === $date;
    }

    /**
     * Calculate risk level based on indicators
     *
     * @param array $indicators Suspicious indicators.
     * @return string Risk level.
     */
    private function calculate_risk_level( $indicators ) {
        $risk_weights = [
            'rapid_fire_queries' => 3,
            'repeated_identical_queries' => 2,
            'unusual_access_time' => 1,
            'ip_address_change' => 1
        ];

        $total_risk = 0;
        foreach ( $indicators as $indicator ) {
            $total_risk += $risk_weights[ $indicator ] ?? 1;
        }

        if ( $total_risk >= 5 ) {
            return 'high';
        } elseif ( $total_risk >= 3 ) {
            return 'medium';
        } elseif ( $total_risk > 0 ) {
            return 'low';
        }

        return 'minimal';
    }

    /**
     * Log security events
     *
     * @param string $event_type Event type.
     * @param array  $context    Request context.
     * @param array  $details    Additional details.
     */
    private function log_security_event( $event_type, $context, $details = [] ) {
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            $log_entry = [
                'timestamp' => current_time( 'mysql' ),
                'event_type' => $event_type,
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
                'details' => $details
            ];

            error_log(
                sprintf(
                    '[AI Page Composer MVDB Security] %s',
                    wp_json_encode( $log_entry )
                )
            );
        }
    }
}