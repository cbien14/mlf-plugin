<?php
/**
 * The main class for the MLF Plugin.
 *
 * This class handles the core functionality and integrates with WordPress.
 */
class MLF_Plugin {

    /**
     * Constructor for the class.
     */
    public function __construct() {
        // Initialize the plugin
        add_action('init', array($this, 'init'));
        // Custom post type "Événements de jeu" removed - using database tables instead
        // add_action('init', array($this, 'register_game_events_post_type'));
    }

    /**
     * Initialize the plugin.
     */
    public function init() {
        // Core functionality can be added here
    }

    /**
     * Run the plugin.
     */
    public function run() {
        // Initialize components immediately
        $this->initialize_components();
    }
    
    /**
     * Initialize all plugin components.
     */
    public function initialize_components() {
        // Initialize game events functionality
        $game_events = new MLF_Game_Events();

        // Initialize admin functionality (classe s'auto-enregistre)
        $admin = new MLF_Admin();
        
        // Initialize frontend functionality (gère le public et les shortcodes)
        $frontend = new MLF_Frontend();
        
        // Initialize user account functionality
        $user_account = new MLF_User_Account();
        
        // Initialize character sheets functionality after WordPress is fully loaded
        add_action('init', function() {
            if (class_exists('MLF_Character_Sheets')) {
                $character_sheets = new MLF_Character_Sheets();
            }
        });
    }
}