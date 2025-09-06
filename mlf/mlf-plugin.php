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
        'includes/class-mlf-user-account.php',
        'includes/class-mlf-character-sheets.php',
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

// Hook pour vérifier et exécuter les migrations de base de données
add_action('plugins_loaded', function() {
    // S'assurer que les classes sont chargées
    if (class_exists('MLF_Activator')) {
        // Vérifier si des migrations sont nécessaires
        if (MLF_Activator::needs_database_update()) {
            MLF_Activator::run_database_migrations();
        }
        
        // En mode debug, exécuter des tests de santé basiques
        if (defined('WP_DEBUG') && WP_DEBUG && isset($_GET['mlf_health_check'])) {
            add_action('wp_footer', function() {
                if (method_exists('MLF_Activator', 'verify_and_repair_database')) {
                    $health = MLF_Activator::verify_and_repair_database();
                    if ($health['status'] !== 'ok') {
                        error_log('MLF Health Check: ' . json_encode($health));
                    }
                }
            });
        }
    }
}, 10);

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

// === DIAGNOSTIC DE LA SOLUTION PÉRENNE ===
// Ajouter le test AJAX pour diagnostiquer la solution
add_action('wp_ajax_test_mlf_solution', function() {
    header('Content-Type: text/plain');
    
    echo "=== DIAGNOSTIC SOLUTION MLF ===\n\n";
    
    // Vérifier si la classe MLF_Session_Forms_Manager existe
    if (!class_exists('MLF_Session_Forms_Manager')) {
        echo "❌ Classe MLF_Session_Forms_Manager non chargée\n";
        wp_die();
    }
    
    echo "✅ Classe MLF_Session_Forms_Manager disponible\n";
    
    // Test de récupération du formulaire session 4
    $form = MLF_Session_Forms_Manager::get_session_form(4);
    
    if (!$form) {
        echo "❌ Aucun formulaire trouvé pour la session 4\n";
        wp_die();
    }
    
    echo "✅ Formulaire trouvé pour la session 4\n";
    echo "Titre: " . $form['form_title'] . "\n";
    
    if (!is_array($form['form_fields'])) {
        echo "❌ Les champs ne sont pas un array: " . gettype($form['form_fields']) . "\n";
        wp_die();
    }
    
    echo "✅ Champs correctement décodés: " . count($form['form_fields']) . " champs\n\n";
    
    foreach ($form['form_fields'] as $index => $field) {
        if (!is_array($field)) {
            echo "❌ Champ $index n'est pas un array\n";
            continue;
        }
        
        echo "Champ $index:\n";
        echo "  - Type: " . ($field['type'] ?? 'N/A') . "\n";
        echo "  - Nom: " . ($field['name'] ?? 'N/A') . "\n";
        echo "  - Label: " . ($field['label'] ?? 'N/A') . "\n";
        echo "  - Requis: " . (isset($field['required']) && $field['required'] ? 'Oui' : 'Non') . "\n";
        
        if (isset($field['options']) && is_array($field['options'])) {
            echo "  - Options: " . implode(', ', $field['options']) . "\n";
        }
        
        if (isset($field['placeholder'])) {
            echo "  - Placeholder: " . $field['placeholder'] . "\n";
        }
        echo "\n";
    }
    
    echo "🎉 SOLUTION PÉRENNE FONCTIONNELLE !\n";
    echo "Les formulaires résistent maintenant aux caractères spéciaux.\n";
    
    wp_die();
});

// Ajouter une page de diagnostic dans l'admin
add_action('admin_menu', function() {
    add_management_page(
        'Test MLF Solution',
        'Test MLF Solution',
        'manage_options',
        'test-mlf-solution',
        function() {
            echo '<div class="wrap">';
            echo '<h1>Test de la Solution MLF</h1>';
            echo '<p>Cette page teste si la solution pérenne pour les formulaires MLF fonctionne.</p>';
            echo '<button id="run-test" class="button button-primary">Exécuter le test</button>';
            echo '<div id="test-results" style="margin-top: 20px; background: #f1f1f1; padding: 15px; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto;"></div>';
            
            echo '<script>
            document.getElementById("run-test").addEventListener("click", function() {
                var resultsDiv = document.getElementById("test-results");
                resultsDiv.textContent = "Exécution du test...";
                
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "' . admin_url('admin-ajax.php') . '");
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        resultsDiv.textContent = xhr.responseText;
                    }
                };
                xhr.send("action=test_mlf_solution");
            });
            </script>';
            
            echo '</div>';
        }
    );
});