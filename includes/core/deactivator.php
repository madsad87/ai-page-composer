<?php
/**
 * Plugin Deactivator - Handles Cleanup During Plugin Deactivation
 * 
 * This file manages cleanup tasks when the AI Page Composer plugin is deactivated. 
 * It clears scheduled events, removes transients, and performs other temporary cleanup 
 * operations. Note: User data is preserved during deactivation and only removed during 
 * uninstall for data safety.
 *
 * Plugin Deactivator class
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\Core;

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

        // Cancel any running AI generation jobs
        self::cancel_running_jobs();

        // Clear WordPress object cache
        self::clear_object_cache();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Note: We don't delete user data on deactivation
        // Data cleanup should only happen on uninstall
    }

    /**
     * Clear scheduled events
     */
    private static function clear_scheduled_events() {
        wp_clear_scheduled_hook( 'ai_composer_daily_reset' );
        wp_clear_scheduled_hook( 'ai_composer_weekly_cleanup' );
        wp_clear_scheduled_hook( 'ai_composer_monthly_reset' );
    }

    /**
     * Clear plugin transients
     */
    private static function clear_transients() {
        global $wpdb;

        // Clear AI Composer specific transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_ai_composer_%'
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_ai_composer_%'
            )
        );

        // Clear plugin detection cache
        delete_transient( 'ai_composer_detected_plugins' );
        delete_transient( 'ai_composer_block_registry' );
        
        // Clear cost tracking cache
        delete_transient( 'ai_composer_daily_usage' );
        delete_transient( 'ai_composer_api_status' );
    }

    /**
     * Cancel any running AI generation jobs
     */
    private static function cancel_running_jobs() {
        global $wpdb;
        
        // Update any pending or in-progress jobs to cancelled status
        $runs_table = $wpdb->prefix . 'ai_composer_runs';
        
        $wpdb->update(
            $runs_table,
            array(
                'status' => 'cancelled',
                'error_message' => __( 'Cancelled due to plugin deactivation', 'ai-page-composer' ),
                'completed_at' => current_time( 'mysql' ),
            ),
            array(
                'status' => 'pending',
            ),
            array( '%s', '%s', '%s' ),
            array( '%s' )
        );
        
        $wpdb->update(
            $runs_table,
            array(
                'status' => 'cancelled',
                'error_message' => __( 'Cancelled due to plugin deactivation', 'ai-page-composer' ),
                'completed_at' => current_time( 'mysql' ),
            ),
            array(
                'status' => 'in_progress',
            ),
            array( '%s', '%s', '%s' ),
            array( '%s' )
        );
    }

    /**
     * Clear WordPress object cache
     */
    private static function clear_object_cache() {
        // Clear any cached data related to the plugin
        wp_cache_flush_group( 'ai_composer' );
        
        // Clear user meta cache for plugin-related data
        wp_cache_delete_multiple( 
            array(
                'ai_composer_preferences',
                'ai_composer_usage_stats',
                'ai_composer_last_generation',
            ),
            'user_meta'
        );
    }

    /**
     * Log deactivation event (if logging is enabled)
     */
    private static function log_deactivation() {
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( 
                sprintf(
                    '[AI Page Composer] Plugin deactivated at %s by user %d',
                    current_time( 'mysql' ),
                    get_current_user_id()
                )
            );
        }
    }
}