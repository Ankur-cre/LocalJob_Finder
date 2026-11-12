<?php
/**
 * Cron: Job Alerts
 * Path: cron-job-alerts.php
 * 
 * Purpose: Daily/Weekly job alerts bhejna.
 * 
 * Usage:
 *   CLI:  php cron-job-alerts.php
 *   HTTP: cron-job-alerts.php?token=YOUR_SECRET_TOKEN
 * 
 * Recommended cron schedule (Linux):
 *   0 9 * * * php /path/cron-job-alerts.php        # daily at 9 AM
 *   0 9 * * 1 php /path/cron-job-alerts.php        # weekly Monday 9 AM
 * 
 * Windows Task Scheduler:
 *   Program: C:\wamp64\bin\php\php{version}\php.exe
 *   Arguments: C:\wamp64\www\local-jobs\cron-job-alerts.php
 *   Schedule: Daily at 9:00 AM
 */

// ============ SECURITY: Only CLI or with token ============
define('CRON_SECRET', 'lkj3h4k23h4lkjh234kjasdf987sdf');

$is_cli = (php_sapi_name() === 'cli');
$is_authorized = $is_cli || (isset($_GET['token']) && $_GET['token'] === CRON_SECRET);

if (!$is_authorized) {
    http_response_code(403);
    exit("Forbidden. Provide ?token=... or run via CLI.\n");
}

// ============ BOOTSTRAP ============
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/cron-logger.php';
if (file_exists(__DIR__ . '/inc/mailer.php')) require_once __DIR__ . '/inc/mailer.php';

$output = [];
function cron_out($msg) {
    global $output;
    $output[] = '[' . date('H:i:s') . '] ' . $msg;
    if (php_sapi_name() === 'cli') echo end($output) . "\n";
}

cron_out("=== Job Alerts Cron Started ===");

