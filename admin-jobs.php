<?php
// ============================================
// STEP 1: PROCESS POST BEFORE ANY HTML
// ============================================
require_once 'inc/auth.php';
must_role('admin');

$job_filter = $_GET['filter'] ?? 'all';

// HANDLE ACTIONS (BEFORE admin-head.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_job'])) {
    $target_id = (int)$_POST['job_id'];
    $note = trim($_POST['admin_note'] ?? '');
    $msg = '';
    
    $emp_q = $db->query("SELECT employer_id, title FROM jobs WHERE id=$target_id");
    if ($emp_data = $emp_q->fetch_assoc()) {
        $emp_id = $emp_data['employer_id'];
        $job_title = $emp_data['title'];
    }

    if ($_POST['action_job'] === 'approve') {
        $db->query("UPDATE jobs SET status='active', admin_note=NULL WHERE id=$target_id");
        if (isset($emp_id)) {
            $n_title = "Job Approved!";
            $n_msg = "Your job '$job_title' has been verified and is now live.";
            $n_link = "employer.php";
            $stmt_n = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
            $stmt_n->bind_param('isss', $emp_id, $n_title, $n_msg, $n_link); 
            $stmt_n->execute();
        }
        $msg = "Job verified and is now live.";
    } elseif ($_POST['action_job'] === 'reject') {
        $stmt = $db->prepare("UPDATE jobs SET status='rejected', admin_note=? WHERE id=?");
        $stmt->bind_param('si', $note, $target_id); 
        $stmt->execute();
        if (isset($emp_id)) {
            $n_title = "Job Rejected";
            $n_msg = "Your job '$job_title' was rejected. Reason: $note";
            $n_link = "employer.php";
            $stmt_n = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
            $stmt_n->bind_param('isss', $emp_id, $n_title, $n_msg, $n_link); 
            $stmt_n->execute();
        }
        $msg = "Job rejected and message sent to employer.";
    } elseif ($_POST['action_job'] === 'request_changes') {
        $stmt = $db->prepare("UPDATE jobs SET admin_note=? WHERE id=?");
        $stmt->bind_param('si', $note, $target_id); 
        $stmt->execute();
        if (isset($emp_id)) {
            $n_title = "Correction Needed";
            $n_msg = "Please correct your job '$job_title'. Admin Note: $note";
            $n_link = "manage-job.php?id=$target_id";
            $stmt_n = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
            $stmt_n->bind_param('isss', $emp_id, $n_title, $n_msg, $n_link); 
            $stmt_n->execute();
        }
        $msg = "Notification sent to employer.";
    } elseif ($_POST['action_job'] === 'delete') {
        $db->query("UPDATE jobs SET status='deleted' WHERE id=$target_id");
        $msg = "Job moved to deleted folder.";
    }
    header("Location: admin-jobs.php?filter=$job_filter&msg=" . urlencode($msg));
    exit;
}

// ============================================
// STEP 2: NOW LOAD HTML HEADER
// ============================================
$title = "Manage Jobs";
require_once 'inc/admin-head.php';
?>

<h2 class="page-title">Manage Jobs</h2>

<div class="folder-tabs">
    <a href="?filter=all" class="folder-tab <?= $job_filter=='all'?'active':'' ?>"><i class="fas fa-folder"></i> All Jobs</a>
    <a href="?filter=active" class="folder-tab <?= $job_filter=='active'?'active':'' ?>"><i class="fas fa-check-circle" style="color:var(--accent-green);"></i> Verified</a>
    <a href="?filter=pending" class="folder-tab <?= $job_filter=='pending'?'active':'' ?>"><i class="fas fa-clock" style="color:var(--accent-yellow);"></i> Pending</a>
    <a href="?filter=notified" class="folder-tab <?= $job_filter=='notified'?'active':'' ?>"><i class="fas fa-bell" style="color:var(--accent-blue);"></i> Notified</a>
    <a href="?filter=rejected" class="folder-tab <?= $job_filter=='rejected'?'active':'' ?>"><i class="fas fa-times-circle" style="color:var(--accent-red);"></i> Rejected</a>
    <a href="?filter=deleted" class="folder-tab <?= $job_filter=='deleted'?'active':'' ?>"><i class="fas fa-trash" style="color:#94A3B8;"></i> Deleted</a>
