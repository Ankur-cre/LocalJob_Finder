<?php
/**
 * OTP Helper Functions
 * Path: inc/otp-helper.php
 * 
 * Real SMS (Fast2SMS/MSG91) + Real Email (PHPMailer SMTP).
 * Admin-toggleable via site_settings.otp_required.
 */

// ============ OTP CONFIG ============
if (!defined('OTP_LENGTH'))       define('OTP_LENGTH', 6);
if (!defined('OTP_EXPIRY_MIN'))   define('OTP_EXPIRY_MIN', 10);
if (!defined('OTP_MAX_ATTEMPTS')) define('OTP_MAX_ATTEMPTS', 5);

// ============ DEV MODE (default OFF — real SMS/email bhejega) ============
// Testing ke liye: config.php mein define('OTP_DEV_MODE', true) kar do
if (!defined('OTP_DEV_MODE')) {
    define('OTP_DEV_MODE', false);
}

// ============ AUTO-CREATE OTP TABLE ============
if (!function_exists('otp_ensure_table')) {
    function otp_ensure_table($db) {
        try {
            $db->query("
                CREATE TABLE IF NOT EXISTS otp_codes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    contact VARCHAR(150) NOT NULL,
                    contact_type ENUM('phone','email') NOT NULL,
                    otp_code VARCHAR(10) NOT NULL,
                    purpose VARCHAR(50) NOT NULL DEFAULT 'verify',
                    attempts INT NOT NULL DEFAULT 0,
                    is_used TINYINT(1) NOT NULL DEFAULT 0,
                    expires_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user (user_id),
                    INDEX idx_contact (contact),
                    INDEX idx_code (otp_code)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (Throwable $e) {
            error_log("otp_ensure_table failed: " . $e->getMessage());
        }
    }
}

// ============ GENERATE OTP ============
if (!function_exists('otp_generate')) {
    function otp_generate($length = OTP_LENGTH) {
        $max = (int)str_repeat('9', $length);
        return str_pad((string)random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}

// ============ SAVE OTP TO DB ============
if (!function_exists('otp_save')) {
    function otp_save($db, $user_id, $contact, $contact_type, $purpose = 'verify', $otp_code = '') {
        otp_ensure_table($db);

        if (!in_array($contact_type, ['phone', 'email'])) {
            return ['ok' => false, 'otp' => '', 'error' => 'Invalid contact type'];
        }
        $contact = trim($contact);
        if ($contact === '') {
            return ['ok' => false, 'otp' => '', 'error' => 'Contact is empty'];
        }

        $otp_code = $otp_code !== '' ? $otp_code : otp_generate();

        // Invalidate older OTPs of same purpose for this user
        try {
            $inv = $db->prepare("UPDATE otp_codes SET is_used=1 WHERE user_id=? AND purpose=? AND is_used=0");
            $inv->bind_param('is', $user_id, $purpose);
            $inv->execute();
        } catch (Throwable $e) {}

        $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MIN * 60);

        try {
            $stmt = $db->prepare("INSERT INTO otp_codes (user_id, contact, contact_type, otp_code, purpose, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isssss', $user_id, $contact, $contact_type, $otp_code, $purpose, $expires);
            if ($stmt->execute()) {
                return ['ok' => true, 'otp' => $otp_code, 'error' => null];
            }
            return ['ok' => false, 'otp' => '', 'error' => $db->error];
        } catch (Throwable $e) {
            return ['ok' => false, 'otp' => '', 'error' => $e->getMessage()];
        }
    }
}

// ============ VERIFY OTP ============
if (!function_exists('otp_verify')) {
    function otp_verify($db, $user_id, $otp_code, $purpose = 'verify') {
        otp_ensure_table($db);
        $otp_code = trim($otp_code);

        if ($otp_code === '') {
            return ['ok' => false, 'error' => 'OTP is empty'];
        }

        try {
            $stmt = $db->prepare("
                SELECT id, attempts, expires_at 
                FROM otp_codes 
                WHERE user_id=? AND otp_code=? AND purpose=? AND is_used=0
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->bind_param('iss', $user_id, $otp_code, $purpose);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            if (!$row) {
                return ['ok' => false, 'error' => 'Invalid OTP code'];
            }

            if (strtotime($row['expires_at']) < time()) {
                $up = $db->prepare("UPDATE otp_codes SET is_used=1 WHERE id=?");
                $up->bind_param('i', $row['id']);
                $up->execute();
                return ['ok' => false, 'error' => 'OTP has expired. Please request a new one.'];
            }

            if ((int)$row['attempts'] >= OTP_MAX_ATTEMPTS) {
                return ['ok' => false, 'error' => 'Too many wrong attempts. Please request a new OTP.'];
            }

            $up = $db->prepare("UPDATE otp_codes SET is_used=1, attempts=attempts+1 WHERE id=?");
            $up->bind_param('i', $row['id']);
            $up->execute();

            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

// ============ MARK WRONG ATTEMPT ============
if (!function_exists('otp_log_wrong_attempt')) {
    function otp_log_wrong_attempt($db, $user_id, $purpose = 'verify') {
        try {
            $stmt = $db->prepare("UPDATE otp_codes SET attempts=attempts+1 WHERE user_id=? AND purpose=? AND is_used=0 ORDER BY id DESC LIMIT 1");
            $stmt->bind_param('is', $user_id, $purpose);
            $stmt->execute();
        } catch (Throwable $e) {}
    }
}

// ============ CLEANUP OLD OTPs ============
if (!function_exists('otp_cleanup_old')) {
    function otp_cleanup_old($db, $days = 7) {
        try {
            $db->query("DELETE FROM otp_codes WHERE created_at < NOW() - INTERVAL $days DAY");
        } catch (Throwable $e) {}
    }
}

// ============ ADMIN TOGGLE: OTP ENABLED? ============
if (!function_exists('otp_is_enabled')) {
    function otp_is_enabled($db) {
        try {
            $res = $db->query("SELECT setting_value FROM site_settings WHERE setting_key='otp_required' LIMIT 1");
            if ($res && $res->num_rows > 0) {
                $val = $res->fetch_assoc()['setting_value'];
                return ($val === '1' || $val === 1 || $val === true);
            }
        } catch (Throwable $e) {}
        return false; // Default OFF
    }
}

// ============ SEND OTP VIA SMS ============
if (!function_exists('otp_send_sms')) {
    function otp_send_sms($phone, $otp, $purpose = 'verify') {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 10) $phone = '91' . $phone;

        // DEV MODE: log only
        if (defined('OTP_DEV_MODE') && OTP_DEV_MODE) {
            $log_dir = __DIR__ . '/../storage/logs';
            if (!is_dir($log_dir)) @mkdir($log_dir, 0777, true);
            @file_put_contents(
                $log_dir . '/otp-sms.log',
                date('Y-m-d H:i:s') . " | To: +$phone | OTP: $otp | Purpose: $purpose\n",
                FILE_APPEND
            );
            return ['ok' => true, 'msg' => 'DEV mode: OTP logged to file'];
        }

        $provider = defined('SMS_PROVIDER') ? SMS_PROVIDER : 'none';

        // ============ FAST2SMS ============
        if ($provider === 'fast2sms') {
            if (!defined('FAST2SMS_API_KEY') || FAST2SMS_API_KEY === '' || strpos(FAST2SMS_API_KEY, 'YOUR_') === 0) {
                return ['ok' => false, 'msg' => 'Fast2SMS API key not configured. Set FAST2SMS_API_KEY in config.php'];
            }

            $url = 'https://www.fast2sms.com/dev/bulkV2';
            $data = [
                'route'    => 'q',
                'message'  => "Your OTP code is {$otp}. Valid for " . OTP_EXPIRY_MIN . " minutes. Do not share this code.",
                'language' => 'english',
                'flash'    => 0,
                'numbers'  => $phone,
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($data),
                CURLOPT_HTTPHEADER     => ['authorization: ' . FAST2SMS_API_KEY],
                CURLOPT_TIMEOUT        => 15,
            ]);
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($err) return ['ok' => false, 'msg' => 'cURL error: ' . $err];

            $json = json_decode($resp, true);
            if ($http >= 200 && $http < 300 && isset($json['return']) && $json['return'] === true) {
                return ['ok' => true, 'msg' => 'SMS sent successfully'];
            }
            return ['ok' => false, 'msg' => 'Fast2SMS failed: ' . ($json['message'][0] ?? $resp)];
        }

        // ============ MSG91 ============
        if ($provider === 'msg91') {
            if (!defined('MSG91_AUTH_KEY') || MSG91_AUTH_KEY === '' || strpos(MSG91_AUTH_KEY, 'YOUR_') === 0) {
                return ['ok' => false, 'msg' => 'MSG91 auth key not configured'];
            }
            if (!defined('MSG91_TEMPLATE_ID') || MSG91_TEMPLATE_ID === '') {
                return ['ok' => false, 'msg' => 'MSG91 template ID not configured'];
            }

            $url = 'https://control.msg91.com/api/v5/otp';
            $postfields = http_build_query([
                'template_id' => MSG91_TEMPLATE_ID,
                'mobile'      => $phone,
                'authkey'     => MSG91_AUTH_KEY,
                'otp'         => $otp,
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $postfields,
                CURLOPT_TIMEOUT        => 15,
            ]);
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $json = json_decode($resp, true);
            if ($http >= 200 && $http < 300 && isset($json['type']) && $json['type'] === 'success') {
                return ['ok' => true, 'msg' => 'SMS sent via MSG91'];
            }
            return ['ok' => false, 'msg' => 'MSG91 failed: ' . $resp];
        }

        return ['ok' => false, 'msg' => 'No SMS provider configured. Set SMS_PROVIDER = "fast2sms" or "msg91" in config.php, OR enable OTP_DEV_MODE for testing.'];
    }
}

// ============ SEND OTP VIA EMAIL ============
if (!function_exists('otp_send_email')) {
    function otp_send_email($email, $otp, $purpose = 'verify') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'msg' => 'Invalid email'];
        }

        // DEV MODE
        if (defined('OTP_DEV_MODE') && OTP_DEV_MODE) {
            $log_dir = __DIR__ . '/../storage/logs';
            if (!is_dir($log_dir)) @mkdir($log_dir, 0777, true);
            @file_put_contents(
                $log_dir . '/otp-email.log',
                date('Y-m-d H:i:s') . " | To: $email | OTP: $otp | Purpose: $purpose\n",
                FILE_APPEND
            );
            return ['ok' => true, 'msg' => 'DEV mode: OTP logged to file'];
        }

        // Use inc/mailer.php if present
        if (file_exists(__DIR__ . '/mailer.php')) {
            require_once __DIR__ . '/mailer.php';
            if (function_exists('send_email')) {
                $site_name = defined('SITE_NAME') ? SITE_NAME : 'Local Jobs';
                $subject = "Your OTP Code — $site_name";
                $body = "
                <div style='font-family:Segoe UI,Tahoma,sans-serif; max-width:560px; margin:0 auto; padding:20px; background:#f4f7fc;'>
                    <div style='background:#fff; border-radius:12px; padding:32px; box-shadow:0 2px 8px rgba(0,0,0,0.05);'>
                        <div style='text-align:center; margin-bottom:20px;'>
                            <div style='display:inline-flex; width:60px; height:60px; background:#eff6ff; color:#2563eb; border-radius:50%; align-items:center; justify-content:center; font-size:1.6rem;'>
                                <span style='font-size:28px;'>🔐</span>
                            </div>
                        </div>
                        <h2 style='color:#111827; margin:0 0 10px; text-align:center;'>Verify Your Account</h2>
                        <p style='color:#6b7280; text-align:center; margin:0 0 24px;'>Use the code below to verify:</p>
                        <div style='text-align:center; padding:16px; background:#eff6ff; border-radius:10px; border:2px dashed #2563eb; margin-bottom:24px;'>
                            <span style='font-size:32px; letter-spacing:8px; font-weight:700; color:#1e40af; font-family:monospace;'>$otp</span>
                        </div>
                        <p style='color:#6b7280; font-size:14px; text-align:center; margin:0;'>This code expires in " . OTP_EXPIRY_MIN . " minutes.</p>
                        <p style='color:#9ca3af; font-size:12px; text-align:center; margin:24px 0 0; border-top:1px solid #e5e7eb; padding-top:16px;'>
                            If you did not request this, please ignore this email.
                        </p>
                    </div>
                </div>";
                return send_email($email, $subject, $body);
            }
        }

        // Fallback: PHP mail()
        $site_name = defined('SITE_NAME') ? SITE_NAME : 'Local Jobs';
        $subject = "Your OTP Code";
        $message = "Your OTP is: $otp\nIt expires in " . OTP_EXPIRY_MIN . " minutes.";
        $headers = "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\nContent-Type: text/plain; charset=UTF-8";
        $sent = @mail($email, $subject, $message, $headers);
        return ['ok' => (bool)$sent, 'msg' => $sent ? 'Email sent via mail()' : 'Email failed'];
    }
}

// ============ DISPATCHER ============
if (!function_exists('otp_dispatch')) {
    function otp_dispatch($contact, $otp, $contact_type, $purpose = 'verify') {
        if ($contact_type === 'phone') {
            return otp_send_sms($contact, $otp, $purpose);
        }
        return otp_send_email($contact, $otp, $purpose);
    }
}

// Auto-cleanup old OTPs (5% chance)
if (function_exists('random_int') && random_int(1, 20) === 1) {
    if (isset($db) && $db instanceof mysqli) {
        otp_cleanup_old($db, 7);
    }
}