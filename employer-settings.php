<?php
require_once 'inc/auth.php';
require_once 'inc/site-config.php';
require_once 'inc/otp-helper.php';
must_role('employer');
$title = "Settings — " . SITE_NAME;
$uid = (int)$_SESSION['uid'];
$tab = $_GET['tab'] ?? 'account';
$msg = '';
$msg_type = 'info';
$cur_lang = current_lang();

// ============ BILLING ACCESS CHECK ============
$billing_on = defined('BILLING_ENABLED') ? BILLING_ENABLED : false;

if ($tab === 'billing' && !$billing_on) {
    $_SESSION['flash_msg'] = "Billing section is currently disabled by admin.";
    header("Location: employer-settings.php?tab=account");
    exit;
}

// Valid tabs
$valid_tabs = ['account','company','preferences','notifications','security','language','support','delete','verification'];
if ($billing_on) {
    $valid_tabs[] = 'billing';
}
if (!in_array($tab, $valid_tabs)) $tab = 'account';

// ============ HELPER ============
if (!function_exists('column_exists')) {
    function column_exists($db, $table, $column) {
        $table  = $db->real_escape_string($table);
        $column = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && $res->num_rows > 0;
    }
}

// ============ AUTO-CREATE COLUMNS ============
$needed = [
    'is_verified'         => "TINYINT(1) NOT NULL DEFAULT 0",
    'verification_status' => "VARCHAR(20) NOT NULL DEFAULT 'none'",
    'verification_doc'    => "VARCHAR(255) NULL",
    'rejection_reason'    => "TEXT NULL",
    'mobile_verified'     => "TINYINT(1) NOT NULL DEFAULT 0",
    'email_verified'      => "TINYINT(1) NOT NULL DEFAULT 0",
    'profile_photo'       => "VARCHAR(255) NULL",
    'company_logo'        => "VARCHAR(255) NULL",
    'cover_image'         => "VARCHAR(255) NULL",
    'business_category'   => "VARCHAR(100) NULL",
    'business_type'       => "VARCHAR(50) NULL",
    'owner_name'          => "VARCHAR(100) NULL",
    'whatsapp'            => "VARCHAR(20) NULL",
    'website'             => "VARCHAR(200) NULL",
    'gstin'               => "VARCHAR(20) NULL",
    'business_proof'      => "VARCHAR(255) NULL",
    'address_proof'       => "VARCHAR(255) NULL",
];
foreach ($needed as $col => $def) {
    if (!column_exists($db, 'users', $col)) {
        try { $db->query("ALTER TABLE users ADD COLUMN `$col` $def"); } catch (Throwable $e) {}
    }
}

// ============ FETCH USER ============
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) { header("Location: logout.php"); exit; }

// ============ ENSURE user_settings ============
$check_s = $db->prepare("SELECT user_id FROM user_settings WHERE user_id=?");
$check_s->bind_param('i', $uid);
$check_s->execute();
if ($check_s->get_result()->num_rows === 0) {
    $ins_s = $db->prepare("INSERT IGNORE INTO user_settings (user_id) VALUES (?)");
    $ins_s->bind_param('i', $uid);
    $ins_s->execute();
}
$stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id=?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
if (!$settings) {
    $settings = ['email_notifications' => 1, 'sms_notifications' => 0, 'whatsapp_alerts' => 0, 'pref_job_type' => 'full-time', 'pref_location' => '', 'pref_expected_salary' => ''];
}

