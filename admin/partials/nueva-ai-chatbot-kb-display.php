<?php
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

        // 1. Fetch URL
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

    // Complete Site Scan (Pages, Posts, Products)
    if ($_POST['nueva_kb_action'] == 'scan_site') {
        // Get all public post types including custom ones (WooCommerce products are 'product')
        $post_types = get_post_types(array('public' => true), 'names');
        // Exclude attachment, revision, nav_menu_item
        unset($post_types['attachment'], $post_types['revision'], $post_types['nav_menu_item']);

        $args = [
            'post_type' => array_values($post_types),
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ];

        $query = new WP_Query($args);
        $count = 0;

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                // Get Content
                $raw_content = get_the_content();
                $clean_text = strip_tags($raw_content);
                $clean_text = preg_replace('/\s+/', ' ', $clean_text);

                // For Products, maybe add Price/Title explicitly
                if (get_post_type() == 'product') {
                    global $product;
                    $clean_text = "Product: " . get_the_title() . ". Price: " . $product->get_price() . ". Description: " . $clean_text;
                }

                if (!empty($clean_text)) {
                    // Check duplicate source
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE source_ref = %s", get_the_permalink()));

                    if (!$exists) {
                        $wpdb->insert($table_name, [
                            'type' => 'wp_' . get_post_type(),
                            'source_ref' => get_the_permalink(),
                            'content' => trim($clean_text),
                            'created_at' => current_time('mysql')
                        ]);
                        $count++;
                    }
                }
            }
            wp_reset_postdata();
        }
        echo '<div class="notice notice-success"><p>Scan Complete. Added ' . $count . ' new items from ' . implode(', ', $post_types) . '.</p></div>';
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
    });
</script>