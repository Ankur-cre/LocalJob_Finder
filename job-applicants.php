<?php
require_once 'inc/auth.php';
must_role('employer');
$title = "Manage Applicants — " . SITE_NAME;

$uid = (int)$_SESSION['uid'];
$job_id = (int)($_GET['id'] ?? 0);
if (!$job_id) { header("Location: employer.php"); exit; }

if (!function_exists('column_exists')) {
    function column_exists($db, $table, $column) {
        $table  = $db->real_escape_string($table);
        $column = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && $res->num_rows > 0;
    }
}

try {
    $needed_app_cols = [
        'interview_details' => "TEXT NULL",
        'interview_date'    => "DATE NULL",
        'interview_time'    => "VARCHAR(50) NULL",
        'interview_type'    => "VARCHAR(30) NULL",
        'interview_location'=> "TEXT NULL",
        'notes'             => "TEXT NULL",
    ];
    foreach ($needed_app_cols as $col => $def) {
        if (!column_exists($db, 'applications', $col)) {
            try { $db->query("ALTER TABLE applications ADD COLUMN `$col` $def"); } catch (Throwable $e) {}
        }
    }
} catch (Throwable $e) {}

try {
    $db->query("
        CREATE TABLE IF NOT EXISTS application_status_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            old_status VARCHAR(30) NULL,
            new_status VARCHAR(30) NOT NULL,
            changed_by INT NOT NULL,
            changed_by_role VARCHAR(20) NOT NULL DEFAULT 'employer',
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_app (application_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {}

$stmt = $db->prepare("SELECT * FROM jobs WHERE id=? AND employer_id=?");
$stmt->bind_param('ii', $job_id, $uid);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    header("Location: employer.php?msg=" . urlencode("Job not found or unauthorized."));
    exit;
}

// ============ HANDLE STATUS UPDATE ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['app_id'], $_POST['app_status'])) {
    $app_id = (int)$_POST['app_id'];
    $new_status = $_POST['app_status'];
    $allowed = ['pending', 'shortlisted', 'interview', 'rejected', 'hired'];
    
    $check = $db->prepare("SELECT a.status, a.seeker_id, j.title AS job_title 
                           FROM applications a 
                           JOIN jobs j ON a.job_id = j.id
                           WHERE a.id=? AND a.job_id=? AND j.employer_id=?");
    $check->bind_param('iii', $app_id, $job_id, $uid);
    $check->execute();
    $current = $check->get_result()->fetch_assoc();
    
    if (!$current) {
        header("Location: job-applicants.php?id=$job_id&err=" . urlencode("Application not found."));
        exit;
    }
    
    $current_status = $current['status'];
    $seeker_id = $current['seeker_id'];
    $job_title = $current['job_title'];
    $error_msg = '';
    $success_msg = '';
    
    if (in_array($new_status, $allowed)) {
        
        if ($new_status === 'interview' && !in_array($current_status, ['shortlisted', 'interview'])) {
            $error_msg = "⚠️ Candidate ko pehle SHORTLIST karo, phir Interview ke liye bulao.";
        }
        elseif ($new_status === 'hired' && !in_array($current_status, ['interview', 'shortlisted', 'hired'])) {
            $error_msg = "⚠️ Direct Hire nahi kar sakte. Pehle Shortlist → Interview → phir Hire.";
        }
        elseif ($new_status === 'pending' && $current_status !== 'pending') {
            $error_msg = "⚠️ Already processed candidate ko 'Pending' mein nahi daal sakte.";
        }
        else {
            $interview_date = null;
            $interview_time = null;
            $interview_type = null;
            $interview_location = null;
            $interview_details = null;
            
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
                    $interview_details = "📅 Date: " . date('d M Y', strtotime($interview_date))
                                       . " | ⏰ Time: " . $interview_time
                                       . " | 📍 Type: " . $type_label
                                       . (!empty($interview_location) ? " | Location/Link: " . $interview_location : '');
                }
            }
            
            if ($error_msg === '') {
                $u = $db->prepare("
                    UPDATE applications 
                    SET status=?, interview_details=?, interview_date=?, interview_time=?, interview_type=?, interview_location=?
                    WHERE id=? AND job_id=?
                ");
                $u->bind_param('ssssssii', $new_status, $interview_details, $interview_date, $interview_time, $interview_type, $interview_location, $app_id, $job_id);
                $u->execute();
                
                $timestamp_field = '';
                if ($new_status === 'shortlisted') $timestamp_field = 'shortlisted_at';
                elseif ($new_status === 'interview') $timestamp_field = 'interview_at';
                elseif ($new_status === 'hired') $timestamp_field = 'hired_at';
                elseif ($new_status === 'rejected') $timestamp_field = 'rejected_at';
                
                if ($timestamp_field && column_exists($db, 'applications', $timestamp_field)) {
                    $db->query("UPDATE applications SET $timestamp_field = NOW() WHERE id = $app_id");
                }
                
                try {
                    $log = $db->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, changed_by_role, notes) VALUES (?, ?, ?, ?, 'employer', ?)");
                    $log_notes = $interview_details ?: null;
                    $log->bind_param('issss', $app_id, $current_status, $new_status, $uid, $log_notes);
                    $log->execute();
                } catch (Throwable $e) {}
                
                $n_title = "Application Update";
                if ($new_status === 'shortlisted') $n_msg = "Great news! You have been shortlisted for '$job_title'.";
                elseif ($new_status === 'interview') $n_msg = "Interview scheduled for '$job_title'. $interview_details";
                elseif ($new_status === 'hired') $n_msg = "🎉 Congratulations! You have been HIRED for '$job_title'.";
                elseif ($new_status === 'rejected') $n_msg = "Your application for '$job_title' was not selected.";
                else $n_msg = "Your application status for '$job_title' is now: " . ucfirst($new_status);
                $n_link = "seeker.php";
                try {
                    $ns = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
                    $ns->bind_param('isss', $seeker_id, $n_title, $n_msg, $n_link);
                    $ns->execute();
                } catch (Throwable $e) {}
                
                $success_msg = "✅ Status updated: " . ucfirst($new_status);
            }
        }
    } else {
        $error_msg = "Invalid status.";
    }
    
    if ($error_msg) {
        header("Location: job-applicants.php?id=$job_id&err=" . urlencode($error_msg));
    } else {
        header("Location: job-applicants.php?id=$job_id&msg=" . urlencode($success_msg));
    }
    exit;
}