// ============ HANDLE POST ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. ACCOUNT
    if (isset($_POST['update_account'])) {
        $name = trim($_POST['name'] ?? '');
        $company = trim($_POST['company_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $owner_name = trim($_POST['owner_name'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $website = trim($_POST['website'] ?? '');

        if ($name === '' || $company === '' || $phone === '') {
            $msg = "Name, company, and phone are required.";
            $msg_type = 'err';
        } else {
            $stmt = $db->prepare("UPDATE users SET name=?, company_name=?, phone=?, owner_name=?, whatsapp=?, website=? WHERE id=?");
            $stmt->bind_param('ssssssi', $name, $company, $phone, $owner_name, $whatsapp, $website, $uid);
            if ($stmt->execute()) {
                $msg = "Account information updated!";
                $msg_type = 'ok';
                $user['name'] = $name; $user['company_name'] = $company; $user['phone'] = $phone;
                $user['owner_name'] = $owner_name; $user['whatsapp'] = $whatsapp; $user['website'] = $website;
            }
        }
    }

    // 2. COMPANY PROFILE
    if (isset($_POST['update_company'])) {
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        $about = trim($_POST['about_me'] ?? '');
        $business_category = trim($_POST['business_category'] ?? '');
        $business_type = trim($_POST['business_type'] ?? '');
        $gstin = trim($_POST['gstin'] ?? '');

        $stmt = $db->prepare("UPDATE users SET address=?, city=?, state=?, district=?, pincode=?, about_me=?, business_category=?, business_type=?, gstin=? WHERE id=?");
        $stmt->bind_param('sssssssssi', $address, $city, $state, $district, $pincode, $about, $business_category, $business_type, $gstin, $uid);
        if ($stmt->execute()) {
            $msg = "Company profile updated!";
            $msg_type = 'ok';
            $user['address'] = $address; $user['city'] = $city; $user['state'] = $state;
            $user['district'] = $district; $user['pincode'] = $pincode; $user['about_me'] = $about;
            $user['business_category'] = $business_category; $user['business_type'] = $business_type;
            $user['gstin'] = $gstin;
        }
    }

    // 3. LOGO / COVER UPLOAD
    if (isset($_POST['update_media'])) {
        $dir = __DIR__ . '/storage/company';
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $success = false;
        if (!empty($_FILES['company_logo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $fn = 'logo_' . $uid . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $dir . '/' . $fn)) {
                    if (!empty($user['company_logo']) && file_exists(__DIR__ . '/' . $user['company_logo'])) @unlink(__DIR__ . '/' . $user['company_logo']);
                    $path = 'storage/company/' . $fn;
                    $db->query("UPDATE users SET company_logo='$path', profile_photo='$path' WHERE id=$uid");
                    $user['company_logo'] = $path; $user['profile_photo'] = $path;
                    $success = true;
                }
            }
        }
        if (!empty($_FILES['cover_image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $fn = 'cover_' . $uid . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $dir . '/' . $fn)) {
                    if (!empty($user['cover_image']) && file_exists(__DIR__ . '/' . $user['cover_image'])) @unlink(__DIR__ . '/' . $user['cover_image']);
                    $path = 'storage/company/' . $fn;
                    $db->query("UPDATE users SET cover_image='$path' WHERE id=$uid");
                    $user['cover_image'] = $path;
                    $success = true;
                }
            }
        }
        if ($success) {
            $msg = "Media updated!";
            $msg_type = 'ok';
        }
    }

    // 4. CHANGE PASSWORD
    if (isset($_POST['change_password'])) {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        if (strlen($new) < 6) {
            $msg = "New password must be at least 6 characters.";
            $msg_type = 'err';
        } elseif (password_verify($old, $user['password'])) {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param('si', $new_hash, $uid);
            $stmt->execute();
            $msg = "Password changed successfully!";
            $msg_type = 'ok';
        } else {
            $msg = "Incorrect old password.";
            $msg_type = 'err';
        }
    }

    // 5. NOTIFICATIONS
    if (isset($_POST['update_notifications'])) {
        $email_n = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_n = isset($_POST['sms_notifications']) ? 1 : 0;
        $whatsapp_n = isset($_POST['whatsapp_alerts']) ? 1 : 0;
        $stmt = $db->prepare("UPDATE user_settings SET email_notifications=?, sms_notifications=?, whatsapp_alerts=? WHERE user_id=?");
        $stmt->bind_param('iiii', $email_n, $sms_n, $whatsapp_n, $uid);
        $stmt->execute();
        $msg = "Notification settings saved!";
        $msg_type = 'ok';
        $settings['email_notifications'] = $email_n;
        $settings['sms_notifications'] = $sms_n;
        $settings['whatsapp_alerts'] = $whatsapp_n;
    }

    // 6. PREFERENCES
    if (isset($_POST['update_preferences'])) {
        $pref_type = $_POST['pref_job_type'] ?? 'full-time';
        $pref_loc = trim($_POST['pref_location'] ?? '');
        $stmt = $db->prepare("UPDATE user_settings SET pref_job_type=?, pref_location=? WHERE user_id=?");
        $stmt->bind_param('ssi', $pref_type, $pref_loc, $uid);
        $stmt->execute();
        $msg = "Hiring preferences saved!";
        $msg_type = 'ok';
    }

    // 7. VERIFICATION DOCUMENT
    if (isset($_POST['submit_verification'])) {
        if (!empty($_FILES['verification_doc']['name'])) {
            $ext = strtolower(pathinfo($_FILES['verification_doc']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','pdf'])) {
                $dir = __DIR__ . '/storage/verification';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $fn = 'verify_' . $uid . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['verification_doc']['tmp_name'], $dir . '/' . $fn)) {
                    $db->query("UPDATE users SET verification_doc='$fn', verification_status='pending' WHERE id=$uid");
                    $user['verification_doc'] = $fn;
                    $user['verification_status'] = 'pending';
                    
                    try {
                        $admin_q = $db->query("SELECT id FROM users WHERE role='admin'");
                        while ($admin = $admin_q->fetch_assoc()) {
                            $n_title = "New Verification Request (Employer)";
                            $n_msg = "Employer '{$user['name']}' has submitted a document for verification.";
                            $n_link = "admin-users.php?filter=pending_verification";
                            $ns = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
                            $ns->bind_param('isss', $admin['id'], $n_title, $n_msg, $n_link);
                            $ns->execute();
                        }
                    } catch (Throwable $e) {}
                    
                    $msg = "Document uploaded! Admin will verify within 24-48 hours.";
                    $msg_type = 'ok';
                }
            } else {
                $msg = "Invalid format. Use JPG, PNG, or PDF.";
                $msg_type = 'err';
            }
        }
    }
}

