<?php
/**
 * Alt Text Editor Module
 *
 * @package Bricks_Accessibility_Checker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Alt Text Editor class
 */
class BAC_Alt_Text_Editor {

 /**
 * Initialize the module
 */
public function __construct() {
    // Don't run in Bricks Builder editing interface
    if (isset($_GET['bricks']) && $_GET['bricks'] === 'run') {
        return;
    }
    
    // Only run for users who can edit media
    if (!current_user_can('upload_files')) {
        return;
    }

    // Add AJAX endpoints
    add_action('wp_ajax_update_attachment_alt_text', array($this, 'update_attachment_alt_text_handler'));
    add_action('wp_ajax_get_attachment_id_from_url', array($this, 'get_attachment_id_from_url_handler'));
    
    // Add frontend scripts and styles
    add_action('wp_footer', array($this, 'add_alt_text_editor_script'), 100);
    add_action('wp_footer', array($this, 'add_alt_text_editor_js'), 101);
}

    /**
     * AJAX handler to update alt text
     */
    public function update_attachment_alt_text_handler() {
        // Verify permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        // Get parameters
        $attachment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $alt_text = isset($_POST['alt_text']) ? sanitize_text_field($_POST['alt_text']) : '';
        
        // Verify attachment exists
        if (!get_post($attachment_id)) {
            wp_send_json_error(array('message' => 'Attachment not found'));
            return;
        }
        
        // Update alt text in database
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        
        // Return success
        wp_send_json_success(array('message' => 'Alt text updated successfully'));
    }

    /**
     * AJAX handler to get attachment ID from URL
     */
    public function get_attachment_id_from_url_handler() {
        // Verify permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        // Get image URL
        $image_url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        
        if (empty($image_url)) {
            wp_send_json_error(array('message' => 'No URL provided'));
            return;
        }
        
        // Try to get attachment ID
        $attachment_id = $this->get_attachment_id_from_url($image_url);
        
        if ($attachment_id) {
            // Get current alt text
            $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            
            wp_send_json_success(array(
                'id' => $attachment_id,
                'alt_text' => $alt_text
            ));
        } else {
            wp_send_json_error(array('message' => 'Attachment not found'));
        }
    }

