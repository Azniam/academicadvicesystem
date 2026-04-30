<?php
header('Content-Type: application/json');

$message = $_POST['message'] ?? '';

if (empty($message)) {
    echo json_encode(['response' => 'Please type a question.']);
    exit();
}

$message = strtolower($message);

// Simple responses
if (strpos($message, 'nstp') !== false) {
    $response = "NSTP 2 requires NSTP 1 as prerequisite. You must pass NSTP 1 first.";
} 
elseif (strpos($message, 'grade') !== false) {
    $response = "Passing grade: 1.00 to 3.00. Failing grade: 3.01 to 5.00.";
}
elseif (strpos($message, 'hello') !== false || strpos($message, 'hi') !== false) {
    $response = "Hello! How can I help you?";
}
else {
    $response = "I can help with grades, prerequisites (NSTP 1 to NSTP 2), and subjects. What do you want to know?";
}

echo json_encode(['response' => $response]);
?>