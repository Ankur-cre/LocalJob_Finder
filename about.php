<?php
require_once 'inc/auth.php';
require_once 'inc/site-config.php';
$title = "About Us";

include 'inc/head.php';

$page_title = "About Local Jobs";
$page_intro = "Connecting local talent with local opportunities.";
$page_icon  = "fa-info-circle";
legal_page_start();
?>

<p>Local Jobs is a local employment discovery platform designed to connect job seekers with employment opportunities in their nearby areas.</p>

<h2><i class="fas fa-users"></i> Who We Are</h2>
<p>We are a small team focused on making local hiring easier, more transparent, and more accessible for small towns and their surrounding communities.</p>

<h2><i class="fas fa-bullseye"></i> Our Mission</h2>
<p>To help job seekers discover opportunities close to home and to help local employers connect with suitable candidates quickly and fairly.</p>

<h2><i class="fas fa-eye"></i> Our Vision</h2>
<p>A future where no local job opportunity is missed because of missing information or distance, and where small businesses can hire confidently and quickly.</p>

<h2><i class="fas fa-gift"></i> What We Offer</h2>
<ul>
    <li>A simple, mobile-friendly job portal</li>
    <li>Verified employer accounts with moderation</li>
    <li>Easy job browsing, filtering and applying</li>
    <li>Resume upload (with automatic compression)</li>
    <li>Application tracking for job seekers</li>
    <li>Applicant management for employers</li>
    <li>Safety guidelines and reporting tools</li>
</ul>

<h2><i class="fas fa-user-tie"></i> For Job Seekers</h2>
<p>Search jobs by title, category or location, view full job details, and apply with one click. Track your application status in your personal dashboard.</p>

<h2><i class="fas fa-building"></i> For Employers</h2>
<p>Register your business, post jobs after admin verification, and manage applications in a clean dashboard — all from a browser, no special software required.</p>

<h2><i class="fas fa-map-marked-alt"></i> Our Target Locations</h2>
<ul>
    <li>Nichlaul</li>
    <li>Maharajganj</li>
    <li>Gorakhpur</li>
    <li>Nearby areas in Uttar Pradesh</li>
</ul>

<h2><i class="fas fa-shield-alt"></i> Safety and Trust</h2>
<p>We encourage both job seekers and employers to follow the best practices listed in our <a href="safety.php">Safety Guidelines</a>. If you encounter a suspicious job or employer, please use our <a href="safety.php#report">Report</a> option or email <a href="mailto:<?= h(SUPPORT_EMAIL) ?>"><?= h(SUPPORT_EMAIL) ?></a>.</p>

<h2><i class="fas fa-envelope"></i> Contact Information</h2>
<ul>
    <li><strong>Email:</strong> <a href="mailto:<?= h(SUPPORT_EMAIL) ?>"><?= h(SUPPORT_EMAIL) ?></a></li>
    <li><strong>Phone:</strong> <?= h(SUPPORT_PHONE) ?></li>
    <li><strong>Address:</strong> <?= h(SITE_ADDRESS) ?></li>
    <li><strong>Hours:</strong> <?= h(SUPPORT_HOURS) ?></li>
</ul>

<?php legal_page_end(); ?>
<?php include 'inc/foot.php'; ?>