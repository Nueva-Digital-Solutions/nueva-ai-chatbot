<?php
defined('ABSPATH') || exit;

// Feedback List Display
global $wpdb;
$table_name = $wpdb->prefix . 'bua_chat_feedback';
$table_history = $wpdb->prefix . 'bua_chat_history';

// Pagination
$per_page = 20;
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($page - 1) * $per_page;

$total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
$feedback_items = $wpdb->get_results("
    SELECT f.*, 
    (SELECT COUNT(*) FROM $table_history h WHERE h.session_id = f.session_id) as msg_count 
    FROM $table_name f 
    ORDER BY f.created_at DESC 
    LIMIT $per_page OFFSET $offset
");

$total_pages = ceil($total_items / $per_page);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">User Feedback</h1>
    <hr class="wp-header-end">

    <div class="tablenav top">
        <div class="alignleft actions">
            <!-- Filter placeholder -->
        </div>
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo $total_items; ?> items</span>
            <span class="pagination-links">
                <?php if ($page > 1): ?>
                    <a class="prev-page button" href="?page=nueva-ai-feedback&paged=<?php echo $page - 1; ?>">‹</a>
                <?php endif; ?>
                <span class="paging-input">Page <?php echo $page; ?> of <span
                        class="total-pages"><?php echo $total_pages; ?></span></span>
                <?php if ($page < $total_pages): ?>
                    <a class="next-page button" href="?page=nueva-ai-feedback&paged=<?php echo $page + 1; ?>">›</a>
                <?php endif; ?>
            </span>
        </div>
        <br class="clear">
    </div>

    <table class="wp-list-table widefat fixed striped table-view-list">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-primary" style="width:50px;">ID</th>
                <th scope="col" class="manage-column">Session ID</th>
                <th scope="col" class="manage-column" style="width:100px;">Rating</th>
                <th scope="col" class="manage-column">Reason / Comment</th>
                <th scope="col" class="manage-column" style="width:150px;">Date</th>
                <th scope="col" class="manage-column" style="width:100px;">Msgs</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($feedback_items)): ?>
                <?php foreach ($feedback_items as $item): ?>
                    <?php
                    $stars = str_repeat('⭐', $item->rating);
                    $color = ($item->rating >= 4) ? 'green' : (($item->rating <= 2) ? 'red' : 'gray');
                    ?>
                    <tr>
                        <td><?php echo $item->id; ?></td>
                        <td><code><?php echo esc_html($item->session_id); ?></code></td>
                        <td><span style="color:<?php echo $color; ?>; font-size:14px;"><?php echo $stars; ?></span>
                            (<?php echo $item->rating; ?>)</td>
                        <td><?php echo esc_html($item->reason ?: '-'); ?></td>
                        <td><?php echo $item->created_at; ?></td>
                        <td><?php echo $item->msg_count; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No feedback received yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>