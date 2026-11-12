<?php
require_once 'inc/auth.php';
require_once 'inc/site-config.php';
must_role('employer');
$title = "Post a Job — " . SITE_NAME;

$uid = (int)$_SESSION['uid'];
$error = '';
$success = '';

// ============ HELPER ============
if (!function_exists('column_exists')) {
    function column_exists($db, $table, $column) {
        $table  = $db->real_escape_string($table);
        $column = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && $res->num_rows > 0;
    }
}

// ============ AUTO-CREATE DRAFT TABLE ============
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS job_drafts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employer_id INT NOT NULL,
            data JSON NOT NULL,
            step INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_emp (employer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {}

// ============ CSRF TOKEN ============
if (empty($_SESSION['csrf_job_post'])) {
    $_SESSION['csrf_job_post'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_job_post'];

// ============ FETCH EMPLOYER LOCATION ============
$stmt = $db->prepare("SELECT city, district, state FROM users WHERE id=?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$emp_loc = $stmt->get_result()->fetch_assoc();
if (!$emp_loc) { header("Location: logout.php"); exit; }

// ============ HANDLE SUBMISSION ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_job_post'], $_POST['csrf_token'])) {
        $error = "Security error. Please refresh the page.";
    } else {

        $action = $_POST['action'] ?? 'publish';

        // Collect fields
        $title_j    = trim($_POST['title'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $cat        = trim($_POST['category'] ?? '');
        $loc        = trim($_POST['location'] ?? '');
        $city       = trim($_POST['city'] ?? '');
        $district   = trim($_POST['district'] ?? '');
        $state      = trim($_POST['state'] ?? '');
        $salary     = trim($_POST['salary'] ?? '');
        $salary_type= trim($_POST['salary_type'] ?? 'monthly');
        $is_negotiable = isset($_POST['is_negotiable']) ? 1 : 0;
        $hide_salary   = isset($_POST['hide_salary']) ? 1 : 0;
        $type       = trim($_POST['job_type'] ?? 'full-time');
        $phone      = trim($_POST['contact_phone'] ?? '');
        $vacancy    = max(1, (int)($_POST['vacancies'] ?? 1));
        $min_edu    = trim($_POST['min_education'] ?? '');
        $experience = trim($_POST['experience'] ?? 'both');
        $skills     = trim($_POST['skills'] ?? '');
        $languages  = trim($_POST['languages'] ?? '');
        $last_date  = !empty($_POST['last_date']) ? $_POST['last_date'] : null;
        $app_limit  = (int)($_POST['application_limit'] ?? 0);
        $resume_req = isset($_POST['resume_required']) ? 1 : 0;

        // Walk-in / Fresher
        $is_walkin = isset($_POST['is_walkin']) ? 1 : 0;
        $walkin_date = !empty($_POST['walkin_date']) ? trim($_POST['walkin_date']) : null;
        $walkin_time = !empty($_POST['walkin_time']) ? trim($_POST['walkin_time']) : null;
        $fresher_allowed = isset($_POST['fresher_allowed']) ? 1 : 0;

        // ============ SUSPICIOUS DETECTION ============
        $is_flagged = 0;
        $flag_reason = '';
        $suspicious_keywords = ['registration fee', 'security deposit', 'pay us', 'pay money', 'advance payment', 'processing fee'];
        foreach ($suspicious_keywords as $kw) {
            if (stripos($desc, $kw) !== false) {
                $is_flagged = 1;
                $flag_reason = "Description contains suspicious keyword: '$kw'";
                break;
            }
        }

        // ============ SAVE DRAFT ============
        if ($action === 'save_draft') {
            $draft_data = json_encode([
                'title' => $title_j, 'description' => $desc, 'category' => $cat,
                'location' => $loc, 'city' => $city, 'district' => $district, 'state' => $state,
                'salary' => $salary, 'salary_type' => $salary_type,
                'is_negotiable' => $is_negotiable, 'hide_salary' => $hide_salary,
                'job_type' => $type, 'contact_phone' => $phone, 'vacancies' => $vacancy,
                'min_education' => $min_edu, 'experience' => $experience,
                'skills' => $skills, 'languages' => $languages,
                'last_date' => $last_date, 'application_limit' => $app_limit,
                'resume_required' => $resume_req,
                'is_walkin' => $is_walkin, 'walkin_date' => $walkin_date, 'walkin_time' => $walkin_time,
                'fresher_allowed' => $fresher_allowed
            ]);
            $d = $db->prepare("INSERT INTO job_drafts (employer_id, data, step) VALUES (?, ?, 6)");
            $d->bind_param('is', $uid, $draft_data);
            if ($d->execute()) {
                header("Location: employer.php?msg=" . urlencode("Job saved as draft."));
                exit;
            } else {
                $error = "Failed to save draft: " . $db->error;
            }
        }
        // ============ PUBLISH ============
        elseif ($action === 'publish') {
            // Validation
            if ($title_j === '' || $desc === '' || $cat === '' || $city === '' || $state === '' || $phone === '') {
                $error = "Please fill all required (*) fields.";
            } elseif (!preg_match('/^[0-9]{10}$/', preg_replace('/\D/', '', $phone))) {
                $error = "Contact phone must be a valid 10-digit number.";
            } elseif (strlen($desc) < 20) {
                $error = "Job description must be at least 20 characters.";
            } else {
                $final_location = $loc ?: ($city . ($district ? ', ' . $district : ''));
                $phone_clean = preg_replace('/\D/', '', $phone);

                // Build dynamic insert based on existing columns
                $cols = ['employer_id', 'title', 'description', 'category', 'location', 'city', 'district', 'state',
                         'salary', 'job_type', 'contact_phone', 'vacancies', 'status',
                         'is_walkin', 'walkin_date', 'walkin_time', 'fresher_allowed',
                         'is_flagged', 'flag_reason'];
                $vals = ['?','?','?','?','?','?','?','?','?','?','?','?',"'pending'",'?','?','?','?','?','?'];
                $types = 'issssssssssiissiis'; // 18 items (status is literal)
                $params = [$uid, $title_j, $desc, $cat, $final_location, $city, $district, $state,
                          $salary, $type, $phone_clean, $vacancy,
                          $is_walkin, $walkin_date, $walkin_time, $fresher_allowed, $is_flagged, $flag_reason];

                // Add optional columns
                $opt_cols = [
                    'salary_type'       => ['s', $salary_type],
                    'is_negotiable'     => ['i', $is_negotiable],
                    'hide_salary'       => ['i', $hide_salary],
                    'min_education'     => ['s', $min_edu],
                    'experience_level'  => ['s', $experience],
                    'skills_required'   => ['s', $skills],
                    'languages'         => ['s', $languages],
                    'last_date'         => ['s', $last_date],
                    'application_limit' => ['i', $app_limit],
                    'resume_required'   => ['i', $resume_req],
                ];
                foreach ($opt_cols as $col => $cfg) {
                    if (column_exists($db, 'jobs', $col)) {
                        $cols[] = $col;
                        $vals[] = '?';
                        $types .= $cfg[0];
                        $params[] = $cfg[1];
                    }
                }

                $sql = "INSERT INTO jobs (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
                $ins = $db->prepare($sql);

                if (!$ins) {
                    $error = "Prepare failed: " . $db->error;
                } else {
                    $ins->bind_param($types, ...$params);

                    if ($ins->execute()) {
                        $new_job_id = $ins->insert_id;
                        $ins->close();

                        // Notify admin
                        try {
                            $admin_q = $db->query("SELECT id FROM users WHERE role='admin' LIMIT 1");
                            if ($admin_row = $admin_q->fetch_assoc()) {
                                $n_title = "New Job Pending Approval";
                                $n_msg = "Employer posted: '$title_j' in $city";
                                $n_link = "admin-users.php?filter=pending_verification";
                                $ns = $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
                                $ns->bind_param('isss', $admin_row['id'], $n_title, $n_msg, $n_link);
                                $ns->execute();
                            }
                        } catch (Throwable $e) {}

                        // Clear CSRF (single-use)
                        unset($_SESSION['csrf_job_post']);
                        header("Location: employer.php?msg=" . urlencode("Job posted successfully! Waiting for admin approval."));
                        exit;
                    } else {
                        $error = "Failed to post job: " . $ins->error;
                        $ins->close();
                    }
                }
            }
        }
    }
}

include 'inc/head.php';
?>

<style>
.post-wrap { background: #f4f7fc; padding: 24px; margin: -20px; min-height: calc(100vh - 80px); }
.post-wrap * { box-sizing: border-box; }

/* Stepper */
.post-stepper { display: flex; gap: 0; background: #fff; border-radius: 12px; overflow: hidden; margin-bottom: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
.post-step-item { flex: 1; padding: 16px 10px; text-align: center; font-weight: 600; font-size: 0.85rem; color: #9ca3af; border-right: 1px solid #f3f4f6; cursor: pointer; transition: all 0.2s; background: transparent; border: none; font-family: inherit; position: relative; }
.post-step-item:last-child { border-right: none; }
.post-step-item:hover { background: #f9fafb; color: #6366f1; }
.post-step-item.active { background: #6366f1; color: #fff; }
.post-step-item.completed { background: #f0fdf4; color: #166534; }
.post-step-num { display: inline-flex; width: 26px; height: 26px; border-radius: 50%; background: #e5e7eb; color: #6b7280; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; margin-right: 6px; }
.post-step-item.active .post-step-num { background: rgba(255,255,255,0.3); color: #fff; }
.post-step-item.completed .post-step-num { background: #22c55e; color: #fff; }

/* Content */
.post-grid { display: grid; grid-template-columns: 1fr 380px; gap: 24px; align-items: start; }
.post-content { background: #fff; border-radius: 14px; padding: 28px; border: 1px solid #e5e7eb; }
.post-content h2 { font-size: 1.2rem; color: #111827; margin: 0 0 20px; padding-bottom: 12px; border-bottom: 2px solid #f3f4f6; display: flex; align-items: center; gap: 8px; }

.post-step-content { display: none; animation: fadeIn 0.3s ease; }
.post-step-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

.pg { margin-bottom: 18px; }
.pg label { display: block; font-size: 0.85rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
.pg label .req { color: #dc2626; }
.pg input, .pg select, .pg textarea { width: 100%; padding: 11px 14px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.92rem; outline: none; background: #fff; font-family: inherit; }
.pg input:focus, .pg select:focus, .pg textarea:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
.pg small { font-size: 0.75rem; color: #9ca3af; margin-top: 4px; display: block; }
.pg-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.pg-row-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }

.toggle-card { background: #f9fafb; padding: 14px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 12px; }
.toggle-card label { display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0; }
.toggle-card input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }
.toggle-card .toggle-text { flex: 1; }
.toggle-card strong { display: block; font-size: 0.92rem; color: #111827; }
.toggle-card .toggle-sub { font-size: 0.78rem; color: #6b7280; display: block; margin-top: 2px; }

/* Navigation */
.post-nav { display: flex; justify-content: space-between; gap: 10px; padding-top: 20px; margin-top: 20px; border-top: 1px solid #f3f4f6; flex-wrap: wrap; }
.post-nav-btn { padding: 11px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 6px; font-family: inherit; }
.post-nav-prev { background: #f3f4f6; color: #374151; }
.post-nav-prev:hover { background: #e5e7eb; }
.post-nav-next { background: #6366f1; color: #fff; }
.post-nav-next:hover { background: #4f46e5; }
.post-nav-draft { background: #fef3c7; color: #92400e; }
.post-nav-draft:hover { background: #fde68a; }
.post-nav-publish { background: #22c55e; color: #fff; padding: 13px 32px; font-size: 1rem; }
.post-nav-publish:hover { background: #16a34a; }

/* Preview side */
.post-preview { background: #fff; border-radius: 14px; padding: 20px; border: 1px solid #e5e7eb; position: sticky; top: 90px; }
.post-preview h3 { font-size: 1rem; color: #111827; margin: 0 0 14px; display: flex; align-items: center; gap: 8px; }
.preview-card { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border-radius: 10px; padding: 20px; color: #fff; }
.preview-card h4 { font-size: 1.1rem; margin: 0 0 4px; color: #fff; }
.preview-card .company { font-size: 0.85rem; opacity: 0.9; margin: 0 0 12px; }
.preview-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.preview-meta span { background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 12px; font-size: 0.72rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
.preview-desc { background: #f9fafb; border-radius: 8px; padding: 14px; margin-top: 14px; }
.preview-desc h5 { font-size: 0.85rem; color: #111827; margin: 0 0 8px; }
.preview-desc p { font-size: 0.8rem; color: #6b7280; margin: 0; line-height: 1.5; white-space: pre-wrap; max-height: 200px; overflow-y: auto; }

@media (max-width: 1024px) {
    .post-grid { grid-template-columns: 1fr; }
    .post-preview { position: static; }
    .post-stepper { flex-wrap: wrap; }
    .post-step-item { flex: 1 0 30%; border-bottom: 1px solid #f3f4f6; }
}
@media (max-width: 600px) {
    .post-wrap { padding: 15px; }
    .post-step-item { font-size: 0.72rem; padding: 12px 6px; }
    .post-step-item .step-label { display: none; }
    .post-step-item .post-step-num { margin-right: 0; }
    .pg-row, .pg-row-3 { grid-template-columns: 1fr; }
    .post-content { padding: 20px; }
}
</style>

<div class="post-wrap">

<div style="background:#fff; padding:20px 24px; border-radius:12px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-left:4px solid #6366f1;">
    <div>
        <h1 style="font-size:1.4rem; color:#111827; margin:0 0 4px;"><i class="fas fa-plus-circle" style="color:#6366f1;"></i> Post a New Job</h1>
        <p style="color:#6b7280; font-size:0.88rem; margin:0;">Complete all steps to publish your job post</p>
    </div>
    <a href="employer.php" class="post-nav-btn post-nav-prev"><i class="fas fa-arrow-left"></i> Dashboard</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-err" style="margin-bottom:20px;"><i class="fas fa-exclamation-circle"></i> <?= h($error) ?></div>
<?php endif; ?>

<!-- Stepper -->
<div class="post-stepper">
    <button type="button" class="post-step-item active" onclick="goStep(1)" data-step="1">
        <span class="post-step-num">1</span><span class="step-label"> Basic Info</span>
    </button>
    <button type="button" class="post-step-item" onclick="goStep(2)" data-step="2">
        <span class="post-step-num">2</span><span class="step-label"> Location</span>
    </button>
    <button type="button" class="post-step-item" onclick="goStep(3)" data-step="3">
        <span class="post-step-num">3</span><span class="step-label"> Salary</span>
    </button>
    <button type="button" class="post-step-item" onclick="goStep(4)" data-step="4">
        <span class="post-step-num">4</span><span class="step-label"> Requirements</span>
    </button>
    <button type="button" class="post-step-item" onclick="goStep(5)" data-step="5">
        <span class="post-step-num">5</span><span class="step-label"> Application</span>
    </button>
    <button type="button" class="post-step-item" onclick="goStep(6)" data-step="6">
        <span class="post-step-num">6</span><span class="step-label"> Preview</span>
    </button>
</div>

<form method="POST" id="postForm" onsubmit="return validateFinal(event)">
    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
    <input type="hidden" name="action" id="formAction" value="publish">

    <div class="post-grid">
        
        <!-- LEFT: Form Content -->
        <div class="post-content">

            <!-- STEP 1: Basic Info -->
            <div class="post-step-content active" id="step-1">
                <h2><i class="fas fa-info-circle" style="color:#6366f1;"></i> Basic Information</h2>
                
                <div class="pg">
                    <label>Job Title <span class="req">*</span></label>
                    <input type="text" name="title" id="in_title" placeholder="e.g. Shop Salesman, Delivery Boy" required maxlength="150">
                </div>
                
                <div class="pg-row">
                    <div class="pg">
                        <label>Category <span class="req">*</span></label>
                        <select name="category" id="in_category" required>
                            <option value="">-- Select --</option>
                            <?php foreach (['Sales','Driver','Teacher','Helper','Technician','Office','Security','Cooking','Others'] as $c): ?>
                                <option value="<?= $c ?>"><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="pg">
                        <label>Job Type</label>
                        <select name="job_type" id="in_job_type">
                            <option value="full-time">Full Time</option>
                            <option value="part-time">Part Time</option>
                            <option value="daily-wage">Daily Wage</option>
                            <option value="contract">Contract</option>
                        </select>
                    </div>
                </div>
                
                <div class="pg">
                    <label>Number of Vacancies <span class="req">*</span></label>
                    <input type="number" name="vacancies" id="in_vacancies" min="1" max="100" value="1" required>
                </div>
                
                <div class="pg">
                    <label>Job Description <span class="req">*</span> <small style="display:inline; color:#9ca3af;">(min 20 characters)</small></label>
                    <textarea name="description" id="in_description" rows="5" placeholder="Describe the role, duties, timings, requirements..." required minlength="20"></textarea>
                </div>
            </div>

            <!-- STEP 2: Location -->
            <div class="post-step-content" id="step-2">
                <h2><i class="fas fa-map-marker-alt" style="color:#6366f1;"></i> Job Location</h2>
                
                <div class="pg-row-3">
                    <div class="pg">
                        <label>City <span class="req">*</span></label>
                        <input type="text" name="city" id="in_city" placeholder="e.g. Nichlaul" required maxlength="100" value="<?= h($emp_loc['city'] ?? '') ?>">
                    </div>
                    <div class="pg">
                        <label>District</label>
                        <input type="text" name="district" id="in_district" placeholder="e.g. Maharajganj" maxlength="100" value="<?= h($emp_loc['district'] ?? '') ?>">
                    </div>
                    <div class="pg">
                        <label>State <span class="req">*</span></label>
                        <input type="text" name="state" id="in_state" placeholder="e.g. Uttar Pradesh" required maxlength="100" value="<?= h($emp_loc['state'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="pg">
                    <label>Landmark / Full Address (Optional)</label>
                    <input type="text" name="location" id="in_location" placeholder="e.g. Main Market, Near Bus Stand" maxlength="255">
                </div>
            </div>

            <!-- STEP 3: Salary -->
            <div class="post-step-content" id="step-3">
                <h2><i class="fas fa-money-bill-wave" style="color:#6366f1;"></i> Salary Information</h2>
                
                <div class="pg-row">
                    <div class="pg">
                        <label>Salary Type</label>
                        <select name="salary_type" id="in_salary_type">
                            <option value="monthly">Monthly</option>
                            <option value="weekly">Weekly</option>
                            <option value="daily">Daily</option>
                            <option value="hourly">Hourly</option>
                        </select>
                    </div>
                    <div class="pg">
                        <label>Salary Amount</label>
                        <input type="text" name="salary" id="in_salary" placeholder="e.g. ₹10,000 - ₹15,000" maxlength="80">
                    </div>
                </div>
                
                <div class="toggle-card">
                    <label>
                        <input type="checkbox" name="is_negotiable" id="in_negotiable">
                        <div class="toggle-text">
                            <strong>Salary is Negotiable</strong>
                            <span class="toggle-sub">Allow candidates to negotiate</span>
                        </div>
                    </label>
                </div>
                
                <div class="toggle-card">
                    <label>
                        <input type="checkbox" name="hide_salary" id="in_hide_salary">
                        <div class="toggle-text">
                            <strong>Hide Salary from Public</strong>
                            <span class="toggle-sub">Salary will not be shown in job listing</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- STEP 4: Requirements -->
            <div class="post-step-content" id="step-4">
                <h2><i class="fas fa-user-graduate" style="color:#6366f1;"></i> Candidate Requirements</h2>
                
                <div class="pg-row">
                    <div class="pg">
                        <label>Minimum Education</label>
                        <select name="min_education" id="in_min_education">
                            <option value="">No requirement</option>
                            <option value="10th">10th Pass</option>
                            <option value="12th">12th Pass</option>
                            <option value="Graduation">Graduation</option>
                            <option value="Post Graduation">Post Graduation</option>
                            <option value="Diploma">Diploma</option>
                        </select>
                    </div>
                    <div class="pg">
                        <label>Experience Level</label>
                        <select name="experience" id="in_experience">
                            <option value="fresher">Fresher Only</option>
                            <option value="experienced">Experienced Only</option>
                            <option value="both" selected>Both (Fresher + Experienced)</option>
                        </select>
                    </div>
                </div>
                
                <div class="pg">
                    <label>Required Skills</label>
                    <input type="text" name="skills" id="in_skills" placeholder="e.g. MS Excel, Hindi Speaking, Communication">
                    <small>Separate with commas</small>
                </div>
                
                <div class="pg">
                    <label>Languages</label>
                    <input type="text" name="languages" id="in_languages" placeholder="e.g. Hindi, English">
                </div>
                
                <div class="toggle-card">
                    <label>
                        <input type="checkbox" name="fresher_allowed" id="in_fresher" checked>
                        <div class="toggle-text">
                            <strong>Fresher Allowed</strong>
                            <span class="toggle-sub">Candidates with no experience can apply</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- STEP 5: Application Settings -->
            <div class="post-step-content" id="step-5">
                <h2><i class="fas fa-cog" style="color:#6366f1;"></i> Application Settings</h2>
                
                <div class="pg-row">
                    <div class="pg">
                        <label>Last Date to Apply</label>
                        <input type="date" name="last_date" id="in_last_date" min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="pg">
                        <label>Application Limit (0 = unlimited)</label>
                        <input type="number" name="application_limit" id="in_app_limit" min="0" value="0">
                    </div>
                </div>
                
                <div class="pg">
                    <label>Contact Phone <span class="req">*</span></label>
                    <input type="tel" name="contact_phone" id="in_contact_phone" placeholder="e.g. 9876543210" required pattern="[0-9+\-\s]{10,15}" maxlength="20">
                </div>
                
                <div class="toggle-card">
                    <label>
                        <input type="checkbox" name="resume_required" id="in_resume_required">
                        <div class="toggle-text">
                            <strong>Resume Required</strong>
                            <span class="toggle-sub">Candidates must upload CV to apply</span>
                        </div>
                    </label>
                </div>
                
                <div class="toggle-card" style="background:#fef3c7; border-color:#fde68a;">
                    <label>
                        <input type="checkbox" name="is_walkin" id="in_walkin" onchange="toggleWalkin()">
                        <div class="toggle-text">
                            <strong>Walk-in Interview</strong>
                            <span class="toggle-sub">Candidates can come directly for interview</span>
                        </div>
                    </label>
                </div>
                
                <div id="walkinFields" style="display:none;">
                    <div class="pg-row">
                        <div class="pg">
                            <label>Walk-in Date</label>
                            <input type="date" name="walkin_date" id="in_walkin_date" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="pg">
                            <label>Walk-in Time</label>
                            <input type="text" name="walkin_time" id="in_walkin_time" placeholder="e.g. 10 AM - 4 PM" maxlength="50">
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 6: Preview -->
            <div class="post-step-content" id="step-6">
                <h2><i class="fas fa-eye" style="color:#6366f1;"></i> Review & Publish</h2>
                
                <div style="background:#f0fdf4; border:1px solid #86efac; padding:16px; border-radius:10px; margin-bottom:20px;">
                    <p style="margin:0; color:#166534; font-size:0.9rem;">
                        <i class="fas fa-check-circle"></i> 
                        <strong>Ready to publish!</strong> Review the preview on the right side.
                    </p>
                </div>
                
                <div id="summaryList" style="background:#f9fafb; padding:16px; border-radius:10px; font-size:0.88rem; line-height:1.8; color:#374151;"></div>
                
                <div style="background:#fef3c7; border:1px solid #fde68a; padding:16px; border-radius:10px; margin-top:20px;">
                    <p style="margin:0; color:#92400e; font-size:0.85rem;">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Note:</strong> Your job will be reviewed by admin before being published publicly.
                    </p>
                </div>
            </div>

            <!-- Navigation Buttons -->
            <div class="post-nav">
                <button type="button" class="post-nav-btn post-nav-prev" id="btnPrev" onclick="prevStep()" style="display:none;">
                    <i class="fas fa-arrow-left"></i> Previous
                </button>
                
                <div style="margin-left:auto; display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="button" class="post-nav-btn post-nav-draft" onclick="submitDraft()">
                        <i class="fas fa-save"></i> Save Draft
                    </button>
                    <button type="button" class="post-nav-btn post-nav-next" id="btnNext" onclick="nextStep()">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" class="post-nav-btn post-nav-publish" id="btnPublish" style="display:none;">
                        <i class="fas fa-paper-plane"></i> Publish Job
                    </button>
                </div>
            </div>
        </div>

        <!-- RIGHT: Live Preview -->
        <div class="post-preview">
            <h3><i class="fas fa-eye" style="color:#6366f1;"></i> Live Preview</h3>
            
            <div class="preview-card">
                <h4 id="pv_title">Job Title</h4>
                <p class="company"><i class="fas fa-building"></i> <?= h($_SESSION['name'] ?? 'Your Company') ?></p>
                <div class="preview-meta">
                    <span id="pv_category"><i class="fas fa-tag"></i> Category</span>
                    <span id="pv_city"><i class="fas fa-map-marker-alt"></i> City</span>
                    <span id="pv_type"><i class="fas fa-clock"></i> Full Time</span>
                    <span id="pv_salary"><i class="fas fa-money-bill"></i> Salary</span>
                </div>
            </div>
            
            <div class="preview-desc">
                <h5>Job Description</h5>
                <p id="pv_desc">Your job description will appear here...</p>
            </div>
            
            <div style="margin-top:14px;">
                <h5 style="font-size:0.82rem; color:#111827; margin:0 0 8px;">Vacancies</h5>
                <p id="pv_vacancies" style="font-size:0.85rem; color:#6b7280; margin:0;">1</p>
            </div>
        </div>
    </div>
</form>

</div>

<script>
let currentStep = 1;
const totalSteps = 6;

// Form fields to bind for live preview
const bindings = {
    'in_title': 'pv_title',
    'in_category': 'pv_category',
    'in_city': 'pv_city',
    'in_job_type': 'pv_type',
    'in_salary': 'pv_salary',
    'in_description': 'pv_desc',
    'in_vacancies': 'pv_vacancies'
};

document.addEventListener('DOMContentLoaded', function() {
    Object.keys(bindings).forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', updatePreview);
            el.addEventListener('change', updatePreview);
        }
    });
    updatePreview();
});

function updatePreview() {
    document.getElementById('pv_title').textContent = document.getElementById('in_title').value || 'Job Title';
    document.getElementById('pv_category').innerHTML = '<i class="fas fa-tag"></i> ' + (document.getElementById('in_category').value || 'Category');
    document.getElementById('pv_city').innerHTML = '<i class="fas fa-map-marker-alt"></i> ' + (document.getElementById('in_city').value || 'City');
    
    const typeMap = {'full-time':'Full Time','part-time':'Part Time','daily-wage':'Daily Wage','contract':'Contract'};
    document.getElementById('pv_type').innerHTML = '<i class="fas fa-clock"></i> ' + (typeMap[document.getElementById('in_job_type').value] || 'Type');
    
    const salary = document.getElementById('in_salary').value;
    document.getElementById('pv_salary').innerHTML = '<i class="fas fa-money-bill"></i> ' + (salary || 'Negotiable');
    
    const desc = document.getElementById('in_description').value;
    document.getElementById('pv_desc').textContent = desc || 'Your job description will appear here...';
    
    document.getElementById('pv_vacancies').textContent = document.getElementById('in_vacancies').value || '1';
}

function goStep(step) {
    if (step < 1 || step > totalSteps) return;
    currentStep = step;
    
    document.querySelectorAll('.post-step-content').forEach(el => el.classList.remove('active'));
    document.getElementById('step-' + step).classList.add('active');
    
    document.querySelectorAll('.post-step-item').forEach(el => {
        el.classList.remove('active', 'completed');
        const s = parseInt(el.dataset.step);
        if (s === step) el.classList.add('active');
        else if (s < step) el.classList.add('completed');
    });
    
    document.getElementById('btnPrev').style.display = step > 1 ? 'inline-flex' : 'none';
    document.getElementById('btnNext').style.display = step < totalSteps ? 'inline-flex' : 'none';
    document.getElementById('btnPublish').style.display = step === totalSteps ? 'inline-flex' : 'none';
    
    if (step === totalSteps) buildSummary();
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextStep() {
    if (!validateStep(currentStep)) return;
    if (currentStep < totalSteps) goStep(currentStep + 1);
}

function prevStep() {
    if (currentStep > 1) goStep(currentStep - 1);
}

function validateStep(step) {
    if (step === 1) {
        const title = document.getElementById('in_title').value.trim();
        const cat = document.getElementById('in_category').value;
        const desc = document.getElementById('in_description').value.trim();
        if (!title) { alert('Please enter job title.'); return false; }
        if (!cat) { alert('Please select category.'); return false; }
        if (desc.length < 20) { alert('Description must be at least 20 characters.'); return false; }
    }
    if (step === 2) {
        const city = document.getElementById('in_city').value.trim();
        const state = document.getElementById('in_state').value.trim();
        if (!city) { alert('Please enter city.'); return false; }
        if (!state) { alert('Please enter state.'); return false; }
    }
    if (step === 5) {
        const phone = document.getElementById('in_contact_phone').value.trim();
        const digits = phone.replace(/\D/g, '');
        if (digits.length !== 10) { alert('Contact phone must be 10 digits.'); return false; }
    }
    return true;
}

function validateFinal(e) {
    // Validate all steps
    for (let s = 1; s <= 5; s++) {
        if (!validateStep(s)) {
            goStep(s);
            e.preventDefault();
            return false;
        }
    }
    return true;
}

function submitDraft() {
    document.getElementById('formAction').value = 'save_draft';
    document.getElementById('postForm').submit();
}

function toggleWalkin() {
    const chk = document.getElementById('in_walkin').checked;
    document.getElementById('walkinFields').style.display = chk ? 'block' : 'none';
}

function buildSummary() {
    const fields = [
        ['Job Title', document.getElementById('in_title').value],
        ['Category', document.getElementById('in_category').value],
        ['Job Type', document.getElementById('in_job_type').value],
        ['Vacancies', document.getElementById('in_vacancies').value],
        ['City', document.getElementById('in_city').value],
        ['District', document.getElementById('in_district').value],
        ['State', document.getElementById('in_state').value],
        ['Salary', document.getElementById('in_salary').value || 'Not specified'],
        ['Contact', document.getElementById('in_contact_phone').value],
    ];
    
    let html = '';
    fields.forEach(([label, value]) => {
        if (value) {
            html += `<div><strong style="color:#111827; display:inline-block; min-width:120px;">${label}:</strong> ${value}</div>`;
        }
    });
    document.getElementById('summaryList').innerHTML = html;
}
</script>

<?php include 'inc/foot.php'; ?>