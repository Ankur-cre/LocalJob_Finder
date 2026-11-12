<?php
/**
 * Cron Logger Helper
 * Path: inc/cron-logger.php
 * 
 * Cron scripts ke run history ko track karta hai — success/fail, items processed,
 * error message, duration. Admin panel isse dekh sakta hai.
 */

if (!function_exists('cron_ensure_table')) {
    function cron_ensure_table($db) {
        try {
            $db->query("
                CREATE TABLE IF NOT EXISTS cron_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    cron_name VARCHAR(100) NOT NULL,
                    status ENUM('running','success','failed','partial') NOT NULL DEFAULT 'running',
                    items_processed INT NOT NULL DEFAULT 0,
                    items_failed INT NOT NULL DEFAULT 0,
                    error_msg TEXT NULL,
                    meta JSON NULL,
                    started_at DATETIME NOT NULL,
                    finished_at DATETIME NULL,
                    duration_sec INT NULL,
                    INDEX idx_cron (cron_name),
                    INDEX idx_status (status),
                    INDEX idx_started (started_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (Throwable $e) {
            error_log("cron_ensure_table failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('cron_start')) {
    /**
     * Start a cron run — returns log ID.
     */
    function cron_start($db, $cron_name, $meta = []) {
        cron_ensure_table($db);
        try {
            $meta_json = !empty($meta) ? json_encode($meta) : null;
            $stmt = $db->prepare("INSERT INTO cron_logs (cron_name, status, meta, started_at) VALUES (?, 'running', ?, NOW())");
            $stmt->bind_param('ss', $cron_name, $meta_json);
            $stmt->execute();
            return (int)$db->insert_id;
        } catch (Throwable $e) {
            error_log("cron_start failed: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('cron_finish')) {
    /**
     * Finish a cron run — updates status, counts, duration.
     * 
     * @param string $status success | failed | partial
     */
    function cron_finish($db, $log_id, $status = 'success', $processed = 0, $failed = 0, $error = '') {
        if (!$log_id) return;
        try {
            $error_val = $error !== '' ? $error : null;
            $stmt = $db->prepare("
                UPDATE cron_logs 
                SET status=?, items_processed=?, items_failed=?, error_msg=?, 
                    finished_at=NOW(), duration_sec=TIMESTAMPDIFF(SECOND, started_at, NOW())
                WHERE id=?
            ");
            $stmt->bind_param('siisi', $status, $processed, $failed, $error_val, $log_id);
            $stmt->execute();
        } catch (Throwable $e) {
            error_log("cron_finish failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('cron_log_message')) {
    /**
     * Append a message to a cron's meta (mid-run).
     */
    function cron_log_message($db, $log_id, $message) {
        if (!$log_id) return;
        try {
            $stmt = $db->prepare("UPDATE cron_logs SET error_msg = CONCAT(COALESCE(error_msg, ''), ?) WHERE id=?");
            $line = '[' . date('H:i:s') . '] ' . $message . "\n";
            $stmt->bind_param('si', $line, $log_id);
            $stmt->execute();
        } catch (Throwable $e) {
            error_log("cron_log_message failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('cron_cleanup_old_logs')) {
    function cron_cleanup_old_logs($db, $days = 30) {
        try {
            $db->query("DELETE FROM cron_logs WHERE started_at < NOW() - INTERVAL $days DAY");
        } catch (Throwable $e) {
            error_log("cron_cleanup_old_logs failed: " . $e->getMessage());
        }
    }
}