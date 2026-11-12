<?php
require_once 'inc/auth.php';
require_once 'inc/otp-helper.php';
if (!logged_in()) { header("Location: login.php"); exit; }

$title = "Verify OTP — " . (defined('SITE_NAME') ? SITE_NAME : 'Local Jobs');
$uid   = (int)$_SESSION['uid'];

$purpose      = $_GET['purpose'] ?? 'verify';
$contact_type = $_GET['type']    ?? 'phone';
$contact      = trim($_GET['contact'] ?? '');

$valid_purposes = ['verify', 'change_phone', 'change_email', 'login', 'reset'];
if (!in_array($purpose, $valid_purposes)) $purpose = 'verify';
if (!in_array($contact_type, ['phone', 'email'])) $contact_type = 'phone';

// If no contact provided, fetch from users table
if (empty($contact)) {
    try {
        $u = $db->prepare("SELECT phone, email FROM users WHERE id=?");
        $u->bind_param('i', $uid);
        $u->execute();
        $user_row = $u->get_result()->fetch_assoc();
        if ($user_row) {
            $contact = $contact_type === 'phone'
                ? ($user_row['phone'] ?? '')
                : ($user_row['email'] ?? '');
        }
    } catch (Throwable $e) {
        error_log("verify-otp fetch contact failed: " . $e->getMessage());
    }
}

// Mask contact for display
$display_contact = $contact;
if ($contact_type === 'phone' && strlen($contact) >= 6) {
    $display_contact = str_repeat('•', strlen($contact) - 4) . substr($contact, -4);
} elseif ($contact_type === 'email' && strpos($contact, '@') !== false) {
    [$user_part, $domain] = explode('@', $contact, 2);
    $display_contact = substr($user_part, 0, 2) . str_repeat('•', max(2, strlen($user_part) - 2)) . '@' . $domain;
}

include 'inc/head.php';
?>

