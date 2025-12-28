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
            $window.css('display', 'flex').hide().fadeIn('fast', function () {
                $(this).css('display', 'flex');
            });
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
        // Visual Typing Dots
        var loadingId = 'typing-' + Date.now();
        $body.append('<div class="message bot typing" id="' + loadingId + '"><div class="typing-indicator"><span></span><span></span><span></span></div></div>');
        scrollToBottom();

        $.post(nueva_chat_vars.ajax_url, {
            action: 'nueva_chat_message',
            message: msg,
            session_id: session_id,
            nonce: nueva_chat_vars.nonce
        }, function (response) {
            $('#' + loadingId).remove();
            if (response.success) {
                // Parse markdown
                var replyHtml = parseMarkdown(response.data.reply);
                $body.append('<div class="message bot">' + replyHtml + '</div>');
            } else {
                $body.append('<div class="message bot error">Something went wrong.</div>');
            }
            scrollToBottom();
        }).fail(function () {
            $('#' + loadingId).remove();
            $body.append('<div class="message bot error">Connection failed.</div>');
            scrollToBottom();
        });
    }

    // Simple Markdown Parser
    function parseMarkdown(text) {
        if (!text) return '';
        var html = text
            // Escape HTML (security)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            // Bold (**text**)
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            // Italic (*text*)
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            // Links [text](url) - ensure target=_blank
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" style="color:var(--nueva-primary); text-decoration:underline;">$1</a>')
            // Newlines
            .replace(/\n/g, '<br>');
        return html;
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
        // Show Toast
        $('#nueva-toast-confirm').fadeIn();
    });

    // Toast Actions
    $('#nueva-toast-no').click(function () {
        $('#nueva-toast-confirm').fadeOut();
    });

    $('#nueva-toast-yes').click(function () {
        $('#nueva-toast-confirm').fadeOut();

        // Visual feedback
        var $btn = $('#nueva-chat-end');
        $btn.prop('disabled', true).text('Ending...');

        $.post(nueva_chat_vars.ajax_url, {
            action: 'nueva_end_chat',
            session_id: session_id
        }, function (response) {
            // Remove old session ID immediately
            localStorage.removeItem('nueva_chat_session_id');

            $body.append('<div class="message bot">Chat ended. Transcript sent! <br> <a href="#" onclick="location.reload();">Start New Chat</a></div>');
            scrollToBottom();

            // Disable inputs
            $input.prop('disabled', true);
            $('#nueva-chat-send').prop('disabled', true);
            $btn.remove(); // Remove end button
        });
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
