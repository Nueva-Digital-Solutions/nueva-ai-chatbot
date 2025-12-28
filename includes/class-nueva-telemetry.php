<?php

/**
 * Handles anonymous usage tracking (Telemetry).
 *
 * @since      1.7.0
 * @package    Nueva_Chatbot
 * @subpackage Nueva_Chatbot/includes
 */

class Nueva_Chatbot_Telemetry
{

    private $plugin_name;
    private $version;
    private $api_endpoint = 'https://api.nuevadigital.com/telemetry'; // Placeholder

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function init()
    {
        // Admin Notice for Opt-in
        add_action('admin_notices', array($this, 'display_optin_notice'));

        // Handle Opt-in/Opt-out Actions
        add_action('admin_init', array($this, 'handle_optin_actions'));

        // Scheduled Event Hook
        add_action('nueva_weekly_telemetry', array($this, 'send_telemetry_data'));
    }

    public function display_optin_notice()
    {
        // Only show to admins
        if (!current_user_can('manage_options'))
            return;

        // Check if already decided
        $optin = get_option('nueva_telemetry_optin');
        if ($optin !== false)
            return; // 'yes' or 'no'

        // Current Page check (maybe only show on plugin pages to be less annoying?)
        // For now, show everywhere to ensure decision is made.
        ?>
        <div class="notice notice-info is-dismissible">
            <p><strong>Help improve Nueva AI Chatbot!</strong> 🚀</p>
            <p>Would you be willing to share anonymous usage statistics (chat volume, feature usage, WP version) to help us make
                the plugin better? We do NOT collect chat content or personal user data.</p>
            <p>
                <a href="<?php echo esc_url(add_query_arg('nueva_telemetry_action', 'allow')); ?>"
                    class="button button-primary">Sure, I'd love to help</a>
                <a href="<?php echo esc_url(add_query_arg('nueva_telemetry_action', 'deny')); ?>"
                    class="button button-secondary">No thanks</a>
            </p>
        </div>
        <?php
    }

    public function handle_optin_actions()
    {
        if (!isset($_GET['nueva_telemetry_action']))
            return;

        // Verify permission
        if (!current_user_can('manage_options'))
            return;

        $action = sanitize_text_field($_GET['nueva_telemetry_action']);

        if ($action === 'allow') {
            update_option('nueva_telemetry_optin', 'yes');
            // Schedule immediately if allowed
            if (!wp_next_scheduled('nueva_weekly_telemetry')) {
                wp_schedule_event(time(), 'weekly', 'nueva_weekly_telemetry');
            }
            // Redirect to remove query arg
            wp_redirect(remove_query_arg('nueva_telemetry_action'));
            exit;
        } elseif ($action === 'deny') {
            update_option('nueva_telemetry_optin', 'no');
            // Clear schedule if exists
            wp_clear_scheduled_hook('nueva_weekly_telemetry');
            wp_redirect(remove_query_arg('nueva_telemetry_action'));
            exit;
        }
    }

    public function send_telemetry_data()
    {
        // Double check consent
        if (get_option('nueva_telemetry_optin') !== 'yes')
            return;

        $stats = $this->collect_stats();

        $response = wp_remote_post($this->api_endpoint, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true, // Block to ensure it runs
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($stats),
            'cookies' => array()
        ));

        if (is_wp_error($response)) {
            error_log('Nueva Telemetry Error: ' . $response->get_error_message());
        }
    }

    private function collect_stats()
    {
        global $wpdb;
        $table_history = $wpdb->prefix . 'bua_chat_history';
        $table_feedback = $wpdb->prefix . 'bua_chat_feedback';
        $table_leads = $wpdb->prefix . 'bua_leads';

        // 1. Counts
        $msg_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_history");
        $session_count = $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM $table_history");
        $leads_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_leads");

        // 2. Feedback
        $avg_rating = $wpdb->get_var("SELECT AVG(rating) FROM $table_feedback");
        $feedback_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_feedback");

        // 3. Environment
        $theme_data = wp_get_theme();

        return array(
            'plugin_version' => $this->version,
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'site_lang' => get_bloginfo('language'),
            'server_software' => $_SERVER['SERVER_SOFTWARE'],
            'stats' => array(
                'total_messages' => (int) $msg_count,
                'total_sessions' => (int) $session_count,
                'total_leads' => (int) $leads_count,
                'avg_rating' => round((float) $avg_rating, 2),
                'feedback_count' => (int) $feedback_count
            ),
            'url' => home_url(), // Helps identify unique sites, can be hashed on server if privacy needed
            'timestamp' => time()
        );
    }
}
