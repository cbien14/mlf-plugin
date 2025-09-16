<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Drop plugin custom tables
$tables = array(
    $wpdb->prefix . 'mlf_custom_form_responses',
    $wpdb->prefix . 'mlf_custom_forms',
    $wpdb->prefix . 'mlf_character_sheets',
    $wpdb->prefix . 'mlf_player_registrations',
    $wpdb->prefix . 'mlf_game_sessions'
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Delete plugin options
$options = array(
    'mlf_enable_email_notifications',
    'mlf_default_session_duration',
    'mlf_max_players_default',
    'mlf_require_registration_confirmation',
    'mlf_allow_waitlist',
    'mlf_auto_confirm_registrations',
    'mlf_email_reminder_hours',
    'mlf_default_location',
    'mlf_contact_email',
    'mlf_database_version',
    'mlf_user_account_page_id'
);

foreach ($options as $opt) {
    delete_option($opt);
}

// Optionally, delete transients prefixed with mlf_
// Note: Only safe on MySQL; adjust for your environment if needed
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mlf_%' OR option_name LIKE '_transient_timeout_mlf_%'");
?>