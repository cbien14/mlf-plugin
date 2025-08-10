<?php
/**
 * Class to manage session-specific custom forms.
 */

class MLF_Session_Forms_Manager {

    /**
     * Initialize the session forms manager.
     */
    public function __construct() {
        // Actions et hooks seront ajoutés ici
    }

    /**
     * Get custom form for a specific session.
     */
    public static function get_session_form($session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_custom_forms';
        
        $form = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE session_id = %d AND is_active = 1", $session_id),
            ARRAY_A
        );
        
        if ($form) {
            $form['form_fields'] = json_decode($form['form_fields'], true);
        }
        
        return $form;
    }

    /**
     * Create or update custom form for a session.
     */
    public static function save_session_form($session_id, $form_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_custom_forms';
        
        // Préparer les données
        $data = array(
            'session_id' => $session_id,
            'form_title' => sanitize_text_field($form_data['form_title'] ?? 'Formulaire d\'inscription'),
            'form_description' => sanitize_textarea_field($form_data['form_description'] ?? ''),
            'form_fields' => wp_json_encode($form_data['form_fields'] ?? array()),
            'is_active' => 1
        );
        
        // Vérifier si un formulaire existe déjà pour cette session
        $existing_form = self::get_session_form($session_id);
        
        if ($existing_form) {
            // Mise à jour
            $data['updated_at'] = current_time('mysql');
            $result = $wpdb->update(
                $table_name,
                $data,
                array('session_id' => $session_id),
                array('%d', '%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );
            return $result !== false ? $existing_form['id'] : false;
        } else {
            // Création
            $result = $wpdb->insert($table_name, $data, array('%d', '%s', '%s', '%s', '%d'));
            return $result ? $wpdb->insert_id : false;
        }
    }

    /**
     * Delete custom form for a session.
     */
    public static function delete_session_form($session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_custom_forms';
        
        $result = $wpdb->delete(
            $table_name,
            array('session_id' => $session_id),
            array('%d')
        );
        
        return $result !== false;
    }

    /**
     * Check if session has a custom form.
     */
    public static function session_has_form($session_id) {
        $form = self::get_session_form($session_id);
        return !empty($form);
    }

    /**
     * Get default form fields for a game type.
     */
    public static function get_default_form_fields($game_type = 'murder') {
        $default_fields = array();
        
        switch ($game_type) {
            case 'murder':
                $default_fields = array(
                    array(
                        'type' => 'text',
                        'name' => 'character_preference',
                        'label' => 'Quel type de personnage préférez-vous jouer ?',
                        'placeholder' => 'Ex: Le détective, le suspect, la victime...',
                        'required' => false
                    ),
                    array(
                        'type' => 'select',
                        'name' => 'experience_level',
                        'label' => 'Votre niveau d\'expérience en murder party',
                        'required' => true,
                        'options' => array(
                            'novice' => 'Novice (première fois)',
                            'debutant' => 'Débutant (1-2 parties)',
                            'intermediaire' => 'Intermédiaire (3-10 parties)',
                            'experimente' => 'Expérimenté (plus de 10 parties)'
                        )
                    ),
                    array(
                        'type' => 'textarea',
                        'name' => 'special_requests',
                        'label' => 'Demandes particulières ou restrictions',
                        'placeholder' => 'Allergies alimentaires, contraintes physiques, préférences de jeu...',
                        'required' => false
                    )
                );
                break;
                
            case 'jdr':
                $default_fields = array(
                    array(
                        'type' => 'text',
                        'name' => 'character_concept',
                        'label' => 'Concept de personnage souhaité',
                        'placeholder' => 'Ex: Guerrier nain, Magicien elfe...',
                        'required' => false
                    ),
                    array(
                        'type' => 'select',
                        'name' => 'experience_level',
                        'label' => 'Votre expérience avec ce système de jeu',
                        'required' => true,
                        'options' => array(
                            'novice' => 'Novice (jamais joué)',
                            'debutant' => 'Débutant (quelques parties)',
                            'intermediaire' => 'Intermédiaire (régulier)',
                            'experimente' => 'Expérimenté (maître du système)'
                        )
                    )
                );
                break;
                
            case 'jeu_de_societe':
                $default_fields = array(
                    array(
                        'type' => 'select',
                        'name' => 'complexity_preference',
                        'label' => 'Préférence de complexité',
                        'required' => true,
                        'options' => array(
                            'simple' => 'Jeux simples et accessibles',
                            'moyen' => 'Complexité moyenne',
                            'complexe' => 'Jeux complexes et stratégiques'
                        )
                    ),
                    array(
                        'type' => 'textarea',
                        'name' => 'game_preferences',
                        'label' => 'Types de jeux préférés',
                        'placeholder' => 'Ex: Jeux de stratégie, coopératifs, party games...',
                        'required' => false
                    )
                );
                break;
        }
        
        return $default_fields;
    }

    /**
     * Save player response to session form.
     */
    public static function save_form_response($session_id, $registration_id, $response_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_custom_form_responses';
        
        $data = array(
            'session_id' => $session_id,
            'registration_id' => $registration_id,
            'response_data' => wp_json_encode($response_data),
            'submitted_at' => current_time('mysql')
        );
        
        // Vérifier si une réponse existe déjà
        $existing_response = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM $table_name WHERE session_id = %d AND registration_id = %d",
                $session_id,
                $registration_id
            )
        );
        
        if ($existing_response) {
            // Mise à jour
            $result = $wpdb->update(
                $table_name,
                $data,
                array('session_id' => $session_id, 'registration_id' => $registration_id),
                array('%d', '%d', '%s', '%s'),
                array('%d', '%d')
            );
        } else {
            // Création
            $result = $wpdb->insert($table_name, $data, array('%d', '%d', '%s', '%s'));
        }
        
        return $result !== false;
    }

    /**
     * Get player response to session form.
     */
    public static function get_form_response($session_id, $registration_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_custom_form_responses';
        
        $response = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE session_id = %d AND registration_id = %d",
                $session_id,
                $registration_id
            ),
            ARRAY_A
        );
        
        if ($response) {
            $response['response_data'] = json_decode($response['response_data'], true);
        }
        
        return $response;
    }

    /**
     * Get all responses for a session.
     */
    public static function get_session_responses($session_id) {
        global $wpdb;
        
        $responses_table = $wpdb->prefix . 'mlf_custom_form_responses';
        $registrations_table = $wpdb->prefix . 'mlf_player_registrations';
        
        $responses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, p.player_name, p.player_email 
                 FROM $responses_table r 
                 JOIN $registrations_table p ON r.registration_id = p.id 
                 WHERE r.session_id = %d 
                 ORDER BY r.submitted_at DESC",
                $session_id
            ),
            ARRAY_A
        );
        
        foreach ($responses as &$response) {
            $response['response_data'] = json_decode($response['response_data'], true);
        }
        
        return $responses;
    }
}
?>
