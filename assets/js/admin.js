/*!
 * AI Page Composer Admin Interface JavaScript
 * 
 * This file provides interactive functionality for AI Page Composer admin pages including
 * general admin enhancements, dashboard widgets, and admin-specific user interface features.
 * It integrates with WordPress admin APIs and complements the settings-specific JavaScript.
 * 
 * AI Page Composer Admin JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    /**
     * AI Page Composer Admin functionality object
     */
    const AIComposerAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initDashboardWidgets();
            this.initQuickActions();
            console.log('AI Page Composer Admin initialized');
        },

        /**
         * Bind admin events
         */
        bindEvents: function() {
            $(document).ready(this.onReady.bind(this));
            
            // Quick generation button in post editor
            $(document).on('click', '.ai-composer-quick-generate', this.quickGenerate.bind(this));
            
            // Cost tracking updates
            $(document).on('click', '.refresh-cost-stats', this.refreshCostStats.bind(this));
            
            // Plugin status checks
            $(document).on('click', '.check-api-status', this.checkApiStatus.bind(this));
            
            // Admin notices dismiss
            $(document).on('click', '.ai-composer-notice .notice-dismiss', this.dismissNotice.bind(this));
        },

        /**
         * Document ready handler for admin
         */
        onReady: function() {
            this.initMetaBoxes();
            this.checkPluginStatus();
            this.initTooltips();
        },

        /**
         * Initialize dashboard widgets
         */
        initDashboardWidgets: function() {
            // Add AI Composer dashboard widget if on dashboard
            if ($('#dashboard-widgets').length) {
                this.loadDashboardWidget();
            }
        },

        /**
         * Load dashboard widget content
         */
        loadDashboardWidget: function() {
            const $widget = $('#ai-composer-dashboard-widget');
            if (!$widget.length) return;
            
            this.ajaxRequest('get_dashboard_stats', {}, function(response) {
                if (response.success) {
                    $widget.find('.inside').html(response.data.html);
                }
            });
        },

        /**
         * Initialize meta boxes for post editor
         */
        initMetaBoxes: function() {
            // AI Composer meta box in post editor
            const $metaBox = $('#ai-composer-meta-box');
            if ($metaBox.length) {
                this.initPostEditorIntegration();
            }
        },

        /**
         * Initialize post editor integration
         */
        initPostEditorIntegration: function() {
            // Add AI Composer button to post editor toolbar
            if (typeof wp !== 'undefined' && wp.data) {
                this.addEditorToolbarButton();
            }
            
            // Bind content insertion handlers
            $(document).on('click', '.insert-content', this.insertGeneratedContent.bind(this));
            $(document).on('click', '.preview-content', this.toggleContentPreview.bind(this));
        },
        
        /**
         * Insert generated content into the editor
         */
        insertGeneratedContent: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const contentData = JSON.parse($button.data('content'));
            
            if (typeof wp !== 'undefined' && wp.data && wp.blocks) {
                // For Gutenberg editor
                this.insertIntoGutenberg(contentData);
            } else {
                // For classic editor
                this.insertIntoClassicEditor(contentData);
            }
            
            $button.text('✓ Inserted!').prop('disabled', true);
            setTimeout(() => {
                $button.text('Insert into Editor').prop('disabled', false);
            }, 2000);
        },
        
        /**
         * Insert content into Gutenberg editor
         */
        insertIntoGutenberg: function(contentData) {
            try {
                const { dispatch, select } = wp.data;
                const blocks = wp.blocks.parse(contentData.raw || '');
                
                if (blocks.length > 0) {
                    // Insert blocks at the end
                    const currentBlocks = select('core/block-editor').getBlocks();
                    const insertIndex = currentBlocks.length;
                    
                    dispatch('core/block-editor').insertBlocks(blocks, insertIndex);
                    
                    this.showNotice('Content inserted into editor successfully!', 'success');
                } else {
                    throw new Error('No valid blocks to insert');
                }
            } catch (error) {
                console.error('Gutenberg insertion failed:', error);
                this.showNotice('Failed to insert content into Gutenberg editor', 'error');
            }
        },
        
        /**
         * Insert content into classic editor
         */
        insertIntoClassicEditor: function(contentData) {
            try {
                const editor = window.tinymce && tinymce.get('content');
                
                if (editor && !editor.isHidden()) {
                    // TinyMCE is active
                    const content = contentData.html || contentData.content || '';
                    editor.insertContent('<br>' + content);
                    this.showNotice('Content inserted into editor successfully!', 'success');
                } else {
                    // Text mode
                    const textarea = document.getElementById('content');
                    if (textarea) {
                        const content = contentData.content || contentData.html || '';
                        textarea.value += '\n\n' + content;
                        this.showNotice('Content inserted into editor successfully!', 'success');
                    } else {
                        throw new Error('Editor not found');
                    }
                }
            } catch (error) {
                console.error('Classic editor insertion failed:', error);
                this.showNotice('Failed to insert content into classic editor', 'error');
            }
        },
        
        /**
         * Toggle content preview
         */
        toggleContentPreview: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $preview = $button.closest('.ai-composer-actions').siblings('.ai-composer-preview');
            
            if ($preview.is(':visible')) {
                $preview.slideUp();
                $button.text('Preview Content');
            } else {
                $preview.slideDown();
                $button.text('Hide Preview');
            }
        },
        
        /**
         * Escape HTML for safe insertion
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            const noticeClass = 'notice notice-' + type;
            const $notice = $('<div class="' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Insert after h1 if on admin page
            if ($('.wrap h1').length) {
                $('.wrap h1').after($notice);
            } else {
                // Fallback to body
                $('body').prepend($notice);
            }
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        },

        /**
         * Add toolbar button to block editor
         */
        addEditorToolbarButton: function() {
            // This would integrate with Gutenberg editor
            // For now, we'll add a button to the post editor meta box
            const $metaBox = $('#ai-composer-meta-box .inside');
            if ($metaBox.length) {
                const buttonHtml = `
                    <p>
                        <button type="button" class="button button-primary ai-composer-quick-generate">
                            🤖 Generate with AI
                        </button>
                        <button type="button" class="button button-secondary check-api-status">
                            Check API Status
                        </button>
                    </p>
                    <div id="ai-composer-generation-status"></div>
                `;
                $metaBox.html(buttonHtml);
            }
        },

        /**
         * Initialize quick actions
         */
        initQuickActions: function() {
            // Add quick action menu items
            this.addQuickActionMenuItems();
        },

        /**
         * Add quick action menu items to admin bar
         */
        addQuickActionMenuItems: function() {
            const $adminBar = $('#wpadminbar');
            if ($adminBar.length) {
                // This would add items to the WordPress admin bar
                // Implementation depends on specific requirements
            }
        },

        /**
         * Handle quick generation
         */
        quickGenerate: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const postId = $('#post_ID').val() || 0;
            
            if (!postId) {
                this.showNotice('Please save the post first', 'warning');
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).text('Generating...');
            
            const $status = $('#ai-composer-generation-status');
            $status.html('<div class="ai-composer-loading">🤖 Generating content with AI...</div>');
            
            this.ajaxRequest('quick_generate_content', {
                post_id: postId
            }, function(response) {
                $button.prop('disabled', false).text('🤖 Generate with AI');
                
                if (response.success) {
                    const data = response.data;
                    
                    // Display success message with metadata
                    let statusHtml = '<div class="notice notice-success"><p><strong>Content generated successfully!</strong></p>';
                    
                    if (data.metadata) {
                        statusHtml += '<p class="description">';
                        statusHtml += 'Generated ' + data.metadata.word_count + ' words in ' + data.metadata.processing_time_ms + 'ms. ';
                        statusHtml += 'Cost: $' + parseFloat(data.metadata.cost_usd).toFixed(4);
                        statusHtml += '</p>';
                    }
                    
                    statusHtml += '</div>';
                    
                    // Add action buttons
                    statusHtml += '<div class="ai-composer-actions" style="margin-top: 10px;">';
                    statusHtml += '<button type="button" class="button button-primary insert-content" data-content="' + 
                                 AIComposerAdmin.escapeHtml(JSON.stringify(data.block_json)) + '">Insert into Editor</button> ';
                    statusHtml += '<button type="button" class="button button-secondary preview-content">Preview Content</button>';
                    statusHtml += '</div>';
                    
                    // Add preview area (hidden by default)
                    statusHtml += '<div class="ai-composer-preview" style="display: none; margin-top: 15px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9;">';
                    statusHtml += '<h4>Generated Content Preview:</h4>';
                    statusHtml += '<div class="preview-content">' + (data.html || data.content) + '</div>';
                    statusHtml += '</div>';
                    
                    $status.html(statusHtml);
                    
                } else {
                    const errorMsg = response.data?.message || 'Generation failed';
                    let errorHtml = '<div class="notice notice-error"><p><strong>Generation failed:</strong> ' + errorMsg + '</p></div>';
                    
                    // Add configure link if APIs not configured
                    if (response.data?.action === 'configure_apis') {
                        const settingsUrl = (typeof aiComposerAdmin !== 'undefined' && aiComposerAdmin.settingsUrl) ? aiComposerAdmin.settingsUrl :
                                           (typeof aiComposerAdminBase !== 'undefined' && aiComposerAdminBase.settingsUrl) ? aiComposerAdminBase.settingsUrl :
                                           'edit.php?page=ai-composer';
                        errorHtml += '<div style="margin-top: 10px;">';
                        errorHtml += '<a href="' + settingsUrl + '" class="button button-secondary">Configure API Keys</a>';
                        errorHtml += '</div>';
                    }
                    
                    $status.html(errorHtml);
                }
            }.bind(this));
        },

        /**
         * Check API status
         */
        checkApiStatus: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            $button.prop('disabled', true).text('Checking...');
            
            this.ajaxRequest('check_api_status', {}, function(response) {
                $button.prop('disabled', false).text('Test API Connections');
                
                if (response.success) {
                    const status = response.data;
                    let statusHtml = '<div class="api-status-results">';
                    
                    Object.keys(status).forEach(api => {
                        const isOnline = status[api];
                        const statusClass = isOnline ? 'success' : 'error';
                        const statusText = isOnline ? 'Online' : 'Offline';
                        statusHtml += `<p><strong>${api}:</strong> <span class="status-${statusClass}">${statusText}</span></p>`;
                    });
                    
                    statusHtml += '</div>';
                    $('#api-status-results').html(statusHtml);
                } else {
                    AIComposerAdmin.showNotice('Failed to check API status', 'error');
                }
            });
        },

        /**
         * Refresh cost statistics
         */
        refreshCostStats: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            $button.prop('disabled', true).text('Refreshing...');
            
            this.ajaxRequest('refresh_cost_stats', {}, function(response) {
                $button.prop('disabled', false).text('Refresh Stats');
                
                if (response.success) {
                    // Update cost display elements
                    $('.daily-cost-amount').text('$' + response.data.daily_costs);
                    $('.monthly-cost-amount').text('$' + response.data.monthly_costs);
                    AIComposerAdmin.showNotice('Cost statistics updated', 'success');
                } else {
                    AIComposerAdmin.showNotice('Failed to refresh cost statistics', 'error');
                }
            });
        },

        /**
         * Check overall plugin status
         */
        checkPluginStatus: function() {
            // Check if we're on AI Composer admin pages
            if (!$('.ai-composer-admin').length) {
                return;
            }
            
            this.ajaxRequest('get_plugin_status', {}, function(response) {
                if (response.success) {
                    const status = response.data;
                    
                    // Show warnings if APIs not configured
                    if (!status.api_configured) {
                        AIComposerAdmin.showNotice(
                            'API keys are not configured. <a href="edit.php?page=ai-composer">Configure now</a>',
                            'warning'
                        );
                    }
                    
                    // Update status indicators
                    $('.api-status-indicator').removeClass('status-online status-offline')
                        .addClass(status.api_configured ? 'status-online' : 'status-offline');
                }
            });
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Simple tooltip implementation
            $('.ai-composer-tooltip').each(function() {
                const $element = $(this);
                const title = $element.attr('title') || $element.data('tooltip');
                
                if (title) {
                    $element.removeAttr('title').on('mouseenter', function() {
                        const $tooltip = $(`<div class="ai-composer-tooltip-content">${title}</div>`);
                        $('body').append($tooltip);
                        
                        const pos = $element.offset();
                        $tooltip.css({
                            position: 'absolute',
                            top: pos.top - $tooltip.outerHeight() - 8,
                            left: pos.left + ($element.outerWidth() / 2) - ($tooltip.outerWidth() / 2),
                            zIndex: 9999
                        });
                    }).on('mouseleave', function() {
                        $('.ai-composer-tooltip-content').remove();
                    });
                }
            });
        },

        /**
         * Dismiss admin notice
         */
        dismissNotice: function(e) {
            const $notice = $(e.target).closest('.notice');
            const noticeId = $notice.data('notice-id');
            
            if (noticeId) {
                // Store dismissed notice to prevent showing again
                this.ajaxRequest('dismiss_notice', {
                    notice_id: noticeId
                });
            }
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type = 'info', dismissible = true) {
            const dismissClass = dismissible ? ' is-dismissible' : '';
            const $notice = $(`
                <div class="notice notice-${type}${dismissClass}">
                    <p>${message}</p>
                    ${dismissible ? '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' : ''}
                </div>
            `);
            
            // Insert notice at the top of the page
            if ($('.wrap h1').length) {
                $('.wrap h1').after($notice);
            } else {
                $('#wpbody-content').prepend($notice);
            }
            
            // Handle dismiss button
            if (dismissible) {
                $notice.on('click', '.notice-dismiss', function() {
                    $notice.fadeOut(300, function() {
                        $(this).remove();
                    });
                });
            }
            
            // Auto-dismiss success messages
            if (type === 'success') {
                setTimeout(() => {
                    $notice.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        },

        /**
         * AJAX helper for admin requests
         */
        ajaxRequest: function(action, data, callback) {
            // Check if required globals are available
            const ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : 
                           (typeof aiComposerAdmin !== 'undefined' && aiComposerAdmin.ajaxUrl) ? aiComposerAdmin.ajaxUrl : 
                           (typeof aiComposerAdminBase !== 'undefined' && aiComposerAdminBase.ajaxUrl) ? aiComposerAdminBase.ajaxUrl :
                           '/wp-admin/admin-ajax.php';
            
            const nonce = (typeof aiComposerAdmin !== 'undefined' && aiComposerAdmin.nonce) ? aiComposerAdmin.nonce :
                         (typeof aiComposerAdminBase !== 'undefined' && aiComposerAdminBase.nonce) ? aiComposerAdminBase.nonce : '';
            
            if (!nonce) {
                console.error('AI Composer: No nonce available for AJAX request');
                this.showNotice('Security error: Please refresh the page and try again.', 'error');
                return;
            }
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_composer_' + action,
                    nonce: nonce,
                    ...data
                },
                success: function(response) {
                    if (typeof callback === 'function') {
                        callback(response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AI Composer Admin AJAX Error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        url: ajaxUrl,
                        action: 'ai_composer_' + action,
                        data: data
                    });
                    
                    let errorMessage = 'An error occurred. Please try again.';
                    
                    // Try to parse the response for more specific error
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.data && response.data.message) {
                            errorMessage = response.data.message;
                        } else if (response && response.message) {
                            errorMessage = response.message;
                        }
                    } catch (parseError) {
                        // Ignore parsing errors, use status-based messages
                        if (xhr.status === 0) {
                            errorMessage = 'Network error. Please check your internet connection.';
                        } else if (xhr.status === 403) {
                            errorMessage = 'Security error. Please refresh the page and try again.';
                        } else if (xhr.status === 404) {
                            errorMessage = 'AJAX endpoint not found. Please check plugin configuration.';
                        } else if (xhr.status === 500) {
                            errorMessage = 'Server error. Check the browser console and server logs for details.';
                        }
                    }
                    
                    AIComposerAdmin.showNotice(errorMessage, 'error');
                    
                    if (typeof callback === 'function') {
                        callback({ success: false, error: error, status: xhr.status, responseText: xhr.responseText });
                    }
                }
            });
        },

        /**
         * Utility: Format currency
         */
        formatCurrency: function(amount) {
            return '$' + parseFloat(amount).toFixed(2);
        },

        /**
         * Utility: Format number with commas
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
    };

    // Initialize admin functionality
    AIComposerAdmin.init();

    // Make it globally available
    window.AIComposerAdmin = AIComposerAdmin;

})(jQuery);