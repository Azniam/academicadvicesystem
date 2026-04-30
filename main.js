// Chatbot functionality
$(document).ready(function() {
    // Create chatbot elements
    $('body').append(`
        <div class="chatbot-icon" id="chatbotIcon">
            <!-- PLACE YOUR CHATBOT IMAGE HERE -->
            <img src="../assets/images/chatbot-icon.png" alt="Chatbot" style="width: 40px; height: 40px; border-radius: 50%;">
        </div>
        <div class="chatbot-window" id="chatbotWindow">
            <div class="chatbot-header bg-primary text-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <!-- PLACE YOUR CHATBOT AVATAR IMAGE HERE -->
                        <img src="../assets/images/chatbot-avatar.png" alt="Chatbot" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;">
                        <h6 class="mb-0">AI Assistant</h6>
                    </div>
                    <button class="btn-close btn-close-white" id="closeChatbot"></button>
                </div>
            </div>
            <div class="chatbot-messages flex-grow-1 p-3" style="height: 380px; overflow-y: auto;">
                <div class="message bot-message mb-2">
                    <div class="d-flex align-items-start">
                        <!-- PLACE YOUR CHATBOT AVATAR IMAGE FOR MESSAGES -->
                        <img src="../assets/images/chatbot-avatar.png" alt="Bot" style="width: 25px; height: 25px; border-radius: 50%; margin-right: 8px;">
                        <div class="bg-light p-2 rounded d-inline-block">
                            Hello! I'm your academic advisor assistant. How can I help you today?
                        </div>
                    </div>
                </div>
            </div>
            <div class="chatbot-input p-3 border-top">
                <div class="input-group">
                    <input type="text" class="form-control" id="chatbotInput" placeholder="Type your question...">
                    <button class="btn btn-primary" id="sendMessage">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    `);
    
    // Toggle chatbot window
    $('#chatbotIcon').click(function() {
        $('#chatbotWindow').toggleClass('show');
    });
    
    $('#closeChatbot').click(function() {
        $('#chatbotWindow').removeClass('show');
    });
    
    // Send message
    $('#sendMessage').click(function() {
        sendMessage();
    });
    
    $('#chatbotInput').keypress(function(e) {
        if(e.which == 13) {
            sendMessage();
        }
    });
    
    function sendMessage() {
        var message = $('#chatbotInput').val().trim();
        if(message == '') return;
        
        // Add user message
        $('.chatbot-messages').append(`
            <div class="message user-message mb-2 text-end">
                <div class="bg-primary text-white p-2 rounded d-inline-block">
                    ${escapeHtml(message)}
                </div>
            </div>
        `);
        
        $('#chatbotInput').val('');
        $('.chatbot-messages').scrollTop($('.chatbot-messages')[0].scrollHeight);
        
        // Send to server
        $.ajax({
            url: 'api/chatbot.php',
            method: 'POST',
            data: { message: message },
            dataType: 'json',
            success: function(data) {
                $('.chatbot-messages').append(`
                    <div class="message bot-message mb-2">
                        <div class="d-flex align-items-start">
                            <!-- PLACE YOUR CHATBOT AVATAR IMAGE FOR RESPONSES -->
                            <img src="../assets/images/chatbot-avatar.png" alt="Bot" style="width: 25px; height: 25px; border-radius: 50%; margin-right: 8px;">
                            <div class="bg-light p-2 rounded d-inline-block">
                                ${escapeHtml(data.response)}
                            </div>
                        </div>
                    </div>
                `);
                $('.chatbot-messages').scrollTop($('.chatbot-messages')[0].scrollHeight);
            }
        });
    }
    
    function escapeHtml(text) {
        return text.replace(/[&<>]/g, function(m) {
            if(m === '&') return '&amp;';
            if(m === '<') return '&lt;';
            if(m === '>') return '&gt;';
            return m;
        });
    }
});