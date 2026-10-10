<?php
/*
 * Forgot password, step 1 (FR-AUTH-09): the guest enters their email and we send a 6-digit
 * code to it; step 2 is account/reset-password.php. What happens next looks the same
 * whether or not the email has an account, so the page can't be used to find out who has one.
 */
require_once '../includes/init.php';
require_once '../includes/mail.php';
require_once '../includes/auth-layout.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    clean_old_tokens();
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter the email address you created your account with.';
    } else {
        $stmt = db()->prepare('SELECT id, name, email FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && code_wait_seconds($user['id'], 'reset') === 0) {
            send_code_email($user, create_email_code($user['id'], 'reset'), 'reset');
        }
        $_SESSION['reset'] = ['email' => $email, 'user' => $user ? (int) $user['id'] : 0, 'tries' => 0];
        redirect('account/reset-password.php');
    }
}

$page_title = 'Forgot your password | ' . HOTEL_NAME;
$page_scripts = ['assets/js/auth.js'];
$flash_inline = true;
include '../includes/header.php';
auth_open('Forgot your password?', 'Enter your email and we\'ll send you a 6-digit code to choose a new one.', 'slides/slide-2.jpg');
?>
<form method="post" novalidate class="auth-form">
    <?= csrf_field() ?>
    <?php text_input('email', 'Email', 'email', 'email', old('email', $_SESSION['reset']['email'] ?? ''), $error, 'maxlength="191"'); ?>
    <button type="submit" class="btn-primary-red w-100">SEND CODE</button>
</form>
<p class="auth-help">Can't get into your email? Call the front desk on <a href="tel:<?= e(str_replace(' ', '', HOTEL_PHONE)) ?>"><?= e(HOTEL_PHONE) ?></a> and they can reset it for you.</p>
<p class="auth-switch">Remembered it? <a href="<?= url('account/login.php') ?>">Log in</a></p>
<?php
auth_close();
include '../includes/footer.php';
