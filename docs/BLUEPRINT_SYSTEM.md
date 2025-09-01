# AI Blueprint System Documentation

## Overview

The AI Blueprint System is a comprehensive framework for managing content generation templates in the AI Page Composer plugin. It provides a structured way to define, validate, and manage AI-powered content generation configurations through WordPress Custom Post Types.

## Key Features

- **JSON Schema Validation**: Comprehensive validation against predefined schemas
- **Custom Post Type Management**: WordPress-native blueprint storage and management
- **REST API Integration**: Full REST API support for AJAX operations
- **Visual Admin Interface**: User-friendly meta boxes and form interfaces
- **Import/Export Functionality**: Blueprint sharing and backup capabilities
- **Block Preferences**: Integration with block detection and preferences system
- **Validation & Preview**: Real-time validation and cost estimation

## Architecture

### Core Components

1. **Schema_Processor** (`includes/blueprints/schema-processor.php`)
   - JSON schema definition and validation
   - Data sanitization and default value application
   - Section and global settings validation

2. **Blueprint_Manager** (`includes/blueprints/blueprint-manager.php`)
   - Custom Post Type registration and management
   - CRUD operations with validation
   - Import/export functionality
   - Blueprint duplication

3. **Blueprint_Meta_Boxes** (`includes/blueprints/blueprint-meta-boxes.php`)
   - Admin interface meta box registration
   - Form rendering and data handling
   - Integration with schema processor

4. **Blueprint_REST_Controller** (`includes/blueprints/blueprint-rest-controller.php`)
   - REST API endpoints for AJAX operations
   - Schema validation endpoints
   - Preview generation and cost estimation

### Database Schema

Blueprints are stored as WordPress Custom Post Types (`ai_blueprint`) with the following meta fields:

- `_ai_blueprint_schema`: Complete blueprint configuration
- `_ai_blueprint_sections`: Section configurations array
- `_ai_blueprint_global_settings`: Global generation settings
- `_ai_blueprint_metadata`: Blueprint metadata (category, difficulty, etc.)
- `_ai_blueprint_validation_errors`: Validation error storage
- Cache fields for efficient querying:
  - `_ai_blueprint_category`
  - `_ai_blueprint_section_count`
  - `_ai_blueprint_difficulty`
  - `_ai_blueprint_generation_mode`
  - `_ai_blueprint_estimated_time`

## Blueprint Schema Definition

### Complete Schema Structure

```json
{
  "sections": [
    {
      "id": "unique-section-id",
      "type": "content|hero|cta|columns|list|quote|gallery|faq|testimonial|pricing|team|custom",
      "heading": "Section Heading",
      "heading_level": 2,
      "word_target": 150,
      "media_policy": "required|optional|none",
      "internal_links": 2,
      "citations_required": true,
      "tone": "professional|casual|technical|friendly|authoritative",
      "allowed_blocks": ["block-type-1", "block-type-2"],
      "block_preferences": {
        "preferred_plugin": "auto|core|genesis_blocks|kadence_blocks|stackable|ultimate_addons|blocksy",
        "primary_block": "block-identifier",
        "fallback_blocks": ["fallback-1", "fallback-2"],
        "pattern_preference": "pattern-name",
        "custom_attributes": {}
      }
    }
  ],
  "global_settings": {
    "generation_mode": "grounded|hybrid|generative",
    "hybrid_alpha": 0.7,
    "mvdb_namespaces": ["content", "products", "docs", "knowledge"],
    "max_tokens_per_section": 1000,
    "image_generation_enabled": true,
    "seo_optimization": true,
    "accessibility_checks": true,
    "cost_limit_usd": 5.0
  },
  "metadata": {
    "version": "1.0.0",
    "description": "Blueprint description",
    "tags": ["tag1", "tag2"],
    "category": "landing-page|blog-post|product-page|about-page|contact-page|custom",
    "estimated_time_minutes": 30,
    "difficulty_level": "beginner|intermediate|advanced"
  }
}
```

