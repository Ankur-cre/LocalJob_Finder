<?php
require_once 'inc/auth.php';
require_once 'inc/locations.php';

if (!logged_in()) {
    header("Location: login.php?err=" . urlencode("Please login to browse jobs."));
    exit;
}

$title = t('nav.browse_jobs') . " — Local Jobs";
$uid = $_SESSION['uid'];

// ============ PAGINATION SETUP ============
$jobs_per_page = 12;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $jobs_per_page;

// Fetch Seeker's Location
$stmt = $db->prepare("SELECT city, district, state FROM users WHERE id=?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$seeker_loc = $stmt->get_result()->fetch_assoc();

// Filters
$q      = trim($_GET['q'] ?? '');
$cat    = trim($_GET['cat'] ?? '');
$state  = trim($_GET['state'] ?? '');
$dist   = trim($_GET['district'] ?? '');
$city   = trim($_GET['city'] ?? '');

$is_manual_search = ($q !== '' || $cat !== '' || $state !== '' || $dist !== '' || $city !== '');

// ============ BUILD WHERE CLAUSE ============
$where = "j.status = 'active'";
$where_types = '';
$where_params = [];

if ($q !== '') {
    $where .= " AND (j.title LIKE ? OR j.description LIKE ?)";
    $where_types .= 'ss';
    $where_params[] = "%$q%";
    $where_params[] = "%$q%";
}
if ($cat !== '') {
    $where .= " AND j.category = ?";
    $where_types .= 's';
    $where_params[] = $cat;
}

// Location filters
if ($state !== '') {
    $where .= " AND j.state = ?";
    $where_types .= 's';
    $where_params[] = $state;
}
if ($dist !== '') {
    $where .= " AND j.district = ?";
    $where_types .= 's';
    $where_params[] = $dist;
}
if ($city !== '') {
    $where .= " AND j.city = ?";
    $where_types .= 's';
    $where_params[] = $city;
}

// ============ COUNT QUERY ============
$count_sql = "SELECT COUNT(*) AS total FROM jobs j WHERE $where";
$count_stmt = $db->prepare($count_sql);
if ($where_types) {
    $count_stmt->bind_param($where_types, ...$where_params);
}
$count_stmt->execute();
$total_jobs_count = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, (int)ceil($total_jobs_count / $jobs_per_page));

if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $jobs_per_page;
}

// ============ MAIN QUERY (with is_saved check) ============
$sql = "SELECT j.*, u.company_name, u.is_verified,
          (SELECT id FROM applications WHERE job_id=j.id AND seeker_id=?) AS my_app_id,
          (SELECT status FROM applications WHERE job_id=j.id AND seeker_id=? LIMIT 1) AS my_app_status,
          (SELECT id FROM saved_jobs WHERE job_id=j.id AND seeker_id=? LIMIT 1) AS is_saved
        FROM jobs j JOIN users u ON j.employer_id = u.id
        WHERE $where
        ORDER BY j.created_at DESC
        LIMIT ? OFFSET ?";

$types = 'iii' . $where_types . 'ii';
$params = array_merge([$uid, $uid, $uid], $where_params, [$jobs_per_page, $offset]);

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$jobs = $stmt->get_result();

function cat_icon($cat) {
    $map = ['Sales' => 'fa-shopping-cart','Driver' => 'fa-motorcycle','Teacher' => 'fa-chalkboard-teacher','Helper' => 'fa-hands-helping','Technician' => 'fa-tools','Office' => 'fa-briefcase','Security' => 'fa-shield-alt','Cooking' => 'fa-utensils'];
    return $map[$cat] ?? 'fa-tag';
}

include 'inc/head.php';
?>

