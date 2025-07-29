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
            // Check if we're in Bricks Builder (not just preview)
            if ($this->is_bricks_builder()) {
                return; // Don't run in Bricks Builder
            }
            
            // Add scripts and styles
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            
            // Add AJAX handlers
            add_action('wp_ajax_bac_update_alt_text', array($this, 'ajax_update_alt_text'));
            add_action('wp_ajax_bac_get_attachment_id', array($this, 'ajax_get_attachment_id'));
        }
    }
    // [END:CONSTRUCTOR]
    
    // [SECTION:BRICKS_DETECTION] Detect if we're in Bricks Builder
    private function is_bricks_builder() {
        // Check if we're in Bricks Builder (not just preview mode)
        return isset($_GET['bricks']) && $_GET['bricks'] === 'run';
    }
    // [END:BRICKS_DETECTION]
    
    // [SECTION:SCRIPTS] Add necessary JavaScript and CSS
    public function enqueue_scripts() {
        // Add inline JavaScript in footer
        add_action('wp_footer', array($this, 'add_inline_script'));
        
        // Add inline CSS in footer
        add_action('wp_footer', array($this, 'add_inline_styles'));
    }
    // [END:SCRIPTS]
    
    // [SECTION:INLINE_STYLES] Add CSS styles to the footer
    public function add_inline_styles() {
        ?>
        <style>
            .bac-missing-alt {
                border: 3px solid red !important;
            }
            .bac-alt-text-image-container {
                position: relative;
                display: inline-block;
            }
            .bac-alt-text-edit-overlay {
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
            .bac-alt-text-edit-overlay:hover {
                opacity: 1;
            }
            .bac-alt-text-edit-button {
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
            .bac-alt-text-modal {
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
            .bac-alt-text-modal h2 {
                margin-top: 0;
                color: #23282d;
            }
            .bac-alt-text-modal img {
                max-width: 100%;
                max-height: 200px;
                display: block;
                margin: 10px 0;
            }
            .bac-alt-text-modal input[type="text"] {
                width: 100%;
                padding: 8px;
                margin: 10px 0;
                border: 1px solid #ddd;
            }
            .bac-alt-text-modal-buttons {
                margin-top: 15px;
                display: flex;
                justify-content: space-between;
            }
            .bac-alt-text-modal-buttons button {
                padding: 8px 15px;
                border: none;
                border-radius: 3px;
                cursor: pointer;
            }
            .bac-alt-text-save-button {
                background-color: #46b450;
                color: white;
            }
            .bac-alt-text-cancel-button {
                background-color: #ddd;
                color: #333;
            }
            .bac-alt-text-success-message {
                background-color: #46b450;
                color: white;
                padding: 10px;
                margin-top: 15px;
                border-radius: 3px;
                text-align: center;
            }
            .bac-alt-text-error-message {
                background-color: #dc3232;
                color: white;
                padding: 10px;
                margin-top: 15px;
                border-radius: 3px;
                text-align: center;
            }
            .bac-alt-text-overlay-missing {
                position: absolute;
                top: 0;
                right: 0;
                background-color: red;
                color: white;
                padding: 3px 6px;
                font-size: 10px;
                border-radius: 0 0 0 3px;
            }
        </style>
        <?php
    }
    // [END:INLINE_STYLES]
    
    // [SECTION:INLINE_SCRIPT] Add JavaScript to the footer
    public function add_inline_script() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('BAC Alt Text Editor initialized');
            
            // Find all images on the page
            $('img').each(function() {
                var $img = $(this);
                var altText = $img.attr('alt');
                var src = $img.attr('src');
                
                // Skip if image is in admin bar
                if ($img.closest('#wpadminbar').length) {
                    return;
                }
                
                // Skip if image already has our overlay
                if ($img.parent().hasClass('bac-alt-text-image-container')) {
                    return;
                }
                
                // Create container
                var $container = $('<div class="bac-alt-text-image-container"></div>');
                
                // Replace image with container
                try {
                    $img.wrap($container);
                    $container = $img.parent();
                } catch (e) {
                    console.error('Failed to wrap image', e);
                    return;
                }
                
                // Check if image has no alt text
                var missingAlt = !$img.attr('alt') || $img.attr('alt').trim() === '';
                if (missingAlt) {
                    $img.addClass('bac-missing-alt');
                    
                    // Add "Missing" label
                    var $missingLabel = $('<div class="bac-alt-text-overlay-missing">MISSING ALT</div>');
                    $container.append($missingLabel);
                }
                
                // Add overlay with edit button
                var $overlay = $('<div class="bac-alt-text-edit-overlay"></div>');
                var $button = $('<button class="bac-alt-text-edit-button"></button>');
                $button.text(missingAlt ? 'ADD ALT TEXT' : 'EDIT ALT TEXT');
                
                $overlay.append($button);
                $container.append($overlay);
                
                // Handle click to edit alt text
                $overlay.on('click', function() {
                    var imageUrl = $img.attr('src');
                    var currentAlt = $img.attr('alt') || '';
                    
                    // Try to get attachment ID from class
                    var attachmentId = 0;
                    var classes = $img.attr('class') || '';
                    var matches = classes.match(/wp-image-(\d+)/);
                    
                    if (matches && matches[1]) {
                        attachmentId = matches[1];
                        showEditModal();
                    } else {
                        // Try to get attachment ID via AJAX
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'bac_get_attachment_id',
                                url: imageUrl,
                                nonce: '<?php echo wp_create_nonce('bac_alt_text_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success && response.data.id) {
                                    attachmentId = response.data.id;
                                    showEditModal();
                                } else {
                                    alert('This image could not be found in the WordPress media library.');
                                }
                            },
                            error: function() {
                                alert('Error checking image. Please try again.');
                            }
                        });
                    }
                    
                    function showEditModal() {
                        // Create modal
                        var $modal = $('<div class="bac-alt-text-modal"></div>');
                        
                        // Set modal content
                        $modal.html(`
                            <h2>Edit Alt Text</h2>
                            <p>Update the alternative text for this image. Good alt text describes the image content for screen readers and helps with SEO.</p>
                            <img src="${imageUrl}" alt="${currentAlt}">
                            <label for="bac-alt-text-input">Alt Text:</label>
                            <input type="text" id="bac-alt-text-input" value="${currentAlt}" placeholder="Describe this image...">
                            <div class="bac-alt-text-modal-buttons">
                                <button class="bac-alt-text-save-button">Save Changes</button>
                                <button class="bac-alt-text-cancel-button">Cancel</button>
                            </div>
                        `);
                        
                        // Add modal to document
                        $('body').append($modal);
                        
                        // Focus input
                        setTimeout(function() {
                            $('#bac-alt-text-input').focus();
                        }, 100);
                        
                        // Cancel button handler
                        $modal.find('.bac-alt-text-cancel-button').on('click', function() {
                            $modal.remove();
                        });
                        
                        // Save button handler
                        $modal.find('.bac-alt-text-save-button').on('click', function() {
                            var newAltText = $('#bac-alt-text-input').val();
                            
                            // Update database via AJAX
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'bac_update_alt_text',
                                    id: attachmentId,
                                    alt_text: newAltText,
                                    nonce: '<?php echo wp_create_nonce('bac_alt_text_nonce'); ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        // Update image on page
                                        $img.attr('alt', newAltText);
                                        
                                        // Update UI
                                        if (newAltText) {
                                            $img.removeClass('bac-missing-alt');
                                            $container.find('.bac-alt-text-overlay-missing').remove();
                                            $button.text('EDIT ALT TEXT');
                                        } else {
                                            $img.addClass('bac-missing-alt');
                                            if ($container.find('.bac-alt-text-overlay-missing').length === 0) {
                                                $container.append('<div class="bac-alt-text-overlay-missing">MISSING ALT</div>');
                                            }
                                            $button.text('ADD ALT TEXT');
                                        }
                                        
                                        // Show success message
                                        var $successMessage = $('<div class="bac-alt-text-success-message">Alt text updated successfully!</div>');
                                        $modal.find('.bac-alt-text-modal-buttons').before($successMessage);
                                        
                                        // Close modal after delay
                                        setTimeout(function() {
                                            $modal.remove();
                                        }, 1500);
                                    } else {
                                        // Show error message
                                        var $errorMessage = $('<div class="bac-alt-text-error-message">Error updating alt text. Please try again.</div>');
                                        $modal.find('.bac-alt-text-modal-buttons').before($errorMessage);
                                    }
                                },
                                error: function() {
                                    // Show error message
                                    var $errorMessage = $('<div class="bac-alt-text-error-message">Server error. Please try again.</div>');
                                    $modal.find('.bac-alt-text-modal-buttons').before($errorMessage);
                                }
                            });
                        });
                        
                        // Close on escape key
                        $(document).on('keydown.bacAltTextModal', function(e) {
                            if (e.key === 'Escape') {
                                $modal.remove();
                                $(document).off('keydown.bacAltTextModal');
                            }
                        });
                    }
                });
            });
        });
        </script>
        <?php
    }
    // [END:INLINE_SCRIPT]
    
    // [SECTION:AJAX_GET_ID] Get attachment ID from URL via AJAX
    public function ajax_get_attachment_id() {
        // Check nonce
        check_ajax_referer('bac_alt_text_nonce', 'nonce');
        
        // Get URL
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        
        if (empty($url)) {
            wp_send_json_error(array('message' => 'URL is empty'));
            exit;
        }
        
        // Try to get attachment ID
        $attachment_id = attachment_url_to_postid($url);
        
        // If not found, try cleaned URL (without dimensions)
        if (!$attachment_id) {
            $clean_url = preg_replace('/-\d+x\d+(\.[a-zA-Z0-9]+)$/', '$1', $url);
            if ($clean_url !== $url) {
                $attachment_id = attachment_url_to_postid($clean_url);
            }
        }
        
        if ($attachment_id) {
            wp_send_json_success(array('id' => $attachment_id));
        } else {
            wp_send_json_error(array('message' => 'Attachment not found'));
        }
        
        exit;
    }
    // [END:AJAX_GET_ID]
    
    // [SECTION:AJAX_UPDATE] Update alt text via AJAX
    public function ajax_update_alt_text() {
        // Check nonce
        check_ajax_referer('bac_alt_text_nonce', 'nonce');
        
        // Get params
        $attachment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $alt_text = isset($_POST['alt_text']) ? sanitize_text_field($_POST['alt_text']) : '';
        
        // Check if attachment exists
        if (!get_post($attachment_id)) {
            wp_send_json_error(array('message' => 'Attachment not found'));
            exit;
        }
        
        // Update alt text
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        
        wp_send_json_success(array('message' => 'Alt text updated'));
        exit;
    }
    // [END:AJAX_UPDATE]
}
// [END:CLASS_DEFINITION]
