<?php
/**
 * AJAX Interview Scheduling
 * Path: ajax-schedule-interview.php
 * 
 * POST params:
 *   action = schedule | reschedule | cancel | complete
 *   app_id = application ID
 *   interview_date, interview_time, interview_type, interview_location (for schedule/reschedule)
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
$action = $_POST['action'] ?? '';

if (!$app_id) {
    echo json_encode(['ok' => false, 'msg' => 'Application ID missing']);
    exit;
}

// ============ HELPER ============
if (!function_exists('int_col_exists')) {
    function int_col_exists($db, $table, $col) {
        $t = $db->real_escape_string($table);
        $c = $db->real_escape_string($col);
        $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return $r && $r->num_rows > 0;
    }
}

// Auto-create interviews table for audit
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS interviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            employer_id INT NOT NULL,
            seeker_id INT NOT NULL,
            job_id INT NOT NULL,
            interview_date DATE NOT NULL,
            interview_time VARCHAR(50) NOT NULL,
            interview_type VARCHAR(30) NOT NULL DEFAULT 'in_person',
            interview_location TEXT NULL,
            notes TEXT NULL,
            status ENUM('scheduled','rescheduled','completed','cancelled') DEFAULT 'scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_app (application_id),
            INDEX idx_emp (employer_id)
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

$seeker_id = (int)$app['seeker_id'];
$job_id = (int)$app['job_id'];
$job_title = $app['job_title'];
$current_status = $app['status'];

$msg = '';
$notif_msg = '';

try {
    switch ($action) {
        
        case 'schedule':
        case 'reschedule':
            $date = trim($_POST['interview_date'] ?? '');
            $time = trim($_POST['interview_time'] ?? '');
            $type = trim($_POST['interview_type'] ?? 'in_person');
            $location = trim($_POST['interview_location'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            if ($date === '' || $time === '') {
                echo json_encode(['ok' => false, 'msg' => 'Date and time are required']);
                exit;
            }
            
            if (strtotime($date) < strtotime('today')) {
                echo json_encode(['ok' => false, 'msg' => 'Interview date cannot be in the past']);
                exit;
            }
            
            $type_labels = ['in_person' => 'In Person', 'phone' => 'Phone Call', 'video' => 'Video Call', 'online' => 'Online Meeting'];
            $type_label = $type_labels[$type] ?? 'In Person';
            $details = "📅 " . date('d M Y', strtotime($date)) . " • ⏰ $time • 📍 $type_label"
                     . (!empty($location) ? " • $location" : '');
            
            // Update application
            $u = $db->prepare("
                UPDATE applications 
                SET status = 'interview', interview_details = ?, interview_date = ?, 
                    interview_time = ?, interview_type = ?, interview_location = ?
                WHERE id = ?
            ");
            $u->bind_param('sssssi', $details, $date, $time, $type, $location, $app_id);
            $u->execute();
            
            if (int_col_exists($db, 'applications', 'interview_at')) {
                $db->query("UPDATE applications SET interview_at = NOW() WHERE id = $app_id");
            }
            
            // Log in interviews table
            $iv = $db->prepare("
                INSERT INTO interviews (application_id, employer_id, seeker_id, job_id, interview_date, interview_time, interview_type, interview_location, notes, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $iv_status = ($action === 'reschedule') ? 'rescheduled' : 'scheduled';
            $iv->bind_param('iiiissssss', $app_id, $uid, $seeker_id, $job_id, $date, $time, $type, $location, $notes, $iv_status);
            $iv->execute();
            
            // Log status history
            try {
                $log = $db->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, changed_by_role, notes) VALUES (?, ?, 'interview', ?, 'employer', ?)");
                $log->bind_param('isis', $app_id, $current_status, $uid, $details);
                $log->execute();
            } catch (Throwable $e) {}
            
            $msg = ($action === 'reschedule') ? 'Interview rescheduled!' : 'Interview scheduled!';
            $notif_msg = ($action === 'reschedule')
                ? "Interview RESCHEDULED for '$job_title'. $details"
                : "Interview SCHEDULED for '$job_title'. $details";
            
            // Notify seeker
            $n_title = "Interview Update";
            $n_link = "seeker.php";
            $ns = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
            $ns->bind_param('isss', $seeker_id, $n_title, $notif_msg, $n_link);
            $ns->execute();
            
            echo json_encode(['ok' => true, 'msg' => $msg, 'interview_details' => $details]);
            exit;
        
        case 'cancel':
            $reason = trim($_POST['notes'] ?? 'Interview cancelled by employer.');
            
            // Update application back to shortlisted
            $u = $db->prepare("UPDATE applications SET status = 'shortlisted', interview_details = NULL WHERE id = ?");
            $u->bind_param('i', $app_id);
            $u->execute();
            
            // Mark interview cancelled
            $iv = $db->prepare("UPDATE interviews SET status = 'cancelled' WHERE application_id = ? AND status IN ('scheduled', 'rescheduled')");
            $iv->bind_param('i', $app_id);
            $iv->execute();
            
            // Notify
            $n_title = "Interview Cancelled";
            $n_msg = "Interview for '$job_title' has been cancelled. Reason: $reason";
            $n_link = "seeker.php";
            $ns = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
            $ns->bind_param('isss', $seeker_id, $n_title, $n_msg, $n_link);
            $ns->execute();
            
            echo json_encode(['ok' => true, 'msg' => 'Interview cancelled.']);
            exit;
        
        case 'complete':
            $iv = $db->prepare("UPDATE interviews SET status = 'completed' WHERE application_id = ? AND status IN ('scheduled', 'rescheduled')");
            $iv->bind_param('i', $app_id);
            $iv->execute();
            echo json_encode(['ok' => true, 'msg' => 'Interview marked as completed.']);
            exit;
        
        default:
            echo json_encode(['ok' => false, 'msg' => 'Invalid action']);
            exit;
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}