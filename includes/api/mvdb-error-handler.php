<?php
/**
 * MVDB Error Handler Class - Comprehensive Error Management
 * 
 * This file contains the MVDB_Error_Handler class that provides comprehensive
 * error handling, logging, and recovery mechanisms for MVDB operations.
 * It includes structured error classification, detailed logging, and
 * intelligent error recovery strategies.
 *
 * @package AIPageComposer\API
 */

namespace AIPageComposer\API;

use AIPageComposer\Admin\Settings_Manager;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MVDB Error Handler class for comprehensive error management
 */
class MVDB_Error_Handler {

    /**
     * Error log group for WordPress cache
     *
     * @var string
     */
    private $error_log_group = 'ai_composer_mvdb_errors';

    /**
     * Settings manager instance
     *
     * @var Settings_Manager
     */
    private $settings_manager;

    /**
     * Error severity levels
     *
     * @var array
     */
    private $severity_levels = [
        'CRITICAL' => 1,
        'ERROR' => 2,
        'WARNING' => 3,
        'INFO' => 4,
        'DEBUG' => 5
    ];

    /**
     * Error categories for classification
     *
     * @var array
     */
    private $error_categories = [
        'AUTHENTICATION',
        'NETWORK',
        'VALIDATION',
        'API_RESPONSE',
        'CACHE',
        'CONFIGURATION',
        'RATE_LIMIT',
        'TIMEOUT',
        'UNKNOWN'
    ];

    /**
     * Maximum error log entries to keep
     *
     * @var int
     */
    private $max_error_entries = 1000;

    /**
     * Constructor
     *
     * @param Settings_Manager $settings_manager Settings manager instance.
     */
    public function __construct( $settings_manager = null ) {
        $this->settings_manager = $settings_manager ?: new Settings_Manager();
        $this->init_error_handling();
    }

