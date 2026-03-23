<?php
// admin/ajax_student_stats.php
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;

if (!$student_id) {
    echo json_encode(['error' => 'Invalid Student ID']);
    exit;
}

try {
    // 1. Fetch Student Details
    $stmt = $pdo->prepare("
        SELECT u.full_name, b.name AS batch_name, b.id AS batch_id 
        FROM users u 
        LEFT JOIN batches b ON u.batch_id = b.id 
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        echo json_encode(['error' => 'Student not found']);
        exit;
    }

    // 2. Aggregate Stats
    // Total lessons in their batch
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE batch_id = ?");
    $stmt->execute([$student['batch_id']]);
    $total_lessons = $stmt->fetchColumn();

    // Lessons completed by student
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM progress WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $completed_lessons_count = $stmt->fetchColumn();

    // Videos watched by student
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM video_progress WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $videos_watched = $stmt->fetchColumn();

    // 3. Detailed completion list
    $stmt = $pdo->prepare("
        SELECT l.title, l.class_type, p.completed_at 
        FROM lessons l 
        LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = ? 
        WHERE l.batch_id = ? 
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$student_id, $student['batch_id']]);
    $completions = $stmt->fetchAll();

    // 5. Calculate Level
    $completion_rate = $total_lessons > 0 ? round(($completed_lessons_count / $total_lessons) * 100, 1) : 0;
    
    // Overall rate might consider videos too
    $video_rate = $videos_watched > 0 ? 100 : 0; // simplistic for now
    // Actually, let's just use completion_rate for simplicity
    
    $level = 1;
    $level_title = 'Apprentice';
    if ($completion_rate >= 90) { $level = 6; $level_title = 'Master'; }
    else if ($completion_rate >= 70) { $level = 5; $level_title = 'Expert'; }
    else if ($completion_rate >= 50) { $level = 4; $level_title = 'Scholar'; }
    else if ($completion_rate >= 30) { $level = 3; $level_title = 'Learner'; }
    else if ($completion_rate >= 10) { $level = 2; $level_title = 'Novice'; }

    echo json_encode([
        'full_name' => $student['full_name'],
        'batch_name' => $student['batch_name'] ?: 'Not Assigned',
        'total_lessons' => $total_lessons,
        'completed_count' => $completed_lessons_count,
        'videos_watched' => $videos_watched,
        'completion_rate' => $completion_rate,
        'level' => $level,
        'level_title' => $level_title,
        'history' => $completions
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
