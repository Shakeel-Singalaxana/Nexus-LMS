<?php
// admin/export_students.php
require_once '../config/db.php';
session_start();

// Authentication Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

// Fetch Students Data
$query = "
    SELECT u.full_name, u.mobile_number, b.name as batch_name, 
           CASE WHEN u.is_verified = 1 THEN 'Verified' ELSE 'Unverified' END as status, 
           u.created_at
    FROM users u
    LEFT JOIN batches b ON u.batch_id = b.id
    WHERE u.role = 'student'
    ORDER BY u.created_at DESC
";
$stmt = $pdo->query($query);
$students = $stmt->fetchAll();

// CSV Setup
$filename = "LMS_Students_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// Add Header Row
fputcsv($output, ['Full Name', 'Mobile Number', 'Batch', 'Status', 'Registration Date']);

// Add Data Rows
foreach ($students as $student) {
    fputcsv($output, [
        $student['full_name'],
        $student['mobile_number'],
        $student['batch_name'] ?? 'Not Assigned',
        $student['status'],
        $student['created_at']
    ]);
}

fclose($output);
exit;
?>
