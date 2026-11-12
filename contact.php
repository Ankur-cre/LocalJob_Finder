<?php
require_once 'inc/auth.php';
require_once 'inc/site-config.php';
$title = "Contact Us";

$success = '';
$errors  = [];

// CSRF token
if (empty($_SESSION['csrf_contact'])) {
    $_SESSION['csrf_contact'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!hash_equals($_SESSION['csrf_contact'], $_POST['csrf'] ?? '')) {
        $errors[] = "Security check failed. Please try again.";
    }

    // Rate limit — 3 messages per session per 10 min
    $now = time();
    $recent = $_SESSION['contact_recent'] ?? [];
    $recent = array_filter($recent, fn($t) => $t > $now - 600);
    if (count($recent) >= 3) {
        $errors[] = "Too many submissions. Please wait a few minutes.";
    }

    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '')                                $errors[] = "Please enter your name.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = "Please enter a valid email.";
    if ($subject === '')                             $errors[] = "Please enter a subject.";
    if (strlen($message) < 10)                       $errors[] = "Message must be at least 10 characters.";
    if (strlen($message) > 2000)                     $errors[] = "Message must be under 2000 characters.";
    if ($phone && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) $errors[] = "Invalid phone number.";

    if (empty($errors)) {
        $ticket_id = 'LJ-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $db->prepare("
            INSERT INTO support_messages (ticket_id, name, email, phone, subject, message, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssssss', $ticket_id, $name, $email, $phone, $subject, $message, $ip);
        if ($stmt->execute()) {
            $success = "Your message has been received. Your ticket ID is <strong>" . h($ticket_id) . "</strong>. We'll respond within 1–2 business days.";
            $_SESSION['csrf_contact'] = bin2hex(random_bytes(32));
            $recent[] = $now;
            $_SESSION['contact_recent'] = $recent;
            $_POST = [];
        } else {
            $errors[] = "We couldn't save your message. Please try again later.";
        }
    }
}

include 'inc/head.php';
?>

<div class="legal-hero">
    <div class="legal-hero-icon"><i class="fas fa-envelope-open-text"></i></div>
    <h1>Contact Us</h1>
    <p>Questions, feedback, or need help? We're here for you.</p>
</div>

<div class="contact-wrap">
    <!-- Info column -->
    <div class="contact-info">
        <h2><i class="fas fa-address-book"></i> Get in Touch</h2>

        <ul class="contact-info-list">
            <li>
                <i class="fas fa-phone"></i>
                <div>
                    <span class="ci-label">Phone</span>
                    <a href="tel:<?= h(preg_replace('/\s+/', '', SUPPORT_PHONE)) ?>"><?= h(SUPPORT_PHONE) ?></a>
                </div>
            </li>
            <li>
                <i class="fab fa-whatsapp"></i>
                <div>
                    <span class="ci-label">WhatsApp</span>
                    <a href="<?= whatsapp_url() ?>" target="_blank" rel="noopener"><?= h(WHATSAPP_NUMBER) ?></a>
                </div>
            </li>
            <li>
                <i class="fas fa-envelope"></i>
                <div>
                    <span class="ci-label">Support Email</span>
                    <a href="mailto:<?= h(SUPPORT_EMAIL) ?>"><?= h(SUPPORT_EMAIL) ?></a>
                </div>
            </li>
            <li>
                <i class="fas fa-user-shield"></i>
                <div>
                    <span class="ci-label">Privacy Email</span>
                    <a href="mailto:<?= h(PRIVACY_EMAIL) ?>"><?= h(PRIVACY_EMAIL) ?></a>
                </div>
            </li>
            <li>
                <i class="fas fa-map-marker-alt"></i>
                <div>
                    <span class="ci-label">Address</span>
                    <span><?= h(SITE_ADDRESS) ?></span>
                </div>
            </li>
            <li>
                <i class="fas fa-clock"></i>
                <div>
                    <span class="ci-label">Support Hours</span>
                    <span><?= h(SUPPORT_HOURS) ?></span>
                </div>
            </li>
        </ul>
    </div>

    <!-- Form column -->
    <div class="contact-form-card">
        <h2><i class="fas fa-paper-plane"></i> Send us a Message</h2>

        <?php if ($success): ?>
            <div class="alert alert-ok"><i class="fas fa-check-circle"></i> <?= $success ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-err">
                <i class="fas fa-exclamation-circle"></i>
                <ul style="margin:6px 0 0 20px;padding:0;">
                    <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="contact-form">
            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_contact']) ?>">

            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" required maxlength="120"
                           value="<?= h($_POST['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required maxlength="190"
                           value="<?= h($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Mobile (optional)</label>
                    <input type="text" name="phone" maxlength="20"
                           value="<?= h($_POST['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Subject *</label>
                    <input type="text" name="subject" required maxlength="200"
                           value="<?= h($_POST['subject'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Message * (10–2000 characters)</label>
                <textarea name="message" rows="6" required maxlength="2000"
                          minlength="10"><?= h($_POST['message'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn-blue btn-block">
                <i class="fas fa-paper-plane"></i> Submit Message
            </button>

            <small style="display:block;margin-top:10px;color:#6b7280;font-size:.82rem;text-align:center;">
                <i class="fas fa-lock"></i> We protect your data. See our
                <a href="privacy.php">Privacy Policy</a>.
            </small>
        </form>
    </div>
</div>

<?php include 'inc/foot.php'; ?>