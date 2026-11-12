<?php
$current_tab = $_GET['tab'] ?? 'account';
$is_employer = (role() === 'employer');
$is_seeker = (role() === 'seeker');

if (!function_exists('isActiveTab')) {
    function isActiveTab($tab) {
        global $current_tab;
        return $current_tab === $tab;
    }
}

// Feature toggles
$resume_builder_on = defined('RESUME_BUILDER_ENABLED') ? RESUME_BUILDER_ENABLED : true;
$billing_on        = defined('BILLING_ENABLED') ? BILLING_ENABLED : false;   // ✅ NEW

// Employer verification status badge
$emp_verify_badge = '';
if ($is_employer && isset($user['verification_status'])) {
    if (!empty($user['is_verified'])) {
        $emp_verify_badge = '<span style="margin-left:auto; background:#dcfce7; color:#166534; font-size:0.62rem; padding:2px 6px; border-radius:8px; font-weight:700;">✓</span>';
    } elseif ($user['verification_status'] === 'pending') {
        $emp_verify_badge = '<span style="margin-left:auto; background:#dbeafe; color:#1e40af; font-size:0.62rem; padding:2px 6px; border-radius:8px; font-weight:700;">⏳</span>';
    }
}

// Seeker profile completion
$seeker_completion = 0;
if ($is_seeker && isset($user)) {
    $seeker_completion = 10;
    if (!empty($user['profile_photo'])) $seeker_completion += 10;
    if (!empty($user['headline'])) $seeker_completion += 10;
    if (!empty($user['about_me'])) $seeker_completion += 10;
    if (!empty($user['dob'])) $seeker_completion += 5;
    if (!empty($user['gender'])) $seeker_completion += 5;
}
?>

<div class="settings-sidebar">
    <div class="settings-sidebar-inner">
        <h3 class="settings-sidebar-title">Settings</h3>

        <?php if ($is_seeker): ?>
            <div style="background:linear-gradient(135deg,#eff6ff,#f0f9ff); border:1px solid #bfdbfe; border-radius:10px; padding:12px 14px; margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <span style="font-size:0.72rem; font-weight:700; color:#1e40af; text-transform:uppercase; letter-spacing:0.3px;">Profile</span>
                    <span style="font-size:0.8rem; font-weight:800; color:#2563eb;"><?= $seeker_completion ?>%</span>
                </div>
                <div style="width:100%; height:6px; background:#dbeafe; border-radius:10px; overflow:hidden;">
                    <div style="height:100%; width:<?= $seeker_completion ?>%; background:linear-gradient(90deg,#2563eb,#6366f1); border-radius:10px; transition:width 0.5s;"></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- MAIN GROUP -->
        <div class="nav-group-label">Main</div>

        <a href="?tab=account" class="settings-nav-item <?= isActiveTab('account') ? 'active' : '' ?>">
            <i class="fas fa-user-circle" style="color:#6366f1;"></i> <span>Account Info</span>
        </a>

        <?php if ($is_seeker): ?>
            <a href="?tab=profile" class="settings-nav-item <?= isActiveTab('profile') ? 'active' : '' ?>">
                <i class="fas fa-user-tie" style="color:#8b5cf6;"></i> <span>My Profile</span>
            </a>
            <?php if ($resume_builder_on): ?>
                <a href="?tab=resume_builder" class="settings-nav-item <?= isActiveTab('resume_builder') ? 'active' : '' ?>">
                    <i class="fas fa-file-pdf" style="color:#dc2626;"></i> <span>Resume Builder</span>
                </a>
            <?php endif; ?>
            <a href="?tab=saved_jobs" class="settings-nav-item <?= isActiveTab('saved_jobs') ? 'active' : '' ?>">
                <i class="fas fa-bookmark" style="color:#f59e0b;"></i> <span>Saved Jobs</span>
            </a>
            <a href="?tab=alerts" class="settings-nav-item <?= isActiveTab('alerts') ? 'active' : '' ?>">
                <i class="fas fa-bell" style="color:#0891b2;"></i> <span>Job Alerts</span>
            </a>
        <?php endif; ?>

        <?php if ($is_employer): ?>
            <a href="?tab=company" class="settings-nav-item <?= isActiveTab('company') ? 'active' : '' ?>">
                <i class="fas fa-building" style="color:#6366f1;"></i> <span>Company Profile</span>
            </a>
            <a href="?tab=verification" class="settings-nav-item <?= isActiveTab('verification') ? 'active' : '' ?>">
                <i class="fas fa-shield-alt" style="color:#16a34a;"></i> <span>Verification</span> <?= $emp_verify_badge ?>
            </a>
        <?php endif; ?>

        <a href="?tab=preferences" class="settings-nav-item <?= isActiveTab('preferences') ? 'active' : '' ?>">
            <i class="fas fa-sliders-h" style="color:#8b5cf6;"></i> <span><?= $is_employer ? 'Hiring Preferences' : 'Job Preferences' ?></span>
        </a>

        <a href="?tab=notifications" class="settings-nav-item <?= isActiveTab('notifications') ? 'active' : '' ?>">
            <i class="fas fa-envelope" style="color:#0ea5e9;"></i> <span>Notifications</span>
        </a>

        <?php if ($is_seeker): ?>
            <a href="?tab=verification" class="settings-nav-item <?= isActiveTab('verification') ? 'active' : '' ?>">
                <i class="fas fa-shield-alt" style="color:#16a34a;"></i> <span>Verification</span>
            </a>
        <?php endif; ?>

        <a href="?tab=security" class="settings-nav-item <?= isActiveTab('security') ? 'active' : '' ?>">
            <i class="fas fa-lock" style="color:#dc2626;"></i> <span>Privacy & Security</span>
        </a>

        <a href="?tab=language" class="settings-nav-item <?= isActiveTab('language') ? 'active' : '' ?>">
            <i class="fas fa-globe" style="color:#6366f1;"></i> <span>Language</span>
        </a>

        <?php if ($is_employer): ?>
            <!-- Hiring Tools -->
            <div class="nav-group-label">Hiring Tools</div>
            
            <a href="javascript:void(0)" class="settings-nav-item" style="opacity:0.5; cursor:not-allowed;" title="Coming soon">
                <i class="fas fa-search" style="color:#8b5cf6;"></i> <span>Find Candidates</span>
                <span style="margin-left:auto; background:#f3f4f6; color:#9ca3af; font-size:0.55rem; padding:1px 5px; border-radius:6px; font-weight:700;">SOON</span>
            </a>
            <a href="javascript:void(0)" class="settings-nav-item" style="opacity:0.5; cursor:not-allowed;" title="Coming soon">
                <i class="fas fa-chart-bar" style="color:#f59e0b;"></i> <span>Hiring Reports</span>
                <span style="margin-left:auto; background:#f3f4f6; color:#9ca3af; font-size:0.55rem; padding:1px 5px; border-radius:6px; font-weight:700;">SOON</span>
            </a>
            
            <!-- ✅ Billing only if enabled -->
            <?php if ($billing_on): ?>
                <a href="?tab=billing" class="settings-nav-item <?= isActiveTab('billing') ? 'active' : '' ?>">
                    <i class="fas fa-credit-card" style="color:#059669;"></i> <span>Billing</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <!-- SUPPORT GROUP -->
        <div class="nav-group-label">Support</div>

        <a href="?tab=support" class="settings-nav-item <?= isActiveTab('support') ? 'active' : '' ?>">
            <i class="fas fa-question-circle" style="color:#0891b2;"></i> <span>Help & Support</span>
        </a>

        <!-- DANGER ZONE -->
        <div class="nav-group-label" style="color:#dc2626;">Danger Zone</div>

        <a href="?tab=delete" class="settings-nav-item danger-item <?= isActiveTab('delete') ? 'active-danger' : '' ?>">
            <i class="fas fa-trash-alt"></i> <span>Delete Account</span>
        </a>
    </div>
