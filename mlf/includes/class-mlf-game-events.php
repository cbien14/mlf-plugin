<?php
/**
 * Game Events functionality for the MLF Plugin.
 *
 * This class handles game event specific functionality including meta boxes and custom fields.
 */
class MLF_Game_Events {

    /**
     * Constructor for the class.
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_game_event_meta_boxes'));
        add_action('save_post', array($this, 'save_game_event_meta'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_game_event_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_mlf_register_for_event', array($this, 'handle_event_registration'));
        add_action('wp_ajax_nopriv_mlf_register_for_event', array($this, 'handle_event_registration'));
        add_action('wp_ajax_mlf_quick_register', array($this, 'handle_quick_registration'));
        add_action('wp_ajax_mlf_load_more_events', array($this, 'handle_load_more_events'));
        add_action('wp_ajax_nopriv_mlf_load_more_events', array($this, 'handle_load_more_events'));
        
        // Frontend display
        add_filter('the_content', array($this, 'add_event_content'));
    }

    /**
     * Add meta boxes for game events.
     */
    public function add_game_event_meta_boxes() {
        add_meta_box(
            'game_event_details',
            __('Détails de l\'événement', 'mlf'),
            array($this, 'render_game_event_details_meta_box'),
            'mlf_game_event',
            'normal',
            'high'
        );

        add_meta_box(
            'game_event_players',
            __('Gestion des joueurs', 'mlf'),
            array($this, 'render_game_event_players_meta_box'),
            'mlf_game_event',
            'side',
            'default'
        );
    }

    /**
     * Render the game event details meta box.
     */
    public function render_game_event_details_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('save_game_event_meta', 'game_event_meta_nonce');

        // Vérifier que le post existe et a un ID valide
        if (!$post || !isset($post->ID) || !is_numeric($post->ID)) {
            echo '<p>' . __('Erreur: Impossible de charger les métadonnées du post.', 'mlf') . '</p>';
            return;
        }

        // Get current values avec vérification de sécurité
        $game_type = get_post_meta($post->ID, '_mlf_game_type', true) ?: '';
        $event_date = get_post_meta($post->ID, '_mlf_event_date', true) ?: '';
        $event_time = get_post_meta($post->ID, '_mlf_event_time', true) ?: '';
        $max_players = get_post_meta($post->ID, '_mlf_max_players', true) ?: '';
        $location = get_post_meta($post->ID, '_mlf_location', true) ?: '';
        $difficulty_level = get_post_meta($post->ID, '_mlf_difficulty_level', true) ?: '';
        $registration_deadline = get_post_meta($post->ID, '_mlf_registration_deadline', true) ?: '';

