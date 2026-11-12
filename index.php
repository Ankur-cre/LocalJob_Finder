<?php
require_once 'inc/auth.php';
$title = "Find Jobs in Nichlaul";

// Stats (landing page pe public dikhane ke liye)
$total_jobs = $db->query("SELECT COUNT(*) c FROM jobs WHERE status='active'")->fetch_assoc()['c'];
$total_emp  = $db->query("SELECT COUNT(*) c FROM users WHERE role='employer'")->fetch_assoc()['c'];
$total_seek = $db->query("SELECT COUNT(*) c FROM users WHERE role='seeker'")->fetch_assoc()['c'];

include 'inc/head.php';
?>

<!-- ============================================================
     HERO SECTION — With Background Image
     ============================================================ -->
<section class="land-hero land-hero-img">
    <div class="land-hero-overlay"></div>
    <div class="land-hero-inner">
        <span class="land-badge">
            <i class="fas fa-map-marker-alt"></i> Nichlaul's #1 Local Job Portal
        </span>

        <p class="land-hero-tagline">
            Real jobs. Real people. Right in your neighborhood.
        </p>

        <div class="land-hero-actions">
            <?php if (logged_in()): ?>
                <a href="jobs.php" class="btn-blue btn-lg">
                    <i class="fas fa-search"></i> Browse Jobs
                </a>
                <?php if (role() === 'employer'): ?>
                    <a href="post.php" class="btn-outline-blue btn-lg">
                        <i class="fas fa-plus"></i> Post a Job
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <a href="join.php" class="btn-blue btn-lg">
                    <i class="fas fa-rocket"></i> Get Started — It's Free
                </a>
                <a href="login.php" class="btn-outline-blue btn-lg">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            <?php endif; ?>
        </div>

        <div class="land-stats">
            <div><strong><?= $total_jobs ?>+</strong><span>Active Jobs</span></div>
            <div><strong><?= $total_emp ?>+</strong><span>Employers</span></div>
            <div><strong><?= $total_seek ?>+</strong><span>Job Seekers</span></div>
        </div>
    </div>
</section>

<!-- ============================================================
     SCROLLABLE IMAGE SECTION — India local jobs
     ============================================================ -->
<section class="land-scroll">
    <div class="land-scroll-wrap">

        <!-- LEFT: Auto-scrolling image column -->
        <div class="land-scroll-images" id="scrollImages">

            <div class="scroll-img-card">
                <img src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=800&q=80" alt="Indian shop owner" loading="lazy">
                <div class="img-overlay">
                    <i class="fas fa-store"></i>
                    <span>Shop & Retail Jobs</span>
                </div>
            </div>

            <div class="scroll-img-card">
                <img src="https://images.unsplash.com/photo-1524178232363-1fb2b075b655?w=800&q=80" alt="Teacher in classroom" loading="lazy">
                <div class="img-overlay">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Teaching & Coaching</span>
                </div>
            </div>

            <div class="scroll-img-card">
                <img src="https://images.unsplash.com/photo-1566576721346-d4a3b4eaeb55?w=800&q=80" alt="Delivery boy India" loading="lazy">
                <div class="img-overlay">
                    <i class="fas fa-motorcycle"></i>
                    <span>Delivery & Driver Jobs</span>
                </div>
            </div>

            <div class="scroll-img-card">
                <img src="https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=800&q=80" alt="Office work" loading="lazy">
                <div class="img-overlay">
                    <i class="fas fa-briefcase"></i>
                    <span>Office & Admin Roles</span>
                </div>
            </div>

            <div class="scroll-img-card">
                <img src="https://images.unsplash.com/photo-1556910103-1c02745aae4d?w=800&q=80" alt="Chef cooking" loading="lazy">
                <div class="img-overlay">
                    <i class="fas fa-utensils"></i>
                    <span>Cooking & Kitchen</span>
                </div>
            </div>

            <div class="scroll-img-card">
                <img src="https://images.unsplash.com/photo-1618044733300-9472054094ee?w=800&q=80" alt="Security guard" loading="lazy">
                <div class="img-overlay">
                    <i class="fas fa-shield-alt"></i>
                    <span>Security Guard Jobs</span>
                </div>
            </div>

            <div class="scroll-img-card">
                <img src="https://images.unsplash.com/photo-1521737604893-d14cc237f11d?w=800&q=80" alt="Local team" loading="lazy">
                <div class="img-overlay">
                    <i class="fas fa-users"></i>
                    <span>Team & Helper Jobs</span>
                </div>
            </div>

            <div class="scroll-img-card">
                <img src="https://images.unsplash.com/photo-1581092918056-0c4c3acd3789?w=800&q=80" alt="Technician repair" loading="lazy">
                <div class="img-overlay">
                    <i class="fas fa-tools"></i>
                    <span>Technician & Repair</span>
                </div>
            </div>

        </div>

        <!-- RIGHT: Info + Get Started -->
        <div class="land-scroll-info">
            <h2>Your Next Job Is <span class="grad-text">Closer Than You Think</span></h2>
            <p class="land-lead">
                Local Jobs connects job seekers with trusted employers in Nichlaul
                and nearby areas. No middlemen. No hidden fees. Just real work.
            </p>

            <ul class="feature-list">
                <li>
                    <span class="fi-icon"><i class="fas fa-bolt"></i></span>
                    <div>
                        <strong>Apply in seconds</strong>
                        <p>One-click applications with your saved resume.</p>
                    </div>
                </li>
                <li>
                    <span class="fi-icon"><i class="fas fa-shield-alt"></i></span>
                    <div>
                        <strong>Verified employers</strong>
                        <p>Every job is admin-checked before going live.</p>
                    </div>
                </li>
                <li>
                    <span class="fi-icon"><i class="fas fa-map-marked-alt"></i></span>
                    <div>
                        <strong>Local-first</strong>
                        <p>Jobs within walking distance — no long commutes.</p>
                    </div>
                </li>
                <li>
                    <span class="fi-icon"><i class="fas fa-briefcase"></i></span>
                    <div>
                        <strong>For employers too</strong>
                        <p>Post a job in 2 minutes. Hire from your neighborhood.</p>
                    </div>
                </li>
            </ul>

            <div class="land-cta-box">
                <h3>Ready to get started?</h3>
                <p>Sign up or login to browse jobs & apply.</p>
                <?php if (logged_in()): ?>
                    <a href="jobs.php" class="btn-blue btn-lg btn-block">
                        <i class="fas fa-search"></i> Browse Jobs Now
                    </a>
                <?php else: ?>
                    <a href="join.php" class="btn-blue btn-lg btn-block">
                        <i class="fas fa-rocket"></i> Get Started — Sign Up / Login
                    </a>
                <?php endif; ?>
                <small><i class="fas fa-lock"></i> Job listings are visible only to logged-in members.</small>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     HOW IT WORKS
     ============================================================ -->
