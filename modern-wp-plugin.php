<?php
/**
 * Main Plugin File - Entry Point
 * 
 * This file serves as the primary entry point for the WordPress plugin. It defines the plugin metadata,
 * initializes the autoloader, sets up activation/deactivation hooks, and bootstraps the main plugin class.
 * All plugin constants are defined here and the core plugin initialization is triggered.
 *
 * Plugin Name: Modern WordPress Plugin Template
 * Plugin URI: https://yourwebsite.com/plugins/modern-wp-plugin
 * Description: A modern, secure, and performant WordPress plugin template following industry best practices.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: modern-wp-plugin
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 *
 * @package ModernWPPlugin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'MODERN_WP_PLUGIN_VERSION', '1.0.0' );
define( 'MODERN_WP_PLUGIN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MODERN_WP_PLUGIN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MODERN_WP_PLUGIN_PLUGIN_FILE', __FILE__ );
define( 'MODERN_WP_PLUGIN_TEXT_DOMAIN', 'modern-wp-plugin' );

// Autoloader
require_once MODERN_WP_PLUGIN_PLUGIN_DIR . 'includes/class-autoloader.php';
ModernWPPlugin\Autoloader::register();

// Initialize the plugin
add_action( 'plugins_loaded', array( 'ModernWPPlugin\Core\Plugin', 'init' ) );

// Activation and deactivation hooks
register_activation_hook( __FILE__, array( 'ModernWPPlugin\Core\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ModernWPPlugin\Core\Deactivator', 'deactivate' ) );