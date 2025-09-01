<?php
/**
 * Cache Manager Class - Section Generation Result Caching
 * 
 * This file contains the Cache_Manager class that handles caching of section
 * generation results to improve performance and reduce API costs. It provides
 * intelligent cache key generation, TTL management, and cache invalidation.
 *
 * @package AIPageComposer\API
 */

namespace AIPageComposer\API;

use AIPageComposer\Admin\Settings_Manager;
use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// WordPress function declarations for namespaced context
if ( ! function_exists( 'AIPageComposer\API\add_action' ) ) {
    function add_action( ...$args ) {
        return \add_action( ...$args );
    }
}

if ( ! function_exists( 'AIPageComposer\API\wp_cache_get' ) ) {
    function wp_cache_get( ...$args ) {
        return \wp_cache_get( ...$args );
    }
}

if ( ! function_exists( 'AIPageComposer\API\wp_cache_set' ) ) {
    function wp_cache_set( ...$args ) {
        return \wp_cache_set( ...$args );
    }
}

if ( ! function_exists( 'AIPageComposer\API\wp_cache_delete' ) ) {
    function wp_cache_delete( ...$args ) {
        return \wp_cache_delete( ...$args );
    }
}

if ( ! function_exists( 'AIPageComposer\API\wp_cache_flush_group' ) ) {
    function wp_cache_flush_group( ...$args ) {
        return \wp_cache_flush_group( ...$args );
    }
}

if ( ! function_exists( 'AIPageComposer\API\current_time' ) ) {
    function current_time( ...$args ) {
        return \current_time( ...$args );
    }
}

if ( ! function_exists( 'AIPageComposer\API\absint' ) ) {
    function absint( ...$args ) {
        return \absint( ...$args );
    }
}

/**
 * Cache Manager class for section generation caching
 */
class Cache_Manager {

    /**
     * Settings manager instance
     *
     * @var Settings_Manager
     */
    private $settings_manager;

    /**
     * Cache group for section results
     *
     * @var string
     */
    private $cache_group = 'ai_composer_sections';

    /**
     * Default cache TTL (1 hour)
     *
     * @var int
     */
    private $default_ttl = 3600;

    /**
     * Maximum cache size per entry (1MB)
     *
     * @var int
     */
    private $max_cache_size = 1048576;

