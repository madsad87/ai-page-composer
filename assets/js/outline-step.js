/**
 * Outline Generation Step JavaScript
 * 
 * Handles the outline generation step in the AI Page Composer admin interface.
 * 
 * @package AIPageComposer
 */

(function($) {
    'use strict';

    /**
     * Outline Step Manager
     */
    const OutlineStep = {
        /**
         * Current blueprint ID
         */
        blueprintId: null,

        /**
         * Generated outline data
         */
        outlineData: null,

        /**
         * Initialize the outline step
         */
        init: function() {
            this.bindEvents();
            this.initCharCounter();
            this.initRangeValues();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Form submission
            $('#outline-form').on('submit', this.handleFormSubmit.bind(this));

            // Advanced options toggle
            $('.toggle-advanced').on('click', this.toggleAdvancedOptions.bind(this));

            // Character counter
            $('#content-brief').on('input', this.updateCharCounter.bind(this));

            // Range value updates
            $('.form-range').on('input', this.updateRangeValue.bind(this));

            // Regenerate outline
            $('#regenerate-outline').on('click', this.regenerateOutline.bind(this));

            // Approve outline
            $('#approve-outline').on('click', this.approveOutline.bind(this));

            // Section actions
            $(document).on('click', '.edit-section', this.editSection.bind(this));
            $(document).on('click', '.remove-section', this.removeSection.bind(this));

            // Step navigation
            $('.prev-step').on('click', this.goToPreviousStep.bind(this));
            $('.next-step').on('click', this.goToNextStep.bind(this));
        },

        /**
         * Initialize character counter
         */
        initCharCounter: function() {
            this.updateCharCounter();
        },

        /**
         * Initialize range value displays
         */
        initRangeValues: function() {
            $('.form-range').each(function() {
                const $range = $(this);
                const $value = $range.siblings('.range-value');
                $value.text($range.val());
            });
        },

        /**
         * Update character counter
         */
        updateCharCounter: function() {
            const $brief = $('#content-brief');
            const length = $brief.val().length;
            const $counter = $('.char-counter');
            
            $counter.text(length + ' / 2000');
            
            if (length < 10) {
                $counter.css('color', '#d63638');
            } else if (length > 1900) {
                $counter.css('color', '#f56e28');
            } else {
                $counter.css('color', '#50575e');
            }
        },

        /**
         * Update range value display
         */
        updateRangeValue: function(e) {
            const $range = $(e.target);
            const $value = $range.siblings('.range-value');
            $value.text($range.val());
        },

        /**
         * Toggle advanced options
         */
        toggleAdvancedOptions: function(e) {
            e.preventDefault();
            const $button = $(e.target).closest('.toggle-advanced');
            const $options = $('.advanced-options');
            const $icon = $button.find('.dashicons');
            
            $options.slideToggle();
            $icon.toggleClass('dashicons-arrow-down dashicons-arrow-up');
            
            const isExpanded = $button.attr('aria-expanded') === 'true';
            $button.attr('aria-expanded', !isExpanded);
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            if (!this.validateForm()) {
                return;
            }

            this.generateOutline();
        },

        /**
         * Validate form data
         */
        validateForm: function() {
            const brief = $('#content-brief').val().trim();
            
            if (brief.length < 10) {
                this.showError('Content brief must be at least 10 characters long.');
                $('#content-brief').focus();
                return false;
            }

            if (brief.length > 2000) {
                this.showError('Content brief must be less than 2000 characters.');
                $('#content-brief').focus();
                return false;
            }

            if (!this.blueprintId) {
                this.showError('No blueprint selected. Please go back and select a blueprint.');
                return false;
            }

            return true;
        },

        /**
         * Generate outline via API
         */
        generateOutline: function() {
            const formData = this.getFormData();
            
            this.setLoading(true);
            this.hideError();

            wp.apiFetch({
                path: '/ai-composer/v1/outline',
                method: 'POST',
                data: formData
            }).then(response => {
                this.handleOutlineResponse(response);
            }).catch(error => {
                this.handleOutlineError(error);
            }).finally(() => {
                this.setLoading(false);
            });
        },

        /**
         * Get form data for API request
         */
        getFormData: function() {
            const formData = {
                blueprint_id: this.blueprintId,
                brief: $('#content-brief').val().trim(),
                audience: $('#target-audience').val().trim(),
                tone: $('#content-tone').val()
            };

            // Add MVDB parameters if advanced options are filled
            const namespaces = $('#mvdb-namespaces').val().trim();
            if (namespaces) {
                formData.mvdb_params = {
                    namespaces: namespaces.split(',').map(ns => ns.trim()).filter(ns => ns),
                    k: parseInt($('#search-results').val()) || 10,
                    min_score: parseFloat($('#min-score').val()) || 0.5,
                    filters: {}
                };
            }

            // Add alpha value
            formData.alpha = parseFloat($('#alpha-value').val()) || 0.7;

            return formData;
        },

        /**
         * Handle successful outline response
         */
        handleOutlineResponse: function(response) {
            this.outlineData = response;
            this.displayOutline(response);
            this.updateCostEstimate(response.estimated_cost || 0);
            this.enableNextStep();
        },

        /**
         * Handle outline generation error
         */
        handleOutlineError: function(error) {
            console.error('Outline generation error:', error);
            
            let message = 'Failed to generate outline. Please try again.';
            if (error.message) {
                message = error.message;
            } else if (error.responseJSON && error.responseJSON.message) {
                message = error.responseJSON.message;
            }
            
            this.showError(message);
        },

        /**
         * Display generated outline
         */
        displayOutline: function(data) {
            const $container = $('#outline-sections-container');
            $container.empty();

            // Update meta information
            $('.total-words').text(data.total_words + ' words');
            $('.estimated-time').text(data.estimated_time + ' minutes');
            $('.generation-mode').text(data.mode || 'stub');

            // Render sections
            data.sections.forEach(section => {
                const $section = this.renderSection(section);
                $container.append($section);
            });

            // Show results
            $('#outline-results').show();
            
            // Scroll to results
            $('html, body').animate({
                scrollTop: $('#outline-results').offset().top - 100
            }, 500);
        },

        /**
         * Render a single section
         */
        renderSection: function(section) {
            const template = $('#outline-section-template').html();
            
            // Prepare template data
            const templateData = {
                id: section.id,
                heading: section.heading,
                type: section.type,
                targetWords: section.targetWords,
                imageText: section.needsImage ? 'Image required' : 'No image',
                subheadings: section.subheadings || [],
                block_preference: section.block_preference
            };

            // Simple template replacement (in a real app, you might use a template engine)
            let html = template;
            html = html.replace(/\{\{id\}\}/g, templateData.id);
            html = html.replace(/\{\{heading\}\}/g, templateData.heading);
            html = html.replace(/\{\{type\}\}/g, templateData.type);
            html = html.replace(/\{\{targetWords\}\}/g, templateData.targetWords);
            html = html.replace(/\{\{imageText\}\}/g, templateData.imageText);

            // Handle subheadings
            if (templateData.subheadings && templateData.subheadings.length > 0) {
                const subheadingsList = templateData.subheadings.map(sh => `<li>${sh}</li>`).join('');
                html = html.replace(/\{\{#if subheadings\}\}[\s\S]*?\{\{\/if\}\}/g, 
                    `<div class="subheadings">
                        <strong>Key Points:</strong>
                        <ul>${subheadingsList}</ul>
                    </div>`);
            } else {
                html = html.replace(/\{\{#if subheadings\}\}[\s\S]*?\{\{\/if\}\}/g, '');
            }

            // Handle block preferences
            if (templateData.block_preference) {
                const bp = templateData.block_preference;
                let blockPrefHtml = `<div class="block-preferences">
                    <strong>Block Recommendation:</strong>
                    <span class="preferred-plugin">${bp.preferred_plugin}</span>
                    <span class="primary-block">${bp.primary_block}</span>`;
                
                if (bp.pattern_preference) {
                    blockPrefHtml += `<span class="pattern-preference">(${bp.pattern_preference})</span>`;
                }
                
                blockPrefHtml += '</div>';
                
                html = html.replace(/\{\{#if block_preference\}\}[\s\S]*?\{\{\/if\}\}/g, blockPrefHtml);
            } else {
                html = html.replace(/\{\{#if block_preference\}\}[\s\S]*?\{\{\/if\}\}/g, '');
            }

            return $(html);
        },

        /**
         * Regenerate outline
         */
        regenerateOutline: function() {
            this.generateOutline();
        },

        /**
         * Approve outline and move to next step
         */
        approveOutline: function() {
            if (this.outlineData) {
                // Store outline data for next step
                this.storeOutlineData();
                this.goToNextStep();
            }
        },

        /**
         * Store outline data for use in next steps
         */
        storeOutlineData: function() {
            // Store in session storage or trigger custom event
            sessionStorage.setItem('ai_composer_outline', JSON.stringify(this.outlineData));
            
            // Trigger custom event for other components
            $(document).trigger('ai_composer_outline_approved', [this.outlineData]);
        },

        /**
         * Edit section
         */
        editSection: function(e) {
            const $section = $(e.target).closest('.outline-section');
            const sectionId = $section.data('section-id');
            
            // In a full implementation, this would open an edit modal
            console.log('Edit section:', sectionId);
        },

        /**
         * Remove section
         */
        removeSection: function(e) {
            if (confirm('Are you sure you want to remove this section?')) {
                const $section = $(e.target).closest('.outline-section');
                $section.fadeOut(() => {
                    $section.remove();
                    this.updateOutlineData();
                });
            }
        },

        /**
         * Update outline data after modifications
         */
        updateOutlineData: function() {
            // Recalculate totals and update data
            if (this.outlineData) {
                const sections = [];
                $('.outline-section').each(function() {
                    const sectionId = $(this).data('section-id');
                    const section = this.outlineData.sections.find(s => s.id === sectionId);
                    if (section) {
                        sections.push(section);
                    }
                }.bind(this));
                
                this.outlineData.sections = sections;
                this.outlineData.total_words = sections.reduce((sum, s) => sum + s.targetWords, 0);
                
                // Update display
                $('.total-words').text(this.outlineData.total_words + ' words');
            }
        },

        /**
         * Set loading state
         */
        setLoading: function(loading) {
            const $btn = $('#generate-outline-btn');
            const $text = $btn.find('.btn-text');
            const $spinner = $btn.find('.spinner');
            
            if (loading) {
                $btn.prop('disabled', true);
                $text.text('Generating...');
                $spinner.show();
            } else {
                $btn.prop('disabled', false);
                $text.text('Generate Outline');
                $spinner.hide();
            }
        },

        /**
         * Update cost estimate
         */
        updateCostEstimate: function(cost) {
            const $estimate = $('.cost-estimate');
            $estimate.text('Estimated cost: $' + cost.toFixed(4));
            $estimate.show();
        },

        /**
         * Show error message
         */
        showError: function(message) {
            // Remove existing error
            $('.outline-error').remove();
            
            // Add new error
            const $error = $(`<div class="notice notice-error outline-error"><p>${message}</p></div>`);
            $('#outline-form').before($error);
            
            // Scroll to error
            $('html, body').animate({
                scrollTop: $error.offset().top - 100
            }, 300);
        },

        /**
         * Hide error message
         */
        hideError: function() {
            $('.outline-error').fadeOut(() => {
                $('.outline-error').remove();
            });
        },

        /**
         * Enable next step
         */
        enableNextStep: function() {
            $('.next-step').prop('disabled', false);
        },

        /**
         * Go to previous step
         */
        goToPreviousStep: function() {
            $(document).trigger('ai_composer_prev_step');
        },

        /**
         * Go to next step
         */
        goToNextStep: function() {
            $(document).trigger('ai_composer_next_step');
        },

        /**
         * Set blueprint ID
         */
        setBlueprintId: function(blueprintId) {
            this.blueprintId = blueprintId;
        },

        /**
         * Reset step
         */
        reset: function() {
            $('#outline-form')[0].reset();
            $('#outline-results').hide();
            $('.next-step').prop('disabled', true);
            this.outlineData = null;
            this.updateCharCounter();
            this.hideError();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        OutlineStep.init();
        
        // Listen for custom events
        $(document).on('ai_composer_blueprint_selected', function(e, blueprintId) {
            OutlineStep.setBlueprintId(blueprintId);
        });
        
        $(document).on('ai_composer_step_shown', function(e, stepId) {
            if (stepId === 'outline-generation-step') {
                // Step is now visible, focus on first input
                $('#content-brief').focus();
            }
        });
        
        $(document).on('ai_composer_reset', function() {
            OutlineStep.reset();
        });
    });

    // Export for global access
    window.AIComposer = window.AIComposer || {};
    window.AIComposer.OutlineStep = OutlineStep;

})(jQuery);