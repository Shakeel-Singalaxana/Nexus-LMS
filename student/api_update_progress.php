<?php
// student/api_update_progress.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$action = $data['action'] ?? '';

if ($action === 'mark_video_done') {
    $video_id = (int)$data['video_id'];
    $lesson_id = (int)$data['lesson_id'];

    // Mark video as completed
    $stmt = $pdo->prepare("INSERT IGNORE INTO video_progress (user_id, video_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $video_id]);

    // Check if all videos in this lesson are completed
    $stmt = $pdo->prepare("SELECT id FROM lesson_videos WHERE lesson_id = ?");
    $stmt->execute([$lesson_id]);
    $all_vids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $all_completed = false;
    if (!empty($all_vids)) {
        $stmt = $pdo->prepare("SELECT video_id FROM video_progress WHERE user_id = ? AND video_id IN (" . implode(',', array_fill(0, count($all_vids), '?')) . ")");
        $stmt->execute(array_merge([$user_id], $all_vids));
        $watched_vids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($watched_vids) === count($all_vids)) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO progress (user_id, lesson_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $lesson_id]);
            $all_completed = true;
        }
    }

    echo json_encode(['success' => true, 'all_completed' => $all_completed]);
    exit;
}

if ($action === 'mark_lesson_done') {
    $lesson_id = (int)$data['lesson_id'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO progress (user_id, lesson_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $lesson_id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
