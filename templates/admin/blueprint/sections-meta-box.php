<?php
/**
 * Blueprint Sections Meta Box Template
 * 
 * This template renders the sections configuration meta box for AI Blueprints.
 * It provides an interface for adding, configuring, and managing content sections.
 *
 * @package AIPageComposer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="ai-blueprint-sections">
    <div class="sections-header">
        <h4><?php esc_html_e( 'Content Sections Configuration', 'ai-page-composer' ); ?></h4>
        <button type="button" id="add-section" class="button button-secondary">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php esc_html_e( 'Add Section', 'ai-page-composer' ); ?>
        </button>
    </div>
    
    <?php if ( empty( $sections_data ) ) : ?>
        <div class="sections-empty-state">
            <div class="empty-state-content">
                <span class="dashicons dashicons-layout"></span>
                <h3><?php esc_html_e( 'No sections configured yet', 'ai-page-composer' ); ?></h3>
                <p><?php esc_html_e( 'Add your first content section to start building your AI Blueprint. Each section represents a distinct part of your content that will be generated.', 'ai-page-composer' ); ?></p>
                <button type="button" id="add-first-section" class="button button-primary">
                    <?php esc_html_e( 'Add Your First Section', 'ai-page-composer' ); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
    
    <div id="sections-container" class="sections-list <?php echo empty( $sections_data ) ? 'hidden' : ''; ?>">
        <?php foreach ( $sections_data as $index => $section ) : ?>
            <?php 
            // Include the section row template
            include AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/admin/blueprint/section-row.php';
            ?>
        <?php endforeach; ?>
    </div>
    
    <template id="section-row-template">
        <?php 
        // Create a template section for new additions
        $section = array();
        $index = '{{index}}';
        include AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/admin/blueprint/section-row.php';
        ?>
    </template>
    
    <div class="sections-footer">
        <p class="description">
            <?php esc_html_e( 'Drag and drop sections to reorder them. Each section will be generated as a separate content block in your final output.', 'ai-page-composer' ); ?>
        </p>
        
        <div class="section-bulk-actions">
            <select id="bulk-action-selector">
                <option value=""><?php esc_html_e( 'Bulk Actions', 'ai-page-composer' ); ?></option>
                <option value="duplicate"><?php esc_html_e( 'Duplicate Selected', 'ai-page-composer' ); ?></option>
                <option value="delete"><?php esc_html_e( 'Delete Selected', 'ai-page-composer' ); ?></option>
                <option value="set-tone"><?php esc_html_e( 'Set Tone', 'ai-page-composer' ); ?></option>
                <option value="set-plugin"><?php esc_html_e( 'Set Preferred Plugin', 'ai-page-composer' ); ?></option>
            </select>
            <button type="button" id="apply-bulk-action" class="button" disabled>
                <?php esc_html_e( 'Apply', 'ai-page-composer' ); ?>
            </button>
        </div>
    </div>
</div>

<style>
.sections-empty-state {
    text-align: center;
    padding: 40px 20px;
    border: 2px dashed #c3c4c7;
    border-radius: 8px;
    background: #f9f9f9;
}

.empty-state-content .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #c3c4c7;
    margin-bottom: 16px;
}

.empty-state-content h3 {
    margin: 16px 0 8px 0;
    color: #1d2327;
}

.empty-state-content p {
    color: #646970;
    max-width: 400px;
    margin: 0 auto 20px auto;
}

.sections-list.hidden {
    display: none;
}

.section-bulk-actions {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #ddd;
}

.section-bulk-actions select,
.section-bulk-actions button {
    margin-right: 8px;
}
</style>