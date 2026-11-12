<?php
// ============================================
// STEP 1: PROCESS POST BEFORE ANY HTML
// ============================================
require_once 'inc/auth.php';
must_role('admin');

$user_filter = $_GET['filter'] ?? 'all';

// HANDLE ACTIONS (BEFORE admin-head.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_user'])) {
    $target_id = (int)$_POST['user_id'];
    $msg = '';
    
    if ($_POST['action_user'] === 'suspend') {
        $db->query("UPDATE users SET status='rejected' WHERE id=$target_id AND role!='admin'");
        $msg = "User suspended successfully.";
    } elseif ($_POST['action_user'] === 'activate') {
        $db->query("UPDATE users SET status='active' WHERE id=$target_id AND role!='admin'");
        $msg = "User activated successfully.";
    } elseif ($_POST['action_user'] === 'verify') {
        $db->query("UPDATE users SET is_verified=1, verification_status='approved', verified_at=NOW() WHERE id=$target_id");
        
        $n_title = "Account Verified! ✅";
        $n_msg = "Congratulations! Your account has been verified. You now have a blue tick on your profile.";
        $n_link = "seeker-settings.php?tab=verification";
        $ns = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
        $ns->bind_param('isss', $target_id, $n_title, $n_msg, $n_link);
        $ns->execute();
        
        $msg = "User verified successfully!";
    } elseif ($_POST['action_user'] === 'unverify') {
        $db->query("UPDATE users SET is_verified=0, verification_status='rejected' WHERE id=$target_id");
        
        $n_title = "Verification Removed";
        $n_msg = "Your account verification has been removed by admin. Please contact support if you have questions.";
        $n_link = "seeker-settings.php?tab=verification";
        $ns = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
        $ns->bind_param('isss', $target_id, $n_title, $n_msg, $n_link);
        $ns->execute();
        
        $msg = "Verification removed.";
    } elseif ($_POST['action_user'] === 'delete') {
        $db->query("DELETE FROM users WHERE id=$target_id AND role!='admin'");
        $msg = "User deleted permanently.";
    }
    header("Location: admin-users.php?filter=$user_filter&msg=" . urlencode($msg));
    exit;
}

// ============================================
// STEP 2: ENSURE COLUMNS EXIST
// ============================================
if (!function_exists('column_exists')) {
    function column_exists($db, $table, $column) {
        $table  = $db->real_escape_string($table);
        $column = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && $res->num_rows > 0;
    }
}

// Add verification columns if missing
$verify_cols = [
    'verification_status' => "VARCHAR(20) NOT NULL DEFAULT 'none'",
    'verification_doc'    => "VARCHAR(255) NULL",
    'rejection_reason'    => "TEXT NULL",
    'verified_at'         => "DATETIME NULL",
    'verified_by'         => "INT NULL",
];
foreach ($verify_cols as $col => $def) {
    if (!column_exists($db, 'users', $col)) {
        try { $db->query("ALTER TABLE users ADD COLUMN `$col` $def"); } catch (Throwable $e) {}
    }
}

// ============================================
// STEP 3: FETCH COUNTS FOR TABS
// ============================================
$pending_verify_count = 0;
try {
    $pc = $db->query("SELECT COUNT(*) c FROM users WHERE role!='admin' AND verification_status='pending'");
    if ($pc) $pending_verify_count = (int)($pc->fetch_assoc()['c'] ?? 0);
} catch (Throwable $e) {}

// ============================================
// STEP 4: LOAD HEADER
// ============================================
$title = "Manage Users";
require_once 'inc/admin-head.php';
?>

<h2 class="page-title">Manage Users</h2>

