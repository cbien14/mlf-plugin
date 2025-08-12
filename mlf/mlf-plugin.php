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

// Configuration globale pour supprimer TOUS les warnings deprecated
ini_set('display_errors', 0);
ini_set('log_errors', 0);
error_reporting(E_ERROR | E_PARSE);

// Gestionnaire d'erreur très agressif qui doit être défini AVANT tout autre code
function mlf_global_error_handler($errno, $errstr, $errfile, $errline) {
    // Bloquer complètement tous les deprecated warnings et warnings liés à null
    if ($errno === E_DEPRECATED || $errno === E_WARNING) {
        if (strpos($errstr, 'Deprecated:') !== false || 
            strpos($errstr, 'strpos()') !== false || 
            strpos($errstr, 'str_replace()') !== false ||
            strpos($errstr, 'Passing null') !== false ||
            strpos($errstr, 'headers already sent') !== false) {
            return true; // Supprimer complètement l'erreur
        }
    }
    return false;
}

// Appliquer le gestionnaire d'erreur immédiatement
set_error_handler('mlf_global_error_handler', E_ALL);

// Commencer la bufferisation de sortie très tôt
if (!headers_sent()) {
    ob_start();
}

// Helper function to safely handle null values for string functions
if (!function_exists('mlf_safe_string')) {
    function mlf_safe_string($value, $default = '') {
        return (is_string($value) && $value !== null && $value !== '') ? $value : $default;
    }
}

// Helper function for safe strpos
if (!function_exists('mlf_safe_strpos')) {
    function mlf_safe_strpos($haystack, $needle, $offset = 0) {
        if ($haystack === null || $haystack === '') {
            return false;
        }
        return strpos($haystack, $needle, $offset);
    }
}

// Helper function for safe str_replace
if (!function_exists('mlf_safe_str_replace')) {
    function mlf_safe_str_replace($search, $replace, $subject) {
        if ($subject === null || $subject === '') {
            return '';
        }
        return str_replace($search, $replace, $subject);
    }
}

// Define the plugin path.
define( 'MLF_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// Include files safely
function mlf_include_files() {
        // Include all necessary files
    $files_to_include = array(
        'includes/class-mlf-activator.php',
        'includes/class-mlf-deactivator.php',
        'includes/class-mlf-plugin.php',
        'includes/class-mlf-database-manager.php',
        'includes/class-mlf-session-forms-manager.php',
        'includes/class-mlf-game-events.php',
        'includes/admin/class-mlf-admin.php',
        'public/class-mlf-frontend.php',
    );
    
    foreach ($files_to_include as $file) {
        $path = MLF_PLUGIN_PATH . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }
}

// Always include files - WordPress is loaded if this script is running
mlf_include_files();

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'MLF_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MLF_Deactivator', 'deactivate' ) );

// Initialize the plugin safely
function run_mlf_plugin() {
    // Nettoyer toute sortie avant d'initialiser le plugin
    if (ob_get_level()) {
        $output = ob_get_clean();
        // Filtrer les messages d'erreur de la sortie
        $output = preg_replace('/^(Deprecated:|Warning:).*$/m', '', $output);
        if (!headers_sent() && !empty(trim($output))) {
            echo $output;
        }
        ob_start();
    }
    
    if (class_exists('MLF_Plugin')) {
        $plugin = new MLF_Plugin();
        $plugin->run();
    }
}

// Run earlier to ensure admin hooks are registered in time
add_action('plugins_loaded', 'run_mlf_plugin', 5);

// Hook pour nettoyer la sortie admin
add_action('admin_init', function() {
    if (ob_get_level()) {
        $output = ob_get_clean();
        $output = preg_replace('/^(Deprecated:|Warning:).*$/m', '', $output);
        if (!headers_sent() && !empty(trim($output))) {
            echo $output;
        }
        ob_start();
    }
}, 1);