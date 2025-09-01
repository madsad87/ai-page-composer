/**
 * AI Page Composer Settings JavaScript
 * 
 * This file handles all interactive functionality for the AI Page Composer settings page
 * including tab switching, form validation, plugin detection refresh, priority sliders,
 * and dynamic form elements.
 * 
 * @package AIPageComposer
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * AI Composer Settings Class
     */
    class AIComposerSettings {
        
        constructor() {
            this.bindEvents();
            this.initTabs();
            this.initSliders();
            this.initFormValidation();
            this.initCustomBlocks();
        }

        /**
         * Bind all event listeners
         */
        bindEvents() {
            // Tab navigation
            $(document).on('click', '.ai-composer-tabs .nav-tab', this.switchTab.bind(this));
            
            // Range slider updates
            $(document).on('input', '.range-input', this.updateRangeDisplay.bind(this));
            
            // Priority slider updates
            $(document).on('input', '.priority-slider', this.updatePriorityDisplay.bind(this));
            
            // Plugin detection refresh
            $(document).on('click', '#refresh-plugin-detection', this.refreshPluginDetection.bind(this));
            
            // Custom block type management
            $(document).on('click', '#add-custom-block', this.addCustomBlockType.bind(this));
            $(document).on('click', '.remove-custom-block', this.removeCustomBlockType.bind(this));
            
            // Form submission
            $(document).on('submit', '#ai-composer-settings-form', this.validateForm.bind(this));
            
            // API key visibility toggle
            $(document).on('click', '.toggle-api-key-visibility', this.toggleApiKeyVisibility.bind(this));
            
            // Settings reset
            $(document).on('click', '#reset-settings', this.resetSettings.bind(this));
            
            // Cost budget alerts
            $(document).on('change', 'input[name*="budget"]', this.checkBudgetLimits.bind(this));
            
            // MVDB connection test
            $(document).on('click', '.test-mvdb-connection', this.testMvdbConnection.bind(this));
        }

        /**
         * Initialize tab functionality
         */
        initTabs() {
            // Show first tab by default
            const firstTab = $('.ai-composer-tabs .nav-tab').first();
            const firstPanel = $(firstTab.attr('href') + '-panel');
            
            firstTab.addClass('nav-tab-active');
            firstPanel.addClass('active').show();
        }

        /**
         * Switch between tabs
         */
        switchTab(e) {
            e.preventDefault();
            
            const $tab = $(e.currentTarget);
            const targetPanel = $tab.attr('href') + '-panel';
            
            // Remove active states
            $('.ai-composer-tabs .nav-tab').removeClass('nav-tab-active');
            $('.ai-composer-panel').removeClass('active').hide();
            
            // Add active states
            $tab.addClass('nav-tab-active');
            $(targetPanel).addClass('active').show();
            
            // Update URL hash without jumping
            if (history.pushState) {
                history.pushState(null, null, $tab.attr('href'));
            }
        }

        /**
         * Initialize range sliders
         */
        initSliders() {
            $('.range-input').each((index, element) => {
                this.updateRangeDisplay({ target: element });
            });
            
            $('.priority-slider').each((index, element) => {
                this.updatePriorityDisplay({ target: element });
            });
        }

        /**
         * Update range slider display value
         */
        updateRangeDisplay(event) {
            const $slider = $(event.target);
            const $valueDisplay = $slider.next('.range-value');
            
            if ($valueDisplay.length) {
                $valueDisplay.text($slider.val());
            }
        }

        /**
         * Update priority slider display and visual feedback
         */
        updatePriorityDisplay(event) {
            const $slider = $(event.target);
            const $valueDisplay = $slider.siblings('.priority-value');
            const $row = $slider.closest('tr');
            
            if ($valueDisplay.length) {
                $valueDisplay.text($slider.val());
            }
            
            // Visual feedback for priority changes
            $row.addClass('priority-changed');
            setTimeout(() => {
                $row.removeClass('priority-changed');
            }, 300);
        }

        /**
         * Initialize form validation
         */
        initFormValidation() {
            // Real-time validation for API keys
            $('input[name*="api_key"]').on('blur', this.validateApiKey.bind(this));
            
            // Real-time validation for budget amounts
            $('input[name*="budget"], input[name*="limit"]').on('blur', this.validateBudgetAmount.bind(this));
            
            // Clear error states on input
            $('.ai-composer-settings input, .ai-composer-settings select').on('input', function() {
                $(this).removeClass('error');
                $(this).siblings('.field-error').remove();
            });
        }

        /**
         * Validate API key format
         */
        validateApiKey(event) {
            const $input = $(event.target);
            const value = $input.val().trim();
            
            if (value && !this.isValidApiKeyFormat(value)) {
                this.showFieldError($input, aiComposerSettings.strings.invalidApiKey || 'Invalid API key format');
                return false;
            }
            
            this.clearFieldError($input);
            return true;
        }

        /**
         * Check if API key format is valid
         */
        isValidApiKeyFormat(key) {
            return /^[a-zA-Z0-9_.-]+$/.test(key);
        }

        /**
         * Validate budget amount
         */
        validateBudgetAmount(event) {
            const $input = $(event.target);
            const value = parseFloat($input.val());
            
            if (isNaN(value) || value <= 0) {
                this.showFieldError($input, 'Budget amount must be greater than 0');
                return false;
            }
            
            this.clearFieldError($input);
            return true;
        }

        /**
         * Show field validation error
         */
        showFieldError($field, message) {
            $field.addClass('error');
            
            // Remove existing error
            $field.siblings('.field-error').remove();
            
            // Add error message
            const $error = $('<span class="field-error"></span>').text(message);
            $field.after($error);
        }

        /**
         * Clear field validation error
         */
        clearFieldError($field) {
            $field.removeClass('error');
            $field.siblings('.field-error').remove();
        }

        /**
         * Validate entire form before submission
         */
        validateForm(event) {
            let isValid = true;
            
            // Validate API keys
            $('input[name*="api_key"]').each((index, element) => {
                if (!this.validateApiKey({ target: element })) {
                    isValid = false;
                }
            });
            
            // Validate budget amounts
            $('input[name*="budget"], input[name*="limit"]').each((index, element) => {
                if (!this.validateBudgetAmount({ target: element })) {
                    isValid = false;
                }
            });
            
            // Validate numeric ranges
            const alphaInput = $('input[name*="alpha_weight"]')[0];
            if (alphaInput && (alphaInput.value < 0 || alphaInput.value > 1)) {
                this.showFieldError($(alphaInput), 'Alpha weight must be between 0 and 1');
                isValid = false;
            }
            
            if (!isValid) {
                event.preventDefault();
                this.showNotice(aiComposerSettings.strings.validationError || 'Please correct the errors below', 'error');
                
                // Focus on first error field
                $('.error').first().focus();
                return false;
            }
            
            return true;
        }

        /**
         * Refresh plugin detection via AJAX
         */
        refreshPluginDetection(event) {
            event.preventDefault();
            
            const $button = $(event.target);
            const originalText = $button.text();
            
            // Show loading state
            $button.text(aiComposerSettings.strings.scanning || 'Scanning...')
                   .prop('disabled', true)
                   .addClass('ai-composer-loading');
            
            // AJAX request
            $.ajax({
                url: aiComposerSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_composer_refresh_plugins',
                    nonce: aiComposerSettings.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message || aiComposerSettings.strings.refreshSuccess, 'success');
                        // Reload page to show updated plugin list
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        this.showNotice(response.data.message || aiComposerSettings.strings.refreshError, 'error');
                    }
                },
                error: () => {
                    this.showNotice(aiComposerSettings.strings.refreshError || 'Failed to refresh plugin detection', 'error');
                },
                complete: () => {
                    // Restore button state
                    $button.text(originalText)
                           .prop('disabled', false)
                           .removeClass('ai-composer-loading');
                }
            });
        }

        /**
         * Initialize custom block type management
         */
        initCustomBlocks() {
            // Add initial event listeners for existing blocks
            $('.remove-custom-block').on('click', this.removeCustomBlockType.bind(this));
        }

        /**
         * Add new custom block type
         */
        addCustomBlockType(event) {
            event.preventDefault();
            
            const container = $('#custom-blocks-container');
            const index = container.children().length;
            
            const blockRow = $(`
                <div class="custom-block-row">
                    <input type="text" 
                           name="ai_composer_settings[block_preferences][custom_block_types][${index}][name]"
                           placeholder="Block Name (e.g., product-showcase)"
                           class="regular-text" required />
                    <input type="text"
                           name="ai_composer_settings[block_preferences][custom_block_types][${index}][namespace]" 
                           placeholder="Namespace (e.g., my-plugin)"
                           class="regular-text" required />
                    <button type="button" class="button remove-custom-block">
                        Remove
                    </button>
                </div>
            `);
            
            container.append(blockRow);
            
            // Focus on first input
            blockRow.find('input').first().focus();
        }

        /**
         * Remove custom block type
         */
        removeCustomBlockType(event) {
            event.preventDefault();
            
            const $row = $(event.target).closest('.custom-block-row');
            $row.fadeOut(300, function() {
                $(this).remove();
            });
        }

        /**
         * Toggle API key visibility
         */
        toggleApiKeyVisibility(event) {
            event.preventDefault();
            
            const $button = $(event.target);
            const $input = $button.siblings('input[type="password"], input[type="text"]');
            
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $button.text('Hide');
            } else {
                $input.attr('type', 'password');
                $button.text('Show');
            }
        }

        /**
         * Reset all settings to defaults
         */
        resetSettings(event) {
            event.preventDefault();
            
            if (!confirm(aiComposerSettings.strings.confirmReset || 'Are you sure you want to reset all settings?')) {
                return;
            }
            
            // Reset form to defaults (this would need server-side handling)
            window.location.href = window.location.href + '&reset=1';
        }

        /**
         * Check budget limits and show warnings
         */
        checkBudgetLimits(event) {
            const $input = $(event.target);
            const value = parseFloat($input.val());
            const dailyBudget = parseFloat($('input[name*="daily_budget_usd"]').val()) || 0;
            const perRunLimit = parseFloat($('input[name*="per_run_limit_usd"]').val()) || 0;
            
            // Warn if per-run limit is close to daily budget
            if (perRunLimit > (dailyBudget * 0.5)) {
                this.showNotice('Warning: Per-run limit is more than 50% of daily budget', 'warning');
            }
        }

        /**
         * Show admin notice
         */
        showNotice(message, type = 'info') {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            // Insert after page title
            $('.ai-composer-settings .wrap h1').after($notice);
            
            // Handle dismiss button
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
            
            // Auto-dismiss after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(() => {
                    $notice.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        }

        /**
         * Initialize tooltips (if WordPress has them available)
         */
        initTooltips() {
            if (typeof $.fn.tooltip === 'function') {
                $('[data-tooltip]').tooltip();
            }
        }

        /**
         * Handle keyboard navigation for tabs
         */
        initKeyboardNavigation() {
            $('.ai-composer-tabs .nav-tab').on('keydown', (event) => {
                const $tabs = $('.ai-composer-tabs .nav-tab');
                const currentIndex = $tabs.index(event.target);
                
                let newIndex;
                
                switch (event.keyCode) {
                    case 37: // Left arrow
                        newIndex = currentIndex > 0 ? currentIndex - 1 : $tabs.length - 1;
                        break;
                    case 39: // Right arrow
                        newIndex = currentIndex < $tabs.length - 1 ? currentIndex + 1 : 0;
                        break;
                    default:
                        return;
                }
                
                event.preventDefault();
                $tabs.eq(newIndex).click().focus();
            });
        }

        /**
         * Test MVDB connection
         */
        testMvdbConnection(event) {
            event.preventDefault();
            
            const $button = $(event.target);
            const $resultsDiv = $('#mvdb-connection-results');
            
            // Get current form values
            const apiUrl = $('#mvdb_api_url').val().trim();
            const accessToken = $('#mvdb_access_token').val().trim();
            
            if (!apiUrl || !accessToken) {
                $resultsDiv.html('<div class="notice notice-error"><p>Please fill in both MVDB API URL and Access Token before testing.</p></div>');
                return;
            }
            
            // Update button state
            $button.prop('disabled', true).text('Testing...');
            $resultsDiv.html('<div class="notice notice-info"><p>Testing MVDB connection...</p></div>');
            
            // Prepare test data
            const testData = {
                action: 'test_mvdb_connection',
                nonce: aiComposerSettings.nonce,
                api_url: apiUrl,
                access_token: accessToken
            };
            
            // Make AJAX request
            $.ajax({
                url: aiComposerSettings.ajaxUrl,
                type: 'POST',
                data: testData,
                timeout: 15000, // 15 second timeout
                success: (response) => {
                    $button.prop('disabled', false).text('Test MVDB Connection');
                    
                    if (response.success) {
                        const result = response.data;
                        let statusHtml = '<div class="notice notice-success"><p><strong>MVDB Connection Test Results:</strong></p>';
                        statusHtml += `<p>✅ Status: ${result.message}</p>`;
                        
                        if (result.response_time) {
                            statusHtml += `<p>⏱️ Response Time: ${result.response_time}ms</p>`;
                        }
                        
                        if (result.endpoint_info) {
                            statusHtml += `<p>🔗 Endpoint: ${result.endpoint_info}</p>`;
                        }
                        
                        statusHtml += '</div>';
                        $resultsDiv.html(statusHtml);
                    } else {
                        let errorHtml = '<div class="notice notice-error"><p><strong>MVDB Connection Failed:</strong></p>';
                        errorHtml += `<p>❌ Error: ${response.data.message || 'Unknown error occurred'}</p>`;
                        
                        if (response.data.details) {
                            errorHtml += `<p>📝 Details: ${response.data.details}</p>`;
                        }
                        
                        if (response.data.suggestions) {
                            errorHtml += '<p><strong>Suggestions:</strong></p><ul>';
                            response.data.suggestions.forEach(suggestion => {
                                errorHtml += `<li>${suggestion}</li>`;
                            });
                            errorHtml += '</ul>';
                        }
                        
                        errorHtml += '</div>';
                        $resultsDiv.html(errorHtml);
                    }
                },
                error: (xhr, status, error) => {
                    $button.prop('disabled', false).text('Test MVDB Connection');
                    
                    let errorMessage = 'Connection test failed';
                    if (status === 'timeout') {
                        errorMessage = 'Connection test timed out (15s). The MVDB service may be slow or unreachable.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    } else if (error) {
                        errorMessage = `Network error: ${error}`;
                    }
                    
                    $resultsDiv.html(`<div class="notice notice-error"><p><strong>Connection Test Failed:</strong></p><p>❌ ${errorMessage}</p></div>`);
                }
            });
        }
    }

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        // Only initialize on AI Composer settings page
        if ($('.ai-composer-settings').length) {
            new AIComposerSettings();
        }
    });

})(jQuery);