</div>

<style>
/* =========================================
   SETTINGS LAYOUT (same as before)
   ========================================= */
.settings-layout-wrap { display: flex; max-width: 100% !important; padding: 0 !important; align-items: stretch; min-height: calc(100vh - 80px); }
.settings-sidebar { width: 280px; flex-shrink: 0; background: #fff; border-right: 1px solid var(--gray-border); height: calc(100vh - 80px); position: sticky; top: 80px; overflow-y: auto; }
.settings-sidebar-inner { padding: 24px 16px; }
.settings-sidebar::-webkit-scrollbar { width: 5px; }
.settings-sidebar::-webkit-scrollbar-track { background: transparent; }
.settings-sidebar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
.settings-sidebar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
.settings-sidebar-title { font-size: 1.25rem; margin-bottom: 18px; color: var(--text); padding-left: 8px; font-weight: 700; }
.nav-group-label { font-size: 0.68rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.6px; padding: 12px 10px 6px; margin-top: 6px; }
.nav-group-label:first-of-type { margin-top: 0; }
.settings-divider { margin: 12px 8px; border-top: 1px solid var(--gray-border); }
.settings-nav-item { display: flex; align-items: center; gap: 11px; padding: 10px 12px; color: #6b7280; text-decoration: none; border-radius: 8px; font-weight: 500; font-size: 0.88rem; margin-bottom: 2px; transition: all 0.15s ease; white-space: nowrap; }
.settings-nav-item:hover { background: #f8fafc; color: #111827; text-decoration: none; }
.settings-nav-item.active { background: #eff6ff; color: #1e40af; font-weight: 600; box-shadow: inset 3px 0 0 #6366f1; }
.settings-nav-item i { width: 18px; text-align: center; font-size: 1rem; flex-shrink: 0; }
.settings-nav-item span { flex: 1; }
.settings-nav-item.danger-item { color: #dc2626; }
.settings-nav-item.danger-item:hover { background: #fef2f2; color: #b91c1c; }
.settings-nav-item.danger-item.active-danger { background: #fee2e2; color: #991b1b; font-weight: 600; box-shadow: inset 3px 0 0 #dc2626; }
.settings-content-area { flex: 1; padding: 32px 40px; background: var(--gray-bg); min-width: 0; position: relative; }
.settings-back-btn { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: #fff; color: #6b7280; box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-decoration: none; transition: all 0.2s; position: absolute; top: 32px; right: 32px; z-index: 10; }
.settings-back-btn:hover { background: #6366f1; color: #fff; transform: translateX(-3px); }

@media (max-width: 900px) {
    .settings-layout-wrap { flex-direction: column; }
    .settings-sidebar { width: 100%; height: auto; position: static; border-right: none; border-bottom: 1px solid var(--gray-border); }
    .settings-sidebar-inner { padding: 12px; display: flex; overflow-x: auto; gap: 6px; }
    .settings-sidebar-title, .nav-group-label, .settings-divider { display: none; }
    .settings-nav-item { white-space: nowrap; margin-bottom: 0; background: #f3f4f6; padding: 8px 14px; font-size: 0.82rem; }
    .settings-nav-item i { display: none; }
    .settings-nav-item.active { background: #6366f1; color: #fff; box-shadow: none; }
    .settings-nav-item.danger-item { background: #fee2e2; }
    .settings-nav-item.danger-item.active-danger { background: #dc2626; color: #fff; }
    .settings-content-area { padding: 20px; }
    .settings-back-btn { top: 20px; right: 20px; width: 35px; height: 35px; }
}
</style>