<div class="folder-tabs">
    <a href="?filter=all" class="folder-tab <?= $user_filter=='all'?'active':'' ?>"><i class="fas fa-folder"></i> All Users</a>
    <a href="?filter=verified" class="folder-tab <?= $user_filter=='verified'?'active':'' ?>"><i class="fas fa-check-circle" style="color:var(--accent-blue);"></i> Verified</a>
    <a href="?filter=unverified" class="folder-tab <?= $user_filter=='unverified'?'active':'' ?>"><i class="fas fa-exclamation-circle" style="color:var(--accent-yellow);"></i> Unverified</a>
    <a href="?filter=pending_verification" class="folder-tab <?= $user_filter=='pending_verification'?'active':'' ?>" style="position:relative;">
        <i class="fas fa-hourglass-half" style="color:#f59e0b;"></i> Pending Verification
        <?php if ($pending_verify_count > 0): ?>
            <span style="background:#ef4444; color:#fff; font-size:0.7rem; padding:2px 7px; border-radius:10px; margin-left:5px; font-weight:700;"><?= $pending_verify_count ?></span>
        <?php endif; ?>
    </a>
    <a href="?filter=employers" class="folder-tab <?= $user_filter=='employers'?'active':'' ?>"><i class="fas fa-building" style="color:#6366f1;"></i> Employers</a>
    <a href="?filter=seekers" class="folder-tab <?= $user_filter=='seekers'?'active':'' ?>"><i class="fas fa-user-tie" style="color:var(--accent-green);"></i> Seekers</a>
    <a href="?filter=suspended" class="folder-tab <?= $user_filter=='suspended'?'active':'' ?>"><i class="fas fa-ban" style="color:var(--accent-red);"></i> Suspended</a>
</div>

<div class="card" style="padding:0; overflow:hidden;">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name / Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Verified</th>
                <?php if ($user_filter === 'pending_verification'): ?>
                    <th>Document</th>
                <?php endif; ?>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $u_where = "role != 'admin'";
        if ($user_filter === 'verified') $u_where .= " AND is_verified = 1";
        elseif ($user_filter === 'unverified') $u_where .= " AND is_verified = 0";
        elseif ($user_filter === 'pending_verification') $u_where .= " AND verification_status = 'pending'";
        elseif ($user_filter === 'employers') $u_where .= " AND role = 'employer'";
        elseif ($user_filter === 'seekers') $u_where .= " AND role = 'seeker'";
        elseif ($user_filter === 'suspended') $u_where .= " AND status = 'rejected'";

        $users = $db->query("SELECT * FROM users WHERE $u_where ORDER BY created_at DESC");
        if($users->num_rows == 0) echo '<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">No users found in this folder.</td></tr>';
        while ($u = $users->fetch_assoc()): 
            $u_data = htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8');
            $is_pending_verify = (($u['verification_status'] ?? 'none') === 'pending');
            $has_doc = !empty($u['verification_doc']);
        ?>
            <tr>
                <td>
                    <a href="javascript:void(0)" onclick="viewProfile(<?= $u_data ?>)" style="color:var(--accent-blue); font-weight:600; text-decoration:underline; font-size:0.95rem;"><?= h($u['name']) ?></a>
                    <br><small style="color:var(--text-muted);"><?= h($u['email']) ?></small>
                </td>
                <td><span class="pill pill-role"><?= h($u['role']) ?></span></td>
                <td><?= $u['status']==='active' ? '<span class="pill pill-active">Active</span>' : '<span class="pill pill-rejected">Suspended</span>' ?></td>
                <td>
                    <?php if ($u['is_verified']): ?>
                        <span class="pill pill-active" style="background:rgba(59,130,246,0.15); color:var(--accent-blue);"><i class="fas fa-check-circle"></i> Verified</span>
                    <?php elseif ($is_pending_verify): ?>
                        <span class="pill pill-pending" style="background:#fef3c7; color:#92400e;"><i class="fas fa-clock"></i> Pending Review</span>
                    <?php else: ?>
                        <span class="pill pill-pending">Not Verified</span>
                    <?php endif; ?>
                </td>
                <?php if ($user_filter === 'pending_verification'): ?>
                    <td>
                        <?php if ($has_doc): ?>
                            <a href="storage/verification/<?= h($u['verification_doc']) ?>" target="_blank" class="btn-sm btn-blue" style="font-size:0.75rem; padding:5px 10px; text-decoration:none;"><i class="fas fa-file-alt"></i> View</a>
                        <?php else: ?>
                            <span style="color:#9ca3af; font-size:0.8rem;">No document</span>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
                <td>
                    <div style="display:flex; gap:5px; flex-wrap:wrap;">
                        <?php if ($user_filter === 'pending_verification'): ?>
                            <!-- APPROVE / REJECT using ajax-admin-action.php -->
                            <button type="button" onclick="approveUser(<?= (int)$u['id'] ?>, '<?= h(addslashes($u['name'])) ?>')" class="btn-sm btn-green" style="padding:6px 10px; font-size:0.75rem; border:none; cursor:pointer; border-radius:6px; background:#22c55e; color:#fff;">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button type="button" onclick="rejectUser(<?= (int)$u['id'] ?>, '<?= h(addslashes($u['name'])) ?>')" class="btn-sm btn-red" style="padding:6px 10px; font-size:0.75rem; border:none; cursor:pointer; border-radius:6px; background:#dc2626; color:#fff;">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        <?php else: ?>
                            <?php if ($u['status'] === 'active'): ?>
                                <form method="POST" onsubmit="return confirm('Suspend this user?');"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button type="submit" name="action_user" value="suspend" class="btn-sm btn-red"><i class="fas fa-ban"></i></button></form>
                            <?php else: ?>
                                <form method="POST"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button type="submit" name="action_user" value="activate" class="btn-sm btn-green"><i class="fas fa-check"></i></button></form>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('Delete user permanently?');"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button type="submit" name="action_user" value="delete" class="btn-sm btn-gray" style="color:var(--accent-red);"><i class="fas fa-trash"></i></button></form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- ============================================ -->
