<?php
/**
 * Block Preferences Tab Template
 * 
 * This template renders the Block Preferences panel showing detected
 * block plugins, priority settings, and section mappings.
 * 
 * @package AIPageComposer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<h2><?php esc_html_e( 'Block Preferences', 'ai-page-composer' ); ?></h2>
<p class="description">
    <?php esc_html_e( 'Configure which block plugins to use for different content sections and manage plugin priorities.', 'ai-page-composer' ); ?>
</p>

<!-- Plugin Detection Status -->
<div class="plugin-detection-status">
    <h3>
        <?php esc_html_e( 'Detected Block Plugins', 'ai-page-composer' ); ?>
        <button type="button" id="refresh-plugin-detection" class="button button-secondary" style="margin-left: 10px;">
            <?php esc_html_e( 'Refresh Detection', 'ai-page-composer' ); ?>
        </button>
    </h3>
    
    <?php if ( empty( $detected_plugins ) ) : ?>
        <div class="notice notice-warning inline">
            <p><?php esc_html_e( 'No block plugins detected. Only WordPress core blocks will be available.', 'ai-page-composer' ); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Plugin', 'ai-page-composer' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'ai-page-composer' ); ?></th>
                    <th><?php esc_html_e( 'Version', 'ai-page-composer' ); ?></th>
                    <th><?php esc_html_e( 'Blocks', 'ai-page-composer' ); ?></th>
                    <th><?php esc_html_e( 'Priority', 'ai-page-composer' ); ?></th>
                    <th><?php esc_html_e( 'Supported Sections', 'ai-page-composer' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $detected_plugins as $plugin_key => $plugin_data ) : ?>
                <tr class="<?php echo $plugin_data['active'] ? 'plugin-active' : 'plugin-inactive'; ?>">
                    <td>
                        <strong><?php echo esc_html( $plugin_data['name'] ); ?></strong>
                        <br><small class="description"><?php echo esc_html( $plugin_data['namespace'] ); ?></small>
                    </td>
                    <td>
                        <span class="status-indicator <?php echo $plugin_data['active'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $plugin_data['active'] ? 
                                esc_html__( 'Active', 'ai-page-composer' ) : 
                                esc_html__( 'Inactive', 'ai-page-composer' ); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html( $plugin_data['version'] ?? 'Unknown' ); ?></td>
                    <td>
                        <?php echo esc_html( $plugin_data['blocks_count'] ?? 0 ); ?>
                        <?php if ( $plugin_data['blocks_count'] > 0 ) : ?>
                            <small class="description"><?php esc_html_e( 'blocks', 'ai-page-composer' ); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $plugin_data['active'] ) : ?>
                            <input type="range" 
                                   name="ai_composer_settings[block_preferences][plugin_priorities][<?php echo esc_attr( $plugin_key ); ?>]"
                                   value="<?php echo esc_attr( $block_preferences['plugin_priorities'][ $plugin_key ] ?? $plugin_data['priority'] ); ?>"
                                   min="1" max="10" step="1"
                                   class="priority-slider"
                                   data-plugin="<?php echo esc_attr( $plugin_key ); ?>">
                            <span class="priority-value"><?php echo esc_html( $block_preferences['plugin_priorities'][ $plugin_key ] ?? $plugin_data['priority'] ); ?></span>
                        <?php else : ?>
                            <span class="priority-disabled">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="supported-sections">
                            <?php if ( ! empty( $plugin_data['supported_sections'] ) ) : ?>
                                <?php foreach ( $plugin_data['supported_sections'] as $section ) : ?>
                                    <span class="section-tag"><?php echo esc_html( ucfirst( $section ) ); ?></span>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <small class="description"><?php esc_html_e( 'General use', 'ai-page-composer' ); ?></small>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p class="description" style="margin-top: 10px;">
            <strong><?php esc_html_e( 'Priority Scale:', 'ai-page-composer' ); ?></strong>
            <?php esc_html_e( '1 = Lowest priority, 10 = Highest priority. Higher priority plugins are preferred for block selection.', 'ai-page-composer' ); ?>
        </p>
    <?php endif; ?>
</div>

<!-- Section Mapping Preferences -->
<div class="section-mappings">
    <h3><?php esc_html_e( 'Section Block Preferences', 'ai-page-composer' ); ?></h3>
    <p class="description">
        <?php esc_html_e( 'Choose preferred block plugins for each content section type. "Auto" uses the highest priority available plugin.', 'ai-page-composer' ); ?>
    </p>
    
    <table class="form-table">
        <?php foreach ( $section_types as $section_key => $section_label ) : ?>
        <tr>
            <th scope="row">
                <label for="section-<?php echo esc_attr( $section_key ); ?>">
                    <?php echo esc_html( $section_label ); ?>
                </label>
            </th>
            <td>
                <select name="ai_composer_settings[block_preferences][section_mappings][<?php echo esc_attr( $section_key ); ?>]" 
                        id="section-<?php echo esc_attr( $section_key ); ?>" 
                        class="regular-text">
                    <option value="auto" <?php selected( $block_preferences['section_mappings'][ $section_key ] ?? 'auto', 'auto' ); ?>>
                        <?php esc_html_e( '🤖 Auto (Best Available)', 'ai-page-composer' ); ?>
                    </option>
                    <option value="core" <?php selected( $block_preferences['section_mappings'][ $section_key ] ?? 'auto', 'core' ); ?>>
                        <?php esc_html_e( '📦 WordPress Core Blocks', 'ai-page-composer' ); ?>
                    </option>
                    <?php foreach ( $detected_plugins as $plugin_key => $plugin_data ) : ?>
                        <?php if ( $plugin_data['active'] && in_array( $section_key, $plugin_data['supported_sections'], true ) ) : ?>
                        <option value="<?php echo esc_attr( $plugin_key ); ?>" 
                                <?php selected( $block_preferences['section_mappings'][ $section_key ] ?? 'auto', $plugin_key ); ?>>
                            🧩 <?php echo esc_html( $plugin_data['name'] ); ?>
                            <?php if ( $plugin_data['blocks_count'] > 0 ) : ?>
                                (<?php echo esc_html( $plugin_data['blocks_count'] ); ?> blocks)
                            <?php endif; ?>
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php 
                    printf(
                        /* translators: %s: Section type name */
                        esc_html__( 'Preferred plugin for %s. "Auto" uses highest priority available plugin.', 'ai-page-composer' ),
                        '<strong>' . esc_html( strtolower( $section_label ) ) . '</strong>'
                    ); 
                    ?>
                </p>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- Block Detection Settings -->
