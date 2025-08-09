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
        // Initialize game events functionality
        $game_events = new MLF_Game_Events();

        // Initialize admin functionality
        if (is_admin()) {
            $admin = new MLF_Admin();
        }

        // Initialize public functionality
        $public = new MLF_Public();
        
        // Initialize frontend functionality
        $frontend = new MLF_Frontend();
    }
}