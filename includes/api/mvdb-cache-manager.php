<?php
/**
 * MVDB Cache Manager Class - Advanced Caching Layer
 * 
 * This file contains the MVDB_Cache_Manager class that provides advanced
 * caching functionality for MVDB responses including query hashing,
 * TTL management, cache statistics, and intelligent invalidation.
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
 * MVDB Cache Manager class for advanced caching operations
 */
class MVDB_Cache_Manager {

    /**
     * Cache group for MVDB responses
     *
     * @var string
     */
    private $cache_group = 'ai_composer_mvdb';

    /**
     * Cache statistics group
     *
     * @var string
     */
    private $stats_group = 'ai_composer_mvdb_stats';

    /**
     * Settings manager instance
     *
     * @var Settings_Manager
     */
    private $settings_manager;

    /**
     * Default cache TTL
     *
     * @var int
     */
    private $default_ttl = 3600;

    /**
     * Constructor
     *
     * @param Settings_Manager $settings_manager Settings manager instance.
     */
    public function __construct( $settings_manager = null ) {
        $this->settings_manager = $settings_manager ?: new Settings_Manager();
        $this->init_cache_settings();
    }

    /**
     * Initialize cache settings
     */
    private function init_cache_settings() {
        $settings = $this->settings_manager->get_all_settings();
        $this->default_ttl = $settings['mvdb_settings']['cache_ttl'] ?? 3600;
    }

    /**
     * Generate cache key with enhanced hashing
     *
     * @param array $params Query parameters.
     * @return string Cache key.
     */
    public function generate_cache_key( $params ) {
        // Sort parameters for consistent hashing
        ksort( $params );
        
        // Handle nested arrays consistently
        array_walk_recursive( $params, function( &$value, $key ) {
            if ( is_array( $value ) ) {
                sort( $value );
            }
        });
        
        // Create cache data for hashing
        $cache_data = [
            'version' => '1.0', // Cache version for invalidation
            'query' => $params['query'] ?? '',
            'namespaces' => $params['namespaces'] ?? [],
            'k' => $params['k'] ?? 10,
            'min_score' => $params['min_score'] ?? 0.5,
            'filters' => $params['filters'] ?? [],
            'timestamp' => floor( time() / 300 ) // 5-minute buckets for near-real-time caching
        ];
        
        $hash = hash( 'sha256', wp_json_encode( $cache_data ) );
        return "mvdb_{$hash}";
    }

    /**
     * Get cached response
     *
     * @param string $cache_key Cache key.
     * @return array|false Cached data or false if not found.
     */
    public function get_cached_response( $cache_key ) {
        $cached_data = wp_cache_get( $cache_key, $this->cache_group );
        
        if ( $cached_data !== false ) {
            $this->update_cache_stats( 'hit' );
            $this->log_cache_access( $cache_key, 'hit' );
            
            // Validate cache data integrity
            if ( $this->validate_cached_data( $cached_data ) ) {
                return $this->prepare_cached_response( $cached_data );
            } else {
                // Invalid cache data, remove it
                $this->delete_cached_response( $cache_key );
            }
        }
        
        $this->update_cache_stats( 'miss' );
        $this->log_cache_access( $cache_key, 'miss' );
        
        return false;
    }

    /**
     * Store response in cache
     *
     * @param string $cache_key Cache key.
     * @param array  $response Response data.
     * @param int    $ttl Time to live (optional).
     * @return bool Success status.
     */
    public function store_response( $cache_key, $response, $ttl = null ) {
        $ttl = $ttl ?: $this->default_ttl;
        
        // Prepare cache data with metadata
        $cache_data = [
            'response' => $response,
            'stored_at' => time(),
            'ttl' => $ttl,
            'version' => '1.0',
            'checksum' => $this->calculate_response_checksum( $response )
        ];
        
        $success = wp_cache_set( $cache_key, $cache_data, $this->cache_group, $ttl );
        
        if ( $success ) {
            $this->update_cache_stats( 'store' );
            $this->log_cache_access( $cache_key, 'store', $ttl );
            $this->track_cache_size( $cache_key, $response );
        }
        
        return $success;
    }

    /**
     * Delete cached response
     *
     * @param string $cache_key Cache key.
     * @return bool Success status.
     */
    public function delete_cached_response( $cache_key ) {
        $success = wp_cache_delete( $cache_key, $this->cache_group );
        
        if ( $success ) {
            $this->update_cache_stats( 'delete' );
            $this->log_cache_access( $cache_key, 'delete' );
        }
        
        return $success;
    }

