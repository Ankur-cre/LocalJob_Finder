<?php
require_once 'inc/auth.php';

// सुनिश्चित करें कि यूजर लॉगिन है और सीकर है
if (!logged_in() || role() !== 'seeker') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$uid = (int)$_SESSION['uid'];
$action = $_POST['action'] ?? '';
$job_id = (int)($_POST['job_id'] ?? 0);

if ($job_id <= 0) {
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'jobs.php'));
    exit;
}

// चेक करें कि जॉब असली है या नहीं
$job_check = $db->prepare("SELECT id FROM jobs WHERE id = ? AND status = 'active'");
$job_check->bind_param('i', $job_id);
$job_check->execute();
if ($job_check->get_result()->num_rows === 0) {
    $_SESSION['flash_msg'] = "Job not found or expired.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'jobs.php'));
    exit;
}

if ($action === 'save') {
    // चेक करें कि पहले से सेव तो नहीं है
    $check = $db->prepare("SELECT id FROM saved_jobs WHERE seeker_id = ? AND job_id = ?");
    $check->bind_param('ii', $uid, $job_id);
    $check->execute();
    
    if ($check->get_result()->num_rows === 0) {
        // सेव करें
        $stmt = $db->prepare("INSERT INTO saved_jobs (seeker_id, job_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $uid, $job_id);
        if ($stmt->execute()) {
            $_SESSION['flash_msg'] = "Job saved successfully!";
        } else {
            $_SESSION['flash_msg'] = "Failed to save job.";
        }
    } else {
        $_SESSION['flash_msg'] = "Job is already saved.";
    }
} 
elseif ($action === 'remove') {
    // हटाएं
    $stmt = $db->prepare("DELETE FROM saved_jobs WHERE seeker_id = ? AND job_id = ?");
    $stmt->bind_param('ii', $uid, $job_id);
    if ($stmt->execute()) {
        $_SESSION['flash_msg'] = "Job removed from saved list.";
    } else {
        $_SESSION['flash_msg'] = "Failed to remove job.";
    }
} 
elseif ($action === 'toggle') {
    // अगर AJAX से टॉगल करना हो (बिना पेज रीलोड के)
    $check = $db->prepare("SELECT id FROM saved_jobs WHERE seeker_id = ? AND job_id = ?");
    $check->bind_param('ii', $uid, $job_id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        // पहले से सेव है, तो हटाओ
        $stmt = $db->prepare("DELETE FROM saved_jobs WHERE seeker_id = ? AND job_id = ?");
        $stmt->bind_param('ii', $uid, $job_id);
        $stmt->execute();
        echo json_encode(['status' => 'removed', 'message' => 'Job unsaved']);
    } else {
        // सेव नहीं है, तो सेव करो
        $stmt = $db->prepare("INSERT INTO saved_jobs (seeker_id, job_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $uid, $job_id);
        $stmt->execute();
        echo json_encode(['status' => 'saved', 'message' => 'Job saved']);
    }
    exit;
}

// रीडायरेक्ट बैक
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'jobs.php'));
exit;
?>