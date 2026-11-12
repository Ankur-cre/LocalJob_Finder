<?php
require_once 'inc/auth.php';
must_role('employer');
$title = "Manage Job — " . SITE_NAME;
$uid = $_SESSION['uid'];

$job_id = (int)($_GET['id'] ?? 0);
if (!$job_id) { header("Location: employer.php"); exit; }

// Fetch job & ensure it belongs to this employer
$stmt = $db->prepare("SELECT * FROM jobs WHERE id=? AND employer_id=?");
$stmt->bind_param('ii', $job_id, $uid);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    header("Location: employer.php?msg=" . urlencode("Job not found or unauthorized."));
    exit;
}

$msg = '';
$error = '';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_job'])) {
    $title_j = trim($_POST['title'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $cat     = trim($_POST['category'] ?? '');
    $loc     = trim($_POST['location'] ?? '');
    $salary  = trim($_POST['salary'] ?? '');
    $type    = trim($_POST['job_type'] ?? 'full-time');
    $phone   = trim($_POST['contact_phone'] ?? '');
    $vacancy = (int)($_POST['vacancies'] ?? 1);

    if ($title_j === '' || $desc === '' || $cat === '' || $loc === '' || $phone === '') {
        $error = "Please fill all required fields.";
    } else {
        $upd = $db->prepare("UPDATE jobs SET title=?, description=?, category=?, location=?, salary=?, job_type=?, contact_phone=?, vacancies=? WHERE id=? AND employer_id=?");
        $upd->bind_param('sssssssiii', $title_j, $desc, $cat, $loc, $salary, $type, $phone, $vacancy, $job_id, $uid);
        if ($upd->execute()) {
            $msg = "Job updated successfully!";
            // Refresh job data
            $stmt->execute();
            $job = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Failed to update job.";
        }
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_job'])) {
    $del = $db->prepare("DELETE FROM jobs WHERE id=? AND employer_id=?");
    $del->bind_param('ii', $job_id, $uid);
    $del->execute();
    header("Location: employer.php?msg=" . urlencode("Job deleted successfully."));
    exit;
}

include 'inc/head.php';
?>

<div class="flex-between mb">
    <div>
        <h2 style="font-size: 1.5rem;"><i class="fas fa-edit" style="color:#6366f1;"></i> Manage Job</h2>
    </div>
    <a href="employer.php" class="btn-sm btn-gray"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<?php if ($msg): ?><div class="alert alert-ok"><i class="fas fa-check-circle"></i> <?= h($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-err"><i class="fas fa-exclamation-circle"></i> <?= h($error) ?></div><?php endif; ?>

<div class="card" style="padding: 32px;">
    <form method="POST">
        <div class="form-group">
            <label>Job Title <span style="color:red;">*</span></label>
            <input type="text" name="title" value="<?= h($job['title']) ?>" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Category <span style="color:red;">*</span></label>
                <select name="category" required>
                    <?php foreach (['Sales','Driver','Teacher','Helper','Technician','Office','Security','Cooking','Others'] as $c): ?>
                        <option value="<?= $c ?>" <?= ($job['category'] === $c) ? 'selected' : '' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Location <span style="color:red;">*</span></label>
                <input type="text" name="location" value="<?= h($job['location']) ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Job Type</label>
                <select name="job_type">
                    <?php foreach (['full-time'=>'Full Time','part-time'=>'Part Time','daily-wage'=>'Daily Wage','contract'=>'Contract'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= ($job['job_type'] === $k) ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Salary</label>
                <input type="text" name="salary" value="<?= h($job['salary']) ?>">
            </div>
            <div class="form-group">
                <label>Number of Vacancies</label>
                <input type="number" name="vacancies" min="1" value="<?= (int)$job['vacancies'] ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Job Description <span style="color:red;">*</span></label>
            <textarea name="description" rows="5" required><?= h($job['description']) ?></textarea>
        </div>

        <div class="form-group">
            <label>Contact Phone <span style="color:red;">*</span></label>
            <input type="text" name="contact_phone" value="<?= h($job['contact_phone']) ?>" required>
        </div>

        <div style="display:flex; justify-content: space-between; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
            <button type="submit" name="update_job" class="btn-blue" style="background:#6366f1;">
                <i class="fas fa-save"></i> Update Job
            </button>
    </form> <!-- Form close for delete -->

            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this job and all its applications? This cannot be undone.');">
                <button type="submit" name="delete_job" class="btn-red">
                    <i class="fas fa-trash"></i> Delete Job
                </button>
            </form>
        </div>
</div>

<?php include 'inc/foot.php'; ?>