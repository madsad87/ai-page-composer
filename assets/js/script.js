/*!
 * Main Frontend JavaScript - Interactive Component Functionality
 * 
 * This file provides interactive functionality for plugin components including block interactions,
 * event handling, AJAX requests, and user interface enhancements. It follows modern JavaScript
 * patterns with organized object structure and proper event management for optimal performance.
 * 
 * Modern WP Plugin JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Plugin main object
     */
    const ModernWPPlugin = {
        
        /**
         * Initialize the plugin
         */
        init: function() {
            this.bindEvents();
            this.initBlocks();
            console.log('Modern WP Plugin initialized');
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            $(document).ready(this.onReady.bind(this));
            $(window).on('load', this.onLoad.bind(this));
            $(window).on('resize', this.onResize.bind(this));
        },

        /**
         * Document ready handler
         */
        onReady: function() {
            this.initSampleBlocks();
            this.initTestimonialBlocks();
        },

        /**
         * Window load handler
         */
        onLoad: function() {
            // Actions to perform after page is fully loaded
        },

        /**
         * Window resize handler
         */
        onResize: function() {
            // Debounce resize events
            clearTimeout(this.resizeTimer);
            this.resizeTimer = setTimeout(this.handleResize.bind(this), 250);
        },

        /**
         * Handle resize events
         */
        handleResize: function() {
            // Resize handling logic
            this.updateBlockDimensions();
        },

        /**
         * Initialize all blocks
         */
        initBlocks: function() {
            this.initSampleBlocks();
            this.initTestimonialBlocks();
        },

        /**
         * Initialize sample blocks
         */
        initSampleBlocks: function() {
            $('.sample-block').each(function() {
                const $block = $(this);
                const $button = $block.find('.sample-block__button');
                
                // Add click tracking
                $button.on('click', function(e) {
                    ModernWPPlugin.trackButtonClick($(this));
                });

                // Add hover effects
                $block.on('mouseenter', function() {
                    $(this).addClass('sample-block--hover');
                }).on('mouseleave', function() {
                    $(this).removeClass('sample-block--hover');
                });
            });
        },

        /**
         * Initialize testimonial blocks
         */
        initTestimonialBlocks: function() {
            $('.testimonial-block').each(function() {
                const $block = $(this);
                const $stars = $block.find('.testimonial-block__star');
                
                // Animate stars on scroll
                if (this.isIntersectionObserverSupported()) {
                    this.observeTestimonial($block[0]);
                }
                
                // Add click to copy functionality
                $block.on('click', '.testimonial-block__quote', function() {
                    ModernWPPlugin.copyToClipboard($(this).text());
                });
            }.bind(this));
        },

        /**
         * Check if Intersection Observer is supported
         */
        isIntersectionObserverSupported: function() {
            return 'IntersectionObserver' in window;
        },

        /**
         * Observe testimonial for animation
         */
        observeTestimonial: function(element) {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        ModernWPPlugin.animateStars($(entry.target));
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.5
            });
            
            observer.observe(element);
        },

        /**
         * Animate testimonial stars
         */
        animateStars: function($testimonial) {
            const $stars = $testimonial.find('.testimonial-block__star.filled');
            
            $stars.each(function(index) {
                setTimeout(function() {
                    $(this).addClass('star-animate');
                }.bind(this), index * 100);
            });
        },

        /**
         * Track button clicks
         */
        trackButtonClick: function($button) {
            const buttonText = $button.text();
            const blockId = $button.closest('[id]').attr('id');
            
            // Send analytics event if available
            if (typeof gtag !== 'undefined') {
                gtag('event', 'click', {
                    event_category: 'Modern WP Plugin',
                    event_label: buttonText,
                    custom_parameter: blockId
                });
            }
            
            console.log('Button clicked:', buttonText, 'Block:', blockId);
        },

        /**
         * Copy text to clipboard
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    ModernWPPlugin.showNotice('Quote copied to clipboard!');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    ModernWPPlugin.showNotice('Quote copied to clipboard!');
                } catch (err) {
                    console.error('Failed to copy text: ', err);
                }
                document.body.removeChild(textArea);
            }
        },

        /**
         * Show notification
         */
        showNotice: function(message, type = 'success') {
            const $notice = $('<div class="modern-wp-plugin-notice modern-wp-plugin-notice--' + type + '">' + message + '</div>');
            
            $('body').append($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Update block dimensions on resize
         */
        updateBlockDimensions: function() {
            $('.sample-block, .testimonial-block').each(function() {
                const $block = $(this);
                const width = $block.width();
                
                // Add responsive classes based on width
                $block.removeClass('block-small block-medium block-large');
                
                if (width < 400) {
                    $block.addClass('block-small');
                } else if (width < 800) {
                    $block.addClass('block-medium');
                } else {
                    $block.addClass('block-large');
                }
            });
        },

        /**
         * AJAX helper function
         */
        ajaxRequest: function(action, data, callback) {
            $.ajax({
                url: ModernWPPlugin.ajaxUrl,
                type: 'POST',
                data: {
                    action: action,
                    nonce: ModernWPPlugin.nonce,
                    ...data
                },
                success: function(response) {
                    if (typeof callback === 'function') {
                        callback(response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                }
            });
        }
    };

    // Initialize the plugin
    ModernWPPlugin.init();

    // Make it globally available
    window.ModernWPPlugin = ModernWPPlugin;

})(jQuery);