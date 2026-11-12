<?php
require_once 'inc/auth.php';
require_once 'inc/site-config.php';
$title = "Terms of Use";

include 'inc/head.php';

$page_title = "Terms of Use";
$page_intro = "Rules for using Local Jobs as a job seeker, employer, or visitor.";
$page_icon  = "fa-file-contract";
legal_page_start();
?>

<p>By accessing or using Local Jobs, you agree to these Terms of Use. If you do not agree, please discontinue use of the platform.</p>

<h2><i class="fas fa-handshake"></i> 1. Acceptance of Terms</h2>
<p>These terms form a binding agreement between you and Local Jobs regarding your use of the platform.</p>

<h2><i class="fas fa-info-circle"></i> 2. About Local Jobs</h2>
<p>Local Jobs is a local employment discovery platform helping job seekers find work in nearby areas, and employers connect with suitable candidates in <?= h(SITE_ADDRESS) ?> and surrounding regions.</p>

<h2><i class="fas fa-check-circle"></i> 3. Eligibility</h2>
<p>You must be at least 18 years old to register. By using Local Jobs you confirm that you meet this requirement and that all information you provide is accurate.</p>

<h2><i class="fas fa-user-plus"></i> 4. Account Registration</h2>
<p>You are responsible for maintaining the confidentiality of your account credentials. You agree not to share your account with others. Employer accounts require admin approval before job posting is enabled.</p>

<h2><i class="fas fa-clipboard-check"></i> 5. Accurate Information</h2>
<p>You agree to provide truthful, accurate, and current information. Providing false information may result in account suspension or termination.</p>

<h2><i class="fas fa-user-tie"></i> 6. Job Seeker Responsibilities</h2>
<ul>
    <li>Provide accurate personal and professional details</li>
    <li>Upload only genuine resumes</li>
    <li>Do not impersonate another person</li>
    <li>Do not misuse employer contact information for spam or harassment</li>
    <li>Follow applicable laws of India</li>
</ul>

<h2><i class="fas fa-building"></i> 7. Employer Responsibilities</h2>
<ul>
    <li>Provide genuine business information</li>
    <li>Post only legitimate job opportunities</li>
    <li>Mention accurate salary, location, and job conditions</li>
    <li>Do not demand unauthorized recruitment fees from candidates</li>
    <li>Do not post misleading or discriminatory advertisements</li>
    <li>Do not misuse candidate resumes or personal data</li>
</ul>

<h2><i class="fas fa-briefcase"></i> 8. Job Posting Rules</h2>
<p>Job posts must be relevant to genuine employment, must not include contact details of third parties without consent, and must be free of prohibited content. Local Jobs may reject or remove any job posting for any reason.</p>

<h2><i class="fas fa-ban"></i> 9. Prohibited Activities</h2>
<ul>
    <li>Fake job advertisements or fraudulent recruitment</li>
    <li>Spam, phishing, or harassment</li>
    <li>Identity theft or impersonation</li>
    <li>Malicious uploads or attempts to compromise the platform</li>
    <li>Scraping or unauthorized data collection</li>
</ul>

<h2><i class="fas fa-file-alt"></i> 10. Resume and Document Rules</h2>
<p>Uploaded resumes must belong to the applicant. Uploading malicious files, third-party resumes without consent, or files containing illegal content is prohibited.</p>

<h2><i class="fas fa-paper-plane"></i> 11. Job Applications</h2>
<p>Applications are informational only. Local Jobs does not guarantee that any employer will review or respond to any application, or offer any interview or employment.</p>

<h2><i class="fas fa-user-check"></i> 12. Employer Verification</h2>
<p>Local Jobs may conduct basic employer verification and moderation. However, verification does <strong>not</strong> guarantee that every job, employer, salary claim, or recruitment outcome is genuine. Users should independently verify job offers before sharing sensitive information or accepting employment.</p>

<h2><i class="fas fa-credit-card"></i> 13. Paid Services and Subscriptions</h2>
<p>Local Jobs may introduce paid features for employers in the future. Any such services will be governed by our <a href="refund-policy.php">Refund Policy</a>.</p>

<h2><i class="fas fa-money-bill-wave"></i> 14. Payments and Refunds</h2>
<p>Where paid features are active, payments and refunds follow the Refund Policy. Where paid features are not active, no charges apply.</p>

<h2><i class="fas fa-exclamation-triangle"></i> 15. Job Accuracy Disclaimer</h2>
<p>Local Jobs does not create, verify in depth, or endorse the content of job postings. We are not responsible for employment decisions, disputes, or outcomes between users and employers.</p>

<h2><i class="fas fa-comments"></i> 16. Third-Party Communication</h2>
<p>Contact details shared through applications belong to the user. Any communication initiated outside the platform is at the users' own risk.</p>

<h2><i class="fas fa-pause-circle"></i> 17. Account Suspension</h2>
<p>We may suspend accounts that violate these Terms, applicable law, or harm other users. Suspension may be temporary or permanent.</p>

<h2><i class="fas fa-user-slash"></i> 18. Account Termination</h2>
<p>We reserve the right to terminate accounts and remove content at our discretion where we reasonably believe a policy violation has occurred.</p>

<h2><i class="fas fa-shield-alt"></i> 19. Limitation of Liability</h2>
<p>To the maximum extent permitted by law, Local Jobs is not liable for indirect, incidental, or consequential damages arising from your use of the platform.</p>

<h2><i class="fas fa-balance-scale"></i> 20. Indemnification</h2>
<p>You agree to indemnify and hold harmless Local Jobs and its team from any claims, damages, or expenses arising from your use of the platform in violation of these Terms.</p>

<h2><i class="fas fa-sync-alt"></i> 21. Changes to Terms</h2>
<p>We may update these Terms. Continued use of the platform after changes means you accept the revised Terms.</p>

<h2><i class="fas fa-gavel"></i> 22. Governing Law</h2>
<p>These Terms are governed by the laws of India. Courts at Nichlaul, Uttar Pradesh, shall have exclusive jurisdiction over disputes.</p>

<h2><i class="fas fa-envelope"></i> 23. Contact Information</h2>
<ul>
    <li><strong>Support Email:</strong> <a href="mailto:<?= h(SUPPORT_EMAIL) ?>"><?= h(SUPPORT_EMAIL) ?></a></li>
    <li><strong>Privacy Email:</strong> <a href="mailto:<?= h(PRIVACY_EMAIL) ?>"><?= h(PRIVACY_EMAIL) ?></a></li>
    <li><strong>Address:</strong> <?= h(SITE_ADDRESS) ?></li>
</ul>

<?php legal_page_end(); ?>
<?php include 'inc/foot.php'; ?>