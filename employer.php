<?php
require_once 'inc/auth.php';
require_once 'inc/site-config.php';
must_role('employer');
$title = "Dashboard — " . SITE_NAME;

$uid = (int)$_SESSION['uid'];

if (!function_exists('column_exists')) {
    function column_exists($db, $table, $column) {
        $table  = $db->real_escape_string($table);
        $column = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && $res->num_rows > 0;
    }
}

try {
    $needed = [
        'profile_photo'       => "VARCHAR(255) NULL",
        'is_verified'         => "TINYINT(1) NOT NULL DEFAULT 0",
        'verification_status' => "VARCHAR(20) NOT NULL DEFAULT 'none'",
        'about_me'            => "TEXT NULL",
        'headline'            => "VARCHAR(255) NULL",
    ];
    foreach ($needed as $col => $def) {
        if (!column_exists($db, 'users', $col)) {
            try { $db->query("ALTER TABLE users ADD COLUMN `$col` $def"); } catch (Throwable $e) {}
        }
    }
} catch (Throwable $e) {}

$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
if (!$emp) { header("Location: logout.php"); exit; }

$completion = 20;
if (!empty($emp['company_name'])) $completion += 15;
if (!empty($emp['phone'])) $completion += 10;
if (!empty($emp['address'])) $completion += 10;
if (!empty($emp['city'])) $completion += 5;
if (!empty($emp['about_me'])) $completion += 15;
if (!empty($emp['profile_photo'])) $completion += 10;
if (!empty($emp['is_verified'])) $completion += 15;
if ($completion > 100) $completion = 100;

$show_ads = false;
try {
    $ads_res = $db->query("SELECT setting_value FROM site_settings WHERE setting_key='employer_show_ads' LIMIT 1");
    if ($ads_res && $row = $ads_res->fetch_assoc()) {
        $show_ads = ($row['setting_value'] == '1');
    }
} catch (Throwable $e) {}

$active_ads = null;
if ($show_ads) {
    try {
        $active_ads = $db->query("SELECT * FROM ads WHERE is_active = 1 AND (target_role = 'both' OR target_role = 'employer') ORDER BY RAND() LIMIT 1");
    } catch (Throwable $e) {}
}

