<?php
/**
 * Character Sheet Download Handler
 * Dedicated endpoint for secure character sheet downloads
 */

// Load WordPress completely
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');

// Security checks
if (!isset($_GET['sheet_id']) || !isset($_GET['nonce'])) {
    http_response_code(400);
    die('Paramètres manquants');
}

$sheet_id = intval($_GET['sheet_id']);
$nonce = sanitize_text_field($_GET['nonce']);

// Verify nonce
if (!wp_verify_nonce($nonce, 'mlf_download_sheet_' . $sheet_id)) {
    http_response_code(403);
    die('Accès refusé - lien expiré');
}

// Get sheet information from database
global $wpdb;
$table_name = $wpdb->prefix . 'mlf_character_sheets';

$sheet = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$table_name} WHERE id = %d",
    $sheet_id
), ARRAY_A);

if (!$sheet) {
    http_response_code(404);
    die('Fiche non trouvée');
}

// Check permissions
$user_id = get_current_user_id();
if (!$user_id) {
    http_response_code(401);
    die('Vous devez être connecté pour télécharger une fiche');
}

// Check if user can download this sheet
if ($sheet['player_id'] != $user_id && !current_user_can('manage_options')) {
    http_response_code(403);
    die('Vous n\'avez pas les permissions pour télécharger cette fiche (Sheet Player ID: ' . $sheet['player_id'] . ', Your ID: ' . $user_id . ')');
}

// Check if file exists
$file_path = $sheet['file_path'];
if (!file_exists($file_path)) {
    http_response_code(404);
    die('Fichier non trouvé sur le serveur');
}

// Set headers for file download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $sheet['file_original_name'] . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private');
header('Pragma: private');
header('Expires: 0');

// Clear any previous output
if (ob_get_level()) {
    ob_end_clean();
}

// Output file
readfile($file_path);
exit;
?>
