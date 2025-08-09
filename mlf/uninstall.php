<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Clean up options and data created by the plugin
delete_option('mlf_plugin_option_name');
delete_option('mlf_plugin_another_option_name');

// If you have custom database tables, you can drop them here
global $wpdb;
$table_name = $wpdb->prefix . 'mlf_custom_table';
$wpdb->query("DROP TABLE IF EXISTS $table_name");
?>