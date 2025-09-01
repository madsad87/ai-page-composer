<?php
/**
 * Outline Generation Step Template
 * 
 * This template renders the outline generation step in the admin UI workflow.
 * 
 * @package AIPageComposer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="outline-generation-step" class="ai-composer-step" style="display: none;">
    <div class="step-header">
        <h3><?php esc_html_e( 'Generate Content Outline', 'ai-page-composer' ); ?></h3>
        <p class="step-description">
            <?php esc_html_e( 'Create a structured outline for your content based on the selected blueprint and your requirements.', 'ai-page-composer' ); ?>
        </p>
    </div>

    <div class="step-content">
        <form id="outline-form" class="ai-composer-form">
            <?php wp_nonce_field( 'ai_composer_outline_nonce', 'outline_nonce' ); ?>
            
            <div class="form-section">
                <h4><?php esc_html_e( 'Content Requirements', 'ai-page-composer' ); ?></h4>
                
                <div class="form-row">
                    <label for="content-brief" class="form-label required">
                        <?php esc_html_e( 'Content Brief:', 'ai-page-composer' ); ?>
                        <span class="required-indicator">*</span>
                    </label>
                    <textarea 
                        id="content-brief" 
                        name="brief" 
                        rows="4" 
                        class="form-control"
                        placeholder="<?php esc_attr_e( 'Describe what content you want to create (10-2000 characters)...', 'ai-page-composer' ); ?>"
                        required
                        minlength="10"
                        maxlength="2000"></textarea>
                    <div class="form-help">
                        <span class="char-counter">0 / 2000</span>
                        <span class="help-text"><?php esc_html_e( 'Provide a clear description of the content you want to create.', 'ai-page-composer' ); ?></span>
                    </div>
                </div>

                <div class="form-row">
                    <label for="target-audience" class="form-label">
                        <?php esc_html_e( 'Target Audience:', 'ai-page-composer' ); ?>
                    </label>
                    <input 
                        type="text" 
                        id="target-audience" 
                        name="audience" 
                        class="form-control"
                        placeholder="<?php esc_attr_e( 'e.g., Small business owners, Students, Tech professionals', 'ai-page-composer' ); ?>"
                        maxlength="500">
                    <div class="form-help">
                        <?php esc_html_e( 'Who is this content intended for? This helps tailor the language and approach.', 'ai-page-composer' ); ?>
                    </div>
                </div>

                <div class="form-row">
                    <label for="content-tone" class="form-label">
                        <?php esc_html_e( 'Content Tone:', 'ai-page-composer' ); ?>
                    </label>
                    <select id="content-tone" name="tone" class="form-control">
                        <option value="professional"><?php esc_html_e( 'Professional', 'ai-page-composer' ); ?></option>
                        <option value="casual"><?php esc_html_e( 'Casual', 'ai-page-composer' ); ?></option>
                        <option value="friendly"><?php esc_html_e( 'Friendly', 'ai-page-composer' ); ?></option>
                        <option value="technical"><?php esc_html_e( 'Technical', 'ai-page-composer' ); ?></option>
                        <option value="authoritative"><?php esc_html_e( 'Authoritative', 'ai-page-composer' ); ?></option>
                    </select>
                    <div class="form-help">
                        <?php esc_html_e( 'Choose the overall tone and style for your content.', 'ai-page-composer' ); ?>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h4>
                    <?php esc_html_e( 'Advanced Options', 'ai-page-composer' ); ?>
                    <button type="button" class="toggle-advanced" aria-expanded="false">
                        <span class="dashicons dashicons-arrow-down"></span>
                    </button>
                </h4>
                
                <div class="advanced-options" style="display: none;">
                    <div class="form-row">
                        <label for="mvdb-namespaces" class="form-label">
                            <?php esc_html_e( 'Knowledge Base Namespaces:', 'ai-page-composer' ); ?>
                        </label>
                        <input 
                            type="text" 
                            id="mvdb-namespaces" 
                            name="mvdb_namespaces" 
                            class="form-control"
                            placeholder="<?php esc_attr_e( 'content, products, docs (comma-separated)', 'ai-page-composer' ); ?>">
                        <div class="form-help">
                            <?php esc_html_e( 'Specify knowledge base namespaces to search for relevant context.', 'ai-page-composer' ); ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <label for="search-results" class="form-label">
                            <?php esc_html_e( 'Search Results (k):', 'ai-page-composer' ); ?>
                        </label>
                        <input 
                            type="number" 
                            id="search-results" 
                            name="k" 
                            class="form-control"
                            value="10"
                            min="1"
                            max="50">
                        <div class="form-help">
                            <?php esc_html_e( 'Number of knowledge base results to retrieve (1-50).', 'ai-page-composer' ); ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <label for="min-score" class="form-label">
                            <?php esc_html_e( 'Minimum Relevance Score:', 'ai-page-composer' ); ?>
                        </label>
                        <input 
                            type="range" 
                            id="min-score" 
                            name="min_score" 
                            class="form-range"
                            value="0.5"
                            min="0"
                            max="1"
                            step="0.1">
                        <div class="range-value">0.5</div>
                        <div class="form-help">
                            <?php esc_html_e( 'Minimum similarity score for including knowledge base results.', 'ai-page-composer' ); ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <label for="alpha-value" class="form-label">
                            <?php esc_html_e( 'Context vs Creativity Balance:', 'ai-page-composer' ); ?>
                        </label>
                        <input 
                            type="range" 
                            id="alpha-value" 
                            name="alpha" 
                            class="form-range"
                            value="0.7"
                            min="0"
                            max="1"
                            step="0.1">
                        <div class="range-value">0.7</div>
                        <div class="range-labels">
                            <span><?php esc_html_e( 'Creative', 'ai-page-composer' ); ?></span>
                            <span><?php esc_html_e( 'Context-focused', 'ai-page-composer' ); ?></span>
                        </div>
                        <div class="form-help">
                            <?php esc_html_e( 'Higher values prioritize knowledge base context, lower values allow more creativity.', 'ai-page-composer' ); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="button button-primary button-large" id="generate-outline-btn">
                    <span class="btn-text"><?php esc_html_e( 'Generate Outline', 'ai-page-composer' ); ?></span>
                    <span class="spinner" style="display: none;"></span>
                </button>
                
                <div class="generation-info">
                    <small class="cost-estimate" style="display: none;">
                        <?php esc_html_e( 'Estimated cost: $0.00', 'ai-page-composer' ); ?>
                    </small>
                </div>
            </div>
        </form>
    </div>

    <div id="outline-results" class="outline-results" style="display: none;">
        <div class="results-header">
            <h4><?php esc_html_e( 'Generated Outline', 'ai-page-composer' ); ?></h4>
            <div class="outline-meta">
                <span class="total-words"></span>
                <span class="estimated-time"></span>
                <span class="generation-mode"></span>
            </div>
        </div>

        <div class="outline-sections" id="outline-sections-container">
            <!-- Generated sections will be inserted here -->
        </div>

        <div class="results-actions">
            <button type="button" class="button button-secondary" id="regenerate-outline">
                <?php esc_html_e( 'Regenerate Outline', 'ai-page-composer' ); ?>
            </button>
            <button type="button" class="button button-primary" id="approve-outline">
                <?php esc_html_e( 'Use This Outline', 'ai-page-composer' ); ?>
            </button>
        </div>
    </div>

    <div class="step-navigation">
        <button type="button" class="button button-secondary prev-step">
            <?php esc_html_e( '← Previous Step', 'ai-page-composer' ); ?>
        </button>
        <button type="button" class="button button-primary next-step" disabled>
            <?php esc_html_e( 'Next Step →', 'ai-page-composer' ); ?>
        </button>
    </div>
</div>

<!-- Outline Section Template -->
<script type="text/template" id="outline-section-template">
    <div class="outline-section" data-section-id="{{id}}">
        <div class="section-header">
            <div class="section-info">
                <h5 class="section-heading">{{heading}}</h5>
                <div class="section-meta">
                    <span class="section-type">{{type}}</span>
                    <span class="word-count">{{targetWords}} words</span>
                    <span class="image-requirement">{{imageText}}</span>
                </div>
            </div>
            <div class="section-actions">
                <button type="button" class="button button-small edit-section" title="<?php esc_attr_e( 'Edit Section', 'ai-page-composer' ); ?>">
                    <span class="dashicons dashicons-edit"></span>
                </button>
                <button type="button" class="button button-small remove-section" title="<?php esc_attr_e( 'Remove Section', 'ai-page-composer' ); ?>">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
        </div>

        <div class="section-details">
            {{#if subheadings}}
            <div class="subheadings">
                <strong><?php esc_html_e( 'Key Points:', 'ai-page-composer' ); ?></strong>
                <ul>
                    {{#each subheadings}}
                    <li>{{this}}</li>
                    {{/each}}
                </ul>
            </div>
            {{/if}}

            {{#if block_preference}}
            <div class="block-preferences">
                <strong><?php esc_html_e( 'Block Recommendation:', 'ai-page-composer' ); ?></strong>
                <span class="preferred-plugin">{{block_preference.preferred_plugin}}</span>
                <span class="primary-block">{{block_preference.primary_block}}</span>
                {{#if block_preference.pattern_preference}}
                <span class="pattern-preference">({{block_preference.pattern_preference}})</span>
                {{/if}}
            </div>
            {{/if}}
        </div>
    </div>
</script>

<style>
.ai-composer-step {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.step-header {
    margin-bottom: 30px;
    text-align: center;
}

.step-header h3 {
    margin: 0 0 10px 0;
    font-size: 24px;
}

.step-description {
    color: #666;
    font-size: 16px;
    margin: 0;
}

.form-section {
    margin-bottom: 30px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
}

.form-section h4 {
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.toggle-advanced {
    background: none;
    border: none;
    padding: 5px;
    cursor: pointer;
}

.form-row {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.required-indicator {
    color: #d63638;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    border-color: #0073aa;
    box-shadow: 0 0 0 1px #0073aa;
    outline: none;
}

.form-help {
    margin-top: 5px;
    font-size: 13px;
    color: #666;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.char-counter {
    font-weight: 600;
}

.form-range {
    width: 100%;
}

.range-value {
    text-align: center;
    font-weight: 600;
    margin-top: 5px;
}

.range-labels {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.form-actions {
    text-align: center;
    margin-top: 30px;
}

.generation-info {
    margin-top: 10px;
}

.outline-results {
    margin-top: 30px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.outline-meta {
    display: flex;
    gap: 15px;
    font-size: 13px;
    color: #666;
}

.outline-section {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 6px;
    border-left: 4px solid #0073aa;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.section-heading {
    margin: 0 0 5px 0;
    font-size: 16px;
}

.section-meta {
    display: flex;
    gap: 10px;
    font-size: 12px;
    color: #666;
}

.section-meta span {
    background: #e0e0e0;
    padding: 2px 6px;
    border-radius: 3px;
}

.section-actions {
    display: flex;
    gap: 5px;
}

.section-details {
    margin-top: 10px;
    font-size: 14px;
}

.subheadings ul {
    margin: 5px 0 0 20px;
}

.block-preferences {
    margin-top: 10px;
    padding: 8px;
    background: #fff;
    border-radius: 4px;
    font-size: 13px;
}

.preferred-plugin,
.primary-block,
.pattern-preference {
    display: inline-block;
    margin-left: 10px;
    padding: 2px 6px;
    background: #0073aa;
    color: #fff;
    border-radius: 3px;
    font-size: 11px;
}

.results-actions {
    text-align: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.step-navigation {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}
</style>