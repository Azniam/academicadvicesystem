<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$message = strtolower($_POST['message'] ?? '');
$response = "I'm sorry, I didn't understand that. Please ask about subjects, prospectus, professors, or grades.";

// Subject information
if (strpos($message, 'subject') !== false || strpos($message, 'course') !== false) {
    $response = "We offer Computer Engineering (CpE) and Civil Engineering (CE) programs. Each program has a 4-year curriculum with core subjects, professional courses, and electives.";
}
// Prospectus information
elseif (strpos($message, 'prospectus') !== false || strpos($message, 'curriculum') !== false) {
    $response = "The prospectus shows all subjects required for your program. You can view it in the Prospectus section. Each subject has lecture hours, lab hours, units, and prerequisites.";
}
// Grades information
elseif (strpos($message, 'grade') !== false) {
    $response = "Grades are encoded by students at the end of each semester. To pass a subject, you need a grade between 1.00 and 3.00. Grades from 3.01 to 5.00 are failing grades. You must pass all prerequisites before taking advanced subjects.";
}
// Prerequisites
elseif (strpos($message, 'prerequisite') !== false) {
    $response = "Prerequisites are subjects you must complete before enrolling in higher-level courses. The system automatically checks if you've passed all prerequisites before allowing enrollment.";
}
// Year standing
elseif (strpos($message, 'standing') !== false || strpos($message, 'year level') !== false) {
    $response = "Your year standing is calculated based on the percentage of passed subjects. You need at least 75% to proceed to the next year level.";
}
// Professors
elseif (strpos($message, 'professor') !== false || strpos($message, 'teacher') !== false) {
    $response = "Professor schedules are available in the Professor Schedule section. You can see which professor handles which subject, their schedule, room, and mode of teaching (F2F/Online).";
}
// Rooms
elseif (strpos($message, 'room') !== false) {
    $response = "Room availability is shown in the Room Availability section. Green rooms are available, red rooms are occupied, and white/gray rooms are faculty or dean offices.";
}
// Help
elseif (strpos($message, 'help') !== false) {
    $response = "I can help you with information about: subjects, prospectus, grades, prerequisites, year standing, professors, rooms, and enrollment. What would you like to know?";
}
// Enrollment
elseif (strpos($message, 'enroll') !== false) {
    $response = "Enrollment is based on your grades and prerequisites. The system automatically determines which subjects you're allowed to take based on your performance.";
}

echo json_encode(['response' => $response]);
?>