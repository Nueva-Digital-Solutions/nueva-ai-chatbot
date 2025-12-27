<?php

class Nueva_Chatbot_API
{

    private $api_key;
    private $model;

    public function __construct()
    {
        $options = get_option('nueva_chat_options');
        $this->api_key = isset($options['general']['api_key']) ? $options['general']['api_key'] : '';
        $this->model = isset($options['general']['model']) ? $options['general']['model'] : 'gemini-1.5-pro';
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
    }

    public function handle_ajax_request()
    {
        check_ajax_referer('nueva_chat_nonce', 'nonce');

        $message = sanitize_text_field($_POST['message']);

        // Log Lead/Chat if session is new (logic to be added)

        $response = $this->query_gemini($message);

        wp_send_json_success(array('reply' => $response));
    }

    public function handle_chat_request($request)
    {
        $params = $request->get_json_params();
        $message = sanitize_text_field($params['message']);

        $response = $this->query_gemini($message);

        return new WP_REST_Response(array('reply' => $response), 200);
    }

    private function query_gemini($user_message)
    {
        if (empty($this->api_key)) {
            return "Error: API Key is missing. Please contact the administrator.";
        }

        // 1. Context Retrieval (KB)
        $context = $this->get_kb_context($user_message);

        // 2. Construct Prompt
        $system_prompt = "You are a helpful AI assistant for this website. Use the following context to answer the user's question. If the answer is not in the context, use your general knowledge but be polite. \n\nContext:\n" . $context;

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
            return "I encountered an error processing your request.";
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
}
