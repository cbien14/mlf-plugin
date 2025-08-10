<?php
/**
 * Template functions for displaying game events.
 */

// Add this to your theme's functions.php or create a template file

/**
 * Display a single game event.
 */
function mlf_display_game_event($post_id = null) {
    if (!$post_id) {
        global $post;
        $post_id = $post->ID;
    }
    
    $event_details = MLF_Game_Events::get_event_details($post_id);
    $post = get_post($post_id);
    
    ?>
    <div class="mlf-game-event mlf-event-<?php echo $post_id; ?>">
        <div class="mlf-game-event-header">
            <h2 class="mlf-game-event-title"><?php echo esc_html($post->post_title); ?></h2>
            <?php if (!empty($event_details['game_type'])): ?>
                <span class="mlf-game-event-type"><?php echo esc_html(ucwords(mlf_safe_str_replace('_', ' ', $event_details['game_type']))); ?></span>
            <?php endif; ?>
        </div>
        
        <div class="mlf-game-event-details">
            <?php if (!empty($event_details['event_date'])): ?>
                <div class="mlf-event-detail">
                    <span class="mlf-event-detail-icon">📅</span>
                    <span class="mlf-event-detail-label"><?php _e('Date:', 'mlf'); ?></span>
                    <span><?php echo esc_html(date('F j, Y', strtotime($event_details['event_date']))); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($event_details['event_time'])): ?>
                <div class="mlf-event-detail">
                    <span class="mlf-event-detail-icon">🕐</span>
                    <span class="mlf-event-detail-label"><?php _e('Time:', 'mlf'); ?></span>
                    <span><?php echo esc_html(date('g:i A', strtotime($event_details['event_time']))); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($event_details['location'])): ?>
                <div class="mlf-event-detail">
                    <span class="mlf-event-detail-icon">📍</span>
                    <span class="mlf-event-detail-label"><?php _e('Location:', 'mlf'); ?></span>
                    <span><?php echo esc_html($event_details['location']); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($event_details['difficulty_level'])): ?>
                <div class="mlf-event-detail">
                    <span class="mlf-event-detail-icon">⭐</span>
                    <span class="mlf-event-detail-label"><?php _e('Difficulty:', 'mlf'); ?></span>
                    <span class="mlf-difficulty-level mlf-difficulty-<?php echo esc_attr($event_details['difficulty_level']); ?>">
                        <?php echo esc_html(ucfirst($event_details['difficulty_level'])); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($post->post_content)): ?>
            <div class="mlf-event-description">
                <?php echo wp_kses_post($post->post_content); ?>
            </div>
        <?php endif; ?>
        
        <?php
        $registered_count = count($event_details['registered_players']);
        $max_players = intval($event_details['max_players']);
        $spots_available = $max_players - $registered_count;
        $registration_open = true;
        
        if (!empty($event_details['registration_deadline'])) {
            $deadline = strtotime($event_details['registration_deadline']);
            $registration_open = (time() < $deadline);
        }
        ?>
        
        <div class="mlf-registration-info">
            <div class="mlf-registration-status">
                <div>
                    <span class="mlf-event-detail-label"><?php _e('Registered Players:', 'mlf'); ?></span>
                    <span class="mlf-current-registrations" data-max-players="<?php echo $max_players; ?>"><?php echo $registered_count; ?></span>
                    <?php if ($max_players): ?>
                        / <?php echo $max_players; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($max_players && $spots_available > 0): ?>
                    <span class="mlf-spots-available"><?php echo $spots_available; ?> <?php _e('spots available', 'mlf'); ?></span>
                <?php elseif ($max_players && $spots_available <= 0): ?>
                    <span class="mlf-spots-full"><?php _e('Event Full', 'mlf'); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if ($registration_open && $spots_available > 0): ?>
                <button class="mlf-register-button mlf-register-form-toggle" data-event-id="<?php echo $post_id; ?>">
                    <?php _e('Register for Event', 'mlf'); ?>
                </button>
                
                <button class="mlf-add-to-calendar" 
                        data-title="<?php echo esc_attr($post->post_title); ?>"
                        data-date="<?php echo esc_attr($event_details['event_date']); ?>"
                        data-time="<?php echo esc_attr($event_details['event_time']); ?>"
                        data-location="<?php echo esc_attr($event_details['location']); ?>"
                        data-description="<?php echo esc_attr(wp_strip_all_tags($post->post_content)); ?>">
                    <?php _e('Add to Calendar', 'mlf'); ?>
                </button>
            <?php elseif (!$registration_open): ?>
                <p class="mlf-registration-closed"><?php _e('Registration is closed for this event.', 'mlf'); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="mlf-registration-form" style="display: none;">
            <h3><?php _e('Register for this Event', 'mlf'); ?></h3>
            <form method="post">
                <?php wp_nonce_field('mlf_event_registration', 'mlf_registration_nonce'); ?>
                <input type="hidden" name="event_id" value="<?php echo $post_id; ?>">
                
                <div class="mlf-form-group">
                    <label for="player_name"><?php _e('Your Name', 'mlf'); ?> *</label>
                    <input type="text" id="player_name" name="player_name" required>
                </div>
                
                <div class="mlf-form-group">
                    <label for="player_email"><?php _e('Email Address', 'mlf'); ?> *</label>
                    <input type="email" id="player_email" name="player_email" required>
                </div>
                
                <div class="mlf-form-group">
                    <label for="player_phone"><?php _e('Phone Number', 'mlf'); ?></label>
                    <input type="text" id="player_phone" name="player_phone">
                </div>
                
                <div class="mlf-form-group">
                    <label for="player_experience"><?php _e('Experience Level', 'mlf'); ?></label>
                    <select id="player_experience" name="player_experience">
                        <option value=""><?php _e('Select your experience', 'mlf'); ?></option>
                        <option value="beginner"><?php _e('Beginner', 'mlf'); ?></option>
                        <option value="intermediate"><?php _e('Intermediate', 'mlf'); ?></option>
                        <option value="advanced"><?php _e('Advanced', 'mlf'); ?></option>
                        <option value="expert"><?php _e('Expert', 'mlf'); ?></option>
                    </select>
                </div>
                
                <div class="mlf-form-group">
                    <label for="player_notes"><?php _e('Additional Notes', 'mlf'); ?></label>
                    <textarea id="player_notes" name="player_notes" placeholder="<?php _e('Any special requirements or questions?', 'mlf'); ?>"></textarea>
                </div>
                
                <div class="mlf-form-group">
                    <input type="submit" value="<?php _e('Submit Registration', 'mlf'); ?>" class="mlf-register-button">
                </div>
            </form>
        </div>
    </div>
    <?php
}

