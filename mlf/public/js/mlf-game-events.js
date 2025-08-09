/* MLF Game Events JavaScript */

jQuery(document).ready(function($) {
    
    // Game event registration functionality
    $('.mlf-register-button').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var eventId = button.data('event-id');
        
        if (button.hasClass('mlf-register-form-toggle')) {
            // Toggle registration form
            var form = $('.mlf-registration-form');
            form.slideToggle();
            
            var buttonText = form.is(':visible') ? 'Hide Registration Form' : 'Register for Event';
            button.text(buttonText);
        } else {
            // Handle quick registration (if user is logged in)
            handleEventRegistration(eventId);
        }
    });
    
    // Registration form submission
    $('.mlf-registration-form form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var eventId = form.find('input[name="event_id"]').val();
        var formData = form.serialize();
        
        // Show loading state
        var submitButton = form.find('input[type="submit"]');
        var originalText = submitButton.val();
        submitButton.val('Registering...').prop('disabled', true);
        
        // Send AJAX request
        $.ajax({
            url: mlf_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mlf_register_for_event',
                nonce: mlf_ajax.nonce,
                event_id: eventId,
                form_data: formData
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showMessage('Registration successful!', 'success');
                    
                    // Update registration count
                    updateRegistrationCount(eventId, response.data.new_count);
                    
                    // Hide form
                    form.closest('.mlf-registration-form').slideUp();
                    
                    // Reset form
                    form[0].reset();
                } else {
                    showMessage(response.data.message || 'Registration failed. Please try again.', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred. Please try again.', 'error');
            },
            complete: function() {
                // Restore button state
                submitButton.val(originalText).prop('disabled', false);
            }
        });
    });
    
    // Handle event filtering
    $('.mlf-event-filter select').on('change', function() {
        var filter = $(this);
        var filterType = filter.data('filter-type');
        var filterValue = filter.val();
        
        filterEvents(filterType, filterValue);
    });
    
    // Handle event search
    $('.mlf-event-search input').on('input', debounce(function() {
        var searchTerm = $(this).val();
        searchEvents(searchTerm);
    }, 300));
    
    // Load more events (if pagination is used)
    $('.mlf-load-more-events').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var page = parseInt(button.data('page')) + 1;
        
        // Show loading state
        button.text('Loading...').prop('disabled', true);
        
        $.ajax({
            url: mlf_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mlf_load_more_events',
                nonce: mlf_ajax.nonce,
                page: page
            },
            success: function(response) {
                if (response.success) {
                    $('.mlf-event-archive').append(response.data.html);
                    button.data('page', page);
                    
                    if (!response.data.has_more) {
                        button.hide();
                    }
                } else {
                    showMessage('Failed to load more events.', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred while loading events.', 'error');
            },
            complete: function() {
                button.text('Load More Events').prop('disabled', false);
            }
        });
    });
    
    // Calendar integration
    $('.mlf-add-to-calendar').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var eventData = {
            title: button.data('title'),
            date: button.data('date'),
            time: button.data('time'),
            location: button.data('location'),
            description: button.data('description')
        };
        
        generateCalendarLink(eventData);
    });
    
    // Utility functions
    function handleEventRegistration(eventId) {
        $.ajax({
            url: mlf_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mlf_quick_register',
                nonce: mlf_ajax.nonce,
                event_id: eventId
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Registration successful!', 'success');
                    updateRegistrationCount(eventId, response.data.new_count);
                } else {
                    showMessage(response.data.message || 'Registration failed.', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred. Please try again.', 'error');
            }
        });
    }
    
    function updateRegistrationCount(eventId, newCount) {
        var countElement = $('.mlf-event-' + eventId + ' .mlf-current-registrations');
        countElement.text(newCount);
        
        // Update available spots
        var maxPlayers = parseInt(countElement.data('max-players'));
        var spotsAvailable = maxPlayers - newCount;
        var spotsElement = $('.mlf-event-' + eventId + ' .mlf-spots-available');
        spotsElement.text(spotsAvailable + ' spots available');
        
        // Disable registration if full
        if (spotsAvailable <= 0) {
            $('.mlf-event-' + eventId + ' .mlf-register-button')
                .prop('disabled', true)
                .text('Event Full');
        }
    }
    
    function filterEvents(filterType, filterValue) {
        $('.mlf-event-card').each(function() {
            var card = $(this);
            var shouldShow = true;
            
            if (filterValue && filterValue !== '') {
                var cardValue = card.data(filterType);
                shouldShow = (cardValue === filterValue);
            }
            
            if (shouldShow) {
                card.show();
            } else {
                card.hide();
            }
        });
    }
    
    function searchEvents(searchTerm) {
        $('.mlf-event-card').each(function() {
            var card = $(this);
            var title = card.find('.mlf-event-card-title').text().toLowerCase();
            var description = card.find('.mlf-event-card-excerpt').text().toLowerCase();
            var searchLower = searchTerm.toLowerCase();
            
            var shouldShow = (searchTerm === '') || 
                           title.includes(searchLower) || 
                           description.includes(searchLower);
            
            if (shouldShow) {
                card.show();
            } else {
                card.hide();
            }
        });
    }
    
    function showMessage(message, type) {
        var messageClass = type === 'success' ? 'mlf-message-success' : 'mlf-message-error';
        var messageHtml = '<div class="mlf-message ' + messageClass + '">' + message + '</div>';
        
        // Remove existing messages
        $('.mlf-message').remove();
        
        // Add new message
        $('.mlf-game-event, .mlf-event-archive').first().prepend(messageHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $('.mlf-message').fadeOut();
        }, 5000);
    }
    
    function generateCalendarLink(eventData) {
        var startDate = new Date(eventData.date + 'T' + eventData.time);
        var endDate = new Date(startDate.getTime() + (2 * 60 * 60 * 1000)); // Add 2 hours
        
        var googleCalendarUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE' +
            '&text=' + encodeURIComponent(eventData.title) +
            '&dates=' + formatDateForCalendar(startDate) + '/' + formatDateForCalendar(endDate) +
            '&location=' + encodeURIComponent(eventData.location) +
            '&details=' + encodeURIComponent(eventData.description);
        
        window.open(googleCalendarUrl, '_blank');
    }
    
    function formatDateForCalendar(date) {
        return date.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
    }
    
    function debounce(func, wait) {
        var timeout;
        return function executedFunction() {
            var context = this;
            var args = arguments;
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
});
