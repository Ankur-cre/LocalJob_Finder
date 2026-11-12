<?php
/**
 * AJAX Job Status Management
 * Path: ajax-job-status.php
 * 
 * POST params:
 *   action = pause | resume | close | reopen | duplicate | delete
 *   job_id = ID of the job
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
$job_id = (int)($_POST['job_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$job_id) {
    echo json_encode(['ok' => false, 'msg' => 'Job ID missing']);
    exit;
}

// Verify ownership
$stmt = $db->prepare("SELECT * FROM jobs WHERE id = ? AND employer_id = ?");
$stmt->bind_param('ii', $job_id, $uid);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    echo json_encode(['ok' => false, 'msg' => 'Job not found or unauthorized']);
    exit;
}

// Auto-create columns
if (!function_exists('ajax_col_exists')) {
    function ajax_col_exists($db, $table, $col) {
        $t = $db->real_escape_string($table);
        $c = $db->real_escape_string($col);
        $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return $r && $r->num_rows > 0;
    }
}
try {
    if (!ajax_col_exists($db, 'jobs', 'paused_at')) {
        $db->query("ALTER TABLE jobs ADD COLUMN paused_at DATETIME NULL");
    }
    if (!ajax_col_exists($db, 'jobs', 'closed_at')) {
        $db->query("ALTER TABLE jobs ADD COLUMN closed_at DATETIME NULL");
    }
} catch (Throwable $e) {}

$current_status = $job['status'];
$msg = '';
$new_status = '';

try {
    switch ($action) {
        
        case 'pause':
            if ($current_status !== 'active') {
                echo json_encode(['ok' => false, 'msg' => 'Only active jobs can be paused']);
                exit;
            }
            $new_status = 'paused';
            $u = $db->prepare("UPDATE jobs SET status = ?, paused_at = NOW() WHERE id = ?");
            $u->bind_param('si', $new_status, $job_id);
            $u->execute();
            $msg = 'Job paused. It is now hidden from job seekers.';
            break;
        
        case 'resume':
            if (!in_array($current_status, ['paused', 'closed'])) {
                echo json_encode(['ok' => false, 'msg' => 'Only paused/closed jobs can be resumed']);
                exit;
            }
            $new_status = 'active';
            $u = $db->prepare("UPDATE jobs SET status = ?, paused_at = NULL, closed_at = NULL WHERE id = ?");
            $u->bind_param('si', $new_status, $job_id);
            $u->execute();
            $msg = 'Job resumed! It is now visible to job seekers again.';
            break;
        
        case 'close':
            if (!in_array($current_status, ['active', 'paused'])) {
                echo json_encode(['ok' => false, 'msg' => 'Job cannot be closed']);
                exit;
            }
            $new_status = 'closed';
            $u = $db->prepare("UPDATE jobs SET status = ?, closed_at = NOW() WHERE id = ?");
            $u->bind_param('si', $new_status, $job_id);
            $u->execute();
            $msg = 'Job closed. You can duplicate it to post a new one.';
            break;
        
        case 'reopen':
            if ($current_status !== 'closed') {
                echo json_encode(['ok' => false, 'msg' => 'Only closed jobs can be reopened']);
                exit;
            }
            $new_status = 'active';
            $u = $db->prepare("UPDATE jobs SET status = ?, closed_at = NULL WHERE id = ?");
            $u->bind_param('si', $new_status, $job_id);
            $u->execute();
            $msg = 'Job reopened! It is now visible to job seekers.';
            break;
        
        case 'duplicate':
            $new_title = $job['title'] . ' (Copy)';
            $u = $db->prepare("
                INSERT INTO jobs (
                    employer_id, title, description, category, location, city, district, state,
                    salary, job_type, contact_phone, vacancies, status,
                    is_walkin, walkin_date, walkin_time, fresher_allowed
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)
            ");
            $u->bind_param('issssssssssiisi',
                $uid, $new_title, $job['description'], $job['category'],
                $job['location'], $job['city'], $job['district'], $job['state'],
                $job['salary'], $job['job_type'], $job['contact_phone'], $job['vacancies'],
                $job['is_walkin'], $job['walkin_date'], $job['walkin_time'], $job['fresher_allowed']
            );
            $u->execute();
            $new_id = $db->insert_id;
            echo json_encode(['ok' => true, 'msg' => 'Job duplicated! New copy is pending approval.', 'new_job_id' => $new_id]);
            exit;
        
        case 'delete':
            if (!in_array($current_status, ['pending', 'draft'])) {
                echo json_encode(['ok' => false, 'msg' => 'Only draft/pending jobs can be deleted. Close the job instead.']);
                exit;
            }
            $d = $db->prepare("DELETE FROM jobs WHERE id = ? AND employer_id = ?");
            $d->bind_param('ii', $job_id, $uid);
            $d->execute();
            echo json_encode(['ok' => true, 'msg' => 'Job deleted permanently.']);
            exit;
        
        default:
            echo json_encode(['ok' => false, 'msg' => 'Invalid action']);
            exit;
    }
    
    echo json_encode(['ok' => true, 'msg' => $msg, 'new_status' => $new_status]);
    
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}