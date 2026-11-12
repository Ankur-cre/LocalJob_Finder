<?php
// ============================================
// STEP 1: PROCESS POST BEFORE ANY HTML
// ============================================
require_once 'inc/auth.php';
must_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $target = (int)$_POST['user_id'];
    $msg = '';
    
    if ($_POST['action'] === 'approve') {
        // Update verification status + is_verified flag
        $db->query("UPDATE users SET verification_status='verified', is_verified=1 WHERE id=$target");
        
        // Send notification to user
        $user_q = $db->query("SELECT name FROM users WHERE id=$target");
        if ($u = $user_q->fetch_assoc()) {
            $n_title = "Account Verified! ✅";
            $n_msg = "Your account has been verified. You now have a blue tick on your profile.";
            $n_link = "verify-account.php";
            $ns = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
            $ns->bind_param('isss', $target, $n_title, $n_msg, $n_link);
            $ns->execute();
        }
        $msg = "User verified successfully!";
    } elseif ($_POST['action'] === 'reject') {
        $reason = trim($_POST['reject_reason'] ?? '');
        $db->query("UPDATE users SET verification_status='rejected', is_verified=0 WHERE id=$target");
        
        // Notify user with reason
        $n_title = "Verification Rejected";
        $n_msg = "Your verification request was rejected." . ($reason ? " Reason: $reason" : "");
        $n_link = "verify-account.php";
        $ns = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
        $ns->bind_param('isss', $target, $n_title, $n_msg, $n_link);
        $ns->execute();
        
        $msg = "Verification rejected." . ($reason ? " Reason sent to user." : "");
    }
    header("Location: admin-verify.php?msg=" . urlencode($msg));
    exit;
}

// ============================================
// STEP 2: NOW LOAD HTML HEADER
// ============================================
$title = "Verification Requests";
require_once 'inc/admin-head.php';

// Fetch pending count for tabs
$pending_count = $db->query("SELECT COUNT(*) c FROM users WHERE verification_status='pending' AND verification_doc IS NOT NULL")->fetch_assoc()['c'];
$verified_count = $db->query("SELECT COUNT(*) c FROM users WHERE verification_status='verified'")->fetch_assoc()['c'];
$rejected_count = $db->query("SELECT COUNT(*) c FROM users WHERE verification_status='rejected'")->fetch_assoc()['c'];

$filter = $_GET['filter'] ?? 'pending';
?>

<h2 class="page-title">Verification Requests</h2>

<!-- FILTER TABS -->
<div class="folder-tabs">
    <a href="?filter=pending" class="folder-tab <?= $filter=='pending'?'active':'' ?>">
        <i class="fas fa-clock" style="color:var(--accent-yellow);"></i> Pending (<?= $pending_count ?>)
    </a>
    <a href="?filter=verified" class="folder-tab <?= $filter=='verified'?'active':'' ?>">
        <i class="fas fa-check-circle" style="color:var(--accent-green);"></i> Verified (<?= $verified_count ?>)
    </a>
    <a href="?filter=rejected" class="folder-tab <?= $filter=='rejected'?'active':'' ?>">
        <i class="fas fa-times-circle" style="color:var(--accent-red);"></i> Rejected (<?= $rejected_count ?>)
    </a>
</div>

