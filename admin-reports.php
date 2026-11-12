<?php
// ============================================
// STEP 1: PROCESS POST BEFORE ANY HTML
// ============================================
require_once 'inc/auth.php';
must_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_report'])) {
    $report_id = (int)$_POST['report_id'];
    $target_id = (int)$_POST['target_user'];
    $msg = '';
    
    if ($_POST['action_report'] === 'suspend') {
        $db->query("UPDATE users SET status='rejected' WHERE id=$target_id");
        $db->query("UPDATE job_reports SET status='resolved' WHERE id=$report_id");
        $msg = "User suspended based on report.";
    } elseif ($_POST['action_report'] === 'block') {
        $db->query("DELETE FROM users WHERE id=$target_id");
        $db->query("UPDATE job_reports SET status='resolved' WHERE id=$report_id");
        $msg = "User blocked and deleted permanently.";
    } elseif ($_POST['action_report'] === 'dismiss') {
        $db->query("UPDATE job_reports SET status='dismissed' WHERE id=$report_id");
        $msg = "Report dismissed (User is safe).";
    }
    header("Location: admin-reports.php?msg=" . urlencode($msg));
    exit;
}

// ============================================
// STEP 2: NOW LOAD HTML HEADER
// ============================================
$title = "Reports & Moderation";
require_once 'inc/admin-head.php';
?>

<h2 class="page-title">Reports & Moderation</h2>
<div class="card" style="padding:0; overflow:hidden;">
    <table class="admin-table">
        <thead><tr><th>Reported User</th><th>Reason</th><th>Description</th><th>Action</th></tr></thead>
        <tbody>
        <?php 
        $reports = $db->query("
            SELECT r.*, u.name AS target_name, u.email AS target_email
            FROM job_reports r LEFT JOIN users u ON r.employer_id = u.id
            WHERE r.status = 'pending' ORDER BY r.created_at DESC
        ");
        if($reports->num_rows == 0) echo '<tr><td colspan="4" style="text-align:center;">No pending reports.</td></tr>';
        while ($r = $reports->fetch_assoc()): 
            $target_id = $r['employer_id'] ?? 0;
        ?>
            <tr>
                <td><strong><?= h($r['target_name'] ?? 'Unknown') ?></strong><br><small style="color:var(--text-muted);"><?= h($r['target_email'] ?? '') ?></small></td>
                <td><strong style="color:var(--accent-red);"><?= h($r['reason']) ?></strong></td>
                <td><small><?= h($r['description']) ?></small></td>
                <td>
                    <div style="display:flex; gap:5px;">
                        <form method="POST" onsubmit="return confirm('Suspend user?');"><input type="hidden" name="report_id" value="<?= $r['id'] ?>"><input type="hidden" name="target_user" value="<?= $target_id ?>"><button type="submit" name="action_report" value="suspend" class="btn-sm btn-red">Suspend</button></form>
                        <form method="POST"><input type="hidden" name="report_id" value="<?= $r['id'] ?>"><button type="submit" name="action_report" value="dismiss" class="btn-sm btn-gray">Dismiss</button></form>
                    </div>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include 'inc/foot.php'; ?>