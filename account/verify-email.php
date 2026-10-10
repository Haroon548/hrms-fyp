<?php
/*
 * Confirm a new account with the 6-digit code sent by email (FR-AUTH-08).
 * Reached after creating an account, or after logging in to an account that isn't confirmed yet.
 * The account waiting for its code is kept in the session, so this page never shows whose
 * emails have accounts. A right code confirms the email and logs the guest in.
 */
require_once '../includes/init.php';
require_once '../includes/mail.php';
require_once '../includes/auth-layout.php';

$stmt = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ? AND email_verified_at IS NULL');
$stmt->execute([(int) ($_SESSION['verify_user'] ?? 0)]);
$pending = $stmt->fetch();
if (!$pending) {
    unset($_SESSION['verify_user']);
    redirect(is_logged_in() ? 'account/index.php' : 'account/login.php');
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['action'] ?? '') === 'resend') {
        if (code_wait_seconds($pending['id'], 'verify') === 0) {
            send_code_email($pending, create_email_code($pending['id'], 'verify'), 'verify');
            flash('success', 'A new code is on its way to ' . masked_email($pending['email']) . '.');
        }
        redirect('account/verify-email.php');
    }
    $error = check_email_code($pending['id'], 'verify', $_POST['code'] ?? '');
    if ($error === '') {
        db()->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')->execute([$pending['id']]);
        $next = safe_next($_SESSION['verify_next'] ?? '', $pending['role'] === 'admin' ? 'admin/index.php' : 'account/index.php');
        unset($_SESSION['verify_user'], $_SESSION['verify_next']);
        log_in_user($pending['id']);
        flash('success', 'Your email is confirmed. Welcome, ' . explode(' ', $pending['name'])[0] . '!');
        redirect($next);
    }
}

$page_title = 'Confirm your email | ' . HOTEL_NAME;
$page_scripts = ['assets/js/auth.js'];
$flash_inline = true;
include '../includes/header.php';
auth_open('Check your email', 'We sent a 6-digit code to ' . masked_email($pending['email']) . '. Enter it to confirm your account.', 'slides/slide-4.jpg');
?>
<form method="post" novalidate class="auth-form" data-code-form>
    <?= csrf_field() ?>
    <?php code_input($error); ?>
    <p class="field-help">The code works for <?= CODE_MINUTES ?> minutes.</p>
    <button type="submit" class="btn-primary-red w-100">CONFIRM EMAIL</button>
</form>
<?php resend_code_button(code_wait_seconds($pending['id'], 'verify')); ?>
<p class="auth-switch">Wrong email address? <a href="<?= url('account/register.php') ?>">Create the account again</a></p>
<?php
auth_close();
include '../includes/footer.php';
