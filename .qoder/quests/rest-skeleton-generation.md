# REST Skeleton & Outline Generation Design

## Overview

This design implements a REST API endpoint for generating content outlines in the AI Page Composer WordPress plugin. The system provides the `/ai-composer/v1/outline` endpoint that accepts blueprint configurations and user inputs to generate structured content outlines with block preferences. It includes development mode stubbing for local testing and production API integration with OpenAI and MVDB services.

## Architecture

The outline generation system follows WordPress REST API conventions and integrates with existing plugin components:

```
graph TB
    A[Admin UI] --> B[Outline Controller]
    B --> C[Outline Generator]
    C --> D{Generation Mode}
    D --> E[LLM Stub Service]
    D --> F[AI Service Client]
    F --> G[OpenAI API]
    F --> H[MVDB Service]
    C --> I[Block Preferences]
    B --> J[Blueprint Manager]
    
    E --> K[Stubbed Response]
    F --> L[AI Response]
    K --> M[Enhanced Outline]
    L --> M
    I --> M
    M --> N[Admin UI Step]
```

### Key Components

1. **Outline_Controller** - REST endpoint handler for `/ai-composer/v1/outline`
2. **Outline_Generator** - Core generation logic with mode switching
3. **LLM_Stub_Service** - Development mode simulation
4. **AI_Service_Client** - Production API integration
5. **Block_Preferences** - Section-specific block recommendations

## REST API Endpoints

### POST /ai-composer/v1/outline

Generates content outline based on blueprint and user inputs.

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| blueprint_id | integer | Yes | Blueprint post ID |
| brief | string | Yes | Content brief (10-2000 chars) |
| audience | string | No | Target audience description |
| tone | string | No | Content tone (professional, casual, technical, friendly, authoritative) |
| mvdb_params | object | No | MVDB retrieval parameters |
| alpha | number | No | Hybrid mode alpha (0.0-1.0, default: 0.7) |

**MVDB Parameters Structure:**
```json
{
  "namespaces": ["content", "products", "docs"],
  "k": 10,
  "min_score": 0.5,
  "filters": {}
}
```

**Response Format:**
```
{
  "sections": [
    {
      "id": "section-1",
      "heading": "Introduction to Topic",
      "type": "hero",
      "targetWords": 150,
      "needsImage": true,
      "mode": "hybrid",
      "subheadings": ["Overview", "Key Benefits"],
      "block_preference": {
        "preferred_plugin": "kadence_blocks",
        "primary_block": "kadence/rowlayout",
        "fallback_blocks": ["core/cover", "core/group"],
        "pattern_preference": "hero-with-image"
      }
    }
  ],
  "total_words": 750,
  "estimated_cost": 0.0015,
  "estimated_time": 120,
  "mode": "hybrid",
  "blueprint_id": 123,
  "generated_at": "2024-01-15T10:30:00+00:00"
}
```

**Error Responses:**
- `400` - Invalid parameters or malformed request
- `403` - Insufficient permissions
- `404` - Blueprint not found
- `500` - Generation service failure

## Implementation Details

### Outline Controller

```
class Outline_Controller extends WP_REST_Controller {
    protected $namespace = 'ai-composer/v1';
    protected $rest_base = 'outline';
    
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'generate_outline' ),
                'permission_callback' => array( $this, 'generate_outline_permissions_check' ),
                'args' => $this->get_outline_args()
            )
        );
    }
    
    public function generate_outline( WP_REST_Request $request ) {
        // Extract and validate parameters
        $params = $this->extract_parameters( $request );
        
        // Validate blueprint exists and is accessible
        $blueprint = $this->validate_blueprint( $params['blueprint_id'] );
        
        // Generate outline using the outline generator
        $outline_data = $this->outline_generator->generate( $params, $blueprint );
        
        // Apply block preferences to sections
        $enhanced_outline = $this->apply_block_preferences( $outline_data );
        
        return rest_ensure_response( $enhanced_outline );
    }
}
```

### Outline Generator

The generator handles mode switching between development and production:

```
class Outline_Generator {
    public function generate( $params, $blueprint ) {
        $use_stub = $this->should_use_stub_mode();
        
        if ( $use_stub ) {
            return $this->generate_stub_outline( $params, $blueprint );
        } else {
            return $this->generate_ai_outline( $params, $blueprint );
        }
    }
    
    private function should_use_stub_mode() {
        // Check for development environment flag
        if ( defined( 'AI_COMPOSER_DEV_MODE' ) && AI_COMPOSER_DEV_MODE ) {
            return true;
        }
        
        // Check for stub mode setting
        $settings = $this->settings_manager->get_all_settings();
        return $settings['development_settings']['use_llm_stub'] ?? false;
    }
}
```

### Block Preferences Integration

Each generated section includes block preference data:

```
private function apply_block_preferences( $outline_data ) {
    $enhanced_sections = array();
    
    foreach ( $outline_data['sections'] as $section ) {
        $enhanced_section = $section;
        
        // Get block preferences for this section type
        $block_preference = $this->block_preferences->get_section_preference( $section['type'] );
        
        if ( $block_preference ) {
            $enhanced_section['block_preference'] = array(
                'preferred_plugin' => $block_preference['preferred_plugin'],
                'primary_block' => $block_preference['primary_block'],
                'fallback_blocks' => $block_preference['fallback_blocks'],
                'pattern_preference' => $block_preference['pattern_preference']
            );
        }
        
        $enhanced_sections[] = $enhanced_section;
    }
    
    return array_merge( $outline_data, array( 'sections' => $enhanced_sections ) );
}
```

## Development Mode Stub Service

### LLM Stub Implementation

For local development without API costs:

