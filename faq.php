<?php
require_once 'inc/auth.php';
require_once 'inc/site-config.php';
$title = "Help & FAQ";

// FAQ data — grouped by category
$faq_categories = [
    'account' => [
        'label' => 'Account',
        'icon'  => 'fa-user-circle',
        'items' => [
            ['q' => 'How do I create an account on Local Jobs?',
             'a' => 'Click "Get Started" on the home page. Choose "I\'m a Job Seeker" or "I\'m an Employer" and fill the registration form. Job seeker accounts are usually active immediately; employer accounts require admin approval before posting jobs.'],
            ['q' => 'What should I do if I forget my password?',
             'a' => 'Password reset is not automated right now. Please email ' . SUPPORT_EMAIL . ' with your registered email address, and the support team will help you regain access.'],
            ['q' => 'How can I change my mobile number or email?',
             'a' => 'Currently, contact details are updated via support. Email ' . SUPPORT_EMAIL . ' from your registered email with the new number/email so we can verify and update it.'],
            ['q' => 'How can I delete my account?',
             'a' => 'Send a request to ' . SUPPORT_EMAIL . ' or ' . PRIVACY_EMAIL . ' from your registered email. Account and associated data (profile, applications, resume) will be removed after verification, subject to any legal retention requirements.'],
        ],
    ],
    'applications' => [
        'label' => 'Job Applications',
        'icon'  => 'fa-file-alt',
        'items' => [
            ['q' => 'How do I apply for a job?',
             'a' => 'Login as a job seeker, browse jobs, open a job, review details and click "Submit Application" at the bottom of the job detail page. You may optionally attach a resume.'],
            ['q' => 'What happens after submitting an application?',
             'a' => 'The employer sees your application in their dashboard. They may shortlist, hire or reject it. Note: employers are not obliged to respond, and Local Jobs cannot guarantee a response.'],
            ['q' => 'How can I check my application status?',
             'a' => 'Go to "My Applications" from the top navigation (or seeker home). You can see the status of each application: Pending, Shortlisted, Hired, or Rejected.'],
            ['q' => 'Can I apply for the same job multiple times?',
             'a' => 'No. Once you have applied to a job, the system prevents duplicate applications for the same job.'],
        ],
    ],
    'resume' => [
        'label' => 'Resume',
        'icon'  => 'fa-file-pdf',
        'items' => [
            ['q' => 'How do I upload my resume?',
             'a' => 'When applying to a job, you can optionally attach a resume file. The system accepts PDF, DOC, DOCX, JPG and PNG files up to 5 MB. Images and (when supported) PDFs are auto-compressed to save space.'],
            ['q' => 'Which resume file formats are supported?',
             'a' => 'PDF, DOC, DOCX, JPG, JPEG and PNG. Maximum upload size is 5 MB.'],
            ['q' => 'How can I update or delete my resume?',
             'a' => 'Resumes are attached per-application. To update or delete, email ' . SUPPORT_EMAIL . ' with the application/job details, and the support team will assist.'],
        ],
    ],
    'employer' => [
        'label' => 'Employer',
        'icon'  => 'fa-building',
        'items' => [
            ['q' => 'How do I create an employer account?',
             'a' => 'Click "Get Started" → "I\'m an Employer". Fill in your details, and submit. Employer accounts require admin approval before you can post jobs.'],
            ['q' => 'How long does employer verification take?',
             'a' => 'Verification is done manually by our team. Most accounts are reviewed within 1–2 business days. We do not guarantee a specific timeframe.'],
            ['q' => 'How can I post a new job?',
             'a' => 'Once your employer account is approved, login and use "Post Job" from the navigation. New jobs may require admin approval before becoming publicly visible.'],
            ['q' => 'How do I view applicants?',
             'a' => 'From your Employer Dashboard, open any job you posted to see all applicants, their resume (if provided), and update application status (Shortlisted, Hired, Rejected).'],
        ],
    ],
    'payments' => [
        'label' => 'Payments',
        'icon'  => 'fa-credit-card',
        'items' => [
            ['q' => 'Is registration free for job seekers?',
             'a' => 'Yes. Job seeker registration, job browsing and applying are completely free on Local Jobs.'],
            ['q' => 'How do employer subscriptions work?',
             'a' => 'Paid employer features are not yet active on this platform. Any paid services introduced later will follow the Refund Policy.'],
            ['q' => 'What should I do if my payment fails?',
             'a' => 'If a paid service is introduced and a payment fails, contact ' . SUPPORT_EMAIL . ' with your transaction reference. Our team will verify and assist with the appropriate refund.'],
        ],
    ],
    'safety' => [
        'label' => 'General & Safety',
        'icon'  => 'fa-shield-alt',
        'items' => [
            ['q' => 'How can I report a fake job?',
             'a' => 'Open the job page and use the "Report Job" option, or go to Safety Guidelines. You can also email ' . SUPPORT_EMAIL . ' with the job title and details.'],
            ['q' => 'How can I contact Local Jobs support?',
             'a' => 'Email ' . SUPPORT_EMAIL . ', call ' . SUPPORT_PHONE . ', or WhatsApp ' . WHATSAPP_NUMBER . '. Support hours: ' . SUPPORT_HOURS . '.'],
        ],
    ],
];

