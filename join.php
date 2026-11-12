<?php
require_once 'inc/auth.php';
$title = "Join";

if (logged_in()) { header("Location: index.php"); exit; }

include 'inc/head.php';
?>

<div class="join-hero">
    <h1><i class="fas fa-briefcase"></i> Join <?= SITE_NAME ?></h1>
    <p>Choose how you want to get started</p>
</div>

<div class="join-cards">
    <a href="reg-seeker.php" class="join-card join-card-green">
        <div class="join-icon"><i class="fas fa-user-tie"></i></div>
        <h3>I'm a Job Seeker</h3>
        <p>Find local jobs, apply with your resume, and get hired near you.</p>
        <span class="join-btn">Register as Seeker <i class="fas fa-arrow-right"></i></span>
    </a>

    <a href="reg-employer.php" class="join-card join-card-blue">
        <div class="join-icon"><i class="fas fa-building"></i></div>
        <h3>I'm an Employer</h3>
        <p>Post jobs, review applications, and hire talent for your business.</p>
        <span class="join-btn">Register as Employer <i class="fas fa-arrow-right"></i></span>
    </a>
</div>

<div class="join-login">
    Already have an account?
    <a href="login.php" class="btn-blue btn-sm">
        <i class="fas fa-sign-in-alt"></i> Login
    </a>
</div>

<?php include 'inc/foot.php'; ?>