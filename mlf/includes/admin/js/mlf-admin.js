jQuery(document).ready(function($) {
    
    // Initialize WordPress media uploader
    var mediaUploader;
    
    // Handle image upload buttons
    $('.mlf-upload-image-btn').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var targetInput = button.data('target');
        var previewContainer = button.data('preview');
        
        // If the media frame already exists, reopen it
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        // Create the media frame
        mediaUploader = wp.media({
            title: 'Choisir une image',
            button: {
                text: 'Utiliser cette image'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        // When an image is selected
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            
            // Set the image URL in the hidden input
            $('#' + targetInput).val(attachment.url);
            
            // Show preview
            var preview = '<img src="' + attachment.url + '" style="max-width: 300px; height: auto; border: 1px solid #ddd; border-radius: 4px;" />';
            $('#' + previewContainer).html(preview);
            
            // Show remove button
            button.siblings('.mlf-remove-image-btn').show();
        });
        
        // Open the media frame
        mediaUploader.open();
    });
    
    // Handle custom forms field type changes
    $(document).on('change', '.field-type-select', function() {
        var fieldType = $(this).val();
        var optionsRow = $(this).closest('.form-field-item').find('.field-options-row');
        
        if (fieldType === 'select' || fieldType === 'radio') {
            optionsRow.show();
        } else {
            optionsRow.hide();
        }
    });
    
    // Initialize field type visibility on page load
    $('.field-type-select').each(function() {
        var fieldType = $(this).val();
        var optionsRow = $(this).closest('.form-field-item').find('.field-options-row');
        
        if (fieldType === 'select' || fieldType === 'radio') {
            optionsRow.show();
        } else {
            optionsRow.hide();
        }
    });
    
    // Filter custom forms based on game type selection
    $('#game_type').on('change', function() {
        var selectedGameType = $(this).val();
        var customFormSelect = $('#custom_form_id');
        
        // Show/hide form options based on game type compatibility
        customFormSelect.find('option').each(function() {
            var option = $(this);
            var formGameType = option.data('game-type');
            
            if (!formGameType || formGameType === 'all' || formGameType === selectedGameType) {
                option.show();
            } else {
                option.hide();
            }
        });
        
        // Reset selection if current selection is not compatible
        var currentSelection = customFormSelect.val();
        if (currentSelection) {
            var currentOption = customFormSelect.find('option[value="' + currentSelection + '"]');
            var currentGameType = currentOption.data('game-type');
            
            if (currentGameType && currentGameType !== 'all' && currentGameType !== selectedGameType) {
                customFormSelect.val('');
            }
        }
    });
    
    // Trigger initial filtering
    $('#game_type').trigger('change');
    
    // Handle image removal
    $('.mlf-remove-image-btn').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var targetInput = button.data('target');
        var previewContainer = button.data('preview');
        
        // Clear the input and preview
        $('#' + targetInput).val('');
        $('#' + previewContainer).html('');
        
        // Hide remove button
        button.hide();
    });
    
    // Initialize form validation and submission handlers
    // Additional functionality can be added here as needed
    
    // Handle session creation form submission via AJAX
    $('#mlf-session-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitButton = $('#mlf-submit-button');
        var $loading = $('#mlf-loading');
        var $messageDiv = $('#mlf-session-message');
        
        // Disable submit button and show loading
        $submitButton.prop('disabled', true);
        $loading.css('display', 'inline-block');
        $messageDiv.hide();
        
        // Prepare form data
        var formData = {
            action: 'mlf_create_session',
            session_name: $('#session_name').val(),
            game_type: $('#game_type').val(),
            session_date: $('#session_date').val(),
            session_time: $('#session_time').val(),
            duration_minutes: $('#duration_minutes').val(),
            max_players: $('#max_players').val(),
            location: $('#location').val(),
            difficulty_level: $('#difficulty_level').val(),
            description: $('#description').val(),
            synopsis: $('#synopsis').val(),
            trigger_warnings: $('#trigger_warnings').val(),
            safety_tools: $('#safety_tools').val(),
            prerequisites: $('#prerequisites').val(),
            additional_info: $('#additional_info').val(),
            banner_image_url: $('#banner_image_url').val(),
            background_image_url: $('#background_image_url').val(),
            is_public: $('#is_public').is(':checked') ? 1 : 0,
            requires_approval: $('#requires_approval').is(':checked') ? 1 : 0,
            registration_deadline: $('#registration_deadline').val(),
            mlf_session_nonce: $('#mlf_session_nonce').val()
        };
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                $submitButton.prop('disabled', false);
                $loading.hide();
                
                if (response.success) {
                    // Show success message
                    $messageDiv
                        .removeClass('notice-error')
                        .addClass('notice-success')
                        .html('<p>Session créée avec succès ! ID: ' + response.data.session_id + '</p>')
                        .show();
                    
                    // Reset form
                    $form[0].reset();
                    
                    // Clear image previews
                    $('.mlf-image-preview').html('');
                    $('.mlf-remove-image-btn').hide();
                    
                    // Optional: redirect to sessions list after a delay
                    setTimeout(function() {
                        window.location.href = 'admin.php?page=mlf-sessions';
                    }, 2000);
                    
                } else {
                    // Show error message
                    $messageDiv
                        .removeClass('notice-success')
                        .addClass('notice-error')
                        .html('<p>Erreur: ' + (response.data.message || 'Erreur inconnue') + '</p>')
                        .show();
                }
            },
            error: function(xhr, status, error) {
                $submitButton.prop('disabled', false);
                $loading.hide();
                
                $messageDiv
                    .removeClass('notice-success')
                    .addClass('notice-error')
                    .html('<p>Erreur de communication avec le serveur</p>')
                    .show();
            }
        });
    });
    
    // Handle session deletion
    $(document).on('click', '.mlf-delete-session', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var sessionId = $button.data('session-id');
        var $row = $button.closest('tr');
        
        if (!confirm('Êtes-vous sûr de vouloir supprimer cette session ? Cette action est irréversible.')) {
            return;
        }
        
        // Disable the button and show loading
        $button.prop('disabled', true).text('Suppression...');
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mlf_delete_session',
                session_id: sessionId,
                nonce: mlf_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Remove the row with animation
                    $row.fadeOut(500, function() {
                        $(this).remove();
                        
                        // Show success message
                        $('<div class="notice notice-success is-dismissible"><p>' + 
                          response.data.message + '</p></div>')
                          .insertAfter('.wrap h1')
                          .delay(3000)
                          .fadeOut();
                    });
                } else {
                    // Re-enable button and show error
                    $button.prop('disabled', false).text('Supprimer');
                    alert('Erreur: ' + (response.data.message || 'Erreur inconnue'));
                }
            },
            error: function(xhr, status, error) {
                // Re-enable button and show error
                $button.prop('disabled', false).text('Supprimer');
                alert('Erreur de communication avec le serveur');
            }
        });
    });
    
    // Handle session update form submission via AJAX
    $('#mlf-edit-session-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $updateButton = $('#mlf-update-button');
        var $loading = $('#mlf-loading');
        var $messageDiv = $('#mlf-session-message');
        var sessionId = $form.data('session-id');
        
        // Disable submit button and show loading
        $updateButton.prop('disabled', true);
        $loading.css('display', 'inline-block');
        $messageDiv.hide();
        
        // Prepare form data
        var formData = {
            action: 'mlf_update_session',
            session_id: sessionId,
            session_name: $('#session_name').val(),
            game_type: $('#game_type').val(),
            session_date: $('#session_date').val(),
            session_time: $('#session_time').val(),
            duration_minutes: $('#duration_minutes').val(),
            max_players: $('#max_players').val(),
            location: $('#location').val(),
            difficulty_level: $('#difficulty_level').val(),
            description: $('#description').val(),
            synopsis: $('#synopsis').val(),
            trigger_warnings: $('#trigger_warnings').val(),
            safety_tools: $('#safety_tools').val(),
            prerequisites: $('#prerequisites').val(),
            additional_info: $('#additional_info').val(),
            banner_image_url: $('#banner_image_url').val(),
            background_image_url: $('#background_image_url').val(),
            is_public: $('#is_public').is(':checked') ? 1 : 0,
            requires_approval: $('#requires_approval').is(':checked') ? 1 : 0,
            registration_deadline: $('#registration_deadline').val(),
            status: $('#status').val(),
            nonce: mlf_admin_ajax.nonce
        };
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                $updateButton.prop('disabled', false);
                $loading.hide();
                
                if (response.success) {
                    // Show success message
                    $messageDiv
                        .removeClass('notice-error')
                        .addClass('notice-success')
                        .html('<p>Session mise à jour avec succès !</p>')
                        .show();
                    
                    // Optional: redirect to sessions list after a delay
                    setTimeout(function() {
                        window.location.href = 'admin.php?page=mlf-sessions';
                    }, 2000);
                    
                } else {
                    // Show error message
                    $messageDiv
                        .removeClass('notice-success')
                        .addClass('notice-error')
                        .html('<p>Erreur: ' + (response.data.message || 'Erreur inconnue') + '</p>')
                        .show();
                }
            },
            error: function(xhr, status, error) {
                $updateButton.prop('disabled', false);
                $loading.hide();
                
                $messageDiv
                    .removeClass('notice-success')
                    .addClass('notice-error')
                    .html('<p>Erreur de communication avec le serveur</p>')
                    .show();
            }
        });
    });

});