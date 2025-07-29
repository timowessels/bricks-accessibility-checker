<?php
/**
 * Plugin Name: Bricks Accessibility Checker
 * Description: Accessibility checker and fixer for Bricks Builder
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: bricks-accessibility-checker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BAC_VERSION', '1.0.0');
define('BAC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BAC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load alt text editor class from module folder
require_once BAC_PLUGIN_DIR . 'modules/alt-text-editor/class-alt-text-editor.php';

/**
 * Main plugin class
 */
class Bricks_Accessibility_Checker {
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Alt text editor instance
     */
    public $alt_text_editor;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Initialize alt text editor
        $this->alt_text_editor = new BAC_Alt_Text_Editor();
    }
}

// Initialize the plugin
function bricks_accessibility_checker() {
    return Bricks_Accessibility_Checker::get_instance();
}

// Start the plugin
bricks_accessibility_checker();