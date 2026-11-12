<?php
require_once 'inc/auth.php';
require_once 'inc/site-config.php';
$title = "Safety Guidelines";

$msg = '';
$errs = [];

// CSRF
if (empty($_SESSION['csrf_report'])) {
    $_SESSION['csrf_report'] = bin2hex(random_bytes(32));
}

// Handle report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    if (!logged_in()) {
        $errs[] = "Please login to report an issue.";
    } elseif (!hash_equals($_SESSION['csrf_report'], $_POST['csrf'] ?? '')) {
        $errs[] = "Security check failed.";
    } else {
        $type = $_POST['report_type'] ?? 'job';
        $reason = trim($_POST['reason'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $job_id = (int)($_POST['job_id'] ?? 0) ?: null;
        $emp_id = (int)($_POST['employer_id'] ?? 0) ?: null;

        if (!in_array($type, ['job','employer','user','other'])) $errs[] = "Invalid report type.";
        if ($reason === '') $errs[] = "Please specify a reason.";
        if (strlen($desc) > 1000) $errs[] = "Description too long.";

        // Agar Job report ho rahi hai to employer_id nikal lo
        if ($type === 'job' && $job_id) {
            $jq = $db->query("SELECT employer_id FROM jobs WHERE id=$job_id");
            if($jrow = $jq->fetch_assoc()) {
                $emp_id = $jrow['employer_id'];
            }
        }

        if (empty($errs)) {
            $rid = $_SESSION['uid'];
            $stmt = $db->prepare("INSERT INTO job_reports (reporter_id, job_id, employer_id, report_type, reason, description) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iiisss', $rid, $job_id, $emp_id, $type, $reason, $desc);
            
            if ($stmt->execute()) {
                $msg = "Thank you. Your report has been submitted. Our team will review it.";
                $_SESSION['csrf_report'] = bin2hex(random_bytes(32));
                $_POST = [];

                // AUTO SUSPEND LOGIC (Agar target_id hai)
                if ($emp_id) {
                    $countQ = $db->query("SELECT COUNT(*) c FROM job_reports WHERE employer_id = $emp_id AND status = 'pending'");
                    $report_count = $countQ->fetch_assoc()['c'];
                    
                    // Agar 3 ya usse zyada reports aayi hain, to suspend kar do
                    if ($report_count >= 3) {
                        $db->query("UPDATE users SET status='rejected' WHERE id=$emp_id AND role!='admin'");
                        $msg .= " <br><strong style='color:red;'>(Note: This user has been temporarily suspended due to multiple reports and is under review).</strong>";
                    }
                }
            } else {
                $errs[] = "Could not submit report. Please try again.";
            }
        }
    }
}

include 'inc/head.php';

$page_title = "Safety Guidelines";
$page_intro = "How to stay safe while job hunting or hiring.";
$page_icon  = "fa-shield-alt";
legal_page_start();
?>

<h2><i class="fas fa-user-tie"></i> For Job Seekers</h2>
<ul>
    <li><strong>Never pay money to get a job.</strong> Genuine employers do not ask for recruitment fees.</li>
    <li><strong>Verify the employer.</strong> Cross-check the company name, phone, and address before meeting.</li>
    <li><strong>Never share OTPs or passwords.</strong> No employer needs them.</li>
    <li><strong>Beware of very high salaries</strong> for very simple jobs — a common scam tactic.</li>
    <li><strong>Verify job location and timings.</strong></li>
    <li><strong>Do not share</strong> Aadhaar, PAN, bank details, or other sensitive documents without a verified offer.</li>
    <li><strong>Meet in public</strong> or during business hours, especially for first interviews.</li>
    <li><strong>Report suspicious employers</strong> using the form below.</li>
</ul>

<h2><i class="fas fa-building"></i> For Employers</h2>
<ul>
    <li>Verify candidate details honestly and fairly.</li>
    <li>Protect candidate resumes — never share them with third parties.</li>
    <li>Do not misuse candidate personal information.</li>
    <li>Do not post misleading job descriptions or salary claims.</li>
    <li>Do not request unnecessary sensitive data (Aadhaar, bank, OTP) during initial hiring stages.</li>
    <li>Follow applicable Indian employment laws.</li>
</ul>

<h2 id="report"><i class="fas fa-flag"></i> Report a Job or Employer</h2>
<p>If you encounter a suspicious job or employer, let us know. All reports are reviewed by our team.</p>

<?php if ($msg): ?>
    <div class="alert alert-ok"><i class="fas fa-check-circle"></i> <?= h($msg) ?></div>
<?php endif; ?>
<?php if ($errs): ?>
    <div class="alert alert-err">
        <i class="fas fa-exclamation-circle"></i>
        <ul style="margin:6px 0 0 20px;padding:0;">
            <?php foreach ($errs as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!logged_in()): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        Please <a href="login.php">login</a> to submit a report. You can also email
        <a href="mailto:<?= h(SUPPORT_EMAIL) ?>"><?= h(SUPPORT_EMAIL) ?></a>.
    </div>
<?php else: ?>
    <form method="POST" class="report-form">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_report']) ?>">

        <div class="form-row">
            <div class="form-group">
                <label>Report Type *</label>
                <select name="report_type" required>
                    <option value="job">Job Posting</option>
                    <option value="employer">Employer</option>
                    <option value="user">User</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Job ID (optional)</label>
                <input type="number" name="job_id" placeholder="e.g. 12">
            </div>
        </div>

        <div class="form-group">
            <label>Reason *</label>
            <input type="text" name="reason" required maxlength="200"
                   placeholder="e.g. Asking for money, fake company">
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="4" maxlength="1000"
                      placeholder="Add any details that will help us verify..."></textarea>
        </div>

        <button type="submit" name="submit_report" value="1" class="btn-red">
            <i class="fas fa-flag"></i> Submit Report
        </button>
    </form>
<?php endif; ?>

<?php legal_page_end(); ?>
<?php include 'inc/foot.php'; ?>