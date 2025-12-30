<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @package    Nueva_Chatbot
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$options = get_option('nueva_chat_options');
// Check if user enabled "Delete Data" in settings
$delete_data = isset($options['general']['delete_data']) ? (bool) $options['general']['delete_data'] : false;

if ($delete_data) {
    global $wpdb;

    // Array of table names to drop
    $tables = array(
        $wpdb->prefix . 'bua_knowledge_base',
        $wpdb->prefix . 'bua_business_profile',
        $wpdb->prefix . 'bua_chat_flows',
        $wpdb->prefix . 'bua_leads',
        $wpdb->prefix . 'bua_chat_history',
        $wpdb->prefix . 'bua_chat_feedback' // Make sure to delete the feedback table too
    );

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    // Delete Options
    delete_option('nueva_chat_options');
    delete_option('nueva_admin_notifications');
    // Also delete version option if it exists (usually defined in main class, but standard practice)
    delete_option('nueva_chatbot_version');
}
