<?php
$options = get_option('nueva_chat_options', [
    'general' => ['api_key' => '', 'agent_name' => 'Nueva Agent', 'model' => 'gemini-1.5-pro'],
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
                    <th scope="row">AI Model</th>
                    <td>
                        <select name="nueva_model">
                            <option value="gemini-1.5-flash" <?php selected($general['model'], 'gemini-1.5-flash'); ?>>
                                Gemini 1.5 Flash (Fastest)</option>
                            <option value="gemini-1.5-pro-001" <?php selected($general['model'], 'gemini-1.5-pro-001'); ?>>
                                Gemini 1.5 Pro (Best Quality)</option>
                            <option value="gemini-1.0-pro" <?php selected($general['model'], 'gemini-1.0-pro'); ?>>
                                Gemini 1.0 Pro (Legacy)</option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Appearance Tab -->
        <div id="tab-appearance" class="tab-content" style="display:none;">
            <table class="form-table">
                <tr>
                    <th scope="row">Primary Color</th>
                    <td><input type="text" name="nueva_primary_color"
                            value="<?php echo esc_attr($appearance['primary_color']); ?>" class="my-color-field"
                            data-default-color="#0073aa" /></td>
                </tr>
                <tr>
                    <th scope="row">Secondary Color (Text/Icon)</th>
                    <td><input type="text" name="nueva_secondary_color"
                            value="<?php echo esc_attr($appearance['secondary_color']); ?>" class="my-color-field"
                            data-default-color="#ffffff" /></td>
                </tr>
                <tr>
                    <th scope="row">Font Family</th>
                    <td>
                        <select name="nueva_font_family">
                            <option value="Roboto" <?php selected($appearance['font_family'], 'Roboto'); ?>>Roboto
                            </option>
                            <option value="Inter" <?php selected($appearance['font_family'], 'Inter'); ?>>Inter</option>
                            <option value="Open Sans" <?php selected($appearance['font_family'], 'Open Sans'); ?>>Open
                                Sans</option>
                        </select>
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
                        </select>
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