<style>
.otp-container { max-width: 480px; margin: 60px auto; padding: 0 20px; }
.otp-card { background: #fff; border-radius: 14px; padding: 40px 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #e5e7eb; }
.otp-icon { width: 70px; height: 70px; background: #eff6ff; color: #2563eb; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 15px; }
.otp-input { width: 100%; padding: 16px; font-size: 1.6rem; text-align: center; letter-spacing: 12px; border: 2px solid #d1d5db; border-radius: 10px; font-weight: 700; transition: all 0.2s; font-family: 'Courier New', monospace; }
.otp-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37,99,235,0.15); }
.btn-verify { width: 100%; padding: 14px; font-size: 1rem; background: #2563eb; color: #fff; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-verify:hover { background: #1e40af; }
.btn-verify:disabled { background: #94a3b8; cursor: not-allowed; }
.msg-box { padding: 12px 14px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
.msg-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.msg-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.msg-info    { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
.resend-area { display: flex; gap: 10px; margin-top: 15px; }
.btn-secondary { flex: 1; padding: 10px; font-size: 0.85rem; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 8px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; }
.btn-secondary:disabled { background: #e5e7eb; color: #9ca3af; cursor: not-allowed; }
.dev-note { background: #fef3c7; border-left: 3px solid #f59e0b; padding: 12px 14px; border-radius: 6px; margin-top: 20px; font-size: 0.82rem; color: #92400e; }
</style>

<div class="otp-container">
    <div class="otp-card">
        <div style="text-align: center; margin-bottom: 25px;">
            <div class="otp-icon"><i class="fas fa-shield-alt"></i></div>
            <h2 style="font-size: 1.4rem; margin: 0 0 8px; color: #111827;">Verify OTP</h2>
            <p style="color: #6b7280; font-size: 0.9rem; margin: 0; line-height: 1.5;">
                Enter the 6-digit code sent to your <?= $contact_type === 'phone' ? 'phone' : 'email' ?>
                <?php if ($display_contact): ?>
                    <br><strong style="color:#2563eb;"><?= h($display_contact) ?></strong>
                <?php endif; ?>
            </p>
        </div>

        <div id="otpMessage" class="msg-box" style="display: none;"></div>

        <form id="otpForm" onsubmit="return verifyOtp(event)">
            <input type="hidden" name="purpose" value="<?= h($purpose) ?>">

            <div style="margin-bottom: 20px;">
                <label style="font-weight: 600; margin-bottom: 8px; display: block; font-size: 0.9rem; color: #374151;">
                    Enter OTP <span style="color:#dc2626;">*</span>
                </label>
                <input
                    type="text"
                    name="otp_code"
                    id="otpCode"
                    class="otp-input"
                    maxlength="6"
                    inputmode="numeric"
                    placeholder="••••••"
                    autocomplete="one-time-code"
                    required
                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6)">
            </div>

            <button type="submit" id="verifyBtn" class="btn-verify">
                <i class="fas fa-check-circle"></i> Verify OTP
            </button>

            <div class="resend-area">
                <button type="button" id="resendBtn" onclick="resendOtp()" class="btn-secondary" disabled>
                    <i class="fas fa-redo"></i> <span id="resendText">Resend (30s)</span>
                </button>
                <a href="seeker-settings.php?tab=security" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>

        <?php if (defined('OTP_DEV_MODE') && OTP_DEV_MODE): ?>
            <div class="dev-note">
                <strong><i class="fas fa-flask"></i> DEV MODE:</strong>
                OTP is logged to <code>storage/logs/otp-sms.log</code> (or <code>otp-email.log</code>).
                Also shown in browser console after sending.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const PURPOSE = <?= json_encode($purpose) ?>;
const TYPE    = <?= json_encode($contact_type) ?>;
const CONTACT = <?= json_encode($contact) ?>;

let resendTimer = null;
let countdown   = 30;

function showMessage(msg, type) {
    type = type || 'error';
    const box = document.getElementById('otpMessage');
    box.style.display = 'flex';
    box.className = 'msg-box msg-' + type;
    let icon = 'fa-info-circle';
    if (type === 'error')   icon = 'fa-exclamation-circle';
    if (type === 'success') icon = 'fa-check-circle';
    box.innerHTML = '<i class="fas ' + icon + '"></i> <span>' + msg + '</span>';
}

function verifyOtp(e) {
    e.preventDefault();
    const code = document.getElementById('otpCode').value.trim();
    if (code.length !== 6) {
        showMessage('Please enter 6-digit OTP', 'error');
        return false;
    }

    const btn = document.getElementById('verifyBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

    const fd = new FormData();
    fd.append('otp_code', code);
    fd.append('purpose', PURPOSE);
    fd.append('contact_type', TYPE);
    if (CONTACT) fd.append('contact', CONTACT);

    fetch('ajax-verify-otp.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                showMessage(data.msg || 'Verified!', 'success');
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Verified!';
                if (data.redirect) {
                    setTimeout(() => { window.location.href = data.redirect; }, 1200);
                }
            } else {
                showMessage(data.msg || 'Invalid OTP', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Verify OTP';
                document.getElementById('otpCode').focus();
            }
        })
        .catch(err => {
            console.error(err);
            showMessage('Network error. Please try again.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Verify OTP';
        });

    return false;
}

function startResendCountdown() {
    countdown = 30;
    const btn = document.getElementById('resendBtn');
    const txt = document.getElementById('resendText');
    if (!btn || !txt) return;
    btn.disabled = true;
    txt.textContent = 'Resend (' + countdown + 's)';
    if (resendTimer) clearInterval(resendTimer);
    resendTimer = setInterval(() => {
        countdown--;
        if (countdown <= 0) {
            clearInterval(resendTimer);
            btn.disabled = false;
            txt.textContent = 'Resend';
        } else {
            txt.textContent = 'Resend (' + countdown + 's)';
        }
    }, 1000);
}

function resendOtp() {
    if (!CONTACT) {
        showMessage('No contact to resend. Please go back.', 'error');
        return;
    }
    const btn = document.getElementById('resendBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

    const fd = new FormData();
    fd.append('contact', CONTACT);
    fd.append('contact_type', TYPE);
    fd.append('purpose', PURPOSE);

    fetch('ajax-send-otp.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            console.log('Resend response:', data);
            if (data.ok) {
                let msg = 'New OTP sent!';
                if (data.debug_otp) msg += ' (DEV: ' + data.debug_otp + ')';
                showMessage(msg, 'success');
                startResendCountdown();
            } else {
                showMessage(data.msg || 'Failed to resend', 'error');
                btn.disabled = false;
            }
            btn.innerHTML = '<i class="fas fa-redo"></i> <span id="resendText">Resend</span>';
        })
        .catch(err => {
            console.error(err);
            showMessage('Network error.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-redo"></i> Resend';
        });
}

// Auto-focus + auto-send OTP on page load (if contact exists)
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('otpCode');
    if (input) input.focus();
    startResendCountdown();

    // Auto-trigger send OTP on first load if contact exists
    if (CONTACT) {
        const fd = new FormData();
        fd.append('contact', CONTACT);
        fd.append('contact_type', TYPE);
        fd.append('purpose', PURPOSE);
        fetch('ajax-send-otp.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                console.log('Auto-send response:', data);
                if (data.ok) {
                    let msg = 'OTP sent!';
                    if (data.debug_otp) msg += ' (DEV OTP: ' + data.debug_otp + ')';
                    showMessage(msg, 'success');
                    if (data.debug_otp) {
                        console.log('%c🔑 DEV OTP: ' + data.debug_otp, 'background:#fef3c7; color:#92400e; padding:4px 8px; border-radius:4px; font-weight:bold;');
                    }
                } else {
                    showMessage(data.msg || 'Failed to send OTP', 'error');
                }
            })
            .catch(err => {
                console.error('Auto-send error:', err);
            });
    }
});
</script>

<?php include 'inc/foot.php'; ?>