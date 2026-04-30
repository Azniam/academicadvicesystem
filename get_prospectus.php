<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year_level = $_POST['year_level'] ?? 'all';
    $semester = $_POST['semester'] ?? 'all';
    $course = $_POST['course'] ?? '';
    
    $sql = "SELECT * FROM prospectus WHERE course = ?";
    $params = [$course];
    
    // Add year filter if not 'all'
    if ($year_level !== 'all') {
        $sql .= " AND year_level = ?";
        $params[] = $year_level;
    }
    
    // Add semester filter if not 'all'
    if ($semester !== 'all') {
        $sql .= " AND semester = ?";
        $params[] = $semester;
    }
    
    $sql .= " ORDER BY year_level, semester, id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $subjects = $stmt->fetchAll();
    
    echo json_encode($subjects);
}
?>