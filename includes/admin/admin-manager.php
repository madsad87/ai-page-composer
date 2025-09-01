<?php
/**
 * Admin Manager - WordPress Admin Interface Integration
 * 
 * This file handles all WordPress admin functionality for AI Page Composer including 
 * settings pages, admin menus, and admin-specific scripts/styles. It provides the 
 * "Content → AI Composer" menu and comprehensive settings interface with proper 
 * form handling, validation, and Block Preferences panel.
 *
 * Admin Manager class
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\Admin;

use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;

// Declare WordPress functions for namespace compatibility
if ( ! function_exists( __NAMESPACE__ . '\add_action' ) ) {
    function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
        return \add_action( $hook, $callback, $priority, $accepted_args );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\add_filter' ) ) {
    function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
        return \add_filter( $hook, $callback, $priority, $accepted_args );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\add_submenu_page' ) ) {
    function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
        return \add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\__' ) ) {
    function __( $text, $domain = 'default' ) {
        return \__( $text, $domain );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\get_bloginfo' ) ) {
    function get_bloginfo( $show = '' ) {
        return \get_bloginfo( $show );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\add_settings_error' ) ) {
    function add_settings_error( $setting, $code, $message, $type = 'error' ) {
        return \add_settings_error( $setting, $code, $message, $type );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\get_option' ) ) {
    function get_option( $option, $default = false ) {
        return \get_option( $option, $default );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\wp_enqueue_style' ) ) {
    function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
        return \wp_enqueue_style( $handle, $src, $deps, $ver, $media );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\wp_enqueue_script' ) ) {
    function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
        return \wp_enqueue_script( $handle, $src, $deps, $ver, $in_footer );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\wp_localize_script' ) ) {
    function wp_localize_script( $handle, $object_name, $l10n ) {
        return \wp_localize_script( $handle, $object_name, $l10n );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\admin_url' ) ) {
    function admin_url( $path = '', $scheme = 'admin' ) {
        return \admin_url( $path, $scheme );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\wp_enqueue_media' ) ) {
    function wp_enqueue_media( $args = array() ) {
        return \wp_enqueue_media( $args );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\settings_errors' ) ) {
    function settings_errors( $setting = '', $sanitize = false, $hide_on_update = false ) {
        return \settings_errors( $setting, $sanitize, $hide_on_update );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\get_current_screen' ) ) {
    function get_current_screen() {
        return \get_current_screen();
    }
}

if ( ! function_exists( __NAMESPACE__ . '\esc_html_e' ) ) {
    function esc_html_e( $text, $domain = 'default' ) {
        return \esc_html_e( $text, $domain );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ) {
        return \esc_html__( $text, $domain );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\esc_url' ) ) {
    function esc_url( $url, $protocols = null, $_context = 'display' ) {
        return \esc_url( $url, $protocols, $_context );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\add_meta_box' ) ) {
    function add_meta_box( $id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null ) {
        return \add_meta_box( $id, $title, $callback, $screen, $context, $priority, $callback_args );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\wp_nonce_field' ) ) {
    function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
        return \wp_nonce_field( $action, $name, $referer, $echo );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\wp_create_nonce' ) ) {
    function wp_create_nonce( $action = -1 ) {
        return \wp_create_nonce( $action );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\check_ajax_referer' ) ) {
    function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
        return \check_ajax_referer( $action, $query_arg, $die );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\current_user_can' ) ) {
    function current_user_can( $capability, ...$args ) {
        return \current_user_can( $capability, ...$args );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\wp_send_json_error' ) ) {
    function wp_send_json_error( $data = null, $status_code = null ) {
        return \wp_send_json_error( $data, $status_code );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\wp_send_json_success' ) ) {
    function wp_send_json_success( $data = null, $status_code = null ) {
        return \wp_send_json_success( $data, $status_code );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\get_post' ) ) {
    function get_post( $post = null, $output = 'OBJECT', $filter = 'raw' ) {
        return \get_post( $post, $output, $filter );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\get_post_type_object' ) ) {
    function get_post_type_object( $post_type ) {
        return \get_post_type_object( $post_type );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\wp_trim_words' ) ) {
    function wp_trim_words( $text, $num_words = 55, $more = null ) {
        return \wp_trim_words( $text, $num_words, $more );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\strip_tags' ) ) {
    function strip_tags( $str, $allowable_tags = null ) {
        return \strip_tags( $str, $allowable_tags );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\update_option' ) ) {
    function update_option( $option, $value, $autoload = null ) {
        return \update_option( $option, $value, $autoload );
    }
}

/**
 * Admin Manager class for AI Page Composer
 */