        ?>
        <table class="form-table">
            <tr>
                <th><label for="mlf_game_type"><?php _e('Type de jeu', 'mlf'); ?></label></th>
                <td>
                    <select id="mlf_game_type" name="mlf_game_type" style="width: 100%;">
                        <option value=""><?php _e('Sélectionner le type de jeu', 'mlf'); ?></option>
                        <option value="jdr" <?php selected($game_type, 'jdr'); ?>><?php _e('JDR', 'mlf'); ?></option>
                        <option value="murder" <?php selected($game_type, 'murder'); ?>><?php _e('Murder', 'mlf'); ?></option>
                        <option value="jeu_de_societe" <?php selected($game_type, 'jeu_de_societe'); ?>><?php _e('Jeu de société', 'mlf'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="mlf_event_date"><?php _e('Date de l\'événement', 'mlf'); ?></label></th>
                <td>
                    <input type="date" id="mlf_event_date" name="mlf_event_date" value="<?php echo esc_attr($event_date); ?>" style="width: 100%;" />
                </td>
            </tr>
            <tr>
                <th><label for="mlf_event_time"><?php _e('Heure de l\'événement', 'mlf'); ?></label></th>
                <td>
                    <input type="time" id="mlf_event_time" name="mlf_event_time" value="<?php echo esc_attr($event_time); ?>" style="width: 100%;" />
                </td>
            </tr>
            <tr>
                <th><label for="mlf_max_players"><?php _e('Nombre maximum de joueurs', 'mlf'); ?></label></th>
                <td>
                    <input type="number" id="mlf_max_players" name="mlf_max_players" value="<?php echo esc_attr($max_players); ?>" min="1" max="100" style="width: 100%;" />
                </td>
            </tr>
            <tr>
                <th><label for="mlf_location"><?php _e('Lieu', 'mlf'); ?></label></th>
                <td>
                    <input type="text" id="mlf_location" name="mlf_location" value="<?php echo esc_attr($location); ?>" style="width: 100%;" />
                    <p class="description"><?php _e('Entrez le lieu de l\'événement ou "En ligne" pour les événements virtuels.', 'mlf'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="mlf_difficulty_level"><?php _e('Niveau de difficulté', 'mlf'); ?></label></th>
                <td>
                    <select id="mlf_difficulty_level" name="mlf_difficulty_level" style="width: 100%;">
                        <option value=""><?php _e('Sélectionner la difficulté', 'mlf'); ?></option>
                        <option value="beginner" <?php selected($difficulty_level, 'beginner'); ?>><?php _e('Débutant', 'mlf'); ?></option>
                        <option value="intermediate" <?php selected($difficulty_level, 'intermediate'); ?>><?php _e('Intermédiaire', 'mlf'); ?></option>
                        <option value="advanced" <?php selected($difficulty_level, 'advanced'); ?>><?php _e('Avancé', 'mlf'); ?></option>
                        <option value="expert" <?php selected($difficulty_level, 'expert'); ?>><?php _e('Expert', 'mlf'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="mlf_registration_deadline"><?php _e('Date limite d\'inscription', 'mlf'); ?></label></th>
                <td>
                    <input type="datetime-local" id="mlf_registration_deadline" name="mlf_registration_deadline" value="<?php echo esc_attr($registration_deadline); ?>" style="width: 100%;" />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render the player management meta box.
     */
    public function render_game_event_players_meta_box($post) {
        // Vérifier que le post existe et a un ID valide
        if (!$post || !isset($post->ID) || !is_numeric($post->ID)) {
            echo '<p>Erreur: Impossible de charger les données des joueurs.</p>';
            return;
        }
        
        $registered_players = get_post_meta($post->ID, '_mlf_registered_players', true);
        if (!is_array($registered_players)) {
            $registered_players = array();
        }

        $max_players = get_post_meta($post->ID, '_mlf_max_players', true) ?: 0;
        $current_count = count($registered_players);

        ?>
        <div class="mlf-player-info">
            <p><strong><?php _e('Inscriptions actuelles :', 'mlf'); ?></strong> <?php echo $current_count; ?></p>
            <?php if ($max_players): ?>
                <p><strong><?php _e('Nombre maximum de joueurs :', 'mlf'); ?></strong> <?php echo $max_players; ?></p>
                <p><strong><?php _e('Places disponibles :', 'mlf'); ?></strong> <?php echo max(0, $max_players - $current_count); ?></p>
            <?php endif; ?>
        </div>

        <?php if (!empty($registered_players)): ?>
            <div class="mlf-registered-players">
                <h4><?php _e('Joueurs inscrits :', 'mlf'); ?></h4>
                <ul>
                    <?php foreach ($registered_players as $player): ?>
                        <li><?php echo esc_html($player['name']); ?> (<?php echo esc_html($player['email']); ?>)</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <p><em><?php _e('Aucun joueur inscrit pour le moment.', 'mlf'); ?></em></p>
        <?php endif; ?>

        <div class="mlf-registration-actions">
            <p><a href="#" class="button" id="mlf-view-registrations"><?php _e('Voir toutes les inscriptions', 'mlf'); ?></a></p>
        </div>
        <?php
    }

    /**
     * Save game event meta data.
     */
    public function save_game_event_meta($post_id) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the nonce
        if (!isset($_POST['game_event_meta_nonce']) || !wp_verify_nonce($_POST['game_event_meta_nonce'], 'save_game_event_meta')) {
            return;
        }

        // Check the user's permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check if this is the correct post type
        if (get_post_type($post_id) !== 'mlf_game_event') {
            return;
        }

        // Save the meta fields
        $meta_fields = array(
            'mlf_game_type',
            'mlf_event_date',
            'mlf_event_time',
            'mlf_max_players',
            'mlf_location',
            'mlf_difficulty_level',
            'mlf_registration_deadline'
        );

        foreach ($meta_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
    }

    /**
     * Enqueue scripts for game events.
     */
    public function enqueue_game_event_scripts() {
        if (is_singular('mlf_game_event') || is_post_type_archive('mlf_game_event')) {
            wp_enqueue_script('mlf-game-events', plugin_dir_url(MLF_PLUGIN_PATH . 'mlf-plugin.php') . 'public/js/mlf-game-events.js', array('jquery'), '1.0.0', true);
            wp_enqueue_style('mlf-game-events', plugin_dir_url(MLF_PLUGIN_PATH . 'mlf-plugin.php') . 'public/css/mlf-game-events.css', array(), '1.0.0');
            
            // Localize script for AJAX
            wp_localize_script('mlf-game-events', 'mlf_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mlf_ajax_nonce'),
            ));
        }
    }

    /**
     * Get game event details for a specific post.
     */
    public static function get_event_details($post_id) {
        return array(
            'game_type' => get_post_meta($post_id, '_mlf_game_type', true) ?: '',
            'event_date' => get_post_meta($post_id, '_mlf_event_date', true) ?: '',
            'event_time' => get_post_meta($post_id, '_mlf_event_time', true) ?: '',
            'max_players' => get_post_meta($post_id, '_mlf_max_players', true) ?: '',
            'location' => get_post_meta($post_id, '_mlf_location', true) ?: '',
            'difficulty_level' => get_post_meta($post_id, '_mlf_difficulty_level', true) ?: '',
            'registration_deadline' => get_post_meta($post_id, '_mlf_registration_deadline', true) ?: '',
            'registered_players' => get_post_meta($post_id, '_mlf_registered_players', true) ?: array(),
        );
    }

    /**
     * Handle event registration via AJAX.
     */
    public function handle_event_registration() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mlf_ajax_nonce')) {
            wp_die('Security check failed');
        }