### Section Types

- **hero**: Hero/banner sections
- **content**: General content sections
- **media_text**: Media and text combinations
- **columns**: Multi-column layouts
- **list**: List-based content
- **quote**: Quote or testimonial sections
- **gallery**: Image galleries
- **faq**: FAQ sections
- **cta**: Call-to-action sections
- **testimonial**: Customer testimonials
- **pricing**: Pricing tables
- **team**: Team member sections
- **custom**: Custom section types

### Generation Modes

- **grounded**: Uses only MVDB knowledge base
- **hybrid**: Combines MVDB and AI generation (recommended)
- **generative**: Uses only AI generation

### Media Policies

- **required**: Section must include media
- **optional**: Media inclusion is optional
- **none**: No media should be included

## Usage Examples

### Creating a Landing Page Blueprint

```php
use AIPageComposer\Blueprints\Blueprint_Manager;

$blueprint_manager = new Blueprint_Manager();

$landing_page_schema = array(
    'sections' => array(
        array(
            'id' => 'hero-section',
            'type' => 'hero',
            'heading' => 'Welcome to Our Service',
            'heading_level' => 1,
            'word_target' => 100,
            'media_policy' => 'required',
            'tone' => 'professional',
            'block_preferences' => array(
                'preferred_plugin' => 'kadence_blocks',
                'primary_block' => 'kadence/rowlayout'
            )
        ),
        array(
            'id' => 'features',
            'type' => 'columns',
            'heading' => 'Key Features',
            'heading_level' => 2,
            'word_target' => 200,
            'media_policy' => 'optional',
            'tone' => 'professional'
        ),
        array(
            'id' => 'cta',
            'type' => 'cta',
            'heading' => 'Get Started Today',
            'heading_level' => 2,
            'word_target' => 75,
            'media_policy' => 'none',
            'tone' => 'friendly'
        )
    ),
    'global_settings' => array(
        'generation_mode' => 'hybrid',
        'hybrid_alpha' => 0.7,
        'cost_limit_usd' => 3.0
    ),
    'metadata' => array(
        'category' => 'landing-page',
        'difficulty_level' => 'beginner',
        'estimated_time_minutes' => 25
    )
);

// Create blueprint post
$blueprint_id = wp_insert_post(array(
    'post_title' => 'Landing Page Blueprint',
    'post_type' => 'ai_blueprint',
    'post_status' => 'publish'
));

// Save blueprint data
update_post_meta($blueprint_id, '_ai_blueprint_schema', $landing_page_schema);
```

### Validating Blueprint Data

```php
use AIPageComposer\Blueprints\Schema_Processor;

$schema_processor = new Schema_Processor();

// Validate complete blueprint
$validation_result = $schema_processor->validate_schema($blueprint_data);

if ($validation_result['valid']) {
    echo "Blueprint is valid!";
} else {
    foreach ($validation_result['errors'] as $error) {
        echo "Error in {$error['property']}: {$error['message']}";
    }
}

// Apply defaults and sanitize
$blueprint_data = $schema_processor->apply_defaults($blueprint_data);
$blueprint_data = $schema_processor->sanitize_data($blueprint_data);
```

### Using REST API Endpoints

#### Validate Schema
```javascript
fetch('/wp-json/ai-composer/v1/validate-schema', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify(blueprintData)
})
.then(response => response.json())
.then(result => {
    if (result.valid) {
        console.log('Blueprint is valid');
    } else {
        console.log('Validation errors:', result.errors);
    }
});
```

#### Generate Preview
```javascript
fetch('/wp-json/ai-composer/v1/blueprint-preview', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify(blueprintData)
})
.then(response => response.json())
.then(result => {
    console.log('Estimated tokens:', result.preview.estimated_tokens);
    console.log('Estimated cost:', result.preview.estimated_cost);
});
```

### Import/Export Blueprints

