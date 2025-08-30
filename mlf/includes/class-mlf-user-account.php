<?php
/**
 * User Account functionality for the MLF Plugin.
 *
 * This class handles user account features including viewing user's session history.
 */
class MLF_User_Account {

    /**
     * Constructor for the class.
     */
    public function __construct() {
        // Add user account menu and pages
        add_action('init', array($this, 'add_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_user_account_scripts'));
        
        // AJAX handlers for user account features
        add_action('wp_ajax_mlf_cancel_registration', array($this, 'handle_cancel_registration'));
    }

    /**
     * Register shortcodes.
     */
    public function add_shortcodes() {
        add_shortcode('mlf_user_sessions', array($this, 'display_user_sessions'));
        add_shortcode('mlf_user_profile', array($this, 'display_user_profile'));
    }

    /**
     * Enqueue user account scripts and styles.
     */
    public function enqueue_user_account_scripts() {
        if (is_user_logged_in()) {
            wp_enqueue_style('mlf-user-account-css', plugin_dir_url(dirname(__FILE__)) . 'public/css/mlf-public.css', array(), '1.0.0');
            wp_enqueue_script('mlf-user-account-js', plugin_dir_url(dirname(__FILE__)) . 'public/js/mlf-public.js', array('jquery'), '1.0.1', true);
            
            wp_localize_script('mlf-user-account-js', 'mlf_user_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mlf_user_account_nonce'),
            ));
        }
    }

    /**
     * Display user's sessions (past and upcoming).
     */
    public function display_user_sessions($atts) {
        $atts = shortcode_atts(array(
            'show_past' => true,
            'show_upcoming' => true,
            'limit' => 20
        ), $atts);

        if (!is_user_logged_in()) {
            return '<div class="mlf-login-required">
                <p>' . __('Vous devez être connecté pour voir vos sessions.', 'mlf') . '</p>
                <a href="' . wp_login_url(get_permalink()) . '" class="mlf-btn mlf-btn-primary">' . __('Se connecter', 'mlf') . '</a>
            </div>';
        }

        $current_user_id = get_current_user_id();
        $sessions = $this->get_user_sessions($current_user_id, array('limit' => intval($atts['limit'])));

        if (empty($sessions)) {
            return '<p>Vous n\'êtes inscrit à aucune session pour le moment.</p>';
        }

        $output = '<div class="mlf-user-sessions">
            <h2>' . __('Mes Sessions de Jeu', 'mlf') . '</h2>
            <div class="mlf-sessions-grid">';

        foreach ($sessions as $session) {
            $output .= '<div class="mlf-session-card">
                <div class="mlf-session-header">
                    <h3>' . esc_html($session['session_name']) . '</h3>
                    <span class="mlf-session-type">' . esc_html($this->get_game_type_label($session['game_type'])) . '</span>
                </div>
                <div class="mlf-session-details">
                    <p><strong>' . __('Date:', 'mlf') . '</strong> ' . date('d/m/Y', strtotime($session['session_date'])) . '</p>
                    <p><strong>' . __('Heure:', 'mlf') . '</strong> ' . date('H:i', strtotime($session['session_time'])) . '</p>
                    <p><strong>' . __('Lieu:', 'mlf') . '</strong> ' . esc_html($session['location']) . '</p>
                    <p><strong>' . __('Statut:', 'mlf') . '</strong> 
                        <span class="mlf-status mlf-status-' . esc_attr($session['registration_status']) . '">
                            ' . esc_html($this->get_status_label($session['registration_status'])) . '
                        </span>
                    </p>
                </div>
                <div class="mlf-session-description">
                    <p>' . wp_kses_post(substr($session['description'], 0, 150)) . '...</p>
                </div>
                <div class="mlf-session-actions">
                    <button class="mlf-btn mlf-btn-secondary mlf-view-session" 
                            data-session-id="' . intval($session['session_id']) . '">
                        ' . __('Voir les détails', 'mlf') . '
                    </button>';
            
            if (in_array($session['registration_status'], ['confirme', 'en_attente'])) {
                $output .= '<button class="mlf-btn mlf-btn-danger mlf-cancel-registration" 
                            data-registration-id="' . intval($session['id']) . '"
                            data-session-name="' . esc_attr($session['session_name']) . '">
                        ' . __('Annuler', 'mlf') . '
                    </button>';
            }
            
            $output .= '</div>
            </div>';
        }

        $output .= '</div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $(".mlf-view-session").on("click", function() {
                var sessionId = $(this).data("session-id");
                window.location.href = "/?page_id=13&action=details&session_id=" + sessionId;
            });

            $(".mlf-cancel-registration").on("click", function() {
                var registrationId = $(this).data("registration-id");
                var sessionName = $(this).data("session-name");
                
                if (confirm("Êtes-vous sûr de vouloir annuler votre inscription à \\"" + sessionName + "\\" ?")) {
                    $.ajax({
                        url: "' . admin_url('admin-ajax.php') . '",
                        type: "POST",
                        data: {
                            action: "mlf_cancel_registration",
                            registration_id: registrationId,
                            nonce: "' . wp_create_nonce('mlf_user_account_nonce') . '"
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data.message || "Erreur lors de l\'annulation");
                            }
                        },
                        error: function() {
                            alert("Erreur de communication avec le serveur");
                        }
                    });
                }
            });
        });
        </script>';

        return $output;
    }

    /**
     * Display user profile summary.
     */
    public function display_user_profile($atts) {
        if (!is_user_logged_in()) {
            return '<div class="mlf-login-required">
                <p>' . __('Vous devez être connecté pour voir votre profil.', 'mlf') . '</p>
                <a href="' . wp_login_url(get_permalink()) . '" class="mlf-btn mlf-btn-primary">' . __('Se connecter', 'mlf') . '</a>
            </div>';
        }

        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        
        // Statistiques utilisateur
        $stats = $this->get_user_statistics($user_id);

        ob_start();
        ?>
        <div class="mlf-user-profile">
            <h2><?php _e('Mon Profil Joueur', 'mlf'); ?></h2>
            
            <div class="mlf-profile-info">
                <div class="mlf-user-details">
                    <h3><?php _e('Informations personnelles', 'mlf'); ?></h3>
                    <p><strong><?php _e('Nom:', 'mlf'); ?></strong> <?php echo esc_html($current_user->display_name); ?></p>
                    <p><strong><?php _e('Email:', 'mlf'); ?></strong> <?php echo esc_html($current_user->user_email); ?></p>
                    <p><strong><?php _e('Membre depuis:', 'mlf'); ?></strong> <?php echo esc_html(date_i18n('d/m/Y', strtotime($current_user->user_registered))); ?></p>
                </div>

                <div class="mlf-user-stats">
                    <h3><?php _e('Mes statistiques', 'mlf'); ?></h3>
                    <div class="mlf-stats-grid">
                        <div class="mlf-stat-item">
                            <span class="mlf-stat-number"><?php echo intval($stats['total_sessions']); ?></span>
                            <span class="mlf-stat-label"><?php _e('Sessions totales', 'mlf'); ?></span>
                        </div>
                        <div class="mlf-stat-item">
                            <span class="mlf-stat-number"><?php echo intval($stats['upcoming_sessions']); ?></span>
                            <span class="mlf-stat-label"><?php _e('Sessions à venir', 'mlf'); ?></span>
                        </div>
                        <div class="mlf-stat-item">
                            <span class="mlf-stat-number"><?php echo intval($stats['completed_sessions']); ?></span>
                            <span class="mlf-stat-label"><?php _e('Sessions terminées', 'mlf'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get user's sessions from database.
     */
    private function get_user_sessions($user_id, $options = array()) {
        global $wpdb;

        $limit = isset($options['limit']) ? intval($options['limit']) : 20;
        
        $query = "
            SELECT 
                pr.id,
                pr.session_id,
                pr.registration_status,
                pr.registration_date,
                pr.notes,
                gs.session_name,
                gs.game_type,
                gs.session_date,
                gs.session_time,
                gs.location,
                gs.description,
                gs.max_players,
                gs.current_players
            FROM {$wpdb->prefix}mlf_player_registrations pr
            JOIN {$wpdb->prefix}mlf_game_sessions gs ON pr.session_id = gs.id
            WHERE pr.user_id = %d
            ORDER BY gs.session_date DESC, gs.session_time DESC
            LIMIT %d
        ";

        return $wpdb->get_results($wpdb->prepare($query, $user_id, $limit), ARRAY_A);
    }

    /**
     * Get user statistics.
     */
    private function get_user_statistics($user_id) {
        global $wpdb;

        $stats = array();

        // Total sessions
        $stats['total_sessions'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}mlf_player_registrations 
            WHERE user_id = %d AND registration_status != 'annule'
        ", $user_id));

        // Upcoming sessions
        $stats['upcoming_sessions'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}mlf_player_registrations pr
            JOIN {$wpdb->prefix}mlf_game_sessions gs ON pr.session_id = gs.id
            WHERE pr.user_id = %d 
            AND pr.registration_status != 'annule'
            AND CONCAT(gs.session_date, ' ', gs.session_time) > NOW()
        ", $user_id));

        // Completed sessions
        $stats['completed_sessions'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}mlf_player_registrations pr
            JOIN {$wpdb->prefix}mlf_game_sessions gs ON pr.session_id = gs.id
            WHERE pr.user_id = %d 
            AND pr.registration_status != 'annule'
            AND CONCAT(gs.session_date, ' ', gs.session_time) < NOW()
        ", $user_id));

        return $stats;
    }

    /**
     * Handle registration cancellation.
     */
    public function handle_cancel_registration() {
        // Vérification de sécurité
        if (!wp_verify_nonce($_POST['nonce'], 'mlf_user_account_nonce')) {
            wp_send_json_error(array('message' => 'Échec de la vérification de sécurité.'));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Vous devez être connecté.'));
            return;
        }

        $registration_id = intval($_POST['registration_id']);
        $current_user_id = get_current_user_id();

        // Vérifier que l'inscription appartient à l'utilisateur
        global $wpdb;
        $registration = $wpdb->get_row($wpdb->prepare("
            SELECT pr.*, gs.session_date, gs.session_time, gs.session_name
            FROM {$wpdb->prefix}mlf_player_registrations pr
            JOIN {$wpdb->prefix}mlf_game_sessions gs ON pr.session_id = gs.id
            WHERE pr.id = %d AND pr.user_id = %d
        ", $registration_id, $current_user_id));

        if (!$registration) {
            wp_send_json_error(array('message' => 'Inscription non trouvée.'));
            return;
        }

        // Vérifier que la session n'est pas déjà passée
        $session_datetime = strtotime($registration->session_date . ' ' . $registration->session_time);
        if ($session_datetime < time()) {
            wp_send_json_error(array('message' => 'Impossible d\'annuler une inscription pour une session passée.'));
            return;
        }

        // Annuler l'inscription
        $result = $wpdb->update(
            $wpdb->prefix . 'mlf_player_registrations',
            array('registration_status' => 'annule'),
            array('id' => $registration_id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'Erreur lors de l\'annulation de l\'inscription.'));
            return;
        }

        // Décrémenter le nombre de joueurs inscrits
        $wpdb->query($wpdb->prepare("
            UPDATE {$wpdb->prefix}mlf_game_sessions 
            SET current_players = GREATEST(0, current_players - 1)
            WHERE id = %d
        ", $registration->session_id));

        wp_send_json_success(array(
            'message' => 'Inscription annulée avec succès.',
            'registration_id' => $registration_id
        ));
    }

    /**
     * Get game type label.
     */
    private function get_game_type_label($type) {
        $labels = array(
            'jdr' => __('JDR', 'mlf'),
            'murder' => __('Murder Party', 'mlf'),
            'jeu_de_societe' => __('Jeu de société', 'mlf'),
        );
        return isset($labels[$type]) ? $labels[$type] : ucfirst($type);
    }

    /**
     * Get status label.
     */
    private function get_status_label($status) {
        $labels = array(
            'confirme' => __('Confirmée', 'mlf'),
            'en_attente' => __('En attente', 'mlf'),
            'liste_attente' => __('Liste d\'attente', 'mlf'),
            'annule' => __('Annulée', 'mlf'),
        );
        return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
    }
}