include 'inc/head.php';
?>

<!-- ============ FAQ HERO ============ -->
<div class="legal-hero">
    <div class="legal-hero-icon">
        <i class="fas fa-question-circle"></i>
    </div>
    <h1>Help & Frequently Asked Questions</h1>
    <p>Find quick answers about accounts, applications, resume, employers and more.</p>
</div>

<!-- ============ SEARCH + FILTER ============ -->
<div class="faq-controls">
    <div class="faq-search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="faqSearch" placeholder="Search questions or answers..."
               autocomplete="off">
        <button type="button" id="faqClear" class="faq-clear" title="Clear">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="faq-filters">
        <button class="faq-filter-btn active" data-cat="all">
            <i class="fas fa-list"></i> All
        </button>
        <?php foreach ($faq_categories as $key => $cat): ?>
            <button class="faq-filter-btn" data-cat="<?= h($key) ?>">
                <i class="fas <?= h($cat['icon']) ?>"></i> <?= h($cat['label']) ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============ FAQ LIST ============ -->
<div class="faq-list" id="faqList">
    <?php foreach ($faq_categories as $catKey => $cat): ?>
        <div class="faq-group" data-cat="<?= h($catKey) ?>">
            <h2 class="faq-group-title">
                <i class="fas <?= h($cat['icon']) ?>"></i> <?= h($cat['label']) ?>
            </h2>
            <?php foreach ($cat['items'] as $i => $item): ?>
                <div class="faq-item"
                     data-q="<?= h(strtolower($item['q'])) ?>"
                     data-a="<?= h(strtolower(strip_tags($item['a']))) ?>">
                    <button class="faq-question" type="button">
                        <span><?= h($item['q']) ?></span>
                        <i class="fas fa-chevron-down faq-chevron"></i>
                    </button>
                    <div class="faq-answer">
                        <p><?= h($item['a']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <!-- Empty state -->
    <div class="faq-empty" id="faqEmpty" style="display:none;">
        <i class="fas fa-search"></i>
        <h3>No results found</h3>
        <p>Try a different keyword or <button class="link-btn" id="faqClear2">clear the search</button>.</p>
    </div>
</div>

<!-- ============ CONTACT SUPPORT CTA ============ -->
<div class="legal-cta">
    <h3><i class="fas fa-headset"></i> Didn't find your answer?</h3>
    <p>Our team is happy to help. Reach out via any channel below.</p>
    <div class="legal-cta-actions">
        <a href="contact.php" class="btn-blue">
            <i class="fas fa-envelope"></i> Contact Support
        </a>
        <a href="<?= whatsapp_url() ?>" target="_blank" rel="noopener" class="btn-green">
            <i class="fab fa-whatsapp"></i> WhatsApp Us
        </a>
        <a href="tel:<?= h(preg_replace('/\s+/', '', SUPPORT_PHONE)) ?>" class="btn-outline-blue">
            <i class="fas fa-phone"></i> Call Support
        </a>
    </div>
</div>

<script src="assets/js/faq.js"></script>
<?php include 'inc/foot.php'; ?>