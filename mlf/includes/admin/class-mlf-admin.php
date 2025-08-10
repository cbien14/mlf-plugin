<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * This class is responsible for handling the admin area functionality.
 */

class MLF_Admin {

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        // Supprimer tous les warnings pour cette classe
        $this->suppress_warnings();
        
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers seulement si les fonctions existent
        if (function_exists('wp_verify_nonce')) {
            add_action('wp_ajax_mlf_create_session', array($this, 'handle_create_session'));
            add_action('wp_ajax_mlf_delete_session', array($this, 'handle_delete_session'));
            add_action('wp_ajax_mlf_update_session', array($this, 'handle_update_session'));
            add_action('wp_ajax_mlf_confirm_registration', array($this, 'handle_confirm_registration'));
        }
    }

    /**
     * Supprimer tous les warnings pour cette classe.
     */
    private function suppress_warnings() {
        // Buffer de sortie pour capturer et nettoyer les erreurs
        if (!ob_get_level()) {
            ob_start();
        }
        
        // Gérer les erreurs au niveau de la classe
        error_reporting(E_ERROR | E_PARSE);
    }

    /**
     * Add admin menus.
     */
    public function add_admin_menus() {
        // Main menu
        add_menu_page(
            __('Sessions de jeu MLF', 'mlf'),
            __('Sessions MLF', 'mlf'),
            'manage_options',
            'mlf-sessions',
            array($this, 'render_sessions_page'),
            'dashicons-games',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'mlf-sessions',
            __('Toutes les sessions', 'mlf'),
            __('Toutes les sessions', 'mlf'),
            'manage_options',
            'mlf-sessions',
            array($this, 'render_sessions_page')
        );
        
        add_submenu_page(
            'mlf-sessions',
            __('Nouvelle session', 'mlf'),
            __('Nouvelle session', 'mlf'),
            'manage_options',
            'mlf-new-session',
            array($this, 'render_new_session_page')
        );
        
        add_submenu_page(
            'mlf-sessions',
            __('Inscriptions', 'mlf'),
            __('Inscriptions', 'mlf'),
            'manage_options',
            'mlf-registrations',
            array($this, 'render_registrations_page')
        );
        
        add_submenu_page(
            'mlf-sessions',
            __('Paramètres', 'mlf'),
            __('Paramètres', 'mlf'),
            'manage_options',
            'mlf-settings',
            array($this, 'render_settings_page')
        );
        
        // Hidden submenu for editing sessions
        add_submenu_page(
            null, // Hidden from menu
            __('Modifier la session', 'mlf'),
            __('Modifier la session', 'mlf'),
            'manage_options',
            'mlf-edit-session',
            array($this, 'render_edit_session_page')
        );
        
        // Hidden submenu for managing session-specific forms
        add_submenu_page(
            null, // Hidden from menu
            __('Gérer le formulaire de session', 'mlf'),
            __('Formulaire de session', 'mlf'),
            'manage_options',
            'mlf-session-form',
            array($this, 'render_session_form_page')
        );
    }

    /**
     * Render the sessions management page.
     */
    public function render_sessions_page() {
        $sessions = MLF_Database_Manager::get_game_sessions(array(
            'date_from' => date('Y-m-d'),
            'limit' => 50
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Sessions de jeu', 'mlf'); ?></h1>
            
            <div class="mlf-admin-actions">
                <a href="<?php echo admin_url('admin.php?page=mlf-new-session'); ?>" class="button button-primary">
                    <?php _e('Créer une nouvelle session', 'mlf'); ?>
                </a>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Nom de la session', 'mlf'); ?></th>
                        <th><?php _e('Type de jeu', 'mlf'); ?></th>
                        <th><?php _e('Date', 'mlf'); ?></th>
                        <th><?php _e('Heure', 'mlf'); ?></th>
                        <th><?php _e('Joueurs', 'mlf'); ?></th>
                        <th><?php _e('Formulaire', 'mlf'); ?></th>
                        <th><?php _e('Statut', 'mlf'); ?></th>
                        <th><?php _e('Actions', 'mlf'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sessions)): ?>
                        <tr>
                            <td colspan="8"><?php _e('Aucune session trouvée.', 'mlf'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td><strong><?php echo esc_html($session['session_name'] ?? ''); ?></strong></td>
                                <td><?php echo esc_html($this->get_game_type_label($session['game_type'] ?? '')); ?></td>
                                <td><?php echo esc_html($session['session_date'] ? date('d/m/Y', strtotime($session['session_date'])) : ''); ?></td>
                                <td><?php echo esc_html($session['session_time'] ? date('H:i', strtotime($session['session_time'])) : ''); ?></td>
                                <td><?php echo intval($session['current_players'] ?? 0); ?>/<?php echo intval($session['max_players'] ?? 0); ?></td>
                                <td>
                                    <?php 
                                    if (MLF_Session_Forms_Manager::session_has_form($session['id'])) {
                                        $form = MLF_Session_Forms_Manager::get_session_form($session['id']);
                                        if ($form) {
                                            echo '<span title="' . esc_attr($form['form_description']) . '">📋 ' . esc_html($form['form_title']) . '</span>';
                                        }
                                    } else {
                                        echo '<span style="color: #999;">Aucun formulaire</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($this->get_status_label($session['status'] ?? '')); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=mlf-edit-session&id=' . $session['id']); ?>" class="button button-small">
                                        <?php _e('Modifier', 'mlf'); ?>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=mlf-session-form&session_id=' . $session['id']); ?>" class="button button-small" title="Gérer le formulaire spécifique à cette session">
                                        📋 Formulaire
                                    </a>
                                    <button class="button button-small mlf-delete-session" data-session-id="<?php echo $session['id']; ?>">
                                        <?php _e('Supprimer', 'mlf'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render the new session page.
     */
    public function render_new_session_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Créer une nouvelle session', 'mlf'); ?></h1>
            
            <div id="mlf-session-message" class="notice" style="display: none;"></div>
            
            <form id="mlf-session-form">
                <?php wp_nonce_field('mlf_create_session', 'mlf_session_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="session_name"><?php _e('Nom de la session', 'mlf'); ?></label></th>
                        <td><input type="text" id="session_name" name="session_name" class="regular-text" required /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="game_type"><?php _e('Type de jeu', 'mlf'); ?></label></th>
                        <td>
                            <select id="game_type" name="game_type" required>
                                <option value=""><?php _e('Sélectionner le type', 'mlf'); ?></option>
                                <option value="jdr"><?php _e('JDR', 'mlf'); ?></option>
                                <option value="murder"><?php _e('Murder', 'mlf'); ?></option>
                                <option value="jeu_de_societe"><?php _e('Jeu de société', 'mlf'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="session_date"><?php _e('Date de la session', 'mlf'); ?></label></th>
                        <td><input type="date" id="session_date" name="session_date" required /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="session_time"><?php _e('Heure', 'mlf'); ?></label></th>
                        <td><input type="time" id="session_time" name="session_time" value="20:00" required /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="duration_minutes"><?php _e('Durée (minutes)', 'mlf'); ?></label></th>
                        <td><input type="number" id="duration_minutes" name="duration_minutes" value="120" min="30" max="480" /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="max_players"><?php _e('Nombre maximum de joueurs', 'mlf'); ?></label></th>
                        <td><input type="number" id="max_players" name="max_players" value="6" min="1" max="20" required /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="location"><?php _e('Lieu', 'mlf'); ?></label></th>
                        <td><input type="text" id="location" name="location" class="regular-text" /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="difficulty_level"><?php _e('Niveau de difficulté', 'mlf'); ?></label></th>
                        <td>
                            <select id="difficulty_level" name="difficulty_level">
                                <option value="debutant"><?php _e('Débutant', 'mlf'); ?></option>
                                <option value="intermediaire"><?php _e('Intermédiaire', 'mlf'); ?></option>
                                <option value="avance"><?php _e('Avancé', 'mlf'); ?></option>
                                <option value="expert"><?php _e('Expert', 'mlf'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="description"><?php _e('Description', 'mlf'); ?></label></th>
                        <td><textarea id="description" name="description" rows="4" class="large-text" placeholder="Description générale de la session..."></textarea></td>
                    </tr>
                    
                    <tr>
                        <th><label for="synopsis"><?php _e('Synopsis', 'mlf'); ?></label></th>
                        <td><textarea id="synopsis" name="synopsis" rows="4" class="large-text" placeholder="Synopsis détaillé de l'histoire ou du scénario..."></textarea></td>
                    </tr>
                    
                    <tr>
                        <th><label for="trigger_warnings"><?php _e('Trigger warnings', 'mlf'); ?></label></th>
                        <td><textarea id="trigger_warnings" name="trigger_warnings" rows="3" class="large-text" placeholder="Avertissements sur les thèmes sensibles abordés..."></textarea></td>
                    </tr>
                    
                    <tr>
                        <th><label for="safety_tools"><?php _e('Outils de sécurité', 'mlf'); ?></label></th>
                        <td><textarea id="safety_tools" name="safety_tools" rows="2" class="large-text" placeholder="Cartes X, lignes et voiles, etc."></textarea></td>
                    </tr>
                    
                    <tr>
                        <th><label for="prerequisites"><?php _e('Prérequis', 'mlf'); ?></label></th>
                        <td><textarea id="prerequisites" name="prerequisites" rows="2" class="large-text" placeholder="Connaissances requises, matériel à apporter..."></textarea></td>
                    </tr>
                    
                    <tr>
                        <th><label for="additional_info"><?php _e('Informations additionnelles', 'mlf'); ?></label></th>
                        <td><textarea id="additional_info" name="additional_info" rows="3" class="large-text" placeholder="Autres informations importantes..."></textarea></td>
                    </tr>
                    
                    <tr>
                        <th><label for="banner_image"><?php _e('Image bannière', 'mlf'); ?></label></th>
                        <td>
                            <div class="mlf-image-upload-container">
                                <input type="hidden" id="banner_image_url" name="banner_image_url" />
                                <button type="button" class="button mlf-upload-image-btn" data-target="banner_image_url" data-preview="banner_image_preview">
                                    <?php _e('Choisir une image bannière', 'mlf'); ?>
                                </button>
                                <button type="button" class="button mlf-remove-image-btn" data-target="banner_image_url" data-preview="banner_image_preview" style="display: none;">
                                    <?php _e('Supprimer', 'mlf'); ?>
                                </button>
                                <div id="banner_image_preview" class="mlf-image-preview" style="margin-top: 10px;"></div>
                                <p class="description"><?php _e('Image affichée en haut de la session (recommandé: 1200x300px)', 'mlf'); ?></p>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="background_image"><?php _e('Image de fond', 'mlf'); ?></label></th>
                        <td>
                            <div class="mlf-image-upload-container">
                                <input type="hidden" id="background_image_url" name="background_image_url" />
                                <button type="button" class="button mlf-upload-image-btn" data-target="background_image_url" data-preview="background_image_preview">
                                    <?php _e('Choisir une image de fond', 'mlf'); ?>
                                </button>
                                <button type="button" class="button mlf-remove-image-btn" data-target="background_image_url" data-preview="background_image_preview" style="display: none;">
                                    <?php _e('Supprimer', 'mlf'); ?>
                                </button>
                                <div id="background_image_preview" class="mlf-image-preview" style="margin-top: 10px;"></div>
                                <p class="description"><?php _e('Image utilisée comme fond de la session (recommandé: 1920x1080px)', 'mlf'); ?></p>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="is_public"><?php _e('Visibilité', 'mlf'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_public" name="is_public" value="1" checked />
                                <?php _e('Session publique (visible par tous)', 'mlf'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="requires_approval"><?php _e('Modération', 'mlf'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="requires_approval" name="requires_approval" value="1" />
                                <?php _e('Inscription soumise à approbation', 'mlf'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    
                    <tr>
                        <th><label for="registration_deadline"><?php _e('Date limite d\'inscription', 'mlf'); ?></label></th>
                        <td><input type="datetime-local" id="registration_deadline" name="registration_deadline" /></td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" id="mlf-submit-button" class="button-primary" value="<?php _e('Créer la session', 'mlf'); ?>" />
                    <span id="mlf-loading" class="spinner" style="display: none;"></span>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render the registrations page.
     */
    public function render_registrations_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Gestion des inscriptions', 'mlf'); ?></h1>
            <p><?php _e('Ici vous pouvez gérer toutes les inscriptions aux sessions.', 'mlf'); ?></p>
        </div>
        <?php
    }

    /**
     * Register the admin area settings.
     */
    public function register_settings() {
        register_setting('mlf_settings', 'mlf_enable_email_notifications');
        register_setting('mlf_settings', 'mlf_default_session_duration');
        register_setting('mlf_settings', 'mlf_max_players_default');
        register_setting('mlf_settings', 'mlf_default_location');
        register_setting('mlf_settings', 'mlf_contact_email');
    }

    /**
     * Render the admin settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Paramètres MLF', 'mlf'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('mlf_settings'); ?>
                <?php do_settings_sections('mlf_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="mlf_enable_email_notifications"><?php _e('Notifications par email', 'mlf'); ?></label></th>
                        <td>
                            <input type="checkbox" id="mlf_enable_email_notifications" name="mlf_enable_email_notifications" value="1" <?php checked(get_option('mlf_enable_email_notifications'), 1); ?> />
                            <label for="mlf_enable_email_notifications"><?php _e('Activer les notifications par email', 'mlf'); ?></label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="mlf_default_session_duration"><?php _e('Durée par défaut (minutes)', 'mlf'); ?></label></th>
                        <td><input type="number" id="mlf_default_session_duration" name="mlf_default_session_duration" value="<?php echo esc_attr(get_option('mlf_default_session_duration', 120)); ?>" /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="mlf_max_players_default"><?php _e('Nombre de joueurs par défaut', 'mlf'); ?></label></th>
                        <td><input type="number" id="mlf_max_players_default" name="mlf_max_players_default" value="<?php echo esc_attr(get_option('mlf_max_players_default', 6)); ?>" /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="mlf_default_location"><?php _e('Lieu par défaut', 'mlf'); ?></label></th>
                        <td><input type="text" id="mlf_default_location" name="mlf_default_location" value="<?php echo esc_attr(get_option('mlf_default_location')); ?>" class="regular-text" /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="mlf_contact_email"><?php _e('Email de contact', 'mlf'); ?></label></th>
                        <td><input type="email" id="mlf_contact_email" name="mlf_contact_email" value="<?php echo esc_attr(get_option('mlf_contact_email')); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the edit session page.
     */
    public function render_edit_session_page() {
        // Check if session ID is provided
        $session_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$session_id) {
            echo '<div class="wrap"><h1>Erreur</h1><p>ID de session manquant.</p></div>';
            return;
        }
        
        // Get session data
        $session = MLF_Database_Manager::get_game_session($session_id);
        
        if (!$session) {
            echo '<div class="wrap"><h1>Erreur</h1><p>Session introuvable.</p></div>';
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Modifier la session', 'mlf'); ?> : <?php echo esc_html($session['session_name'] ?? ''); ?></h1>
            
            <div id="mlf-session-message" class="notice" style="display: none;"></div>
            
            <form id="mlf-edit-session-form" data-session-id="<?php echo $session_id; ?>">
                <?php wp_nonce_field('mlf_update_session', 'mlf_session_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="session_name"><?php _e('Nom de la session', 'mlf'); ?></label></th>
                        <td><input type="text" id="session_name" name="session_name" class="regular-text" value="<?php echo $this->safe_attr($session['session_name']); ?>" required /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="game_type"><?php _e('Type de jeu', 'mlf'); ?></label></th>
                        <td>
                            <select id="game_type" name="game_type" required>
                                <option value=""><?php _e('Sélectionner le type', 'mlf'); ?></option>
                                <option value="jdr" <?php selected($session['game_type'], 'jdr'); ?>><?php _e('JDR', 'mlf'); ?></option>
                                <option value="murder" <?php selected($session['game_type'], 'murder'); ?>><?php _e('Murder', 'mlf'); ?></option>
                                <option value="jeu_de_societe" <?php selected($session['game_type'], 'jeu_de_societe'); ?>><?php _e('Jeu de société', 'mlf'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="session_date"><?php _e('Date de la session', 'mlf'); ?></label></th>
                        <td><input type="date" id="session_date" name="session_date" value="<?php echo $this->safe_attr($session['session_date']); ?>" required /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="session_time"><?php _e('Heure', 'mlf'); ?></label></th>
                        <td><input type="time" id="session_time" name="session_time" value="<?php echo $this->safe_attr($session['session_time']); ?>" required /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="duration_minutes"><?php _e('Durée (minutes)', 'mlf'); ?></label></th>
                        <td><input type="number" id="duration_minutes" name="duration_minutes" value="<?php echo esc_attr($session['duration_minutes']); ?>" min="30" max="480" /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="max_players"><?php _e('Nombre maximum de joueurs', 'mlf'); ?></label></th>
                        <td><input type="number" id="max_players" name="max_players" value="<?php echo esc_attr($session['max_players']); ?>" min="1" max="20" required /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="location"><?php _e('Lieu', 'mlf'); ?></label></th>
                        <td><input type="text" id="location" name="location" class="regular-text" value="<?php echo esc_attr($session['location']); ?>" /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="difficulty_level"><?php _e('Niveau de difficulté', 'mlf'); ?></label></th>
                        <td>
                            <select id="difficulty_level" name="difficulty_level">
                                <option value="debutant" <?php selected($session['difficulty_level'], 'debutant'); ?>><?php _e('Débutant', 'mlf'); ?></option>
                                <option value="intermediaire" <?php selected($session['difficulty_level'], 'intermediaire'); ?>><?php _e('Intermédiaire', 'mlf'); ?></option>
                                <option value="avance" <?php selected($session['difficulty_level'], 'avance'); ?>><?php _e('Avancé', 'mlf'); ?></option>
                                <option value="expert" <?php selected($session['difficulty_level'], 'expert'); ?>><?php _e('Expert', 'mlf'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="description"><?php _e('Description', 'mlf'); ?></label></th>
                        <td><textarea id="description" name="description" rows="4" class="large-text" placeholder="Description générale de la session..."><?php echo $this->safe_textarea($session['description']); ?></textarea></td>
                    </tr>
                    
                    <tr>
                        <th><label for="synopsis"><?php _e('Synopsis', 'mlf'); ?></label></th>
                        <td><textarea id="synopsis" name="synopsis" rows="4" class="large-text" placeholder="Synopsis détaillé de l'histoire ou du scénario..."><?php echo esc_textarea($session['synopsis']); ?></textarea></td>
                    </tr>
                    
                    <tr>
                        <th><label for="trigger_warnings"><?php _e('Trigger warnings', 'mlf'); ?></label></th>
                        <td><textarea id="trigger_warnings" name="trigger_warnings" rows="3" class="large-text" placeholder="Avertissements sur les thèmes sensibles abordés..."><?php echo esc_textarea($session['trigger_warnings']); ?></textarea></td>
                    </tr>
                    
                    <tr>
                        <th><label for="safety_tools"><?php _e('Outils de sécurité', 'mlf'); ?></label></th>
                        <td><textarea id="safety_tools" name="safety_tools" rows="2" class="large-text" placeholder="Cartes X, lignes et voiles, etc."><?php echo esc_textarea($session['safety_tools']); ?></textarea></td>
                    </tr>
                    
                    <tr>
                        <th><label for="prerequisites"><?php _e('Prérequis', 'mlf'); ?></label></th>
                        <td><textarea id="prerequisites" name="prerequisites" rows="2" class="large-text" placeholder="Connaissances requises, matériel à apporter..."><?php echo esc_textarea($session['prerequisites']); ?></textarea></td>
                    </tr>
                    
                    <tr>
                        <th><label for="additional_info"><?php _e('Informations additionnelles', 'mlf'); ?></label></th>
                        <td><textarea id="additional_info" name="additional_info" rows="3" class="large-text" placeholder="Autres informations importantes..."><?php echo esc_textarea($session['additional_info']); ?></textarea></td>
                    </tr>
                    
                    <tr>
                        <th><label for="banner_image"><?php _e('Image bannière', 'mlf'); ?></label></th>
                        <td>
                            <div class="mlf-image-upload-container">
                                <input type="hidden" id="banner_image_url" name="banner_image_url" value="<?php echo esc_attr($session['banner_image_url']); ?>" />
                                <button type="button" class="button mlf-upload-image-btn" data-target="banner_image_url" data-preview="banner_image_preview">
                                    <?php _e('Choisir une image bannière', 'mlf'); ?>
                                </button>
                                <button type="button" class="button mlf-remove-image-btn" data-target="banner_image_url" data-preview="banner_image_preview" style="<?php echo $session['banner_image_url'] ? '' : 'display: none;'; ?>">
                                    <?php _e('Supprimer', 'mlf'); ?>
                                </button>
                                <div id="banner_image_preview" class="mlf-image-preview" style="margin-top: 10px;">
                                    <?php if ($session['banner_image_url']): ?>
                                        <img src="<?php echo esc_url($session['banner_image_url']); ?>" alt="Banner" style="max-width: 300px; height: auto;" />
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php _e('Image affichée en haut de la session (recommandé: 1200x300px)', 'mlf'); ?></p>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="background_image"><?php _e('Image de fond', 'mlf'); ?></label></th>
                        <td>
                            <div class="mlf-image-upload-container">
                                <input type="hidden" id="background_image_url" name="background_image_url" value="<?php echo esc_attr($session['background_image_url']); ?>" />
                                <button type="button" class="button mlf-upload-image-btn" data-target="background_image_url" data-preview="background_image_preview">
                                    <?php _e('Choisir une image de fond', 'mlf'); ?>
                                </button>
                                <button type="button" class="button mlf-remove-image-btn" data-target="background_image_url" data-preview="background_image_preview" style="<?php echo $session['background_image_url'] ? '' : 'display: none;'; ?>">
                                    <?php _e('Supprimer', 'mlf'); ?>
                                </button>
                                <div id="background_image_preview" class="mlf-image-preview" style="margin-top: 10px;">
                                    <?php if ($session['background_image_url']): ?>
                                        <img src="<?php echo esc_url($session['background_image_url']); ?>" alt="Background" style="max-width: 300px; height: auto;" />
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php _e('Image utilisée comme fond de la session (recommandé: 1920x1080px)', 'mlf'); ?></p>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="is_public"><?php _e('Visibilité', 'mlf'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_public" name="is_public" value="1" <?php checked($session['is_public'], 1); ?> />
                                <?php _e('Session publique (visible par tous)', 'mlf'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="requires_approval"><?php _e('Modération', 'mlf'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="requires_approval" name="requires_approval" value="1" <?php checked($session['requires_approval'], 1); ?> />
                                <?php _e('Inscription soumise à approbation', 'mlf'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="registration_deadline"><?php _e('Date limite d\'inscription', 'mlf'); ?></label></th>
                        <td><input type="datetime-local" id="registration_deadline" name="registration_deadline" value="<?php echo esc_attr($session['registration_deadline']); ?>" /></td>
                    </tr>
                    
                    <tr>
                        <th><label for="status"><?php _e('Statut', 'mlf'); ?></label></th>
                        <td>
                            <select id="status" name="status">
                                <option value="planifiee" <?php selected($session['status'], 'planifiee'); ?>><?php _e('Planifiée', 'mlf'); ?></option>
                                <option value="en_cours" <?php selected($session['status'], 'en_cours'); ?>><?php _e('En cours', 'mlf'); ?></option>
                                <option value="terminee" <?php selected($session['status'], 'terminee'); ?>><?php _e('Terminée', 'mlf'); ?></option>
                                <option value="annulee" <?php selected($session['status'], 'annulee'); ?>><?php _e('Annulée', 'mlf'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" id="mlf-update-button" class="button-primary" value="<?php _e('Mettre à jour la session', 'mlf'); ?>" />
                    <a href="<?php echo admin_url('admin.php?page=mlf-sessions'); ?>" class="button"><?php _e('Annuler', 'mlf'); ?></a>
                    <span id="mlf-loading" class="spinner" style="display: none;"></span>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue admin-specific styles and scripts.
     */
    public function enqueue_admin_scripts($hook) {
        // Vérification de sécurité pour $hook
        if (empty($hook) || !is_string($hook)) {
            return;
        }
        
        if (mlf_safe_strpos($hook, 'mlf-') !== false) {
            // Enqueue WordPress media uploader
            wp_enqueue_media();
            
            // Construction sécurisée de l'URL du plugin
            $plugin_file = dirname(__DIR__, 2) . '/mlf-plugin.php';
            if (file_exists($plugin_file)) {
                $plugin_url = plugin_dir_url($plugin_file);
                
                wp_enqueue_script('mlf-admin-js', $plugin_url . 'includes/admin/js/mlf-admin.js', array('jquery'), '1.0.0', true);
                wp_enqueue_style('mlf-admin-css', $plugin_url . 'assets/css/mlf-plugin.css', array(), '1.0.0');
                
                wp_localize_script('mlf-admin-js', 'mlf_admin_ajax', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('mlf_admin_nonce'),
                ));
            }
        }
    }

    /**
     * Get game type label.
     */
    private function get_game_type_label($type) {
        if (empty($type)) {
            return '';
        }
        
        $labels = array(
            'all' => 'Tous les types',
            'jdr' => 'JDR',
            'murder' => 'Murder',
            'jeu_de_societe' => 'Jeu de société'
        );
        
        return isset($labels[$type]) ? $labels[$type] : $type;
    }

    /**
     * Get status label.
     */
    private function get_status_label($status) {
        if (empty($status)) {
            return '';
        }
        
        $labels = array(
            'planifiee' => 'Planifiée',
            'en_cours' => 'En cours',
            'terminee' => 'Terminée',
            'annulee' => 'Annulée'
        );
        
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
    
    /**
     * Safe attribute output - handles null values.
     */
    private function safe_attr($value) {
        return esc_attr($value ?? '');
    }
    
    /**
     * Safe textarea output - handles null values.
     */
    private function safe_textarea($value) {
        return esc_textarea($value ?? '');
    }

    /**
     * Handle session creation via AJAX.
     */
    public function handle_create_session() {
        if (!wp_verify_nonce($_POST['mlf_session_nonce'], 'mlf_create_session')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $session_data = array(
            'session_name' => sanitize_text_field($_POST['session_name']),
            'game_type' => sanitize_text_field($_POST['game_type']),
            'session_date' => sanitize_text_field($_POST['session_date']),
            'session_time' => sanitize_text_field($_POST['session_time']),
            'duration_minutes' => intval($_POST['duration_minutes']),
            'max_players' => intval($_POST['max_players']),
            'location' => sanitize_text_field($_POST['location']),
            'difficulty_level' => sanitize_text_field($_POST['difficulty_level']),
            'description' => sanitize_textarea_field($_POST['description']),
            'synopsis' => sanitize_textarea_field($_POST['synopsis']),
            'trigger_warnings' => sanitize_textarea_field($_POST['trigger_warnings']),
            'safety_tools' => sanitize_textarea_field($_POST['safety_tools']),
            'prerequisites' => sanitize_textarea_field($_POST['prerequisites']),
            'additional_info' => sanitize_textarea_field($_POST['additional_info']),
            'banner_image_url' => esc_url_raw($_POST['banner_image_url']),
            'background_image_url' => esc_url_raw($_POST['background_image_url']),
            'is_public' => isset($_POST['is_public']) ? 1 : 0,
            'requires_approval' => isset($_POST['requires_approval']) ? 1 : 0,
            'registration_deadline' => sanitize_text_field($_POST['registration_deadline']),
            'game_master_id' => get_current_user_id(),
            'game_master_name' => wp_get_current_user()->display_name
        );

        $session_id = MLF_Database_Manager::create_game_session($session_data);

        if ($session_id) {
            
            wp_send_json_success(array('session_id' => $session_id));
        } else {
            wp_send_json_error(array('message' => 'Failed to create session'));
        }
    }

    /**
     * Handle session deletion via AJAX.
     */
    public function handle_delete_session() {
        if (!wp_verify_nonce($_POST['nonce'], 'mlf_admin_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $session_id = intval($_POST['session_id']);
        if (!$session_id) {
            wp_send_json_error(array('message' => 'Invalid session ID'));
        }

        // Verify the session exists and belongs to the current user or user has admin rights
        $session = MLF_Database_Manager::get_game_session($session_id);
        if (!$session) {
            wp_send_json_error(array('message' => 'Session not found'));
        }

        // Delete the session (this will also delete associated registrations)
        $result = MLF_Database_Manager::delete_game_session($session_id);

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Session supprimée avec succès',
                'session_id' => $session_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de la suppression de la session'));
        }
    }

    /**
     * Handle session update via AJAX.
     */
    public function handle_update_session() {
        if (!wp_verify_nonce($_POST['nonce'], 'mlf_admin_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $session_id = intval($_POST['session_id']);
        if (!$session_id) {
            wp_send_json_error(array('message' => 'Invalid session ID'));
        }

        // Prepare update data (similar to create but for updating)
        $session_data = array(
            'session_name' => sanitize_text_field($_POST['session_name']),
            'game_type' => sanitize_text_field($_POST['game_type']),
            'session_date' => sanitize_text_field($_POST['session_date']),
            'session_time' => sanitize_text_field($_POST['session_time']),
            'duration_minutes' => intval($_POST['duration_minutes']),
            'max_players' => intval($_POST['max_players']),
            'location' => sanitize_text_field($_POST['location']),
            'difficulty_level' => sanitize_text_field($_POST['difficulty_level']),
            'description' => sanitize_textarea_field($_POST['description']),
            'synopsis' => sanitize_textarea_field($_POST['synopsis']),
            'trigger_warnings' => sanitize_textarea_field($_POST['trigger_warnings']),
            'safety_tools' => sanitize_textarea_field($_POST['safety_tools']),
            'prerequisites' => sanitize_textarea_field($_POST['prerequisites']),
            'additional_info' => sanitize_textarea_field($_POST['additional_info']),
            'banner_image_url' => esc_url_raw($_POST['banner_image_url']),
            'background_image_url' => esc_url_raw($_POST['background_image_url']),
            'is_public' => isset($_POST['is_public']) ? 1 : 0,
            'requires_approval' => isset($_POST['requires_approval']) ? 1 : 0,
            'registration_deadline' => sanitize_text_field($_POST['registration_deadline']),
            'status' => sanitize_text_field($_POST['status'])
        );

        $result = MLF_Database_Manager::update_game_session($session_id, $session_data);

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Session mise à jour avec succès',
                'session_id' => $session_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de la mise à jour de la session'));
        }
    }

    /**
     * Render the session-specific form management page.
     */
    public function render_session_form_page() {
        $session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
        
        if (!$session_id) {
            echo '<div class="notice notice-error"><p>ID de session invalide.</p></div>';
            return;
        }

        // Récupérer les détails de la session
        $sessions = MLF_Database_Manager::get_game_sessions(array('id' => $session_id));
        if (empty($sessions)) {
            echo '<div class="notice notice-error"><p>Session non trouvée.</p></div>';
            return;
        }
        $session = $sessions[0];

        // Initialiser le gestionnaire de formulaires spécifiques aux sessions
        if (!class_exists('MLF_Session_Forms_Manager')) {
            require_once MLF_PLUGIN_DIR . 'includes/class-mlf-session-forms-manager.php';
        }
        $session_forms_manager = new MLF_Session_Forms_Manager();

        // Traitement des soumissions de formulaire
        if (isset($_POST['submit_session_form']) && wp_verify_nonce($_POST['mlf_session_form_nonce'], 'mlf_session_form')) {
            $form_data = array(
                'form_title' => sanitize_text_field($_POST['form_title']),
                'form_description' => sanitize_textarea_field($_POST['form_description']),
                'form_fields' => json_encode($_POST['form_fields'] ?? array()),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            );

            $result = $session_forms_manager->save_session_form($session_id, $form_data);
            if ($result) {
                echo '<div class="notice notice-success"><p>Formulaire enregistré avec succès !</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Erreur lors de l\'enregistrement du formulaire.</p></div>';
            }
        }

        // Récupérer le formulaire existant
        $existing_form = $session_forms_manager->get_session_form($session_id);
        
        ?>
        <div class="wrap">
            <h1>Formulaire personnalisé pour : <?php echo esc_html($session['session_name']); ?></h1>
            <p>Gérez le formulaire d'inscription spécifique à cette session de jeu.</p>

            <form method="post">
                <?php wp_nonce_field('mlf_session_form', 'mlf_session_form_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="form_title">Titre du formulaire</label></th>
                        <td>
                            <input type="text" id="form_title" name="form_title" 
                                   value="<?php echo esc_attr($existing_form['form_title'] ?? 'Formulaire d\'inscription - ' . $session['session_name']); ?>" 
                                   class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="form_description">Description</label></th>
                        <td>
                            <textarea id="form_description" name="form_description" rows="3" class="large-text"><?php 
                                echo esc_textarea($existing_form['form_description'] ?? 'Formulaire d\'inscription personnalisé pour cette session.'); 
                            ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="is_active">Statut</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_active" name="is_active" value="1" 
                                       <?php checked($existing_form['is_active'] ?? 1, 1); ?>>
                                Formulaire actif
                            </label>
                        </td>
                    </tr>
                </table>

                <h2>Champs du formulaire</h2>
                <div id="form-fields-container">
                    <?php
                    $fields = array();
                    if ($existing_form && !empty($existing_form['form_fields'])) {
                        $fields = json_decode($existing_form['form_fields'], true) ?: array();
                    }
                    
                    // Si aucun champ, ajouter les champs par défaut
                    if (empty($fields)) {
                        $fields = $session_forms_manager->get_default_form_fields();
                    }
                    
                    foreach ($fields as $index => $field): ?>
                        <div class="form-field-row" data-field-index="<?php echo $index; ?>">
                            <h4>Champ <?php echo $index + 1; ?></h4>
                            <table class="form-table">
                                <tr>
                                    <th>Label</th>
                                    <td>
                                        <input type="text" name="form_fields[<?php echo $index; ?>][label]" 
                                               value="<?php echo esc_attr($field['label']); ?>" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Type</th>
                                    <td>
                                        <select name="form_fields[<?php echo $index; ?>][type]">
                                            <option value="text" <?php selected($field['type'], 'text'); ?>>Texte</option>
                                            <option value="textarea" <?php selected($field['type'], 'textarea'); ?>>Zone de texte</option>
                                            <option value="select" <?php selected($field['type'], 'select'); ?>>Liste déroulante</option>
                                            <option value="checkbox" <?php selected($field['type'], 'checkbox'); ?>>Case à cocher</option>
                                            <option value="email" <?php selected($field['type'], 'email'); ?>>Email</option>
                                            <option value="number" <?php selected($field['type'], 'number'); ?>>Nombre</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Obligatoire</th>
                                    <td>
                                        <input type="checkbox" name="form_fields[<?php echo $index; ?>][required]" 
                                               value="1" <?php checked($field['required'] ?? false, true); ?>>
                                    </td>
                                </tr>
                                <tr class="field-options" style="<?php echo $field['type'] === 'select' ? '' : 'display:none;'; ?>">
                                    <th>Options (une par ligne)</th>
                                    <td>
                                        <textarea name="form_fields[<?php echo $index; ?>][options]" rows="3"><?php 
                                            echo esc_textarea(is_array($field['options'] ?? '') ? implode("\n", $field['options']) : ($field['options'] ?? '')); 
                                        ?></textarea>
                                    </td>
                                </tr>
                            </table>
                            <button type="button" class="remove-field-btn button" style="margin-bottom: 20px;">Supprimer ce champ</button>
                            <hr>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" id="add-field-btn" class="button">Ajouter un champ</button>

                <p class="submit">
                    <input type="submit" name="submit_session_form" class="button-primary" value="Enregistrer le formulaire">
                    <a href="<?php echo admin_url('admin.php?page=mlf-sessions'); ?>" class="button">Retour aux sessions</a>
                </p>
            </form>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var fieldIndex = <?php echo count($fields); ?>;

            // Ajouter un nouveau champ
            $('#add-field-btn').click(function() {
                var newField = `
                    <div class="form-field-row" data-field-index="${fieldIndex}">
                        <h4>Champ ${fieldIndex + 1}</h4>
                        <table class="form-table">
                            <tr>
                                <th>Label</th>
                                <td><input type="text" name="form_fields[${fieldIndex}][label]" required></td>
                            </tr>
                            <tr>
                                <th>Type</th>
                                <td>
                                    <select name="form_fields[${fieldIndex}][type]">
                                        <option value="text">Texte</option>
                                        <option value="textarea">Zone de texte</option>
                                        <option value="select">Liste déroulante</option>
                                        <option value="checkbox">Case à cocher</option>
                                        <option value="email">Email</option>
                                        <option value="number">Nombre</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Obligatoire</th>
                                <td><input type="checkbox" name="form_fields[${fieldIndex}][required]" value="1"></td>
                            </tr>
                            <tr class="field-options" style="display:none;">
                                <th>Options (une par ligne)</th>
                                <td><textarea name="form_fields[${fieldIndex}][options]" rows="3"></textarea></td>
                            </tr>
                        </table>
                        <button type="button" class="remove-field-btn button" style="margin-bottom: 20px;">Supprimer ce champ</button>
                        <hr>
                    </div>
                `;
                $('#form-fields-container').append(newField);
                fieldIndex++;
            });

            // Supprimer un champ
            $(document).on('click', '.remove-field-btn', function() {
                $(this).closest('.form-field-row').remove();
            });

            // Afficher/masquer les options selon le type de champ
            $(document).on('change', 'select[name*="[type]"]', function() {
                var $row = $(this).closest('.form-field-row');
                var $optionsRow = $row.find('.field-options');
                
                if ($(this).val() === 'select') {
                    $optionsRow.show();
                } else {
                    $optionsRow.hide();
                }
            });
        });
        </script>
        <?php
    }
}