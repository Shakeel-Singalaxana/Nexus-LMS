<?php
// diag_insert.php
require_once 'config/db.php';
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO lessons (batch_id, class_type, title) VALUES (1, 'Theory', 'Test Manual Insert')");
    $stmt->execute();
    $id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("INSERT INTO lesson_resources (lesson_id, resource_type, file_path, file_name) VALUES (?, 'link', 'https://test.com', 'Test Link')");
    $stmt->execute([$id]);
    
    $pdo->commit();
    echo "Manual Test Successful. Lesson ID: $id";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Manual Test Failed: " . $e->getMessage();
}
?>
