<?php
// [SECTION:SECURITY] Keeps the file secure
if (!defined('ABSPATH')) {
    exit;
}
// [END:SECURITY]

// [SECTION:CLASS_DEFINITION] Alt Text Editor class
class BAC_Alt_Text_Editor {

    // [SECTION:CONSTRUCTOR] Sets up the editor
    public function __construct() {
        // Only run on frontend for logged-in users with upload capability
        if (!is_admin() && is_user_logged_in() && current_user_can('upload_files')) {
            // Add JavaScript to highlight missing alt text
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            
            // Add AJAX handler for updating alt text
            add_action('wp_ajax_bac_update_alt_text', array($this, 'ajax_update_alt_text'));
        }
    }
    // [END:CONSTRUCTOR]
    
    // [SECTION:SCRIPTS] Add necessary JavaScript and CSS
    public function enqueue_scripts() {
        // Enqueue our script
        wp_enqueue_script(
            'bac-alt-text-editor',
            BAC_PLUGIN_URL . 'modules/alt-text-editor/js/alt-text-editor.js',
            array('jquery'),
            BAC_VERSION,
            true
        );
        
        // Enqueue our styles
        wp_enqueue_style(
            'bac-alt-text-editor',
            BAC_PLUGIN_URL . 'modules/alt-text-editor/css/alt-text-editor.css',
            array(),
            BAC_VERSION
        );
        
        // Add WordPress AJAX URL and security nonce
        wp_localize_script('bac-alt-text-editor', 'bacAltTextEditor', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bac_alt_text_editor_nonce')
        ));
    }
    // [END:SCRIPTS]
    
    // [SECTION:AJAX_HANDLER] Process AJAX requests to update alt text
    public function ajax_update_alt_text() {
        // Check security nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bac_alt_text_editor_nonce')) {
            wp_send_json_error('Security check failed');
            exit;
        }
        
        // Get attachment ID and new alt text
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        $alt_text = isset($_POST['alt_text']) ? sanitize_text_field($_POST['alt_text']) : '';
        
        // Check if attachment exists
        if (!get_post($attachment_id)) {
            wp_send_json_error('Attachment not found');
            exit;
        }
        
        // Update the alt text
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        
        // Return success
        wp_send_json_success(array(
            'message' => 'Alt text updated successfully',
            'attachment_id' => $attachment_id,
            'alt_text' => $alt_text
        ));
        
        exit;
    }
    // [END:AJAX_HANDLER]
}
// [END:CLASS_DEFINITION]