```
class LLM_Stub_Service {
    public function generate_outline( $params, $blueprint ) {
        $blueprint_sections = $blueprint['schema']['sections'] ?? array();
        $sections = array();
        
        foreach ( $blueprint_sections as $index => $template_section ) {
            $section = array(
                'id' => 'section-' . ( $index + 1 ),
                'heading' => $this->generate_contextual_heading( $template_section, $params['brief'] ),
                'type' => $template_section['type'],
                'targetWords' => $template_section['word_target'],
                'needsImage' => $template_section['media_policy'] === 'required',
                'mode' => 'stub',
                'subheadings' => $this->generate_subheadings( $template_section['type'], $params['brief'] )
            );
            
            $sections[] = $section;
        }
        
        return array(
            'sections' => $sections,
            'total_words' => array_sum( array_column( $sections, 'targetWords' ) ),
            'estimated_cost' => 0.0,
            'estimated_time' => count( $sections ) * 5,
            'mode' => 'stub',
            'blueprint_id' => $blueprint['post']->ID,
            'generated_at' => current_time( 'c' )
        );
    }
}
```

### Contextual Heading Generation

The stub service generates realistic headings based on the content brief:

```
private function generate_contextual_heading( $template_section, $brief ) {
    $brief_words = $this->extract_key_terms( $brief );
    $main_topic = ! empty( $brief_words ) ? $brief_words[0] : 'Your Topic';
    
    $heading_templates = array(
        'hero' => array(
            'Transform Your %s Today',
            'The Ultimate Guide to %s',
            'Master %s: Expert Solutions'
        ),
        'content' => array(
            'Understanding %s: Key Insights',
            'Essential %s Strategies',
            'How %s Can Benefit You'
        )
    );
    
    $templates = $heading_templates[ $template_section['type'] ] ?? $heading_templates['content'];
    $selected_template = $templates[ array_rand( $templates ) ];
    
    return sprintf( $selected_template, $main_topic );
}
```

## Production AI Service Integration

### AI Service Client

For production mode with real APIs:

```
class AI_Service_Client {
    public function generate_outline( $prompt, $mvdb_context, $alpha ) {
        // Build complete prompt with MVDB context
        $enhanced_prompt = $this->enhance_prompt_with_context( $prompt, $mvdb_context );
        
        // Call OpenAI API
        $response = $this->call_openai_api( $enhanced_prompt, $alpha );
        
        return $this->parse_ai_response( $response );
    }
    
    private function enhance_prompt_with_context( $prompt, $mvdb_context ) {
        if ( empty( $mvdb_context ) ) {
            return $prompt;
        }
        
        $context_text = "Relevant context from knowledge base:\n";
        foreach ( $mvdb_context as $chunk ) {
            $context_text .= "- " . $chunk['content'] . "\n";
        }
        
        return $context_text . "\n" . $prompt;
    }
}
```

### MVDB Integration

```
public function retrieve_mvdb_context( $query, $namespaces, $k, $min_score ) {
    $response = $this->mvdb_client->post( '/vector-search', array(
        'query' => $query,
        'namespaces' => $namespaces,
        'k' => $k,
        'min_score' => $min_score
    ) );
    
    return $response['results'] ?? array();
}
```

## Security & Validation

### Permission Checks

```
public function generate_outline_permissions_check() {
    return current_user_can( 'edit_posts' );
}
```

### Parameter Validation

```
private function extract_parameters( WP_REST_Request $request ) {
    $params = array(
        'blueprint_id' => absint( $request->get_param( 'blueprint_id' ) ),
        'brief' => sanitize_textarea_field( $request->get_param( 'brief' ) ),
        'audience' => sanitize_text_field( $request->get_param( 'audience' ) ),
        'tone' => sanitize_key( $request->get_param( 'tone' ) ),
        'alpha' => $this->validate_alpha_value( $request->get_param( 'alpha' ) )
    );
    
    if ( empty( $params['blueprint_id'] ) ) {
        throw new Exception( __( 'Blueprint ID is required', 'ai-page-composer' ) );
    }
    
    if ( empty( $params['brief'] ) ) {
        throw new Exception( __( 'Brief is required', 'ai-page-composer' ) );
    }
    
    return $params;
}
```

## Testing Strategy

### Unit Tests

```
class Test_Outline_Controller extends WP_UnitTestCase {
    public function test_outline_generation_endpoint() {
        wp_set_current_user( $this->admin_user_id );
        
        $blueprint_id = $this->create_test_blueprint();
        
        $request = new WP_REST_Request( 'POST', '/ai-composer/v1/outline' );
        $request->set_param( 'blueprint_id', $blueprint_id );
        $request->set_param( 'brief', 'Create content about sustainable gardening' );
        
        $response = $this->server->dispatch( $request );
        
        $this->assertEquals( 200, $response->get_status() );
        $data = $response->get_data();
        
        $this->assertArrayHasKey( 'sections', $data );
        $this->assertArrayHasKey( 'total_words', $data );
        
        // Verify block preferences are included
        foreach ( $data['sections'] as $section ) {
            $this->assertArrayHasKey( 'block_preference', $section );
            $this->assertArrayHasKey( 'preferred_plugin', $section['block_preference'] );
        }
    }
}
```

### Integration Testing

Test both stub and production modes:

```
public function test_stub_mode_generation() {
    // Force stub mode
    add_filter( 'ai_composer_use_stub_mode', '__return_true' );
    
    $response = $this->generate_outline_request();
    $data = $response->get_data();
    
    $this->assertEquals( 'stub', $data['mode'] );
    $this->assertEquals( 0.0, $data['estimated_cost'] );
}

public function test_production_mode_generation() {
    // Mock API responses
    $this->mock_openai_response();
    
    add_filter( 'ai_composer_use_stub_mode', '__return_false' );
    
    $response = $this->generate_outline_request();
    $data = $response->get_data();
    
    $this->assertEquals( 'hybrid', $data['mode'] );
    $this->assertGreaterThan( 0, $data['estimated_cost'] );
}
```