// ============ AUTO-CREATE REQUIRED COLUMNS ============
function cron_column_exists($db, $table, $column) {
    $table = $db->real_escape_string($table);
    $column = $db->real_escape_string($column);
    $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

try {
    // job_alerts table
    $db->query("
        CREATE TABLE IF NOT EXISTS job_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seeker_id INT NOT NULL,
            alert_name VARCHAR(150) NULL,
            keyword VARCHAR(150) NOT NULL,
            location VARCHAR(150) NULL,
            category VARCHAR(100) NULL,
            frequency ENUM('immediate','daily','weekly') NOT NULL DEFAULT 'immediate',
            is_paused TINYINT(1) NOT NULL DEFAULT 0,
            last_sent_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_seeker (seeker_id),
            INDEX idx_active (is_paused)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Add missing columns if table already existed
    $need_cols = [
        'frequency'    => "ENUM('immediate','daily','weekly') NOT NULL DEFAULT 'immediate'",
        'is_paused'    => "TINYINT(1) NOT NULL DEFAULT 0",
        'last_sent_at' => "DATETIME NULL",
        'alert_name'   => "VARCHAR(150) NULL",
        'category'     => "VARCHAR(100) NULL",
    ];
    foreach ($need_cols as $col => $def) {
        if (!cron_column_exists($db, 'job_alerts', $col)) {
            try { $db->query("ALTER TABLE job_alerts ADD COLUMN `$col` $def"); } catch (Throwable $e) {}
        }
    }

    // notifications table — needs is_read, link etc. (usually present, just safe check)
    if (!cron_column_exists($db, 'notifications', 'is_read')) {
        try { $db->query("ALTER TABLE notifications ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
    }
} catch (Throwable $e) {
    cron_out("Setup error: " . $e->getMessage());
}

// ============ START LOG ============
$log_id = cron_start($db, 'job_alerts_cron', ['trigger' => $is_cli ? 'cli' : 'http']);
cron_out("Log ID: $log_id");

// ============ DETERMINE WHICH ALERTS TO PROCESS ============
$today_day = (int)date('N'); // 1=Mon ... 7=Sun
$now_time  = date('H:i:s');

// Daily: process once a day (any time before 12 PM)
// Weekly: only on Monday
// Immediate: skip (should be handled in ajax-job-alerts.php on creation, or separately)
$where_freq = [];
$where_freq[] = "a.frequency = 'daily'";
if ($today_day === 1) { // Monday → include weekly
    $where_freq[] = "a.frequency = 'weekly'";
}

$where_sql = "(" . implode(' OR ', $where_freq) . ")";

// Also don't re-send if already sent in last 20 hours
try {
    $alerts_res = $db->query("
        SELECT a.*, u.email AS user_email, u.name AS user_name, u.id AS user_id
        FROM job_alerts a
        JOIN users u ON a.seeker_id = u.id
        WHERE a.is_paused = 0
          AND $where_sql
          AND (a.last_sent_at IS NULL OR a.last_sent_at < NOW() - INTERVAL 20 HOUR)
        ORDER BY a.id ASC
    ");
} catch (Throwable $e) {
    cron_out("Query error: " . $e->getMessage());
    cron_finish($db, $log_id, 'failed', 0, 0, $e->getMessage());
    exit;
}

if (!$alerts_res || $alerts_res->num_rows === 0) {
    cron_out("No alerts to process today.");
    cron_finish($db, $log_id, 'success', 0, 0, '');
    echo implode("\n", $output);
    exit;
}

cron_out("Found " . $alerts_res->num_rows . " alert(s) to process.");

// ============ PROCESS EACH ALERT ============
$total_processed = 0;
$total_failed    = 0;

while ($alert = $alerts_res->fetch_assoc()) {
    $alert_id = (int)$alert['id'];
    $seeker_id = (int)$alert['seeker_id'];
    $keyword   = trim($alert['keyword'] ?? '');
    $location  = trim($alert['location'] ?? '');
    $category  = trim($alert['category'] ?? '');

    cron_out("--- Alert #$alert_id (seeker $seeker_id, keyword='$keyword') ---");

    try {
        // Build job match query
        $j_where = ["j.status = 'active'"];
        $j_params = [];
        $j_types = '';

        if ($keyword !== '') {
            $j_where[] = "(j.title LIKE ? OR j.description LIKE ?)";
            $like = '%' . $keyword . '%';
            $j_params[] = $like;
            $j_params[] = $like;
            $j_types .= 'ss';
        }
        if ($location !== '') {
            $j_where[] = "(j.location LIKE ? OR j.city LIKE ?)";
            $like = '%' . $location . '%';
            $j_params[] = $like;
            $j_params[] = $like;
            $j_types .= 'ss';
        }
        if ($category !== '') {
            $j_where[] = "j.category = ?";
            $j_params[] = $category;
            $j_types .= 's';
        }

        // Only jobs newer than last_sent_at (or last 7 days if never sent)
        $since = $alert['last_sent_at'] ?: date('Y-m-d H:i:s', strtotime('-7 days'));
        $j_where[] = "j.created_at > ?";
        $j_params[] = $since;
        $j_types .= 's';

        $j_sql = "SELECT j.id, j.title, j.company_name, j.location, j.salary, j.job_type
                  FROM jobs j
                  WHERE " . implode(' AND ', $j_where) . "
                  ORDER BY j.created_at DESC
                  LIMIT 10";

        $j_stmt = $db->prepare($j_sql);
        if (!$j_stmt) throw new Exception("Job query prepare failed: " . $db->error);
        $j_stmt->bind_param($j_types, ...$j_params);
        $j_stmt->execute();
        $jobs = $j_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($jobs)) {
            cron_out("  → No matching jobs.");
            // Still update last_sent_at so it doesn't retry every run
            $db->query("UPDATE job_alerts SET last_sent_at = NOW() WHERE id = $alert_id");
            continue;
        }

        cron_out("  → Found " . count($jobs) . " matching job(s).");

        // ============ INSERT IN-APP NOTIFICATION ============
        $n_title = "New Jobs Match Your Alert";
        $n_msg   = "Hi " . ($alert['user_name'] ?: 'there') . ", " . count($jobs) . " new job(s) match your alert '" . ($alert['alert_name'] ?: $keyword) . "'.";
        $n_link  = "jobs.php?q=" . urlencode($keyword);
        try {
            $n_stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
            if ($n_stmt) {
                $n_stmt->bind_param('isss', $seeker_id, $n_title, $n_msg, $n_link);
                $n_stmt->execute();
                cron_out("  → In-app notification created.");
            }
        } catch (Throwable $e) {
            cron_out("  → Notification insert failed: " . $e->getMessage());
        }

        // ============ SEND EMAIL ============
        if (!empty($alert['user_email']) && function_exists('send_email_template')) {
            $jobs_html = '';
            foreach ($jobs as $job) {
                $jobs_html .= "<div style='border:1px solid #e5e7eb; border-left:3px solid #2563eb; padding:12px 14px; margin-bottom:10px; border-radius:6px;'>"
                            . "<h4 style='margin:0 0 4px 0; color:#111827; font-size:15px;'>" . htmlspecialchars($job['title']) . "</h4>"
                            . "<p style='margin:0; font-size:13px; color:#6b7280;'>"
                            . htmlspecialchars($job['company_name'] ?? '') 
                            . " • " . htmlspecialchars($job['location'] ?? '')
                            . (!empty($job['salary']) ? " • " . htmlspecialchars($job['salary']) : '')
                            . "</p>"
                            . "<p style='margin:8px 0 0;'><a href='" . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '') . "/local-jobs/job.php?id=" . (int)$job['id'] . "' style='font-size:13px; color:#2563eb;'>View Job →</a></p>"
                            . "</div>";
            }

            $email_result = send_email_template(
                $alert['user_email'],
                "New Jobs Matching Your Alert — " . (defined('SITE_NAME') ? SITE_NAME : 'Local Jobs'),
                "Hi " . ($alert['user_name'] ?: 'there') . ", new jobs found!",
                "<p>We found <strong>" . count($jobs) . "</strong> new job(s) matching your alert:</p>" . $jobs_html,
                'View All Matches',
                (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '') . "/local-jobs/jobs.php?q=" . urlencode($keyword)
            );
            cron_out("  → Email " . ($email_result['ok'] ? 'sent' : 'failed: ' . $email_result['msg']));
        } else {
            cron_out("  → Email skipped (no email or mailer missing).");
        }

        // ============ UPDATE last_sent_at ============
        $db->query("UPDATE job_alerts SET last_sent_at = NOW() WHERE id = $alert_id");
        $total_processed++;

    } catch (Throwable $e) {
        cron_out("  → Error: " . $e->getMessage());
        $total_failed++;
        cron_log_message($db, $log_id, "Alert #$alert_id failed: " . $e->getMessage());
    }
}

// ============ FINISH ============
$final_status = $total_failed > 0 ? ($total_processed > 0 ? 'partial' : 'failed') : 'success';
cron_finish($db, $log_id, $final_status, $total_processed, $total_failed, '');
cron_out("=== Done. Processed: $total_processed | Failed: $total_failed ===");

// Cleanup old logs (5% chance)
if (random_int(1, 20) === 1) {
    cron_cleanup_old_logs($db, 30);
    cron_out("Old logs cleaned up.");
}

// Output
if (!$is_cli) {
    header('Content-Type: text/plain; charset=utf-8');
    echo implode("\n", $output);
}