<?php
// Dashboard Logic
global $wpdb;

// 1. Total Conversations (Unique sessions in History)
$table_history = $wpdb->prefix . 'bua_chat_history';
$total_chats = $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM $table_history");

// 2. Feedback Stats
$table_feedback = $wpdb->prefix . 'bua_chat_feedback';
$total_feedback = $wpdb->get_var("SELECT COUNT(*) FROM $table_feedback");
$avg_rating = $wpdb->get_var("SELECT AVG(rating) FROM $table_feedback");
$avg_rating = number_format($avg_rating ?: 0, 1);

// Distributions (1-5)
$ratings_dist = $wpdb->get_results("SELECT rating, COUNT(*) as count FROM $table_feedback GROUP BY rating ORDER BY rating ASC");
$dist_map = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($ratings_dist as $row) {
    $dist_map[$row->rating] = $row->count;
}
$dist_json = json_encode(array_values($dist_map));

// 3. Leads Count
$table_leads = $wpdb->prefix . 'bua_leads';
$total_leads = $wpdb->get_var("SELECT COUNT(*) FROM $table_leads");

// 4. Daily Chats (Last 7 Days)
$daily_chats = $wpdb->get_results("
    SELECT DATE(timestamp) as date, COUNT(DISTINCT session_id) as count 
    FROM $table_history 
    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
    GROUP BY DATE(timestamp) 
    ORDER BY date ASC
");
$dates = [];
$counts = [];
foreach ($daily_chats as $day) {
    $dates[] = date('M d', strtotime($day->date));
    $counts[] = $day->count;
}
$dates_json = json_encode($dates);
$counts_json = json_encode($counts);

?>

<div class="wrap" style="font-family: 'Segoe UI', sans-serif;">
    <h1 class="wp-heading-inline">Nueva AI Dashboard</h1>
    <p class="description">Overview of your chatbot's performance and user feedback.</p>
    <hr class="wp-header-end">

    <div
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
        <!-- KPI Cards -->
        <div
            style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #dcdcde; border-left: 4px solid #0073aa;">
            <h3 style="margin:0; font-size:14px; color:#646970; text-transform:uppercase;">Total Conversations</h3>
            <p style="font-size:32px; font-weight:bold; margin:10px 0; color:#1d2327;"><?php echo $total_chats; ?></p>
        </div>

        <div
            style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #dcdcde; border-left: 4px solid #00a32a;">
            <h3 style="margin:0; font-size:14px; color:#646970; text-transform:uppercase;">Avg Satisfaction</h3>
            <p style="font-size:32px; font-weight:bold; margin:10px 0; color:#1d2327;"><?php echo $avg_rating; ?> <span
                    style="font-size:16px; color:#aaa;">/ 5.0</span></p>
        </div>

        <div
            style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #dcdcde; border-left: 4px solid #dba617;">
            <h3 style="margin:0; font-size:14px; color:#646970; text-transform:uppercase;">Total Feedback</h3>
            <p style="font-size:32px; font-weight:bold; margin:10px 0; color:#1d2327;"><?php echo $total_feedback; ?>
            </p>
        </div>

        <div
            style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #dcdcde; border-left: 4px solid #d63638;">
            <h3 style="margin:0; font-size:14px; color:#646970; text-transform:uppercase;">Leads Captured</h3>
            <p style="font-size:32px; font-weight:bold; margin:10px 0; color:#1d2327;"><?php echo $total_leads; ?></p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 30px;">
        <!-- Chart 1: Daily Activity -->
        <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #dcdcde;">
            <h3 style="margin-top:0;">Chat Activity (Last 7 Days)</h3>
            <canvas id="nuevaChatChart" height="150"></canvas>
        </div>

        <!-- Chart 2: Feedback Distribution -->
        <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #dcdcde;">
            <h3 style="margin-top:0;">Feedback Distribution</h3>
            <canvas id="nuevaFeedbackChart" height="200"></canvas>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Line Chart
        const ctx1 = document.getElementById('nuevaChatChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: <?php echo $dates_json; ?>,
                datasets: [{
                    label: 'Unique Sessions',
                    data: <?php echo $counts_json; ?>,
                    borderColor: '#0073aa',
                    backgroundColor: 'rgba(0, 115, 170, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });

        // 2. Bar Chart (Feedback)
        const ctx2 = document.getElementById('nuevaFeedbackChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                datasets: [{
                    data: <?php echo $dist_json; ?>,
                    backgroundColor: [
                        '#d63638', // Red
                        '#dba617', // Orange
                        '#f0f0f1', // Grey
                        '#00a32a', // Green
                        '#2271b1'  // Blue
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    });
</script>