<section class="land-how" id="how">
    <h2>How <span class="grad-text">Local Jobs</span> Works</h2>
    <p class="land-section-lead">Simple, transparent, and built for small towns.</p>

    <div class="how-grid">
        <div class="how-card">
            <div class="how-num">1</div>
            <div class="how-icon"><i class="fas fa-user-plus"></i></div>
            <h3>Create your account</h3>
            <p>Choose Job Seeker or Employer. It's free and takes under a minute.</p>
        </div>
        <div class="how-card">
            <div class="how-num">2</div>
            <div class="how-icon"><i class="fas fa-search"></i></div>
            <h3>Browse or post jobs</h3>
            <p>Seekers explore verified local jobs. Employers post openings.</p>
        </div>
        <div class="how-card">
            <div class="how-num">3</div>
            <div class="how-icon"><i class="fas fa-handshake"></i></div>
            <h3>Connect & get hired</h3>
            <p>Apply with your resume. Employers review and contact you directly.</p>
        </div>
    </div>
</section>

<!-- ============================================================
     CATEGORIES STRIP
     ============================================================ -->
<section class="land-cats" id="categories">
    <h2>Explore <span class="grad-text">Job Categories</span></h2>
    <p class="land-section-lead">Login to see live jobs in each category.</p>

    <div class="cat-chips">
        <?php foreach (
            ['Sales','Driver','Teacher','Helper','Technician','Office','Security','Cooking','Others']
            as $c
        ): ?>
            <a href="<?= logged_in() ? 'jobs.php?cat=' . urlencode($c) : 'login.php?err=' . urlencode('Please login to view jobs.') ?>"
               class="cat-chip">
                <i class="fas fa-tag"></i> <?= h($c) ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============================================================
     FINAL CTA BANNER
     ============================================================ -->
<section class="land-final-cta">
    <div class="land-final-inner">
        <h2>Your future starts with one click.</h2>
        <p>Join hundreds of locals already finding work on Local Jobs.</p>
        <?php if (!logged_in()): ?>
            <a href="join.php" class="btn-amber btn-lg">
                <i class="fas fa-arrow-right"></i> Get Started Free
            </a>
        <?php else: ?>
            <a href="jobs.php" class="btn-amber btn-lg">
                <i class="fas fa-search"></i> Browse Jobs
            </a>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    const box = document.getElementById('scrollImages');
    if (!box) return;

    let paused = false;
    let dir = 1; // 1 = down, -1 = up

    // Pause on hover / touch
    box.addEventListener('mouseenter', () => paused = true);
    box.addEventListener('mouseleave', () => paused = false);
    box.addEventListener('touchstart', () => paused = true, { passive: true });
    box.addEventListener('touchend',   () => setTimeout(() => paused = false, 1500), { passive: true });

    // Pause when user manually scrolls
    let userScrollTimer;
    box.addEventListener('wheel', () => {
        paused = true;
        clearTimeout(userScrollTimer);
        userScrollTimer = setTimeout(() => paused = false, 2500);
    }, { passive: true });

    // Auto-scroll loop
    function tick() {
        if (!paused) {
            box.scrollTop += dir * 0.6;

            // Bounce back at ends
            if (box.scrollTop + box.clientHeight >= box.scrollHeight - 1) dir = -1;
            if (box.scrollTop <= 0) dir = 1;
        }
        requestAnimationFrame(tick);
    }
    tick();
})();
</script>

<?php include 'inc/foot.php'; ?>