<?php

/**
 * The admin-specific functionality of the plugin.
 */
class Nueva_Chatbot_Admin
{

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Register AJAX (Admin side)
        add_action('wp_ajax_nueva_check_notifications', array($this, 'ajax_check_notifications'));
        add_action('wp_ajax_nueva_dismiss_notification', array($this, 'ajax_dismiss_notification'));
    }

    public function enqueue_styles()
    {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . '../admin/css/nueva-ai-chatbot-admin.css', array(), $this->version, 'all');
        // Add color picker styles
        wp_enqueue_style('wp-color-picker');
    }

    public function enqueue_scripts()
    {
        wp_enqueue_media(); // Required for Media Uploader
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . '../admin/js/nueva-ai-chatbot-admin.js', array('jquery', 'wp-color-picker'), $this->version, false);

        // Chart.js for Dashboard
        if (isset($_GET['page']) && $_GET['page'] === 'nueva-ai-dashboard') {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.0', true);
        }

        wp_localize_script($this->plugin_name, 'nueva_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nueva_admin_nonce')
        ));
    }

    public function add_plugin_admin_menu()
    {
        add_menu_page(
            'Nueva AI Chat',
            'Nueva AI Chat',
            'manage_options',
            'nueva-ai-chat',
            array($this, 'display_dashboard_page'), // Dashboard as main landing
            'dashicons-superhero',
            25
        );

        add_submenu_page(
            'nueva-ai-chat',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'nueva-ai-dashboard',
            array($this, 'display_dashboard_page')
        );

        add_submenu_page(
            'nueva-ai-chat',
            'General Settings',
            'Settings',
            'manage_options',
            'nueva-ai-chat',
            array($this, 'display_general_settings')
        );

        add_submenu_page(
            'nueva-ai-chat',
            'Feedback',
            'Feedback',
            'manage_options',
            'nueva-ai-feedback',
            array($this, 'display_feedback_page')
        );

        add_submenu_page(
            'nueva-ai-chat',
            'Knowledge Base',
            'Knowledge Base',
            'manage_options',
            'nueva-ai-kb',
            array($this, 'display_kb_page')
        );

        add_submenu_page(
            'nueva-ai-chat',
            'Flow Builder',
            'Flow Builder',
            'manage_options',
            'nueva-ai-flows',
            array($this, 'display_flows_page')
        );

        add_submenu_page(
            'nueva-ai-chat',
            'Leads & Chats',
            'Leads & Chats',
            'manage_options',
            'nueva-ai-leads',
            array($this, 'display_leads_page')
        );



        add_submenu_page(
            'nueva-ai-chat',
            'Chat History',
            'Chat History',
            'manage_options',
            'nueva-ai-history',
            array($this, 'display_history_page')
        );

        add_submenu_page(
            'nueva-ai-chat',
            'Real Time Agent',
            'Real Time Agent',
            'manage_options',
            'nueva-ai-realtime',
            array($this, 'display_realtime_page')
        );

        add_submenu_page(
            'nueva-ai-chat',
            'Premium Features',
            'Premium Features',
            'manage_options',
            'nueva-ai-premium',
            array($this, 'display_premium_page')
        );
    }

    public function display_dashboard_page()
    {
        require_once plugin_dir_path(__FILE__) . '../admin/partials/nueva-ai-chatbot-dashboard.php';
    }


    public function display_feedback_page()
    {
        require_once plugin_dir_path(__FILE__) . '../admin/partials/nueva-ai-chatbot-feedback.php';
    }

    public function display_general_settings()
    {
        // Save logic (simple POST for now)
        if (isset($_POST['nueva_save_settings']) && check_admin_referer('nueva_chat_options_verify')) {
            $this->save_settings();
        }
        require_once plugin_dir_path(__FILE__) . '../admin/partials/nueva-ai-chatbot-admin-display.php';
    }

    public function display_kb_page()
    {
        require_once plugin_dir_path(__FILE__) . '../admin/partials/nueva-ai-chatbot-kb-display.php';
    }

    public function display_flows_page()
    {
        require_once plugin_dir_path(__FILE__) . '../admin/partials/nueva-ai-chatbot-flows-display.php';
    }

    public function display_leads_page()
    {
        require_once plugin_dir_path(__FILE__) . '../admin/partials/nueva-ai-chatbot-leads-display.php';
    }

    public function display_history_page()
    {
        require_once plugin_dir_path(__FILE__) . 'class-nueva-chatbot-history.php';
        $history = new Nueva_Chatbot_History();
        $history->display_page();
    }

    public function display_realtime_page()
    {
        require_once plugin_dir_path(__FILE__) . '../admin/partials/nueva-ai-chatbot-realtime-display.php';
    }

    public function display_premium_page()
    {
        require_once plugin_dir_path(__FILE__) . '../admin/partials/nueva-ai-chatbot-premium-display.php';
    }

    public function define_admin_hooks()
    {
        // ... existing hooks ...
        add_action('wp_ajax_nueva_check_notifications', array($this, 'ajax_check_notifications'));
        add_action('wp_ajax_nueva_dismiss_notification', array($this, 'ajax_dismiss_notification'));

        // KB Scan Hooks
        add_action('wp_ajax_nueva_kb_scan_list', array($this, 'ajax_kb_scan_list'));
        add_action('wp_ajax_nueva_kb_scan_import', array($this, 'ajax_kb_scan_import'));

        // CSV Export
        add_action('admin_post_nueva_export_leads', array($this, 'export_leads_csv'));
    }

    public function ajax_check_notifications()
    {
        check_ajax_referer('nueva_admin_nonce', 'nonce');

        $notifications = get_option('nueva_admin_notifications', []);
        $unread = [];

        if (!empty($notifications)) {
            foreach ($notifications as $n) {
                if (!$n['read']) {
                    $unread[] = $n;
                }
            }
        }

        wp_send_json_success($unread);
    }

    public function ajax_dismiss_notification()
    {
        check_ajax_referer('nueva_admin_nonce', 'nonce');
        $session_id = sanitize_text_field($_POST['session_id']);

        $notifications = get_option('nueva_admin_notifications', []);
        foreach ($notifications as &$n) {
            if ($n['session_id'] === $session_id) {
                $n['read'] = true;
            }
        }
        update_option('nueva_admin_notifications', $notifications);
        wp_send_json_success();
    }

    public function export_leads_csv()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Permission Denied');
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="leads-export-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, array('ID', 'Date', 'Session ID', 'Name', 'Email', 'User Data', 'Synced'));

        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_leads';
        $leads = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

        foreach ($leads as $lead) {
            $data = json_decode($lead['user_data'], true);
            $name = $data['name'] ?? '';
            $email = $data['email'] ?? '';

            fputcsv($output, array(
                $lead['id'],
                $lead['collected_at'],
                $lead['chat_session_id'],
                $name,
                $email,
                $lead['user_data'],
                $lead['is_synced'] ? 'Yes' : 'No'
            ));
        }
        fclose($output);
        exit;
    }

    private function save_settings()
    {
        // Update Options
        $options = array(
            'general' => array(
                'api_key' => sanitize_text_field($_POST['nueva_api_key']),
                'agent_name' => sanitize_text_field($_POST['nueva_agent_name']),
                'notification_email' => sanitize_email($_POST['nueva_notification_email']),
                'industry' => sanitize_text_field($_POST['nueva_industry']), // Added Industry
                'model' => sanitize_text_field($_POST['nueva_model']),
                'model_custom' => sanitize_text_field($_POST['nueva_model_custom']),
            ),
            'business_info' => array(
                'business_name' => sanitize_text_field($_POST['nueva_business_name']),
                'legal_name' => sanitize_text_field($_POST['nueva_legal_name']),
                'contact_link' => esc_url_raw($_POST['nueva_contact_link']),
                'founding_date' => sanitize_text_field($_POST['nueva_founding_date']),
                'gst' => sanitize_text_field($_POST['nueva_gst']),
                'office_timing' => sanitize_text_field($_POST['nueva_office_timing']),
                'price_range' => sanitize_text_field($_POST['nueva_price_range']),
                // Repeaters (using helper to sanitize array of arrays)
                'locations' => isset($_POST['nueva_locations']) ? $this->sanitize_recursive($_POST['nueva_locations']) : [],
                'mobile_numbers' => isset($_POST['nueva_mobile_numbers']) ? $this->sanitize_recursive($_POST['nueva_mobile_numbers']) : [],
                'emails' => isset($_POST['nueva_emails']) ? $this->sanitize_recursive($_POST['nueva_emails']) : [],
                'social_media' => isset($_POST['nueva_social_media']) ? $this->sanitize_recursive($_POST['nueva_social_media']) : [],
                'founders' => isset($_POST['nueva_founders']) ? $this->sanitize_recursive($_POST['nueva_founders']) : [],
                'service_areas' => isset($_POST['nueva_service_areas']) ? $this->sanitize_recursive($_POST['nueva_service_areas']) : [],
            ),
            'appearance' => array(
                'primary_color' => sanitize_hex_color($_POST['nueva_primary_color']),
                'primary_gradient_start' => sanitize_hex_color($_POST['nueva_primary_gradient_start']),
                'primary_gradient_end' => sanitize_hex_color($_POST['nueva_primary_gradient_end']),
                'secondary_color' => sanitize_hex_color($_POST['nueva_secondary_color']),
                'accent_color' => sanitize_hex_color($_POST['nueva_accent_color']), // Added Accent Color
                'font_family' => sanitize_text_field($_POST['nueva_font_family']),
                'font_size' => intval($_POST['nueva_font_size']),
                'position_desktop' => sanitize_text_field($_POST['nueva_position_desktop']),
                'position_mobile' => sanitize_text_field($_POST['nueva_position_mobile']),
                'profile_image' => esc_url_raw($_POST['nueva_profile_image']),
            ),
            'behavior' => array(
                'tone' => sanitize_text_field($_POST['nueva_tone']),
                'agent_instructions' => sanitize_textarea_field($_POST['nueva_agent_instructions']),
                'lead_mode' => sanitize_text_field($_POST['nueva_lead_mode']),
                'gate_title' => sanitize_text_field($_POST['nueva_gate_title']),
                'gate_btn' => sanitize_text_field($_POST['nueva_gate_btn']),
                'lead_skip_logged_in' => isset($_POST['nueva_lead_skip_logged_in']) ? (bool) $_POST['nueva_lead_skip_logged_in'] : false,
                'allow_visits' => sanitize_text_field($_POST['nueva_allow_visits']),
                'allow_links' => sanitize_text_field($_POST['nueva_allow_links']),
                'guest_orders' => sanitize_text_field($_POST['nueva_guest_orders']),
                'kb_strictness' => sanitize_text_field($_POST['nueva_kb_strictness']),
                'lead_fields' => sanitize_textarea_field($_POST['nueva_lead_fields']),
                'initial_message' => sanitize_textarea_field($_POST['nueva_initial_message']),
                'enable_handoff' => isset($_POST['nueva_enable_handoff']) ? (bool) $_POST['nueva_enable_handoff'] : false,
                'default_lang' => sanitize_text_field($_POST['nueva_default_lang']),
                'supported_langs' => sanitize_text_field($_POST['nueva_supported_langs']),
            ),
            'visibility' => array(
                'exclude_pages' => isset($_POST['nueva_exclude_pages']) ? array_map('intval', $_POST['nueva_exclude_pages']) : [],
            )
        );
        update_option('nueva_chat_options', $options);
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
    }

    public function ajax_kb_scan_list()
    {
        check_ajax_referer('nueva_admin_nonce', 'nonce');

        $post_types = get_post_types(array('public' => true), 'names');
        unset($post_types['attachment'], $post_types['revision'], $post_types['nav_menu_item']);

        $args = [
            'post_type' => array_values($post_types),
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ];

        $query = new WP_Query($args);
        $results = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $results[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'type' => get_post_type(),
                    'date' => get_the_date('Y-m-d'),
                    'link' => get_permalink()
                ];
            }
            wp_reset_postdata();
        }

        wp_send_json_success($results);
    }

    public function ajax_kb_scan_import()
    {
        check_ajax_referer('nueva_admin_nonce', 'nonce');

        if (!isset($_POST['ids']) || !is_array($_POST['ids'])) {
            wp_send_json_error('No items selected.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_knowledge_base';
        $ids = array_map('intval', $_POST['ids']);
        $count = 0;

        foreach ($ids as $post_id) {
            $post = get_post($post_id);
            if (!$post)
                continue;

            $raw_content = $post->post_content; // Raw content first
            // Strip tags but keep some structure if possible? standard strip_tags is fine for now as per previous logic.
            $clean_text = strip_tags($raw_content);
            $clean_text = preg_replace('/\s+/', ' ', $clean_text);
            $clean_text = trim($clean_text);

            // Enhance Product Data
            if ($post->post_type == 'product' && function_exists('wc_get_product')) {
                $product = wc_get_product($post_id);
                if ($product) {
                    $price = $product->get_price();
                    $sku = $product->get_sku();
                    $stock = $product->is_in_stock() ? 'In Stock' : 'Out of Stock';
                    $cats = wc_get_product_category_list($product->get_id());
                    $clean_text = "Product: " . $post->post_title . "\nPrice: " . $price . "\nSKU: " . $sku . "\nAvailability: " . $stock . "\nCategories: " . strip_tags($cats) . "\nDescription: " . $clean_text;
                }
            } else {
                // Prepend Title for context on standard posts
                $clean_text = "Title: " . $post->post_title . "\nType: " . $post->post_type . "\nContent: " . $clean_text;
            }

            if (!empty($clean_text)) {
                // Check duplicate source
                $link = get_permalink($post_id);
                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE source_ref = %s", $link));

                if (!$exists) {
                    $wpdb->insert($table_name, [
                        'type' => 'wp_' . $post->post_type,
                        'source_ref' => $link,
                        'content' => substr($clean_text, 0, 10000), // Limit size safer
                        'created_at' => current_time('mysql')
                    ]);
                    $count++;
                }
            }
        }

        wp_send_json_success(['added' => $count]);
    }
}

