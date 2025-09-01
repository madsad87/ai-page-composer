<?php
/**
 * AI Page Composer - Main Plugin File
 * 
 * This file serves as the primary entry point for the AI Page Composer WordPress plugin. 
 * It defines the plugin metadata, initializes the autoloader, sets up activation/deactivation hooks,
 * and bootstraps the main plugin class. All plugin constants are defined here and the core 
 * plugin initialization is triggered.
 *
 * Plugin Name: AI Page Composer
 * Plugin URI: https://yourwebsite.com/plugins/ai-page-composer
 * Description: AI-powered page composition tool with block preferences, API integration, and intelligent content generation.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-page-composer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 *
 * @package AIPageComposer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'AI_PAGE_COMPOSER_VERSION', '1.0.0' );
define( 'AI_PAGE_COMPOSER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_PAGE_COMPOSER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_PAGE_COMPOSER_PLUGIN_FILE', __FILE__ );
define( 'AI_PAGE_COMPOSER_TEXT_DOMAIN', 'ai-page-composer' );
define( 'AI_PAGE_COMPOSER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader
require_once AI_PAGE_COMPOSER_PLUGIN_DIR . 'includes/class-autoloader.php';
AIPageComposer\Autoloader::register();

// Initialize the plugin
$ai_page_composer_plugin = null;
add_action( 'plugins_loaded', function() {
    global $ai_page_composer_plugin;
    $ai_page_composer_plugin = AIPageComposer\Core\Plugin::init();
} );

// Activation and deactivation hooks
register_activation_hook( __FILE__, array( 'AIPageComposer\Core\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AIPageComposer\Core\Deactivator', 'deactivate' ) );

// Plugin action links filter
add_filter( 'plugin_action_links_' . AI_PAGE_COMPOSER_PLUGIN_BASENAME, array( 'AIPageComposer\Admin\Admin_Manager', 'add_action_links' ) );