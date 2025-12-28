<?php

class Nueva_Chatbot_History
{

    public function display_page()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_chat_history';

        // Get distinct sessions with their latest timestamp and meta data
        // This is a bit complex in raw SQL to get 'last' meta, so we'll grab distinct session_ids and then get details
        // Better: Group by session_id

        $query = "SELECT session_id, MAX(timestamp) as last_activity, 
                  (SELECT meta_data FROM $table_name as t2 WHERE t2.session_id = t1.session_id AND sender = 'user' LIMIT 1) as meta_data 
                  FROM $table_name as t1 
                  GROUP BY session_id 
                  ORDER BY last_activity DESC 
                  LIMIT 50";

        $sessions = $wpdb->get_results($query);

        echo '<div class="wrap">';
        echo '<h1>Chat History & Transcripts</h1>';

        if (empty($sessions)) {
            echo '<p>No chats recorded yet.</p>';
            echo '</div>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Session ID</th>';
        echo '<th>Date (Last Active)</th>';
        echo '<th>User IP</th>';
        echo '<th>Actions</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($sessions as $session) {
            $meta = json_decode($session->meta_data, true);
            $ip = isset($meta['ip']) ? $meta['ip'] : 'Unknown';
            $date = $session->last_activity;
            $sid = esc_html($session->session_id);

            echo "<tr>";
            echo "<td>$sid</td>";
            echo "<td>$date</td>";
            echo "<td>$ip</td>";
            echo "<td><button class='button view-transcript' data-session='$sid'>View Transcript</button></td>";
            echo "</tr>";

            // Hidden row for transcript
            echo "<tr id='transcript-$sid' style='display:none;'><td colspan='4'>";
            echo "<div style='background:#f9f9f9; padding:15px; max-height:300px; overflow-y:auto;'>";
            $this->render_transcript($session->session_id);
            echo "</div></td></tr>";
        }

        echo '</tbody></table>';
        echo '</div>';

        // Simple JS for toggling
        ?>
        <script>
            jQuery(document).ready(function ($) {
                $('.view-transcript').click(function () {
                    var sid = $(this).data('session');
                    $('#transcript-' + sid).toggle();
                });
            });
        </script>
        <?php
    }

    private function render_transcript($session_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bua_chat_history';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE session_id = %s ORDER BY timestamp ASC", $session_id));

        foreach ($rows as $row) {
            $sender = $row->sender == 'user' ? '<strong style="color:blue;">User</strong>' : '<strong style="color:green;">Bot</strong>';
            echo "<p>[$row->timestamp] $sender: " . esc_html($row->message) . "</p>";
        }
    }
}
