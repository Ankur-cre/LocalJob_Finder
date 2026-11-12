<?php
require_once 'inc/auth.php';
must_role('seeker');

$uid = (int)$_SESSION['uid'];
$action = $_POST['action'] ?? '';
$redirect = 'seeker-settings.php?tab=profile';

// ============ UPDATE PERSONAL INFO ============
if ($action === 'update_personal_info') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $gender = trim($_POST['gender'] ?? '');
    $city = trim($_POST['city'] ?? '');

    if ($name !== '' && $phone !== '') {
        $stmt = $db->prepare("UPDATE users SET name=?, phone=?, dob=?, gender=?, city=? WHERE id=?");
        $stmt->bind_param('sssssi', $name, $phone, $dob, $gender, $city, $uid);
        $stmt->execute();
    }
    header("Location: $redirect&pmsg=" . urlencode("Personal info updated!"));
    exit;
}

// ============ ADD EDUCATION ============
if ($action === 'add_education') {
    $qual = trim($_POST['qualification'] ?? '');
    $stream = trim($_POST['stream'] ?? '');
    $school = trim($_POST['school_name'] ?? '');
    $uni = trim($_POST['university'] ?? '');
    $year = trim($_POST['passing_year'] ?? '');
    $perc = trim($_POST['percentage'] ?? '');
    $status = $_POST['status'] ?? 'Completed';

    if ($qual !== '' && $school !== '' && $year !== '') {
        $stmt = $db->prepare("INSERT INTO seeker_education (seeker_id, qualification, stream, school_name, university, passing_year, percentage, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssssss', $uid, $qual, $stream, $school, $uni, $year, $perc, $status);
        $stmt->execute();
    }
    header("Location: $redirect&pmsg=" . urlencode("Education added!"));
    exit;
}

// ============ DELETE EDUCATION ============
if ($action === 'delete_education') {
    $edu_id = (int)($_POST['edu_id'] ?? 0);
    $stmt = $db->prepare("DELETE FROM seeker_education WHERE id=? AND seeker_id=?");
    $stmt->bind_param('ii', $edu_id, $uid);
    $stmt->execute();
    header("Location: $redirect&pmsg=" . urlencode("Education deleted."));
    exit;
}

// ============ ADD SKILL ============
if ($action === 'add_skill') {
    $skill = trim($_POST['skill_name'] ?? '');
    $level = $_POST['skill_level'] ?? 'Beginner';
    $cat = $_POST['category'] ?? 'General';

    if ($skill !== '') {
        $stmt = $db->prepare("INSERT INTO seeker_skills (seeker_id, skill_name, skill_level, category) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $uid, $skill, $level, $cat);
        $stmt->execute();
    }
    header("Location: $redirect&pmsg=" . urlencode("Skill added!"));
    exit;
}

// ============ DELETE SKILL ============
if ($action === 'delete_skill') {
    $sid = (int)($_POST['skill_id'] ?? 0);
    $stmt = $db->prepare("DELETE FROM seeker_skills WHERE id=? AND seeker_id=?");
    $stmt->bind_param('ii', $sid, $uid);
    $stmt->execute();
    header("Location: $redirect&pmsg=" . urlencode("Skill removed."));
    exit;
}

// ============ ADD EXPERIENCE ============
if ($action === 'add_experience') {
    $title = trim($_POST['job_title'] ?? '');
    $company = trim($_POST['company_name'] ?? '');
    $type = $_POST['employment_type'] ?? 'Full-Time';
    $start = $_POST['start_date'] ?? null;
    $end = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $is_current = isset($_POST['is_current']) ? (int)$_POST['is_current'] : 0;
    $desc = trim($_POST['description'] ?? '');

    if ($title !== '' && $company !== '' && $start) {
        $stmt = $db->prepare("INSERT INTO seeker_experience (seeker_id, job_title, company_name, employment_type, start_date, end_date, is_current, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssssi', $uid, $title, $company, $type, $start, $end, $is_current, $desc);
        $stmt->execute();
    }
    header("Location: $redirect&pmsg=" . urlencode("Experience added!"));
    exit;
}

// ============ DELETE EXPERIENCE ============
if ($action === 'delete_experience') {
    $eid = (int)($_POST['exp_id'] ?? 0);
    $stmt = $db->prepare("DELETE FROM seeker_experience WHERE id=? AND seeker_id=?");
    $stmt->bind_param('ii', $eid, $uid);
    $stmt->execute();
    header("Location: $redirect&pmsg=" . urlencode("Experience deleted."));
    exit;
}

// ============ UPDATE PREFERENCES ============
if ($action === 'update_preferences') {
    $pref_type = $_POST['pref_job_type'] ?? 'full-time';
    $pref_loc = trim($_POST['pref_location'] ?? '');
    $pref_sal = trim($_POST['pref_expected_salary'] ?? '');

    $db->query("INSERT IGNORE INTO user_settings (user_id) VALUES ($uid)");
    
    $stmt = $db->prepare("UPDATE user_settings SET pref_job_type=?, pref_location=?, pref_expected_salary=? WHERE user_id=?");
    $stmt->bind_param('sssi', $pref_type, $pref_loc, $pref_sal, $uid);
    $stmt->execute();
    header("Location: $redirect&pmsg=" . urlencode("Preferences saved!"));
    exit;
}

// ============ UPDATE ABOUT ME ============
if ($action === 'update_about') {
    $headline = trim($_POST['headline'] ?? '');
    $about = trim($_POST['about_me'] ?? '');

    $stmt = $db->prepare("UPDATE users SET headline=?, about_me=? WHERE id=?");
    $stmt->bind_param('ssi', $headline, $about, $uid);
    $stmt->execute();
    header("Location: $redirect&pmsg=" . urlencode("About Me saved!"));
    exit;
}

// Default
header("Location: $redirect");
exit;
?>