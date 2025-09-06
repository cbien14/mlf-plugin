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
        
        if ($form && !empty($form['form_fields'])) {
            $form['form_fields'] = self::clean_and_decode_json($form['form_fields']);
        }
        
        return $form;
    }

    /**
     * Méthode robuste pour nettoyer et décoder le JSON des formulaires
     * Gère automatiquement tous les problèmes d'échappement
     */
    private static function clean_and_decode_json($json_string) {
        // Étape 1: Nettoyer les échappements multiples
        $cleaned = $json_string;
        
        // Si le JSON est encapsulé dans des quotes externes, les enlever
        if (substr($cleaned, 0, 1) === '"' && substr($cleaned, -1) === '"') {
            $cleaned = substr($cleaned, 1, -1);
        }
        
        // Corriger les échappements doubles de slashes
        $cleaned = str_replace('\\\\', '\\', $cleaned);
        
        // Corriger les échappements de quotes
        $cleaned = str_replace('\\"', '"', $cleaned);
        
        // Tenter le décodage
        $decoded = json_decode($cleaned, true);
        
        // Si ça échoue, essayer d'autres nettoyages
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Essayer de décoder le string original directement
            $decoded = json_decode($json_string, true);
            
            // Si ça échoue encore, nettoyer plus agressivement
            if (json_last_error() !== JSON_ERROR_NONE) {
                $cleaned = $json_string;
                $cleaned = stripslashes($cleaned); // Méthode PHP native
                $decoded = json_decode($cleaned, true);
            }
        }
        
        // Si on a encore un échec, retourner un formulaire par défaut
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            error_log("MLF: Formulaire corrompu, utilisation du défaut");
            return self::get_default_form_fields();
        }
        
        // Étape 2: Valider et nettoyer la structure des champs
        $validated_fields = array();
        foreach ($decoded as $index => $field) {
            if (!is_array($field) || empty($field['label']) || empty($field['type'])) {
                continue; // Ignorer les champs invalides
            }
            
            $validated_field = array(
                'type' => sanitize_text_field($field['type']),
                'name' => isset($field['name']) ? $field['name'] : 'field_' . $index,
                'label' => trim($field['label']),
                'required' => !empty($field['required'])
            );
            
            // Gérer les options pour select
            if ($field['type'] === 'select' && !empty($field['options'])) {
                if (is_array($field['options'])) {
                    $validated_field['options'] = $field['options'];
                } else {
                    // Convertir string en array si nécessaire
                    $options_lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $field['options']));
                    $validated_field['options'] = array_map('trim', array_filter($options_lines));
                }
            }
            
            // Ajouter placeholder si manquant
            if (!isset($field['placeholder'])) {
                if ($field['type'] === 'text') {
                    $validated_field['placeholder'] = 'Saisissez ' . strtolower($validated_field['label']) . '...';
                } elseif ($field['type'] === 'textarea') {
                    $validated_field['placeholder'] = 'Décrivez...';
                }
            } else {
                $validated_field['placeholder'] = $field['placeholder'];
            }
            
            $validated_fields[] = $validated_field;
        }
        
        return $validated_fields;
    }

    /**
     * Create or update custom form for a session.
     */
    public static function save_session_form($session_id, $form_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_custom_forms';
        
        // Valider et nettoyer les données avant sauvegarde
        $form_data = self::validate_and_clean_form_data($form_data);
        
        // Préparer les données
        $data = array(
            'session_id' => $session_id,
            'form_title' => sanitize_text_field($form_data['form_title'] ?? 'Formulaire d\'inscription'),
            'form_description' => sanitize_textarea_field($form_data['form_description'] ?? ''),
            'form_fields' => wp_json_encode($form_data['form_fields'] ?? array(), JSON_UNESCAPED_UNICODE),
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
     * Valide et nettoie les données de formulaire avant sauvegarde
     * Empêche la corruption des caractères spéciaux et des quotes
     */
    private static function validate_and_clean_form_data($form_data) {
        if (!isset($form_data['form_fields']) || !is_array($form_data['form_fields'])) {
            $form_data['form_fields'] = array();
            return $form_data;
        }
        
        $cleaned_fields = array();
        
        foreach ($form_data['form_fields'] as $index => $field) {
            if (!is_array($field)) {
                continue; // Ignorer les champs non-array
            }
            
            $cleaned_field = array(
                'type' => sanitize_text_field($field['type'] ?? 'text'),
                'name' => sanitize_text_field($field['name'] ?? 'field_' . $index),
                'label' => trim($field['label'] ?? 'Champ ' . ($index + 1)),
                'required' => !empty($field['required'])
            );
            
            // Nettoyer le placeholder
            if (!empty($field['placeholder'])) {
                $cleaned_field['placeholder'] = trim($field['placeholder']);
            }
            
            // Gérer les options pour select
            if ($field['type'] === 'select' && !empty($field['options'])) {
                if (is_array($field['options'])) {
                    $cleaned_field['options'] = array_map('trim', array_filter($field['options']));
                } else {
                    // Convertir string en array
                    $options_lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $field['options']));
                    $cleaned_field['options'] = array_map('trim', array_filter($options_lines));
                }
            }
            
            $cleaned_fields[] = $cleaned_field;
        }
        
        $form_data['form_fields'] = $cleaned_fields;
        return $form_data;
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
        
        // Récupérer le user_id via registration_id
        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}mlf_player_registrations WHERE id = %d",
                $registration_id
            )
        );
        
        if (!$user_id) {
            return false; // Registration ID invalide
        }
        
        $data = array(
            'session_id' => $session_id,
            'registration_id' => $registration_id,
            'user_id' => $user_id,
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
                array('%d', '%d', '%d', '%s', '%s'),
                array('%d', '%d')
            );
        } else {
            // Création
            $result = $wpdb->insert($table_name, $data, array('%d', '%d', '%d', '%s', '%s'));
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
