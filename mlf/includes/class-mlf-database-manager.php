<?php
/**
 * MLF Database Manager
 * 
 * Handles all database operations for game sessions and player registrations.
 */
class MLF_Database_Manager {
    
    /**
     * Get all game sessions.
     */
    public static function get_game_sessions($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => null,
            'game_type' => null,
            'date_from' => null,
            'date_to' => null,
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'session_date',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'mlf_game_sessions';
        $where_clauses = array();
        $where_values = array();
        
        if ($args['status']) {
            $where_clauses[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        if ($args['game_type']) {
            $where_clauses[] = "game_type = %s";
            $where_values[] = $args['game_type'];
        }
        
        if ($args['date_from']) {
            $where_clauses[] = "session_date >= %s";
            $where_values[] = $args['date_from'];
        }
        
        if ($args['date_to']) {
            $where_clauses[] = "session_date <= %s";
            $where_values[] = $args['date_to'];
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $order_sql = sprintf(' ORDER BY %s %s', $args['orderby'], $args['order']);
        $limit_sql = sprintf(' LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        
        $sql = "SELECT * FROM $table_name" . $where_sql . $order_sql . $limit_sql;
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Get a single game session by ID.
     */
    public static function get_game_session($session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_game_sessions';
        
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $session_id);
        
        return $wpdb->get_row($sql, ARRAY_A);
    }
    
    /**
     * Create a new game session.
     */
    public static function create_game_session($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_game_sessions';
        
        $defaults = array(
            'event_id' => null,
            'session_name' => '',
            'game_type' => 'jdr',
            'game_master_id' => null,
            'game_master_name' => '',
            'session_date' => current_time('Y-m-d'),
            'session_time' => '20:00:00',
            'duration_minutes' => 120,
            'max_players' => 6,
            'current_players' => 0,
            'location' => '',
            'difficulty_level' => 'debutant',
            'description' => '',
            'notes' => '',
            'status' => 'planifiee',
            'registration_deadline' => null
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Prepare the data for insertion
        $insert_data = array();
        $format = array();
        
        // Only include non-null values or required fields
        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                $insert_data[$key] = $value;
                
                // Set appropriate format based on field type
                switch ($key) {
                    case 'event_id':
                    case 'game_master_id':
                    case 'duration_minutes':
                    case 'max_players':
                    case 'current_players':
                        $format[] = '%d';
                        break;
                    default:
                        $format[] = '%s';
                        break;
                }
            } elseif (in_array($key, array('session_name', 'game_type', 'session_date', 'session_time', 'max_players'))) {
                // Required fields - include even if empty
                $insert_data[$key] = $value;
                if (in_array($key, array('max_players'))) {
                    $format[] = '%d';
                } else {
                    $format[] = '%s';
                }
            }
        }
        
        $result = $wpdb->insert(
            $table_name,
            $insert_data,
            $format
        );
        
        if ($result !== false) {
            return $wpdb->insert_id;
        }
        
        // Debug: Log the error if insertion fails
        if ($wpdb->last_error) {
            error_log('MLF Database Error: ' . $wpdb->last_error);
            error_log('MLF Insert Data: ' . print_r($insert_data, true));
        }
        
        return false;
    }
    
    /**
     * Update a game session.
     */
    public static function update_game_session($session_id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_game_sessions';
        
        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $session_id),
            null, // Let WordPress determine the format
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a game session.
     */
    public static function delete_game_session($session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_game_sessions';
        
        // First delete all registrations for this session
        self::delete_session_registrations($session_id);
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $session_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get player registrations for a session.
     */
    public static function get_session_registrations($session_id, $status = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_player_registrations';
        
        $where_sql = "WHERE session_id = %d";
        $where_values = array($session_id);
        
        if ($status) {
            $where_sql .= " AND registration_status = %s";
            $where_values[] = $status;
        }
        
        $sql = "SELECT * FROM $table_name $where_sql ORDER BY registration_date ASC";
        $sql = $wpdb->prepare($sql, $where_values);
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Register a player for a session.
     */
    public static function register_player($session_id, $player_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_player_registrations';
        
        // Check if player is already registered
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE session_id = %d AND player_email = %s",
            $session_id,
            $player_data['player_email']
        ));
        
        if ($existing) {
            return new WP_Error('already_registered', 'Le joueur est déjà inscrit à cette session.');
        }
        
        // Check if session is full
        $session = self::get_game_session($session_id);
        if (!$session) {
            return new WP_Error('invalid_session', 'Session invalide.');
        }
        
        $confirmed_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE session_id = %d AND registration_status = 'confirme'",
            $session_id
        ));
        
        $registration_status = 'en_attente';
        if ($confirmed_count >= $session['max_players']) {
            $registration_status = 'liste_attente';
        }
        
        $defaults = array(
            'session_id' => $session_id,
            'registration_status' => $registration_status,
            'registration_date' => current_time('mysql')
        );
        
        $player_data = wp_parse_args($player_data, $defaults);
        
        $result = $wpdb->insert(
            $table_name,
            $player_data,
            array(
                '%d', // session_id
                '%d', // user_id
                '%s', // player_name
                '%s', // player_email
                '%s', // player_phone
                '%s', // experience_level
                '%s', // character_name
                '%s', // character_class
                '%s', // special_requests
                '%s', // dietary_restrictions
                '%s', // registration_status
                '%s', // registration_date
                '%s', // confirmation_date
                '%s', // attendance_status
                '%s'  // notes
            )
        );
        
        if ($result !== false) {
            // Update current players count
            if ($registration_status === 'confirme') {
                self::update_session_player_count($session_id);
            }
            
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update player registration status.
     */
    public static function update_registration_status($registration_id, $status) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_player_registrations';
        
        $old_status = $wpdb->get_var($wpdb->prepare(
            "SELECT registration_status FROM $table_name WHERE id = %d",
            $registration_id
        ));
        
        $result = $wpdb->update(
            $table_name,
            array(
                'registration_status' => $status,
                'confirmation_date' => ($status === 'confirme') ? current_time('mysql') : null
            ),
            array('id' => $registration_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Update session player count if status changed
            if ($old_status !== $status) {
                $session_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT session_id FROM $table_name WHERE id = %d",
                    $registration_id
                ));
                self::update_session_player_count($session_id);
            }
        }
        
        return $result !== false;
    }
    
    /**
     * Delete player registration.
     */
    public static function delete_registration($registration_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_player_registrations';
        
        $session_id = $wpdb->get_var($wpdb->prepare(
            "SELECT session_id FROM $table_name WHERE id = %d",
            $registration_id
        ));
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $registration_id),
            array('%d')
        );
        
