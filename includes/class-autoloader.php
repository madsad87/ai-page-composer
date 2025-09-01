<?php
/**
 * Autoloader Class - PSR-4 Compatible Class Loading
 * 
 * This file implements a PSR-4 compatible autoloader for the AI Page Composer plugin namespace. 
 * It automatically loads classes on-demand when they are first referenced, eliminating the need 
 * for manual require statements. The autoloader maps the AIPageComposer namespace to the includes 
 * directory structure.
 *
 * Autoloader class
 *
 * @package AIPageComposer
 */

namespace AIPageComposer;

/**
 * Autoloader class for the AI Page Composer plugin
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
        if ( strpos( $class_name, 'AIPageComposer\\' ) !== 0 ) {
            return;
        }

        $class_name = str_replace( 'AIPageComposer\\', '', $class_name );
        $class_name = str_replace( '\\', DIRECTORY_SEPARATOR, $class_name );
        
        // Convert namespace structure to file path
        $file_path = AI_PAGE_COMPOSER_PLUGIN_DIR . 'includes/' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
        
        if ( file_exists( $file_path ) ) {
            require_once $file_path;
        }
    }
}