<?php
/**
 * MVDB Manager Class - WP Engine Smart Search Vector Database Integration
 * 
 * This file contains the MVDB_Manager class that handles WP Engine Smart Search
 * vector database operations for content retrieval. It provides the core
 * functionality for the retrieval pipeline including parameter validation,
 * cache management, API integration, and response processing.
 *
 * @package AIPageComposer\API
 */

namespace AIPageComposer\API;

use AIPageComposer\Admin\Settings_Manager;
use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;
use AIPageComposer\API\MVDB_Cache_Manager;
use AIPageComposer\API\MVDB_Error_Handler;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MVDB Manager class for vector database operations
 */
class MVDB_Manager {

    /**
     * Settings manager instance
     *
     * @var Settings_Manager
     */
    private $settings_manager;

    /**
     * Cache manager instance
     *
     * @var MVDB_Cache_Manager
     */
    private $cache_manager;

    /**
     * Error handler instance
     *
     * @var MVDB_Error_Handler
     */
    private $error_handler;

    /**
     * Cache group for MVDB responses
     *
     * @var string
     */
    private $cache_group = 'ai_composer_mvdb';

    /**
     * Default cache TTL (1 hour)
     *
     * @var int
     */
    private $cache_ttl = 3600;

    /**
     * WP Engine Smart Search API base URL
     *
     * @var string
     */
    private $api_base_url;

    /**
     * API access token
     *
     * @var string
     */
    private $access_token;

    /**
     * Constructor
     *
     * @param Settings_Manager $settings_manager Settings manager instance.
     */
    public function __construct( $settings_manager = null ) {
        $this->settings_manager = $settings_manager ?: new Settings_Manager();
        $this->cache_manager = new MVDB_Cache_Manager( $this->settings_manager );
        $this->error_handler = new MVDB_Error_Handler( $this->settings_manager );
        $this->init_api_credentials();
    }

    /**
     * Initialize API credentials from settings
     *
     * @throws \Exception If MVDB API credentials not configured.
     */
    private function init_api_credentials() {
        try {
            $settings = $this->settings_manager->get_all_settings();
            
            $this->api_base_url = $settings['mvdb_settings']['api_url'] ?? '';
            $this->access_token = $settings['mvdb_settings']['access_token'] ?? '';
            $this->cache_ttl = $settings['mvdb_settings']['cache_ttl'] ?? 3600;
            
            if ( empty( $this->api_base_url ) || empty( $this->access_token ) ) {
                throw new \Exception( __( 'MVDB API credentials not configured', 'ai-page-composer' ) );
            }
        } catch ( \Exception $e ) {
            $this->error_handler->handle_error(
                'Failed to initialize MVDB API credentials',
                'CRITICAL',
                'CONFIGURATION',
                $e,
                [ 'settings_available' => ! empty( $settings ) ]
            );
            throw $e;
        }
    }

    /**
     * Retrieve context chunks from MVDB
     *
     * @param array $params Retrieval parameters.
     * @return array Retrieved chunks with metadata.
     * @throws \Exception If retrieval fails.
     */
    public function retrieve_context( $params ) {
        try {
            // Start timing
            $start_time = microtime( true );

            // Validate parameters
            $validated_params = $this->validate_retrieval_params( $params );
            
            // Generate cache key
            $cache_key = $this->cache_manager->generate_cache_key( $validated_params );
            
            // Check cache first
            $cached_result = $this->cache_manager->get_cached_response( $cache_key );
            if ( $cached_result !== false ) {
                return $cached_result;
            }
            
            // Build similarity query
            $similarity_query = $this->build_similarity_query( $validated_params );
            
            // Execute API request
            $response = $this->execute_similarity_request( $similarity_query );
            
            // Process and filter response
            $processed_chunks = $this->process_similarity_response( $response, $validated_params );
            
            // Apply quality filters
            $filtered_chunks = $this->apply_quality_filters( $processed_chunks, $validated_params );
            
            // Calculate metrics
            $processing_time = ( microtime( true ) - $start_time ) * 1000; // Convert to milliseconds
            $result = $this->calculate_retrieval_metrics( $filtered_chunks, $validated_params, $processing_time );
            
            // Cache result
            $this->cache_manager->store_response( $cache_key, $result );
            
            return $result;

        } catch ( \Exception $e ) {
            $error_id = $this->error_handler->handle_error(
                'MVDB retrieval pipeline failed',
                'ERROR',
                'API_RESPONSE',
                $e,
                [ 'retrieval_params' => $params ]
            );
            
            $this->log_error( 'MVDB retrieval failed', $e->getMessage(), [ 'error_id' => $error_id, 'params' => $params ] );
            throw $e;
        }
    }

