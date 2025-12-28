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

    function appendMessage(sender, text, hidden = false, allowHtml = false) {
        if (hidden) return;

        let displayText = text;
        let suggestions = [];

        if (sender === 'bot') {
            const parsed = parseSuggestions(text);
            displayText = parsed.cleanText;
            suggestions = parsed.suggestions;
        }

        // 1. Escape HTML first (Security) - Skip if allowed
        if (!allowHtml) {
            displayText = escapeHtml(displayText);
        }

        // 2. Simple Markdown
        let formattedText = displayText
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" style="color:inherit; text-decoration:underline;">$1</a>')
            .replace(/\n/g, '<br>');

        // HTML Construction
        // Fix: Use 'message' class to match CSS. 
        // Only apply inline styles for USER (primary color). Let CSS handle BOT (white).
        let inlineStyle = '';
        if (sender === 'user') {
            inlineStyle = `style="background-color: ${nueva_chat_vars.primary_col}; color: #fff;"`;
        }

        const $msgContainer = $(`<div class="message ${sender}" ${inlineStyle}></div>`);
        $body.append($msgContainer);

        if (sender === 'bot') {
            typeWriter($msgContainer, formattedText, function () {
                if (suggestions.length > 0) {
                    renderSuggestions(suggestions);
                }
            });
        } else {
            $msgContainer.html(formattedText);
            scrollToBottom();
        }
    }

    function typeWriter($element, html, onComplete) {
        let i = 0;
        let speed = 15; // ms per char

        function type() {
            if (i < html.length) {
                let char = html.charAt(i);

                // Fast-forward tags to render valid HTML
                if (char === '<') {
                    let closingIdx = html.indexOf('>', i);
                    if (closingIdx !== -1) {
                        i = closingIdx + 1;
                    } else {
                        i++;
                    }
                } else {
                    i++;
                }

                $element.html(html.substring(0, i));
                scrollToBottom();
                setTimeout(type, speed);
            } else {
                if (onComplete) onComplete();
            }
        }
        type();
    }

    function renderSuggestions(suggestions) {
        if (suggestions.length > 0) {
            const $chips = $('<div class="nueva-suggestions" style="margin-top:5px; display:flex; gap:5px; flex-wrap:wrap; padding: 0 10px; margin-bottom:10px;"></div>');
            suggestions.forEach(s => {
                // Style: Accent color border/text, White background. No hover logic.
                const $btn = $(`<button style="font-size:12px; padding:6px 12px; background:${nueva_chat_vars.secondary_col || '#fff'}; border:1px solid ${nueva_chat_vars.accent_col}; color:${nueva_chat_vars.accent_col}; border-radius:15px; cursor:pointer; font-weight:500;">${s}</button>`);

                $btn.click(function () {
                    sendMessage(s);
                    $(this).parent().fadeOut(); // Optional: Hide suggestions after click
                });
                $chips.append($btn);
            });
            $body.append($chips);
            scrollToBottom();
        }
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

            appendMessage('bot', 'Chat ended. Transcript sent! <br> <a href="#" onclick="location.reload();">Start New Chat</a>', false, true);

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