    /**
     * Flush all MVDB cache
     *
     * @return bool Success status.
     */
    public function flush_cache() {
        $success = wp_cache_flush_group( $this->cache_group );
        
        if ( $success ) {
            $this->reset_cache_stats();
            $this->log_cache_access( 'all', 'flush' );
        }
        
        return $success;
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics.
     */
    public function get_cache_stats() {
        $stats = wp_cache_get( 'cache_stats', $this->stats_group );
        
        if ( $stats === false ) {
            $stats = $this->init_cache_stats();
        }
        
        // Calculate derived metrics
        $total_requests = $stats['hits'] + $stats['misses'];
        $hit_rate = $total_requests > 0 ? ( $stats['hits'] / $total_requests ) * 100 : 0;
        
        return array_merge( $stats, [
            'total_requests' => $total_requests,
            'hit_rate_percentage' => round( $hit_rate, 2 ),
            'avg_response_size' => $stats['total_stores'] > 0 ? round( $stats['total_size'] / $stats['total_stores'], 2 ) : 0
        ]);
    }

    /**
     * Validate cached data integrity
     *
     * @param array $cached_data Cached data.
     * @return bool True if valid.
     */
    private function validate_cached_data( $cached_data ) {
        // Check required fields
        if ( ! isset( $cached_data['response'], $cached_data['stored_at'], $cached_data['checksum'] ) ) {
            return false;
        }
        
        // Check data age (additional safety check beyond TTL)
        $max_age = 86400; // 24 hours absolute maximum
        if ( ( time() - $cached_data['stored_at'] ) > $max_age ) {
            return false;
        }
        
        // Verify checksum
        $expected_checksum = $this->calculate_response_checksum( $cached_data['response'] );
        if ( $cached_data['checksum'] !== $expected_checksum ) {
            return false;
        }
        
        return true;
    }

    /**
     * Calculate response checksum for integrity validation
     *
     * @param array $response Response data.
     * @return string Checksum.
     */
    private function calculate_response_checksum( $response ) {
        // Use only critical response data for checksum
        $checksum_data = [
            'chunks_count' => count( $response['chunks'] ?? [] ),
            'total_retrieved' => $response['total_retrieved'] ?? 0,
            'query_hash' => $response['query_hash'] ?? ''
        ];
        
        return hash( 'md5', wp_json_encode( $checksum_data ) );
    }

    /**
     * Prepare cached response with cache metadata
     *
     * @param array $cached_data Cached data.
     * @return array Response with cache metadata.
     */
    private function prepare_cached_response( $cached_data ) {
        $response = $cached_data['response'];
        
        // Add cache metadata
        $response['cached'] = true;
        $response['cache_timestamp'] = date( 'Y-m-d H:i:s', $cached_data['stored_at'] );
        $response['cache_age_seconds'] = time() - $cached_data['stored_at'];
        $response['cache_ttl'] = $cached_data['ttl'] ?? $this->default_ttl;
        
        return $response;
    }

    /**
     * Update cache statistics
     *
     * @param string $action Action type (hit, miss, store, delete).
     * @param int    $size   Optional size for store operations.
     */
    private function update_cache_stats( $action, $size = 0 ) {
        $stats = wp_cache_get( 'cache_stats', $this->stats_group );
        
        if ( $stats === false ) {
            $stats = $this->init_cache_stats();
        }
        
        switch ( $action ) {
            case 'hit':
                $stats['hits']++;
                $stats['last_hit'] = time();
                break;
            case 'miss':
                $stats['misses']++;
                $stats['last_miss'] = time();
                break;
            case 'store':
                $stats['stores']++;
                $stats['total_stores']++;
                $stats['total_size'] += $size;
                $stats['last_store'] = time();
                break;
            case 'delete':
                $stats['deletes']++;
                $stats['last_delete'] = time();
                break;
        }
        
        wp_cache_set( 'cache_stats', $stats, $this->stats_group, 86400 ); // 24 hour TTL for stats
    }

    /**
     * Initialize cache statistics
     *
     * @return array Initial stats structure.
     */
    private function init_cache_stats() {
        return [
            'hits' => 0,
            'misses' => 0,
            'stores' => 0,
            'deletes' => 0,
            'total_stores' => 0,
            'total_size' => 0,
            'last_hit' => 0,
            'last_miss' => 0,
            'last_store' => 0,
            'last_delete' => 0,
            'initialized_at' => time()
        ];
    }

    /**
     * Reset cache statistics
     */
    private function reset_cache_stats() {
        $stats = $this->init_cache_stats();
        wp_cache_set( 'cache_stats', $stats, $this->stats_group, 86400 );
    }

    /**
     * Track cache size for statistics
     *
     * @param string $cache_key Cache key.
     * @param array  $response  Response data.
     */
    private function track_cache_size( $cache_key, $response ) {
        $size = strlen( wp_json_encode( $response ) );
        $this->update_cache_stats( 'store', $size );
    }

    /**
     * Log cache access for debugging
     *
     * @param string $cache_key Cache key.
     * @param string $action    Action type.
     * @param int    $ttl       TTL for store operations.
     */
    private function log_cache_access( $cache_key, $action, $ttl = null ) {
        $settings = $this->settings_manager->get_all_settings();
        $debug_enabled = $settings['mvdb_settings']['enable_debug_logging'] ?? false;
        
        if ( $debug_enabled && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            $log_data = [
                'cache_key' => substr( $cache_key, 0, 20 ) . '...', // Truncate for readability
                'action' => $action,
                'timestamp' => current_time( 'mysql' )
            ];
            
            if ( $ttl ) {
                $log_data['ttl'] = $ttl;
            }
            
            error_log( 
                sprintf( 
                    '[AI Page Composer MVDB Cache] %s',
                    wp_json_encode( $log_data )
                )
            );
        }
    }

    /**
     * Intelligent cache warming
     *
     * @param array $popular_queries Array of popular query parameters.
     * @return array Warming results.
     */
    public function warm_cache( $popular_queries ) {
        $results = [
            'total_queries' => count( $popular_queries ),
            'warmed' => 0,
            'skipped' => 0,
            'errors' => 0
        ];
        
        foreach ( $popular_queries as $params ) {
            try {
                $cache_key = $this->generate_cache_key( $params );
                
                // Skip if already cached
                if ( $this->get_cached_response( $cache_key ) !== false ) {
                    $results['skipped']++;
                    continue;
                }
                
                // Here you would trigger actual MVDB retrieval
                // This is a placeholder for cache warming logic
                $results['warmed']++;
                
            } catch ( \Exception $e ) {
                $results['errors']++;
                error_log( '[MVDB Cache] Cache warming error: ' . $e->getMessage() );
            }
        }
        
        return $results;
    }

    /**
     * Cache maintenance and cleanup
     *
     * @return array Maintenance results.
     */
    public function perform_maintenance() {
        $results = [
            'expired_cleaned' => 0,
            'invalid_cleaned' => 0,
            'stats_reset' => false
        ];
        
        // This would typically involve iterating through cache keys
        // WordPress cache doesn't provide key enumeration, so this is conceptual
        
        // Reset stats if they're very old
        $stats = $this->get_cache_stats();
        if ( ( time() - $stats['initialized_at'] ) > 604800 ) { // 1 week
            $this->reset_cache_stats();
            $results['stats_reset'] = true;
        }
        
        return $results;
    }

    /**
     * Get cache key suggestions for optimization
     *
     * @param array $recent_queries Recent query parameters.
     * @return array Optimization suggestions.
     */
    public function get_optimization_suggestions( $recent_queries ) {
        $suggestions = [];
        
        // Analyze query patterns
        $query_patterns = [];
        foreach ( $recent_queries as $params ) {
            $pattern = [
                'namespace_count' => count( $params['namespaces'] ?? [] ),
                'has_filters' => ! empty( $params['filters'] ),
                'k_value' => $params['k'] ?? 10
            ];
            
            $pattern_key = implode( '_', $pattern );
            $query_patterns[ $pattern_key ] = ( $query_patterns[ $pattern_key ] ?? 0 ) + 1;
        }
        
        // Generate suggestions based on patterns
        $stats = $this->get_cache_stats();
        
        if ( $stats['hit_rate_percentage'] < 50 ) {
            $suggestions[] = [
                'type' => 'low_hit_rate',
                'message' => 'Cache hit rate is below 50%. Consider increasing cache TTL or optimizing query patterns.',
                'current_hit_rate' => $stats['hit_rate_percentage']
            ];
        }
        
        if ( $stats['avg_response_size'] > 50000 ) { // 50KB
            $suggestions[] = [
                'type' => 'large_responses',
                'message' => 'Average response size is large. Consider reducing k values or implementing response compression.',
                'avg_size_bytes' => $stats['avg_response_size']
            ];
        }
        
        return $suggestions;
    }
}