    /**
     * Validate retrieval parameters
     *
     * @param array $params Input parameters.
     * @return array Validated parameters.
     * @throws \Exception If validation fails.
     */
    private function validate_retrieval_params( $params ) {
        $validated = [];
        
        // Validate sectionId
        if ( empty( $params['sectionId'] ) || ! preg_match( '/^section-[a-zA-Z0-9_-]+$/', $params['sectionId'] ) ) {
            throw new \Exception( __( 'Invalid section ID format', 'ai-page-composer' ) );
        }
        $validated['sectionId'] = sanitize_text_field( $params['sectionId'] );
        
        // Validate query
        if ( empty( $params['query'] ) || strlen( $params['query'] ) < 10 || strlen( $params['query'] ) > 500 ) {
            throw new \Exception( __( 'Query must be between 10 and 500 characters', 'ai-page-composer' ) );
        }
        $validated['query'] = sanitize_textarea_field( $params['query'] );
        
        // Validate namespaces
        $allowed_namespaces = [ 'content', 'products', 'docs', 'knowledge' ];
        $namespaces = $params['namespaces'] ?? [ 'content' ];
        
        if ( ! is_array( $namespaces ) ) {
            $namespaces = [ $namespaces ];
        }
        
        $validated_namespaces = [];
        foreach ( $namespaces as $namespace ) {
            $clean_namespace = sanitize_key( $namespace );
            if ( in_array( $clean_namespace, $allowed_namespaces, true ) ) {
                $validated_namespaces[] = $clean_namespace;
            }
        }
        
        if ( empty( $validated_namespaces ) ) {
            $validated_namespaces = [ 'content' ];
        }
        $validated['namespaces'] = $validated_namespaces;
        
        // Validate k (retrieval count)
        $k = absint( $params['k'] ?? 10 );
        if ( $k < 1 || $k > 50 ) {
            $k = 10;
        }
        $validated['k'] = $k;
        
        // Validate min_score
        $min_score = floatval( $params['min_score'] ?? 0.5 );
        if ( $min_score < 0.0 || $min_score > 1.0 ) {
            $min_score = 0.5;
        }
        $validated['min_score'] = $min_score;
        
        // Validate filters
        $validated['filters'] = $this->validate_filters( $params['filters'] ?? [] );
        
        return $validated;
    }

    /**
     * Validate filter parameters
     *
     * @param array $filters Input filters.
     * @return array Validated filters.
     */
    private function validate_filters( $filters ) {
        if ( ! is_array( $filters ) ) {
            return [];
        }
        
        $validated = [];
        
        // Post type filter
        if ( ! empty( $filters['post_type'] ) ) {
            $post_types = is_array( $filters['post_type'] ) ? $filters['post_type'] : [ $filters['post_type'] ];
            $valid_post_types = [];
            
            foreach ( $post_types as $post_type ) {
                $clean_type = sanitize_key( $post_type );
                if ( post_type_exists( $clean_type ) ) {
                    $valid_post_types[] = $clean_type;
                }
            }
            
            if ( ! empty( $valid_post_types ) ) {
                $validated['post_type'] = $valid_post_types;
            }
        }
        
        // Date range filter
        if ( ! empty( $filters['date_range'] ) && is_array( $filters['date_range'] ) ) {
            $date_range = [];
            
            if ( ! empty( $filters['date_range']['start'] ) ) {
                $start_date = sanitize_text_field( $filters['date_range']['start'] );
                if ( $this->validate_date_format( $start_date ) ) {
                    $date_range['start'] = $start_date;
                }
            }
            
            if ( ! empty( $filters['date_range']['end'] ) ) {
                $end_date = sanitize_text_field( $filters['date_range']['end'] );
                if ( $this->validate_date_format( $end_date ) ) {
                    $date_range['end'] = $end_date;
                }
            }
            
            if ( ! empty( $date_range ) ) {
                $validated['date_range'] = $date_range;
            }
        }
        
        // Language filter
        if ( ! empty( $filters['language'] ) ) {
            $language = sanitize_key( $filters['language'] );
            if ( strlen( $language ) === 2 ) { // ISO 639-1 language codes
                $validated['language'] = $language;
            }
        }
        
        // License filter
        if ( ! empty( $filters['license'] ) ) {
            $allowed_licenses = [ 'CC-BY', 'CC-BY-SA', 'CC-BY-NC', 'public-domain', 'fair-use', 'commercial' ];
            $licenses = is_array( $filters['license'] ) ? $filters['license'] : [ $filters['license'] ];
            $valid_licenses = [];
            
            foreach ( $licenses as $license ) {
                $clean_license = sanitize_key( $license );
                if ( in_array( $clean_license, $allowed_licenses, true ) ) {
                    $valid_licenses[] = $clean_license;
                }
            }
            
            if ( ! empty( $valid_licenses ) ) {
                $validated['license'] = $valid_licenses;
            }
        }
        
        // Author filter
        if ( ! empty( $filters['author'] ) ) {
            $authors = is_array( $filters['author'] ) ? $filters['author'] : [ $filters['author'] ];
            $valid_authors = array_map( 'absint', $authors );
            $valid_authors = array_filter( $valid_authors );
            
            if ( ! empty( $valid_authors ) ) {
                $validated['author'] = $valid_authors;
            }
        }
        
        // Exclude IDs filter
        if ( ! empty( $filters['exclude_ids'] ) ) {
            $exclude_ids = is_array( $filters['exclude_ids'] ) ? $filters['exclude_ids'] : [ $filters['exclude_ids'] ];
            $valid_ids = array_map( 'absint', $exclude_ids );
            $valid_ids = array_filter( $valid_ids );
            
            if ( ! empty( $valid_ids ) ) {
                $validated['exclude_ids'] = $valid_ids;
            }
        }
        
        return $validated;
    }