// ============ HANDLE BULK ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'], $_POST['bulk_ids'])) {
    $bulk_status = $_POST['bulk_action'];
    $ids = array_map('intval', (array)$_POST['bulk_ids']);
    $allowed = ['shortlisted', 'rejected'];
    $count = 0;
    
    if (in_array($bulk_status, $allowed) && !empty($ids)) {
        foreach ($ids as $app_id) {
            $check = $db->prepare("SELECT a.status, a.seeker_id, j.title FROM applications a JOIN jobs j ON a.job_id = j.id WHERE a.id=? AND a.job_id=? AND j.employer_id=?");
            $check->bind_param('iii', $app_id, $job_id, $uid);
            $check->execute();
            $curr = $check->get_result()->fetch_assoc();
            if (!$curr) continue;
            
            if ($bulk_status === 'shortlisted' && $curr['status'] !== 'pending') continue;
            if ($bulk_status === 'rejected' && !in_array($curr['status'], ['pending', 'shortlisted', 'interview'])) continue;
            
            $u = $db->prepare("UPDATE applications SET status=? WHERE id=?");
            $u->bind_param('si', $bulk_status, $app_id);
            $u->execute();
            
            $tf = ($bulk_status === 'shortlisted') ? 'shortlisted_at' : 'rejected_at';
            if (column_exists($db, 'applications', $tf)) {
                $db->query("UPDATE applications SET $tf = NOW() WHERE id = $app_id");
            }
            
            try {
                $log = $db->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, changed_by_role) VALUES (?, ?, ?, ?, 'employer')");
                $log->bind_param('isss', $app_id, $curr['status'], $bulk_status, $uid);
                $log->execute();
            } catch (Throwable $e) {}
            
            $n_title = "Application Update";
            $n_msg = ($bulk_status === 'shortlisted')
                ? "You have been shortlisted for '{$curr['title']}'."
                : "Your application for '{$curr['title']}' was not selected.";
            $n_link = "seeker.php";
            try {
                $ns = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
                $ns->bind_param('isss', $curr['seeker_id'], $n_title, $n_msg, $n_link);
                $ns->execute();
            } catch (Throwable $e) {}
            
            $count++;
        }
    }
    
    header("Location: job-applicants.php?id=$job_id&msg=" . urlencode("$count candidate(s) updated to " . ucfirst($bulk_status) . "."));
    exit;
}

