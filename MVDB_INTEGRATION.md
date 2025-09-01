# MVDB Retrieval Pipeline Documentation

## Overview

The MVDB (Managed Vector Database) Retrieval Pipeline provides intelligent content retrieval functionality for the AI Page Composer WordPress plugin. It integrates with WP Engine's Smart Search vector database to enable context-aware content generation through semantic search capabilities.

## Architecture

### Components

1. **MVDB_Manager** - Core vector database operations manager
2. **API_Manager** - REST API endpoint handler with `/retrieve` endpoint
3. **Settings_Manager** - Enhanced with MVDB configuration options
4. **Quality Assurance** - Comprehensive filtering and metrics system

### Data Flow

```
Client Request → API Validation → MVDB Manager → WP Engine API → Response Processing → Quality Filtering → Metrics Calculation → Cached Response
```

## API Reference

### POST `/wp-json/ai-composer/v1/retrieve`

Retrieves contextually relevant content chunks from the vector database.

#### Authentication
- **Required**: WordPress admin capabilities (`manage_options`)
- **Method**: WordPress cookie-based authentication

#### Request Parameters

| Parameter | Type | Required | Description | Validation |
|-----------|------|----------|-------------|------------|
| `sectionId` | string | ✓ | Section identifier | `/^section-[a-zA-Z0-9_-]+$/` |
| `query` | string | ✓ | Search query text | 10-500 characters |
| `namespaces` | array | ✗ | Target namespaces | `['content', 'products', 'docs', 'knowledge']` |
| `k` | integer | ✗ | Number of results | 1-50, default: 10 |
| `min_score` | float | ✗ | Minimum relevance score | 0.0-1.0, default: 0.5 |
| `filters` | object | ✗ | Additional filters | See filters schema below |

#### Filters Schema

```json
{
  "post_type": ["post", "page", "product"],
  "date_range": {
    "start": "2023-01-01",
    "end": "2024-12-31"
  },
  "language": "en",
  "license": ["CC-BY", "CC-BY-SA", "public-domain"],
  "author": [1, 5, 10],
  "exclude_ids": [123, 456, 789]
}
```

#### Example Request

```bash
curl -X POST "https://yoursite.com/wp-json/ai-composer/v1/retrieve" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: your-nonce-here" \
  -d '{
    "sectionId": "section-hero-1",
    "query": "WordPress development best practices",
    "namespaces": ["content", "docs"],
    "k": 15,
    "min_score": 0.7,
    "filters": {
      "post_type": ["post", "page"],
      "language": "en",
      "license": ["CC-BY", "CC-BY-SA"]
    }
  }'
```

#### Response Schema

```json
{
  "chunks": [
    {
      "id": "chunk-123",
      "text": "Relevant content excerpt...",
      "score": 0.89,
      "metadata": {
        "source_url": "https://example.com/post-slug",
        "type": "article",
        "date": "2024-01-15T10:30:00Z",
        "license": "CC-BY-4.0",
        "language": "en",
        "post_id": 456,
        "author": "John Doe",
        "categories": ["Technology", "WordPress"],
        "word_count": 150,
        "excerpt": "Brief content summary..."
      }
    }
  ],
  "total_retrieved": 8,
  "total_available": 25,
  "recall_score": 0.75,
  "average_score": 0.82,
  "score_distribution": {
    "high_quality": 5,
    "medium_quality": 2,
    "low_quality": 1,
    "score_range": {"min": 0.65, "max": 0.94}
  },
  "diversity_metrics": {
    "content_type_diversity": 0.75,
    "source_diversity": 0.88,
    "temporal_diversity": 0.40
  },
  "query_hash": "sha256:abc123...",
  "processing_time_ms": 234,
  "cached": false,
  "cache_timestamp": "2024-01-15 10:30:00",
  "warnings": [
    {
      "type": "low_recall",
      "message": "Recall score below threshold (0.75 < 0.8)",
      "suggestion": "Consider broadening search terms or lowering min_score"
    }
  ],
  "filters_applied": {
    "license_filter": true,
    "min_score_filter": true,
    "date_range_filter": false
  },
  "metadata": {
    "api_version": "1.0",
    "timestamp": "2024-01-15 10:30:00",
    "section_id": "section-hero-1",
    "namespaces_used": ["content", "docs"],
    "min_score_threshold": 0.7
  }
}
```

