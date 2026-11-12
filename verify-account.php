<?php
require_once 'inc/auth.php';
if (!logged_in()) { header("Location: login.php"); exit; }
$title = "Verify Account — " . SITE_NAME;
$uid = $_SESSION['uid'];
$user = $db->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['verification_doc']['name'])) {
    $ext = strtolower(pathinfo($_FILES['verification_doc']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
        $dir = __DIR__ . '/storage/verification';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        
        $filename = 'verify_' . $uid . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['verification_doc']['tmp_name'], $dir . '/' . $filename)) {
            $db->query("UPDATE users SET verification_doc='$filename', verification_status='pending' WHERE id=$uid");
            $msg = "Document uploaded! Admin will verify within 24 hours.";
            $user['verification_status'] = 'pending';
        }
    } else {
        $msg = "<span style='color:red;'>Invalid format. Use JPG, PNG, or PDF.</span>";
    }
}

include 'inc/head.php';
?>

<div class="wrap" style="padding: 40px 20px; max-width: 700px;">
    <div class="card" style="padding: 40px;">
        <h1 style="font-size: 1.8rem; margin-bottom: 10px;"><i class="fas fa-shield-alt" style="color:var(--blue);"></i> Account Verification</h1>
        <p style="color: var(--gray); margin-bottom: 25px;">Get the blue tick by verifying your identity.</p>

        <?php if ($msg): ?><div class="alert alert-info"><?= $msg ?></div><?php endif; ?>

        <?php if ($user['verification_status'] === 'verified'): ?>
            <div style="text-align:center; padding:30px; background:#ecfdf5; border-radius:12px;">
                <i class="fas fa-check-circle" style="font-size:4rem; color:var(--green);"></i>
                <h2 style="color:var(--green-dark); margin:15px 0;">Account Verified!</h2>
                <p>Your account has the blue tick.</p>
            </div>
        <?php elseif ($user['verification_status'] === 'pending'): ?>
            <div style="text-align:center; padding:30px; background:#fef3c7; border-radius:12px;">
                <i class="fas fa-clock" style="font-size:4rem; color:var(--amber);"></i>
                <h2 style="color:#92400e; margin:15px 0;">Verification Pending</h2>
                <p>Admin will review your document shortly.</p>
            </div>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label style="font-weight:600;">
                        <?= role() === 'employer' ? 'Upload Business License / Shop Registration' : 'Upload Aadhaar Card / ID Proof' ?>
                    </label>
                    <p style="font-size:0.85rem; color:var(--gray); margin-bottom:15px;">Only admins can see this document. It will not be public.</p>
                    <input type="file" name="verification_doc" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
                <button type="submit" class="btn-blue" style="width:100%; padding:14px; margin-top:15px;">
                    <i class="fas fa-upload"></i> Submit for Verification
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include 'inc/foot.php'; ?>