/**
 * Display game events archive.
 */
function mlf_display_game_events_archive($args = array()) {
    $defaults = array(
        'posts_per_page' => 12,
        'meta_key' => '_mlf_event_date',
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_compare' => '>=',
        'meta_value' => date('Y-m-d'),
    );
    
    $args = wp_parse_args($args, $defaults);
    $args['post_type'] = 'mlf_game_event';
    $args['post_status'] = 'publish';
    
    $events = new WP_Query($args);
    
    if ($events->have_posts()): ?>
        <div class="mlf-event-archive">
            <?php while ($events->have_posts()): $events->the_post(); ?>
                <?php mlf_display_game_event_card(get_the_ID()); ?>
            <?php endwhile; ?>
        </div>
        
        <?php if ($events->max_num_pages > 1): ?>
            <button class="mlf-load-more-events" data-page="1">
                <?php _e('Load More Events', 'mlf'); ?>
            </button>
        <?php endif; ?>
    <?php else: ?>
        <p class="mlf-no-events"><?php _e('No upcoming events found.', 'mlf'); ?></p>
    <?php endif;
    
    wp_reset_postdata();
}

/**
 * Display game event card for archive view.
 */
function mlf_display_game_event_card($post_id) {
    $event_details = MLF_Game_Events::get_event_details($post_id);
    $post = get_post($post_id);
    $registered_count = count($event_details['registered_players']);
    $max_players = intval($event_details['max_players']);
    
    ?>
    <div class="mlf-event-card" 
         data-game-type="<?php echo esc_attr($event_details['game_type']); ?>"
         data-difficulty="<?php echo esc_attr($event_details['difficulty_level']); ?>">
        
        <?php if (has_post_thumbnail($post_id)): ?>
            <img src="<?php echo get_the_post_thumbnail_url($post_id, 'medium'); ?>" 
                 alt="<?php echo esc_attr($post->post_title); ?>" 
                 class="mlf-event-card-image">
        <?php endif; ?>
        
        <div class="mlf-event-card-content">
            <h3 class="mlf-event-card-title">
                <a href="<?php echo get_permalink($post_id); ?>"><?php echo esc_html($post->post_title); ?></a>
            </h3>
            
            <div class="mlf-event-card-meta">
                <?php if (!empty($event_details['event_date'])): ?>
                    <span>📅 <?php echo esc_html(date('M j, Y', strtotime($event_details['event_date']))); ?></span>
                <?php endif; ?>
                
                <?php if (!empty($event_details['event_time'])): ?>
                    <span>🕐 <?php echo esc_html(date('g:i A', strtotime($event_details['event_time']))); ?></span>
                <?php endif; ?>
                
                <?php if (!empty($event_details['location'])): ?>
                    <span>📍 <?php echo esc_html($event_details['location']); ?></span>
                <?php endif; ?>
                
                <?php if ($max_players): ?>
                    <span>👥 <?php echo $registered_count; ?>/<?php echo $max_players; ?> <?php _e('players', 'mlf'); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if ($post->post_excerpt): ?>
                <div class="mlf-event-card-excerpt">
                    <?php echo esc_html($post->post_excerpt); ?>
                </div>
            <?php endif; ?>
            
            <a href="<?php echo get_permalink($post_id); ?>" class="mlf-read-more">
                <?php _e('Learn More & Register', 'mlf'); ?> →
            </a>
        </div>
    </div>
    <?php
}