#### Error Responses

**503 Service Unavailable** - MVDB service not configured
```json
{
  "code": "mvdb_unavailable",
  "message": "MVDB service is not available. Please check your configuration.",
  "data": {"status": 503}
}
```

**400 Bad Request** - Invalid parameters
```json
{
  "code": "rest_invalid_param",
  "message": "Invalid parameter(s): sectionId",
  "data": {"status": 400}
}
```

**500 Internal Server Error** - Retrieval failure
```json
{
  "code": "retrieval_failed",
  "message": "MVDB API authentication failed. Please check your access token.",
  "data": {"status": 500}
}
```

## Configuration

### WordPress Admin Settings

Navigate to **AI Composer → Settings → Vector Database Configuration**:

1. **MVDB API URL**: WP Engine Smart Search endpoint
   - Default: `https://api.wpengine.com/smart-search/v1`
   
2. **MVDB Access Token**: Authentication token from WP Engine
   - Required for API access
   
3. **Cache TTL**: Response cache duration in seconds
   - Default: 3600 (1 hour)
   - Range: 300-86400 seconds
   
4. **Request Timeout**: API request timeout in seconds
   - Default: 30 seconds
   - Range: 5-120 seconds
   
5. **Debug Logging**: Enable detailed request/response logging
   - Default: Disabled
   - Requires `WP_DEBUG_LOG` enabled

### Programmatic Configuration

```php
// Update MVDB settings programmatically
$settings_manager = new AIPageComposer\Admin\Settings_Manager();
$settings_manager->update_setting( 'mvdb_settings', 'api_url', 'https://custom-endpoint.com' );
$settings_manager->update_setting( 'mvdb_settings', 'access_token', 'your-token-here' );
```

## Quality Assurance Features

### Automatic Filtering

1. **Content Quality Checks**
   - Minimum text length (50 characters)
   - Low-quality content detection (placeholders, test content)
   - Excessive repetition filtering (>30% word repetition)

2. **License Filtering**
   - Respects specified license requirements
   - Supports CC licenses, public domain, commercial use

3. **Language Filtering**
   - ISO 639-1 language code validation
   - Content language matching

4. **Date Range Filtering**
   - Content publication date constraints
   - Flexible start/end date specification

### Metrics and Monitoring

1. **Retrieval Metrics**
   - Recall score (percentage of requested results retrieved)
   - Average relevance score
   - Score distribution analysis

2. **Diversity Metrics**
   - Content type diversity
   - Source diversity (different authors/sites)
   - Temporal diversity (publication dates)

3. **Performance Metrics**
   - Processing time measurement
   - Cache hit/miss tracking
   - API response times

4. **Quality Warnings**
   - Low recall alerts
   - Poor relevance warnings
   - Excessive filtering notifications
   - Low diversity alerts

## Caching System

### Cache Strategies

1. **Query-based Caching**
   - SHA256 hash of query parameters
   - Automatic cache invalidation
   - Configurable TTL

2. **Cache Metadata**
   - Cache timestamp tracking
   - Hit/miss ratio monitoring
   - Cache performance metrics

### Cache Management

```php
// Clear MVDB cache programmatically
wp_cache_flush_group( 'ai_composer_mvdb' );

// Get cache statistics
$cache_stats = wp_cache_get_stats( 'ai_composer_mvdb' );
```

## Integration Examples

### Basic Content Retrieval

```javascript
// JavaScript example for admin interface
fetch('/wp-json/ai-composer/v1/retrieve', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce
  },
  body: JSON.stringify({
    sectionId: 'section-hero-1',
    query: 'WordPress development best practices',
    k: 10
  })
})
.then(response => response.json())
.then(data => {
  console.log('Retrieved chunks:', data.chunks);
  console.log('Quality metrics:', data.recall_score);
});
```

