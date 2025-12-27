jQuery(document).ready(function($){
    var isOpen = false;
    var $widget = $('.nueva-chat-widget');
    var $window = $('.nueva-chat-window');
    var $body = $('#nueva-chat-body');
    var $input = $('#nueva-chat-input');
    
    // Toggle Chat
    $('.nueva-chat-button, .close-chat').click(function(){
        isOpen = !isOpen;
        if(isOpen) {
            $window.fadeIn('fast');
            $widget.removeClass('closed').addClass('open');
        } else {
            $window.fadeOut('fast');
            $widget.removeClass('open').addClass('closed');
        }
    });

    // Send Message Logic
    $('#nueva-chat-send').click(function(){
        sendMessage();
    });

    $input.keypress(function(e){
        if(e.which == 13) sendMessage();
    });

    function sendMessage() {
        var msg = $input.val().trim();
        if(!msg) return;

        // Append User Msg
        $body.append('<div class="message user">' + escapeHtml(msg) + '</div>');
        $input.val('');
        scrollToBottom();

        // AJAX Call to Backend
        // For now, simple echo or loading state
        $body.append('<div class="message bot typing">Thinking...</div>');
        scrollToBottom();

        // Placeholder for future API integration
        /*
        $.post(nueva_chat_vars.ajax_url, {
            action: 'nueva_chat_message',
            message: msg,
            nonce: nueva_chat_vars.nonce
        }, function(response) {
            $('.typing').remove();
            $body.append('<div class="message bot">' + response.data.reply + '</div>');
            scrollToBottom();
        });
        */
       
       // Temporary timeout to simulate response
       setTimeout(function(){
           $('.typing').remove();
           $body.append('<div class="message bot">I am the Nueva AI Agent. My brain is not fully connected yet, but I hear you: ' + escapeHtml(msg) + '</div>');
           scrollToBottom();
       }, 1000);
    }

    function scrollToBottom() {
        $body.scrollTop($body[0].scrollHeight);
    }

    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // --- SECURITY: Branding Integrity Check ---
    setInterval(function(){
        var link = $('#nueva-branding-link');
        var container = $('.nueva-powered-by');
        
        // 1. Check existence
        if(link.length === 0 || container.length === 0) {
            disablePlugin("Branding removed.");
            return;
        }

        // 2. Check visibility (rudimentary)
        if(link.css('display') === 'none' || link.css('opacity') == 0 || link.css('visibility') === 'hidden') {
            disablePlugin("Branding hidden.");
            return;
        }
        
        if(container.css('display') === 'none' || container.height() < 5) {
             disablePlugin("Branding hidden.");
             return;
        }

    }, 3000); // Check every 3 seconds

    function disablePlugin(reason) {
        if(!$widget.hasClass('disabled-by-security')) {
            $widget.addClass('disabled-by-security');
            $window.html('<div style="padding:20px; color:red; text-align:center;">Chatbot disabled due to license violation: ' + reason + '</div>');
            console.error("Nueva Chatbot Security Violation: " + reason);
        }
    }

});
