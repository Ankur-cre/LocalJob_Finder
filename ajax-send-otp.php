<?php
/**
 * AJAX: Send OTP
 * Path: ajax-send-otp.php
 * 
 * Request (POST):
 *   contact_type = phone | email
 *   contact      = actual phone/email
 *   purpose      = verify | change_phone | change_email | login | reset
 * 
 * Response (JSON):
 *   { ok: true/false, msg: "...", debug_otp: "..." (only in DEV) }
 */

require_once 'inc/auth.php';
require_once 'inc/otp-helper.php';

header('Content-Type: application/json; charset=utf-8');

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request method']);
    exit;
}

// Must be logged in
if (!logged_in()) {
    echo json_encode(['ok' => false, 'msg' => 'Please login first']);
    exit;
}

$uid          = (int)$_SESSION['uid'];
$contact_type = $_POST['contact_type'] ?? '';
$contact      = trim($_POST['contact'] ?? '');
$purpose      = $_POST['purpose'] ?? 'verify';

// Validate purpose
$valid_purposes = ['verify', 'change_phone', 'change_email', 'login', 'reset'];
if (!in_array($purpose, $valid_purposes)) $purpose = 'verify';

// Validate contact_type
if (!in_array($contact_type, ['phone', 'email'])) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid contact type']);
    exit;
}

// Validate contact
if ($contact === '') {
    echo json_encode(['ok' => false, 'msg' => 'Please enter ' . ($contact_type === 'phone' ? 'phone number' : 'email')]);
    exit;
}

if ($contact_type === 'phone') {
    $digits = preg_replace('/\D/', '', $contact);
    if (strlen($digits) < 10 || strlen($digits) > 15) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid phone number (10-15 digits)']);
        exit;
    }
    $contact = $digits;
} else {
    if (!filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid email address']);
        exit;
    }
}

// Rate limit: max 3 OTPs per 5 minutes for same user+purpose
try {
    otp_ensure_table($db);
    $stmt = $db->prepare("SELECT COUNT(*) c FROM otp_codes WHERE user_id=? AND purpose=? AND created_at > NOW() - INTERVAL 5 MINUTE");
    $stmt->bind_param('is', $uid, $purpose);
    $stmt->execute();
    $recent = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    if ($recent >= 3) {
        echo json_encode(['ok' => false, 'msg' => 'Too many requests. Please wait 5 minutes before trying again.']);
        exit;
    }
} catch (Throwable $e) {
    // Continue even if rate-limit check fails
    error_log("Rate limit check failed: " . $e->getMessage());
}

// Save OTP to DB
$save = otp_save($db, $uid, $contact, $contact_type, $purpose);
if (!$save['ok']) {
    echo json_encode(['ok' => false, 'msg' => 'Failed to generate OTP: ' . ($save['error'] ?? 'unknown error')]);
    exit;
}

// Send OTP via SMS or Email
$dispatch = otp_dispatch($contact, $save['otp'], $contact_type, $purpose);

$response = [
    'ok'  => (bool)$dispatch['ok'],
    'msg' => $dispatch['ok']
        ? ('OTP sent to your ' . ($contact_type === 'phone' ? 'phone' : 'email'))
        : ('Failed to send OTP: ' . ($dispatch['msg'] ?? 'unknown error')),
];

// DEV mode: include OTP in response for easy testing
if (defined('OTP_DEV_MODE') && OTP_DEV_MODE) {
    $response['debug_otp']  = $save['otp'];
    $response['debug_note'] = 'DEV mode: OTP shown here + logged to storage/logs/';
}

echo json_encode($response);