## Admin UI Integration

### JavaScript Client

```
class OutlineGenerator {
    async generateOutline(blueprintId, brief, options = {}) {
        const response = await wp.apiFetch({
            path: '/ai-composer/v1/outline',
            method: 'POST',
            data: {
                blueprint_id: blueprintId,
                brief: brief,
                audience: options.audience || '',
                tone: options.tone || 'professional',
                mvdb_params: options.mvdbParams || {},
                alpha: options.alpha || 0.7
            }
        });
        
        return response;
    }
}
```

### Admin UI Step Integration

The generated outline appears in the Admin UI workflow:

```
public function render_outline_step() {
    ?>
    <div id="outline-generation-step" class="ai-composer-step">
        <h3><?php esc_html_e( 'Generate Content Outline', 'ai-page-composer' ); ?></h3>
        
        <form id="outline-form">
            <div class="form-row">
                <label for="content-brief"><?php esc_html_e( 'Content Brief:', 'ai-page-composer' ); ?></label>
                <textarea id="content-brief" name="brief" rows="4" required></textarea>
            </div>
            
            <div class="form-row">
                <label for="target-audience"><?php esc_html_e( 'Target Audience:', 'ai-page-composer' ); ); ?></label>
                <input type="text" id="target-audience" name="audience">
            </div>
            
            <button type="submit" class="button button-primary">
                <?php esc_html_e( 'Generate Outline', 'ai-page-composer' ); ?>
            </button>
        </form>
        
        <div id="outline-results" style="display:none;">
            <!-- Generated outline will be displayed here -->
        </div>
    </div>
    <?php
}
```

## API Manager Registration

Integration with existing API manager:

```
public function register_rest_routes() {
    // Existing routes...
    
    // Register outline controller
    $outline_controller = new Outline_Controller( $this->blueprint_manager, $this->block_preferences );
    $outline_controller->register_routes();
}
```

## Error Handling & Logging

### Comprehensive Error Handling

```
try {
    $outline_data = $this->outline_generator->generate( $params, $blueprint );
    $enhanced_outline = $this->apply_block_preferences( $outline_data );
    
    return rest_ensure_response( $enhanced_outline );
    
} catch ( Exception $e ) {
    error_log( '[AI Composer] Outline generation failed: ' . $e->getMessage() );
    
    return new WP_Error(
        'outline_generation_failed',
        $e->getMessage(),
        array( 'status' => 500 )
    );
}
```

### Audit Logging

```
        });

        // Get most frequent words (simple frequency analysis)
        $word_freq = array_count_values( $filtered_words );
        arsort( $word_freq );
        
        return array_slice( array_keys( $word_freq ), 0, 3 );
    }

    /**
     * Generate default stub when no blueprint is available
     *
     * @param array $params Generation parameters.
     * @return array Default stub outline.
     */
    private function generate_default_stub( $params ) {
        $main_topic = $this->extract_key_terms( $params['brief'] )[0] ?? 'Content';
        
        $default_sections = array(
            array(
                'id' => 'section-1',
                'heading' => sprintf( 'Introduction to %s', ucfirst( $main_topic ) ),
                'type' => 'hero',
                'targetWords' => 100,
                'needsImage' => true,
                'mode' => 'hybrid',
                'subheadings' => array()
            ),
            array(
                'id' => 'section-2',
                'heading' => sprintf( 'Understanding %s', ucfirst( $main_topic ) ),
                'type' => 'content',
                'targetWords' => 300,
                'needsImage' => false,
                'mode' => 'hybrid',
                'subheadings' => array(
                    sprintf( 'What is %s?', $main_topic ),
                    sprintf( 'Why %s Matters', $main_topic ),
                    'Key Benefits'
                )
            ),
            array(
                'id' => 'section-3',
                'heading' => sprintf( 'Getting Started with %s', ucfirst( $main_topic ) ),
                'type' => 'content',
                'targetWords' => 250,
                'needsImage' => true,
                'mode' => 'hybrid',
                'subheadings' => array(
                    'Step-by-Step Guide',
                    'Best Practices',
                    'Common Mistakes to Avoid'
                )
            ),
            array(
                'id' => 'section-4',
                'heading' => 'Take Action Today',
                'type' => 'cta',
                'targetWords' => 80,
                'needsImage' => false,
                'mode' => 'hybrid',
                'subheadings' => array()
            )
        );

        return array(
            'sections' => $default_sections,
            'total_words' => 730,
            'estimated_cost' => 0.0,
            'estimated_time' => 20,
            'mode' => 'stub',
            'blueprint_id' => null,
            'generated_at' => current_time( 'c' )
        );
    }
}
```

## AI Service Client for Production

### Production API Integration

