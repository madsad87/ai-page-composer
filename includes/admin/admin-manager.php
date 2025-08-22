<?php
/**
 * Admin Manager - WordPress Admin Interface Integration
 * 
 * This file handles all WordPress admin functionality including settings pages, admin menus, meta boxes,
 * and admin-specific scripts/styles. It provides a professional admin interface with proper form handling,
 * validation, and integration with WordPress admin patterns and user experience standards.
 *
 * Admin Manager class
 *
 * @package ModernWPPlugin
 */

namespace ModernWPPlugin\Admin;

/**
 * Admin Manager class
 */
class Admin_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( MODERN_WP_PLUGIN_PLUGIN_FILE ), array( $this, 'add_action_links' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'Modern WP Plugin Settings', 'modern-wp-plugin' ),
            __( 'Modern WP Plugin', 'modern-wp-plugin' ),
            'manage_options',
            'modern-wp-plugin',
            array( $this, 'settings_page' )
        );
    }

    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting(
            'modern_wp_plugin_settings',
            'modern_wp_plugin_options',
            array( $this, 'sanitize_options' )
        );

        add_settings_section(
            'modern_wp_plugin_general',
            __( 'General Settings', 'modern-wp-plugin' ),
            array( $this, 'general_section_callback' ),
            'modern-wp-plugin'
        );

        add_settings_field(
            'enable_feature_1',
            __( 'Enable Feature 1', 'modern-wp-plugin' ),
            array( $this, 'enable_feature_1_callback' ),
            'modern-wp-plugin',
            'modern_wp_plugin_general'
        );

        add_settings_field(
            'enable_feature_2',
            __( 'Enable Feature 2', 'modern-wp-plugin' ),
            array( $this, 'enable_feature_2_callback' ),
            'modern-wp-plugin',
            'modern_wp_plugin_general'
        );
    }

    /**
     * Sanitize options
     *
     * @param array $input Input options.
     * @return array Sanitized options.
     */
    public function sanitize_options( $input ) {
        $sanitized = array();

        if ( isset( $input['enable_feature_1'] ) ) {
            $sanitized['enable_feature_1'] = (bool) $input['enable_feature_1'];
        }

        if ( isset( $input['enable_feature_2'] ) ) {
            $sanitized['enable_feature_2'] = (bool) $input['enable_feature_2'];
        }

        return $sanitized;
    }

    /**
     * General section callback
     */
    public function general_section_callback() {
        echo '<p>' . __( 'Configure the general settings for the plugin.', 'modern-wp-plugin' ) . '</p>';
    }

    /**
     * Enable Feature 1 callback
     */
    public function enable_feature_1_callback() {
        $options = get_option( 'modern_wp_plugin_options' );
        $value = isset( $options['enable_feature_1'] ) ? $options['enable_feature_1'] : false;
        ?>
        <label>
            <input type="checkbox" name="modern_wp_plugin_options[enable_feature_1]" value="1" <?php checked( $value, true ); ?> />
            <?php esc_html_e( 'Enable this feature', 'modern-wp-plugin' ); ?>
        </label>
        <?php
    }

    /**
     * Enable Feature 2 callback
     */
    public function enable_feature_2_callback() {
        $options = get_option( 'modern_wp_plugin_options' );
        $value = isset( $options['enable_feature_2'] ) ? $options['enable_feature_2'] : false;
        ?>
        <label>
            <input type="checkbox" name="modern_wp_plugin_options[enable_feature_2]" value="1" <?php checked( $value, true ); ?> />
            <?php esc_html_e( 'Enable this experimental feature', 'modern-wp-plugin' ); ?>
        </label>
        <?php
    }

    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Modern WP Plugin Settings', 'modern-wp-plugin' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'modern_wp_plugin_settings' );
                do_settings_sections( 'modern-wp-plugin' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'settings_page_modern-wp-plugin' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'modern-wp-plugin-admin',
            MODERN_WP_PLUGIN_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MODERN_WP_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'modern-wp-plugin-admin',
            MODERN_WP_PLUGIN_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            MODERN_WP_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Add action links
     *
     * @param array $links Existing action links.
     * @return array Modified action links.
     */
    public function add_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'options-general.php?page=modern-wp-plugin' ) . '">' . __( 'Settings', 'modern-wp-plugin' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
}