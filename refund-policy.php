<?php
require_once 'inc/auth.php';
require_once 'inc/site-config.php';
$title = "Refund Policy";

include 'inc/head.php';

$page_title = "Refund Policy";
$page_intro = "Applicable only to paid services, when introduced.";
$page_icon  = "fa-undo-alt";
legal_page_start();
?>

<div class="alert alert-info">
    <i class="fas fa-info-circle"></i>
    <strong>Current status:</strong> Local Jobs does not currently offer any paid services.
    Registration for job seekers and employers is free. This policy will apply only when paid employer features are introduced.
</div>

<h2><i class="fas fa-credit-card"></i> 1. Subscription Charges</h2>
<p>When introduced, paid employer subscriptions will be clearly displayed with their prices before purchase. No charges will apply without your explicit confirmation.</p>

<h2><i class="fas fa-receipt"></i> 2. Payment Confirmation</h2>
<p>After a successful payment, you will receive an on-screen confirmation and, where available, an email or SMS receipt with a transaction reference.</p>

<h2><i class="fas fa-times-circle"></i> 3. Failed Payments</h2>
<p>If a payment fails during processing, no amount should be deducted. If you notice a deduction despite a failure message, contact us with your transaction reference; we will verify with the payment provider.</p>

<h2><i class="fas fa-copy"></i> 4. Duplicate Payments</h2>
<p>If you are charged twice for the same purchase, contact us with both transaction references. Duplicate charges that are verified will be refunded to the original payment method.</p>

<h2><i class="fas fa-undo"></i> 5. Refund Request Process</h2>
<p>To request a refund, email <a href="mailto:<?= h(SUPPORT_EMAIL) ?>"><?= h(SUPPORT_EMAIL) ?></a> with:</p>
<ul>
    <li>Your registered account email</li>
    <li>Transaction reference(s)</li>
    <li>Reason for the refund request</li>
</ul>

<h2><i class="fas fa-check-circle"></i> 6. Refund Eligibility</h2>
<p>Refund eligibility depends on the specific paid service and its stated terms. In general, refunds are considered for:</p>
<ul>
    <li>Duplicate charges</li>
    <li>Payments for services that were not delivered due to a verified technical error</li>
    <li>Other cases as required by applicable law</li>
</ul>
<p>Refunds are generally not provided for services that have already been delivered or used.</p>

<h2><i class="fas fa-search"></i> 7. Refund Review</h2>
<p>All refund requests are reviewed on a case-by-case basis. We may ask for additional information to verify the transaction.</p>

<h2><i class="fas fa-clock"></i> 8. Refund Processing</h2>
<p>Verified refunds will be processed to the original payment method. Processing times depend on the payment provider and your bank. We do not guarantee a specific timeframe.</p>

<h2><i class="fas fa-envelope"></i> 9. Contact</h2>
<ul>
    <li><strong>Support Email:</strong> <a href="mailto:<?= h(SUPPORT_EMAIL) ?>"><?= h(SUPPORT_EMAIL) ?></a></li>
    <li><strong>Phone:</strong> <?= h(SUPPORT_PHONE) ?></li>
    <li><strong>Address:</strong> <?= h(SITE_ADDRESS) ?></li>
</ul>

<?php legal_page_end(); ?>
<?php include 'inc/foot.php'; ?>