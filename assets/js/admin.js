/*!
 * Admin Interface JavaScript - WordPress Admin Enhancement
 * 
 * This file provides interactive functionality for WordPress admin pages including form validation,
 * settings management, media uploaders, and admin-specific user interface enhancements. It integrates
 * with WordPress admin APIs and follows admin JavaScript best practices for optimal user experience.
 * 
 * Modern WP Plugin Admin JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Admin functionality object
     */
    const ModernWPPluginAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initTooltips();
            this.initFormValidation();
            console.log('Modern WP Plugin Admin initialized');
        },

        /**
         * Bind admin events
         */
        bindEvents: function() {
            $(document).ready(this.onReady.bind(this));
            
            // Settings form handling
            $('#modern-wp-plugin-settings-form').on('submit', this.validateForm.bind(this));
            
            // Tab navigation
            $('.modern-wp-plugin-tab').on('click', this.switchTab.bind(this));
            
            // Reset to defaults
            $('.modern-wp-plugin-reset').on('click', this.resetToDefaults.bind(this));
            
            // Import/Export functionality
            $('.modern-wp-plugin-export').on('click', this.exportSettings.bind(this));
            $('.modern-wp-plugin-import').on('change', this.importSettings.bind(this));
        },

        /**
         * Document ready handler for admin
         */
        onReady: function() {
            this.initMetaBoxes();
            this.initColorPickers();
            this.initMediaUploaders();
            this.checkDependencies();
        },

        /**
         * Initialize meta boxes
         */
        initMetaBoxes: function() {
            // Make meta boxes sortable
            if (typeof postboxes !== 'undefined') {
                postboxes.add_postbox_toggles('modern-wp-plugin');
            }
            
            // Add custom meta box functionality
            $('.modern-wp-plugin-meta-box').each(function() {
                const $metaBox = $(this);
                const $toggle = $metaBox.find('.handlediv');
                
                $toggle.on('click', function() {
                    $metaBox.find('.inside').slideToggle();
                });
            });
        },

        /**
         * Initialize color pickers
         */
        initColorPickers: function() {
            if ($.fn.wpColorPicker) {
                $('.modern-wp-plugin-color-picker').wpColorPicker({
                    change: function(event, ui) {
                        ModernWPPluginAdmin.previewColorChange($(this), ui.color.toString());
                    }
                });
            }
        },

        /**
         * Initialize media uploaders
         */
        initMediaUploaders: function() {
            $('.modern-wp-plugin-upload-button').on('click', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const $input = $button.siblings('input[type="text"]');
                const $preview = $button.siblings('.modern-wp-plugin-preview');
                
                const mediaUploader = wp.media({
                    title: 'Select Image',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    $input.val(attachment.url);
                    $preview.html('<img src="' + attachment.url + '" style="max-width: 200px;" />');
                });
                
                mediaUploader.open();
            });
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('.modern-wp-plugin-tooltip').each(function() {
                const $tooltip = $(this);
                const title = $tooltip.attr('title');
                
                $tooltip.removeAttr('title').on('mouseenter', function() {
                    const $tip = $('<div class="modern-wp-plugin-tip">' + title + '</div>');
                    $('body').append($tip);
                    
                    const pos = $tooltip.offset();
                    $tip.css({
                        top: pos.top - $tip.outerHeight() - 5,
                        left: pos.left + ($tooltip.outerWidth() / 2) - ($tip.outerWidth() / 2)
                    });
                }).on('mouseleave', function() {
                    $('.modern-wp-plugin-tip').remove();
                });
            });
        },

        /**
         * Initialize form validation
         */
        initFormValidation: function() {
            // Real-time validation
            $('input[data-validate]').on('blur', function() {
                ModernWPPluginAdmin.validateField($(this));
            });
            
            // Number field constraints
            $('input[type="number"]').on('input', function() {
                const $input = $(this);
                const min = parseInt($input.attr('min'));
                const max = parseInt($input.attr('max'));
                const val = parseInt($input.val());
                
                if (!isNaN(min) && val < min) {
                    $input.val(min);
                }
                if (!isNaN(max) && val > max) {
                    $input.val(max);
                }
            });
        },

        /**
         * Validate individual field
         */
        validateField: function($field) {
            const validateType = $field.data('validate');
            const value = $field.val();
            let isValid = true;
            let message = '';
            
            switch (validateType) {
                case 'email':
                    isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
                    message = 'Please enter a valid email address';
                    break;
                case 'url':
                    isValid = /^https?:\/\/.+/.test(value) || value === '';
                    message = 'Please enter a valid URL';
                    break;
                case 'required':
                    isValid = value.trim() !== '';
                    message = 'This field is required';
                    break;
            }
            
            this.showFieldValidation($field, isValid, message);
            return isValid;
        },

        /**
         * Show field validation result
         */
        showFieldValidation: function($field, isValid, message) {
            $field.removeClass('field-valid field-invalid');
            $field.siblings('.field-validation').remove();
            
            if (isValid) {
                $field.addClass('field-valid');
            } else {
                $field.addClass('field-invalid');
                $field.after('<span class="field-validation field-error">' + message + '</span>');
            }
        },

        /**
         * Validate entire form
         */
        validateForm: function(e) {
            let isValid = true;
            
            $('input[data-validate]').each(function() {
                if (!ModernWPPluginAdmin.validateField($(this))) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                this.showNotice('Please correct the errors below', 'error');
                return false;
            }
            
            return true;
        },

        /**
         * Switch between tabs
         */
        switchTab: function(e) {
            e.preventDefault();
            
            const $tab = $(e.currentTarget);
            const target = $tab.data('target');
            
            // Update tab navigation
            $('.modern-wp-plugin-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show/hide tab content
            $('.modern-wp-plugin-tab-content').hide();
            $(target).show();
            
            // Save active tab
            localStorage.setItem('modern-wp-plugin-active-tab', target);
        },

        /**
         * Reset settings to defaults
         */
        resetToDefaults: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to reset all settings to defaults? This cannot be undone.')) {
                return;
            }
            
            this.ajaxRequest('reset_to_defaults', {}, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    ModernWPPluginAdmin.showNotice('Failed to reset settings', 'error');
                }
            });
        },

        /**
         * Export settings
         */
        exportSettings: function(e) {
            e.preventDefault();
            
            this.ajaxRequest('export_settings', {}, function(response) {
                if (response.success) {
                    const dataStr = JSON.stringify(response.data, null, 2);
                    const dataUri = 'data:application/json;charset=utf-8,' + encodeURIComponent(dataStr);
                    
                    const exportFileDefaultName = 'modern-wp-plugin-settings.json';
                    
                    const linkElement = document.createElement('a');
                    linkElement.setAttribute('href', dataUri);
                    linkElement.setAttribute('download', exportFileDefaultName);
                    linkElement.click();
                } else {
                    ModernWPPluginAdmin.showNotice('Failed to export settings', 'error');
                }
            });
        },

        /**
         * Import settings
         */
        importSettings: function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const settings = JSON.parse(e.target.result);
                    
                    ModernWPPluginAdmin.ajaxRequest('import_settings', {
                        settings: settings
                    }, function(response) {
                        if (response.success) {
                            ModernWPPluginAdmin.showNotice('Settings imported successfully', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            ModernWPPluginAdmin.showNotice('Failed to import settings', 'error');
                        }
                    });
                } catch (error) {
                    ModernWPPluginAdmin.showNotice('Invalid settings file', 'error');
                }
            };
            reader.readAsText(file);
        },

        /**
         * Preview color changes
         */
        previewColorChange: function($input, color) {
            const target = $input.data('preview-target');
            if (target) {
                $(target).css('color', color);
            }
        },

        /**
         * Check plugin dependencies
         */
        checkDependencies: function() {
            // Check if ACF is active
            this.ajaxRequest('check_dependencies', {}, function(response) {
                if (response.data && response.data.missing_dependencies) {
                    const dependencies = response.data.missing_dependencies;
                    let message = 'Missing dependencies: ' + dependencies.join(', ');
                    ModernWPPluginAdmin.showNotice(message, 'warning');
                }
            });
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type = 'info') {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        },

        /**
         * AJAX helper for admin
         */
        ajaxRequest: function(action, data, callback) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'modern_wp_plugin_' + action,
                    nonce: ModernWPPluginAdmin.nonce,
                    ...data
                },
                success: function(response) {
                    if (typeof callback === 'function') {
                        callback(response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Admin AJAX Error:', error);
                    ModernWPPluginAdmin.showNotice('An error occurred. Please try again.', 'error');
                }
            });
        }
    };

    // Initialize admin functionality
    ModernWPPluginAdmin.init();

    // Make it globally available
    window.ModernWPPluginAdmin = ModernWPPluginAdmin;

})(jQuery);