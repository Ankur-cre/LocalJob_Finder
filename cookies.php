<?php
require_once 'inc/auth.php';
require_once 'inc/site-config.php';
$title = "Cookie Policy";

include 'inc/head.php';

$page_title = "Cookie Policy";
$page_intro = "How Local Jobs uses cookies and similar technologies.";
$page_icon  = "fa-cookie-bite";
legal_page_start();
?>

<h2><i class="fas fa-question-circle"></i> 1. What Are Cookies?</h2>
<p>Cookies are small text files stored on your device by your browser. They help websites remember your preferences and keep you logged in.</p>

<h2><i class="fas fa-key"></i> 2. Essential Session Cookies</h2>
<p>Local Jobs uses a session cookie to keep you logged in as you move between pages. Without this cookie, you would need to log in on every page. This cookie contains a session ID only, not your password.</p>

<h2><i class="fas fa-sign-in-alt"></i> 3. Login-Related Cookies</h2>
<p>When you log in, we store a session identifier that confirms your identity for that session. It expires when you log out or when the session times out.</p>

<h2><i class="fas fa-sliders-h"></i> 4. Preference Cookies</h2>
<p>We do not currently set preference cookies. If we add features such as "remember me" or saved language settings in the future, this policy will be updated.</p>

<h2><i class="fas fa-chart-bar"></i> 5. Analytics Cookies</h2>
<p>We do not currently use third-party analytics tools such as Google Analytics.</p>

<h2><i class="fas fa-globe"></i> 6. Third-Party Cookies</h2>
<p>Local Jobs may load assets from Font Awesome CDN (icons) and Unsplash (demo images). These services may set their own cookies or access basic device information when loading content.</p>

<h2><i class="fas fa-cog"></i> 7. Managing Cookies</h2>
<p>You can control cookies from your browser settings. Disabling essential cookies will prevent you from logging in and using certain features.</p>
<ul>
    <li>Chrome: Settings → Privacy and security → Cookies</li>
    <li>Firefox: Settings → Privacy & Security → Cookies</li>
    <li>Safari: Preferences → Privacy → Cookies</li>
</ul>

<h2><i class="fas fa-envelope"></i> 8. Contact</h2>
<p>For questions about this Cookie Policy, email <a href="mailto:<?= h(PRIVACY_EMAIL) ?>"><?= h(PRIVACY_EMAIL) ?></a>.</p>

<?php legal_page_end(); ?>
<?php include 'inc/foot.php'; ?>