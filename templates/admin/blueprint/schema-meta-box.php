<?php
/**
 * Blueprint Schema Meta Box Template
 * 
 * This template renders the schema configuration meta box for AI Blueprints.
 * It provides both visual and JSON editors for blueprint configuration.
 *
 * @package AIPageComposer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="ai-blueprint-schema-editor">
    <div class="schema-tabs">
        <ul class="nav-tab-wrapper">
            <li><a href="#visual-editor" class="nav-tab nav-tab-active" data-tab="visual-editor"><?php esc_html_e( 'Visual Editor', 'ai-page-composer' ); ?></a></li>
            <li><a href="#json-editor" class="nav-tab" data-tab="json-editor"><?php esc_html_e( 'JSON Editor', 'ai-page-composer' ); ?></a></li>
            <li><a href="#validation-results" class="nav-tab" data-tab="validation-results"><?php esc_html_e( 'Validation', 'ai-page-composer' ); ?></a></li>
        </ul>
    </div>
    
    <div id="visual-editor" class="tab-content active">
        <div id="blueprint-visual-editor" class="blueprint-editor-container">
            <div class="blueprint-editor-placeholder">
                <p><?php esc_html_e( 'The visual editor will be loaded here. Use the sections editor below to configure your blueprint.', 'ai-page-composer' ); ?></p>
                <p class="description"><?php esc_html_e( 'The visual editor provides an interactive interface for configuring your AI Blueprint. Switch to the JSON editor for advanced configuration.', 'ai-page-composer' ); ?></p>
            </div>
        </div>
    </div>
    
    <div id="json-editor" class="tab-content">
        <div class="json-editor-controls">
            <button type="button" id="validate-json" class="button button-secondary">
                <?php esc_html_e( 'Validate JSON', 'ai-page-composer' ); ?>
            </button>
            <button type="button" id="format-json" class="button button-secondary">
                <?php esc_html_e( 'Format JSON', 'ai-page-composer' ); ?>
            </button>
            <button type="button" id="sync-from-sections" class="button button-secondary">
                <?php esc_html_e( 'Sync from Sections', 'ai-page-composer' ); ?>
            </button>
        </div>
        
        <label for="blueprint_schema_json">
            <?php esc_html_e( 'Blueprint JSON Schema:', 'ai-page-composer' ); ?>
        </label>
        <textarea id="blueprint_schema_json" 
                  name="blueprint_schema_json" 
                  rows="20" 
                  class="large-text code"
                  spellcheck="false"
                  placeholder="<?php esc_attr_e( 'Enter blueprint JSON configuration here...', 'ai-page-composer' ); ?>"><?php echo esc_textarea( $schema_json ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Edit the blueprint schema in JSON format. Changes will be validated automatically. Use the "Sync from Sections" button to generate JSON from the visual editor.', 'ai-page-composer' ); ?>
        </p>
    </div>
    
    <div id="validation-results" class="tab-content">
        <div id="schema-validation-results" class="validation-container">
            <div class="validation-placeholder">
                <p><?php esc_html_e( 'Validation results will be displayed here after validating your blueprint.', 'ai-page-composer' ); ?></p>
                <button type="button" id="run-validation" class="button button-primary">
                    <?php esc_html_e( 'Run Validation', 'ai-page-composer' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="blueprint_schema_data" name="blueprint_schema_data" value="">

<script type="text/javascript">
// Initialize blueprint data for React component
window.aiBlueprintData = <?php echo wp_json_encode( $schema_data ?: array() ); ?>;
</script>