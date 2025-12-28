<?php

/**
 * The public-facing functionality of the plugin.
 */
class Nueva_Chatbot_Public
{

    private $plugin_name;
    private $version;
    private $options;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        $defaults = [
            'general' => ['api_key' => '', 'agent_name' => 'Nueva Agent', 'model' => 'gemini-1.5-pro'],
            'appearance' => ['primary_color' => '#0073aa', 'primary_gradient_start' => '#0073aa', 'primary_gradient_end' => '#005a87', 'secondary_color' => '#ffffff', 'font_family' => 'Roboto', 'font_size' => '16', 'position_desktop' => 'right', 'position_mobile' => 'right', 'profile_image' => ''],
            'behavior' => ['tone' => 'professional', 'default_lang' => 'en', 'supported_langs' => 'en'],
            'visibility' => ['include_pages' => [], 'exclude_pages' => []]
        ];

        $options = get_option('nueva_chat_options', []);

        // Deep merge logic simplified for this structure
        $this->options = $defaults;
        if (is_array($options)) {
            foreach ($defaults as $group => $settings) {
                if (isset($options[$group]) && is_array($options[$group])) {
                    $this->options[$group] = array_merge($settings, $options[$group]);
                }
            }
        }
    }

    public function enqueue_styles()
    {
        if (!$this->should_render())
            return;

        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/nueva-ai-chatbot-public.css', array('dashicons'), time(), 'all');

        // Dynamic CSS
        $appearance = $this->options['appearance'];
        $accent_color = isset($appearance['accent_color']) ? $appearance['accent_color'] : $appearance['primary_color']; // Default to primary if not set

        // Font URL Construction
        $font_family = $appearance['font_family'];
        $font_url = 'https://fonts.googleapis.com/css2?family=' . urlencode($font_family) . ':wght@400;500;700&display=swap';
        wp_enqueue_style('nueva-google-fonts', $font_url, array(), null);

        $css = "
            :root {
                --nueva-primary: " . esc_attr($appearance['primary_color']) . ";
                --nueva-secondary: " . esc_attr($appearance['secondary_color']) . ";
                --nueva-accent: " . esc_attr($accent_color) . ";
                --nueva-font: '" . esc_attr($font_family) . "', sans-serif;
                --nueva-font-size: " . intval($appearance['font_size']) . "px;
            }
            .nueva-chat-widget {
                " . ($appearance['position_desktop'] == 'left' ? 'left: 20px; right: auto;' : 'right: 20px; left: auto;') . "
            }
            @media (max-width: 600px) {
                .nueva-chat-widget {
                    " . ($appearance['position_mobile'] == 'left' ? 'left: 10px; right: auto;' : 'right: 10px; left: auto;') . "
                }
            }
        ";
        wp_add_inline_style($this->plugin_name, $css);
    }

    public function enqueue_scripts()
    {
        if (!$this->should_render())
            return;

        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/nueva-chat-widget.js', array('jquery'), time(), false);

        wp_localize_script($this->plugin_name, 'nueva_chat_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nueva_chat_nonce'),
            'is_logged_in' => is_user_logged_in(),
            'agent_name' => esc_js($this->options['general']['agent_name']),
            'profile_image' => esc_url($this->options['appearance']['profile_image']),
            'primary_col' => esc_attr($this->options['appearance']['primary_color']), // for generic use
            'secondary_col' => esc_attr($this->options['appearance']['secondary_color']),
            'accent_col' => esc_attr(isset($this->options['appearance']['accent_color']) ? $this->options['appearance']['accent_color'] : $this->options['appearance']['primary_color']),
            'initial_message' => esc_js(isset($this->options['behavior']['initial_message']) && !empty($this->options['behavior']['initial_message']) ? $this->options['behavior']['initial_message'] : 'Hello! How can I help you today?')
        ));

        add_action('wp_footer', array($this, 'render_chat_widget'));
    }

    private function should_render()
    {
        $visibility = $this->options['visibility'];
        $current_id = get_queried_object_id();

        // 1. Exclude Logic
        if (!empty($visibility['exclude_pages']) && in_array($current_id, $visibility['exclude_pages'])) {
            return false;
        }

        // Default: Show everywhere
        return true;
    }

    public function render_chat_widget()
    {
        $initial_msg = isset($this->options['behavior']['initial_message']) && !empty($this->options['behavior']['initial_message']) ? $this->options['behavior']['initial_message'] : 'Hello! How can I help you today?';
        ?>
        <div class="nueva-chat-widget closed">
            <div class="nueva-chat-button">
                <span class="dashicons dashicons-format-chat"></span>
            </div>
            <div class="nueva-chat-window">
                <div class="nueva-chat-header">
                    <div class="agent-info">
                        <?php if ($this->options['appearance']['profile_image']): ?>
                            <img src="<?php echo esc_url($this->options['appearance']['profile_image']); ?>" class="agent-avatar">
                        <?php endif; ?>
                        <span><?php echo esc_html($this->options['general']['agent_name']); ?></span>
                    </div>
                    <button class="close-chat">&times;</button>
                </div>
                <div class="nueva-chat-body" id="nueva-chat-body">
                    <div class="message bot">
                        <?php echo esc_html($initial_msg); ?>
                    </div>
                </div>

                <!-- Lead Gate Removed (Conversational Mode) -->
                <div class="nueva-chat-footer">
                    <div class="nueva-footer-controls">
                        <input type="text" id="nueva-chat-input" placeholder="Type a message...">
                        <button id="nueva-chat-send" title="Send Message">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16"
                                height="16">
                                <path
                                    d="M1.946 9.315c-.522-.174-.527-.455.01-.634l19.087-6.362c.529-.176.832.12.684.638l-5.454 19.086c-.15.529-.455.547-.679.045L12 14l6-8-8 6-8.054-2.685z">
                                </path>
                            </svg>
                        </button>
                        <button id="nueva-chat-end" title="End Chat & Email Transcript" class="nueva-end-chat-btn">
                            End
                        </button>
                    </div>
                    <!-- Branding: Critical Requirement -->
                    <div class="nueva-powered-by">
                        <a href="https://nuevadigital.co.in" target="_blank" id="nueva-branding-link">Powered by Nueva
                            Digital</a>
                    </div>
                </div>
                <!-- Custom Toast Confirmation -->
                <div id="nueva-toast-confirm" class="nueva-toast" style="display:none;">
                    <p>End chat and email transcript?</p>
                    <div class="nueva-toast-actions">
                        <button id="nueva-toast-yes" class="nueva-btn-primary">Yes, End</button>
                        <button id="nueva-toast-no" class="nueva-btn-secondary">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

}
