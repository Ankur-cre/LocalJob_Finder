<?php
// ============================================
// 1. PROCESS FORM SUBMISSIONS (BEFORE ANY HTML)
// ============================================
require_once 'inc/auth.php';
must_role('admin');

// ============ AUTO-CREATE site_settings TABLE ============
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS site_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {
    error_log("site_settings auto-create failed: " . $e->getMessage());
}

// ============ HANDLE POST ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_settings'])) {

    // Toggle fields (checkbox) — 1 or 0
    $toggle_keys = [
        'seeker_show_sub',
        'seeker_show_ads',
        'employer_show_sub',
        'employer_show_ads',
        'otp_required',
        'resume_builder_enabled',
        'employer_show_billing',   // ✅ NEW
    ];

    foreach ($toggle_keys as $k) {
        $val = isset($_POST[$k]) ? '1' : '0';
        $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        if ($stmt) {
            $stmt->bind_param('ss', $k, $val);
            $stmt->execute();
        }
    }

    // Text fields
    $text_keys = [
        'default_language',
        'support_email',
        'support_phone',
        'privacy_policy_text',
        'terms_conditions_text',
        'job_expiry_days',
        'site_tagline',
    ];

    foreach ($text_keys as $k) {
        $val = trim($_POST[$k] ?? '');
        $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        if ($stmt) {
            $stmt->bind_param('ss', $k, $val);
            $stmt->execute();
        }
    }

    header("Location: admin-settings.php?msg=" . urlencode("Global settings updated successfully!"));
    exit;
}

// ============================================
// 2. FETCH SETTINGS
// ============================================
$site_settings = [];
$res = $db->query("SELECT setting_key, setting_value FROM site_settings");
if ($res) {
    while ($row = $res->fetch_assoc()) { $site_settings[$row['setting_key']] = $row['setting_value']; }
}

// Defaults
$defaults = [
    'seeker_show_sub'         => '0',
    'seeker_show_ads'         => '0',
    'employer_show_sub'       => '0',
    'employer_show_ads'       => '0',
    'otp_required'            => '1',
    'resume_builder_enabled'  => '1',
    'employer_show_billing'   => '0',   // ✅ NEW — default OFF
    'default_language'        => 'en',
    'support_email'           => '',
    'support_phone'           => '',
    'privacy_policy_text'     => '',
    'terms_conditions_text'   => '',
    'job_expiry_days'         => '30',
    'site_tagline'            => '',
];
foreach ($defaults as $k => $v) {
    if (!isset($site_settings[$k])) $site_settings[$k] = $v;
}

$msg = $_GET['msg'] ?? '';

// ============================================
// 3. LOAD HEADER
// ============================================
$title = "Settings";
require_once 'inc/admin-head.php';
?>

<h2 class="page-title">Global Settings</h2>

