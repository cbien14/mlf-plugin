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
        // Hooks AJAX pour l'inscription aux sessions (uniquement pour utilisateurs connectés)
        add_action('wp_ajax_mlf_register_session', array($this, 'handle_session_registration'));
        add_action('wp_ajax_mlf_register_for_session', array($this, 'handle_session_registration'));
        // Note: Pas de hooks nopriv car les utilisateurs doivent être connectés
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
        // Gérer les différentes vues selon les paramètres URL
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
        
        switch ($action) {
            case 'details':
                if ($session_id) {
                    return $this->display_session_details_page($session_id);
                }
                break;
                
            case 'register':
                if ($session_id) {
                    return $this->display_registration_page($session_id);
                }
                break;
        }
        
        // Vue par défaut : liste des sessions
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
                                <?php 
                                $user_registration = false;
                                if (is_user_logged_in()) {
                                    $user_registration = MLF_Database_Manager::is_user_registered($session['id']);
                                }
                                ?>
                                
                                <?php if (!is_user_logged_in()): ?>
                                    <div class="mlf-login-required">
                                        <p><?php _e('Vous devez être connecté pour vous inscrire', 'mlf'); ?></p>
                                        <a href="<?php echo wp_login_url(get_permalink()); ?>" class="mlf-btn mlf-btn-secondary">
                                            <?php _e('Se connecter', 'mlf'); ?>
                                        </a>
                                    </div>
                                    
                                <?php elseif ($user_registration): ?>
                                    <div class="mlf-user-registered">
                                        <?php 
                                        switch ($user_registration) {
                                            case 'confirme':
                                                echo '<span class="mlf-status mlf-status-confirmed">✅ ' . __('Inscrit(e)', 'mlf') . '</span>';
                                                break;
                                            case 'en_attente':
                                                echo '<span class="mlf-status mlf-status-pending">⏳ ' . __('En attente', 'mlf') . '</span>';
                                                break;
                                            case 'liste_attente':
                                                echo '<span class="mlf-status mlf-status-waitlist">📝 ' . __('Liste d\'attente', 'mlf') . '</span>';
                                                break;
                                            default:
                                                echo '<span class="mlf-status mlf-status-other">' . esc_html($user_registration) . '</span>';
                                        }
                                        ?>
                                    </div>
                                    
                                <?php elseif (intval($session['current_players']) < intval($session['max_players'])): ?>
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

        // Vérifier que l'utilisateur est connecté
        if (!is_user_logged_in()) {
            return '<div class="mlf-login-required">' .
                   '<p>' . __('Vous devez être connecté pour vous inscrire à une session.', 'mlf') . '</p>' .
                   '<a href="' . wp_login_url(get_permalink()) . '" class="mlf-btn mlf-btn-secondary">' . __('Se connecter', 'mlf') . '</a>' .
                   '</div>';
        }

        $session = MLF_Database_Manager::get_game_session($session_id);
        if (!$session || !$session['is_public']) {
            return '<p>' . __('Session non trouvée ou non publique.', 'mlf') . '</p>';
        }

        // Vérifier si l'utilisateur est déjà inscrit
        $existing_registration = MLF_Database_Manager::is_user_registered($session_id);
        if ($existing_registration) {
            $status_labels = array(
                'confirme' => __('Confirmé', 'mlf'),
                'en_attente' => __('En attente', 'mlf'),
                'liste_attente' => __('Liste d\'attente', 'mlf'),
                'annule' => __('Annulé', 'mlf')
            );
            
            $status_label = isset($status_labels[$existing_registration]) ? $status_labels[$existing_registration] : $existing_registration;
            
            return '<div class="mlf-already-registered">' .
                   '<p><strong>' . __('Vous êtes déjà inscrit à cette session.', 'mlf') . '</strong></p>' .
                   '<p>' . __('Statut :', 'mlf') . ' <span class="mlf-status mlf-status-' . esc_attr($existing_registration) . '">' . esc_html($status_label) . '</span></p>' .
                   '</div>';
        }

        // Récupérer les informations de l'utilisateur connecté
        $current_user = wp_get_current_user();
        $user_meta = get_user_meta($current_user->ID);
        
        // Préparer les valeurs pré-remplies
        $user_name = $current_user->display_name ?: $current_user->user_login;
        $user_email = $current_user->user_email;
        $user_phone = isset($user_meta['phone'][0]) ? $user_meta['phone'][0] : '';

        ob_start();
        ?>
        <div class="mlf-registration-container">
            <h3><?php _e('Inscription à la session', 'mlf'); ?></h3>
            <p class="mlf-session-title"><strong><?php echo esc_html($session['session_name']); ?></strong></p>
            
            <form id="mlf-registration-form" class="mlf-form" data-session-id="<?php echo esc_attr($session_id); ?>">
                <?php wp_nonce_field('mlf_register_session', 'mlf_registration_nonce'); ?>
                
                <div class="mlf-user-info-section">
                    <h4><?php _e('Vos informations (automatiques)', 'mlf'); ?></h4>
                    
                    <div class="mlf-form-group">
                        <label for="player_name"><?php _e('Nom complet', 'mlf'); ?></label>
                        <input type="text" id="player_name" name="player_name" 
                               value="<?php echo esc_attr($user_name); ?>" 
                               readonly class="mlf-readonly" 
                               title="<?php _e('Cette information provient de votre profil utilisateur', 'mlf'); ?>" />
                        <small class="mlf-field-note"><?php _e('Provient de votre profil utilisateur', 'mlf'); ?></small>
                    </div>
                    
                    <div class="mlf-form-group">
                        <label for="player_email"><?php _e('Email', 'mlf'); ?></label>
                        <input type="email" id="player_email" name="player_email" 
                               value="<?php echo esc_attr($user_email); ?>" 
                               readonly class="mlf-readonly"
                               title="<?php _e('Cette information provient de votre profil utilisateur', 'mlf'); ?>" />
                        <small class="mlf-field-note"><?php _e('Provient de votre profil utilisateur', 'mlf'); ?></small>
                    </div>
                    
                    <div class="mlf-form-group">
                        <label for="player_phone"><?php _e('Téléphone', 'mlf'); ?></label>
                        <input type="tel" id="player_phone" name="player_phone" 
                               value="<?php echo esc_attr($user_phone); ?>" 
                               placeholder="<?php _e('Optionnel - vous pouvez le modifier', 'mlf'); ?>" />
                        <small class="mlf-field-note"><?php _e('Vous pouvez modifier ce champ si nécessaire', 'mlf'); ?></small>
                    </div>
                </div>
                
                <div class="mlf-session-info-section">
                    <h4><?php _e('Informations pour cette session', 'mlf'); ?></h4>
                    
                    <div class="mlf-form-group">
                        <label for="experience_level"><?php _e('Votre niveau d\'expérience', 'mlf'); ?></label>
                        <select id="experience_level" name="experience_level">
                            <option value="debutant"><?php _e('Débutant', 'mlf'); ?></option>
                            <option value="intermediaire" selected><?php _e('Intermédiaire', 'mlf'); ?></option>
                            <option value="avance"><?php _e('Avancé', 'mlf'); ?></option>
                            <option value="expert"><?php _e('Expert', 'mlf'); ?></option>
                        </select>
                    </div>
                    
                    <?php if ($session['game_type'] === 'jdr'): ?>
                        <div class="mlf-form-group">
                            <label for="character_name"><?php _e('Nom du personnage souhaité', 'mlf'); ?></label>
                            <input type="text" id="character_name" name="character_name" 
                                   placeholder="<?php _e('Ex: Elara la Magicienne', 'mlf'); ?>" />
                        </div>
                        
                        <div class="mlf-form-group">
                            <label for="character_class"><?php _e('Classe/Type de personnage préféré', 'mlf'); ?></label>
                            <input type="text" id="character_class" name="character_class" 
                                   placeholder="<?php _e('Ex: Magicien, Guerrier, Rôdeur...', 'mlf'); ?>" />
                        </div>
                    <?php endif; ?>
                    
                    <div class="mlf-form-group">
                        <label for="special_requests"><?php _e('Demandes spéciales ou préférences', 'mlf'); ?></label>
                        <textarea id="special_requests" name="special_requests" rows="3" 
                                  placeholder="<?php _e('Toute information utile pour le MJ...', 'mlf'); ?>"></textarea>
                    </div>
                    
                    <div class="mlf-form-group">
                        <label for="dietary_restrictions"><?php _e('Restrictions alimentaires', 'mlf'); ?></label>
                        <textarea id="dietary_restrictions" name="dietary_restrictions" rows="2" 
                                  placeholder="<?php _e('Allergies, régimes spéciaux...', 'mlf'); ?>"></textarea>
                    </div>
                </div>
                
                <div class="mlf-form-actions">
                    <button type="submit" class="mlf-btn mlf-btn-primary">
                        <?php _e('Confirmer mon inscription', 'mlf'); ?>
                    </button>
                    <span class="mlf-loading" style="display: none;"><?php _e('Inscription en cours...', 'mlf'); ?></span>
                </div>
                
                <div id="mlf-registration-message" class="mlf-message" style="display: none;"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle session registration via AJAX.
     */
    public function handle_session_registration() {
        // Vérifier que l'utilisateur est connecté
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Vous devez être connecté pour vous inscrire à une session'));
            return;
        }

        // Vérifier le nonce
        if (!isset($_POST['mlf_registration_nonce']) || !wp_verify_nonce($_POST['mlf_registration_nonce'], 'mlf_register_session')) {
            wp_send_json_error(array('message' => 'Vérification de sécurité échouée'));
            return;
        }

        $session_id = intval($_POST['session_id']);
        $session = MLF_Database_Manager::get_game_session($session_id);
        
        if (!$session) {
            wp_send_json_error(array('message' => 'Session non trouvée'));
            return;
        }

        // Vérifier si l'utilisateur n'est pas déjà inscrit
        $existing_registration = MLF_Database_Manager::is_user_registered($session_id);
        if ($existing_registration) {
            wp_send_json_error(array('message' => 'Vous êtes déjà inscrit à cette session'));
            return;
        }

        // Vérifier si la session n'est pas complète
        if (intval($session['current_players']) >= intval($session['max_players'])) {
            wp_send_json_error(array('message' => 'Cette session est complète'));
            return;
        }

        // Collecter seulement les champs additionnels (les infos utilisateur sont automatiques)
        $registration_data = array();
        
        // Champs optionnels standards
        $optional_fields = array('experience_level', 'character_name', 'character_class', 'special_requests', 'dietary_restrictions');
        foreach ($optional_fields as $field) {
            if (isset($_POST[$field])) {
                if (in_array($field, array('special_requests', 'dietary_restrictions'))) {
                    $registration_data[$field] = sanitize_textarea_field($_POST[$field]);
                } else {
                    $registration_data[$field] = sanitize_text_field($_POST[$field]);
                }
            }
        }

        // Collecter tous les champs personnalisés (field_*)
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'field_') === 0) {
                if (is_array($value)) {
                    $registration_data[$key] = array_map('sanitize_text_field', $value);
                } else {
                    $registration_data[$key] = sanitize_text_field($value);
                }
            }
        }

        // Ajouter l'ID utilisateur si connecté
        if (is_user_logged_in()) {
            $registration_data['user_id'] = get_current_user_id();
        }

        // Tenter l'inscription
        $result = MLF_Database_Manager::register_player($session_id, $registration_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        // Récupérer les données mises à jour de la session
        $updated_session = MLF_Database_Manager::get_game_session($session_id);

        // Succès - inclure les données mises à jour
        wp_send_json_success(array(
            'message' => 'Inscription réussie ! Vous recevrez un email de confirmation.',
            'registration_id' => $result,
            'session' => array(
                'current_players' => intval($updated_session['current_players']),
                'max_players' => intval($updated_session['max_players']),
                'is_full' => intval($updated_session['current_players']) >= intval($updated_session['max_players'])
            )
        ));
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

    /**
     * Afficher la vue détaillée d'une session sur une page dédiée.
     */
    private function display_session_details_page($session_id) {
        $session = MLF_Database_Manager::get_game_session($session_id);
        
        if (!$session || !$session['is_public']) {
            return '<p class="mlf-error">' . __('Session non trouvée ou non publique.', 'mlf') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="mlf-session-details-page">
            <div class="mlf-breadcrumb">
                <a href="<?php echo esc_url(remove_query_arg(array('action', 'session_id'))); ?>" class="mlf-back-btn">
                    ← <?php _e('Retour aux sessions', 'mlf'); ?>
                </a>
            </div>
            
            <div class="mlf-session-details-content">
                <?php if (!empty($session['banner_image_url'])): ?>
                    <div class="mlf-session-banner">
                        <img src="<?php echo esc_url($session['banner_image_url']); ?>" alt="<?php echo esc_attr($session['session_name']); ?>" class="mlf-banner-image" />
                    </div>
                <?php endif; ?>
                
                <h1 class="mlf-session-title"><?php echo esc_html($session['session_name']); ?></h1>
                
                <div class="mlf-session-meta-grid">
                    <div class="mlf-meta-item">
                        <strong><?php _e('Type de jeu:', 'mlf'); ?></strong>
                        <span class="mlf-game-type mlf-game-type-<?php echo esc_attr($session['game_type']); ?>">
                            <?php echo esc_html($this->get_game_type_label($session['game_type'])); ?>
                        </span>
                    </div>
                    
                    <div class="mlf-meta-item">
                        <strong><?php _e('Date et heure:', 'mlf'); ?></strong>
                        <?php echo esc_html(date_i18n('d/m/Y', strtotime($session['session_date']))); ?>
                        <?php _e('à', 'mlf'); ?>
                        <?php echo esc_html(date('H:i', strtotime($session['session_time']))); ?>
                    </div>
                    
                    <?php if (!empty($session['location'])): ?>
                        <div class="mlf-meta-item">
                            <strong><?php _e('Lieu:', 'mlf'); ?></strong>
                            <?php echo esc_html($session['location']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mlf-meta-item">
                        <strong><?php _e('Joueurs:', 'mlf'); ?></strong>
                        <?php echo intval($session['current_players']); ?>/<?php echo intval($session['max_players']); ?>
                    </div>
                    
                    <div class="mlf-meta-item">
                        <strong><?php _e('Niveau:', 'mlf'); ?></strong>
                        <?php echo esc_html($this->get_difficulty_label($session['difficulty_level'])); ?>
                    </div>
                    
                    <?php if (!empty($session['age_requirement'])): ?>
                        <div class="mlf-meta-item">
                            <strong><?php _e('Âge requis:', 'mlf'); ?></strong>
                            <?php echo esc_html($session['age_requirement']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($session['description'])): ?>
                    <div class="mlf-session-description">
                        <h3><?php _e('Description', 'mlf'); ?></h3>
                        <?php echo wp_kses_post(wpautop($session['description'])); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($session['detailed_description'])): ?>
                    <div class="mlf-session-detailed-description">
                        <h3><?php _e('Description détaillée', 'mlf'); ?></h3>
                        <?php echo wp_kses_post(wpautop($session['detailed_description'])); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($session['rules_and_requirements'])): ?>
                    <div class="mlf-session-rules">
                        <h3><?php _e('Règles et prérequis', 'mlf'); ?></h3>
                        <?php echo wp_kses_post(wpautop($session['rules_and_requirements'])); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($session['trigger_warnings'])): ?>
                    <div class="mlf-trigger-warnings">
                        <h3><?php _e('⚠️ Trigger warnings', 'mlf'); ?></h3>
                        <div class="mlf-warnings-content">
                            <?php echo wp_kses_post(wpautop($session['trigger_warnings'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mlf-session-actions">
                    <?php 
                    $is_full = intval($session['current_players']) >= intval($session['max_players']);
                    $user_registered = false;
                    
                    // Vérifier si l'utilisateur est déjà inscrit
                    if (is_user_logged_in()) {
                        $current_user = wp_get_current_user();
                        $existing_registration = MLF_Database_Manager::get_user_registration($session_id, $current_user->ID);
                        $user_registered = !empty($existing_registration);
                    }
                    
                    if (!is_user_logged_in()): ?>
                        <div class="mlf-auth-required">
                            <p class="mlf-auth-message"><?php _e('Vous devez être connecté pour vous inscrire à cette session.', 'mlf'); ?></p>
                            <a href="<?php echo wp_login_url(get_permalink()); ?>" class="mlf-btn mlf-btn-primary mlf-btn-large">
                                <?php _e('Se connecter', 'mlf'); ?>
                            </a>
                        </div>
                    <?php elseif ($user_registered): ?>
                        <div class="mlf-user-registered">
                            <p class="mlf-registration-status">
                                <span class="mlf-status-icon">✅</span>
                                <?php _e('Vous êtes déjà inscrit à cette session', 'mlf'); ?>
                            </p>
                            <span class="mlf-btn mlf-btn-success mlf-btn-large mlf-btn-disabled">
                                <?php _e('Inscription confirmée', 'mlf'); ?>
                            </span>
                        </div>
                    <?php elseif ($is_full): ?>
                        <div class="mlf-session-full">
                            <p class="mlf-full-message"><?php _e('Cette session est actuellement complète.', 'mlf'); ?></p>
                            <span class="mlf-btn mlf-btn-disabled mlf-btn-large">
                                <?php _e('Session complète', 'mlf'); ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="mlf-registration-available">
                            <a href="<?php echo esc_url(add_query_arg(array('action' => 'register', 'session_id' => $session_id))); ?>" 
                               class="mlf-btn mlf-btn-primary mlf-btn-large">
                                <?php _e('S\'inscrire à cette session', 'mlf'); ?>
                            </a>
                            <p class="mlf-places-info">
                                <?php 
                                $places_restantes = intval($session['max_players']) - intval($session['current_players']);
                                printf(
                                    _n('Il reste %d place disponible', 'Il reste %d places disponibles', $places_restantes, 'mlf'),
                                    $places_restantes
                                );
                                ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Afficher le formulaire d'inscription sur une page dédiée.
     */
    private function display_registration_page($session_id) {
        $session = MLF_Database_Manager::get_game_session($session_id);
        
        if (!$session || !$session['is_public']) {
            return '<p class="mlf-error">' . __('Session non trouvée ou non publique.', 'mlf') . '</p>';
        }
        
        if (intval($session['current_players']) >= intval($session['max_players'])) {
            return '<p class="mlf-error">' . __('Cette session est complète.', 'mlf') . '</p>';
        }

        // Vérifier l'authentification utilisateur
        if (!is_user_logged_in()) {
            ob_start();
            ?>
            <div class="mlf-registration-page">
                <div class="mlf-breadcrumb">
                    <a href="<?php echo esc_url(remove_query_arg(array('action', 'session_id'))); ?>" class="mlf-back-btn">
                        ← <?php _e('Retour aux sessions', 'mlf'); ?>
                    </a>
                </div>
                
                <div class="mlf-registration-content">
                    <h1><?php _e('Inscription', 'mlf'); ?> - <?php echo esc_html($session['session_name']); ?></h1>
                    
                    <div class="mlf-login-required">
                        <div class="mlf-auth-notice">
                            <h3><?php _e('Connexion requise', 'mlf'); ?></h3>
                            <p><?php _e('Vous devez être connecté à votre compte pour vous inscrire à une session.', 'mlf'); ?></p>
                            <div class="mlf-auth-actions">
                                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="mlf-btn mlf-btn-primary">
                                    <?php _e('Se connecter', 'mlf'); ?>
                                </a>
                                <a href="<?php echo wp_registration_url(); ?>" class="mlf-btn mlf-btn-secondary">
                                    <?php _e('Créer un compte', 'mlf'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        // Récupérer les informations de l'utilisateur connecté
        $current_user = wp_get_current_user();
        $user_phone = get_user_meta($current_user->ID, 'phone', true);
        
        // Vérifier si l'utilisateur est déjà inscrit à cette session
        $existing_registration = MLF_Database_Manager::get_user_registration($session_id, $current_user->ID);
        
        if ($existing_registration) {
            ob_start();
            ?>
            <div class="mlf-registration-page">
                <div class="mlf-breadcrumb">
                    <a href="<?php echo esc_url(remove_query_arg(array('action', 'session_id'))); ?>" class="mlf-back-btn">
                        ← <?php _e('Retour aux sessions', 'mlf'); ?>
                    </a>
                </div>
                
                <div class="mlf-registration-content">
                    <h1><?php _e('Inscription', 'mlf'); ?> - <?php echo esc_html($session['session_name']); ?></h1>
                    
                    <div class="mlf-already-registered">
                        <div class="mlf-registration-status">
                            <h3><?php _e('Vous êtes déjà inscrit', 'mlf'); ?></h3>
                            <p><?php _e('Vous êtes déjà inscrit à cette session.', 'mlf'); ?></p>
                            <p><strong><?php _e('Statut :', 'mlf'); ?></strong> 
                                <span class="mlf-status mlf-status-<?php echo esc_attr($existing_registration['status']); ?>">
                                    <?php echo esc_html(ucfirst($existing_registration['status'])); ?>
                                </span>
                            </p>
                            <div class="mlf-registration-actions">
                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'details', 'session_id' => $session_id), remove_query_arg(array('action')))); ?>" 
                                   class="mlf-btn mlf-btn-secondary">
                                    <?php _e('Voir les détails de la session', 'mlf'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }
        
        ob_start();
        ?>
        <div class="mlf-registration-page">
            <div class="mlf-breadcrumb">
                <a href="<?php echo esc_url(remove_query_arg(array('action', 'session_id'))); ?>" class="mlf-back-btn">
                    ← <?php _e('Retour aux sessions', 'mlf'); ?>
                </a>
                <span class="mlf-breadcrumb-separator">/</span>
                <a href="<?php echo esc_url(add_query_arg(array('action' => 'details', 'session_id' => $session_id), remove_query_arg(array('action')))); ?>">
                    <?php echo esc_html($session['session_name']); ?>
                </a>
            </div>
            
            <div class="mlf-registration-content">
                <h1><?php _e('Inscription', 'mlf'); ?> - <?php echo esc_html($session['session_name']); ?></h1>
                
                <div class="mlf-session-summary">
                    <h3><?php _e('Résumé de la session', 'mlf'); ?></h3>
                    <div class="mlf-summary-grid">
                        <div class="mlf-summary-item">
                            <strong><?php _e('Date:', 'mlf'); ?></strong>
                            <?php echo esc_html(date_i18n('d/m/Y', strtotime($session['session_date']))); ?>
                            <?php _e('à', 'mlf'); ?>
                            <?php echo esc_html(date('H:i', strtotime($session['session_time']))); ?>
                        </div>
                        
                        <?php if (!empty($session['location'])): ?>
                            <div class="mlf-summary-item">
                                <strong><?php _e('Lieu:', 'mlf'); ?></strong>
                                <?php echo esc_html($session['location']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mlf-summary-item">
                            <strong><?php _e('Places disponibles:', 'mlf'); ?></strong>
                            <?php echo intval($session['max_players']) - intval($session['current_players']); ?>
                            / <?php echo intval($session['max_players']); ?>
                        </div>
                    </div>
                </div>

                <div class="mlf-registration-container">
                    <form id="mlf-registration-form" class="mlf-form" data-session-id="<?php echo esc_attr($session_id); ?>">
                        <?php wp_nonce_field('mlf_register_session', 'mlf_registration_nonce'); ?>
                        <input type="hidden" name="session_id" value="<?php echo esc_attr($session_id); ?>" />
                        
                        <div class="mlf-user-info-section">
                            <h3><?php _e('Vos informations (automatiques)', 'mlf'); ?></h3>
                            
                            <div class="mlf-form-group">
                                <label for="player_name"><?php _e('Nom complet', 'mlf'); ?></label>
                                <input type="text" id="player_name" name="player_name" 
                                       value="<?php echo esc_attr($current_user->display_name); ?>" 
                                       readonly class="mlf-readonly" 
                                       title="<?php _e('Cette information provient de votre profil utilisateur', 'mlf'); ?>" />
                                <small class="mlf-field-note"><?php _e('Provient de votre profil utilisateur', 'mlf'); ?></small>
                            </div>
                            
                            <div class="mlf-form-group">
                                <label for="player_email"><?php _e('Email', 'mlf'); ?></label>
                                <input type="email" id="player_email" name="player_email" 
                                       value="<?php echo esc_attr($current_user->user_email); ?>" 
                                       readonly class="mlf-readonly"
                                       title="<?php _e('Cette information provient de votre profil utilisateur', 'mlf'); ?>" />
                                <small class="mlf-field-note"><?php _e('Provient de votre profil utilisateur', 'mlf'); ?></small>
                            </div>
                            
                            <div class="mlf-form-group">
                                <label for="player_phone"><?php _e('Téléphone', 'mlf'); ?></label>
                                <input type="tel" id="player_phone" name="player_phone" 
                                       value="<?php echo esc_attr($user_phone); ?>" 
                                       placeholder="<?php _e('Optionnel - vous pouvez le modifier', 'mlf'); ?>" />
                                <small class="mlf-field-note"><?php _e('Vous pouvez modifier ce champ si nécessaire', 'mlf'); ?></small>
                            </div>
                        </div>
                        
                        <div class="mlf-session-info-section">
                            <h3><?php _e('Informations pour cette session', 'mlf'); ?></h3>
                            
                            <div class="mlf-form-group">
                                <label for="experience_level"><?php _e('Votre niveau d\'expérience', 'mlf'); ?></label>
                                <select id="experience_level" name="experience_level">
                                    <option value="debutant"><?php _e('Débutant', 'mlf'); ?></option>
                                    <option value="intermediaire" selected><?php _e('Intermédiaire', 'mlf'); ?></option>
                                    <option value="avance"><?php _e('Avancé', 'mlf'); ?></option>
                                    <option value="expert"><?php _e('Expert', 'mlf'); ?></option>
                                </select>
                            </div>
                            
                            <div class="mlf-form-group">
                                <label for="character_name"><?php _e('Nom du personnage souhaité', 'mlf'); ?></label>
                                <input type="text" id="character_name" name="character_name" 
                                       placeholder="<?php _e('Ex: Lady Catherine, Le Baron...', 'mlf'); ?>" />
                            </div>
                            
                            <div class="mlf-form-group">
                                <label for="special_requests"><?php _e('Demandes spéciales ou préférences', 'mlf'); ?></label>
                                <textarea id="special_requests" name="special_requests" rows="3" 
                                          placeholder="<?php _e('Préférences de rôle, limitations physiques, etc.', 'mlf'); ?>"></textarea>
                            </div>
                            
                            <div class="mlf-form-group">
                                <label for="dietary_restrictions"><?php _e('Restrictions alimentaires', 'mlf'); ?></label>
                                <textarea id="dietary_restrictions" name="dietary_restrictions" rows="2" 
                                          placeholder="<?php _e('Allergies, régimes spéciaux...', 'mlf'); ?>"></textarea>
                            </div>
                        </div>

                        <div class="mlf-form-actions">
                            <button type="submit" class="mlf-btn mlf-btn-primary mlf-btn-large">
                                <?php _e('Confirmer mon inscription', 'mlf'); ?>
                            </button>
                            <span class="mlf-loading" style="display: none;"><?php _e('Inscription en cours...', 'mlf'); ?></span>
                            
                            <a href="<?php echo esc_url(add_query_arg(array('action' => 'details', 'session_id' => $session_id), remove_query_arg(array('action')))); ?>" 
                               class="mlf-btn mlf-btn-secondary">
                                <?php _e('Voir les détails', 'mlf'); ?>
                            </a>
                        </div>
                        
                        <div id="mlf-registration-message" class="mlf-message" style="display: none;"></div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a custom form field
     */
    private function render_form_field($field) {
        if (!is_array($field) || empty($field['type'])) {
            return;
        }

        $field_id = 'field_' . uniqid();
        $field_name = sanitize_key($field['name'] ?? $field_id);
        $field_label = esc_html($field['label'] ?? '');
        $field_type = sanitize_key($field['type']);
        $field_required = !empty($field['required']);
        $field_placeholder = esc_attr($field['placeholder'] ?? '');

        echo '<div class="mlf-form-group">';
        
        if ($field_label) {
            echo '<label for="' . $field_id . '">' . $field_label;
            if ($field_required) {
                echo ' *';
            }
            echo '</label>';
        }

        switch ($field_type) {
            case 'text':
            case 'email':
            case 'tel':
            case 'url':
                echo '<input type="' . $field_type . '" id="' . $field_id . '" name="' . $field_name . '"';
                if ($field_placeholder) {
                    echo ' placeholder="' . $field_placeholder . '"';
                }
                if ($field_required) {
                    echo ' required';
                }
                echo ' />';
                break;
                
            case 'textarea':
                echo '<textarea id="' . $field_id . '" name="' . $field_name . '"';
                if ($field_placeholder) {
                    echo ' placeholder="' . $field_placeholder . '"';
                }
                if ($field_required) {
                    echo ' required';
                }
                echo ' rows="3"></textarea>';
                break;
                
            case 'select':
                echo '<select id="' . $field_id . '" name="' . $field_name . '"';
                if ($field_required) {
                    echo ' required';
                }
                echo '>';
                
                if (!$field_required) {
                    echo '<option value="">-- Choisir --</option>';
                }
                
                if (!empty($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as $option) {
                        $value = esc_attr($option['value'] ?? $option);
                        $label = esc_html($option['label'] ?? $option);
                        echo '<option value="' . $value . '">' . $label . '</option>';
                    }
                }
                echo '</select>';
                break;
                
            case 'checkbox':
                echo '<input type="checkbox" id="' . $field_id . '" name="' . $field_name . '" value="1"';
                if ($field_required) {
                    echo ' required';
                }
                echo ' />';
                break;
                
            case 'radio':
                if (!empty($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as $index => $option) {
                        $option_id = $field_id . '_' . $index;
                        $value = esc_attr($option['value'] ?? $option);
                        $label = esc_html($option['label'] ?? $option);
                        
                        echo '<div class="mlf-radio-option">';
                        echo '<input type="radio" id="' . $option_id . '" name="' . $field_name . '" value="' . $value . '"';
                        if ($field_required) {
                            echo ' required';
                        }
                        echo ' />';
                        echo '<label for="' . $option_id . '">' . $label . '</label>';
                        echo '</div>';
                    }
                }
                break;
                
            default:
                echo '<input type="text" id="' . $field_id . '" name="' . $field_name . '"';
                if ($field_placeholder) {
                    echo ' placeholder="' . $field_placeholder . '"';
                }
                if ($field_required) {
                    echo ' required';
                }
                echo ' />';
                break;
        }

        if (!empty($field['description'])) {
            echo '<small class="mlf-field-description">' . esc_html($field['description']) . '</small>';
        }

        echo '</div>';
    }
}
