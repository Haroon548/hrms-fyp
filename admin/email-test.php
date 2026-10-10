<?php
/*
 * Admin: check that email works (D9, FR-MAIL-01). Shows whether the Gmail details are in
 * config/config.php, sends a test email to any address, and explains a failure in plain words
 * (e.g. "Gmail refused the login: check the app password").
 */
require_once '../includes/init.php';
require_once '../includes/mail.php';
require_once '../includes/admin-layout.php';
require_admin();

$result = null;
$to = current_user()['email'];

if (admin_action() === 'send') {
    $to = trim($_POST['to'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $result = [false, 'Enter the email address to send the test to.'];
    } else {
        $started = microtime(true);
        $sent = send_email($to, '', 'Test email from ' . HOTEL_NAME,
            '<h1 style="font-size:22px;color:#D21242;font-weight:normal;margin:0 0 16px;">Email is working</h1>'
            . '<p>This test was sent from the admin panel on ' . e(date('l j F Y, g:i A')) . '. Booking confirmations, codes and invoices will arrive like this.</p>',
            "Email is working.\n\nThis test was sent from the admin panel on " . date('l j F Y, g:i A') . '.');
        $result = $sent
            ? [true, 'Sent to ' . $to . ' in ' . round(microtime(true) - $started, 1) . ' s. Check that inbox (and the spam folder the first time).']
            : [false, email_error()];
    }
}

admin_open('Email check', 'email', 'Make sure booking confirmations, sign-up codes and invoices reach guests');
?>
<section class="admin-card">
    <h2><i class="fa-regular fa-envelope" aria-hidden="true"></i> Settings</h2>
    <?php if (email_is_set_up()): ?>
        <p class="mb-1"><?= status_badge('shown') ?> Emails are sent from <strong><?= e(SMTP_USER) ?></strong> through <?= e(SMTP_HOST) ?>:<?= (int) SMTP_PORT ?>, with the name "<?= e(SMTP_FROM_NAME) ?>".</p>
        <p class="admin-muted mb-0">Guests who reply reach <?= e(HOTEL_EMAIL) ?>.</p>
    <?php else: ?>
        <p><?= status_badge('maintenance') ?> <strong>Not set up yet.</strong> Guests see every confirmation and code on screen, but no email leaves the site. A copy of each email is kept in <code>logs/emails/</code>.</p>
        <ol class="email-steps">
            <li>Sign in to the hotel's Gmail account and turn on <strong>2-Step Verification</strong> (Google Account → Security).</li>
            <li>Open <strong>myaccount.google.com/apppasswords</strong>, type a name such as "Hotel website" and press Create. Google shows a 16-letter app password.</li>
            <li>In <code>config/config.php</code>, put the Gmail address in <code>SMTP_USER</code> and the 16 letters (no spaces) in <code>SMTP_PASS</code>, and save.</li>
            <li>Come back here and send a test email.</li>
        </ol>
    <?php endif; ?>
    <p class="admin-muted mb-0">Sign-up email codes are <?= EMAIL_VERIFICATION ? '<strong>on</strong>' : '<strong>off</strong> (new accounts work straight away)' ?>: <code>EMAIL_VERIFICATION</code> in config.php.</p>
</section>

<section class="admin-card">
    <h2><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Send a test email</h2>
    <?php if ($result): ?>
        <div class="notice notice-<?= $result[0] ? 'success' : 'error' ?>" role="<?= $result[0] ? 'status' : 'alert' ?>">
            <i class="fa-solid <?= $result[0] ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" aria-hidden="true"></i><span><?= e($result[1]) ?></span></div>
    <?php endif; ?>
    <form method="post" class="admin-inline-form" data-checkout-form>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send">
        <div class="form-field"><label for="to">Send to</label><input type="email" id="to" name="to" value="<?= e($to) ?>" maxlength="191" required></div>
        <div class="admin-inline-buttons"><button type="submit" class="btn-primary-red btn-complete" data-busy="SENDING…"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Send test</button></div>
    </form>
</section>
<script src="<?= asset('assets/js/checkout.js') ?>"></script>
<?php admin_close();
