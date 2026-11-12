<?php
require_once 'inc/auth.php';
must_role('seeker');
$title = t('nav.home') . " — Seeker";

$uid = $_SESSION['uid'];

// ============ PAGINATION SETUP ============
$jobs_per_page = 12;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $jobs_per_page;

// ============ CHECK ADS SETTINGS ============
$ads_res = $db->query("SELECT setting_value FROM site_settings WHERE setting_key='seeker_show_ads'");
$show_ads = ($ads_res && $ads_res->fetch_assoc()['setting_value'] == '1');

$active_ads = null;
if ($show_ads) {
    $active_ads = $db->query("SELECT * FROM ads WHERE is_active = 1 AND (target_role = 'both' OR target_role = 'seeker') ORDER BY RAND() LIMIT 1");
}

// ============ STATS ============
$new_jobs_q = $db->prepare("
    SELECT COUNT(*) c FROM jobs j
    WHERE j.status='active'
      AND j.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      AND j.id NOT IN (SELECT job_id FROM applications WHERE seeker_id=?)
");
$new_jobs_q->bind_param('i', $uid);
$new_jobs_q->execute();
$new_jobs_count = $new_jobs_q->get_result()->fetch_assoc()['c'];

$total_jobs = $db->query("SELECT COUNT(*) c FROM jobs WHERE status='active'")->fetch_assoc()['c'];

// Pagination
$total_jobs_count = $total_jobs;
$total_pages = max(1, (int)ceil($total_jobs_count / $jobs_per_page));

if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $jobs_per_page;
}

$applied_q = $db->prepare("SELECT COUNT(*) c FROM applications WHERE seeker_id=?");
$applied_q->bind_param('i', $uid);
$applied_q->execute();
$applied_count = $applied_q->get_result()->fetch_assoc()['c'];

$short_q = $db->prepare("SELECT COUNT(*) c FROM applications WHERE seeker_id=? AND status='shortlisted'");
$short_q->bind_param('i', $uid);
$short_q->execute();
$shortlisted_count = $short_q->get_result()->fetch_assoc()['c'];

$hired_q = $db->prepare("SELECT COUNT(*) c FROM applications WHERE seeker_id=? AND status='hired'");
$hired_q->bind_param('i', $uid);
$hired_q->execute();
$hired_count = $hired_q->get_result()->fetch_assoc()['c'];

