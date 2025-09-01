<?php
/**
 * Blueprint Section Row Template
 * 
 * This template renders a single section row in the sections meta box.
 * It's used both for existing sections and as a template for new sections.
 *
 * @package AIPageComposer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure we have default values
$section = wp_parse_args( $section, array(
    'id' => '',
    'type' => 'content',
    'heading' => '',
    'heading_level' => 2,
    'word_target' => 150,
    'media_policy' => 'optional',
    'internal_links' => 2,
    'citations_required' => true,
    'tone' => 'professional',
    'allowed_blocks' => array(),
    'block_preferences' => array(
        'preferred_plugin' => 'auto',
        'primary_block' => '',
        'fallback_blocks' => array(),
        'pattern_preference' => '',
        'custom_attributes' => array()
    )
) );
?>

<div class="section-row" data-index="<?php echo esc_attr( $index ); ?>">
    <div class="section-header">
        <div class="section-handle">
            <span class="dashicons dashicons-menu"></span>
        </div>
        
        <h5 class="section-title">
            <span class="section-number"><?php echo esc_html( is_numeric( $index ) ? $index + 1 : '{{number}}' ); ?>.</span>
            <input type="text" 
                   name="sections[<?php echo esc_attr( $index ); ?>][heading]" 
                   value="<?php echo esc_attr( $section['heading'] ); ?>"
                   placeholder="<?php esc_attr_e( 'Enter section heading...', 'ai-page-composer' ); ?>"
                   class="section-heading-input"
                   required>
        </h5>
        
        <div class="section-controls">
            <button type="button" class="button-link section-toggle" title="<?php esc_attr_e( 'Toggle section details', 'ai-page-composer' ); ?>">
                <span class="dashicons dashicons-arrow-up-alt2"></span>
            </button>
            <button type="button" class="button-link move-section-up" title="<?php esc_attr_e( 'Move Up', 'ai-page-composer' ); ?>">
                <span class="dashicons dashicons-arrow-up"></span>
            </button>
            <button type="button" class="button-link move-section-down" title="<?php esc_attr_e( 'Move Down', 'ai-page-composer' ); ?>">
                <span class="dashicons dashicons-arrow-down"></span>
            </button>
            <button type="button" class="button-link duplicate-section" title="<?php esc_attr_e( 'Duplicate Section', 'ai-page-composer' ); ?>">
                <span class="dashicons dashicons-admin-page"></span>
            </button>
            <button type="button" class="button-link remove-section" title="<?php esc_attr_e( 'Remove Section', 'ai-page-composer' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
    </div>
    
    <div class="section-content">
        <!-- Hidden ID field -->
        <input type="hidden" name="sections[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $section['id'] ?: 'section-' . ( is_numeric( $index ) ? $index + 1 : uniqid() ) ); ?>" class="section-id-field">
        
        <div class="section-row-fields">
            <div class="field-group">
                <label><?php esc_html_e( 'Section Type:', 'ai-page-composer' ); ?></label>
                <select name="sections[<?php echo esc_attr( $index ); ?>][type]" class="section-type-select">
                    <?php foreach ( $section_types as $type => $label ) : ?>
                        <option value="<?php echo esc_attr( $type ); ?>" 
                                <?php selected( $section['type'], $type ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e( 'The type of content section determines the generated structure and block selection.', 'ai-page-composer' ); ?></p>
            </div>
            
            <div class="field-group">
                <label><?php esc_html_e( 'Word Target:', 'ai-page-composer' ); ?></label>
                <input type="number" 
                       name="sections[<?php echo esc_attr( $index ); ?>][word_target]" 
                       value="<?php echo esc_attr( $section['word_target'] ); ?>"
                       min="10" max="2000" step="10"
                       class="small-text">
                <p class="description"><?php esc_html_e( 'Target word count for this section (10-2000 words).', 'ai-page-composer' ); ?></p>
            </div>
            
            <div class="field-group">
                <label><?php esc_html_e( 'Heading Level:', 'ai-page-composer' ); ?></label>
                <select name="sections[<?php echo esc_attr( $index ); ?>][heading_level]" class="small-text">
                    <?php for ( $i = 1; $i <= 6; $i++ ) : ?>
                        <option value="<?php echo $i; ?>" 
                                <?php selected( $section['heading_level'], $i ); ?>>
                            H<?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <p class="description"><?php esc_html_e( 'HTML heading level for this section.', 'ai-page-composer' ); ?></p>
            </div>
            
            <div class="field-group">
                <label><?php esc_html_e( 'Media Policy:', 'ai-page-composer' ); ?></label>
                <select name="sections[<?php echo esc_attr( $index ); ?>][media_policy]">
                    <option value="none" <?php selected( $section['media_policy'], 'none' ); ?>>
                        <?php esc_html_e( 'No Images', 'ai-page-composer' ); ?>
                    </option>
                    <option value="optional" <?php selected( $section['media_policy'], 'optional' ); ?>>
                        <?php esc_html_e( 'Optional', 'ai-page-composer' ); ?>
                    </option>
                    <option value="required" <?php selected( $section['media_policy'], 'required' ); ?>>
                        <?php esc_html_e( 'Required', 'ai-page-composer' ); ?>
                    </option>
                </select>
                <p class="description"><?php esc_html_e( 'Whether images should be included in this section.', 'ai-page-composer' ); ?></p>
            </div>
        </div>
        
        <!-- Block Preferences Section -->
        <div class="block-preferences-section">
            <h6><?php esc_html_e( 'Block Preferences', 'ai-page-composer' ); ?></h6>
            
            <div class="preference-fields">
                <div class="field-group">
                    <label><?php esc_html_e( 'Preferred Plugin:', 'ai-page-composer' ); ?></label>
                    <select name="sections[<?php echo esc_attr( $index ); ?>][block_preferences][preferred_plugin]" 
                            class="preferred-plugin-select">
                        <option value="auto" <?php selected( $section['block_preferences']['preferred_plugin'] ?? 'auto', 'auto' ); ?>>
                            <?php esc_html_e( 'Auto (Best Available)', 'ai-page-composer' ); ?>
                        </option>
                        <option value="core" <?php selected( $section['block_preferences']['preferred_plugin'] ?? 'auto', 'core' ); ?>>
                            <?php esc_html_e( 'WordPress Core', 'ai-page-composer' ); ?>
                        </option>
                        <?php foreach ( $detected_plugins as $key => $plugin ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" 
                                    <?php selected( $section['block_preferences']['preferred_plugin'] ?? 'auto', $key ); ?>
                                    <?php disabled( ! $plugin['active'] ); ?>>
                                <?php echo esc_html( $plugin['name'] ); ?>
                                <?php if ( ! $plugin['active'] ) : ?>
                                    <?php esc_html_e( '(Inactive)', 'ai-page-composer' ); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Preferred block plugin for generating this section.', 'ai-page-composer' ); ?></p>
                </div>
                
                <div class="field-group">
                    <label><?php esc_html_e( 'Primary Block:', 'ai-page-composer' ); ?></label>
                    <input type="text" 
                           name="sections[<?php echo esc_attr( $index ); ?>][block_preferences][primary_block]" 
                           value="<?php echo esc_attr( $section['block_preferences']['primary_block'] ?? '' ); ?>"
                           placeholder="e.g., core/cover, kadence/rowlayout"
                           class="regular-text">
                    <p class="description">
                        <?php esc_html_e( 'Specific block name to use (overrides plugin preference). Leave empty for automatic selection.', 'ai-page-composer' ); ?>
                    </p>
                </div>
                
                <div class="field-group">
                    <label><?php esc_html_e( 'Fallback Blocks:', 'ai-page-composer' ); ?></label>
                    <input type="text" 
                           name="sections[<?php echo esc_attr( $index ); ?>][block_preferences][fallback_blocks]" 
                           value="<?php echo esc_attr( implode( ', ', $section['block_preferences']['fallback_blocks'] ?? array() ) ); ?>"
                           placeholder="core/paragraph, core/media-text"
                           class="regular-text">
                    <p class="description">
                        <?php esc_html_e( 'Comma-separated list of fallback blocks if primary block is unavailable.', 'ai-page-composer' ); ?>
                    </p>
                </div>
                
                <div class="field-group">
                    <label><?php esc_html_e( 'Pattern Preference:', 'ai-page-composer' ); ?></label>
                    <input type="text" 
                           name="sections[<?php echo esc_attr( $index ); ?>][block_preferences][pattern_preference]" 
                           value="<?php echo esc_attr( $section['block_preferences']['pattern_preference'] ?? '' ); ?>"
                           placeholder="core/hero-with-image"
                           class="regular-text">
                    <p class="description">
                        <?php esc_html_e( 'Preferred block pattern to use for this section structure.', 'ai-page-composer' ); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Advanced Section Options -->
        <details class="advanced-section-options">
            <summary><?php esc_html_e( 'Advanced Options', 'ai-page-composer' ); ?></summary>
            
            <div class="advanced-fields">
                <div class="field-group">
                    <label><?php esc_html_e( 'Internal Links:', 'ai-page-composer' ); ?></label>
                    <input type="number" 
                           name="sections[<?php echo esc_attr( $index ); ?>][internal_links]" 
                           value="<?php echo esc_attr( $section['internal_links'] ); ?>"
                           min="0" max="10" step="1"
                           class="small-text">
                    <p class="description"><?php esc_html_e( 'Target number of internal links to include in this section.', 'ai-page-composer' ); ?></p>
                </div>
                
                <div class="field-group">
                    <label><?php esc_html_e( 'Tone:', 'ai-page-composer' ); ?></label>
                    <select name="sections[<?php echo esc_attr( $index ); ?>][tone]">
                        <?php foreach ( $tone_options as $tone => $label ) : ?>
                            <option value="<?php echo esc_attr( $tone ); ?>" 
                                    <?php selected( $section['tone'], $tone ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Writing tone for this section content.', 'ai-page-composer' ); ?></p>
                </div>
                
                <div class="field-group checkbox-group">
                    <label>
                        <input type="checkbox" 
                               name="sections[<?php echo esc_attr( $index ); ?>][citations_required]" 
                               value="1" 
                               <?php checked( $section['citations_required'] ); ?>>
                        <?php esc_html_e( 'Citations Required', 'ai-page-composer' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Whether this section should include citations and sources.', 'ai-page-composer' ); ?></p>
                </div>
                
                <div class="field-group">
                    <label><?php esc_html_e( 'Allowed Blocks:', 'ai-page-composer' ); ?></label>
                    <textarea name="sections[<?php echo esc_attr( $index ); ?>][allowed_blocks]" 
                              rows="3" 
                              class="regular-text"
                              placeholder="core/paragraph&#10;core/heading&#10;core/image"><?php echo esc_textarea( implode( "\n", $section['allowed_blocks'] ) ); ?></textarea>
                    <p class="description">
                        <?php esc_html_e( 'One block name per line. Leave empty to allow all blocks. This restricts which blocks can be used in this section.', 'ai-page-composer' ); ?>
                    </p>
                </div>
            </div>
        </details>
        
        <!-- Section Validation Status -->
        <div class="section-validation-status">
            <span class="validation-indicator" data-status="pending">
                <span class="dashicons dashicons-clock"></span>
                <?php esc_html_e( 'Validation pending', 'ai-page-composer' ); ?>
            </span>
        </div>
    </div>
</div>