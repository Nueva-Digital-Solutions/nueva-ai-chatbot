<?php
global $wpdb;
$table_name = $wpdb->prefix . 'bua_chat_flows';

// Handle Save
if (isset($_POST['nueva_flow_action']) && check_admin_referer('nueva_flow_verify')) {
    $title = sanitize_text_field($_POST['flow_title']);
    $json = wp_unslash($_POST['flow_json']); // Allow JSON structure
    $keywords = sanitize_text_field($_POST['flow_keywords']);

    $wpdb->insert($table_name, [
        'title' => $title,
        'flow_json' => $json,
        'trigger_keywords' => $keywords,
        'is_active' => 1,
        'created_at' => current_time('mysql')
    ]);
    echo '<div class="notice notice-success"><p>Flow saved successfully.</p></div>';
}

$flows = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
?>

<div class="wrap">
    <h1>Chat Flows</h1>
    <div style="margin-bottom: 20px;">
        <!-- Placeholder for Auto-Generate -->
        <button class="button button-secondary" disabled>Auto-Generate Flow (Coming Soon)</button>
    </div>

    <div style="display:flex; gap:20px;">
        <div style="flex:1;">
            <h3>Create New Flow</h3>
            <form method="post">
                <?php wp_nonce_field('nueva_flow_verify'); ?>
                <input type="hidden" name="nueva_flow_action" value="save">
                <table class="form-table">
                    <tr>
                        <th>Flow Title</th>
                        <td><input type="text" name="flow_title" class="widefat" required></td>
                    </tr>
                    <tr>
                        <th>Trigger Keywords</th>
                        <td><input type="text" name="flow_keywords" class="widefat"
                                placeholder="pricing, support, hello"></td>
                    </tr>
                    <tr>
                        <th>Flow JSON Structure</th>
                        <td>
                            <textarea name="flow_json" class="widefat" rows="10"
                                placeholder='{"start": "Welcome!", "options": [{"label": "Sales", "next": "sales_node"}]}'></textarea>
                            <p class="description">Enter the flow logic in JSON format.</p>
                        </td>
                    </tr>
                </table>
                <input type="submit" class="button button-primary" value="Save Flow">
            </form>
        </div>

        <div style="flex:1;">
            <h3>Existing Flows</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Keywords</th>
                        <th>Active</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($flows):
                        foreach ($flows as $flow): ?>
                            <tr>
                                <td><?php echo $flow->title; ?></td>
                                <td><?php echo $flow->trigger_keywords; ?></td>
                                <td><?php echo $flow->is_active ? 'Yes' : 'No'; ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="3">No flows found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>