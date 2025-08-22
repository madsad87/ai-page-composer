<?php
/**
 * Core Plugin Class - Central Orchestrator and Singleton Manager
 * 
 * This file contains the main plugin class that serves as the central orchestrator for all plugin functionality.
 * It implements the singleton pattern, manages component initialization, handles WordPress hooks, and coordinates
 * between different managers (Admin, Fields, Blocks, API). This is the heart of the plugin architecture.
 *
 * Main plugin class
 *
 * @package ModernWPPlugin
 */

namespace ModernWPPlugin\Core;

use ModernWPPlugin\Admin\Admin_Manager;
use ModernWPPlugin\Fields\Field_Manager;
use ModernWPPlugin\Blocks\Block_Manager;
use ModernWPPlugin\API\API_Manager;

/**
 * Main plugin class
 */
class Plugin {

    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private static $instance = null;

    /**
     * Admin manager instance
     *
     * @var Admin_Manager
     */
    public $admin;

    /**
     * Field manager instance
     *
     * @var Field_Manager
     */
    public $fields;

    /**
     * Block manager instance
     *
     * @var Block_Manager
     */
    public $blocks;

    /**
     * API manager instance
     *
     * @var API_Manager
     */
    public $api;

    /**
     * Initialize the plugin
     */
    public static function init() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_textdomain();
        $this->init_hooks();
        $this->init_components();
    }

    /**
     * Load plugin textdomain
     */
    private function load_textdomain() {
        load_plugin_textdomain(
            MODERN_WP_PLUGIN_TEXT_DOMAIN,
            false,
            dirname( plugin_basename( MODERN_WP_PLUGIN_PLUGIN_FILE ) ) . '/languages'
        );
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'init', array( $this, 'init_plugin' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        $this->admin  = new Admin_Manager();
        $this->fields = new Field_Manager();
        $this->blocks = new Block_Manager();
        $this->api    = new API_Manager();
    }

    /**
     * Initialize plugin on WordPress init
     */
    public function init_plugin() {
        // Plugin initialization logic
        do_action( 'modern_wp_plugin_init' );
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'modern-wp-plugin-style',
            MODERN_WP_PLUGIN_PLUGIN_URL . 'assets/css/style.css',
            array(),
            MODERN_WP_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'modern-wp-plugin-script',
            MODERN_WP_PLUGIN_PLUGIN_URL . 'assets/js/script.js',
            array( 'jquery' ),
            MODERN_WP_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Get plugin instance
     *
     * @return Plugin
     */
    public static function get_instance() {
        return self::$instance;
    }
}