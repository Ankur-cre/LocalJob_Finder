<?php
require_once 'inc/auth.php';
must_role('seeker');
$title = "Job Alerts — " . SITE_NAME;
$uid = $_SESSION['uid'];
$msg = '';

// Add Alert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_alert'])) {
    $kw = trim($_POST['keyword']);
    $loc = trim($_POST['location']);
    $type = $_POST['job_type'];
    $db->query("INSERT INTO job_alerts (seeker_id, keyword, location, job_type) VALUES ($uid, '$kw', '$loc', '$type')");
    $msg = "Job alert created! We'll notify you when new jobs match.";
}

// Delete Alert
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $db->query("DELETE FROM job_alerts WHERE id=$id AND seeker_id=$uid");
    header("Location: seeker-alerts.php?msg=" . urlencode("Alert deleted."));
    exit;
}

$alerts = $db->query("SELECT * FROM job_alerts WHERE seeker_id=$uid ORDER BY created_at DESC");

include 'inc/head.php';
?>

<div style="background: var(--gray-bg); padding: 30px 0;">
    <div class="wrap" style="max-width: 900px;">
        <h1 style="font-size: 1.6rem; margin-bottom: 20px;"><i class="fas fa-bell" style="color:var(--amber);"></i> Job Alerts</h1>
        
        <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>

        <div class="card" style="margin-bottom: 20px;">
            <h3 style="font-size: 1.1rem; margin-bottom: 15px;">Create New Alert</h3>
            <form method="POST" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <input type="text" name="keyword" placeholder="Job Title / Skill (e.g. Driver)" required style="flex:1; padding:10px; border:1px solid #d1d5db; border-radius:6px;">
                <input type="text" name="location" placeholder="City (e.g. Nichlaul)" style="flex:1; padding:10px; border:1px solid #d1d5db; border-radius:6px;">
                <select name="job_type" style="padding:10px; border:1px solid #d1d5db; border-radius:6px;">
                    <option value="any">Any Type</option>
                    <option value="full-time">Full-Time</option>
                    <option value="part-time">Part-Time</option>
                </select>
                <button type="submit" name="add_alert" class="btn-blue">Create Alert</button>
            </form>
        </div>

        <div class="card">
            <h3 style="font-size: 1.1rem; margin-bottom: 15px;">Active Alerts</h3>
            <?php if ($alerts->num_rows === 0): ?>
                <p style="color: var(--gray);">You have no active job alerts.</p>
            <?php else: ?>
                <table style="width:100%; border-collapse: collapse;">
                    <thead><tr style="text-align:left; border-bottom:2px solid #eee;"><th>Keyword</th><th>Location</th><th>Type</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php while($a = $alerts->fetch_assoc()): ?>
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:12px;"><?= h($a['keyword']) ?></td>
                            <td style="padding:12px;"><?= h($a['location']) ?></td>
                            <td style="padding:12px;"><?= ucfirst($a['job_type']) ?></td>
                            <td style="padding:12px;"><a href="?del=<?= $a['id'] ?>" class="btn-sm btn-red" onclick="return confirm('Delete?');">Delete</a></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'inc/foot.php'; ?>