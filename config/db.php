<?php
// config/db.php
// InfinityFree uses 'sqlXXX.epizy.com' as host, 'epiz_XXXXXXX' as user, 'password' as pass, 'epiz_XXXXXXX_dbname' as dbname.
// Since we don't know the exact credentials, placeholders are used here.

$host = 'localhost'; // Usually 127.0.0.1 or epizy's server
$dbname = 'lms_db';
$user = 'root'; // Replacement with InfinityFree username
$pass = ''; // Replacement with InfinityFree password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}
?>
