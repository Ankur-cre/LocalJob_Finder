<?php
require_once 'inc/auth.php';
must_role('seeker');
$title = "My Profile — " . SITE_NAME;
$uid = $_SESSION['uid'];

// ============ HELPER ============
if (!function_exists('column_exists')) {
    function column_exists($db, $table, $column) {
        $table  = $db->real_escape_string($table);
        $column = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && $res->num_rows > 0;
    }
}

// ============ AUTO-CREATE PRIVACY COLUMNS (safe) ============
$privacy_cols = [
    'show_phone'    => "TINYINT(1) NOT NULL DEFAULT 1",
    'show_email'    => "TINYINT(1) NOT NULL DEFAULT 1",
    'show_photo'    => "TINYINT(1) NOT NULL DEFAULT 1",
    'show_location' => "TINYINT(1) NOT NULL DEFAULT 1",
    'share_on_hire' => "TINYINT(1) NOT NULL DEFAULT 1",
];
foreach ($privacy_cols as $col => $def) {
    if (!column_exists($db, 'users', $col)) {
        try { $db->query("ALTER TABLE users ADD COLUMN `$col` $def"); } catch (Throwable $e) {}
    }
}

$user = $db->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$msg = '';

// ============ HANDLE PERSONAL UPDATE ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_personal'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $city = trim($_POST['city']);
    
    $stmt = $db->prepare("UPDATE users SET name=?, phone=?, city=? WHERE id=?");
    $stmt->bind_param('sssi', $name, $phone, $city, $uid);
    $stmt->execute();
    $msg = "Personal info updated!";
    $user['name'] = $name; $user['phone'] = $phone; $user['city'] = $city;
}

// ============ HANDLE PRIVACY UPDATE ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_privacy'])) {
    $show_phone    = isset($_POST['show_phone'])    ? 1 : 0;
    $show_email    = isset($_POST['show_email'])    ? 1 : 0;
    $show_photo    = isset($_POST['show_photo'])    ? 1 : 0;
    $show_location = isset($_POST['show_location']) ? 1 : 0;
    $share_on_hire = isset($_POST['share_on_hire']) ? 1 : 0;

    $up = $db->prepare("UPDATE users SET show_phone=?, show_email=?, show_photo=?, show_location=?, share_on_hire=? WHERE id=?");
    $up->bind_param('iiiiii', $show_phone, $show_email, $show_photo, $show_location, $share_on_hire, $uid);
    if ($up->execute()) {
        $msg = "Privacy settings updated!";
        $user['show_phone'] = $show_phone;
        $user['show_email'] = $show_email;
        $user['show_photo'] = $show_photo;
        $user['show_location'] = $show_location;
        $user['share_on_hire'] = $share_on_hire;
    } else {
        $msg = "Failed to update privacy settings.";
    }
}

include 'inc/head.php';
?>

