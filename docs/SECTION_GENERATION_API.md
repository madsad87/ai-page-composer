# Section Generation API Documentation

## Overview

The Section Generation API provides comprehensive endpoints for generating website content sections with AI-powered content creation, block-aware formatting, and integrated media management. This system supports three generation modes (Grounded/Hybrid/Generative) and includes advanced features like citation management, cost tracking, and intelligent caching.

## API Endpoints

### POST /wp-json/ai-composer/v1/section

Generates content sections with block-aware output and citation management.

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sectionId` | string | Yes | Section identifier from outline |
| `content_brief` | string | Yes | Content generation brief (min 10 chars) |
| `mode` | string | No | Generation mode: `grounded`, `hybrid`, `generative` (default: `hybrid`) |
| `alpha` | number | No | Hybrid mode alpha weight 0.0-1.0 (default: 0.7) |
| `block_preferences` | object | No | Block type preferences |
| `image_requirements` | object | No | Image generation settings |
| `citation_settings` | object | No | Citation configuration |

#### Block Preferences Structure

```json
{
  \"preferred_plugin\": \"kadence_blocks\",
  \"section_type\": \"hero\",
  \"fallback_blocks\": [\"core/cover\", \"core/group\"],
  \"custom_attributes\": {
    \"padding\": [\"20\", \"20\", \"20\", \"20\"],
    \"backgroundOverlay\": {\"color\": \"rgba(0,0,0,0.3)\"}
  }
}
```

#### Image Requirements Structure

```json
{
  \"policy\": \"required\",
  \"style\": \"photographic\",
  \"alt_text_required\": true,
  \"license_compliance\": [\"CC-BY\", \"public-domain\"]
}
```

#### Citation Settings Structure

```json
{
  \"enabled\": true,
  \"style\": \"inline\",
  \"include_mvdb_refs\": true,
  \"format\": \"text\"
}
```

#### Response Format

```json
{
  \"sectionId\": \"hero-1\",
  \"content\": {
    \"html\": \"<div class=\\\"wp-block-kadence-rowlayout\\\">...</div>\",
    \"json\": {
      \"blockName\": \"kadence/rowlayout\",
      \"attrs\": {
        \"uniqueID\": \"hero-123\",
        \"backgroundImg\": [{\"url\": \"...\", \"id\": 456}]
      },
      \"innerBlocks\": []
    }
  },
  \"blockType\": {
    \"name\": \"kadence/rowlayout\",
    \"plugin\": \"kadence_blocks\", 
    \"namespace\": \"kadence\",
    \"fallback_used\": false
  },
  \"citations\": [
    {
      \"id\": \"cite-1\",
      \"text\": \"According to industry research...\",
      \"source\": \"https://example.com/research\",
      \"type\": \"inline\",
      \"mvdb_chunk_id\": \"chunk-abc123\"
    }
  ],
  \"mediaId\": 456,
  \"media\": {
    \"id\": 456,
    \"url\": \"https://site.com/wp-content/uploads/hero-bg.jpg\",
    \"alt\": \"Modern office workspace with laptops\",
    \"license\": \"CC-BY-4.0\",
    \"attribution\": \"Photo by John Doe\"
  },
  \"generation_metadata\": {
    \"mode\": \"hybrid\",
    \"alpha\": 0.7,
    \"word_count\": 285,
    \"token_count\": 420,
    \"cost_usd\": 0.012,
    \"processing_time_ms\": 3250,
    \"cache_hit\": false
  }
}
```

### POST /wp-json/ai-composer/v1/image

Generates or searches for images with WordPress Media Library integration.

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `prompt` | string | Yes | Image description/search query |
| `style` | string | No | Image style: `photographic`, `illustration`, `abstract` (default: `photographic`) |
| `source` | string | No | Generation source: `generate`, `search`, `upload` (default: `generate`) |
| `alt_text` | string | No | Custom alt text |
| `license_filter` | array | No | Acceptable license types |
| `dimensions` | object | No | Image size requirements |

#### Response Format

```json
{
  \"mediaId\": 789,
  \"url\": \"https://site.com/wp-content/uploads/generated-image.jpg\",
  \"alt\": \"Professional team collaboration in modern office\",
  \"license\": \"Generated Content\",
  \"attribution\": \"AI Generated via DALL-E\",
  \"dimensions\": {
    \"width\": 1920,
    \"height\": 1080
  },
  \"file_size\": 245760,
  \"metadata\": {
    \"source\": \"generated\",
    \"prompt\": \"team collaboration modern office\",
    \"style\": \"photographic\",
    \"cost_usd\": 0.04,
    \"generation_time_ms\": 12000
  }
}
```

## Generation Modes

### Grounded Mode
Content is generated strictly based on retrieved context from the MVDB vector database. Ensures high factual accuracy and relevance to existing knowledge base.

### Hybrid Mode (Default)
Balances retrieved context with creative generation using the alpha parameter:
- Alpha closer to 1.0: More reliance on retrieved context
- Alpha closer to 0.0: More creative generation
- Default alpha: 0.7 (70% context, 30% creative)

### Generative Mode
Pure AI generation without context retrieval. Provides maximum creativity and flexibility.

## Block-Aware Content Generation

The system automatically detects available block plugins and selects the most appropriate block type based on:

1. **User Preferences**: Explicitly preferred plugin/block
2. **Plugin Detection**: Active block plugins and their capabilities
3. **Section Type**: Content type requirements (hero, testimonial, etc.)
4. **Fallback Strategy**: Graceful degradation to core blocks

### Supported Block Plugins

- **Kadence Blocks**: Advanced layout and design blocks
- **Genesis Blocks**: Professional content blocks
- **Stackable**: Modern design blocks  
- **Ultimate Addons for Gutenberg**: Feature-rich blocks
- **WordPress Core**: Standard Gutenberg blocks

### Section Types

- `hero`: Hero/banner sections
- `content`: General content sections
- `testimonial`: Customer testimonials
- `pricing`: Pricing tables/lists
- `team`: Team member profiles
- `faq`: Frequently asked questions
- `cta`: Call-to-action sections
- `feature`: Feature highlights

## Citation Management

The system automatically extracts and formats citations from generated content:

### Citation Types
- **Inline**: `[1]`, `[2]` style references
- **Parenthetical**: `(Source, 2024)` style
- **Narrative**: \"According to Source...\" style

### Citation Formats
- **Text**: Plain text citations
- **HTML**: Formatted with links and accessibility attributes
- **Footnotes**: Numbered references with bibliography
- **Bibliography**: Full source listings

## Image Integration

### Image Sources
- **DALL-E**: AI-generated images
- **Unsplash**: Free stock photography
- **Upload**: Direct file uploads

### Image Styles
- `photographic`: Realistic photography
- `illustration`: Digital illustrations
- `abstract`: Abstract art designs
- `minimalist`: Clean, simple designs

### License Compliance
- Automatic license detection and filtering
- Support for Creative Commons, public domain, and royalty-free
- Proper attribution and metadata storage

## Caching System

### Cache Features
- **Intelligent Key Generation**: Based on section parameters
- **Compression**: Automatic data compression for large entries
- **TTL Management**: Configurable time-to-live settings
- **Multi-tier**: Object cache with database fallback
- **Statistics**: Hit rates, performance metrics

### Cache Configuration
```php
'cache_settings' => [
    'enable_section_cache' => true,
    'section_cache_ttl' => 3600, // 1 hour
    'max_cache_size_mb' => 100,
    'cache_cleanup_enabled' => true,
    'cache_compression' => true
]
```

## Cost Tracking

### Cost Management Features
- Real-time cost tracking per operation
- Daily and monthly budget monitoring
- Token usage tracking
- API provider cost calculation
- Alert system for budget limits

### Cost Breakdown
- **Content Generation**: Based on token usage
- **Image Generation**: Per image/API call
- **MVDB Retrieval**: Per query operation

## Error Handling

### Common Error Responses

#### 400 Bad Request
```json
{
  \"code\": \"invalid_parameters\",
  \"message\": \"Invalid section parameters provided\",
  \"data\": {
    \"status\": 400
  }
}
```

#### 403 Forbidden
```json
{
  \"code\": \"insufficient_permissions\",
  \"message\": \"User does not have required permissions\",
  \"data\": {
    \"status\": 403
  }
}
```

#### 500 Internal Server Error
```json
{
  \"code\": \"section_generation_failed\",
  \"message\": \"Content generation failed: API error\",
  \"data\": {
    \"status\": 500
  }
}
```

## Usage Examples

### Basic Section Generation

```javascript
const response = await fetch('/wp-json/ai-composer/v1/section', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce
  },
  body: JSON.stringify({
    sectionId: 'hero-main',
    content_brief: 'Create a compelling hero section for a SaaS product that helps teams collaborate more effectively',
    mode: 'hybrid',
    alpha: 0.8,
    block_preferences: {
      preferred_plugin: 'kadence_blocks',
      section_type: 'hero'
    },
    image_requirements: {
      policy: 'required',
      style: 'photographic'
    }
  })
});

