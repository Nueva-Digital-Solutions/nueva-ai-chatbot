<?php
defined('ABSPATH') || exit;

/**
 * Partial: Knowledge Base Display
 * 
 * This file is included within the Nueva_Chatbot_Admin class.
 * It runs in the WordPress admin context where functions like 
 * check_admin_referer() and classes like WP_Query are available.
 * 
 * @global wpdb $wpdb
 */

// Handle Actions
if (isset($_POST['nueva_kb_action']) && check_admin_referer('nueva_kb_verify')) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bua_knowledge_base';

    // Delete Item
    if (isset($_POST['nueva_kb_action']) && $_POST['nueva_kb_action'] == 'delete_item') {
        $id = intval($_POST['item_id']);
        $wpdb->delete($table_name, ['id' => $id]);
        echo '<div class="notice notice-success"><p>Item deleted successfully.</p></div>';
    }

    // Update Item
    if (isset($_POST['nueva_kb_action']) && $_POST['nueva_kb_action'] == 'edit_item') {
        $id = intval($_POST['edit_item_id']);
        $content = wp_kses_post($_POST['edit_content']);
        $wpdb->update($table_name, ['content' => $content], ['id' => $id]);
        echo '<div class="notice notice-success"><p>Item updated successfully.</p></div>';
    }

    // Add URL
    if ($_POST['nueva_kb_action'] == 'add_url') {
        $url = esc_url_raw($_POST['kb_url']);

        // 1. Validation: Local Domain Only
        $home_host = parse_url(home_url(), PHP_URL_HOST);
        $url_host = parse_url($url, PHP_URL_HOST);

        // Strip www. for looser matching if needed, but strict is safer.
        // Let's do a case-insensitive check
        if (strcasecmp($home_host, $url_host) !== 0) {
            echo '<div class="notice notice-error"><p>External URLs are not allowed. You can only add URLs from your own domain (' . esc_html($home_host) . ').</p></div>';
        } else {
            // 2. Fetch URL
            $response = wp_remote_get($url, array('timeout' => 30));

            if (is_wp_error($response)) {
                echo '<div class="notice notice-error"><p>Failed to fetch URL: ' . $response->get_error_message() . '</p></div>';
            } else {
                $html = wp_remote_retrieve_body($response);

                // 2. Extract Text
                if (!empty($html)) {
                    $dom = new DOMDocument();
                    libxml_use_internal_errors(true);
                    $dom->loadHTML($html);
                    libxml_clear_errors();

                    $xpath = new DOMXPath($dom);
                    // Remove scripts and styles
                    foreach ($xpath->query('//script|//style|//noscript') as $node) {
                        $node->parentNode->removeChild($node);
                    }

                    $content = trim($dom->textContent);
                    // Clean extra whitespace
                    $content = preg_replace('/\s+/', ' ', $content);
                    // Limit size
                    $content = substr($content, 0, 5000);

                    $wpdb->insert($table_name, [
                        'type' => 'url',
                        'source_ref' => $url,
                        'content' => $content,
                        'created_at' => current_time('mysql')
                    ]);
                    echo '<div class="notice notice-success"><p>URL scraped and saved successfully.</p></div>';
                } else {
                    echo '<div class="notice notice-warning"><p>URL returned empty content.</p></div>';
                }
            }
        }
    }

    // Manual Entry
    if ($_POST['nueva_kb_action'] == 'add_manual') {
        $data = [];
        $headings = $_POST['manual_heading'];
        $paragraphs = $_POST['manual_paragraph'];
        if (is_array($headings)) {
            foreach ($headings as $index => $heading) {
                // Combine for simpler storage in vector-like simple search
                $text = "Context: " . sanitize_text_field($heading) . "\n" . sanitize_textarea_field($paragraphs[$index]);

                $wpdb->insert($table_name, [
                    'type' => 'manual',
                    'source_ref' => 'Manual - ' . substr($heading, 0, 20) . '...',
                    'content' => $text,
                    'created_at' => current_time('mysql')
                ]);
            }
        }
        echo '<div class="notice notice-success"><p>Manual entries added.</p></div>';
    }


}

// Fetch Items
global $wpdb;
$table_name = $wpdb->prefix . 'bua_knowledge_base';
$items = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
?>