<!-- VERIFICATION LIST -->
<div class="card" style="padding:0; overflow:hidden;">
    <?php
    if ($filter === 'pending') {
        $where = "verification_status='pending' AND verification_doc IS NOT NULL";
    } elseif ($filter === 'verified') {
        $where = "verification_status='verified'";
    } elseif ($filter === 'rejected') {
        $where = "verification_status='rejected'";
    } else {
        $where = "verification_status='pending' AND verification_doc IS NOT NULL";
    }
    
    $pending = $db->query("SELECT * FROM users WHERE $where ORDER BY created_at DESC");
    ?>

    <?php if ($pending->num_rows === 0): ?>
        <div style="padding: 60px 20px; text-align: center;">
            <i class="fas fa-inbox" style="font-size: 3rem; color: #475569; margin-bottom: 15px; display: block;"></i>
            <h3 style="color: #94A3B8; font-size: 1.1rem; margin-bottom: 8px;">
                <?php if ($filter === 'pending'): ?>
                    No pending verification requests
                <?php elseif ($filter === 'verified'): ?>
                    No verified users yet
                <?php else: ?>
                    No rejected requests
                <?php endif; ?>
            </h3>
            <p style="color: #64748B; font-size: 0.9rem;">Requests will appear here when users upload verification documents.</p>
        </div>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Contact</th>
                    <th>Document</th>
                    <th>Requested</th>
                    <th>Status / Action</th>
                </tr>
            </thead>
            <tbody>
            <?php while($u = $pending->fetch_assoc()): 
                $user_data = htmlspecialchars(json_encode([
                    'id' => $u['id'],
                    'name' => $u['name'],
                    'email' => $u['email'],
                    'phone' => $u['phone'],
                    'role' => $u['role'],
                    'company_name' => $u['company_name'],
                    'city' => $u['city'],
                    'state' => $u['state'],
                    'created_at' => $u['created_at'],
                    'verification_doc' => $u['verification_doc']
                ]), ENT_QUOTES, 'UTF-8');
            ?>
                <tr>
                    <!-- USER -->
                    <td>
                        <a href="javascript:void(0)" onclick="viewUser(<?= $user_data ?>)" style="color:var(--accent-blue); font-weight:600; text-decoration:underline; font-size:0.95rem;">
                            <?= h($u['name']) ?>
                        </a>
                        <?php if (!empty($u['company_name'])): ?>
                            <br><small style="color:var(--text-muted);"><i class="fas fa-building"></i> <?= h($u['company_name']) ?></small>
                        <?php endif; ?>
                    </td>
                    
                    <!-- ROLE -->
                    <td><span class="pill pill-role"><?= h($u['role']) ?></span></td>
                    
                    <!-- CONTACT -->
                    <td>
                        <small style="display:block; color:#94A3B8;"><i class="fas fa-envelope"></i> <?= h($u['email']) ?></small>
                        <?php if (!empty($u['phone'])): ?>
                            <small style="display:block; color:#94A3B8; margin-top:3px;"><i class="fas fa-phone"></i> <?= h($u['phone']) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($u['city'])): ?>
                            <small style="display:block; color:#64748B; margin-top:3px;"><i class="fas fa-map-marker-alt"></i> <?= h($u['city']) ?>, <?= h($u['state']) ?></small>
                        <?php endif; ?>
                    </td>
                    
                    <!-- DOCUMENT -->
                    <td>
                        <?php if (!empty($u['verification_doc'])): ?>
                            <a href="storage/verification/<?= h($u['verification_doc']) ?>" target="_blank" class="btn-sm btn-blue" style="padding:8px 14px;">
                                <i class="fas fa-file-alt"></i> View Doc
                            </a>
                        <?php else: ?>
                            <span style="color:#64748B; font-size:0.85rem;">No doc</span>
                        <?php endif; ?>
                    </td>
                    
                    <!-- REQUESTED DATE -->
                    <td>
                        <small style="color:#94A3B8;">
                            <i class="far fa-clock"></i> 
                            <?= date('d M Y', strtotime($u['created_at'])) ?>
                        </small>
                    </td>
                    
                    <!-- STATUS / ACTIONS -->
                    <td>
                        <?php if ($filter === 'pending'): ?>
                            <div style="display:flex; gap:5px; flex-wrap:wrap;">
                                <form method="POST" onsubmit="return confirm('Approve this user?');" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" name="action" value="approve" class="btn-sm btn-green" style="padding:8px 14px;">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <button type="button" onclick="openRejectModal(<?= $u['id'] ?>, '<?= h(addslashes($u['name'])) ?>')" class="btn-sm btn-red" style="padding:8px 14px;">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                            
                        <?php elseif ($filter === 'verified'): ?>
                            <span class="pill pill-active">
                                <i class="fas fa-check-circle"></i> Verified
                            </span>
                            
                        <?php elseif ($filter === 'rejected'): ?>
                            <span class="pill pill-rejected">
                                <i class="fas fa-times-circle"></i> Rejected
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- USER DETAILS MODAL -->
<div id="userModal" class="modal-overlay">
    <div class="modal-content">
        <i class="fas fa-times close-modal" onclick="document.getElementById('userModal').style.display='none'"></i>
        
        <div class="modal-header">
            <h2 id="u_name">User Name</h2>
            <div style="display:flex; gap:10px; align-items:center; margin-top:8px;">
                <span id="u_role" class="pill pill-role">Role</span>
            </div>
        </div>
        
        <div class="profile-grid">
            <div class="profile-item">
                <label>Email Address</label>
                <div id="u_email"></div>
            </div>
            <div class="profile-item">
                <label>Phone Number</label>
                <div id="u_phone"></div>
            </div>
            <div class="profile-item profile-full" id="u_company_box" style="display:none;">
                <label>Company / Shop Name</label>
                <div id="u_company"></div>
            </div>
            <div class="profile-item profile-full" id="u_location_box" style="display:none;">
                <label>Location</label>
                <div id="u_location"></div>
            </div>
            <div class="profile-item">
                <label>Registered On</label>
                <div id="u_joined"></div>
            </div>
            <div class="profile-item" id="u_doc_box" style="display:none;">
                <label>Verification Document</label>
                <div id="u_doc"></div>
            </div>
        </div>
    </div>
