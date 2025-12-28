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

            checkLeadGate(); // Trigger Gate Check
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

    // --- Lead Gate Logic (v1.5.0) ---
    function checkLeadGate() {
        if (!nueva_chat_vars.lead_mode || nueva_chat_vars.lead_mode !== 'gate') return false;

        // Check if already captured
        if (localStorage.getItem('nueva_lead_captured')) return false;
        if (nueva_chat_vars.is_logged_in === '1' && nueva_chat_vars.lead_skip_logged_in === '1') return false;

        // Show Gate
        showGateForm();
        return true;
    }

    function showGateForm() {
        // Simple overlay form
        const html = `
            <div id="nueva-lead-gate" style="position:absolute; top:0; left:0; width:100%; height:100%; background:#fff; z-index:110; padding:20px; display:flex; flex-direction:column; justify-content:center;">
                <h3 style="text-align:center;">${nueva_chat_vars.gate_title || 'Welcome!'}</h3>
                <p style="text-align:center; color:#666; margin-bottom:20px;">Please introduce yourself to start chatting.</p>
                <input type="text" id="gate-name" placeholder="Your Name" style="width:100%; padding:10px; margin-bottom:10px; border:1px solid #ddd; border-radius:4px;">
                <input type="email" id="gate-email" placeholder="Your Email" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ddd; border-radius:4px;">
                <button id="gate-submit" style="background:${nueva_chat_vars.primary_col}; color:#fff; border:none; padding:12px; border-radius:4px; font-weight:bold; cursor:pointer;">${nueva_chat_vars.gate_btn || 'Start Chat'}</button>
            </div>
        `;
        $('#nueva-chat-body').append(html);

        $('#gate-submit').click(function () {
            const name = $('#gate-name').val();
            const email = $('#gate-email').val();
            if (name && email) {
                // Save locally & Send hidden message
                localStorage.setItem('nueva_lead_captured', 'true');
                $('#nueva-lead-gate').remove();

                // Greeting
                appendMessage('user', `Hi, I'm ${name} (${email})`, true); // Hidden
                sendMessage(`Hi, I'm ${name}.`); // Visible greeting
            } else {
                alert('Please fill in all fields.');
            }
        });
    }

    // --- Suggestion Parsing ---
    function parseSuggestions(text) {
        const regex = /\[SUGGESTIONS\]\s*(\[.*?\])/s;
        const match = text.match(regex);
        if (match) {
            try {
                const suggestions = JSON.parse(match[1]);
                return {
                    cleanText: text.replace(regex, '').trim(),
                    suggestions: suggestions
                };
            } catch (e) {
                return { cleanText: text, suggestions: [] };
            }
        }
        return { cleanText: text, suggestions: [] };
    }

    function appendMessage(sender, text, hidden = false) {
        if (hidden) return;

        let displayText = text;
        let suggestions = [];

        if (sender === 'bot') {
            const parsed = parseSuggestions(text);
            displayText = parsed.cleanText;
            suggestions = parsed.suggestions;
        }

        // Markdown (Simple)
        let formattedText = displayText
            .replace(/\*\*(.*?)\*\*/g, '<b>$1</b>')
            .replace(/\*(.*?)\*/g, '<i>$1</i>')
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');

        const msgHtml = `<div class="nueva-chat-message ${sender}" style="background-color: ${sender === 'user' ? nueva_chat_vars.primary_col : '#f1f1f1'}; color: ${sender === 'user' ? '#fff' : '#000'};">
            ${formattedText}
        </div>`;

        $('#nueva-chat-messages').append(msgHtml);

        // Render Suggestions Chips
        if (suggestions.length > 0) {
            const $chips = $('<div class="nueva-suggestions" style="margin-top:5px; display:flex; gap:5px; flex-wrap:wrap;"></div>');
            suggestions.forEach(s => {
                const $btn = $(`<button style="font-size:12px; padding:4px 8px; background:#e0e0e0; border:none; border-radius:12px; cursor:pointer; color:#333;">${s}</button>`);
                $btn.click(function () {
                    sendMessage(s);
                });
                $chips.append($btn);
            });
            $('#nueva-chat-messages').append($chips);
        }

        scrollToBottom();
    }
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
