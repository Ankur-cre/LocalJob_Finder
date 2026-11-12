<?php
require_once 'inc/auth.php';

if (!logged_in()) {
    header("Location: login.php?err=" . urlencode("Please login to view job details."));
    exit;
}

$title = "Job Details";
$uid = $_SESSION['uid'];

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: jobs.php");
    exit;
}

// ============ FETCH JOB + COMPANY INFO ============
$stmt = $db->prepare("
    SELECT j.*,
           u.id AS emp_id,
           u.company_name,
           u.name AS employer_name,
           u.email AS employer_email,
           u.phone AS employer_phone,
           u.address AS employer_address,
           u.created_at AS employer_since,
           u.is_verified,
           (SELECT COUNT(*) FROM jobs WHERE employer_id=u.id AND status='active') AS company_total_jobs,
           (SELECT COUNT(*) FROM applications WHERE job_id=j.id) AS total_applicants
    FROM jobs j
    JOIN users u ON j.employer_id = u.id
    WHERE j.id = ? AND j.status = 'active'
");
$stmt->bind_param('i', $id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    include 'inc/head.php';
    ?>
    <div class="card text-center" style="padding:50px 20px;">
        <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:#f59e0b;margin-bottom:15px;"></i>
        <h2 style="color:#374151;margin-bottom:8px;">Job not found</h2>
        <p style="color:#6b7280;margin-bottom:20px;">Yeh job delete ho gaya hai, closed hai, ya abhi active nahi hai.</p>
        <a href="jobs.php" class="btn-blue"><i class="fas fa-arrow-left"></i> Back to Jobs</a>
    </div>
    <?php
    include 'inc/foot.php';
    exit;
}

// ============ FETCH COMPANY GALLERY IMAGES ============
$gq = $db->prepare("SELECT gallery_images FROM users WHERE id=?");
$gq->bind_param('i', $job['emp_id']);
$gq->execute();
$g_row = $gq->get_result()->fetch_assoc();
$emp_imgs = $g_row['gallery_images'] ? explode(',', $g_row['gallery_images']) : [];

// ============ CHECK ALREADY APPLIED / SAVED ============
$already_applied = false;
$is_saved = false;
$my_status = null;
$has_reported = false;
if (role() === 'seeker') {
    $chk = $db->prepare("SELECT id, status FROM applications WHERE job_id=? AND seeker_id=? LIMIT 1");
    $chk->bind_param('ii', $id, $uid);
    $chk->execute();
    $res = $chk->get_result()->fetch_assoc();
    if ($res) { $already_applied = true; $my_status = $res['status']; }

    $chk_save = $db->prepare("SELECT id FROM saved_jobs WHERE job_id=? AND seeker_id=?");
    $chk_save->bind_param('ii', $id, $uid);
    $chk_save->execute();
    if ($chk_save->get_result()->num_rows > 0) $is_saved = true;

    $chk_rep = $db->prepare("SELECT id FROM job_reports WHERE job_id=? AND reporter_id=? LIMIT 1");
    $chk_rep->bind_param('ii', $id, $uid);
    $chk_rep->execute();
    if ($chk_rep->get_result()->num_rows > 0) $has_reported = true;
}

// ============ FETCH SIMILAR JOBS (SAFE) ============
$similar_jobs = [];

if (!function_exists('column_exists')) {
    function column_exists($db, $table, $column) {
        $table  = $db->real_escape_string($table);
        $column = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && $res->num_rows > 0;
    }
}

try {
    $where  = ["j.status = 'active'", "j.id != ?"];
    $params = [$id];
    $types  = 'i';

    $has_cat  = column_exists($db, 'jobs', 'category');
    $has_city = column_exists($db, 'jobs', 'city');

    if ($has_cat && $has_city && !empty($job['category']) && !empty($job['city'])) {
        $where[]  = "(j.category = ? OR j.city = ?)";
        $params[] = $job['category'];
        $params[] = $job['city'];
        $types   .= 'ss';
    } elseif ($has_cat && !empty($job['category'])) {
        $where[]  = "j.category = ?";
        $params[] = $job['category'];
        $types   .= 's';
    } elseif ($has_city && !empty($job['city'])) {
        $where[]  = "j.city = ?";
        $params[] = $job['city'];
        $types   .= 's';
    }

    $order = "j.created_at DESC";
    if ($has_cat && !empty($job['category'])) {
        $order = "(j.category = ?) DESC, j.created_at DESC";
        $params[] = $job['category'];
        $types   .= 's';
    }

    $select = ["j.id", "j.created_at"];
    foreach (['title','location','salary','job_type','category','city'] as $col) {
        if (column_exists($db, 'jobs', $col)) $select[] = "j.$col";
    }

    $company_col = 'NULL AS company_name';
    if (column_exists($db, 'users', 'company_name')) {
        $company_col = 'u.company_name';
    } elseif (column_exists($db, 'jobs', 'company_name')) {
        $company_col = 'j.company_name';
    }
    $select[] = $company_col;

    $sql = "SELECT " . implode(', ', $select) . "
            FROM jobs j
            LEFT JOIN users u ON j.employer_id = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY $order
            LIMIT 4";

    $sq = $db->prepare($sql);
    if ($sq) {
        $sq->bind_param($types, ...$params);
        $sq->execute();
        $similar_jobs = $sq->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    $similar_jobs = [];
    error_log("Similar jobs query failed: " . $e->getMessage());
}

// ============ RESUME COMPRESSION HELPER ============
if (!function_exists('compress_resume')) {
    function compress_resume($source, $dest, $ext) {
        if ($ext === 'pdf') {
            if (class_exists('Imagick')) {
                try {
                    $img = new Imagick();
                    $img->setResolution(100, 100);
                    $img->readImage($source);
                    $img->setImageCompression(Imagick::COMPRESSION_JPEG);
                    $img->setImageCompressionQuality(60);
                    $img->stripImage();
                    $img->writeImages($dest, true);
                    $img->clear();
                    if (filesize($dest) > 0 && filesize($dest) < filesize($source)) return true;
                } catch (Exception $e) {}
            }
            return move_uploaded_file($source, $dest);
        }
        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $info = getimagesize($source);
            if (!$info) return false;
            $mime = $info['mime'];
            if ($mime === 'image/jpeg')      $img = imagecreatefromjpeg($source);
            elseif ($mime === 'image/png')   $img = imagecreatefrompng($source);
            else return move_uploaded_file($source, $dest);
            $w = imagesx($img); $h = imagesy($img);
            if ($w > 1200) {
                $new_w = 1200; $new_h = (int)($h * (1200 / $w));
                $resized = imagecreatetruecolor($new_w, $new_h);
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $new_w, $new_h, $w, $h);
                imagedestroy($img); $img = $resized;
            }
            if ($ext === 'png') imagepng($img, $dest, 8);
            else imagejpeg($img, $dest, 75);
            imagedestroy($img);
            if (filesize($dest) < filesize($source)) return true;
        }
        return move_uploaded_file($source, $dest);
    }
}

