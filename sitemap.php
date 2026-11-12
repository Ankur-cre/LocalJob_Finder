<?php
require_once 'inc/auth.php';
require_once 'inc/site-config.php';
$title = "Sitemap";

// Pull actual categories from DB
$cats = [];
$catRes = $db->query("SELECT DISTINCT category FROM jobs WHERE status='active' AND category<>'' ORDER BY category");
if ($catRes) { while ($r = $catRes->fetch_assoc()) $cats[] = $r['category']; }
if (empty($cats)) $cats = ['Sales','Driver','Teacher','Helper','Technician','Office','Security','Cooking','Others'];

$groups = [
    'Public Pages' => [
        ['Home', 'index.php', 'fa-home', 'Landing page with search & info'],
        ['Browse Jobs', 'jobs.php', 'fa-briefcase', 'All active job listings'],
        ['How It Works', 'index.php#how', 'fa-question-circle', 'Three-step overview'],
        ['Job Categories', 'index.php#categories', 'fa-tags', 'Explore by category'],
        ['About Us', 'about.php', 'fa-info-circle', 'Who we are'],
        ['Contact Us', 'contact.php', 'fa-envelope', 'Reach our team'],
        ['Help & FAQ', 'faq.php', 'fa-question-circle', 'Common questions'],
        ['Safety Guidelines', 'safety.php', 'fa-shield-alt', 'Stay safe while hiring/applying'],
        ['Privacy Policy', 'privacy.php', 'fa-user-shield', 'How we handle your data'],
        ['Terms of Use', 'terms.php', 'fa-file-contract', 'Rules for using the platform'],
        ['Cookie Policy', 'cookies.php', 'fa-cookie-bite', 'Cookies we use'],
        ['Refund Policy', 'refund-policy.php', 'fa-undo-alt', 'Paid services only'],
        ['Sitemap', 'sitemap.php', 'fa-sitemap', 'This page'],
    ],
];

if (logged_in() && role() === 'seeker') {
    $groups['Job Seeker Pages'] = [
        ['Seeker Home', 'seeker-home.php', 'fa-home', 'Your personalized dashboard'],
        ['Browse Jobs', 'jobs.php', 'fa-briefcase', 'All active jobs'],
        ['My Applications', 'seeker.php', 'fa-file-alt', 'Applications you submitted'],
    ];
}
if (logged_in() && role() === 'employer') {
    $groups['Employer Pages'] = [
        ['Employer Dashboard', 'employer.php', 'fa-chart-line', 'Manage jobs & applicants'],
        ['Post a Job', 'post.php', 'fa-plus-circle', 'Publish a new listing'],
    ];
}
if (logged_in() && role() === 'admin') {
    $groups['Admin Pages'] = [
        ['Admin Dashboard', 'admin.php', 'fa-shield-alt', 'Moderation & overview'],
    ];
}

include 'inc/head.php';
?>

<div class="legal-hero">
    <div class="legal-hero-icon"><i class="fas fa-sitemap"></i></div>
    <h1>Site Map</h1>
    <p>All the important pages of Local Jobs, in one place.</p>
</div>

<?php foreach ($groups as $groupName => $links): ?>
    <div class="sitemap-group">
        <h2 class="sitemap-group-title">
            <i class="fas fa-folder-open"></i> <?= h($groupName) ?>
        </h2>
        <div class="sitemap-grid">
            <?php foreach ($links as [$label, $url, $icon, $desc]): ?>
                <a href="<?= h($url) ?>" class="sitemap-tile">
                    <span class="sitemap-tile-icon"><i class="fas <?= h($icon) ?>"></i></span>
                    <span class="sitemap-tile-text">
                        <strong><?= h($label) ?></strong>
                        <small><?= h($desc) ?></small>
                    </span>
                    <i class="fas fa-arrow-right sitemap-arrow"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<div class="sitemap-group">
    <h2 class="sitemap-group-title">
        <i class="fas fa-tags"></i> Job Categories
    </h2>
    <div class="cat-chips">
        <?php foreach ($cats as $c): ?>
            <a href="jobs.php?cat=<?= urlencode($c) ?>" class="cat-chip">
                <i class="fas fa-tag"></i> <?= h($c) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'inc/foot.php'; ?>