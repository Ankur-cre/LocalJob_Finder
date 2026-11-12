<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'local_jobs');

define('SITE_NAME', 'Local Jobs Nichlaul');
define('SITE_URL', 'http://localhost/local-jobs');
define('STORAGE_PATH', __DIR__ . '/../storage/');
define('STORAGE_URL', 'storage/');

// Connection
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    die("Database Connection Failed: " . $db->connect_error);
}
$db->set_charset("utf8mb4");

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>