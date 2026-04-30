<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = $_POST['room_id'] ?? 0;
    $room_code = $_POST['room_code'] ?? '';
    
    // Get schedules for the room
    if ($room_id) {
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   u.fullname as professor_name,
                   sub.subject_code, sub.descriptive_title as subject_title,
                   r.room_code
            FROM schedules s
            JOIN users u ON s.professor_id = u.user_id
            JOIN subjects sub ON s.subject_id = sub.id
            JOIN rooms r ON s.room_id = r.id
            WHERE s.room_id = ?
            ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), s.start_time
        ");
        $stmt->execute([$room_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   u.fullname as professor_name,
                   sub.subject_code, sub.descriptive_title as subject_title,
                   r.room_code
            FROM schedules s
            JOIN users u ON s.professor_id = u.user_id
            JOIN subjects sub ON s.subject_id = sub.id
            JOIN rooms r ON s.room_id = r.id
            WHERE r.room_code = ?
            ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), s.start_time
        ");
        $stmt->execute([$room_code]);
    }
    
    $schedules = $stmt->fetchAll();
    
    $formatted_schedules = [];
    foreach ($schedules as $schedule) {
        $formatted_schedules[] = [
            'day' => $schedule['day'],
            'time' => date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time'])),
            'subject_code' => $schedule['subject_code'],
            'subject_title' => $schedule['subject_title'],
            'professor_name' => $schedule['professor_name'],
            'mode' => $schedule['mode'],
            'room_code' => $schedule['room_code']
        ];
    }
    
    echo json_encode(['schedules' => $formatted_schedules]);
}
?>