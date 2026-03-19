<?php
// auth/api_update_theme.php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Not logged in']));
}

$data = json_decode(file_get_contents('php://input'), true);
$theme = $data['theme'] ?? 'light';

if (!in_array($theme, ['light', 'dark'])) {
    die(json_encode(['status' => 'error', 'message' => 'Invalid theme']));
}

$_SESSION['theme'] = $theme;

// Update database for students
if ($_SESSION['role'] === 'student') {
    $stmt = $pdo->prepare("UPDATE users SET theme = ? WHERE id = ?");
    $stmt->execute([$theme, $_SESSION['user_id']]);
}

echo json_encode(['status' => 'success', 'theme' => $theme]);
?>
