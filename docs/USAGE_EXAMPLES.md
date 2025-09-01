# AI Blueprint System - Usage Examples

## Table of Contents

1. [Basic Blueprint Creation](#basic-blueprint-creation)
2. [Advanced Configuration](#advanced-configuration)
3. [REST API Usage](#rest-api-usage)
4. [Import/Export Operations](#importexport-operations)

## Basic Blueprint Creation

### Creating a Simple Blog Post Blueprint

```php
<?php
use AIPageComposer\Blueprints\Blueprint_Manager;
use AIPageComposer\Blueprints\Schema_Processor;

$blueprint_manager = new Blueprint_Manager();
$schema_processor = new Schema_Processor();

$blog_post_blueprint = array(
    'sections' => array(
        array(
            'id' => 'introduction',
            'type' => 'content',
            'heading' => 'Introduction',
            'heading_level' => 2,
            'word_target' => 120,
            'media_policy' => 'optional',
            'tone' => 'professional'
        ),
        array(
            'id' => 'main-content',
            'type' => 'content',
            'heading' => 'Main Content',
            'heading_level' => 2,
            'word_target' => 400,
            'media_policy' => 'optional',
            'tone' => 'professional'
        )
    ),
    'global_settings' => array(
        'generation_mode' => 'hybrid',
        'cost_limit_usd' => 3.0
    ),
    'metadata' => array(
        'category' => 'blog-post',
        'difficulty_level' => 'intermediate'
    )
);

$blog_post_blueprint = $schema_processor->apply_defaults($blog_post_blueprint);
$validation_result = $schema_processor->validate_schema($blog_post_blueprint);

if ($validation_result['valid']) {
    $blueprint_id = wp_insert_post(array(
        'post_title' => 'Standard Blog Post Blueprint',
        'post_type' => 'ai_blueprint',
        'post_status' => 'publish'
    ));
    
    update_post_meta($blueprint_id, '_ai_blueprint_schema', $blog_post_blueprint);
    echo "Blueprint created with ID: $blueprint_id";
}
?>
```

### Creating a Landing Page Blueprint

```php
<?php
$landing_page_blueprint = array(
    'sections' => array(
        array(
            'id' => 'hero',
            'type' => 'hero',
            'heading' => 'Transform Your Business Today',
            'heading_level' => 1,
            'word_target' => 80,
            'media_policy' => 'required',
            'tone' => 'friendly',
            'block_preferences' => array(
                'preferred_plugin' => 'kadence_blocks',
                'primary_block' => 'kadence/rowlayout'
            )
        ),
        array(
            'id' => 'features',
            'type' => 'columns',
            'heading' => 'Why Choose Us',
            'word_target' => 180,
            'tone' => 'professional'
        ),
        array(
            'id' => 'cta',
            'type' => 'cta',
            'heading' => 'Ready to Get Started?',
            'word_target' => 60,
            'tone' => 'friendly'
        )
    ),
    'global_settings' => array(
        'generation_mode' => 'hybrid',
        'cost_limit_usd' => 4.0
    ),
    'metadata' => array(
        'category' => 'landing-page',
        'difficulty_level' => 'beginner'
    )
);
?>
```

## Advanced Configuration

### Complex Section with Block Preferences

```php
<?php
$advanced_section = array(
    'id' => 'pricing-table',
    'type' => 'pricing',
    'heading' => 'Choose Your Plan',
    'word_target' => 200,
    'block_preferences' => array(
        'preferred_plugin' => 'kadence_blocks',
        'primary_block' => 'kadence/column',
        'fallback_blocks' => array('core/columns', 'core/group'),
        'custom_attributes' => array(
            'pricing_columns' => 3,
            'highlight_column' => 2,
            'currency_symbol' => '$'
        )
    )
);
?>
```

## REST API Usage

### JavaScript Validation

```javascript
class BlueprintValidator {
    constructor(apiUrl, nonce) {
        this.apiUrl = apiUrl;
        this.nonce = nonce;
    }
    
    async validateSchema(blueprintData) {
        const response = await fetch(`${this.apiUrl}/validate-schema`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.nonce
            },
            body: JSON.stringify(blueprintData)
        });
        
        return await response.json();
    }
}

// Usage
const validator = new BlueprintValidator('/wp-json/ai-composer/v1', wpApiSettings.nonce);
const result = await validator.validateSchema(blueprintData);

if (result.valid) {
    console.log('Blueprint is valid');
} else {
    console.log('Errors:', result.errors);
}
```

### Preview Generation

```javascript
async function generatePreview(blueprintData) {
    const response = await fetch('/wp-json/ai-composer/v1/blueprint-preview', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpApiSettings.nonce
        },
        body: JSON.stringify(blueprintData)
    });
    
    const result = await response.json();
    
    console.log('Estimated tokens:', result.preview.estimated_tokens);
    console.log('Estimated cost:', result.preview.estimated_cost);
    
    return result;
}
```

## Import/Export Operations

### Export Multiple Blueprints

```php
<?php
function export_blueprints($blueprint_ids) {
    $blueprint_manager = new Blueprint_Manager();
    $export_data = array(
        'export_version' => '1.0',
        'exported_at' => current_time('mysql'),
        'blueprints' => array()
    );
    
    foreach ($blueprint_ids as $blueprint_id) {
        $blueprint_data = $blueprint_manager->get_blueprint($blueprint_id);
        if ($blueprint_data) {
            $post = get_post($blueprint_id);
            $export_data['blueprints'][] = array(
                'title' => $post->post_title,
                'blueprint' => $blueprint_data
            );
        }
    }
    
    return wp_json_encode($export_data, JSON_PRETTY_PRINT);
}

// Export specific blueprints
$export_data = export_blueprints(array(123, 124, 125));
file_put_contents('my-blueprints.json', $export_data);
?>
```

### Import Blueprints

```php
<?php
function import_blueprints($json_file) {
    $blueprint_manager = new Blueprint_Manager();
    $import_data = json_decode(file_get_contents($json_file), true);
    
    $results = array('imported' => array(), 'errors' => array());
    
    foreach ($import_data['blueprints'] as $blueprint_data) {
        $import_blueprint = array(
            'title' => $blueprint_data['title'],
            'blueprint' => $blueprint_data['blueprint']
        );
        
        $blueprint_id = $blueprint_manager->import_blueprint(wp_json_encode($import_blueprint));
        
        if (is_wp_error($blueprint_id)) {
            $results['errors'][] = $blueprint_id->get_error_message();
        } else {
            $results['imported'][] = $blueprint_id;
        }
    }
    
    return $results;
}

$results = import_blueprints('imported-blueprints.json');
echo "Imported: " . count($results['imported']) . " blueprints";
?>
```

This document provides essential usage examples for the AI Blueprint System. For complete documentation, see BLUEPRINT_SYSTEM.md.