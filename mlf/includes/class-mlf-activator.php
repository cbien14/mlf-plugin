<?php
/**
 * MLF Plugin Activator
 * 
 * Handles plugin activation tasks including database table creation.
 */
class MLF_Activator {
    
    /**
     * Activate the plugin.
     * 
     * This method is called when the plugin is activated.
     * It creates the necessary database tables and sets up default options.
     */
    public static function activate() {
        // Vérifier que WordPress est prêt
        if (!function_exists('get_option') || !function_exists('add_option')) {
            return; // WordPress n'est pas encore prêt
        }
        
        // Create database tables
        self::create_game_sessions_table();
        self::create_player_registrations_table();
        self::create_custom_forms_tables();
        self::create_character_sheets_table();
        
        // Run database migrations if needed
        self::run_database_migrations();
        
        // Set default options
        self::set_default_options();
        
        // Update database version
        self::update_database_version();
        
        // Flush rewrite rules to ensure custom post type URLs work
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules();
        }
    }
    
    /**
     * Create the game sessions table.
     */
    private static function create_game_sessions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_game_sessions';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NULL,
            session_name varchar(255) NOT NULL,
            game_type enum('jdr', 'murder', 'jeu_de_societe') NOT NULL,
            game_master_id bigint(20) unsigned NULL,
            game_master_name varchar(255) NULL,
            session_date date NOT NULL,
            session_time time NOT NULL,
            duration_minutes int(11) DEFAULT 120,
            max_players int(11) NOT NULL DEFAULT 6,
            current_players int(11) DEFAULT 0,
            location varchar(255) NULL,
            difficulty_level enum('debutant', 'intermediaire', 'avance', 'expert') DEFAULT 'debutant',
            description text NULL,
            synopsis text NULL,
            trigger_warnings text NULL,
            additional_info text NULL,
            safety_tools text NULL,
            prerequisites text NULL,
            banner_image_url varchar(500) NULL,
            background_image_url varchar(500) NULL,
            notes text NULL,
            status enum('planifiee', 'en_cours', 'terminee', 'annulee') DEFAULT 'planifiee',
            registration_deadline datetime NULL,
            is_public tinyint(1) DEFAULT 1,
            requires_approval tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY game_master_id (game_master_id),
            KEY session_date (session_date),
            KEY status (status),
            KEY game_type (game_type),
            KEY is_public (is_public)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create the player registrations table.
     */
    private static function create_player_registrations_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_player_registrations';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            session_id int(11) NOT NULL,
            user_id bigint(20) unsigned NULL,
            player_name varchar(255) NOT NULL,
            player_email varchar(255) NOT NULL,
            player_phone varchar(20) NULL,
            experience_level enum('debutant', 'intermediaire', 'avance', 'expert') NULL,
            character_name varchar(255) NULL,
            character_class varchar(100) NULL,
            special_requests text NULL,
            dietary_restrictions text NULL,
            registration_status enum('en_attente', 'confirme', 'annule', 'liste_attente') DEFAULT 'en_attente',
            registration_date datetime DEFAULT CURRENT_TIMESTAMP,
            confirmation_date datetime NULL,
            attendance_status enum('present', 'absent', 'retard') NULL,
            notes text NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY user_id (user_id),
            KEY player_email (player_email),
            KEY registration_status (registration_status),
            UNIQUE KEY unique_session_player (session_id, player_email)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Set default plugin options.
     */
    private static function set_default_options() {
        // Set default plugin options with safe fallbacks
        $admin_email = get_option('admin_email');
        if (empty($admin_email)) {
            // Construction sécurisée d'un email par défaut
            $domain = 'localhost';
            if (isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) {
                // Nettoyage basique sans sanitize_text_field
                $domain = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $_SERVER['HTTP_HOST']);
            } elseif (isset($_SERVER['SERVER_NAME']) && !empty($_SERVER['SERVER_NAME'])) {
                // Nettoyage basique sans sanitize_text_field
                $domain = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $_SERVER['SERVER_NAME']);
            }
            $admin_email = 'admin@' . $domain;
        }
        
        $default_options = array(
            'mlf_enable_email_notifications' => 1,
            'mlf_default_session_duration' => 120, // minutes
            'mlf_max_players_default' => 6,
            'mlf_require_registration_confirmation' => 1,
            'mlf_allow_waitlist' => 1,
            'mlf_auto_confirm_registrations' => 0,
            'mlf_email_reminder_hours' => 24,
            'mlf_default_location' => 'Local associatif MLF',
            'mlf_contact_email' => $admin_email,
        );
        
        foreach ($default_options as $option_name => $option_value) {
            // Vérification plus sûre de l'existence de l'option
            $existing_value = get_option($option_name, false);
            if ($existing_value === false) {
                add_option($option_name, $option_value);
            }
        }
    }
    
    /**
     * Get the database version for upgrade management.
     */
    public static function get_database_version() {
        return '1.1.0'; // Incrémenté pour inclure la colonne user_id
    }
    
    /**
     * Check if database needs to be updated.
     */
    public static function needs_database_update() {
        $current_version = get_option('mlf_database_version', '0.0.0');
        return version_compare($current_version, self::get_database_version(), '<');
    }
    
    /**
     * Update database version option.
     */
    public static function update_database_version() {
        update_option('mlf_database_version', self::get_database_version());
    }
    
    /**
     * Run database migrations based on current version.
     */
    public static function run_database_migrations() {
        $current_version = get_option('mlf_database_version', '0.0.0');
        
        // Migration vers 1.1.0 - Ajouter user_id à custom_form_responses
        if (version_compare($current_version, '1.1.0', '<')) {
            self::migrate_to_1_1_0();
        }
        
        // Mettre à jour la version
        self::update_database_version();
    }
    
    /**
     * Migration vers version 1.1.0
     * Ajoute la colonne user_id à wp_mlf_custom_form_responses
     */
    private static function migrate_to_1_1_0() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_custom_form_responses';
        
        // Vérifier si la table existe
        $table_exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM information_schema.tables 
            WHERE table_schema = %s 
            AND table_name = %s
        ", DB_NAME, $table_name));
        
        if (!$table_exists) {
            // Table n'existe pas, la créer avec la nouvelle structure
            self::create_custom_form_responses_table();
            return;
        }
        
        // Vérifier si la colonne user_id existe déjà
        $column_exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM information_schema.columns 
            WHERE table_schema = %s 
            AND table_name = %s 
            AND column_name = 'user_id'
        ", DB_NAME, $table_name));
        
        if (!$column_exists) {
            // Ajouter la colonne user_id
            $wpdb->query("ALTER TABLE $table_name 
                         ADD COLUMN user_id int(11) NOT NULL COMMENT 'User who submitted the response' AFTER registration_id,
                         ADD KEY user_id (user_id)");
            
            // Populer la colonne avec les données existantes
            $wpdb->query("UPDATE $table_name r
                         INNER JOIN {$wpdb->prefix}mlf_player_registrations p ON r.registration_id = p.id
                         SET r.user_id = p.user_id
                         WHERE r.user_id = 0 OR r.user_id IS NULL");
        }
    }
    
    /**
     * Vérifier et réparer la structure de la base de données
     * Méthode utilitaire pour s'assurer que la DB est toujours fonctionnelle
     */
    public static function verify_and_repair_database() {
        global $wpdb;
        
        $issues_found = [];
        $fixes_applied = [];
        
        // 1. Vérifier table wp_mlf_custom_form_responses
        $table_name = $wpdb->prefix . 'mlf_custom_form_responses';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if (!$table_exists) {
            self::create_custom_form_responses_table();
            $fixes_applied[] = "Table $table_name créée";
        } else {
            // Vérifier colonne user_id
            $columns = $wpdb->get_results("DESCRIBE $table_name");
            $has_user_id = false;
            foreach ($columns as $col) {
                if ($col->Field === 'user_id') {
                    $has_user_id = true;
                    break;
                }
            }
            
            if (!$has_user_id) {
                $issues_found[] = "Colonne user_id manquante dans $table_name";
                
                // Ajouter la colonne
                $result = $wpdb->query("ALTER TABLE $table_name 
                                       ADD COLUMN user_id int(11) NOT NULL COMMENT 'User who submitted the response' AFTER registration_id,
                                       ADD KEY user_id (user_id)");
                
                if ($result !== false) {
                    $fixes_applied[] = "Colonne user_id ajoutée à $table_name";
                    
                    // Populer avec les données existantes
                    $updated = $wpdb->query("UPDATE $table_name r
                                           INNER JOIN {$wpdb->prefix}mlf_player_registrations p ON r.registration_id = p.id
                                           SET r.user_id = p.user_id
                                           WHERE r.user_id = 0 OR r.user_id IS NULL");
                    
                    if ($updated !== false) {
                        $fixes_applied[] = "user_id populé pour $updated réponses existantes";
                    }
                }
            }
        }
        
        // 2. Vérifier les autres tables principales
        $required_tables = [
            'mlf_game_sessions' => 'create_game_sessions_table',
            'mlf_player_registrations' => 'create_player_registrations_table',
            'mlf_custom_forms' => 'create_custom_forms_table'
        ];
        
        foreach ($required_tables as $table_suffix => $create_method) {
            $full_table_name = $wpdb->prefix . $table_suffix;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            
            if (!$exists) {
                $issues_found[] = "Table $full_table_name manquante";
                self::$create_method();
                $fixes_applied[] = "Table $full_table_name créée";
            }
        }
        
        return [
            'issues_found' => $issues_found,
            'fixes_applied' => $fixes_applied,
            'status' => empty($issues_found) ? 'ok' : 'repaired'
        ];
    }

    /**
     * Create the custom forms tables.
     */
    private static function create_custom_forms_tables() {
        self::create_custom_forms_table();
        self::create_custom_form_responses_table();
    }

    /**
     * Create the custom forms table - Session-specific forms.
     */
    private static function create_custom_forms_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_custom_forms';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            session_id int(11) NOT NULL,
            form_title varchar(255) NOT NULL DEFAULT 'Formulaire d''inscription',
            form_description text NULL,
            form_fields longtext NOT NULL COMMENT 'JSON data for form fields',
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_session_form (session_id),
            KEY is_active (is_active),
            FOREIGN KEY (session_id) REFERENCES {$wpdb->prefix}mlf_game_sessions(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create the custom form responses table.
     */
    private static function create_custom_form_responses_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_custom_form_responses';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            session_id int(11) NOT NULL,
            registration_id int(11) NOT NULL,
            user_id int(11) NOT NULL COMMENT 'User who submitted the response',
            response_data longtext NOT NULL COMMENT 'JSON data for form responses',
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY registration_id (registration_id),
            KEY user_id (user_id),
            UNIQUE KEY unique_response (session_id, registration_id),
            FOREIGN KEY (session_id) REFERENCES {$wpdb->prefix}mlf_game_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (registration_id) REFERENCES {$wpdb->prefix}mlf_player_registrations(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create the character sheets table.
     */
    private static function create_character_sheets_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mlf_character_sheets';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            session_id int(11) NOT NULL,
            player_id bigint(20) unsigned NOT NULL,
            registration_id int(11) NOT NULL,
            file_name varchar(255) NOT NULL,
            file_original_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_url varchar(500) NOT NULL,
            file_type varchar(100) NOT NULL COMMENT 'MIME type of the file',
            file_size bigint(20) NOT NULL COMMENT 'File size in bytes',
            file_description text NULL COMMENT 'Optional description of the character sheet',
            is_private tinyint(1) DEFAULT 0 COMMENT '0=visible to player and admins, 1=only to creator and admins',
            uploaded_by bigint(20) unsigned NOT NULL COMMENT 'User ID of the uploader (usually game master)',
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY player_id (player_id),
            KEY registration_id (registration_id),
            KEY uploaded_by (uploaded_by),
            FOREIGN KEY (session_id) REFERENCES {$wpdb->prefix}mlf_game_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (registration_id) REFERENCES {$wpdb->prefix}mlf_player_registrations(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}