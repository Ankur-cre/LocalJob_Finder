<?php
/**
 * AJAX: Admin Actions
 * Path: ajax-admin-action.php
 * 
 * Request (POST):
 *   action = approve_user | reject_user | approve_employer | reject_employer
 *   user_id = target user ID
 *   reason  = (for reject) reason
 * 
 * Response (JSON): { ok, msg }
 */

require_once 'inc/auth.php';
if (file_exists('inc/otp-helper.php')) require_once 'inc/otp-helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Invalid method']);
    exit;
}

// Must be admin
if (!logged_in() || role() !== 'admin') {
    echo json_encode(['ok' => false, 'msg' => 'Admin access required']);
    exit;
}

$admin_uid = (int)$_SESSION['uid'];
$action    = $_POST['action'] ?? '';
$user_id   = (int)($_POST['user_id'] ?? 0);
$reason    = trim($_POST['reason'] ?? '');

if (!$user_id) {
    echo json_encode(['ok' => false, 'msg' => 'User ID missing']);
    exit;
}

// Fetch target user
$stmt = $db->prepare("SELECT id, name, email, role, verification_status, is_verified FROM users WHERE id=?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();

if (!$target) {
    echo json_encode(['ok' => false, 'msg' => 'User not found']);
    exit;
}

// ============ ENSURE COLUMNS ============
function aja_col_exists($db, $table, $col) {
    $t = $db->real_escape_string($table);
    $c = $db->real_escape_string($col);
    $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $r && $r->num_rows > 0;
}

$extra_cols = [
    'verification_status' => "VARCHAR(20) NOT NULL DEFAULT 'none'",
    'rejection_reason'    => "TEXT NULL",
    'verified_at'         => "DATETIME NULL",
    'verified_by'         => "INT NULL",
];
foreach ($extra_cols as $col => $def) {
    if (!aja_col_exists($db, 'users', $col)) {
        try { $db->query("ALTER TABLE users ADD COLUMN `$col` $def"); } catch (Throwable $e) {}
    }
}

// ============ HANDLE ACTIONS ============
$msg = '';
$ok  = false;

try {
    if ($action === 'approve_user' || $action === 'approve_employer') {
        $up = $db->prepare("UPDATE users SET is_verified=1, verification_status='approved', rejection_reason=NULL, verified_at=NOW(), verified_by=? WHERE id=?");
        $up->bind_param('ii', $admin_uid, $user_id);
        if ($up->execute()) {
            $ok = true;
            $msg = "User '" . $target['name'] . "' approved successfully.";

            // Notify user
            try {
                $n_title = "Account Verified ✅";
                $n_msg   = "Congratulations! Your account has been verified by the admin. A blue tick will now appear on your profile.";
                $n_link  = ($target['role'] === 'employer') ? 'employer-settings.php?tab=verification' : 'seeker-settings.php?tab=verification';
                $n = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
                $n->bind_param('isss', $user_id, $n_title, $n_msg, $n_link);
                $n->execute();
            } catch (Throwable $e) { error_log("Approve notify: " . $e->getMessage()); }

            // Send email (optional)
            if (!empty($target['email']) && file_exists('inc/mailer.php')) {
                require_once 'inc/mailer.php';
                if (function_exists('send_email_template')) {
                    @send_email_template(
                        $target['email'],
                        "Your Account Has Been Verified",
                        "Hi " . $target['name'] . ", you're verified! ✅",
                        "<p>Your account has been successfully verified by our team.</p><p>A blue tick will now appear on your profile, increasing trust and visibility.</p>"
                    );
                }
            }
        } else {
            $msg = "Update failed: " . $db->error;
        }

    } elseif ($action === 'reject_user' || $action === 'reject_employer') {
        if ($reason === '') $reason = 'Document unclear or invalid.';
        $up = $db->prepare("UPDATE users SET is_verified=0, verification_status='rejected', rejection_reason=?, verified_at=NOW(), verified_by=? WHERE id=?");
        $up->bind_param('sii', $reason, $admin_uid, $user_id);
        if ($up->execute()) {
            $ok = true;
            $msg = "User '" . $target['name'] . "' rejected.";

            // Notify
            try {
                $n_title = "Verification Rejected";
                $n_msg   = "Your verification request was rejected. Reason: " . $reason . ". Please upload a clearer document and try again.";
                $n_link  = ($target['role'] === 'employer') ? 'employer-settings.php?tab=verification' : 'seeker-settings.php?tab=verification';
                $n = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
                $n->bind_param('isss', $user_id, $n_title, $n_msg, $n_link);
                $n->execute();
            } catch (Throwable $e) { error_log("Reject notify: " . $e->getMessage()); }

            // Email
            if (!empty($target['email']) && file_exists('inc/mailer.php')) {
                require_once 'inc/mailer.php';
                if (function_exists('send_email_template')) {
                    @send_email_template(
                        $target['email'],
                        "Verification Request Rejected",
                        "Verification not approved",
                        "<p>Hi " . htmlspecialchars($target['name']) . ",</p><p>Unfortunately, your verification request was rejected.</p><p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p><p>You can upload a clearer document and try again from your settings page.</p>"
                    );
                }
            }
        } else {
            $msg = "Update failed: " . $db->error;
        }

    } else {
        $msg = "Unknown action: $action";
    }
} catch (Throwable $e) {
    $ok = false;
    $msg = "Error: " . $e->getMessage();
    error_log("ajax-admin-action: " . $e->getMessage());
}

echo json_encode(['ok' => $ok, 'msg' => $msg]);