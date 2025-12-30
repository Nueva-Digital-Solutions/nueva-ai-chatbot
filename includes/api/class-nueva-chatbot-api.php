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

        add_action('wp_ajax_nueva_upload_file', array($this, 'handle_upload_file_request'));
        add_action('wp_ajax_nopriv_nueva_upload_file', array($this, 'handle_upload_file_request'));

        add_action('wp_ajax_nueva_end_chat', array($this, 'handle_end_chat_request'));
        add_action('wp_ajax_nopriv_nueva_end_chat', array($this, 'handle_end_chat_request'));

        add_action('wp_ajax_nueva_submit_feedback', array($this, 'handle_submit_feedback_request'));
        add_action('wp_ajax_nopriv_nueva_submit_feedback', array($this, 'handle_submit_feedback_request'));
    }

    public function handle_upload_file_request()
    {
        check_ajax_referer('nueva_chat_nonce', 'nonce');

        if (!isset($_FILES['file'])) {
            wp_send_json_error('No file uploaded.');
        }

        $file = $_FILES['file'];

        // Validation: Size (1MB = 1048576 bytes)
        if ($file['size'] > 1048576) {
            wp_send_json_error('File too large. Max 1MB.');
        }

        // Validation: Type
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $file_info = wp_check_filetype($file['name']);
        if (!in_array($file_info['type'], $allowed_mimes)) {
            wp_send_json_error('Invalid file type. Only JPG, PNG, WEBP, PDF allowed.');
        }

        // Upload
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($file, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            wp_send_json_success(array(
                'url' => $movefile['url'],
                'path' => $movefile['file'], // Absolute path for server-side reading
                'type' => $movefile['type']
            ));
        } else {
            wp_send_json_error($movefile['error']);
        }
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

    public function handle_submit_feedback_request()
    {
        check_ajax_referer('nueva_chat_nonce', 'nonce');

        $session_id = sanitize_text_field($_POST['session_id']);
        $rating = intval($_POST['rating']);
        $reason = sanitize_textarea_field($_POST['reason']);

        if (empty($session_id) || $rating < 1 || $rating > 5) {
            wp_send_json_error('Invalid feedback data.');
        }

        // Trigger Async Categorization
        $category = 'General';
        // Only classify if table column exists (backwards compatibility)
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_chat_feedback';
        $has_cat_col = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'category'");

        if ($has_cat_col) {
            $category = $this->categorize_conversation($session_id);
            $wpdb->insert(
                $table_name,
                array(
                    'session_id' => $session_id,
                    'rating' => $rating,
                    'reason' => $reason,
                    'category' => $category,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%d', '%s', '%s', '%s')
            );
        } else {
            // Fallback for old schema
            $wpdb->insert(
                $table_name,
                array(
                    'session_id' => $session_id,
                    'rating' => $rating,
                    'reason' => $reason,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%d', '%s', '%s')
            );
        }

        wp_send_json_success('Feedback saved.');
    }

    // New Helper: Categorize Conversation
    private function categorize_conversation($session_id)
    {
        // 1. Get Transcript
        $messages = $this->get_full_transcript($session_id);
        if (empty($messages))
            return 'Unknown';

        // 2. Prepare Prompt
        $system_prompt = "Analyze the following chat transcript. Classify intent into ONE: Sales, Support, Technical, General, Complaint. Return ONLY category name.";

        // 3. Call Gemini
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->api_key}";

        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $system_prompt . "\n\n" . $messages]
                    ]
                ]
            ]
        ];

        $response = wp_remote_post($url, array(
            'body' => json_encode($body),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 5
        ));

        if (is_wp_error($response))
            return 'General';

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $cat = trim($data['candidates'][0]['content']['parts'][0]['text']);
            // Normalize
            $allowed = ['Sales', 'Support', 'Technical', 'General', 'Complaint'];
            foreach ($allowed as $valid) {
                if (stripos($cat, $valid) !== false)
                    return $valid;
            }
        }
        return 'General';
    }

    private function get_full_transcript($session_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_chat_history';
        $results = $wpdb->get_results($wpdb->prepare("SELECT sender, message FROM $table_name WHERE session_id = %s ORDER BY timestamp ASC", $session_id));
        $text = "";
        foreach ($results as $row) {
            $role = ($row->sender === 'user') ? 'User' : 'Agent';
            $text .= "$role: " . mb_substr($row->message, 0, 500) . "\n";
        }
        return $text;
    }

    public function handle_ajax_request()
    {
        check_ajax_referer('nueva_chat_nonce', 'nonce');

        $message = sanitize_text_field($_POST['message']);
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';

        // Attachment Handling
        $attachment = null;
        if (isset($_POST['attachment_path']) && !empty($_POST['attachment_path'])) {
            $attachment = array(
                'path' => sanitize_text_field($_POST['attachment_path']),
                'mime' => sanitize_text_field($_POST['attachment_mime']),
                'url' => isset($_POST['attachment_url']) ? esc_url_raw($_POST['attachment_url']) : ''
            );
        }

        // Capture Leads
        if ($session_id) {
            $this->capture_lead($session_id, $message);
            // Modify saved message to include attachment link if present
            $save_msg = $message;
            if ($attachment) {
                $save_msg .= " [Attachment: " . $attachment['url'] . "]";
            }
            $this->save_message($session_id, 'user', $save_msg);
        }

        $response = $this->query_gemini($message, $session_id, $attachment);

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

    private function query_gemini($user_message, $session_id, $attachment = null)
    {
        if (empty($this->api_key)) {
            return "Error: API Key is missing. Please contact the administrator.";
        }

        // Get Options
        // Get Options
        $options = get_option('nueva_chat_options');
        $agent_name = isset($options['general']['agent_name']) ? $options['general']['agent_name'] : 'Nueva Agent';
        $tone = isset($options['behavior']['tone']) ? $options['behavior']['tone'] : 'professional';
        $extra_instructions = isset($options['behavior']['agent_instructions']) ? $options['behavior']['agent_instructions'] : '';
        $enable_handoff = isset($options['behavior']['enable_handoff']) ? $options['behavior']['enable_handoff'] : false;
        $lead_fields = isset($options['behavior']['lead_fields']) ? $options['behavior']['lead_fields'] : 'email or phone number';

        // Settings / Flags
        $lead_mode = isset($options['behavior']['lead_mode']) ? $options['behavior']['lead_mode'] : 'disabled';
        $skip_logged_in = isset($options['behavior']['lead_skip_logged_in']) ? $options['behavior']['lead_skip_logged_in'] : false;
        $allow_visits = isset($options['behavior']['allow_visits']) ? $options['behavior']['allow_visits'] : 'no';
        $allow_links = isset($options['behavior']['allow_links']) ? $options['behavior']['allow_links'] : 'yes';
        $kb_strictness = isset($options['behavior']['kb_strictness']) ? $options['behavior']['kb_strictness'] : 'balanced';

        // --- HANDOFF LOGIC START ---
        if ($enable_handoff && $this->check_handoff_request($user_message)) {
            // Check if we have lead info
            if (!$this->has_lead_info($session_id)) {
                return "I can connect you with a human agent. First, please provide your $lead_fields so they can contact you.";
            } else {
                // Trigger Notification
                $this->add_admin_notification($session_id, $user_message);
                return "I have notified a human agent. They will contact you via mail, WhatsApp, or call shortly. Is there anything else I can help with in the meantime?";
            }
        }
        // --- HANDOFF LOGIC END ---

        // 1. Context Retrieval (KB)
        $kb_context = $this->get_kb_context($user_message);

        // 1b. Business Profile Context
        $biz_context = $this->get_business_profile_context();

        // 2. Dynamic Context (User Data / WooCommerce)
        $user_context = $this->get_dynamic_context();

        // 3. Conversation History (Memory)
        $history_context = $this->get_conversation_history($session_id);

        $full_context = $biz_context . "\n" . $kb_context . "\n" . $user_context . "\n" . $history_context;

        // --- DYNAMIC PROMPT BUILDER ---

        // 1. Core Persona
        $system_prompt = "You are $agent_name. Your tone is $tone. You are an AI support agent for this specific website.\n";

        // STRICT RULE ADDITION
        $system_prompt .= "CRITICAL: You must NEVER invent information. Verify EVERY answer against the provided Context below. If the user corrects you or asks something not in the context, apologize and explicitly state that the information is not available in your knowledge base.\n";

        // 2. Strictness
        if ($kb_strictness === 'strict') {
            $system_prompt .= "STRICT INSTRUCTION: Answer using ONLY the context provided below. If the answer is not in the context, say 'I don't have that information'. Do NOT use general knowledge.\n";
        } else {
            $system_prompt .= "INSTRUCTION: Prioritize the proper Context below. If the answer is missing, you may use general helpful knowledge, but be cautious not to hallucinate business policies.\n";
        }

        // 3. Visit Policy
        if ($allow_visits === 'yes') {
            $system_prompt .= "[POLICY] Physical Visits: ALLOWED. You may invite users to visit the store/office if relevant.\n";
        } else {
            $system_prompt .= "[POLICY] Physical Visits: NOT ALLOWED. We are Online/Remote only. Do not suggest visiting a physical location.\n";
        }

        // 4. Link Sharing Policy
        if ($allow_links === 'yes') {
            $system_prompt .= "[POLICY] Links: ALLOWED. If a 'Source URL' is present in the context and relevant to the user's question, you MUST share it. Format it as a Markdown link.\n";
        } else {
            $system_prompt .= "[POLICY] Links: NOT ALLOWED. Do NOT output URLs even if they are in the context.\n";
        }

        // 5. Lead Gen Logic
        $has_leads = $this->has_lead_info($session_id);

        if ($lead_mode === 'conversational' && !$has_leads) {
            $should_ask = true;
            if ($skip_logged_in && is_user_logged_in()) {
                $should_ask = false;
                $system_prompt .= "\n[RULE: LEAD GENERATION] User is LOGGED IN. Do NOT ask for contact info. Proceed to help.\n";
            }

            if ($should_ask) {
                $system_prompt .= "\n[RULE: LEAD GENERATION]\n";
                $system_prompt .= "IF User Status is GUEST (or Lead Info is Missing):\n";
                $system_prompt .= "- Your GOAL is to collect these fields ONE BY ONE: [$lead_fields].\n";
                $system_prompt .= "- VALIDATION: Check each input. \n";
                $system_prompt .= "  * Email MUST look like an email (user@domain). If invalid, say 'That doesn't look right. Please enter a valid email.'\n";
                $system_prompt .= "  * Phone MUST contain digits. If text only, reject it.\n";
                $system_prompt .= "- Once all fields are collected, proceed to answer their question.\n";
                $system_prompt .= "- during this collection phase, do NOT output [SUGGESTIONS].\n";
            }
        } elseif ($lead_mode === 'conversational' && $has_leads) {
            $system_prompt .= "\n[RULE: LEAD GENERATION] Lead info has already been collected. Do NOT ask for it again.\n";
        }

        $system_prompt .= "
        [FEATURES]
        - SUGGESTIONS: At the end of your response, ALWAYS provide 2-3 short follow-up questions users might ask next.
          Format: [SUGGESTIONS] [\"Suggestion 1\", \"Suggestion 2\"]
        
        - GUEST ORDERS: You can look up orders if provided an Order ID (digits) AND (Email OR Phone Number).
          If user provides ID + Phone, say 'Checking order #ID with phone...'
        ";

        // 6. KB / Context Rules
        $system_prompt .= "\n[RULE: CONTEXT & PRIVACY]\nDo NOT mention 'Knowledge Base' or 'Context'. Answer naturally.\n";

        // 7. Extra / Custom
        if (!empty($extra_instructions)) {
            $system_prompt .= "\nCUSTOM INSTRUCTIONS: $extra_instructions\n";
        }

        $system_prompt .= "\n[RULE: SMART ENDING]\nIf the user indicates they are done (e.g., 'No thanks', 'I'm good', 'Bye', 'No'), append the token [END_CHAT] to the end of your response.\n";

        $system_prompt .= "\nContext:\n$full_context";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->api_key}";

        $parts = [];
        $parts[] = ['text' => $system_prompt . "\n\nUser: " . $user_message];

        // Attach Image/PDF if exists
        if ($attachment && file_exists($attachment['path'])) {
            $file_data = base64_encode(file_get_contents($attachment['path']));
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $attachment['mime'],
                    'data' => $file_data
                ]
            ];
        }

        $body = [
            'contents' => [
                [
                    'parts' => $parts
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

        // 1. Auto-Capture Logged In User (if not exists)
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $existing = $wpdb->get_row($wpdb->prepare("SELECT id, user_data FROM $table_name WHERE chat_session_id = %s", $session_id));

            if (!$existing) {
                // Create initial lead from WP Account
                $account_data = [
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                    'is_logged_in' => true
                ];
                $wpdb->insert($table_name, array('chat_session_id' => $session_id, 'user_data' => json_encode($account_data), 'collected_at' => current_time('mysql'), 'is_synced' => 0));
                // We return here? No, look for phone in message too.
            }
        }

        // 2. Parse Message for Email/Phone
        preg_match('/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i', $message, $email_matches);

        // Sanitize for Phone: Remove spaces, dashes, parens
        $clean_msg = preg_replace('/[^0-9]/', '', $message);
        // Look for 10-15 digits
        preg_match('/\d{10,15}/', $clean_msg, $phone_matches);

        $data = [];
        if (!empty($email_matches[0]))
            $data['email'] = $email_matches[0];
        if (!empty($phone_matches[0]))
            $data['phone'] = $phone_matches[0]; // Stores raw digits

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

        // Check Guest Order Lookup
        $options = get_option('nueva_chat_options');
        $guest_orders_enabled = isset($options['behavior']['guest_orders']) ? $options['behavior']['guest_orders'] : 'yes';

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $context .= "User Status: LOGGED IN\n";
            $context .= "Current User ID: " . $user->ID . "\n";
            $context .= "Current User Name: " . $user->display_name . "\n";
            $context .= "Current User Email: " . $user->user_email . "\n";

            // 2. WooCommerce Orders (if active)
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
            $context .= "User Status: GUEST (Not Logged In)\n";

            // Login Prompt Logic
            $login_url = wp_login_url(); // Default fallback
            if (class_exists('WooCommerce')) {
                $my_account_id = get_option('woocommerce_myaccount_page_id');
                if ($my_account_id) {
                    $login_url = get_permalink($my_account_id);
                }
            }

            $context .= "Instruction: The user is NOT logged in. If they ask about their orders, account status, or recent purchases, politely ask them to log in first. Provide this link: [Login / My Account]($login_url). Do NOT attempt to look up orders for guests.\n";
        }

        return $context;
    }

    private function get_business_profile_context()
    {
        $options = get_option('nueva_chat_options');
        $biz_data = isset($options['business_info']) ? $options['business_info'] : [];

        if (empty($biz_data)) {
            return "";
        }

        $info = "--- Business Context ---\n";

        // 1. Basic Details
        // 1. Basic Details
        $map = array(
            'business_name' => 'Name',
            'legal_name' => 'Legal Name',
            'founding_date' => 'Founding Date',
            'gst' => 'Tax ID / GST',
            'price_range' => 'Price Range',
            'office_timing' => 'Office Timing',
            'contact_link' => 'Contact Page'
        );

        foreach ($map as $key => $label) {
            if (!empty($biz_data[$key])) {
                $info .= "$label: " . $biz_data[$key] . "\n";
            }
        }

        // 2. Locations
        if (!empty($biz_data['locations']) && is_array($biz_data['locations'])) {
            $info .= "\n[Locations]\n";
            foreach ($biz_data['locations'] as $loc) {
                $parts = [];
                if (!empty($loc['addr1']))
                    $parts[] = $loc['addr1'];
                if (!empty($loc['addr2']))
                    $parts[] = $loc['addr2'];
                if (!empty($loc['city']))
                    $parts[] = $loc['city'];
                if (!empty($loc['pincode']))
                    $parts[] = $loc['pincode'];
                if (!empty($loc['country']))
                    $parts[] = $loc['country'];
                if (!empty($loc['mobile']))
                    $parts[] = "(Tel: " . $loc['mobile'] . ")";

                $info .= "- " . implode(", ", $parts) . "\n";
            }
        }

        // 3. Contact Methods
        if (!empty($biz_data['emails']) && is_array($biz_data['emails'])) {
            $info .= "\n[Emails]\n";
            foreach ($biz_data['emails'] as $em) {
                if (!empty($em['email'])) {
                    $type = !empty($em['type']) ? ucfirst($em['type']) : 'Support';
                    $info .= "- $type: " . $em['email'] . "\n";
                }
            }
        }

        if (!empty($biz_data['mobile_numbers']) && is_array($biz_data['mobile_numbers'])) {
            $info .= "\n[Phone Numbers]\n";
            foreach ($biz_data['mobile_numbers'] as $mob) {
                if (!empty($mob['number'])) {
                    $type = !empty($mob['type']) ? ucfirst($mob['type']) : 'Support';
                    $info .= "- $type: " . $mob['number'] . "\n";
                }
            }
        }

        // 4. Social & People
        if (!empty($biz_data['social_media']) && is_array($biz_data['social_media'])) {
            $info .= "\n[Social Media]\n";
            foreach ($biz_data['social_media'] as $soc) {
                if (!empty($soc['link'])) {
                    $platform = !empty($soc['platform']) ? ucfirst($soc['platform']) : 'Link';
                    $info .= "- $platform: " . $soc['link'] . "\n";
                }
            }
        }

        if (!empty($biz_data['founders']) && is_array($biz_data['founders'])) {
            $names = [];
            foreach ($biz_data['founders'] as $f) {
                if (!empty($f['name']))
                    $names[] = $f['name'];
            }
            if (!empty($names)) {
                $info .= "\nFounders: " . implode(", ", $names) . "\n";
            }
        }

        if (!empty($biz_data['service_areas']) && is_array($biz_data['service_areas'])) {
            $areas = [];
            foreach ($biz_data['service_areas'] as $a) {
                if (!empty($a['name']))
                    $areas[] = $a['name'];
            }
            if (!empty($areas)) {
                $info .= "\nService Areas: " . implode(", ", $areas) . "\n";
            }
        }

        $info .= "--- End Business Context ---\n";
        return $info;
    }

    private function get_kb_context($query)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_knowledge_base';

        // 1. Tokenize query
        $clean_query = preg_replace('/[^\p{L}\p{N}\s]/u', '', strtolower($query));
        $stop_words = ['the', 'is', 'in', 'at', 'of', 'on', 'and', 'a', 'to', 'it', 'for', 'or', 'an', 'as', 'by', 'with', 'from', 'that', 'which', 'who', 'what', 'where', 'when', 'why', 'how', 'can', 'could', 'would', 'should', 'do', 'does', 'did'];
        $words = explode(' ', $clean_query);
        $keywords = array_diff($words, $stop_words);
        $keywords = array_filter($keywords);

        if (empty($keywords))
            return "";

        // 2. Build Scoring Query
        $likes = [];
        $params = [];
        foreach ($keywords as $word) {
            $likes[] = "(content LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($word) . '%';
        }
        $score_formula = implode(' + ', $likes);

        // Fetch source_ref as well
        $sql = "SELECT content, source_ref, ($score_formula) as relevance FROM $table_name HAVING relevance > 0 ORDER BY relevance DESC LIMIT 4";
        $prepared_sql = $wpdb->prepare($sql, $params);
        $results = $wpdb->get_results($prepared_sql);

        $context = "";
        if ($results) {
            $context .= "--- Knowledge Base Search Results ---\n";
            foreach ($results as $row) {
                // Determine source label
                $source = !empty($row->source_ref) ? "Source URL: " . $row->source_ref : "(Manual Entry)";
                $context .= trim($row->content) . "\n" . $source . "\n\n";
            }
            $context .= "--- End KB Results ---\n";
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
    // --- FLOW GENERATION ---
    public function generate_flow_from_prompt($user_prompt)
    {
        $schema = '{
            "start": "step_id",
            "nodes": {
                "step_id": {
                    "message": "Bot Message",
                    "options": [
                        { "label": "Btn Label", "next": "next_step_id", "action": "step|link|phone", "value": "url or phone (optional)" }
                    ]
                }
            }
        }';

        $system_prompt = "You are an AI Chat Flow Architect. 
        Create a logic flow JSON based on: '$user_prompt'.
        
        Output MUST be valid JSON following this exact structure:
        $schema
        
        Rules:
        1. Use unique IDs for keys (e.g. step_01, step_02).
        2. 'start' must point to the first node.
        3. 'action' can be 'step' (default), 'link' (open url), or 'phone' (call).
        4. If action is link/phone, put valid URL/Number in 'value'.
        5. Return ONLY the JSON string. No markdown formatting.";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->api_key}";

        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $system_prompt]
                    ]
                ]
            ]
        ];

        $args = array(
            'body' => json_encode($body),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 45,
            'method' => 'POST'
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response))
            return false;

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $raw = $data['candidates'][0]['content']['parts'][0]['text'];
            // Clean markdown blocks if present
            $raw = preg_replace('/^```json\s*|\s*```$/', '', $raw);
            $json = json_decode($raw, true);
            return $json;
        }
        return false;
    }
}
