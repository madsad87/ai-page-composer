<?php
/**
 * AI Page Composer Settings Page Template
 * 
 * This template renders the main settings page for AI Page Composer with
 * tabbed navigation and comprehensive form sections including the Block
 * Preferences panel.
 * 
 * @package AIPageComposer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Security check
AIPageComposer\Utils\Security_Helper::verify_request( false );
?>

<div class="wrap ai-composer-settings">
    <h1><?php esc_html_e( 'AI Page Composer Settings', 'ai-page-composer' ); ?></h1>
    
    <?php settings_errors( 'ai_composer_settings' ); ?>
    
    <form method="post" action="options.php" id="ai-composer-settings-form">
        <?php
        settings_fields( AIPageComposer\Admin\Settings_Manager::SETTINGS_GROUP );
        AIPageComposer\Utils\Security_Helper::nonce_field();
        ?>
        
        <nav class="nav-tab-wrapper ai-composer-tabs">
            <a href="#api-config" class="nav-tab nav-tab-active" data-target="#api-config-panel">
                <?php esc_html_e( 'API Configuration', 'ai-page-composer' ); ?>
            </a>
            <a href="#mvdb-config" class="nav-tab" data-target="#mvdb-panel">
                <?php esc_html_e( 'Vector Database', 'ai-page-composer' ); ?>
            </a>
            <a href="#generation" class="nav-tab" data-target="#generation-panel">
                <?php esc_html_e( 'Generation Defaults', 'ai-page-composer' ); ?>
            </a>
            <a href="#content-policy" class="nav-tab" data-target="#content-policy-panel">
                <?php esc_html_e( 'Content Policies', 'ai-page-composer' ); ?>
            </a>
            <a href="#block-preferences" class="nav-tab" data-target="#block-preferences-panel">
                <?php esc_html_e( 'Block Preferences', 'ai-page-composer' ); ?>
            </a>
            <a href="#section-generation" class="nav-tab" data-target="#section-generation-panel">
                <?php esc_html_e( 'Section Generation', 'ai-page-composer' ); ?>
            </a>
            <a href="#cost-management" class="nav-tab" data-target="#cost-management-panel">
                <?php esc_html_e( 'Cost Management', 'ai-page-composer' ); ?>
            </a>
        </nav>
        
        <div class="ai-composer-tab-content">
            
            <!-- API Configuration Panel -->
            <div id="api-config-panel" class="ai-composer-panel active">
                <h2><?php esc_html_e( 'API Configuration', 'ai-page-composer' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Configure your API keys for AI services. All keys are stored securely and never transmitted in plain text.', 'ai-page-composer' ); ?>
                </p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="openai_api_key"><?php esc_html_e( 'OpenAI API Key', 'ai-page-composer' ); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="openai_api_key" 
                                   name="ai_composer_settings[api_settings][openai_api_key]" 
                                   value="<?php echo esc_attr( $settings['api_settings']['openai_api_key'] ?? '' ); ?>" 
                                   class="regular-text" 
                                   required />
                            <button type="button" class="button button-secondary toggle-api-key-visibility">Show</button>
                            <p class="description">
                                <?php 
                                printf(
                                    /* translators: %s: OpenAI website URL */
                                    esc_html__( 'Get your API key from %s', 'ai-page-composer' ),
                                    '<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mvdb_api_key"><?php esc_html_e( 'Vector Database API Key (Legacy)', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="mvdb_api_key" 
                                   name="ai_composer_settings[api_settings][mvdb_api_key]" 
                                   value="<?php echo esc_attr( $settings['api_settings']['mvdb_api_key'] ?? '' ); ?>" 
                                   class="regular-text" />
                            <button type="button" class="button button-secondary toggle-api-key-visibility">Show</button>
                            <p class="description">
                                <?php 
                                printf(
                                    /* translators: %s: Vector Database tab link */
                                    esc_html__( 'Legacy API key field. Please use the %s tab for full MVDB configuration.', 'ai-page-composer' ),
                                    '<a href="#mvdb-config" class="nav-tab-link">' . esc_html__( 'Vector Database', 'ai-page-composer' ) . '</a>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="image_api_key"><?php esc_html_e( 'Image Generation API Key', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="image_api_key" 
                                   name="ai_composer_settings[api_settings][image_api_key]" 
                                   value="<?php echo esc_attr( $settings['api_settings']['image_api_key'] ?? '' ); ?>" 
                                   class="regular-text" />
                            <button type="button" class="button button-secondary toggle-api-key-visibility">Show</button>
                            <p class="description">
                                <?php esc_html_e( 'Optional: API key for image generation (DALL-E, Midjourney, etc.)', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php esc_html_e( 'API Status', 'ai-page-composer' ); ?></h3>
                <div id="api-status-check">
                    <button type="button" class="button button-secondary check-api-status">
                        <?php esc_html_e( 'Test API Connections', 'ai-page-composer' ); ?>
                    </button>
                    <div id="api-status-results"></div>
                </div>
            </div>
            
            <!-- Vector Database Configuration Panel -->
            <div id="mvdb-panel" class="ai-composer-panel">
                <h2><?php esc_html_e( 'Vector Database Configuration', 'ai-page-composer' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Configure your vector database settings for content retrieval and semantic search.', 'ai-page-composer' ); ?>
                </p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="mvdb_api_url"><?php esc_html_e( 'MVDB API URL', 'ai-page-composer' ); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="mvdb_api_url" 
                                   name="ai_composer_settings[mvdb_settings][api_url]" 
                                   value="<?php echo esc_attr( $settings['mvdb_settings']['api_url'] ?? 'https://api.wpengine.com/smart-search/v1' ); ?>" 
                                   class="regular-text" 
                                   required />
                            <p class="description">
                                <?php 
                                printf(
                                    /* translators: %s: WP Engine Smart Search URL */
                                    esc_html__( 'WP Engine Smart Search API endpoint. Default: %s', 'ai-page-composer' ),
                                    '<code>https://api.wpengine.com/smart-search/v1</code>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mvdb_access_token"><?php esc_html_e( 'MVDB Access Token', 'ai-page-composer' ); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="mvdb_access_token" 
                                   name="ai_composer_settings[mvdb_settings][access_token]" 
                                   value="<?php echo esc_attr( $settings['mvdb_settings']['access_token'] ?? '' ); ?>" 
                                   class="regular-text" 
                                   required />
                            <button type="button" class="button button-secondary toggle-api-key-visibility">Show</button>
                            <p class="description">
                                <?php esc_html_e( 'Authentication token for your WP Engine Smart Search service.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mvdb_cache_ttl"><?php esc_html_e( 'Cache TTL (seconds)', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="mvdb_cache_ttl" 
                                   name="ai_composer_settings[mvdb_settings][cache_ttl]" 
                                   value="<?php echo esc_attr( $settings['mvdb_settings']['cache_ttl'] ?? 3600 ); ?>" 
                                   min="300" max="86400" step="300" 
                                   class="small-text" />
                            <p class="description">
                                <?php esc_html_e( 'How long to cache MVDB responses (300-86400 seconds). Default: 3600 (1 hour).', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mvdb_timeout_seconds"><?php esc_html_e( 'Request Timeout (seconds)', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="mvdb_timeout_seconds" 
                                   name="ai_composer_settings[mvdb_settings][timeout_seconds]" 
                                   value="<?php echo esc_attr( $settings['mvdb_settings']['timeout_seconds'] ?? 30 ); ?>" 
                                   min="5" max="120" step="5" 
                                   class="small-text" />
                            <p class="description">
                                <?php esc_html_e( 'Maximum time to wait for MVDB API responses (5-120 seconds). Default: 30.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mvdb_enable_debug_logging"><?php esc_html_e( 'Debug Logging', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="mvdb_enable_debug_logging"
                                       name="ai_composer_settings[mvdb_settings][enable_debug_logging]" 
                                       value="1"
                                       <?php checked( $settings['mvdb_settings']['enable_debug_logging'] ?? false ); ?> />
                                <?php esc_html_e( 'Enable detailed MVDB request/response logging', 'ai-page-composer' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Log detailed information about MVDB requests for troubleshooting. Requires WP_DEBUG_LOG enabled.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php esc_html_e( 'MVDB Connection Test', 'ai-page-composer' ); ?></h3>
                <div id="mvdb-connection-test">
                    <button type="button" class="button button-secondary test-mvdb-connection">
                        <?php esc_html_e( 'Test MVDB Connection', 'ai-page-composer' ); ?>
                    </button>
                    <div id="mvdb-connection-results"></div>
                </div>
            </div>
            
            <!-- Generation Defaults Panel -->
            <div id="generation-panel" class="ai-composer-panel">
                <h2><?php esc_html_e( 'Generation Defaults', 'ai-page-composer' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Set default parameters for AI content generation. These can be overridden per-generation.', 'ai-page-composer' ); ?>
                </p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="default_mode"><?php esc_html_e( 'Default Generation Mode', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <select id="default_mode" name="ai_composer_settings[generation_defaults][default_mode]">
                                <option value="grounded" <?php selected( $settings['generation_defaults']['default_mode'] ?? 'hybrid', 'grounded' ); ?>>
                                    <?php esc_html_e( 'Grounded (Data-based)', 'ai-page-composer' ); ?>
                                </option>
                                <option value="hybrid" <?php selected( $settings['generation_defaults']['default_mode'] ?? 'hybrid', 'hybrid' ); ?>>
                                    <?php esc_html_e( 'Hybrid (Mixed)', 'ai-page-composer' ); ?>
                                </option>
                                <option value="generative" <?php selected( $settings['generation_defaults']['default_mode'] ?? 'hybrid', 'generative' ); ?>>
                                    <?php esc_html_e( 'Generative (Creative)', 'ai-page-composer' ); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Grounded: Uses only retrieved data. Hybrid: Mixes data with generated content. Generative: Primarily AI-generated.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="alpha_weight"><?php esc_html_e( 'Alpha Weight', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <input type="range" 
                                   id="alpha_weight" 
                                   name="ai_composer_settings[generation_defaults][alpha_weight]" 
                                   value="<?php echo esc_attr( $settings['generation_defaults']['alpha_weight'] ?? 0.7 ); ?>" 
                                   min="0" max="1" step="0.1" 
                                   class="range-input" />
                            <span class="range-value"><?php echo esc_html( $settings['generation_defaults']['alpha_weight'] ?? 0.7 ); ?></span>
                            <p class="description">
                                <?php esc_html_e( 'Balance between retrieved data (0) and generated content (1). 0.7 is recommended.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="k_value"><?php esc_html_e( 'K Value (Retrieval Count)', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="k_value" 
                                   name="ai_composer_settings[generation_defaults][k_value]" 
                                   value="<?php echo esc_attr( $settings['generation_defaults']['k_value'] ?? 10 ); ?>" 
                                   min="1" max="50" 
                                   class="small-text" />
                            <p class="description">
                                <?php esc_html_e( 'Number of relevant content pieces to retrieve from vector database (1-50).', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="min_score"><?php esc_html_e( 'Minimum Relevance Score', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <input type="range" 
                                   id="min_score" 
                                   name="ai_composer_settings[generation_defaults][min_score]" 
                                   value="<?php echo esc_attr( $settings['generation_defaults']['min_score'] ?? 0.5 ); ?>" 
                                   min="0" max="1" step="0.1" 
                                   class="range-input" />
                            <span class="range-value"><?php echo esc_html( $settings['generation_defaults']['min_score'] ?? 0.5 ); ?></span>
                            <p class="description">
                                <?php esc_html_e( 'Minimum relevance score for retrieved content (0.0-1.0). Higher values = more relevant content.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="default_namespaces"><?php esc_html_e( 'Default Namespaces', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <?php
                            $current_namespaces = $settings['generation_defaults']['default_namespaces'] ?? array( 'content' );
                            $available_namespaces = array(
                                'content' => __( 'Content', 'ai-page-composer' ),
                                'products' => __( 'Products', 'ai-page-composer' ),
                                'docs' => __( 'Documentation', 'ai-page-composer' ),
                                'knowledge' => __( 'Knowledge Base', 'ai-page-composer' ),
                            );
                            
                            foreach ( $available_namespaces as $namespace => $label ) :
                            ?>
                            <label>
                                <input type="checkbox" 
                                       name="ai_composer_settings[generation_defaults][default_namespaces][]" 
                                       value="<?php echo esc_attr( $namespace ); ?>"
                                       <?php checked( in_array( $namespace, $current_namespaces, true ) ); ?> />
                                <?php echo esc_html( $label ); ?>
                            </label><br>
                            <?php endforeach; ?>
                            <p class="description">
                                <?php esc_html_e( 'Select which content namespaces to search by default.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Content Policies Panel -->
            <div id="content-policy-panel" class="ai-composer-panel">
                <h2><?php esc_html_e( 'Content Policies', 'ai-page-composer' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Define content generation policies and restrictions to ensure quality and compliance.', 'ai-page-composer' ); ?>
                </p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="image_generation_policy"><?php esc_html_e( 'Image Generation Policy', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <select id="image_generation_policy" name="ai_composer_settings[content_policies][image_generation_policy]">
                                <option value="always" <?php selected( $settings['content_policies']['image_generation_policy'] ?? 'auto', 'always' ); ?>>
                                    <?php esc_html_e( 'Always Generate', 'ai-page-composer' ); ?>
                                </option>
                                <option value="auto" <?php selected( $settings['content_policies']['image_generation_policy'] ?? 'auto', 'auto' ); ?>>
                                    <?php esc_html_e( 'Auto (when appropriate)', 'ai-page-composer' ); ?>
                                </option>
                                <option value="manual" <?php selected( $settings['content_policies']['image_generation_policy'] ?? 'auto', 'manual' ); ?>>
                                    <?php esc_html_e( 'Manual Selection', 'ai-page-composer' ); ?>
                                </option>
                                <option value="never" <?php selected( $settings['content_policies']['image_generation_policy'] ?? 'auto', 'never' ); ?>>
                                    <?php esc_html_e( 'Never Generate', 'ai-page-composer' ); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'When to generate images for content sections.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="internal_linking_enabled"><?php esc_html_e( 'Internal Linking', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="internal_linking_enabled"
                                       name="ai_composer_settings[content_policies][internal_linking_enabled]" 
                                       value="1"
                                       <?php checked( $settings['content_policies']['internal_linking_enabled'] ?? true ); ?> />
                                <?php esc_html_e( 'Enable automatic internal linking', 'ai-page-composer' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Automatically add links to related internal content.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="max_internal_links"><?php esc_html_e( 'Max Internal Links', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="max_internal_links" 
                                   name="ai_composer_settings[content_policies][max_internal_links]" 
                                   value="<?php echo esc_attr( $settings['content_policies']['max_internal_links'] ?? 3 ); ?>" 
                                   min="0" max="10" 
                                   class="small-text" />
                            <p class="description">
                                <?php esc_html_e( 'Maximum number of internal links per content section (0-10).', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="citation_required"><?php esc_html_e( 'Citations', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="citation_required"
                                       name="ai_composer_settings[content_policies][citation_required]" 
                                       value="1"
                                       <?php checked( $settings['content_policies']['citation_required'] ?? true ); ?> />
                                <?php esc_html_e( 'Require citations for generated content', 'ai-page-composer' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Include source citations when using retrieved data.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Block Preferences Panel -->
            <div id="block-preferences-panel" class="ai-composer-panel">
                <?php
                // Use the Block Preferences class to render the panel
                $this->render_block_preferences_tab( $settings, $detected_plugins, $section_types );
                ?>
            </div>
            
            <!-- Section Generation Panel -->
            <div id="section-generation-panel" class="ai-composer-panel">
                <h2><?php esc_html_e( 'Section Generation Settings', 'ai-page-composer' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Configure settings specific to section generation API and content creation.', 'ai-page-composer' ); ?>
                </p>
                
                <h3><?php esc_html_e( 'Generation Modes', 'ai-page-composer' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="section_default_mode"><?php esc_html_e( 'Default Section Mode', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <select id="section_default_mode" name="ai_composer_settings[section_generation][default_mode]">
                                <option value="grounded" <?php selected( $settings['section_generation']['default_mode'] ?? 'hybrid', 'grounded' ); ?>>
                                    <?php esc_html_e( 'Grounded - Use only retrieved context', 'ai-page-composer' ); ?>
                                </option>
                                <option value="hybrid" <?php selected( $settings['section_generation']['default_mode'] ?? 'hybrid', 'hybrid' ); ?>>
                                    <?php esc_html_e( 'Hybrid - Balance context and generation', 'ai-page-composer' ); ?>
                                </option>
                                <option value="generative" <?php selected( $settings['section_generation']['default_mode'] ?? 'hybrid', 'generative' ); ?>>
                                    <?php esc_html_e( 'Generative - Pure AI generation', 'ai-page-composer' ); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Default generation mode for new sections. Can be overridden per section.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="section_alpha"><?php esc_html_e( 'Default Alpha Weight', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <input type="range" 
                                   id="section_alpha" 
                                   name="ai_composer_settings[section_generation][alpha]" 
                                   value="<?php echo esc_attr( $settings['section_generation']['alpha'] ?? 0.7 ); ?>" 
                                   min="0" max="1" step="0.1" 
                                   class="range-input" />
                            <span class="range-value"><?php echo esc_html( $settings['section_generation']['alpha'] ?? 0.7 ); ?></span>
                            <p class="description">
                                <?php esc_html_e( 'Balance between context (0.0) and creativity (1.0) in hybrid mode.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php esc_html_e( 'Image Generation', 'ai-page-composer' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="image_policy"><?php esc_html_e( 'Default Image Policy', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <select id="image_policy" name="ai_composer_settings[section_generation][image_policy]">
                                <option value="none" <?php selected( $settings['section_generation']['image_policy'] ?? 'optional', 'none' ); ?>>
                                    <?php esc_html_e( 'None - No images', 'ai-page-composer' ); ?>
                                </option>
                                <option value="optional" <?php selected( $settings['section_generation']['image_policy'] ?? 'optional', 'optional' ); ?>>
                                    <?php esc_html_e( 'Optional - Include when appropriate', 'ai-page-composer' ); ?>
                                </option>
                                <option value="required" <?php selected( $settings['section_generation']['image_policy'] ?? 'optional', 'required' ); ?>>
                                    <?php esc_html_e( 'Required - Always include images', 'ai-page-composer' ); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Default policy for including images in generated sections.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="image_style"><?php esc_html_e( 'Default Image Style', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <select id="image_style" name="ai_composer_settings[section_generation][image_style]">
                                <option value="photographic" <?php selected( $settings['section_generation']['image_style'] ?? 'photographic', 'photographic' ); ?>>
                                    <?php esc_html_e( 'Photographic', 'ai-page-composer' ); ?>
                                </option>
                                <option value="illustration" <?php selected( $settings['section_generation']['image_style'] ?? 'photographic', 'illustration' ); ?>>
                                    <?php esc_html_e( 'Illustration', 'ai-page-composer' ); ?>
                                </option>
                                <option value="abstract" <?php selected( $settings['section_generation']['image_style'] ?? 'photographic', 'abstract' ); ?>>
                                    <?php esc_html_e( 'Abstract', 'ai-page-composer' ); ?>
                                </option>
                                <option value="minimalist" <?php selected( $settings['section_generation']['image_style'] ?? 'photographic', 'minimalist' ); ?>>
                                    <?php esc_html_e( 'Minimalist', 'ai-page-composer' ); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Default style for generated images.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php esc_html_e( 'Citations & Attribution', 'ai-page-composer' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="citations_enabled"><?php esc_html_e( 'Enable Citations', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="citations_enabled"
                                       name="ai_composer_settings[section_generation][citations_enabled]" 
                                       value="1"
                                       <?php checked( $settings['section_generation']['citations_enabled'] ?? true ); ?> />
                                <?php esc_html_e( 'Automatically extract and format citations from generated content', 'ai-page-composer' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Links generated content back to MVDB source chunks for transparency.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="citation_style"><?php esc_html_e( 'Citation Style', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <select id="citation_style" name="ai_composer_settings[section_generation][citation_style]">
                                <option value="inline" <?php selected( $settings['section_generation']['citation_style'] ?? 'inline', 'inline' ); ?>>
                                    <?php esc_html_e( 'Inline [1], [2]', 'ai-page-composer' ); ?>
                                </option>
                                <option value="footnote" <?php selected( $settings['section_generation']['citation_style'] ?? 'inline', 'footnote' ); ?>>
                                    <?php esc_html_e( 'Footnotes with references', 'ai-page-composer' ); ?>
                                </option>
                                <option value="bibliography" <?php selected( $settings['section_generation']['citation_style'] ?? 'inline', 'bibliography' ); ?>>
                                    <?php esc_html_e( 'Bibliography list', 'ai-page-composer' ); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'How citations should be formatted in the generated content.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php esc_html_e( 'Caching', 'ai-page-composer' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_section_cache"><?php esc_html_e( 'Enable Section Caching', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="enable_section_cache"
                                       name="ai_composer_settings[cache_settings][enable_section_cache]" 
                                       value="1"
                                       <?php checked( $settings['cache_settings']['enable_section_cache'] ?? true ); ?> />
                                <?php esc_html_e( 'Cache generated sections to reduce API costs and improve performance', 'ai-page-composer' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Identical generation requests will return cached results.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="section_cache_ttl"><?php esc_html_e( 'Cache Duration (seconds)', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="section_cache_ttl" 
                                   name="ai_composer_settings[cache_settings][section_cache_ttl]" 
                                   value="<?php echo esc_attr( $settings['cache_settings']['section_cache_ttl'] ?? 3600 ); ?>" 
                                   min="300" max="86400" 
                                   class="small-text" />
                            <p class="description">
                                <?php esc_html_e( 'How long to cache section results (300-86400 seconds). Default: 3600 (1 hour).', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php esc_html_e( 'Section Generation Tools', 'ai-page-composer' ); ?></h3>
                <div class="section-generation-tools">
                    <button type="button" class="button button-secondary" id="test-section-generation">
                        <?php esc_html_e( 'Test Section Generation', 'ai-page-composer' ); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="clear-section-cache">
                        <?php esc_html_e( 'Clear Section Cache', 'ai-page-composer' ); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="view-cache-stats">
                        <?php esc_html_e( 'View Cache Statistics', 'ai-page-composer' ); ?>
                    </button>
                </div>
                
                <div id="section-generation-test-results" style="display: none; margin-top: 20px;">
                    <h4><?php esc_html_e( 'Test Results', 'ai-page-composer' ); ?></h4>
                    <div id="test-results-content"></div>
                </div>
            </div>
            
            <!-- Cost Management Panel -->
            <div id="cost-management-panel" class="ai-composer-panel">
                <h2><?php esc_html_e( 'Cost Management', 'ai-page-composer' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Manage API usage costs and set budget limits to control spending.', 'ai-page-composer' ); ?>
                </p>
                
                <!-- Current Usage Dashboard -->
                <div class="cost-dashboard">
                    <div class="cost-card">
                        <h4><?php esc_html_e( 'Daily Usage', 'ai-page-composer' ); ?></h4>
                        <p class="amount daily-cost-amount">$<?php echo esc_html( number_format( $daily_costs, 2 ) ); ?></p>
                    </div>
                    <div class="cost-card">
                        <h4><?php esc_html_e( 'Monthly Usage', 'ai-page-composer' ); ?></h4>
                        <p class="amount monthly-cost-amount">$<?php echo esc_html( number_format( $monthly_costs, 2 ) ); ?></p>
                    </div>
                    <div class="cost-card">
                        <h4><?php esc_html_e( 'Daily Limit', 'ai-page-composer' ); ?></h4>
                        <p class="amount">$<?php echo esc_html( number_format( $settings['cost_management']['daily_budget_usd'] ?? 10.0, 2 ) ); ?></p>
                    </div>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="daily_budget_usd"><?php esc_html_e( 'Daily Budget (USD)', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="daily_budget_usd" 
                                   name="ai_composer_settings[cost_management][daily_budget_usd]" 
                                   value="<?php echo esc_attr( $settings['cost_management']['daily_budget_usd'] ?? 10.0 ); ?>" 
                                   min="0.01" max="1000" step="0.01" 
                                   class="small-text" />
                            <p class="description">
                                <?php esc_html_e( 'Maximum daily spending on API calls ($0.01 - $1000).', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="per_run_limit_usd"><?php esc_html_e( 'Per-run Limit (USD)', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="per_run_limit_usd" 
                                   name="ai_composer_settings[cost_management][per_run_limit_usd]" 
                                   value="<?php echo esc_attr( $settings['cost_management']['per_run_limit_usd'] ?? 2.0 ); ?>" 
                                   min="0.01" max="100" step="0.01" 
                                   class="small-text" />
                            <p class="description">
                                <?php esc_html_e( 'Maximum cost per individual generation run ($0.01 - $100).', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="token_limit_per_section"><?php esc_html_e( 'Token Limit per Section', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="token_limit_per_section" 
                                   name="ai_composer_settings[cost_management][token_limit_per_section]" 
                                   value="<?php echo esc_attr( $settings['cost_management']['token_limit_per_section'] ?? 1000 ); ?>" 
                                   min="100" max="5000" 
                                   class="small-text" />
                            <p class="description">
                                <?php esc_html_e( 'Maximum tokens to generate per content section (100-5000).', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cost_alerts_enabled"><?php esc_html_e( 'Cost Alerts', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="cost_alerts_enabled"
                                       name="ai_composer_settings[cost_management][cost_alerts_enabled]" 
                                       value="1"
                                       <?php checked( $settings['cost_management']['cost_alerts_enabled'] ?? true ); ?> />
                                <?php esc_html_e( 'Send email alerts when approaching budget limits', 'ai-page-composer' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Receive notifications at 80% and 100% of daily budget.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="budget_reset_schedule"><?php esc_html_e( 'Budget Reset Schedule', 'ai-page-composer' ); ?></label>
                        </th>
                        <td>
                            <select id="budget_reset_schedule" name="ai_composer_settings[cost_management][budget_reset_schedule]">
                                <option value="daily" <?php selected( $settings['cost_management']['budget_reset_schedule'] ?? 'daily', 'daily' ); ?>>
                                    <?php esc_html_e( 'Daily', 'ai-page-composer' ); ?>
                                </option>
                                <option value="weekly" <?php selected( $settings['cost_management']['budget_reset_schedule'] ?? 'daily', 'weekly' ); ?>>
                                    <?php esc_html_e( 'Weekly', 'ai-page-composer' ); ?>
                                </option>
                                <option value="monthly" <?php selected( $settings['cost_management']['budget_reset_schedule'] ?? 'daily', 'monthly' ); ?>>
                                    <?php esc_html_e( 'Monthly', 'ai-page-composer' ); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'How often to reset usage counters.', 'ai-page-composer' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div style="margin-top: 20px;">
                    <button type="button" class="button button-secondary refresh-cost-stats">
                        <?php esc_html_e( 'Refresh Statistics', 'ai-page-composer' ); ?>
                    </button>
                </div>
            </div>
            
        </div>
        
        <p class="submit">
            <?php submit_button( __( 'Save Settings', 'ai-page-composer' ), 'primary', 'submit', false ); ?>
            <button type="button" id="reset-settings" class="button button-secondary" style="margin-left: 10px;">
                <?php esc_html_e( 'Reset to Defaults', 'ai-page-composer' ); ?>
            </button>
        </p>
    </form>
</div>