    /**
     * Validate date format (YYYY-MM-DD)
     *
     * @param string $date Date string to validate.
     * @return bool True if valid date format.
     */
    private function validate_date_format( $date ) {
        $d = \DateTime::createFromFormat( 'Y-m-d', $date );
        return $d && $d->format( 'Y-m-d' ) === $date;
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics.
     */
    public function get_cache_stats() {
        return $this->cache_manager->get_cache_stats();
    }
    
    /**
     * Get error statistics for monitoring
     *
     * @param string $timeframe Timeframe for statistics.
     * @return array Error statistics.
     */
    public function get_error_statistics( $timeframe = 'day' ) {
        return $this->error_handler->get_error_statistics( $timeframe );
    }
    
    /**
     * Get recent error logs
     *
     * @param array $filters Filter options.
     * @return array Error logs.
     */
    public function get_error_logs( $filters = [] ) {
        return $this->error_handler->get_error_logs( $filters );
    }
    
    /**
     * Clear error logs
     *
     * @param array $filters Optional filters for selective clearing.
     * @return bool Success status.
     */
    public function clear_error_logs( $filters = [] ) {
        return $this->error_handler->clear_error_logs( $filters );
    }
    
    /**
     * Flush MVDB cache
     *
     * @return bool Success status.
     */
    public function flush_cache() {
        return $this->cache_manager->flush_cache();
    }
    
    /**
     * Perform cache maintenance
     *
     * @return array Maintenance results.
     */
    public function perform_cache_maintenance() {
        return $this->cache_manager->perform_maintenance();
    }

    /**
     * Log error with context
     *
     * @param string $message Error message.
     * @param string $details Error details.
     * @param array  $context Request context.
     */
    private function log_error( $message, $details, $context = [] ) {
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            $log_entry = sprintf(
                '[AI Page Composer MVDB] ERROR: %s - Details: %s - Context: %s',
                $message,
                $details,
                wp_json_encode( $context )
            );
            error_log( $log_entry );
        }
    }
    
    /**
     * Log debug information
     *
     * @param string $message Debug message.
     * @param array  $context Debug context.
     */
    private function log_debug( $message, $context = [] ) {
        $settings = $this->settings_manager->get_all_settings();
        $debug_enabled = $settings['mvdb_settings']['enable_debug_logging'] ?? false;
        
        if ( $debug_enabled && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            $log_entry = sprintf(
                '[AI Page Composer MVDB] DEBUG: %s - Context: %s',
                $message,
                wp_json_encode( $context )
            );
            error_log( $log_entry );
        }
    }

    /**
     * Build similarity query for WP Engine Smart Search API
     *
     * @param array $params Validated parameters.
     * @return array GraphQL query and variables.
     */
    private function build_similarity_query( $params ) {
        $query = '
            query GetSimilarContent($query: String!, $fields: [FieldInput!]!, $limit: Int!, $offset: Int!, $filter: String, $minScore: Float, $namespaces: [String!]) {
                similarity(
                    input: {
                        nearest: {
                            text: $query,
                            fields: $fields
                        },
                        filter: $filter,
                        namespaces: $namespaces
                    },
                    limit: $limit,
                    offset: $offset,
                    minScore: $minScore
                ) {
                    total
                    docs {
                        id
                        score
                        data
                        metadata
                    }
                }
            }
        ';
        
        $variables = [
            'query' => $params['query'],
            'fields' => [
                [ 'name' => 'post_content', 'boost' => 1.0 ],
                [ 'name' => 'post_title', 'boost' => 1.2 ],
                [ 'name' => 'post_excerpt', 'boost' => 0.8 ]
            ],
            'limit' => $params['k'],
            'offset' => 0,
            'minScore' => $params['min_score'],
            'namespaces' => $params['namespaces']
        ];
        
        // Build filter string from filters
        $filter_parts = [];
        
        if ( ! empty( $params['filters']['post_type'] ) ) {
            $post_type_filters = array_map( function( $type ) {
                return "post_type:$type";
            }, $params['filters']['post_type'] );
            $filter_parts[] = '(' . implode( ' OR ', $post_type_filters ) . ')';
        }
        
        if ( ! empty( $params['filters']['exclude_ids'] ) ) {
            $exclude_filters = array_map( function( $id ) {
                return "NOT ID:$id";
            }, $params['filters']['exclude_ids'] );
            $filter_parts[] = '(' . implode( ' AND ', $exclude_filters ) . ')';
        }
        
        if ( ! empty( $params['filters']['date_range'] ) ) {
            $date_filters = [];
            if ( ! empty( $params['filters']['date_range']['start'] ) ) {
                $date_filters[] = "post_date:>={$params['filters']['date_range']['start']}";
            }
            if ( ! empty( $params['filters']['date_range']['end'] ) ) {
                $date_filters[] = "post_date:<={$params['filters']['date_range']['end']}";
            }
            if ( ! empty( $date_filters ) ) {
                $filter_parts[] = '(' . implode( ' AND ', $date_filters ) . ')';
            }
        }
        
        if ( ! empty( $params['filters']['language'] ) ) {
            $filter_parts[] = "language:{$params['filters']['language']}";
        }
        
        if ( ! empty( $params['filters']['license'] ) ) {
            $license_filters = array_map( function( $license ) {
                return "license:$license";
            }, $params['filters']['license'] );
            $filter_parts[] = '(' . implode( ' OR ', $license_filters ) . ')';
        }
        
        if ( ! empty( $params['filters']['author'] ) ) {
            $author_filters = array_map( function( $author_id ) {
                return "post_author:$author_id";
            }, $params['filters']['author'] );
            $filter_parts[] = '(' . implode( ' OR ', $author_filters ) . ')';
        }
        
        $variables['filter'] = ! empty( $filter_parts ) ? implode( ' AND ', $filter_parts ) : null;
        
        return [
            'query' => $query,
            'variables' => $variables
        ];
    }

