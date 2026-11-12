<?php
require_once 'inc/auth.php';
if (!logged_in()) { exit; }

$uid = $_SESSION['uid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_single'])) {
        $id = (int)$_POST['id'];
        $db->query("DELETE FROM notifications WHERE id=$id AND user_id=$uid");
    }
    if (isset($_POST['clear_all'])) {
        $db->query("DELETE FROM notifications WHERE user_id=$uid");
    }
}

// Redirect back to the page they came from
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: $referer");
exit;
?>