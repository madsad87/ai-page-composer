<?php
/**
 * Plugin Deactivator - Handles Cleanup During Plugin Deactivation
 * 
 * This file manages cleanup tasks when the plugin is deactivated. It clears scheduled events, removes
 * transients, and performs other temporary cleanup operations. Note: User data is preserved during
 * deactivation and only removed during uninstall for data safety.
 *
 * Plugin Deactivator class
 *
 * @package ModernWPPlugin
 */

namespace ModernWPPlugin\Core;

/**
 * Plugin Deactivator class
 */
class Deactivator {

    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear scheduled events
        self::clear_scheduled_events();

        // Clear transients
        self::clear_transients();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Note: We don't delete user data on deactivation
        // Data cleanup should only happen on uninstall
    }

    /**
     * Clear scheduled events
     */
    private static function clear_scheduled_events() {
        wp_clear_scheduled_hook( 'modern_wp_plugin_daily_cleanup' );
        wp_clear_scheduled_hook( 'modern_wp_plugin_weekly_maintenance' );
    }

    /**
     * Clear plugin transients
     */
    private static function clear_transients() {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_modern_wp_plugin_%'
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_modern_wp_plugin_%'
            )
        );
    }
}