class Admin_Manager {

    /**
     * Settings manager instance
     *
     * @var Settings_Manager
     */
    private $settings_manager;

    /**
     * Block preferences instance
     *
     * @var Block_Preferences
     */
    private $block_preferences;

    /**
     * Constructor
     *
     * @param Settings_Manager  $settings_manager  Settings manager instance.
     * @param Block_Preferences $block_preferences Block preferences instance.
     */
    public function __construct( $settings_manager, $block_preferences ) {
        $this->settings_manager  = $settings_manager;
        $this->block_preferences = $block_preferences;

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        add_filter( 'plugin_action_links_' . AI_PAGE_COMPOSER_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_ai_composer_quick_generate_content', array( $this, 'ajax_quick_generate_content' ) );
        add_action( 'wp_ajax_test_mvdb_connection', array( $this, 'ajax_test_mvdb_connection' ) );
    }

    /**
     * Add admin menu under Content
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php',
            __( 'AI Page Composer', 'ai-page-composer' ),
            __( 'AI Composer', 'ai-page-composer' ),
            'manage_options',
            'ai-composer',
            array( $this, 'render_main_page' )
        );
    }

    /**
     * Initialize admin settings
     */
    public function admin_init() {
        // Settings are handled by Settings_Manager
        // Check requirements on admin load
        $this->check_requirements();
    }

    /**
     * Check plugin requirements
     */
    public function check_requirements() {
        // Check API keys
        $settings = $this->settings_manager->get_all_settings();
        $api_settings = $settings['api_settings'] ?? array();

        if ( empty( $api_settings['openai_api_key'] ) || empty( $api_settings['mvdb_api_key'] ) ) {
            add_action( 'admin_notices', array( $this, 'missing_api_keys_notice' ) );
        }

        // Check WordPress version
        if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
            add_action( 'admin_notices', array( $this, 'wp_version_notice' ) );
        }
    }

