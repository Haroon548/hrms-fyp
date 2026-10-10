<?php
/*
 * Accounts: who is logged in, logging in and out, password rules,
 * and the single-use codes sent by email (FR-AUTH, NFR-01, NFR-10).
 * Loaded by init.php.
 */

const MAX_FAILED_LOGINS = 5;   // wrong passwords in a row before the account is locked
const LOCK_MINUTES = 10;       // how long it stays locked (FR-AUTH-10)

// The logged-in user's row, or null for visitors.
function current_user()
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT id, name, email, phone, role, email_verified_at FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
    }
    return $user;
}

function is_logged_in()
{
    return current_user() !== null;
}

function is_admin()
{
    return is_logged_in() && current_user()['role'] === 'admin';
}

// Put at the top of pages for logged-in users. Visitors go to the login page
// and come back here afterwards.
function require_login()
{
    if (!is_logged_in()) {
        flash('info', 'Please log in to continue.');
        redirect('account/login.php?next=' . urlencode(current_page()));
    }
}

// Put at the top of every admin page (FR-AUTH-04).
function require_admin()
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Only hotel staff with an admin account can open this page.');
    }
}

// This page's address inside the site, e.g. "account/index.php?tab=profile".
function current_page()
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $base = base_path();
    if ($base !== '' && strpos($uri, $base) === 0) {
        $uri = substr($uri, strlen($base));
    }
    return ltrim($uri, '/');
}

// Only allow "next" links inside this site, so a login link can't send people elsewhere.
function safe_next($next, $default = 'account/index.php')
{
    $next = (string) $next;
    if ($next === '' || preg_match('#^(/|\\\\|[a-z][a-z0-9+.-]*:)#i', $next) || strpos($next, '//') !== false) {
        return $default;
    }
    return $next;
}

function log_in_user($userId)
{
    session_regenerate_id(true); // new session id, so an old one can't be reused
    $_SESSION['user_id'] = (int) $userId;
}

function log_out_user()
{
    $_SESSION = [];
    session_regenerate_id(true);
}

// Returns what's wrong with a new password, or '' if it's fine.
function password_problem($password)
{
    if (strlen($password) < 8) {
        return 'Use at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Use at least one letter and one number.';
    }
    return '';
}

/* ---------- Email codes (sign-up, forgot password, change password) ----------
   A 6-digit code is emailed, the way banks and Google do it. Only a hash of the code is
   saved, so someone who sees the database can't read it. Each code works once, for
   15 minutes, and stops working after 5 wrong tries; a new one can be sent once a minute
   (NFR-10). Purposes: 'verify' (new account), 'reset' (forgot password), 'password'
   (changing the password while logged in), 'email' (changing the email: sent to the new one). */

const CODE_MINUTES = 15;
const CODE_TRIES = 5;
const CODE_RESEND_SECONDS = 60;

function code_hash($userId, $purpose, $code)
{
    return hash('sha256', $userId . '|' . $purpose . '|' . $code);
}

// Makes a new code for a user; older unused codes of the same kind stop working.
function create_email_code($userId, $purpose)
{
    db()->prepare('UPDATE email_tokens SET used_at = NOW() WHERE user_id = ? AND purpose = ? AND used_at IS NULL')
        ->execute([$userId, $purpose]);
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    db()->prepare('INSERT INTO email_tokens (user_id, purpose, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))')
        ->execute([$userId, $purpose, code_hash($userId, $purpose, $code), CODE_MINUTES]);
    return $code;
}

// Checks a typed code. Returns '' if it is right (and uses it up), or a message saying what's wrong.
function check_email_code($userId, $purpose, $code)
{
    $code = preg_replace('/\D/', '', (string) $code);
    $stmt = db()->prepare('SELECT id, token_hash, attempts FROM email_tokens
                           WHERE user_id = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()
                           ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId, $purpose]);
    $row = $stmt->fetch();
    if (!$row) {
        return 'This code has expired or was replaced by a newer one. Press "Send a new code".';
    }
    if (strlen($code) === 6 && hash_equals($row['token_hash'], code_hash($userId, $purpose, $code))) {
        db()->prepare('UPDATE email_tokens SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);
        return '';
    }
    $tries = $row['attempts'] + 1;
    if ($tries >= CODE_TRIES) {
        db()->prepare('UPDATE email_tokens SET attempts = ?, used_at = NOW() WHERE id = ?')->execute([$tries, $row['id']]);
        return 'That code is wrong, and it has now stopped working for your safety. Press "Send a new code".';
    }
    db()->prepare('UPDATE email_tokens SET attempts = ? WHERE id = ?')->execute([$tries, $row['id']]);
    $left = CODE_TRIES - $tries;
    return 'That code isn\'t right. Check the latest email and try again (' . $left . ' ' . ($left === 1 ? 'try' : 'tries') . ' left).';
}

// Seconds before another code may be sent (0 = now).
function code_wait_seconds($userId, $purpose)
{
    $stmt = db()->prepare('SELECT TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) FROM email_tokens WHERE user_id = ? AND purpose = ?');
    $stmt->execute([$userId, $purpose]);
    $since = $stmt->fetchColumn();
    return $since === null ? 0 : max(0, CODE_RESEND_SECONDS - (int) $since);
}

// "ali.khan@gmail.com" → "al•••••@gmail.com", shown on the code pages
function masked_email($email)
{
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($name, 0, 2) . str_repeat('•', max(3, mb_strlen($name) - 2)) . '@' . $domain;
}

// Tidy-up when an account page opens (no scheduled jobs on free hosting, D16):
// remove codes that expired more than a week ago.
function clean_old_tokens()
{
    db()->exec('DELETE FROM email_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
}
