<?php
/**
 * Plugin Activator - Handles Plugin Activation Setup and Requirements
 * 
 * This file manages all tasks that need to be performed when the plugin is activated. It checks system
 * requirements (WordPress and PHP versions), creates database tables if needed, sets default options,
 * and performs any other one-time setup tasks required for the plugin to function properly.
 *
 * Plugin Activator class
 *
 * @package ModernWPPlugin
 */

namespace ModernWPPlugin\Core;

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
            wp_die( __( 'This plugin requires WordPress 6.0 or higher.', 'modern-wp-plugin' ) );
        }

        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            wp_die( __( 'This plugin requires PHP 7.4 or higher.', 'modern-wp-plugin' ) );
        }

        // Create custom tables if needed
        self::create_tables();

        // Set default options
        self::set_default_options();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Example custom table creation
        /*
        $table_name = $wpdb->prefix . 'modern_wp_plugin_data';

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            name tinytext NOT NULL,
            text text NOT NULL,
            url varchar(55) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        */
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $default_options = array(
            'version' => MODERN_WP_PLUGIN_VERSION,
            'installed_date' => current_time( 'mysql' ),
            'enable_feature_1' => true,
            'enable_feature_2' => false,
        );

        add_option( 'modern_wp_plugin_options', $default_options );
    }
}