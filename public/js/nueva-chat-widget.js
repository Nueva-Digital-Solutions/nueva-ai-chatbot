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

    function sendMessage(text = null) {
        var msg = text || $input.val().trim();
        if (!msg) return;

        // Append User Msg
        appendMessage('user', msg);
        $input.val('');

        // Visual Typing Dots
        var loadingId = 'typing-' + Date.now();
        $body.append('<div class="nueva-chat-message bot typing" id="' + loadingId + '"><div class="typing-indicator"><span></span><span></span><span></span></div></div>');
        scrollToBottom();

        $.post(nueva_chat_vars.ajax_url, {
            action: 'nueva_chat_message',
            message: msg,
            session_id: session_id,
            nonce: nueva_chat_vars.nonce
        }, function (response) {
            $('#' + loadingId).remove();
            if (response.success) {
                appendMessage('bot', response.data.reply);
            } else {
                appendMessage('bot', 'Something went wrong.');
            }
        }).fail(function () {
            $('#' + loadingId).remove();
            appendMessage('bot', 'Connection failed.');
        });
    }

    // --- Suggestion Parsing ---
    function parseSuggestions(text) {
        // Match [SUGGESTIONS] followed by a JSON array, spanning multiple lines
        const regex = /\[SUGGESTIONS\]\s*(\[[\s\S]*?\])/i;
        const match = text.match(regex);
        if (match) {
            try {
                // sanitize smart quotes just in case
                let jsonStr = match[1].replace(/[\u201C\u201D]/g, '"');
                const suggestions = JSON.parse(jsonStr);
                return {
                    cleanText: text.replace(regex, '').trim(),
                    suggestions: suggestions
                };
            } catch (e) {
                console.warn('Nueva AI: Failed to parse suggestions', e);
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

        // 1. Escape HTML first (Security)
        displayText = escapeHtml(displayText);

        // 2. Simple Markdown
        let formattedText = displayText
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" style="color:inherit; text-decoration:underline;">$1</a>')
            .replace(/\n/g, '<br>');

        const msgHtml = `<div class="nueva-chat-message ${sender}" style="background-color: ${sender === 'user' ? nueva_chat_vars.primary_col : '#f1f1f1'}; color: ${sender === 'user' ? '#fff' : '#000'};">
            ${formattedText}
        </div>`;

        $body.append(msgHtml);

        // Render Suggestions Chips
        if (suggestions.length > 0) {
            const $chips = $('<div class="nueva-suggestions" style="margin-top:5px; display:flex; gap:5px; flex-wrap:wrap; padding: 0 10px; margin-bottom:10px;"></div>');
            suggestions.forEach(s => {
                const $btn = $(`<button style="font-size:12px; padding:6px 12px; background:#fff; border:1px solid ${nueva_chat_vars.primary_col}; color:${nueva_chat_vars.primary_col}; border-radius:15px; cursor:pointer; font-weight:500; transition:all 0.2s;">${s}</button>`);

                $btn.hover(
                    function () { $(this).css({ background: nueva_chat_vars.primary_col, color: '#fff' }); },
                    function () { $(this).css({ background: '#fff', color: nueva_chat_vars.primary_col }); }
                );

                $btn.click(function () {
                    sendMessage(s);
                    $(this).parent().fadeOut(); // Optional: Hide suggestions after click
                });
                $chips.append($btn);
            });
            $body.append($chips);
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
