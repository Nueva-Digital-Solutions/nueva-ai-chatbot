jQuery(document).ready(function ($) {

    // --- Media Uploader Handling (existing) ---
    // ... (retained via existing inline or separate logic if any, but adding here is cleaner) ...

    // --- Notification Polling ---
    var seenNotifications = [];

    function checkNotifications() {
        $.post(nueva_admin.ajax_url, {
            action: 'nueva_check_notifications',
            nonce: nueva_admin.nonce
        }, function (response) {
            if (response.success && response.data.length > 0) {
                response.data.forEach(function (notif) {
                    if (seenNotifications.indexOf(notif.session_id) === -1) {
                        showAdminNotification(notif);
                        seenNotifications.push(notif.session_id);
                    }
                });
            }
        });
    }

    function showAdminNotification(notif) {
        var html = `
        <div class="notice notice-warning is-dismissible nueva-handoff-alert" data-session="${notif.session_id}">
            <p><strong>Nueva AI Alert:</strong> Customer requesting Human Agent!</p>
            <p><em>"${escapeHtml(notif.message)}"</em></p>
            <p>
                <a href="admin.php?page=nueva-ai-history&session_id=${notif.session_id}" class="button button-primary">View Chat</a>
                <button class="button nueva-dismiss-btn">Dismiss</button>
            </p>
        </div>
        `;
        $('.wrap > h1').after(html); // Inject after title

        // Play sound (optional, simple beep)
        // var context = new AudioContext(); ... (skipped for simplicity/browser policy)
    }

    // Dismiss Handler
    $(document).on('click', '.nueva-dismiss-btn', function () {
        var $notice = $(this).closest('.notice');
        var sessionId = $notice.data('session');

        $.post(nueva_admin.ajax_url, {
            action: 'nueva_dismiss_notification',
            session_id: sessionId,
            nonce: nueva_admin.nonce
        });

        $notice.fadeOut();
    });

    // Start Polling (every 15s)
    setInterval(checkNotifications, 15000);

    function escapeHtml(text) {
        if (!text) return "";
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }
});
