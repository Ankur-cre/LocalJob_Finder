<?php
/**
 * Permanent Account Deletion
 * Path: delete-account.php
 * 
 * POST params:
 *   - confirm_password = current password for verification
 *   - confirm_delete = 'yes' (checkbox)
 *   - role_type = 'seeker' | 'employer' (optional sanity check)
 */

require_once 'inc/auth.php';

if (!logged_in()) {
    header("Location: login.php?err=" . urlencode("Please login first."));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$uid  = (int)$_SESSION['uid'];
$role = role();

// Only seeker/employer can delete (not admin)
if (!in_array($role, ['seeker', 'employer'])) {
    header("Location: " . ($role === 'admin' ? 'admin.php' : 'index.php') . "?err=" . urlencode("Admin accounts cannot be deleted from here."));
    exit;
}

$redirect_settings = ($role === 'employer') ? 'employer-settings.php?tab=delete' : 'seeker-settings.php?tab=delete';

// ============ VALIDATE INPUT ============
$confirm_password = $_POST['confirm_password'] ?? '';
$confirm_delete   = $_POST['confirm_delete']   ?? '';

if ($confirm_delete !== 'yes') {
    header("Location: $redirect_settings&err=" . urlencode("Please check the confirmation box."));
    exit;
}

if ($confirm_password === '') {
    header("Location: $redirect_settings&err=" . urlencode("Please enter your password."));
    exit;
}

// ============ VERIFY PASSWORD ============
$stmt = $db->prepare("SELECT id, name, email, password, profile_photo FROM users WHERE id=? AND role=?");
$stmt->bind_param('is', $uid, $role);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: logout.php");
    exit;
}

if (!password_verify($confirm_password, $user['password'])) {
    header("Location: $redirect_settings&err=" . urlencode("Incorrect password. Account NOT deleted."));
    exit;
}

// ============ HELPER: Check table/column exists ============
if (!function_exists('del_col_exists')) {
    function del_col_exists($db, $table, $col) {
        $t = $db->real_escape_string($table);
        $c = $db->real_escape_string($col);
        $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return $r && $r->num_rows > 0;
    }
}
if (!function_exists('del_table_exists')) {
    function del_table_exists($db, $table) {
        $t = $db->real_escape_string($table);
        $r = $db->query("SHOW TABLES LIKE '$t'");
        return $r && $r->num_rows > 0;
    }
}

// ============ DELETE ALL RELATED DATA ============
$deleted_counts = [];
$errors = [];

try {
    // 1. user_settings
    if (del_table_exists($db, 'user_settings')) {
        $stmt = $db->prepare("DELETE FROM user_settings WHERE user_id=?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $deleted_counts['user_settings'] = $stmt->affected_rows;
    }

    // 2. applications
    if (del_table_exists($db, 'applications')) {
        $stmt = $db->prepare("DELETE FROM applications WHERE seeker_id=?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $deleted_counts['applications'] = $stmt->affected_rows;
    }

    // 3. saved_jobs
    if (del_table_exists($db, 'saved_jobs')) {
        $stmt = $db->prepare("DELETE FROM saved_jobs WHERE seeker_id=?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $deleted_counts['saved_jobs'] = $stmt->affected_rows;
    }

    // 4. job_alerts
    if (del_table_exists($db, 'job_alerts')) {
        $stmt = $db->prepare("DELETE FROM job_alerts WHERE seeker_id=?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $deleted_counts['job_alerts'] = $stmt->affected_rows;
    }

    // 5. otp_codes
    if (del_table_exists($db, 'otp_codes')) {
        $stmt = $db->prepare("DELETE FROM otp_codes WHERE user_id=?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $deleted_counts['otp_codes'] = $stmt->affected_rows;
    }

    // 6. seeker_skills
    if (del_table_exists($db, 'seeker_skills')) {
        $stmt = $db->prepare("DELETE FROM seeker_skills WHERE seeker_id=?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $deleted_counts['seeker_skills'] = $stmt->affected_rows;
    }

    // 7. seeker_education
    if (del_table_exists($db, 'seeker_education')) {
        $stmt = $db->prepare("DELETE FROM seeker_education WHERE seeker_id=?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $deleted_counts['seeker_education'] = $stmt->affected_rows;
    }

    // 8. seeker_experience
    if (del_table_exists($db, 'seeker_experience')) {
        $stmt = $db->prepare("DELETE FROM seeker_experience WHERE seeker_id=?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $deleted_counts['seeker_experience'] = $stmt->affected_rows;
    }

    // 9. notifications
    if (del_table_exists($db, 'notifications')) {
        $stmt = $db->prepare("DELETE FROM notifications WHERE user_id=?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $deleted_counts['notifications'] = $stmt->affected_rows;
    }

    // 10. job_reports — reporter_id set to NULL (retain report log for admin)
    if (del_table_exists($db, 'job_reports') && del_col_exists($db, 'job_reports', 'reporter_id')) {
        $stmt = $db->prepare("UPDATE job_reports SET reporter_id=NULL WHERE reporter_id=?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $deleted_counts['job_reports_anonymized'] = $stmt->affected_rows;
    }

    // 11. chat / messages (if exists)
    if (del_table_exists($db, 'messages')) {
        if (del_col_exists($db, 'messages', 'sender_id')) {
            $db->query("DELETE FROM messages WHERE sender_id=$uid");
        }
        if (del_col_exists($db, 'messages', 'receiver_id')) {
            $db->query("DELETE FROM messages WHERE receiver_id=$uid");
        }
    }

    // 12. Finally: users table
    $stmt = $db->prepare("DELETE FROM users WHERE id=? AND role=?");
    $stmt->bind_param('is', $uid, $role);
    $stmt->execute();

    if ($stmt->affected_rows < 1) {
        throw new Exception("Failed to delete user account from database.");
    }
} catch (Throwable $e) {
    error_log("Delete account error: " . $e->getMessage());
    header("Location: $redirect_settings&err=" . urlencode("Delete failed: " . $e->getMessage()));
    exit;
}

// ============ DELETE USER FILES ============
$file_patterns = [
    __DIR__ . '/storage/profiles/user_' . $uid . '_*',
    __DIR__ . '/storage/profiles/' . ($user['profile_photo'] ?? ''),
    __DIR__ . '/storage/resumes/resume_' . $uid . '_*',
    __DIR__ . '/storage/verification/verify_' . $uid . '_*',
];

foreach ($file_patterns as $pattern) {
    if ($pattern === '' || basename($pattern) === '') continue;
    $matches = glob($pattern);
    if ($matches) {
        foreach ($matches as $file) {
            if (is_file($file)) @unlink($file);
        }
    }
}

// ============ CLEAR SESSION + DESTROY ============
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

/// ============ REDIRECT TO LOGIN PAGE WITH MESSAGE ============
header("Location: login.php?msg=" . urlencode("Your account has been permanently deleted successfully. We're sorry to see you go.") . "&type=success");
exit;