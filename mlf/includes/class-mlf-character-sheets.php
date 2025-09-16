<?php
/**
 * Character Sheets Manager for the MLF Plugin.
 *
 * This class handles character sheet file uploads and access control.
 * Only session administrators can upload files. Players can only view their files.
 */
class MLF_Character_Sheets {

    /**
     * Constructor for the class.
     */
    public function __construct() {
        // Add character sheet management to admin interface
        add_action('init', array($this, 'add_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_character_scripts'));
        
        // AJAX handlers for character sheet file management
        add_action('wp_ajax_mlf_upload_character_sheet', array($this, 'handle_upload_character_sheet'));
        add_action('wp_ajax_mlf_delete_character_sheet', array($this, 'handle_delete_character_sheet'));
        add_action('wp_ajax_mlf_download_character_sheet', array($this, 'handle_download_character_sheet'));
        
                // File download handler - must run early before any output
        add_action('template_redirect', array($this, 'handle_file_download'), 1);
    }

    /**
     * Register shortcodes.
     */
    public function add_shortcodes() {
        add_shortcode('mlf_character_sheets', array($this, 'display_character_sheets'));
    }

    /**
     * Enqueue character sheet scripts and styles.
     */
    public function enqueue_character_scripts() {
        wp_enqueue_style('mlf-character-css', plugin_dir_url(dirname(__FILE__)) . 'public/css/mlf-public.css', array(), '1.0.0');
        wp_enqueue_script('mlf-character-js', plugin_dir_url(dirname(__FILE__)) . 'public/js/mlf-public.js', array('jquery'), '1.0.0', true);
        
        wp_localize_script('mlf-character-js', 'mlf_character_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mlf_character_nonce'),
            'max_file_size' => wp_max_upload_size(),
        ));
    }

    /**
     * Handle file download requests.
     */
    public function handle_file_download() {
        if (isset($_GET['mlf_download_sheet']) && isset($_GET['nonce'])) {
            error_log('MLF: Download request detected');
            $sheet_id = intval($_GET['mlf_download_sheet']);
            $nonce = sanitize_text_field($_GET['nonce']);
            
            if (!wp_verify_nonce($nonce, 'mlf_download_sheet_' . $sheet_id)) {
                error_log('MLF: Invalid nonce for sheet ' . $sheet_id);
                wp_die('Accès refusé - lien expiré');
            }
            
            error_log('MLF: Serving file for sheet ' . $sheet_id);
            $this->serve_character_sheet_file($sheet_id);
        }
    }

    /**
     * Display character sheets for a session.
     */
    public function display_character_sheets($atts) {
        $atts = shortcode_atts(array(
            'session_id' => 0,
            'user_id' => 0,
            'mode' => 'view', // 'view', 'manage'
        ), $atts);

        $session_id = intval($atts['session_id']);
        $user_id = intval($atts['user_id']) ?: get_current_user_id();
        $mode = sanitize_text_field($atts['mode']);

        if (!$session_id) {
            return '<div class="mlf-error">' . __('ID de session requis.', 'mlf') . '</div>';
        }

        // Check permissions
        if (!$this->can_view_character_sheets($session_id, $user_id, $mode)) {
            return '<div class="mlf-error">' . __('Vous n\'avez pas les permissions pour voir ces fiches.', 'mlf') . '</div>';
        }

        $character_sheets = $this->get_character_sheets($session_id, $user_id, $mode);

        ob_start();
        ?>
        <div class="mlf-character-sheets" data-session-id="<?php echo esc_attr($session_id); ?>">
            <div class="mlf-character-sheets-header">
                <h3><?php _e('Fiches de Personnage', 'mlf'); ?></h3>
                
                <?php if ($mode === 'manage' && $this->can_upload_character_sheets($session_id)): ?>
                    <button class="mlf-btn mlf-btn-primary mlf-upload-character-btn" 
                            data-session-id="<?php echo esc_attr($session_id); ?>">
                        <?php _e('📁 Ajouter une fiche', 'mlf'); ?>
                    </button>
                <?php endif; ?>
            </div>

            <?php if (empty($character_sheets)): ?>
                <div class="mlf-no-character-sheets">
                    <?php if ($mode === 'view'): ?>
                        <p><?php _e('Aucune fiche de personnage disponible pour cette session.', 'mlf'); ?></p>
                    <?php else: ?>
                        <p><?php _e('Aucune fiche uploadée pour cette session. Ajoutez la première !', 'mlf'); ?></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="mlf-character-sheets-list">
                    <?php foreach ($character_sheets as $sheet): ?>
                        <div class="mlf-character-sheet-card" data-sheet-id="<?php echo esc_attr($sheet['id']); ?>">
                            <div class="mlf-character-header">
                                <div class="mlf-character-info">
                                    <h4 class="mlf-character-name">
                                        <?php echo esc_html($sheet['file_original_name']); ?>
                                    </h4>
                                    <div class="mlf-character-meta">
                                        <span class="mlf-player-name">
                                            <?php _e('Joueur:', 'mlf'); ?> 
                                            <?php echo esc_html($sheet['player_name']); ?>
                                        </span>
                                        <span class="mlf-file-info">
                                            <?php echo esc_html($this->format_file_size($sheet['file_size'])); ?> • 
                                            <?php echo esc_html($this->get_file_type_label($sheet['file_type'])); ?>
                                        </span>
                                        <?php if ($sheet['is_private']): ?>
                                            <span class="mlf-private-indicator"><?php _e('🔒 Privé', 'mlf'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mlf-character-actions">
                                    <?php 
                                    $download_url = $this->get_download_url($sheet['id']);
                                    if ($this->can_download_sheet($sheet, $user_id)):
                                    ?>
                                        <a href="<?php echo esc_url($download_url); ?>" 
                                           class="mlf-btn mlf-btn-small mlf-btn-primary mlf-download-btn"
                                           target="_blank">
                                            <?php _e('📄 Télécharger', 'mlf'); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($mode === 'manage' || current_user_can('manage_options')): ?>
                                        <button class="mlf-btn mlf-btn-small mlf-btn-danger mlf-delete-character-btn" 
                                                data-sheet-id="<?php echo esc_attr($sheet['id']); ?>"
                                                data-file-name="<?php echo esc_attr($sheet['file_original_name']); ?>">
                                            <?php _e('🗑️ Supprimer', 'mlf'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($sheet['file_description'])): ?>
                                <div class="mlf-character-content">
                                    <div class="mlf-character-description">
                                        <h5><?php _e('Description', 'mlf'); ?></h5>
                                        <?php echo wp_kses_post(wpautop($sheet['file_description'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mlf-character-footer">
                                <small class="mlf-creation-info">
                                    <?php printf(
                                        __('Uploadé le %s par %s', 'mlf'),
                                        date_i18n('d/m/Y à H:i', strtotime($sheet['uploaded_at'])),
                                        esc_html($sheet['uploader_name'])
                                    ); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Upload Modal -->
        <div id="mlf-upload-modal" class="mlf-modal" style="display: none;">
            <div class="mlf-modal-content">
                <span class="mlf-close">&times;</span>
                <h3><?php _e('Ajouter une fiche de personnage', 'mlf'); ?></h3>
                
                <form id="mlf-upload-form" enctype="multipart/form-data">
                    <input type="hidden" id="upload-session-id" name="session_id" value="<?php echo esc_attr($session_id); ?>">
                    
                    <div class="mlf-form-group">
                        <label for="character-player-select"><?php _e('Joueur:', 'mlf'); ?></label>
                        <select id="character-player-select" name="player_id" required>
                            <option value=""><?php _e('Sélectionner un joueur...', 'mlf'); ?></option>
                            <?php
                            $registered_players = $this->get_registered_players($session_id);
                            foreach ($registered_players as $player): ?>
                                <option value="<?php echo esc_attr($player['user_id']); ?>" 
                                        data-registration-id="<?php echo esc_attr($player['registration_id']); ?>">
                                    <?php echo esc_html($player['player_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mlf-form-group">
                        <label for="character-file"><?php _e('Fichier de personnage:', 'mlf'); ?></label>
                        <input type="file" id="character-file" name="character_file" required 
                               accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif">
                        <small>
                            <?php _e('Formats acceptés: PDF, DOC, DOCX, TXT, images (JPG, PNG, GIF)', 'mlf'); ?><br>
                            <?php printf(__('Taille maximum: %s', 'mlf'), size_format(wp_max_upload_size())); ?>
                        </small>
                    </div>

                    <div class="mlf-form-group">
                        <label for="file-description"><?php _e('Description (optionnelle):', 'mlf'); ?></label>
                        <textarea id="file-description" name="file_description" rows="3" 
                                  placeholder="<?php _e('Description de la fiche de personnage...', 'mlf'); ?>"></textarea>
                    </div>

                    <div class="mlf-form-group">
                        <label>
                            <input type="checkbox" id="file-is-private" name="is_private" value="1">
                            <?php _e('Fiche privée (visible uniquement par les administrateurs)', 'mlf'); ?>
                        </label>
                    </div>

                    <div class="mlf-form-actions">
                        <button type="submit" class="mlf-btn mlf-btn-primary">
                            <?php _e('📁 Uploader', 'mlf'); ?>
                        </button>
                        <button type="button" class="mlf-btn mlf-btn-secondary mlf-cancel-btn">
                            <?php _e('Annuler', 'mlf'); ?>
                        </button>
                    </div>
                    
                    <div class="mlf-upload-progress" style="display: none;">
                        <div class="mlf-progress-bar">
                            <div class="mlf-progress-fill"></div>
                        </div>
                        <div class="mlf-progress-text">Upload en cours...</div>
                    </div>
                </form>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var modal = $('#mlf-upload-modal');
            var form = $('#mlf-upload-form');

            // Ouvrir le modal d'upload
            $('.mlf-upload-character-btn').on('click', function() {
                form[0].reset();
                $('.mlf-upload-progress').hide();
                modal.show();
            });

            // Fermer le modal
            $('.mlf-close, .mlf-cancel-btn').on('click', function() {
                modal.hide();
            });

            // Soumettre le formulaire d'upload
            form.on('submit', function(e) {
                e.preventDefault();
                uploadCharacterSheet();
            });

            // Supprimer une fiche
            $('.mlf-delete-character-btn').on('click', function() {
                var sheetId = $(this).data('sheet-id');
                var fileName = $(this).data('file-name');
                
                if (confirm('Êtes-vous sûr de vouloir supprimer la fiche "' + fileName + '" ?')) {
                    deleteCharacterSheet(sheetId);
                }
            });

            function uploadCharacterSheet() {
                var formData = new FormData(form[0]);
                formData.append('action', 'mlf_upload_character_sheet');
                formData.append('nonce', mlf_character_ajax.nonce);

                $('.mlf-upload-progress').show();
                $('.mlf-progress-fill').css('width', '0%');

                $.ajax({
                    url: mlf_character_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: function() {
                        var xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener("progress", function(evt) {
                            if (evt.lengthComputable) {
                                var percentComplete = (evt.loaded / evt.total) * 100;
                                $('.mlf-progress-fill').css('width', percentComplete + '%');
                                $('.mlf-progress-text').text('Upload: ' + Math.round(percentComplete) + '%');
                            }
                        }, false);
                        return xhr;
                    },
                    success: function(response) {
                        $('.mlf-upload-progress').hide();
                        if (response.success) {
                            modal.hide();
                            location.reload(); // Recharger pour voir le nouveau fichier
                        } else {
                            alert('Erreur: ' + response.data.message);
                        }
                    },
                    error: function() {
                        $('.mlf-upload-progress').hide();
                        alert('Erreur lors de l\'upload du fichier.');
                    }
                });
            }

            function deleteCharacterSheet(sheetId) {
                $.ajax({
                    url: mlf_character_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mlf_delete_character_sheet',
                        sheet_id: sheetId,
                        nonce: mlf_character_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('[data-sheet-id="' + sheetId + '"]').fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            alert('Erreur: ' + response.data.message);
                        }
                    }
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Get character sheets for a session.
     */
    private function get_character_sheets($session_id, $user_id, $mode) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mlf_character_sheets';
        
        $where_conditions = array("cs.session_id = %d");
        $query_params = array($session_id);
        
        // Filter by user permissions
        if ($mode === 'view' && !current_user_can('manage_options')) {
            $where_conditions[] = "(cs.player_id = %d OR cs.is_private = 0)";
            $query_params[] = $user_id;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT cs.*, 
                   player.display_name as player_name,
                   uploader.display_name as uploader_name
            FROM {$table_name} cs
            LEFT JOIN {$wpdb->users} player ON cs.player_id = player.ID
            LEFT JOIN {$wpdb->users} uploader ON cs.uploaded_by = uploader.ID
            WHERE {$where_clause}
            ORDER BY cs.uploaded_at DESC
        ", $query_params), ARRAY_A);
        
        return $results ?: array();
    }

    /**
     * Get registered players for a session.
     */
    private function get_registered_players($session_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mlf_player_registrations';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT r.id as registration_id, r.user_id, u.display_name as player_name
            FROM {$table_name} r
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
            WHERE r.session_id = %d AND r.registration_status = 'confirme'
            ORDER BY u.display_name
        ", $session_id), ARRAY_A);
        
        return $results ?: array();
    }

    /**
     * Check if user can view character sheets.
     */
    private function can_view_character_sheets($session_id, $user_id, $mode) {
        if (current_user_can('manage_options')) {
            return true;
        }
        
        if ($mode === 'manage') {
            return $this->is_session_creator($session_id, $user_id);
        }
        
        // For view mode, check if user is registered for the session
        return $this->is_user_registered($session_id, $user_id);
    }

    /**
     * Check if user can upload character sheets.
     */
    private function can_upload_character_sheets($session_id) {
        $user_id = get_current_user_id();
        return current_user_can('manage_options') || $this->is_session_creator($session_id, $user_id);
    }

    /**
     * Check if user is the session creator.
     */
    private function is_session_creator($session_id, $user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mlf_game_sessions';
        
        // Use game_master_id as the authoritative owner of a session
        $creator_id = $wpdb->get_var($wpdb->prepare(
            "SELECT game_master_id FROM {$table_name} WHERE id = %d",
            $session_id
        ));
        
        return $creator_id == $user_id;
    }

    /**
     * Check if user is registered for the session.
     */
    private function is_user_registered($session_id, $user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mlf_player_registrations';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE session_id = %d AND user_id = %d AND registration_status = 'confirme'",
            $session_id, $user_id
        ));
        
        return $count > 0;
    }

    /**
     * Check if user can download a specific sheet.
     */
    private function can_download_sheet($sheet, $user_id) {
        if (current_user_can('manage_options')) {
            return true;
        }
        
        if ($sheet['is_private'] && $sheet['player_id'] != $user_id) {
            return false;
        }
        
        return $sheet['player_id'] == $user_id || $this->is_session_creator($sheet['session_id'], $user_id);
    }

    /**
     * Get download URL for a character sheet.
     */
    private function get_download_url($sheet_id) {
    $nonce = wp_create_nonce('mlf_download_sheet_' . $sheet_id);
    // Build URL from plugin root to avoid subfolder path issues
    $url = plugins_url('download-sheet.php', MLF_PLUGIN_PATH . 'mlf-plugin.php');
    return add_query_arg(array('sheet_id' => $sheet_id, 'nonce' => $nonce), $url);
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
     * Get file type label.
     */
    private function get_file_type_label($file_type) {
        $types = array(
            'pdf' => 'PDF',
            'doc' => 'Word',
            'docx' => 'Word',
            'txt' => 'Texte',
            'jpg' => 'Image',
            'jpeg' => 'Image',
            'png' => 'Image',
            'gif' => 'Image',
        );
        
        return isset($types[$file_type]) ? $types[$file_type] : strtoupper($file_type);
    }

    /**
     * Serve character sheet file for download.
     */
    private function serve_character_sheet_file($sheet_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mlf_character_sheets';
        
        $sheet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $sheet_id
        ), ARRAY_A);
        
        if (!$sheet) {
            wp_die('Fiche non trouvée');
        }
        
        $user_id = get_current_user_id();
        if (!$this->can_download_sheet($sheet, $user_id)) {
            wp_die('Accès refusé');
        }
        
        $file_path = $sheet['file_path'];
        if (!file_exists($file_path)) {
            wp_die('Fichier non trouvé');
        }
        
        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $sheet['file_original_name'] . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private');
        header('Pragma: private');
        
        // Output file
        readfile($file_path);
        exit;
    }

    /**
     * Handle file upload AJAX request.
     */
    public function handle_upload_character_sheet() {
        // Verify nonce
        $nonce_field = isset($_POST['mlf_character_nonce']) ? $_POST['mlf_character_nonce'] : ($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce_field, 'mlf_character_nonce')) {
            wp_send_json_error(array('message' => 'Erreur de sécurité'));
        }
        
        $session_id = intval($_POST['session_id']);
        $player_id = intval($_POST['player_id']);
        $file_description = sanitize_textarea_field($_POST['file_description']);
        $is_private = isset($_POST['is_private']) ? 1 : 0;
        $user_id = get_current_user_id();
        
        // Check permissions
        if (!$this->can_upload_character_sheets($session_id)) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes'));
        }
        
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
        
        // Save to database
        global $wpdb;
        $table_name = $wpdb->prefix . 'mlf_character_sheets';
        
        // Find registration_id for this player and session
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}mlf_player_registrations 
             WHERE session_id = %d AND user_id = %d",
            $session_id, $player_id
        ));
        
        if (!$registration) {
            wp_send_json_error(array('message' => 'Inscription non trouvée pour ce joueur'));
        }
        
        $result = $wpdb->insert(
            $table_name,
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
     * Handle delete character sheet AJAX request.
     */
    public function handle_delete_character_sheet() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mlf_character_nonce')) {
            wp_die('Erreur de sécurité');
        }
        
        $sheet_id = intval($_POST['sheet_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'mlf_character_sheets';
        
        // Get sheet info
        $sheet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $sheet_id
        ), ARRAY_A);
        
        if (!$sheet) {
            wp_send_json_error(array('message' => 'Fiche non trouvée'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options') && !$this->is_session_creator($sheet['session_id'], $user_id)) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes'));
        }
        
        // Delete file
        if (file_exists($sheet['file_path'])) {
            unlink($sheet['file_path']);
        }
        
        // Delete from database
        $result = $wpdb->delete(
            $table_name,
            array('id' => $sheet_id),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Erreur lors de la suppression'));
        }
        
        wp_send_json_success(array('message' => 'Fiche supprimée avec succès'));
    }
}
