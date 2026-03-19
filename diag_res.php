<?php
require_once 'config/db.php';

try {
    $stmt = $pdo->query("SELECT * FROM lesson_resources ORDER BY id DESC LIMIT 5");
    $rows = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($rows, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
