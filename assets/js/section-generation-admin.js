/**
 * Section Generation Admin JavaScript
 * 
 * Handles admin interface interactions for section generation settings
 * and tools including testing, cache management, and statistics.
 */

(function($) {
    'use strict';

    /**
     * Section Generation Admin Handler
     */
    var SectionGenerationAdmin = {
        
        /**
         * Initialize the handler
         */
        init: function() {
            this.bindEvents();
            this.updateRangeInputs();
        },
        
        /**
         * Bind UI events
         */
        bindEvents: function() {
            // Range input updates
            $('.range-input').on('input', this.updateRangeValue);
            
            // Section generation tools
            $('#test-section-generation').on('click', this.testSectionGeneration);
            $('#clear-section-cache').on('click', this.clearSectionCache);
            $('#view-cache-stats').on('click', this.viewCacheStats);
            
            // Tab switching
            $('.nav-tab').on('click', this.switchTab);
        },
        
        /**
         * Update range input display values
         */
        updateRangeInputs: function() {
            $('.range-input').each(function() {
                var $input = $(this);
                var $value = $input.siblings('.range-value');
                $value.text($input.val());
            });
        },
        
        /**
         * Update range value display
         */
        updateRangeValue: function() {
            var $input = $(this);
            var $value = $input.siblings('.range-value');
            $value.text($input.val());
        },
        
        /**
         * Test section generation
         */
        testSectionGeneration: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $results = $('#section-generation-test-results');
            var $content = $('#test-results-content');
            
            $button.prop('disabled', true).text('Testing...');
            $results.show();
            $content.html('<div class="spinner is-active"></div><p>Running section generation test...</p>');
            
            // Test data
            var testData = {
                sectionId: 'test-section-' + Date.now(),
                content_brief: 'Generate a test hero section for a modern SaaS product',
                mode: $('#section_default_mode').val() || 'hybrid',
                alpha: parseFloat($('#section_alpha').val()) || 0.7,
                block_preferences: {
                    section_type: 'hero',
                    preferred_plugin: 'kadence_blocks'
                },
                image_requirements: {
                    policy: $('#image_policy').val() || 'optional',
                    style: $('#image_style').val() || 'photographic'
                }
            };
            
            $.ajax({
                url: ajaxurl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'ai_composer_test_section_generation',
                    nonce: aiComposerAdmin.nonce,
                    test_data: testData
                },
                success: function(response) {
                    if (response.success) {
                        SectionGenerationAdmin.displayTestResults(response.data, $content);
                    } else {
                        $content.html('<div class="notice notice-error"><p>Test failed: ' + (response.data || 'Unknown error') + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    $content.html('<div class="notice notice-error"><p>Request failed: ' + error + '</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Section Generation');
                }
            });
        },
        
        /**
         * Display test results
         */
        displayTestResults: function(data, $container) {
            var html = '<div class="notice notice-success"><p><strong>Test Successful!</strong></p></div>';
            
            html += '<table class="widefat striped">';
            html += '<tbody>';
            html += '<tr><td><strong>Section ID:</strong></td><td>' + (data.sectionId || 'N/A') + '</td></tr>';
            html += '<tr><td><strong>Block Type:</strong></td><td>' + (data.blockType ? data.blockType.name : 'N/A') + '</td></tr>';
            html += '<tr><td><strong>Plugin:</strong></td><td>' + (data.blockType ? data.blockType.plugin : 'N/A') + '</td></tr>';
            html += '<tr><td><strong>Citations:</strong></td><td>' + (data.citations ? data.citations.length : 0) + '</td></tr>';
            html += '<tr><td><strong>Media ID:</strong></td><td>' + (data.mediaId || 'None') + '</td></tr>';
            
            if (data.generation_metadata) {
                html += '<tr><td><strong>Generation Mode:</strong></td><td>' + data.generation_metadata.mode + '</td></tr>';
                html += '<tr><td><strong>Word Count:</strong></td><td>' + data.generation_metadata.word_count + '</td></tr>';
                html += '<tr><td><strong>Token Count:</strong></td><td>' + data.generation_metadata.token_count + '</td></tr>';
                html += '<tr><td><strong>Cost (USD):</strong></td><td>$' + parseFloat(data.generation_metadata.cost_usd).toFixed(4) + '</td></tr>';
                html += '<tr><td><strong>Processing Time:</strong></td><td>' + data.generation_metadata.processing_time_ms + 'ms</td></tr>';
                html += '<tr><td><strong>Cache Hit:</strong></td><td>' + (data.generation_metadata.cache_hit ? 'Yes' : 'No') + '</td></tr>';
            }
            
            html += '</tbody>';
            html += '</table>';
            
            if (data.content && data.content.html) {
                html += '<h4>Generated HTML Preview:</h4>';
                html += '<div style="border: 1px solid #ddd; padding: 10px; background: #f9f9f9; max-height: 200px; overflow-y: auto;">';
                html += '<pre><code>' + this.escapeHtml(data.content.html.substring(0, 500)) + (data.content.html.length > 500 ? '...' : '') + '</code></pre>';
                html += '</div>';
            }
            
            $container.html(html);
        },
        
        /**
         * Clear section cache
         */
        clearSectionCache: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            
            if (!confirm('Are you sure you want to clear the section generation cache? This will remove all cached results.')) {
                return;
            }
            
            $button.prop('disabled', true).text('Clearing...');
            
            $.ajax({
                url: ajaxurl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'ai_composer_clear_section_cache',
                    nonce: aiComposerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SectionGenerationAdmin.showNotice('Cache cleared successfully!', 'success');
                    } else {
                        SectionGenerationAdmin.showNotice('Failed to clear cache: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    SectionGenerationAdmin.showNotice('Request failed: ' + error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Clear Section Cache');
                }
            });
        },
        
        /**
         * View cache statistics
         */
        viewCacheStats: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            $button.prop('disabled', true).text('Loading...');
            
            $.ajax({
                url: ajaxurl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'ai_composer_get_cache_stats',
                    nonce: aiComposerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SectionGenerationAdmin.displayCacheStats(response.data);
                    } else {
                        SectionGenerationAdmin.showNotice('Failed to load cache stats: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    SectionGenerationAdmin.showNotice('Request failed: ' + error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('View Cache Statistics');
                }
            });
        },
        
        /**
         * Display cache statistics in modal
         */
        displayCacheStats: function(stats) {
            var html = '<div id="cache-stats-modal" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border: 1px solid #ddd; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); z-index: 10000; max-width: 600px; width: 90%;">';
            html += '<h3>Cache Statistics</h3>';
            html += '<table class="widefat striped">';
            html += '<tbody>';
            html += '<tr><td><strong>Status:</strong></td><td>' + (stats.status || 'Unknown') + '</td></tr>';
            html += '<tr><td><strong>Hit Rate:</strong></td><td>' + (stats.hit_rate || 0) + '%</td></tr>';
            html += '<tr><td><strong>Total Entries:</strong></td><td>' + (stats.total_entries || 0) + '</td></tr>';
            html += '<tr><td><strong>Cache Size:</strong></td><td>' + (stats.total_size_mb || 0) + ' MB</td></tr>';
            html += '<tr><td><strong>Memory Usage:</strong></td><td>' + (stats.memory_usage ? stats.memory_usage.current_mb + ' MB' : 'N/A') + '</td></tr>';
            html += '</tbody>';
            html += '</table>';
            
            if (stats.recommendations && stats.recommendations.length > 0) {
                html += '<h4>Recommendations:</h4>';
                html += '<ul>';
                stats.recommendations.forEach(function(rec) {
                    html += '<li>' + rec + '</li>';
                });
                html += '</ul>';
            }
            
            html += '<div style="text-align: right; margin-top: 15px;">';
            html += '<button type="button" class="button button-primary" onclick="jQuery(this).closest(\'#cache-stats-modal\').remove(); jQuery(\'#cache-stats-overlay\').remove();">Close</button>';
            html += '</div>';
            html += '</div>';
            html += '<div id="cache-stats-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;" onclick="jQuery(this).remove(); jQuery(\'#cache-stats-modal\').remove();"></div>';
            
            $('body').append(html);
        },
        
        /**
         * Switch between tabs
         */
        switchTab: function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var target = $tab.data('target');
            
            // Update tab states
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Update panel states
            $('.ai-composer-panel').removeClass('active');
            $(target).addClass('active');
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.ai-composer-settings h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        },
        
        /**
         * Escape HTML for safe display
         */
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        SectionGenerationAdmin.init();
    });
    
})(jQuery);