    /**
     * Execute similarity request to WP Engine API
     *
     * @param array $query_data GraphQL query and variables.
     * @return array API response.
     * @throws \Exception If API request fails.
     */
    private function execute_similarity_request( $query_data ) {
        $settings = $this->settings_manager->get_all_settings();
        $timeout = $settings['mvdb_settings']['timeout_seconds'] ?? 30;
        $retry_attempts = $settings['mvdb_settings']['retry_attempts'] ?? 2;
        
        $request_body = [
            'query' => $query_data['query'],
            'variables' => $query_data['variables']
        ];
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->access_token,
            'Content-Type' => 'application/json',
            'User-Agent' => 'AI-Page-Composer/' . ( defined( 'AI_PAGE_COMPOSER_VERSION' ) ? AI_PAGE_COMPOSER_VERSION : '1.0.0' )
        ];
        
        $attempts = 0;
        $last_error = null;
        
        while ( $attempts <= $retry_attempts ) {
            try {
                $this->log_debug( 'Executing MVDB API request', [
                    'url' => $this->api_base_url,
                    'attempt' => $attempts + 1,
                    'variables' => $query_data['variables']
                ]);
                
                $response = wp_remote_post( $this->api_base_url, [
                    'headers' => $headers,
                    'body' => wp_json_encode( $request_body ),
                    'timeout' => $timeout,
                    'data_format' => 'body'
                ]);
                
                if ( is_wp_error( $response ) ) {
                    $last_error = $response->get_error_message();
                    throw new \Exception( 'WP Remote request failed: ' . $last_error );
                }
                
                $response_code = wp_remote_retrieve_response_code( $response );
                $response_body = wp_remote_retrieve_body( $response );
                
                if ( $response_code !== 200 ) {
                    $error_data = json_decode( $response_body, true );
                    $error_message = $error_data['error']['message'] ?? 'Unknown API error';
                    
                    // Handle specific error codes
                    if ( $response_code === 401 ) {
                        throw new \Exception( 'MVDB API authentication failed. Please check your access token.' );
                    } elseif ( $response_code === 429 ) {
                        // Rate limit - wait and retry
                        if ( $attempts < $retry_attempts ) {
                            sleep( min( pow( 2, $attempts ), 10 ) ); // Exponential backoff, max 10 seconds
                            $attempts++;
                            continue;
                        }
                        throw new \Exception( 'MVDB API rate limit exceeded. Please try again later.' );
                    } else {
                        throw new \Exception( "MVDB API error ($response_code): $error_message" );
                    }
                }
                
                $decoded_response = json_decode( $response_body, true );
                
                if ( json_last_error() !== JSON_ERROR_NONE ) {
                    throw new \Exception( 'Invalid JSON response from MVDB API: ' . json_last_error_msg() );
                }
                
                if ( isset( $decoded_response['errors'] ) ) {
                    $error_messages = array_map( function( $error ) {
                        return $error['message'] ?? 'Unknown GraphQL error';
                    }, $decoded_response['errors'] );
                    throw new \Exception( 'GraphQL errors: ' . implode( ', ', $error_messages ) );
                }
                
                $this->log_debug( 'MVDB API request successful', [
                    'response_code' => $response_code,
                    'total_docs' => $decoded_response['data']['similarity']['total'] ?? 0
                ]);
                
                return $decoded_response;
                
            } catch ( \Exception $e ) {
                $error_id = $this->error_handler->handle_api_error(
                    $e,
                    $request_body,
                    [ 'attempt' => $attempts + 1, 'max_attempts' => $retry_attempts ]
                );
                
                $last_error = $e->getMessage();
                $attempts++;
                
                if ( $attempts > $retry_attempts ) {
                    break;
                }
                
                // Wait before retry (except for authentication errors)
                if ( strpos( $last_error, 'authentication failed' ) === false ) {
                    sleep( min( $attempts, 5 ) );
                }
            }
        }
        
        $final_error = new \Exception( "MVDB API request failed after $retry_attempts retries: $last_error" );
        $this->error_handler->handle_api_error(
            $final_error,
            $request_body,
            [ 'total_attempts' => $attempts, 'final_error' => $last_error ]
        );
        
