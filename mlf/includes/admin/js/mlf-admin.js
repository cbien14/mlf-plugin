jQuery(document).ready(function($) {
    
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
    
    // Handle session deletion (if applicable)
    $('.mlf-delete-session').on('click', function(e) {
        e.preventDefault();
        
        var sessionId = $(this).data('session-id');
        
        if (confirm('Êtes-vous sûr de vouloir supprimer cette session ?')) {
            // Add deletion AJAX logic here if needed
            console.log('Delete session ID:', sessionId);
        }
    });
    
});