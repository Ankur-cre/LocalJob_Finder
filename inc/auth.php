<?php
/**
 * Authentication & Core Bootstrap
 * Local Jobs — Final Secure Version
 * 
 * Fixes:
 * - Session auto-validation (prevents foreign key errors)
 * - Deleted user session cleanup
 * - Cross-role security check
 */

// ============================================================
// 1) SESSION START
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('SITE_NAME', 'Local Jobs');

// ============================================================
// 2) DATABASE CONNECTION
// ============================================================
// ⚠️ LIVE HOSTING PAR YE VALUES CHANGE KARO
$db = new mysqli('localhost', 'root', '', 'local_jobs');

if ($db->connect_error) {
    die("DB error: " . $db->connect_error);
}
$db->set_charset('utf8mb4');

// ============================================================
// 3) SESSION AUTO-VALIDATE (FOREIGN KEY ERROR FIX)
// ============================================================
// Har page load par check karo ki session wala user DB me exist karta hai.
// Agar user delete ho gaya, to session clear kar do.
if (isset($_SESSION['uid'])) {
    $uid_check = (int)$_SESSION['uid'];
    $user_check = $db->query("SELECT id, role, status FROM users WHERE id = $uid_check LIMIT 1");
    
    if (!$user_check || $user_check->num_rows === 0) {
        // User DB me nahi hai — session invalid
        $_SESSION = [];
        session_unset();
        session_destroy();
        header("Location: login.php?err=" . urlencode("Your session has expired. Please login again."));
        exit;
    }
    
    $user_row = $user_check->fetch_assoc();
    
    // Role mismatch check (agar admin ne role change kar diya)
    if (isset($_SESSION['role']) && $user_row['role'] !== $_SESSION['role']) {
        $_SESSION = [];
        session_unset();
        session_destroy();
        header("Location: login.php?err=" . urlencode("Your account has been updated. Please login again."));
        exit;
    }
    
    // Suspended account check
    if ($user_row['status'] === 'rejected') {
        $_SESSION = [];
        session_unset();
        session_destroy();
        header("Location: login.php?err=" . urlencode("Your account has been suspended. Please contact support."));
        exit;
    }
}

// ============================================================
// 4) LANGUAGE ENGINE
// ============================================================
if (!function_exists('t')) {
    require_once __DIR__ . '/lang.php';
}

// ============================================================
// 5) HELPER FUNCTIONS
// ============================================================

if (!function_exists('h')) {
    function h($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('logged_in')) {
    function logged_in() {
        return isset($_SESSION['uid']);
    }
}

if (!function_exists('role')) {
    function role() {
        return $_SESSION['role'] ?? null;
    }
}

if (!function_exists('must_role')) {
    function must_role($r) {
        if (!logged_in() || role() !== $r) {
            header("Location: login.php?err=" . urlencode("Please login first."));
            exit;
        }
    }
}