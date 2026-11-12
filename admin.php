<?php
$title = "Dashboard";
require_once 'inc/admin-head.php';

// Fetch Data & Stats
$stats = [
    'seekers'   => $db->query("SELECT COUNT(*) c FROM users WHERE role='seeker'")->fetch_assoc()['c'],
    'employers' => $db->query("SELECT COUNT(*) c FROM users WHERE role='employer'")->fetch_assoc()['c'],
    'jobs'      => $db->query("SELECT COUNT(*) c FROM jobs WHERE status != 'deleted'")->fetch_assoc()['c'],
    'pending_jobs' => $db->query("SELECT COUNT(*) c FROM jobs WHERE status='pending'")->fetch_assoc()['c'],
    
    'new_users' => $db->query("SELECT COUNT(*) c FROM users WHERE role!='admin' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'],
    'new_jobs'  => $db->query("SELECT COUNT(*) c FROM jobs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status != 'deleted'")->fetch_assoc()['c'],
];
?>

<h2 class="page-title">Overview</h2>
<div class="dashboard-stats" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:30px;">
    <div class="card" style="margin:0; padding:20px; display:flex; flex-direction:column; justify-content:space-between; gap:15px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:1.4rem; color:var(--accent-green);"><i class="fas fa-users"></i></div>
        </div>
        <div>
            <div style="color:var(--text-muted); font-size:0.85rem; font-weight:500;">Total Users</div>
            <div style="font-size:1.8rem; font-weight:700; color:#fff;"><?= number_format($stats['seekers'] + $stats['employers']) ?></div>
            <span style="font-size:0.8rem; color:var(--accent-green); font-weight:600;"><i class="fas fa-arrow-up"></i> <?= $stats['new_users'] ?> new this week</span>
        </div>
    </div>
    <div class="card" style="margin:0; padding:20px; display:flex; flex-direction:column; justify-content:space-between; gap:15px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:1.4rem; color:var(--accent-blue);"><i class="fas fa-briefcase"></i></div>
        </div>
        <div>
            <div style="color:var(--text-muted); font-size:0.85rem; font-weight:500;">Total Jobs</div>
            <div style="font-size:1.8rem; font-weight:700; color:#fff;"><?= number_format($stats['jobs']) ?></div>
            <span style="font-size:0.8rem; color:var(--accent-green); font-weight:600;"><i class="fas fa-arrow-up"></i> <?= $stats['new_jobs'] ?> new this week</span>
        </div>
    </div>
    <div class="card" style="margin:0; padding:20px; display:flex; flex-direction:column; justify-content:space-between; gap:15px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:1.4rem; color:var(--accent-yellow);"><i class="fas fa-clock"></i></div>
        </div>
        <div>
            <div style="color:var(--text-muted); font-size:0.85rem; font-weight:500;">Pending Jobs</div>
            <div style="font-size:1.8rem; font-weight:700; color:#fff;"><?= number_format($stats['pending_jobs']) ?></div>
        </div>
    </div>
    <div class="card" style="margin:0; padding:20px; display:flex; flex-direction:column; justify-content:space-between; gap:15px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:1.4rem; color:var(--accent-red);"><i class="fas fa-chart-line"></i></div>
        </div>
        <div>
            <div style="color:var(--text-muted); font-size:0.85rem; font-weight:500;">Revenue</div>
            <div style="font-size:1.8rem; font-weight:700; color:#fff;">₹0</div>
        </div>
    </div>
</div>

<div class="card">
    <h3 style="font-size:1rem; font-weight:600; margin-bottom:20px; color:#fff; border-bottom:1px solid var(--border-color); padding-bottom:12px;">Recent Activity</h3>
    <div style="display:flex; flex-direction:column; gap:16px;">
        <div style="display:flex; align-items:center; gap:12px; font-size:0.9rem; color:var(--text-muted);"><i class="fas fa-check-circle" style="color:var(--accent-green);"></i> New User Registration</div>
        <div style="display:flex; align-items:center; gap:12px; font-size:0.9rem; color:var(--text-muted);"><i class="fas fa-check-circle" style="color:var(--accent-green);"></i> New Job Posted (Pending)</div>
        <div style="display:flex; align-items:center; gap:12px; font-size:0.9rem; color:var(--text-muted);"><i class="fas fa-check-circle" style="color:var(--accent-green);"></i> Employer Verification</div>
        <div style="display:flex; align-items:center; gap:12px; font-size:0.9rem; color:var(--text-muted);"><i class="fas fa-check-circle" style="color:var(--accent-green);"></i> New Report</div>
    </div>
</div>

<?php include 'inc/foot.php'; ?>