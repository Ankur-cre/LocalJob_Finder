<?php if (logged_in() && role() === 'admin'): ?>
    <!-- ADMIN DARK FOOTER (Clean & Minimal) -->
    </div><!-- /.content-area -->
    </div><!-- /.main-content -->

    <style>
        /* Admin specific footer alignment */
        body { display: flex; flex-direction: column; min-height: 100vh; }
        .main-content { flex: 1; display: flex; flex-direction: column; }
        .content-area { flex: 1; }
    </style>
    
    <script>
        // Notification Dropdown Toggle Script for Admin
        function toggleNotif(e) {
            if(e) { e.preventDefault(); e.stopPropagation(); }
            var dropdown = document.getElementById('notifDropdown');
            if(dropdown) dropdown.classList.toggle('show');
        }
        function closeNotif(e) {
            if(e) { e.preventDefault(); e.stopPropagation(); }
            var dropdown = document.getElementById('notifDropdown');
            if(dropdown) dropdown.classList.remove('show');
        }
        document.addEventListener('click', function(e) {
            var notif = document.getElementById('notifDropdown');
            if (notif && !e.target.closest('.notif-wrap')) notif.classList.remove('show');
        });
    </script>
    </body>
    </html>

<?php else: ?>
    <!-- NORMAL USER FOOTER (Rich) -->
    </div><!-- /.wrap -->
    </main>

    <footer class="footer-rich">
        <div class="wrap footer-grid">

            <div class="footer-col footer-col-brand">
                <a href="<?= logged_in() && role()==='seeker' ? 'seeker-home.php' : 'index.php' ?>" class="footer-brand">
                    <i class="fas fa-briefcase"></i> <?= SITE_NAME ?>
                </a>
                <p class="footer-tagline"><?= h(SITE_TAGLINE) ?></p>
                <p class="footer-about"><?= t('footer.about') ?></p>
                <p class="footer-locations">
                    <i class="fas fa-map-marker-alt"></i>
                    Nichlaul · Maharajganj · Gorakhpur
                </p>
                <p class="footer-locations">
                    <i class="fas fa-envelope"></i>
                    <a href="mailto:<?= h(SUPPORT_EMAIL) ?>"><?= h(SUPPORT_EMAIL) ?></a>
                </p>
                <p class="footer-locations">
                    <i class="fas fa-phone"></i>
                    <a href="tel:<?= h(preg_replace('/\s+/', '', SUPPORT_PHONE)) ?>"><?= h(SUPPORT_PHONE) ?></a>
                </p>

                <div class="footer-social">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    <a href="<?= whatsapp_url() ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                </div>
            </div>

            <div class="footer-col">
                <h4><?= t('footer.for_seekers') ?></h4>
                <ul class="footer-links">
                    <li><a href="jobs.php"><i class="fas fa-angle-right"></i> <?= t('footer.browse_jobs') ?></a></li>
                    <li><a href="index.php#categories"><i class="fas fa-angle-right"></i> <?= t('footer.job_categories') ?></a></li>
                    <li><a href="index.php#how"><i class="fas fa-angle-right"></i> <?= t('footer.how_it_works') ?></a></li>
                    <?php if (logged_in() && role()==='seeker'): ?>
                        <li><a href="seeker-home.php"><i class="fas fa-angle-right"></i> <?= t('footer.my_home') ?></a></li>
                        <li><a href="seeker.php"><i class="fas fa-angle-right"></i> <?= t('nav.my_apps') ?></a></li>
                    <?php else: ?>
                        <li><a href="reg-seeker.php"><i class="fas fa-angle-right"></i> <?= t('footer.create_account') ?></a></li>
                        <li><a href="login.php"><i class="fas fa-angle-right"></i> <?= t('nav.login') ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="footer-col">
                <h4><?= t('footer.for_employers') ?></h4>
                <ul class="footer-links">
                    <?php if (logged_in() && role()==='employer'): ?>
                        <li><a href="post.php"><i class="fas fa-angle-right"></i> <?= t('nav.post_job') ?></a></li>
                        <li><a href="employer.php"><i class="fas fa-angle-right"></i> <?= t('nav.dashboard') ?></a></li>
                    <?php else: ?>
                        <li><a href="reg-employer.php"><i class="fas fa-angle-right"></i> <?= t('footer.employer_reg') ?></a></li>
                        <li><a href="login.php"><i class="fas fa-angle-right"></i> <?= t('footer.employer_login') ?></a></li>
                    <?php endif; ?>
                    <li><a href="safety.php"><i class="fas fa-angle-right"></i> <?= t('footer.employer_guide') ?></a></li>
                    <li><a href="about.php"><i class="fas fa-angle-right"></i> <?= t('footer.about_us') ?></a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4><?= t('footer.help_support') ?></h4>
                <ul class="footer-links">
                    <li><a href="faq.php"><i class="fas fa-angle-right"></i> <?= t('footer.faq') ?></a></li>
                    <li><a href="contact.php"><i class="fas fa-angle-right"></i> <?= t('footer.contact') ?></a></li>
                    <li><a href="safety.php"><i class="fas fa-angle-right"></i> <?= t('footer.safety') ?></a></li>
                    <li><a href="safety.php#report"><i class="fas fa-angle-right"></i> <?= t('footer.report') ?></a></li>
                    <li><a href="about.php"><i class="fas fa-angle-right"></i> <?= t('footer.about_us') ?></a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4><?= t('footer.legal') ?></h4>
                <ul class="footer-links">
                    <li><a href="privacy.php"><i class="fas fa-angle-right"></i> <?= t('footer.privacy') ?></a></li>
                    <li><a href="terms.php"><i class="fas fa-angle-right"></i> <?= t('footer.terms') ?></a></li>
                    <li><a href="cookies.php"><i class="fas fa-angle-right"></i> <?= t('footer.cookie') ?></a></li>
                    <li><a href="refund-policy.php"><i class="fas fa-angle-right"></i> <?= t('footer.refund') ?></a></li>
                    <li><a href="sitemap.php"><i class="fas fa-angle-right"></i> <?= t('footer.sitemap') ?></a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="wrap footer-bottom-inner">
                <p>
                    &copy; <?= date('Y') ?> <strong><?= SITE_NAME ?></strong>. <?= t('footer.rights') ?>.
                    <span class="footer-team"><?= SITE_NAME ?> Team</span>
                    <span class="footer-sep">·</span>
                    <a href="mailto:<?= h(SUPPORT_EMAIL) ?>"><?= h(SUPPORT_EMAIL) ?></a>
                </p>
                <p class="footer-bottom-links">
                    <a href="privacy.php"><?= t('footer.privacy') ?></a>
                    <span>·</span>
                    <a href="terms.php"><?= t('footer.terms') ?></a>
                    <span>·</span>
                    <a href="sitemap.php"><?= t('footer.sitemap') ?></a>
                </p>
            </div>
        </div>
    </footer>

    <button id="backToTop" class="back-to-top" aria-label="Back to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
    (function () {
        const btn = document.getElementById('backToTop');
        if (!btn) return;
        window.addEventListener('scroll', () => {
            btn.classList.toggle('show', window.scrollY > 300);
        });
        btn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    })();
    </script>

    </body>
    </html>
<?php endif; ?>