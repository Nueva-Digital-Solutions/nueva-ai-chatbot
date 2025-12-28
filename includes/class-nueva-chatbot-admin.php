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
    }

    public function enqueue_styles()
    {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . '../admin/css/nueva-ai-chatbot-admin.css', array(), $this->version, 'all');
        // Add color picker styles
        wp_enqueue_style('wp-color-picker');
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . '../admin/js/nueva-ai-chatbot-admin.js', array('jquery', 'wp-color-picker', 'media-upload'), $this->version, false);
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
            array($this, 'display_general_settings'), // Main page callback
            'dashicons-superhero',
            25
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

    private function save_settings()
    {
        // Update Options
        $options = array(
            'general' => array(
                'api_key' => sanitize_text_field($_POST['nueva_api_key']),
                'agent_name' => sanitize_text_field($_POST['nueva_agent_name']),
                'model' => sanitize_text_field($_POST['nueva_model']),
                'model_custom' => sanitize_text_field($_POST['nueva_model_custom']),
            ),
            'appearance' => array(
                'primary_color' => sanitize_hex_color($_POST['nueva_primary_color']),
                'secondary_color' => sanitize_hex_color($_POST['nueva_secondary_color']),
                'font_family' => sanitize_text_field($_POST['nueva_font_family']),
                'font_size' => intval($_POST['nueva_font_size']),
                'position_desktop' => sanitize_text_field($_POST['nueva_position_desktop']),
                'position_mobile' => sanitize_text_field($_POST['nueva_position_mobile']),
                'profile_image' => esc_url_raw($_POST['nueva_profile_image']),
            ),
            'behavior' => array(
                'tone' => sanitize_text_field($_POST['nueva_tone']),
                'initial_message' => sanitize_textarea_field($_POST['nueva_initial_message']),
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
}
