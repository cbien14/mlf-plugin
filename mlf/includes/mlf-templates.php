<?php
/**
 * Template functions for displaying game events - Version Corrigée
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display a single game event - Version sécurisée
 */
function mlf_display_game_event($post_id = null) {
    // Vérifier que WordPress est complètement chargé
    if (!function_exists('get_post') || !function_exists('esc_html')) {
        return false;
    }
    
    if (!$post_id) {
        global $post;
        if (!$post) return false;
        $post_id = $post->ID;
    }
    
    // Vérifier que la classe MLF_Game_Events existe
    if (!class_exists('MLF_Game_Events')) {
        return false;
    }
    
    $event_details = MLF_Game_Events::get_event_details($post_id);
    $post = get_post($post_id);
    
    if (!$post) return false;
    
    ?>
    <div class="mlf-game-event mlf-event-<?php echo $post_id; ?>">
        <div class="mlf-game-event-header">
            <h2 class="mlf-game-event-title"><?php echo esc_html($post->post_title); ?></h2>
            <span class="mlf-game-event-type">Murder</span>
        </div>
        
        <div class="mlf-game-event-details">
            <?php if (!empty($event_details['event_date'])): ?>
                <div class="mlf-event-detail">
                    <span class="mlf-event-detail-icon">📅</span>
                    <span class="mlf-event-detail-label"><?php esc_html_e('Date:', 'mlf'); ?></span>
                    <span><?php echo esc_html(date('F j, Y', strtotime($event_details['event_date']))); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($event_details['location'])): ?>
                <div class="mlf-event-detail">
                    <span class="mlf-event-detail-icon">📍</span>
                    <span class="mlf-event-detail-label"><?php esc_html_e('Location:', 'mlf'); ?></span>
                    <span><?php echo esc_html($event_details['location']); ?></span>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="mlf-game-event-actions">
            <button class="mlf-register-button" data-event-id="<?php echo $post_id; ?>">
                <?php esc_html_e('Register for Event', 'mlf'); ?>
            </button>
        </div>
    </div>
    <?php
    
    return true;
}

/**
 * Get simple registration form
 */
function mlf_get_simple_registration_form($event_id) {
    if (!function_exists('wp_nonce_field')) {
        return false;
    }
    
    ob_start();
    ?>
    <form class="mlf-registration-form" data-event-id="<?php echo esc_attr($event_id); ?>">
        <?php wp_nonce_field('mlf_event_registration', 'mlf_registration_nonce'); ?>
        <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
        
        <div class="mlf-form-field">
            <label><?php esc_html_e('Name:', 'mlf'); ?></label>
            <input type="text" name="player_name" required>
        </div>
        
        <div class="mlf-form-field">
            <label><?php esc_html_e('Email:', 'mlf'); ?></label>
            <input type="email" name="player_email" required>
        </div>
        
        <button type="submit"><?php esc_html_e('Register', 'mlf'); ?></button>
    </form>
    <?php
    return ob_get_clean();
}

?>
