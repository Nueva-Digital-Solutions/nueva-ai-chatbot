<?php

/**
 * The core plugin class.
 */
class Nueva_Chatbot
{

    protected $loader;
    protected $plugin_name;
    protected $version = '1.7.0';
    protected $api; // Fix undefined property

    public function __construct()
    {
        if (defined('NUEVA_AI_CHATBOT_VERSION')) {
            $this->version = NUEVA_AI_CHATBOT_VERSION;
        } else {
            $this->version = '1.5.0';
        }
        $this->plugin_name = 'nueva-ai-chatbot';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies()
    {
        require_once plugin_dir_path(__FILE__) . 'class-nueva-chatbot-loader.php';
        require_once plugin_dir_path(__FILE__) . 'class-nueva-chatbot-i18n.php';
        require_once plugin_dir_path(__FILE__) . 'class-nueva-chatbot-admin.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-nueva-chatbot-public.php';
        require_once plugin_dir_path(__FILE__) . 'api/class-nueva-chatbot-api.php';
        require_once plugin_dir_path(__FILE__) . 'class-nueva-github-updater.php'; // Load Updater
        require_once plugin_dir_path(__FILE__) . 'class-nueva-telemetry.php'; // Load Telemetry

        $this->loader = new Nueva_Chatbot_Loader();
        $this->api = new Nueva_Chatbot_API();
        $this->api->register_routes();

        // Initialize Telemetry
        $telemetry = new Nueva_Chatbot_Telemetry($this->plugin_name, $this->version);
        $telemetry->init();

        // Initialize GitHub Updater
        // Params: Plugin File, User, Repo
        if (is_admin()) {
            new Nueva_Chatbot_Updater(
                plugin_dir_path(dirname(__FILE__)) . 'nueva-ai-chatbot.php',
                'Nueva-Digital-Solutions',
                'nueva-ai-chatbot'
            );
        }
    }

    private function set_locale()
    {
        $plugin_i18n = new Nueva_Chatbot_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks()
    {
        $plugin_admin = new Nueva_Chatbot_Admin($this->plugin_name, $this->version);

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
    }

    private function define_public_hooks()
    {
        $plugin_public = new Nueva_Chatbot_Public($this->plugin_name, $this->version);

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    public function run()
    {
        $this->loader->run();
    }

    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    public function get_version()
    {
        return $this->version;
    }

    public function get_loader()
    {
        return $this->loader;
    }
}