        $event_id = intval($_POST['event_id']);
        parse_str($_POST['form_data'], $form_data);

        // Validate required fields
        if (empty($form_data['player_name']) || empty($form_data['player_email'])) {
            wp_send_json_error(array('message' => 'Name and email are required.'));
        }

        // Check if event exists and registration is open
        if (get_post_type($event_id) !== 'mlf_game_event') {
            wp_send_json_error(array('message' => 'Invalid event.'));
        }

        $event_details = self::get_event_details($event_id);
        $registered_players = $event_details['registered_players'];
        $max_players = intval($event_details['max_players']);

        // Check if event is full
        if ($max_players && count($registered_players) >= $max_players) {
            wp_send_json_error(array('message' => 'This event is full.'));
        }

        // Check registration deadline
        if ($event_details['registration_deadline']) {
            $deadline = strtotime($event_details['registration_deadline']);
            if (time() > $deadline) {
                wp_send_json_error(array('message' => 'Registration deadline has passed.'));
            }
        }

        // Check if already registered
        foreach ($registered_players as $player) {
            if ($player['email'] === $form_data['player_email']) {
                wp_send_json_error(array('message' => 'You are already registered for this event.'));
            }
        }

        // Add new player
        $new_player = array(
            'name' => sanitize_text_field($form_data['player_name']),
            'email' => sanitize_email($form_data['player_email']),
            'phone' => sanitize_text_field($form_data['player_phone'] ?? ''),
            'experience' => sanitize_text_field($form_data['player_experience'] ?? ''),
            'notes' => sanitize_textarea_field($form_data['player_notes'] ?? ''),
            'registration_date' => current_time('mysql'),
        );

        $registered_players[] = $new_player;
        update_post_meta($event_id, '_mlf_registered_players', $registered_players);

        // Send confirmation email (optional)
        $this->send_registration_confirmation($event_id, $new_player);

        wp_send_json_success(array(
            'message' => 'Registration successful!',
            'new_count' => count($registered_players)
        ));
    }

    /**
     * Handle quick registration for logged-in users.
     */
    public function handle_quick_registration() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to register.'));
        }

        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mlf_ajax_nonce')) {
            wp_die('Security check failed');
        }

        $event_id = intval($_POST['event_id']);
        $user = wp_get_current_user();

        // Use user data for registration
        $form_data = array(
            'player_name' => $user->display_name,
            'player_email' => $user->user_email,
            'player_phone' => get_user_meta($user->ID, 'phone', true),
            'player_experience' => '',
            'player_notes' => ''
        );

        // Process same as regular registration
        $_POST['form_data'] = http_build_query($form_data);
        $this->handle_event_registration();
    }

    /**
     * Handle loading more events via AJAX.
     */
    public function handle_load_more_events() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mlf_ajax_nonce')) {
            wp_die('Security check failed');
        }

        $page = intval($_POST['page']);
        
        $args = array(
            'post_type' => 'mlf_game_event',
            'posts_per_page' => 12,
            'paged' => $page,
            'meta_key' => '_mlf_event_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_compare' => '>=',
            'meta_value' => date('Y-m-d'),
        );

        $events = new WP_Query($args);
        
        ob_start();
        if ($events->have_posts()) {
            while ($events->have_posts()) {
                $events->the_post();
                mlf_display_game_event_card(get_the_ID());
            }
        }
        $html = ob_get_clean();
        wp_reset_postdata();

        wp_send_json_success(array(
            'html' => $html,
            'has_more' => ($page < $events->max_num_pages)
        ));
    }

    /**
     * Add event content to single event posts.
     */
    public function add_event_content($content) {
        if (is_singular('mlf_game_event') && in_the_loop() && is_main_query()) {
            ob_start();
            mlf_display_game_event(get_the_ID());
            $event_content = ob_get_clean();
            return $event_content;
        }
        return $content;
    }

    /**
     * Send registration confirmation email.
     */
    private function send_registration_confirmation($event_id, $player_data) {
        $post = get_post($event_id);
        $event_details = self::get_event_details($event_id);
        
        $subject = sprintf(__('Registration Confirmation: %s', 'mlf'), $post->post_title);
        
        $message = sprintf(
            __("Hello %s,\n\nYou have successfully registered for the following event:\n\n%s\n\nEvent Details:\nDate: %s\nTime: %s\nLocation: %s\n\nWe look forward to seeing you there!\n\nBest regards,\nThe MLF Team", 'mlf'),
            $player_data['name'],
            $post->post_title,
            $event_details['event_date'] ? date('F j, Y', strtotime($event_details['event_date'])) : 'TBD',
            $event_details['event_time'] ? date('g:i A', strtotime($event_details['event_time'])) : 'TBD',
            $event_details['location'] ?: 'TBD'
        );

        wp_mail($player_data['email'], $subject, $message);
    }
}