try {
    $auto_close = $db->prepare("
        UPDATE jobs j
        SET j.status = 'closed'
        WHERE j.employer_id = ?
          AND j.status = 'active'
          AND (SELECT COUNT(*) FROM applications WHERE job_id = j.id AND status = 'hired') >= j.vacancies
    ");
    $auto_close->bind_param('i', $uid);
    $auto_close->execute();
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_id'], $_POST['job_status'])) {
    $allowed = ['active', 'closed'];
    $new_status = $_POST['job_status'];
    $jb_id = (int)$_POST['job_id'];
    if (in_array($new_status, $allowed)) {
        $u = $db->prepare("UPDATE jobs SET status=? WHERE id=? AND employer_id=?");
        $u->bind_param('sii', $new_status, $jb_id, $uid);
        $u->execute();
    }
    header("Location: employer.php?msg=" . urlencode("Job status updated."));
    exit;
}

$notifs = null;
try {
    $notif_q = $db->prepare("SELECT id, title, admin_note FROM jobs WHERE employer_id=? AND admin_note IS NOT NULL AND admin_note != ''");
    $notif_q->bind_param('i', $uid);
    $notif_q->execute();
    $notifs = $notif_q->get_result();
} catch (Throwable $e) {}

$stats = [
    'total_jobs' => 0, 'active_jobs' => 0, 'pending_jobs' => 0, 'expired_jobs' => 0,
    'total_apps' => 0, 'new_apps' => 0, 'shortlisted' => 0, 'interview' => 0, 'hired' => 0,
];

try {
    $st = $db->prepare("
        SELECT
          (SELECT COUNT(*) FROM jobs WHERE employer_id=?) AS total_jobs,
          (SELECT COUNT(*) FROM jobs WHERE employer_id=? AND status='active') AS active_jobs,
          (SELECT COUNT(*) FROM jobs WHERE employer_id=? AND status='pending') AS pending_jobs,
          (SELECT COUNT(*) FROM jobs WHERE employer_id=? AND status IN ('closed','expired')) AS expired_jobs,
          (SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.employer_id=?) AS total_apps,
          (SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.employer_id=? AND a.status='pending') AS new_apps,
          (SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.employer_id=? AND a.status='shortlisted') AS shortlisted,
          (SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.employer_id=? AND a.status='interview') AS interview,
          (SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.employer_id=? AND a.status='hired') AS hired
    ");
    $st->bind_param('iiiiiiiii', $uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if ($row) $stats = $row;
} catch (Throwable $e) {}

$recent_apps = null;
try {
    $ra = $db->prepare("
        SELECT a.*, su.name AS seeker_name, su.profile_photo AS seeker_photo,
               j.title AS job_title, j.id AS job_id
        FROM applications a
        JOIN users su ON a.seeker_id = su.id
        JOIN jobs j ON a.job_id = j.id
        WHERE j.employer_id = ?
        ORDER BY a.applied_at DESC
        LIMIT 5
    ");
    $ra->bind_param('i', $uid);
    $ra->execute();
    $recent_apps = $ra->get_result();
} catch (Throwable $e) {}

$active_jobs_list = null;
try {
    $al = $db->prepare("
        SELECT j.*,
               (SELECT COUNT(*) FROM applications WHERE job_id=j.id) AS total_apps,
               (SELECT COUNT(*) FROM applications WHERE job_id=j.id AND status='hired') AS hired_count
        FROM jobs j WHERE j.employer_id = ?
        ORDER BY
          CASE j.status WHEN 'active' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END,
          j.created_at DESC
        LIMIT 4
    ");
    $al->bind_param('i', $uid);
    $al->execute();
    $active_jobs_list = $al->get_result();
} catch (Throwable $e) {}

$recent_activity = null;
try {
    $act = $db->prepare("
        SELECT a.id, a.status, a.applied_at, a.shortlisted_at, a.hired_at,
               su.name AS seeker_name, j.title AS job_title
        FROM applications a
        JOIN users su ON a.seeker_id = su.id
        JOIN jobs j ON a.job_id = j.id
        WHERE j.employer_id = ?
        ORDER BY GREATEST(COALESCE(a.applied_at,'2000-01-01'), COALESCE(a.shortlisted_at,'2000-01-01'), COALESCE(a.hired_at,'2000-01-01')) DESC
        LIMIT 5
    ");
    $act->bind_param('i', $uid);
    $act->execute();
    $recent_activity = $act->get_result();
} catch (Throwable $e) {}

$gallery_imgs = [];
try {
    $gq = $db->prepare("SELECT gallery_images FROM users WHERE id=?");
    $gq->bind_param('i', $uid);
    $gq->execute();
    $grow = $gq->get_result()->fetch_assoc();
    if (!empty($grow['gallery_images'])) {
        $gallery_imgs = array_filter(explode(',', $grow['gallery_images']));
    }
} catch (Throwable $e) {}

function cat_icon($cat) {
    $map = ['Sales' => 'fa-shopping-cart','Driver' => 'fa-motorcycle','Teacher' => 'fa-chalkboard-teacher','Helper' => 'fa-hands-helping','Technician' => 'fa-tools','Office' => 'fa-briefcase','Security' => 'fa-shield-alt','Cooking' => 'fa-utensils'];
    return $map[$cat] ?? 'fa-tag';
}

include 'inc/head.php';
?>

<style>
.dash-clean { background: #f4f7fc; padding: 24px; margin: -20px; min-height: calc(100vh - 80px); }
.dash-clean * { box-sizing: border-box; }

/* Hero Banner — Seeker Style */
.emp-hero {
    background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
    border: 1px solid #c7d2fe;
    border-radius: 16px;
    padding: 28px 32px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.emp-hero h1 {
    font-size: 1.6rem;
    color: #111827;
    margin: 0 0 6px;
    font-weight: 700;
}
.emp-hero .grad-text {
    background: linear-gradient(135deg, #2563eb, #6366f1);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.emp-hero p {
    color: #6b7280;
    font-size: 0.95rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.emp-hero .verified-badge {
    background: #dcfce7;
    color: #166534;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.72rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* 5 Stat Boxes — Seeker Style */
.stats-row-clean {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.stat-box-clean {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    border-top: 4px solid #2563eb;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    transition: transform 0.2s, box-shadow 0.2s;
    text-decoration: none;
    color: inherit;
    display: block;
}
.stat-box-clean:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    text-decoration: none;
    color: inherit;
}
.stat-box-clean .num {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 6px;
}
.stat-box-clean .lbl {
    font-size: 0.8rem;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

/* Grid Layout */
.emp-grid-2 {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 24px;
    align-items: start;
}

/* Card */
.clean-card {
    background: #fff;
    border-radius: 14px;
    padding: 24px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    margin-bottom: 20px;
}
.clean-card-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #111827;
    margin: 0 0 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-bottom: 14px;
    border-bottom: 1px solid #f3f4f6;
}
.clean-card-title > span {
    display: flex;
    align-items: center;
    gap: 8px;
}
.clean-card-title a {
    font-size: 0.82rem;
    color: #2563eb;
    text-decoration: none;
    font-weight: 600;
}
.clean-card-title a:hover { text-decoration: underline; }

/* Job Grid Tiles */
.jobs-grid-clean {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
}
.job-tile-clean {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px;
    transition: all 0.2s;
    position: relative;
}
.job-tile-clean:hover {
    border-color: #2563eb;
    box-shadow: 0 4px 15px rgba(37,99,235,0.08);
    transform: translateY(-2px);
}
.job-tile-clean .job-cat-icon {
    width: 42px;
    height: 42px;
    background: #eff6ff;
    color: #2563eb;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    margin-bottom: 10px;
}
.job-tile-clean h3 {
    font-size: 0.95rem;
    color: #111827;
    margin: 0 0 6px;
    font-weight: 700;
    line-height: 1.3;
}
.job-tile-clean .job-meta-small {
    font-size: 0.78rem;
    color: #6b7280;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.job-tile-clean .job-meta-small span {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.job-tile-clean .job-pills {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.job-tile-clean .info-pill {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pill-active { background: #dcfce7; color: #166534; }
.pill-pending { background: #fef3c7; color: #92400e; }
.pill-paused { background: #ede9fe; color: #6d28d9; }
.pill-closed { background: #f3f4f6; color: #6b7280; }
.pill-rejected { background: #fee2e2; color: #991b1b; }

.job-tile-actions {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
    padding-top: 12px;
    border-top: 1px solid #f3f4f6;
}
.mini-icon-btn {
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s;
    background: #f3f4f6;
    color: #374151;
}
.mini-icon-btn:hover { background: #e5e7eb; }
.mini-icon-btn.pause { background: #fef3c7; color: #92400e; }
.mini-icon-btn.resume { background: #dcfce7; color: #166534; }
.mini-icon-btn.reopen { background: #dbeafe; color: #1e40af; }
.mini-icon-btn.primary { background: #2563eb; color: #fff; }
.mini-icon-btn.primary:hover { background: #1e40af; }

/* Recent Applications */
.recent-app-clean {
    display: flex;
    gap: 12px;
    padding: 12px;
    background: #f9fafb;
    border-radius: 10px;
    margin-bottom: 10px;
    text-decoration: none;
    color: inherit;
    border-left: 3px solid transparent;
    transition: all 0.2s;
}
.recent-app-clean:hover {
    background: #f3f4f6;
    text-decoration: none;
    color: inherit;
}
.recent-app-clean.pending { border-left-color: #f59e0b; }
.recent-app-clean.shortlisted { border-left-color: #3b82f6; }
.recent-app-clean.interview { border-left-color: #6366f1; }
.recent-app-clean.hired { border-left-color: #22c55e; }
.recent-app-clean.rejected { border-left-color: #ef4444; }

.ra-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #dbeafe;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
    flex-shrink: 0;
    overflow: hidden;
}
.ra-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.ra-info { flex: 1; min-width: 0; }
.ra-name {
    font-size: 0.9rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ra-sub {
    font-size: 0.78rem;
    color: #6b7280;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ra-time {
    font-size: 0.7rem;
    color: #9ca3af;
    margin-top: 3px;
}

.pill {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.pill-pending-status { background: #fef3c7; color: #92400e; }
.pill-shortlisted { background: #dbeafe; color: #1e40af; }
.pill-interview { background: #e0e7ff; color: #4338ca; }
.pill-hired-status { background: #dcfce7; color: #166534; }
.pill-rejected-status { background: #fee2e2; color: #991b1b; }

/* Sidebar widgets */
.side-stat-card {
    background: #fff;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    margin-bottom: 20px;
}
.side-stat-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #111827;
    margin: 0 0 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.progress-bar-clean {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    margin-top: 10px;
}
.progress-fill-clean {
    height: 100%;
    background: linear-gradient(90deg, #2563eb, #6366f1);
    border-radius: 10px;
    transition: width 0.5s;
}

.activity-item {
    position: relative;
    padding-left: 20px;
    padding-bottom: 14px;
}
.activity-item::before {
    content: '';
    position: absolute;
    left: 4px;
    top: 4px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #2563eb;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #dbeafe;
}
.activity-item::after {
    content: '';
    position: absolute;
    left: 8px;
    top: 16px;
    bottom: -2px;
    width: 2px;
    background: #e5e7eb;
}
.activity-item:last-child::after { display: none; }
.activity-item:last-child { padding-bottom: 0; }
.activity-item p {
    font-size: 0.82rem;
    color: #374151;
    margin: 0;
    line-height: 1.4;
}
.activity-item strong { color: #111827; }

.gallery-scroll-clean {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding-bottom: 6px;
}
.gallery-scroll-clean::-webkit-scrollbar { height: 4px; }
.gallery-scroll-clean::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
.gallery-scroll-clean img {
    height: 90px;
    width: 130px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    flex-shrink: 0;
}

.empty-clean {
    text-align: center;
    padding: 40px 20px;
    color: #9ca3af;
}
.empty-clean i {
    font-size: 2.5rem;
    color: #d1d5db;
    margin-bottom: 12px;
    display: block;
}
.empty-clean p {
    font-size: 0.88rem;
    margin: 0;
}

/* Verification Card */
.verify-card-green {
    text-align: center;
    padding: 16px;
    background: #dcfce7;
    border-radius: 10px;
}
.verify-card-green i { font-size: 1.8rem; color: #22c55e; display: block; margin-bottom: 8px; }
.verify-card-green strong { color: #166534; display: block; font-size: 0.92rem; }
.verify-card-green small { color: #16a34a; font-size: 0.78rem; }

.verify-card-blue {
    text-align: center;
    padding: 16px;
    background: #eff6ff;
    border-radius: 10px;
}
.verify-card-blue i { font-size: 1.8rem; color: #2563eb; display: block; margin-bottom: 8px; }
.verify-card-blue strong { color: #1e40af; display: block; font-size: 0.92rem; }
.verify-card-blue small { color: #3b82f6; font-size: 0.78rem; }

.verify-card-amber {
    text-align: center;
    padding: 16px;
    background: #fef3c7;
    border-radius: 10px;
}
.verify-card-amber i { font-size: 1.8rem; color: #f59e0b; display: block; margin-bottom: 8px; }
.verify-card-amber strong { color: #92400e; display: block; font-size: 0.92rem; }
.verify-card-amber small { color: #a16207; font-size: 0.78rem; }

@media (max-width: 1024px) {
    .stats-row-clean { grid-template-columns: repeat(3, 1fr); }
    .emp-grid-2 { grid-template-columns: 1fr; }
    .jobs-grid-clean { grid-template-columns: 1fr; }
}
@media (max-width: 700px) {
    .dash-clean { padding: 15px; }
    .stats-row-clean { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .stat-box-clean { padding: 14px 10px; }
    .stat-box-clean .num { font-size: 1.5rem; }
    .stat-box-clean .lbl { font-size: 0.7rem; }
    .emp-hero { padding: 20px; }
    .emp-hero h1 { font-size: 1.3rem; }
    .clean-card { padding: 16px; }
}
</style>

<div class="dash-clean">

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-ok" style="margin-bottom: 20px;"><i class="fas fa-check-circle"></i> <?= h($_GET['msg']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?>
    <div class="alert alert-err" style="margin-bottom: 20px;"><i class="fas fa-exclamation-circle"></i> <?= h($_GET['err']) ?></div>
<?php endif; ?>

<?php if ($notifs && $notifs->num_rows > 0): ?>
    <div style="background:#fef2f2; border:1px solid #fecaca; padding:20px; border-radius:12px; margin-bottom:24px;">
        <h3 style="color:#dc2626; margin:0 0 15px; font-size:1.05rem;"><i class="fas fa-bell"></i> Action Required — Admin Notification</h3>
        <?php while($n = $notifs->fetch_assoc()): ?>
            <div style="background:#fff; padding:14px; border-radius:8px; margin-bottom:10px; border-left:4px solid #dc2626;">
                <strong style="font-size:0.95rem; display:block; margin-bottom:4px; color:#111827;">Job: <?= h($n['title']) ?></strong>
                <span style="color:#7f1d1d; font-size:0.9rem;"><strong>Message:</strong> <?= h($n['admin_note']) ?></span>
                <div style="margin-top:10px;">
                    <a href="manage-job.php?id=<?= (int)$n['id'] ?>" style="background:#dc2626; color:#fff; padding:8px 14px; border-radius:6px; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px; font-size:0.85rem;">
                        <i class="fas fa-edit"></i> Edit & Resubmit
                    </a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<!-- HERO BANNER — Seeker Style -->
<div class="emp-hero">
    <div>
        <h1>Welcome back, <span class="grad-text"><?= h($emp['name']) ?></span>!</h1>
        <p>
            <i class="fas fa-building" style="color:#2563eb;"></i>
            <strong><?= h($emp['company_name'] ?? 'Your Business') ?></strong>
            <?php if (!empty($emp['is_verified'])): ?>
                <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
            <?php endif; ?>
        </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="post.php" class="btn-blue btn-lg" style="padding:12px 22px; font-size:0.95rem;">
            <i class="fas fa-plus-circle"></i> Post New Job
        </a>
    </div>
</div>

<!-- 5 STAT BOXES — Seeker Style -->
<div class="stats-row-clean">
    <a href="employer.php#jobs" class="stat-box-clean" style="border-top-color:#22c55e;">
        <div class="num" style="color:#22c55e;"><?= (int)$stats['active_jobs'] ?></div>
        <div class="lbl">Active Jobs</div>
    </a>
    <a href="employer.php#applicants" class="stat-box-clean" style="border-top-color:#2563eb;">
        <div class="num" style="color:#2563eb;"><?= (int)$stats['total_apps'] ?></div>
        <div class="lbl">Total Applicants</div>
    </a>
    <a href="employer.php#applicants" class="stat-box-clean" style="border-top-color:#f59e0b;">
        <div class="num" style="color:#f59e0b;"><?= (int)$stats['new_apps'] ?></div>
        <div class="lbl">Pending</div>
    </a>
    <a href="employer.php#applicants" class="stat-box-clean" style="border-top-color:#3b82f6;">
        <div class="num" style="color:#3b82f6;"><?= (int)$stats['shortlisted'] ?></div>
        <div class="lbl">Shortlisted</div>
    </a>
    <a href="employer.php#applicants" class="stat-box-clean" style="border-top-color:#166534;">
        <div class="num" style="color:#166534;"><?= (int)$stats['hired'] ?></div>
        <div class="lbl">Hired</div>
    </a>
</div>

<!-- MAIN GRID -->
<div class="emp-grid-2">
    
    <div>
        
        <!-- GALLERY PREVIEW -->
        <?php if (!empty($gallery_imgs)): ?>
        <div class="clean-card">
            <div class="clean-card-title">
                <span><i class="fas fa-images" style="color:#2563eb;"></i> Shop Gallery Preview</span>
                <a href="gallery.php">Manage →</a>
            </div>
            <div class="gallery-scroll-clean">
                <?php foreach (array_slice($gallery_imgs, 0, 8) as $img): ?>
                    <img src="storage/gallery/<?= h($img) ?>" alt="Gallery">
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- MY JOB POSTINGS — Grid -->
        <div class="clean-card" id="jobs">
            <div class="clean-card-title">
                <span><i class="fas fa-briefcase" style="color:#2563eb;"></i> My Job Postings</span>
                <a href="post.php">+ New Job</a>
            </div>
            
            <?php if ($active_jobs_list && $active_jobs_list->num_rows > 0): ?>
                <div class="jobs-grid-clean">
                    <?php while($j = $active_jobs_list->fetch_assoc()): 
                        $needed = max(0, (int)$j['vacancies'] - (int)$j['hired_count']);
                        $status_cls = 'pill-' . ($j['status'] === 'active' ? 'active' : ($j['status'] === 'pending' ? 'pending' : ($j['status'] === 'paused' ? 'paused' : ($j['status'] === 'rejected' ? 'rejected' : 'closed'))));
                    ?>
                        <div class="job-tile-clean">
                            <div class="job-cat-icon"><i class="fas <?= cat_icon($j['category'] ?? 'Others') ?>"></i></div>
                            <h3><?= h($j['title']) ?></h3>
                            <div class="job-meta-small">
                                <span><i class="fas fa-users"></i> <?= (int)$j['total_apps'] ?> applied</span>
                                <span><i class="fas fa-user-check"></i> <?= (int)$j['hired_count'] ?>/<?= (int)$j['vacancies'] ?> hired</span>
                            </div>
                            <div class="job-pills">
                                <span class="info-pill <?= $status_cls ?>">
                                    <?php if ($j['status'] === 'active'): ?><i class="fas fa-check-circle"></i>
                                    <?php elseif ($j['status'] === 'pending'): ?><i class="fas fa-clock"></i>
                                    <?php elseif ($j['status'] === 'paused'): ?><i class="fas fa-pause-circle"></i>
                                    <?php else: ?><i class="fas fa-times-circle"></i><?php endif; ?>
                                    <?= ucfirst($j['status']) ?>
                                </span>
                                <span class="info-pill" style="background:#f3f4f6; color:#6b7280;"><i class="fas fa-calendar"></i> <?= date('d M', strtotime($j['created_at'])) ?></span>
                            </div>
                            <div class="job-tile-actions">
                                <a href="job-applicants.php?id=<?= (int)$j['id'] ?>" class="mini-icon-btn primary">
                                    <i class="fas fa-users"></i> Applicants
                                </a>
                                <?php if ($j['status'] === 'active'): ?>
                                    <button type="button" onclick="jobAction(<?= (int)$j['id'] ?>, 'pause')" class="mini-icon-btn pause" title="Pause">
                                        <i class="fas fa-pause"></i>
                                    </button>
                                <?php elseif ($j['status'] === 'paused'): ?>
                                    <button type="button" onclick="jobAction(<?= (int)$j['id'] ?>, 'resume')" class="mini-icon-btn resume" title="Resume">
                                        <i class="fas fa-play"></i>
                                    </button>
                                <?php elseif ($j['status'] === 'closed'): ?>
                                    <button type="button" onclick="jobAction(<?= (int)$j['id'] ?>, 'reopen')" class="mini-icon-btn reopen" title="Reopen">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                <?php endif; ?>
                                <button type="button" onclick="jobAction(<?= (int)$j['id'] ?>, 'duplicate')" class="mini-icon-btn" title="Duplicate">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <a href="manage-job.php?id=<?= (int)$j['id'] ?>" class="mini-icon-btn" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php if ($active_jobs_list->num_rows >= 4): ?>
                <div style="text-align:center; margin-top:16px;">
                    <a href="my-jobs.php" style="color:#2563eb; text-decoration:none; font-weight:600; font-size:0.88rem;">
                        View all jobs <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-clean">
                    <i class="fas fa-briefcase"></i>
                    <p>No jobs posted yet. Start by posting your first job!</p>
                    <a href="post.php" class="btn-blue" style="display:inline-flex; margin-top:12px; padding:10px 20px; font-size:0.85rem;">
                        <i class="fas fa-plus-circle"></i> Post First Job
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- RECENT APPLICANTS -->
        <div class="clean-card" id="applicants">
            <div class="clean-card-title">
                <span><i class="fas fa-user-plus" style="color:#2563eb;"></i> Recent Applications</span>
                <a href="employer.php#jobs">View All →</a>
            </div>
            
            <?php if ($recent_apps && $recent_apps->num_rows > 0): ?>
                <?php while($ap = $recent_apps->fetch_assoc()): 
                    $st_cls = $ap['status'];
                    $pill_cls = 'pill-' . ($ap['status'] === 'pending' ? 'pending-status' : ($ap['status'] === 'hired' ? 'hired-status' : ($ap['status'] === 'rejected' ? 'rejected-status' : $ap['status'])));
                ?>
                    <a href="candidate-profile.php?id=<?= (int)$ap['id'] ?>" class="recent-app-clean <?= $st_cls ?>">
                        <div class="ra-avatar">
                            <?php if (!empty($ap['seeker_photo'])): ?>
                                <img src="<?= h($ap['seeker_photo']) ?>" alt="">
                            <?php else: ?>
                                <?= strtoupper(substr($ap['seeker_name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="ra-info">
                            <div class="ra-name"><?= h($ap['seeker_name']) ?></div>
                            <div class="ra-sub">Applied for: <?= h($ap['job_title']) ?></div>
                            <div class="ra-time"><i class="far fa-clock"></i> <?= date('d M, h:i A', strtotime($ap['applied_at'])) ?></div>
                        </div>
                        <div style="align-self:center;">
                            <span class="pill <?= $pill_cls ?>">
                                <?= ucfirst($ap['status']) ?>
                            </span>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-clean">
                    <i class="fas fa-inbox"></i>
                    <p>No applications yet. Post a job to start receiving applications.</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- SIDEBAR -->
    <div>
        
        <!-- PROFILE COMPLETION -->
        <div class="side-stat-card">
            <div class="side-stat-title">
                <i class="fas fa-chart-pie" style="color:#22c55e;"></i> Profile Completion
            </div>
            <div style="text-align:center; margin:12px 0;">
                <div style="font-size:2.2rem; font-weight:800; color:<?= $completion >= 80 ? '#22c55e' : ($completion >= 50 ? '#f59e0b' : '#ef4444') ?>;">
                    <?= $completion ?>%
                </div>
                <div style="font-size:0.8rem; color:#6b7280;">Profile Complete</div>
            </div>
            <div class="progress-bar-clean">
                <div class="progress-fill-clean" style="width:<?= $completion ?>%; background:<?= $completion >= 80 ? 'linear-gradient(90deg,#22c55e,#16a34a)' : 'linear-gradient(90deg,#2563eb,#6366f1)' ?>;"></div>
            </div>
            <?php if ($completion < 100): ?>
                <a href="employer-settings.php?tab=company" style="display:block; text-align:center; margin-top:15px; background:#2563eb; color:#fff; padding:10px; border-radius:8px; text-decoration:none; font-weight:600; font-size:0.85rem;">
                    Complete Profile →
                </a>
            <?php endif; ?>
        </div>

        <!-- VERIFICATION -->
        <div class="side-stat-card">
            <div class="side-stat-title">
                <i class="fas fa-shield-alt" style="color:#2563eb;"></i> Verification
            </div>
            <?php if (!empty($emp['is_verified'])): ?>
                <div class="verify-card-green">
                    <i class="fas fa-check-circle"></i>
                    <strong>Verified Business</strong>
                    <small>Blue tick shown on your profile</small>
                </div>
            <?php elseif (($emp['verification_status'] ?? '') === 'pending'): ?>
                <div class="verify-card-blue">
                    <i class="fas fa-clock"></i>
                    <strong>Under Review</strong>
                    <small>Admin will verify soon</small>
                </div>
            <?php else: ?>
                <div class="verify-card-amber">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Not Verified</strong>
                    <small>Build trust with candidates</small>
                    <a href="employer-settings.php?tab=verification" style="display:block; margin-top:12px; background:#f59e0b; color:#fff; padding:8px; border-radius:6px; text-decoration:none; font-weight:600; font-size:0.82rem;">
                        Verify Now →
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- RECENT ACTIVITY -->
        <div class="side-stat-card">
            <div class="side-stat-title">
                <i class="fas fa-history" style="color:#0891b2;"></i> Recent Activity
            </div>
            <?php if ($recent_activity && $recent_activity->num_rows > 0): ?>
                <?php while($act = $recent_activity->fetch_assoc()): 
                    $text = 'applied for';
                    if ($act['status'] === 'shortlisted') $text = 'shortlisted for';
                    elseif ($act['status'] === 'interview') $text = 'interview scheduled for';
                    elseif ($act['status'] === 'hired') $text = 'hired for';
                    elseif ($act['status'] === 'rejected') $text = 'rejected for';
                ?>
                    <div class="activity-item">
                        <p><strong><?= h($act['seeker_name']) ?></strong> <?= $text ?> <strong><?= h($act['job_title']) ?></strong></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-clean" style="padding: 20px 10px;">
                    <i class="fas fa-history"></i>
                    <p>No recent activity</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- SPONSORED AD -->
        <?php if ($show_ads && $active_ads && $active_ads->num_rows > 0): 
            $ad = $active_ads->fetch_assoc(); 
        ?>
            <div class="side-stat-card" style="padding:0; overflow:hidden;">
                <a href="<?= h($ad['ad_link']) ?>" target="_blank" style="display:block; text-decoration:none; color:inherit;">
                    <div style="position:relative;">
                        <img src="<?= h($ad['ad_image']) ?>" alt="<?= h($ad['ad_title']) ?>" style="width:100%; height:160px; object-fit:cover; display:block;">
                        <span style="position:absolute; top:8px; right:8px; background:rgba(0,0,0,0.75); color:#fff; font-size:0.65rem; padding:2px 7px; border-radius:4px; font-weight:700;">AD</span>
                    </div>
                    <div style="padding:14px;">
                        <h3 style="margin:0 0 5px; font-size:0.95rem; color:#111827; font-weight:700;"><?= h($ad['ad_title']) ?></h3>
                        <p style="margin:0; font-size:0.82rem; color:#6b7280;"><?= h(substr($ad['ad_content'], 0, 100)) ?>...</p>
                    </div>
                </a>
            </div>
        <?php endif; ?>
        
    </div>
</div>

</div>

<script>
function jobAction(jobId, action) {
    const messages = {
        'pause': 'Pause this job? It will be hidden from job seekers.',
        'resume': 'Resume this job? It will be visible again.',
        'reopen': 'Reopen this job? It will be visible to job seekers.',
        'duplicate': 'Create a copy of this job? The copy will be pending approval.'
    };
    
    if (!confirm(messages[action] || 'Are you sure?')) return;
    
    const fd = new FormData();
    fd.append('action', action);
    fd.append('job_id', jobId);
    
    fetch('ajax-job-status.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                alert('✅ ' + data.msg);
                if (action === 'duplicate' && data.new_job_id) {
                    window.location.href = 'manage-job.php?id=' + data.new_job_id;
                } else {
                    location.reload();
                }
            } else {
                alert('❌ ' + (data.msg || 'Failed'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('❌ Network error. Please try again.');
        });
}
</script>

<?php include 'inc/foot.php'; ?>