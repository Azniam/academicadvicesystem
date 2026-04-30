<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = $_POST['room_id'];
    $day = $_POST['day'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $exclude_id = $_POST['exclude_id'] ?? null;
    
    $sql = "
        SELECT s.*, u.fullname as professor_name
        FROM schedules s
        JOIN users u ON s.professor_id = u.user_id
        WHERE s.room_id = ? AND s.day = ? 
        AND ((s.start_time <= ? AND s.end_time > ?) OR (s.start_time < ? AND s.end_time >= ?))
    ";
    $params = [$room_id, $day, $start_time, $start_time, $end_time, $end_time];
    
    if ($exclude_id) {
        $sql .= " AND s.id != ?";
        $params[] = $exclude_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $conflict = $stmt->fetch();
    
    if ($conflict) {
        echo json_encode([
            'available' => false,
            'professor_name' => $conflict['professor_name'],
            'subject_code' => $conflict['subject_code'] ?? 'Unknown'
        ]);
    } else {
        echo json_encode(['available' => true]);
    }
}
?>