<?php
require_once 'inc/auth.php';

// Ensure logged in as seeker
if (!logged_in() || role() !== 'seeker') {
    http_response_code(403);
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    } else {
        header("Location: login.php");
    }
    exit;
}

$uid = (int)$_SESSION['uid'];
$action = $_POST['action'] ?? '';
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// ============================================
// HELPER — Safe column check
// ============================================
if (!function_exists('column_exists')) {
    function column_exists($db, $table, $column) {
        $table  = $db->real_escape_string($table);
        $column = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && $res->num_rows > 0;
    }
}

// ============================================
// AUTO-CREATE job_alerts TABLE + COLUMNS
// ============================================
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS job_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seeker_id INT NOT NULL,
            keyword VARCHAR(150) NOT NULL,
            location VARCHAR(150) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_seeker (seeker_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $needed_cols = [
        'alert_name'  => "VARCHAR(150) NULL",
        'category'    => "VARCHAR(100) NULL",
        'frequency'   => "ENUM('immediate','daily','weekly') NOT NULL DEFAULT 'immediate'",
        'status'      => "ENUM('active','paused') NOT NULL DEFAULT 'active'",
        'is_paused'   => "TINYINT(1) NOT NULL DEFAULT 0",
        'last_sent_at'=> "DATETIME NULL",
    ];
    foreach ($needed_cols as $col => $def) {
        if (!column_exists($db, 'job_alerts', $col)) {
            try { $db->query("ALTER TABLE job_alerts ADD COLUMN `$col` $def"); } catch (Throwable $e) {}
        }
    }
} catch (Throwable $e) {
    error_log("job_alerts auto-init failed: " . $e->getMessage());
}

// ============================================
// HELPER — Response
// ============================================
function respond_ajax($ok, $message, $redirect = '') {
    global $is_ajax;
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status'   => $ok ? 'success' : 'error',
            'message'  => $message,
            'redirect' => $redirect ?: 'seeker-settings.php?tab=alerts',
        ]);
    } else {
        if ($ok) {
            $_SESSION['flash_msg'] = $message;
        } else {
            $_SESSION['flash_msg'] = $message;
        }
        header("Location: " . ($redirect ?: 'seeker-settings.php?tab=alerts'));
    }
    exit;
}

// ============================================
// 1. CREATE ALERT
// ============================================
if ($action === 'create') {
    $alert_name = trim($_POST['alert_name'] ?? '');
    $keyword    = trim($_POST['keyword'] ?? '');
    $location   = trim($_POST['location'] ?? '');
    $category   = trim($_POST['category'] ?? '');
    $frequency  = $_POST['frequency'] ?? 'immediate';

    if (!in_array($frequency, ['immediate', 'daily', 'weekly'])) $frequency = 'immediate';

    if ($keyword === '') {
        respond_ajax(false, "Keyword is required to create an alert.");
    }

    // Build dynamic insert based on existing columns
    $cols = ['seeker_id', 'keyword', 'location', 'created_at'];
    $vals = ['?', '?', '?', 'NOW()'];
    $types = 'iss';
    $params = [$uid, $keyword, $location];

    if (column_exists($db, 'job_alerts', 'alert_name')) {
        $cols[] = 'alert_name'; $vals[] = '?'; $types .= 's'; $params[] = $alert_name;
    }
    if (column_exists($db, 'job_alerts', 'category')) {
        $cols[] = 'category'; $vals[] = '?'; $types .= 's'; $params[] = $category;
    }
    if (column_exists($db, 'job_alerts', 'frequency')) {
        $cols[] = 'frequency'; $vals[] = '?'; $types .= 's'; $params[] = $frequency;
    }
    if (column_exists($db, 'job_alerts', 'status')) {
        $cols[] = 'status'; $vals[] = "'active'";
    }

    try {
        $sql = "INSERT INTO job_alerts (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
        $stmt = $db->prepare($sql);
        if (!$stmt) throw new Exception($db->error);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            respond_ajax(true, "Job Alert created successfully!");
        } else {
            respond_ajax(false, "Failed to create alert: " . $stmt->error);
        }
    } catch (Throwable $e) {
        respond_ajax(false, "Failed to create alert: " . $e->getMessage());
    }
}

