<?php
global $wpdb;
$table_name = $wpdb->prefix . 'bua_leads';
$leads = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
?>

<div class="wrap">
    <h1>Collected Leads</h1>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Session ID</th>
                <th>Data</th>
                <th>Synced?</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($leads):
                foreach ($leads as $lead): ?>
                    <tr>
                        <td><?php echo $lead->id; ?></td>
                        <td><?php echo $lead->collected_at; ?></td>
                        <td><?php echo $lead->chat_session_id; ?></td>
                        <td>
                            <?php
                            $data = json_decode($lead->user_data, true);
                            if ($data) {
                                foreach ($data as $k => $v) {
                                    echo "<strong>$k:</strong> $v<br>";
                                }
                            } else {
                                echo $lead->user_data;
                            }
                            ?>
                        </td>
                        <td><?php echo $lead->is_synced ? 'Yes' : 'No'; ?></td>
                    </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="5">No leads found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>