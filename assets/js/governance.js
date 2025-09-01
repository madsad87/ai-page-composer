/**
 * AI Composer Governance JavaScript
 * 
 * Handles interaction for the governance system including diff viewing,
 * re-run functionality, and run management.
 */

(function($) {
    'use strict';

    // Governance system object
    const AIComposerGovernance = {
        
        /**
         * Initialize governance functionality
         */
        init: function() {
            this.bindEvents();
            this.setupModals();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Diff functionality
            $(document).on('click', '.diff-run', this.handleDiffClick.bind(this));
            
            // Re-run functionality
            $(document).on('click', '.rerun-generation', this.handleRerunClick.bind(this));
            $(document).on('click', '#preview-rerun', this.handlePreviewRerun.bind(this));
            $(document).on('submit', '#rerun-form', this.handleExecuteRerun.bind(this));
            
            // Delete functionality
            $(document).on('click', '.delete-run', this.handleDeleteClick.bind(this));
            
            // Modal controls
            $(document).on('click', '.modal-close', this.closeModal.bind(this));
            $(document).on('click', '.ai-composer-modal', function(e) {
                if (e.target === this) {
                    AIComposerGovernance.closeModal();
                }
            });
            
            // Keyboard controls
            $(document).on('keydown', this.handleKeydown.bind(this));
        },

        /**
         * Setup modal functionality
         */
        setupModals: function() {
            // Ensure modals are properly positioned
            $('.ai-composer-modal').appendTo('body');
        },

        /**
         * Handle diff button click
         */
        handleDiffClick: function(e) {
            e.preventDefault();
            
            const runId = $(e.target).data('run-id');
            if (!runId) return;
            
            this.showModal('#diff-modal');
            this.loadDiff(runId);
        },

        /**
         * Handle re-run button click
         */
        handleRerunClick: function(e) {
            e.preventDefault();
            
            const runId = $(e.target).data('run-id');
            if (!runId) return;
            
            // Store run ID for later use
            $('#rerun-form').data('run-id', runId);
            
            this.showModal('#rerun-modal');
            this.resetRerunForm();
        },

        /**
         * Handle delete button click
         */
        handleDeleteClick: function(e) {
            e.preventDefault();
            
            const runId = $(e.target).data('run-id');
            if (!runId) return;
            
            if (!confirm(aiComposerGovernance.strings.confirmDelete)) {
                return;
            }
            
            this.deleteRun(runId);
        },

        /**
         * Handle preview re-run
         */
        handlePreviewRerun: function(e) {
            e.preventDefault();
            
            const runId = $('#rerun-form').data('run-id');
            const options = this.getRerunOptions();
            
            this.previewRerun(runId, options);
        },

        /**
         * Handle execute re-run
         */
        handleExecuteRerun: function(e) {
            e.preventDefault();
            
            const runId = $('#rerun-form').data('run-id');
            const options = this.getRerunOptions();
            
            this.executeRerun(runId, options);
        },

        /**
         * Handle keyboard events
         */
        handleKeydown: function(e) {
            if (e.key === 'Escape') {
                this.closeModal();
            }
        },

        /**
         * Show modal
         */
        showModal: function(modalId) {
            $(modalId).show();
            $('body').addClass('modal-open');
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.ai-composer-modal').hide();
            $('body').removeClass('modal-open');
        },

        /**
         * Load diff data
         */
        loadDiff: function(runId) {
            $('#diff-content').html('<p>' + aiComposerGovernance.strings.processing + '</p>');
            
            $.ajax({
                url: aiComposerGovernance.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_composer_governance_action',
                    governance_action: 'generate_diff',
                    run_id: runId,
                    compare_to: 'current',
                    nonce: aiComposerGovernance.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AIComposerGovernance.renderDiff(response.data);
                    } else {
                        $('#diff-content').html('<div class="notice notice-error"><p>' + 
                            (response.data.message || aiComposerGovernance.strings.error) + '</p></div>');
                    }
                },
                error: function() {
                    $('#diff-content').html('<div class="notice notice-error"><p>' + 
                        aiComposerGovernance.strings.error + '</p></div>');
                }
            });
        },

        /**
         * Render diff data
         */
        renderDiff: function(diffData) {
            let html = '<div class="diff-viewer">';
            
            // Summary
            if (diffData.visualization_data) {
                const summary = diffData.visualization_data.change_summary;
                html += '<div class="diff-summary">';
                html += '<h3>Change Summary</h3>';
                html += '<div class="summary-stats">';
                html += '<span class="stat">Total Changes: <strong>' + summary.total_changes + '</strong></span>';
                html += '<span class="stat">Significant Changes: <strong>' + summary.significant_changes + '</strong></span>';
                html += '<span class="stat">Plugin Changes: <strong>' + summary.plugin_changes + '</strong></span>';
                html += '</div>';
                
                if (diffData.visualization_data.recommendation) {
                    html += '<div class="diff-recommendation">';
                    html += '<strong>Recommendation:</strong> ' + diffData.visualization_data.recommendation;
                    html += '</div>';
                }
                html += '</div>';
            }
            
            // Parameter changes
            if (diffData.parameter_changes && Object.keys(diffData.parameter_changes).length > 0) {
                html += '<div class="diff-section">';
                html += '<h3>Parameter Changes</h3>';
                html += '<table class="widefat">';
                html += '<thead><tr><th>Parameter</th><th>From</th><th>To</th></tr></thead>';
                html += '<tbody>';
                
                Object.keys(diffData.parameter_changes).forEach(function(param) {
                    const change = diffData.parameter_changes[param];
                    if (typeof change === 'object' && change.from !== undefined) {
                        html += '<tr>';
                        html += '<td>' + param + '</td>';
                        html += '<td>' + AIComposerGovernance.formatValue(change.from) + '</td>';
                        html += '<td>' + AIComposerGovernance.formatValue(change.to) + '</td>';
                        html += '</tr>';
                    }
                });
                
                html += '</tbody></table>';
                html += '</div>';
            }
            
            // Section changes
            if (diffData.section_diffs && diffData.section_diffs.length > 0) {
                html += '<div class="diff-section">';
                html += '<h3>Section Changes</h3>';
                
                diffData.section_diffs.forEach(function(section) {
                    if (Object.keys(section.changes).length > 0) {
                        html += '<div class="section-diff">';
                        html += '<h4>Section: ' + section.section_id + '</h4>';
                        
                        Object.keys(section.changes).forEach(function(changeType) {
                            const change = section.changes[changeType];
                            html += '<div class="change-item">';
                            html += '<strong>' + changeType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + ':</strong> ';
                            
                            if (typeof change === 'object' && change.from !== undefined) {
                                html += AIComposerGovernance.formatValue(change.from) + ' → ' + AIComposerGovernance.formatValue(change.to);
                                if (change.reason) {
                                    html += ' (' + change.reason + ')';
                                }
                            } else {
                                html += AIComposerGovernance.formatValue(change);
                            }
                            
                            html += '</div>';
                        });
                        
                        html += '</div>';
                    }
                });
                
                html += '</div>';
            }
            
            // Plugin changes
            if (diffData.plugin_availability_changes && Object.keys(diffData.plugin_availability_changes).length > 0) {
                html += '<div class="diff-section">';
                html += '<h3>Plugin Changes</h3>';
                
                Object.keys(diffData.plugin_availability_changes).forEach(function(plugin) {
                    const changes = diffData.plugin_availability_changes[plugin];
                    html += '<div class="plugin-diff">';
                    html += '<h4>' + plugin + '</h4>';
                    
                    Object.keys(changes.changes).forEach(function(changeType) {
                        const change = changes.changes[changeType];
                        html += '<div class="change-item">';
                        html += '<strong>' + changeType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + ':</strong> ';
                        html += AIComposerGovernance.formatValue(change);
                        html += '</div>';
                    });
                    
                    html += '</div>';
                });
                
                html += '</div>';
            }
            
            html += '</div>';
            
            $('#diff-content').html(html);
        },

        /**
         * Preview re-run
         */
        previewRerun: function(runId, options) {
            $('#preview-content').html('<p>' + aiComposerGovernance.strings.processing + '</p>');
            $('#rerun-preview').show();
            
            $.ajax({
                url: aiComposerGovernance.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_composer_governance_action',
                    governance_action: 'preview_rerun',
                    run_id: runId,
                    rerun_options: options,
                    nonce: aiComposerGovernance.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AIComposerGovernance.renderPreview(response.data);
                    } else {
                        $('#preview-content').html('<div class="notice notice-error"><p>' + 
                            (response.data.message || aiComposerGovernance.strings.error) + '</p></div>');
                    }
                },
                error: function() {
                    $('#preview-content').html('<div class="notice notice-error"><p>' + 
                        aiComposerGovernance.strings.error + '</p></div>');
                }
            });
        },

        /**
         * Execute re-run
         */
        executeRerun: function(runId, options) {
            const $form = $('#rerun-form');
            const $button = $form.find('button[type="submit"]');
            const originalText = $button.text();
            
            $button.prop('disabled', true).text(aiComposerGovernance.strings.processing);
            
            $.ajax({
                url: aiComposerGovernance.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_composer_governance_action',
                    governance_action: 'execute_rerun',
                    run_id: runId,
                    rerun_options: options,
                    nonce: aiComposerGovernance.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Re-run completed successfully! New run ID: ' + response.data.rerun_result.new_run_id);
                        AIComposerGovernance.closeModal();
                        location.reload(); // Refresh to show new run
                    } else {
                        alert('Re-run failed: ' + (response.data.message || aiComposerGovernance.strings.error));
                    }
                },
                error: function() {
                    alert('Re-run failed: ' + aiComposerGovernance.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Delete run
         */
        deleteRun: function(runId) {
            $.ajax({
                url: aiComposerGovernance.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_composer_governance_action',
                    governance_action: 'delete_run',
                    run_id: runId,
                    nonce: aiComposerGovernance.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Run deleted successfully');
                        location.reload(); // Refresh to remove deleted run
                    } else {
                        alert('Delete failed: ' + (response.data.message || aiComposerGovernance.strings.error));
                    }
                },
                error: function() {
                    alert('Delete failed: ' + aiComposerGovernance.strings.error);
                }
            });
        },

        /**
         * Get re-run options from form
         */
        getRerunOptions: function() {
            const $form = $('#rerun-form');
            return {
                preserve_plugin_preferences: $form.find('[name="preserve_plugin_preferences"]').is(':checked'),
                fallback_on_missing_plugins: $form.find('[name="fallback_on_missing_plugins"]').is(':checked'),
                update_namespace_versions: $form.find('[name="update_namespace_versions"]').is(':checked')
            };
        },

        /**
         * Reset re-run form
         */
        resetRerunForm: function() {
            $('#rerun-preview').hide();
            $('#preview-content').empty();
        },

        /**
         * Render preview data
         */
        renderPreview: function(previewData) {
            let html = '<div class="rerun-preview">';
            
            if (previewData.can_proceed) {
                html += '<div class="notice notice-success"><p>Re-run is ready to proceed</p></div>';
            } else {
                html += '<div class="notice notice-error"><p>Re-run cannot proceed</p></div>';
            }
            
            // Parameter adaptations
            if (previewData.parameter_adaptations && previewData.parameter_adaptations.length > 0) {
                html += '<h4>Parameter Adaptations</h4>';
                html += '<ul>';
                previewData.parameter_adaptations.forEach(function(adaptation) {
                    html += '<li><strong>' + adaptation.parameter + ':</strong> ' + 
                           adaptation.original + ' → ' + adaptation.adapted + 
                           ' (' + adaptation.reason + ')</li>';
                });
                html += '</ul>';
            }
            
            // Plugin fallbacks
            if (previewData.plugin_fallbacks && Object.keys(previewData.plugin_fallbacks).length > 0) {
                html += '<h4>Plugin Fallbacks</h4>';
                html += '<ul>';
                Object.keys(previewData.plugin_fallbacks).forEach(function(original) {
                    const fallback = previewData.plugin_fallbacks[original];
                    html += '<li>' + original + ' → ' + fallback + '</li>';
                });
                html += '</ul>';
            }
            
            // Warnings
            if (previewData.warnings && previewData.warnings.length > 0) {
                html += '<h4>Warnings</h4>';
                html += '<ul>';
                previewData.warnings.forEach(function(warning) {
                    html += '<li class="warning">' + warning + '</li>';
                });
                html += '</ul>';
            }
            
            // Estimated cost
            if (previewData.estimated_cost) {
                html += '<div class="estimated-cost">';
                html += '<strong>Estimated Cost:</strong> $' + parseFloat(previewData.estimated_cost).toFixed(4);
                html += '</div>';
            }
            
            html += '</div>';
            
            $('#preview-content').html(html);
        },

        /**
         * Format value for display
         */
        formatValue: function(value) {
            if (typeof value === 'object') {
                return JSON.stringify(value);
            }
            if (typeof value === 'boolean') {
                return value ? 'Yes' : 'No';
            }
            return String(value);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        AIComposerGovernance.init();
    });

})(jQuery);