    /**
     * Add meta boxes for post editor
     */
    public function add_meta_boxes() {
        // Add AI Composer meta box to posts and pages
        $post_types = array( 'post', 'page' );
        
        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'ai-composer-meta-box',
                __( '🤖 AI Page Composer', 'ai-page-composer' ),
                array( $this, 'render_ai_composer_meta_box' ),
                $post_type,
                'side',
                'high'
            );
        }
    }

    /**
     * Render AI Composer meta box
     *
     * @param WP_Post $post Current post object.
     */
    public function render_ai_composer_meta_box( $post ) {
        // Add nonce for security
        wp_nonce_field( 'ai_composer_meta_box', 'ai_composer_meta_box_nonce' );
        
        // Check if APIs are configured
        $settings = $this->settings_manager->get_all_settings();
        $api_settings = $settings['api_settings'] ?? array();
        $apis_configured = ! empty( $api_settings['openai_api_key'] ) && ! empty( $api_settings['mvdb_api_key'] );
        
        if ( ! $apis_configured ) {
            echo '<p class="description">';
            printf(
                /* translators: %s: Settings page URL */
                esc_html__( 'Please configure your API keys in the %s first.', 'ai-page-composer' ),
                '<a href="' . esc_url( admin_url( 'edit.php?page=ai-composer' ) ) . '">' . esc_html__( 'settings page', 'ai-page-composer' ) . '</a>'
            );
            echo '</p>';
            return;
        }
        
        ?>
        <div class="ai-composer-meta-box-content">
            <p class="description">
                <?php esc_html_e( 'Generate AI-powered content for this post using your configured settings.', 'ai-page-composer' ); ?>
            </p>
            
            <div class="ai-composer-generation-controls">
                <!-- The JavaScript will populate this area with buttons -->
            </div>
            
            <div id="ai-composer-generation-status" class="ai-composer-status-area">
                <!-- Status messages will appear here -->
            </div>
        </div>
        <?php
    }

    /**
     * Render main settings page
     */
    public function render_main_page() {
        // Security check
        Security_Helper::verify_request( false );

        // Handle form submission
        if ( isset( $_POST['submit'] ) ) {
            $this->handle_settings_save();
        }

        // Get current settings and detected plugins
        $settings = $this->settings_manager->get_all_settings();
        $detected_plugins = $this->block_preferences->get_detected_plugins();
        $section_types = $this->block_preferences->get_section_types();
        
        // Get cost variables for the settings page
        $daily_costs = get_option( 'ai_composer_daily_costs', 0.0 );
        $monthly_costs = get_option( 'ai_composer_monthly_costs', 0.0 );

        // Render the page
        include AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }

    /**
     * Handle settings form submission
     */
    private function handle_settings_save() {
        // Verify nonce
        Security_Helper::verify_request( true );

        // Process the form (WordPress Settings API handles this automatically)
        // Display success message
        add_settings_error(
            'ai_composer_settings',
            'settings_saved',
            __( 'Settings saved successfully.', 'ai-page-composer' ),
            'success'
        );
    }

    /**
     * Render API Configuration tab content
     *
     * @param array $settings Current settings.
     */
    public function render_api_config_tab( $settings ) {
        $api_settings = $settings['api_settings'] ?? array();
        include AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/admin/tabs/api-configuration.php';
    }

    /**
     * Render Generation Defaults tab content
     *
     * @param array $settings Current settings.
     */
    public function render_generation_defaults_tab( $settings ) {
        $generation_defaults = $settings['generation_defaults'] ?? array();
        include AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/admin/tabs/generation-defaults.php';
    }

    /**
     * Render Content Policies tab content
     *
     * @param array $settings Current settings.
     */
    public function render_content_policies_tab( $settings ) {
        $content_policies = $settings['content_policies'] ?? array();
        include AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/admin/tabs/content-policies.php';
    }

    /**
     * Render Block Preferences tab content
     *
     * @param array $settings         Current settings.
     * @param array $detected_plugins Detected block plugins.
     * @param array $section_types    Available section types.
     */
    public function render_block_preferences_tab( $settings, $detected_plugins, $section_types ) {
        $block_preferences = $settings['block_preferences'] ?? array();
        include AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/admin/tabs/block-preferences.php';
    }

    /**
     * Render Cost Management tab content
     *
     * @param array $settings Current settings.
     */
    public function render_cost_management_tab( $settings ) {
        $cost_management = $settings['cost_management'] ?? array();
        
        // Get current usage stats
        $daily_costs = get_option( 'ai_composer_daily_costs', 0.0 );
        $monthly_costs = get_option( 'ai_composer_monthly_costs', 0.0 );
        
        include AI_PAGE_COMPOSER_PLUGIN_DIR . 'templates/admin/tabs/cost-management.php';
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        // Load on settings page
        $load_on_settings = ( 'posts_page_ai-composer' === $hook );
        
        // Load on post editor pages
        $load_on_editor = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
        
        if ( ! $load_on_settings && ! $load_on_editor ) {
            return;
        }

        // Enqueue admin styles
        wp_enqueue_style(
            'ai-page-composer-admin',
            AI_PAGE_COMPOSER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AI_PAGE_COMPOSER_VERSION
        );

        // Enqueue admin script
        wp_enqueue_script(
            'ai-page-composer-admin',
            AI_PAGE_COMPOSER_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'wp-util' ),
            AI_PAGE_COMPOSER_VERSION,
            true
        );
        
        // Always localize the script with basic admin AJAX data
        wp_localize_script(
            'ai-page-composer-admin',
            'aiComposerAdminBase',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'ai_composer_nonce' ),
                'settingsUrl' => admin_url( 'edit.php?page=ai-composer' ),
            )
        );

        // Only load settings-specific scripts on settings page
        if ( $load_on_settings ) {
            // Enqueue settings specific script
            wp_enqueue_script(
                'ai-page-composer-settings',
                AI_PAGE_COMPOSER_PLUGIN_URL . 'assets/js/settings.js',
                array( 'jquery', 'wp-util' ),
                AI_PAGE_COMPOSER_VERSION,
                true
            );
            
            // Enqueue section generation admin script
            wp_enqueue_script(
                'ai-page-composer-section-generation',
                AI_PAGE_COMPOSER_PLUGIN_URL . 'assets/js/section-generation-admin.js',
                array( 'jquery', 'wp-util' ),
                AI_PAGE_COMPOSER_VERSION,
                true
            );

            // Localize script data
            wp_localize_script(
                'ai-page-composer-settings',
                'aiComposerSettings',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce' => Security_Helper::generate_ajax_nonce(),
                    'strings' => array(
                        'scanning' => __( 'Scanning...', 'ai-page-composer' ),
                        'refreshSuccess' => __( 'Plugin detection refreshed successfully.', 'ai-page-composer' ),
                        'refreshError' => __( 'Failed to refresh plugin detection.', 'ai-page-composer' ),
                        'saveSuccess' => __( 'Settings saved successfully.', 'ai-page-composer' ),
                        'saveError' => __( 'Failed to save settings.', 'ai-page-composer' ),
                        'confirmReset' => __( 'Are you sure you want to reset all settings to defaults?', 'ai-page-composer' ),
                    ),
                )
            );
            
            // Localize script data for section generation admin
            wp_localize_script(
                'ai-page-composer-section-generation',
                'aiComposerAdmin',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce' => Security_Helper::generate_ajax_nonce(),
                    'strings' => array(
                        'testingSection' => __( 'Testing section generation...', 'ai-page-composer' ),
                        'clearingCache' => __( 'Clearing cache...', 'ai-page-composer' ),
                        'loadingStats' => __( 'Loading statistics...', 'ai-page-composer' ),
                        'confirmClearCache' => __( 'Are you sure you want to clear the section cache?', 'ai-page-composer' ),
                    ),
                )
            );
        }
        
        // Localize script for AJAX on editor pages
        if ( $load_on_editor ) {
            wp_localize_script(
                'ai-page-composer-admin',
                'aiComposerAdmin',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce( 'ai_composer_nonce' ),
                    'settingsUrl' => admin_url( 'edit.php?page=ai-composer' ),
                    'strings' => array(
                        'generating' => __( 'Generating content...', 'ai-page-composer' ),
                        'success' => __( 'Content generated successfully!', 'ai-page-composer' ),
                        'error' => __( 'Generation failed. Please try again.', 'ai-page-composer' ),
                    ),
                )
            );
        }
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Display settings errors/notices
        settings_errors( 'ai_composer_settings' );
    }

    /**
     * Notice for missing API keys
     */
    public function missing_api_keys_notice() {
        $screen = get_current_screen();
        if ( 'posts_page_ai-composer' === $screen->id ) {
            return; // Don't show on settings page
        }

        $settings_url = admin_url( 'edit.php?page=ai-composer' );
        ?>        
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php esc_html_e( 'AI Page Composer:', 'ai-page-composer' ); ?></strong>
                <?php 
                printf(
                    /* translators: %s: Settings page URL */
                    esc_html__( 'Please configure your API keys in the %s to start using the plugin.', 'ai-page-composer' ),
                    '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'settings page', 'ai-page-composer' ) . '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Notice for WordPress version compatibility
     */
    public function wp_version_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e( 'AI Page Composer:', 'ai-page-composer' ); ?></strong>
                <?php esc_html_e( 'This plugin requires WordPress 6.0 or higher. Please update WordPress.', 'ai-page-composer' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Add action links to plugin list
     *
     * @param array $links Existing action links.
     * @return array Modified action links.
     */
    public static function add_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'edit.php?page=ai-composer' ),
            __( 'Settings', 'ai-page-composer' )
        );
        
        $docs_link = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://yourwebsite.com/docs/ai-page-composer',
            __( 'Documentation', 'ai-page-composer' )
        );
        
        array_unshift( $links, $settings_link, $docs_link );
        return $links;
    }

    /**
     * Get plugin status for dashboard
     *
     * @return array Plugin status information
     */
    public function get_plugin_status() {
        $settings = $this->settings_manager->get_all_settings();
        $api_settings = $settings['api_settings'] ?? array();
        
        return array(
            'api_configured' => ! empty( $api_settings['openai_api_key'] ) && ! empty( $api_settings['mvdb_api_key'] ),
            'daily_costs' => get_option( 'ai_composer_daily_costs', 0.0 ),
            'monthly_costs' => get_option( 'ai_composer_monthly_costs', 0.0 ),
            'plugin_version' => AI_PAGE_COMPOSER_VERSION,
            'detected_plugins' => count( $this->block_preferences->get_detected_plugins() ),
        );
    }

    /**
     * Handle AJAX request for quick content generation
     */
    public function ajax_quick_generate_content() {
        // Verify nonce and permissions
        check_ajax_referer( 'ai_composer_nonce', 'nonce' );
        
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-page-composer' ) ) );
        }
        
        $post_id = intval( $_POST['post_id'] ?? 0 );
        
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'ai-page-composer' ) ) );
        }
        
        try {
            // Debug: Log that we reached this point
            error_log( '[AI Composer] Starting quick generation for post ID: ' . $post_id );
            
            // Get post data
            $post = get_post( $post_id );
            if ( ! $post ) {
                error_log( '[AI Composer] Post not found: ' . $post_id );
                wp_send_json_error( array( 'message' => __( 'Post not found.', 'ai-page-composer' ) ) );
            }
            
            // Check if APIs are configured
            $settings = $this->settings_manager->get_all_settings();
            $api_settings = $settings['api_settings'] ?? array();
            
            error_log( '[AI Composer] API settings check - OpenAI: ' . ( ! empty( $api_settings['openai_api_key'] ) ? 'configured' : 'missing' ) . ', MVDB: ' . ( ! empty( $api_settings['mvdb_api_key'] ) ? 'configured' : 'missing' ) );
            
            if ( empty( $api_settings['openai_api_key'] ) || empty( $api_settings['mvdb_api_key'] ) ) {
                wp_send_json_error( array( 
                    'message' => __( 'API keys not configured. Please configure them in the settings first.', 'ai-page-composer' ),
                    'action' => 'configure_apis'
                ) );
            }
            
            // Check if Section_Generator class exists
            if ( ! class_exists( '\AIPageComposer\API\Section_Generator' ) ) {
                error_log( '[AI Composer] Section_Generator class not found' );
                wp_send_json_error( array( 'message' => __( 'AI generation service not available. Please check plugin installation.', 'ai-page-composer' ) ) );
            }
            
            // Check if dependencies exist
            $required_classes = [
                '\AIPageComposer\API\MVDB_Manager',
                '\AIPageComposer\API\Block_Resolver',
                '\AIPageComposer\API\Citation_Manager',
                '\AIPageComposer\API\AI_Service_Client'
            ];
            
            foreach ( $required_classes as $class ) {
                if ( ! class_exists( $class ) ) {
                    error_log( '[AI Composer] Required class not found: ' . $class );
                    wp_send_json_error( array( 'message' => sprintf( __( 'Required component not available: %s', 'ai-page-composer' ), $class ) ) );
                }
            }
            
            // Initialize section generator
            error_log( '[AI Composer] Initializing Section_Generator' );
            $section_generator = new \AIPageComposer\API\Section_Generator();
            
            // Prepare generation parameters based on post
            $content_brief = $this->prepare_content_brief_from_post( $post );
            $generation_params = $this->prepare_generation_params( $post, $content_brief, $settings );
            
            error_log( '[AI Composer] Generation parameters prepared: ' . wp_json_encode( $generation_params, JSON_PARTIAL_OUTPUT_ON_ERROR ) );
            
            // Generate content
            error_log( '[AI Composer] Starting content generation' );
            $result = $section_generator->generate( $generation_params );
            
            error_log( '[AI Composer] Content generation completed successfully' );
            
            // Track costs
            $this->track_generation_cost( $result['generation_metadata']['cost_usd'] );
            
            wp_send_json_success( array( 
                'message' => __( 'Content generated successfully!', 'ai-page-composer' ),
                'content' => $result['content'],
                'html' => $result['html'],
                'block_json' => $result['block_json'],
                'metadata' => $result['generation_metadata']
            ) );
            
        } catch ( \Exception $e ) {
            error_log( '[AI Composer] Quick generation failed: ' . $e->getMessage() );
            error_log( '[AI Composer] Stack trace: ' . $e->getTraceAsString() );
            wp_send_json_error( array( 
                'message' => sprintf( 
                    /* translators: %s: Error message */
                    __( 'Generation failed: %s', 'ai-page-composer' ), 
                    $e->getMessage() 
                )
            ) );
        }
    }
    
    /**
     * Prepare content brief from post data
     *
     * @param WP_Post $post The post object.
     * @return string Content brief.
     */
    private function prepare_content_brief_from_post( $post ) {
        $brief_parts = array();
        
        // Use post title as primary context
        if ( ! empty( $post->post_title ) ) {
            $brief_parts[] = 'Topic: ' . $post->post_title;
        }
        
        // Add existing content as context if available
        if ( ! empty( $post->post_content ) ) {
            $existing_content = wp_trim_words( strip_tags( $post->post_content ), 50 );
            $brief_parts[] = 'Existing content context: ' . $existing_content;
        }
        
        // Add post type context
        $post_type_object = get_post_type_object( $post->post_type );
        if ( $post_type_object ) {
            $brief_parts[] = 'Content type: ' . $post_type_object->labels->singular_name;
        }
        
        // Default brief if no context available
        if ( empty( $brief_parts ) ) {
            $brief_parts[] = 'Generate professional content for this ' . $post->post_type;
        }
        
        return implode( '. ', $brief_parts );
    }
    
    /**
     * Prepare generation parameters for post
     *
     * @param WP_Post $post The post object.
     * @param string  $content_brief Content brief.
     * @param array   $settings Plugin settings.
     * @return array Generation parameters.
     */
    private function prepare_generation_params( $post, $content_brief, $settings ) {
        // Get default settings
        $generation_defaults = $settings['generation_defaults'] ?? array();
        $section_generation = $settings['section_generation'] ?? array();
        
        return array(
            'sectionId' => 'post-' . $post->ID . '-quick-gen',
            'content_brief' => $content_brief,
            'mode' => $section_generation['default_mode'] ?? 'hybrid',
            'alpha' => floatval( $section_generation['alpha'] ?? 0.7 ),
            'block_preferences' => $this->get_post_block_preferences( $post ),
            'image_requirements' => array(
                'include_image' => false, // Skip images for quick generation
                'style' => 'photographic'
            ),
            'citation_settings' => array(
                'include_citations' => true,
                'format' => 'inline'
            )
        );
    }
    
    /**
     * Get block preferences for post
     *
     * @param WP_Post $post The post object.
     * @return array Block preferences.
     */
    private function get_post_block_preferences( $post ) {
        // Get detected plugins
        $detected_plugins = $this->block_preferences->get_detected_plugins();
        
        // Default to core blocks if no plugins detected
        if ( empty( $detected_plugins ) ) {
            return array(
                'plugin' => 'core',
                'block_name' => 'core/paragraph',
                'section_type' => 'content'
            );
        }
        
        // Use first available plugin with highest priority
        $preferred_plugin = array_key_first( $detected_plugins );
        $plugin_blocks = $detected_plugins[ $preferred_plugin ]['blocks'] ?? array();
        
        if ( ! empty( $plugin_blocks ) ) {
            $preferred_block = array_key_first( $plugin_blocks );
            return array(
                'plugin' => $preferred_plugin,
                'block_name' => $preferred_block,
                'section_type' => 'content'
            );
        }
        
        // Fallback to core
        return array(
            'plugin' => 'core',
            'block_name' => 'core/paragraph',
            'section_type' => 'content'
        );
    }
    
    /**
     * Track generation cost
     *
     * @param float $cost_usd Cost in USD.
     */
    private function track_generation_cost( $cost_usd ) {
        if ( $cost_usd <= 0 ) {
            return;
        }
        
        // Update daily costs
        $daily_costs = get_option( 'ai_composer_daily_costs', 0.0 );
        update_option( 'ai_composer_daily_costs', $daily_costs + $cost_usd );
        
        // Update monthly costs
        $monthly_costs = get_option( 'ai_composer_monthly_costs', 0.0 );
        update_option( 'ai_composer_monthly_costs', $monthly_costs + $cost_usd );
    }

    /**
     * AJAX handler to test MVDB connection
     */
    public function ajax_test_mvdb_connection() {
        // Verify nonce and permissions
        check_ajax_referer( 'ai_composer_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-page-composer' ) ) );
        }

        $api_url = sanitize_url( $_POST['api_url'] ?? '' );
        $access_token = sanitize_text_field( $_POST['access_token'] ?? '' );

        if ( empty( $api_url ) || empty( $access_token ) ) {
            wp_send_json_error( array( 
                'message' => __( 'Both API URL and Access Token are required.', 'ai-page-composer' ),
                'suggestions' => array(
                    __( 'Enter your WP Engine Smart Search API URL', 'ai-page-composer' ),
                    __( 'Enter your WP Engine Smart Search Access Token', 'ai-page-composer' )
                )
            ) );
        }

        try {
            // Record start time for response measurement
            $start_time = microtime( true );
            
            // Prepare a simple test query to MVDB endpoint
            $test_query = array(
                'query' => 'query TestConnection { __typename }',
                'variables' => array()
            );

            $response = wp_remote_post( $api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'AI-Page-Composer/' . ( defined( 'AI_PAGE_COMPOSER_VERSION' ) ? AI_PAGE_COMPOSER_VERSION : '1.0.0' )
                ),
                'body' => wp_json_encode( $test_query ),
                'timeout' => 15,
                'data_format' => 'body'
            ) );

            $response_time = round( ( microtime( true ) - $start_time ) * 1000 );

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( array(
                    'message' => sprintf( __( 'Connection failed: %s', 'ai-page-composer' ), $response->get_error_message() ),
                    'details' => $response->get_error_code(),
                    'suggestions' => array(
                        __( 'Check your internet connection', 'ai-page-composer' ),
                        __( 'Verify the API URL is correct', 'ai-page-composer' ),
                        __( 'Ensure your server can make outbound HTTPS requests', 'ai-page-composer' )
                    )
                ) );
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );

            if ( $response_code === 200 ) {
                // Success - connection established
                wp_send_json_success( array(
                    'message' => __( 'MVDB connection successful!', 'ai-page-composer' ),
                    'response_time' => $response_time,
                    'endpoint_info' => parse_url( $api_url, PHP_URL_HOST )
                ) );
            } elseif ( $response_code === 401 ) {
                // Authentication error
                wp_send_json_error( array(
                    'message' => __( 'Authentication failed', 'ai-page-composer' ),
                    'details' => __( 'Invalid access token', 'ai-page-composer' ),
                    'suggestions' => array(
                        __( 'Check your WP Engine Smart Search access token', 'ai-page-composer' ),
                        __( 'Ensure the token has not expired', 'ai-page-composer' ),
                        __( 'Verify the token has proper permissions', 'ai-page-composer' )
                    )
                ) );
            } elseif ( $response_code === 403 ) {
                // Permission error
                wp_send_json_error( array(
                    'message' => __( 'Access forbidden', 'ai-page-composer' ),
                    'details' => __( 'Token lacks required permissions', 'ai-page-composer' ),
                    'suggestions' => array(
                        __( 'Check your access token permissions', 'ai-page-composer' ),
                        __( 'Contact your WP Engine administrator', 'ai-page-composer' )
                    )
                ) );
            } elseif ( $response_code === 404 ) {
                // Endpoint not found
                wp_send_json_error( array(
                    'message' => __( 'API endpoint not found', 'ai-page-composer' ),
                    'details' => sprintf( __( 'URL returned 404: %s', 'ai-page-composer' ), $api_url ),
                    'suggestions' => array(
                        __( 'Check the API URL is correct', 'ai-page-composer' ),
                        __( 'Default URL: https://api.wpengine.com/smart-search/v1', 'ai-page-composer' ),
                        __( 'Contact WP Engine support if the endpoint has changed', 'ai-page-composer' )
                    )
                ) );
            } else {
                // Other HTTP error
                $decoded_response = json_decode( $response_body, true );
                $error_message = '';
                
                if ( $decoded_response && isset( $decoded_response['error'] ) ) {
                    $error_message = $decoded_response['error']['message'] ?? __( 'Unknown API error', 'ai-page-composer' );
                } else {
                    $error_message = sprintf( __( 'HTTP %d error', 'ai-page-composer' ), $response_code );
                }

                wp_send_json_error( array(
                    'message' => $error_message,
                    'details' => sprintf( __( 'Response code: %d', 'ai-page-composer' ), $response_code ),
                    'suggestions' => array(
                        __( 'Check the MVDB service status', 'ai-page-composer' ),
                        __( 'Try again in a few minutes', 'ai-page-composer' ),
                        __( 'Contact WP Engine support if the problem persists', 'ai-page-composer' )
                    )
                ) );
            }

        } catch ( \Exception $e ) {
            wp_send_json_error( array(
                'message' => sprintf( __( 'Connection test failed: %s', 'ai-page-composer' ), $e->getMessage() ),
                'details' => get_class( $e ),
                'suggestions' => array(
                    __( 'Check your server configuration', 'ai-page-composer' ),
                    __( 'Ensure PHP cURL extension is enabled', 'ai-page-composer' ),
                    __( 'Contact your hosting provider if issues persist', 'ai-page-composer' )
                )
            ) );
        }
    }
}