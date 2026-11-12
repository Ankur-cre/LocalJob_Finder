<?php
require_once 'inc/auth.php';
require_once 'inc/site-config.php';
must_role('employer');
$title = "My Jobs — " . SITE_NAME;

$uid = (int)$_SESSION['uid'];
$current_page = 'jobs';

// Auto-create missing columns
if (!function_exists('col_exists')) {
    function col_exists($db, $table, $col) {
        $t = $db->real_escape_string($table);
        $c = $db->real_escape_string($col);
        $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return $r && $r->num_rows > 0;
    }
}
try {
    foreach ([
        'paused_at' => "DATETIME NULL",
        'closed_at' => "DATETIME NULL",
    ] as $col => $def) {
        if (!col_exists($db, 'jobs', $col)) {
            $db->query("ALTER TABLE jobs ADD COLUMN `$col` $def");
        }
    }
} catch (Throwable $e) {}

// ============ FILTER + SEARCH ============
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$valid_filters = ['all', 'active', 'pending', 'paused', 'closed', 'expired', 'rejected'];
if (!in_array($filter, $valid_filters)) $filter = 'all';

// Count stats
$count_q = $db->prepare("
    SELECT status, COUNT(*) c FROM jobs WHERE employer_id = ? GROUP BY status
");
$count_q->bind_param('i', $uid);
$count_q->execute();
$counts = ['all' => 0, 'active' => 0, 'pending' => 0, 'paused' => 0, 'closed' => 0, 'expired' => 0, 'rejected' => 0];
$res = $count_q->get_result();
while ($row = $res->fetch_assoc()) {
    $counts[$row['status']] = (int)$row['c'];
    $counts['all'] += (int)$row['c'];
}

// Build query
$where = "j.employer_id = ?";
$params = [$uid];
$types = 'i';

if ($filter === 'expired') {
    $where .= " AND j.status IN ('expired', 'closed')";
} elseif ($filter !== 'all') {
    $where .= " AND j.status = ?";
    $params[] = $filter;
    $types .= 's';
}

if ($search !== '') {
    $where .= " AND (j.title LIKE ? OR j.location LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

// Sort
$order = "j.created_at DESC";
if ($sort === 'oldest') $order = "j.created_at ASC";
elseif ($sort === 'title') $order = "j.title ASC";
elseif ($sort === 'applicants') {
    $order = "(SELECT COUNT(*) FROM applications WHERE job_id = j.id) DESC";
}

// Total count
$ct = $db->prepare("SELECT COUNT(*) c FROM jobs j WHERE $where");
$ct->bind_param($types, ...$params);
$ct->execute();
$total_jobs_filtered = (int)$ct->get_result()->fetch_assoc()['c'];
$total_pages = max(1, ceil($total_jobs_filtered / $per_page));

// Fetch jobs
$sql = "
    SELECT j.*, 
           (SELECT COUNT(*) FROM applications WHERE job_id = j.id) AS total_apps,
           (SELECT COUNT(*) FROM applications WHERE job_id = j.id AND status = 'hired') AS hired_count,
           (SELECT COUNT(*) FROM applications WHERE job_id = j.id AND status = 'pending') AS pending_apps
    FROM jobs j 
    WHERE $where 
    ORDER BY $order 
    LIMIT $per_page OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$jobs = $stmt->get_result();

include 'inc/head.php';
?>

<style>
.mj-wrap { background: #f4f7fc; padding: 24px; margin: -20px; min-height: calc(100vh - 80px); }
.mj-wrap * { box-sizing: border-box; }

.mj-header {
    background: #fff; border-radius: 12px; padding: 20px 24px; margin-bottom: 20px;
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
    border-left: 4px solid #6366f1;
}
.mj-header h1 { font-size: 1.4rem; color: #111827; margin: 0 0 4px; }
.mj-header p { font-size: 0.88rem; color: #6b7280; margin: 0; }

.mj-filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.mj-tab {
    padding: 8px 16px; background: #fff; border: 1.5px solid #e5e7eb;
    border-radius: 8px; text-decoration: none; color: #6b7280;
    font-weight: 600; font-size: 0.82rem; display: inline-flex;
    align-items: center; gap: 6px; transition: all 0.2s;
}
.mj-tab:hover { border-color: #6366f1; color: #6366f1; text-decoration: none; }
.mj-tab.active { background: #6366f1; color: #fff; border-color: #6366f1; }
.mj-tab .count {
    background: rgba(255,255,255,0.3); padding: 1px 7px;
    border-radius: 10px; font-size: 0.7rem; font-weight: 700;
}
.mj-tab:not(.active) .count { background: #f3f4f6; color: #6b7280; }

.mj-search-bar { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
.mj-search-bar input[type="text"] {
    flex: 1; min-width: 200px; padding: 10px 14px;
    border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 0.9rem;
    outline: none;
}
.mj-search-bar input[type="text"]:focus { border-color: #6366f1; }
.mj-search-bar select {
    padding: 10px 14px; border: 1.5px solid #e5e7eb;
    border-radius: 8px; font-size: 0.9rem; background: #fff;
}
.mj-search-bar button {
    padding: 10px 20px; background: #6366f1; color: #fff;
    border: none; border-radius: 8px; font-weight: 600;
    cursor: pointer; font-size: 0.9rem;
}

.job-card {
    background: #fff; border-radius: 12px; padding: 18px 20px;
    margin-bottom: 12px; border: 1px solid #e5e7eb;
    transition: box-shadow 0.2s;
    border-left: 4px solid #6366f1;
}
.job-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
.job-card.status-active { border-left-color: #22c55e; }
.job-card.status-pending { border-left-color: #f59e0b; }
.job-card.status-paused { border-left-color: #8b5cf6; }
.job-card.status-closed { border-left-color: #6b7280; }
.job-card.status-rejected { border-left-color: #ef4444; }

.job-header-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
.job-title-block { flex: 1; min-width: 200px; }
.job-title-block h3 {
    font-size: 1.05rem; color: #111827; margin: 0 0 4px; font-weight: 700;
}
.job-title-block h3 a { color: inherit; text-decoration: none; }
.job-title-block h3 a:hover { color: #6366f1; }
.job-meta-line {
    display: flex; flex-wrap: wrap; gap: 10px; font-size: 0.8rem; color: #6b7280;
}
.job-meta-line span { display: inline-flex; align-items: center; gap: 4px; }

.job-status-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 12px; border-radius: 12px; font-size: 0.72rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
}
.status-pill-active { background: #dcfce7; color: #166534; }
.status-pill-pending { background: #fef3c7; color: #92400e; }
.status-pill-paused { background: #ede9fe; color: #6d28d9; }
.status-pill-closed { background: #f3f4f6; color: #6b7280; }
.status-pill-rejected { background: #fee2e2; color: #991b1b; }

.job-stats-row {
    display: flex; gap: 16px; flex-wrap: wrap; margin-top: 12px;
    padding-top: 12px; border-top: 1px solid #f3f4f6;
}
.job-stat {
    display: flex; align-items: center; gap: 6px;
    font-size: 0.82rem; color: #6b7280;
}
.job-stat strong { color: #111827; font-size: 0.95rem; }

.job-actions {
    display: flex; gap: 6px; flex-wrap: wrap; margin-top: 12px;
}
.job-action-btn {
    padding: 6px 12px; border-radius: 6px; font-size: 0.78rem;
    font-weight: 600; text-decoration: none; border: none;
    cursor: pointer; display: inline-flex; align-items: center;
    gap: 4px; transition: all 0.2s;
}
.jb-primary { background: #6366f1; color: #fff; }
.jb-primary:hover { background: #4f46e5; color: #fff; }
.jb-success { background: #22c55e; color: #fff; }
.jb-success:hover { background: #16a34a; color: #fff; }
.jb-warn { background: #f59e0b; color: #fff; }
.jb-warn:hover { background: #d97706; color: #fff; }
.jb-danger { background: #ef4444; color: #fff; }
.jb-danger:hover { background: #dc2626; color: #fff; }
.jb-gray { background: #f3f4f6; color: #374151; }
.jb-gray:hover { background: #e5e7eb; }
.jb-purple { background: #8b5cf6; color: #fff; }
.jb-purple:hover { background: #7c3aed; color: #fff; }

.jobs-pagination {
    display: flex; justify-content: center; gap: 6px; margin-top: 20px; flex-wrap: wrap;
}
.jobs-pagination a, .jobs-pagination span {
    padding: 8px 14px; border-radius: 8px; text-decoration: none;
    background: #fff; border: 1.5px solid #e5e7eb; color: #374151;
    font-weight: 600; font-size: 0.85rem;
}
.jobs-pagination a:hover { border-color: #6366f1; color: #6366f1; text-decoration: none; }
.jobs-pagination .current { background: #6366f1; color: #fff; border-color: #6366f1; }
.jobs-pagination .disabled { opacity: 0.4; pointer-events: none; }

.empty-jobs {
    background: #fff; border-radius: 12px; padding: 60px 20px;
    text-align: center; border: 1px solid #e5e7eb;
}
.empty-jobs i { font-size: 3rem; color: #d1d5db; margin-bottom: 12px; display: block; }
.empty-jobs h3 { font-size: 1.1rem; color: #374151; margin: 0 0 8px; }
.empty-jobs p { color: #6b7280; margin: 0 0 20px; font-size: 0.9rem; }

@media (max-width: 600px) {
    .job-header-row { flex-direction: column; }
    .job-status-pill { align-self: flex-start; }
}
</style>

<div class="mj-wrap">

<div class="mj-header">
    <div>
        <h1><i class="fas fa-briefcase" style="color:#6366f1;"></i> My Jobs</h1>
        <p>Manage all your job postings in one place</p>
    </div>
    <a href="post.php" style="background:#6366f1; color:#fff; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
        <i class="fas fa-plus-circle"></i> Post New Job
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-ok" style="margin-bottom:20px;"><i class="fas fa-check-circle"></i> <?= h($_GET['msg']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?>
    <div class="alert alert-err" style="margin-bottom:20px;"><i class="fas fa-exclamation-circle"></i> <?= h($_GET['err']) ?></div>
<?php endif; ?>

<!-- FILTER TABS -->
<div class="mj-filter-tabs">
    <a href="?filter=all" class="mj-tab <?= $filter==='all'?'active':'' ?>">
        <i class="fas fa-list"></i> All <span class="count"><?= $counts['all'] ?></span>
    </a>
    <a href="?filter=active" class="mj-tab <?= $filter==='active'?'active':'' ?>">
        <i class="fas fa-check-circle"></i> Active <span class="count"><?= $counts['active'] ?></span>
    </a>
    <a href="?filter=pending" class="mj-tab <?= $filter==='pending'?'active':'' ?>">
        <i class="fas fa-clock"></i> Pending <span class="count"><?= $counts['pending'] ?></span>
    </a>
    <a href="?filter=paused" class="mj-tab <?= $filter==='paused'?'active':'' ?>">
        <i class="fas fa-pause-circle"></i> Paused <span class="count"><?= $counts['paused'] ?></span>
    </a>
    <a href="?filter=closed" class="mj-tab <?= $filter==='closed'?'active':'' ?>">
        <i class="fas fa-times-circle"></i> Closed <span class="count"><?= $counts['closed'] ?></span>
    </a>
    <a href="?filter=rejected" class="mj-tab <?= $filter==='rejected'?'active':'' ?>">
        <i class="fas fa-ban"></i> Rejected <span class="count"><?= $counts['rejected'] ?></span>
    </a>
</div>

<!-- SEARCH -->
<form method="GET" class="mj-search-bar">
    <input type="hidden" name="filter" value="<?= h($filter) ?>">
    <input type="text" name="q" placeholder="Search jobs by title or location..." value="<?= h($search) ?>">
    <select name="sort">
        <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest First</option>
        <option value="oldest" <?= $sort==='oldest'?'selected':'' ?>>Oldest First</option>
        <option value="title" <?= $sort==='title'?'selected':'' ?>>Title (A-Z)</option>
        <option value="applicants" <?= $sort==='applicants'?'selected':'' ?>>Most Applicants</option>
    </select>
    <button type="submit"><i class="fas fa-search"></i> Apply</button>
</form>

<!-- JOB LIST -->
<?php if ($jobs->num_rows === 0): ?>
    <div class="empty-jobs">
        <i class="fas fa-briefcase"></i>
        <h3>No jobs found</h3>
        <p><?= $filter === 'all' ? 'You have not posted any jobs yet.' : 'No jobs with this filter.' ?></p>
        <a href="post.php" style="background:#6366f1; color:#fff; padding:10px 24px; border-radius:8px; text-decoration:none; font-weight:600; display:inline-block;">
            <i class="fas fa-plus-circle"></i> Post Your First Job
        </a>
    </div>
<?php else: ?>
    <?php while($j = $jobs->fetch_assoc()): 
        $needed = max(0, (int)$j['vacancies'] - (int)$j['hired_count']);
        $status_class = 'status-' . $j['status'];
    ?>
        <div class="job-card <?= $status_class ?>" data-job-id="<?= (int)$j['id'] ?>">
            <div class="job-header-row">
                <div class="job-title-block">
                    <h3><a href="job.php?id=<?= (int)$j['id'] ?>"><?= h($j['title']) ?></a></h3>
                    <div class="job-meta-line">
                        <span><i class="fas fa-tag"></i> <?= h($j['category']) ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?= h($j['city']) ?>, <?= h($j['state']) ?></span>
                        <span><i class="fas fa-clock"></i> <?= h(ucfirst(str_replace('-', ' ', $j['job_type']))) ?></span>
                        <?php if (!empty($j['salary'])): ?>
                            <span><i class="fas fa-money-bill"></i> <?= h($j['salary']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
                    <span class="job-status-pill status-pill-<?= $j['status'] ?>">
                        <?php 
                            $status_icons = ['active' => 'fa-check-circle', 'pending' => 'fa-clock', 'paused' => 'fa-pause-circle', 'closed' => 'fa-times-circle', 'rejected' => 'fa-ban'];
                            $icon = $status_icons[$j['status']] ?? 'fa-info-circle';
                        ?>
                        <i class="fas <?= $icon ?>"></i> <?= h(ucfirst($j['status'])) ?>
                    </span>
                    <small style="color:#9ca3af; font-size:0.72rem;">
                        Posted <?= date('d M Y', strtotime($j['created_at'])) ?>
                    </small>
                </div>
            </div>
            
            <div class="job-stats-row">
                <div class="job-stat"><i class="fas fa-users" style="color:#6366f1;"></i> <strong><?= (int)$j['total_apps'] ?></strong> applicants</div>
                <div class="job-stat"><i class="fas fa-clock" style="color:#f59e0b;"></i> <strong><?= (int)$j['pending_apps'] ?></strong> pending</div>
                <div class="job-stat"><i class="fas fa-user-check" style="color:#22c55e;"></i> <strong><?= (int)$j['hired_count'] ?>/<?= (int)$j['vacancies'] ?></strong> hired</div>
                <div class="job-stat"><i class="fas fa-briefcase" style="color:#8b5cf6;"></i> <strong><?= $needed ?></strong> vacancies left</div>
            </div>
            
            <div class="job-actions">
                <a href="job-applicants.php?id=<?= (int)$j['id'] ?>" class="job-action-btn jb-primary">
                    <i class="fas fa-users"></i> View Applicants
                </a>
                
                <?php if ($j['status'] === 'active'): ?>
                    <button class="job-action-btn jb-warn" onclick="jobAction(<?= (int)$j['id'] ?>, 'pause')">
                        <i class="fas fa-pause"></i> Pause
                    </button>
                    <button class="job-action-btn jb-gray" onclick="jobAction(<?= (int)$j['id'] ?>, 'close')">
                        <i class="fas fa-times"></i> Close
                    </button>
                <?php elseif ($j['status'] === 'paused'): ?>
                    <button class="job-action-btn jb-success" onclick="jobAction(<?= (int)$j['id'] ?>, 'resume')">
                        <i class="fas fa-play"></i> Resume
                    </button>
                    <button class="job-action-btn jb-gray" onclick="jobAction(<?= (int)$j['id'] ?>, 'close')">
                        <i class="fas fa-times"></i> Close
                    </button>
                <?php elseif ($j['status'] === 'closed'): ?>
                    <button class="job-action-btn jb-success" onclick="jobAction(<?= (int)$j['id'] ?>, 'reopen')">
                        <i class="fas fa-redo"></i> Reopen
                    </button>
                <?php elseif ($j['status'] === 'pending'): ?>
                    <button class="job-action-btn jb-danger" onclick="jobAction(<?= (int)$j['id'] ?>, 'delete')">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                <?php endif; ?>
                
                <button class="job-action-btn jb-purple" onclick="jobAction(<?= (int)$j['id'] ?>, 'duplicate')">
                    <i class="fas fa-copy"></i> Duplicate
                </button>
                
                <a href="manage-job.php?id=<?= (int)$j['id'] ?>" class="job-action-btn jb-gray">
                    <i class="fas fa-edit"></i> Edit
                </a>
                
                <a href="job.php?id=<?= (int)$j['id'] ?>" class="job-action-btn jb-gray" target="_blank">
                    <i class="fas fa-eye"></i> Preview
                </a>
            </div>
        </div>
    <?php endwhile; ?>
    
    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
    <div class="jobs-pagination">
        <?php if ($page > 1): ?>
            <a href="?filter=<?= h($filter) ?>&q=<?= urlencode($search) ?>&sort=<?= h($sort) ?>&page=<?= $page - 1 ?>">← Prev</a>
        <?php else: ?>
            <span class="disabled">← Prev</span>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <?php if ($i === $page): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="?filter=<?= h($filter) ?>&q=<?= urlencode($search) ?>&sort=<?= h($sort) ?>&page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?filter=<?= h($filter) ?>&q=<?= urlencode($search) ?>&sort=<?= h($sort) ?>&page=<?= $page + 1 ?>">Next →</a>
        <?php else: ?>
            <span class="disabled">Next →</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

</div>

<script>
function jobAction(jobId, action) {
    const messages = {
        'pause': 'Pause this job? It will be hidden from job seekers.',
        'resume': 'Resume this job? It will be visible again.',
        'close': 'Close this job permanently? You can reopen it later.',
        'reopen': 'Reopen this job? It will be visible to job seekers.',
        'duplicate': 'Create a copy of this job? The copy will be pending approval.',
        'delete': 'Delete this job permanently? This cannot be undone!'
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
            alert('❌ Network error');
        });
}
</script>

<?php include 'inc/foot.php'; ?>