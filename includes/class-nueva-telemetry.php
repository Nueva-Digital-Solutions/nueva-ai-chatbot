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
    private $api_endpoint = 'https://resourcehub.in/wp-json/ntr/v1/collect';
    private $api_key = '562c2ae71e9f5f85b7e541ba6b80f88d';

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
        if (!current_user_can('manage_options'))
            return;

        // Check if already decided
        $optin = get_option('nueva_telemetry_optin');
        if ($optin !== false)
            return;

        $admin_email = get_option('admin_email');
        ?>
        <div class="notice notice-info is-dismissible" style="padding:15px; border-left-color: #7228e6;">
            <div style="display:flex; align-items:flex-start; gap:15px;">
                <div style="font-size:30px;">🚀</div>
                <div>
                    <h3 style="margin:0 0 10px;">Stay Updated & Improve Nueva</h3>
                    <p style="margin:0 0 15px;">Enter your email to receive <strong>feature updates, security alerts, and AI
                            tips</strong>. By subscribing, you also help us improve the plugin by sharing <span
                            style="border-bottom:1px dotted #666; cursor:help;"
                            title="We collect WP Version, Active Plugins, Theme, and aggregate usage stats (Chat counts). We NEVER collect chat contents.">anonymous
                            usage statistics</span>.</p>

                    <form method="post" action="" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <input type="email" name="nueva_telemetry_email" value="<?php echo esc_attr($admin_email); ?>"
                            placeholder="your@email.com" required style="width:250px;">
                        <input type="hidden" name="nueva_telemetry_nonce"
                            value="<?php echo wp_create_nonce('nueva_telemetry_optin'); ?>">
                        <button type="submit" name="nueva_telemetry_submit" value="allow"
                            class="button button-primary">Subscribe & Connect</button>
                        <a href="<?php echo esc_url(add_query_arg('nueva_telemetry_action', 'deny')); ?>"
                            style="margin-left:10px; color:#999; text-decoration:none; font-size:12px;">No thanks</a>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_optin_actions()
    {
        // 1. Handle Form Submit (Allow)
        if (isset($_POST['nueva_telemetry_submit']) && $_POST['nueva_telemetry_submit'] === 'allow') {
            if (!isset($_POST['nueva_telemetry_nonce']) || !wp_verify_nonce($_POST['nueva_telemetry_nonce'], 'nueva_telemetry_optin')) {
                return;
            }
            if (!current_user_can('manage_options'))
                return;

            $email = sanitize_email($_POST['nueva_telemetry_email']);
            update_option('nueva_telemetry_email', $email);
            update_option('nueva_telemetry_optin', 'yes');

            // Schedule immediately
            if (!wp_next_scheduled('nueva_weekly_telemetry')) {
                wp_schedule_event(time(), 'weekly', 'nueva_weekly_telemetry');
            }

            // Trigger first send immediately (async potentially, but sync for now to ensure connectivity)
            $this->send_telemetry_data();

            wp_redirect(remove_query_arg(array('nueva_telemetry_action', 'nueva_telemetry_submit')));
            exit;
        }

        // 2. Handle Link Click (Deny)
        if (isset($_GET['nueva_telemetry_action']) && $_GET['nueva_telemetry_action'] === 'deny') {
            if (!current_user_can('manage_options'))
                return;
            update_option('nueva_telemetry_optin', 'no');
            wp_clear_scheduled_hook('nueva_weekly_telemetry');
            wp_redirect(remove_query_arg('nueva_telemetry_action'));
            exit;
        }
    }

    public function send_telemetry_data()
    {
        if (get_option('nueva_telemetry_optin') !== 'yes')
            return;

        $stats = $this->collect_stats();

        $response = wp_remote_post($this->api_endpoint, array(
            'method' => 'POST',
            'timeout' => 10,
            'blocking' => false,
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key
            ),
            'body' => json_encode($stats),
            'cookies' => array()
        ));
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

        // 2. Feedback Categories (AI) - If column exists
        $categories = array();
        // Check if 'category' column exists first to avoid errors during upgrade
        if ($wpdb->get_results("SHOW COLUMNS FROM $table_feedback LIKE 'category'")) {
            $raw_cats = $wpdb->get_results("SELECT category, COUNT(*) as count FROM $table_feedback WHERE category IS NOT NULL GROUP BY category");
            foreach ($raw_cats as $rc) {
                $categories[$rc->category] = $rc->count;
            }
        }

        $avg_rating = $wpdb->get_var("SELECT AVG(rating) FROM $table_feedback");

        // 3. Environment & Context
        $theme_data = wp_get_theme();
        $settings = get_option('nueva_chat_options');

        // Get Active Plugins (Names only)
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();
        $active_plugin_names = array();
        foreach ($all_plugins as $path => $pdata) {
            if (is_plugin_active($path)) {
                $active_plugin_names[] = $pdata['Name'];
            }
        }

        return array(
            'identity' => array(
                'email' => get_option('nueva_telemetry_email'),
                'url' => home_url(),
                'site_name' => get_bloginfo('name'),
                'site_desc' => get_bloginfo('description'),
            ),
            'environment' => array(
                'plugin_version' => $this->version,
                'wp_version' => get_bloginfo('version'),
                'php_version' => phpversion(),
                'site_lang' => get_bloginfo('language'),
                'server' => $_SERVER['SERVER_SOFTWARE'],
                'theme' => $theme_data->get('Name'),
                'active_plugins' => array_slice($active_plugin_names, 0, 50) // Cap at 50
            ),
            'settings' => array(
                'industry' => isset($settings['general']['industry']) ? $settings['general']['industry'] : 'Unknown',
                'lead_mode' => isset($settings['behavior']['lead_mode']) ? $settings['behavior']['lead_mode'] : 'chat',
            ),
            'business_profile' => isset($settings['business_info']) ? $settings['business_info'] : array(),
            'usage' => array(
                'total_messages' => (int) $msg_count,
                'total_sessions' => (int) $session_count,
                'total_leads' => (int) $leads_count,
                'avg_rating' => round((float) $avg_rating, 2),
                'ai_categories' => $categories
            ),
            'timestamp' => time()
        );
    }
}
