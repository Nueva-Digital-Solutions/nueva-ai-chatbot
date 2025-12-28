<?php

class Nueva_Chatbot_API
{

    private $api_key;
    private $model;

    public function __construct()
    {
        $options = get_option('nueva_chat_options');
        $this->api_key = isset($options['general']['api_key']) ? $options['general']['api_key'] : '';

        $selected_model = isset($options['general']['model']) ? $options['general']['model'] : 'gemini-2.5-flash';
        if ($selected_model === 'custom' && !empty($options['general']['model_custom'])) {
            $this->model = $options['general']['model_custom'];
        } else {
            $this->model = $selected_model;
        }
    }

    public function register_routes()
    {
        add_action('rest_api_init', function () {
            register_rest_route('nueva-ai/v1', '/chat', array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_chat_request'),
                'permission_callback' => '__return_true', // Validation done via Nonce manually if needed or open
            ));
        });

        // AJAX Fallback
        add_action('wp_ajax_nueva_chat_message', array($this, 'handle_ajax_request'));
        add_action('wp_ajax_nopriv_nueva_chat_message', array($this, 'handle_ajax_request'));

        add_action('wp_ajax_nueva_end_chat', array($this, 'handle_end_chat_request'));
        add_action('wp_ajax_nopriv_nueva_end_chat', array($this, 'handle_end_chat_request'));
    }

    public function handle_end_chat_request()
    {
        $session_id = sanitize_text_field($_POST['session_id']);

        // 1. Send Transcript
        $email_sent = $this->send_transcript_email($session_id);

        // 2. Clear History (Optional, but good for privacy if requested, but let's keep it for records)
        // For now, we just reply success.

        if ($email_sent) {
            wp_send_json_success('Chat ended. Transcript sent to admin.');
        } else {
            wp_send_json_success('Chat ended. Transcript could not be sent.');
        }
    }

    public function handle_ajax_request()
    {
        check_ajax_referer('nueva_chat_nonce', 'nonce');

        $message = sanitize_text_field($_POST['message']);
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';

        // Save User Message
        if ($session_id) {
            $this->save_message($session_id, 'user', $message);
        }

        $response = $this->query_gemini($message);

        // Save Bot Message
        if ($session_id) {
            $this->save_message($session_id, 'bot', $response);
        }

        wp_send_json_success(array('reply' => $response));
    }

    public function handle_chat_request($request)
    {
        $params = $request->get_json_params();
        $message = sanitize_text_field($params['message']);
        // REST API might need session_id passed in body
        $session_id = isset($params['session_id']) ? sanitize_text_field($params['session_id']) : '';

        if ($session_id) {
            $this->save_message($session_id, 'user', $message);
        }

        $response = $this->query_gemini($message);

        if ($session_id) {
            $this->save_message($session_id, 'bot', $response);
        }

        return new WP_REST_Response(array('reply' => $response), 200);
    }

    private function query_gemini($user_message)
    {
        if (empty($this->api_key)) {
            return "Error: API Key is missing. Please contact the administrator.";
        }

        // Get Options for Persona
        $options = get_option('nueva_chat_options');
        $agent_name = isset($options['general']['agent_name']) ? $options['general']['agent_name'] : 'Nueva Agent';
        $tone = isset($options['behavior']['tone']) ? $options['behavior']['tone'] : 'professional';

        // 1. Context Retrieval (KB)
        $context = $this->get_kb_context($user_message);

        // 2. Construct Prompt with Persona
        $system_prompt = "You are $agent_name. Your tone is $tone. Use the following context to answer the user's question. If the answer is not in the context, use your general knowledge but be polite. \n\nContext:\n" . $context;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->api_key}";

        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $system_prompt . "\n\nUser: " . $user_message]
                    ]
                ]
            ]
        ];

        $args = array(
            'body' => json_encode($body),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 45,
            'method' => 'POST',
            'data_format' => 'body',
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return "I'm having trouble connecting right now. Please try again later.";
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            // Log error
            error_log("Gemini API Error: " . print_r($data, true));
            // DEBUG: Return specific error to user
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown Error';
            return "API Error ($code): " . $error_msg;
        }

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }

        return "I didn't understand that. Could you rephrase?";
    }

    private function get_kb_context($query)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_knowledge_base';

        // Simple search for now (LIKE query)
        // In a real vector DB we'd do semantic search
        $wild = '%' . $wpdb->esc_like($query) . '%';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT content FROM $table_name WHERE content LIKE %s LIMIT 3",
            $wild
        ));

        $context = "";
        if ($results) {
            foreach ($results as $row) {
                $context .= $row->content . "\n---\n";
            }
        }
        return $context;
    }

    private function get_client_ip()
    {
        $ip = 'Unknown';
        if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Can be multiple IPs, first is original
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return sanitize_text_field($ip);
    }

    private function save_message($session_id, $sender, $message)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_chat_history';

        $meta_data = array();
        if ($sender === 'user') {
            $meta_data['ip'] = $this->get_client_ip();
        }

        $wpdb->insert(
            $table_name,
            array(
                'session_id' => $session_id,
                'sender' => $sender,
                'message' => $message,
                'timestamp' => current_time('mysql'),
                'meta_data' => !empty($meta_data) ? json_encode($meta_data) : ''
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }

    private function send_transcript_email($session_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_chat_history';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s ORDER BY timestamp ASC",
            $session_id
        ));

        if (empty($results)) {
            return false;
        }

        $chat_content = "<h3>Chat Transcript</h3>";
        $chat_content .= "<p><strong>Session ID:</strong> $session_id</p><ul>";

        foreach ($results as $row) {
            $sender = ucfirst($row->sender);
            $msg = nl2br(esc_html($row->message));
            $time = $row->timestamp;
            $chat_content .= "<li><strong>[$time] $sender:</strong> $msg</li>";
        }
        $chat_content .= "</ul>";

        $to = get_option('admin_email');
        $site_name = get_bloginfo('name');
        $subject = "[$site_name] Chat Transcript - Session $session_id";

        // Simplified headers for better deliverability
        // We let WordPress handle the 'From' address (usually wordpress@domain.com)
        // to avoid SPF/DKIM rejections.
        $headers = array('Content-Type: text/html; charset=UTF-8');

        $sent = wp_mail($to, $subject, $chat_content, $headers); // Return value is bool

        if (!$sent) {
            error_log("Nueva Chatbot: Failed to send transcript email for session $session_id to $to");
        }

        return $sent;
    }
}
