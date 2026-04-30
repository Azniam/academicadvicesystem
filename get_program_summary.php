<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course = $_POST['course'] ?? 'CE';
    
    // Get total subjects and units for the program
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_subjects,
            SUM(units) as total_units
        FROM prospectus 
        WHERE course = ?
    ");
    $stmt->execute([$course]);
    $result = $stmt->fetch();
    
    echo json_encode([
        'total_subjects' => $result['total_subjects'] ?? 0,
        'total_units' => $result['total_units'] ?? 0
    ]);
}
?>