// URL params
if (isset($_GET['msg']) && empty($msg)) { $msg = $_GET['msg']; $msg_type = 'ok'; }
if (isset($_GET['pmsg']) && empty($msg)) { $msg = $_GET['pmsg']; $msg_type = 'ok'; }
if (isset($_GET['err']) && empty($msg)) { $msg = $_GET['err']; $msg_type = 'err'; }

// Flash message
if (isset($_SESSION['flash_msg']) && empty($msg)) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
    $msg_type = 'err';
}

// Stats
$stmt = $db->prepare("SELECT COUNT(*) c FROM jobs WHERE employer_id=?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$total_jobs = $stmt->get_result()->fetch_assoc()['c'];

$stmt = $db->prepare("SELECT COUNT(*) c FROM jobs WHERE employer_id=? AND status='active'");
$stmt->bind_param('i', $uid);
$stmt->execute();
$active_jobs = $stmt->get_result()->fetch_assoc()['c'];

include 'inc/head.php';
?>

<style>
.settings-layout-wrap { display: flex; max-width: 100% !important; padding: 0 !important; align-items: stretch; min-height: calc(100vh - 80px); }
.settings-sidebar { width: 280px; flex-shrink: 0; background: #fff; border-right: 1px solid var(--gray-border); height: calc(100vh - 80px); position: sticky; top: 80px; overflow-y: auto; }
.settings-sidebar-inner { padding: 30px 20px; }
.settings-content-area { flex: 1; padding: 40px; background: #f4f7fc; min-width: 0; position: relative; }
.settings-back-btn { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: #fff; color: var(--text-soft); box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-decoration: none; transition: all 0.2s; position: absolute; top: 40px; right: 40px; z-index: 10; }
.settings-back-btn:hover { background: var(--blue); color: #fff; }

.form-group { margin-bottom: 18px; }
.form-group label { display: block; font-size: 0.88rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 11px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.92rem; outline: none; font-family: inherit; background: #fff; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

.btn-blue, .btn-red, .btn-gray {
    padding: 10px 20px; border-radius: 8px; font-weight: 600;
    font-size: 0.9rem; cursor: pointer; border: none;
    display: inline-flex; align-items: center; gap: 6px; font-family: inherit;
}
.btn-blue { background: #2563eb; color: #fff; }
.btn-blue:hover { background: #1e40af; }
.btn-red { background: #dc2626; color: #fff; }
.btn-red:hover { background: #b91c1c; }
.btn-gray { background: #f3f4f6; color: #374151; }
.btn-gray:hover { background: #e5e7eb; }

.switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #cbd5e1; transition: .4s; border-radius: 34px; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; transition: .4s; border-radius: 50%; }
input:checked + .slider { background: #2563eb; }
input:checked + .slider:before { transform: translateX(20px); }

.logo-upload { width: 120px; height: 120px; border-radius: 12px; border: 2px dashed #d1d5db; display: flex; align-items: center; justify-content: center; flex-direction: column; overflow: hidden; cursor: pointer; position: relative; background: #f9fafb; }
.logo-upload img { width: 100%; height: 100%; object-fit: cover; }
.logo-upload i { font-size: 2rem; color: #9ca3af; margin-bottom: 4px; }
.logo-upload span { font-size: 0.7rem; color: #9ca3af; text-align: center; }
.cover-upload { width: 100%; height: 180px; border-radius: 12px; border: 2px dashed #d1d5db; display: flex; align-items: center; justify-content: center; flex-direction: column; overflow: hidden; cursor: pointer; background: #f9fafb; }
.cover-upload img { width: 100%; height: 100%; object-fit: cover; }
.cover-upload i { font-size: 2rem; color: #9ca3af; margin-bottom: 4px; }

/* Support Help Cards */
.help-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.help-actions a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; text-decoration: none; color: #374151; font-size: 0.9rem; font-weight: 600; }
.help-actions a:hover { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }

@media (max-width: 900px) {
    .settings-layout-wrap { flex-direction: column; }
    .settings-sidebar { width: 100%; height: auto; position: static; border-right: none; border-bottom: 1px solid var(--gray-border); }
    .settings-sidebar-inner { padding: 15px; display: flex; overflow-x: auto; }
    .settings-sidebar-title, .settings-divider { display: none; }
    .settings-nav-item { white-space: nowrap; margin-bottom: 0; margin-right: 8px; background: var(--gray-bg); }
    .settings-content-area { padding: 20px; }
    .form-row { grid-template-columns: 1fr; }
    .settings-back-btn { top: 20px; right: 20px; width: 35px; height: 35px; }
}
</style>

<div class="settings-layout-wrap">
    <?php include 'inc/settings-nav.php'; ?>
    <div class="settings-content-area">
        <a href="employer.php" class="settings-back-btn"><i class="fas fa-arrow-left"></i></a>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type === 'err' ? 'err' : 'ok' ?>" style="margin-bottom: 20px;">
                <i class="fas fa-<?= $msg_type === 'err' ? 'exclamation-circle' : 'check-circle' ?>"></i> <?= h($msg) ?>
            </div>
        <?php endif; ?>

        <div class="settings-content-box">
            
            <!-- ACCOUNT TAB -->
            <?php if ($tab === 'account'): ?>
                <h2 style="font-size:1.4rem; margin-bottom:5px;">Account Information</h2>
                <p style="color:#6b7280; font-size:0.9rem; margin-bottom:25px;">Basic login and contact info.</p>
                
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group"><label>Your Name</label><input type="text" name="name" value="<?= h($user['name']) ?>" required></div>
                        <div class="form-group"><label>Owner / HR Name</label><input type="text" name="owner_name" value="<?= h($user['owner_name'] ?? '') ?>"></div>
                    </div>
                    <div class="form-group"><label>Company Name</label><input type="text" name="company_name" value="<?= h($user['company_name'] ?? '') ?>" required></div>
                    <div class="form-row">
                        <div class="form-group"><label>Email</label><input type="email" value="<?= h($user['email']) ?>" disabled style="background:#f3f4f6;"></div>
                        <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= h($user['phone']) ?>" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>WhatsApp Number</label><input type="text" name="whatsapp" value="<?= h($user['whatsapp'] ?? '') ?>" placeholder="+91 9876543210"></div>
                        <div class="form-group"><label>Website (Optional)</label><input type="url" name="website" value="<?= h($user['website'] ?? '') ?>" placeholder="https://example.com"></div>
                    </div>
                    <button type="submit" name="update_account" class="btn-blue"><i class="fas fa-save"></i> Save Changes</button>
                </form>

                <!-- Contact Verification -->
                <div style="margin-top:30px; padding-top:25px; border-top:1px solid #e5e7eb;">
                    <h3 style="font-size:1.1rem; margin-bottom:15px;"><i class="fas fa-shield-alt" style="color:#2563eb;"></i> Contact Verification</h3>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div style="background:#f9fafb; padding:16px; border-radius:10px; border-left:3px solid <?= !empty($user['mobile_verified']) ? '#22c55e' : '#f59e0b' ?>;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                <strong><i class="fas fa-phone" style="color:#2563eb;"></i> Phone</strong>
                                <span style="background:<?= !empty($user['mobile_verified']) ? '#dcfce7' : '#fef3c7' ?>; color:<?= !empty($user['mobile_verified']) ? '#166534' : '#92400e' ?>; padding:2px 8px; border-radius:10px; font-size:0.7rem; font-weight:700;"><?= !empty($user['mobile_verified']) ? '✓ Verified' : '⚠ Unverified' ?></span>
                            </div>
                            <p style="margin:0 0 10px; color:#374151; font-weight:600;"><?= h($user['phone']) ?></p>
                            <?php if (defined('OTP_REQUIRED') && OTP_REQUIRED): ?>
                                <?php if (empty($user['mobile_verified'])): ?>
                                    <a href="verify-otp.php?purpose=verify&type=phone&contact=<?= urlencode($user['phone'] ?? '') ?>" class="btn-blue" style="padding:6px 12px; font-size:0.8rem;"><i class="fas fa-shield-alt"></i> Verify Now</a>
                                <?php else: ?>
                                    <a href="verify-otp.php?purpose=change_phone&type=phone&contact=<?= urlencode($user['phone'] ?? '') ?>" style="background:#fff; color:#2563eb; padding:6px 12px; border-radius:6px; font-size:0.8rem; text-decoration:none; border:1.5px solid #2563eb; font-weight:600;"><i class="fas fa-exchange-alt"></i> Change</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div style="background:#f9fafb; padding:16px; border-radius:10px; border-left:3px solid <?= !empty($user['email_verified']) ? '#22c55e' : '#f59e0b' ?>;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                <strong><i class="fas fa-envelope" style="color:#2563eb;"></i> Email</strong>
                                <span style="background:<?= !empty($user['email_verified']) ? '#dcfce7' : '#fef3c7' ?>; color:<?= !empty($user['email_verified']) ? '#166534' : '#92400e' ?>; padding:2px 8px; border-radius:10px; font-size:0.7rem; font-weight:700;"><?= !empty($user['email_verified']) ? '✓ Verified' : '⚠ Unverified' ?></span>
                            </div>
                            <p style="margin:0 0 10px; color:#374151; font-weight:600; word-break:break-all;"><?= h($user['email']) ?></p>
                            <?php if (defined('OTP_REQUIRED') && OTP_REQUIRED): ?>
                                <?php if (empty($user['email_verified'])): ?>
                                    <a href="verify-otp.php?purpose=verify&type=email&contact=<?= urlencode($user['email'] ?? '') ?>" class="btn-blue" style="padding:6px 12px; font-size:0.8rem;"><i class="fas fa-shield-alt"></i> Verify Now</a>
                                <?php else: ?>
                                    <a href="verify-otp.php?purpose=change_email&type=email&contact=<?= urlencode($user['email'] ?? '') ?>" style="background:#fff; color:#2563eb; padding:6px 12px; border-radius:6px; font-size:0.8rem; text-decoration:none; border:1.5px solid #2563eb; font-weight:600;"><i class="fas fa-exchange-alt"></i> Change</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- COMPANY PROFILE TAB -->
            <?php if ($tab === 'company'): ?>
                <h2 style="font-size:1.4rem; margin-bottom:5px;">Company Profile</h2>
                <p style="color:#6b7280; font-size:0.9rem; margin-bottom:25px;">This information is shown to job seekers.</p>
                
                <form method="POST" enctype="multipart/form-data" style="background:#f9fafb; padding:20px; border-radius:12px; margin-bottom:24px;">
                    <h3 style="font-size:1rem; margin-bottom:16px;"><i class="fas fa-image" style="color:#2563eb;"></i> Company Media</h3>
                    <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:6px;">Logo</label>
                            <label class="logo-upload" for="logoInput">
                                <?php if (!empty($user['company_logo'])): ?>
                                    <img src="<?= h($user['company_logo']) ?>" alt="Logo">
                                <?php else: ?>
                                    <i class="fas fa-camera"></i>
                                    <span>Upload<br>Logo</span>
                                <?php endif; ?>
                            </label>
                            <input type="file" name="company_logo" id="logoInput" accept="image/*" style="display:none;" onchange="this.form.submit()">
                        </div>
                    </div>
                    <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:6px;">Cover Image</label>
                    <label class="cover-upload" for="coverInput">
                        <?php if (!empty($user['cover_image'])): ?>
                            <img src="<?= h($user['cover_image']) ?>" alt="Cover">
                        <?php else: ?>
                            <i class="fas fa-image"></i>
                            <span style="font-size:0.8rem; color:#9ca3af;">Click to upload cover image</span>
                        <?php endif; ?>
                    </label>
                    <input type="file" name="cover_image" id="coverInput" accept="image/*" style="display:none;" onchange="this.form.submit()">
                    <input type="hidden" name="update_media" value="1">
                </form>
                
                <form method="POST">
                    <input type="hidden" name="update_company" value="1">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Business Category</label>
                            <select name="business_category">
                                <option value="">-- Select --</option>
                                <?php foreach (['Retail','Restaurant','IT Services','Manufacturing','Education','Healthcare','Transport','Construction','Other'] as $bc): ?>
                                    <option value="<?= $bc ?>" <?= ($user['business_category'] ?? '') === $bc ? 'selected' : '' ?>><?= $bc ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Business Type</label>
                            <select name="business_type">
                                <option value="">-- Select --</option>
                                <option value="shop" <?= ($user['business_type'] ?? '') === 'shop' ? 'selected' : '' ?>>Shop</option>
                                <option value="company" <?= ($user['business_type'] ?? '') === 'company' ? 'selected' : '' ?>>Company</option>
                                <option value="factory" <?= ($user['business_type'] ?? '') === 'factory' ? 'selected' : '' ?>>Factory</option>
                                <option value="office" <?= ($user['business_type'] ?? '') === 'office' ? 'selected' : '' ?>>Office</option>
                                <option value="other" <?= ($user['business_type'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label>About Your Business</label><textarea name="about_me" rows="4"><?= h($user['about_me'] ?? '') ?></textarea></div>
                    <div class="form-group"><label>Full Address</label><input type="text" name="address" value="<?= h($user['address'] ?? '') ?>"></div>
                    <div class="form-row">
                        <div class="form-group"><label>City</label><input type="text" name="city" value="<?= h($user['city'] ?? '') ?>"></div>
                        <div class="form-group"><label>District</label><input type="text" name="district" value="<?= h($user['district'] ?? '') ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>State</label><input type="text" name="state" value="<?= h($user['state'] ?? '') ?>"></div>
                        <div class="form-group"><label>PIN Code</label><input type="text" name="pincode" value="<?= h($user['pincode'] ?? '') ?>" maxlength="10"></div>
                    </div>
                    <div class="form-group"><label>GSTIN (Optional)</label><input type="text" name="gstin" value="<?= h($user['gstin'] ?? '') ?>" placeholder="e.g. 22AAAAA0000A1Z5" maxlength="20"></div>
                    <button type="submit" class="btn-blue"><i class="fas fa-save"></i> Update Company Profile</button>
                    <a href="company-preview.php" class="btn-gray" style="text-decoration:none;"><i class="fas fa-eye"></i> Preview Profile</a>
                </form>
            <?php endif; ?>

            <!-- VERIFICATION TAB -->
            <?php if ($tab === 'verification'): ?>
                <h2 style="font-size:1.4rem; margin-bottom:5px;">Account Verification</h2>
                <p style="color:#6b7280; font-size:0.9rem; margin-bottom:20px;">Get a blue tick on your company profile.</p>
                
                <?php if (!empty($user['is_verified'])): ?>
                    <div style="background:#dcfce7; border-left:4px solid #22c55e; padding:20px; border-radius:10px; margin-bottom:20px;">
                        <h3 style="color:#166534; margin:0 0 6px;"><i class="fas fa-check-circle"></i> Account Verified</h3>
                        <p style="color:#166534; margin:0; font-size:0.9rem;">Your business is fully verified. A blue tick appears on your profile.</p>
                    </div>
                <?php elseif (($user['verification_status'] ?? 'none') === 'pending'): ?>
                    <div style="background:#eff6ff; border-left:4px solid #2563eb; padding:20px; border-radius:10px; margin-bottom:20px;">
                        <h3 style="color:#1e40af; margin:0 0 6px;"><i class="fas fa-clock"></i> Under Review</h3>
                        <p style="color:#1e40af; margin:0; font-size:0.9rem;">Your document is under review. Admin will verify within 24-48 hours.</p>
                    </div>
                <?php else: ?>
                    <?php if (!empty($user['rejection_reason'])): ?>
                        <div style="background:#fef2f2; border-left:4px solid #dc2626; padding:16px; border-radius:10px; margin-bottom:20px;">
                            <strong style="color:#991b1b;"><i class="fas fa-times-circle"></i> Previous Rejection</strong>
                            <p style="color:#991b1b; margin:6px 0 0; font-size:0.9rem;"><?= h($user['rejection_reason']) ?></p>
                        </div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="submit_verification" value="1">
                        <div class="form-group">
                            <label>Upload Business Document (GST, Shop License, Aadhaar, etc.)</label>
                            <input type="file" name="verification_doc" accept=".jpg,.jpeg,.png,.pdf" required>
                            <small style="color:#6b7280; font-size:0.8rem;">JPG, PNG, or PDF. Max 5MB.</small>
                        </div>
                        <button type="submit" class="btn-blue"><i class="fas fa-paper-plane"></i> Submit Document</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <!-- PREFERENCES -->
            <?php if ($tab === 'preferences'): ?>
                <h2 style="font-size:1.4rem; margin-bottom:5px;">Hiring Preferences</h2>
                <p style="color:#6b7280; font-size:0.9rem; margin-bottom:25px;">Default settings for new job posts.</p>
                <form method="POST">
                    <input type="hidden" name="update_preferences" value="1">
                    <div class="form-group">
                        <label>Default Job Type</label>
                        <select name="pref_job_type">
                            <option value="full-time" <?= ($settings['pref_job_type'] ?? '') === 'full-time' ? 'selected' : '' ?>>Full-Time</option>
                            <option value="part-time" <?= ($settings['pref_job_type'] ?? '') === 'part-time' ? 'selected' : '' ?>>Part-Time</option>
                            <option value="contract" <?= ($settings['pref_job_type'] ?? '') === 'contract' ? 'selected' : '' ?>>Contract</option>
                            <option value="daily-wage" <?= ($settings['pref_job_type'] ?? '') === 'daily-wage' ? 'selected' : '' ?>>Daily Wage</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Default Location</label><input type="text" name="pref_location" value="<?= h($settings['pref_location'] ?? '') ?>"></div>
                    <button type="submit" class="btn-blue"><i class="fas fa-save"></i> Save Preferences</button>
                </form>
            <?php endif; ?>

            <!-- NOTIFICATIONS -->
            <?php if ($tab === 'notifications'): ?>
                <h2 style="font-size:1.4rem; margin-bottom:5px;">Notifications</h2>
                <p style="color:#6b7280; font-size:0.9rem; margin-bottom:20px;">Choose how you want to be notified.</p>
                <form method="POST">
                    <input type="hidden" name="update_notifications" value="1">
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:15px 0; border-bottom:1px solid #eee;">
                        <div><strong>📧 Email Alerts</strong><p style="font-size:0.8rem; color:#6b7280; margin:0;">New applications received</p></div>
                        <label class="switch"><input type="checkbox" name="email_notifications" value="1" <?= !empty($settings['email_notifications']) ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:15px 0; border-bottom:1px solid #eee;">
                        <div><strong>📱 SMS Alerts</strong><p style="font-size:0.8rem; color:#6b7280; margin:0;">SMS notifications</p></div>
                        <label class="switch"><input type="checkbox" name="sms_notifications" value="1" <?= !empty($settings['sms_notifications']) ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:15px 0;">
                        <div><strong>💬 WhatsApp Alerts</strong><p style="font-size:0.8rem; color:#6b7280; margin:0;">WhatsApp notifications</p></div>
                        <label class="switch"><input type="checkbox" name="whatsapp_alerts" value="1" <?= !empty($settings['whatsapp_alerts']) ? 'checked' : '' ?>><span class="slider"></span></label>
                    </div>
                    <button type="submit" class="btn-blue" style="margin-top:20px;"><i class="fas fa-save"></i> Save Notifications</button>
                </form>
            <?php endif; ?>

            <!-- SECURITY -->
            <?php if ($tab === 'security'): ?>
                <h2 style="font-size:1.4rem; margin-bottom:5px;">Privacy & Security</h2>
                <p style="color:#6b7280; font-size:0.9rem; margin-bottom:20px;">Keep your account secure.</p>
                <h3 style="font-size:1rem; margin-bottom:15px;">Change Password</h3>
                <form method="POST">
                    <div class="form-group"><label>Current Password</label><input type="password" name="old_password" required></div>
                    <div class="form-group"><label>New Password (min 6)</label><input type="password" name="new_password" required minlength="6"></div>
                    <button type="submit" name="change_password" class="btn-red"><i class="fas fa-key"></i> Update Password</button>
                </form>
            <?php endif; ?>

            <!-- LANGUAGE -->
            <?php if ($tab === 'language'): ?>
                <h2 style="font-size:1.4rem; margin-bottom:5px;">Language Preferences</h2>
                <p style="color:#6b7280; font-size:0.9rem; margin-bottom:20px;">Choose your preferred language.</p>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <?php foreach (LANG_NAMES as $code => $name): ?>
                        <a href="?lang=<?= urlencode($code) ?>" style="padding:15px; border-radius:8px; border:1.5px solid <?= $cur_lang === $code ? '#2563eb' : '#d1d5db' ?>; background:<?= $cur_lang === $code ? '#eff6ff' : '#fff' ?>; text-decoration:none; color:#374151; font-weight:600; display:flex; justify-content:space-between; align-items:center;">
                            <span><?= h($name) ?></span>
                            <?php if ($cur_lang === $code): ?><i class="fas fa-check-circle" style="color:#2563eb;"></i><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- BILLING — only if enabled -->
            <?php if ($tab === 'billing' && $billing_on): ?>
                <h2 style="font-size:1.4rem; margin-bottom:5px;">Subscription & Billing</h2>
                <p style="color:#6b7280; font-size:0.9rem; margin-bottom:25px;">Manage your subscription plan.</p>
                <div style="background:#f9fafb; border:1px solid #e5e7eb; padding:20px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                    <div>
                        <strong style="font-size:1.1rem; text-transform:uppercase;">FREE PLAN</strong>
                        <p style="font-size:0.85rem; color:#6b7280; margin:5px 0 0;">Current plan — paid plans coming soon.</p>
                    </div>
                    <button class="btn-gray" disabled style="cursor:not-allowed;">Upgrade Coming Soon</button>
                </div>
                <div style="margin-top:20px; padding:15px; background:#eff6ff; border-radius:8px; border-left:4px solid #2563eb;">
                    <p style="font-size:0.9rem; color:#1e40af; margin:0;"><i class="fas fa-info-circle"></i> Total Jobs: <strong><?= (int)$total_jobs ?></strong> | Active: <strong><?= (int)$active_jobs ?></strong></p>
                </div>
            <?php endif; ?>

            <!-- SUPPORT — SEEKER STYLE -->
            <?php if ($tab === 'support'): ?>
                <h2 style="font-size:1.4rem; margin-bottom:5px;">Help & Support</h2>
                <p style="color:#6b7280; font-size:0.9rem; margin-bottom:25px;">Need help? We're here for you.</p>
                
                <div style="background:#fff; border-radius:14px; padding:24px; box-shadow:0 2px 12px rgba(0,0,0,0.04); border:1px solid #e5e7eb;">
                    <div class="help-actions" style="flex-direction:column; gap:15px;">
                        <a href="contact.php" style="padding:20px; justify-content:flex-start; width:100%; border-radius:12px;">
                            <i class="fas fa-envelope" style="font-size:1.5rem; color:#2563eb;"></i>
                            <div>
                                <strong style="display:block; color:#111827; margin-bottom:2px;">Contact Support</strong>
                                <small style="color:#6b7280; font-weight:400;">Email or call our support team</small>
                            </div>
                        </a>
                        <a href="safety.php#report" style="padding:20px; justify-content:flex-start; width:100%; border-radius:12px;">
                            <i class="fas fa-flag" style="font-size:1.5rem; color:#dc2626;"></i>
                            <div>
                                <strong style="display:block; color:#111827; margin-bottom:2px;">Report an Issue</strong>
                                <small style="color:#6b7280; font-weight:400;">Report a fake candidate or suspicious activity</small>
                            </div>
                        </a>
                        <a href="faq.php" style="padding:20px; justify-content:flex-start; width:100%; border-radius:12px;">
                            <i class="fas fa-question-circle" style="font-size:1.5rem; color:#f59e0b;"></i>
                            <div>
                                <strong style="display:block; color:#111827; margin-bottom:2px;">Frequently Asked Questions</strong>
                                <small style="color:#6b7280; font-weight:400;">Find quick answers to common questions</small>
                            </div>
                        </a>
                    </div>
                </div>
                
                <!-- Quick Contact Info -->
                <div style="background:#f9fafb; padding:20px; border-radius:12px; margin-top:20px; border:1px solid #e5e7eb;">
                    <h3 style="font-size:1rem; margin:0 0 15px; color:#111827;"><i class="fas fa-info-circle" style="color:#2563eb;"></i> Quick Contact</h3>
                    <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:12px;">
                        <li style="display:flex; align-items:center; gap:10px; font-size:0.9rem;"><i class="fas fa-envelope" style="color:#2563eb; width:24px;"></i> <a href="mailto:<?= h(SUPPORT_EMAIL) ?>" style="color:#374151;"><?= h(SUPPORT_EMAIL) ?></a></li>
                        <li style="display:flex; align-items:center; gap:10px; font-size:0.9rem;"><i class="fas fa-phone" style="color:#2563eb; width:24px;"></i> <a href="tel:<?= h(preg_replace('/\s+/','',SUPPORT_PHONE)) ?>" style="color:#374151;"><?= h(SUPPORT_PHONE) ?></a></li>
                        <li style="display:flex; align-items:center; gap:10px; font-size:0.9rem;"><i class="fab fa-whatsapp" style="color:#22c55e; width:24px;"></i> <a href="<?= whatsapp_url() ?>" target="_blank" style="color:#374151;"><?= h(WHATSAPP_NUMBER) ?></a></li>
                        <li style="display:flex; align-items:center; gap:10px; font-size:0.9rem;"><i class="fas fa-clock" style="color:#2563eb; width:24px;"></i> <span style="color:#374151;"><?= h(SUPPORT_HOURS) ?></span></li>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- DELETE -->
            <?php if ($tab === 'delete'): ?>
                <h2 style="font-size:1.4rem; margin-bottom:5px; color:#dc2626;"><i class="fas fa-exclamation-triangle"></i> Delete Account</h2>
                <p style="color:#6b7280; font-size:0.9rem; margin-bottom:25px;">Once deleted, there is no going back.</p>
                <div style="background:#fef2f2; border:2px solid #fecaca; padding:20px; border-radius:12px; margin-bottom:20px;">
                    <h3 style="color:#991b1b; margin:0 0 12px;">Delete Company Account</h3>
                    <ul style="color:#7f1d1d; font-size:0.85rem; line-height:1.8; padding-left:20px; margin:0 0 12px;">
                        <li>Company profile + logo + gallery</li>
                        <li>All posted jobs (active + expired)</li>
                        <li>All applications received</li>
                        <li>Verification documents</li>
                    </ul>
                </div>
                <form method="POST" action="delete-account.php" onsubmit="return confirm('⚠️ FINAL WARNING ⚠️\n\nAre you sure you want to PERMANENTLY delete your account? This CANNOT be undone.');">
                    <div class="form-group">
                        <label>Enter Password to Confirm <span style="color:#dc2626;">*</span></label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <div style="margin-bottom:20px; padding:14px; background:#f9fafb; border-radius:8px;">
                        <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="confirm_delete" value="yes" required style="margin-top:3px;">
                            <span style="font-size:0.88rem; color:#374151;">I understand this is permanent and cannot be undone.</span>
                        </label>
                    </div>
                    <button type="submit" style="background:#dc2626; color:#fff; padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer;">
                        <i class="fas fa-trash-alt"></i> Delete Account Permanently
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include 'inc/foot.php'; ?>