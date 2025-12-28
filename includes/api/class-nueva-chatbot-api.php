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

        // Capture Leads
        if ($session_id) {
            $this->capture_lead($session_id, $message);
            $this->save_message($session_id, 'user', $message);
        }

        $response = $this->query_gemini($message, $session_id);

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
            $this->capture_lead($session_id, $message);
            $this->save_message($session_id, 'user', $message);
        }

        $response = $this->query_gemini($message, $session_id);

        if ($session_id) {
            $this->save_message($session_id, 'bot', $response);
        }

        return new WP_REST_Response(array('reply' => $response), 200);
    }

    private function query_gemini($user_message, $session_id)
    {
        if (empty($this->api_key)) {
            return "Error: API Key is missing. Please contact the administrator.";
        }

        // Get Options
        $options = get_option('nueva_chat_options');
        $agent_name = isset($options['general']['agent_name']) ? $options['general']['agent_name'] : 'Nueva Agent';
        $tone = isset($options['behavior']['tone']) ? $options['behavior']['tone'] : 'professional';
        $instructions = isset($options['behavior']['agent_instructions']) ? $options['behavior']['agent_instructions'] : '';
        $enable_handoff = isset($options['behavior']['enable_handoff']) ? $options['behavior']['enable_handoff'] : false;
        $lead_fields = isset($options['behavior']['lead_fields']) ? $options['behavior']['lead_fields'] : 'email or phone number';

        // --- HANDOFF LOGIC START ---
        if ($enable_handoff && $this->check_handoff_request($user_message)) {
            // Check if we have lead info
            if (!$this->has_lead_info($session_id)) {
                return "I can connect you with a human agent. First, please provide your $lead_fields so they can contact you.";
            } else {
                // Trigger Notification
                $this->add_admin_notification($session_id, $user_message);
                return "I have notified a human agent. They will join shortly or contact you via the details provided. Is there anything else I can help with in the meantime?";
            }
        }
        // --- HANDOFF LOGIC END ---

        // 1. Context Retrieval (KB)
        $kb_context = $this->get_kb_context($user_message);

        // 2. Dynamic Context (User Data / WooCommerce)
        $user_context = $this->get_dynamic_context();

        // 3. Conversation History (Memory)
        $history_context = $this->get_conversation_history($session_id);

        $full_context = $kb_context . "\n" . $user_context . "\n" . $history_context;

        // 3. Construct Prompt with Persona & Strictness
        $system_prompt = "You are $agent_name. Your tone is $tone. 
You are an AI support agent for this specific website. 
STRICT INSTRUCTION: Answer the user's question using ONLY the context provided below.
IMPORTANT: Never mention the words 'context', 'knowledge base', 'internal data', or 'provided text'. Answer naturally as if you simply know the information.
If the answer is not in the context, politely deny knowing it (e.g., 'I don't have that information directly...') or offer to connect with human support.
Do NOT make up information.

CUSTOM INSTRUCTIONS: $instructions

Context:
$full_context";

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
            error_log("Gemini API Error: " . print_r($data, true));
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown Error';
            return "API Error ($code): " . $error_msg;
        }

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }

        return "I didn't understand that. Could you rephrase?";
    }

    private function get_conversation_history($session_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_chat_history';

        // Get last 6 messages (approx 3 turns)
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT sender, message FROM $table_name WHERE session_id = %s ORDER BY timestamp DESC LIMIT 6",
            $session_id
        ));

        if (!$results)
            return "";

        $history = "--- Conversation History ---\n";
        // Results are DESC, so reverse them to chronological order
        $results = array_reverse($results);
        foreach ($results as $row) {
            $role = ($row->sender === 'user') ? 'User' : 'Agent';
            $history .= "$role: " . mb_substr($row->message, 0, 200) . "\n"; // Truncate long msgs
        }
        $history .= "--- End History ---\n";

        return $history;
    }

    // Helper: Detect Handoff Intent
    private function check_handoff_request($message)
    {
        $keywords = ['human', 'agent', 'support', 'person', 'talk to someone', 'real person'];
        foreach ($keywords as $word) {
            if (stripos($message, $word) !== false) {
                return true;
            }
        }
        return false;
    }

    private function has_lead_info($session_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_leads';
        $lead = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_name WHERE chat_session_id = %s", $session_id));
        return !empty($lead);
    }

    private function add_admin_notification($session_id, $message)
    {
        $notifications = get_option('nueva_admin_notifications', []);
        if (!is_array($notifications))
            $notifications = [];
        $notifications[] = [
            'type' => 'handoff',
            'session_id' => $session_id,
            'message' => $message,
            'time' => time(),
            'read' => false
        ];
        if (count($notifications) > 20)
            array_shift($notifications);
        update_option('nueva_admin_notifications', $notifications);
    }

    private function capture_lead($session_id, $message)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_leads';
        preg_match('/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i', $message, $email_matches);
        preg_match('/\b\d{10,15}\b/', $message, $phone_matches);

        $data = [];
        if (!empty($email_matches[0]))
            $data['email'] = $email_matches[0];
        if (!empty($phone_matches[0]))
            $data['phone'] = $phone_matches[0];

        if (!empty($data)) {
            $existing = $wpdb->get_row($wpdb->prepare("SELECT user_data FROM $table_name WHERE chat_session_id = %s", $session_id));
            if ($existing) {
                $existing_data = json_decode($existing->user_data, true);
                if (!is_array($existing_data))
                    $existing_data = [];
                $new_data = array_merge($existing_data, $data);
                $wpdb->update($table_name, array('user_data' => json_encode($new_data)), array('chat_session_id' => $session_id));
            } else {
                $wpdb->insert($table_name, array('chat_session_id' => $session_id, 'user_data' => json_encode($data), 'collected_at' => current_time('mysql'), 'is_synced' => 0));
            }
        }
    }

    private function get_dynamic_context()
    {
        $context = "";
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $context .= "Current User Name: " . $user->display_name . "\n";
            $context .= "Current User Email: " . $user->user_email . "\n";
            if (class_exists('WooCommerce')) {
                $orders = wc_get_orders(array('customer' => $user->ID, 'limit' => 3, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects'));
                if ($orders) {
                    $context .= "User's Recent Orders:\n";
                    foreach ($orders as $order) {
                        $context .= "- Order #" . $order->get_id() . " (" . $order->get_status() . "): " . wc_price($order->get_total()) . " - Date: " . $order->get_date_created()->format('Y-m-d') . "\n";
                        foreach ($order->get_items() as $item_id => $item) {
                            $context .= "  * " . $item->get_name() . " x " . $item->get_quantity() . "\n";
                        }
                    }
                } else {
                    $context .= "User has no recent orders.\n";
                }
            }
        } else {
            $context .= "User is not logged in (Guest).\n";
        }
        return $context;
    }

    private function get_kb_context($query)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_knowledge_base';
        $wild = '%' . $wpdb->esc_like($query) . '%';
        $results = $wpdb->get_results($wpdb->prepare("SELECT content FROM $table_name WHERE content LIKE %s LIMIT 3", $wild));
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
        if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP']))
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR']))
            $ip = $_SERVER['REMOTE_ADDR'];
        return sanitize_text_field($ip);
    }

    private function save_message($session_id, $sender, $message)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_chat_history';
        $meta_data = array();
        if ($sender === 'user')
            $meta_data['ip'] = $this->get_client_ip();
        $wpdb->insert($table_name, array('session_id' => $session_id, 'sender' => $sender, 'message' => $message, 'timestamp' => current_time('mysql'), 'meta_data' => !empty($meta_data) ? json_encode($meta_data) : ''), array('%s', '%s', '%s', '%s', '%s'));
    }

    private function send_transcript_email($session_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_chat_history';
        $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE session_id = %s ORDER BY timestamp ASC", $session_id));

        if (empty($results))
            return false;

        $chat_content = "<h3>Chat Transcript</h3>";

        // Add User Info if Logged In or Lead Captured
        $user_info = "Guest";
        if (is_user_logged_in()) {
            $u = wp_get_current_user();
            $user_info = "User ID: {$u->ID} ({$u->user_login}) - {$u->user_email}";
        }
        $leads_table = $wpdb->prefix . 'bua_leads';
        $lead = $wpdb->get_row($wpdb->prepare("SELECT user_data FROM $leads_table WHERE chat_session_id = %s", $session_id));
        if ($lead) {
            $user_info .= "<br>Lead Data: " . esc_html($lead->user_data);
        }

        $chat_content .= "<p><strong>Session ID:</strong> $session_id</p>";
        $chat_content .= "<p><strong>User:</strong> $user_info</p><hr><ul>";

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

        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($to, $subject, $chat_content, $headers);

        if (!$sent) {
            error_log("Nueva Chatbot: Failed to send transcript email for session $session_id to $to");
        }

        return $sent;
    }
}
