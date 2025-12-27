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
        $this->options = get_option('nueva_chat_options');
    }

    public function enqueue_styles()
    {
        if (!$this->should_render())
            return;

        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/nueva-ai-chatbot-public.css', array(), $this->version, 'all');

        // Dynamic CSS
        $appearance = $this->options['appearance'];
        $css = "
            :root {
                --nueva-primary: " . esc_attr($appearance['primary_color']) . ";
                --nueva-secondary: " . esc_attr($appearance['secondary_color']) . ";
                --nueva-font: '" . esc_attr($appearance['font_family']) . "', sans-serif;
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

        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/nueva-chat-widget.js', array('jquery'), $this->version, false);

        wp_localize_script($this->plugin_name, 'nueva_chat_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nueva_chat_nonce'),
            'agent_name' => esc_js($this->options['general']['agent_name']),
            'profile_image' => esc_url($this->options['appearance']['profile_image'])
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

        // 2. Include Logic (if not empty, strictly check)
        if (!empty($visibility['include_pages'])) {
            if (!in_array($current_id, $visibility['include_pages'])) {
                return false;
            }
        }

        return true;
    }

    public function render_chat_widget()
    {
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
                        Hello! How can I help you today?
                    </div>
                </div>
                <div class="nueva-chat-footer">
                    <input type="text" id="nueva-chat-input" placeholder="Type a message...">
                    <button id="nueva-chat-send">Send</button>
                    <!-- Branding: Critical Requirement -->
                    <div class="nueva-powered-by">
                        <a href="https://nuevadigital.co.in" target="_blank" id="nueva-branding-link">Powered by Nueva
                            Digital</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

}