```php
<?php
/**
 * AI Service Client - Production API integration
 * 
 * Handles communication with external AI services (OpenAI, MVDB)
 * for production outline generation.
 *
 * @package AIPageComposer\API
 */

namespace AIPageComposer\API;

use AIPageComposer\Admin\Settings_Manager;

/**
 * AI Service Client class
 */
class AI_Service_Client {

    /**
     * Settings manager instance
     *
     * @var Settings_Manager
     */
    private $settings_manager;

    /**
     * HTTP client for API calls
     *
     * @var object
     */
    private $http_client;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings_manager = new Settings_Manager();
        $this->http_client = new \GuzzleHttp\Client();
    }

    /**
     * Generate outline using OpenAI API
     *
     * @param string $prompt Generation prompt.
     * @param array  $mvdb_context MVDB context data.
     * @param float  $alpha Alpha value for hybrid mode.
     * @return array AI response data.
     * @throws Exception If API call fails.
     */
    public function generate_outline( $prompt, $mvdb_context = array(), $alpha = 0.7 ) {
        $settings = $this->settings_manager->get_all_settings();
        $api_key = $settings['api_settings']['openai_api_key'] ?? '';

        if ( empty( $api_key ) ) {
            throw new Exception( __( 'OpenAI API key not configured', 'ai-page-composer' ) );
        }

        // Build enhanced prompt with MVDB context
        $enhanced_prompt = $this->build_enhanced_prompt( $prompt, $mvdb_context, $alpha );

        try {
            $response = $this->http_client->post( 'https://api.openai.com/v1/chat/completions', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'json' => array(
                    'model' => 'gpt-4',
                    'messages' => array(
                        array(
                            'role' => 'system',
                            'content' => 'You are an expert content strategist. Generate structured content outlines in JSON format.'
                        ),
                        array(
                            'role' => 'user',
                            'content' => $enhanced_prompt
                        )
                    ),
                    'temperature' => 0.7,
                    'max_tokens' => 2000
                )
            ));

            $response_data = json_decode( $response->getBody()->getContents(), true );
            
            if ( ! $response_data || ! isset( $response_data['choices'][0]['message']['content'] ) ) {
                throw new Exception( __( 'Invalid API response format', 'ai-page-composer' ) );
            }

            $content = $response_data['choices'][0]['message']['content'];
            $outline_data = json_decode( $content, true );

            if ( json_last_error() !== JSON_ERROR_NONE ) {
                throw new Exception( __( 'Failed to parse outline JSON', 'ai-page-composer' ) );
            }

            return $outline_data;

        } catch ( \GuzzleHttp\Exception\RequestException $e ) {
            throw new Exception( sprintf( __( 'OpenAI API error: %s', 'ai-page-composer' ), $e->getMessage() ) );
        }
    }

    /**
     * Retrieve context from MVDB
     *
     * @param string $query Search query.
     * @param array  $namespaces MVDB namespaces.
     * @param int    $k Number of results.
     * @param float  $min_score Minimum relevance score.
     * @return array MVDB context data.
     * @throws Exception If MVDB call fails.
     */
    public function retrieve_mvdb_context( $query, $namespaces, $k, $min_score ) {
        $settings = $this->settings_manager->get_all_settings();
        $api_key = $settings['api_settings']['mvdb_api_key'] ?? '';

        if ( empty( $api_key ) ) {
            throw new Exception( __( 'MVDB API key not configured', 'ai-page-composer' ) );
        }

        try {
            $response = $this->http_client->post( 'https://api.wpengine.com/v1/vector/search', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'json' => array(
                    'query' => $query,
                    'namespaces' => $namespaces,
                    'k' => $k,
                    'min_score' => $min_score
                )
            ));

            $response_data = json_decode( $response->getBody()->getContents(), true );
            
            return $response_data['results'] ?? array();

        } catch ( \GuzzleHttp\Exception\RequestException $e ) {
            throw new Exception( sprintf( __( 'MVDB API error: %s', 'ai-page-composer' ), $e->getMessage() ) );
        }
    }

    /**
     * Build enhanced prompt with MVDB context
     *
     * @param string $base_prompt Base generation prompt.
     * @param array  $mvdb_context MVDB context chunks.
     * @param float  $alpha Alpha value for hybrid mode.
     * @return string Enhanced prompt.
     */
    private function build_enhanced_prompt( $base_prompt, $mvdb_context, $alpha ) {
        $enhanced_prompt = $base_prompt;

        if ( ! empty( $mvdb_context ) ) {
            $enhanced_prompt .= "

Relevant context from your knowledge base:
";
            
            foreach ( array_slice( $mvdb_context, 0, 5 ) as $chunk ) {
                $enhanced_prompt .= sprintf(
                    "- %s (Score: %.2f)
",
                    wp_trim_words( $chunk['text'], 50 ),
                    $chunk['score']
                );
            }

            $enhanced_prompt .= sprintf(
                "
Use this context with alpha=%.1f weighting (1.0=fully grounded, 0.0=fully creative).
",
                $alpha
            );
        }

        $enhanced_prompt .= "

Response format: JSON with 'sections' array containing objects with: id, heading, type, targetWords, needsImage, mode, subheadings.";

        return $enhanced_prompt;
    }
}
```

## Admin UI Integration

### JavaScript Interface Component