<?php if ($msg): ?>
    <div class="alert alert-ok" style="margin-bottom: 20px;">
        <i class="fas fa-check-circle"></i> <?= h($msg) ?>
    </div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="action_settings" value="1">

    <!-- ============ SECTION 1: PANEL TOGGLES ============ -->
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="font-size: 1.05rem; margin-bottom: 20px; color: #fff;"><i class="fas fa-sliders-h"></i> Panel Toggles</h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">

            <!-- Seeker -->
            <div style="background: var(--bg-body); padding: 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                <h4 style="margin-bottom: 20px; color:var(--accent-green); font-size:0.95rem;"><i class="fas fa-user-tie"></i> Job Seeker Panel</h4>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                    <div>
                        <strong style="display:block; font-size:0.9rem; color:#fff;">Show Subscription Plans</strong>
                        <small style="color:var(--text-muted);">Display plans on seeker dashboard.</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="seeker_show_sub" value="1" <?= $site_settings['seeker_show_sub'] == '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong style="display:block; font-size:0.9rem; color:#fff;">Run Ads</strong>
                        <small style="color:var(--text-muted);">Display ads on seeker dashboard.</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="seeker_show_ads" value="1" <?= $site_settings['seeker_show_ads'] == '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <!-- Employer -->
            <div style="background: var(--bg-body); padding: 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                <h4 style="margin-bottom: 20px; color:var(--accent-blue); font-size:0.95rem;"><i class="fas fa-building"></i> Employer Panel</h4>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                    <div>
                        <strong style="display:block; font-size:0.9rem; color:#fff;">Show Subscription Plans</strong>
                        <small style="color:var(--text-muted);">Display plans on employer dashboard.</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="employer_show_sub" value="1" <?= $site_settings['employer_show_sub'] == '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong style="display:block; font-size:0.9rem; color:#fff;">Run Ads</strong>
                        <small style="color:var(--text-muted);">Display ads on employer dashboard.</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="employer_show_ads" value="1" <?= $site_settings['employer_show_ads'] == '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ SECTION 2: FEATURES ============ -->
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="font-size: 1.05rem; margin-bottom: 20px; color: #fff;"><i class="fas fa-tools"></i> Features Toggle</h3>

        <!-- Resume Builder -->
        <div style="display:flex; justify-content:space-between; align-items:center; padding: 16px; background: var(--bg-body); border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 14px;">
            <div>
                <strong style="display:block; font-size:0.95rem; color:#fff;">
                    <i class="fas fa-file-alt" style="color:var(--accent-green);"></i> Resume Builder (Seeker)
                </strong>
                <small style="color:var(--text-muted); font-size:0.82rem;">
                    ON karne par seeker settings mein "Resume Builder" option dikhega.
                </small>
            </div>
            <label class="switch">
                <input type="checkbox" name="resume_builder_enabled" value="1" <?= $site_settings['resume_builder_enabled'] == '1' ? 'checked' : '' ?>>
                <span class="slider"></span>
            </label>
        </div>

        <!-- Billing (Employer) — ✅ NEW -->
        <div style="display:flex; justify-content:space-between; align-items:center; padding: 16px; background: var(--bg-body); border-radius: 8px; border: 1px solid var(--border-color);">
            <div>
                <strong style="display:block; font-size:0.95rem; color:#fff;">
                    <i class="fas fa-credit-card" style="color:var(--accent-blue);"></i> Billing Section (Employer)
                </strong>
                <small style="color:var(--text-muted); font-size:0.82rem;">
                    ON karne par employer settings mein "Billing" option dikhega. OFF karne par hide ho jayega.
                </small>
            </div>
            <label class="switch">
                <input type="checkbox" name="employer_show_billing" value="1" <?= $site_settings['employer_show_billing'] == '1' ? 'checked' : '' ?>>
                <span class="slider"></span>
            </label>
        </div>
    </div>

    <!-- ============ SECTION 3: LANGUAGE & SITE ============ -->
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="font-size: 1.05rem; margin-bottom: 20px; color: #fff;"><i class="fas fa-globe"></i> Language & Site Info</h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <label style="display:block; font-size:0.9rem; color:#fff; margin-bottom:8px; font-weight:600;">Default Language</label>
                <select name="default_language" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-body); color:#fff;">
                    <option value="en" <?= $site_settings['default_language'] === 'en' ? 'selected' : '' ?>>English</option>
                    <option value="hi" <?= $site_settings['default_language'] === 'hi' ? 'selected' : '' ?>>हिन्दी (Hindi)</option>
                </select>
                <small style="color:var(--text-muted); display:block; margin-top:5px; font-size:0.8rem;">New users ke liye default language.</small>
            </div>

            <div>
                <label style="display:block; font-size:0.9rem; color:#fff; margin-bottom:8px; font-weight:600;">Site Tagline</label>
                <input type="text" name="site_tagline" value="<?= h($site_settings['site_tagline']) ?>" placeholder="e.g. Find Local Jobs Fast" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-body); color:#fff;">
                <small style="color:var(--text-muted); display:block; margin-top:5px; font-size:0.8rem;">Homepage ke neeche dikhta hai.</small>
            </div>

            <div>
                <label style="display:block; font-size:0.9rem; color:#fff; margin-bottom:8px; font-weight:600;">Support Email</label>
                <input type="email" name="support_email" value="<?= h($site_settings['support_email']) ?>" placeholder="support@example.com" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-body); color:#fff;">
            </div>

            <div>
                <label style="display:block; font-size:0.9rem; color:#fff; margin-bottom:8px; font-weight:600;">Support Phone</label>
                <input type="text" name="support_phone" value="<?= h($site_settings['support_phone']) ?>" placeholder="+91 98765 43210" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-body); color:#fff;">
            </div>

            <div>
                <label style="display:block; font-size:0.9rem; color:#fff; margin-bottom:8px; font-weight:600;">Job Expiry (days)</label>
                <input type="number" name="job_expiry_days" value="<?= h($site_settings['job_expiry_days']) ?>" min="1" max="365" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-body); color:#fff;">
                <small style="color:var(--text-muted); display:block; margin-top:5px; font-size:0.8rem;">Job auto-expire kitne din baad ho.</small>
            </div>

            <div>
                <label style="display:block; font-size:0.9rem; color:#fff; margin-bottom:8px; font-weight:600;">OTP Verification</label>
                <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-body); padding:10px 14px; border-radius:8px; border:1px solid var(--border-color);">
                    <div>
                        <strong style="font-size:0.85rem; color:#fff; display:block;">OTP Required</strong>
                        <small style="color:var(--text-muted); font-size:0.75rem;">New users ko OTP verify karna hoga</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="otp_required" value="1" <?= $site_settings['otp_required'] == '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ SECTION 4: PRIVACY POLICY ============ -->
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="font-size: 1.05rem; margin-bottom: 15px; color: #fff;"><i class="fas fa-user-shield"></i> Privacy Policy</h3>
        <label style="display:block; font-size:0.88rem; color:var(--text-muted); margin-bottom:8px;">Ye text <code>privacy.php</code> page par dikhega. HTML allowed.</label>
        <textarea name="privacy_policy_text" rows="8" placeholder="Enter privacy policy text (HTML supported)..." style="width:100%; padding:12px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-body); color:#fff; font-family:monospace; font-size:0.85rem; resize:vertical;"><?= h($site_settings['privacy_policy_text']) ?></textarea>
    </div>

    <!-- ============ SECTION 5: TERMS & CONDITIONS ============ -->
    <div class="card" style="margin-bottom: 20px;">
        <h3 style="font-size: 1.05rem; margin-bottom: 15px; color: #fff;"><i class="fas fa-file-contract"></i> Terms & Conditions</h3>
        <label style="display:block; font-size:0.88rem; color:var(--text-muted); margin-bottom:8px;">Ye text <code>terms.php</code> page par dikhega. HTML allowed.</label>
        <textarea name="terms_conditions_text" rows="8" placeholder="Enter terms & conditions text (HTML supported)..." style="width:100%; padding:12px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-body); color:#fff; font-family:monospace; font-size:0.85rem; resize:vertical;"><?= h($site_settings['terms_conditions_text']) ?></textarea>
    </div>

    <!-- ============ SAVE BUTTON ============ -->
    <div style="text-align: right; margin-bottom: 40px;">
        <button type="submit" class="btn-sm btn-blue" style="padding: 12px 30px; font-size: 0.95rem;">
            <i class="fas fa-save"></i> Save All Settings
        </button>
    </div>
</form>

<!-- Toggle Switch CSS -->
<style>
    .switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #4b5563; transition: .4s; border-radius: 34px; }
    .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: var(--accent-green) !important; }
    input:checked + .slider:before { transform: translateX(20px); }
</style>

<?php include 'inc/foot.php'; ?>