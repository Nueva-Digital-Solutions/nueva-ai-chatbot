jQuery(document).ready(function ($) {
    var isOpen = false;
    var $widget = $('.nueva-chat-widget');
    var $window = $('.nueva-chat-window');
    var $body = $('#nueva-chat-body');
    var $input = $('#nueva-chat-input');

    // Session ID Management
    var session_id = localStorage.getItem('nueva_chat_session_id');
    if (!session_id) {
        session_id = 'sess_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        localStorage.setItem('nueva_chat_session_id', session_id);
    }

    // Toggle Chat
    $('.nueva-chat-button, .close-chat').click(function () {
        isOpen = !isOpen;
        if (isOpen) {
            $window.fadeIn('fast');
            $widget.removeClass('closed').addClass('open');
            scrollToBottom();
        } else {
            $window.fadeOut('fast');
            $widget.removeClass('open').addClass('closed');
        }
    });

    // Send Message Logic
    $('#nueva-chat-send').click(function () {
        sendMessage();
    });

    $input.keypress(function (e) {
        if (e.which == 13) sendMessage();
    });

    function sendMessage() {
        var msg = $input.val().trim();
        if (!msg) return;

        // Append User Msg
        $body.append('<div class="message user">' + escapeHtml(msg) + '</div>');
        $input.val('');
        scrollToBottom();

        // AJAX Call to Backend
        $body.append('<div class="message bot typing">Thinking...</div>');
        scrollToBottom();

        $.post(nueva_chat_vars.ajax_url, {
            action: 'nueva_chat_message',
            message: msg,
            session_id: session_id,
            nonce: nueva_chat_vars.nonce
        }, function (response) {
            $('.typing').remove();
            if (response.success) {
                // Parse markdown if possible (simple replacement for now)
                var reply = escapeHtml(response.data.reply).replace(/\n/g, '<br>');
                $body.append('<div class="message bot">' + reply + '</div>');
            } else {
                $body.append('<div class="message bot error">Something went wrong.</div>');
            }
            scrollToBottom();
        }).fail(function () {
            $('.typing').remove();
            $body.append('<div class="message bot error">Connection failed.</div>');
            scrollToBottom();
        });
    }

    function scrollToBottom() {
        $body.animate({ scrollTop: $body.prop("scrollHeight") }, 500);
    }

    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    // End Chat Handler
    $('#nueva-chat-end').click(function () {
        if (confirm("End current chat and email transcript?")) {
            $.post(nueva_chat_vars.ajax_url, {
                action: 'nueva_end_chat',
                session_id: session_id
            }, function (response) {
                $body.append('<div class="message bot">Chat ended. Transcript sent!</div>');
                scrollToBottom();
                // Clear session
                localStorage.removeItem('nueva_chat_session_id');
                // Create new session for next time? Or just leave it.
                session_id = 'sess_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
                localStorage.setItem('nueva_chat_session_id', session_id);
            });
        }
    });

    // --- SECURITY: Branding Integrity Check ---
    setTimeout(function () { // Wait for initial render
        setInterval(function () {
            var link = $('#nueva-branding-link');
            var container = $('.nueva-powered-by');

            // 1. Check existence
            if (link.length === 0 || container.length === 0) {
                console.warn("Nueva Brand Link missing");
                // disablePlugin("Branding removed."); // Relaxed for debugging
                return;
            }

            // 2. Check visibility (rudimentary)
            if (link.css('display') === 'none' || link.css('opacity') == 0 || link.css('visibility') === 'hidden') {
                console.warn("Nueva Brand Link hidden");
                // disablePlugin("Branding hidden."); // Relaxed for debugging
                return;
            }

            if (container.css('display') === 'none' || container.height() < 5) {
                console.warn("Nueva Brand Container hidden");
                // disablePlugin("Branding hidden."); // Relaxed for debugging
                return;
            }

        }, 5000); // Check every 5 seconds
    }, 2000);

    function disablePlugin(reason) {
        if (!$widget.hasClass('disabled-by-security')) {
            $widget.addClass('disabled-by-security');
            $window.html('<div style="padding:20px; color:red; text-align:center;">Chatbot disabled due to license violation: ' + reason + '</div>');
            console.error("Nueva Chatbot Security Violation: " + reason);
        }
    }

});