        if ($result !== false && $session_id) {
            self::update_session_player_count($session_id);
        }
        
        return $result !== false;
    }
    
    /**
     * Delete all registrations for a session.
     */
    private static function delete_session_registrations($session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_player_registrations';
        
        return $wpdb->delete(
            $table_name,
            array('session_id' => $session_id),
            array('%d')
        );
    }
    
    /**
     * Update the current player count for a session.
     */
    private static function update_session_player_count($session_id) {
        global $wpdb;
        
        $registrations_table = $wpdb->prefix . 'mlf_player_registrations';
        $sessions_table = $wpdb->prefix . 'mlf_game_sessions';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $registrations_table WHERE session_id = %d AND registration_status = 'confirme'",
            $session_id
        ));
        
        return $wpdb->update(
            $sessions_table,
            array('current_players' => $count),
            array('id' => $session_id),
            array('%d'),
            array('%d')
        );
    }
    
    /**
     * Get upcoming sessions for a specific game master.
     */
    public static function get_game_master_sessions($game_master_id, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_game_sessions';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE game_master_id = %d 
             AND session_date >= CURDATE() 
             AND status IN ('planifiee', 'en_cours')
             ORDER BY session_date ASC, session_time ASC 
             LIMIT %d",
            $game_master_id,
            $limit
        );
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Get player's registered sessions.
     */
    public static function get_player_sessions($player_email, $limit = 10) {
        global $wpdb;
        
        $sessions_table = $wpdb->prefix . 'mlf_game_sessions';
        $registrations_table = $wpdb->prefix . 'mlf_player_registrations';
        
        $sql = $wpdb->prepare(
            "SELECT s.*, r.registration_status, r.registration_date 
             FROM $sessions_table s
             INNER JOIN $registrations_table r ON s.id = r.session_id
             WHERE r.player_email = %s
             AND s.session_date >= CURDATE()
             ORDER BY s.session_date ASC, s.session_time ASC
             LIMIT %d",
            $player_email,
            $limit
        );
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
}
