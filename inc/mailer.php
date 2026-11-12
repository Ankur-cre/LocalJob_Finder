<?php
/**
 * Email Helper — inc/mailer.php
 * 
 * Central email sending function. Supports:
 *  - PHPMailer (recommended for production, SMTP)
 *  - PHP native mail() (fallback for WAMP testing)
 */

if (!function_exists('send_email')) {
    /**
     * Send an email.
     * 
     * @param string $to       Recipient email
     * @param string $subject  Subject line
     * @param string $body     HTML body
     * @param string $from     (optional) from email
     * @return array ['ok'=>bool, 'msg'=>string]
     */
    function send_email($to, $subject, $body, $from = '') {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'msg' => 'Invalid recipient email'];
        }

        $site_name = defined('SITE_NAME') ? SITE_NAME : 'Local Jobs';
        $from_email = $from ?: (defined('MAIL_FROM') ? MAIL_FROM : ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));

        // ============ OPTION 1: PHPMailer (if installed via Composer) ============
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            try {
                require_once __DIR__ . '/../vendor/autoload.php';
                if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = defined('SMTP_USER') ? SMTP_USER : '';
                    $mail->Password   = defined('SMTP_PASS') ? SMTP_PASS : '';
                    $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
                    $mail->Port       = defined('SMTP_PORT')   ? SMTP_PORT   : 587;
                    $mail->setFrom($from_email, $site_name);
                    $mail->addAddress($to);
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body    = $body;
                    $mail->AltBody = strip_tags($body);
                    $mail->send();
                    return ['ok' => true, 'msg' => 'Email sent via PHPMailer'];
                }
            } catch (Throwable $e) {
                error_log("PHPMailer error: " . $e->getMessage());
                // Fall through to native mail()
            }
        }

        // ============ OPTION 2: DEV MODE — log to file ============
        if (defined('OTP_DEV_MODE') && OTP_DEV_MODE) {
            $log_dir = __DIR__ . '/../storage/logs';
            if (!is_dir($log_dir)) @mkdir($log_dir, 0777, true);
            $log = "=== EMAIL ===\n"
                 . "To: $to\n"
                 . "From: $from_email\n"
                 . "Subject: $subject\n"
                 . "Body:\n$body\n"
                 . "Time: " . date('Y-m-d H:i:s') . "\n"
                 . "=============\n\n";
            @file_put_contents($log_dir . '/emails.log', $log, FILE_APPEND);
            return ['ok' => true, 'msg' => 'DEV mode: Email logged to file'];
        }

        // ============ OPTION 3: PHP native mail() ============
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: $site_name <$from_email>\r\n";

        $sent = @mail($to, $subject, $body, $headers);
        return ['ok' => (bool)$sent, 'msg' => $sent ? 'Email sent via PHP mail()' : 'Email failed'];
    }
}

// ============ OPTIONAL: Send HTML email with template ============
if (!function_exists('send_email_template')) {
    function send_email_template($to, $subject, $heading, $message_html, $cta_text = '', $cta_url = '') {
        $site_name = defined('SITE_NAME') ? SITE_NAME : 'Local Jobs';
        $primary   = '#2563eb';

        $cta = '';
        if ($cta_text && $cta_url) {
            $cta = "<p style='margin-top:24px;'><a href='$cta_url' style='display:inline-block; background:$primary; color:#fff; padding:12px 24px; border-radius:8px; text-decoration:none; font-weight:600;'>$cta_text</a></p>";
        }

        $body = "
        <div style='font-family:Segoe UI,Tahoma,sans-serif; max-width:600px; margin:0 auto; padding:20px; background:#f4f7fc;'>
            <div style='background:#fff; border-radius:12px; padding:30px; box-shadow:0 2px 8px rgba(0,0,0,0.05);'>
                <h2 style='color:#111827; margin:0 0 16px 0;'>$heading</h2>
                <div style='color:#374151; line-height:1.6; font-size:15px;'>
                    $message_html
                </div>
                $cta
                <hr style='border:none; border-top:1px solid #e5e7eb; margin:24px 0;'>
                <p style='font-size:12px; color:#9ca3af; margin:0;'>— $site_name Team</p>
            </div>
        </div>";

        return send_email($to, $subject, $body);
    }
}