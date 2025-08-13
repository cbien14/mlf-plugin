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
    $('.mlf-details-btn, .mlf-session-details-btn').on('click', function(e) {
        e.preventDefault();
        
        var sessionId = $(this).data('session-id');
        
        // Si on est sur la page utilisateur (ID 18), rediriger vers la page des sessions (ID 13)
        var currentUrl = new URL(window.location);
        var isUserPage = currentUrl.searchParams.get('page_id') === '18' || window.location.pathname.includes('ma-page');
        
        if (isUserPage) {
            // Rediriger vers la page des sessions avec les détails
            var sessionsUrl = new URL(window.location.origin);
            sessionsUrl.searchParams.set('page_id', '13');
            sessionsUrl.searchParams.set('action', 'details');
            sessionsUrl.searchParams.set('session_id', sessionId);
            window.location.href = sessionsUrl.toString();
        } else {
            // Rester sur la page actuelle et ajouter les paramètres
            currentUrl.searchParams.set('action', 'details');
            currentUrl.searchParams.set('session_id', sessionId);
            window.location.href = currentUrl.toString();
        }
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
        var $message = $('#mlf-registration-result');
        
        // Disable submit button and show loading
        $submitBtn.prop('disabled', true);
        if ($loading.length) {
            $loading.show();
        }
        $message.hide();
        
        // Prepare form data - collecte automatique de tous les champs
        var formData = {
            action: 'mlf_register_for_session'
        };

        // Collecter tous les champs du formulaire dynamiquement
        $form.find('input, select, textarea').each(function() {
            var $field = $(this);
            var fieldName = $field.attr('name');
            
            // Ignorer les champs sans nom et les boutons de soumission
            if (!fieldName || $field.attr('type') === 'submit') {
                return;
            }
            
            if ($field.attr('type') === 'checkbox') {
                formData[fieldName] = $field.is(':checked') ? $field.val() : '';
            } else if ($field.attr('type') === 'radio') {
                if ($field.is(':checked')) {
                    formData[fieldName] = $field.val();
                }
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
                if ($loading.length) {
                    $loading.hide();
                }
                
                try {
                    // S'assurer que la réponse est un objet JSON
                    var jsonResponse = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (jsonResponse.success) {
                        $message
                            .removeClass('mlf-error')
                            .addClass('mlf-success')
                            .html('<p>' + jsonResponse.data.message + '</p>')
                            .show();
                        
                        // Reset form after successful registration
                        $form[0].reset();
                        
                        // Update UI to reflect registration - données de session mises à jour
                        if (jsonResponse.data.session) {
                            updateSessionUI(jsonResponse.data.session);
                        }
                        
                        // Rediriger vers la page de détails après un délai
                        setTimeout(function() {
                            var sessionId = $form.find('input[name="session_id"]').val();
                            if (sessionId) {
                                var currentUrl = new URL(window.location);
                                currentUrl.searchParams.set('action', 'details');
                                currentUrl.searchParams.set('session_id', sessionId);
                                window.location.href = currentUrl.toString();
                            }
                        }, 2000);
                        
                    } else {
                        $message
                            .removeClass('mlf-success')
                            .addClass('mlf-error')
                            .html('<p>' + (jsonResponse.data.message || 'Erreur inconnue') + '</p>')
                            .show();
                    }
                } catch (e) {
                    // Si la réponse n'est pas du JSON valide, l'afficher telle quelle
                    $message
                        .removeClass('mlf-success')
                        .addClass('mlf-error')
                        .html('<p>Erreur de traitement de la réponse: ' + response.substring(0, 200) + '</p>')
                        .show();
                }
            },
            error: function(xhr, status, error) {
                $submitBtn.prop('disabled', false);
                if ($loading.length) {
                    $loading.hide();
                }
                
                $message
                    .removeClass('mlf-success')
                    .addClass('mlf-error')
                    .html('<p>Erreur de communication avec le serveur: ' + status + '</p>')
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

    /**
     * Update session UI with new data after registration.
     */
    function updateSessionUI(sessionData) {
        // Mettre à jour le compteur de joueurs sur la page de détails
        $('.mlf-meta-item').each(function() {
            var $item = $(this);
            if ($item.find('strong').text().includes('Joueurs:')) {
                $item.html(
                    '<strong>Joueurs:</strong> ' + 
                    sessionData.current_players + '/' + sessionData.max_players
                );
            }
        });

        // Mettre à jour le bouton d'inscription si la session est maintenant complète
        if (sessionData.is_full) {
            $('.mlf-session-actions .mlf-btn-primary').replaceWith(
                '<span class="mlf-btn mlf-btn-disabled mlf-btn-large">Session complète</span>'
            );
        }

        // Mettre à jour les cartes de session sur la page principale si présentes
        $('.mlf-session-card').each(function() {
            var $card = $(this);
            var cardSessionId = $card.data('session-id');
            
            if (cardSessionId == sessionData.session_id) {
                // Mettre à jour le compteur de joueurs
                $card.find('.mlf-session-players').html(
                    '<strong>Joueurs:</strong> ' + 
                    sessionData.current_players + '/' + sessionData.max_players
                );
                
                // Mettre à jour le bouton si complet
                if (sessionData.is_full) {
                    $card.find('.mlf-register-btn')
                        .replaceWith('<span class="mlf-btn mlf-btn-disabled">Complet</span>');
                }
            }
        });

        console.log('Interface mise à jour après inscription:', sessionData);
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