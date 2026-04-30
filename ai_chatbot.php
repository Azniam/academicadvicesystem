<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

// Get user message
$message = $_POST['message'] ?? '';
$user_id = $_SESSION['user_id'] ?? 'guest';

if (empty($message)) {
    echo json_encode(['response' => 'Please ask a question.']);
    exit();
}

// Your Google Gemini API Key (get from https://aistudio.google.com/)
$api_key = 'AIzaSyBpiaZS1gUWeYj8QoYz4OdpYOZ6qeB6jg8';

// System prompt - teaches AI about your system
$system_prompt = "You are an academic advising assistant for a university. 
You help students with:
- Course enrollment and prerequisites
- Grade requirements (1.00-3.00 PASSED, 3.01-5.00 FAILED)
- Year standing (need 75% to proceed)
- Subject schedules and room availability
- Program requirements for CE and CpE

Be helpful, concise, and friendly. If you don't know something, say so honestly.";

// Prepare conversation context (store last messages in session)
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// Add user message to history
$_SESSION['chat_history'][] = ['role' => 'user', 'content' => $message];

// Limit history to last 10 exchanges
if (count($_SESSION['chat_history']) > 20) {
    $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -20);
}

// Build conversation for API
$contents = [];
$contents[] = ['role' => 'user', 'parts' => [['text' => $system_prompt]]];
$contents[] = ['role' => 'model', 'parts' => [['text' => "I understand. I'll help students with academic advising."]]];

foreach ($_SESSION['chat_history'] as $exchange) {
    $role = $exchange['role'] == 'user' ? 'user' : 'model';
    $contents[] = ['role' => $role, 'parts' => [['text' => $exchange['content']]]];
}

// Call Gemini API
$url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=" . $api_key;

$data = [
    'contents' => $contents,
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 500,
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    $result = json_decode($response, true);
    $ai_response = $result['candidates'][0]['content']['parts'][0]['text'] ?? "Sorry, I couldn't generate a response.";
    
    // Save AI response to history
    $_SESSION['chat_history'][] = ['role' => 'model', 'content' => $ai_response];
    
    echo json_encode(['response' => $ai_response]);
} else {
    // Fallback to keyword matching if API fails
    echo json_encode(['response' => getFallbackResponse($message)]);
}

// Fallback function (your existing chatbot logic)
function getFallbackResponse($message) {
    $message = strtolower($message);
    
    if (strpos($message, 'subject') !== false || strpos($message, 'course') !== false) {
        return "We offer Computer Engineering (CpE) and Civil Engineering (CE) programs. Each program has a 4-year curriculum with core subjects, professional courses, and electives.";
    }
    if (strpos($message, 'grade') !== false) {
        return "Grades are encoded by students at the end of each semester. To pass a subject, you need a grade between 1.00 and 3.00. Grades from 3.01 to 5.00 are failing grades.";
    }
    if (strpos($message, 'prerequisite') !== false) {
        return "Prerequisites are subjects you must complete before enrolling in higher-level courses. The system automatically checks if you've passed all prerequisites before allowing enrollment.";
    }
    if (strpos($message, 'standing') !== false || strpos($message, 'year level') !== false) {
        return "Your year standing is calculated based on the percentage of passed subjects. You need at least 75% to proceed to the next year level.";
    }
    if (strpos($message, 'professor') !== false) {
        return "Professor schedules are available in the Professor Schedule section. You can see which professor handles which subject, their schedule, room, and mode of teaching (F2F/Online).";
    }
    if (strpos($message, 'room') !== false) {
        return "Room availability is shown in the Room Availability section. Green rooms are available, red rooms are occupied.";
    }
    if (strpos($message, 'help') !== false) {
        return "I can help you with information about: subjects, prospectus, grades, prerequisites, year standing, professors, rooms, and enrollment. What would you like to know?";
    }
    
    return "I'm sorry, I didn't understand that. Please ask about subjects, grades, prerequisites, professors, or rooms.";
}
?>