/**
 * Alt Text Editor JavaScript
 * Highlights images missing alt text and provides interface to add it
 */
(function($) {
    // [SECTION:DOCUMENT_READY] Run when page is loaded
    $(document).ready(function() {
        // Find all images on the page
        $('img').each(function() {
            var $img = $(this);
            var altText = $img.attr('alt');
            var src = $img.attr('src');
            
            // If alt attribute is missing or empty
            if (!altText || altText === '') {
                // Add red border to highlight the issue
                $img.addClass('bac-missing-alt');
                
                // Create edit button
                var $editButton = $('<button>', {
                    'class': 'bac-edit-alt-button',
                    'text': 'Add Alt Text',
                    'data-src': src
                });
                
                // Get attachment ID from classes (wp-image-123)
                var attachmentIdClass = $img.attr('class') ? $img.attr('class').match(/wp-image-(\d+)/) : null;
                var attachmentId = attachmentIdClass ? attachmentIdClass[1] : null;
                
                if (attachmentId) {
                    $editButton.attr('data-attachment-id', attachmentId);
                    
                    // Add button after image
                    $img.after($editButton);
                    
                    // Wrap both in a container for positioning
                    $img.parent().css('position', 'relative');
                }
            }
        });
        
        // [SECTION:EDIT_BUTTON] Handle edit button click
        $(document).on('click', '.bac-edit-alt-button', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var attachmentId = $button.data('attachment-id');
            var $img = $button.prev('img');
            
            // Create edit form
            var $form = $('<div>', {
                'class': 'bac-alt-text-form'
            }).append(
                $('<label>', {
                    'for': 'bac-alt-text-input-' + attachmentId,
                    'text': 'Alt Text Description:'
                }),
                $('<input>', {
                    'type': 'text',
                    'id': 'bac-alt-text-input-' + attachmentId,
                    'class': 'bac-alt-text-input',
                    'placeholder': 'Describe this image'
                }),
                $('<div>', {
                    'class': 'bac-alt-text-buttons'
                }).append(
                    $('<button>', {
                        'class': 'bac-alt-text-save',
                        'text': 'Save'
                    }),
                    $('<button>', {
                        'class': 'bac-alt-text-cancel',
                        'text': 'Cancel'
                    })
                )
            );
            
            // Replace button with form
            $button.after($form);
            $button.hide();
            
            // Focus the input
            $('#bac-alt-text-input-' + attachmentId).focus();
            
            // [SECTION:SAVE_BUTTON] Handle save button click
            $form.find('.bac-alt-text-save').on('click', function() {
                var newAltText = $form.find('.bac-alt-text-input').val();
                
                // Send AJAX request to update alt text
                $.ajax({
                    url: bacAltTextEditor.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'bac_update_alt_text',
                        nonce: bacAltTextEditor.nonce,
                        attachment_id: attachmentId,
                        alt_text: newAltText
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update image alt attribute
                            $img.attr('alt', newAltText);
                            
                            // Remove red border
                            $img.removeClass('bac-missing-alt');
                            
                            // Remove form and button
                            $form.remove();
                            $button.remove();
                            
                            // Show success message
                            var $success = $('<div>', {
                                'class': 'bac-alt-text-success',
                                'text': 'Alt text updated!'
                            });
                            
                            $img.after($success);
                            
                            // Hide success message after 3 seconds
                            setTimeout(function() {
                                $success.fadeOut(400, function() {
                                    $success.remove();
                                });
                            }, 3000);
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                    }
                });
            });
            
            // [SECTION:CANCEL_BUTTON] Handle cancel button click
            $form.find('.bac-alt-text-cancel').on('click', function() {
                $form.remove();
                $button.show();
            });
        });
    });
    // [END:DOCUMENT_READY]
})(jQuery);
