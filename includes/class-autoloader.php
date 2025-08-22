<?php
/**
 * Autoloader Class - PSR-4 Compatible Class Loading
 * 
 * This file implements a PSR-4 compatible autoloader for the plugin's namespace. It automatically loads
 * classes on-demand when they are first referenced, eliminating the need for manual require statements.
 * The autoloader maps the ModernWPPlugin namespace to the includes directory structure.
 *
 * Autoloader class
 *
 * @package ModernWPPlugin
 */

namespace ModernWPPlugin;

/**
 * Autoloader class for the plugin
 */
class Autoloader {

    /**
     * Register the autoloader
     */
    public static function register() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    /**
     * Autoload classes
     *
     * @param string $class_name The class name.
     */
    public static function autoload( $class_name ) {
        if ( strpos( $class_name, 'ModernWPPlugin\\' ) !== 0 ) {
            return;
        }

        $class_name = str_replace( 'ModernWPPlugin\\', '', $class_name );
        $class_name = str_replace( '\\', DIRECTORY_SEPARATOR, $class_name );
        
        $file_path = MODERN_WP_PLUGIN_PLUGIN_DIR . 'includes/' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
        
        if ( file_exists( $file_path ) ) {
            require_once $file_path;
        }
    }
}