</div>

<!-- REJECT REASON MODAL -->
<div id="rejectModal" class="modal-overlay">
    <div class="modal-content" style="width: 500px;">
        <i class="fas fa-times close-modal" onclick="document.getElementById('rejectModal').style.display='none'"></i>
        
        <div class="modal-header">
            <h2>Reject Verification</h2>
            <p style="color:var(--text-muted); font-size:0.9rem; margin-top:5px;">
                Rejecting: <strong id="reject_name" style="color:var(--accent-red);"></strong>
            </p>
        </div>
        
        <form method="POST" id="rejectForm">
            <input type="hidden" name="user_id" id="reject_user_id">
            <input type="hidden" name="action" value="reject">
            
            <div style="margin-bottom: 20px;">
                <label style="display:block; font-weight:600; margin-bottom:8px; color:#fff;">Reason for Rejection (Optional)</label>
                <textarea name="reject_reason" rows="3" placeholder="E.g., Document is not clear, ID proof mismatch, etc..." 
                          style="width:100%; padding:12px; background:var(--bg-body); border:1px solid var(--border-color); color:#fff; border-radius:8px; font-family:inherit; font-size:0.9rem;"></textarea>
                <small style="color:var(--text-muted); font-size:0.8rem; display:block; margin-top:6px;">
                    <i class="fas fa-info-circle"></i> Ye reason user ko notification mein jaayega.
                </small>
            </div>
            
            <div style="display:flex; gap:10px; justify-content:flex-end; border-top:1px solid var(--border-color); padding-top:20px;">
                <button type="button" onclick="document.getElementById('rejectModal').style.display='none'" class="btn-sm btn-gray" style="padding:10px 20px;">
                    Cancel
                </button>
                <button type="submit" class="btn-sm btn-red" style="padding:10px 20px;">
                    <i class="fas fa-times"></i> Confirm Reject
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function viewUser(data) {
    document.getElementById('u_name').innerText = data.name;
    document.getElementById('u_role').innerText = data.role.toUpperCase();
    document.getElementById('u_email').innerText = data.email;
    document.getElementById('u_phone').innerText = data.phone || 'N/A';
    
    // Company
    if (data.company_name) {
        document.getElementById('u_company_box').style.display = 'block';
        document.getElementById('u_company').innerText = data.company_name;
    } else {
        document.getElementById('u_company_box').style.display = 'none';
    }
    
    // Location
    if (data.city || data.state) {
        document.getElementById('u_location_box').style.display = 'block';
        let loc = [];
        if (data.city) loc.push(data.city);
        if (data.state) loc.push(data.state);
        document.getElementById('u_location').innerText = loc.join(', ');
    } else {
        document.getElementById('u_location_box').style.display = 'none';
    }
    
    // Joined date
    document.getElementById('u_joined').innerText = new Date(data.created_at).toLocaleDateString("en-GB", { 
        day: 'numeric', month: 'short', year: 'numeric' 
    });
    
    // Document
    if (data.verification_doc) {
        document.getElementById('u_doc_box').style.display = 'block';
        document.getElementById('u_doc').innerHTML = `<a href="storage/verification/${data.verification_doc}" target="_blank" class="btn-sm btn-blue"><i class="fas fa-file-alt"></i> View Document</a>`;
    } else {
        document.getElementById('u_doc_box').style.display = 'none';
    }
    
    document.getElementById('userModal').style.display = 'flex';
}

function openRejectModal(userId, userName) {
    document.getElementById('reject_user_id').value = userId;
    document.getElementById('reject_name').innerText = userName;
    document.getElementById('rejectModal').style.display = 'flex';
}

// Close modal on background click
document.getElementById('userModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php include 'inc/foot.php'; ?>