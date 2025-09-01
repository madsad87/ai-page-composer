<?php
/**
 * Blueprint Preview Meta Box Template
 * 
 * This template renders the preview meta box for AI Blueprints.
 * It provides preview and testing functionality for blueprint configurations.
 *
 * @package AIPageComposer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="ai-blueprint-preview">
    <div class="preview-actions">
        <button type="button" id="preview-blueprint" class="button button-secondary">
            <span class="dashicons dashicons-visibility"></span>
            <?php esc_html_e( 'Preview Blueprint', 'ai-page-composer' ); ?>
        </button>
        <button type="button" id="test-generation" class="button button-secondary">
            <span class="dashicons dashicons-performance"></span>
            <?php esc_html_e( 'Test Generation', 'ai-page-composer' ); ?>
        </button>
        <button type="button" id="estimate-cost" class="button button-secondary">
            <span class="dashicons dashicons-money-alt"></span>
            <?php esc_html_e( 'Estimate Cost', 'ai-page-composer' ); ?>
        </button>
    </div>
    
    <div id="blueprint-preview-container" class="preview-container">
        <div class="preview-placeholder">
            <div class="placeholder-content">
                <span class="dashicons dashicons-layout"></span>
                <h4><?php esc_html_e( 'Blueprint Preview', 'ai-page-composer' ); ?></h4>
                <p><?php esc_html_e( 'Click "Preview Blueprint" to see how this blueprint will structure content and estimate generation requirements.', 'ai-page-composer' ); ?></p>
            </div>
        </div>
    </div>
    
    <div id="generation-test-results" class="test-results" style="display:none;">
        <h4><?php esc_html_e( 'Generation Test Results', 'ai-page-composer' ); ?></h4>
        <div class="test-results-content">
            <!-- Test results will be populated here -->
        </div>
    </div>
    
    <div id="cost-estimation" class="cost-estimation" style="display:none;">
        <h4><?php esc_html_e( 'Cost Estimation', 'ai-page-composer' ); ?></h4>
        <div class="cost-details">
            <div class="cost-item">
                <span class="label"><?php esc_html_e( 'Estimated Tokens:', 'ai-page-composer' ); ?></span>
                <span class="value" id="estimated-tokens">-</span>
            </div>
            <div class="cost-item">
                <span class="label"><?php esc_html_e( 'Estimated Cost:', 'ai-page-composer' ); ?></span>
                <span class="value" id="estimated-cost">-</span>
            </div>
            <div class="cost-item">
                <span class="label"><?php esc_html_e( 'Estimated Time:', 'ai-page-composer' ); ?></span>
                <span class="value" id="estimated-time">-</span>
            </div>
        </div>
    </div>
    
    <div class="preview-help">
        <h4><?php esc_html_e( 'Preview Actions', 'ai-page-composer' ); ?></h4>
        <ul>
            <li>
                <strong><?php esc_html_e( 'Preview Blueprint:', 'ai-page-composer' ); ?></strong>
                <?php esc_html_e( 'Shows the structure and configuration of your blueprint without generating content.', 'ai-page-composer' ); ?>
            </li>
            <li>
                <strong><?php esc_html_e( 'Test Generation:', 'ai-page-composer' ); ?></strong>
                <?php esc_html_e( 'Runs a limited test generation to validate your configuration and API connectivity.', 'ai-page-composer' ); ?>
            </li>
            <li>
                <strong><?php esc_html_e( 'Estimate Cost:', 'ai-page-composer' ); ?></strong>
                <?php esc_html_e( 'Calculates the estimated cost and time for generating content with this blueprint.', 'ai-page-composer' ); ?>
            </li>
        </ul>
    </div>
</div>

<style>
.preview-actions {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.preview-actions .button .dashicons {
    margin-right: 4px;
}

.preview-container {
    min-height: 200px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
    margin-bottom: 16px;
}

.preview-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 200px;
    text-align: center;
    color: #646970;
}

.placeholder-content .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.placeholder-content h4 {
    margin: 0 0 8px 0;
    color: #1d2327;
}

.placeholder-content p {
    margin: 0;
    max-width: 300px;
}

.test-results,
.cost-estimation {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 16px;
    margin-bottom: 16px;
}

.test-results h4,
.cost-estimation h4 {
    margin-top: 0;
    margin-bottom: 12px;
    color: #1d2327;
}

.cost-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
}

.cost-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.cost-item .label {
    font-size: 12px;
    color: #646970;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.cost-item .value {
    font-weight: 600;
    color: #1d2327;
    font-size: 16px;
}

.preview-help {
    border-top: 1px solid #ddd;
    padding-top: 16px;
}

.preview-help h4 {
    margin-top: 0;
    margin-bottom: 12px;
    color: #1d2327;
    font-size: 14px;
}

.preview-help ul {
    margin: 0;
    padding-left: 16px;
}

.preview-help li {
    margin-bottom: 8px;
    font-size: 13px;
    line-height: 1.4;
}

.preview-help strong {
    color: #1d2327;
}

/* Preview content styles */
.blueprint-preview-content {
    padding: 16px;
}

.preview-section {
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    margin-bottom: 12px;
    background: #fafafa;
}

.preview-section-header {
    padding: 12px 16px;
    background: #f0f0f0;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.preview-section-title {
    font-weight: 600;
    color: #1d2327;
    margin: 0;
}

.preview-section-meta {
    font-size: 12px;
    color: #646970;
}

.preview-section-content {
    padding: 16px;
}

.preview-section-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    margin-bottom: 12px;
}

.preview-detail {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.preview-detail-label {
    font-size: 11px;
    color: #646970;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.preview-detail-value {
    font-size: 13px;
    color: #1d2327;
    font-weight: 500;
}

.preview-block-info {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 3px;
    padding: 8px 12px;
    margin-top: 8px;
}

.preview-block-info-title {
    font-size: 12px;
    font-weight: 600;
    color: #1d2327;
    margin-bottom: 4px;
}

.preview-suggested-blocks {
    font-size: 11px;
    color: #646970;
}

/* Loading states */
.preview-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100px;
    color: #646970;
}

.preview-loading .dashicons {
    animation: spin 1s linear infinite;
    margin-right: 8px;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.preview-error {
    padding: 16px;
    background: #fcf2f2;
    border: 1px solid #f1b5b5;
    border-radius: 4px;
    color: #d63384;
}

.preview-success {
    padding: 16px;
    background: #f0f9f0;
    border: 1px solid #b5f1b5;
    border-radius: 4px;
    color: #198754;
}
</style>