// ============ LATEST JOBS (PAGINATED) ============
$latest = $db->prepare("
    SELECT j.*, u.company_name,
      (SELECT id FROM applications WHERE job_id=j.id AND seeker_id=?) AS my_app_id,
      (SELECT status FROM applications WHERE job_id=j.id AND seeker_id=? LIMIT 1) AS my_app_status
    FROM jobs j JOIN users u ON j.employer_id = u.id
    WHERE j.status='active'
    ORDER BY j.created_at DESC
    LIMIT ? OFFSET ?
");
$latest->bind_param('iiii', $uid, $uid, $jobs_per_page, $offset);
$latest->execute();
$latest_jobs = $latest->get_result();

// ============ RECENT APPLICATIONS ============
$recent_apps_q = $db->prepare("
    SELECT a.*, j.title, u.company_name
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON j.employer_id = u.id
    WHERE a.seeker_id = ?
    ORDER BY a.applied_at DESC
    LIMIT 5
");
$recent_apps_q->bind_param('i', $uid);
$recent_apps_q->execute();
$recent_apps = $recent_apps_q->get_result();

function cat_icon($cat) {
    $map = ['Sales' => 'fa-shopping-cart','Driver' => 'fa-motorcycle','Teacher' => 'fa-chalkboard-teacher','Helper' => 'fa-hands-helping','Technician' => 'fa-tools','Office' => 'fa-briefcase','Security' => 'fa-shield-alt','Cooking' => 'fa-utensils'];
    return $map[$cat] ?? 'fa-tag';
}

include 'inc/head.php';
?>

<!-- ============ INLINE CSS (Page-specific — conflicts avoid karne ke liye) ============ -->
<style>
/* Pagination — Force override */
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

.pagination-wrap .page-prev,
.pagination-wrap .page-next {
    font-size: 0.8rem !important;
}

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
.pagination-jump label {
    margin: 0 !important;
    font-weight: 500 !important;
    font-size: 0.85rem !important;
}
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
    transition: border-color 0.2s, box-shadow 0.2s !important;
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
</style>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-ok"><i class="fas fa-check-circle"></i> <?= h($_GET['msg']) ?></div>
<?php endif; ?>

<!-- WELCOME BANNER -->
<section class="seeker-hero">
    <div class="seeker-hero-inner">
        <div>
            <h1><?= t('seeker.welcome') ?>, <span class="grad-text"><?= h($_SESSION['name']) ?></span>!</h1>
            <p>
                <?php if ($new_jobs_count > 0): ?>
                    <i class="fas fa-bolt"></i>
                    <strong><?= $new_jobs_count ?> <?= t('seeker.new_jobs') ?></strong>
                <?php else: ?>
                    <?= t('seeker.check_back') ?>
                <?php endif; ?>
            </p>
        </div>
        <a href="jobs.php" class="btn-blue btn-lg">
            <i class="fas fa-search"></i> <?= t('seeker.browse_all') ?>
        </a>
    </div>
</section>

<!-- MAIN LAYOUT -->
<div style="display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap;">
    
    <!-- LEFT COLUMN -->
    <div style="flex: 1; min-width: 300px;">
        
        <!-- STATS -->
        <div class="stats-row">
            <div class="stat-box" style="border-top-color:var(--green);"><div class="num" style="color:var(--green);"><?= (int)$new_jobs_count ?></div><div class="lbl"><?= t('seeker.new_jobs') ?></div></div>
            <div class="stat-box"><div class="num"><?= (int)$total_jobs ?></div><div class="lbl"><?= t('seeker.total_jobs') ?></div></div>
            <div class="stat-box" style="border-top-color:#f59e0b;"><div class="num" style="color:#f59e0b;"><?= (int)$applied_count ?></div><div class="lbl"><?= t('seeker.my_apps') ?></div></div>
            <div class="stat-box" style="border-top-color:var(--blue);"><div class="num" style="color:var(--blue);"><?= (int)$shortlisted_count ?></div><div class="lbl"><?= t('seeker.shortlisted') ?></div></div>
            <div class="stat-box" style="border-top-color:var(--green-dark);"><div class="num" style="color:var(--green-dark);"><?= (int)$hired_count ?></div><div class="lbl"><?= t('seeker.hired') ?></div></div>
        </div>

        <!-- LATEST JOBS -->
        <div class="latest-jobs-section">
            <div class="latest-jobs-head">
                <div><h2><i class="fas fa-fire"></i> <?= t('seeker.latest_jobs') ?></h2></div>
                <a href="jobs.php" class="btn-gray btn-sm"><?= t('seeker.view_all') ?> →</a>
            </div>

            <?php if ($latest_jobs->num_rows === 0): ?>
                <div class="card empty-state"><i class="fas fa-briefcase"></i><h3><?= t('seeker.no_jobs') ?></h3><p><?= t('seeker.check_back') ?></p></div>
            <?php else: ?>
                <div class="jobs-grid">
                <?php while ($j = $latest_jobs->fetch_assoc()):
                    $is_new = strtotime($j['created_at']) >= strtotime('-7 days');
                    $applied = !empty($j['my_app_id']);
                ?>
                    <article class="job-tile">
                        <div class="job-tile-top">
                            <div class="job-cat-icon"><i class="fas <?= cat_icon($j['category']) ?>"></i></div>
                            <?php if ($is_new): ?><span class="badge-new"><i class="fas fa-bolt"></i> <?= t('seeker.new_badge') ?></span><?php endif; ?>
                        </div>
                        <h3 class="job-tile-title"><?= h($j['title']) ?></h3>
                        <p class="job-tile-company"><i class="fas fa-building"></i> <?= h($j['company_name']) ?></p>
                        <p class="job-tile-desc"><?= h(mb_substr($j['description'], 0, 110)) ?><?= mb_strlen($j['description']) > 110 ? '…' : '' ?></p>
                        <div class="job-tile-pills">
                            <span class="info-pill"><i class="fas fa-map-marker-alt"></i> <?= h($j['location']) ?></span>
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
                        if ($current_page > 1): ?>
                            <a href="?page=<?= $current_page - 1 ?>" class="page-btn page-prev" title="Previous">
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
                                <a href="?page=<?= $p ?>" class="page-btn"><?= $p ?></a>
                            <?php endif;
                        endforeach;

                        if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>" class="page-btn page-next" title="Next">
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
        </div>

        <!-- RECENT APPLICATIONS -->
        <?php if ($recent_apps->num_rows > 0): ?>
        <div class="card" style="margin-top:24px;">
            <div class="flex-between mb">
                <h3 style="margin:0;"><i class="fas fa-file-alt"></i> <?= t('seeker.recent_apps') ?></h3>
                <a href="seeker.php" class="btn-sm btn-gray"><?= t('seeker.view_all') ?> →</a>
            </div>
            <div style="overflow-x:auto;">
                <table>
                    <thead><tr><th><?= t('seeker.job') ?></th><th><?= t('seeker.company') ?></th><th><?= t('seeker.applied_date') ?></th><th><?= t('seeker.status') ?></th></tr></thead>
                    <tbody>
                    <?php while ($a = $recent_apps->fetch_assoc()): ?>
                        <?php
                          $cls = 'pending';
                          if ($a['status']==='shortlisted') $cls='shortlisted';
                          elseif ($a['status']==='hired')   $cls='hired';
                          elseif ($a['status']==='rejected')$cls='rejected';
                        ?>
                        <tr>
                            <td><strong><?= h($a['title']) ?></strong></td>
                            <td><?= h($a['company_name']) ?></td>
                            <td><?= date('d M', strtotime($a['applied_at'])) ?></td>
                            <td><span class="pill pill-<?= $cls ?>"><?= h($a['status']) ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT COLUMN (Sticky Ads) -->
    <?php if ($show_ads && $active_ads && $active_ads->num_rows > 0): ?>
        <?php $ad = $active_ads->fetch_assoc(); ?>
        <div style="width: 300px; flex-shrink: 0; position: sticky; top: 90px;">
            <a href="<?= h($ad['ad_link']) ?>" target="_blank" style="display:block; text-decoration:none; background:#fff; border-radius:12px; overflow:hidden; box-shadow: 0 6px 20px rgba(0,0,0,0.12); border: 1px solid #e5e7eb; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="position:relative;">
                    <img src="<?= h($ad['ad_image']) ?>" alt="<?= h($ad['ad_title']) ?>" style="width: 100%; height: 200px; object-fit: cover; display:block;">
                    <span style="position:absolute; top:10px; right:10px; background:rgba(0,0,0,0.7); color:#fff; font-size:0.7rem; padding:3px 8px; border-radius:6px; font-weight:bold; letter-spacing:0.5px;">SPONSORED</span>
                </div>
                <div style="padding: 18px;">
                    <h3 style="margin:0 0 6px; font-size:1.1rem; color:#111827; font-weight:700; line-height:1.3;"><?= h($ad['ad_title']) ?></h3>
                    <p style="margin:0 0 15px; font-size:0.88rem; color:#6b7280; line-height:1.5;"><?= h($ad['ad_content']) ?></p>
                    <div style="display:inline-block; background:#eff6ff; color:var(--blue); font-weight:700; font-size:0.85rem; padding:8px 16px; border-radius:6px; width:100%; text-align:center;">
                        Learn More <i class="fas fa-arrow-right" style="margin-left:5px;"></i>
                    </div>
                </div>
            </a>
        </div>
    <?php endif; ?>

</div>

<script>
function jumpToPage() {
    const input = document.getElementById('jumpPage');
    const totalPages = <?= $total_pages ?>;
    let page = parseInt(input.value);
    if (isNaN(page) || page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    window.location.href = '?page=' + page;
}
</script>

<?php include 'inc/foot.php'; ?>