$db->query("UPDATE applications SET viewed_at = NOW() WHERE job_id = $job_id AND viewed_at IS NULL");

$filter_status = $_GET['filter'] ?? 'all';
$search        = trim($_GET['q'] ?? '');
$sort          = $_GET['sort'] ?? 'newest';
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;

$count_query = $db->prepare("SELECT status, COUNT(*) AS c FROM applications WHERE job_id=? GROUP BY status");
$count_query->bind_param('i', $job_id);
$count_query->execute();
$status_counts = ['all' => 0, 'pending' => 0, 'shortlisted' => 0, 'interview' => 0, 'hired' => 0, 'rejected' => 0];
$res = $count_query->get_result();
while ($row = $res->fetch_assoc()) {
    $status_counts[$row['status']] = (int)$row['c'];
    $status_counts['all'] += (int)$row['c'];
}

$where = "a.job_id = ?";
$params = [$job_id];
$types = 'i';
if ($filter_status !== 'all' && isset($status_counts[$filter_status])) {
    $where .= " AND a.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if ($search !== '') {
    $where .= " AND (su.name LIKE ? OR su.email LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$order_by = "a.applied_at DESC";
if ($sort === 'oldest') $order_by = "a.applied_at ASC";
elseif ($sort === 'name') $order_by = "su.name ASC";
elseif ($sort === 'status') $order_by = "a.status ASC, a.applied_at DESC";

$count_total_q = $db->prepare("SELECT COUNT(*) AS c FROM applications a JOIN users su ON a.seeker_id=su.id WHERE $where");
$count_total_q->bind_param($types, ...$params);
$count_total_q->execute();
$filtered_total = (int)$count_total_q->get_result()->fetch_assoc()['c'];
$total_pages = max(1, ceil($filtered_total / $per_page));

$sql = "
    SELECT a.*, su.name AS seeker_name, su.email AS seeker_email, su.phone AS seeker_phone, 
           su.is_verified AS seeker_verified, su.headline AS seeker_headline, 
           su.profile_photo AS seeker_photo, su.city AS seeker_city
    FROM applications a
    JOIN users su ON a.seeker_id = su.id
    WHERE $where
    ORDER BY $order_by
    LIMIT $per_page OFFSET $offset
";
$apps_query = $db->prepare($sql);
$apps_query->bind_param($types, ...$params);
$apps_query->execute();
$apps_res = $apps_query->get_result();

include 'inc/head.php';
?>

<style>
.apps-clean { background: #f4f7fc; padding: 24px; margin: -20px; min-height: calc(100vh - 80px); }
.apps-clean * { box-sizing: border-box; }

.apps-header {
    background: #fff;
    border-radius: 14px;
    padding: 22px 26px;
    margin-bottom: 20px;
    border-left: 4px solid #2563eb;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.apps-header h2 {
    font-size: 1.4rem;
    color: #111827;
    margin: 0 0 6px;
    font-weight: 700;
}
.apps-header h2 span { color: #2563eb; }
.apps-header p {
    color: #6b7280;
    font-size: 0.88rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.filter-tabs-clean {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.filter-tab-clean {
    padding: 8px 16px;
    background: #fff;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    text-decoration: none;
    color: #6b7280;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.filter-tab-clean:hover { border-color: #2563eb; color: #2563eb; text-decoration: none; }
.filter-tab-clean.active { background: #2563eb; color: #fff; border-color: #2563eb; }
.filter-tab-clean .badge {
    background: rgba(255,255,255,0.25);
    padding: 1px 7px;
    border-radius: 10px;
    font-size: 0.72rem;
    font-weight: 700;
}
.filter-tab-clean:not(.active) .badge { background: #eff6ff; color: #2563eb; }

.search-bar-clean {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.search-bar-clean input[type="text"] {
    flex: 1;
    min-width: 200px;
    padding: 11px 14px;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.9rem;
    outline: none;
    transition: border 0.2s;
}
.search-bar-clean input[type="text"]:focus { border-color: #2563eb; }
.search-bar-clean select {
    padding: 11px 14px;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.9rem;
    background: #fff;
    cursor: pointer;
    outline: none;
}
.search-bar-clean button {
    padding: 11px 20px;
    background: #2563eb;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    font-size: 0.9rem;
}
.search-bar-clean button:hover { background: #1e40af; }

/* Applicant Card — Seeker Style */
.applicant-card-clean {
    background: #fff;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 16px;
    border: 1px solid #e5e7eb;
    border-left: 4px solid #2563eb;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    transition: box-shadow 0.2s;
}
.applicant-card-clean:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
.applicant-card-clean.pending { border-left-color: #f59e0b; }
.applicant-card-clean.shortlisted { border-left-color: #2563eb; }
.applicant-card-clean.interview { border-left-color: #6366f1; }
.applicant-card-clean.hired { border-left-color: #22c55e; background: #f0fdf4; }
.applicant-card-clean.rejected { border-left-color: #ef4444; }

.app-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.app-card-user {
    display: flex;
    gap: 14px;
    flex: 1;
    min-width: 220px;
}
.app-avatar-lg {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    background: #dbeafe;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: 800;
    flex-shrink: 0;
    overflow: hidden;
    border: 3px solid #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.app-avatar-lg img { width: 100%; height: 100%; object-fit: cover; }
.app-user-info { flex: 1; min-width: 0; }
.app-user-name {
    font-size: 1.05rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.app-user-name a { color: inherit; text-decoration: none; }
.app-user-name a:hover { color: #2563eb; }
.app-user-headline {
    font-size: 0.85rem;
    color: #6b7280;
    margin-bottom: 6px;
}
.app-user-meta {
    font-size: 0.8rem;
    color: #6b7280;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.app-user-meta span {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.app-card-status {
    text-align: right;
    flex-shrink: 0;
}

/* Vertical Timeline — Seeker Style */
.app-timeline {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 20px 0 12px;
    margin: 16px 0;
    border-top: 1px solid #f3f4f6;
    border-bottom: 1px solid #f3f4f6;
    overflow-x: auto;
}
.timeline-step {
    text-align: center;
    flex: 1;
    min-width: 80px;
}
.timeline-icon {
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
    transition: all 0.3s;
    border: 2px solid #e5e7eb;
}
.timeline-step.done .timeline-icon { background: #2563eb; border-color: #2563eb; }
.timeline-step.current .timeline-icon { background: #2563eb; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37,99,235,0.15); }
.timeline-step.hired .timeline-icon { background: #22c55e; border-color: #22c55e; }
.timeline-step.rejected .timeline-icon { background: #ef4444; border-color: #ef4444; }

.timeline-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #9ca3af;
    margin-bottom: 2px;
}
.timeline-step.done .timeline-label { color: #2563eb; }
.timeline-step.current .timeline-label { color: #2563eb; }
.timeline-step.hired .timeline-label { color: #22c55e; }
.timeline-step.rejected .timeline-label { color: #ef4444; }

.timeline-date {
    font-size: 0.7rem;
    color: #9ca3af;
}
.timeline-connector {
    flex: 0 0 30px;
    height: 2px;
    background: #e5e7eb;
    margin-top: 17px;
    align-self: flex-start;
}
.timeline-connector.done { background: #2563eb; }

/* Interview details */
.interview-details-box {
    background: #eef2ff;
    border-left: 3px solid #6366f1;
    padding: 12px 16px;
    border-radius: 8px;
    margin: 16px 0;
    font-size: 0.88rem;
    color: #4338ca;
}
.interview-details-box strong { display: block; margin-bottom: 4px; }

/* Actions */
.app-card-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
}
.action-btn-clean {
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
    white-space: nowrap;
}
.btn-blue-clean { background: #2563eb; color: #fff; }
.btn-blue-clean:hover { background: #1e40af; color: #fff; }
.btn-green-clean { background: #22c55e; color: #fff; }
.btn-green-clean:hover { background: #16a34a; color: #fff; }
.btn-red-clean { background: #ef4444; color: #fff; }
.btn-red-clean:hover { background: #dc2626; color: #fff; }
.btn-gray-clean { background: #f3f4f6; color: #374151; border: 1.5px solid #e5e7eb; }
.btn-gray-clean:hover { background: #e5e7eb; }
.btn-outline-clean { background: #fff; color: #2563eb; border: 1.5px solid #2563eb; }
.btn-outline-clean:hover { background: #eff6ff; }

/* Status form */
.status-update-form {
    background: #f9fafb;
    border-radius: 10px;
    padding: 16px;
    margin-top: 14px;
    border: 1px dashed #d1d5db;
    display: none;
}
.status-update-form.show { display: block; }
.status-update-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 12px;
}
.status-update-form label {
    display: block;
    font-size: 0.78rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 4px;
}
.status-update-form select,
.status-update-form input {
    width: 100%;
    padding: 8px 12px;
    border: 1.5px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.85rem;
    outline: none;
}
.status-update-form select:focus,
.status-update-form input:focus { border-color: #2563eb; }

.status-pill-lg {
    padding: 5px 14px;
    border-radius: 14px;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pill-pending-lg { background: #fef3c7; color: #92400e; }
.pill-shortlisted-lg { background: #dbeafe; color: #1e40af; }
.pill-interview-lg { background: #e0e7ff; color: #4338ca; }
.pill-hired-lg { background: #dcfce7; color: #166534; }
.pill-rejected-lg { background: #fee2e2; color: #991b1b; }

/* Pagination */
.pagination-clean {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin-top: 20px;
    flex-wrap: wrap;
}
.pagination-clean a,
.pagination-clean span {
    padding: 8px 14px;
    border-radius: 8px;
    text-decoration: none;
    background: #fff;
    border: 1.5px solid #e5e7eb;
    color: #374151;
    font-weight: 600;
    font-size: 0.85rem;
}
.pagination-clean a:hover { border-color: #2563eb; color: #2563eb; text-decoration: none; }
.pagination-clean .current { background: #2563eb; color: #fff; border-color: #2563eb; }
.pagination-clean .disabled { opacity: 0.4; pointer-events: none; }

.empty-clean {
    background: #fff;
    border-radius: 12px;
    padding: 60px 20px;
    text-align: center;
}
.empty-clean i { font-size: 3rem; color: #d1d5db; margin-bottom: 12px; display: block; }
.empty-clean h3 { color: #374151; margin-bottom: 8px; font-size: 1.1rem; }
.empty-clean p { color: #6b7280; margin: 0; font-size: 0.9rem; }

@media (max-width: 700px) {
    .apps-clean { padding: 15px; }
    .status-update-form .form-row { grid-template-columns: 1fr; }
    .applicant-card-clean { padding: 16px; }
    .app-card-header { flex-direction: column; }
    .app-card-status { text-align: left; }
}
</style>

<div class="apps-clean">

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-ok" style="margin-bottom:20px;"><i class="fas fa-check-circle"></i> <?= h($_GET['msg']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?>
    <div class="alert alert-err" style="margin-bottom:20px;"><i class="fas fa-exclamation-circle"></i> <?= h($_GET['err']) ?></div>
<?php endif; ?>

<!-- HEADER -->
<div class="apps-header">
    <div>
        <h2>Applicants for: <span><?= h($job['title']) ?></span></h2>
        <p>
            <i class="fas fa-users"></i> <?= (int)$status_counts['all'] ?> total applications •
            Vacancies: <?= (int)$job['vacancies'] ?> •
            Location: <?= h($job['city'] ?? '') ?>, <?= h($job['district'] ?? '') ?>
        </p>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="employer.php" class="action-btn-clean btn-gray-clean"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <a href="manage-job.php?id=<?= $job_id ?>" class="action-btn-clean btn-blue-clean"><i class="fas fa-edit"></i> Edit Job</a>
    </div>
</div>

<!-- FILTER TABS -->
<div class="filter-tabs-clean">
    <a href="?id=<?= $job_id ?>&filter=all" class="filter-tab-clean <?= $filter_status==='all'?'active':'' ?>">
        <i class="fas fa-list"></i> All <span class="badge"><?= $status_counts['all'] ?></span>
    </a>
    <a href="?id=<?= $job_id ?>&filter=pending" class="filter-tab-clean <?= $filter_status==='pending'?'active':'' ?>">
        <i class="fas fa-clock"></i> Pending <span class="badge"><?= $status_counts['pending'] ?></span>
    </a>
    <a href="?id=<?= $job_id ?>&filter=shortlisted" class="filter-tab-clean <?= $filter_status==='shortlisted'?'active':'' ?>">
        <i class="fas fa-star"></i> Shortlisted <span class="badge"><?= $status_counts['shortlisted'] ?></span>
    </a>
    <a href="?id=<?= $job_id ?>&filter=interview" class="filter-tab-clean <?= $filter_status==='interview'?'active':'' ?>">
        <i class="fas fa-phone"></i> Interview <span class="badge"><?= $status_counts['interview'] ?></span>
    </a>
    <a href="?id=<?= $job_id ?>&filter=hired" class="filter-tab-clean <?= $filter_status==='hired'?'active':'' ?>">
        <i class="fas fa-check-circle"></i> Hired <span class="badge"><?= $status_counts['hired'] ?></span>
    </a>
    <a href="?id=<?= $job_id ?>&filter=rejected" class="filter-tab-clean <?= $filter_status==='rejected'?'active':'' ?>">
        <i class="fas fa-times-circle"></i> Rejected <span class="badge"><?= $status_counts['rejected'] ?></span>
    </a>
</div>

<!-- SEARCH -->
<form method="GET" class="search-bar-clean">
    <input type="hidden" name="id" value="<?= $job_id ?>">
    <input type="hidden" name="filter" value="<?= h($filter_status) ?>">
    <input type="text" name="q" placeholder="Search by name or email..." value="<?= h($search) ?>">
    <select name="sort">
        <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest First</option>
        <option value="oldest" <?= $sort==='oldest'?'selected':'' ?>>Oldest First</option>
        <option value="name" <?= $sort==='name'?'selected':'' ?>>Name (A-Z)</option>
        <option value="status" <?= $sort==='status'?'selected':'' ?>>By Status</option>
    </select>
    <button type="submit"><i class="fas fa-search"></i> Apply</button>
</form>

<!-- APPLICANTS -->
<?php if ($apps_res->num_rows === 0): ?>
    <div class="empty-clean">
        <i class="fas fa-inbox"></i>
        <h3>No applicants found</h3>
        <p><?= $filter_status === 'all' ? 'Nobody has applied to this job yet.' : 'No applicants with this status.' ?></p>
    </div>
<?php else: ?>
    <?php while($a = $apps_res->fetch_assoc()): 
        $cur = $a['status'];
        $can_shortlist = in_array($cur, ['pending']);
        $can_interview = in_array($cur, ['shortlisted']);
        $can_hire = in_array($cur, ['interview', 'shortlisted']);
        $can_reject = in_array($cur, ['pending', 'shortlisted', 'interview']);
        $pill_cls = 'pill-' . ($cur === 'pending' ? 'pending-lg' : ($cur === 'hired' ? 'hired-lg' : ($cur === 'rejected' ? 'rejected-lg' : ($cur === 'interview' ? 'interview-lg' : 'shortlisted-lg'))));
    ?>
        <div class="applicant-card-clean <?= $cur ?>">
            
            <!-- Header -->
            <div class="app-card-header">
                <div class="app-card-user">
                    <div class="app-avatar-lg">
                        <?php if (!empty($a['seeker_photo'])): ?>
                            <img src="<?= h($a['seeker_photo']) ?>" alt="">
                        <?php else: ?>
                            <?= strtoupper(substr($a['seeker_name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="app-user-info">
                        <div class="app-user-name">
                            <a href="candidate-profile.php?id=<?= (int)$a['id'] ?>"><?= h($a['seeker_name']) ?></a>
                            <?php if (!empty($a['seeker_verified'])): ?>
                                <i class="fas fa-check-circle" style="color:#0ea5e9; font-size:0.85rem;" title="Verified"></i>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($a['seeker_headline'])): ?>
                            <div class="app-user-headline"><?= h($a['seeker_headline']) ?></div>
                        <?php endif; ?>
                        <div class="app-user-meta">
                            <span><i class="fas fa-envelope"></i> <?= h($a['seeker_email']) ?></span>
                            <?php if ($a['seeker_phone']): ?>
                                <span><i class="fas fa-phone"></i> <?= h($a['seeker_phone']) ?></span>
                            <?php endif; ?>
                            <?php if ($a['seeker_city']): ?>
                                <span><i class="fas fa-map-marker-alt"></i> <?= h($a['seeker_city']) ?></span>
                            <?php endif; ?>
                            <span><i class="far fa-clock"></i> Applied <?= date('d M Y', strtotime($a['applied_at'])) ?></span>
                        </div>
                    </div>
                </div>
                <div class="app-card-status">
                    <span class="status-pill-lg <?= $pill_cls ?>">
                        <?php if ($cur === 'pending'): ?><i class="fas fa-clock"></i>
                        <?php elseif ($cur === 'shortlisted'): ?><i class="fas fa-star"></i>
                        <?php elseif ($cur === 'interview'): ?><i class="fas fa-phone"></i>
                        <?php elseif ($cur === 'hired'): ?><i class="fas fa-check-circle"></i>
                        <?php else: ?><i class="fas fa-times-circle"></i><?php endif; ?>
                        <?= ucfirst($cur) ?>
                    </span>
                </div>
            </div>

            <!-- Timeline -->
            <div class="app-timeline">
                <div class="timeline-step done">
                    <div class="timeline-icon"><i class="fas fa-paper-plane"></i></div>
                    <div class="timeline-label">Applied</div>
                    <div class="timeline-date"><?= date('d M', strtotime($a['applied_at'])) ?></div>
                </div>
                
                <div class="timeline-connector <?= !empty($a['viewed_at']) ? 'done' : '' ?>"></div>
                
                <div class="timeline-step <?= !empty($a['viewed_at']) ? 'done' : '' ?>">
                    <div class="timeline-icon"><i class="fas fa-eye"></i></div>
                    <div class="timeline-label">Viewed</div>
                    <div class="timeline-date"><?= !empty($a['viewed_at']) ? date('d M', strtotime($a['viewed_at'])) : '—' ?></div>
                </div>
                
                <div class="timeline-connector <?= !empty($a['shortlisted_at']) ? 'done' : '' ?>"></div>
                
                <div class="timeline-step <?= !empty($a['shortlisted_at']) ? 'done' : '' ?>">
                    <div class="timeline-icon"><i class="fas fa-star"></i></div>
                    <div class="timeline-label">Shortlisted</div>
                    <div class="timeline-date"><?= !empty($a['shortlisted_at']) ? date('d M', strtotime($a['shortlisted_at'])) : '—' ?></div>
                </div>
                
                <div class="timeline-connector <?= !empty($a['interview_at']) ? 'done' : '' ?>"></div>
                
                <div class="timeline-step <?= !empty($a['interview_at']) ? 'done' : '' ?>">
                    <div class="timeline-icon"><i class="fas fa-phone"></i></div>
                    <div class="timeline-label">Interview</div>
                    <div class="timeline-date"><?= !empty($a['interview_at']) ? date('d M', strtotime($a['interview_at'])) : '—' ?></div>
                </div>
                
                <div class="timeline-connector <?= (!empty($a['hired_at']) || !empty($a['rejected_at'])) ? 'done' : '' ?>"></div>
                
                <?php if (!empty($a['hired_at'])): ?>
                    <div class="timeline-step done hired">
                        <div class="timeline-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="timeline-label">Hired</div>
                        <div class="timeline-date"><?= date('d M', strtotime($a['hired_at'])) ?></div>
                    </div>
                <?php elseif (!empty($a['rejected_at'])): ?>
                    <div class="timeline-step done rejected">
                        <div class="timeline-icon"><i class="fas fa-times-circle"></i></div>
                        <div class="timeline-label">Rejected</div>
                        <div class="timeline-date"><?= date('d M', strtotime($a['rejected_at'])) ?></div>
                    </div>
                <?php else: ?>
                    <div class="timeline-step">
                        <div class="timeline-icon"><i class="fas fa-hourglass"></i></div>
                        <div class="timeline-label">Result</div>
                        <div class="timeline-date">Pending</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Interview details -->
            <?php if ($cur === 'interview' && !empty($a['interview_details'])): ?>
                <div class="interview-details-box">
                    <strong><i class="fas fa-calendar-check"></i> Interview Details:</strong>
                    <?= h($a['interview_details']) ?>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="app-card-actions">
                <a href="candidate-profile.php?id=<?= (int)$a['id'] ?>" class="action-btn-clean btn-outline-clean">
                    <i class="fas fa-user-circle"></i> View Profile
                </a>
                <?php if (!empty($a['resume_file'])): ?>
                    <a href="storage/resumes/<?= h($a['resume_file']) ?>" target="_blank" class="action-btn-clean btn-outline-clean">
                        <i class="fas fa-file-pdf"></i> CV
                    </a>
                <?php endif; ?>
                <button type="button" onclick="toggleStatusForm(<?= (int)$a['id'] ?>)" class="action-btn-clean btn-blue-clean">
                    <i class="fas fa-tasks"></i> Update Status
                </button>
                <?php if (in_array($cur, ['shortlisted', 'interview', 'hired'])): ?>
                    <a href="chat.php?app_id=<?= (int)$a['id'] ?>" class="action-btn-clean btn-green-clean">
                        <i class="fas fa-comments"></i> Chat
                    </a>
                <?php endif; ?>
            </div>

            <!-- Status update form (hidden by default) -->
            <div class="status-update-form" id="statusForm_<?= (int)$a['id'] ?>">
                <form method="POST">
                    <input type="hidden" name="app_id" value="<?= (int)$a['id'] ?>">
                    
                    <div class="form-row">
                        <div>
                            <label>New Status</label>
                            <select name="app_status" id="status_<?= (int)$a['id'] ?>" onchange="toggleInterviewFields(<?= (int)$a['id'] ?>)" required>
                                <?php if ($cur === 'pending'): ?>
                                    <option value="pending" selected>⏳ Pending (Current)</option>
                                <?php endif; ?>
                                <option value="shortlisted" <?= $cur==='shortlisted'?'selected':'' ?> <?= !$can_shortlist && $cur!=='shortlisted'?'disabled':'' ?>>⭐ Shortlist</option>
                                <option value="interview" <?= $cur==='interview'?'selected':'' ?> <?= !$can_interview && $cur!=='interview'?'disabled':'' ?>>📞 Interview</option>
                                <option value="hired" <?= $cur==='hired'?'selected':'' ?> <?= !$can_hire && $cur!=='hired'?'disabled':'' ?>>✅ Hire</option>
                                <option value="rejected" <?= $cur==='rejected'?'selected':'' ?> <?= !$can_reject && $cur!=='rejected'?'disabled':'' ?>>❌ Reject</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="interviewFields_<?= (int)$a['id'] ?>" style="display:<?= $cur==='interview' ? 'block' : 'none' ?>;">
                        <div class="form-row">
                            <div>
                                <label>Interview Date *</label>
                                <input type="date" name="interview_date" value="<?= h($a['interview_date'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
                            </div>
                            <div>
                                <label>Interview Time *</label>
                                <input type="text" name="interview_time" placeholder="e.g. 10:00 AM" value="<?= h($a['interview_time'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Interview Type</label>
                                <select name="interview_type">
                                    <option value="in_person" <?= ($a['interview_type'] ?? '')==='in_person'?'selected':'' ?>>In Person</option>
                                    <option value="phone" <?= ($a['interview_type'] ?? '')==='phone'?'selected':'' ?>>Phone Call</option>
                                    <option value="video" <?= ($a['interview_type'] ?? '')==='video'?'selected':'' ?>>Video Call</option>
                                    <option value="online" <?= ($a['interview_type'] ?? '')==='online'?'selected':'' ?>>Online Meeting</option>
                                </select>
                            </div>
                            <div>
                                <label>Location / Link</label>
                                <input type="text" name="interview_location" placeholder="Address or meeting link" value="<?= h($a['interview_location'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="action-btn-clean btn-blue-clean" style="width:100%; justify-content:center; padding:10px;">
                        <i class="fas fa-save"></i> Save & Update Status
                    </button>
                </form>
            </div>
            
        </div>
    <?php endwhile; ?>
    
    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination-clean">
        <?php if ($page > 1): ?>
            <a href="?id=<?= $job_id ?>&filter=<?= h($filter_status) ?>&q=<?= urlencode($search) ?>&sort=<?= h($sort) ?>&page=<?= $page - 1 ?>">← Prev</a>
        <?php else: ?>
            <span class="disabled">← Prev</span>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <?php if ($i === $page): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="?id=<?= $job_id ?>&filter=<?= h($filter_status) ?>&q=<?= urlencode($search) ?>&sort=<?= h($sort) ?>&page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?id=<?= $job_id ?>&filter=<?= h($filter_status) ?>&q=<?= urlencode($search) ?>&sort=<?= h($sort) ?>&page=<?= $page + 1 ?>">Next →</a>
        <?php else: ?>
            <span class="disabled">Next →</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

</div>

<script>
function toggleStatusForm(appId) {
    const form = document.getElementById('statusForm_' + appId);
    form.classList.toggle('show');
}

function toggleInterviewFields(appId) {
    const sel = document.getElementById('status_' + appId);
    const box = document.getElementById('interviewFields_' + appId);
    box.style.display = sel.value === 'interview' ? 'block' : 'none';
}
</script>

<?php include 'inc/foot.php'; ?>