### Advanced Filtering

```php
// PHP example for server-side integration
$api_manager = new AIPageComposer\API\API_Manager( $settings_manager );
$mvdb_manager = new AIPageComposer\API\MVDB_Manager( $settings_manager );

$params = [
    'sectionId' => 'section-content-1',
    'query' => 'Advanced WordPress security techniques',
    'namespaces' => ['content', 'docs'],
    'k' => 20,
    'min_score' => 0.8,
    'filters' => [
        'post_type' => ['post'],
        'date_range' => [
            'start' => '2023-01-01',
            'end' => '2024-12-31'
        ],
        'language' => 'en',
        'license' => ['CC-BY', 'CC-BY-SA', 'public-domain']
    ]
];

try {
    $result = $mvdb_manager->retrieve_context( $params );
    
    foreach ( $result['chunks'] as $chunk ) {
        echo "Score: {$chunk['score']}\n";
        echo "Text: {$chunk['text']}\n";
        echo "Source: {$chunk['metadata']['source_url']}\n\n";
    }
    
    echo "Quality Metrics:\n";
    echo "Recall Score: {$result['recall_score']}\n";
    echo "Average Score: {$result['average_score']}\n";
    echo "Processing Time: {$result['processing_time_ms']}ms\n";
    
} catch ( Exception $e ) {
    error_log( 'MVDB retrieval failed: ' . $e->getMessage() );
}
```

## Troubleshooting

### Common Issues

1. **503 Service Unavailable**
   - Check MVDB API URL and access token configuration
   - Verify WP Engine Smart Search service status
   - Review WordPress debug logs

2. **Authentication Errors**
   - Validate access token format and permissions
   - Check token expiration
   - Verify API endpoint URL

3. **Poor Retrieval Quality**
   - Adjust `min_score` threshold
   - Broaden search namespaces
   - Refine query terminology
   - Review filter constraints

4. **Slow Performance**
   - Check API timeout settings
   - Monitor cache hit rates
   - Review network connectivity
   - Optimize query complexity

### Debug Mode

Enable debug logging to troubleshoot issues:

```php
// In wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );

// Enable MVDB debug logging in settings
$settings = get_option( 'ai_composer_settings' );
$settings['mvdb_settings']['enable_debug_logging'] = true;
update_option( 'ai_composer_settings', $settings );
```

Debug logs location: `/wp-content/debug.log`

### Performance Optimization

1. **Cache Tuning**
   - Increase cache TTL for stable content
   - Use object caching plugins (Redis, Memcached)
   - Monitor cache hit ratios

2. **Query Optimization**
   - Use specific namespaces
   - Set appropriate `k` values
   - Implement reasonable `min_score` thresholds

3. **Network Optimization**
   - Use CDN for API requests
   - Implement request batching
   - Monitor API rate limits

## Security Considerations

1. **Authentication**
   - WordPress nonce validation required
   - Admin capability checking enforced
   - No public API access

2. **Input Validation**
   - All parameters sanitized and validated
   - SQL injection prevention
   - XSS protection on output

3. **Rate Limiting**
   - WordPress built-in rate limiting
   - API provider rate limits respected
   - Retry logic with exponential backoff

4. **Data Protection**
   - Access tokens securely stored
   - Sensitive data masked in logs
   - HTTPS required for API calls

## Migration and Updates

### Version Compatibility

- **WordPress**: 6.0+ required
- **PHP**: 7.4+ required
- **WP Engine Smart Search**: API v1

### Database Schema Updates

The MVDB integration uses WordPress options table for configuration storage. No additional database tables are required.

### Backwards Compatibility

The MVDB integration is designed to be backward compatible with existing AI Page Composer installations. If MVDB is not configured, the system gracefully degrades to use alternative content sources.

---

For additional support and advanced configuration options, please refer to the main AI Page Composer documentation or contact support.