<!-- ============ INLINE CSS ============ -->
<style>
/* Pagination CSS */
.pagination-wrap {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 18px !important;
    flex-wrap: wrap !important;
    margin-top: 30px !important;
    padding: 20px 24px !important;
    background: #fff !important;
    border-radius: 14px !important;
    box-shadow: 0 2px 12px rgba(0,0,0,.06) !important;
    border: 1px solid #e5e7eb !important;
    width: 100% !important;
    box-sizing: border-box !important;
}
.pagination-info {
    font-size: 0.88rem !important;
    color: #6b7280 !important;
    white-space: nowrap !important;
}
.pagination-info strong { color: #111827 !important; font-weight: 700 !important; }
.pagination-numbers {
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    flex-wrap: wrap !important;
    justify-content: center !important;
    margin: 0 auto !important;
}
.pagination-wrap .page-btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-width: 38px !important;
    height: 38px !important;
    padding: 0 10px !important;
    border-radius: 8px !important;
    background: #fff !important;
    color: #374151 !important;
    border: 1.5px solid #e5e7eb !important;
    text-decoration: none !important;
    font-weight: 600 !important;
    font-size: 0.9rem !important;
    cursor: pointer !important;
    transition: all 0.2s !important;
    box-sizing: border-box !important;
}
.pagination-wrap .page-btn:hover:not(.disabled):not(.page-active) {
    background: #eff6ff !important;
    border-color: #2563eb !important;
    color: #2563eb !important;
    text-decoration: none !important;
    transform: translateY(-1px) !important;
}
.pagination-wrap .page-btn.page-active {
    background: linear-gradient(135deg, #2563eb, #1e40af) !important;
    color: #fff !important;
    border-color: #2563eb !important;
    box-shadow: 0 4px 12px rgba(37,99,235,.3) !important;
    cursor: default !important;
}
.pagination-wrap .page-btn.disabled {
    opacity: 0.4 !important;
    cursor: not-allowed !important;
    pointer-events: none !important;
}
.pagination-wrap .page-prev, .pagination-wrap .page-next { font-size: 0.8rem !important; }
.pagination-wrap .page-ellipsis {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-width: 30px !important;
    height: 38px !important;
    color: #9ca3af !important;
    font-weight: 700 !important;
}
.pagination-jump {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    font-size: 0.88rem !important;
    color: #6b7280 !important;
    white-space: nowrap !important;
}
.pagination-jump label { margin: 0 !important; font-weight: 500 !important; font-size: 0.85rem !important; }
.pagination-jump input {
    width: 60px !important;
    padding: 8px 10px !important;
    border: 1.5px solid #e5e7eb !important;
    border-radius: 8px !important;
    text-align: center !important;
    font-size: 0.9rem !important;
    font-weight: 600 !important;
    color: #111827 !important;
    outline: none !important;
    box-sizing: border-box !important;
}
.pagination-jump input:focus {
    border-color: #2563eb !important;
    box-shadow: 0 0 0 3px rgba(37,99,235,.1) !important;
}
@media (max-width: 700px) {
    .pagination-wrap {
        flex-direction: column !important;
        gap: 14px !important;
        padding: 16px !important;
    }
    .pagination-info { order: 2 !important; }
    .pagination-numbers { order: 1 !important; }
    .pagination-jump { order: 3 !important; }
    .pagination-wrap .page-btn { min-width: 34px !important; height: 34px !important; font-size: 0.82rem !important; }
}

/* Save Button CSS */
.save-btn {
    background: #fff;
    border: 1.5px solid #d1d5db;
    color: #6b7280;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
    padding: 0;
}
.save-btn:hover {
    border-color: #2563eb;
    color: #2563eb;
    background: #eff6ff;
}
.save-btn.saved {
    background: #eff6ff;
    border-color: #2563eb;
    color: #2563eb;
}
.save-btn.saved i {
    font-weight: 900;
}
</style>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-ok"><?= h($_GET['msg']) ?></div>
<?php endif; ?>

<!-- SEARCH / FILTER BAR -->
<div class="hero" style="padding:30px;">
    <h1 style="font-size:1.5rem;margin-bottom:6px;">
        <i class="fas fa-briefcase"></i> <?= t('nav.browse_jobs') ?>
    </h1>
    <p style="margin-bottom:18px;">
        Welcome, <?= h($_SESSION['name']) ?> — find your next opportunity.
        <?php if ($is_manual_search): ?>
            <br><small style="background:rgba(255,255,255,0.2); padding:4px 8px; border-radius:4px; display:inline-block; margin-top:8px;">
                <i class="fas fa-filter"></i> Filters applied
                <?php 
                $loc_parts = [];
                if ($city) $loc_parts[] = $city;
                if ($dist) $loc_parts[] = $dist;
                if ($state) $loc_parts[] = $state;
                if ($loc_parts) echo ' · ' . implode(', ', $loc_parts);
                ?>
            </small>
        <?php endif; ?>
    </p>
    
    <form method="GET" action="jobs.php" class="hero-search" style="display:flex; flex-wrap:wrap; gap:10px; max-width:100%;">
        
        <input type="text" name="q" placeholder="Job title or keyword..." value="<?= h($q) ?>" style="flex:2; min-width:200px;">
        
        <select name="cat" style="flex:1; min-width:150px;">
            <option value="">All Categories</option>
            <?php foreach (['Sales','Driver','Teacher','Helper','Technician','Office','Security','Cooking','Others'] as $c): ?>
                <option value="<?= $c ?>" <?= $cat===$c?'selected':'' ?>><?= $c ?></option>
            <?php endforeach; ?>
        </select>

        <select name="state" id="filterState" onchange="loadDistricts()" style="flex:1; min-width:150px;">
            <option value="">All States</option>
            <?php foreach (get_states() as $s): ?>
                <option value="<?= $s ?>" <?= $state===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>

        <select name="district" id="filterDistrict" onchange="loadCities()" style="flex:1; min-width:150px;">
            <option value="">All Districts</option>
            <?php if ($state): foreach (get_districts($state) as $d): ?>
                <option value="<?= $d ?>" <?= $dist===$d?'selected':'' ?>><?= $d ?></option>
            <?php endforeach; endif; ?>
        </select>

        <select name="city" id="filterCity" style="flex:1; min-width:150px;">
            <option value="">All Cities</option>
            <?php if ($state && $dist): foreach (get_cities($state, $dist) as $ci): ?>
                <option value="<?= $ci ?>" <?= $city===$ci?'selected':'' ?>><?= $ci ?></option>
            <?php endforeach; endif; ?>
        </select>

        <button type="submit" class="btn-blue" style="padding:12px 26px;"><i class="fas fa-search"></i> Search</button>
    </form>
</div>

<!-- RESULTS HEADER -->
<div class="latest-jobs-head">
    <div>
        <h2>
            <i class="fas fa-briefcase"></i>
            <?php if ($is_manual_search): ?>
                Search Results (<?= $total_jobs_count ?> total)
            <?php else: ?>
                All Jobs (<?= $total_jobs_count ?> total)
            <?php endif; ?>
        </h2>
        <p>Click any job to see full details & apply.</p>
    </div>
    <?php if ($is_manual_search): ?>
        <a href="jobs.php" class="btn-sm btn-gray"><i class="fas fa-times"></i> Clear Filters</a>
    <?php endif; ?>
</div>

<!-- JOB GRID -->
<?php if ($jobs->num_rows === 0): ?>
    <div class="card empty-state">
        <i class="fas fa-search"></i>
        <h3>No jobs found</h3>
        <p>
            <?php if ($is_manual_search): ?>
                Try <a href="jobs.php">clearing filters</a> to see all jobs.
            <?php else: ?>
                Filhal koi active job nahi hai. Check back soon!
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <div class="jobs-grid">
    <?php while ($j = $jobs->fetch_assoc()):
        $is_new = strtotime($j['created_at']) >= strtotime('-7 days');
        $applied = !empty($j['my_app_id']);
        $is_saved = !empty($j['is_saved']);
    ?>
        <article class="job-tile">
            <div class="job-tile-top">
                <div class="job-cat-icon"><i class="fas <?= cat_icon($j['category']) ?>"></i></div>
                <div style="display:flex; gap:5px; flex-wrap:wrap; align-items:center;">
                    <?php if (!empty($j['is_walkin'])): ?>
                        <span class="badge-new" style="background:#f59e0b; font-size:0.65rem;"><i class="fas fa-calendar-alt"></i> Walk-in</span>
                    <?php endif; ?>
                    <?php if (!empty($j['fresher_allowed'])): ?>
                        <span class="badge-new" style="background:#10b981; font-size:0.65rem;"><i class="fas fa-user-graduate"></i> Fresher</span>
                    <?php endif; ?>
                    <?php if ($is_new): ?>
                        <span class="badge-new"><i class="fas fa-bolt"></i> NEW</span>
                    <?php endif; ?>
                    
                    <!-- SAVE BUTTON -->
                    <button type="button" class="save-btn <?= $is_saved ? 'saved' : '' ?>" 
                            onclick="toggleSave(this, <?= (int)$j['id'] ?>)"
                            title="<?= $is_saved ? 'Remove from saved' : 'Save this job' ?>">
                        <i class="fas fa-bookmark"></i>
                    </button>
                </div>
            </div>

            <h3 class="job-tile-title"><?= h($j['title']) ?></h3>
            <p class="job-tile-company">
                <i class="fas fa-building"></i> <?= h($j['company_name']) ?>
                <?php if (!empty($j['is_verified'])): ?>
                    <i class="fas fa-check-circle" style="color: var(--blue); font-size: 0.85rem;" title="Verified Employer"></i>
                <?php endif; ?>
            </p>
            
            <p class="job-tile-desc">
                <?= h(mb_substr($j['description'], 0, 110)) ?><?= mb_strlen($j['description']) > 110 ? '…' : '' ?>
            </p>

            <div class="job-tile-pills">
                <span class="info-pill">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?= h($j['city']) ?><?= $j['district'] ? ', ' . h($j['district']) : '' ?>
                </span>
                <span class="info-pill"><i class="fas fa-tag"></i> <?= h($j['category']) ?></span>
                <?php if ($j['salary']): ?><span class="info-pill info-pill-green"><i class="fas fa-money-bill"></i> <?= h($j['salary']) ?></span><?php endif; ?>
            </div>

            <div class="job-tile-footer">
                <span class="job-tile-date"><i class="fas fa-calendar"></i> <?= date('d M', strtotime($j['created_at'])) ?></span>
                <?php if ($applied): ?>
                    <span class="applied-tag"><i class="fas fa-check-circle"></i> <?= t('seeker.applied') ?></span>
                <?php else: ?>
                    <a href="job.php?id=<?= (int)$j['id'] ?>" class="btn-blue btn-sm"><?= t('seeker.view_details') ?> <i class="fas fa-arrow-right"></i></a>
                <?php endif; ?>
            </div>
        </article>
    <?php endwhile; ?>
    </div>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination-wrap">
        
        <div class="pagination-info">
            Showing <strong><?= $offset + 1 ?>–<?= min($offset + $jobs_per_page, $total_jobs_count) ?></strong> 
            of <strong><?= $total_jobs_count ?></strong> jobs
        </div>

        <div class="pagination-numbers">
            <?php
            $query_string = $_GET;
            unset($query_string['page']);
            $base_qs = http_build_query($query_string);
            $qs_prefix = $base_qs ? $base_qs . '&' : '';

            if ($current_page > 1): ?>
                <a href="?<?= $qs_prefix ?>page=<?= $current_page - 1 ?>" class="page-btn page-prev" title="Previous">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="page-btn page-prev disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif;

            $show_pages = [];
            $max_visible = 7;
            if ($total_pages <= $max_visible) {
                for ($i = 1; $i <= $total_pages; $i++) $show_pages[] = $i;
            } else {
                $show_pages[] = 1;
                $start = max(2, $current_page - 1);
                $end = min($total_pages - 1, $current_page + 1);
                if ($start > 2) $show_pages[] = '...';
                for ($i = $start; $i <= $end; $i++) $show_pages[] = $i;
                if ($end < $total_pages - 1) $show_pages[] = '...';
                $show_pages[] = $total_pages;
            }

            foreach ($show_pages as $p):
                if ($p === '...'): ?>
                    <span class="page-ellipsis">…</span>
                <?php elseif ($p == $current_page): ?>
                    <span class="page-btn page-active"><?= $p ?></span>
                <?php else: ?>
                    <a href="?<?= $qs_prefix ?>page=<?= $p ?>" class="page-btn"><?= $p ?></a>
                <?php endif;
            endforeach;

            if ($current_page < $total_pages): ?>
                <a href="?<?= $qs_prefix ?>page=<?= $current_page + 1 ?>" class="page-btn page-next" title="Next">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="page-btn page-next disabled"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>

        <div class="pagination-jump">
            <label for="jumpPage">Go to:</label>
            <input type="number" id="jumpPage" min="1" max="<?= $total_pages ?>" value="<?= $current_page ?>" onkeypress="if(event.key==='Enter')jumpToPage()">
            <button type="button" onclick="jumpToPage()" class="btn-sm btn-blue">
                <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- ============ SCRIPTS ============ -->
<script>
// Save Button Toggle
function toggleSave(btn, jobId) {
    btn.disabled = true;
    btn.style.opacity = '0.5';

    fetch('ajax-save-job.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=toggle&job_id=' + jobId
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.style.opacity = '1';
        
        if (data.status === 'saved') {
            btn.classList.add('saved');
            btn.title = 'Remove from saved';
        } else if (data.status === 'removed') {
            btn.classList.remove('saved');
            btn.title = 'Save this job';
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.style.opacity = '1';
        alert('Something went wrong. Please try again.');
    });
}

// Location Dropdowns
function loadDistricts() {
    var state = document.getElementById('filterState').value;
    var distSelect = document.getElementById('filterDistrict');
    var citySelect = document.getElementById('filterCity');
    distSelect.innerHTML = '<option value="">Loading...</option>';
    citySelect.innerHTML = '<option value="">All Cities</option>';
    if (state === '') {
        distSelect.innerHTML = '<option value="">All Districts</option>';
        return;
    }
    fetch('ajax-get-locations.php?action=get_districts&state=' + encodeURIComponent(state))
        .then(res => res.json())
        .then(data => {
            distSelect.innerHTML = '<option value="">All Districts</option>';
            data.forEach(function(d) {
                distSelect.innerHTML += '<option value="' + d + '">' + d + '</option>';
            });
        });
}

function loadCities() {
    var state = document.getElementById('filterState').value;
    var district = document.getElementById('filterDistrict').value;
    var citySelect = document.getElementById('filterCity');
    citySelect.innerHTML = '<option value="">Loading...</option>';
    if (state === '' || district === '') {
        citySelect.innerHTML = '<option value="">All Cities</option>';
        return;
    }
    fetch('ajax-get-locations.php?action=get_cities&state=' + encodeURIComponent(state) + '&district=' + encodeURIComponent(district))
        .then(res => res.json())
        .then(data => {
            citySelect.innerHTML = '<option value="">All Cities</option>';
            data.forEach(function(c) {
                citySelect.innerHTML += '<option value="' + c + '">' + c + '</option>';
            });
        });
}

// Jump to Page
function jumpToPage() {
    const input = document.getElementById('jumpPage');
    const totalPages = <?= $total_pages ?>;
    let page = parseInt(input.value);
    if (isNaN(page) || page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    const params = new URLSearchParams(window.location.search);
    params.set('page', page);
    window.location.href = '?' + params.toString();
}
</script>

<?php include 'inc/foot.php'; ?>