<?php
/**
 * API для получения данных студента
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

$pdo = getDB();
$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($studentId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid student ID']);
    exit;
}

// Получение данных студента
$stmt = $pdo->prepare("SELECT s.*, g.name as group_name, g.course 
                       FROM students s 
                       JOIN groups g ON s.group_id = g.id 
                       WHERE s.id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    echo json_encode(['error' => 'Student not found']);
    exit;
}

// Получение оценок студента
$grades = getStudentGrades($pdo, $studentId);
$avgGrade = calculateStudentAverage($studentId, $pdo);

echo json_encode([
    'student' => $student,
    'grades' => $grades,
    'avg_grade' => $avgGrade
]);
?>