<div class="wrap">
    <h1>Knowledge Base</h1>
    <h2 class="nav-tab-wrapper">
        <a href="#tab-list" class="nav-tab nav-tab-active">All Items</a>
        <a href="#tab-add-url" class="nav-tab">Add URL</a>
        <a href="#tab-manual" class="nav-tab">Manual Entry</a>
        <a href="#tab-scan" class="nav-tab">Scan Website</a>
        <a href="#tab-faq" class="nav-tab">FAQ Builder</a>
    </h2>

    <div id="tab-list" class="tab-content">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Source</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($items):
                    foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo $item->id; ?></td>
                            <td><?php echo $item->type; ?></td>
                            <td><?php echo $item->source_ref; ?></td>
                            <td><?php echo $item->created_at; ?></td>
                            <td>
                                <button class="button button-small edit-kb" data-id="<?php echo $item->id; ?>"
                                    data-content="<?php echo esc_attr($item->content); ?>">Edit</button>

                                <form method="post" style="display:inline-block; margin-left: 5px;"
                                    onsubmit="return confirm('Are you sure?');">
                                    <?php wp_nonce_field('nueva_kb_verify'); ?>
                                    <input type="hidden" name="nueva_kb_action" value="delete_item">
                                    <input type="hidden" name="item_id" value="<?php echo $item->id; ?>">
                                    <button type="submit" class="button button-small button-link-delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="5">No items found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Edit Modal (Simple Inline Logic) -->
    <div id="nueva-kb-edit-modal"
        style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:20px; border:1px solid #ccc; box-shadow:0 0 10px rgba(0,0,0,0.5); z-index:9999; width: 60%; max-width: 600px;">
        <h3>Edit Knowledge Base Item</h3>
        <form method="post">
            <?php wp_nonce_field('nueva_kb_verify'); ?>
            <input type="hidden" name="nueva_kb_action" value="edit_item">
            <input type="hidden" name="edit_item_id" id="edit_item_id">
            <textarea name="edit_content" id="edit_content" rows="10" style="width:100%;"></textarea>
            <br><br>
            <button type="submit" class="button button-primary">Save Changes</button>
            <button type="button" class="button" id="close-edit-modal">Cancel</button>
        </form>
    </div>

    <!-- Add URL -->
    <div id="tab-add-url" class="tab-content" style="display:none;">
        <form method="post">
            <?php wp_nonce_field('nueva_kb_verify'); ?>
            <input type="hidden" name="nueva_kb_action" value="add_url">
            <table class="form-table">
                <tr>
                    <th>URL to Scrap</th>
                    <td><input type="url" name="kb_url" class="regular-text" required></td>
                </tr>
                <tr>
                    <td>
                        <button type="submit" class="button button-primary">Fetch & Save</button>
                    </td>
                </tr>
            </table>
        </form>
    </div>

    <!-- Manual Entry (Repeater) -->
    <div id="tab-manual" class="tab-content" style="display:none;">
        <form method="post">
            <?php wp_nonce_field('nueva_kb_verify'); ?>
            <input type="hidden" name="nueva_kb_action" value="add_manual">
            <div id="manual-repeater">
                <div class="manual-row"
                    style="background:#f9f9f9; padding:10px; margin-bottom:10px; border:1px solid #e5e5e5;">
                    <p><label>Heading</label><br><input type="text" name="manual_heading[]" class="widefat"></p>
                    <p><label>Content</label><br><textarea name="manual_paragraph[]" class="widefat"
                            rows="3"></textarea></p>
                </div>
            </div>
            <button type="button" class="button" id="add-repeater-row">Add Another Section</button>
            <hr>
            <input type="submit" class="button button-primary" value="Save Manual Entry">
        </form>
    </div>

    <!-- Scan Site (New Selective UI) -->
    <div id="tab-scan" class="tab-content" style="display:none;">
        <div class="scan-controls" style="margin-bottom: 20px;">
            <p>Scan your entire website for Pages, Posts, Products, and other content types. You can review the list and
                select exactly what to add to the knowledge base.</p>
            <button type="button" class="button button-primary" id="btn-start-scan">Start Website Scan</button>
            <span id="scan-spinner" class="spinner" style="float:none; margin-left: 5px;"></span>
        </div>

        <div id="scan-results-container" style="display:none;">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <button type="button" class="button button-primary" id="btn-import-selected">Import
                        Selected</button>
                    <span id="import-spinner" class="spinner" style="float:none; margin-left: 5px;"></span>
                </div>
                <div class="alignleft actions">
                    <label><input type="checkbox" id="cb-select-all-scan"> Select All</label>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="cb-select-all-header"></th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Date Published</th>
                        <th>Link</th>
                    </tr>
                </thead>
                <tbody id="scan-results-body">
                    <!-- Results injected here -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- FAQ Builder Tab -->
    <div id="tab-faq" class="tab-content" style="display:none;">
        <form method="post">
            <?php wp_nonce_field('nueva_kb_verify'); ?>
            <input type="hidden" name="nueva_kb_action" value="save_faq_builder">

            <div style="display:flex; gap:20px;">
                <!-- FAQ Questions Repeater -->
                <div style="flex:2;">
                    <h3>FAQ Questions</h3>
                    <p>Add questions and answers. These will be displayed via shortcode <code>[nueva_faq]</code> and
                        also trained into the AI.</p>

                    <?php
                    $faq_data = get_option('nueva_faq_data', []);
                    $faqs = isset($faq_data['items']) ? $faq_data['items'] : [];
                    ?>

                    <div id="faq-repeater">
                        <?php if (!empty($faqs)): ?>
                            <?php foreach ($faqs as $index => $faq): ?>
                                <div class="faq-row"
                                    style="background:#fff; padding:15px; margin-bottom:15px; border:1px solid #ccd0d4; border-left: 4px solid #2271b1;">
                                    <p>
                                        <label><strong>Question</strong></label><br>
                                        <input type="text" name="faq_question[]" class="widefat"
                                            value="<?php echo esc_attr($faq['q']); ?>" required>
                                    </p>
                                    <p>
                                        <label><strong>Answer</strong></label><br>
                                        <textarea name="faq_answer[]" class="widefat" rows="3"
                                            required><?php echo esc_textarea($faq['a']); ?></textarea>
                                    </p>
                                    <button type="button" class="button button-link-delete remove-faq-row">Remove
                                        Question</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <button type="button" class="button" id="add-faq-row">+ Add Question</button>
                </div>

                <!-- Styling Sidebar -->
                <div style="flex:1; background:#f0f0f1; padding:20px; border-radius:5px; height:fit-content;">
                    <h3>Design & Styling</h3>
                    <p>Customize how the FAQ accordion looks on your site.</p>

                    <?php $style = isset($faq_data['style']) ? $faq_data['style'] : []; ?>

                    <p>
                        <label><strong>Title Font Color</strong></label><br>
                        <input type="text" name="style_title_color" class="color-picker"
                            value="<?php echo esc_attr(isset($style['title_color']) ? $style['title_color'] : '#333333'); ?>">
                    </p>
                    <p>
                        <label><strong>Title Font Size (px)</strong></label><br>
                        <input type="number" name="style_title_size" class="small-text"
                            value="<?php echo esc_attr(isset($style['title_size']) ? $style['title_size'] : '16'); ?>">
                        px
                    </p>

                    <hr>

                    <p>
                        <label><strong>Background Color</strong></label><br>
                        <input type="text" name="style_bg_color" class="color-picker"
                            value="<?php echo esc_attr(isset($style['bg_color']) ? $style['bg_color'] : '#ffffff'); ?>">
                    </p>
                    <p>
                        <label><strong>Accordion Padding (px)</strong></label><br>
                        <input type="number" name="style_padding" class="small-text"
                            value="<?php echo esc_attr(isset($style['padding']) ? $style['padding'] : '15'); ?>"> px
                    </p>
                    <p>
                        <label><strong>Item Spacing (px)</strong></label><br>
                        <input type="number" name="style_spacing" class="small-text"
                            value="<?php echo esc_attr(isset($style['spacing']) ? $style['spacing'] : '10'); ?>"> px
                    </p>
                </div>
            </div>

            <hr>
            <input type="submit" class="button button-primary button-large" value="Save FAQ & Sync to AI">
        </form>
    </div>
