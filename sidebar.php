<?php
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(180deg, #00A7E1 0%, #F17720 100%);
            color: white;
            transition: all 0.3s;
            z-index: 1000;
        }
        .sidebar .logo-area {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar .logo-area .layered-logo {
            position: relative;
            display: inline-block;
        }
        .sidebar .logo-area .layered-logo .logo-back {
            position: absolute;
            top: -20px;
            left: -30px;
            opacity: 0.5;
        }
        .sidebar .logo-area .layered-logo .logo-front {
            position: relative;
            z-index: 1;
        }
        .sidebar .user-id {
            background: rgba(255,255,255,0.1);
            padding: 8px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 0.85rem;
            text-align: center;
        }
        .sidebar .nav-menu {
            padding: 20px 0;
        }
        .sidebar .nav-item {
            list-style: none;
            margin: 5px 15px;
        }
        .sidebar .nav-link {
            color: #e2e8f0;
            padding: 12px 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s;
            text-decoration: none;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        .sidebar .nav-link.active {
            background: white;
            color: #00A7E1;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .sidebar .nav-link.active i {
            color: #F17720;
        }
        .sidebar .nav-link i {
            width: 24px;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        .main-content {
            margin-left: 280px;
            padding: 20px;
            background: #f5f7fa;
            min-height: 100vh;
        }
        .chatbot-icon {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            z-index: 1000;
            transition: all 0.3s;
        }
        .chatbot-icon:hover {
            transform: scale(1.1);
        }
        .chatbot-icon i {
            font-size: 28px;
            color: white;
        }
        .chatbot-window {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 350px;
            height: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: none;
            flex-direction: column;
            z-index: 1001;
            overflow: hidden;
        }
        .chatbot-window.show {
            display: flex;
        }
        .chatbot-messages {
            flex-grow: 1;
            overflow-y: auto;
            padding: 15px;
        }
        .chatbot-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        .typing-indicator {
            display: inline-block;
            background: #e9ecef;
            border-radius: 15px;
            padding: 10px 15px;
        }
        .typing-indicator span {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #6c757d;
            margin: 0 2px;
            animation: typing 1.4s infinite ease-in-out;
        }
        .typing-indicator span:nth-child(1) { animation-delay: 0s; }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
            30% { transform: translateY(-5px); opacity: 1; }
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .chatbot-window {
                width: 300px;
                height: 450px;
                bottom: 80px;
                right: 15px;
            }
            .chatbot-icon {
                bottom: 15px;
                right: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="logo-area">
            <div class="layered-logo">
                <img src="../assets/images/logo-back.png" alt="Back Logo" class="logo-back" style="width: 150px; height: 150px; object-fit: contain;">
                <img src="../assets/images/logo-front.png" alt="Front Logo" class="logo-front" style="width: 145px; height: 145px; object-fit: contain;">
            </div>
            <div class="user-id">
                <?php if($role == 'admin'): ?>
                    <i class="fas fa-user-shield me-2"></i>
                <?php elseif($role == 'professor'): ?>
                    <i class="fas fa-chalkboard-teacher me-2"></i>
                <?php elseif($role == 'student'): ?>
                    <i class="fas fa-user-graduate me-2"></i>
                <?php endif; ?>
                <?php echo htmlspecialchars($user_id); ?>
            </div>
        </div>
        <ul class="nav-menu">
            <?php if($role == 'student'): ?>
                <li class="nav-item"><a href="dashboard.php" class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-home"></i>Home / Dashboard</a></li>
                <li class="nav-item"><a href="information.php" class="nav-link <?php echo ($current_page == 'information.php') ? 'active' : ''; ?>"><i class="fas fa-user"></i>Student Information</a></li>
                <li class="nav-item"><a href="prospectus.php" class="nav-link <?php echo ($current_page == 'prospectus.php') ? 'active' : ''; ?>"><i class="fas fa-book"></i>Prospectus</a></li>
                <li class="nav-item"><a href="grade_encoding.php" class="nav-link <?php echo ($current_page == 'grade_encoding.php') ? 'active' : ''; ?>"><i class="fas fa-edit"></i>Grade Encoding</a></li>
                <li class="nav-item"><a href="advising_report.php" class="nav-link <?php echo ($current_page == 'advising_report.php') ? 'active' : ''; ?>"><i class="fas fa-file-alt"></i>Advising Report</a></li>
                <li class="nav-item"><a href="professor_schedule.php" class="nav-link <?php echo ($current_page == 'professor_schedule.php') ? 'active' : ''; ?>"><i class="fas fa-chalkboard-teacher"></i>Professor Schedule</a></li>
                <li class="nav-item"><a href="room_availability.php" class="nav-link <?php echo ($current_page == 'room_availability.php') ? 'active' : ''; ?>"><i class="fas fa-door-open"></i>Room Availability</a></li>
            <?php elseif($role == 'professor'): ?>
                <li class="nav-item"><a href="dashboard.php" class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-home"></i>Home / Dashboard</a></li>
                <li class="nav-item"><a href="information.php" class="nav-link <?php echo ($current_page == 'information.php') ? 'active' : ''; ?>"><i class="fas fa-user"></i>Professor Information</a></li>
                <li class="nav-item"><a href="prospectus.php" class="nav-link <?php echo ($current_page == 'prospectus.php') ? 'active' : ''; ?>"><i class="fas fa-book"></i>Prospectus</a></li>
                <li class="nav-item"><a href="room_management.php" class="nav-link <?php echo ($current_page == 'room_management.php') ? 'active' : ''; ?>"><i class="fas fa-door-open"></i>Room Management</a></li>
            <?php elseif($role == 'admin'): ?>
                <li class="nav-item"><a href="dashboard.php" class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-home"></i>Home / Dashboard</a></li>
                <li class="nav-item"><a href="student_records.php" class="nav-link <?php echo ($current_page == 'student_records.php') ? 'active' : ''; ?>"><i class="fas fa-users"></i>Student Records</a></li>
                <li class="nav-item"><a href="data_management.php" class="nav-link <?php echo ($current_page == 'data_management.php') ? 'active' : ''; ?>"><i class="fas fa-database"></i>Data Management</a></li>
                <li class="nav-item"><a href="room_management.php" class="nav-link <?php echo ($current_page == 'room_management.php') ? 'active' : ''; ?>"><i class="fas fa-door-open"></i>Room Management</a></li>
                <li class="nav-item"><a href="professor_schedule.php" class="nav-link <?php echo ($current_page == 'professor_schedule.php') ? 'active' : ''; ?>"><i class="fas fa-chalkboard-teacher"></i>Professor Schedule</a></li>
                <li class="nav-item"><a href="create_account.php" class="nav-link <?php echo ($current_page == 'create_account.php') ? 'active' : ''; ?>"><i class="fas fa-user-plus"></i>Create Account</a></li>
            <?php endif; ?>
            <li class="nav-item"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
        </ul>
    </div>
    <div class="main-content">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // System prompt for AI
    const systemPrompt = `You are an AI academic advising assistant for the Academic Advising System.

IMPORTANT RULES:
- Passing grade: 1.00 to 3.00 = PASSED
- Failing grade: 3.01 to 5.00 = FAILED
- Year standing formula: (Passed Subjects ÷ Total Subjects) × 100
- Need at least 75% to proceed to next year level
- Prerequisites: NSTP 1 → NSTP 2, MATH 2E → MATH 3E, CpE 1 → CpE 2
- Room colors: Green=Available, Red=Occupied, Gray=Office
- Programs: Computer Engineering (CpE) and Civil Engineering (CE)

Answer concisely and helpfully. Be friendly.`;

    // Create chatbot elements
    $('body').append(`
        <div class="chatbot-icon" id="chatbotIcon">
            <img src="../assets/images/chatbot-icon.png" alt="Chatbot" style="width: 40px; height: 40px; border-radius: 50%;">
        </div>
        <div class="chatbot-window" id="chatbotWindow">
            <div class="chatbot-header bg-primary text-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <img src="../assets/images/chatbot-avatar.png" alt="Chatbot" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;">
                        <h6 class="mb-0">AI Assistant</h6>
                    </div>
                    <button class="btn-close btn-close-white" id="closeChatbot"></button>
                </div>
            </div>
            <div class="chatbot-messages">
                <div class="message bot-message mb-2">
                    <div class="d-flex align-items-start">
                        <img src="../assets/images/chatbot-avatar.png" alt="Bot" style="width: 25px; height: 25px; border-radius: 50%; margin-right: 8px;">
                        <div class="bg-light p-2 rounded d-inline-block">
                            Hello! 👋 I'm your AI academic advisor. I can help you with:<br><br>
                            • 📊 Grades (1.00-3.00 PASSED, 3.01-5.00 FAILED)<br>
                            • 🔗 Prerequisites (NSTP 1 → NSTP 2)<br>
                            • 📈 Year standing (need 75% to proceed)<br>
                            • 🏠 Room availability (Green=Available, Red=Occupied)<br>
                            • 👨‍🏫 Professor schedules<br><br>
                            Ask me anything about your academics!
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
    
    // Fallback simple responses (used when API fails)
    function getSimpleResponse(message) {
        const msg = message.toLowerCase();
        if (msg.includes('nstp')) {
            return "📖 NSTP Requirements:\n• NSTP 1 is a prerequisite for NSTP 2\n• You must PASS NSTP 1 (grade 1.00-3.00) before taking NSTP 2\n• If you FAIL NSTP 1, you cannot take NSTP 2 until you retake and pass it.";
        }
        else if (msg.includes('grade')) {
            return "📊 Grade System:\n• 1.00 - 3.00 = PASSED ✅\n• 3.01 - 5.00 = FAILED ❌\n• Year Standing: (Passed Subjects ÷ Total Subjects) × 100\n• Need at least 75% to proceed to next year level";
        }
        else if (msg.includes('prerequisite')) {
            return "🔗 Prerequisites are subjects you must PASS before taking advanced courses.\n\nExamples:\n• NSTP 1 → NSTP 2\n• MATH 2E → MATH 3E\n• CpE 1 → CpE 2";
        }
        else if (msg.includes('room')) {
            return "🏠 Room Availability:\n• Green = Available ✅\n• Red = Occupied ❌\n• Gray = Faculty/Dean Office\n• Click any room to see full weekly schedule";
        }
        else if (msg.includes('professor') || msg.includes('schedule')) {
            return "👨‍🏫 Professor Schedules:\n• Shows subject, time, day, room, and mode (F2F/Online)\n• View in 'Professor Schedule' section\n• Professors can manage their own schedules";
        }
        else if (msg.includes('prospectus')) {
            return "📖 Prospectus / Curriculum:\n• Shows all subjects for your program\n• Filter by Year Level and Semester\n• Each subject shows: Code, Title, Units, Prerequisites";
        }
        else if (msg.includes('hello') || msg.includes('hi')) {
            return "Hello! 👋 How can I help you with your academics today?";
        }
        else {
            return "I can help with:\n• Grades (1.00-3.00 PASSED)\n• Prerequisites (NSTP 1 → NSTP 2)\n• Year standing (need 75%)\n• Room availability (Green=Available, Red=Occupied)\n• Professor schedules\n\nWhat would you like to know?";
        }
    }
    
    // Send message function
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
        
        // Show typing indicator
        $('.chatbot-messages').append(`
            <div class="message bot-message mb-2" id="typingIndicator">
                <div class="d-flex align-items-start">
                    <img src="../assets/images/chatbot-avatar.png" alt="Bot" style="width: 25px; height: 25px; border-radius: 50%; margin-right: 8px;">
                    <div class="typing-indicator">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
        `);
        $('.chatbot-messages').scrollTop($('.chatbot-messages')[0].scrollHeight);
        
        // Try Hugging Face API first
        const fullPrompt = systemPrompt + "\n\nUser: " + message + "\n\nAssistant:";
        
        $.ajax({
            url: 'https://api-inference.huggingface.co/models/microsoft/DialoGPT-medium',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({
                inputs: fullPrompt,
                parameters: {
                    max_length: 300,
                    temperature: 0.7,
                    do_sample: true
                }
            }),
            timeout: 10000, // 10 second timeout
            success: function(data) {
                $('#typingIndicator').remove();
                let response = data.generated_text || "I'm not sure about that. Can you rephrase?";
                // Clean up the response
                response = response.replace(fullPrompt, '').trim();
                if (!response || response.length < 2) {
                    response = getSimpleResponse(message);
                }
                
                $('.chatbot-messages').append(`
                    <div class="message bot-message mb-2">
                        <div class="d-flex align-items-start">
                            <img src="../assets/images/chatbot-avatar.png" alt="Bot" style="width: 25px; height: 25px; border-radius: 50%; margin-right: 8px;">
                            <div class="bg-light p-2 rounded d-inline-block" style="white-space: pre-line;">
                                ${escapeHtml(response)}
                            </div>
                        </div>
                    </div>
                `);
                $('.chatbot-messages').scrollTop($('.chatbot-messages')[0].scrollHeight);
            },
            error: function(xhr, status, error) {
                $('#typingIndicator').remove();
                // Fallback to simple responses
                let response = getSimpleResponse(message);
                $('.chatbot-messages').append(`
                    <div class="message bot-message mb-2">
                        <div class="d-flex align-items-start">
                            <img src="../assets/images/chatbot-avatar.png" alt="Bot" style="width: 25px; height: 25px; border-radius: 50%; margin-right: 8px;">
                            <div class="bg-light p-2 rounded d-inline-block" style="white-space: pre-line;">
                                ${escapeHtml(response)}
                            </div>
                        </div>
                    </div>
                `);
                $('.chatbot-messages').scrollTop($('.chatbot-messages')[0].scrollHeight);
            }
        });
    }
    
    // Send message on button click
    $('#sendMessage').click(function() {
        sendMessage();
    });
    
    // Send message on Enter key
    $('#chatbotInput').keypress(function(e) {
        if(e.which == 13) {
            sendMessage();
        }
    });
    
    function escapeHtml(text) {
        return text.replace(/[&<>]/g, function(m) {
            if(m === '&') return '&amp;';
            if(m === '<') return '&lt;';
            if(m === '>') return '&gt;';
            return m;
        });
    }
});
</script>