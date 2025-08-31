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
            
            // Backup handler for character sheet upload in case the main one doesn't work
            add_action('wp_ajax_mlf_upload_character_sheet', array($this, 'handle_admin_upload_character_sheet'));
            add_action('wp_ajax_mlf_get_response_details', array($this, 'handle_get_response_details'));
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
            __('Fiches de personnage', 'mlf'),
            __('Fiches de personnage', 'mlf'),
            'manage_options',
            'mlf-character-sheets',
            array($this, 'render_character_sheets_page')
        );
        
        add_submenu_page(
            'mlf-sessions',
            __('Réponses aux formulaires', 'mlf'),
            __('Réponses aux formulaires', 'mlf'),
            'manage_options',
            'mlf-form-responses',
            array($this, 'render_form_responses_page')
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
        global $wpdb;
        
        // Gérer les actions (confirmation, suppression, etc.)
        if (isset($_POST['action']) && wp_verify_nonce($_POST['_wpnonce'], 'mlf_admin_action')) {
            $this->handle_registration_action();
        }
        
        // Récupérer toutes les inscriptions avec les informations de session
        $registrations = $wpdb->get_results(
            "SELECT r.*, s.session_name, s.session_date, s.session_time, s.max_players 
             FROM {$wpdb->prefix}mlf_player_registrations r 
             LEFT JOIN {$wpdb->prefix}mlf_game_sessions s ON r.session_id = s.id 
             ORDER BY r.registration_date DESC",
            ARRAY_A
        );
        
        ?>
        <div class="wrap">
            <h1><?php _e('Gestion des inscriptions', 'mlf'); ?></h1>
            
            <?php if (isset($_GET['message'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html($_GET['message']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (empty($registrations)): ?>
                <div class="notice notice-info">
                    <p><?php _e('Aucune inscription trouvée.', 'mlf'); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=mlf-sessions'); ?>"><?php _e('Gérer les sessions', 'mlf'); ?></a></p>
                </div>
            <?php else: ?>
                <p><?php printf(__('Total: %d inscriptions trouvées', 'mlf'), count($registrations)); ?></p>
                
                <form method="post">
                    <?php wp_nonce_field('mlf_admin_action'); ?>
                    
                    <div class="tablenav top">
                        <div class="alignleft actions">
                            <select name="bulk_action">
                                <option value=""><?php _e('Actions groupées', 'mlf'); ?></option>
                                <option value="confirm"><?php _e('Confirmer', 'mlf'); ?></option>
                                <option value="cancel"><?php _e('Annuler', 'mlf'); ?></option>
                                <option value="delete"><?php _e('Supprimer', 'mlf'); ?></option>
                            </select>
                            <input type="submit" class="button" value="<?php _e('Appliquer', 'mlf'); ?>">
                        </div>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="cb-select-all">
                                </td>
                                <th class="manage-column"><?php _e('ID', 'mlf'); ?></th>
                                <th class="manage-column"><?php _e('Joueur', 'mlf'); ?></th>
                                <th class="manage-column"><?php _e('Email', 'mlf'); ?></th>
                                <th class="manage-column"><?php _e('Session', 'mlf'); ?></th>
                                <th class="manage-column"><?php _e('Date session', 'mlf'); ?></th>
                                <th class="manage-column"><?php _e('Statut', 'mlf'); ?></th>
                                <th class="manage-column"><?php _e('Date inscription', 'mlf'); ?></th>
                                <th class="manage-column"><?php _e('Actions', 'mlf'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrations as $registration): ?>
                                <tr>
                                    <th class="check-column">
                                        <input type="checkbox" name="registration_ids[]" value="<?php echo $registration['id']; ?>">
                                    </th>
                                    <td><strong><?php echo $registration['id']; ?></strong></td>
                                    <td>
                                        <?php echo esc_html($registration['player_name']); ?>
                                        <?php if (!empty($registration['player_phone'])): ?>
                                            <br><small><?php echo esc_html($registration['player_phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="mailto:<?php echo esc_attr($registration['player_email']); ?>">
                                            <?php echo esc_html($registration['player_email']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($registration['session_name']): ?>
                                            <strong><?php echo esc_html($registration['session_name']); ?></strong>
                                            <br><small>ID: <?php echo $registration['session_id']; ?></small>
                                        <?php else: ?>
                                            <em><?php _e('Session supprimée', 'mlf'); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($registration['session_date']): ?>
                                            <?php echo date_i18n('d/m/Y', strtotime($registration['session_date'])); ?>
                                            <?php if ($registration['session_time']): ?>
                                                <br><small><?php echo date('H:i', strtotime($registration['session_time'])); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <em>-</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $registration['registration_status'];
                                        $status_class = '';
                                        $status_label = '';
                                        
                                        switch ($status) {
                                            case 'confirme':
                                                $status_class = 'status-confirmed';
                                                $status_label = __('Confirmé', 'mlf');
                                                break;
                                            case 'en_attente':
                                                $status_class = 'status-pending';
                                                $status_label = __('En attente', 'mlf');
                                                break;
                                            case 'annule':
                                                $status_class = 'status-cancelled';
                                                $status_label = __('Annulé', 'mlf');
                                                break;
                                            case 'liste_attente':
                                                $status_class = 'status-waitlist';
                                                $status_label = __('Liste d\'attente', 'mlf');
                                                break;
                                            default:
                                                $status_label = esc_html($status);
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date_i18n('d/m/Y H:i', strtotime($registration['registration_date'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($status !== 'confirme'): ?>
                                            <button type="submit" name="action" value="confirm_single" 
                                                    onclick="this.form.registration_id.value=<?php echo $registration['id']; ?>"
                                                    class="button button-small button-primary">
                                                <?php _e('Confirmer', 'mlf'); ?>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button type="submit" name="action" value="delete_single" 
                                                onclick="return confirm('<?php _e('Êtes-vous sûr de vouloir supprimer cette inscription ?', 'mlf'); ?>') && (this.form.registration_id.value=<?php echo $registration['id']; ?>)"
                                                class="button button-small button-link-delete">
                                            <?php _e('Supprimer', 'mlf'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <input type="hidden" name="registration_id" value="">
                </form>
            <?php endif; ?>
        </div>
        
        <style>
            .status-badge {
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
                text-transform: uppercase;
            }
            .status-confirmed { background: #d4edda; color: #155724; }
            .status-pending { background: #fff3cd; color: #856404; }
            .status-cancelled { background: #f8d7da; color: #721c24; }
            .status-waitlist { background: #d1ecf1; color: #0c5460; }
        </style>
        
        <script>
            // Sélection/désélection de toutes les cases
            document.getElementById('cb-select-all').addEventListener('change', function() {
                var checkboxes = document.querySelectorAll('input[name="registration_ids[]"]');
                for (var i = 0; i < checkboxes.length; i++) {
                    checkboxes[i].checked = this.checked;
                }
            });
        </script>
        <?php
    }
    
    /**
     * Handle registration actions (confirm, cancel, delete).
     */
    private function handle_registration_action() {
        global $wpdb;
        
        $action = sanitize_text_field($_POST['action']);
        $message = '';
        
        if ($action === 'confirm_single' || $action === 'delete_single') {
            $registration_id = intval($_POST['registration_id']);
            
            if ($action === 'confirm_single') {
                $result = $wpdb->update(
                    $wpdb->prefix . 'mlf_player_registrations',
                    array(
                        'registration_status' => 'confirme',
                        'confirmation_date' => current_time('mysql')
                    ),
                    array('id' => $registration_id),
                    array('%s', '%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    // Mettre à jour le compteur de la session
                    $session_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT session_id FROM {$wpdb->prefix}mlf_player_registrations WHERE id = %d",
                        $registration_id
                    ));
                    if ($session_id) {
                        $this->update_session_player_count($session_id);
                    }
                    $message = __('Inscription confirmée avec succès.', 'mlf');
                }
                
            } elseif ($action === 'delete_single') {
                $session_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT session_id FROM {$wpdb->prefix}mlf_player_registrations WHERE id = %d",
                    $registration_id
                ));
                
                $result = $wpdb->delete(
                    $wpdb->prefix . 'mlf_player_registrations',
                    array('id' => $registration_id),
                    array('%d')
                );
                
                if ($result !== false) {
                    // Mettre à jour le compteur de la session
                    if ($session_id) {
                        $this->update_session_player_count($session_id);
                    }
                    $message = __('Inscription supprimée avec succès.', 'mlf');
                }
            }
        }
        
        // Actions groupées
        elseif (!empty($_POST['registration_ids']) && !empty($_POST['bulk_action'])) {
            $registration_ids = array_map('intval', $_POST['registration_ids']);
            $bulk_action = sanitize_text_field($_POST['bulk_action']);
            $count = 0;
            
            foreach ($registration_ids as $registration_id) {
                $session_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT session_id FROM {$wpdb->prefix}mlf_player_registrations WHERE id = %d",
                    $registration_id
                ));
                
                if ($bulk_action === 'confirm') {
                    $result = $wpdb->update(
                        $wpdb->prefix . 'mlf_player_registrations',
                        array(
                            'registration_status' => 'confirme',
                            'confirmation_date' => current_time('mysql')
                        ),
                        array('id' => $registration_id),
                        array('%s', '%s'),
                        array('%d')
                    );
                } elseif ($bulk_action === 'cancel') {
                    $result = $wpdb->update(
                        $wpdb->prefix . 'mlf_player_registrations',
                        array('registration_status' => 'annule'),
                        array('id' => $registration_id),
                        array('%s'),
                        array('%d')
                    );
                } elseif ($bulk_action === 'delete') {
                    $result = $wpdb->delete(
                        $wpdb->prefix . 'mlf_player_registrations',
                        array('id' => $registration_id),
                        array('%d')
                    );
                }
                
                if ($result !== false) {
                    $count++;
                    // Mettre à jour le compteur de la session
                    if ($session_id) {
                        $this->update_session_player_count($session_id);
                    }
                }
            }
            
            $message = sprintf(__('%d inscriptions mises à jour.', 'mlf'), $count);
        }
        
        if ($message) {
            wp_redirect(add_query_arg('message', urlencode($message), wp_get_referer()));
            exit;
        }
    }
    
    /**
     * Update session player count.
     */
    private function update_session_player_count($session_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mlf_player_registrations WHERE session_id = %d AND registration_status = 'confirme'",
            $session_id
        ));
        
        $wpdb->update(
            $wpdb->prefix . 'mlf_game_sessions',
            array('current_players' => $count),
            array('id' => $session_id),
            array('%d'),
            array('%d')
        );
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
    
    /**
     * Render the character sheets management page.
     */
    public function render_character_sheets_page() {
        global $wpdb;
        
        // Handle actions (upload, delete, etc.)
        if (isset($_POST['action']) && wp_verify_nonce($_POST['_wpnonce'], 'mlf_admin_action')) {
            $this->handle_character_sheets_action();
        }
        
        // Get session filter
        $session_filter = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
        
        // Get all sessions for filter dropdown
        $sessions = $wpdb->get_results(
            "SELECT id, session_name, session_date 
             FROM {$wpdb->prefix}mlf_game_sessions 
             ORDER BY session_date DESC",
            ARRAY_A
        );
        
        // Get character sheets with session and player info
        $where_clause = $session_filter ? "WHERE cs.session_id = $session_filter" : "";
        $character_sheets = $wpdb->get_results(
            "SELECT cs.*, 
                    s.session_name, 
                    s.session_date,
                    p.display_name as player_name,
                    u.display_name as uploader_name
             FROM {$wpdb->prefix}mlf_character_sheets cs
             LEFT JOIN {$wpdb->prefix}mlf_game_sessions s ON cs.session_id = s.id
             LEFT JOIN {$wpdb->users} p ON cs.player_id = p.ID
             LEFT JOIN {$wpdb->users} u ON cs.uploaded_by = u.ID
             $where_clause
             ORDER BY cs.uploaded_at DESC",
            ARRAY_A
        );
        
        ?>
        <div class="wrap">
            <h1><?php _e('Gestion des fiches de personnage', 'mlf'); ?></h1>
            
            <?php if (isset($_GET['message'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html($_GET['message']); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Filter by session -->
            <div class="tablenav top">
                <form method="get" action="">
                    <input type="hidden" name="page" value="mlf-character-sheets">
                    <select name="session_id" onchange="this.form.submit()">
                        <option value="0"><?php _e('Toutes les sessions', 'mlf'); ?></option>
                        <?php foreach ($sessions as $session): ?>
                            <option value="<?php echo $session['id']; ?>" <?php selected($session_filter, $session['id']); ?>>
                                <?php echo esc_html($session['session_name']) . ' - ' . date('d/m/Y', strtotime($session['session_date'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            
            <?php if ($session_filter): ?>
                <!-- Upload new character sheet -->
                <div class="card" style="margin-bottom: 20px;">
                    <h2><?php _e('Ajouter une fiche de personnage', 'mlf'); ?></h2>
                    <?php $this->render_character_upload_form($session_filter); ?>
                </div>
            <?php endif; ?>
            
            <!-- Character sheets table -->
            <form method="post" action="">
                <?php wp_nonce_field('mlf_admin_action'); ?>
                
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action">
                            <option value=""><?php _e('Actions groupées', 'mlf'); ?></option>
                            <option value="delete"><?php _e('Supprimer', 'mlf'); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php _e('Appliquer', 'mlf'); ?>">
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th><?php _e('Nom du fichier', 'mlf'); ?></th>
                            <th><?php _e('Session', 'mlf'); ?></th>
                            <th><?php _e('Joueur', 'mlf'); ?></th>
                            <th><?php _e('Type', 'mlf'); ?></th>
                            <th><?php _e('Taille', 'mlf'); ?></th>
                            <th><?php _e('Uploadé par', 'mlf'); ?></th>
                            <th><?php _e('Date', 'mlf'); ?></th>
                            <th><?php _e('Actions', 'mlf'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($character_sheets)): ?>
                            <tr>
                                <td colspan="9">
                                    <p><?php _e('Aucune fiche de personnage trouvée.', 'mlf'); ?></p>
                                    <?php if (!$session_filter): ?>
                                        <p><em><?php _e('Sélectionnez une session pour voir les options d\'upload.', 'mlf'); ?></em></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($character_sheets as $sheet): ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="sheet_ids[]" value="<?php echo $sheet['id']; ?>">
                                    </th>
                                    <td>
                                        <strong><?php echo esc_html($sheet['file_original_name']); ?></strong>
                                        <?php if ($sheet['is_private']): ?>
                                            <span class="dashicons dashicons-lock" title="<?php _e('Fiche privée', 'mlf'); ?>"></span>
                                        <?php endif; ?>
                                        <?php if (!empty($sheet['file_description'])): ?>
                                            <br><em><?php echo esc_html(wp_trim_words($sheet['file_description'], 10)); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($sheet['session_name']); ?></td>
                                    <td><?php echo esc_html($sheet['player_name']); ?></td>
                                    <td><?php echo strtoupper($sheet['file_type']); ?></td>
                                    <td><?php echo $this->format_file_size($sheet['file_size']); ?></td>
                                    <td><?php echo esc_html($sheet['uploader_name']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($sheet['uploaded_at'])); ?></td>
                                    <td>
                                        <a href="<?php echo $this->get_download_url($sheet['id']); ?>" 
                                           class="button button-small" target="_blank">
                                            <?php _e('Télécharger', 'mlf'); ?>
                                        </a>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('mlf_admin_action'); ?>
                                            <input type="hidden" name="action" value="delete_single">
                                            <input type="hidden" name="sheet_id" value="<?php echo $sheet['id']; ?>">
                                            <input type="submit" class="button button-small button-link-delete" 
                                                   value="<?php _e('Supprimer', 'mlf'); ?>"
                                                   onclick="return confirm('<?php _e('Êtes-vous sûr de vouloir supprimer cette fiche ?', 'mlf'); ?>')">
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
        
        <script>
        // Sélection/désélection de toutes les cases
        document.getElementById('cb-select-all').addEventListener('change', function() {
            var checkboxes = document.querySelectorAll('input[name="sheet_ids[]"]');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = this.checked;
            }
        });
        </script>
        <?php
    }
    
    /**
     * Handle character sheets actions (delete).
     */
    private function handle_character_sheets_action() {
        global $wpdb;
        
        $action = sanitize_text_field($_POST['action']);
        $message = '';
        
        if ($action === 'delete_single') {
            $sheet_id = intval($_POST['sheet_id']);
            if ($this->delete_character_sheet($sheet_id)) {
                $message = __('Fiche supprimée avec succès.', 'mlf');
            } else {
                $message = __('Erreur lors de la suppression de la fiche.', 'mlf');
            }
        } elseif ($action === 'delete' && isset($_POST['sheet_ids'])) {
            $sheet_ids = array_map('intval', $_POST['sheet_ids']);
            $deleted = 0;
            foreach ($sheet_ids as $sheet_id) {
                if ($this->delete_character_sheet($sheet_id)) {
                    $deleted++;
                }
            }
            $message = sprintf(__('%d fiche(s) supprimée(s).', 'mlf'), $deleted);
        }
        
        if ($message) {
            wp_redirect(add_query_arg('message', urlencode($message), wp_get_referer()));
            exit;
        }
    }
    
    /**
     * Delete a character sheet.
     */
    private function delete_character_sheet($sheet_id) {
        global $wpdb;
        
        // Get sheet info
        $sheet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mlf_character_sheets WHERE id = %d",
            $sheet_id
        ), ARRAY_A);
        
        if (!$sheet) {
            return false;
        }
        
        // Delete file
        if (file_exists($sheet['file_path'])) {
            unlink($sheet['file_path']);
        }
        
        // Delete from database
        $result = $wpdb->delete(
            $wpdb->prefix . 'mlf_character_sheets',
            array('id' => $sheet_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Format file size for display.
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
     * Get download URL for a character sheet.
     */
    private function get_download_url($sheet_id) {
        $nonce = wp_create_nonce('mlf_download_sheet_' . $sheet_id);
        return add_query_arg(array(
            'mlf_download_sheet' => $sheet_id,
            'nonce' => $nonce
        ), home_url());
    }
    
    /**
     * Render character upload form for admin interface.
     */
    private function render_character_upload_form($session_id) {
        $current_user_id = get_current_user_id();
        
        // Check if user can upload (admin or session manager)
        if (!current_user_can('manage_options')) {
            echo '<p>' . __('Vous n\'avez pas les permissions pour uploader des fichiers.', 'mlf') . '</p>';
            return;
        }
        
        // Get session info
        global $wpdb;
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mlf_game_sessions WHERE id = %d",
            $session_id
        ), ARRAY_A);
        
        if (!$session) {
            echo '<p>' . __('Session non trouvée.', 'mlf') . '</p>';
            return;
        }
        
        // Get registered players for this session
        $players = $wpdb->get_results($wpdb->prepare(
            "SELECT pr.*, u.display_name 
             FROM {$wpdb->prefix}mlf_player_registrations pr
             LEFT JOIN {$wpdb->users} u ON pr.user_id = u.ID
             WHERE pr.session_id = %d AND pr.registration_status = 'confirme'
             ORDER BY u.display_name",
            $session_id
        ), ARRAY_A);
        
        ?>
        <div class="mlf-character-upload-form">
            <form id="mlf-character-upload-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('mlf_character_upload', 'mlf_character_nonce'); ?>
                <input type="hidden" name="action" value="upload_character_sheet">
                <input type="hidden" name="session_id" value="<?php echo esc_attr($session_id); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="player_id"><?php _e('Joueur', 'mlf'); ?></label>
                        </th>
                        <td>
                            <select name="player_id" id="player_id" required>
                                <option value=""><?php _e('Sélectionner un joueur', 'mlf'); ?></option>
                                <?php foreach ($players as $player): ?>
                                    <option value="<?php echo esc_attr($player['user_id']); ?>">
                                        <?php echo esc_html($player['display_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="character_file"><?php _e('Fichier', 'mlf'); ?></label>
                        </th>
                        <td>
                            <input type="file" name="character_file" id="character_file" required 
                                   accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif">
                            <p class="description">
                                <?php _e('Formats acceptés: PDF, DOC, DOCX, TXT, JPG, PNG, GIF. Taille max: ', 'mlf'); ?>
                                <?php echo size_format(wp_max_upload_size()); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="file_description"><?php _e('Description', 'mlf'); ?></label>
                        </th>
                        <td>
                            <textarea name="file_description" id="file_description" rows="3" cols="50" 
                                      placeholder="<?php _e('Description optionnelle du fichier...', 'mlf'); ?>"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="is_private"><?php _e('Visibilité', 'mlf'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_private" id="is_private" value="1">
                                <?php _e('Fichier privé (visible uniquement par les administrateurs)', 'mlf'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php _e('Uploader la fiche', 'mlf'); ?>">
                </p>
            </form>
            
            <div id="mlf-upload-result"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#mlf-character-upload-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                formData.append('action', 'mlf_upload_character_sheet');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        $('#mlf-upload-result').html('<p><?php _e("Upload en cours...", "mlf"); ?></p>');
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#mlf-upload-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            $('#mlf-character-upload-form')[0].reset();
                            // Reload page to show new file
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $('#mlf-upload-result').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#mlf-upload-result').html('<div class="notice notice-error"><p><?php _e("Erreur lors de l\'upload", "mlf"); ?></p></div>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Backup handler for character sheet upload from admin interface.
     */
    public function handle_admin_upload_character_sheet() {
        // Basic security check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['mlf_character_nonce'], 'mlf_character_upload')) {
            wp_send_json_error(array('message' => 'Erreur de sécurité'));
        }
        
        $session_id = intval($_POST['session_id']);
        $player_id = intval($_POST['player_id']);
        $file_description = sanitize_textarea_field($_POST['file_description']);
        $is_private = isset($_POST['is_private']) ? 1 : 0;
        $user_id = get_current_user_id();
        
        // Validate file upload
        if (!isset($_FILES['character_file']) || $_FILES['character_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => 'Erreur lors du téléchargement du fichier'));
        }
        
        $file = $_FILES['character_file'];
        $allowed_types = array('pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif');
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            wp_send_json_error(array('message' => 'Type de fichier non autorisé'));
        }
        
        // Create uploads directory for character sheets
        $upload_dir = wp_upload_dir();
        $mlf_dir = $upload_dir['basedir'] . '/mlf-character-sheets';
        if (!file_exists($mlf_dir)) {
            wp_mkdir_p($mlf_dir);
        }
        
        // Generate unique filename
        $filename = sanitize_file_name($session_id . '_' . $player_id . '_' . time() . '.' . $file_ext);
        $file_path = $mlf_dir . '/' . $filename;
        $file_url = $upload_dir['baseurl'] . '/mlf-character-sheets/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            wp_send_json_error(array('message' => 'Erreur lors de la sauvegarde du fichier'));
        }
        
        // Find registration_id for this player and session
        global $wpdb;
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}mlf_player_registrations 
             WHERE session_id = %d AND user_id = %d",
            $session_id, $player_id
        ));
        
        if (!$registration) {
            // Delete uploaded file since we can't save to database
            unlink($file_path);
            wp_send_json_error(array('message' => 'Inscription non trouvée pour ce joueur'));
        }
        
        // Save to database
        $result = $wpdb->insert(
            $wpdb->prefix . 'mlf_character_sheets',
            array(
                'session_id' => $session_id,
                'player_id' => $player_id,
                'registration_id' => $registration->id,
                'uploaded_by' => $user_id,
                'file_name' => $filename,
                'file_original_name' => $file['name'],
                'file_path' => $file_path,
                'file_url' => $file_url,
                'file_type' => $file_ext,
                'file_size' => $file['size'],
                'file_description' => $file_description,
                'is_private' => $is_private,
                'uploaded_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            // Delete uploaded file if database insert failed
            unlink($file_path);
            wp_send_json_error(array('message' => 'Erreur lors de la sauvegarde en base de données'));
        }
        
        wp_send_json_success(array('message' => 'Fiche uploadée avec succès'));
    }

    /**
     * Render the form responses administration page.
     */
    public function render_form_responses_page() {
        global $wpdb;
        
        // Récupérer le filtre de session si défini
        $session_filter = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
        $view_response = isset($_GET['view_response']) ? intval($_GET['view_response']) : 0;
        
        // Récupérer toutes les sessions pour le filtre
        $sessions = MLF_Database_Manager::get_game_sessions(array('limit' => 100));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Réponses aux formulaires', 'mlf'); ?></h1>
            
            <?php if (isset($_GET['message'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html($_GET['message']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($view_response): ?>
                <!-- Affichage détaillé d'une réponse -->
                <?php $this->display_response_details($view_response); ?>
                <p><a href="?page=mlf-form-responses<?php echo $session_filter ? '&session_id=' . $session_filter : ''; ?>" class="button">← Retour à la liste</a></p>
            <?php else: ?>
                <!-- Vue liste normale -->
                
                <!-- Filter by session -->
                <div class="tablenav top">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="mlf-form-responses">
                        <select name="session_id" onchange="this.form.submit()">
                            <option value="0"><?php _e('Toutes les sessions', 'mlf'); ?></option>
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?php echo $session['id']; ?>" <?php selected($session_filter, $session['id']); ?>>
                                    <?php echo esc_html($session['session_name']) . ' - ' . date('d/m/Y', strtotime($session['session_date'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                
                <?php
                // Construire la requête pour récupérer les réponses avec les informations des joueurs
                $responses_table = $wpdb->prefix . 'mlf_custom_form_responses';
                $registrations_table = $wpdb->prefix . 'mlf_player_registrations';
                $sessions_table = $wpdb->prefix . 'mlf_game_sessions';
                
                $where_clause = '';
                $prepare_values = array();
                
                if ($session_filter) {
                    $where_clause = 'WHERE cfr.session_id = %d';
                    $prepare_values[] = $session_filter;
                }
                
                $query = "
                    SELECT 
                        cfr.*,
                        pr.player_name,
                        pr.player_email,
                        s.session_name,
                        s.session_date
                    FROM $responses_table cfr
                    JOIN $registrations_table pr ON cfr.registration_id = pr.id
                    JOIN $sessions_table s ON cfr.session_id = s.id
                    $where_clause
                    ORDER BY cfr.submitted_at DESC
                ";
                
                if (!empty($prepare_values)) {
                    $responses = $wpdb->get_results($wpdb->prepare($query, ...$prepare_values), ARRAY_A);
                } else {
                    $responses = $wpdb->get_results($query, ARRAY_A);
                }
                ?>
                
                <?php if (empty($responses)): ?>
                    <div class="notice notice-info">
                        <p><?php _e('Aucune réponse aux formulaires trouvée.', 'mlf'); ?></p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Session', 'mlf'); ?></th>
                                <th><?php _e('Joueur', 'mlf'); ?></th>
                                <th><?php _e('Email', 'mlf'); ?></th>
                                <th><?php _e('Date de soumission', 'mlf'); ?></th>
                                <th><?php _e('Actions', 'mlf'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($responses as $response): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($response['session_name']); ?></strong><br>
                                        <small><?php echo esc_html(date('d/m/Y', strtotime($response['session_date']))); ?></small>
                                    </td>
                                    <td><?php echo esc_html($response['player_name']); ?></td>
                                    <td><?php echo esc_html($response['player_email']); ?></td>
                                    <td><?php echo esc_html(date_i18n('d/m/Y à H:i', strtotime($response['submitted_at']))); ?></td>
                                    <td>
                                        <a href="?page=mlf-form-responses&view_response=<?php echo $response['id']; ?><?php echo $session_filter ? '&session_id=' . $session_filter : ''; ?>" 
                                           class="button button-primary">
                                            <?php _e('Voir les réponses', 'mlf'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Display detailed response for a specific form submission.
     */
    private function display_response_details($response_id) {
        global $wpdb;
        
        $responses_table = $wpdb->prefix . 'mlf_custom_form_responses';
        $registrations_table = $wpdb->prefix . 'mlf_player_registrations';
        $sessions_table = $wpdb->prefix . 'mlf_game_sessions';
        $forms_table = $wpdb->prefix . 'mlf_custom_forms';
        
        $query = "
            SELECT 
                cfr.*,
                pr.player_name,
                pr.player_email,
                s.session_name,
                s.session_date,
                cf.form_title,
                cf.form_fields
            FROM $responses_table cfr
            JOIN $registrations_table pr ON cfr.registration_id = pr.id
            JOIN $sessions_table s ON cfr.session_id = s.id
            LEFT JOIN $forms_table cf ON cfr.session_id = cf.session_id AND cf.is_active = 1
            WHERE cfr.id = %d
        ";
        
        $response = $wpdb->get_row($wpdb->prepare($query, $response_id), ARRAY_A);
        
        if (!$response) {
            echo '<div class="notice notice-error"><p>Réponse introuvable.</p></div>';
            return;
        }
        
        // Décoder les données
        $form_responses = json_decode($response['response_data'], true);
        $form_fields = json_decode($response['form_fields'], true);
        
        // Si pas de form_fields via le LEFT JOIN, essayer de récupérer directement
        if (!$form_fields) {
            $form_query = "SELECT form_fields FROM $forms_table WHERE session_id = %d AND is_active = 1 LIMIT 1";
            $form_fields_raw = $wpdb->get_var($wpdb->prepare($form_query, $response['session_id']));
            if ($form_fields_raw) {
                $form_fields = json_decode($form_fields_raw, true);
            }
        }
        
        ?>
        <div class="mlf-response-details-page">
            <h2>Réponses de <?php echo esc_html($response['player_name']); ?></h2>
            
            <div class="mlf-player-info" style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                <h3>Informations du joueur</h3>
                <p><strong>Nom :</strong> <?php echo esc_html($response['player_name']); ?></p>
                <p><strong>Email :</strong> <?php echo esc_html($response['player_email']); ?></p>
                <p><strong>Session :</strong> <?php echo esc_html($response['session_name']); ?></p>
                <p><strong>Date de la session :</strong> <?php echo esc_html(date('d/m/Y', strtotime($response['session_date']))); ?></p>
                <p><strong>Date de soumission :</strong> <?php echo esc_html(date_i18n('d/m/Y à H:i', strtotime($response['submitted_at']))); ?></p>
            </div>
            
            <div class="mlf-form-responses">
                <h3>Réponses au formulaire</h3>
                
                <?php if (is_array($form_responses) && count($form_responses) > 0): ?>
                    
                    <?php if (is_array($form_fields)): ?>
                        <?php foreach ($form_fields as $field): ?>
                            <?php
                            $field_name = $field['name'];
                            $field_label = $field['label'];
                            $field_type = isset($field['type']) ? $field['type'] : 'text';
                            ?>
                            <div class="mlf-response-detail" style="margin-bottom: 20px; padding: 15px; background: #fff; border-left: 4px solid #0073aa; border-radius: 4px;">
                                <div class="question" style="font-weight: bold; color: #333; margin-bottom: 8px;">
                                    <?php echo esc_html($field_label); ?>
                                </div>
                                <div class="answer" style="padding: 8px 12px; background: #f9f9f9; border-radius: 3px; border: 1px solid #ddd;">
                                    <?php if (isset($form_responses[$field_name])): ?>
                                        <?php
                                        $answer = $form_responses[$field_name];
                                        
                                        // Traitement selon le type de champ
                                        if ($field_type === 'checkbox' && is_array($answer)) {
                                            echo esc_html(implode(', ', $answer));
                                        } elseif ($field_type === 'file' && is_array($answer) && isset($answer['url'])) {
                                            echo '<a href="' . esc_url($answer['url']) . '" target="_blank">' . esc_html($answer['name']) . '</a>';
                                        } else {
                                            echo esc_html($answer);
                                        }
                                        ?>
                                    <?php else: ?>
                                        <em>Pas de réponse</em>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Affichage de secours sans structure de formulaire -->
                        <?php foreach ($form_responses as $key => $value): ?>
                            <div class="mlf-response-detail" style="margin-bottom: 20px; padding: 15px; background: #fff; border-left: 4px solid #0073aa; border-radius: 4px;">
                                <div class="question" style="font-weight: bold; color: #333; margin-bottom: 8px;">
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>
                                </div>
                                <div class="answer" style="padding: 8px 12px; background: #f9f9f9; border-radius: 3px; border: 1px solid #ddd;">
                                    <?php echo esc_html(is_array($value) ? implode(', ', $value) : $value); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="notice notice-warning">
                        <p>Aucune réponse trouvée pour ce formulaire.</p>
                    <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0;">
                        <p><strong>✅ Condition remplie - Affichage des réponses en cours...</strong></p>
                    </div>
                    
                    <?php foreach ($form_fields as $field): ?>
                        <?php
                        $field_name = $field['name'];
                        $field_label = $field['label'];
                        $field_type = isset($field['type']) ? $field['type'] : 'text';
                        
                        ?>
                        <div class="mlf-response-detail" style="margin-bottom: 20px; padding: 15px; background: #fff; border-left: 4px solid #0073aa; border-radius: 4px;">
                            <div class="question" style="font-weight: bold; color: #333; margin-bottom: 8px;">
                                <?php echo esc_html($field_label); ?>
                            </div>
                            <div class="answer" style="padding: 8px 12px; background: #f9f9f9; border-radius: 3px; border: 1px solid #ddd;">
                                <?php if (isset($form_responses[$field_name])): ?>
                                    <?php
                                    $answer = $form_responses[$field_name];
                                    
                                    // Traitement selon le type de champ
                                    if ($field_type === 'checkbox' && is_array($answer)) {
                                        echo esc_html(implode(', ', $answer));
                                    } elseif ($field_type === 'file' && is_array($answer) && isset($answer['url'])) {
                                        echo '<a href="' . esc_url($answer['url']) . '" target="_blank">' . esc_html($answer['name']) . '</a>';
                                    } else {
                                        echo esc_html($answer);
                                    }
                                    ?>
                                <?php else: ?>
                                    <em>Pas de réponse pour ce champ</em>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                </div>
                            <p><strong>Form fields:</strong></p>
                            <pre><?php echo esc_html($response['form_fields'] ?? 'NULL'); ?></pre>
                        </details>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}