```php
$blueprint_manager = new Blueprint_Manager();

// Export blueprint
$export_data = $blueprint_manager->export_blueprint($blueprint_id);
file_put_contents('my-blueprint.json', $export_data);

// Import blueprint
$import_data = file_get_contents('my-blueprint.json');
$new_blueprint_id = $blueprint_manager->import_blueprint($import_data);
```

## Admin Interface

### Meta Boxes

The blueprint editing interface consists of three main meta boxes:

1. **Schema Configuration**: Visual and JSON editors for blueprint configuration
2. **Sections Management**: Dynamic section creation and configuration
3. **Validation Status**: Real-time validation feedback and error reporting

### JavaScript Integration

The admin interface uses JavaScript for:
- Dynamic section management (add, remove, reorder)
- Real-time validation via AJAX
- Preview generation and cost estimation
- Form auto-save functionality

### CSS Styling

Custom CSS provides:
- Responsive layout for all screen sizes
- Accessibility-compliant color schemes
- Professional styling matching WordPress admin
- Loading states and visual feedback

## Hook Integration

### Actions

- `ai_blueprint_saved`: Fired when a blueprint is saved
- `ai_blueprint_duplicated`: Fired when a blueprint is duplicated
- `ai_blueprint_imported`: Fired when a blueprint is imported

### Filters

- `ai_blueprint_default_schema`: Filter default schema values
- `ai_blueprint_validation_rules`: Customize validation rules
- `ai_blueprint_export_data`: Modify export data structure

## Performance Considerations

### Caching

- Blueprint metadata is cached in post meta for efficient queries
- Schema validation results are cached to avoid repeated validation
- REST API responses include appropriate cache headers

### Database Optimization

- Indexes on frequently queried meta fields
- Efficient post queries with proper meta_query usage
- Pagination support for large blueprint collections

### Memory Management

- Large blueprint schemas are handled efficiently
- JSON validation uses streaming where possible
- File exports are generated on-demand

## Security

### Capabilities

- `manage_ai_blueprints`: Full blueprint management (admin only)
- `create_ai_blueprints`: Create new blueprints (admin/editor)
- `edit_ai_blueprints`: Edit existing blueprints (admin/editor)

### Data Sanitization

- All user input is sanitized before storage
- JSON data is validated against strict schemas
- XSS protection on all output

### Nonce Verification

- All form submissions require valid nonces
- REST API endpoints use proper authentication
- CSRF protection on all state-changing operations

## Troubleshooting

### Common Issues

1. **Validation Errors**: Check schema structure against documentation
2. **Import Failures**: Ensure JSON is valid and contains required fields
3. **Permission Issues**: Verify user has appropriate capabilities
4. **Performance Issues**: Check for large blueprint schemas or excessive sections

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Error Logging

Blueprint system errors are logged to WordPress debug log with prefix `AI Blueprint:`.

## Testing

### Unit Tests

Comprehensive unit tests are provided for:
- Schema validation functionality
- Blueprint CRUD operations
- REST API endpoints
- Import/export functionality

Run tests with:
```bash
composer test
```

### Test Data

The `Test_Blueprint_Factory` class provides helper methods for creating test blueprints with valid and invalid data.

## Best Practices

### Blueprint Design

1. **Keep sections focused**: Each section should have a clear, single purpose
2. **Use appropriate word targets**: Balance content depth with generation cost
3. **Choose suitable block preferences**: Match blocks to content types
4. **Test validation early**: Validate schemas during development

### Performance

1. **Limit section count**: Keep blueprints under 10 sections for optimal performance
2. **Use efficient queries**: Leverage meta queries for filtering
3. **Cache results**: Store frequently accessed data in transients
4. **Monitor costs**: Set appropriate cost limits for generation

### Maintenance

1. **Regular backups**: Export important blueprints regularly
2. **Version control**: Track schema changes with version numbers
3. **Monitor validation**: Check for validation errors after schema updates
4. **Update documentation**: Keep custom blueprint documentation current

This documentation provides a comprehensive guide to the AI Blueprint System. For additional help or advanced use cases, consult the plugin's main documentation or contact support.