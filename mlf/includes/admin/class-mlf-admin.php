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
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers for admin
        add_action('wp_ajax_mlf_create_session', array($this, 'handle_create_session'));
        add_action('wp_ajax_mlf_update_session', array($this, 'handle_update_session'));
        add_action('wp_ajax_mlf_delete_session', array($this, 'handle_delete_session'));
        add_action('wp_ajax_mlf_confirm_registration', array($this, 'handle_confirm_registration'));
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
                        <th><?php _e('Statut', 'mlf'); ?></th>
                        <th><?php _e('Actions', 'mlf'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sessions)): ?>
                        <tr>
                            <td colspan="7"><?php _e('Aucune session trouvée.', 'mlf'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td><strong><?php echo esc_html($session['session_name']); ?></strong></td>
                                <td><?php echo esc_html($this->get_game_type_label($session['game_type'])); ?></td>
                                <td><?php echo esc_html(date('d/m/Y', strtotime($session['session_date']))); ?></td>
                                <td><?php echo esc_html(date('H:i', strtotime($session['session_time']))); ?></td>
                                <td><?php echo intval($session['current_players']); ?>/<?php echo intval($session['max_players']); ?></td>
                                <td><?php echo esc_html($this->get_status_label($session['status'])); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=mlf-edit-session&id=' . $session['id']); ?>" class="button button-small">
                                        <?php _e('Modifier', 'mlf'); ?>
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
     * Enqueue admin-specific styles and scripts.
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'mlf-') !== false) {
            wp_enqueue_script('mlf-admin-js', plugin_dir_url(MLF_PLUGIN_PATH . 'mlf-plugin.php') . 'includes/admin/js/mlf-admin.js', array('jquery'), '1.0.0', true);
            wp_enqueue_style('mlf-admin-css', plugin_dir_url(MLF_PLUGIN_PATH . 'mlf-plugin.php') . 'assets/css/mlf-plugin.css', array(), '1.0.0');
            
            wp_localize_script('mlf-admin-js', 'mlf_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mlf_admin_nonce'),
            ));
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
     * Get status label.
     */
    private function get_status_label($status) {
        $labels = array(
            'planifiee' => 'Planifiée',
            'en_cours' => 'En cours',
            'terminee' => 'Terminée',
            'annulee' => 'Annulée'
        );
        
        return isset($labels[$status]) ? $labels[$status] : $status;
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
}