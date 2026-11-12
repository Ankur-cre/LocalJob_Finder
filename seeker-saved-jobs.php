<?php
require_once 'inc/auth.php';
must_role('seeker');
$title = "Saved Jobs — " . SITE_NAME;
$uid = $_SESSION['uid'];

// Fetch saved jobs
$saved = $db->prepare("
    SELECT j.*, u.company_name, s.created_at as saved_on
    FROM saved_jobs s
    JOIN jobs j ON s.job_id = j.id
    JOIN users u ON j.employer_id = u.id
    WHERE s.seeker_id = ? AND j.status = 'active'
    ORDER BY s.created_at DESC
");
$saved->bind_param('i', $uid);
$saved->execute();
$res = $saved->get_result();

include 'inc/head.php';
?>

<div style="background: var(--gray-bg); padding: 30px 0;">
    <div class="wrap">
        <h1 style="font-size: 1.6rem; margin-bottom: 20px;"><i class="fas fa-bookmark" style="color:var(--blue);"></i> My Saved Jobs</h1>
        
        <?php if ($res->num_rows === 0): ?>
            <div class="card empty-state">
                <i class="fas fa-bookmark"></i>
                <h3>No saved jobs yet</h3>
                <p>Save jobs from the Browse Jobs page to view them here.</p>
                <a href="jobs.php" class="btn-blue" style="margin-top: 15px;">Browse Jobs</a>
            </div>
        <?php else: ?>
            <div class="jobs-grid">
                <?php while ($j = $res->fetch_assoc()): ?>
                    <article class="job-tile">
                        <h3 class="job-tile-title"><?= h($j['title']) ?></h3>
                        <p class="job-tile-company"><i class="fas fa-building"></i> <?= h($j['company_name']) ?></p>
                        <div class="job-tile-pills">
                            <span class="info-pill"><i class="fas fa-map-marker-alt"></i> <?= h($j['location']) ?></span>
                            <span class="info-pill"><i class="fas fa-money-bill"></i> <?= h($j['salary'] ?: 'N/A') ?></span>
                        </div>
                        <div class="job-tile-footer">
                            <span class="job-tile-date">Saved: <?= date('d M', strtotime($j['saved_on'])) ?></span>
                            <a href="job.php?id=<?= $j['id'] ?>" class="btn-blue btn-sm">View Job</a>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'inc/foot.php'; ?>