<?php
/**
 * Blueprint Validation Meta Box Template
 * 
 * This template renders the validation status meta box for AI Blueprints.
 * It displays validation results and schema compliance information.
 *
 * @package AIPageComposer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="ai-blueprint-validation">
    <?php if ( empty( $validation_errors ) ) : ?>
        <div class="validation-status validation-success">
            <div class="status-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="status-content">
                <h4><?php esc_html_e( 'Blueprint Valid', 'ai-page-composer' ); ?></h4>
                <p><?php esc_html_e( 'Your blueprint configuration is valid and ready for content generation.', 'ai-page-composer' ); ?></p>
            </div>
        </div>
        
        <?php if ( ! empty( $schema_data ) ) : ?>
            <div class="validation-summary">
                <h4><?php esc_html_e( 'Configuration Summary', 'ai-page-composer' ); ?></h4>
                <ul class="summary-list">
                    <li>
                        <strong><?php esc_html_e( 'Sections:', 'ai-page-composer' ); ?></strong>
                        <?php echo esc_html( count( $schema_data['sections'] ?? array() ) ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Generation Mode:', 'ai-page-composer' ); ?></strong>
                        <?php echo esc_html( ucfirst( $schema_data['global_settings']['generation_mode'] ?? 'hybrid' ) ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Category:', 'ai-page-composer' ); ?></strong>
                        <?php echo esc_html( ucwords( str_replace( '-', ' ', $schema_data['metadata']['category'] ?? 'custom' ) ) ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Difficulty:', 'ai-page-composer' ); ?></strong>
                        <?php echo esc_html( ucfirst( $schema_data['metadata']['difficulty_level'] ?? 'intermediate' ) ); ?>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
        
    <?php else : ?>
        <div class="validation-status validation-error">
            <div class="status-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="status-content">
                <h4><?php esc_html_e( 'Validation Errors', 'ai-page-composer' ); ?></h4>
                <p><?php esc_html_e( 'Your blueprint has validation errors that need to be fixed before it can be used.', 'ai-page-composer' ); ?></p>
            </div>
        </div>
        
        <div class="validation-errors">
            <h4><?php esc_html_e( 'Errors to Fix:', 'ai-page-composer' ); ?></h4>
            <ul class="error-list">
                <?php foreach ( $validation_errors as $error ) : ?>
                    <li class="validation-error-item">
                        <div class="error-property">
                            <?php if ( ! empty( $error['property'] ) ) : ?>
                                <code><?php echo esc_html( $error['property'] ); ?></code>
                            <?php endif; ?>
                        </div>
                        <div class="error-message">
                            <?php echo esc_html( $error['message'] ); ?>
                        </div>
                        <?php if ( ! empty( $error['constraint'] ) ) : ?>
                            <div class="error-constraint">
                                <small><?php echo esc_html( sprintf( __( 'Constraint: %s', 'ai-page-composer' ), $error['constraint'] ) ); ?></small>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="validation-actions">
        <button type="button" id="revalidate-blueprint" class="button button-secondary">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e( 'Re-validate', 'ai-page-composer' ); ?>
        </button>
        
        <?php if ( ! empty( $validation_errors ) ) : ?>
            <button type="button" id="auto-fix-errors" class="button button-secondary">
                <span class="dashicons dashicons-admin-tools"></span>
                <?php esc_html_e( 'Auto-fix Common Issues', 'ai-page-composer' ); ?>
            </button>
        <?php endif; ?>
        
        <button type="button" id="download-schema" class="button button-secondary">
            <span class="dashicons dashicons-download"></span>
            <?php esc_html_e( 'Download Schema', 'ai-page-composer' ); ?>
        </button>
    </div>
    
    <div class="validation-help">
        <details>
            <summary><?php esc_html_e( 'Validation Help', 'ai-page-composer' ); ?></summary>
            <div class="help-content">
                <h4><?php esc_html_e( 'Common Issues and Solutions:', 'ai-page-composer' ); ?></h4>
                <ul>
                    <li>
                        <strong><?php esc_html_e( 'Missing section heading:', 'ai-page-composer' ); ?></strong>
                        <?php esc_html_e( 'Each section must have a non-empty heading.', 'ai-page-composer' ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Invalid section type:', 'ai-page-composer' ); ?></strong>
                        <?php esc_html_e( 'Section type must be one of the predefined types (hero, content, media_text, etc.).', 'ai-page-composer' ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Word target out of range:', 'ai-page-composer' ); ?></strong>
                        <?php esc_html_e( 'Word target must be between 10 and 2000 words.', 'ai-page-composer' ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Invalid generation mode:', 'ai-page-composer' ); ?></strong>
                        <?php esc_html_e( 'Generation mode must be grounded, hybrid, or generative.', 'ai-page-composer' ); ?>
                    </li>
                </ul>
            </div>
        </details>
    </div>
</div>

<style>
.validation-status {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    border-radius: 4px;
    margin-bottom: 16px;
}

.validation-success {
    background: #f0f9f0;
    border: 1px solid #4caf50;
}

.validation-error {
    background: #fcf2f2;
    border: 1px solid #f44336;
}

.status-icon .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.validation-success .status-icon .dashicons {
    color: #4caf50;
}

.validation-error .status-icon .dashicons {
    color: #f44336;
}

.status-content h4 {
    margin: 0 0 8px 0;
    color: #1d2327;
}

.status-content p {
    margin: 0;
    font-size: 14px;
    line-height: 1.4;
}

.validation-success .status-content {
    color: #2e7d32;
}

.validation-error .status-content {
    color: #c62828;
}

.validation-summary {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 16px;
    margin-bottom: 16px;
}

.validation-summary h4 {
    margin-top: 0;
    margin-bottom: 12px;
    color: #1d2327;
    font-size: 14px;
}

.summary-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.summary-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
}

.summary-list li:last-child {
    border-bottom: none;
}

.validation-errors {
    background: #fff;
    border: 1px solid #f44336;
    border-radius: 4px;
    padding: 16px;
    margin-bottom: 16px;
}

.validation-errors h4 {
    margin-top: 0;
    margin-bottom: 12px;
    color: #c62828;
    font-size: 14px;
}

.error-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.validation-error-item {
    padding: 12px;
    margin-bottom: 8px;
    background: #fafafa;
    border: 1px solid #e0e0e0;
    border-radius: 3px;
}

.validation-error-item:last-child {
    margin-bottom: 0;
}

.error-property {
    margin-bottom: 4px;
}

.error-property code {
    background: #f44336;
    color: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.error-message {
    color: #1d2327;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 4px;
}

.error-constraint {
    color: #646970;
    font-size: 12px;
}

.validation-actions {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.validation-actions .button .dashicons {
    margin-right: 4px;
}

.validation-help {
    border-top: 1px solid #ddd;
    padding-top: 16px;
}

.validation-help summary {
    cursor: pointer;
    font-weight: 600;
    color: #1d2327;
    margin-bottom: 12px;
}

.help-content h4 {
    margin-top: 0;
    margin-bottom: 12px;
    color: #1d2327;
    font-size: 14px;
}

.help-content ul {
    margin: 0;
    padding-left: 16px;
}

.help-content li {
    margin-bottom: 8px;
    font-size: 13px;
    line-height: 1.4;
}

.help-content strong {
    color: #1d2327;
}

/* Animation for re-validation */
.validation-revalidating {
    opacity: 0.6;
    pointer-events: none;
}

.validation-revalidating::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>