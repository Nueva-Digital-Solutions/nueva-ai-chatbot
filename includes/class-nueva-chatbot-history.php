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

                // Admin Feedback Save
                $('.save-admin-feedback').click(function(e) {
                    e.preventDefault();
                    var btn = $(this);
                    var container = btn.closest('.nueva-admin-feedback-box');
                    var sid = container.data('session');
                    var rating = container.find('.admin-rating-select').val();
                    var feedback = container.find('.admin-feedback-text').val();
                    var msgSpan = container.find('.feedback-msg');
                    var spinner = container.find('.spinner');

                    msgSpan.text('').removeClass('notice-success notice-error');
                    spinner.addClass('is-active');
                    btn.prop('disabled', true);

                    $.post(nueva_admin.ajax_url, {
                        action: 'nueva_save_admin_feedback',
                        nonce: nueva_admin.nonce,
                        session_id: sid,
                        admin_rating: rating,
                        admin_feedback: feedback
                    }, function(res) {
                        spinner.removeClass('is-active');
                        btn.prop('disabled', false);
                        if (res.success) {
                            msgSpan.text(res.data).css('color', 'green');
                        } else {
                            msgSpan.text(res.data).css('color', 'red');
                        }
                    });
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

        // --- Admin Feedback Section ---
        $fb_table = $wpdb->prefix . 'bua_chat_feedback';
        $feedback = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fb_table WHERE session_id = %s", $session_id));

        $admin_rating = isset($feedback->admin_rating) ? $feedback->admin_rating : 0;
        $admin_feedback = isset($feedback->admin_feedback) ? $feedback->admin_feedback : '';

        echo '<hr>';
        echo '<h3>Admin Feedback (AI Training)</h3>';
        echo '<div class="nueva-admin-feedback-box" data-session="' . esc_attr($session_id) . '">';

        echo '<label><strong>Rate Chat Quality (1-5):</strong></label><br>';
        echo '<select class="admin-rating-select">';
        echo '<option value="0">Select Rating</option>';
        for ($i = 1; $i <= 5; $i++) {
            $sel = ($admin_rating == $i) ? 'selected' : '';
            echo "<option value='$i' $sel>$i Stars</option>";
        }
        echo '</select><br><br>';

        echo '<label><strong>Suggestions for AI:</strong></label><br>';
        echo '<textarea class="admin-feedback-text large-text" rows="3" placeholder="What should the AI have done differently?">' . esc_textarea($admin_feedback) . '</textarea><br><br>';

        echo '<button type="button" class="button button-primary save-admin-feedback">Save Feedback</button>';
        echo '<span class="spinner" style="float:none;"></span> <span class="feedback-msg"></span>';
        echo '</div>';
    }
}