// ============ HANDLE SAVE / UNSAVE JOB (TOGGLE) ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_job'])) {
    if (role() === 'seeker') {
        if ($is_saved) {
            $del = $db->prepare("DELETE FROM saved_jobs WHERE seeker_id=? AND job_id=?");
            $del->bind_param('ii', $uid, $id);
            $del->execute();
            header("Location: job.php?id=$id&msg=" . urlencode("Job removed from saved list."));
            exit;
        } else {
            $ins = $db->prepare("INSERT INTO saved_jobs (seeker_id, job_id) VALUES (?, ?)");
            $ins->bind_param('ii', $uid, $id);
            $ins->execute();
            header("Location: job.php?id=$id&msg=" . urlencode("Job saved successfully!"));
            exit;
        }
    }
}

// ============ HANDLE REPORT JOB ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_job'])) {
    if (role() === 'seeker' && !$has_reported) {
        $reason = trim($_POST['reason'] ?? '');
        if ($reason !== '') {
            $ins = $db->prepare("INSERT INTO job_reports (reporter_id, job_id, employer_id, report_type, reason) VALUES (?, ?, ?, 'job', ?)");
            $ins->bind_param('iiis', $uid, $id, $job['emp_id'], $reason);
            $ins->execute();
            header("Location: job.php?id=$id&msg=" . urlencode("Report submitted. Admin will review it."));
            exit;
        } else {
            header("Location: job.php?id=$id&err=" . urlencode("Please provide a reason for reporting."));
            exit;
        }
    }
}

