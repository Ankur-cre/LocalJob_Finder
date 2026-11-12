<?php
require_once 'inc/auth.php';
must_role('employer');
$title = "Candidate Profile — " . SITE_NAME;

$uid = (int)$_SESSION['uid'];
$app_id = (int)($_GET['id'] ?? 0);
if (!$app_id) { header("Location: employer.php"); exit; }

if (!function_exists('column_exists')) {
    function column_exists($db, $table, $column) {
        $table  = $db->real_escape_string($table);
        $column = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && $res->num_rows > 0;
    }
}

$stmt = $db->prepare("
    SELECT a.*, j.title AS job_title, j.id AS job_id, j.city AS job_city, j.district AS job_district,
           su.id AS seeker_id, su.name AS seeker_name, su.email AS seeker_email, 
           su.phone AS seeker_phone, su.address AS seeker_address, su.is_verified,
           su.city AS seeker_city, su.state AS seeker_state, su.headline AS seeker_headline,
           su.about_me AS seeker_about, su.profile_photo AS seeker_photo,
           su.dob AS seeker_dob, su.gender AS seeker_gender
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users su ON a.seeker_id = su.id
    WHERE a.id = ? AND j.employer_id = ?
");
$stmt->bind_param('ii', $app_id, $uid);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    header("Location: employer.php?msg=" . urlencode("Candidate profile not found or unauthorized."));
    exit;
}

$privacy = ['show_phone' => 1, 'show_email' => 1, 'show_photo' => 1, 'show_location' => 1, 'share_on_hire' => 1];
try {
    $p_fields = [];
    foreach (array_keys($privacy) as $col) {
        if (column_exists($db, 'users', $col)) $p_fields[] = $col;
    }
    if (!empty($p_fields)) {
        $psql = "SELECT " . implode(',', $p_fields) . " FROM users WHERE id = ?";
        $pq = $db->prepare($psql);
        $pq->bind_param('i', $app['seeker_id']);
        $pq->execute();
        $prow = $pq->get_result()->fetch_assoc();
        if ($prow) {
            foreach ($p_fields as $col) {
                if ($prow[$col] !== null) $privacy[$col] = (int)$prow[$col];
            }
        }
    }
} catch (Throwable $e) {}

$is_hired = ($app['status'] === 'hired');
if ($is_hired && !empty($privacy['share_on_hire'])) {
    $privacy['show_phone'] = 1;
    $privacy['show_email'] = 1;
    $privacy['show_photo'] = 1;
    $privacy['show_location'] = 1;
}

if (empty($app['viewed_at'])) {
    $db->query("UPDATE applications SET viewed_at = NOW() WHERE id = $app_id");
    $app['viewed_at'] = date('Y-m-d H:i:s');
}

$skills = null;
try {
    $sq = $db->prepare("SELECT * FROM seeker_skills WHERE seeker_id=? LIMIT 10");
    $sq->bind_param('i', $app['seeker_id']);
    $sq->execute();
    $skills = $sq->get_result();
} catch (Throwable $e) {}

$education = null;
try {
    $eq = $db->prepare("SELECT * FROM seeker_education WHERE seeker_id=? ORDER BY passing_year DESC LIMIT 3");
    $eq->bind_param('i', $app['seeker_id']);
    $eq->execute();
    $education = $eq->get_result();
} catch (Throwable $e) {}

$experience = null;
try {
    $xq = $db->prepare("SELECT * FROM seeker_experience WHERE seeker_id=? ORDER BY start_date DESC LIMIT 3");
    $xq->bind_param('i', $app['seeker_id']);
    $xq->execute();
    $experience = $xq->get_result();
} catch (Throwable $e) {}

