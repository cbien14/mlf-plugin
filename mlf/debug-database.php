<?php
/**
 * Debug script for MLF Plugin database
 * 
 * This script helps debug database issues
 */

// Activation du plugin
if (function_exists('MLF_Activator::activate')) {
    MLF_Activator::activate();
    echo "Plugin activated.\n";
}

// Vérification des tables
global $wpdb;

$sessions_table = $wpdb->prefix . 'mlf_game_sessions';
$registrations_table = $wpdb->prefix . 'mlf_player_registrations';

// Test de création de session
$test_session = array(
    'session_name' => 'Test Session Debug',
    'game_type' => 'jdr',
    'session_date' => date('Y-m-d'),
    'session_time' => '20:00:00',
    'max_players' => 6
);

echo "Tentative de création d'une session de test...\n";
$session_id = MLF_Database_Manager::create_game_session($test_session);

if ($session_id) {
    echo "Session créée avec succès ! ID: " . $session_id . "\n";
    
    // Vérifier que la session existe dans la base
    $session = MLF_Database_Manager::get_game_session($session_id);
    if ($session) {
        echo "Session trouvée dans la base de données :\n";
        print_r($session);
    } else {
        echo "ERREUR: Session non trouvée dans la base de données\n";
    }
} else {
    echo "ERREUR: Échec de la création de la session\n";
    if ($wpdb->last_error) {
        echo "Erreur MySQL: " . $wpdb->last_error . "\n";
    }
}

// Vérifier la structure des tables
echo "\nStructure de la table sessions:\n";
$columns = $wpdb->get_results("DESCRIBE $sessions_table");
foreach ($columns as $column) {
    echo $column->Field . " - " . $column->Type . "\n";
}

echo "\nStructure de la table registrations:\n";
$columns = $wpdb->get_results("DESCRIBE $registrations_table");
foreach ($columns as $column) {
    echo $column->Field . " - " . $column->Type . "\n";
}

// Compter les sessions existantes
$count = $wpdb->get_var("SELECT COUNT(*) FROM $sessions_table");
echo "\nNombre total de sessions dans la base: " . $count . "\n";
