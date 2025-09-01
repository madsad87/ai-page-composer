<?php
/**
 * Block Preferences Class - Plugin Detection and Block Management
 * 
 * This file handles the detection of installed block plugins, manages priority
 * scoring for different plugins, and provides section mapping functionality
 * for the AI Page Composer plugin.
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\Admin;

use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Block Preferences class for plugin detection and management
 */
class Block_Preferences {

    /**
     * Priority weights for scoring system
     */
    private $priority_weights = array(
        'user_preference' => 10,    // Highest priority
        'plugin_active' => 8,       // Plugin is active
        'block_availability' => 6,  // Block is registered
        'feature_support' => 4,     // Supports required features
        'plugin_rating' => 2,       // General plugin priority
        'core_fallback' => 1,       // Core blocks as fallback
    );

    /**
     * Known block plugins and their configurations
     */
    private $known_plugins = array(
        'genesis_blocks' => array(
            'name' => 'Genesis Blocks',
            'file' => 'genesis-blocks/genesis-blocks.php',
            'namespace' => 'genesis-blocks',
            'supported_sections' => array( 'hero', 'testimonial', 'pricing', 'team', 'cta' ),
            'default_priority' => 8,
        ),
        'kadence_blocks' => array(
            'name' => 'Kadence Blocks',
            'file' => 'kadence-blocks/kadence-blocks.php',
            'namespace' => 'kadence',
            'supported_sections' => array( 'hero', 'content', 'testimonial', 'tabs', 'accordion', 'pricing' ),
            'default_priority' => 8,
        ),
        'stackable' => array(
            'name' => 'Stackable',
            'file' => 'stackable-ultimate-gutenberg-blocks/stackable.php',
            'namespace' => 'ugb',
            'supported_sections' => array( 'hero', 'feature', 'team', 'testimonial' ),
            'default_priority' => 7,
        ),
        'ultimate_addons' => array(
            'name' => 'Ultimate Addons for Gutenberg',
            'file' => 'ultimate-addons-for-gutenberg/ultimate-addons-for-gutenberg.php',
            'namespace' => 'uagb',
            'supported_sections' => array( 'hero', 'content', 'testimonial', 'team', 'pricing' ),
            'default_priority' => 7,
        ),
        'blocksy' => array(
            'name' => 'Blocksy Companion',
            'file' => 'blocksy-companion/blocksy-companion.php',
            'namespace' => 'blocksy',
            'supported_sections' => array( 'hero', 'content', 'team' ),
            'default_priority' => 6,
        ),
    );

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'init' ) );
        add_action( 'wp_ajax_ai_composer_refresh_plugins', array( $this, 'ajax_refresh_plugins' ) );
    }

    /**
     * Initialize block preferences
     */
    public function init() {
        // Scan plugins on admin load if needed
        if ( is_admin() && ! get_transient( 'ai_composer_plugins_scanned' ) ) {
            $this->scan_active_plugins();
            set_transient( 'ai_composer_plugins_scanned', true, HOUR_IN_SECONDS );
        }
    }

    /**
     * Get all detected block plugins
     *
     * @return array Array of detected plugins with metadata
     */
    public function get_detected_plugins() {
        $cached = get_transient( 'ai_composer_detected_plugins' );
        if ( false !== $cached ) {
            return $cached;
        }

        $detected = $this->scan_active_plugins();
        set_transient( 'ai_composer_detected_plugins', $detected, HOUR_IN_SECONDS );
        
        return $detected;
    }

    /**
     * Scan for active block plugins
     *
     * @return array Array of detected plugins
     */
    public function scan_active_plugins() {
        $detected_plugins = array();
        $active_plugins = get_option( 'active_plugins', array() );

        // Always include core blocks
        $detected_plugins['core'] = array(
            'active' => true,
            'name' => 'WordPress Core Blocks',
            'version' => get_bloginfo( 'version' ),
            'namespace' => 'core',
            'priority' => 5,
            'blocks_count' => $this->count_core_blocks(),
            'supported_sections' => array( 'hero', 'content', 'testimonial', 'pricing', 'team', 'faq', 'cta' ),
            'plugin_file' => 'wordpress-core',
        );

        // Check known plugins
        foreach ( $this->known_plugins as $key => $plugin_info ) {
            $is_active = in_array( $plugin_info['file'], $active_plugins, true );
            
            $detected_plugins[ $key ] = array(
                'active' => $is_active,
                'name' => $plugin_info['name'],
                'version' => $this->get_plugin_version( $plugin_info['file'] ),
                'namespace' => $plugin_info['namespace'],
                'priority' => $plugin_info['default_priority'],
                'blocks_count' => $is_active ? $this->count_plugin_blocks( $plugin_info['namespace'] ) : 0,
                'supported_sections' => $plugin_info['supported_sections'],
                'plugin_file' => $plugin_info['file'],
            );
        }

        // Scan for other block plugins
        $other_plugins = $this->detect_other_block_plugins( $active_plugins );
        $detected_plugins = array_merge( $detected_plugins, $other_plugins );

        return $detected_plugins;
    }

    /**
     * Detect other block plugins not in known list
     *
     * @param array $active_plugins List of active plugin files.
     * @return array Array of other detected block plugins
     */
    private function detect_other_block_plugins( $active_plugins ) {
        $other_plugins = array();
        $all_plugins = get_plugins();

        foreach ( $active_plugins as $plugin_file ) {
            if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
                continue;
            }

            $plugin_data = $all_plugins[ $plugin_file ];
            $plugin_slug = dirname( $plugin_file );

            // Skip known plugins
            if ( $this->is_known_plugin( $plugin_file ) ) {
                continue;
            }

            // Check if it's likely a block plugin
            if ( $this->is_likely_block_plugin( $plugin_data, $plugin_slug ) ) {
                $namespace = $this->guess_plugin_namespace( $plugin_slug );
                
                $other_plugins[ $plugin_slug ] = array(
                    'active' => true,
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'namespace' => $namespace,
                    'priority' => 4, // Default priority for unknown plugins
                    'blocks_count' => $this->count_plugin_blocks( $namespace ),
                    'supported_sections' => array( 'content' ), // Default to content section
                    'plugin_file' => $plugin_file,
                );
            }
        }

        return $other_plugins;
    }

    /**
     * Check if plugin is in known list
     *
     * @param string $plugin_file Plugin file path.
     * @return bool True if known plugin
     */
    private function is_known_plugin( $plugin_file ) {
        foreach ( $this->known_plugins as $plugin_info ) {
            if ( $plugin_info['file'] === $plugin_file ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if plugin is likely a block plugin
     *
     * @param array  $plugin_data Plugin metadata.
     * @param string $plugin_slug Plugin directory slug.
     * @return bool True if likely a block plugin
     */
    private function is_likely_block_plugin( $plugin_data, $plugin_slug ) {
        $indicators = array( 'block', 'gutenberg', 'editor', 'page builder', 'elementor' );
        
        $search_text = strtolower( $plugin_data['Name'] . ' ' . $plugin_data['Description'] );
        
        foreach ( $indicators as $indicator ) {
            if ( strpos( $search_text, $indicator ) !== false ) {
                return true;
            }
        }

        // Check if plugin registers blocks
        return $this->plugin_registers_blocks( $plugin_slug );
    }

    /**
     * Check if plugin registers blocks
     *
     * @param string $plugin_slug Plugin directory slug.
     * @return bool True if plugin registers blocks
     */
    private function plugin_registers_blocks( $plugin_slug ) {
        $registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();
        
        foreach ( $registered_blocks as $block_name => $block_type ) {
            if ( strpos( $block_name, $plugin_slug ) === 0 ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Guess plugin namespace from slug
     *
     * @param string $plugin_slug Plugin directory slug.
     * @return string Guessed namespace
     */
    private function guess_plugin_namespace( $plugin_slug ) {
        // Common patterns for namespace guessing
        $namespace_patterns = array(
            'blocks' => '',
            'gutenberg' => '',
            '-' => '/',
            '_' => '-',
        );

        $namespace = $plugin_slug;
        foreach ( $namespace_patterns as $pattern => $replacement ) {
            $namespace = str_replace( $pattern, $replacement, $namespace );
        }

        return sanitize_key( $namespace );
    }

    /**
     * Count core blocks
     *
     * @return int Number of core blocks
     */
    private function count_core_blocks() {
        $registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();
        $core_count = 0;

        foreach ( $registered_blocks as $block_name => $block_type ) {
            if ( strpos( $block_name, 'core/' ) === 0 ) {
                $core_count++;
            }
        }

        return $core_count;
    }

    /**
     * Count blocks for a specific plugin namespace
     *
     * @param string $namespace Plugin namespace.
     * @return int Number of blocks
     */
    private function count_plugin_blocks( $namespace ) {
        $registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();
        $count = 0;

        foreach ( $registered_blocks as $block_name => $block_type ) {
            if ( strpos( $block_name, $namespace . '/' ) === 0 ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get plugin version
     *
     * @param string $plugin_file Plugin file path.
     * @return string Plugin version
     */
    private function get_plugin_version( $plugin_file ) {
        if ( 'wordpress-core' === $plugin_file ) {
            return get_bloginfo( 'version' );
        }

        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        if ( file_exists( $plugin_path ) ) {
            $plugin_data = get_plugin_data( $plugin_path );
            return $plugin_data['Version'] ?? 'Unknown';
        }

        return 'Unknown';
    }

    /**
     * Calculate block priority for a section
     *
     * @param string $section_type Type of section.
     * @param string $plugin_key   Plugin key.
     * @return int Priority score
     */
    public function calculate_block_priority( $section_type, $plugin_key ) {
        $score = 0;
        $plugin = $this->get_detected_plugin( $plugin_key );
        
        if ( ! $plugin ) {
            return 0;
        }

        $user_pref = $this->get_user_preference( $section_type );

        // User explicit preference
        if ( $user_pref === $plugin_key ) {
            $score += $this->priority_weights['user_preference'];
        }

        // Plugin active status
        if ( $plugin['active'] ) {
            $score += $this->priority_weights['plugin_active'];
        }

        // Block availability for section
        if ( in_array( $section_type, $plugin['supported_sections'], true ) ) {
            $score += $this->priority_weights['block_availability'];
        }

        // Feature support (advanced styling, patterns, etc.)
        if ( $this->supports_advanced_features( $plugin_key, $section_type ) ) {
            $score += $this->priority_weights['feature_support'];
        }

        // Plugin priority setting
        $score += $plugin['priority'] ?? $this->priority_weights['plugin_rating'];

        return $score;
    }

    /**
     * Get detected plugin by key
     *
     * @param string $plugin_key Plugin key.
     * @return array|false Plugin data or false if not found
     */
    public function get_detected_plugin( $plugin_key ) {
        $detected = $this->get_detected_plugins();
        return $detected[ $plugin_key ] ?? false;
    }

    /**
     * Get user preference for section
     *
     * @param string $section_type Section type.
     * @return string User preferred plugin for section
     */
    private function get_user_preference( $section_type ) {
        $settings = get_option( 'ai_composer_settings', array() );
        $section_mappings = $settings['block_preferences']['section_mappings'] ?? array();
        
        return $section_mappings[ $section_type ] ?? 'auto';
    }

    /**
     * Check if plugin supports advanced features
     *
     * @param string $plugin_key   Plugin key.
     * @param string $section_type Section type.
     * @return bool True if supports advanced features
     */
    private function supports_advanced_features( $plugin_key, $section_type ) {
        // Advanced feature support matrix
        $advanced_features = array(
            'genesis_blocks' => array( 'hero', 'testimonial', 'pricing' ),
            'kadence_blocks' => array( 'hero', 'testimonial', 'tabs', 'accordion' ),
            'stackable' => array( 'hero', 'feature', 'team' ),
            'ultimate_addons' => array( 'hero', 'testimonial', 'team' ),
        );

        $supported_sections = $advanced_features[ $plugin_key ] ?? array();
        return in_array( $section_type, $supported_sections, true );
    }

    /**
     * Get best plugin for section type
     *
     * @param string $section_type Section type.
     * @return string Best plugin key for section
     */
    public function get_best_plugin_for_section( $section_type ) {
        $detected = $this->get_detected_plugins();
        $best_plugin = 'core';
        $best_score = 0;

        foreach ( $detected as $plugin_key => $plugin_data ) {
            if ( ! $plugin_data['active'] ) {
                continue;
            }

            $score = $this->calculate_block_priority( $section_type, $plugin_key );
            if ( $score > $best_score ) {
                $best_score = $score;
                $best_plugin = $plugin_key;
            }
        }

        return $best_plugin;
    }

    /**
     * Get section preference with detailed block information
     *
     * @param string $section_type Section type.
     * @return array|null Section preference data or null if not found.
     */
    public function get_section_preference( $section_type ) {
        $settings = get_option( 'ai_composer_settings', array() );
        $section_mappings = $settings['block_preferences']['section_mappings'] ?? array();
        
        // Get user preference or default to auto
        $preferred_plugin = $section_mappings[ $section_type ] ?? 'auto';
        
        // If auto, determine best plugin
        if ( 'auto' === $preferred_plugin ) {
            $preferred_plugin = $this->get_best_plugin_for_section( $section_type );
        }
        
        // Get plugin data
        $plugin_data = $this->get_detected_plugin( $preferred_plugin );
        if ( ! $plugin_data || ! $plugin_data['active'] ) {
            // Fallback to core if preferred plugin is not available
            $preferred_plugin = 'core';
            $plugin_data = $this->get_detected_plugin( 'core' );
        }
        
        if ( ! $plugin_data ) {
            return null;
        }
        
        // Build block preference structure
        $preference = array(
            'preferred_plugin' => $preferred_plugin,
            'primary_block' => $this->get_primary_block_for_section( $section_type, $preferred_plugin ),
            'fallback_blocks' => $this->get_fallback_blocks_for_section( $section_type, $preferred_plugin ),
            'pattern_preference' => $this->get_pattern_preference_for_section( $section_type, $preferred_plugin ),
        );
        
        return $preference;
    }

    /**
     * Get primary block for section type and plugin
     *
     * @param string $section_type Section type.
     * @param string $plugin_key Plugin key.
     * @return string Primary block name.
     */
    private function get_primary_block_for_section( $section_type, $plugin_key ) {
        // Define primary blocks for each plugin and section type
        $primary_blocks = array(
            'genesis_blocks' => array(
                'hero' => 'genesis-blocks/gb-container',
                'testimonial' => 'genesis-blocks/gb-testimonial',
                'pricing' => 'genesis-blocks/gb-pricing',
                'team' => 'genesis-blocks/gb-profile',
                'content' => 'genesis-blocks/gb-container',
                'cta' => 'genesis-blocks/gb-button',
                'faq' => 'genesis-blocks/gb-accordion',
            ),
            'kadence_blocks' => array(
                'hero' => 'kadence/rowlayout',
                'testimonial' => 'kadence/testimonials',
                'pricing' => 'kadence/pricing',
                'team' => 'kadence/infobox',
                'content' => 'kadence/column',
                'cta' => 'kadence/button',
                'faq' => 'kadence/accordion',
            ),
            'stackable' => array(
                'hero' => 'ugb/hero',
                'testimonial' => 'ugb/testimonial',
                'team' => 'ugb/team-member',
                'content' => 'ugb/container',
                'cta' => 'ugb/button',
                'faq' => 'ugb/expand',
            ),
            'ultimate_addons' => array(
                'hero' => 'uagb/container',
                'testimonial' => 'uagb/testimonial',
                'pricing' => 'uagb/table-of-contents',
                'team' => 'uagb/team',
                'content' => 'uagb/container',
                'cta' => 'uagb/buttons',
                'faq' => 'uagb/faq',
            ),
            'core' => array(
                'hero' => 'core/cover',
                'testimonial' => 'core/quote',
                'pricing' => 'core/table',
                'team' => 'core/media-text',
                'content' => 'core/paragraph',
                'cta' => 'core/buttons',
                'faq' => 'core/details',
            ),
        );
        
        return $primary_blocks[ $plugin_key ][ $section_type ] ?? $primary_blocks['core'][ $section_type ] ?? 'core/paragraph';
    }

    /**
     * Get fallback blocks for section type and plugin
     *
     * @param string $section_type Section type.
     * @param string $plugin_key Plugin key.
     * @return array Fallback block names.
     */
    private function get_fallback_blocks_for_section( $section_type, $plugin_key ) {
        // Define fallback blocks for each section type
        $fallback_blocks = array(
            'hero' => array( 'core/cover', 'core/group', 'core/media-text' ),
            'testimonial' => array( 'core/quote', 'core/media-text', 'core/group' ),
            'pricing' => array( 'core/table', 'core/group', 'core/columns' ),
            'team' => array( 'core/media-text', 'core/group', 'core/columns' ),
            'content' => array( 'core/paragraph', 'core/heading', 'core/group' ),
            'cta' => array( 'core/buttons', 'core/button', 'core/group' ),
            'faq' => array( 'core/details', 'core/group', 'core/paragraph' ),
        );
        
        return $fallback_blocks[ $section_type ] ?? array( 'core/paragraph', 'core/group' );
    }

    /**
     * Get pattern preference for section type and plugin
     *
     * @param string $section_type Section type.
     * @param string $plugin_key Plugin key.
     * @return string Pattern preference.
     */
    private function get_pattern_preference_for_section( $section_type, $plugin_key ) {
        // Define pattern preferences for each plugin and section type
        $pattern_preferences = array(
            'genesis_blocks' => array(
                'hero' => 'hero-with-background',
                'testimonial' => 'testimonial-card',
                'pricing' => 'pricing-table',
                'team' => 'team-member-card',
            ),
            'kadence_blocks' => array(
                'hero' => 'hero-with-image',
                'testimonial' => 'testimonial-slider',
                'pricing' => 'pricing-comparison',
                'team' => 'team-grid',
            ),
            'stackable' => array(
                'hero' => 'hero-banner',
                'testimonial' => 'testimonial-block',
                'team' => 'team-profile',
            ),
        );
        
        return $pattern_preferences[ $plugin_key ][ $section_type ] ?? '';
    }

    /**
     * Render block preferences panel
     */
    public function render_preferences_panel() {
        $detected_plugins = $this->get_detected_plugins();
        $settings = get_option( 'ai_composer_settings', array() );
        $current_priorities = $settings['block_preferences']['plugin_priorities'] ?? array();
        $section_mappings = $settings['block_preferences']['section_mappings'] ?? array();

        include AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/admin/block-preferences-panel.php';
    }

    /**
     * AJAX handler for refreshing plugin detection
     */
    public function ajax_refresh_plugins() {
        Security_Helper::verify_ajax_request();

        // Clear cache and rescan
        delete_transient( 'ai_composer_detected_plugins' );
        delete_transient( 'ai_composer_plugins_scanned' );
        
        $detected = $this->scan_active_plugins();
        set_transient( 'ai_composer_detected_plugins', $detected, HOUR_IN_SECONDS );

        wp_send_json_success(
            array(
                'message' => __( 'Plugin detection refreshed successfully.', 'ai-page-composer' ),
                'plugins' => $detected,
            )
        );
    }

    /**
     * Update plugin priorities from settings
     *
     * @param array $new_priorities New priority settings.
     * @return bool Update success
     */
    public function update_plugin_priorities( $new_priorities ) {
        $sanitized = Validation_Helper::sanitize_plugin_priorities( $new_priorities );
        
        $settings = get_option( 'ai_composer_settings', array() );
        $settings['block_preferences']['plugin_priorities'] = $sanitized;
        
        return update_option( 'ai_composer_settings', $settings );
    }

    /**
     * Get section types
     *
     * @return array Section types with labels
     */
    public function get_section_types() {
        return array(
            'hero' => __( 'Hero Sections', 'ai-page-composer' ),
            'content' => __( 'Content Sections', 'ai-page-composer' ),
            'testimonial' => __( 'Testimonials', 'ai-page-composer' ),
            'pricing' => __( 'Pricing Tables', 'ai-page-composer' ),
            'team' => __( 'Team Members', 'ai-page-composer' ),
            'faq' => __( 'FAQ Sections', 'ai-page-composer' ),
            'cta' => __( 'Call to Action', 'ai-page-composer' ),
        );
    }
}