jQuery(document).ready(function($) {
    
    // Handle registration button clicks
    $('.mlf-register-btn').on('click', function(e) {
        e.preventDefault();
        
        var sessionId = $(this).data('session-id');
        var currentUrl = new URL(window.location);
        currentUrl.searchParams.set('action', 'register');
        currentUrl.searchParams.set('session_id', sessionId);
        window.location.href = currentUrl.toString();
    });
    
    // Handle details button clicks
    $('.mlf-details-btn').on('click', function(e) {
        e.preventDefault();
        
        var sessionId = $(this).data('session-id');
        var currentUrl = new URL(window.location);
        currentUrl.searchParams.set('action', 'details');
        currentUrl.searchParams.set('session_id', sessionId);
        window.location.href = currentUrl.toString();
    });

    // Handle registration from details page
    $(document).on('click', '.mlf-register-from-details', function(e) {
        e.preventDefault();
        
        var sessionId = $(this).data('session-id');
        var currentUrl = new URL(window.location);
        currentUrl.searchParams.set('action', 'register');
        currentUrl.searchParams.set('session_id', sessionId);
        window.location.href = currentUrl.toString();
    });
    
    // Handle modal close buttons
    $('.mlf-close').on('click', function() {
        $(this).closest('.mlf-modal').hide();
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('mlf-modal')) {
            $(e.target).hide();
        }
    });
    
    // Handle registration form submission
    $(document).on('submit', '#mlf-registration-form', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var $loading = $form.find('.mlf-loading');
        var $message = $('#mlf-registration-message');
        
        // Disable submit button and show loading
        $submitBtn.prop('disabled', true);
        $loading.show();
        $message.hide();
        
        // Prepare form data
        var formData = {
            action: 'mlf_register_for_session',
            session_id: $form.data('session-id'),
            player_name: $('#player_name').val(),
            player_email: $('#player_email').val(),
            player_phone: $('#player_phone').val(),
            experience_level: $('#experience_level').val(),
            character_name: $('#character_name').val(),
            character_class: $('#character_class').val(),
            special_requests: $('#special_requests').val(),
            dietary_restrictions: $('#dietary_restrictions').val(),
            mlf_registration_nonce: $form.find('[name="mlf_registration_nonce"]').val()
        };

        // Ajouter tous les champs personnalisés dynamiquement
        $form.find('[name^="custom_field_"]').each(function() {
            var $field = $(this);
            var fieldName = $field.attr('name');
            
            if ($field.attr('type') === 'checkbox') {
                formData[fieldName] = $field.is(':checked') ? $field.val() : '';
            } else {
                formData[fieldName] = $field.val();
            }
        });
        
        // Send AJAX request
        $.ajax({
            url: mlf_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                $submitBtn.prop('disabled', false);
                $loading.hide();
                
                if (response.success) {
                    $message
                        .removeClass('mlf-error')
                        .addClass('mlf-success')
                        .html('<p>' + response.data.message + '</p>')
                        .show();
                    
                    // Reset form after successful registration
                    $form[0].reset();
                    
                    // Update UI to reflect registration
                    updateSessionCard($form.data('session-id'));
                    
                    // Auto-close modal after a delay
                    setTimeout(function() {
                        $('#mlf-registration-modal').hide();
                    }, 3000);
                    
                } else {
                    $message
                        .removeClass('mlf-success')
                        .addClass('mlf-error')
                        .html('<p>' + (response.data.message || 'Erreur inconnue') + '</p>')
                        .show();
                }
            },
            error: function(xhr, status, error) {
                $submitBtn.prop('disabled', false);
                $loading.hide();
                
                $message
                    .removeClass('mlf-success')
                    .addClass('mlf-error')
                    .html('<p>Erreur de communication avec le serveur</p>')
                    .show();
            }
        });
    });
    
    /**
     * Show registration modal with form for specific session.
     */
    function showRegistrationModal(sessionId) {
        var $modal = $('#mlf-registration-modal');
        var $container = $('#mlf-registration-form-container');
        
        // Show loading in modal
        $container.html('<div class="mlf-loading-content">Chargement du formulaire...</div>');
        $modal.show();
        
        // Load registration form via AJAX
        $.ajax({
            url: mlf_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mlf_get_registration_form',
                session_id: sessionId,
                nonce: mlf_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $container.html(response.data.form_html);
                } else {
                    $container.html('<p class="mlf-error">Erreur lors du chargement du formulaire</p>');
                }
            },
            error: function() {
                $container.html('<p class="mlf-error">Erreur de communication</p>');
            }
        });
    }
    
    /**
     * Show details modal for specific session.
     */
    function showDetailsModal(sessionId) {
        var $modal = $('#mlf-details-modal');
        var $container = $('#mlf-session-details-container');
        
        // Show loading in modal
        $container.html('<div class="mlf-loading-content">Chargement des détails...</div>');
        $modal.show();
        
        // Load session details via AJAX
        $.ajax({
            url: mlf_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mlf_get_session_details',
                session_id: sessionId,
                nonce: mlf_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $container.html(response.data.details_html);
                } else {
                    $container.html('<p class="mlf-error">Erreur lors du chargement des détails</p>');
                }
            },
            error: function() {
                $container.html('<p class="mlf-error">Erreur de communication</p>');
            }
        });
    }
    
    /**
     * Update session card UI after registration.
     */
    function updateSessionCard(sessionId) {
        var $card = $('.mlf-session-card[data-session-id="' + sessionId + '"]');
        
        // Reload session data to get updated player count
        $.ajax({
            url: mlf_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mlf_get_session_card_data',
                session_id: sessionId,
                nonce: mlf_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update player count
                    $card.find('.mlf-session-players').html(
                        '<strong>Joueurs:</strong> ' + 
                        response.data.current_players + '/' + response.data.max_players
                    );
                    
                    // Update registration button if session is now full
                    if (response.data.current_players >= response.data.max_players) {
                        $card.find('.mlf-register-btn')
                            .replaceWith('<span class="mlf-btn mlf-btn-disabled">Complet</span>');
                    }
                }
            }
        });
    }
    
    // Filter functionality
    $('.mlf-filter-form select').on('change', function() {
        var $form = $(this).closest('form');
        var currentUrl = new URL(window.location);
        
        // Update URL parameters based on form values
        var gameType = $form.find('[name="filter_game_type"]').val();
        if (gameType) {
            currentUrl.searchParams.set('filter_game_type', gameType);
        } else {
            currentUrl.searchParams.delete('filter_game_type');
        }
        
        // Reload page with new filters
        window.location.href = currentUrl.toString();
    });
    
});