<div class="block-detection-settings">
    <h3><?php esc_html_e( 'Detection Settings', 'ai-page-composer' ); ?></h3>
    
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="detection_enabled"><?php esc_html_e( 'Plugin Detection', 'ai-page-composer' ); ?></label>
            </th>
            <td>
                <label>
                    <input type="checkbox" 
                           id="detection_enabled"
                           name="ai_composer_settings[block_preferences][detection_enabled]" 
                           value="1"
                           <?php checked( $block_preferences['detection_enabled'] ?? true ); ?> />
                    <?php esc_html_e( 'Enable automatic block plugin detection', 'ai-page-composer' ); ?>
                </label>
                <p class="description">
                    <?php esc_html_e( 'Automatically scan for and detect block plugins. Disable if causing performance issues.', 'ai-page-composer' ); ?>
                </p>
            </td>
        </tr>
    </table>
</div>

<!-- Custom Block Types -->
<div class="custom-block-types">
    <h3><?php esc_html_e( 'Custom Block Types', 'ai-page-composer' ); ?></h3>
    <p class="description">
        <?php esc_html_e( 'Register additional block types that are not automatically detected. Useful for custom blocks or theme-specific blocks.', 'ai-page-composer' ); ?>
    </p>
    
    <div class="custom-blocks-list">
        <div id="custom-blocks-container">
            <?php 
            $custom_blocks = $block_preferences['custom_block_types'] ?? array();
            if ( ! empty( $custom_blocks ) ) :
                foreach ( $custom_blocks as $index => $custom_block ) :
            ?>
            <div class="custom-block-row">
                <input type="text" 
                       name="ai_composer_settings[block_preferences][custom_block_types][<?php echo esc_attr( $index ); ?>][name]"
                       placeholder="<?php esc_attr_e( 'Block Name (e.g., product-showcase)', 'ai-page-composer' ); ?>"
                       value="<?php echo esc_attr( $custom_block['name'] ?? '' ); ?>"
                       class="regular-text" />
                <input type="text"
                       name="ai_composer_settings[block_preferences][custom_block_types][<?php echo esc_attr( $index ); ?>][namespace]" 
                       placeholder="<?php esc_attr_e( 'Namespace (e.g., my-plugin)', 'ai-page-composer' ); ?>"
                       value="<?php echo esc_attr( $custom_block['namespace'] ?? '' ); ?>"
                       class="regular-text" />
                <button type="button" class="button remove-custom-block">
                    <?php esc_html_e( 'Remove', 'ai-page-composer' ); ?>
                </button>
            </div>
            <?php 
                endforeach;
            endif;
            ?>
        </div>
        <button type="button" id="add-custom-block" class="button button-secondary">
            <?php esc_html_e( '+ Add Custom Block Type', 'ai-page-composer' ); ?>
        </button>
        <p class="description" style="margin-top: 10px;">
            <?php esc_html_e( 'Add custom block types that should be considered during content generation. Enter the block name and its namespace.', 'ai-page-composer' ); ?>
        </p>
    </div>
</div>

<style>
.section-tag {
    display: inline-block;
    background: #f0f0f1;
    color: #2c3338;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    margin: 1px;
}

.supported-sections {
    max-width: 200px;
}

.plugin-active {
    background-color: rgba(209, 231, 221, 0.1);
}

.plugin-inactive {
    background-color: rgba(248, 215, 218, 0.1);
    opacity: 0.7;
}

.priority-slider {
    width: 80px !important;
    margin-right: 8px;
}

.priority-value {
    display: inline-block;
    min-width: 20px;
    font-weight: 600;
    color: #0073aa;
}

.custom-block-row {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 10px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.custom-block-row input[type="text"] {
    flex: 1;
}

@media (max-width: 782px) {
    .custom-block-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .priority-slider {
        width: 100% !important;
        margin-bottom: 5px;
    }
    
    .supported-sections {
        max-width: none;
    }
    
    .section-tag {
        margin: 2px;
    }
}
</style>