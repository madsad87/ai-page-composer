<?php
/**
 * Plugin Activator - Handles Plugin Activation Setup and Requirements
 * 
 * This file manages all tasks that need to be performed when the AI Page Composer plugin is activated.
 * It checks system requirements (WordPress and PHP versions), creates database tables for blueprints
 * and generation runs, sets default settings, and performs other one-time setup tasks.
 *
 * Plugin Activator class
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\Core;

use AIPageComposer\Utils\Validation_Helper;

/**
 * Plugin Activator class
 */
class Activator {

    /**
     * Activate the plugin
     */
    public static function activate() {
        // Check WordPress version
        if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
            wp_die( __( 'AI Page Composer requires WordPress 6.0 or higher.', 'ai-page-composer' ) );
        }

        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            wp_die( __( 'AI Page Composer requires PHP 7.4 or higher.', 'ai-page-composer' ) );
        }

        // Create custom tables if needed
        self::create_tables();

        // Set default options
        self::set_default_options();

        // Initialize blueprint system
        self::init_blueprint_system();

        // Create default user capabilities
        self::add_capabilities();

        // Schedule cleanup events
        self::schedule_events();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Blueprints table for storing content templates
        $blueprints_table = $wpdb->prefix . 'ai_composer_blueprints';
        $blueprints_sql = "CREATE TABLE $blueprints_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            blueprint_data longtext NOT NULL,
            section_types text,
            created_by bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY created_by (created_by),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Generation runs table for tracking AI generations
        $runs_table = $wpdb->prefix . 'ai_composer_runs';
        $runs_sql = "CREATE TABLE $runs_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) UNSIGNED,
            user_id bigint(20) UNSIGNED NOT NULL,
            generation_mode varchar(20) DEFAULT 'hybrid',
            prompt_data longtext,
            generated_content longtext,
            tokens_used int(11) DEFAULT 0,
            cost_usd decimal(10,4) DEFAULT 0.0000,
            api_calls_made int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            error_message text,
            started_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            completed_at datetime,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY started_at (started_at)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $blueprints_sql );
        dbDelta( $runs_sql );
        
        // Cost tracking table for detailed cost logging
        $cost_log_table = $wpdb->prefix . 'ai_composer_cost_log';
        $cost_log_sql = "CREATE TABLE $cost_log_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            operation_type varchar(50) NOT NULL,
            cost_usd decimal(10,4) NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            api_provider varchar(50),
            token_count int(11),
            request_details longtext,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY operation_type (operation_type),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        // Cache table for section generation results
        $cache_table = $wpdb->prefix . 'ai_composer_cache';
        $cache_sql = "CREATE TABLE $cache_table (
            cache_key varchar(191) NOT NULL,
            cache_data longtext NOT NULL,
            cache_group varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY (cache_key),
            KEY cache_group (cache_group),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        dbDelta( $cost_log_sql );
        dbDelta( $cache_sql );
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        // API Configuration
        $api_settings = array(
            'openai_api_key' => '',
            'mvdb_api_key' => '',
            'image_api_key' => '',
        );

        // Generation Defaults
        $generation_defaults = array(
            'default_mode' => 'hybrid',
            'alpha_weight' => 0.7,
            'k_value' => 10,
            'min_score' => 0.5,
            'default_namespaces' => array( 'content' ),
        );

        // Content Policies
        $content_policies = array(
            'image_generation_policy' => 'auto',
            'internal_linking_enabled' => true,
            'max_internal_links' => 3,
            'citation_required' => true,
            'license_filters' => array( 'CC-BY', 'CC-BY-SA', 'public-domain' ),
        );

        // Block Preferences
        $block_preferences = array(
            'detection_enabled' => true,
            'plugin_priorities' => array(
                'genesis_blocks' => 8,
                'kadence_blocks' => 8,
                'stackable' => 7,
                'ultimate_addons' => 7,
                'blocksy' => 6,
                'core' => 5,
            ),
            'section_mappings' => array(
                'hero' => 'auto',
                'content' => 'auto',
                'testimonial' => 'auto',
                'pricing' => 'auto',
                'team' => 'auto',
                'faq' => 'auto',
                'cta' => 'auto',
            ),
            'custom_block_types' => array(),
        );

        // Cost Management
        $cost_management = array(
            'daily_budget_usd' => 10.0,
            'per_run_limit_usd' => 2.0,
            'token_limit_per_section' => 1000,
            'cost_alerts_enabled' => true,
            'budget_reset_schedule' => 'daily',
        );
        
        // Cache Settings
        $cache_settings = array(
            'enable_section_cache' => true,
            'section_cache_ttl' => 3600, // 1 hour
            'max_cache_size_mb' => 100,
            'cache_cleanup_enabled' => true,
            'cache_compression' => true,
        );
        
        // Section Generation Settings
        $section_generation = array(
            'default_mode' => 'hybrid',
            'alpha' => 0.7,
            'image_policy' => 'optional',
            'image_style' => 'photographic',
            'citations_enabled' => true,
            'citation_style' => 'inline',
        );

        // Plugin metadata
        $plugin_data = array(
            'version' => AI_PAGE_COMPOSER_VERSION,
            'installed_date' => current_time( 'mysql' ),
            'db_version' => '1.0.0',
        );

        // Main settings option
        $default_settings = array(
            'api_settings' => $api_settings,
            'generation_defaults' => $generation_defaults,
            'content_policies' => $content_policies,
            'block_preferences' => $block_preferences,
            'cost_management' => $cost_management,
            'cache_settings' => $cache_settings,
            'section_generation' => $section_generation,
        );

        // Add options (won't override existing)
        add_option( 'ai_composer_settings', $default_settings );
        add_option( 'ai_composer_plugin_data', $plugin_data );
        
        // Initialize cost tracking
        add_option( 'ai_composer_daily_costs', 0.0 );
        add_option( 'ai_composer_monthly_costs', 0.0 );
        add_option( 'ai_composer_last_reset', current_time( 'mysql' ) );
    }

    /**
     * Add custom capabilities
     */
    private static function add_capabilities() {
        $role = get_role( 'administrator' );
        if ( $role ) {
            $role->add_cap( 'use_ai_composer' );
            $role->add_cap( 'manage_ai_composer_settings' );
        }

        $role = get_role( 'editor' );
        if ( $role ) {
            $role->add_cap( 'use_ai_composer' );
        }
    }

    /**
     * Schedule cleanup and maintenance events
     */
    private static function schedule_events() {
        // Daily cost reset
        if ( ! wp_next_scheduled( 'ai_composer_daily_reset' ) ) {
            wp_schedule_event( time(), 'daily', 'ai_composer_daily_reset' );
        }

        // Weekly cleanup of old generation runs
        if ( ! wp_next_scheduled( 'ai_composer_weekly_cleanup' ) ) {
            wp_schedule_event( time(), 'weekly', 'ai_composer_weekly_cleanup' );
        }

        // Monthly cost reset
        if ( ! wp_next_scheduled( 'ai_composer_monthly_reset' ) ) {
            wp_schedule_event( time(), 'monthly', 'ai_composer_monthly_reset' );
        }
    }

    /**
     * Initialize blueprint system
     */
    private static function init_blueprint_system() {
        // Register ai_blueprint post type early
        self::register_blueprint_post_type();
        
        // Create default blueprint templates
        self::create_default_blueprints();
        
        // Set blueprint permissions
        self::add_blueprint_capabilities();
    }

    /**
     * Register blueprint post type during activation
     */
    private static function register_blueprint_post_type() {
        $labels = array(
            'name' => __( 'AI Blueprints', 'ai-page-composer' ),
            'singular_name' => __( 'AI Blueprint', 'ai-page-composer' ),
            'menu_name' => __( 'AI Blueprints', 'ai-page-composer' ),
            'add_new' => __( 'Add New Blueprint', 'ai-page-composer' ),
            'add_new_item' => __( 'Add New AI Blueprint', 'ai-page-composer' ),
            'edit_item' => __( 'Edit AI Blueprint', 'ai-page-composer' ),
            'new_item' => __( 'New AI Blueprint', 'ai-page-composer' ),
            'view_item' => __( 'View AI Blueprint', 'ai-page-composer' ),
            'search_items' => __( 'Search AI Blueprints', 'ai-page-composer' ),
            'not_found' => __( 'No AI blueprints found', 'ai-page-composer' ),
            'not_found_in_trash' => __( 'No AI blueprints found in trash', 'ai-page-composer' ),
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'ai-composer',
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'manage_ai_blueprints',
                'edit_posts' => 'manage_ai_blueprints',
                'edit_others_posts' => 'manage_ai_blueprints',
                'delete_posts' => 'manage_ai_blueprints',
                'delete_others_posts' => 'manage_ai_blueprints',
                'read_private_posts' => 'manage_ai_blueprints',
                'edit_post' => 'manage_ai_blueprints',
                'delete_post' => 'manage_ai_blueprints',
                'read_post' => 'manage_ai_blueprints'
            ),
            'hierarchical' => false,
            'supports' => array( 'title', 'author', 'revisions' ),
            'show_in_rest' => true,
            'rest_base' => 'ai-blueprints',
        );

        register_post_type( 'ai_blueprint', $args );
    }

    /**
     * Create default blueprint templates
     */
    private static function create_default_blueprints() {
        // Check if default blueprints already exist
        $existing_blueprints = get_posts( array(
            'post_type' => 'ai_blueprint',
            'meta_key' => '_is_default_blueprint',
            'meta_value' => '1',
            'posts_per_page' => 1,
        ) );

        if ( ! empty( $existing_blueprints ) ) {
            return; // Default blueprints already exist
        }

        // Landing Page Blueprint
        $landing_page_blueprint = array(
            'sections' => array(
                array(
                    'id' => 'hero-section',
                    'type' => 'hero',
                    'heading' => 'Hero Section',
                    'heading_level' => 1,
                    'word_target' => 100,
                    'media_policy' => 'required',
                    'internal_links' => 1,
                    'citations_required' => false,
                    'tone' => 'professional',
                    'allowed_blocks' => array(),
                    'block_preferences' => array(
                        'preferred_plugin' => 'auto',
                        'primary_block' => '',
                        'fallback_blocks' => array(),
                        'pattern_preference' => '',
                        'custom_attributes' => array()
                    )
                ),
                array(
                    'id' => 'features-section',
                    'type' => 'columns',
                    'heading' => 'Key Features',
                    'heading_level' => 2,
                    'word_target' => 200,
                    'media_policy' => 'optional',
                    'internal_links' => 2,
                    'citations_required' => false,
                    'tone' => 'professional',
                    'allowed_blocks' => array(),
                    'block_preferences' => array(
                        'preferred_plugin' => 'auto',
                        'primary_block' => '',
                        'fallback_blocks' => array(),
                        'pattern_preference' => '',
                        'custom_attributes' => array()
                    )
                ),
                array(
                    'id' => 'cta-section',
                    'type' => 'cta',
                    'heading' => 'Get Started Today',
                    'heading_level' => 2,
                    'word_target' => 75,
                    'media_policy' => 'none',
                    'internal_links' => 0,
                    'citations_required' => false,
                    'tone' => 'friendly',
                    'allowed_blocks' => array(),
                    'block_preferences' => array(
                        'preferred_plugin' => 'auto',
                        'primary_block' => '',
                        'fallback_blocks' => array(),
                        'pattern_preference' => '',
                        'custom_attributes' => array()
                    )
                )
            ),
            'global_settings' => array(
                'generation_mode' => 'hybrid',
                'hybrid_alpha' => 0.7,
                'mvdb_namespaces' => array( 'content' ),
                'max_tokens_per_section' => 1000,
                'image_generation_enabled' => true,
                'seo_optimization' => true,
                'accessibility_checks' => true,
                'cost_limit_usd' => 5.0
            ),
            'metadata' => array(
                'version' => '1.0.0',
                'description' => 'A default landing page blueprint with hero, features, and call-to-action sections.',
                'tags' => array( 'landing-page', 'marketing', 'default' ),
                'category' => 'landing-page',
                'estimated_time_minutes' => 25,
                'difficulty_level' => 'beginner'
            )
        );

        $landing_page_id = wp_insert_post( array(
            'post_title' => 'Default Landing Page Blueprint',
            'post_type' => 'ai_blueprint',
            'post_status' => 'publish',
            'post_author' => 1,
        ) );

        if ( ! is_wp_error( $landing_page_id ) ) {
            update_post_meta( $landing_page_id, '_ai_blueprint_schema', $landing_page_blueprint );
            update_post_meta( $landing_page_id, '_ai_blueprint_sections', $landing_page_blueprint['sections'] );
            update_post_meta( $landing_page_id, '_ai_blueprint_global_settings', $landing_page_blueprint['global_settings'] );
            update_post_meta( $landing_page_id, '_ai_blueprint_metadata', $landing_page_blueprint['metadata'] );
            update_post_meta( $landing_page_id, '_is_default_blueprint', '1' );
            update_post_meta( $landing_page_id, '_ai_blueprint_category', 'landing-page' );
            update_post_meta( $landing_page_id, '_ai_blueprint_section_count', 3 );
            update_post_meta( $landing_page_id, '_ai_blueprint_difficulty', 'beginner' );
            update_post_meta( $landing_page_id, '_ai_blueprint_generation_mode', 'hybrid' );
            update_post_meta( $landing_page_id, '_ai_blueprint_estimated_time', 25 );
        }

        // Blog Post Blueprint
        $blog_post_blueprint = array(
            'sections' => array(
                array(
                    'id' => 'introduction',
                    'type' => 'content',
                    'heading' => 'Introduction',
                    'heading_level' => 2,
                    'word_target' => 150,
                    'media_policy' => 'optional',
                    'internal_links' => 1,
                    'citations_required' => true,
                    'tone' => 'professional',
                    'allowed_blocks' => array(),
                    'block_preferences' => array(
                        'preferred_plugin' => 'auto',
                        'primary_block' => '',
                        'fallback_blocks' => array(),
                        'pattern_preference' => '',
                        'custom_attributes' => array()
                    )
                ),
                array(
                    'id' => 'main-content',
                    'type' => 'content',
                    'heading' => 'Main Content',
                    'heading_level' => 2,
                    'word_target' => 400,
                    'media_policy' => 'optional',
                    'internal_links' => 3,
                    'citations_required' => true,
                    'tone' => 'professional',
                    'allowed_blocks' => array(),
                    'block_preferences' => array(
                        'preferred_plugin' => 'auto',
                        'primary_block' => '',
                        'fallback_blocks' => array(),
                        'pattern_preference' => '',
                        'custom_attributes' => array()
                    )
                ),
                array(
                    'id' => 'conclusion',
                    'type' => 'content',
                    'heading' => 'Conclusion',
                    'heading_level' => 2,
                    'word_target' => 100,
                    'media_policy' => 'none',
                    'internal_links' => 1,
                    'citations_required' => false,
                    'tone' => 'professional',
                    'allowed_blocks' => array(),
                    'block_preferences' => array(
                        'preferred_plugin' => 'auto',
                        'primary_block' => '',
                        'fallback_blocks' => array(),
                        'pattern_preference' => '',
                        'custom_attributes' => array()
                    )
                )
            ),
            'global_settings' => array(
                'generation_mode' => 'grounded',
                'hybrid_alpha' => 0.8,
                'mvdb_namespaces' => array( 'content', 'docs' ),
                'max_tokens_per_section' => 800,
                'image_generation_enabled' => true,
                'seo_optimization' => true,
                'accessibility_checks' => true,
                'cost_limit_usd' => 3.0
            ),
            'metadata' => array(
                'version' => '1.0.0',
                'description' => 'A structured blog post blueprint with introduction, main content, and conclusion.',
                'tags' => array( 'blog-post', 'article', 'content', 'default' ),
                'category' => 'blog-post',
                'estimated_time_minutes' => 20,
                'difficulty_level' => 'intermediate'
            )
        );

        $blog_post_id = wp_insert_post( array(
            'post_title' => 'Default Blog Post Blueprint',
            'post_type' => 'ai_blueprint',
            'post_status' => 'publish',
            'post_author' => 1,
        ) );

        if ( ! is_wp_error( $blog_post_id ) ) {
            update_post_meta( $blog_post_id, '_ai_blueprint_schema', $blog_post_blueprint );
            update_post_meta( $blog_post_id, '_ai_blueprint_sections', $blog_post_blueprint['sections'] );
            update_post_meta( $blog_post_id, '_ai_blueprint_global_settings', $blog_post_blueprint['global_settings'] );
            update_post_meta( $blog_post_id, '_ai_blueprint_metadata', $blog_post_blueprint['metadata'] );
            update_post_meta( $blog_post_id, '_is_default_blueprint', '1' );
            update_post_meta( $blog_post_id, '_ai_blueprint_category', 'blog-post' );
            update_post_meta( $blog_post_id, '_ai_blueprint_section_count', 3 );
            update_post_meta( $blog_post_id, '_ai_blueprint_difficulty', 'intermediate' );
            update_post_meta( $blog_post_id, '_ai_blueprint_generation_mode', 'grounded' );
            update_post_meta( $blog_post_id, '_ai_blueprint_estimated_time', 20 );
        }
    }

    /**
     * Add blueprint-specific capabilities
     */
    private static function add_blueprint_capabilities() {
        $role = get_role( 'administrator' );
        if ( $role ) {
            $role->add_cap( 'manage_ai_blueprints' );
            $role->add_cap( 'create_ai_blueprints' );
            $role->add_cap( 'edit_ai_blueprints' );
            $role->add_cap( 'delete_ai_blueprints' );
        }

        $role = get_role( 'editor' );
        if ( $role ) {
            $role->add_cap( 'create_ai_blueprints' );
            $role->add_cap( 'edit_ai_blueprints' );
        }
    }
}