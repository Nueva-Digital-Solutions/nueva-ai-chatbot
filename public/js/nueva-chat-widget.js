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

    // --- File Attachment Logic (v1.6.0) ---
    var currentAttachment = null;

    // Inject File Input & Icon if not present (handled effectively by CSS/PHP usually, but ensuring dynamic here)
    if ($('#nueva-chat-file').length === 0) {
        $('<input type="file" id="nueva-chat-file" style="display:none;" accept="image/png, image/jpeg, image/webp, application/pdf">').insertAfter($input);
        $('<button type="button" id="nueva-chat-attach" class="nueva-chat-icon-btn"><svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg></button>').insertBefore($input);
    }

    // Attachment Preview Ctn
    var $previewCtn = $('<div id="nueva-attachment-preview" style="display:none; background:#f0f0f1; padding:5px 10px; border-bottom:1px solid #ddd; font-size:12px; display:flex; align-items:center; justify-content:space-between;"></div>');
    $previewCtn.insertBefore('.nueva-chat-footer');
    $previewCtn.hide(); // Force hide initially

    $('#nueva-chat-attach').click(function () {
        $('#nueva-chat-file').click();
    });

    $('#nueva-chat-file').change(function () {
        var file = this.files[0];
        if (!file) return;

        // Validation
        if (file.size > 1048576) { // 1MB
            alert('File is too large. Max 1MB allowed.');
            $(this).val('');
            return;
        }
        var allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (allowed.indexOf(file.type) === -1) {
            alert('Invalid file type. Only JPG, PNG, WEBP, PDF allowed.');
            $(this).val('');
            return;
        }

        // Upload
        var formData = new FormData();
        formData.append('action', 'nueva_upload_file');
        formData.append('file', file);
        formData.append('nonce', nueva_chat_vars.nonce);

        // Disable input while uploading
        $('#nueva-chat-attach').css('opacity', '0.5');

        $.ajax({
            url: nueva_chat_vars.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                $('#nueva-chat-attach').css('opacity', '1');
                if (response.success) {
                    currentAttachment = response.data; // {url, path, type}
                    currentAttachment.name = file.name;
                    showAttachmentPreview();
                } else {
                    alert('Upload failed: ' + (response.data || 'Unknown error'));
                }
            },
            error: function () {
                $('#nueva-chat-attach').css('opacity', '1');
                alert('Upload connection failed.');
            }
        });
    });

    function showAttachmentPreview() {
        if (!currentAttachment) return;
        var iconStr = (currentAttachment.type === 'application/pdf') ? '📄' : '🖼️';
        $previewCtn.html(`<span>${iconStr} ${currentAttachment.name}</span> <span id="nueva-remove-attach" style="cursor:pointer; color:red; font-weight:bold;">×</span>`);
        $previewCtn.slideDown('fast');

        $('#nueva-remove-attach').click(function () {
            currentAttachment = null;
            $('#nueva-chat-file').val('');
            $previewCtn.slideUp('fast');
        });
    }

    // Send Message Logic
    $('#nueva-chat-send').click(function () {
        sendMessage();
    });

    $input.keypress(function (e) {
        if (e.which == 13) sendMessage();
    });

    function sendMessage(text = null) {
        var msg = text || $input.val().trim();
        if (!msg && !currentAttachment) return; // Allow empty msg if attachment exists

        // If attachment, mimic msg content
        if (currentAttachment && !msg) msg = "Sent an attachment.";

        // Append User Msg
        var displayMsg = msg;
        if (currentAttachment) {
            displayMsg += `<br><small><em>[Attachment: ${currentAttachment.name}]</em></small>`;
        }
        appendMessage('user', displayMsg, false, true); // allowHtml true for attachment subtext
        $input.val('');

        // Prepare Payload
        var payload = {
            action: 'nueva_chat_message',
            message: msg,
            session_id: session_id,
            nonce: nueva_chat_vars.nonce
        };

        if (currentAttachment) {
            payload.attachment_path = currentAttachment.path;
            payload.attachment_url = currentAttachment.url;
            payload.attachment_mime = currentAttachment.type;

            // Clear Attachment State
            currentAttachment = null;
            $('#nueva-chat-file').val('');
            $previewCtn.slideUp('fast');
        }

        // Visual Typing Dots
        var loadingId = 'typing-' + Date.now();
        $body.append('<div class="message bot typing" id="' + loadingId + '"><div class="typing-indicator"><span></span><span></span><span></span></div></div>');
        scrollToBottom();

        $.post(nueva_chat_vars.ajax_url, payload, function (response) {
            $('#' + loadingId).remove();
            if (response.success) {
                var reply = response.data.reply;

                // Smart End-Chat Detection (v1.7.0)
                if (reply.indexOf('[END_CHAT]') !== -1) {
                    reply = reply.replace('[END_CHAT]', '').trim();
                    appendMessage('bot', reply);

                    // Trigger End Flow
                    setTimeout(function () {
                        showFeedbackForm();
                    }, 2000); // 2s delay to read goodbye
                } else {
                    appendMessage('bot', reply);
                }
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
                scrollToBottom(false); // Instant scroll for smoothness
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

    function scrollToBottom(animate = true) {
        if (animate) {
            $body.stop().animate({ scrollTop: $body.prop("scrollHeight") }, 500);
        } else {
            $body.scrollTop($body.prop("scrollHeight"));
        }
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

    // --- Inactivity Logic (v1.7.0) ---
    var lastInteraction = Date.now();
    $input.on('keypress click', function () { lastInteraction = Date.now(); });
    $(document).click(function () { if (isOpen) lastInteraction = Date.now(); });

    setInterval(function () {
        if (isOpen && (Date.now() - lastInteraction > 5 * 60 * 1000)) { // 5 mins
            if (!localStorage.getItem('nueva_feedback_shown')) {
                // Auto-trigger end chat flow if not already showing feedback
                appendMessage('bot', 'Session timed out due to inactivity.');
                showFeedbackForm();
            }
        }
    }, 30000); // Check every 30s

    // End Chat Handler
    $('#nueva-chat-end').click(function () {
        $('#nueva-toast-confirm').fadeIn();
    });

    $('#nueva-toast-no').click(function () {
        $('#nueva-toast-confirm').fadeOut();
    });

    $('#nueva-toast-yes').click(function () {
        $('#nueva-toast-confirm').fadeOut();
        showFeedbackForm();
    });

    function showFeedbackForm() {
        if ($('#nueva-feedback-form').length > 0) return;
        localStorage.setItem('nueva_feedback_shown', 'true');

        // Clear Body & Show Form
        $body.html('');

        var html = `
            <div id="nueva-feedback-form" style="height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:20px; text-align:center;">
                <h3>Rate your experience</h3>
                <div class="feedback-emojis" style="font-size:32px; display:flex; gap:15px; margin:20px 0; cursor:pointer;">
                    <span data-val="1" title="Very Dissatisfied">😠</span>
                    <span data-val="2" title="Dissatisfied">😕</span>
                    <span data-val="3" title="Neutral">😐</span>
                    <span data-val="4" title="Satisfied">🙂</span>
                    <span data-val="5" title="Very Satisfied">😄</span>
                </div>
                <textarea id="feedback-reason" placeholder="Any comments? (Optional)" style="width:100%; height:80px; border:1px solid #ddd; padding:10px; border-radius:8px; display:none; margin-bottom:15px;"></textarea>
                <button id="feedback-submit" class="nueva-btn-primary" style="width:100%;" disabled>Submit Feedback</button>
                <button id="feedback-skip" style="background:none; border:none; color:#999; margin-top:10px; cursor:pointer; font-size:12px;">Skip</button>
            </div>
        `;
        $body.append(html);
        $('#nueva-chat-input').prop('disabled', true);

        var selectedRating = 0;
        $('.feedback-emojis span').click(function () {
            $('.feedback-emojis span').css('opacity', '0.4');
            $(this).css('opacity', '1');
            selectedRating = $(this).data('val');
            $('#feedback-submit').prop('disabled', false);

            $('#feedback-reason').show();
            if (selectedRating <= 3) {
                $('#feedback-reason').attr('placeholder', 'What went wrong? (Optional)');
            } else {
                $('#feedback-reason').attr('placeholder', 'Any comments? (Optional)');
            }
        });

        $('#feedback-submit').click(function () {
            var reason = $('#feedback-reason').val();
            submitFeedback(selectedRating, reason);
        });

        $('#feedback-skip').click(function () {
            endSessionFinal();
        });
    }

    function submitFeedback(rating, reason) {
        var $btn = $('#feedback-submit');
        $btn.prop('disabled', true).text('Submitting...');

        $.post(nueva_chat_vars.ajax_url, {
            action: 'nueva_submit_feedback',
            session_id: session_id,
            rating: rating,
            reason: reason,
            nonce: nueva_chat_vars.nonce
        }, function () {
            $btn.text('Submitted!');
            endSessionFinal();
        }).fail(function () {
            $btn.prop('disabled', false).text('Try Again');
            alert('Connection failed. Please try again.');
        });
    }

    function endSessionFinal() {
        // Final Cleanup
        $body.html('<div style="height:100%; display:flex; align-items:center; justify-content:center; flex-direction:column;"><h3>Thank You!</h3><p>Chat ended.</p><button onclick="location.reload()" class="nueva-btn-primary">Start New Chat</button></div>');

        $('#nueva-chat-end').remove(); // Remove end button
        localStorage.removeItem('nueva_chat_session_id');
        localStorage.removeItem('nueva_feedback_shown');
        localStorage.removeItem('nueva_lead_captured');

        // Allow close
        setTimeout(function () {
            // Optional: Auto close?
        }, 5000);

        // Send actual End Chat signal to backend for transcript
        $.post(nueva_chat_vars.ajax_url, {
            action: 'nueva_end_chat',
            session_id: session_id
        });
    }

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
