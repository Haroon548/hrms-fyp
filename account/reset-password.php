<?php
/*
 * Forgot password, step 2 (FR-AUTH-09): the 6-digit code from the email and a new password.
 * A right code saves the password, unlocks the account (FR-AUTH-10), confirms the email
 * (the code proves the inbox is theirs) and sends a "your password was changed" alert.
 * For an email without an account the page behaves the same and every code is "wrong".
 */
require_once '../includes/init.php';
require_once '../includes/mail.php';
require_once '../includes/auth-layout.php';

$reset = $_SESSION['reset'] ?? null;
if (!$reset) {
    redirect('account/forgot-password.php');
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['action'] ?? '') === 'resend') {
        $stmt = db()->prepare('SELECT id, name, email FROM users WHERE id = ?');
        $stmt->execute([$reset['user']]);
        if (($user = $stmt->fetch()) && code_wait_seconds($user['id'], 'reset') === 0) {
            send_code_email($user, create_email_code($user['id'], 'reset'), 'reset');
        }
        flash('success', 'If there\'s an account for ' . $reset['email'] . ', a new code is on its way.');
        redirect('account/reset-password.php');
    }

    $password = $_POST['password'] ?? '';
    if ($problem = password_problem($password)) {
        $errors['password'] = $problem;
    } elseif ($password !== ($_POST['password_confirm'] ?? '')) {
        $errors['password_confirm'] = 'The passwords don\'t match.';
    }
    // The code is only checked (and used up) once the new password is fine
    if (!$errors) {
        if ($reset['user']) {
            $codeProblem = check_email_code($reset['user'], 'reset', $_POST['code'] ?? '');
        } else {
            // No account: answer exactly like a wrong code
            $_SESSION['reset']['tries'] = ++$reset['tries'];
            $left = CODE_TRIES - $reset['tries'];
            $codeProblem = $left > 0 ? 'That code isn\'t right. Check the latest email and try again (' . $left . ' ' . ($left === 1 ? 'try' : 'tries') . ' left).'
                : 'That code is wrong, and it has now stopped working for your safety. Press "Send a new code".';
        }
        if ($codeProblem !== '') {
            $errors['code'] = $codeProblem;
        }
    }
    if (!$errors) {
        db()->prepare('UPDATE users SET password_hash = ?, failed_logins = 0, locked_until = NULL,
                       email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user']]);
        $stmt = db()->prepare('SELECT name, email FROM users WHERE id = ?');
        $stmt->execute([$reset['user']]);
        send_password_changed_email($stmt->fetch());
        unset($_SESSION['reset']);
        flash('success', 'Your password has been changed. Log in with your new password.');
        redirect('account/login.php?email=' . urlencode($reset['email']));
    }
}

$page_title = 'Choose a new password | ' . HOTEL_NAME;
$page_scripts = ['assets/js/auth.js'];
$flash_inline = true;
include '../includes/header.php';
auth_open('Check your email', 'If there\'s an account for ' . masked_email($reset['email']) . ', we\'ve sent it a 6-digit code. Enter it with your new password.', 'slides/slide-2.jpg');
?>
<form method="post" novalidate class="auth-form">
    <?= csrf_field() ?>
    <?php code_input($errors['code'] ?? ''); ?>
    <?php password_input('password', 'New password', 'new-password', true, $errors['password'] ?? ''); ?>
    <?php password_input('password_confirm', 'Confirm new password', 'new-password', false, $errors['password_confirm'] ?? ''); ?>
    <button type="submit" class="btn-primary-red w-100">SAVE NEW PASSWORD</button>
</form>
<?php resend_code_button($reset['user'] ? code_wait_seconds($reset['user'], 'reset') : 0); ?>
<p class="auth-switch">Wrong email? <a href="<?= url('account/forgot-password.php') ?>">Use another one</a></p>
<?php
auth_close();
include '../includes/footer.php';
