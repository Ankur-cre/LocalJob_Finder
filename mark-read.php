<?php
require_once 'inc/auth.php';
if (!logged_in()) { exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $id = (int)$_POST['id'];
    $uid = $_SESSION['uid'];
    $db->query("UPDATE notifications SET is_read = 1 WHERE id=$id AND user_id=$uid");
}
?>