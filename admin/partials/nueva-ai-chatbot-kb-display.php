<?php
// Handle Actions
if (isset($_POST['nueva_kb_action']) && check_admin_referer('nueva_kb_verify')) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bua_knowledge_base';

    // Add URL
    if ($_POST['nueva_kb_action'] == 'add_url') {
        $url = esc_url_raw($_POST['kb_url']);
        // Placeholder text extraction
        $content = "Content scraped from $url (Placeholder)";
        $wpdb->insert($table_name, [
            'type' => 'url',
            'source_ref' => $url,
            'content' => $content,
            'created_at' => current_time('mysql')
        ]);
        echo '<div class="notice notice-success"><p>URL added to Knowledge Base.</p></div>';
    }

    // Manual Entry
    if ($_POST['nueva_kb_action'] == 'add_manual') {
        $data = [];
        $headings = $_POST['manual_heading'];
        $paragraphs = $_POST['manual_paragraph'];
        if (is_array($headings)) {
            foreach ($headings as $index => $heading) {
                $data[] = [
                    'heading' => sanitize_text_field($heading),
                    'content' => sanitize_textarea_field($paragraphs[$index])
                ];
            }
        }
        $wpdb->insert($table_name, [
            'type' => 'structured_manual',
            'source_ref' => 'Manual Entry ' . date('Y-m-d H:i'),
            'content' => json_encode($data), // Searchable content handling needed later
            'raw_data' => json_encode($data),
            'created_at' => current_time('mysql')
        ]);
        echo '<div class="notice notice-success"><p>Manual entry added.</p></div>';
    }

    // Site Scan
    if ($_POST['nueva_kb_action'] == 'scan_site') {
        $args = ['post_type' => ['post', 'page'], 'posts_per_page' => -1, 'post_status' => 'publish'];
        $query = new WP_Query($args);
        $count = 0;
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $content = strip_tags(get_the_content());
                $wpdb->insert($table_name, [
                    'type' => 'wp_post',
                    'source_ref' => get_the_ID(),
                    'content' => $content,
                    'created_at' => current_time('mysql')
                ]);
                $count++;
            }
        }
        echo '<div class="notice notice-success"><p>Scanned ' . $count . ' posts/pages.</p></div>';
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
                            <td><button class="button button-small delete-kb" data-id="<?php echo $item->id; ?>">Delete</button>
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
                        <submit class="button button-primary">Fetch & Save</submit>
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

    <!-- Scan Site -->
    <div id="tab-scan" class="tab-content" style="display:none;">
        <form method="post">
            <?php wp_nonce_field('nueva_kb_verify'); ?>
            <input type="hidden" name="nueva_kb_action" value="scan_site">
            <p>This will scan all published Pages and Posts and add their text content to the knowledge base.</p>
            <input type="submit" class="button button-primary" value="Start Scan">
        </form>
    </div>

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

        $('#add-repeater-row').click(function () {
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
    });
</script>