    /**
     * Initialize error handling system
     */
    private function init_error_handling() {
        // Set custom error handler for MVDB operations
        add_action( 'wp_ajax_mvdb_get_error_logs', [ $this, 'ajax_get_error_logs' ] );
        add_action( 'wp_ajax_mvdb_clear_error_logs', [ $this, 'ajax_clear_error_logs' ] );
        
        // Schedule error log cleanup
        if ( ! wp_next_scheduled( 'mvdb_cleanup_error_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'mvdb_cleanup_error_logs' );
        }
        add_action( 'mvdb_cleanup_error_logs', [ $this, 'cleanup_old_errors' ] );
    }

    /**
     * Handle and log error with comprehensive context
     *
     * @param string    $message Error message.
     * @param string    $severity Error severity level.
     * @param string    $category Error category.
     * @param \Exception|null $exception Original exception if available.
     * @param array     $context Additional context data.
     * @return string Unique error ID for tracking.
     */
    public function handle_error( $message, $severity = 'ERROR', $category = 'UNKNOWN', $exception = null, $context = [] ) {
        $error_id = $this->generate_error_id();
        
        $error_data = [
            'id' => $error_id,
            'message' => $message,
            'severity' => $severity,
            'category' => $category,
            'timestamp' => current_time( 'mysql' ),
            'context' => $this->sanitize_context( $context ),
            'stack_trace' => $exception ? $this->format_stack_trace( $exception ) : null,
            'user_id' => get_current_user_id(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->get_client_ip(),
            'wp_version' => get_bloginfo( 'version' ),
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage( true ),
            'peak_memory' => memory_get_peak_usage( true )
        ];

        // Store error in WordPress cache for quick access
        $this->store_error_log( $error_data );

        // Log to WordPress debug log if enabled
        $this->log_to_wp_debug( $error_data );

        // Send critical errors to external monitoring if configured
        if ( $severity === 'CRITICAL' ) {
            $this->send_critical_alert( $error_data );
        }

        // Trigger recovery mechanisms
        $this->attempt_error_recovery( $error_data );

        return $error_id;
    }

    /**
     * Handle API-specific errors with enhanced context
     *
     * @param \Exception $exception API exception.
     * @param array      $request_data Original request data.
     * @param array      $response_data Response data if available.
     * @return string Error ID.
     */
    public function handle_api_error( $exception, $request_data = [], $response_data = [] ) {
        $category = $this->classify_api_error( $exception );
        $severity = $this->determine_error_severity( $exception, $category );

        $context = [
            'api_request' => $this->sanitize_api_request( $request_data ),
            'api_response' => $this->sanitize_api_response( $response_data ),
            'error_code' => $exception->getCode(),
            'error_file' => $exception->getFile(),
            'error_line' => $exception->getLine()
        ];

        return $this->handle_error(
            $exception->getMessage(),
            $severity,
            $category,
            $exception,
            $context
        );
    }

    /**
     * Classify API error based on exception details
     *
     * @param \Exception $exception API exception.
     * @return string Error category.
     */
    private function classify_api_error( $exception ) {
        $message = strtolower( $exception->getMessage() );
        $code = $exception->getCode();

        // HTTP status code classification
        if ( $code === 401 || $code === 403 ) {
            return 'AUTHENTICATION';
        } elseif ( $code === 429 ) {
            return 'RATE_LIMIT';
        } elseif ( $code >= 500 ) {
            return 'API_RESPONSE';
        } elseif ( $code >= 400 ) {
            return 'VALIDATION';
        }

        // Message-based classification
        if ( strpos( $message, 'timeout' ) !== false || strpos( $message, 'timed out' ) !== false ) {
            return 'TIMEOUT';
        } elseif ( strpos( $message, 'network' ) !== false || strpos( $message, 'connection' ) !== false ) {
            return 'NETWORK';
        } elseif ( strpos( $message, 'authentication' ) !== false || strpos( $message, 'token' ) !== false ) {
            return 'AUTHENTICATION';
        } elseif ( strpos( $message, 'validation' ) !== false || strpos( $message, 'invalid' ) !== false ) {
            return 'VALIDATION';
        } elseif ( strpos( $message, 'configuration' ) !== false || strpos( $message, 'config' ) !== false ) {
            return 'CONFIGURATION';
        }

        return 'UNKNOWN';
    }

    /**
     * Determine error severity based on exception and category
     *
     * @param \Exception $exception API exception.
     * @param string     $category Error category.
     * @return string Error severity.
     */
    private function determine_error_severity( $exception, $category ) {
        $code = $exception->getCode();

        // Critical errors that require immediate attention
        if ( $category === 'AUTHENTICATION' && $code === 401 ) {
            return 'CRITICAL'; // Invalid credentials
        } elseif ( $category === 'CONFIGURATION' ) {
            return 'CRITICAL'; // Misconfiguration
        } elseif ( $code >= 500 ) {
            return 'CRITICAL'; // Server errors
        }

        // High priority errors
        if ( $category === 'RATE_LIMIT' || $category === 'TIMEOUT' ) {
            return 'ERROR';
        } elseif ( $code >= 400 && $code < 500 ) {
            return 'ERROR'; // Client errors
        }

        // Medium priority
        if ( $category === 'NETWORK' || $category === 'VALIDATION' ) {
            return 'WARNING';
        }

        return 'INFO';
    }

    /**
     * Generate unique error ID for tracking
     *
     * @return string Unique error ID.
     */
    private function generate_error_id() {
        return 'mvdb_' . uniqid() . '_' . time();
    }

    /**
     * Store error log in WordPress cache
     *
     * @param array $error_data Error data.
     */
    private function store_error_log( $error_data ) {
        $error_logs = wp_cache_get( 'error_logs', $this->error_log_group );
        
        if ( $error_logs === false ) {
            $error_logs = [];
        }

        // Add new error to the beginning of the array
        array_unshift( $error_logs, $error_data );

        // Limit the number of stored errors
        if ( count( $error_logs ) > $this->max_error_entries ) {
            $error_logs = array_slice( $error_logs, 0, $this->max_error_entries );
        }

        wp_cache_set( 'error_logs', $error_logs, $this->error_log_group, DAY_IN_SECONDS );
    }

    /**
     * Get stored error logs with filtering options
     *
     * @param array $filters Filter options.
     * @return array Filtered error logs.
     */
    public function get_error_logs( $filters = [] ) {
        $error_logs = wp_cache_get( 'error_logs', $this->error_log_group );
        
        if ( $error_logs === false ) {
            return [];
        }

        return $this->filter_error_logs( $error_logs, $filters );
    }

    /**
     * Filter error logs based on criteria
     *
     * @param array $error_logs Raw error logs.
     * @param array $filters Filter criteria.
     * @return array Filtered error logs.
     */
    private function filter_error_logs( $error_logs, $filters ) {
        $filtered_logs = $error_logs;

        // Filter by severity
        if ( ! empty( $filters['severity'] ) ) {
            $filtered_logs = array_filter( $filtered_logs, function( $log ) use ( $filters ) {
                return $log['severity'] === $filters['severity'];
            });
        }

        // Filter by category
        if ( ! empty( $filters['category'] ) ) {
            $filtered_logs = array_filter( $filtered_logs, function( $log ) use ( $filters ) {
                return $log['category'] === $filters['category'];
            });
        }

        // Filter by time range
        if ( ! empty( $filters['since'] ) ) {
            $since_timestamp = strtotime( $filters['since'] );
            $filtered_logs = array_filter( $filtered_logs, function( $log ) use ( $since_timestamp ) {
                return strtotime( $log['timestamp'] ) >= $since_timestamp;
            });
        }

        // Limit results
        $limit = $filters['limit'] ?? 100;
        if ( count( $filtered_logs ) > $limit ) {
            $filtered_logs = array_slice( $filtered_logs, 0, $limit );
        }

        return array_values( $filtered_logs );
    }

    /**
     * Get error statistics and analytics
     *
     * @param string $timeframe Timeframe for statistics (hour, day, week, month).
     * @return array Error statistics.
     */
    public function get_error_statistics( $timeframe = 'day' ) {
        $error_logs = $this->get_error_logs();
        
        $timeframe_seconds = [
            'hour' => HOUR_IN_SECONDS,
            'day' => DAY_IN_SECONDS,
            'week' => WEEK_IN_SECONDS,
            'month' => MONTH_IN_SECONDS
        ];

        $since = time() - ( $timeframe_seconds[ $timeframe ] ?? DAY_IN_SECONDS );
        
        $recent_errors = array_filter( $error_logs, function( $log ) use ( $since ) {
            return strtotime( $log['timestamp'] ) >= $since;
        });

        $stats = [
            'total_errors' => count( $recent_errors ),
            'by_severity' => [],
            'by_category' => [],
            'error_rate' => 0,
            'most_common_errors' => [],
            'timeframe' => $timeframe
        ];

        // Count by severity
        foreach ( $this->severity_levels as $severity => $level ) {
            $count = count( array_filter( $recent_errors, function( $log ) use ( $severity ) {
                return $log['severity'] === $severity;
            }));
            $stats['by_severity'][ $severity ] = $count;
        }

        // Count by category
        foreach ( $this->error_categories as $category ) {
            $count = count( array_filter( $recent_errors, function( $log ) use ( $category ) {
                return $log['category'] === $category;
            }));
            $stats['by_category'][ $category ] = $count;
        }

        // Calculate error rate (errors per hour)
        $hours = ( $timeframe_seconds[ $timeframe ] ?? DAY_IN_SECONDS ) / HOUR_IN_SECONDS;
        $stats['error_rate'] = $hours > 0 ? round( count( $recent_errors ) / $hours, 2 ) : 0;

        // Find most common error messages
        $error_messages = array_column( $recent_errors, 'message' );
        $message_counts = array_count_values( $error_messages );
        arsort( $message_counts );
        $stats['most_common_errors'] = array_slice( $message_counts, 0, 5, true );

        return $stats;
    }

    /**
     * Attempt automatic error recovery
     *
     * @param array $error_data Error data.
     */
    private function attempt_error_recovery( $error_data ) {
        $category = $error_data['category'];
        $context = $error_data['context'];

        switch ( $category ) {
            case 'CACHE':
                $this->recover_from_cache_error( $context );
                break;
            case 'AUTHENTICATION':
                $this->recover_from_auth_error( $context );
                break;
            case 'RATE_LIMIT':
                $this->recover_from_rate_limit( $context );
                break;
            case 'TIMEOUT':
                $this->recover_from_timeout( $context );
                break;
        }
    }

    /**
     * Recover from cache-related errors
     *
     * @param array $context Error context.
     */
    private function recover_from_cache_error( $context ) {
        // Clear corrupted cache data
        if ( ! empty( $context['cache_key'] ) ) {
            wp_cache_delete( $context['cache_key'], 'ai_composer_mvdb' );
        }

        // Log recovery attempt
        $this->handle_error(
            'Attempted cache recovery by clearing corrupted data',
            'INFO',
            'CACHE',
            null,
            [ 'recovery_action' => 'cache_clear' ]
        );
    }

    /**
     * Recover from authentication errors
     *
     * @param array $context Error context.
     */
    private function recover_from_auth_error( $context ) {
        // Check if token needs refresh (if refresh mechanism exists)
        $settings = $this->settings_manager->get_all_settings();
        $access_token = $settings['mvdb_settings']['access_token'] ?? '';

        if ( empty( $access_token ) ) {
            $this->handle_error(
                'Authentication recovery failed: No access token configured',
                'CRITICAL',
                'CONFIGURATION',
                null,
                [ 'recovery_action' => 'auth_check_failed' ]
            );
        }
    }

    /**
     * Recover from rate limit errors
     *
     * @param array $context Error context.
     */
    private function recover_from_rate_limit( $context ) {
        // Set temporary backoff flag
        wp_cache_set( 'rate_limit_backoff', time(), 'ai_composer_mvdb', 300 ); // 5 minute backoff

        $this->handle_error(
            'Applied rate limit backoff strategy',
            'INFO',
            'RATE_LIMIT',
            null,
            [ 'recovery_action' => 'backoff_applied', 'backoff_duration' => 300 ]
        );
    }

    /**
     * Recover from timeout errors
     *
     * @param array $context Error context.
     */
    private function recover_from_timeout( $context ) {
        // Suggest timeout configuration increase
        $settings = $this->settings_manager->get_all_settings();
        $current_timeout = $settings['mvdb_settings']['timeout_seconds'] ?? 30;

        if ( $current_timeout < 60 ) {
            $this->handle_error(
                "Consider increasing timeout setting (current: {$current_timeout}s)",
                'WARNING',
                'CONFIGURATION',
                null,
                [ 'recovery_suggestion' => 'increase_timeout', 'current_timeout' => $current_timeout ]
            );
        }
    }

    /**
     * Send critical error alerts to external monitoring
     *
     * @param array $error_data Error data.
     */
    private function send_critical_alert( $error_data ) {
        // This would integrate with external monitoring services
        // For now, we'll just ensure it's logged prominently

        $alert_data = [
            'type' => 'MVDB_CRITICAL_ERROR',
            'error_id' => $error_data['id'],
            'message' => $error_data['message'],
            'timestamp' => $error_data['timestamp'],
            'context' => $error_data['context']
        ];

        // Log critical alert
        error_log( 
            sprintf( 
                '[AI Page Composer MVDB CRITICAL] %s',
                wp_json_encode( $alert_data )
            )
        );

        // Store critical alerts separately for admin notification
        $critical_alerts = wp_cache_get( 'critical_alerts', $this->error_log_group );
        if ( $critical_alerts === false ) {
            $critical_alerts = [];
        }

        array_unshift( $critical_alerts, $alert_data );
        
        // Keep only last 50 critical alerts
        if ( count( $critical_alerts ) > 50 ) {
            $critical_alerts = array_slice( $critical_alerts, 0, 50 );
        }

        wp_cache_set( 'critical_alerts', $critical_alerts, $this->error_log_group, WEEK_IN_SECONDS );
    }

    /**
     * Get critical alerts for admin notification
     *
     * @return array Critical alerts.
     */
    public function get_critical_alerts() {
        $critical_alerts = wp_cache_get( 'critical_alerts', $this->error_log_group );
        return $critical_alerts !== false ? $critical_alerts : [];
    }

    /**
     * Clear error logs
     *
     * @param array $filters Optional filters for selective clearing.
     * @return bool Success status.
     */
    public function clear_error_logs( $filters = [] ) {
        if ( empty( $filters ) ) {
            // Clear all logs
            wp_cache_delete( 'error_logs', $this->error_log_group );
            wp_cache_delete( 'critical_alerts', $this->error_log_group );
            return true;
        }

        // Selective clearing based on filters
        $error_logs = $this->get_error_logs();
        $filtered_logs = $this->filter_error_logs( $error_logs, $filters );
        
        // Remove filtered logs from the main collection
        $remaining_logs = array_udiff( $error_logs, $filtered_logs, function( $a, $b ) {
            return strcmp( $a['id'], $b['id'] );
        });

        wp_cache_set( 'error_logs', array_values( $remaining_logs ), $this->error_log_group, DAY_IN_SECONDS );
        
        return true;
    }

    /**
     * Cleanup old error logs (scheduled task)
     */
    public function cleanup_old_errors() {
        $error_logs = wp_cache_get( 'error_logs', $this->error_log_group );
        
        if ( $error_logs === false ) {
            return;
        }

        $cutoff_time = strtotime( '-7 days' ); // Keep errors for 7 days
        
        $recent_logs = array_filter( $error_logs, function( $log ) use ( $cutoff_time ) {
            return strtotime( $log['timestamp'] ) > $cutoff_time;
        });

        wp_cache_set( 'error_logs', array_values( $recent_logs ), $this->error_log_group, DAY_IN_SECONDS );

        // Log cleanup activity
        $removed_count = count( $error_logs ) - count( $recent_logs );
        if ( $removed_count > 0 ) {
            $this->handle_error(
                "Cleaned up {$removed_count} old error log entries",
                'INFO',
                'MAINTENANCE',
                null,
                [ 'cleanup_action' => 'old_errors_removed', 'count' => $removed_count ]
            );
        }
    }

    /**
     * Sanitize context data for logging
     *
     * @param array $context Raw context data.
     * @return array Sanitized context.
     */
    private function sanitize_context( $context ) {
        $sanitized = [];

        foreach ( $context as $key => $value ) {
            $clean_key = sanitize_key( $key );
            
            if ( is_array( $value ) ) {
                $sanitized[ $clean_key ] = $this->sanitize_context( $value );
            } elseif ( is_string( $value ) ) {
                // Don't log sensitive information
                if ( in_array( strtolower( $key ), [ 'password', 'token', 'key', 'secret' ], true ) ) {
                    $sanitized[ $clean_key ] = '[REDACTED]';
                } else {
                    $sanitized[ $clean_key ] = sanitize_textarea_field( $value );
                }
            } else {
                $sanitized[ $clean_key ] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize API request data for logging
     *
     * @param array $request_data API request data.
     * @return array Sanitized request data.
     */
    private function sanitize_api_request( $request_data ) {
        $sanitized = $this->sanitize_context( $request_data );
        
        // Remove sensitive headers
        if ( isset( $sanitized['headers'] ) ) {
            unset( $sanitized['headers']['Authorization'] );
        }

        return $sanitized;
    }

    /**
     * Sanitize API response data for logging
     *
     * @param array $response_data API response data.
     * @return array Sanitized response data.
     */
    private function sanitize_api_response( $response_data ) {
        $sanitized = $this->sanitize_context( $response_data );
        
        // Truncate large response bodies
        if ( isset( $sanitized['body'] ) && strlen( $sanitized['body'] ) > 1000 ) {
            $sanitized['body'] = substr( $sanitized['body'], 0, 1000 ) . '... [TRUNCATED]';
        }

        return $sanitized;
    }

    /**
     * Format stack trace for logging
     *
     * @param \Exception $exception Exception object.
     * @return array Formatted stack trace.
     */
    private function format_stack_trace( $exception ) {
        $trace = $exception->getTrace();
        $formatted_trace = [];

        foreach ( array_slice( $trace, 0, 10 ) as $i => $frame ) {
            $formatted_trace[] = sprintf(
                '#%d %s(%d): %s%s%s()',
                $i,
                $frame['file'] ?? '[internal]',
                $frame['line'] ?? 0,
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? ''
            );
        }

        return $formatted_trace;
    }

    /**
     * Get client IP address safely
     *
     * @return string Client IP address.
     */
    private function get_client_ip() {
        $ip_keys = [ 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' ];
        
        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = $_SERVER[ $key ];
                
                // Handle comma-separated IPs (forwarded headers)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Log to WordPress debug log
     *
     * @param array $error_data Error data.
     */
    private function log_to_wp_debug( $error_data ) {
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            $log_entry = sprintf(
                '[AI Page Composer MVDB ERROR] [%s] [%s] %s - Context: %s',
                $error_data['severity'],
                $error_data['category'],
                $error_data['message'],
                wp_json_encode( $error_data['context'] )
            );
            
            error_log( $log_entry );
        }
    }

    /**
     * AJAX handler for getting error logs
     */
    public function ajax_get_error_logs() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $filters = $_POST['filters'] ?? [];
        $error_logs = $this->get_error_logs( $filters );
        $statistics = $this->get_error_statistics( $_POST['timeframe'] ?? 'day' );

        wp_send_json_success([
            'logs' => $error_logs,
            'statistics' => $statistics,
            'critical_alerts' => $this->get_critical_alerts()
        ]);
    }

    /**
     * AJAX handler for clearing error logs
     */
    public function ajax_clear_error_logs() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $filters = $_POST['filters'] ?? [];
        $success = $this->clear_error_logs( $filters );

        if ( $success ) {
            wp_send_json_success( [ 'message' => 'Error logs cleared successfully' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Failed to clear error logs' ] );
        }
    }
}