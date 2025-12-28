<?php
$options = get_option('nueva_chat_options', [
    'general' => ['api_key' => '', 'agent_name' => 'Nueva Agent', 'model' => 'gemini-2.5-flash'],
    'appearance' => ['primary_color' => '#0073aa', 'secondary_color' => '#ffffff', 'font_family' => 'Roboto', 'font_size' => '16', 'position_desktop' => 'right', 'position_mobile' => 'right', 'profile_image' => ''],
    'behavior' => ['tone' => 'professional', 'default_lang' => 'en', 'supported_langs' => 'en'],
    'visibility' => ['include_pages' => [], 'exclude_pages' => []]
]);

$general = $options['general'];
$appearance = $options['appearance'];
$behavior = $options['behavior'];
$visibility = $options['visibility'];
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <h2 class="nav-tab-wrapper">
        <a href="#tab-general" class="nav-tab nav-tab-active">General</a>
        <a href="#tab-appearance" class="nav-tab">Appearance</a>
        <a href="#tab-behavior" class="nav-tab">Behavior</a>
        <a href="#tab-visibility" class="nav-tab">Visibility</a>
    </h2>

    <form method="post" action="">
        <?php wp_nonce_field('nueva_chat_options_verify'); ?>

        <!-- General Tab -->
        <div id="tab-general" class="tab-content">
            <table class="form-table">
                <tr>
                    <th scope="row">Gemini API Key</th>
                    <td><input type="password" name="nueva_api_key" value="<?php echo esc_attr($general['api_key']); ?>"
                            class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row">Agent Name</th>
                    <td><input type="text" name="nueva_agent_name"
                            value="<?php echo esc_attr($general['agent_name']); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row">Notification Email</th>
                    <td>
                        <input type="email" name="nueva_notification_email"
                            value="<?php echo isset($general['notification_email']) ? esc_attr($general['notification_email']) : get_option('admin_email'); ?>"
                            class="regular-text" />
                        <p class="description">Email to receive chat transcripts and leads.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">AI Model</th>
                    <td>
                        <select name="nueva_model" id="nueva_model_select">
                            <option value="gemini-2.5-flash" <?php selected($general['model'], 'gemini-2.5-flash'); ?>>
                                Gemini 2.5 Flash</option>
                            <option value="gemini-2.5-flash-lite" <?php selected($general['model'], 'gemini-2.5-flash-lite'); ?>>
                                Gemini 2.5 Flash Lite</option>
                            <option value="gemini-3-flash-preview" <?php selected($general['model'], 'gemini-3-flash-preview'); ?>>
                                Gemini 3.0 Flash Preview</option>
                            <option value="gemini-3-pro-preview" <?php selected($general['model'], 'gemini-3-pro-preview'); ?>>
                                Gemini 3.0 Pro Preview</option>
                            <option value="custom" <?php selected($general['model'], 'custom'); ?>>
                                Custom / Other...</option>
                        </select>
                        <br>
                        <input type="text" name="nueva_model_custom" id="nueva_model_custom"
                            value="<?php echo isset($general['model_custom']) ? esc_attr($general['model_custom']) : ''; ?>"
                            class="regular-text" placeholder="e.g., gemini-2.0-pro-exp"
                            style="margin-top: 5px; display: <?php echo ($general['model'] === 'custom') ? 'block' : 'none'; ?>;" />
                        <p class="description">Select a preset or choose "Custom" to enter a specific Model ID manually.
                        </p>
                        <script>
                            jQuery(document).ready(function ($) {
                                $('#nueva_model_select').change(functio n() {
                                    if($(this).val() === 'custom') {
                                    $('#nueva_model_custom').show();
                                } else {
                                    $('#nueva_model_custom').hide();
                                }
                            });
                            });
                        </script>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Appearance Tab -->
        <div id="tab-appearance" class="tab-content" style="display:none;">
            <table class="form-table">
                <tr>
                    <th scope="row">Primary Color (Background)</th>
                    <td><input type="text" name="nueva_primary_color"
                            value="<?php echo esc_attr($appearance['primary_color']); ?>" class="my-color-field"
                            data-default-color="#0073aa" /></td>
                </tr>
                <!-- Gradient fields removed per user request for solid colors -->
                <tr>
                    <th scope="row">Secondary Color (Text/Icon)</th>
                    <td><input type="text" name="nueva_secondary_color"
                            value="<?php echo esc_attr($appearance['secondary_color']); ?>" class="my-color-field"
                            data-default-color="#ffffff" /></td>
                </tr>
                <tr>
                    <th scope="row">Accent Color (Buttons/Highlights)</th>
                    <td><input type="text" name="nueva_accent_color"
                            value="<?php echo isset($appearance['accent_color']) ? esc_attr($appearance['accent_color']) : '#0073aa'; ?>"
                            class="my-color-field" data-default-color="#0073aa" /></td>
                </tr>
                <tr>
                    <th scope="row">Font Family</th>
                    <td>
                        <?php
                        $fonts = [
                            'Roboto',
                            'Inter',
                            'Open Sans',
                            'Lato',
                            'Montserrat',
                            'Oswald',
                            'Source Sans Pro',
                            'Slabo 27px',
                            'Raleway',
                            'PT Sans',
                            'Merriweather',
                            'Noto Sans',
                            'Nunito Sans',
                            'Prompt',
                            'Work Sans',
                            'Quicksand',
                            'Rubik',
                            'Fira Sans',
                            'Barlow',
                            'Mulish',
                            'Oxygen',
                            'Mukta',
                            'Heebo',
                            'Ubuntu',
                            'Playfair Display',
                            'Poppins'
                        ];
                        sort($fonts);
                        ?>
                        <select name="nueva_font_family">
                            <?php foreach ($fonts as $font): ?>
                                <option value="<?php echo $font; ?>" <?php selected($appearance['font_family'], $font); ?>>
                                    <?php echo $font; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Standard Google Fonts.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Font Size (px)</th>
                    <td><input type="number" name="nueva_font_size"
                            value="<?php echo esc_attr($appearance['font_size']); ?>" class="small-text" /></td>
                </tr>
                <tr>
                    <th scope="row">Position (Desktop)</th>
                    <td>
                        <select name="nueva_position_desktop">
                            <option value="right" <?php selected($appearance['position_desktop'], 'right'); ?>>Bottom
                                Right</option>
                            <option value="left" <?php selected($appearance['position_desktop'], 'left'); ?>>Bottom Left
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Position (Mobile)</th>
                    <td>
                        <select name="nueva_position_mobile">
                            <option value="right" <?php selected($appearance['position_mobile'], 'right'); ?>>Bottom
                                Right</option>
                            <option value="left" <?php selected($appearance['position_mobile'], 'left'); ?>>Bottom Left
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Profile Image URL</th>
                    <td>
                        <input type="text" name="nueva_profile_image" id="nueva_profile_image"
                            value="<?php echo esc_attr($appearance['profile_image']); ?>" class="regular-text" />
                        <input type="button" class="button" id="upload_image_button" value="Upload Image" />
                    </td>
                </tr>
            </table>
        </div>

        <!-- Behavior Tab -->
        <div id="tab-behavior" class="tab-content" style="display:none;">
            <table class="form-table">
                <tr>
                    <th scope="row">Agent Tone</th>
                    <td>
                        <select name="nueva_tone">
                            <option value="professional" <?php selected($behavior['tone'], 'professional'); ?>>
                                Professional</option>
                            <option value="friendly" <?php selected($behavior['tone'], 'friendly'); ?>>Friendly</option>
                            <option value="humorous" <?php selected($behavior['tone'], 'humorous'); ?>>Humorous</option>
                            <option value="humorous" <?php selected($behavior['tone'], 'humorous'); ?>>Humorous</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Lead Generation Mode</th>
                    <td>
                        <select name="nueva_lead_mode">
                            <option value="disabled" <?php selected(isset($behavior['lead_mode']) ? $behavior['lead_mode'] : 'disabled', 'disabled'); ?>>Disabled (Chat Immediately)
                            </option>
                            <option value="conversational" <?php selected(isset($behavior['lead_mode']) ? $behavior['lead_mode'] : '', 'conversational'); ?>>Conversational (Ask Name -> Email ->
                                Phone)</option>
                            <option value="gate" <?php selected(isset($behavior['lead_mode']) ? $behavior['lead_mode'] : '', 'gate'); ?>>Before Chat (Lead Gate Form)</option>
                        </select>
                        <p class="description">How should the AI collect user details?</p>
                        <div id="nueva_gate_options"
                            style="margin-top:10px; padding:10px; background:#f0f0f1; border:1px solid #ddd; <?php echo (isset($behavior['lead_mode']) && $behavior['lead_mode'] === 'gate') ? '' : 'display:none;'; ?>">
                            <label>Gate Title: <input type="text" name="nueva_gate_title"
                                    value="<?php echo isset($behavior['gate_title']) ? esc_attr($behavior['gate_title']) : 'Welcome! Please introduce yourself.'; ?>"
                                    class="regular-text"></label><br>
                            <label>Gate Button: <input type="text" name="nueva_gate_btn"
                                    value="<?php echo isset($behavior['gate_btn']) ? esc_attr($behavior['gate_btn']) : 'Start Chat'; ?>"
                                    class="regular-text"></label>
                        </div>
                        <script>
                            jQuery(document).ready(function ($) {
                                $('select[name="nueva_lead_mode"]').change(function () {
                                    if ($(this).val() === 'gate') {
                                        $('#nueva_gate_options').show();
                                    } else {
                                        $('#nueva_gate_options').hide();
                                    }
                                });
                            });
                        </script>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Logged-in Users</th>
                    <td>
                        <label>
                            <input type="checkbox" name="nueva_lead_skip_logged_in" value="1" <?php checked(isset($behavior['lead_skip_logged_in']) ? $behavior['lead_skip_logged_in'] : false); ?>>
                            Skip lead collection if user is already Logged In
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Office/Store Visits</th>
                    <td>
                        <select name="nueva_allow_visits">
                            <option value="yes" <?php selected(isset($behavior['allow_visits']) ? $behavior['allow_visits'] : 'no', 'yes'); ?>>✅ Allowed (We accept physical visits)
                            </option>
                            <option value="no" <?php selected(isset($behavior['allow_visits']) ? $behavior['allow_visits'] : 'no', 'no'); ?>>❌ Not Allowed (Online / Remote Only)
                            </option>
                        </select>
                        <p class="description">Does your business accept walk-ins or physical visits? The AI will answer
                            accordingly.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Link Sharing</th>
                    <td>
                        <select name="nueva_allow_links">
                            <option value="yes" <?php selected(isset($behavior['allow_links']) ? $behavior['allow_links'] : 'yes', 'yes'); ?>>✅ Allowed (Share Content URLs)</option>
                            <option value="no" <?php selected(isset($behavior['allow_links']) ? $behavior['allow_links'] : '', 'no'); ?>>❌ Disabled (Do NOT share URLs)</option>
                        </select>
                        <p class="description">Should the AI provide links to products or pages when found?</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Guest Order Status</th>
                    <td>
                        <select name="nueva_guest_orders">
                            <option value="yes" <?php selected(isset($behavior['guest_orders']) ? $behavior['guest_orders'] : 'yes', 'yes'); ?>>✅ Enabled (Allow Order ID + Email lookup)
                            </option>
                            <option value="no" <?php selected(isset($behavior['guest_orders']) ? $behavior['guest_orders'] : '', 'no'); ?>>❌ Disabled</option>
                        </select>
                        <p class="description">Allows guest users to check order status by providing Order ID and Email
                            in chat.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">AI Strictness</th>
                    <td>
                        <select name="nueva_kb_strictness">
                            <option value="balanced" <?php selected(isset($behavior['kb_strictness']) ? $behavior['kb_strictness'] : 'balanced', 'balanced'); ?>>Balanced (KB + General
                                Knowledge)</option>
                            <option value="strict" <?php selected(isset($behavior['kb_strictness']) ? $behavior['kb_strictness'] : '', 'strict'); ?>>Strict (Knowledge Base ONLY)</option>
                        </select>
                        <p class="description">Strict mode reduces hallucinations but may say "I don't know" more often.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Additional Instructions</th>
                    <td>
                        <textarea name="nueva_agent_instructions" id="nueva_agent_instructions" class="large-text"
                            rows="5" placeholder="e.g. Always mention our 24/7 support. Be very polite."><?php
                            echo isset($behavior['agent_instructions']) ? esc_textarea($behavior['agent_instructions']) : '';
                            ?></textarea>
                        <p class="description">Any extra rules (Tone, specific phrases to avoid) to append to the system
                            prompt.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Lead Fields to Collect</th>
                    <td>
                        <textarea name="nueva_lead_fields" class="large-text" rows="4"
                            placeholder="Name, Email, Phone, City, Order ID, etc."><?php
                            echo isset($behavior['lead_fields']) ? esc_textarea($behavior['lead_fields']) : 'Name, Email, Phone';
                            ?></textarea>
                        <p class="description">List all fields the AI should collect from the user (comma or new-line
                            separated). The AI will validate standard formats (Email, Phone) automatically.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Initial Welcome Message</th>
                    <td>
                        <textarea name="nueva_initial_message" class="large-text"
                            rows="3"><?php echo isset($behavior['initial_message']) ? esc_textarea($behavior['initial_message']) : 'Hello! How can I help you today?'; ?></textarea>
                        <p class="description">The first message the chatbot sends to the user.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Human Handoff</th>
                    <td>
                        <label>
                            <input type="checkbox" name="nueva_enable_handoff" value="1" <?php checked(isset($behavior['enable_handoff']) && $behavior['enable_handoff']); ?> />
                            Enable Human Handoff
                        </label>
                        <p class="description">If enabled, the AI will notify Admin when a user asks for human support
                            (only after collecting leads).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Default Language</th>
                    <td><input type="text" name="nueva_default_lang"
                            value="<?php echo esc_attr($behavior['default_lang']); ?>" class="regular-text"
                            placeholder="en" /></td>
                </tr>
                <tr>
                    <th scope="row">Supported Languages (comma separated)</th>
                    <td><input type="text" name="nueva_supported_langs"
                            value="<?php echo esc_attr($behavior['supported_langs']); ?>" class="regular-text"
                            placeholder="en, es, fr" /></td>
                </tr>
            </table>
        </div>

        <!-- Visibility Tab -->
        <div id="tab-visibility" class="tab-content" style="display:none;">
            <p>The chatbot is displayed on <strong>all pages</strong> by default. Select pages below to
                <strong>hide</strong> it.
            </p>
            <?php
            // Fetch all pages and posts
            $all_pages = get_posts([
                'post_type' => ['page', 'post'],
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'orderby' => 'title',
                'order' => 'ASC'
            ]);
            $excluded_ids = isset($visibility['exclude_pages']) && is_array($visibility['exclude_pages']) ? $visibility['exclude_pages'] : [];
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Exclude on Pages/Posts</th>
                    <td>
                        <select name="nueva_exclude_pages[]" multiple style="height: 300px; width: 100%;">
                            <?php foreach ($all_pages as $p): ?>
                                <option value="<?php echo $p->ID; ?>" <?php echo in_array($p->ID, $excluded_ids) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($p->post_title); ?> (<?php echo ucfirst($p->post_type); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Hold Ctrl (Windows) or Command (Mac) to select multiple pages.</p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <input type="submit" name="nueva_save_settings" id="submit" class="button button-primary"
                value="Save Changes">
        </p>
    </form>
</div>

<script>
    jQuery(document).ready(function ($) {
        $('.nav-tab').click(function (e) {
            e.preventDefault();
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('.tab-content').hide();
            var activeTab = $(this).attr('href');
            $(activeTab).show();
        });
        $('.my-color-field').wpColorPicker();

        // Media Uploader
        var file_frame;
        $('#upload_image_button').on('click', function (event) {
            event.preventDefault();
            if (file_frame) {
                file_frame.open();
                return;
            }
            file_frame = wp.media.frames.file_frame = wp.media({
                title: 'Select Profile Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });
            file_frame.on('select', function () {
                var attachment = file_frame.state().get('selection').first().toJSON();
                $('#nueva_profile_image').val(attachment.url);
            });
            file_frame.open();
        });
    });
</script>