<?php
require_once 'inc/auth.php';
must_role('employer');
$title = "My Gallery — " . SITE_NAME;
$uid = $_SESSION['uid'];
$msg = '';
$error = '';

// Fetch current images
$stmt = $db->prepare("SELECT gallery_images FROM users WHERE id=?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$current_images = !empty($user_data['gallery_images']) ? explode(',', $user_data['gallery_images']) : [];

// Handle Image Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_images'])) {
    if (!empty($_FILES['gallery']['name'][0])) {
        $dir = __DIR__ . '/storage/gallery';
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $uploaded_count = 0;
        foreach ($_FILES['gallery']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['gallery']['error'][$key] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['gallery']['name'][$key], PATHINFO_EXTENSION));
                $size = $_FILES['gallery']['size'][$key];
                
                if (in_array($ext, ['jpg','jpeg','png']) && $size <= 2*1024*1024) {
                    $filename = 'gallery_' . $uid . '_' . time() . '_' . $key . '.' . $ext;
                    if (move_uploaded_file($tmp_name, $dir . '/' . $filename)) {
                        $current_images[] = $filename;
                        $uploaded_count++;
                    }
                }
            }
        }
        
        if ($uploaded_count > 0) {
            $all_imgs = implode(',', $current_images);
            $upd = $db->prepare("UPDATE users SET gallery_images=? WHERE id=?");
            $upd->bind_param('si', $all_imgs, $uid);
            $upd->execute();
            $msg = "$uploaded_count images uploaded successfully!";
        } else {
            $error = "Upload failed. Please check file type (JPG/PNG) and size (Max 2MB).";
        }
    } else {
        $error = "Please select at least one image.";
    }
}

// Handle Image Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_img'])) {
    $img_to_delete = $_POST['delete_img'];
    $key = array_search($img_to_delete, $current_images);
    if ($key !== false) {
        unset($current_images[$key]);
        // Delete from folder
        @unlink(__DIR__ . '/storage/gallery/' . $img_to_delete);
        
        $all_imgs = implode(',', $current_images);
        $upd = $db->prepare("UPDATE users SET gallery_images=? WHERE id=?");
        $upd->bind_param('si', $all_imgs, $uid);
        $upd->execute();
        $msg = "Image deleted.";
    }
}

include 'inc/head.php';
?>

<div class="flex-between mb">
    <div>
        <h2 style="font-size: 1.5rem;"><i class="fas fa-images" style="color:#6366f1;"></i> My Company Gallery</h2>
        <p style="color: var(--gray); font-size: 0.9rem;">Upload photos of your shop, center, or office. These will be visible to job seekers.</p>
    </div>
    <a href="employer.php" class="btn-sm btn-gray"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<?php if ($msg): ?><div class="alert alert-ok"><i class="fas fa-check-circle"></i> <?= h($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-err"><i class="fas fa-exclamation-circle"></i> <?= h($error) ?></div><?php endif; ?>

<div class="card" style="margin-bottom: 24px;">
    <h3 style="margin-bottom: 15px; font-size: 1.1rem;"><i class="fas fa-upload" style="color:#6366f1;"></i> Upload New Images</h3>
    <form method="POST" enctype="multipart/form-data" style="display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="margin:0; flex:1; min-width:250px;">
            <label>Select Images (JPG/PNG, Max 2MB each)</label>
            <input type="file" name="gallery[]" accept="image/jpeg, image/png" multiple required style="padding: 10px; border: 1px dashed #6366f1; border-radius: 8px; background: #f9fafb; width:100%;">
        </div>
        <button type="submit" name="upload_images" class="btn-blue" style="background:#6366f1; padding: 12px 24px;">
            <i class="fas fa-cloud-upload-alt"></i> Upload
        </button>
    </form>
</div>

<div class="card">
    <h3 style="margin-bottom: 15px; font-size: 1.1rem;"><i class="fas fa-th-large" style="color:#6366f1;"></i> Your Uploaded Images (<?= count($current_images) ?>)</h3>
    
    <?php if (empty($current_images)): ?>
        <p style="color:var(--gray);">No images uploaded yet. Upload your first image above.</p>
    <?php else: ?>
        <div class="gallery-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
            <?php foreach ($current_images as $img): ?>
                <div style="position: relative; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); group;">
                    <img src="storage/gallery/<?= h($img) ?>" style="width: 100%; height: 150px; object-fit: cover; display:block;">
                    
                    <form method="POST" style="position: absolute; top: 8px; right: 8px;">
                        <input type="hidden" name="delete_img" value="<?= h($img) ?>">
                        <button type="submit" class="btn-sm btn-red" style="padding: 4px 8px; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;" onclick="return confirm('Delete this image?');" title="Delete Image">
                            <i class="fas fa-trash" style="font-size: 0.75rem;"></i>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'inc/foot.php'; ?>