</div>

<script>
    jQuery(document).ready(function ($) {
        // Tab Switching
        $('.nav-tab').click(function (e) {
            e.preventDefault();
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('.tab-content').hide();
            var activeTab = $(this).attr('href');
            $(activeTab).show();
        });

        // --- SCAN LOGIC ---

        // 1. Fetch List
        $('#btn-start-scan').click(function () {
            var $btn = $(this);
            var $spinner = $('#scan-spinner');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $('#scan-results-container').hide();
            $('#scan-results-body').empty();

            $.ajax({
                url: nueva_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'nueva_kb_scan_list',
                    nonce: nueva_admin.nonce
                },
                success: function (res) {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');

                    if (res.success) {
                        var items = res.data;
                        if (items.length === 0) {
                            alert('No public content found to scan.');
                            return;
                        }

                        // Render List
                        items.forEach(function (item) {
                            var row = '<tr>' +
                                '<th class="check-column"><input type="checkbox" name="scan_items[]" value="' + item.id + '"></th>' +
                                '<td><strong>' + item.title + '</strong></td>' +
                                '<td>' + item.type + '</td>' +
                                '<td>' + item.date + '</td>' +
                                '<td><a href="' + item.link + '" target="_blank">View</a></td>' +
                                '</tr>';
                            $('#scan-results-body').append(row);
                        });

                        $('#scan-results-container').fadeIn();

                    } else {
                        alert('Error scanning site: ' + res.data);
                    }
                },
                error: function () {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                    alert('Network error while scanning.');
                }
            });
        });

        // 2. Select All
        $('#cb-select-all-scan, #cb-select-all-header').change(function () {
            var checked = $(this).is(':checked');
            $('input[name="scan_items[]"]').prop('checked', checked);
            // Sync both triggers
            $('#cb-select-all-scan, #cb-select-all-header').prop('checked', checked);
        });

        // 3. Import Selected
        $('#btn-import-selected').click(function () {
            var selectedIds = [];
            $('input[name="scan_items[]"]:checked').each(function () {
                selectedIds.push($(this).val());
            });

            if (selectedIds.length === 0) {
                alert('Please select at least one item to import.');
                return;
            }

            if (!confirm('Import ' + selectedIds.length + ' items to Knowledge Base?')) return;

            var $btn = $(this);
            var $spinner = $('#import-spinner');
            $btn.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: nueva_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'nueva_kb_scan_import',
                    nonce: nueva_admin.nonce,
                    ids: selectedIds
                },
                success: function (res) {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                    if (res.success) {
                        alert('Success! Added ' + res.data.added + ' items to the Knowledge Base. Refreshing page...');
                        location.reload();
                    } else {
                        alert('Error importing: ' + res.data);
                    }
                },
                error: function () {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                    alert('Network error while importing.');
                }
            });
        });

        // --- OLD JS LOGIC ---

        // Repeater
        $('#add-repeater-row').click(function () {
            // ... existing repeater logic ...
            var row = '<div class="manual-row" style="background:#f9f9f9; padding:10px; margin-bottom:10px; border:1px solid #e5e5e5;">' +
                '<p><label>Heading</label><br><input type="text" name="manual_heading[]" class="widefat"></p>' +
                '<p><label>Content</label><br><textarea name="manual_paragraph[]" class="widefat" rows="3"></textarea></p>' +
                '<button type="button" class="button button-link-delete remove-row">Remove</button>' +
                '</div>';
            $('#manual-repeater').append(row);
        });

        $(document).on('click', '.remove-row', function () {
            $(this).closest('.manual-row').remove();
        });

        // Edit Handler
        $('.edit-kb').click(function (e) {
            e.preventDefault();
            var id = $(this).data('id');
            var content = $(this).data('content');
            $('#edit_item_id').val(id);
            $('#edit_content').val(content);
            $('#nueva-kb-edit-modal').fadeIn();
        });

        $('#close-edit-modal').click(function () {
            $('#nueva-kb-edit-modal').fadeOut();
        });

        // --- FAQ BUILDER ---
        $('#add-faq-row').click(function () {
            var row = '<div class="faq-row" style="background:#fff; padding:15px; margin-bottom:15px; border:1px solid #ccd0d4; border-left: 4px solid #2271b1;">' +
                '<p><label><strong>Question</strong></label><br><input type="text" name="faq_question[]" class="widefat" required></p>' +
                '<p><label><strong>Answer</strong></label><br><textarea name="faq_answer[]" class="widefat" rows="3" required></textarea></p>' +
                '<button type="button" class="button button-link-delete remove-faq-row">Remove Question</button>' +
                '</div>';
            $('#faq-repeater').append(row);
        });

        $(document).on('click', '.remove-faq-row', function () {
            if (confirm('Remove this question?')) {
                $(this).closest('.faq-row').remove();
            }
        });

        // Init Color Picker
        if ($.fn.wpColorPicker) {
            $('.color-picker').wpColorPicker();
        }
    });
</script>