        throw $final_error;
    }

    /**
     * Process similarity response
     *
     * @param array $response API response.
     * @param array $params Validated parameters.
     * @return array Processed chunks.
     */
    private function process_similarity_response( $response, $params ) {
        $chunks = [];
        
        if ( ! isset( $response['data']['similarity']['docs'] ) || ! is_array( $response['data']['similarity']['docs'] ) ) {
            $this->log_debug( 'No documents found in MVDB response', $response );
            return $chunks;
        }
        
        $docs = $response['data']['similarity']['docs'];
        
        foreach ( $docs as $doc ) {
            try {
                $chunk = $this->format_chunk( $doc, $params );
                if ( $chunk ) {
                    $chunks[] = $chunk;
                }
            } catch ( \Exception $e ) {
                $this->log_error( 'Failed to process document chunk', $e->getMessage(), $doc );
                // Continue processing other documents
            }
        }
        
        $this->log_debug( 'Processed MVDB response', [
            'total_docs' => count( $docs ),
            'processed_chunks' => count( $chunks )
        ]);
        
        return $chunks;
    }
    
    /**
     * Format individual chunk from MVDB document
     *
     * @param array $doc Document from MVDB response.
     * @param array $params Validated parameters.
     * @return array|null Formatted chunk or null if invalid.
     */
    private function format_chunk( $doc, $params ) {
        if ( ! isset( $doc['id'], $doc['score'], $doc['data'] ) ) {
            return null;
        }
        
        $data = $doc['data'];
        $metadata = $doc['metadata'] ?? [];
        
        // Extract text content
        $text = $this->extract_text_content( $data );
        if ( empty( $text ) ) {
            return null;
        }
        
        // Generate chunk ID
        $chunk_id = 'chunk-' . sanitize_key( $doc['id'] );
        
        // Build chunk metadata
        $chunk_metadata = $this->build_chunk_metadata( $data, $metadata );
        
        return [
            'id' => $chunk_id,
            'text' => $text,
            'score' => floatval( $doc['score'] ),
            'metadata' => $chunk_metadata
        ];
    }
    
    /**
     * Extract text content from document data
     *
     * @param array $data Document data.
     * @return string Extracted text content.
     */
    private function extract_text_content( $data ) {
        $text_parts = [];
        
        // Extract title
        if ( ! empty( $data['post_title'] ) ) {
            $text_parts[] = sanitize_text_field( $data['post_title'] );
        }
        
        // Extract excerpt if available
        if ( ! empty( $data['post_excerpt'] ) ) {
            $text_parts[] = sanitize_textarea_field( $data['post_excerpt'] );
        }
        
        // Extract content
        if ( ! empty( $data['post_content'] ) ) {
            // Strip HTML tags and sanitize
            $content = wp_strip_all_tags( $data['post_content'] );
            $content = sanitize_textarea_field( $content );
            
            // Limit content length and add ellipsis if needed
            if ( strlen( $content ) > 500 ) {
                $content = substr( $content, 0, 497 ) . '...';
            }
            
            $text_parts[] = $content;
        }
        
        return implode( ' ', array_filter( $text_parts ) );
    }
    
    /**
     * Build comprehensive chunk metadata
     *
     * @param array $data Document data.
     * @param array $metadata Additional metadata.
     * @return array Chunk metadata.
     */
    private function build_chunk_metadata( $data, $metadata ) {
        $chunk_metadata = [];
        
        // Source URL
        if ( ! empty( $data['post_id'] ) ) {
            $post_id = absint( $data['post_id'] );
            $chunk_metadata['source_url'] = get_permalink( $post_id );
            $chunk_metadata['post_id'] = $post_id;
        } elseif ( ! empty( $metadata['source_url'] ) ) {
            $chunk_metadata['source_url'] = esc_url_raw( $metadata['source_url'] );
        }
        
        // Content type
        $chunk_metadata['type'] = $this->determine_content_type( $data );
        
        // Date
        if ( ! empty( $data['post_date'] ) ) {
            $chunk_metadata['date'] = sanitize_text_field( $data['post_date'] );
        }
        
        // License information
        if ( ! empty( $metadata['license'] ) ) {
            $chunk_metadata['license'] = sanitize_key( $metadata['license'] );
        } else {
            $chunk_metadata['license'] = 'unknown';
        }
        
        // Language
        if ( ! empty( $metadata['language'] ) ) {
            $chunk_metadata['language'] = sanitize_key( $metadata['language'] );
        } else {
            $chunk_metadata['language'] = 'en'; // Default to English
        }
        
        // Author information
        if ( ! empty( $data['post_author'] ) ) {
            $author_id = absint( $data['post_author'] );
            $author = get_userdata( $author_id );
            if ( $author ) {
                $chunk_metadata['author'] = $author->display_name;
            }
        } elseif ( ! empty( $metadata['author'] ) ) {
            $chunk_metadata['author'] = sanitize_text_field( $metadata['author'] );
        }
        
        // Categories
        if ( ! empty( $data['post_id'] ) ) {
            $categories = get_the_category( absint( $data['post_id'] ) );
            if ( $categories && ! is_wp_error( $categories ) ) {
                $chunk_metadata['categories'] = array_map( function( $cat ) {
                    return $cat->name;
                }, $categories );
            }
        } elseif ( ! empty( $metadata['categories'] ) && is_array( $metadata['categories'] ) ) {
            $chunk_metadata['categories'] = array_map( 'sanitize_text_field', $metadata['categories'] );
        }
        
        // Word count
        if ( ! empty( $data['post_content'] ) ) {
            $word_count = str_word_count( wp_strip_all_tags( $data['post_content'] ) );
            $chunk_metadata['word_count'] = $word_count;
        }
        
        // Excerpt
        if ( ! empty( $data['post_excerpt'] ) ) {
            $excerpt = sanitize_textarea_field( $data['post_excerpt'] );
            if ( strlen( $excerpt ) > 200 ) {
                $excerpt = substr( $excerpt, 0, 197 ) . '...';
            }
            $chunk_metadata['excerpt'] = $excerpt;
        } elseif ( ! empty( $data['post_content'] ) ) {
            // Generate excerpt from content
            $content = wp_strip_all_tags( $data['post_content'] );
            $excerpt = wp_trim_words( $content, 30, '...' );
            $chunk_metadata['excerpt'] = sanitize_textarea_field( $excerpt );
        }
        
        // Additional metadata
        if ( ! empty( $metadata['tags'] ) && is_array( $metadata['tags'] ) ) {
            $chunk_metadata['tags'] = array_map( 'sanitize_text_field', $metadata['tags'] );
        }
        
        return $chunk_metadata;
    }
    
    /**
     * Determine content type from document data
     *
     * @param array $data Document data.
     * @return string Content type.
     */
    private function determine_content_type( $data ) {
        if ( ! empty( $data['post_type'] ) ) {
            $post_type = sanitize_key( $data['post_type'] );
            
            // Map WordPress post types to readable types
            $type_map = [
                'post' => 'article',
                'page' => 'page',
                'product' => 'product',
                'attachment' => 'media',
                'revision' => 'revision',
                'nav_menu_item' => 'menu_item'
            ];
            
            return $type_map[ $post_type ] ?? $post_type;
        }
        
        return 'unknown';
    }

    /**
     * Apply quality filters
     *
     * @param array $chunks Processed chunks.
     * @param array $params Validated parameters.
     * @return array Filtered chunks.
     */
    private function apply_quality_filters( $chunks, $params ) {
        if ( empty( $chunks ) ) {
            return $chunks;
        }
        
        $filtered_chunks = [];
        $filters_applied = [];
        
        foreach ( $chunks as $chunk ) {
            // Apply minimum score filter (already handled by API, but double-check)
            if ( $chunk['score'] < $params['min_score'] ) {
                continue;
            }
            $filters_applied['min_score_filter'] = true;
            
            // Apply content quality filters
            if ( ! $this->passes_content_quality_check( $chunk ) ) {
                continue;
            }
            $filters_applied['content_quality_filter'] = true;
            
            // Apply license filters
            if ( ! $this->passes_license_filter( $chunk, $params ) ) {
                continue;
            }
            $filters_applied['license_filter'] = true;
            
            // Apply date range filters (post-processing)
            if ( ! $this->passes_date_filter( $chunk, $params ) ) {
                continue;
            }
            $filters_applied['date_range_filter'] = true;
            
            // Apply language filter (post-processing)
            if ( ! $this->passes_language_filter( $chunk, $params ) ) {
                continue;
            }
            $filters_applied['language_filter'] = true;
            
            // Apply content length filter
            if ( ! $this->passes_length_filter( $chunk ) ) {
                continue;
            }
            $filters_applied['length_filter'] = true;
            
            $filtered_chunks[] = $chunk;
        }
        
        $this->log_debug( 'Applied quality filters', [
            'original_count' => count( $chunks ),
            'filtered_count' => count( $filtered_chunks ),
            'filters_applied' => $filters_applied
        ]);
        
        // Sort by score (descending)
        usort( $filtered_chunks, function( $a, $b ) {
            return $b['score'] <=> $a['score'];
        });
        
        return $filtered_chunks;
    }
    
    /**
     * Check if chunk passes content quality requirements
     *
     * @param array $chunk Chunk data.
     * @return bool True if passes quality check.
     */
    private function passes_content_quality_check( $chunk ) {
        // Check minimum text length
        if ( strlen( $chunk['text'] ) < 50 ) {
            return false;
        }
        
        // Check for placeholder or low-quality content
        $low_quality_indicators = [
            'lorem ipsum',
            'placeholder',
            'coming soon',
            'under construction',
            'test content'
        ];
        
        $text_lower = strtolower( $chunk['text'] );
        foreach ( $low_quality_indicators as $indicator ) {
            if ( strpos( $text_lower, $indicator ) !== false ) {
                return false;
            }
        }
        
        // Check for excessive repetition
        $words = explode( ' ', $chunk['text'] );
        if ( count( $words ) > 10 ) {
            $word_counts = array_count_values( $words );
            $max_repetition = max( $word_counts );
            $repetition_ratio = $max_repetition / count( $words );
            
            if ( $repetition_ratio > 0.3 ) { // More than 30% repetition
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if chunk passes license filter
     *
     * @param array $chunk Chunk data.
     * @param array $params Validated parameters.
     * @return bool True if passes license filter.
     */
    private function passes_license_filter( $chunk, $params ) {
        if ( empty( $params['filters']['license'] ) ) {
            return true; // No license filter specified
        }
        
        $chunk_license = $chunk['metadata']['license'] ?? 'unknown';
        
        // Allow content with unknown license if 'commercial' is in allowed licenses
        if ( $chunk_license === 'unknown' && in_array( 'commercial', $params['filters']['license'], true ) ) {
            return true;
        }
        
        return in_array( $chunk_license, $params['filters']['license'], true );
    }
    
    /**
     * Check if chunk passes date filter
     *
     * @param array $chunk Chunk data.
     * @param array $params Validated parameters.
     * @return bool True if passes date filter.
     */
    private function passes_date_filter( $chunk, $params ) {
        if ( empty( $params['filters']['date_range'] ) ) {
            return true; // No date filter specified
        }
        
        $chunk_date = $chunk['metadata']['date'] ?? null;
        if ( ! $chunk_date ) {
            return true; // No date information available
        }
        
        $chunk_timestamp = strtotime( $chunk_date );
        if ( ! $chunk_timestamp ) {
            return true; // Invalid date format
        }
        
        $date_range = $params['filters']['date_range'];
        
        if ( ! empty( $date_range['start'] ) ) {
            $start_timestamp = strtotime( $date_range['start'] );
            if ( $start_timestamp && $chunk_timestamp < $start_timestamp ) {
                return false;
            }
        }
        
        if ( ! empty( $date_range['end'] ) ) {
            $end_timestamp = strtotime( $date_range['end'] . ' 23:59:59' ); // End of day
            if ( $end_timestamp && $chunk_timestamp > $end_timestamp ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if chunk passes language filter
     *
     * @param array $chunk Chunk data.
     * @param array $params Validated parameters.
     * @return bool True if passes language filter.
     */
    private function passes_language_filter( $chunk, $params ) {
        if ( empty( $params['filters']['language'] ) ) {
            return true; // No language filter specified
        }
        
        $chunk_language = $chunk['metadata']['language'] ?? 'en';
        
        return $chunk_language === $params['filters']['language'];
    }
    
    /**
     * Check if chunk passes length filter
     *
     * @param array $chunk Chunk data.
     * @return bool True if passes length filter.
     */
    private function passes_length_filter( $chunk ) {
        $text_length = strlen( $chunk['text'] );
        $word_count = str_word_count( $chunk['text'] );
        
        // Minimum requirements
        if ( $text_length < 50 || $word_count < 10 ) {
            return false;
        }
        
        // Maximum requirements (avoid overly long content)
        if ( $text_length > 2000 || $word_count > 400 ) {
            // Truncate but don't reject
            $chunk['text'] = wp_trim_words( $chunk['text'], 350, '...' );
        }
        
        return true;
    }

    /**
     * Calculate retrieval metrics
     *
     * @param array $chunks Filtered chunks.
     * @param array $params Validated parameters.
     * @param float $processing_time Processing time in milliseconds.
     * @return array Result with metrics.
     */
    private function calculate_retrieval_metrics( $chunks, $params, $processing_time ) {
        $total_retrieved = count( $chunks );
        $requested_k = $params['k'];
        
        // Calculate recall score (how many of requested results we got)
        $recall_score = $requested_k > 0 ? min( $total_retrieved / $requested_k, 1.0 ) : 0.0;
        
        // Calculate average score
        $average_score = 0.0;
        if ( $total_retrieved > 0 ) {
            $total_score = array_sum( array_column( $chunks, 'score' ) );
            $average_score = $total_score / $total_retrieved;
        }
        
        // Calculate score distribution
        $score_distribution = $this->calculate_score_distribution( $chunks );
        
        // Generate warnings
        $warnings = $this->generate_quality_warnings( $chunks, $params, $recall_score, $average_score );
        
        // Determine filters applied
        $filters_applied = $this->determine_filters_applied( $params );
        
        // Calculate diversity metrics
        $diversity_metrics = $this->calculate_diversity_metrics( $chunks );
        
        return [
            'chunks' => $chunks,
            'total_retrieved' => $total_retrieved,
            'total_available' => $this->estimate_total_available( $chunks, $params ),
            'recall_score' => round( $recall_score, 3 ),
            'average_score' => round( $average_score, 3 ),
            'score_distribution' => $score_distribution,
            'diversity_metrics' => $diversity_metrics,
            'query_hash' => hash( 'sha256', $params['query'] ),
            'processing_time_ms' => round( $processing_time, 2 ),
            'warnings' => $warnings,
            'filters_applied' => $filters_applied,
            'metadata' => [
                'api_version' => '1.0',
                'timestamp' => current_time( 'mysql' ),
                'section_id' => $params['sectionId'],
                'namespaces_used' => $params['namespaces'],
                'min_score_threshold' => $params['min_score']
            ]
        ];
    }
    
    /**
     * Calculate score distribution
     *
     * @param array $chunks Filtered chunks.
     * @return array Score distribution metrics.
     */
    private function calculate_score_distribution( $chunks ) {
        if ( empty( $chunks ) ) {
            return [
                'high_quality' => 0,
                'medium_quality' => 0,
                'low_quality' => 0,
                'score_range' => [ 'min' => 0, 'max' => 0 ]
            ];
        }
        
        $scores = array_column( $chunks, 'score' );
        $high_quality = 0; // score >= 0.8
        $medium_quality = 0; // score >= 0.6 && < 0.8
        $low_quality = 0; // score < 0.6
        
        foreach ( $scores as $score ) {
            if ( $score >= 0.8 ) {
                $high_quality++;
            } elseif ( $score >= 0.6 ) {
                $medium_quality++;
            } else {
                $low_quality++;
            }
        }
        
        return [
            'high_quality' => $high_quality,
            'medium_quality' => $medium_quality,
            'low_quality' => $low_quality,
            'score_range' => [
                'min' => round( min( $scores ), 3 ),
                'max' => round( max( $scores ), 3 )
            ]
        ];
    }
    
    /**
     * Generate quality warnings
     *
     * @param array $chunks Filtered chunks.
     * @param array $params Validated parameters.
     * @param float $recall_score Recall score.
     * @param float $average_score Average score.
     * @return array Array of warnings.
     */
    private function generate_quality_warnings( $chunks, $params, $recall_score, $average_score ) {
        $warnings = [];
        
        // Low recall warning
        if ( $recall_score < 0.8 ) {
            $warnings[] = [
                'type' => 'low_recall',
                'message' => sprintf(
                    __( 'Recall score below threshold (%.3f < 0.8)', 'ai-page-composer' ),
                    $recall_score
                ),
                'suggestion' => __( 'Consider broadening search terms or lowering min_score', 'ai-page-composer' )
            ];
        }
        
        // Low average score warning
        if ( $average_score < 0.7 ) {
            $warnings[] = [
                'type' => 'low_average_score',
                'message' => sprintf(
                    __( 'Average relevance score is low (%.3f)', 'ai-page-composer' ),
                    $average_score
                ),
                'suggestion' => __( 'Try refining your search query for better relevance', 'ai-page-composer' )
            ];
        }
        
        // No results warning
        if ( empty( $chunks ) ) {
            $warnings[] = [
                'type' => 'no_results',
                'message' => __( 'No relevant content found', 'ai-page-composer' ),
                'suggestion' => __( 'Try broader search terms or check MVDB configuration', 'ai-page-composer' )
            ];
        }
        
        // Limited diversity warning
        $diversity_metrics = $this->calculate_diversity_metrics( $chunks );
        if ( $diversity_metrics['content_type_diversity'] < 0.3 && count( $chunks ) > 3 ) {
            $warnings[] = [
                'type' => 'low_diversity',
                'message' => __( 'Results show limited content diversity', 'ai-page-composer' ),
                'suggestion' => __( 'Consider expanding namespaces or adjusting filters', 'ai-page-composer' )
            ];
        }
        
        // Excessive filtering warning
        if ( ! empty( $params['filters'] ) && count( $chunks ) < $params['k'] * 0.5 ) {
            $warnings[] = [
                'type' => 'excessive_filtering',
                'message' => __( 'Many results were filtered out', 'ai-page-composer' ),
                'suggestion' => __( 'Consider relaxing filter criteria', 'ai-page-composer' )
            ];
        }
        
        return $warnings;
    }
    
    /**
     * Calculate diversity metrics
     *
     * @param array $chunks Filtered chunks.
     * @return array Diversity metrics.
     */
    private function calculate_diversity_metrics( $chunks ) {
        if ( empty( $chunks ) ) {
            return [
                'content_type_diversity' => 0,
                'source_diversity' => 0,
                'temporal_diversity' => 0
            ];
        }
        
        $total_chunks = count( $chunks );
        
        // Content type diversity
        $content_types = [];
        foreach ( $chunks as $chunk ) {
            $type = $chunk['metadata']['type'] ?? 'unknown';
            $content_types[ $type ] = ( $content_types[ $type ] ?? 0 ) + 1;
        }
        $content_type_diversity = count( $content_types ) / max( $total_chunks, 1 );
        
        // Source diversity (based on post_id or source_url)
        $sources = [];
        foreach ( $chunks as $chunk ) {
            $source = $chunk['metadata']['post_id'] ?? $chunk['metadata']['source_url'] ?? 'unknown';
            $sources[ $source ] = true;
        }
        $source_diversity = count( $sources ) / max( $total_chunks, 1 );
        
        // Temporal diversity (based on publication dates)
        $dates = [];
        foreach ( $chunks as $chunk ) {
            if ( ! empty( $chunk['metadata']['date'] ) ) {
                $year_month = substr( $chunk['metadata']['date'], 0, 7 ); // YYYY-MM
                $dates[ $year_month ] = true;
            }
        }
        $temporal_diversity = count( $dates ) > 0 ? count( $dates ) / max( $total_chunks, 1 ) : 0;
        
        return [
            'content_type_diversity' => round( $content_type_diversity, 3 ),
            'source_diversity' => round( $source_diversity, 3 ),
            'temporal_diversity' => round( $temporal_diversity, 3 )
        ];
    }
    
    /**
     * Determine which filters were applied
     *
     * @param array $params Validated parameters.
     * @return array Filters applied status.
     */
    private function determine_filters_applied( $params ) {
        return [
            'post_type_filter' => ! empty( $params['filters']['post_type'] ),
            'date_range_filter' => ! empty( $params['filters']['date_range'] ),
            'language_filter' => ! empty( $params['filters']['language'] ),
            'license_filter' => ! empty( $params['filters']['license'] ),
            'author_filter' => ! empty( $params['filters']['author'] ),
            'exclude_ids_filter' => ! empty( $params['filters']['exclude_ids'] ),
            'min_score_filter' => $params['min_score'] > 0.0,
            'namespace_filter' => count( $params['namespaces'] ) < 4 // Less than all available namespaces
        ];
    }
    
    /**
     * Estimate total available results
     *
     * @param array $chunks Retrieved chunks.
     * @param array $params Validated parameters.
     * @return int Estimated total available.
     */
    private function estimate_total_available( $chunks, $params ) {
        // Simple estimation based on retrieved results
        $retrieved_count = count( $chunks );
        $requested_count = $params['k'];
        
        if ( $retrieved_count < $requested_count ) {
            // We got fewer than requested, likely this is all available
            return $retrieved_count;
        }
        
        // We got the full requested amount, estimate there might be more
        // This is a rough estimate - in reality, you'd use the API's total field
        return (int) ( $retrieved_count * 1.5 );
    }
}