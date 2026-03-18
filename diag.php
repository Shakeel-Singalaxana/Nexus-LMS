<?php
require_once 'config/db.php';
echo "<pre>";
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database:\n";
    print_r($tables);

    foreach ($tables as $table) {
        echo "\nStructure of $table:\n";
        $columns = $pdo->query("DESCRIBE $table")->fetchAll();
        print_r($columns);
    }

    $batches = $pdo->query("SELECT * FROM batches")->fetchAll();
    echo "\nBatches content:\n";
    print_r($batches);

    $students = $pdo->query("SELECT id, username, role, batch_id FROM users WHERE role = 'student'")->fetchAll();
    echo "\nStudents content:\n";
    print_r($students);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
echo "</pre>";
?>
