<?php
require_once 'inc/auth.php';
must_role('seeker');
$title = "Apply for Job";

$job_id = intval($_GET['job_id'] ?? $_POST['job_id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM jobs WHERE id=? AND status='active'");
$stmt->bind_param('i', $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

$err = ''; $ok = '';

if ($job && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cover = trim($_POST['cover_letter'] ?? '');

    if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
        $err = "Please upload your resume (PDF).";
    } else {
        $f = $_FILES['resume'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $err = "Only PDF files are allowed.";
        } elseif ($f['size'] > 2 * 1024 * 1024) {
            $err = "File must be under 2MB.";
        } else {
            $dir = STORAGE_PATH . 'resumes/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $newName = 'resume_' . $_SESSION['uid'] . '_' . time() . '.pdf';
            if (move_uploaded_file($f['tmp_name'], $dir . $newName)) {
                $chk = $db->prepare("SELECT id FROM applications WHERE job_id=? AND seeker_id=?");
                $chk->bind_param('ii', $job_id, $_SESSION['uid']);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $err = "You already applied for this job.";
                } else {
                    $ins = $db->prepare("INSERT INTO applications (job_id, seeker_id, resume_file, cover_letter) VALUES (?,?,?,?)");
                    $ins->bind_param('iiss', $job_id, $_SESSION['uid'], $newName, $cover);
                    if ($ins->execute()) {
                        $ok = "Application submitted successfully!";
                    } else {
                        $err = "Failed to submit application.";
                    }
                }
            } else {
                $err = "File upload failed.";
            }
        }
    }
}

include 'inc/head.php';
?>
<div class="auth-box">
    <div class="card">
    <?php if (!$job): ?>
        <div class="alert alert-err">Job not found.</div>
        <a href="index.php" class="btn-blue">← Back</a>
    <?php else: ?>
        <h2 class="mb">Apply for: <?= h($job['title']) ?></h2>
        <?php if ($err): ?><div class="alert alert-err"><?= h($err) ?></div><?php endif; ?>
        <?php if ($ok): ?>
            <div class="alert alert-ok"><?= h($ok) ?></div>
            <a href="seeker.php" class="btn-blue">View My Applications →</a>
        <?php else: ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="job_id" value="<?= $job_id ?>">
            <div class="fgroup">
                <label>Resume (PDF, max 2MB) *</label>
                <input type="file" name="resume" accept=".pdf" required>
            </div>
            <div class="fgroup">
                <label>Cover Letter (optional)</label>
                <textarea name="cover_letter" placeholder="Why are you interested?"></textarea>
            </div>
            <button type="submit" style="width:100%;">Submit Application</button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</div>
<?php include 'inc/foot.php'; ?>