// ============================================
// 2. DELETE ALERT
// ============================================
if ($action === 'delete') {
    $alert_id = (int)($_POST['alert_id'] ?? 0);
    if ($alert_id <= 0) respond_ajax(false, "Invalid alert ID.");

    $stmt = $db->prepare("DELETE FROM job_alerts WHERE id = ? AND seeker_id = ?");
    $stmt->bind_param('ii', $alert_id, $uid);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        respond_ajax(true, "Job Alert deleted successfully.");
    } else {
        respond_ajax(false, "Alert not found or already deleted.");
    }
}

// ============================================
// 3. PAUSE / RESUME ALERT
// ============================================
if ($action === 'toggle_status') {
    $alert_id = (int)($_POST['alert_id'] ?? 0);
    $new_status = $_POST['status'] ?? 'active';
    if (!in_array($new_status, ['active', 'paused'])) $new_status = 'active';
    if ($alert_id <= 0) respond_ajax(false, "Invalid alert ID.");

    // Prefer status column, fallback to is_paused
    $has_status = column_exists($db, 'job_alerts', 'status');
    $has_paused = column_exists($db, 'job_alerts', 'is_paused');

    try {
        if ($has_status) {
            $stmt = $db->prepare("UPDATE job_alerts SET status = ? WHERE id = ? AND seeker_id = ?");
            $stmt->bind_param('sii', $new_status, $alert_id, $uid);
        } elseif ($has_paused) {
            $is_p = ($new_status === 'paused') ? 1 : 0;
            $stmt = $db->prepare("UPDATE job_alerts SET is_paused = ? WHERE id = ? AND seeker_id = ?");
            $stmt->bind_param('iii', $is_p, $alert_id, $uid);
        } else {
            respond_ajax(false, "Status field not available in database.");
        }

        if ($stmt->execute()) {
            respond_ajax(true, "Alert status updated to " . ucfirst($new_status) . ".");
        } else {
            respond_ajax(false, "Failed to update alert status.");
        }
    } catch (Throwable $e) {
        respond_ajax(false, "Error: " . $e->getMessage());
    }
}

// ============================================
// 4. UPDATE ALERT (Edit)
// ============================================
if ($action === 'update') {
    $alert_id   = (int)($_POST['alert_id'] ?? 0);
    $alert_name = trim($_POST['alert_name'] ?? '');
    $keyword    = trim($_POST['keyword'] ?? '');
    $location   = trim($_POST['location'] ?? '');
    $frequency  = $_POST['frequency'] ?? 'immediate';
    if (!in_array($frequency, ['immediate', 'daily', 'weekly'])) $frequency = 'immediate';

    if ($alert_id <= 0 || $keyword === '') {
        respond_ajax(false, "Alert ID and keyword are required.");
    }

    // Build dynamic set
    $set_parts = ['keyword = ?', 'location = ?'];
    $types = 'ss';
    $params = [$keyword, $location];

    if (column_exists($db, 'job_alerts', 'alert_name')) {
        $set_parts[] = 'alert_name = ?'; $types .= 's'; $params[] = $alert_name;
    }
    if (column_exists($db, 'job_alerts', 'frequency')) {
        $set_parts[] = 'frequency = ?'; $types .= 's'; $params[] = $frequency;
    }

    $types .= 'ii';
    $params[] = $alert_id;
    $params[] = $uid;

    try {
        $sql = "UPDATE job_alerts SET " . implode(', ', $set_parts) . " WHERE id = ? AND seeker_id = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) throw new Exception($db->error);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            respond_ajax(true, "Job Alert updated successfully.");
        } else {
            respond_ajax(false, "Failed to update alert.");
        }
    } catch (Throwable $e) {
        respond_ajax(false, "Error: " . $e->getMessage());
    }
}

// Default
respond_ajax(false, "Invalid action.");