$history = null;
try {
    $hq = $db->prepare("
        SELECT h.*, u.name AS changed_by_name 
        FROM application_status_history h
        LEFT JOIN users u ON h.changed_by = u.id
        WHERE h.application_id = ?
        ORDER BY h.created_at DESC LIMIT 10
    ");
    $hq->bind_param('i', $app_id);
    $hq->execute();
    $history = $hq->get_result();
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['app_status'])) {
    $new_status = $_POST['app_status'];
    $allowed = ['pending', 'shortlisted', 'interview', 'rejected', 'hired'];
    $current_status = $app['status'];
    $error_msg = '';
    $success_msg = '';
    
    if (in_array($new_status, $allowed)) {
        if ($new_status === 'interview' && !in_array($current_status, ['shortlisted', 'interview'])) {
            $error_msg = "⚠️ Candidate ko pehle SHORTLIST karo, phir Interview ke liye bulao.";
        } elseif ($new_status === 'hired' && !in_array($current_status, ['interview', 'shortlisted', 'hired'])) {
            $error_msg = "⚠️ Direct Hire nahi kar sakte. Pehle Shortlist → Interview → phir Hire.";
        } elseif ($new_status === 'pending' && $current_status !== 'pending') {
            $error_msg = "⚠️ Already processed candidate ko 'Pending' mein nahi daal sakte.";
        } else {
            $interview_details = null;
            $interview_date = null;
            $interview_time = null;
            $interview_type = null;
            $interview_location = null;
            
            if ($new_status === 'interview') {
                $interview_date = trim($_POST['interview_date'] ?? '');
                $interview_time = trim($_POST['interview_time'] ?? '');
                $interview_type = trim($_POST['interview_type'] ?? 'in_person');
                $interview_location = trim($_POST['interview_location'] ?? '');
                
                if ($interview_date === '' || $interview_time === '') {
                    $error_msg = "⚠️ Interview date aur time zaroor bharo.";
                } else {
                    $type_labels = ['in_person' => 'In Person', 'phone' => 'Phone Call', 'video' => 'Video Call', 'online' => 'Online Meeting'];
                    $type_label = $type_labels[$interview_type] ?? 'In Person';
                    $interview_details = "📅 " . date('d M Y', strtotime($interview_date))
                                       . " • ⏰ " . $interview_time
                                       . " • 📍 " . $type_label
                                       . (!empty($interview_location) ? " • " . $interview_location : '');
                }
            }
            
            if ($error_msg === '') {
                $u = $db->prepare("
                    UPDATE applications 
                    SET status=?, interview_details=?, interview_date=?, interview_time=?, interview_type=?, interview_location=?
                    WHERE id=?
                ");
                $u->bind_param('ssssssi', $new_status, $interview_details, $interview_date, $interview_time, $interview_type, $interview_location, $app_id);
                
                if ($u->execute()) {
                    $tf = '';
                    if ($new_status === 'shortlisted') $tf = 'shortlisted_at';
                    elseif ($new_status === 'interview') $tf = 'interview_at';
                    elseif ($new_status === 'hired') $tf = 'hired_at';
                    elseif ($new_status === 'rejected') $tf = 'rejected_at';
                    if ($tf && column_exists($db, 'applications', $tf)) {
                        $db->query("UPDATE applications SET $tf = NOW() WHERE id = $app_id");
                    }
                    
                    try {
                        $log = $db->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, changed_by_role, notes) VALUES (?, ?, ?, ?, 'employer', ?)");
                        $log->bind_param('issss', $app_id, $current_status, $new_status, $uid, $interview_details);
                        $log->execute();
                    } catch (Throwable $e) {}
                    
                    try {
                        $seeker_id = $app['seeker_id'];
                        $job_title = $app['job_title'];
                        $n_title = "Application Update";
                        if ($new_status === 'interview') $n_msg = "Interview scheduled for '$job_title'. $interview_details";
                        elseif ($new_status === 'hired') $n_msg = "🎉 Congratulations! You have been HIRED for '$job_title'.";
                        elseif ($new_status === 'shortlisted') $n_msg = "Great news! You have been shortlisted for '$job_title'.";
                        elseif ($new_status === 'rejected') $n_msg = "Your application for '$job_title' was not selected.";
                        else $n_msg = "Application status for '$job_title' updated.";
                        
                        $n_link = "seeker.php";
                        $ns = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
                        $ns->bind_param('isss', $seeker_id, $n_title, $n_msg, $n_link);
                        $ns->execute();
                    } catch (Throwable $e) {}
                    
                    $success_msg = "✅ Status updated to: " . ucfirst($new_status);
                }
            }
        }
    }
    
    if ($error_msg) {
        header("Location: candidate-profile.php?id=$app_id&err=" . urlencode($error_msg));
    } else {
        header("Location: candidate-profile.php?id=$app_id&msg=" . urlencode($success_msg));
    }
    exit;
}

