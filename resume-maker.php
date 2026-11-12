<?php
require_once 'inc/auth.php';
require_once 'inc/site-config.php';
must_role('seeker');

// ★ Block if Resume Builder is disabled by admin
if (!defined('RESUME_BUILDER_ENABLED') || !RESUME_BUILDER_ENABLED) {
    $_SESSION['flash_msg'] = "Resume Builder is currently disabled by admin.";
    header("Location: seeker-settings.php?tab=account");
    exit;
}

$title = "Resume Maker — " . SITE_NAME;
$uid = (int)$_SESSION['uid'];

// Fetch User Data
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { header("Location: logout.php"); exit; }

include 'inc/head.php';
?>

<style>
/* =========================================
   RESUME MAKER CSS (MULTI-STEP UI)
   ========================================= */
.rb-full-wrapper { background: #f4f7fc; padding: 30px; min-height: calc(100vh - 80px); margin-top: -20px; }
.rb-top-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px; margin-bottom: 25px; }
.rb-title-area { display: flex; align-items: center; gap: 15px; }
.rb-title-icon { width: 60px; height: 60px; background: #2563eb; color: #fff; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0; }
.rb-title-area h1 { font-size: 1.8rem; color: #111827; margin: 0 0 5px; font-weight: 700; }
.rb-title-area p { color: #6b7280; margin: 0; font-size: 0.95rem; }
.rb-quick-badge { background: #fff; border: 1px solid #e5e7eb; padding: 12px 20px; border-radius: 10px; display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); flex-shrink: 0; }
.rb-quick-badge i { font-size: 1.5rem; color: #f59e0b; }
.rb-quick-badge strong { display: block; color: #111827; font-size: 0.95rem; }
.rb-quick-badge small { color: #6b7280; font-size: 0.8rem; }

.rb-stepper { display: flex; background: #fff; border-radius: 10px; overflow: hidden; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
.rb-step { flex: 1; padding: 18px 15px; text-align: center; font-weight: 600; font-size: 0.95rem; color: #6b7280; display: flex; align-items: center; justify-content: center; gap: 10px; border-right: 1px solid #f3f4f6; cursor: pointer; transition: all 0.2s; background: transparent; border-top: none; border-left: none; border-bottom: none; font-family: inherit; }
.rb-step:last-child { border-right: none; }
.rb-step:hover { background: #f9fafb; color: #2563eb; }
.rb-step.active { background: #2563eb; color: #fff; }
.rb-step.completed { background: #f0fdf4; color: #166534; }
.rb-step-num { width: 24px; height: 24px; border-radius: 50%; background: #e5e7eb; color: #6b7280; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; flex-shrink: 0; }
.rb-step.active .rb-step-num { background: rgba(255,255,255,0.2); color: #fff; }
.rb-step.completed .rb-step-num { background: #22c55e; color: #fff; }

.rb-step-content { display: none; animation: fadeIn 0.3s ease; }
.rb-step-content.active { display: block; }

.rb-options-row { display: flex; gap: 20px; margin-bottom: 30px; }
.rb-opt-card { flex: 1; background: #fff; border: 2px solid #e5e7eb; border-radius: 12px; padding: 25px 20px; text-align: center; position: relative; transition: all 0.2s; cursor: pointer; }
.rb-opt-card:hover { border-color: #2563eb; box-shadow: 0 4px 15px rgba(37,99,235,0.08); transform: translateY(-2px); }
.rb-opt-badge { position: absolute; top: 15px; right: 15px; padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 700; }
.badge-green { background: #dcfce7; color: #166534; }
.badge-pink { background: #fce7f3; color: #9d174d; }
.rb-opt-icon { width: 60px; height: 60px; margin: 0 auto 15px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; background: #eff6ff; color: #2563eb; }
.rb-opt-card h3 { font-size: 1.1rem; color: #111827; margin-bottom: 10px; }
.rb-opt-card p { color: #6b7280; font-size: 0.85rem; margin-bottom: 20px; line-height: 1.5; min-height: 40px; }
.rb-btn-sm { padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; transition: all 0.2s; }
.btn-blue-solid { background: #2563eb; color: #fff; }
.btn-blue-solid:hover { background: #1e40af; }
.btn-blue-outline { background: #fff; border: 1.5px solid #2563eb; color: #2563eb; }
.btn-blue-outline:hover { background: #eff6ff; }

.rb-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; display: none; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
.rb-modal-overlay.show { display: flex; }
.rb-modal-content { background: #fff; border-radius: 14px; width: 90%; max-width: 600px; padding: 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); position: relative; max-height: 90vh; overflow-y: auto; }
.rb-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; }
.rb-modal-header h2 { font-size: 1.4rem; margin: 0; color: #111827; }
.rb-modal-close { background: none; border: none; font-size: 1.5rem; color: #6b7280; cursor: pointer; }

.ai-chat-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; height: 300px; overflow-y: auto; margin-bottom: 20px; display: flex; flex-direction: column; gap: 15px; }
.ai-msg { display: flex; gap: 10px; max-width: 80%; }
.ai-msg.bot { align-self: flex-start; }
.ai-msg.user { align-self: flex-end; flex-direction: row-reverse; }
.ai-avatar { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; }
.ai-msg.bot .ai-avatar { background: #2563eb; color: #fff; }
.ai-msg.user .ai-avatar { background: #22c55e; color: #fff; }
.ai-bubble { background: #fff; padding: 10px 15px; border-radius: 10px; font-size: 0.9rem; color: #374151; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
.ai-msg.user .ai-bubble { background: #2563eb; color: #fff; }
.ai-input-area { display: flex; gap: 10px; }
.ai-input-area input { flex: 1; padding: 12px 15px; border: 1px solid #d1d5db; border-radius: 8px; outline: none; }

.tpl-search-bar { display: flex; gap: 10px; margin-bottom: 20px; }
.tpl-search-bar input { flex: 1; padding: 10px 15px; border: 1px solid #d1d5db; border-radius: 8px; }
.tpl-grid-modal { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }
.tpl-item-modal { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; cursor: pointer; transition: 0.2s; }
.tpl-item-modal:hover { border-color: #2563eb; transform: translateY(-2px); }
.tpl-item-modal img { width: 100%; height: 120px; object-fit: cover; background: #f3f4f6; }
.tpl-item-modal .tpl-name { padding: 8px; text-align: center; font-size: 0.8rem; font-weight: 600; color: #374151; }
.tpl-item-modal .tpl-btn { display: block; width: 100%; padding: 8px; background: #eff6ff; color: #2563eb; border: none; font-weight: 600; font-size: 0.8rem; cursor: pointer; border-top: 1px solid #e5e7eb; }

.upload-dropzone { border: 2px dashed #d1d5db; border-radius: 12px; padding: 40px 20px; text-align: center; background: #f9fafb; cursor: pointer; transition: 0.2s; margin-bottom: 20px; }
.upload-dropzone:hover { border-color: #2563eb; background: #eff6ff; }
.upload-dropzone i { font-size: 3rem; color: #9ca3af; margin-bottom: 15px; display: block; }
.upload-dropzone p { color: #6b7280; font-size: 0.95rem; margin: 0; }
.upload-dropzone span { color: #2563eb; font-weight: 600; }

.rb-editor-grid { display: grid; grid-template-columns: 1fr 380px; gap: 24px; align-items: start; }
.rb-editor-card { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden; }
.rb-editor-header { padding: 15px 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
.rb-editor-header h3 { font-size: 1.1rem; color: #111827; margin: 0; font-weight: 700; }
.rb-editor-body { display: flex; height: 100%; min-height: 500px; }
.rb-editor-tabs { width: 160px; flex-shrink: 0; border-right: 1px solid #e5e7eb; background: #f9fafb; }
.rb-ed-tab { padding: 15px; display: flex; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 600; color: #6b7280; cursor: pointer; border-left: 3px solid transparent; transition: 0.2s; }
.rb-ed-tab:hover { background: #f3f4f6; }
.rb-ed-tab.active { background: #eff6ff; color: #2563eb; border-left-color: #2563eb; }
.rb-ed-tab i { width: 18px; text-align: center; }

.rb-ed-form { padding: 25px; flex: 1; overflow-y: auto; }
.rb-ed-form .form-field { margin-bottom: 18px; }
.rb-ed-form label { font-size: 0.85rem; font-weight: 600; color: #374151; margin-bottom: 6px; display: block; }
.rb-ed-form input, .rb-ed-form select, .rb-ed-form textarea { width: 100%; padding: 11px 14px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem; outline: none; font-family: inherit; }
.rb-ed-form input:focus, .rb-ed-form select:focus, .rb-ed-form textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

.rb-ed-actions { display: flex; justify-content: flex-end; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
.btn-next-step { background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; }
.btn-next-step:hover { background: #1e40af; }

.rb-preview-card { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; padding: 20px; position: sticky; top: 90px; }
.rb-preview-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.rb-preview-header h3 { font-size: 1.1rem; color: #111827; margin: 0; font-weight: 700; }
.rb-preview-icons { display: flex; gap: 5px; }
.rb-preview-icons span { width: 30px; height: 30px; border-radius: 6px; background: #f3f4f6; color: #9ca3af; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; cursor: pointer; }
.rb-preview-icons span.active { background: #2563eb; color: #fff; }

.rb-a4-preview { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; }
.rb-a4-header { background: #1d4ed8; color: #fff; padding: 25px; display: flex; align-items: center; gap: 15px; }
.rb-a4-avatar { width: 70px; height: 70px; border-radius: 50%; background: #fff; overflow: hidden; border: 3px solid rgba(255,255,255,0.3); flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.rb-a4-avatar img { width: 100%; height: 100%; object-fit: cover; }
.rb-a4-name { font-size: 1.3rem; font-weight: 700; margin: 0 0 3px; color: #fff; line-height: 1.2; }
.rb-a4-role { font-size: 0.85rem; opacity: 0.9; margin: 0 0 6px; }
.rb-a4-contact { font-size: 0.75rem; display: flex; flex-direction: column; gap: 3px; opacity: 0.9; }

.rb-a4-body { padding: 25px; font-size: 0.8rem; color: #374151; }
.rb-a4-section { margin-bottom: 18px; }
.rb-a4-section h4 { font-size: 0.9rem; color: #1e40af; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; margin-bottom: 10px; font-weight: 700; text-transform: uppercase; }
.rb-a4-section p { margin: 0 0 5px; line-height: 1.5; }
.rb-a4-skills { display: flex; flex-wrap: wrap; gap: 6px; }
.rb-a4-skills span { background: #f3f4f6; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; color: #4b5563; }

.rb-final-step { display: grid; grid-template-columns: 1fr 300px; gap: 24px; align-items: start; }
.rb-final-preview-box { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; padding: 30px; }
.rb-final-actions-box { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; padding: 25px; position: sticky; top: 90px; }
.rb-final-actions-box h3 { font-size: 1.2rem; color: #111827; margin-bottom: 15px; font-weight: 700; }
.rb-final-actions-box p { font-size: 0.9rem; color: #6b7280; margin-bottom: 25px; line-height: 1.5; }
.rb-action-btn { width: 100%; padding: 14px; border-radius: 8px; font-weight: 700; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: 10px; cursor: pointer; border: none; margin-bottom: 12px; transition: 0.2s; }
.btn-final-download { background: #2563eb; color: #fff; }
.btn-final-download:hover { background: #1e40af; }
.btn-final-edit { background: #f3f4f6; color: #374151; }
.btn-final-edit:hover { background: #e5e7eb; }
.btn-final-share { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
.btn-final-share:hover { background: #e9d5ff; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

@media (max-width: 1024px) {
    .rb-editor-grid, .rb-final-step { grid-template-columns: 1fr; }
    .rb-preview-card, .rb-final-actions-box { position: static; }
    .rb-options-row { flex-direction: column; }
}
@media (max-width: 768px) {
    .rb-stepper { flex-direction: column; }
    .rb-step { border-right: none; border-bottom: 1px solid #f3f4f6; }
    .rb-editor-body { flex-direction: column; }
    .rb-editor-tabs { width: 100%; display: flex; overflow-x: auto; border-right: none; border-bottom: 1px solid #e5e7eb; }
    .rb-ed-tab { border-left: none; border-bottom: 3px solid transparent; white-space: nowrap; }
    .rb-ed-tab.active { border-left-color: transparent; border-bottom-color: #2563eb; }
}
</style>

<div class="rb-full-wrapper">
    
    <!-- Top Header -->
    <div class="rb-top-header">
        <div class="rb-title-area">
            <div class="rb-title-icon"><i class="fas fa-file-alt"></i></div>
            <div>
                <h1>Resume Builder</h1>
                <p>Create a professional resume easily. Choose a template, add your details, and download in PDF.</p>
            </div>
        </div>
        <div class="rb-quick-badge">
            <i class="fas fa-medal"></i>
            <div>
                <strong>Quick & Easy</strong>
                <small>No skills required. Build your resume in minutes!</small>
            </div>
        </div>
    </div>

    <!-- Stepper -->
    <div class="rb-stepper">
        <button class="rb-step active" onclick="goToStep(1)">
            <span class="rb-step-num">1</span> Choose Option
        </button>
        <button class="rb-step" onclick="goToStep(2)">
            <span class="rb-step-num">2</span> Add Details
        </button>
        <button class="rb-step" onclick="goToStep(3)">
            <span class="rb-step-num">3</span> Preview & Download
        </button>
    </div>

    <!-- STEP 1: CHOOSE OPTION -->
    <div id="step-1" class="rb-step-content active">
        <div class="rb-options-row">
            <div class="rb-opt-card" onclick="openAiModal()">
                <span class="rb-opt-badge badge-green">Recommended</span>
                <div class="rb-opt-icon"><i class="fas fa-magic"></i></div>
                <h3>Use AI Builder</h3>
                <p>Answer simple questions and let AI create your resume.</p>
                <button class="rb-btn-sm btn-blue-solid">Start AI Builder <i class="fas fa-arrow-right"></i></button>
            </div>
            <div class="rb-opt-card" onclick="openSearchModal()">
                <span class="rb-opt-badge badge-pink">New</span>
                <div class="rb-opt-icon" style="background:#fce7f3; color:#db2777;"><i class="fas fa-search"></i></div>
                <h3>Search Online</h3>
                <p>Find professional templates and import them.</p>
                <button class="rb-btn-sm btn-blue-outline" style="color:#db2777; border-color:#db2777;">Search Templates <i class="fas fa-arrow-right"></i></button>
            </div>
            <div class="rb-opt-card" onclick="openUploadModal()">
                <div class="rb-opt-icon" style="background:#e0f2fe; color:#0284c7;"><i class="fas fa-cloud-upload-alt"></i></div>
                <h3>Upload Existing</h3>
                <p>Upload your existing resume (PDF/DOC/DOCX).</p>
                <button class="rb-btn-sm btn-blue-outline" style="color:#0284c7; border-color:#0284c7;">Upload File <i class="fas fa-arrow-right"></i></button>
            </div>
        </div>
    </div>

    <!-- STEP 2: ADD DETAILS -->
    <div id="step-2" class="rb-step-content">
        <div class="rb-editor-grid">
            <div class="rb-editor-card">
                <div class="rb-editor-header">
                    <h3>Resume Editor</h3>
                    <small style="color:#6b7280;">Edit your resume with live preview</small>
                </div>
                <div class="rb-editor-body">
                    <div class="rb-editor-tabs">
                        <div class="rb-ed-tab active" onclick="switchEditorTab(this, 'personal')"><i class="fas fa-user"></i> Personal</div>
                        <div class="rb-ed-tab" onclick="switchEditorTab(this, 'education')"><i class="fas fa-graduation-cap"></i> Education</div>
                        <div class="rb-ed-tab" onclick="switchEditorTab(this, 'skills')"><i class="fas fa-cog"></i> Skills</div>
                        <div class="rb-ed-tab" onclick="switchEditorTab(this, 'experience')"><i class="fas fa-briefcase"></i> Experience</div>
                        <div class="rb-ed-tab" onclick="switchEditorTab(this, 'objective')"><i class="fas fa-bullseye"></i> Objective</div>
                    </div>
                    <div class="rb-ed-form">
                        <div id="ed-personal" class="ed-content">
                            <div class="form-field"><label>Full Name *</label><input type="text" value="<?= h($user['name']) ?>" onkeyup="updatePreview('name', this.value)"></div>
                            <div class="form-field"><label>Phone Number *</label><input type="text" value="<?= h($user['phone']) ?>" onkeyup="updatePreview('phone', this.value)"></div>
                            <div class="form-field"><label>Email Address</label><input type="email" value="<?= h($user['email']) ?>" readonly style="background:#f3f4f6;"></div>
                            <div class="form-field"><label>Location</label><input type="text" value="<?= h($user['city'] ?? '') ?>, <?= h($user['state'] ?? '') ?>" onkeyup="updatePreview('location', this.value)"></div>
                            <div class="rb-ed-actions"><button class="btn-next-step" onclick="switchTabAndNext('education')">Next: Education <i class="fas fa-arrow-right"></i></button></div>
                        </div>
                        <div id="ed-education" class="ed-content" style="display:none;">
                            <div class="form-field"><label>Highest Qualification</label><select><option>Graduation</option><option>12th</option><option>10th</option></select></div>
                            <div class="form-field"><label>Course / Stream</label><input type="text" placeholder="e.g. B.Sc Computer Science"></div>
                            <div class="form-field"><label>School / College</label><input type="text" placeholder="Institute Name"></div>
                            <div class="form-field"><label>Passing Year</label><input type="text" placeholder="e.g. 2023"></div>
                            <div class="rb-ed-actions"><button class="btn-next-step" onclick="switchTabAndNext('skills')">Next: Skills <i class="fas fa-arrow-right"></i></button></div>
                        </div>
                        <div id="ed-skills" class="ed-content" style="display:none;">
                            <div class="form-field"><label>Add Skill</label><input type="text" placeholder="e.g. MS Excel"></div>
                            <div class="form-field"><label>Skill Level</label><select><option>Beginner</option><option>Intermediate</option><option>Advanced</option></select></div>
                            <div class="rb-ed-actions"><button class="btn-next-step" onclick="switchTabAndNext('experience')">Next: Experience <i class="fas fa-arrow-right"></i></button></div>
                        </div>
                        <div id="ed-experience" class="ed-content" style="display:none;">
                            <div class="form-field"><label>Job Title</label><input type="text" placeholder="e.g. Data Entry Operator"></div>
                            <div class="form-field"><label>Company Name</label><input type="text" placeholder="e.g. ABC Pvt Ltd"></div>
                            <div class="form-field"><label>Duration</label><input type="text" placeholder="e.g. Jan 2022 - Present"></div>
                            <div class="rb-ed-actions"><button class="btn-next-step" onclick="switchTabAndNext('objective')">Next: Objective <i class="fas fa-arrow-right"></i></button></div>
                        </div>
                        <div id="ed-objective" class="ed-content" style="display:none;">
                            <div class="form-field"><label>Career Objective</label><textarea rows="5" placeholder="Write a short summary about yourself..."></textarea></div>
                            <div class="rb-ed-actions"><button class="btn-next-step" style="background:#22c55e;" onclick="goToStep(3)">Finish & Preview <i class="fas fa-check"></i></button></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rb-preview-card">
                <div class="rb-preview-header">
                    <h3>Live Preview</h3>
                    <div class="rb-preview-icons"><span class="active"><i class="fas fa-desktop"></i></span><span><i class="fas fa-tablet-alt"></i></span><span><i class="fas fa-mobile-alt"></i></span></div>
                </div>
                <div class="rb-a4-preview">
                    <div class="rb-a4-header">
                        <div class="rb-a4-avatar"><?php if(!empty($user['profile_photo'])): ?><img src="<?= h($user['profile_photo']) ?>" id="preview-photo"><?php else: ?><i class="fas fa-user" style="font-size:1.5rem; color:#2563eb;"></i><?php endif; ?></div>
                        <div><h2 class="rb-a4-name" id="preview-name"><?= h($user['name']) ?></h2><p class="rb-a4-role" id="preview-role"><?= h($user['headline'] ?: 'Job Seeker') ?></p><div class="rb-a4-contact"><span><i class="fas fa-phone"></i> <span id="preview-phone"><?= h($user['phone']) ?></span></span><span><i class="fas fa-envelope"></i> <?= h($user['email']) ?></span><span><i class="fas fa-map-marker-alt"></i> <span id="preview-location"><?= h($user['city'] ?? '') ?>, <?= h($user['state'] ?? '') ?></span></span></div></div>
                    </div>
                    <div class="rb-a4-body">
                        <div class="rb-a4-section"><h4>Career Objective</h4><p><?= h($user['about_me'] ?: 'To obtain a challenging position...') ?></p></div>
                        <div class="rb-a4-section"><h4>Education</h4><p><strong>B.Sc. Computer Science – 2022</strong><br>Dr. A.P.J. Abdul Kalam University</p></div>
                        <div class="rb-a4-section"><h4>Skills</h4><div class="rb-a4-skills"><span>MS Office</span><span>Data Entry</span><span>Communication</span></div></div>
                        <div class="rb-a4-section"><h4>Experience</h4><p><strong>Fresher</strong></p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 3: PREVIEW & DOWNLOAD -->
    <div id="step-3" class="rb-step-content">
        <div class="rb-final-step">
            <div class="rb-final-preview-box">
                <div class="rb-a4-preview" style="max-width: 100%;">
                    <div class="rb-a4-header">
                        <div class="rb-a4-avatar"><?php if(!empty($user['profile_photo'])): ?><img src="<?= h($user['profile_photo']) ?>"><?php else: ?><i class="fas fa-user" style="font-size:2rem; color:#2563eb;"></i><?php endif; ?></div>
                        <div><h2 class="rb-a4-name"><?= h($user['name']) ?></h2><p class="rb-a4-role"><?= h($user['headline'] ?: 'Job Seeker') ?></p><div class="rb-a4-contact"><span><i class="fas fa-phone"></i> <?= h($user['phone']) ?></span><span><i class="fas fa-envelope"></i> <?= h($user['email']) ?></span><span><i class="fas fa-map-marker-alt"></i> <?= h($user['city'] ?? '') ?>, <?= h($user['state'] ?? '') ?></span></div></div>
                    </div>
                    <div class="rb-a4-body" style="padding: 40px; font-size: 0.95rem;">
                        <div class="rb-a4-section"><h4>Career Objective</h4><p><?= h($user['about_me'] ?: 'To obtain a challenging position...') ?></p></div>
                        <div class="rb-a4-section"><h4>Education</h4><p><strong>B.Sc. Computer Science – 2022</strong><br>Dr. A.P.J. Abdul Kalam University</p></div>
                        <div class="rb-a4-section"><h4>Skills</h4><div class="rb-a4-skills"><span>MS Office</span><span>Data Entry</span><span>Communication</span></div></div>
                        <div class="rb-a4-section"><h4>Experience</h4><p><strong>Fresher</strong></p></div>
                    </div>
                </div>
            </div>
            <div class="rb-final-actions-box">
                <h3><i class="fas fa-download"></i> Ready to Download?</h3>
                <p>Your resume is ready. You can now download it as PDF or share it directly with employers.</p>
                <button class="rb-action-btn btn-final-download"><i class="fas fa-file-pdf"></i> Download as PDF</button>
                <button class="rb-action-btn btn-final-edit" onclick="goToStep(2)"><i class="fas fa-edit"></i> Edit Details</button>
                <button class="rb-action-btn btn-final-share"><i class="fas fa-share-alt"></i> Share Resume</button>
            </div>
        </div>
    </div>

</div>

<!-- MODALS -->
<div id="aiModal" class="rb-modal-overlay">
    <div class="rb-modal-content">
        <div class="rb-modal-header"><h2><i class="fas fa-magic" style="color:#2563eb;"></i> AI Resume Builder</h2><button class="rb-modal-close" onclick="closeModal('aiModal')"><i class="fas fa-times"></i></button></div>
        <div class="ai-chat-box" id="aiChatBox">
            <div class="ai-msg bot"><div class="ai-avatar"><i class="fas fa-robot"></i></div><div class="ai-bubble">Hello! I am your AI assistant. Let's build your resume. What is your full name?</div></div>
        </div>
        <div class="ai-input-area"><input type="text" id="aiInput" placeholder="Type your answer here..." onkeypress="handleAiInput(event)"><button class="btn-blue-solid" style="width:auto;" onclick="sendAiMessage()">Send</button></div>
    </div>
</div>

<div id="searchModal" class="rb-modal-overlay">
    <div class="rb-modal-content" style="max-width: 800px;">
        <div class="rb-modal-header"><h2><i class="fas fa-search" style="color:#db2777;"></i> Search Online Templates</h2><button class="rb-modal-close" onclick="closeModal('searchModal')"><i class="fas fa-times"></i></button></div>
        <div class="tpl-search-bar"><input type="text" placeholder="Search templates..."><button class="btn-blue-solid" style="width:auto; padding:0 20px;">Search</button></div>
        <div class="tpl-grid-modal">
            <div class="tpl-item-modal"><img src="https://via.placeholder.com/200x250?text=Modern+Blue"><div class="tpl-name">Modern Blue</div><button class="tpl-btn" onclick="useTemplate('Modern Blue')">Use Template</button></div>
            <div class="tpl-item-modal"><img src="https://via.placeholder.com/200x250?text=Clean+Pro"><div class="tpl-name">Clean Professional</div><button class="tpl-btn" onclick="useTemplate('Clean Professional')">Use Template</button></div>
            <div class="tpl-item-modal"><img src="https://via.placeholder.com/200x250?text=Simple+Elegant"><div class="tpl-name">Simple Elegant</div><button class="tpl-btn" onclick="useTemplate('Simple Elegant')">Use Template</button></div>
        </div>
    </div>
</div>

<div id="uploadModal" class="rb-modal-overlay">
    <div class="rb-modal-content" style="max-width: 500px;">
        <div class="rb-modal-header"><h2><i class="fas fa-cloud-upload-alt" style="color:#0284c7;"></i> Upload Existing Resume</h2><button class="rb-modal-close" onclick="closeModal('uploadModal')"><i class="fas fa-times"></i></button></div>
        <form action="upload-resume-process.php" method="POST" enctype="multipart/form-data">
            <div class="upload-dropzone" onclick="document.getElementById('resumeFile').click()">
                <i class="fas fa-file-upload"></i>
                <p>Drag & drop your file here or <span>browse</span></p>
                <input type="file" name="resume_file" id="resumeFile" accept=".pdf,.doc,.docx" style="display:none;" onchange="showFileName(this)">
            </div>
            <div id="filePreview" style="display:none; margin-bottom:20px; padding:10px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; font-size:0.9rem; color:#166534;"><i class="fas fa-check-circle"></i> Selected: <span id="fileName"></span></div>
            <button type="submit" class="btn-blue-solid" style="width:100%; padding:12px; font-size:1rem;"><i class="fas fa-upload"></i> Upload & Process</button>
        </form>
    </div>
</div>

<script>
    function goToStep(stepNumber) {
        document.querySelectorAll('.rb-step').forEach((btn, index) => {
            btn.classList.remove('active', 'completed');
            if (index + 1 === stepNumber) btn.classList.add('active');
            else if (index + 1 < stepNumber) btn.classList.add('completed');
        });
        document.querySelectorAll('.rb-step-content').forEach(content => content.classList.remove('active'));
        document.getElementById('step-' + stepNumber).classList.add('active');
    }
    function openAiModal() { document.getElementById('aiModal').classList.add('show'); }
    function openSearchModal() { document.getElementById('searchModal').classList.add('show'); }
    function openUploadModal() { document.getElementById('uploadModal').classList.add('show'); }
    function closeModal(id) { document.getElementById(id).classList.remove('show'); }
    function sendAiMessage() {
        let input = document.getElementById('aiInput');
        let msg = input.value.trim(); if (!msg) return;
        let chatBox = document.getElementById('aiChatBox');
        chatBox.innerHTML += `<div class="ai-msg user"><div class="ai-avatar"><i class="fas fa-user"></i></div><div class="ai-bubble">${msg}</div></div>`;
        input.value = ''; chatBox.scrollTop = chatBox.scrollHeight;
        setTimeout(() => { chatBox.innerHTML += `<div class="ai-msg bot"><div class="ai-avatar"><i class="fas fa-robot"></i></div><div class="ai-bubble">Got it! (AI Processing...)</div></div>`; chatBox.scrollTop = chatBox.scrollHeight; }, 1000);
    }
    function handleAiInput(e) { if (e.key === 'Enter') sendAiMessage(); }
    function useTemplate(name) { closeModal('searchModal'); alert(`Template "${name}" selected!`); goToStep(2); }
    function showFileName(input) { if (input.files && input.files[0]) { document.getElementById('fileName').innerText = input.files[0].name; document.getElementById('filePreview').style.display = 'block'; } }
    function switchEditorTab(tabEl, tabName) {
        document.querySelectorAll('.rb-ed-tab').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.ed-content').forEach(el => el.style.display = 'none');
        tabEl.classList.add('active'); document.getElementById('ed-' + tabName).style.display = 'block';
    }
    function switchTabAndNext(nextTabName) {
        let nextTab = Array.from(document.querySelectorAll('.rb-ed-tab')).find(el => el.getAttribute('onclick').includes(nextTabName));
        if(nextTab) switchEditorTab(nextTab, nextTabName);
    }
    function updatePreview(field, value) {
        if (field === 'name') document.getElementById('preview-name').innerText = value || 'Your Name';
        if (field === 'phone') document.getElementById('preview-phone').innerText = value || '+91 XXXX';
        if (field === 'location') document.getElementById('preview-location').innerText = value || 'City, State';
    }
</script>

<?php include 'inc/foot.php'; ?>