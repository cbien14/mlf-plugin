<?php
/**
 * Plugin Name: MLF
 * Description: A WordPress plugin for managing game sessions organized through the website.
 * Version: 1.0.0
 * Author: Cbien1.4
 * Author URI: https://yourwebsite.com
 * License: GPL2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define the plugin path.
define( 'MLF_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// Include the necessary files.
require_once MLF_PLUGIN_PATH . 'includes/class-mlf-plugin.php';
require_once MLF_PLUGIN_PATH . 'includes/class-mlf-activator.php';
require_once MLF_PLUGIN_PATH . 'includes/class-mlf-deactivator.php';
require_once MLF_PLUGIN_PATH . 'includes/class-mlf-database-manager.php';
require_once MLF_PLUGIN_PATH . 'includes/class-mlf-game-events.php';
require_once MLF_PLUGIN_PATH . 'includes/mlf-templates.php';
require_once MLF_PLUGIN_PATH . 'includes/admin/class-mlf-admin.php';
require_once MLF_PLUGIN_PATH . 'public/class-mlf-public.php';
require_once MLF_PLUGIN_PATH . 'public/class-mlf-frontend.php';

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'MLF_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MLF_Deactivator', 'deactivate' ) );

// Initialize the plugin.
function run_mlf_plugin() {
    $plugin = new MLF_Plugin();
    $plugin->run();
}
run_mlf_plugin();