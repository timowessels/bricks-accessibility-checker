<?php
/**
 * Plugin Name: Bricks Accessibility Checker
 * Description: Accessibility checker and fixer for Bricks Builder
 * Version: 1.0.0
 * Author: Timo Wessels
 * Text Domain: bricks-accessibility-checker
 */

// [SECTION:SECURITY] Keeps the file secure
if (!defined('ABSPATH')) {
    exit;
}
// [END:SECURITY]

// [SECTION:CONSTANTS] Important plugin information
define('BAC_VERSION', '1.0.0');
define('BAC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BAC_PLUGIN_URL', plugin_dir_url(__FILE__));
// [END:CONSTANTS]

// [SECTION:INCLUDES] Loads the alt-text editor file
// We only include files that actually exist
require_once BAC_PLUGIN_DIR . 'modules/alt-text-editor/class-alt-text-editor.php';
// [END:INCLUDES]

// [SECTION:MAIN_CLASS] Main plugin controller
class Bricks_Accessibility_Checker {
    private static $instance = null;
    public $alt_text_editor;
    private $settings = array();
    
    // [SECTION:SINGLETON] Creates only one plugin instance
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    // [END:SINGLETON]
    
    // [SECTION:CONSTRUCTOR] Sets up the plugin when it starts
    private function __construct() {
        // Load settings
        $this->load_settings();
        
        // Initialize modules based on settings
        add_action('init', array($this, 'init_modules'));
        
        // Admin-specific initialization
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        }
    }
    // [END:CONSTRUCTOR]
    
    // [SECTION:SETTINGS] Handles plugin settings
    private function load_settings() {
        // Default settings if nothing is saved yet
        $default_settings = array(
            'enable_alt_text_editor' => 1, // 1 = enabled, 0 = disabled
        );
        
        // Get any saved settings from the database
        $saved_settings = get_option('bac_settings', array());
        
        // Combine saved settings with defaults
        $this->settings = wp_parse_args($saved_settings, $default_settings);
    }
    
    public function register_settings() {
        register_setting('bac_settings_group', 'bac_settings');
    }
    // [END:SETTINGS]
    
    // [SECTION:MODULES] Starts plugin features
    public function init_modules() {
        // Start Alt Text Editor if enabled
        if ($this->settings['enable_alt_text_editor'] && class_exists('BAC_Alt_Text_Editor')) {
            $this->alt_text_editor = new BAC_Alt_Text_Editor();
        }
    }
    // [END:MODULES]
    
    // [SECTION:ADMIN_SCRIPTS] Loads CSS for admin page
    public function admin_enqueue_scripts($hook) {
        // Only load on our plugin page
        if ($hook !== 'toplevel_page_bricks-accessibility') {
            return;
        }
        
        // Add toggle switch CSS
        wp_enqueue_style(
            'bac-admin-style',
            BAC_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            BAC_VERSION
        );
    }
    // [END:ADMIN_SCRIPTS]
    
    // [SECTION:ADMIN_MENU] Creates admin menu item
    public function add_admin_menu() {
        add_menu_page(
            __('Bricks Accessibility', 'bricks-accessibility-checker'),
            __('Bricks A11y', 'bricks-accessibility-checker'),
            'manage_options',
            'bricks-accessibility',
            array($this, 'render_admin_page'),
            'dashicons-universal-access',
            100
        );
    }
    // [END:ADMIN_MENU]
    
    // [SECTION:ADMIN_PAGE] Creates settings page with toggles
    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Bricks Accessibility Checker', 'bricks-accessibility-checker'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('bac_settings_group'); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php echo esc_html__('Enable Alt Text Editor', 'bricks-accessibility-checker'); ?></th>
                        <td>
                            <label class="bac-switch">
                                <input type="checkbox" name="bac_settings[enable_alt_text_editor]" value="1" <?php checked(1, $this->settings['enable_alt_text_editor'], true); ?>>
                                <span class="bac-slider"></span>
                            </label>
                            <p class="description"><?php echo esc_html__('Shows missing alt text on images and allows editing them on the frontend.', 'bricks-accessibility-checker'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    // [END:ADMIN_PAGE]
}
// [END:MAIN_CLASS]

// [SECTION:INITIALIZATION] Starts the plugin
function bricks_accessibility_checker() {
    return Bricks_Accessibility_Checker::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'bricks_accessibility_checker');
// [END:INITIALIZATION]
