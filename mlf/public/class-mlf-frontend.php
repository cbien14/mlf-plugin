<?php
/**
 * The public-facing functionality of the plugin.
 */

class MLF_Frontend {

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        add_action('init', array($this, 'add_shortcodes'));
        add_action('wp_ajax_mlf_register_for_session', array($this, 'handle_session_registration'));
        add_action('wp_ajax_nopriv_mlf_register_for_session', array($this, 'handle_session_registration'));
    }

    /**
     * Register shortcodes.
     */
    public function add_shortcodes() {
        add_shortcode('mlf_sessions_list', array($this, 'display_sessions_list'));
        add_shortcode('mlf_session_details', array($this, 'display_session_details'));
        add_shortcode('mlf_registration_form', array($this, 'display_registration_form'));
    }

    /**
     * Enqueue public-facing stylesheets and scripts.
     */
    public function enqueue_public_scripts() {
        wp_enqueue_style('mlf-public-css', plugin_dir_url(dirname(__FILE__)) . 'public/css/mlf-public.css', array(), '1.0.0');
        wp_enqueue_script('mlf-public-js', plugin_dir_url(dirname(__FILE__)) . 'public/js/mlf-public.js', array('jquery'), '1.0.0', true);
        
        wp_localize_script('mlf-public-js', 'mlf_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mlf_public_nonce'),
        ));
    }

    /**
     * Display list of available sessions.
     */
    public function display_sessions_list($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'game_type' => '',
            'upcoming_only' => true
        ), $atts);

        $filters = array(
            'limit' => intval($atts['limit']),
            'is_public' => 1
        );

        if ($atts['upcoming_only']) {
            $filters['date_from'] = date('Y-m-d');
        }

        if (!empty($atts['game_type'])) {
            $filters['game_type'] = sanitize_text_field($atts['game_type']);
        }

        $sessions = MLF_Database_Manager::get_game_sessions($filters);

        ob_start();
        ?>
        <div class="mlf-sessions-list">
            <h2><?php _e('Sessions de jeu disponibles', 'mlf'); ?></h2>
            
            <div class="mlf-filters">
                <form method="get" class="mlf-filter-form">
                    <select name="filter_game_type" onchange="this.form.submit()">
                        <option value=""><?php _e('Tous les types de jeux', 'mlf'); ?></option>
                        <option value="jdr" <?php selected($_GET['filter_game_type'] ?? '', 'jdr'); ?>><?php _e('JDR', 'mlf'); ?></option>
                        <option value="murder" <?php selected($_GET['filter_game_type'] ?? '', 'murder'); ?>><?php _e('Murder', 'mlf'); ?></option>
                        <option value="jeu_de_societe" <?php selected($_GET['filter_game_type'] ?? '', 'jeu_de_societe'); ?>><?php _e('Jeu de société', 'mlf'); ?></option>
                    </select>
                </form>
            </div>

            <?php if (empty($sessions)): ?>
                <p class="mlf-no-sessions"><?php _e('Aucune session disponible pour le moment.', 'mlf'); ?></p>
            <?php else: ?>
                <div class="mlf-sessions-grid">
                    <?php foreach ($sessions as $session): ?>
                        <div class="mlf-session-card" data-session-id="<?php echo esc_attr($session['id']); ?>">
                            <div class="mlf-session-header">
                                <h3 class="mlf-session-title"><?php echo esc_html($session['session_name']); ?></h3>
                                <span class="mlf-game-type mlf-game-type-<?php echo esc_attr($session['game_type']); ?>">
                                    <?php echo esc_html($this->get_game_type_label($session['game_type'])); ?>
                                </span>
                            </div>
                            
                            <div class="mlf-session-meta">
                                <div class="mlf-session-date">
                                    <strong><?php _e('Date:', 'mlf'); ?></strong> 
                                    <?php echo esc_html(date_i18n('d/m/Y', strtotime($session['session_date']))); ?>
                                    <?php _e('à', 'mlf'); ?>
                                    <?php echo esc_html(date('H:i', strtotime($session['session_time']))); ?>
                                </div>
                                
                                <?php if (!empty($session['location'])): ?>
                                    <div class="mlf-session-location">
                                        <strong><?php _e('Lieu:', 'mlf'); ?></strong> 
                                        <?php echo esc_html($session['location']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mlf-session-players">
                                    <strong><?php _e('Joueurs:', 'mlf'); ?></strong> 
                                    <?php echo intval($session['current_players']); ?>/<?php echo intval($session['max_players']); ?>
                                </div>
                                
                                <div class="mlf-session-difficulty">
                                    <strong><?php _e('Niveau:', 'mlf'); ?></strong> 
                                    <?php echo esc_html($this->get_difficulty_label($session['difficulty_level'])); ?>
                                </div>
                            </div>

                            <?php if (!empty($session['description'])): ?>
                                <div class="mlf-session-description">
                                    <?php echo wp_kses_post(wpautop($session['description'])); ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($session['trigger_warnings'])): ?>
                                <div class="mlf-trigger-warnings">
                                    <strong><?php _e('⚠️ Trigger warnings:', 'mlf'); ?></strong>
                                    <div class="mlf-warnings-content">
                                        <?php echo wp_kses_post(wpautop($session['trigger_warnings'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mlf-session-actions">
                                <?php if (intval($session['current_players']) < intval($session['max_players'])): ?>
                                    <button class="mlf-btn mlf-btn-primary mlf-register-btn" data-session-id="<?php echo esc_attr($session['id']); ?>">
                                        <?php _e('S\'inscrire', 'mlf'); ?>
                                    </button>
                                <?php else: ?>
                                    <span class="mlf-btn mlf-btn-disabled"><?php _e('Complet', 'mlf'); ?></span>
                                <?php endif; ?>
                                
                                <button class="mlf-btn mlf-btn-secondary mlf-details-btn" data-session-id="<?php echo esc_attr($session['id']); ?>">
                                    <?php _e('Détails', 'mlf'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="mlf-registration-modal" class="mlf-modal" style="display: none;">
            <div class="mlf-modal-content">
                <span class="mlf-close">&times;</span>
                <h3><?php _e('Inscription à la session', 'mlf'); ?></h3>
                <div id="mlf-registration-form-container">
                    <!-- Le formulaire sera chargé ici via AJAX -->
                </div>
            </div>
        </div>

        <div id="mlf-details-modal" class="mlf-modal" style="display: none;">
            <div class="mlf-modal-content">
                <span class="mlf-close">&times;</span>
                <div id="mlf-session-details-container">
                    <!-- Les détails seront chargés ici via AJAX -->
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Display detailed session information.
     */
    public function display_session_details($atts) {
        $atts = shortcode_atts(array(
            'session_id' => 0
        ), $atts);

        $session_id = intval($atts['session_id']);
        if (!$session_id) {
            return '<p>' . __('ID de session invalide.', 'mlf') . '</p>';
        }

        $session = MLF_Database_Manager::get_game_session($session_id);
        if (!$session || !$session['is_public']) {
            return '<p>' . __('Session non trouvée ou non publique.', 'mlf') . '</p>';
        }

        ob_start();
        ?>
        <div class="mlf-session-details">
            <h2><?php echo esc_html($session['session_name']); ?></h2>
            
            <div class="mlf-session-info">
                <div class="mlf-info-grid">
                    <div class="mlf-info-item">
                        <strong><?php _e('Type de jeu:', 'mlf'); ?></strong>
                        <span class="mlf-game-type mlf-game-type-<?php echo esc_attr($session['game_type']); ?>">
                            <?php echo esc_html($this->get_game_type_label($session['game_type'])); ?>
                        </span>
                    </div>
                    
                    <div class="mlf-info-item">
                        <strong><?php _e('Date et heure:', 'mlf'); ?></strong>
                        <?php echo esc_html(date_i18n('d/m/Y', strtotime($session['session_date']))); ?>
                        <?php _e('à', 'mlf'); ?>
                        <?php echo esc_html(date('H:i', strtotime($session['session_time']))); ?>
                    </div>
                    
                    <div class="mlf-info-item">
                        <strong><?php _e('Durée:', 'mlf'); ?></strong>
                        <?php echo intval($session['duration_minutes']); ?> <?php _e('minutes', 'mlf'); ?>
                    </div>
                    
                    <?php if (!empty($session['location'])): ?>
                        <div class="mlf-info-item">
                            <strong><?php _e('Lieu:', 'mlf'); ?></strong>
                            <?php echo esc_html($session['location']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mlf-info-item">
                        <strong><?php _e('Niveau:', 'mlf'); ?></strong>
                        <?php echo esc_html($this->get_difficulty_label($session['difficulty_level'])); ?>
                    </div>
                    
                    <div class="mlf-info-item">
                        <strong><?php _e('Joueurs:', 'mlf'); ?></strong>
                        <?php echo intval($session['current_players']); ?>/<?php echo intval($session['max_players']); ?>
                    </div>
                    
                    <?php if (!empty($session['game_master_name'])): ?>
                        <div class="mlf-info-item">
                            <strong><?php _e('Maître de jeu:', 'mlf'); ?></strong>
                            <?php echo esc_html($session['game_master_name']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($session['description'])): ?>
                <div class="mlf-section">
                    <h3><?php _e('Description', 'mlf'); ?></h3>
                    <div class="mlf-content">
                        <?php echo wp_kses_post(wpautop($session['description'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($session['synopsis'])): ?>
                <div class="mlf-section">
                    <h3><?php _e('Synopsis', 'mlf'); ?></h3>
                    <div class="mlf-content">
                        <?php echo wp_kses_post(wpautop($session['synopsis'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($session['trigger_warnings'])): ?>
                <div class="mlf-section mlf-warnings-section">
                    <h3><?php _e('⚠️ Trigger warnings', 'mlf'); ?></h3>
                    <div class="mlf-content mlf-warnings">
                        <?php echo wp_kses_post(wpautop($session['trigger_warnings'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($session['safety_tools'])): ?>
                <div class="mlf-section">
                    <h3><?php _e('Outils de sécurité', 'mlf'); ?></h3>
                    <div class="mlf-content">
                        <?php echo wp_kses_post(wpautop($session['safety_tools'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($session['prerequisites'])): ?>
                <div class="mlf-section">
                    <h3><?php _e('Prérequis', 'mlf'); ?></h3>
                    <div class="mlf-content">
                        <?php echo wp_kses_post(wpautop($session['prerequisites'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($session['additional_info'])): ?>
                <div class="mlf-section">
                    <h3><?php _e('Informations additionnelles', 'mlf'); ?></h3>
                    <div class="mlf-content">
                        <?php echo wp_kses_post(wpautop($session['additional_info'])); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Display registration form for a session.
     */
    public function display_registration_form($atts) {
        $atts = shortcode_atts(array(
            'session_id' => 0
        ), $atts);

        $session_id = intval($atts['session_id']);
        if (!$session_id) {
            return '<p>' . __('ID de session invalide.', 'mlf') . '</p>';
        }

        $session = MLF_Database_Manager::get_game_session($session_id);
        if (!$session || !$session['is_public']) {
            return '<p>' . __('Session non trouvée ou non publique.', 'mlf') . '</p>';
        }

        ob_start();
        ?>
        <form id="mlf-registration-form" class="mlf-form" data-session-id="<?php echo esc_attr($session_id); ?>">
            <?php wp_nonce_field('mlf_register_session', 'mlf_registration_nonce'); ?>
            
            <div class="mlf-form-group">
                <label for="player_name"><?php _e('Nom complet *', 'mlf'); ?></label>
                <input type="text" id="player_name" name="player_name" required />
            </div>
            
            <div class="mlf-form-group">
                <label for="player_email"><?php _e('Email *', 'mlf'); ?></label>
                <input type="email" id="player_email" name="player_email" required />
            </div>
            
            <div class="mlf-form-group">
                <label for="player_phone"><?php _e('Téléphone', 'mlf'); ?></label>
                <input type="tel" id="player_phone" name="player_phone" />
            </div>
            
            <div class="mlf-form-group">
                <label for="experience_level"><?php _e('Niveau d\'expérience', 'mlf'); ?></label>
                <select id="experience_level" name="experience_level">
                    <option value="debutant"><?php _e('Débutant', 'mlf'); ?></option>
                    <option value="intermediaire"><?php _e('Intermédiaire', 'mlf'); ?></option>
                    <option value="avance"><?php _e('Avancé', 'mlf'); ?></option>
                    <option value="expert"><?php _e('Expert', 'mlf'); ?></option>
                </select>
            </div>
            
            <?php if ($session['game_type'] === 'jdr'): ?>
                <div class="mlf-form-group">
                    <label for="character_name"><?php _e('Nom du personnage', 'mlf'); ?></label>
                    <input type="text" id="character_name" name="character_name" />
                </div>
                
                <div class="mlf-form-group">
                    <label for="character_class"><?php _e('Classe/Type de personnage', 'mlf'); ?></label>
                    <input type="text" id="character_class" name="character_class" />
                </div>
            <?php endif; ?>
            
            <div class="mlf-form-group">
                <label for="special_requests"><?php _e('Demandes spéciales', 'mlf'); ?></label>
                <textarea id="special_requests" name="special_requests" rows="3"></textarea>
            </div>
            
            <div class="mlf-form-group">
                <label for="dietary_restrictions"><?php _e('Restrictions alimentaires', 'mlf'); ?></label>
                <textarea id="dietary_restrictions" name="dietary_restrictions" rows="2"></textarea>
            </div>
            
            <div class="mlf-form-actions">
                <button type="submit" class="mlf-btn mlf-btn-primary">
                    <?php _e('S\'inscrire', 'mlf'); ?>
                </button>
                <span class="mlf-loading" style="display: none;"><?php _e('Inscription en cours...', 'mlf'); ?></span>
            </div>
            
            <div id="mlf-registration-message" class="mlf-message" style="display: none;"></div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle session registration via AJAX.
     */
    public function handle_session_registration() {
        if (!wp_verify_nonce($_POST['mlf_registration_nonce'], 'mlf_register_session')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        $session_id = intval($_POST['session_id']);
        $session = MLF_Database_Manager::get_game_session($session_id);
        
        if (!$session || !$session['is_public']) {
            wp_send_json_error(array('message' => 'Session non trouvée ou non publique'));
        }

        // Check if session is full
        if (intval($session['current_players']) >= intval($session['max_players'])) {
            wp_send_json_error(array('message' => 'La session est complète'));
        }

        // Check if user is already registered
        $existing_registration = MLF_Database_Manager::get_player_registration_by_email(
            $session_id, 
            sanitize_email($_POST['player_email'])
        );
        
        if ($existing_registration) {
            wp_send_json_error(array('message' => 'Vous êtes déjà inscrit à cette session'));
        }

        $registration_data = array(
            'session_id' => $session_id,
            'player_name' => sanitize_text_field($_POST['player_name']),
            'player_email' => sanitize_email($_POST['player_email']),
            'player_phone' => sanitize_text_field($_POST['player_phone']),
            'experience_level' => sanitize_text_field($_POST['experience_level']),
            'character_name' => sanitize_text_field($_POST['character_name']),
            'character_class' => sanitize_text_field($_POST['character_class']),
            'special_requests' => sanitize_textarea_field($_POST['special_requests']),
            'dietary_restrictions' => sanitize_textarea_field($_POST['dietary_restrictions']),
            'registration_status' => $session['requires_approval'] ? 'en_attente' : 'confirme'
        );

        // Add user ID if logged in
        if (is_user_logged_in()) {
            $registration_data['user_id'] = get_current_user_id();
        }

        $registration_id = MLF_Database_Manager::create_player_registration($registration_data);

        if ($registration_id) {
            // Update session player count if auto-confirmed
            if (!$session['requires_approval']) {
                MLF_Database_Manager::update_session_player_count($session_id);
            }

            $message = $session['requires_approval'] 
                ? 'Inscription enregistrée ! Elle sera confirmée par l\'organisateur.'
                : 'Inscription confirmée !';
                
            wp_send_json_success(array(
                'registration_id' => $registration_id,
                'message' => $message
            ));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de l\'inscription'));
        }
    }

    /**
     * Get game type label.
     */
    private function get_game_type_label($type) {
        $labels = array(
            'jdr' => 'JDR',
            'murder' => 'Murder',
            'jeu_de_societe' => 'Jeu de société'
        );
        
        return isset($labels[$type]) ? $labels[$type] : $type;
    }

    /**
     * Get difficulty level label.
     */
    private function get_difficulty_label($level) {
        $labels = array(
            'debutant' => 'Débutant',
            'intermediaire' => 'Intermédiaire',
            'avance' => 'Avancé',
            'expert' => 'Expert'
        );
        
        return isset($labels[$level]) ? $labels[$level] : $level;
    }
}
