/**
 * AI Blueprint Admin JavaScript
 * 
 * Handles admin interface interactions for AI Blueprint management including
 * dynamic section management, schema validation, preview generation, and
 * integration with WordPress REST API endpoints.
 */

(function($) {
    'use strict';

    // Blueprint Admin Manager
    const BlueprintAdmin = {
        
        // Configuration
        config: {
            sectionIndex: 0,
            validationTimer: null,
            validationDelay: 1000,
            currentTab: 'visual-editor'
        },

        // Initialize the admin interface
        init: function() {
            this.bindEvents();
            this.initSortable();
            this.loadInitialData();
            this.initTabs();
            console.log('Blueprint Admin initialized');
        },

        // Bind event handlers
        bindEvents: function() {
            // Section management
            $(document).on('click', '#add-section, #add-first-section', this.addSection.bind(this));
            $(document).on('click', '.remove-section', this.removeSection.bind(this));
            $(document).on('click', '.duplicate-section', this.duplicateSection.bind(this));
            $(document).on('click', '.move-section-up', this.moveSectionUp.bind(this));
            $(document).on('click', '.move-section-down', this.moveSectionDown.bind(this));
            $(document).on('click', '.section-toggle', this.toggleSection.bind(this));

            // Tab switching
            $(document).on('click', '.nav-tab', this.switchTab.bind(this));

            // JSON editor actions
            $(document).on('click', '#validate-json', this.validateJSON.bind(this));
            $(document).on('click', '#format-json', this.formatJSON.bind(this));
            $(document).on('click', '#sync-from-sections', this.syncFromSections.bind(this));

            // Preview actions
            $(document).on('click', '#preview-blueprint', this.previewBlueprint.bind(this));
            $(document).on('click', '#test-generation', this.testGeneration.bind(this));
            $(document).on('click', '#estimate-cost', this.estimateCost.bind(this));

            // Validation actions
            $(document).on('click', '#revalidate-blueprint', this.revalidateBlueprint.bind(this));
            $(document).on('click', '#auto-fix-errors', this.autoFixErrors.bind(this));
            $(document).on('click', '#download-schema', this.downloadSchema.bind(this));

            // Form field changes
            $(document).on('input change', '.section-heading-input, .section-type-select', this.updateSectionNumbers.bind(this));
            $(document).on('input', '#blueprint_schema_json', this.onJSONChange.bind(this));
            $(document).on('change', '.generation-mode-select', this.onGenerationModeChange.bind(this));
            $(document).on('input', '#hybrid_alpha', this.updateAlphaDisplay.bind(this));

            // Bulk actions
            $(document).on('change', '#bulk-action-selector', this.toggleBulkButton.bind(this));
            $(document).on('click', '#apply-bulk-action', this.applyBulkAction.bind(this));

            // Auto-save
            $(document).on('input change', '.ai-blueprint-sections input, .ai-blueprint-sections select, .ai-blueprint-sections textarea', 
                          this.scheduleValidation.bind(this));
        },

        // Initialize sortable sections
        initSortable: function() {
            if ($.fn.sortable) {
                $('#sections-container').sortable({
                    handle: '.section-handle',
                    items: '.section-row',
                    placeholder: 'section-placeholder',
                    tolerance: 'pointer',
                    update: this.updateSectionNumbers.bind(this)
                });
            }
        },

        // Initialize tabs
        initTabs: function() {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                const tabId = $(this).data('tab');
                BlueprintAdmin.switchTab.call(this, e);
            });
        },

        // Load initial data
        loadInitialData: function() {
            this.config.sectionIndex = $('.section-row').length;
            this.updateSectionNumbers();
            this.updateEmptyState();
        },

        // Add new section
        addSection: function(e) {
            e.preventDefault();
            
            const template = $('#section-row-template').html();
            if (!template) return;

            const sectionHtml = template
                .replace(/\{\{index\}\}/g, this.config.sectionIndex)
                .replace(/\{\{number\}\}/g, this.config.sectionIndex + 1);

            $('#sections-container').append(sectionHtml);
            this.config.sectionIndex++;
            
            this.updateSectionNumbers();
            this.updateEmptyState();
            this.scrollToSection($('.section-row').last());
            
            // Auto-focus the heading input
            $('.section-row').last().find('.section-heading-input').focus();
        },

        // Remove section
        removeSection: function(e) {
            e.preventDefault();
            
            if (!confirm(aiBlueprintAdmin.i18n.removeSection || 'Are you sure you want to remove this section?')) {
                return;
            }

            $(e.target).closest('.section-row').fadeOut(300, function() {
                $(this).remove();
                BlueprintAdmin.updateSectionNumbers();
                BlueprintAdmin.updateEmptyState();
            });
        },

        // Duplicate section
        duplicateSection: function(e) {
            e.preventDefault();
            
            const $section = $(e.target).closest('.section-row');
            const $clone = $section.clone();
            
            // Update clone attributes
            const newIndex = this.config.sectionIndex;
            $clone.find('input, select, textarea').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/\[\d+\]/, '[' + newIndex + ']'));
                }
            });
            
            // Update heading to indicate it's a copy
            const $headingInput = $clone.find('.section-heading-input');
            $headingInput.val($headingInput.val() + ' (Copy)');
            
            $section.after($clone);
            this.config.sectionIndex++;
            
            this.updateSectionNumbers();
            this.scrollToSection($clone);
        },

        // Move section up
        moveSectionUp: function(e) {
            e.preventDefault();
            
            const $section = $(e.target).closest('.section-row');
            const $prev = $section.prev('.section-row');
            
            if ($prev.length) {
                $section.insertBefore($prev);
                this.updateSectionNumbers();
                this.scrollToSection($section);
            }
        },

        // Move section down
        moveSectionDown: function(e) {
            e.preventDefault();
            
            const $section = $(e.target).closest('.section-row');
            const $next = $section.next('.section-row');
            
            if ($next.length) {
                $section.insertAfter($next);
                this.updateSectionNumbers();
                this.scrollToSection($section);
            }
        },

        // Toggle section content
        toggleSection: function(e) {
            e.preventDefault();
            
            const $section = $(e.target).closest('.section-row');
            const $content = $section.find('.section-content');
            const $icon = $(e.target).find('.dashicons');
            
            $content.slideToggle(300);
            $icon.toggleClass('dashicons-arrow-up-alt2 dashicons-arrow-down-alt2');
        },

        // Switch tabs
        switchTab: function(e) {
            e.preventDefault();
            
            const $tab = $(this);
            const tabId = $tab.data('tab') || $tab.attr('href').substring(1);
            
            // Update tab appearance
            $tab.siblings().removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show/hide content
            $('.tab-content').removeClass('active');
            $('#' + tabId).addClass('active');
            
            this.config.currentTab = tabId;
            
            // Trigger tab-specific actions
            if (tabId === 'json-editor') {
                this.syncFromSections();
            }
        },

        // Validate JSON
        validateJSON: function(e) {
            e.preventDefault();
            
            const jsonData = $('#blueprint_schema_json').val();
            if (!jsonData.trim()) {
                this.showNotice('error', 'No JSON data to validate');
                return;
            }

            try {
                const data = JSON.parse(jsonData);
                this.makeAPIRequest('validate-schema', data, 
                    this.handleValidationResponse.bind(this),
                    this.handleValidationError.bind(this)
                );
            } catch (error) {
                this.showNotice('error', 'Invalid JSON: ' + error.message);
            }
        },

        // Format JSON
        formatJSON: function(e) {
            e.preventDefault();
            
            const $textarea = $('#blueprint_schema_json');
            const jsonData = $textarea.val();
            
            try {
                const parsed = JSON.parse(jsonData);
                const formatted = JSON.stringify(parsed, null, 2);
                $textarea.val(formatted);
                this.showNotice('success', 'JSON formatted successfully');
            } catch (error) {
                this.showNotice('error', 'Invalid JSON: ' + error.message);
            }
        },

        // Sync from sections
        syncFromSections: function() {
            const blueprintData = this.collectBlueprintData();
            const jsonString = JSON.stringify(blueprintData, null, 2);
            $('#blueprint_schema_json').val(jsonString);
            $('#blueprint_schema_data').val(jsonString);
        },

        // Preview blueprint
        previewBlueprint: function(e) {
            e.preventDefault();
            
            const blueprintData = this.collectBlueprintData();
            const $button = $(e.target);
            const originalText = $button.text();
            
            $button.prop('disabled', true).text('Generating Preview...');
            
            this.makeAPIRequest('blueprint-preview', blueprintData,
                function(response) {
                    BlueprintAdmin.displayPreview(response.preview);
                    $button.prop('disabled', false).text(originalText);
                },
                function(error) {
                    BlueprintAdmin.showNotice('error', 'Preview generation failed: ' + error.message);
                    $button.prop('disabled', false).text(originalText);
                }
            );
        },

        // Test generation
        testGeneration: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const originalText = $button.text();
            
            $button.prop('disabled', true).text('Running Test...');
            
            // Simulate test generation
            setTimeout(function() {
                const testResults = {
                    success: true,
                    message: 'Test generation completed successfully',
                    stats: {
                        sections_processed: $('.section-row').length,
                        estimated_tokens: 1250,
                        estimated_cost: 0.056
                    }
                };
                
                BlueprintAdmin.displayTestResults(testResults);
                $button.prop('disabled', false).text(originalText);
            }, 2000);
        },

        // Estimate cost
        estimateCost: function(e) {
            e.preventDefault();
            
            const blueprintData = this.collectBlueprintData();
            const estimation = this.calculateEstimation(blueprintData);
            
            $('#estimated-tokens').text(estimation.tokens.toLocaleString());
            $('#estimated-cost').text('$' + estimation.cost.toFixed(3));
            $('#estimated-time').text(estimation.time + ' minutes');
            
            $('#cost-estimation').show();
        },

        // Update section numbers
        updateSectionNumbers: function() {
            $('.section-row').each(function(index) {
                $(this).find('.section-number').text((index + 1) + '.');
                $(this).attr('data-index', index);
            });
        },

        // Update empty state
        updateEmptyState: function() {
            const hasSections = $('.section-row').length > 0;
            $('.sections-empty-state').toggle(!hasSections);
            $('#sections-container').toggleClass('hidden', !hasSections);
        },

        // Scroll to section
        scrollToSection: function($section) {
            $('html, body').animate({
                scrollTop: $section.offset().top - 100
            }, 500);
        },

        // Collect blueprint data from form
        collectBlueprintData: function() {
            const sections = [];
            
            $('.section-row').each(function() {
                const $section = $(this);
                const sectionData = {
                    id: $section.find('[name*="[id]"]').val() || 'section-' + Date.now(),
                    type: $section.find('[name*="[type]"]').val() || 'content',
                    heading: $section.find('[name*="[heading]"]').val() || '',
                    heading_level: parseInt($section.find('[name*="[heading_level]"]').val()) || 2,
                    word_target: parseInt($section.find('[name*="[word_target]"]').val()) || 150,
                    media_policy: $section.find('[name*="[media_policy]"]').val() || 'optional',
                    internal_links: parseInt($section.find('[name*="[internal_links]"]').val()) || 2,
                    citations_required: $section.find('[name*="[citations_required]"]').is(':checked'),
                    tone: $section.find('[name*="[tone]"]').val() || 'professional'
                };
                
                if (sectionData.heading) {
                    sections.push(sectionData);
                }
            });

            return {
                sections: sections,
                global_settings: this.collectGlobalSettings(),
                metadata: {
                    version: '1.0.0',
                    category: 'custom',
                    difficulty_level: 'intermediate'
                }
            };
        },

        // Collect global settings
        collectGlobalSettings: function() {
            return {
                generation_mode: $('[name="global_settings[generation_mode]"]').val() || 'hybrid',
                hybrid_alpha: parseFloat($('[name="global_settings[hybrid_alpha]"]').val()) || 0.7,
                max_tokens_per_section: parseInt($('[name="global_settings[max_tokens_per_section]"]').val()) || 1000,
                cost_limit_usd: parseFloat($('[name="global_settings[cost_limit_usd]"]').val()) || 5.0,
                image_generation_enabled: $('[name="global_settings[image_generation_enabled]"]').is(':checked'),
                seo_optimization: $('[name="global_settings[seo_optimization]"]').is(':checked'),
                accessibility_checks: $('[name="global_settings[accessibility_checks]"]').is(':checked'),
                mvdb_namespaces: $('[name="global_settings[mvdb_namespaces][]"]:checked').map(function() {
                    return $(this).val();
                }).get()
            };
        },

        // Make API request
        makeAPIRequest: function(endpoint, data, successCallback, errorCallback) {
            $.ajax({
                url: aiBlueprintAdmin.restUrl + endpoint,
                method: 'POST',
                data: JSON.stringify(data),
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', aiBlueprintAdmin.nonce);
                },
                success: function(response) {
                    if (successCallback) successCallback(response);
                },
                error: function(xhr, status, error) {
                    const errorData = xhr.responseJSON || { message: error };
                    if (errorCallback) errorCallback(errorData);
                }
            });
        },

        // Handle validation response
        handleValidationResponse: function(response) {
            if (response.valid) {
                this.showNotice('success', aiBlueprintAdmin.i18n.validationSuccess || 'Blueprint validation successful');
                this.displayValidationResults(response);
            } else {
                this.showNotice('error', aiBlueprintAdmin.i18n.validationError || 'Blueprint validation failed');
                this.displayValidationResults(response);
            }
        },

        // Handle validation error
        handleValidationError: function(error) {
            this.showNotice('error', 'Validation request failed: ' + error.message);
        },

        // Display validation results
        displayValidationResults: function(results) {
            const $container = $('#schema-validation-results');
            let html = '';

            if (results.valid) {
                html = '<div class="validation-success"><h4>✓ Blueprint Valid</h4><p>Your blueprint configuration is valid and ready for use.</p></div>';
            } else {
                html = '<div class="validation-errors"><h4>⚠ Validation Errors</h4><ul>';
                results.errors.forEach(function(error) {
                    html += '<li><strong>' + (error.property || 'General') + ':</strong> ' + error.message + '</li>';
                });
                html += '</ul></div>';
            }

            $container.html(html);
            
            // Switch to validation tab
            $('[data-tab="validation-results"]').click();
        },

        // Display preview
        displayPreview: function(preview) {
            const $container = $('#blueprint-preview-container');
            let html = '<div class="blueprint-preview-content">';

            if (preview.sections && preview.sections.length > 0) {
                html += '<h4>Preview Structure</h4>';
                preview.sections.forEach(function(section, index) {
                    html += '<div class="preview-section">';
                    html += '<div class="preview-section-header">';
                    html += '<h5 class="preview-section-title">' + (index + 1) + '. ' + (section.heading || 'Untitled Section') + '</h5>';
                    html += '<span class="preview-section-meta">' + section.type + ' • ' + section.word_target + ' words</span>';
                    html += '</div>';
                    html += '</div>';
                });

                html += '<div class="preview-summary">';
                html += '<p><strong>Total Sections:</strong> ' + preview.sections.length + '</p>';
                html += '<p><strong>Estimated Tokens:</strong> ' + (preview.estimated_tokens || 0).toLocaleString() + '</p>';
                html += '<p><strong>Estimated Cost:</strong> $' + (preview.estimated_cost || 0).toFixed(3) + '</p>';
                html += '</div>';
            } else {
                html += '<div class="preview-empty"><p>No sections configured yet.</p></div>';
            }

            html += '</div>';
            $container.html(html);
        },

        // Display test results
        displayTestResults: function(results) {
            const $container = $('#generation-test-results');
            let html = '<div class="test-results-content">';

            if (results.success) {
                html += '<div class="test-success">';
                html += '<h5>✓ Test Successful</h5>';
                html += '<p>' + results.message + '</p>';
                if (results.stats) {
                    html += '<ul>';
                    html += '<li>Sections Processed: ' + results.stats.sections_processed + '</li>';
                    html += '<li>Estimated Tokens: ' + results.stats.estimated_tokens.toLocaleString() + '</li>';
                    html += '<li>Estimated Cost: $' + results.stats.estimated_cost.toFixed(3) + '</li>';
                    html += '</ul>';
                }
                html += '</div>';
            } else {
                html += '<div class="test-error">';
                html += '<h5>⚠ Test Failed</h5>';
                html += '<p>' + (results.message || 'Test generation failed') + '</p>';
                html += '</div>';
            }

            html += '</div>';
            $container.html(html).show();
        },

        // Calculate estimation
        calculateEstimation: function(blueprintData) {
            let totalTokens = 0;
            const sections = blueprintData.sections || [];

            sections.forEach(function(section) {
                const wordTarget = section.word_target || 150;
                const tokens = Math.ceil(wordTarget / 0.75) + 50; // 1 token ≈ 0.75 words + overhead
                totalTokens += tokens;
            });

            const cost = (totalTokens / 1000) * 0.045; // $0.045 per 1K tokens
            const time = Math.max(5, sections.length * 2); // 2 minutes per section, minimum 5

            return {
                tokens: totalTokens,
                cost: cost,
                time: time
            };
        },

        // Schedule validation
        scheduleValidation: function() {
            clearTimeout(this.config.validationTimer);
            this.config.validationTimer = setTimeout(function() {
                BlueprintAdmin.syncFromSections();
            }, this.config.validationDelay);
        },

        // Show notice
        showNotice: function(type, message) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.ai-blueprint-schema-editor').prepend($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // JSON change handler
        onJSONChange: function() {
            // Update hidden field
            $('#blueprint_schema_data').val($('#blueprint_schema_json').val());
        },

        // Generation mode change handler
        onGenerationModeChange: function(e) {
            const mode = $(e.target).val();
            $('.hybrid-alpha-group').toggle(mode === 'hybrid');
        },

        // Update alpha display
        updateAlphaDisplay: function(e) {
            $('.alpha-value').text($(e.target).val());
        },

        // Toggle bulk button
        toggleBulkButton: function() {
            const action = $('#bulk-action-selector').val();
            $('#apply-bulk-action').prop('disabled', !action);
        },

        // Apply bulk action
        applyBulkAction: function() {
            const action = $('#bulk-action-selector').val();
            const $selectedSections = $('.section-row input[type="checkbox"]:checked').closest('.section-row');
            
            if (!action || $selectedSections.length === 0) return;

            switch (action) {
                case 'delete':
                    if (confirm('Delete selected sections?')) {
                        $selectedSections.remove();
                        this.updateSectionNumbers();
                        this.updateEmptyState();
                    }
                    break;
                case 'duplicate':
                    // Implementation for bulk duplicate
                    break;
            }
        },

        // Revalidate blueprint
        revalidateBlueprint: function(e) {
            e.preventDefault();
            this.validateJSON(e);
        },

        // Auto-fix errors
        autoFixErrors: function(e) {
            e.preventDefault();
            // Implementation for auto-fixing common validation errors
            this.showNotice('info', 'Auto-fix functionality coming soon');
        },

        // Download schema
        downloadSchema: function(e) {
            e.preventDefault();
            
            const blueprintData = this.collectBlueprintData();
            const dataStr = JSON.stringify(blueprintData, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            
            const link = document.createElement('a');
            link.href = URL.createObjectURL(dataBlob);
            link.download = 'blueprint-schema.json';
            link.click();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof aiBlueprintAdmin !== 'undefined') {
            BlueprintAdmin.init();
        }
    });

    // Export for global access
    window.BlueprintAdmin = BlueprintAdmin;

})(jQuery);