<?php
// ============================================
// 1. PROCESS FORM SUBMISSIONS (BEFORE ANY HTML)
// ============================================
require_once 'inc/auth.php';
must_role('admin');

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ADD PLAN
    if (isset($_POST['action_add_plan'])) {
        $name = trim($_POST['plan_name']); $price = (float)$_POST['price'];
        $days = (int)$_POST['duration_days']; $features = trim($_POST['features']);
        $target = $_POST['target_role'];
        $stmt = $db->prepare("INSERT INTO subscription_plans (plan_name, price, duration_days, features, target_role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sdiss', $name, $price, $days, $features, $target); $stmt->execute();
        $msg = "New subscription plan added.";
    }
    
    // ADD AD (With File Upload)
    if (isset($_POST['action_add_ad'])) {
        $t = trim($_POST['ad_title']); 
        $c = trim($_POST['ad_content']);
        $l = trim($_POST['ad_link']); 
        $tr = $_POST['target_role'];
        
        $image_path = null;
        
        // Handle Image File Upload
        if (!empty($_FILES['ad_image_file']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($_FILES['ad_image_file']['name'], PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $dir = __DIR__ . '/storage/ads';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                
                $filename = 'ad_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $dest = $dir . '/' . $filename;
                
                if (move_uploaded_file($_FILES['ad_image_file']['tmp_name'], $dest)) {
                    $image_path = 'storage/ads/' . $filename;
                }
            }
        }
        
        // Insert into Database
        $stmt = $db->prepare("INSERT INTO ads (ad_title, ad_content, ad_link, ad_image, target_role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $t, $c, $l, $image_path, $tr); 
        $stmt->execute();
        $msg = "New ad campaign with image added.";
    }
    
    // Toggle / Delete Actions
    if (isset($_POST['action_delete_plan'])) { $id=(int)$_POST['plan_id']; $db->query("DELETE FROM subscription_plans WHERE id=$id"); $msg="Plan deleted."; }
    if (isset($_POST['action_toggle_plan'])) { $id=(int)$_POST['plan_id']; $db->query("UPDATE subscription_plans SET is_active=NOT is_active WHERE id=$id"); $msg="Plan toggled."; }
    
    if (isset($_POST['action_delete_ad'])) { 
        $id=(int)$_POST['ad_id']; 
        // Delete image file too
        $q = $db->query("SELECT ad_image FROM ads WHERE id=$id")->fetch_assoc();
        if ($q && $q['ad_image'] && file_exists(__DIR__ . '/' . $q['ad_image'])) {
            unlink(__DIR__ . '/' . $q['ad_image']);
        }
        $db->query("DELETE FROM ads WHERE id=$id"); 
        $msg="Ad and image deleted."; 
    }
    if (isset($_POST['action_toggle_ad'])) { $id=(int)$_POST['ad_id']; $db->query("UPDATE ads SET is_active=NOT is_active WHERE id=$id"); $msg="Ad toggled."; }
    
    // REDIRECT (Ab ye error nahi dega kyunki HTML abhi load hi nahi hua)
    header("Location: admin-monetization.php?msg=" . urlencode($msg));
    exit;
}

// ============================================
// 2. NOW LOAD HTML HEADER
// ============================================
$title = "Monetization";
require_once 'inc/admin-head.php';
?>

<h2 class="page-title">Monetization Manager</h2>
<div class="monetize-tabs" style="display:flex; gap:10px; margin-bottom:20px;">
    <button type="button" class="btn-sm btn-blue active" onclick="showMonetize('plansTab', this)">Subscription Plans</button>
    <button type="button" class="btn-sm btn-gray" onclick="showMonetize('adsTab', this)">Ads Manager</button>
</div>

<!-- ================= PLANS TAB ================= -->
<div id="plansTab" class="monetize-content">
    <div style="display:flex; gap: 20px; flex-wrap: wrap; align-items: flex-start;">
        <div class="card" style="flex: 1; min-width: 300px;">
            <h3 style="margin-bottom: 15px; color:var(--accent-blue); font-size:1rem;"><i class="fas fa-plus-circle"></i> Add Plan</h3>
            <form method="POST">
                <input type="hidden" name="action_add_plan" value="1">
                <div style="margin-bottom:15px;"><label>Plan Name</label><input type="text" name="plan_name" class="form-control" required style="width:100%; padding:10px; background:var(--bg-body); border:1px solid var(--border-color); color:#fff; border-radius:6px;"></div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
                    <div><label>Price (₹)</label><input type="number" name="price" class="form-control" required style="width:100%; padding:10px; background:var(--bg-body); border:1px solid var(--border-color); color:#fff; border-radius:6px;"></div>
                    <div><label>Days</label><input type="number" name="duration_days" class="form-control" value="30" required style="width:100%; padding:10px; background:var(--bg-body); border:1px solid var(--border-color); color:#fff; border-radius:6px;"></div>
                </div>
                <div style="margin-bottom:15px;"><label>Features</label><textarea name="features" class="form-control" rows="2" style="width:100%; padding:10px; background:var(--bg-body); border:1px solid var(--border-color); color:#fff; border-radius:6px;"></textarea></div>
                <div style="margin-bottom:15px;"><label>Target</label>
                    <select name="target_role" class="form-control" style="width:100%; padding:10px; background:var(--bg-body); border:1px solid var(--border-color); color:#fff; border-radius:6px;">
                        <option value="both">Both</option><option value="seeker">Seeker</option><option value="employer">Employer</option>
                    </select>
                </div>
                <button type="submit" class="btn-sm btn-blue" style="width:100%; justify-content:center;">Save Plan</button>
            </form>
        </div>
        
        <div class="card" style="flex: 2; min-width: 400px; padding:0; overflow:hidden;">
            <table class="admin-table">
                <thead><tr><th>Plan</th><th>Price</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php 
                $plans = $db->query("SELECT * FROM subscription_plans ORDER BY price ASC");
                if($plans->num_rows == 0) echo '<tr><td colspan="4" style="text-align:center;">No plans found.</td></tr>';
                while ($p = $plans->fetch_assoc()): 
                ?>
                    <tr>
                        <td><strong><?= h($p['plan_name']) ?></strong><br><small style="color:var(--text-muted);"><?= h($p['features']) ?></small></td>
                        <td>₹<?= number_format($p['price']) ?> / <?= $p['duration_days'] ?>d</td>
                        <td><form method="POST"><input type="hidden" name="plan_id" value="<?= $p['id'] ?>"><button type="submit" name="action_toggle_plan" class="btn-sm <?= $p['is_active'] ? 'btn-green' : 'btn-gray' ?>"><?= $p['is_active'] ? 'ON' : 'OFF' ?></button></form></td>
                        <td><form method="POST"><input type="hidden" name="plan_id" value="<?= $p['id'] ?>"><button type="submit" name="action_delete_plan" class="btn-sm btn-red">Delete</button></form></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================= ADS TAB ================= -->
<div id="adsTab" class="monetize-content" style="display:none;">
    <div style="display:flex; gap: 20px; flex-wrap: wrap; align-items: flex-start;">
        
        <!-- Add Ad Form -->
        <div class="card" style="flex: 1; min-width: 350px;">
            <h3 style="margin-bottom: 15px; color:var(--accent-yellow); font-size:1rem;"><i class="fas fa-bullhorn"></i> Create New Ad</h3>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action_add_ad" value="1">
                
                <!-- Image Upload -->
                <div style="margin-bottom:15px; background: var(--bg-body); padding: 12px; border-radius: 8px; border: 1px dashed var(--border-color);">
                    <label style="color:var(--accent-yellow);"><i class="fas fa-image"></i> Upload Ad Image *</label>
                    <input type="file" name="ad_image_file" accept="image/*" required style="width:100%; padding:8px; color:#fff; font-size:0.85rem;">
                    <small style="color:var(--text-muted); font-size:0.75rem; margin-top:4px; display:block;">Max 2MB. Formats: JPG, PNG, GIF.</small>
                </div>

                <div style="margin-bottom:15px;"><label>Ad Title *</label><input type="text" name="ad_title" class="form-control" required style="width:100%; padding:10px; background:var(--bg-body); border:1px solid var(--border-color); color:#fff; border-radius:6px;"></div>
                
                <div style="margin-bottom:15px;"><label>Short Description *</label><textarea name="ad_content" class="form-control" rows="2" required style="width:100%; padding:10px; background:var(--bg-body); border:1px solid var(--border-color); color:#fff; border-radius:6px;"></textarea></div>
                
                <div style="margin-bottom:15px;">
                    <label>Target Link (URL) *</label>
                    <input type="url" name="ad_link" class="form-control" placeholder="https://your-website.com" required style="width:100%; padding:10px; background:var(--bg-body); border:1px solid var(--border-color); color:#fff; border-radius:6px;">
                    <small style="color:var(--text-muted); font-size:0.75rem; margin-top:4px; display:block;">User will go here when clicking the ad.</small>
                </div>
                
                <div style="margin-bottom:15px;"><label>Target Audience</label>
                    <select name="target_role" class="form-control" style="width:100%; padding:10px; background:var(--bg-body); border:1px solid var(--border-color); color:#fff; border-radius:6px;">
                        <option value="both">Both (Seeker & Employer)</option>
                        <option value="seeker">Job Seekers Only</option>
                        <option value="employer">Employers Only</option>
                    </select>
                </div>
                <button type="submit" class="btn-sm btn-blue" style="width:100%; justify-content:center; background:var(--accent-yellow); color:#000; padding:12px; font-size:1rem;"><i class="fas fa-rocket"></i> Launch Ad</button>
            </form>
        </div>
        
        <!-- Ad List -->
        <div class="card" style="flex: 2; min-width: 400px; padding:0; overflow:hidden;">
            <table class="admin-table">
                <thead><tr><th>Ad Preview</th><th>Target</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php 
                $ads = $db->query("SELECT * FROM ads ORDER BY created_at DESC");
                if($ads->num_rows == 0) echo '<tr><td colspan="4" style="text-align:center;">No ads running.</td></tr>';
                while ($a = $ads->fetch_assoc()): 
                ?>
                    <tr>
                        <td>
                            <div style="display:flex; gap:12px; align-items:center;">
                                <!-- Clickable Image with Link -->
                                <?php if (!empty($a['ad_image'])): ?>
                                    <a href="<?= h($a['ad_link']) ?>" target="_blank" title="Click to open link">
                                        <img src="<?= h($a['ad_image']) ?>" style="width:60px; height:60px; object-fit:cover; border-radius:8px; border:1px solid var(--border-color); transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                    </a>
                                <?php else: ?>
                                    <div style="width:60px; height:60px; background:var(--bg-body); border-radius:8px; display:flex; align-items:center; justify-content:center;"><i class="fas fa-image" style="color:#475569;"></i></div>
                                <?php endif; ?>
                                
                                <div>
                                    <strong style="display:block; color:#fff; font-size:0.95rem;"><?= h($a['ad_title']) ?></strong>
                                    <small style="color:var(--text-muted); display:block; margin-top:2px;"><?= h($a['ad_content']) ?></small>
                                    <?php if (!empty($a['ad_link'])): ?>
                                        <a href="<?= h($a['ad_link']) ?>" target="_blank" style="font-size:0.75rem; color:var(--accent-blue); text-decoration:none; margin-top:4px; display:inline-block;"><i class="fas fa-link"></i> Visit Link</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><span class="pill pill-role"><?= ucfirst($a['target_role']) ?></span></td>
                        <td><form method="POST"><input type="hidden" name="ad_id" value="<?= $a['id'] ?>"><button type="submit" name="action_toggle_ad" class="btn-sm <?= $a['is_active'] ? 'btn-green' : 'btn-gray' ?>"><?= $a['is_active'] ? 'ON' : 'OFF' ?></button></form></td>
                        <td><form method="POST" onsubmit="return confirm('Delete this ad and its image?');"><input type="hidden" name="ad_id" value="<?= $a['id'] ?>"><button type="submit" name="action_delete_ad" class="btn-sm btn-red"><i class="fas fa-trash"></i></button></form></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function showMonetize(tabId, btn) {
        document.querySelectorAll('.monetize-content').forEach(el => el.style.display = 'none');
        document.getElementById(tabId).style.display = 'block';
        document.querySelectorAll('.monetize-tabs button').forEach(el => { el.classList.remove('active', 'btn-blue'); el.classList.add('btn-gray'); });
        btn.classList.remove('btn-gray'); btn.classList.add('active', 'btn-blue');
    }
</script>

<?php include 'inc/foot.php'; ?>