<!-- PROFILE MODAL -->
<!-- ============================================ -->
<div id="profileModal" class="modal-overlay">
    <div class="modal-content">
        <i class="fas fa-times close-modal" onclick="document.getElementById('profileModal').style.display='none'"></i>
        <div class="modal-header">
            <h2 id="m_name">User Name</h2>
            <div style="display:flex; gap:10px; align-items:center;">
                <span id="m_role" class="pill pill-role">Role</span>
                <span id="m_verified"></span>
            </div>
        </div>
        <div class="profile-grid">
            <div class="profile-item"><label>Email Address</label><div id="m_email"></div></div>
            <div class="profile-item"><label>Phone Number</label><div id="m_phone"></div></div>
            <div class="profile-item profile-full" id="company_box" style="display:none;"><label>Company / Shop</label><div id="m_company"></div></div>
            <div class="profile-item profile-full" id="address_box" style="display:none;"><label>Full Address</label><div id="m_address"></div></div>
            <div class="profile-item"><label>Account Status</label><div id="m_status"></div></div>
            <div class="profile-item"><label>Joined On</label><div id="m_joined"></div></div>
            <div class="profile-item profile-full" id="verification_doc_box" style="display:none;">
                <label>Verification Document</label>
                <div id="m_verification_doc"></div>
            </div>
            <div class="profile-item profile-full" id="rejection_reason_box" style="display:none;">
                <label>Previous Rejection Reason</label>
                <div id="m_rejection_reason" style="color:#dc2626;"></div>
            </div>
            <div class="profile-item profile-full" id="gallery_box" style="display:none;">
                <label>Shop / Company Gallery</label>
                <div class="profile-gallery" id="m_gallery_container"></div>
            </div>
        </div>
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color); display:flex; justify-content:flex-end; gap:10px;" id="modal_actions"></div>
    </div>
</div>