const data = await response.json();
console.log('Generated section:', data);
```

### Image Generation

```javascript
const imageResponse = await fetch('/wp-json/ai-composer/v1/image', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce
  },
  body: JSON.stringify({
    prompt: 'Modern office space with diverse team collaborating',
    style: 'photographic',
    source: 'generate',
    dimensions: {
      width: 1200,
      height: 600,
      aspect_ratio: '2:1'
    }
  })
});

const imageData = await imageResponse.json();
console.log('Generated image:', imageData);
```

### PHP Integration

```php
// Generate section content
$section_controller = new \\AIPageComposer\\API\\Section_Controller();

$request = new WP_REST_Request('POST', '/ai-composer/v1/section');
$request->set_param('sectionId', 'testimonials-1');
$request->set_param('content_brief', 'Customer testimonials highlighting the key benefits of our product');
$request->set_param('mode', 'grounded');
$request->set_param('block_preferences', [
    'section_type' => 'testimonial',
    'preferred_plugin' => 'kadence_blocks'
]);

$response = $section_controller->generate_section($request);
$section_data = $response->get_data();

// Use generated content
echo $section_data['content']['html'];
```

## Performance Considerations

### Optimization Tips
1. **Use Caching**: Enable section caching for frequently requested content
2. **Batch Operations**: Generate multiple sections in sequence for better efficiency
3. **Monitor Costs**: Set appropriate budget limits and monitor token usage
4. **Cache Strategy**: Use longer TTL for stable content, shorter for dynamic content
5. **Fallback Blocks**: Configure appropriate fallback blocks for better compatibility

### Rate Limits
- API calls are subject to WordPress REST API rate limiting
- External API providers (OpenAI, DALL-E) have their own rate limits
- Consider implementing request queuing for high-volume usage

## Security

### Authentication
- Requires valid WordPress nonce for AJAX requests
- REST API uses WordPress authentication
- User must have `edit_posts` capability

### Input Sanitization
- All input parameters are sanitized using WordPress functions
- SQL injection protection through prepared statements
- XSS prevention through proper output escaping

### Data Privacy
- Generated content may be cached locally
- API keys and sensitive settings are properly secured
- User content is not shared with external services beyond generation

## Troubleshooting

### Common Issues

1. **Block Not Found**: Ensure target block plugin is active
2. **API Key Issues**: Verify API keys are correctly configured
3. **Permission Errors**: Check user capabilities and nonces
4. **Cache Issues**: Clear cache if seeing stale content
5. **Memory Limits**: Increase PHP memory limit for large generations

### Debug Mode
Enable WordPress debug logging to troubleshoot issues:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Log entries will appear in `/wp-content/debug.log` with prefix `[AI Composer]`.