```javascript
/**
 * Outline Generation Component
 * 
 * Handles the admin interface for generating content outlines
 * via the REST API endpoint.
 */
class OutlineGenerator {
    constructor() {
        this.initializeComponents();
        this.bindEvents();
    }

    initializeComponents() {
        // Initialize outline generation form
        this.form = document.getElementById('outline-generation-form');
        this.resultsContainer = document.getElementById('outline-results');
        this.loadingIndicator = document.getElementById('outline-loading');
        
        // Initialize blueprint selector
        this.blueprintSelect = document.getElementById('blueprint-select');
        this.loadBlueprints();
    }

    bindEvents() {
        if (this.form) {
            this.form.addEventListener('submit', this.handleFormSubmit.bind(this));
        }

        // Blueprint change handler
        if (this.blueprintSelect) {
            this.blueprintSelect.addEventListener('change', this.handleBlueprintChange.bind(this));
        }

        // Advanced options toggle
        const advancedToggle = document.getElementById('advanced-options-toggle');
        if (advancedToggle) {
            advancedToggle.addEventListener('click', this.toggleAdvancedOptions.bind(this));
        }
    }

    async loadBlueprints() {
        try {
            const response = await wp.apiFetch({
                path: '/ai-composer/v1/blueprints',
                method: 'GET'
            });

            this.populateBlueprintSelect(response);
        } catch (error) {
            console.error('Failed to load blueprints:', error);
            this.showError('Failed to load blueprints. Please refresh the page.');
        }
    }

    populateBlueprintSelect(blueprints) {
        if (!this.blueprintSelect) return;

        // Clear existing options except the first placeholder
        const placeholder = this.blueprintSelect.querySelector('option[value=""]');
        this.blueprintSelect.innerHTML = '';
        if (placeholder) {
            this.blueprintSelect.appendChild(placeholder);
        }

        blueprints.forEach(blueprint => {
            const option = document.createElement('option');
            option.value = blueprint.id;
            option.textContent = blueprint.title;
            this.blueprintSelect.appendChild(option);
        });
    }

    async handleFormSubmit(event) {
        event.preventDefault();
        
        const formData = new FormData(this.form);
        const params = this.extractFormParameters(formData);
        
        // Validate required fields
        if (!this.validateParameters(params)) {
            return;
        }

        try {
            this.showLoading(true);
            this.clearResults();
            
            const outline = await this.generateOutline(params);
            this.displayOutline(outline);
            
        } catch (error) {
            this.showError(error.message || 'Outline generation failed');
        } finally {
            this.showLoading(false);
        }
    }

    extractFormParameters(formData) {
        const params = {
            blueprint_id: parseInt(formData.get('blueprint_id')),
            brief: formData.get('brief'),
            audience: formData.get('audience') || '',
            tone: formData.get('tone') || 'professional',
            alpha: parseFloat(formData.get('alpha')) || 0.7
        };

        // Extract MVDB parameters
        const mvdbNamespaces = formData.getAll('mvdb_namespaces');
        params.mvdb_params = {
            namespaces: mvdbNamespaces.length > 0 ? mvdbNamespaces : ['content'],
            k: parseInt(formData.get('mvdb_k')) || 10,
            min_score: parseFloat(formData.get('mvdb_min_score')) || 0.5,
            filters: {}
        };

        return params;
    }

    validateParameters(params) {
        const errors = [];

        if (!params.blueprint_id || params.blueprint_id <= 0) {
            errors.push('Please select a blueprint');
        }

        if (!params.brief || params.brief.trim().length < 10) {
            errors.push('Brief must be at least 10 characters long');
        }

        if (params.alpha < 0 || params.alpha > 1) {
            errors.push('Alpha value must be between 0.0 and 1.0');
        }

        if (errors.length > 0) {
            this.showError(errors.join('<br>'));
            return false;
        }

        return true;
    }

    async generateOutline(params) {
        const response = await wp.apiFetch({
            path: '/ai-composer/v1/outline',
            method: 'POST',
            data: params
        });

        if (!response || !response.sections) {
            throw new Error('Invalid response format from outline generation');
        }

        return response;
    }

    displayOutline(outline) {
        if (!this.resultsContainer) return;

        let html = `
            <div class="outline-summary">
                <h3>Generated Outline</h3>
                <div class="outline-stats">
                    <span class="stat">
                        <strong>${outline.sections.length}</strong> sections
                    </span>
                    <span class="stat">
                        <strong>${outline.total_words}</strong> total words
                    </span>
                    <span class="stat">
                        <strong>$${outline.estimated_cost}</strong> estimated cost
                    </span>
                    <span class="stat">
                        <strong>${Math.ceil(outline.estimated_time / 60)}</strong> min estimated time
                    </span>
                </div>
            </div>
            <div class="outline-sections">
        `;

        outline.sections.forEach((section, index) => {
            html += `
                <div class="outline-section" data-section-id="${section.id}">
                    <div class="section-header">
                        <h4>${index + 1}. ${section.heading}</h4>
                        <div class="section-meta">
                            <span class="section-type">${section.type}</span>
                            <span class="section-words">${section.targetWords} words</span>
                            ${section.needsImage ? '<span class="needs-image">📷 Image</span>' : ''}
                        </div>
                    </div>
            `;

            if (section.subheadings && section.subheadings.length > 0) {
                html += `
                    <div class="section-subheadings">
                        <ul>
                            ${section.subheadings.map(sub => `<li>${sub}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }

            if (section.block_preference) {
                html += `
                    <div class="section-block-preference">
                        <small>
                            Block: ${section.block_preference.primary_block || 'Auto'} 
                            (${section.block_preference.preferred_plugin || 'auto'})
                        </small>
                    </div>
                `;
            }

            html += `
                    <div class="section-actions">
                        <button type="button" class="button button-secondary edit-section" 
                                data-section-id="${section.id}">
                            Edit Section
                        </button>
                        <button type="button" class="button button-secondary preview-section" 
                                data-section-id="${section.id}">
                            Preview
                        </button>
                    </div>
                </div>
            `;
        });

        html += `
            </div>
            <div class="outline-actions">
                <button type="button" class="button button-primary" id="proceed-to-generation">
                    Proceed to Content Generation
                </button>
                <button type="button" class="button button-secondary" id="edit-outline">
                    Edit Outline
                </button>
                <button type="button" class="button button-secondary" id="regenerate-outline">
                    Regenerate
                </button>
            </div>
        `;

        this.resultsContainer.innerHTML = html;
        
        // Bind action events
        this.bindOutlineActions();
        
        // Store outline data for next steps
        window.aiComposerCurrentOutline = outline;
    }

    bindOutlineActions() {
        // Proceed to generation button
        const proceedBtn = document.getElementById('proceed-to-generation');
        if (proceedBtn) {
            proceedBtn.addEventListener('click', () => {
                // Trigger next step in admin workflow
                if (typeof window.aiComposerProceedToGeneration === 'function') {
                    window.aiComposerProceedToGeneration(window.aiComposerCurrentOutline);
                }
            });
        }

        // Edit outline button
        const editBtn = document.getElementById('edit-outline');
        if (editBtn) {
            editBtn.addEventListener('click', this.editOutline.bind(this));
        }

        // Regenerate button
        const regenBtn = document.getElementById('regenerate-outline');
        if (regenBtn) {
            regenBtn.addEventListener('click', () => {
                this.form.dispatchEvent(new Event('submit'));
            });
        }

        // Section action buttons
        document.querySelectorAll('.edit-section').forEach(btn => {
            btn.addEventListener('click', this.editSection.bind(this));
        });

        document.querySelectorAll('.preview-section').forEach(btn => {
            btn.addEventListener('click', this.previewSection.bind(this));
        });
    }

    editOutline() {
        // Switch to inline editing mode
        document.querySelectorAll('.outline-section').forEach(section => {
            this.makeEditableSection(section);
        });
    }

    makeEditableSection(sectionElement) {
        const header = sectionElement.querySelector('h4');
        const currentText = header.textContent.replace(/^\d+\.\s*/, '');
        
        header.innerHTML = `
            <input type="text" value="${currentText}" 
                   class="section-heading-edit" style="width: 100%;">
        `;

        // Add save/cancel buttons
        const actions = sectionElement.querySelector('.section-actions');
        actions.innerHTML = `
            <button type="button" class="button button-primary save-section">Save</button>
            <button type="button" class="button button-secondary cancel-section">Cancel</button>
        `;

        // Bind save/cancel events
        const saveBtn = actions.querySelector('.save-section');
        const cancelBtn = actions.querySelector('.cancel-section');
        
        saveBtn.addEventListener('click', () => this.saveSectionEdit(sectionElement, currentText));
        cancelBtn.addEventListener('click', () => this.cancelSectionEdit(sectionElement, currentText));
    }

    saveSectionEdit(sectionElement, originalText) {
        const input = sectionElement.querySelector('.section-heading-edit');
        const newText = input.value.trim();
        
        if (newText) {
            // Update the outline data
            const sectionId = sectionElement.getAttribute('data-section-id');
            if (window.aiComposerCurrentOutline) {
                const section = window.aiComposerCurrentOutline.sections.find(s => s.id === sectionId);
                if (section) {
                    section.heading = newText;
                }
            }
            
            // Update display
            const header = sectionElement.querySelector('h4');
            const index = Array.from(sectionElement.parentNode.children).indexOf(sectionElement);
            header.textContent = `${index + 1}. ${newText}`;
        }
        
        this.restoreSectionActions(sectionElement);
    }

    cancelSectionEdit(sectionElement, originalText) {
        const header = sectionElement.querySelector('h4');
        const index = Array.from(sectionElement.parentNode.children).indexOf(sectionElement);
        header.textContent = `${index + 1}. ${originalText}`;
        
        this.restoreSectionActions(sectionElement);
    }

    restoreSectionActions(sectionElement) {
        const sectionId = sectionElement.getAttribute('data-section-id');
        const actions = sectionElement.querySelector('.section-actions');
        
        actions.innerHTML = `
            <button type="button" class="button button-secondary edit-section" 
                    data-section-id="${sectionId}">
                Edit Section
            </button>
            <button type="button" class="button button-secondary preview-section" 
                    data-section-id="${sectionId}">
                Preview
            </button>
        `;
        
        // Re-bind events
        actions.querySelector('.edit-section').addEventListener('click', this.editSection.bind(this));
        actions.querySelector('.preview-section').addEventListener('click', this.previewSection.bind(this));
    }

    editSection(event) {
        const sectionId = event.target.getAttribute('data-section-id');
        const sectionElement = event.target.closest('.outline-section');
        
        this.makeEditableSection(sectionElement);
    }

    previewSection(event) {
        const sectionId = event.target.getAttribute('data-section-id');
        
        if (window.aiComposerCurrentOutline) {
            const section = window.aiComposerCurrentOutline.sections.find(s => s.id === sectionId);
            if (section) {
                this.showSectionPreview(section);
            }
        }
    }

    showSectionPreview(section) {
        // Create modal or side panel for section preview
        const modal = document.createElement('div');
        modal.className = 'section-preview-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Section Preview: ${section.heading}</h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="preview-details">
                        <p><strong>Type:</strong> ${section.type}</p>
                        <p><strong>Target Words:</strong> ${section.targetWords}</p>
                        <p><strong>Needs Image:</strong> ${section.needsImage ? 'Yes' : 'No'}</p>
                        <p><strong>Mode:</strong> ${section.mode}</p>
                    </div>
                    ${section.subheadings && section.subheadings.length > 0 ? `
                        <div class="preview-subheadings">
                            <h4>Subheadings:</h4>
                            <ul>
                                ${section.subheadings.map(sub => `<li>${sub}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                    ${section.block_preference ? `
                        <div class="preview-block-info">
                            <h4>Block Configuration:</h4>
                            <p><strong>Plugin:</strong> ${section.block_preference.preferred_plugin}</p>
                            <p><strong>Primary Block:</strong> ${section.block_preference.primary_block}</p>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        
        // Close modal events
        modal.querySelector('.modal-close').addEventListener('click', () => {
            document.body.removeChild(modal);
        });
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                document.body.removeChild(modal);
            }
        });
    }

    handleBlueprintChange(event) {
        const blueprintId = parseInt(event.target.value);
        
        if (blueprintId > 0) {
            this.loadBlueprintPreview(blueprintId);
        } else {
            this.clearBlueprintPreview();
        }
    }

    async loadBlueprintPreview(blueprintId) {
        try {
            const response = await wp.apiFetch({
                path: `/ai-composer/v1/blueprints/${blueprintId}`,
                method: 'GET'
            });

            this.displayBlueprintPreview(response);
        } catch (error) {
            console.error('Failed to load blueprint preview:', error);
        }
    }

    displayBlueprintPreview(blueprint) {
        const previewContainer = document.getElementById('blueprint-preview');
        if (!previewContainer) return;

        const sections = blueprint.schema?.sections || [];
        const globalSettings = blueprint.global_settings || {};

        let html = `
            <div class="blueprint-preview-content">
                <h4>Blueprint Preview: ${blueprint.title}</h4>
                <div class="blueprint-meta">
                    <span>Mode: ${globalSettings.generation_mode || 'hybrid'}</span>
                    <span>Sections: ${sections.length}</span>
                    <span>Alpha: ${globalSettings.hybrid_alpha || 0.7}</span>
                </div>
                <div class="blueprint-sections">
        `;

        sections.forEach((section, index) => {
            html += `
                <div class="blueprint-section-preview">
                    <strong>${index + 1}. ${section.heading}</strong>
                    <span class="section-details">
                        ${section.type} (${section.word_target} words)
                    </span>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;

        previewContainer.innerHTML = html;
    }

    clearBlueprintPreview() {
        const previewContainer = document.getElementById('blueprint-preview');
        if (previewContainer) {
            previewContainer.innerHTML = '';
        }
    }

    toggleAdvancedOptions() {
        const advancedSection = document.getElementById('advanced-options');
        const toggleBtn = document.getElementById('advanced-options-toggle');
        
        if (advancedSection && toggleBtn) {
            const isVisible = advancedSection.style.display !== 'none';
            advancedSection.style.display = isVisible ? 'none' : 'block';
            toggleBtn.textContent = isVisible ? 'Show Advanced Options' : 'Hide Advanced Options';
        }
    }

    showLoading(show) {
        if (this.loadingIndicator) {
            this.loadingIndicator.style.display = show ? 'block' : 'none';
        }
        
        const submitBtn = this.form?.querySelector('input[type="submit"], button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = show;
            submitBtn.textContent = show ? 'Generating...' : 'Generate Outline';
        }
    }

    clearResults() {
        if (this.resultsContainer) {
            this.resultsContainer.innerHTML = '';
        }
    }

    showError(message) {
        const errorContainer = document.getElementById('outline-errors') || this.resultsContainer;
        if (errorContainer) {
            errorContainer.innerHTML = `
                <div class="notice notice-error">
                    <p>${message}</p>
                </div>
            `;
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('outline-generation-form')) {
        new OutlineGenerator();
    }
});
```

## Admin UI Template

### Outline Generation Form

```html
<div class="wrap ai-composer-outline-generator">
    <h1><?php esc_html_e('Generate Content Outline', 'ai-page-composer'); ?></h1>
    
    <div id="outline-errors"></div>
    
    <form id="outline-generation-form" class="outline-form">
        <?php wp_nonce_field('ai_composer_generate_outline', 'outline_nonce'); ?>
        
        <div class="form-section">
            <h2><?php esc_html_e('Basic Configuration', 'ai-page-composer'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="blueprint-select"><?php esc_html_e('Blueprint', 'ai-page-composer'); ?></label>
                    </th>
                    <td>
                        <select id="blueprint-select" name="blueprint_id" required class="regular-text">
                            <option value=""><?php esc_html_e('Select a blueprint...', 'ai-page-composer'); ?></option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Choose a content blueprint to structure the outline', 'ai-page-composer'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="content-brief"><?php esc_html_e('Content Brief', 'ai-page-composer'); ?></label>
                    </th>
                    <td>
                        <textarea id="content-brief" name="brief" rows="4" class="large-text" required 
                                  placeholder="<?php esc_attr_e('Describe the content you want to create...', 'ai-page-composer'); ?>"></textarea>
                        <p class="description">
                            <?php esc_html_e('Minimum 10 characters. Be specific about your topic and goals.', 'ai-page-composer'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="target-audience"><?php esc_html_e('Target Audience', 'ai-page-composer'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="target-audience" name="audience" class="regular-text" 
                               placeholder="<?php esc_attr_e('e.g., small business owners, developers, consumers', 'ai-page-composer'); ?>">
                        <p class="description">
                            <?php esc_html_e('Optional. Helps tailor the content tone and complexity.', 'ai-page-composer'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="content-tone"><?php esc_html_e('Content Tone', 'ai-page-composer'); ?></label>
                    </th>
                    <td>
                        <select id="content-tone" name="tone" class="regular-text">
                            <option value="professional"><?php esc_html_e('Professional', 'ai-page-composer'); ?></option>
                            <option value="casual"><?php esc_html_e('Casual', 'ai-page-composer'); ?></option>
                            <option value="technical"><?php esc_html_e('Technical', 'ai-page-composer'); ?></option>
                            <option value="friendly"><?php esc_html_e('Friendly', 'ai-page-composer'); ?></option>
                            <option value="authoritative"><?php esc_html_e('Authoritative', 'ai-page-composer'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="form-section">
            <button type="button" id="advanced-options-toggle" class="button button-secondary">
                <?php esc_html_e('Show Advanced Options', 'ai-page-composer'); ?>
            </button>
            
            <div id="advanced-options" style="display: none;">
                <h2><?php esc_html_e('Advanced Configuration', 'ai-page-composer'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="alpha-value"><?php esc_html_e('Alpha Value', 'ai-page-composer'); ?></label>
                        </th>
                        <td>
                            <input type="range" id="alpha-value" name="alpha" min="0.0" max="1.0" step="0.1" value="0.7" 
                                   class="alpha-slider">
                            <span id="alpha-display">0.7</span>
                            <p class="description">
                                <?php esc_html_e('0.0 = More creative/generative, 1.0 = More grounded/factual', 'ai-page-composer'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('MVDB Namespaces', 'ai-page-composer'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="mvdb_namespaces[]" value="content" checked> <?php esc_html_e('Content', 'ai-page-composer'); ?></label><br>
                                <label><input type="checkbox" name="mvdb_namespaces[]" value="products"> <?php esc_html_e('Products', 'ai-page-composer'); ?></label><br>
                                <label><input type="checkbox" name="mvdb_namespaces[]" value="docs"> <?php esc_html_e('Documentation', 'ai-page-composer'); ?></label><br>
                                <label><input type="checkbox" name="mvdb_namespaces[]" value="knowledge"> <?php esc_html_e('Knowledge Base', 'ai-page-composer'); ?></label>
                            </fieldset>
                            <p class="description">
                                <?php esc_html_e('Select which knowledge areas to search for relevant context', 'ai-page-composer'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="mvdb-k"><?php esc_html_e('Retrieval Count (K)', 'ai-page-composer'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="mvdb-k" name="mvdb_k" min="1" max="50" value="10" class="small-text">
                            <p class="description">
                                <?php esc_html_e('Number of relevant chunks to retrieve from MVDB (1-50)', 'ai-page-composer'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="mvdb-min-score"><?php esc_html_e('Minimum Score', 'ai-page-composer'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="mvdb-min-score" name="mvdb_min_score" 
                                   min="0.0" max="1.0" step="0.1" value="0.5" class="small-text">
                            <p class="description">
                                <?php esc_html_e('Minimum relevance score for MVDB results (0.0-1.0)', 'ai-page-composer'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="form-actions">
            <input type="submit" class="button button-primary" value="<?php esc_attr_e('Generate Outline', 'ai-page-composer'); ?>">
        </div>
    </form>
    
    <div id="blueprint-preview" class="blueprint-preview-section"></div>
    
    <div id="outline-loading" class="outline-loading" style="display: none;">
        <div class="spinner is-active"></div>
        <p><?php esc_html_e('Generating outline...', 'ai-page-composer'); ?></p>
    </div>
    
    <div id="outline-results" class="outline-results-section"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Alpha value display update
    const alphaSlider = document.getElementById('alpha-value');
    const alphaDisplay = document.getElementById('alpha-display');
    
    if (alphaSlider && alphaDisplay) {
        alphaSlider.addEventListener('input', function() {
            alphaDisplay.textContent = this.value;
        });
    }
});
</script>
```

## Testing Strategy

### Unit Testing for Outline Generation

```php
<?php
/**
 * Test Outline Controller functionality
 */
class Test_Outline_Controller extends WP_UnitTestCase {
    
    private $outline_controller;
    private $blueprint_manager;
    private $block_preferences;
    private $admin_user_id;
    
    public function setUp(): void {
        parent::setUp();
        
        $this->blueprint_manager = new Blueprint_Manager();
        $this->block_preferences = new Block_Preferences();
        $this->outline_controller = new Outline_Controller( $this->blueprint_manager, $this->block_preferences );
        
        // Create admin user
        $this->admin_user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user( $this->admin_user_id );
        
        // Set up development mode
        define( 'AI_COMPOSER_DEV_MODE', true );
    }
    
    /**
     * Test outline generation with valid parameters
     */
    public function test_outline_generation_success() {
        // Create test blueprint
        $blueprint_id = $this->create_test_blueprint();
        
        // Create test request
        $request = new WP_REST_Request( 'POST', '/ai-composer/v1/outline' );
        $request->set_param( 'blueprint_id', $blueprint_id );
        $request->set_param( 'brief', 'This is a test content brief for outline generation' );
        $request->set_param( 'audience', 'developers' );
        $request->set_param( 'tone', 'technical' );
        $request->set_param( 'alpha', 0.7 );
        
        $response = $this->outline_controller->generate_outline( $request );
        
        $this->assertInstanceOf( 'WP_REST_Response', $response );
        
        $data = $response->get_data();
        $this->assertArrayHasKey( 'sections', $data );
        $this->assertArrayHasKey( 'total_words', $data );
        $this->assertArrayHasKey( 'estimated_cost', $data );
        $this->assertIsArray( $data['sections'] );
        $this->assertGreaterThan( 0, count( $data['sections'] ) );
        
        // Verify section structure
        foreach ( $data['sections'] as $section ) {
            $this->assertArrayHasKey( 'id', $section );
            $this->assertArrayHasKey( 'heading', $section );
            $this->assertArrayHasKey( 'type', $section );
            $this->assertArrayHasKey( 'targetWords', $section );
            $this->assertArrayHasKey( 'needsImage', $section );
            $this->assertArrayHasKey( 'mode', $section );
            $this->assertArrayHasKey( 'block_preference', $section );
        }
    }
    
    /**
     * Test outline generation with missing blueprint ID
     */
    public function test_outline_generation_missing_blueprint() {
        $request = new WP_REST_Request( 'POST', '/ai-composer/v1/outline' );
        $request->set_param( 'brief', 'Test brief' );
        
        $response = $this->outline_controller->generate_outline( $request );
        
        $this->assertInstanceOf( 'WP_Error', $response );
        $this->assertEquals( 'outline_generation_failed', $response->get_error_code() );
    }
    
    /**
     * Test outline generation with invalid brief
     */




























































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































