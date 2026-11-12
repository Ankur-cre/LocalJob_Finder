<?php
/**
 * Employer Dashboard Sidebar Navigation
 * Path: inc/employer-nav.php
 * 
 * Include karne se pehle yeh variables set karo:
 *   $current_page = 'dashboard' | 'jobs' | 'applicants' | 'post' | 'settings' | 'chat'
 */

if (!function_exists('empNavActive')) {
    function empNavActive($page) {
        global $current_page;
        return (isset($current_page) && $current_page === $page) ? 'active' : '';
    }
}

$emp_sidebar_unread = 0;
try {
    $uid_nav = (int)$_SESSION['uid'];
    $cq = $db->prepare("SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0");
    if ($cq) {
        $cq->bind_param('i', $uid_nav);
        $cq->execute();
        $emp_sidebar_unread = (int)($cq->get_result()->fetch_assoc()['c'] ?? 0);
    }
} catch (Throwable $e) {}

$active_jobs_count = 0;
try {
    $ac = $db->prepare("SELECT COUNT(*) c FROM jobs WHERE employer_id = ? AND status = 'active'");
    if ($ac) {
        $ac->bind_param('i', $uid_nav);
        $ac->execute();
        $active_jobs_count = (int)($ac->get_result()->fetch_assoc()['c'] ?? 0);
    }
} catch (Throwable $e) {}
?>

<aside class="emp-sidebar">
    <div class="emp-sidebar-inner">
        
        <!-- Logo -->
        <div class="emp-sidebar-brand">
            <div class="emp-brand-icon"><i class="fas fa-briefcase"></i></div>
            <div>
                <h3>Employer</h3>
                <p>Dashboard</p>
            </div>
        </div>

        <!-- Main Nav -->
        <div class="emp-nav-label">Main</div>

        <a href="employer.php" class="emp-nav-item <?= empNavActive('dashboard') ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>

        <a href="my-jobs.php" class="emp-nav-item <?= empNavActive('jobs') ?>">
            <i class="fas fa-briefcase"></i>
            <span>My Jobs</span>
            <?php if ($active_jobs_count > 0): ?>
                <span class="emp-nav-badge"><?= $active_jobs_count ?></span>
            <?php endif; ?>
        </a>

        <a href="post.php" class="emp-nav-item <?= empNavActive('post') ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Post a Job</span>
        </a>

        <a href="employer.php#applicants" class="emp-nav-item <?= empNavActive('applicants') ?>">
            <i class="fas fa-users"></i>
            <span>Applicants</span>
        </a>

        <a href="javascript:void(0)" class="emp-nav-item" style="opacity:0.55; cursor:not-allowed;" title="Coming soon">
            <i class="fas fa-search"></i>
            <span>Find Candidates</span>
            <span class="emp-nav-badge" style="background:#f3f4f6; color:#9ca3af;">Soon</span>
        </a>

        <!-- Account -->
        <div class="emp-nav-label">Account</div>

        <a href="employer-settings.php?tab=company" class="emp-nav-item">
            <i class="fas fa-building"></i>
            <span>Company Profile</span>
        </a>

        <a href="employer-settings.php?tab=verification" class="emp-nav-item">
            <i class="fas fa-shield-alt"></i>
            <span>Verification</span>
        </a>

        <a href="employer-settings.php" class="emp-nav-item <?= empNavActive('settings') ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>

        <!-- Support -->
        <div class="emp-nav-label">Support</div>

        <a href="employer-settings.php?tab=support" class="emp-nav-item">
            <i class="fas fa-question-circle"></i>
            <span>Help & Support</span>
        </a>

        <a href="chat.php" class="emp-nav-item <?= empNavActive('chat') ?>">
            <i class="fas fa-comments"></i>
            <span>Messages</span>
            <?php if ($emp_sidebar_unread > 0): ?>
                <span class="emp-nav-badge" style="background:#ef4444; color:#fff;"><?= $emp_sidebar_unread ?></span>
            <?php endif; ?>
        </a>

    </div>
</aside>

<style>
.emp-sidebar {
    width: 250px;
    flex-shrink: 0;
    background: #fff;
    border-right: 1px solid #e5e7eb;
    height: calc(100vh - 80px);
    position: sticky;
    top: 80px;
    overflow-y: auto;
}
.emp-sidebar::-webkit-scrollbar { width: 4px; }
.emp-sidebar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }

.emp-sidebar-inner { padding: 20px 14px; }

.emp-sidebar-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 8px 18px;
    border-bottom: 1px solid #f3f4f6;
    margin-bottom: 14px;
}
.emp-brand-icon {
    width: 38px;
    height: 38px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}
.emp-sidebar-brand h3 {
    font-size: 0.95rem;
    color: #111827;
    margin: 0 0 1px;
    font-weight: 700;
}
.emp-sidebar-brand p {
    font-size: 0.7rem;
    color: #9ca3af;
    margin: 0;
}

.emp-nav-label {
    font-size: 0.65rem;
    font-weight: 700;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 12px 10px 6px;
}

.emp-nav-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 10px 12px;
    color: #6b7280;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.88rem;
    margin-bottom: 2px;
    transition: all 0.15s;
    white-space: nowrap;
}
.emp-nav-item:hover {
    background: #f8fafc;
    color: #111827;
    text-decoration: none;
}
.emp-nav-item.active {
    background: #eff6ff;
    color: #1e40af;
    font-weight: 600;
    box-shadow: inset 3px 0 0 #6366f1;
}
.emp-nav-item i {
    width: 18px;
    text-align: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.emp-nav-item span { flex: 1; }

.emp-nav-badge {
    background: #eff6ff;
    color: #6366f1;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 10px;
}

@media (max-width: 900px) {
    .emp-sidebar {
        width: 100%;
        height: auto;
        position: static;
        border-right: none;
        border-bottom: 1px solid #e5e7eb;
    }
    .emp-sidebar-inner {
        padding: 12px;
        display: flex;
        overflow-x: auto;
        gap: 6px;
    }
    .emp-sidebar-brand,
    .emp-nav-label { display: none; }
    .emp-nav-item {
        white-space: nowrap;
        margin-bottom: 0;
        background: #f3f4f6;
        padding: 8px 14px;
        font-size: 0.82rem;
    }
    .emp-nav-item i { display: none; }
    .emp-nav-item.active {
        background: #6366f1;
        color: #fff;
        box-shadow: none;
    }
    .emp-nav-badge { display: none; }
}
</style>