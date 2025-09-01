/**
 * Assembly Interface JavaScript - Frontend assembly interface for AI Page Composer
 * 
 * This file handles the frontend interface for content assembly, preview modal,
 * plugin indicators, and draft creation functionality.
 */

(function($) {
    'use strict';

    /**
     * Assembly Interface Class
     */
    class AssemblyInterface {
        constructor() {
            this.apiBase = wpApiSettings.root + 'ai-composer/v1/';
            this.nonce = wpApiSettings.nonce;
            this.currentAssemblyData = null;
            this.currentPreviewData = null;
            this.previewModal = null;
            
            this.init();
        }

        /**
         * Initialize the interface
         */
        init() {
            this.bindEvents();
            this.createPreviewModal();
            this.loadDetectedPlugins();
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Assembly trigger buttons
            $(document).on('click', '.ai-composer-assemble-btn', this.handleAssembleClick.bind(this));
            
            // Preview buttons
            $(document).on('click', '.ai-composer-preview-btn', this.handlePreviewClick.bind(this));
            
            // Draft creation buttons
            $(document).on('click', '.ai-composer-create-draft-btn', this.handleCreateDraftClick.bind(this));
            
            // Plugin indicator toggles
            $(document).on('change', '#ai-composer-show-indicators', this.togglePluginIndicators.bind(this));
            
            // Responsive preview toggles
            $(document).on('click', '.ai-composer-responsive-btn', this.handleResponsiveToggle.bind(this));
            
            // Modal close events
            $(document).on('click', '.ai-composer-modal-close', this.closePreviewModal.bind(this));
            $(document).on('click', '.ai-composer-modal-overlay', this.closePreviewModal.bind(this));
            
            // Keyboard events
            $(document).on('keydown', this.handleKeydown.bind(this));
        }

        /**
         * Handle assemble button click
         */
        async handleAssembleClick(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const sectionsData = this.extractSectionsData();
            
            if (!sectionsData || sectionsData.length === 0) {
                this.showNotification('No sections available for assembly', 'error');
                return;
            }

            $button.prop('disabled', true).addClass('loading');
            this.showLoadingState('Assembling content...');

            try {
                const assemblyData = await this.assembleContent(sectionsData);
                this.currentAssemblyData = assemblyData;
                
                this.displayAssemblyResult(assemblyData);
                this.showNotification('Content assembled successfully!', 'success');
                
                // Enable preview and draft buttons
                $('.ai-composer-preview-btn, .ai-composer-create-draft-btn').prop('disabled', false);
                
            } catch (error) {
                console.error('Assembly failed:', error);
                this.showNotification('Assembly failed: ' + error.message, 'error');
            } finally {
                $button.prop('disabled', false).removeClass('loading');
                this.hideLoadingState();
            }
        }

        /**
         * Handle preview button click
         */
        async handlePreviewClick(e) {
            e.preventDefault();
            
            if (!this.currentAssemblyData) {
                this.showNotification('Please assemble content first', 'warning');
                return;
            }

            const $button = $(e.currentTarget);
            $button.prop('disabled', true).addClass('loading');

            try {
                const previewOptions = this.getPreviewOptions();
                const previewData = await this.generatePreview(this.currentAssemblyData, previewOptions);
                this.currentPreviewData = previewData;
                
                this.showPreviewModal(previewData);
                
            } catch (error) {
                console.error('Preview generation failed:', error);
                this.showNotification('Preview generation failed: ' + error.message, 'error');
            } finally {
                $button.prop('disabled', false).removeClass('loading');
            }
        }

        /**
         * Handle create draft button click
         */
        async handleCreateDraftClick(e) {
            e.preventDefault();
            
            if (!this.currentAssemblyData) {
                this.showNotification('Please assemble content first', 'warning');
                return;
            }

            const draftData = this.prepareDraftData();
            if (!draftData) {
                return;
            }

            const $button = $(e.currentTarget);
            $button.prop('disabled', true).addClass('loading');
            this.showLoadingState('Creating draft...');

            try {
                const result = await this.createDraft(draftData);
                this.displayDraftResult(result);
                this.showNotification('Draft created successfully!', 'success');
                
            } catch (error) {
                console.error('Draft creation failed:', error);
                this.showNotification('Draft creation failed: ' + error.message, 'error');
            } finally {
                $button.prop('disabled', false).removeClass('loading');
                this.hideLoadingState();
            }
        }

        /**
         * Assemble content via API
         */
        async assembleContent(sections) {
            const blueprintId = this.getBlueprintId();
            const assemblyOptions = this.getAssemblyOptions();

            const response = await fetch(this.apiBase + 'assemble', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify({
                    sections: sections,
                    blueprint_id: blueprintId,
                    assembly_options: assemblyOptions
                })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Assembly request failed');
            }

            return await response.json();
        }

        /**
         * Generate preview via API
         */
        async generatePreview(assembledContent, previewOptions) {
            const response = await fetch(this.apiBase + 'preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify({
                    assembled_content: assembledContent,
                    preview_options: previewOptions
                })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Preview generation failed');
            }

            return await response.json();
        }

        /**
         * Create draft via API
         */
        async createDraft(draftData) {
            const response = await fetch(this.apiBase + 'create-draft', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify(draftData)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Draft creation failed');
            }

            return await response.json();
        }

        /**
         * Load detected plugins
         */
        async loadDetectedPlugins() {
            try {
                const response = await fetch(this.apiBase + 'detected-plugins', {
                    headers: {
                        'X-WP-Nonce': this.nonce
                    }
                });

                if (response.ok) {
                    const plugins = await response.json();
                    this.displayDetectedPlugins(plugins);
                }
            } catch (error) {
                console.error('Failed to load detected plugins:', error);
            }
        }

        /**
         * Extract sections data from DOM
         */
        extractSectionsData() {
            const sections = [];
            $('.ai-composer-section').each(function() {
                const $section = $(this);
                sections.push({
                    id: $section.data('section-id'),
                    type: $section.data('section-type') || 'content',
                    title: $section.find('.section-title').text().trim(),
                    content: $section.find('.section-content').html() || '',
                    metadata: $section.data('metadata') || {}
                });
            });
            return sections;
        }

        /**
         * Get blueprint ID from current context
         */
        getBlueprintId() {
            return $('#ai-composer-blueprint-id').val() || null;
        }

        /**
         * Get assembly options from form
         */
        getAssemblyOptions() {
            return {
                respect_user_preferences: $('#ai-composer-respect-preferences').is(':checked'),
                enable_fallbacks: $('#ai-composer-enable-fallbacks').is(':checked'),
                validate_html: $('#ai-composer-validate-html').is(':checked'),
                optimize_images: $('#ai-composer-optimize-images').is(':checked'),
                seo_optimization: $('#ai-composer-seo-optimization').is(':checked')
            };
        }

        /**
         * Get preview options from form
         */
        getPreviewOptions() {
            return {
                show_plugin_indicators: $('#ai-composer-show-indicators').is(':checked'),
                include_responsive_preview: $('#ai-composer-responsive-preview').is(':checked'),
                highlight_fallbacks: $('#ai-composer-highlight-fallbacks').is(':checked'),
                show_accessibility_info: $('#ai-composer-accessibility-info').is(':checked')
            };
        }

        /**
         * Prepare draft data from form
         */
        prepareDraftData() {
            const title = $('#ai-composer-draft-title').val().trim();
            const postType = $('#ai-composer-post-type').val() || 'post';
            const status = $('#ai-composer-post-status').val() || 'draft';

            if (!title) {
                this.showNotification('Please enter a title for the draft', 'warning');
                return null;
            }

            return {
                assembled_content: this.currentAssemblyData,
                post_meta: {
                    title: title,
                    post_type: postType,
                    status: status,
                    excerpt: $('#ai-composer-draft-excerpt').val().trim(),
                    featured_image_id: $('#ai-composer-featured-image-id').val() || null,
                    author_id: $('#ai-composer-author-id').val() || null
                },
                seo_data: {
                    meta_title: $('#ai-composer-meta-title').val().trim(),
                    meta_description: $('#ai-composer-meta-description').val().trim(),
                    focus_keyword: $('#ai-composer-focus-keyword').val().trim()
                },
                taxonomies: {
                    categories: this.getSelectedCategories(),
                    tags: this.getSelectedTags()
                }
            };
        }

        /**
         * Get selected categories
         */
        getSelectedCategories() {
            const categories = [];
            $('.ai-composer-category:checked').each(function() {
                categories.push({
                    id: parseInt($(this).val()),
                    name: $(this).data('name')
                });
            });
            return categories;
        }

        /**
         * Get selected tags
         */
        getSelectedTags() {
            const tagInput = $('#ai-composer-tags').val().trim();
            return tagInput ? tagInput.split(',').map(tag => tag.trim()) : [];
        }

        /**
         * Create preview modal
         */
        createPreviewModal() {
            const modalHtml = `
                <div id="ai-composer-preview-modal" class="ai-composer-modal" style="display: none;">
                    <div class="ai-composer-modal-overlay"></div>
                    <div class="ai-composer-modal-content">
                        <div class="ai-composer-modal-header">
                            <h2>Content Preview</h2>
                            <div class="ai-composer-responsive-controls">
                                <button type="button" class="ai-composer-responsive-btn" data-width="320">Mobile</button>
                                <button type="button" class="ai-composer-responsive-btn" data-width="768">Tablet</button>
                                <button type="button" class="ai-composer-responsive-btn active" data-width="1200">Desktop</button>
                            </div>
                            <button type="button" class="ai-composer-modal-close">&times;</button>
                        </div>
                        <div class="ai-composer-modal-body">
                            <div class="ai-composer-preview-container">
                                <iframe id="ai-composer-preview-iframe" frameborder="0"></iframe>
                            </div>
                            <div class="ai-composer-preview-sidebar">
                                <div class="ai-composer-plugin-indicators">
                                    <h3>Plugin Usage</h3>
                                    <div id="ai-composer-indicators-list"></div>
                                </div>
                                <div class="ai-composer-accessibility-report">
                                    <h3>Accessibility</h3>
                                    <div id="ai-composer-accessibility-content"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            this.previewModal = $('#ai-composer-preview-modal');
        }

        /**
         * Show preview modal
         */
        showPreviewModal(previewData) {
            const iframe = $('#ai-composer-preview-iframe');
            iframe.attr('src', previewData.iframe_src);
            
            this.displayPluginIndicators(previewData.plugin_indicators || []);
            this.displayAccessibilityReport(previewData.accessibility_report || {});
            
            this.previewModal.fadeIn(300);
            $('body').addClass('ai-composer-modal-open');
        }

        /**
         * Close preview modal
         */
        closePreviewModal() {
            this.previewModal.fadeOut(300);
            $('body').removeClass('ai-composer-modal-open');
        }

        /**
         * Handle responsive toggle
         */
        handleResponsiveToggle(e) {
            const $button = $(e.currentTarget);
            const width = $button.data('width');
            
            $('.ai-composer-responsive-btn').removeClass('active');
            $button.addClass('active');
            
            $('#ai-composer-preview-iframe').css('width', width + 'px');
        }

        /**
         * Display assembly result
         */
        displayAssemblyResult(assemblyData) {
            const $resultContainer = $('#ai-composer-assembly-result');
            
            if ($resultContainer.length === 0) {
                return;
            }

            const metadata = assemblyData.assembly_metadata || {};
            const indicators = assemblyData.plugin_indicators || [];

            let html = `
                <div class="ai-composer-result-summary">
                    <h3>Assembly Complete</h3>
                    <div class="ai-composer-stats">
                        <span class="stat">
                            <strong>${assemblyData.assembled_content.blocks.length}</strong> blocks assembled
                        </span>
                        <span class="stat">
                            <strong>${metadata.fallbacks_applied || 0}</strong> fallbacks used
                        </span>
                        <span class="stat">
                            <strong>${Object.keys(metadata.blocks_used || {}).length}</strong> plugins used
                        </span>
                    </div>
                </div>
            `;

            if (indicators.length > 0) {
                html += '<div class="ai-composer-plugin-usage">';
                html += '<h4>Plugin Usage</h4>';
                html += '<ul>';
                indicators.forEach(indicator => {
                    const fallbackClass = indicator.fallback_used ? 'fallback' : '';
                    html += `<li class="${fallbackClass}">
                        <span class="plugin-name">${indicator.plugin_used}</span>
                        <span class="block-name">${indicator.block_name}</span>
                        ${indicator.fallback_used ? '<span class="fallback-badge">Fallback</span>' : ''}
                    </li>`;
                });
                html += '</ul>';
                html += '</div>';
            }

            $resultContainer.html(html).show();
        }

        /**
         * Display detected plugins
         */
        displayDetectedPlugins(plugins) {
            const $container = $('#ai-composer-detected-plugins');
            
            if ($container.length === 0) {
                return;
            }

            let html = '';
            Object.entries(plugins).forEach(([key, plugin]) => {
                const activeClass = plugin.is_active ? 'active' : 'inactive';
                html += `
                    <div class="ai-composer-plugin-item ${activeClass}">
                        <div class="plugin-name">${plugin.name}</div>
                        <div class="plugin-stats">
                            <span class="block-count">${plugin.block_count} blocks</span>
                            <span class="status ${activeClass}">${plugin.is_active ? 'Active' : 'Inactive'}</span>
                        </div>
                    </div>
                `;
            });

            $container.html(html);
        }

        /**
         * Display plugin indicators
         */
        displayPluginIndicators(indicators) {
            const $container = $('#ai-composer-indicators-list');
            
            let html = '';
            indicators.forEach(indicator => {
                const fallbackClass = indicator.is_fallback ? 'fallback' : '';
                html += `
                    <div class="ai-composer-indicator-item ${fallbackClass}">
                        <div class="indicator-plugin" style="background-color: ${indicator.color}">
                            ${indicator.plugin}
                        </div>
                        <div class="indicator-details">
                            <div class="block-title">${indicator.block_title}</div>
                            ${indicator.is_fallback ? '<span class="fallback-label">Fallback</span>' : ''}
                        </div>
                    </div>
                `;
            });

            $container.html(html);
        }

        /**
         * Display accessibility report
         */
        displayAccessibilityReport(report) {
            const $container = $('#ai-composer-accessibility-content');
            
            let html = `
                <div class="accessibility-score">
                    <div class="score-circle" data-score="${report.score || 0}">
                        <span class="score-number">${report.score || 0}</span>
                    </div>
                    <div class="score-label">Accessibility Score</div>
                </div>
            `;

            if (report.issues && report.issues.length > 0) {
                html += '<div class="accessibility-issues">';
                html += '<h4>Issues Found</h4>';
                html += '<ul>';
                report.issues.forEach(issue => {
                    html += `<li class="issue">${issue}</li>`;
                });
                html += '</ul>';
                html += '</div>';
            }

            if (report.recommendations && report.recommendations.length > 0) {
                html += '<div class="accessibility-recommendations">';
                html += '<h4>Recommendations</h4>';
                html += '<ul>';
                report.recommendations.forEach(rec => {
                    html += `<li class="recommendation">${rec}</li>`;
                });
                html += '</ul>';
                html += '</div>';
            }

            $container.html(html);
        }

        /**
         * Display draft result
         */
        displayDraftResult(result) {
            const $container = $('#ai-composer-draft-result');
            
            if ($container.length === 0) {
                // Create result container if it doesn't exist
                $('.ai-composer-create-draft-btn').after('<div id="ai-composer-draft-result" class="ai-composer-result-container"></div>');
            }

            const html = `
                <div class="ai-composer-draft-success">
                    <h3>Draft Created Successfully!</h3>
                    <div class="draft-details">
                        <p><strong>Title:</strong> ${result.post_data.title}</p>
                        <p><strong>Word Count:</strong> ${result.post_data.word_count}</p>
                        <p><strong>Blocks:</strong> ${result.post_data.block_count}</p>
                    </div>
                    <div class="draft-actions">
                        <a href="${result.edit_url}" class="button button-primary" target="_blank">Edit Draft</a>
                        <a href="${result.preview_url}" class="button" target="_blank">Preview</a>
                    </div>
                </div>
            `;

            $('#ai-composer-draft-result').html(html).show();
        }

        /**
         * Toggle plugin indicators
         */
        togglePluginIndicators() {
            const showIndicators = $('#ai-composer-show-indicators').is(':checked');
            
            if (this.currentPreviewData && this.previewModal.is(':visible')) {
                // Regenerate preview with new options
                this.handlePreviewClick({ preventDefault: () => {} });
            }
        }

        /**
         * Handle keyboard events
         */
        handleKeydown(e) {
            if (e.keyCode === 27 && this.previewModal.is(':visible')) { // Escape key
                this.closePreviewModal();
            }
        }

        /**
         * Show loading state
         */
        showLoadingState(message) {
            const loadingHtml = `
                <div id="ai-composer-loading" class="ai-composer-loading">
                    <div class="loading-spinner"></div>
                    <div class="loading-message">${message}</div>
                </div>
            `;
            
            if ($('#ai-composer-loading').length === 0) {
                $('body').append(loadingHtml);
            } else {
                $('.loading-message').text(message);
                $('#ai-composer-loading').show();
            }
        }

        /**
         * Hide loading state
         */
        hideLoadingState() {
            $('#ai-composer-loading').fadeOut(300);
        }

        /**
         * Show notification
         */
        showNotification(message, type = 'info') {
            const notificationHtml = `
                <div class="ai-composer-notification ${type}">
                    <div class="notification-content">
                        <span class="notification-message">${message}</span>
                        <button type="button" class="notification-close">&times;</button>
                    </div>
                </div>
            `;
            
            const $notification = $(notificationHtml);
            $('body').append($notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual close
            $notification.find('.notification-close').on('click', function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }
    }

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        if (typeof wpApiSettings !== 'undefined') {
            new AssemblyInterface();
        }
    });

})(jQuery);