$current_status = $app['status'];
$can_shortlist = in_array($current_status, ['pending']);
$can_interview = in_array($current_status, ['shortlisted']);
$can_hire = in_array($current_status, ['interview', 'shortlisted']);
$can_reject = in_array($current_status, ['pending', 'shortlisted', 'interview']);

$steps = [
    'applied' => !empty($app['applied_at']),
    'viewed' => !empty($app['viewed_at']),
    'shortlisted' => !empty($app['shortlisted_at']),
    'interview' => !empty($app['interview_at']),
    'hired' => !empty($app['hired_at']),
    'rejected' => !empty($app['rejected_at']),
];

include 'inc/head.php';
?>

<style>
.cp-clean { background: #f4f7fc; padding: 24px; margin: -20px; min-height: calc(100vh - 80px); }
.cp-clean * { box-sizing: border-box; }

.cp-card-clean {
    background: #fff;
    border-radius: 14px;
    padding: 24px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    margin-bottom: 20px;
}
.cp-card-title-clean {
    font-size: 1.05rem;
    font-weight: 700;
    color: #111827;
    margin: 0 0 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f3f4f6;
}

/* Timeline */
.cp-timeline-clean {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 20px 0 10px;
    overflow-x: auto;
}
.cp-timeline-clean .t-step {
    text-align: center;
    flex: 1;
    min-width: 80px;
}
.cp-timeline-clean .t-icon {
    width: 34px;
    height: 34px;
    background: #e5e7eb;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
    font-size: 0.8rem;
    border: 2px solid #e5e7eb;
    transition: all 0.3s;
}
.cp-timeline-clean .t-step.done .t-icon { background: #2563eb; border-color: #2563eb; }
.cp-timeline-clean .t-step.current .t-icon { background: #2563eb; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37,99,235,0.15); transform: scale(1.1); }
.cp-timeline-clean .t-step.hired .t-icon { background: #22c55e; border-color: #22c55e; }
.cp-timeline-clean .t-step.rejected .t-icon { background: #ef4444; border-color: #ef4444; }
.cp-timeline-clean .t-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #9ca3af;
    margin-bottom: 2px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.cp-timeline-clean .t-step.done .t-label { color: #2563eb; }
.cp-timeline-clean .t-step.current .t-label { color: #2563eb; }
.cp-timeline-clean .t-step.hired .t-label { color: #22c55e; }
.cp-timeline-clean .t-step.rejected .t-label { color: #ef4444; }
.cp-timeline-clean .t-date { font-size: 0.68rem; color: #9ca3af; }
.cp-timeline-clean .t-conn {
    flex: 0 0 30px;
    height: 2px;
    background: #e5e7eb;
    margin-top: 17px;
}
.cp-timeline-clean .t-conn.done { background: #2563eb; }

/* Layout */
.cp-grid-clean {
    display: grid;
    grid-template-columns: 1fr 1.4fr;
    gap: 24px;
    align-items: start;
}

/* Avatar */
.cp-avatar-lg {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    background: #dbeafe;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0 auto 15px;
    overflow: hidden;
    border: 4px solid #fff;
    box-shadow: 0 4px 12px rgba(37,99,235,0.2);
}
.cp-avatar-lg img { width: 100%; height: 100%; object-fit: cover; }

/* Status badge */
.cp-status-badge-clean {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    margin-bottom: 20px;
    width: 100%;
}
.stb-pending { background: #fef3c7; color: #92400e; }
.stb-shortlisted { background: #dbeafe; color: #1e40af; }
.stb-interview { background: #e0e7ff; color: #4338ca; }
.stb-hired { background: #dcfce7; color: #166534; }
.stb-rejected { background: #fee2e2; color: #991b1b; }

/* Contact rows */
.cp-contact-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 0.9rem;
}
.cp-contact-row:last-child { border-bottom: none; }
.cp-contact-row i { width: 16px; color: #2563eb; flex-shrink: 0; }
.cp-lock { color: #9ca3af; font-style: italic; font-size: 0.85rem; }

/* Skills */
.cp-skill-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    margin: 4px 4px 4px 0;
}
.cp-skill-tag .lvl {
    background: #dbeafe;
    padding: 1px 6px;
    border-radius: 8px;
    font-size: 0.65rem;
    text-transform: uppercase;
}

/* List items */
.cp-list-item {
    border-left: 3px solid #2563eb;
    padding: 12px 14px;
    background: #f9fafb;
    border-radius: 8px;
    margin-bottom: 10px;
}
.cp-list-item strong { display: block; font-size: 0.92rem; color: #111827; margin-bottom: 3px; }
.cp-list-item p { margin: 0; font-size: 0.8rem; color: #6b7280; }

/* History */
.cp-history-item {
    padding: 10px 12px;
    background: #f9fafb;
    border-radius: 8px;
    margin-bottom: 8px;
    font-size: 0.82rem;
    border-left: 3px solid #2563eb;
}
.cp-history-item .time {
    color: #9ca3af;
    font-size: 0.72rem;
    display: block;
    margin-top: 4px;
}

/* Form */
.cp-form-group { margin-bottom: 14px; }
.cp-form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: #374151; margin-bottom: 5px; }
.cp-form-group select, .cp-form-group input, .cp-form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1.5px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.9rem;
    outline: none;
    background: #fff;
}
.cp-form-group select:focus, .cp-form-group input:focus, .cp-form-group textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

/* Buttons */
.cp-btn {
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.88rem;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: all 0.2s;
}
.cp-btn-primary { background: #2563eb; color: #fff; }
.cp-btn-primary:hover { background: #1e40af; color: #fff; }
.cp-btn-success { background: #22c55e; color: #fff; }
.cp-btn-success:hover { background: #16a34a; color: #fff; }
.cp-btn-outline { background: #fff; color: #2563eb; border: 1.5px solid #2563eb; }
.cp-btn-outline:hover { background: #eff6ff; }
.cp-btn-block { width: 100%; justify-content: center; }

@media (max-width: 900px) {
    .cp-grid-clean { grid-template-columns: 1fr; }
    .cp-timeline-clean { overflow-x: auto; }
}
@media (max-width: 700px) {
    .cp-clean { padding: 15px; }
    .cp-card-clean { padding: 16px; }
}
</style>

<div class="cp-clean">

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-ok" style="margin-bottom:20px;"><i class="fas fa-check-circle"></i> <?= h($_GET['msg']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?>
    <div class="alert alert-err" style="margin-bottom:20px;"><i class="fas fa-exclamation-circle"></i> <?= h($_GET['err']) ?></div>
<?php endif; ?>

<div style="margin-bottom: 20px; display:flex; gap:10px; flex-wrap:wrap;">
    <a href="job-applicants.php?id=<?= $app['job_id'] ?>" class="cp-btn cp-btn-outline">
        <i class="fas fa-arrow-left"></i> Back to Applicants
    </a>
    <a href="employer.php" class="cp-btn cp-btn-outline">
        <i class="fas fa-home"></i> Dashboard
    </a>
</div>

<!-- TIMELINE -->
<div class="cp-card-clean">
    <h3 class="cp-card-title-clean">
        <i class="fas fa-route" style="color:#2563eb;"></i> Application Progress
    </h3>
    <p style="font-size:0.85rem; color:#6b7280; margin:0 0 10px;">
        Applied: <?= date('d M Y, h:i A', strtotime($app['applied_at'])) ?>
    </p>
    <div class="cp-timeline-clean">
        <div class="t-step done">
            <div class="t-icon"><i class="fas fa-paper-plane"></i></div>
            <div class="t-label">Applied</div>
            <div class="t-date"><?= date('d M', strtotime($app['applied_at'])) ?></div>
        </div>
        <div class="t-conn <?= $steps['viewed'] ? 'done' : '' ?>"></div>
        <div class="t-step <?= $steps['viewed'] ? 'done' : '' ?> <?= $current_status==='pending' && $steps['viewed'] ? 'current' : '' ?>">
            <div class="t-icon"><i class="fas fa-eye"></i></div>
            <div class="t-label">Viewed</div>
            <div class="t-date"><?= $steps['viewed'] ? date('d M', strtotime($app['viewed_at'])) : '—' ?></div>
        </div>
        <div class="t-conn <?= $steps['shortlisted'] ? 'done' : '' ?>"></div>
        <div class="t-step <?= $steps['shortlisted'] ? 'done' : '' ?> <?= $current_status==='shortlisted' ? 'current' : '' ?>">
            <div class="t-icon"><i class="fas fa-star"></i></div>
            <div class="t-label">Shortlisted</div>
            <div class="t-date"><?= $steps['shortlisted'] ? date('d M', strtotime($app['shortlisted_at'])) : '—' ?></div>
        </div>
        <div class="t-conn <?= $steps['interview'] ? 'done' : '' ?>"></div>
        <div class="t-step <?= $steps['interview'] ? 'done' : '' ?> <?= $current_status==='interview' ? 'current' : '' ?>">
            <div class="t-icon"><i class="fas fa-phone"></i></div>
            <div class="t-label">Interview</div>
            <div class="t-date"><?= $steps['interview'] ? date('d M', strtotime($app['interview_at'])) : '—' ?></div>
        </div>
        <div class="t-conn <?= $steps['hired'] || $steps['rejected'] ? 'done' : '' ?>"></div>
        <?php if ($current_status === 'rejected'): ?>
            <div class="t-step rejected current">
                <div class="t-icon"><i class="fas fa-times-circle"></i></div>
                <div class="t-label">Rejected</div>
                <div class="t-date"><?= date('d M', strtotime($app['rejected_at'])) ?></div>
            </div>
        <?php elseif ($current_status === 'hired'): ?>
            <div class="t-step done hired current">
                <div class="t-icon"><i class="fas fa-check-circle"></i></div>
                <div class="t-label">Hired</div>
                <div class="t-date"><?= date('d M', strtotime($app['hired_at'])) ?></div>
            </div>
        <?php else: ?>
            <div class="t-step">
                <div class="t-icon"><i class="fas fa-hourglass"></i></div>
                <div class="t-label">Result</div>
                <div class="t-date">Pending</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- GRID -->
<div class="cp-grid-clean">

    <!-- LEFT -->
    <div>
        <div class="cp-card-clean" style="text-align: center;">
            <div class="cp-avatar-lg">
                <?php if (!empty($app['seeker_photo']) && !empty($privacy['show_photo'])): ?>
                    <img src="<?= h($app['seeker_photo']) ?>" alt="">
                <?php else: ?>
                    <?= strtoupper(substr($app['seeker_name'], 0, 1)) ?>
                <?php endif; ?>
            </div>
            <h2 style="font-size:1.4rem; color:#111827; margin:0 0 5px;">
                <?= h($app['seeker_name']) ?>
                <?php if ($app['is_verified']): ?>
                    <i class="fas fa-check-circle" style="color:#0ea5e9; font-size:1rem;" title="Verified"></i>
                <?php endif; ?>
            </h2>
            <?php if (!empty($app['seeker_headline'])): ?>
                <p style="color:#6b7280; font-size:0.9rem; margin:0 0 12px;"><?= h($app['seeker_headline']) ?></p>
            <?php endif; ?>
            <p style="color:#6b7280; font-size:0.85rem; margin:0;">
                Applied for: <strong style="color:#2563eb;"><?= h($app['job_title']) ?></strong>
            </p>
        </div>
        
        <div class="cp-card-clean">
            <h3 class="cp-card-title-clean">
                <i class="fas fa-address-book" style="color:#2563eb;"></i> Contact Info
                <?php if (!$is_hired): ?>
                    <i class="fas fa-shield-alt" style="color:#9ca3af; font-size:0.75rem;" title="Privacy applied"></i>
                <?php endif; ?>
            </h3>
            <div class="cp-contact-row">
                <i class="fas fa-envelope"></i>
                <?php if (!empty($privacy['show_email']) && !empty($app['seeker_email'])): ?>
                    <a href="mailto:<?= h($app['seeker_email']) ?>" style="color:#111827; text-decoration:none;"><?= h($app['seeker_email']) ?></a>
                <?php else: ?>
                    <span class="cp-lock"><i class="fas fa-lock"></i> Hidden by candidate</span>
                <?php endif; ?>
            </div>
            <div class="cp-contact-row">
                <i class="fas fa-phone"></i>
                <?php if (!empty($privacy['show_phone']) && !empty($app['seeker_phone'])): ?>
                    <a href="tel:<?= h($app['seeker_phone']) ?>" style="color:#111827; text-decoration:none;"><?= h($app['seeker_phone']) ?></a>
                <?php elseif (!empty($app['seeker_phone'])): ?>
                    <span class="cp-lock"><i class="fas fa-lock"></i> Hidden by candidate</span>
                <?php else: ?>
                    <span style="color:#9ca3af;">Not provided</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($app['seeker_address']) || !empty($app['seeker_city'])): ?>
                <div class="cp-contact-row">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php if (!empty($privacy['show_location'])): ?>
                        <span><?= h($app['seeker_address'] ?: ($app['seeker_city'] . ($app['seeker_state'] ? ', ' . $app['seeker_state'] : ''))) ?></span>
                    <?php else: ?>
                        <span class="cp-lock"><i class="fas fa-lock"></i> Location hidden</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($is_hired && (!$privacy['show_phone'] || !$privacy['show_email'])): ?>
                <div style="margin-top:10px; padding:8px 10px; background:#dcfce7; color:#166534; border-radius:6px; font-size:0.78rem;">
                    <i class="fas fa-unlock-alt"></i> Candidate is hired — full contact unlocked.
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($app['seeker_about'])): ?>
        <div class="cp-card-clean">
            <h3 class="cp-card-title-clean"><i class="fas fa-user" style="color:#2563eb;"></i> About Candidate</h3>
            <p style="font-size:0.88rem; color:#374151; line-height:1.7; margin:0;"><?= nl2br(h($app['seeker_about'])) ?></p>
        </div>
        <?php endif; ?>
        
        <?php if ($skills && $skills->num_rows > 0): ?>
        <div class="cp-card-clean">
            <h3 class="cp-card-title-clean"><i class="fas fa-cog" style="color:#2563eb;"></i> Skills</h3>
            <?php while($s = $skills->fetch_assoc()): ?>
                <span class="cp-skill-tag">
                    <?= h($s['skill_name']) ?>
                    <span class="lvl"><?= h($s['skill_level'] ?? 'Beginner') ?></span>
                </span>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($education && $education->num_rows > 0): ?>
        <div class="cp-card-clean">
            <h3 class="cp-card-title-clean"><i class="fas fa-graduation-cap" style="color:#2563eb;"></i> Education</h3>
            <?php while($e = $education->fetch_assoc()): ?>
                <div class="cp-list-item">
                    <strong><?= h($e['qualification']) ?><?= !empty($e['stream']) ? ' - ' . h($e['stream']) : '' ?></strong>
                    <p><?= h($e['school_name']) ?><?= !empty($e['university']) ? ', ' . h($e['university']) : '' ?></p>
                    <p style="margin-top:4px; color:#9ca3af;">Passed: <?= h($e['passing_year']) ?><?= !empty($e['percentage']) ? ' • ' . h($e['percentage']) : '' ?></p>
                </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($experience && $experience->num_rows > 0): ?>
        <div class="cp-card-clean">
            <h3 class="cp-card-title-clean"><i class="fas fa-briefcase" style="color:#2563eb;"></i> Experience</h3>
            <?php while($x = $experience->fetch_assoc()): ?>
                <div class="cp-list-item" style="border-left-color:#22c55e;">
                    <strong><?= h($x['job_title']) ?></strong>
                    <p><?= h($x['company_name']) ?></p>
                    <p style="margin-top:4px; color:#9ca3af;">
                        <?= date('M Y', strtotime($x['start_date'])) ?> - 
                        <?= !empty($x['is_current']) ? 'Present' : (!empty($x['end_date']) ? date('M Y', strtotime($x['end_date'])) : 'Present') ?>
                    </p>
                </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
        
        <div class="cp-card-clean">
            <h3 class="cp-card-title-clean"><i class="fas fa-file-pdf" style="color:#2563eb;"></i> Resume / CV</h3>
            <?php if (!empty($app['resume_file'])): ?>
                <a href="storage/resumes/<?= h($app['resume_file']) ?>" target="_blank" class="cp-btn cp-btn-primary cp-btn-block">
                    <i class="fas fa-file-pdf"></i> View / Download CV
                </a>
            <?php else: ?>
                <div style="background:#fef3c7; color:#92400e; padding:12px; border-radius:8px; font-size:0.85rem; text-align:center;">
                    <i class="fas fa-exclamation-triangle"></i> No resume uploaded
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- RIGHT -->
    <div>
        <div class="cp-card-clean">
            <h3 class="cp-card-title-clean"><i class="fas fa-tasks" style="color:#2563eb;"></i> Manage Candidate</h3>
            
            <?php 
                $st_labels = [
                    'pending' => '⏳ Pending Review',
                    'shortlisted' => '⭐ Shortlisted',
                    'interview' => '📞 Interview Scheduled',
                    'hired' => '✅ Hired',
                    'rejected' => '❌ Rejected'
                ];
            ?>
            <div class="cp-status-badge-clean stb-<?= $current_status ?>">
                <?= $st_labels[$current_status] ?? ucfirst($current_status) ?>
            </div>
            
            <?php if ($current_status === 'interview' && !empty($app['interview_details'])): ?>
                <div style="background:#eff6ff; border-left:4px solid #2563eb; padding:14px; border-radius:8px; margin-bottom:20px;">
                    <p style="font-size:0.75rem; color:#6b7280; margin:0 0 6px; font-weight:600; text-transform:uppercase;">Interview Details Sent</p>
                    <p style="margin:0; color:#111827; font-size:0.88rem; line-height:1.5;"><?= nl2br(h($app['interview_details'])) ?></p>
                </div>
            <?php endif; ?>
            
            <div style="background:#f0f9ff; border-left:3px solid #0ea5e9; padding:10px 14px; border-radius:6px; margin-bottom:20px; font-size:0.82rem; color:#075985;">
                <i class="fas fa-info-circle"></i>
                <?php if ($current_status === 'pending'): ?>
                    <strong>Next:</strong> Shortlist the candidate to proceed.
                <?php elseif ($current_status === 'shortlisted'): ?>
                    <strong>Next:</strong> Schedule interview OR hire directly.
                <?php elseif ($current_status === 'interview'): ?>
                    <strong>Next:</strong> Hire or reject based on result.
                <?php elseif ($current_status === 'hired'): ?>
                    <strong>✅ Hired!</strong> You can now chat/WhatsApp.
                <?php elseif ($current_status === 'rejected'): ?>
                    <strong>Rejected.</strong> Process complete.
                <?php endif; ?>
            </div>
            
            <form method="POST">
                <div class="cp-form-group">
                    <label>Update Status</label>
                    <select name="app_status" id="statusSelect" onchange="toggleInterview()">
                        <?php if ($current_status === 'pending'): ?>
                            <option value="pending" selected>⏳ Pending (Current)</option>
                        <?php endif; ?>
                        <option value="shortlisted" <?= $current_status==='shortlisted'?'selected':'' ?> <?= !$can_shortlist && $current_status!=='shortlisted'?'disabled':'' ?>>⭐ Shortlist</option>
                        <option value="interview" <?= $current_status==='interview'?'selected':'' ?> <?= !$can_interview && $current_status!=='interview'?'disabled':'' ?>>📞 Call for Interview<?= !$can_interview && $current_status!=='interview'?' (Shortlist first)':'' ?></option>
                        <option value="hired" <?= $current_status==='hired'?'selected':'' ?> <?= !$can_hire && $current_status!=='hired'?'disabled':'' ?>>✅ Hire<?= !$can_hire && $current_status!=='hired'?' (Interview first)':'' ?></option>
                        <option value="rejected" <?= $current_status==='rejected'?'selected':'' ?> <?= !$can_reject && $current_status!=='rejected'?'disabled':'' ?>>❌ Reject</option>
                    </select>
                </div>
                
                <div id="interviewBox" style="display:<?= $current_status==='interview' ? 'block' : 'none' ?>;">
                    <div class="cp-form-group">
                        <label>Interview Date *</label>
                        <input type="date" name="interview_date" value="<?= h($app['interview_date'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="cp-form-group">
                        <label>Interview Time *</label>
                        <input type="text" name="interview_time" placeholder="e.g. 10:00 AM" value="<?= h($app['interview_time'] ?? '') ?>">
                    </div>
                    <div class="cp-form-group">
                        <label>Interview Type</label>
                        <select name="interview_type">
                            <option value="in_person" <?= ($app['interview_type'] ?? '')==='in_person'?'selected':'' ?>>In Person</option>
                            <option value="phone" <?= ($app['interview_type'] ?? '')==='phone'?'selected':'' ?>>Phone Call</option>
                            <option value="video" <?= ($app['interview_type'] ?? '')==='video'?'selected':'' ?>>Video Call</option>
                            <option value="online" <?= ($app['interview_type'] ?? '')==='online'?'selected':'' ?>>Online Meeting</option>
                        </select>
                    </div>
                    <div class="cp-form-group">
                        <label>Location / Meeting Link</label>
                        <input type="text" name="interview_location" placeholder="Address or meeting link" value="<?= h($app['interview_location'] ?? '') ?>">
                    </div>
                </div>
                
                <button type="submit" class="cp-btn cp-btn-primary cp-btn-block" style="padding:12px;">
                    <i class="fas fa-save"></i> Save & Update Status
                </button>
            </form>
            
            <?php if (in_array($current_status, ['shortlisted', 'interview', 'hired'])): ?>
                <div style="margin-top:20px; padding-top:20px; border-top:1px dashed #e5e7eb;">
                    <p style="font-size:0.85rem; color:#6b7280; margin:0 0 12px;">
                        <i class="fas fa-handshake" style="color:#10b981;"></i> Contact candidate:
                    </p>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <a href="chat.php?app_id=<?= $app_id ?>" class="cp-btn cp-btn-success cp-btn-block" style="padding:10px;">
                            <i class="fas fa-comments"></i> Chat
                        </a>
                        <?php if (!empty($privacy['show_phone']) && !empty($app['seeker_phone'])): ?>
                            <a href="https://wa.me/91<?= h(preg_replace('/\D/', '', $app['seeker_phone'])) ?>" target="_blank" class="cp-btn cp-btn-block" style="padding:10px; border:2px solid #22c55e; background:#fff; color:#22c55e;">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div style="margin-top:20px; padding-top:20px; border-top:1px dashed #e5e7eb;">
                    <p style="font-size:0.82rem; color:#9ca3af; text-align:center; margin:0;">
                        <i class="fas fa-lock"></i> Chat & WhatsApp unlock after shortlisting.
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($history && $history->num_rows > 0): ?>
        <div class="cp-card-clean">
            <h3 class="cp-card-title-clean"><i class="fas fa-history" style="color:#2563eb;"></i> Status History</h3>
            <?php while($h = $history->fetch_assoc()): ?>
                <div class="cp-history-item">
                    <strong style="color:#111827;">
                        <?= ucfirst($h['old_status'] ?? 'new') ?> → <?= ucfirst($h['new_status']) ?>
                    </strong>
                    <?php if (!empty($h['notes'])): ?>
                        <p style="margin:4px 0 0; color:#6b7280; font-size:0.78rem;"><?= h($h['notes']) ?></p>
                    <?php endif; ?>
                    <span class="time">
                        <i class="far fa-clock"></i> <?= date('d M Y, h:i A', strtotime($h['created_at'])) ?>
                        <?php if (!empty($h['changed_by_name'])): ?>
                            • by <?= h($h['changed_by_name']) ?>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

</div>

<script>
function toggleInterview() {
    const sel = document.getElementById('statusSelect');
    const box = document.getElementById('interviewBox');
    box.style.display = sel.value === 'interview' ? 'block' : 'none';
}
</script>

<?php include 'inc/foot.php'; ?>