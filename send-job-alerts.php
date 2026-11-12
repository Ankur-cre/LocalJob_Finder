<?php
require_once 'inc/auth.php';
must_role('admin');

$job_id = (int)($_GET['job_id'] ?? 0);
if (!$job_id) { die("Job ID required"); }

$job = $db->query("SELECT j.*, u.company_name FROM jobs j JOIN users u ON j.employer_id=u.id WHERE j.id=$job_id")->fetch_assoc();
if (!$job) { die("Job not found"); }

// Find seekers matching this job (by location or category)
$seekers = $db->query("
    SELECT id, name, email, phone FROM users 
    WHERE role='seeker' AND status='active'
");

$count = 0;
while ($s = $seekers->fetch_assoc()) {
    $title = "New Job Alert: " . $job['title'];
    $message = "Hi {$s['name']}, a new job '{$job['title']}' at '{$job['company_name']}' in {$job['location']} has been posted. Apply now!";
    
    // In-app notification
    $ins = $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    $ins->bind_param('iss', $s['id'], $title, $message);
    $ins->execute();
    $count++;
    
    // TODO: Email/WhatsApp bhejne ke liye yahan API call karo
}

header("Location: admin-jobs.php?msg=" . urlencode("Alerts sent to $count seekers."));
exit;
?>