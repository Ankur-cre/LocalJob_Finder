<?php
/**
 * AJAX: Verify OTP
 * Path: ajax-verify-otp.php
 * 
 * Request (POST):
 *   otp_code     = 6-digit code
 *   purpose      = verify | change_phone | change_email | login | reset
 *   contact      = (optional) new contact to save after verify
 *   contact_type = (optional) phone | email
 * 
 * Response (JSON):
 *   { ok: true/false, msg: "...", redirect: "..." (optional) }
 */

require_once 'inc/auth.php';
require_once 'inc/otp-helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request method']);
    exit;
}

if (!logged_in()) {
    echo json_encode(['ok' => false, 'msg' => 'Please login first']);
    exit;
}

$uid      = (int)$_SESSION['uid'];
$otp_code = trim($_POST['otp_code'] ?? '');
$purpose  = $_POST['purpose'] ?? 'verify';

// Validate purpose
$valid_purposes = ['verify', 'change_phone', 'change_email', 'login', 'reset'];
if (!in_array($purpose, $valid_purposes)) $purpose = 'verify';

if ($otp_code === '') {
    echo json_encode(['ok' => false, 'msg' => 'Please enter OTP']);
    exit;
}

if (!preg_match('/^\d{6}$/', $otp_code)) {
    echo json_encode(['ok' => false, 'msg' => 'OTP must be exactly 6 digits']);
    exit;
}

// Verify OTP
$verify = otp_verify($db, $uid, $otp_code, $purpose);
if (!$verify['ok']) {
    echo json_encode(['ok' => false, 'msg' => $verify['error'] ?? 'Invalid OTP']);
    exit;
}

// OTP valid — take action based on purpose
try {
    // Fetch the OTP row we just used to know which contact was verified
    $stmt = $db->prepare("SELECT contact, contact_type FROM otp_codes WHERE user_id=? AND purpose=? AND is_used=1 ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('is', $uid, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    $contact      = $row['contact']      ?? null;
    $contact_type = $row['contact_type'] ?? null;

    if ($purpose === 'verify' || $purpose === 'login') {
        // Account verification — mark user verified
        if ($contact_type === 'phone' && $contact) {
            $up = $db->prepare("UPDATE users SET mobile_verified=1 WHERE id=?");
            if ($up) { $up->bind_param('i', $uid); $up->execute(); }
        } elseif ($contact_type === 'email' && $contact) {
            $up = $db->prepare("UPDATE users SET email_verified=1 WHERE id=?");
            if ($up) { $up->bind_param('i', $uid); $up->execute(); }
        }
    } elseif ($purpose === 'change_phone') {
        if ($contact) {
            $up = $db->prepare("UPDATE users SET phone=?, mobile_verified=1 WHERE id=?");
            if ($up) { $up->bind_param('si', $contact, $uid); $up->execute(); }
        }
    } elseif ($purpose === 'change_email') {
        if ($contact) {
            $up = $db->prepare("UPDATE users SET email=?, email_verified=1 WHERE id=?");
            if ($up) { $up->bind_param('si', $contact, $uid); $up->execute(); }
        }
    }

    // Determine redirect
    $redirect = 'seeker-settings.php?tab=security&pmsg=' . urlencode('Contact verified successfully!');

    echo json_encode([
        'ok'       => true,
        'msg'      => 'OTP verified successfully!',
        'redirect' => $redirect,
    ]);
} catch (Throwable $e) {
    error_log("OTP post-verify update failed: " . $e->getMessage());
    echo json_encode([
        'ok'  => true,
        'msg' => 'OTP verified, but contact update failed. Please contact support.',
    ]);
}