// ============ HANDLE APPLY (WITH SNAPSHOT LOCATION) ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {

    if (role() !== 'seeker') {
        header("Location: job.php?id=$id&err=" . urlencode("Only job seekers can apply."));
        exit;
    }
    if ($already_applied) {
        header("Location: job.php?id=$id&err=" . urlencode("You already applied to this job."));
        exit;
    }

    $loc_q = $db->query("SELECT city, district, state FROM users WHERE id=$uid")->fetch_assoc();
    $snap_city = $loc_q['city'] ?? null;
    $snap_dist = $loc_q['district'] ?? null;
    $snap_state = $loc_q['state'] ?? null;

    $resume = null;
    if (!empty($_FILES['resume']['name']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $maxsize = 5 * 1024 * 1024;

        if (in_array($ext, $allowed) && $_FILES['resume']['size'] <= $maxsize) {
            $dir = __DIR__ . '/storage/resumes';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $filename = 'resume_' . $uid . '_' . time() . '.' . $ext;
            $dest = $dir . '/' . $filename;
            if (compress_resume($_FILES['resume']['tmp_name'], $dest, $ext)) {
                $resume = $filename;
            } elseif (move_uploaded_file($_FILES['resume']['tmp_name'], $dest)) {
                $resume = $filename;
            }
        }
    }

    $ins = $db->prepare("
        INSERT INTO applications (job_id, seeker_id, resume_file, status, snapshot_city, snapshot_district, snapshot_state) 
        VALUES (?, ?, ?, 'pending', ?, ?, ?)
    ");
    $ins->bind_param('iissss', $id, $uid, $resume, $snap_city, $snap_dist, $snap_state);

    if ($ins->execute()) {
        header("Location: job.php?id=$id&msg=" . urlencode("Application submitted successfully!"));
    } else {
        header("Location: job.php?id=$id&err=" . urlencode("Failed to submit. Please try again."));
    }
    exit;
}

if (!function_exists('cat_icon')) {
    function cat_icon($cat) {
        $map = ['Sales' => 'fa-shopping-cart','Driver' => 'fa-motorcycle','Teacher' => 'fa-chalkboard-teacher','Helper' => 'fa-hands-helping','Technician' => 'fa-tools','Office' => 'fa-briefcase','Security' => 'fa-shield-alt','Cooking' => 'fa-utensils'];
        return $map[$cat] ?? 'fa-tag';
    }
}

$is_new = strtotime($job['created_at']) >= strtotime('-7 days');

$expiry_date = !empty($job['expires_at']) ? $job['expires_at'] : date('Y-m-d', strtotime($job['created_at'] . ' +30 days'));
$days_left = (int)floor((strtotime($expiry_date) - time()) / 86400);
$is_expiring_soon = $days_left >= 0 && $days_left <= 5;

$share_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$share_text = urlencode(($job['title'] ?? '') . ' at ' . ($job['company_name'] ?? 'Company') . ' - ' . ($job['location'] ?? ''));

include 'inc/head.php';
?>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-ok"><i class="fas fa-check-circle"></i> <?= h($_GET['msg']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?>
    <div class="alert alert-err"><i class="fas fa-exclamation-circle"></i> <?= h($_GET['err']) ?></div>
<?php endif; ?>

<!-- ============ SUSPICIOUS JOB WARNING ============ -->
<?php if (!empty($job['is_flagged'])): ?>
    <div style="background:#fef2f2; border:2px solid #ef4444; border-radius:12px; padding:20px; margin-bottom:20px;">
        <div style="display:flex; gap:15px; align-items:flex-start;">
            <i class="fas fa-exclamation-triangle" style="font-size:2rem; color:#dc2626;"></i>
            <div>
                <h3 style="color:#991b1b; margin-bottom:5px; font-size:1.1rem;">⚠️ This Job Has Been Flagged</h3>
                <p style="color:#7f1d1d; font-size:0.9rem; margin:0;">
                    This job posting was automatically flagged for review. Reason: <strong><?= h($job['flag_reason'] ?? '') ?></strong>
                </p>
                <p style="color:#7f1d1d; font-size:0.85rem; margin-top:8px;">
                    <strong>Please be careful.</strong> Never pay money to get a job. Report suspicious activity immediately.
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ============ EXPIRING SOON WARNING ============ -->
<?php if ($is_expiring_soon && !$already_applied): ?>
    <div style="background:#fffbeb; border:2px solid #f59e0b; border-radius:12px; padding:15px 20px; margin-bottom:20px;">
        <div style="display:flex; gap:12px; align-items:center;">
            <i class="fas fa-hourglass-half" style="font-size:1.5rem; color:#d97706;"></i>
            <div>
                <strong style="color:#92400e;">⏰ Hurry! This job expires in <?= $days_left ?> day<?= $days_left !== 1 ? 's' : '' ?>.</strong>
                <p style="color:#92400e; font-size:0.85rem; margin:3px 0 0;">Apply soon before the listing closes.</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
    <a href="jobs.php" class="btn-sm btn-gray"><i class="fas fa-arrow-left"></i> Back to Jobs</a>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <?php if (role() === 'seeker'): ?>
        <form method="POST" style="margin:0;">
            <button type="submit" name="save_job" value="1" class="btn-sm <?= $is_saved ? 'btn-green' : 'btn-outline-blue' ?>">
                <i class="fas fa-bookmark"></i> <?= $is_saved ? 'Saved ✓' : 'Save Job' ?>
            </button>
        </form>
        <?php endif; ?>

        <a href="https://wa.me/?text=<?= $share_text ?>%20<?= urlencode($share_url) ?>" target="_blank" rel="noopener" class="btn-sm btn-gray" style="background:#25d366; color:#fff; border:none;" title="Share on WhatsApp">
            <i class="fab fa-whatsapp"></i> Share
        </a>
        <button type="button" onclick="copyJobLink()" class="btn-sm btn-gray" title="Copy Link">
            <i class="fas fa-link"></i> <span id="copyText">Copy Link</span>
        </button>

        <?php if (role() === 'seeker' && !$has_reported): ?>
        <button type="button" onclick="document.getElementById('reportModal').style.display='flex'" class="btn-sm" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca;">
            <i class="fas fa-flag"></i> Report
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ============ JOB HEADER ============ -->
<div class="job-detail-header">
    <div class="job-detail-header-main">
        <div class="job-detail-icon">
            <i class="fas <?= cat_icon($job['category'] ?? '') ?>"></i>
        </div>
        <div>
            <?php if ($is_new): ?>
                <span class="badge-new" style="margin-bottom:8px;"><i class="fas fa-bolt"></i> NEW</span>
            <?php endif; ?>
            <h1 class="job-detail-title"><?= h($job['title'] ?? 'Untitled Job') ?></h1>
            <p class="job-detail-company"><i class="fas fa-building"></i> <?= h($job['company_name'] ?? 'Company') ?></p>
        </div>
    </div>

    <div class="job-detail-pills">
        <?php if (!empty($job['location'])): ?>
            <span class="info-pill info-pill-lg"><i class="fas fa-map-marker-alt"></i> <?= h($job['location']) ?></span>
        <?php endif; ?>
        <?php if (!empty($job['job_type'])): ?>
            <span class="info-pill info-pill-lg"><i class="fas fa-clock"></i> <?= h($job['job_type']) ?></span>
        <?php endif; ?>
        <?php if (!empty($job['salary'])): ?>
            <span class="info-pill info-pill-lg info-pill-green"><i class="fas fa-money-bill"></i> <?= h($job['salary']) ?></span>
        <?php endif; ?>
        <?php if (!empty($job['category'])): ?>
            <span class="info-pill info-pill-lg"><i class="fas fa-tag"></i> <?= h($job['category']) ?></span>
        <?php endif; ?>
        <span class="info-pill info-pill-lg"><i class="fas fa-users"></i> <?= (int)($job['total_applicants'] ?? 0) ?> applicant<?= (int)($job['total_applicants'] ?? 0) !== 1 ? 's' : '' ?></span>
    </div>
</div>

<!-- ============ WALK-IN INTERVIEW BANNER ============ -->
<?php if (!empty($job['is_walkin'])): ?>
    <div style="background:linear-gradient(135deg, #f59e0b, #d97706); color:#fff; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 12px rgba(245,158,11,0.3);">
        <div style="display:flex; gap:15px; align-items:center;">
            <i class="fas fa-calendar-alt" style="font-size:2.5rem;"></i>
            <div>
                <h3 style="margin-bottom:5px; font-size:1.3rem;">🚶 Walk-in Interview</h3>
                <p style="margin:0; font-size:1rem;">
                    <strong>Date:</strong> <?= !empty($job['walkin_date']) ? date('d M Y', strtotime($job['walkin_date'])) : 'TBA' ?>
                    <?php if (!empty($job['walkin_time'])): ?> | <strong>Time:</strong> <?= h($job['walkin_time']) ?><?php endif; ?>
                </p>
                <p style="margin:5px 0 0; font-size:0.9rem; opacity:0.9;">Walk-in matlab aap direct interview ke liye aa sakte hain, koi appointment ki zaroorat nahi.</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ============ TAGS BAR ============ -->
<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px;">
    <?php if (!empty($job['fresher_allowed'])): ?>
        <span style="background:#dcfce7; color:#166534; padding:6px 14px; border-radius:20px; font-weight:600; font-size:0.85rem;">
            <i class="fas fa-user-graduate"></i> Fresher Allowed
        </span>
    <?php endif; ?>
    <?php if (!empty($job['is_verified'])): ?>
        <span style="background:#dbeafe; color:#1e40af; padding:6px 14px; border-radius:20px; font-weight:600; font-size:0.85rem;">
            <i class="fas fa-check-circle"></i> Verified Employer
        </span>
    <?php endif; ?>
    <?php if (!empty($job['is_walkin'])): ?>
        <span style="background:#fef3c7; color:#92400e; padding:6px 14px; border-radius:20px; font-weight:600; font-size:0.85rem;">
            <i class="fas fa-calendar-alt"></i> Walk-in Available
        </span>
    <?php endif; ?>
    <?php if ($days_left >= 0 && $days_left <= 5): ?>
        <span style="background:#fee2e2; color:#991b1b; padding:6px 14px; border-radius:20px; font-weight:600; font-size:0.85rem;">
            <i class="fas fa-hourglass-end"></i> Expires in <?= $days_left ?> day<?= $days_left !== 1 ? 's' : '' ?>
        </span>
    <?php endif; ?>
</div>

<!-- ============ MAIN GRID ============ -->
<div class="job-detail-grid">
    <div class="job-detail-main">
        <div class="card">
            <h2 class="detail-section-title"><i class="fas fa-align-left"></i> About the Role</h2>
            <div class="detail-description"><?= nl2br(h($job['description'] ?? '')) ?></div>
        </div>

        <div class="card">
            <h2 class="detail-section-title"><i class="fas fa-info-circle"></i> Job Overview</h2>
            <div class="detail-info-grid">
                <div class="detail-info-item"><span class="detail-label">Job Type</span><span class="detail-value"><?= h($job['job_type'] ?? 'N/A') ?></span></div>
                <div class="detail-info-item"><span class="detail-label">Category</span><span class="detail-value"><?= h($job['category'] ?? 'N/A') ?></span></div>
                <div class="detail-info-item"><span class="detail-label">Location</span><span class="detail-value"><?= h($job['city'] ?? '') ?>, <?= h($job['district'] ?? '') ?></span></div>
                <div class="detail-info-item"><span class="detail-label">Salary</span><span class="detail-value"><?= h(!empty($job['salary']) ? $job['salary'] : 'Not specified') ?></span></div>
                <div class="detail-info-item"><span class="detail-label">Posted On</span><span class="detail-value"><?= date('d M Y', strtotime($job['created_at'])) ?></span></div>
                <div class="detail-info-item"><span class="detail-label">Contact Phone</span><span class="detail-value">
                    <?php if (!empty($job['contact_phone'])): ?>
                        <a href="tel:<?= h($job['contact_phone']) ?>" style="color:var(--blue);text-decoration:none;"><?= h($job['contact_phone']) ?></a>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </span></div>
            </div>
        </div>

        <!-- ============ SIMILAR JOBS ============ -->
        <?php if (!empty($similar_jobs)): ?>
        <div class="card">
            <h2 class="detail-section-title"><i class="fas fa-thumbs-up"></i> Similar Jobs You May Like</h2>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <?php foreach ($similar_jobs as $sj): ?>
                    <a href="job.php?id=<?= (int)$sj['id'] ?>" style="display:block; padding:14px; border:1px solid #e5e7eb; border-radius:10px; text-decoration:none; color:inherit; transition:all 0.15s;" onmouseover="this.style.borderColor='#2563eb';this.style.background='#f8fafc';" onmouseout="this.style.borderColor='#e5e7eb';this.style.background='transparent';">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap;">
                            <div>
                                <h4 style="margin:0 0 4px; font-size:1rem; color:#111827;"><?= h($sj['title'] ?? 'Untitled Job') ?></h4>
                                <p style="margin:0; font-size:0.85rem; color:#6b7280;">
                                    <i class="fas fa-building"></i> <?= h($sj['company_name'] ?? 'Company') ?>
                                    <?php if (!empty($sj['location'])): ?>
                                        &nbsp;·&nbsp; <i class="fas fa-map-marker-alt"></i> <?= h($sj['location']) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div style="text-align:right;">
                                <?php if (!empty($sj['salary'])): ?>
                                    <div style="font-size:0.85rem; color:#059669; font-weight:600;"><?= h($sj['salary']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($sj['job_type'])): ?>
                                    <div style="font-size:0.75rem; color:#9ca3af; margin-top:3px;">
                                        <i class="fas fa-clock"></i> <?= h($sj['job_type']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="job-detail-sidebar">
        <div class="card company-card">
            <h2 class="detail-section-title"><i class="fas fa-building"></i> About the Company</h2>

            <?php if (!empty($emp_imgs)): ?>
                <div style="margin-bottom: 20px; margin-top: 5px;">
                    <h4 style="font-size: 0.85rem; color: #6b7280; margin-bottom: 10px; text-transform:uppercase;"><i class="fas fa-images"></i> Company Photos</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <?php foreach (array_slice($emp_imgs, 0, 4) as $img): ?>
                            <div style="border-radius: 8px; overflow: hidden; cursor: pointer;" onclick="openLightbox('storage/gallery/<?= h($img) ?>')">
                                <img src="storage/gallery/<?= h($img) ?>" style="width: 100%; height: 90px; object-fit: cover; border: 1px solid #e5e7eb;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="company-header">
                <div class="company-logo"><i class="fas fa-store"></i></div>
                <div>
                    <h3 class="company-name">
                        <?= h($job['company_name'] ?? 'Company') ?>
                        <?php if (!empty($job['is_verified'])): ?>
                            <i class="fas fa-check-circle" style="color: var(--blue); font-size: 0.9rem;" title="Verified Employer"></i>
                        <?php endif; ?>
                    </h3>
                    <p class="company-meta"><i class="fas fa-briefcase"></i> <?= (int)($job['company_total_jobs'] ?? 0) ?> active jobs</p>
                </div>
            </div>

            <ul class="company-info-list">
                <?php if (!empty($job['employer_name'])): ?>
                <li><i class="fas fa-user-tie"></i><div><span class="ci-label">Contact</span><span class="ci-value"><?= h($job['employer_name']) ?></span></div></li>
                <?php endif; ?>
                <?php if (!empty($job['employer_address'])): ?>
                <li><i class="fas fa-map-marker-alt"></i><div><span class="ci-label">Address</span><span class="ci-value"><?= h($job['employer_address']) ?></span></div></li>
                <?php endif; ?>
            </ul>

            <a href="jobs.php?employer=<?= (int)$job['emp_id'] ?>" class="btn-sm btn-outline-blue" style="width:100%; justify-content:center; margin-top:12px;">
                <i class="fas fa-briefcase"></i> View All Jobs from <?= h($job['company_name'] ?? 'Company') ?>
            </a>
        </div>
    </div>
</div>

<!-- ============ APPLY SECTION ============ -->
<div class="apply-box">
    <?php if (role() !== 'seeker'): ?>
        <div class="alert alert-info" style="margin:0;"><i class="fas fa-info-circle"></i> Sirf job seekers hi apply kar sakte hain.</div>
    <?php elseif ($already_applied): ?>
        <?php
          $cls = 'pending';
          if ($my_status === 'shortlisted') $cls = 'shortlisted';
          elseif ($my_status === 'hired')   $cls = 'hired';
          elseif ($my_status === 'rejected')$cls = 'rejected';
        ?>
        <div class="apply-success">
            <div class="apply-success-icon"><i class="fas fa-check-circle"></i></div>
            <div>
                <h3>You've already applied!</h3>
                <p>Status: <span class="pill pill-<?= $cls ?>"><?= h($my_status ?? 'pending') ?></span></p>
            </div>
            <a href="seeker.php" class="btn-gray btn-sm">View Applications</a>
        </div>
    <?php else: ?>
        <div class="apply-form-wrap">
            <div class="apply-form-head">
                <h2><i class="fas fa-paper-plane"></i> Apply for this Job</h2>
                <p>Upload your resume (optional) and submit. The employer will contact you directly.</p>
            </div>
            <form method="POST" enctype="multipart/form-data" class="apply-form">
                <div class="form-group">
                    <label><i class="fas fa-file-pdf"></i> Resume <small style="color:#9ca3af;">(Optional — PDF/DOC/JPG, max 5MB)</small></label>
                    <input type="file" name="resume" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                </div>
                <button type="submit" name="apply" value="1" class="btn-blue btn-lg"><i class="fas fa-paper-plane"></i> Submit Application</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- ============ REPORT MODAL ============ -->
<div id="reportModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center; padding:20px;" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:#fff; border-radius:14px; padding:24px; max-width:480px; width:100%; box-shadow:0 20px 50px rgba(0,0,0,0.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0; color:#111827; font-size:1.2rem;"><i class="fas fa-flag" style="color:#dc2626;"></i> Report This Job</h3>
            <button type="button" onclick="document.getElementById('reportModal').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#9ca3af;">&times;</button>
        </div>
        <p style="color:#6b7280; font-size:0.9rem; margin-bottom:16px;">Help us keep the platform safe. Tell us why this job looks suspicious.</p>
        <form method="POST" onsubmit="combineReason()">
            <div class="form-group">
                <label style="font-weight:600; margin-bottom:6px; display:block; font-size:0.9rem;">Reason <span style="color:#dc2626;">*</span></label>
                <select id="reportReasonSelect" required style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:8px; margin-bottom:12px; font-size:0.9rem;">
                    <option value="">-- Select a reason --</option>
                    <option value="Fake / Scam job">Fake / Scam job</option>
                    <option value="Asking for money">Asking for money</option>
                    <option value="Misleading salary/location">Misleading salary/location</option>
                    <option value="Offensive content">Offensive content</option>
                    <option value="Duplicate posting">Duplicate posting</option>
                    <option value="Other">Other</option>
                </select>
                <label style="font-weight:600; margin-bottom:6px; display:block; font-size:0.9rem;">Additional details (optional)</label>
                <textarea id="reportReasonDetail" rows="3" placeholder="Explain in detail..." style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:8px; font-size:0.9rem; resize:vertical;"></textarea>
                <input type="hidden" name="reason" id="reasonCombined">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
                <button type="button" onclick="document.getElementById('reportModal').style.display='none'" class="btn-sm btn-gray">Cancel</button>
                <button type="submit" name="report_job" value="1" class="btn-sm" style="background:#dc2626; color:#fff; border:none;">
                    <i class="fas fa-paper-plane"></i> Submit Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- LIGHTBOX -->
<div id="lightbox" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:9999; justify-content:center; align-items:center; cursor:pointer;" onclick="this.style.display='none'">
    <img id="lightboxImg" src="" style="max-width:90%; max-height:90%; border-radius:8px;">
</div>

<script>
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').style.display = 'flex';
}

function copyJobLink() {
    const url = <?= json_encode($share_url) ?>;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
            const el = document.getElementById('copyText');
            const orig = el.textContent;
            el.textContent = 'Copied!';
            setTimeout(function(){ el.textContent = orig; }, 1500);
        });
    } else {
        const ta = document.createElement('textarea');
        ta.value = url;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);
        const el = document.getElementById('copyText');
        const orig = el.textContent;
        el.textContent = 'Copied!';
        setTimeout(function(){ el.textContent = orig; }, 1500);
    }
}

function combineReason() {
    var sel = document.getElementById('reportReasonSelect').value;
    var det = document.getElementById('reportReasonDetail').value.trim();
    document.getElementById('reasonCombined').value = det ? (sel + ' - ' + det) : sel;
}
</script>

<?php include 'inc/foot.php'; ?>