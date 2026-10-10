<?php
/*
 * Log in (FR-AUTH-02, FR-AUTH-08, FR-AUTH-10).
 * After 5 wrong passwords in a row the account is locked for 10 minutes.
 */
require_once '../includes/init.php';
require_once '../includes/mail.php';
require_once '../includes/auth-layout.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'account/index.php');
}

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '', '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT id, password_hash, role, email_verified_at, failed_logins, locked_until FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
        $minutes = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
        $error = 'Too many wrong passwords. For your safety this account is locked for ' . $minutes . ' more minute' . ($minutes === 1 ? '' : 's') . '. You can also reset your password.';
    } elseif (!$user || !password_verify($password, $user['password_hash'])) {
        if (!$user) {
            // Same amount of work as a real check, so the response time doesn't reveal which emails have accounts
            password_verify($password, '$2y$12$xVMpQcGXM18.yD6XULS/a.XX.Jp7qLVH7C8MOk3T1vCS2AflnszKe');
            $error = 'The email or password is wrong.';
        } else {
            $failed = $user['failed_logins'] + 1;
            if ($failed >= MAX_FAILED_LOGINS) {
                db()->prepare('UPDATE users SET failed_logins = 0, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?')
                    ->execute([LOCK_MINUTES, $user['id']]);
                $error = 'Too many wrong passwords. For your safety this account is locked for ' . LOCK_MINUTES . ' minutes. You can also reset your password.';
            } else {
                db()->prepare('UPDATE users SET failed_logins = ? WHERE id = ?')->execute([$failed, $user['id']]);
                $left = MAX_FAILED_LOGINS - $failed;
                $error = 'The email or password is wrong.' . ($left <= 2 ? ' ' . $left . ' more ' . ($left === 1 ? 'try' : 'tries') . ' before the account is locked for ' . LOCK_MINUTES . ' minutes.' : '');
            }
        }
    } elseif (EMAIL_VERIFICATION && $user['email_verified_at'] === null) {
        // Right password, but the email was never confirmed: send a code (at most one a minute)
        if (code_wait_seconds($user['id'], 'verify') === 0) {
            $stmt = db()->prepare('SELECT name, email FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            send_code_email($stmt->fetch(), create_email_code($user['id'], 'verify'), 'verify');
        }
        $_SESSION['verify_user'] = (int) $user['id'];
        $_SESSION['verify_next'] = $next;
        flash('info', 'Please confirm your email first. We\'ve sent a 6-digit code to ' . $email . '.');
        redirect('account/verify-email.php');
    } else {
        // Correct: clear the counter, upgrade the hash if PHP's default changed, and log in
        db()->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?')->execute([$user['id']]);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        log_in_user($user['id']);
        redirect($next !== '' ? $next : ($user['role'] === 'admin' ? 'admin/index.php' : 'account/index.php'));
    }
}

$page_title = 'Log in | ' . HOTEL_NAME;
$page_scripts = ['assets/js/auth.js'];
$flash_inline = true;
include '../includes/header.php';

auth_open('Welcome back', 'Log in to see your bookings, orders and invoices.', 'slides/slide-3.jpg');
?>
<?php if ($error): ?>
    <div class="notice notice-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= e($error) ?></span></div>
<?php endif; ?>
<form method="post" novalidate class="auth-form">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <?php text_input('email', 'Email', 'email', 'email', old('email', $_GET['email'] ?? ''), '', 'maxlength="191"'); ?>
    <?php password_input('password', 'Password', 'current-password'); ?>
    <p class="auth-forgot"><a href="<?= url('account/forgot-password.php') ?>">Forgot your password?</a></p>
    <button type="submit" class="btn-primary-red w-100">LOG IN</button>
</form>
<p class="auth-switch">New here? <a href="<?= url('account/register.php' . ($next !== '' ? '?next=' . urlencode($next) : '')) ?>">Create an account</a></p>
<?php
auth_close();
include '../includes/footer.php';
