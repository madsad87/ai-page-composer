<?php
/**
 * Blueprint Global Settings Meta Box Template
 * 
 * This template renders the global settings meta box for AI Blueprints.
 * It provides configuration options that apply to the entire blueprint.
 *
 * @package AIPageComposer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure we have default values
$global_settings = wp_parse_args( $global_settings, array(
    'generation_mode' => 'hybrid',
    'hybrid_alpha' => 0.7,
    'mvdb_namespaces' => array( 'content' ),
    'max_tokens_per_section' => 1000,
    'image_generation_enabled' => true,
    'seo_optimization' => true,
    'accessibility_checks' => true,
    'cost_limit_usd' => 5.0
) );
?>

<div class="ai-blueprint-global-settings">
    <div class="field-group">
        <label for="generation_mode"><?php esc_html_e( 'Generation Mode:', 'ai-page-composer' ); ?></label>
        <select id="generation_mode" name="global_settings[generation_mode]" class="generation-mode-select">
            <?php foreach ( $generation_modes as $mode => $label ) : ?>
                <option value="<?php echo esc_attr( $mode ); ?>" 
                        <?php selected( $global_settings['generation_mode'], $mode ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'How content should be generated: using MVDB data only, AI only, or a hybrid approach.', 'ai-page-composer' ); ?>
        </p>
    </div>
    
    <div class="field-group hybrid-alpha-group" style="<?php echo $global_settings['generation_mode'] !== 'hybrid' ? 'display:none;' : ''; ?>">
        <label for="hybrid_alpha"><?php esc_html_e( 'Hybrid Alpha:', 'ai-page-composer' ); ?></label>
        <div class="hybrid-alpha-control">
            <input type="range" 
                   id="hybrid_alpha" 
                   name="global_settings[hybrid_alpha]" 
                   value="<?php echo esc_attr( $global_settings['hybrid_alpha'] ); ?>"
                   min="0.0" max="1.0" step="0.1"
                   class="alpha-slider">
            <span class="alpha-value"><?php echo esc_html( $global_settings['hybrid_alpha'] ); ?></span>
        </div>
        <p class="description">
            <?php esc_html_e( '0.0 = More creative/generative, 1.0 = More grounded/data-driven', 'ai-page-composer' ); ?>
        </p>
    </div>
    
    <div class="field-group mvdb-namespaces-group">
        <label><?php esc_html_e( 'MVDB Namespaces:', 'ai-page-composer' ); ?></label>
        <?php 
        $selected_namespaces = $global_settings['mvdb_namespaces'];
        $available_namespaces = array(
            'content' => __( 'Content', 'ai-page-composer' ),
            'products' => __( 'Products', 'ai-page-composer' ),
            'docs' => __( 'Documentation', 'ai-page-composer' ),
            'knowledge' => __( 'Knowledge Base', 'ai-page-composer' )
        );
        ?>
        <div class="checkbox-group">
            <?php foreach ( $available_namespaces as $namespace => $label ) : ?>
                <label class="checkbox-label">
                    <input type="checkbox" 
                           name="global_settings[mvdb_namespaces][]" 
                           value="<?php echo esc_attr( $namespace ); ?>"
                           <?php checked( in_array( $namespace, $selected_namespaces, true ) ); ?>>
                    <?php echo esc_html( $label ); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="description">
            <?php esc_html_e( 'MVDB data sources to use for content generation. Select multiple sources for broader context.', 'ai-page-composer' ); ?>
        </p>
    </div>
    
    <div class="field-group">
        <label for="max_tokens_per_section"><?php esc_html_e( 'Max Tokens per Section:', 'ai-page-composer' ); ?></label>
        <input type="number" 
               id="max_tokens_per_section" 
               name="global_settings[max_tokens_per_section]" 
               value="<?php echo esc_attr( $global_settings['max_tokens_per_section'] ); ?>"
               min="100" max="5000" step="100"
               class="small-text">
        <p class="description">
            <?php esc_html_e( 'Maximum number of tokens to use per section (affects cost and generation time).', 'ai-page-composer' ); ?>
        </p>
    </div>
    
    <div class="field-group">
        <label for="cost_limit_usd"><?php esc_html_e( 'Cost Limit (USD):', 'ai-page-composer' ); ?></label>
        <input type="number" 
               id="cost_limit_usd" 
               name="global_settings[cost_limit_usd]" 
               value="<?php echo esc_attr( $global_settings['cost_limit_usd'] ); ?>"
               min="0.01" max="100.0" step="0.01"
               class="small-text">
        <p class="description">
            <?php esc_html_e( 'Maximum cost allowed for generating this blueprint. Generation will stop if limit is exceeded.', 'ai-page-composer' ); ?>
        </p>
    </div>
    
    <div class="field-group checkbox-group">
        <h4><?php esc_html_e( 'Features', 'ai-page-composer' ); ?></h4>
        
        <label>
            <input type="checkbox" 
                   name="global_settings[image_generation_enabled]" 
                   value="1" 
                   <?php checked( $global_settings['image_generation_enabled'] ); ?>>
            <?php esc_html_e( 'Enable Image Generation', 'ai-page-composer' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'Allow AI to generate images for sections that require media.', 'ai-page-composer' ); ?></p>
        
        <label>
            <input type="checkbox" 
                   name="global_settings[seo_optimization]" 
                   value="1" 
                   <?php checked( $global_settings['seo_optimization'] ); ?>>
            <?php esc_html_e( 'SEO Optimization', 'ai-page-composer' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'Optimize generated content for search engines (meta descriptions, keywords, etc.).', 'ai-page-composer' ); ?></p>
        
        <label>
            <input type="checkbox" 
                   name="global_settings[accessibility_checks]" 
                   value="1" 
                   <?php checked( $global_settings['accessibility_checks'] ); ?>>
            <?php esc_html_e( 'Accessibility Checks', 'ai-page-composer' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'Ensure generated content meets accessibility standards (alt text, heading structure, etc.).', 'ai-page-composer' ); ?></p>
    </div>
    
    <div class="global-settings-summary">
        <h4><?php esc_html_e( 'Configuration Summary', 'ai-page-composer' ); ?></h4>
        <div class="summary-content">
            <div class="summary-item">
                <span class="label"><?php esc_html_e( 'Mode:', 'ai-page-composer' ); ?></span>
                <span class="value generation-mode-display"><?php echo esc_html( $generation_modes[ $global_settings['generation_mode'] ] ?? '' ); ?></span>
            </div>
            <div class="summary-item">
                <span class="label"><?php esc_html_e( 'Cost Limit:', 'ai-page-composer' ); ?></span>
                <span class="value">$<?php echo esc_html( number_format( $global_settings['cost_limit_usd'], 2 ) ); ?></span>
            </div>
            <div class="summary-item">
                <span class="label"><?php esc_html_e( 'Max Tokens:', 'ai-page-composer' ); ?></span>
                <span class="value"><?php echo esc_html( number_format( $global_settings['max_tokens_per_section'] ) ); ?></span>
            </div>
        </div>
    </div>
</div>

<style>
.hybrid-alpha-control {
    display: flex;
    align-items: center;
    gap: 10px;
}

.alpha-slider {
    flex: 1;
    max-width: 200px;
}

.alpha-value {
    font-weight: bold;
    min-width: 30px;
}

.checkbox-group label {
    display: block;
    margin-bottom: 8px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mvdb-namespaces-group .checkbox-group {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin-bottom: 8px;
}

.global-settings-summary {
    margin-top: 20px;
    padding: 16px;
    background: #f9f9f9;
    border-radius: 4px;
    border-left: 4px solid #2196F3;
}

.summary-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
}

.summary-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.summary-item .label {
    font-size: 12px;
    color: #646970;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.summary-item .value {
    font-weight: 600;
    color: #1d2327;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Update hybrid alpha display
    $('#hybrid_alpha').on('input', function() {
        $('.alpha-value').text($(this).val());
    });
    
    // Show/hide hybrid alpha based on generation mode
    $('.generation-mode-select').on('change', function() {
        const mode = $(this).val();
        const $hybridGroup = $('.hybrid-alpha-group');
        
        if (mode === 'hybrid') {
            $hybridGroup.show();
        } else {
            $hybridGroup.hide();
        }
        
        // Update summary
        $('.generation-mode-display').text($(this).find(':selected').text());
    });
    
    // Update summary on cost limit change
    $('#cost_limit_usd').on('input', function() {
        $('.global-settings-summary .summary-item:nth-child(2) .value').text('$' + parseFloat($(this).val()).toFixed(2));
    });
    
    // Update summary on max tokens change
    $('#max_tokens_per_section').on('input', function() {
        $('.global-settings-summary .summary-item:nth-child(3) .value').text(parseInt($(this).val()).toLocaleString());
    });
});
</script>