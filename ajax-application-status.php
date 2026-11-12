<?php
/**
 * AJAX Application Status Change
 * Path: ajax-application-status.php
 * 
 * POST params:
 *   app_id = application ID
 *   new_status = pending | shortlisted | interview | hired | rejected
 *   interview_date, interview_time, interview_type, interview_location (if interview)
 *   notes = optional
 * 
 * Returns JSON
 */

require_once 'inc/auth.php';
header('Content-Type: application/json; charset=utf-8');

if (!logged_in() || role() !== 'employer') {
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Invalid method']);
    exit;
}

$uid = (int)$_SESSION['uid'];
$app_id = (int)($_POST['app_id'] ?? 0);
$new_status = $_POST['new_status'] ?? '';
$allowed = ['pending', 'shortlisted', 'interview', 'hired', 'rejected'];

if (!$app_id) {
    echo json_encode(['ok' => false, 'msg' => 'Application ID missing']);
    exit;
}

if (!in_array($new_status, $allowed)) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid status']);
    exit;
}

// ============ HELPER ============
if (!function_exists('ajax_col_exists2')) {
    function ajax_col_exists2($db, $table, $col) {
        $t = $db->real_escape_string($table);
        $c = $db->real_escape_string($col);
        $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return $r && $r->num_rows > 0;
    }
}

// Auto-create needed columns
try {
    foreach ([
        'interview_date'     => "DATE NULL",
        'interview_time'     => "VARCHAR(50) NULL",
        'interview_type'     => "VARCHAR(30) NULL",
        'interview_location' => "TEXT NULL",
        'interview_details'  => "TEXT NULL",
        'notes'              => "TEXT NULL",
    ] as $col => $def) {
        if (!ajax_col_exists2($db, 'applications', $col)) {
            $db->query("ALTER TABLE applications ADD COLUMN `$col` $def");
        }
    }
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

// ============ FETCH APPLICATION ============
$stmt = $db->prepare("
    SELECT a.*, j.title AS job_title, j.id AS job_id, su.name AS seeker_name
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users su ON a.seeker_id = su.id
    WHERE a.id = ? AND j.employer_id = ?
");
$stmt->bind_param('ii', $app_id, $uid);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    echo json_encode(['ok' => false, 'msg' => 'Application not found or unauthorized']);
    exit;
}

$current_status = $app['status'];
$seeker_id = $app['seeker_id'];
$job_title = $app['job_title'];

// ============ WORKFLOW VALIDATION ============
if ($new_status === 'interview' && !in_array($current_status, ['shortlisted', 'interview'])) {
    echo json_encode(['ok' => false, 'msg' => 'Candidate ko pehle shortlist karo, phir interview schedule karo.']);
    exit;
}
if ($new_status === 'hired' && !in_array($current_status, ['interview', 'shortlisted', 'hired'])) {
    echo json_encode(['ok' => false, 'msg' => 'Direct hire nahi kar sakte. Pehle shortlist ya interview karo.']);
    exit;
}
if ($new_status === 'pending' && $current_status !== 'pending') {
    echo json_encode(['ok' => false, 'msg' => 'Already processed candidate ko pending mein nahi daal sakte.']);
    exit;
}

// ============ INTERVIEW DETAILS ============
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
        echo json_encode(['ok' => false, 'msg' => 'Interview date aur time zaroor bharo.']);
        exit;
    }
    
    $type_labels = ['in_person' => 'In Person', 'phone' => 'Phone Call', 'video' => 'Video Call', 'online' => 'Online Meeting'];
    $type_label = $type_labels[$interview_type] ?? 'In Person';
    $interview_details = "📅 " . date('d M Y', strtotime($interview_date))
                       . " • ⏰ " . $interview_time
                       . " • 📍 " . $type_label
                       . (!empty($interview_location) ? " • " . $interview_location : '');
}

$notes = trim($_POST['notes'] ?? '');

// ============ UPDATE ============
try {
    $u = $db->prepare("
        UPDATE applications 
        SET status = ?, interview_details = ?, interview_date = ?, interview_time = ?, interview_type = ?, interview_location = ?, notes = ?
        WHERE id = ?
    ");
    $u->bind_param('sssssssi', $new_status, $interview_details, $interview_date, $interview_time, $interview_type, $interview_location, $notes, $app_id);
    $u->execute();
    
    // Set timestamp
    $tf = '';
    if ($new_status === 'shortlisted') $tf = 'shortlisted_at';
    elseif ($new_status === 'interview') $tf = 'interview_at';
    elseif ($new_status === 'hired') $tf = 'hired_at';
    elseif ($new_status === 'rejected') $tf = 'rejected_at';
    if ($tf && ajax_col_exists2($db, 'applications', $tf)) {
        $db->query("UPDATE applications SET $tf = NOW() WHERE id = $app_id");
    }
    
    // Log history
    $log = $db->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, changed_by_role, notes) VALUES (?, ?, ?, ?, 'employer', ?)");
    $log->bind_param('issss', $app_id, $current_status, $new_status, $uid, $interview_details ?: $notes);
    $log->execute();
    
    // Notify seeker
    $n_title = "Application Update";
    if ($new_status === 'interview') $n_msg = "Interview scheduled for '$job_title'. $interview_details";
    elseif ($new_status === 'hired') $n_msg = "🎉 Congratulations! You have been HIRED for '$job_title'.";
    elseif ($new_status === 'shortlisted') $n_msg = "Great news! You have been shortlisted for '$job_title'.";
    elseif ($new_status === 'rejected') $n_msg = "Your application for '$job_title' was not selected.";
    else $n_msg = "Your application status for '$job_title' updated to " . ucfirst($new_status);
    
    $n_link = "seeker.php";
    $ns = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
    $ns->bind_param('isss', $seeker_id, $n_title, $n_msg, $n_link);
    $ns->execute();
    
    echo json_encode([
        'ok' => true,
        'msg' => 'Status updated to ' . ucfirst($new_status),
        'new_status' => $new_status,
        'interview_details' => $interview_details,
    ]);
    
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}