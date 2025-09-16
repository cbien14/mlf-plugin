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
        add_action('init', array($this, 'handle_form_submissions'));
        // Hooks AJAX pour l'inscription aux sessions (uniquement pour utilisateurs connectés)
        add_action('wp_ajax_mlf_register_session', array($this, 'handle_session_registration'));
        add_action('wp_ajax_mlf_register_for_session', array($this, 'handle_session_registration'));
        // Hook pour mise à jour des réponses au formulaire custom
        add_action('wp_ajax_mlf_update_custom_responses', array($this, 'handle_update_custom_responses'));
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
     * Handle form submissions (POST requests).
     */
    public function handle_form_submissions() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'mlf_update_custom_responses') {
            return;
        }

        // Rediriger vers la méthode AJAX mais en mode direct
        $this->handle_update_custom_responses_direct();
    }

    /**
     * Handle custom form responses update for registered users (direct POST).
     */
    private function handle_update_custom_responses_direct() {
        // Vérifier l'utilisateur connecté
        if (!is_user_logged_in()) {
            wp_die(__('Vous devez être connecté.', 'mlf'));
            return;
        }

        // Vérifier le nonce
        if (!wp_verify_nonce($_POST['mlf_responses_nonce'], 'mlf_update_responses')) {
            wp_die(__('Erreur de sécurité', 'mlf'));
            return;
        }

        $session_id = intval($_POST['session_id']);
        $registration_id = intval($_POST['registration_id']);
        $current_user = wp_get_current_user();

        // Vérifier que l'utilisateur est bien inscrit à cette session avec un statut confirmé
        $existing_registration = MLF_Database_Manager::get_user_registration($session_id, $current_user->ID);
        if (!$existing_registration || $existing_registration['id'] != $registration_id) {
            wp_die(__('Vous n\'êtes pas inscrit à cette session.', 'mlf'));
            return;
        }

        // Vérifier que l'inscription est confirmée
        if ($existing_registration['registration_status'] !== 'confirme') {
            wp_die(__('Votre inscription doit être confirmée par un administrateur avant de pouvoir modifier vos réponses.', 'mlf'));
            return;
        }

        // Collecter les données du formulaire custom
        $custom_form_data = array();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'field_') === 0) {
                if (is_array($value)) {
                    $custom_form_data[$key] = array_map('sanitize_text_field', $value);
                } else {
                    $custom_form_data[$key] = sanitize_text_field($value);
                }
            }
        }

        // Sauvegarder les réponses
        if (!empty($custom_form_data) && class_exists('MLF_Session_Forms_Manager')) {
            $save_result = MLF_Session_Forms_Manager::save_form_response(
                $session_id,
                $registration_id,
                $custom_form_data
            );
            
            if ($save_result) {
                // Rediriger avec message de succès
                $redirect_url = add_query_arg(array(
                    'action' => 'details',
                    'session_id' => $session_id,
                    'mlf_message' => 'updated'
                ), home_url());
                wp_redirect($redirect_url);
                exit;
            } else {
                wp_die(__('Erreur lors de la sauvegarde de vos réponses.', 'mlf'));
            }
        } else {
            wp_die(__('Aucune donnée à sauvegarder.', 'mlf'));
        }
    }

    /**
     * Enqueue public-facing stylesheets and scripts.
     */
    public function enqueue_public_scripts() {
        wp_enqueue_style('mlf-public-css', plugin_dir_url(dirname(__FILE__)) . 'public/css/mlf-public.css', array(), '1.0.0');
        wp_enqueue_script('mlf-public-js', plugin_dir_url(dirname(__FILE__)) . 'public/js/mlf-public.js', array('jquery'), '1.0.1', true);
        
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
            'upcoming_only' => true
        ), $atts);

        $filters = array(
            'limit' => intval($atts['limit'])
        );

        if ($atts['upcoming_only']) {
            $filters['date_from'] = date('Y-m-d');
        }

        $sessions = MLF_Database_Manager::get_game_sessions($filters);

        ob_start();
        ?>
        <div class="mlf-sessions-list">
            <h2><?php _e('Sessions de Murder disponibles', 'mlf'); ?></h2>
            
            <?php if (empty($sessions)): ?>
                <p class="mlf-no-sessions"><?php _e('Aucune session disponible pour le moment.', 'mlf'); ?></p>
            <?php else: ?>
                <div class="mlf-sessions-grid">
                    <?php foreach ($sessions as $session): ?>
                        <div class="mlf-session-card" data-session-id="<?php echo esc_attr($session['id']); ?>">
                            <div class="mlf-session-header">
                                <h3 class="mlf-session-title"><?php echo esc_html($session['session_name']); ?></h3>
                                <span class="mlf-game-type mlf-game-type-murder">
                                    <?php _e('Murder', 'mlf'); ?>
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
                                    <?php if (!empty($session['min_players'])): ?>
                                        <span class="mlf-min-players"><?php printf(__('(min. %d)', 'mlf'), intval($session['min_players'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($session['intention_note'])): ?>
                                <div class="mlf-session-intention">
                                    <strong><?php _e('Note d\'intention:', 'mlf'); ?></strong>
                                    <div class="mlf-intention-content">
                                        <?php echo wp_kses_post(wpautop($session['intention_note'])); ?>
                                    </div>
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
        if (!$session) {
            return '<p>' . __('Session non trouvée.', 'mlf') . '</p>';
        }

        ob_start();
        ?>
        <div class="mlf-session-details">
            <h2><?php echo esc_html($session['session_name']); ?></h2>
            
            <div class="mlf-session-info">
                <div class="mlf-info-grid">
                    <div class="mlf-info-item">
                        <strong><?php _e('Type de jeu:', 'mlf'); ?></strong>
                        <span class="mlf-game-type mlf-game-type-murder">
                            <?php _e('Murder', 'mlf'); ?>
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
                        <strong><?php _e('Joueurs:', 'mlf'); ?></strong>
                        <?php echo intval($session['current_players']); ?>/<?php echo intval($session['max_players']); ?>
                        <?php if (!empty($session['min_players'])): ?>
                            <span class="mlf-min-players"><?php printf(__('(minimum %d)', 'mlf'), intval($session['min_players'])); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($session['game_master_name'])): ?>
                        <div class="mlf-info-item">
                            <strong><?php _e('Maître de jeu:', 'mlf'); ?></strong>
                            <?php echo esc_html($session['game_master_name']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($session['intention_note'])): ?>
                <div class="mlf-section">
                    <h3><?php _e('Note d\'intention', 'mlf'); ?></h3>
                    <div class="mlf-content">
                        <?php echo wp_kses_post(wpautop($session['intention_note'])); ?>
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
        if (!$session) {
            return '<p>' . __('Session non trouvée.', 'mlf') . '</p>';
        }

        // Vérifier si l'utilisateur est déjà inscrit
        $existing_registration = MLF_Database_Manager::is_user_registered($session_id);
        $user_registration_data = null;
        $existing_custom_responses = null;
        
        if ($existing_registration) {
            // Récupérer les données complètes de l'inscription
            $current_user = wp_get_current_user();
            $user_registration_data = MLF_Database_Manager::get_user_registration($session_id, $current_user->ID);
            
            // Récupérer les réponses aux formulaires custom existantes
            if ($user_registration_data && class_exists('MLF_Session_Forms_Manager')) {
                $existing_custom_responses = MLF_Session_Forms_Manager::get_form_response($session_id, $user_registration_data['id']);
            }
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
                
                <?php
                // Ajouter le formulaire custom de la session s'il existe
                if (class_exists('MLF_Session_Forms_Manager')) {
                    $custom_form = MLF_Session_Forms_Manager::get_session_form($session_id);
                    if ($custom_form && !empty($custom_form['form_fields'])) {
                        ?>
                        <div class="mlf-custom-form-section">
                            <h4><?php echo esc_html($custom_form['form_title'] ?? __('Questions spécifiques à cette session', 'mlf')); ?></h4>
                            <?php if (!empty($custom_form['form_description'])): ?>
                                <p class="mlf-form-description"><?php echo wp_kses_post($custom_form['form_description']); ?></p>
                            <?php endif; ?>
                            
                            <?php
                            // Si l'utilisateur est déjà inscrit, afficher un message et pré-remplir avec ses réponses
                            if ($existing_registration) {
                                $status_labels = array(
                                    'confirme' => __('Confirmé', 'mlf'),
                                    'en_attente' => __('En attente', 'mlf'),
                                    'liste_attente' => __('Liste d\'attente', 'mlf'),
                                    'annule' => __('Annulé', 'mlf')
                                );
                                $status_label = isset($status_labels[$existing_registration]) ? $status_labels[$existing_registration] : $existing_registration;
                                ?>
                                <div class="mlf-already-registered-notice">
                                    <p><strong><?php _e('Vous êtes déjà inscrit à cette session.', 'mlf'); ?></strong></p>
                                    <p><?php _e('Statut :', 'mlf'); ?> <span class="mlf-status mlf-status-<?php echo esc_attr($existing_registration); ?>"><?php echo esc_html($status_label); ?></span></p>
                                    <p><em><?php _e('Vous pouvez modifier vos réponses au formulaire ci-dessous.', 'mlf'); ?></em></p>
                                </div>
                                <?php
                            }
                            
                            foreach ($custom_form['form_fields'] as $field_index => $field) {
                                $field_name = 'field_' . $field_index;
                                $field_value = '';
                                
                                // Pré-remplir avec les réponses existantes
                                if ($existing_custom_responses && isset($existing_custom_responses['response_data'][$field_name])) {
                                    $field_value = $existing_custom_responses['response_data'][$field_name];
                                }
                                
                                echo '<div class="mlf-form-group mlf-custom-field">';
                                
                                // Pour les checkboxes, on n'affiche pas de label séparé car le label fait partie de la checkbox
                                if ($field['type'] !== 'checkbox') {
                                    echo '<label for="' . esc_attr($field_name) . '">';
                                    echo esc_html($field['label'] ?? '');
                                    if (!empty($field['required'])) {
                                        echo ' <span class="mlf-required">*</span>';
                                    }
                                    echo '</label>';
                                }
                                
                                switch ($field['type']) {
                                    case 'text':
                                    case 'email':
                                    case 'number':
                                        echo '<input type="' . esc_attr($field['type']) . '" ';
                                        echo 'id="' . esc_attr($field_name) . '" ';
                                        echo 'name="' . esc_attr($field_name) . '" ';
                                        echo 'value="' . esc_attr($field_value) . '" ';
                                        if (!empty($field['placeholder'])) {
                                            echo 'placeholder="' . esc_attr($field['placeholder']) . '" ';
                                        }
                                        if (!empty($field['required'])) {
                                            echo 'required ';
                                        }
                                        echo '/>';
                                        break;
                                        
                                    case 'textarea':
                                        echo '<textarea ';
                                        echo 'id="' . esc_attr($field_name) . '" ';
                                        echo 'name="' . esc_attr($field_name) . '" ';
                                        echo 'rows="' . esc_attr($field['rows'] ?? 3) . '" ';
                                        if (!empty($field['placeholder'])) {
                                            echo 'placeholder="' . esc_attr($field['placeholder']) . '" ';
                                        }
                                        if (!empty($field['required'])) {
                                            echo 'required ';
                                        }
                                        echo '>' . esc_textarea($field_value) . '</textarea>';
                                        break;
                                        
                                    case 'select':
                                        echo '<select ';
                                        echo 'id="' . esc_attr($field_name) . '" ';
                                        echo 'name="' . esc_attr($field_name) . '" ';
                                        if (!empty($field['required'])) {
                                            echo 'required ';
                                        }
                                        echo '>';
                                        
                                        if (!empty($field['placeholder'])) {
                                            echo '<option value="">' . esc_html($field['placeholder']) . '</option>';
                                        }
                                        
                                        if (!empty($field['options'])) {
                                            foreach ($field['options'] as $option) {
                                                $selected = ($field_value === $option) ? 'selected' : '';
                                                echo '<option value="' . esc_attr($option) . '" ' . $selected . '>' . esc_html($option) . '</option>';
                                            }
                                        }
                                        echo '</select>';
                                        break;
                                        
                                    case 'radio':
                                        if (!empty($field['options'])) {
                                            echo '<div class="mlf-radio-group">';
                                            foreach ($field['options'] as $option_index => $option) {
                                                $radio_id = $field_name . '_' . $option_index;
                                                $checked = ($field_value === $option) ? 'checked' : '';
                                                echo '<label class="mlf-radio-label">';
                                                echo '<input type="radio" ';
                                                echo 'id="' . esc_attr($radio_id) . '" ';
                                                echo 'name="' . esc_attr($field_name) . '" ';
                                                echo 'value="' . esc_attr($option) . '" ';
                                                echo $checked . ' ';
                                                if (!empty($field['required'])) {
                                                    echo 'required ';
                                                }
                                                echo '/>';
                                                echo '<span>' . esc_html($option) . '</span>';
                                                echo '</label>';
                                            }
                                            echo '</div>';
                                        }
                                        break;
                                        
                                    case 'checkbox':
                                        $checked = !empty($field_value) ? 'checked' : '';
                                        echo '<label class="mlf-checkbox-label">';
                                        echo '<input type="checkbox" ';
                                        echo 'id="' . esc_attr($field_name) . '" ';
                                        echo 'name="' . esc_attr($field_name) . '" ';
                                        echo 'value="1" ';
                                        echo $checked . ' ';
                                        if (!empty($field['required'])) {
                                            echo 'required ';
                                        }
                                        echo '/>';
                                        echo '<span>' . esc_html($field['label'] ?? '');
                                        if (!empty($field['required'])) {
                                            echo ' <span class="mlf-required">*</span>';
                                        }
                                        echo '</span>';
                                        echo '</label>';
                                        break;
                                }
                                
                                if (!empty($field['description'])) {
                                    echo '<small class="mlf-field-description">' . esc_html($field['description']) . '</small>';
                                }
                                
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <?php
                    }
                }
                ?>
                
                <div class="mlf-form-actions">
                    <?php if ($existing_registration): ?>
                        <button type="submit" class="mlf-btn mlf-btn-primary">
                            <?php _e('Mettre à jour mes réponses', 'mlf'); ?>
                        </button>
                        <span class="mlf-loading" style="display: none;"><?php _e('Mise à jour en cours...', 'mlf'); ?></span>
                    <?php else: ?>
                        <button type="submit" class="mlf-btn mlf-btn-primary">
                            <?php _e('Confirmer mon inscription', 'mlf'); ?>
                        </button>
                        <span class="mlf-loading" style="display: none;"><?php _e('Inscription en cours...', 'mlf'); ?></span>
                    <?php endif; ?>
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

        // Vérifier si l'utilisateur est déjà inscrit
        $existing_registration = MLF_Database_Manager::is_user_registered($session_id);
        $current_user = wp_get_current_user();
        $user_registration_data = null;
        $is_updating_responses = false;
        
        if ($existing_registration) {
            // L'utilisateur est déjà inscrit, on permet la mise à jour des réponses au formulaire custom
            $user_registration_data = MLF_Database_Manager::get_user_registration($session_id, $current_user->ID);
            $is_updating_responses = true;
        } else {
            // Vérifier si la session n'est pas complète pour une nouvelle inscription
            if (intval($session['current_players']) >= intval($session['max_players'])) {
                wp_send_json_error(array('message' => 'Cette session est complète'));
                return;
            }
        }

        // Collecter seulement les champs additionnels (les infos utilisateur sont automatiques)
        $registration_data = array();

        // Collecter tous les champs personnalisés (field_*)
        $custom_form_data = array();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'field_') === 0) {
                if (is_array($value)) {
                    $custom_form_data[$key] = array_map('sanitize_text_field', $value);
                } else {
                    $custom_form_data[$key] = sanitize_text_field($value);
                }
            }
        }

        // Si l'utilisateur est déjà inscrit, on met à jour seulement ses réponses au formulaire custom
        if ($is_updating_responses && $user_registration_data) {
            // Sauvegarder les réponses au formulaire custom
            if (!empty($custom_form_data) && class_exists('MLF_Session_Forms_Manager')) {
                $save_result = MLF_Session_Forms_Manager::save_form_response(
                    $session_id,
                    $user_registration_data['id'],
                    $custom_form_data
                );
                
                if ($save_result) {
                    wp_send_json_success(array(
                        'message' => __('Vos réponses au formulaire ont été mises à jour avec succès !', 'mlf')
                    ));
                } else {
                    wp_send_json_error(array('message' => __('Erreur lors de la mise à jour de vos réponses.', 'mlf')));
                }
            } else {
                wp_send_json_success(array(
                    'message' => __('Aucune modification à enregistrer.', 'mlf')
                ));
            }
            return;
        }

        // Pour les nouvelles inscriptions, procéder normalement
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

        // Sauvegarder les réponses au formulaire custom si elles existent
        if (!empty($custom_form_data) && class_exists('MLF_Session_Forms_Manager')) {
            $save_custom_result = MLF_Session_Forms_Manager::save_form_response(
                $session_id,
                $result, // ID de l'inscription qui vient d'être créée
                $custom_form_data
            );
            
            if (!$save_custom_result) {
                // Log l'erreur mais ne pas faire échouer l'inscription
                error_log('MLF: Erreur lors de la sauvegarde du formulaire custom pour la session ' . $session_id);
            }
        }

        // Récupérer les données mises à jour de la session
        $updated_session = MLF_Database_Manager::get_game_session($session_id);

        // Succès - inclure les données mises à jour
        wp_send_json_success(array(
            'message' => 'Inscription enregistrée ! Votre demande est en attente de validation par l\'administrateur. Vous recevrez un email de confirmation.',
            'registration_id' => $result,
            'session' => array(
                'current_players' => intval($updated_session['current_players']),
                'max_players' => intval($updated_session['max_players']),
                'is_full' => intval($updated_session['current_players']) >= intval($updated_session['max_players'])
            )
        ));
    }

    /**
     * Handle custom form responses update for registered users.
     */
    public function handle_update_custom_responses() {
        // Vérifier l'utilisateur connecté
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Vous devez être connecté.'));
            return;
        }

        // Vérifier le nonce
        if (!wp_verify_nonce($_POST['mlf_responses_nonce'], 'mlf_update_responses')) {
            wp_send_json_error(array('message' => 'Erreur de sécurité'));
            return;
        }

        $session_id = intval($_POST['session_id']);
        $registration_id = intval($_POST['registration_id']);
        $current_user = wp_get_current_user();

        // Vérifier que l'utilisateur est bien inscrit à cette session avec un statut confirmé
        $existing_registration = MLF_Database_Manager::get_user_registration($session_id, $current_user->ID);
        if (!$existing_registration || $existing_registration['id'] != $registration_id) {
            wp_send_json_error(array('message' => 'Vous n\'êtes pas inscrit à cette session.'));
            return;
        }

        // Vérifier que l'inscription est confirmée
        if ($existing_registration['registration_status'] !== 'confirme') {
            wp_send_json_error(array('message' => 'Votre inscription doit être confirmée par un administrateur avant de pouvoir modifier vos réponses.'));
            return;
        }

        // Collecter les données du formulaire custom
        $custom_form_data = array();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'field_') === 0) {
                if (is_array($value)) {
                    $custom_form_data[$key] = array_map('sanitize_text_field', $value);
                } else {
                    $custom_form_data[$key] = sanitize_text_field($value);
                }
            }
        }

        // Sauvegarder les réponses
        if (!empty($custom_form_data) && class_exists('MLF_Session_Forms_Manager')) {
            $save_result = MLF_Session_Forms_Manager::save_form_response(
                $session_id,
                $registration_id,
                $custom_form_data
            );
            
            if ($save_result) {
                wp_send_json_success(array('message' => 'Vos réponses ont été mises à jour avec succès.'));
            } else {
                wp_send_json_error(array('message' => 'Erreur lors de la sauvegarde.'));
            }
        } else {
            wp_send_json_error(array('message' => 'Aucune donnée à sauvegarder.'));
        }
    }

    /**
     * Afficher la vue détaillée d'une session sur une page dédiée.
     */
    private function display_session_details_page($session_id) {
        $session = MLF_Database_Manager::get_game_session($session_id);
        
        if (!$session) {
            return '<p class="mlf-error">' . __('Session non trouvée.', 'mlf') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="mlf-session-details-page">
            <?php
            // Afficher le message de succès si mis à jour
            if (isset($_GET['mlf_message']) && $_GET['mlf_message'] === 'updated') {
                ?>
                <div class="mlf-success-message">
                    <p><strong><?php _e('✅ Vos réponses ont été mises à jour avec succès !', 'mlf'); ?></strong></p>
                </div>
                <?php
            }
            ?>
            
            <div class="mlf-breadcrumb">
                <a href="<?php echo esc_url(remove_query_arg(array('action', 'session_id', 'mlf_message'))); ?>" class="mlf-back-btn">
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
                        <span class="mlf-game-type mlf-game-type-murder">
                            <?php _e('Murder', 'mlf'); ?>
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
                        <?php if (!empty($session['min_players'])): ?>
                            <span class="mlf-min-players"><?php printf(__('(minimum %d)', 'mlf'), intval($session['min_players'])); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($session['age_requirement'])): ?>
                        <div class="mlf-meta-item">
                            <strong><?php _e('Âge requis:', 'mlf'); ?></strong>
                            <?php echo esc_html($session['age_requirement']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($session['intention_note'])): ?>
                    <div class="mlf-session-intention">
                        <h3><?php _e('Note d\'intention', 'mlf'); ?></h3>
                        <?php echo wp_kses_post(wpautop($session['intention_note'])); ?>
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
                    
                    // Vérifier si l'utilisateur est déjà inscrit avec un statut confirmé
                    if (is_user_logged_in()) {
                        $current_user = wp_get_current_user();
                        $existing_registration = MLF_Database_Manager::get_user_registration($session_id, $current_user->ID);
                        $user_registered = !empty($existing_registration) && $existing_registration['registration_status'] === 'confirme';
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
                                <?php _e('Vous êtes inscrit à cette session', 'mlf'); ?>
                            </p>
                            <span class="mlf-btn mlf-btn-success mlf-btn-large mlf-btn-disabled">
                                <?php _e('Inscription confirmée', 'mlf'); ?>
                            </span>
                        </div>
                    <?php elseif (!empty($existing_registration)): ?>
                        <div class="mlf-user-pending">
                            <p class="mlf-registration-status">
                                <?php 
                                switch($existing_registration['registration_status']) {
                                    case 'en_attente':
                                        echo '<span class="mlf-status-icon">⏳</span>';
                                        _e('Votre inscription est en attente de validation', 'mlf');
                                        break;
                                    case 'annule':
                                        echo '<span class="mlf-status-icon">❌</span>';
                                        _e('Votre inscription a été annulée', 'mlf');
                                        break;
                                    case 'liste_attente':
                                        echo '<span class="mlf-status-icon">📋</span>';
                                        _e('Vous êtes sur la liste d\'attente', 'mlf');
                                        break;
                                    default:
                                        echo '<span class="mlf-status-icon">❓</span>';
                                        printf(__('Statut: %s', 'mlf'), esc_html($existing_registration['registration_status']));
                                }
                                ?>
                            </p>
                            <?php if ($existing_registration['registration_status'] === 'annule'): ?>
                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'register', 'session_id' => $session_id))); ?>" 
                                   class="mlf-btn mlf-btn-primary mlf-btn-large">
                                    <?php _e('S\'inscrire à nouveau', 'mlf'); ?>
                                </a>
                            <?php else: ?>
                                <span class="mlf-btn mlf-btn-secondary mlf-btn-large mlf-btn-disabled">
                                    <?php _e('En attente', 'mlf'); ?>
                                </span>
                            <?php endif; ?>
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
                
                <?php
                // Afficher le formulaire custom et les fiches de personnage pour les joueurs inscrits avec inscription confirmée
                if (is_user_logged_in()) {
                    $current_user = wp_get_current_user();
                    $existing_registration = MLF_Database_Manager::get_user_registration($session_id, $current_user->ID);
                    
                    if ($existing_registration && $existing_registration['registration_status'] === 'confirme') {
                        // L'utilisateur a une inscription confirmée, afficher le formulaire custom s'il existe
                        if (class_exists('MLF_Session_Forms_Manager')) {
                            $custom_form = MLF_Session_Forms_Manager::get_session_form($session_id);
                            if ($custom_form && !empty($custom_form['form_fields'])) {
                                $existing_custom_responses = MLF_Session_Forms_Manager::get_form_response($session_id, $existing_registration['id']);
                                ?>
                                <div class="mlf-registered-user-form">
                                    <h3><?php echo esc_html($custom_form['form_title'] ?? __('Formulaire spécifique à cette session', 'mlf')); ?></h3>
                                    <?php if (!empty($custom_form['form_description'])): ?>
                                        <p class="mlf-form-description"><?php echo wp_kses_post($custom_form['form_description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="mlf-registration-status-notice">
                                        <p><strong><?php _e('Vous êtes inscrit à cette session.', 'mlf'); ?></strong></p>
                                        <p><em><?php _e('Vous pouvez consulter ou modifier vos réponses ci-dessous.', 'mlf'); ?></em></p>
                                    </div>
                                    
                                    <form class="mlf-custom-form-readonly" method="post" action="">
                                        <?php wp_nonce_field('mlf_update_responses', 'mlf_responses_nonce'); ?>
                                        <input type="hidden" name="action" value="mlf_update_custom_responses" />
                                        <input type="hidden" name="session_id" value="<?php echo esc_attr($session_id); ?>" />
                                        <input type="hidden" name="registration_id" value="<?php echo esc_attr($existing_registration['id']); ?>" />
                                        
                                        <?php
                                        foreach ($custom_form['form_fields'] as $field_index => $field) {
                                            $field_name = 'field_' . $field_index;
                                            $field_value = '';
                                            
                                            // Pré-remplir avec les réponses existantes
                                            if ($existing_custom_responses && isset($existing_custom_responses['response_data'][$field_name])) {
                                                $field_value = $existing_custom_responses['response_data'][$field_name];
                                            }
                                            
                            echo '<div class="mlf-form-group mlf-custom-field">';
                            
                            // Pour les checkboxes, on n'affiche pas de label séparé car le label fait partie de la checkbox
                            if ($field['type'] !== 'checkbox') {
                                echo '<label for="' . esc_attr($field_name) . '">';
                                echo esc_html($field['label'] ?? '');
                                if (!empty($field['required'])) {
                                    echo ' <span class="mlf-required">*</span>';
                                }
                                echo '</label>';
                            }                                            switch ($field['type']) {
                                                case 'text':
                                                case 'email':
                                                case 'number':
                                                    echo '<input type="' . esc_attr($field['type']) . '" ';
                                                    echo 'id="' . esc_attr($field_name) . '" ';
                                                    echo 'name="' . esc_attr($field_name) . '" ';
                                                    echo 'value="' . esc_attr($field_value) . '" ';
                                                    if (!empty($field['placeholder'])) {
                                                        echo 'placeholder="' . esc_attr($field['placeholder']) . '" ';
                                                    }
                                                    if (!empty($field['required'])) {
                                                        echo 'required ';
                                                    }
                                                    echo '/>';
                                                    break;
                                                    
                                                case 'textarea':
                                                    echo '<textarea ';
                                                    echo 'id="' . esc_attr($field_name) . '" ';
                                                    echo 'name="' . esc_attr($field_name) . '" ';
                                                    echo 'rows="' . esc_attr($field['rows'] ?? 3) . '" ';
                                                    if (!empty($field['placeholder'])) {
                                                        echo 'placeholder="' . esc_attr($field['placeholder']) . '" ';
                                                    }
                                                    if (!empty($field['required'])) {
                                                        echo 'required ';
                                                    }
                                                    echo '>' . esc_textarea($field_value) . '</textarea>';
                                                    break;
                                                    
                                                case 'select':
                                                    echo '<select ';
                                                    echo 'id="' . esc_attr($field_name) . '" ';
                                                    echo 'name="' . esc_attr($field_name) . '" ';
                                                    if (!empty($field['required'])) {
                                                        echo 'required ';
                                                    }
                                                    echo '>';
                                                    
                                                    if (!empty($field['placeholder'])) {
                                                        echo '<option value="">' . esc_html($field['placeholder']) . '</option>';
                                                    }
                                                    
                                                    if (!empty($field['options'])) {
                                                        foreach ($field['options'] as $option) {
                                                            $selected = ($field_value === $option) ? 'selected' : '';
                                                            echo '<option value="' . esc_attr($option) . '" ' . $selected . '>' . esc_html($option) . '</option>';
                                                        }
                                                    }
                                                    echo '</select>';
                                                    break;
                                                    
                                                case 'radio':
                                                    if (!empty($field['options'])) {
                                                        echo '<div class="mlf-radio-group">';
                                                        foreach ($field['options'] as $option_index => $option) {
                                                            $radio_id = $field_name . '_' . $option_index;
                                                            $checked = ($field_value === $option) ? 'checked' : '';
                                                            echo '<label class="mlf-radio-label">';
                                                            echo '<input type="radio" ';
                                                            echo 'id="' . esc_attr($radio_id) . '" ';
                                                            echo 'name="' . esc_attr($field_name) . '" ';
                                                            echo 'value="' . esc_attr($option) . '" ';
                                                            echo $checked . ' ';
                                                            if (!empty($field['required'])) {
                                                                echo 'required ';
                                                            }
                                                            echo '/>';
                                                            echo '<span>' . esc_html($option) . '</span>';
                                                            echo '</label>';
                                                        }
                                                        echo '</div>';
                                                    }
                                                    break;
                                                    
                                                case 'checkbox':
                                                    $checked = !empty($field_value) ? 'checked' : '';
                                                    echo '<label class="mlf-checkbox-label">';
                                                    echo '<input type="checkbox" ';
                                                    echo 'id="' . esc_attr($field_name) . '" ';
                                                    echo 'name="' . esc_attr($field_name) . '" ';
                                                    echo 'value="1" ';
                                                    echo $checked . ' ';
                                                    if (!empty($field['required'])) {
                                                        echo 'required ';
                                                    }
                                                    echo '/>';
                                                    echo '<span>' . esc_html($field['label'] ?? '');
                                                    if (!empty($field['required'])) {
                                                        echo ' <span class="mlf-required">*</span>';
                                                    }
                                                    echo '</span>';
                                                    echo '</label>';
                                                    break;
                                            }
                                            
                                            if (!empty($field['description'])) {
                                                echo '<small class="mlf-field-description">' . esc_html($field['description']) . '</small>';
                                            }
                                            
                                            echo '</div>';
                                        }
                                        ?>
                                        
                                        <div class="mlf-form-actions">
                                            <button type="submit" class="mlf-btn mlf-btn-primary">
                                                <?php _e('Mettre à jour mes réponses', 'mlf'); ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <?php
                            }
                        }
                        
                        // Afficher ses fiches de personnage
                        echo $this->display_user_character_sheets($session_id, $current_user->ID);
                    }
                }
                ?>
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
        
        if (!$session) {
            return '<p class="mlf-error">' . __('Session non trouvée.', 'mlf') . '</p>';
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
        
        // Pour les checkboxes, on structure différemment le label
        if ($field_label && $field_type !== 'checkbox') {
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
                echo '<label class="mlf-checkbox-label">';
                echo '<input type="checkbox" id="' . $field_id . '" name="' . $field_name . '" value="1"';
                if ($field_required) {
                    echo ' required';
                }
                echo ' />';
                echo '<span>' . $field_label;
                if ($field_required) {
                    echo ' *';
                }
                echo '</span>';
                echo '</label>';
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
    
    /**
     * Afficher les fiches de personnage d'un utilisateur pour une session.
     */
    private function display_user_character_sheets($session_id, $user_id) {
        global $wpdb;
        
        // Récupérer les fiches de personnage du joueur pour cette session
        $character_sheets = $wpdb->get_results($wpdb->prepare(
            "SELECT cs.*, s.session_name 
             FROM {$wpdb->prefix}mlf_character_sheets cs
             LEFT JOIN {$wpdb->prefix}mlf_game_sessions s ON cs.session_id = s.id
             WHERE cs.session_id = %d AND cs.player_id = %d 
             AND (cs.is_private = 0 OR cs.uploaded_by = %d)
             ORDER BY cs.uploaded_at DESC",
            $session_id, $user_id, $user_id
        ), ARRAY_A);
        
        if (empty($character_sheets)) {
            return ''; // Pas de fiches, ne rien afficher
        }
        
        ob_start();
        ?>
        <div class="mlf-character-sheets-section">
            <h3><?php _e('📋 Vos fiches de personnage', 'mlf'); ?></h3>
            <div class="mlf-character-sheets-list">
                <?php foreach ($character_sheets as $sheet): ?>
                    <div class="mlf-character-sheet-item">
                        <div class="mlf-sheet-info">
                            <div class="mlf-sheet-name">
                                <?php echo esc_html($sheet['file_original_name']); ?>
                                <?php if ($sheet['is_private']): ?>
                                    <span class="mlf-private-badge" title="<?php _e('Fiche privée', 'mlf'); ?>">🔒</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($sheet['file_description'])): ?>
                                <div class="mlf-sheet-description">
                                    <?php echo esc_html($sheet['file_description']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mlf-sheet-meta">
                                <span class="mlf-sheet-type"><?php echo strtoupper($sheet['file_type']); ?></span>
                                <span class="mlf-sheet-size"><?php echo $this->format_file_size($sheet['file_size']); ?></span>
                                <span class="mlf-sheet-date"><?php echo date_i18n('d/m/Y H:i', strtotime($sheet['uploaded_at'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="mlf-sheet-actions">
                            <?php 
                            $download_url = $this->get_character_sheet_download_url($sheet['id']);
                            ?>
                            <a href="<?php echo esc_url($download_url); ?>" 
                               class="mlf-btn mlf-btn-small mlf-btn-secondary" 
                               target="_blank"
                               title="<?php _e('Télécharger la fiche', 'mlf'); ?>">
                                📥 <?php _e('Télécharger', 'mlf'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mlf-sheet-upload-section">
                <p class="mlf-upload-info">
                    <?php _e('Vous pouvez ajouter ou modifier vos fiches de personnage depuis votre espace personnel.', 'mlf'); ?>
                </p>
                <?php if (function_exists('get_permalink')): ?>
                    <a href="<?php echo esc_url(get_permalink(get_option('mlf_user_account_page_id'))); ?>" 
                       class="mlf-btn mlf-btn-primary mlf-btn-small">
                        <?php _e('Gérer mes fiches', 'mlf'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Formater la taille d'un fichier pour l'affichage.
     */
    private function format_file_size($bytes) {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
    
    /**
     * Générer l'URL de téléchargement d'une fiche de personnage.
     */
    private function get_character_sheet_download_url($sheet_id) {
        $nonce = wp_create_nonce('mlf_download_sheet_' . $sheet_id);
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        return $plugin_url . 'download-sheet.php?sheet_id=' . $sheet_id . '&nonce=' . $nonce;
    }
}
