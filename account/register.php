<?php
/*
 * Create an account (FR-AUTH-01, FR-AUTH-05, FR-AUTH-08). With email verification on, a
 * 6-digit code is emailed and the guest confirms it on account/verify-email.php.
 * Signing up again with an email that was never confirmed updates that account and sends a new code.
 */
require_once '../includes/init.php';
require_once '../includes/mail.php';
require_once '../includes/auth-layout.php';

if (is_logged_in()) {
    redirect('account/index.php');
}

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    // Check everything on the server, not just in the browser
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['name'] = 'Enter your full name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!preg_match('/^\+?[0-9 \-]{7,20}$/', $phone)) {
        $errors['phone'] = 'Enter a phone number, e.g. 0300 1234567.';
    }
    if ($problem = password_problem($password)) {
        $errors['password'] = $problem;
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'The passwords don\'t match.';
    }

    $unconfirmedId = 0;
    if (!isset($errors['email'])) {
        $stmt = db()->prepare('SELECT id, email_verified_at FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $existing = $stmt->fetch();
        if ($existing && ($existing['email_verified_at'] !== null || !EMAIL_VERIFICATION)) {
            $errors['email'] = 'There is already an account with this email. Log in, or reset your password.';
        } elseif ($existing) {
            $unconfirmedId = (int) $existing['id']; // signed up before but never typed the code
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($unconfirmedId) {
            db()->prepare('UPDATE users SET name = ?, phone = ?, password_hash = ? WHERE id = ?')->execute([$name, $phone, $hash, $unconfirmedId]);
            $userId = $unconfirmedId;
        } else {
            db()->prepare('INSERT INTO users (name, email, phone, password_hash, role, email_verified_at)
                           VALUES (?, ?, ?, ?, \'customer\', ' . (EMAIL_VERIFICATION ? 'NULL' : 'NOW()') . ')')
                ->execute([$name, $email, $phone, $hash]);
            $userId = (int) db()->lastInsertId();
        }

        if (EMAIL_VERIFICATION) {
            if (code_wait_seconds($userId, 'verify') === 0) {
                send_code_email(['name' => $name, 'email' => $email], create_email_code($userId, 'verify'), 'verify');
            }
            $_SESSION['verify_user'] = $userId;
            $_SESSION['verify_next'] = $next;
            // Shown on screen whether or not the email could be sent (D4)
            flash('success', 'Almost done! We\'ve emailed a 6-digit code to ' . $email . '.');
            redirect('account/verify-email.php');
        }

        log_in_user($userId);
        flash('success', 'Welcome, ' . explode(' ', $name)[0] . '! Your account is ready.');
        redirect($next);
    }
}

$page_title = 'Create an account | ' . HOTEL_NAME;
$page_scripts = ['assets/js/auth.js'];
$flash_inline = true;
include '../includes/header.php';

auth_open('Create your account', 'Book rooms, order food and keep all your stays in one place.', 'slides/slide-4.jpg');
?>
<?php if ($errors): ?>
    <div class="notice notice-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span>Please fix the highlighted fields.</span></div>
<?php endif; ?>
<form method="post" novalidate class="auth-form">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <?php text_input('name', 'Full name', 'text', 'name', old('name'), $errors['name'] ?? '', 'maxlength="100"'); ?>
    <?php text_input('email', 'Email', 'email', 'email', old('email'), $errors['email'] ?? '', 'maxlength="191"'); ?>
    <?php text_input('phone', 'Phone', 'tel', 'tel', old('phone'), $errors['phone'] ?? '', 'maxlength="20" placeholder="0300 1234567"'); ?>
    <?php password_input('password', 'Password', 'new-password', true, $errors['password'] ?? ''); ?>
    <?php password_input('password_confirm', 'Confirm password', 'new-password', false, $errors['password_confirm'] ?? ''); ?>
    <button type="submit" class="btn-primary-red w-100">CREATE ACCOUNT</button>
</form>
<p class="auth-switch">Already have an account? <a href="<?= url('account/login.php' . ($next !== 'account/index.php' ? '?next=' . urlencode($next) : '')) ?>">Log in</a></p>
<?php
auth_close();
include '../includes/footer.php';
