<?php
require_once 'inc/auth.php';
if (!logged_in()) { header("Location: login.php"); exit; }

$title = "Account Information — " . SITE_NAME;
$uid = $_SESSION['uid'];
$user = $db->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$msg = '';

// ============================================
// HANDLE PHOTO UPLOAD & PROFILE UPDATE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_account'])) {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $stmt = $db->prepare("UPDATE users SET name=?, phone=? WHERE id=?");
        $stmt->bind_param('ssi', $name, $phone, $uid);
        $stmt->execute();
        $msg = "Account details updated successfully!";
        $user['name'] = $name; $user['phone'] = $phone;
    }

    // Photo Upload
    if (!empty($_FILES['profile_photo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $dir = __DIR__ . '/storage/profiles';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            
            $filename = 'user_' . $uid . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dir . '/' . $filename)) {
                // Delete old photo
                if ($user['profile_photo'] && file_exists(__DIR__ . '/' . $user['profile_photo'])) {
                    unlink(__DIR__ . '/' . $user['profile_photo']);
                }
                $new_path = 'storage/profiles/' . $filename;
                $db->query("UPDATE users SET profile_photo='$new_path' WHERE id=$uid");
                $user['profile_photo'] = $new_path;
                $msg = "Profile photo updated!";
            }
        } else {
            $msg = "<span style='color:red;'>Invalid format. Use JPG, PNG, WEBP.</span>";
        }
    }
}

include 'inc/head.php';
?>

<div style="background: var(--gray-bg); padding-bottom: 50px;">
    <div class="wrap" style="padding-top: 30px; max-width: 900px;">
        <p style="font-size: 0.9rem; color: var(--gray); margin-bottom: 10px;">
            <a href="<?= role() === 'seeker' ? 'seeker-home.php' : 'employer.php' ?>" style="color: var(--blue);">Dashboard</a> > Account Information
        </p>
        
        <?php if ($msg): ?>
            <div class="alert alert-info" style="margin-bottom: 20px;"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
        <?php endif; ?>

        <!-- PROFILE CARD HEADER -->
        <div style="background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 30px;">
            <div style="height: 120px; background: linear-gradient(135deg, var(--blue), #1e40af);"></div>
            <div style="padding: 0 30px 30px; position: relative;">
                
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: -50px; margin-bottom: 20px;">
                    <!-- Avatar -->
                    <div style="width: 120px; height: 120px; border-radius: 50%; border: 4px solid #fff; background: #e5e7eb; overflow: hidden; position: relative; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                        <?php if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/' . $user['profile_photo'])): ?>
                            <img src="<?= h($user['profile_photo']) ?>" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background: var(--blue-light); color: var(--blue); font-size: 3rem;">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display:flex; gap:10px;">
                        <form method="POST" enctype="multipart/form-data" style="display:inline;">
                            <label class="btn-sm btn-blue" style="cursor:pointer;">
                                <i class="fas fa-camera"></i> Change Photo
                                <input type="file" name="profile_photo" style="display:none;" onchange="this.form.submit()">
                            </label>
                        </form>
                        <a href="<?= role() === 'seeker' ? 'my-profile.php' : 'company-profile.php' ?>" class="btn-sm btn-outline-blue">
                            <i class="fas fa-user-edit"></i> <?= role() === 'seeker' ? 'My Profile' : 'Company Profile' ?>
                        </a>
                    </div>
                </div>

                <h1 style="font-size: 1.6rem; color: var(--text); margin-bottom: 5px;">
                    <?= h($user['company_name'] ?: $user['name']) ?>
                    <?php if ($user['is_verified']): ?>
                        <i class="fas fa-check-circle" style="color: var(--blue); font-size: 1.2rem;" title="Verified"></i>
                    <?php endif; ?>
                </h1>
                <p style="color: var(--gray); font-size: 0.95rem; margin-bottom: 15px;">
                    <?= role() === 'employer' ? 'Employer at ' . h($user['company_name']) : ($user['headline'] ?: 'Job Seeker') ?>
                </p>

                <div style="display: flex; gap: 15px; flex-wrap: wrap; font-size: 0.9rem; color: var(--gray);">
                    <span><i class="fas fa-envelope" style="color: var(--blue);"></i> <?= h($user['email']) ?></span>
                    <span><i class="fas fa-phone" style="color: var(--blue);"></i> <?= h($user['phone']) ?></span>
                    <span><i class="fas fa-map-marker-alt" style="color: var(--blue);"></i> <?= h($user['city']) ?>, <?= h($user['state']) ?></span>
                    <span><i class="fas fa-calendar" style="color: var(--blue);"></i> Member since <?= date('M Y', strtotime($user['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- ACCOUNT DETAILS FORM -->
        <div style="background: #fff; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
            <h2 style="font-size: 1.2rem; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px;">Basic Account Details</h2>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" value="<?= h($user['name']) ?>" required>
                    </div>
                    <?php if (role() === 'employer'): ?>
                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" value="<?= h($user['company_name']) ?>" disabled style="background:#f3f4f6;">
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email Address (Locked)</label>
                        <input type="email" value="<?= h($user['email']) ?>" disabled style="background:#f3f4f6;">
                        <small>To change email, contact support.</small>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?= h($user['phone']) ?>" required>
                    </div>
                </div>

                <button type="submit" name="update_account" class="btn-blue" style="margin-top: 10px;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'inc/foot.php'; ?>