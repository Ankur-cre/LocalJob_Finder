<?php
require_once 'inc/auth.php';
require_once 'inc/site-config.php';
require_once 'inc/otp-helper.php';
must_role('seeker');
$title = "Settings — " . SITE_NAME;
$uid = (int)$_SESSION['uid'];
$tab = $_GET['tab'] ?? 'account';
$msg = '';
$cur_lang = current_lang();

// ============================================
// HELPER — SAFE COLUMN CHECK
// ============================================
if (!function_exists('column_exists')) {
    function column_exists($db, $table, $column) {
        $table  = $db->real_escape_string($table);
        $column = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && $res->num_rows > 0;
    }
}

// ============================================
// AUTO-CREATE MISSING COLUMNS IN user_settings
// ============================================
$required_settings_cols = [
    'profile_visibility'  => "VARCHAR(20) NOT NULL DEFAULT 'public'",
    'resume_visibility'   => "VARCHAR(20) NOT NULL DEFAULT 'employers'",
    'show_photo'          => "TINYINT(1) NOT NULL DEFAULT 1",
    'show_email'          => "TINYINT(1) NOT NULL DEFAULT 1",
    'show_phone'          => "TINYINT(1) NOT NULL DEFAULT 1",
    'show_location'       => "TINYINT(1) NOT NULL DEFAULT 1",
    'email_notifications' => "TINYINT(1) NOT NULL DEFAULT 1",
    'sms_notifications'   => "TINYINT(1) NOT NULL DEFAULT 1",
    'whatsapp_alerts'     => "TINYINT(1) NOT NULL DEFAULT 1",
    'pref_job_type'       => "VARCHAR(50) NULL",
    'pref_location'       => "VARCHAR(150) NULL",
    'pref_expected_salary'=> "VARCHAR(100) NULL",
];

try {
    $existing_cols = [];
    $col_res = $db->query("SHOW COLUMNS FROM user_settings");
    if ($col_res) {
        while ($row = $col_res->fetch_assoc()) $existing_cols[] = $row['Field'];
    }
    foreach ($required_settings_cols as $col => $definition) {
        if (!in_array($col, $existing_cols)) {
            try {
                $db->query("ALTER TABLE user_settings ADD COLUMN `$col` $definition");
            } catch (Throwable $e) {
                error_log("Auto-create user_settings.$col failed: " . $e->getMessage());
            }
        }
    }
} catch (Throwable $e) {
    error_log("user_settings auto-init failed: " . $e->getMessage());
}

// ============================================
// FLASH MESSAGE
// ============================================
if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

// ============================================
// ★ RESUME BUILDER ACCESS CHECK ★
// ============================================
if ($tab === 'resume_builder') {
    if (defined('RESUME_BUILDER_ENABLED') && RESUME_BUILDER_ENABLED) {
        header("Location: resume-maker.php");
        exit;
    } else {
        $_SESSION['flash_msg'] = "Resume Builder is currently disabled by admin.";
        header("Location: seeker-settings.php?tab=account");
        exit;
    }
}

// Valid tabs (Resume Builder only if enabled)
$valid_tabs = ['account','profile','saved_jobs','alerts','preferences','notifications','security','language','delete', 'verification', 'support'];
if (defined('RESUME_BUILDER_ENABLED') && RESUME_BUILDER_ENABLED) {
    $valid_tabs[] = 'resume_builder';
}
if (!in_array($tab, $valid_tabs)) $tab = 'account';

// Fetch User Data
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) { header("Location: logout.php"); exit; }

// Ensure user_settings exists
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

// Profile completion
$completion = 10;
if (!empty($user['profile_photo'])) $completion += 10;
if (!empty($user['headline'])) $completion += 10;
if (!empty($user['about_me'])) $completion += 10;
if (!empty($user['dob'])) $completion += 5;
if (!empty($user['gender'])) $completion += 5;

$s1 = $db->prepare("SELECT COUNT(*) c FROM seeker_skills WHERE seeker_id=?");
$s1->bind_param('i', $uid); $s1->execute();
if ($s1->get_result()->fetch_assoc()['c'] > 0) $completion += 15;

$s2 = $db->prepare("SELECT COUNT(*) c FROM seeker_education WHERE seeker_id=?");
$s2->bind_param('i', $uid); $s2->execute();
if ($s2->get_result()->fetch_assoc()['c'] > 0) $completion += 15;

$s3 = $db->prepare("SELECT COUNT(*) c FROM seeker_experience WHERE seeker_id=?");
$s3->bind_param('i', $uid); $s3->execute();
if ($s3->get_result()->fetch_assoc()['c'] > 0) $completion += 20;
if ($completion > 100) $completion = 100;