</div>

<div class="card" style="padding:0; overflow:hidden;">
    <table class="admin-table">
        <thead><tr><th>Job Title</th><th>Company</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php 
        $where = "j.status != 'deleted'";
        if ($job_filter === 'active') $where = "j.status = 'active'";
        elseif ($job_filter === 'pending') $where = "j.status = 'pending'";
        elseif ($job_filter === 'notified') $where = "j.status = 'pending' AND j.admin_note IS NOT NULL AND j.admin_note != ''";
        elseif ($job_filter === 'rejected') $where = "j.status = 'rejected'";
        elseif ($job_filter === 'deleted') $where = "j.status = 'deleted'";

        $jobs = $db->query("SELECT j.*, u.company_name FROM jobs j JOIN users u ON j.employer_id=u.id WHERE $where ORDER BY j.created_at DESC");
        
        if($jobs->num_rows == 0) echo '<tr><td colspan="4" style="text-align:center;">No jobs found in this folder.</td></tr>';
        while ($j = $jobs->fetch_assoc()): 
            $j_data = htmlspecialchars(json_encode($j), ENT_QUOTES, 'UTF-8');
            
            if ($j['status'] === 'pending' && !empty($j['admin_note'])) {
                $status_html = '<span class="pill" style="background:rgba(59,130,246,0.15); color:var(--accent-blue);"><i class="fas fa-bell"></i> Notified</span>';
            } elseif ($j['status'] === 'active') {
                $status_html = '<span class="pill pill-active">Active</span>';
            } elseif ($j['status'] === 'pending') {
                $status_html = '<span class="pill pill-pending">Pending</span>';
            } elseif ($j['status'] === 'rejected') {
                $status_html = '<span class="pill pill-rejected">Rejected</span>';
            } else {
                $status_html = '<span class="pill pill-deleted">Deleted</span>';
            }
        ?>
            <tr>
                <td><strong><?= h($j['title']) ?></strong><br><small style="color:var(--text-muted);"><i class="fas fa-map-marker-alt"></i> <?= h($j['location']) ?></small></td>
                <td><?= h($j['company_name']) ?></td>
                <td><?= $status_html ?></td>
                <td>
                    <div style="display:flex; gap:5px;">
                        <button type="button" class="btn-sm btn-blue" onclick="viewJob(<?= $j_data ?>)"><i class="fas fa-eye"></i> View</button>
                        
                        <?php if ($j['status'] === 'pending'): ?>
                            <form method="POST" style="display:inline;"><input type="hidden" name="job_id" value="<?= $j['id'] ?>"><button type="submit" name="action_job" value="approve" class="btn-sm btn-green" title="Verify & Live"><i class="fas fa-check"></i> Verify</button></form>
                        <?php endif; ?>
                        
                        <?php if ($j['status'] !== 'deleted'): ?>
                            <form method="POST" onsubmit="return confirm('Move job to Deleted folder?');" style="display:inline;"><input type="hidden" name="job_id" value="<?= $j['id'] ?>"><button type="submit" name="action_job" value="delete" class="btn-sm btn-gray" style="color:var(--accent-red);" title="Delete"><i class="fas fa-trash"></i></button></form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- JOB DETAILS MODAL -->