    /**
     * Helper function to get attachment ID from URL
     */
    private function get_attachment_id_from_url($url) {
        // Try WordPress core function first
        $attachment_id = attachment_url_to_postid($url);
        
        if ($attachment_id) {
            return $attachment_id;
        }
        
        // If core function fails, try with cleaned URL (removes size suffix)
        $clean_url = preg_replace('/-\d+x\d+(\.[a-zA-Z0-9]+)$/', '$1', $url);
        if ($clean_url !== $url) {
            $attachment_id = attachment_url_to_postid($clean_url);
            if ($attachment_id) {
                return $attachment_id;
            }
        }
        
        // Last resort: try database query
        global $wpdb;
        
        // Get upload directory info
        $uploads = wp_get_upload_dir();
        $upload_dir = $uploads['baseurl'];
        
        // Check if URL contains upload path
        if (strpos($url, $upload_dir) !== false) {
            // Extract path relative to uploads directory
            $rel_path = str_replace($upload_dir, '', $url);
            $rel_path = preg_replace('/-\d+x\d+(\.[a-zA-Z0-9]+)$/', '$1', $rel_path);
            
            // Search in database
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = '_wp_attached_file' 
                AND meta_value LIKE %s",
                '%' . $wpdb->esc_like(basename($rel_path))
            ));
        }
        
        return (int) $attachment_id;
    }

    /**
     * Add CSS for alt text editor
     */
    public function add_alt_text_editor_script() {
        // Only for users who can edit media
        if (!current_user_can('upload_files')) {
            return;
        }
        
        ?>
        <style>
            .missing-alt-image {
                border: 3px solid red !important;
            }
            .alt-text-edit-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 115, 170, 0.3);
                opacity: 0;
                transition: opacity 0.2s;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
            }
            .alt-text-edit-overlay:hover {
                opacity: 1;
            }
            .alt-text-edit-button {
                background-color: #0073aa;
                color: white;
                border: none;
                border-radius: 3px;
                padding: 8px 12px;
                font-size: 14px;
                font-weight: bold;
                text-transform: uppercase;
                cursor: pointer;
            }
            .alt-text-modal {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background-color: white;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0 0 20px rgba(0,0,0,0.3);
                z-index: 999999;
                max-width: 500px;
                width: 90%;
            }
            .alt-text-modal h2 {
                margin-top: 0;
                color: #23282d;
            }
            .alt-text-modal img {
                max-width: 100%;
                max-height: 200px;
                display: block;
                margin: 10px 0;
            }
            .alt-text-modal input[type="text"] {
                width: 100%;
                padding: 8px;
                margin: 10px 0;
                border: 1px solid #ddd;
            }
            .alt-text-modal-buttons {
                margin-top: 15px;
                display: flex;
                justify-content: space-between;
            }
            .alt-text-modal-buttons button {
                padding: 8px 15px;
                border: none;
                border-radius: 3px;
                cursor: pointer;
            }
            .alt-text-save-button {
                background-color: #46b450;
                color: white;
            }
            .alt-text-cancel-button {
                background-color: #ddd;
                color: #333;
            }
            .alt-text-success-message {
                background-color: #46b450;
                color: white;
                padding: 10px;
                margin-top: 15px;
                border-radius: 3px;
                text-align: center;
            }
            .alt-text-error-message {
                background-color: #dc3232;
                color: white;
                padding: 10px;
                margin-top: 15px;
                border-radius: 3px;
                text-align: center;
            }
            .alt-text-overlay-missing {
                position: absolute;
                top: 0;
                right: 0;
                background-color: red;
                color: white;
                padding: 3px 6px;
                font-size: 10px;
                border-radius: 0 0 0 3px;
            }
            /* Debug Console Styles */
            #alt-text-debug-console {
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                max-height: 200px;
                overflow-y: auto;
                background-color: rgba(0, 0, 0, 0.8);
                color: #fff;
                font-family: monospace;
                font-size: 12px;
                z-index: 999999;
                padding: 10px;
                border-top: 2px solid #0073aa;
            }
            #alt-text-debug-console.collapsed {
                height: 30px;
                max-height: 30px;
                overflow: hidden;
            }
            #alt-text-debug-console-header {
                display: flex;
                justify-content: space-between;
                margin-bottom: 5px;
                padding-bottom: 5px;
                border-bottom: 1px solid #333;
                cursor: pointer;
            }
            #alt-text-debug-console-clear {
                background-color: #dc3232;
                color: white;
                border: none;
                padding: 2px 5px;
                border-radius: 2px;
                cursor: pointer;
                font-size: 10px;
            }
            .alt-text-log-entry {
                margin: 3px 0;
                padding: 2px 0;
                border-bottom: 1px dotted #333;
            }
            .alt-text-log-info {
                color: #7ad2ff;
            }
            .alt-text-log-success {
                color: #46b450;
            }
            .alt-text-log-error {
                color: #dc3232;
            }
            .alt-text-log-warning {
                color: #ffb900;
            }
            .alt-text-log-details {
                color: #aaa;
                margin-left: 20px;
                word-break: break-all;
            }
        </style>
        <?php
    }

    /**
     * Add JavaScript for alt text editor
     */
    public function add_alt_text_editor_js() {
        // Only for users who can edit media
        if (!current_user_can('upload_files')) {
            return;
        }
        
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize debug console
            const debugConsole = document.createElement('div');
            debugConsole.id = 'alt-text-debug-console';
            debugConsole.className = 'collapsed';
            debugConsole.innerHTML = `
                <div id="alt-text-debug-console-header">
                    <span>Alt Text Debug Console</span>
                    <button id="alt-text-debug-console-clear">Clear</button>
                </div>
                <div id="alt-text-debug-console-content"></div>
            `;
            document.body.appendChild(debugConsole);
            
            // Toggle console collapse
            document.getElementById('alt-text-debug-console-header').addEventListener('click', function(e) {
                if (e.target.id !== 'alt-text-debug-console-clear') {
                    debugConsole.classList.toggle('collapsed');
                }
            });
            
            // Clear console
            document.getElementById('alt-text-debug-console-clear').addEventListener('click', function() {
                document.getElementById('alt-text-debug-console-content').innerHTML = '';
            });
            
            // Logger function
            function logMessage(type, message, details = null) {
                // Log to browser console
                switch(type) {
                    case 'info':
                        console.info('Alt Text Editor:', message, details || '');
                        break;
                    case 'success':
                        console.log('Alt Text Editor:', message, details || '');
                        break;
                    case 'warning':
                        console.warn('Alt Text Editor:', message, details || '');
                        break;
                    case 'error':
                        console.error('Alt Text Editor:', message, details || '');
                        break;
                }
                
                // Log to debug console
                const logEntry = document.createElement('div');
                logEntry.className = 'alt-text-log-entry';
                
                const timestamp = new Date().toLocaleTimeString();
                logEntry.innerHTML = `<span class="alt-text-log-${type}">[${timestamp}] ${message}</span>`;
                
                if (details) {
                    let detailsStr = '';
                    if (typeof details === 'object') {
                        try {
                            detailsStr = JSON.stringify(details);
                        } catch (e) {
                            detailsStr = 'Object (cannot stringify)';
                        }
                    } else {
                        detailsStr = details.toString();
                    }
                    
                    const detailsElem = document.createElement('div');
                    detailsElem.className = 'alt-text-log-details';
                    detailsElem.textContent = detailsStr;
                    logEntry.appendChild(detailsElem);
                }
                
                const consoleContent = document.getElementById('alt-text-debug-console-content');
                consoleContent.appendChild(logEntry);
                consoleContent.scrollTop = consoleContent.scrollHeight;
                
                // Expand console on error/warning
                if (type === 'error' || type === 'warning') {
                    debugConsole.classList.remove('collapsed');
                }
            }
            
            // Start app
            logMessage('info', 'Alt Text Editor Starting...');
            
            // Get admin URL
            let adminUrl = '';
            const adminBar = document.getElementById('wpadminbar');
            if (adminBar) {
                const adminLinks = adminBar.querySelectorAll('a');
                for (let i = 0; i < adminLinks.length; i++) {
                    if (adminLinks[i].href && adminLinks[i].href.includes('/wp-admin/')) {
                        adminUrl = adminLinks[i].href.split('/wp-admin/')[0] + '/wp-admin/';
                        break;
                    }
                }
            }
            
            if (!adminUrl) {
                logMessage('error', 'Admin URL not found. Are you logged in as an administrator?');
                return;
            } else {
                logMessage('info', 'Admin URL found', adminUrl);
            }
            
            // Get all images
            const images = document.querySelectorAll('img');
            logMessage('info', 'Found ' + images.length + ' images on page');
            
            // Count missing alt text
            let missingAltCount = 0;
            
            // Process each image
            images.forEach(function(img, index) {
                // Skip admin bar images
                if (img.closest('#wpadminbar')) {
                    return;
                }
                
                // Check if image already has our overlay
                if (img.parentNode.classList && img.parentNode.classList.contains('alt-text-image-container')) {
                    return;
                }
                
                // Log image info
                logMessage('info', `Processing image #${index + 1}`, {
                    src: img.src,
                    alt: img.alt || '(empty)',
                    width: img.width,
                    height: img.height,
                    class: img.className
                });
                
                // Create container
                const container = document.createElement('div');
                container.style.position = 'relative';
                container.style.display = 'inline-block';
                container.className = 'alt-text-image-container';
                
                // Replace image with container
                try {
                    const parent = img.parentNode;
                    parent.insertBefore(container, img);
                    container.appendChild(img);
                } catch (e) {
                    logMessage('error', `Failed to wrap image #${index + 1}`, e.message);
                    return;
                }
                
                // Check if image has no alt text
                const missingAlt = !img.hasAttribute('alt') || img.getAttribute('alt').trim() === '';
                if (missingAlt) {
                    img.classList.add('missing-alt-image');
                    missingAltCount++;
                    
                    logMessage('warning', `Image #${index + 1} is missing alt text`);
                    
                    // Add "Missing" label
                    const missingLabel = document.createElement('div');
                    missingLabel.className = 'alt-text-overlay-missing';
                    missingLabel.textContent = 'MISSING ALT';
                    container.appendChild(missingLabel);
                }
                
                // Add overlay with edit button
                const overlay = document.createElement('div');
                overlay.className = 'alt-text-edit-overlay';
                
                const button = document.createElement('button');
                button.className = 'alt-text-edit-button';
                button.textContent = missingAlt ? 'ADD ALT TEXT' : 'EDIT ALT TEXT';
                
                overlay.appendChild(button);
                container.appendChild(overlay);
                
                // Handle click to edit alt text
                overlay.addEventListener('click', function() {
                    const imageUrl = img.getAttribute('src');
                    const currentAlt = img.getAttribute('alt') || '';
                    
                    logMessage('info', 'Clicked edit button for image', imageUrl);
                    
                    // Extract filename from URL for display
                    const filename = imageUrl.split('/').pop();
                    logMessage('info', 'Image filename: ' + filename);
                    
                    // Check if image is in WordPress media library
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', adminUrl + 'admin-ajax.php');
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                logMessage('info', 'AJAX response received', response);
                                
                                if (response.success) {
                                    // Image found in media library
                                    const attachmentId = response.data.id;
                                    const serverAltText = response.data.alt_text;
                                    
                                    logMessage('success', `Found attachment ID: ${attachmentId}`, {
                                        altText: serverAltText
                                    });
                                    
                                    // Create modal
                                    createEditModal(img, imageUrl, serverAltText, attachmentId);
                                } else {
                                    // Image not found in media library
                                    logMessage('error', 'Image not found in media library', response.data);
                                    alert('This image could not be found in the WordPress media library. Only images uploaded to this WordPress site can have their alt text updated.');
                                }
                            } catch (e) {
                                logMessage('error', 'Failed to parse AJAX response', {
                                    error: e.message,
                                    response: xhr.responseText.substring(0, 500) + '...' // Only log first 500 chars to avoid huge logs
                                });
                                alert('An error occurred. Please try again.');
                            }
                        } else {
                            logMessage('error', 'AJAX request failed', {
                                status: xhr.status,
                                response: xhr.responseText.substring(0, 500) + '...'
                            });
                            alert('Failed to check image in media library. Please try again.');
                        }
                    };
                    
                    xhr.onerror = function() {
                        logMessage('error', 'AJAX request failed (network error)');
                        alert('Network error. Please check your connection and try again.');
                    };
                    
                    logMessage('info', 'Sending AJAX request to get image ID', imageUrl);
                    xhr.send('action=get_attachment_id_from_url&url=' + encodeURIComponent(imageUrl));
                });
            });
            
            logMessage('info', `Scan complete. Found ${missingAltCount} images without alt text`);
            
            // Function to create edit modal
            function createEditModal(img, imageUrl, altText, attachmentId) {
                logMessage('info', 'Creating edit modal for image', {
                    id: attachmentId,
                    url: imageUrl,
                    altText: altText
                });
                
                // Create modal
                const modal = document.createElement('div');
                modal.className = 'alt-text-modal';
                
                // Set modal content
                modal.innerHTML = `
                    <h2>Edit Alt Text</h2>
                    <p>Update the alternative text for this image. Good alt text describes the image content for screen readers and helps with SEO.</p>
                    <img src="${imageUrl}" alt="${altText || ''}">
                    <label for="alt-text-input">Alt Text:</label>
                    <input type="text" id="alt-text-input" value="${altText || ''}" placeholder="Describe this image...">
                    <div class="alt-text-modal-buttons">
                        <button class="alt-text-save-button">Save Changes</button>
                        <button class="alt-text-cancel-button">Cancel</button>
                    </div>
                `;
                
                // Add modal to document
                document.body.appendChild(modal);
                
                // Focus input
                setTimeout(() => {
                    modal.querySelector('#alt-text-input').focus();
                }, 100);
                
                // Cancel button handler
                modal.querySelector('.alt-text-cancel-button').addEventListener('click', function() {
                    logMessage('info', 'Edit cancelled');
                    document.body.removeChild(modal);
                });
                
                // Save button handler
                modal.querySelector('.alt-text-save-button').addEventListener('click', function() {
                    const newAltText = modal.querySelector('#alt-text-input').value;
                    
                    logMessage('info', 'Saving new alt text', {
                        id: attachmentId,
                        oldText: altText,
                        newText: newAltText
                    });
                    
                    // Update database via AJAX
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', adminUrl + 'admin-ajax.php');
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                logMessage('info', 'Save response received', response);
                                
                                if (response.success) {
                                    // Update image on page
                                    img.setAttribute('alt', newAltText);
                                    
                                    logMessage('success', 'Alt text updated successfully', {
                                        id: attachmentId,
                                        newText: newAltText
                                    });
                                    
                                    // Update UI
                                    if (newAltText) {
                                        img.classList.remove('missing-alt-image');
                                        
                                        // Remove "Missing" label if present
                                        const missingLabel = img.parentNode.querySelector('.alt-text-overlay-missing');
                                        if (missingLabel) {
                                            img.parentNode.removeChild(missingLabel);
                                        }
                                        
                                        // Update button text
                                        const button = img.parentNode.querySelector('.alt-text-edit-button');
                                        if (button) {
                                            button.textContent = 'EDIT ALT TEXT';
                                        }
                                    } else {
                                        img.classList.add('missing-alt-image');
                                        
                                        // Add "Missing" label if not present
                                        if (!img.parentNode.querySelector('.alt-text-overlay-missing')) {
                                            const missingLabel = document.createElement('div');
                                            missingLabel.className = 'alt-text-overlay-missing';
                                            missingLabel.textContent = 'MISSING ALT';
                                            img.parentNode.appendChild(missingLabel);
                                        }
                                        
                                        // Update button text
                                        const button = img.parentNode.querySelector('.alt-text-edit-button');
                                        if (button) {
                                            button.textContent = 'ADD ALT TEXT';
                                        }
                                    }
                                    
                                    // Show success message
                                    const successMessage = document.createElement('div');
                                    successMessage.className = 'alt-text-success-message';
                                    successMessage.textContent = 'Alt text updated successfully!';
                                    
                                    modal.querySelector('.alt-text-modal-buttons').before(successMessage);
                                    
                                    // Close modal after delay
                                    setTimeout(function() {
                                        document.body.removeChild(modal);
                                    }, 1500);
                                } else {
                                    logMessage('error', 'Failed to update alt text', response.data);
                                    
                                    // Show error message
                                    const errorMessage = document.createElement('div');
                                    errorMessage.className = 'alt-text-error-message';
                                    errorMessage.textContent = response.data.message || 'An error occurred. Please try again.';
                                    
                                    modal.querySelector('.alt-text-modal-buttons').before(errorMessage);
                                }
                            } catch (e) {
                                logMessage('error', 'Error parsing save response', {
                                    error: e.message,
                                    response: xhr.responseText.substring(0, 500) + '...'
                                });
                                
                                // Show error message
                                const errorMessage = document.createElement('div');
                                errorMessage.className = 'alt-text-error-message';
                                errorMessage.textContent = 'An error occurred. Please try again.';
                                
                                modal.querySelector('.alt-text-modal-buttons').before(errorMessage);
                            }
                        } else {
                            logMessage('error', 'Save request failed', {
                                status: xhr.status,
                                response: xhr.responseText.substring(0, 500) + '...'
                            });
                            
                            // Show error message
                            const errorMessage = document.createElement('div');
                            errorMessage.className = 'alt-text-error-message';
                            errorMessage.textContent = 'Server error. Please try again.';
                            
                            modal.querySelector('.alt-text-modal-buttons').before(errorMessage);
                        }
                    };
                    
                    xhr.onerror = function() {
                        logMessage('error', 'Save request failed (network error)');
                        
                        // Show error message
                        const errorMessage = document.createElement('div');
                        errorMessage.className = 'alt-text-error-message';
                        errorMessage.textContent = 'Network error. Please check your connection and try again.';
                        
                        modal.querySelector('.alt-text-modal-buttons').before(errorMessage);
                    };
                    
                    logMessage('info', 'Sending save request', {
                        id: attachmentId,
                        newText: newAltText
                    });
                    xhr.send('action=update_attachment_alt_text&id=' + encodeURIComponent(attachmentId) + '&alt_text=' + encodeURIComponent(newAltText));
                });
                
                // Close on escape key
                document.addEventListener('keydown', function escapeHandler(e) {
                    if (e.key === 'Escape') {
                        logMessage('info', 'Modal closed with Escape key');
                        document.body.removeChild(modal);
                        document.removeEventListener('keydown', escapeHandler);
                    }
                });
            }
        });
        </script>
        <?php
    }
}
