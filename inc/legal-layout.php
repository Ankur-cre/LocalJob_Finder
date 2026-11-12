<?php
/**
 * Helper to wrap any legal/info page with a consistent layout.
 *
 * Usage in each page:
 *   $page_title = "...";
 *   $page_intro = "...";
 *   $page_icon  = "fa-shield-alt";
 *   legal_page_start();
 *   ... your HTML ...
 *   legal_page_end();
 */

if (!function_exists('legal_page_start')) {
    function legal_page_start() {
        global $page_title, $page_intro, $page_icon;
        ?>
        <div class="legal-hero">
            <div class="legal-hero-icon">
                <i class="fas <?= $page_icon ?? 'fa-info-circle' ?>"></i>
            </div>
            <h1><?= h($page_title ?? 'Information') ?></h1>
            <?php if (!empty($page_intro)): ?>
                <p><?= h($page_intro) ?></p>
            <?php endif; ?>
            <div class="legal-meta">
                <i class="fas fa-calendar"></i>
                Last updated: <strong><?= date('d M Y', strtotime(LEGAL_LAST_UPDATED)) ?></strong>
            </div>
        </div>
        <div class="legal-content">
        <?php
    }
}

if (!function_exists('legal_page_end')) {
    function legal_page_end() {
        ?>
        </div><!-- /.legal-content -->

        <!-- Contact Support CTA -->
        <div class="legal-cta">
            <h3><i class="fas fa-headset"></i> Still have questions?</h3>
            <p>Our team is here to help you.</p>
            <div class="legal-cta-actions">
                <a href="contact.php" class="btn-blue">
                    <i class="fas fa-envelope"></i> Contact Us
                </a>
                <a href="faq.php" class="btn-outline-blue">
                    <i class="fas fa-question-circle"></i> Help & FAQ
                </a>
            </div>
        </div>
        <?php
    }
}