<div id="jobModal" class="modal-overlay">
    <div class="modal-content">
        <i class="fas fa-times close-modal" onclick="document.getElementById('jobModal').style.display='none'"></i>
        
        <div class="modal-header">
            <h2 id="j_title" style="margin-bottom: 4px;"></h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;"><i class="fas fa-building"></i> <span id="j_company"></span></p>
        </div>

        <div id="admin_note_box" style="display:none; background: rgba(245, 158, 11, 0.15); border: 1px solid var(--accent-yellow); color: var(--accent-yellow); padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;">
            <strong><i class="fas fa-exclamation-triangle"></i> Previous Note Sent:</strong> <span id="j_admin_note"></span>
        </div>

        <div class="profile-grid" style="margin-bottom: 24px;">
            <div class="profile-item"><label>Category</label><div id="j_category"></div></div>
            <div class="profile-item"><label>Job Type</label><div id="j_type"></div></div>
            <div class="profile-item"><label>Location</label><div id="j_location"></div></div>
            <div class="profile-item"><label>Salary</label><div id="j_salary"></div></div>
            <div class="profile-item"><label>Vacancies</label><div id="j_vacancies"></div></div>
            <div class="profile-item"><label>Contact Phone</label><div id="j_phone"></div></div>
            <div class="profile-item profile-full">
                <label>Job Description</label>
                <div id="j_desc" style="background: var(--bg-body); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); font-weight: 400; line-height: 1.6;"></div>
            </div>
        </div>

        <form method="POST" id="jobActionForm" style="border-top: 1px solid var(--border-color); padding-top: 20px;">
            <input type="hidden" name="job_id" id="j_id">
            
            <div id="note_area" style="margin-bottom: 15px;">
                <label>Notification Message for Employer</label>
                <textarea name="admin_note" id="j_note_input" class="form-control" rows="3" placeholder="e.g. Please correct the salary details..." style="width:100%; padding:10px; background:var(--bg-body); border:1px solid var(--border-color); color:#fff; border-radius:6px;"></textarea>
            </div>
            
            <div style="display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;">
                <button type="submit" name="action_job" value="request_changes" class="btn-sm btn-yellow" style="padding:10px 20px;" onclick="return confirm('Send this message to employer?');"><i class="fas fa-bell"></i> Send Notification</button>
                <button type="submit" name="action_job" value="reject" class="btn-sm btn-red" style="padding:10px 20px;" onclick="return confirm('Reject this job?');"><i class="fas fa-times"></i> Reject</button>
                <button type="submit" name="action_job" value="approve" class="btn-sm btn-green" style="padding:10px 20px;"><i class="fas fa-check"></i> Verify & Live</button>
                <button type="submit" name="action_job" value="delete" class="btn-sm btn-gray" style="padding:10px 20px; color:var(--accent-red);" onclick="return confirm('Delete this job?');"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
    function viewJob(data) {
        document.getElementById('j_id').value = data.id;
        document.getElementById('j_title').innerText = data.title;
        document.getElementById('j_company').innerText = data.company_name;
        document.getElementById('j_category').innerText = data.category;
        document.getElementById('j_type').innerText = data.job_type;
        document.getElementById('j_location').innerText = data.location;
        document.getElementById('j_salary').innerText = data.salary || 'Not specified';
        document.getElementById('j_vacancies').innerText = data.vacancies;
        document.getElementById('j_phone').innerText = data.contact_phone;
        document.getElementById('j_desc').innerText = data.description;

        let noteBox = document.getElementById('admin_note_box');
        if (data.admin_note && data.admin_note.trim() !== '') {
            noteBox.style.display = 'block';
            document.getElementById('j_admin_note').innerText = data.admin_note;
            document.getElementById('j_note_input').value = data.admin_note;
        } else {
            noteBox.style.display = 'none';
            document.getElementById('j_note_input').value = '';
        }

        if (data.status === 'deleted') {
            document.getElementById('note_area').style.display = 'none';
            document.getElementById('jobActionForm').querySelector('.btn-green').style.display = 'none';
            document.getElementById('jobActionForm').querySelector('.btn-red').style.display = 'none';
            document.getElementById('jobActionForm').querySelector('.btn-yellow').style.display = 'none';
            document.getElementById('jobActionForm').querySelector('.btn-gray').style.display = 'none';
        } else {
            document.getElementById('note_area').style.display = 'block';
            document.getElementById('jobActionForm').querySelector('.btn-green').style.display = 'inline-flex';
            document.getElementById('jobActionForm').querySelector('.btn-red').style.display = 'inline-flex';
            document.getElementById('jobActionForm').querySelector('.btn-yellow').style.display = 'inline-flex';
            document.getElementById('jobActionForm').querySelector('.btn-gray').style.display = 'inline-flex';
        }

        document.getElementById('jobModal').style.display = 'flex';
    }
</script>

<?php include 'inc/foot.php'; ?>