// ============================================
// HANDLE FORM SUBMISSIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. PERSONAL INFO
    if (isset($_POST['update_personal'])) {
        $name = trim($_POST['name'] ?? '');
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $gender = trim($_POST['gender'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '' || $phone === '') {
            $msg = "Name and Phone are required.";
        } else {
            $stmt = $db->prepare("UPDATE users SET name=?, dob=?, gender=?, phone=? WHERE id=?");
            $stmt->bind_param('ssssi', $name, $dob, $gender, $phone, $uid);
            if ($stmt->execute()) {
                $msg = "Personal information updated successfully!";
                $user['name'] = $name; $user['dob'] = $dob;
                $user['gender'] = $gender; $user['phone'] = $phone;
            }
        }
    }

    // 2. LOCATION
    if (isset($_POST['update_location'])) {
        $state = trim($_POST['state'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $locality = trim($_POST['locality'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');

        $stmt = $db->prepare("UPDATE users SET state=?, district=?, city=?, locality=?, pincode=? WHERE id=?");
        $stmt->bind_param('sssssi', $state, $district, $city, $locality, $pincode, $uid);
        if ($stmt->execute()) {
            $msg = "Location updated successfully!";
            $user['state'] = $state; $user['district'] = $district;
            $user['city'] = $city; $user['locality'] = $locality; $user['pincode'] = $pincode;
        }
    }

    // 3. PREFERENCES
    if (isset($_POST['update_preferences'])) {
        $email_n = isset($_POST['email_notifications']) ? 1 : 0;
        $job_a   = isset($_POST['job_alerts']) ? 1 : 0;
        $app_u   = isset($_POST['app_updates']) ? 1 : 0;

        $stmt = $db->prepare("UPDATE user_settings SET email_notifications=?, sms_notifications=?, whatsapp_alerts=? WHERE user_id=?");
        $stmt->bind_param('iiii', $email_n, $job_a, $app_u, $uid);
        $stmt->execute();
        $msg = "Preferences saved!";
    }

    // 4. PROFILE PHOTO UPLOAD
    if (isset($_POST['update_photo'])) {
        if (!empty($_FILES['profile_photo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $dir = __DIR__ . '/storage/profiles';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $filename = 'user_' . $uid . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dir . '/' . $filename)) {
                    if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/' . $user['profile_photo'])) {
                        @unlink(__DIR__ . '/' . $user['profile_photo']);
                    }
                    $new_path = 'storage/profiles/' . $filename;
                    $db->query("UPDATE users SET profile_photo='$new_path' WHERE id=$uid");
                    $msg = "Profile photo updated successfully!";
                    $user['profile_photo'] = $new_path;
                }
            } else {
                $msg = "Invalid image format. Use JPG, PNG, WEBP.";
            }
        }
    }

    // 5. VERIFICATION DOCUMENT
    if (isset($_POST['submit_verification'])) {
        if (!empty($_FILES['verification_doc']['name'])) {
            $ext = strtolower(pathinfo($_FILES['verification_doc']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                $dir = __DIR__ . '/storage/verification';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $filename = 'verify_' . $uid . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['verification_doc']['tmp_name'], $dir . '/' . $filename)) {
                    $db->query("UPDATE users SET verification_doc='$filename', verification_status='pending' WHERE id=$uid");

                    try {
                        $admin_q = $db->query("SELECT id FROM users WHERE role='admin'");
                        while ($admin = $admin_q->fetch_assoc()) {
                            $n_title = "New Verification Request";
                            $n_msg = "User '{$user['name']}' has submitted a document for verification.";
                            $n_link = "admin-users.php?filter=pending_verification";
                            $ns = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
                            $ns->bind_param('isss', $admin['id'], $n_title, $n_msg, $n_link);
                            $ns->execute();
                        }
                    } catch (Throwable $e) { error_log("Admin notify failed: ".$e->getMessage()); }

                    $msg = "Document uploaded! Admin will verify within 24-48 hours.";
                }
            } else {
                $msg = "Invalid format. Use JPG, PNG, or PDF.";
            }
        }
    }

    // 6. PRIVACY SETTINGS
    if (isset($_POST['update_privacy'])) {
        $profile_vis = $_POST['profile_visibility'] ?? 'public';
        $resume_vis  = $_POST['resume_visibility']  ?? 'employers';
        $show_photo  = isset($_POST['show_photo'])    ? 1 : 0;
        $show_email  = isset($_POST['show_email'])    ? 1 : 0;
        $show_phone  = isset($_POST['show_phone'])    ? 1 : 0;
        $show_loc    = isset($_POST['show_location']) ? 1 : 0;

        try {
            $set_parts = [];
            $bind_types = '';
            $bind_vals  = [];

            $wanted = [
                'profile_visibility' => ['s', $profile_vis],
                'resume_visibility'  => ['s', $resume_vis],
                'show_photo'         => ['i', $show_photo],
                'show_email'         => ['i', $show_email],
                'show_phone'         => ['i', $show_phone],
                'show_location'      => ['i', $show_loc],
            ];

            foreach ($wanted as $col => [$type, $val]) {
                if (column_exists($db, 'user_settings', $col)) {
                    $set_parts[] = "`$col` = ?";
                    $bind_types .= $type;
                    $bind_vals[] = $val;
                }
            }

            if (!empty($set_parts)) {
                $sql = "UPDATE user_settings SET " . implode(', ', $set_parts) . " WHERE user_id = ?";
                $bind_types .= 'i';
                $bind_vals[] = $uid;

                $stmt = $db->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param($bind_types, ...$bind_vals);
                    if ($stmt->execute()) {
                        $msg = "Privacy settings saved!";
                        $settings = $db->query("SELECT * FROM user_settings WHERE user_id=$uid")->fetch_assoc();

                        // Sync to users table
                        try {
                            $u_parts = [];
                            $u_types = '';
                            $u_vals  = [];
                            if (column_exists($db, 'users', 'show_photo'))    { $u_parts[] = 'show_photo = ?';    $u_types .= 'i'; $u_vals[] = $show_photo; }
                            if (column_exists($db, 'users', 'show_email'))    { $u_parts[] = 'show_email = ?';    $u_types .= 'i'; $u_vals[] = $show_email; }
                            if (column_exists($db, 'users', 'show_phone'))    { $u_parts[] = 'show_phone = ?';    $u_types .= 'i'; $u_vals[] = $show_phone; }
                            if (column_exists($db, 'users', 'show_location')) { $u_parts[] = 'show_location = ?'; $u_types .= 'i'; $u_vals[] = $show_loc; }
                            if (!empty($u_parts)) {
                                $u_sql = "UPDATE users SET " . implode(', ', $u_parts) . " WHERE id = ?";
                                $u_types .= 'i';
                                $u_vals[] = $uid;
                                $u_stmt = $db->prepare($u_sql);
                                if ($u_stmt) {
                                    $u_stmt->bind_param($u_types, ...$u_vals);
                                    $u_stmt->execute();
                                }
                            }
                        } catch (Throwable $e) {
                            error_log("Users sync failed: " . $e->getMessage());
                        }
                    } else {
                        $msg = "Failed to save privacy settings. Please try again.";
                    }
                }
            }
        } catch (Throwable $e) {
            $msg = "Error: " . $e->getMessage();
        }
    }

    // 7. CHANGE PASSWORD
    if (isset($_POST['change_password'])) {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';

        if (password_verify($old, $user['password'])) {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param('si', $new_hash, $uid);
            $stmt->execute();
            $msg = "Password changed successfully!";
        } else {
            $msg = "Incorrect old password.";
        }
    }
}

include 'inc/head.php';
?>

<style>
/* ============ SHARED STYLES ============ */
.settings-layout-wrap { display: flex; max-width: 100% !important; padding: 0 !important; align-items: stretch; min-height: calc(100vh - 80px); }
.settings-sidebar { width: 280px; flex-shrink: 0; background: #fff; border-right: 1px solid var(--gray-border); height: calc(100vh - 80px); position: sticky; top: 80px; overflow-y: auto; }
.settings-sidebar-inner { padding: 30px 20px; }
.settings-sidebar-title { font-size: 1.3rem; margin-bottom: 25px; color: var(--text); padding-left: 10px; font-weight: 700; }
.settings-divider { margin: 15px 10px; border-top: 1px solid var(--gray-border); }
.settings-nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: var(--gray); text-decoration: none; border-radius: 8px; font-weight: 500; font-size: 0.92rem; margin-bottom: 4px; transition: all 0.2s ease; white-space: nowrap; }
.settings-nav-item:hover { background: var(--gray-bg); color: var(--text); text-decoration: none; }
.settings-nav-item.active { background: var(--blue-light); color: var(--blue-dark); font-weight: 600; }
.settings-nav-item i { width: 20px; text-align: center; font-size: 1.05rem; }
.settings-content-area { flex: 1; padding: 40px; background: #f4f7fc; min-width: 0; position: relative; }
.settings-back-btn { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: #fff; color: var(--text-soft); box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-decoration: none; transition: all 0.2s; position: absolute; top: 40px; right: 40px; z-index: 10; }
.settings-back-btn:hover { background: var(--blue); color: #fff; }

.acc-container { max-width: 1000px; }
.acc-header { margin-bottom: 30px; }
.acc-header h1 { font-size: 1.8rem; color: #111827; margin-bottom: 5px; }
.acc-header p { color: #6b7280; font-size: 1rem; margin: 0; }
.acc-grid { display: grid; grid-template-columns: 1fr; gap: 24px; }
.acc-card { background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); border: 1px solid #e5e7eb; }
.acc-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #f3f4f6; }
.acc-card-title { display: flex; align-items: center; gap: 10px; font-size: 1.1rem; font-weight: 700; color: #111827; }
.acc-card-title i { color: #2563eb; background: #eff6ff; padding: 8px; border-radius: 8px; font-size: 1rem; }

.profile-summary-card { display: flex; gap: 24px; align-items: center; flex-wrap: wrap; }
.profile-avatar-large { width: 110px; height: 110px; border-radius: 50%; background: #dbeafe; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 3rem; overflow: hidden; border: 4px solid #fff; box-shadow: 0 4px 12px rgba(37,99,235,0.2); }
.profile-avatar-large img { width: 100%; height: 100%; object-fit: cover; }
.profile-summary-info { flex: 1; min-width: 250px; }
.profile-summary-info h2 { font-size: 1.5rem; color: #111827; margin-bottom: 6px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.profile-summary-info .role-badge { display: inline-block; background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
.status-active { background: #dcfce7; color: #166534; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-restricted { background: #fee2e2; color: #991b1b; }
.profile-meta { display: flex; flex-wrap: wrap; gap: 18px; font-size: 0.9rem; color: #6b7280; margin-top: 10px; }
.profile-meta span { display: inline-flex; align-items: center; gap: 6px; }

.info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
.info-item label { display: block; font-size: 0.78rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-bottom: 4px; }
.info-item div { font-size: 0.98rem; color: #111827; font-weight: 600; }

.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.form-field { display: flex; flex-direction: column; gap: 8px; }
.form-field label { font-size: 0.88rem; font-weight: 600; color: #374151; }
.form-field input, .form-field select, .form-field textarea { padding: 11px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.95rem; color: #111827; outline: none; background: #fff; width: 100%; font-family: inherit; }
.form-field input:focus, .form-field select:focus, .form-field textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

.btn-primary-blue { background: #2563eb; color: #fff; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-family: inherit; text-decoration: none; }
.btn-primary-blue:hover { background: #1e40af; }
.btn-outline-blue { background: #fff; color: #2563eb; padding: 8px 16px; border: 1.5px solid #2563eb; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
.btn-outline-blue:hover { background: #eff6ff; }

.toggle-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid #f3f4f6; }
.toggle-row:last-child { border-bottom: none; }
.toggle-row strong { display: block; font-size: 0.95rem; color: #111827; margin-bottom: 2px; }
.toggle-row small { font-size: 0.8rem; color: #6b7280; }
.switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 34px; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .slider { background-color: #2563eb; }
input:checked + .slider:before { transform: translateX(20px); }

.badge-verified { background: #dcfce7; color: #166534; padding: 3px 10px; border-radius: 12px; font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
.badge-unverified { background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 12px; font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }

.help-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.help-actions a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; text-decoration: none; color: #374151; font-size: 0.9rem; font-weight: 600; }
.help-actions a:hover { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }

.profile-tab-btn.active { color: #2563eb !important; border-bottom-color: #2563eb !important; }
.profile-tab-content { animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

.empty-state { text-align: center; padding: 50px 20px; }
.empty-state i { font-size: 3rem; color: #d1d5db; margin-bottom: 15px; display: block; }
.empty-state h3 { font-size: 1.2rem; color: #374151; margin-bottom: 8px; }
.empty-state p { color: #6b7280; font-size: 0.95rem; margin-bottom: 20px; }

.otp-verify-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 8px; }

@media (max-width: 900px) {
    .settings-layout-wrap { flex-direction: column; }
    .settings-sidebar { width: 100%; height: auto; position: static; border-right: none; border-bottom: 1px solid var(--gray-border); }
    .settings-sidebar-inner { padding: 15px; display: flex; overflow-x: auto; }
    .settings-sidebar-title, .settings-divider { display: none; }
    .settings-nav-item { white-space: nowrap; margin-bottom: 0; margin-right: 8px; background: var(--gray-bg); }
    .settings-nav-item.active { background: var(--blue); color: #fff; }
    .settings-content-area { padding: 20px; }
    .form-grid-2, .info-grid { grid-template-columns: 1fr; }
    .settings-back-btn { top: 20px; right: 20px; width: 35px; height: 35px; }
}
</style>

<div class="settings-layout-wrap">

    <?php include 'inc/settings-nav.php'; ?>

    <div class="settings-content-area">
        <a href="seeker-home.php" class="settings-back-btn" title="Back to Dashboard">
            <i class="fas fa-arrow-left"></i>
        </a>

        <?php if ($msg): ?>
            <div class="alert alert-ok" style="margin-bottom: 20px; max-width: 1000px;">
                <i class="fas fa-check-circle"></i> <?= h($msg) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['err'])): ?>
            <div class="alert alert-err" style="margin-bottom: 20px; max-width: 1000px;">
                <i class="fas fa-exclamation-circle"></i> <?= h($_GET['err']) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['pmsg'])): ?>
            <div class="alert alert-ok" style="margin-bottom: 20px; max-width: 1000px;">
                <i class="fas fa-check-circle"></i> <?= h($_GET['pmsg']) ?>
            </div>
        <?php endif; ?>

        <!-- ============ ACCOUNT TAB ============ -->
        <?php if ($tab === 'account'): ?>
        <div class="acc-container">
            <div class="acc-header">
                <h1>Account Information</h1>
                <p>Manage your personal details and registered account information.</p>
            </div>

            <div class="acc-grid">
                <div class="acc-card">
                    <div class="profile-summary-card">
                        <div class="profile-avatar-large">
                            <?php if (!empty($user['profile_photo'])): ?>
                                <img src="<?= h($user['profile_photo']) ?>" alt="Profile">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div class="profile-summary-info">
                            <h2>
                                <?= h($user['name']) ?>
                                <span class="role-badge">Job Seeker</span>
                                <span class="status-badge status-<?= $user['status'] === 'active' ? 'active' : ($user['status'] === 'pending' ? 'pending' : 'restricted') ?>">
                                    <?= ucfirst($user['status'] ?? 'active') ?>
                                </span>
                            </h2>
                            <p style="color:#6b7280; font-size:0.9rem; margin:0 0 10px;">User ID: #<?= str_pad($user['id'], 6, '0', STR_PAD_LEFT) ?></p>
                            <div class="profile-meta">
                                <span><i class="far fa-calendar" style="color:#2563eb;"></i> Joined <?= date('M Y', strtotime($user['created_at'])) ?></span>
                                <?php if (!empty($user['last_login'])): ?>
                                    <span><i class="far fa-clock" style="color:#2563eb;"></i> Last login: <?= date('d M, h:i A', strtotime($user['last_login'])) ?></span>
                                <?php endif; ?>
                                <span>
                                    <?php if (!empty($user['mobile_verified'])): ?>
                                        <span class="badge-verified"><i class="fas fa-check-circle"></i> Mobile Verified</span>
                                    <?php else: ?>
                                        <span class="badge-unverified"><i class="fas fa-exclamation-circle"></i> Mobile Unverified</span>
                                    <?php endif; ?>
                                </span>
                                <span>
                                    <?php if (!empty($user['email_verified'])): ?>
                                        <span class="badge-verified"><i class="fas fa-check-circle"></i> Email Verified</span>
                                    <?php else: ?>
                                        <span class="badge-unverified"><i class="fas fa-exclamation-circle"></i> Email Unverified</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="acc-card">
                    <div class="acc-card-header">
                        <div class="acc-card-title"><i class="fas fa-user"></i> Personal Information</div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="update_personal" value="1">
                        <div class="form-grid-2">
                            <div class="form-field"><label>Full Name</label><input type="text" name="name" value="<?= h($user['name']) ?>" required></div>
                            <div class="form-field"><label>Date of Birth</label><input type="date" name="dob" value="<?= h($user['dob'] ?? '') ?>"></div>
                            <div class="form-field">
                                <label>Gender</label>
                                <select name="gender">
                                    <option value="">— Select —</option>
                                    <option value="Male" <?= ($user['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($user['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= ($user['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-field"><label>Mobile Number</label><input type="text" name="phone" value="<?= h($user['phone']) ?>" required></div>
                        </div>
                        <div style="margin-top: 18px;">
                            <button type="submit" class="btn-primary-blue"><i class="fas fa-save"></i> Save Changes</button>
                        </div>
                    </form>
                </div>

                <!-- CONTACT INFO with OTP buttons -->
                <div class="acc-card">
                    <div class="acc-card-header">
                        <div class="acc-card-title"><i class="fas fa-address-book"></i> Contact Information</div>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Mobile Number</label>
                            <div style="display:flex; align-items:center; flex-wrap:wrap; gap:8px;">
                                <span><?= h($user['phone']) ?></span>
                                <?php if (!empty($user['mobile_verified'])): ?>
                                    <span class="badge-verified"><i class="fas fa-check-circle"></i> Verified</span>
                                <?php else: ?>
                                    <span class="badge-unverified"><i class="fas fa-clock"></i> Unverified</span>
                                <?php endif; ?>
                            </div>
                            <?php if (defined('OTP_REQUIRED') && OTP_REQUIRED): ?>
                            <div class="otp-verify-row">
                                <?php if (empty($user['mobile_verified'])): ?>
                                    <a href="verify-otp.php?purpose=verify&type=phone&contact=<?= urlencode($user['phone'] ?? '') ?>" class="btn-outline-blue" style="font-size:0.8rem; padding:6px 12px;">
                                        <i class="fas fa-shield-alt"></i> Verify Now
                                    </a>
                                <?php else: ?>
                                    <a href="verify-otp.php?purpose=change_phone&type=phone&contact=<?= urlencode($user['phone'] ?? '') ?>" class="btn-outline-blue" style="font-size:0.8rem; padding:6px 12px;">
                                        <i class="fas fa-exchange-alt"></i> Change via OTP
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="info-item">
                            <label>Email Address</label>
                            <div style="display:flex; align-items:center; flex-wrap:wrap; gap:8px; word-break:break-all;">
                                <span><?= h($user['email']) ?></span>
                                <?php if (!empty($user['email_verified'])): ?>
                                    <span class="badge-verified"><i class="fas fa-check-circle"></i> Verified</span>
                                <?php else: ?>
                                    <span class="badge-unverified"><i class="fas fa-clock"></i> Unverified</span>
                                <?php endif; ?>
                            </div>
                            <?php if (defined('OTP_REQUIRED') && OTP_REQUIRED): ?>
                            <div class="otp-verify-row">
                                <?php if (empty($user['email_verified'])): ?>
                                    <a href="verify-otp.php?purpose=verify&type=email&contact=<?= urlencode($user['email'] ?? '') ?>" class="btn-outline-blue" style="font-size:0.8rem; padding:6px 12px;">
                                        <i class="fas fa-shield-alt"></i> Verify Now
                                    </a>
                                <?php else: ?>
                                    <a href="verify-otp.php?purpose=change_email&type=email&contact=<?= urlencode($user['email'] ?? '') ?>" class="btn-outline-blue" style="font-size:0.8rem; padding:6px 12px;">
                                        <i class="fas fa-exchange-alt"></i> Change via OTP
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="background:#eff6ff; border-left:4px solid #2563eb; padding:14px 18px; border-radius:8px; margin-top:20px; font-size:0.88rem; color:#1e40af;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Security:</strong> Email and Mobile Number changes require OTP verification.
                    </div>
                </div>

                <div class="acc-card">
                    <div class="acc-card-header">
                        <div class="acc-card-title"><i class="fas fa-map-marker-alt"></i> Location Information</div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="update_location" value="1">
                        <div class="form-grid-2">
                            <div class="form-field"><label>State</label><input type="text" name="state" value="<?= h($user['state'] ?? '') ?>" placeholder="e.g. Uttar Pradesh"></div>
                            <div class="form-field"><label>District</label><input type="text" name="district" value="<?= h($user['district'] ?? '') ?>" placeholder="e.g. Maharajganj"></div>
                            <div class="form-field"><label>City / Town</label><input type="text" name="city" value="<?= h($user['city'] ?? '') ?>" placeholder="e.g. Nichlaul"></div>
                            <div class="form-field"><label>Village / Locality</label><input type="text" name="locality" value="<?= h($user['locality'] ?? '') ?>" placeholder="e.g. Main Market"></div>
                            <div class="form-field"><label>PIN Code</label><input type="text" name="pincode" value="<?= h($user['pincode'] ?? '') ?>" placeholder="e.g. 273164" maxlength="10"></div>
                        </div>
                        <div style="margin-top: 18px;"><button type="submit" class="btn-primary-blue"><i class="fas fa-save"></i> Update Location</button></div>
                    </form>
                </div>

                <div class="acc-card">
                    <div class="acc-card-header">
                        <div class="acc-card-title"><i class="fas fa-id-badge"></i> Account Details</div>
                    </div>
                    <div class="info-grid">
                        <div class="info-item"><label>Account ID</label><div>#<?= str_pad($user['id'], 6, '0', STR_PAD_LEFT) ?></div></div>
                        <div class="info-item"><label>Account Type</label><div>Job Seeker</div></div>
                        <div class="info-item"><label>Registration Date</label><div><?= date('d M Y', strtotime($user['created_at'])) ?></div></div>
                        <div class="info-item"><label>Account Status</label><div><span class="status-badge status-<?= ($user['status'] ?? 'active') === 'active' ? 'active' : 'pending' ?>"><?= ucfirst($user['status'] ?? 'active') ?></span></div></div>
                        <div class="info-item"><label>Mobile Verified</label><div><?= !empty($user['mobile_verified']) ? 'Yes' : 'No' ?></div></div>
                        <div class="info-item"><label>Email Verified</label><div><?= !empty($user['email_verified']) ? 'Yes' : 'No' ?></div></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============ PROFILE TAB ============ -->
        <?php if ($tab === 'profile'): ?>
        <div class="acc-container" style="max-width: 1100px;">
            <div class="acc-card" style="margin-bottom: 24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px;">
                    <div>
                        <h1 style="font-size:1.6rem; color:#111827; margin:0 0 6px;">My Profile</h1>
                        <p style="color:#6b7280; margin:0;">Complete your profile to increase your chances of getting hired.</p>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:1.8rem; font-weight:800; color:#2563eb; line-height:1;"><?= $completion ?>%</div>
                        <div style="font-size:0.8rem; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px;">Profile Completed</div>
                    </div>
                </div>
                <div style="width:100%; height:12px; background:#e5e7eb; border-radius:10px; margin-top:15px; overflow:hidden;">
                    <div style="height:100%; width:<?= $completion ?>%; background:<?= $completion >= 80 ? '#22c55e' : '#2563eb' ?>; border-radius:10px; transition: width 0.5s;"></div>
                </div>
            </div>

            <div style="background:#fff; border-radius:14px 14px 0 0; border:1px solid #e5e7eb; border-bottom:none; display:flex; overflow-x:auto;">
                <button type="button" class="profile-tab-btn active" onclick="showProfileTab('personal', this)" style="padding:16px 24px; border:none; background:transparent; font-weight:600; color:#2563eb; border-bottom:3px solid #2563eb; cursor:pointer; font-size:0.95rem; white-space:nowrap; display:flex; align-items:center; gap:8px;"><i class="fas fa-user"></i> Personal Info</button>
                <button type="button" class="profile-tab-btn" onclick="showProfileTab('education', this)" style="padding:16px 24px; border:none; background:transparent; font-weight:600; color:#6b7280; border-bottom:3px solid transparent; cursor:pointer; font-size:0.95rem; white-space:nowrap; display:flex; align-items:center; gap:8px;"><i class="fas fa-graduation-cap"></i> Education</button>
                <button type="button" class="profile-tab-btn" onclick="showProfileTab('skills', this)" style="padding:16px 24px; border:none; background:transparent; font-weight:600; color:#6b7280; border-bottom:3px solid transparent; cursor:pointer; font-size:0.95rem; white-space:nowrap; display:flex; align-items:center; gap:8px;"><i class="fas fa-cog"></i> Skills</button>
                <button type="button" class="profile-tab-btn" onclick="showProfileTab('experience', this)" style="padding:16px 24px; border:none; background:transparent; font-weight:600; color:#6b7280; border-bottom:3px solid transparent; cursor:pointer; font-size:0.95rem; white-space:nowrap; display:flex; align-items:center; gap:8px;"><i class="fas fa-briefcase"></i> Experience</button>
                <button type="button" class="profile-tab-btn" onclick="showProfileTab('preferences', this)" style="padding:16px 24px; border:none; background:transparent; font-weight:600; color:#6b7280; border-bottom:3px solid transparent; cursor:pointer; font-size:0.95rem; white-space:nowrap; display:flex; align-items:center; gap:8px;"><i class="fas fa-sliders-h"></i> Preferences</button>
                <button type="button" class="profile-tab-btn" onclick="showProfileTab('about', this)" style="padding:16px 24px; border:none; background:transparent; font-weight:600; color:#6b7280; border-bottom:3px solid transparent; cursor:pointer; font-size:0.95rem; white-space:nowrap; display:flex; align-items:center; gap:8px;"><i class="fas fa-align-left"></i> About Me</button>
            </div>

            <div style="background:#fff; padding:30px; border:1px solid #e5e7eb; border-radius:0 0 14px 14px;">
                <div id="ptab-personal" class="profile-tab-content">
                    <h2 style="font-size:1.3rem; margin-bottom:5px; color:#111827;">Personal Information</h2>
                    <p style="color:#6b7280; margin-bottom:25px;">Update your basic details shown to employers.</p>
                    <div style="display:flex; gap:30px; flex-wrap:wrap;">
                        <div style="text-align:center;">
                            <div style="width:130px; height:130px; border-radius:50%; background:#dbeafe; display:flex; align-items:center; justify-content:center; font-size:3.5rem; color:#2563eb; overflow:hidden; border:4px solid #fff; box-shadow:0 4px 12px rgba(37,99,235,0.2); margin-bottom:12px;">
                                <?php if (!empty($user['profile_photo'])): ?>
                                    <img src="<?= h($user['profile_photo']) ?>" style="width:100%; height:100%; object-fit:cover;">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="update_photo" value="1">
                                <label for="photoInput" style="display:inline-block; padding:8px 15px; background:#fff; border:1px solid #d1d5db; border-radius:8px; font-size:0.85rem; cursor:pointer; font-weight:600; color:#374151;"><i class="fas fa-camera"></i> Change Photo</label>
                                <input type="file" id="photoInput" name="profile_photo" accept=".jpg,.jpeg,.png,.webp" style="display:none;" onchange="this.form.submit()">
                            </form>
                            <p style="font-size:0.75rem; color:#6b7280; margin-top:8px;">JPG, PNG (Max 2MB)</p>
                        </div>
                        <div style="flex:1; min-width:300px;">
                            <form method="POST">
                                <input type="hidden" name="update_personal" value="1">
                                <div class="form-grid-2">
                                    <div class="form-field"><label>Full Name</label><input type="text" name="name" value="<?= h($user['name']) ?>" required></div>
                                    <div class="form-field"><label>Mobile Number</label><input type="text" name="phone" value="<?= h($user['phone']) ?>" required></div>
                                    <div class="form-field"><label>Date of Birth</label><input type="date" name="dob" value="<?= h($user['dob'] ?? '') ?>"></div>
                                    <div class="form-field"><label>Gender</label><select name="gender"><option value="">— Select —</option><option value="Male" <?= ($user['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option><option value="Female" <?= ($user['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option><option value="Other" <?= ($user['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option></select></div>
                                    <div class="form-field"><label>Email Address</label><input type="email" value="<?= h($user['email']) ?>" readonly style="background:#f9fafb;"></div>
                                    <div class="form-field"><label>City</label><input type="text" name="city" value="<?= h($user['city'] ?? '') ?>"></div>
                                </div>
                                <div style="margin-top:20px;"><button type="submit" class="btn-primary-blue"><i class="fas fa-save"></i> Save Changes</button></div>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="ptab-education" class="profile-tab-content" style="display:none;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <div><h2 style="font-size:1.3rem; margin-bottom:5px; color:#111827;">Education Details</h2><p style="color:#6b7280; margin:0;">Add your academic qualifications.</p></div>
                        <button type="button" onclick="document.getElementById('eduAddForm').style.display='block'" class="btn-primary-blue"><i class="fas fa-plus"></i> Add Education</button>
                    </div>
                    <div id="eduAddForm" style="display:none; background:#f9fafb; padding:20px; border-radius:10px; border:1px dashed #d1d5db; margin-bottom:20px;">
                        <form method="POST" action="ajax-profile-action.php">
                            <input type="hidden" name="action" value="add_education">
                            <div class="form-grid-2">
                                <div class="form-field"><label>Highest Qualification *</label><select name="qualification" required><option value="">— Select —</option><option>10th</option><option>12th</option><option>Graduation</option><option>Post Graduation</option><option>Diploma</option><option>PhD</option></select></div>
                                <div class="form-field"><label>Course / Stream</label><input type="text" name="stream" placeholder="e.g. B.Sc Computer Science"></div>
                                <div class="form-field"><label>School / College *</label><input type="text" name="school_name" required placeholder="Institute Name"></div>
                                <div class="form-field"><label>University / Board</label><input type="text" name="university" placeholder="University Name"></div>
                                <div class="form-field"><label>Passing Year *</label><input type="text" name="passing_year" placeholder="e.g. 2023" required></div>
                                <div class="form-field"><label>Percentage / CGPA</label><input type="text" name="percentage" placeholder="e.g. 85% or 8.5 CGPA"></div>
                                <div class="form-field"><label>Status</label><select name="status"><option value="Completed">Completed</option><option value="Pursuing">Pursuing</option></select></div>
                            </div>
                            <div style="margin-top:15px; display:flex; gap:10px;"><button type="submit" class="btn-primary-blue"><i class="fas fa-save"></i> Save Education</button><button type="button" onclick="document.getElementById('eduAddForm').style.display='none'" style="padding:10px 20px; background:#fff; border:1px solid #d1d5db; border-radius:8px; cursor:pointer; font-weight:600;">Cancel</button></div>
                        </form>
                    </div>
                    <?php $edu_q = $db->prepare("SELECT * FROM seeker_education WHERE seeker_id=? ORDER BY passing_year DESC"); $edu_q->bind_param('i', $uid); $edu_q->execute(); $edu_res = $edu_q->get_result();
                    if ($edu_res->num_rows === 0): ?>
                        <div style="text-align:center; padding:40px; background:#f9fafb; border-radius:10px; color:#6b7280;"><i class="fas fa-graduation-cap" style="font-size:3rem; color:#d1d5db; margin-bottom:10px; display:block;"></i><p>No education added yet. Click "Add Education" to start.</p></div>
                    <?php else: while($edu = $edu_res->fetch_assoc()): ?>
                        <div style="border:1px solid #e5e7eb; border-left:4px solid #2563eb; border-radius:8px; padding:18px; margin-bottom:12px;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
                                <div><h4 style="margin:0 0 5px; font-size:1.05rem; color:#111827; font-weight:700;"><?= h($edu['qualification']) ?> <?= !empty($edu['stream']) ? '- ' . h($edu['stream']) : '' ?></h4><p style="color:#6b7280; font-size:0.9rem; margin:0 0 3px;"><?= h($edu['school_name']) ?><?= !empty($edu['university']) ? ', ' . h($edu['university']) : '' ?></p><p style="color:#6b7280; font-size:0.85rem; margin:0;">Passing Year: <?= h($edu['passing_year']) ?><?= !empty($edu['percentage']) ? ' | ' . h($edu['percentage']) : '' ?></p></div>
                                <div style="display:flex; gap:8px;"><form method="POST" action="ajax-profile-action.php" onsubmit="return confirm('Delete this education?');" style="display:inline;"><input type="hidden" name="action" value="delete_education"><input type="hidden" name="edu_id" value="<?= $edu['id'] ?>"><button type="submit" style="padding:6px 12px; background:#fee2e2; color:#991b1b; border:none; border-radius:6px; cursor:pointer; font-size:0.82rem;"><i class="fas fa-trash"></i></button></form></div>
                            </div>
                        </div>
                    <?php endwhile; endif; ?>
                </div>

                <div id="ptab-skills" class="profile-tab-content" style="display:none;">
                    <h2 style="font-size:1.3rem; margin-bottom:5px; color:#111827;">Skills & Expertise</h2><p style="color:#6b7280; margin-bottom:25px;">Add skills to stand out to employers.</p>
                    <div style="background:#f9fafb; padding:20px; border-radius:10px; border:1px dashed #d1d5db; margin-bottom:20px;">
                        <form method="POST" action="ajax-profile-action.php"><input type="hidden" name="action" value="add_skill"><div style="display:grid; grid-template-columns: 2fr 1fr 1fr auto; gap:10px; align-items:end;">
                            <div class="form-field"><label>Skill Name *</label><input type="text" name="skill_name" placeholder="e.g. MS Excel" required></div>
                            <div class="form-field"><label>Level</label><select name="skill_level"><option value="Beginner">Beginner</option><option value="Intermediate" selected>Intermediate</option><option value="Advanced">Advanced</option></select></div>
                            <div class="form-field"><label>Category</label><select name="category"><option>General</option><option>Technical</option><option>Soft Skills</option><option>Language</option></select></div>
                            <button type="submit" class="btn-primary-blue" style="padding:11px 20px; height:42px;"><i class="fas fa-plus"></i> Add</button>
                        </div></form>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:10px;">
                        <?php $skills_q = $db->prepare("SELECT * FROM seeker_skills WHERE seeker_id=?"); $skills_q->bind_param('i', $uid); $skills_q->execute(); $skills_res = $skills_q->get_result();
                        if ($skills_res->num_rows === 0): ?>
                            <p style="color:#6b7280;">No skills added yet.</p>
                        <?php else: while($skill = $skills_res->fetch_assoc()): ?>
                            <div style="display:inline-flex; align-items:center; gap:8px; background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; padding:8px 14px; border-radius:20px; font-size:0.9rem; font-weight:600;"><?= h($skill['skill_name']) ?><span style="background:#dbeafe; color:#1e40af; padding:2px 8px; border-radius:10px; font-size:0.7rem; text-transform:uppercase;"><?= h($skill['skill_level'] ?? 'Beginner') ?></span><form method="POST" action="ajax-profile-action.php" style="display:inline; margin:0;"><input type="hidden" name="action" value="delete_skill"><input type="hidden" name="skill_id" value="<?= $skill['id'] ?>"><button type="submit" style="background:none; border:none; color:#1e40af; cursor:pointer; padding:0; font-size:0.85rem;" onclick="return confirm('Remove this skill?');"><i class="fas fa-times"></i></button></form></div>
                        <?php endwhile; endif; ?>
                    </div>
                </div>

                <div id="ptab-experience" class="profile-tab-content" style="display:none;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <div><h2 style="font-size:1.3rem; margin-bottom:5px; color:#111827;">Work Experience</h2><p style="color:#6b7280; margin:0;">Add your past work experience or select Fresher.</p></div>
                        <button type="button" onclick="document.getElementById('expAddForm').style.display='block'" class="btn-primary-blue"><i class="fas fa-plus"></i> Add Experience</button>
                    </div>
                    <div id="expAddForm" style="display:none; background:#f9fafb; padding:20px; border-radius:10px; border:1px dashed #d1d5db; margin-bottom:20px;">
                        <form method="POST" action="ajax-profile-action.php"><input type="hidden" name="action" value="add_experience"><div class="form-grid-2">
                            <div class="form-field"><label>Job Title *</label><input type="text" name="job_title" required placeholder="e.g. Data Entry Operator"></div>
                            <div class="form-field"><label>Company Name *</label><input type="text" name="company_name" required placeholder="e.g. ABC Pvt Ltd"></div>
                            <div class="form-field"><label>Employment Type</label><select name="employment_type"><option>Full-Time</option><option>Part-Time</option><option>Internship</option><option>Contract</option><option>Freelance</option></select></div>
                            <div class="form-field"><label>Start Date *</label><input type="date" name="start_date" required></div>
                            <div class="form-field"><label>End Date</label><input type="date" name="end_date"></div>
                            <div class="form-field"><label>Currently Working?</label><select name="is_current"><option value="0">No</option><option value="1">Yes</option></select></div>
                        </div>
                        <div class="form-field" style="margin-top:15px;"><label>Job Description</label><textarea name="description" rows="3" placeholder="Describe your responsibilities..."></textarea></div>
                        <div style="margin-top:15px; display:flex; gap:10px;"><button type="submit" class="btn-primary-blue"><i class="fas fa-save"></i> Save Experience</button><button type="button" onclick="document.getElementById('expAddForm').style.display='none'" style="padding:10px 20px; background:#fff; border:1px solid #d1d5db; border-radius:8px; cursor:pointer; font-weight:600;">Cancel</button></div></form>
                    </div>
                    <?php $exp_q = $db->prepare("SELECT * FROM seeker_experience WHERE seeker_id=? ORDER BY start_date DESC"); $exp_q->bind_param('i', $uid); $exp_q->execute(); $exp_res = $exp_q->get_result();
                    if ($exp_res->num_rows === 0): ?>
                        <div style="text-align:center; padding:40px; background:#f9fafb; border-radius:10px; color:#6b7280;"><i class="fas fa-briefcase" style="font-size:3rem; color:#d1d5db; margin-bottom:10px; display:block;"></i><p>No experience added yet.</p></div>
                    <?php else: while($exp = $exp_res->fetch_assoc()): ?>
                        <div style="border:1px solid #e5e7eb; border-left:4px solid #22c55e; border-radius:8px; padding:18px; margin-bottom:12px;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap;"><div><h4 style="margin:0 0 5px; font-size:1.05rem; color:#111827; font-weight:700;"><?= h($exp['job_title']) ?></h4><p style="color:#6b7280; font-size:0.9rem; margin:0 0 3px;"><?= h($exp['company_name']) ?></p><p style="color:#6b7280; font-size:0.85rem; margin:0;"><?= date('M Y', strtotime($exp['start_date'])) ?> - <?= !empty($exp['is_current']) ? 'Present' : (!empty($exp['end_date']) ? date('M Y', strtotime($exp['end_date'])) : 'Present') ?></p><?php if (!empty($exp['description'])): ?><p style="color:#4b5563; font-size:0.9rem; margin:10px 0 0;"><?= nl2br(h($exp['description'])) ?></p><?php endif; ?></div>
                            <form method="POST" action="ajax-profile-action.php" onsubmit="return confirm('Delete this experience?');"><input type="hidden" name="action" value="delete_experience"><input type="hidden" name="exp_id" value="<?= $exp['id'] ?>"><button type="submit" style="padding:6px 12px; background:#fee2e2; color:#991b1b; border:none; border-radius:6px; cursor:pointer; font-size:0.82rem;"><i class="fas fa-trash"></i></button></form></div>
                        </div>
                    <?php endwhile; endif; ?>
                </div>

                <div id="ptab-preferences" class="profile-tab-content" style="display:none;">
                    <h2 style="font-size:1.3rem; margin-bottom:5px; color:#111827;">Job Preferences</h2><p style="color:#6b7280; margin-bottom:25px;">Tell employers what kind of job you're looking for.</p>
                    <form method="POST" action="ajax-profile-action.php"><input type="hidden" name="action" value="update_preferences"><div class="form-grid-2">
                        <div class="form-field"><label>Preferred Job Type</label><select name="pref_job_type"><option value="full-time" <?= ($settings['pref_job_type'] ?? '') === 'full-time' ? 'selected' : '' ?>>Full-Time</option><option value="part-time" <?= ($settings['pref_job_type'] ?? '') === 'part-time' ? 'selected' : '' ?>>Part-Time</option><option value="contract" <?= ($settings['pref_job_type'] ?? '') === 'contract' ? 'selected' : '' ?>>Contract</option><option value="daily-wage" <?= ($settings['pref_job_type'] ?? '') === 'daily-wage' ? 'selected' : '' ?>>Daily Wage</option></select></div>
                        <div class="form-field"><label>Preferred Location</label><input type="text" name="pref_location" value="<?= h($settings['pref_location'] ?? '') ?>" placeholder="e.g. Nichlaul, Gorakhpur"></div>
                        <div class="form-field"><label>Expected Salary</label><input type="text" name="pref_expected_salary" value="<?= h($settings['pref_expected_salary'] ?? '') ?>" placeholder="e.g. ₹15,000/month"></div>
                    </div><div style="margin-top:20px;"><button type="submit" class="btn-primary-blue"><i class="fas fa-save"></i> Save Preferences</button></div></form>
                </div>

                <div id="ptab-about" class="profile-tab-content" style="display:none;">
                    <h2 style="font-size:1.3rem; margin-bottom:5px; color:#111827;">About Me</h2><p style="color:#6b7280; margin-bottom:25px;">Write a short professional introduction about yourself.</p>
                    <form method="POST" action="ajax-profile-action.php"><input type="hidden" name="action" value="update_about">
                        <div class="form-field"><label>Professional Headline</label><input type="text" name="headline" value="<?= h($user['headline'] ?? '') ?>" placeholder="e.g. Computer Operator | Data Entry Expert" maxlength="150"></div>
                        <div class="form-field" style="margin-top:15px;"><label>About Me <span id="charCount" style="font-size:0.8rem; color:#6b7280; float:right;"><?= strlen($user['about_me'] ?? '') ?> / 1000</span></label><textarea name="about_me" rows="6" maxlength="1000" placeholder="Write a brief summary..." oninput="document.getElementById('charCount').innerText = this.value.length + ' / 1000'"><?= h($user['about_me'] ?? '') ?></textarea></div>
                        <div style="margin-top:20px;"><button type="submit" class="btn-primary-blue"><i class="fas fa-save"></i> Save About Me</button></div>
                    </form>
                </div>
            </div>
        </div>
        <script>
        function showProfileTab(tabName, btnEl) {
            document.querySelectorAll('.profile-tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.profile-tab-btn').forEach(el => { el.classList.remove('active'); el.style.color = '#6b7280'; el.style.borderBottomColor = 'transparent'; });
            document.getElementById('ptab-' + tabName).style.display = 'block';
            btnEl.classList.add('active'); btnEl.style.color = '#2563eb'; btnEl.style.borderBottomColor = '#2563eb';
        }
        </script>
        <?php endif; ?>

        <!-- ============ SAVED JOBS TAB ============ -->
        <?php if ($tab === 'saved_jobs'): ?>
        <div class="acc-container">
            <div class="acc-header">
                <h1>Saved Jobs</h1>
                <p>Jobs you have bookmarked for later.</p>
            </div>

            <?php 
            $saved_q = $db->prepare("
                SELECT j.*, u.company_name, s.created_at as saved_date 
                FROM saved_jobs s 
                JOIN jobs j ON s.job_id = j.id 
                JOIN users u ON j.employer_id = u.id 
                WHERE s.seeker_id = ? AND j.status = 'active'
                ORDER BY s.created_at DESC
            ");
            $saved_q->bind_param('i', $uid);
            $saved_q->execute();
            $saved_res = $saved_q->get_result();

            if ($saved_res->num_rows === 0): ?>
                <div class="acc-card empty-state">
                    <i class="fas fa-bookmark"></i>
                    <h3>No saved jobs yet</h3>
                    <p>Click the bookmark icon on any job to save it for later.</p>
                    <a href="jobs.php" class="btn-primary-blue"><i class="fas fa-search"></i> Browse Jobs</a>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php while($sj = $saved_res->fetch_assoc()): ?>
                        <div class="acc-card" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px; border-left: 4px solid #2563eb;">
                            <div style="flex: 1; min-width: 250px;">
                                <h3 style="font-size: 1.1rem; color: #111827; margin-bottom: 5px;"><?= h($sj['title']) ?></h3>
                                <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 10px;"><i class="fas fa-building"></i> <?= h($sj['company_name']) ?> &bull; <i class="fas fa-map-marker-alt"></i> <?= h($sj['location']) ?></p>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <span class="badge-verified" style="background:#eff6ff; color:#1e40af;"><i class="fas fa-money-bill"></i> <?= h($sj['salary'] ?: 'N/A') ?></span>
                                    <span class="badge-verified" style="background:#f3f4f6; color:#374151;"><i class="fas fa-clock"></i> <?= h($sj['job_type']) ?></span>
                                    <span class="badge-verified" style="background:#fef3c7; color:#92400e;"><i class="fas fa-calendar"></i> Saved: <?= date('d M', strtotime($sj['saved_date'])) ?></span>
                                </div>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <a href="job.php?id=<?= $sj['id'] ?>" class="btn-primary-blue" style="padding: 8px 15px; font-size: 0.85rem;"><i class="fas fa-eye"></i> View Job</a>
                                <a href="job.php?id=<?= $sj['id'] ?>#apply" class="btn-primary-blue" style="background:#22c55e; padding: 8px 15px; font-size: 0.85rem;"><i class="fas fa-paper-plane"></i> Apply Now</a>
                                <button onclick="removeSavedJob(<?= $sj['id'] ?>)" style="background:#fee2e2; color:#991b1b; border:none; padding:8px 15px; border-radius:8px; font-weight:600; font-size:0.85rem; cursor:pointer;"><i class="fas fa-trash"></i> Remove</button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
        <script>
        function removeSavedJob(jobId) {
            if (confirm('Remove this job from saved?')) {
                let form = document.createElement('form');
                form.method = 'POST';
                form.action = 'ajax-save-job.php';
                form.innerHTML = `<input type="hidden" name="action" value="remove"><input type="hidden" name="job_id" value="${jobId}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
        </script>
        <?php endif; ?>

        <!-- ============ JOB ALERTS TAB ============ -->
        <?php if ($tab === 'alerts'): ?>
        <div class="acc-container">
            <div class="acc-header">
                <h1>Job Alerts</h1>
                <p>Get notified when new jobs match your criteria.</p>
            </div>

            <div class="acc-card" style="margin-bottom: 20px;">
                <h3 style="font-size: 1.1rem; margin-bottom: 15px;">Create New Alert</h3>
                <form method="POST" action="ajax-job-alerts.php">
                    <input type="hidden" name="action" value="create">
                    <div class="form-grid-2">
                        <div class="form-field"><label>Alert Name</label><input type="text" name="alert_name" placeholder="e.g. Driver Jobs in Gorakhpur" required></div>
                        <div class="form-field"><label>Keyword</label><input type="text" name="keyword" placeholder="e.g. Driver" required></div>
                        <div class="form-field"><label>Location</label><input type="text" name="location" placeholder="e.g. Gorakhpur"></div>
                        <div class="form-field"><label>Frequency</label><select name="frequency"><option value="immediate">Immediate</option><option value="daily">Daily Digest</option><option value="weekly">Weekly Digest</option></select></div>
                    </div>
                    <div style="margin-top:15px;"><button type="submit" class="btn-primary-blue"><i class="fas fa-plus"></i> Create Alert</button></div>
                </form>
            </div>

            <div class="acc-card">
                <h3 style="font-size: 1.1rem; margin-bottom: 15px;">Your Active Alerts</h3>
                <?php
                $alert_q = $db->prepare("SELECT * FROM job_alerts WHERE seeker_id=? ORDER BY created_at DESC");
                $alert_q->bind_param('i', $uid);
                $alert_q->execute();
                $alert_res = $alert_q->get_result();

                if ($alert_res->num_rows === 0): ?>
                    <p style="color: #6b7280;">You have no active job alerts. Create one above to get started.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php while($al = $alert_res->fetch_assoc()): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f9fafb; border-radius: 8px; border-left: 3px solid #2563eb;">
                                <div>
                                    <strong style="color: #111827; font-size: 0.95rem;"><?= h($al['keyword']) ?></strong> 
                                    <?php if(!empty($al['location'])): ?> <span style="color:#6b7280;">in <?= h($al['location']) ?></span><?php endif; ?>
                                    <br><small style="color: #6b7280; font-size: 0.8rem;">Frequency: <?= ucfirst($al['frequency'] ?? 'immediate') ?> &bull; Created: <?= date('d M Y', strtotime($al['created_at'])) ?></small>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <a href="jobs.php?q=<?= urlencode($al['keyword']) ?>" class="btn-outline-blue" style="padding:6px 12px; font-size:0.8rem; border-radius:6px; text-decoration:none;">View Jobs</a>
                                    <form method="POST" action="ajax-job-alerts.php" style="display:inline;"><input type="hidden" name="action" value="delete"><input type="hidden" name="alert_id" value="<?= $al['id'] ?>"><button type="submit" style="background:#fee2e2; color:#991b1b; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:0.8rem;" onclick="return confirm('Delete this alert?');"><i class="fas fa-trash"></i></button></form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============ NOTIFICATIONS TAB ============ -->
        <?php if ($tab === 'notifications'): ?>
        <div class="acc-container">
            <div class="acc-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                <div>
                    <h1>Notifications</h1>
                    <p>Stay updated on your job applications and alerts.</p>
                </div>
                <form method="POST" action="clear-notifications.php">
                    <input type="hidden" name="clear_all" value="1">
                    <button type="submit" class="btn-primary-blue" style="background:#fee2e2; color:#991b1b;"><i class="fas fa-trash"></i> Clear All</button>
                </form>
            </div>

            <div class="acc-card">
                <?php
                $notif_q = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
                $notif_q->bind_param('i', $uid);
                $notif_q->execute();
                $notif_res = $notif_q->get_result();

                if ($notif_res->num_rows === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No notifications yet</h3>
                        <p>You will see updates here when employers view or respond to your applications.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php while($n = $notif_res->fetch_assoc()): 
                            $is_unread = empty($n['is_read']);
                            $bg_color = $is_unread ? '#eff6ff' : '#fff';
                            $border_color = $is_unread ? '#2563eb' : '#e5e7eb';
                        ?>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; padding: 15px; background: <?= $bg_color ?>; border-radius: 8px; border-left: 3px solid <?= $border_color ?>;">
                                <div>
                                    <strong style="color: #111827; font-size: 0.95rem;"><?= h($n['title']) ?></strong>
                                    <p style="color: #4b5563; font-size: 0.88rem; margin: 5px 0;"><?= h($n['message']) ?></p>
                                    <small style="color: #9ca3af; font-size: 0.75rem;"><i class="far fa-clock"></i> <?= date('d M, h:i A', strtotime($n['created_at'])) ?></small>
                                </div>
                                <div style="display: flex; gap: 5px;">
                                    <?php if(!empty($n['link'])): ?>
                                        <a href="<?= h($n['link']) ?>" class="btn-outline-blue" style="padding:5px 10px; font-size:0.75rem; border-radius:4px; text-decoration:none;">View</a>
                                    <?php endif; ?>
                                    <form method="POST" action="clear-notifications.php" style="display:inline;"><input type="hidden" name="clear_single" value="1"><input type="hidden" name="id" value="<?= $n['id'] ?>"><button type="submit" style="background:none; border:none; color:#9ca3af; cursor:pointer; padding:5px;" title="Clear"><i class="fas fa-times"></i></button></form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============ PRIVACY & SECURITY TAB ============ -->
        <?php if ($tab === 'security'): ?>
        <div class="acc-container">
            <div class="acc-header">
                <h1>Privacy & Security</h1>
                <p>Manage your account privacy and security settings.</p>
            </div>

            <div class="acc-grid">
                <!-- Privacy -->
                <div class="acc-card">
                    <div class="acc-card-header"><div class="acc-card-title"><i class="fas fa-user-shield"></i> Privacy Settings</div></div>
                    <form method="POST">
                        <input type="hidden" name="update_privacy" value="1">
                        <div class="form-field" style="margin-bottom:15px;">
                            <label>Profile Visibility</label>
                            <select name="profile_visibility">
                                <option value="public" <?= ($settings['profile_visibility'] ?? '') === 'public' ? 'selected' : '' ?>>Public (Everyone can see)</option>
                                <option value="employers" <?= ($settings['profile_visibility'] ?? '') === 'employers' ? 'selected' : '' ?>>Registered Employers Only</option>
                                <option value="private" <?= ($settings['profile_visibility'] ?? '') === 'private' ? 'selected' : '' ?>>Private (Only you)</option>
                            </select>
                        </div>
                        <div class="form-field" style="margin-bottom:15px;">
                            <label>Resume Visibility</label>
                            <select name="resume_visibility">
                                <option value="visible" <?= ($settings['resume_visibility'] ?? '') === 'visible' ? 'selected' : '' ?>>Visible to Employers</option>
                                <option value="on_apply" <?= ($settings['resume_visibility'] ?? '') === 'on_apply' ? 'selected' : '' ?>>Only when I apply</option>
                                <option value="private" <?= ($settings['resume_visibility'] ?? '') === 'private' ? 'selected' : '' ?>>Private</option>
                            </select>
                        </div>

                        <hr style="margin:20px 0; border-top:1px solid #e5e7eb;">

                        <div class="toggle-row">
                            <div><strong>Show Profile Photo</strong><small>Allow employers to see your photo</small></div>
                            <label class="switch"><input type="checkbox" name="show_photo" value="1" <?= !empty($settings['show_photo']) ? 'checked' : '' ?>><span class="slider"></span></label>
                        </div>
                        <div class="toggle-row">
                            <div><strong>Show Email Address</strong><small>Visible on your public profile</small></div>
                            <label class="switch"><input type="checkbox" name="show_email" value="1" <?= !empty($settings['show_email']) ? 'checked' : '' ?>><span class="slider"></span></label>
                        </div>
                        <div class="toggle-row">
                            <div><strong>Show Phone Number</strong><small>Visible on your public profile</small></div>
                            <label class="switch"><input type="checkbox" name="show_phone" value="1" <?= !empty($settings['show_phone']) ? 'checked' : '' ?>><span class="slider"></span></label>
                        </div>
                        <div class="toggle-row">
                            <div><strong>Show Exact Location</strong><small>Allow employers to see your city</small></div>
                            <label class="switch"><input type="checkbox" name="show_location" value="1" <?= !empty($settings['show_location']) ? 'checked' : '' ?>><span class="slider"></span></label>
                        </div>

                        <div style="margin-top:20px;"><button type="submit" class="btn-primary-blue"><i class="fas fa-save"></i> Save Privacy Settings</button></div>
                    </form>
                </div>

                <!-- Change Contact via OTP -->
                <div class="acc-card">
                    <div class="acc-card-header"><div class="acc-card-title"><i class="fas fa-exchange-alt"></i> Change Contact (OTP Protected)</div></div>
                    <p style="color:#6b7280; font-size:0.9rem; margin-bottom:20px;">For security, changing your phone or email requires OTP verification. Click below to start the process.</p>

                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div style="background:#f9fafb; border-radius:10px; padding:16px; border-left:3px solid #2563eb;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <div>
                                    <strong style="display:block; color:#111827; font-size:0.95rem;"><i class="fas fa-phone" style="color:#2563eb;"></i> Change Phone Number</strong>
                                    <small style="color:#6b7280; font-size:0.85rem;">Current: <?= h($user['phone']) ?></small>
                                </div>
                                <a href="verify-otp.php?purpose=change_phone&type=phone&contact=<?= urlencode($user['phone'] ?? '') ?>" class="btn-outline-blue" style="font-size:0.85rem;">
                                    <i class="fas fa-shield-alt"></i> Start OTP
                                </a>
                            </div>
                        </div>

                        <div style="background:#f9fafb; border-radius:10px; padding:16px; border-left:3px solid #2563eb;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <div>
                                    <strong style="display:block; color:#111827; font-size:0.95rem;"><i class="fas fa-envelope" style="color:#2563eb;"></i> Change Email Address</strong>
                                    <small style="color:#6b7280; font-size:0.85rem; word-break:break-all;">Current: <?= h($user['email']) ?></small>
                                </div>
                                <a href="verify-otp.php?purpose=change_email&type=email&contact=<?= urlencode($user['email'] ?? '') ?>" class="btn-outline-blue" style="font-size:0.85rem;">
                                    <i class="fas fa-shield-alt"></i> Start OTP
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Password -->
                <div class="acc-card">
                    <div class="acc-card-header"><div class="acc-card-title"><i class="fas fa-lock"></i> Security Settings</div></div>
                    <h3 style="font-size:1.1rem; margin-bottom:15px;">Change Password</h3>
                    <form method="POST">
                        <div class="form-field" style="margin-bottom:15px;"><label>Current Password</label><input type="password" name="old_password" required></div>
                        <div class="form-field" style="margin-bottom:15px;"><label>New Password</label><input type="password" name="new_password" required minlength="6"></div>
                        <div style="margin-top:20px;"><button type="submit" name="change_password" class="btn-primary-blue"><i class="fas fa-key"></i> Update Password</button></div>
                    </form>

                    <hr style="margin:25px 0; border-top:1px solid #e5e7eb;">

                    <h3 style="font-size:1.1rem; margin-bottom:15px;">Active Session</h3>
                    <div style="background:#f9fafb; padding:15px; border-radius:8px; border-left:3px solid #22c55e;">
                        <p style="margin:0; color:#111827; font-weight:600;"><i class="fas fa-desktop"></i> Current Device</p>
                        <p style="margin:5px 0 0; font-size:0.85rem; color:#6b7280;"><?= $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device' ?></p>
                        <p style="margin:5px 0 0; font-size:0.85rem; color:#6b7280;">IP: <?= $_SERVER['REMOTE_ADDR'] ?? 'Unknown' ?></p>
                        <span class="badge-verified" style="margin-top:8px; display:inline-block;"><i class="fas fa-circle"></i> Active Now</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============ ACCOUNT PREFERENCES TAB ============ -->
        <?php if ($tab === 'preferences'): ?>
        <div class="acc-container">
            <div class="acc-header"><h1>Account Preferences</h1><p>Manage your language and notification settings.</p></div>
            <div class="acc-card">
                <form method="POST"><input type="hidden" name="update_preferences" value="1">
                    <div class="form-field" style="margin-bottom:15px;"><label>Language</label><select name="language"><option value="en" <?= $cur_lang === 'en' ? 'selected' : '' ?>>English</option><option value="hi" <?= $cur_lang === 'hi' ? 'selected' : '' ?>>हिन्दी (Hindi)</option></select></div>
                    <div class="toggle-row"><div><strong>Email Notifications</strong><small>Receive account-related emails</small></div><label class="switch"><input type="checkbox" name="email_notifications" value="1" checked><span class="slider"></span></label></div>
                    <div class="toggle-row"><div><strong>Job Alerts</strong><small>Get notified about matching jobs</small></div><label class="switch"><input type="checkbox" name="job_alerts" value="1" checked><span class="slider"></span></label></div>
                    <div class="toggle-row"><div><strong>Application Updates</strong><small>Updates on your applied jobs</small></div><label class="switch"><input type="checkbox" name="app_updates" value="1" checked><span class="slider"></span></label></div>
                    <div style="margin-top: 18px;"><button type="submit" class="btn-primary-blue"><i class="fas fa-save"></i> Save Preferences</button></div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============ VERIFICATION TAB ============ -->
        <?php if ($tab === 'verification'): ?>
        <div class="acc-container">
            <div class="acc-header"><h1>Verification Status</h1><p>Verify your account to get a Blue Tick on your profile.</p></div>
            <div class="acc-grid">
                <div class="acc-card">
                    <div class="acc-card-header"><div class="acc-card-title"><i class="fas fa-shield-alt"></i> Verification Details</div></div>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Mobile Verification</label>
                            <div>
                                <?php if (!empty($user['mobile_verified'])): ?>
                                    <span class="badge-verified"><i class="fas fa-check-circle"></i> Verified</span>
                                <?php else: ?>
                                    <span class="badge-unverified"><i class="fas fa-clock"></i> Unverified</span>
                                    <?php if (defined('OTP_REQUIRED') && OTP_REQUIRED): ?>
                                    <div style="margin-top:8px;">
                                        <a href="verify-otp.php?purpose=verify&type=phone&contact=<?= urlencode($user['phone'] ?? '') ?>" class="btn-outline-blue" style="font-size:0.8rem; padding:6px 12px;">
                                            <i class="fas fa-shield-alt"></i> Verify Now
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <label>Email Verification</label>
                            <div>
                                <?php if (!empty($user['email_verified'])): ?>
                                    <span class="badge-verified"><i class="fas fa-check-circle"></i> Verified</span>
                                <?php else: ?>
                                    <span class="badge-unverified"><i class="fas fa-clock"></i> Unverified</span>
                                    <?php if (defined('OTP_REQUIRED') && OTP_REQUIRED): ?>
                                    <div style="margin-top:8px;">
                                        <a href="verify-otp.php?purpose=verify&type=email&contact=<?= urlencode($user['email'] ?? '') ?>" class="btn-outline-blue" style="font-size:0.8rem; padding:6px 12px;">
                                            <i class="fas fa-shield-alt"></i> Verify Now
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item"><label>Account Verification</label><div><?php if (!empty($user['is_verified'])): ?><span class="badge-verified"><i class="fas fa-check-circle"></i> Verified</span><?php elseif (($user['verification_status'] ?? 'none') === 'pending'): ?><span class="badge-unverified" style="background:#dbeafe; color:#1e40af;"><i class="fas fa-clock"></i> Pending Review</span><?php else: ?><span class="badge-unverified"><i class="fas fa-times-circle"></i> Not Verified</span><?php endif; ?></div></div>
                        <div class="info-item"><label>Age Eligibility</label><div><?php if (!empty($user['dob'])): ?><?php $age = (new DateTime($user['dob']))->diff(new DateTime())->y; ?><?= $age >= 18 ? '<span class="badge-verified"><i class="fas fa-check-circle"></i> Eligible ('.$age.' yrs)</span>' : '<span class="badge-unverified"><i class="fas fa-exclamation-circle"></i> Under 18</span>' ?><?php else: ?><span class="badge-unverified"><i class="fas fa-exclamation-circle"></i> Add DOB</span><?php endif; ?></div></div>
                    </div>
                </div>
                <?php if (empty($user['is_verified']) && ($user['verification_status'] ?? 'none') !== 'pending'): ?>
                <div class="acc-card">
                    <div class="acc-card-header"><div class="acc-card-title"><i class="fas fa-upload"></i> Submit for Verification</div></div>
                    <p style="color:#6b7280; font-size:0.9rem; margin-bottom:15px;">Upload a valid ID (Aadhaar, PAN, or Business License) to get verified.</p>
                    <form method="POST" enctype="multipart/form-data"><input type="hidden" name="submit_verification" value="1">
                        <div class="form-field"><label>Upload Document (JPG, PNG, PDF)</label><input type="file" name="verification_doc" accept=".jpg,.jpeg,.png,.pdf" required></div>
                        <div style="margin-top: 18px;"><button type="submit" class="btn-primary-blue"><i class="fas fa-paper-plane"></i> Submit Document</button></div>
                    </form>
                </div>
                <?php elseif (($user['verification_status'] ?? 'none') === 'pending'): ?>
                <div class="acc-card" style="background:#eff6ff; border-color:#bfdbfe;"><div style="text-align:center; padding:20px;"><i class="fas fa-clock" style="font-size:3rem; color:#2563eb; margin-bottom:15px;"></i><h3 style="color:#1e40af; margin-bottom:5px;">Verification Pending</h3><p style="color:#1e40af; margin:0;">Your document is under review. Admin will verify it within 24-48 hours.</p></div></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============ SUPPORT TAB ============ -->
        <?php if ($tab === 'support'): ?>
        <div class="acc-container">
            <div class="acc-header"><h1>Help & Support</h1><p>Need help? We're here for you.</p></div>
            <div class="acc-card">
                <div class="help-actions" style="flex-direction:column; gap:15px;">
                    <a href="contact.php" style="padding:20px; justify-content:flex-start; width:100%; border-radius:12px;"><i class="fas fa-envelope" style="font-size:1.5rem; color:#2563eb;"></i><div><strong style="display:block; color:#111827; margin-bottom:2px;">Contact Support</strong><small style="color:#6b7280; font-weight:400;">Email or call our support team</small></div></a>
                    <a href="safety.php#report" style="padding:20px; justify-content:flex-start; width:100%; border-radius:12px;"><i class="fas fa-flag" style="font-size:1.5rem; color:#dc2626;"></i><div><strong style="display:block; color:#111827; margin-bottom:2px;">Report an Issue</strong><small style="color:#6b7280; font-weight:400;">Report a fake job or suspicious employer</small></div></a>
                    <a href="faq.php" style="padding:20px; justify-content:flex-start; width:100%; border-radius:12px;"><i class="fas fa-question-circle" style="font-size:1.5rem; color:#f59e0b;"></i><div><strong style="display:block; color:#111827; margin-bottom:2px;">Frequently Asked Questions</strong><small style="color:#6b7280; font-weight:400;">Find quick answers to common questions</small></div></a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============ LANGUAGE TAB ============ -->
        <?php if ($tab === 'language'): ?>
        <div class="acc-container">
            <div class="acc-header"><h1>Language Preferences</h1><p>Choose your preferred language for the website.</p></div>
            <div class="acc-card">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <a href="?tab=language&lang=en" style="padding: 20px; border-radius: 10px; border: 2px solid <?= $cur_lang === 'en' ? '#2563eb' : '#e5e7eb' ?>; background: <?= $cur_lang === 'en' ? '#eff6ff' : '#fff' ?>; text-decoration: none; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 600; color: <?= $cur_lang === 'en' ? '#1e40af' : '#374151' ?>;">English</span>
                        <?php if($cur_lang === 'en'): ?><i class="fas fa-check-circle" style="color:#2563eb;"></i><?php endif; ?>
                    </a>
                    <a href="?tab=language&lang=hi" style="padding: 20px; border-radius: 10px; border: 2px solid <?= $cur_lang === 'hi' ? '#2563eb' : '#e5e7eb' ?>; background: <?= $cur_lang === 'hi' ? '#eff6ff' : '#fff' ?>; text-decoration: none; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 600; color: <?= $cur_lang === 'hi' ? '#1e40af' : '#374151' ?>;">हिन्दी (Hindi)</span>
                        <?php if($cur_lang === 'hi'): ?><i class="fas fa-check-circle" style="color:#2563eb;"></i><?php endif; ?>
                    </a>
                </div>
                <p style="margin-top: 20px; font-size: 0.9rem; color: #6b7280;"><i class="fas fa-info-circle"></i> Changing the language will update the interface text where translation is available.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============ DELETE TAB (★ NEW ★) ============ -->
        <?php if ($tab === 'delete'): ?>
        <div class="acc-container" style="max-width: 800px;">
            <div class="acc-header" style="margin-bottom: 25px;">
                <h1 style="color:#dc2626;"><i class="fas fa-exclamation-triangle"></i> Delete Account</h1>
                <p>Once your account is deleted, there is no going back.</p>
            </div>

            <?php if (isset($_GET['err'])): ?>
                <div class="alert alert-err" style="margin-bottom:20px;">
                    <i class="fas fa-exclamation-circle"></i> <?= h($_GET['err']) ?>
                </div>
            <?php endif; ?>

            <!-- Warning Card -->
            <div class="acc-card" style="background:#fef2f2; border:2px solid #fecaca; margin-bottom:20px;">
                <h3 style="color:#991b1b; font-size:1.1rem; margin-bottom:12px;">
                    <i class="fas fa-times-circle"></i> This action cannot be undone
                </h3>
                <p style="color:#7f1d1d; font-size:0.92rem; margin-bottom:10px; line-height:1.6;">
                    Deleting your account will <strong>permanently remove</strong> the following:
                </p>
                <ul style="color:#7f1d1d; font-size:0.88rem; line-height:1.8; padding-left:20px; margin-bottom:12px;">
                    <li>Your profile, photo, and all personal information</li>
                    <li>All your job applications</li>
                    <li>Saved jobs and job alerts</li>
                    <li>Skills, education, and experience details</li>
                    <li>Resume and verification documents</li>
                    <li>Notifications and messages</li>
                </ul>
                <p style="color:#991b1b; font-weight:600; margin:0; font-size:0.9rem;">
                    <i class="fas fa-info-circle"></i> Your account cannot be recovered after deletion.
                </p>
            </div>

            <!-- Delete Form -->
            <div class="acc-card">
                <h3 style="font-size:1.1rem; margin-bottom:20px;">Confirm Account Deletion</h3>

                <form method="POST" action="delete-account.php" 
                      onsubmit="return confirmDelete(this);">
                    
                    <!-- Password -->
                    <div class="form-field" style="margin-bottom:18px;">
                        <label style="font-weight:600;">
                            Enter your password to confirm <span style="color:#dc2626;">*</span>
                        </label>
                        <input type="password" name="confirm_password" id="delPassword" required 
                               placeholder="Your current password" 
                               autocomplete="current-password">
                    </div>

                    <!-- Confirm Checkbox -->
                    <div style="margin-bottom:20px; padding:14px; background:#f9fafb; border-radius:8px; border:1px solid #e5e7eb;">
                        <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="confirm_delete" value="yes" id="delConfirm" required 
                                   style="margin-top:3px; width:18px; height:18px; cursor:pointer;">
                            <span style="font-size:0.9rem; color:#374151; line-height:1.5;">
                                I understand that deleting my account is <strong>permanent</strong> and 
                                all my data (applications, saved jobs, profile, etc.) will be <strong>immediately removed</strong>. 
                                This action cannot be undone.
                            </span>
                        </label>
                    </div>

                    <!-- Buttons -->
                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <a href="seeker-settings.php?tab=account" class="btn-outline-blue" 
                           style="padding:10px 20px; border-color:#e5e7eb; color:#374151;">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" 
                                style="background:#dc2626; color:#fff; padding:10px 24px; border:none; border-radius:8px; font-weight:600; font-size:0.9rem; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                            <i class="fas fa-trash-alt"></i> Delete My Account Permanently
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function confirmDelete(form) {
            const pwd = document.getElementById('delPassword').value.trim();
            const chk = document.getElementById('delConfirm').checked;
            
            if (!pwd) {
                alert('Please enter your password.');
                return false;
            }
            if (!chk) {
                alert('Please check the confirmation box.');
                return false;
            }
            return confirm(
                '⚠️ FINAL WARNING ⚠️\n\n' +
                'Are you absolutely sure you want to PERMANENTLY delete your account?\n\n' +
                'This action CANNOT be undone. All your data will be erased immediately.'
            );
        }
        </script>
        <?php endif; ?>

    </div>
</div>

<?php include 'inc/foot.php'; ?>