<script>
    // ============================================
    // APPROVE USER — calls ajax-admin-action.php
    // ============================================
    function approveUser(userId, userName) {
        if (!confirm('Approve verification for "' + userName + '"?')) return;
        
        const fd = new FormData();
        fd.append('action', 'approve_user');
        fd.append('user_id', userId);
        
        fetch('ajax-admin-action.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    alert('✅ ' + data.msg);
                    location.reload();
                } else {
                    alert('❌ ' + (data.msg || 'Failed to approve'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('❌ Network error. Please try again.');
            });
    }
    
    // ============================================
    // REJECT USER — calls ajax-admin-action.php
    // ============================================
    function rejectUser(userId, userName) {
        const reason = prompt('Rejection reason for "' + userName + '":\n\n(e.g. Document unclear, Invalid ID, Photo blurry...)', '');
        if (reason === null) return; // user cancelled
        if (reason.trim() === '') {
            alert('Please provide a reason for rejection.');
            return;
        }
        
        const fd = new FormData();
        fd.append('action', 'reject_user');
        fd.append('user_id', userId);
        fd.append('reason', reason.trim());
        
        fetch('ajax-admin-action.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    alert('✅ ' + data.msg);
                    location.reload();
                } else {
                    alert('❌ ' + (data.msg || 'Failed to reject'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('❌ Network error. Please try again.');
            });
    }
    
    // ============================================
    // VIEW PROFILE MODAL
    // ============================================
    function viewProfile(data) {
        document.getElementById('m_name').innerText = data.name;
        document.getElementById('m_role').innerText = (data.role || '').toUpperCase();
        document.getElementById('m_email').innerText = data.email;
        document.getElementById('m_phone').innerText = data.phone || 'N/A';
        
        if (data.company_name) {
            document.getElementById('company_box').style.display = 'block';
            document.getElementById('m_company').innerText = data.company_name;
        } else { document.getElementById('company_box').style.display = 'none'; }

        if (data.address) {
            document.getElementById('address_box').style.display = 'block';
            document.getElementById('m_address').innerText = data.address;
        } else { document.getElementById('address_box').style.display = 'none'; }

        // Verification doc
        let docBox = document.getElementById('verification_doc_box');
        let docDiv = document.getElementById('m_verification_doc');
        if (data.verification_doc && data.verification_doc.trim() !== '') {
            docBox.style.display = 'block';
            docDiv.innerHTML = '<a href="storage/verification/' + data.verification_doc + '" target="_blank" style="display:inline-flex; align-items:center; gap:8px; background:#2563eb; color:#fff; padding:8px 16px; border-radius:8px; text-decoration:none; font-weight:600;"><i class="fas fa-file-alt"></i> View Document</a>';
        } else {
            docBox.style.display = 'none';
        }

        // Rejection reason
        let rejBox = document.getElementById('rejection_reason_box');
        if (data.rejection_reason && data.rejection_reason.trim() !== '') {
            rejBox.style.display = 'block';
            document.getElementById('m_rejection_reason').innerText = data.rejection_reason;
        } else {
            rejBox.style.display = 'none';
        }

        // Verified badge + actions
        let verifiedDiv = document.getElementById('m_verified');
        let actionBtnHtml = '';
        let vstatus = data.verification_status || 'none';
        
        if (data.is_verified == 1) {
            verifiedDiv.innerHTML = '<span class="pill pill-active" style="background:rgba(59,130,246,0.15); color:var(--accent-blue);"><i class="fas fa-check-circle"></i> Verified</span>';
            actionBtnHtml = `<form method="POST"><input type="hidden" name="user_id" value="${data.id}"><button type="submit" name="action_user" value="unverify" class="btn-sm btn-gray" style="padding:10px 20px;">Remove Verification</button></form>`;
        } else if (vstatus === 'pending') {
            verifiedDiv.innerHTML = '<span class="pill pill-pending" style="background:#fef3c7; color:#92400e;"><i class="fas fa-clock"></i> Pending Review</span>';
            actionBtnHtml = `
                <button type="button" onclick="document.getElementById('profileModal').style.display='none'; approveUser(${data.id}, '${(data.name || '').replace(/'/g, "\\'")}')" style="padding:10px 20px; border:none; border-radius:6px; background:#22c55e; color:#fff; cursor:pointer; font-weight:600;"><i class="fas fa-check"></i> Approve</button>
                <button type="button" onclick="document.getElementById('profileModal').style.display='none'; rejectUser(${data.id}, '${(data.name || '').replace(/'/g, "\\'")}')" style="padding:10px 20px; border:none; border-radius:6px; background:#dc2626; color:#fff; cursor:pointer; font-weight:600;"><i class="fas fa-times"></i> Reject</button>
            `;
        } else {
            verifiedDiv.innerHTML = '<span class="pill pill-pending"><i class="fas fa-exclamation-circle"></i> Not Verified</span>';
            actionBtnHtml = `<form method="POST"><input type="hidden" name="user_id" value="${data.id}"><button type="submit" name="action_user" value="verify" class="btn-sm btn-blue" style="background:var(--accent-blue); padding:10px 20px;"><i class="fas fa-check"></i> Verify User</button></form>`;
        }
        document.getElementById('modal_actions').innerHTML = actionBtnHtml;

        // Status + Joined
        document.getElementById('m_status').innerHTML = (data.status === 'active') ? '<span class="pill pill-active">Active</span>' : '<span class="pill pill-rejected">Suspended</span>';
        document.getElementById('m_joined').innerText = new Date(data.created_at).toLocaleDateString("en-GB", { day: 'numeric', month: 'short', year: 'numeric' });

        // Gallery
        let galleryBox = document.getElementById('gallery_box');
        let galleryContainer = document.getElementById('m_gallery_container');
        if (data.gallery_images && data.gallery_images.trim() !== '') {
            galleryBox.style.display = 'block';
            let imgs = data.gallery_images.split(',');
            let html = '';
            imgs.forEach(img => { if (img.trim() !== '') html += `<img src="storage/gallery/${img}">`; });
            galleryContainer.innerHTML = html;
        } else { galleryBox.style.display = 'none'; }

        document.getElementById('profileModal').style.display = 'flex';
    }
</script>

<?php include 'inc/foot.php'; ?>