<style>
    body { background-color: #eef2f6 !important; }
    .page { background-color: #eef2f6 !important; padding: 0 !important; margin: 0 !important; }
    .wrap { max-width: 100% !important; padding: 0 !important; }

    .rb-wrapper-custom { display: flex !important; gap: 24px !important; background: #eef2f6 !important; padding: 30px !important; min-height: 100vh !important; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important; max-width: 1400px !important; margin: 0 auto !important; box-sizing: border-box !important; }
    .rb-sidebar-custom { width: 320px !important; flex-shrink: 0 !important; display: flex !important; flex-direction: column !important; gap: 20px !important; }
    .rb-sidebar-card-custom { background: #ffffff !important; border-radius: 12px !important; padding: 30px 25px !important; box-shadow: 0 4px 15px rgba(0,0,0,0.03) !important; border: 1px solid #e2e8f0 !important; }
    .rb-brand-custom { display: flex !important; align-items: center !important; gap: 12px !important; margin-bottom: 8px !important; }
    .rb-brand-custom i { color: #2563eb !important; font-size: 1.8rem !important; }
    .rb-brand-custom h2 { font-size: 1.4rem !important; font-weight: 800 !important; color: #111827 !important; margin: 0 !important; }
    .rb-desc-custom { font-size: 0.9rem !important; color: #6b7280 !important; line-height: 1.5 !important; margin-bottom: 35px !important; }
    .rb-step-custom { display: flex !important; gap: 15px !important; margin-bottom: 35px !important; position: relative !important; }
    .rb-step-custom:last-child { margin-bottom: 0 !important; }
    .rb-step-custom::before { content: '' !important; position: absolute !important; left: 17px !important; top: 35px !important; bottom: -30px !important; width: 2px !important; background: #e5e7eb !important; z-index: 1 !important; }
    .rb-step-custom:last-child::before { display: none !important; }
    .rb-step-icon-custom { width: 36px !important; height: 36px !important; border-radius: 50% !important; background: #e5e7eb !important; color: #6b7280 !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: 700 !important; z-index: 2 !important; flex-shrink: 0 !important; font-size: 1rem !important; }
    .rb-step-custom.done .rb-step-icon-custom { background: #22c55e !important; color: #fff !important; }
    .rb-step-custom.active .rb-step-icon-custom { background: #2563eb !important; color: #fff !important; }
    .rb-step-content-custom h4 { font-size: 1rem !important; font-weight: 700 !important; color: #111827 !important; margin: 0 0 4px 0 !important; }
    .rb-step-content-custom p { font-size: 0.85rem !important; color: #6b7280 !important; margin: 0 !important; line-height: 1.4 !important; }
    .rb-help-custom { background: #f0f5ff !important; border: 1px solid #dbeafe !important; border-radius: 12px !important; padding: 25px !important; }
    .rb-help-custom h4 { font-size: 1.1rem !important; font-weight: 700 !important; color: #111827 !important; margin: 0 0 10px 0 !important; display: flex !important; align-items: center !important; gap: 10px !important; }
    .rb-help-custom h4 i { color: #f59e0b !important; font-size: 1.3rem !important; }
    .rb-help-custom p { font-size: 0.85rem !important; color: #6b7280 !important; margin: 0 0 20px 0 !important; line-height: 1.5 !important; }
    .rb-help-btn-custom { width: 100% !important; background: #2563eb !important; color: #fff !important; border: none !important; padding: 12px !important; border-radius: 8px !important; font-weight: 600 !important; font-size: 0.9rem !important; cursor: pointer !important; display: flex !important; justify-content: center !important; align-items: center !important; gap: 8px !important; }
    .rb-main-custom { flex: 1 !important; background: #ffffff !important; border-radius: 12px !important; box-shadow: 0 4px 15px rgba(0,0,0,0.03) !important; border: 1px solid #e2e8f0 !important; display: flex !important; flex-direction: column !important; overflow: hidden !important; }
    .rb-header-custom { background: #1d4ed8 !important; padding: 30px !important; display: flex !important; justify-content: space-between !important; align-items: center !important; color: #fff !important; }
    .rb-header-left-custom { display: flex !important; align-items: center !important; gap: 20px !important; }
    .rb-header-icon-custom { width: 60px !important; height: 60px !important; background: rgba(255,255,255,0.2) !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-size: 1.8rem !important; color: #fff !important; }
    .rb-header-custom h1 { font-size: 1.8rem !important; font-weight: 700 !important; margin: 0 0 5px 0 !important; color: #fff !important; }
    .rb-header-custom p { font-size: 1rem !important; color: #bfdbfe !important; margin: 0 !important; }
    .rb-progress-wrap-custom { display: flex !important; align-items: center !important; gap: 15px !important; width: 300px !important; }
    .rb-progress-bar-custom { flex: 1 !important; height: 12px !important; background: rgba(255,255,255,0.2) !important; border-radius: 10px !important; overflow: hidden !important; }
    .rb-progress-fill-custom { height: 100% !important; background: #22c55e !important; width: 40% !important; border-radius: 10px !important; }
    .rb-progress-text-custom { font-size: 0.9rem !important; font-weight: 600 !important; white-space: nowrap !important; color: #fff !important; }
    .rb-tabs-custom { display: flex !important; background: #f8fafc !important; border-bottom: 1px solid #e2e8f0 !important; padding: 0 20px !important; overflow-x: auto !important; }
    .rb-tab-custom { padding: 18px 24px !important; font-size: 0.95rem !important; font-weight: 600 !important; color: #64748b !important; cursor: pointer !important; border-bottom: 3px solid transparent !important; display: flex !important; align-items: center !important; gap: 8px !important; white-space: nowrap !important; background: transparent !important; border-top: none !important; border-left: none !important; border-right: none !important; transition: all 0.2s !important; }
    .rb-tab-custom:hover { color: #2563eb !important; }
    .rb-tab-custom.active { color: #2563eb !important; border-bottom-color: #2563eb !important; background: #ffffff !important; border-top-left-radius: 8px !important; border-top-right-radius: 8px !important; }
    .rb-body-custom { padding: 30px !important; flex: 1 !important; }
    .rb-section-card-custom { background: #f8fafc !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; padding: 25px !important; margin-bottom: 24px !important; }
    .rb-section-title-custom { font-size: 1.2rem !important; font-weight: 700 !important; color: #1e293b !important; margin: 0 0 20px 0 !important; display: flex !important; align-items: center !important; gap: 10px !important; }
    .rb-section-title-custom i { color: #2563eb !important; background: #dbeafe !important; padding: 8px !important; border-radius: 8px !important; font-size: 1.1rem !important; }
    .rb-grid-custom { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 20px !important; }
    .rb-grid-4-custom { display: grid !important; grid-template-columns: repeat(4, 1fr) !important; gap: 15px !important; }
    .rb-form-group-custom { display: flex !important; flex-direction: column !important; gap: 8px !important; }
    .rb-form-group-custom label { font-size: 0.9rem !important; font-weight: 600 !important; color: #334155 !important; }
    .rb-form-group-custom label span { color: #dc2626 !important; }
    .rb-input-custom { padding: 12px 15px !important; border: 1px solid #cbd5e1 !important; border-radius: 8px !important; font-size: 0.95rem !important; color: #0f172a !important; outline: none !important; transition: border 0.2s !important; width: 100% !important; background: #ffffff !important; box-sizing: border-box !important; }
    .rb-input-custom:focus { border-color: #2563eb !important; box-shadow: 0 0 0 3px rgba(37,99,235,0.1) !important; }
    .rb-input-icon-custom { position: relative !important; }
    .rb-input-icon-custom i { position: absolute !important; left: 15px !important; top: 50% !important; transform: translateY(-50%) !important; color: #94a3b8 !important; }
    .rb-input-icon-custom .rb-input-custom { padding-left: 40px !important; }
    .rb-photo-col-custom { width: 140px !important; flex-shrink: 0 !important; }
    .rb-photo-box-custom { width: 140px !important; height: 160px !important; background: #e2e8f0 !important; border-radius: 12px !important; display: flex !important; align-items: center !important; justify-content: center !important; position: relative !important; overflow: hidden !important; border: 2px dashed #94a3b8 !important; margin-bottom: 10px !important; }
    .rb-photo-box-custom i.fa-user { font-size: 3.5rem !important; color: #94a3b8 !important; }
    .rb-photo-box-custom img { width: 100% !important; height: 100% !important; object-fit: cover !important; }
    .rb-photo-cam-custom { position: absolute !important; bottom: 5px !important; right: 5px !important; background: #2563eb !important; color: #fff !important; width: 32px !important; height: 32px !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; cursor: pointer !important; border: 2px solid #fff !important; font-size: 0.8rem !important; }
    .rb-photo-btn-custom { width: 100% !important; background: #ffffff !important; border: 1px solid #cbd5e1 !important; color: #334155 !important; padding: 8px !important; border-radius: 8px !important; font-size: 0.85rem !important; font-weight: 600 !important; cursor: pointer !important; display: flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; margin-bottom: 5px !important; }
    .rb-photo-hint-custom { font-size: 0.75rem !important; color: #64748b !important; text-align: center !important; }
    .rb-footer-custom { padding: 20px 30px !important; border-top: 1px solid #e2e8f0 !important; display: flex !important; justify-content: space-between !important; align-items: center !important; background: #ffffff !important; }
    .rb-btn-custom { padding: 12px 24px !important; border-radius: 8px !important; font-size: 1rem !important; font-weight: 600 !important; cursor: pointer !important; display: inline-flex !important; align-items: center !important; gap: 8px !important; border: none !important; transition: all 0.2s !important; }
    .rb-btn-outline-custom { background: #ffffff !important; border: 1.5px solid #2563eb !important; color: #2563eb !important; }
    .rb-btn-primary-custom { background: #1d4ed8 !important; color: #ffffff !important; }
    .rb-tab-content-custom { display: none !important; }
    .rb-tab-content-custom.active { display: block !important; animation: fadeIn 0.3s ease !important; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

    /* Privacy toggles */
    .privacy-item {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding: 16px !important;
        background: #fff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 10px !important;
        margin-bottom: 12px !important;
    }
    .privacy-info { flex: 1 !important; padding-right: 16px !important; }
    .privacy-info h4 { margin: 0 0 4px 0 !important; font-size: 0.95rem !important; color: #111827 !important; font-weight: 600 !important; }
    .privacy-info p { margin: 0 !important; font-size: 0.8rem !important; color: #6b7280 !important; line-height: 1.4 !important; }
    .switch { position: relative; display: inline-block; width: 48px; height: 26px; flex-shrink: 0; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 26px; }
    .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
    input:checked + .slider { background-color: #22c55e; }
    input:checked + .slider:before { transform: translateX(22px); }

    @media (max-width: 900px) {
        .rb-wrapper-custom { flex-direction: column !important; padding: 15px !important; }
        .rb-sidebar-custom { width: 100% !important; }
        .rb-grid-custom, .rb-grid-4-custom { grid-template-columns: 1fr !important; }
        .rb-header-custom { flex-direction: column !important; align-items: flex-start !important; gap: 20px !important; }
        .rb-progress-wrap-custom { width: 100% !important; }
    }
</style>

<div class="rb-wrapper-custom">
    <!-- LEFT SIDEBAR -->
    <div class="rb-sidebar-custom">
        <div class="rb-sidebar-card-custom">
            <div class="rb-brand-custom">
                <i class="fas fa-file-alt"></i>
                <h2>Resume Builder</h2>
            </div>
            <p class="rb-desc-custom">Create a professional resume in just a few steps</p>
            
            <div class="rb-step-custom done">
                <div class="rb-step-icon-custom"><i class="fas fa-check"></i></div>
                <div class="rb-step-content-custom">
                    <h4>Choose Template</h4>
                    <p>Select your preferred template</p>
                </div>
            </div>
            <div class="rb-step-custom active">
                <div class="rb-step-icon-custom">2</div>
                <div class="rb-step-content-custom">
                    <h4>Add Details</h4>
                    <p>Fill in your information</p>
                </div>
            </div>
            <div class="rb-step-custom">
                <div class="rb-step-icon-custom">3</div>
                <div class="rb-step-content-custom">
                    <h4>Preview & Download</h4>
                    <p>Review and download your resume</p>
                </div>
            </div>
        </div>

        <div class="rb-help-custom">
            <h4><i class="fas fa-lightbulb"></i> Need Help?</h4>
            <p>Don't worry! Just fill the form with your correct details. You can edit any section later.</p>
            <button class="rb-help-btn-custom"><i class="fas fa-play-circle"></i> View Tutorial</button>
        </div>
    </div>

    <!-- RIGHT MAIN CONTENT -->
    <div class="rb-main-custom">
        <div class="rb-header-custom">
            <div class="rb-header-left-custom">
                <div class="rb-header-icon-custom"><i class="fas fa-user-plus"></i></div>
                <div>
                    <h1>Add Your Details</h1>
                    <p>Fill in the information below to build your resume</p>
                </div>
            </div>
            <div class="rb-progress-wrap-custom">
                <div class="rb-progress-bar-custom"><div class="rb-progress-fill-custom"></div></div>
                <div class="rb-progress-text-custom">Step 2 of 3</div>
            </div>
        </div>

        <!-- Tabs (added Privacy) -->
        <div class="rb-tabs-custom">
            <button type="button" class="rb-tab-custom active" onclick="switchTab('personal', this)"><i class="fas fa-user"></i> Personal Info</button>
            <button type="button" class="rb-tab-custom" onclick="switchTab('education', this)"><i class="fas fa-graduation-cap"></i> Education</button>
            <button type="button" class="rb-tab-custom" onclick="switchTab('skills', this)"><i class="fas fa-cog"></i> Skills</button>
            <button type="button" class="rb-tab-custom" onclick="switchTab('experience', this)"><i class="fas fa-briefcase"></i> Experience</button>
            <button type="button" class="rb-tab-custom" onclick="switchTab('objective', this)"><i class="fas fa-bullseye"></i> Job Objective</button>
            <button type="button" class="rb-tab-custom" onclick="switchTab('privacy', this)"><i class="fas fa-shield-alt"></i> Privacy</button>
        </div>

        <form method="POST">
            <div class="rb-body-custom">
                <?php if ($msg): ?>
                    <div style="background:#dcfce7; color:#166534; padding:12px; border-radius:8px; margin-bottom:20px; font-weight:600; border:1px solid #bbf7d0;">
                        <i class="fas fa-check-circle"></i> <?= h($msg) ?>
                    </div>
                <?php endif; ?>

                <!-- 1. PERSONAL INFO -->
                <div id="tab-personal" class="rb-tab-content-custom active">
                    <div class="rb-section-card-custom">
                        <div class="rb-section-title-custom"><i class="fas fa-user"></i> 1. Personal Information</div>
                        
                        <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                            <div class="rb-photo-col-custom">
                                <div class="rb-photo-box-custom">
                                    <?php if (!empty($user['profile_photo'])): ?>
                                        <img src="<?= h($user['profile_photo']) ?>" alt="Profile">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                    <div class="rb-photo-cam-custom"><i class="fas fa-camera"></i></div>
                                </div>
                                <button type="button" class="rb-photo-btn-custom"><i class="fas fa-upload"></i> Change Photo</button>
                                <div class="rb-photo-hint-custom">JPG, PNG (Max 2MB)</div>
                            </div>

                            <div style="flex: 1; min-width: 300px;">
                                <div class="rb-grid-custom">
                                    <div class="rb-form-group-custom">
                                        <label>Full Name <span>*</span></label>
                                        <input type="text" name="name" class="rb-input-custom" value="<?= h($user['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="rb-form-group-custom">
                                        <label>Date of Birth <span>*</span></label>
                                        <div class="rb-input-icon-custom">
                                            <i class="far fa-calendar-alt"></i>
                                            <input type="date" name="dob" class="rb-input-custom" value="<?= h($user['dob'] ?? '2000-01-01') ?>" required>
                                        </div>
                                    </div>
                                    <div class="rb-form-group-custom">
                                        <label>Gender <span>*</span></label>
                                        <select name="gender" class="rb-input-custom" required>
                                            <option value="Male" <?= ($user['gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= ($user['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                                        </select>
                                    </div>
                                    <div class="rb-form-group-custom">
                                        <label>Mobile Number <span>*</span></label>
                                        <div class="rb-input-icon-custom">
                                            <i class="fas fa-phone"></i>
                                            <input type="text" name="phone" class="rb-input-custom" value="<?= h($user['phone'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    <div class="rb-form-group-custom">
                                        <label>Email Address</label>
                                        <div class="rb-input-icon-custom">
                                            <i class="fas fa-envelope"></i>
                                            <input type="email" class="rb-input-custom" value="<?= h($user['email'] ?? '') ?>" readonly style="background:#f1f5f9;">
                                        </div>
                                    </div>
                                    <div class="rb-form-group-custom">
                                        <label>Current Location</label>
                                        <div class="rb-input-icon-custom">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <input type="text" name="city" class="rb-input-custom" value="<?= h($user['city'] ?? '') ?>" placeholder="City, State">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. EDUCATION -->
                <div id="tab-education" class="rb-tab-content-custom">
                    <div class="rb-section-card-custom">
                        <div class="rb-section-title-custom"><i class="fas fa-graduation-cap"></i> 2. Education Details</div>
                        <div class="rb-grid-4-custom">
                            <div class="rb-form-group-custom">
                                <label>Highest Qualification <span>*</span></label>
                                <select class="rb-input-custom" required>
                                    <option>Graduation</option>
                                    <option>Post Graduation</option>
                                    <option>12th</option>
                                    <option>10th</option>
                                </select>
                            </div>
                            <div class="rb-form-group-custom">
                                <label>Course / Stream <span>*</span></label>
                                <input type="text" class="rb-input-custom" placeholder="B.Sc Computer Science">
                            </div>
                            <div class="rb-form-group-custom">
                                <label>Institute / College Name <span>*</span></label>
                                <input type="text" class="rb-input-custom" placeholder="University">
                            </div>
                            <div class="rb-form-group-custom">
                                <label>Passing Year <span>*</span></label>
                                <select class="rb-input-custom" required>
                                    <option>2022</option>
                                    <option>2023</option>
                                    <option>2024</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tab-skills" class="rb-tab-content-custom">
                    <div class="rb-section-card-custom">
                        <div class="rb-section-title-custom"><i class="fas fa-cog"></i> 3. Skills</div>
                        <p style="color:#64748b; font-size:0.9rem;">Skills section UI is under construction.</p>
                    </div>
                </div>

                <div id="tab-experience" class="rb-tab-content-custom">
                    <div class="rb-section-card-custom">
                        <div class="rb-section-title-custom"><i class="fas fa-briefcase"></i> 4. Experience</div>
                        <p style="color:#64748b; font-size:0.9rem;">Experience section UI is under construction.</p>
                    </div>
                </div>

                <div id="tab-objective" class="rb-tab-content-custom">
                    <div class="rb-section-card-custom">
                        <div class="rb-section-title-custom"><i class="fas fa-bullseye"></i> 5. Job Objective</div>
                        <p style="color:#64748b; font-size:0.9rem;">Objective section UI is under construction.</p>
                    </div>
                </div>

                <!-- 6. PRIVACY (NEW) -->
                <div id="tab-privacy" class="rb-tab-content-custom">
                    <div class="rb-section-card-custom">
                        <div class="rb-section-title-custom"><i class="fas fa-shield-alt"></i> 6. Privacy Settings</div>
                        <p style="color:#64748b; font-size:0.9rem; margin-bottom:20px;">
                            Control what employers can see when they view your application. Your contact details are always hidden from public view.
                        </p>

                        <label class="privacy-item" style="cursor:pointer;">
                            <div class="privacy-info">
                                <h4><i class="fas fa-phone" style="color:#2563eb;"></i> Show Phone Number</h4>
                                <p>Employers can see your phone number after you apply.</p>
                            </div>
                            <div class="switch">
                                <input type="checkbox" name="show_phone" value="1" <?= !empty($user['show_phone']) ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </div>
                        </label>

                        <label class="privacy-item" style="cursor:pointer;">
                            <div class="privacy-info">
                                <h4><i class="fas fa-envelope" style="color:#2563eb;"></i> Show Email Address</h4>
                                <p>Employers can see your email address after you apply.</p>
                            </div>
                            <div class="switch">
                                <input type="checkbox" name="show_email" value="1" <?= !empty($user['show_email']) ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </div>
                        </label>

                        <label class="privacy-item" style="cursor:pointer;">
                            <div class="privacy-info">
                                <h4><i class="fas fa-camera" style="color:#2563eb;"></i> Show Profile Photo</h4>
                                <p>Employers can see your photo on your profile.</p>
                            </div>
                            <div class="switch">
                                <input type="checkbox" name="show_photo" value="1" <?= !empty($user['show_photo']) ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </div>
                        </label>

                        <label class="privacy-item" style="cursor:pointer;">
                            <div class="privacy-info">
                                <h4><i class="fas fa-map-marker-alt" style="color:#2563eb;"></i> Show Location / Address</h4>
                                <p>Employers can see your city and address.</p>
                            </div>
                            <div class="switch">
                                <input type="checkbox" name="show_location" value="1" <?= !empty($user['show_location']) ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </div>
                        </label>

                        <label class="privacy-item" style="cursor:pointer; border-color:#86efac; background:#f0fdf4;">
                            <div class="privacy-info">
                                <h4><i class="fas fa-unlock-alt" style="color:#16a34a;"></i> Unlock Contact When Hired</h4>
                                <p>If you get hired, your full contact details will be visible to that employer (recommended).</p>
                            </div>
                            <div class="switch">
                                <input type="checkbox" name="share_on_hire" value="1" <?= !empty($user['share_on_hire']) ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </div>
                        </label>

                        <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:14px; margin-top:20px; font-size:0.85rem; color:#1e40af;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> These settings only apply to <u>employers</u> viewing your profile. Your contact details are never shown publicly on the website.
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div class="rb-footer-custom">
                <button type="button" class="rb-btn-custom rb-btn-outline-custom" onclick="prevTab()"><i class="fas fa-arrow-left"></i> Previous</button>
                <button type="submit" name="update_personal" class="rb-btn-custom rb-btn-outline-custom" style="border-color:#e5e7eb; color:#374151;"><i class="fas fa-save"></i> Save Draft</button>
                <button type="submit" name="update_privacy" class="rb-btn-custom rb-btn-primary-custom"><i class="fas fa-shield-alt"></i> Save Privacy Settings</button>
            </div>
        </form>
    </div>
</div>

<script>
    const tabIds = ['personal', 'education', 'skills', 'experience', 'objective', 'privacy'];
    let currentTabIndex = 0;

    function switchTab(tabName, btnElement) {
        document.querySelectorAll('.rb-tab-content-custom').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.rb-tab-custom').forEach(el => el.classList.remove('active'));
        
        document.getElementById('tab-' + tabName).classList.add('active');
        
        if (btnElement) {
            btnElement.classList.add('active');
        } else {
            currentTabIndex = tabIds.indexOf(tabName);
            document.querySelectorAll('.rb-tab-custom')[currentTabIndex].classList.add('active');
        }

        const nextBtn = document.querySelector('.rb-btn-primary-custom');
        if (nextBtn) {
            if (currentTabIndex < tabIds.length - 1) {
                let nextName = tabIds[currentTabIndex + 1];
                nextBtn.innerHTML = `Next: ${nextName.charAt(0).toUpperCase() + nextName.slice(1)} <i class="fas fa-arrow-right"></i>`;
            } else {
                nextBtn.innerHTML = `Finish <i class="fas fa-check"></i>`;
            }
        }
    }

    function nextTab() {
        if (currentTabIndex < tabIds.length - 1) {
            currentTabIndex++;
            switchTab(tabIds[currentTabIndex], null);
        }
    }

    function prevTab() {
        if (currentTabIndex > 0) {
            currentTabIndex--;
            switchTab(tabIds[currentTabIndex], null);
        }
    }
</script>

<?php include 'inc/foot.php'; ?>