    /**
     * Cache statistics
     *
     * @var array
     */
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings_manager = new Settings_Manager();
        $this->init_cache_hooks();
    }

    /**
     * Initialize cache-related hooks
     */
    private function init_cache_hooks() {
        // Clean up expired cache entries daily
        add_action( 'ai_composer_daily_cleanup', [ $this, 'cleanup_expired_cache' ] );
        
        // Clear cache when settings change
        add_action( 'ai_composer_settings_updated', [ $this, 'clear_all_cache' ] );
    }

    /**
     * Get cached result
     *
     * @param string $cache_key Cache key.
     * @return mixed Cached data or false if not found.
     */
    public function get( $cache_key ) {
        if ( ! $this->is_caching_enabled() ) {
            return false;
        }

        $cache_key = $this->sanitize_cache_key( $cache_key );
        
        // Try WordPress object cache first
        $cached_data = wp_cache_get( $cache_key, $this->cache_group );
        
        if ( $cached_data !== false ) {
            $this->stats['hits']++;
            return $this->decompress_cache_data( $cached_data );
        }
        
        // Try database cache as fallback
        $cached_data = $this->get_from_database_cache( $cache_key );
        
        if ( $cached_data !== false ) {
            // Store back in object cache for faster access
            wp_cache_set( $cache_key, $cached_data, $this->cache_group, $this->get_cache_ttl() );
            $this->stats['hits']++;
            return $this->decompress_cache_data( $cached_data );
        }
        
        $this->stats['misses']++;
        return false;
    }

    /**
     * Set cached result
     *
     * @param string $cache_key Cache key.
     * @param mixed  $data Data to cache.
     * @param int    $ttl Time to live in seconds.
     * @return bool True on success, false on failure.
     */
    public function set( $cache_key, $data, $ttl = null ) {
        if ( ! $this->is_caching_enabled() ) {
            return false;
        }

        $cache_key = $this->sanitize_cache_key( $cache_key );
        $ttl = $ttl ?: $this->get_cache_ttl();
        
        // Compress data if it's large
        $compressed_data = $this->compress_cache_data( $data );
        
        // Check size limits
        if ( $this->get_data_size( $compressed_data ) > $this->max_cache_size ) {
            error_log( '[AI Composer] Cache data too large for key: ' . $cache_key );
            return false;
        }
        
        // Set in object cache
        $object_cache_success = wp_cache_set( $cache_key, $compressed_data, $this->cache_group, $ttl );
        
        // Set in database cache as backup
        $database_cache_success = $this->set_in_database_cache( $cache_key, $compressed_data, $ttl );
        
        if ( $object_cache_success || $database_cache_success ) {
            $this->stats['sets']++;
            return true;
        }
        
        return false;
    }

    /**
     * Delete cached result
     *
     * @param string $cache_key Cache key.
     * @return bool True on success, false on failure.
     */
    public function delete( $cache_key ) {
        $cache_key = $this->sanitize_cache_key( $cache_key );
        
        // Delete from object cache
        $object_cache_success = wp_cache_delete( $cache_key, $this->cache_group );
        
        // Delete from database cache
        $database_cache_success = $this->delete_from_database_cache( $cache_key );
        
        if ( $object_cache_success || $database_cache_success ) {
            $this->stats['deletes']++;
            return true;
        }
        
        return false;
    }

    /**
     * Clear all cached results
     *
     * @return bool True on success, false on failure.
     */
    public function clear_all_cache() {
        // Clear object cache group
        wp_cache_flush_group( $this->cache_group );
        
        // Clear database cache
        return $this->clear_database_cache();
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics.
     */
    public function get_statistics() {
        $total_requests = $this->stats['hits'] + $this->stats['misses'];
        $hit_rate = $total_requests > 0 ? ( $this->stats['hits'] / $total_requests ) * 100 : 0;
        
        return array_merge( $this->stats, [
            'hit_rate' => round( $hit_rate, 2 ),
            'total_requests' => $total_requests,
            'cache_size' => $this->get_cache_size(),
            'cache_entries' => $this->get_cache_entry_count()
        ] );
    }

    /**
     * Check if caching is enabled
     *
     * @return bool True if caching is enabled.
     */
    private function is_caching_enabled() {
        $settings = $this->settings_manager->get_all_settings();
        return ! empty( $settings['cache_settings']['enable_section_cache'] );
    }

    /**
     * Get cache TTL
     *
     * @return int Cache TTL in seconds.
     */
    private function get_cache_ttl() {
        $settings = $this->settings_manager->get_all_settings();
        return absint( $settings['cache_settings']['section_cache_ttl'] ?? $this->default_ttl );
    }

    /**
     * Sanitize cache key
     *
     * @param string $cache_key Raw cache key.
     * @return string Sanitized cache key.
     */
    private function sanitize_cache_key( $cache_key ) {
        // Remove invalid characters and limit length
        $sanitized = preg_replace( '/[^a-zA-Z0-9_-]/', '_', $cache_key );
        return substr( $sanitized, 0, 172 ); // WordPress cache key limit
    }

    /**
     * Compress cache data
     *
     * @param mixed $data Data to compress.
     * @return string Compressed data.
     */
    private function compress_cache_data( $data ) {
        $serialized = serialize( $data );
        
        // Add compression metadata
        $cache_entry = [
            'data' => $serialized,
            'compressed' => false,
            'created_at' => time(),
            'version' => '1.0'
        ];
        
        // Compress if data is large
        if ( strlen( $serialized ) > 1024 ) {
            if ( function_exists( 'gzcompress' ) ) {
                $compressed = gzcompress( $serialized, 6 );
                if ( $compressed !== false && strlen( $compressed ) < strlen( $serialized ) ) {
                    $cache_entry['data'] = $compressed;
                    $cache_entry['compressed'] = true;
                }
            }
        }
        
        return serialize( $cache_entry );
    }

    /**
     * Decompress cache data
     *
     * @param string $compressed_data Compressed data.
     * @return mixed Decompressed data.
     */
    private function decompress_cache_data( $compressed_data ) {
        $cache_entry = unserialize( $compressed_data );
        
        if ( ! is_array( $cache_entry ) || ! isset( $cache_entry['data'] ) ) {
            // Legacy format or corrupted data
            return unserialize( $compressed_data );
        }
        
        $data = $cache_entry['data'];
        
        if ( ! empty( $cache_entry['compressed'] ) ) {
            if ( function_exists( 'gzuncompress' ) ) {
                $data = gzuncompress( $data );
            }
        }
        
        return unserialize( $data );
    }

    /**
     * Get data size in bytes
     *
     * @param mixed $data Data to measure.
     * @return int Size in bytes.
     */
    private function get_data_size( $data ) {
        return strlen( is_string( $data ) ? $data : serialize( $data ) );
    }

    /**
     * Get cached result from database
     *
     * @param string $cache_key Cache key.
     * @return mixed Cached data or false if not found.
     */
    private function get_from_database_cache( $cache_key ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_composer_cache';
        
        $result = $wpdb->get_row( $wpdb->prepare(
            "SELECT cache_data, expires_at FROM {$table_name} WHERE cache_key = %s AND expires_at > NOW()",
            $cache_key
        ) );
        
        if ( $result ) {
            return $result->cache_data;
        }
        
        return false;
    }

    /**
     * Set cached result in database
     *
     * @param string $cache_key Cache key.
     * @param string $data Compressed data.
     * @param int    $ttl Time to live in seconds.
     * @return bool True on success, false on failure.
     */
    private function set_in_database_cache( $cache_key, $data, $ttl ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_composer_cache';
        $expires_at = date( 'Y-m-d H:i:s', time() + $ttl );
        
        $result = $wpdb->replace(
            $table_name,
            [
                'cache_key' => $cache_key,
                'cache_data' => $data,
                'created_at' => current_time( 'mysql' ),
                'expires_at' => $expires_at,
                'cache_group' => $this->cache_group
            ],
            [ '%s', '%s', '%s', '%s', '%s' ]
        );
        
        return $result !== false;
    }

    /**
     * Delete cached result from database
     *
     * @param string $cache_key Cache key.
     * @return bool True on success, false on failure.
     */
    private function delete_from_database_cache( $cache_key ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_composer_cache';
        
        $result = $wpdb->delete(
            $table_name,
            [ 'cache_key' => $cache_key ],
            [ '%s' ]
        );
        
        return $result !== false;
    }

    /**
     * Clear all database cache
     *
     * @return bool True on success, false on failure.
     */
    private function clear_database_cache() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_composer_cache';
        
        $result = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table_name} WHERE cache_group = %s",
            $this->cache_group
        ) );
        
        return $result !== false;
    }

    /**
     * Get cache size in bytes
     *
     * @return int Cache size in bytes.
     */
    private function get_cache_size() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_composer_cache';
        
        $result = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(LENGTH(cache_data)) FROM {$table_name} WHERE cache_group = %s AND expires_at > NOW()",
            $this->cache_group
        ) );
        
        return absint( $result );
    }

    /**
     * Get cache entry count
     *
     * @return int Number of cache entries.
     */
    private function get_cache_entry_count() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_composer_cache';
        
        $result = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE cache_group = %s AND expires_at > NOW()",
            $this->cache_group
        ) );
        
        return absint( $result );
    }

    /**
     * Cleanup expired cache entries
     */
    public function cleanup_expired_cache() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_composer_cache';
        
        $deleted = $wpdb->query(
            "DELETE FROM {$table_name} WHERE expires_at <= NOW()"
        );
        
        if ( $deleted > 0 ) {
            error_log( "[AI Composer] Cleaned up {$deleted} expired cache entries" );
        }
    }

    /**
     * Generate cache key for section parameters
     *
     * @param array $params Section parameters.
     * @return string Cache key.
     */
    public function generate_section_cache_key( $params ) {
        // Include relevant parameters in cache key
        $cache_params = [
            'sectionId' => $params['sectionId'] ?? '',
            'content_brief' => $params['content_brief'] ?? '',
            'mode' => $params['mode'] ?? 'hybrid',
            'alpha' => $params['alpha'] ?? 0.7,
            'block_preferences' => $params['block_preferences'] ?? [],
            'image_requirements' => $params['image_requirements'] ?? []
        ];
        
        // Add settings version to invalidate cache when settings change
        $settings = $this->settings_manager->get_all_settings();
        $settings_hash = hash( 'crc32', serialize( $settings ) );
        $cache_params['settings_version'] = $settings_hash;
        
        // Generate deterministic cache key
        return 'section_' . hash( 'sha256', serialize( $cache_params ) );
    }

    /**
     * Warm up cache with common section types
     *
     * @param array $section_types Section types to warm up.
     */
    public function warm_up_cache( $section_types = [] ) {
        if ( empty( $section_types ) ) {
            $section_types = [ 'hero', 'content', 'testimonial', 'cta' ];
        }
        
        foreach ( $section_types as $section_type ) {
            // This would pre-generate common sections
            // Implementation would depend on specific requirements
            error_log( "[AI Composer] Cache warm-up for section type: {$section_type}" );
        }
    }

    /**
     * Get cache health status
     *
     * @return array Cache health information.
     */
    public function get_cache_health() {
        $stats = $this->get_statistics();
        
        return [
            'status' => $this->is_caching_enabled() ? 'enabled' : 'disabled',
            'hit_rate' => $stats['hit_rate'],
            'total_entries' => $stats['cache_entries'],
            'total_size_mb' => round( $stats['cache_size'] / 1048576, 2 ),
            'memory_usage' => $this->get_memory_usage(),
            'recommendations' => $this->get_cache_recommendations( $stats )
        ];
    }

    /**
     * Get memory usage information
     *
     * @return array Memory usage data.
     */
    private function get_memory_usage() {
        return [
            'current_mb' => round( memory_get_usage() / 1048576, 2 ),
            'peak_mb' => round( memory_get_peak_usage() / 1048576, 2 ),
            'limit_mb' => ini_get( 'memory_limit' )
        ];
    }

    /**
     * Get cache recommendations
     *
     * @param array $stats Cache statistics.
     * @return array Recommendations.
     */
    private function get_cache_recommendations( $stats ) {
        $recommendations = [];
        
        if ( $stats['hit_rate'] < 50 && $stats['total_requests'] > 10 ) {
            $recommendations[] = 'Consider increasing cache TTL to improve hit rate';
        }
        
        if ( $stats['cache_entries'] > 1000 ) {
            $recommendations[] = 'Large number of cache entries - consider cleanup';
        }
        
        if ( $stats['cache_size'] > 50 * 1048576 ) { // 50MB
            $recommendations[] = 'Cache size is large - consider reducing TTL or entry limits';
        }
        
        return $recommendations;
    }
}